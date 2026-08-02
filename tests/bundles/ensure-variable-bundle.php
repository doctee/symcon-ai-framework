<?php

declare(strict_types=1);

const EXPECTED_BUNDLE_EXPORTS = [
    'SAEF_EnsureVariable',
    'SAEF_ValidateIdent',
    'SAEF_ValidateModuleGuid',
    'SAEF_ValidateMutableObject',
    'SAEF_ValidateObjectName',
    'SAEF_ValidateParentObject',
    'SAEF_ValidateScriptType',
    'SAEF_ValidateVariableType',
];

if (isset($argv[1]) && in_array($argv[1], ['--function-conflict', '--constant-conflict'], true)) {
    runConflictProbe($argv[1], $argv[2] ?? '');
    exit(0);
}

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
$manifest = $projectRoot . '/bundles/symcon/ensure-variable.bundle.json';
$builder = $projectRoot . '/tools/build-symcon-bundle.php';
$temporaryRoots = [];

try {
    $temporaryRoots[] = createTemporaryRoot('saef-bundle-a-');
    $temporaryRoots[] = createTemporaryRoot('saef-bundle-b-');

    foreach ($temporaryRoots as $temporaryRoot) {
        runCommand([
            PHP_BINARY,
            $builder,
            '--output-root=' . $temporaryRoot,
            $manifest,
        ]);
    }

    $relativeOutputs = [
        'dist/symcon/saef-ensure-variable.php',
        'dist/symcon/saef-ensure-variable.php.sha256',
        'dist/symcon/saef-ensure-variable.sources.json',
    ];

    foreach ($relativeOutputs as $relativeOutput) {
        $first = readRequiredFile($temporaryRoots[0] . '/' . $relativeOutput);
        $second = readRequiredFile($temporaryRoots[1] . '/' . $relativeOutput);
        $tracked = readRequiredFile($projectRoot . '/' . $relativeOutput);

        assertSameValue($first, $second, 'Independent builds differ: ' . $relativeOutput);
        assertSameValue($tracked, $first, 'Tracked generated artifact is stale: ' . $relativeOutput);
    }

    $artifactPath = $temporaryRoots[0] . '/dist/symcon/saef-ensure-variable.php';
    $artifact = readRequiredFile($artifactPath);
    verifyArtifactStructure($artifact);
    verifySidecars($temporaryRoots[0], $artifact);
    verifyConflictFailures($artifactPath);
    verifyCanonicalSourceAliasesAreRejected($builder, $temporaryRoots);
    verifyGuardSideEffectsAreRejected($builder, $temporaryRoots);

    require_once __DIR__ . '/FakeSymconRuntime.php';
    require_once $artifactPath;

    verifyCreateAndIdempotency();
    verifyExistingVariableReconciliation();
    verifyOptionalMetadata();
    verifyValidationFailures();
    verifyExistingObjectConflicts();
    verifySuppressedMissingIdentWarning();

    fwrite(STDOUT, "EnsureVariable bundle tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Bundle test failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeOwnedTree($temporaryRoot);
    }
}

function verifyArtifactStructure(string $artifact): void
{
    $tokens = token_get_all($artifact, TOKEN_PARSE);
    $exports = [];

    foreach ($tokens as $index => $token) {
        if (!is_array($token)) {
            continue;
        }

        if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE, T_DIR], true)) {
            throw new RuntimeException('Generated artifact contains an unresolved file dependency.');
        }

        if ($token[0] !== T_FUNCTION) {
            continue;
        }

        for ($next = $index + 1; $next < count($tokens); $next++) {
            $candidate = $tokens[$next];

            if (is_array($candidate) && $candidate[0] === T_STRING) {
                $exports[] = $candidate[1];
                break;
            }

            if ($candidate === '(') {
                break;
            }
        }
    }

    sort($exports, SORT_STRING);
    assertSameValue(EXPECTED_BUNDLE_EXPORTS, $exports, 'Generated export set differs from the manifest contract.');
    assertFalseValue(str_contains($artifact, '/Users/'), 'Artifact contains an absolute user path.');
}

