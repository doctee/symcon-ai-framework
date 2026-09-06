<?php

declare(strict_types=1);

function failOwnTracksActiveIdentityReseal(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertOwnTracksActiveIdentityReseal(bool $condition, string $message): void
{
    if (!$condition) {
        failOwnTracksActiveIdentityReseal($message);
    }
}

$root = dirname(__DIR__, 2);
$resealPath = $root
    . '/deployments/symcon/windows/adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1';
$adapterPath = $root
    . '/deployments/symcon/windows/adapters/Invoke-SaefOwnTracksPositionMapModuleAdapter.ps1';
$reseal = file_get_contents($resealPath);
$adapter = file_get_contents($adapterPath);

assertOwnTracksActiveIdentityReseal(is_string($reseal), 'Active-identity reseal command is unreadable.');
assertOwnTracksActiveIdentityReseal(is_string($adapter), 'OwnTracks module adapter is unreadable.');

foreach (
    [
        "[ValidateSet('preflight', 'apply')]",
        "'reseal-saef-owntracks-position-map-active-identity'",
        "'Global\\SAEF.DeploymentChannel'",
        '[Threading.Mutex]::new($false, [string] $adapterPolicy.mutexName)',
        'ExpectedPreviousPackageIdentitySha256',
        'ExpectedActivePackageIdentitySha256',
        'ExpectedActiveDeploymentId',
        'Status path overlaps the installed channel policy.',
        'Status path overlaps the installed adapter policy.',
        'OwnTracks adapter state must use a target-owned child root.',
        'Status path is inside a managed channel or module root.',
        'Assert-NoBroadWriteAcl -Path ([string] $adapterPolicy.activeModulePath)',
        'Read-BoundedJson -Path $activeStatePath',
        'Completed activation transaction differs.',
        'Channel activation evidence differs.',
        "[string] \$manifest.targetDirectoryName -cne (\$ExpectedActiveDeploymentId + '-module')",
        'Get-DirectoryPackageIdentity -Path ([string] $adapterPolicy.activeModulePath)',
        "Get-DirectoryPackageIdentity -Path (Join-Path \$transactionRoot 'rollback')",
        '$adapterPolicy.expectedActivePackageIdentitySha256 = $ExpectedActivePackageIdentitySha256',
        '$target.expectedAdapterPolicySha256 = $script:proposedAdapterPolicySha256',
        'Write-AtomicBytes -Path $script:adapterPolicyPath -Bytes $candidateAdapterBytes',
        'Write-AtomicBytes -Path $ChannelPolicyPath -Bytes $candidateChannelBytes',
        "\$script:finalOutcome = 'resealed'",
        "\$script:finalOutcome = 'rolled_back'",
        "\$script:finalOutcome = 'manual_recovery_required'",
        'failureDetail = $script:failureDetail',
        'failureType = $script:failureType',
        'failureHResult = $script:failureHResult',
        'moduleReloadAttempted = $false',
        'moduleActivationAttempted = $false',
        'symconRpcContactAttempted = $false',
        'sshdRestartAttempted = $false',
        'providerContactAttempted = $false',
        'publicationAttempted = $false',
        'cleanupAttempted = $false',
        'exit $script:finalExitCode',
    ] as $fragment
) {
    assertOwnTracksActiveIdentityReseal(
        str_contains($reseal, $fragment),
        "Active-identity reseal fragment is missing: {$fragment}"
    );
}

foreach (
    [
        'Restart-Service',
        'Stop-Service',
        'Start-Service',
        'MC_ReloadModule',
        'IPS_',
        'Invoke-RestMethod',
        'Invoke-WebRequest',
        'Invoke-Expression',
        '[IO.Directory]::Move(',
        'Remove-Item -Recurse',
    ] as $forbidden
) {
    assertOwnTracksActiveIdentityReseal(
        !str_contains($reseal, $forbidden),
        "Active-identity reseal contains forbidden action: {$forbidden}"
    );
}

assertOwnTracksActiveIdentityReseal(
    strpos($reseal, "[Threading.Mutex]::new(\$false, 'Global\\SAEF.DeploymentChannel')")
        < strpos($reseal, '[Threading.Mutex]::new($false, [string] $adapterPolicy.mutexName)'),
    'Reseal does not acquire channel and adapter mutexes in channel order.'
);
assertOwnTracksActiveIdentityReseal(
    substr_count(
        $reseal,
        '$adapterPolicy.expectedActivePackageIdentitySha256 = $ExpectedActivePackageIdentitySha256'
    ) === 1,
    'Reseal must have exactly one active-identity policy mutation.'
);
assertOwnTracksActiveIdentityReseal(
    substr_count($reseal, 'exit $script:finalExitCode') === 1,
    'Reseal must have one native exit boundary.'
);
assertOwnTracksActiveIdentityReseal(
    str_contains(
        $adapter,
        '$activePackageIdentity -ne [string] $script:policy.expectedActivePackageIdentitySha256'
    ),
    'Module adapter no longer enforces the administratively pinned active-package identity.'
);

fwrite(STDOUT, "PASS: OwnTracksPositionMap active-identity reseal contract\n");
