<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\MqttDiscoveryExporter;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../../../helpers/object/EnsureCategory.php';
require_once __DIR__ . '/../../../helpers/object/EnsureInstance.php';
require_once __DIR__ . '/../../../helpers/object/EnsureEvent.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Registry.php';
require_once __DIR__ . '/../../../helpers/diagnostics/Statistics.php';
require_once __DIR__ . '/../../../helpers/diagnostics/ErrorRingBuffer.php';
require_once __DIR__ . '/../../../helpers/variable/WaitForVariable.php';
require_once __DIR__ . '/MqttDiscoveryExporterCore.php';

/**
 * IP-Symcon runtime adapter for the MQTT Discovery Exporter candidate.
 *
 * The candidate composes diagnostics, reconciliation, MQTT publication,
 * indexed dispatch and ownership-exact cleanup. Live deployment remains a
 * separate gate outside this runtime adapter.
 */
final class MqttDiscoveryExporterRuntime
{
    private const MQTT_CLIENT_DEVICE_MODULE_ID = '{91D174F2-AE0F-B8D8-5EF4-6232B9083CCF}';
    private const MQTT_CLIENT_GATEWAY_MODULE_ID = '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}';
    private const MQTT_SERVER_DEVICE_MODULE_ID = '{01C00ADD-D04E-452E-B66A-D253278743FE}';
    private const MQTT_SERVER_GATEWAY_MODULE_ID = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    private const REGISTRY_SCHEMA_VERSION = 1;
    private const ERROR_CAPACITY = 20;

