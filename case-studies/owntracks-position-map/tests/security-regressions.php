<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksPinnedHttpsTileTransport;
use OwnTracksPositionMap\Prototype\OwnTracksProviderTileCache;
use OwnTracksPositionMap\Prototype\OwnTracksProviderTileRuntime;
use OwnTracksPositionMap\Prototype\OwnTracksTileDeadline;
use OwnTracksPositionMap\Prototype\OwnTracksTileMissResolver;
use OwnTracksPositionMap\Prototype\OwnTracksTileMissStateStore;
use OwnTracksPositionMap\Prototype\OwnTracksTileRequestBudget;
use OwnTracksPositionMap\Prototype\OwnTracksTileSelectionAllowlist;

require_once __DIR__ . '/bootstrap.php';

if (($argv[1] ?? '') === 'worker') {
    echo "ready\n";
    flush();
    try {
        if ($argv[2] === 'state') {
            (new OwnTracksTileMissStateStore($argv[3]))->withSelection(
                hash('sha256', 'concurrent'),
                1000,
                static fn (array &$state): null => null
            );
        } else {
            (new OwnTracksProviderTileCache($argv[3], 'synthetic'))->lookup(0, 0, 0, 1000);
        }
        echo "complete\n";
    } catch (RuntimeException $exception) {
        echo str_contains($exception->getMessage(), 'deadline') ? "deadline\n" : "unexpected\n";
    }
    exit(0);
}

