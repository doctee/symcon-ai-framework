<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksProviderTileCache;
use OwnTracksPositionMap\Prototype\OwnTracksTileFileCache;

require_once __DIR__ . '/bootstrap.php';

const PROVIDER_CACHE_NOW = 1_725_184_000;
const PROVIDER_CACHE_REVISION = 'osm-standard-policy-v1';

function providerCacheRoot(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-provider-cache-'
        . bin2hex(random_bytes(8));
}

function removeProviderCacheRoot(string $root): void
{
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-provider-cache-';
    if (!str_starts_with($root, $prefix) || !is_dir($root) || is_link($root)) {
        throw new RuntimeException('Refusing unsafe provider-cache cleanup.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || $entry->isLink()) {
            throw new RuntimeException('Provider-cache cleanup entry is unsafe.');
        }
        if ($entry->isFile()) {
            if (!unlink($entry->getPathname())) {
                throw new RuntimeException('Provider-cache file cleanup failed.');
            }
            continue;
        }
        if ($entry->isDir() && !rmdir($entry->getPathname())) {
            throw new RuntimeException('Provider-cache directory cleanup failed.');
        }
    }
    if (!rmdir($root)) {
        throw new RuntimeException('Provider-cache root cleanup failed.');
    }
}

function providerCachePng(): string
{
    $content = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lE'
        . 'QVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($content)) {
        throw new RuntimeException('Provider-cache PNG fixture is invalid.');
    }

    return $content;
}

/** @return array<string, mixed> */
function providerCacheResponse(int $status, array $overrides = []): array
{
    return array_replace([
        'status' => $status,
        'body' => $status === 200 ? providerCachePng() : '',
        'cacheable' => true,
        'cacheTtlSeconds' => 100,
        'etag' => '"provider-cache-etag"',
        'lastModified' => 'Mon, 01 Sep 2025 00:00:00 GMT',
    ], $overrides);
}

