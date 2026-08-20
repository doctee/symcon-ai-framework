<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Navimow/PayloadMapper.php';
require_once __DIR__ . '/../distribution/libs/Navimow/MqttPayloadException.php';
require_once __DIR__ . '/../distribution/libs/Navimow/MqttPayloadParser.php';
require_once __DIR__
    . '/../distribution/libs/Navimow/MqttPartialStateAccumulator.php';

use Navimow\MqttPartialStateAccumulator;
use Navimow\MqttPayloadException;
use Navimow\MqttPayloadParser;
use Navimow\PayloadMapper;

const SHADOW_DEVICE_ID = 'DEVICE_001';
const SHADOW_LOCATION_TOPIC =
    '/downlink/vehicle/DEVICE_001/realtimeDate/location';
const SHADOW_STATE_TOPIC =
    '/downlink/vehicle/DEVICE_001/realtimeDate/state';
const SHADOW_RECEIVED_AT = 1700000001;

function loadShadowPayload(string $name): string
{
    $contents = file_get_contents(
        __DIR__ . '/../fixtures/mqtt/' . $name
    );
    if ($contents === false) {
        throw new RuntimeException('Unable to read MQTT payload fixture.');
    }

    $fixture = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($fixture) || !array_key_exists('payload', $fixture)) {
        throw new RuntimeException('MQTT payload fixture is malformed.');
    }

    return json_encode($fixture['payload'], JSON_THROW_ON_ERROR);
}

function assertShadow(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertShadowThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (MqttPayloadException) {
        return;
    }

    throw new RuntimeException($message);
}

$state = MqttPartialStateAccumulator::initialState();
foreach (
    [
        'state-running.json' => PayloadMapper::VEHICLE_STATE_RUNNING,
        'state-docking.json' => PayloadMapper::VEHICLE_STATE_DOCKING,
        'state-docked.json' => PayloadMapper::VEHICLE_STATE_DOCKED,
    ] as $fixture => $expectedState
) {
    $parsed = MqttPayloadParser::parse(
        SHADOW_STATE_TOPIC,
        loadShadowPayload($fixture),
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    );
    $patch = $parsed['patches'][0];
    assertShadow(
        $parsed['channel'] === 'state'
            && $parsed['receivedAt'] === SHADOW_RECEIVED_AT
            && $patch['fields']['vehicleState'] === $expectedState
            && $patch['classification'] === 'known-state'
            && $patch['reconciliationHint'] === true,
        sprintf('Direct state normalization failed for %s.', $fixture)
    );
    $reduced = MqttPartialStateAccumulator::reduce(
        $state,
        $patch,
        SHADOW_RECEIVED_AT
    );
    assertShadow(
        $reduced['accepted'] === true
            && $reduced['reason'] === 'applied',
        sprintf('Direct state reduction failed for %s.', $fixture)
    );
    $state = $reduced['state'];
}
assertShadow(
    $state['fields']['vehicleState'] === PayloadMapper::VEHICLE_STATE_DOCKED
        && $state['fields']['batteryLevel'] === 9,
    'Direct-state transition did not reach the docked candidate.'
);

$unknownState = MqttPayloadParser::parse(
    SHADOW_STATE_TOPIC,
    '{"device_id":"DEVICE_001","state":"isVendorFuture",'
        . '"battery":51,"timestamp":1700000200000}',
    SHADOW_DEVICE_ID,
    SHADOW_RECEIVED_AT
);
$unknownPatch = $unknownState['patches'][0];
$unknownResult = MqttPartialStateAccumulator::reduce(
    MqttPartialStateAccumulator::initialState(),
    $unknownPatch,
    SHADOW_RECEIVED_AT
);
assertShadow(
    $unknownPatch['classification'] === 'unknown-state'
        && !array_key_exists('vehicleState', $unknownPatch['fields'])
        && $unknownResult['reason'] === 'unknown-state'
        && $unknownResult['reconciliationHint'] === true
        && $unknownResult['diagnosticDeltas']['unknownState'] === 1
        && !str_contains(
            json_encode($unknownResult, JSON_THROW_ON_ERROR),
            'isVendorFuture'
        ),
    'Unknown direct state escaped its bounded reason-code contract.'
);

