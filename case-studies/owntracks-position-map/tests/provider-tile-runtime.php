<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksProviderTileCache;
use OwnTracksPositionMap\Prototype\OwnTracksProviderTileRuntime;
use OwnTracksPositionMap\Prototype\OwnTracksTileMissStateStore;
use OwnTracksPositionMap\Prototype\OwnTracksTileRequestBudget;
use OwnTracksPositionMap\Prototype\OwnTracksTileSelectionAllowlist;

require_once __DIR__ . '/bootstrap.php';

const PROVIDER_RUNTIME_NOW = 1_725_184_000;

function providerRuntimePng(): string
{
    $content = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lE'
        . 'QVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($content)) {
        throw new RuntimeException('Provider runtime PNG is invalid.');
    }

    return $content;
}

/** @return array<string, mixed> */
function providerRuntimeResponse(int $status, array $overrides = []): array
{
    return array_replace([
        'status' => $status,
        'contentType' => $status === 200 ? 'image/png' : '',
        'redirected' => false,
        'elapsedMilliseconds' => 10,
        'peerAddress' => '1.1.1.1',
        'body' => $status === 200 ? providerRuntimePng() : '',
        'cacheTtlSeconds' => 100,
        'cacheable' => true,
        'etag' => '"provider-runtime-etag"',
        'lastModified' => 'Mon, 01 Sep 2025 00:00:00 GMT',
    ], $overrides);
}

function providerRuntimeRoot(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-provider-runtime-'
        . bin2hex(random_bytes(8));
}

function removeProviderRuntimeRoot(string $root): void
{
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-provider-runtime-';
    if (!str_starts_with($root, $prefix) || !is_dir($root) || is_link($root)) {
        throw new RuntimeException('Refusing unsafe provider-runtime cleanup.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || $entry->isLink()) {
            throw new RuntimeException('Provider-runtime cleanup entry is unsafe.');
        }
        if ($entry->isFile()) {
            if (!unlink($entry->getPathname())) {
                throw new RuntimeException('Provider-runtime cleanup failed.');
            }
        } elseif ($entry->isDir() && !rmdir($entry->getPathname())) {
            throw new RuntimeException('Provider-runtime directory cleanup failed.');
        }
    }
    if (!rmdir($root)) {
        throw new RuntimeException('Provider-runtime root cleanup failed.');
    }
}

