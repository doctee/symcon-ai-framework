<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use DateTimeZone;
use InvalidArgumentException;

final class LocationDefinition
{
    /**
     * @param array<string, mixed> $configuration
     *
     * @return array{key: string, latitude: float, longitude: float, timezone: string, elevation: ?float}
     */
    public static function normalize(array $configuration): array
    {
        $key = $configuration['key'] ?? null;
        if (
            !is_string($key)
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1
        ) {
            throw new InvalidArgumentException('Location key is invalid.');
        }

        $latitude = self::requireFloat($configuration, 'latitude');
        $longitude = self::requireFloat($configuration, 'longitude');
        self::assertFiniteRange($latitude, -90.0, 90.0, 'latitude');
        self::assertFiniteRange($longitude, -180.0, 180.0, 'longitude');

        $timezone = $configuration['timezone'] ?? null;
        if (!is_string($timezone) || trim($timezone) === '') {
            throw new InvalidArgumentException('Location timezone is required.');
        }
        $timezone = trim($timezone);
        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new InvalidArgumentException('Location timezone is invalid.');
        }

        $elevation = null;
        if (array_key_exists('elevation', $configuration) && $configuration['elevation'] !== null) {
            $elevation = self::requireFloat($configuration, 'elevation');
            self::assertFiniteRange($elevation, -500.0, 9000.0, 'elevation');
        }

        return [
            'key' => $key,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => $elevation,
        ];
    }

    /** @param array<string, mixed> $configuration */
    private static function requireFloat(array $configuration, string $key): float
    {
        $value = $configuration[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException(sprintf('Location %s must be numeric.', $key));
        }

        return (float) $value;
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
}
