<?php

declare(strict_types=1);

function failOwnTracksPositionMapAdapter(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertOwnTracksPositionMapAdapter(bool $condition, string $message): void
{
    if (!$condition) {
        failOwnTracksPositionMapAdapter($message);
    }
}

/** @return array{exitCode:int,stdout:string,stderr:string} */
function runOwnTracksPositionMapAdapterProcess(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        failOwnTracksPositionMapAdapter('Cannot start child process.');
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

function removeOwnTracksPositionMapAdapterTree(string $path): void
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
$adapterPath = $root . '/deployments/symcon/windows/adapters/Invoke-SaefOwnTracksPositionMapModuleAdapter.ps1';
$retentionPath = $root . '/deployments/symcon/windows/adapters/Invoke-SaefOwnTracksPositionMapModuleRetention.ps1';
$policyPath = $root . '/deployments/symcon/windows/adapters/owntracks-position-map-adapter-policy.example.json';
$transactionPath = $root . '/deployments/symcon/windows/adapters/owntracks-position-map-module-transaction.json';
$retentionPlanPath = $root . '/deployments/symcon/windows/adapters/owntracks-position-map-retention-plan.example.json';
$channelPolicyPath = $root . '/deployments/symcon/windows/deployment-channel-policy.example.json';
$builderPath = $root . '/tools/build-symcon-module-deployment-package.php';

$adapter = file_get_contents($adapterPath);
$retention = file_get_contents($retentionPath);
$policy = json_decode((string) file_get_contents($policyPath), true, flags: JSON_THROW_ON_ERROR);
$transaction = json_decode((string) file_get_contents($transactionPath), true, flags: JSON_THROW_ON_ERROR);
$retentionPlan = json_decode((string) file_get_contents($retentionPlanPath), true, flags: JSON_THROW_ON_ERROR);
$channelPolicy = json_decode((string) file_get_contents($channelPolicyPath), true, flags: JSON_THROW_ON_ERROR);

assertOwnTracksPositionMapAdapter(is_string($adapter), 'Adapter source is unreadable.');
assertOwnTracksPositionMapAdapter(is_string($retention), 'Retention source is unreadable.');
assertOwnTracksPositionMapAdapter($policy['adapterProfile'] === 'saef-owntracks-position-map-v1', 'Policy profile differs.');
assertOwnTracksPositionMapAdapter($policy['targetId'] === 'saef-owntracks-position-map', 'Policy target differs.');
assertOwnTracksPositionMapAdapter(
    $policy['libraryGuid'] === '{C1B6C0DD-88B1-4360-95C3-F7F542EBC7DD}'
        && $policy['moduleGuid'] === '{7698CC84-EB18-40EC-B594-F403F50966A1}',
    'OwnTracksPositionMap GUID contract differs.'
);
assertOwnTracksPositionMapAdapter($policy['moduleControlInstanceId'] === 0, 'Example contains a live Module Control ID.');
assertOwnTracksPositionMapAdapter(
    $transaction['ownership']['mode'] === 'exactly-one-positive-module-instance'
        && $transaction['ownership']['repositoryMetadataAllowed'] === false,
    'Ownership contract permits implicit Git adoption.'
);
assertOwnTracksPositionMapAdapter(
    $transaction['state']['candidateFormat'] === 2
        && $transaction['state']['rollbackFormat'] === 2
        && $transaction['state']['formatChangeAllowed'] === false
        && $transaction['state']['rollbackPreparation'] === 'fresh-v2-snapshot-byte-exact-restore',
    'Configuration/state contract differs.'
);
assertOwnTracksPositionMapAdapter(
    $transaction['reload']['method'] === 'MC_ReloadModule'
        && $transaction['reload']['serviceRestartAllowed'] === false,
    'Reload contract is not narrowly targeted.'
);
assertOwnTracksPositionMapAdapter(
    $transaction['retention']['owner'] === 'owntracks-position-map-adapter'
        && $transaction['retention']['genericCleanupAllowed'] === false,
    'Retention ownership differs.'
);
assertOwnTracksPositionMapAdapter($retentionPlan['mode'] === 'plan', 'Example retention plan is not read-only.');
assertOwnTracksPositionMapAdapter(
    ($channelPolicy['standaloneModuleTargets'] ?? null) === [],
    'Repository channel policy unexpectedly activates a standalone-module target.'
);

$requiredAdapterFragments = [
    "[ValidateSet('preflight', 'activate')]",
    "[Threading.Mutex]::new(\$false, [string] \$script:policy.mutexName)",
    "Git-managed module trees cannot be adopted by this adapter.",
    "-Method 'IPS_GetInstanceListByModuleID'",
    "-Method 'IPS_GetConfiguration'",
    "-Method 'IPS_HasChanges'",
    "-Method 'MC_ReloadModule'",
    'Enter-RuntimeQuiescence',
    '[IO.FileShare]::ReadWrite',
    '$stream.Lock(0, 1)',
    'Assert-ZeroActiveLeases',
    'foreach ($clientProperty in $state.clients.PSObject.Properties)',
    'foreach ($selectionProperty in $missState.selections.PSObject.Properties)',
    'Get-RuntimeStateSnapshot',
    'Restore-RuntimeStateSnapshot -Snapshot $script:runtimeStateSnapshot',
    'expectedActivePackageIdentitySha256',
    'expectedConfigurationSha256',
    "instanceIDs.Count -ne 1",
    "[IO.Directory]::Move([string] \$script:policy.activeModulePath, \$script:rollbackPath)",
    "[IO.Directory]::Move(\$script:rollbackPath, [string] \$script:policy.activeModulePath)",
    "Restore-Configurations -Snapshot \$script:snapshot",
    "Wait-Healthy -Snapshot \$script:snapshot",
    "Write-AdapterStatus -Outcome 'manual_recovery_required'",
    "Write-AdapterStatus -Outcome 'rolled_back'",
    "packageIdentitySha256 = \$script:packageIdentitySha256",
];
foreach ($requiredAdapterFragments as $fragment) {
    assertOwnTracksPositionMapAdapter(str_contains($adapter, $fragment), "Adapter fragment is missing: {$fragment}");
}
foreach (['MC_UpdateModule', 'Restart-Service', 'Stop-Service', 'Start-Service', 'Invoke-Expression', 'iex '] as $forbidden) {
    assertOwnTracksPositionMapAdapter(!str_contains($adapter, $forbidden), "Adapter contains forbidden action: {$forbidden}");
}
assertOwnTracksPositionMapAdapter(
    substr_count($adapter, "-Method 'MC_ReloadModule'") === 1,
    'Adapter must have one targeted reload call site.'
);
assertOwnTracksPositionMapAdapter(
    !str_contains($adapter, '.PSObject.Properties.Value'),
    'Adapter must iterate empty JSON maps without strict-mode member enumeration.'
);
assertOwnTracksPositionMapAdapter(
    strpos($adapter, 'Copy-CandidateToTransaction')
        < strpos($adapter, '[IO.Directory]::Move([string] $script:policy.activeModulePath, $script:rollbackPath)'),
    'Rollback package is not prepared before the active path changes.'
);
$rollbackStart = strpos($adapter, 'function Invoke-Rollback');
$mainStart = strpos($adapter, "try {\n    \$script:failureCode = 'contract'");
$rollbackSource = substr($adapter, $rollbackStart, $mainStart - $rollbackStart);
assertOwnTracksPositionMapAdapter(
    strpos($rollbackSource, 'Restore-RuntimeStateSnapshot -Snapshot $script:runtimeStateSnapshot')
        < strpos($rollbackSource, 'Invoke-TargetedReload'),
    'Rollback must restore the fresh state snapshot before reloading the previous package.'
);
assertOwnTracksPositionMapAdapter(
    str_contains(
        $rollbackSource,
        '-ExpectedPackageIdentitySha256 ([string] $script:snapshot.activePackageIdentitySha256)'
    ),
    'Rollback health must be pinned to the previous package identity.'
);

$requiredRetentionFragments = [
    "[ValidateSet('plan', 'apply')]",
    "inventorySha256 = \$inventorySha256",
    'Retention inventory changed after approval.',
    "outcome -eq 'manual_recovery_required'",
    "-not [bool] \$_.protected",
    'Approved artifact is not currently eligible.',
    'Remove-Item -LiteralPath $artifactPath -Recurse -Force',
];
foreach ($requiredRetentionFragments as $fragment) {
    assertOwnTracksPositionMapAdapter(str_contains($retention, $fragment), "Retention fragment is missing: {$fragment}");
}
assertOwnTracksPositionMapAdapter(
    substr_count($retention, 'Remove-Item -LiteralPath $artifactPath -Recurse -Force') === 1,
    'Retention must have exactly one artifact deletion call site.'
);

$temporaryRoot = sys_get_temp_dir() . '/saef-owntracks-position-map-adapter-' . bin2hex(random_bytes(8));
mkdir($temporaryRoot, 0700, true);
try {
    $planPath = $temporaryRoot . '/plan.json';
    $outputPath = $temporaryRoot . '/candidate.zip';
    file_put_contents(
        $planPath,
        json_encode(
            [
                'formatVersion' => 1,
                'deploymentId' => 'saef-owntracks-position-map-adapter-test',
                'targetDirectoryName' => 'saef-owntracks-position-map-adapter-test-module',
                'modulePath' => $root . '/dist/symcon/saef-owntracks-position-map-module',
                'moduleTargetId' => 'saef-owntracks-position-map',
                'libraryGuid' => $policy['libraryGuid'],
                'transactionContractPath' => $transactionPath,
                'outputPath' => $outputPath,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    $first = runOwnTracksPositionMapAdapterProcess([PHP_BINARY, $builderPath, $planPath]);
    assertOwnTracksPositionMapAdapter($first['exitCode'] === 0, 'Concrete module package build failed.');
    $build = json_decode($first['stdout'], true, flags: JSON_THROW_ON_ERROR);
    assertOwnTracksPositionMapAdapter(
        $build['moduleTargetId'] === 'saef-owntracks-position-map'
            && preg_match('/^[a-f0-9]{64}$/D', $build['packageIdentitySha256']) === 1,
        'Concrete package identity contract differs.'
    );
    $archive = new ZipArchive();
    assertOwnTracksPositionMapAdapter($archive->open($outputPath) === true, 'Cannot inspect concrete package.');
    $packagedTransaction = $archive->getFromName('module-transaction.json');
    $archive->close();
    assertOwnTracksPositionMapAdapter(
        $packagedTransaction === json_encode(
            $transaction,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n",
        'Packaged adapter transaction is not canonical.'
    );
} finally {
    removeOwnTracksPositionMapAdapterTree($temporaryRoot);
}

fwrite(STDOUT, "PASS: OwnTracksPositionMap standalone module adapter contract\n");
