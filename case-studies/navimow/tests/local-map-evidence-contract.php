<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/../distribution/NavimowAccount/module.php';

final class LocalMapEvidenceAccount extends NavimowAccount
{
    public int $now = 2000000;

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}

function assertLocalMapEvidence(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function requestLocalMapEvidence(
    LocalMapEvidenceAccount $account,
    string $deviceId
): array {
    return json_decode(
        $account->ForwardData(json_encode([
            'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}',
            'SchemaVersion' => 1,
            'Function' => 'GetLocalMapEvidence',
            'DeviceId' => $deviceId,
        ], JSON_THROW_ON_ERROR)),
        true,
        64,
        JSON_THROW_ON_ERROR
    );
}

$account = new LocalMapEvidenceAccount(4001);
$account->Create();
$account->ApplyChanges();
assertLocalMapEvidence(
    requestLocalMapEvidence($account, 'SYNTHETIC_DEVICE')['status']
        === 'disabled',
    'Local-map evidence must be disabled by default.'
);
$account->testSetProperty('EnableMqttPositionDiagnostics', true);
assertLocalMapEvidence(
    requestLocalMapEvidence($account, 'SYNTHETIC_DEVICE')['status']
        === 'inactive',
    'Position diagnostics must not activate MQTT.'
);
$account->testSetProperty('EnableMqttShadow', true);
assertLocalMapEvidence(
    requestLocalMapEvidence($account, 'SYNTHETIC_DEVICE')['status']
        === 'unavailable',
    'Empty retained position evidence should be unavailable.'
);

$state = Navimow\MqttPositionDiagnostic::reduce(
    Navimow\MqttPositionDiagnostic::initialState(),
    [
        'localX' => 10.0,
        'localY' => 20.0,
        'orientation' => 0.25,
        'sourceTimestamp' => $account->now * 1000,
        'vehicleStateCode' => 1,
    ],
    $account->now
);
$account->testSetAttribute('MqttPositionDiagnostic', json_encode([
    'formatVersion' => 1,
    'deviceKey' => hash('sha256', 'SYNTHETIC_DEVICE'),
    'conflictingDeviceCount' => 0,
    'state' => $state,
], JSON_THROW_ON_ERROR));
$account->testSetAttribute(
    'MqttTaskObservationLedger',
    Navimow\MqttTaskObservationLedger::serializeLedger(
        Navimow\MqttTaskObservationLedger::initialLedger()
    )
);
$available = requestLocalMapEvidence($account, 'SYNTHETIC_DEVICE');
assertLocalMapEvidence(
    $available['status'] === 'ok'
        && $available['position']['availability'] === 'available'
        && $available['task']['status'] === 'unavailable'
        && strlen(json_encode($available, JSON_THROW_ON_ERROR)) <= 262144,
    'Matching bounded local-map evidence was not returned.'
);
assertLocalMapEvidence(
    requestLocalMapEvidence($account, 'OTHER_DEVICE')['status']
        === 'unavailable',
    'A mismatched device received retained map evidence.'
);
$root = json_decode(
    $account->testReadAttribute('MqttPositionDiagnostic'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$root['conflictingDeviceCount'] = 1;
$account->testSetAttribute(
    'MqttPositionDiagnostic',
    json_encode($root, JSON_THROW_ON_ERROR)
);
assertLocalMapEvidence(
    requestLocalMapEvidence($account, 'SYNTHETIC_DEVICE')['status']
        === 'ambiguous',
    'Conflicting-device evidence did not fail closed.'
);

echo "Navimow local-map evidence contract checks passed.\n";
