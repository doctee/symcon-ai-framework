<?php

declare(strict_types=1);

function failMediaCarouselAdapter(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertMediaCarouselAdapter(bool $condition, string $message): void
{
    if (!$condition) {
        failMediaCarouselAdapter($message);
    }
}

/** @return array{exitCode:int,stdout:string,stderr:string} */
function runMediaCarouselAdapterProcess(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        failMediaCarouselAdapter('Cannot start child process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exitCode' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

function removeMediaCarouselAdapterTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

$root = dirname(__DIR__, 2);
$adapterPath = $root . '/deployments/symcon/windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1';
$policyPath = $root . '/deployments/symcon/windows/adapters/media-carousel-adapter-policy.example.json';
$transactionPath = $root . '/deployments/symcon/windows/adapters/media-carousel-module-transaction.json';
$deploymentPlanPath = $root . '/deployments/symcon/windows/adapters/media-carousel-deployment-plan.example.json';
$channelPolicyPath = $root . '/deployments/symcon/windows/deployment-channel-policy.example.json';
$builderPath = $root . '/tools/build-symcon-module-deployment-package.php';

$adapter = file_get_contents($adapterPath);
$policy = json_decode((string) file_get_contents($policyPath), true, flags: JSON_THROW_ON_ERROR);
$transaction = json_decode((string) file_get_contents($transactionPath), true, flags: JSON_THROW_ON_ERROR);
$deploymentPlan = json_decode((string) file_get_contents($deploymentPlanPath), true, flags: JSON_THROW_ON_ERROR);
$channelPolicy = json_decode((string) file_get_contents($channelPolicyPath), true, flags: JSON_THROW_ON_ERROR);

assertMediaCarouselAdapter(is_string($adapter), 'Adapter source is unreadable.');
assertMediaCarouselAdapter($policy['adapterProfile'] === 'saef-media-carousel-v1', 'Policy profile differs.');
assertMediaCarouselAdapter($policy['targetId'] === 'saef-media-carousel', 'Policy target differs.');
assertMediaCarouselAdapter(
    $policy['libraryGuid'] === '{8D263598-06EF-4440-982C-9E86E3F8D130}'
        && $policy['moduleGuid'] === '{41D0C5ED-8331-4B26-A44E-6FDCEC1BC41F}',
    'MediaCarousel GUID contract differs.'
);
assertMediaCarouselAdapter($policy['moduleControlInstanceId'] === 0, 'Example contains a live Module Control ID.');
assertMediaCarouselAdapter(
    $policy['moduleControlModuleGuid'] === '{00000000-0000-0000-0000-000000000000}',
    'Example unexpectedly assumes a live Module Control module identity.'
);
assertMediaCarouselAdapter(
    $policy['moduleType'] === 3 && $policy['modulePrefix'] === 'SAEFMC',
    'MediaCarousel module metadata contract differs.'
);
assertMediaCarouselAdapter(
    $transaction['ownership']['mode'] === 'adapter-owned-package-directory'
        && $transaction['ownership']['repositoryMetadataAllowed'] === false,
    'Ownership contract permits implicit Git adoption.'
);
assertMediaCarouselAdapter(
    $transaction['state']['authoritative'] === 'symcon-instance-configuration'
        && $transaction['state']['rollbackPreparation'] === 'fresh-snapshot-no-conversion',
    'Configuration/state contract differs.'
);
assertMediaCarouselAdapter(
    $transaction['reload']['method'] === 'MC_ReloadModule'
        && $transaction['reload']['serviceRestartAllowed'] === false,
    'Reload contract is not narrowly targeted.'
);
assertMediaCarouselAdapter(
    $transaction['retention']['owner'] === 'channel-v8-cross-root-contract'
        && $transaction['retention']['implemented'] === false
        && $transaction['retention']['genericCleanupAllowed'] === false,
    'Retention ownership differs.'
);
assertMediaCarouselAdapter(
    $policy['expectedInstances'][0]['instanceId'] === 0
        && $policy['expectedActivePackageIdentitySha256']
            === '<private-sha256-of-active-module-tree>',
    'Example policy does not expose non-runnable exact baseline bindings.'
);
assertMediaCarouselAdapter(
    $deploymentPlan['moduleTargetId'] === 'saef-media-carousel'
        && $deploymentPlan['transactionContractPath'] === 'media-carousel-module-transaction.json',
    'Concrete deployment plan is not bound to the MediaCarousel transaction.'
);
assertMediaCarouselAdapter(
    ($channelPolicy['standaloneModuleTargets'] ?? null) === [],
    'Repository channel policy unexpectedly activates a standalone-module target.'
);

$requiredAdapterFragments = [
    "[ValidateSet('preflight', 'activate')]",
    "[Threading.Mutex]::new(\$false, [string] \$script:policy.mutexName)",
    '[Array]::Sort($relativePaths, [StringComparer]::Ordinal)',
    '[Array]::Sort($orderedInstanceIDs)',
    'expectedActivePackageIdentitySha256',
    'expectedInstances',
    "Git-managed module trees cannot be adopted by this adapter.",
    "-Method 'IPS_GetInstanceListByModuleID'",
    "-Method 'IPS_GetConfiguration'",
    "-Method 'IPS_HasChanges'",
    "-Method 'IPS_FunctionExists' -Parameters @('MC_ReloadModule')",
    "-Method 'MC_ReloadModule'",
    "[IO.Directory]::Move([string] \$script:policy.activeModulePath, \$script:rollbackPath)",
    "[IO.Directory]::Move(\$script:rollbackPath, [string] \$script:policy.activeModulePath)",
    "Restore-Configurations -Snapshot \$script:snapshot",
    "Wait-Healthy -Snapshot \$script:snapshot",
    "\$script:failureCode = 'pre_mutation_recheck'",
    'Active package identity drifted before mutation.',
    "Write-AdapterStatus -Outcome 'manual_recovery_required'",
    "Write-AdapterStatus -Outcome 'rolled_back'",
    "packageIdentitySha256 = \$script:packageIdentitySha256",
    'Fresh configuration snapshot exceeds the adapter state limit.',
];
foreach ($requiredAdapterFragments as $fragment) {
    assertMediaCarouselAdapter(str_contains($adapter, $fragment), "Adapter fragment is missing: {$fragment}");
}
foreach (['MC_UpdateModule', 'Restart-Service', 'Stop-Service', 'Start-Service', 'Invoke-Expression', 'iex '] as $forbidden) {
    assertMediaCarouselAdapter(!str_contains($adapter, $forbidden), "Adapter contains forbidden action: {$forbidden}");
}
assertMediaCarouselAdapter(
    !str_contains($adapter, 'Sort-Object'),
    'Adapter identity or baseline ordering must not depend on Sort-Object.'
);
assertMediaCarouselAdapter(
    substr_count($adapter, "-Method 'MC_ReloadModule'") === 1,
    'Adapter must have one targeted reload call site.'
);
assertMediaCarouselAdapter(
    strpos($adapter, 'Copy-CandidateToTransaction -Destination $candidateTransactionPath')
        < strpos($adapter, '[IO.Directory]::Move([string] $script:policy.activeModulePath, $script:rollbackPath)'),
    'Rollback package is not prepared before the active path changes.'
);
assertMediaCarouselAdapter(
    strpos($adapter, "\$script:failureCode = 'pre_mutation_recheck'")
        < strpos($adapter, '[IO.Directory]::Move([string] $script:policy.activeModulePath, $script:rollbackPath)'),
    'Fresh baseline is not rechecked immediately before the active path changes.'
);

$temporaryRoot = sys_get_temp_dir() . '/saef-media-carousel-adapter-' . bin2hex(random_bytes(8));
mkdir($temporaryRoot, 0700, true);
try {
    $planPath = $temporaryRoot . '/plan.json';
    $outputPath = $temporaryRoot . '/candidate.zip';
    file_put_contents(
        $planPath,
        json_encode(
            [
                'formatVersion' => 1,
                'deploymentId' => 'saef-media-carousel-adapter-test',
                'targetDirectoryName' => 'saef-media-carousel-adapter-test-module',
                'modulePath' => $root . '/dist/symcon/saef-media-carousel-module',
                'moduleTargetId' => 'saef-media-carousel',
                'libraryGuid' => $policy['libraryGuid'],
                'transactionContractPath' => $transactionPath,
                'outputPath' => $outputPath,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    $first = runMediaCarouselAdapterProcess([PHP_BINARY, $builderPath, $planPath]);
    assertMediaCarouselAdapter($first['exitCode'] === 0, 'Concrete module package build failed.');
    $build = json_decode($first['stdout'], true, flags: JSON_THROW_ON_ERROR);
    assertMediaCarouselAdapter(
        $build['moduleTargetId'] === 'saef-media-carousel'
            && preg_match('/^[a-f0-9]{64}$/D', $build['packageIdentitySha256']) === 1,
        'Concrete package identity contract differs.'
    );
    $archive = new ZipArchive();
    assertMediaCarouselAdapter($archive->open($outputPath) === true, 'Cannot inspect concrete package.');
    $packagedTransaction = $archive->getFromName('module-transaction.json');
    $archive->close();
    assertMediaCarouselAdapter(
        $packagedTransaction === json_encode(
            $transaction,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n",
        'Packaged adapter transaction is not canonical.'
    );
} finally {
    removeMediaCarouselAdapterTree($temporaryRoot);
}

fwrite(STDOUT, "PASS: MediaCarousel standalone module adapter contract\n");
