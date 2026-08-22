<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/publication/ModulePublication.php';

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
$temporaryRoot = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR
    . 'saef-module-publication-test-' . bin2hex(random_bytes(8));
$privateContractRoot = $projectRoot . '/private/module-publication-test-'
    . bin2hex(random_bytes(8));
$originalPath = getenv('PATH');
$originalGitConfig = getenv('GIT_CONFIG_GLOBAL');

try {
    modulePublicationTestMkdir($temporaryRoot);
    modulePublicationTestMkdir($privateContractRoot);
    modulePublicationTestConfigureEnvironment($temporaryRoot, $originalPath);

    $openContract = loadModulePublicationContract(
        $projectRoot,
        'deployments/symcon/open-meteo-publication.json'
    );
    $openCandidate = buildModulePublicationCandidate($projectRoot, $openContract);
    modulePublicationTestSame(44, count($openCandidate['files']), 'Open-Meteo inventory differs.');
    modulePublicationTestSame(
        '79a0d0e15f2c94ec3a6bfbee35a370ba38278ddd2977972c9351477528741e94',
        $openCandidate['publicationSha256'],
        'Open-Meteo publication compatibility hash differs.'
    );

    $mediaContract = loadModulePublicationContract(
        $projectRoot,
        'deployments/symcon/media-carousel-publication.json'
    );
    $mediaCandidate = buildModulePublicationCandidate($projectRoot, $mediaContract);
    modulePublicationTestSame(11, count($mediaCandidate['files']), 'MediaCarousel inventory differs.');
    modulePublicationTestSame(
        'e1abec6028aec935fb08ffb01d1774b5091caff7020b7dc1cb5f8c04a7c8ba87',
        $mediaCandidate['filesetSha256'],
        'MediaCarousel fileset differs.'
    );

    modulePublicationTestContractFailures($projectRoot, $privateContractRoot);
    modulePublicationTestCandidateFailures($mediaContract, $mediaCandidate, $temporaryRoot);
    modulePublicationTestApplyGates($mediaContract, $mediaCandidate);
    modulePublicationTestCleanupBoundaries($temporaryRoot);
    modulePublicationTestGitFlows($mediaContract, $mediaCandidate, $temporaryRoot);

    fwrite(STDOUT, "module-publication: ok\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'module-publication: failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (is_string($originalPath)) {
        putenv('PATH=' . $originalPath);
    }
    if (is_string($originalGitConfig)) {
        putenv('GIT_CONFIG_GLOBAL=' . $originalGitConfig);
    } else {
        putenv('GIT_CONFIG_GLOBAL');
    }
    modulePublicationTestRemoveTree($temporaryRoot);
    modulePublicationTestRemoveTree($privateContractRoot);
}

function modulePublicationTestContractFailures(string $projectRoot, string $privateRoot): void
{
    $source = $projectRoot . '/deployments/symcon/media-carousel-publication.json';
    $contract = json_decode(
        (string) file_get_contents($source),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($contract)) {
        throw new RuntimeException('MediaCarousel test contract is invalid.');
    }

    modulePublicationTestThrows(
        static fn (): array => loadModulePublicationContract(
            $projectRoot,
            'private/publication.local.json'
        ),
        'must be a public deployments/symcon contract'
    );

    modulePublicationTestInvalidContract(
        $projectRoot,
        $privateRoot,
        $contract + ['unknown' => true],
        'fields are invalid'
    );

    $wrongVersion = $contract;
    $wrongVersion['formatVersion'] = 2;
    modulePublicationTestInvalidContract(
        $projectRoot,
        $privateRoot,
        $wrongVersion,
        'version is unsupported'
    );

    $wrongRepository = $contract;
    $wrongRepository['repository']['publicUrl'] = 'https://github.com/example/other';
    modulePublicationTestInvalidContract(
        $projectRoot,
        $privateRoot,
        $wrongRepository,
        'public repository identity differs'
    );

    $localRepository = $contract;
    $localRepository['repository']['cloneUrl'] = 'file:///private/repository.git';
    modulePublicationTestInvalidContract(
        $projectRoot,
        $privateRoot,
        $localRepository,
        'clone repository identity differs'
    );

    $traversal = $contract;
    $traversal['generated']['manifest'] = '../outside.json';
    modulePublicationTestInvalidContract(
        $projectRoot,
        $privateRoot,
        $traversal,
        'is unsafe'
    );

    $unknownPublication = $contract;
    $unknownPublication['publication']['unknown'] = true;
    modulePublicationTestInvalidContract(
        $projectRoot,
        $privateRoot,
        $unknownPublication,
        'fields are invalid'
    );

    $directPublication = $contract;
    $directPublication['publication'] = [
        'mode' => 'direct_branch',
        'topicBranch' => null,
        'pullRequest' => null,
    ];
    modulePublicationTestInvalidContract(
        $projectRoot,
        $privateRoot,
        $directPublication,
        'restricted to the Open-Meteo compatibility contract'
    );
}

