<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksEtaProjector;
use OwnTracksPositionMap\Prototype\OwnTracksDayWindow;
use OwnTracksPositionMap\Prototype\OwnTracksTrackCore;
use OwnTracksPositionMap\Prototype\OwnTracksWgs84;

require_once __DIR__ . '/bootstrap.php';

$springDay = OwnTracksDayWindow::fromLocalDate(
    '2024-03-31',
    'Europe/Berlin'
);
$autumnDay = OwnTracksDayWindow::fromLocalDate(
    '2024-10-27',
    'Europe/Berlin'
);
assertSameValue(23 * 60 * 60, $springDay['durationSeconds'], 'Spring DST day');
assertSameValue(25 * 60 * 60, $autumnDay['durationSeconds'], 'Autumn DST day');

$fixture = syntheticFixture();
$result = OwnTracksTrackCore::project(
    $fixture['positionRecordsNewestFirst'],
    $fixture['accuracyRecordsNewestFirst'],
    $fixture['query']
);
$expected = $fixture['expected'];
foreach (
    [
        'validObservations',
        'invalidRecords',
        'outsideWindowRecords',
        'fitObservationCount',
        'renderedPoints',
    ] as $key
) {
    assertSameValue(
        $expected[$key],
        $result['statistics'][$key],
        'Unexpected statistic ' . $key
    );
}

$strictUnknownAccuracyQuery = $fixture['query'];
$strictUnknownAccuracyQuery['renderMode'] = 'line-with-sampled-timestamps';
$strictUnknownAccuracy = OwnTracksTrackCore::project(
    $fixture['positionRecordsNewestFirst'],
    [],
    $strictUnknownAccuracyQuery
);
assertSameValue(
    0,
    $strictUnknownAccuracy['statistics']['renderedPoints'],
    'Unknown accuracy must remain excluded unless explicitly enabled'
);
$legacyUnknownAccuracyQuery = $strictUnknownAccuracyQuery;
$legacyUnknownAccuracyQuery['allowUnknownAccuracyForLine'] = true;
$legacyUnknownAccuracy = OwnTracksTrackCore::project(
    $fixture['positionRecordsNewestFirst'],
    [],
    $legacyUnknownAccuracyQuery
);
assertTrue(
    $legacyUnknownAccuracy['statistics']['renderedPoints'] > 0,
    'Explicit legacy accuracy policy did not retain historical points'
);
assertSameValue(
    $legacyUnknownAccuracy['statistics']['renderedPoints'],
    $legacyUnknownAccuracy['statistics']['renderedUnverifiedPoints'],
    'Historical points were not marked as unverified'
);

$clockSkewQuery = $fixture['query'];
$clockSkewQuery['from'] = 1704070000;
$clockSkewQuery['to'] = 1704071000;
$clockSkewQuery['maxRenderedPoints'] = 10;
$clockSkewQuery['maximumSourceClockLeadSeconds'] = 5;
$clockSkewQuery['allowUnknownAccuracyForLine'] = true;
$toleratedClockSkew = OwnTracksTrackCore::project(
    [[
        'TimeStamp' => 1704070495,
        'Value' => '{"tst":1704070500,"lat":10.0,"lon":20.0}',
    ]],
    [],
    $clockSkewQuery
);
assertSameValue(
    1,
    $toleratedClockSkew['statistics']['renderedPoints'],
    'A five-second source clock lead must remain line eligible'
);
assertTrue(
    in_array(
        'source-clock-skew-tolerated',
        $toleratedClockSkew['etaEvidence'][0]['qualityFlags'],
        true
    ),
    'Tolerated source clock skew was not marked explicitly'
);
assertTrue(
    !in_array(
        'source-time-ahead',
        $toleratedClockSkew['etaEvidence'][0]['qualityFlags'],
        true
    ),
    'Boundary clock skew was incorrectly rejected'
);

$rejectedClockSkew = OwnTracksTrackCore::project(
    [[
        'TimeStamp' => 1704070494,
        'Value' => '{"tst":1704070500,"lat":10.0,"lon":20.0}',
    ]],
    [],
    $clockSkewQuery
);
assertSameValue(
    0,
    $rejectedClockSkew['statistics']['renderedPoints'],
    'A six-second source clock lead must remain excluded'
);
assertTrue(
    in_array(
        'source-time-ahead',
        $rejectedClockSkew['etaEvidence'][0]['qualityFlags'],
        true
    ),
    'Excessive source clock lead was not marked'
);
foreach (
    [
        'south' => 'fitSouth',
        'west' => 'fitWest',
        'north' => 'fitNorth',
        'east' => 'fitEast',
    ] as $bound => $fixtureKey
) {
    assertTrue(
        abs($result['fitBounds'][$bound] - $expected[$fixtureKey]) < 0.000001,
        'Unexpected fit bound ' . $bound
    );
}
assertTrue(
    $result['statistics']['renderBudgetReached'],
    'Synthetic result must exercise the render budget.'
);
assertTrue(
    $result['statistics']['fitObservationCount']
        > $result['statistics']['renderedPoints'],
    'Fit-all must use more than the sampled marker set.'
);
assertTrue(
    OwnTracksTrackCore::isSuperseded(7, 8),
    'An older selection generation must be rejected.'
);
assertTrue(
    !OwnTracksTrackCore::isSuperseded(7, 7),
    'The active selection generation must be accepted.'
);