    /**
     * Validates and ensures the desired reconcile resources without publishing
     * or removing anything.
     *
     * @param array<string, mixed> $configuration Validated, normalized core configuration.
     *
     * @return array{
     *     diagnostics: array<string, mixed>,
     *     publishPlan: array<string, array<string, mixed>>,
     *     summary: array{entities: int, commandAdapters: int, stateEvents: int, messagesToPublish: int}
     * }
     */
    public static function prepareReconcile(int $ownerScriptID, array $configuration): array
    {
        $diagnostics = self::initializeDiagnostics($ownerScriptID, $configuration);

        return self::prepareReconcileWithDiagnostics(
            $ownerScriptID,
            $configuration,
            $diagnostics,
            true
        );
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $diagnostics
     *
     * @return array{
     *     diagnostics: array<string, mixed>,
     *     publishPlan: array<string, array<string, mixed>>,
     *     summary: array{entities: int, commandAdapters: int, stateEvents: int, messagesToPublish: int}
     * }
     */
    private static function prepareReconcileWithDiagnostics(
        int $ownerScriptID,
        array $configuration,
        array $diagnostics,
        bool $recordPreparationFailure
    ): array {

        try {
            $preparedEntities = self::validateAndPrepareEntities($configuration);
            $transport = self::mqttTransportContract($configuration);
            self::assertCleanupNotRequired(
                $diagnostics['registry']['managedEntities'],
                $preparedEntities,
                $transport['deviceModuleID']
            );

            $tree = self::ensureResourceTree(
                $ownerScriptID,
                $preparedEntities,
                $diagnostics['registry']
            );
            if ($tree['registry'] !== $diagnostics['registry']) {
                \SAEF_WriteRegistry($diagnostics['registryID'], $tree['registry']);
            }
            $diagnostics['registry'] = $tree['registry'];

            $managedEntities = $diagnostics['registry']['managedEntities'];
            foreach ($preparedEntities as $entityKey => $prepared) {
                $deviceKey = self::preparedDeviceKey($prepared);
                $managedEntities[$entityKey] = self::plannedManagedEntityMetadata(
                    $prepared,
                    $managedEntities[$entityKey] ?? [],
                    $transport['deviceModuleID'],
                    $tree['devices'][$deviceKey]
                );
            }
            ksort($managedEntities);
            $plannedRegistry = $diagnostics['registry'];
            $plannedRegistry['managedEntities'] = $managedEntities;
            if ($plannedRegistry !== $diagnostics['registry']) {
                \SAEF_WriteRegistry($diagnostics['registryID'], $plannedRegistry);
            }
            $diagnostics['registry'] = $plannedRegistry;

            $commandIndex = [];
            $stateIndex = [];
            $publishPlan = [];
            $commandAdapterCount = 0;
            $stateEventCount = 0;
            $messagesToPublish = 0;

            foreach ($preparedEntities as $entityKey => $prepared) {
                $deviceKey = self::preparedDeviceKey($prepared);
                $resources = self::ensureEntityResources(
                    $ownerScriptID,
                    $configuration,
                    $prepared,
                    $tree['devices'][$deviceKey]
                );
                $previous = $managedEntities[$entityKey] ?? [];
                $discoveryChanged = ($previous['discoveryHash'] ?? null) !== $prepared['discoveryHash']
                    || ($previous['discoveryPublished'] ?? false) !== true;
                $runtimeChanged = ($previous['runtimeHash'] ?? null) !== $prepared['runtimeHash']
                    || ($previous['runtimePublished'] ?? false) !== true;

                foreach ($resources['commandVariableIDs'] as $commandType => $variableID) {
                    $commandIndex[(string)$variableID] = [
                        'entityKey' => $entityKey,
                        'commandType' => $commandType,
                    ];
                }

                foreach ($resources['stateVariableIDs'] as $capability => $variableID) {
                    $stateIndex[(string)$variableID] ??= [];
                    $stateIndex[(string)$variableID][] = [
                        'entityKey' => $entityKey,
                        'capability' => $capability,
                    ];
                }

                $discoveryMessages = $discoveryChanged ? [[
                    'topic' => $prepared['discoveryTopic'],
                    'payload' => $prepared['discoveryPayload'],
                    'retain' => true,
                ]] : [];
                $runtimeMessages = [];
                if ($runtimeChanged) {
                    foreach ($prepared['runtimeTopics'] as $topic => $payload) {
                        $runtimeMessages[] = [
                            'topic' => $topic,
                            'payload' => $payload,
                            'retain' => $configuration['defaults']['retain'],
                        ];
                    }
                }

                $publishPlan[$entityKey] = [
                    'discoveryChanged' => $discoveryChanged,
                    'runtimeChanged' => $runtimeChanged,
                    'discoveryMessages' => $discoveryMessages,
                    'runtimeMessages' => $runtimeMessages,
                ];
                $messagesToPublish += count($discoveryMessages) + count($runtimeMessages);

                $managedEntities[$entityKey] = self::managedEntityMetadata(
                    $prepared,
                    $resources,
                    $previous
                );
                $commandAdapterCount += count($resources['commandVariableIDs']);
                $stateEventCount += count($resources['stateEventIDs']);
            }

            ksort($commandIndex);
            ksort($stateIndex);
            ksort($managedEntities);
            ksort($publishPlan);

            $registry = $diagnostics['registry'];
            $registry['managedEntities'] = $managedEntities;
            $registry['commandIndex'] = $commandIndex;
            $registry['stateIndex'] = $stateIndex;
            $registry['preparedConfigurationHash'] = $diagnostics['configurationHash'];
            if ($registry !== $diagnostics['registry']) {
                \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
            }
            $diagnostics['registry'] = $registry;

            return [
                'diagnostics' => $diagnostics,
                'publishPlan' => $publishPlan,
                'summary' => [
                    'entities' => count($preparedEntities),
                    'commandAdapters' => $commandAdapterCount,
                    'stateEvents' => $stateEventCount,
                    'messagesToPublish' => $messagesToPublish,
                ],
            ];
        } catch (Throwable $exception) {
            if ($recordPreparationFailure) {
                self::recordFailure(
                    $diagnostics['errorRingBufferID'],
                    $diagnostics['statisticIDs'],
                    $exception,
                    'reconcile_preparation'
                );
                \IPS_LogMessage(
                    'SAEF MQTT Discovery Exporter',
                    'Reconcile preparation failed: ' . $exception->getMessage()
                );
            }

            throw $exception;
        }
    }

    /**
     * Executes the prepared retained MQTT publication plan without cleanup.
     *
     * @param array<string, mixed> $configuration Validated, normalized core configuration.
     *
     * @return array{
     *     preparation: array<string, mixed>,
     *     diagnostics: array<string, mixed>,
     *     summary: array{publishers: int, publishedMessages: int, skippedChannels: int}
     * }
     */
    public static function executeReconcileWithoutCleanup(int $ownerScriptID, array $configuration): array
    {
        return self::executeReconcile($ownerScriptID, $configuration, false);
    }

    /**
     * Executes ownership-exact cleanup before reconciling and publishing the
     * desired configuration.
     *
     * @param array<string, mixed> $configuration Validated, normalized core configuration.
     *
     * @return array{
     *     preparation: array<string, mixed>,
     *     diagnostics: array<string, mixed>,
     *     cleanup: array{entities: int, events: int, instances: int, categories: int, retainedTopics: int},
     *     summary: array{publishers: int, publishedMessages: int, skippedChannels: int}
     * }
     */
    public static function executeReconcileWithCleanup(int $ownerScriptID, array $configuration): array
    {
        return self::executeReconcile($ownerScriptID, $configuration, true);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    private static function executeReconcile(
        int $ownerScriptID,
        array $configuration,
        bool $cleanupEnabled
    ): array {
        $diagnostics = self::initializeDiagnostics($ownerScriptID, $configuration);
        \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_RUN']);
        \SAEF_IncrementStatistic($diagnostics['statisticIDs']['EXECUTIONS']);

        $semaphoreName = 'SAEF_MQTT_EXPORTER_PUBLISH_' . $ownerScriptID;
        if (!\IPS_SemaphoreEnter($semaphoreName, 5000)) {
            $exception = new RuntimeException('MQTT publication semaphore timed out.');
            self::recordFailure(
                $diagnostics['errorRingBufferID'],
                $diagnostics['statisticIDs'],
                $exception,
                'reconcile_lock'
            );
            \IPS_LogMessage('SAEF MQTT Discovery Exporter', $exception->getMessage());
            throw $exception;
        }

        try {
            $cleanup = [
                'entities' => 0,
                'events' => 0,
                'instances' => 0,
                'categories' => 0,
                'retainedTopics' => 0,
            ];
            if ($cleanupEnabled) {
                $cleanupResult = self::cleanupManagedResources(
                    $ownerScriptID,
                    $configuration,
                    $diagnostics
                );
                $diagnostics = $cleanupResult['diagnostics'];
                $cleanup = $cleanupResult['summary'];
            }

            $preparation = self::prepareReconcileWithDiagnostics(
                $ownerScriptID,
                $configuration,
                $diagnostics,
                false
            );
            $diagnostics = $preparation['diagnostics'];
            $publisherResult = self::ensurePublisherResources(
                $ownerScriptID,
                $configuration,
                $diagnostics
            );
            $diagnostics = $publisherResult['diagnostics'];
            $publisherValueIDs = $publisherResult['valueIDsByTopic'];
            $publishedMessages = 0;
            $skippedChannels = 0;
            $registry = $diagnostics['registry'];

            foreach ($preparation['publishPlan'] as $entityKey => $entityPlan) {
                if ($entityPlan['discoveryChanged'] === true) {
                    foreach ($entityPlan['discoveryMessages'] as $message) {
                        self::publishMessage($message, $publisherValueIDs);
                        \SAEF_IncrementStatistic($diagnostics['statisticIDs']['PUBLISHES']);
                        $publishedMessages++;
                    }
                    $registry['managedEntities'][$entityKey]['discoveryHash'] =
                        $registry['managedEntities'][$entityKey]['desiredDiscoveryHash'];
                    $registry['managedEntities'][$entityKey]['discoveryPublished'] = true;
                    \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
                } else {
                    \SAEF_IncrementStatistic($diagnostics['statisticIDs']['PUBLISH_SKIPS']);
                    $skippedChannels++;
                }

                if ($entityPlan['runtimeChanged'] === true) {
                    foreach ($entityPlan['runtimeMessages'] as $message) {
                        self::publishMessage($message, $publisherValueIDs);
                        \SAEF_IncrementStatistic($diagnostics['statisticIDs']['PUBLISHES']);
                        $publishedMessages++;
                    }
                    $registry['managedEntities'][$entityKey]['runtimeHash'] =
                        $registry['managedEntities'][$entityKey]['desiredRuntimeHash'];
                    $registry['managedEntities'][$entityKey]['runtimePublished'] = true;
                    \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
                } else {
                    \SAEF_IncrementStatistic($diagnostics['statisticIDs']['PUBLISH_SKIPS']);
                    $skippedChannels++;
                }
            }

            $registry['lastSuccessfulReconcile'] = time();
            $registry['publishedConfigurationHash'] = $diagnostics['configurationHash'];
            \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
            $diagnostics['registry'] = $registry;
            \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_SUCCESS']);
            \SAEF_IncrementStatistic($diagnostics['statisticIDs']['SUCCESSES']);

            return [
                'preparation' => $preparation,
                'diagnostics' => $diagnostics,
                'cleanup' => $cleanup,
                'summary' => [
                    'publishers' => count($publisherValueIDs),
                    'publishedMessages' => $publishedMessages,
                    'skippedChannels' => $skippedChannels,
                ],
            ];
        } catch (Throwable $exception) {
            self::recordFailure(
                $diagnostics['errorRingBufferID'],
                $diagnostics['statisticIDs'],
                $exception,
                $cleanupEnabled ? 'reconcile_cleanup_or_publish' : 'reconcile_publish'
            );
            \IPS_LogMessage(
                'SAEF MQTT Discovery Exporter',
                'Reconcile publication failed: ' . $exception->getMessage()
            );

            throw $exception;
        } finally {
            if (!\IPS_SemaphoreLeave($semaphoreName)) {
                \IPS_LogMessage(
                    'SAEF MQTT Discovery Exporter',
                    'Unable to release MQTT publication semaphore.'
                );
            }
        }
    }

    /**
     * Dispatches exactly one registered command or state trigger.
     *
     * @param array<string, mixed> $configuration Validated, normalized core configuration.
     *
     * @return array{
     *     type: 'command'|'state',
     *     status: string,
     *     entityKey: string,
     *     commandType?: string,
     *     publishedMessages: int
     * }
     */
    public static function dispatchTriggeredVariable(
        int $ownerScriptID,
        array $configuration,
        int $triggerVariableID
    ): array {
        $diagnostics = self::loadExistingDiagnostics($ownerScriptID);
        try {
            self::validateDispatchConfiguration($configuration, $diagnostics['registry']);
        } catch (Throwable $exception) {
            self::recordFailure(
                $diagnostics['errorRingBufferID'],
                $diagnostics['statisticIDs'],
                $exception,
                'dispatch_configuration'
            );
            \IPS_LogMessage('SAEF MQTT Discovery Exporter', 'Dispatch rejected: ' . $exception->getMessage());
            throw $exception;
        }
        \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_RUN']);
        \SAEF_IncrementStatistic($diagnostics['statisticIDs']['EXECUTIONS']);

        $commandEntry = $diagnostics['registry']['commandIndex'][(string)$triggerVariableID] ?? null;
        $stateEntries = $diagnostics['registry']['stateIndex'][(string)$triggerVariableID] ?? null;
        if ($commandEntry !== null && $stateEntries !== null) {
            throw new RuntimeException('Trigger variable has ambiguous Registry ownership.');
        }
        if ($commandEntry !== null && !is_array($commandEntry)) {
            throw new RuntimeException('Command Registry index entry is invalid.');
        }
        if (
            $stateEntries !== null
            && (!is_array($stateEntries) || count($stateEntries) !== 1 || !is_array($stateEntries[0]))
        ) {
            throw new RuntimeException('State trigger must resolve to exactly one Registry entry.');
        }
        if ($commandEntry === null && $stateEntries === null) {
            throw new RuntimeException('Trigger variable is not registered for dispatch.');
        }

        $isStateTrigger = $stateEntries !== null;
        $semaphoreName = 'SAEF_MQTT_EXPORTER_DISPATCH_' . $ownerScriptID;
        if (!\IPS_SemaphoreEnter($semaphoreName, $isStateTrigger ? 1 : 5000)) {
            if ($isStateTrigger) {
                $entityKey = $stateEntries[0]['entityKey'] ?? null;
                if (!is_string($entityKey)) {
                    throw new RuntimeException('State Registry entity key is invalid.');
                }

                return [
                    'type' => 'state',
                    'status' => 'coalesced',
                    'entityKey' => $entityKey,
                    'publishedMessages' => 0,
                ];
            }

            $exception = new RuntimeException('MQTT dispatch semaphore timed out.');
            self::recordFailure(
                $diagnostics['errorRingBufferID'],
                $diagnostics['statisticIDs'],
                $exception,
                'dispatch_lock'
            );
            throw $exception;
        }

        try {
            if ($commandEntry !== null) {
                return self::dispatchCommand(
                    $configuration,
                    $diagnostics,
                    $triggerVariableID,
                    $commandEntry
                );
            }
            return self::dispatchState($configuration, $diagnostics, $stateEntries[0]);
        } catch (Throwable $exception) {
            self::recordFailure(
                $diagnostics['errorRingBufferID'],
                $diagnostics['statisticIDs'],
                $exception,
                'dispatch'
            );
            \IPS_LogMessage('SAEF MQTT Discovery Exporter', 'Dispatch failed: ' . $exception->getMessage());
            throw $exception;
        } finally {
            if (!\IPS_SemaphoreLeave($semaphoreName)) {
                \IPS_LogMessage('SAEF MQTT Discovery Exporter', 'Unable to release dispatch semaphore.');
            }
        }
    }

    /**
     * Ensures the script-owned diagnostic structure and configuration metadata.
     *
     * @param array<string, mixed> $configuration Validated, normalized core configuration.
     *
     * @return array{
     *     categoryID: int,
     *     registryID: int,
     *     errorRingBufferID: int,
     *     statisticIDs: array<string, int>,
     *     configurationHash: string,
     *     registry: array<string, mixed>
     * }
     */
    public static function initializeDiagnostics(int $ownerScriptID, array $configuration): array
    {
        $errorRingBufferID = null;
        $statisticIDs = [];

        try {
            self::validateDiagnosticsInput($ownerScriptID, $configuration);
            $configurationHash = \SAEF_CreateConfigurationHash($configuration);

            $categoryID = \SAEF_EnsureCategory(
                $ownerScriptID,
                'MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS',
                'MQTT Discovery Exporter Diagnostics',
                900,
                'Information',
                false
            );

            $errorRingBufferID = \SAEF_EnsureErrorRingBufferVariable(
                $categoryID,
                'ERROR_HISTORY',
                'Error History',
                900,
                'Warning',
                false
            );

            $statisticIDs = \SAEF_EnsureStatisticsVariables(
                $categoryID,
                self::statisticsDefinitions(),
                false
            );

            $registryID = \SAEF_EnsureRegistryVariable(
                $categoryID,
                'MANAGED_STATE_REGISTRY',
                'Managed State Registry',
                100,
                'Database',
                false
            );

            $registry = \SAEF_ReadRegistry($registryID);
            self::validateRegistry($registry);
            $updatedRegistry = self::updateConfigurationMetadata(
                $registry,
                $configurationHash,
                $configuration['version']
            );

            if ($updatedRegistry !== $registry) {
                \SAEF_WriteRegistry($registryID, $updatedRegistry);
            }

            return [
                'categoryID' => $categoryID,
                'registryID' => $registryID,
                'errorRingBufferID' => $errorRingBufferID,
                'statisticIDs' => $statisticIDs,
                'configurationHash' => $configurationHash,
                'registry' => $updatedRegistry,
            ];
        } catch (Throwable $exception) {
            self::recordFailure(
                $errorRingBufferID,
                $statisticIDs,
                $exception,
                'diagnostics_initialization'
            );
            \IPS_LogMessage(
                'SAEF MQTT Discovery Exporter',
                'Diagnostics initialization failed: ' . $exception->getMessage()
            );

            throw $exception;
        }
    }

    /**
     * @return array{
     *     categoryID: int,
     *     registryID: int,
     *     errorRingBufferID: int,
     *     statisticIDs: array<string, int>,
     *     registry: array<string, mixed>
     * }
     */
    private static function loadExistingDiagnostics(int $ownerScriptID): array
    {
        if ($ownerScriptID <= 0 || !\IPS_ScriptExists($ownerScriptID)) {
            throw new InvalidArgumentException('Owner script does not exist: ' . $ownerScriptID);
        }

        $categoryID = self::requiredChildID($ownerScriptID, 'MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS', 0);
        $registryID = self::requiredChildID($categoryID, 'MANAGED_STATE_REGISTRY', 2);
        $errorRingBufferID = self::requiredChildID($categoryID, 'ERROR_HISTORY', 2);
        \SAEF_ValidateRegistryVariable($registryID);
        \SAEF_ValidateErrorRingBufferVariable($errorRingBufferID);

        $statisticIDs = [];
        foreach (
            [
                'EXECUTIONS',
                'SUCCESSES',
                'FAILURES',
                'COMMANDS',
                'PUBLISHES',
                'PUBLISH_SKIPS',
                'LAST_RUN',
                'LAST_SUCCESS',
                'LAST_FAILURE',
            ] as $ident
        ) {
            $statisticIDs[$ident] = self::requiredChildID($categoryID, $ident, 2);
        }

        $registry = \SAEF_ReadRegistry($registryID);
        self::validateRegistry($registry);

        return [
            'categoryID' => $categoryID,
            'registryID' => $registryID,
            'errorRingBufferID' => $errorRingBufferID,
            'statisticIDs' => $statisticIDs,
            'registry' => $registry,
        ];
    }

    private static function requiredChildID(int $parentID, string $ident, int $expectedObjectType): int
    {
        $objectID = @\IPS_GetObjectIDByIdent($ident, $parentID);
        if ($objectID === false) {
            throw new RuntimeException('Required exporter object is missing: ' . $ident);
        }
        $object = \IPS_GetObject($objectID);
        if (
            ($object['ParentID'] ?? null) !== $parentID
            || ($object['ObjectIdent'] ?? null) !== $ident
            || ($object['ObjectType'] ?? null) !== $expectedObjectType
        ) {
            throw new RuntimeException('Required exporter object has incompatible ownership: ' . $ident);
        }

        return $objectID;
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $registry
     */
    private static function validateDispatchConfiguration(array $configuration, array $registry): void
    {
        $configurationHash = \SAEF_CreateConfigurationHash($configuration);
        if (
            ($registry['preparedConfigurationHash'] ?? null) !== $configurationHash
            || ($registry['publishedConfigurationHash'] ?? null) !== $configurationHash
        ) {
            throw new RuntimeException('Dispatch configuration is not fully reconciled and published.');
        }
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $diagnostics
     * @param array<string, mixed> $commandEntry
     *
     * @return array{type: 'command', status: string, entityKey: string, commandType: string, publishedMessages: int}
     */
    private static function dispatchCommand(
        array $configuration,
        array $diagnostics,
        int $commandVariableID,
        array $commandEntry
    ): array {
        $entityKey = $commandEntry['entityKey'] ?? null;
        $commandType = $commandEntry['commandType'] ?? null;
        if (!is_string($entityKey) || !is_string($commandType)) {
            throw new RuntimeException('Command Registry index contract is invalid.');
        }

        $entityContract = self::findEntityContract($configuration, $entityKey);
        $payload = \GetValue($commandVariableID);
        if (!is_string($payload)) {
            return self::commandFailureResult(
                $diagnostics,
                $entityKey,
                $commandType,
                'invalid_payload',
                new RuntimeException('MQTT command payload is not a string.')
            );
        }

        try {
            $command = MqttDiscoveryExporterCore::parseCommand(
                $entityContract['entity'],
                $commandType,
                $payload
            );
        } catch (InvalidArgumentException $exception) {
            return self::commandFailureResult(
                $diagnostics,
                $entityKey,
                $commandType,
                'invalid_payload',
                $exception
            );
        }

        $capability = $entityContract['entity']['capabilities'][$commandType] ?? null;
        if (
            !is_array($capability)
            || !isset($capability['stateVariableID'], $capability['actionVariableID'])
            || !is_int($capability['stateVariableID'])
            || !is_int($capability['actionVariableID'])
        ) {
            throw new RuntimeException('Command capability contract is invalid: ' . $entityKey . '.' . $commandType);
        }
        self::validateVariableType(
            $capability['actionVariableID'],
            $commandType === 'power' ? 0 : 1,
            'action',
            $entityKey,
            $commandType
        );
        if (!\HasAction($capability['actionVariableID'])) {
            return self::commandFailureResult(
                $diagnostics,
                $entityKey,
                $commandType,
                'action_failed',
                new RuntimeException('Configured action is unavailable.')
            );
        }

        try {
            $actionAccepted = \RequestAction(
                $capability['actionVariableID'],
                $command['value']
            );
        } catch (Throwable $exception) {
            return self::commandFailureResult(
                $diagnostics,
                $entityKey,
                $commandType,
                'action_failed',
                $exception
            );
        }

        $confirmation = $entityContract['entity']['confirmation'];
        $confirmed = \SAEF_WaitForVariable(
            $capability['stateVariableID'],
            $confirmation['timeoutMilliseconds'],
            $confirmation['pollIntervalMilliseconds'],
            null,
            SAEF_WAIT_UPDATED,
            $confirmation['timeoutMilliseconds'],
            static fn (mixed $actualValue): bool => self::commandFeedbackMatches(
                $commandType,
                $command['value'],
                $actualValue
            )
        );
        if (!$confirmed) {
            $status = $actionAccepted ? 'confirmation_timeout' : 'action_failed';
            $message = $actionAccepted
                ? 'Observed state confirmation timed out.'
                : 'Configured device action returned false without confirmed feedback.';

            return self::commandFailureResult(
                $diagnostics,
                $entityKey,
                $commandType,
                $status,
                new RuntimeException($message)
            );
        }

        try {
            $publishedMessages = self::publishAffectedEntityRuntime(
                $configuration,
                $diagnostics,
                $entityKey,
                $entityContract,
                true
            );
            \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_SUCCESS']);
            \SAEF_IncrementStatistic($diagnostics['statisticIDs']['COMMANDS']);
            \SAEF_IncrementStatistic($diagnostics['statisticIDs']['SUCCESSES']);

            return [
                'type' => 'command',
                'status' => 'confirmed',
                'entityKey' => $entityKey,
                'commandType' => $commandType,
                'publishedMessages' => $publishedMessages,
            ];
        } catch (Throwable $exception) {
            return self::commandFailureResult(
                $diagnostics,
                $entityKey,
                $commandType,
                'publish_failed',
                $exception
            );
        }
    }

    /**
     * Compares authoritative device feedback with one parsed command value.
     *
     * Integer light values may cross bounded conversion boundaries between
     * integrations. Boolean power and packed RGB values remain exact.
     */
    private static function commandFeedbackMatches(
        string $commandType,
        bool|int $expectedValue,
        mixed $actualValue
    ): bool {
        return match ($commandType) {
            'power' => is_bool($actualValue) && $actualValue === $expectedValue,
            'brightness' => is_int($actualValue)
                && is_int($expectedValue)
                && abs($actualValue - $expectedValue) <= 1,
            'colorTemperature' => is_int($actualValue)
                && is_int($expectedValue)
                && abs($actualValue - $expectedValue) <= 10,
            'rgb' => is_int($actualValue) && $actualValue === $expectedValue,
            default => throw new RuntimeException(
                'Unsupported command feedback type: ' . $commandType
            ),
        };
    }

    /**
     * @param array<string, mixed> $diagnostics
     *
     * @return array{type: 'command', status: string, entityKey: string, commandType: string, publishedMessages: int}
     */
    private static function commandFailureResult(
        array $diagnostics,
        string $entityKey,
        string $commandType,
        string $status,
        Throwable $exception
    ): array {
        self::recordFailure(
            $diagnostics['errorRingBufferID'],
            $diagnostics['statisticIDs'],
            $exception,
            'command_' . $status
        );
        \IPS_LogMessage(
            'SAEF MQTT Discovery Exporter',
            'Command ' . $status . ': ' . $exception->getMessage()
        );

        return [
            'type' => 'command',
            'status' => $status,
            'entityKey' => $entityKey,
            'commandType' => $commandType,
            'publishedMessages' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $diagnostics
     * @param array<string, mixed> $stateEntry
     *
     * @return array{type: 'state', status: string, entityKey: string, publishedMessages: int}
     */
    private static function dispatchState(
        array $configuration,
        array $diagnostics,
        array $stateEntry
    ): array {
        $entityKey = $stateEntry['entityKey'] ?? null;
        if (!is_string($entityKey)) {
            throw new RuntimeException('State Registry index contract is invalid.');
        }
        $entityContract = self::findEntityContract($configuration, $entityKey);
        $publishedMessages = self::publishAffectedEntityRuntime(
            $configuration,
            $diagnostics,
            $entityKey,
            $entityContract,
            false
        );
        \SAEF_SetStatisticTimestamp($diagnostics['statisticIDs']['LAST_SUCCESS']);
        \SAEF_IncrementStatistic($diagnostics['statisticIDs']['SUCCESSES']);

        return [
            'type' => 'state',
            'status' => $publishedMessages === 0 ? 'skipped' : 'published',
            'entityKey' => $entityKey,
            'publishedMessages' => $publishedMessages,
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array{device: array<string, mixed>, entity: array<string, mixed>}
     */
    private static function findEntityContract(array $configuration, string $expectedEntityKey): array
    {
        if (!isset($configuration['devices']) || !is_array($configuration['devices'])) {
            throw new RuntimeException('Dispatch configuration devices are invalid.');
        }

        foreach ($configuration['devices'] as $device) {
            if (!is_array($device) || !isset($device['entities']) || !is_array($device['entities'])) {
                throw new RuntimeException('Dispatch device contract is invalid.');
            }
            foreach ($device['entities'] as $entity) {
                if (!is_array($entity)) {
                    throw new RuntimeException('Dispatch entity contract is invalid.');
                }
                if (MqttDiscoveryExporterCore::entityKey($device, $entity) === $expectedEntityKey) {
                    return ['device' => $device, 'entity' => $entity];
                }
            }
        }

        throw new RuntimeException('Registry entity is absent from dispatch configuration: ' . $expectedEntityKey);
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $diagnostics
     * @param array{device: array<string, mixed>, entity: array<string, mixed>} $entityContract
     */
    private static function publishAffectedEntityRuntime(
        array $configuration,
        array $diagnostics,
        string $entityKey,
        array $entityContract,
        bool $force
    ): int {
        $observedValues = self::readObservedEntityValues($entityKey, $entityContract['entity']);
        $runtime = MqttDiscoveryExporterCore::buildRuntimePayloads(
            $configuration,
            $entityContract['device'],
            $entityContract['entity'],
            $observedValues
        );
        $runtimeHash = MqttDiscoveryExporterCore::payloadHash($runtime['topics']);
        $registry = $diagnostics['registry'];
        $managedEntry = $registry['managedEntities'][$entityKey] ?? null;
        if (!is_array($managedEntry)) {
            throw new RuntimeException('Managed entity is absent during runtime publication: ' . $entityKey);
        }

        if (
            !$force
            && ($managedEntry['runtimePublished'] ?? false) === true
            && ($managedEntry['runtimeHash'] ?? null) === $runtimeHash
        ) {
            \SAEF_IncrementStatistic($diagnostics['statisticIDs']['PUBLISH_SKIPS']);

            return 0;
        }

        $publisherValueIDs = self::loadPublisherValueIDs($registry);
        $publishedMessages = 0;
        foreach ($runtime['topics'] as $topic => $payload) {
            self::publishMessage(
                ['topic' => $topic, 'payload' => $payload, 'retain' => true],
                $publisherValueIDs
            );
            \SAEF_IncrementStatistic($diagnostics['statisticIDs']['PUBLISHES']);
            $publishedMessages++;
        }

        $registry['managedEntities'][$entityKey]['runtimeHash'] = $runtimeHash;
        $registry['managedEntities'][$entityKey]['desiredRuntimeHash'] = $runtimeHash;
        $registry['managedEntities'][$entityKey]['runtimePublished'] = true;
        \SAEF_WriteRegistry($diagnostics['registryID'], $registry);

        return $publishedMessages;
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, bool|int>
     */
    private static function readObservedEntityValues(string $entityKey, array $entity): array
    {
        if (!isset($entity['capabilities']) || !is_array($entity['capabilities'])) {
            throw new RuntimeException('Observed entity capabilities are invalid: ' . $entityKey);
        }
        $expectedTypes = [
            'power' => 0,
            'brightness' => 1,
            'rgb' => 1,
            'colorTemperature' => 1,
        ];
        $values = [];

        foreach ($entity['capabilities'] as $capability => $contract) {
            if (!is_string($capability) || !isset($expectedTypes[$capability]) || !is_array($contract)) {
                throw new RuntimeException('Observed capability contract is invalid: ' . $entityKey);
            }
            $stateVariableID = $contract['stateVariableID'] ?? null;
            if (!is_int($stateVariableID)) {
                throw new RuntimeException('Observed state variable ID is invalid: ' . $entityKey . '.' . $capability);
            }
            self::validateVariableType(
                $stateVariableID,
                $expectedTypes[$capability],
                'state',
                $entityKey,
                $capability
            );
            $value = \GetValue($stateVariableID);
            if ($expectedTypes[$capability] === 0 && !is_bool($value)) {
                throw new RuntimeException('Observed Boolean value is invalid: ' . $entityKey . '.' . $capability);
            }
            if ($expectedTypes[$capability] === 1 && !is_int($value)) {
                throw new RuntimeException('Observed integer value is invalid: ' . $entityKey . '.' . $capability);
            }
            $values[$capability] = $value;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $registry
     *
     * @return array<string, int>
     */
    private static function loadPublisherValueIDs(array $registry): array
    {
        if (!isset($registry['publishers']) || !is_array($registry['publishers'])) {
            throw new RuntimeException('Publisher Registry is missing.');
        }
        $valueIDs = [];

        foreach ($registry['publishers'] as $publisher) {
            if (
                !is_array($publisher)
                || ($publisher['resourceState'] ?? null) !== 'ready'
                || !isset($publisher['topic'], $publisher['valueVariableID'])
                || !is_string($publisher['topic'])
                || !is_int($publisher['valueVariableID'])
            ) {
                throw new RuntimeException('Publisher Registry entry is not ready.');
            }
            $variableID = $publisher['valueVariableID'];
            self::validateVariableType($variableID, 3, 'publisher', 'registry', $publisher['topic']);
            if (!\HasAction($variableID)) {
                throw new RuntimeException('Publisher Value variable has no action.');
            }
            $valueIDs[$publisher['topic']] = $variableID;
        }

        return $valueIDs;
    }

    /**
     * Removes only resources whose complete ownership contract is recorded in
     * the Registry. Registry ownership is reduced only after all effects
     * succeed, so interrupted cleanup remains retryable.
     *
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $diagnostics
     *
     * @return array{
     *     diagnostics: array<string, mixed>,
     *     summary: array{entities: int, events: int, instances: int, categories: int, retainedTopics: int}
     * }
     */
    private static function cleanupManagedResources(
        int $ownerScriptID,
        array $configuration,
        array $diagnostics
    ): array {
        $preparedEntities = self::validateAndPrepareEntities($configuration);
        $transport = self::mqttTransportContract($configuration);
        $registry = $diagnostics['registry'];
        $cleanupEntities = [];

        foreach ($registry['managedEntities'] as $entityKey => $previous) {
            if (!is_string($entityKey) || !is_array($previous)) {
                throw new RuntimeException('Managed entity cleanup contract is invalid.');
            }
            if (
                !isset($preparedEntities[$entityKey])
                || self::entityRequiresCleanup(
                    $previous,
                    $preparedEntities[$entityKey],
                    $transport['deviceModuleID']
                )
            ) {
                $cleanupEntities[$entityKey] = $previous;
            }
        }
        ksort($cleanupEntities);

        $desiredTopics = [];
        foreach ($preparedEntities as $prepared) {
            $desiredTopics[$prepared['discoveryTopic']] = true;
            foreach (array_keys($prepared['runtimeTopics']) as $topic) {
                $desiredTopics[$topic] = true;
            }
        }

        $cleanupTopics = [];
        foreach ($cleanupEntities as $entry) {
            $discoveryTopic = $entry['discoveryTopic'] ?? null;
            $runtimeTopics = $entry['runtimeTopics'] ?? null;
            if (!is_string($discoveryTopic) || !is_array($runtimeTopics)) {
                throw new RuntimeException('Managed entity cleanup topics are invalid.');
            }
            $cleanupTopics[$discoveryTopic] = true;
            foreach ($runtimeTopics as $topic) {
                if (!is_string($topic)) {
                    throw new RuntimeException('Managed runtime cleanup topic is invalid.');
                }
                $cleanupTopics[$topic] = true;
            }
        }

        $cleanupPublishers = [];
        foreach ($registry['publishers'] as $publisherKey => $publisher) {
            if (!is_string($publisherKey) || !is_array($publisher)) {
                throw new RuntimeException('Publisher cleanup contract is invalid.');
            }
            $topic = $publisher['topic'] ?? null;
            if (!is_string($topic) || hash('sha256', $topic) !== $publisherKey) {
                throw new RuntimeException('Publisher cleanup identity is invalid.');
            }
            if (isset($cleanupTopics[$topic]) || !isset($desiredTopics[$topic])) {
                $cleanupPublishers[$publisherKey] = $publisher;
            }
        }
        ksort($cleanupPublishers);

        $cleanupCategories = self::preflightObsoleteResourceCategories(
            $ownerScriptID,
            $preparedEntities,
            $registry,
            $cleanupEntities,
            $cleanupPublishers
        );

        // Complete the ownership preflight before disabling, publishing or
        // deleting anything. A mismatch therefore fails without side effects.
        $eventFields = [
            'commandEventIDs' => 'commandEventIdents',
            'stateEventIDs' => 'stateEventIdents',
        ];
        foreach ($cleanupEntities as $entry) {
            foreach ($eventFields as $idField => $identField) {
                $ids = $entry[$idField] ?? null;
                $idents = $entry[$identField] ?? null;
                if (!is_array($ids) || !is_array($idents)) {
                    throw new RuntimeException('Managed event cleanup contract is invalid.');
                }
                foreach ($ids as $capability => $eventID) {
                    $ident = $idents[$capability] ?? null;
                    if (!is_int($eventID) || !is_string($ident)) {
                        throw new RuntimeException('Managed event cleanup identity is invalid.');
                    }
                    self::assertOwnedEventIfPresent($ownerScriptID, $eventID, $ident);
                }
            }
            self::assertOwnedCommandAdapters($ownerScriptID, $entry);
        }
        foreach ($cleanupPublishers as $publisherKey => $publisher) {
            $instanceID = $publisher['instanceID'] ?? null;
            $ident = $publisher['ident'] ?? null;
            $valueID = $publisher['valueVariableID'] ?? null;
            $retain = $publisher['retain'] ?? null;
            $moduleID = $publisher['moduleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID;
            $parentID = $publisher['parentCategoryID'] ?? $ownerScriptID;
            if (
                !is_int($instanceID)
                || !is_string($ident)
                || !is_int($valueID)
                || !is_bool($retain)
                || !is_int($parentID)
                || !self::isSupportedMqttDeviceModuleID($moduleID)
            ) {
                throw new RuntimeException('Managed publisher cleanup identity is invalid.');
            }
            if (\IPS_ObjectExists($instanceID)) {
                self::assertOwnedAdapter(
                    $parentID,
                    $instanceID,
                    $ident,
                    $valueID,
                    $moduleID,
                    $retain && !isset($registry['cleanupTombstones'][$publisherKey])
                );
            }
        }

        $eventCount = 0;
        foreach ($cleanupEntities as $entry) {
            foreach ($eventFields as $idField => $identField) {
                $ids = $entry[$idField] ?? null;
                $idents = $entry[$identField] ?? null;
                if (!is_array($ids) || !is_array($idents)) {
                    throw new RuntimeException('Managed event cleanup contract is invalid.');
                }
                foreach ($ids as $capability => $eventID) {
                    $ident = $idents[$capability] ?? null;
                    if (!is_int($eventID) || !is_string($ident)) {
                        throw new RuntimeException('Managed event cleanup identity is invalid.');
                    }
                    if (self::disableOwnedEvent($ownerScriptID, $eventID, $ident)) {
                        $eventCount++;
                    }
                }
            }
        }

        $retainedTopicCount = 0;
        foreach ($cleanupPublishers as $publisherKey => $publisher) {
            $topicCleared = self::clearOwnedRetainedTopic(
                $ownerScriptID,
                $publisherKey,
                $publisher,
                isset($registry['cleanupTombstones'][$publisherKey])
            );
            if ($topicCleared) {
                $retainedTopicCount++;
                \SAEF_IncrementStatistic($diagnostics['statisticIDs']['PUBLISHES']);
                $registry['cleanupTombstones'][$publisherKey] = true;
                \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
            }
        }

        foreach ($cleanupEntities as $entry) {
            foreach ($eventFields as $idField => $identField) {
                foreach ($entry[$idField] as $capability => $eventID) {
                    self::deleteOwnedEvent($ownerScriptID, $eventID, $entry[$identField][$capability]);
                }
            }
        }

        $instanceCount = 0;
        foreach ($cleanupEntities as $entry) {
            $instanceIDs = $entry['commandInstanceIDs'] ?? null;
            $instanceIdents = $entry['commandInstanceIdents'] ?? null;
            $valueIDs = $entry['commandVariableIDs'] ?? null;
            $moduleID = $entry['adapterModuleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID;
            $parentID = $entry['commandParentCategoryID'] ?? $ownerScriptID;
            if (
                !is_array($instanceIDs)
                || !is_array($instanceIdents)
                || !is_array($valueIDs)
                || !is_int($parentID)
                || !self::isSupportedMqttDeviceModuleID($moduleID)
            ) {
                throw new RuntimeException('Managed command adapter cleanup contract is invalid.');
            }
            foreach ($instanceIDs as $capability => $instanceID) {
                $ident = $instanceIdents[$capability] ?? null;
                $valueID = $valueIDs[$capability] ?? null;
                if (!is_int($instanceID) || !is_string($ident) || !is_int($valueID)) {
                    throw new RuntimeException('Managed command adapter cleanup identity is invalid.');
                }
                if (self::deleteOwnedAdapter($parentID, $instanceID, $ident, $valueID, $moduleID)) {
                    $instanceCount++;
                }
            }
        }
        foreach ($cleanupPublishers as $publisher) {
            $instanceID = $publisher['instanceID'] ?? null;
            $ident = $publisher['ident'] ?? null;
            $valueID = $publisher['valueVariableID'] ?? null;
            $moduleID = $publisher['moduleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID;
            $parentID = $publisher['parentCategoryID'] ?? $ownerScriptID;
            if (
                !is_int($instanceID)
                || !is_string($ident)
                || !is_int($valueID)
                || !is_int($parentID)
                || !self::isSupportedMqttDeviceModuleID($moduleID)
            ) {
                throw new RuntimeException('Managed publisher cleanup identity is invalid.');
            }
            if (self::deleteOwnedAdapter($parentID, $instanceID, $ident, $valueID, $moduleID)) {
                $instanceCount++;
            }
        }

        $categoryCount = 0;
        foreach ($cleanupCategories['devices'] as $deviceKey => $categories) {
            self::deleteOwnedCategory(
                $categories['commandsCategoryID'],
                $categories['deviceCategoryID'],
                'COMMANDS'
            );
            self::deleteOwnedCategory(
                $categories['publishersCategoryID'],
                $categories['deviceCategoryID'],
                'PUBLISHERS'
            );
            self::deleteOwnedCategory(
                $categories['deviceCategoryID'],
                $cleanupCategories['rootID'],
                $categories['deviceIdent']
            );
            unset($registry['resourceTree']['devices'][$deviceKey]);
            $categoryCount += 3;
        }
        if ($cleanupCategories['deleteRoot']) {
            self::deleteOwnedCategory(
                $cleanupCategories['rootID'],
                $ownerScriptID,
                'MQTT_DISCOVERY_EXPORTER_DEVICES'
            );
            $registry['resourceTree'] = ['devices' => []];
            $categoryCount++;
        }

        foreach (array_keys($cleanupEntities) as $entityKey) {
            unset($registry['managedEntities'][$entityKey]);
        }
        foreach (array_keys($cleanupPublishers) as $publisherKey) {
            unset($registry['publishers'][$publisherKey]);
            unset($registry['cleanupTombstones'][$publisherKey]);
        }
        self::rebuildRegistryIndexes($registry);
        $registry['preparedConfigurationHash'] = null;
        $registry['publishedConfigurationHash'] = null;
        \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
        $diagnostics['registry'] = $registry;

        return [
            'diagnostics' => $diagnostics,
            'summary' => [
                'entities' => count($cleanupEntities),
                'events' => $eventCount,
                'instances' => $instanceCount,
                'categories' => $categoryCount,
                'retainedTopics' => $retainedTopicCount,
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $preparedEntities
     * @param array<string, mixed> $registry
     * @param array<string, array<string, mixed>> $cleanupEntities
     * @param array<string, array<string, mixed>> $cleanupPublishers
     *
     * @return array{rootID: int, deleteRoot: bool, devices: array<string, array<string, int|string>>}
     */
    private static function preflightObsoleteResourceCategories(
        int $ownerScriptID,
        array $preparedEntities,
        array $registry,
        array $cleanupEntities,
        array $cleanupPublishers
    ): array {
        $resourceTree = $registry['resourceTree'] ?? [];
        $registeredDevices = is_array($resourceTree) ? ($resourceTree['devices'] ?? []) : [];
        if (!is_array($registeredDevices) || $registeredDevices === []) {
            return ['rootID' => 0, 'deleteRoot' => false, 'devices' => []];
        }
        $rootID = $resourceTree['rootID'] ?? null;
        $rootIdent = $resourceTree['rootIdent'] ?? null;
        if (!is_int($rootID) || $rootIdent !== 'MQTT_DISCOVERY_EXPORTER_DEVICES') {
            throw new RuntimeException('Managed resource tree root contract is invalid.');
        }
        self::assertOwnedObject($rootID, 0, $ownerScriptID, $rootIdent);

        $desiredDeviceKeys = [];
        foreach ($preparedEntities as $prepared) {
            $desiredDeviceKeys[self::preparedDeviceKey($prepared)] = true;
        }
        $devices = [];
        foreach ($registeredDevices as $deviceKey => $categories) {
            if (isset($desiredDeviceKeys[$deviceKey])) {
                continue;
            }
            if (!is_string($deviceKey) || !is_array($categories)) {
                throw new RuntimeException('Managed device category contract is invalid.');
            }
            foreach (
                ['deviceCategoryID', 'commandsCategoryID', 'publishersCategoryID'] as $field
            ) {
                if (!isset($categories[$field]) || !is_int($categories[$field])) {
                    throw new RuntimeException('Managed device category identity is invalid: ' . $deviceKey);
                }
            }
            $deviceIdent = $categories['deviceIdent'] ?? null;
            if (!is_string($deviceIdent)) {
                throw new RuntimeException('Managed device category Ident is invalid: ' . $deviceKey);
            }
            self::assertOwnedObject($categories['deviceCategoryID'], 0, $rootID, $deviceIdent);
            self::assertOwnedObject(
                $categories['commandsCategoryID'],
                0,
                $categories['deviceCategoryID'],
                'COMMANDS'
            );
            self::assertOwnedObject(
                $categories['publishersCategoryID'],
                0,
                $categories['deviceCategoryID'],
                'PUBLISHERS'
            );

            $expectedCommands = [];
            foreach ($cleanupEntities as $entry) {
                if (($entry['deviceKey'] ?? null) === $deviceKey) {
                    foreach (($entry['commandInstanceIDs'] ?? []) as $instanceID) {
                        if (is_int($instanceID) && \IPS_ObjectExists($instanceID)) {
                            $expectedCommands[] = $instanceID;
                        }
                    }
                }
            }
            $expectedPublishers = [];
            foreach ($cleanupPublishers as $publisher) {
                if (($publisher['parentCategoryID'] ?? null) === $categories['publishersCategoryID']) {
                    $instanceID = $publisher['instanceID'] ?? null;
                    if (is_int($instanceID) && \IPS_ObjectExists($instanceID)) {
                        $expectedPublishers[] = $instanceID;
                    }
                }
            }
            sort($expectedCommands);
            sort($expectedPublishers);
            if (\IPS_GetChildrenIDs($categories['commandsCategoryID']) !== $expectedCommands) {
                throw new RuntimeException('Commands category contains unmanaged objects: ' . $deviceKey);
            }
            if (\IPS_GetChildrenIDs($categories['publishersCategoryID']) !== $expectedPublishers) {
                throw new RuntimeException('Publishers category contains unmanaged objects: ' . $deviceKey);
            }
            $deviceChildren = \IPS_GetChildrenIDs($categories['deviceCategoryID']);
            sort($deviceChildren);
            $expectedDeviceChildren = [$categories['commandsCategoryID'], $categories['publishersCategoryID']];
            sort($expectedDeviceChildren);
            if ($deviceChildren !== $expectedDeviceChildren) {
                throw new RuntimeException('Device category contains unmanaged objects: ' . $deviceKey);
            }
            $devices[$deviceKey] = $categories;
        }

        $deleteRoot = count($devices) === count($registeredDevices);
        if ($deleteRoot) {
            $rootChildren = \IPS_GetChildrenIDs($rootID);
            $expectedRootChildren = array_map(
                static fn (array $categories): int => $categories['deviceCategoryID'],
                $devices
            );
            sort($expectedRootChildren);
            if ($rootChildren !== $expectedRootChildren) {
                throw new RuntimeException('Devices category contains unmanaged objects.');
            }
        }

        return ['rootID' => $rootID, 'deleteRoot' => $deleteRoot, 'devices' => $devices];
    }

    private static function deleteOwnedCategory(int $categoryID, int $parentID, string $ident): void
    {
        self::assertOwnedObject($categoryID, 0, $parentID, $ident);
        if (\IPS_GetChildrenIDs($categoryID) !== []) {
            throw new RuntimeException('Owned category has remaining child objects: ' . $ident);
        }
        if (!\IPS_DeleteCategory($categoryID)) {
            throw new RuntimeException('Unable to delete owned category: ' . $ident);
        }
    }

    /** @param array<string, mixed> $previous @param array<string, mixed> $prepared */
    private static function entityRequiresCleanup(
        array $previous,
        array $prepared,
        string $desiredModuleID
    ): bool {
        if (($previous['adapterModuleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID) !== $desiredModuleID) {
            return true;
        }
        if (($previous['discoveryTopic'] ?? null) !== $prepared['discoveryTopic']) {
            return true;
        }

        foreach (
            [
                'runtimeTopics' => array_keys($prepared['runtimeTopics']),
                'commandTopics' => $prepared['commandTopics'],
                'commandInstanceIdents' => $prepared['commandInstanceIdents'],
                'commandEventIdents' => $prepared['commandEventIdents'],
                'stateEventIdents' => $prepared['stateEventIdents'],
            ] as $field => $desired
        ) {
            $owned = $previous[$field] ?? null;
            if (!is_array($owned)) {
                throw new RuntimeException('Managed cleanup field is invalid: ' . $field);
            }
            foreach ($owned as $key => $value) {
                if (!is_string($value) || !in_array($value, array_values($desired), true)) {
                    return true;
                }
                if (is_string($key) && isset($desired[$key]) && $desired[$key] !== $value) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function disableOwnedEvent(int $ownerScriptID, int $eventID, string $ident): bool
    {
        if (!\IPS_ObjectExists($eventID)) {
            return false;
        }
        self::assertOwnedObject($eventID, 4, $ownerScriptID, $ident);
        \IPS_GetEvent($eventID);
        \IPS_SetEventActive($eventID, false);

        return true;
    }

    private static function assertOwnedEventIfPresent(int $ownerScriptID, int $eventID, string $ident): void
    {
        if (!\IPS_ObjectExists($eventID)) {
            return;
        }
        self::assertOwnedObject($eventID, 4, $ownerScriptID, $ident);
        \IPS_GetEvent($eventID);
        if (\IPS_GetChildrenIDs($eventID) !== []) {
            throw new RuntimeException('Owned event has unexpected child objects: ' . $ident);
        }
    }

    /** @param array<string, mixed> $entry */
    private static function assertOwnedCommandAdapters(int $ownerScriptID, array $entry): void
    {
        $instanceIDs = $entry['commandInstanceIDs'] ?? null;
        $instanceIdents = $entry['commandInstanceIdents'] ?? null;
        $valueIDs = $entry['commandVariableIDs'] ?? null;
        $moduleID = $entry['adapterModuleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID;
        $parentID = $entry['commandParentCategoryID'] ?? $ownerScriptID;
        if (
            !is_array($instanceIDs)
            || !is_array($instanceIdents)
            || !is_array($valueIDs)
            || !is_int($parentID)
            || !self::isSupportedMqttDeviceModuleID($moduleID)
        ) {
            throw new RuntimeException('Managed command adapter cleanup contract is invalid.');
        }
        foreach ($instanceIDs as $capability => $instanceID) {
            $ident = $instanceIdents[$capability] ?? null;
            $valueID = $valueIDs[$capability] ?? null;
            if (!is_int($instanceID) || !is_string($ident) || !is_int($valueID)) {
                throw new RuntimeException('Managed command adapter cleanup identity is invalid.');
            }
            if (\IPS_ObjectExists($instanceID)) {
                self::assertOwnedAdapter($parentID, $instanceID, $ident, $valueID, $moduleID, false);
            }
        }
    }

    private static function deleteOwnedEvent(int $ownerScriptID, int $eventID, string $ident): void
    {
        if (!\IPS_ObjectExists($eventID)) {
            return;
        }
        self::assertOwnedObject($eventID, 4, $ownerScriptID, $ident);
        if (\IPS_GetChildrenIDs($eventID) !== []) {
            throw new RuntimeException('Owned event has unexpected child objects: ' . $ident);
        }
        if (!\IPS_DeleteEvent($eventID)) {
            throw new RuntimeException('Unable to delete owned event: ' . $ident);
        }
    }

    /** @param array<string, mixed> $publisher */
    private static function clearOwnedRetainedTopic(
        int $ownerScriptID,
        string $publisherKey,
        array $publisher,
        bool $alreadyCleared
    ): bool {
        $topic = $publisher['topic'] ?? null;
        $retain = $publisher['retain'] ?? null;
        $instanceID = $publisher['instanceID'] ?? null;
        $ident = $publisher['ident'] ?? null;
        $valueID = $publisher['valueVariableID'] ?? null;
        $moduleID = $publisher['moduleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID;
        $parentID = $publisher['parentCategoryID'] ?? $ownerScriptID;
        if (
            !is_string($topic)
            || hash('sha256', $topic) !== $publisherKey
            || !is_bool($retain)
            || !is_int($instanceID)
            || !is_string($ident)
            || !is_int($valueID)
            || !is_int($parentID)
            || !self::isSupportedMqttDeviceModuleID($moduleID)
        ) {
            throw new RuntimeException('Managed retained-topic cleanup contract is invalid.');
        }
        if (!$retain || $alreadyCleared || !\IPS_ObjectExists($instanceID)) {
            return false;
        }
        self::assertOwnedAdapter($parentID, $instanceID, $ident, $valueID, $moduleID, true);
        if (!\RequestAction($valueID, '')) {
            throw new RuntimeException('Retained-topic cleanup publication failed.');
        }

        return true;
    }

    private static function deleteOwnedAdapter(
        int $parentID,
        int $instanceID,
        string $ident,
        int $valueID,
        string $moduleID
    ): bool {
        if (!\IPS_ObjectExists($instanceID)) {
            return false;
        }
        self::assertOwnedAdapter($parentID, $instanceID, $ident, $valueID, $moduleID, false);
        if (\IPS_VariableExists($valueID) && !\IPS_DeleteVariable($valueID)) {
            throw new RuntimeException('Unable to delete owned adapter Value variable: ' . $ident);
        }
        if (\IPS_GetChildrenIDs($instanceID) !== []) {
            throw new RuntimeException('Owned adapter has unexpected child objects: ' . $ident);
        }
        if (!\IPS_DeleteInstance($instanceID)) {
            throw new RuntimeException('Unable to delete owned adapter: ' . $ident);
        }

        return true;
    }

    private static function assertOwnedAdapter(
        int $parentID,
        int $instanceID,
        string $ident,
        int $valueID,
        string $moduleID,
        bool $requireValue
    ): void {
        self::assertOwnedObject($instanceID, 1, $parentID, $ident);
        $instance = \IPS_GetInstance($instanceID);
        if (($instance['ModuleInfo']['ModuleID'] ?? null) !== $moduleID) {
            throw new RuntimeException('Owned adapter module mismatch: ' . $ident);
        }
        $children = \IPS_GetChildrenIDs($instanceID);
        if (\IPS_VariableExists($valueID)) {
            self::assertOwnedObject($valueID, 2, $instanceID, 'Value');
            self::validateVariableType($valueID, 3, 'cleanup', 'adapter', $ident);
            if (array_values(array_diff($children, [$valueID])) !== []) {
                throw new RuntimeException('Owned adapter has unexpected child objects: ' . $ident);
            }
        } elseif ($requireValue) {
            throw new RuntimeException('Owned adapter Value variable is missing: ' . $ident);
        } elseif ($children !== []) {
            throw new RuntimeException('Owned adapter child contract is invalid: ' . $ident);
        }
    }

    private static function isSupportedMqttDeviceModuleID(mixed $moduleID): bool
    {
        return is_string($moduleID) && in_array(
            $moduleID,
            [self::MQTT_CLIENT_DEVICE_MODULE_ID, self::MQTT_SERVER_DEVICE_MODULE_ID],
            true
        );
    }

    private static function assertOwnedObject(
        int $objectID,
        int $type,
        int $parentID,
        string $ident
    ): void {
        $object = \IPS_GetObject($objectID);
        if (
            ($object['ObjectType'] ?? null) !== $type
            || ($object['ParentID'] ?? null) !== $parentID
            || ($object['ObjectIdent'] ?? null) !== $ident
        ) {
            throw new RuntimeException('Registry ownership verification failed: ' . $ident);
        }
    }

    /** @param array<string, mixed> $registry */
    private static function rebuildRegistryIndexes(array &$registry): void
    {
        $commandIndex = [];
        $stateIndex = [];
        foreach ($registry['managedEntities'] as $entityKey => $entry) {
            if (!is_string($entityKey) || !is_array($entry)) {
                throw new RuntimeException('Remaining managed entity contract is invalid.');
            }
            foreach (($entry['commandVariableIDs'] ?? []) as $capability => $variableID) {
                if (!is_string($capability) || !is_int($variableID)) {
                    throw new RuntimeException('Remaining command index contract is invalid.');
                }
                $commandIndex[(string)$variableID] = [
                    'entityKey' => $entityKey,
                    'commandType' => $capability,
                ];
            }
            foreach (($entry['stateVariableIDs'] ?? []) as $capability => $variableID) {
                if (!is_string($capability) || !is_int($variableID)) {
                    throw new RuntimeException('Remaining state index contract is invalid.');
                }
                $stateIndex[(string)$variableID] = [[
                    'entityKey' => $entityKey,
                    'capability' => $capability,
                ]];
            }
        }
        ksort($commandIndex);
        ksort($stateIndex);
        $registry['commandIndex'] = $commandIndex;
        $registry['stateIndex'] = $stateIndex;
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array{transport: 'client'|'server', gatewayID: int, gatewayModuleID: string, deviceModuleID: string}
     */
    private static function mqttTransportContract(array $configuration): array
    {
        $mqtt = $configuration['mqtt'] ?? null;
        if (!is_array($mqtt)) {
            throw new InvalidArgumentException('Normalized MQTT configuration is missing.');
        }

        $transport = $mqtt['transport'] ?? 'server';
        if (!is_string($transport) || !in_array($transport, ['client', 'server'], true)) {
            throw new InvalidArgumentException('Configured MQTT transport must be client or server.');
        }

        $gatewayID = $mqtt['gatewayID'] ?? ($transport === 'server' ? ($mqtt['serverID'] ?? null) : null);
        if (!is_int($gatewayID) || $gatewayID <= 0 || !\IPS_InstanceExists($gatewayID)) {
            throw new InvalidArgumentException('Configured MQTT gateway instance does not exist.');
        }

        $gatewayModuleID = $transport === 'client'
            ? self::MQTT_CLIENT_GATEWAY_MODULE_ID
            : self::MQTT_SERVER_GATEWAY_MODULE_ID;
        $deviceModuleID = $transport === 'client'
            ? self::MQTT_CLIENT_DEVICE_MODULE_ID
            : self::MQTT_SERVER_DEVICE_MODULE_ID;
        $instance = \IPS_GetInstance($gatewayID);
        if (($instance['ModuleInfo']['ModuleID'] ?? null) !== $gatewayModuleID) {
            throw new InvalidArgumentException('Configured MQTT gateway module does not match transport.');
        }
        if (!in_array($deviceModuleID, \IPS_GetModuleList(), true)) {
            throw new RuntimeException('Required MQTT Device module is not available.');
        }

        return [
            'transport' => $transport,
            'gatewayID' => $gatewayID,
            'gatewayModuleID' => $gatewayModuleID,
            'deviceModuleID' => $deviceModuleID,
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, array{
     *     device: array<string, mixed>,
     *     entity: array<string, mixed>,
     *     discoveryTopic: string,
     *     discoveryPayload: string,
     *     discoveryHash: string,
     *     runtimeTopics: array<string, string>,
     *     runtimeHash: string,
     *     uuid: string,
     *     commandTopics: array<string, string>,
     *     commandInstanceIdents: array<string, string>,
     *     commandEventIdents: array<string, string>,
     *     stateEventIdents: array<string, string>,
     *     stateVariableIDs: array<string, int>
     * }>
     */
    private static function validateAndPrepareEntities(array $configuration): array
    {
        self::mqttTransportContract($configuration);
        if (!isset($configuration['defaults']['retain']) || !is_bool($configuration['defaults']['retain'])) {
            throw new InvalidArgumentException('Normalized runtime retain setting must be Boolean.');
        }
        if (!isset($configuration['devices']) || !is_array($configuration['devices'])) {
            throw new InvalidArgumentException('Normalized configuration devices must be an array.');
        }

        $preparedEntities = [];
        $stateVariableOwners = [];

        foreach ($configuration['devices'] as $device) {
            if (!is_array($device) || !isset($device['entities']) || !is_array($device['entities'])) {
                throw new InvalidArgumentException('Normalized device contract is invalid.');
            }

            foreach ($device['entities'] as $entity) {
                if (!is_array($entity)) {
                    throw new InvalidArgumentException('Normalized entity contract is invalid.');
                }

                $entityKey = MqttDiscoveryExporterCore::entityKey($device, $entity);
                $observedValues = self::validateEntityVariables($entityKey, $entity);
                $runtime = MqttDiscoveryExporterCore::buildRuntimePayloads(
                    $configuration,
                    $device,
                    $entity,
                    $observedValues
                );
                $discoveryPayload = MqttDiscoveryExporterCore::buildDiscoveryPayload(
                    $configuration,
                    $device,
                    $entity
                );
                $commandTopics = self::commandTopics($configuration, $device, $entity);
                $commandInstanceIdents = [];
                $commandEventIdents = [];
                $stateEventIdents = [];
                $stateVariableIDs = [];

                foreach (array_keys($commandTopics) as $commandType) {
                    $identity = self::resourceIdentity($entityKey, $commandType);
                    $commandInstanceIdents[$commandType] = 'MQTT_CMD_' . $identity;
                    $commandEventIdents[$commandType] = 'EV_CMD_' . $identity;
                }

                foreach ($entity['capabilities'] as $capability => $contract) {
                    if (!is_string($capability) || !is_array($contract)) {
                        throw new RuntimeException('Normalized capability contract is invalid: ' . $entityKey);
                    }
                    $identity = self::resourceIdentity($entityKey, $capability);
                    $stateEventIdents[$capability] = 'EV_STATE_' . $identity;
                    $stateVariableIDs[$capability] = $contract['stateVariableID'];
                    $stateVariableKey = (string)$contract['stateVariableID'];
                    if (isset($stateVariableOwners[$stateVariableKey])) {
                        throw new RuntimeException(
                            'State variable has ambiguous entity ownership: ' . $stateVariableKey
                        );
                    }
                    $stateVariableOwners[$stateVariableKey] = $entityKey . '.' . $capability;
                }

                $discoveryJson = MqttDiscoveryExporterCore::canonicalJson($discoveryPayload);
                $preparedEntities[$entityKey] = [
                    'device' => $device,
                    'entity' => $entity,
                    'discoveryTopic' => MqttDiscoveryExporterCore::discoveryTopic(
                        $configuration,
                        $device,
                        $entity
                    ),
                    'discoveryPayload' => $discoveryJson,
                    'discoveryHash' => hash('sha256', $discoveryJson),
                    'runtimeTopics' => $runtime['topics'],
                    'runtimeHash' => MqttDiscoveryExporterCore::payloadHash($runtime['topics']),
                    'uuid' => $discoveryPayload['unique_id'],
                    'commandTopics' => $commandTopics,
                    'commandInstanceIdents' => $commandInstanceIdents,
                    'commandEventIdents' => $commandEventIdents,
                    'stateEventIdents' => $stateEventIdents,
                    'stateVariableIDs' => $stateVariableIDs,
                ];
            }
        }

        ksort($preparedEntities);

        return $preparedEntities;
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, bool|int>
     */
    private static function validateEntityVariables(string $entityKey, array $entity): array
    {
        if (!isset($entity['capabilities']) || !is_array($entity['capabilities'])) {
            throw new RuntimeException('Normalized entity capabilities are missing: ' . $entityKey);
        }

        $expectedTypes = [
            'power' => 0,
            'brightness' => 1,
            'rgb' => 1,
            'colorTemperature' => 1,
        ];
        $observedValues = [];

        foreach ($entity['capabilities'] as $capability => $contract) {
            if (!is_string($capability) || !isset($expectedTypes[$capability]) || !is_array($contract)) {
                throw new RuntimeException('Unsupported normalized capability: ' . $entityKey);
            }

            $stateVariableID = $contract['stateVariableID'] ?? null;
            $actionVariableID = $contract['actionVariableID'] ?? null;
            if (!is_int($stateVariableID) || !is_int($actionVariableID)) {
                throw new RuntimeException('Capability variable IDs must be integers: ' . $entityKey . '.' . $capability);
            }

            self::validateVariableType($stateVariableID, $expectedTypes[$capability], 'state', $entityKey, $capability);
            self::validateVariableType($actionVariableID, $expectedTypes[$capability], 'action', $entityKey, $capability);
            if (!\HasAction($actionVariableID)) {
                throw new RuntimeException('Action variable has no action: ' . $entityKey . '.' . $capability);
            }

            $value = \GetValue($stateVariableID);
            if ($expectedTypes[$capability] === 0 && !is_bool($value)) {
                throw new RuntimeException('Boolean state value expected: ' . $entityKey . '.' . $capability);
            }
            if ($expectedTypes[$capability] === 1 && !is_int($value)) {
                throw new RuntimeException('Integer state value expected: ' . $entityKey . '.' . $capability);
            }
            $observedValues[$capability] = $value;
        }

        return $observedValues;
    }

    private static function validateVariableType(
        int $variableID,
        int $expectedType,
        string $role,
        string $entityKey,
        string $capability
    ): void {
        if ($variableID <= 0 || !\IPS_VariableExists($variableID)) {
            throw new InvalidArgumentException(sprintf(
                '%s variable does not exist: %s.%s',
                ucfirst($role),
                $entityKey,
                $capability
            ));
        }

        $variable = \IPS_GetVariable($variableID);
        if ($variable['VariableType'] !== $expectedType) {
            throw new RuntimeException(sprintf(
                '%s variable has an incompatible type: %s.%s',
                ucfirst($role),
                $entityKey,
                $capability
            ));
        }
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $device
     * @param array<string, mixed> $entity
     *
     * @return array<string, string>
     */
    private static function commandTopics(array $configuration, array $device, array $entity): array
    {
        $baseTopic = MqttDiscoveryExporterCore::runtimeBaseTopic($configuration, $device, $entity);
        $topics = ['power' => $baseTopic . '/set'];

        if (isset($entity['capabilities']['brightness'])) {
            $topics['brightness'] = $baseTopic . '/brightness/set';
        }
        if (isset($entity['capabilities']['rgb'])) {
            $topics['rgb'] = $baseTopic . '/rgb/set';
        }
        if (isset($entity['capabilities']['colorTemperature'])) {
            $topics['colorTemperature'] = $baseTopic . '/color_temp/set';
        }

        return $topics;
    }

    private static function resourceIdentity(string $entityKey, string $capability): string
    {
        return strtoupper(substr(hash('sha256', $entityKey . ':' . $capability), 0, 16));
    }

    /**
     * @param array<string, mixed> $previousEntities
     * @param array<string, array<string, mixed>> $preparedEntities
     */
    private static function assertCleanupNotRequired(
        array $previousEntities,
        array $preparedEntities,
        string $desiredModuleID
    ): void {
        $removed = MqttDiscoveryExporterCore::planRemovedEntries(
            $previousEntities,
            array_keys($preparedEntities)
        );
        if ($removed !== []) {
            throw new RuntimeException(
                'Reconcile would remove managed entities while cleanup is disabled: '
                . implode(', ', array_keys($removed))
            );
        }

        foreach ($previousEntities as $entityKey => $previous) {
            if (!is_array($previous) || !isset($preparedEntities[$entityKey])) {
                continue;
            }

            $prepared = $preparedEntities[$entityKey];
            if (($previous['adapterModuleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID) !== $desiredModuleID) {
                throw new RuntimeException('MQTT transport change requires cleanup: ' . $entityKey);
            }
            if (
                isset($previous['discoveryTopic'])
                && $previous['discoveryTopic'] !== $prepared['discoveryTopic']
            ) {
                throw new RuntimeException('Discovery topic change requires cleanup: ' . $entityKey);
            }

            self::assertNoRemovedValues(
                $previous,
                'runtimeTopics',
                array_keys($prepared['runtimeTopics']),
                $entityKey
            );
            self::assertNoRemovedValues($previous, 'commandTopics', $prepared['commandTopics'], $entityKey);
            self::assertNoRemovedValues(
                $previous,
                'commandInstanceIdents',
                $prepared['commandInstanceIdents'],
                $entityKey
            );
            self::assertNoRemovedValues(
                $previous,
                'commandEventIdents',
                $prepared['commandEventIdents'],
                $entityKey
            );
            self::assertNoRemovedValues(
                $previous,
                'stateEventIdents',
                $prepared['stateEventIdents'],
                $entityKey
            );
        }
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<int|string, mixed> $desired
     */
    private static function assertNoRemovedValues(
        array $previous,
        string $field,
        array $desired,
        string $entityKey
    ): void {
        if (!array_key_exists($field, $previous)) {
            return;
        }
        if (!is_array($previous[$field])) {
            throw new RuntimeException('Managed entity field is invalid: ' . $entityKey . '.' . $field);
        }

        $previousValues = array_values($previous[$field]);
        $desiredValues = array_values($desired);
        foreach ($previousValues as $previousValue) {
            if (!is_string($previousValue) || !in_array($previousValue, $desiredValues, true)) {
                throw new RuntimeException('Managed resource change requires cleanup: ' . $entityKey . '.' . $field);
            }
        }
    }

    /**
     * Creates the visible, device-oriented resource tree. Display names,
     * positions and icons are defaults applied only when an object is created;
     * stable Idents and parent relationships remain exporter-managed.
     *
     * @param array<string, array<string, mixed>> $preparedEntities
     * @param array<string, mixed> $registry
     *
     * @return array{registry: array<string, mixed>, devices: array<string, array<string, int|string>>}
     */
    private static function ensureResourceTree(
        int $ownerScriptID,
        array $preparedEntities,
        array $registry
    ): array {
        if ($preparedEntities === []) {
            return ['registry' => $registry, 'devices' => []];
        }

        $previousTree = $registry['resourceTree'] ?? ['devices' => []];
        if (!is_array($previousTree)) {
            throw new RuntimeException('Managed resource tree contract is invalid.');
        }
        $previousDevices = $previousTree['devices'] ?? [];
        if (!is_array($previousDevices)) {
            throw new RuntimeException('Managed resource tree devices contract is invalid.');
        }
        if (isset($previousTree['rootID'])) {
            $previousRootID = $previousTree['rootID'];
            $previousRootIdent = $previousTree['rootIdent'] ?? null;
            if (!is_int($previousRootID) || $previousRootIdent !== 'MQTT_DISCOVERY_EXPORTER_DEVICES') {
                throw new RuntimeException('Managed resource tree root identity is invalid.');
            }
            self::assertOwnedObject($previousRootID, 0, $ownerScriptID, $previousRootIdent);
        }

        $rootID = \SAEF_EnsureCategory(
            $ownerScriptID,
            'MQTT_DISCOVERY_EXPORTER_DEVICES',
            'Devices',
            1000,
            'Network',
            false
        );
        $resourceTree = [
            'rootID' => $rootID,
            'rootIdent' => 'MQTT_DISCOVERY_EXPORTER_DEVICES',
            'devices' => $previousDevices,
        ];
        $deviceDefinitions = [];
        foreach ($preparedEntities as $prepared) {
            $deviceKey = self::preparedDeviceKey($prepared);
            $device = $prepared['device'];
            $deviceName = $device['name'] ?? null;
            if (!is_string($deviceName) || trim($deviceName) === '') {
                throw new RuntimeException('Prepared device name is invalid: ' . $deviceKey);
            }
            $deviceDefinitions[$deviceKey] = $deviceName;
        }
        ksort($deviceDefinitions);

        $devices = [];
        $position = 10;
        foreach ($deviceDefinitions as $deviceKey => $deviceName) {
            $deviceIdent = 'DEVICE_' . strtoupper(substr(hash('sha256', $deviceKey), 0, 16));
            $previousDevice = $previousDevices[$deviceKey] ?? null;
            if ($previousDevice !== null) {
                if (!is_array($previousDevice)) {
                    throw new RuntimeException('Managed device resource tree contract is invalid: ' . $deviceKey);
                }
                self::assertOwnedObject(
                    $previousDevice['deviceCategoryID'],
                    0,
                    $rootID,
                    $deviceIdent
                );
                self::assertOwnedObject(
                    $previousDevice['commandsCategoryID'],
                    0,
                    $previousDevice['deviceCategoryID'],
                    'COMMANDS'
                );
                self::assertOwnedObject(
                    $previousDevice['publishersCategoryID'],
                    0,
                    $previousDevice['deviceCategoryID'],
                    'PUBLISHERS'
                );
            }
            $deviceCategoryID = \SAEF_EnsureCategory(
                $rootID,
                $deviceIdent,
                $deviceName,
                $position,
                'Bulb',
                false
            );
            $commandsCategoryID = \SAEF_EnsureCategory(
                $deviceCategoryID,
                'COMMANDS',
                'Commands',
                10,
                'Execute',
                false
            );
            $publishersCategoryID = \SAEF_EnsureCategory(
                $deviceCategoryID,
                'PUBLISHERS',
                'Publishers',
                20,
                'Network',
                false
            );
            $devices[$deviceKey] = [
                'deviceIdent' => $deviceIdent,
                'deviceCategoryID' => $deviceCategoryID,
                'commandsCategoryID' => $commandsCategoryID,
                'publishersCategoryID' => $publishersCategoryID,
            ];
            $position += 10;
        }

        $resourceTree['devices'] = $devices;
        $registry['resourceTree'] = $resourceTree;

        return ['registry' => $registry, 'devices' => $devices];
    }

    /** @param array<string, mixed> $prepared */
    private static function preparedDeviceKey(array $prepared): string
    {
        $device = $prepared['device'] ?? null;
        $deviceKey = is_array($device) ? ($device['id'] ?? null) : null;
        if (!is_string($deviceKey) || trim($deviceKey) === '') {
            throw new RuntimeException('Prepared device identity is invalid.');
        }

        return $deviceKey;
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $diagnostics
     *
     * @return array{diagnostics: array<string, mixed>, valueIDsByTopic: array<string, int>}
     */
    private static function ensurePublisherResources(
        int $ownerScriptID,
        array $configuration,
        array $diagnostics
    ): array {
        $transport = self::mqttTransportContract($configuration);
        $desiredPublishers = self::desiredPublisherContracts(
            $diagnostics['registry']['managedEntities'],
            $configuration['defaults']['retain']
        );
        $registry = $diagnostics['registry'];
        $previousPublishers = $registry['publishers'];
        $removed = MqttDiscoveryExporterCore::planRemovedEntries(
            $previousPublishers,
            array_keys($desiredPublishers)
        );
        if ($removed !== []) {
            throw new RuntimeException('Publisher removal requires cleanup while cleanup is disabled.');
        }

        $publishers = $previousPublishers;
        foreach ($desiredPublishers as $publisherKey => $desired) {
            $previous = $publishers[$publisherKey] ?? [];
            if (!is_array($previous)) {
                throw new RuntimeException('Publisher registry entry is invalid: ' . $publisherKey);
            }
            if (isset($previous['topic']) && $previous['topic'] !== $desired['topic']) {
                throw new RuntimeException('Publisher identity collision: ' . $publisherKey);
            }

            $publishers[$publisherKey] = [
                'resourceState' => ($previous['resourceState'] ?? null) === 'ready'
                    && ($previous['topic'] ?? null) === $desired['topic']
                    && ($previous['retain'] ?? null) === $desired['retain']
                    && ($previous['moduleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID)
                        === $transport['deviceModuleID']
                        ? 'ready'
                        : 'planned',
                'ident' => $desired['ident'],
                'topic' => $desired['topic'],
                'retain' => $desired['retain'],
                'moduleID' => $transport['deviceModuleID'],
                'parentCategoryID' => $desired['parentCategoryID'],
                'instanceID' => $previous['instanceID'] ?? null,
                'valueVariableID' => $previous['valueVariableID'] ?? null,
            ];
        }
        ksort($publishers);
        $registry['publishers'] = $publishers;
        if ($registry !== $diagnostics['registry']) {
            \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
        }
        $diagnostics['registry'] = $registry;

        $valueIDsByTopic = [];
        foreach ($desiredPublishers as $publisherKey => $desired) {
            $adapter = self::ensureMqttDeviceAdapter(
                $desired['parentCategoryID'],
                $transport['gatewayID'],
                $transport['deviceModuleID'],
                $desired['ident'],
                'MQTT Publisher ' . strtoupper(substr($publisherKey, 0, 12)),
                $desired['topic'],
                $desired['retain']
            );
            $registry['publishers'][$publisherKey]['resourceState'] = 'ready';
            $registry['publishers'][$publisherKey]['instanceID'] = $adapter['instanceID'];
            $registry['publishers'][$publisherKey]['valueVariableID'] = $adapter['valueVariableID'];
            $valueIDsByTopic[$desired['topic']] = $adapter['valueVariableID'];
        }
        \SAEF_WriteRegistry($diagnostics['registryID'], $registry);
        $diagnostics['registry'] = $registry;

        return ['diagnostics' => $diagnostics, 'valueIDsByTopic' => $valueIDsByTopic];
    }

    /**
     * @param array<string, mixed> $managedEntities
     *
     * @return array<string, array{ident: string, topic: string, retain: bool, parentCategoryID: int}>
     */
    private static function desiredPublisherContracts(array $managedEntities, bool $runtimeRetain): array
    {
        $publishers = [];

        foreach ($managedEntities as $entityKey => $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Managed entity publisher contract is invalid.');
            }
            $discoveryTopic = $entry['discoveryTopic'] ?? null;
            $runtimeTopics = $entry['runtimeTopics'] ?? null;
            $parentCategoryID = $entry['publisherParentCategoryID'] ?? null;
            if (!is_string($discoveryTopic) || !is_array($runtimeTopics) || !is_int($parentCategoryID)) {
                throw new RuntimeException('Managed entity topics are invalid: ' . $entityKey);
            }

            self::addPublisherContract($publishers, $discoveryTopic, true, $parentCategoryID);
            foreach ($runtimeTopics as $runtimeTopic) {
                if (!is_string($runtimeTopic)) {
                    throw new RuntimeException('Managed runtime topic is invalid: ' . $entityKey);
                }
                self::addPublisherContract($publishers, $runtimeTopic, $runtimeRetain, $parentCategoryID);
            }
        }

        ksort($publishers);

        return $publishers;
    }

    /**
     * @param array<string, array{ident: string, topic: string, retain: bool, parentCategoryID: int}> $publishers
     */
    private static function addPublisherContract(
        array &$publishers,
        string $topic,
        bool $retain,
        int $parentCategoryID
    ): void {
        $publisherKey = hash('sha256', $topic);
        if (isset($publishers[$publisherKey]) && $publishers[$publisherKey]['topic'] !== $topic) {
            throw new RuntimeException('Publisher topic hash collision.');
        }

        $publishers[$publisherKey] = [
            'ident' => 'MQTT_PUB_' . strtoupper(substr($publisherKey, 0, 16)),
            'topic' => $topic,
            'retain' => $retain,
            'parentCategoryID' => $parentCategoryID,
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, int> $valueIDsByTopic
     */
    private static function publishMessage(array $message, array $valueIDsByTopic): void
    {
        $topic = $message['topic'] ?? null;
        $payload = $message['payload'] ?? null;
        $retain = $message['retain'] ?? null;
        if (!is_string($topic) || !is_string($payload) || !is_bool($retain)) {
            throw new RuntimeException('Publication plan message contract is invalid.');
        }
        if (!isset($valueIDsByTopic[$topic])) {
            throw new RuntimeException('Publication plan has no owned publisher: ' . $topic);
        }

        if (!\RequestAction($valueIDsByTopic[$topic], $payload)) {
            throw new RuntimeException('MQTT publication action failed: ' . $topic);
        }
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $prepared
     *
     * @return array{
     *     commandInstanceIDs: array<string, int>,
     *     commandVariableIDs: array<string, int>,
     *     commandEventIDs: array<string, int>,
     *     stateEventIDs: array<string, int>,
     *     stateVariableIDs: array<string, int>
     * }
     */
    private static function ensureEntityResources(
        int $ownerScriptID,
        array $configuration,
        array $prepared,
        array $deviceResources
    ): array {
        $transport = self::mqttTransportContract($configuration);
        $device = $prepared['device'];
        $entity = $prepared['entity'];
        $entityName = (string)$device['name'] . ' ' . (string)$entity['name'];
        $commandInstanceIDs = [];
        $commandVariableIDs = [];
        $commandEventIDs = [];
        $stateEventIDs = [];
        $commandParentID = $deviceResources['commandsCategoryID'] ?? null;
        $publisherParentID = $deviceResources['publishersCategoryID'] ?? null;
        $deviceCategoryID = $deviceResources['deviceCategoryID'] ?? null;
        if (!is_int($commandParentID) || !is_int($publisherParentID) || !is_int($deviceCategoryID)) {
            throw new RuntimeException('Prepared device resource tree is invalid.');
        }

        foreach ($prepared['commandTopics'] as $commandType => $topic) {
            $instanceIdent = $prepared['commandInstanceIdents'][$commandType];
            $eventIdent = $prepared['commandEventIdents'][$commandType];
            $adapter = self::ensureMqttDeviceAdapter(
                $commandParentID,
                $transport['gatewayID'],
                $transport['deviceModuleID'],
                $instanceIdent,
                'HA Command ' . $entityName . ' ' . $commandType,
                $topic,
                false
            );
            $eventID = \SAEF_EnsureTriggeredScriptEvent(
                $ownerScriptID,
                $eventIdent,
                'HA Command Trigger ' . $entityName . ' ' . $commandType,
                $ownerScriptID,
                $adapter['valueVariableID'],
                0,
                true,
                3000 + count($commandEventIDs),
                true,
                false
            );

            $commandInstanceIDs[$commandType] = $adapter['instanceID'];
            $commandVariableIDs[$commandType] = $adapter['valueVariableID'];
            $commandEventIDs[$commandType] = $eventID;
        }

        foreach ($prepared['stateVariableIDs'] as $capability => $stateVariableID) {
            $stateEventIDs[$capability] = \SAEF_EnsureTriggeredScriptEvent(
                $ownerScriptID,
                $prepared['stateEventIdents'][$capability],
                'State Trigger ' . $entityName . ' ' . $capability,
                $ownerScriptID,
                $stateVariableID,
                1,
                true,
                5000 + count($stateEventIDs),
                true,
                false
            );
        }

        return [
            'commandInstanceIDs' => $commandInstanceIDs,
            'commandVariableIDs' => $commandVariableIDs,
            'commandEventIDs' => $commandEventIDs,
            'stateEventIDs' => $stateEventIDs,
            'stateVariableIDs' => $prepared['stateVariableIDs'],
            'adapterModuleID' => $transport['deviceModuleID'],
            'deviceCategoryID' => $deviceCategoryID,
            'commandParentCategoryID' => $commandParentID,
            'publisherParentCategoryID' => $publisherParentID,
        ];
    }

    /** @return array{instanceID: int, valueVariableID: int} */
    private static function ensureMqttDeviceAdapter(
        int $parentID,
        int $gatewayID,
        string $deviceModuleID,
        string $ident,
        string $name,
        string $topic,
        bool $retain
    ): array {
        $instanceID = \SAEF_EnsureInstance(
            $parentID,
            $ident,
            $name,
            $deviceModuleID,
            2000,
            'Network',
            true,
            false
        );
        $changed = false;
        $instance = \IPS_GetInstance($instanceID);
        $connectionID = $instance['ConnectionID'] ?? null;
        if (!is_int($connectionID)) {
            throw new RuntimeException('MQTT command adapter has an invalid connection contract: ' . $ident);
        }
        if ($connectionID !== $gatewayID) {
            if ($connectionID > 0) {
                \IPS_DisconnectInstance($instanceID);
            }
            \IPS_ConnectInstance($instanceID, $gatewayID);
            $changed = true;
        }

        $desiredConfiguration = [
            'Topic' => $topic,
            'Type' => 3,
            'Retain' => $retain,
        ];
        $currentConfiguration = self::readInstanceConfiguration($instanceID, $ident);
        foreach ($desiredConfiguration as $key => $value) {
            if (($currentConfiguration[$key] ?? null) !== $value) {
                $changed = true;
                break;
            }
        }
        if ($changed) {
            \IPS_SetConfiguration(
                $instanceID,
                json_encode($desiredConfiguration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            );
        }

        $valueVariableID = @\IPS_GetObjectIDByIdent('Value', $instanceID);
        if ($changed || $valueVariableID === false) {
            \IPS_ApplyChanges($instanceID);
            $valueVariableID = @\IPS_GetObjectIDByIdent('Value', $instanceID);
        }
        if ($valueVariableID === false || !\IPS_VariableExists($valueVariableID)) {
            throw new RuntimeException('MQTT command adapter Value variable is missing: ' . $ident);
        }
        $variable = \IPS_GetVariable($valueVariableID);
        if ($variable['VariableType'] !== 3) {
            throw new RuntimeException('MQTT Device Value variable must be string: ' . $ident);
        }
        if (!\HasAction($valueVariableID)) {
            throw new RuntimeException('MQTT Device Value variable has no action: ' . $ident);
        }

        return ['instanceID' => $instanceID, 'valueVariableID' => $valueVariableID];
    }

    /** @return array<string, mixed> */
    private static function readInstanceConfiguration(int $instanceID, string $ident): array
    {
        try {
            $configuration = json_decode(
                \IPS_GetConfiguration($instanceID),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'MQTT command adapter configuration is invalid JSON: ' . $ident,
                0,
                $exception
            );
        }

        if (!is_array($configuration)) {
            throw new RuntimeException('MQTT command adapter configuration must be an object: ' . $ident);
        }

        return $configuration;
    }

    /**
     * @param array<string, mixed> $prepared
     * @param array<string, mixed> $resources
     * @param array<string, mixed> $previous
     *
     * @return array<string, mixed>
     */
    private static function managedEntityMetadata(array $prepared, array $resources, array $previous): array
    {
        return [
            'schemaVersion' => 1,
            'resourceState' => 'ready',
            'uuid' => $prepared['uuid'],
            'discoveryTopic' => $prepared['discoveryTopic'],
            'runtimeTopics' => array_keys($prepared['runtimeTopics']),
            'commandTopics' => $prepared['commandTopics'],
            'commandInstanceIdents' => $prepared['commandInstanceIdents'],
            'commandInstanceIDs' => $resources['commandInstanceIDs'],
            'commandVariableIDs' => $resources['commandVariableIDs'],
            'commandEventIdents' => $prepared['commandEventIdents'],
            'commandEventIDs' => $resources['commandEventIDs'],
            'stateEventIdents' => $prepared['stateEventIdents'],
            'stateEventIDs' => $resources['stateEventIDs'],
            'stateVariableIDs' => $resources['stateVariableIDs'],
            'adapterModuleID' => $resources['adapterModuleID'],
            'deviceKey' => self::preparedDeviceKey($prepared),
            'deviceCategoryID' => $resources['deviceCategoryID'],
            'commandParentCategoryID' => $resources['commandParentCategoryID'],
            'publisherParentCategoryID' => $resources['publisherParentCategoryID'],
            'desiredDiscoveryHash' => $prepared['discoveryHash'],
            'desiredRuntimeHash' => $prepared['runtimeHash'],
            'discoveryHash' => $previous['discoveryHash'] ?? null,
            'discoveryPublished' => $previous['discoveryPublished'] ?? false,
            'runtimeHash' => $previous['runtimeHash'] ?? null,
            'runtimePublished' => $previous['runtimePublished'] ?? false,
        ];
    }

    /**
     * Records exact desired ownership before object creation starts.
     *
     * @param array<string, mixed> $prepared
     * @param array<string, mixed> $previous
     *
     * @return array<string, mixed>
     */
    private static function plannedManagedEntityMetadata(
        array $prepared,
        array $previous,
        string $adapterModuleID,
        array $deviceResources
    ): array {
        $previousAdapterModuleID = $previous['adapterModuleID'] ?? self::MQTT_SERVER_DEVICE_MODULE_ID;
        $resourceContractUnchanged = ($previous['commandInstanceIdents'] ?? null)
                === $prepared['commandInstanceIdents']
            && ($previous['commandEventIdents'] ?? null) === $prepared['commandEventIdents']
            && ($previous['stateEventIdents'] ?? null) === $prepared['stateEventIdents']
            && ($previous['commandTopics'] ?? null) === $prepared['commandTopics']
            && ($previous['runtimeTopics'] ?? null) === array_keys($prepared['runtimeTopics'])
            && ($previous['discoveryTopic'] ?? null) === $prepared['discoveryTopic']
            && $previousAdapterModuleID === $adapterModuleID;

        return [
            'schemaVersion' => 1,
            'resourceState' => $resourceContractUnchanged
                && ($previous['resourceState'] ?? null) === 'ready'
                    ? 'ready'
                    : 'planned',
            'uuid' => $prepared['uuid'],
            'discoveryTopic' => $prepared['discoveryTopic'],
            'runtimeTopics' => array_keys($prepared['runtimeTopics']),
            'commandTopics' => $prepared['commandTopics'],
            'commandInstanceIdents' => $prepared['commandInstanceIdents'],
            'commandInstanceIDs' => $previous['commandInstanceIDs'] ?? [],
            'commandVariableIDs' => $previous['commandVariableIDs'] ?? [],
            'commandEventIdents' => $prepared['commandEventIdents'],
            'commandEventIDs' => $previous['commandEventIDs'] ?? [],
            'stateEventIdents' => $prepared['stateEventIdents'],
            'stateEventIDs' => $previous['stateEventIDs'] ?? [],
            'stateVariableIDs' => $prepared['stateVariableIDs'],
            'adapterModuleID' => $adapterModuleID,
            'deviceKey' => self::preparedDeviceKey($prepared),
            'deviceCategoryID' => $deviceResources['deviceCategoryID'],
            'commandParentCategoryID' => $deviceResources['commandsCategoryID'],
            'publisherParentCategoryID' => $deviceResources['publishersCategoryID'],
            'desiredDiscoveryHash' => $prepared['discoveryHash'],
            'desiredRuntimeHash' => $prepared['runtimeHash'],
            'discoveryHash' => $previous['discoveryHash'] ?? null,
            'discoveryPublished' => $previous['discoveryPublished'] ?? false,
            'runtimeHash' => $previous['runtimeHash'] ?? null,
            'runtimePublished' => $previous['runtimePublished'] ?? false,
        ];
    }

    /**
     * @return list<array{
     *     ident: string,
     *     name: string,
     *     type: int,
     *     profile: string,
     *     position: int,
     *     icon: string|null
     * }>
     */
    private static function statisticsDefinitions(): array
    {
        return [
            self::statisticDefinition('EXECUTIONS', 'Executions', 200),
            self::statisticDefinition('SUCCESSES', 'Successes', 210),
            self::statisticDefinition('FAILURES', 'Failures', 220),
            self::statisticDefinition('COMMANDS', 'Commands', 230),
            self::statisticDefinition('PUBLISHES', 'Publishes', 240),
            self::statisticDefinition('PUBLISH_SKIPS', 'Publish Skips', 250),
            self::statisticDefinition('LAST_RUN', 'Last Run', 300, '~UnixTimestamp'),
            self::statisticDefinition('LAST_SUCCESS', 'Last Success', 310, '~UnixTimestamp'),
            self::statisticDefinition('LAST_FAILURE', 'Last Failure', 320, '~UnixTimestamp'),
        ];
    }

    /**
     * @return array{
     *     ident: string,
     *     name: string,
     *     type: int,
     *     profile: string,
     *     position: int,
     *     icon: string|null
     * }
     */
    private static function statisticDefinition(
        string $ident,
        string $name,
        int $position,
        string $profile = ''
    ): array {
        return [
            'ident' => $ident,
            'name' => $name,
            'type' => 1,
            'profile' => $profile,
            'position' => $position,
            'icon' => null,
        ];
    }

    /** @param array<string, mixed> $configuration */
    private static function validateDiagnosticsInput(int $ownerScriptID, array $configuration): void
    {
        if ($ownerScriptID <= 0 || !\IPS_ScriptExists($ownerScriptID)) {
            throw new InvalidArgumentException('Owner script does not exist: ' . $ownerScriptID);
        }

        if (!isset($configuration['version']) || !is_string($configuration['version'])) {
            throw new InvalidArgumentException('Normalized configuration version must be a non-empty string.');
        }

        if (trim($configuration['version']) === '') {
            throw new InvalidArgumentException('Normalized configuration version must be a non-empty string.');
        }
    }

    /** @param array<string, mixed> $registry */
    private static function validateRegistry(array $registry): void
    {
        if ($registry === []) {
            return;
        }

        if (($registry['schemaVersion'] ?? null) !== self::REGISTRY_SCHEMA_VERSION) {
            throw new RuntimeException('Managed state registry has an unsupported schema version.');
        }

        foreach (['managedEntities', 'commandIndex', 'stateIndex'] as $arrayKey) {
            if (!isset($registry[$arrayKey]) || !is_array($registry[$arrayKey])) {
                throw new RuntimeException('Managed state registry field must be an array: ' . $arrayKey);
            }
        }
        if (isset($registry['publishers']) && !is_array($registry['publishers'])) {
            throw new RuntimeException('Managed state registry field must be an array: publishers');
        }
        if (isset($registry['cleanupTombstones']) && !is_array($registry['cleanupTombstones'])) {
            throw new RuntimeException('Managed state registry field must be an array: cleanupTombstones');
        }
        if (isset($registry['resourceTree']) && !is_array($registry['resourceTree'])) {
            throw new RuntimeException('Managed state registry field must be an array: resourceTree');
        }
        foreach (($registry['cleanupTombstones'] ?? []) as $publisherKey => $cleared) {
            if (
                !is_string($publisherKey)
                || preg_match('/^[0-9a-f]{64}$/', $publisherKey) !== 1
                || $cleared !== true
                || !isset($registry['publishers'][$publisherKey])
            ) {
                throw new RuntimeException('Managed cleanup tombstone marker is invalid.');
            }
        }

        foreach (
            [
                'configurationHash',
                'previousConfigurationHash',
                'preparedConfigurationHash',
                'publishedConfigurationHash',
            ] as $hashKey
        ) {
            $hash = $registry[$hashKey] ?? null;
            if ($hash !== null && (!is_string($hash) || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1)) {
                throw new RuntimeException('Managed state registry contains an invalid hash: ' . $hashKey);
            }
        }

        $lastReconcile = $registry['lastSuccessfulReconcile'] ?? null;
        if ($lastReconcile !== null && (!is_int($lastReconcile) || $lastReconcile < 0)) {
            throw new RuntimeException('Managed state registry contains an invalid reconcile timestamp.');
        }
    }

    /**
     * @param array<string, mixed> $registry
     *
     * @return array<string, mixed>
     */
    private static function updateConfigurationMetadata(
        array $registry,
        string $configurationHash,
        string $exporterVersion
    ): array {
        $currentHash = $registry['configurationHash'] ?? null;
        $previousHash = $registry['previousConfigurationHash'] ?? null;

        if ($currentHash !== null && $currentHash !== $configurationHash) {
            $previousHash = $currentHash;
        }

        $registry['schemaVersion'] = self::REGISTRY_SCHEMA_VERSION;
        $registry['exporterVersion'] = $exporterVersion;
        $registry['configurationHash'] = $configurationHash;
        $registry['previousConfigurationHash'] = $previousHash;
        $registry['managedEntities'] ??= [];
        $registry['commandIndex'] ??= [];
        $registry['stateIndex'] ??= [];
        $registry['publishers'] ??= [];
        $registry['cleanupTombstones'] ??= [];
        $registry['resourceTree'] ??= ['devices' => []];
        $registry['lastSuccessfulReconcile'] ??= null;

        return $registry;
    }

    /**
     * @param array<string, int> $statisticIDs
     */
    private static function recordFailure(
        ?int $errorRingBufferID,
        array $statisticIDs,
        Throwable $exception,
        string $phase
    ): void {
        if ($errorRingBufferID !== null) {
            try {
                \SAEF_AppendErrorRingBufferEntry(
                    $errorRingBufferID,
                    'Exporter failure during ' . $phase . '.',
                    self::ERROR_CAPACITY,
                    [
                        'phase' => $phase,
                        'type' => $exception::class,
                    ]
                );
            } catch (Throwable $diagnosticException) {
                \IPS_LogMessage(
                    'SAEF MQTT Discovery Exporter',
                    'Unable to record diagnostic error: ' . $diagnosticException->getMessage()
                );
            }
        }

        if (isset($statisticIDs['FAILURES'])) {
            try {
                \SAEF_IncrementStatistic($statisticIDs['FAILURES']);
                if (isset($statisticIDs['LAST_FAILURE'])) {
                    \SAEF_SetStatisticTimestamp($statisticIDs['LAST_FAILURE']);
                }
            } catch (Throwable $diagnosticException) {
                \IPS_LogMessage(
                    'SAEF MQTT Discovery Exporter',
                    'Unable to update diagnostic failure statistics: ' . $diagnosticException->getMessage()
                );
            }
        }
    }
}
