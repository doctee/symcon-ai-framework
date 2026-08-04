<?php

declare(strict_types=1);

if (!class_exists('SolarCalibrationCore')) {
    require_once __DIR__ . '/SolarCalibrationCore.php';
}

final class SolarCalibrationCollectorRuntime
{
    private const SOLAR_MODULE_GUID = '{C86E5442-13CF-4145-B23C-EF2B7635D79E}';
    private const ARCHIVE_MODULE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const MAX_SNAPSHOTS_PER_TARGET = 1000;
    private const MAX_ARCHIVE_PAGES = 8;
    private const ARCHIVE_PAGE_SIZE = 10000;

    /** @param array<string, mixed> $configuration */
    public function __construct(private array $configuration)
    {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $configuration = $this->validatedConfiguration();
        $lockName = 'SAEF.OpenMeteoCalibration.' . hash('sha256', $configuration['snapshotDirectory']);
        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            return ['success' => false, 'code' => 'busy'];
        }

        try {
            $this->ensureDirectory($configuration['snapshotDirectory']);
            $captures = [];
            $analyses = [];
            foreach ($configuration['targets'] as $target) {
                $captures[$target['key']] = $this->capture($configuration, $target);
                $analyses[$target['key']] = $this->analyzeOne($configuration, $target);
            }

            return [
                'success' => true,
                'code' => 'ok',
                'captures' => $captures,
                'analyses' => $analyses,
            ];
        } finally {
            if (!IPS_SemaphoreLeave($lockName)) {
                IPS_LogMessage('OpenMeteoCalibration', 'Collector lock could not be released.');
            }
        }
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    private function capture(array $configuration, array $target): array
    {
        $instanceId = $target['solarInstanceId'];
        $lastSuccess = $this->integerVariable($instanceId, 'LastSuccess');
        if ($lastSuccess <= 0) {
            return ['outcome' => 'waiting_for_forecast'];
        }
        $validFrom = $this->integerVariable($instanceId, 'ForecastValidFrom');
        $validTo = $this->integerVariable($instanceId, 'ForecastValidTo');
        $configurationHash = $this->stringVariable($instanceId, 'ConfigurationHash');
        if ($validFrom <= 0 || $validTo <= $validFrom || preg_match('/^[a-f0-9]{64}$/', $configurationHash) !== 1) {
            throw new RuntimeException('Solar forecast metadata is invalid.');
        }

        $targetDirectory = $configuration['snapshotDirectory'] . DIRECTORY_SEPARATOR . $target['key'];
        $this->ensureDirectory($targetDirectory);
        $snapshotCount = count(glob($targetDirectory . DIRECTORY_SEPARATOR . 'forecast-*.json') ?: []);
        if ($snapshotCount >= self::MAX_SNAPSHOTS_PER_TARGET) {
            throw new RuntimeException('Snapshot retention limit reached.');
        }

        $baseName = sprintf('forecast-%d-%s.json', $lastSuccess, $configurationHash);
        $snapshotPath = $targetDirectory . DIRECTORY_SEPARATOR . $baseName;
        if ($this->verifiedImmutableFileExists($snapshotPath)) {
            return ['outcome' => 'unchanged', 'issuedAt' => $lastSuccess];
        }

        $powerResult = $this->decodeModuleResult(OMSOLAR_GetPowerForecastJson(
            $instanceId,
            $validFrom,
            $validTo,
            'system'
        ));
        $dailyResult = $this->decodeModuleResult(OMSOLAR_GetDailyEnergyForecastJson(
            $instanceId,
            $validFrom,
            $validTo,
            'system'
        ));
        $snapshot = SolarCalibrationCore::buildSnapshot(
            $target['key'],
            $lastSuccess,
            $configurationHash,
            $powerResult['data']['system'] ?? [],
            $dailyResult['data']['system'] ?? []
        );
        $snapshot['capturedAt'] = time();
        $snapshot['solarInstanceId'] = $instanceId;
        $snapshot['measurementVariableId'] = $target['measurementVariableId'];
        $snapshot['dailyEnergyVariableId'] = $target['dailyEnergyVariableId'];

        $this->writeImmutable($snapshotPath, SolarCalibrationCore::encode($snapshot));

        return [
            'outcome' => 'created',
            'issuedAt' => $lastSuccess,
            'powerPointCount' => count($snapshot['power']),
            'dailyPointCount' => count($snapshot['dailyEnergy']),
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    private function analyzeOne(array $configuration, array $target): array
    {
        $targetDirectory = $configuration['snapshotDirectory'] . DIRECTORY_SEPARATOR . $target['key'];
        $snapshotPaths = glob($targetDirectory . DIRECTORY_SEPARATOR . 'forecast-*.json') ?: [];
        sort($snapshotPaths, SORT_STRING);
        foreach ($snapshotPaths as $snapshotPath) {
            if (str_ends_with($snapshotPath, '.analysis.json')) {
                continue;
            }
            $analysisPath = substr($snapshotPath, 0, -5) . '.analysis.json';
            if ($this->verifiedImmutableFileExists($analysisPath)) {
                continue;
            }
            if (!$this->verifiedImmutableFileExists($snapshotPath)) {
                throw new RuntimeException('Incomplete forecast snapshot found.');
            }
            $snapshot = json_decode((string)file_get_contents($snapshotPath), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($snapshot) || ($snapshot['targetKey'] ?? null) !== $target['key']) {
                throw new RuntimeException('Forecast snapshot identity mismatch.');
            }
            if (($snapshot['forecastValidTo'] ?? PHP_INT_MAX) > time()) {
                return ['outcome' => 'waiting_for_complete_horizon'];
            }

            $events = $this->readMeasurementEvents(
                $configuration['archiveId'],
                $target['measurementVariableId'],
                (int)$snapshot['forecastValidFrom'],
                (int)$snapshot['forecastValidTo']
            );
            $samples = SolarCalibrationCore::alignPowerMeasurements(
                $snapshot['power'] ?? [],
                $events,
                $target['maxNonZeroCarrySeconds']
            );
            if ($samples === []) {
                return ['outcome' => 'waiting_for_measurements'];
            }
            $metrics = SolarCalibrationCore::calculatePowerMetrics($samples);
            $daily = $this->dailyEnergyComparison(
                $configuration['archiveId'],
                $target['dailyEnergyVariableId'],
                $snapshot['dailyEnergy'] ?? [],
                (int)$snapshot['issuedAt']
            );
            $analysis = [
                'schemaVersion' => 1,
                'targetKey' => $target['key'],
                'issuedAt' => $snapshot['issuedAt'],
                'configurationHash' => $snapshot['configurationHash'],
                'snapshotSha256' => hash_file('sha256', $snapshotPath),
                'analyzedAt' => time(),
                'powerMetrics' => $metrics,
                'powerSamples' => $samples,
                'dailyEnergy' => $daily,
            ];
            $this->writeImmutable($analysisPath, SolarCalibrationCore::encode($analysis));

            return [
                'outcome' => 'created',
                'issuedAt' => $snapshot['issuedAt'],
                'sampleCount' => count($samples),
                'coverage' => $metrics['coverage'],
            ];
        }

        return ['outcome' => 'nothing_pending'];
    }

    /** @return array<string, mixed> */
    private function validatedConfiguration(): array
    {
        $snapshotDirectory = $this->configuration['snapshotDirectory'] ?? null;
        if (!is_string($snapshotDirectory) || $snapshotDirectory === '' || !str_starts_with($snapshotDirectory, IPS_GetKernelDir())) {
            throw new InvalidArgumentException('Snapshot directory must be below the IP-Symcon kernel directory.');
        }
        $archiveIds = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE_GUID);
        if (count($archiveIds) !== 1) {
            throw new RuntimeException('Expected exactly one Archive Control instance.');
        }
        $targets = $this->configuration['targets'] ?? null;
        if (!is_array($targets) || $targets === [] || count($targets) > 8) {
            throw new InvalidArgumentException('Calibration targets are invalid.');
        }

        $validatedTargets = [];
        $seenKeys = [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                throw new InvalidArgumentException('Calibration target must be an array.');
            }
            $key = $target['key'] ?? null;
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1 || isset($seenKeys[$key])) {
                throw new InvalidArgumentException('Calibration target key is invalid or duplicated.');
            }
            $seenKeys[$key] = true;
            $solarInstanceId = $this->positiveId($target['solarInstanceId'] ?? null, 'solar instance');
            $measurementVariableId = $this->positiveId($target['measurementVariableId'] ?? null, 'measurement variable');
            $dailyEnergyVariableId = $this->positiveId($target['dailyEnergyVariableId'] ?? null, 'daily energy variable');
            $carry = $target['maxNonZeroCarrySeconds'] ?? null;
            if (!is_int($carry) || $carry <= 0 || $carry > 3600) {
                throw new InvalidArgumentException('Measurement carry bound is invalid.');
            }
            $instance = IPS_GetInstance($solarInstanceId);
            if (($instance['ModuleInfo']['ModuleID'] ?? null) !== self::SOLAR_MODULE_GUID || $instance['InstanceStatus'] !== 102) {
                throw new RuntimeException('Solar calibration target is not active or has the wrong module type.');
            }
            foreach ([$measurementVariableId, $dailyEnergyVariableId] as $variableId) {
                if (!IPS_VariableExists($variableId) || !AC_GetLoggingStatus($archiveIds[0], $variableId)) {
                    throw new RuntimeException('Calibration measurement variable is missing or not logged.');
                }
            }
            $validatedTargets[] = [
                'key' => $key,
                'solarInstanceId' => $solarInstanceId,
                'measurementVariableId' => $measurementVariableId,
                'dailyEnergyVariableId' => $dailyEnergyVariableId,
                'maxNonZeroCarrySeconds' => $carry,
            ];
        }

