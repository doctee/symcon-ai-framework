<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksMotionAwareTargetResolver;

require_once __DIR__ . '/bootstrap.php';

/** @return list<array<string, mixed>> */
function targetResolverPositions(
    float $fromLatitude,
    float $fromLongitude,
    float $toLatitude,
    float $toLongitude,
    int $startAt,
    int $stepSeconds = 60
): array {
    $positions = [];
    for ($index = 0; $index < 6; $index++) {
        $fraction = $index / 5;
        $positions[] = [
            'observedAt' => $startAt + $index * $stepSeconds,
            'latitudeDegrees' =>
                $fromLatitude + ($toLatitude - $fromLatitude) * $fraction,
            'longitudeDegrees' =>
                $fromLongitude + ($toLongitude - $fromLongitude) * $fraction,
            'lineEligible' => true,
        ];
    }

    return $positions;
}

$evaluatedAt = 2_000_000_300;
$targets = [
    ['targetKey' => 'north', 'latitude' => 49.5, 'longitude' => 10.0],
    ['targetKey' => 'south', 'latitude' => 48.5, 'longitude' => 10.0],
];
$policy = [
    'evaluatedAt' => $evaluatedAt,
    'minimumNetApproachMeters' => 50.0,
];

$towardNorth = targetResolverPositions(
    49.0,
    10.0,
    49.05,
    10.0,
    $evaluatedAt - 300
);
$automotive = OwnTracksMotionAwareTargetResolver::resolve(
    $towardNorth,
    [['observedAt' => $evaluatedAt - 360, 'value' => 5]],
    $targets,
    $policy
);
assertSameValue('selected', $automotive['status'], 'Automotive result');
assertSameValue('north', $automotive['targetKey'], 'North target');
assertSameValue('automotive', $automotive['motionMode'], 'Automotive mode');
assertTrue(
    $automotive['closingSpeedMetersPerSecond'] > 0.0,
    'Selected target must have positive closing speed'
);

$towardSouth = targetResolverPositions(
    49.0,
    10.0,
    48.95,
    10.0,
    $evaluatedAt - 300
);
$south = OwnTracksMotionAwareTargetResolver::resolve(
    $towardSouth,
    [['observedAt' => $evaluatedAt - 360, 'value' => 5]],
    $targets,
    $policy
);
assertSameValue('south', $south['targetKey'], 'South target');

$stationary = OwnTracksMotionAwareTargetResolver::resolve(
    $towardNorth,
    [['observedAt' => $evaluatedAt - 360, 'value' => 1]],
    $targets,
    $policy
);
assertSameValue('unavailable', $stationary['status'], 'Stationary result');
assertSameValue('activity-stationary', $stationary['reason'], 'Stationary reason');

$walkingConflict = OwnTracksMotionAwareTargetResolver::resolve(
    $towardNorth,
    [['observedAt' => $evaluatedAt - 360, 'value' => 2]],
    $targets,
    $policy
);
assertSameValue(
    'activity-speed-conflict',
    $walkingConflict['reason'],
    'Walking cannot validate automotive speed'
);

$staleActivity = OwnTracksMotionAwareTargetResolver::resolve(
    $towardNorth,
    [['observedAt' => $evaluatedAt - 4000, 'value' => 1]],
    $targets,
    $policy
);
assertSameValue('selected', $staleActivity['status'], 'Stale activity fallback');
assertSameValue('unknown', $staleActivity['motionMode'], 'Stale mode is unknown');

$sidewaysTargets = [
    ['targetKey' => 'east', 'latitude' => 49.0, 'longitude' => 11.0],
    ['targetKey' => 'west', 'latitude' => 49.0, 'longitude' => 9.0],
];
$sideways = targetResolverPositions(
    48.95,
    10.0,
    49.05,
    10.0,
    $evaluatedAt - 300
);
$ambiguous = OwnTracksMotionAwareTargetResolver::resolve(
    $sideways,
    [['observedAt' => $evaluatedAt - 360, 'value' => 5]],
    $sidewaysTargets,
    $policy
);
assertSameValue('ambiguous', $ambiguous['status'], 'Sideways movement result');
assertSameValue(null, $ambiguous['targetKey'], 'Ambiguous target');

$futureActivity = OwnTracksMotionAwareTargetResolver::resolve(
    $towardNorth,
    [['observedAt' => $evaluatedAt + 1, 'value' => 1]],
    $targets,
    $policy
);
assertSameValue('unknown', $futureActivity['motionMode'], 'Future activity ignored');

$earthRadiusMeters = 6371008.8;
$oneHundredKilometresLatitudeDegrees = rad2deg(
    100000.0 / $earthRadiusMeters
);
$radiusPositions = targetResolverPositions(
    0.0,
    0.0,
    0.05,
    0.0,
    $evaluatedAt - 300
);
$atBoundary = OwnTracksMotionAwareTargetResolver::resolve(
    $radiusPositions,
    [['observedAt' => $evaluatedAt - 360, 'value' => 5]],
    [
        [
            'targetKey' => 'boundary',
            'latitude' => 0.05 + $oneHundredKilometresLatitudeDegrees,
            'longitude' => 0.0,
        ],
        ['targetKey' => 'far', 'latitude' => -2.0, 'longitude' => 0.0],
    ],
    $policy
);
assertSameValue(
    'unavailable',
    $atBoundary['status'],
    'Exact 100 km boundary must be unavailable'
);
assertSameValue(
    'outside-target-radius',
    $atBoundary['reason'],
    'Exact 100 km boundary reason'
);
assertSameValue(
    $evaluatedAt,
    $atBoundary['basisObservedAt'],
    'Radius rejection basis time'
);

$insideBoundary = OwnTracksMotionAwareTargetResolver::resolve(
    $radiusPositions,
    [['observedAt' => $evaluatedAt - 360, 'value' => 5]],
    [
        [
            'targetKey' => 'inside',
            'latitude' => 0.05 + $oneHundredKilometresLatitudeDegrees - 0.00001,
            'longitude' => 0.0,
        ],
        ['targetKey' => 'far', 'latitude' => -2.0, 'longitude' => 0.0],
    ],
    $policy
);
assertSameValue(
    'selected',
    $insideBoundary['status'],
    'Target just inside 100 km must remain eligible'
);
assertSameValue(
    'inside',
    $insideBoundary['targetKey'],
    'Only eligible target may be selected'
);

echo "OwnTracks motion-aware target resolver tests passed.\n";
