<?php

declare(strict_types=1);

function failScopeBoundApprovalRunner(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertScopeBoundApprovalRunner(bool $condition, string $message): void
{
    if (!$condition) {
        failScopeBoundApprovalRunner($message);
    }
}

$root = dirname(__DIR__, 2);
$windowsRoot = $root . '/deployments/symcon/windows';
$runner = file_get_contents($windowsRoot . '/Invoke-SaefScopeBoundApprovalRunner.ps1');
$gateway = file_get_contents($windowsRoot . '/Invoke-SaefDeploymentGateway.ps1');
$reseal = file_get_contents(
    $windowsRoot . '/adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1'
);
$policy = json_decode(
    (string) file_get_contents(
        $windowsRoot . '/adapters/owntracks-position-map-approval-policy.example.json'
    ),
    true,
    flags: JSON_THROW_ON_ERROR
);

assertScopeBoundApprovalRunner(is_string($runner), 'Approval runner is unreadable.');
assertScopeBoundApprovalRunner(is_string($gateway), 'Deployment gateway is unreadable.');
assertScopeBoundApprovalRunner(is_string($reseal), 'OwnTracks reseal is unreadable.');
assertScopeBoundApprovalRunner(is_array($policy), 'Approval policy example is invalid.');

foreach (
    [
        "'Global\\SAEF.DeploymentApproval'",
        'ApprovalEnvelopeBase64Url',
        'ConvertTo-CanonicalJson',
        '$Value -is [Collections.IDictionary]',
        'ConvertTo-CanonicalValue -Value $dictionary[$name]',
        'ConvertTo-CanonicalValue -Value $property.Value',
        'Get-HmacSha256',
        'Test-FixedTimeTextEquals',
        'Assert-ExactProperties',
        'maximumStateFiles',
        'Approval state capacity is exhausted.',
        'Approval proof has already reached a terminal outcome.',
        'Approval plan was claimed by another proof.',
        'Set-FailureDiagnostics -ErrorRecord $_',
        "\$script:failureCode = 'claim_state_write'",
        'errorLine = $script:errorLine',
        "@('qualify', 'stage', 'preflight', 'activate', 'postflight', 'rollback')",
        "'final_postflight'",
        'Reconcile-InterruptedPhase',
        "[string] \$script:state.phase -ceq 'rollback'",
        "Invoke-Adapter -Operation 'inspect'",
        'Write-ResealRollbackBackups',
        'Restore-ResealPolicies',
        "Invoke-Adapter -Operation 'rollback'",
        "Set-TerminalState -Outcome 'manual_recovery_required'",
        'Write-AtomicBytes -Path $ChannelPolicyPath',
        'Write-AtomicBytes -Path $AdapterPolicyPath',
        'channelPolicySha256',
        'adapterPolicySha256',
        'exit $script:finalExitCode',
    ] as $fragment
) {
    assertScopeBoundApprovalRunner(
        str_contains($runner, $fragment),
        "Approval runner fragment is missing: {$fragment}"
    );
}

assertScopeBoundApprovalRunner(
    str_contains(
        $runner,
        '(Get-Sha256 -Path ([string] $script:policy.resealScriptPath)))) {'
    ),
    'Approval qualification condition is not closed for Windows PowerShell 5.1.'
);

assertScopeBoundApprovalRunner(
    strpos($runner, "[Threading.Mutex]::new(\$false, 'Global\\SAEF.DeploymentApproval')")
        < strpos($runner, 'if (Test-Path -LiteralPath $script:statePath -PathType Leaf)'),
    'Approval claim is evaluated before the global approval mutex.'
);
assertScopeBoundApprovalRunner(
    str_contains($gateway, "'Global\\SAEF.DeploymentChannel'"),
    'Deployment channel mutex ownership is missing from the gateway.'
);
assertScopeBoundApprovalRunner(
    substr_count($runner, 'exit $script:finalExitCode') === 1,
    'Approval runner must have one native exit boundary.'
);
assertScopeBoundApprovalRunner(
    preg_match('/\.\$[A-Za-z_]/', $runner) === 0,
    'Approval runner contains a Windows PowerShell 5.1-unsafe dynamic property access.'
);
assertScopeBoundApprovalRunner(
    !str_contains($runner, '$env:SSH_ORIGINAL_COMMAND')
        && !str_contains($runner, 'Invoke-Expression')
        && !str_contains($runner, 'errorMessage')
        && !str_contains($runner, 'Restart-Service')
        && !str_contains($runner, 'Start-Service')
        && !str_contains($runner, 'Stop-Service'),
    'Approval runner contains a forbidden execution or service-control path.'
);
assertScopeBoundApprovalRunner(
    str_contains($gateway, "@('probe', 'stage', 'preflight', 'activate', 'status')")
        && str_contains($gateway, "[string] \$parts[2] -cne 'approved'")
        && !str_contains($gateway, "'approve'"),
    'Gateway widens its verb allowlist or lacks the bounded activation mode.'
);
assertScopeBoundApprovalRunner(
    str_contains($reseal, '[switch] $ChannelMutexAlreadyHeld')
        && str_contains($reseal, '[string] $CoordinatorPlanSha256')
        && str_contains($reseal, '[string] $ActivationStatusPath'),
    'OwnTracks reseal lacks coordinator-owned lock and evidence binding.'
);

$expectedPolicyKeys = [
    'formatVersion',
    'runnerProfile',
    'targetId',
    'adapterProfile',
    'qualificationProfile',
    'postflightProfile',
    'approvalStateRoot',
    'approvalSecretPath',
    'qualificationEvidencePath',
    'expectedQualificationEvidenceSha256',
    'channelHostBindingSha256',
    'approverIdentitySha256',
    'executionHostIdentitySha256',
    'maximumStateFiles',
    'resealEnabled',
    'resealScriptPath',
    'expectedResealScriptSha256',
];
sort($expectedPolicyKeys, SORT_STRING);
$actualPolicyKeys = array_keys($policy);
sort($actualPolicyKeys, SORT_STRING);
assertScopeBoundApprovalRunner(
    $actualPolicyKeys === $expectedPolicyKeys,
    'Approval policy example fields differ.'
);
assertScopeBoundApprovalRunner(
    $policy['maximumStateFiles'] === 256 && $policy['resealEnabled'] === true,
    'Approval policy example bounds differ.'
);

fwrite(STDOUT, "scope-bound-approval-runner: ok\n");
