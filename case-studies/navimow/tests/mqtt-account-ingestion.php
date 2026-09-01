<?php

declare(strict_types=1);

$mqttTestInstances = [];
$mqttTestProperties = [];
$mqttTestConfigurations = [];

function IPS_InstanceExists(int $instanceId): bool
{
    global $mqttTestInstances;

    return isset($mqttTestInstances[$instanceId]);
}

function IPS_GetInstance(int $instanceId): array
{
    global $mqttTestInstances;

    return $mqttTestInstances[$instanceId] ?? [];
}

function IPS_GetProperty(int $instanceId, string $name): mixed
{
    global $mqttTestProperties;

    return $mqttTestProperties[$instanceId][$name] ?? null;
}

function IPS_GetConfiguration(int $instanceId): string
{
    global $mqttTestConfigurations;

    return json_encode(
        $mqttTestConfigurations[$instanceId] ?? [],
        JSON_THROW_ON_ERROR
    );
}

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__
    . '/../distribution/NavimowAccount/module.php';

const MQTT_TEST_ACCOUNT_ID = 1001;
const MQTT_TEST_RECEIVER_ID = 2001;
const MQTT_TEST_CLIENT_ID = 2002;
const MQTT_TEST_WEBSOCKET_ID = 2003;
const MQTT_TEST_ACCOUNT_MODULE =
    '{3C2693FC-1068-4A63-856B-8AC0376556CC}';
const MQTT_TEST_RECEIVER_MODULE =
    '{1B9960A2-A30C-D846-DF55-800F583AA812}';
const MQTT_TEST_CLIENT_MODULE =
    '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}';
const MQTT_TEST_WEBSOCKET_MODULE =
    '{D68FD31F-0E90-7019-F16C-1949BD3079EF}';

function assertMqttAccount(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function decodeMqttAccount(string $json): array
{
    $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected a JSON object or array.');
    }

    return $decoded;
}

function mqttAccountEnvelope(): string
{
    $fixture = decodeMqttAccount((string) file_get_contents(
        __DIR__ . '/../fixtures/mqtt/symcon-envelope-state.json'
    ));

    return json_encode($fixture['envelope'], JSON_THROW_ON_ERROR);
}

function mqttAccountLocationEnvelope(): string
{
    return json_encode([
        'DataID' => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}',
        'PacketType' => 3,
        'Payload' => json_encode([[
            'postureTheta' => '0.5',
            'postureX' => '12.5',
            'postureY' => '-8.25',
            'time' => 1700000002000,
            'type' => 1,
            'vehicleState' => 4,
        ]], JSON_THROW_ON_ERROR),
        'QualityOfService' => 0,
        'Retain' => false,
        'Topic' => '/downlink/vehicle/DEVICE_001/realtimeDate/location',
    ], JSON_THROW_ON_ERROR);
}

function mqttAccountTaskEnvelope(array $payload): string
{
    return json_encode([
        'DataID' => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}',
        'PacketType' => 3,
        'Payload' => json_encode([$payload], JSON_THROW_ON_ERROR),
        'QualityOfService' => 0,
        'Retain' => false,
        'Topic' => '/downlink/vehicle/DEVICE_001/realtimeDate/location',
    ], JSON_THROW_ON_ERROR);
}

$account = new NavimowAccount(MQTT_TEST_ACCOUNT_ID);
$account->Create();
$account->ApplyChanges();

$positionDisabled = decodeMqttAccount(
    $account->GetMqttPositionDiagnostics()
);
assertMqttAccount(
    $positionDisabled['status'] === 'disabled'
        && $positionDisabled['featureEnabled'] === false
        && $positionDisabled['transportEnabled'] === false
        && $positionDisabled['observation'] === null,
    'MQTT position diagnostics are not disabled by default.'
);
$account->testSetProperty('EnableMqttPositionDiagnostics', true);
$positionInactive = decodeMqttAccount(
    $account->GetMqttPositionDiagnostics()
);
assertMqttAccount(
    $positionInactive['status'] === 'inactive'
        && $positionInactive['featureEnabled'] === true
        && $positionInactive['transportEnabled'] === false
        && $positionInactive['observation'] === null,
    'Position diagnostics did not remain inactive without MQTT transport.'
);