/** @param array<string, mixed> $contract */
function modulePublicationTestInvalidContract(
    string $projectRoot,
    string $privateRoot,
    array $contract,
    string $message
): void {
    $path = $privateRoot . '/contract-' . bin2hex(random_bytes(4)) . '.local.json';
    $contents = json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $contents . "\n") === false) {
        throw new RuntimeException('Cannot write invalid publication contract fixture.');
    }
    $relative = str_replace($projectRoot . '/', '', str_replace('\\', '/', $path));
    modulePublicationTestThrows(
        static fn (): array => loadModulePublicationContract($projectRoot, $relative, true),
        $message
    );
}

function modulePublicationTestCleanupBoundaries(string $temporaryRoot): void
{
    $generic = newModulePublicationPath('saef-module-publication-apply-');
    $legacy = newModulePublicationPath('saef-open-meteo-apply-');
    $unmanaged = $temporaryRoot . '/unmanaged-cleanup-boundary';
    modulePublicationTestMkdir($generic);
    modulePublicationTestMkdir($legacy);
    modulePublicationTestMkdir($unmanaged);

    removeModulePublicationTree($generic);
    removeModulePublicationTree($legacy);
    removeModulePublicationTree($unmanaged);
    if (is_dir($generic) || is_dir($legacy) || !is_dir($unmanaged)) {
        throw new RuntimeException('Publication cleanup boundary differs.');
    }
}

/**
 * @param array<string, mixed> $contract
 * @param array{files: array<string, string>, filesetSha256: string, publicationSha256: string} $candidate
 */
function modulePublicationTestCandidateFailures(array $contract, array $candidate, string $temporaryRoot): void
{
    $missingInventory = $contract;
    array_pop($missingInventory['inventory']);
    modulePublicationTestThrows(
        static fn (): array => buildModulePublicationCandidate(dirname(__DIR__, 2), $missingInventory),
        'inventory differs'
    );

    $tree = $temporaryRoot . '/candidate-tree';
    writeModulePublicationTree($tree, $candidate['files']);
    verifyModulePublicationTree($tree, $candidate['files']);
    if (file_put_contents($tree . '/README.md', 'changed') === false) {
        throw new RuntimeException('Cannot alter candidate-tree fixture.');
    }
    modulePublicationTestThrows(
        static fn (): null => verifyModulePublicationTree($tree, $candidate['files']),
        'missing, changed or additional'
    );
    if (file_put_contents($tree . '/unexpected.txt', 'extra') === false) {
        throw new RuntimeException('Cannot add unclassified fixture.');
    }
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationBaselinePathsAllowed($tree, $candidate['files']),
        'outside the allowlist'
    );
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationStagedPaths(
            ['README.md', 'unexpected.txt'],
            $candidate['files']
        ),
        'unclassified path'
    );

    $deterministicA = $temporaryRoot . '/deterministic-a';
    $deterministicB = $temporaryRoot . '/deterministic-b';
    writeModulePublicationTree($deterministicA, $candidate['files']);
    writeModulePublicationTree($deterministicB, $candidate['files']);
    modulePublicationTestSame(
        modulePublicationTreeHashes($deterministicA, false),
        modulePublicationTreeHashes($deterministicB, false),
        'Prepared publication trees are not deterministic.'
    );

    $linkTree = $temporaryRoot . '/link-tree';
    modulePublicationTestMkdir($linkTree);
    if (!symlink($temporaryRoot, $linkTree . '/link')) {
        throw new RuntimeException('Cannot create publication symlink fixture.');
    }
    modulePublicationTestThrows(
        static fn (): array => modulePublicationTreeHashes($linkTree, false),
        'symbolic link'
    );

    $privateFiles = ['README.md' => 'path /Users/example is private'];
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationPrivacy(
            $privateFiles,
            ['forbiddenMarkers' => []]
        ),
        'private marker'
    );
    $configuredMarkerFiles = ['README.md' => 'contains PRIVATE_FIXTURE_MARKER'];
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationPrivacy(
            $configuredMarkerFiles,
            ['forbiddenMarkers' => ['PRIVATE_FIXTURE_MARKER']]
        ),
        'private marker'
    );
    $networkFiles = ['README.md' => 'endpoint 192.168.1.1'];
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationPrivacy(
            $networkFiles,
            ['forbiddenMarkers' => []]
        ),
        'private network address'
    );
}

