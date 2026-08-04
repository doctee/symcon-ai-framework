<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/harness/FakeClock.php';
require_once __DIR__
    . '/../distribution/NavimowAccount/module.php';

final class MqttPilotCheckpointAccount extends NavimowAccount
{
    public function __construct(
        int $instanceId,
        private NavimowTestFakeClock $clock,
        private int $kernelStartTime
    ) {
        parent::__construct($instanceId);
    }

    protected function currentTimestamp(): int
    {
        return $this->clock->now();
    }

    protected function currentKernelStartTime(): int
    {
        return $this->kernelStartTime;
    }

    public function testSetKernelStartTime(int $timestamp): void
    {
        $this->kernelStartTime = $timestamp;
    }
}

function assertPilotCheckpoint(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function decodePilotCheckpoint(string $json): array
{
    $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected pilot diagnostic object.');
    }

    return $decoded;
}

function invokePilotPrivate(
    NavimowAccount $account,
    string $method,
    mixed ...$arguments
): mixed {
    $reflection = new ReflectionMethod(NavimowAccount::class, $method);

    return $reflection->invoke($account, ...$arguments);
}

$clock = new NavimowTestFakeClock(1700000000);
$account = new MqttPilotCheckpointAccount(
    7101,
    $clock,
    1699999900
);
$account->Create();
$account->ApplyChanges();
$disabled = decodePilotCheckpoint(
    $account->GetMqttPilotDiagnostics()
);
$disabledSummaryJson = $account->GetMqttPilotSummary();
$disabledSummary = decodePilotCheckpoint($disabledSummaryJson);
assertPilotCheckpoint(
    ($disabled['formatVersion'] ?? null) === 2
        && ($disabled['featureEnabled'] ?? null) === false
        && ($disabled['active'] ?? null) === false
        && ($disabled['checkpointIntervalSeconds'] ?? null) === 18000
        && ($disabled['checkpoints'] ?? null) === []
        && ($disabled['coreTransitions'] ?? null) === []
        && ($disabled['coreStatusEventDrops'] ?? null) === 0
        && ($disabled['checkpointSequence'] ?? null) === 0
        && ($disabled['episodeSequence'] ?? null) === 0
        && ($disabled['rotationSequence'] ?? null) === 0
        && ($disabled['coreTransitionSequence'] ?? null) === 0
        && ($disabledSummary['formatVersion'] ?? null) === 1
        && ($disabledSummary['featureEnabled'] ?? null) === false
        && ($disabledSummary['active'] ?? null) === false
        && ($disabledSummary['checkpoints'] ?? null) === []
        && ($disabledSummary['latestEpisode'] ?? null) === null
        && strlen($disabledSummaryJson) <= 16384
        && $account->testTimerInterval('MqttPilotCheckpoint') === 0
        && count(
            $account->testSnapshotPersistentState()['variables']
        ) === 6,
    'Disabled pilot diagnostics changed the public contract.'
);

$account->testSetProperty('EnableMqttShadow', true);
$account->ApplyChanges();
$staged = decodePilotCheckpoint(
    $account->GetMqttPilotDiagnostics()
);
assertPilotCheckpoint(
    ($staged['featureEnabled'] ?? null) === true
        && ($staged['active'] ?? null) === false
        && $account->testTimerInterval('MqttPilotCheckpoint') === 0,
    'Inactive staging started the pilot checkpoint timer.'
);
invokePilotPrivate(
    $account,
    'startMqttPilotObservationIfNeeded'
);
$started = decodePilotCheckpoint(
    $account->GetMqttPilotDiagnostics()
);
assertPilotCheckpoint(
    ($started['active'] ?? null) === true
        && ($started['sessionSequence'] ?? null) === 1
        && ($started['startedAt'] ?? null) === 1700000000
        && ($started['nextCheckpointAt'] ?? null) === 1700018000
        && $account->testTimerInterval('MqttPilotCheckpoint')
            === 18000000,
    'Enabled pilot did not start an absolute five-hour schedule.'
);

