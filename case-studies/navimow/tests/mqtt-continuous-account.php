<?php

declare(strict_types=1);

$continuousInstances = [];
$continuousProperties = [];
$continuousConfigurations = [];
$continuousOperations = [];

function IPS_InstanceExists(int $instanceId): bool
{
    global $continuousInstances;
    return isset($continuousInstances[$instanceId]);
}

function IPS_GetInstance(int $instanceId): array
{
    global $continuousInstances;
    return $continuousInstances[$instanceId] ?? [];
}

function IPS_GetProperty(int $instanceId, string $name): mixed
{
    global $continuousProperties;
    return $continuousProperties[$instanceId][$name] ?? null;
}

function IPS_GetConfiguration(int $instanceId): string
{
    global $continuousConfigurations;
    return json_encode(
        $continuousConfigurations[$instanceId] ?? [],
        JSON_THROW_ON_ERROR
    );
}

function IPS_SetProperty(
    int $instanceId,
    string $name,
    mixed $value
): void {
    global $continuousConfigurations, $continuousOperations;
    $continuousConfigurations[$instanceId][$name] = $value;
    $continuousOperations[] = [
        'operation' => 'set',
        'instanceId' => $instanceId,
        'name' => $name,
        'value' => $value,
    ];
}

function IPS_ApplyChanges(int $instanceId): void
{
    global $continuousInstances, $continuousOperations;
    $continuousOperations[] = [
        'operation' => 'apply',
        'instanceId' => $instanceId,
    ];
    $continuousInstances[$instanceId]['InstanceStatus'] = 102;
}

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/harness/FakeClock.php';
require_once __DIR__
    . '/../../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../distribution/NavimowAccount/module.php';

