<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/MapGeometryReducer.php';

use Navimow\Prototype\MapGeometryReducer;

function assertMapGeometry(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param callable(): void $operation
 */
function assertMapGeometryRejected(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$fixture = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../fixtures/map/map-detail-plain-synthetic.json'
    ),
    true,
    64,
    JSON_THROW_ON_ERROR
);
assertMapGeometry(is_array($fixture), 'Synthetic map fixture is invalid.');

$projection = MapGeometryReducer::reduce($fixture);
assertMapGeometry(
    $projection['formatVersion'] === 1
        && $projection['authority'] === 'decoded-private-map-payload'
        && $projection['coordinateFrame'] === 'navimow-local-map'
        && count($projection['zones']) === 2
        && count($projection['obstacles']) === 1
        && count($projection['visionOffAreas']) === 1,
    'Synthetic geometry projection differs.'
);
assertMapGeometry(
    $projection['zones'][0]['calculatedArea'] === 100.0
        && $projection['zones'][1]['calculatedArea'] === 64.0
        && count($projection['zones'][0]['ring']) === 5
        && $projection['zones'][0]['ring'][0]
            === $projection['zones'][0]['ring'][4]
        && count($projection['zones'][0]['boundaryFlags']) === 4,
    'Zone ring normalization or area calculation differs.'
);
assertMapGeometry(
    $projection['station']['x'] === 1.5
        && $projection['station']['y'] === 0.5
        && abs($projection['station']['direction'] - 1.5708) < 0.000001,
    'Charging station projection differs.'
);

$stringPayload = $fixture;
$stringPayload['map_detail'] = json_encode(
    $fixture['map_detail'],
    JSON_THROW_ON_ERROR
);
assertMapGeometry(
    MapGeometryReducer::reduce($stringPayload) === $projection,
    'JSON-string map detail does not match object projection.'
);

assertMapGeometryRejected(
    static fn (): array => MapGeometryReducer::reduce([
        'map_detail' => '{invalid-json',
    ]),
    'Malformed map detail was accepted.'
);

$selfIntersecting = $fixture;
$selfIntersecting['map_detail']['sub_maps'][0]['elements'][0]['points'] = [
    [0, 0],
    [10, 10],
    [0, 10],
    [10, 0],
];
assertMapGeometryRejected(
    static fn (): array => MapGeometryReducer::reduce($selfIntersecting),
    'Self-intersecting boundary was accepted.'
);

$invalidCoordinate = $fixture;
$invalidCoordinate['map_detail']['sub_maps'][0]['elements'][0]['points'][0][0]
    = 1000 * 1000 + 1;
assertMapGeometryRejected(
    static fn (): array => MapGeometryReducer::reduce($invalidCoordinate),
    'Out-of-range coordinate was accepted.'
);

$tooManyZones = $fixture;
$tooManyZones['map_detail']['sub_maps'] = array_fill(
    0,
    33,
    $fixture['map_detail']['sub_maps'][0]
);
assertMapGeometryRejected(
    static fn (): array => MapGeometryReducer::reduce($tooManyZones),
    'Unbounded zone list was accepted.'
);

$privacyProjection = json_encode(
    $projection,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
);
assertMapGeometry(
    !str_contains($privacyProjection, 'Nachbar')
        && !str_contains($privacyProjection, 'Weber')
        && !str_contains($privacyProjection, 'DEVICE_')
        && !str_contains($privacyProjection, '/downlink/'),
    'Synthetic geometry projection contains private installation data.'
);

echo "Navimow map geometry reducer checks passed.\n";
