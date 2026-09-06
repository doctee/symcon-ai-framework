<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';
require_once __DIR__ . '/harness/LocalMapFixture.php';

final class LocalMapRuntimeDevice extends NavimowDevice
{
    public int $now = 2000000;

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}

function assertLocalMapRuntime(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$device = new LocalMapRuntimeDevice(4201);
$device->Create();
$fixture = navimowLocalMapFixture($device->now);
$device->testSetProperty('DeviceId', 'SYNTHETIC_DEVICE');
$device->testSetProperty('EnableLocalMap', true);
$device->testSetProperty(
    'AcceptedMapProjection',
    json_encode($fixture['package'], JSON_THROW_ON_ERROR)
);
$device->testSetProperty('AcceptedGeometryKey', $fixture['geometryKey']);
$device->ApplyChanges();
$device->testSetVariable('Online', true);
$device->testSetVariable('VehicleState', 1);
$device->testSetVariable('LastStatusUpdate', $device->now);
$requests = [];
$runtimeEvidence = $fixture['evidence'];
$device->testSetParentHandler(static function (string $json) use (
    &$requests,
    &$runtimeEvidence
): string {
    $requests[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    return json_encode($runtimeEvidence, JSON_THROW_ON_ERROR);
});
assertLocalMapRuntime(
    $device->RefreshLocalMap() === 'Local map refresh succeeded.',
    'Synthetic local-map refresh failed.'
);
$svg = $device->testReadVariable('LocalMap');
$track = Navimow\RevisionBoundedTrackStore::restoreState(
    $device->testReadAttribute('LocalMapTrackState')
);
$projection = Navimow\RevisionBoundedTrackStore::project($track);
$runtimeChecks = [
    'requestCount' => count($requests) === 1,
    'requestFunction' => ($requests[0]['Function'] ?? null)
        === 'GetLocalMapEvidence',
    'requestDevice' => ($requests[0]['DeviceId'] ?? null)
        === 'SYNTHETIC_DEVICE',
    'svg' => is_string($svg) && str_contains($svg, '<svg'),
    'darkTheme' => is_string($svg)
        && str_contains($svg, 'data-theme="dark"'),
    'station' => is_string($svg)
        && str_contains($svg, 'station-undocked'),
    'mowerState' => is_string($svg)
        && str_contains($svg, 'mower mower-active'),
    'hiddenLabel' => is_string($svg)
        && !str_contains($svg, '>Area A</text>'),
    'zones' => is_string($svg)
        && substr_count($svg, '<polygon class="zone"') === 4,
    'points' => $projection['pointCount'] > 0,
];
assertLocalMapRuntime(
    !in_array(false, $runtimeChecks, true),
    'Rendered runtime projection differs: '
        . json_encode($runtimeChecks, JSON_THROW_ON_ERROR)
);
$runtimeEvidence['status'] = 'delayed';
assertLocalMapRuntime(
    $device->RefreshLocalMap() === 'Local map refresh succeeded.'
        && str_contains(
            $device->testReadVariable('LocalMap'),
            'mower mower-active mower-position-delayed'
        )
        && str_contains(
            $device->testReadVariable('LocalMap'),
            '<polyline class="path"'
        ),
    'Delayed MQTT evidence did not retain the marker and path.'
);
$runtimeEvidence['status'] = 'stale';
assertLocalMapRuntime(
    $device->RefreshLocalMap() === 'Local map refresh succeeded.'
        && !str_contains(
            $device->testReadVariable('LocalMap'),
            'class="mower mower-'
        )
        && str_contains(
            $device->testReadVariable('LocalMap'),
            '<polyline class="path"'
        ),
    'Stale MQTT evidence did not hide only the current marker.'
);
$runtimeEvidence['status'] = 'ok';
$device->testSetVariable('LastStatusUpdate', $device->now - 301);
assertLocalMapRuntime(
    $device->RefreshLocalMap() === 'Local map refresh succeeded.'
        && str_contains(
            $device->testReadVariable('LocalMap'),
            'station-unknown'
        )
        && str_contains(
            $device->testReadVariable('LocalMap'),
            'mower mower-unknown'
        ),
    'Stale REST state did not render an unknown station.'
);
$device->testSetVariable('LastStatusUpdate', $device->now);
$device->testSetVariable('VehicleState', 2);
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
assertLocalMapRuntime(
    $device->RefreshLocalMap()
        === 'Local map rendered without fresh MQTT evidence.'
        && str_contains(
            $device->testReadVariable('LocalMap'),
            'station-docked'
        )
        && !str_contains(
            $device->testReadVariable('LocalMap'),
            'class="mower mower-'
        ),
    'Inactive MQTT did not preserve the path and update REST station state.'
);
$before = $device->testReadVariable('LocalMap');
$device->testSetParentHandler(static fn (): string => json_encode([
    'formatVersion' => 1,
    'status' => 'error',
    'authority' => [
        'state' => 'rest-authoritative',
        'path' => 'mqtt-inference',
        'task' => 'mqtt-inference',
    ],
    'observedAt' => 2000000,
    'position' => null,
    'task' => null,
], JSON_THROW_ON_ERROR));
assertLocalMapRuntime(
    $device->RefreshLocalMap() === 'Local map evidence is error.'
        && $device->testReadVariable('LocalMap') === $before,
    'Evidence failure replaced the previous valid map.'
);

echo "Navimow local-map runtime reducer checks passed.\n";
