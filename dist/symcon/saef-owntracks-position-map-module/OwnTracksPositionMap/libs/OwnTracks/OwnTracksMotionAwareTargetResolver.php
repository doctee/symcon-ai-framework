<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

/**
 * Case-study-local, provider-neutral resolver for a small private target set.
 *
 * It deliberately uses target-closing speed only for destination selection.
 * Route-aware ETA remains the responsibility of a separately owned provider.
 */
final class OwnTracksMotionAwareTargetResolver
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/D';
    private const MAXIMUM_TARGET_DISTANCE_METERS = 100000.0;
    private const ACTIVITY_BY_VALUE = [
        0 => 'unknown',
        1 => 'stationary',
        2 => 'walking',
        3 => 'running',
        4 => 'cycling',
        5 => 'automotive',
    ];
    private const SPEED_RANGES = [
        'unknown' => [0.3, 70.0],
        'stationary' => [0.0, 1.5],
        'walking' => [0.3, 3.5],
        'running' => [1.0, 8.0],
        'cycling' => [1.5, 20.0],
        'automotive' => [2.0, 70.0],
    ];

    /**
     * @param list<array<string, mixed>> $observations
     * @param list<array<string, mixed>> $activities
     * @param list<array<string, mixed>> $targets
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function resolve(
        array $observations,
        array $activities,
        array $targets,
        array $options
    ): array {
        $policy = self::policy($options);
        $targets = self::targets($targets);
        $positions = self::positions($observations, $policy);
        if ($positions === []) {
            return self::unavailable('position-evidence-insufficient');
        }

        $latest = $positions[count($positions) - 1];
        $latestAt = $latest['observedAt'];
        $age = $policy['evaluatedAt'] - $latestAt;
        if ($age < 0 || $age > $policy['maximumPositionAgeSeconds']) {
            return self::unavailable('current-position-stale');
        }

        $eligibleTargets = [];
        foreach ($targets as $target) {
            $distance = OwnTracksWgs84::distanceMeters(
                $latest['coordinate'],
                [
                    'latitude' => $target['latitude'],
                    'longitude' => $target['longitude'],
                ]
            );
            if ($distance < self::MAXIMUM_TARGET_DISTANCE_METERS) {
                $eligibleTargets[] = $target;
            }
        }
        if ($eligibleTargets === []) {
            return self::unavailable(
                'outside-target-radius',
                'unknown',
                null,
                $latestAt
            );
        }
        $targets = $eligibleTargets;

        $activity = self::activityAt($activities, $latestAt, $policy);
        $segments = self::segments($positions, $targets);
        if (count($segments) < $policy['minimumSegmentCount']) {
            return self::unavailable(
                'movement-evidence-insufficient',
                $activity['mode']
            );
        }

        $groundSpeeds = array_column($segments, 'groundSpeed');
        $groundSpeed = self::median($groundSpeeds);
        [$minimumSpeed, $maximumSpeed] = self::SPEED_RANGES[$activity['mode']];
        if ($activity['mode'] === 'stationary') {
            return self::unavailable('activity-stationary', $activity['mode']);
        }
        if (
            $groundSpeed < $minimumSpeed
            || $groundSpeed > $maximumSpeed
        ) {
            return self::unavailable(
                'activity-speed-conflict',
                $activity['mode'],
                $groundSpeed
            );
        }

        $ranked = [];
        foreach ($targets as $target) {
            $targetSegments = [];
            foreach ($segments as $segment) {
                $targetSegments[] = $segment['targets'][$target['targetKey']];
            }
            $closingSpeeds = array_column($targetSegments, 'closingSpeed');
            $positiveCount = count(array_filter(
                $closingSpeeds,
                static fn (float $speed): bool => $speed > 0.0
            ));
            $approachRatio = $positiveCount / count($closingSpeeds);
            $closingSpeed = self::median($closingSpeeds);
            $firstDistance = $targetSegments[0]['previousDistance'];
            $remainingDistance = $targetSegments[count($targetSegments) - 1]
                ['currentDistance'];
            $netApproach = $firstDistance - $remainingDistance;
            $efficiency = max(0.0, min(1.0, $closingSpeed / $groundSpeed));
            $progress = max(0.0, min(
                1.0,
                $netApproach / max(1000.0, $firstDistance)
            ));
            $score = 0.55 * $approachRatio
                + 0.35 * $efficiency
                + 0.10 * $progress;
            $ranked[] = [
                'targetKey' => $target['targetKey'],
                'target' => $target,
                'score' => $score,
                'approachRatio' => $approachRatio,
                'closingSpeedMetersPerSecond' => $closingSpeed,
                'netApproachMeters' => $netApproach,
                'remainingDistanceMeters' => $remainingDistance,
            ];
        }
        usort(
            $ranked,
            static fn (array $left, array $right): int =>
                $right['score'] <=> $left['score']
        );
        $best = $ranked[0];
        $runnerUp = $ranked[1] ?? null;
        $margin = $runnerUp === null
            ? $best['score']
            : $best['score'] - $runnerUp['score'];
        if (
            $best['score'] < $policy['minimumScore']
            || $margin < $policy['minimumScoreMargin']
            || $best['approachRatio'] < $policy['minimumApproachRatio']
            || $best['netApproachMeters'] < $policy['minimumNetApproachMeters']
            || $best['closingSpeedMetersPerSecond'] <= 0.0
        ) {
            return [
                'status' => 'ambiguous',
                'reason' => 'target-confidence-insufficient',
                'targetKey' => null,
                'basisObservedAt' => $latestAt,
                'motionMode' => $activity['mode'],
                'motionObservedAt' => $activity['observedAt'],
                'groundSpeedMetersPerSecond' => $groundSpeed,
                'closingSpeedMetersPerSecond' => null,
                'remainingDistanceMeters' => null,
                'confidence' => $best['score'],
                'confidenceMargin' => $margin,
                'evidenceSegmentCount' => count($segments),
            ];
        }

        return [
            'status' => 'selected',
            'reason' => 'target-approach-supported',
            'targetKey' => $best['targetKey'],
            'target' => $best['target'],
            'basisObservedAt' => $latestAt,
            'motionMode' => $activity['mode'],
            'motionObservedAt' => $activity['observedAt'],
            'groundSpeedMetersPerSecond' => $groundSpeed,
            'closingSpeedMetersPerSecond' =>
                $best['closingSpeedMetersPerSecond'],
            'remainingDistanceMeters' => $best['remainingDistanceMeters'],
            'confidence' => $best['score'],
            'confidenceMargin' => $margin,
            'evidenceSegmentCount' => count($segments),
        ];
    }

    /** @param array<string, mixed> $options */
    private static function policy(array $options): array
    {
        $policy = [
            'evaluatedAt' => $options['evaluatedAt'] ?? null,
            'lookbackSeconds' => $options['lookbackSeconds'] ?? 30 * 60,
            'maximumPositionAgeSeconds' =>
                $options['maximumPositionAgeSeconds'] ?? 15 * 60,
            'maximumActivityAgeSeconds' =>
                $options['maximumActivityAgeSeconds'] ?? 30 * 60,
            'minimumSegmentCount' => $options['minimumSegmentCount'] ?? 3,
            'minimumApproachRatio' =>
                $options['minimumApproachRatio'] ?? 0.65,
            'minimumNetApproachMeters' =>
                $options['minimumNetApproachMeters'] ?? 100.0,
            'minimumScore' => $options['minimumScore'] ?? 0.60,
            'minimumScoreMargin' =>
                $options['minimumScoreMargin'] ?? 0.15,
        ];
        if (
            !self::boundedInteger($policy['evaluatedAt'], 1, PHP_INT_MAX)
            || !self::boundedInteger($policy['lookbackSeconds'], 1, 86400)
            || !self::boundedInteger(
                $policy['maximumPositionAgeSeconds'],
                1,
                86400
            )
            || !self::boundedInteger(
                $policy['maximumActivityAgeSeconds'],
                1,
                86400
            )
            || !self::boundedInteger($policy['minimumSegmentCount'], 1, 100)
            || !self::boundedNumber($policy['minimumApproachRatio'], 0.0, 1.0)
            || !self::boundedNumber(
                $policy['minimumNetApproachMeters'],
                0.0,
                1000000.0
            )
            || !self::boundedNumber($policy['minimumScore'], 0.0, 1.0)
            || !self::boundedNumber(
                $policy['minimumScoreMargin'],
                0.0,
                1.0
            )
        ) {
            throw new InvalidArgumentException('Target resolver policy is invalid.');
        }

        return $policy;
    }

    /**
     * @param list<array<string, mixed>> $targets
     * @return list<array<string, mixed>>
     */
    private static function targets(array $targets): array
    {
        if (count($targets) < 2 || count($targets) > 8) {
            throw new InvalidArgumentException('Target set size is invalid.');
        }
        $normalized = [];
        $keys = [];
        foreach ($targets as $target) {
            $key = $target['targetKey'] ?? null;
            if (
                !is_string($key)
                || preg_match(self::KEY_PATTERN, $key) !== 1
                || isset($keys[$key])
            ) {
                throw new InvalidArgumentException('Target key is invalid.');
            }
            $coordinate = OwnTracksWgs84::coordinate(
                $target['latitude'] ?? null,
                $target['longitude'] ?? null
            );
            $keys[$key] = true;
            $normalized[] = [
                'targetKey' => $key,
                'latitude' => $coordinate['latitude'],
                'longitude' => $coordinate['longitude'],
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $observations
     * @param array<string, mixed> $policy
     * @return list<array{observedAt: int, coordinate: array<string, float>}>
     */
    private static function positions(array $observations, array $policy): array
    {
        $positions = [];
        foreach ($observations as $observation) {
            if (($observation['lineEligible'] ?? false) !== true) {
                continue;
            }
            $observedAt = $observation['observedAt'] ?? null;
            if (
                !is_int($observedAt)
                || $observedAt < $policy['evaluatedAt'] - $policy['lookbackSeconds']
                || $observedAt > $policy['evaluatedAt']
            ) {
                continue;
            }
            $positions[] = [
                'observedAt' => $observedAt,
                'coordinate' => OwnTracksWgs84::coordinate(
                    $observation['latitudeDegrees'] ?? null,
                    $observation['longitudeDegrees'] ?? null
                ),
            ];
        }
        usort(
            $positions,
            static fn (array $left, array $right): int =>
                $left['observedAt'] <=> $right['observedAt']
        );

        return $positions;
    }

    /**
     * Change-only activity archives are carried forward only within a bounded
     * age. A future activity must never describe an earlier position.
     *
     * @param list<array<string, mixed>> $activities
     * @param array<string, mixed> $policy
     * @return array{mode: string, observedAt: int|null}
     */
    private static function activityAt(
        array $activities,
        int $positionAt,
        array $policy
    ): array {
        $selectedAt = null;
        $selectedMode = 'unknown';
        foreach ($activities as $activity) {
            $observedAt = $activity['observedAt'] ?? null;
            $value = $activity['value'] ?? null;
            if (
                !is_int($observedAt)
                || $observedAt > $positionAt
                || !is_int($value)
                || !isset(self::ACTIVITY_BY_VALUE[$value])
                || ($selectedAt !== null && $observedAt <= $selectedAt)
            ) {
                continue;
            }
            $selectedAt = $observedAt;
            $selectedMode = self::ACTIVITY_BY_VALUE[$value];
        }
        if (
            $selectedAt === null
            || $positionAt - $selectedAt > $policy['maximumActivityAgeSeconds']
        ) {
            return ['mode' => 'unknown', 'observedAt' => null];
        }

        return ['mode' => $selectedMode, 'observedAt' => $selectedAt];
    }

    /**
     * @param list<array{observedAt: int, coordinate: array<string, float>}> $positions
     * @param list<array<string, mixed>> $targets
     * @return list<array<string, mixed>>
     */
    private static function segments(array $positions, array $targets): array
    {
        $segments = [];
        for ($index = 1; $index < count($positions); $index++) {
            $previous = $positions[$index - 1];
            $current = $positions[$index];
            $duration = $current['observedAt'] - $previous['observedAt'];
            if ($duration <= 0) {
                continue;
            }
            $distance = OwnTracksWgs84::distanceMeters(
                $previous['coordinate'],
                $current['coordinate']
            );
            $groundSpeed = $distance / $duration;
            if ($groundSpeed <= 0.0 || $groundSpeed > 70.0) {
                continue;
            }
            $targetEvidence = [];
            foreach ($targets as $target) {
                $coordinate = [
                    'latitude' => $target['latitude'],
                    'longitude' => $target['longitude'],
                ];
                $previousDistance = OwnTracksWgs84::distanceMeters(
                    $previous['coordinate'],
                    $coordinate
                );
                $currentDistance = OwnTracksWgs84::distanceMeters(
                    $current['coordinate'],
                    $coordinate
                );
                $targetEvidence[$target['targetKey']] = [
                    'previousDistance' => $previousDistance,
                    'currentDistance' => $currentDistance,
                    'closingSpeed' =>
                        ($previousDistance - $currentDistance) / $duration,
                ];
            }
            $segments[] = [
                'groundSpeed' => $groundSpeed,
                'targets' => $targetEvidence,
            ];
        }

        return $segments;
    }

    /** @param list<float> $values */
    private static function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2.0;
    }

    /** @return array<string, mixed> */
    private static function unavailable(
        string $reason,
        string $motionMode = 'unknown',
        ?float $groundSpeed = null,
        ?int $basisObservedAt = null
    ): array {
        return [
            'status' => 'unavailable',
            'reason' => $reason,
            'targetKey' => null,
            'basisObservedAt' => $basisObservedAt,
            'motionMode' => $motionMode,
            'motionObservedAt' => null,
            'groundSpeedMetersPerSecond' => $groundSpeed,
            'closingSpeedMetersPerSecond' => null,
            'remainingDistanceMeters' => null,
            'confidence' => null,
            'confidenceMargin' => null,
            'evidenceSegmentCount' => 0,
        ];
    }

    private static function boundedInteger(
        mixed $value,
        int $minimum,
        int $maximum
    ): bool {
        return is_int($value) && $value >= $minimum && $value <= $maximum;
    }

    private static function boundedNumber(
        mixed $value,
        float $minimum,
        float $maximum
    ): bool {
        return (is_int($value) || is_float($value))
            && is_finite((float) $value)
            && (float) $value >= $minimum
            && (float) $value <= $maximum;
    }
}