final class ContinuousMqttAccount extends NavimowAccount
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

    protected function currentKernelStartTime(): int
    {
        return 0;
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

function assertContinuousAccount(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function continuousAccountJson(string $encoded): array
{
    $decoded = json_decode($encoded, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected continuous account JSON.');
    }
    return $decoded;
}

function continuousSubscriptions(string $deviceId): string
{
    return json_encode(array_map(
        static fn (string $channel): array => [
            'Topic' => sprintf(
                '/downlink/vehicle/%s/realtimeDate/%s',
                $deviceId,
                $channel
            ),
            'QoS' => 0,
        ],
        ['attributes', 'event', 'location', 'state']
    ), JSON_THROW_ON_ERROR);
}

$accountId = 7101;
$receiverId = 7201;
$mqttId = 7202;
$webSocketId = 7203;
$clock = new NavimowTestFakeClock(1700000000);
$credentialCalls = 0;
$transport = static function (array $request) use (&$credentialCalls): array {
    if (!str_contains($request['url'], '/mqtt/userInfo/get/v2')) {
        throw new RuntimeException('Unexpected continuous transport call.');
    }
    $credentialCalls++;
    return [
        'status' => 200,
        'body' => (string) file_get_contents(
            __DIR__ . '/../fixtures/mqtt/mqtt-credential-success.json'
        ),
    ];
};

$continuousInstances = [
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
$continuousProperties[$receiverId] = [
    'AccountInstanceId' => $accountId,
];
$continuousConfigurations[$mqttId] = [
    'UserName' => '',
    'Password' => '',
    'ClientID' => '',
    'KeepAliveInterval' => 60,
    'Subscriptions' => continuousSubscriptions('DEVICE_001'),
];
$continuousConfigurations[$webSocketId] = [
    'URL' => '',
    'Headers' => '[]',
    'Type' => 1,
    'VerifyCertificate' => true,
    'Active' => false,
];

$account = new ContinuousMqttAccount($accountId, $clock, $transport);
$account->Create();
$account->ApplyChanges();
$definitions = $account->testVariableDefinitions();
assertContinuousAccount(
    ($definitions['MqttOperatingState']['position'] ?? null) === 70
        && ($definitions['MqttLastMessageAt']['position'] ?? null) === 80
        && ($definitions['MqttLastPositionAt']['position'] ?? null) === 90
        && ($definitions['MqttPositionFreshness']['position'] ?? null) === 100
        && ($definitions['MqttLeaseExpiresAt']['position'] ?? null) === 110
        && $account->testReadVariable('MqttOperatingState') === 0,
    'Continuous status variables are not stable and additive.'
);

$account->testSetProperty('ClientSecret', 'SYNTHETIC_CLIENT_SECRET');
$account->testSetProperty('EnableMqttShadow', true);
$account->testSetProperty('EnableMqttPositionDiagnostics', true);
$account->testSetProperty('MqttReceiverInstanceId', $receiverId);
$account->testSetAttribute('AccessToken', 'SYNTHETIC_ACCESS_TOKEN');
$account->testSetAttribute('RefreshToken', 'SYNTHETIC_REFRESH_TOKEN');
$account->testSetAttribute('TokenExpiresAtInternal', $clock->now() + 1000000);
$account->testSetAttribute(
    'DiscoveryCache',
    json_encode([['id' => 'DEVICE_001']], JSON_THROW_ON_ERROR)
);
$account->ApplyChanges();
assertContinuousAccount(
    $account->AdoptMqttShadowChain() === 'MQTT chain adopted.',
    'Continuous test topology could not be adopted.'
);

$account->testSetProperty('MqttOperatingMode', 2);
$account->ApplyChanges();
$starting = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($starting['formatVersion'] ?? null) === 3
        && ($starting['operation']['effectiveMode'] ?? null)
            === 'ContinuousReceiveOnly'
        && ($starting['operation']['state'] ?? null) === 'Starting'
        && ($starting['operation']['leaseExpiresAt'] ?? null)
            === $clock->now() + 259200
        && $account->testTimerInterval('MqttLifecycle') === 5000
        && $account->testTimerInterval('MqttPilotDeadline') === 0
        && $account->testReadVariable('MqttOperatingState') === 1,
    'Continuous mode did not schedule exactly one leased start.'
);
assertContinuousAccount(
    $account->ConnectMqttShadow()
        === 'Use Resume Continuous MQTT in continuous mode.'
        && $credentialCalls === 0,
    'Manual pilot connect was accepted in continuous mode.'
);

$clock->advance(5);
$account->ProcessMqttLifecycle();
assertContinuousAccount(
    $credentialCalls === 1
        && ($continuousConfigurations[$webSocketId]['Active'] ?? false),
    'Continuous initial connection was not executed once.'
);
$continuousInstances[$mqttId]['InstanceStatus'] = 102;
$continuousInstances[$webSocketId]['InstanceStatus'] = 102;
$clock->advance(60);
$account->ProcessMqttLifecycle();
$active = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($active['operation']['state'] ?? null) === 'Active'
        && ($active['statistics']['continuousStarts'] ?? null) === 1
        && $account->testReadVariable('MqttOperatingState') === 2,
    'Continuous operation did not become active.'
);
$activeLeaseExpiresAt = $active['operation']['leaseExpiresAt'];
$activeCredentialCalls = $credentialCalls;
$account->ApplyChanges();
$activeAfterApply = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($activeAfterApply['operation']['state'] ?? null) === 'Active'
        && ($activeAfterApply['operation']['leaseExpiresAt'] ?? null)
            === $activeLeaseExpiresAt
        && $credentialCalls === $activeCredentialCalls
        && $account->testTimerInterval('MqttContinuousLease') > 0,
    'ApplyChanges did not preserve an eligible active lease.'
);
$positionEnvelope = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../fixtures/mqtt/symcon-envelope-location.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
)['envelope'];
$positionEnvelope['Payload'] = json_encode([[
    'postureTheta' => '0.5',
    'postureX' => '12.5',
    'postureY' => '-8.25',
    'time' => 1700000002000,
    'type' => 1,
    'vehicleState' => 4,
]], JSON_THROW_ON_ERROR);
$acceptedPositionAt = $clock->now();
assertContinuousAccount(
    $account->IngestMqttEnvelope(
        $receiverId,
        json_encode($positionEnvelope, JSON_THROW_ON_ERROR)
    ) === 'accepted'
        && $account->testReadVariable('MqttLastMessageAt')
            === $acceptedPositionAt
        && $account->testReadVariable('MqttLastPositionAt')
            === $acceptedPositionAt
        && $account->testReadVariable('MqttPositionFreshness') === 1,
    'Accepted position did not project Fresh timestamps.'
);
$clock->advance(120);
$account->ProcessMqttLifecycle();
assertContinuousAccount(
    $account->testReadVariable('MqttPositionFreshness') === 1,
    'The exact 120-second position boundary is not Fresh.'
);
$clock->advance(1);
$account->ProcessMqttLifecycle();
assertContinuousAccount(
    $account->testReadVariable('MqttPositionFreshness') === 2,
    'Position age 121 seconds is not Delayed.'
);
$clock->advance(480);
$account->ProcessMqttLifecycle();
assertContinuousAccount(
    $account->testReadVariable('MqttPositionFreshness') === 3,
    'Position age 601 seconds is not Stale.'
);

