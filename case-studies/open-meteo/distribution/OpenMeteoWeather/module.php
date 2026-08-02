<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/OpenMeteo/FieldCatalog.php';
require_once __DIR__ . '/../libs/OpenMeteo/ForecastPoint.php';
require_once __DIR__ . '/../libs/OpenMeteo/ForecastSeries.php';
require_once __DIR__ . '/../libs/OpenMeteo/ParsedForecast.php';
require_once __DIR__ . '/../libs/OpenMeteo/IntervalAligner.php';
require_once __DIR__ . '/../libs/OpenMeteo/RequestBuilder.php';
require_once __DIR__ . '/../libs/OpenMeteo/ResponseParser.php';
require_once __DIR__ . '/../libs/OpenMeteo/ForecastStateReducer.php';
require_once __DIR__ . '/../libs/OpenMeteo/WeatherForecastProjector.php';
if (!function_exists('SAEF_EnsureProfile')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/object/EnsureProfile.php';
}
if (!function_exists('SAEF_CreateConfigurationHash')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/diagnostics/ConfigurationHash.php';
}
require_once __DIR__ . '/../libs/OpenMeteo/Profiles.php';

use SAEF\CaseStudy\OpenMeteo\FieldCatalog;
use SAEF\CaseStudy\OpenMeteo\ForecastStateReducer;
use SAEF\CaseStudy\OpenMeteo\Profiles;
use SAEF\CaseStudy\OpenMeteo\RequestBuilder;
use SAEF\CaseStudy\OpenMeteo\ResponseParser;
use SAEF\CaseStudy\OpenMeteo\WeatherForecastProjector;

