<?php

declare(strict_types=1);

require_once __DIR__
    . '/../tools/symcon-mqtt-spike-library/NavimowMqttReceiveProbe/'
    . 'MqttReceiveProbeReducer.php';

use Navimow\Spike\MqttReceiveProbeReducer;

const PROBE_DEVICE_ID = 'DEVICE_001';
const PROBE_DATA_ID = '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}';
const PROBE_STATE_TOPIC =
    '/downlink/vehicle/DEVICE_001/realtimeDate/state';
const PROBE_LOCATION_TOPIC =
    '/downlink/vehicle/DEVICE_001/realtimeDate/location';

function assertProbe(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function probeEnvelope(string $topic, mixed $payload, array $extra = []): string
{
    return json_encode(
        array_merge(
            [
                'DataID' => PROBE_DATA_ID,
                'PacketType' => 3,
                'Topic' => $topic,
                'Payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'QoS' => 0,
                'Retain' => false,
            ],
            $extra
        ),
        JSON_THROW_ON_ERROR
    );
}

$state = MqttReceiveProbeReducer::initialState(1000);
$state = MqttReceiveProbeReducer::consume(
    $state,
    probeEnvelope(
        PROBE_STATE_TOPIC,
        [
            'battery' => 75,
            'device_id' => PROBE_DEVICE_ID,
            'state' => 'isRunning',
            'timestamp' => 1700000000000,
        ]
    ),
    PROBE_DEVICE_ID,
    1001
);
assertProbe(
    $state['acceptedMessageCount'] === 1
        && $state['channelCounts']['state'] === 1,
    'State envelope was not accepted.'
);
assertProbe(
    $state['envelopeShapes'][0]['fields'] === [
        'DataID' => 'string',
        'PacketType' => 'integer',
        'Payload' => 'string',
        'QoS' => 'integer',
        'Retain' => 'boolean',
        'Topic' => 'string',
    ],
    'Envelope key/type shape changed.'
);
assertProbe(
    $state['payloadShapes'][0]['shape']['fields'] === [
        'battery' => 'integer',
        'device_id' => 'string',
        'state' => 'string',
        'timestamp' => 'integer',
    ],
    'State payload shape changed.'
);

$state = MqttReceiveProbeReducer::consume(
    $state,
    probeEnvelope(
        PROBE_LOCATION_TOPIC,
        [[
            'postureTheta' => 0.5,
            'postureX' => 1.0,
            'postureY' => 2.0,
            'time' => 1700000001000,
            'type' => 1,
            'vehicleState' => 4,
        ]]
    ),
    PROBE_DEVICE_ID,
    1002
);
assertProbe(
    $state['acceptedMessageCount'] === 2
        && $state['channelCounts']['location'] === 1,
    'Location envelope was not accepted.'
);
assertProbe(
    $state['payloadShapes'][1]['shape']['type'] === 'array'
        && $state['payloadShapes'][1]['shape']['objectFields']['vehicleState']
            === ['integer'],
    'Location array shape changed.'
);

$encodedReport = json_encode(
    MqttReceiveProbeReducer::report($state),
    JSON_THROW_ON_ERROR
);
foreach (
    [
        PROBE_DEVICE_ID,
        PROBE_STATE_TOPIC,
        'isRunning',
        '1700000000000',
        'postureX":1',
    ] as $privateValue
) {
    assertProbe(
        !str_contains($encodedReport, $privateValue),
        'Sanitized report retained a private payload or topic value.'
    );
}

$invalidJson = MqttReceiveProbeReducer::consume(
    MqttReceiveProbeReducer::initialState(2000),
    '{',
    PROBE_DEVICE_ID,
    2001
);
assertProbe(
    $invalidJson['rejectedMessageCount'] === 1
        && $invalidJson['lastResult'] === 'invalid-envelope-json',
    'Malformed envelope JSON was not rejected.'
);

$unknownTopic = MqttReceiveProbeReducer::consume(
    MqttReceiveProbeReducer::initialState(3000),
    probeEnvelope(
        '/downlink/vehicle/DEVICE_001/realtimeDate/unknown',
        ['private' => true]
    ),
    PROBE_DEVICE_ID,
    3001
);
assertProbe(
    $unknownTopic['unknownTopicCount'] === 1
        && $unknownTopic['acceptedMessageCount'] === 0,
    'Unknown topic was not rejected.'
);

$mismatchedDevice = MqttReceiveProbeReducer::consume(
    MqttReceiveProbeReducer::initialState(4000),
    probeEnvelope(
        PROBE_STATE_TOPIC,
        [
            'battery' => 75,
            'device_id' => 'OTHER_DEVICE',
            'state' => 'isRunning',
            'timestamp' => 1700000000000,
        ]
    ),
    PROBE_DEVICE_ID,
    4001
);
assertProbe(
    $mismatchedDevice['lastResult'] === 'state-device-mismatch',
    'Mismatched state device identity was not rejected.'
);

$wrongDataId = json_decode(
    probeEnvelope(PROBE_LOCATION_TOPIC, [['type' => 1]]),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$wrongDataId['DataID'] = '{00000000-0000-0000-0000-000000000000}';
$wrongDataIdState = MqttReceiveProbeReducer::consume(
    MqttReceiveProbeReducer::initialState(5000),
    json_encode($wrongDataId, JSON_THROW_ON_ERROR),
    PROBE_DEVICE_ID,
    5001
);
assertProbe(
    $wrongDataIdState['lastResult'] === 'unexpected-data-id',
    'Unexpected DataID was not rejected.'
);

$oversizedEnvelope = MqttReceiveProbeReducer::consume(
    MqttReceiveProbeReducer::initialState(6000),
    str_repeat('x', 65537),
    PROBE_DEVICE_ID,
    6001
);
assertProbe(
    $oversizedEnvelope['oversizedMessageCount'] === 1
        && $oversizedEnvelope['rejectedMessageCount'] === 1,
    'Oversized envelope was not rejected.'
);

$bounded = MqttReceiveProbeReducer::initialState(7000);
for ($index = 0; $index < 32; $index++) {
    $bounded = MqttReceiveProbeReducer::consume(
        $bounded,
        probeEnvelope(PROBE_LOCATION_TOPIC, [['type' => $index]]),
        PROBE_DEVICE_ID,
        7001 + $index
    );
}
assertProbe(
    $bounded['acceptedMessageCount'] === 32
        && $bounded['limitReached'] === true
        && $bounded['accepting'] === false,
    'Accepted-message evidence bound was not enforced.'
);
$afterLimit = MqttReceiveProbeReducer::consume(
    $bounded,
    probeEnvelope(PROBE_LOCATION_TOPIC, [['type' => 99]]),
    PROBE_DEVICE_ID,
    8000
);
assertProbe(
    $afterLimit === $bounded,
    'Closed evidence changed after the message bound.'
);

$closed = MqttReceiveProbeReducer::close(
    MqttReceiveProbeReducer::initialState(9000),
    9001
);
assertProbe(
    $closed['accepting'] === false
        && $closed['closedAt'] === 9001
        && $closed['lastResult'] === 'closed-without-message',
    'Manual evidence closure changed.'
);

$moduleRoot = __DIR__
    . '/../tools/symcon-mqtt-spike-library/NavimowMqttReceiveProbe';
$moduleMetadata = json_decode(
    (string) file_get_contents($moduleRoot . '/module.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertProbe(
    $moduleMetadata['id'] === '{35003FD6-161B-4211-8B43-718876ABA4F6}',
    'Probe module GUID changed.'
);
assertProbe(
    $moduleMetadata['parentRequirements'] === [
        '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
    ]
        && $moduleMetadata['implemented'] === [PROBE_DATA_ID],
    'Probe MQTT data-flow metadata changed.'
);

$moduleSource = (string) file_get_contents($moduleRoot . '/module.php');
foreach (
    [
        'SendDataToParent',
        'SendDataToChildren',
        'MQTT_Publish',
        'sendCommands',
        'RegisterVariable',
        'RequestAction',
    ] as $prohibitedSource
) {
    assertProbe(
        !str_contains($moduleSource, $prohibitedSource),
        'Probe module contains prohibited source: ' . $prohibitedSource
    );
}

fwrite(STDOUT, "Navimow Symcon MQTT receive probe checks passed.\n");