/**
 * @param array<string, mixed> $contract
 * @param array{filesetSha256: string, publicationSha256: string} $candidate
 */
function modulePublicationTestApplyGates(array $contract, array $candidate): void
{
    $valid = modulePublicationTestOptions($contract, $candidate, str_repeat('a', 40));
    assertModulePublicationApplyGate($valid, $contract, $candidate);

    $wrongFileset = $valid;
    $wrongFileset['expectedFilesetSha256'] = str_repeat('0', 64);
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationApplyGate($wrongFileset, $contract, $candidate),
        'expected fileset hash'
    );

    $wrongPublication = $valid;
    $wrongPublication['expectedPublicationSha256'] = str_repeat('0', 64);
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationApplyGate($wrongPublication, $contract, $candidate),
        'expected publication hash'
    );

    $wrongRemote = $valid;
    $wrongRemote['expectedRemoteCommit'] = 'short';
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationApplyGate($wrongRemote, $contract, $candidate),
        'full SHA-1'
    );

    $wrongConfirmation = $valid;
    $wrongConfirmation['confirmation'] = 'wrong-confirmation';
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationApplyGate($wrongConfirmation, $contract, $candidate),
        'confirmation differs'
    );

    $wrongMessage = $valid;
    $wrongMessage['commitMessage'] = "invalid\nmessage";
    modulePublicationTestThrows(
        static fn (): null => assertModulePublicationApplyGate($wrongMessage, $contract, $candidate),
        'commit message is invalid'
    );
}

/**
 * @param array<string, mixed> $contract
 * @param array{files: array<string, string>, filesetSha256: string, publicationSha256: string} $candidate
 */
