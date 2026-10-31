<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Navimow/RevisionBoundedTrackStore.php';
require_once __DIR__ . '/../distribution/libs/Navimow/RetainedMapPath.php';

use Navimow\RetainedMapPath;
use Navimow\RevisionBoundedTrackStore;

function assertDisplayTrack(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function displayPoint(int $at, int $state, float $x): array
{
    return [
        'localX' => $x, 'localY' => 2.0, 'orientation' => 0.0,
        'receivedAt' => $at, 'sourceTimestamp' => $at,
        'vehicleStateCode' => $state,
        'attribution' => ['source' => 'geometry-fallback', 'zoneKey' => null],
    ];
}

/** @param list<array<string, mixed>> $points
 * @return array<string, mixed>
 */
function displayScene(string $key, array $points): array
{
    return [
        'revision' => ['geometryKey' => $key, 'state' => 'accepted', 'pathCompatible' => true],
        'path' => ['status' => 'included', 'segments' => [[
            'sequence' => 1, 'breakReason' => 'time-gap', 'points' => $points,
            'vehicleStateCode' => $points[0]['vehicleStateCode'] ?? 0,
        ]]],
    ];
}

$key = hash('sha256', 'synthetic-map');
$start = 1700000000;
$mowing = displayScene($key, [
    displayPoint($start, 1, 1.0), displayPoint($start + 5, 1, 2.0),
]);
$unchanged = serialize($mowing);
$state = RetainedMapPath::observe(RetainedMapPath::restore('{}'), $mowing, 1, $start + 5);
assertDisplayTrack(serialize($mowing) === $unchanged, 'Display filter changed its input.');
$original = RetainedMapPath::path($state, $key)['segments'];
assertDisplayTrack(count($original) === 1, 'Mowing path missing.');
$finalScene = displayScene($key, [
    displayPoint($start, 1, 1.0), displayPoint($start + 5, 1, 2.0),
    displayPoint($start + 8, 1, 2.5), displayPoint($start + 10, 2, 3.0),
]);
$finished = RetainedMapPath::observe($state, $finalScene, 2, $start + 10);
assertDisplayTrack($finished['observedAt'] === $start + 8, 'Final pre-dock edge missing.');
assertDisplayTrack(count(RetainedMapPath::path($finished, $key)['segments'][0]['points']) === 3, 'Docked point leaked into display.');
$state = RetainedMapPath::observe($state, displayScene($key, [displayPoint($start + 10, 2, 3.0)]), 2, $start + 10);
for ($i = 1; $i <= 100; ++$i) {
    $time = $start + 10 + $i * 3600;
    $state = RetainedMapPath::observe($state, displayScene($key, [displayPoint($time, 2, 3.0)]), 2, $time);
}
$state = RetainedMapPath::restore(RetainedMapPath::serialize($state));
assertDisplayTrack(RetainedMapPath::path($state, $key)['segments'] === $original, 'Idle/midnight/72-hour retention removed display.');
$newStart = $start + 101 * 3600;
$state = RetainedMapPath::observe($state, displayScene($key, [displayPoint($newStart, 1, 10.0)]), 1, $newStart);
assertDisplayTrack(RetainedMapPath::path($state, $key)['segments'] === $original, 'One new point replaced previous path.');
$next = displayScene($key, [displayPoint($newStart, 1, 10.0), displayPoint($newStart + 5, 1, 11.0)]);
$state = RetainedMapPath::observe($state, $next, 1, $newStart + 5);
$nextPath = RetainedMapPath::path($state, $key);
assertDisplayTrack(count($nextPath['segments']) === 1 && $nextPath['segments'][0]['points'][0]['receivedAt'] === $newStart, 'New run retained old connecting edge.');
assertDisplayTrack($nextPath['displayHistory']['observedAt'] === $newStart + 5, 'Display timestamp differs.');
foreach ([1, 7, 8, 4, 0] as $code) {
    $time = $newStart + 20 + $code;
    $fault = displayScene($key, [displayPoint($time, $code, 11.0)]);
    $input = serialize($fault);
    $state = RetainedMapPath::observe($state, $fault, $code === 0 ? null : $code, $time);
    assertDisplayTrack(serialize($fault) === $input, 'Fault evidence altered.');
    assertDisplayTrack($state['phase'] === 'running', 'Fault/pause/unknown closed run.');
}
$working = RevisionBoundedTrackStore::scenePath($state['working'], $key);
$codes = [];
foreach ($working['segments'] as $segment) {
    foreach ($segment['points'] as $point) {
        $codes[] = $point['vehicleStateCode'];
    }
}
assertDisplayTrack(in_array(7, $codes, true) && in_array(8, $codes, true) && in_array(0, $codes, true), 'Stationary fault/unknown evidence lost.');
assertDisplayTrack(RetainedMapPath::path($state, $key)['segments'] === $nextPath['segments'], 'Fault singleton replaced path.');
for ($i = 1; $i <= 100; ++$i) {
    $time = $newStart + 100 + $i * 300;
    $state = RetainedMapPath::observe($state, displayScene($key, [displayPoint($time, 7, 11.0)]), 7, $time);
}
assertDisplayTrack(RetainedMapPath::path($state, $key)['segments'] === $nextPath['segments'], 'Long fault evicted protected display.');
$other = hash('sha256', 'changed-map');
assertDisplayTrack(RetainedMapPath::path($state, $other)['segments'] === [], 'Old geometry shown on new map.');
$state = RetainedMapPath::observe($state, displayScene($other, []), null, $newStart + 40000);
assertDisplayTrack(RetainedMapPath::path($state, $other)['segments'] === [], 'Geometry transition leaked path.');
assertDisplayTrack(strlen(RetainedMapPath::serialize($state)) < 1100 * 1024, 'State unbounded.');
echo "Retained display track: idle, midnight, restart, new run, fault and revision checks passed.\n";