$disabled = decodeMqttAccount(
    $account->ValidateMqttShadowConfiguration()
);
assertMqttAccount(
    $disabled === [
        'enabled' => false,
        'valid' => true,
        'status' => 'disabled',
        'receiverInstanceId' => 0,
    ],
    'MQTT shadow default is not disabled and side-effect free.'
);

$account->testSetProperty('EnableMqttShadow', true);
$account->testSetProperty('EnableMqttPositionDiagnostics', true);
$account->testSetProperty(
    'MqttReceiverInstanceId',
    MQTT_TEST_RECEIVER_ID
);
$missing = decodeMqttAccount(
    $account->ValidateMqttShadowConfiguration()
);
assertMqttAccount(
    $missing['status'] === 'configuration-invalid',
    'Missing Receiver did not fail closed.'
);

$mqttTestInstances = [
    MQTT_TEST_RECEIVER_ID => [
        'ModuleInfo' => ['ModuleID' => MQTT_TEST_RECEIVER_MODULE],
        'ConnectionID' => MQTT_TEST_CLIENT_ID,
    ],
    MQTT_TEST_CLIENT_ID => [
        'ModuleInfo' => ['ModuleID' => MQTT_TEST_CLIENT_MODULE],
        'ConnectionID' => MQTT_TEST_WEBSOCKET_ID,
    ],
    MQTT_TEST_WEBSOCKET_ID => [
        'ModuleInfo' => ['ModuleID' => MQTT_TEST_WEBSOCKET_MODULE],
        'ConnectionID' => 0,
    ],
];
$mqttTestProperties[MQTT_TEST_RECEIVER_ID] = [
    'AccountInstanceId' => MQTT_TEST_ACCOUNT_ID,
];
$account->testSetAttribute(
    'DiscoveryCache',
    json_encode([['id' => 'DEVICE_001']], JSON_THROW_ON_ERROR)
);
$topics = [
    '/downlink/vehicle/DEVICE_001/realtimeDate/attributes',
    '/downlink/vehicle/DEVICE_001/realtimeDate/event',
    '/downlink/vehicle/DEVICE_001/realtimeDate/location',
    '/downlink/vehicle/DEVICE_001/realtimeDate/state',
];
$mqttTestConfigurations[MQTT_TEST_CLIENT_ID] = [
    'UserName' => '',
    'Password' => '',
    'ClientID' => '',
    'KeepAliveInterval' => 60,
    'Subscriptions' => json_encode(
        array_map(
            static fn (string $topic): array => [
                'Topic' => $topic,
                'QualityOfService' => 0,
            ],
            $topics
        ),
        JSON_THROW_ON_ERROR
    ),
];
$mqttTestConfigurations[MQTT_TEST_WEBSOCKET_ID] = [
    'Active' => false,
    'URL' => '',
    'Headers' => '[]',
    'Type' => 1,
    'VerifyCertificate' => true,
];
assertMqttAccount(
    $account->AdoptMqttShadowChain() === 'MQTT chain adopted.',
    'Dedicated inactive MQTT chain was not adopted.'
);

$ready = decodeMqttAccount(
    $account->ValidateMqttShadowConfiguration()
);
assertMqttAccount(
    $ready['valid'] === true && $ready['status'] === 'ready',
    'Valid dedicated MQTT chain was not accepted.'
);

$beforeVariables = $account->testSnapshotPersistentState()['variables'];
$result = $account->IngestMqttEnvelope(
    MQTT_TEST_RECEIVER_ID,
    mqttAccountEnvelope()
);
assertMqttAccount(
    $result === 'accepted',
    'Fixture-backed state envelope was not accepted.'
);
$lifecycle = decodeMqttAccount(
    $account->testReadAttribute('MqttLifecycleRegistry')
);
assertMqttAccount(
    ($lifecycle['state'] ?? null) === 'ShadowActive',
    'Accepted MQTT evidence did not activate lifecycle shadow state.'
);
$afterVariables = $account->testSnapshotPersistentState()['variables'];
assertMqttAccount(
    $beforeVariables === $afterVariables,
    'MQTT ingestion changed a public Account variable.'
);

