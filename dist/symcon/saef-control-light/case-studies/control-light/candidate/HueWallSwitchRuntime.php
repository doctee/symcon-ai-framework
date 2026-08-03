<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../../../helpers/object/EnsureEvent.php';
require_once __DIR__ . '/../../../helpers/object/EnsureVariable.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ErrorRingBuffer.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Registry.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Statistics.php';
require_once __DIR__ . '/../../../helpers/variable/WaitForVariable.php';
require_once __DIR__ . '/HueWallSwitchCore.php';

/**
 * Runtime candidate for two Hue Wall Modules controlling ControlLight facades.
 *
 * Reconciliation is deliberately separated from the latency-sensitive variable
 * event path. Execute the owner script once after changing configuration. The
 * resulting registry contains the small resource index used by event runs.
 */
final class HueWallSwitchRuntime
{
    private const ACTION_EVENT_PREFIX = 'HWS_EV_ACTION_';
    private const FEEDBACK_EVENT_PREFIX = 'HWS_EV_FEEDBACK_';
    private const DEBOUNCE_PREFIX = 'HWS_DEBOUNCE_';
    private const ERROR_CAPACITY = 20;

    /**
     * @param array<string, mixed> $sourceIPS
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function run(int $ownerScriptID, array $sourceIPS, array $configuration): array
    {
        $normalized = HueWallSwitchCore::normalizeConfiguration($configuration);
        self::validateOwnerScript($ownerScriptID);
        $sender = (string) ($sourceIPS['SENDER'] ?? '');

        if (in_array($sender, ['Execute', 'RunScript'], true)) {
            $diagnostics = self::initializeDiagnostics($ownerScriptID);
            SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_EXECUTIONS']);
            SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['HWS_LAST_RUN']);
            $resources = self::reconcileResources($ownerScriptID, $normalized);
            self::writeRuntimeIndex($diagnostics, $resources, $normalized);
            self::recordSuccess($diagnostics);

            return [
                'status' => 'reconciled',
                'actionEvents' => count($resources['actionEventIDs']),
                'feedbackEvents' => count($resources['feedbackEventIDs']),
            ];
        }

        $runtime = self::loadRuntimeIndex($ownerScriptID, $normalized);
        $diagnostics = $runtime['diagnostics'];
        $resources = $runtime['resources'];
        SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_EXECUTIONS']);
        SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['HWS_LAST_RUN']);

        if ($sender !== 'Variable') {
            return ['status' => 'ignored_sender', 'sender' => $sender];
        }

        $sourceVariableID = (int) ($sourceIPS['VARIABLE'] ?? 0);
        $sourceKey = self::sourceKeyForVariable($sourceVariableID, $normalized);
        if ($sourceKey !== null) {
            self::assertSourceVariable($sourceVariableID, $sourceKey);
            $rawAction = $sourceIPS['VALUE'] ?? GetValue($sourceVariableID);
            if (!is_string($rawAction)) {
                throw new RuntimeException('Hue Wall action variable must provide a string value.');
            }

            return self::dispatchActionUpdate(
                $ownerScriptID,
                $sourceKey,
                $rawAction,
                $resources,
                $normalized,
                $diagnostics
            );
        }

        $targetKey = self::targetKeyForVariable($sourceVariableID, $normalized);
        if ($targetKey !== null) {
            self::assertTargetVariable($sourceVariableID, $targetKey);
            SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_FEEDBACKS']);
            SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['HWS_LAST_FEEDBACK']);

            return ['status' => 'feedback_observed', 'targetKey' => $targetKey];
        }

        return ['status' => 'ignored_variable'];
    }

    /**
     * @param array<string, mixed> $configuration Normalized configuration.
     *
     * @return array<string, mixed>
     */
    public static function reconcileResources(int $ownerScriptID, array $configuration): array
    {
        self::validateOwnerScript($ownerScriptID);
        $actionEventIDs = [];
        $feedbackEventIDs = [];
        $debounceVariableIDs = [];

        foreach ($configuration['targets'] as $targetKey => $target) {
            self::assertTargetVariable((int) $target['stateVariableID'], (string) $targetKey);
            $feedbackEventIDs[$targetKey] = SAEF_EnsureTriggeredScriptEvent(
                $ownerScriptID,
                (string) $target['feedbackEventIdent'],
                'Hue Wall feedback: ' . (string) $target['name'],
                $ownerScriptID,
                (int) $target['stateVariableID'],
                1,
                true,
                null,
                true,
                false
            );
        }

        foreach ($configuration['sources'] as $sourceKey => $source) {
            self::assertSourceVariable((int) $source['sourceVariableID'], (string) $sourceKey);
            $debounceVariableIDs[$sourceKey] = [];
            foreach (self::sourceTargetKeys($source) as $targetKey) {
                $debounceVariableIDs[$sourceKey][$targetKey] = SAEF_EnsureVariable(
                    $ownerScriptID,
                    self::debounceIdent((string) $sourceKey, $targetKey),
                    'Hue Wall debounce: ' . (string) $source['name'] . ' / ' . $targetKey,
                    2,
                    '',
                    null,
                    null,
                    null,
                    false
                );
            }
            $actionEventIDs[$sourceKey] = SAEF_EnsureTriggeredScriptEvent(
                $ownerScriptID,
                (string) $source['actionEventIdent'],
                'Hue Wall action: ' . (string) $source['name'],
                $ownerScriptID,
                (int) $source['sourceVariableID'],
                0,
                true,
                null,
                true,
                false
            );
        }

        self::deactivateObsoleteOwnedEvents(
            $ownerScriptID,
            array_merge(array_values($actionEventIDs), array_values($feedbackEventIDs))
        );

        return [
            'actionEventIDs' => $actionEventIDs,
            'feedbackEventIDs' => $feedbackEventIDs,
            'debounceVariableIDs' => $debounceVariableIDs,
        ];
    }