invokePilotPrivate(
    $account,
    'replaceMqttPilotCoreStatusMessages',
    [9001, 9002]
);
invokePilotPrivate(
    $account,
    'replaceMqttPilotCoreStatusMessages',
    [9001, 9002]
);
$pilotMessages = $account->testRegisteredMessages();
assertPilotCheckpoint(
    count($pilotMessages) === 3
        && in_array([
            'senderId' => 9001,
            'messageId' => IM_CHANGESTATUS,
        ], $pilotMessages, true)
        && in_array([
            'senderId' => 9002,
            'messageId' => IM_CHANGESTATUS,
        ], $pilotMessages, true),
    'Pilot Core status registration was not idempotent.'
);
invokePilotPrivate(
    $account,
    'replaceMqttPilotCoreStatusMessages',
    []
);
assertPilotCheckpoint(
    $account->testRegisteredMessages() === [[
        'senderId' => 0,
        'messageId' => IPS_KERNELSTARTED,
    ]],
    'Pilot Core status registrations were not removed.'
);
$beforeUnrelatedMessage = $account->testSnapshotPersistentState();
$account->MessageSink(
    1,
    9001,
    IM_CHANGESTATUS,
    ['secret' => 'SYNTHETIC_PRIVATE_VALUE']
);
assertPilotCheckpoint(
    $account->testSnapshotPersistentState()
        === $beforeUnrelatedMessage,
    'Unowned status message or raw Data changed pilot state.'
);

$beforeRead = $account->testSnapshotPersistentState();
$account->GetMqttPilotDiagnostics();
$account->GetMqttPilotSummary();
assertPilotCheckpoint(
    $account->testSnapshotPersistentState() === $beforeRead,
    'Pilot diagnostic read mutated persistent state.'
);

$snapshot = $account->testSnapshotPersistentState();
$clock->advance(18120);
$restarted = new MqttPilotCheckpointAccount(
    7101,
    $clock,
    1700018000
);
$restarted->Create();
$restarted->testRestorePersistentState($snapshot);
$restarted->ApplyChanges();
assertPilotCheckpoint(
    $restarted->testTimerInterval('MqttPilotCheckpoint') === 1000,
    'Overdue restart did not schedule one immediate checkpoint.'
);
$restarted->ProcessMqttPilotCheckpoint();
$afterRestart = decodePilotCheckpoint(
    $restarted->GetMqttPilotDiagnostics()
);
$checkpoint = $afterRestart['checkpoints'][0] ?? [];
assertPilotCheckpoint(
    count($afterRestart['checkpoints'] ?? []) === 1
        && ($checkpoint['scheduledAt'] ?? null) === 1700018000
        && ($checkpoint['recordedAt'] ?? null) === 1700018120
        && ($checkpoint['delaySeconds'] ?? null) === 120
        && ($afterRestart['nextCheckpointAt'] ?? null) === 1700036000
        && $restarted->testTimerInterval('MqttPilotCheckpoint')
            === 17880000,
    'Restart reconciliation replayed or drifted the checkpoint schedule.'
);