function modulePublicationTestGitFlows(array $contract, array $candidate, string $temporaryRoot): void
{
    $unchanged = modulePublicationTestRemote($temporaryRoot . '/unchanged', $candidate, false);
    $unchangedContract = modulePublicationTestLocalContract($contract, $unchanged['remote']);
    applyModulePublication(
        $unchangedContract,
        $candidate,
        modulePublicationTestOptions($contract, $candidate, $unchanged['commit'])
    );
    modulePublicationTestSame(
        null,
        modulePublicationRemoteCommit($unchanged['seed'], modulePublicationTestBranch($contract, $candidate), true),
        'Unchanged publication created a topic branch.'
    );

    $wrongRemote = modulePublicationTestRemote($temporaryRoot . '/wrong-remote', $candidate, true);
    $wrongRemoteContract = modulePublicationTestLocalContract($contract, $wrongRemote['remote']);
    modulePublicationTestThrows(
        static fn (): null => applyModulePublication(
            $wrongRemoteContract,
            $candidate,
            modulePublicationTestOptions($contract, $candidate, str_repeat('0', 40))
        ),
        'repository baseline differs'
    );

    $success = modulePublicationTestRemote($temporaryRoot . '/success', $candidate, true);
    $successContract = modulePublicationTestLocalContract($contract, $success['remote']);
    putenv('FAKE_GH_MODE=success');
    putenv('FAKE_GH_LOG=' . $temporaryRoot . '/gh-success.log');
    applyModulePublication(
        $successContract,
        $candidate,
        modulePublicationTestOptions($contract, $candidate, $success['commit'])
    );
    $topicBranch = modulePublicationTestBranch($contract, $candidate);
    $publishedCommit = modulePublicationRemoteCommit($success['seed'], $topicBranch);
    if (!is_string($publishedCommit) || !is_file($temporaryRoot . '/gh-success.log')) {
        throw new RuntimeException('Successful PR publication evidence is incomplete.');
    }
    $ghArguments = (string) file_get_contents($temporaryRoot . '/gh-success.log');
    modulePublicationTestContains($ghArguments, 'pr create');
    if (str_contains($ghArguments, '--draft')) {
        throw new RuntimeException('Pull-request publication unexpectedly created a draft.');
    }

    $drift = modulePublicationTestRemote($temporaryRoot . '/drift', $candidate, true);
    $driftContract = modulePublicationTestLocalContract($contract, $drift['remote']);
    modulePublicationTestThrows(
        static function () use ($driftContract, $candidate, $contract, $drift): void {
            applyModulePublication(
                $driftContract,
                $candidate,
                modulePublicationTestOptions($contract, $candidate, $drift['commit']),
                static fn (): string => modulePublicationTestAdvanceRemote(
                    $drift['root'],
                    $drift['remote']
                )
            );
        },
        'Remote branch changed'
    );

    $pushFailure = modulePublicationTestRemote($temporaryRoot . '/push-failure', $candidate, true);
    $hook = $pushFailure['remote'] . '/hooks/pre-receive';
    if (file_put_contents($hook, "#!/bin/sh\nexit 1\n") === false || !chmod($hook, 0755)) {
        throw new RuntimeException('Cannot install push-failure fixture.');
    }
    $pushFailureContract = modulePublicationTestLocalContract($contract, $pushFailure['remote']);
    $pushException = modulePublicationTestCaptureException(static function () use (
        $pushFailureContract,
        $candidate,
        $contract,
        $pushFailure
    ): void {
        applyModulePublication(
            $pushFailureContract,
            $candidate,
            modulePublicationTestOptions($contract, $candidate, $pushFailure['commit'])
        );
    });
    modulePublicationTestContains($pushException->getMessage(), 'preserve workspace evidence');
    modulePublicationTestRemovePreservedWorkspace($pushException);

    $prFailure = modulePublicationTestRemote($temporaryRoot . '/pr-failure', $candidate, true);
    $prFailureContract = modulePublicationTestLocalContract($contract, $prFailure['remote']);
    putenv('FAKE_GH_MODE=fail');
    $prException = modulePublicationTestCaptureException(static function () use (
        $prFailureContract,
        $candidate,
        $contract,
        $prFailure
    ): void {
        applyModulePublication(
            $prFailureContract,
            $candidate,
            modulePublicationTestOptions($contract, $candidate, $prFailure['commit'])
        );
    });
    modulePublicationTestContains($prException->getMessage(), 'preserve workspace evidence');
    if (modulePublicationRemoteCommit($prFailure['seed'], $topicBranch, true) === null) {
        throw new RuntimeException('PR failure did not preserve the pushed topic branch.');
    }
    modulePublicationTestRemovePreservedWorkspace($prException);
}

/**
 * @param array<string, mixed> $contract
 * @param array{filesetSha256: string, publicationSha256: string} $candidate
 * @return array<string, string>
 */
function modulePublicationTestOptions(array $contract, array $candidate, string $commit): array
{
    return [
        'mode' => 'apply',
        'contractPath' => 'unused',
        'prepareTarget' => '',
        'expectedFilesetSha256' => $candidate['filesetSha256'],
        'expectedPublicationSha256' => $candidate['publicationSha256'],
        'expectedRemoteCommit' => $commit,
        'confirmation' => $contract['repository']['confirmation'],
        'commitMessage' => 'feat: publish module fixture',
    ];
}

/**
 * @param array<string, mixed> $contract
 * @return array<string, mixed>
 */
function modulePublicationTestLocalContract(array $contract, string $remote): array
{
    $contract['repository']['cloneUrl'] = 'file://' . $remote;

    return $contract;
}

/**
 * @param array{files: array<string, string>} $candidate
 * @return array{root: string, remote: string, seed: string, commit: string}
 */
function modulePublicationTestRemote(string $root, array $candidate, bool $changeReadme): array
{
    modulePublicationTestMkdir($root);
    $remote = $root . '/remote.git';
    $seed = $root . '/seed';
    runModulePublicationCommand(['git', 'init', '--bare', $remote], $root);
    runModulePublicationCommand(['git', 'init', '-b', 'main', $seed], $root);
    writeModulePublicationFiles($seed, $candidate['files']);
    if ($changeReadme && file_put_contents($seed . '/README.md', "previous\n") === false) {
        throw new RuntimeException('Cannot change remote README fixture.');
    }
    runModulePublicationCommand(['git', 'add', '--all'], $seed);
    runModulePublicationCommand(['git', 'commit', '-m', 'chore: seed fixture'], $seed);
    runModulePublicationCommand(['git', 'remote', 'add', 'origin', 'file://' . $remote], $seed);
    runModulePublicationCommand(['git', 'push', '-u', 'origin', 'main'], $seed);
    runModulePublicationCommand(
        ['git', 'symbolic-ref', 'HEAD', 'refs/heads/main'],
        $remote
    );

    return [
        'root' => $root,
        'remote' => $remote,
        'seed' => $seed,
        'commit' => trim(runModulePublicationCommand(['git', 'rev-parse', 'HEAD'], $seed)),
    ];
}

