<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use RuntimeException;
use Throwable;

require_once __DIR__ . '/../../../helpers/object/EnsureEvent.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ErrorRingBuffer.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Registry.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Statistics.php';
require_once __DIR__ . '/../../../helpers/variable/WaitForVariable.php';
require_once __DIR__ . '/ManualOnPulseOffCore.php';

/**
 * Authoritative adapter for a manually activated lamp with pulse-off supply.
 */
final class ManualOnPulseOffRuntime
{
    private const ERROR_CAPACITY = 20;

    /**
     * @param array<string, mixed> $sourceIPS
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function run(
        int $ownerScriptID,
        array $sourceIPS,
        array $configuration
    ): array {
        $normalized = ManualOnPulseOffCore::normalizeConfiguration($configuration);
        $resources = self::reconcileResources($ownerScriptID, $normalized);
        $diagnostics = self::initializeDiagnostics($ownerScriptID, $normalized);
        \SAEF_IncrementStatistic($diagnostics['statistics']['EXECUTIONS']);
        \SAEF_SetStatisticTimestamp($diagnostics['statistics']['LAST_RUN']);

        try {
            $sender = (string)($sourceIPS['SENDER'] ?? '');
            if (in_array($sender, ['Execute', 'RunScript'], true)) {
                self::syncState($resources, $normalized);
                self::recordOutcome($diagnostics, 'synchronized');

                return ['status' => 'synchronized'];
            }

            if ($sender === 'Variable') {
                if ((int)($sourceIPS['VARIABLE'] ?? 0) !== $normalized['powerVariableID']) {
                    return ['status' => 'ignored_variable'];
                }
                self::syncState($resources, $normalized);
                self::recordOutcome($diagnostics, 'feedback_synchronized');

                return ['status' => 'feedback_synchronized'];
            }

            if (in_array($sender, ['WebFront', 'VoiceControl', 'Action'], true)) {
                if ((int)($sourceIPS['VARIABLE'] ?? 0) !== $resources['stateVariableID']) {
                    throw new RuntimeException('Action source is not the adapter-owned state variable.');
                }

                $result = self::dispatch(
                    $ownerScriptID,
                    (bool)($sourceIPS['VALUE'] ?? false),
                    $resources,
                    $normalized,
                    $diagnostics
                );
                self::recordOutcome($diagnostics, (string)$result['status']);

                return $result;
            }

            return ['status' => 'ignored_sender', 'sender' => $sender];
        } catch (Throwable $exception) {
            self::recordFailure($diagnostics, $exception);
            if (in_array((string)($sourceIPS['SENDER'] ?? ''), ['WebFront', 'VoiceControl', 'Action'], true)) {
                return [
                    'status' => 'command_failed',
                    'failureClass' => 'adapter_runtime_failure',
                ];
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $resources
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $diagnostics
     *
     * @return array<string, mixed>
     */
    public static function dispatch(
        int $ownerScriptID,
        bool $requestedState,
        array $resources,
        array $configuration,
        array $diagnostics
    ): array {
        $semaphore = 'SAEF_MANUAL_ON_PULSE_OFF_' . $ownerScriptID;
        if (!\IPS_SemaphoreEnter($semaphore, $configuration['semaphoreTimeoutMilliseconds'])) {
            throw new RuntimeException('Pulse-off adapter semaphore timed out.');
        }

        try {
            $observedState = self::readObservedState($configuration);
            $plan = ManualOnPulseOffCore::plan($requestedState, $observedState);

            if ($plan === 'already_confirmed') {
                self::syncState($resources, $configuration);

                return ['status' => 'already_confirmed'];
            }
            if ($plan === 'manual_activation_required') {
                \SAEF_IncrementStatistic($diagnostics['statistics']['MANUAL_ACTIVATION_REQUIRED']);

                return [
                    'status' => 'command_failed',
                    'failureClass' => 'manual_activation_required',
                ];
            }
            if ($plan === 'observe_before_idempotent_off') {
                $becameActive = self::waitForPowerState(
                    true,
                    $configuration['observationMilliseconds'],
                    $configuration
                );
                if (!$becameActive) {
                    self::syncState($resources, $configuration);

                    return ['status' => 'already_confirmed'];
                }
            }

            if (!\RequestAction($configuration['relayVariableID'], false)) {
                throw new RuntimeException('Relay rejected the pulse-off request.');
            }
            \SAEF_IncrementStatistic($diagnostics['statistics']['PULSE_COMMANDS']);

            $deadline = microtime(true)
                + ($configuration['confirmationTimeoutMilliseconds'] / 1000);
            $powerOff = self::waitForPowerState(
                false,
                self::remainingMilliseconds($deadline),
                $configuration
            );
            self::syncState($resources, $configuration);
            if (!$powerOff) {
                \SAEF_IncrementStatistic($diagnostics['statistics']['CONFIRMATION_TIMEOUTS']);
                throw new RuntimeException('Lamp power did not confirm the off state.');
            }

            $relayRestored = self::waitForRelayRestored(
                self::remainingMilliseconds($deadline),
                $configuration
            );
            if (!$relayRestored) {
                \SAEF_IncrementStatistic($diagnostics['statistics']['CONFIRMATION_TIMEOUTS']);
                throw new RuntimeException('Supply relay was not restored after the off pulse.');
            }

            \SAEF_SetStatisticTimestamp($diagnostics['statistics']['LAST_FEEDBACK']);

            return ['status' => 'confirmed'];
        } finally {
            if (!\IPS_SemaphoreLeave($semaphore)) {
                \IPS_LogMessage(
                    'SAEF ManualOnPulseOff',
                    'Unable to release pulse-off adapter semaphore.'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    private static function reconcileResources(int $ownerScriptID, array $configuration): array
    {
        if ($ownerScriptID <= 0 || !\IPS_ScriptExists($ownerScriptID)) {
            throw new RuntimeException('Pulse-off adapter owner script does not exist.');
        }
        $stateVariableID = \IPS_GetParent($ownerScriptID);
        if (
            !\IPS_VariableExists($stateVariableID)
            || \IPS_GetVariable($stateVariableID)['VariableType'] !== 0
        ) {
            throw new RuntimeException('Pulse-off adapter owner must be below a Boolean state variable.');
        }
        if (
            !\IPS_VariableExists($configuration['powerVariableID'])
            || !in_array(
                \IPS_GetVariable($configuration['powerVariableID'])['VariableType'],
                [1, 2],
                true
            )
        ) {
            throw new RuntimeException('Power feedback variable is missing or non-numeric.');
        }
        if (
            !\IPS_VariableExists($configuration['relayVariableID'])
            || \IPS_GetVariable($configuration['relayVariableID'])['VariableType'] !== 0
        ) {
            throw new RuntimeException('Relay variable is missing or non-Boolean.');
        }
        $relay = \IPS_GetVariable($configuration['relayVariableID']);
        if (($relay['VariableCustomAction'] ?: $relay['VariableAction']) <= 0) {
            throw new RuntimeException('Relay variable has no action.');
        }

        $eventID = \SAEF_EnsureTriggeredScriptEvent(
            $ownerScriptID,
            'EV_POWER_FEEDBACK',
            'Power Feedback',
            $ownerScriptID,
            $configuration['powerVariableID'],
            1,
            true,
            null,
            true,
            false
        );

        return [
            'stateVariableID' => $stateVariableID,
            'powerFeedbackEventID' => $eventID,
        ];
    }

    /** @param array<string, mixed> $configuration */
    private static function readObservedState(array $configuration): bool
    {
        return ManualOnPulseOffCore::isLampOn(
            \GetValue($configuration['powerVariableID']),
            $configuration['powerOnThreshold']
        );
    }

    /** @param array<string, mixed> $configuration */
    private static function waitForPowerState(
        bool $expectedState,
        int $timeoutMilliseconds,
        array $configuration
    ): bool {
        if (self::readObservedState($configuration) === $expectedState) {
            return true;
        }
        if ($timeoutMilliseconds <= 0) {
            return false;
        }

        return \SAEF_WaitForVariable(
            $configuration['powerVariableID'],
            $timeoutMilliseconds,
            min($configuration['pollIntervalMilliseconds'], $timeoutMilliseconds),
            null,
            \SAEF_WAIT_UPDATED,
            0,
            static fn(mixed $value): bool => ManualOnPulseOffCore::isLampOn(
                $value,
                $configuration['powerOnThreshold']
            ) === $expectedState
        );
    }

    /** @param array<string, mixed> $configuration */
    private static function waitForRelayRestored(
        int $timeoutMilliseconds,
        array $configuration
    ): bool {
        if ((bool)\GetValue($configuration['relayVariableID']) === true) {
            return true;
        }
        if ($timeoutMilliseconds <= 0) {
            return false;
        }

        return \SAEF_WaitForVariable(
            $configuration['relayVariableID'],
            $timeoutMilliseconds,
            min($configuration['pollIntervalMilliseconds'], $timeoutMilliseconds),
            true,
            \SAEF_WAIT_UPDATED
        );
    }

    /** @param array<string, mixed> $resources @param array<string, mixed> $configuration */
    private static function syncState(array $resources, array $configuration): void
    {
        $state = self::readObservedState($configuration);
        if ((bool)\GetValue($resources['stateVariableID']) !== $state) {
            \SetValue($resources['stateVariableID'], $state);
        }
    }

    private static function remainingMilliseconds(float $deadline): int
    {
        return max(0, (int)ceil(($deadline - microtime(true)) * 1000));
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    private static function initializeDiagnostics(
        int $ownerScriptID,
        array $configuration
    ): array {
        $registryID = \SAEF_EnsureRegistryVariable(
            $ownerScriptID,
            'REGISTRY',
            'Registry',
            100,
            null,
            false
        );
        $errorID = \SAEF_EnsureErrorRingBufferVariable(
            $ownerScriptID,
            'ERROR_HISTORY',
            'Error History',
            110,
            null,
            false
        );
        $statistics = \SAEF_EnsureStatisticsVariables(
            $ownerScriptID,
            [
                ['ident' => 'EXECUTIONS', 'name' => 'Executions', 'type' => 1],
                ['ident' => 'PULSE_COMMANDS', 'name' => 'Pulse Commands', 'type' => 1],
                [
                    'ident' => 'MANUAL_ACTIVATION_REQUIRED',
                    'name' => 'Manual Activation Required',
                    'type' => 1,
                ],
                [
                    'ident' => 'CONFIRMATION_TIMEOUTS',
                    'name' => 'Confirmation Timeouts',
                    'type' => 1,
                ],
                ['ident' => 'ERRORS', 'name' => 'Errors', 'type' => 1],
                [
                    'ident' => 'LAST_RUN',
                    'name' => 'Last Run',
                    'type' => 1,
                    'profile' => '~UnixTimestamp',
                ],
                [
                    'ident' => 'LAST_FEEDBACK',
                    'name' => 'Last Feedback',
                    'type' => 1,
                    'profile' => '~UnixTimestamp',
                ],
            ],
            false
        );
        \SAEF_WriteRegistry($registryID, [
            'version' => $configuration['version'],
            'configurationHash' => \SAEF_CreateConfigurationHash($configuration),
            'lastOutcome' => \SAEF_ReadRegistry($registryID)['lastOutcome'] ?? null,
        ]);

        return [
            'registryID' => $registryID,
            'errorID' => $errorID,
            'statistics' => $statistics,
        ];
    }

    /** @param array<string, mixed> $diagnostics */
    private static function recordOutcome(array $diagnostics, string $outcome): void
    {
        $registry = \SAEF_ReadRegistry($diagnostics['registryID']);
        $registry['lastOutcome'] = $outcome;
        $registry['lastOutcomeAt'] = time();
        \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
    }

    /** @param array<string, mixed> $diagnostics */
    private static function recordFailure(array $diagnostics, Throwable $exception): void
    {
        try {
            \SAEF_IncrementStatistic($diagnostics['statistics']['ERRORS']);
            \SAEF_AppendErrorRingBufferEntry(
                $diagnostics['errorID'],
                'Pulse-off adapter failure.',
                self::ERROR_CAPACITY,
                ['type' => $exception::class, 'message' => $exception->getMessage()]
            );
            self::recordOutcome($diagnostics, 'command_failed');
        } catch (Throwable $diagnosticFailure) {
            \IPS_LogMessage(
                'SAEF ManualOnPulseOff',
                'Unable to record adapter failure: ' . $diagnosticFailure->getMessage()
            );
        }
        \IPS_LogMessage('SAEF ManualOnPulseOff', $exception->getMessage());
    }
}
