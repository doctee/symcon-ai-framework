<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use UnexpectedValueException;

final class WeatherForecastProjector
{
    private const SCHEMA_VERSION = 1;

    /**
     * @return array{
     *     schemaVersion: int,
     *     validFrom: int,
     *     validTo: int,
     *     current: array<string, array<string, int|float|string>>,
     *     hourly: array<string, list<array<string, int|float|string>>>,
     *     daily: array<string, list<array<string, int|float|string>>>,
     *     publicValues: array<string, bool|int|float>
     * }
     */
    public static function project(ParsedForecast $forecast, bool $withSoil, int $now): array
    {
        if ($now < 0) {
            throw new UnexpectedValueException('Forecast projection time is invalid.');
        }

        $current = self::exportSection(
            $forecast,
            'current',
            FieldCatalog::weatherCurrentFields()
        );
        $hourlyFields = FieldCatalog::weatherHourlyFields($withSoil);
        $hourly = self::exportSection($forecast, 'hourly', $hourlyFields);
        $daily = self::exportSection(
            $forecast,
            'daily',
            FieldCatalog::weatherDailyFields()
        );

        $range = $hourly['temperature_2m'] ?? [];
        if ($range === []) {
            throw new UnexpectedValueException('Forecast hourly range is empty.');
        }
        $first = $range[0];
        $last = $range[count($range) - 1];

        $publicValues = self::currentPublicValues($forecast);
        $publicValues += self::todayPublicValues($forecast, $now);
        if ($withSoil) {
            $publicValues += self::soilPublicValues(
                $forecast,
                (int) $publicValues['CurrentValidAt']
            );
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'validFrom' => (int) $first['validFrom'],
            'validTo' => (int) $last['validTo'],
            'current' => self::firstPoints($current),
            'hourly' => $hourly,
            'daily' => $daily,
            'publicValues' => $publicValues,
        ];
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, list<array<string, int|float|string>>>
     */
    private static function exportSection(
        ParsedForecast $forecast,
        string $section,
        array $fields
    ): array {
        $result = [];
        foreach ($fields as $field) {
            $series = match ($section) {
                'current' => $forecast->current($field),
                'hourly' => $forecast->hourly($field),
                'daily' => $forecast->daily($field),
                default => throw new UnexpectedValueException('Forecast section is invalid.'),
            };
            $result[$field] = array_map(
                static fn (ForecastPoint $point): array => [
                    'sourceTimestamp' => $point->sourceTimestamp(),
                    'validFrom' => $point->validFrom(),
                    'validTo' => $point->validTo(),
                    'value' => $point->value(),
                    'unit' => $point->unit(),
                    'semantics' => $point->semantics(),
                ],
                $series->points()
            );
        }

        return $result;
    }

    /**
     * @param array<string, list<array<string, int|float|string>>> $section
     *
     * @return array<string, array<string, int|float|string>>
     */
    private static function firstPoints(array $section): array
    {
        $result = [];
        foreach ($section as $field => $points) {
            if ($points === []) {
                throw new UnexpectedValueException('Forecast current point is missing.');
            }
            $result[$field] = $points[0];
        }

        return $result;
    }

    /** @return array<string, bool|int|float> */
    private static function currentPublicValues(ParsedForecast $forecast): array
    {
        $mapping = [
            'Temperature' => 'temperature_2m',
            'RelativeHumidity' => 'relative_humidity_2m',
            'DewPoint' => 'dew_point_2m',
            'ApparentTemperature' => 'apparent_temperature',
            'PressureMsl' => 'pressure_msl',
            'SurfacePressure' => 'surface_pressure',
            'WindSpeed' => 'wind_speed_10m',
            'WindDirection' => 'wind_direction_10m',
            'WindGust' => 'wind_gusts_10m',
            'Precipitation' => 'precipitation',
            'Rain' => 'rain',
            'Showers' => 'showers',
            'Snowfall' => 'snowfall',
            'WeatherCode' => 'weather_code',
            'CloudCover' => 'cloud_cover',
            'IsDay' => 'is_day',
        ];
        $values = [];
        foreach ($mapping as $ident => $field) {
            $value = $forecast->current($field)->points()[0]->value();
            $values[$ident] = $ident === 'IsDay' ? $value === 1 : $value;
        }
        $values['CurrentValidAt'] = $forecast->current('temperature_2m')->points()[0]
            ->sourceTimestamp();

        return $values;
    }

    /** @return array<string, int|float> */
    private static function todayPublicValues(ParsedForecast $forecast, int $now): array
    {
        $mapping = [
            'TodayWeatherCode' => 'weather_code',
            'TodayTemperatureMin' => 'temperature_2m_min',
            'TodayTemperatureMax' => 'temperature_2m_max',
            'TodayPrecipitationProbabilityMax' => 'precipitation_probability_max',
            'TodayPrecipitationSum' => 'precipitation_sum',
            'TodaySunshineDuration' => 'sunshine_duration',
            'TodayEt0' => 'et0_fao_evapotranspiration',
            'TodaySunrise' => 'sunrise',
            'TodaySunset' => 'sunset',
        ];
        $values = [];
        foreach ($mapping as $ident => $field) {
            $values[$ident] = self::localDayPoint($forecast->daily($field), $now)->value();
        }

        return $values;
    }

    /** @return array<string, int|float> */
    private static function soilPublicValues(ParsedForecast $forecast, int $currentTimestamp): array
    {
        $mapping = [
            'SoilTemperature0cm' => 'soil_temperature_0cm',
            'SoilTemperature6cm' => 'soil_temperature_6cm',
            'SoilTemperature18cm' => 'soil_temperature_18cm',
            'SoilTemperature54cm' => 'soil_temperature_54cm',
            'SoilMoisture0To1cm' => 'soil_moisture_0_to_1cm',
            'SoilMoisture1To3cm' => 'soil_moisture_1_to_3cm',
            'SoilMoisture3To9cm' => 'soil_moisture_3_to_9cm',
            'SoilMoisture9To27cm' => 'soil_moisture_9_to_27cm',
            'SoilMoisture27To81cm' => 'soil_moisture_27_to_81cm',
        ];
        $values = [];
        foreach ($mapping as $ident => $field) {
            $values[$ident] = self::nearestPoint(
                $forecast->hourly($field),
                $currentTimestamp
            )->value();
        }

        return $values;
    }

    private static function localDayPoint(ForecastSeries $series, int $now): ForecastPoint
    {
        foreach ($series->points() as $point) {
            if ($point->validFrom() <= $now && $now < $point->validTo()) {
                return $point;
            }
        }

        throw new UnexpectedValueException('Forecast does not contain the current local day.');
    }

    private static function nearestPoint(ForecastSeries $series, int $timestamp): ForecastPoint
    {
        $nearest = null;
        $distance = PHP_INT_MAX;
        foreach ($series->points() as $point) {
            $candidateDistance = abs($point->sourceTimestamp() - $timestamp);
            if ($candidateDistance < $distance) {
                $nearest = $point;
                $distance = $candidateDistance;
            }
        }
        if ($nearest === null) {
            throw new UnexpectedValueException('Forecast series is empty.');
        }

        return $nearest;
    }
}
