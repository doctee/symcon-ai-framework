<?php

declare(strict_types=1);

/**
 * Internal storage contract shared only by the two local package builders.
 * This is not a live path resolver, deployment wrapper or cleanup API.
 *
 * @param array<string, mixed> $plan
 * @return array{root: string, workstream: string, owner: string, purpose: string}|null
 */
function packageArtifactStorage(array $plan): ?array
{
    if (($plan['formatVersion'] ?? null) === 1) {
        if (array_key_exists('storage', $plan)) {
            throw new RuntimeException('Storage requires package plan formatVersion 2.');
        }
        return null;
    }
    if (($plan['formatVersion'] ?? null) !== 2 || array_key_exists('outputPath', $plan)) {
        throw new RuntimeException('Package plan version or outputPath conflicts with managed storage.');
    }
    if (PHP_OS_FAMILY === 'Windows' || !function_exists('posix_geteuid')) {
        throw new RuntimeException('Managed package storage currently requires a local POSIX producer.');
    }
    $storage = $plan['storage'] ?? null;
    if (!is_array($storage)) {
        throw new RuntimeException('Package storage configuration is missing.');
    }
    $keys = array_keys($storage);
    sort($keys, SORT_STRING);
    if ($keys !== ['owner', 'purpose', 'root', 'workstream']) {
        throw new RuntimeException('Package storage fields differ.');
    }
    foreach ($storage as $value) {
        if (!is_string($value) || trim($value) === '' || strlen($value) > 4096) {
            throw new RuntimeException('Package storage field is empty or invalid.');
        }
    }
    if (
        preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $storage['workstream']) !== 1
        || strlen($storage['workstream']) > 128
    ) {
        throw new RuntimeException('Package storage workstream is invalid.');
    }
    assertPackageArtifactDirectory($storage['root']);
    return $storage;
}

function assertPackageArtifactDirectory(string $path): void
{
    clearstatcache();
    if (!str_starts_with($path, '/') || realpath($path) !== $path || !is_dir($path)) {
        throw new RuntimeException('Package storage directory must be existing, absolute and canonical.');
    }
    for ($part = $path; $part !== '/'; $part = dirname($part)) {
        if (is_link($part)) {
            throw new RuntimeException('Package storage directory has a symbolic-link component.');
        }
    }
    $mode = fileperms($path);
    if ($mode === false || ($mode & 0077) !== 0 || fileowner($path) !== posix_geteuid()) {
        throw new RuntimeException('Package storage directory must be private and owned by the current user.');
    }
}

/**
 * @param array{root: string, workstream: string, owner: string, purpose: string} $storage
 * @return array{directory: string, package: string, record: array<string, mixed>}
 */
function beginPackageArtifactRun(
    array $storage,
    string $runId,
    string $sourceRoot,
    string $planPath,
    string $producerPath,
    string $planBytes
): array {
    if (preg_match('/^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$/D', $runId) !== 1) {
        throw new RuntimeException('Package artifact run ID is invalid.');
    }
    assertPackageArtifactDirectory($storage['root']);
    if ($storage['root'] === $sourceRoot || str_starts_with($storage['root'], $sourceRoot . '/')) {
        throw new RuntimeException('Package storage must be outside the input fileset.');
    }
    $producerHash = hash_file('sha256', $producerPath);
    $supportHash = hash_file('sha256', __FILE__);
    $planHash = hash('sha256', $planBytes);
    if (
        $producerHash === false || $supportHash === false || strlen($planBytes) > 65536
        || hash_file('sha256', $planPath) !== $planHash
    ) {
        throw new RuntimeException('Cannot bind package producer or bounded unchanged plan.');
    }
    $parent = $storage['root'] . '/' . $storage['workstream'];
    if (!file_exists($parent) && !is_link($parent) && !mkdir($parent, 0700)) {
        throw new RuntimeException('Cannot create package workstream directory.');
    }
    assertPackageArtifactDirectory($parent);
    $directory = $parent . '/' . $runId;
    if (file_exists($directory) || is_link($directory) || !mkdir($directory, 0700)) {
        throw new RuntimeException('Package run already exists or cannot be created.');
    }
    assertPackageArtifactDirectory($directory);
    $package = $directory . '/package.local.zip';
    $record = [
        'formatVersion' => 1,
        'artifactId' => $storage['workstream'] . '-' . $runId,
        'workstream' => $storage['workstream'],
        'owner' => $storage['owner'],
        'purpose' => $storage['purpose'],
        'producer' => [
            'name' => basename($producerPath),
            'revision' => 'sha256:' . hash('sha256', $producerHash . ':' . $supportHash),
        ],
        'role' => 'package',
        'location' => ['path' => $package, 'basis' => 'absolute'],
        'state' => 'review-required',
        'identity' => ['manifestRef' => null, 'sha256' => null],
        'evidence' => [
            'basis' => 'unverified',
            'checkedAtUtc' => gmdate('Y-m-d\TH:i:s\Z'),
            'references' => [
                $planPath . '#sha256=' . $planHash,
                $directory . '/input-plan.local.json#sha256=' . $planHash,
                $producerPath . '#sha256=' . $producerHash,
                __FILE__ . '#sha256=' . $supportHash,
            ],
        ],
        'retention' => [
            'decision' => 'retain',
            'minimumUntilUtc' => null,
            'reviewAfterUtc' => null,
            'holds' => ['Build incomplete; retain all output until reviewed.'],
            'dependencies' => ['Subsequent deployment and recovery references are unknown.'],
        ],
        'copyOf' => null,
    ];
    try {
        writePackageArtifactFile($directory . '/started.local.json', packageArtifactJson($record));
        writePackageArtifactFile($directory . '/input-plan.local.json', $planBytes);
    } catch (Throwable $failure) {
        throw new RuntimeException('Retained incomplete package run: ' . $directory, 0, $failure);
    }
    return ['directory' => $directory, 'package' => $package, 'record' => $record];
}

