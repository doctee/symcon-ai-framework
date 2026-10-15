<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;

/** Approximate horizontal irradiance from hourly DNI/DHI; no weather fetches. */
final class WarmingWeather
{
    public static function solar(array $data, int $from, int $to, float $latitude, float $longitude): array
    {
        $fields = [];
        foreach (['direct_normal_irradiance', 'diffuse_radiation'] as $field) {
            foreach ($data[$field] ?? [] as $p) {
                $t = $p['validFrom'] ?? null;
                if (
                    !is_int($t) || ($p['validTo'] ?? null) !== $t + 3600
                    || ($p['semantics'] ?? '') !== 'preceding_interval' || ($p['unit'] ?? '') !== 'W/m²'
                    || isset($fields[$field][$t])
                ) {
                    throw new RuntimeException('Invalid solar forecast interval.');
                }
                $v = Forecast::number($p['value'] ?? null, $field);
                if ($v < 0 || $v > 1600) {
                    throw new RuntimeException('Solar forecast outside physical guard.');
                }
                $fields[$field][$t] = $v;
            }
        }
        $result = [];
        for ($t = $from; $t < $to; $t += 3600) {
            if (!isset($fields['direct_normal_irradiance'][$t], $fields['diffuse_radiation'][$t])) {
                throw new RuntimeException('Solar forecast coverage incomplete.');
            }
            $cosine = 0.0;
            for ($sample = 150; $sample < 3600; $sample += 300) {
                $position = self::solarPosition($t + $sample, $latitude, $longitude);
                $cosine += max(0.0, sin(deg2rad($position['elevationDegrees']))) / 12;
            }
            $result[$t] = $fields['diffuse_radiation'][$t] + $fields['direct_normal_irradiance'][$t] * $cosine;
        }
        return $result;
    }

    // Same NOAA-based equations as Open-Meteo's private LocalHorizonModel method.
    // Kept local to avoid changing that independently deployed module's API/owner.
    /** @return array{elevationDegrees: float, azimuthDegrees: float} */
    private static function solarPosition(float $timestamp, float $latitude, float $longitude): array
    {
        $julianDay = $timestamp / 86400.0 + 2440587.5;
        $century = ($julianDay - 2451545.0) / 36525.0;
        $meanLongitude = self::normalizeDegrees(
            280.46646 + $century * (36000.76983 + 0.0003032 * $century)
        );
        $meanAnomaly = 357.52911
            + $century * (35999.05029 - 0.0001537 * $century);
        $eccentricity = 0.016708634
            - $century * (0.000042037 + 0.0000001267 * $century);
        $equationOfCenter = sin(deg2rad($meanAnomaly))
                * (1.914602 - $century * (0.004817 + 0.000014 * $century))
            + sin(deg2rad(2.0 * $meanAnomaly))
                * (0.019993 - 0.000101 * $century)
            + sin(deg2rad(3.0 * $meanAnomaly)) * 0.000289;
        $trueLongitude = $meanLongitude + $equationOfCenter;
        $omega = 125.04 - 1934.136 * $century;
        $apparentLongitude = $trueLongitude
            - 0.00569
            - 0.00478 * sin(deg2rad($omega));
        $meanObliquity = 23.0
            + (26.0
                + (21.448
                    - $century * (46.815 + $century * (0.00059 - 0.001813 * $century)))
                / 60.0)
            / 60.0;
        $obliquity = $meanObliquity + 0.00256 * cos(deg2rad($omega));
        $declination = asin(
            sin(deg2rad($obliquity)) * sin(deg2rad($apparentLongitude))
        );

        $y = tan(deg2rad($obliquity) / 2.0);
        $y *= $y;
        $meanLongitudeRadians = deg2rad($meanLongitude);
        $meanAnomalyRadians = deg2rad($meanAnomaly);
        $equationOfTime = 4.0 * rad2deg(
            $y * sin(2.0 * $meanLongitudeRadians)
            - 2.0 * $eccentricity * sin($meanAnomalyRadians)
            + 4.0 * $eccentricity * $y
                * sin($meanAnomalyRadians) * cos(2.0 * $meanLongitudeRadians)
            - 0.5 * $y * $y * sin(4.0 * $meanLongitudeRadians)
            - 1.25 * $eccentricity * $eccentricity * sin(2.0 * $meanAnomalyRadians)
        );

        $utcSeconds = fmod($timestamp, 86400.0);
        if ($utcSeconds < 0.0) {
            $utcSeconds += 86400.0;
        }
        $solarMinutes = fmod(
            $utcSeconds / 60.0 + $equationOfTime + 4.0 * $longitude,
            1440.0
        );
        if ($solarMinutes < 0.0) {
            $solarMinutes += 1440.0;
        }
        $hourAngleDegrees = $solarMinutes / 4.0 - 180.0;

        $latitudeRadians = deg2rad($latitude);
        $hourAngleRadians = deg2rad($hourAngleDegrees);
        $cosZenith = sin($latitudeRadians) * sin($declination)
            + cos($latitudeRadians) * cos($declination) * cos($hourAngleRadians);
        $cosZenith = max(-1.0, min(1.0, $cosZenith));
        $zenithRadians = acos($cosZenith);
        $azimuthDegrees = rad2deg(atan2(
            sin($hourAngleRadians),
            cos($hourAngleRadians) * sin($latitudeRadians)
                - tan($declination) * cos($latitudeRadians)
        )) + 180.0;

        return [
            'elevationDegrees' => 90.0 - rad2deg($zenithRadians),
            'azimuthDegrees' => self::normalizeDegrees($azimuthDegrees),
        ];
    }

    private static function normalizeDegrees(float $value): float
    {
        return fmod(fmod($value, 360.0) + 360.0, 360.0);
    }
}
