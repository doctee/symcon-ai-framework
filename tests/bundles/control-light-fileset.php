<?php
declare(strict_types=1);

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
$builder = $projectRoot . '/tools/build-symcon-fileset.php';
$manifest = $projectRoot . '/deployments/symcon/control-light.fileset.json';
$relativeRoot = 'dist/symcon/saef-control-light';
$temporaryRoots = [];

try {
    $temporaryRoots = [
        createControlLightFilesetTemporaryRoot('control-light-fileset-a-'),
        createControlLightFilesetTemporaryRoot('control-light-fileset-b-'),
    ];
    foreach ($temporaryRoots as $temporaryRoot) {
        runControlLightFilesetCommand([
            PHP_BINARY,
            $builder,
            '--output-root=' . $temporaryRoot,
            $manifest,
        ]);
    }

    $first = controlLightFilesetHashes($temporaryRoots[0] . '/' . $relativeRoot);
    $second = controlLightFilesetHashes($temporaryRoots[1] . '/' . $relativeRoot);
    $tracked = controlLightFilesetHashes($projectRoot . '/' . $relativeRoot);
    assertControlLightFilesetSame($first, $second, 'Independent fileset builds differ.');
    assertControlLightFilesetSame($first, $tracked, 'Tracked ControlLight fileset is stale.');

    $sourceMap = json_decode(
        (string)file_get_contents($temporaryRoots[0] . '/' . $relativeRoot . '/fileset.sources.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    assertControlLightFilesetSame(11, count($sourceMap['orderedSources'] ?? []), 'Source closure differs.');
    assertControlLightFilesetSame(
        true,
        in_array('SAEF_EnsureLink', $sourceMap['functionExports'] ?? [], true),
        'EnsureLink is missing from the fileset.'
    );
    assertControlLightFilesetSame(
        [
            'SAEF\\CaseStudy\\ControlLight\\ControlLightCore',
            'SAEF\\CaseStudy\\ControlLight\\ControlLightRuntime',
        ],
        $sourceMap['classExports'] ?? [],
        'ControlLight class exports differ.'
    );

    $bootstrap = (string)file_get_contents($temporaryRoots[0] . '/' . $relativeRoot . '/bootstrap.php');
    if (str_contains($bootstrap, '/Users/') || str_contains($bootstrap, 'ObjectID')) {
        throw new RuntimeException('Generated ControlLight bootstrap contains private deployment data.');
    }
    verifyControlLightCanonicalSourceRejection($builder, $temporaryRoots);

    fwrite(STDOUT, "ControlLight fileset tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'ControlLight fileset test failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeControlLightFilesetTree($temporaryRoot);
    }
}

/** @param list<string> $command */
function runControlLightFilesetCommand(array $command): void
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ControlLight fileset build.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('Fileset subprocess failed: ' . trim((string)$stderr . (string)$stdout));
    }
}

