<?php

declare(strict_types=1);

const SAEF_OPEN_METEO_PUBLICATION_FORMAT_VERSION = 1;
const SAEF_OPEN_METEO_PUBLICATION_HASH_CONTEXT = "SAEF-OPEN-METEO-PUBLICATION\0v1\0";
const SAEF_OPEN_METEO_PUBLICATION_CONFIG = 'deployments/symcon/open-meteo-publication.json';
const SAEF_OPEN_METEO_FILESET_MANIFEST = 'deployments/symcon/open-meteo-module.fileset.json';

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(runOpenMeteoPublicationCli($_SERVER));
}

/** @param array<string, mixed> $server */
function runOpenMeteoPublicationCli(array $server): int
{
    try {
        $projectRoot = str_replace('\\', '/', dirname(__DIR__));
        $arguments = openMeteoPublicationArguments($server['argv'] ?? null);
        $options = parseOpenMeteoPublicationArguments($arguments);
        $contract = loadOpenMeteoPublicationContract($projectRoot);
        $candidate = buildOpenMeteoPublicationCandidate($projectRoot, $contract);

        if ($options['mode'] === 'check') {
            writeOpenMeteoPublicationResult([
                'outcome' => 'checked',
                'mutationAttempted' => false,
                'repository' => $contract['repository']['name'],
                'branch' => $contract['repository']['branch'],
                'fileCount' => count($candidate['files']),
                'filesetSha256' => $candidate['filesetSha256'],
                'publicationSha256' => $candidate['publicationSha256'],
            ]);

            return 0;
        }

        if ($options['mode'] === 'prepare') {
            $target = $options['prepareTarget'] !== ''
                ? $options['prepareTarget']
                : newOpenMeteoPublicationPath('saef-open-meteo-prepare-');
            writeOpenMeteoPublicationTree($target, $candidate['files']);
            verifyOpenMeteoPublicationTree($target, $candidate['files']);
            writeOpenMeteoPublicationResult([
                'outcome' => 'prepared',
                'mutationAttempted' => false,
                'target' => $target,
                'repository' => $contract['repository']['name'],
                'branch' => $contract['repository']['branch'],
                'fileCount' => count($candidate['files']),
                'filesetSha256' => $candidate['filesetSha256'],
                'publicationSha256' => $candidate['publicationSha256'],
            ]);

            return 0;
        }

        assertOpenMeteoApplyGate($options, $contract, $candidate);
        applyOpenMeteoPublication($contract, $candidate, $options);

        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Open-Meteo publication failed: ' . $exception->getMessage() . "\n");

        return 1;
    }
}

/** @return list<string> */
function openMeteoPublicationArguments(mixed $value): array
{
    if (!is_array($value) || !array_is_list($value)) {
        throw new RuntimeException('CLI arguments are unavailable.');
    }
    $arguments = [];
    foreach ($value as $argument) {
        if (!is_string($argument)) {
            throw new RuntimeException('CLI argument is not a string.');
        }
        $arguments[] = $argument;
    }

    return $arguments;
}

/**
 * @param list<string> $arguments
 *
 * @return array{
 *     mode: 'check'|'prepare'|'apply',
 *     prepareTarget: string,
 *     expectedFilesetSha256: string,
 *     expectedRemoteCommit: string,
 *     confirmation: string,
 *     commitMessage: string
 * }
 */
