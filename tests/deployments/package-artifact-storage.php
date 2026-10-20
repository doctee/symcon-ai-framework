<?php

declare(strict_types=1);

use Opis\JsonSchema\Validator;

$project = dirname(__DIR__, 2);
require $project . '/tools/deployment/PackageArtifacts.php';
$configuredVendor = getenv('COMPOSER_VENDOR_DIR') ?: 'vendor';
$vendor = str_starts_with($configuredVendor, '/') ? $configuredVendor : $project . '/' . $configuredVendor;
require $vendor . '/autoload.php';
$validator = new Validator();
$schema = json_decode(file_get_contents($project . '/standards/schemas/artifact-record.schema.json'));
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Package storage regression: ' . $label);
    }
    $checks++;
};
$reject = static function (callable $operation, string $reason) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $error) {
        $assert(str_contains($error->getMessage(), $reason), 'specific rejection: ' . $reason);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $reason);
};

// Retain all synthetic inputs/results in the ignored worktree; no cleanup path.
$parent = $project . '/private/package-artifact-tests';
if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
    throw new RuntimeException('Cannot create local test parent.');
}
assertPackageArtifactDirectory($parent);
$root = $parent . '/' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700)) {
    throw new RuntimeException('Cannot create retained test run.');
}
fwrite(STDOUT, "Retained local fixtures: {$root}\n");
mkdir($root . '/operations', 0700);
mkdir($root . '/source', 0700);
mkdir($root . '/module', 0700);
file_put_contents($root . '/source/bootstrap.php', "<?php\nfunction SyntheticExample(): void {}\n");
$old = '.oldx-filesets/saef-old-fileset/bootstrap.php';
$new = '.saef-filesets/saef-new-fileset/bootstrap.php';
file_put_contents($root . '/bootstrap.php', "<?php require '{$old}';\n");
$guid = '{11111111-2222-3333-4444-555555555555}';
file_put_contents($root . '/module/library.json', json_encode(['id' => $guid]));
mkdir($root . '/module/Example', 0700);
file_put_contents($root . '/module/Example/module.json', json_encode([
    'id' => '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}',
]));
file_put_contents($root . '/module/Example/module.php', "<?php\n");
file_put_contents($root . '/transaction.json', json_encode([
    'formatVersion' => 1, 'adapterProfile' => 'saef-synthetic-v1',
]));
$storage = [
    'root' => $root . '/operations', 'workstream' => 'synthetic-storage',
    'owner' => 'offline-test', 'purpose' => 'Retained synthetic package storage regression',
];
$sourcePaths = [
    $root . '/source/bootstrap.php', $root . '/bootstrap.php', $root . '/module/library.json',
    $root . '/module/Example/module.json', $root . '/module/Example/module.php', $root . '/transaction.json',
];
$sourceHashes = array_map(static fn (string $path): string => hash_file('sha256', $path), $sourcePaths);
$plans = [
    'build-symcon-deployment-package.php' => [
        'formatVersion' => 1, 'deploymentId' => 'saef-test', 'targetDirectoryName' => 'saef-new-fileset',
        'filesetPath' => $root . '/source', 'bootstrapSnapshotPath' => $root . '/bootstrap.php',
        'oldToken' => $old, 'newToken' => $new, 'requiredRuntimeFunctions' => ['SyntheticExample'],
    ],
    'build-symcon-module-deployment-package.php' => [
        'formatVersion' => 1, 'deploymentId' => 'saef-test-module', 'targetDirectoryName' => 'saef-module-fileset',
        'modulePath' => $root . '/module', 'moduleTargetId' => 'saef-test-module', 'libraryGuid' => $guid,
        'transactionContractPath' => $root . '/transaction.json',
    ],
];
$runBuilder = static function (string $builder, string $planPath): array {
    $pipes = [];
    $process = proc_open([PHP_BINARY, $builder, $planPath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start local builder.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
};
foreach ($plans as $name => $plan) {
    $builder = $project . '/tools/' . $name;
    $path = $root . '/' . $name . '.plan.local.json';
    $plan['outputPath'] = $root . '/' . $name . '.legacy.zip';
    file_put_contents($path, json_encode($plan));
    [$exit, $stdout, $stderr] = $runBuilder($builder, $path);
    $assert($exit === 0, 'legacy build: ' . $stderr);
    $legacy = json_decode($stdout, true, 64, JSON_THROW_ON_ERROR);
    $legacyBytes = file_get_contents($plan['outputPath']);
    $assert(!array_key_exists('runDirectory', $legacy), 'legacy stdout shape unchanged');
    [$exit] = $runBuilder($builder, $path);
    $assert($exit !== 0 && file_get_contents($plan['outputPath']) === $legacyBytes, 'legacy collision preserved');
    unset($plan['outputPath']);
    $plan['formatVersion'] = 2;
    $plan['storage'] = $storage;
    $path = $root . '/' . $name . '.v2.plan.local.json';
    file_put_contents($path, json_encode($plan));
    $priorRun = null;
    for ($iteration = 0; $iteration < 2; $iteration++) {
        [$exit, $stdout, $stderr] = $runBuilder($builder, $path);
        $assert($exit === 0, 'managed build: ' . $stderr);
        $result = json_decode($stdout, true, 64, JSON_THROW_ON_ERROR);
        $run = $result['runDirectory'];
        $assert($run !== $priorRun, 'distinct run, no overwrite');
        $priorRun = $run;
        $assert(preg_match('/^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$/D', basename($run)) === 1, 'UTC run naming');
        $assert(dirname($run) === $storage['root'] . '/' . $storage['workstream'], 'configured location');
        $assert(file_get_contents($run . '/package.local.zip') === $legacyBytes, 'byte-identical ZIP to legacy');
        $assert($result['packageSha256'] === $legacy['packageSha256'], 'stable package identity');
        $record = json_decode(file_get_contents($result['artifactRecordPath']));
        $assert($validator->validate($record, $schema)->isValid(), 'generated metadata schema');
        $assert($record->identity->sha256 === $result['packageSha256'], 'metadata binds package');
        $assert($record->state === 'completed' && $record->retention->decision === 'retain', 'completion retains');
        $assert(file_get_contents($run . '/input-plan.local.json') === file_get_contents($path), 'plan bytes retained');
        $started = json_decode(file_get_contents($run . '/started.local.json'));
        $assert($started->state === 'review-required', 'initial receipt never claims success');
        $assert($validator->validate($started, $schema)->isValid(), 'initial receipt schema');
        $assert((fileperms($run) & 0077) === 0, 'private run directory');
        $assert((fileperms($run . '/package.local.zip') & 0077) === 0, 'private output file');
        $archive = new ZipArchive();
        $assert($archive->open($run . '/package.local.zip') === true, 'read back archive');
        $assert($archive->locateName('artifact-record.local.json') === false, 'metadata outside immutable ZIP');
        $manifest = $archive->getFromName('deployment.json');
        $assert(json_decode($manifest)->formatVersion === 1, 'deployment manifest version unchanged');
        $assert(
            $record->identity->manifestRef === 'package.local.zip!deployment.json#sha256=' . hash('sha256', $manifest),
            'record binds actual archive manifest'
        );
        $archive->close();
    }
    $input = $plan['filesetPath'] ?? $plan['modulePath'];
    $bad = $plan;
    $bad['storage']['root'] = $root;
    $bad['storage']['workstream'] = basename($input);
    $beforeInput = scandir($input);
    $overlapPath = $root . '/' . $name . '.overlap.plan.local.json';
    file_put_contents($overlapPath, json_encode($bad));
    [$exit, , $stderr] = $runBuilder($builder, $overlapPath);
    $assert($exit !== 0 && str_contains($stderr, 'outside the input'), 'CLI workstream overlap rejected: ' . $name);
    $assert(scandir($input) === $beforeInput, 'CLI overlap creates no input child: ' . $name);

    $sibling = $bad;
    $sibling['storage']['workstream'] .= '-output';
    $siblingPath = $root . '/' . $name . '.sibling.plan.local.json';
    file_put_contents($siblingPath, json_encode($sibling));
    [$exit, $stdout, $stderr] = $runBuilder($builder, $siblingPath);
    $assert($exit === 0, 'common-prefix sibling allowed: ' . $stderr);
    $siblingResult = json_decode($stdout, true, 64, JSON_THROW_ON_ERROR);
    $assert(
        file_get_contents($siblingResult['runDirectory'] . '/package.local.zip') === $legacyBytes,
        'common-prefix sibling keeps package bytes'
    );

    $bad = $plan;
    $bad['outputPath'] = $root . '/must-not-exist.zip';
    file_put_contents($root . '/conflict.local.json', json_encode($bad));
    [$exit, , $stderr] = $runBuilder($builder, $root . '/conflict.local.json');
    $assert($exit !== 0 && str_contains($stderr, 'conflicts'), 'CLI conflicting path rejected');
    $assert(!file_exists($bad['outputPath']), 'no conflicting output created');
    $bad = $plan;
    if (isset($bad['newToken'])) {
        $bad['newToken'] = 'outside-fileset';
    } else {
        $bad['libraryGuid'] = '{BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB}';
    }
    $before = scandir($storage['root'] . '/' . $storage['workstream']);
    file_put_contents($root . '/invalid-candidate.local.json', json_encode($bad));
    [$exit] = $runBuilder($builder, $root . '/invalid-candidate.local.json');
    $assert($exit !== 0, 'invalid candidate still rejected');
    $assert($before === scandir($storage['root'] . '/' . $storage['workstream']), 'candidate rejection creates no run');
}

$reject(static fn () => packageArtifactStorage(['formatVersion' => 1, 'storage' => $storage]), 'formatVersion 2');
$reject(static fn () => packageArtifactStorage(['formatVersion' => 9]), 'version');
foreach (['root', 'workstream', 'owner', 'purpose'] as $field) {
    $bad = $storage;
    unset($bad[$field]);
    $reject(static fn () => packageArtifactStorage(['formatVersion' => 2, 'storage' => $bad]), 'fields differ');
}
foreach (['../escape', 'Uppercase', ''] as $slug) {
    $bad = $storage;
    $bad['workstream'] = $slug;
    $reject(static fn () => packageArtifactStorage(['formatVersion' => 2, 'storage' => $bad]), 'invalid');
}
foreach ([$root . '/missing', $root . '/operations/../operations', 'relative'] as $path) {
    $bad = $storage;
    $bad['root'] = $path;
    $reject(static fn () => packageArtifactStorage(['formatVersion' => 2, 'storage' => $bad]), 'canonical');
}
mkdir($root . '/unprotected', 0755);
$bad = $storage;
$bad['root'] = $root . '/unprotected';
$reject(static fn () => packageArtifactStorage(['formatVersion' => 2, 'storage' => $bad]), 'private');
symlink($storage['root'], $root . '/alias');
$bad['root'] = $root . '/alias';
$reject(static fn () => packageArtifactStorage(['formatVersion' => 2, 'storage' => $bad]), 'canonical');

$source = $root . '/source';
$producer = $project . '/tools/build-symcon-deployment-package.php';
$planPath = $root . '/build-symcon-deployment-package.php.v2.plan.local.json';
$planBytes = file_get_contents($planPath);
$begin = static fn (array $config, string $id): array => beginPackageArtifactRun(
    $config,
    $id,
    $source,
    $planPath,
    $producer,
    $planBytes
);
symlink($source, $storage['root'] . '/linked-workstream');
$bad = $storage;
$bad['workstream'] = 'linked-workstream';
$reject(static fn () => $begin($bad, '20260101T000000Z-000000000004'), 'canonical');
$bad = $storage;
$bad['root'] = $source;
$reject(static fn () => $begin($bad, '20260101T000000Z-000000000001'), 'outside the input');
$fixedId = '20260101T000000Z-000000000005';
$nested = $storage;
$nested['workstream'] = 'run-overlap';
$nestedParent = $nested['root'] . '/' . $nested['workstream'];
$nestedSource = $nestedParent . '/' . $fixedId;
$reject(static fn () => beginPackageArtifactRun(
    $nested,
    $fixedId,
    $nestedSource,
    $planPath,
    $producer,
    $planBytes
), 'outside the input');
$assert(!file_exists($nestedParent), 'run overlap rejected before creating missing parent');
mkdir($nestedParent, 0700);
mkdir($nestedSource, 0700);
writePackageArtifactFile($nestedSource . '/sentinel.local.txt', 'protected source');
$reject(static fn () => beginPackageArtifactRun(
    $nested,
    $fixedId,
    $nestedSource,
    $planPath,
    $producer,
    $planBytes
), 'outside the input');
$assert(scandir($nestedSource) === ['.', '..', 'sentinel.local.txt'], 'existing run/source untouched');
$assert(file_get_contents($nestedSource . '/sentinel.local.txt') === 'protected source', 'source sentinel unchanged');
$reject(static fn () => $begin($storage, '../escape'), 'run ID');
$run = $begin($storage, '20260101T000000Z-000000000002');
$startedHash = hash_file('sha256', $run['directory'] . '/started.local.json');
$reject(static fn () => $begin($storage, '20260101T000000Z-000000000002'), 'already exists');
$assert(hash_file('sha256', $run['directory'] . '/started.local.json') === $startedHash, 'collision unchanged');
$assert(!file_exists($run['directory'] . '/artifact-record.local.json'), 'interrupted run not completed');
file_put_contents($run['package'], 'synthetic failure fixture');
writePackageArtifactFile($run['directory'] . '/artifact-record.local.json', 'collision sentinel');
$reject(static fn () => completePackageArtifactRun($run, '{}'), 'already exists');
failPackageArtifactRun($run, new RuntimeException('synthetic failure'));
$assert(file_get_contents($run['directory'] . '/artifact-record.local.json') === 'collision sentinel', 'receipt not overwritten');
$assert(is_file($run['package']) && is_file($run['directory'] . '/failure.local.json'), 'failed output retained');
$reject(static fn () => beginPackageArtifactRun(
    $storage,
    '20260101T000000Z-000000000003',
    $source,
    $planPath,
    $producer,
    '{}'
), 'unchanged plan');
$assert(!file_exists($storage['root'] . '/' . $storage['workstream'] . '/20260101T000000Z-000000000003'), 'drift creates no run');
$assert(
    $sourceHashes === array_map(static fn (string $path): string => hash_file('sha256', $path), $sourcePaths),
    'all synthetic source bytes unchanged'
);
writePackageArtifactFile($root . '/test-result.local.json', packageArtifactJson([
    'outcome' => 'passed', 'checks' => $checks, 'cleanupPerformed' => false,
    'testObjectsRetained' => true, 'liveAccess' => false,
]));
fwrite(STDOUT, "PASS: {$checks} local package storage checks; all fixtures retained.\n");
