<?php

declare(strict_types=1);

final class SolarCalibrationCore
{
    private const MAX_POINTS = 256;
    private const MAX_SAMPLES = 512;
    private const MAX_METRIC_SAMPLES = 10000;
    private const MAX_SIGNAL_EVENTS = 100000;

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
        if ($samples === [] || count($samples) > self::MAX_METRIC_SAMPLES) {
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

    /**
     * Classifies storage-constrained power samples without discarding the
     * realized measurement. Only unequivocally unconstrained samples are
     * eligible for physical forecast calibration.
     *
     * @param array<int, array<string, mixed>> $samples
     * @param array<string, mixed> $signalEvents
     * @param array<string, mixed> $policy
     * @return array<int, array<string, mixed>>
     */
    public static function classifyPowerSamples(array $samples, array $signalEvents, array $policy): array
    {
        if ($samples === [] || count($samples) > self::MAX_SAMPLES) {
            throw new InvalidArgumentException('Classification sample count is invalid.');
        }

        $mode = $policy['mode'] ?? null;
        if ($mode === 'none') {
            return array_map(
                static fn(array $sample): array => $sample + [
                    'classification' => 'unconstrained',
                    'calibrationEligible' => true,
                    'classificationReasons' => ['policy_disabled'],
                    'constraintEvidence' => null,
                ],
                $samples
            );
        }
        if ($mode !== 'zero_export_storage') {
            throw new InvalidArgumentException('Unsupported curtailment policy mode.');
        }

        $thresholds = self::validatedCurtailmentThresholds($policy);
        $requiredSignals = [
            'solarPowerW',
            'stateOfChargePercent',
            'chargePowerW',
            'outputPowerW',
            'homeLoadW',
            'gridExportW',
            'gridImportW',
            'statusCode',
        ];
        $normalizedSignals = [];
        foreach ($requiredSignals as $signal) {
            if (!isset($signalEvents[$signal]) || !is_array($signalEvents[$signal])) {
                throw new InvalidArgumentException('Required curtailment signal is missing: ' . $signal);
            }
            $normalizedSignals[$signal] = self::normalizeSignalEvents($signalEvents[$signal], $signal);
        }

        $classified = [];
        foreach ($samples as $sample) {
            $from = $sample['validFrom'] ?? null;
            $to = $sample['validTo'] ?? null;
            $forecastKw = self::finiteNonNegative($sample['forecastKw'] ?? null, 'forecast power');
            $measuredKw = self::finiteNonNegative($sample['measuredKw'] ?? null, 'measured power');
            $measurementCoverage = self::finiteRange(
                $sample['coverage'] ?? null,
                0.0,
                1.0,
                'measurement coverage'
            );
            if (!is_int($from) || !is_int($to) || $from <= 0 || $to <= $from) {
                throw new InvalidArgumentException('Invalid classification interval.');
            }

            $summaries = [];
            foreach ($normalizedSignals as $signal => $events) {
                $carrySeconds = in_array($signal, ['stateOfChargePercent', 'statusCode'], true)
                    ? null
                    : $thresholds['signalCarrySeconds'];
                $summaries[$signal] = self::summarizeSignalInterval(
                    $events,
                    $from,
                    $to,
                    $carrySeconds,
                    $signal === 'stateOfChargePercent' ? $thresholds['fullSocPercent'] : null
                );
            }
            $heartbeat = self::heartbeatCoverage(
                $normalizedSignals,
                $from,
                $to,
                $thresholds['heartbeatMaxGapSeconds']
            );
            $ratio = $forecastKw > 0.0 ? $measuredKw / $forecastKw : null;

            [$classification, $eligible, $reasons] = self::classifyCurtailmentEvidence(
                $forecastKw,
                $ratio,
                $measurementCoverage,
                $summaries,
                $heartbeat,
                $thresholds
            );

            $classified[] = $sample + [
                'classification' => $classification,
                'calibrationEligible' => $eligible,
                'classificationReasons' => $reasons,
                'constraintEvidence' => [
                    'realizedToForecastRatio' => $ratio,
                    'heartbeatCoverage' => $heartbeat,
                    'fullSocFraction' => $summaries['stateOfChargePercent']['thresholdFraction'],
                    'socMinimumPercent' => $summaries['stateOfChargePercent']['minimum'],
                    'socMaximumPercent' => $summaries['stateOfChargePercent']['maximum'],
                    'chargeAbsoluteAverageW' => $summaries['chargePowerW']['absoluteAverage'],
                    'outputAverageW' => $summaries['outputPowerW']['average'],
                    'homeLoadAverageW' => $summaries['homeLoadW']['average'],
                    'gridExportAverageW' => $summaries['gridExportW']['average'],
                    'gridImportAverageW' => $summaries['gridImportW']['average'],
                    'statusDominantCode' => $summaries['statusCode']['dominantValue'],
                    'auxiliaryCoverage' => self::minimumSignalCoverage($summaries),
                    'signalCoverage' => array_map(
                        static fn(array $summary): float|null => $summary['coverage'],
                        $summaries
                    ),
                ],
            ];
        }

        return $classified;
    }

    /**
     * @param array<int, array<string, mixed>> $samples
     * @return array<string, mixed>
     */
    public static function summarizeClassifications(array $samples): array
    {
        $allowed = ['unconstrained', 'curtailed', 'uncertain', 'data_gap'];
        $counts = array_fill_keys($allowed, 0);
        $seconds = array_fill_keys($allowed, 0);
        $eligible = [];
        foreach ($samples as $sample) {
            $classification = $sample['classification'] ?? null;
            $duration = $sample['durationSeconds'] ?? null;
            if (!is_string($classification) || !array_key_exists($classification, $counts)) {
                throw new InvalidArgumentException('Invalid sample classification.');
            }
            if (!is_int($duration) || $duration <= 0) {
                throw new InvalidArgumentException('Invalid classified sample duration.');
            }
            $counts[$classification]++;
            $seconds[$classification] += $duration;
            if (($sample['calibrationEligible'] ?? null) === true) {
                $eligible[] = $sample;
            }
        }

        return [
            'counts' => $counts,
            'durationSeconds' => $seconds,
            'calibrationEligibleCount' => count($eligible),
            'calibrationMetrics' => $eligible === [] ? null : self::calculatePowerMetrics($eligible),
        ];
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

    /**
     * @param array<string, mixed> $policy
     * @return array{
     *   minimumForecastKw: float,
     *   maximumRealizedToForecastRatio: float,
     *   minimumMeasurementCoverage: float,
     *   minimumAuxiliaryCoverage: float,
     *   minimumHeartbeatCoverage: float,
     *   fullSocPercent: float,
     *   minimumPossibleFullSocFraction: float,
     *   minimumFullSocFraction: float,
     *   maximumChargeAbsoluteAverageW: float,
     *   maximumGridExportAverageW: float,
     *   maximumGridImportAverageW: float,
     *   signalCarrySeconds: int,
     *   heartbeatMaxGapSeconds: int
     * }
     */
    private static function validatedCurtailmentThresholds(array $policy): array
    {
        $validated = [
            'minimumForecastKw' => self::finiteRange($policy['minimumForecastKw'] ?? null, 0.0, 10.0, 'minimumForecastKw'),
            'maximumRealizedToForecastRatio' => self::finiteRange($policy['maximumRealizedToForecastRatio'] ?? null, 0.0, 1.0, 'maximumRealizedToForecastRatio'),
            'minimumMeasurementCoverage' => self::finiteRange($policy['minimumMeasurementCoverage'] ?? null, 0.0, 1.0, 'minimumMeasurementCoverage'),
            'minimumAuxiliaryCoverage' => self::finiteRange($policy['minimumAuxiliaryCoverage'] ?? null, 0.0, 1.0, 'minimumAuxiliaryCoverage'),
            'minimumHeartbeatCoverage' => self::finiteRange($policy['minimumHeartbeatCoverage'] ?? null, 0.0, 1.0, 'minimumHeartbeatCoverage'),
            'fullSocPercent' => self::finiteRange($policy['fullSocPercent'] ?? null, 0.0, 100.0, 'fullSocPercent'),
            'minimumPossibleFullSocFraction' => self::finiteRange($policy['minimumPossibleFullSocFraction'] ?? null, 0.0, 1.0, 'minimumPossibleFullSocFraction'),
            'minimumFullSocFraction' => self::finiteRange($policy['minimumFullSocFraction'] ?? null, 0.0, 1.0, 'minimumFullSocFraction'),
            'maximumChargeAbsoluteAverageW' => self::finiteRange($policy['maximumChargeAbsoluteAverageW'] ?? null, 0.0, 10 * 1000.0, 'maximumChargeAbsoluteAverageW'),
            'maximumGridExportAverageW' => self::finiteRange($policy['maximumGridExportAverageW'] ?? null, 0.0, 10 * 1000.0, 'maximumGridExportAverageW'),
            'maximumGridImportAverageW' => self::finiteRange($policy['maximumGridImportAverageW'] ?? null, 0.0, 10 * 1000.0, 'maximumGridImportAverageW'),
            'signalCarrySeconds' => 0,
            'heartbeatMaxGapSeconds' => 0,
        ];
        if ($validated['minimumPossibleFullSocFraction'] > $validated['minimumFullSocFraction']) {
            throw new InvalidArgumentException('Possible full-SOC fraction exceeds the confirmed threshold.');
        }
        foreach (['signalCarrySeconds', 'heartbeatMaxGapSeconds'] as $key) {
            $value = $policy[$key] ?? null;
            if (!is_int($value) || $value <= 0 || $value > 3600) {
                throw new InvalidArgumentException('Invalid curtailment threshold: ' . $key);
            }
            $validated[$key] = $value;
        }

        return $validated;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, float>
     */
    private static function normalizeSignalEvents(array $events, string $label): array
    {
        if (count($events) > self::MAX_SIGNAL_EVENTS) {
            throw new InvalidArgumentException('Curtailment signal event count is unbounded.');
        }
        $normalized = [];
        foreach ($events as $event) {
            $timestamp = $event['timestamp'] ?? null;
            $value = $event['value'] ?? null;
            if (!is_int($timestamp) || $timestamp <= 0 || (!is_int($value) && !is_float($value))) {
                throw new InvalidArgumentException('Invalid curtailment signal event: ' . $label);
            }
            $number = (float)$value;
            if (!is_finite($number)) {
                throw new InvalidArgumentException('Non-finite curtailment signal event: ' . $label);
            }
            $normalized[$timestamp] = $number;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int, float> $events
     * @return array<string, float|null>
     */
    private static function summarizeSignalInterval(
        array $events,
        int $from,
        int $to,
        ?int $carrySeconds,
        ?float $threshold
    ): array {
        $currentTimestamp = null;
        $currentValue = null;
        foreach ($events as $timestamp => $value) {
            if ($timestamp > $from) {
                break;
            }
            $currentTimestamp = $timestamp;
            $currentValue = $value;
        }
        if ($currentTimestamp === null || $currentValue === null) {
            return self::emptySignalSummary();
        }

        $cursor = $from;
        $coveredSeconds = 0;
        $weighted = 0.0;
        $weightedAbsolute = 0.0;
        $minimum = null;
        $maximum = null;
        $thresholdSeconds = 0;
        $durations = [];
        foreach ($events as $timestamp => $value) {
            if ($timestamp <= $from) {
                continue;
            }
            if ($timestamp >= $to) {
                break;
            }
            self::appendSignalSegment(
                $cursor,
                $timestamp,
                $currentTimestamp,
                $currentValue,
                $carrySeconds,
                $threshold,
                $coveredSeconds,
                $weighted,
                $weightedAbsolute,
                $minimum,
                $maximum,
                $thresholdSeconds,
                $durations
            );
            $cursor = $timestamp;
            $currentTimestamp = $timestamp;
            $currentValue = $value;
        }
        self::appendSignalSegment(
            $cursor,
            $to,
            $currentTimestamp,
            $currentValue,
            $carrySeconds,
            $threshold,
            $coveredSeconds,
            $weighted,
            $weightedAbsolute,
            $minimum,
            $maximum,
            $thresholdSeconds,
            $durations
        );
        if ($coveredSeconds <= 0) {
            return self::emptySignalSummary();
        }
        arsort($durations, SORT_NUMERIC);
        $dominantKey = array_key_first($durations);

        return [
            'coverage' => (float)($coveredSeconds / ($to - $from)),
            'average' => (float)($weighted / $coveredSeconds),
            'absoluteAverage' => (float)($weightedAbsolute / $coveredSeconds),
            'minimum' => $minimum,
            'maximum' => $maximum,
            'thresholdFraction' => $threshold === null
                ? null
                : (float)($thresholdSeconds / $coveredSeconds),
            'dominantValue' => is_string($dominantKey) ? (float)$dominantKey : null,
        ];
    }

    /** @return array<string, float|null> */
    private static function emptySignalSummary(): array
    {
        return [
            'coverage' => 0.0,
            'average' => null,
            'absoluteAverage' => null,
            'minimum' => null,
            'maximum' => null,
            'thresholdFraction' => null,
            'dominantValue' => null,
        ];
    }

    /**
     * @param array<string, array<int, float>> $signals
     */
    private static function heartbeatCoverage(array $signals, int $from, int $to, int $maximumGapSeconds): float
    {
        $timestamps = [];
        foreach ($signals as $events) {
            foreach (array_keys($events) as $timestamp) {
                $timestamps[$timestamp] = true;
            }
        }
        if ($timestamps === []) {
            return 0.0;
        }
        $ordered = array_keys($timestamps);
        sort($ordered, SORT_NUMERIC);
        $last = null;
        foreach ($ordered as $timestamp) {
            if ($timestamp > $from) {
                break;
            }
            $last = $timestamp;
        }
        $cursor = $from;
        $coveredSeconds = 0;
        foreach ($ordered as $timestamp) {
            if ($timestamp <= $from) {
                continue;
            }
            if ($timestamp >= $to) {
                break;
            }
            if ($last !== null) {
                $coveredSeconds += max(0, min($timestamp, $last + $maximumGapSeconds) - $cursor);
            }
            $cursor = $timestamp;
            $last = $timestamp;
        }
        if ($last !== null) {
            $coveredSeconds += max(0, min($to, $last + $maximumGapSeconds) - $cursor);
        }

        return min(1.0, $coveredSeconds / ($to - $from));
    }

    /**
     * @param array<string, array<string, float|null>> $summaries
     * @param array<string, int|float> $thresholds
     * @return array{string, bool, array<int, string>}
     */
    private static function classifyCurtailmentEvidence(
        float $forecastKw,
        ?float $ratio,
        float $measurementCoverage,
        array $summaries,
        float $heartbeatCoverage,
        array $thresholds
    ): array {
        if ($measurementCoverage < $thresholds['minimumMeasurementCoverage']) {
            return ['data_gap', false, ['measurement_coverage_low']];
        }
        if ($forecastKw < $thresholds['minimumForecastKw']) {
            return ['unconstrained', true, ['forecast_below_curtailment_scope']];
        }
        if ($ratio === null || $ratio > $thresholds['maximumRealizedToForecastRatio']) {
            return ['unconstrained', true, ['realized_power_not_materially_constrained']];
        }
        if ($heartbeatCoverage < $thresholds['minimumHeartbeatCoverage']) {
            return ['data_gap', false, ['archive_heartbeat_coverage_low']];
        }
        if (self::minimumSignalCoverage($summaries) < $thresholds['minimumAuxiliaryCoverage']) {
            return ['data_gap', false, ['auxiliary_signal_coverage_low']];
        }

        $fullSocFraction = $summaries['stateOfChargePercent']['thresholdFraction'];
        if (!is_float($fullSocFraction) || $fullSocFraction < $thresholds['minimumPossibleFullSocFraction']) {
            return ['unconstrained', true, ['battery_not_full_for_required_fraction']];
        }
        if ($fullSocFraction < $thresholds['minimumFullSocFraction']) {
            return ['uncertain', false, ['battery_full_only_partially']];
        }

        $charge = $summaries['chargePowerW']['absoluteAverage'];
        $export = $summaries['gridExportW']['average'];
        $import = $summaries['gridImportW']['average'];
        if (!is_float($charge) || !is_float($export) || !is_float($import)) {
            return ['data_gap', false, ['required_flow_summary_missing']];
        }
        if ($charge > $thresholds['maximumChargeAbsoluteAverageW']) {
            return ['unconstrained', true, ['battery_power_flow_active']];
        }
        if ($export > $thresholds['maximumGridExportAverageW']) {
            return ['unconstrained', true, ['grid_export_absorbs_generation']];
        }
        if ($import > $thresholds['maximumGridImportAverageW']) {
            return ['unconstrained', true, ['grid_import_indicates_unmet_demand']];
        }

        return ['curtailed', false, [
            'battery_full',
            'realized_power_below_forecast',
            'battery_not_absorbing',
            'grid_export_near_zero',
            'grid_import_near_zero',
        ]];
    }

    /** @param array<string, array<string, float|null>> $summaries */
    private static function minimumSignalCoverage(array $summaries): float
    {
        $minimum = 1.0;
        foreach ($summaries as $summary) {
            $coverage = $summary['coverage'] ?? null;
            if ($coverage === null) {
                return 0.0;
            }
            $minimum = min($minimum, $coverage);
        }

        return $minimum;
    }

    /**
     * @param array<int|string, int> $durations
     */
    private static function appendSignalSegment(
        int $from,
        int $to,
        int $valueTimestamp,
        float $value,
        ?int $carrySeconds,
        ?float $threshold,
        int &$coveredSeconds,
        float &$weighted,
        float &$weightedAbsolute,
        ?float &$minimum,
        ?float &$maximum,
        int &$thresholdSeconds,
        array &$durations
    ): void {
        if ($to <= $from) {
            return;
        }
        $coveredTo = $carrySeconds === null || abs($value) <= 0.0000001
            ? $to
            : min($to, $valueTimestamp + $carrySeconds);
        if ($coveredTo <= $from) {
            return;
        }
        $seconds = $coveredTo - $from;
        $coveredSeconds += $seconds;
        $weighted += $value * $seconds;
        $weightedAbsolute += abs($value) * $seconds;
        $minimum = $minimum === null ? $value : min($minimum, $value);
        $maximum = $maximum === null ? $value : max($maximum, $value);
        if ($threshold !== null && $value >= $threshold) {
            $thresholdSeconds += $seconds;
        }
        $key = sprintf('%.6F', $value);
        $durations[$key] = ($durations[$key] ?? 0) + $seconds;
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
