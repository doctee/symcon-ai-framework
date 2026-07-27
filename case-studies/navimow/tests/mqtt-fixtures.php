<?php

declare(strict_types=1);

$fixtureDirectory = __DIR__ . '/../fixtures/mqtt';

/**
 * @return array<string, mixed>
 */
function loadMqttFixture(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read MQTT fixture.');
    }

    $decoded = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('MQTT fixture must be a JSON object.');
    }

    return $decoded;
}

function assertMqttFixture(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertExactLocationTopic(array $fixture): void
{
    assertMqttFixture(
        ($fixture['topic'] ?? null)
            === '/downlink/vehicle/DEVICE_001/realtimeDate/location',
        'MQTT location fixture has an unexpected topic.'
    );
    assertMqttFixture(
        ($fixture['channel'] ?? null) === 'location',
        'MQTT location fixture has an unexpected channel.'
    );
    assertMqttFixture(
        !str_contains((string) $fixture['topic'], '#')
            && !str_contains((string) $fixture['topic'], '+'),
        'MQTT fixture topic must not contain a wildcard.'
    );
}

function assertExactStateTopic(array $fixture): void
{
    assertMqttFixture(
        ($fixture['topic'] ?? null)
            === '/downlink/vehicle/DEVICE_001/realtimeDate/state',
        'MQTT state fixture has an unexpected topic.'
    );
    assertMqttFixture(
        ($fixture['channel'] ?? null) === 'state',
        'MQTT state fixture has an unexpected channel.'
    );
}

$credential = loadMqttFixture($fixtureDirectory . '/credential-shape.json');
assertMqttFixture(($credential['code'] ?? null) === 1, 'Credential fixture must be successful.');
$credentialData = $credential['data'] ?? null;
assertMqttFixture(is_array($credentialData), 'Credential data is missing.');
assertMqttFixture(
    ($credentialData['userName'] ?? null) === 'USER_001',
    'MQTT username is not sanitized.'
);
assertMqttFixture(
    ($credentialData['pwdInfo'] ?? null) === 'REDACTED_MQTT_PASSWORD',
    'MQTT password is not sanitized.'
);
assertMqttFixture(
    ($credentialData['mqttHost'] ?? null) === 'REDACTED_MQTT_ENDPOINT'
        && ($credentialData['mqttUrl'] ?? null) === 'REDACTED_MQTT_ENDPOINT',
    'MQTT endpoint is not sanitized.'
);
assertMqttFixture(
    ($credentialData['subTopics'] ?? null) === ['mapChange', 'realtime'],
    'MQTT subTopics shape changed.'
);

$pose = loadMqttFixture($fixtureDirectory . '/location-pose-partial.json');
assertExactLocationTopic($pose);
$posePayload = $pose['payload'] ?? null;
assertMqttFixture(
    is_array($posePayload) && count($posePayload) === 1 && is_array($posePayload[0]),
    'Pose fixture must contain one payload object.'
);
$poseEntry = $posePayload[0];
assertMqttFixture(is_string($poseEntry['postureTheta'] ?? null), 'postureTheta type changed.');
assertMqttFixture(is_float($poseEntry['postureX'] ?? null), 'postureX type changed.');
assertMqttFixture(is_float($poseEntry['postureY'] ?? null), 'postureY type changed.');
assertMqttFixture(is_int($poseEntry['time'] ?? null), 'Location time type changed.');
assertMqttFixture(is_int($poseEntry['type'] ?? null), 'Location type field changed.');
assertMqttFixture(
    is_int($poseEntry['vehicleState'] ?? null),
    'MQTT vehicleState must remain numeric until mapped by evidence.'
);

$partial = loadMqttFixture($fixtureDirectory . '/location-type-3-partial.json');
assertExactLocationTopic($partial);
$partialPayload = $partial['payload'] ?? null;
assertMqttFixture(
    is_array($partialPayload)
        && count($partialPayload) === 1
        && is_array($partialPayload[0]),
    'Partial fixture must contain one payload object.'
);
$partialEntry = $partialPayload[0];
assertMqttFixture(
    array_keys($partialEntry) === ['time', 'type'],
    'Partial location shape must retain only observed fields.'
);
assertMqttFixture(
    !array_key_exists('vehicleState', $partialEntry),
    'Absent vehicleState must remain absent.'
);

foreach (
    [
        'state-running.json' => 'isRunning',
        'state-docking.json' => 'isDocking',
        'state-docked.json' => 'isDocked',
    ] as $file => $expectedState
) {
    $stateFixture = loadMqttFixture($fixtureDirectory . '/' . $file);
    assertExactStateTopic($stateFixture);
    $statePayload = $stateFixture['payload'] ?? null;
    assertMqttFixture(
        is_array($statePayload)
            && ($statePayload['device_id'] ?? null) === 'DEVICE_001'
            && ($statePayload['state'] ?? null) === $expectedState
            && is_int($statePayload['battery'] ?? null)
            && is_int($statePayload['timestamp'] ?? null),
        sprintf('MQTT state fixture %s changed shape.', $file)
    );
}

foreach (
    [
        'location-running.json' => 4,
        'location-docking.json' => 5,
        'location-docked.json' => 2,
    ] as $file => $expectedState
) {
    $stateFixture = loadMqttFixture($fixtureDirectory . '/' . $file);
    assertExactLocationTopic($stateFixture);
    $statePayload = $stateFixture['payload'] ?? null;
    assertMqttFixture(
        is_array($statePayload)
            && count($statePayload) === 1
            && is_array($statePayload[0])
            && ($statePayload[0]['vehicleState'] ?? null) === $expectedState,
        sprintf('MQTT numeric state fixture %s changed shape.', $file)
    );
}

$typeFour = loadMqttFixture(
    $fixtureDirectory . '/location-type-4-no-time.json'
);
assertExactLocationTopic($typeFour);
$typeFourPayload = $typeFour['payload'] ?? null;
assertMqttFixture(
    is_array($typeFourPayload)
        && count($typeFourPayload) === 1
        && is_array($typeFourPayload[0])
        && $typeFourPayload[0] === [
            'taskDelay' => true,
            'type' => 4,
        ],
    'Timestamp-less type-4 fixture changed shape.'
);

$publicText = '';
foreach (glob($fixtureDirectory . '/*.json') ?: [] as $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to scan MQTT fixture.');
    }
    $publicText .= $contents;
}
foreach (
    [
        'PRIVATE-',
        'Bearer ',
        'access_token',
        'refresh_token',
        '/Users/',
    ] as $forbidden
) {
    assertMqttFixture(
        !str_contains($publicText, $forbidden),
        sprintf('MQTT fixtures contain forbidden text: %s', $forbidden)
    );
}

echo "Navimow MQTT fixture checks passed.\n";
