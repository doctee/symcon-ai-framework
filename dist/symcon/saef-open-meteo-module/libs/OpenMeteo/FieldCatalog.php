<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class FieldCatalog
{
    public const SEMANTICS_INSTANT = 'instant';
    public const SEMANTICS_PRECEDING_INTERVAL = 'preceding_interval';
    public const SEMANTICS_LOCAL_DAY = 'local_day';

    /** @var list<string> */
    private const WEATHER_CURRENT_FIELDS = [
        'temperature_2m',
        'relative_humidity_2m',
        'dew_point_2m',
        'apparent_temperature',
        'precipitation',
        'rain',
        'showers',
        'snowfall',
        'weather_code',
        'cloud_cover',
        'pressure_msl',
        'surface_pressure',
        'wind_speed_10m',
        'wind_direction_10m',
        'wind_gusts_10m',
        'is_day',
    ];

    /** @var list<string> */
    private const WEATHER_HOURLY_FIELDS = [
        'temperature_2m',
        'relative_humidity_2m',
        'dew_point_2m',
        'apparent_temperature',
        'precipitation_probability',
        'precipitation',
        'rain',
        'showers',
        'snowfall',
        'weather_code',
        'pressure_msl',
        'surface_pressure',
        'cloud_cover',
        'cloud_cover_low',
        'cloud_cover_mid',
        'cloud_cover_high',
        'visibility',
        'wind_speed_10m',
        'wind_direction_10m',
        'wind_gusts_10m',
        'sunshine_duration',
        'et0_fao_evapotranspiration',
        'vapour_pressure_deficit',
    ];

    /** @var list<string> */
    private const WEATHER_DAILY_FIELDS = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'apparent_temperature_max',
        'apparent_temperature_min',
        'precipitation_sum',
        'rain_sum',
        'showers_sum',
        'snowfall_sum',
        'precipitation_probability_max',
        'precipitation_hours',
        'sunrise',
        'sunset',
        'sunshine_duration',
        'wind_speed_10m_max',
        'wind_gusts_10m_max',
        'wind_direction_10m_dominant',
        'shortwave_radiation_sum',
        'et0_fao_evapotranspiration',
    ];

    /** @var list<string> */
    private const SOIL_HOURLY_FIELDS = [
        'soil_temperature_0cm',
        'soil_temperature_6cm',
        'soil_temperature_18cm',
        'soil_temperature_54cm',
        'soil_moisture_0_to_1cm',
        'soil_moisture_1_to_3cm',
        'soil_moisture_3_to_9cm',
        'soil_moisture_9_to_27cm',
        'soil_moisture_27_to_81cm',
    ];

    /** @var array<string, list<string>> */
    private const EXPECTED_UNITS = [
        'temperature_2m' => ['°C'],
        'temperature_2m_max' => ['°C'],
        'temperature_2m_min' => ['°C'],
        'apparent_temperature' => ['°C'],
        'apparent_temperature_max' => ['°C'],
        'apparent_temperature_min' => ['°C'],
        'dew_point_2m' => ['°C'],
        'relative_humidity_2m' => ['%'],
        'precipitation_probability' => ['%'],
        'precipitation_probability_max' => ['%'],
        'cloud_cover' => ['%'],
        'cloud_cover_low' => ['%'],
        'cloud_cover_mid' => ['%'],
        'cloud_cover_high' => ['%'],
        'precipitation' => ['mm'],
        'precipitation_sum' => ['mm'],
        'rain' => ['mm'],
        'rain_sum' => ['mm'],
        'showers' => ['mm'],
        'showers_sum' => ['mm'],
        'et0_fao_evapotranspiration' => ['mm'],
        'snowfall' => ['cm'],
        'snowfall_sum' => ['cm'],
        'pressure_msl' => ['hPa'],
        'surface_pressure' => ['hPa'],
        'wind_speed_10m' => ['km/h'],
        'wind_speed_10m_max' => ['km/h'],
        'wind_gusts_10m' => ['km/h'],
        'wind_gusts_10m_max' => ['km/h'],
        'wind_direction_10m' => ['°'],
        'wind_direction_10m_dominant' => ['°'],
        'weather_code' => ['wmo code'],
        'is_day' => [''],
        'visibility' => ['m'],
        'sunshine_duration' => ['s'],
        'precipitation_hours' => ['h'],
        'vapour_pressure_deficit' => ['kPa'],
        'shortwave_radiation_sum' => ['MJ/m²'],
        'sunrise' => ['unixtime', 'iso8601'],
        'sunset' => ['unixtime', 'iso8601'],
        'global_tilted_irradiance' => ['W/m²'],
        'ac_power' => ['kW'],
        'daily_energy' => ['kWh'],
        'soil_temperature_0cm' => ['°C'],
        'soil_temperature_6cm' => ['°C'],
        'soil_temperature_18cm' => ['°C'],
        'soil_temperature_54cm' => ['°C'],
        'soil_moisture_0_to_1cm' => ['m³/m³'],
        'soil_moisture_1_to_3cm' => ['m³/m³'],
        'soil_moisture_3_to_9cm' => ['m³/m³'],
        'soil_moisture_9_to_27cm' => ['m³/m³'],
        'soil_moisture_27_to_81cm' => ['m³/m³'],
    ];

    /** @return list<string> */
    public static function weatherCurrentFields(): array
    {
        return self::WEATHER_CURRENT_FIELDS;
    }

    /** @return list<string> */
    public static function weatherHourlyFields(bool $withSoil): array
    {
        return $withSoil
            ? array_merge(self::WEATHER_HOURLY_FIELDS, self::SOIL_HOURLY_FIELDS)
            : self::WEATHER_HOURLY_FIELDS;
    }

    /** @return list<string> */
    public static function weatherDailyFields(): array
    {
        return self::WEATHER_DAILY_FIELDS;
    }

    /** @return list<string> */
    public static function soilHourlyFields(): array
    {
        return self::SOIL_HOURLY_FIELDS;
    }

    public static function permitsNullGap(string $section, string $field): bool
    {
        return in_array($section, ['hourly', 'daily'], true);
    }

    public static function semantics(string $section, string $field): string
    {
        if ($section === 'daily') {
            return self::SEMANTICS_LOCAL_DAY;
        }

        if (
            $section === 'current' && in_array($field, [
            'precipitation',
            'rain',
            'showers',
            'snowfall',
            'wind_gusts_10m',
            ], true)
        ) {
            return self::SEMANTICS_PRECEDING_INTERVAL;
        }

        if (
            $section === 'hourly' && in_array($field, [
            'precipitation',
            'rain',
            'showers',
            'snowfall',
            'wind_gusts_10m',
            'sunshine_duration',
            'et0_fao_evapotranspiration',
            'global_tilted_irradiance',
            ], true)
        ) {
            return self::SEMANTICS_PRECEDING_INTERVAL;
        }

        return self::SEMANTICS_INSTANT;
    }

    public static function assertUnit(string $field, string $unit): void
    {
        $expected = self::EXPECTED_UNITS[$field] ?? null;
        if ($expected === null) {
            throw new InvalidArgumentException(
                sprintf('No unit contract exists for field "%s".', $field)
            );
        }

        if (!in_array($unit, $expected, true)) {
            throw new InvalidArgumentException(
                sprintf('Field "%s" has an incompatible unit.', $field)
            );
        }
    }
}
