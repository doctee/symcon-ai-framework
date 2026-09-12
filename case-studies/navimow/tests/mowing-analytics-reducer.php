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
        'vehicleStateCode' => 1,
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
            'vehicleStateCode' => 1,
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
            'vehicleStateCode' => 4,
            'startedAt' => $start + 300,
            'endedAt' => $start + 360,
            'breakReason' => 'vehicle-state-change',
            'pathLengthLocal' => 8.0,
            'points' => [
                $runningPoints[0] + [
                    'receivedAt' => $start + 300,
                    'sourceTimestamp' => $start + 300,
                    'vehicleStateCode' => 4,
                ],
                $runningPoints[4] + [
                    'receivedAt' => $start + 360,
                    'sourceTimestamp' => $start + 360,
                    'vehicleStateCode' => 4,
                ],
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
        && $scene['path']['segments'][0]['vehicleStateCode'] === 1
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
$projection = MowingAnalyticsReducer::project(
    $state,
    $geometryKey,
    $start + 400,
    $options
);
$zone = $projection['zones'][0];
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

echo "Navimow mowing analytics reducer checks passed.\n";
