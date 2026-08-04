<?php

declare(strict_types=1);

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'saef-calibration-builder-' . bin2hex(random_bytes(6));
if (!mkdir($temporaryDirectory, 0700, true)) {
    throw new RuntimeException('Temporary directory could not be created.');
}

try {
    $configurationPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'configuration.json';
    $outputPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'collector.php';
    $configuration = [
        'snapshotDirectoryRelative' => 'user/saef-open-meteo-calibration',
        'targets' => [[
            'key' => 'solar_test',
            'solarInstanceId' => 101,
            'measurementVariableId' => 102,
            'dailyEnergyVariableId' => 103,
            'maxNonZeroCarrySeconds' => 300,
        ]],
    ];
    file_put_contents($configurationPath, json_encode($configuration, JSON_THROW_ON_ERROR));
    $command = sprintf(
        '%s %s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/../tools/build-calibration-collector.php'),
        escapeshellarg($configurationPath),
        escapeshellarg($outputPath)
    );
    exec($command, $output, $exitCode);
    if ($exitCode !== 0 || !is_file($outputPath)) {
        throw new RuntimeException('Collector builder failed.');
    }
    $source = (string)file_get_contents($outputPath);
    if (substr_count($source, '<?php') !== 1 || substr_count($source, 'declare(strict_types=1);') !== 1) {
        throw new RuntimeException('Generated collector header differs.');
    }
    foreach (['SolarCalibrationCore', 'SolarCalibrationCollectorRuntime', "'solar_test'", 'IPS_GetKernelDir'] as $needle) {
        if (!str_contains($source, $needle)) {
            throw new RuntimeException('Generated collector source is incomplete.');
        }
    }
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($outputPath), $lintOutput, $lintExitCode);
    if ($lintExitCode !== 0) {
        throw new RuntimeException('Generated collector source is not valid PHP.');
    }
} finally {
    foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($temporaryDirectory);
}

echo "solar-calibration-builder: ok\n";
