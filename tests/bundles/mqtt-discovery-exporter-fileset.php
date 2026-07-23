<?php

declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;
use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

if (isset($argv[1]) && in_array($argv[1], ['--function-conflict', '--class-conflict'], true)) {
    runFilesetConflictProbe($argv[1], $argv[2] ?? '');
    exit(0);
}

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
$builder = $projectRoot . '/tools/build-symcon-fileset.php';
$manifest = $projectRoot . '/deployments/symcon/mqtt-discovery-exporter.fileset.json';
$relativeRoot = 'dist/symcon/saef-mqtt-discovery-exporter';
$temporaryRoots = [];

try {
    $temporaryRoots[] = createFilesetTemporaryRoot('saef-fileset-a-');
    $temporaryRoots[] = createFilesetTemporaryRoot('saef-fileset-b-');
    foreach ($temporaryRoots as $temporaryRoot) {
        runFilesetCommand([
            PHP_BINARY,
            $builder,
            '--output-root=' . $temporaryRoot,
            $manifest,
        ]);
    }

    $firstFiles = listFilesetFiles($temporaryRoots[0] . '/' . $relativeRoot);
    $secondFiles = listFilesetFiles($temporaryRoots[1] . '/' . $relativeRoot);
    $trackedFiles = listFilesetFiles($projectRoot . '/' . $relativeRoot);
    assertFilesetSame($firstFiles, $secondFiles, 'Independent fileset file lists differ.');
    assertFilesetSame($firstFiles, $trackedFiles, 'Tracked fileset file list differs.');

    foreach ($firstFiles as $relativeFile) {
        $first = readFilesetFile($temporaryRoots[0] . '/' . $relativeRoot . '/' . $relativeFile);
        $second = readFilesetFile($temporaryRoots[1] . '/' . $relativeRoot . '/' . $relativeFile);
        $tracked = readFilesetFile($projectRoot . '/' . $relativeRoot . '/' . $relativeFile);
        assertFilesetSame($first, $second, 'Independent fileset content differs: ' . $relativeFile);
        assertFilesetSame($first, $tracked, 'Tracked fileset content is stale: ' . $relativeFile);
    }

    $sourceMap = json_decode(
        readFilesetFile($temporaryRoots[0] . '/' . $relativeRoot . '/fileset.sources.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    assertFilesetSame(14, count($sourceMap['orderedSources'] ?? []), 'Source closure size differs.');
    foreach ($sourceMap['orderedSources'] as $sourceEntry) {
        $sourcePath = $sourceEntry['path'] ?? null;
        if (!is_string($sourcePath)) {
            throw new RuntimeException('Source map path is invalid.');
        }
        assertFilesetSame(
            readFilesetFile($projectRoot . '/' . $sourcePath),
            readFilesetFile($temporaryRoots[0] . '/' . $relativeRoot . '/' . $sourcePath),
            'Canonical source was transformed: ' . $sourcePath
        );
    }

    $bootstrapPath = $temporaryRoots[0] . '/' . $relativeRoot . '/bootstrap.php';
    $bootstrap = readFilesetFile($bootstrapPath);
    if (str_contains($bootstrap, '/Users/') || str_contains($bootstrap, 'ObjectID')) {
        throw new RuntimeException('Generated bootstrap contains private deployment data.');
    }
    verifyFilesetConflictFailures($bootstrapPath);

    require_once __DIR__ . '/../mqtt-discovery-exporter/DiagnosticsFakeSymconRuntime.php';
    require_once $bootstrapPath;
    assertFilesetSame(true, function_exists('SAEF_EnsureCategory'), 'Helper export is unavailable.');
    assertFilesetSame(true, function_exists('SAEF_EnsureScript'), 'EnsureScript export is unavailable.');
    assertFilesetSame(true, class_exists(MqttDiscoveryExporterCore::class), 'Core class is unavailable.');
    assertFilesetSame(true, class_exists(MqttDiscoveryExporterRuntime::class), 'Runtime class is unavailable.');

    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration([
        'version' => 'fileset-smoke',
        'location' => 'fileset_test',
        'mqtt' => [
            'serverID' => 9999,
            'baseTopic' => 'saef/fileset',
            'discoveryPrefix' => 'homeassistant',
        ],
        'devices' => [],
    ]);
    $diagnostics = MqttDiscoveryExporterRuntime::initializeDiagnostics($ownerScriptID, $configuration);
    assertFilesetSame(1, $diagnostics['registry']['schemaVersion'], 'Fileset runtime smoke failed.');
    $variableID = SAEF_EnsureVariable(
        $ownerScriptID,
        'FILESET_COMPATIBILITY',
        'Fileset Compatibility',
        0
    );
    $reusedVariableID = SAEF_EnsureVariable(
        $ownerScriptID,
        'FILESET_COMPATIBILITY',
        'Fileset Compatibility',
        0
    );
    assertFilesetSame($variableID, $reusedVariableID, 'EnsureVariable compatibility is not idempotent.');

    fwrite(STDOUT, "MQTT Discovery Exporter fileset tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fileset test failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeFilesetTree($temporaryRoot);
    }
}

function runFilesetConflictProbe(string $mode, string $bootstrap): void
{
    if ($mode === '--function-conflict') {
        eval('function SAEF_EnsureCategory(): void {}');
    } else {
        eval('namespace SAEF\\CaseStudy\\MqttDiscoveryExporter; class MqttDiscoveryExporterCore {}');
    }
    try {
        require $bootstrap;
    } catch (RuntimeException $exception) {
        if (str_contains($exception->getMessage(), 'SAEF fileset namespace conflict:')) {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException('Expected fileset conflict was not rejected.');
}

function verifyFilesetConflictFailures(string $bootstrap): void
{
    foreach (['--function-conflict', '--class-conflict'] as $mode) {
        runFilesetCommand([PHP_BINARY, __FILE__, $mode, $bootstrap]);
    }
}

/** @param list<string> $command */
function runFilesetCommand(array $command): void
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start fileset test process.');
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

function createFilesetTemporaryRoot(string $prefix): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));
    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Cannot create fileset temporary root.');
    }

    return $path;
}

/** @return list<string> */
function listFilesetFiles(string $root): array
{
    if (!is_dir($root)) {
        throw new RuntimeException('Fileset output directory is missing: ' . $root);
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo && $item->isFile()) {
            $files[] = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        }
    }
    sort($files, SORT_STRING);

    return $files;
}

function readFilesetFile(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Cannot read fileset test file: ' . $path);
    }

    return $contents;
}

function removeFilesetTree(string $root): void
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

function assertFilesetSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}
