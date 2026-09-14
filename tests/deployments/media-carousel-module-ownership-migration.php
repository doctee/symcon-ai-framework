<?php

declare(strict_types=1);

function failMediaCarouselOwnershipMigration(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertMediaCarouselOwnershipMigration(bool $condition, string $message): void
{
    if (!$condition) {
        failMediaCarouselOwnershipMigration($message);
    }
}

$root = dirname(__DIR__, 2);
$scriptPath = $root
    . '/deployments/symcon/windows/adapters/Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1';
$policyPath = $root
    . '/deployments/symcon/windows/adapters/media-carousel-ownership-migration-policy.example.json';
$transactionPath = $root
    . '/deployments/symcon/windows/adapters/media-carousel-ownership-migration-transaction.json';

$script = file_get_contents($scriptPath);
$policy = json_decode((string) file_get_contents($policyPath), true, flags: JSON_THROW_ON_ERROR);
$transaction = json_decode((string) file_get_contents($transactionPath), true, flags: JSON_THROW_ON_ERROR);

assertMediaCarouselOwnershipMigration(is_string($script), 'Migration script is unreadable.');
assertMediaCarouselOwnershipMigration(
    $policy['migrationProfile'] === 'saef-media-carousel-package-ownership-v1'
        && $policy['targetId'] === 'saef-media-carousel'
        && $policy['adapterProfile'] === 'saef-media-carousel-v1',
    'Migration policy identity differs.'
);
assertMediaCarouselOwnershipMigration(
    $policy['moduleControlInstanceId'] === 0
        && $policy['moduleControlModuleGuid'] === '{00000000-0000-0000-0000-000000000000}'
        && $policy['expectedInstances'][0]['instanceId'] === 0,
    'Example policy contains a live Symcon identity.'
);
assertMediaCarouselOwnershipMigration(
    str_starts_with($policy['loaderRoot'], '<private-absolute-')
        && str_starts_with($policy['activeModulePath'], '<private-absolute-')
        && str_starts_with($policy['candidateModulePath'], '<private-absolute-')
        && str_starts_with($policy['migrationStateRoot'], '<private-absolute-'),
    'Example policy paths are unexpectedly runnable.'
);
assertMediaCarouselOwnershipMigration(
    $policy['expectedSourceCommit'] === '<private-full-lowercase-source-commit>'
        && $policy['expectedImplementationSha256']
            === '<private-sha256-of-exact-migration-script>',
    'Example source or implementation binding is unexpectedly concrete.'
);

assertMediaCarouselOwnershipMigration(
    $transaction['source']['owner'] === 'module-control-git-checkout'
        && $transaction['source']['repositoryMetadataRequired'] === true
        && $transaction['source']['preserveByteExactRollback'] === true,
    'Source ownership contract differs.'
);
assertMediaCarouselOwnershipMigration(
    $transaction['candidate']['owner'] === 'channel-v8-adapter-package'
        && $transaction['candidate']['repositoryMetadataAllowed'] === false
        && $transaction['candidate']['requireManifestIdentity'] === true,
    'Candidate ownership contract differs.'
);
assertMediaCarouselOwnershipMigration(
    $transaction['locks']['order'] === ['channel', 'adapter']
        && $transaction['locks']['waitMilliseconds'] === 0,
    'Migration lock contract differs.'
);
assertMediaCarouselOwnershipMigration(
    $transaction['approval']['planHashRequired'] === true
        && $transaction['approval']['hostAndUserBindingRequired'] === true
        && $transaction['approval']['nonceClaimMode'] === 'create-new-file'
        && $transaction['approval']['expiredPlanAllowedForApply'] === false,
    'Migration approval contract differs.'
);
assertMediaCarouselOwnershipMigration(
    $transaction['operationSequence'] === [
        'persist-prepared-state',
        'fresh-pre-mutation-recheck',
        'move-source-to-rollback',
        'move-candidate-to-active',
        'targeted-module-reload',
        'independent-postflight',
    ],
    'Migration operation sequence differs.'
);
assertMediaCarouselOwnershipMigration(
    $transaction['switch']['mode'] === 'same-volume-directory-move'
        && $transaction['switch']['activeDirectoryNamePreserved'] === true
        && $transaction['switch']['maximumReloadCountPerDirection'] === 1,
    'Migration switch contract differs.'
);
assertMediaCarouselOwnershipMigration(
    $transaction['rollback']['automaticAfterSourceMove'] === true
        && $transaction['rollback']['retainFailedCandidate'] === true
        && $transaction['rollback']['requireSourceAndRuntimePostflight'] === true
        && $transaction['rollback']['manualRecoveryOnUnprovenRestore'] === true,
    'Migration rollback contract differs.'
);
assertMediaCarouselOwnershipMigration(
    !in_array(false, $transaction['forbidden'], true),
    'Migration transaction permits a forbidden side effect.'
);

$requiredFragments = [
    "[ValidateSet('preflight', 'apply', 'inspect', 'rollback')]",
    "return \$Value -cmatch '^(?:[a-f0-9]{40}|[a-f0-9]{64})$'",
    '[Array]::Sort($ordered, [StringComparer]::Ordinal)',
    'Candidate contains a reparse point.',
    "Join-Path \$Root '.git'",
    "Join-Path \$gitRoot 'packed-refs'",
    "'remote \"origin\"'",
    'expectedSourceTreeSha256',
    'expectedSourceAclSha256',
    'expectedCandidatePackageIdentitySha256',
    'expectedImplementationSha256',
    "adapterProfile = [string] \$script:policy.adapterProfile",
    "allowedOperation = 'apply'",
    'sourceOwner = [string] $script:transaction.source.owner',
    'candidateOwner = [string] $script:transaction.candidate.owner',
    '[Security.Principal.WindowsIdentity]::GetCurrent().User.Value',
    '[Environment]::MachineName',
    '[IO.FileMode]::CreateNew',
    "[Threading.Mutex]::new(\$false, [string] \$script:policy.channelMutexName)",
    "[Threading.Mutex]::new(\$false, [string] \$script:policy.adapterMutexName)",
    "\$script:failureCode = 'pre_mutation_recheck'",
    "Write-TransactionState -Outcome 'source_moved'",
    'Get-SourceIdentity -Root $script:rollbackPath',
    'Get-CandidateIdentity -Root $script:candidateStagingPath',
    "-Method 'IPS_GetReferenceList'",
    "-Method 'IPS_SetConfiguration'",
    "-Method 'IPS_ApplyChanges'",
    "Write-TransactionState -Outcome 'migrated'",
    "Write-TransactionState -Outcome 'rolled_back'",
    "Write-TransactionState -Outcome 'manual_recovery_required'",
    "'Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1'",
    'serviceRestartAttempted = $false',
    'providerContactAttempted = $false',
    'publicationAttempted = $false',
    'retentionCleanupAttempted = $false',
];
foreach ($requiredFragments as $fragment) {
    assertMediaCarouselOwnershipMigration(
        str_contains($script, $fragment),
        "Migration fragment is missing: {$fragment}"
    );
}

$forbiddenFragments = [
    'MC_UpdateModule',
    'Restart-Service',
    'Stop-Service',
    'Start-Service',
    'Restart-Computer',
    'Invoke-Expression',
    'iex ',
    'git.exe',
    '& git ',
    'Start-Process',
    'Invoke-WebRequest',
];
foreach ($forbiddenFragments as $fragment) {
    assertMediaCarouselOwnershipMigration(
        !str_contains($script, $fragment),
        "Migration contains forbidden action: {$fragment}"
    );
}
assertMediaCarouselOwnershipMigration(
    !str_contains($script, 'Sort-Object'),
    'Migration identity ordering must not depend on Sort-Object.'
);
assertMediaCarouselOwnershipMigration(
    substr_count($script, "-Method 'MC_ReloadModule'") === 1,
    'Migration must expose one targeted reload call site.'
);

$channelLock = strpos($script, '$script:channelMutex.WaitOne(0)');
$adapterLock = strpos($script, '$script:adapterMutex.WaitOne(0)');
$freshRecheck = strpos($script, '$script:failureCode = \'pre_mutation_recheck\'');
$sourceMove = strpos(
    $script,
    '[IO.Directory]::Move([string] $script:policy.activeModulePath, $script:rollbackPath)'
);
$movedSourceCheck = strpos($script, '$movedSource = Get-SourceIdentity -Root $script:rollbackPath');
$candidateMove = strpos(
    $script,
    '[IO.Directory]::Move($script:candidateStagingPath, [string] $script:policy.activeModulePath)'
);
$targetedReload = strpos($script, "\$script:failureCode = 'targeted_reload'");
$postflight = strpos($script, "\$script:failureCode = 'postflight'");

foreach (
    [$channelLock, $adapterLock, $freshRecheck, $sourceMove, $movedSourceCheck, $candidateMove,
        $targetedReload, $postflight] as $position
) {
    assertMediaCarouselOwnershipMigration($position !== false, 'Migration ordering marker is missing.');
}
assertMediaCarouselOwnershipMigration(
    $channelLock < $adapterLock,
    'Adapter mutex is acquired before the channel mutex.'
);
assertMediaCarouselOwnershipMigration(
    $freshRecheck < $sourceMove
        && $sourceMove < $movedSourceCheck
        && $movedSourceCheck < $candidateMove
        && $candidateMove < $targetedReload
        && $targetedReload < $postflight,
    'Migration mutation or postflight order differs from its contract.'
);

$publicArtifacts = $script
    . file_get_contents($policyPath)
    . file_get_contents($transactionPath);
foreach (['/Users/', 'C:\\Users\\', 'SmartHome24', 'carsten'] as $privateMarker) {
    assertMediaCarouselOwnershipMigration(
        !str_contains($publicArtifacts, $privateMarker),
        "Public migration artifact contains private marker: {$privateMarker}"
    );
}

fwrite(STDOUT, "PASS: MediaCarousel package ownership migration contract\n");
