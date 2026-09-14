<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\MqttDiscoveryExporter\Deployment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Case-study-local transaction for adopting the event-value snapshot owner
 * contract after the backward-compatible runtime fileset is active.
 */
final class MqttSupersessionOwnerMigration
{
    public const PURPOSE = 'saef-mqtt-supersession-owner-migration';
    public const TARGET_ID = 'saef-mqtt-discovery-exporter';
    public const AUTOMATION_ACTION_ID = '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}';

    private const FORMAT_VERSION = 1;
    private const LOCK_NAME = 'SAEF_MQTT_SUPERSESSION_OWNER_MIGRATION';
    private const LOCK_TIMEOUT_MILLISECONDS = 5000;
    private const MAX_PLAN_LIFETIME_SECONDS = 3600;

    /**
     * @param array<string, mixed> $input
     * @param array<int, callable(): mixed> $configurationLoaders
     * @param callable(array<string, mixed>): string $configurationHasher
     * @param array{runtimeSha256: string, coreSha256: string, filesetSha256: string} $runtimeIdentity
     *
     * @return array<string, mixed>
     */
    public static function preflight(
        array $input,
        MqttSupersessionMigrationEnvironment $environment,
        array $configurationLoaders,
        callable $configurationHasher,
        array $runtimeIdentity,
        int $lifetimeSeconds = 900
    ): array {
        self::validateInput($input, $configurationLoaders);
        self::validateRuntimeIdentity($input, $runtimeIdentity);
        if ($lifetimeSeconds < 60 || $lifetimeSeconds > self::MAX_PLAN_LIFETIME_SECONDS) {
            throw new InvalidArgumentException('Migration plan lifetime is out of bounds.');
        }

        $owners = self::inspectOwners(
            $input,
            $environment,
            $configurationLoaders,
            $configurationHasher,
            'original',
            null
        );
        $createdAt = $environment->now()->setTimezone(new DateTimeZone('UTC'));
        $plan = [
            'formatVersion' => self::FORMAT_VERSION,
            'purpose' => self::PURPOSE,
            'targetId' => self::TARGET_ID,
            'allowedOperation' => 'apply',
            'repositoryBaseCommit' => $input['repositoryBaseCommit'],
            'createdAtUtc' => $createdAt->format('Y-m-d\TH:i:s.u\Z'),
            'expiresAtUtc' => $createdAt->modify('+' . $lifetimeSeconds . ' seconds')->format('Y-m-d\TH:i:s.u\Z'),
            'nonce' => $environment->nonce(),
            'inputSha256' => self::inputIdentitySha256($input),
            'runtimeIdentity' => $runtimeIdentity,
            'owners' => $owners,
        ];
        $planSha256 = self::sha256($plan);

        return self::status([
            'operation' => 'preflight',
            'outcome' => 'ready',
            'exitCode' => 0,
            'planSha256' => $planSha256,
            'reviewPlan' => $plan,
            'ownerCount' => count($owners),
            'eventCount' => self::eventCount($owners),
            'commandEventCount' => self::eventCount($owners, 'command'),
            'stateEventCount' => self::eventCount($owners, 'state'),
            'mutationAttempted' => false,
            'sourceMutationAttempted' => false,
            'eventMutationAttempted' => false,
            'diagnosticsInitializationAttempted' => false,
            'claimAttempted' => false,
            'claimCreated' => false,
            'claimState' => 'absent',
            'rollbackAttempted' => false,
            'rollbackSucceeded' => false,
            'mqttPublishAttempted' => false,
            'deviceActionAttempted' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $plan
     * @param array<int, callable(): mixed> $configurationLoaders
     * @param callable(array<string, mixed>): string $configurationHasher
     * @param callable(int, array<string, mixed>): array<string, mixed> $diagnosticsInitializer
     * @param array{runtimeSha256: string, coreSha256: string, filesetSha256: string} $runtimeIdentity
     *
     * @return array<string, mixed>
     */
    public static function apply(
        array $input,
        array $plan,
        string $expectedPlanSha256,
        MqttSupersessionMigrationEnvironment $environment,
        array $configurationLoaders,
        callable $configurationHasher,
        callable $diagnosticsInitializer,
        array $runtimeIdentity
    ): array {
        self::validateInput($input, $configurationLoaders);
        self::validatePlan($input, $plan, $expectedPlanSha256, $environment);
        self::validateRuntimeIdentity($input, $runtimeIdentity);

        if (!$environment->semaphoreEnter(self::LOCK_NAME, self::LOCK_TIMEOUT_MILLISECONDS)) {
            throw new RuntimeException('Migration semaphore timed out.');
        }

        $mutationAttempted = false;
        $sourceMutationAttempted = false;
        $eventMutationAttempted = false;
        $diagnosticsInitializationAttempted = false;
        $claimAttempted = false;
        $claimCreated = false;
        $claimState = '';
        $rollbackAttempted = false;
        $rollbackSucceeded = false;

        try {
            $baseline = self::inspectOwners(
                $input,
                $environment,
                $configurationLoaders,
                $configurationHasher,
                'original',
                null
            );
            if (!hash_equals(self::sha256($plan['owners']), self::sha256($baseline))) {
                throw new RuntimeException('Migration baseline differs from the reviewed plan.');
            }

            $claimAttempted = true;
            if (!$environment->createClaim($expectedPlanSha256, $plan['nonce'])) {
                throw new RuntimeException('Migration approval plan was already claimed.');
            }
            $claimCreated = true;
            $claimState = 'claimed';

            $mutationAttempted = true;
            $eventMutationAttempted = true;
            self::setEvents($plan['owners'], $environment, false);
            self::assertEventActivity($plan['owners'], $environment, false);
            $environment->transitionClaim(
                $expectedPlanSha256,
                $plan['nonce'],
                $claimState,
                'events_disabled'
            );
            $claimState = 'events_disabled';

            $sourceMutationAttempted = true;
            self::setOwnerSources($input, $environment, 'candidate');
            self::assertOwnerSourceState($input, $environment, 'candidate');
            $environment->transitionClaim(
                $expectedPlanSha256,
                $plan['nonce'],
                $claimState,
                'sources_updated'
            );
            $claimState = 'sources_updated';

            $diagnosticsInitializationAttempted = true;
            foreach ($input['owners'] as $owner) {
                $ownerID = $owner['ownerScriptId'];
                $configuration = $configurationLoaders[$ownerID]();
                if (!is_array($configuration)) {
                    throw new RuntimeException('Configuration loader did not return an array.');
                }
                $diagnosticsInitializer($ownerID, $configuration);
            }
            $environment->transitionClaim(
                $expectedPlanSha256,
                $plan['nonce'],
                $claimState,
                'diagnostics_initialized'
            );
            $claimState = 'diagnostics_initialized';

            self::inspectOwners(
                $input,
                $environment,
                $configurationLoaders,
                $configurationHasher,
                'candidate',
                false,
                true
            );

            self::restoreReviewedEventActivity($plan['owners'], $environment);
            $postflight = self::inspectOwners(
                $input,
                $environment,
                $configurationLoaders,
                $configurationHasher,
                'candidate',
                null,
                true
            );
            self::assertReviewedEventState($plan['owners'], $postflight);
            $environment->transitionClaim(
                $expectedPlanSha256,
                $plan['nonce'],
                $claimState,
                'completed'
            );
            $claimState = 'completed';

            return self::status([
                'operation' => 'apply',
                'outcome' => 'migrated',
                'exitCode' => 0,
                'planSha256' => $expectedPlanSha256,
                'ownerCount' => count($postflight),
                'eventCount' => self::eventCount($postflight),
                'mutationAttempted' => $mutationAttempted,
                'sourceMutationAttempted' => $sourceMutationAttempted,
                'eventMutationAttempted' => $eventMutationAttempted,
                'diagnosticsInitializationAttempted' => $diagnosticsInitializationAttempted,
                'claimAttempted' => $claimAttempted,
                'claimCreated' => $claimCreated,
                'claimState' => $claimState,
                'rollbackAttempted' => false,
                'rollbackSucceeded' => false,
                'diagnosticsRetainedOnRollback' => true,
                'mqttPublishAttempted' => false,
                'deviceActionAttempted' => false,
            ]);
        } catch (Throwable $exception) {
            if ($mutationAttempted) {
                $rollbackAttempted = true;
                try {
                    self::restoreOriginalState($input, $plan['owners'], $environment);
                    $rollbackSucceeded = true;
                    if ($claimCreated && $claimState !== '') {
                        $environment->transitionClaim(
                            $expectedPlanSha256,
                            $plan['nonce'],
                            $claimState,
                            'rolled_back'
                        );
                        $claimState = 'rolled_back';
                    }
                } catch (Throwable $rollbackException) {
                    return self::status([
                        'operation' => 'apply',
                        'outcome' => 'manual_recovery_required',
                        'exitCode' => 40,
                        'planSha256' => $expectedPlanSha256,
                        'failureCode' => 'rollback',
                        'failureType' => $exception::class,
                        'failureDetailSha256' => hash('sha256', $exception->getMessage()),
                        'rollbackFailureType' => $rollbackException::class,
                        'rollbackFailureDetailSha256' => hash('sha256', $rollbackException->getMessage()),
                        'mutationAttempted' => true,
                        'sourceMutationAttempted' => $sourceMutationAttempted,
                        'eventMutationAttempted' => $eventMutationAttempted,
                        'diagnosticsInitializationAttempted' => $diagnosticsInitializationAttempted,
                        'claimAttempted' => $claimAttempted,
                        'claimCreated' => $claimCreated,
                        'claimState' => $claimState,
                        'rollbackAttempted' => true,
                        'rollbackSucceeded' => false,
                        'diagnosticsRetainedOnRollback' => true,
                        'mqttPublishAttempted' => false,
                        'deviceActionAttempted' => false,
                    ]);
                }
            }

            return self::status([
                'operation' => 'apply',
                'outcome' => $rollbackSucceeded ? 'rolled_back' : 'failed',
                'exitCode' => $rollbackSucceeded ? 30 : 10,
                'planSha256' => $expectedPlanSha256,
                'failureCode' => $mutationAttempted ? 'transaction' : 'fresh_preflight',
                'failureType' => $exception::class,
                'failureDetailSha256' => hash('sha256', $exception->getMessage()),
                'mutationAttempted' => $mutationAttempted,
                'sourceMutationAttempted' => $sourceMutationAttempted,
                'eventMutationAttempted' => $eventMutationAttempted,
                'diagnosticsInitializationAttempted' => $diagnosticsInitializationAttempted,
                'claimAttempted' => $claimAttempted,
                'claimCreated' => $claimCreated,
                'claimState' => $claimState,
                'rollbackAttempted' => $rollbackAttempted,
                'rollbackSucceeded' => $rollbackSucceeded,
                'diagnosticsRetainedOnRollback' => true,
                'mqttPublishAttempted' => false,
                'deviceActionAttempted' => false,
            ]);
        } finally {
            $environment->semaphoreLeave(self::LOCK_NAME);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $plan
     * @param array<int, callable(): mixed> $configurationLoaders
     * @param callable(array<string, mixed>): string $configurationHasher
     * @param array{runtimeSha256: string, coreSha256: string, filesetSha256: string} $runtimeIdentity
     *
     * @return array<string, mixed>
     */
    public static function inspect(
        array $input,
        array $plan,
        string $expectedPlanSha256,
        MqttSupersessionMigrationEnvironment $environment,
        array $configurationLoaders,
        callable $configurationHasher,
        array $runtimeIdentity
    ): array {
        self::validateInput($input, $configurationLoaders);
        self::validatePlanHashAndIdentity($input, $plan, $expectedPlanSha256);
        self::validateRuntimeIdentity($input, $runtimeIdentity);

        if (!$environment->semaphoreEnter(self::LOCK_NAME, self::LOCK_TIMEOUT_MILLISECONDS)) {
            throw new RuntimeException('Migration semaphore timed out.');
        }
        try {
            $claimState = $environment->getClaimState($expectedPlanSha256, $plan['nonce']);
            $sourceStates = [];
            foreach ($input['owners'] as $owner) {
                $source = $environment->getScriptContent($owner['ownerScriptId']);
                $hash = hash('sha256', $source);
                if (hash_equals($owner['candidateSourceSha256'], $hash)) {
                    $sourceStates[] = 'candidate';
                } elseif (hash_equals($owner['originalSourceSha256'], $hash)) {
                    $sourceStates[] = 'original';
                } else {
                    $sourceStates[] = 'unknown';
                }
            }

            $state = 'manual_recovery_required';
            if (count(array_unique($sourceStates)) === 1 && $sourceStates[0] === 'candidate') {
                $owners = self::inspectOwners(
                    $input,
                    $environment,
                    $configurationLoaders,
                    $configurationHasher,
                    'candidate',
                    null,
                    true
                );
                self::assertReviewedEventState($plan['owners'], $owners);
                if ($claimState === 'completed') {
                    $state = 'migrated';
                }
            } elseif (count(array_unique($sourceStates)) === 1 && $sourceStates[0] === 'original') {
                $owners = self::inspectOwners(
                    $input,
                    $environment,
                    $configurationLoaders,
                    $configurationHasher,
                    'original',
                    null
                );
                self::assertReviewedEventState($plan['owners'], $owners);
                if ($claimState === null) {
                    $state = 'baseline';
                } elseif ($claimState === 'rolled_back') {
                    $state = 'rolled_back';
                }
            }

            return self::status([
                'operation' => 'inspect',
                'outcome' => $state === 'manual_recovery_required' ? $state : 'inspected',
                'exitCode' => $state === 'manual_recovery_required' ? 40 : 0,
                'inspectionState' => $state,
                'planSha256' => $expectedPlanSha256,
                'claimState' => $claimState ?? 'absent',
                'mutationAttempted' => false,
                'sourceMutationAttempted' => false,
                'eventMutationAttempted' => false,
                'diagnosticsInitializationAttempted' => false,
                'rollbackAttempted' => false,
                'rollbackSucceeded' => false,
                'mqttPublishAttempted' => false,
                'deviceActionAttempted' => false,
            ]);
        } finally {
            $environment->semaphoreLeave(self::LOCK_NAME);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $plan
     * @param array<int, callable(): mixed> $configurationLoaders
     *
     * @return array<string, mixed>
     */
    public static function rollback(
        array $input,
        array $plan,
        string $expectedPlanSha256,
        MqttSupersessionMigrationEnvironment $environment,
        array $configurationLoaders
    ): array {
        self::validateInput($input, $configurationLoaders);
        self::validatePlanHashAndIdentity($input, $plan, $expectedPlanSha256);
        if (!$environment->semaphoreEnter(self::LOCK_NAME, self::LOCK_TIMEOUT_MILLISECONDS)) {
            throw new RuntimeException('Migration semaphore timed out.');
        }
        try {
            $claimState = $environment->getClaimState($expectedPlanSha256, $plan['nonce']);
            if ($claimState === null) {
                throw new RuntimeException('Migration approval claim is missing.');
            }
            self::restoreOriginalState($input, $plan['owners'], $environment);
            if ($claimState !== 'rolled_back') {
                $environment->transitionClaim(
                    $expectedPlanSha256,
                    $plan['nonce'],
                    $claimState,
                    'rolled_back'
                );
            }

            return self::status([
                'operation' => 'rollback',
                'outcome' => 'rolled_back',
                'exitCode' => 0,
                'planSha256' => $expectedPlanSha256,
                'mutationAttempted' => true,
                'sourceMutationAttempted' => true,
                'eventMutationAttempted' => true,
                'diagnosticsInitializationAttempted' => false,
                'claimAttempted' => false,
                'claimCreated' => false,
                'claimState' => 'rolled_back',
                'rollbackAttempted' => true,
                'rollbackSucceeded' => true,
                'diagnosticsRetainedOnRollback' => true,
                'mqttPublishAttempted' => false,
                'deviceActionAttempted' => false,
            ]);
        } finally {
            $environment->semaphoreLeave(self::LOCK_NAME);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, callable(): mixed> $configurationLoaders
     * @param callable(array<string, mixed>): string $configurationHasher
     *
     * @return list<array<string, mixed>>
     */
    private static function inspectOwners(
        array $input,
        MqttSupersessionMigrationEnvironment $environment,
        array $configurationLoaders,
        callable $configurationHasher,
        string $sourceState,
        ?bool $activeOverride,
        bool $requireSupersessionDiagnostics = false
    ): array {
        $owners = [];
        foreach ($input['owners'] as $owner) {
            $ownerID = $owner['ownerScriptId'];
            if (!$environment->scriptExists($ownerID)) {
                throw new RuntimeException('Migration owner script does not exist.');
            }
            $source = $environment->getScriptContent($ownerID);
            $expectedSourceHash = $sourceState === 'candidate'
                ? $owner['candidateSourceSha256']
                : $owner['originalSourceSha256'];
            if (!hash_equals($expectedSourceHash, hash('sha256', $source))) {
                throw new RuntimeException('Migration owner source differs.');
            }

            $configuration = $configurationLoaders[$ownerID]();
            if (!is_array($configuration)) {
                throw new RuntimeException('Configuration loader did not return an array.');
            }
            $configurationHash = $configurationHasher($configuration);
            self::assertSha256($configurationHash, 'Configuration hash');

            $diagnosticsID = self::requiredChild(
                $environment,
                $ownerID,
                'MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS',
                0
            );
            $registryID = self::requiredChild(
                $environment,
                $diagnosticsID,
                'MANAGED_STATE_REGISTRY',
                2
            );
            $registry = self::decodeObject($environment->getValue($registryID), 'Managed Registry');
            if (
                ($registry['schemaVersion'] ?? null) !== 1
                || ($registry['preparedConfigurationHash'] ?? null) !== $configurationHash
                || ($registry['publishedConfigurationHash'] ?? null) !== $configurationHash
                || !is_array($registry['managedEntities'] ?? null)
                || !is_array($registry['commandIndex'] ?? null)
                || !is_array($registry['stateIndex'] ?? null)
            ) {
                throw new RuntimeException('Managed Registry contract differs.');
            }

            $events = self::collectEvents($environment, $ownerID, $registry, $activeOverride);
            $commandCount = count(array_filter(
                $events,
                static fn (array $event): bool => $event['kind'] === 'command'
            ));
            $stateCount = count($events) - $commandCount;
            if (
                $commandCount !== $owner['expectedCommandEventCount']
                || $stateCount !== $owner['expectedStateEventCount']
                || count($events) !== $owner['expectedEventCount']
            ) {
                throw new RuntimeException('Owner event count differs.');
            }

            $arbitrationID = self::optionalChild(
                $environment,
                $diagnosticsID,
                'COMMAND_ARBITRATION_REGISTRY',
                2
            );
            $supersededID = self::optionalChild(
                $environment,
                $diagnosticsID,
                'SUPERSEDED_COMMANDS',
                2
            );
            $diagnosticsState = 'absent';
            if ($arbitrationID !== null || $supersededID !== null) {
                if ($arbitrationID === null || $supersededID === null) {
                    throw new RuntimeException('Supersession diagnostics are partial.');
                }
                $arbitration = self::decodeObject(
                    $environment->getValue($arbitrationID),
                    'Command arbitration Registry'
                );
                if (
                    ($arbitration['schemaVersion'] ?? null) !== 1
                    || !is_array($arbitration['channels'] ?? null)
                ) {
                    throw new RuntimeException('Command arbitration Registry contract differs.');
                }
                $superseded = $environment->getValue($supersededID);
                $variable = $environment->getVariable($supersededID);
                if (!is_int($superseded) || $superseded < 0 || ($variable['VariableType'] ?? null) !== 1) {
                    throw new RuntimeException('Superseded command statistic contract differs.');
                }
                $diagnosticsState = 'initialized';
            }
            if ($requireSupersessionDiagnostics && $diagnosticsState !== 'initialized') {
                throw new RuntimeException('Supersession diagnostics are not initialized.');
            }

            $owners[] = [
                'ownerScriptId' => $ownerID,
                'sourceSha256' => $expectedSourceHash,
                'candidateSourceSha256' => $owner['candidateSourceSha256'],
                'configurationSha256' => $configurationHash,
                'configurationLoaderSha256' => $owner['configurationLoaderSha256'],
                'diagnosticsState' => $diagnosticsState,
                'events' => $events,
            ];
        }
        usort(
            $owners,
            static fn (array $left, array $right): int => $left['ownerScriptId'] <=> $right['ownerScriptId']
        );

        return $owners;
    }

    /**
     * @param array<string, mixed> $registry
     *
     * @return list<array<string, mixed>>
     */
    private static function collectEvents(
        MqttSupersessionMigrationEnvironment $environment,
        int $ownerID,
        array $registry,
        ?bool $activeOverride
    ): array {
        $events = [];
        $seen = [];
        foreach ($registry['managedEntities'] as $managed) {
            if (!is_array($managed)) {
                throw new RuntimeException('Managed entity contract differs.');
            }
            foreach (
                [
                    'command' => ['commandEventIDs', 'commandEventIdents', 0],
                    'state' => ['stateEventIDs', 'stateEventIdents', 1],
                ] as $kind => [$idField, $identField, $triggerType]
            ) {
                $ids = $managed[$idField] ?? null;
                $idents = $managed[$identField] ?? null;
                if (!is_array($ids) || !is_array($idents)) {
                    throw new RuntimeException('Managed event identity contract differs.');
                }
                foreach ($ids as $capability => $eventID) {
                    $ident = $idents[$capability] ?? null;
                    if (!is_int($eventID) || $eventID <= 0 || !is_string($ident) || $ident === '') {
                        throw new RuntimeException('Managed event identity is invalid.');
                    }
                    if (isset($seen[$eventID])) {
                        throw new RuntimeException('Managed event identity is duplicated.');
                    }
                    $seen[$eventID] = true;
                    if (!$environment->objectExists($eventID)) {
                        throw new RuntimeException('Managed event does not exist.');
                    }
                    $object = $environment->getObject($eventID);
                    $event = $environment->getEvent($eventID);
                    $active = self::eventField($event, ['EventActive'], null);
                    $actualTriggerType = self::eventField(
                        $event,
                        ['EventTriggerType', 'TriggerType'],
                        null
                    );
                    $triggerVariableID = self::eventField(
                        $event,
                        ['EventTriggerVariableID', 'TriggerVariableID'],
                        null
                    );
                    $actionID = self::eventField($event, ['EventActionID', 'ActionID'], '');
                    if (
                        ($object['ObjectType'] ?? null) !== 4
                        || ($object['ParentID'] ?? null) !== $ownerID
                        || ($object['ObjectIdent'] ?? null) !== $ident
                        || self::eventField($event, ['EventType'], null) !== 0
                        || $actualTriggerType !== $triggerType
                        || !is_int($triggerVariableID)
                        || $triggerVariableID <= 0
                        || !is_bool($active)
                        || strcasecmp((string)$actionID, self::AUTOMATION_ACTION_ID) !== 0
                    ) {
                        throw new RuntimeException('Managed event contract differs.');
                    }
                    if ($activeOverride !== null && $active !== $activeOverride) {
                        throw new RuntimeException('Managed event activity differs.');
                    }

                    $index = $kind === 'command'
                        ? $registry['commandIndex'][(string)$triggerVariableID] ?? null
                        : $registry['stateIndex'][(string)$triggerVariableID] ?? null;
                    if (!is_array($index)) {
                        throw new RuntimeException('Managed event Registry index differs.');
                    }
                    $events[] = [
                        'eventId' => $eventID,
                        'ident' => $ident,
                        'kind' => $kind,
                        'triggerType' => $actualTriggerType,
                        'triggerVariableId' => $triggerVariableID,
                        'actionId' => strtoupper((string)$actionID),
                        'active' => $active,
                    ];
                }
            }
        }
        usort($events, static fn (array $left, array $right): int => $left['eventId'] <=> $right['eventId']);

        return $events;
    }

    /** @param list<array<string, mixed>> $owners */
    private static function setEvents(
        array $owners,
        MqttSupersessionMigrationEnvironment $environment,
        bool $active
    ): void {
        foreach ($owners as $owner) {
            foreach ($owner['events'] as $event) {
                $environment->setEventActive($event['eventId'], $active);
            }
        }
    }

    /** @param list<array<string, mixed>> $owners */
    private static function assertEventActivity(
        array $owners,
        MqttSupersessionMigrationEnvironment $environment,
        bool $active
    ): void {
        foreach ($owners as $owner) {
            foreach ($owner['events'] as $event) {
                $current = $environment->getEvent($event['eventId']);
                if (self::eventField($current, ['EventActive'], null) !== $active) {
                    throw new RuntimeException('Managed event activity read-back differs.');
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $owners */
    private static function restoreReviewedEventActivity(
        array $owners,
        MqttSupersessionMigrationEnvironment $environment
    ): void {
        foreach ($owners as $owner) {
            foreach ($owner['events'] as $event) {
                $environment->setEventActive($event['eventId'], $event['active']);
            }
        }
    }

    /** @param array<string, mixed> $input */
    private static function setOwnerSources(
        array $input,
        MqttSupersessionMigrationEnvironment $environment,
        string $state
    ): void {
        foreach ($input['owners'] as $owner) {
            $key = $state === 'candidate' ? 'candidateSourceBase64' : 'originalSourceBase64';
            $source = base64_decode($owner[$key], true);
            if ($source === false) {
                throw new RuntimeException('Migration owner source encoding is invalid.');
            }
            $environment->setScriptContent($owner['ownerScriptId'], $source);
        }
    }

    /** @param array<string, mixed> $input */
    private static function assertOwnerSourceState(
        array $input,
        MqttSupersessionMigrationEnvironment $environment,
        string $state
    ): void {
        foreach ($input['owners'] as $owner) {
            $hashKey = $state === 'candidate' ? 'candidateSourceSha256' : 'originalSourceSha256';
            $source = $environment->getScriptContent($owner['ownerScriptId']);
            if (!hash_equals($owner[$hashKey], hash('sha256', $source))) {
                throw new RuntimeException('Migration owner source read-back differs.');
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $reviewedOwners
     */
    private static function restoreOriginalState(
        array $input,
        array $reviewedOwners,
        MqttSupersessionMigrationEnvironment $environment
    ): void {
        self::setEvents($reviewedOwners, $environment, false);
        self::setOwnerSources($input, $environment, 'original');
        self::assertOwnerSourceState($input, $environment, 'original');
        self::restoreReviewedEventActivity($reviewedOwners, $environment);
        foreach ($reviewedOwners as $owner) {
            foreach ($owner['events'] as $event) {
                $current = $environment->getEvent($event['eventId']);
                if (self::eventField($current, ['EventActive'], null) !== $event['active']) {
                    throw new RuntimeException('Rollback event activity read-back differs.');
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, mixed> $configurationLoaders
     */
    private static function validateInput(array $input, array $configurationLoaders): void
    {
        if (
            ($input['formatVersion'] ?? null) !== self::FORMAT_VERSION
            || ($input['purpose'] ?? null) !== self::PURPOSE
            || ($input['targetId'] ?? null) !== self::TARGET_ID
            || !is_string($input['repositoryBaseCommit'] ?? null)
            || preg_match('/^[0-9a-f]{40}$/D', $input['repositoryBaseCommit']) !== 1
            || !is_string($input['claimRoot'] ?? null)
            || $input['claimRoot'] === ''
            || strlen($input['claimRoot']) > 1024
            || str_contains($input['claimRoot'], "\0")
            || !is_array($input['owners'] ?? null)
            || count($input['owners']) !== 2
        ) {
            throw new InvalidArgumentException('Migration input contract is invalid.');
        }
        foreach (
            [
                'historicalRuntimeSha256',
                'historicalCoreSha256',
                'candidateRuntimeSha256',
                'candidateCoreSha256',
                'candidateFilesetSha256',
            ] as $field
        ) {
            self::assertSha256($input[$field] ?? null, 'Migration input identity');
        }

        $ownerIDs = [];
        foreach ($input['owners'] as $owner) {
            if (!is_array($owner)) {
                throw new InvalidArgumentException('Migration owner input is invalid.');
            }
            $ownerID = $owner['ownerScriptId'] ?? null;
            if (!is_int($ownerID) || $ownerID <= 0 || isset($ownerIDs[$ownerID])) {
                throw new InvalidArgumentException('Migration owner ID is invalid or duplicated.');
            }
            $ownerIDs[$ownerID] = true;
            if (!isset($configurationLoaders[$ownerID]) || !is_callable($configurationLoaders[$ownerID])) {
                throw new InvalidArgumentException('Migration configuration loader is missing.');
            }
            foreach (
                ['originalSourceSha256', 'candidateSourceSha256', 'configurationLoaderSha256'] as $field
            ) {
                self::assertSha256($owner[$field] ?? null, 'Migration owner identity');
            }
            foreach (['originalSourceBase64', 'candidateSourceBase64'] as $field) {
                if (!is_string($owner[$field] ?? null)) {
                    throw new InvalidArgumentException('Migration owner source is missing.');
                }
                $decoded = base64_decode($owner[$field], true);
                if (
                    $decoded === false
                    || !hash_equals(
                        $owner[$field === 'originalSourceBase64'
                            ? 'originalSourceSha256'
                            : 'candidateSourceSha256'],
                        hash('sha256', $decoded)
                    )
                ) {
                    throw new InvalidArgumentException('Migration owner source identity differs.');
                }
            }
            foreach (
                ['expectedEventCount', 'expectedCommandEventCount', 'expectedStateEventCount'] as $field
            ) {
                if (!is_int($owner[$field] ?? null) || $owner[$field] < 0) {
                    throw new InvalidArgumentException('Migration owner event count is invalid.');
                }
            }
            if (
                $owner['expectedEventCount']
                !== $owner['expectedCommandEventCount'] + $owner['expectedStateEventCount']
            ) {
                throw new InvalidArgumentException('Migration owner event counts do not add up.');
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $plan
     */
    private static function validatePlan(
        array $input,
        array $plan,
        string $expectedPlanSha256,
        MqttSupersessionMigrationEnvironment $environment
    ): void {
        self::validatePlanHashAndIdentity($input, $plan, $expectedPlanSha256);
        $createdAt = self::parseUtc($plan['createdAtUtc'] ?? null);
        $expiresAt = self::parseUtc($plan['expiresAtUtc'] ?? null);
        $now = $environment->now()->setTimezone(new DateTimeZone('UTC'));
        if (
            $createdAt > $now
            || $expiresAt <= $now
            || $expiresAt->getTimestamp() - $createdAt->getTimestamp() > self::MAX_PLAN_LIFETIME_SECONDS
        ) {
            throw new RuntimeException('Migration plan is expired or has an invalid time bound.');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $plan
     */
    private static function validatePlanHashAndIdentity(
        array $input,
        array $plan,
        string $expectedPlanSha256
    ): void {
        self::assertSha256($expectedPlanSha256, 'Expected plan hash');
        if (
            !hash_equals($expectedPlanSha256, self::sha256($plan))
            || ($plan['formatVersion'] ?? null) !== self::FORMAT_VERSION
            || ($plan['purpose'] ?? null) !== self::PURPOSE
            || ($plan['targetId'] ?? null) !== self::TARGET_ID
            || ($plan['allowedOperation'] ?? null) !== 'apply'
            || ($plan['repositoryBaseCommit'] ?? null) !== $input['repositoryBaseCommit']
            || ($plan['inputSha256'] ?? null) !== self::inputIdentitySha256($input)
            || !is_string($plan['nonce'] ?? null)
            || preg_match('/^[0-9a-f]{32}$/D', $plan['nonce']) !== 1
            || !is_array($plan['owners'] ?? null)
            || count($plan['owners']) !== count($input['owners'])
        ) {
            throw new RuntimeException('Migration plan identity differs.');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array{runtimeSha256: string, coreSha256: string, filesetSha256: string} $identity
     */
    private static function validateRuntimeIdentity(array $input, array $identity): void
    {
        foreach (
            [
                'runtimeSha256' => 'candidateRuntimeSha256',
                'coreSha256' => 'candidateCoreSha256',
                'filesetSha256' => 'candidateFilesetSha256',
            ] as $actual => $expected
        ) {
            self::assertSha256($identity[$actual], 'Active runtime identity');
            if (!hash_equals($input[$expected], $identity[$actual])) {
                throw new RuntimeException('Active runtime identity differs from the migration input.');
            }
        }
    }

    /** @param array<string, mixed> $input */
    private static function inputIdentitySha256(array $input): string
    {
        return self::sha256([
            'formatVersion' => $input['formatVersion'],
            'purpose' => $input['purpose'],
            'targetId' => $input['targetId'],
            'repositoryBaseCommit' => $input['repositoryBaseCommit'],
            'claimRootSha256' => hash('sha256', $input['claimRoot']),
            'historicalRuntimeSha256' => $input['historicalRuntimeSha256'],
            'historicalCoreSha256' => $input['historicalCoreSha256'],
            'candidateRuntimeSha256' => $input['candidateRuntimeSha256'],
            'candidateCoreSha256' => $input['candidateCoreSha256'],
            'candidateFilesetSha256' => $input['candidateFilesetSha256'],
            'owners' => array_map(
                static fn (array $owner): array => [
                    'ownerScriptId' => $owner['ownerScriptId'],
                    'originalSourceSha256' => $owner['originalSourceSha256'],
                    'candidateSourceSha256' => $owner['candidateSourceSha256'],
                    'configurationLoaderSha256' => $owner['configurationLoaderSha256'],
                    'expectedEventCount' => $owner['expectedEventCount'],
                    'expectedCommandEventCount' => $owner['expectedCommandEventCount'],
                    'expectedStateEventCount' => $owner['expectedStateEventCount'],
                ],
                $input['owners']
            ),
        ]);
    }

    private static function requiredChild(
        MqttSupersessionMigrationEnvironment $environment,
        int $parentID,
        string $ident,
        int $objectType
    ): int {
        $objectID = $environment->getObjectIDByIdent($ident, $parentID);
        if ($objectID === false) {
            throw new RuntimeException('Required migration object is missing.');
        }
        self::assertOwnedObject($environment, $objectID, $parentID, $ident, $objectType);

        return $objectID;
    }

    private static function optionalChild(
        MqttSupersessionMigrationEnvironment $environment,
        int $parentID,
        string $ident,
        int $objectType
    ): ?int {
        $objectID = $environment->getObjectIDByIdent($ident, $parentID);
        if ($objectID === false) {
            return null;
        }
        self::assertOwnedObject($environment, $objectID, $parentID, $ident, $objectType);

        return $objectID;
    }

    private static function assertOwnedObject(
        MqttSupersessionMigrationEnvironment $environment,
        int $objectID,
        int $parentID,
        string $ident,
        int $objectType
    ): void {
        if ($objectID <= 0 || !$environment->objectExists($objectID)) {
            throw new RuntimeException('Migration object identity is invalid.');
        }
        $object = $environment->getObject($objectID);
        if (
            ($object['ObjectType'] ?? null) !== $objectType
            || ($object['ParentID'] ?? null) !== $parentID
            || ($object['ObjectIdent'] ?? null) !== $ident
        ) {
            throw new RuntimeException('Migration object ownership differs.');
        }
    }

    /** @return array<string, mixed> */
    private static function decodeObject(mixed $value, string $label): array
    {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException($label . ' value is invalid.');
        }
        try {
            $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($label . ' JSON is invalid.', 0, $exception);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException($label . ' must be a JSON object.');
        }

        return $decoded;
    }

    /** @param list<string> $names */
    private static function eventField(array $event, array $names, mixed $default): mixed
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $event)) {
                return $event[$name];
            }
        }

        return $default;
    }

    private static function parseUtc(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Migration plan timestamp is invalid.');
        }
        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new RuntimeException('Migration plan timestamp is invalid.', 0, $exception);
        }
    }

    /** @param list<array<string, mixed>> $owners */
    private static function eventCount(array $owners, ?string $kind = null): int
    {
        $count = 0;
        foreach ($owners as $owner) {
            foreach ($owner['events'] as $event) {
                if ($kind === null || $event['kind'] === $kind) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $reviewedOwners
     * @param list<array<string, mixed>> $actualOwners
     */
    private static function assertReviewedEventState(array $reviewedOwners, array $actualOwners): void
    {
        $reviewedEvents = [];
        foreach ($reviewedOwners as $owner) {
            $reviewedEvents[(string)$owner['ownerScriptId']] = $owner['events'];
        }
        $actualEvents = [];
        foreach ($actualOwners as $owner) {
            $actualEvents[(string)$owner['ownerScriptId']] = $owner['events'];
        }
        ksort($reviewedEvents, SORT_STRING);
        ksort($actualEvents, SORT_STRING);
        if (!hash_equals(self::sha256($reviewedEvents), self::sha256($actualEvents))) {
            throw new RuntimeException('Migration event state differs from the reviewed plan.');
        }
    }

    private static function assertSha256(mixed $value, string $label): void
    {
        if (!is_string($value) || preg_match('/^[0-9a-f]{64}$/D', $value) !== 1) {
            throw new InvalidArgumentException($label . ' must be a lowercase SHA-256 value.');
        }
    }

    /** @param mixed $value */
    public static function canonicalJson(mixed $value): string
    {
        $normalized = self::canonicalValue($value);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /** @param mixed $value */
    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonicalValue'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalValue($item);
        }

        return $value;
    }

    /** @param mixed $value */
    private static function sha256(mixed $value): string
    {
        return hash('sha256', self::canonicalJson($value));
    }

    /** @param array<string, mixed> $fields */
    private static function status(array $fields): array
    {
        return [
            'formatVersion' => self::FORMAT_VERSION,
            'timestampUtc' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s.u\Z'),
            'phase' => 'mqtt_supersession_owner_migration',
            'targetId' => self::TARGET_ID,
            ...$fields,
        ];
    }
}
