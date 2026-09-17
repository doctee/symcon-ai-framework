<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

final class SolarForecastProjector
{
    private const SCHEMA_VERSION = 2;

    /**
     * @param array<string, ParsedForecast> $forecastsByOrientation
     *
     * @return array{
     *     schemaVersion: int,
     *     outputMode: string,
     *     validFrom: int,
     *     validTo: int,
     *     power: array{system: list<array<string, int|float|string>>, baseline: list<array<string, int|float|string>>},
     *     dailyEnergy: array{system: list<array<string, int|float|string>>, baseline: list<array<string, int|float|string>>},
     *     publicValues: array{CurrentPowerForecast: float, TodayEnergyForecast: float, TomorrowEnergyForecast: float}
     * }
     */
    public static function project(
        PvConfiguration $configuration,
        array $forecastsByOrientation,
        int $now,
        string $outputMode = 'direct_ac',
        ?LocalHorizonModel $localHorizonModel = null
    ): array {
        if (
            $now < 0
            || $forecastsByOrientation === []
            || !in_array($outputMode, ['direct_ac', 'pv_harvest'], true)
        ) {
            throw new UnexpectedValueException('Solar forecast projection input is invalid.');
        }

        $temperature = null;
        $timezone = null;
        $baselineGtiByOrientation = [];
        $gtiByOrientation = [];
        foreach ($configuration->uniqueOrientations() as $orientationKey => $orientation) {
            $forecast = $forecastsByOrientation[$orientationKey] ?? null;
            if (!$forecast instanceof ParsedForecast) {
                throw new UnexpectedValueException('A required solar response is missing.');
            }
            if ($timezone !== null && $forecast->timezone() !== $timezone) {
                throw new UnexpectedValueException('Solar response timezones differ.');
            }
            $timezone = $forecast->timezone();
            $temperature ??= $forecast->hourly('temperature_2m');
            $gti = $forecast->hourly(
                'global_tilted_irradiance'
            );
            $baselineGtiByOrientation[$orientationKey] = $gti;
            if ($localHorizonModel !== null) {
                $gti = $localHorizonModel->adjustTiltedIrradiance(
                    $gti,
                    $forecast->hourly('direct_normal_irradiance'),
                    $orientation['tiltDegrees'],
                    $orientation['azimuthDegrees']
                );
            }
            $gtiByOrientation[$orientationKey] = $gti;
        }
        if (count($forecastsByOrientation) !== count($gtiByOrientation)) {
            throw new UnexpectedValueException('Solar response set differs from configuration.');
        }

        $power = self::calculatePower(
            $configuration,
            $gtiByOrientation,
            $temperature,
            $outputMode
        );
        $baselinePower = self::calculatePower(
            $configuration,
            $baselineGtiByOrientation,
            $temperature,
            $outputMode
        );
        $dailyEnergy = SolarForecastCalculator::dailyEnergy($power, $timezone);
        $baselineDailyEnergy = SolarForecastCalculator::dailyEnergy(
            $baselinePower,
            $timezone
        );
        if (
            $power->count() === 0
            || $dailyEnergy->count() === 0
            || $baselinePower->count() !== $power->count()
            || $baselineDailyEnergy->count() !== $dailyEnergy->count()
        ) {
            throw new UnexpectedValueException('Calculated solar forecast is empty.');
        }

        $powerPoints = self::export($power);
        $baselinePowerPoints = self::export($baselinePower);
        $dailyPoints = self::export($dailyEnergy);
        $baselineDailyPoints = self::export($baselineDailyEnergy);
        $first = $powerPoints[0];
        $last = $powerPoints[count($powerPoints) - 1];

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'outputMode' => $outputMode,
            'validFrom' => (int) $first['validFrom'],
            'validTo' => (int) $last['validTo'],
            'power' => [
                'system' => $powerPoints,
                'baseline' => $baselinePowerPoints,
            ],
            'dailyEnergy' => [
                'system' => $dailyPoints,
                'baseline' => $baselineDailyPoints,
            ],
            'publicValues' => [
                'CurrentPowerForecast' => self::containingValue($power, $now),
                'TodayEnergyForecast' => self::localDayValue($dailyEnergy, $timezone, $now, 0),
                'TomorrowEnergyForecast' => self::localDayValue(
                    $dailyEnergy,
                    $timezone,
                    $now,
                    1
                ),
            ],
        ];
    }

    /** @param array<string, ForecastSeries> $gtiByOrientation */
    private static function calculatePower(
        PvConfiguration $configuration,
        array $gtiByOrientation,
        ForecastSeries $temperature,
        string $outputMode
    ): ForecastSeries {
        return $outputMode === 'pv_harvest'
            ? SolarForecastCalculator::calculatePvHarvest(
                $configuration,
                $gtiByOrientation,
                $temperature
            )
            : SolarForecastCalculator::calculate(
                $configuration,
                $gtiByOrientation,
                $temperature
            );
    }

    /** @return list<array<string, int|float|string>> */
    private static function export(ForecastSeries $series): array
    {
        return array_map(
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

    private static function containingValue(ForecastSeries $series, int $timestamp): float
    {
        foreach ($series->points() as $point) {
            if ($point->validFrom() <= $timestamp && $timestamp < $point->validTo()) {
                return (float) $point->value();
            }
        }

        return 0.0;
    }

    private static function localDayValue(
        ForecastSeries $series,
        string $timezone,
        int $now,
        int $dayOffset
    ): float {
        $date = (new DateTimeImmutable('@' . $now))
            ->setTimezone(new DateTimeZone($timezone))
            ->modify('+' . $dayOffset . ' day')
            ->format('Y-m-d');
        $bounds = IntervalAligner::localDayBounds($date, $timezone);
        foreach ($series->points() as $point) {
            if ($point->validFrom() === $bounds['from'] && $point->validTo() === $bounds['to']) {
                return (float) $point->value();
            }
        }

        return 0.0;
    }
}
