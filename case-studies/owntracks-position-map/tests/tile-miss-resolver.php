<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksTileMissResolver;
use OwnTracksPositionMap\Prototype\OwnTracksTileSelectionAllowlist;

require_once __DIR__ . '/bootstrap.php';

const TILE_MISS_NOW = 1_725_184_000;

/** @return array<string, mixed> */
function tileMissConfiguration(array $overrides = []): array
{
    return array_replace([
        'mode' => 'fixed-https-xyz',
        'origin' => 'https://tiles.example.test',
        'pathTemplate' => '/fixed-style/{z}/{x}/{y}.png',
        'maximumZoom' => 12,
        'maximumRequestsPerSelection' => 4,
        'maximumBytesPerSelection' => 1024 * 1024,
        'timeoutMilliseconds' => 1500,
        'negativeTtlSeconds' => 60,
    ], $overrides);
}

/** @return string */
function tileMissPng()
{
    $decoded = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lE'
        . 'QVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($decoded)) {
        throw new RuntimeException('Synthetic PNG is invalid.');
    }

    return $decoded;
}

/** @return array{status: int, contentType: string, redirected: bool, elapsedMilliseconds: int, peerAddress: string, body: string} */
function tileMissResponse(array $overrides = []): array
{
    return array_replace([
        'status' => 200,
        'contentType' => 'image/png',
        'redirected' => false,
        'elapsedMilliseconds' => 40,
        'peerAddress' => '1.1.1.1',
        'body' => tileMissPng(),
    ], $overrides);
}

$allowlist = OwnTracksTileSelectionAllowlist::fromFitBounds(
    [
        'west' => 10.9,
        'south' => 48.0,
        'east' => 11.2,
        'north' => 48.2,
        'crossesAntimeridian' => false,
    ],
    8,
    10,
    1,
    128
);
assertTrue($allowlist->tileCount() > 0, 'Allowlist must authorize bounded tiles.');
assertSameValue(64, strlen($allowlist->fingerprint()), 'Allowlist fingerprint length');

$allowedTile = null;
for ($x = 0; $x < 1024 && $allowedTile === null; $x++) {
    for ($y = 0; $y < 1024; $y++) {
        if ($allowlist->allows(10, $x, $y)) {
            $allowedTile = [10, $x, $y];
            break;
        }
    }
}
assertTrue(is_array($allowedTile), 'Synthetic allowlist tile must be discoverable.');
[$zoom, $x, $y] = $allowedTile;
assertTrue(!$allowlist->allows(10, 0, 0), 'Unrelated world tile must be denied.');

$resolver = new OwnTracksTileMissResolver($allowlist, tileMissConfiguration());
$state = [];
$upstreamCalls = 0;
$static = $resolver->read(
    $zoom,
    $x,
    $y,
    static fn (): array => ['content' => tileMissPng()],
    static function () use (&$upstreamCalls): array {
        $upstreamCalls++;
        return tileMissResponse();
    },
    $state,
    TILE_MISS_NOW
);
assertTrue(is_array($static), 'Static tile must be returned first.');
assertSameValue(0, $upstreamCalls, 'Static hit upstream calls');

$capturedUrl = null;
$capturedOptions = null;
$dynamic = $resolver->read(
    $zoom,
    $x,
    $y,
    static fn (): ?array => null,
    static function (string $url, array $options) use (&$capturedUrl, &$capturedOptions): array {
        $capturedUrl = $url;
        $capturedOptions = $options;
        return tileMissResponse();
    },
    $state,
    TILE_MISS_NOW
);
assertTrue(is_array($dynamic), 'Allowlisted upstream tile must be accepted.');
assertSameValue(
    'https://tiles.example.test/fixed-style/' . $zoom . '/' . $x . '/' . $y . '.png',
    $capturedUrl,
    'Fixed upstream URL'
);
assertSameValue(false, $capturedOptions['followRedirects'], 'Redirect policy');
assertSameValue(true, $capturedOptions['requirePublicPeerAddress'], 'Peer policy');
assertSameValue(1, $state['upstreamRequests'], 'Upstream request count');
assertSameValue(1, $state['upstreamSuccesses'], 'Upstream success count');

$deferredState = [];
$deferredResolver = new OwnTracksTileMissResolver(
    $allowlist,
    tileMissConfiguration()
);
$deferred = $deferredResolver->read(
    $zoom,
    $x,
    $y,
    static fn (): ?array => null,
    static function (): never {
        throw new UnexpectedValueException('Synthetic admission delay.');
    },
    $deferredState,
    TILE_MISS_NOW
);
assertSameValue(null, $deferred, 'Deferred provider result');
assertSameValue(
    0,
    $deferredState['upstreamRequests'],
    'Deferred admission consumed the viewport request budget.'
);
assertSameValue(
    0,
    count($deferredState['negativeCache']),
    'Deferred admission populated the negative cache.'
);

$outsideCalls = 0;
$outside = $resolver->read(
    10,
    0,
    0,
    static fn (): ?array => null,
    static function () use (&$outsideCalls): array {
        $outsideCalls++;
        return tileMissResponse();
    },
    $state,
    TILE_MISS_NOW
);
assertSameValue(null, $outside, 'Outside allowlist result');
assertSameValue(0, $outsideCalls, 'Outside allowlist upstream calls');

$negativeCalls = 0;
for ($attempt = 0; $attempt < 2; $attempt++) {
    $negative = $resolver->read(
        $zoom,
        $x,
        $y,
        static fn (): ?array => null,
        static function () use (&$negativeCalls): array {
            $negativeCalls++;
            return tileMissResponse(['status' => 404, 'body' => '']);
        },
        $state,
        TILE_MISS_NOW + 1
    );
    assertSameValue(null, $negative, 'Negative result');
}
assertSameValue(1, $negativeCalls, 'Negative cache upstream calls');
assertSameValue(1, $state['negativeCacheHits'], 'Negative cache hit count');