$roots = [providerCacheRoot(), providerCacheRoot(), providerCacheRoot()];
try {
    $retainedBytes = new OwnTracksTileFileCache(
        $roots[0],
        604_800,
        2,
        1024 * 1024
    );
    $retainedBytes->write(
        'provider-bytes-v1',
        10,
        1,
        1,
        providerCachePng(),
        PROVIDER_CACHE_NOW
    );
    assertTrue(
        $retainedBytes->read(
            'provider-bytes-v1',
            10,
            1,
            1,
            PROVIDER_CACHE_NOW + 301
        ) !== null,
        'Configured byte retention ignored its TTL.'
    );
    assertSameValue(
        null,
        $retainedBytes->read(
            'provider-bytes-v1',
            10,
            1,
            1,
            PROVIDER_CACHE_NOW + 604_800
        ),
        'Configured byte retention failed to expire.'
    );

    $cache = new OwnTracksProviderTileCache(
        $roots[1],
        PROVIDER_CACHE_REVISION
    );
    assertSameValue(
        'miss',
        $cache->lookup(10, 543, 352, PROVIDER_CACHE_NOW)['state'],
        'Initial provider cache state'
    );
    $cache->store200(
        10,
        543,
        352,
        providerCacheResponse(200),
        PROVIDER_CACHE_NOW
    );
    $fresh = $cache->lookup(10, 543, 352, PROVIDER_CACHE_NOW + 1);
    assertSameValue('fresh', $fresh['state'], 'Fresh provider cache state');
    assertSameValue(providerCachePng(), $fresh['content'], 'Fresh cache content');
    assertSameValue(
        [],
        $fresh['conditionalHeaders'],
        'Fresh cache must not request revalidation.'
    );
    $stale = $cache->lookup(10, 543, 352, PROVIDER_CACHE_NOW + 101);
    assertSameValue('stale', $stale['state'], 'Stale provider cache state');
    assertSameValue(
        null,
        $stale['content'],
        'Stale content must not be exposed as a fallback response.'
    );
    assertSameValue(
        '"provider-cache-etag"',
        $stale['conditionalHeaders']['If-None-Match'],
        'Stale cache ETag'
    );
    assertSameValue(
        'Mon, 01 Sep 2025 00:00:00 GMT',
        $stale['conditionalHeaders']['If-Modified-Since'],
        'Stale cache Last-Modified'
    );
    assertSameValue(
        true,
        $cache->refresh304(
            10,
            543,
            352,
            providerCacheResponse(304, [
                'cacheTtlSeconds' => 200,
                'etag' => null,
                'lastModified' => null,
            ]),
            PROVIDER_CACHE_NOW + 101
        ),
        '304 cache refresh'
    );
    assertSameValue(
        'fresh',
        $cache->lookup(10, 543, 352, PROVIDER_CACHE_NOW + 102)['state'],
        'Revalidated provider cache state'
    );
    assertSameValue(
        'stale',
        $cache->lookup(10, 543, 352, PROVIDER_CACHE_NOW + 302)['state'],
        'Revalidated provider cache expiry is anchored at the 304 response.'
    );

    $beforeError = $cache->statistics(PROVIDER_CACHE_NOW + 302);
    $stillStale = $cache->lookup(10, 543, 352, PROVIDER_CACHE_NOW + 302);
    assertSameValue('stale', $stillStale['state'], 'Error-path stale state');
    assertSameValue(
        null,
        $stillStale['content'],
        'Transport failure must not expose stale content.'
    );
    assertSameValue(
        $beforeError['revalidations'],
        $cache->statistics(PROVIDER_CACHE_NOW + 302)['revalidations'],
        'Transport failure must not refresh cache metadata.'
    );

    $cache->store200(
        10,
        543,
        352,
        providerCacheResponse(200, [
            'cacheable' => false,
            'cacheTtlSeconds' => 0,
            'etag' => null,
            'lastModified' => null,
        ]),
        PROVIDER_CACHE_NOW + 303
    );
    assertSameValue(
        'miss',
        $cache->lookup(10, 543, 352, PROVIDER_CACHE_NOW + 303)['state'],
        'Non-cacheable response must discard content.'
    );
    assertSameValue(
        false,
        $cache->refresh304(
            10,
            544,
            352,
            providerCacheResponse(304),
            PROVIDER_CACHE_NOW + 304
        ),
        '304 without cached content must fail.'
    );

    $cache->store200(
        10,
        545,
        352,
        providerCacheResponse(200),
        PROVIDER_CACHE_NOW + 304
    );
    assertSameValue(
        false,
        $cache->refresh304(
            10,
            545,
            352,
            providerCacheResponse(304, [
                'cacheable' => false,
                'cacheTtlSeconds' => 0,
            ]),
            PROVIDER_CACHE_NOW + 405
        ),
        'Non-cacheable 304 must not refresh content.'
    );
    assertSameValue(
        'miss',
        $cache->lookup(10, 545, 352, PROVIDER_CACHE_NOW + 405)['state'],
        'Non-cacheable 304 must discard prior content.'
    );

    $validatorRejected = false;
    try {
        $cache->store200(
            10,
            543,
            352,
            providerCacheResponse(200, ['etag' => "bad\r\nvalue"]),
            PROVIDER_CACHE_NOW + 305
        );
    } catch (InvalidArgumentException) {
        $validatorRejected = true;
    }
    assertTrue($validatorRejected, 'Invalid cache validator must fail closed.');

    $cache->store200(
        10,
        543,
        352,
        providerCacheResponse(200),
        PROVIDER_CACHE_NOW + 400
    );
    $manifest = file_get_contents($roots[1] . '/metadata.json');
    assertTrue(is_string($manifest), 'Provider metadata manifest is missing.');
    assertTrue(
        !str_contains($manifest, PROVIDER_CACHE_REVISION)
            && !str_contains($manifest, '/10/543/352'),
        'Provider metadata manifest exposes its selection key.'
    );

    assertSameValue(
        1,
        file_put_contents($roots[1] . '/metadata.json', '{'),
        'Corrupt provider manifest fixture write'
    );
    assertSameValue(
        4,
        file_put_contents($roots[1] . '/unowned.txt', 'keep'),
        'Unowned provider-cache fixture write'
    );
    $recovered = new OwnTracksProviderTileCache(
        $roots[1],
        PROVIDER_CACHE_REVISION
    );
    assertSameValue(
        'miss',
        $recovered->lookup(10, 543, 352, PROVIDER_CACHE_NOW + 401)['state'],
        'Corrupt provider metadata must reset to a miss.'
    );
    assertTrue(
        is_file($roots[1] . '/unowned.txt'),
        'Provider metadata recovery removed an unowned file.'
    );

    $bounded = new OwnTracksProviderTileCache(
        $roots[2],
        PROVIDER_CACHE_REVISION
    );
    for ($tileX = 0; $tileX < 513; $tileX++) {
        $bounded->store200(
            10,
            $tileX,
            1,
            providerCacheResponse(200),
            PROVIDER_CACHE_NOW
        );
    }
    assertSameValue(
        512,
        $bounded->statistics(PROVIDER_CACHE_NOW)['entries'],
        'Provider cache entry bound'
    );
    assertSameValue(
        'miss',
        $bounded->lookup(10, 0, 1, PROVIDER_CACHE_NOW)['state'],
        'Provider cache LRU eviction'
    );
    $bounded->clear();
    assertSameValue(
        0,
        $bounded->statistics(PROVIDER_CACHE_NOW)['entries'],
        'Provider cache clear boundary'
    );
} finally {
    foreach ($roots as $root) {
        if (is_dir($root)) {
            removeProviderCacheRoot($root);
        }
    }
}

fwrite(STDOUT, "Provider tile-cache tests passed.\n");
