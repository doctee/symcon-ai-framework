<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';
require_once __DIR__ . '/harness/LocalMapFixture.php';

final class LocalMapStatisticsDevice extends NavimowDevice
{
    public int $now = 2000000;

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}

function assertLocalMapStatistics(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$device = new LocalMapStatisticsDevice(4401);
$device->Create();
$fixture = navimowLocalMapFixture($device->now);
$device->testSetProperty('DeviceId', 'SYNTHETIC_DEVICE');
$device->testSetProperty('EnableLocalMap', true);
$device->testSetProperty('EnableZoneStatistics', true);
$device->testSetProperty(
    'AcceptedMapProjection',
    json_encode($fixture['package'], JSON_THROW_ON_ERROR)
);
$device->testSetProperty('AcceptedGeometryKey', $fixture['geometryKey']);
$device->ApplyChanges();

assertLocalMapStatistics(
    $device->testStatus() === IS_ACTIVE,
    'Successful ApplyChanges did not finalize the active instance status.'
);

$definitions = $device->testVariableDefinitions();
$expected = [
    'StatisticsState' => [1, 'NAVIMOW.StatisticsState'],
    'StatisticsUpdatedAt' => [1, '~UnixTimestamp'],
    'Zone101PassProgress' => [2, 'NAVIMOW.Percentage'],
    'Zone101ObservedArea' => [2, 'NAVIMOW.Area'],
    'Zone101LastObservedAt' => [1, '~UnixTimestamp'],
    'Zone101StatisticsQuality' => [1, 'NAVIMOW.StatisticsQuality'],
    'Zone102PassProgress' => [2, 'NAVIMOW.Percentage'],
    'Zone103PassProgress' => [2, 'NAVIMOW.Percentage'],
];
foreach ($expected as $ident => [$type, $profile]) {
    assertLocalMapStatistics(
        isset($definitions[$ident])
            && $definitions[$ident]['type'] === $type
            && $definitions[$ident]['profile'] === $profile,
        'Statistics variable contract differs for ' . $ident . '.'
    );
}
assertLocalMapStatistics(
    !isset($definitions['Zone104PassProgress'])
        && $device->testReadVariable('StatisticsState') === 1,
    'Unbound zone or initial state differs.'
);

$device->testSetVariable('Online', true);
$device->testSetVariable('VehicleState', 1);
$device->testSetVariable('LastStatusUpdate', $device->now);
$device->testSetParentHandler(static fn (): string => json_encode(
    $fixture['evidence'],
    JSON_THROW_ON_ERROR
));
assertLocalMapStatistics(
    $device->RefreshLocalMap() === 'Local map refresh succeeded.'
        && $device->testReadVariable('StatisticsState') === 2
        && $device->testReadVariable('StatisticsUpdatedAt') === $device->now
        && $device->testReadVariable('Zone101PassProgress') === 100.0
        && $device->testReadVariable('Zone101ObservedArea') === 90.0
        && $device->testReadVariable('Zone101StatisticsQuality') === 3
        && $device->testReadVariable('Zone102PassProgress') === 20.0
        && $device->testReadVariable('Zone103PassProgress') === 30.0,
    'Fresh statistics projection differs.'
);

$retainedArea = $device->testReadVariable('Zone101ObservedArea');
$device->testSetParentHandler(static fn (): string => json_encode([
    'formatVersion' => 1,
    'status' => 'inactive',
    'authority' => [
        'state' => 'rest-authoritative',
        'path' => 'mqtt-inference',
        'task' => 'mqtt-inference',
    ],
    'observedAt' => 2000000,
    'position' => null,
    'task' => null,
], JSON_THROW_ON_ERROR));
assertLocalMapStatistics(
    $device->RefreshLocalMap()
        === 'Local map rendered without fresh MQTT evidence.'
        && $device->testReadVariable('StatisticsState') === 3
        && $device->testReadVariable('Zone101ObservedArea') === $retainedArea,
    'Stale statistics did not preserve retained values.'
);

$beforeDisable = $device->testVariableDefinitions();
$device->testSetProperty('EnableZoneStatistics', false);
$device->ApplyChanges();
assertLocalMapStatistics(
    $device->testReadVariable('StatisticsState') === 0
        && $device->testVariableDefinitions() === $beforeDisable,
    'Statistics disable removed or changed stable variables.'
);

echo "Navimow local-map statistics variable checks passed.\n";