class OpenMeteoWeather extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIGURATION_ERROR = 200;
    private const MAX_RETRY_STEP = 4;
    private const SEMAPHORE_TIMEOUT_MILLISECONDS = 1000;
    private const MAXIMUM_CACHE_QUERY_SECONDS = 864000;

    /** @var array<string, int> */
    private const DATA_STATE_VALUES = [
        ForecastStateReducer::STATE_UNCONFIGURED => 0,
        'fetching' => 1,
        ForecastStateReducer::STATE_CURRENT => 2,
        ForecastStateReducer::STATE_STALE => 3,
        ForecastStateReducer::STATE_WARNING => 4,
        ForecastStateReducer::STATE_ERROR => 5,
    ];

    /** @var array<int, int> */
    private const RETRY_INTERVAL_MINUTES = [
        1 => 5,
        2 => 15,
        3 => 30,
    ];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyBoolean('LocationConfigured', false);
        $this->RegisterPropertyFloat('Latitude', 0.0);
        $this->RegisterPropertyFloat('Longitude', 0.0);
        $this->RegisterPropertyBoolean('UseElevation', false);
        $this->RegisterPropertyFloat('Elevation', 0.0);
        $this->RegisterPropertyString('Timezone', 'Europe/Berlin');
        $this->RegisterPropertyInteger('ForecastDays', 7);
        $this->RegisterPropertyBoolean('WithSoil', false);
        $this->RegisterPropertyInteger('PollingIntervalMinutes', 60);
        $this->RegisterPropertyInteger('HttpTimeoutSeconds', 10);
        $this->RegisterPropertyInteger('StaleAfterMinutes', 180);

        $this->RegisterAttributeString('RuntimeState', '');
        $this->RegisterAttributeString('ForecastCache', '');

        $this->RegisterTimer(
            'UpdateData',
            0,
            'OMWEATHER_UpdateData($_IPS["TARGET"]);'
        );
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        Profiles::ensure();
        $this->registerOperationalVariables();
        $this->registerWeatherVariables();
        $this->registerSoilVariables();

        if (!$this->ReadPropertyBoolean('LocationConfigured')) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetValue('DataState', 0);
            $this->SetStatus(self::STATUS_INACTIVE);

            return;
        }

        try {
            RequestBuilder::normalizeLocationConfiguration(
                $this->locationConfiguration(),
                10
            );
            $this->validateRuntimePolicy();
            $state = $this->runtimeState();
            $this->writeRuntimeState($state);
            $this->publishOperationalState($state, $this->currentTimestamp());
            $this->scheduleNormalPolling();
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetValue('DataState', 5);
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);
        }
    }

    public function UpdateData(): string
    {
        if (!$this->ReadPropertyBoolean('LocationConfigured')) {
            return $this->result(false, 'configuration_missing');
        }

        try {
            $configuration = $this->requestConfiguration();
            RequestBuilder::normalizeLocationConfiguration($configuration, 10);
            $this->validateRuntimePolicy();
        } catch (Throwable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetValue('DataState', 5);
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);

            return $this->result(false, 'configuration_invalid');
        }

        $lockName = 'OpenMeteoWeather.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lockName, self::SEMAPHORE_TIMEOUT_MILLISECONDS)) {
            return $this->result(false, 'busy');
        }

        try {
            return $this->executeUpdate($configuration);
        } finally {
            if (!IPS_SemaphoreLeave($lockName)) {
                IPS_LogMessage('OpenMeteoWeather', 'Instance lock could not be released.');
            }
        }
    }

    public function GetLocationDescriptor(): string
    {
        if (!$this->ReadPropertyBoolean('LocationConfigured')) {
            return $this->result(false, 'configuration_missing');
        }

        try {
            $location = RequestBuilder::normalizeLocationConfiguration(
                $this->locationConfiguration(),
                10
            );

            return json_encode(
                ['success' => true, 'location' => $location],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable) {
            return $this->result(false, 'configuration_invalid');
        }
    }

    public function GetCurrentJson(): string
    {
        $cache = $this->readCache();
        if ($cache === null) {
            return $this->result(false, 'cache_empty');
        }

        return $this->encodeResult(['success' => true, 'data' => $cache['current']]);
    }

    public function GetHourlyForecastJson(int $from, int $to, string $fieldsJson): string
    {
        return $this->forecastSlice('hourly', $from, $to, $fieldsJson);
    }

    public function GetDailyForecastJson(int $from, int $to, string $fieldsJson): string
    {
        return $this->forecastSlice('daily', $from, $to, $fieldsJson);
    }

    /** @param array<string, mixed> $configuration */
    private function executeUpdate(array $configuration): string
    {
        $attemptedAt = $this->currentTimestamp();
        $state = $this->runtimeState();
        $this->SetValue('DataState', self::DATA_STATE_VALUES['fetching']);
        $this->SetValue('LastFetchAttempt', $attemptedAt);

        try {
            $url = RequestBuilder::weather($configuration);
            try {
                $body = $this->fetchUrl(
                    $url,
                    $this->ReadPropertyInteger('HttpTimeoutSeconds') * 1000
                );
            } catch (Throwable) {
                return $this->recordFailure($state, $attemptedAt, 'transport_error', true);
            }
            if ($body === false) {
                return $this->recordFailure($state, $attemptedAt, 'transport_error', true);
            }

            $withSoil = $this->ReadPropertyBoolean('WithSoil');
            $forecast = ResponseParser::parse(
                $body,
                FieldCatalog::weatherCurrentFields(),
                FieldCatalog::weatherHourlyFields($withSoil),
                FieldCatalog::weatherDailyFields()
            );
            $cache = WeatherForecastProjector::project($forecast, $withSoil, $attemptedAt);
            $state = ForecastStateReducer::success(
                $state,
                $attemptedAt,
                $cache['validFrom'],
                $cache['validTo']
            );

            $this->WriteAttributeString('ForecastCache', $this->encodeCache($cache));
            foreach ($cache['publicValues'] as $ident => $value) {
                $this->SetValue($ident, $value);
            }
            $this->writeRuntimeState($state);
            $this->publishOperationalState($state, $attemptedAt);
            $this->scheduleNormalPolling();
            $this->SetStatus(self::STATUS_ACTIVE);

            return $this->result(true, 'ok');
        } catch (InvalidArgumentException) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);

            return $this->recordFailure(
                $state,
                $attemptedAt,
                'configuration_invalid',
                false
            );
        } catch (Throwable) {
            return $this->recordFailure($state, $attemptedAt, 'response_invalid', true);
        }
    }

    /**
     * Kept protected so offline module tests can inject a transport without network access.
     */
    protected function fetchUrl(string $url, int $timeoutMilliseconds): string|false
    {
        return Sys_GetURLContentEx($url, [
            'Timeout' => $timeoutMilliseconds,
            'VerifyPeer' => true,
            'VerifyHost' => true,
        ]);
    }

    /**
     * @param array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * } $state
     */
    private function recordFailure(
        array $state,
        int $attemptedAt,
        string $errorCode,
        bool $retryable
    ): string {
        $state = ForecastStateReducer::failure($state, $attemptedAt, $errorCode, $retryable);
        $state = ForecastStateReducer::evaluateFreshness(
            $state,
            $attemptedAt,
            $this->ReadPropertyInteger('StaleAfterMinutes') * 60
        );
        $this->writeRuntimeState($state);
        $this->publishOperationalState($state, $attemptedAt);
        if ($retryable) {
            $this->scheduleAfterFailure($state['retryCount']);
        }
        IPS_LogMessage('OpenMeteoWeather', 'Update failed (' . $errorCode . ').');

        return $this->result(false, $errorCode);
    }

    /**
     * @return array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * }
     */
    private function runtimeState(): array
    {
        return ForecastStateReducer::fromJson(
            $this->ReadAttributeString('RuntimeState'),
            $this->configurationHash(),
            self::MAX_RETRY_STEP
        );
    }

    /**
     * @param array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * } $state
     */
    private function writeRuntimeState(array $state): void
    {
        $this->WriteAttributeString('RuntimeState', ForecastStateReducer::toJson($state));
    }

    /**
     * @param array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * } $state
     */
    private function publishOperationalState(array $state, int $now): void
    {
        $state = ForecastStateReducer::evaluateFreshness(
            $state,
            $now,
            $this->ReadPropertyInteger('StaleAfterMinutes') * 60
        );
        $this->SetValue('DataState', self::DATA_STATE_VALUES[$state['state']] ?? 5);
        $this->SetValue('LastFetchAttempt', $state['lastAttempt'] ?? 0);
        $this->SetValue('LastSuccess', $state['lastSuccess'] ?? 0);
        $this->SetValue('ForecastValidFrom', $state['validFrom'] ?? 0);
        $this->SetValue('ForecastValidTo', $state['validTo'] ?? 0);
        $age = $state['lastSuccess'] === null
            ? 0
            : max(0, intdiv($now - $state['lastSuccess'], 60));
        $this->SetValue('ForecastAgeMinutes', $age);
    }

    private function scheduleNormalPolling(): void
    {
        $this->SetTimerInterval(
            'UpdateData',
            $this->ReadPropertyInteger('PollingIntervalMinutes') * 60000
        );
    }

    private function scheduleAfterFailure(int $retryCount): void
    {
        $minutes = self::RETRY_INTERVAL_MINUTES[$retryCount]
            ?? $this->ReadPropertyInteger('PollingIntervalMinutes');
        $this->SetTimerInterval('UpdateData', $minutes * 60000);
    }

    /** @return array<string, mixed> */
    private function requestConfiguration(): array
    {
        return $this->locationConfiguration() + [
            'withSoil' => $this->ReadPropertyBoolean('WithSoil'),
        ];
    }

    private function configurationHash(): string
    {
        return SAEF_CreateConfigurationHash($this->requestConfiguration());
    }

    private function forecastSlice(string $section, int $from, int $to, string $fieldsJson): string
    {
        if ($from < 0 || $to <= $from || $to - $from > self::MAXIMUM_CACHE_QUERY_SECONDS) {
            return $this->result(false, 'range_invalid');
        }
        $cache = $this->readCache();
        if ($cache === null) {
            return $this->result(false, 'cache_empty');
        }
        try {
            $fields = json_decode($fieldsJson, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->result(false, 'fields_invalid');
        }
        if (!is_array($fields) || $fields === [] || count($fields) > 32) {
            return $this->result(false, 'fields_invalid');
        }

        $available = $cache[$section];
        $result = [];
        foreach ($fields as $field) {
            if (!is_string($field) || !isset($available[$field])) {
                return $this->result(false, 'field_unknown');
            }
            $points = [];
            foreach ($available[$field] as $point) {
                if (!is_array($point)) {
                    return $this->result(false, 'cache_invalid');
                }
                $validFrom = $point['validFrom'] ?? null;
                $validTo = $point['validTo'] ?? null;
                if (!is_int($validFrom) || !is_int($validTo)) {
                    return $this->result(false, 'cache_invalid');
                }
                if ($validTo >= $from && $validFrom < $to) {
                    $points[] = $point;
                }
            }
            $result[$field] = $points;
        }

        return $this->encodeResult(['success' => true, 'data' => $result]);
    }

    /**
     * @return null|array{
     *     current: array<string, array<string, int|float|string>>,
     *     hourly: array<string, list<array<string, int|float|string>>>,
     *     daily: array<string, list<array<string, int|float|string>>>
     * }
     */
    private function readCache(): ?array
    {
        try {
            $cache = json_decode(
                $this->ReadAttributeString('ForecastCache'),
                true,
                128,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return null;
        }
        if (
            !is_array($cache)
            || ($cache['configurationHash'] ?? null) !== $this->configurationHash()
            || !is_array($cache['current'] ?? null)
            || !is_array($cache['hourly'] ?? null)
            || !is_array($cache['daily'] ?? null)
        ) {
            return null;
        }

        /** @var array{
         *     current: array<string, array<string, int|float|string>>,
         *     hourly: array<string, list<array<string, int|float|string>>>,
         *     daily: array<string, list<array<string, int|float|string>>>
         * } $cache */
        return $cache;
    }

    /** @param array<string, mixed> $cache */
    private function encodeCache(array $cache): string
    {
        $cache['configurationHash'] = $this->configurationHash();

        return $this->encodeResult($cache);
    }

    private function result(bool $success, string $code): string
    {
        return $this->encodeResult(['success' => $success, 'code' => $code]);
    }

    /** @param array<string, mixed> $value */
    private function encodeResult(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    protected function currentTimestamp(): int
    {
        return time();
    }

    /** @return array<string, float|int|string|null> */
    private function locationConfiguration(): array
    {
        return [
            'latitude' => $this->ReadPropertyFloat('Latitude'),
            'longitude' => $this->ReadPropertyFloat('Longitude'),
            'elevation' => $this->ReadPropertyBoolean('UseElevation')
                ? $this->ReadPropertyFloat('Elevation')
                : null,
            'timezone' => trim($this->ReadPropertyString('Timezone')),
            'forecastDays' => $this->ReadPropertyInteger('ForecastDays'),
        ];
    }

    private function validateRuntimePolicy(): void
    {
        $pollingInterval = $this->ReadPropertyInteger('PollingIntervalMinutes');
        $timeout = $this->ReadPropertyInteger('HttpTimeoutSeconds');
        $staleAfter = $this->ReadPropertyInteger('StaleAfterMinutes');
        if (
            $pollingInterval < 30
            || $timeout < 1
            || $timeout > 60
            || $staleAfter < $pollingInterval
        ) {
            throw new InvalidArgumentException('Weather runtime policy is invalid.');
        }
    }

    private function registerOperationalVariables(): void
    {
        $this->RegisterVariableInteger('DataState', 'Data State', 'OPENMETEO.DataState', 10);
        $this->RegisterVariableInteger('LastFetchAttempt', 'Last Fetch Attempt', '~UnixTimestamp', 20);
        $this->RegisterVariableInteger('LastSuccess', 'Last Success', '~UnixTimestamp', 30);
        $this->RegisterVariableInteger('ForecastValidFrom', 'Forecast Valid From', '~UnixTimestamp', 40);
        $this->RegisterVariableInteger('ForecastValidTo', 'Forecast Valid To', '~UnixTimestamp', 50);
        $this->RegisterVariableInteger('ForecastAgeMinutes', 'Forecast Age Minutes', '', 60);
    }

    private function registerWeatherVariables(): void
    {
        $this->RegisterVariableFloat('Temperature', 'Temperature', '~Temperature', 100);
        $this->RegisterVariableFloat('RelativeHumidity', 'Relative Humidity', '~Humidity.F', 110);
        $this->RegisterVariableFloat('DewPoint', 'Dew Point', '~Temperature', 120);
        $this->RegisterVariableFloat('ApparentTemperature', 'Apparent Temperature', '~Temperature', 130);
        $this->RegisterVariableFloat('PressureMsl', 'Pressure MSL', 'OPENMETEO.Pressure', 140);
        $this->RegisterVariableFloat('SurfacePressure', 'Surface Pressure', 'OPENMETEO.Pressure', 150);
        $this->RegisterVariableFloat('WindSpeed', 'Wind Speed', 'OPENMETEO.WindSpeed', 160);
        $this->RegisterVariableInteger('WindDirection', 'Wind Direction', 'OPENMETEO.Direction', 170);
        $this->RegisterVariableFloat('WindGust', 'Wind Gust', 'OPENMETEO.WindSpeed', 180);
        $this->RegisterVariableFloat('Precipitation', 'Precipitation', 'OPENMETEO.WaterDepth', 190);
        $this->RegisterVariableFloat('Rain', 'Rain', 'OPENMETEO.WaterDepth', 200);
        $this->RegisterVariableFloat('Showers', 'Showers', 'OPENMETEO.WaterDepth', 210);
        $this->RegisterVariableFloat('Snowfall', 'Snowfall', 'OPENMETEO.Snowfall', 220);
        $this->RegisterVariableInteger('WeatherCode', 'Weather Code', 'OPENMETEO.WeatherCode', 230);
        $this->RegisterVariableInteger('CloudCover', 'Cloud Cover', '~Intensity.100', 240);
        $this->RegisterVariableBoolean('IsDay', 'Is Day', '', 250);
        $this->RegisterVariableInteger('CurrentValidAt', 'Current Valid At', '~UnixTimestamp', 260);

        $this->RegisterVariableInteger('TodayWeatherCode', 'Today Weather Code', 'OPENMETEO.WeatherCode', 300);
        $this->RegisterVariableFloat('TodayTemperatureMin', 'Today Temperature Min', '~Temperature', 310);
        $this->RegisterVariableFloat('TodayTemperatureMax', 'Today Temperature Max', '~Temperature', 320);
        $this->RegisterVariableInteger(
            'TodayPrecipitationProbabilityMax',
            'Today Precipitation Probability Max',
            '~Intensity.100',
            330
        );
        $this->RegisterVariableFloat(
            'TodayPrecipitationSum',
            'Today Precipitation Sum',
            'OPENMETEO.WaterDepth',
            340
        );
        $this->RegisterVariableInteger(
            'TodaySunshineDuration',
            'Today Sunshine Duration',
            'OPENMETEO.Duration',
            350
        );
        $this->RegisterVariableFloat('TodayEt0', 'Today ET0', 'OPENMETEO.WaterDepth', 360);
        $this->RegisterVariableInteger('TodaySunrise', 'Today Sunrise', '~UnixTimestamp', 370);
        $this->RegisterVariableInteger('TodaySunset', 'Today Sunset', '~UnixTimestamp', 380);
    }

    private function registerSoilVariables(): void
    {
        $this->RegisterVariableFloat('SoilTemperature0cm', 'Soil Temperature 0 cm', '~Temperature', 400);
        $this->RegisterVariableFloat('SoilTemperature6cm', 'Soil Temperature 6 cm', '~Temperature', 410);
        $this->RegisterVariableFloat('SoilTemperature18cm', 'Soil Temperature 18 cm', '~Temperature', 420);
        $this->RegisterVariableFloat('SoilTemperature54cm', 'Soil Temperature 54 cm', '~Temperature', 430);
        $this->RegisterVariableFloat(
            'SoilMoisture0To1cm',
            'Soil Moisture 0 to 1 cm',
            'OPENMETEO.SoilMoisture',
            440
        );
        $this->RegisterVariableFloat(
            'SoilMoisture1To3cm',
            'Soil Moisture 1 to 3 cm',
            'OPENMETEO.SoilMoisture',
            450
        );
        $this->RegisterVariableFloat(
            'SoilMoisture3To9cm',
            'Soil Moisture 3 to 9 cm',
            'OPENMETEO.SoilMoisture',
            460
        );
        $this->RegisterVariableFloat(
            'SoilMoisture9To27cm',
            'Soil Moisture 9 to 27 cm',
            'OPENMETEO.SoilMoisture',
            470
        );
        $this->RegisterVariableFloat(
            'SoilMoisture27To81cm',
            'Soil Moisture 27 to 81 cm',
            'OPENMETEO.SoilMoisture',
            480
        );
    }
}
