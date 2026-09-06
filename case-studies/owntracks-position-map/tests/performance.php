<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksTrackCore;

require_once __DIR__ . '/bootstrap.php';

$sizes = [250, 1000, 5000, 10000];
$results = [];
foreach ($sizes as $size) {
    $start = 1704067200;
    $records = [];
    $accuracyRecords = [];
    for ($index = $size - 1; $index >= 0; $index--) {
        $timestamp = $start + $index * 5;
        $records[] = [
            'TimeStamp' => $timestamp + 1,
            'Value' => json_encode(
                [
                    'tst' => $timestamp,
                    'lat' => 10.0 + $index * 0.000001,
                    'lon' => 20.0 + $index * 0.000001,
                ],
                JSON_THROW_ON_ERROR
            ),
        ];
        $accuracyRecords[] = [
            'TimeStamp' => $timestamp,
            'Value' => 5.0 + ($index % 3),
        ];
    }
    $query = [
        'requestGeneration' => $size,
        'sourceKey' => 'synthetic-performance',
        'from' => $start,
        'to' => $start + 24 * 60 * 60,
        'renderMode' => 'line-with-sampled-timestamps',
        'maxArchiveRecords' => $size,
        'maxRenderedPoints' => 500,
        'archiveLimitReached' => $size === 10000,
        'maximumAccuracyAgeSeconds' => 24 * 60 * 60,
    ];
    $before = hrtime(true);
    $result = OwnTracksTrackCore::project(
        $records,
        $accuracyRecords,
        $query
    );
    $milliseconds = (hrtime(true) - $before) / 1000000;
    assertSameValue(
        $size,
        $result['statistics']['fitObservationCount'],
        'Fit-all input count for size ' . $size
    );
    assertSameValue(
        min(500, $size),
        $result['statistics']['renderedPoints'],
        'Rendered point count for size ' . $size
    );
    assertTrue(
        strlen(json_encode($result, JSON_THROW_ON_ERROR)) <= 2 * 1024 * 1024,
        'Serialized result bound for size ' . $size
    );
    assertTrue(
        $milliseconds < 5000.0,
        'Offline projection exceeded 5 seconds for size ' . $size
    );
    $results[] = sprintf('%d=%0.1fms', $size, $milliseconds);
}

fwrite(
    STDOUT,
    'OwnTracks performance checks passed: ' . implode(', ', $results) . ".\n"
);
