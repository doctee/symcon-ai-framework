<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/RevisionBoundedTrackStore.php';

use Navimow\Prototype\RevisionBoundedTrackStore;

function assertRevisionBoundedTrack(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param callable(): mixed $operation */
function assertRevisionBoundedTrackRejected(
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

/**
 * @param list<array<string, mixed>> $points
 *
 * @return array<string, mixed>
 */
function revisionBoundedTrackScene(string $geometryKey, array $points): array
{
    return [
        'revision' => [
            'geometryKey' => $geometryKey,
            'state' => 'accepted',
            'pathCompatible' => true,
        ],
        'path' => [
            'status' => 'included',
            'segments' => [[
                'sequence' => 1,
                'breakReason' => 'initial',
                'taskZoneKey' => null,
                'points' => $points,
            ]],
        ],
    ];
}

/** @return array<string, mixed> */
function revisionBoundedTrackPoint(int $timestamp, float $offset = 0.0): array
{
    return [
        'localX' => 1.0 + $offset,
        'localY' => 2.0 + $offset,
        'orientation' => 0.25,
        'receivedAt' => $timestamp,
        'attribution' => [
            'zoneKey' => null,
            'source' => 'geometry-fallback',
        ],
    ];
}

$firstKey = hash('sha256', 'revision-1');
$firstScene = revisionBoundedTrackScene(
    $firstKey,
    [
        revisionBoundedTrackPoint(1000),
        revisionBoundedTrackPoint(1005, 1.0),
    ]
);
$state = RevisionBoundedTrackStore::ingestScene(
    RevisionBoundedTrackStore::initialState(),
    $firstScene
);
$projection = RevisionBoundedTrackStore::project($state);
assertRevisionBoundedTrack(
    $projection['revisionCount'] === 1
        && $projection['segmentCount'] === 1
        && $projection['pointCount'] === 2
        && $projection['counters']['ingestedPointCount'] === 2,
    'Initial retained-track projection differs.'
);

$serialized = RevisionBoundedTrackStore::serializeState($state);
$restored = RevisionBoundedTrackStore::restoreState($serialized);
assertRevisionBoundedTrack(
    RevisionBoundedTrackStore::serializeState($restored) === $serialized,
    'Serialized retained-track state is not stable.'
);
$scenePath = RevisionBoundedTrackStore::scenePath($restored, $firstKey);
assertRevisionBoundedTrack(
    $scenePath['status'] === 'included'
        && count($scenePath['segments']) === 1
        && count($scenePath['segments'][0]['points']) === 2
        && $scenePath['segments'][0]['points'][0]
            ['attribution']['source'] === 'geometry-fallback',
    'Retained track could not be projected for rendering.'
);
$pruned = RevisionBoundedTrackStore::pruneBefore($restored, 1005);
$prunedProjection = RevisionBoundedTrackStore::project($pruned);
assertRevisionBoundedTrack(
    $prunedProjection['pointCount'] === 1
        && $prunedProjection['counters']['evictedPointCount'] === 1,
    'Time-bounded track pruning differs.'
);

$state = RevisionBoundedTrackStore::ingestScene($state, $firstScene);
$projection = RevisionBoundedTrackStore::project($state);
assertRevisionBoundedTrack(
    $projection['pointCount'] === 2
        && $projection['segmentCount'] === 1
        && $projection['counters']['duplicatePointCount'] === 2,
    'Duplicate track points were retained.'
);

for ($revision = 2; $revision <= 5; ++$revision) {
    $state = RevisionBoundedTrackStore::ingestScene(
        $state,
        revisionBoundedTrackScene(
            hash('sha256', 'revision-' . $revision),
            [revisionBoundedTrackPoint(1000 + $revision * 100, $revision)]
        )
    );
}
$projection = RevisionBoundedTrackStore::project($state);
assertRevisionBoundedTrack(
    $projection['revisionCount'] === 4
        && $projection['pointCount'] === 4
        && $projection['counters']['evictedRevisionCount'] === 1
        && $projection['counters']['evictedSegmentCount'] === 1
        && $projection['counters']['evictedPointCount'] === 2
        && !in_array(
            $firstKey,
            array_column($projection['revisions'], 'geometryKey'),
            true
        ),
    'Oldest geometry revision was not evicted atomically.'
);

$manyPoints = [];
for ($index = 0; $index < 2050; ++$index) {
    $manyPoints[] = revisionBoundedTrackPoint(
        10 * 1000 + $index,
        $index / 1000
    );
}
$bounded = RevisionBoundedTrackStore::ingestScene(
    RevisionBoundedTrackStore::initialState(),
    revisionBoundedTrackScene(hash('sha256', 'bounded'), $manyPoints)
);
$boundedProjection = RevisionBoundedTrackStore::project($bounded);
assertRevisionBoundedTrack(
    $boundedProjection['pointCount'] === 2048
        && $boundedProjection['counters']['ingestedPointCount'] === 2050
        && $boundedProjection['counters']['evictedPointCount'] === 2,
    'Point retention did not enforce the 2048-point limit.'
);

$candidate = $firstScene;
$candidate['revision']['state'] = 'candidate';
assertRevisionBoundedTrackRejected(
    static fn (): array => RevisionBoundedTrackStore::ingestScene(
        RevisionBoundedTrackStore::initialState(),
        $candidate
    ),
    'A candidate geometry revision was retained.'
);
assertRevisionBoundedTrackRejected(
    static fn (): array => RevisionBoundedTrackStore::restoreState('{'),
    'Malformed retained-track state was restored.'
);

echo "revision-bounded track store tests passed\n";