/** @param list<string> $temporaryRoots */
function verifyControlLightCanonicalSourceRejection(string $builder, array &$temporaryRoots): void
{
    if (PHP_OS_FAMILY === 'Windows') {
        return;
    }

    $fixtureRoot = createControlLightFilesetTemporaryRoot('control-light-fileset-canonical-');
    $temporaryRoots[] = $fixtureRoot;

    foreach (
        [
            'tools',
            'helpers',
            'case-studies/control-light/candidate',
            'deployments/symcon',
        ] as $directory
    ) {
        if (!mkdir($fixtureRoot . '/' . $directory, 0700, true)) {
            throw new RuntimeException('Cannot create canonical-path fixture directory.');
        }
    }

    if (!copy($builder, $fixtureRoot . '/tools/build-symcon-fileset.php')) {
        throw new RuntimeException('Cannot copy fileset builder into canonical-path fixture.');
    }

    writeControlLightFixture(
        $fixtureRoot . '/helpers/Fixture.php',
        <<<'PHP'
<?php
declare(strict_types=1);

if (!defined('SAEF_HELPER_FILESET_FIXTURE')) {
    define('SAEF_HELPER_FILESET_FIXTURE', true);

    // function SAEF_DecoyFromComment(): void {}
    // defined('SAEF_HELPER_DECOY_FROM_COMMENT')
    function SAEF_FilesetFixture(): void
    {
    }
}
PHP
        . "\n"
    );
    writeControlLightFixture(
        $fixtureRoot . '/case-studies/control-light/candidate/Real.php',
        <<<'PHP'
<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

require_once __DIR__ . '/../../../helpers/Fixture.php';

final class Fixture
{
}
PHP
        . "\n"
    );

    $aliasPath = $fixtureRoot . '/case-studies/control-light/candidate/Entry.php';
    if (!symlink('Real.php', $aliasPath)) {
        throw new RuntimeException('Cannot create canonical-path fixture symlink.');
    }

    writeControlLightFixture(
        $fixtureRoot . '/deployments/symcon/fixture.fileset.json',
        <<<'JSON'
{
    "formatVersion": 1,
    "name": "canonical-path-fixture",
    "entry": "case-studies/control-light/candidate/Entry.php",
    "phpMinimum": "8.2",
    "outputDirectory": "dist/symcon/canonical-path-fixture",
    "functionExports": [
        "SAEF_FilesetFixture"
    ],
    "classExports": [
        "SAEF\\CaseStudy\\ControlLight\\Fixture"
    ]
}
JSON
        . "\n"
    );

    runControlLightFilesetCommandExpectFailure(
        [
            PHP_BINARY,
            $fixtureRoot . '/tools/build-symcon-fileset.php',
            '--output-root=' . $fixtureRoot . '/output',
            $fixtureRoot . '/deployments/symcon/fixture.fileset.json',
        ],
        'Fileset source must resolve to its canonical path inside the project root'
    );

    writeControlLightFixture(
        $fixtureRoot . '/deployments/symcon/token-aware.fileset.json',
        <<<'JSON'
{
    "formatVersion": 1,
    "name": "token-aware-fixture",
    "entry": "case-studies/control-light/candidate/Real.php",
    "phpMinimum": "8.2",
    "outputDirectory": "dist/symcon/token-aware-fixture",
    "functionExports": [
        "SAEF_FilesetFixture"
    ],
    "classExports": [
        "SAEF\\CaseStudy\\ControlLight\\Fixture"
    ]
}
JSON
        . "\n"
    );
    runControlLightFilesetCommand([
        PHP_BINARY,
        $fixtureRoot . '/tools/build-symcon-fileset.php',
        '--output-root=' . $fixtureRoot . '/output',
        $fixtureRoot . '/deployments/symcon/token-aware.fileset.json',
    ]);

    $bootstrap = file_get_contents(
        $fixtureRoot . '/output/dist/symcon/token-aware-fixture/bootstrap.php'
    );
    if ($bootstrap === false || str_contains($bootstrap, 'DECOY_FROM_COMMENT')) {
        throw new RuntimeException('Fileset export discovery accepted a comment decoy.');
    }
}

/** @param list<string> $command */
function runControlLightFilesetCommandExpectFailure(array $command, string $expectedMessage): void
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start expected-failure fileset build.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $output = (string)$stderr . (string)$stdout;
    if ($status === 0 || !str_contains($output, $expectedMessage)) {
        throw new RuntimeException('Fileset builder did not reject source alias: ' . trim($output));
    }
}

function writeControlLightFixture(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) !== strlen($contents)) {
        throw new RuntimeException('Cannot write ControlLight fileset fixture.');
    }
}

function createControlLightFilesetTemporaryRoot(string $prefix): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));
    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Cannot create ControlLight fileset temporary root.');
    }
    return $path;
}

/** @return array<string, string> */
function controlLightFilesetHashes(string $root): array
{
    if (!is_dir($root)) {
        throw new RuntimeException('ControlLight fileset root is missing.');
    }
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo && $item->isFile()) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            $hashes[$relative] = hash_file('sha256', $item->getPathname());
        }
    }
    ksort($hashes, SORT_STRING);
    return $hashes;
}

function removeControlLightFilesetTree(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($root);
}

function assertControlLightFilesetSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}
