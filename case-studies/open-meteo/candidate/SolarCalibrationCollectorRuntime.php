<?php

declare(strict_types=1);

if (!class_exists('SolarCalibrationCore')) {
    require_once __DIR__ . '/SolarCalibrationCore.php';
}

final class SolarCalibrationCollectorRuntime
{
    private const ANALYSIS_VERSION = '2.1.0';
    private const SOLAR_MODULE_GUID = '{C86E5442-13CF-4145-B23C-EF2B7635D79E}';
    private const ARCHIVE_MODULE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const MAX_SNAPSHOTS_PER_TARGET = 1000;
    private const MAX_ANALYSES_PER_TARGET_PER_RUN = 4;
    private const MEASUREMENT_GRACE_SECONDS = 6 * 3600;
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
                $analyses[$target['key']] = $this->analyzeBatch($configuration, $target);
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
        $snapshotCount = count($this->baseSnapshotPaths($targetDirectory));
        $limitMarkerPath = $targetDirectory . DIRECTORY_SEPARATOR . 'collection-limit-reached.json';
        if ($this->verifiedImmutableFileExists($limitMarkerPath)) {
            return $this->retentionLimitOutcome(
                $targetDirectory,
                $target['key'],
                $lastSuccess,
                $snapshotCount
            );
        }
        $baseName = sprintf('forecast-%d-%s.json', $lastSuccess, $configurationHash);
        $snapshotPath = $targetDirectory . DIRECTORY_SEPARATOR . $baseName;
        if ($this->verifiedImmutableFileExists($snapshotPath)) {
            return ['outcome' => 'unchanged', 'issuedAt' => $lastSuccess];
        }
        if ($snapshotCount >= self::MAX_SNAPSHOTS_PER_TARGET) {
            return $this->retentionLimitOutcome(
                $targetDirectory,
                $target['key'],
                $lastSuccess,
                $snapshotCount
            );
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
    private function analyzeBatch(array $configuration, array $target): array
    {
        $targetDirectory = $configuration['snapshotDirectory'] . DIRECTORY_SEPARATOR . $target['key'];
        $snapshotPaths = $this->baseSnapshotPaths($targetDirectory);
        $analysisPolicyHash = $this->analysisPolicyHash($target['curtailmentPolicy']);
        $createdCount = 0;
        $terminalDataGapCount = 0;
        $waitingForMeasurementsCount = 0;
        $waitingForCompleteHorizonCount = 0;
        $lastCreated = null;
        foreach ($snapshotPaths as $snapshotPath) {
            $analysisPath = substr($snapshotPath, 0, -5)
                . '.analysis-v2-' . $analysisPolicyHash . '.json';
            if ($this->verifiedImmutableFileExists($analysisPath)) {
                continue;
            }
            if ($createdCount >= self::MAX_ANALYSES_PER_TARGET_PER_RUN) {
                break;
            }
            if (!$this->verifiedImmutableFileExists($snapshotPath)) {
                throw new RuntimeException('Incomplete forecast snapshot found.');
            }
            $snapshot = json_decode((string)file_get_contents($snapshotPath), true, 64, JSON_THROW_ON_ERROR);
            $pathIdentity = $this->snapshotPathIdentity($snapshotPath);
            if (
                !is_array($snapshot)
                || ($snapshot['schemaVersion'] ?? null) !== 1
                || ($snapshot['targetKey'] ?? null) !== $target['key']
                || ($snapshot['issuedAt'] ?? null) !== $pathIdentity['issuedAt']
                || ($snapshot['configurationHash'] ?? null) !== $pathIdentity['configurationHash']
            ) {
                throw new RuntimeException('Forecast snapshot identity mismatch.');
            }
            if (($snapshot['forecastValidTo'] ?? PHP_INT_MAX) > time()) {
                $waitingForCompleteHorizonCount++;
                continue;
            }

            $issuedAt = $pathIdentity['issuedAt'];
            $forecastPoints = array_values(array_filter(
                is_array($snapshot['power'] ?? null) ? $snapshot['power'] : [],
                static fn(mixed $point): bool => is_array($point)
                    && is_int($point['validFrom'] ?? null)
                    && $point['validFrom'] >= $issuedAt
            ));
            if ($forecastPoints === []) {
                throw new RuntimeException('Forecast snapshot has no post-issue power intervals.');
            }
            $analysisFrom = $forecastPoints[0]['validFrom'];
            $lastPoint = $forecastPoints[count($forecastPoints) - 1];
            $analysisTo = $lastPoint['validTo'] ?? null;
            if (!is_int($analysisTo) || $analysisTo <= $analysisFrom) {
                throw new RuntimeException('Forecast analysis range is invalid.');
            }

            $events = $this->readMeasurementEvents(
                $configuration['archiveId'],
                $target['measurementVariableId'],
                $analysisFrom,
                $analysisTo
            );
            $samples = SolarCalibrationCore::alignPowerMeasurements(
                $forecastPoints,
                $events,
                $target['maxNonZeroCarrySeconds']
            );
            if ($samples === []) {
                if (time() < $analysisTo + self::MEASUREMENT_GRACE_SECONDS) {
                    $waitingForMeasurementsCount++;
                    continue;
                }
                $analysis = $this->terminalDataGapAnalysis(
                    $configuration,
                    $target,
                    $snapshot,
                    $snapshotPath,
                    $analysisPolicyHash,
                    $issuedAt,
                    $analysisFrom,
                    $analysisTo
                );
                $this->writeImmutable($analysisPath, SolarCalibrationCore::encode($analysis));
                $createdCount++;
                $terminalDataGapCount++;
                $lastCreated = [
                    'issuedAt' => $issuedAt,
                    'analysisOutcome' => 'terminal_data_gap',
                    'sampleCount' => 0,
                ];
                continue;
            }

            $signalEvents = [];
            if ($target['curtailmentPolicy']['mode'] === 'zero_export_storage') {
                $signalEvents['solarPowerW'] = array_map(
                    static fn(array $event): array => [
                        'timestamp' => $event['timestamp'],
                        'value' => $event['valueW'],
                    ],
                    $events
                );
                foreach ($target['curtailmentPolicy']['signalVariableIds'] as $signal => $variableId) {
                    $signalEvents[$signal] = $this->readArchiveEvents(
                        $configuration['archiveId'],
                        $variableId,
                        $analysisFrom,
                        $analysisTo
                    );
                }
            }
            $classifiedSamples = SolarCalibrationCore::classifyPowerSamples(
                $samples,
                $signalEvents,
                $target['curtailmentPolicy']
            );
            $classificationSummary = SolarCalibrationCore::summarizeClassifications($classifiedSamples);
            $realizedMetrics = SolarCalibrationCore::calculatePowerMetrics($classifiedSamples);
            $daily = $this->dailyEnergyComparison(
                $configuration['archiveId'],
                $target['dailyEnergyVariableId'],
                $snapshot['dailyEnergy'] ?? [],
                $issuedAt
            );
            $daily = $this->annotateDailyClassifications(
                $daily,
                $classifiedSamples,
                $target['curtailmentPolicy']['minimumDailyClassificationCoverage']
            );
            $analysis = [
                'schemaVersion' => 2,
                'analysisVersion' => self::ANALYSIS_VERSION,
                'analysisPolicyHash' => $analysisPolicyHash,
                'analysisOutcome' => 'complete',
                'targetKey' => $target['key'],
                'issuedAt' => $snapshot['issuedAt'],
                'configurationHash' => $snapshot['configurationHash'],
                'snapshotSha256' => hash_file('sha256', $snapshotPath),
                'analyzedAt' => time(),
                'analysisValidFrom' => $analysisFrom,
                'analysisValidTo' => $analysisTo,
                'realizedPowerMetrics' => $realizedMetrics,
                'calibrationPowerMetrics' => $classificationSummary['calibrationMetrics'],
                'classificationSummary' => [
                    'counts' => $classificationSummary['counts'],
                    'durationSeconds' => $classificationSummary['durationSeconds'],
                    'calibrationEligibleCount' => $classificationSummary['calibrationEligibleCount'],
                ],
                'powerSamples' => $classifiedSamples,
                'dailyEnergy' => $daily,
            ];
            $this->writeImmutable($analysisPath, SolarCalibrationCore::encode($analysis));
            $createdCount++;
            $lastCreated = [
                'issuedAt' => $snapshot['issuedAt'],
                'analysisOutcome' => 'complete',
                'sampleCount' => count($classifiedSamples),
                'coverage' => $realizedMetrics['coverage'],
                'classificationCounts' => $classificationSummary['counts'],
                'calibrationEligibleCount' => $classificationSummary['calibrationEligibleCount'],
            ];
        }

        if ($createdCount > 0) {
            return [
                'outcome' => 'created',
                'createdCount' => $createdCount,
                'terminalDataGapCount' => $terminalDataGapCount,
                'batchLimit' => self::MAX_ANALYSES_PER_TARGET_PER_RUN,
                'lastCreated' => $lastCreated,
            ];
        }
        if ($waitingForMeasurementsCount > 0) {
            return [
                'outcome' => 'waiting_for_measurements',
                'pendingCount' => $waitingForMeasurementsCount,
                'graceSeconds' => self::MEASUREMENT_GRACE_SECONDS,
            ];
        }
        if ($waitingForCompleteHorizonCount > 0) {
            return [
                'outcome' => 'waiting_for_complete_horizon',
                'pendingCount' => $waitingForCompleteHorizonCount,
            ];
        }

        return ['outcome' => 'nothing_pending'];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $target
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function terminalDataGapAnalysis(
        array $configuration,
        array $target,
        array $snapshot,
        string $snapshotPath,
        string $analysisPolicyHash,
        int $issuedAt,
        int $analysisFrom,
        int $analysisTo
    ): array {
        $daily = $this->dailyEnergyComparison(
            $configuration['archiveId'],
            $target['dailyEnergyVariableId'],
            is_array($snapshot['dailyEnergy'] ?? null) ? $snapshot['dailyEnergy'] : [],
            $issuedAt
        );

        return [
            'schemaVersion' => 2,
            'analysisVersion' => self::ANALYSIS_VERSION,
            'analysisPolicyHash' => $analysisPolicyHash,
            'analysisOutcome' => 'terminal_data_gap',
            'dataGapReason' => 'no_measurement_samples_after_grace',
            'targetKey' => $target['key'],
            'issuedAt' => $issuedAt,
            'configurationHash' => $snapshot['configurationHash'],
            'snapshotSha256' => hash_file('sha256', $snapshotPath),
            'analyzedAt' => time(),
            'analysisValidFrom' => $analysisFrom,
            'analysisValidTo' => $analysisTo,
            'measurementGraceSeconds' => self::MEASUREMENT_GRACE_SECONDS,
            'realizedPowerMetrics' => null,
            'calibrationPowerMetrics' => null,
            'classificationSummary' => [
                'counts' => $this->emptyClassificationCounts(),
                'durationSeconds' => $this->emptyClassificationCounts(),
                'calibrationEligibleCount' => 0,
            ],
            'powerSamples' => [],
            'dailyEnergy' => $this->annotateDailyClassifications(
                $daily,
                [],
                $target['curtailmentPolicy']['minimumDailyClassificationCoverage']
            ),
        ];
    }

    /** @return array<string, int> */
    private function emptyClassificationCounts(): array
    {
        return [
            'unconstrained' => 0,
            'curtailed' => 0,
            'uncertain' => 0,
            'data_gap' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function retentionLimitOutcome(
        string $targetDirectory,
        string $targetKey,
        int $rejectedIssuedAt,
        int $snapshotCount
    ): array {
        $markerPath = $targetDirectory . DIRECTORY_SEPARATOR . 'collection-limit-reached.json';
        $alreadyReported = $this->verifiedImmutableFileExists($markerPath);
        if (!$alreadyReported) {
            $marker = [
                'schemaVersion' => 1,
                'targetKey' => $targetKey,
                'snapshotCount' => $snapshotCount,
                'snapshotLimit' => self::MAX_SNAPSHOTS_PER_TARGET,
                'firstRejectedIssuedAt' => $rejectedIssuedAt,
                'recordedAt' => time(),
                'outcome' => 'collection_paused',
                'requiredAction' => 'separate_retention_or_test_end_decision',
            ];
            $this->writeImmutable($markerPath, SolarCalibrationCore::encode($marker));
            IPS_LogMessage(
                'OpenMeteoCalibration',
                'Snapshot collection paused at the configured limit for target ' . $targetKey . '.'
            );
        }

        return [
            'outcome' => 'retention_limit_reached',
            'snapshotCount' => $snapshotCount,
            'limit' => self::MAX_SNAPSHOTS_PER_TARGET,
            'notification' => $alreadyReported ? 'unchanged' : 'created',
        ];
    }

    /** @return array{issuedAt: int, configurationHash: string} */
    private function snapshotPathIdentity(string $snapshotPath): array
    {
        $matches = [];
        if (
            preg_match(
                '/^forecast-([0-9]+)-([a-f0-9]{64})\.json$/',
                basename($snapshotPath),
                $matches
            ) !== 1
        ) {
            throw new RuntimeException('Forecast snapshot path is invalid.');
        }
        $issuedAt = filter_var($matches[1], FILTER_VALIDATE_INT);
        if (!is_int($issuedAt) || $issuedAt <= 0) {
            throw new RuntimeException('Forecast snapshot path issue time is invalid.');
        }

        return [
            'issuedAt' => $issuedAt,
            'configurationHash' => $matches[2],
        ];
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
            $curtailmentPolicy = $this->validatedCurtailmentPolicy(
                $target['curtailmentPolicy'] ?? ['mode' => 'none'],
                $archiveIds[0]
            );
            $validatedTargets[] = [
                'key' => $key,
                'solarInstanceId' => $solarInstanceId,
                'measurementVariableId' => $measurementVariableId,
                'dailyEnergyVariableId' => $dailyEnergyVariableId,
                'maxNonZeroCarrySeconds' => $carry,
                'curtailmentPolicy' => $curtailmentPolicy,
            ];
        }

        return [
            'snapshotDirectory' => rtrim($snapshotDirectory, DIRECTORY_SEPARATOR),
            'archiveId' => $archiveIds[0],
            'targets' => $validatedTargets,
        ];
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private function validatedCurtailmentPolicy(array $policy, int $archiveId): array
    {
        $mode = $policy['mode'] ?? null;
        if ($mode === 'none') {
            return [
                'mode' => 'none',
                'minimumDailyClassificationCoverage' => $this->normalizedDailyCoverage($policy),
            ];
        }
        if ($mode !== 'zero_export_storage') {
            throw new InvalidArgumentException('Curtailment policy mode is invalid.');
        }

        $signalVariableIds = $policy['signalVariableIds'] ?? null;
        $requiredSignals = [
            'stateOfChargePercent',
            'chargePowerW',
            'outputPowerW',
            'homeLoadW',
            'gridExportW',
            'gridImportW',
            'statusCode',
        ];
        if (!is_array($signalVariableIds) || array_keys($signalVariableIds) !== $requiredSignals) {
            throw new InvalidArgumentException('Curtailment signal mapping is invalid.');
        }
        $validatedSignals = [];
        foreach ($signalVariableIds as $signal => $variableId) {
            $validatedId = $this->positiveId($variableId, 'curtailment signal');
            if (!IPS_VariableExists($validatedId) || !AC_GetLoggingStatus($archiveId, $validatedId)) {
                throw new RuntimeException('Curtailment signal variable is missing or not logged.');
            }
            $variable = IPS_GetVariable($validatedId);
            if (!in_array($variable['VariableType'], [1, 2], true)) {
                throw new RuntimeException('Curtailment signal variable is not numeric.');
            }
            $validatedSignals[$signal] = $validatedId;
        }

        $floatThresholds = [
            'minimumForecastKw' => [0.0, 10.0],
            'maximumRealizedToForecastRatio' => [0.0, 1.0],
            'minimumMeasurementCoverage' => [0.0, 1.0],
            'minimumAuxiliaryCoverage' => [0.0, 1.0],
            'minimumHeartbeatCoverage' => [0.0, 1.0],
            'fullSocPercent' => [0.0, 100.0],
            'minimumPossibleFullSocFraction' => [0.0, 1.0],
            'minimumFullSocFraction' => [0.0, 1.0],
            'minimumBatteryChargingAverageW' => [0.0, 10 * 1000.0],
            'maximumGridExportAverageW' => [0.0, 10 * 1000.0],
            'maximumGridImportAverageW' => [0.0, 10 * 1000.0],
            'minimumDailyClassificationCoverage' => [0.0, 1.0],
        ];
        $validated = [
            'mode' => $mode,
            'signalVariableIds' => $validatedSignals,
        ];
        foreach ($floatThresholds as $key => [$minimum, $maximum]) {
            $value = $policy[$key] ?? null;
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Curtailment threshold is not numeric: ' . $key);
            }
            $number = (float)$value;
            if (!is_finite($number) || $number < $minimum || $number > $maximum) {
                throw new InvalidArgumentException('Curtailment threshold is out of range: ' . $key);
            }
            $validated[$key] = $number;
        }
        $batteryChargingSign = $policy['batteryChargingSign'] ?? null;
        if (!in_array($batteryChargingSign, ['positive', 'negative'], true)) {
            throw new InvalidArgumentException('Battery charging sign is invalid.');
        }
        $validated['batteryChargingSign'] = $batteryChargingSign;
        $gridFlowEvidenceMode = $policy['gridFlowEvidenceMode'] ?? null;
        if (!in_array($gridFlowEvidenceMode, ['exclusive_target', 'diagnostic_only'], true)) {
            throw new InvalidArgumentException('Grid-flow evidence mode is invalid.');
        }
        $validated['gridFlowEvidenceMode'] = $gridFlowEvidenceMode;
        $localTimezone = $policy['localTimezone'] ?? null;
        if (!is_string($localTimezone)) {
            throw new InvalidArgumentException('Local timezone is invalid.');
        }
        try {
            new DateTimeZone($localTimezone);
        } catch (Exception) {
            throw new InvalidArgumentException('Local timezone is invalid.');
        }
        $validated['localTimezone'] = $localTimezone;
        $windows = $policy['knownShadingWindows'] ?? null;
        if (!is_array($windows) || count($windows) > 8) {
            throw new InvalidArgumentException('Known-shading windows are invalid.');
        }
        $validatedWindows = [];
        foreach ($windows as $window) {
            $start = is_array($window) ? ($window['startMinuteOfDay'] ?? null) : null;
            $end = is_array($window) ? ($window['endMinuteOfDay'] ?? null) : null;
            if (!is_int($start) || !is_int($end) || $start < 0 || $start > 1439 || $end < 1 || $end > 1440 || $start === $end) {
                throw new InvalidArgumentException('Known-shading window is invalid.');
            }
            $validatedWindows[] = ['startMinuteOfDay' => $start, 'endMinuteOfDay' => $end];
        }
        $validated['knownShadingWindows'] = $validatedWindows;
        $possibleFullSocFraction = $validated['minimumPossibleFullSocFraction'] ?? null;
        $confirmedFullSocFraction = $validated['minimumFullSocFraction'] ?? null;
        if (!is_float($possibleFullSocFraction) || !is_float($confirmedFullSocFraction)) {
            throw new LogicException('Full-SOC thresholds were not normalized.');
        }
        if ($possibleFullSocFraction > $confirmedFullSocFraction) {
            throw new InvalidArgumentException('Possible full-SOC fraction exceeds the confirmed threshold.');
        }
        foreach (['signalCarrySeconds', 'heartbeatMaxGapSeconds'] as $key) {
            $value = $policy[$key] ?? null;
            if (!is_int($value) || $value <= 0 || $value > 3600) {
                throw new InvalidArgumentException('Curtailment duration is invalid: ' . $key);
            }
            $validated[$key] = $value;
        }

        return $validated;
    }

    /** @param array<string, mixed> $policy */
    private function normalizedDailyCoverage(array $policy): float
    {
        $value = $policy['minimumDailyClassificationCoverage'] ?? 0.9;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Daily classification coverage is not numeric.');
        }
        $number = (float)$value;
        if (!is_finite($number) || $number < 0.0 || $number > 1.0) {
            throw new InvalidArgumentException('Daily classification coverage is out of range.');
        }

        return $number;
    }

    /** @param array<string, mixed> $policy */
    private function analysisPolicyHash(array $policy): string
    {
        return hash('sha256', self::ANALYSIS_VERSION . "\n" . json_encode(
            $policy,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    /** @return array<int, string> */
    private function baseSnapshotPaths(string $targetDirectory): array
    {
        $paths = array_values(array_filter(
            glob($targetDirectory . DIRECTORY_SEPARATOR . 'forecast-*.json') ?: [],
            static fn(string $path): bool => preg_match(
                '/forecast-[0-9]+-[a-f0-9]{64}\.json$/',
                $path
            ) === 1
        ));
        sort($paths, SORT_STRING);

        return $paths;
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
        return array_map(
            static fn(array $event): array => [
                'timestamp' => $event['timestamp'],
                'valueW' => $event['value'],
            ],
            $this->readArchiveEvents($archiveId, $variableId, $from, $to)
        );
    }

    /** @return array<int, array{timestamp: int, value: float}> */
    private function readArchiveEvents(int $archiveId, int $variableId, int $from, int $to): array
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
        foreach ($values as $timestamp => $value) {
            $events[] = ['timestamp' => $timestamp, 'value' => $value];
        }

        return $events;
    }

    /**
     * @param array<int, array<string, int|float|null>> $daily
     * @param array<int, array<string, mixed>> $samples
     * @return array<int, array<string, mixed>>
     */
    private function annotateDailyClassifications(
        array $daily,
        array $samples,
        float $minimumClassificationCoverage
    ): array {
        foreach ($daily as &$day) {
            $counts = [
                'unconstrained' => 0,
                'curtailed' => 0,
                'uncertain' => 0,
                'data_gap' => 0,
            ];
            $expectedSeconds = max(0, (int)($day['validTo'] ?? 0) - (int)($day['validFrom'] ?? 0));
            $representedSeconds = 0.0;
            foreach ($samples as $sample) {
                if (
                    ($sample['validFrom'] ?? PHP_INT_MAX) < ($day['validTo'] ?? 0)
                    && ($sample['validTo'] ?? 0) > ($day['validFrom'] ?? PHP_INT_MAX)
                ) {
                    $classification = $sample['classification'] ?? null;
                    if (is_string($classification) && array_key_exists($classification, $counts)) {
                        $counts[$classification]++;
                    }
                    $overlapFrom = max((int)$sample['validFrom'], (int)$day['validFrom']);
                    $overlapTo = min((int)$sample['validTo'], (int)$day['validTo']);
                    $coverage = $sample['coverage'] ?? 0.0;
                    if ((is_int($coverage) || is_float($coverage)) && $overlapTo > $overlapFrom) {
                        $representedSeconds += ($overlapTo - $overlapFrom) * max(0.0, min(1.0, (float)$coverage));
                    }
                }
            }
            $representedSeconds = min((float)$expectedSeconds, $representedSeconds);
            $classificationCoverage = $expectedSeconds > 0
                ? $representedSeconds / $expectedSeconds
                : 0.0;
            $day['classificationCounts'] = $counts;
            $day['expectedDurationSeconds'] = $expectedSeconds;
            $day['representedDurationSeconds'] = $representedSeconds;
            $day['classificationCoverage'] = $classificationCoverage;
            $day['calibrationEligible'] = array_sum($counts) > 0
                && ($day['measuredKwh'] ?? null) !== null
                && $classificationCoverage >= $minimumClassificationCoverage
                && $counts['curtailed'] === 0
                && $counts['uncertain'] === 0
                && $counts['data_gap'] === 0;
        }
        unset($day);

        return $daily;
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
