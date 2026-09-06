<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/harness/FakeClock.php';
require_once __DIR__
    . '/../distribution/NavimowAccount/module.php';

final class MqttShadowDiagnosticsAccount extends NavimowAccount
{
    public function __construct(
        int $instanceId,
        private NavimowTestFakeClock $clock
    ) {
        parent::__construct($instanceId);
    }

    protected function currentTimestamp(): int
    {
        return $this->clock->now();
    }
}

function assertShadowDiagnostics(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function decodeShadowDiagnostics(string $json): array
{
    $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected diagnostic JSON object.');
    }

    return $decoded;
}

function diagnosticShadowState(
    array $fields,
    ?int $sourceTimestamp,
    int $receivedAt
): array {
    return [
        'state' => [
            'formatVersion' => 1,
            'fields' => $fields,
            'lastSourceTimestamp' => $sourceTimestamp,
            'lastReceivedAt' => $receivedAt,
        ],
        'updatedAt' => $receivedAt,
    ];
}

function setDiagnosticShadow(
    MqttShadowDiagnosticsAccount $account,
    array $devices
): void {
    $account->testSetAttribute(
        'MqttShadowState',
        json_encode([
            'formatVersion' => 1,
            'devices' => $devices,
        ], JSON_THROW_ON_ERROR)
    );
}

$clock = new NavimowTestFakeClock(1700000100);
$account = new MqttShadowDiagnosticsAccount(6101, $clock);
$account->Create();
$account->ApplyChanges();

$beforeEmptyRead = $account->testSnapshotPersistentState();
$empty = decodeShadowDiagnostics($account->GetMqttDiagnostics());
assertShadowDiagnostics(
    ($empty['formatVersion'] ?? null) === 3
        && (
            $empty['shadow']['trackedDeviceCount']
                ?? null
        ) === 0
        && (
            $empty['shadow']['observation']['status']
                ?? null
        ) === 'unavailable'
        && (
            $empty['shadow']['observation']['authority']
                ?? null
        ) === 'mqtt-hint'
        && (
            $empty['shadow']['observation']['fields']
                ?? null
        ) === [
            'vehicleState' => null,
            'batteryLevel' => null,
            'mowingPercentage' => null,
            'locationType' => null,
            'locationVehicleStateCode' => null,
            'action' => null,
            'subAction' => null,
            'mowStartType' => null,
            'currentMowProgress' => null,
            'subtotalArea' => null,
            'mowingWeekArea' => null,
            'taskDelay' => null,
            'taskTelemetryReceivedAt' => null,
            'taskTelemetryAgeSeconds' => null,
            'boundaryKey' => null,
            'partitionKey' => null,
            'partitionCount' => null,
        ]
        && $account->testSnapshotPersistentState()
            === $beforeEmptyRead,
    'Empty shadow diagnostics are not versioned and read-only.'
);

$account->testSetProperty('EnableMqttShadow', true);
$enabledEmpty = decodeShadowDiagnostics(
    $account->GetMqttDiagnostics()
);
assertShadowDiagnostics(
    ($enabledEmpty['featureEnabled'] ?? null) === true
        && (
            $enabledEmpty['shadow']['observation']['status']
                ?? null
        ) === 'unavailable',
    'Enabled empty shadow did not remain unavailable.'
);
$account->testSetProperty('EnableMqttShadow', false);

$deviceKey = hash('sha256', 'SYNTHETIC_DEVICE_ONE');
setDiagnosticShadow($account, [
    $deviceKey => diagnosticShadowState([
        'vehicleState' => 1,
        'batteryLevel' => 73,
        'mowingPercentage' => 42.5,
        'locationType' => 3,
        'locationVehicleStateCode' => 5,
        'action' => 8,
        'subAction' => 6,
        'mowStartType' => 0,
        'currentMowProgress' => 4250,
        'subtotalArea' => 23.45,
        'mowingWeekArea' => 123.45,
        'taskDelay' => true,
        'taskTelemetryReceivedAt' => 1700000050,
        'boundaryKey' => hash('sha256', 'BOUNDARY'),
        'partitionKey' => hash('sha256', 'PARTITIONS'),
        'partitionCount' => 2,
    ], 1700000000000, 1700000000),
]);
$beforeAvailableRead = $account->testSnapshotPersistentState();
$availableJson = $account->GetMqttDiagnostics();
$available = decodeShadowDiagnostics($availableJson);
assertShadowDiagnostics(
    (
        $available['shadow']['observation']
            ?? null
    ) === [
        'status' => 'available',
        'authority' => 'mqtt-hint',
        'lastSourceTimestamp' => 1700000000000,
        'lastReceivedAt' => 1700000000,
        'ageSeconds' => 100,
        'fields' => [
            'vehicleState' => 1,
            'batteryLevel' => 73,
            'mowingPercentage' => 42.5,
            'locationType' => 3,
            'locationVehicleStateCode' => 5,
            'action' => 8,
            'subAction' => 6,
            'mowStartType' => 0,
            'currentMowProgress' => 4250,
            'subtotalArea' => 23.45,
            'mowingWeekArea' => 123.45,
            'taskDelay' => true,
            'taskTelemetryReceivedAt' => 1700000050,
            'taskTelemetryAgeSeconds' => 50,
            'boundaryKey' => hash('sha256', 'BOUNDARY'),
            'partitionKey' => hash('sha256', 'PARTITIONS'),
            'partitionCount' => 2,
        ],
    ]
        && !str_contains($availableJson, $deviceKey)
        && !str_contains($availableJson, 'SYNTHETIC_DEVICE_ONE')
        && $account->testSnapshotPersistentState()
            === $beforeAvailableRead,
    'Single-device shadow observation is incomplete or identifying.'
);

