<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';
require_once __DIR__ . '/harness/LocalMapFixture.php';

final class LocalMapLifecycleDevice extends NavimowDevice
{
    public int $now = 2000000;

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}

function assertLocalMapLifecycle(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$device = new LocalMapLifecycleDevice(4101);
$device->Create();
$device->ApplyChanges();
$definitions = $device->testVariableDefinitions();
assertLocalMapLifecycle(
    isset($definitions['LocalMap'])
        && $definitions['LocalMap']['type'] === 3
        && $definitions['LocalMap']['profile'] === '~HTMLBox'
        && $device->testTimerInterval('LocalMapRefresh') === 0,
    'Disabled local-map lifecycle contract differs.'
);
$fixture = navimowLocalMapFixture($device->now);
$device->testSetProperty('DeviceId', 'SYNTHETIC_DEVICE');
$device->testSetProperty('EnableLocalMap', true);
$device->testSetProperty(
    'AcceptedMapProjection',
    json_encode($fixture['package'], JSON_THROW_ON_ERROR)
);
$device->testSetProperty(
    'AcceptedGeometryKey',
    $fixture['geometryKey']
);
$device->ApplyChanges();
assertLocalMapLifecycle(
    $device->testTimerInterval('LocalMapRefresh') === 300000,
    'Configured inactive map did not select the idle cadence.'
);
$device->testSetVariable('Online', true);
$device->testSetVariable('VehicleState', 1);
$device->testSetVariable('LastStatusUpdate', $device->now);
$device->MessageSink($device->now, 0, IPS_KERNELSTARTED, []);
assertLocalMapLifecycle(
    $device->testTimerInterval('LocalMapRefresh') === 60000,
    'Kernel reconciliation did not select the active cadence.'
);

$invalidTheme = new LocalMapLifecycleDevice(4102);
$invalidTheme->Create();
$invalidTheme->testSetProperty('DeviceId', 'SYNTHETIC_DEVICE');
$invalidTheme->testSetProperty('EnableLocalMap', true);
$invalidTheme->testSetProperty(
    'AcceptedMapProjection',
    json_encode($fixture['package'], JSON_THROW_ON_ERROR)
);
$invalidTheme->testSetProperty(
    'AcceptedGeometryKey',
    $fixture['geometryKey']
);
$invalidTheme->testSetProperty('MapTheme', 'system');
$invalidTheme->ApplyChanges();
assertLocalMapLifecycle(
    $invalidTheme->testTimerInterval('LocalMapRefresh') === 0,
    'Unknown map theme did not fail configuration validation.'
);

echo "Navimow local-map Device lifecycle checks passed.\n";
