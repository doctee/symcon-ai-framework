<?php

declare(strict_types=1);

$reconcileInstances = [];
$reconcileProperties = [];
$reconcileConfigurations = [];

function IPS_InstanceExists(int $instanceId): bool
{
    global $reconcileInstances;

    return isset($reconcileInstances[$instanceId]);
}

function IPS_GetInstance(int $instanceId): array
{
    global $reconcileInstances;

    return $reconcileInstances[$instanceId] ?? [];
}

function IPS_GetProperty(int $instanceId, string $name): mixed
{
    global $reconcileProperties;

    return $reconcileProperties[$instanceId][$name] ?? null;
}

function IPS_GetConfiguration(int $instanceId): string
{
    global $reconcileConfigurations;

    return json_encode(
        $reconcileConfigurations[$instanceId] ?? [],
        JSON_THROW_ON_ERROR
    );
}

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/harness/FakeClock.php';
require_once __DIR__
    . '/../distribution/NavimowAccount/module.php';
require_once __DIR__
    . '/../distribution/NavimowDevice/module.php';

final class MqttReconciliationAccount extends NavimowAccount
{
    public function __construct(
        int $instanceId,
        private NavimowTestFakeClock $clock,
        private Closure $transport
    ) {
        parent::__construct($instanceId);
    }

    protected function currentTimestamp(): int
    {
        return $this->clock->now();
    }

    protected function createApiClient(): Navimow\ApiClient
    {
        return new Navimow\ApiClient(
            'https://navimow.invalid',
            $this->transport
        );
    }
}

function assertReconciliation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function decodeReconciliation(string $json): array
{
    $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected bounded JSON data.');
    }

    return $decoded;
}

function reconciliationEnvelope(): string
{
    $fixture = decodeReconciliation((string) file_get_contents(
        __DIR__ . '/../fixtures/mqtt/symcon-envelope-state.json'
    ));

    return json_encode($fixture['envelope'], JSON_THROW_ON_ERROR);
}

