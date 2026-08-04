<?php

declare(strict_types=1);

final class SolarCalibrationCore
{
    private const MAX_POINTS = 256;
    private const MAX_SAMPLES = 512;

    /**
     * @param array<int, array<string, mixed>> $powerPoints
     * @param array<int, array<string, mixed>> $dailyEnergyPoints
     * @return array<string, mixed>
     */
    public static function buildSnapshot(
        string $targetKey,
        int $issuedAt,
        string $configurationHash,
        array $powerPoints,
        array $dailyEnergyPoints
    ): array {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $targetKey) !== 1) {
            throw new InvalidArgumentException('Invalid calibration target key.');
        }
        if ($issuedAt <= 0) {
            throw new InvalidArgumentException('Snapshot issue time must be positive.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $configurationHash) !== 1) {
            throw new InvalidArgumentException('Invalid configuration hash.');
        }

        $power = self::normalizePoints($powerPoints, 'kW', 'preceding_interval');
        $dailyEnergy = self::normalizePoints($dailyEnergyPoints, 'kWh', 'local_day');
        $firstPowerPoint = $power[0];
        $lastPowerPoint = $power[count($power) - 1];

        return [
            'schemaVersion' => 1,
            'targetKey' => $targetKey,
            'issuedAt' => $issuedAt,
            'configurationHash' => $configurationHash,
            'power' => $power,
            'dailyEnergy' => $dailyEnergy,
            'forecastValidFrom' => $firstPowerPoint['validFrom'],
            'forecastValidTo' => $lastPowerPoint['validTo'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $samples
     * @return array<string, int|float|null>
     */
    public static function calculatePowerMetrics(array $samples): array
    {
        if ($samples === [] || count($samples) > self::MAX_SAMPLES) {
            throw new InvalidArgumentException('Calibration sample count is invalid.');
        }

        $weightedBias = 0.0;
        $weightedAbsoluteError = 0.0;
        $weightedSquaredError = 0.0;
        $forecastEnergy = 0.0;
        $measuredEnergy = 0.0;
        $weightHours = 0.0;
        $coverageSeconds = 0.0;
        $totalSeconds = 0;

        foreach ($samples as $sample) {
            $forecast = self::finiteNonNegative($sample['forecastKw'] ?? null, 'forecast power');
            $measured = self::finiteNonNegative($sample['measuredKw'] ?? null, 'measured power');
            $duration = $sample['durationSeconds'] ?? 0;
            $coverage = self::finiteRange($sample['coverage'] ?? null, 0.0, 1.0, 'coverage');
            if (!is_int($duration) || $duration <= 0 || $duration > 86400) {
                throw new InvalidArgumentException('Invalid sample duration.');
            }

            $hours = ($duration * $coverage) / 3600.0;
            $error = $measured - $forecast;
            $weightedBias += $error * $hours;
            $weightedAbsoluteError += abs($error) * $hours;
            $weightedSquaredError += ($error ** 2) * $hours;
            $forecastEnergy += $forecast * $hours;
            $measuredEnergy += $measured * $hours;
            $weightHours += $hours;
            $coverageSeconds += $duration * $coverage;
            $totalSeconds += $duration;
        }

        if ($weightHours <= 0.0) {
            throw new InvalidArgumentException('Calibration samples have no measured coverage.');
        }

        return [
            'sampleCount' => count($samples),
            'weightHours' => $weightHours,
            'coverage' => $coverageSeconds / $totalSeconds,
            'forecastEnergyKwh' => $forecastEnergy,
            'measuredEnergyKwh' => $measuredEnergy,
            'biasKw' => $weightedBias / $weightHours,
            'maeKw' => $weightedAbsoluteError / $weightHours,
            'rmseKw' => sqrt($weightedSquaredError / $weightHours),
            'energyRatio' => $forecastEnergy > 0.0 ? $measuredEnergy / $forecastEnergy : null,
        ];
    }

    /**
     * Converts change-based watt measurements into forecast-interval samples.
     * Non-zero values are carried only for the configured freshness bound;
     * zero may be carried across the complete interval because unchanged night
     * values are intentionally not written repeatedly by many collectors.
     *
     * @param array<int, array<string, mixed>> $forecastPoints
     * @param array<int, array<string, mixed>> $measurementEvents
     * @return array<int, array{validFrom: int, validTo: int, forecastKw: float, measuredKw: float, durationSeconds: int, coverage: float}>
     */
    public static function alignPowerMeasurements(
        array $forecastPoints,
        array $measurementEvents,
        int $maxNonZeroCarrySeconds
    ): array {
        if ($maxNonZeroCarrySeconds <= 0 || $maxNonZeroCarrySeconds > 3600) {
            throw new InvalidArgumentException('Invalid measurement carry bound.');
        }

        $events = [];
        foreach ($measurementEvents as $event) {
            $timestamp = $event['timestamp'] ?? null;
            if (!is_int($timestamp) || $timestamp <= 0) {
                throw new InvalidArgumentException('Invalid measurement timestamp.');
            }
            $events[$timestamp] = self::finiteNonNegative($event['valueW'] ?? null, 'measurement value');
        }
        if ($events === []) {
            return [];
        }
        ksort($events, SORT_NUMERIC);

        $samples = [];
        foreach ($forecastPoints as $point) {
            $from = $point['validFrom'] ?? null;
            $to = $point['validTo'] ?? null;
            if (!is_int($from) || !is_int($to) || $from <= 0 || $to <= $from) {
                throw new InvalidArgumentException('Invalid forecast interval for alignment.');
            }
            $forecastKw = self::finiteNonNegative($point['value'] ?? null, 'forecast value');

            $currentTimestamp = null;
            $currentValueW = null;
            foreach ($events as $timestamp => $valueW) {
                if ($timestamp > $from) {
                    break;
                }
                $currentTimestamp = $timestamp;
                $currentValueW = $valueW;
            }
            if ($currentTimestamp === null || $currentValueW === null) {
                continue;
            }

            $cursor = $from;
            $coveredSeconds = 0;
            $measuredEnergyKwh = 0.0;
            foreach ($events as $timestamp => $valueW) {
                if ($timestamp <= $from) {
                    continue;
                }
                if ($timestamp >= $to) {
                    break;
                }
                self::appendMeasurementSegment(
                    $cursor,
                    $timestamp,
                    $currentTimestamp,
                    $currentValueW,
                    $maxNonZeroCarrySeconds,
                    $coveredSeconds,
                    $measuredEnergyKwh
                );
                $cursor = $timestamp;
                $currentTimestamp = $timestamp;
                $currentValueW = $valueW;
            }
            self::appendMeasurementSegment(
                $cursor,
                $to,
                $currentTimestamp,
                $currentValueW,
                $maxNonZeroCarrySeconds,
                $coveredSeconds,
                $measuredEnergyKwh
            );

            if ($coveredSeconds <= 0) {
                continue;
            }
            $duration = $to - $from;
            $samples[] = [
                'validFrom' => $from,
                'validTo' => $to,
                'forecastKw' => $forecastKw,
                'measuredKw' => $measuredEnergyKwh / ($coveredSeconds / 3600.0),
                'durationSeconds' => $duration,
                'coverage' => $coveredSeconds / $duration,
            ];
        }

        return $samples;
    }

    /** @param array<string, mixed> $value */
    public static function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ) . "\n";
    }

    /**
     * @param array<int, array<string, mixed>> $points
     * @return non-empty-array<int, array{sourceTimestamp: int, validFrom: int, validTo: int, value: float, unit: string, semantics: string}>
     */
    private static function normalizePoints(array $points, string $unit, string $semantics): array
    {
        if ($points === [] || count($points) > self::MAX_POINTS) {
            throw new InvalidArgumentException('Forecast point count is invalid.');
        }

        $normalized = [];
        $seen = [];
        foreach ($points as $point) {
            $sourceTimestamp = $point['sourceTimestamp'] ?? null;
            $validFrom = $point['validFrom'] ?? null;
            $validTo = $point['validTo'] ?? null;
            if (
                !is_int($sourceTimestamp) || !is_int($validFrom) || !is_int($validTo)
                || $sourceTimestamp <= 0 || $validFrom <= 0 || $validTo <= $validFrom
            ) {
                throw new InvalidArgumentException('Invalid forecast interval.');
            }
            if (($point['unit'] ?? null) !== $unit || ($point['semantics'] ?? null) !== $semantics) {
                throw new InvalidArgumentException('Forecast unit or semantics mismatch.');
            }
            $key = $validFrom . ':' . $validTo;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate forecast interval.');
            }
            $seen[$key] = true;
            $normalized[] = [
                'sourceTimestamp' => $sourceTimestamp,
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'value' => self::finiteNonNegative($point['value'] ?? null, 'forecast value'),
                'unit' => $unit,
                'semantics' => $semantics,
            ];
        }

        usort($normalized, static fn(array $left, array $right): int => $left['validFrom'] <=> $right['validFrom']);

        return $normalized;
    }

    private static function finiteNonNegative(mixed $value, string $label): float
    {
        return self::finiteRange($value, 0.0, PHP_FLOAT_MAX, $label);
    }

    private static function finiteRange(mixed $value, float $minimum, float $maximum, string $label): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Invalid ' . $label . '.');
        }
        $number = (float)$value;
        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException('Invalid ' . $label . '.');
        }

        return $number;
    }

    private static function appendMeasurementSegment(
        int $from,
        int $to,
        int $valueTimestamp,
        float $valueW,
        int $maxNonZeroCarrySeconds,
        int &$coveredSeconds,
        float &$measuredEnergyKwh
    ): void {
        if ($to <= $from) {
            return;
        }
        $coveredTo = $valueW <= 0.0000001
            ? $to
            : min($to, $valueTimestamp + $maxNonZeroCarrySeconds);
        if ($coveredTo <= $from) {
            return;
        }
        $seconds = $coveredTo - $from;
        $coveredSeconds += $seconds;
        $measuredEnergyKwh += ($valueW / 1000.0) * ($seconds / 3600.0);
    }
}