function verifySidecars(string $temporaryRoot, string $artifact): void
{
    $artifactHash = hash('sha256', $artifact);
    $checksum = readRequiredFile(
        $temporaryRoot . '/dist/symcon/saef-ensure-variable.php.sha256'
    );
    assertSameValue(
        $artifactHash . "  saef-ensure-variable.php\n",
        $checksum,
        'Artifact checksum sidecar does not match.'
    );

    $sourceMapContents = readRequiredFile(
        $temporaryRoot . '/dist/symcon/saef-ensure-variable.sources.json'
    );
    $sourceMap = json_decode($sourceMapContents, true, 512, JSON_THROW_ON_ERROR);

    assertSameValue(
        $artifactHash,
        $sourceMap['artifact']['sha256'] ?? null,
        'Source map artifact hash does not match.'
    );
    assertSameValue(
        [
            'helpers/common/Validation.php',
            'helpers/object/EnsureVariable.php',
        ],
        array_column($sourceMap['orderedSources'] ?? [], 'path'),
        'Source map closure or dependency order differs.'
    );

    $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
    $sourceInputContext = hash_init('sha256');
    hash_update(
        $sourceInputContext,
        "SAEF-SYMCON-BUNDLE\0" . ($sourceMap['builderVersion'] ?? '') . "\0"
    );
    $manifest = json_decode(
        readRequiredFile($projectRoot . '/bundles/symcon/ensure-variable.bundle.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $canonicalManifest = json_encode(
        sortBundleTestJsonValue($manifest),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    hash_update(
        $sourceInputContext,
        strlen($canonicalManifest) . "\0" . $canonicalManifest
    );

    foreach ($sourceMap['orderedSources'] ?? [] as $sourceEntry) {
        $sourcePath = $sourceEntry['path'] ?? null;
        if (!is_string($sourcePath)) {
            throw new RuntimeException('Source map contains an invalid canonical path.');
        }
        $source = readRequiredFile($projectRoot . '/' . $sourcePath);
        assertSameValue(
            hash('sha256', $source),
            $sourceEntry['sha256'] ?? null,
            'Canonical source hash differs: ' . $sourcePath
        );
        hash_update($sourceInputContext, strlen($sourcePath) . "\0" . $sourcePath);
        hash_update($sourceInputContext, strlen($source) . "\0" . $source);
    }

    $sourceInputHash = hash_final($sourceInputContext);
    assertSameValue(
        $sourceInputHash,
        $sourceMap['sourceInputHash'] ?? null,
        'Independently reconstructed source-input hash differs.'
    );
    assertSameValue(
        true,
        str_contains($artifact, 'Source input SHA-256: ' . $sourceInputHash),
        'Artifact header does not contain the verified source-input hash.'
    );
    assertFalseValue(str_contains($sourceMapContents, '/Users/'), 'Source map contains an absolute user path.');
}

function sortBundleTestJsonValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
    }

    foreach ($value as $key => $child) {
        $value[$key] = sortBundleTestJsonValue($child);
    }

    return $value;
}

function verifyConflictFailures(string $artifactPath): void
{
    runCommand([PHP_BINARY, __FILE__, '--function-conflict', $artifactPath]);
    runCommand([PHP_BINARY, __FILE__, '--constant-conflict', $artifactPath]);
}

