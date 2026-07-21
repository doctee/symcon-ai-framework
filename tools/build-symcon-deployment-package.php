<?php

declare(strict_types=1);

const SAEF_DEPLOYMENT_PACKAGE_FORMAT = 1;
const SAEF_DEPLOYMENT_PACKAGE_MTIME = 315532800;

try {
    if ($argc !== 2) {
        throw new InvalidArgumentException(
            'Usage: php tools/build-symcon-deployment-package.php <plan.local.json>'
        );
    }

    $planPath = realpath($argv[1]);
    if ($planPath === false || !is_file($planPath)) {
        throw new RuntimeException('Deployment package plan is missing.');
    }
    $plan = json_decode((string) file_get_contents($planPath), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($plan) || ($plan['formatVersion'] ?? null) !== SAEF_DEPLOYMENT_PACKAGE_FORMAT) {
        throw new RuntimeException('Unsupported deployment package plan format.');
    }

    foreach (
        [
            'deploymentId',
            'targetDirectoryName',
            'filesetPath',
            'bootstrapSnapshotPath',
            'oldToken',
            'newToken',
            'outputPath',
        ] as $key
    ) {
        if (!isset($plan[$key]) || !is_string($plan[$key]) || $plan[$key] === '') {
            throw new RuntimeException("Deployment package plan field is missing: {$key}");
        }
    }
    assertSafeDeploymentIdentifier($plan['deploymentId']);
    assertSafeDeploymentIdentifier($plan['targetDirectoryName']);
    if (!str_contains($plan['newToken'], $plan['targetDirectoryName'])) {
        throw new RuntimeException('New bootstrap token must identify the target directory.');
    }

    $filesetPath = realpath(resolvePlanPath($planPath, $plan['filesetPath']));
    $bootstrapPath = realpath(resolvePlanPath($planPath, $plan['bootstrapSnapshotPath']));
    $outputPath = resolvePlanPath($planPath, $plan['outputPath']);
    if ($filesetPath === false || !is_dir($filesetPath)) {
        throw new RuntimeException('Fileset directory is missing.');
    }
    if ($bootstrapPath === false || !is_file($bootstrapPath)) {
        throw new RuntimeException('Bootstrap snapshot is missing.');
    }
    if (file_exists($outputPath)) {
        throw new RuntimeException('Deployment package output already exists.');
    }

    $bootstrap = (string) file_get_contents($bootstrapPath);
    assertPrintableAsciiToken($plan['oldToken']);
    assertPrintableAsciiToken($plan['newToken']);
    if (strlen($plan['oldToken']) !== strlen($plan['newToken'])) {
        throw new RuntimeException('Bootstrap tokens must have equal byte length.');
    }
    if (substr_count($bootstrap, $plan['oldToken']) !== 1 || str_contains($bootstrap, $plan['newToken'])) {
        throw new RuntimeException('Bootstrap snapshot does not have the required exact token state.');
    }
    $replacementCount = 0;
    $candidateBootstrap = str_replace($plan['oldToken'], $plan['newToken'], $bootstrap, $replacementCount);
    if ($replacementCount !== 1) {
        throw new RuntimeException('Bootstrap candidate replacement count is invalid.');
    }

    $files = collectFilesetFiles($filesetPath);
    if ($files === []) {
        throw new RuntimeException('Fileset is empty.');
    }
    $manifest = [
        'formatVersion' => SAEF_DEPLOYMENT_PACKAGE_FORMAT,
        'deploymentId' => $plan['deploymentId'],
        'targetDirectoryName' => $plan['targetDirectoryName'],
        'bootstrap' => [
            'expectedActiveSha256' => hash('sha256', $bootstrap),
            'expectedCandidateSha256' => hash('sha256', $candidateBootstrap),
            'oldToken' => $plan['oldToken'],
            'newToken' => $plan['newToken'],
        ],
        'files' => array_map(
            static fn (array $file): array => [
                'path' => 'fileset/' . $file['relativePath'],
                'sha256' => $file['sha256'],
                'size' => $file['size'],
            ],
            $files
        ),
    ];
    $manifestJson = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";

    $outputDirectory = dirname($outputPath);
    if (!is_dir($outputDirectory)) {
        throw new RuntimeException('Deployment package output directory is missing.');
    }
    $temporaryPath = $outputPath . '.tmp-' . bin2hex(random_bytes(8));
    $archive = new ZipArchive();
    if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('Cannot create deployment package archive.');
    }
    try {
        addStoredArchiveString($archive, 'deployment.json', $manifestJson);
        foreach ($files as $file) {
            $archiveName = 'fileset/' . $file['relativePath'];
            if (!$archive->addFile($file['absolutePath'], $archiveName)) {
                throw new RuntimeException("Cannot add fileset file: {$file['relativePath']}");
            }
            setStoredArchiveMetadata($archive, $archiveName);
        }
    } finally {
        $archive->close();
    }
    if (!rename($temporaryPath, $outputPath)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Cannot finalize deployment package archive.');
    }

    fwrite(
        STDOUT,
        json_encode(
            [
                'formatVersion' => SAEF_DEPLOYMENT_PACKAGE_FORMAT,
                'deploymentId' => $plan['deploymentId'],
                'packageSha256' => hash_file('sha256', $outputPath),
                'fileCount' => count($files),
                'packageBytes' => filesize($outputPath),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Deployment package build failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}

function resolvePlanPath(string $planPath, string $path): string
{
    if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
        return $path;
    }

    return dirname($planPath) . DIRECTORY_SEPARATOR . $path;
}

function assertSafeDeploymentIdentifier(string $value): void
{
    if (preg_match('/^saef-[a-z0-9][a-z0-9.-]{0,63}$/', $value) !== 1) {
        throw new RuntimeException('Deployment identifier is invalid.');
    }
}

function assertPrintableAsciiToken(string $value): void
{
    if ($value === '' || preg_match('/^[\x20-\x7E]+$/D', $value) !== 1) {
        throw new RuntimeException('Bootstrap token must be printable ASCII.');
    }
}

/**
 * @return list<array{absolutePath: string, relativePath: string, sha256: string, size: int}>
 */
function collectFilesetFiles(string $root): array
{
    $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    $files = [];
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if ($file->isLink()) {
            throw new RuntimeException('Fileset symlinks are not allowed.');
        }
        $absolutePath = $file->getPathname();
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root)));
        if (!isSafeArchivePath($relativePath)) {
            throw new RuntimeException("Fileset path is unsafe: {$relativePath}");
        }
        $size = $file->getSize();
        $hash = hash_file('sha256', $absolutePath);
        if ($size === false || $hash === false) {
            throw new RuntimeException("Cannot inspect fileset file: {$relativePath}");
        }
        $files[] = [
            'absolutePath' => $absolutePath,
            'relativePath' => $relativePath,
            'sha256' => $hash,
            'size' => $size,
        ];
    }
    usort($files, static fn (array $left, array $right): int => $left['relativePath'] <=> $right['relativePath']);

    return $files;
}

function isSafeArchivePath(string $path): bool
{
    if ($path === '' || str_contains($path, '\\') || str_contains($path, ':') || str_starts_with($path, '/')) {
        return false;
    }
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

function addStoredArchiveString(ZipArchive $archive, string $name, string $contents): void
{
    if (!$archive->addFromString($name, $contents)) {
        throw new RuntimeException("Cannot add archive content: {$name}");
    }
    setStoredArchiveMetadata($archive, $name);
}

function setStoredArchiveMetadata(ZipArchive $archive, string $name): void
{
    if (!$archive->setCompressionName($name, ZipArchive::CM_STORE)) {
        throw new RuntimeException("Cannot set archive compression: {$name}");
    }
    if (!$archive->setMtimeName($name, SAEF_DEPLOYMENT_PACKAGE_MTIME)) {
        throw new RuntimeException("Cannot set archive timestamp: {$name}");
    }
}