function parseOpenMeteoPublicationArguments(array $arguments): array
{
    array_shift($arguments);
    $mode = '';
    $prepareTarget = '';
    $expectedFilesetSha256 = '';
    $expectedRemoteCommit = '';
    $confirmation = '';
    $commitMessage = '';

    foreach ($arguments as $argument) {
        if ($argument === '--check' || $argument === '--prepare' || $argument === '--apply') {
            $candidateMode = substr($argument, 2);
            if ($mode !== '' && $mode !== $candidateMode) {
                throw new InvalidArgumentException('Publication modes are mutually exclusive.');
            }
            $mode = $candidateMode;
        } elseif (str_starts_with($argument, '--prepare=')) {
            if ($mode !== '' && $mode !== 'prepare') {
                throw new InvalidArgumentException('Publication modes are mutually exclusive.');
            }
            $mode = 'prepare';
            $prepareTarget = substr($argument, strlen('--prepare='));
        } elseif (str_starts_with($argument, '--expected-fileset-sha256=')) {
            $expectedFilesetSha256 = substr($argument, strlen('--expected-fileset-sha256='));
        } elseif (str_starts_with($argument, '--expected-remote-commit=')) {
            $expectedRemoteCommit = substr($argument, strlen('--expected-remote-commit='));
        } elseif (str_starts_with($argument, '--confirm-publication=')) {
            $confirmation = substr($argument, strlen('--confirm-publication='));
        } elseif (str_starts_with($argument, '--commit-message=')) {
            $commitMessage = substr($argument, strlen('--commit-message='));
        } else {
            throw new InvalidArgumentException('Unknown publication option: ' . $argument);
        }
    }

    if ($mode === '') {
        throw new InvalidArgumentException(
            'Usage: php tools/publish-open-meteo-module.php '
            . '--check|--prepare[=TARGET]|--apply [apply gates]'
        );
    }

    return [
        'mode' => $mode,
        'prepareTarget' => $prepareTarget,
        'expectedFilesetSha256' => strtolower($expectedFilesetSha256),
        'expectedRemoteCommit' => strtolower($expectedRemoteCommit),
        'confirmation' => $confirmation,
        'commitMessage' => $commitMessage,
    ];
}

/**
 * @return array{
 *     repository: array{name: string, publicUrl: string, cloneUrl: string, branch: string, confirmation: string},
 *     generatedDirectory: string,
 *     sourceMap: string,
 *     sidecar: string,
 *     metadata: list<array{source: string, target: string}>
 * }
 */
