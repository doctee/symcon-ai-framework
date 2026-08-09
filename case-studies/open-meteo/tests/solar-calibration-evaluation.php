<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/SolarCalibrationEvaluationCore.php';

function evaluationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return array{
 *   validFrom: int,
 *   validTo: int,
 *   forecastKw: float,
 *   measuredKw: float,
 *   durationSeconds: int,
 *   coverage: float,
 *   classification: string,
 *   calibrationEligible: bool
 * }
 */
function evaluationSample(
    int $from,
    float $forecast,
    float $measured,
    string $classification = 'unconstrained',
    bool $eligible = true
): array {
    return [
        'validFrom' => $from,
        'validTo' => $from + 3600,
        'forecastKw' => $forecast,
        'measuredKw' => $measured,
        'durationSeconds' => 3600,
        'coverage' => 1.0,
        'classification' => $classification,
        'calibrationEligible' => $eligible,
    ];
}

$interval = 500000;
$analyses = [
    [
        'schemaVersion' => 2,
        'targetKey' => 'solar_a',
        'issuedAt' => $interval - 30 * 3600,
        'powerSamples' => [
            evaluationSample($interval, 1.0, 0.5),
            evaluationSample($interval + 3600, 1.0, 0.2, 'curtailed', false),
        ],
    ],
    [
        'schemaVersion' => 2,
        'targetKey' => 'solar_a',
        'issuedAt' => $interval - 2 * 3600,
        'powerSamples' => [
            evaluationSample($interval, 0.8, 0.5),
            evaluationSample($interval + 3600, 0.7, 0.2, 'curtailed', false),
        ],
    ],
];

$evaluation = SolarCalibrationEvaluationCore::evaluate($analyses);
evaluationCheck($evaluation['analysisCount'] === 2, 'Analysis count differs.');
evaluationCheck($evaluation['inputSampleCountWithOverlap'] === 4, 'Input sample count differs.');
evaluationCheck($evaluation['operationalDistinctIntervalCount'] === 2, 'Operational deduplication differs.');
evaluationCheck(
    abs((float)$evaluation['operational']['realizedMetrics']['forecastEnergyKwh'] - 1.5) < 0.000001,
    'Operational selection did not keep the shortest lead.'
);
evaluationCheck(
    $evaluation['operational']['calibrationEligibleCount'] === 1,
    'Ineligible curtailment entered calibration metrics.'
);
evaluationCheck(
    abs((float)$evaluation['operational']['calibrationMetrics']['energyRatio'] - 0.625) < 0.000001,
    'Calibration ratio differs.'
);
evaluationCheck(
    $evaluation['leadTimeBuckets']['00-06h']['sampleCount'] === 2,
    'Short lead bucket differs.'
);
evaluationCheck(
    $evaluation['leadTimeBuckets']['24-48h']['sampleCount'] === 2,
    'Long lead bucket differs.'
);
evaluationCheck(
    $evaluation['leadTimeBuckets']['06-24h']['sampleCount'] === 0,
    'Empty lead bucket differs.'
);

$mixedTargetsRejected = false;
try {
    $mixed = $analyses;
    $mixed[1]['targetKey'] = 'solar_b';
    SolarCalibrationEvaluationCore::evaluate($mixed);
} catch (InvalidArgumentException) {
    $mixedTargetsRejected = true;
}
evaluationCheck($mixedTargetsRejected, 'Mixed target identities were accepted.');

echo "solar-calibration-evaluation: ok\n";
