<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../../../helpers/object/EnsureEvent.php';
require_once __DIR__ . '/../../../helpers/object/EnsureLink.php';
require_once __DIR__ . '/../../../helpers/object/EnsureVariable.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ErrorRingBuffer.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Registry.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Statistics.php';
require_once __DIR__ . '/../../../helpers/variable/WaitForVariable.php';
require_once __DIR__ . '/ControlLightCore.php';

/**
 * IP-Symcon runtime candidate for ControlLight v2.
 *
 * Live deployment remains a separate, explicitly approved migration gate. The
 * runtime preserves existing user-facing names, positions and icons while it
 * manages functional contracts such as Idents, profiles, actions and events.
 */
final class ControlLightRuntime
{
    private const DIAGNOSTIC_ERROR_CAPACITY = 20;

    /**
     * @param array<string, mixed> $sourceIPS
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function run(int $ownerScriptID, array $sourceIPS, array $configuration): array
    {
        $normalized = ControlLightCore::normalizeConfiguration($configuration);
        $diagnostics = self::initializeDiagnostics($ownerScriptID, $normalized);
        \SAEF_IncrementStatistic($diagnostics['statisticIDs']['EXECUTIONS']);
        \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_RUN']);

        try {
            $resources = self::reconcileResources($ownerScriptID, $normalized);
            $sender = (string)($sourceIPS['SENDER'] ?? '');
            $sourceVariableID = (int)($sourceIPS['VARIABLE'] ?? 0);
            $value = $sourceIPS['VALUE'] ?? null;

            if ($sender === 'Execute' || $sender === 'RunScript') {
                self::syncAll($resources, $normalized);
                self::recordSuccess($diagnostics);

                return ['status' => 'synchronized', 'sender' => $sender];
            }

            if (in_array($sender, ['WebFront', 'VoiceControl', 'Action'], true)) {
                if (!self::userMayControl($normalized, $sender)) {
                    self::syncAll($resources, $normalized);

                    return ['status' => 'blocked_by_alarm', 'sender' => $sender];
                }

                $capability = self::capabilityForLocalVariable($sourceVariableID, $resources);
                if ($capability === null) {
                    throw new InvalidArgumentException('Action source is not an owned ControlLight variable.');
                }

                $result = self::dispatchLocalAction(
                    $ownerScriptID,
                    $capability,
                    $value,
                    $resources,
                    $normalized,
                    $diagnostics
                );
                self::recordSuccess($diagnostics);

                return $result + ['sender' => $sender];
            }

            if ($sender === 'Variable') {
                $targetCapability = self::capabilityForTargetVariable($sourceVariableID, $resources);
                if ($targetCapability !== null) {
                    self::syncCapability($targetCapability, $resources, $normalized);
                    self::recordSuccess($diagnostics);

                    return ['status' => 'feedback_synchronized', 'capability' => $targetCapability];
                }

                $external = self::externalTriggerForVariable($sourceVariableID, $resources);
                if ($external !== null) {
                    if ($external['respectAlarm'] === true && !self::userMayControl($normalized, 'External')) {
                        self::syncAll($resources, $normalized);

                        return ['status' => 'blocked_by_alarm', 'sender' => 'External'];
                    }

                    $state = self::externalTriggerState($external, $value, $resources);
                    $result = self::dispatchTargetAction(
                        $ownerScriptID,
                        'state',
                        $state,
                        $resources,
                        $normalized,
                        $diagnostics
                    );
                    self::recordSuccess($diagnostics);

                    return $result + ['sender' => 'External'];
                }

                return ['status' => 'ignored_variable'];
            }

            return ['status' => 'ignored_sender', 'sender' => $sender];
        } catch (Throwable $exception) {
            self::recordFailure($diagnostics, $exception, 'runtime');
            \IPS_LogMessage('SAEF ControlLight v2', 'Runtime failed: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $configuration Normalized configuration.
     *
     * @return array<string, mixed>
     */
    public static function reconcileResources(int $ownerScriptID, array $configuration): array
    {
        self::validateOwnerScript($ownerScriptID);
        $parentID = \IPS_GetParent($ownerScriptID);
        $targetRootID = self::resolveTargetRoot($parentID);
        $resources = [
            'ownerScriptID' => $ownerScriptID,
            'parentID' => $parentID,
            'targetRootID' => $targetRootID,
            'localVariableIDs' => [],
            'targetVariableIDs' => [],
            'externalTriggers' => [],
        ];

        foreach (ControlLightCore::capabilities() as $capability) {
            $definition = $configuration['capabilities'][$capability];
            if ($definition['enabled'] !== true) {
                self::deactivateLocalCapability($parentID, $definition);
                self::deactivateOwnedEvent($ownerScriptID, self::eventIdent($capability));
                continue;
            }

            $localVariableID = \SAEF_EnsureVariable(
                $parentID,
                $definition['localIdent'],
                $definition['localName'],
                $definition['localType'],
                $definition['profile'],
                $ownerScriptID,
                $definition['position'],
                null,
                false
            );
            $resources['localVariableIDs'][$capability] = $localVariableID;

            if ($targetRootID === null) {
                \IPS_SetHidden($localVariableID, true);
                self::deactivateOwnedEvent($ownerScriptID, self::eventIdent($capability));
                continue;
            }

            $targetVariableID = self::resolveTargetVariable(
                $targetRootID,
                $definition['targetIdent'],
                $definition['targetType']
            );
            if ($targetVariableID === null) {
                \IPS_SetHidden($localVariableID, true);
                self::deactivateOwnedEvent($ownerScriptID, self::eventIdent($capability));
                continue;
            }

            self::assertVariableHasAction($targetVariableID, $capability);
            $resources['targetVariableIDs'][$capability] = $targetVariableID;
            \IPS_SetHidden($localVariableID, false);

            \SAEF_EnsureTriggeredScriptEvent(
                $ownerScriptID,
                self::eventIdent($capability),
                self::eventIdent($capability),
                $ownerScriptID,
                $targetVariableID,
                $capability === 'state' ? 0 : 1,
                true,
                null,
                true,
                false
            );
        }

        foreach ($configuration['externalTriggers'] as $index => $externalTrigger) {
            $sourceVariableID = self::resolveExternalTriggerVariable($externalTrigger);
            $eventIdent = 'EV_EXTERNAL_' . (string)$index;
            \SAEF_EnsureTriggeredScriptEvent(
                $ownerScriptID,
                $eventIdent,
                $eventIdent,
                $ownerScriptID,
                $sourceVariableID,
                $externalTrigger['event'] === 'update' ? 0 : 1,
                true,
                null,
                true,
                false
            );
            $externalTrigger['resolvedVariableID'] = $sourceVariableID;
            $resources['externalTriggers'][] = $externalTrigger;
        }

        self::deactivateObsoleteExternalEvents($ownerScriptID, count($configuration['externalTriggers']));

        return $resources;
    }