$privatePeerState = [];
$privatePeer = $resolver->read(
    $zoom,
    $x,
    $y,
    static fn (): ?array => null,
    static fn (): array => tileMissResponse(['peerAddress' => '127.0.0.1']),
    $privatePeerState,
    TILE_MISS_NOW
);
assertSameValue(null, $privatePeer, 'Private peer response');
assertSameValue(0, $privatePeerState['upstreamSuccesses'], 'Private peer successes');

$byteResolver = new OwnTracksTileMissResolver(
    $allowlist,
    tileMissConfiguration([
        'maximumBytesPerSelection' => 512 * 1024,
        'maximumRequestsPerSelection' => 4,
    ])
);
$byteState = [];
$largeBody = "\x89PNG\r\n\x1A\n" . str_repeat('x', 300 * 1024);
$allowedTiles = [];
for ($candidateX = 0; $candidateX < 1024 && count($allowedTiles) < 2; $candidateX++) {
    for ($candidateY = 0; $candidateY < 1024; $candidateY++) {
        if ($allowlist->allows(10, $candidateX, $candidateY)) {
            $allowedTiles[] = [10, $candidateX, $candidateY];
            break;
        }
    }
}
assertSameValue(2, count($allowedTiles), 'Byte-budget fixture tile count');
foreach ($allowedTiles as $index => [$tileZoom, $tileX, $tileY]) {
    $result = $byteResolver->read(
        $tileZoom,
        $tileX,
        $tileY,
        static fn (): ?array => null,
        static fn (): array => tileMissResponse(['body' => $largeBody]),
        $byteState,
        TILE_MISS_NOW
    );
    assertSameValue($index === 0, is_array($result), 'Byte-budget result ' . $index);
}
assertSameValue(1, $byteState['budgetRejections'], 'Byte-budget rejection count');

$stableSelectionKey = hash('sha256', 'synthetic-data-selection');
$firstViewport = OwnTracksTileSelectionAllowlist::fromFitBounds(
    ['west' => 20.0, 'south' => 10.0, 'east' => 20.1, 'north' => 10.1],
    10,
    10,
    1,
    32
);
$secondViewport = OwnTracksTileSelectionAllowlist::fromFitBounds(
    ['west' => 20.2, 'south' => 10.0, 'east' => 20.3, 'north' => 10.1],
    10,
    10,
    1,
    32
);
$viewportTile = static function (OwnTracksTileSelectionAllowlist $viewport): array {
    for ($candidateX = 0; $candidateX < 1024; $candidateX++) {
        for ($candidateY = 0; $candidateY < 1024; $candidateY++) {
            if ($viewport->allows(10, $candidateX, $candidateY)) {
                return [10, $candidateX, $candidateY];
            }
        }
    }
    throw new RuntimeException('Synthetic viewport contains no tile.');
};
$stableState = [];
$stableCalls = 0;
foreach ([$firstViewport, $secondViewport] as $index => $viewport) {
    $viewportResolver = new OwnTracksTileMissResolver(
        $viewport,
        tileMissConfiguration(['maximumRequestsPerSelection' => 1]),
        $stableSelectionKey
    );
    [$viewportZoom, $viewportX, $viewportY] = $viewportTile($viewport);
    $viewportResult = $viewportResolver->read(
        $viewportZoom,
        $viewportX,
        $viewportY,
        static fn (): ?array => null,
        static function () use (&$stableCalls): array {
            $stableCalls++;
            return tileMissResponse();
        },
        $stableState,
        TILE_MISS_NOW
    );
    assertSameValue(
        $index === 0,
        is_array($viewportResult),
        'Stable selection budget across viewport ' . $index
    );
}
assertSameValue(1, $stableCalls, 'Viewport change reset the selection budget.');
assertSameValue(
    $stableSelectionKey,
    $stableState['selectionFingerprint'] ?? null,
    'Stable selection state key'
);

foreach (
        [
        ['origin' => 'http://tiles.example.test'],
        ['origin' => 'https://127.0.0.1'],
        ['origin' => 'https://user@tiles.example.test'],
        ['origin' => 'https://tiles.example.test/path'],
        ['pathTemplate' => '/{z}/{x}/{y}.png?secret=value'],
        ['pathTemplate' => '//other.example/{z}/{x}/{y}.png'],
        ] as $invalidOverride
) {
    $rejected = false;
    try {
        new OwnTracksTileMissResolver(
            $allowlist,
            tileMissConfiguration($invalidOverride)
        );
    } catch (\InvalidArgumentException) {
        $rejected = true;
    }
    assertTrue($rejected, 'Unsafe fallback configuration must be rejected.');
}

$invalidSelectionKeyRejected = false;
try {
    new OwnTracksTileMissResolver(
        $allowlist,
        tileMissConfiguration(),
        'not-a-selection-key'
    );
} catch (\InvalidArgumentException) {
    $invalidSelectionKeyRejected = true;
}
assertTrue($invalidSelectionKeyRejected, 'Invalid stable selection key accepted.');

$oversized = false;
try {
    OwnTracksTileSelectionAllowlist::fromFitBounds(
        [
            'west' => -170.0,
            'south' => -70.0,
            'east' => 170.0,
            'north' => 70.0,
            'crossesAntimeridian' => false,
        ],
        8,
        12,
        1,
        32
    );
} catch (\InvalidArgumentException) {
    $oversized = true;
}
assertTrue($oversized, 'Oversized spatial selection must fail closed.');

fwrite(STDOUT, "Tile miss resolver tests passed.\n");
