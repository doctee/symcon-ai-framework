[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][ValidateSet('apply')][string] $Operation,
    [Parameter(Mandatory = $true)][string] $ChannelPolicyPath,
    [Parameter(Mandatory = $true)][string] $ExpectedChannelPolicySha256,
    [Parameter(Mandatory = $true)][string] $ExpectedPreviousPackageIdentitySha256,
    [Parameter(Mandatory = $true)][string] $ExpectedActivePackageIdentitySha256,
    [Parameter(Mandatory = $true)][string] $ExpectedActiveDeploymentId,
    [Parameter(Mandatory = $true)][string] $DeploymentUser,
    [Parameter(Mandatory = $true)][string] $StatusPath,
    [Parameter(Mandatory = $true)][switch] $ChannelMutexAlreadyHeld,
    [Parameter(Mandatory = $true)][string] $CoordinatorPlanSha256,
    [Parameter(Mandatory = $true)][string] $ActivationStatusPath,
    [Parameter(Mandatory = $true)][string] $Confirmation
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

if (-not [bool] $ChannelMutexAlreadyHeld -or
    $Confirmation -cne 'reseal-saef-owntracks-position-map-active-identity' -or
    (Get-Sha256 -Path $ChannelPolicyPath) -cne $ExpectedChannelPolicySha256 -or
    -not (Test-Path -LiteralPath $ActivationStatusPath -PathType Leaf)) {
    throw [Security.SecurityException]::new('Synthetic reseal binding differs.')
}

$adapterPolicyPath = Join-Path (Split-Path -Parent $ChannelPolicyPath) 'adapter-policy.json'
$channelPolicy = Get-Content -LiteralPath $ChannelPolicyPath -Raw | ConvertFrom-Json
$adapterPolicy = Get-Content -LiteralPath $adapterPolicyPath -Raw | ConvertFrom-Json
$channelPolicy | Add-Member -NotePropertyName 'qualifiedPackageSha256' `
    -NotePropertyValue $ExpectedActivePackageIdentitySha256 -Force
$adapterPolicy.expectedActivePackageIdentitySha256 = $ExpectedActivePackageIdentitySha256
[IO.File]::WriteAllText(
    $ChannelPolicyPath,
    ($channelPolicy | ConvertTo-Json -Depth 5) + [Environment]::NewLine,
    [Text.UTF8Encoding]::new($false)
)
[IO.File]::WriteAllText(
    $adapterPolicyPath,
    ($adapterPolicy | ConvertTo-Json -Depth 5) + [Environment]::NewLine,
    [Text.UTF8Encoding]::new($false)
)

$status = [ordered]@{
    formatVersion = 1
    timestampUtc = [DateTime]::UtcNow.ToString('o')
    operation = $Operation
    outcome = 'resealed'
    exitCode = 0
    mutationAttempted = $true
    channelMutexInherited = $true
    coordinatorPlanSha256 = $CoordinatorPlanSha256
    proposedChannelPolicySha256 = Get-Sha256 -Path $ChannelPolicyPath
    proposedAdapterPolicySha256 = Get-Sha256 -Path $adapterPolicyPath
}
[IO.File]::WriteAllText(
    $StatusPath,
    ($status | ConvertTo-Json -Depth 4) + [Environment]::NewLine,
    [Text.UTF8Encoding]::new($false)
)
exit 0
