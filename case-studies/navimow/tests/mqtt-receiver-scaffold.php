<?php

declare(strict_types=1);

$receiverInstances = [];
$receiverAccountIngestionResult = 'accepted';
$receiverAccountIngestionThrows = false;

class IPSModule
{
    public int $InstanceID;
    private array $properties = [];
    private array $attributes = [];
    public array $debugMessages = [];

    public function __construct(int $instanceId = 2001)
    {
        $this->InstanceID = $instanceId;
    }

    public function Create()
    {
    }

    public function ApplyChanges()
    {
    }

    public function RegisterPropertyInteger(string $name, int $default): void
    {
        $this->properties[$name] ??= $default;
    }

    public function RegisterAttributeString(string $name, string $default): void
    {
        $this->attributes[$name] ??= $default;
    }

    public function ReadAttributeString(string $name): string
    {
        return (string) ($this->attributes[$name] ?? '');
    }

    public function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function ReadPropertyInteger(string $name): int
    {
        return $this->properties[$name] ?? 0;
    }

    public function SetTestPropertyInteger(string $name, int $value): void
    {
        $this->properties[$name] = $value;
    }

    public function SetTestAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function SendDebug(
        string $message,
        string $data,
        int $format
    ): void {
        $this->debugMessages[] = [
            'message' => $message,
            'data' => $data,
            'format' => $format,
        ];
    }
}

function IPS_InstanceExists(int $instanceId): bool
{
    global $receiverInstances;

    return array_key_exists($instanceId, $receiverInstances);
}

function IPS_GetInstance(int $instanceId): array
{
    global $receiverInstances;

    return $receiverInstances[$instanceId] ?? [];
}

function NAVAC_IngestMqttEnvelope(
    int $accountInstanceId,
    int $receiverInstanceId,
    string $envelopeJson
): string {
    global $receiverAccountIngestionResult;
    global $receiverAccountIngestionThrows;

    if ($receiverAccountIngestionThrows) {
        throw new RuntimeException('Synthetic Account handoff failure.');
    }
    if (
        $accountInstanceId !== 1001
        || $receiverInstanceId !== 2001
        || $envelopeJson === ''
    ) {
        return 'pairing-rejected';
    }

    return $receiverAccountIngestionResult;
}

require_once __DIR__
    . '/../distribution/NavimowMqttReceiver/module.php';

const RECEIVER_MODULE_ID = '{1B9960A2-A30C-D846-DF55-800F583AA812}';
const RECEIVER_ACCOUNT_MODULE_ID =
    '{3C2693FC-1068-4A63-856B-8AC0376556CC}';
const RECEIVER_PARENT_INTERFACE =
    '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
const RECEIVER_DATA_INTERFACE =
    '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}';

function assertReceiver(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function loadReceiverFixture(string $name): array
{
    $contents = file_get_contents(
        __DIR__ . '/../fixtures/mqtt/' . $name
    );
    if ($contents === false) {
        throw new RuntimeException('Unable to read Receiver fixture.');
    }

    $fixture = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($fixture) || !is_array($fixture['envelope'] ?? null)) {
        throw new RuntimeException('Receiver fixture is malformed.');
    }

    return $fixture['envelope'];
}

function encodeReceiverEnvelope(array $envelope): string
{
    return json_encode($envelope, JSON_THROW_ON_ERROR);
}

function lastReceiverResult(NavimowMqttReceiver $receiver): array
{
    $message = $receiver->debugMessages[
        array_key_last($receiver->debugMessages)
    ] ?? null;
    if (
        !is_array($message)
        || ($message['message'] ?? null) !== 'MQTT Receive'
        || ($message['format'] ?? null) !== 0
    ) {
        throw new RuntimeException('Receiver debug metadata is missing.');
    }

    $decoded = json_decode(
        (string) $message['data'],
        true,
        8,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($decoded)) {
        throw new RuntimeException('Receiver debug metadata is invalid.');
    }

    return $decoded;
}

function receiverDiagnostics(NavimowMqttReceiver $receiver): array
{
    $decoded = json_decode(
        $receiver->GetReceiveDiagnostics(),
        true,
        8,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($decoded)) {
        throw new RuntimeException('Receiver diagnostics are invalid.');
    }

    return $decoded;
}

$moduleRoot = __DIR__
    . '/../distribution/NavimowMqttReceiver';
