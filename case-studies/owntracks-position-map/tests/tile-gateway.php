<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksTileAccessPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksTileCapability;
use OwnTracksPositionMap\Prototype\OwnTracksTileFileCache;
use OwnTracksPositionMap\Prototype\OwnTracksTileGateway;

require_once __DIR__ . '/bootstrap.php';

const TILE_TEST_SECRET =
    'synthetic-repository-only-secret-000000000000000000000000';
const TILE_TEST_AUDIENCE = 'owntracks-position-map:synthetic';
const TILE_TEST_REVISION = 'synthetic-v1';
const TILE_TEST_NOW = 1_725_184_000;

/** @return array<string, mixed> */
function gatewayPolicy(array $overrides = []): array
{
    return OwnTracksTileAccessPolicy::normalize(array_replace([
        'mode' => 'symcon-webhook',
        'connectReachable' => true,
        'authenticationMode' => 'ephemeral-header-capability',
        'headerName' => 'X-SAEF-Tile-Capability',
        'hookPathPrefix' => '/hook/owntracks-position-map',
        'connectForwardingVerified' => true,
        'headerCanonicalizationVerified' => true,
        'tokenTtlSeconds' => 300,
        'refreshBeforeExpirySeconds' => 60,
        'maximumRequestsPerMinute' => 30,
        'maximumConcurrentRequests' => 4,
    ], $overrides));
}

/** @return array{method: string, path: string, headers: array<string, string>, bodyBytes: int} */
function gatewayRequest(string $token, array $overrides = []): array
{
    return array_replace([
        'method' => 'GET',
        'path' => '/hook/owntracks-position-map/3/4/2.png',
        'headers' => ['X-SAEF-Tile-Capability' => $token],
        'bodyBytes' => 0,
    ], $overrides);
}

/** @return array{content: string} */
function syntheticPng(): array
{
    $decoded = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lE'
        . 'QVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($decoded)) {
        throw new RuntimeException('Synthetic PNG is invalid.');
    }

    return ['content' => $decoded];
}

function tileCacheTestRoot(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-tile-cache-'
        . bin2hex(random_bytes(8));
}

function removeTileCacheTestRoot(string $root): void
{
    $temporary = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-tile-cache-';
    if (!str_starts_with($root, $temporary) || !is_dir($root) || is_link($root)) {
        throw new RuntimeException('Refusing unsafe tile cache test cleanup.');
    }
    $items = scandir($root);
    if (!is_array($items)) {
        throw new RuntimeException('Tile cache test root cannot be scanned.');
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $root . DIRECTORY_SEPARATOR . $item;
        if (!is_file($path) || is_link($path) || !unlink($path)) {
            throw new RuntimeException('Tile cache test file cannot be removed.');
        }
    }
    if (!rmdir($root)) {
        throw new RuntimeException('Tile cache test root cannot be removed.');
    }
}

$issued = OwnTracksTileCapability::issue(
    TILE_TEST_SECRET,
    TILE_TEST_AUDIENCE,
    'synthetic-client-0001',
    TILE_TEST_NOW,
    300
);
assertTrue(
    !str_contains($issued['token'], 'synthetic-client-0001'),
    'Capability must remain opaque in its serialized representation.'
);
$claims = OwnTracksTileCapability::verify(
    $issued['token'],
    TILE_TEST_SECRET,
    TILE_TEST_AUDIENCE,
    TILE_TEST_NOW + 1
);
assertSameValue(
    'synthetic-client-0001',
    $claims['clientSessionKey'],
    'Capability client binding'
);
assertSameValue(TILE_TEST_NOW + 300, $claims['expiresAt'], 'Capability expiry');

foreach (
    [
        [$issued['token'] . 'x', TILE_TEST_AUDIENCE, TILE_TEST_NOW + 1],
        [$issued['token'], 'owntracks-position-map:other', TILE_TEST_NOW + 1],
        [$issued['token'], TILE_TEST_AUDIENCE, TILE_TEST_NOW + 300],
    ] as [$token, $audience, $now]
) {
    try {
        OwnTracksTileCapability::verify(
            $token,
            TILE_TEST_SECRET,
            $audience,
            $now
        );
        throw new RuntimeException('Invalid capability was accepted.');
    } catch (InvalidArgumentException) {
    }
}

