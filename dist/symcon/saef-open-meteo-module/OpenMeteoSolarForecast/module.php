<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/OpenMeteo/PvConfiguration.php';
if (!function_exists('SAEF_CreateConfigurationHash')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/diagnostics/ConfigurationHash.php';
}
if (!function_exists('SAEF_EnsureProfile')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/object/EnsureProfile.php';
}
require_once __DIR__ . '/../libs/OpenMeteo/Profiles.php';

use SAEF\CaseStudy\OpenMeteo\Profiles;
use SAEF\CaseStudy\OpenMeteo\PvConfiguration;

class OpenMeteoSolarForecast extends IPSModule
{
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIGURATION_ERROR = 200;
    private const WEATHER_MODULE_ID = '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}';

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('WeatherInstanceId', 0);
        $this->RegisterPropertyInteger('ForecastDays', 4);
        $this->RegisterPropertyInteger('PollingIntervalMinutes', 60);
        $this->RegisterPropertyString('ArraysJson', '[]');
        $this->RegisterPropertyString('InvertersJson', '[]');
        $this->RegisterPropertyBoolean('EnableShadingProfile', false);
        $this->RegisterPropertyBoolean('EnableCalibration', false);
        $this->RegisterPropertyInteger('HttpTimeoutSeconds', 10);
        $this->RegisterPropertyInteger('StaleAfterMinutes', 180);
        $this->RegisterAttributeInteger('RegisteredWeatherReferenceId', 0);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        Profiles::ensure();
        $this->registerVariables();

        $weatherInstanceId = $this->ReadPropertyInteger('WeatherInstanceId');
        if ($weatherInstanceId <= 0) {
            $this->reconcileWeatherReference(0);
            $this->SetStatus(self::STATUS_INACTIVE);

            return;
        }

        try {
            $arrays = $this->decodeConfigurationList(
                $this->ReadPropertyString('ArraysJson')
            );
            $inverters = $this->decodeConfigurationList(
                $this->ReadPropertyString('InvertersJson')
            );
            new PvConfiguration($arrays, $inverters);
            $this->validateRuntimePolicy();
            $this->validateWeatherInstance($weatherInstanceId);
            $this->reconcileWeatherReference($weatherInstanceId);
            $this->SetValue('ConfigurationHash', SAEF_CreateConfigurationHash([
                'weatherInstanceId' => $weatherInstanceId,
                'forecastDays' => $this->ReadPropertyInteger('ForecastDays'),
                'pollingIntervalMinutes' => $this->ReadPropertyInteger('PollingIntervalMinutes'),
                'arrays' => $arrays,
                'inverters' => $inverters,
                'httpTimeoutSeconds' => $this->ReadPropertyInteger('HttpTimeoutSeconds'),
                'staleAfterMinutes' => $this->ReadPropertyInteger('StaleAfterMinutes'),
            ]));
            $this->SetStatus(self::STATUS_INACTIVE);
        } catch (Throwable) {
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);
        }
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
                throw new InvalidArgumentException(
                    'Solar configuration entries must be objects.'
                );
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
        if (
            $forecastDays < 1
            || $forecastDays > 7
            || $pollingInterval < 30
            || $timeout < 1
            || $timeout > 60
            || $staleAfter < $pollingInterval
            || $this->ReadPropertyBoolean('EnableShadingProfile')
            || $this->ReadPropertyBoolean('EnableCalibration')
        ) {
            throw new InvalidArgumentException('Solar runtime policy is invalid.');
        }
    }

    private function validateWeatherInstance(int $instanceId): void
    {
        if (!IPS_InstanceExists($instanceId)) {
            throw new InvalidArgumentException('Weather instance does not exist.');
        }
        $instance = IPS_GetInstance($instanceId);
        $moduleId = $instance['ModuleInfo']['ModuleID'] ?? null;
        if (!is_string($moduleId) || $moduleId !== self::WEATHER_MODULE_ID) {
            throw new InvalidArgumentException('Weather instance has an incompatible module type.');
        }
    }

    private function reconcileWeatherReference(int $desiredInstanceId): void
    {
        $registeredInstanceId = $this->ReadAttributeInteger('RegisteredWeatherReferenceId');
        if ($registeredInstanceId === $desiredInstanceId) {
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
        $this->RegisterVariableFloat(
            'CurrentPowerForecast',
            'Current Power Forecast',
            'OPENMETEO.Power',
            100
        );
        $this->RegisterVariableFloat(
            'TodayEnergyForecast',
            'Today Energy Forecast',
            'OPENMETEO.Energy',
            110
        );
        $this->RegisterVariableFloat(
            'TomorrowEnergyForecast',
            'Tomorrow Energy Forecast',
            'OPENMETEO.Energy',
            120
        );
        $this->RegisterVariableString('ConfigurationHash', 'Configuration Hash', '', 130);
    }
}
