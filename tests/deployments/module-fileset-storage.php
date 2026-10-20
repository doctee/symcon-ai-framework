<?php

declare(strict_types=1);

use Opis\JsonSchema\Validator;

$project = dirname(__DIR__, 2);
require $project . '/tools/deployment/ModuleFilesetArtifacts.php';
$vendor = getenv('COMPOSER_VENDOR_DIR') ?: $project . '/vendor';
require(str_starts_with($vendor, '/') ? $vendor : $project . '/' . $vendor) . '/autoload.php';
$validator = new Validator();
$schema = json_decode(file_get_contents($project . '/standards/schemas/artifact-record.schema.json'));
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('Fileset storage regression: ' . $label);
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
$command = static function (array $arguments): array {
    $process = proc_open([PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start local fileset builder.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
};
$inventory = static function (string $root): array {
    $result = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink() || !$file->isFile()) {
            throw new RuntimeException('Unexpected fileset entry.');
        }
        $result[substr($file->getPathname(), strlen($root) + 1)] = file_get_contents($file->getPathname());
    }
    ksort($result, SORT_STRING);
    return $result;
};

// All synthetic runs, including deliberate failures, remain in this worktree.
$parent = $project . '/private/module-fileset-storage-tests';
if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
    throw new RuntimeException('Cannot create retained test parent.');
}
assertPackageArtifactDirectory($parent);
$root = $parent . '/' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700) || !mkdir($root . '/operations', 0700)) {
    throw new RuntimeException('Cannot create retained test run.');
}
fwrite(STDOUT, "Retained local fixtures: {$root}\n");
$storage = ['root' => $root . '/operations', 'workstream' => 'synthetic-fileset',
    'owner' => 'offline-test', 'purpose' => 'Retained fileset storage regression'];
$plan = ['formatVersion' => 2, 'storage' => $storage];
$planPath = $root . '/plan.local.json';
writePackageArtifactFile($planPath, packageArtifactJson($plan));
$builder = $project . '/tools/build-symcon-module-fileset.php';
$sourceHashes = [];
$allManifests = glob($project . '/deployments/symcon/*-module.fileset.json');
$assert(count($allManifests) === 5, 'all five current module manifests covered');
foreach ($allManifests as $manifest) {
    $definition = json_decode(file_get_contents($manifest), true, 64, JSON_THROW_ON_ERROR);
    foreach ([$manifest, ...array_map(static fn (array $f): string => $project . '/' . $f['source'], $definition['files'])] as $source) {
        $sourceHashes[$source] = hash_file('sha256', $source);
    }
    $relativeRoot = $definition['outputDirectory'];
    $legacyRoot = $root . '/legacy-' . $definition['name'];
    mkdir($legacyRoot, 0700);
    [$exit, $stdout, $stderr] = $command([$builder, '--output-root=' . $legacyRoot, $manifest]);
    $assert($exit === 0 && $stderr === '' && str_starts_with($stdout, 'Built '), 'legacy CLI result');
    $legacy = $inventory($legacyRoot . '/' . $relativeRoot);
    $assert($legacy === $inventory($project . '/' . $relativeRoot), 'unchanged tracked bytes and hashes');
    [$exit, $stdout, $stderr] = $command([$builder, '--check', $manifest]);
    $assert($exit === 0 && $stdout === "Symcon module fileset artifacts are current.\n" && $stderr === '', 'legacy check');
    $prior = null;
    for ($iteration = 0; $iteration < 2; $iteration++) {
        [$exit, $stdout, $stderr] = $command([$builder, '--private-output-plan=' . $planPath, $manifest]);
        $assert($exit === 0 && $stderr === '', 'private CLI: ' . $stderr);
        $result = json_decode($stdout, true, 16, JSON_THROW_ON_ERROR);
        $run = $result['runDirectory'];
        $assert($run !== $prior, 'distinct runs, no reuse');
        $prior = $run;
        $assert(dirname($run) === $storage['root'] . '/' . $storage['workstream'], 'configured workstream');
        $assert(preg_match('/^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$/D', basename($run)) === 1, 'UTC run ID');
        $assert($inventory($result['outputRoot'] . '/' . $relativeRoot) === $legacy, 'private exact legacy inventory');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($run, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
            $assert(!$file->isLink() && $file->getOwner() === posix_geteuid(), 'owned plain output');
            $assert(($file->getPerms() & 0777) === ($file->isDir() ? 0700 : 0600), 'exact private modes');
        }
        $assert((fileperms($run) & 0777) === 0700, 'private run mode');
        $record = json_decode(file_get_contents($result['artifactRecordPath']));
        $started = json_decode(file_get_contents($run . '/started.local.json'));
        $assert($validator->validate($record, $schema)->isValid(), 'completed record schema');
        $assert($validator->validate($started, $schema)->isValid(), 'started record schema');
        $assert($started->state === 'review-required' && $record->state === 'completed', 'honest lifecycle states');
        $assert($record->retention->decision === 'retain' && $record->copyOf === null, 'original retained');
        $assert($record->identity->sha256 === null, 'no invented tree hash');
        $assert(str_ends_with($record->identity->manifestRef, '#sha256=' . hash('sha256', $legacy['fileset.sources.json'])), 'source-map identity');
        $assert(file_get_contents($run . '/input-plan.local.json') === file_get_contents($planPath), 'exact plan copy');
        $assert(file_get_contents($run . '/input-manifest.local.json') === file_get_contents($manifest), 'exact manifest copy');
        [$exit] = $command([$builder, '--check', '--output-root=' . $result['outputRoot'], $manifest]);
        $assert($exit === 0, 'ordinary read-only check of private output');
    }
}

