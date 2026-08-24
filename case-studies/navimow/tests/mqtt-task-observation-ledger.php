<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Navimow/MqttPayloadException.php';
require_once __DIR__ . '/../distribution/libs/Navimow/MqttTaskObservationLedger.php';

function assertTaskLedger(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fixture = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../fixtures/mqtt/task-observation-ledger-sequence.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertTaskLedger(is_array($fixture), 'Task ledger fixture is invalid.');

$ledger = Navimow\MqttTaskObservationLedger::initialLedger();
foreach ($fixture['observations'] as $observation) {
    $ledger = Navimow\MqttTaskObservationLedger::reduce(
        $ledger,
        $observation['fields'],
        $fixture['deviceKey'],
        $observation['receivedAt'],
        $observation['sessionSequence']
    );
}

$encoded = Navimow\MqttTaskObservationLedger::serializeLedger($ledger);
$restored = Navimow\MqttTaskObservationLedger::restore($encoded);
$projection = Navimow\MqttTaskObservationLedger::project($restored);
$types = array_column($projection['transitions'], 'type');

assertTaskLedger(
    $projection['authority'] === 'mqtt-inference'
        && $projection['semanticUnit'] === 'correlated-zone-pass'
        && $projection['retainedPassCount'] === 3,
    'Synthetic sequence did not produce three inferred zone passes.'
);
assertTaskLedger(
    $projection['passes'][0]['completionObservedAt'] === 1800000002
        && $projection['passes'][0]['maxProgress'] === 10000,
    'Completion evidence was not retained on the completed pass.'
);
assertTaskLedger(
    $projection['passes'][1]['firstProgress'] === 0
        && $projection['passes'][1]['lastProgress'] === 800
        && $projection['passes'][1]['completionObservedAt'] === null
        && $projection['passes'][1]['firstSessionSequence'] === 4
        && $projection['passes'][1]['lastSessionSequence'] === 5,
    'Progress wrap or cross-session pass continuation is incorrect.'
);
assertTaskLedger(
    $projection['passes'][2]['boundaryKey']
        === str_repeat('b', 64)
        && $projection['passes'][2]['partitionCount'] === 2,
    'Area correlation change did not open the expected pass.'
);
foreach (
    [
        'first-observation',
        'completion-observed',
        'progress-wrap',
        'phase-change',
        'transport-session-change',
        'area-correlation-change',
        'delay-change',
    ] as $expectedType
) {
    assertTaskLedger(
        in_array($expectedType, $types, true),
        sprintf('Expected transition %s is missing.', $expectedType)
    );
}
assertTaskLedger(
    !str_contains($encoded, 'currentMowBoundary')
        && !str_contains($encoded, 'partitionIds')
        && !str_contains($encoded, 'mapWorkPosition'),
    'Task ledger retained a forbidden raw field.'
);

$nonTask = Navimow\MqttTaskObservationLedger::reduce(
    $restored,
    ['vehicleState' => 4],
    $fixture['deviceKey'],
    1800000009,
    5
);
assertTaskLedger(
    $nonTask === $restored,
    'Non-task telemetry changed the task observation ledger.'
);

$malformedRejected = false;
try {
    Navimow\MqttTaskObservationLedger::restore('{');
} catch (Navimow\MqttPayloadException) {
    $malformedRejected = true;
}
assertTaskLedger(
    $malformedRejected,
    'Malformed persisted task ledger was not rejected.'
);

$bounded = Navimow\MqttTaskObservationLedger::initialLedger();
for ($index = 0; $index < 100; $index++) {
    $bounded = Navimow\MqttTaskObservationLedger::reduce(
        $bounded,
        [
            'boundaryKey' => hash('sha256', 'area-' . $index),
            'currentMowProgress' => 100,
            'taskTelemetryReceivedAt' => 1800000100 + $index,
        ],
        $fixture['deviceKey'],
        1800000100 + $index,
        6
    );
}
$boundedProjection = Navimow\MqttTaskObservationLedger::project($bounded);
assertTaskLedger(
    $boundedProjection['retainedPassCount'] === 32
        && $boundedProjection['retainedTransitionCount'] === 64
        && strlen(
            Navimow\MqttTaskObservationLedger::serializeLedger($bounded)
        ) <= 65536,
    'Task ledger entry or byte limits are not enforced.'
);

echo "Navimow MQTT task observation ledger checks passed.\n";