/** @param list<string> $temporaryRoots */
function verifyCanonicalSourceAliasesAreRejected(string $builder, array &$temporaryRoots): void
{
    if (PHP_OS_FAMILY === 'Windows') {
        return;
    }

    $fixtureRoot = createTemporaryRoot('saef-bundle-canonical-');
    $temporaryRoots[] = $fixtureRoot;

    foreach (['tools', 'helpers/test', 'bundles/symcon'] as $directory) {
        if (!mkdir($fixtureRoot . '/' . $directory, 0700, true)) {
            throw new RuntimeException('Cannot create canonical bundle fixture directory.');
        }
    }

    if (!copy($builder, $fixtureRoot . '/tools/build-symcon-bundle.php')) {
        throw new RuntimeException('Cannot copy bundle builder into canonical-path fixture.');
    }

    writeRequiredFile(
        $fixtureRoot . '/helpers/test/Real.php',
        <<<'PHP'
<?php
declare(strict_types=1);

if (!defined('SAEF_HELPER_CANONICAL_FIXTURE')) {
    define('SAEF_HELPER_CANONICAL_FIXTURE', true);

    function SAEF_CanonicalFixture(): void
    {
    }
}
PHP
        . "\n"
    );

    if (!symlink('Real.php', $fixtureRoot . '/helpers/test/Alias.php')) {
        throw new RuntimeException('Cannot create canonical bundle fixture symlink.');
    }

    writeRequiredFile(
        $fixtureRoot . '/bundles/symcon/canonical.bundle.json',
        <<<'JSON'
{
    "formatVersion": 1,
    "name": "canonical-fixture",
    "entries": [
        "helpers/test/Alias.php"
    ],
    "phpMinimum": "8.2",
    "output": "dist/symcon/canonical-fixture.php",
    "exports": [
        "SAEF_CanonicalFixture"
    ]
}
JSON
        . "\n"
    );

    runCommandExpectFailure(
        [
            PHP_BINARY,
            $fixtureRoot . '/tools/build-symcon-bundle.php',
            '--output-root=' . $fixtureRoot . '/output',
            $fixtureRoot . '/bundles/symcon/canonical.bundle.json',
        ],
        'Helper source must resolve to its canonical path inside the project root'
    );
}

/** @param list<string> $temporaryRoots */
function verifyGuardSideEffectsAreRejected(string $builder, array &$temporaryRoots): void
{
    $fixtureRoot = createTemporaryRoot('saef-bundle-side-effect-');
    $temporaryRoots[] = $fixtureRoot;

    foreach (['tools', 'helpers/test', 'bundles/symcon'] as $directory) {
        if (!mkdir($fixtureRoot . '/' . $directory, 0700, true)) {
            throw new RuntimeException('Cannot create bundle side-effect fixture directory.');
        }
    }

    if (!copy($builder, $fixtureRoot . '/tools/build-symcon-bundle.php')) {
        throw new RuntimeException('Cannot copy bundle builder into side-effect fixture.');
    }

    writeRequiredFile(
        $fixtureRoot . '/helpers/test/SideEffect.php',
        <<<'PHP'
<?php

declare(strict_types=1);

if (!defined('SAEF_HELPER_SIDE_EFFECT_TEST')) {
    define('SAEF_HELPER_SIDE_EFFECT_TEST', true);
    $unexpectedSideEffect = true;

    function SAEF_SideEffectTest(): void
    {
    }
}
PHP
        . "\n"
    );
    writeRequiredFile(
        $fixtureRoot . '/bundles/symcon/side-effect.bundle.json',
        <<<'JSON'
{
    "formatVersion": 1,
    "name": "side-effect-test",
    "entries": [
        "helpers/test/SideEffect.php"
    ],
    "phpMinimum": "8.2",
    "output": "dist/symcon/side-effect-test.php",
    "exports": [
        "SAEF_SideEffectTest"
    ]
}
JSON
        . "\n"
    );

    runCommandExpectFailure(
        [
            PHP_BINARY,
            $fixtureRoot . '/tools/build-symcon-bundle.php',
            $fixtureRoot . '/bundles/symcon/side-effect.bundle.json',
        ],
        'Guard blocks may contain only their guard definition and named functions'
    );
}

