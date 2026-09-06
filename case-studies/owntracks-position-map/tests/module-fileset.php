<?php

declare(strict_types=1);

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 3));
$builder = $projectRoot . '/tools/build-symcon-module-fileset.php';
$validator = $projectRoot
    . '/case-studies/owntracks-position-map/tools/validate-distribution.php';
$manifest = $projectRoot
    . '/deployments/symcon/owntracks-position-map-module.fileset.json';
$relativeRoot = 'dist/symcon/saef-owntracks-position-map-module';
$temporaryRoots = [];

try {
    $temporaryRoots = [
        ownTracksTemporaryRoot('owntracks-module-a-'),
        ownTracksTemporaryRoot('owntracks-module-b-'),
    ];
    foreach ($temporaryRoots as $temporaryRoot) {
        runOwnTracksCommand([
            PHP_BINARY,
            $builder,
            '--output-root=' . $temporaryRoot,
            $manifest,
        ]);
    }

    $firstRoot = $temporaryRoots[0] . '/' . $relativeRoot;
    $secondRoot = $temporaryRoots[1] . '/' . $relativeRoot;
    $first = ownTracksFileHashes($firstRoot);
    $second = ownTracksFileHashes($secondRoot);
    $tracked = ownTracksFileHashes($projectRoot . '/' . $relativeRoot);
    ownTracksSame($first, $second, 'Independent module filesets differ.');
    ownTracksSame($first, $tracked, 'Tracked OwnTracks module fileset is stale.');
    ownTracksSame(37, count($first), 'Generated fileset inventory count differs.');

    $sourceMap = json_decode(
        (string) file_get_contents($firstRoot . '/fileset.sources.json'),
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    ownTracksSame(
        'saef-owntracks-position-map-module',
        $sourceMap['name'] ?? null,
        'Fileset source-map identity differs.'
    );
    ownTracksSame(35, count($sourceMap['files'] ?? []), 'Payload count differs.');
    foreach ($sourceMap['files'] ?? [] as $mapping) {
        if (!is_array($mapping)) {
            throw new RuntimeException('Fileset source-map entry is invalid.');
        }
        $source = $mapping['source'] ?? null;
        $target = $mapping['target'] ?? null;
        $expectedHash = $mapping['sha256'] ?? null;
        if (!is_string($source) || !is_string($target) || !is_string($expectedHash)) {
            throw new RuntimeException('Fileset source-map fields are invalid.');
        }
        ownTracksSame(
            hash_file('sha256', $projectRoot . '/' . $source),
            $expectedHash,
            'Canonical source hash differs.'
        );
        ownTracksSame(
            $expectedHash,
            hash_file('sha256', $firstRoot . '/' . $target),
            'Generated target is not byte-exact.'
        );
    }

    runOwnTracksCommand([PHP_BINARY, $validator, $firstRoot]);
    runOwnTracksCommand([PHP_BINARY, $validator]);
    runOwnTracksCommand([PHP_BINARY, $builder, '--check', $manifest]);

    $staleFile = $firstRoot . '/stale.txt';
    if (file_put_contents($staleFile, "stale\n") === false) {
        throw new RuntimeException('Cannot create stale-target regression file.');
    }
    $staleCheck = executeOwnTracksCommand([
        PHP_BINARY,
        $builder,
        '--check',
        '--output-root=' . $temporaryRoots[0],
        $manifest,
    ]);
    ownTracksSame(1, $staleCheck['status'], 'Fileset check accepted stale content.');
    if (!str_contains($staleCheck['stderr'], 'additional targets')) {
        throw new RuntimeException('Stale-target rejection was not classified.');
    }

    fwrite(STDOUT, "OwnTracks module fileset tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'OwnTracks module fileset tests failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeOwnTracksTree($temporaryRoot);
    }
}

/** @param list<string> $command */
function runOwnTracksCommand(array $command): void
{
    $result = executeOwnTracksCommand($command);
    if ($result['status'] !== 0) {
        throw new RuntimeException(
            'OwnTracks subprocess failed: '
            . trim($result['stderr'] . $result['stdout'])
        );
    }
}

/** @param list<string> $command @return array{status: int, stdout: string, stderr: string} */
function executeOwnTracksCommand(array $command): array
{
    $pipes = [];
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start OwnTracks test subprocess.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'status' => proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

function ownTracksTemporaryRoot(string $prefix): string
{
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Cannot create OwnTracks temporary root.');
    }

    return $path;
}

/** @return array<string, string> */
function ownTracksFileHashes(string $root): array
{
    if (!is_dir($root) || is_link($root)) {
        throw new RuntimeException('OwnTracks fileset root is missing or unsafe.');
    }
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || !$entry->isFile() || $entry->isLink()) {
            continue;
        }
        $relative = str_replace(
            '\\',
            '/',
            substr($entry->getPathname(), strlen($root) + 1)
        );
        $hash = hash_file('sha256', $entry->getPathname());
        if (!is_string($hash)) {
            throw new RuntimeException('OwnTracks fileset target is unreadable.');
        }
        $hashes[$relative] = $hash;
    }
    ksort($hashes, SORT_STRING);

    return $hashes;
}

function removeOwnTracksTree(string $root): void
{
    $temporary = str_replace('\\', '/', rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR));
    $normalized = str_replace('\\', '/', $root);
    if (
        !str_starts_with($normalized, $temporary . '/owntracks-module-')
        || !is_dir($normalized)
        || is_link($normalized)
    ) {
        throw new RuntimeException('Refusing unsafe OwnTracks temporary cleanup.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($normalized, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            continue;
        }
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($normalized);
}

function ownTracksSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}
