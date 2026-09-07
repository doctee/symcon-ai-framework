<?php

declare(strict_types=1);

function failScopeBoundApprovalWindows(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertScopeBoundApprovalWindows(bool $condition, string $message): void
{
    if (!$condition) {
        failScopeBoundApprovalWindows($message);
    }
}

$root = dirname(__DIR__, 2);
$windowsRoot = $root . '/deployments/symcon/windows';
$fixtureRoot = $root . '/tests/fixtures/deployments/windows';
$initializer = file_get_contents($windowsRoot . '/Initialize-SaefScopeBoundApprovalProfile.ps1');
$qualification = file_get_contents(
    $windowsRoot . '/Invoke-SaefScopeBoundApprovalWindowsQualification.ps1'
);
$syntheticAdapter = file_get_contents($fixtureRoot . '/Invoke-SaefApprovalSyntheticAdapter.ps1');
$syntheticReseal = file_get_contents($fixtureRoot . '/Invoke-SaefApprovalSyntheticReseal.ps1');

foreach (
    [
        'approval profile initializer' => $initializer,
        'approval Windows qualification' => $qualification,
        'synthetic approval adapter' => $syntheticAdapter,
        'synthetic approval reseal' => $syntheticReseal,
    ] as $label => $source
) {
    assertScopeBoundApprovalWindows(is_string($source), ucfirst($label) . ' is unreadable.');
}

$initializerFragments = [
    '[switch] $PreflightOnly',
    'Assert-PowerShellSyntax',
    'ExpectedQualificationEvidenceSha256',
    'ExpectedRunnerSha256',
    'ExpectedResealScriptSha256',
    'Set-RestrictedDirectoryAcl',
    'Set-RestrictedRuntimeFileAcl',
    'Assert-RestrictedAcl',
    "-DeploymentRights 'RX'",
    'Get-FileSnapshot',
    'Restore-Snapshots',
    'Write-AtomicBytes',
    "Set-ObjectProperty -Value \$target -Name 'approvalRunnerPath'",
    'activeMutationAttempted = $false',
    'serviceRestartAttempted = $false',
    "Write-Status -Phase 'install' -Outcome 'installed'",
];
foreach ($initializerFragments as $fragment) {
    assertScopeBoundApprovalWindows(
        str_contains($initializer, $fragment),
        "Approval profile initializer fragment is missing: {$fragment}"
    );
}
assertScopeBoundApprovalWindows(
    str_contains(
        $initializer,
        'Set-RestrictedDirectoryAcl -Path $ApprovalRoot -DeploymentIdentity $deploymentIdentity `' . "\n" .
        "        -DeploymentRights 'RX'"
    ) && str_contains(
        $initializer,
        'Set-RestrictedDirectoryAcl -Path $approvalTargetRoot -DeploymentIdentity $deploymentIdentity `' . "\n" .
        "        -DeploymentRights 'RX'"
    ) && str_contains(
        $initializer,
        'Set-RestrictedDirectoryAcl -Path $approvalStateRoot -DeploymentIdentity $deploymentIdentity'
    ),
    'Approval profile directory ACLs do not isolate secret replacement from mutable state.'
);
assertScopeBoundApprovalWindows(
    str_contains($initializer, '[int] $evidence.positiveCaseCount -ne 6')
        && str_contains($initializer, '[int] $evidence.negativeCaseCount -ne 8'),
    'Approval profile initializer does not require the complete qualification matrix.'
);
assertScopeBoundApprovalWindows(
    !str_contains($initializer, 'Restart-Service')
        && !str_contains($initializer, 'Stop-Service')
        && !str_contains($initializer, 'Start-Service')
        && !str_contains($initializer, 'Invoke-Expression'),
    'Approval profile initializer contains a service or arbitrary execution path.'
);

$qualificationFragments = [
    '[Management.Automation.Language.Parser]::ParseFile',
    '$Value -is [Collections.IDictionary]',
    'ConvertTo-CanonicalValue -Value $dictionary[$name]',
    'ConvertTo-CanonicalValue -Value $property.Value',
    "'base_positive'",
    "'profile_installer_positive'",
    "'replay_negative'",
    "'reseal_positive'",
    "'pre_mutation_resume'",
    "'expired'",
    "'wrong_user'",
    "'wrong_host'",
    "'bad_signature'",
    "'baseline-drift'",
    "'forbidden-risk'",
    "'lock-contention'",
    "'postflight_rollback'",
    "'reseal_rollback'",
    "'Global\\SAEF.DeploymentApproval'",
    'productionMutationAttempted = $false',
    'serviceRestartAttempted = $false',
    'scratchCleanupSucceeded',
    'Update-ProfileInstallerDiagnostics',
    'Update-RunnerScenarioDiagnostics',
    '[AllowEmptyString()][string] $ResealSha256',
    "'profileInstallerFailedStep'",
    "'runnerStatusFailureCode'",
    "'runnerErrorType'",
    "'runnerErrorLine'",
    "'runnerErrorCommand'",
    "'runnerStandardErrorBytes'",
    "'runnerScenarioPhase'",
    "'errorId'",
    "'errorLine'",
    "if (\$Outcome -cne 'passed')",
    'positiveCaseCount -ne 6',
    'negativeCaseCount -ne 8',
];
foreach ($qualificationFragments as $fragment) {
    assertScopeBoundApprovalWindows(
        str_contains($qualification, $fragment),
        "Approval Windows qualification fragment is missing: {$fragment}"
    );
}
assertScopeBoundApprovalWindows(
    !str_contains($qualification, 'Restart-Service')
        && !str_contains($qualification, 'Stop-Service')
        && !str_contains($qualification, 'Start-Service')
        && !str_contains($qualification, 'Invoke-RestMethod')
        && !str_contains($qualification, 'Invoke-WebRequest')
        && !str_contains($qualification, 'ssh '),
    'Approval Windows qualification reaches a production or network mutation boundary.'
);
assertScopeBoundApprovalWindows(
    preg_match('/\.\$[A-Za-z_]/', $qualification) === 0,
    'Approval Windows qualification contains an unsafe dynamic property access.'
);
assertScopeBoundApprovalWindows(
    !str_contains($qualification, '.PSObject.Properties.Name'),
    'Approval Windows qualification contains an unsafe aggregate property-name access.'
);
assertScopeBoundApprovalWindows(
    str_contains($qualification, 'Invoke-ProfileInstallerScenario')
        && str_contains($qualification, "'-File', \$ProfileInitializerPath")
        && str_contains($qualification, "'-PreflightOnly'")
        && str_contains($qualification, '[bool] $postflight.repairRequired'),
    'Approval Windows qualification does not execute the profile installer in scratch.'
);
assertScopeBoundApprovalWindows(
    str_contains($syntheticAdapter, "[ValidateSet('preflight', 'activate', 'postflight', 'inspect', 'rollback')]")
        && str_contains($syntheticAdapter, "Write-Status -Outcome 'rolled_back'")
        && str_contains($syntheticReseal, '[switch] $ChannelMutexAlreadyHeld')
        && str_contains($syntheticReseal, 'CoordinatorPlanSha256'),
    'Synthetic Windows fixtures do not exercise the fixed runner contract.'
);

fwrite(STDOUT, "scope-bound-approval-windows: ok\n");