    /**
     * Executes a confirmed target action. Public for deterministic runtime tests.
     *
     * @param array<string, mixed> $resources
     * @param array<string, mixed> $configuration Normalized configuration.
     * @param array<string, mixed> $diagnostics
     *
     * @return array<string, mixed>
     */
    public static function dispatchTargetAction(
        int $ownerScriptID,
        string $capability,
        mixed $localValue,
        array $resources,
        array $configuration,
        array $diagnostics
    ): array {
        $targetVariableID = $resources['targetVariableIDs'][$capability] ?? null;
        if (!is_int($targetVariableID)) {
            throw new RuntimeException('Target capability is unavailable: ' . $capability);
        }
        $expectedTargetValue = ControlLightCore::localToTarget($capability, $localValue, $configuration);
        $semaphoreName = 'SAEF_CONTROL_LIGHT_' . (string)$ownerScriptID;
        $semaphoreTimeout = $configuration['semaphore']['timeoutMilliseconds'];

        if (!\IPS_SemaphoreEnter($semaphoreName, $semaphoreTimeout)) {
            throw new RuntimeException('ControlLight semaphore timed out.');
        }

        try {
            $currentTargetValue = \GetValue($targetVariableID);
            if (
                ControlLightCore::targetValueMatches(
                    $capability,
                    $expectedTargetValue,
                    $currentTargetValue,
                    $configuration
                )
            ) {
                self::syncAll($resources, $configuration);

                return ['status' => 'already_confirmed', 'capability' => $capability];
            }

            if (!\RequestAction($targetVariableID, $expectedTargetValue)) {
                throw new RuntimeException('Target action rejected the requested value: ' . $capability);
            }
            \SAEF_IncrementStatistic($diagnostics['statisticIDs']['COMMANDS']);

            $confirmed = ControlLightCore::targetValueMatches(
                $capability,
                $expectedTargetValue,
                \GetValue($targetVariableID),
                $configuration
            );
            if (!$confirmed && $configuration['confirmation']['timeoutMilliseconds'] > 0) {
                $confirmed = \SAEF_WaitForVariable(
                    $targetVariableID,
                    $configuration['confirmation']['timeoutMilliseconds'],
                    $configuration['confirmation']['pollIntervalMilliseconds'],
                    null,
                    \SAEF_WAIT_UPDATED,
                    $configuration['confirmation']['pollIntervalMilliseconds'],
                    static fn(mixed $actual): bool => ControlLightCore::targetValueMatches(
                        $capability,
                        $expectedTargetValue,
                        $actual,
                        $configuration
                    )
                );
            }

            self::syncAll($resources, $configuration);
            if (!$confirmed) {
                \SAEF_IncrementStatistic($diagnostics['statisticIDs']['CONFIRMATION_TIMEOUTS']);
                throw new RuntimeException('Authoritative feedback confirmation timed out: ' . $capability);
            }

            \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_FEEDBACK']);

            return ['status' => 'confirmed', 'capability' => $capability];
        } finally {
            if (!\IPS_SemaphoreLeave($semaphoreName)) {
                \IPS_LogMessage('SAEF ControlLight v2', 'Unable to release ControlLight semaphore.');
            }
        }
    }