$statistics = json_decode(
    (string) $restarted->testReadAttribute('MqttStatistics'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$statistics['lastReceivedAt'] = $clock->now() - 10;
$restarted->testSetAttribute(
    'MqttStatistics',
    json_encode($statistics, JSON_THROW_ON_ERROR)
);
$restarted->testSetVariable(
    'LastRestSuccess',
    $clock->now() - 5
);
$restarted->testSetVariable('ConnectionState', 3);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotRotation',
    $clock->now()
);
$clock->advance(30);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotCoreStatusTransition',
    'websocket',
    200,
    200,
    true,
    $clock->now()
);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotCoreStatusTransition',
    'websocket',
    200,
    200,
    true,
    $clock->now()
);
$clock->advance(30);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotEpisodeDetected',
    'lifecycle-observation',
    200,
    200,
    $clock->now()
);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotReconnectScheduled',
    $clock->now(),
    $clock->now() + 60
);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotRotation',
    $clock->now()
);
$clock->advance(60);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotReconnectStarted',
    $clock->now()
);
$lifecycle = json_decode(
    (string) $restarted->testReadAttribute('MqttLifecycleRegistry'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$lifecycle['reconnectAttempt'] = 1;
$restarted->testSetAttribute(
    'MqttLifecycleRegistry',
    json_encode($lifecycle, JSON_THROW_ON_ERROR)
);
$restarted->testSetKernelStartTime(1700018060);
$clock->advance(10);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotCoreStatusTransition',
    'mqtt',
    102,
    102,
    true,
    $clock->now()
);
$clock->advance(50);
invokePilotPrivate(
    $restarted,
    'closeMqttPilotEpisode',
    'recovered',
    $clock->now()
);
$episodeResult = decodePilotCheckpoint(
    $restarted->GetMqttPilotDiagnostics()
);
$episodeSummaryJson = $restarted->GetMqttPilotSummary();
$episodeSummary = decodePilotCheckpoint($episodeSummaryJson);
$episode = $episodeResult['episodes'][0] ?? [];
$rotation = $episodeResult['rotations'][0] ?? [];
assertPilotCheckpoint(
    array_key_exists('openEpisode', $episodeResult)
        && $episodeResult['openEpisode'] === null
        && ($episode['detectionSource'] ?? null)
            === 'lifecycle-observation'
        && ($episode['mqttStatus'] ?? null) === 200
        && ($episode['webSocketStatus'] ?? null) === 200
        && ($episode['durationSeconds'] ?? null) === 120
        && ($episode['coreFaultLeadSeconds'] ?? null) === 30
        && ($episode['reconnectScheduledAt'] ?? null)
            === 1700018180
        && ($episode['reconnectDueAt'] ?? null) === 1700018240
        && ($episode['reconnectStartedAt'] ?? null)
            === 1700018240
        && ($episode['coreReadyAt'] ?? null) === 1700018250
        && ($episode['coreReadySource'] ?? null)
            === 'status-message'
        && ($episode['recoveryConfirmationLagSeconds'] ?? null)
            === 50
        && ($episode['mqttIngressSeen'] ?? null) === true
        && ($episode['mqttIngressAgeSeconds'] ?? null) === 70
        && ($episode['restSuccessSeen'] ?? null) === true
        && ($episode['restSuccessAgeSeconds'] ?? null) === 65
        && ($episode['restConnectionState'] ?? null) === 3
        && ($episode['rotationSeparationSeconds'] ?? null) === 60
        && ($episode['diagnosticCompleteness'] ?? null) === 'complete'
        && count($episode['coreTransitions'] ?? []) === 2
        && count($episodeResult['coreTransitions'] ?? []) === 2
        && ($episode['reconnectAttemptsUsed'] ?? null) === 1
        && ($episode['outcome'] ?? null) === 'recovered'
        && ($episode['overlappedRotation'] ?? null) === true
        && ($episode['kernelEpochChanged'] ?? null) === true
        && ($rotation['overlappingEpisodeSequence'] ?? null) === 0
        && ($episodeResult['rotations'][1]
            ['overlappingEpisodeSequence'] ?? null) === 1,
    'Bounded transport episode did not retain causal evidence: '
        . json_encode(
            ['episode' => $episode, 'rotation' => $rotation],
            JSON_THROW_ON_ERROR
    )
);
assertPilotCheckpoint(
    ($episodeResult['checkpointSequence'] ?? null) === 1
        && ($episodeResult['episodeSequence'] ?? null) === 1
        && ($episodeResult['rotationSequence'] ?? null) === 2
        && ($episodeResult['coreTransitionSequence'] ?? null) === 2
        && ($episodeSummary['formatVersion'] ?? null) === 1
        && ($episodeSummary['episodeSequence'] ?? null) === 1
        && ($episodeSummary['latestEpisode']['sequence'] ?? null) === 1
        && !array_key_exists(
            'coreTransitions',
            $episodeSummary['latestEpisode'] ?? []
        )
        && !array_key_exists('episodes', $episodeSummary)
        && !array_key_exists('rotations', $episodeSummary)
        && !array_key_exists('coreTransitions', $episodeSummary)
        && strlen($episodeSummaryJson) <= 16384,
    'Bounded summary did not preserve cumulative accounting.'
);

for ($index = 0; $index < 35; $index++) {
    $clock->advance(1);
    invokePilotPrivate(
        $restarted,
        'recordMqttPilotCoreStatusTransition',
        $index % 2 === 0 ? 'mqtt' : 'websocket',
        200,
        200,
        true,
        $clock->now()
    );
}
$boundedTransitions = decodePilotCheckpoint(
    $restarted->GetMqttPilotDiagnostics()
);
assertPilotCheckpoint(
    count($boundedTransitions['coreTransitions'] ?? []) === 32,
    'Core transition history exceeded its fixed bound.'
);
$clock->advance(121);
invokePilotPrivate(
    $restarted,
    'recordMqttPilotEpisodeDetected',
    'lifecycle-observation',
    200,
    200,
    $clock->now()
);
$fallbackEpisode = decodePilotCheckpoint(
    $restarted->GetMqttPilotDiagnostics()
)['openEpisode'] ?? [];
assertPilotCheckpoint(
    ($fallbackEpisode['diagnosticCompleteness'] ?? null)
        === 'polling-fallback'
        && ($fallbackEpisode['coreFaultObservedAt'] ?? null) === 0
        && ($fallbackEpisode['coreTransitions'] ?? null) === [],
    'Missing Core callback did not retain an explicit polling fallback.'
);
for ($index = 0; $index < 10; $index++) {
    $clock->advance(1);
    invokePilotPrivate(
        $restarted,
        'recordMqttPilotCoreStatusTransition',
        $index % 2 === 0 ? 'mqtt' : 'websocket',
        200,
        200,
        true,
        $clock->now()
    );
}
$boundedEpisodeTransitions = decodePilotCheckpoint(
    $restarted->GetMqttPilotDiagnostics()
)['openEpisode']['coreTransitions'] ?? [];
assertPilotCheckpoint(
    count($boundedEpisodeTransitions) === 8,
    'Episode Core transition history exceeded its fixed bound.'
);
invokePilotPrivate(
    $restarted,
    'closeMqttPilotEpisode',
    'disabled',
    $clock->now()
);

$malformedEntries = [];
for ($index = 0; $index < 40; $index++) {
    $malformedEntries[] = [
        'sequence' => PHP_INT_MAX - 40 + $index,
        'sessionSequence' => PHP_INT_MAX,
        'recordedAt' => PHP_INT_MAX - 40 + $index,
        'delaySeconds' => 1,
        'secret' => 'SYNTHETIC_PRIVATE_VALUE',
    ];
}
$restarted->testSetAttribute(
    'MqttPilotObservationRegistry',
    json_encode([
        'formatVersion' => 1,
        'active' => true,
        'checkpoints' => $malformedEntries,
        'episodes' => [[
            'sequence' => 1,
            'detectionSource' => 'PRIVATE_TOPIC',
            'secret' => 'SYNTHETIC_PRIVATE_VALUE',
        ]],
        'rotations' => [],
        'coreTransitions' => array_fill(0, 40, [
            'sequence' => 1,
            'senderRole' => 'PRIVATE_INSTANCE',
            'classification' => 'PRIVATE_ERROR',
            'secret' => 'SYNTHETIC_PRIVATE_VALUE',
        ]),
        'privateDeviceId' => 'SYNTHETIC_PRIVATE_VALUE',
    ], JSON_THROW_ON_ERROR)
);
$sanitizedJson = $restarted->GetMqttPilotDiagnostics();
$sanitized = decodePilotCheckpoint($sanitizedJson);
$summaryJson = $restarted->GetMqttPilotSummary();
$summary = decodePilotCheckpoint($summaryJson);
assertPilotCheckpoint(
    ($sanitized['formatVersion'] ?? null) === 2
        && count($sanitized['checkpoints'] ?? []) === 32
        && count($sanitized['coreTransitions'] ?? []) === 32
        && ($sanitized['episodes'][0]['detectionSource'] ?? null)
            === 'unknown'
        && ($sanitized['episodes'][0]
            ['diagnosticCompleteness'] ?? null) === 'legacy'
        && ($sanitized['episodes'][0]['coreFaultObservedAt'] ?? null)
            === 0
        && !str_contains($sanitizedJson, 'SYNTHETIC_PRIVATE_VALUE')
        && !str_contains($sanitizedJson, 'PRIVATE_TOPIC'),
    'Pilot projection is unbounded or leaks unsupported fields.'
);
assertPilotCheckpoint(
    ($summary['formatVersion'] ?? null) === 1
        && count($summary['checkpoints'] ?? []) === 32
        && array_keys($summary['checkpoints'][0] ?? []) === [
            'sequence',
            'sessionSequence',
            'recordedAt',
            'delaySeconds',
        ]
        && ($summary['checkpoints'][0]['delaySeconds'] ?? null) === 1
        && strlen($summaryJson) <= 16384
        && !str_contains($summaryJson, 'SYNTHETIC_PRIVATE_VALUE')
        && !str_contains($summaryJson, 'PRIVATE_TOPIC'),
    'Summary coverage, size or privacy contract failed.'
);

try {
    invokePilotPrivate(
        $restarted,
        'encodeMqttPilotSummary',
        ['padding' => str_repeat('x', 16384)]
    );
    throw new RuntimeException('Oversized pilot summary was accepted.');
} catch (RuntimeException $runtimeException) {
    assertPilotCheckpoint(
        $runtimeException->getMessage()
            === 'MQTT pilot summary exceeds the fixed byte limit.',
        'Pilot summary did not fail closed at its byte limit.'
    );
}

$reconciledFixture = decodePilotCheckpoint((string) file_get_contents(
    __DIR__ . '/../fixtures/mqtt/episode-accounting-reconciled.json'
));
assertPilotCheckpoint(
    ($reconciledFixture['unexpectedDisconnectDelta'] ?? null) === 12
        && ($reconciledFixture['episodeSequenceDelta'] ?? null) === 8
        && ($reconciledFixture['duplicateObservationDelta'] ?? null) === 4
        && ($reconciledFixture['evidenceGap'] ?? null) === false
        && ($reconciledFixture['classification'] ?? null) === 'FAIL',
    'Reconciled pilot evidence fixture changed unexpectedly.'
);

$restarted->testSetProperty('EnableMqttShadow', false);
$restarted->ApplyChanges();
$closed = decodePilotCheckpoint(
    $restarted->GetMqttPilotDiagnostics()
);
assertPilotCheckpoint(
    ($closed['active'] ?? null) === false
        && ($closed['stoppedAt'] ?? null) === $clock->now()
        && ($closed['nextCheckpointAt'] ?? null) === 0
        && $restarted->testTimerInterval('MqttPilotCheckpoint') === 0
        && count(
            $restarted->testSnapshotPersistentState()['variables']
        ) === 6,
    'Disabling MQTT did not close the internal pilot schedule.'
);
assertPilotCheckpoint(
    !str_contains(
        (string) $restarted->testReadAttribute(
            'MqttPilotObservationRegistry'
        ),
        'SYNTHETIC_PRIVATE_VALUE'
    ),
    'Version-1 migration retained unsupported nested fields.'
);

echo "Navimow MQTT pilot checkpoint tests passed.\n";