function reconciliationStatusResponse(
    int $battery = 43,
    string $vehicleState = 'isRunning'
): array
{
    return [
        'status' => 200,
        'body' => json_encode([
            'code' => 1,
            'desc' => 'Operation successful',
            'data' => [
                'payload' => [
                    'devices' => [[
                        'id' => 'DEVICE_001',
                        'capacityRemaining' => [[
                            'unit' => 'PERCENTAGE',
                            'rawValue' => $battery,
                        ]],
                        'vehicleState' => $vehicleState,
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ];
}

$accountId = 1001;
$receiverId = 2001;
$mqttId = 2002;
$webSocketId = 2003;
$clock = new NavimowTestFakeClock(1700000100);
$transportCalls = [];
$statusBattery = 43;
$statusState = 'isRunning';
$transportFailure = false;
$transport = static function (array $request) use (
    &$transportCalls,
    &$statusBattery,
    &$statusState,
    &$transportFailure
): array {
    $transportCalls[] = $request;
    if ($transportFailure) {
        return [
            'status' => 503,
            'body' => '{"error":"synthetic"}',
        ];
    }
    return reconciliationStatusResponse($statusBattery, $statusState);
};
$account = new MqttReconciliationAccount(
    $accountId,
    $clock,
    $transport
);
$account->Create();
$account->ApplyChanges();
$account->testSetProperty('EnableMqttShadow', true);
$account->testSetProperty('MqttReceiverInstanceId', $receiverId);
$account->testSetAttribute('AccessToken', 'SYNTHETIC_ACCESS');
$account->testSetAttribute(
    'TokenExpiresAtInternal',
    1700003600
);
$account->testSetAttribute(
    'DiscoveryCache',
    json_encode(
        [['id' => 'DEVICE_001']],
        JSON_THROW_ON_ERROR
    )
);

$reconcileInstances = [
    $receiverId => [
        'ModuleInfo' => [
            'ModuleID' => '{1B9960A2-A30C-D846-DF55-800F583AA812}',
        ],
        'ConnectionID' => $mqttId,
    ],
    $mqttId => [
        'ModuleInfo' => [
            'ModuleID' => '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}',
        ],
        'ConnectionID' => $webSocketId,
    ],
    $webSocketId => [
        'ModuleInfo' => [
            'ModuleID' => '{D68FD31F-0E90-7019-F16C-1949BD3079EF}',
        ],
        'ConnectionID' => 0,
    ],
];
$reconcileProperties[$receiverId] = [
    'AccountInstanceId' => $accountId,
];
$topics = array_map(
    static fn (string $channel): string => sprintf(
        '/downlink/vehicle/DEVICE_001/realtimeDate/%s',
        $channel
    ),
    ['attributes', 'event', 'location', 'state']
);
$reconcileConfigurations[$mqttId] = [
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
$reconcileConfigurations[$webSocketId] = [
    'Active' => false,
    'URL' => '',
    'Headers' => '[]',
    'Type' => 1,
    'VerifyCertificate' => true,
];
assertReconciliation(
    $account->AdoptMqttShadowChain() === 'MQTT chain adopted.',
    'Reconciliation MQTT chain was not adopted.'
);

$accountVariables = $account->testSnapshotPersistentState()['variables'];
assertReconciliation(
    $account->IngestMqttEnvelope(
        $receiverId,
        reconciliationEnvelope()
    ) === 'accepted',
    'First MQTT state was not accepted.'
);
$clock->set(1700000102);
assertReconciliation(
    $account->IngestMqttEnvelope(
        $receiverId,
        reconciliationEnvelope()
    ) === 'accepted',
    'Repeated MQTT state was not accepted.'
);
$pending = decodeReconciliation(
    $account->testReadAttribute('MqttPendingReconciliation')
);
$entry = array_values($pending['entries'])[0] ?? null;
assertReconciliation(
    count($pending['entries']) === 1
        && ($entry['firstQueuedAt'] ?? null) === 1700000100
        && ($entry['lastHintAt'] ?? null) === 1700000102
        && ($entry['notBefore'] ?? null) === 1700000130,
    'Repeated MQTT hints did not coalesce deterministically.'
);
assertReconciliation(
    $account->testTimerInterval('MqttReconcile') === 28000,
    'Reconciliation timer did not retain the first due time.'
);
assertReconciliation(
    $accountVariables
        === $account->testSnapshotPersistentState()['variables'],
    'MQTT ingestion changed an Account variable.'
);

$clock->set(1700000129);
$account->ProcessMqttReconciliation();
assertReconciliation(
    $account->testChildMessages() === []
        && $account->testTimerInterval('MqttReconcile') === 1000,
    'Reconciliation ran before its not-before timestamp.'
);

$clock->set(1700000130);
$account->ProcessMqttReconciliation();
$messages = $account->testChildMessages();
$targetedMessage = decodeReconciliation(
    $messages[array_key_last($messages)]
);
assertReconciliation(
    ($targetedMessage['Function'] ?? null) === 'PollStatus'
        && ($targetedMessage['DeviceId'] ?? null) === 'DEVICE_001'
        && ($targetedMessage['Reason'] ?? null)
            === 'mqtt-shadow-reconciliation',
    'Account did not emit the exact targeted poll message.'
);
assertReconciliation(
    decodeReconciliation(
        $account->testReadAttribute('MqttPendingReconciliation')
    )['entries'] === [],
    'Processed reconciliation remained queued.'
);

$target = new NavimowDevice(3001);
$target->Create();
$target->testSetProperty('DeviceId', 'DEVICE_001');
$target->ApplyChanges();
$other = new NavimowDevice(3002);
$other->Create();
$other->testSetProperty('DeviceId', 'DEVICE_002');
$other->ApplyChanges();
$parentHandler = static fn (string $json): string
    => $account->ForwardData($json);
$target->testSetParentHandler($parentHandler);
$other->testSetParentHandler($parentHandler);

$otherBefore = $other->testSnapshotPersistentState()['variables'];
$other->ReceiveData(json_encode(
    $targetedMessage,
    JSON_THROW_ON_ERROR
));
assertReconciliation(
    $otherBefore === $other->testSnapshotPersistentState()['variables']
        && $transportCalls === [],
    'Non-target Device performed a REST read or variable write.'
);

$target->ReceiveData(json_encode(
    $targetedMessage,
    JSON_THROW_ON_ERROR
));
assertReconciliation(
    count($transportCalls) === 1
        && $target->testReadVariable('VehicleState') === 1
        && $target->testReadVariable('BatteryLevel') === 43
        && $target->testReadVariable('LastStatusUpdate')
            === 1700000130,
    'Target Device did not apply the authoritative REST result.'
);
$statistics = decodeReconciliation(
    $account->testReadAttribute('MqttStatistics')
);
assertReconciliation(
    ($statistics['comparisonMatches'] ?? 0) === 1
        && ($statistics['comparisonMismatches'] ?? 0) === 0,
    'REST/MQTT comparison did not record a private match.'
);

$statusBattery = 44;
$clock->set(1700000140);
$account->ForwardData(json_encode([
    'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}',
    'SchemaVersion' => 1,
    'Function' => 'GetStatus',
    'DeviceId' => 'DEVICE_001',
], JSON_THROW_ON_ERROR));
$statistics = decodeReconciliation(
    $account->testReadAttribute('MqttStatistics')
);
assertReconciliation(
    ($statistics['comparisonMatches'] ?? 0) === 2,
    'One-percent battery tolerance was not treated as a match.'
);

$statusBattery = 46;
$clock->set(1700000141);
$account->ForwardData(json_encode([
    'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}',
    'SchemaVersion' => 1,
    'Function' => 'GetStatus',
    'DeviceId' => 'DEVICE_001',
], JSON_THROW_ON_ERROR));
$statistics = decodeReconciliation(
    $account->testReadAttribute('MqttStatistics')
);
assertReconciliation(
    ($statistics['comparisonMismatches'] ?? 0) === 1,
    'REST/MQTT mismatch was not retained as private diagnostics.'
);

$clock->set(1700000500);
$statusBattery = 43;
$account->ForwardData(json_encode([
    'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}',
    'SchemaVersion' => 1,
    'Function' => 'GetStatus',
    'DeviceId' => 'DEVICE_001',
], JSON_THROW_ON_ERROR));
$statistics = decodeReconciliation(
    $account->testReadAttribute('MqttStatistics')
);
assertReconciliation(
    ($statistics['comparisonStale'] ?? 0) === 1,
    'Stale MQTT candidate was compared as current state.'
);

$shadowBeforeFailure = $account->testReadAttribute('MqttShadowState');
$transportFailure = true;
$failure = decodeReconciliation($account->ForwardData(json_encode([
    'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}',
    'SchemaVersion' => 1,
    'Function' => 'GetStatus',
    'DeviceId' => 'DEVICE_001',
], JSON_THROW_ON_ERROR)));
assertReconciliation(
    ($failure['status'] ?? null) === 'error'
        && $shadowBeforeFailure
            === $account->testReadAttribute('MqttShadowState'),
    'REST failure changed or exposed the private MQTT shadow.'
);
$transportFailure = false;

$clock->set(1700000501);
$account->IngestMqttEnvelope(
    $receiverId,
    reconciliationEnvelope()
);
$cooldownEntry = array_values(decodeReconciliation(
    $account->testReadAttribute('MqttPendingReconciliation')
)['entries'])[0] ?? null;
assertReconciliation(
    ($cooldownEntry['notBefore'] ?? null) === 1700000531,
    'Per-device REST wake cooldown is not bounded to 30 seconds.'
);

$clock->set(1700000600);
$boundedEntries = [];
for ($index = 1; $index <= 5; $index++) {
    $deviceId = 'DEVICE_001';
    $deviceKey = hash('sha256', $deviceId . ':' . $index);
    $boundedEntries[$deviceKey] = [
        'deviceId' => $deviceId,
        'firstQueuedAt' => 1700000500 + $index,
        'lastHintAt' => 1700000500 + $index,
        'notBefore' => 1700000590,
        'reasonCode' => 'mqtt-semantic-hint',
    ];
}
$account->testSetAttribute(
    'MqttPendingReconciliation',
    json_encode([
        'formatVersion' => 1,
        'entries' => $boundedEntries,
    ], JSON_THROW_ON_ERROR)
);
$messageCountBefore = count($account->testChildMessages());
$account->ProcessMqttReconciliation();
$remaining = decodeReconciliation(
    $account->testReadAttribute('MqttPendingReconciliation')
);
assertReconciliation(
    count($account->testChildMessages()) - $messageCountBefore === 4
        && count($remaining['entries']) === 1,
    'One reconciliation run did not enforce the four-device bound.'
);

echo "Navimow MQTT shadow reconciliation checks passed.\n";
