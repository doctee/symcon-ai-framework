<?php

declare(strict_types=1);

// Reuse the isolated module/runtime harness, never a production connection.
require __DIR__ . '/mqtt-shadow-reconciliation.php';

use Navimow\MqttPartialStateAccumulator;
use Navimow\MqttPositionDiagnostic;

function assertFreshness(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function freshnessPatch(array $fields, ?int $time): array
{
    return [
        'fields' => $fields,
        'sourceTimestamp' => $time,
        'classification' => 'known-state',
        'reconciliationHint' => false,
        'receiptTimestampAllowed' => $time === null,
    ];
}

$base = MqttPartialStateAccumulator::reduce(
    MqttPartialStateAccumulator::initialState(),
    freshnessPatch(['batteryLevel' => 20, 'vehicleState' => 2], 1000000),
    1000
)['state'];
$partial = MqttPartialStateAccumulator::reduce(
    $base,
    freshnessPatch(['currentMowProgress' => 100], 2000000),
    2000
)['state'];
assertFreshness(
    MqttPartialStateAccumulator::fieldAge($partial, 'batteryLevel', 2000) === 1000
        && MqttPartialStateAccumulator::fieldAge($partial, 'currentMowProgress', 2000) === 0,
    'Progress traffic rejuvenated battery freshness.'
);
$duplicate = MqttPartialStateAccumulator::reduce(
    $partial,
    freshnessPatch(['currentMowProgress' => 999], 2000000),
    2100
)['state'];
assertFreshness(
    $duplicate['fields']['currentMowProgress'] === 100
        && MqttPartialStateAccumulator::fieldAge($duplicate, 'currentMowProgress', 2100) === 100,
    'Duplicate/conflicting source time replaced or rejuvenated a field.'
);
$complement = MqttPartialStateAccumulator::reduce(
    $duplicate,
    freshnessPatch(['taskDelay' => true], 2000000),
    2100
)['state'];
assertFreshness(
    $complement['fields']['taskDelay'] === true,
    'Equal-time complementary fields must be admitted.'
);
unset($base['fieldReceivedAt'], $base['fieldSourceTimestamp']);
assertFreshness(
    MqttPartialStateAccumulator::fieldAge($base, 'batteryLevel', 2000) === null
        && MqttPartialStateAccumulator::fieldAge($partial, 'batteryLevel', 999) === null,
    'Legacy state or clock rollback fabricated freshness.'
);
$receiptOnly = MqttPartialStateAccumulator::reduce(
    $partial,
    freshnessPatch(['taskDelay' => false], null),
    2200
)['state'];
assertFreshness(
    MqttPartialStateAccumulator::fieldAge($receiptOnly, 'taskDelay', 2200) === 0
        && MqttPartialStateAccumulator::fieldAge($receiptOnly, 'vehicleState', 2200) === 1200,
    'Receipt-only evidence changed unrelated field ages.'
);

$pose = [
    'localX' => 10.0, 'localY' => 5.0, 'orientation' => 0.0,
    'sourceTimestamp' => 1700000000000, 'vehicleStateCode' => 4,
];
$position = MqttPositionDiagnostic::reduce(
    MqttPositionDiagnostic::initialState(),
    $pose,
    1700000000,
    1
);
foreach ([1700000000000, 1699999999000] as $source) {
    $replayed = MqttPositionDiagnostic::reduce(
        MqttPositionDiagnostic::restoreState(
            MqttPositionDiagnostic::serializeState($position)
        ),
        array_replace($pose, ['sourceTimestamp' => $source, 'localX' => 1.0]),
        1700000100,
        2
    );
    assertFreshness(
        $replayed['latest'] === $position['latest']
            && $replayed['track'] === $position['track']
            && $replayed['pathLengthLocal'] === $position['pathLengthLocal']
            && MqttPositionDiagnostic::project($replayed, 1700000100)['latest']['ageSeconds'] === 100,
        'Replay after restore/reconnect changed position, track, distance or age.'
    );
}
$newPosition = MqttPositionDiagnostic::reduce(
    $position,
    array_replace($pose, ['sourceTimestamp' => 1700000001000]),
    1700000100,
    2
);
assertFreshness(
    $newPosition['latest']['receivedAt'] === 1700000100,
    'New source time at unchanged coordinates was not accepted.'
);

// Exercise module comparison and read-only diagnostic projection.
$testNow = $clock->now();
$deviceKey = hash('sha256', 'DEVICE_001');
$account->testSetAttribute('MqttShadowState', json_encode([
    'formatVersion' => 1, 'devices' => [$deviceKey => ['state' => $partial]],
], JSON_THROW_ON_ERROR));
$compare = new ReflectionMethod(NavimowAccount::class, 'compareMqttShadowWithRest');
$compare->invoke($account, 'DEVICE_001', ['vehicleState' => 2, 'batteryLevel' => 20], 2000);
$stats = json_decode($account->testReadAttribute('MqttStatistics'), true, 32, JSON_THROW_ON_ERROR);
assertFreshness($stats['lastComparisonResult'] === 'stale', 'Stale fields were compared as a match.');

$account->testSetProperty('EnableMqttShadow', true);
$account->testSetProperty('EnableMqttPositionDiagnostics', true);
$reconcileInstances[$mqttId]['InstanceStatus'] = 102;
$reconcileInstances[$webSocketId]['InstanceStatus'] = 102;
$reconcileConfigurations[$webSocketId]['Active'] = true;
$rest = new ReflectionMethod(NavimowAccount::class, 'recordDevicePollingState');
$diagnostic = new ReflectionMethod(NavimowAccount::class, 'mqttDataFlowProjection');
$account->testSetAttribute('MqttPositionDiagnostic', '{}');
$rest->invoke($account, 'DEVICE_001', 1, $testNow);
$before = $account->testSnapshotPersistentState();
assertFreshness(
    $diagnostic->invoke($account)['status'] === 'awaiting-data'
        && $account->testSnapshotPersistentState() === $before,
    'Fresh REST motion must start a read-only grace window.'
);
$clock->set($testNow + 121);
assertFreshness(
    $diagnostic->invoke($account)['status'] === 'telemetry-missing',
    'Connected transport without expected positions did not report missing telemetry.'
);
$beforeMissingRead = $account->testSnapshotPersistentState();
$diagnostic->invoke($account);
assertFreshness(
    $beforeMissingRead === $account->testSnapshotPersistentState(),
    'Telemetry-missing diagnosis caused a write or recovery.'
);
$ingestPosition = new ReflectionMethod(NavimowAccount::class, 'reduceMqttPositionDiagnostic');
assertFreshness(
    $ingestPosition->invoke($account, $deviceKey, $pose, $clock->now()) === true
        && $diagnostic->invoke($account)['status'] === 'receiving',
    'Accepted position did not restore receiving diagnostics.'
);
$positionVariable = $account->testReadVariable('MqttLastPositionAt');
$clock->advance(1);
assertFreshness(
    $ingestPosition->invoke($account, $deviceKey, $pose, $clock->now()) === false
        && $account->testReadVariable('MqttLastPositionAt') === $positionVariable,
    'Module projected duplicate position as newly received.'
);
$reconcileInstances[$mqttId]['InstanceStatus'] = 104;
assertFreshness(
    $diagnostic->invoke($account)['status'] === 'transport-unavailable',
    'Transport failure was mistaken for ordinary telemetry silence.'
);
$reconcileInstances[$mqttId]['InstanceStatus'] = 102;
$clock->set($testNow + 301);
assertFreshness(
    $diagnostic->invoke($account)['status'] === 'rest-unknown',
    'Stale REST evidence continued to assert expected telemetry.'
);
$rest->invoke($account, 'DEVICE_001', 2, $testNow + 301);
assertFreshness(
    $diagnostic->invoke($account)['status'] === 'not-expected',
    'Dock silence must not be diagnosed as missing telemetry.'
);
$account->testSetProperty('EnableMqttShadow', false);
assertFreshness(
    $diagnostic->invoke($account)['status'] === 'disabled'
        && $diagnostic->invoke($account)['automaticRecoveryEnabled'] === false,
    'Disabled diagnostics or no-automatic-recovery contract changed.'
);
$account->testSetProperty('EnableMqttShadow', true);
$account->testSetAttribute('MqttPositionDiagnostic', '{}');
$rest->invoke($account, 'DEVICE_002', 1, $clock->now());
assertFreshness(
    $diagnostic->invoke($account)['status'] === 'rest-unknown',
    'Ambiguous device selection borrowed another mower freshness.'
);

echo "Navimow freshness hardening checks passed.\n";
