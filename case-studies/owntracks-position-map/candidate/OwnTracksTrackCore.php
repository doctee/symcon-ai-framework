<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class OwnTracksTrackCore
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/D';
    private const MAX_ARCHIVE_RECORDS = 10000;
    private const MAX_RENDERED_POINTS = 5000;
    private const MAX_SEGMENTS = 512;
    private const MAX_WINDOW_SECONDS = 31 * 24 * 60 * 60;
    private const MAX_RESULT_BYTES = 2 * 1024 * 1024;
    private const ETA_EVIDENCE_LIMIT = 64;

    /**
     * Project bounded Archive Control records into a renderer-neutral track.
     *
     * Input records use the Archive Control shape (`TimeStamp`, `Value`).
     * Position values are OwnTracks JSON payloads. The caller remains
     * responsible for the bounded archive read and for selecting one source.
     *
     * @param list<array<string, mixed>> $positionRecords
     * @param list<array<string, mixed>> $accuracyRecords
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function project(
        array $positionRecords,
        array $accuracyRecords,
        array $query
    ): array {
        $query = self::query($query);
        if (count($positionRecords) > $query['maxArchiveRecords']) {
            throw new InvalidArgumentException(
                'Position input exceeds the declared archive bound.'
            );
        }
        if (count($accuracyRecords) > self::MAX_ARCHIVE_RECORDS) {
            throw new InvalidArgumentException('Accuracy input is unbounded.');
        }

        $accuracy = self::accuracyChanges($accuracyRecords);
        $decoded = self::decodePositions($positionRecords, $query);
        $observations = self::attributeQuality(
            $decoded['observations'],
            $accuracy,
            $query
        );

        usort(
            $observations,
            static fn (array $left, array $right): int =>
                [$left['observedAt'], $left['receivedAt'], $left['_readIndex']]
                <=>
                [$right['observedAt'], $right['receivedAt'], $right['_readIndex']]
        );
        $observations = self::segment($observations, $query);

        $coordinates = array_map(
            static fn (array $observation): array => [
                'latitude' => $observation['latitudeDegrees'],
                'longitude' => $observation['longitudeDegrees'],
            ],
            $observations
        );
        $fitBounds = OwnTracksWgs84::bounds($coordinates);
        $etaEvidence = array_slice(
            $observations,
            -self::ETA_EVIDENCE_LIMIT
        );
        $render = self::renderProjection($observations, $query);

        $etaEvidence = array_map(
            [self::class, 'withoutInternalFields'],
            $etaEvidence
        );

        $result = [
            'requestGeneration' => $query['requestGeneration'],
            'sourceKey' => $query['sourceKey'],
            'coordinateReference' => 'EPSG:4326',
            'query' => [
                'from' => $query['from'],
                'to' => $query['to'],
                'renderMode' => $query['renderMode'],
                'maxArchiveRecords' => $query['maxArchiveRecords'],
                'maxRenderedPoints' => $query['maxRenderedPoints'],
            ],
            'historyWindow' => [
                'requestedFrom' => $query['from'],
                'requestedTo' => $query['to'],
                'returnedFrom' => $observations[0]['observedAt'] ?? null,
                'returnedTo' => $observations === []
                    ? null
                    : $observations[count($observations) - 1]
                        ['observedAt'],
                'archiveLimitReached' => $query['archiveLimitReached'],
            ],
            'fitBounds' => $fitBounds,
            'etaEvidence' => $etaEvidence,
            'render' => $render,
            'statistics' => [
                'archiveRecordsRead' => count($positionRecords),
                'validObservations' => count($observations),
                'invalidRecords' => $decoded['invalidRecords'],
                'outsideWindowRecords' => $decoded['outsideWindowRecords'],
                'accuracyChangesRead' => count($accuracy),
                'renderedPoints' => count($render['points']),
                'renderedUnverifiedPoints' =>
                    $render['renderedUnverifiedPoints'],
                'removedByQuality' => $render['removedByQuality'],
                'removedByRenderBudget' => $render['removedByRenderBudget'],
                'segmentCount' => $render['segmentCount'],
                'archiveLimitReached' => $query['archiveLimitReached'],
                'renderBudgetReached' => $render['renderBudgetReached'],
                'fitObservationCount' => $fitBounds['observationCount'] ?? 0,
            ],
        ];

        self::assertSerializedBound($result);

        return $result;
    }

    public static function isSuperseded(
        int $resultGeneration,
        int $activeGeneration
    ): bool {
        if ($resultGeneration <= 0 || $activeGeneration <= 0) {
            throw new InvalidArgumentException(
                'Request generations must be positive.'
            );
        }

        return $resultGeneration !== $activeGeneration;
    }

    /** @param array<string, mixed> $query */
    private static function query(array $query): array
    {
        $normalized = [
            'requestGeneration' => $query['requestGeneration'] ?? null,
            'sourceKey' => $query['sourceKey'] ?? null,
            'from' => $query['from'] ?? null,
            'to' => $query['to'] ?? null,
            'renderMode' => $query['renderMode'] ?? 'timestamp-points',
            'maxArchiveRecords' => $query['maxArchiveRecords'] ?? 2500,
            'maxRenderedPoints' => $query['maxRenderedPoints'] ?? 500,
            'archiveLimitReached' => $query['archiveLimitReached'] ?? false,
            'maximumGapSeconds' => $query['maximumGapSeconds'] ?? 60 * 60,
            'maximumReceptionDelaySeconds' =>
                $query['maximumReceptionDelaySeconds'] ?? 15 * 60,
            'maximumSourceClockLeadSeconds' =>
                $query['maximumSourceClockLeadSeconds'] ?? 0,
            'maximumAccuracyAgeSeconds' =>
                $query['maximumAccuracyAgeSeconds'] ?? 30 * 60,
            'maximumAccuracyMeters' =>
                $query['maximumAccuracyMeters'] ?? 100.0,
            'maximumStepDistanceMeters' =>
                $query['maximumStepDistanceMeters'] ?? 50000.0,
            'excludePoorAccuracyFromLine' =>
                $query['excludePoorAccuracyFromLine'] ?? true,
            'allowUnknownAccuracyForLine' =>
                $query['allowUnknownAccuracyForLine'] ?? false,
        ];
        $modes = [
            'timestamp-points',
            'segmented-line',
            'line-with-sampled-timestamps',
        ];
        if (
            !self::boundedInteger(
                $normalized['requestGeneration'],
                1,
                PHP_INT_MAX
            )
            || !is_string($normalized['sourceKey'])
            || preg_match(
                self::KEY_PATTERN,
                $normalized['sourceKey']
            ) !== 1
            || !self::boundedInteger($normalized['from'], 1, PHP_INT_MAX)
            || !self::boundedInteger($normalized['to'], 1, PHP_INT_MAX)
            || $normalized['to'] <= $normalized['from']
            || $normalized['to'] - $normalized['from']
                > self::MAX_WINDOW_SECONDS
            || !is_string($normalized['renderMode'])
            || !in_array($normalized['renderMode'], $modes, true)
            || !self::boundedInteger(
                $normalized['maxArchiveRecords'],
                1,
                self::MAX_ARCHIVE_RECORDS
            )
            || !self::boundedInteger(
                $normalized['maxRenderedPoints'],
                1,
                self::MAX_RENDERED_POINTS
            )
            || !is_bool($normalized['archiveLimitReached'])
            || !self::boundedInteger(
                $normalized['maximumGapSeconds'],
                1,
                24 * 60 * 60
            )
            || !self::boundedInteger(
                $normalized['maximumReceptionDelaySeconds'],
                0,
                24 * 60 * 60
            )
            || !self::boundedInteger(
                $normalized['maximumSourceClockLeadSeconds'],
                0,
                60
            )
            || !self::boundedInteger(
                $normalized['maximumAccuracyAgeSeconds'],
                1,
                24 * 60 * 60
            )
            || !self::positiveFinite(
                $normalized['maximumAccuracyMeters'],
                100000.0
            )
            || !self::positiveFinite(
                $normalized['maximumStepDistanceMeters'],
                10000000.0
            )
            || !is_bool($normalized['excludePoorAccuracyFromLine'])
            || !is_bool($normalized['allowUnknownAccuracyForLine'])
        ) {
            throw new InvalidArgumentException('Track query is invalid.');
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array{changedAt: int, accuracyMeters: float}>
     */
    private static function accuracyChanges(array $records): array
    {
        $changes = [];
        foreach ($records as $record) {
            $timestamp = $record['TimeStamp'] ?? null;
            $value = $record['Value'] ?? null;
            if (
                !is_int($timestamp)
                || $timestamp <= 0
                || !self::nonNegativeFinite($value, 100000.0)
            ) {
                continue;
            }
            $changes[] = [
                'changedAt' => $timestamp,
                'accuracyMeters' => (float) $value,
            ];
        }
        usort(
            $changes,
            static fn (array $left, array $right): int =>
                $left['changedAt'] <=> $right['changedAt']
        );

        return $changes;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param array<string, mixed> $query
     * @return array{
     *     observations: list<array<string, mixed>>,
     *     invalidRecords: int,
     *     outsideWindowRecords: int
     * }
     */
    private static function decodePositions(array $records, array $query): array
    {
        $observations = [];
        $invalid = 0;
        $outside = 0;
        foreach ($records as $readIndex => $record) {
            $receivedAt = $record['TimeStamp'] ?? null;
            $value = $record['Value'] ?? null;
            if (!is_int($receivedAt) || $receivedAt <= 0 || !is_string($value)) {
                $invalid++;
                continue;
            }
            try {
                $payload = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $invalid++;
                continue;
            }
            if (!is_array($payload)) {
                $invalid++;
                continue;
            }
            $observedAt = $payload['tst'] ?? null;
            if (!is_int($observedAt) || $observedAt <= 0) {
                $invalid++;
                continue;
            }
            try {
                $coordinate = OwnTracksWgs84::coordinate(
                    $payload['lat'] ?? null,
                    $payload['lon'] ?? null
                );
            } catch (InvalidArgumentException) {
                $invalid++;
                continue;
            }
            if ($observedAt < $query['from'] || $observedAt >= $query['to']) {
                $outside++;
                continue;
            }
            $altitude = $payload['alt'] ?? null;
            if ($altitude !== null && !self::finiteNumber($altitude)) {
                $altitude = null;
            }
            $observations[] = [
                'observedAt' => $observedAt,
                'receivedAt' => $receivedAt,
                'latitudeDegrees' => $coordinate['latitude'],
                'longitudeDegrees' => $coordinate['longitude'],
                'altitudeMeters' => $altitude === null
                    ? null
                    : (float) $altitude,
                'horizontalAccuracyMeters' => null,
                'accuracyObservedAt' => null,
                'accuracyAttribution' => 'unknown',
                'qualityFlags' => [],
                'lineEligible' => true,
                'lineConfidence' => 'verified',
                'segmentIndex' => null,
                '_readIndex' => $readIndex,
            ];
        }

        return [
            'observations' => $observations,
            'invalidRecords' => $invalid,
            'outsideWindowRecords' => $outside,
        ];
    }

    /**
     * @param list<array<string, mixed>> $observations
     * @param list<array{changedAt: int, accuracyMeters: float}> $accuracy
     * @param array<string, mixed> $query
     * @return list<array<string, mixed>>
     */
    private static function attributeQuality(
        array $observations,
        array $accuracy,
        array $query
    ): array {
        usort(
            $observations,
            static fn (array $left, array $right): int =>
                [$left['receivedAt'], $left['_readIndex']]
                <=> [$right['receivedAt'], $right['_readIndex']]
        );
        $lastPayloadTime = null;
        $accuracyIndex = 0;
        $latestAccuracy = null;
        foreach ($observations as $index => $observation) {
            $flags = [];
            $delay = $observation['receivedAt'] - $observation['observedAt'];
            if ($delay < -$query['maximumSourceClockLeadSeconds']) {
                $flags[] = 'source-time-ahead';
            } elseif ($delay < 0) {
                $flags[] = 'source-clock-skew-tolerated';
            } elseif ($delay > $query['maximumReceptionDelaySeconds']) {
                $flags[] = 'delayed-reception';
            }
            if (
                $lastPayloadTime !== null
                && $observation['observedAt'] < $lastPayloadTime
            ) {
                $flags[] = 'out-of-order';
            }
            $lastPayloadTime = $observation['observedAt'];

            while (
                isset($accuracy[$accuracyIndex])
                && $accuracy[$accuracyIndex]['changedAt']
                    <= $observation['receivedAt']
            ) {
                $latestAccuracy = $accuracy[$accuracyIndex];
                $accuracyIndex++;
            }
            if ($latestAccuracy === null) {
                $flags[] = 'accuracy-unknown';
                if ($query['allowUnknownAccuracyForLine']) {
                    $observation['lineConfidence'] = 'unverified';
                } else {
                    $observation['lineEligible'] = false;
                }
            } else {
                $accuracyAge = $observation['receivedAt']
                    - $latestAccuracy['changedAt'];
                $observation['accuracyObservedAt'] =
                    $latestAccuracy['changedAt'];
                if ($accuracyAge > $query['maximumAccuracyAgeSeconds']) {
                    $flags[] = 'accuracy-stale';
                    $observation['lineEligible'] = false;
                } else {
                    $observation['horizontalAccuracyMeters'] =
                        $latestAccuracy['accuracyMeters'];
                    $observation['accuracyAttribution'] = 'last-known';
                    if (
                        $latestAccuracy['accuracyMeters']
                        > $query['maximumAccuracyMeters']
                    ) {
                        $flags[] = 'accuracy-poor';
                        if ($query['excludePoorAccuracyFromLine']) {
                            $observation['lineEligible'] = false;
                        }
                    }
                }
            }
            if (
                in_array('out-of-order', $flags, true)
                || in_array('delayed-reception', $flags, true)
                || in_array('source-time-ahead', $flags, true)
            ) {
                $observation['lineEligible'] = false;
            }
            $observation['qualityFlags'] = $flags;
            $observations[$index] = $observation;
        }

        return $observations;
    }

    /**
     * @param list<array<string, mixed>> $observations
     * @param array<string, mixed> $query
     * @return list<array<string, mixed>>
     */
    private static function segment(array $observations, array $query): array
    {
        $segmentIndex = -1;
        $previous = null;
        $seen = [];
        foreach ($observations as $index => $observation) {
            $flags = $observation['qualityFlags'];
            $positionKey = sprintf(
                '%.7F:%.7F:%d',
                $observation['latitudeDegrees'],
                $observation['longitudeDegrees'],
                $observation['observedAt']
            );
            if (isset($seen[$positionKey])) {
                $flags[] = 'duplicate-position';
            }
            $seen[$positionKey] = true;

            $break = $previous === null;
            if ($previous !== null) {
                $duration = $observation['observedAt']
                    - $previous['observedAt'];
                if ($duration > $query['maximumGapSeconds']) {
                    $flags[] = 'gap-before';
                    $break = true;
                }
                $distance = OwnTracksWgs84::distanceMeters(
                    [
                        'latitude' => $previous['latitudeDegrees'],
                        'longitude' => $previous['longitudeDegrees'],
                    ],
                    [
                        'latitude' => $observation['latitudeDegrees'],
                        'longitude' => $observation['longitudeDegrees'],
                    ]
                );
                if ($distance > $query['maximumStepDistanceMeters']) {
                    $flags[] = 'implausible-jump';
                    $observation['lineEligible'] = false;
                    $break = true;
                }
                if (
                    !$previous['lineEligible']
                    || !$observation['lineEligible']
                    || $previous['lineConfidence']
                        !== $observation['lineConfidence']
                ) {
                    $break = true;
                }
            }
            if (!$observation['lineEligible']) {
                $observation['segmentIndex'] = null;
                $observation['qualityFlags'] = array_values(
                    array_unique($flags)
                );
                $observations[$index] = $observation;
                $previous = $observation;
                continue;
            }
            if ($break) {
                $segmentIndex++;
                if ($segmentIndex >= self::MAX_SEGMENTS) {
                    throw new RuntimeException('Segment bound exceeded.');
                }
            }
            $observation['segmentIndex'] = $segmentIndex;
            $observation['qualityFlags'] = array_values(array_unique($flags));
            $observations[$index] = $observation;
            $previous = $observation;
        }

        return $observations;
    }

    /**
     * @param list<array<string, mixed>> $observations
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private static function renderProjection(array $observations, array $query): array
    {
        $candidates = $query['renderMode'] === 'timestamp-points'
            ? $observations
            : array_values(array_filter(
                $observations,
                static fn (array $observation): bool =>
                    $observation['lineEligible'] === true
            ));
        $removedByQuality = count($observations) - count($candidates);
        $sample = self::sample(
            $candidates,
            $query['maxRenderedPoints'],
            $query['renderMode'] !== 'timestamp-points'
        );
        $selected = $sample['points'];
        $points = array_map([self::class, 'withoutInternalFields'], $selected);

        $segments = [];
        if ($query['renderMode'] !== 'timestamp-points') {
            foreach ($points as $point) {
                $segment = (string) $point['segmentIndex'];
                $segments[$segment][] = $point;
            }
            $segments = array_values(array_filter(
                $segments,
                static fn (array $segment): bool => count($segment) >= 2
            ));
        }

        return [
            'mode' => $query['renderMode'],
            'points' => $points,
            'segments' => $segments,
            'segmentCount' => count($segments),
            'renderedUnverifiedPoints' => count(array_filter(
                $points,
                static fn (array $point): bool =>
                    ($point['lineConfidence'] ?? null) === 'unverified'
            )),
            'removedByQuality' => $removedByQuality,
            'removedByRenderBudget' => count($candidates) - count($selected),
            'renderBudgetReached' => count($candidates) > count($selected),
            'allSegmentBoundariesRetained' =>
                $sample['allSegmentBoundariesRetained'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $points
     * @return array{
     *     points: list<array<string, mixed>>,
     *     allSegmentBoundariesRetained: bool
     * }
     */
    private static function sample(
        array $points,
        int $limit,
        bool $preserveSegmentBoundaries
    ): array {
        $count = count($points);
        if ($count <= $limit) {
            return [
                'points' => $points,
                'allSegmentBoundariesRetained' => true,
            ];
        }
        if ($limit === 1) {
            return [
                'points' => [$points[$count - 1]],
                'allSegmentBoundariesRetained' => !$preserveSegmentBoundaries,
            ];
        }

        $mandatory = [0 => true, $count - 1 => true];
        if ($preserveSegmentBoundaries) {
            $previousSegment = null;
            foreach ($points as $index => $point) {
                $segment = $point['segmentIndex'];
                if ($segment !== $previousSegment) {
                    $mandatory[$index] = true;
                    if ($index > 0) {
                        $mandatory[$index - 1] = true;
                    }
                }
                $previousSegment = $segment;
            }
        }
        $allBoundariesRetained = count($mandatory) <= $limit;
        $selectedIndexes = $allBoundariesRetained ? $mandatory : [];
        $remainingSlots = $limit - count($selectedIndexes);
        if ($remainingSlots > 0) {
            for ($slot = 0; $slot < $remainingSlots; $slot++) {
                $index = (int) round(
                    $slot * ($count - 1) / max(1, $remainingSlots - 1)
                );
                $selectedIndexes[$index] = true;
            }
        }
        if (count($selectedIndexes) > $limit) {
            $indexes = array_keys($selectedIndexes);
            $selectedIndexes = [];
            for ($slot = 0; $slot < $limit; $slot++) {
                $index = (int) round(
                    $slot * (count($indexes) - 1) / ($limit - 1)
                );
                $selectedIndexes[$indexes[$index]] = true;
            }
        }
        if (count($selectedIndexes) < $limit) {
            for ($index = 0; $index < $count; $index++) {
                $selectedIndexes[$index] = true;
                if (count($selectedIndexes) === $limit) {
                    break;
                }
            }
        }
        ksort($selectedIndexes, SORT_NUMERIC);
        $selected = [];
        foreach (array_keys($selectedIndexes) as $index) {
            $selected[] = $points[$index];
        }

        return [
            'points' => $selected,
            'allSegmentBoundariesRetained' => $allBoundariesRetained,
        ];
    }

    /** @param array<string, mixed> $observation */
    private static function withoutInternalFields(array $observation): array
    {
        unset($observation['_readIndex']);

        return $observation;
    }

    /** @param array<string, mixed> $result */
    private static function assertSerializedBound(array $result): void
    {
        try {
            $json = json_encode($result, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Track result cannot be serialized.',
                0,
                $exception
            );
        }
        if (strlen($json) > self::MAX_RESULT_BYTES) {
            throw new RuntimeException('Serialized track result exceeds bound.');
        }
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
        return self::finiteNumber($value)
            && (float) $value > 0.0
            && (float) $value <= $maximum;
    }

    private static function nonNegativeFinite(
        mixed $value,
        float $maximum
    ): bool {
        return self::finiteNumber($value)
            && (float) $value >= 0.0
            && (float) $value <= $maximum;
    }

    private static function finiteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value))
            && is_finite((float) $value);
    }
}
