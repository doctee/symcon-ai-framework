<?php

declare(strict_types=1);

require_once __DIR__ . '/PackageArtifacts.php';

function createPrivateFilesetDirectory(string $path): void
{
    if (file_exists($path) || is_link($path) || !mkdir($path, 0700)) {
        throw new RuntimeException('Private fileset directory already exists or cannot be created: ' . $path);
    }
    assertPackageArtifactDirectory($path);
    if ((fileperms($path) & 0777) !== 0700) {
        throw new RuntimeException('Private fileset directory mode is not 0700; retain: ' . $path);
    }
}

/**
 * Private output placement only; the module builder still owns every payload byte.
 * Trusted local ancestors and independently reviewed ACLs are operator prerequisites.
 *
 * @param array<string, string> $outputs
 * @return array{runDirectory: string, outputRoot: string, artifactRecordPath: string}
 */
function writePrivateModuleFileset(
    array $outputs,
    string $planPath,
    string $manifestPath,
    string $manifestHash,
    string $producerPath
): array {
    if (realpath($planPath) !== $planPath || !is_file($planPath) || is_link($planPath)) {
        throw new RuntimeException('Private output plan must be an absolute canonical file.');
    }
    $planBytes = file_get_contents($planPath);
    if ($planBytes === false || strlen($planBytes) > 65536) {
        throw new RuntimeException('Private output plan is unreadable or too large.');
    }
    $plan = json_decode($planBytes, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($plan) || count($plan) !== 2 || !isset($plan['formatVersion'], $plan['storage'])) {
        throw new RuntimeException('Private output plan fields differ.');
    }
    if ($plan['formatVersion'] !== 2) {
        throw new RuntimeException('Private output plan requires formatVersion 2.');
    }
    $storage = packageArtifactStorage($plan);
    if ($storage === null) {
        throw new RuntimeException('Private storage is missing.');
    }
    $manifest = realpath($manifestPath);
    if ($manifest === false || hash_file('sha256', $manifest) !== $manifestHash) {
        throw new RuntimeException('Fileset manifest changed during build.');
    }
    $producerHash = hash_file('sha256', $producerPath);
    $supportHash = hash_file('sha256', __FILE__);
    $sharedHash = hash_file('sha256', __DIR__ . '/PackageArtifacts.php');
    if ($producerHash === false || $supportHash === false || $sharedHash === false) {
        throw new RuntimeException('Cannot bind fileset producer.');
    }
    // Preflight the entire output namespace, including directory/file collisions.
    $names = [];
    $directories = [];
    foreach ($outputs as $relative => $bytes) {
        if (!str_starts_with($relative, 'dist/symcon/') || str_contains($relative, '\\')) {
            throw new RuntimeException('Unsafe private fileset path.');
        }
        foreach (explode('/', $relative) as $segment) {
            if (
                preg_match('/^[A-Za-z0-9._ -]+$/D', $segment) !== 1
                || $segment === '.' || $segment === '..' || rtrim($segment, '. ') !== $segment
            ) {
                throw new RuntimeException('Unsafe private fileset path.');
            }
        }
        $key = strtolower($relative);
        if (isset($names[$key])) {
            throw new RuntimeException('Case-colliding private fileset paths.');
        }
        $names[$key] = true;
        for ($dir = dirname($relative); $dir !== '.'; $dir = dirname($dir)) {
            $key = strtolower($dir);
            if (isset($directories[$key]) && $directories[$key] !== $dir) {
                throw new RuntimeException('Case-colliding private fileset directories.');
            }
            $directories[$key] = $dir;
        }
    }
    if ($outputs === [] || array_intersect_key($names, $directories) !== []) {
        throw new RuntimeException('Empty or conflicting private fileset namespace.');
    }
    $sidecars = array_values(array_filter(array_keys($outputs), static fn (string $path): bool =>
        str_ends_with($path, '/fileset.sources.json')));
    if (count($sidecars) !== 1) {
        throw new RuntimeException('Ambiguous private fileset identity.');
    }
    $sourceMap = json_decode($outputs[$sidecars[0]], true, 64, JSON_THROW_ON_ERROR);
    $projectRoot = dirname($producerPath, 2);
    $inputDirectories = [$projectRoot . '/' . dirname($sidecars[0]), dirname($manifest)];
    foreach ($sourceMap['files'] as $source) {
        $inputDirectories[] = $projectRoot . '/' . dirname($source['source']);
    }
    $parent = $storage['root'] . '/' . $storage['workstream'];
    $runId = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6));
    $run = $parent . '/' . $runId;
    // Appending the workstream/run can enter an input even when the root is outside it.
    foreach ([$storage['root'], $parent, $run] as $outputDirectory) {
        foreach ($inputDirectories as $inputDirectory) {
            if ($outputDirectory === $inputDirectory || str_starts_with($outputDirectory, $inputDirectory . '/')) {
                throw new RuntimeException('Private storage must be outside source and generated input directories.');
            }
        }
    }
    if (!file_exists($parent) && !is_link($parent)) {
        createPrivateFilesetDirectory($parent);
    }
    assertPackageArtifactDirectory($parent);
    createPrivateFilesetDirectory($run);
    $record = [
        'formatVersion' => 1, 'artifactId' => $storage['workstream'] . '-' . $runId,
        'workstream' => $storage['workstream'], 'owner' => $storage['owner'], 'purpose' => $storage['purpose'],
        'producer' => ['name' => basename($producerPath),
            'revision' => 'sha256:' . hash('sha256', $producerHash . ':' . $supportHash . ':' . $sharedHash)],
        'role' => 'package', 'location' => ['path' => $run . '/fileset', 'basis' => 'absolute'],
        'state' => 'review-required', 'identity' => ['manifestRef' => null, 'sha256' => null],
        'evidence' => ['basis' => 'unverified', 'checkedAtUtc' => gmdate('Y-m-d\TH:i:s\Z'),
            'references' => [$manifest . '#sha256=' . $manifestHash,
                $planPath . '#sha256=' . hash('sha256', $planBytes),
                $producerPath . '#sha256=' . $producerHash,
                __FILE__ . '#sha256=' . $supportHash,
                __DIR__ . '/PackageArtifacts.php#sha256=' . $sharedHash]],
        'retention' => ['decision' => 'retain', 'minimumUntilUtc' => null, 'reviewAfterUtc' => null,
            'holds' => ['Keep incomplete runs; no publication, deployment or cleanup authority.'],
            'dependencies' => ['Future deployment and recovery references are unknown.']],
        'copyOf' => null,
    ];
    try {
        assertPackageArtifactDirectory($run);
        writePackageArtifactFile($run . '/started.local.json', packageArtifactJson($record));
        writePackageArtifactFile($run . '/input-plan.local.json', $planBytes);
        $manifestBytes = file_get_contents($manifest);
        if ($manifestBytes === false || hash('sha256', $manifestBytes) !== $manifestHash) {
            throw new RuntimeException('Fileset manifest changed before output.');
        }
        writePackageArtifactFile($run . '/input-manifest.local.json', $manifestBytes);
        $outputRoot = $run . '/fileset';
        createPrivateFilesetDirectory($outputRoot);
        $directories = array_values($directories);
        sort($directories, SORT_STRING);
        foreach ($directories as $relative) {
            createPrivateFilesetDirectory($outputRoot . '/' . $relative);
        }
        foreach ($outputs as $relative => $bytes) {
            writePackageArtifactFile($outputRoot . '/' . $relative, $bytes);
        }
        // Reopen every output before recording completion; do not infer success from writes.
        foreach ($outputs as $relative => $bytes) {
            if (file_get_contents($outputRoot . '/' . $relative) !== $bytes) {
                throw new RuntimeException('Private fileset readback differs.');
            }
        }
        $record['state'] = 'completed';
        $record['identity']['manifestRef'] = $outputRoot . '/' . $sidecars[0]
            . '#sha256=' . hash('sha256', $outputs[$sidecars[0]]);
        $record['evidence']['basis'] = 'local-observation';
        $record['evidence']['checkedAtUtc'] = gmdate('Y-m-d\TH:i:s\Z');
        $record['retention']['holds'] = ['Retain until explicit lifecycle review; not installed or published.'];
        writePackageArtifactFile(
            $run . '/summary.local.txt',
            "Private fileset build and byte readback completed.\nNo publication, deployment or cleanup.\n"
        );
        $recordPath = $run . '/artifact-record.local.json';
        writePackageArtifactFile($recordPath, packageArtifactJson($record));
        return ['runDirectory' => $run, 'outputRoot' => $outputRoot, 'artifactRecordPath' => $recordPath];
    } catch (Throwable $error) {
        throw new RuntimeException('Retained incomplete private fileset run: ' . $run, 0, $error);
    }
}
