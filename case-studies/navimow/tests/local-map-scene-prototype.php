<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../candidate/MqttPathSegmenter.php';
require_once __DIR__ . '/../candidate/ZoneStatisticsReducer.php';
require_once __DIR__ . '/../candidate/LocalMapSceneProjector.php';

use Navimow\Prototype\LocalMapSceneProjector;
use Navimow\Prototype\MqttPathSegmenter;
use Navimow\Prototype\ZoneStatisticsReducer;

function assertLocalMapScene(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param callable(): void $operation */
function assertLocalMapSceneRejected(
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

$fixture = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../fixtures/map/local-map-scene-synthetic.json'
    ),
    true,
    64,
    JSON_THROW_ON_ERROR
);
assertLocalMapScene(is_array($fixture), 'Local-map fixture is invalid.');

$path = MqttPathSegmenter::build(
    $fixture['points'],
    $fixture['ledgerProjection']['passes'],
    [
        'joinWindowSeconds' => 30,
        'maximumGapSeconds' => 120,
        'maximumStepDistanceLocal' => 100.0,
        'minimumRetainSeconds' => 0,
        'minimumRetainDistanceLocal' => 0.01,
    ]
);
$statistics = ZoneStatisticsReducer::reduce(
    $fixture['ledgerProjection'],
    $fixture['configuredZoneAreas']
);
$geometryKey = SAEF_CreateConfigurationHash($fixture['geometry']);
$revision = [
    'currentGeometryKey' => $geometryKey,
    'acceptedGeometryKey' => $geometryKey,
    'pathGeometryKey' => $geometryKey,
    'statisticsGeometryKey' => $geometryKey,
    'frameCorrelationApproved' => true,
];

$scene = LocalMapSceneProjector::build(
    $fixture['geometry'],
    $path,
    $statistics,
    $fixture['bindings'],
    $revision
);
assertLocalMapScene(
    $scene['formatVersion'] === 1
        && $scene['coordinateFrame'] === 'navimow-local-map-candidate'
        && $scene['revision']['state'] === 'accepted'
        && $scene['revision']['pathCompatible'] === true
        && $scene['revision']['statisticsCompatible'] === true
        && count($scene['zones']) === 4
        && count($scene['obstacles']) === 3,
    'Accepted local-map scene contract differs.'
);
assertLocalMapScene(
    $scene['viewport']['minimumX'] < -1.0
        && $scene['viewport']['maximumX'] > 40.0
        && $scene['viewport']['width'] > 40.0
        && $scene['viewport']['height'] > 40.0
        && $scene['viewport']['paddingLocal'] >= 0.75
        && $scene['viewport']['paddingLocal'] < 1.5,
    'Viewport does not contain geometry, station and retained path.'
);
assertLocalMapScene(
    $scene['overlapDiagnostics']['pairCount'] === 1
        && $scene['overlapDiagnostics']
            ['geometryOnlyAttributionUnambiguous'] === false
        && $scene['overlapDiagnostics']['pairs'][0]
            ['strictBoundaryCrossings'] === 2,
    'Synthetic zone overlap was not detected.'
);
assertLocalMapScene(
    array_column(
        array_column($scene['obstacles'], 'ownership'),
        'status'
    ) === ['single-zone', 'single-zone', 'single-zone'],
    'Obstacle ownership is not unique.'
);
assertLocalMapScene(
    $scene['path']['status'] === 'included'
        && $scene['path']['counters']['includedPointCount'] === 6
        && $scene['path']['counters']['taskAttributedPointCount'] === 4
        && $scene['path']['counters']['geometryFallbackPointCount'] === 0
        && $scene['path']['counters']['ambiguousPointCount'] === 1
        && $scene['path']['counters']['outsidePointCount'] === 1,
    'Task-first path attribution counters differ.'
);