/** @param array<string, mixed> $value */
function packageArtifactJson(array $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

function writePackageArtifactFile(string $path, string $bytes): void
{
    if (file_exists($path) || is_link($path)) {
        throw new RuntimeException('Package evidence path already exists.');
    }
    $stream = fopen($path, 'x');
    if ($stream === false) {
        throw new RuntimeException('Package evidence path already exists or cannot be created.');
    }
    try {
        if (!chmod($path, 0600) || fwrite($stream, $bytes) !== strlen($bytes) || !fflush($stream)) {
            throw new RuntimeException('Cannot write private package evidence.');
        }
    } finally {
        fclose($stream);
    }
}

/**
 * @param array{directory: string, package: string, record: array<string, mixed>} $run
 * @return array{artifactRecordPath: string, runDirectory: string}
 */
function completePackageArtifactRun(array $run, string $manifestJson): array
{
    assertPackageArtifactDirectory($run['directory']);
    $hash = hash_file('sha256', $run['package']);
    if ($hash === false || !chmod($run['package'], 0600)) {
        throw new RuntimeException('Cannot verify private package output.');
    }
    $record = $run['record'];
    $record['state'] = 'completed';
    $record['identity'] = [
        'manifestRef' => 'package.local.zip!deployment.json#sha256=' . hash('sha256', $manifestJson),
        'sha256' => $hash,
    ];
    $record['evidence']['basis'] = 'local-observation';
    $record['evidence']['checkedAtUtc'] = gmdate('Y-m-d\TH:i:s\Z');
    $record['retention']['holds'] = ['No deployment or cleanup authority; keep until explicit lifecycle review.'];
    writePackageArtifactFile(
        $run['directory'] . '/summary.local.txt',
        "Package build completed; not installed or published.\nSHA-256: {$hash}\n"
        . "Source and lifecycle references: artifact-record.local.json\nCleanup: none\n"
    );
    $recordPath = $run['directory'] . '/artifact-record.local.json';
    writePackageArtifactFile($recordPath, packageArtifactJson($record));
    return ['artifactRecordPath' => $recordPath, 'runDirectory' => $run['directory']];
}

/** @param array{directory: string, package: string, record: array<string, mixed>}|null $run */
function failPackageArtifactRun(?array $run, Throwable $failure): void
{
    if ($run === null) {
        return;
    }
    try {
        writePackageArtifactFile($run['directory'] . '/failure.local.json', packageArtifactJson([
            'outcome' => 'failed',
            'failureType' => get_class($failure),
            'cleanupPerformed' => false,
            'retained' => true,
        ]));
    } catch (Throwable) {
        fwrite(STDERR, "Failure receipt unavailable; retain the incomplete run.\n");
    }
    fwrite(STDERR, 'Retained package run: ' . $run['directory'] . "\n");
}
