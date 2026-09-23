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
function Get-TestHash { param([string] $Path) return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant() }
& $qualification -ExpectedRunnerSha256 (Get-TestHash $runner) -ExpectedAdapterSha256 (Get-TestHash $adapter) `
    -ExpectedResealSha256 (Get-TestHash $reseal) -ExpectedChildProcessContractSha256 (Get-TestHash $childContract) `
    -RunnerPath $runner -AdapterPath $adapter -ResealPath $reseal -ChildProcessContractPath $childContract `
    -SyntheticAdapterPath (Join-Path $fixtures 'Invoke-SaefApprovalSyntheticAdapter.ps1') `
    -SyntheticResealPath (Join-Path $fixtures 'Invoke-SaefApprovalSyntheticReseal.ps1') `
    -QualificationTargetId $TargetId -StatusPath $status
$qualificationExit = $LASTEXITCODE
Get-Content -LiteralPath $status
if ($qualificationExit -ne 0) { exit $qualificationExit }
$result = Get-Content -LiteralPath $status -Raw | ConvertFrom-Json
if ($result.outcome -cne 'passed' -or $result.exitCode -ne 0 -or
    $result.positiveCaseCount -ne 6 -or $result.negativeCaseCount -ne 8 -or
    -not $result.scratchCleanupSucceeded -or $result.productionMutationAttempted -or $result.serviceRestartAttempted) {
    throw 'Native approval qualification did not prove its contract.'
}
Write-Output ('PASS: isolated approval runner matrix for ' + $TargetId)
