<?php

declare(strict_types=1);

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 3));
$builder = $projectRoot . '/tools/build-symcon-module-fileset.php';
$validator = $projectRoot . '/case-studies/media-carousel/tools/validate-distribution.php';
$manifest = $projectRoot . '/deployments/symcon/media-carousel-module.fileset.json';
$relativeRoot = 'dist/symcon/saef-media-carousel-module';
$temporaryRoots = [];

try {
    $temporaryRoots = [
        mediaCarouselTemporaryRoot('media-carousel-fileset-a-'),
        mediaCarouselTemporaryRoot('media-carousel-fileset-b-'),
    ];
    foreach ($temporaryRoots as $temporaryRoot) {
        runMediaCarouselCommand([
            PHP_BINARY,
            $builder,
            '--output-root=' . $temporaryRoot,
            $manifest,
        ]);
    }

    $firstRoot = $temporaryRoots[0] . '/' . $relativeRoot;
    $secondRoot = $temporaryRoots[1] . '/' . $relativeRoot;
    $first = mediaCarouselFileHashes($firstRoot);
    $second = mediaCarouselFileHashes($secondRoot);
    $tracked = mediaCarouselFileHashes($projectRoot . '/' . $relativeRoot);
    mediaCarouselSame($first, $second, 'Independent module fileset builds differ.');
    mediaCarouselSame($first, $tracked, 'Tracked MediaCarousel fileset is stale.');

    $expectedFiles = [
        'MediaCarousel/carousel.js',
        'MediaCarousel/form.json',
        'MediaCarousel/locale.json',
        'MediaCarousel/module.html',
        'MediaCarousel/module.json',
        'MediaCarousel/module.php',
        'fileset.sha256',
        'fileset.sources.json',
        'library.json',
    ];
    mediaCarouselSame($expectedFiles, array_keys($first), 'Generated fileset inventory differs.');

    $sourceMap = json_decode(
        (string) file_get_contents($firstRoot . '/fileset.sources.json'),
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    mediaCarouselSame(
        'saef-media-carousel-module',
        $sourceMap['name'] ?? null,
        'Fileset source-map identity differs.'
    );
    mediaCarouselSame(7, count($sourceMap['files'] ?? []), 'Fileset source count differs.');
    foreach ($sourceMap['files'] ?? [] as $entry) {
        if (!is_array($entry)) {
            throw new RuntimeException('Fileset source-map entry is invalid.');
        }
        $source = $entry['source'] ?? null;
        $target = $entry['target'] ?? null;
        $expectedHash = $entry['sha256'] ?? null;
        if (!is_string($source) || !is_string($target) || !is_string($expectedHash)) {
            throw new RuntimeException('Fileset source-map fields are invalid.');
        }
        mediaCarouselSame(
            hash_file('sha256', $projectRoot . '/' . $source),
            $expectedHash,
            'Canonical source hash differs.'
        );
        mediaCarouselSame(
            $expectedHash,
            hash_file('sha256', $firstRoot . '/' . $target),
            'Generated target is not byte-exact.'
        );
        $contents = (string) file_get_contents($firstRoot . '/' . $target);
        foreach (['/Users/', '\\Users\\', '192.168.'] as $privateMarker) {
            if (str_contains($contents, $privateMarker)) {
                throw new RuntimeException('Generated payload contains private installation data.');
            }
        }
    }

    $publicationInventory = $first;
    $publicationInventory['LICENSE'] = mediaCarouselFileHash($projectRoot . '/LICENSE');
    $publicationInventory['README.md'] = mediaCarouselFileHash(
        $projectRoot . '/case-studies/media-carousel/publication/README.md'
    );
    ksort($publicationInventory, SORT_STRING);
    mediaCarouselSame(11, count($publicationInventory), 'Publication candidate inventory differs.');
    foreach (['LICENSE', 'README.md', 'library.json', 'fileset.sources.json', 'fileset.sha256'] as $required) {
        if (!isset($publicationInventory[$required])) {
            throw new RuntimeException('Publication candidate is missing ' . $required . '.');
        }
    }

    runMediaCarouselCommand([PHP_BINARY, $validator]);
    runMediaCarouselCommand([PHP_BINARY, $builder, '--check', $manifest]);

    $additionalTarget = $firstRoot . '/stale.txt';
    if (file_put_contents($additionalTarget, "stale\n") === false) {
        throw new RuntimeException('Cannot create stale fileset regression target.');
    }
    $additionalCheck = executeMediaCarouselCommand([
        PHP_BINARY,
        $builder,
        '--check',
        '--output-root=' . $temporaryRoots[0],
        $manifest,
    ]);
    mediaCarouselSame(1, $additionalCheck['status'], 'Fileset check accepted an additional target.');
    if (!str_contains($additionalCheck['stderr'], 'additional targets')) {
        throw new RuntimeException('Additional fileset target failure is not classified.');
    }

    fwrite(STDOUT, "media-carousel-fileset: ok\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'media-carousel-fileset: failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeMediaCarouselTree($temporaryRoot);
    }
}

/** @param list<string> $command */
function runMediaCarouselCommand(array $command): void
{
    $result = executeMediaCarouselCommand($command);
    if ($result['status'] !== 0) {
        throw new RuntimeException(
            'MediaCarousel subprocess failed: ' . trim($result['stderr'] . $result['stdout'])
        );
    }
}

/**
 * @param list<string> $command
 *
 * @return array{status: int, stdout: string, stderr: string}
 */
function executeMediaCarouselCommand(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start MediaCarousel test subprocess.');
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

function mediaCarouselTemporaryRoot(string $prefix): string
{
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Cannot create MediaCarousel temporary root.');
    }
    return $path;
}

/** @return array<string, string> */
function mediaCarouselFileHashes(string $root): array
{
    if (!is_dir($root)) {
        throw new RuntimeException('MediaCarousel fileset root is missing.');
    }
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
        $hashes[$relative] = mediaCarouselFileHash($entry->getPathname());
    }
    ksort($hashes, SORT_STRING);
    return $hashes;
}

function mediaCarouselFileHash(string $path): string
{
    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        throw new RuntimeException('Cannot hash MediaCarousel fileset target.');
    }
    return $hash;
}

function mediaCarouselSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function removeMediaCarouselTree(string $root): void
{
    $temporaryPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'media-carousel-fileset-';
    if (!str_starts_with($root, $temporaryPrefix) || !is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
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
    rmdir($root);
}