function modulePublicationTestAdvanceRemote(string $root, string $remote): string
{
    $clone = $root . '/drift-writer';
    runModulePublicationCommand(['git', 'clone', 'file://' . $remote, $clone], $root);
    if (file_put_contents($clone . '/README.md', "drift\n") === false) {
        throw new RuntimeException('Cannot write remote-drift fixture.');
    }
    runModulePublicationCommand(['git', 'add', 'README.md'], $clone);
    runModulePublicationCommand(['git', 'commit', '-m', 'chore: drift fixture'], $clone);
    runModulePublicationCommand(['git', 'push', 'origin', 'main'], $clone);

    return trim(runModulePublicationCommand(['git', 'rev-parse', 'HEAD'], $clone));
}

/**
 * @param array<string, mixed> $contract
 * @param array{filesetSha256: string} $candidate
 */
function modulePublicationTestBranch(array $contract, array $candidate): string
{
    return modulePublicationTopicBranch(
        $contract['publication']['topicBranch'],
        $contract['name'],
        $candidate['filesetSha256']
    );
}

function modulePublicationTestConfigureEnvironment(string $root, string|false $originalPath): void
{
    $gitConfig = $root . '/gitconfig';
    $config = "[user]\n\tname = SAEF Test\n\temail = saef-test@example.invalid\n";
    if (file_put_contents($gitConfig, $config) === false) {
        throw new RuntimeException('Cannot write publication Git fixture configuration.');
    }
    putenv('GIT_CONFIG_GLOBAL=' . $gitConfig);

    $bin = $root . '/bin';
    modulePublicationTestMkdir($bin);
    $gh = <<<'SH'
#!/bin/sh
if [ -n "$FAKE_GH_LOG" ]; then
    printf '%s\n' "$*" > "$FAKE_GH_LOG"
fi
if [ "$FAKE_GH_MODE" = "fail" ]; then
    echo "simulated PR failure" >&2
    exit 1
fi
echo "https://github.com/doctee/saef-media-carousel/pull/42"
SH;
    if (file_put_contents($bin . '/gh', $gh . "\n") === false || !chmod($bin . '/gh', 0755)) {
        throw new RuntimeException('Cannot write fake gh fixture.');
    }
    putenv('PATH=' . $bin . PATH_SEPARATOR . (is_string($originalPath) ? $originalPath : ''));
}

function modulePublicationTestRemovePreservedWorkspace(Throwable $exception): void
{
    if (
        preg_match(
            '~preserve workspace evidence at ([^ ]+) and verify~',
            $exception->getMessage(),
            $matches
        ) !== 1
    ) {
        throw new RuntimeException('Preserved publication workspace path is missing.');
    }
    if (!is_dir($matches[1])) {
        throw new RuntimeException('Preserved publication workspace does not exist.');
    }
    removeModulePublicationTree($matches[1]);
}

function modulePublicationTestThrows(callable $operation, string $message): void
{
    $exception = modulePublicationTestCaptureException($operation);
    modulePublicationTestContains($exception->getMessage(), $message);
}

function modulePublicationTestCaptureException(callable $operation): Throwable
{
    try {
        $operation();
    } catch (Throwable $exception) {
        return $exception;
    }
    throw new RuntimeException('Expected publication failure did not occur.');
}

function modulePublicationTestContains(string $actual, string $expected): void
{
    if (!str_contains($actual, $expected)) {
        throw new RuntimeException(
            'Expected failure text "' . $expected . '", got "' . $actual . '".'
        );
    }
}

function modulePublicationTestSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function modulePublicationTestMkdir(string $path): void
{
    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create publication test directory.');
    }
}

function modulePublicationTestRemoveTree(string $root): void
{
    if (!is_dir($root)) {
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
