<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use DateTimeZone;
use InvalidArgumentException;

final class RequestBuilder
{
    public const DWD_ICON_ENDPOINT = 'https://api.open-meteo.com/v1/dwd-icon';

    /**
     * @param array<string, mixed> $configuration
     */
    public static function weather(array $configuration): string
    {
        $normalized = self::normalizeLocationConfiguration($configuration, 10);
        $withSoil = self::optionalBoolean($configuration, 'withSoil', false);

        $query = self::baseQuery($normalized);
        $query['current'] = implode(',', FieldCatalog::weatherCurrentFields());
        $query['hourly'] = implode(',', FieldCatalog::weatherHourlyFields($withSoil));
        $query['daily'] = implode(',', FieldCatalog::weatherDailyFields());

        return self::DWD_ICON_ENDPOINT . '?' . http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    /**
     * @param array<string, mixed> $locationConfiguration
     */
    public static function solar(
        array $locationConfiguration,
        float $tiltDegrees,
        float $azimuthDegrees
    ): string {
        $normalized = self::normalizeLocationConfiguration($locationConfiguration, 7);
        self::assertFiniteRange($tiltDegrees, 0.0, 90.0, 'tilt');
        self::assertFiniteRange($azimuthDegrees, -180.0, 180.0, 'azimuth');

        $query = self::baseQuery($normalized);
        $query['tilt'] = self::formatFloat($tiltDegrees);
        $query['azimuth'] = self::formatFloat($azimuthDegrees);
        $query['hourly'] = 'temperature_2m,global_tilted_irradiance';

        return self::DWD_ICON_ENDPOINT . '?' . http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array{latitude: float, longitude: float, timezone: string, forecastDays: int, elevation: ?float}
     */
    public static function normalizeLocationConfiguration(
        array $configuration,
        int $maximumForecastDays
    ): array {
        $latitude = self::requireFloat($configuration, 'latitude');
        $longitude = self::requireFloat($configuration, 'longitude');
        self::assertFiniteRange($latitude, -90.0, 90.0, 'latitude');
        self::assertFiniteRange($longitude, -180.0, 180.0, 'longitude');

        $timezone = $configuration['timezone'] ?? null;
        if (!is_string($timezone) || $timezone === '') {
            throw new InvalidArgumentException('Location timezone is required.');
        }
        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new InvalidArgumentException('Location timezone is invalid.');
        }

        $forecastDays = $configuration['forecastDays'] ?? null;
        if (!is_int($forecastDays) || $forecastDays < 1 || $forecastDays > $maximumForecastDays) {
            throw new InvalidArgumentException('Location forecastDays is outside the supported range.');
        }

        $elevation = null;
        if (array_key_exists('elevation', $configuration) && $configuration['elevation'] !== null) {
            $elevation = self::requireFloat($configuration, 'elevation');
            self::assertFiniteRange($elevation, -500.0, 9000.0, 'elevation');
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'forecastDays' => $forecastDays,
            'elevation' => $elevation,
        ];
    }

    /**
     * @param array{latitude: float, longitude: float, timezone: string, forecastDays: int, elevation: ?float} $configuration
     *
     * @return array<string, int|string>
     */
    private static function baseQuery(array $configuration): array
    {
        $query = [
            'latitude' => self::formatFloat($configuration['latitude']),
            'longitude' => self::formatFloat($configuration['longitude']),
            'timezone' => $configuration['timezone'],
            'timeformat' => 'unixtime',
            'temperature_unit' => 'celsius',
            'wind_speed_unit' => 'kmh',
            'precipitation_unit' => 'mm',
            'cell_selection' => 'land',
            'forecast_days' => $configuration['forecastDays'],
        ];
        if ($configuration['elevation'] !== null) {
            $query['elevation'] = self::formatFloat($configuration['elevation']);
        }

        return $query;
    }

    /** @param array<string, mixed> $configuration */
    private static function requireFloat(array $configuration, string $key): float
    {
        $value = $configuration[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException(
                sprintf('Location %s must be numeric.', $key)
            );
        }

        return (float) $value;
    }

    /** @param array<string, mixed> $configuration */
    private static function optionalBoolean(
        array $configuration,
        string $key,
        bool $default
    ): bool {
        if (!array_key_exists($key, $configuration)) {
            return $default;
        }
        if (!is_bool($configuration[$key])) {
            throw new InvalidArgumentException(
                sprintf('Location %s must be boolean.', $key)
            );
        }

        return $configuration[$key];
    }

    private static function assertFiniteRange(
        float $value,
        float $minimum,
        float $maximum,
        string $field
    ): void {
        if (!is_finite($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException(
                sprintf('Location %s is outside the supported range.', $field)
            );
        }
    }

    private static function formatFloat(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