function runConflictProbe(string $mode, string $artifactPath): void
{
    if (!is_file($artifactPath)) {
        throw new RuntimeException('Conflict probe artifact does not exist.');
    }

    if ($mode === '--function-conflict') {
        function SAEF_EnsureVariable(): int
        {
            return -1;
        }
    } else {
        define('SAEF_HELPER_VALIDATION', true);
    }

    try {
        require $artifactPath;
    } catch (RuntimeException $exception) {
        if (str_starts_with($exception->getMessage(), 'SAEF bundle namespace conflict:')) {
            return;
        }

        throw $exception;
    }

    throw new RuntimeException('Generated bundle did not reject a pre-existing SAEF namespace conflict.');
}

function verifyCreateAndIdempotency(): void
{
    FakeSymconRuntime::reset();
    $parentID = FakeSymconRuntime::createParent();
    $variableID = SAEF_EnsureVariable($parentID, 'STATE', 'State', 1);

    assertSameValue(1, FakeSymconRuntime::variableCount(), 'Variable was not created exactly once.');
    assertSameValue(2, FakeSymconRuntime::getObject($variableID)['ObjectType'], 'Created object is not a variable.');
    assertSameValue(1, FakeSymconRuntime::getVariable($variableID)['VariableType'], 'Created variable type differs.');

    $secondID = SAEF_EnsureVariable($parentID, 'STATE', 'State', 1);
    assertSameValue($variableID, $secondID, 'Second ensure call did not reuse the variable.');
    assertSameValue(1, FakeSymconRuntime::variableCount(), 'Second ensure call created a duplicate.');
}

function verifyExistingVariableReconciliation(): void
{
    FakeSymconRuntime::reset();
    $parentID = FakeSymconRuntime::createParent();
    $variableID = FakeSymconRuntime::createExistingVariable(
        $parentID,
        'PRESERVED',
        'Old Name',
        3,
        'user value'
    );

    $resultID = SAEF_EnsureVariable($parentID, 'PRESERVED', 'New Name', 3);
    assertSameValue($variableID, $resultID, 'Existing compatible variable identity changed.');
    assertSameValue('user value', FakeSymconRuntime::getValue($variableID), 'Existing value was not preserved.');
    assertSameValue(
        'New Name',
        FakeSymconRuntime::getObject($variableID)['ObjectName'],
        'Supported name metadata was not reconciled.'
    );
}

function verifyOptionalMetadata(): void
{
    FakeSymconRuntime::reset();
    FakeSymconRuntime::addProfile('SAEF.Test.Profile');
    $actionID = FakeSymconRuntime::createScript();
    $parentID = FakeSymconRuntime::createParent();
    $variableID = SAEF_EnsureVariable(
        $parentID,
        'COMMAND',
        'Command',
        1,
        'SAEF.Test.Profile',
        $actionID,
        42,
        'Power'
    );

    $object = FakeSymconRuntime::getObject($variableID);
    $variable = FakeSymconRuntime::getVariable($variableID);
    assertSameValue(42, $object['ObjectPosition'], 'Position was not assigned.');
    assertSameValue('Power', $object['ObjectIcon'], 'Icon was not assigned.');
    assertSameValue('SAEF.Test.Profile', $variable['VariableCustomProfile'], 'Profile was not assigned.');
    assertSameValue($actionID, $variable['VariableCustomAction'], 'Action was not assigned.');
}

