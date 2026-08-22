<?php

declare(strict_types=1);

const SAEF_MODULE_PUBLICATION_FORMAT_VERSION = 1;
const SAEF_MODULE_PUBLICATION_TOPIC_FILESYSTEM_PREFIX = 'saef-module-publication-';

/** @param array<string, mixed> $server */
function runModulePublicationCli(
    array $server,
    ?string $boundContract = null,
    string $programName = 'tools/publish-symcon-module.php',
    bool $openMeteoCompatibility = false
): int {
    try {
        $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $arguments = modulePublicationArguments($server['argv'] ?? null);
        $options = parseModulePublicationArguments($arguments, $boundContract, $programName);
        $contract = loadModulePublicationContract($projectRoot, $options['contractPath']);
        $candidate = buildModulePublicationCandidate($projectRoot, $contract);

        if ($options['mode'] === 'check') {
            writeModulePublicationResult([
                'outcome' => 'checked',
                'mutationAttempted' => false,
                ...modulePublicationResultIdentity($contract, $openMeteoCompatibility),
                'repository' => $contract['repository']['name'],
                'branch' => $contract['repository']['baseBranch'],
                'fileCount' => count($candidate['files']),
                'filesetSha256' => $candidate['filesetSha256'],
                'publicationSha256' => $candidate['publicationSha256'],
            ]);

            return 0;
        }

        if ($options['mode'] === 'prepare') {
            $target = $options['prepareTarget'] !== ''
                ? $options['prepareTarget']
                : newModulePublicationPath(
                    $openMeteoCompatibility
                        ? 'saef-open-meteo-prepare-'
                        : 'saef-module-publication-prepare-'
                );
            writeModulePublicationTree($target, $candidate['files']);
            verifyModulePublicationTree($target, $candidate['files']);
            writeModulePublicationResult([
                'outcome' => 'prepared',
                'mutationAttempted' => false,
                'target' => $target,
                ...modulePublicationResultIdentity($contract, $openMeteoCompatibility),
                'repository' => $contract['repository']['name'],
                'branch' => $contract['repository']['baseBranch'],
                'fileCount' => count($candidate['files']),
                'filesetSha256' => $candidate['filesetSha256'],
                'publicationSha256' => $candidate['publicationSha256'],
            ]);

            return 0;
        }

        assertModulePublicationApplyGate($options, $contract, $candidate);
        applyModulePublication(
            $contract,
            $candidate,
            $options,
            null,
            $openMeteoCompatibility
        );

        return 0;
    } catch (Throwable $exception) {
        $failurePrefix = $openMeteoCompatibility
            ? 'Open-Meteo publication failed: '
            : 'Module publication failed: ';
        fwrite(STDERR, $failurePrefix . $exception->getMessage() . "\n");

        return 1;
    }
}

/**
 * @param array{name: string, publication: array{mode: 'direct_branch'|'pull_request'}} $contract
 *
 * @return array{}|array{publication: string, publicationMode: 'direct_branch'|'pull_request'}
 */
function modulePublicationResultIdentity(array $contract, bool $openMeteoCompatibility): array
{
    if ($openMeteoCompatibility) {
        return [];
    }

    return [
        'publication' => $contract['name'],
        'publicationMode' => $contract['publication']['mode'],
    ];
}

/** @return list<string> */
function modulePublicationArguments(mixed $value): array
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
 *     contractPath: string,
 *     prepareTarget: string,
 *     expectedFilesetSha256: string,
 *     expectedPublicationSha256: string,
 *     expectedRemoteCommit: string,
 *     confirmation: string,
 *     commitMessage: string
 * }
 */