    /**
     * @param array<string, mixed> $configuration Normalized configuration.
     *
     * @return array<string, mixed>
     */
    public static function initializeDiagnostics(int $ownerScriptID, array $configuration): array
    {
        self::validateOwnerScript($ownerScriptID);
        $configurationHash = \SAEF_CreateConfigurationHash($configuration);
        $registryID = \SAEF_EnsureRegistryVariable(
            $ownerScriptID,
            'CONTROL_LIGHT_REGISTRY',
            'ControlLight Registry',
            900,
            'Database',
            false
        );
        $errorRingBufferID = \SAEF_EnsureErrorRingBufferVariable(
            $ownerScriptID,
            'CONTROL_LIGHT_ERROR_HISTORY',
            'ControlLight Error History',
            920,
            'Warning',
            false
        );
        $statisticIDs = \SAEF_EnsureStatisticsVariables(
            $ownerScriptID,
            self::statisticsDefinitions(),
            false
        );
        $registry = \SAEF_ReadRegistry($registryID);
        $updatedRegistry = $registry;
        $updatedRegistry['version'] = $configuration['version'];
        $updatedRegistry['configurationHash'] = $configurationHash;
        $updatedRegistry['brightnessSemantics'] = $configuration['brightnessSemantics'];
        if ($updatedRegistry !== $registry) {
            \SAEF_WriteRegistry($registryID, $updatedRegistry);
        }

        return [
            'registryID' => $registryID,
            'errorRingBufferID' => $errorRingBufferID,
            'statisticIDs' => $statisticIDs,
            'configurationHash' => $configurationHash,
        ];
    }

