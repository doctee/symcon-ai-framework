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
        'curtailmentPolicy' => calibrationCurtailmentPolicy($target['curtailmentPolicy'] ?? ['mode' => 'none']),
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
$sender = $GLOBALS['_IPS']['SENDER'] ?? null;
$emitResult = $sender !== 'TimerEvent';

try {
    $result = (new SolarCalibrationCollectorRuntime($collectorConfiguration))->run();
    if ($emitResult) {
        echo json_encode(
            $result,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
} catch (Throwable $throwable) {
    IPS_LogMessage('OpenMeteoCalibration', 'Collector failed: ' . get_class($throwable));
    if ($emitResult) {
        echo json_encode(
            ['success' => false, 'code' => 'collector_failed', 'exception' => get_class($throwable)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    }
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

/**
 * @param mixed $policy
 * @return array<string, mixed>
 */
function calibrationCurtailmentPolicy(mixed $policy): array
{
    if (!is_array($policy)) {
        throw new InvalidArgumentException('Curtailment policy must be an object.');
    }
    $mode = $policy['mode'] ?? null;
    if ($mode === 'none') {
        return ['mode' => 'none'];
    }
    if ($mode !== 'zero_export_storage') {
        throw new InvalidArgumentException('Curtailment policy mode is invalid.');
    }

    $requiredSignals = [
        'stateOfChargePercent',
        'chargePowerW',
        'outputPowerW',
        'homeLoadW',
        'gridExportW',
        'gridImportW',
        'statusCode',
    ];
    $signals = $policy['signalVariableIds'] ?? null;
    if (!is_array($signals)) {
        throw new InvalidArgumentException('Curtailment signal mapping is invalid.');
    }
    $actualSignalKeys = array_keys($signals);
    sort($actualSignalKeys, SORT_STRING);
    $expectedSignalKeys = $requiredSignals;
    sort($expectedSignalKeys, SORT_STRING);
    if ($actualSignalKeys !== $expectedSignalKeys) {
        throw new InvalidArgumentException('Curtailment signal mapping is incomplete.');
    }
    $normalizedSignals = [];
    foreach ($requiredSignals as $signal) {
        $variableId = $signals[$signal];
        if (!is_int($variableId) || $variableId <= 0) {
            throw new InvalidArgumentException('Curtailment signal ID is invalid.');
        }
        $normalizedSignals[$signal] = $variableId;
    }

    $ranges = [
        'minimumForecastKw' => [0.0, 10.0],
        'maximumRealizedToForecastRatio' => [0.0, 1.0],
        'minimumMeasurementCoverage' => [0.0, 1.0],
        'minimumAuxiliaryCoverage' => [0.0, 1.0],
        'minimumHeartbeatCoverage' => [0.0, 1.0],
        'fullSocPercent' => [0.0, 100.0],
        'minimumPossibleFullSocFraction' => [0.0, 1.0],
        'minimumFullSocFraction' => [0.0, 1.0],
        'maximumChargeAbsoluteAverageW' => [0.0, 10 * 1000.0],
        'maximumGridExportAverageW' => [0.0, 10 * 1000.0],
        'maximumGridImportAverageW' => [0.0, 10 * 1000.0],
    ];
    $normalized = [
        'mode' => $mode,
        'signalVariableIds' => $normalizedSignals,
    ];
    foreach ($ranges as $key => [$minimum, $maximum]) {
        $value = $policy[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Curtailment threshold is not numeric.');
        }
        $number = (float)$value;
        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException('Curtailment threshold is out of range.');
        }
        $normalized[$key] = $number;
    }
    $possibleFullSocFraction = $normalized['minimumPossibleFullSocFraction'] ?? null;
    $confirmedFullSocFraction = $normalized['minimumFullSocFraction'] ?? null;
    if (!is_float($possibleFullSocFraction) || !is_float($confirmedFullSocFraction)) {
        throw new LogicException('Full-SOC thresholds were not normalized.');
    }
    if ($possibleFullSocFraction > $confirmedFullSocFraction) {
        throw new InvalidArgumentException('Possible full-SOC fraction exceeds the confirmed threshold.');
    }
    foreach (['signalCarrySeconds', 'heartbeatMaxGapSeconds'] as $key) {
        $value = $policy[$key] ?? null;
        if (!is_int($value) || $value <= 0 || $value > 3600) {
            throw new InvalidArgumentException('Curtailment duration is invalid.');
        }
        $normalized[$key] = $value;
    }

    return $normalized;
}
