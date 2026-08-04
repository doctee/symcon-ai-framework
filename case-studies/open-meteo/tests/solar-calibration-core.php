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

$invalidRejected = false;
try {
    SolarCalibrationCore::buildSnapshot('Solar A', 150, str_repeat('a', 64), $power, $daily);
} catch (InvalidArgumentException) {
    $invalidRejected = true;
}
calibrationCheck($invalidRejected, 'Invalid target key was accepted.');

echo "solar-calibration-core: ok\n";
