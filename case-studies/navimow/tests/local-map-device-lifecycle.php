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

$invalidGeometry = $fixture['package'];
$invalidGeometry['geometry']['zones'][0]['ring'] = [[0.0, 0.0]];
$invalidBinding = $fixture['package'];
$invalidBinding['bindings'][0]['zoneId'] = 999;
$invalidCases = [
    'blank DeviceId' => [
        'deviceId' => '',
        'package' => $fixture['package'],
        'geometryKey' => $fixture['geometryKey'],
        'hidden' => '[1]',
        'theme' => 'dark',
    ],
    'invalid hidden zones' => [
        'deviceId' => 'SYNTHETIC_DEVICE',
        'package' => $fixture['package'],
        'geometryKey' => $fixture['geometryKey'],
        'hidden' => '{invalid',
        'theme' => 'dark',
    ],
    'invalid theme' => [
        'deviceId' => 'SYNTHETIC_DEVICE',
        'package' => $fixture['package'],
        'geometryKey' => $fixture['geometryKey'],
        'hidden' => '[1]',
        'theme' => 'system',
    ],
    'invalid geometry' => [
        'deviceId' => 'SYNTHETIC_DEVICE',
        'package' => $invalidGeometry,
        'geometryKey' => SAEF_CreateConfigurationHash(
            $invalidGeometry['geometry']
        ),
        'hidden' => '[1]',
        'theme' => 'dark',
    ],
    'invalid binding' => [
        'deviceId' => 'SYNTHETIC_DEVICE',
        'package' => $invalidBinding,
        'geometryKey' => $fixture['geometryKey'],
        'hidden' => '[1]',
        'theme' => 'dark',
    ],
];
$invalidInstanceId = 4102;
foreach ($invalidCases as $name => $case) {
    $invalid = new LocalMapLifecycleDevice($invalidInstanceId++);
    $invalid->Create();
    $invalid->testSetProperty('DeviceId', $case['deviceId']);
    $invalid->testSetProperty('EnableLocalMap', true);
    $invalid->testSetProperty(
        'AcceptedMapProjection',
        json_encode($case['package'], JSON_THROW_ON_ERROR)
    );
    $invalid->testSetProperty(
        'AcceptedGeometryKey',
        $case['geometryKey']
    );
    $invalid->testSetProperty('HiddenZoneSequences', $case['hidden']);
    $invalid->testSetProperty('MapTheme', $case['theme']);
    $requestCount = 0;
    $invalid->testSetParentHandler(
        static function () use (&$requestCount): string {
            ++$requestCount;
            return '{}';
        }
    );
    $invalid->ApplyChanges();
    assertLocalMapLifecycle(
        $invalid->testTimerInterval('LocalMapRefresh') === 0
            && $invalid->testReadVariable('LocalMap') === ''
            && $invalid->RefreshLocalMap()
                === 'Local map configuration is invalid.'
            && $invalid->testTimerInterval('LocalMapRefresh') === 0
            && $invalid->testReadVariable('LocalMap') === ''
            && $requestCount === 0,
        'Invalid configuration did not fail closed: ' . $name
    );
}

echo "Navimow local-map Device lifecycle checks passed.\n";