$root = providerRuntimeRoot();
try {
    $fitBounds = [
        'west' => 20.0,
        'south' => 10.0,
        'east' => 20.1,
        'north' => 10.1,
        'crossesAntimeridian' => false,
    ];
    $allowlist = OwnTracksTileSelectionAllowlist::fromFitBounds(
        $fitBounds,
        8,
        10,
        1,
        128
    );
    $allowedTiles = [];
    for ($x = 0; $x < 1024 && count($allowedTiles) < 2; $x++) {
        for ($y = 0; $y < 1024; $y++) {
            if ($allowlist->allows(10, $x, $y)) {
                $allowedTiles[] = [10, $x, $y];
                break;
            }
        }
    }
    assertSameValue(2, count($allowedTiles), 'Provider runtime allowlist size');
    [$zoom, $tileX, $tileY] = $allowedTiles[0];
    [$budgetZoom, $budgetX, $budgetY] = $allowedTiles[1];
    $runtime = new OwnTracksProviderTileRuntime(
        $allowlist,
        [
            'mode' => 'fixed-https-xyz',
            'origin' => 'https://tile.openstreetmap.org',
            'pathTemplate' => '/{z}/{x}/{y}.png',
            'maximumZoom' => 10,
            'maximumRequestsPerSelection' => 8,
            // Unknown failed transfers retain a worst-case byte reservation.
            'maximumBytesPerSelection' => 1024 * 1024,
            'timeoutMilliseconds' => 1500,
            'negativeTtlSeconds' => 60,
        ],
        new OwnTracksProviderTileCache(
            $root . '/cache',
            'provider-runtime-policy-v1'
        ),
        new OwnTracksTileMissStateStore($root . '/state'),
        new OwnTracksTileRequestBudget($root . '/budget'),
        hash('sha256', 'synthetic-viewport'),
        hash('sha256', 'synthetic-provider-budget')
    );

    $providerCalls = 0;
    $static = $runtime->read(
        $zoom,
        $tileX,
        $tileY,
        static fn (): array => ['content' => 'static-authority-fallback'],
        static function () use (&$providerCalls): array {
            $providerCalls++;
            return providerRuntimeResponse(200);
        },
        PROVIDER_RUNTIME_NOW,
        30,
        2
    );
    assertSameValue(providerRuntimePng(), $static['content'] ?? null, 'Provider priority');
    assertSameValue(1, $providerCalls, 'Provider priority fetch count');

    $dynamic = $runtime->read(
        $zoom,
        $tileX,
        $tileY,
        static fn (): ?array => null,
        static function () use (&$providerCalls): array {
            $providerCalls++;
            return providerRuntimeResponse(200);
        },
        PROVIDER_RUNTIME_NOW,
        30,
        2
    );
    assertSameValue(providerRuntimePng(), $dynamic['content'] ?? null, 'Dynamic tile');
    assertSameValue(1, $providerCalls, 'Dynamic cache provider calls');

    $cached = $runtime->read(
        $zoom,
        $tileX,
        $tileY,
        static fn (): array => ['content' => 'static-authority-fallback'],
        static function () use (&$providerCalls): array {
            $providerCalls++;
            throw new RuntimeException('Fresh cache unexpectedly fetched.');
        },
        PROVIDER_RUNTIME_NOW + 1,
        30,
        2
    );
    assertSameValue(providerRuntimePng(), $cached['content'] ?? null, 'Fresh provider cache');
    assertSameValue(1, $providerCalls, 'Fresh cache provider calls');

    $failed = $runtime->read(
        $zoom,
        $tileX,
        $tileY,
        static fn (): array => ['content' => 'static-authority-fallback'],
        static function () use (&$providerCalls): array {
            $providerCalls++;
            throw new RuntimeException('Synthetic provider failure.');
        },
        PROVIDER_RUNTIME_NOW + 101,
        30,
        2
    );
    assertSameValue(
        'static-authority-fallback',
        $failed['content'] ?? null,
        'Stale provider failure must fall back to static content.'
    );
    assertSameValue(2, $providerCalls, 'Stale failure provider calls');

    $conditionalHeaders = null;
    $revalidated = $runtime->read(
        $zoom,
        $tileX,
        $tileY,
        static fn (): ?array => null,
        static function (
            string $url,
            array $options,
            array $headers
        ) use (
            &$providerCalls,
            &$conditionalHeaders
        ): array {
            $providerCalls++;
            $conditionalHeaders = $headers;
            return providerRuntimeResponse(304, ['cacheTtlSeconds' => 200]);
        },
        PROVIDER_RUNTIME_NOW + 162,
        30,
        2
    );
    assertSameValue(providerRuntimePng(), $revalidated['content'] ?? null, '304 content');
    assertSameValue(
        '"provider-runtime-etag"',
        $conditionalHeaders['If-None-Match'] ?? null,
        '304 conditional ETag'
    );

    $outsideCalls = 0;
    $outside = $runtime->read(
        10,
        0,
        0,
        static fn (): ?array => null,
        static function () use (&$outsideCalls): array {
            $outsideCalls++;
            return providerRuntimeResponse(200);
        },
        PROVIDER_RUNTIME_NOW + 163,
        30,
        2
    );
    assertSameValue(null, $outside, 'Outside selection result');
    assertSameValue(0, $outsideCalls, 'Outside selection provider calls');

    $oversizedProviderCalls = 0;
    $maximumBody = "\x89PNG\r\n\x1A\n"
        . str_repeat('x', 512 * 1024 - 8);
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $overBudget = $runtime->read(
            $budgetZoom,
            $budgetX,
            $budgetY,
            static fn (): ?array => null,
            static function () use (
                &$oversizedProviderCalls,
                $maximumBody
            ): array {
                $oversizedProviderCalls++;
                return providerRuntimeResponse(200, ['body' => $maximumBody]);
            },
            PROVIDER_RUNTIME_NOW + 164 + $attempt,
            30,
            2
        );
        assertSameValue(
            null,
            $overBudget,
            'Over-budget provider response must not be returned.'
        );
    }
    assertSameValue(
        1,
        $oversizedProviderCalls,
        'A failed over-budget transfer must retain its charge and block another fetch.'
    );

    $state = file_get_contents($root . '/state/state.json');
    assertTrue(is_string($state), 'Provider runtime state is missing.');
    assertTrue(
        !str_contains($state, $zoom . '/' . $tileX . '/' . $tileY)
            && !str_contains($state, (string) $fitBounds['west']),
        'Provider runtime state exposed spatial selection data.'
    );

    $sharedBudgetKey = hash('sha256', 'shared-provider-minute-budget');
    $firstViewportRuntime = new OwnTracksProviderTileRuntime(
        $allowlist,
        [
            'mode' => 'fixed-https-xyz',
            'origin' => 'https://tile.openstreetmap.org',
            'pathTemplate' => '/{z}/{x}/{y}.png',
            'maximumZoom' => 10,
            'maximumRequestsPerSelection' => 24,
            'maximumBytesPerSelection' => 4 * 1024 * 1024,
            'timeoutMilliseconds' => 1500,
            'negativeTtlSeconds' => 60,
        ],
        new OwnTracksProviderTileCache(
            $root . '/separate-cache-a',
            'provider-runtime-policy-v1'
        ),
        new OwnTracksTileMissStateStore($root . '/separate-state'),
        new OwnTracksTileRequestBudget($root . '/shared-budget'),
        hash('sha256', 'viewport-a'),
        $sharedBudgetKey
    );
    $secondViewportRuntime = new OwnTracksProviderTileRuntime(
        $allowlist,
        [
            'mode' => 'fixed-https-xyz',
            'origin' => 'https://tile.openstreetmap.org',
            'pathTemplate' => '/{z}/{x}/{y}.png',
            'maximumZoom' => 10,
            'maximumRequestsPerSelection' => 24,
            'maximumBytesPerSelection' => 4 * 1024 * 1024,
            'timeoutMilliseconds' => 1500,
            'negativeTtlSeconds' => 60,
        ],
        new OwnTracksProviderTileCache(
            $root . '/separate-cache-b',
            'provider-runtime-policy-v1'
        ),
        new OwnTracksTileMissStateStore($root . '/separate-state'),
        new OwnTracksTileRequestBudget($root . '/shared-budget'),
        hash('sha256', 'viewport-b'),
        $sharedBudgetKey
    );
    $sharedBudgetCalls = 0;
    $firstViewportRuntime->read(
        $zoom,
        $tileX,
        $tileY,
        static fn (): ?array => null,
        static function () use (&$sharedBudgetCalls): array {
            $sharedBudgetCalls++;
            return providerRuntimeResponse(200);
        },
        PROVIDER_RUNTIME_NOW + 300,
        1,
        2
    );
    $secondBudgetResult = $secondViewportRuntime->read(
        $budgetZoom,
        $budgetX,
        $budgetY,
        static fn (): ?array => null,
        static function () use (&$sharedBudgetCalls): array {
            $sharedBudgetCalls++;
            return providerRuntimeResponse(200);
        },
        PROVIDER_RUNTIME_NOW + 301,
        1,
        2
    );
    assertSameValue(
        null,
        $secondBudgetResult,
        'A new viewport bypassed the stable provider minute budget.'
    );
    assertSameValue(
        1,
        $sharedBudgetCalls,
        'Stable provider minute budget did not span viewport keys.'
    );
    $retriedBudgetResult = $secondViewportRuntime->read(
        $budgetZoom,
        $budgetX,
        $budgetY,
        static fn (): ?array => null,
        static function () use (&$sharedBudgetCalls): array {
            $sharedBudgetCalls++;
            return providerRuntimeResponse(200);
        },
        PROVIDER_RUNTIME_NOW + 360,
        1,
        2
    );
    assertTrue(
        is_array($retriedBudgetResult),
        'Deferred provider admission could not retry in the next minute.'
    );
    assertSameValue(
        2,
        $sharedBudgetCalls,
        'Deferred provider admission reached the transport prematurely.'
    );
} finally {
    if (is_dir($root)) {
        removeProviderRuntimeRoot($root);
    }
}

fwrite(STDOUT, "Provider tile-runtime tests passed.\n");
