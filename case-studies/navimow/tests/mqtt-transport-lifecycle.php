<?php

declare(strict_types=1);

$lifecycleInstances = [];
$lifecycleProperties = [];
$lifecycleConfigurations = [];
$lifecycleOperations = [];
$lifecycleConfigurationReadFailures = [];
$lifecycleConfigurationReadCounts = [];

function IPS_InstanceExists(int $instanceId): bool
{
    global $lifecycleInstances;

    return isset($lifecycleInstances[$instanceId]);
}

function IPS_GetInstance(int $instanceId): array
{
    global $lifecycleInstances;

    return $lifecycleInstances[$instanceId] ?? [];
}

function IPS_GetProperty(int $instanceId, string $name): mixed
{
    global $lifecycleProperties;

    return $lifecycleProperties[$instanceId][$name] ?? null;
}

function IPS_GetConfiguration(int $instanceId): string
{
    global $lifecycleConfigurations;
    global $lifecycleConfigurationReadFailures;
    global $lifecycleConfigurationReadCounts;

    $lifecycleConfigurationReadCounts[$instanceId] =
        ($lifecycleConfigurationReadCounts[$instanceId] ?? 0) + 1;
    $remainingFailures =
        $lifecycleConfigurationReadFailures[$instanceId] ?? 0;
    if ($remainingFailures > 0) {
        $lifecycleConfigurationReadFailures[$instanceId] =
            $remainingFailures - 1;
        throw new RuntimeException(
            'Synthetic Core configuration is not ready.'
        );
    }

    return json_encode(
        $lifecycleConfigurations[$instanceId] ?? [],
        JSON_THROW_ON_ERROR
    );
}

function IPS_SetProperty(
    int $instanceId,
    string $name,
    mixed $value
): void {
    global $lifecycleConfigurations, $lifecycleOperations;

    $lifecycleConfigurations[$instanceId][$name] = $value;
    $lifecycleOperations[] = [
        'operation' => 'set',
        'instanceId' => $instanceId,
        'name' => $name,
        'value' => $value,
    ];
}

function IPS_ApplyChanges(int $instanceId): void
{
    global $lifecycleInstances, $lifecycleOperations;

    $lifecycleOperations[] = [
        'operation' => 'apply',
        'instanceId' => $instanceId,
    ];
    $lifecycleInstances[$instanceId]['InstanceStatus'] = 102;
}

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/harness/FakeClock.php';
require_once __DIR__
    . '/../distribution/NavimowAccount/module.php';

final class MqttLifecycleAccount extends NavimowAccount
{
    private int $kernelStartTime;

    public function __construct(
        int $instanceId,
        private NavimowTestFakeClock $clock,
        private Closure $transport
    ) {
        parent::__construct($instanceId);
        $this->kernelStartTime = $clock->now();
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

    protected function createApiClient(): Navimow\ApiClient
    {
        return new Navimow\ApiClient(
            'https://navimow.invalid',
            $this->transport
        );
    }

    protected function setOwnProperty(string $name, mixed $value): void
    {
        $this->testSetProperty($name, $value);
    }

    protected function applyOwnChanges(): void
    {
        $this->ApplyChanges();
    }
}

function assertLifecycle(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertLifecycleThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (UnexpectedValueException) {
        return;
    }

    throw new RuntimeException($message);
}

function decodeLifecycle(string $json): array
{
    $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected bounded JSON data.');
    }

    return $decoded;
}

function lifecycleSubscriptions(
    string $deviceId,
    bool $legacy = false
): string {
    $subscriptions = [];
    foreach (['attributes', 'event', 'location', 'state'] as $channel) {
        $subscription = [
            'Topic' => sprintf(
                '/downlink/vehicle/%s/realtimeDate/%s',
                $deviceId,
                $channel
            ),
        ];
        $subscription[$legacy ? 'QualityOfService' : 'QoS'] = 0;
        $subscriptions[] = $subscription;
    }

    return json_encode($subscriptions, JSON_THROW_ON_ERROR);
}

$canonicalSubscriptions =
    Navimow\MqttTransportConfiguration::createSubscriptions([
        ['id' => 'DEVICE_001'],
    ]);
assertLifecycle(
    array_keys($canonicalSubscriptions[0]) === ['Topic', 'QoS']
        && $canonicalSubscriptions[0]['QoS'] === 0,
    'Canonical subscriptions do not use the native QoS key.'
);
assertLifecycle(
    Navimow\MqttTransportConfiguration::configuredSubscriptions([
        'Subscriptions' => json_encode(
            $canonicalSubscriptions,
            JSON_THROW_ON_ERROR
        ),
    ]) === $canonicalSubscriptions,
    'Canonical subscriptions did not round-trip.'
);
$legacySubscriptions = json_decode(
    lifecycleSubscriptions('DEVICE_001', true),
    true,
    16,
    JSON_THROW_ON_ERROR
);
assertLifecycle(
    Navimow\MqttTransportConfiguration::configuredSubscriptions([
        'Subscriptions' => $legacySubscriptions,
    ]) === $canonicalSubscriptions,
    'Exact legacy subscriptions were not normalized.'
);
foreach (
    [
        'mixed keys' => [
            'Topic' => $canonicalSubscriptions[0]['Topic'],
            'QoS' => 0,
            'QualityOfService' => 0,
        ],
        'unknown key' => [
            'Topic' => $canonicalSubscriptions[0]['Topic'],
            'QoS' => 0,
            'Enabled' => true,
        ],
        'nonzero QoS' => [
            'Topic' => $canonicalSubscriptions[0]['Topic'],
            'QoS' => 1,
        ],
        'string QoS' => [
            'Topic' => $canonicalSubscriptions[0]['Topic'],
            'QoS' => '0',
        ],
        'missing QoS' => [
            'Topic' => $canonicalSubscriptions[0]['Topic'],
        ],
    ] as $name => $invalidSubscription
) {
    assertLifecycleThrows(
        static fn (): array =>
            Navimow\MqttTransportConfiguration::configuredSubscriptions([
                'Subscriptions' => [$invalidSubscription],
            ]),
        sprintf('Invalid subscription schema was accepted: %s.', $name)
    );
}

