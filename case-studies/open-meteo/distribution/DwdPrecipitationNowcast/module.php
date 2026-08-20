<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/OpenMeteo/ForecastStateReducer.php';
require_once __DIR__ . '/../libs/OpenMeteo/LocationDefinition.php';
require_once __DIR__ . '/../libs/DwdNowcast/RequestBuilder.php';
require_once __DIR__ . '/../libs/DwdNowcast/ResponseParser.php';
require_once __DIR__ . '/../libs/DwdNowcast/ForecastProjector.php';
require_once __DIR__ . '/../libs/DwdNowcast/NowcastHtmlRenderer.php';
require_once __DIR__ . '/../libs/DwdNowcast/TransportDiagnostics.php';
if (!function_exists('SAEF_EnsureProfile')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/object/EnsureProfile.php';
}
if (!function_exists('SAEF_CreateConfigurationHash')) {
    require_once __DIR__ . '/../libs/SAEF/helpers/diagnostics/ConfigurationHash.php';
}
require_once __DIR__ . '/../libs/DwdNowcast/Profiles.php';

use SAEF\CaseStudy\DwdNowcast\ForecastProjector;
use SAEF\CaseStudy\DwdNowcast\NowcastHtmlRenderer;
use SAEF\CaseStudy\DwdNowcast\Profiles;
use SAEF\CaseStudy\DwdNowcast\RequestBuilder;
use SAEF\CaseStudy\DwdNowcast\ResponseParser;
use SAEF\CaseStudy\DwdNowcast\TransportDiagnostics;
use SAEF\CaseStudy\OpenMeteo\ForecastStateReducer;
use SAEF\CaseStudy\OpenMeteo\LocationDefinition;

/**
 * @phpstan-type RuntimeState array{
 *     state: string,
 *     configurationHash: string,
 *     hasData: bool,
 *     lastAttempt: ?int,
 *     lastSuccess: ?int,
 *     validFrom: ?int,
 *     validTo: ?int,
 *     retryCount: int,
 *     maxRetries: int,
 *     errorCode: ?string
 * }
 */