        return [
            'snapshotDirectory' => rtrim($snapshotDirectory, DIRECTORY_SEPARATOR),
            'archiveId' => $archiveIds[0],
            'targets' => $validatedTargets,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeModuleResult(string $json): array
    {
        if (strlen($json) > 1024 * 1024) {
            throw new RuntimeException('Forecast cache result is unbounded.');
        }
        $result = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($result) || ($result['success'] ?? null) !== true || !is_array($result['data']['system'] ?? null)) {
            throw new RuntimeException('Forecast cache result is invalid.');
        }

        return $result;
    }

    /** @return array<int, array{timestamp: int, valueW: float}> */
    private function readMeasurementEvents(int $archiveId, int $variableId, int $from, int $to): array
    {
        $values = [];
        $preceding = AC_GetLoggedValues($archiveId, $variableId, 0, $from, 1);
        if (!is_array($preceding)) {
            throw new RuntimeException('Preceding archive read failed.');
        }
        foreach ($preceding as $value) {
            $values[(int)$value['TimeStamp']] = (float)$value['Value'];
        }

        $pageTo = $to;
        for ($page = 0; $page < self::MAX_ARCHIVE_PAGES; $page++) {
            $chunk = AC_GetLoggedValues($archiveId, $variableId, $from, $pageTo, self::ARCHIVE_PAGE_SIZE);
            if (!is_array($chunk)) {
                throw new RuntimeException('Archive page read failed.');
            }
            foreach ($chunk as $value) {
                $values[(int)$value['TimeStamp']] = (float)$value['Value'];
            }
            if (count($chunk) < self::ARCHIVE_PAGE_SIZE) {
                break;
            }
            $oldest = min(array_column($chunk, 'TimeStamp'));
            if ($oldest <= $from) {
                break;
            }
            $pageTo = $oldest - 1;
            if ($page === self::MAX_ARCHIVE_PAGES - 1) {
                throw new RuntimeException('Archive read page bound reached.');
            }
        }
        ksort($values, SORT_NUMERIC);

        $events = [];
        foreach ($values as $timestamp => $valueW) {
            $events[] = ['timestamp' => $timestamp, 'valueW' => $valueW];
        }

        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $dailyPoints
     * @return array<int, array<string, int|float|null>>
     */
    private function dailyEnergyComparison(int $archiveId, int $variableId, array $dailyPoints, int $issuedAt): array
    {
        $comparisons = [];
        foreach ($dailyPoints as $point) {
            $from = $point['validFrom'] ?? null;
            $to = $point['validTo'] ?? null;
            $forecast = $point['value'] ?? null;
            if (!is_int($from) || !is_int($to) || $to <= $from || $to <= $issuedAt || !is_numeric($forecast)) {
                continue;
            }
            $values = AC_GetLoggedValues($archiveId, $variableId, $from, $to - 1, self::ARCHIVE_PAGE_SIZE);
            if (!is_array($values)) {
                throw new RuntimeException('Daily energy archive read failed.');
            }
            $measured = null;
            foreach ($values as $value) {
                $number = (float)$value['Value'];
                $measured = $measured === null ? $number : max($measured, $number);
            }
            $forecastValue = (float)$forecast;
            $comparisons[] = [
                'validFrom' => $from,
                'validTo' => $to,
                'forecastKwh' => $forecastValue,
                'measuredKwh' => $measured,
                'errorKwh' => $measured === null ? null : $measured - $forecastValue,
                'ratio' => $measured === null || $forecastValue <= 0.0 ? null : $measured / $forecastValue,
            ];
        }

        return $comparisons;
    }

    private function integerVariable(int $instanceId, string $ident): int
    {
        $variableId = @IPS_GetObjectIDByIdent($ident, $instanceId);
        if ($variableId === false || !IPS_VariableExists($variableId)) {
            throw new RuntimeException('Required solar variable is missing.');
        }

        return (int)GetValue($variableId);
    }

    private function stringVariable(int $instanceId, string $ident): string
    {
        $variableId = @IPS_GetObjectIDByIdent($ident, $instanceId);
        if ($variableId === false || !IPS_VariableExists($variableId)) {
            throw new RuntimeException('Required solar variable is missing.');
        }

        return (string)GetValue($variableId);
    }

    private function positiveId(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException('Invalid ' . $label . ' ID.');
        }

        return $value;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Calibration snapshot directory could not be created.');
        }
    }

    /** @phpstan-impure */
    private function verifiedImmutableFileExists(string $path): bool
    {
        $hashPath = $path . '.sha256';
        if (!is_file($path) && !is_file($hashPath)) {
            return false;
        }
        if (!is_file($path) || !is_file($hashPath)) {
            throw new RuntimeException('Incomplete immutable calibration file found.');
        }
        $expected = trim((string)file_get_contents($hashPath));
        $actual = hash_file('sha256', $path);
        if ($actual === false || !hash_equals($expected, $actual)) {
            throw new RuntimeException('Immutable calibration file hash mismatch.');
        }

        return true;
    }

    private function writeImmutable(string $path, string $content): void
    {
        if ($this->verifiedImmutableFileExists($path)) {
            return;
        }
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        $hashTemporary = $path . '.sha256.tmp-' . bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) {
                throw new RuntimeException('Calibration snapshot write was incomplete.');
            }
            @chmod($temporary, 0600);
            if (is_file($path) || !rename($temporary, $path)) {
                throw new RuntimeException('Calibration snapshot activation failed.');
            }
            $hash = hash_file('sha256', $path);
            if ($hash === false || file_put_contents($hashTemporary, $hash . "\n", LOCK_EX) !== 65) {
                throw new RuntimeException('Calibration snapshot hash write failed.');
            }
            @chmod($hashTemporary, 0600);
            if (is_file($path . '.sha256') || !rename($hashTemporary, $path . '.sha256')) {
                throw new RuntimeException('Calibration snapshot hash activation failed.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
            if (is_file($hashTemporary)) {
                @unlink($hashTemporary);
            }
        }
        if (!$this->verifiedImmutableFileExists($path)) {
            throw new RuntimeException('Calibration snapshot verification failed.');
        }
    }
}
