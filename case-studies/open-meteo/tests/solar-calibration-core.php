<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/SolarCalibrationCore.php';

function calibrationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$power = [
    ['sourceTimestamp' => 200, 'validFrom' => 100, 'validTo' => 200, 'value' => 0.4, 'unit' => 'kW', 'semantics' => 'preceding_interval'],
    ['sourceTimestamp' => 300, 'validFrom' => 200, 'validTo' => 300, 'value' => 0.8, 'unit' => 'kW', 'semantics' => 'preceding_interval'],
];
$daily = [
    ['sourceTimestamp' => 100, 'validFrom' => 100, 'validTo' => 86500, 'value' => 4.2, 'unit' => 'kWh', 'semantics' => 'local_day'],
];
$snapshot = SolarCalibrationCore::buildSnapshot('solar_a', 150, str_repeat('a', 64), $power, $daily);
calibrationCheck($snapshot['forecastValidFrom'] === 100, 'Snapshot start differs.');
calibrationCheck($snapshot['forecastValidTo'] === 300, 'Snapshot end differs.');
calibrationCheck(count($snapshot['power']) === 2, 'Snapshot power count differs.');

$metrics = SolarCalibrationCore::calculatePowerMetrics([
    ['forecastKw' => 0.4, 'measuredKw' => 0.5, 'durationSeconds' => 3600, 'coverage' => 1.0],
    ['forecastKw' => 0.8, 'measuredKw' => 0.6, 'durationSeconds' => 3600, 'coverage' => 0.5],
]);
calibrationCheck(abs((float)$metrics['forecastEnergyKwh'] - 0.8) < 0.000001, 'Forecast energy differs.');
calibrationCheck(abs((float)$metrics['measuredEnergyKwh'] - 0.8) < 0.000001, 'Measured energy differs.');
calibrationCheck(abs((float)$metrics['coverage'] - 0.75) < 0.000001, 'Coverage differs.');
calibrationCheck(abs((float)$metrics['maeKw'] - (0.2 / 1.5)) < 0.000001, 'MAE differs.');

$aligned = SolarCalibrationCore::alignPowerMeasurements(
    [[
        'validFrom' => 1000,
        'validTo' => 4600,
        'value' => 0.5,
    ]],
    [
        ['timestamp' => 900, 'valueW' => 400.0],
        ['timestamp' => 1900, 'valueW' => 600.0],
        ['timestamp' => 3700, 'valueW' => 0.0],
    ],
    1200
);
calibrationCheck(count($aligned) === 1, 'Aligned sample count differs.');
calibrationCheck(abs($aligned[0]['coverage'] - (3000 / 3600)) < 0.000001, 'Aligned coverage differs.');
calibrationCheck(abs($aligned[0]['measuredKw'] - 0.36) < 0.000001, 'Aligned power differs.');

$classificationSample = [[
    'validFrom' => 1000,
    'validTo' => 4600,
    'forecastKw' => 1.0,
    'measuredKw' => 0.2,
    'durationSeconds' => 3600,
    'coverage' => 1.0,
]];
$policy = [
    'mode' => 'zero_export_storage',
    'minimumForecastKw' => 0.2,
    'maximumRealizedToForecastRatio' => 0.6,
    'minimumMeasurementCoverage' => 0.8,
    'minimumAuxiliaryCoverage' => 0.8,
    'minimumHeartbeatCoverage' => 0.8,
    'fullSocPercent' => 98.0,
    'minimumPossibleFullSocFraction' => 0.1,
    'minimumFullSocFraction' => 0.5,
    'maximumChargeAbsoluteAverageW' => 50.0,
    'maximumGridExportAverageW' => 25.0,
    'maximumGridImportAverageW' => 25.0,
    'signalCarrySeconds' => 900,
    'heartbeatMaxGapSeconds' => 900,
];
$activeEvents = [];
foreach ([900, 1600, 2200, 2800, 3400, 4000] as $timestamp) {
    $activeEvents[] = ['timestamp' => $timestamp, 'value' => 200.0];
}
$signals = [
    'solarPowerW' => $activeEvents,
    'stateOfChargePercent' => [['timestamp' => 900, 'value' => 100]],
    'chargePowerW' => [['timestamp' => 900, 'value' => 0]],
    'outputPowerW' => $activeEvents,
    'homeLoadW' => $activeEvents,
    'gridExportW' => [['timestamp' => 900, 'value' => 0]],
    'gridImportW' => [['timestamp' => 900, 'value' => 0]],
    'statusCode' => [['timestamp' => 900, 'value' => 6]],
];
$classified = SolarCalibrationCore::classifyPowerSamples($classificationSample, $signals, $policy);
calibrationCheck(
    $classified[0]['classification'] === 'curtailed',
    'Curtailment was not classified: ' . json_encode($classified[0], JSON_THROW_ON_ERROR)
);
calibrationCheck($classified[0]['calibrationEligible'] === false, 'Curtailment was calibration eligible.');