class DwdPrecipitationNowcast extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIGURATION_ERROR = 200;
    private const LOCATION_MODULE_ID = '{3B6B9CB0-8D95-4358-874A-13FF1A8BECD1}';
    private const MAXIMUM_LOCATION_DESCRIPTOR_BYTES = 4096;
    private const MAXIMUM_RESPONSE_BYTES = 262144;
    private const MAX_RETRY_STEP = 4;
    private const SEMAPHORE_TIMEOUT_MILLISECONDS = 1000;
    private const MAXIMUM_POLLING_OFFSET_SECONDS = 120;
    private const RETRY_JITTER_SECONDS = 45;

    private ?string $capturedTransportWarningClass = null;

    /** @var array<string, int> */
    private const DATA_STATE_VALUES = [
        ForecastStateReducer::STATE_UNCONFIGURED => 0,
        'fetching' => 1,
        ForecastStateReducer::STATE_CURRENT => 2,
        ForecastStateReducer::STATE_STALE => 3,
        ForecastStateReducer::STATE_WARNING => 4,
        ForecastStateReducer::STATE_ERROR => 5,
    ];

    /** @var array<int, int> */
    private const RETRY_INTERVAL_MINUTES = [
        1 => 1,
        2 => 2,
        3 => 5,
    ];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('LocationInstanceId', 0);
        $this->RegisterPropertyInteger('ForecastWindowMinutes', 60);
        $this->RegisterPropertyFloat('RainThresholdMmPerHour', 0.1);
        $this->RegisterPropertyBoolean('EnableAutomaticUpdates', true);
        $this->RegisterPropertyInteger('PollingIntervalMinutes', 5);
        $this->RegisterPropertyInteger('PollingOffsetSeconds', -1);
        $this->RegisterPropertyInteger('HttpTimeoutSeconds', 10);
        $this->RegisterPropertyInteger('StaleAfterMinutes', 15);

        $this->RegisterAttributeString('RuntimeState', '');
        $this->RegisterAttributeString('ForecastCache', '');
        $this->RegisterAttributeString('TransportDiagnostics', '');
        $this->RegisterAttributeInteger('RegisteredLocationReferenceId', 0);

        $this->RegisterTimer(
            'UpdateData',
            0,
            'DWDNOWCAST_UpdateData($_IPS["TARGET"]);'
        );
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        Profiles::ensure();
        $this->registerVariables();
        $this->migrateNowcastChartName();

        $locationInstanceId = $this->ReadPropertyInteger('LocationInstanceId');
        if ($locationInstanceId <= 0) {
            $this->reconcileLocationReference(0);
            $this->SetTimerInterval('UpdateData', 0);
            $this->resetDomainValues();
            $this->SetValue('DataState', 0);
            $this->SetStatus(self::STATUS_INACTIVE);

            return;
        }

        try {
            $this->requestConfiguration();
            $this->validateRuntimePolicy();
            $this->reconcileLocationReference($locationInstanceId);
            $state = $this->runtimeState();
            $this->writeRuntimeState($state);
            $cache = $this->readCache();
            if ($cache === null) {
                $this->resetDomainValues();
            } else {
                $this->publishNowcastChart($cache);
            }
            $this->publishOperationalState($state, $this->currentTimestamp());
            $this->scheduleInitialPolling();
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetValue('DataState', 5);
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);
        }
    }

    public function UpdateData(): string
    {
        if ($this->ReadPropertyInteger('LocationInstanceId') <= 0) {
            return $this->result(false, 'configuration_missing');
        }

        try {
            $configuration = $this->requestConfiguration();
            $this->validateRuntimePolicy();
        } catch (Throwable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetValue('DataState', 5);
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);

            return $this->result(false, 'configuration_invalid');
        }

        $lockName = 'DwdPrecipitationNowcast.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lockName, self::SEMAPHORE_TIMEOUT_MILLISECONDS)) {
            return $this->result(false, 'busy');
        }

        try {
            return $this->executeUpdate($configuration);
        } finally {
            if (!IPS_SemaphoreLeave($lockName)) {
                IPS_LogMessage('DwdPrecipitationNowcast', 'Instance lock could not be released.');
            }
        }
    }

    public function GetForecastJson(): string
    {
        $cache = $this->readCache();
        if ($cache === null) {
            return $this->result(false, 'cache_empty');
        }

        return $this->encode(['success' => true, 'data' => $cache]);
    }

    public function GetLocationDescriptor(): string
    {
        try {
            return $this->encode([
                'success' => true,
                'location' => $this->sharedLocationConfiguration(
                    $this->ReadPropertyInteger('LocationInstanceId')
                ),
            ]);
        } catch (Throwable) {
            return $this->result(false, 'configuration_invalid');
        }
    }

    public function GetDiagnosticsJson(): string
    {
        return $this->encode([
            'success' => true,
            'transport' => $this->transportDiagnostics(),
            'scheduling' => [
                'pollingOffsetSeconds' => $this->resolvedPollingOffsetSeconds(),
                'retryJitterSeconds' => $this->resolvedRetryJitterSeconds(),
            ],
        ]);
    }

    /** @param array{key: string, latitude: float, longitude: float, timezone: string, elevation: ?float} $configuration */
    private function executeUpdate(array $configuration): string
    {
        $attemptedAt = $this->currentTimestamp();
        $state = $this->runtimeState();
        $this->SetValue('DataState', self::DATA_STATE_VALUES['fetching']);
        $this->SetValue('LastFetchAttempt', $attemptedAt);

        try {
            $url = RequestBuilder::build(
                $configuration['latitude'],
                $configuration['longitude'],
                $attemptedAt
            );
            try {
                $this->capturedTransportWarningClass = null;
                $body = $this->fetchUrl(
                    $url,
                    $this->ReadPropertyInteger('HttpTimeoutSeconds') * 1000
                );
            } catch (Throwable) {
                return $this->recordTransportFailure(
                    $state,
                    $attemptedAt,
                    $this->capturedTransportWarningClass ?? TransportDiagnostics::CLASS_EXCEPTION
                );
            }
            if ($body === false) {
                return $this->recordTransportFailure(
                    $state,
                    $attemptedAt,
                    $this->capturedTransportWarningClass ?? TransportDiagnostics::CLASS_NO_RESPONSE
                );
            }
            if (strlen($body) > self::MAXIMUM_RESPONSE_BYTES) {
                return $this->recordTransportFailure(
                    $state,
                    $attemptedAt,
                    TransportDiagnostics::CLASS_RESPONSE_TOO_LARGE
                );
            }

            $parsed = ResponseParser::parse($body);
            $cache = ForecastProjector::project(
                $parsed,
                $this->ReadPropertyInteger('ForecastWindowMinutes'),
                $this->ReadPropertyFloat('RainThresholdMmPerHour'),
                $attemptedAt
            );
            $failedAttempts = $state['retryCount'];
            $shouldLogRecovery = $this->shouldLogRecovery($state);
            $transportDiagnostics = $this->transportDiagnostics();
            $transportFailures = $transportDiagnostics['consecutiveFailures'];
            $state = ForecastStateReducer::success(
                $state,
                $attemptedAt,
                $cache['validFrom'],
                $cache['validTo']
            );

            $this->WriteAttributeString('ForecastCache', $this->encodeCache($cache));
            $this->publishForecast($cache);
            $this->writeRuntimeState($state);
            $this->writeTransportDiagnostics(
                TransportDiagnostics::success($transportDiagnostics, $attemptedAt)
            );
            $this->publishOperationalState($state, $attemptedAt);
            $this->scheduleNormalPolling();
            $this->SetStatus(self::STATUS_ACTIVE);

            if ($shouldLogRecovery) {
                $message = sprintf(
                    'Update recovered after %d failed attempt(s)',
                    $failedAttempts
                );
                if ($transportFailures > 0) {
                    $message .= sprintf(', including %d transport failure(s)', $transportFailures);
                }
                IPS_LogMessage('DwdPrecipitationNowcast', $message . '.');
            }

            return $this->result(true, 'ok');
        } catch (InvalidArgumentException) {
            return $this->recordFailure($state, $attemptedAt, 'response_invalid', true);
        } catch (Throwable) {
            return $this->recordFailure($state, $attemptedAt, 'response_invalid', true);
        }
    }

    /**
     * Kept protected so offline module tests can inject a transport without network access.
     */
    protected function fetchUrl(string $url, int $timeoutMilliseconds): string|false
    {
        set_error_handler(function (int $severity, string $message): bool {
            if ($severity !== E_WARNING && $severity !== E_USER_WARNING) {
                return false;
            }
            $failureClass = TransportDiagnostics::classifyWarning($message);
            if ($failureClass === null) {
                return false;
            }
            $this->capturedTransportWarningClass = $failureClass;

            return true;
        }, E_WARNING | E_USER_WARNING);
        try {
            $body = $this->performUrlRequest($url, $timeoutMilliseconds);

            return $this->capturedTransportWarningClass === null ? $body : false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Kept protected so offline module tests can exercise the warning boundary without network access.
     */
    protected function performUrlRequest(string $url, int $timeoutMilliseconds): string|false
    {
        return Sys_GetURLContentEx($url, [
            'Timeout' => $timeoutMilliseconds,
            'VerifyPeer' => true,
            'VerifyHost' => true,
        ]);
    }

    /** @param RuntimeState $state */
    private function recordTransportFailure(array $state, int $attemptedAt, string $failureClass): string
    {
        $diagnostics = TransportDiagnostics::failure(
            $this->transportDiagnostics(),
            $attemptedAt,
            $failureClass
        );
        $this->writeTransportDiagnostics($diagnostics);

        return $this->recordFailure($state, $attemptedAt, 'transport_error', true, $failureClass);
    }

    /** @param RuntimeState $state */
    private function recordFailure(
        array $state,
        int $attemptedAt,
        string $errorCode,
        bool $retryable,
        ?string $detailCode = null
    ): string {
        $previousState = $state;
        $state = ForecastStateReducer::failure($state, $attemptedAt, $errorCode, $retryable);
        $state = ForecastStateReducer::evaluateFreshness(
            $state,
            $attemptedAt,
            $this->ReadPropertyInteger('StaleAfterMinutes') * 60
        );
        $this->writeRuntimeState($state);
        $this->publishOperationalState($state, $attemptedAt);
        if ($retryable) {
            $this->scheduleAfterFailure($state['retryCount']);
        }
        if ($this->shouldLogFailure($previousState, $state, $retryable)) {
            $context = $detailCode === null ? $errorCode : $errorCode . ', ' . $detailCode;
            IPS_LogMessage(
                'DwdPrecipitationNowcast',
                sprintf(
                    'Update failed (%s, retry %d/%d).',
                    $context,
                    $state['retryCount'],
                    $state['maxRetries']
                )
            );
        }

        return $this->result(false, $errorCode);
    }

    /**
     * @param RuntimeState $previousState
     * @param RuntimeState $state
     */
    private function shouldLogFailure(array $previousState, array $state, bool $retryable): bool
    {
        if (!$retryable) {
            return true;
        }
        if (!$state['hasData'] && $previousState['state'] !== ForecastStateReducer::STATE_ERROR) {
            return true;
        }
        if (
            $state['state'] === ForecastStateReducer::STATE_STALE
            && $previousState['state'] !== ForecastStateReducer::STATE_STALE
        ) {
            return true;
        }

        return $previousState['retryCount'] < $state['maxRetries']
            && $state['retryCount'] >= $state['maxRetries'];
    }

    /** @param RuntimeState $state */
    private function shouldLogRecovery(array $state): bool
    {
        return $state['retryCount'] > 0 && (
            !$state['hasData']
            || $state['state'] === ForecastStateReducer::STATE_STALE
            || $state['retryCount'] >= $state['maxRetries']
        );
    }

    /** @return RuntimeState */
    private function runtimeState(): array
    {
        return ForecastStateReducer::fromJson(
            $this->ReadAttributeString('RuntimeState'),
            $this->configurationHash(),
            self::MAX_RETRY_STEP
        );
    }

    /** @param RuntimeState $state */
    private function writeRuntimeState(array $state): void
    {
        $this->WriteAttributeString('RuntimeState', ForecastStateReducer::toJson($state));
    }

    /** @return array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: ?int, lastFailureClass: ?string, lastRecoveryAt: ?int, lastRecoveryAttempts: int} */
    private function transportDiagnostics(): array
    {
        return TransportDiagnostics::fromJson($this->ReadAttributeString('TransportDiagnostics'));
    }

    /** @param array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: ?int, lastFailureClass: ?string, lastRecoveryAt: ?int, lastRecoveryAttempts: int} $diagnostics */
    private function writeTransportDiagnostics(array $diagnostics): void
    {
        $this->WriteAttributeString(
            'TransportDiagnostics',
            TransportDiagnostics::toJson($diagnostics)
        );
    }

    /** @param RuntimeState $state */
    private function publishOperationalState(array $state, int $now): void
    {
        $state = ForecastStateReducer::evaluateFreshness(
            $state,
            $now,
            $this->ReadPropertyInteger('StaleAfterMinutes') * 60
        );
        $this->SetValue('DataState', self::DATA_STATE_VALUES[$state['state']] ?? 5);
        $this->SetValue('LastFetchAttempt', $state['lastAttempt'] ?? 0);
        $this->SetValue('LastSuccess', $state['lastSuccess'] ?? 0);
        $this->SetValue('ForecastValidFrom', $state['validFrom'] ?? 0);
        $this->SetValue('ForecastValidTo', $state['validTo'] ?? 0);
        $age = $state['lastSuccess'] === null
            ? 0
            : max(0, intdiv($now - $state['lastSuccess'], 60));
        $this->SetValue('ForecastAgeMinutes', $age);
        $productTime = (int) $this->testCacheValue('productTime', 0);
        $this->SetValue('ProductAgeMinutes', $productTime <= 0 ? 0 : max(0, intdiv($now - $productTime, 60)));
    }

    /** @param array<string, mixed> $cache */
    private function publishForecast(array $cache): void
    {
        $summary = $cache['summary'];
        $this->SetValue('ProductTime', $cache['productTime']);
        $this->SetValue('RainExpected', $summary['rainExpected']);
        $this->SetValue('RainStartsInMinutes', $summary['rainStartsInMinutes']);
        $this->SetValue('RainEndsInMinutes', $summary['rainEndsInMinutes']);
        $this->SetValue('PrecipitationSum', $summary['precipitationSumMm']);
        $this->SetValue('MaximumIntensity', $summary['maximumIntensityMmPerHour']);
        $this->SetValue('NextIntervalIntensity', $summary['nextIntervalIntensityMmPerHour']);
        $this->SetValue('WindowMinutes', $cache['evaluationWindowMinutes']);
        $this->SetValue('ForecastPointCount', $summary['forecastPointCount']);
        $this->SetValue('NativeResolutionMinutes', $cache['nativeResolutionMinutes']);
        $this->publishNowcastChart($cache);
    }

    private function scheduleNormalPolling(): void
    {
        $this->SetTimerInterval(
            'UpdateData',
            $this->ReadPropertyBoolean('EnableAutomaticUpdates')
                ? $this->ReadPropertyInteger('PollingIntervalMinutes') * 60000
                : 0
        );
    }

    private function scheduleInitialPolling(): void
    {
        if (!$this->ReadPropertyBoolean('EnableAutomaticUpdates')) {
            $this->SetTimerInterval('UpdateData', 0);

            return;
        }
        $seconds = ($this->ReadPropertyInteger('PollingIntervalMinutes') * 60)
            + $this->resolvedPollingOffsetSeconds();
        $this->SetTimerInterval('UpdateData', $seconds * 1000);
    }

    private function scheduleAfterFailure(int $retryCount): void
    {
        if (!$this->ReadPropertyBoolean('EnableAutomaticUpdates')) {
            $this->SetTimerInterval('UpdateData', 0);

            return;
        }
        $minutes = self::RETRY_INTERVAL_MINUTES[$retryCount]
            ?? $this->ReadPropertyInteger('PollingIntervalMinutes');
        $seconds = ($minutes * 60) + $this->resolvedRetryJitterSeconds();
        $this->SetTimerInterval('UpdateData', $seconds * 1000);
    }

    private function resolvedPollingOffsetSeconds(): int
    {
        $configured = $this->ReadPropertyInteger('PollingOffsetSeconds');
        if ($configured >= 0) {
            return $configured;
        }

        return $this->stableInstanceOffset('polling', self::MAXIMUM_POLLING_OFFSET_SECONDS + 1);
    }

    private function resolvedRetryJitterSeconds(): int
    {
        $configured = $this->ReadPropertyInteger('PollingOffsetSeconds');
        if ($configured >= 0) {
            return $configured % self::RETRY_JITTER_SECONDS;
        }

        return $this->stableInstanceOffset('retry', self::RETRY_JITTER_SECONDS);
    }

    private function stableInstanceOffset(string $purpose, int $modulus): int
    {
        $hashPrefix = substr(hash('sha256', 'dwd-nowcast:' . $purpose . ':' . $this->InstanceID), 0, 7);

        return (int) hexdec($hashPrefix) % $modulus;
    }

    /** @return array{key: string, latitude: float, longitude: float, timezone: string, elevation: ?float} */
    private function requestConfiguration(): array
    {
        return $this->sharedLocationConfiguration(
            $this->ReadPropertyInteger('LocationInstanceId')
        );
    }

    private function configurationHash(): string
    {
        return SAEF_CreateConfigurationHash([
            'location' => $this->requestConfiguration(),
            'forecastWindowMinutes' => $this->ReadPropertyInteger('ForecastWindowMinutes'),
            'rainThresholdMmPerHour' => $this->ReadPropertyFloat('RainThresholdMmPerHour'),
        ]);
    }

    /** @return array{key: string, latitude: float, longitude: float, timezone: string, elevation: ?float} */
    private function sharedLocationConfiguration(int $instanceId): array
    {
        if ($instanceId <= 0 || !IPS_InstanceExists($instanceId)) {
            throw new InvalidArgumentException('Shared location instance does not exist.');
        }
        $instance = IPS_GetInstance($instanceId);
        $moduleId = $instance['ModuleInfo']['ModuleID'] ?? null;
        if (!is_string($moduleId) || $moduleId !== self::LOCATION_MODULE_ID) {
            throw new InvalidArgumentException('Shared location instance has an incompatible module type.');
        }

        $descriptorJson = SAEFLOCATION_GetDescriptor($instanceId);
        if (strlen($descriptorJson) > self::MAXIMUM_LOCATION_DESCRIPTOR_BYTES) {
            throw new InvalidArgumentException('Shared location descriptor is too large.');
        }
        $descriptor = json_decode($descriptorJson, true, 16, JSON_THROW_ON_ERROR);
        if (
            !is_array($descriptor)
            || ($descriptor['success'] ?? null) !== true
            || !is_array($descriptor['location'] ?? null)
        ) {
            throw new InvalidArgumentException('Shared location descriptor is unavailable.');
        }

        return LocationDefinition::normalize($descriptor['location']);
    }

    private function reconcileLocationReference(int $desiredInstanceId): void
    {
        $registeredInstanceId = $this->ReadAttributeInteger('RegisteredLocationReferenceId');
        if ($registeredInstanceId === $desiredInstanceId) {
            if ($desiredInstanceId > 0) {
                $this->RegisterReference($desiredInstanceId);
            }

            return;
        }
        if ($registeredInstanceId > 0) {
            $this->UnregisterReference($registeredInstanceId);
        }
        if ($desiredInstanceId > 0) {
            $this->RegisterReference($desiredInstanceId);
        }
        $this->WriteAttributeInteger('RegisteredLocationReferenceId', $desiredInstanceId);
    }

    private function validateRuntimePolicy(): void
    {
        $window = $this->ReadPropertyInteger('ForecastWindowMinutes');
        $threshold = $this->ReadPropertyFloat('RainThresholdMmPerHour');
        $polling = $this->ReadPropertyInteger('PollingIntervalMinutes');
        $pollingOffset = $this->ReadPropertyInteger('PollingOffsetSeconds');
        $timeout = $this->ReadPropertyInteger('HttpTimeoutSeconds');
        $staleAfter = $this->ReadPropertyInteger('StaleAfterMinutes');
        if (
            $window < RequestBuilder::NATIVE_RESOLUTION_MINUTES
            || $window > RequestBuilder::MAXIMUM_HORIZON_MINUTES
            || $window % RequestBuilder::NATIVE_RESOLUTION_MINUTES !== 0
            || !is_finite($threshold)
            || $threshold <= 0.0
            || $threshold > 1000.0
            || $polling < RequestBuilder::NATIVE_RESOLUTION_MINUTES
            || $polling % RequestBuilder::NATIVE_RESOLUTION_MINUTES !== 0
            || $pollingOffset < -1
            || $pollingOffset > self::MAXIMUM_POLLING_OFFSET_SECONDS
            || $timeout < 1
            || $timeout > 60
            || $staleAfter < $polling
        ) {
            throw new InvalidArgumentException('DWD nowcast runtime policy is invalid.');
        }
    }

    private function registerVariables(): void
    {
        $this->RegisterVariableInteger('DataState', 'Data State', 'DWDNOWCAST.DataState', 10);
        $this->RegisterVariableInteger('LastFetchAttempt', 'Last Fetch Attempt', '~UnixTimestamp', 20);
        $this->RegisterVariableInteger('LastSuccess', 'Last Success', '~UnixTimestamp', 30);
        $this->RegisterVariableInteger('ProductTime', 'Product Time', '~UnixTimestamp', 40);
        $this->RegisterVariableInteger('ForecastValidFrom', 'Forecast Valid From', '~UnixTimestamp', 50);
        $this->RegisterVariableInteger('ForecastValidTo', 'Forecast Valid To', '~UnixTimestamp', 60);
        $this->RegisterVariableInteger('ForecastAgeMinutes', 'Forecast Age Minutes', '', 70);
        $this->RegisterVariableInteger('ProductAgeMinutes', 'Product Age Minutes', '', 80);
        $this->RegisterVariableBoolean('RainExpected', 'Rain Expected', '', 100);
        $this->RegisterVariableInteger('RainStartsInMinutes', 'Rain Starts In', 'DWDNOWCAST.Minutes', 110);
        $this->RegisterVariableInteger('RainEndsInMinutes', 'Rain Ends In', 'DWDNOWCAST.Minutes', 120);
        $this->RegisterVariableFloat('PrecipitationSum', 'Precipitation Sum', 'DWDNOWCAST.WaterDepth', 130);
        $this->RegisterVariableFloat('MaximumIntensity', 'Maximum Intensity', 'DWDNOWCAST.Intensity', 140);
        $this->RegisterVariableFloat('NextIntervalIntensity', 'Next Interval Intensity', 'DWDNOWCAST.Intensity', 150);
        $this->RegisterVariableInteger('WindowMinutes', 'Evaluation Window', 'DWDNOWCAST.Minutes', 160);
        $this->RegisterVariableInteger('ForecastPointCount', 'Forecast Point Count', '', 170);
        $this->RegisterVariableInteger('NativeResolutionMinutes', 'Native Resolution', 'DWDNOWCAST.Minutes', 180);
        $this->RegisterVariableString(
            'NowcastChart',
            $this->Translate('Rain forecast'),
            $this->chartPresentation(),
            190
        );
    }

    /** @return string|array{PRESENTATION: string, HTML_TYPE: int, PADDING: bool} */
    private function chartPresentation(): string|array
    {
        if (!defined('VARIABLE_PRESENTATION_WEB_CONTENT')) {
            return '~HTMLBox';
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
            'HTML_TYPE' => 0,
            'PADDING' => true,
        ];
    }

    private function migrateNowcastChartName(): void
    {
        $variableId = $this->GetIDForIdent('NowcastChart');
        if ($variableId <= 0 || !IPS_VariableExists($variableId)) {
            throw new RuntimeException('Nowcast chart variable is missing.');
        }

        $object = IPS_GetObject($variableId);
        if (
            ($object['ObjectType'] ?? null) !== 2
            || ($object['ParentID'] ?? null) !== $this->InstanceID
            || ($object['ObjectIdent'] ?? null) !== 'NowcastChart'
        ) {
            throw new RuntimeException('Nowcast chart variable ownership differs.');
        }

        $legacyName = 'Rain forecast';
        $translatedName = $this->Translate($legacyName);
        if (IPS_GetName($variableId) === $legacyName && $translatedName !== $legacyName) {
            IPS_SetName($variableId, $translatedName);
        }
    }

    private function resetDomainValues(): void
    {
        $this->SetValue('ProductTime', 0);
        $this->SetValue('RainExpected', false);
        $this->SetValue('RainStartsInMinutes', -1);
        $this->SetValue('RainEndsInMinutes', -1);
        $this->SetValue('PrecipitationSum', 0.0);
        $this->SetValue('MaximumIntensity', 0.0);
        $this->SetValue('NextIntervalIntensity', 0.0);
        $this->SetValue('WindowMinutes', $this->ReadPropertyInteger('ForecastWindowMinutes'));
        $this->SetValue('ForecastPointCount', 0);
        $this->SetValue('NativeResolutionMinutes', RequestBuilder::NATIVE_RESOLUTION_MINUTES);
        $this->SetValue('NowcastChart', NowcastHtmlRenderer::renderEmpty($this->chartLabels()));
    }

    /** @param array<string, mixed> $cache */
    private function publishNowcastChart(array $cache): void
    {
        $location = $this->requestConfiguration();
        $this->SetValue(
            'NowcastChart',
            NowcastHtmlRenderer::render($cache, $location['timezone'], $this->chartLabels())
        );
    }

    /**
     * @return array{
     *     rainIn: string,
     *     noRain: string,
     *     now: string,
     *     minuteTooltip: string,
     *     noData: string
     * }
     */
    private function chartLabels(): array
    {
        return [
            'rainIn' => $this->Translate('Rain in %d min'),
            'noRain' => $this->Translate('No rain in %d min'),
            'now' => $this->Translate('now'),
            'minuteTooltip' => $this->Translate('+%d min: %.2f mm/h'),
            'noData' => $this->Translate('No nowcast data'),
        ];
    }

    /** @return null|array<string, mixed> */
    private function readCache(): ?array
    {
        try {
            $cache = json_decode(
                $this->ReadAttributeString('ForecastCache'),
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return null;
        }
        if (
            !is_array($cache)
            || ($cache['configurationHash'] ?? null) !== $this->configurationHash()
            || !is_array($cache['points'] ?? null)
            || !is_array($cache['windowPoints'] ?? null)
            || !is_array($cache['summary'] ?? null)
        ) {
            return null;
        }

        return $cache;
    }

    private function testCacheValue(string $key, mixed $default): mixed
    {
        $cache = $this->readCache();

        return $cache[$key] ?? $default;
    }

    /** @param array<string, mixed> $cache */
    private function encodeCache(array $cache): string
    {
        $cache['configurationHash'] = $this->configurationHash();

        return $this->encode($cache);
    }

    private function result(bool $success, string $code): string
    {
        return $this->encode(['success' => $success, 'code' => $code]);
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    protected function currentTimestamp(): int
    {
        return time();
    }
}
