<?php

declare(strict_types=1);

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 3));
$publisher = $projectRoot . '/tools/publish-open-meteo-module.php';
$temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'open-meteo-publication-test-' . bin2hex(random_bytes(8));

try {
    $check = runOpenMeteoPublicationTestCommand([
        PHP_BINARY,
        $publisher,
        '--check',
    ]);
    $checkResult = json_decode($check['stdout'], true, 512, JSON_THROW_ON_ERROR);
    openMeteoPublicationTestSame(0, $check['status'], 'Publication check failed.');
    openMeteoPublicationTestSame('checked', $checkResult['outcome'] ?? null, 'Check outcome differs.');
    openMeteoPublicationTestSame(false, $checkResult['mutationAttempted'] ?? null, 'Check attempted mutation.');
    openMeteoPublicationTestSame(33, $checkResult['fileCount'] ?? null, 'Publication file count differs.');
    openMeteoPublicationTestHash($checkResult['filesetSha256'] ?? null, 'Fileset hash is invalid.');
    openMeteoPublicationTestHash($checkResult['publicationSha256'] ?? null, 'Publication hash is invalid.');

    $preparedRoot = $temporaryRoot . '/prepared';
    $prepare = runOpenMeteoPublicationTestCommand([
        PHP_BINARY,
        $publisher,
        '--prepare=' . $preparedRoot,
    ]);
    $prepareResult = json_decode($prepare['stdout'], true, 512, JSON_THROW_ON_ERROR);
    openMeteoPublicationTestSame(0, $prepare['status'], 'Publication prepare failed.');
    openMeteoPublicationTestSame('prepared', $prepareResult['outcome'] ?? null, 'Prepare outcome differs.');
    openMeteoPublicationTestSame(false, $prepareResult['mutationAttempted'] ?? null, 'Prepare attempted mutation.');
    openMeteoPublicationTestSame($preparedRoot, $prepareResult['target'] ?? null, 'Prepare target differs.');
    openMeteoPublicationTestSame(
        $checkResult['publicationSha256'] ?? null,
        $prepareResult['publicationSha256'] ?? null,
        'Prepared publication identity differs.'
    );

    $preparedFiles = openMeteoPublicationTestHashes($preparedRoot);
    openMeteoPublicationTestSame(33, count($preparedFiles), 'Prepared file inventory differs.');
    foreach (['LICENSE', 'README.md', 'library.json', 'fileset.sources.json', 'fileset.sha256'] as $required) {
        if (!isset($preparedFiles[$required])) {
            throw new RuntimeException('Prepared publication is missing ' . $required . '.');
        }
    }
    openMeteoPublicationTestSame(
        hash_file('sha256', $projectRoot . '/LICENSE'),
        $preparedFiles['LICENSE'],
        'Prepared license is not canonical.'
    );
    openMeteoPublicationTestSame(
        hash_file('sha256', $projectRoot . '/case-studies/open-meteo/publication/README.md'),
        $preparedFiles['README.md'],
        'Prepared README is not canonical.'
    );

    $sourceMap = json_decode(
        (string) file_get_contents($preparedRoot . '/fileset.sources.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    openMeteoPublicationTestSame(29, count($sourceMap['files'] ?? []), 'Prepared payload count differs.');
    foreach ($sourceMap['files'] ?? [] as $entry) {
        if (!is_array($entry) || !is_string($entry['target'] ?? null) || !is_string($entry['sha256'] ?? null)) {
            throw new RuntimeException('Prepared source-map entry is invalid.');
        }
        openMeteoPublicationTestSame(
            $entry['sha256'],
            $preparedFiles[$entry['target']] ?? null,
            'Prepared payload differs from its source map.'
        );
    }

    require_once $publisher;
    $baselineRoot = $temporaryRoot . '/baseline-subset';
    if (!mkdir($baselineRoot, 0700, true)) {
        throw new RuntimeException('Cannot create publication baseline regression tree.');
    }
    if (file_put_contents($baselineRoot . '/LICENSE', (string) file_get_contents($preparedRoot . '/LICENSE')) === false) {
        throw new RuntimeException('Cannot create allowlisted publication baseline file.');
    }
    assertOpenMeteoPublicationBaselinePathsAllowed($baselineRoot, [
        'LICENSE' => (string) file_get_contents($preparedRoot . '/LICENSE'),
        'README.md' => (string) file_get_contents($preparedRoot . '/README.md'),
    ]);
    if (file_put_contents($baselineRoot . '/unexpected.txt', 'unexpected') === false) {
        throw new RuntimeException('Cannot create unknown publication baseline file.');
    }
    $unknownBaselinePathRejected = false;
    try {
        assertOpenMeteoPublicationBaselinePathsAllowed($baselineRoot, [
            'LICENSE' => (string) file_get_contents($preparedRoot . '/LICENSE'),
            'README.md' => (string) file_get_contents($preparedRoot . '/README.md'),
        ]);
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'outside the allowlist')) {
            throw $exception;
        }
        $unknownBaselinePathRejected = true;
    }
    if (!$unknownBaselinePathRejected) {
        throw new RuntimeException('Publication baseline accepted an unknown path.');
    }

    $secondPrepare = runOpenMeteoPublicationTestCommand([
        PHP_BINARY,
        $publisher,
        '--prepare=' . $preparedRoot,
    ]);
    openMeteoPublicationTestSame(1, $secondPrepare['status'], 'Existing prepare target was accepted.');
    if (!str_contains($secondPrepare['stderr'], 'must not already exist')) {
        throw new RuntimeException('Existing prepare-target failure is not classified.');
    }

    $ungatedApply = runOpenMeteoPublicationTestCommand([
        PHP_BINARY,
        $publisher,
        '--apply',
    ]);
    openMeteoPublicationTestSame(1, $ungatedApply['status'], 'Ungated publication apply was accepted.');
    if (!str_contains($ungatedApply['stderr'], 'expected fileset hash')) {
        throw new RuntimeException('Ungated apply failure is not classified before network access.');
    }

    $toolSource = (string) file_get_contents($publisher);
    foreach (['MC_UpdateModule', 'IPS_CreateInstance', 'symcon_'] as $forbidden) {
        if (str_contains($toolSource, $forbidden)) {
            throw new RuntimeException('Publisher crosses the repository/live-operation boundary.');
        }
    }

    $symlinkTree = $temporaryRoot . '/symlink-tree';
    $symlinkTarget = $symlinkTree . '/target-directory';
    if (!mkdir($symlinkTarget, 0700, true)) {
        throw new RuntimeException('Cannot create publication symlink regression tree.');
    }
    if (!symlink($symlinkTarget, $symlinkTree . '/unknown-link')) {
        throw new RuntimeException('Cannot create publication symlink regression link.');
    }
    $symlinkRejected = false;
    try {
        openMeteoPublicationTreeHashes($symlinkTree, false);
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'symbolic link')) {
            throw $exception;
        }
        $symlinkRejected = true;
    }
    if (!$symlinkRejected) {
        throw new RuntimeException('Publication tree accepted a directory symbolic link.');
    }

    fwrite(STDOUT, "publication: ok\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'publication: failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    removeOpenMeteoPublicationTestTree($temporaryRoot);
}

/**
 * @param list<string> $command
 *
 * @return array{status: int, stdout: string, stderr: string}
 */
function runOpenMeteoPublicationTestCommand(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start publication test subprocess.');
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

/** @return array<string, string> */
function openMeteoPublicationTestHashes(string $root): array
{
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo || !$item->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        $hash = hash_file('sha256', $item->getPathname());
        if ($hash === false) {
            throw new RuntimeException('Cannot hash prepared publication file.');
        }
        $hashes[$relative] = $hash;
    }
    ksort($hashes, SORT_STRING);

    return $hashes;
}

function openMeteoPublicationTestHash(mixed $value, string $message): void
{
    if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
        throw new RuntimeException($message);
    }
}

function openMeteoPublicationTestSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function removeOpenMeteoPublicationTestTree(string $root): void
{
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'open-meteo-publication-test-';
    if (!str_starts_with($root, $prefix) || !is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($root);
}