$unconstrainedSignals = $signals;
$unconstrainedSignals['stateOfChargePercent'] = [['timestamp' => 900, 'value' => 80]];
$unconstrained = SolarCalibrationCore::classifyPowerSamples(
    $classificationSample,
    $unconstrainedSignals,
    $policy
);
calibrationCheck(
    $unconstrained[0]['classification'] === 'unconstrained',
    'Non-full battery was not unconstrained.'
);
calibrationCheck($unconstrained[0]['calibrationEligible'] === true, 'Unconstrained sample was excluded.');

$partialSignals = $signals;
$partialSignals['stateOfChargePercent'] = [
    ['timestamp' => 900, 'value' => 95],
    ['timestamp' => 3700, 'value' => 100],
];
$uncertain = SolarCalibrationCore::classifyPowerSamples($classificationSample, $partialSignals, $policy);
calibrationCheck($uncertain[0]['classification'] === 'uncertain', 'Partial curtailment was not uncertain.');

$gapSignals = $signals;
$gapSignals['solarPowerW'] = [['timestamp' => 900, 'value' => 200]];
$gapSignals['outputPowerW'] = [['timestamp' => 900, 'value' => 200]];
$gapSignals['homeLoadW'] = [['timestamp' => 900, 'value' => 200]];
$dataGap = SolarCalibrationCore::classifyPowerSamples($classificationSample, $gapSignals, $policy);
calibrationCheck($dataGap[0]['classification'] === 'data_gap', 'Missing heartbeat was not a data gap.');

$summary = SolarCalibrationCore::summarizeClassifications([
    $classified[0],
    $unconstrained[0],
    $uncertain[0],
    $dataGap[0],
]);
calibrationCheck($summary['counts']['curtailed'] === 1, 'Curtailment count differs.');
calibrationCheck($summary['counts']['unconstrained'] === 1, 'Unconstrained count differs.');
calibrationCheck($summary['counts']['uncertain'] === 1, 'Uncertain count differs.');
calibrationCheck($summary['counts']['data_gap'] === 1, 'Data-gap count differs.');
calibrationCheck($summary['calibrationEligibleCount'] === 1, 'Eligible sample count differs.');

$disabled = SolarCalibrationCore::classifyPowerSamples($classificationSample, [], ['mode' => 'none']);
calibrationCheck($disabled[0]['classification'] === 'unconstrained', 'Disabled policy changed classification.');
calibrationCheck($disabled[0]['calibrationEligible'] === true, 'Disabled policy excluded a sample.');

$invalidRejected = false;
try {
    SolarCalibrationCore::buildSnapshot('Solar A', 150, str_repeat('a', 64), $power, $daily);
} catch (InvalidArgumentException) {
    $invalidRejected = true;
}
calibrationCheck($invalidRejected, 'Invalid target key was accepted.');

echo "solar-calibration-core: ok\n";