$manifest = $allManifests[0];
$before = scandir($storage['root'] . '/' . $storage['workstream']);
foreach ([['--check'], ['--output-root=' . $root], ['--private-output-plan=' . $planPath]] as $options) {
    [$exit, , $stderr] = $command([$builder, '--private-output-plan=' . $planPath, ...$options, $manifest]);
    $assert($exit !== 0 && (str_contains($stderr, 'conflicts') || str_contains($stderr, 'Duplicate')), 'conflicting CLI rejected');
}
$assert($before === scandir($storage['root'] . '/' . $storage['workstream']), 'CLI rejection has no run');
$definition = json_decode(file_get_contents($manifest), true);
$outputs = [];
foreach ($inventory($project . '/' . $definition['outputDirectory']) as $path => $bytes) {
    $outputs[$definition['outputDirectory'] . '/' . $path] = $bytes;
}
$write = static fn (array $files, string $path = ''): array => writePrivateModuleFileset(
    $files,
    $path === '' ? $planPath : $path,
    $manifest,
    hash_file('sha256', $manifest),
    $builder
);
mkdir($root . '/unprotected', 0755);
symlink($storage['root'], $root . '/alias');
symlink($storage['root'], $storage['root'] . '/linked-workstream');
foreach (['relative', $root . '/missing', $root . '/alias', $storage['root'] . '/../operations', $root . '/unprotected'] as $index => $badRoot) {
    $bad = $plan;
    $bad['storage']['root'] = $badRoot;
    $path = $root . '/bad-root-' . $index . '.json';
    writePackageArtifactFile($path, packageArtifactJson($bad));
    $reject(static fn () => $write($outputs, $path), $index === 4 ? 'private' : 'canonical');
}
foreach (['../escape', 'linked-workstream'] as $index => $slug) {
    $bad = $plan;
    $bad['storage']['workstream'] = $slug;
    $path = $root . '/bad-workstream-' . $index . '.json';
    writePackageArtifactFile($path, packageArtifactJson($bad));
    $reject(static fn () => $write($outputs, $path), $index === 0 ? 'invalid' : 'canonical');
}
$reject(static fn () => writePrivateModuleFileset($outputs, $planPath, $manifest, str_repeat('0', 64), $builder), 'manifest changed');
foreach (['../escape', 'dist/symcon/../escape', 'dist/symcon/UPPER.php', 'dist/symcon/name:stream', 'dist/symcon/trailing.'] as $path) {
    $bad = $outputs + ['dist/symcon/upper.php' => 'sentinel', $path => 'invalid'];
    $reject(static fn () => $write($bad), $path === 'dist/symcon/UPPER.php' ? 'Case-colliding' : 'Unsafe');
}
$reject(static fn () => $write($outputs + ['dist/symcon/tree' => 'x', 'dist/symcon/tree/child.php' => 'x']), 'conflicting');
$reject(static fn () => $write($outputs + ['dist/symcon/Dir/a.php' => 'x', 'dist/symcon/dir/b.php' => 'x']), 'Case-colliding');
$sentinel = $root . '/sentinel';
writePackageArtifactFile($sentinel, 'do not overwrite');
$reject(static fn () => writePackageArtifactFile($sentinel, 'replacement'), 'already exists');
$assert(file_get_contents($sentinel) === 'do not overwrite', 'exclusive writes preserve existing bytes');
$assert($before === scandir($storage['root'] . '/' . $storage['workstream']), 'all preflight rejections have no run');

foreach (
    [['formatVersion' => 1, 'storage' => $storage], $plan + ['unexpected' => true],
    ['formatVersion' => 2, 'storage' => $storage + ['extra' => 'no']],
    ['formatVersion' => 2, 'storage' => null]] as $index => $bad
) {
    $path = $root . '/invalid-plan-' . $index . '.json';
    writePackageArtifactFile($path, packageArtifactJson($bad));
    $reject(static fn () => $write($outputs, $path), $index === 0 ? 'formatVersion 2' : 'fields differ');
}
symlink($planPath, $root . '/plan-alias.json');
$reject(static fn () => $write($outputs, $root . '/plan-alias.json'), 'canonical');
$overlap = $plan;
$overlap['storage']['root'] = $root;
writePackageArtifactFile($root . '/overlap.json', packageArtifactJson($overlap));
writePackageArtifactFile($root . '/manifest.local.json', file_get_contents($manifest));
$reject(static fn () => writePrivateModuleFileset(
    $outputs,
    $root . '/overlap.json',
    $root . '/manifest.local.json',
    hash_file('sha256', $manifest),
    $builder
), 'outside source');