$leaseEligibleAt = $active['operation']['renewalEligibleAt'];
$clock->advance($leaseEligibleAt - $clock->now());
$account->testSetVariable('LastRestSuccess', $clock->now());
$account->ProcessMqttContinuousLease();
$renewed = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($renewed['operation']['renewalCount'] ?? null) === 1
        && ($renewed['operation']['leaseExpiresAt'] ?? null)
            === $clock->now() + 259200
        && ($renewed['statistics']['continuousLeaseRenewals'] ?? null) === 1,
    'Healthy continuous lease was not renewed without reconnect.'
);

$continuousInstances[$mqttId]['InstanceStatus'] = 104;
$continuousInstances[$webSocketId]['InstanceStatus'] = 104;
$account->ProcessMqttLifecycle();
foreach ([60, 300, 900] as $delay) {
    $clock->advance($delay);
    $account->ProcessMqttLifecycle();
    $continuousInstances[$mqttId]['InstanceStatus'] = 104;
    $continuousInstances[$webSocketId]['InstanceStatus'] = 104;
    $clock->advance(60);
    $account->ProcessMqttLifecycle();
}
$circuit = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($circuit['operation']['state'] ?? null) === 'CircuitOpen'
        && ($circuit['operation']['circuitReason'] ?? null)
            === 'inner-reconnect-exhausted'
        && ($circuit['statistics']['continuousCircuitOpenings'] ?? null) === 1
        && ($continuousConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && ($continuousConfigurations[$mqttId]['UserName'] ?? null) === ''
        && ($continuousConfigurations[$mqttId]['Password'] ?? null) === '',
    'Reconnect exhaustion did not open a credential-free circuit.'
);
$circuitNextProbeAt = $circuit['operation']['nextProbeAt'];
$circuitCredentialCalls = $credentialCalls;
$account->ApplyChanges();
$circuitAfterApply = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($circuitAfterApply['operation']['state'] ?? null) === 'CircuitOpen'
        && ($circuitAfterApply['operation']['nextProbeAt'] ?? null)
            === $circuitNextProbeAt
        && $credentialCalls === $circuitCredentialCalls
        && $account->testTimerInterval('MqttContinuousRecovery') > 0,
    'ApplyChanges did not preserve a credential-free circuit cooldown.'
);

$nextProbeAt = $circuit['operation']['nextProbeAt'];
$clock->advance($nextProbeAt - $clock->now());
$account->testSetVariable('LastRestSuccess', $clock->now());
$account->ProcessMqttContinuousRecovery();
$probe = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($probe['operation']['state'] ?? null) === 'HalfOpen'
        && ($probe['operation']['halfOpenProbeCount'] ?? null) === 1
        && ($probe['statistics']['continuousHalfOpenProbes'] ?? null) === 1,
    'Due outer recovery did not consume exactly one probe.'
);
$continuousInstances[$mqttId]['InstanceStatus'] = 102;
$continuousInstances[$webSocketId]['InstanceStatus'] = 102;
$clock->advance(60);
$account->ProcessMqttLifecycle();
$confirming = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($confirming['operation']['state'] ?? null) === 'RecoveryConfirming',
    'Healthy half-open probe did not enter confirmation.'
);
$clock->advance(900);
$account->ProcessMqttLifecycle();
$recovered = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($recovered['operation']['state'] ?? null) === 'Active'
        && ($recovered['statistics']['continuousHalfOpenRecoveries'] ?? null)
            === 1,
    'Sustained outer recovery did not return to Active.'
);

