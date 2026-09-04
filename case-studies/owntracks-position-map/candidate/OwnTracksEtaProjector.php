<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

final class OwnTracksEtaProjector
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/D';
    private const MAX_ETA_SECONDS = 7 * 24 * 60 * 60;

    /**
     * @param list<array<string, mixed>> $observations
     * @param array<string, mixed>|null $target
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function project(
        array $observations,
        ?array $target,
        array $options
    ): array {
        $policy = self::policy($options);
        if ($target === null) {
            return self::unavailable('target-missing');
        }
        $target = self::target($target);

        $route = $target['routeEstimate'];
        if ($route !== null) {
            $routeResult = self::routeResult($route, $policy);
            if ($routeResult['status'] !== 'stale') {
                return $routeResult + ['targetKey' => $target['targetKey']];
            }
            if (!$policy['allowGeodesicFallback']) {
                return $routeResult + ['targetKey' => $target['targetKey']];
            }
        }

        if (!$policy['allowGeodesicFallback']) {
            return self::unavailable(
                'route-estimate-unavailable',
                $target['targetKey']
            );
        }

        return self::geodesicResult($observations, $target, $policy);
    }

    /** @param array<string, mixed> $options */
    private static function policy(array $options): array
    {
        $policy = [
            'evaluatedAt' => $options['evaluatedAt'] ?? null,
            'maximumRouteAgeSeconds' =>
                $options['maximumRouteAgeSeconds'] ?? 15 * 60,
            'maximumCurrentPositionAgeSeconds' =>
                $options['maximumCurrentPositionAgeSeconds'] ?? 15 * 60,
            'lookbackSeconds' => $options['lookbackSeconds'] ?? 30 * 60,
            'arrivalRadiusMeters' =>
                $options['arrivalRadiusMeters'] ?? 50.0,
            'minimumSpeedMetersPerSecond' =>
                $options['minimumSpeedMetersPerSecond'] ?? 0.5,
            'maximumSpeedMetersPerSecond' =>
                $options['maximumSpeedMetersPerSecond'] ?? 70.0,
            'allowGeodesicFallback' =>
                $options['allowGeodesicFallback'] ?? false,
            'closingSpeedMetersPerSecond' =>
                $options['closingSpeedMetersPerSecond'] ?? null,
            'closingSpeedObservedAt' =>
                $options['closingSpeedObservedAt'] ?? null,
            'closingSpeedEvidenceCount' =>
                $options['closingSpeedEvidenceCount'] ?? null,
        ];
        if (
            !is_int($policy['evaluatedAt'])
            || $policy['evaluatedAt'] <= 0
            || !self::boundedInteger(
                $policy['maximumRouteAgeSeconds'],
                1,
                24 * 60 * 60
            )
            || !self::boundedInteger(
                $policy['maximumCurrentPositionAgeSeconds'],
                1,
                24 * 60 * 60
            )
            || !self::boundedInteger(
                $policy['lookbackSeconds'],
                1,
                24 * 60 * 60
            )
            || !self::positiveFinite(
                $policy['arrivalRadiusMeters'],
                10000.0
            )
            || !self::positiveFinite(
                $policy['minimumSpeedMetersPerSecond'],
                1000.0
            )
            || !self::positiveFinite(
                $policy['maximumSpeedMetersPerSecond'],
                1000.0
            )
            || $policy['minimumSpeedMetersPerSecond']
                >= $policy['maximumSpeedMetersPerSecond']
            || !is_bool($policy['allowGeodesicFallback'])
            || !self::validClosingSpeedEvidence($policy)
        ) {
            throw new InvalidArgumentException('ETA policy is invalid.');
        }

        return $policy;
    }

    /** @param array<string, mixed> $target */
    private static function target(array $target): array
    {
        $targetKey = $target['targetKey'] ?? null;
        if (
            !is_string($targetKey)
            || preg_match(self::KEY_PATTERN, $targetKey) !== 1
        ) {
            throw new InvalidArgumentException('ETA target key is invalid.');
        }
        $coordinate = OwnTracksWgs84::coordinate(
            $target['latitude'] ?? null,
            $target['longitude'] ?? null
        );
        $route = $target['routeEstimate'] ?? null;
        if ($route !== null && !is_array($route)) {
            throw new InvalidArgumentException('Route estimate is invalid.');
        }

        return [
            'targetKey' => $targetKey,
            'coordinate' => $coordinate,
            'routeEstimate' => $route,
        ];
    }

    /**
     * @param array<string, mixed> $route
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private static function routeResult(array $route, array $policy): array
    {
        $authorityKey = $route['authorityKey'] ?? null;
        $estimatedAt = $route['estimatedAt'] ?? null;
        $etaSeconds = $route['etaSeconds'] ?? null;
        $distance = $route['remainingDistanceMeters'] ?? null;
        if (
            !is_string($authorityKey)
            || preg_match(self::KEY_PATTERN, $authorityKey) !== 1
            || !is_int($estimatedAt)
            || $estimatedAt <= 0
            || !self::boundedInteger($etaSeconds, 0, self::MAX_ETA_SECONDS)
            || !self::nonNegativeFinite($distance, 100000000.0)
        ) {
            throw new InvalidArgumentException('Route estimate is invalid.');
        }

        $age = $policy['evaluatedAt'] - $estimatedAt;
        if ($age < 0 || $age > $policy['maximumRouteAgeSeconds']) {
            return [
                'status' => 'stale',
                'strategy' => 'external-route',
                'routeAware' => true,
                'basisObservedAt' => $estimatedAt,
                'estimatedArrivalAt' => null,
                'etaSeconds' => null,
                'remainingDistanceMeters' => (float) $distance,
                'speedMetersPerSecond' => null,
                'evidenceSampleCount' => 0,
                'reason' => 'route-estimate-stale',
                'authorityKey' => $authorityKey,
            ];
        }

        $arrivalAt = $estimatedAt + $etaSeconds;
        $remainingEta = max(0, $arrivalAt - $policy['evaluatedAt']);
        $reached = $distance <= $policy['arrivalRadiusMeters']
            || $remainingEta === 0;

        return [
            'status' => $reached ? 'reached' : 'available',
            'strategy' => 'external-route',
            'routeAware' => true,
            'basisObservedAt' => $estimatedAt,
            'estimatedArrivalAt' => $arrivalAt,
            'etaSeconds' => $remainingEta,
            'remainingDistanceMeters' => (float) $distance,
            'speedMetersPerSecond' => null,
            'evidenceSampleCount' => 0,
            'reason' => $reached ? 'target-reached' : 'fresh-route-estimate',
            'authorityKey' => $authorityKey,
        ];
    }

    /**
     * @param list<array<string, mixed>> $observations
     * @param array<string, mixed> $target
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private static function geodesicResult(
        array $observations,
        array $target,
        array $policy
    ): array {
        if ($observations === []) {
            return self::unavailable(
                'position-missing',
                $target['targetKey']
            );
        }
        usort(
            $observations,
            static fn (array $left, array $right): int =>
                ($left['observedAt'] ?? 0) <=> ($right['observedAt'] ?? 0)
        );
        $latest = $observations[count($observations) - 1];
        $latestAt = $latest['observedAt'] ?? null;
        if (
            !is_int($latestAt)
            || $latestAt <= 0
            || $policy['evaluatedAt'] - $latestAt < 0
            || $policy['evaluatedAt'] - $latestAt
                > $policy['maximumCurrentPositionAgeSeconds']
        ) {
            return self::unavailable(
                'current-position-stale',
                $target['targetKey']
            );
        }
        if (($latest['lineEligible'] ?? false) !== true) {
            return self::unavailable(
                'current-position-quality',
                $target['targetKey']
            );
        }

        $latestCoordinate = OwnTracksWgs84::coordinate(
            $latest['latitudeDegrees'] ?? null,
            $latest['longitudeDegrees'] ?? null
        );
        $remainingDistance = OwnTracksWgs84::distanceMeters(
            $latestCoordinate,
            $target['coordinate']
        );
        if ($remainingDistance <= $policy['arrivalRadiusMeters']) {
            return [
                'status' => 'reached',
                'strategy' => 'geodesic-observed-speed',
                'routeAware' => false,
                'basisObservedAt' => $latestAt,
                'estimatedArrivalAt' => $policy['evaluatedAt'],
                'etaSeconds' => 0,
                'remainingDistanceMeters' => $remainingDistance,
                'speedMetersPerSecond' => null,
                'evidenceSampleCount' => 0,
                'reason' => 'target-reached',
                'targetKey' => $target['targetKey'],
                'authorityKey' => null,
            ];
        }

        $closingSpeed = $policy['closingSpeedMetersPerSecond'];
        if ($closingSpeed !== null) {
            $closingSpeedAt = $policy['closingSpeedObservedAt'];
            $closingSpeedCount = $policy['closingSpeedEvidenceCount'];
            if (
                (float) $closingSpeed
                    < $policy['minimumSpeedMetersPerSecond']
                || (float) $closingSpeed
                    > $policy['maximumSpeedMetersPerSecond']
            ) {
                return self::unavailable(
                    'closing-speed-evidence-out-of-policy',
                    $target['targetKey']
                );
            }
            if (
                !is_int($closingSpeedAt)
                || $closingSpeedAt > $latestAt
                || $latestAt - $closingSpeedAt
                    > $policy['maximumCurrentPositionAgeSeconds']
                || !is_int($closingSpeedCount)
            ) {
                return self::unavailable(
                    'closing-speed-evidence-stale',
                    $target['targetKey']
                );
            }
            $etaSeconds = (int) ceil(
                $remainingDistance / (float) $closingSpeed
            );
            if ($etaSeconds > self::MAX_ETA_SECONDS) {
                return self::unavailable(
                    'eta-out-of-range',
                    $target['targetKey']
                );
            }

            return [
                'status' => 'available',
                'strategy' => 'geodesic-target-closing-speed',
                'routeAware' => false,
                'basisObservedAt' => $closingSpeedAt,
                'estimatedArrivalAt' => $policy['evaluatedAt'] + $etaSeconds,
                'etaSeconds' => $etaSeconds,
                'remainingDistanceMeters' => $remainingDistance,
                'speedMetersPerSecond' => (float) $closingSpeed,
                'evidenceSampleCount' => $closingSpeedCount,
                'reason' => 'diagnostic-motion-aware-estimate',
                'targetKey' => $target['targetKey'],
                'authorityKey' => null,
            ];
        }

        $speeds = [];
        $previous = null;
        foreach ($observations as $observation) {
            if (($observation['lineEligible'] ?? false) !== true) {
                $previous = null;
                continue;
            }
            $observedAt = $observation['observedAt'] ?? null;
            if (
                !is_int($observedAt)
                || $observedAt < $latestAt - $policy['lookbackSeconds']
                || $observedAt > $latestAt
            ) {
                continue;
            }
            if ($previous !== null) {
                $previousAt = $previous['observedAt'] ?? null;
                if (!is_int($previousAt)) {
                    $previous = $observation;
                    continue;
                }
                $duration = $observedAt - $previousAt;
                if ($duration > 0) {
                    $distance = OwnTracksWgs84::distanceMeters(
                        OwnTracksWgs84::coordinate(
                            $previous['latitudeDegrees'] ?? null,
                            $previous['longitudeDegrees'] ?? null
                        ),
                        OwnTracksWgs84::coordinate(
                            $observation['latitudeDegrees'] ?? null,
                            $observation['longitudeDegrees'] ?? null
                        )
                    );
                    $speed = $distance / $duration;
                    if (
                        $speed >= $policy['minimumSpeedMetersPerSecond']
                        && $speed <= $policy['maximumSpeedMetersPerSecond']
                    ) {
                        $speeds[] = $speed;
                    }
                }
            }
            $previous = $observation;
        }
        if ($speeds === []) {
            return self::unavailable(
                'speed-evidence-insufficient',
                $target['targetKey']
            );
        }

        sort($speeds, SORT_NUMERIC);
        $speed = $speeds[(int) floor((count($speeds) - 1) / 2)];
        $etaSeconds = (int) ceil($remainingDistance / $speed);
        if ($etaSeconds > self::MAX_ETA_SECONDS) {
            return self::unavailable(
                'eta-out-of-range',
                $target['targetKey']
            );
        }

        return [
            'status' => 'available',
            'strategy' => 'geodesic-observed-speed',
            'routeAware' => false,
            'basisObservedAt' => $latestAt,
            'estimatedArrivalAt' => $policy['evaluatedAt'] + $etaSeconds,
            'etaSeconds' => $etaSeconds,
            'remainingDistanceMeters' => $remainingDistance,
            'speedMetersPerSecond' => $speed,
            'evidenceSampleCount' => count($speeds),
            'reason' => 'diagnostic-geodesic-estimate',
            'targetKey' => $target['targetKey'],
            'authorityKey' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function unavailable(
        string $reason,
        ?string $targetKey = null
    ): array {
        return [
            'status' => 'unavailable',
            'strategy' => 'none',
            'routeAware' => false,
            'basisObservedAt' => null,
            'estimatedArrivalAt' => null,
            'etaSeconds' => null,
            'remainingDistanceMeters' => null,
            'speedMetersPerSecond' => null,
            'evidenceSampleCount' => 0,
            'reason' => $reason,
            'targetKey' => $targetKey,
            'authorityKey' => null,
        ];
    }

    private static function boundedInteger(
        mixed $value,
        int $minimum,
        int $maximum
    ): bool {
        return is_int($value) && $value >= $minimum && $value <= $maximum;
    }

    private static function positiveFinite(mixed $value, float $maximum): bool
    {
        return (is_int($value) || is_float($value))
            && is_finite((float) $value)
            && (float) $value > 0.0
            && (float) $value <= $maximum;
    }

    private static function nonNegativeFinite(
        mixed $value,
        float $maximum
    ): bool {
        return (is_int($value) || is_float($value))
            && is_finite((float) $value)
            && (float) $value >= 0.0
            && (float) $value <= $maximum;
    }

    /** @param array<string, mixed> $policy */
    private static function validClosingSpeedEvidence(array $policy): bool
    {
        $speed = $policy['closingSpeedMetersPerSecond'];
        $observedAt = $policy['closingSpeedObservedAt'];
        $count = $policy['closingSpeedEvidenceCount'];
        if ($speed === null && $observedAt === null && $count === null) {
            return true;
        }

        return self::positiveFinite($speed, 1000.0)
            && self::boundedInteger($observedAt, 1, PHP_INT_MAX)
            && self::boundedInteger($count, 1, 10000);
    }
}