    /** @param array<string, mixed> $resources @param array<string, mixed> $configuration */
    private static function dispatchLocalAction(
        int $ownerScriptID,
        string $capability,
        mixed $value,
        array $resources,
        array $configuration,
        array $diagnostics
    ): array {
        if ($capability === 'brightness' && (int)$value <= 0 && isset($resources['targetVariableIDs']['state'])) {
            return self::dispatchTargetAction(
                $ownerScriptID,
                'state',
                false,
                $resources,
                $configuration,
                $diagnostics
            );
        }

        return self::dispatchTargetAction(
            $ownerScriptID,
            $capability,
            $value,
            $resources,
            $configuration,
            $diagnostics
        );
    }

    /** @param array<string, mixed> $resources @param array<string, mixed> $configuration */
    private static function syncAll(array $resources, array $configuration): void
    {
        foreach (ControlLightCore::capabilities() as $capability) {
            if (isset($resources['localVariableIDs'][$capability], $resources['targetVariableIDs'][$capability])) {
                self::syncCapability($capability, $resources, $configuration);
            }
        }
    }

    /** @param array<string, mixed> $resources @param array<string, mixed> $configuration */
    private static function syncCapability(string $capability, array $resources, array $configuration): void
    {
        $localVariableID = $resources['localVariableIDs'][$capability] ?? null;
        $targetVariableID = $resources['targetVariableIDs'][$capability] ?? null;
        if (!is_int($localVariableID) || !is_int($targetVariableID)) {
            return;
        }

        $targetState = null;
        if (isset($resources['targetVariableIDs']['state'])) {
            $targetState = (bool)\GetValue($resources['targetVariableIDs']['state']);
        }
        $localValue = ControlLightCore::targetToLocal(
            $capability,
            \GetValue($targetVariableID),
            $configuration,
            $targetState
        );
        if (\GetValue($localVariableID) !== $localValue) {
            \SetValue($localVariableID, $localValue);
        }

        if ($capability === 'state' && $configuration['brightnessSemantics'] === ControlLightCore::BRIGHTNESS_EFFECTIVE) {
            if (isset($resources['localVariableIDs']['brightness'], $resources['targetVariableIDs']['brightness'])) {
                $brightness = ControlLightCore::targetToLocal(
                    'brightness',
                    \GetValue($resources['targetVariableIDs']['brightness']),
                    $configuration,
                    (bool)$localValue
                );
                if (\GetValue($resources['localVariableIDs']['brightness']) !== $brightness) {
                    \SetValue($resources['localVariableIDs']['brightness'], $brightness);
                }
            }
        }
    }

    private static function resolveTargetRoot(int $parentID): ?int
    {
        $linkID = @\IPS_GetObjectIDByIdent('LINK_TARGET_PARENT', $parentID);
        if ($linkID === false) {
            // Creation default only. Subsequent target selection remains user configuration.
            \SAEF_EnsureLink(
                $parentID,
                'LINK_TARGET_PARENT',
                'ControlLight Target',
                $parentID,
                100,
                null,
                true
            );

            return null;
        }
        $object = \IPS_GetObject($linkID);
        if ($object['ObjectType'] !== 6) {
            throw new RuntimeException('LINK_TARGET_PARENT exists but is not a link.');
        }
        $targetID = \IPS_GetLink($linkID)['TargetID'];
        if ($targetID <= 0 || $targetID === $parentID || !\IPS_ObjectExists($targetID)) {
            return null;
        }

        return $targetID;
    }

    private static function resolveTargetVariable(int $parentID, string $ident, int $expectedType): ?int
    {
        $variableID = @\IPS_GetObjectIDByIdent($ident, $parentID);
        if ($variableID === false) {
            return null;
        }
        if (!\IPS_VariableExists($variableID)) {
            throw new RuntimeException('Target Ident exists but is not a variable: ' . $ident);
        }
        $variable = \IPS_GetVariable($variableID);
        if ($variable['VariableType'] !== $expectedType) {
            throw new RuntimeException('Target variable type mismatch: ' . $ident);
        }

        return $variableID;
    }

    private static function assertVariableHasAction(int $variableID, string $capability): void
    {
        $variable = \IPS_GetVariable($variableID);
        if ($variable['VariableCustomAction'] <= 0 && $variable['VariableAction'] <= 0) {
            throw new RuntimeException('Target variable has no action: ' . $capability);
        }
    }

