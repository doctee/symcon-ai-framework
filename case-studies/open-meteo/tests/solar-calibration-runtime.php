<?php

declare(strict_types=1);

/** @var array<string, mixed> */
$calibrationRuntimeState = [];

final class CalibrationRuntimeLog
{
    /** @var array<int, array{sender: string, message: string}> */
    private static array $messages = [];

    public static function append(string $sender, string $message): void
    {
        self::$messages[] = ['sender' => $sender, 'message' => $message];
    }

    public static function count(): int
    {
        return count(self::$messages);
    }
}

function IPS_GetKernelDir(): string
{
    global $calibrationRuntimeState;

    return $calibrationRuntimeState['kernelDirectory'];
}

/** @return array<int, int> */
function IPS_GetInstanceListByModuleID(string $moduleId): array
{
    return [900];
}

/** @return array<string, mixed> */
function IPS_GetInstance(int $instanceId): array
{
    return [
        'ModuleInfo' => ['ModuleID' => '{C86E5442-13CF-4145-B23C-EF2B7635D79E}'],
        'InstanceStatus' => 102,
    ];
}

function IPS_VariableExists(int $variableId): bool
{
    return $variableId > 0;
}

function AC_GetLoggingStatus(int $archiveId, int $variableId): bool
{
    return true;
}

function IPS_SemaphoreEnter(string $name, int $timeout): bool
{
    return true;
}

function IPS_SemaphoreLeave(string $name): bool
{
    return true;
}

function IPS_LogMessage(string $sender, string $message): void
{
    CalibrationRuntimeLog::append($sender, $message);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentId): int|false
{
    global $calibrationRuntimeState;

    return $calibrationRuntimeState['identIds'][$ident] ?? false;
}

function GetValue(int $variableId): mixed
{
    global $calibrationRuntimeState;

    return $calibrationRuntimeState['values'][$variableId] ?? null;
}

function AC_GetLoggedValues(int $archiveId, int $variableId, int $start, int $end, int $limit): mixed
{
    return [];
}

function OMSOLAR_GetPowerForecastJson(int $instanceId, int $from, int $to, string $scope): string
{
    global $calibrationRuntimeState;

    return json_encode(
        ['success' => true, 'data' => ['system' => $calibrationRuntimeState['powerForecast']]],
        JSON_THROW_ON_ERROR
    );
}

function OMSOLAR_GetDailyEnergyForecastJson(int $instanceId, int $from, int $to, string $scope): string
{
    global $calibrationRuntimeState;

    return json_encode(
        ['success' => true, 'data' => ['system' => $calibrationRuntimeState['dailyForecast']]],
        JSON_THROW_ON_ERROR
    );
}

require_once __DIR__ . '/../candidate/SolarCalibrationCollectorRuntime.php';

function calibrationRuntimeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $snapshot */
function writeCalibrationRuntimeFixture(string $path, array $snapshot): void
{
    $content = SolarCalibrationCore::encode($snapshot);
    file_put_contents($path, $content);
    file_put_contents($path . '.sha256', hash('sha256', $content) . "\n");
}

/** @return array<string, mixed> */
function calibrationRuntimeSnapshot(string $targetKey, int $issuedAt, string $configurationHash): array
{
    return SolarCalibrationCore::buildSnapshot(
        $targetKey,
        $issuedAt,
        $configurationHash,
        [[
            'sourceTimestamp' => $issuedAt + 3660,
            'validFrom' => $issuedAt + 60,
            'validTo' => $issuedAt + 3660,
            'value' => 0.5,
            'unit' => 'kW',
            'semantics' => 'preceding_interval',
        ]],
        [[
            'sourceTimestamp' => $issuedAt + 7200,
            'validFrom' => $issuedAt + 60,
            'validTo' => $issuedAt + 7200,
            'value' => 1.0,
            'unit' => 'kWh',
            'semantics' => 'local_day',
        ]]
    );
}

/** @return array<string, mixed> */
function calibrationRuntimeConfiguration(string $directory, string $targetKey): array
{
    return [
        'snapshotDirectory' => $directory,
        'targets' => [[
            'key' => $targetKey,
            'solarInstanceId' => 101,
            'measurementVariableId' => 102,
            'dailyEnergyVariableId' => 103,
            'maxNonZeroCarrySeconds' => 300,
            'curtailmentPolicy' => ['mode' => 'none'],
        ]],
    ];
}

$temporaryDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'saef-calibration-runtime-'
    . bin2hex(random_bytes(6));
if (!mkdir($temporaryDirectory, 0700, true)) {
    throw new RuntimeException('Runtime test directory could not be created.');
}

try {
    $configurationHash = str_repeat('a', 64);
    $firstDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'snapshots';
    $targetDirectory = $firstDirectory . DIRECTORY_SEPARATOR . 'solar_test';
    mkdir($targetDirectory, 0700, true);
    $firstIssuedAt = time() - 10 * 86400;
    for ($offset = 0; $offset < 5; $offset++) {
        $issuedAt = $firstIssuedAt + $offset * 3600;
        $snapshot = calibrationRuntimeSnapshot('solar_test', $issuedAt, $configurationHash);
        writeCalibrationRuntimeFixture(
            $targetDirectory . DIRECTORY_SEPARATOR . sprintf('forecast-%d-%s.json', $issuedAt, $configurationHash),
            $snapshot
        );
    }
    $lastIssuedAt = $firstIssuedAt + 4 * 3600;
    $calibrationRuntimeState = [
        'kernelDirectory' => $temporaryDirectory . DIRECTORY_SEPARATOR,
        'identIds' => [
            'LastSuccess' => 201,
            'ForecastValidFrom' => 202,
            'ForecastValidTo' => 203,
            'ConfigurationHash' => 204,
        ],
        'values' => [
            201 => $lastIssuedAt,
            202 => $lastIssuedAt + 60,
            203 => $lastIssuedAt + 3660,
            204 => $configurationHash,
        ],
        'powerForecast' => [],
        'dailyForecast' => [],
    ];

    $runtime = new SolarCalibrationCollectorRuntime(
        calibrationRuntimeConfiguration($firstDirectory, 'solar_test')
    );
    $annotateDaily = new ReflectionMethod(SolarCalibrationCollectorRuntime::class, 'annotateDailyClassifications');
    $dayFrom = 1704067200;
    $partialDaily = $annotateDaily->invoke($runtime, [[
        'validFrom' => $dayFrom,
        'validTo' => $dayFrom + 86400,
        'forecastKwh' => 4.0,
        'measuredKwh' => 3.5,
    ]], [[
        'validFrom' => $dayFrom,
        'validTo' => $dayFrom + 3600,
        'durationSeconds' => 3600,
        'coverage' => 1.0,
        'classification' => 'unconstrained',
    ]], 0.9);
    calibrationRuntimeCheck(
        $partialDaily[0]['calibrationEligible'] === false
            && abs((float)$partialDaily[0]['classificationCoverage'] - (1 / 24)) < 0.000001,
        'Partial daily classification coverage was incorrectly eligible.'
    );
    $completeDaily = $annotateDaily->invoke($runtime, [[
        'validFrom' => $dayFrom,
        'validTo' => $dayFrom + 86400,
        'forecastKwh' => 4.0,
        'measuredKwh' => 3.5,
    ]], [[
        'validFrom' => $dayFrom,
        'validTo' => $dayFrom + 86400,
        'durationSeconds' => 86400,
        'coverage' => 0.95,
        'classification' => 'unconstrained',
    ]], 0.9);
    calibrationRuntimeCheck(
        $completeDaily[0]['calibrationEligible'] === true
            && abs((float)$completeDaily[0]['classificationCoverage'] - 0.95) < 0.000001,
        'Complete daily classification coverage was not eligible.'
    );
    $first = $runtime->run();
    calibrationRuntimeCheck($first['success'] === true, 'First runtime batch failed.');
    calibrationRuntimeCheck(
        ($first['analyses']['solar_test']['createdCount'] ?? null) === 4,
        'Runtime did not enforce the analysis batch bound.'
    );
    calibrationRuntimeCheck(
        ($first['analyses']['solar_test']['terminalDataGapCount'] ?? null) === 4,
        'Old empty measurement horizons were not terminalized.'
    );
    $analysisPaths = glob($targetDirectory . DIRECTORY_SEPARATOR . '*.analysis-v2-*.json') ?: [];
    calibrationRuntimeCheck(count($analysisPaths) === 4, 'First runtime batch file count differs.');
    $analysis = json_decode((string)file_get_contents($analysisPaths[0]), true, 64, JSON_THROW_ON_ERROR);
    calibrationRuntimeCheck(
        ($analysis['analysisOutcome'] ?? null) === 'terminal_data_gap'
            && ($analysis['powerSamples'] ?? null) === []
            && array_key_exists('calibrationPowerMetrics', $analysis)
            && $analysis['calibrationPowerMetrics'] === null,
        'Terminal data-gap analysis contract differs.'
    );

    $second = $runtime->run();
    calibrationRuntimeCheck(
        ($second['analyses']['solar_test']['createdCount'] ?? null) === 1,
        'Second runtime batch did not drain the remainder.'
    );
    $third = $runtime->run();
    calibrationRuntimeCheck(
        ($third['analyses']['solar_test']['outcome'] ?? null) === 'nothing_pending',
        'Drained runtime backlog was not stable.'
    );

    $capDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'cap';
    $capTargetDirectory = $capDirectory . DIRECTORY_SEPARATOR . 'solar_cap';
    mkdir($capTargetDirectory, 0700, true);
    for ($offset = 0; $offset < 1000; $offset++) {
        $snapshotIssuedAt = $firstIssuedAt + $offset;
        $path = $capTargetDirectory
            . DIRECTORY_SEPARATOR
            . sprintf('forecast-%d-%s.json', $snapshotIssuedAt, $configurationHash);
        writeCalibrationRuntimeFixture(
            $path,
            calibrationRuntimeSnapshot('solar_cap', $snapshotIssuedAt, $configurationHash)
        );
    }
    $calibrationRuntimeState['values'][201] = time();
    $calibrationRuntimeState['values'][202] = time() + 60;
    $calibrationRuntimeState['values'][203] = time() + 3660;
    $capRuntime = new SolarCalibrationCollectorRuntime(
        calibrationRuntimeConfiguration($capDirectory, 'solar_cap')
    );
    $capResult = $capRuntime->run();
    calibrationRuntimeCheck(
        ($capResult['captures']['solar_cap']['outcome'] ?? null) === 'retention_limit_reached',
        'Snapshot ceiling was not reported as a controlled outcome.'
    );
    calibrationRuntimeCheck(
        ($capResult['captures']['solar_cap']['notification'] ?? null) === 'created'
            && CalibrationRuntimeLog::count() === 1,
        'Snapshot ceiling did not create one bounded notification.'
    );
    $markerPath = $capTargetDirectory . DIRECTORY_SEPARATOR . 'collection-limit-reached.json';
    calibrationRuntimeCheck(
        is_file($markerPath) && is_file($markerPath . '.sha256'),
        'Snapshot ceiling evidence marker is incomplete.'
    );
    $repeatedCapResult = $capRuntime->run();
    calibrationRuntimeCheck(
        ($repeatedCapResult['captures']['solar_cap']['notification'] ?? null) === 'unchanged'
            && CalibrationRuntimeLog::count() === 1,
        'Snapshot ceiling notification was repeated.'
    );
    calibrationRuntimeCheck($capResult['success'] === true, 'Snapshot ceiling failed the collector run.');

    $identityDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'identity';
    $identityTargetDirectory = $identityDirectory . DIRECTORY_SEPARATOR . 'solar_identity';
    mkdir($identityTargetDirectory, 0700, true);
    $identityIssuedAt = $firstIssuedAt;
    $identityPath = $identityTargetDirectory
        . DIRECTORY_SEPARATOR
        . sprintf('forecast-%d-%s.json', $identityIssuedAt, $configurationHash);
    writeCalibrationRuntimeFixture(
        $identityPath,
        calibrationRuntimeSnapshot('solar_identity', $identityIssuedAt + 1, $configurationHash)
    );
    $calibrationRuntimeState['values'][201] = $identityIssuedAt;
    $calibrationRuntimeState['values'][202] = $identityIssuedAt + 60;
    $calibrationRuntimeState['values'][203] = $identityIssuedAt + 3660;
    $identityRejected = false;
    try {
        (new SolarCalibrationCollectorRuntime(
            calibrationRuntimeConfiguration($identityDirectory, 'solar_identity')
        ))->run();
    } catch (RuntimeException) {
        $identityRejected = true;
    }
    calibrationRuntimeCheck($identityRejected, 'Snapshot path and content identity mismatch was accepted.');
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $path) {
        if ($path->isDir()) {
            rmdir($path->getPathname());
        } else {
            unlink($path->getPathname());
        }
    }
    rmdir($temporaryDirectory);
}

echo "solar-calibration-runtime: ok\n";
