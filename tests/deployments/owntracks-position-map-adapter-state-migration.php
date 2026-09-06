<?php

declare(strict_types=1);

function failOwnTracksAdapterStateMigration(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertOwnTracksAdapterStateMigration(bool $condition, string $message): void
{
    if (!$condition) {
        failOwnTracksAdapterStateMigration($message);
    }
}

$root = dirname(__DIR__, 2);
$migrationPath = $root
    . '/deployments/symcon/windows/adapters/Invoke-SaefOwnTracksPositionMapAdapterStateMigration.ps1';
$initializerPath = $root . '/deployments/symcon/windows/Initialize-SaefDeploymentChannel.ps1';
$gatewayPath = $root . '/deployments/symcon/windows/Invoke-SaefDeploymentGateway.ps1';
$migration = file_get_contents($migrationPath);
$initializer = file_get_contents($initializerPath);
$gateway = file_get_contents($gatewayPath);

assertOwnTracksAdapterStateMigration(is_string($migration), 'Adapter-state migration is unreadable.');
assertOwnTracksAdapterStateMigration(is_string($initializer), 'Channel initializer is unreadable.');
assertOwnTracksAdapterStateMigration(is_string($gateway), 'Deployment gateway is unreadable.');

foreach (
    [
        "[ValidateSet('preflight', 'apply')]",
        "'migrate-saef-owntracks-position-map-adapter-state'",
        "'Global\\SAEF.DeploymentChannel'",
        '[Threading.Mutex]::new($false, [string] $adapterPolicy.mutexName)',
        "'owntracks-position-map-adapter'",
        "'owntracks-position-map'",
        'Adapter-state migration path contract is invalid.',
        'Get-StateTreeIdentity',
        'Assert-GenericDeploymentInventory',
        'Configured deployment count leaves no post-migration staging capacity.',
        '[IO.Directory]::Move($script:sourceRoot, $script:destinationRoot)',
        '$adapterPolicy.adapterStateRoot = $script:destinationRoot',
        '$target.expectedAdapterPolicySha256 = $script:proposedAdapterPolicySha256',
        'Write-AtomicBytes -Path $script:adapterPolicyPath -Bytes $candidateAdapterBytes',
        'Write-AtomicBytes -Path $ChannelPolicyPath -Bytes $candidateChannelBytes',
        '[IO.Directory]::Move($script:destinationRoot, $script:sourceRoot)',
        "\$script:finalOutcome = 'rolled_back'",
        "\$script:finalOutcome = 'manual_recovery_required'",
        'moduleReloadAttempted = $false',
        'moduleActivationAttempted = $false',
        'symconRpcContactAttempted = $false',
        'providerContactAttempted = $false',
        'publicationAttempted = $false',
        'cleanupAttempted = $false',
        'exit $script:finalExitCode',
    ] as $fragment
) {
    assertOwnTracksAdapterStateMigration(
        str_contains($migration, $fragment),
        "Adapter-state migration fragment is missing: {$fragment}"
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
        'Remove-Item -LiteralPath $script:sourceRoot -Recurse',
        'Remove-Item -LiteralPath $script:destinationRoot -Recurse',
    ] as $forbidden
) {
    assertOwnTracksAdapterStateMigration(
        !str_contains($migration, $forbidden),
        "Adapter-state migration contains forbidden action: {$forbidden}"
    );
}

assertOwnTracksAdapterStateMigration(
    strpos($migration, "[Threading.Mutex]::new(\$false, 'Global\\SAEF.DeploymentChannel')")
        < strpos($migration, '[Threading.Mutex]::new($false, [string] $adapterPolicy.mutexName)'),
    'Migration does not acquire channel and adapter mutexes in channel order.'
);
assertOwnTracksAdapterStateMigration(
    substr_count($migration, '[IO.Directory]::Move(') === 2,
    'Migration must have exactly one apply move and one rollback move.'
);
assertOwnTracksAdapterStateMigration(
    substr_count($migration, 'exit $script:finalExitCode') === 1,
    'Migration must have one native exit boundary.'
);
assertOwnTracksAdapterStateMigration(
    str_contains($initializer, "Join-Path \$SymconScriptsRoot '.saef-adapter-states'")
        && str_contains($initializer, 'maxDeploymentCount = $MaxDeploymentCount')
        && str_contains($gateway, "'adapterStateRoot'"),
    'Channel does not expose the separated adapter-state and bounded-capacity contract.'
);

fwrite(STDOUT, "PASS: OwnTracksPositionMap adapter-state migration contract\n");