$accountId = 4101;
$receiverId = 4201;
$mqttId = 4202;
$webSocketId = 4203;
$clock = new NavimowTestFakeClock(1700000000);
$credentialCalls = 0;
$credentialFailureKind = '';
$transport = static function (array $request) use (
    &$credentialCalls,
    &$credentialFailureKind
): array {
    if (str_contains($request['url'], '/oauth/getAccessToken')) {
        return [
            'status' => 200,
            'body' => json_encode([
                'access_token' => 'SYNTHETIC_REFRESHED_ACCESS',
                'refresh_token' => 'SYNTHETIC_REFRESH_TOKEN',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR),
        ];
    }
    if (!str_contains($request['url'], '/mqtt/userInfo/get/v2')) {
        throw new RuntimeException('Unexpected lifecycle transport call.');
    }
    $credentialCalls++;
    if ($credentialFailureKind === 'transport') {
        throw new RuntimeException('Synthetic credential failure.');
    }
    if ($credentialFailureKind !== '') {
        throw new Navimow\ApiException(
            $credentialFailureKind,
            'Synthetic credential failure.'
        );
    }

    return [
        'status' => 200,
        'body' => (string) file_get_contents(
            __DIR__ . '/../fixtures/mqtt/mqtt-credential-success.json'
        ),
    ];
};

$lifecycleInstances = [
    $receiverId => [
        'ModuleInfo' => [
            'ModuleID' => '{1B9960A2-A30C-D846-DF55-800F583AA812}',
        ],
        'ConnectionID' => $mqttId,
        'InstanceStatus' => 104,
    ],
    $mqttId => [
        'ModuleInfo' => [
            'ModuleID' => '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}',
        ],
        'ConnectionID' => $webSocketId,
        'InstanceStatus' => 104,
    ],
    $webSocketId => [
        'ModuleInfo' => [
            'ModuleID' => '{D68FD31F-0E90-7019-F16C-1949BD3079EF}',
        ],
        'ConnectionID' => 0,
        'InstanceStatus' => 104,
    ],
];
$lifecycleProperties[$receiverId] = [
    'AccountInstanceId' => $accountId,
];
$lifecycleConfigurations[$mqttId] = [
    'UserName' => '',
    'Password' => '',
    'ClientID' => '',
    'KeepAliveInterval' => 60,
    'Subscriptions' => lifecycleSubscriptions('DEVICE_001', true),
];
$lifecycleConfigurations[$webSocketId] = [
    'URL' => '',
    'Headers' => '[]',
    'Type' => 1,
    'VerifyCertificate' => true,
    'Active' => false,
];

$account = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$account->Create();
$account->ApplyChanges();
assertLifecycle(
    $account->testStatus() === IS_ACTIVE
        && $lifecycleOperations === []
        && $account->testRegisteredMessages() === [[
            'senderId' => 0,
            'messageId' => IPS_KERNELSTARTED,
        ]],
    'Default-disabled ApplyChanges touched a Core instance.'
);
$account->testSetProperty('ClientSecret', 'SYNTHETIC_CLIENT_SECRET');
$account->ApplyChanges();
assertLifecycle(
    $account->testStatus() === IS_ACTIVE
        && $lifecycleOperations === [],
    'Authorization-pending ApplyChanges did not finalize active status.'
);
$account->testSetProperty('EnableMqttShadow', true);
$account->testSetProperty('MqttReceiverInstanceId', $receiverId);
$account->testSetAttribute('AccessToken', 'SYNTHETIC_ACCESS_TOKEN');
$account->testSetAttribute('RefreshToken', 'SYNTHETIC_REFRESH_TOKEN');
$account->testSetAttribute('TokenExpiresAtInternal', 1700003600);
$account->testSetAttribute(
    'DiscoveryCache',
    json_encode([['id' => 'DEVICE_001']], JSON_THROW_ON_ERROR)
);
$account->ApplyChanges();
assertLifecycle(
    $account->testStatus() === IS_ACTIVE
        && $lifecycleOperations === [],
    'Authenticated ApplyChanges did not finalize active status.'
);

$candidate = decodeLifecycle(
    $account->ValidateMqttAdoptionCandidate()
);
assertLifecycle(
    ($candidate['valid'] ?? false) === true
        && $lifecycleOperations === [],
    'Candidate validation was not read-only and successful.'
);
assertLifecycle(
    $account->AdoptMqttShadowChain() === 'MQTT chain adopted.',
    'Explicit adoption failed.'
);
$ownership = $account->testReadAttribute('MqttOwnershipRegistry');
$identity = $account->testReadAttribute('MqttClientIdentity');
assertLifecycle(
    preg_match('/^[a-f0-9]{32}$/D', $identity) === 1
        && !str_contains($ownership, 'DEVICE_001')
        && !str_contains($ownership, 'SYNTHETIC_')
        && $lifecycleOperations === [],
    'Adoption did not retain only redacted ownership evidence.'
);
$ownershipBefore = $ownership;
assertLifecycle(
    $account->AdoptMqttShadowChain()
        === 'MQTT chain is already adopted.'
        && $account->testReadAttribute('MqttOwnershipRegistry')
            === $ownershipBefore
        && $account->testReadAttribute('MqttClientIdentity')
            === $identity,
    'Repeated adoption was not idempotent.'
);

$persistentBeforeDiagnostics = $account->testSnapshotPersistentState();
$operationsBeforeDiagnostics = $lifecycleOperations;
$readyDiagnosticsJson = $account->GetMqttDiagnostics();
$readyDiagnostics = decodeLifecycle($readyDiagnosticsJson);
assertLifecycle(
    array_keys($readyDiagnostics) === [
        'formatVersion',
        'featureEnabled',
        'configurationStatus',
        'lifecycle',
        'statistics',
        'errors',
        'shadow',
    ]
        && ($readyDiagnostics['formatVersion'] ?? null) === 2
        && ($readyDiagnostics['featureEnabled'] ?? null) === true
        && ($readyDiagnostics['configurationStatus'] ?? null) === 'ready'
        && ($readyDiagnostics['lifecycle']['state'] ?? null) === 'Ready'
        && ($readyDiagnostics['statistics']['received'] ?? null) === 0
        && ($readyDiagnostics['statistics']['accepted'] ?? null) === 0
        && ($readyDiagnostics['statistics']['rejected'] ?? null) === 0
        && ($readyDiagnostics['errors']['count'] ?? null) === 0
        && ($readyDiagnostics['shadow']['trackedDeviceCount'] ?? null) === 0
        && (
            $readyDiagnostics['shadow']['observation']['status']
                ?? null
        ) === 'unavailable'
        && $account->testSnapshotPersistentState()
            === $persistentBeforeDiagnostics
        && $lifecycleOperations === $operationsBeforeDiagnostics,
    'Ready MQTT diagnostics are not bounded and read-only.'
);

$lifecycleOperations = [];
assertLifecycle(
    $account->ConnectMqttShadow()
        === 'MQTT connection attempt started.',
    'Explicit MQTT connection failed.'
);
assertLifecycle(
    $credentialCalls === 1,
    'Connect did not retrieve credentials exactly once.'
);
$pilotAfterConnect = decodeLifecycle(
    $account->GetMqttPilotDiagnostics()
);
assertLifecycle(
    ($pilotAfterConnect['active'] ?? null) === true
        && ($pilotAfterConnect['startedAt'] ?? null)
            === $clock->now()
        && ($pilotAfterConnect['nextCheckpointAt'] ?? null)
            === $clock->now() + 18000
        && $account->testTimerInterval('MqttPilotCheckpoint')
            === 18000000,
    'Connect did not start the native pilot checkpoint schedule.'
);
$statusMessages = $account->testRegisteredMessages();
assertLifecycle(
    in_array([
        'senderId' => $mqttId,
        'messageId' => IM_CHANGESTATUS,
    ], $statusMessages, true)
        && in_array([
            'senderId' => $webSocketId,
            'messageId' => IM_CHANGESTATUS,
        ], $statusMessages, true),
    'Active pilot did not register both owned Core status messages.'
);
$account->MessageSink(
    1,
    $webSocketId,
    IM_CHANGESTATUS,
    ['secret' => 'SYNTHETIC_PRIVATE_VALUE']
);
$statusDiagnosticsJson = $account->GetMqttPilotDiagnostics();
$statusDiagnostics = decodeLifecycle($statusDiagnosticsJson);
assertLifecycle(
    count($statusDiagnostics['coreTransitions'] ?? []) === 1
        && (
            $statusDiagnostics['coreTransitions'][0]
                ['senderRole'] ?? null
        ) === 'websocket'
        && !str_contains(
            $statusDiagnosticsJson,
            'SYNTHETIC_PRIVATE_VALUE'
        ),
    'Owned status message was not sanitized and retained.'
);
$activations = array_values(array_filter(
    $lifecycleOperations,
    static fn (array $operation): bool =>
        ($operation['operation'] ?? null) === 'set'
        && ($operation['instanceId'] ?? null) === $webSocketId
        && ($operation['name'] ?? null) === 'Active'
        && ($operation['value'] ?? null) === true
));
assertLifecycle(
    count($activations) === 1
        && ($lifecycleOperations[0]['instanceId'] ?? null)
            === $webSocketId
        && ($lifecycleOperations[1]['operation'] ?? null) === 'apply',
    'Connect violated inactive-first or single-activation ordering.'
);
assertLifecycle(
    ($lifecycleConfigurations[$webSocketId]['Active'] ?? false) === true
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? '')
            === 'SYNTHETIC_MQTT_USER'
        && str_starts_with(
            (string) ($lifecycleConfigurations[$webSocketId]['Headers']
                ?? ''),
            '[{"Name":"Authorization","Value":"Bearer '
        ),
    'Connect did not configure the expected native transport shape.'
);
$configuredSubscriptions = json_decode(
    (string) ($lifecycleConfigurations[$mqttId]['Subscriptions'] ?? ''),
    true,
    16,
    JSON_THROW_ON_ERROR
);
assertLifecycle(
    is_array($configuredSubscriptions)
        && count($configuredSubscriptions) === 4
        && array_reduce(
            $configuredSubscriptions,
            static fn (bool $carry, mixed $subscription): bool =>
                $carry
                && is_array($subscription)
                && array_keys($subscription) === ['Topic', 'QoS']
                && ($subscription['QoS'] ?? null) === 0,
            true
        ),
    'Connect did not migrate subscriptions to the native QoS schema.'
);
$persistentJson = json_encode(
    $account->testSnapshotPersistentState(),
    JSON_THROW_ON_ERROR
);
assertLifecycle(
    !str_contains($persistentJson, 'SYNTHETIC_MQTT_USER')
        && !str_contains($persistentJson, 'SYNTHETIC_MQTT_PASSWORD'),
    'Private MQTT credentials leaked into Account persistence.'
);

$stateFixture = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../fixtures/mqtt/symcon-envelope-state.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$stateEnvelope = json_encode(
    $stateFixture['envelope'],
    JSON_THROW_ON_ERROR
);
$lifecycleOperations = [];
assertLifecycle(
    $account->IngestMqttEnvelope($receiverId, $stateEnvelope)
        === 'accepted'
        && $lifecycleOperations === [],
    'Synthetic accepted MQTT evidence touched Core configuration.'
);
$persistentBeforeActiveDiagnostics =
    $account->testSnapshotPersistentState();
$activeDiagnosticsJson = $account->GetMqttDiagnostics();
$activeDiagnostics = decodeLifecycle($activeDiagnosticsJson);
$expectedActiveDiagnostics = json_decode(
    (string) file_get_contents(
        __DIR__
        . '/../fixtures/mqtt/bounded-diagnostics-shadow-active.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
)['diagnostics'];
assertLifecycle(
    $activeDiagnostics === $expectedActiveDiagnostics
        && ($activeDiagnostics['configurationStatus'] ?? null) === 'ready'
        && ($activeDiagnostics['lifecycle']['state'] ?? null)
            === 'ShadowActive'
        && ($activeDiagnostics['lifecycle']['lastResult'] ?? null)
            === 'accepted'
        && ($activeDiagnostics['statistics']['connectionAttempts'] ?? null)
            === 1
        && ($activeDiagnostics['statistics']['received'] ?? null) === 1
        && ($activeDiagnostics['statistics']['accepted'] ?? null) === 1
        && ($activeDiagnostics['statistics']['rejected'] ?? null) === 0
        && ($activeDiagnostics['statistics']['lastReceivedAt'] ?? null)
            === 1700000000
        && ($activeDiagnostics['shadow']['trackedDeviceCount'] ?? null) === 1
        && (
            $activeDiagnostics['shadow']['pendingReconciliationCount']
                ?? null
        ) === 1
        && strlen($activeDiagnosticsJson) < 4096
        && !str_contains($activeDiagnosticsJson, 'DEVICE_001')
        && !str_contains($activeDiagnosticsJson, 'SYNTHETIC')
        && !str_contains($activeDiagnosticsJson, '/downlink/')
        && !str_contains($activeDiagnosticsJson, 'Bearer ')
        && !str_contains($activeDiagnosticsJson, 'wss://')
        && $account->testSnapshotPersistentState()
            === $persistentBeforeActiveDiagnostics
        && $lifecycleOperations === [],
    'Active MQTT diagnostics are incomplete, mutable or private.'
);

$lifecycleOperations = [];
assertLifecycle(
    $account->RefreshAuthentication() === 'Token refresh succeeded.'
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && $account->testTimerInterval('MqttLifecycle') === 5000,
    'OAuth refresh did not schedule a credential-safe MQTT rotation.'
);
$rotationDiagnostics = decodeLifecycle($account->GetMqttDiagnostics());
assertLifecycle(
    ($rotationDiagnostics['lifecycle']['state'] ?? null)
        === 'ReconnectScheduled'
        && (
            $rotationDiagnostics['lifecycle']['lastTransitionReason']
                ?? null
        ) === 'token-rotation'
        && (
            $rotationDiagnostics['statistics']['credentialRotations']
                ?? null
        ) === 1,
    'OAuth refresh did not expose bounded rotation diagnostics.'
);

$clock->advance(5);
$lifecycleOperations = [];
$account->ProcessMqttLifecycle();
$rotationAttemptDiagnostics = decodeLifecycle(
    $account->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === 2
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? false)
            === true
        && $account->testTimerInterval('MqttLifecycle') === 60000
        && (
            $rotationAttemptDiagnostics['statistics'][
                'lastConnectionTrigger'
            ] ?? null
        ) === 'rotation',
    'Scheduled token rotation did not reconnect exactly once.'
);
$clock->advance(60);
$account->ProcessMqttLifecycle();
$healthyDiagnostics = decodeLifecycle($account->GetMqttDiagnostics());
assertLifecycle(
    ($healthyDiagnostics['lifecycle']['state'] ?? null)
        === 'ShadowActive'
        && (
            $healthyDiagnostics['statistics']['connectionSuccesses']
                ?? null
        ) === 1,
    'Healthy MQTT transport was not confirmed after rotation.'
);

