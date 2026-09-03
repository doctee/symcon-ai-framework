<?php

declare(strict_types=1);

const SAEF_MODULE_DEPLOYMENT_PACKAGE_FORMAT = 1;
const SAEF_MODULE_DEPLOYMENT_PACKAGE_MTIME = 315532800;

try {
    if ($argc !== 2) {
        throw new InvalidArgumentException(
            'Usage: php tools/build-symcon-module-deployment-package.php <plan.local.json>'
        );
    }

    $planPath = realpath($argv[1]);
    if ($planPath === false || !is_file($planPath)) {
        throw new RuntimeException('Module deployment package plan is missing.');
    }
    $plan = json_decode((string) file_get_contents($planPath), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($plan) || ($plan['formatVersion'] ?? null) !== SAEF_MODULE_DEPLOYMENT_PACKAGE_FORMAT) {
        throw new RuntimeException('Unsupported module deployment package plan format.');
    }
    $requiredFields = [
        'deploymentId',
        'targetDirectoryName',
        'modulePath',
        'moduleTargetId',
        'libraryGuid',
        'transactionContractPath',
        'outputPath',
    ];
    foreach ($requiredFields as $field) {
        if (!isset($plan[$field]) || !is_string($plan[$field]) || $plan[$field] === '') {
            throw new RuntimeException("Module deployment plan field is missing: {$field}");
        }
    }

    assertSafeModuleDeploymentIdentifier($plan['deploymentId']);
    assertSafeModuleDeploymentIdentifier($plan['targetDirectoryName']);
    assertSafeModuleDeploymentIdentifier($plan['moduleTargetId']);
    assertSymconGuid($plan['libraryGuid']);

    $modulePath = realpath(resolveModuleDeploymentPlanPath($planPath, $plan['modulePath']));
    $contractPath = realpath(resolveModuleDeploymentPlanPath($planPath, $plan['transactionContractPath']));
    $outputPath = resolveModuleDeploymentPlanPath($planPath, $plan['outputPath']);
    if ($modulePath === false || !is_dir($modulePath)) {
        throw new RuntimeException('Module directory is missing.');
    }
    if ($contractPath === false || !is_file($contractPath) || is_link($contractPath)) {
        throw new RuntimeException('Module transaction contract is missing or unsafe.');
    }
    if (file_exists($outputPath)) {
        throw new RuntimeException('Module deployment package output already exists.');
    }

    $transactionContract = (string) file_get_contents($contractPath);
    if ($transactionContract === '' || strlen($transactionContract) > 1048576) {
        throw new RuntimeException('Module transaction contract is empty or exceeds its byte limit.');
    }
    $transaction = json_decode($transactionContract, true, flags: JSON_THROW_ON_ERROR);
    if (
        !is_array($transaction)
        || ($transaction['formatVersion'] ?? null) !== 1
        || !isset($transaction['adapterProfile'])
        || !is_string($transaction['adapterProfile'])
        || preg_match('/^saef-[a-z0-9][a-z0-9.-]{0,63}$/D', $transaction['adapterProfile']) !== 1
    ) {
        throw new RuntimeException('Module transaction contract identity is invalid.');
    }
    $transactionContract = json_encode(
        $transaction,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";

    $files = collectModuleDeploymentFiles($modulePath);
    if ($files === []) {
        throw new RuntimeException('Module directory is empty.');
    }
    assertModuleLibraryIdentity($modulePath, $plan['libraryGuid']);
    $identityText = implode(
        '',
        array_map(
            static fn (array $file): string => $file['relativePath']
                . "\0" . $file['size'] . "\0" . $file['sha256'] . "\n",
            $files
        )
    );
    $packageIdentitySha256 = hash('sha256', $identityText);

    $manifest = [
        'formatVersion' => SAEF_MODULE_DEPLOYMENT_PACKAGE_FORMAT,
        'deploymentKind' => 'standalone-module',
        'deploymentId' => $plan['deploymentId'],
        'targetDirectoryName' => $plan['targetDirectoryName'],
        'module' => [
            'targetId' => $plan['moduleTargetId'],
            'libraryGuid' => strtoupper($plan['libraryGuid']),
            'packageIdentitySha256' => $packageIdentitySha256,
            'transactionContractSha256' => hash('sha256', $transactionContract),
        ],
        'files' => array_map(
            static fn (array $file): array => [
                'path' => 'module/' . $file['relativePath'],
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
        throw new RuntimeException('Module deployment package output directory is missing.');
    }
    $temporaryPath = $outputPath . '.tmp-' . bin2hex(random_bytes(8));
    $archive = new ZipArchive();
    if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('Cannot create module deployment package archive.');
    }
    try {
        addStoredModuleDeploymentString($archive, 'deployment.json', $manifestJson);
        addStoredModuleDeploymentString($archive, 'module-transaction.json', $transactionContract);
        foreach ($files as $file) {
            $archiveName = 'module/' . $file['relativePath'];
            if (!$archive->addFile($file['absolutePath'], $archiveName)) {
                throw new RuntimeException("Cannot add module file: {$file['relativePath']}");
            }
            setStoredModuleDeploymentMetadata($archive, $archiveName);
        }
    } finally {
        $archive->close();
    }
    if (!rename($temporaryPath, $outputPath)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Cannot finalize module deployment package archive.');
    }

    fwrite(
        STDOUT,
        json_encode(
            [
                'formatVersion' => SAEF_MODULE_DEPLOYMENT_PACKAGE_FORMAT,
                'deploymentKind' => 'standalone-module',
                'deploymentId' => $plan['deploymentId'],
                'moduleTargetId' => $plan['moduleTargetId'],
                'packageIdentitySha256' => $packageIdentitySha256,
                'packageSha256' => hash_file('sha256', $outputPath),
                'fileCount' => count($files),
                'packageBytes' => filesize($outputPath),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Module deployment package build failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}

function resolveModuleDeploymentPlanPath(string $planPath, string $path): string
{
    if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
        return $path;
    }

    return dirname($planPath) . DIRECTORY_SEPARATOR . $path;
}

function assertSafeModuleDeploymentIdentifier(string $value): void
{
    if (preg_match('/^saef-[a-z0-9][a-z0-9.-]{0,63}$/D', $value) !== 1) {
        throw new RuntimeException('Module deployment identifier is invalid.');
    }
}

function assertSymconGuid(string $value): void
{
    if (preg_match('/^\{[A-Fa-f0-9]{8}(?:-[A-Fa-f0-9]{4}){3}-[A-Fa-f0-9]{12}\}$/D', $value) !== 1) {
        throw new RuntimeException('Module library GUID is invalid.');
    }
}

function assertModuleLibraryIdentity(string $modulePath, string $expectedGuid): void
{
    $libraryPath = $modulePath . DIRECTORY_SEPARATOR . 'library.json';
    if (!is_file($libraryPath) || is_link($libraryPath)) {
        throw new RuntimeException('Module library.json is missing or unsafe.');
    }
    $library = json_decode((string) file_get_contents($libraryPath), true, flags: JSON_THROW_ON_ERROR);
    if (
        !is_array($library)
        || !isset($library['id'])
        || !is_string($library['id'])
        || strtoupper($library['id']) !== strtoupper($expectedGuid)
    ) {
        throw new RuntimeException('Module library identity differs from the deployment plan.');
    }
    $moduleMetadata = glob($modulePath . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'module.json');
    if ($moduleMetadata === false || $moduleMetadata === []) {
        throw new RuntimeException('Module directory contains no module metadata.');
    }
    foreach ($moduleMetadata as $metadataPath) {
        if (!is_file($metadataPath) || is_link($metadataPath)) {
            throw new RuntimeException('Module metadata is missing or unsafe.');
        }
        $metadata = json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($metadata) || !isset($metadata['id']) || !is_string($metadata['id'])) {
            throw new RuntimeException('Module metadata identity is invalid.');
        }
        assertSymconGuid($metadata['id']);
    }
}

/** @return list<array{absolutePath: string, relativePath: string, sha256: string, size: int}> */
function collectModuleDeploymentFiles(string $root): array
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
            throw new RuntimeException('Module symlinks are not allowed.');
        }
        $absolutePath = $file->getPathname();
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root)));
        if (!isSafeModuleDeploymentPath($relativePath)) {
            throw new RuntimeException("Module path is unsafe: {$relativePath}");
        }
        $size = $file->getSize();
        $hash = hash_file('sha256', $absolutePath);
        if ($size === false || $hash === false) {
            throw new RuntimeException("Cannot inspect module file: {$relativePath}");
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

function isSafeModuleDeploymentPath(string $path): bool
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

function addStoredModuleDeploymentString(ZipArchive $archive, string $name, string $contents): void
{
    if (!$archive->addFromString($name, $contents)) {
        throw new RuntimeException("Cannot add archive content: {$name}");
    }
    setStoredModuleDeploymentMetadata($archive, $name);
}

function setStoredModuleDeploymentMetadata(ZipArchive $archive, string $name): void
{
    if (!$archive->setCompressionName($name, ZipArchive::CM_STORE)) {
        throw new RuntimeException("Cannot set archive compression: {$name}");
    }
    if (!$archive->setMtimeName($name, SAEF_MODULE_DEPLOYMENT_PACKAGE_MTIME)) {
        throw new RuntimeException("Cannot set archive timestamp: {$name}");
    }
}
