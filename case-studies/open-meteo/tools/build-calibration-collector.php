<?php

declare(strict_types=1);

const CALIBRATION_USAGE = 'Usage: php build-calibration-collector.php <configuration.json> <output.php>';

$arguments = $GLOBALS['argv'] ?? [];
if (!is_array($arguments) || count($arguments) !== 3) {
    fwrite(STDERR, CALIBRATION_USAGE . "\n");
    exit(2);
}

$configurationPath = $arguments[1];
$outputPath = $arguments[2];
if (!is_string($configurationPath) || !is_string($outputPath)) {
    throw new InvalidArgumentException('Collector paths must be strings.');
}
$configuration = json_decode((string)file_get_contents($configurationPath), true, 32, JSON_THROW_ON_ERROR);
if (!is_array($configuration)) {
    throw new RuntimeException('Collector configuration must be a JSON object.');
}

$relativeDirectory = $configuration['snapshotDirectoryRelative'] ?? null;
if (
    !is_string($relativeDirectory)
    || preg_match('#^user/[a-zA-Z0-9][a-zA-Z0-9._/-]{0,127}$#', $relativeDirectory) !== 1
    || str_contains($relativeDirectory, '..')
) {
    throw new InvalidArgumentException('Invalid relative snapshot directory.');
}

$targets = $configuration['targets'] ?? null;
if (!is_array($targets) || $targets === [] || count($targets) > 8) {
    throw new InvalidArgumentException('Invalid collector targets.');
}

$normalizedTargets = [];
$seenKeys = [];
foreach ($targets as $target) {
    if (!is_array($target)) {
        throw new InvalidArgumentException('Collector target must be an object.');
    }
    $key = $target['key'] ?? null;
    if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1 || isset($seenKeys[$key])) {
        throw new InvalidArgumentException('Invalid or duplicated collector target key.');
    }
    $seenKeys[$key] = true;
    foreach (['solarInstanceId', 'measurementVariableId', 'dailyEnergyVariableId'] as $idKey) {
        if (!is_int($target[$idKey] ?? null) || $target[$idKey] <= 0) {
            throw new InvalidArgumentException('Invalid collector target ID.');
        }
    }
    $carry = $target['maxNonZeroCarrySeconds'] ?? null;
    if (!is_int($carry) || $carry <= 0 || $carry > 3600) {
        throw new InvalidArgumentException('Invalid collector target carry bound.');
    }
    $normalizedTargets[] = [
        'key' => $key,
        'solarInstanceId' => $target['solarInstanceId'],
        'measurementVariableId' => $target['measurementVariableId'],
        'dailyEnergyVariableId' => $target['dailyEnergyVariableId'],
        'maxNonZeroCarrySeconds' => $carry,
    ];
}

$candidateDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'candidate';
$core = calibrationBody($candidateDirectory . DIRECTORY_SEPARATOR . 'SolarCalibrationCore.php');
$runtime = calibrationBody($candidateDirectory . DIRECTORY_SEPARATOR . 'SolarCalibrationCollectorRuntime.php');
$runtime = preg_replace(
    "/if \(!class_exists\('SolarCalibrationCore'\)\) \{\s*require_once __DIR__ \. '\/SolarCalibrationCore\.php';\s*\}\s*/",
    '',
    $runtime,
    1,
    $replacementCount
);
if (!is_string($runtime) || $replacementCount !== 1) {
    throw new RuntimeException('Collector runtime include guard could not be removed.');
}

$embeddedConfiguration = var_export([
    'relativeDirectory' => $relativeDirectory,
    'targets' => $normalizedTargets,
], true);
$runner = <<<'PHP'

$embeddedConfiguration = __CONFIGURATION__;
$collectorConfiguration = [
    'snapshotDirectory' => IPS_GetKernelDir()
        . str_replace('/', DIRECTORY_SEPARATOR, $embeddedConfiguration['relativeDirectory']),
    'targets' => $embeddedConfiguration['targets'],
];

try {
    $result = (new SolarCalibrationCollectorRuntime($collectorConfiguration))->run();
    echo json_encode(
        $result,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $throwable) {
    IPS_LogMessage('OpenMeteoCalibration', 'Collector failed: ' . get_class($throwable));
    echo json_encode(
        ['success' => false, 'code' => 'collector_failed', 'exception' => get_class($throwable)],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    );
}
PHP;
$runner = str_replace('__CONFIGURATION__', $embeddedConfiguration, $runner);
$source = "<?php\n\ndeclare(strict_types=1);\n\n" . $core . "\n" . $runtime . "\n" . $runner . "\n";

$outputDirectory = dirname($outputPath);
if (!is_dir($outputDirectory)) {
    throw new RuntimeException('Collector output directory does not exist.');
}
if (file_put_contents($outputPath, $source, LOCK_EX) !== strlen($source)) {
    throw new RuntimeException('Collector source could not be written completely.');
}

echo json_encode([
    'success' => true,
    'output' => $outputPath,
    'sha256' => hash('sha256', $source),
    'targetCount' => count($normalizedTargets),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";

function calibrationBody(string $path): string
{
    $source = file_get_contents($path);
    if (!is_string($source)) {
        throw new RuntimeException('Collector source component could not be read.');
    }
    $source = preg_replace('/^<\?php\s+declare\(strict_types=1\);\s*/', '', $source, 1, $replacementCount);
    if (!is_string($source) || $replacementCount !== 1) {
        throw new RuntimeException('Collector source component header is invalid.');
    }

    return rtrim($source);
}
