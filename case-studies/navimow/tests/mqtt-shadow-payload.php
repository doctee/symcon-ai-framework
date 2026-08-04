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
        && !str_contains($poseJson, 'posture')
        && !str_contains($poseJson, '9876.54321')
        && !str_contains($poseJson, '-8765.4321'),
    'Geometry escaped the parser reduction boundary.'
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
        && $secondLocation['state']['fields']['locationVehicleStateCode'] === 1,
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
    $timestampLessResult['accepted'] === false
        && $timestampLessResult['reason'] === 'missing-timestamp'
        && $timestampLessResult['state'] === $secondLocation['state'],
    'Timestamp-less location patch changed shadow state.'
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

echo "Navimow MQTT shadow payload checks passed.\n";