setDiagnosticShadow($account, [
    $deviceKey => diagnosticShadowState([
        'vehicleState' => 5,
        'batteryLevel' => 101,
        'mowingPercentage' => -1,
        'locationType' => -1,
        'locationVehicleStateCode' => PHP_INT_MAX,
        'unsupportedSecret' => 123456,
    ], null, 1700000200),
]);
$filteredJson = $account->GetMqttDiagnostics();
$filtered = decodeShadowDiagnostics($filteredJson);
assertShadowDiagnostics(
    ($filtered['shadow']['observation']['status'] ?? null)
        === 'available'
        && (
            $filtered['shadow']['observation']['ageSeconds']
                ?? null
        ) === 0
        && (
            $filtered['shadow']['observation']['fields']
                ?? null
        ) === [
            'vehicleState' => 5,
            'batteryLevel' => null,
            'mowingPercentage' => null,
            'locationType' => null,
            'locationVehicleStateCode' => null,
            'action' => null,
            'subAction' => null,
            'mowStartType' => null,
            'currentMowProgress' => null,
            'subtotalArea' => null,
            'mowingWeekArea' => null,
            'taskDelay' => null,
            'taskTelemetryReceivedAt' => null,
            'taskTelemetryAgeSeconds' => null,
            'boundaryKey' => null,
            'partitionKey' => null,
            'partitionCount' => null,
        ]
        && !str_contains($filteredJson, 'unsupportedSecret')
        && !str_contains($filteredJson, '123456'),
    'Unsupported or invalid shadow fields were not filtered.'
);

$secondDeviceKey = hash('sha256', 'SYNTHETIC_DEVICE_TWO');
setDiagnosticShadow($account, [
    $deviceKey => diagnosticShadowState(
        ['vehicleState' => 1],
        1700000000000,
        1700000000
    ),
    $secondDeviceKey => diagnosticShadowState(
        ['vehicleState' => 2],
        1700000001000,
        1700000001
    ),
]);
$ambiguousJson = $account->GetMqttDiagnostics();
$ambiguous = decodeShadowDiagnostics($ambiguousJson);
assertShadowDiagnostics(
    ($ambiguous['shadow']['trackedDeviceCount'] ?? null) === 2
        && (
            $ambiguous['shadow']['observation']['status']
                ?? null
        ) === 'ambiguous'
        && (
            $ambiguous['shadow']['observation']['lastReceivedAt']
                ?? null
        ) === null
        && !str_contains($ambiguousJson, $deviceKey)
        && !str_contains($ambiguousJson, $secondDeviceKey),
    'Multiple tracked devices did not fail closed.'
);

$account->testSetAttribute('MqttShadowState', '{');
$malformedRoot = decodeShadowDiagnostics(
    $account->GetMqttDiagnostics()
);
assertShadowDiagnostics(
    (
        $malformedRoot['shadow']['observation']['status']
            ?? null
    ) === 'invalid',
    'Malformed shadow root was not marked invalid.'
);

setDiagnosticShadow($account, [
    $deviceKey => [
        'state' => [
            'formatVersion' => 1,
            'fields' => ['vehicleState' => 1],
            'lastSourceTimestamp' => 1700000000000,
        ],
        'updatedAt' => 1700000000,
    ],
]);
$malformedState = decodeShadowDiagnostics(
    $account->GetMqttDiagnostics()
);
assertShadowDiagnostics(
    (
        $malformedState['shadow']['observation']['status']
            ?? null
    ) === 'invalid',
    'Malformed device shadow was not marked invalid.'
);

setDiagnosticShadow($account, [
    $deviceKey => diagnosticShadowState(
        ['vehicleState' => 2],
        1700000000000,
        1700000000
    ),
]);
$account->ApplyChanges();
$cleaned = decodeShadowDiagnostics($account->GetMqttDiagnostics());
assertShadowDiagnostics(
    ($cleaned['shadow']['trackedDeviceCount'] ?? null) === 0
        && (
            $cleaned['shadow']['pendingReconciliationCount']
                ?? null
        ) === 0
        && (
            $cleaned['shadow']['observation']['status']
                ?? null
        ) === 'unavailable',
    'ApplyChanges did not remove the diagnostic shadow sample.'
);

echo "Navimow MQTT shadow diagnostic checks passed.\n";
