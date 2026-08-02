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
require_once __DIR__ . '/../libs/OpenMeteo/PvConfiguration.php';
require_once __DIR__ . '/../libs/OpenMeteo/SolarForecastCalculator.php';
require_once __DIR__ . '/../libs/OpenMeteo/SolarForecastProjector.php';
if (!function_exists('SAEF_CreateConfigurationHash')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/diagnostics/ConfigurationHash.php';
}
if (!function_exists('SAEF_EnsureProfile')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/object/EnsureProfile.php';
}
require_once __DIR__ . '/../libs/OpenMeteo/Profiles.php';

use SAEF\CaseStudy\OpenMeteo\FieldCatalog;
use SAEF\CaseStudy\OpenMeteo\ForecastStateReducer;
use SAEF\CaseStudy\OpenMeteo\Profiles;
use SAEF\CaseStudy\OpenMeteo\PvConfiguration;
use SAEF\CaseStudy\OpenMeteo\RequestBuilder;
use SAEF\CaseStudy\OpenMeteo\ResponseParser;
use SAEF\CaseStudy\OpenMeteo\SolarForecastProjector;

class OpenMeteoSolarForecast extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIGURATION_ERROR = 200;
    private const MAX_RETRY_STEP = 4;
    private const SEMAPHORE_TIMEOUT_MILLISECONDS = 1000;
    private const MAXIMUM_CACHE_QUERY_SECONDS = 864000;
    private const MAXIMUM_LOCATION_DESCRIPTOR_BYTES = 4096;
    private const WEATHER_MODULE_ID = '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}';

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

        $this->RegisterPropertyInteger('WeatherInstanceId', 0);
        $this->RegisterPropertyInteger('ForecastDays', 4);
        $this->RegisterPropertyString('ForecastOutputMode', 'direct_ac');
        $this->RegisterPropertyBoolean('EnableAutomaticUpdates', false);
        $this->RegisterPropertyInteger('PollingIntervalMinutes', 60);
        $this->RegisterPropertyString('ArraysJson', '[]');
        $this->RegisterPropertyString('InvertersJson', '[]');
        $this->RegisterPropertyBoolean('EnableShadingProfile', false);
        $this->RegisterPropertyBoolean('EnableCalibration', false);
        $this->RegisterPropertyInteger('HttpTimeoutSeconds', 10);
        $this->RegisterPropertyInteger('StaleAfterMinutes', 180);

        $this->RegisterAttributeString('RuntimeState', '');
        $this->RegisterAttributeString('ForecastCache', '');
        $this->RegisterAttributeInteger('RegisteredWeatherReferenceId', 0);

        $this->RegisterTimer(
            'UpdateData',
            0,
            'OMSOLAR_UpdateData($_IPS["TARGET"]);'
        );
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        Profiles::ensure();
        $this->registerVariables();

        if ($this->ReadPropertyInteger('WeatherInstanceId') <= 0) {
            $this->reconcileWeatherReference(0);
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetValue('DataState', 0);
            $this->SetStatus(self::STATUS_INACTIVE);

            return;
        }

        try {
            $context = $this->runtimeContext();
            $this->reconcileWeatherReference($context['weatherInstanceId']);
            $state = $this->runtimeState($context['configurationHash']);
            $this->writeRuntimeState($state);
            $this->SetValue('ConfigurationHash', $context['configurationHash']);
            if (!$state['hasData']) {
                $this->SetValue('CurrentPowerForecast', 0.0);
                $this->SetValue('TodayEnergyForecast', 0.0);
                $this->SetValue('TomorrowEnergyForecast', 0.0);
            }
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
        if ($this->ReadPropertyInteger('WeatherInstanceId') <= 0) {
            return $this->result(false, 'configuration_missing');
        }

        try {
            $context = $this->runtimeContext();
        } catch (Throwable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetValue('DataState', 5);
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);

            return $this->result(false, 'configuration_invalid');
        }

        $lockName = 'OpenMeteoSolarForecast.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lockName, self::SEMAPHORE_TIMEOUT_MILLISECONDS)) {
            return $this->result(false, 'busy');
        }

        try {
            return $this->executeUpdate($context);
        } finally {
            if (!IPS_SemaphoreLeave($lockName)) {
                IPS_LogMessage('OpenMeteoSolarForecast', 'Instance lock could not be released.');
            }
        }
    }

    public function GetPowerForecastJson(
        int $from,
        int $to,
        string $breakdown = 'system'
    ): string {
        return $this->forecastSlice('power', $from, $to, $breakdown);
    }

    public function GetDailyEnergyForecastJson(
        int $from,
        int $to,
        string $breakdown = 'system'
    ): string {
        return $this->forecastSlice('dailyEnergy', $from, $to, $breakdown);
    }

    /**
     * @param array{
     *     weatherInstanceId: int,
     *     location: array{latitude: float, longitude: float, timezone: string, forecastDays: int, elevation: ?float},
     *     pv: PvConfiguration,
     *     configurationHash: string
     * } $context
     */
    private function executeUpdate(array $context): string
    {
        $attemptedAt = $this->currentTimestamp();
        $state = $this->runtimeState($context['configurationHash']);
        $this->SetValue('DataState', self::DATA_STATE_VALUES['fetching']);
        $this->SetValue('LastFetchAttempt', $attemptedAt);

        try {
            $forecasts = [];
            foreach ($context['pv']->uniqueOrientations() as $orientationKey => $orientation) {
                $url = RequestBuilder::solar(
                    $context['location'],
                    $orientation['tiltDegrees'],
                    $orientation['azimuthDegrees']
                );
                try {
                    $body = $this->fetchUrl(
                        $url,
                        $this->ReadPropertyInteger('HttpTimeoutSeconds') * 1000
                    );
                } catch (Throwable) {
                    return $this->recordFailure(
                        $state,
                        $attemptedAt,
                        'transport_error',
                        true
                    );
                }
                if ($body === false) {
                    return $this->recordFailure(
                        $state,
                        $attemptedAt,
                        'transport_error',
                        true
                    );
                }
                $forecasts[$orientationKey] = ResponseParser::parse(
                    $body,
                    [],
                    ['temperature_2m', 'global_tilted_irradiance'],
                    []
                );
            }

            $cache = SolarForecastProjector::project(
                $context['pv'],
                $forecasts,
                $attemptedAt,
                $this->ReadPropertyString('ForecastOutputMode')
            );
            $state = ForecastStateReducer::success(
                $state,
                $attemptedAt,
                $cache['validFrom'],
                $cache['validTo']
            );

            $this->WriteAttributeString(
                'ForecastCache',
                $this->encodeCache($cache, $context['configurationHash'])
            );
            foreach ($cache['publicValues'] as $ident => $value) {
                $this->SetValue($ident, $value);
            }
            $this->writeRuntimeState($state);
            $this->publishOperationalState($state, $attemptedAt);
            $this->scheduleNormalPolling();
            $this->SetStatus(self::STATUS_ACTIVE);

            return $this->result(true, 'ok');
        } catch (Throwable) {
            return $this->recordFailure($state, $attemptedAt, 'response_invalid', true);
        }
    }

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
     *     state: string, configurationHash: string, hasData: bool,
     *     lastAttempt: ?int, lastSuccess: ?int, validFrom: ?int, validTo: ?int,
     *     retryCount: int, maxRetries: int, errorCode: ?string
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
        IPS_LogMessage('OpenMeteoSolarForecast', 'Update failed (' . $errorCode . ').');

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
    private function runtimeState(string $configurationHash): array
    {
        return ForecastStateReducer::fromJson(
            $this->ReadAttributeString('RuntimeState'),
            $configurationHash,
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
            $this->ReadPropertyBoolean('EnableAutomaticUpdates')
                ? $this->ReadPropertyInteger('PollingIntervalMinutes') * 60000
                : 0
        );
    }

    private function scheduleAfterFailure(int $retryCount): void
    {
        if (!$this->ReadPropertyBoolean('EnableAutomaticUpdates')) {
            $this->SetTimerInterval('UpdateData', 0);

            return;
        }
        $minutes = self::RETRY_INTERVAL_MINUTES[$retryCount]
            ?? $this->ReadPropertyInteger('PollingIntervalMinutes');
        $this->SetTimerInterval('UpdateData', $minutes * 60000);
    }

    private function forecastSlice(
        string $section,
        int $from,
        int $to,
        string $breakdown
    ): string {
        if ($from < 0 || $to <= $from || $to - $from > self::MAXIMUM_CACHE_QUERY_SECONDS) {
            return $this->result(false, 'range_invalid');
        }
        if ($breakdown !== 'system') {
            return $this->result(false, 'breakdown_unsupported');
        }
        $cache = $this->readCache();
        if ($cache === null) {
            return $this->result(false, 'cache_empty');
        }

        $points = [];
        foreach ($cache[$section]['system'] as $point) {
            if (!is_array($point)) {
                return $this->result(false, 'cache_invalid');
            }
            $validFrom = $point['validFrom'] ?? null;
            $validTo = $point['validTo'] ?? null;
            if (!is_int($validFrom) || !is_int($validTo)) {
                return $this->result(false, 'cache_invalid');
            }
            if ($validTo > $from && $validFrom < $to) {
                $points[] = $point;
            }
        }

        return $this->encodeResult([
            'success' => true,
            'breakdown' => 'system',
            'data' => ['system' => $points],
        ]);
    }

    /** @return null|array<string, mixed> */
    private function readCache(): ?array
    {
        try {
            $configurationHash = $this->runtimeContext()['configurationHash'];
            $cache = json_decode(
                $this->ReadAttributeString('ForecastCache'),
                true,
                128,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return null;
        }
        if (
            !is_array($cache)
            || ($cache['configurationHash'] ?? null) !== $configurationHash
            || !is_array($cache['power']['system'] ?? null)
            || !is_array($cache['dailyEnergy']['system'] ?? null)
        ) {
            return null;
        }

        return $cache;
    }

    /** @param array<string, mixed> $cache */
    private function encodeCache(array $cache, string $configurationHash): string
    {
        $cache['configurationHash'] = $configurationHash;

        return $this->encodeResult($cache);
    }

    /**
     * @return array{
     *     weatherInstanceId: int,
     *     location: array{latitude: float, longitude: float, timezone: string, forecastDays: int, elevation: ?float},
     *     pv: PvConfiguration,
     *     configurationHash: string
     * }
     */
    private function runtimeContext(): array
    {
        $weatherInstanceId = $this->ReadPropertyInteger('WeatherInstanceId');
        $this->validateWeatherInstance($weatherInstanceId);
        $this->validateRuntimePolicy();
        $arrays = $this->decodeConfigurationList($this->ReadPropertyString('ArraysJson'));
        $inverters = $this->decodeConfigurationList(
            $this->ReadPropertyString('InvertersJson')
        );
        $pv = new PvConfiguration($arrays, $inverters);
        $location = $this->weatherLocationConfiguration($weatherInstanceId);

        return [
            'weatherInstanceId' => $weatherInstanceId,
            'location' => $location,
            'pv' => $pv,
            'configurationHash' => SAEF_CreateConfigurationHash([
                'location' => $location,
                'forecastOutputMode' => $this->ReadPropertyString('ForecastOutputMode'),
                'arrays' => $pv->arrays(),
                'inverters' => $pv->inverters(),
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function decodeConfigurationList(string $json): array
    {
        $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new InvalidArgumentException('Solar configuration must be a JSON list.');
        }

        $result = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException('Solar configuration entries must be objects.');
            }
            $result[] = $entry;
        }

        return $result;
    }

    private function validateRuntimePolicy(): void
    {
        $forecastDays = $this->ReadPropertyInteger('ForecastDays');
        $pollingInterval = $this->ReadPropertyInteger('PollingIntervalMinutes');
        $timeout = $this->ReadPropertyInteger('HttpTimeoutSeconds');
        $staleAfter = $this->ReadPropertyInteger('StaleAfterMinutes');
        $outputMode = $this->ReadPropertyString('ForecastOutputMode');
        if (
            $forecastDays < 1
            || $forecastDays > 7
            || $pollingInterval < 30
            || $timeout < 1
            || $timeout > 60
            || $staleAfter < $pollingInterval
            || !in_array($outputMode, ['direct_ac', 'pv_harvest'], true)
            || $this->ReadPropertyBoolean('EnableShadingProfile')
            || $this->ReadPropertyBoolean('EnableCalibration')
        ) {
            throw new InvalidArgumentException('Solar runtime policy is invalid.');
        }
    }

    private function validateWeatherInstance(int $instanceId): void
    {
        if ($instanceId <= 0 || !IPS_InstanceExists($instanceId)) {
            throw new InvalidArgumentException('Weather instance does not exist.');
        }
        $instance = IPS_GetInstance($instanceId);
        $moduleId = $instance['ModuleInfo']['ModuleID'] ?? null;
        if (!is_string($moduleId) || $moduleId !== self::WEATHER_MODULE_ID) {
            throw new InvalidArgumentException('Weather instance has an incompatible module type.');
        }
    }

    /** @return array{latitude: float, longitude: float, timezone: string, forecastDays: int, elevation: ?float} */
    private function weatherLocationConfiguration(int $instanceId): array
    {
        $descriptorJson = OMWEATHER_GetLocationDescriptor($instanceId);
        if (strlen($descriptorJson) > self::MAXIMUM_LOCATION_DESCRIPTOR_BYTES) {
            throw new InvalidArgumentException('Weather location descriptor is too large.');
        }
        $descriptor = json_decode($descriptorJson, true, 32, JSON_THROW_ON_ERROR);
        if (
            !is_array($descriptor)
            || ($descriptor['success'] ?? null) !== true
            || !is_array($descriptor['location'] ?? null)
        ) {
            throw new InvalidArgumentException('Weather location descriptor is invalid.');
        }
        $location = $descriptor['location'];
        $location['forecastDays'] = $this->ReadPropertyInteger('ForecastDays');

        return RequestBuilder::normalizeLocationConfiguration($location, 7);
    }

    private function reconcileWeatherReference(int $desiredInstanceId): void
    {
        $registeredInstanceId = $this->ReadAttributeInteger('RegisteredWeatherReferenceId');
        if ($registeredInstanceId === $desiredInstanceId) {
            if ($desiredInstanceId > 0) {
                $this->RegisterReference($desiredInstanceId);
            }

            return;
        }
        if ($registeredInstanceId > 0) {
            $this->UnregisterReference($registeredInstanceId);
        }
        if ($desiredInstanceId > 0) {
            $this->RegisterReference($desiredInstanceId);
        }
        $this->WriteAttributeInteger('RegisteredWeatherReferenceId', $desiredInstanceId);
    }

    private function registerVariables(): void
    {
        $this->RegisterVariableInteger('DataState', 'Data State', 'OPENMETEO.DataState', 10);
        $this->RegisterVariableInteger('LastFetchAttempt', 'Last Fetch Attempt', '~UnixTimestamp', 20);
        $this->RegisterVariableInteger('LastSuccess', 'Last Success', '~UnixTimestamp', 30);
        $this->RegisterVariableInteger('ForecastValidFrom', 'Forecast Valid From', '~UnixTimestamp', 40);
        $this->RegisterVariableInteger('ForecastValidTo', 'Forecast Valid To', '~UnixTimestamp', 50);
        $this->RegisterVariableInteger('ForecastAgeMinutes', 'Forecast Age Minutes', '', 60);
        $this->RegisterVariableFloat('CurrentPowerForecast', 'Current Power Forecast', 'OPENMETEO.Power', 100);
        $this->RegisterVariableFloat('TodayEnergyForecast', 'Today Energy Forecast', 'OPENMETEO.Energy', 110);
        $this->RegisterVariableFloat('TomorrowEnergyForecast', 'Tomorrow Energy Forecast', 'OPENMETEO.Energy', 120);
        $this->RegisterVariableString('ConfigurationHash', 'Configuration Hash', '', 130);
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
}