$flags = [];
foreach ($result['etaEvidence'] as $observation) {
    $flags = array_merge($flags, $observation['qualityFlags']);
}
foreach (
    [
        'delayed-reception',
        'out-of-order',
        'accuracy-poor',
        'gap-before',
    ] as $expectedFlag
) {
    assertTrue(
        in_array($expectedFlag, $flags, true),
        'Missing quality flag ' . $expectedFlag
    );
}

foreach (
    [
        'timestamp-points',
        'segmented-line',
        'line-with-sampled-timestamps',
    ] as $mode
) {
    $query = $fixture['query'];
    $query['renderMode'] = $mode;
    $modeResult = OwnTracksTrackCore::project(
        $fixture['positionRecordsNewestFirst'],
        $fixture['accuracyRecordsNewestFirst'],
        $query
    );
    assertSameValue($mode, $modeResult['render']['mode'], 'Render mode changed');
    assertTrue(
        count($modeResult['render']['points']) <= $query['maxRenderedPoints'],
        'Render point bound exceeded'
    );
}

$antimeridian = OwnTracksWgs84::bounds([
    ['latitude' => 1.0, 'longitude' => 179.5],
    ['latitude' => 2.0, 'longitude' => -179.5],
]);
assertTrue(
    $antimeridian !== null && $antimeridian['crossesAntimeridian'],
    'Fit bounds must choose the minimal antimeridian interval.'
);

$etaPolicy = [
    'evaluatedAt' => 1704075060,
    'allowGeodesicFallback' => true,
];
$diagnosticEta = OwnTracksEtaProjector::project(
    $result['etaEvidence'],
    $fixture['target'],
    $etaPolicy
);
assertSameValue('available', $diagnosticEta['status'], 'Diagnostic ETA status');
assertSameValue(
    'geodesic-observed-speed',
    $diagnosticEta['strategy'],
    'Diagnostic ETA strategy'
);
assertTrue(!$diagnosticEta['routeAware'], 'Diagnostic ETA is not route-aware');

$motionAwareEta = OwnTracksEtaProjector::project(
    $result['etaEvidence'],
    $fixture['target'],
    $etaPolicy + [
        'closingSpeedMetersPerSecond' => 2.5,
        'closingSpeedObservedAt' => 1704075000,
        'closingSpeedEvidenceCount' => 4,
    ]
);
assertSameValue(
    'geodesic-target-closing-speed',
    $motionAwareEta['strategy'],
    'Motion-aware ETA strategy'
);
assertSameValue(
    4,
    $motionAwareEta['evidenceSampleCount'],
    'Motion-aware ETA evidence count'
);

$routeTarget = $fixture['target'];
$routeTarget['routeEstimate'] = [
    'authorityKey' => 'synthetic-router',
    'estimatedAt' => 1704075000,
    'etaSeconds' => 900,
    'remainingDistanceMeters' => 4200.0,
];
$routeEta = OwnTracksEtaProjector::project(
    $result['etaEvidence'],
    $routeTarget,
    $etaPolicy
);
assertSameValue('external-route', $routeEta['strategy'], 'Route ETA strategy');
assertTrue($routeEta['routeAware'], 'External ETA must be route-aware');
assertSameValue(840, $routeEta['etaSeconds'], 'Route ETA age adjustment');

$missingEta = OwnTracksEtaProjector::project(
    $result['etaEvidence'],
    null,
    $etaPolicy
);
assertSameValue('unavailable', $missingEta['status'], 'Missing target status');
assertSameValue('target-missing', $missingEta['reason'], 'Missing target reason');

$encoded = json_encode([$fixture, $result], JSON_THROW_ON_ERROR);
foreach (['objectId', 'trackerId', 'privateTopic', 'realCoordinate'] as $forbidden) {
    assertTrue(
        stripos($encoded, $forbidden) === false,
        'Fixture contains forbidden installation marker ' . $forbidden
    );
}

fwrite(STDOUT, "OwnTracks offline core tests passed.\n");