$pose = MqttPayloadParser::parse(
    SHADOW_LOCATION_TOPIC,
    '[{"postureTheta":"0.387","postureX":9876.54321,'
        . '"postureY":-8765.4321,"time":1700000000000,'
        . '"type":1,"vehicleState":1}]',
    SHADOW_DEVICE_ID,
    SHADOW_RECEIVED_AT
);
$posePatch = $pose['patches'][0];
$poseJson = json_encode($pose, JSON_THROW_ON_ERROR);
assertShadow(
    $posePatch['fields'] === [
        'locationType' => 1,
        'locationVehicleStateCode' => 1,
    ]
        && $posePatch['geometryPresent'] === true
        && $posePatch['pose'] === [
            'localX' => 9876.54321,
            'localY' => -8765.4321,
            'orientation' => 0.387,
            'sourceTimestamp' => 1700000000000,
            'vehicleStateCode' => 1,
        ]
        && !str_contains($poseJson, 'posture'),
    'Geometry was not reduced to the bounded local-pose contract.'
);

$locationState = MqttPartialStateAccumulator::initialState();
$firstLocation = MqttPartialStateAccumulator::reduce(
    $locationState,
    $posePatch,
    SHADOW_RECEIVED_AT
);
$partial = MqttPayloadParser::parse(
    SHADOW_LOCATION_TOPIC,
    loadShadowPayload('location-type-3-partial.json'),
    SHADOW_DEVICE_ID,
    SHADOW_RECEIVED_AT + 1
);
$secondLocation = MqttPartialStateAccumulator::reduce(
    $firstLocation['state'],
    $partial['patches'][0],
    SHADOW_RECEIVED_AT + 1
);
assertShadow(
    $secondLocation['state']['fields']['locationType'] === 3
        && $secondLocation['state']['fields']['locationVehicleStateCode'] === 1
        && $partial['patches'][0]['pose'] === null,
    'Partial location update cleared an absent field.'
);

$outOfOrder = MqttPayloadParser::parse(
    SHADOW_LOCATION_TOPIC,
    '[{"time":1699999999999,"type":9}]',
    SHADOW_DEVICE_ID,
    SHADOW_RECEIVED_AT + 2
);
$outOfOrderResult = MqttPartialStateAccumulator::reduce(
    $secondLocation['state'],
    $outOfOrder['patches'][0],
    SHADOW_RECEIVED_AT + 2
);
assertShadow(
    $outOfOrderResult['accepted'] === false
        && $outOfOrderResult['reason'] === 'out-of-order'
        && $outOfOrderResult['state'] === $secondLocation['state'],
    'Out-of-order location patch changed shadow state.'
);

$timestampLess = MqttPayloadParser::parse(
    SHADOW_LOCATION_TOPIC,
    loadShadowPayload('location-type-4-no-time.json'),
    SHADOW_DEVICE_ID,
    SHADOW_RECEIVED_AT + 3
);
$timestampLessResult = MqttPartialStateAccumulator::reduce(
    $secondLocation['state'],
    $timestampLess['patches'][0],
    SHADOW_RECEIVED_AT + 3
);
assertShadow(
    $timestampLessResult['accepted'] === true
        && $timestampLessResult['reason'] === 'applied'
        && $timestampLess['patches'][0]['classification']
            === 'receipt-timestamped-task'
        && $timestampLessResult['state']['fields']['taskDelay'] === true
        && $timestampLessResult['state']['fields']
            ['taskTelemetryReceivedAt'] === SHADOW_RECEIVED_AT + 3
        && $timestampLessResult['state']['lastSourceTimestamp']
            === $secondLocation['state']['lastSourceTimestamp'],
    'Timestamp-less task delay was not safely receipt-timestamped.'
);