$persistentBeforeNativeRestart =
    $account->testSnapshotPersistentState();
$nativeActiveConfigurations = $lifecycleConfigurations;
$nativeActiveInstances = $lifecycleInstances;
$variablesBeforeNativeRestart =
    $persistentBeforeNativeRestart['variables'];
$credentialCallsBeforeNativeRestart = $credentialCalls;
$nativeStatisticsBeforeRestart = json_decode(
    (string) (
        $persistentBeforeNativeRestart['attributes']['MqttStatistics']
            ?? '{}'
    ),
    true,
    16,
    JSON_THROW_ON_ERROR
);
$connectionAttemptsBeforeNativeRestart =
    $nativeStatisticsBeforeRestart['connectionAttempts'] ?? 0;

$transientReadinessFixture = json_decode(
    (string) file_get_contents(
        __DIR__
        . '/../fixtures/mqtt/core-resume-transient-core-readiness.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$transientPreReady = $transientReadinessFixture['preReady'] ?? null;
$transientPostReady = $transientReadinessFixture['postReady'] ?? null;
assertLifecycle(
    is_array($transientPreReady) && is_array($transientPostReady),
    'Transient Core-readiness fixture is invalid.'
);

$clock->advance(1);
$transientKernelStart = $clock->now();
$transientReadinessAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$transientReadinessAccount->Create();
$transientReadinessAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$transientReadinessAccount->testSetKernelStartTime(
    $transientKernelStart
);
$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleOperations = [];
$lifecycleConfigurationReadCounts = [];
$lifecycleConfigurationReadFailures = [
    $mqttId => (int) (
        $transientPreReady['mqttConfigurationUnavailableReads'] ?? 0
    ),
];
$transientErrorsBefore = json_decode(
    (string) (
        $persistentBeforeNativeRestart['attributes'][
            'MqttErrorHistory'
        ] ?? '[]'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$transientStatisticsBefore = json_decode(
    (string) (
        $persistentBeforeNativeRestart['attributes'][
            'MqttStatistics'
        ] ?? '{}'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$transientReadinessAccount->ApplyChanges();
$transientAwaitingLifecycle = json_decode(
    (string) $transientReadinessAccount->testReadAttribute(
        'MqttLifecycleRegistry'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$transientErrorsAfter = json_decode(
    (string) $transientReadinessAccount->testReadAttribute(
        'MqttErrorHistory'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertLifecycle(
    $lifecycleOperations === []
        && array_sum($lifecycleConfigurationReadCounts) === (
            $transientPreReady['expectedConfigurationReads'] ?? null
        )
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? null)
            === true
        && $transientReadinessAccount->testTimerInterval(
            'MqttLifecycle'
        ) === (
            $transientPreReady['expectedTimerMilliseconds'] ?? null
        )
        && (
            $transientAwaitingLifecycle['lastTransitionReason']
                ?? null
        ) === (
            $transientPreReady['expectedLifecycleState'] ?? null
        )
        && (
            $transientAwaitingLifecycle[
                'kernelStartReconciledAt'
            ] ?? null
        ) === ($transientPreReady['expectedReconciledAt'] ?? null)
        && $transientErrorsAfter === $transientErrorsBefore,
    'Pre-ready transient Core unreadiness bypassed the durable barrier.'
);
$transientAwaitingState =
    $transientReadinessAccount->testSnapshotPersistentState();
$transientReadinessAccount->ApplyChanges();
assertLifecycle(
    $lifecycleOperations === []
        && array_sum($lifecycleConfigurationReadCounts) === 0
        && $transientReadinessAccount->testSnapshotPersistentState()
            === $transientAwaitingState,
    'Repeated pre-ready ApplyChanges changed the durable barrier.'
);

$lifecycleConfigurationReadFailures = [];
$lifecycleConfigurationReadCounts = [];
$transientReadinessAccount->MessageSink(
    1,
    0,
    IPS_KERNELSTARTED,
    []
);
assertLifecycle(
    $transientReadinessAccount->testTimerInterval(
        'MqttLifecycle'
    ) === (
        ($transientPostReady['reconciliationDelaySeconds'] ?? 0)
        * 1000
    )
        && $lifecycleOperations === [],
    'Transient Core readiness did not schedule post-ready reconciliation.'
);
$transientScheduledState =
    $transientReadinessAccount->testSnapshotPersistentState();
$transientReadinessAccount->MessageSink(
    2,
    0,
    IPS_KERNELSTARTED,
    []
);
assertLifecycle(
    $transientReadinessAccount->testSnapshotPersistentState()
        === $transientScheduledState
        && $transientReadinessAccount->testTimerInterval(
            'MqttLifecycle'
        ) === (
            ($transientPostReady['reconciliationDelaySeconds'] ?? 0)
            * 1000
        )
        && $lifecycleOperations === [],
    'Duplicate ready message changed transient reconciliation.'
);
$clock->advance(
    (int) ($transientPostReady['reconciliationDelaySeconds'] ?? 0)
);
$transientReadinessAccount->ProcessMqttLifecycle();
$transientReadyDiagnostics = decodeLifecycle(
    $transientReadinessAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $lifecycleOperations === []
        && (
            $transientReadyDiagnostics['lifecycle']['state'] ?? null
        ) === ($transientPostReady['expectedLifecycleState'] ?? null)
        && (
            $transientReadyDiagnostics['lifecycle'][
                'lastTransitionReason'
            ] ?? null
        ) === (
            $transientPostReady['expectedTransitionReason'] ?? null
        )
        && (
            $transientReadyDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === (
            $transientPostReady['expectedClassification'] ?? null
        )
        && (
            $transientReadyDiagnostics['statistics'][
                'coreResumeObservations'
            ] ?? null
        ) === (
            ($transientStatisticsBefore['coreResumeObservations'] ?? 0)
            + (
                $transientPostReady[
                    'expectedCoreResumeObservationDelta'
                ] ?? 0
            )
        )
        && (
            $transientReadyDiagnostics['statistics'][
                'connectionAttempts'
            ] ?? null
        ) === (
            ($transientStatisticsBefore['connectionAttempts'] ?? 0)
            + (
                $transientPostReady[
                    'expectedConnectionAttemptDelta'
                ] ?? 0
            )
        )
        && json_decode(
            (string) $transientReadinessAccount->testReadAttribute(
                'MqttErrorHistory'
            ),
            true,
            32,
            JSON_THROW_ON_ERROR
        ) === $transientErrorsBefore,
    'Post-ready transient Core recovery was not adopted exactly once.'
);
$lifecycleConfigurationReadFailures = [];
$lifecycleConfigurationReadCounts = [];

$clock->advance(1);
$applyFirstExpiredTokenAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$applyFirstExpiredTokenAccount->Create();
$applyFirstExpiredTokenAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$applyFirstExpiredTokenAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() - 1
);
$applyFirstExpiredTokenAccount->testSetKernelStartTime($clock->now());
$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleOperations = [];
$credentialCallsBeforeApplyFirstExpired = $credentialCalls;
$applyFirstExpiredTokenAccount->ApplyChanges();
$applyFirstExpiredStatus = $applyFirstExpiredTokenAccount->testStatus();
$applyFirstExpiredLifecycle = json_decode(
    (string) $applyFirstExpiredTokenAccount->testReadAttribute(
        'MqttLifecycleRegistry'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertLifecycle(
    $applyFirstExpiredStatus === IS_ACTIVE
        && $lifecycleOperations === []
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? null)
            === true
        && (
            $applyFirstExpiredLifecycle['lastTransitionReason']
                ?? null
        ) === 'kernel-start-awaiting-ready'
        && (
            $applyFirstExpiredLifecycle['kernelStartReconciledAt']
                ?? null
        ) === 0,
    'Apply-first expired token bypassed the durable barrier.'
);
$applyFirstExpiredTokenAccount->MessageSink(
    3,
    0,
    IPS_KERNELSTARTED,
    []
);
$clock->advance(15);
$applyFirstExpiredTokenAccount->ProcessMqttLifecycle();
$applyFirstExpiredDiagnostics = decodeLifecycle(
    $applyFirstExpiredTokenAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeApplyFirstExpired
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && (
            $applyFirstExpiredDiagnostics['lifecycle']['state']
                ?? null
        ) === 'WaitingForAuthentication'
        && (
            $applyFirstExpiredDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === 'authentication-unavailable',
    'Apply-first expired token was not classified after ready.'
);

$clock->advance(1);
$applyFirstInvalidConfigurationAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$applyFirstInvalidConfigurationAccount->Create();
$applyFirstInvalidConfigurationAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$applyFirstInvalidConfigurationAccount->testSetProperty(
    'ClientSecret',
    ''
);
$applyFirstInvalidConfigurationAccount->testSetKernelStartTime(
    $clock->now()
);
$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleOperations = [];
$applyFirstInvalidConfigurationAccount->ApplyChanges();
$applyFirstInvalidStatus =
    $applyFirstInvalidConfigurationAccount->testStatus();
$applyFirstInvalidLifecycle = json_decode(
    (string) $applyFirstInvalidConfigurationAccount->testReadAttribute(
        'MqttLifecycleRegistry'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertLifecycle(
    $applyFirstInvalidStatus === IS_ACTIVE
        && $lifecycleOperations === []
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? null)
            === true
        && (
            $applyFirstInvalidLifecycle['lastTransitionReason']
                ?? null
        ) === 'kernel-start-awaiting-ready',
    'Apply-first invalid configuration bypassed the durable barrier.'
);
$applyFirstInvalidConfigurationAccount->MessageSink(
    4,
    0,
    IPS_KERNELSTARTED,
    []
);
$clock->advance(15);
$applyFirstInvalidConfigurationAccount->ProcessMqttLifecycle();
$applyFirstInvalidDiagnostics = decodeLifecycle(
    $applyFirstInvalidConfigurationAccount->GetMqttDiagnostics()
);
assertLifecycle(
    ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && (
            $applyFirstInvalidDiagnostics['lifecycle']['state']
                ?? null
        ) === 'ConfigurationError'
        && (
            $applyFirstInvalidDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === 'configuration-invalid',
    'Apply-first invalid configuration was not classified after ready.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleOperations = [];

$clock->advance(1);
$applyFirstKernelStart = $clock->now();
$applyFirstAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$applyFirstAccount->Create();
$applyFirstAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$applyFirstAccount->testSetKernelStartTime($applyFirstKernelStart);
$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleOperations = [];
$applyFirstAccount->ApplyChanges();
$applyFirstStatus = $applyFirstAccount->testStatus();
$applyFirstAwaitingDiagnostics = decodeLifecycle(
    $applyFirstAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $applyFirstStatus === IS_ACTIVE
        && $lifecycleOperations === []
        && $credentialCalls === $credentialCallsBeforeNativeRestart
        && $applyFirstAccount->testTimerInterval('MqttLifecycle')
            === 0
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? null)
            === true
        && (
            $applyFirstAwaitingDiagnostics['lifecycle'][
                'lastTransitionReason'
            ] ?? null
        ) === 'kernel-start-awaiting-ready',
    'Apply-first kernel ordering did not await the ready message safely.'
);
$applyFirstAccount->MessageSink(1, 0, IPS_KERNELSTARTED, []);
$applyFirstScheduled = $applyFirstAccount->testSnapshotPersistentState();
assertLifecycle(
    $applyFirstAccount->testTimerInterval('MqttLifecycle')
            === 15000
        && $lifecycleOperations === [],
    'Ready message did not schedule apply-first reconciliation.'
);
$applyFirstAccount->MessageSink(2, 0, IPS_KERNELSTARTED, []);
assertLifecycle(
    $applyFirstAccount->testSnapshotPersistentState()
        === $applyFirstScheduled
        && $applyFirstAccount->testTimerInterval('MqttLifecycle')
            === 15000
        && $lifecycleOperations === [],
    'Duplicate ready message was not idempotent.'
);
$clock->advance(15);
$applyFirstAccount->ProcessMqttLifecycle();
$applyFirstDiagnostics = decodeLifecycle(
    $applyFirstAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeNativeRestart
        && $lifecycleOperations === []
        && (
            $applyFirstDiagnostics['lifecycle']['lastTransitionReason']
                ?? null
        ) === 'core-resumed'
        && (
            $applyFirstDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === 'healthy'
        && (
            $applyFirstDiagnostics['statistics'][
                'connectionAttempts'
            ] ?? null
        ) === 2
        && (
            $applyFirstDiagnostics['statistics'][
                'coreResumeObservations'
            ] ?? null
        ) === 1,
    'Apply-first kernel ordering did not adopt the healthy Core.'
);

$clock->advance(1);
$messageFirstKernelStart = $clock->now();
$messageFirstAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$messageFirstAccount->Create();
$messageFirstAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$messageFirstAccount->testSetKernelStartTime($messageFirstKernelStart);
$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleOperations = [];
$messageFirstAccount->MessageSink(2, 0, IPS_KERNELSTARTED, []);
$messageFirstAccount->ApplyChanges();
assertLifecycle(
    $lifecycleOperations === []
        && $credentialCalls === $credentialCallsBeforeNativeRestart
        && $messageFirstAccount->testTimerInterval('MqttLifecycle')
            === 15000
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? null)
            === true,
    'Message-first ApplyChanges overwrote pending kernel reconciliation.'
);
$clock->advance(15);
$messageFirstAccount->ProcessMqttLifecycle();
$messageFirstDiagnostics = decodeLifecycle(
    $messageFirstAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeNativeRestart
        && $lifecycleOperations === []
        && (
            $messageFirstDiagnostics['lifecycle'][
                'lastTransitionReason'
            ] ?? null
        ) === 'core-resumed'
        && (
            $messageFirstDiagnostics['statistics'][
                'coreResumeObservations'
            ] ?? null
        ) === 1,
    'Message-first kernel ordering did not adopt the healthy Core.'
);

$clock->advance(1);
$rotationKernelStart = $clock->now();
$rotationDuringKernelAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$rotationDuringKernelAccount->Create();
$rotationDuringKernelAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$rotationDuringKernelAccount->testSetKernelStartTime(
    $rotationKernelStart
);
$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleOperations = [];
$rotationDuringKernelAccount->MessageSink(
    3,
    0,
    IPS_KERNELSTARTED,
    []
);
assertLifecycle(
    $rotationDuringKernelAccount->RefreshAuthentication()
        === 'Token refresh succeeded.'
        && $lifecycleOperations === []
        && $credentialCalls === $credentialCallsBeforeNativeRestart
        && $rotationDuringKernelAccount->testTimerInterval(
            'MqttLifecycle'
        ) === 15000
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? null)
            === true,
    'Token rotation overwrote pending kernel reconciliation.'
);
$clock->advance(15);
$rotationDuringKernelAccount->ProcessMqttLifecycle();
$rotationKernelDiagnostics = decodeLifecycle(
    $rotationDuringKernelAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeNativeRestart
        && $lifecycleOperations === []
        && (
            $rotationKernelDiagnostics['lifecycle'][
                'lastTransitionReason'
            ] ?? null
        ) === 'core-resumed'
        && (
            $rotationKernelDiagnostics['statistics'][
                'coreResumeObservations'
            ] ?? null
        ) === 1,
    'Deferred token rotation prevented native Core adoption.'
);
$clock->advance(60);
$rotationDuringKernelAccount->ProcessMqttLifecycle();
$deferredRotationDiagnostics = decodeLifecycle(
    $rotationDuringKernelAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeNativeRestart
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && $rotationDuringKernelAccount->testTimerInterval(
            'MqttLifecycle'
        ) === 5000
        && (
            $deferredRotationDiagnostics['statistics'][
                'credentialRotations'
            ] ?? null
        ) === 2
        && (
            $deferredRotationDiagnostics['lifecycle'][
                'lastTransitionReason'
            ] ?? null
        ) === 'token-rotation',
    'Deferred token rotation was not resumed after Core classification.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$account = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$account->Create();
$account->testRestorePersistentState($persistentBeforeNativeRestart);
$clock->advance(1);
$account->testSetKernelStartTime($clock->now());
$lifecycleOperations = [];
$beforeIgnoredMessage = $account->testSnapshotPersistentState();
$account->MessageSink(1, 1, IPS_KERNELSTARTED, []);
$account->MessageSink(2, 0, 99999, []);
assertLifecycle(
    $account->testSnapshotPersistentState() === $beforeIgnoredMessage
        && $account->testTimerInterval('MqttLifecycle') === 0
        && $lifecycleOperations === [],
    'Unrelated messages changed the MQTT lifecycle.'
);
$account->MessageSink(3, 0, IPS_KERNELSTARTED, []);
$scheduledKernelState = $account->testSnapshotPersistentState();
assertLifecycle(
    $account->testTimerInterval('MqttLifecycle') === 15000
        && $credentialCalls === $credentialCallsBeforeNativeRestart
        && $lifecycleOperations === [],
    'Kernel start did not schedule a mutation-free reconciliation.'
);
$account->MessageSink(4, 0, IPS_KERNELSTARTED, []);
assertLifecycle(
    $account->testSnapshotPersistentState() === $scheduledKernelState
        && $account->testTimerInterval('MqttLifecycle') === 15000
        && $lifecycleOperations === [],
    'Duplicate kernel start scheduling was not idempotent.'
);
$clock->advance(15);
$account->ProcessMqttLifecycle();
$nativeResumeDiagnostics = decodeLifecycle(
    $account->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeNativeRestart
        && $lifecycleOperations === []
        && $account->testTimerInterval('MqttLifecycle') === 60000
        && (
            $nativeResumeDiagnostics['lifecycle'][
                'lastTransitionReason'
            ] ?? null
        ) === 'core-resumed'
        && (
            $nativeResumeDiagnostics['lifecycle'][
                'kernelStartObservedAt'
            ] ?? null
        ) === $clock->now() - 15
        && (
            $nativeResumeDiagnostics['lifecycle'][
                'kernelStartReconciledAt'
            ] ?? null
        ) === $clock->now()
        && (
            $nativeResumeDiagnostics['lifecycle']['kernelStartTime']
                ?? null
        ) === $clock->now() - 15
        && (
            $nativeResumeDiagnostics['statistics'][
                'coreResumeObservations'
            ] ?? null
        ) === 1
        && (
            $nativeResumeDiagnostics['statistics'][
                'connectionAttempts'
            ] ?? null
        ) === 2
        && $account->testSnapshotPersistentState()['variables']
            === $variablesBeforeNativeRestart,
    'Healthy native Core restart was not adopted without reconnect.'
);
$reconciledNativeState = $account->testSnapshotPersistentState();
$account->MessageSink(5, 0, IPS_KERNELSTARTED, []);
assertLifecycle(
    $account->testSnapshotPersistentState() === $reconciledNativeState
        && $account->testTimerInterval('MqttLifecycle') === 60000,
    'Reconciled kernel epoch was processed more than once.'
);

$variablesBeforeRestart =
    $account->testSnapshotPersistentState()['variables'];
$credentialCallsBeforeRestart = $credentialCalls;
$lifecycleOperations = [];
$account->ApplyChanges();
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeRestart
        && $account->testTimerInterval('MqttLifecycle') === 5000
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && $account->testSnapshotPersistentState()['variables']
            === $variablesBeforeRestart,
    'ApplyChanges did not produce a delayed credential-free restart.'
);
$account->ApplyChanges();
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeRestart
        && $account->testTimerInterval('MqttLifecycle') === 5000
        && $account->testSnapshotPersistentState()['variables']
            === $variablesBeforeRestart,
    'Repeated ApplyChanges was not restart-idempotent.'
);
$clock->advance(1);
$account->testSetKernelStartTime($clock->now());
$account->MessageSink(6, 0, IPS_KERNELSTARTED, []);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeRestart
        && $account->testTimerInterval('MqttLifecycle') === 15000,
    'Kernel start did not replace the pending configuration timer.'
);
$clock->advance(15);
$account->ProcessMqttLifecycle();
$credentialFreeKernelDiagnostics = decodeLifecycle(
    $account->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeRestart
        && $account->testTimerInterval('MqttLifecycle') === 5000
        && (
            $credentialFreeKernelDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === 'credential-free'
        && (
            $credentialFreeKernelDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 1,
    'Credential-free kernel reconciliation did not defer connection.'
);
$clock->advance(5);
$account->ProcessMqttLifecycle();
$kernelFallbackDiagnostics = decodeLifecycle(
    $account->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeRestart + 1
        && $account->testTimerInterval('MqttLifecycle') === 60000
        && (
            $kernelFallbackDiagnostics['statistics'][
                'lastConnectionTrigger'
            ] ?? null
        ) === 'kernel-fallback'
        && (
            $kernelFallbackDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 0
        && (
            $kernelFallbackDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ] ?? null
        ) === [],
    'Restart recovery did not perform exactly one delayed connection.'
);
$clock->advance(60);
$account->ProcessMqttLifecycle();

$lifecycleInstances[$mqttId]['InstanceStatus'] = 104;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 104;
$clock->advance(60);
$account->ProcessMqttLifecycle();
$disconnectDiagnostics = decodeLifecycle($account->GetMqttDiagnostics());
assertLifecycle(
    $account->testTimerInterval('MqttLifecycle') === 60000
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && (
            $disconnectDiagnostics['statistics'][
                'unexpectedDisconnects'
            ] ?? null
        ) === 1
        && (
            $disconnectDiagnostics['lifecycle']['lastTransitionReason']
                ?? null
        ) === 'core-disconnected',
    'Unexpected disconnect did not schedule the first bounded retry.'
);

$credentialFailureKind = 'transport';
$clock->advance(60);
$account->ProcessMqttLifecycle();
$retryOneDiagnostics = decodeLifecycle($account->GetMqttDiagnostics());
assertLifecycle(
    $account->testTimerInterval('MqttLifecycle') === 300000
        && ($retryOneDiagnostics['lifecycle']['reconnectAttempt'] ?? null)
            === 1,
    'First reconnect failure did not schedule the 300-second delay.'
);
$clock->advance(300);
$account->ProcessMqttLifecycle();
$retryTwoDiagnostics = decodeLifecycle($account->GetMqttDiagnostics());
assertLifecycle(
    $account->testTimerInterval('MqttLifecycle') === 900000
        && ($retryTwoDiagnostics['lifecycle']['reconnectAttempt'] ?? null)
            === 2,
    'Second reconnect failure did not schedule the 900-second delay.'
);
$clock->advance(900);
$account->ProcessMqttLifecycle();
$exhaustedDiagnostics = decodeLifecycle($account->GetMqttDiagnostics());
$credentialCallsAtExhaustion = $credentialCalls;
assertLifecycle(
    $account->testTimerInterval('MqttLifecycle') === 0
        && ($exhaustedDiagnostics['lifecycle']['state'] ?? null)
            === 'Disconnected'
        && (
            $exhaustedDiagnostics['lifecycle']['lastTransitionReason']
                ?? null
        ) === 'reconnect-exhausted'
        && (
            $exhaustedDiagnostics['statistics']['reconnectAttempts']
                ?? null
        ) === 3
        && (
            $exhaustedDiagnostics['statistics']['reconnectExhausted']
                ?? null
        ) === 1,
    'Reconnect sequence was not exhausted after exactly three attempts.'
);
$account->ProcessMqttLifecycle();
assertLifecycle(
    $credentialCalls === $credentialCallsAtExhaustion
        && $account->testTimerInterval('MqttLifecycle') === 0,
    'Exhausted reconnect sequence performed an unbounded fourth attempt.'
);
$account->ProcessMqttPilotClosure();
$exhaustedPilot = decodeLifecycle($account->GetMqttPilotDiagnostics());
assertLifecycle(
    ($exhaustedPilot['featureEnabled'] ?? null) === false
        && ($exhaustedPilot['active'] ?? null) === false
        && ($exhaustedPilot['closureState'] ?? null) === 'Closed'
        && ($exhaustedPilot['closureReason'] ?? null)
            === 'reconnect-exhausted'
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && $account->testTimerInterval('MqttPilotClosure') === 0,
    'Reconnect exhaustion did not complete automatic pilot closure.'
);
$account->testSetProperty('EnableMqttShadow', true);
$account->ApplyChanges();

$credentialFailureKind = 'authentication';
assertLifecycle(
    $account->ConnectMqttShadow()
        === 'MQTT connection attempt failed.'
        && $account->testTimerInterval('MqttLifecycle') === 0
        && (
            decodeLifecycle($account->GetMqttDiagnostics())[
                'lifecycle'
            ]['state'] ?? null
        ) === 'ReauthenticationRequired'
        && (
            decodeLifecycle($account->GetMqttPilotDiagnostics())[
                'closureReason'
            ] ?? null
        ) === 'terminal-authentication',
    'Authentication failure incorrectly entered transport retry.'
);
$account->ProcessMqttPilotClosure();
$account->testSetProperty('EnableMqttShadow', true);
$account->ApplyChanges();
$credentialFailureKind = 'configuration';
assertLifecycle(
    $account->ConnectMqttShadow()
        === 'MQTT connection attempt failed.'
        && $account->testTimerInterval('MqttLifecycle') === 0
        && (
            decodeLifecycle($account->GetMqttDiagnostics())[
                'lifecycle'
            ]['state'] ?? null
        ) === 'ConfigurationError'
        && (
            decodeLifecycle($account->GetMqttPilotDiagnostics())[
                'closureReason'
            ] ?? null
        ) === 'terminal-configuration',
    'Configuration failure incorrectly entered transport retry.'
);
$account->ProcessMqttPilotClosure();
$account->testSetProperty('EnableMqttShadow', true);
$account->ApplyChanges();

$postClosureLifecycle = decodeLifecycle(
    (string) $account->testReadAttribute('MqttLifecycleRegistry')
);
$postClosureLifecycle['reconnectAttempt'] = 3;
$account->testSetAttribute(
    'MqttLifecycleRegistry',
    json_encode($postClosureLifecycle, JSON_THROW_ON_ERROR)
);
$credentialFailureKind = '';
assertLifecycle(
    $account->ConnectMqttShadow()
        === 'MQTT connection attempt started.',
    'Recovery connection after bounded failures did not start.'
);
$clock->advance(60);
$account->ProcessMqttLifecycle();
assertLifecycle(
    (
        decodeLifecycle($account->GetMqttDiagnostics())[
            'lifecycle'
        ]['reconnectAttempt'] ?? null
    ) === 3,
    'Reconnect history reset before the stable-health window.'
);
$clock->advance(900);
$account->ProcessMqttLifecycle();
assertLifecycle(
    (
        decodeLifecycle($account->GetMqttDiagnostics())[
            'lifecycle'
        ]['reconnectAttempt'] ?? null
    ) === 0,
    'Reconnect history did not reset after 15 healthy minutes.'
);

$lifecycleOperations = [];
assertLifecycle(
    $account->DisconnectMqttShadow()
        === 'MQTT transport disconnected.',
    'Owned transport disconnect failed.'
);
assertLifecycle(
    ($lifecycleConfigurations[$webSocketId]['Active'] ?? true) === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && (
            decodeLifecycle(
                $account->GetMqttPilotDiagnostics()
            )['active'] ?? null
        ) === false
        && $account->testRegisteredMessages() === [[
            'senderId' => 0,
            'messageId' => IPS_KERNELSTARTED,
        ]]
        && $account->testTimerInterval('MqttPilotCheckpoint') === 0,
    'Disconnect did not deactivate and clear private credentials.'
);

$variablesBeforeDisable =
    $account->testSnapshotPersistentState()['variables'];
$account->testSetProperty('EnableMqttShadow', false);
$account->ApplyChanges();
$disabledClosureRequested = decodeLifecycle(
    $account->GetMqttPilotDiagnostics()
);
assertLifecycle(
    $account->testStatus() === IS_ACTIVE
        && $account->testTimerInterval('MqttLifecycle') === 0
        && $account->testTimerInterval('MqttReconcile') === 0
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && ($disabledClosureRequested['closureState'] ?? null)
            === 'ClosureRequested'
        && ($disabledClosureRequested['closureReason'] ?? null)
            === 'operator-disabled'
        && $account->testTimerInterval('MqttPilotClosure') === 1000
        && $account->testSnapshotPersistentState()['variables']
            === $variablesBeforeDisable,
    'Feature disable did not stop MQTT without public-variable churn.'
);
$account->ProcessMqttPilotClosure();
$disabledClosureCompleted = decodeLifecycle(
    $account->GetMqttPilotDiagnostics()
);
assertLifecycle(
    ($disabledClosureCompleted['closureState'] ?? null) === 'Closed'
        && ($disabledClosureCompleted['closureReason'] ?? null)
            === 'operator-disabled'
        && $account->testTimerInterval('MqttPilotClosure') === 0
        && $account->testSnapshotPersistentState()['variables']
            === $variablesBeforeDisable,
    'Feature disable did not complete owned pilot closure.'
);
$lifecycleOperations = [];
$account->ApplyChanges();
assertLifecycle(
    $account->testStatus() === IS_ACTIVE
        && $account->testTimerInterval('MqttLifecycle') === 0
        && $account->testSnapshotPersistentState()['variables']
            === $variablesBeforeDisable,
    'Repeated disabled ApplyChanges was not idempotent.'
);
$clock->advance(1);
$account->testSetKernelStartTime($clock->now());
$lifecycleOperations = [];
$credentialCallsBeforeDisabledRestart = $credentialCalls;
$account->MessageSink(7, 0, IPS_KERNELSTARTED, []);
assertLifecycle(
    $account->testTimerInterval('MqttLifecycle') === 15000
        && $lifecycleOperations === [],
    'Disabled kernel start did not remain mutation-free.'
);
$clock->advance(15);
$account->ProcessMqttLifecycle();
$disabledRestartDiagnostics = decodeLifecycle(
    $account->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeDisabledRestart
        && $lifecycleOperations === []
        && $account->testTimerInterval('MqttLifecycle') === 0
        && ($disabledRestartDiagnostics['lifecycle']['state'] ?? null)
            === 'Disabled'
        && (
            $disabledRestartDiagnostics['lifecycle'][
                'kernelStartReconciledAt'
            ] ?? null
        ) === $clock->now(),
    'Disabled restart reconciliation performed transport work.'
);
$account->testSetProperty('EnableMqttShadow', true);
$account->ApplyChanges();

$lifecycleOperations = [];
$lifecycleConfigurations[$webSocketId]['Type'] = 0;
assertLifecycle(
    $account->DisconnectMqttShadow()
        === 'MQTT ownership validation failed.'
        && $lifecycleOperations === [],
    'Ownership drift did not prevent Core mutation.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$authenticationRestartAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$authenticationRestartAccount->Create();
$authenticationRestartAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$authenticationRestartAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() - 1
);
$clock->advance(1);
$authenticationRestartAccount->testSetKernelStartTime($clock->now());
$credentialCallsBeforeAuthenticationRestart = $credentialCalls;
$lifecycleOperations = [];
$authenticationRestartAccount->MessageSink(
    8,
    0,
    IPS_KERNELSTARTED,
    []
);
$clock->advance(15);
$authenticationRestartAccount->ProcessMqttLifecycle();
$authenticationRestartDiagnostics = decodeLifecycle(
    $authenticationRestartAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeAuthenticationRestart
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && $authenticationRestartAccount->testTimerInterval(
            'MqttLifecycle'
        ) === 0
        && (
            $authenticationRestartDiagnostics['lifecycle']['state']
                ?? null
        ) === 'WaitingForAuthentication'
        && (
            $authenticationRestartDiagnostics['statistics'][
                'coreResumeObservations'
            ] ?? null
        ) === 0
        && (
            $authenticationRestartDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 0,
    'Authentication failure did not clean and suspend restart recovery.'
);

$boundedObservationFixture = json_decode(
    (string) file_get_contents(
        __DIR__
        . '/../fixtures/mqtt/core-resume-bounded-health-observation.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$boundedDelayed30 =
    $boundedObservationFixture['cases']['delayed30'] ?? null;
$boundedNeverReady =
    $boundedObservationFixture['cases']['neverReady'] ?? null;
assertLifecycle(
    is_array($boundedDelayed30) && is_array($boundedNeverReady),
    'Bounded Core-resume observation fixture is invalid.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleInstances[$mqttId]['InstanceStatus'] = 104;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 104;
$delayedRestartAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$delayedRestartAccount->Create();
$delayedRestartAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$delayedRestartAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 3600
);
$clock->advance(1);
$delayedKernelStart = $clock->now();
$delayedRestartAccount->testSetKernelStartTime($delayedKernelStart);
$credentialCallsBeforeDelayedRestart = $credentialCalls;
$lifecycleOperations = [];
$delayedRestartAccount->MessageSink(9, 0, IPS_KERNELSTARTED, []);
$clock->advance(15);
$delayedRestartAccount->ProcessMqttLifecycle();
$delayedPendingDiagnostics = decodeLifecycle(
    $delayedRestartAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeDelayedRestart
        && $lifecycleOperations === []
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? false)
            === true
        && $delayedRestartAccount->testTimerInterval(
            'MqttLifecycle'
        ) === 15000
        && (
            $delayedPendingDiagnostics['lifecycle']['state'] ?? null
        ) === 'CoreResumeObserving'
        && (
            $delayedPendingDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === 'pending-with-credentials',
    'Delayed Core readiness was cleaned at the first observation.'
);
$delayedPendingLifecycle = $delayedPendingDiagnostics['lifecycle'];
assertLifecycle(
    ($delayedPendingLifecycle['kernelCoreObservationCount'] ?? null)
        === 1
        && (
            $delayedPendingLifecycle['kernelCoreObservationDeadlineAt']
                ?? null
        ) === $delayedKernelStart + 180
        && (
            $delayedPendingLifecycle['lastKernelCoreFailedPredicates']
                ?? null
        ) === ['mqtt-status', 'websocket-status']
        && count(
            $delayedPendingLifecycle['kernelCoreObservations'] ?? []
        ) === 1
        && (
            $delayedPendingLifecycle['kernelCoreObservations'][0][
                'offsetSeconds'
            ] ?? null
        ) === 15
        && (
            $delayedPendingLifecycle['kernelCoreObservations'][0][
                'authorizationPresent'
            ] ?? null
        ) === true
        && (
            $delayedPendingLifecycle['kernelCoreObservations'][0][
                'mqttUsernamePresent'
            ] ?? null
        ) === true
        && (
            $delayedPendingLifecycle['kernelCoreObservations'][0][
                'mqttPasswordPresent'
            ] ?? null
        ) === true,
    'Pending Core-resume diagnostics are incomplete or unbounded.'
);
$delayedPendingEvidence = [
    $delayedPendingLifecycle['kernelCoreObservationCount'],
    $delayedPendingLifecycle['kernelCoreObservationDeadlineAt'],
    $delayedPendingLifecycle['kernelCoreObservations'],
];
$delayedRestartAccount->ProcessMqttLifecycle();
$delayedRestartAccount->MessageSink(
    $clock->now(),
    0,
    IPS_KERNELSTARTED,
    []
);
$delayedRestartAccount->ApplyChanges();
$delayedDuplicateDiagnostics = decodeLifecycle(
    $delayedRestartAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $lifecycleOperations === []
        && $delayedRestartAccount->testTimerInterval(
            'MqttLifecycle'
        ) === 15000
        && [
            $delayedDuplicateDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null,
            $delayedDuplicateDiagnostics['lifecycle'][
                'kernelCoreObservationDeadlineAt'
            ] ?? null,
            $delayedDuplicateDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ] ?? null,
        ] === $delayedPendingEvidence,
    'Duplicate timer, ready message or ApplyChanges reset pending evidence.'
);
$lifecycleInstances[$mqttId]['InstanceStatus'] = 102;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 102;
$clock->advance(15);
$delayedRestartAccount->ProcessMqttLifecycle();
$delayedReadyDiagnostics = decodeLifecycle(
    $delayedRestartAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeDelayedRestart
        && $lifecycleOperations === []
        && ($delayedReadyDiagnostics['lifecycle']['state'] ?? null)
            === ($boundedDelayed30['expectedState'] ?? null)
        && (
            $delayedReadyDiagnostics['lifecycle'][
                'lastTransitionReason'
            ] ?? null
        ) === ($boundedDelayed30['expectedReason'] ?? null)
        && (
            $delayedReadyDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === ($boundedDelayed30['expectedClassification'] ?? null)
        && (
            $delayedReadyDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 2
        && count(
            $delayedReadyDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ] ?? []
        ) === 2
        && (
            $delayedReadyDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ][1]['offsetSeconds'] ?? null
        ) === 30
        && (
            $delayedReadyDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ][1]['healthy'] ?? null
        ) === true
        && (
            $delayedReadyDiagnostics['statistics']['connectionAttempts']
                ?? null
        ) === $connectionAttemptsBeforeNativeRestart,
    'Delayed Core readiness was not adopted without Account reconnect.'
);

$boundedOffsets =
    $boundedObservationFixture['schedule']['absoluteOffsetsSeconds']
        ?? null;
assertLifecycle(
    $boundedOffsets === [15, 30, 60, 90, 120, 180],
    'Bounded Core-resume offsets are invalid.'
);
foreach ($boundedOffsets as $targetOffset) {
    $lifecycleConfigurations = $nativeActiveConfigurations;
    $lifecycleInstances = $nativeActiveInstances;
    $lifecycleInstances[$mqttId]['InstanceStatus'] = 104;
    $lifecycleInstances[$webSocketId]['InstanceStatus'] = 104;
    $offsetAdoptionAccount = new MqttLifecycleAccount(
        $accountId,
        $clock,
        $transport
    );
    $offsetAdoptionAccount->Create();
    $offsetAdoptionAccount->testRestorePersistentState(
        $persistentBeforeNativeRestart
    );
    $offsetAdoptionAccount->testSetAttribute(
        'TokenExpiresAtInternal',
        $clock->now() + 3600
    );
    $clock->advance(1);
    $offsetKernelStart = $clock->now();
    $offsetAdoptionAccount->testSetKernelStartTime($offsetKernelStart);
    $lifecycleOperations = [];
    $offsetAdoptionAccount->MessageSink(
        100 + $targetOffset,
        0,
        IPS_KERNELSTARTED,
        []
    );
    $offsetBaseline = decodeLifecycle(
        $offsetAdoptionAccount->GetMqttDiagnostics()
    );
    $previousOffset = 0;
    foreach ($boundedOffsets as $index => $offset) {
        if ($offset > $targetOffset) {
            break;
        }
        $clock->advance($offset - $previousOffset);
        if ($offset === $targetOffset) {
            $lifecycleInstances[$mqttId]['InstanceStatus'] = 102;
            $lifecycleInstances[$webSocketId]['InstanceStatus'] = 102;
        }
        $offsetAdoptionAccount->ProcessMqttLifecycle();
        $offsetDiagnostics = decodeLifecycle(
            $offsetAdoptionAccount->GetMqttDiagnostics()
        );
        $offsetLifecycle = $offsetDiagnostics['lifecycle'] ?? [];
        $offsetObservations =
            $offsetLifecycle['kernelCoreObservations'] ?? [];
        assertLifecycle(
            $lifecycleOperations === []
                && ($offsetLifecycle['kernelCoreObservationCount'] ?? null)
                    === $index + 1
                && count($offsetObservations) === $index + 1
                && (
                    $offsetObservations[$index]['offsetSeconds'] ?? null
                ) === $offset
                && (
                    $offsetLifecycle['kernelCoreObservationDeadlineAt']
                        ?? null
                ) === $offsetKernelStart + 180,
            sprintf(
                'Core-resume observation at +%d seconds is invalid.',
                $offset
            )
        );
        if ($offset < $targetOffset) {
            assertLifecycle(
                ($offsetLifecycle['state'] ?? null)
                    === 'CoreResumeObserving'
                    && (
                        $offsetObservations[$index]['healthy'] ?? null
                    ) === false,
                sprintf(
                    'Core was adopted before +%d seconds.',
                    $targetOffset
                )
            );
        } else {
            foreach (
                array_slice($offsetObservations, 0, -1) as $priorObservation
            ) {
                assertLifecycle(
                    ($priorObservation['healthy'] ?? null) === false,
                    'A prior unhealthy observation changed classification.'
                );
            }
            assertLifecycle(
                ($offsetLifecycle['state'] ?? null) === 'ShadowActive'
                    && (
                        $offsetLifecycle['lastTransitionReason'] ?? null
                    ) === 'core-resumed'
                    && (
                        $offsetLifecycle[
                            'lastKernelCoreClassification'
                        ] ?? null
                    ) === 'healthy'
                    && (
                        $offsetObservations[$index]['healthy'] ?? null
                    ) === true
                    && (
                        $offsetDiagnostics['statistics'][
                            'connectionAttempts'
                        ] ?? null
                    ) === (
                        $offsetBaseline['statistics'][
                            'connectionAttempts'
                        ] ?? null
                    )
                    && (
                        $offsetDiagnostics['statistics'][
                            'coreResumeObservations'
                        ] ?? null
                    ) === (
                        (
                            $offsetBaseline['statistics'][
                                'coreResumeObservations'
                            ] ?? 0
                        ) + 1
                    ),
                sprintf(
                    'Healthy Core was not adopted at +%d seconds.',
                    $targetOffset
                )
            );
            $offsetEvidence = [
                $offsetLifecycle['kernelCoreObservationCount'] ?? null,
                $offsetObservations,
                $offsetDiagnostics['statistics'][
                    'coreResumeObservations'
                ] ?? null,
            ];
            $clock->advance(1);
            $offsetAdoptionAccount->ProcessMqttLifecycle();
            $offsetAfterAdoption = decodeLifecycle(
                $offsetAdoptionAccount->GetMqttDiagnostics()
            );
            assertLifecycle(
                [
                    $offsetAfterAdoption['lifecycle'][
                        'kernelCoreObservationCount'
                    ] ?? null,
                    $offsetAfterAdoption['lifecycle'][
                        'kernelCoreObservations'
                    ] ?? null,
                    $offsetAfterAdoption['statistics'][
                        'coreResumeObservations'
                    ] ?? null,
                ] === $offsetEvidence,
                sprintf(
                    'Core adoption at +%d seconds was not idempotent.',
                    $targetOffset
                )
            );
        }
        $previousOffset = $offset;
    }
}

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleInstances[$mqttId]['InstanceStatus'] = 104;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 104;
$neverReadyAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$neverReadyAccount->Create();
$neverReadyAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$neverReadyAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 3600
);
$clock->advance(1);
$neverReadyKernelStart = $clock->now();
$neverReadyAccount->testSetKernelStartTime($neverReadyKernelStart);
$credentialCallsBeforeNeverReady = $credentialCalls;
$lifecycleOperations = [];
$neverReadyAccount->MessageSink(12, 0, IPS_KERNELSTARTED, []);
foreach ([15, 15, 30, 30, 30] as $index => $advance) {
    $clock->advance($advance);
    $neverReadyAccount->ProcessMqttLifecycle();
    $neverReadyPending = decodeLifecycle(
        $neverReadyAccount->GetMqttDiagnostics()
    );
    assertLifecycle(
        $credentialCalls === $credentialCallsBeforeNeverReady
            && $lifecycleOperations === []
            && ($lifecycleConfigurations[$webSocketId]['Active'] ?? false)
                === true
            && ($neverReadyPending['lifecycle']['state'] ?? null)
                === ($boundedNeverReady['expectedPendingState'] ?? null)
            && (
                $neverReadyPending['lifecycle'][
                    'kernelCoreObservationCount'
                ] ?? null
            ) === $index + 1
            && $neverReadyAccount->testTimerInterval(
                'MqttLifecycle'
            ) === [15000, 30000, 30000, 30000, 60000][$index],
        'Never-ready Core mutated before the observation deadline.'
    );
}
$clock->advance(60);
$neverReadyAccount->ProcessMqttLifecycle();
$neverReadyDiagnostics = decodeLifecycle(
    $neverReadyAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeNeverReady
        && count($lifecycleOperations)
            === ($boundedNeverReady['expectedFinalCoreMutationCount'] ?? null)
        && ($lifecycleConfigurations[$webSocketId]['Active'] ?? true)
            === false
        && ($lifecycleConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($lifecycleConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($lifecycleConfigurations[$mqttId]['Password'] ?? null) === ''
        && $neverReadyAccount->testTimerInterval(
            'MqttLifecycle'
        ) === 60000
        && ($neverReadyDiagnostics['lifecycle']['state'] ?? null)
            === ($boundedNeverReady['expectedFinalState'] ?? null)
        && (
            $neverReadyDiagnostics['lifecycle']['lastTransitionReason']
                ?? null
        ) === ($boundedNeverReady['expectedFinalReason'] ?? null)
        && (
            $neverReadyDiagnostics['statistics']['unexpectedDisconnects']
                ?? null
        ) === ($boundedNeverReady['expectedUnexpectedDisconnectDelta'] ?? null)
        && (
            $neverReadyDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 6
        && (
            $neverReadyDiagnostics['lifecycle'][
                'kernelCoreObservationDeadlineAt'
            ] ?? null
        ) === $neverReadyKernelStart + 180
        && (
            $neverReadyDiagnostics['lifecycle'][
                'lastKernelCoreFailedPredicates'
            ] ?? null
        ) === ['mqtt-status', 'websocket-status']
        && count(
            $neverReadyDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ] ?? []
        ) === 6,
    'Never-ready Core did not enter one bounded final recovery.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleInstances[$mqttId]['InstanceStatus'] = 104;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 104;
$lateTimerAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$lateTimerAccount->Create();
$lateTimerAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$lateTimerAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 3600
);
$clock->advance(1);
$lateTimerKernelStart = $clock->now();
$lateTimerAccount->testSetKernelStartTime($lateTimerKernelStart);
$lifecycleOperations = [];
$lateTimerAccount->MessageSink(13, 0, IPS_KERNELSTARTED, []);
$clock->advance(20);
assertLifecycle(
    $lateTimerAccount->IngestMqttEnvelope($receiverId, $stateEnvelope)
        === 'accepted'
        && $lifecycleOperations === [],
    'Synthetic ingress during Core observation touched Core configuration.'
);
$clock->advance(27);
$lateTimerAccount->ProcessMqttLifecycle();
$lateTimerPending = decodeLifecycle(
    $lateTimerAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $lifecycleOperations === []
        && $lateTimerAccount->testTimerInterval('MqttLifecycle')
            === 13000
        && ($lateTimerPending['lifecycle']['state'] ?? null)
            === 'CoreResumeObserving'
        && (
            $lateTimerPending['lifecycle']['kernelCoreObservationCount']
                ?? null
        ) === 1
        && (
            $lateTimerPending['lifecycle']['kernelCoreObservations'][0][
                'offsetSeconds'
            ] ?? null
        ) === 47
        && (
            $lateTimerPending['lifecycle']['kernelCoreObservations'][0][
                'lastReceivedAt'
            ] ?? null
        ) === $lateTimerKernelStart + 20
        && (
            $lateTimerPending['statistics']['connectionAttempts'] ?? null
        ) === $connectionAttemptsBeforeNativeRestart,
    'Late timer replayed offsets or ingress replaced strict Core health.'
);
$lifecycleInstances[$mqttId]['InstanceStatus'] = 102;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 102;
$clock->advance(13);
$lateTimerAccount->ProcessMqttLifecycle();
$lateTimerReady = decodeLifecycle(
    $lateTimerAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $lifecycleOperations === []
        && ($lateTimerReady['lifecycle']['state'] ?? null)
            === 'ShadowActive'
        && (
            $lateTimerReady['lifecycle']['kernelCoreObservationCount']
                ?? null
        ) === 2
        && (
            $lateTimerReady['lifecycle']['kernelCoreObservations'][1][
                'offsetSeconds'
            ] ?? null
        ) === 60,
    'Late timer did not preserve absolute next-offset adoption.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleInstances[$mqttId]['InstanceStatus'] = 104;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 104;
$pastDeadlineAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$pastDeadlineAccount->Create();
$pastDeadlineAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$pastDeadlineAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 3600
);
$clock->advance(1);
$pastDeadlineAccount->testSetKernelStartTime($clock->now());
$lifecycleOperations = [];
$pastDeadlineAccount->MessageSink(14, 0, IPS_KERNELSTARTED, []);
$clock->advance(186);
$pastDeadlineAccount->ProcessMqttLifecycle();
$pastDeadlineDiagnostics = decodeLifecycle(
    $pastDeadlineAccount->GetMqttDiagnostics()
);
assertLifecycle(
    count($lifecycleOperations) === 7
        && $pastDeadlineAccount->testTimerInterval('MqttLifecycle')
            === 60000
        && ($pastDeadlineDiagnostics['lifecycle']['state'] ?? null)
            === 'ReconnectScheduled'
        && (
            $pastDeadlineDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 1
        && (
            $pastDeadlineDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ][0]['offsetSeconds'] ?? null
        ) === 186,
    'Past-deadline execution replayed missed observations.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$lifecycleInstances[$mqttId]['InstanceStatus'] = 104;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 104;
$newEpochAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$newEpochAccount->Create();
$newEpochAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$newEpochAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 3600
);
$clock->advance(1);
$newEpochAccount->testSetKernelStartTime($clock->now());
$lifecycleOperations = [];
$newEpochAccount->MessageSink(15, 0, IPS_KERNELSTARTED, []);
$clock->advance(15);
$newEpochAccount->ProcessMqttLifecycle();
$oldEpochDiagnostics = decodeLifecycle(
    $newEpochAccount->GetMqttDiagnostics()
);
$oldEpochDeadline =
    $oldEpochDiagnostics['lifecycle']['kernelCoreObservationDeadlineAt']
        ?? 0;
$clock->advance(1);
$newEpochStart = $clock->now();
$newEpochAccount->testSetKernelStartTime($newEpochStart);
$newEpochAccount->MessageSink(16, 0, IPS_KERNELSTARTED, []);
$newEpochPending = decodeLifecycle(
    $newEpochAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $lifecycleOperations === []
        && $newEpochAccount->testTimerInterval('MqttLifecycle')
            === 15000
        && (
            $newEpochPending['lifecycle']['kernelCoreObservationCount']
                ?? null
        ) === 0
        && (
            $newEpochPending['lifecycle'][
                'kernelCoreObservationDeadlineAt'
            ] ?? null
        ) === $newEpochStart + 180
        && $oldEpochDeadline !== $newEpochStart + 180,
    'New kernel epoch retained the stale observation window.'
);
$lifecycleInstances[$mqttId]['InstanceStatus'] = 102;
$lifecycleInstances[$webSocketId]['InstanceStatus'] = 102;
$clock->advance(15);
$newEpochAccount->ProcessMqttLifecycle();
$newEpochReady = decodeLifecycle(
    $newEpochAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $lifecycleOperations === []
        && ($newEpochReady['lifecycle']['state'] ?? null)
            === 'ShadowActive'
        && (
            $newEpochReady['lifecycle']['kernelCoreObservationCount']
                ?? null
        ) === 1
        && (
            $newEpochReady['lifecycle']['kernelCoreObservations'][0][
                'offsetSeconds'
            ] ?? null
        ) === 15,
    'New kernel epoch did not reconcile independently.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$ownershipDriftRestartAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$ownershipDriftRestartAccount->Create();
$ownershipDriftRestartAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$ownershipDriftRestartAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() - 1
);
$lifecycleConfigurations[$webSocketId]['Type'] = 0;
$clock->advance(1);
$ownershipDriftRestartAccount->testSetKernelStartTime($clock->now());
$credentialCallsBeforeOwnershipDrift = $credentialCalls;
$lifecycleOperations = [];
$ownershipDriftRestartAccount->MessageSink(
    10,
    0,
    IPS_KERNELSTARTED,
    []
);
$clock->advance(15);
$ownershipDriftRestartAccount->ProcessMqttLifecycle();
$ownershipDriftRestartDiagnostics = decodeLifecycle(
    $ownershipDriftRestartAccount->GetMqttDiagnostics()
);
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeOwnershipDrift
        && $lifecycleOperations === []
        && $ownershipDriftRestartAccount->testTimerInterval(
            'MqttLifecycle'
        ) === 0
        && (
            $ownershipDriftRestartDiagnostics['lifecycle']['state']
                ?? null
        ) === 'ConfigurationError'
        && (
            $ownershipDriftRestartDiagnostics['lifecycle'][
                'kernelStartReconciledAt'
            ] ?? null
        ) === $clock->now()
        && (
            $ownershipDriftRestartDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === 'ownership-invalid'
        && (
            $ownershipDriftRestartDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 0,
    'Auth plus ownership drift was retried or mutated during restart recovery.'
);

$lifecycleConfigurations = $nativeActiveConfigurations;
$lifecycleInstances = $nativeActiveInstances;
$disableWinsAccount = new MqttLifecycleAccount(
    $accountId,
    $clock,
    $transport
);
$disableWinsAccount->Create();
$disableWinsAccount->testRestorePersistentState(
    $persistentBeforeNativeRestart
);
$disableWinsAccount->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 3600
);
$clock->advance(1);
$disableWinsAccount->testSetKernelStartTime($clock->now());
$disableWinsAccount->MessageSink(11, 0, IPS_KERNELSTARTED, []);
$disableWinsAccount->testSetProperty('EnableMqttShadow', false);
$disableWinsAccount->ApplyChanges();
$disableWinsAccount->ProcessMqttPilotClosure();
$disableWinsClosure = decodeLifecycle(
    $disableWinsAccount->GetMqttPilotDiagnostics()
);
assertLifecycle(
    ($disableWinsClosure['closureState'] ?? null) === 'Closed'
        && ($disableWinsClosure['closureReason'] ?? null)
            === 'operator-disabled',
    'Explicit disable did not complete before kernel reconciliation.'
);
$credentialCallsBeforeDisableWins = $credentialCalls;
$lifecycleOperations = [];
$clock->advance(15);
$disableWinsAccount->ProcessMqttLifecycle();
assertLifecycle(
    $credentialCalls === $credentialCallsBeforeDisableWins
        && $lifecycleOperations === []
        && $disableWinsAccount->testTimerInterval('MqttLifecycle') === 0
        && (
            decodeLifecycle($disableWinsAccount->GetMqttDiagnostics())[
                'lifecycle'
            ]['state'] ?? null
        ) === 'Disabled'
        && (
            decodeLifecycle($disableWinsAccount->GetMqttDiagnostics())[
                'lifecycle'
            ]['kernelCoreObservationCount'] ?? null
        ) === 0
        && (
            decodeLifecycle($disableWinsAccount->GetMqttDiagnostics())[
                'lifecycle'
            ]['kernelCoreObservationDeadlineAt'] ?? null
        ) === 0,
    'Explicit disable did not cancel pending kernel reconciliation.'
);

$malformedAccount = new MqttLifecycleAccount(
    4102,
    $clock,
    $transport
);
$malformedAccount->Create();
$malformedAccount->ApplyChanges();
$malformedAccount->testSetAttribute(
    'MqttLifecycleRegistry',
    json_encode([
        'state' => 'SECRET_DEVICE_001',
        'stateChangedAt' => -1,
        'lastResult' => '/downlink/vehicle/SECRET',
        'lastResultAt' => '1700000000',
        'lastCoreStatus' => -102,
        'observedAt' => null,
        'kernelStartObservedAt' => -1,
        'kernelStartReconciledAt' => '1700000000',
        'kernelStartTime' => null,
        'lastKernelCoreClassification' => 'SECRET_CLASSIFICATION',
        'lastKernelCoreClassificationAt' => '1700000000',
        'kernelCoreObservationCount' => '999',
        'kernelCoreObservationDeadlineAt' => '1700000090',
        'lastKernelCoreFailedPredicates' => [
            'mqtt-status',
            'SECRET_PREDICATE',
            'websocket-status',
        ],
        'kernelCoreObservations' => array_fill(0, 10, [
            'ordinal' => '1',
            'observedAt' => '1700000000',
            'offsetSeconds' => -1,
            'mqttStatus' => '102',
            'webSocketStatus' => 104,
            'webSocketActive' => true,
            'authorizationPresent' => true,
            'mqttUsernamePresent' => true,
            'mqttPasswordPresent' => true,
            'lastReceivedAt' => '1700000000',
            'healthy' => false,
            'credential' => 'Bearer SECRET',
            'topic' => '/downlink/vehicle/SECRET',
        ]),
    ], JSON_THROW_ON_ERROR)
);
$malformedAccount->testSetAttribute(
    'MqttStatistics',
    json_encode([
        'connectionAttempts' => -1,
        'coreResumeObservations' => '999',
        'received' => '42',
        'accepted' => PHP_INT_MAX,
        'rejected' => null,
        'lastConnectionTrigger' => 'Bearer SECRET',
        'lastConnectionTriggerAt' => -1,
        'lastReconciliationResult' => 'Bearer SECRET',
        'lastComparisonResult' => 'wss://secret.invalid/path',
    ], JSON_THROW_ON_ERROR)
);
$malformedAccount->testSetAttribute(
    'MqttErrorHistory',
    json_encode(array_fill(0, 40, [
        'reason' => 'SECRET_ERROR',
        'at' => 1700000000,
        'payload' => 'SYNTHETIC_MQTT_PASSWORD',
    ]), JSON_THROW_ON_ERROR)
);
$malformedAccount->testSetAttribute(
    'MqttShadowState',
    json_encode([
        'devices' => array_fill(0, 100, [
            'topic' => '/downlink/vehicle/SECRET',
        ]),
    ], JSON_THROW_ON_ERROR)
);
$malformedAccount->testSetAttribute(
    'MqttPendingReconciliation',
    json_encode([
        'entries' => array_fill(0, 100, [
            'deviceId' => 'SECRET_DEVICE_001',
        ]),
    ], JSON_THROW_ON_ERROR)
);
$malformedBeforeDiagnostics =
    $malformedAccount->testSnapshotPersistentState();
$malformedDiagnosticsJson =
    $malformedAccount->GetMqttDiagnostics();
$malformedDiagnostics = decodeLifecycle($malformedDiagnosticsJson);
assertLifecycle(
    ($malformedDiagnostics['featureEnabled'] ?? null) === false
        && ($malformedDiagnostics['configurationStatus'] ?? null)
            === 'disabled'
        && ($malformedDiagnostics['lifecycle']['state'] ?? null)
            === 'unknown'
        && ($malformedDiagnostics['lifecycle']['stateChangedAt'] ?? null)
            === 0
        && (
            $malformedDiagnostics['lifecycle'][
                'kernelStartObservedAt'
            ] ?? null
        ) === 0
        && (
            $malformedDiagnostics['lifecycle'][
                'kernelStartReconciledAt'
            ] ?? null
        ) === 0
        && (
            $malformedDiagnostics['lifecycle']['kernelStartTime']
                ?? null
        ) === 0
        && (
            $malformedDiagnostics['lifecycle'][
                'lastKernelCoreClassification'
            ] ?? null
        ) === 'unknown'
        && (
            $malformedDiagnostics['lifecycle'][
                'lastKernelCoreClassificationAt'
            ] ?? null
        ) === 0
        && (
            $malformedDiagnostics['lifecycle'][
                'kernelCoreObservationCount'
            ] ?? null
        ) === 0
        && (
            $malformedDiagnostics['lifecycle'][
                'kernelCoreObservationDeadlineAt'
            ] ?? null
        ) === 0
        && (
            $malformedDiagnostics['lifecycle'][
                'lastKernelCoreFailedPredicates'
            ] ?? null
        ) === ['mqtt-status', 'websocket-status']
        && count(
            $malformedDiagnostics['lifecycle'][
                'kernelCoreObservations'
            ] ?? []
        ) === 6
        && ($malformedDiagnostics['lifecycle']['lastResult'] ?? null)
            === 'unknown'
        && (
            $malformedDiagnostics['statistics']['connectionAttempts']
                ?? null
        ) === 0
        && (
            $malformedDiagnostics['statistics'][
                'coreResumeObservations'
            ] ?? null
        ) === 0
        && ($malformedDiagnostics['statistics']['received'] ?? null) === 0
        && ($malformedDiagnostics['statistics']['accepted'] ?? null)
            === PHP_INT_MAX
        && (
            $malformedDiagnostics['statistics']['lastConnectionTrigger']
                ?? null
        ) === 'unknown'
        && (
            $malformedDiagnostics['statistics'][
                'lastConnectionTriggerAt'
            ] ?? null
        ) === 0
        && (
            $malformedDiagnostics['statistics'][
                'lastReconciliationResult'
            ] ?? null
        ) === 'unknown'
        && (
            $malformedDiagnostics['statistics']['lastComparisonResult']
                ?? null
        ) === 'unknown'
        && ($malformedDiagnostics['errors']['count'] ?? null) === 0
        && ($malformedDiagnostics['errors']['latestReason'] ?? null)
            === 'none'
        && ($malformedDiagnostics['shadow']['trackedDeviceCount'] ?? null)
            === 64
        && (
            $malformedDiagnostics['shadow']['pendingReconciliationCount']
                ?? null
        ) === 64
        && (
            $malformedDiagnostics['shadow']['observation']['status']
                ?? null
        ) === 'invalid'
        && strlen($malformedDiagnosticsJson) < 4096
        && !str_contains($malformedDiagnosticsJson, 'SECRET')
        && !str_contains($malformedDiagnosticsJson, '/downlink/')
        && !str_contains($malformedDiagnosticsJson, 'Bearer ')
        && !str_contains($malformedDiagnosticsJson, 'wss://')
        && $malformedAccount->testSnapshotPersistentState()
            === $malformedBeforeDiagnostics,
    'Malformed MQTT diagnostics were not normalized and redacted.'
);

$source = (string) file_get_contents(
    __DIR__ . '/../distribution/NavimowAccount/module.php'
);
assertLifecycle(
    !str_contains($source, 'IPS_CreateInstance')
        && !str_contains($source, 'IPS_DeleteInstance')
        && !str_contains($source, 'MC_ReloadModule')
        && str_contains($source, 'public function GetMqttDiagnostics')
        && str_contains($source, 'public function MessageSink')
        && str_contains($source, "'kernel-reconcile'")
        && str_contains($source, 'decodeMqttDiagnosticAttribute'),
    'Lifecycle increment introduced forbidden automatic Core operations.'
);

echo "Navimow MQTT transport lifecycle checks passed.\n";