assertContinuousAccount(
    $account->DisconnectMqttShadow()
        === 'Continuous MQTT suspension scheduled.',
    'Continuous manual suspension was not requested.'
);
$account->ProcessMqttContinuousClosure();
$suspended = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($suspended['operation']['state'] ?? null) === 'Suspended'
        && $account->testReadVariable('MqttOperatingState') === 5
        && $account->testReadVariable('MqttLeaseExpiresAt') === 0
        && ($continuousConfigurations[$webSocketId]['Headers'] ?? null)
            === '[]'
        && $account->testReadVariable('MqttLastMessageAt')
            === $acceptedPositionAt
        && $account->testReadVariable('MqttLastPositionAt')
            === $acceptedPositionAt
        && $account->testReadVariable('MqttPositionFreshness') === 0,
    'Continuous suspension did not complete credential-free.'
);
$account->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 1199
);
assertContinuousAccount(
    $account->ResumeMqttContinuousOperation()
        === 'Continuous MQTT prerequisites are not ready.'
        && continuousAccountJson(
            $account->GetMqttDiagnostics()
        )['operation']['state'] === 'Suspended',
    'A token horizon below 1200 seconds started continuous MQTT.'
);
$account->testSetAttribute(
    'TokenExpiresAtInternal',
    $clock->now() + 1000000
);
$resumedAt = $clock->now();
assertContinuousAccount(
    $account->ResumeMqttContinuousOperation()
        === 'Continuous MQTT connection attempt scheduled.'
        && $account->testTimerInterval('MqttLifecycle') === 5000,
    'Explicit continuous resume did not schedule one new lease.'
);
$resumed = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($resumed['operation']['state'] ?? null) === 'Starting'
        && ($resumed['operation']['sessionSequence'] ?? null) === 2
        && ($resumed['operation']['leaseExpiresAt'] ?? null)
            === $resumedAt + 259200,
    'Explicit resume did not create the next bounded session.'
);

$diagnosticsJson = $account->GetMqttDiagnostics();
assertContinuousAccount(
    strlen($diagnosticsJson) < 8192
        && !str_contains($diagnosticsJson, 'SYNTHETIC')
        && !str_contains($diagnosticsJson, 'DEVICE_001')
        && !str_contains($diagnosticsJson, '/downlink/')
        && !str_contains($diagnosticsJson, 'Bearer '),
    'Continuous diagnostics leaked private material or became unbounded.'
);

$clock->advance(
    $resumed['operation']['leaseExpiresAt'] - $clock->now()
);
$account->ApplyChanges();
$expiring = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($expiring['operation']['state'] ?? null) === 'Stopping'
        && ($expiring['operation']['stopReason'] ?? null)
            === 'lease-expired',
    'ApplyChanges did not stop an exactly expired continuous lease.'
);
$account->ProcessMqttContinuousClosure();
$expired = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($expired['operation']['state'] ?? null) === 'Suspended'
        && ($expired['operation']['leaseExpiresAt'] ?? null) === 0
        && ($account->testSnapshotPersistentState()['properties'][
            'EnableMqttShadow'
        ] ?? false) === true
        && $credentialCalls === 5,
    'Expired continuous lease did not suspend credential-free.'
);

$account->testSetProperty('MqttOperatingMode', 99);
$account->ApplyChanges();
$account->ProcessMqttContinuousClosure();
$account->ProcessMqttContinuousClosure();
$invalidMode = continuousAccountJson($account->GetMqttDiagnostics());
assertContinuousAccount(
    ($invalidMode['operation']['effectiveMode'] ?? null) === 'Disabled'
        && ($invalidMode['operation']['state'] ?? null) === 'Stopped'
        && ($invalidMode['operation']['stopReason'] ?? null)
            === 'configuration-invalid'
        && ($account->testSnapshotPersistentState()['properties'][
            'EnableMqttShadow'
        ] ?? true) === false
        && $credentialCalls === 5,
    'Invalid operating mode did not disable and clean without reconnect.'
);

echo "Navimow continuous MQTT account checks passed.\n";