$taskOverlapPoint = null;
foreach ($scene['path']['segments'] as $segment) {
    foreach ($segment['points'] as $point) {
        if (
            $point['localX'] === 9.0
            && $point['localY'] === 5.0
            && $point['attribution']['source'] === 'task'
        ) {
            $taskOverlapPoint = $point;
        }
    }
}
assertLocalMapScene(
    is_array($taskOverlapPoint)
        && $taskOverlapPoint['attribution']['zoneKey']
            === str_repeat('a', 64)
        && $taskOverlapPoint['attribution']['geometryCandidateCount'] === 2
        && $taskOverlapPoint['attribution']['geometryPlausible'] === true,
    'Task evidence did not resolve the geometric overlap.'
);

$zonesByKey = [];
foreach ($scene['zones'] as $zone) {
    if ($zone['zoneKey'] !== null) {
        $zonesByKey[$zone['zoneKey']] = $zone;
    }
}
assertLocalMapScene(
    $zonesByKey[str_repeat('a', 64)]
        ['denominatorMatchesStatistics'] === true
        && $zonesByKey[str_repeat('b', 64)]
            ['denominatorMatchesStatistics'] === false
        && $zonesByKey[str_repeat('c', 64)]
            ['denominatorMatchesStatistics'] === true
        && $scene['zones'][3]['zoneKey'] === null
        && $scene['zones'][3]['statistics'] === null,
    'Reported-area denominator or unassigned-zone gate differs.'
);

$changedGeometry = $fixture['geometry'];
$changedGeometry['zones'][2]['ring'][1][0] = 31.0;
$changedKey = SAEF_CreateConfigurationHash($changedGeometry);
$staleScene = LocalMapSceneProjector::build(
    $changedGeometry,
    $path,
    $statistics,
    $fixture['bindings'],
    [
        'currentGeometryKey' => $changedKey,
        'acceptedGeometryKey' => $geometryKey,
        'pathGeometryKey' => $geometryKey,
        'statisticsGeometryKey' => $geometryKey,
        'frameCorrelationApproved' => true,
    ]
);
assertLocalMapScene(
    $staleScene['revision']['state'] === 'candidate'
        && $staleScene['revision']['requiresReconciliation'] === true
        && $staleScene['path']['status'] === 'revision-mismatch'
        && $staleScene['path']['segments'] === []
        && array_filter(
            array_column($staleScene['zones'], 'statistics')
        ) === [],
    'Changed geometry mixed old path or statistics into the candidate scene.'
);

$frameBlocked = LocalMapSceneProjector::build(
    $fixture['geometry'],
    $path,
    $statistics,
    $fixture['bindings'],
    array_replace($revision, ['frameCorrelationApproved' => false])
);
assertLocalMapScene(
    $frameBlocked['path']['status'] === 'frame-not-approved'
        && $frameBlocked['path']['segments'] === [],
    'Unapproved coordinate-frame correlation exposed a path.'
);

assertLocalMapSceneRejected(
    static fn (): array => LocalMapSceneProjector::build(
        $fixture['geometry'],
        $path,
        $statistics,
        $fixture['bindings'],
        array_replace(
            $revision,
            ['currentGeometryKey' => str_repeat('f', 64)]
        )
    ),
    'A mismatched geometry fingerprint was accepted.'
);

$privacyProjection = json_encode(
    $scene,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
);
assertLocalMapScene(
    !str_contains($privacyProjection, 'PRIVATE_ZONE_ALIAS_A')
        && !str_contains($privacyProjection, 'PRIVATE_ZONE_ALIAS_B')
        && !str_contains($privacyProjection, 'PRIVATE_ZONE_ALIAS_C')
        && !str_contains($privacyProjection, 'DEVICE_')
        && !str_contains($privacyProjection, '/downlink/'),
    'Synthetic scene contains private installation data.'
);
assertLocalMapScene(
    $scene['contracts']['geometricCoveragePercent'] === 'not-implemented'
        && $scene['contracts']
            ['revisionMismatchDropsPathAndStatistics'] === true,
    'Prototype overstates geometric coverage or revision compatibility.'
);

echo "Navimow local-map scene prototype checks passed.\n";