$taskProgress = MqttPayloadParser::parse(
    SHADOW_LOCATION_TOPIC,
    loadShadowPayload('location-task-progress.json'),
    SHADOW_DEVICE_ID,
    SHADOW_RECEIVED_AT + 4
);
$taskPatch = $taskProgress['patches'][0];
assertShadow(
    $taskPatch['fields'] === [
        'action' => 8,
        'currentMowProgress' => 4250,
        'mowStartType' => 0,
        'mowingPercentage' => 42.0,
        'mowingWeekArea' => 123.45,
        'subAction' => 6,
        'subtotalArea' => 23.45,
        'locationType' => 1,
        'taskTelemetryReceivedAt' => SHADOW_RECEIVED_AT + 4,
    ]
        && $taskPatch['areaIdentity'] === ['boundaryId' => 7]
        && $taskPatch['unknownFieldCount'] === 0
        && !str_contains(
            json_encode($taskPatch, JSON_THROW_ON_ERROR),
            str_repeat('a', 128)
        ),
    'Task progress was not reduced to the bounded diagnostic contract.'
);

$partition = MqttPayloadParser::parse(
    SHADOW_LOCATION_TOPIC,
    loadShadowPayload('location-partitions.json'),
    SHADOW_DEVICE_ID,
    SHADOW_RECEIVED_AT + 5
);
assertShadow(
    $partition['patches'][0]['areaIdentity'] === [
        'partitionIds' => [7, 9],
        ]
        && $partition['patches'][0]['fields'] === [
            'locationType' => 3,
            'taskTelemetryReceivedAt' => SHADOW_RECEIVED_AT + 5,
        ],
    'Partition evidence was not retained as bounded transient identity.'
);

$serialized = MqttPartialStateAccumulator::serializeState(
    $secondLocation['state']
);
assertShadow(
    strlen($serialized) < 4096,
    'Serialized shadow state is not bounded.'
);
$restored = MqttPartialStateAccumulator::restoreAfterRestart($serialized);
assertShadow(
    $restored === MqttPartialStateAccumulator::initialState(),
    'Semantic MQTT state survived restart restoration.'
);

assertShadowThrows(
    static fn (): array => MqttPayloadParser::parse(
        '/downlink/vehicle/DEVICE_OTHER/realtimeDate/state',
        '{}',
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    ),
    'Cross-device topic was accepted.'
);
assertShadowThrows(
    static fn (): array => MqttPayloadParser::parse(
        '/downlink/vehicle/DEVICE_001/realtimeDate/event',
        '{}',
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    ),
    'Unsupported event payload was accepted.'
);
assertShadowThrows(
    static fn (): array => MqttPayloadParser::parse(
        SHADOW_LOCATION_TOPIC,
        str_repeat(' ', 32769),
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    ),
    'Oversized semantic payload was accepted.'
);
assertShadowThrows(
    static fn (): array => MqttPayloadParser::parse(
        SHADOW_LOCATION_TOPIC,
        '[{"postureX":"not-numeric","time":1700000000000}]',
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    ),
    'Invalid geometry was accepted.'
);
assertShadowThrows(
    static fn (): array => MqttPayloadParser::parse(
        SHADOW_LOCATION_TOPIC,
        '[{"taskDelay":"true","type":4}]',
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    ),
    'Invalid task-delay type was accepted.'
);
assertShadowThrows(
    static fn (): array => MqttPayloadParser::parse(
        SHADOW_LOCATION_TOPIC,
        '[{"partitionIds":[],"time":1700000003000,"type":3}]',
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    ),
    'Empty partition list was accepted.'
);
assertShadowThrows(
    static fn (): array => MqttPayloadParser::parse(
        SHADOW_LOCATION_TOPIC,
        '[{"postureTheta":3.2,"postureX":0,"postureY":0,'
            . '"time":1700000000000,"vehicleState":4}]',
        SHADOW_DEVICE_ID,
        SHADOW_RECEIVED_AT
    ),
    'Out-of-range orientation was accepted.'
);

echo "Navimow MQTT shadow payload checks passed.\n";