$state = [];
$reader = static fn (int $zoom, int $x, int $y): array => syntheticPng();
$accepted = OwnTracksTileGateway::handle(
    gatewayRequest($issued['token']),
    gatewayPolicy(),
    TILE_TEST_SECRET,
    TILE_TEST_AUDIENCE,
    18,
    TILE_TEST_REVISION,
    null,
    TILE_TEST_NOW + 1,
    $reader,
    $state
);
assertSameValue(200, $accepted['status'], 'Authorized tile status');
assertSameValue('accepted', $accepted['classification'], 'Authorized classification');
assertSameValue('image/png', $accepted['headers']['Content-Type'], 'Tile content type');
assertSameValue(
    'private, max-age=300',
    $accepted['headers']['Cache-Control'],
    'Private tile cache policy'
);
assertSameValue(
    'X-SAEF-Tile-Capability',
    $accepted['headers']['Vary'],
    'Capability cache variation'
);
assertTrue($accepted['body'] !== '', 'GET tile body is missing.');
assertTrue(
    !str_contains(serialize($accepted), $issued['token']),
    'Capability leaked into a gateway response.'
);

$headState = [];
$head = OwnTracksTileGateway::handle(
    gatewayRequest($issued['token'], ['method' => 'HEAD']),
    gatewayPolicy(),
    TILE_TEST_SECRET,
    TILE_TEST_AUDIENCE,
    18,
    TILE_TEST_REVISION,
    null,
    TILE_TEST_NOW + 1,
    $reader,
    $headState
);
assertSameValue(200, $head['status'], 'HEAD tile status');
assertSameValue('', $head['body'], 'HEAD tile body');
assertTrue(
    (int) $head['headers']['Content-Length'] > 0,
    'HEAD tile length is missing.'
);

foreach (
    [
        gatewayRequest('', ['headers' => []]),
        gatewayRequest($issued['token'] . 'x'),
        gatewayRequest($issued['token'], ['method' => 'POST']),
        gatewayRequest(
            $issued['token'],
            ['path' => '/hook/owntracks-position-map/3/4/2.png/extra']
        ),
        gatewayRequest(
            $issued['token'],
            ['path' => '/hook/owntracks-position-map/3/8/2.png']
        ),
        gatewayRequest(
            $issued['token'],
            ['path' => '/hook/owntracks-position-map/3/4/2.png?token=no']
        ),
        gatewayRequest($issued['token'], ['bodyBytes' => 1]),
    ] as $request
) {
    $rejectedState = [];
    $rejected = OwnTracksTileGateway::handle(
        $request,
        gatewayPolicy(),
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        18,
        TILE_TEST_REVISION,
        null,
        TILE_TEST_NOW + 1,
        $reader,
        $rejectedState
    );
    assertSameValue(404, $rejected['status'], 'Rejected request status');
    assertSameValue('no-store', $rejected['headers']['Cache-Control'], 'Rejected cache policy');
}

$unavailableState = [];
$unavailable = OwnTracksTileGateway::handle(
    gatewayRequest($issued['token']),
    gatewayPolicy(),
    TILE_TEST_SECRET,
    TILE_TEST_AUDIENCE,
    18,
    TILE_TEST_REVISION,
    null,
    TILE_TEST_NOW + 1,
    static fn (int $zoom, int $x, int $y): array => ['content' => 'not-png'],
    $unavailableState
);
assertSameValue(404, $unavailable['status'], 'Invalid tile status');
assertSameValue('tile-unavailable', $unavailable['classification'], 'Invalid tile classification');

