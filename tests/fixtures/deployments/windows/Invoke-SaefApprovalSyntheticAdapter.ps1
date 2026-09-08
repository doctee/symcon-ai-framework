[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'activate', 'postflight', 'inspect', 'rollback')]
    [string] $Operation,

    [Parameter(Mandatory = $true)][string] $ManifestPath,
    [Parameter(Mandatory = $true)][string] $CandidatePath,
    [Parameter(Mandatory = $true)][string] $TransactionContractPath,
    [Parameter(Mandatory = $true)][string] $AdapterPolicyPath,
    [Parameter(Mandatory = $true)][Uri] $RpcUri,
    [Parameter(Mandatory = $true)][string] $CredentialPath,
    [Parameter(Mandatory = $true)][string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$ExitRolledBack = 30

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Write-Status {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter(Mandatory = $true)][bool] $ActivationAttempted,
        [Parameter(Mandatory = $true)][bool] $RollbackAttempted,
        [Parameter(Mandatory = $true)][bool] $RollbackSucceeded
    )
    $record = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        operation = $Operation
        deploymentId = [string] $script:manifest.deploymentId
        manifestSha256 = Get-Sha256 -Path $ManifestPath
        packageIdentitySha256 = [string] $script:manifest.module.packageIdentitySha256
        outcome = $Outcome
        exitCode = $ExitCode
        activationAttempted = $ActivationAttempted
        rollbackAttempted = $RollbackAttempted
        rollbackSucceeded = $RollbackSucceeded
    }
    [IO.File]::WriteAllText(
        $StatusPath,
        ($record | ConvertTo-Json -Depth 4) + [Environment]::NewLine,
        [Text.UTF8Encoding]::new($false)
    )
}

$script:manifest = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
$policy = Get-Content -LiteralPath $AdapterPolicyPath -Raw | ConvertFrom-Json
$activePath = Join-Path (Split-Path -Parent $AdapterPolicyPath) 'synthetic-active.local.txt'
$previousPath = Join-Path (Split-Path -Parent $AdapterPolicyPath) 'synthetic-previous.local.txt'
$postflightCountPath = Join-Path (Split-Path -Parent $AdapterPolicyPath) 'synthetic-postflight-count.local.txt'
$candidateIdentity = [string] $script:manifest.module.packageIdentitySha256
$previousIdentity = [string] $policy.expectedActivePackageIdentitySha256

switch ($Operation) {
    'preflight' {
        if ((Get-Content -LiteralPath $activePath -Raw).Trim() -cne $previousIdentity) {
            throw [InvalidOperationException]::new('Synthetic preflight baseline differs.')
        }
        Write-Status -Outcome 'passed' -ExitCode 0 -ActivationAttempted $false `
            -RollbackAttempted $false -RollbackSucceeded $false
        exit 0
    }
    'activate' {
        if ([string] $env:SAEF_APPROVAL_SYNTHETIC_CRASH_ACTIVATE -ceq '1') {
            exit 99
        }
        [IO.File]::WriteAllText(
            $previousPath,
            (Get-Content -LiteralPath $activePath -Raw).Trim(),
            [Text.UTF8Encoding]::new($false)
        )
        [IO.File]::WriteAllText($activePath, $candidateIdentity, [Text.UTF8Encoding]::new($false))
        Write-Status -Outcome 'activated' -ExitCode 0 -ActivationAttempted $true `
            -RollbackAttempted $false -RollbackSucceeded $false
        exit 0
    }
    'postflight' {
        $count = if (Test-Path -LiteralPath $postflightCountPath -PathType Leaf) {
            [int] (Get-Content -LiteralPath $postflightCountPath -Raw).Trim()
        } else { 0 }
        $count++
        [IO.File]::WriteAllText(
            $postflightCountPath,
            [string] $count,
            [Text.UTF8Encoding]::new($false)
        )
        if ([int] $env:SAEF_APPROVAL_SYNTHETIC_FAIL_POSTFLIGHT_AT -eq $count) {
            Write-Status -Outcome 'failed' -ExitCode 10 -ActivationAttempted $true `
                -RollbackAttempted $false -RollbackSucceeded $false
            exit 10
        }
        if ((Get-Content -LiteralPath $activePath -Raw).Trim() -cne $candidateIdentity) {
            throw [InvalidOperationException]::new('Synthetic active identity differs.')
        }
        Write-Status -Outcome 'passed' -ExitCode 0 -ActivationAttempted $true `
            -RollbackAttempted $false -RollbackSucceeded $false
        exit 0
    }
    'inspect' {
        $active = (Get-Content -LiteralPath $activePath -Raw).Trim()
        $outcome = if ($active -ceq $candidateIdentity) {
            'active'
        } elseif (Test-Path -LiteralPath $previousPath -PathType Leaf) {
            'rolled_back'
        } else {
            'not_applied'
        }
        Write-Status -Outcome $outcome -ExitCode 0 -ActivationAttempted $true `
            -RollbackAttempted ($outcome -ceq 'rolled_back') `
            -RollbackSucceeded ($outcome -ceq 'rolled_back')
        exit 0
    }
    'rollback' {
        if (-not (Test-Path -LiteralPath $previousPath -PathType Leaf)) {
            throw [IO.FileNotFoundException]::new('Synthetic rollback identity is missing.')
        }
        [IO.File]::WriteAllText(
            $activePath,
            (Get-Content -LiteralPath $previousPath -Raw).Trim(),
            [Text.UTF8Encoding]::new($false)
        )
        Write-Status -Outcome 'rolled_back' -ExitCode $ExitRolledBack -ActivationAttempted $true `
            -RollbackAttempted $true -RollbackSucceeded $true
        exit $ExitRolledBack
    }
}
