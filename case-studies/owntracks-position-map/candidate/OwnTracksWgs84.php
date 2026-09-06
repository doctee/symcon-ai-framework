<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

final class OwnTracksWgs84
{
    private const EARTH_RADIUS_METERS = 6371008.8;

    /**
     * @return array{latitude: float, longitude: float}
     */
    public static function coordinate(mixed $latitude, mixed $longitude): array
    {
        if (
            !self::finiteNumber($latitude)
            || !self::finiteNumber($longitude)
        ) {
            throw new InvalidArgumentException('WGS84 coordinate is invalid.');
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        if (
            $latitude < -90.0
            || $latitude > 90.0
            || $longitude < -180.0
            || $longitude > 180.0
        ) {
            throw new InvalidArgumentException('WGS84 coordinate is out of range.');
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * @param array{latitude: float, longitude: float} $from
     * @param array{latitude: float, longitude: float} $to
     */
    public static function distanceMeters(array $from, array $to): float
    {
        $latitudeOne = deg2rad($from['latitude']);
        $latitudeTwo = deg2rad($to['latitude']);
        $latitudeDelta = $latitudeTwo - $latitudeOne;
        $longitudeDelta = deg2rad(
            self::shortestLongitudeDelta(
                $from['longitude'],
                $to['longitude']
            )
        );

        $sinLatitude = sin($latitudeDelta / 2.0);
        $sinLongitude = sin($longitudeDelta / 2.0);
        $haversine = ($sinLatitude * $sinLatitude)
            + cos($latitudeOne)
            * cos($latitudeTwo)
            * ($sinLongitude * $sinLongitude);
        $haversine = min(1.0, max(0.0, $haversine));

        return self::EARTH_RADIUS_METERS
            * 2.0
            * atan2(sqrt($haversine), sqrt(1.0 - $haversine));
    }

    /**
     * @param list<array{latitude: float, longitude: float}> $coordinates
     * @return array{
     *     south: float,
     *     west: float,
     *     north: float,
     *     east: float,
     *     crossesAntimeridian: bool,
     *     observationCount: int
     * }|null
     */
    public static function bounds(array $coordinates): ?array
    {
        if ($coordinates === []) {
            return null;
        }

        $latitudes = [];
        $longitudes = [];
        foreach ($coordinates as $coordinate) {
            $normalized = self::coordinate(
                $coordinate['latitude'],
                $coordinate['longitude']
            );
            $latitudes[] = $normalized['latitude'];
            $longitudes[] = self::longitude360($normalized['longitude']);
        }
        sort($longitudes, SORT_NUMERIC);

        if (count($longitudes) === 1) {
            $west = self::longitude180($longitudes[0]);
            $east = $west;
        } else {
            $largestGap = -1.0;
            $gapAfter = 0;
            $count = count($longitudes);
            for ($index = 0; $index < $count; $index++) {
                $next = $index === $count - 1
                    ? $longitudes[0] + 360.0
                    : $longitudes[$index + 1];
                $gap = $next - $longitudes[$index];
                if ($gap > $largestGap) {
                    $largestGap = $gap;
                    $gapAfter = $index;
                }
            }

            $westIndex = ($gapAfter + 1) % $count;
            $west = self::longitude180($longitudes[$westIndex]);
            $east = self::longitude180($longitudes[$gapAfter]);
        }

        return [
            'south' => min($latitudes),
            'west' => $west,
            'north' => max($latitudes),
            'east' => $east,
            'crossesAntimeridian' => $west > $east,
            'observationCount' => count($coordinates),
        ];
    }

    private static function shortestLongitudeDelta(
        float $from,
        float $to
    ): float {
        $delta = fmod(($to - $from) + 540.0, 360.0) - 180.0;

        return $delta === -180.0 ? 180.0 : $delta;
    }

    private static function longitude360(float $longitude): float
    {
        $normalized = fmod($longitude + 360.0, 360.0);

        return $normalized < 0.0 ? $normalized + 360.0 : $normalized;
    }

    private static function longitude180(float $longitude): float
    {
        $normalized = fmod($longitude + 180.0, 360.0);
        if ($normalized < 0.0) {
            $normalized += 360.0;
        }

        return $normalized - 180.0;
    }

    private static function finiteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value))
            && is_finite((float) $value);
    }
}