// The configured root can be outside inputs while the appended workstream is not.
$fixture = $root . '/overlap-fixture';
foreach (['', '/tools', '/contracts', '/inputs', '/dist', '/dist/symcon', '/dist/symcon/example'] as $suffix) {
    createPrivateFilesetDirectory($fixture . $suffix);
}
$fixtureProducer = $fixture . '/tools/producer.php';
$fixtureManifest = $fixture . '/contracts/manifest.json';
writePackageArtifactFile($fixtureProducer, 'Synthetic identity only; never executed.');
writePackageArtifactFile($fixtureManifest, '{}');
writePackageArtifactFile($fixture . '/inputs/keep', 'source sentinel');
writePackageArtifactFile($fixture . '/dist/symcon/example/keep', 'generated sentinel');
$fixtureOutputs = [
    'dist/symcon/example/fileset.sources.json' => packageArtifactJson([
        'files' => [['source' => 'inputs/keep'], ['source' => 'missing-inputs/file.php']],
    ]),
];
foreach (
    [
    [$fixture, 'inputs'],
    [$fixture, 'contracts'],
    [$fixture . '/dist/symcon', 'example'],
    [$fixture, 'missing-inputs'],
    [$fixture . '/inputs', 'nested'],
    ] as $index => [$fixtureRoot, $slug]
) {
    $fixturePlan = $plan;
    $fixturePlan['storage']['root'] = $fixtureRoot;
    $fixturePlan['storage']['workstream'] = $slug;
    $fixturePlanPath = $root . '/effective-overlap-' . $index . '.json';
    writePackageArtifactFile($fixturePlanPath, packageArtifactJson($fixturePlan));
    $snapshot = $inventory($fixture);
    $parentPath = $fixtureRoot . '/' . $slug;
    $parentEntries = is_dir($parentPath) ? scandir($parentPath) : null;
    $rootEntries = scandir($fixtureRoot);
    $reject(static fn () => writePrivateModuleFileset(
        $fixtureOutputs,
        $fixturePlanPath,
        $fixtureManifest,
        hash_file('sha256', $fixtureManifest),
        $fixtureProducer
    ), 'outside source');
    $assert($inventory($fixture) === $snapshot, 'overlap preserves all input bytes');
    $assert(scandir($fixtureRoot) === $rootEntries, 'overlap creates no parent');
    $assert(
        $parentEntries === null ? !file_exists($parentPath) && !is_link($parentPath) : scandir($parentPath) === $parentEntries,
        'overlap creates no run'
    );
}
$fixturePlan['storage']['root'] = $fixture;
$fixturePlan['storage']['workstream'] = 'inputs-other';
writePackageArtifactFile($root . '/non-overlap.json', packageArtifactJson($fixturePlan));
$control = writePrivateModuleFileset(
    $fixtureOutputs,
    $root . '/non-overlap.json',
    $fixtureManifest,
    hash_file('sha256', $fixtureManifest),
    $fixtureProducer
);
$assert(dirname($control['runDirectory']) === $fixture . '/inputs-other', 'sibling with common prefix admitted');
$assert($inventory($control['outputRoot']) === $fixtureOutputs, 'non-overlap control byte readback');

$collision = $root . '/collision';
createPrivateFilesetDirectory($collision);
writePackageArtifactFile($collision . '/keep', 'sentinel');
$reject(static fn () => createPrivateFilesetDirectory($collision), 'already exists');
$assert(file_get_contents($collision . '/keep') === 'sentinel', 'directory collision preserved');
symlink($collision, $root . '/linked-directory');
$reject(static fn () => createPrivateFilesetDirectory($root . '/linked-directory'), 'already exists');
$oldMask = umask(0277);
try {
    $reject(static fn () => createPrivateFilesetDirectory($root . '/restrictive-mask'), 'not 0700');
} finally {
    umask($oldMask);
}
$assert(is_dir($root . '/restrictive-mask'), 'unsuitable new mode retained without repair');
$assert((fileperms($storage['root']) & 0777) === 0700, 'existing root rights unchanged');

// An overlong new filename triggers an actual write failure after the initial receipt.
$reject(static fn () => $write($outputs + ['dist/symcon/' . str_repeat('a', 260) . '.php' => 'failure']), 'Retained incomplete');
$after = scandir($storage['root'] . '/' . $storage['workstream']);
$created = array_values(array_diff($after, $before));
$assert(count($created) === 1, 'one retained partial run');
$failed = $storage['root'] . '/' . $storage['workstream'] . '/' . $created[0];
$assert(is_file($failed . '/started.local.json') && !file_exists($failed . '/artifact-record.local.json'), 'partial run is not completed');
foreach ($sourceHashes as $path => $hash) {
    $assert(hash_file('sha256', $path) === $hash, 'all inputs unchanged');
}
writePackageArtifactFile($root . '/test-result.local.json', packageArtifactJson([
    'outcome' => 'passed', 'checks' => $checks, 'cleanupPerformed' => false, 'testObjectsRetained' => true,
]));
fwrite(STDOUT, "PASS: {$checks} module fileset storage checks; all fixtures retained.\n");
