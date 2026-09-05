<?php

declare(strict_types=1);

function failOwnTracksAdapterState(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertOwnTracksAdapterState(bool $condition, string $message): void
{
    if (!$condition) {
        failOwnTracksAdapterState($message);
    }
}

$root = dirname(__DIR__, 2);
$initializerPath = $root
    . '/deployments/symcon/windows/adapters/Initialize-SaefOwnTracksPositionMapAdapterState.ps1';
$channelInitializerPath = $root . '/deployments/symcon/windows/Initialize-SaefDeploymentChannel.ps1';
$initializer = file_get_contents($initializerPath);
$channelInitializer = file_get_contents($channelInitializerPath);

assertOwnTracksAdapterState(is_string($initializer), 'Adapter-state initializer is unreadable.');
assertOwnTracksAdapterState(is_string($channelInitializer), 'Channel initializer is unreadable.');

$requiredFragments = [
    "[ValidateSet('preflight', 'install')]",
    "[ValidatePattern('^[a-f0-9]{64}$')]",
    "[ValidatePattern('^[A-Za-z0-9_.-]{1,64}$')]",
    "'provision-saef-owntracks-position-map-adapter-state'",
    "[string] \$script:policy.adapterProfile -cne 'saef-owntracks-position-map-v1'",
    "[string] \$script:policy.targetId -cne 'saef-owntracks-position-map'",
    'Adapter policy hash differs.',
    'Adapter state-root path boundary is invalid.',
    'Assert-PlainAncestorChain -Path $stateParent',
    "Get-LocalUser -Name \$DeploymentUser",
    "Get-LocalGroup -SID 'S-1-5-32-544'",
    '[Threading.Mutex]::new($false, [string] $script:policy.mutexName)',
    "\$Operation -eq 'preflight'",
    '[IO.Directory]::CreateDirectory($script:stateRoot)',
    "& icacls.exe \$Path '/inheritance:r'",
    "'*S-1-5-18:(OI)(CI)F'",
    "'*S-1-5-32-544:(OI)(CI)F'",
    "Assert-ProtectedAcl -Path \$script:stateRoot -DeploymentSid \$deploymentSid",
    'Remove-Item -LiteralPath $script:stateRoot -Force',
    "\$script:finalOutcome = 'rolled_back'",
    "\$script:finalOutcome = 'manual_recovery_required'",
    'activeModuleMutationAttempted = $false',
    'installedChannelMutationAttempted = $false',
    'targetAllowlistMutationAttempted = $false',
    'modulePreflightAttempted = $false',
    'moduleActivationAttempted = $false',
    'symconRpcContactAttempted = $false',
    'providerContactAttempted = $false',
    'publicationAttempted = $false',
    'exit $script:finalExitCode',
];
foreach ($requiredFragments as $fragment) {
    assertOwnTracksAdapterState(
        str_contains($initializer, $fragment),
        "Adapter-state initializer fragment is missing: {$fragment}"
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
        'iex ',
        'Remove-Item -LiteralPath $script:stateRoot -Recurse',
    ] as $forbidden
) {
    assertOwnTracksAdapterState(
        !str_contains($initializer, $forbidden),
        "Adapter-state initializer contains forbidden action: {$forbidden}"
    );
}

assertOwnTracksAdapterState(
    substr_count($initializer, '[IO.Directory]::CreateDirectory($script:stateRoot)') === 1,
    'Adapter state-root must have exactly one creation call site.'
);
assertOwnTracksAdapterState(
    substr_count($initializer, 'Remove-Item -LiteralPath $script:stateRoot -Force') === 1,
    'Adapter state-root must have exactly one non-recursive rollback-removal call site.'
);
assertOwnTracksAdapterState(
    is_int($preflightBranch = strpos($initializer, "} elseif (\$Operation -eq 'preflight') {"))
        && is_int($creationCall = strpos($initializer, '[IO.Directory]::CreateDirectory($script:stateRoot)'))
        && $preflightBranch < $creationCall,
    'Preflight branch does not guard the state-root creation call site.'
);
assertOwnTracksAdapterState(
    str_contains($initializer, "& icacls.exe \$Path '/inheritance:r'")
        && str_contains($channelInitializer, "& icacls.exe \$Path '/inheritance:r'"),
    'Adapter-state initializer does not reuse the channel ACL inheritance primitive.'
);
assertOwnTracksAdapterState(
    str_contains($initializer, "'*S-1-5-18:(OI)(CI)F'")
        && str_contains($channelInitializer, "'*S-1-5-18:(OI)(CI)F'"),
    'Adapter-state initializer does not reuse the channel SYSTEM ACL rule.'
);
assertOwnTracksAdapterState(
    str_contains($initializer, "'*S-1-5-32-544:(OI)(CI)F'")
        && str_contains($channelInitializer, "'*S-1-5-32-544:(OI)(CI)F'"),
    'Adapter-state initializer does not reuse the channel Administrators ACL rule.'
);
assertOwnTracksAdapterState(
    !str_contains($initializer, '[Security.AccessControl.FileSystemRights]::Modify -bor')
        && substr_count($initializer, 'exit $script:finalExitCode') === 1,
    'Adapter-state initializer has an unsafe ACL mask or multiple native exits.'
);

fwrite(STDOUT, "PASS: OwnTracksPositionMap adapter-state initializer contract\n");