    /** @param array<string, mixed> $externalTrigger */
    private static function resolveExternalTriggerVariable(array $externalTrigger): int
    {
        $sourceID = $externalTrigger['sourceID'];
        if (!\IPS_ObjectExists($sourceID)) {
            throw new InvalidArgumentException('External trigger source does not exist.');
        }
        $object = \IPS_GetObject($sourceID);
        if ($object['ObjectType'] === 2) {
            return $sourceID;
        }
        if ($object['ObjectType'] === 6) {
            $targetID = \IPS_GetLink($sourceID)['TargetID'];
            if (\IPS_VariableExists($targetID)) {
                return $targetID;
            }
        }
        throw new InvalidArgumentException('External trigger source is neither a variable nor a valid link.');
    }

    /** @param array<string, mixed> $resources */
    private static function capabilityForLocalVariable(int $variableID, array $resources): ?string
    {
        foreach ($resources['localVariableIDs'] as $capability => $candidateID) {
            if ($candidateID === $variableID) {
                return $capability;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $resources */
    private static function capabilityForTargetVariable(int $variableID, array $resources): ?string
    {
        foreach ($resources['targetVariableIDs'] as $capability => $candidateID) {
            if ($candidateID === $variableID) {
                return $capability;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $resources @return array<string, mixed>|null */
    private static function externalTriggerForVariable(int $variableID, array $resources): ?array
    {
        foreach ($resources['externalTriggers'] as $trigger) {
            if ($trigger['resolvedVariableID'] === $variableID) {
                return $trigger;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $trigger @param array<string, mixed> $resources */
    private static function externalTriggerState(array $trigger, mixed $value, array $resources): bool
    {
        $stateTargetID = $resources['targetVariableIDs']['state'] ?? null;
        if (!is_int($stateTargetID)) {
            throw new RuntimeException('External trigger requires an available state target.');
        }

        $state = match ($trigger['action']) {
            'on' => true,
            'off' => false,
            'toggle' => !(bool)\GetValue($stateTargetID),
            'value' => self::externalValueToBoolean($value),
            default => throw new RuntimeException('Unsupported external trigger action.'),
        };

        return $trigger['invert'] === true ? !$state : $state;
    }

    private static function externalValueToBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float)$value !== 0.0;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'on', 'ein', 'an', 'yes', 'ja'], true);
    }

    /** @param array<string, mixed> $configuration */
    private static function userMayControl(array $configuration, string $sender): bool
    {
        if ($sender === 'Action') {
            return true;
        }
        $alarmID = $configuration['alarmID'];
        if ($alarmID <= 0 || !\IPS_VariableExists($alarmID)) {
            return true;
        }
        $alarmValue = (bool)\GetValue($alarmID);
        return $configuration['alarmIDIsAlarmActive'] ? !$alarmValue : $alarmValue;
    }

    private static function eventIdent(string $capability): string
    {
        return match ($capability) {
            'state' => 'EV_TARGET_STATE',
            'brightness' => 'EV_TARGET_DIM',
            'colorTemperature' => 'EV_TARGET_TEMP',
            'color' => 'EV_TARGET_COLOR',
            default => throw new InvalidArgumentException('Unsupported capability: ' . $capability),
        };
    }

    private static function deactivateOwnedEvent(int $ownerScriptID, string $ident): void
    {
        $eventID = @\IPS_GetObjectIDByIdent($ident, $ownerScriptID);
        if ($eventID === false) {
            return;
        }
        if (!\IPS_EventExists($eventID)) {
            throw new RuntimeException('Owned event Ident exists but is not an event: ' . $ident);
        }
        \IPS_SetEventActive($eventID, false);
    }

    /** @param array<string, mixed> $definition */
    private static function deactivateLocalCapability(int $parentID, array $definition): void
    {
        $variableID = @\IPS_GetObjectIDByIdent($definition['localIdent'], $parentID);
        if ($variableID === false) {
            return;
        }
        if (!\IPS_VariableExists($variableID)) {
            throw new RuntimeException(
                'Disabled local capability Ident exists but is not a variable: ' . $definition['localIdent']
            );
        }
        $variable = \IPS_GetVariable($variableID);
        if ($variable['VariableType'] !== $definition['localType']) {
            throw new RuntimeException(
                'Disabled local capability has incompatible type: ' . $definition['localIdent']
            );
        }
        \IPS_SetVariableCustomAction($variableID, 0);
        \IPS_SetHidden($variableID, true);
    }

    private static function deactivateObsoleteExternalEvents(int $ownerScriptID, int $desiredCount): void
    {
        foreach (\IPS_GetChildrenIDs($ownerScriptID) as $childID) {
            $object = \IPS_GetObject($childID);
            $ident = (string)($object['ObjectIdent'] ?? '');
            if ($object['ObjectType'] !== 4 || !str_starts_with($ident, 'EV_EXTERNAL_')) {
                continue;
            }
            $index = filter_var(substr($ident, strlen('EV_EXTERNAL_')), FILTER_VALIDATE_INT);
            if (is_int($index) && $index >= $desiredCount) {
                \IPS_SetEventActive($childID, false);
            }
        }
    }

    private static function validateOwnerScript(int $ownerScriptID): void
    {
        if ($ownerScriptID <= 0 || !\IPS_ScriptExists($ownerScriptID)) {
            throw new InvalidArgumentException('ControlLight owner script does not exist.');
        }
    }

    /** @return list<array<string, mixed>> */
    private static function statisticsDefinitions(): array
    {
        return [
            ['ident' => 'EXECUTIONS', 'name' => 'Executions', 'type' => 1, 'position' => 100],
            ['ident' => 'COMMANDS', 'name' => 'Commands', 'type' => 1, 'position' => 110],
            ['ident' => 'SUCCESSES', 'name' => 'Successes', 'type' => 1, 'position' => 120],
            ['ident' => 'ERRORS', 'name' => 'Errors', 'type' => 1, 'position' => 130],
            [
                'ident' => 'CONFIRMATION_TIMEOUTS',
                'name' => 'Confirmation Timeouts',
                'type' => 1,
                'position' => 140,
            ],
            ['ident' => 'LAST_RUN', 'name' => 'Last Run', 'type' => 1, 'profile' => '~UnixTimestamp', 'position' => 150],
            [
                'ident' => 'LAST_SUCCESS',
                'name' => 'Last Success',
                'type' => 1,
                'profile' => '~UnixTimestamp',
                'position' => 160,
            ],
            [
                'ident' => 'LAST_FEEDBACK',
                'name' => 'Last Feedback',
                'type' => 1,
                'profile' => '~UnixTimestamp',
                'position' => 170,
            ],
        ];
    }

    /** @param array<string, mixed> $diagnostics */
    private static function recordSuccess(array $diagnostics): void
    {
        \SAEF_IncrementStatistic($diagnostics['statisticIDs']['SUCCESSES']);
        \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_SUCCESS']);
    }

    /** @param array<string, mixed> $diagnostics */
    private static function recordFailure(array $diagnostics, Throwable $exception, string $phase): void
    {
        try {
            \SAEF_IncrementStatistic($diagnostics['statisticIDs']['ERRORS']);
        } catch (Throwable $diagnosticException) {
            \IPS_LogMessage(
                'SAEF ControlLight v2',
                'Unable to update failure statistic: ' . $diagnosticException->getMessage()
            );
        }

        try {
            \SAEF_AppendErrorRingBufferEntry(
                $diagnostics['errorRingBufferID'],
                'ControlLight failure during ' . $phase . '.',
                self::DIAGNOSTIC_ERROR_CAPACITY,
                ['phase' => $phase, 'type' => $exception::class]
            );
        } catch (Throwable $diagnosticException) {
            \IPS_LogMessage(
                'SAEF ControlLight v2',
                'Unable to update error history: ' . $diagnosticException->getMessage()
            );
        }
    }
}
