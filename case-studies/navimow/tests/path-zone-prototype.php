<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/MqttPathSegmenter.php';
require_once __DIR__ . '/../candidate/ZoneStatisticsReducer.php';

use Navimow\Prototype\MqttPathSegmenter;
use Navimow\Prototype\ZoneStatisticsReducer;

function assertPathZone(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fixture = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../fixtures/mqtt/path-zone-prototype.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
assertPathZone(is_array($fixture), 'Path-zone fixture is invalid.');

$path = MqttPathSegmenter::build(
    $fixture['points'],
    $fixture['ledgerProjection']['passes'],
    [
        'joinWindowSeconds' => 30,
        'maximumGapSeconds' => 120,
        'maximumStepDistanceLocal' => 50.0,
        'minimumRetainSeconds' => 5,
        'minimumRetainDistanceLocal' => 0.5,
    ]
);
$breakReasons = array_column($path['segments'], 'breakReason');
$areaKeys = array_values(array_unique(array_filter(
    array_column($path['segments'], 'areaKey'),
    static fn (mixed $value): bool => is_string($value)
)));
assertPathZone(
    $path['authority'] === 'mqtt-inference'
        && $path['coordinateFrame'] === 'uncalibrated-local'
        && $path['counters']['receivedPointCount'] === 11
        && $path['counters']['downsampledPointCount'] === 1
        && count($areaKeys) === 3,
    'Time-window correlation or path downsampling is incorrect.'
);
foreach (
    [
        'first-point',
        'time-gap',
        'vehicle-state-change',
        'transport-session-change',
        'coordinate-discontinuity',
    ] as $reason
) {
    assertPathZone(
        in_array($reason, $breakReasons, true),
        sprintf('Expected path break %s is missing.', $reason)
    );
}
assertPathZone(
    $path['latest']['receivedAt'] === 3070
        && $path['latest']['passSequence'] === 3,
    'Latest position was not retained independently of downsampling.'
);

$statistics = ZoneStatisticsReducer::reduce(
    $fixture['ledgerProjection'],
    $fixture['configuredZoneAreas']
);
$zones = [];
foreach ($statistics['zones'] as $zone) {
    $zones[$zone['areaKey']] = $zone;
}
$zoneA = $zones[str_repeat('a', 64)];
$zoneB = $zones[str_repeat('b', 64)];
$zoneC = $zones[str_repeat('c', 64)];
assertPathZone(
    $zoneA['completedPassCount'] === 1
        && $zoneA['latestPass']['passProgressPercent'] === 100.0
        && $zoneA['latestPass']['observedAreaDelta'] === 150.0
        && $zoneA['latestPass']['latestObservedAreaPercent'] === 50.0,
    'Completed synthetic zone statistics are incorrect.'
);
assertPathZone(
    $zoneB['interruptionCount'] === 1
        && $zoneB['completedPassCount'] === 0
        && $zoneB['latestPass']['passProgressPercent'] === 12.0
        && $zoneB['latestPass']['latestObservedAreaPercent'] === null,
    'Rain-interrupted zone or denominator gate is incorrect.'
);
assertPathZone(
    $zoneC['resumeCount'] === 1
        && $zoneC['latestPass']['passProgressPercent'] === 42.06
        && abs($zoneC['latestPass']['observedAreaDelta'] - 13.16) < 0.000001
        && $zoneC['latestPass']['latestObservedAreaPercent'] === 5.264,
    'Productive resumed zone statistics are incorrect.'
);
assertPathZone(
    $statistics['percentageContract']['geometricCoveragePercent']
        === 'not-implemented',
    'Prototype incorrectly claims geometric coverage.'
);

$invalidAreaRejected = false;
try {
    ZoneStatisticsReducer::reduce(
        $fixture['ledgerProjection'],
        [str_repeat('a', 64) => 0]
    );
} catch (InvalidArgumentException) {
    $invalidAreaRejected = true;
}
assertPathZone(
    $invalidAreaRejected,
    'Invalid zone-area denominator was accepted.'
);

$privacyProjection = json_encode(
    $statistics,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
);
assertPathZone(
    !str_contains($privacyProjection, 'Zone 1')
        && !str_contains($privacyProjection, 'Nachbar')
        && !str_contains($privacyProjection, 'Weber')
        && !str_contains($privacyProjection, 'DEVICE_')
        && !str_contains($privacyProjection, '/downlink/'),
    'Zone projection contains a private label, identity or topic.'
);

echo "Navimow path and zone prototype checks passed.\n";
