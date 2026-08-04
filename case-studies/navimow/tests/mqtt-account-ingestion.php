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

$account = new NavimowAccount(MQTT_TEST_ACCOUNT_ID);
$account->Create();
$account->ApplyChanges();

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
assertMqttAccount(
    $clearedShadow['devices'] === []
        && $clearedPending['entries'] === [],
    'ApplyChanges did not clear ephemeral MQTT state.'
);

echo "Navimow MQTT Account ingestion checks passed.\n";