$account->testSetAttribute(
    'MqttPilotObservationRegistry',
    json_encode(['sessionSequence' => 12], JSON_THROW_ON_ERROR)
);
$positionResult = $account->IngestMqttEnvelope(
    MQTT_TEST_RECEIVER_ID,
    mqttAccountLocationEnvelope()
);
$position = decodeMqttAccount(
    $account->GetMqttPositionDiagnostics()
);
assertMqttAccount(
    $positionResult === 'accepted'
        && $position['status'] === 'available'
        && $position['authority'] === 'diagnostic-only'
        && $position['coordinateSystem'] === 'local-map'
        && $position['trackedDeviceCount'] === 1
        && $position['observation']['latest']['localX'] === 12.5
        && $position['observation']['latest']['localY'] === -8.25
        && $position['observation']['latest']['orientation'] === 0.5
        && $position['observation']['latest']['sessionSequence'] === 12
        && $position['observation']['counters']['retainedSampleCount'] === 1
        && $account->testSnapshotPersistentState()['variables']
            === $afterVariables,
    'Position ingestion changed public state or lost bounded local pose.'
);
$positionJson = $account->GetMqttPositionDiagnostics();
assertMqttAccount(
    !str_contains($positionJson, 'DEVICE_001')
        && !str_contains($positionJson, '/downlink/')
        && !str_contains($positionJson, 'posture'),
    'Position diagnostics exposed an identity, topic or raw field name.'
);

$taskProgressResult = $account->IngestMqttEnvelope(
    MQTT_TEST_RECEIVER_ID,
    mqttAccountTaskEnvelope([
        'action' => 8,
        'currentMowBoundary' => 700001,
        'currentMowProgress' => 4250,
        'mapWorkPosition' => str_repeat('a', 128),
        'mowStartType' => 0,
        'mowingPercentage' => 42,
        'mowingWeekArea' => '123.45',
        'subAction' => 6,
        'subtotalArea' => '23.45',
        'time' => 1700000003000,
        'type' => 1,
    ])
);
$partitionResult = $account->IngestMqttEnvelope(
    MQTT_TEST_RECEIVER_ID,
    mqttAccountTaskEnvelope([
        'partitionIds' => [700001, 700002],
        'time' => 1700000004000,
        'type' => 3,
    ])
);
$taskDelayResult = $account->IngestMqttEnvelope(
    MQTT_TEST_RECEIVER_ID,
    mqttAccountTaskEnvelope([
        'taskDelay' => true,
        'type' => 4,
    ])
);
$taskDiagnosticsJson = $account->GetMqttDiagnostics();
$taskDiagnostics = decodeMqttAccount($taskDiagnosticsJson);
$taskFields = $taskDiagnostics['shadow']['observation']['fields'] ?? [];
assertMqttAccount(
    $taskProgressResult === 'accepted'
        && $partitionResult === 'accepted'
        && $taskDelayResult === 'accepted'
        && ($taskFields['action'] ?? null) === 8
        && ($taskFields['subAction'] ?? null) === 6
        && ($taskFields['mowStartType'] ?? null) === 0
        && ($taskFields['currentMowProgress'] ?? null) === 4250
        && ($taskFields['mowingPercentage'] ?? null) === 42
        && ($taskFields['subtotalArea'] ?? null) === 23.45
        && ($taskFields['mowingWeekArea'] ?? null) === 123.45
        && ($taskFields['taskDelay'] ?? null) === true
        && ($taskFields['partitionCount'] ?? null) === 2
        && preg_match(
            '/^[a-f0-9]{64}$/D',
            (string) ($taskFields['boundaryKey'] ?? '')
        ) === 1
        && preg_match(
            '/^[a-f0-9]{64}$/D',
            (string) ($taskFields['partitionKey'] ?? '')
        ) === 1
        && !str_contains($taskDiagnosticsJson, str_repeat('a', 128)),
    'Task telemetry did not reach the bounded diagnostic projection.'
);
$taskShadowJson = $account->testReadAttribute('MqttShadowState');
$taskLedgerJson = $account->testReadAttribute(
    'MqttTaskObservationLedger'
);
$taskLedgerDiagnosticsJson =
    $account->GetMqttTaskObservationDiagnostics();