$cacheRoots = [tileCacheTestRoot(), tileCacheTestRoot(), tileCacheTestRoot()];
try {
    $cacheReads = 0;
    $cacheReader = static function (
        int $zoom,
        int $x,
        int $y
    ) use (&$cacheReads): array {
        $cacheReads++;
        return syntheticPng();
    };
    $secondCapability = OwnTracksTileCapability::issue(
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        'synthetic-client-0002',
        TILE_TEST_NOW,
        300
    );
    $cacheState = [];
    foreach ([$issued['token'], $secondCapability['token']] as $cacheToken) {
        $cache = new OwnTracksTileFileCache($cacheRoots[0]);
        $cacheResponse = OwnTracksTileGateway::handle(
            gatewayRequest($cacheToken),
            gatewayPolicy(),
            TILE_TEST_SECRET,
            TILE_TEST_AUDIENCE,
            18,
            TILE_TEST_REVISION,
            $cache,
            TILE_TEST_NOW + 1,
            $cacheReader,
            $cacheState
        );
        assertSameValue(200, $cacheResponse['status'], 'Cached tile status');
    }
    assertSameValue(1, $cacheReads, 'Cache must survive adapter reconstruction');
    $cacheStatistics = $cache->statistics(TILE_TEST_NOW + 1);
    assertSameValue(1, $cacheStatistics['hits'], 'Tile cache hit count');
    assertSameValue(1, $cacheStatistics['misses'], 'Tile cache miss count');
    assertSameValue(1, $cacheStatistics['entries'], 'Tile cache entry count');

    $revisionResponse = OwnTracksTileGateway::handle(
        gatewayRequest($secondCapability['token']),
        gatewayPolicy(),
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        18,
        'synthetic-v2',
        $cache,
        TILE_TEST_NOW + 2,
        $cacheReader,
        $cacheState
    );
    assertSameValue(200, $revisionResponse['status'], 'Revised tile status');
    assertSameValue(2, $cacheReads, 'Tile revision must partition cache entries');

    $refreshedCapability = OwnTracksTileCapability::issue(
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        'synthetic-client-0003',
        TILE_TEST_NOW + 300,
        300
    );
    $expiredCacheResponse = OwnTracksTileGateway::handle(
        gatewayRequest($refreshedCapability['token']),
        gatewayPolicy(),
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        18,
        TILE_TEST_REVISION,
        new OwnTracksTileFileCache($cacheRoots[0]),
        TILE_TEST_NOW + 301,
        $cacheReader,
        $cacheState
    );
    assertSameValue(200, $expiredCacheResponse['status'], 'Expired cache status');
    assertSameValue(3, $cacheReads, 'Expired cache entry must be read again');

    $statisticsBeforeRejection = $cache->statistics(TILE_TEST_NOW + 302);
    $unauthorizedCacheResponse = OwnTracksTileGateway::handle(
        gatewayRequest($refreshedCapability['token'] . 'x'),
        gatewayPolicy(),
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        18,
        TILE_TEST_REVISION,
        $cache,
        TILE_TEST_NOW + 302,
        static function (int $zoom, int $x, int $y): never {
            throw new RuntimeException('Unauthorized request reached tile reader.');
        },
        $cacheState
    );
    assertSameValue(404, $unauthorizedCacheResponse['status'], 'Unauthorized cache');
    assertSameValue(
        $statisticsBeforeRejection,
        $cache->statistics(TILE_TEST_NOW + 302),
        'Unauthorized request reached the tile cache'
    );
    $manifest = file_get_contents($cacheRoots[0] . '/manifest.json');
    assertTrue(is_string($manifest), 'Tile cache manifest is missing.');
    foreach ([$issued['token'], $secondCapability['token'], TILE_TEST_REVISION] as $secret) {
        assertTrue(!str_contains($manifest, $secret), 'Manifest contains sensitive key material.');
    }

    $boundedCapability = OwnTracksTileCapability::issue(
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        'synthetic-client-bounds',
        TILE_TEST_NOW,
        900
    );
    $entryCache = new OwnTracksTileFileCache($cacheRoots[1]);
    $entryBoundState = [];
    for ($tileX = 0; $tileX < 256; $tileX++) {
        $boundedResponse = OwnTracksTileGateway::handle(
            gatewayRequest(
                $boundedCapability['token'],
                ['path' => '/hook/owntracks-position-map/9/' . $tileX . '/1.png']
            ),
            gatewayPolicy(['maximumRequestsPerMinute' => 1200]),
            TILE_TEST_SECRET,
            TILE_TEST_AUDIENCE,
            18,
            TILE_TEST_REVISION,
            $entryCache,
            TILE_TEST_NOW + 1,
            $reader,
            $entryBoundState
        );
        assertSameValue(200, $boundedResponse['status'], 'Entry-bounded status');
    }
    assertTrue(
        $entryCache->read(TILE_TEST_REVISION, 9, 0, 1, TILE_TEST_NOW + 1) !== null,
        'LRU refresh read failed.'
    );
    $entryCache->write(
        TILE_TEST_REVISION,
        9,
        256,
        1,
        syntheticPng()['content'],
        TILE_TEST_NOW + 1
    );
    assertTrue(
        $entryCache->read(TILE_TEST_REVISION, 9, 0, 1, TILE_TEST_NOW + 1) !== null,
        'Recently used cache entry was evicted.'
    );
    assertSameValue(
        null,
        $entryCache->read(TILE_TEST_REVISION, 9, 1, 1, TILE_TEST_NOW + 1),
        'Least recently used cache entry was retained.'
    );
    $entryStatistics = $entryCache->statistics(TILE_TEST_NOW + 1);
    assertSameValue(256, $entryStatistics['entries'], 'Tile cache entry bound');
    assertTrue($entryStatistics['evictions'] >= 1, 'Entry eviction is missing.');

    $largePng = "\x89PNG\r\n\x1A\n" . str_repeat("\0", (512 * 1024) - 8);
    $byteCache = new OwnTracksTileFileCache($cacheRoots[2]);
    for ($tileX = 0; $tileX < 33; $tileX++) {
        $byteCache->write(
            TILE_TEST_REVISION,
            9,
            $tileX,
            2,
            $largePng,
            TILE_TEST_NOW + 1
        );
    }
    $byteStatistics = $byteCache->statistics(TILE_TEST_NOW + 1);
    assertTrue(
        $byteStatistics['totalBytes'] <= 16 * 1024 * 1024,
        'Tile cache byte bound was exceeded.'
    );
    assertTrue($byteStatistics['evictions'] >= 1, 'Byte eviction is missing.');

    assertSameValue(
        1,
        file_put_contents($cacheRoots[2] . '/manifest.json', '{'),
        'Corrupt manifest fixture write'
    );
    assertSameValue(
        4,
        file_put_contents($cacheRoots[2] . '/unowned.txt', 'keep'),
        'Unowned cache fixture write'
    );
    $recoveredCache = new OwnTracksTileFileCache($cacheRoots[2]);
    assertSameValue(
        null,
        $recoveredCache->read(TILE_TEST_REVISION, 9, 0, 2, TILE_TEST_NOW + 2),
        'Corrupt cache manifest did not reset to a miss.'
    );
    assertSameValue(0, $recoveredCache->statistics(TILE_TEST_NOW + 2)['entries'], 'Corrupt cache retained entries');
    assertTrue(
        is_file($cacheRoots[2] . '/unowned.txt'),
        'Corrupt cache recovery removed an unowned file.'
    );
} finally {
    foreach ($cacheRoots as $cacheRoot) {
        if (is_dir($cacheRoot)) {
            removeTileCacheTestRoot($cacheRoot);
        }
    }
}

