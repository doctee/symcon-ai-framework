<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\DwdNowcast;

use InvalidArgumentException;

final class ForecastProjector
{
    /**
     * @param array{
     *     productTime: int,
     *     validFrom: int,
     *     validTo: int,
     *     resolutionMinutes: int,
     *     points: list<array{
     *         validAt: int,
     *         leadMinutes: int,
     *         intensityMmPerHour: float,
     *         accumulationMm: float
     *     }>
     * } $forecast
     *
     * @return array<string, mixed>
     */
    public static function project(
        array $forecast,
        int $windowMinutes,
        float $rainThresholdMmPerHour,
        int $fetchedAt
    ): array {
        self::validatePolicy($windowMinutes, $rainThresholdMmPerHour, $fetchedAt);

        $windowPoints = array_values(array_filter(
            $forecast['points'],
            static fn (array $point): bool => $point['leadMinutes'] <= $windowMinutes
        ));
        if ($windowPoints === []) {
            throw new InvalidArgumentException('Nowcast evaluation window has no points.');
        }

        $sum = 0.0;
        $maximum = 0.0;
        $firstWetLead = null;
        $lastWetLead = null;
        foreach ($windowPoints as $point) {
            $sum += $point['accumulationMm'];
            $maximum = max($maximum, $point['intensityMmPerHour']);
            if ($point['intensityMmPerHour'] >= $rainThresholdMmPerHour) {
                $firstWetLead ??= $point['leadMinutes'];
                $lastWetLead = $point['leadMinutes'];
            }
        }
        $resolution = $forecast['resolutionMinutes'];
        $rainStarts = $firstWetLead === null ? -1 : max(0, $firstWetLead - $resolution);
        $rainEnds = $lastWetLead ?? -1;

        return [
            'schemaVersion' => 1,
            'provider' => 'dwd-rv-wms',
            'layer' => 'dwd:Niederschlagsradar',
            'fetchedAt' => $fetchedAt,
            'productTime' => $forecast['productTime'],
            'validFrom' => $forecast['validFrom'],
            'validTo' => $forecast['validTo'],
            'nativeResolutionMinutes' => $resolution,
            'maximumHorizonMinutes' => RequestBuilder::MAXIMUM_HORIZON_MINUTES,
            'evaluationWindowMinutes' => $windowMinutes,
            'rainThresholdMmPerHour' => $rainThresholdMmPerHour,
            'points' => $forecast['points'],
            'windowPoints' => $windowPoints,
            'summary' => [
                'rainExpected' => $firstWetLead !== null,
                'rainStartsInMinutes' => $rainStarts,
                'rainEndsInMinutes' => $rainEnds,
                'precipitationSumMm' => round($sum, 3),
                'maximumIntensityMmPerHour' => round($maximum, 3),
                'nextIntervalIntensityMmPerHour' => $windowPoints[0]['intensityMmPerHour'],
                'forecastPointCount' => count($windowPoints),
            ],
        ];
    }

    private static function validatePolicy(
        int $windowMinutes,
        float $rainThresholdMmPerHour,
        int $fetchedAt
    ): void {
        if (
            $windowMinutes < RequestBuilder::NATIVE_RESOLUTION_MINUTES
            || $windowMinutes > RequestBuilder::MAXIMUM_HORIZON_MINUTES
            || $windowMinutes % RequestBuilder::NATIVE_RESOLUTION_MINUTES !== 0
        ) {
            throw new InvalidArgumentException('Nowcast evaluation window is invalid.');
        }
        if (
            !is_finite($rainThresholdMmPerHour)
            || $rainThresholdMmPerHour <= 0.0
            || $rainThresholdMmPerHour > 1000.0
            || $fetchedAt <= 0
        ) {
            throw new InvalidArgumentException('Nowcast projection policy is invalid.');
        }
    }
}