$taskLedgerDiagnostics = decodeMqttAccount(
    $taskLedgerDiagnosticsJson
);
assertMqttAccount(
    !str_contains($taskShadowJson, '700001')
        && !str_contains($taskShadowJson, '700002')
        && !str_contains($taskShadowJson, 'currentMowBoundary')
        && !str_contains($taskShadowJson, 'partitionIds')
        && !str_contains($taskShadowJson, 'mapWorkPosition'),
    'Raw task identity or opaque work-position data was persisted.'
);
assertMqttAccount(
    $taskLedgerDiagnostics['status'] === 'available'
        && $taskLedgerDiagnostics['authority'] === 'mqtt-inference'
        && $taskLedgerDiagnostics['semanticUnit']
            === 'correlated-zone-pass'
        && $taskLedgerDiagnostics['retainedPassCount'] === 1
        && $taskLedgerDiagnostics['passes'][0]['lastProgress'] === 4250
        && !str_contains($taskLedgerJson, 'DEVICE_001')
        && !str_contains($taskLedgerJson, '700001')
        && !str_contains($taskLedgerJson, '700002')
        && !str_contains($taskLedgerDiagnosticsJson, '/downlink/')
        && !str_contains($taskLedgerDiagnosticsJson, 'mapWorkPosition'),
    'Task observation ledger is unavailable, unbounded or not privacy-safe.'
);
$positionRoot = decodeMqttAccount(
    $account->testReadAttribute('MqttPositionDiagnostic')
);
$positionRoot['conflictingDeviceCount'] = 1;
$account->testSetAttribute(
    'MqttPositionDiagnostic',
    json_encode($positionRoot, JSON_THROW_ON_ERROR)
);
$ambiguousPosition = decodeMqttAccount(
    $account->GetMqttPositionDiagnostics()
);
assertMqttAccount(
    $ambiguousPosition['status'] === 'ambiguous'
        && $ambiguousPosition['trackedDeviceCount'] === 1
        && $ambiguousPosition['observation'] === null,
    'Cross-device position evidence did not fail closed.'
);
$account->testSetAttribute('MqttPositionDiagnostic', '{');
$invalidPosition = decodeMqttAccount(
    $account->GetMqttPositionDiagnostics()
);
assertMqttAccount(
    $invalidPosition['status'] === 'invalid'
        && $invalidPosition['observation'] === null,
    'Malformed position diagnostics did not fail closed.'
);
$positionRoot['conflictingDeviceCount'] = 0;
$account->testSetAttribute(
    'MqttPositionDiagnostic',
    json_encode($positionRoot, JSON_THROW_ON_ERROR)
);

$shadow = decodeMqttAccount(
    $account->testReadAttribute('MqttShadowState')
);
assertMqttAccount(
    count($shadow['devices']) === 1
        && !str_contains(
            $account->testReadAttribute('MqttShadowState'),
            'DEVICE_001'
        ),
    'MQTT shadow did not retain exactly one hashed semantic state.'
);
$pending = decodeMqttAccount(
    $account->testReadAttribute('MqttPendingReconciliation')
);
assertMqttAccount(
    count($pending['entries']) === 1,
    'MQTT ingestion did not queue bounded REST reconciliation.'
);

$mqttTestConfigurations[MQTT_TEST_CLIENT_ID]['Subscriptions'] =
    json_encode(
        [[
            'Topic' => '/downlink/vehicle/+/realtimeDate/state',
            'QualityOfService' => 0,
        ]],
        JSON_THROW_ON_ERROR
    );
$wildcard = decodeMqttAccount(
    $account->ValidateMqttShadowConfiguration()
);
assertMqttAccount(
    $wildcard['status'] === 'configuration-invalid',
    'Wildcard subscription did not fail closed.'
);

$mqttTestConfigurations[MQTT_TEST_CLIENT_ID]['Subscriptions'] =
    json_encode(
        array_map(
            static fn (string $topic): array => [
                'Topic' => $topic,
                'QualityOfService' => 0,
            ],
            $topics
        ),
        JSON_THROW_ON_ERROR
    );
$account->ApplyChanges();
$clearedShadow = decodeMqttAccount(
    $account->testReadAttribute('MqttShadowState')
);
$clearedPending = decodeMqttAccount(
    $account->testReadAttribute('MqttPendingReconciliation')
);
$clearedPosition = decodeMqttAccount(
    $account->GetMqttPositionDiagnostics()
);
$retainedTaskLedger = decodeMqttAccount(
    $account->GetMqttTaskObservationDiagnostics()
);
assertMqttAccount(
    $clearedShadow['devices'] === []
        && $clearedPending['entries'] === []
        && $clearedPosition['status'] === 'unavailable'
        && $clearedPosition['trackedDeviceCount'] === 0
        && $retainedTaskLedger['status'] === 'available'
        && $retainedTaskLedger['retainedPassCount'] === 1,
    'ApplyChanges did not clear ephemeral MQTT state.'
);

echo "Navimow MQTT Account ingestion checks passed.\n";