    /**
     * Dispatches one action update. Expected device/action failures are returned
     * as operational results and never escape the Symcon event boundary.
     *
     * @param array<string, mixed> $resources
     * @param array<string, mixed> $configuration Normalized configuration.
     * @param array<string, mixed> $diagnostics
     *
     * @return array<string, mixed>
     */
    public static function dispatchActionUpdate(
        int $ownerScriptID,
        string $sourceKey,
        string $rawAction,
        array $resources,
        array $configuration,
        array $diagnostics,
        ?float $now = null
    ): array {
        if (!isset($configuration['sources'][$sourceKey])) {
            throw new InvalidArgumentException('Unknown Hue Wall source key: ' . $sourceKey);
        }
        SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_ACTION_UPDATES']);
        $source = $configuration['sources'][$sourceKey];
        $targetKey = HueWallSwitchCore::targetKeyForAction($source, $rawAction);
        if ($targetKey === null) {
            SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_IGNORED_ACTIONS']);

            return ['status' => 'ignored_action', 'sourceKey' => $sourceKey];
        }
        $debounceVariableID = $resources['debounceVariableIDs'][$sourceKey][$targetKey] ?? null;
        if (!is_int($debounceVariableID)) {
            throw new RuntimeException(
                'Missing debounce state for Hue Wall source/target: ' . $sourceKey . '/' . $targetKey
            );
        }

        $target = $configuration['targets'][$targetKey];
        $targetVariableID = (int) $target['stateVariableID'];
        self::assertTargetVariable($targetVariableID, $targetKey);
        self::assertVariableType(
            $debounceVariableID,
            2,
            'debounce.' . $sourceKey . '.' . $targetKey
        );
        $semaphoreName = 'SAEF_HUE_WALL_' . $ownerScriptID . '_' . strtoupper($targetKey);
        if (!IPS_SemaphoreEnter($semaphoreName, (int) $configuration['semaphore']['timeoutMilliseconds'])) {
            return self::recordOperationalFailure(
                $diagnostics,
                'busy',
                $sourceKey,
                $targetKey
            );
        }

        try {
            $timestamp = $now ?? microtime(true);
            $previousTimestamp = GetValue($debounceVariableID);
            if (!is_int($previousTimestamp) && !is_float($previousTimestamp)) {
                throw new RuntimeException('Hue Wall debounce state must be numeric.');
            }

            $elapsedMilliseconds = ($timestamp - (float) $previousTimestamp) * 1000;
            if (
                (float) $previousTimestamp > 0.0
                && $configuration['debounceMilliseconds'] > 0
                && $elapsedMilliseconds >= 0.0
                && $elapsedMilliseconds < $configuration['debounceMilliseconds']
            ) {
                SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_DEBOUNCED']);

                return [
                    'status' => 'debounced',
                    'sourceKey' => $sourceKey,
                    'targetKey' => $targetKey,
                ];
            }

            SetValue($debounceVariableID, $timestamp);
            $confirmedState = GetValue($targetVariableID);
            if (!is_bool($confirmedState)) {
                throw new RuntimeException('ControlLight STATE facade must contain a boolean value.');
            }
            $desiredState = HueWallSwitchCore::desiredState($confirmedState);
            SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_COMMAND_ATTEMPTS']);

            try {
                $accepted = RequestAction($targetVariableID, $desiredState);
            } catch (Throwable $exception) {
                return self::recordOperationalFailure(
                    $diagnostics,
                    'action_exception',
                    $sourceKey,
                    $targetKey,
                    $exception::class
                );
            }

            if (!$accepted) {
                return self::recordOperationalFailure(
                    $diagnostics,
                    'action_rejected',
                    $sourceKey,
                    $targetKey
                );
            }

            $confirmed = GetValue($targetVariableID) === $desiredState;
            if (!$confirmed && $configuration['confirmation']['timeoutMilliseconds'] > 0) {
                $confirmed = SAEF_WaitForVariable(
                    $targetVariableID,
                    (int) $configuration['confirmation']['timeoutMilliseconds'],
                    (int) $configuration['confirmation']['pollIntervalMilliseconds'],
                    $desiredState,
                    SAEF_WAIT_UPDATED,
                    (int) $configuration['confirmation']['pollIntervalMilliseconds']
                );
            }

            if (!$confirmed) {
                SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_CONFIRMATION_TIMEOUTS']);

                return self::recordOperationalFailure(
                    $diagnostics,
                    'feedback_timeout',
                    $sourceKey,
                    $targetKey
                );
            }

            SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_CONFIRMED']);
            SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['HWS_LAST_SUCCESS']);
            SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['HWS_LAST_FEEDBACK']);

