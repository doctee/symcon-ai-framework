<?php

declare(strict_types=1);

if (!class_exists('SolarCalibrationCore')) {
    require_once __DIR__ . '/SolarCalibrationCore.php';
}

final class SolarCalibrationEvaluationCore
{
    private const MAX_ANALYSES = 1000;
    private const MAX_INPUT_SAMPLES = 100000;

    /** @var array<string, array{minimum: int, maximum: ?int}> */
    private const LEAD_BUCKETS = [
        '00-06h' => ['minimum' => 0, 'maximum' => 6 * 3600],
        '06-24h' => ['minimum' => 6 * 3600, 'maximum' => 24 * 3600],
        '24-48h' => ['minimum' => 24 * 3600, 'maximum' => 48 * 3600],
        '48-72h' => ['minimum' => 48 * 3600, 'maximum' => 72 * 3600],
        '72h+' => ['minimum' => 72 * 3600, 'maximum' => null],
    ];

    /**
     * @param array<int, array<string, mixed>> $analyses
     * @return array<string, mixed>
     */
    public static function evaluate(array $analyses): array
    {
        if ($analyses === [] || count($analyses) > self::MAX_ANALYSES) {
            throw new InvalidArgumentException('Evaluation analysis count is invalid.');
        }

        $targetKey = null;
        $inputSampleCount = 0;
        $operational = [];
        $bucketSelections = [];
        foreach ($analyses as $analysis) {
            if (($analysis['schemaVersion'] ?? null) !== 2) {
                throw new InvalidArgumentException('Evaluation requires schema-v2 analyses.');
            }
            $analysisTarget = $analysis['targetKey'] ?? null;
            $issuedAt = $analysis['issuedAt'] ?? null;
            $samples = $analysis['powerSamples'] ?? null;
            if (
                !is_string($analysisTarget)
                || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $analysisTarget) !== 1
                || !is_int($issuedAt)
                || $issuedAt <= 0
                || !is_array($samples)
            ) {
                throw new InvalidArgumentException('Evaluation analysis metadata is invalid.');
            }
            if ($targetKey !== null && $targetKey !== $analysisTarget) {
                throw new InvalidArgumentException('Evaluation analyses mix target identities.');
            }
            $targetKey = $analysisTarget;

            foreach ($samples as $sample) {
                if (!is_array($sample)) {
                    throw new InvalidArgumentException('Evaluation sample is invalid.');
                }
                $from = $sample['validFrom'] ?? null;
                $to = $sample['validTo'] ?? null;
                if (!is_int($from) || !is_int($to) || $to <= $from || $from < $issuedAt) {
                    throw new InvalidArgumentException('Evaluation sample interval is invalid.');
                }
                $inputSampleCount++;
                if ($inputSampleCount > self::MAX_INPUT_SAMPLES) {
                    throw new InvalidArgumentException('Evaluation sample count is unbounded.');
                }

                $leadSeconds = $from - $issuedAt;
                $candidate = array_merge($sample, [
                    'issuedAt' => $issuedAt,
                    'leadSeconds' => $leadSeconds,
                ]);
                $intervalKey = $from . ':' . $to;
                self::selectShortestLead($operational, $intervalKey, $candidate);

                $bucket = self::leadBucket($leadSeconds);
                if (!isset($bucketSelections[$bucket])) {
                    $bucketSelections[$bucket] = [];
                }
                self::selectShortestLead(
                    $bucketSelections[$bucket],
                    $intervalKey,
                    $candidate
                );
            }
        }

        $leadTimeBuckets = [];
        foreach (array_keys(self::LEAD_BUCKETS) as $bucket) {
            $selected = array_values($bucketSelections[$bucket] ?? []);
            $leadTimeBuckets[$bucket] = self::summarize($selected);
        }

        return [
            'schemaVersion' => 1,
            'targetKey' => $targetKey,
            'method' => [
                'operational' => 'shortest_non_negative_lead_per_interval',
                'leadBuckets' => 'shortest_lead_per_interval_inside_bucket',
                'bucketBoundsSeconds' => self::LEAD_BUCKETS,
            ],
            'analysisCount' => count($analyses),
            'inputSampleCountWithOverlap' => $inputSampleCount,
            'operationalDistinctIntervalCount' => count($operational),
            'operational' => self::summarize(array_values($operational)),
            'leadTimeBuckets' => $leadTimeBuckets,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $selection
     * @param array<string, mixed> $candidate
     */
    private static function selectShortestLead(
        array &$selection,
        string $intervalKey,
        array $candidate
    ): void {
        $leadSeconds = $candidate['leadSeconds'] ?? null;
        if (!is_int($leadSeconds) || $leadSeconds < 0) {
            throw new InvalidArgumentException('Evaluation lead time is invalid.');
        }
        $selectedLead = $selection[$intervalKey]['leadSeconds'] ?? null;
        if (!is_int($selectedLead) || $leadSeconds < $selectedLead) {
            $selection[$intervalKey] = $candidate;
        }
    }

    private static function leadBucket(int $leadSeconds): string
    {
        foreach (self::LEAD_BUCKETS as $name => $bounds) {
            if (
                $leadSeconds >= $bounds['minimum']
                && ($bounds['maximum'] === null || $leadSeconds < $bounds['maximum'])
            ) {
                return $name;
            }
        }

        throw new LogicException('Evaluation lead time has no bucket.');
    }

    /**
     * @param array<int, array<string, mixed>> $samples
     * @return array<string, mixed>
     */
    private static function summarize(array $samples): array
    {
        if ($samples === []) {
            return [
                'sampleCount' => 0,
                'realizedMetrics' => null,
                'calibrationEligibleCount' => 0,
                'calibrationMetrics' => null,
                'classificationCounts' => self::emptyClassificationCounts(),
            ];
        }

        $classificationCounts = self::emptyClassificationCounts();
        $eligible = [];
        foreach ($samples as $sample) {
            $classification = $sample['classification'] ?? null;
            if (!is_string($classification) || !array_key_exists($classification, $classificationCounts)) {
                throw new InvalidArgumentException('Evaluation classification is invalid.');
            }
            $classificationCounts[$classification]++;
            if (($sample['calibrationEligible'] ?? null) === true) {
                $eligible[] = $sample;
            }
        }

        return [
            'sampleCount' => count($samples),
            'realizedMetrics' => SolarCalibrationCore::calculatePowerMetrics($samples),
            'calibrationEligibleCount' => count($eligible),
            'calibrationMetrics' => $eligible === []
                ? null
                : SolarCalibrationCore::calculatePowerMetrics($eligible),
            'classificationCounts' => $classificationCounts,
        ];
    }

    /** @return array<string, int> */
    private static function emptyClassificationCounts(): array
    {
        return [
            'unconstrained' => 0,
            'curtailed' => 0,
            'uncertain' => 0,
            'data_gap' => 0,
        ];
    }
}