$rateState = [];
for ($requestIndex = 0; $requestIndex < 30; $requestIndex++) {
    $response = OwnTracksTileGateway::handle(
        gatewayRequest($issued['token']),
        gatewayPolicy(),
        TILE_TEST_SECRET,
        TILE_TEST_AUDIENCE,
        18,
        TILE_TEST_REVISION,
        null,
        TILE_TEST_NOW + 1,
        $reader,
        $rateState
    );
    assertSameValue(200, $response['status'], 'In-budget tile request');
}
$limited = OwnTracksTileGateway::handle(
    gatewayRequest($issued['token']),
    gatewayPolicy(),
    TILE_TEST_SECRET,
    TILE_TEST_AUDIENCE,
    18,
    TILE_TEST_REVISION,
    null,
    TILE_TEST_NOW + 1,
    $reader,
    $rateState
);
assertSameValue(429, $limited['status'], 'Rate-limited tile status');

$concurrencyState = ['clients' => [
    hash('sha256', $claims['capabilityId']) => [
        'windowStartedAt' => intdiv(TILE_TEST_NOW + 1, 60) * 60,
        'requests' => 0,
        'inFlight' => 4,
        'updatedAt' => TILE_TEST_NOW + 1,
    ],
]];
$concurrencyLimited = OwnTracksTileGateway::handle(
    gatewayRequest($issued['token']),
    gatewayPolicy(),
    TILE_TEST_SECRET,
    TILE_TEST_AUDIENCE,
    18,
    TILE_TEST_REVISION,
    null,
    TILE_TEST_NOW + 1,
    $reader,
    $concurrencyState
);
assertSameValue(429, $concurrencyLimited['status'], 'Concurrency-limited status');
assertSameValue(
    'concurrency-limited',
    $concurrencyLimited['classification'],
    'Concurrency classification'
);

fwrite(STDOUT, "OwnTracks tile gateway tests passed.\n");
