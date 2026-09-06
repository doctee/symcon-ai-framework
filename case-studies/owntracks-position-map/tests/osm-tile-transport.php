<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksOsmTileProviderPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksPinnedHttpsTileTransport;

require_once __DIR__ . '/bootstrap.php';

const OSM_TILE_TEST_NOW = 1_725_184_000;
const OSM_TILE_TEST_URL = 'https://tile.openstreetmap.org/10/543/352.png';

/** @return array<string, mixed> */
function osmProviderConfiguration(array $overrides = []): array
{
    return array_replace([
        'mode' => 'osm-standard-raster-on-miss',
        'origin' => 'https://tile.openstreetmap.org',
        'pathTemplate' => '/{z}/{x}/{y}.png',
        'userAgent' => 'SAEFOwnTracksPositionMap/0.1 '
            . '(+https://github.com/doctee)',
        'refererOrigin' => 'https://connect.symcon.de/',
        'maximumConcurrentRequests' => 2,
        'maximumRequestsPerMinute' => 30,
    ], $overrides);
}

/** @return array<string, mixed> */
function osmTransportConfiguration(array $policy): array
{
    return [
        'origin' => $policy['origin'],
        'pathTemplate' => $policy['pathTemplate'],
        'userAgent' => $policy['userAgent'],
        'refererOrigin' => $policy['refererOrigin'],
        'timeoutMilliseconds' => 1500,
        'maximumResponseBytes' => 512 * 1024,
        'fallbackCacheTtlSeconds' => $policy['fallbackCacheTtlSeconds'],
    ];
}

/** @return array<string, mixed> */
function osmTransportResponse(array $overrides = []): array
{
    return array_replace([
        'status' => 200,
        'body' => "\x89PNG\r\n\x1A\nsynthetic",
        'primaryAddress' => '1.1.1.1',
        'effectiveUrl' => OSM_TILE_TEST_URL,
        'redirected' => false,
        'elapsedMilliseconds' => 20,
        'headers' => [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"synthetic-etag"',
            'Last-Modified' => 'Mon, 01 Sep 2025 00:00:00 GMT',
        ],
    ], $overrides);
}

$policy = OwnTracksOsmTileProviderPolicy::normalize(osmProviderConfiguration());
assertSameValue(
    OwnTracksOsmTileProviderPolicy::ATTRIBUTION_TEXT,
    $policy['attributionText'],
    'OSM attribution text'
);
assertSameValue(false, $policy['prefetchAllowed'], 'OSM prefetch policy');
assertSameValue(false, $policy['offlineArchiveAllowed'], 'OSM archive policy');
assertSameValue(604_800, $policy['fallbackCacheTtlSeconds'], 'OSM fallback TTL');
assertSameValue(2, $policy['maximumConcurrentRequests'], 'OSM concurrency');

foreach (
    [
        ['origin' => 'https://tiles.example.test'],
        ['pathTemplate' => '/style/{z}/{x}/{y}.png'],
        ['userAgent' => 'curl/8.0'],
        ['userAgent' => 'SAEFOwnTracksPositionMap/0.1'],
        ['refererOrigin' => 'http://connect.example.test/'],
        ['refererOrigin' => 'https://127.0.0.1/'],
        ['refererOrigin' => 'https://connect.example.test/'],
        ['maximumConcurrentRequests' => 3],
        ['maximumRequestsPerMinute' => 61],
    ] as $invalidOverride
) {
    $rejected = false;
    try {
        OwnTracksOsmTileProviderPolicy::normalize(
            osmProviderConfiguration($invalidOverride)
        );
    } catch (\InvalidArgumentException) {
        $rejected = true;
    }
    assertTrue($rejected, 'Unsafe OSM provider configuration must fail.');
}

$transport = new OwnTracksPinnedHttpsTileTransport(
    osmTransportConfiguration($policy)
);
$capturedPlan = null;
$response = $transport->fetch(
    OSM_TILE_TEST_URL,
    [
        'timeoutMilliseconds' => 1500,
        'maximumResponseBytes' => 512 * 1024,
        'followRedirects' => false,
        'requirePublicPeerAddress' => true,
    ],
    [
        'If-None-Match' => '"old-etag"',
        'If-Modified-Since' => 'Sun, 31 Aug 2025 00:00:00 GMT',
    ],
    static fn (string $host): array => $host === 'tile.openstreetmap.org'
        ? ['8.8.8.8', '1.1.1.1']
        : [],
    static function (array $plan) use (&$capturedPlan): array {
        $capturedPlan = $plan;
        return osmTransportResponse();
    },
    OSM_TILE_TEST_NOW
);
assertSameValue('1.1.1.1', $capturedPlan['pinnedAddress'], 'Pinned address');
assertSameValue(false, $capturedPlan['followRedirects'], 'Redirect plan');
assertSameValue(true, $capturedPlan['verifyTlsPeer'], 'TLS peer plan');
assertTrue(
    in_array('User-Agent: ' . $policy['userAgent'], $capturedPlan['headers'], true),
    'Identifiable User-Agent must be sent.'
);
assertTrue(
    in_array('Referer: ' . $policy['refererOrigin'], $capturedPlan['headers'], true),
    'Approved Referer origin must be sent.'
);
assertTrue(
    in_array('If-None-Match: "old-etag"', $capturedPlan['headers'], true),
    'Conditional ETag must be sent.'
);
assertSameValue('image/png', $response['contentType'], 'Response content type');
assertSameValue(86_400, $response['cacheTtlSeconds'], 'Origin max-age');
assertSameValue(true, $response['cacheable'], 'Origin cacheability');
assertSameValue('"synthetic-etag"', $response['etag'], 'Response ETag');

