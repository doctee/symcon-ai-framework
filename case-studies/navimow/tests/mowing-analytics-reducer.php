<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../candidate/LocalMapSceneProjector.php';
require_once __DIR__ . '/../candidate/RevisionBoundedTrackStore.php';
require_once __DIR__ . '/../candidate/MowingAnalyticsReducer.php';

use Navimow\Prototype\LocalMapSceneProjector;
use Navimow\Prototype\MowingAnalyticsReducer;
use Navimow\Prototype\RevisionBoundedTrackStore;

function assertMowingAnalytics(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param callable(): mixed $operation */
function assertMowingAnalyticsRejected(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$zoneKey = hash('sha256', 'synthetic-zone');
$geometry = [
    'formatVersion' => 1,
    'coordinateFrame' => 'navimow-local-map',
    'zones' => [[
        'id' => 101,
        'reportedArea' => 100.0,
        'calculatedArea' => 100.0,
        'ring' => [
            [0.0, 0.0],
            [10.0, 0.0],
            [10.0, 10.0],
            [0.0, 10.0],
            [0.0, 0.0],
        ],
    ]],
    'obstacles' => [[
        'calculatedArea' => 1.0,
        'ring' => [
            [1.0, 1.0],
            [2.0, 1.0],
            [2.0, 2.0],
            [1.0, 2.0],
            [1.0, 1.0],
        ],
    ]],
    'station' => ['x' => -1.0, 'y' => 0.0, 'direction' => 0.0],
];
$geometryKey = SAEF_CreateConfigurationHash($geometry);
$start = 1725192000;
$runningPoints = [];
foreach ([1.0, 3.0, 5.0, 7.0, 9.0] as $index => $x) {
    $timestamp = $start + $index * 60;
    $runningPoints[] = [
        'localX' => $x,
        'localY' => 5.0,
        'orientation' => 0.0,
        'sourceTimestamp' => $timestamp,
        'receivedAt' => $timestamp,
        'vehicleStateCode' => 4,
    ];
}
$path = [
    'formatVersion' => 1,
    'authority' => 'mqtt-inference',
    'coordinateFrame' => 'uncalibrated-local',
    'latest' => $runningPoints[array_key_last($runningPoints)],
    'segments' => [
        [
            'sequence' => 1,
            'passSequence' => 7,
            'areaKey' => $zoneKey,
            'sessionSequence' => 3,
            'vehicleStateCode' => 4,
            'startedAt' => $start,
            'endedAt' => $start + 240,
            'breakReason' => 'first-point',
            'pathLengthLocal' => 8.0,
            'points' => $runningPoints,
        ],
        [
            'sequence' => 2,
            'passSequence' => 7,
            'areaKey' => $zoneKey,
            'sessionSequence' => 3,
            'vehicleStateCode' => 5,
            'startedAt' => $start + 300,
            'endedAt' => $start + 360,
            'breakReason' => 'vehicle-state-change',
            'pathLengthLocal' => 8.0,
            'points' => [
                array_replace($runningPoints[0], [
                    'receivedAt' => $start + 300,
                    'sourceTimestamp' => $start + 300,
                    'vehicleStateCode' => 5,
                ]),
                array_replace($runningPoints[4], [
                    'receivedAt' => $start + 360,
                    'sourceTimestamp' => $start + 360,
                    'vehicleStateCode' => 5,
                ]),
            ],
        ],
    ],
    'counters' => [],
    'policy' => [],
];
$statistics = [
    'formatVersion' => 1,
    'authority' => 'mqtt-inference',
    'zones' => [[
        'areaKey' => $zoneKey,
        'configuredZoneArea' => 100.0,
        'interruptionCount' => 2,
        'resumeCount' => 1,
    ]],
];
$revision = [
    'currentGeometryKey' => $geometryKey,
    'acceptedGeometryKey' => $geometryKey,
    'pathGeometryKey' => $geometryKey,
    'statisticsGeometryKey' => $geometryKey,
    'frameCorrelationApproved' => true,
];
$scene = LocalMapSceneProjector::build(
    $geometry,
    $path,
    $statistics,
    [[
        'zoneId' => 101,
        'zoneKey' => $zoneKey,
        'label' => 'Synthetic Zone',
    ]],
    $revision
);
$trackState = RevisionBoundedTrackStore::ingestScene(
    RevisionBoundedTrackStore::initialState(),
    $scene
);
$scene['path'] = RevisionBoundedTrackStore::scenePath(
    $trackState,
    $geometryKey
);
assertMowingAnalytics(
    $scene['path']['segments'][0]['passSequence'] === 7
        && $scene['path']['segments'][0]['sessionSequence'] === 3
        && $scene['path']['segments'][0]['vehicleStateCode'] === 4
        && $scene['path']['segments'][0]['points'][0]
            ['sourceTimestamp'] === $start,
    'Retained path lost analytics metadata.'
);

$options = [
    'timeZone' => 'UTC',
    'metersPerLocalUnit' => 1.0,
    'cuttingWidthMeters' => 1.0,
    'coverageCellSizeMeters' => 0.5,
    'recencyWarningDays' => 7,
    'recencyCriticalDays' => 14,
    'zoneBindings' => [[
        'zoneId' => 101,
        'zoneKey' => $zoneKey,
    ]],
    'subareas' => [[
        'key' => 'west-half',
        'zoneKey' => $zoneKey,
        'label' => 'West Half',
        'ring' => [
            [0.0, 0.0],
            [5.0, 0.0],
            [5.0, 10.0],
            [0.0, 10.0],
            [0.0, 0.0],
        ],
    ]],
];
$state = MowingAnalyticsReducer::update(
    MowingAnalyticsReducer::initialState(),
    $scene,
    $start + 400,
    $options
);
// Neither REST's numeric Running value nor dock/travel/unknown MQTT codes
// qualify a position segment for geometric mowing estimates.
foreach ([0, 1, 2, 3, 5, 6, 99] as $excludedCode) {
    $wireCodeScene = $scene;
    foreach ($wireCodeScene['path']['segments'] as &$wireSegment) {
        $wireSegment['vehicleStateCode'] = $excludedCode;
        foreach ($wireSegment['points'] as &$wirePoint) {
            $wirePoint['vehicleStateCode'] = $excludedCode;
        }
        unset($wirePoint);
    }
    unset($wireSegment);
    $wireState = MowingAnalyticsReducer::update(
        MowingAnalyticsReducer::initialState(),
        $wireCodeScene,
        $start + 400,
        $options
    );
    $wireProjection = MowingAnalyticsReducer::project(
        $wireState,
        $geometryKey,
        $start + 400,
        $options
    );
    assertMowingAnalytics(
        $wireProjection['latestRun'] === null
        && $wireProjection['zones'][0]['todayEstimatedArea'] === null,
        'Excluded MQTT code unexpectedly contributed mowing area: ' . $excludedCode
    );
}
$projection = MowingAnalyticsReducer::project(
    $state,
    $geometryKey,
    $start + 400,
    $options
);
$zone = $projection['zones'][0];
// Reconstruct prior contracts to exercise retained-state upgrades.
$policyMethod = new ReflectionMethod(MowingAnalyticsReducer::class, 'policy');
$oldPolicy = $policyMethod->invoke(null, $options);
$oldSubareas = array_map(static fn (array $subarea): array => [
    'key' => $subarea['key'],
    'zoneKey' => $subarea['zoneKey'],
    'ring' => $subarea['ring'],
], $oldPolicy['subareas']);
usort($oldSubareas, static fn (array $a, array $b): int => $a['key'] <=> $b['key']);
foreach ([1, 2] as $oldAlgorithmVersion) {
    $oldContractKey = hash('sha256', json_encode([
        'algorithmVersion' => $oldAlgorithmVersion,
        'geometryKey' => $geometryKey,
        'timeZone' => $oldPolicy['timeZone'],
        'metersPerLocalUnit' => $oldPolicy['metersPerLocalUnit'],
        'cuttingWidthMeters' => $oldPolicy['cuttingWidthMeters'],
        'coverageCellSizeMeters' => $oldPolicy['coverageCellSizeMeters'],
        'zoneBindings' => $oldPolicy['zoneBindings'],
        'subareas' => $oldSubareas,
    ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    $oldState = $state;
    $oldState['revisions'][0]['contractKey'] = $oldContractKey;
    $oldRevision = $oldState['revisions'][0];
    $beforeUpgrade = MowingAnalyticsReducer::project(
        $oldState,
        $geometryKey,
        $start + 400,
        $options
    );
    assertMowingAnalytics(
        $beforeUpgrade['latestRun'] === null,
        'Prior-version aggregates leaked into the corrected projection.'
    );
    $upgradedState = MowingAnalyticsReducer::update(
        MowingAnalyticsReducer::restoreState(
            MowingAnalyticsReducer::serializeState($oldState)
        ),
        $scene,
        $start + 500,
        $options
    );
    $upgradedProjection = MowingAnalyticsReducer::project(
        $upgradedState,
        $geometryKey,
        $start + 500,
        $options
    );
    assertMowingAnalytics(
        count($upgradedState['revisions']) === 2
            && $upgradedState['revisions'][0] === $oldRevision
            && $upgradedProjection['latestRun'] === $projection['latestRun'],
        'Upgrade must preserve old evidence and recalculate only retained points.'
    );
}
$legacyZeroState = $state;
foreach ($legacyZeroState['revisions'][0]['runs'] as &$legacyRun) {
    $legacyRun['estimatedArea'] = 0.0;
    $legacyRun['areaPerformanceSquareMetersPerHour'] = 0.0;
}
unset($legacyRun);
foreach ($legacyZeroState['revisions'][0]['days'] as &$legacyDay) {
    foreach ($legacyDay['zones'] as &$legacyZone) {
        $legacyZone['estimatedArea'] = 0.0;
    }
    unset($legacyZone);
}
unset($legacyDay);
$unknownProjection = MowingAnalyticsReducer::project(
    $legacyZeroState,
    $geometryKey,
    $start + 400,
    $options
);
assertMowingAnalytics(
    $unknownProjection['zones'][0]['latestRunCoveragePercent'] === null
        && $unknownProjection['zones'][0]['weekEstimatedArea'] === null
        && $unknownProjection['latestRun']['estimatedArea'] === null
        && $unknownProjection['latestRun']['areaPerformanceSquareMetersPerHour'] === null
        && $unknownProjection['latestRun']['endedAt'] === $projection['latestRun']['endedAt']
        && $legacyZeroState['revisions'][0]['runs'][0]['estimatedArea'] === 0.0,
    'Legacy empty coverage must project as unknown without rewriting history.'
);
$incrementalTrack = RevisionBoundedTrackStore::initialState();
$incrementalAnalytics = MowingAnalyticsReducer::initialState();
$sourceScene = $scene;
$sourcePoints = $sourceScene['path']['segments'][0]['points'];
foreach (array_keys($sourcePoints) as $index) {
    $partial = $sourceScene;
    $partial['path']['segments'] = [$sourceScene['path']['segments'][0]];
    $partial['path']['segments'][0]['points'] = array_slice(
        $sourcePoints,
        0,
        $index + 1
    );
    $incrementalTrack = RevisionBoundedTrackStore::ingestScene(
        $incrementalTrack,
        $partial
    );
    $partial['path'] = RevisionBoundedTrackStore::scenePath(
        $incrementalTrack,
        $geometryKey
    );
    $incrementalAnalytics = MowingAnalyticsReducer::update(
        $incrementalAnalytics,
        $partial,
        $start + 400,
        $options
    );
}
$incrementalProjection = MowingAnalyticsReducer::project(
    $incrementalAnalytics,
    $geometryKey,
    $start + 400,
    $options
);
assertMowingAnalytics(
    $incrementalProjection['zones'] === $projection['zones'],
    'Incremental retained points differ from batch mowing analytics.'
);
assertMowingAnalytics(
    $projection['state'] === 'available'
        && $projection['contracts']['restRemainsStateAuthority'] === true
        && $projection['contracts']['rainReasonEvidence'] === 'not-observed'
        && $zone['zoneId'] === 101
        && abs($zone['latestRun']['distanceMeters'] - 8.0) < 0.0001
        && $zone['latestRun']['activeDurationSeconds'] === 240
        && is_float($zone['latestRun']['estimatedArea'])
        && $zone['latestRun']['estimatedArea'] > 0.0
        && is_float($zone['latestRunCoveragePercent'])
        && $zone['recencyState'] === 1
        && $zone['interruptionCount'] === 2
        && $zone['resumeCount'] === 1
        && $zone['rainInterruptionCount'] === null
        && $projection['subareas'][0]['todayEstimatedArea'] > 0.0
        && $projection['subareas'][0]['lastMowedAt'] !== null
        && $projection['subareas'][0]['recencyState'] === 1,
    'Mowing analytics projection differs.'
);

$stateAgain = MowingAnalyticsReducer::update(
    $state,
    $scene,
    $start + 500,
    $options
);
$again = MowingAnalyticsReducer::project(
    $stateAgain,
    $geometryKey,
    $start + 500,
    $options
);
assertMowingAnalytics(
    abs($again['latestRun']['distanceMeters'] - 8.0) < 0.0001
        && $again['counters']['updateCount'] === 2,
    'Repeated retained-scene reduction double-counted a run.'
);

$widerOptions = $options;
$widerOptions['cuttingWidthMeters'] = 2.0;
$stateWithWiderContract = MowingAnalyticsReducer::update(
    $stateAgain,
    $scene,
    $start + 600,
    $widerOptions
);
$originalContractProjection = MowingAnalyticsReducer::project(
    $stateWithWiderContract,
    $geometryKey,
    $start + 600,
    $options
);
$widerContractProjection = MowingAnalyticsReducer::project(
    $stateWithWiderContract,
    $geometryKey,
    $start + 600,
    $widerOptions
);
assertMowingAnalytics(
    count($stateWithWiderContract['revisions']) === 2
        && $originalContractProjection['latestRun']['estimatedArea']
            === $again['latestRun']['estimatedArea']
        && $widerContractProjection['latestRun']['estimatedArea']
            > $again['latestRun']['estimatedArea'],
    'Physical calibration drift mixed incompatible analytics revisions.'
);

$changedSubareaOptions = $options;
$changedSubareaOptions['subareas'][0]['ring'][1] = [4.0, 0.0];
$changedSubareaOptions['subareas'][0]['ring'][2] = [4.0, 10.0];
$stateWithSubareaContract = MowingAnalyticsReducer::update(
    $stateWithWiderContract,
    $scene,
    $start + 700,
    $changedSubareaOptions
);
assertMowingAnalytics(
    count($stateWithSubareaContract['revisions']) === 3,
    'Subarea geometry drift did not create a separate analytics revision.'
);

$mismatchedBindingOptions = $options;
$mismatchedBindingOptions['zoneBindings'][0]['zoneKey'] = hash(
    'sha256',
    'different-zone'
);
assertMowingAnalyticsRejected(
    static fn (): array => MowingAnalyticsReducer::update(
        $stateAgain,
        $scene,
        $start + 800,
        $mismatchedBindingOptions
    ),
    'A zone-binding mismatch was accepted.'
);

$invalidTimeZoneOptions = $options;
$invalidTimeZoneOptions['timeZone'] = 123;
assertMowingAnalyticsRejected(
    static fn (): array => MowingAnalyticsReducer::project(
        $stateAgain,
        $geometryKey,
        $start + 800,
        $invalidTimeZoneOptions
    ),
    'A non-string time zone was accepted.'
);

$warning = MowingAnalyticsReducer::project(
    $stateAgain,
    $geometryKey,
    $start + 8 * 86400,
    $options
);
$critical = MowingAnalyticsReducer::project(
    $stateAgain,
    $geometryKey,
    $start + 15 * 86400,
    $options
);
assertMowingAnalytics(
    $warning['zones'][0]['recencyState'] === 2
        && $critical['zones'][0]['recencyState'] === 3,
    'Seven- and fourteen-day mowing recency gates differ.'
);

$disabledOptions = $options;
$disabledOptions['cuttingWidthMeters'] = 0.0;
$disabledState = MowingAnalyticsReducer::update(
    MowingAnalyticsReducer::initialState(),
    $scene,
    $start + 400,
    $disabledOptions
);
$disabled = MowingAnalyticsReducer::project(
    $disabledState,
    $geometryKey,
    $start + 400,
    $disabledOptions
);
assertMowingAnalytics(
    $disabled['contracts']['geometricCoverage']['state']
        === 'disabled-missing-cutting-width'
        && $disabled['zones'][0]['latestRun']['estimatedArea'] === null
        && $disabled['zones'][0]['latestRunCoveragePercent'] === null,
    'Uncalibrated geometric coverage did not fail closed.'
);

$serialized = MowingAnalyticsReducer::serializeState($stateAgain);
assertMowingAnalytics(
    MowingAnalyticsReducer::serializeState(
        MowingAnalyticsReducer::restoreState($serialized)
    ) === $serialized,
    'Mowing analytics serialization is not stable.'
);

$corrupted = json_decode($serialized, true, 64, JSON_THROW_ON_ERROR);
$corrupted['revisions'][0]['days'][0]['subareas'][0]['lastMowedAt'] =
    'invalid';
assertMowingAnalyticsRejected(
    static fn (): array => MowingAnalyticsReducer::restoreState(
        json_encode($corrupted, JSON_THROW_ON_ERROR)
    ),
    'A corrupted retained subarea timestamp was accepted.'
);

// A multi-kilometre retained pass must not exhaust the budget by resampling.
$longScene = $scene;
$longSegment = $scene['path']['segments'][0];
$longSegment['points'] = [];
for ($index = 0; $index < 600; ++$index) {
    $longSegment['points'][] = array_replace(
        $scene['path']['segments'][0]['points'][0],
        [
            'localX' => $index % 2 === 0 ? 1.0 : 9.0,
            'receivedAt' => $start + $index * 6,
            'sourceTimestamp' => $start + $index * 6,
        ]
    );
}
$longScene['path']['segments'] = [$longSegment];
$fineOptions = array_replace($options, [
    'cuttingWidthMeters' => 0.43,
    'coverageCellSizeMeters' => 0.1,
]);
$longState = MowingAnalyticsReducer::update(
    MowingAnalyticsReducer::initialState(),
    $longScene,
    $start + 3600,
    $fineOptions
);
$longProjection = MowingAnalyticsReducer::project(
    $longState,
    $geometryKey,
    $start + 3600,
    $fineOptions
);
assertMowingAnalytics(
    $longProjection['latestRun']['coverageTruncated'] === false
        && abs($longProjection['latestRun']['estimatedArea'] - 3.2) < 0.00001
        && $longProjection['latestRun']['distanceMeters'] === 4792.0,
    'Long retained pass exhausted the budget or counted overlapping cells twice.'
);
$tinyOptions = array_replace($fineOptions, ['coverageCellSizeMeters' => 0.05]);
$limitedState = MowingAnalyticsReducer::update(
    MowingAnalyticsReducer::initialState(),
    $scene,
    $start + 400,
    $tinyOptions
);
$limitedState = MowingAnalyticsReducer::update(
    $limitedState,
    $longScene,
    $start + 3600,
    $tinyOptions
);
$limited = MowingAnalyticsReducer::project(
    $limitedState,
    $geometryKey,
    $start + 3600,
    $tinyOptions
);
assertMowingAnalytics(
    $limited['latestRun']['coverageTruncated'] === true
        && $limited['latestRun']['estimatedArea'] === null
        && $limited['zones'][0]['todayEstimatedArea'] === null
        && $limited['zones'][0]['weekEstimatedArea'] === null
        && $limited['zones'][0]['monthEstimatedArea'] === null
        && $limited['subareas'][0]['weekEstimatedArea'] === null,
    'An incomplete snapshot retained a misleading complete area.'
);
assertMowingAnalytics(
    MowingAnalyticsReducer::restoreState(
        MowingAnalyticsReducer::serializeState($limitedState)
    ) === $limitedState,
    'Incomplete coverage evidence did not survive serialization.'
);

// Compare scanline rasterization against an independent cell-center oracle.
$coverageMethod = new ReflectionMethod(MowingAnalyticsReducer::class, 'coverageCells');
$rasterPolicy = [
    'metersPerLocalUnit' => 1.0,
    'cuttingWidthMeters' => 0.8,
    'coverageCellSizeMeters' => 0.2,
];
$rasterZone = [
    'ring' => [[-10.0, -10.0], [10.0, -10.0], [10.0, 10.0], [-10.0, 10.0]],
    'obstacleIndexes' => [1],
];
$rasterObstacles = [['ring' => [
    [-0.4, -0.4], [0.4, -0.4], [0.4, 0.4], [-0.4, 0.4],
]]];
foreach ([[3.0, 0.0], [0.0, 3.0], [3.0, 2.0], [-3.0, 2.0]] as $end) {
    $left = ['localX' => -1.03, 'localY' => -1.07];
    $right = ['localX' => $end[0], 'localY' => $end[1]];
    $count = 0;
    $truncated = false;
    $actual = $coverageMethod->invokeArgs(null, [
        $left, $right, $rasterZone, $rasterObstacles, $rasterPolicy,
        &$count, &$truncated,
    ]);
    $expected = [];
    $dx = $right['localX'] - $left['localX'];
    $dy = $right['localY'] - $left['localY'];
    $length = hypot($dx, $dy);
    for ($ix = -25; $ix < 25; ++$ix) {
        for ($iy = -25; $iy < 25; ++$iy) {
            $x = ($ix + 0.5) * 0.2;
            $y = ($iy + 0.5) * 0.2;
            $along = (($x - $left['localX']) * $dx
                + ($y - $left['localY']) * $dy) / $length;
            $across = abs(($x - $left['localX']) * $dy
                - ($y - $left['localY']) * $dx) / $length;
            if (
                $along >= 0.0 && $along <= $length && $across <= 0.4
                && !(abs($x) < 0.4 && abs($y) < 0.4)
            ) {
                $expected[] = $ix . ':' . $iy;
            }
        }
    }
    $keys = array_keys($actual);
    sort($keys);
    sort($expected);
    assertMowingAnalytics(
        !$truncated && $keys === $expected,
        'Raster cells differ from swept-strip oracle (direction/obstacle/negative coordinates).'
    );
}

echo "Navimow mowing analytics reducer checks passed.\n";