function loadOpenMeteoPublicationContract(string $projectRoot): array
{
    $path = $projectRoot . '/' . SAEF_OPEN_METEO_PUBLICATION_CONFIG;
    $decoded = json_decode(
        (string) file_get_contents($path),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (
        !is_array($decoded) || array_keys($decoded) !== [
        'formatVersion',
        'repository',
        'generatedDirectory',
        'sourceMap',
        'sidecar',
        'metadata',
        ]
    ) {
        throw new RuntimeException('Publication contract fields are invalid.');
    }
    if ($decoded['formatVersion'] !== SAEF_OPEN_METEO_PUBLICATION_FORMAT_VERSION) {
        throw new RuntimeException('Publication contract version is unsupported.');
    }
    $repository = $decoded['repository'] ?? null;
    if (
        !is_array($repository) || array_keys($repository) !== [
        'name', 'publicUrl', 'cloneUrl', 'branch', 'confirmation',
        ]
    ) {
        throw new RuntimeException('Publication repository contract is invalid.');
    }
    $expectedRepository = [
        'name' => 'doctee/saef-open-meteo',
        'publicUrl' => 'https://github.com/doctee/saef-open-meteo',
        'cloneUrl' => 'git@github.com:doctee/saef-open-meteo.git',
        'branch' => 'main',
        'confirmation' => 'publish-doctee-saef-open-meteo-main',
    ];
    if ($repository !== $expectedRepository) {
        throw new RuntimeException('Publication repository identity differs.');
    }

    foreach (['generatedDirectory', 'sourceMap', 'sidecar'] as $key) {
        if (!is_string($decoded[$key] ?? null)) {
            throw new RuntimeException('Publication path contract is invalid.');
        }
    }
    if (
        $decoded['generatedDirectory'] !== 'dist/symcon/saef-open-meteo-module'
        || $decoded['sourceMap'] !== 'fileset.sources.json'
        || $decoded['sidecar'] !== 'fileset.sha256'
    ) {
        throw new RuntimeException('Publication generated-tree identity differs.');
    }
    if (!is_array($decoded['metadata']) || !array_is_list($decoded['metadata'])) {
        throw new RuntimeException('Publication metadata contract is invalid.');
    }
    $metadata = [];
    $previousSource = null;
    $targets = [];
    foreach ($decoded['metadata'] as $mapping) {
        if (!is_array($mapping) || array_keys($mapping) !== ['source', 'target']) {
            throw new RuntimeException('Publication metadata mapping is invalid.');
        }
        $source = publicationRelativePath($mapping['source'] ?? null, 'metadata source');
        $target = publicationRelativePath($mapping['target'] ?? null, 'metadata target');
        if ($previousSource !== null && $source <= $previousSource) {
            throw new RuntimeException('Publication metadata sources must be sorted and unique.');
        }
        if (isset($targets[$target])) {
            throw new RuntimeException('Publication metadata targets must be unique.');
        }
        $previousSource = $source;
        $targets[$target] = true;
        $metadata[] = ['source' => $source, 'target' => $target];
    }
    if (
        $metadata !== [
        ['source' => 'LICENSE', 'target' => 'LICENSE'],
        ['source' => 'case-studies/open-meteo/publication/README.md', 'target' => 'README.md'],
        ]
    ) {
        throw new RuntimeException('Publication metadata allowlist differs.');
    }

    return [
        'repository' => $expectedRepository,
        'generatedDirectory' => $decoded['generatedDirectory'],
        'sourceMap' => $decoded['sourceMap'],
        'sidecar' => $decoded['sidecar'],
        'metadata' => $metadata,
    ];
}

/**
 * @param array{
 *     repository: array{name: string, publicUrl: string, cloneUrl: string, branch: string, confirmation: string},
 *     generatedDirectory: string,
 *     sourceMap: string,
 *     sidecar: string,
 *     metadata: list<array{source: string, target: string}>
 * } $contract
 *
 * @return array{files: array<string, string>, filesetSha256: string, publicationSha256: string}
 */
function buildOpenMeteoPublicationCandidate(string $projectRoot, array $contract): array
{
    runOpenMeteoPublicationCommand([
        PHP_BINARY,
        $projectRoot . '/tools/build-symcon-module-fileset.php',
        '--check',
        $projectRoot . '/' . SAEF_OPEN_METEO_FILESET_MANIFEST,
    ], $projectRoot);

    $generatedRoot = $projectRoot . '/' . $contract['generatedDirectory'];
    $sourceMapPath = $generatedRoot . '/' . $contract['sourceMap'];
    $sourceMap = json_decode(
        (string) file_get_contents($sourceMapPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($sourceMap) || ($sourceMap['name'] ?? null) !== 'saef-open-meteo-module') {
        throw new RuntimeException('Generated source map identity differs.');
    }
    $filesetSha256 = $sourceMap['filesetSha256'] ?? null;
    if (!is_string($filesetSha256) || preg_match('/^[a-f0-9]{64}$/D', $filesetSha256) !== 1) {
        throw new RuntimeException('Generated fileset hash is invalid.');
    }
    $sourceEntries = $sourceMap['files'] ?? null;
    if (!is_array($sourceEntries) || !array_is_list($sourceEntries) || count($sourceEntries) !== 24) {
        throw new RuntimeException('Generated publication payload count differs.');
    }

    $files = [];
    foreach ($sourceEntries as $entry) {
        if (!is_array($entry)) {
            throw new RuntimeException('Generated source-map entry is invalid.');
        }
        $target = publicationRelativePath($entry['target'] ?? null, 'payload target');
        $expectedHash = $entry['sha256'] ?? null;
        if (!is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1) {
            throw new RuntimeException('Generated payload hash is invalid.');
        }
        if (isset($files[$target])) {
            throw new RuntimeException('Generated publication target is duplicated.');
        }
        $contents = readOpenMeteoPublicationFile($generatedRoot . '/' . $target);
        if (!hash_equals($expectedHash, hash('sha256', $contents))) {
            throw new RuntimeException('Generated payload differs from its source map.');
        }
        $files[$target] = $contents;
    }

    $files[$contract['sourceMap']] = readOpenMeteoPublicationFile($sourceMapPath);
    $sidecar = readOpenMeteoPublicationFile($generatedRoot . '/' . $contract['sidecar']);
    if ($sidecar !== $filesetSha256 . "  fileset\n") {
        throw new RuntimeException('Generated fileset sidecar differs.');
    }
    $files[$contract['sidecar']] = $sidecar;

    foreach ($contract['metadata'] as $mapping) {
        if (isset($files[$mapping['target']])) {
            throw new RuntimeException('Publication metadata overlaps a generated target.');
        }
        $files[$mapping['target']] = readOpenMeteoPublicationFile(
            $projectRoot . '/' . $mapping['source']
        );
    }
    ksort($files, SORT_STRING);
    if (count($files) !== 28) {
        throw new RuntimeException('Complete publication file count differs.');
    }

    assertOpenMeteoPublicationPrivacy($files);
    assertOpenMeteoPublicationMetadata($files, $contract['repository']['publicUrl']);

    return [
        'files' => $files,
        'filesetSha256' => $filesetSha256,
        'publicationSha256' => hashOpenMeteoPublication($files),
    ];
}

/**
 * @param array{
 *     mode: string,
 *     expectedFilesetSha256: string,
 *     expectedRemoteCommit: string,
 *     confirmation: string,
 *     commitMessage: string
 * } $options
 * @param array{
 *     repository: array{name: string, publicUrl: string, cloneUrl: string, branch: string, confirmation: string}
 * } $contract
 * @param array{filesetSha256: string} $candidate
 */
function assertOpenMeteoApplyGate(array $options, array $contract, array $candidate): void
{
    if ($options['mode'] !== 'apply') {
        throw new RuntimeException('Unknown publication mode.');
    }
    if (!hash_equals($candidate['filesetSha256'], $options['expectedFilesetSha256'])) {
        throw new RuntimeException('Explicit expected fileset hash does not match the candidate.');
    }
    if (preg_match('/^[a-f0-9]{40}$/D', $options['expectedRemoteCommit']) !== 1) {
        throw new RuntimeException('Explicit expected remote commit must be a full SHA-1.');
    }
    if (!hash_equals($contract['repository']['confirmation'], $options['confirmation'])) {
        throw new RuntimeException('Explicit publication confirmation differs.');
    }
    if (
        $options['commitMessage'] === ''
        || strlen($options['commitMessage']) > 100
        || str_contains($options['commitMessage'], "\n")
        || str_contains($options['commitMessage'], "\r")
    ) {
        throw new RuntimeException('Publication commit message is invalid.');
    }
}

/**
 * @param array{
 *     repository: array{name: string, publicUrl: string, cloneUrl: string, branch: string, confirmation: string}
 * } $contract
 * @param array{files: array<string, string>, filesetSha256: string, publicationSha256: string} $candidate
 * @param array{expectedRemoteCommit: string, commitMessage: string} $options
 */
function applyOpenMeteoPublication(array $contract, array $candidate, array $options): void
{
    $temporaryRoot = newOpenMeteoPublicationPath('saef-open-meteo-apply-');
    if (!mkdir($temporaryRoot, 0700)) {
        throw new RuntimeException('Cannot create publication workspace.');
    }
    $workingTree = $temporaryRoot . '/repository';
    $verificationTree = $temporaryRoot . '/verification';
    $mutationAttempted = false;
    $preserveWorkspace = false;

    try {
        runOpenMeteoPublicationCommand([
            'git', 'clone', '--branch', $contract['repository']['branch'],
            '--single-branch', '--no-tags', $contract['repository']['cloneUrl'], $workingTree,
        ], $temporaryRoot);
        assertOpenMeteoPublicationGitBaseline(
            $workingTree,
            $contract['repository']['cloneUrl'],
            $contract['repository']['branch'],
            $options['expectedRemoteCommit']
        );
        assertOpenMeteoPublicationBaselinePathsAllowed($workingTree, $candidate['files'], true);
        writeOpenMeteoPublicationFiles($workingTree, $candidate['files']);
        runOpenMeteoPublicationCommand(['git', 'diff', '--check'], $workingTree);

        $status = trim(runOpenMeteoPublicationCommand(
            ['git', 'status', '--porcelain=v1', '--untracked-files=all'],
            $workingTree
        ));
        if ($status === '') {
            verifyOpenMeteoPublicationRemote(
                $temporaryRoot,
                $verificationTree,
                $contract,
                $candidate,
                $options['expectedRemoteCommit']
            );
            writeOpenMeteoPublicationResult([
                'outcome' => 'unchanged',
                'mutationAttempted' => false,
                'repository' => $contract['repository']['name'],
                'branch' => $contract['repository']['branch'],
                'commit' => $options['expectedRemoteCommit'],
                'fileCount' => count($candidate['files']),
                'filesetSha256' => $candidate['filesetSha256'],
                'publicationSha256' => $candidate['publicationSha256'],
            ]);
            return;
        }

        $addCommand = ['git', 'add', '--'];
        foreach (array_keys($candidate['files']) as $path) {
            $addCommand[] = $path;
        }
        runOpenMeteoPublicationCommand($addCommand, $workingTree);
        $stagedPaths = array_values(array_filter(explode(
            "\n",
            trim(runOpenMeteoPublicationCommand(['git', 'diff', '--cached', '--name-only'], $workingTree))
        )));
        foreach ($stagedPaths as $path) {
            if (!array_key_exists($path, $candidate['files'])) {
                throw new RuntimeException('Staged publication contains an unclassified path.');
            }
        }
        if ($stagedPaths === []) {
            throw new RuntimeException('Publication status changed without a staged candidate file.');
        }

        runOpenMeteoPublicationCommand(
            ['git', 'commit', '-m', $options['commitMessage']],
            $workingTree
        );
        $newCommit = trim(runOpenMeteoPublicationCommand(['git', 'rev-parse', 'HEAD'], $workingTree));
        $remoteBeforePush = openMeteoPublicationRemoteCommit(
            $workingTree,
            $contract['repository']['branch']
        );
        if (!hash_equals($options['expectedRemoteCommit'], $remoteBeforePush)) {
            throw new RuntimeException('Remote branch changed after the publication clone.');
        }

        $mutationAttempted = true;
        runOpenMeteoPublicationCommand([
            'git', 'push', 'origin',
            'HEAD:refs/heads/' . $contract['repository']['branch'],
        ], $workingTree);
        verifyOpenMeteoPublicationRemote(
            $temporaryRoot,
            $verificationTree,
            $contract,
            $candidate,
            $newCommit
        );
        writeOpenMeteoPublicationResult([
            'outcome' => 'published',
            'mutationAttempted' => true,
            'repository' => $contract['repository']['name'],
            'branch' => $contract['repository']['branch'],
            'previousCommit' => $options['expectedRemoteCommit'],
            'commit' => $newCommit,
            'changedFileCount' => count($stagedPaths),
            'fileCount' => count($candidate['files']),
            'filesetSha256' => $candidate['filesetSha256'],
            'publicationSha256' => $candidate['publicationSha256'],
        ]);
    } catch (Throwable $exception) {
        if ($mutationAttempted) {
            $preserveWorkspace = true;
            throw new RuntimeException(
                'Remote mutation was attempted; preserve workspace evidence at '
                . $temporaryRoot . ' and verify the remote before retrying. '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
        throw $exception;
    } finally {
        if (!$preserveWorkspace) {
            removeOpenMeteoPublicationTree($temporaryRoot);
        }
    }
}

/** @param array<string, string> $files */
function writeOpenMeteoPublicationTree(string $target, array $files): void
{
    if ($target === '' || !str_starts_with($target, '/')) {
        throw new RuntimeException('Prepare target must be an absolute path.');
    }
    if (file_exists($target)) {
        throw new RuntimeException('Prepare target must not already exist.');
    }
    if (!mkdir($target, 0700, true)) {
        throw new RuntimeException('Cannot create publication prepare target.');
    }
    writeOpenMeteoPublicationFiles($target, $files);
}

/** @param array<string, string> $files */
function writeOpenMeteoPublicationFiles(string $root, array $files): void
{
    foreach ($files as $relative => $contents) {
        $absolute = $root . '/' . $relative;
        $directory = dirname($absolute);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create publication directory.');
        }
        $temporary = $directory . '/.' . basename($absolute) . '.tmp.' . getmypid();
        if (
            file_put_contents($temporary, $contents) !== strlen($contents)
            || !rename($temporary, $absolute)
        ) {
            throw new RuntimeException('Cannot write publication file.');
        }
    }
}

/** @param array<string, string> $files */
function verifyOpenMeteoPublicationTree(string $root, array $files, bool $allowGit = false): void
{
    $actual = openMeteoPublicationTreeHashes($root, $allowGit);
    $expected = [];
    foreach ($files as $relative => $contents) {
        $expected[$relative] = hash('sha256', $contents);
    }
    ksort($expected, SORT_STRING);
    if ($actual !== $expected) {
        throw new RuntimeException('Publication tree contains missing, changed or additional files.');
    }
}

/** @param array<string, string> $files */
function assertOpenMeteoPublicationBaselinePathsAllowed(
    string $root,
    array $files,
    bool $allowGit = false
): void {
    $actual = array_keys(openMeteoPublicationTreeHashes($root, $allowGit));
    foreach ($actual as $path) {
        if (!array_key_exists($path, $files)) {
            throw new RuntimeException(
                'Publication baseline contains a path outside the allowlist.'
            );
        }
    }
}

/** @return array<string, string> */
function openMeteoPublicationTreeHashes(string $root, bool $allowGit): array
{
    if (!is_dir($root)) {
        throw new RuntimeException('Publication tree is missing.');
    }
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if ($allowGit && ($relative === '.git' || str_starts_with($relative, '.git/'))) {
            continue;
        }
        if ($item->isLink()) {
            throw new RuntimeException('Publication tree contains a symbolic link.');
        }
        if (!$item->isFile()) {
            continue;
        }
        $hash = hash_file('sha256', $item->getPathname());
        if ($hash === false) {
            throw new RuntimeException('Cannot hash publication tree file.');
        }
        $hashes[$relative] = $hash;
    }
    ksort($hashes, SORT_STRING);

    return $hashes;
}

function assertOpenMeteoPublicationGitBaseline(
    string $workingTree,
    string $expectedRemote,
    string $expectedBranch,
    string $expectedCommit
): void {
    $status = trim(runOpenMeteoPublicationCommand(
        ['git', 'status', '--porcelain=v1', '--untracked-files=all'],
        $workingTree
    ));
    $remote = trim(runOpenMeteoPublicationCommand(['git', 'remote', 'get-url', 'origin'], $workingTree));
    $branch = trim(runOpenMeteoPublicationCommand(['git', 'branch', '--show-current'], $workingTree));
    $commit = trim(runOpenMeteoPublicationCommand(['git', 'rev-parse', 'HEAD'], $workingTree));
    if (
        $status !== '' || $remote !== $expectedRemote || $branch !== $expectedBranch
        || !hash_equals($expectedCommit, $commit)
    ) {
        throw new RuntimeException('Publication repository baseline differs.');
    }
    foreach (['user.name', 'user.email'] as $key) {
        if (trim(runOpenMeteoPublicationCommand(['git', 'config', '--get', $key], $workingTree)) === '') {
            throw new RuntimeException('Git publication identity is incomplete.');
        }
    }
}

/**
 * @param array{
 *     repository: array{name: string, publicUrl: string, cloneUrl: string, branch: string, confirmation: string}
 * } $contract
 * @param array{files: array<string, string>} $candidate
 */
function verifyOpenMeteoPublicationRemote(
    string $temporaryRoot,
    string $verificationTree,
    array $contract,
    array $candidate,
    string $expectedCommit
): void {
    if (file_exists($verificationTree)) {
        throw new RuntimeException('Publication verification target already exists.');
    }
    runOpenMeteoPublicationCommand([
        'git', 'clone', '--depth', '1', '--branch', $contract['repository']['branch'],
        '--single-branch', '--no-tags', $contract['repository']['cloneUrl'], $verificationTree,
    ], $temporaryRoot);
    $commit = trim(runOpenMeteoPublicationCommand(['git', 'rev-parse', 'HEAD'], $verificationTree));
    if (!hash_equals($expectedCommit, $commit)) {
        throw new RuntimeException('Independent remote verification commit differs.');
    }
    verifyOpenMeteoPublicationTree($verificationTree, $candidate['files'], true);
}

function openMeteoPublicationRemoteCommit(string $workingTree, string $branch): string
{
    $output = trim(runOpenMeteoPublicationCommand(
        ['git', 'ls-remote', 'origin', 'refs/heads/' . $branch],
        $workingTree
    ));
    if (preg_match('/^([a-f0-9]{40})\s+refs\/heads\/' . preg_quote($branch, '/') . '$/D', $output, $matches) !== 1) {
        throw new RuntimeException('Cannot resolve exact remote publication commit.');
    }

    return $matches[1];
}

/** @param array<string, string> $files */
function assertOpenMeteoPublicationPrivacy(array $files): void
{
    foreach ($files as $path => $contents) {
        foreach (['/Users/', '\\Users\\', 'Seestall', 'München'] as $marker) {
            if (str_contains($contents, $marker)) {
                throw new RuntimeException('Publication contains a private marker in ' . $path . '.');
            }
        }
        if (preg_match('/(?:^|[^0-9])(?:10\.|192\.168\.|172\.(?:1[6-9]|2[0-9]|3[01])\.)[0-9]{1,3}\.[0-9]{1,3}(?:[^0-9]|$)/', $contents) === 1) {
            throw new RuntimeException('Publication contains a private network address in ' . $path . '.');
        }
    }
}

/** @param array<string, string> $files */
function assertOpenMeteoPublicationMetadata(array $files, string $publicUrl): void
{
    foreach (['library.json', 'OpenMeteoWeather/module.json', 'OpenMeteoSolarForecast/module.json'] as $path) {
        $metadata = json_decode($files[$path] ?? '', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($metadata) || ($metadata['url'] ?? null) !== $publicUrl) {
            throw new RuntimeException('Publication module URL differs in ' . $path . '.');
        }
    }
    if (
        !str_contains($files['README.md'] ?? '', $publicUrl)
        || !str_contains($files['LICENSE'] ?? '', 'PolyForm Noncommercial License 1.0.0')
    ) {
        throw new RuntimeException('Publication README or license identity differs.');
    }
}

/** @param array<string, string> $files */
function hashOpenMeteoPublication(array $files): string
{
    ksort($files, SORT_STRING);
    $context = hash_init('sha256');
    hash_update($context, SAEF_OPEN_METEO_PUBLICATION_HASH_CONTEXT);
    foreach ($files as $path => $contents) {
        hash_update($context, strlen($path) . "\0" . $path . strlen($contents) . "\0" . $contents);
    }

    return hash_final($context);
}

function publicationRelativePath(mixed $path, string $kind): string
{
    if (
        !is_string($path) || $path === '' || str_contains($path, '\\')
        || str_contains($path, '://') || str_starts_with($path, '/')
    ) {
        throw new RuntimeException('Publication ' . $kind . ' is unsafe.');
    }
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new RuntimeException('Publication ' . $kind . ' is unsafe.');
        }
    }

    return $path;
}

function readOpenMeteoPublicationFile(string $path): string
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('Publication source file is missing or not canonical.');
    }
    $contents = file_get_contents($path);
    if ($contents === false || str_contains($contents, "\r")) {
        throw new RuntimeException('Publication source must be readable LF-only content.');
    }

    return $contents;
}

/** @param list<string> $command */
function runOpenMeteoPublicationCommand(array $command, string $workingDirectory): string
{
    $pipes = [];
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start publication subprocess.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('Publication subprocess failed: ' . trim($stderr . $stdout));
    }

    return $stdout;
}

function newOpenMeteoPublicationPath(string $prefix): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
}

function removeOpenMeteoPublicationTree(string $root): void
{
    $temporaryPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'saef-open-meteo-apply-';
    if (!str_starts_with($root, $temporaryPrefix) || !is_dir($root)) {
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

/** @param array<string, mixed> $result */
function writeOpenMeteoPublicationResult(array $result): void
{
    fwrite(STDOUT, json_encode(
        $result,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n");
}