$fallbackResponse = $transport->fetch(
    OSM_TILE_TEST_URL,
    [
        'timeoutMilliseconds' => 1500,
        'maximumResponseBytes' => 512 * 1024,
        'followRedirects' => false,
        'requirePublicPeerAddress' => true,
    ],
    [],
    static fn (): array => ['1.1.1.1'],
    static fn (): array => osmTransportResponse([
        'headers' => ['Content-Type' => 'image/png'],
    ]),
    OSM_TILE_TEST_NOW
);
assertSameValue(604_800, $fallbackResponse['cacheTtlSeconds'], 'Fallback cache TTL');

$noStoreResponse = $transport->fetch(
    OSM_TILE_TEST_URL,
    [
        'timeoutMilliseconds' => 1500,
        'maximumResponseBytes' => 512 * 1024,
        'followRedirects' => false,
        'requirePublicPeerAddress' => true,
    ],
    [],
    static fn (): array => ['1.1.1.1'],
    static fn (): array => osmTransportResponse([
        'headers' => [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ],
    ]),
    OSM_TILE_TEST_NOW
);
assertSameValue(false, $noStoreResponse['cacheable'], 'No-store cacheability');
assertSameValue(0, $noStoreResponse['cacheTtlSeconds'], 'No-store TTL');

$sharedAgeResponse = $transport->fetch(
    OSM_TILE_TEST_URL,
    [
        'timeoutMilliseconds' => 1500,
        'maximumResponseBytes' => 512 * 1024,
        'followRedirects' => false,
        'requirePublicPeerAddress' => true,
    ],
    [],
    static fn (): array => ['1.1.1.1'],
    static fn (): array => osmTransportResponse([
        'headers' => [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=900, s-maxage=1200',
            'Age' => '200',
        ],
    ]),
    OSM_TILE_TEST_NOW
);
assertSameValue(1000, $sharedAgeResponse['cacheTtlSeconds'], 'Shared max-age');

foreach (
    [
        static fn (): array => ['127.0.0.1'],
        static fn (): array => ['1.1.1.1', '10.0.0.1'],
        static fn (): array => [],
    ] as $unsafeDns
) {
    $executed = false;
    $rejected = false;
    try {
        $transport->fetch(
            OSM_TILE_TEST_URL,
            [
                'timeoutMilliseconds' => 1500,
                'maximumResponseBytes' => 512 * 1024,
                'followRedirects' => false,
                'requirePublicPeerAddress' => true,
            ],
            [],
            $unsafeDns,
            static function () use (&$executed): array {
                $executed = true;
                return osmTransportResponse();
            },
            OSM_TILE_TEST_NOW
        );
    } catch (\RuntimeException) {
        $rejected = true;
    }
    assertTrue($rejected, 'Unsafe DNS result must fail closed.');
    assertSameValue(false, $executed, 'Unsafe DNS must block the executor.');
}

foreach (
    [
        ['primaryAddress' => '8.8.8.8'],
        ['primaryAddress' => '127.0.0.1'],
        ['effectiveUrl' => 'https://other.example.test/10/543/352.png'],
        ['redirected' => true],
        ['elapsedMilliseconds' => 1501],
        ['body' => str_repeat('x', 512 * 1024 + 1)],
    ] as $invalidResponse
) {
    $rejected = false;
    try {
        $transport->fetch(
            OSM_TILE_TEST_URL,
            [
                'timeoutMilliseconds' => 1500,
                'maximumResponseBytes' => 512 * 1024,
                'followRedirects' => false,
                'requirePublicPeerAddress' => true,
            ],
            [],
            static fn (): array => ['1.1.1.1'],
            static fn (): array => osmTransportResponse($invalidResponse),
            OSM_TILE_TEST_NOW
        );
    } catch (\RuntimeException) {
        $rejected = true;
    }
    assertTrue($rejected, 'Unsafe transport response must fail closed.');
}

foreach (
    [
        'http://tile.openstreetmap.org/10/543/352.png',
        'https://tile.openstreetmap.org/10/543/352.png?token=x',
        'https://tile.openstreetmap.org/10/543/../352.png',
        'https://other.example.test/10/543/352.png',
    ] as $unauthorizedUrl
) {
    $rejected = false;
    try {
        $transport->fetch(
            $unauthorizedUrl,
            [
                'timeoutMilliseconds' => 1500,
                'maximumResponseBytes' => 512 * 1024,
                'followRedirects' => false,
                'requirePublicPeerAddress' => true,
            ],
            [],
            static fn (): array => ['1.1.1.1'],
            static fn (): array => osmTransportResponse(),
            OSM_TILE_TEST_NOW
        );
    } catch (\InvalidArgumentException) {
        $rejected = true;
    }
    assertTrue($rejected, 'Unauthorized transport URL must fail closed.');
}

$headerRejected = false;
try {
    $transport->fetch(
        OSM_TILE_TEST_URL,
        [
            'timeoutMilliseconds' => 1500,
            'maximumResponseBytes' => 512 * 1024,
            'followRedirects' => false,
            'requirePublicPeerAddress' => true,
        ],
        ['If-None-Match' => "bad\r\nInjected: value"],
        static fn (): array => ['1.1.1.1'],
        static fn (): array => osmTransportResponse(),
        OSM_TILE_TEST_NOW
    );
} catch (\InvalidArgumentException) {
    $headerRejected = true;
}
assertTrue($headerRejected, 'Conditional header injection must fail closed.');

fwrite(STDOUT, "OSM tile transport tests passed.\n");