            return [
                'status' => 'confirmed',
                'sourceKey' => $sourceKey,
                'targetKey' => $targetKey,
                'state' => $desiredState,
            ];
        } finally {
            if (!IPS_SemaphoreLeave($semaphoreName)) {
                IPS_LogMessage('SAEF Hue Wall', 'Unable to release Hue Wall target semaphore.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function initializeDiagnostics(int $ownerScriptID): array
    {
        self::validateOwnerScript($ownerScriptID);
        $registryID = SAEF_EnsureRegistryVariable(
            $ownerScriptID,
            'HUE_WALL_REGISTRY',
            'Hue Wall Registry',
            900,
            'Database',
            false
        );
        $errorRingBufferID = SAEF_EnsureErrorRingBufferVariable(
            $ownerScriptID,
            'HUE_WALL_ERROR_HISTORY',
            'Hue Wall Error History',
            910,
            'Warning',
            false
        );
        $statisticIDs = SAEF_EnsureStatisticsVariables(
            $ownerScriptID,
            self::statisticsDefinitions(),
            false
        );

        return [
            'registryID' => $registryID,
            'errorRingBufferID' => $errorRingBufferID,
            'statisticIDs' => $statisticIDs,
        ];
    }

    /**
     * @param array<string, mixed> $diagnostics
     * @param array<string, mixed> $resources
     * @param array<string, mixed> $configuration
     */
    private static function writeRuntimeIndex(array $diagnostics, array $resources, array $configuration): void
    {
        $registry = [
            'version' => $configuration['version'],
            'configurationHash' => SAEF_CreateConfigurationHash($configuration),
            'semantics' => 'toggle_confirmed_control_light_state',
            'diagnosticIDs' => [
                'errorRingBufferID' => $diagnostics['errorRingBufferID'],
                'statisticIDs' => $diagnostics['statisticIDs'],
            ],
            'debounceVariableIDs' => $resources['debounceVariableIDs'],
        ];
        SAEF_WriteRegistry($diagnostics['registryID'], $registry);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array{diagnostics: array<string, mixed>, resources: array<string, mixed>}
     */
    private static function loadRuntimeIndex(int $ownerScriptID, array $configuration): array
    {
        $registryID = @IPS_GetObjectIDByIdent('HUE_WALL_REGISTRY', $ownerScriptID);
        if ($registryID === false || !IPS_VariableExists($registryID)) {
            throw new RuntimeException('Hue Wall runtime is not reconciled. Execute the owner script first.');
        }

        $registry = SAEF_ReadRegistry($registryID);
        if (($registry['configurationHash'] ?? null) !== SAEF_CreateConfigurationHash($configuration)) {
            throw new RuntimeException('Hue Wall configuration changed. Execute the owner script before handling events.');
        }

        $diagnosticIDs = $registry['diagnosticIDs'] ?? null;
        $debounceVariableIDs = $registry['debounceVariableIDs'] ?? null;
        if (!is_array($diagnosticIDs) || !is_array($debounceVariableIDs)) {
            throw new RuntimeException('Hue Wall runtime registry is incomplete.');
        }
        $errorRingBufferID = $diagnosticIDs['errorRingBufferID'] ?? null;
        $statisticIDs = $diagnosticIDs['statisticIDs'] ?? null;
        if (!is_int($errorRingBufferID) || !is_array($statisticIDs)) {
            throw new RuntimeException('Hue Wall diagnostic resource index is invalid.');
        }

        foreach (self::statisticsDefinitions() as $definition) {
            $ident = $definition['ident'];
            if (!isset($statisticIDs[$ident]) || !is_int($statisticIDs[$ident])) {
                throw new RuntimeException('Hue Wall statistic resource is missing: ' . $ident);
            }
        }

        foreach ($configuration['sources'] as $sourceKey => $_source) {
            $sourceDebounceVariableIDs = $debounceVariableIDs[$sourceKey] ?? null;
            if (!is_array($sourceDebounceVariableIDs)) {
                throw new RuntimeException('Hue Wall debounce resource map is missing: ' . $sourceKey);
            }
            foreach (self::sourceTargetKeys($configuration['sources'][$sourceKey]) as $targetKey) {
                if (
                    !isset($sourceDebounceVariableIDs[$targetKey])
                    || !is_int($sourceDebounceVariableIDs[$targetKey])
                ) {
                    throw new RuntimeException(
                        'Hue Wall debounce resource is missing: ' . $sourceKey . '/' . $targetKey
                    );
                }
            }
        }

        return [
            'diagnostics' => [
                'registryID' => $registryID,
                'errorRingBufferID' => $errorRingBufferID,
                'statisticIDs' => $statisticIDs,
            ],
            'resources' => [
                'debounceVariableIDs' => $debounceVariableIDs,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $diagnostics
     *
     * @return array<string, mixed>
     */
    private static function recordOperationalFailure(
        array $diagnostics,
        string $failureClass,
        string $sourceKey,
        string $targetKey,
        ?string $exceptionType = null
    ): array {
        try {
            SAEF_IncrementStatistic($diagnostics['statisticIDs']['HWS_COMMAND_FAILURES']);
            $context = [
                'failureClass' => $failureClass,
                'sourceKey' => $sourceKey,
                'targetKey' => $targetKey,
            ];
            if ($exceptionType !== null) {
                $context['exceptionType'] = $exceptionType;
            }
            SAEF_AppendErrorRingBufferEntry(
                $diagnostics['errorRingBufferID'],
                'Hue Wall command failed.',
                self::ERROR_CAPACITY,
                $context
            );
        } catch (Throwable $diagnosticException) {
            IPS_LogMessage('SAEF Hue Wall', 'Unable to record command failure: ' . $diagnosticException->getMessage());
        }

        return [
            'status' => 'command_failed',
            'failureClass' => $failureClass,
            'sourceKey' => $sourceKey,
            'targetKey' => $targetKey,
        ];
    }

    /** @param array<string, mixed> $diagnostics */
    private static function recordSuccess(array $diagnostics): void
    {
        SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['HWS_LAST_SUCCESS']);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function sourceKeyForVariable(int $variableID, array $configuration): ?string
    {
        foreach ($configuration['sources'] as $sourceKey => $source) {
            if ((int) $source['sourceVariableID'] === $variableID) {
                return (string) $sourceKey;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function targetKeyForVariable(int $variableID, array $configuration): ?string
    {
        foreach ($configuration['targets'] as $targetKey => $target) {
            if ((int) $target['stateVariableID'] === $variableID) {
                return (string) $targetKey;
            }
        }

        return null;
    }

    private static function assertSourceVariable(int $variableID, string $sourceKey): void
    {
        self::assertVariableType($variableID, 3, 'source.' . $sourceKey);
    }

    private static function assertTargetVariable(int $variableID, string $targetKey): void
    {
        self::assertVariableType($variableID, 0, 'target.' . $targetKey);
        $variable = IPS_GetVariable($variableID);
        if ((int) $variable['VariableCustomAction'] <= 0 && (int) $variable['VariableAction'] <= 0) {
            throw new RuntimeException('ControlLight target facade has no action: ' . $targetKey);
        }
    }

    private static function assertVariableType(int $variableID, int $expectedType, string $role): void
    {
        if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
            throw new InvalidArgumentException('Hue Wall variable does not exist: ' . $role);
        }
        $variable = IPS_GetVariable($variableID);
        if ((int) $variable['VariableType'] !== $expectedType) {
            throw new RuntimeException('Hue Wall variable type mismatch: ' . $role);
        }
    }

    /**
     * @param list<int> $desiredEventIDs
     */
    private static function deactivateObsoleteOwnedEvents(int $ownerScriptID, array $desiredEventIDs): void
    {
        $desired = array_fill_keys($desiredEventIDs, true);
        foreach (IPS_GetChildrenIDs($ownerScriptID) as $childID) {
            $object = IPS_GetObject($childID);
            $ident = (string) ($object['ObjectIdent'] ?? '');
            $owned = str_starts_with($ident, self::ACTION_EVENT_PREFIX)
                || str_starts_with($ident, self::FEEDBACK_EVENT_PREFIX);
            if ((int) $object['ObjectType'] !== 4 || !$owned || isset($desired[$childID])) {
                continue;
            }
            IPS_SetEventActive($childID, false);
        }
    }

    /** @param array<string, mixed> $source @return list<string> */
    private static function sourceTargetKeys(array $source): array
    {
        return array_values(array_unique([
            (string) $source['leftTargetKey'],
            (string) $source['rightTargetKey'],
        ]));
    }

    private static function debounceIdent(string $sourceKey, string $targetKey): string
    {
        return self::DEBOUNCE_PREFIX . strtoupper($sourceKey . '_' . $targetKey);
    }

    private static function validateOwnerScript(int $ownerScriptID): void
    {
        if ($ownerScriptID <= 0 || !IPS_ScriptExists($ownerScriptID)) {
            throw new InvalidArgumentException('Hue Wall owner script does not exist.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function statisticsDefinitions(): array
    {
        return [
            ['ident' => 'HWS_EXECUTIONS', 'name' => 'Hue Wall executions', 'type' => 1, 'position' => 920],
            ['ident' => 'HWS_ACTION_UPDATES', 'name' => 'Hue Wall action updates', 'type' => 1, 'position' => 930],
            ['ident' => 'HWS_COMMAND_ATTEMPTS', 'name' => 'Hue Wall command attempts', 'type' => 1, 'position' => 940],
            ['ident' => 'HWS_CONFIRMED', 'name' => 'Hue Wall confirmed', 'type' => 1, 'position' => 950],
            ['ident' => 'HWS_COMMAND_FAILURES', 'name' => 'Hue Wall command failures', 'type' => 1, 'position' => 960],
            ['ident' => 'HWS_CONFIRMATION_TIMEOUTS', 'name' => 'Hue Wall confirmation timeouts', 'type' => 1, 'position' => 970],
            ['ident' => 'HWS_DEBOUNCED', 'name' => 'Hue Wall debounced', 'type' => 1, 'position' => 980],
            ['ident' => 'HWS_IGNORED_ACTIONS', 'name' => 'Hue Wall ignored actions', 'type' => 1, 'position' => 990],
            ['ident' => 'HWS_FEEDBACKS', 'name' => 'Hue Wall feedbacks', 'type' => 1, 'position' => 1000],
            ['ident' => 'HWS_LAST_RUN', 'name' => 'Hue Wall last run', 'type' => 1, 'profile' => '~UnixTimestamp', 'position' => 1010],
            ['ident' => 'HWS_LAST_SUCCESS', 'name' => 'Hue Wall last success', 'type' => 1, 'profile' => '~UnixTimestamp', 'position' => 1020],
            ['ident' => 'HWS_LAST_FEEDBACK', 'name' => 'Hue Wall last feedback', 'type' => 1, 'profile' => '~UnixTimestamp', 'position' => 1030],
        ];
    }
}
