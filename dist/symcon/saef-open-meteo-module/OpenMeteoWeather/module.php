<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/OpenMeteo/FieldCatalog.php';
require_once __DIR__ . '/../libs/OpenMeteo/RequestBuilder.php';
if (!function_exists('SAEF_EnsureProfile')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/object/EnsureProfile.php';
}
require_once __DIR__ . '/../libs/OpenMeteo/Profiles.php';

use SAEF\CaseStudy\OpenMeteo\Profiles;
use SAEF\CaseStudy\OpenMeteo\RequestBuilder;

class OpenMeteoWeather extends IPSModule
{
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIGURATION_ERROR = 200;

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
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        Profiles::ensure();
        $this->registerOperationalVariables();
        $this->registerWeatherVariables();
        $this->registerSoilVariables();

        if (!$this->ReadPropertyBoolean('LocationConfigured')) {
            $this->SetStatus(self::STATUS_INACTIVE);

            return;
        }

        try {
            RequestBuilder::normalizeLocationConfiguration(
                $this->locationConfiguration(),
                10
            );
            $this->validateRuntimePolicy();
            $this->SetStatus(self::STATUS_INACTIVE);
        } catch (Throwable) {
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);
        }
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