function parseModulePublicationArguments(
    array $arguments,
    ?string $boundContract,
    string $programName
): array {
    array_shift($arguments);
    $mode = '';
    $prepareTarget = '';
    $expectedFilesetSha256 = '';
    $expectedPublicationSha256 = '';
    $expectedRemoteCommit = '';
    $confirmation = '';
    $commitMessage = '';
    $contractPath = $boundContract ?? '';

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
        } elseif (str_starts_with($argument, '--expected-publication-sha256=')) {
            $expectedPublicationSha256 = substr(
                $argument,
                strlen('--expected-publication-sha256=')
            );
        } elseif (str_starts_with($argument, '--confirm-publication=')) {
            $confirmation = substr($argument, strlen('--confirm-publication='));
        } elseif (str_starts_with($argument, '--commit-message=')) {
            $commitMessage = substr($argument, strlen('--commit-message='));
        } elseif (str_starts_with($argument, '--contract=')) {
            $requestedContract = substr($argument, strlen('--contract='));
            if ($boundContract !== null && $requestedContract !== $boundContract) {
                throw new InvalidArgumentException('Bound publication contract cannot be overridden.');
            }
            $contractPath = $requestedContract;
        } else {
            throw new InvalidArgumentException('Unknown publication option: ' . $argument);
        }
    }

    if ($mode === '') {
        throw new InvalidArgumentException(
            'Usage: php ' . $programName . ' '
            . '--check|--prepare[=TARGET]|--apply [apply gates]'
        );
    }
    if ($contractPath === '') {
        throw new InvalidArgumentException('An explicit publication contract is required.');
    }

    return [
        'mode' => $mode,
        'contractPath' => publicationRelativePath($contractPath, 'contract path'),
        'prepareTarget' => $prepareTarget,
        'expectedFilesetSha256' => strtolower($expectedFilesetSha256),
        'expectedPublicationSha256' => strtolower($expectedPublicationSha256),
        'expectedRemoteCommit' => strtolower($expectedRemoteCommit),
        'confirmation' => $confirmation,
        'commitMessage' => $commitMessage,
    ];
}

/**
 * @return array<string, mixed>
 */
