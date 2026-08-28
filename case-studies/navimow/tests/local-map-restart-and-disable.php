<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';
require_once __DIR__ . '/harness/LocalMapFixture.php';

final class LocalMapRestartDevice extends NavimowDevice
{
    public int $now = 2000000;

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}

function assertLocalMapRestart(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$before = new LocalMapRestartDevice(4401);
$before->Create();
$fixture = navimowLocalMapFixture($before->now);
$before->testSetProperty('DeviceId', 'SYNTHETIC_DEVICE');
$before->testSetProperty('EnableLocalMap', true);
$before->testSetProperty(
    'AcceptedMapProjection',
    json_encode($fixture['package'], JSON_THROW_ON_ERROR)
);
$before->testSetProperty('AcceptedGeometryKey', $fixture['geometryKey']);
$before->ApplyChanges();
$before->testSetVariable('Online', true);
$before->testSetVariable('VehicleState', 2);
$before->testSetVariable('LastStatusUpdate', $before->now);
$before->testSetParentHandler(static fn (): string => json_encode(
    $fixture['evidence'],
    JSON_THROW_ON_ERROR
));
assertLocalMapRestart(
    $before->RefreshLocalMap() === 'Local map refresh succeeded.'
        && str_contains(
            $before->testReadVariable('LocalMap'),
            'station-docked'
        ),
    'Docked baseline map was not rendered.'
);
$snapshot = $before->testSnapshotPersistentState();
$after = new LocalMapRestartDevice(4401);
$after->Create();
$after->testRestorePersistentState($snapshot);
$after->ApplyChanges();
assertLocalMapRestart(
    $after->testReadVariable('LocalMap')
        === $before->testReadVariable('LocalMap')
        && $after->testTimerInterval('LocalMapRefresh') === 300000,
    'Restart did not preserve the valid map and idle cadence.'
);
$track = $after->testReadAttribute('LocalMapTrackState');
$after->testSetProperty('EnableLocalMap', false);
$after->ApplyChanges();
assertLocalMapRestart(
    $after->testTimerInterval('LocalMapRefresh') === 0
        && $after->testReadVariable('LocalMap') === ''
        && $after->testReadAttribute('LocalMapTrackState') === $track,
    'Feature disable did not stop rendering while retaining bounded state.'
);

$invalidConfiguration = new LocalMapRestartDevice(4403);
$invalidConfiguration->Create();
$invalidConfiguration->testRestorePersistentState($snapshot);
$invalidConfiguration->testSetProperty(
    'HiddenZoneSequences',
    '{invalid'
);
$parentRequestCount = 0;
$invalidConfiguration->testSetParentHandler(
    static function () use (&$parentRequestCount): string {
        ++$parentRequestCount;
        return '{}';
    }
);
$invalidConfiguration->ApplyChanges();
assertLocalMapRestart(
    $invalidConfiguration->testTimerInterval('LocalMapRefresh') === 0
        && $invalidConfiguration->testReadVariable('LocalMap') === ''
        && $invalidConfiguration->testReadAttribute(
            'LocalMapTrackState'
        ) === $track
        && $invalidConfiguration->RefreshLocalMap()
            === 'Local map configuration is invalid.'
        && $parentRequestCount === 0,
    'Invalid configuration retained presentation or performed a parent read.'
);

$corrupt = new LocalMapRestartDevice(4402);
$corrupt->Create();
$corrupt->testRestorePersistentState($snapshot);
$corrupt->testSetAttribute('LocalMapTrackState', '{invalid');
$corrupt->ApplyChanges();
$corrupt->testSetParentHandler(static fn (): string => json_encode(
    $fixture['evidence'],
    JSON_THROW_ON_ERROR
));
assertLocalMapRestart(
    $corrupt->RefreshLocalMap() === 'Local map refresh failed.'
        && $corrupt->testReadVariable('BatteryLevel') === 0,
    'Corrupt map state did not fail locally.'
);

echo "Navimow local-map restart and disable checks passed.\n";