$metadata = json_decode(
    (string) file_get_contents($moduleRoot . '/module.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertReceiver(
    ($metadata['id'] ?? null) === RECEIVER_MODULE_ID
        && ($metadata['name'] ?? null) === 'Navimow MQTT Receiver'
        && ($metadata['type'] ?? null) === 3
        && ($metadata['prefix'] ?? null) === 'NAVMQTTRX',
    'Receiver module identity changed.'
);
assertReceiver(
    ($metadata['parentRequirements'] ?? null) === [
        RECEIVER_PARENT_INTERFACE,
    ]
        && ($metadata['childRequirements'] ?? null) === []
        && ($metadata['implemented'] ?? null) === [
            RECEIVER_DATA_INTERFACE,
        ],
    'Receiver native MQTT interfaces changed.'
);

$moduleIds = [];
foreach (
    glob(__DIR__ . '/../distribution/*/module.json') ?: [] as $moduleFile
) {
    $module = json_decode(
        (string) file_get_contents($moduleFile),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $moduleId = $module['id'] ?? null;
    assertReceiver(
        is_string($moduleId) && !isset($moduleIds[$moduleId]),
        'Distribution contains a duplicate module GUID.'
    );
    $moduleIds[$moduleId] = true;
}

$form = json_decode(
    (string) file_get_contents($moduleRoot . '/form.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertReceiver(
    !array_key_exists('actions', $form)
        && count($form['elements'] ?? []) === 2
        && ($form['elements'][0]['type'] ?? null) === 'SelectInstance'
        && ($form['elements'][0]['name'] ?? null) === 'AccountInstanceId'
        && ($form['elements'][0]['validModules'] ?? null) === [
            RECEIVER_ACCOUNT_MODULE_ID,
        ]
        && ($form['elements'][1]['type'] ?? null) === 'Label',
    'Receiver form does not enforce the inactive Account pairing contract.'
);

$locale = json_decode(
    (string) file_get_contents($moduleRoot . '/locale.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$translations = $locale['translations']['de'] ?? null;
assertReceiver(
    is_array($translations)
        && isset($translations['Navimow MQTT Receiver'])
        && isset($translations['Navimow Account'])
        && isset(
            $translations[
                'MQTT shadow handoff remains inactive until Account pairing is implemented.'
            ]
        ),
    'Receiver locale contract is incomplete.'
);

$source = (string) file_get_contents($moduleRoot . '/module.php');
foreach (
    [
        'SendDataToParent',
        'SendDataToChildren',
        'MQTT_Publish',
        'RequestAction',
        'RegisterVariable',
        'RegisterTimer',
        'ApiClient',
        'CommandContract',
        'action.devices.commands',
        '/uplink/',
    ] as $prohibited
) {
    assertReceiver(
        !str_contains($source, $prohibited),
        'Receiver contains prohibited source: ' . $prohibited
    );
}
assertReceiver(
    str_contains(
        $source,
        "RegisterPropertyInteger('AccountInstanceId', 0)"
    )
        && str_contains(
            $source,
            "RegisterAttributeString('ReceiveDiagnostics', '{}')"
        )
        && str_contains($source, 'MqttEnvelopeParser::parse')
        && str_contains($source, "'retained-rejected'"),
    'Receiver source is missing its bounded scaffold contract.'
);

$receiver = new NavimowMqttReceiver();
$receiver->Create();
$receiver->ApplyChanges();
$emptyDiagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $emptyDiagnostics === [
        'formatVersion' => 1,
        'receiveCalls' => 0,
        'forwarded' => 0,
        'oversized' => 0,
        'invalidEnvelope' => 0,
        'retainedRejected' => 0,
        'unpaired' => 0,
        'invalidAccount' => 0,
        'handoffFailed' => 0,
        'accountResultInvalid' => 0,
        'lastResult' => 'none',
        'lastReceivedAt' => 0,
        'lastForwardedAt' => 0,
    ],
    'Fresh Receiver diagnostics do not match the fixed projection.'
);
$locationEnvelope = encodeReceiverEnvelope(
    loadReceiverFixture('symcon-envelope-location.json')
);
$receiver->ReceiveData($locationEnvelope);
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'unpaired',
    'Unpaired Receiver did not drop a valid envelope.'
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 1
        && $diagnostics['unpaired'] === 1
        && $diagnostics['forwarded'] === 0
        && $diagnostics['lastResult'] === 'unpaired'
        && $diagnostics['lastReceivedAt'] > 0
        && $diagnostics['lastForwardedAt'] === 0,
    'Receiver did not count the unpaired ingress boundary.'
);

$receiver->ReceiveData(encodeReceiverEnvelope(
    loadReceiverFixture('symcon-envelope-retained.json')
));
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'retained-rejected',
    'Receiver did not reject retained semantic input.'
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 2
        && $diagnostics['retainedRejected'] === 1,
    'Receiver did not count retained rejection.'
);

$receiver->ReceiveData(encodeReceiverEnvelope(
    loadReceiverFixture('symcon-envelope-invalid-data-id.json')
));
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'invalid-envelope',
    'Receiver did not reject an invalid DataID.'
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 3
        && $diagnostics['invalidEnvelope'] === 1,
    'Receiver did not count invalid envelope input.'
);

$receiver->ReceiveData(str_repeat('x', 65537));
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'oversized-envelope',
    'Receiver did not enforce the outer byte limit.'
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 4
        && $diagnostics['oversized'] === 1,
    'Receiver did not count oversized input.'
);