function loadModulePublicationContract(
    string $projectRoot,
    string $relativePath,
    bool $allowTestFixture = false
): array {
    if (
        !$allowTestFixture
        && preg_match(
            '~^deployments/symcon/[a-z0-9-]+-publication\.json$~D',
            $relativePath
        ) !== 1
    ) {
        throw new RuntimeException(
            'Publication contract must be a public deployments/symcon contract.'
        );
    }
    $path = $projectRoot . '/' . publicationRelativePath($relativePath, 'contract path');
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('Publication contract is missing or not canonical.');
    }
    assertModulePublicationProjectPath($projectRoot, $relativePath, false);
    $decoded = json_decode(
        (string) file_get_contents($path),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($decoded)) {
        throw new RuntimeException('Publication contract must be an object.');
    }
    assertModulePublicationFields($decoded, [
        'formatVersion',
        'name',
        'hashNamespace',
        'repository',
        'publication',
        'generated',
        'metadata',
        'inventory',
        'privacy',
        'metadataValidation',
    ], 'contract');
    if ($decoded['formatVersion'] !== SAEF_MODULE_PUBLICATION_FORMAT_VERSION) {
        throw new RuntimeException('Publication contract version is unsupported.');
    }
    $name = modulePublicationIdentifier($decoded['name'] ?? null, 'publication name');
    $hashNamespace = $decoded['hashNamespace'] ?? null;
    if (!is_string($hashNamespace) || preg_match('/^SAEF-[A-Z0-9-]+-PUBLICATION$/D', $hashNamespace) !== 1) {
        throw new RuntimeException('Publication hash namespace is invalid.');
    }

    $repository = $decoded['repository'] ?? null;
    if (!is_array($repository)) {
        throw new RuntimeException('Publication repository contract is invalid.');
    }
    assertModulePublicationFields($repository, [
        'name', 'publicUrl', 'cloneUrl', 'baseBranch', 'confirmation',
    ], 'repository');
    foreach (['name', 'publicUrl', 'cloneUrl', 'baseBranch', 'confirmation'] as $key) {
        if (!is_string($repository[$key] ?? null) || $repository[$key] === '') {
            throw new RuntimeException('Publication repository field is invalid: ' . $key . '.');
        }
    }
    if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/D', $repository['name']) !== 1) {
        throw new RuntimeException('Publication repository name is invalid.');
    }
    if ($repository['publicUrl'] !== 'https://github.com/' . $repository['name']) {
        throw new RuntimeException('Publication public repository identity differs.');
    }
    if (
        $repository['cloneUrl'] !== 'git@github.com:' . $repository['name'] . '.git'
        && $repository['cloneUrl'] !== $repository['publicUrl'] . '.git'
    ) {
        throw new RuntimeException('Publication clone repository identity differs.');
    }
    assertModulePublicationBranch($repository['baseBranch'], 'base branch');
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/D', $repository['confirmation']) !== 1) {
        throw new RuntimeException('Publication confirmation token is invalid.');
    }

    $publication = $decoded['publication'] ?? null;
    if (!is_array($publication)) {
        throw new RuntimeException('Publication mode contract is invalid.');
    }
    assertModulePublicationFields($publication, ['mode', 'topicBranch', 'pullRequest'], 'publication mode');
    if (!in_array($publication['mode'] ?? null, ['direct_branch', 'pull_request'], true)) {
        throw new RuntimeException('Publication mode is unsupported.');
    }
    if ($publication['mode'] === 'direct_branch') {
        if ($name !== 'open-meteo') {
            throw new RuntimeException(
                'Direct-branch publication is restricted to the Open-Meteo compatibility contract.'
            );
        }
        if ($publication['topicBranch'] !== null || $publication['pullRequest'] !== null) {
            throw new RuntimeException('Direct publication must not define pull-request fields.');
        }
    } else {
        if (!is_string($publication['topicBranch'] ?? null)) {
            throw new RuntimeException('Pull-request topic branch is invalid.');
        }
        assertModulePublicationBranch($publication['topicBranch'], 'topic branch template', true);
        $pullRequest = $publication['pullRequest'] ?? null;
        if (!is_array($pullRequest)) {
            throw new RuntimeException('Pull-request metadata is invalid.');
        }
        assertModulePublicationFields($pullRequest, ['repository', 'base', 'title', 'body'], 'pull request');
        if (
            ($pullRequest['repository'] ?? null) !== $repository['name']
            || ($pullRequest['base'] ?? null) !== $repository['baseBranch']
        ) {
            throw new RuntimeException('Pull-request repository or base differs.');
        }
        foreach (['title', 'body'] as $key) {
            if (!is_string($pullRequest[$key] ?? null) || trim($pullRequest[$key]) === '') {
                throw new RuntimeException('Pull-request ' . $key . ' is invalid.');
            }
        }
    }

    $generated = $decoded['generated'] ?? null;
    if (!is_array($generated)) {
        throw new RuntimeException('Generated publication contract is invalid.');
    }
    assertModulePublicationFields(
        $generated,
        ['directory', 'manifest', 'sourceMap', 'sidecar', 'name'],
        'generated publication'
    );
    foreach (['directory', 'manifest', 'sourceMap', 'sidecar'] as $key) {
        $generated[$key] = publicationRelativePath($generated[$key] ?? null, 'generated ' . $key);
    }
    $generated['name'] = modulePublicationIdentifier($generated['name'] ?? null, 'generated name');
    assertModulePublicationProjectPath($projectRoot, $generated['directory'], true);
    assertModulePublicationProjectPath($projectRoot, $generated['manifest'], false);
    assertModulePublicationProjectPath(
        $projectRoot,
        $generated['directory'] . '/' . $generated['sourceMap'],
        false
    );
    assertModulePublicationProjectPath(
        $projectRoot,
        $generated['directory'] . '/' . $generated['sidecar'],
        false
    );

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
        assertModulePublicationProjectPath($projectRoot, $source, false);
        $metadata[] = ['source' => $source, 'target' => $target];
    }
    $inventory = modulePublicationPathList($decoded['inventory'] ?? null, 'inventory');
    $privacy = $decoded['privacy'] ?? null;
    if (!is_array($privacy)) {
        throw new RuntimeException('Publication privacy policy is invalid.');
    }
    assertModulePublicationFields($privacy, ['forbiddenMarkers'], 'privacy');
    if (!is_array($privacy['forbiddenMarkers']) || !array_is_list($privacy['forbiddenMarkers'])) {
        throw new RuntimeException('Publication privacy markers are invalid.');
    }
    $forbiddenMarkers = [];
    foreach ($privacy['forbiddenMarkers'] as $marker) {
        if (!is_string($marker) || $marker === '' || strlen($marker) > 128) {
            throw new RuntimeException('Publication privacy marker is invalid.');
        }
        $forbiddenMarkers[] = $marker;
    }

    $metadataValidation = $decoded['metadataValidation'] ?? null;
    if (!is_array($metadataValidation)) {
        throw new RuntimeException('Publication metadata validation policy is invalid.');
    }
    assertModulePublicationFields(
        $metadataValidation,
        ['repositoryUrlPaths', 'readmeTarget', 'licenseTarget', 'licenseMarker'],
        'metadata validation'
    );
    $metadataValidation['repositoryUrlPaths'] = modulePublicationPathList(
        $metadataValidation['repositoryUrlPaths'] ?? null,
        'metadata URL paths'
    );
    foreach (['readmeTarget', 'licenseTarget'] as $key) {
        $metadataValidation[$key] = publicationRelativePath(
            $metadataValidation[$key] ?? null,
            'metadata validation ' . $key
        );
    }
    if (!is_string($metadataValidation['licenseMarker'] ?? null) || $metadataValidation['licenseMarker'] === '') {
        throw new RuntimeException('Publication license marker is invalid.');
    }

    return [
        'name' => $name,
        'hashNamespace' => $hashNamespace,
        'repository' => $repository,
        'publication' => $publication,
        'generated' => $generated,
        'metadata' => $metadata,
        'inventory' => $inventory,
        'privacy' => ['forbiddenMarkers' => $forbiddenMarkers],
        'metadataValidation' => $metadataValidation,
    ];
}

