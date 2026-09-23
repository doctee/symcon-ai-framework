# Real runner/profile installer, synthetic target and reseal only. No live RPC.
param([Parameter(Mandatory = $true)][ValidateSet('saef-owntracks-position-map', 'saef-media-carousel')][string] $TargetId)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$windows = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
$fixtures = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../fixtures/deployments/windows'))
$adapterName = if ($TargetId -ceq 'saef-media-carousel') { 'Invoke-SaefMediaCarouselModuleAdapter.ps1' } else { 'Invoke-SaefOwnTracksPositionMapModuleAdapter.ps1' }
$runner = Join-Path $windows 'Invoke-SaefScopeBoundApprovalRunner.ps1'
$adapter = Join-Path (Join-Path $windows 'adapters') $adapterName
$reseal = Join-Path $windows 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1'
$childContract = Join-Path $windows 'SaefChildProcess.ps1'
$qualification = Join-Path $windows 'Invoke-SaefScopeBoundApprovalWindowsQualification.ps1'
$status = Join-Path $PSScriptRoot ($TargetId + '-approval-qualification.local.json')
$deploymentUser = (Get-LocalUser -SID ([Security.Principal.WindowsIdentity]::GetCurrent().User)).Name
function Get-TestHash { param([string] $Path) return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant() }
. $childContract
$initializer = Join-Path $windows 'Initialize-SaefScopeBoundApprovalProfile.ps1'
$missingUser = 'saef-ci-' + [guid]::NewGuid().ToString('N').Substring(0, 8)
$failureStatus = Join-Path $PSScriptRoot ($TargetId + '-expected-profile-failure.local.json')
$failure = Invoke-SaefPowerShellChildProcess -ScriptPath $initializer -ExpectedScriptSha256 (Get-TestHash $initializer) `
    -Arguments @('-DeploymentUser', $missingUser, '-TargetId', $TargetId, '-QualificationProfile', 'saef-test-v1',
        '-PostflightProfile', 'saef-test-v1', '-ChannelHostBindingSha256', ('a' * 64),
        '-ApproverIdentitySha256', ('b' * 64), '-ExecutionHostIdentitySha256', ('c' * 64),
        '-ApprovalSecretRecordPath', $failureStatus, '-QualificationEvidencePath', $failureStatus,
        '-ExpectedQualificationEvidenceSha256', ('d' * 64), '-ExpectedRunnerSha256', (Get-TestHash $runner),
        '-RunnerSourcePath', $runner, '-PreflightOnly', '-StatusPath', $failureStatus) `
    -TimeoutSeconds 60 -MaximumOutputBytes 8192
$failed = Get-Content -LiteralPath $failureStatus -Raw | ConvertFrom-Json
if ($failure.exitCode -ne 10 -or $failed.outcome -cne 'failed' -or $failed.failedStep -cne 'deployment_account' -or
    $failed.mutationAttempted -or $failed.activeMutationAttempted -or $failed.serviceRestartAttempted) {
    throw 'Profile initializer did not report preflight failure without mutation.'
}
$qualificationResult = Invoke-SaefPowerShellChildProcess -ScriptPath $qualification `
    -ExpectedScriptSha256 (Get-TestHash $qualification) -TimeoutSeconds 600 -MaximumOutputBytes 65536 `
    -Arguments @('-ExpectedRunnerSha256', (Get-TestHash $runner), '-ExpectedAdapterSha256', (Get-TestHash $adapter),
        '-ExpectedResealSha256', (Get-TestHash $reseal), '-ExpectedChildProcessContractSha256', (Get-TestHash $childContract),
        '-RunnerPath', $runner, '-AdapterPath', $adapter, '-ResealPath', $reseal, '-ChildProcessContractPath', $childContract,
        '-ProfileInitializerPath', $initializer,
        '-SyntheticAdapterPath', (Join-Path $fixtures 'Invoke-SaefApprovalSyntheticAdapter.ps1'),
        '-SyntheticResealPath', (Join-Path $fixtures 'Invoke-SaefApprovalSyntheticReseal.ps1'),
        '-QualificationTargetId', $TargetId, '-QualificationDeploymentUser', $deploymentUser, '-StatusPath', $status)
$qualificationExit = $qualificationResult.exitCode
if (-not (Test-Path -LiteralPath $status -PathType Leaf)) {
    # These are isolated fixtures with generated dummy credentials only. Surface
    # the bounded native startup error instead of hiding it behind Get-Content.
    Write-Output ([Text.Encoding]::UTF8.GetString($qualificationResult.standardError))
    throw ('Qualification produced no status; termination=' + $qualificationResult.terminationReason + '; exit=' + $qualificationExit)
}
Get-Content -LiteralPath $status
if ($qualificationExit -ne 0) { exit $qualificationExit }
$result = Get-Content -LiteralPath $status -Raw | ConvertFrom-Json
if ($result.outcome -cne 'passed' -or $result.exitCode -ne 0 -or
    $result.positiveCaseCount -ne 6 -or $result.negativeCaseCount -ne 8 -or
    -not $result.scratchCleanupSucceeded -or $result.productionMutationAttempted -or $result.serviceRestartAttempted) {
    throw 'Native approval qualification did not prove its contract.'
}
Write-Output ('PASS: isolated approval runner matrix for ' + $TargetId)