$receiver->SetTestPropertyInteger('AccountInstanceId', 1001);
$receiver->ReceiveData($locationEnvelope);
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'invalid-account',
    'Receiver accepted a missing Account instance.'
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 5
        && $diagnostics['invalidAccount'] === 1,
    'Receiver did not count a missing Account.'
);

$receiverInstances[1001] = [
    'ModuleInfo' => ['ModuleID' => '{00000000-0000-0000-0000-000000000000}'],
];
$receiver->ReceiveData($locationEnvelope);
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'invalid-account',
    'Receiver accepted an instance of another module.'
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 6
        && $diagnostics['invalidAccount'] === 2,
    'Receiver did not count an incompatible Account.'
);

$receiverInstances[1001] = [
    'ModuleInfo' => ['ModuleID' => RECEIVER_ACCOUNT_MODULE_ID],
];
$receiver->ReceiveData($locationEnvelope);
assertReceiver(
    lastReceiverResult($receiver)['result']
        === 'accepted',
    'Receiver did not hand the bounded envelope to the paired Account.'
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 7
        && $diagnostics['forwarded'] === 1
        && $diagnostics['lastResult'] === 'accepted'
        && $diagnostics['lastForwardedAt'] > 0,
    'Receiver did not count a successful Account handoff.'
);

$receiverAccountIngestionResult = 'unexpected-private-result';
$receiver->ReceiveData($locationEnvelope);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'account-result-invalid'
        && $diagnostics['receiveCalls'] === 8
        && $diagnostics['forwarded'] === 2
        && $diagnostics['accountResultInvalid'] === 1
        && $diagnostics['lastResult'] === 'account-result-invalid',
    'Receiver did not normalize an unrecognized Account result.'
);

$receiverAccountIngestionThrows = true;
$receiver->ReceiveData($locationEnvelope);
$receiverAccountIngestionThrows = false;
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    lastReceiverResult($receiver)['result'] === 'account-handoff-failed'
        && $diagnostics['receiveCalls'] === 9
        && $diagnostics['forwarded'] === 2
        && $diagnostics['handoffFailed'] === 1
        && $diagnostics['lastResult'] === 'account-handoff-failed',
    'Receiver did not count a failed Account handoff.'
);

$receiver->SetTestAttributeString(
    'ReceiveDiagnostics',
    json_encode(
        [
            'formatVersion' => 99,
            'receiveCalls' => -1,
            'forwarded' => 'private',
            'oversized' => 2147483647,
            'lastResult' => 'private-result-body',
            'lastReceivedAt' => -1,
            'topic' => '/private/topic',
            'payload' => 'private-payload',
        ],
        JSON_THROW_ON_ERROR
    )
);
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['formatVersion'] === 1
        && $diagnostics['receiveCalls'] === 0
        && $diagnostics['forwarded'] === 0
        && $diagnostics['oversized'] === 2147483647
        && $diagnostics['lastResult'] === 'unknown'
        && $diagnostics['lastReceivedAt'] === 0
        && !array_key_exists('topic', $diagnostics)
        && !array_key_exists('payload', $diagnostics),
    'Receiver diagnostics did not sanitize poisoned state.'
);
$receiver->ReceiveData(str_repeat('x', 65537));
$diagnostics = receiverDiagnostics($receiver);
assertReceiver(
    $diagnostics['receiveCalls'] === 1
        && $diagnostics['oversized'] === 2147483647
        && $diagnostics['lastResult'] === 'oversized-envelope',
    'Receiver diagnostics did not recover with a saturated counter.'
);
$receiver->SetTestAttributeString(
    'ReceiveDiagnostics',
    str_repeat('x', 4097)
);
assertReceiver(
    receiverDiagnostics($receiver) === $emptyDiagnostics,
    'Receiver diagnostics did not reject oversized stored state.'
);

$debugJson = json_encode(
    $receiver->debugMessages,
    JSON_THROW_ON_ERROR
);
foreach (
    [
        'DEVICE_001',
        '/downlink/',
        '"battery"',
        'isRunning',
        '"Payload"',
        '"Topic"',
    ] as $forbiddenDebug
) {
    assertReceiver(
        !str_contains($debugJson, $forbiddenDebug),
        'Receiver debug retained semantic input: ' . $forbiddenDebug
    );
}

$diagnosticJson = $receiver->GetReceiveDiagnostics();
assertReceiver(
    strlen($diagnosticJson) < 1024,
    'Receiver diagnostic projection exceeded its output bound.'
);
foreach (
    [
        'DEVICE_001',
        '/private/topic',
        'private-payload',
        'unexpected-private-result',
        '"Payload"',
        '"Topic"',
        'DataID',
        'AccountInstanceId',
    ] as $forbiddenDiagnostic
) {
    assertReceiver(
        !str_contains($diagnosticJson, $forbiddenDiagnostic),
        'Receiver diagnostics retained private input: '
            . $forbiddenDiagnostic
    );
}

echo "Navimow MQTT Receiver diagnostics checks passed.\n";
