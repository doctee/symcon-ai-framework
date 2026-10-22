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

// Lifecycle clears remove observations, not device-clock boundaries.
$account->testSetAttribute('MqttShadowState', json_encode([
    'formatVersion' => 1, 'devices' => [$deviceKey => ['state' => $partial]],
], JSON_THROW_ON_ERROR));
$account->testSetAttribute('MqttPositionDiagnostic', json_encode([
    'formatVersion' => 1, 'deviceKey' => $deviceKey, 'conflictingDeviceCount' => 0,
    'state' => $position,
], JSON_THROW_ON_ERROR));
$beforeResetAt = $account->testReadVariable('MqttLastPositionAt');
$account->ApplyChanges();
$reset = json_decode($account->GetMqttDiagnostics(), true, 32, JSON_THROW_ON_ERROR)['observationResets'];
$root = json_decode($account->testReadAttribute('MqttPositionDiagnostic'), true, 32, JSON_THROW_ON_ERROR);
$shadow = json_decode($account->testReadAttribute('MqttShadowState'), true, 32, JSON_THROW_ON_ERROR);
assertFreshness(
    $root['state']['latest'] === null && $root['state']['track'] === []
        && $root['state']['lastSourceTimestamp'] === $pose['sourceTimestamp']
        && $shadow['devices'][$deviceKey]['state']['fields'] === []
        && $shadow['devices'][$deviceKey]['state']['fieldReceivedAt'] === []
        && $reset['lastResetReason'] === 'apply-changes'
        && $reset['lastResetAt'] === $clock->now()
        && $reset['cumulativeCounters']['sampleSequence'] === 1,
    'ApplyChanges lost ordering, retained observations or omitted reset accounting.'
);
$account->ApplyChanges();
assertFreshness(
    json_decode($account->GetMqttDiagnostics(), true)['observationResets'] === $reset,
    'Empty repeated ApplyChanges fabricated a reset or double-counted samples.'
);
$restoredAccount = new MqttReconciliationAccount(9901, $clock, static function (): array {
    throw new RuntimeException('No network request is permitted in the restore regression.');
});
$restoredAccount->Create();
$restoredAccount->testRestorePersistentState($account->testSnapshotPersistentState());
$restoredAccount->ApplyChanges();
foreach ([$pose['sourceTimestamp'], $pose['sourceTimestamp'] - 1] as $sourceTime) {
    assertFreshness(
        !$ingestPosition->invoke(
            $restoredAccount,
            $deviceKey,
            array_replace($pose, ['sourceTimestamp' => $sourceTime]),
            $clock->now()
        ) && $restoredAccount->testReadVariable('MqttLastPositionAt') === $beforeResetAt,
        'Restore/ApplyChanges admitted an old position or refreshed its receipt time.'
    );
}
$rejectedRoot = json_decode($restoredAccount->testReadAttribute('MqttPositionDiagnostic'), true);
assertFreshness(
    $rejectedRoot['state']['latest'] === null && $rejectedRoot['state']['track'] === []
        && $rejectedRoot['state']['pathLengthLocal'] === 0,
    'Rejected post-reset positions created geometry.'
);
assertFreshness(
    $ingestPosition->invoke(
        $restoredAccount,
        $deviceKey,
        array_replace($pose, ['sourceTimestamp' => $pose['sourceTimestamp'] + 1]),
        $clock->now()
    ),
    'Strictly newer post-reset position was not admitted.'
);
$clearedFieldState = $shadow['devices'][$deviceKey]['state'];
$equal = MqttPartialStateAccumulator::reduce(
    $clearedFieldState,
    freshnessPatch(['currentMowProgress' => 999, 'taskDelay' => true], 2000000),
    $clock->now()
)['state'];
assertFreshness(
    !isset($equal['fields']['currentMowProgress']) && $equal['fields']['taskDelay'] === true
        && MqttPartialStateAccumulator::fieldAge($equal, 'currentMowProgress', $clock->now()) === null,
    'Reset allowed equal-time replay or rejected a complementary field.'
);
$older = MqttPartialStateAccumulator::reduce(
    $equal,
    freshnessPatch(['batteryLevel' => 99], 1999999),
    $clock->now()
);
assertFreshness(!$older['accepted'] && $older['state'] === $equal, 'Reset admitted an older shadow packet.');
$newer = MqttPartialStateAccumulator::reduce(
    $equal,
    freshnessPatch(['currentMowProgress' => 101], 2000001),
    $clock->now()
)['state'];
assertFreshness(
    $newer['fields']['currentMowProgress'] === 101
        && MqttPartialStateAccumulator::fieldAge($newer, 'batteryLevel', $clock->now()) === null,
    'New field update rejuvenated an absent field.'
);
$receiptWithBoundary = MqttPartialStateAccumulator::reduce(
    $partial,
    freshnessPatch(['currentMowProgress' => 102], null),
    $clock->now()
)['state'];
assertFreshness(
    $receiptWithBoundary['fieldSourceTimestamp']['currentMowProgress'] === 2000000,
    'Receipt-only evidence erased a known source-time boundary.'
);
$clear = new ReflectionMethod(NavimowAccount::class, 'clearMqttEphemeralState');
$totalsBefore = json_decode($restoredAccount->GetMqttDiagnostics(), true)['observationResets'];
$clear->invoke($restoredAccount, 'transport-disconnect');
$totalsAfter = json_decode($restoredAccount->GetMqttDiagnostics(), true)['observationResets'];
assertFreshness(
    $totalsAfter['cumulativeCounters'] === $totalsBefore['cumulativeCounters']
        && $totalsAfter['cumulativeCounters']['sampleSequence'] === 4
        && $totalsAfter['cumulativeCounters']['rejectedTimestampCount'] === 2
        && $totalsAfter['segmentCounters']['sampleSequence'] === 0
        && $totalsAfter['segmentSequence'] === $totalsBefore['segmentSequence'] + 1
        && $totalsAfter['lastResetReason'] === 'transport-disconnect',
    'Disconnect lost or double-counted cumulative counters.'
);
$beforeRead = $restoredAccount->testSnapshotPersistentState();
$resetJson = $restoredAccount->GetMqttDiagnostics();
assertFreshness(
    $restoredAccount->testSnapshotPersistentState() === $beforeRead
        && !str_contains($resetJson, $deviceKey) && !str_contains($resetJson, 'localX')
        && strlen($resetJson) < 6144,
    'Reset diagnostics mutated state, leaked identity/coordinates or exceeded the bound.'
);
$restoredAccount->testSetAttribute('MqttPositionDiagnostic', '{');
$beforeInvalidClear = $restoredAccount->testSnapshotPersistentState();
$clear->invoke($restoredAccount, 'apply-changes');
assertFreshness(
    $restoredAccount->testReadAttribute('MqttPositionDiagnostic') === '{'
        && $restoredAccount->testReadAttribute('MqttShadowState')
            === $beforeInvalidClear['attributes']['MqttShadowState']
        && json_decode($restoredAccount->GetMqttDiagnostics(), true)['errors']['latestReason'] === 'ordering-state-invalid',
    'Malformed ordering state was discarded or omitted from diagnostics.'
);
$invalidRejected = false;
try {
    $ingestPosition->invoke($restoredAccount, $deviceKey, $pose, $clock->now());
} catch (JsonException) {
    $invalidRejected = true;
}
assertFreshness($invalidRejected, 'Malformed ordering state allowed ingestion with a fresh boundary.');

$boundedDevices = [];
for ($index = 0; $index < 64; $index++) {
    $boundedDevices[hash('sha256', 'synthetic-device-' . $index)] = [
        'state' => $clearedFieldState, 'updatedAt' => $clock->now(),
    ];
}
$restoredAccount->testSetAttribute('MqttShadowState', json_encode([
    'formatVersion' => 1, 'devices' => $boundedDevices,
], JSON_THROW_ON_ERROR));
$payloadIngest = new ReflectionMethod(NavimowAccount::class, 'reduceMqttPayload');
$beforeCapacity = $restoredAccount->testReadAttribute('MqttShadowState');
$capacityRejected = false;
try {
    $payloadIngest->invoke($restoredAccount, ['patches' => []], 'synthetic-new-device', $clock->now());
} catch (Navimow\MqttPayloadException) {
    $capacityRejected = true;
}
assertFreshness(
    $capacityRejected && $restoredAccount->testReadAttribute('MqttShadowState') === $beforeCapacity,
    'Bounded device capacity evicted an ordering boundary.'
);

echo "Navimow freshness hardening checks passed.\n";