function verifyValidationFailures(): void
{
    FakeSymconRuntime::reset();
    $parentID = FakeSymconRuntime::createParent();

    assertThrows(
        InvalidArgumentException::class,
        static fn (): int => SAEF_EnsureVariable(999999, 'STATE', 'State', 1),
        'Invalid parent was accepted.'
    );
    assertThrows(
        InvalidArgumentException::class,
        static fn (): int => SAEF_EnsureVariable($parentID, 'invalid-ident', 'State', 1),
        'Invalid Ident was accepted.'
    );
    assertThrows(
        InvalidArgumentException::class,
        static fn (): int => SAEF_EnsureVariable($parentID, 'STATE', '', 1),
        'Empty name was accepted.'
    );
    assertThrows(
        InvalidArgumentException::class,
        static fn (): int => SAEF_EnsureVariable($parentID, 'STATE', 'State', 4),
        'Invalid variable type was accepted.'
    );
    assertThrows(
        RuntimeException::class,
        static fn (): int => SAEF_EnsureVariable($parentID, 'STATE', 'State', 1, 'Missing.Profile'),
        'Missing variable profile was accepted.'
    );
    assertThrows(
        RuntimeException::class,
        static fn (): int => SAEF_EnsureVariable($parentID, 'STATE', 'State', 1, '', 999999),
        'Missing action script was accepted.'
    );
    assertSameValue(0, FakeSymconRuntime::variableCount(), 'Validation failure caused a variable side effect.');
}

function verifyExistingObjectConflicts(): void
{
    FakeSymconRuntime::reset();
    $parentID = FakeSymconRuntime::createParent();
    FakeSymconRuntime::createNonVariable($parentID, 'COLLISION', 'Collision');
    assertThrows(
        RuntimeException::class,
        static fn (): int => SAEF_EnsureVariable($parentID, 'COLLISION', 'Collision', 1),
        'Existing non-variable collision was accepted.'
    );

    FakeSymconRuntime::createExistingVariable($parentID, 'WRONG_TYPE', 'Wrong Type', 0, false);
    assertThrows(
        RuntimeException::class,
        static fn (): int => SAEF_EnsureVariable($parentID, 'WRONG_TYPE', 'Wrong Type', 1),
        'Existing variable with wrong type was accepted.'
    );
}

function verifySuppressedMissingIdentWarning(): void
{
    FakeSymconRuntime::reset();
    $parentID = FakeSymconRuntime::createParent();
    $events = [];

    set_error_handler(
        static function (int $severity, string $message) use (&$events): bool {
            $events[] = [
                'severity' => $severity,
                'message' => $message,
                'reportable' => (error_reporting() & $severity) !== 0,
            ];

            return true;
        }
    );

    try {
        SAEF_EnsureVariable($parentID, 'MISSING', 'Missing', 0);
    } finally {
        restore_error_handler();
    }

    assertSameValue(1, count($events), 'Missing Ident lookup did not produce exactly one fake warning event.');
    assertSameValue(E_USER_WARNING, $events[0]['severity'], 'Unexpected missing Ident warning severity.');
    assertSameValue(false, $events[0]['reportable'], 'Narrow missing Ident warning was not suppressed.');
}

/** @param list<string> $command */
function runCommand(array $command): void
{
    $pipes = [];
    $process = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start bundle builder process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($status !== 0) {
        throw new RuntimeException('Bundle builder process failed: ' . trim((string)$stderr . (string)$stdout));
    }
}

/** @param list<string> $command */
function runCommandExpectFailure(array $command, string $expectedMessage): void
{
    $pipes = [];
    $process = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start expected-failure bundle builder process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $output = (string)$stderr . (string)$stdout;

    if ($status === 0 || !str_contains($output, $expectedMessage)) {
        throw new RuntimeException(
            'Bundle builder did not reject the fixture as expected: ' . trim($output)
        );
    }
}

function createTemporaryRoot(string $prefix): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));

    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Cannot create temporary bundle test directory.');
    }

    return $path;
}

function readRequiredFile(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Cannot read required test file: ' . $path);
    }

    return $contents;
}

function writeRequiredFile(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) !== strlen($contents)) {
        throw new RuntimeException('Cannot write required test fixture: ' . $path);
    }
}

function removeOwnedTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

function assertThrows(string $expectedClass, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException(
            $message . ' Unexpected exception: ' . $exception::class . ' (' . $exception->getMessage() . ')',
            0,
            $exception
        );
    }

    throw new RuntimeException($message);
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function assertFalseValue(bool $actual, string $message): void
{
    assertSameValue(false, $actual, $message);
}
