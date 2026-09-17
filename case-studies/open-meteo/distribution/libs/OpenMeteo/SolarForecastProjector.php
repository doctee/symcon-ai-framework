<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

final class SolarForecastProjector
{
    private const SCHEMA_VERSION = 3;

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
     *     irradiance: array{system: list<array<string, int|float|string>>, baseline: list<array<string, int|float|string>>},
     *     solarInput: array{directNormalIrradiance: list<array<string, int|float|string>>, airTemperature: list<array<string, int|float|string>>},
     *     publicValues: array{CurrentPowerForecast: float, CurrentBaselinePowerForecast: float, CurrentGtiSystem: float, CurrentGtiBaseline: float, CurrentHorizonLossPercent: float, TodayEnergyForecast: float, TomorrowEnergyForecast: float}
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
        $directNormalIrradiance = null;
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
            $directNormalIrradiance ??= $forecast->hourly('direct_normal_irradiance');
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
        $systemIrradiance = self::weightedIrradiance(
            $configuration,
            $gtiByOrientation,
            'system_tilted_irradiance'
        );
        $baselineIrradiance = self::weightedIrradiance(
            $configuration,
            $baselineGtiByOrientation,
            'baseline_tilted_irradiance'
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
        $systemIrradiancePoints = self::export($systemIrradiance);
        $baselineIrradiancePoints = self::export($baselineIrradiance);
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
            'irradiance' => [
                'system' => $systemIrradiancePoints,
                'baseline' => $baselineIrradiancePoints,
            ],
            'solarInput' => [
                'directNormalIrradiance' => self::export($directNormalIrradiance),
                'airTemperature' => self::export($temperature),
            ],
            'publicValues' => [
                'CurrentPowerForecast' => self::containingValue($power, $now),
                'CurrentBaselinePowerForecast' => self::containingValue(
                    $baselinePower,
                    $now
                ),
                'CurrentGtiSystem' => self::containingValue($systemIrradiance, $now),
                'CurrentGtiBaseline' => self::containingValue($baselineIrradiance, $now),
                'CurrentHorizonLossPercent' => self::currentHorizonLossPercent(
                    $systemIrradiance,
                    $baselineIrradiance,
                    $now
                ),
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
    private static function weightedIrradiance(
        PvConfiguration $configuration,
        array $gtiByOrientation,
        string $field
    ): ForecastSeries {
        $weights = [];
        $totalWeight = 0.0;
        foreach ($configuration->arrays() as $array) {
            $orientationKey = $array['orientationKey'];
            $weights[$orientationKey] = ($weights[$orientationKey] ?? 0.0)
                + $array['peakPowerKw'];
            $totalWeight += $array['peakPowerKw'];
        }
        if ($totalWeight <= 0.0 || count($weights) !== count($gtiByOrientation)) {
            throw new UnexpectedValueException('Solar irradiance weights are invalid.');
        }

        $reference = reset($gtiByOrientation);
        if (!$reference instanceof ForecastSeries || $reference->count() === 0) {
            throw new UnexpectedValueException('Solar irradiance reference is empty.');
        }
        $points = [];
        foreach ($reference->points() as $referencePoint) {
            $weightedValue = 0.0;
            foreach ($weights as $orientationKey => $weight) {
                $series = $gtiByOrientation[$orientationKey] ?? null;
                if (!$series instanceof ForecastSeries) {
                    throw new UnexpectedValueException('Solar irradiance orientation is missing.');
                }
                $point = $series->pointAtSourceTimestamp($referencePoint->sourceTimestamp());
                if (
                    $point === null
                    || $point->validFrom() !== $referencePoint->validFrom()
                    || $point->validTo() !== $referencePoint->validTo()
                ) {
                    throw new UnexpectedValueException('Solar irradiance intervals differ.');
                }
                $weightedValue += (float) $point->value() * $weight;
            }
            $points[] = new ForecastPoint(
                $field,
                'W/m²',
                FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
                $referencePoint->sourceTimestamp(),
                $referencePoint->validFrom(),
                $referencePoint->validTo(),
                $weightedValue / $totalWeight
            );
        }

        return new ForecastSeries($field, 'W/m²', $points);
    }

    private static function currentHorizonLossPercent(
        ForecastSeries $system,
        ForecastSeries $baseline,
        int $timestamp
    ): float {
        $baselineValue = self::containingValue($baseline, $timestamp);
        if ($baselineValue <= 0.0) {
            return 0.0;
        }

        $systemValue = self::containingValue($system, $timestamp);

        return max(0.0, min(100.0, (($baselineValue - $systemValue) / $baselineValue) * 100.0));
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