$root = rtrim(sys_get_temp_dir(), '/\\') . '/owntracks-security-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) {
    throw new RuntimeException('Security fixture root unavailable.');
}
$children = [];
try {
    foreach (
        ['state' => ['.state.lock', '.state-', 'state.json'],
        'metadata' => ['.metadata.lock', '.metadata-', 'metadata.json']] as $kind => [$lockName, $prefix, $file]
    ) {
        $directory = $root . '/' . $kind;
        if ($kind === 'state') {
            (new OwnTracksTileMissStateStore($directory))->withSelection(
                hash('sha256', 'concurrent'),
                1000,
                static fn (array &$state): null => null
            );
        } else {
            (new OwnTracksProviderTileCache($directory, 'synthetic'))->lookup(0, 0, 0, 1000);
        }
        foreach ([false, true] as $timeoutCase) {
            $lock = fopen($directory . '/' . $lockName, 'c+b');
            assertTrue(is_resource($lock) && flock($lock, LOCK_EX), 'Fixture lock unavailable.');
            $temporary = $directory . '/' . $prefix . str_repeat('a', 32);
            file_put_contents($temporary, file_get_contents($directory . '/' . $file));
            $process = proc_open([PHP_BINARY, __FILE__, 'worker', $kind, $directory], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes);
            assertTrue(is_resource($process), 'Fixture child unavailable.');
            $children[] = $process;
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 2);
            assertSameValue("ready\n", fgets($pipes[1]), 'Child did not start.');
            $start = hrtime(true);
            if ($timeoutCase) {
                assertSameValue("deadline\n", fgets($pipes[1]), 'Contended request did not time out.');
                assertTrue(hrtime(true) - $start < 1500000000, 'Lock wait exceeded bound.');
            } else {
                usleep(50000);
            }
            clearstatcache(true, $temporary);
            assertTrue(is_file($temporary), 'Waiting request deleted active writer temporary.');
            assertTrue(rename($temporary, $directory . '/' . $file), 'Writer could not commit.');
            flock($lock, LOCK_UN);
            fclose($lock);
            if (!$timeoutCase) {
                assertSameValue("complete\n", fgets($pipes[1]), 'Waiting request failed after release.');
            }
            assertSameValue('', stream_get_contents($pipes[2]), 'Child emitted an error.');
            fclose($pipes[1]);
            fclose($pipes[2]);
            assertSameValue(0, proc_close($process), 'Child failed.');
            array_pop($children);
        }
    }

    $key = hash('sha256', 'synthetic-reservation');
    $store = new OwnTracksTileMissStateStore($root . '/reservations');
    $allowlist = OwnTracksTileSelectionAllowlist::fromFitBounds(
        ['west' => 10.9, 'south' => 48.0, 'east' => 11.0, 'north' => 48.1,
            'crossesAntimeridian' => false],
        1,
        1,
        0,
        8
    );
    $config = [
        'mode' => 'fixed-https-xyz', 'origin' => 'https://tiles.example.test',
        'pathTemplate' => '/{z}/{x}/{y}.png', 'maximumZoom' => 18,
        'maximumRequestsPerSelection' => 48, 'maximumBytesPerSelection' => 4 * 1024 * 1024,
        'timeoutMilliseconds' => 1500, 'negativeTtlSeconds' => 60,
    ];
    $resolver = new OwnTracksTileMissResolver($allowlist, $config, $key);
    $reserve = static fn (array &$state): ?array => $resolver->reserve(1, 1, 0, $state, 1000);
    $first = $store->withSelection($key, 1000, $reserve);
    $second = $store->withSelection($key, 1000, $reserve);
    assertTrue(is_array($first) && is_array($second), 'Concurrent reservations unavailable.');
    $state = $store->withSelection($key, 1000, static fn (array &$s): array => $s);
    assertSameValue(2, $state['upstreamRequests'], 'Requests not reserved before I/O.');
    assertSameValue(1024 * 1024, $state['upstreamBytes'], 'Worst-case bytes not reserved.');
    $png = "\x89PNG\r\n\x1A\nsynthetic";
    $response = ['status' => 200, 'contentType' => 'image/png', 'redirected' => false,
        'elapsedMilliseconds' => 1, 'peerAddress' => '1.1.1.1', 'body' => $png];
    foreach ([$second, $first, $first] as $reservation) {
        $store->withSelection($key, 1001, static fn (array &$s): ?array =>
            $resolver->complete($reservation, $response, $s, 1001));
    }
    $state = $store->withSelection($key, 1001, static fn (array &$s): array => $s);
    assertSameValue(2, $state['upstreamSuccesses'], 'Duplicate completion was counted.');
    assertSameValue(2 * strlen($png), $state['upstreamBytes'], 'Concurrent completion lost accounting.');

    // In-flight reservations remain charged after an interrupted worker expires.
    $store->withSelection($key, 1002, $reserve);
    $store->withSelection($key, 1020, static fn (array &$s): ?array => $resolver->reserve(1, 1, 0, $s, 1020));
    $state = $store->withSelection($key, 1020, static fn (array &$s): array => $s);
    assertSameValue(4, $state['upstreamRequests'], 'Interrupted request charge lost.');
    assertSameValue(1, count($state['pendingReservations']), 'Expired reservation not pruned.');

    $statePath = $root . '/reservations/state.json';
    $v2 = json_decode(file_get_contents($statePath), true, 16, JSON_THROW_ON_ERROR);
    $snapshot = file_get_contents($statePath);
    $legacy = json_decode(OwnTracksTileMissStateStore::prepareLegacyRollback($snapshot), true);
    assertSameValue(1, $legacy['version'], 'Legacy rollback format differs.');
    assertSameValue($snapshot, file_get_contents($statePath), 'Rollback preparation changed live-format state.');
    assertSameValue(
        $v2['selections'][$key]['state']['upstreamBytes'],
        $legacy['selections'][$key]['state']['upstreamBytes'],
        'Rollback refunded pending byte charges.'
    );
    assertTrue(!isset($legacy['selections'][$key]['state']['pendingReservations']), 'Legacy pending ledger retained.');
    $rollbackOutput = $root . '/rollback-v1.json';
    $rollbackProcess = proc_open([
        PHP_BINARY, __DIR__ . '/../tools/prepare-miss-state-rollback.php',
        '--prepare-legacy', $statePath, hash('sha256', $snapshot), $rollbackOutput,
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $rollbackPipes);
    assertTrue(is_resource($rollbackProcess), 'Rollback fixture process unavailable.');
    fclose($rollbackPipes[0]);
    $rollbackResult = json_decode(stream_get_contents($rollbackPipes[1]), true);
    $rollbackError = stream_get_contents($rollbackPipes[2]);
    fclose($rollbackPipes[1]);
    fclose($rollbackPipes[2]);
    assertSameValue(0, proc_close($rollbackProcess), 'Rollback tool failed: ' . $rollbackError);
    assertSameValue(true, $rollbackResult['sourceUnchanged'], 'Rollback tool changed source snapshot.');
    assertSameValue($legacy, json_decode(file_get_contents($rollbackOutput), true), 'Rollback tool output differs.');
    file_put_contents($statePath, json_encode($legacy, JSON_THROW_ON_ERROR));
    $migrated = $store->withSelection($key, 1021, static fn (array &$s): array => $s);
    assertSameValue($state['upstreamBytes'], $migrated['upstreamBytes'], 'Legacy migration reset consumed bytes.');
    assertSameValue(4, $migrated['upstreamRequests'], 'Legacy migration reset consumed requests.');
    foreach (['{', str_repeat('x', 256 * 1024 + 1)] as $invalid) {
        file_put_contents($statePath, $invalid);
        $called = false;
        try {
            $store->withSelection($key, 1022, static function (array &$s) use (&$called): void {
                $called = true;
            });
            throw new LogicException('Corrupt state was accepted.');
        } catch (RuntimeException) {
            assertSameValue(false, $called, 'Operation ran with corrupt state.');
            assertSameValue(hash('sha256', $invalid), hash_file('sha256', $statePath), 'Corrupt evidence was removed.');
        }
    }

    $runtime = new OwnTracksProviderTileRuntime(
        $allowlist,
        $config,
        new OwnTracksProviderTileCache($root . '/runtime-cache', 'synthetic'),
        new OwnTracksTileMissStateStore($root . '/runtime-state'),
        new OwnTracksTileRequestBudget($root . '/runtime-budget'),
        $key
    );
    $networkOutsideLock = false;
    $tile = $runtime->read(
        1,
        1,
        0,
        static fn (): null => null,
        static function () use ($root, &$networkOutsideLock, $response): array {
            $lock = fopen($root . '/runtime-state/.state.lock', 'c+b');
            $networkOutsideLock = flock($lock, LOCK_EX | LOCK_NB);
            if ($networkOutsideLock) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
            $json = json_decode(file_get_contents($root . '/runtime-state/state.json'), true);
            $entry = reset($json['selections']);
            assertSameValue(1, $entry['state']['upstreamRequests'], 'Budget not persisted before network.');
            return $response + ['cacheable' => false];
        },
        1000,
        60,
        2
    );
    assertTrue($networkOutsideLock, 'Network ran inside the global state lock.');
    assertSameValue($png, $tile['content'] ?? null, 'Reserved request did not complete.');

    $transport = new OwnTracksPinnedHttpsTileTransport([
        'origin' => 'https://tiles.example.test', 'pathTemplate' => '/{z}/{x}/{y}.png',
        'userAgent' => 'OfflineSecurityTest/1.0', 'refererOrigin' => 'https://connect.symcon.de/',
        'timeoutMilliseconds' => 250, 'maximumResponseBytes' => 524288, 'fallbackCacheTtlSeconds' => 604800,
    ]);
    $executed = false;
    try {
        $transport->fetch(
            'https://tiles.example.test/0/0/0.png',
            [
            'timeoutMilliseconds' => 250, 'maximumResponseBytes' => 524288,
            'followRedirects' => false, 'requirePublicPeerAddress' => true,
            ],
            [],
            static function (): array {
                usleep(300000);
                return ['1.1.1.1'];
            },
            static function () use (&$executed): array {
                $executed = true;
                return [];
            },
            1000
        );
        throw new LogicException('Expired DNS deadline was accepted.');
    } catch (RuntimeException) {
        assertSameValue(false, $executed, 'HTTP executor ran after DNS deadline.');
    }
    $expired = new OwnTracksTileDeadline(1);
    usleep(2000);
    try {
        $expired->remainingMilliseconds();
        throw new LogicException('Expired operation deadline accepted.');
    } catch (RuntimeException) {
        // Expected; no I/O follows an exhausted operation budget.
    }
    echo "OwnTracks security regression tests passed.\n";
} finally {
    foreach ($children as $child) {
        if (is_resource($child)) {
            proc_terminate($child);
            proc_close($child);
        }
    }
    // Only the exact, newly created fixture directory is ever removed.
    $expectedPrefix = rtrim(sys_get_temp_dir(), '/\\') . '/owntracks-security-';
    if (str_starts_with($root, $expectedPrefix) && is_dir($root) && !is_link($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }
}
