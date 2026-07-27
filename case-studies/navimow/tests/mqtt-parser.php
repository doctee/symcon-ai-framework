<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/MqttPayloadException.php';
require_once __DIR__ . '/../candidate/MqttPayloadParser.php';
require_once __DIR__ . '/../candidate/MqttPartialStateAccumulator.php';

use Navimow\MqttPartialStateAccumulator;
use Navimow\MqttPayloadException;
use Navimow\MqttPayloadParser;

const MQTT_DEVICE_ID = 'DEVICE_001';
const MQTT_LOCATION_TOPIC =
    '/downlink/vehicle/DEVICE_001/realtimeDate/location';

function loadMqttParserFixture(string $name): string
{
    $contents = file_get_contents(
        __DIR__ . '/../fixtures/mqtt/' . $name
    );
    if ($contents === false) {
        throw new RuntimeException('Unable to read MQTT parser fixture.');
    }

    $fixture = json_decode(
        $contents,
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($fixture) || !array_key_exists('payload', $fixture)) {
        throw new RuntimeException('MQTT parser fixture is malformed.');
    }

    return json_encode($fixture['payload'], JSON_THROW_ON_ERROR);
}

function assertMqttParser(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertMqttParserThrows(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (MqttPayloadException) {
        return;
    }

    throw new RuntimeException($message);
}

$poseResult = MqttPayloadParser::parse(
    MQTT_LOCATION_TOPIC,
    loadMqttParserFixture('location-pose-partial.json'),
    MQTT_DEVICE_ID
);
assertMqttParser(
    $poseResult['channel'] === 'location',
    'Location channel was not parsed.'
);
assertMqttParser(
    count($poseResult['patches']) === 1,
    'Pose fixture should yield one patch.'
);
$posePatch = $poseResult['patches'][0];
assertMqttParser(
    $posePatch['fields']['postureTheta'] === 0.387,
    'Numeric postureTheta string was not normalized.'
);
assertMqttParser(
    $posePatch['fields']['postureX'] === 1.0
        && $posePatch['fields']['postureY'] === 2.0,
    'Synthetic coordinates were not parsed.'
);
assertMqttParser(
    $posePatch['fields']['vehicleState'] === 1,
    'Numeric vehicleState was changed or mapped.'
);
assertMqttParser(
    $posePatch['sourceTimestamp'] === 1700000000000,
    'Pose timestamp was not retained.'
);
assertMqttParser(
    $posePatch['unknownFields'] === [],
    'Known pose fields were classified as unknown.'
);

$partialResult = MqttPayloadParser::parse(
    MQTT_LOCATION_TOPIC,
    loadMqttParserFixture('location-type-3-partial.json'),
    MQTT_DEVICE_ID
);
$partialPatch = $partialResult['patches'][0];
assertMqttParser(
    array_keys($partialPatch['fields']) === ['time', 'type'],
    'Partial patch invented absent fields.'
);
assertMqttParser(
    !array_key_exists('vehicleState', $partialPatch['fields']),
    'Partial patch invented vehicleState.'
);

$accumulator = new MqttPartialStateAccumulator();
$firstApply = $accumulator->apply($posePatch);
assertMqttParser(
    $firstApply['accepted'] === true,
    'Initial pose patch was not accepted.'
);
$secondApply = $accumulator->apply($partialPatch);
assertMqttParser(
    $secondApply['accepted'] === true,
    'Newer partial patch was not accepted.'
);
assertMqttParser(
    $secondApply['state']['fields']['vehicleState'] === 1
        && $secondApply['state']['fields']['postureX'] === 1.0,
    'Absent fields cleared accumulated state.'
);
assertMqttParser(
    $secondApply['state']['fields']['type'] === 3,
    'Present type did not update accumulated state.'
);

$nullPayload = json_encode(
    [[
        'time' => 1700000001000,
        'vehicleState' => null,
        'postureX' => null,
        'vendorField' => ['private' => 'not-retained'],
    ]],
    JSON_THROW_ON_ERROR
);
$nullResult = MqttPayloadParser::parse(
    MQTT_LOCATION_TOPIC,
    $nullPayload,
    MQTT_DEVICE_ID
);
$nullPatch = $nullResult['patches'][0];
assertMqttParser(
    $nullPatch['nullFields'] === ['postureX', 'vehicleState'],
    'Explicit null fields were not classified.'
);
assertMqttParser(
    $nullPatch['unknownFields'] === ['vendorField'],
    'Unknown field name was not classified.'
);
assertMqttParser(
    !array_key_exists('vendorField', $nullPatch['fields']),
    'Unknown field value leaked into mapped fields.'
);
$nullApply = $accumulator->apply($nullPatch);
assertMqttParser(
    $nullApply['state']['fields']['vehicleState'] === 1
        && $nullApply['state']['fields']['postureX'] === 1.0,
    'Explicit null cleared accumulated state.'
);
assertMqttParser(
    $nullApply['ignoredNullFields'] === ['postureX', 'vehicleState'],
    'Ignored null diagnostics changed.'
);

$outOfOrderPayload = json_encode(
    [[
        'time' => 1699999999999,
        'type' => 99,
    ]],
    JSON_THROW_ON_ERROR
);
$outOfOrder = MqttPayloadParser::parse(
    MQTT_LOCATION_TOPIC,
    $outOfOrderPayload,
    MQTT_DEVICE_ID
);
$outOfOrderApply = $accumulator->apply($outOfOrder['patches'][0]);
assertMqttParser(
    $outOfOrderApply['accepted'] === false
        && $outOfOrderApply['reason'] === 'out-of-order',
    'Older timestamp was not rejected.'
);
assertMqttParser(
    $outOfOrderApply['state']['fields']['type'] === 3,
    'Rejected patch changed accumulated state.'
);

$duplicateApply = $accumulator->apply($nullPatch);
assertMqttParser(
    $duplicateApply['accepted'] === true
        && $duplicateApply['state'] === $nullApply['state'],
    'Duplicate patch was not idempotent.'
);

assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        '/downlink/vehicle/DEVICE_OTHER/realtimeDate/location',
        '[]',
        MQTT_DEVICE_ID
    ),
    'Message for another mower was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        '/downlink/vehicle/DEVICE_001/#',
        '[]',
        MQTT_DEVICE_ID
    ),
    'Wildcard topic was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        '/downlink/vehicle/DEVICE_001/realtimeDate/state',
        '{}',
        MQTT_DEVICE_ID
    ),
    'Unverified state payload contract was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        MQTT_LOCATION_TOPIC,
        '{}',
        MQTT_DEVICE_ID
    ),
    'Object location root was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        MQTT_LOCATION_TOPIC,
        '[]',
        MQTT_DEVICE_ID
    ),
    'Empty location array was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        MQTT_LOCATION_TOPIC,
        '[{"vehicleState":"1"}]',
        MQTT_DEVICE_ID
    ),
    'String vehicleState was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        MQTT_LOCATION_TOPIC,
        '[{"postureX":"not-a-number"}]',
        MQTT_DEVICE_ID
    ),
    'Invalid numeric field was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        MQTT_LOCATION_TOPIC,
        '[{"type":3}]',
        MQTT_DEVICE_ID
    ),
    'Timestamp-less location entry was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        MQTT_LOCATION_TOPIC,
        "\xC3\x28",
        MQTT_DEVICE_ID
    ),
    'Invalid UTF-8 JSON was accepted.'
);
assertMqttParserThrows(
    static fn (): array => MqttPayloadParser::parse(
        MQTT_LOCATION_TOPIC,
        str_repeat(' ', 1048577),
        MQTT_DEVICE_ID
    ),
    'Oversized payload was accepted.'
);

echo "Navimow MQTT partial payload parser checks passed.\n";