/**
 * @param array<string, mixed> $contract
 *
 * @return array{files: array<string, string>, filesetSha256: string, publicationSha256: string}
 */
function buildModulePublicationCandidate(string $projectRoot, array $contract): array
{
    runModulePublicationCommand([
        PHP_BINARY,
        $projectRoot . '/tools/build-symcon-module-fileset.php',
        '--check',
        $projectRoot . '/' . $contract['generated']['manifest'],
    ], $projectRoot);

    $generatedRoot = $projectRoot . '/' . $contract['generated']['directory'];
    $sourceMapPath = $generatedRoot . '/' . $contract['generated']['sourceMap'];
    $sourceMap = json_decode(
        (string) file_get_contents($sourceMapPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($sourceMap) || ($sourceMap['name'] ?? null) !== $contract['generated']['name']) {
        throw new RuntimeException('Generated source map identity differs.');
    }
    $filesetSha256 = $sourceMap['filesetSha256'] ?? null;
    if (!is_string($filesetSha256) || preg_match('/^[a-f0-9]{64}$/D', $filesetSha256) !== 1) {
        throw new RuntimeException('Generated fileset hash is invalid.');
    }
    $sourceEntries = $sourceMap['files'] ?? null;
    if (!is_array($sourceEntries) || !array_is_list($sourceEntries) || $sourceEntries === []) {
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
        $contents = readModulePublicationFile($generatedRoot . '/' . $target);
        if (!hash_equals($expectedHash, hash('sha256', $contents))) {
            throw new RuntimeException('Generated payload differs from its source map.');
        }
        $files[$target] = $contents;
    }

    $files[$contract['generated']['sourceMap']] = readModulePublicationFile($sourceMapPath);
    $sidecar = readModulePublicationFile($generatedRoot . '/' . $contract['generated']['sidecar']);
    if ($sidecar !== $filesetSha256 . "  fileset\n") {
        throw new RuntimeException('Generated fileset sidecar differs.');
    }
    $files[$contract['generated']['sidecar']] = $sidecar;

    foreach ($contract['metadata'] as $mapping) {
        if (isset($files[$mapping['target']])) {
            throw new RuntimeException('Publication metadata overlaps a generated target.');
        }
        $files[$mapping['target']] = readModulePublicationFile(
            $projectRoot . '/' . $mapping['source']
        );
    }
    ksort($files, SORT_STRING);
    if (array_keys($files) !== $contract['inventory']) {
        throw new RuntimeException('Complete publication inventory differs.');
    }

    assertModulePublicationPrivacy($files, $contract['privacy']);
    assertModulePublicationMetadata(
        $files,
        $contract['repository']['publicUrl'],
        $contract['metadataValidation']
    );

    return [
        'files' => $files,
        'filesetSha256' => $filesetSha256,
        'publicationSha256' => hashModulePublication($files, $contract['hashNamespace']),
    ];
}

/**
 * @param array{
 *     mode: string,
 *     expectedFilesetSha256: string,
 *     expectedPublicationSha256: string,
 *     expectedRemoteCommit: string,
 *     confirmation: string,
 *     commitMessage: string
 * } $options
 * @param array{
 *     repository: array{name: string, confirmation: string},
 *     publication: array{mode: 'direct_branch'|'pull_request'}
 * } $contract
 * @param array{filesetSha256: string, publicationSha256: string} $candidate
 */
function assertModulePublicationApplyGate(array $options, array $contract, array $candidate): void
{
    if ($options['mode'] !== 'apply') {
        throw new RuntimeException('Unknown publication mode.');
    }
    if (!hash_equals($candidate['filesetSha256'], $options['expectedFilesetSha256'])) {
        throw new RuntimeException('Explicit expected fileset hash does not match the candidate.');
    }
    if (
        $options['expectedPublicationSha256'] !== ''
        && !hash_equals(
            $candidate['publicationSha256'],
            $options['expectedPublicationSha256']
        )
    ) {
        throw new RuntimeException('Explicit expected publication hash does not match the candidate.');
    }
    if (
        $contract['publication']['mode'] === 'pull_request'
        && $options['expectedPublicationSha256'] === ''
    ) {
        throw new RuntimeException('Explicit expected publication hash is required for PR publication.');
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
 *     name: string,
 *     repository: array{name: string, cloneUrl: string, baseBranch: string},
 *     publication: array{
 *         mode: 'direct_branch'|'pull_request',
 *         topicBranch: ?string,
 *         pullRequest: ?array{repository: string, base: string, title: string, body: string}
 *     }
 * } $contract
 * @param array{files: array<string, string>, filesetSha256: string, publicationSha256: string} $candidate
 * @param array{expectedRemoteCommit: string, commitMessage: string} $options
 */
function applyModulePublication(
    array $contract,
    array $candidate,
    array $options,
    ?callable $beforeRemoteDriftCheck = null,
    bool $openMeteoCompatibility = false
): void {
    $temporaryRoot = newModulePublicationPath(
        $openMeteoCompatibility
            ? 'saef-open-meteo-apply-'
            : 'saef-module-publication-apply-'
    );
    if (!mkdir($temporaryRoot, 0700)) {
        throw new RuntimeException('Cannot create publication workspace.');
    }
    $workingTree = $temporaryRoot . '/repository';
    $verificationTree = $temporaryRoot . '/verification';
    $mutationAttempted = false;
    $preserveWorkspace = false;
    $baseBranch = $contract['repository']['baseBranch'];
    $targetBranch = $contract['publication']['mode'] === 'pull_request'
        ? modulePublicationTopicBranch(
            $contract['publication']['topicBranch'],
            $contract['name'],
            $candidate['filesetSha256']
        )
        : $baseBranch;

    try {
        runModulePublicationCommand([
            'git', 'clone', '--branch', $baseBranch,
            '--single-branch', '--no-tags', $contract['repository']['cloneUrl'], $workingTree,
        ], $temporaryRoot);
        assertModulePublicationGitBaseline(
            $workingTree,
            $contract['repository']['cloneUrl'],
            $baseBranch,
            $options['expectedRemoteCommit']
        );
        assertModulePublicationBaselinePathsAllowed($workingTree, $candidate['files'], true);
        writeModulePublicationFiles($workingTree, $candidate['files']);
        runModulePublicationCommand(['git', 'diff', '--check'], $workingTree);

        $status = trim(runModulePublicationCommand(
            ['git', 'status', '--porcelain=v1', '--untracked-files=all'],
            $workingTree
        ));
        if ($status === '') {
            verifyModulePublicationRemote(
                $temporaryRoot,
                $verificationTree,
                $contract,
                $candidate,
                $options['expectedRemoteCommit'],
                $baseBranch
            );
            writeModulePublicationResult([
                'outcome' => 'unchanged',
                'mutationAttempted' => false,
                ...modulePublicationResultIdentity($contract, $openMeteoCompatibility),
                'repository' => $contract['repository']['name'],
                'branch' => $baseBranch,
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
        runModulePublicationCommand($addCommand, $workingTree);
        $stagedPaths = array_values(array_filter(explode(
            "\n",
            trim(runModulePublicationCommand(['git', 'diff', '--cached', '--name-only'], $workingTree))
        )));
        assertModulePublicationStagedPaths($stagedPaths, $candidate['files']);
        verifyModulePublicationTree($workingTree, $candidate['files'], true);

        runModulePublicationCommand(
            ['git', 'commit', '-m', $options['commitMessage']],
            $workingTree
        );
        $newCommit = trim(runModulePublicationCommand(['git', 'rev-parse', 'HEAD'], $workingTree));
        if ($beforeRemoteDriftCheck !== null) {
            $beforeRemoteDriftCheck();
        }
        $remoteBeforePush = modulePublicationRemoteCommit(
            $workingTree,
            $baseBranch
        );
        if (!hash_equals($options['expectedRemoteCommit'], $remoteBeforePush)) {
            throw new RuntimeException('Remote branch changed after the publication clone.');
        }
        if (
            $contract['publication']['mode'] === 'pull_request'
            && modulePublicationRemoteCommit($workingTree, $targetBranch, true) !== null
        ) {
            throw new RuntimeException('Publication topic branch already exists remotely.');
        }

        $mutationAttempted = true;
        runModulePublicationCommand([
            'git', 'push', 'origin',
            'HEAD:refs/heads/' . $targetBranch,
        ], $workingTree);
        verifyModulePublicationRemote(
            $temporaryRoot,
            $verificationTree,
            $contract,
            $candidate,
            $newCommit,
            $targetBranch
        );
        $result = [
            'outcome' => 'published',
            'mutationAttempted' => true,
            ...modulePublicationResultIdentity($contract, $openMeteoCompatibility),
            'repository' => $contract['repository']['name'],
            'branch' => $targetBranch,
            ...($openMeteoCompatibility ? [] : ['baseBranch' => $baseBranch]),
            'previousCommit' => $options['expectedRemoteCommit'],
            'commit' => $newCommit,
            'changedFileCount' => count($stagedPaths),
            'fileCount' => count($candidate['files']),
            'filesetSha256' => $candidate['filesetSha256'],
            'publicationSha256' => $candidate['publicationSha256'],
        ];
        if ($contract['publication']['mode'] === 'pull_request') {
            $result['pullRequestUrl'] = createModulePublicationPullRequest(
                $contract,
                $targetBranch,
                $candidate,
                $workingTree
            );
        }
        writeModulePublicationResult($result);
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
            removeModulePublicationTree($temporaryRoot);
        }
    }
}

/** @param array<string, string> $files */
function writeModulePublicationTree(string $target, array $files): void
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
    writeModulePublicationFiles($target, $files);
}

/** @param array<string, string> $files */
function writeModulePublicationFiles(string $root, array $files): void
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
function verifyModulePublicationTree(string $root, array $files, bool $allowGit = false): void
{
    $actual = modulePublicationTreeHashes($root, $allowGit);
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
function assertModulePublicationBaselinePathsAllowed(
    string $root,
    array $files,
    bool $allowGit = false
): void {
    $actual = array_keys(modulePublicationTreeHashes($root, $allowGit));
    foreach ($actual as $path) {
        if (!array_key_exists($path, $files)) {
            throw new RuntimeException(
                'Publication baseline contains a path outside the allowlist.'
            );
        }
    }
}

/**
 * @param list<string> $stagedPaths
 * @param array<string, string> $files
 */
function assertModulePublicationStagedPaths(array $stagedPaths, array $files): void
{
    foreach ($stagedPaths as $path) {
        if (!array_key_exists($path, $files)) {
            throw new RuntimeException('Staged publication contains an unclassified path.');
        }
    }
    if ($stagedPaths === []) {
        throw new RuntimeException('Publication status changed without a staged candidate file.');
    }
}

/** @return array<string, string> */
function modulePublicationTreeHashes(string $root, bool $allowGit): array
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

function assertModulePublicationGitBaseline(
    string $workingTree,
    string $expectedRemote,
    string $expectedBranch,
    string $expectedCommit
): void {
    $status = trim(runModulePublicationCommand(
        ['git', 'status', '--porcelain=v1', '--untracked-files=all'],
        $workingTree
    ));
    $remote = trim(runModulePublicationCommand(['git', 'remote', 'get-url', 'origin'], $workingTree));
    $branch = trim(runModulePublicationCommand(['git', 'branch', '--show-current'], $workingTree));
    $commit = trim(runModulePublicationCommand(['git', 'rev-parse', 'HEAD'], $workingTree));
    $remoteHead = runModulePublicationCommand(
        ['git', 'ls-remote', '--symref', 'origin', 'HEAD'],
        $workingTree
    );
    $expectedHead = 'ref: refs/heads/' . $expectedBranch . "\tHEAD\n"
        . $expectedCommit . "\tHEAD";
    if (
        $status !== '' || $remote !== $expectedRemote || $branch !== $expectedBranch
        || !hash_equals($expectedCommit, $commit) || trim($remoteHead) !== $expectedHead
    ) {
        throw new RuntimeException('Publication repository baseline differs.');
    }
    foreach (['user.name', 'user.email'] as $key) {
        if (trim(runModulePublicationCommand(['git', 'config', '--get', $key], $workingTree)) === '') {
            throw new RuntimeException('Git publication identity is incomplete.');
        }
    }
}

/**
 * @param array{
 *     repository: array{cloneUrl: string}
 * } $contract
 * @param array{files: array<string, string>} $candidate
 */
function verifyModulePublicationRemote(
    string $temporaryRoot,
    string $verificationTree,
    array $contract,
    array $candidate,
    string $expectedCommit,
    string $branch
): void {
    if (file_exists($verificationTree)) {
        throw new RuntimeException('Publication verification target already exists.');
    }
    runModulePublicationCommand([
        'git', 'clone', '--depth', '1', '--branch', $branch,
        '--single-branch', '--no-tags', $contract['repository']['cloneUrl'], $verificationTree,
    ], $temporaryRoot);
    $commit = trim(runModulePublicationCommand(['git', 'rev-parse', 'HEAD'], $verificationTree));
    if (!hash_equals($expectedCommit, $commit)) {
        throw new RuntimeException('Independent remote verification commit differs.');
    }
    verifyModulePublicationTree($verificationTree, $candidate['files'], true);
}

function modulePublicationRemoteCommit(
    string $workingTree,
    string $branch,
    bool $allowMissing = false
): ?string {
    $output = trim(runModulePublicationCommand(
        ['git', 'ls-remote', 'origin', 'refs/heads/' . $branch],
        $workingTree
    ));
    if ($allowMissing && $output === '') {
        return null;
    }
    if (preg_match('/^([a-f0-9]{40})\s+refs\/heads\/' . preg_quote($branch, '/') . '$/D', $output, $matches) !== 1) {
        throw new RuntimeException('Cannot resolve exact remote publication commit.');
    }

    return $matches[1];
}

/**
 * @param array<string, string> $files
 * @param array{forbiddenMarkers: list<string>} $policy
 */
function assertModulePublicationPrivacy(array $files, array $policy): void
{
    foreach ($files as $path => $contents) {
        foreach (array_merge(['/Users/', '\\Users\\'], $policy['forbiddenMarkers']) as $marker) {
            if (str_contains($contents, $marker)) {
                throw new RuntimeException('Publication contains a private marker in ' . $path . '.');
            }
        }
        if (preg_match('/(?:^|[^0-9])(?:10\.|192\.168\.|172\.(?:1[6-9]|2[0-9]|3[01])\.)[0-9]{1,3}\.[0-9]{1,3}(?:[^0-9]|$)/', $contents) === 1) {
            throw new RuntimeException('Publication contains a private network address in ' . $path . '.');
        }
    }
}

/**
 * @param array<string, string> $files
 * @param array{
 *     repositoryUrlPaths: list<string>,
 *     readmeTarget: string,
 *     licenseTarget: string,
 *     licenseMarker: string
 * } $policy
 */
function assertModulePublicationMetadata(array $files, string $publicUrl, array $policy): void
{
    foreach ($policy['repositoryUrlPaths'] as $path) {
        $metadata = json_decode($files[$path] ?? '', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($metadata) || ($metadata['url'] ?? null) !== $publicUrl) {
            throw new RuntimeException('Publication module URL differs in ' . $path . '.');
        }
    }
    if (
        !str_contains($files[$policy['readmeTarget']] ?? '', $publicUrl)
        || !str_contains(
            $files[$policy['licenseTarget']] ?? '',
            $policy['licenseMarker']
        )
    ) {
        throw new RuntimeException('Publication README or license identity differs.');
    }
}

/** @param array<string, string> $files */
function hashModulePublication(array $files, string $namespace): string
{
    ksort($files, SORT_STRING);
    $context = hash_init('sha256');
    hash_update($context, $namespace . "\0v1\0");
    foreach ($files as $path => $contents) {
        hash_update($context, strlen($path) . "\0" . $path . strlen($contents) . "\0" . $contents);
    }

    return hash_final($context);
}

/**
 * @param array<string, mixed> $value
 * @param list<string> $expected
 */
function assertModulePublicationFields(array $value, array $expected, string $kind): void
{
    $actual = array_keys($value);
    sort($actual, SORT_STRING);
    sort($expected, SORT_STRING);
    if ($actual !== $expected) {
        throw new RuntimeException('Publication ' . $kind . ' fields are invalid.');
    }
}

function modulePublicationIdentifier(mixed $value, string $kind): string
{
    if (!is_string($value) || preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/D', $value) !== 1) {
        throw new RuntimeException('Publication ' . $kind . ' is invalid.');
    }

    return $value;
}

/** @return list<string> */
function modulePublicationPathList(mixed $value, string $kind): array
{
    if (!is_array($value) || !array_is_list($value) || $value === []) {
        throw new RuntimeException('Publication ' . $kind . ' must be a non-empty list.');
    }
    $paths = [];
    $previous = null;
    foreach ($value as $path) {
        $path = publicationRelativePath($path, $kind);
        if ($previous !== null && strcmp($path, $previous) <= 0) {
            throw new RuntimeException('Publication ' . $kind . ' must be sorted and unique.');
        }
        $paths[] = $path;
        $previous = $path;
    }

    return $paths;
}

function assertModulePublicationBranch(string $branch, string $kind, bool $template = false): void
{
    $candidate = $template
        ? strtr($branch, ['{name}' => 'module', '{fileset}' => str_repeat('a', 12)])
        : $branch;
    if (
        $candidate === ''
        || strlen($candidate) > 128
        || str_contains($candidate, '..')
        || str_ends_with($candidate, '.')
        || preg_match('/^(?!\/)(?!.*\/$)[A-Za-z0-9][A-Za-z0-9._\/-]*$/D', $candidate) !== 1
    ) {
        throw new RuntimeException('Publication ' . $kind . ' is invalid.');
    }
    if ($template) {
        $unknownTemplate = preg_replace('/\{(?:name|fileset)\}/', '', $branch);
        if (is_string($unknownTemplate) && str_contains($unknownTemplate, '{')) {
            throw new RuntimeException('Publication topic branch template has an unknown token.');
        }
    }
}

function modulePublicationTopicBranch(string $template, string $name, string $filesetSha256): string
{
    $branch = strtr($template, [
        '{name}' => $name,
        '{fileset}' => substr($filesetSha256, 0, 12),
    ]);
    assertModulePublicationBranch($branch, 'topic branch');

    return $branch;
}

function assertModulePublicationProjectPath(
    string $projectRoot,
    string $relative,
    bool $directory
): void {
    $relative = publicationRelativePath($relative, 'project path');
    $current = rtrim($projectRoot, '/');
    foreach (explode('/', $relative) as $segment) {
        $current .= '/' . $segment;
        if (is_link($current)) {
            throw new RuntimeException('Publication project path contains a symbolic link.');
        }
    }
    if (($directory && !is_dir($current)) || (!$directory && !is_file($current))) {
        throw new RuntimeException('Publication project path is missing or has the wrong type.');
    }
}

/**
 * @param array{
 *     name: string,
 *     publication: array{
 *         pullRequest: array{repository: string, base: string, title: string, body: string}
 *     }
 * } $contract
 * @param array{filesetSha256: string, publicationSha256: string} $candidate
 */
function createModulePublicationPullRequest(
    array $contract,
    string $targetBranch,
    array $candidate,
    string $workingTree
): string {
    $pullRequest = $contract['publication']['pullRequest'];
    $tokens = [
        '{name}' => $contract['name'],
        '{filesetSha256}' => $candidate['filesetSha256'],
        '{publicationSha256}' => $candidate['publicationSha256'],
    ];
    $title = strtr($pullRequest['title'], $tokens);
    $body = strtr($pullRequest['body'], $tokens);
    if (strlen($title) > 200 || strlen($body) > 20 * 1024) {
        throw new RuntimeException('Rendered pull-request metadata exceeds its bound.');
    }
    $output = trim(runModulePublicationCommand([
        'gh', 'pr', 'create',
        '--repo', $pullRequest['repository'],
        '--base', $pullRequest['base'],
        '--head', $targetBranch,
        '--title', $title,
        '--body', $body,
    ], $workingTree));
    $pattern = '~^https://github\.com/'
        . preg_quote($pullRequest['repository'], '~')
        . '/pull/[1-9][0-9]*$~D';
    if (preg_match($pattern, $output) !== 1) {
        throw new RuntimeException('Pull-request creation result is invalid.');
    }

    return $output;
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

function readModulePublicationFile(string $path): string
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
function runModulePublicationCommand(array $command, string $workingDirectory): string
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

function newModulePublicationPath(string $prefix): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
}

function removeModulePublicationTree(string $root): void
{
    $temporaryRoot = preg_quote(
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR),
        '~'
    );
    $pattern = '~^' . $temporaryRoot
        . '/saef-(?:module-publication|open-meteo)-apply-[a-f0-9]{16}$~D';
    if (preg_match($pattern, $root) !== 1 || !is_dir($root)) {
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
function writeModulePublicationResult(array $result): void
{
    fwrite(STDOUT, json_encode(
        $result,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n");
}
