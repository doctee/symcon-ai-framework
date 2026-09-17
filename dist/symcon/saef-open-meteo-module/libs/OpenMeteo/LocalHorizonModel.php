<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class LocalHorizonModel
{
    private const SAMPLE_SECONDS = 300;

    public function __construct(
        private readonly LocalHorizonProfile $profile,
        private readonly float $latitude,
        private readonly float $longitude
    ) {
        if (
            !is_finite($latitude)
            || !is_finite($longitude)
            || $latitude < -90.0
            || $latitude > 90.0
            || $longitude < -180.0
            || $longitude > 180.0
        ) {
            throw new InvalidArgumentException('Local horizon location is invalid.');
        }
    }

    public function adjustTiltedIrradiance(
        ForecastSeries $globalTiltedIrradiance,
        ForecastSeries $directNormalIrradiance,
        float $tiltDegrees,
        float $azimuthDegrees
    ): ForecastSeries {
        $this->assertSeriesContract(
            $globalTiltedIrradiance,
            'global_tilted_irradiance'
        );
        $this->assertSeriesContract(
            $directNormalIrradiance,
            'direct_normal_irradiance'
        );
        if (
            !is_finite($tiltDegrees)
            || !is_finite($azimuthDegrees)
            || $tiltDegrees < 0.0
            || $tiltDegrees > 90.0
            || $azimuthDegrees < -180.0
            || $azimuthDegrees > 180.0
        ) {
            throw new InvalidArgumentException('Local horizon panel orientation is invalid.');
        }

        $points = [];
        foreach ($globalTiltedIrradiance->points() as $gtiPoint) {
            $dniPoint = $directNormalIrradiance->pointAtSourceTimestamp(
                $gtiPoint->sourceTimestamp()
            );
            if (!$dniPoint instanceof ForecastPoint) {
                throw new InvalidArgumentException('Local horizon direct irradiance timestamp is missing.');
            }
            if (
                $dniPoint->validFrom() !== $gtiPoint->validFrom()
                || $dniPoint->validTo() !== $gtiPoint->validTo()
            ) {
                throw new InvalidArgumentException('Local horizon irradiance intervals do not align.');
            }

            $gti = (float) $gtiPoint->value();
            $dni = (float) $dniPoint->value();
            if (!is_finite($gti) || !is_finite($dni) || $gti < 0.0 || $dni < 0.0) {
                throw new InvalidArgumentException('Local horizon irradiance value is invalid.');
            }

            $blockedDirect = $this->blockedDirectPlaneIrradiance(
                $dni,
                $gtiPoint->validFrom(),
                $gtiPoint->validTo(),
                $tiltDegrees,
                $azimuthDegrees
            );
            $adjusted = max(0.0, $gti - min($gti, $blockedDirect));
            $points[] = new ForecastPoint(
                $gtiPoint->field(),
                $gtiPoint->unit(),
                $gtiPoint->semantics(),
                $gtiPoint->sourceTimestamp(),
                $gtiPoint->validFrom(),
                $gtiPoint->validTo(),
                $adjusted
            );
        }

        return new ForecastSeries(
            $globalTiltedIrradiance->field(),
            $globalTiltedIrradiance->unit(),
            $points
        );
    }

    private function blockedDirectPlaneIrradiance(
        float $directNormalIrradiance,
        int $validFrom,
        int $validTo,
        float $tiltDegrees,
        float $panelAzimuthDegrees
    ): float {
        $durationSeconds = $validTo - $validFrom;
        if ($durationSeconds <= 0 || $durationSeconds > 86400) {
            throw new InvalidArgumentException('Local horizon interval duration is invalid.');
        }

        $sampleCount = max(1, (int) ceil($durationSeconds / self::SAMPLE_SECONDS));
        $segmentSeconds = $durationSeconds / $sampleCount;
        $blockedIncidenceSum = 0.0;
        for ($sample = 0; $sample < $sampleCount; $sample++) {
            $timestamp = $validFrom + ($sample + 0.5) * $segmentSeconds;
            $position = $this->solarPosition($timestamp);
            if ($position['elevationDegrees'] <= 0.0) {
                continue;
            }

            $incidence = self::incidenceCosine(
                $position['elevationDegrees'],
                $position['azimuthDegrees'],
                $tiltDegrees,
                $panelAzimuthDegrees
            );
            if (
                $incidence > 0.0
                && $position['elevationDegrees']
                    <= $this->profile->elevationAt($position['azimuthDegrees'])
            ) {
                $blockedIncidenceSum += $incidence;
            }
        }

        return $directNormalIrradiance * $blockedIncidenceSum / $sampleCount;
    }

    /** @return array{elevationDegrees: float, azimuthDegrees: float} */
    private function solarPosition(float $timestamp): array
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
            $utcSeconds / 60.0 + $equationOfTime + 4.0 * $this->longitude,
            1440.0
        );
        if ($solarMinutes < 0.0) {
            $solarMinutes += 1440.0;
        }
        $hourAngleDegrees = $solarMinutes / 4.0 - 180.0;

        $latitudeRadians = deg2rad($this->latitude);
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

    private static function incidenceCosine(
        float $solarElevationDegrees,
        float $solarAzimuthDegrees,
        float $tiltDegrees,
        float $panelAzimuthDegrees
    ): float {
        $solarZenith = deg2rad(90.0 - $solarElevationDegrees);
        $tilt = deg2rad($tiltDegrees);
        $panelAzimuthNorthClockwise = self::normalizeDegrees(
            180.0 + $panelAzimuthDegrees
        );
        $azimuthDifference = deg2rad(
            $solarAzimuthDegrees - $panelAzimuthNorthClockwise
        );

        return max(
            0.0,
            cos($solarZenith) * cos($tilt)
                + sin($solarZenith) * sin($tilt) * cos($azimuthDifference)
        );
    }

    private function assertSeriesContract(ForecastSeries $series, string $field): void
    {
        if ($series->field() !== $field || $series->unit() !== 'W/m²') {
            throw new InvalidArgumentException('Local horizon irradiance series contract is invalid.');
        }
        foreach ($series->points() as $point) {
            if ($point->semantics() !== FieldCatalog::SEMANTICS_PRECEDING_INTERVAL) {
                throw new InvalidArgumentException('Local horizon irradiance semantics are invalid.');
            }
        }
    }

    private static function normalizeDegrees(float $degrees): float
    {
        $normalized = fmod($degrees, 360.0);

        return $normalized < 0.0 ? $normalized + 360.0 : $normalized;
    }
}
