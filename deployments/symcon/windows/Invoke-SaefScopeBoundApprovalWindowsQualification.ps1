[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedRunnerSha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedAdapterSha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedResealSha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedChildProcessContractSha256,

    [Parameter()]
    [string] $RunnerPath = (Join-Path $PSScriptRoot 'Invoke-SaefScopeBoundApprovalRunner.ps1'),

    [Parameter()]
    [string] $AdapterPath = (Join-Path $PSScriptRoot 'adapters\Invoke-SaefOwnTracksPositionMapModuleAdapter.ps1'),

    [Parameter()]
    [string] $ResealPath = (Join-Path $PSScriptRoot 'adapters\Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1'),

    [Parameter()]
    [string] $ProfileInitializerPath = (Join-Path $PSScriptRoot 'Initialize-SaefScopeBoundApprovalProfile.ps1'),

    [Parameter()]
    [string] $SyntheticAdapterPath = (Join-Path $PSScriptRoot 'Invoke-SaefApprovalSyntheticAdapter.ps1'),

    [Parameter()]
    [string] $SyntheticResealPath = (Join-Path $PSScriptRoot 'Invoke-SaefApprovalSyntheticReseal.ps1'),

    [Parameter()]
    [string] $ChildProcessContractPath = (Join-Path $PSScriptRoot 'SaefChildProcess.ps1'),

    [Parameter()]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($StatusPath)) {
    $StatusPath = Join-Path $PSScriptRoot 'scope-bound-approval-windows-qualification.local.json'
}

$ExitSuccess = 0
$ExitFailed = 10
$script:failedCheck = 'initialization'
$script:positiveCaseCount = 0
$script:negativeCaseCount = 0
$script:scratchMutationAttempted = $false
$script:scratchCleanupSucceeded = $false
$script:scratchRoot = ''
$script:runnerSha256 = ''
$script:adapterSha256 = ''
$script:resealSha256 = ''
$script:childProcessContractSha256 = ''
$script:profileInstallerPhase = 'not_started'
$script:profileInstallerProcessExitCode = -1
$script:profileInstallerStatusWritten = $false
$script:profileInstallerStatusExitCode = -1
$script:profileInstallerOutcome = ''
$script:profileInstallerFailedStep = ''
$script:profileInstallerErrorType = ''
$script:profileInstallerRollbackAttempted = $false
$script:profileInstallerRollbackSucceeded = $false
$script:profileInstallerStandardErrorBytes = 0
$script:runnerScenarioLabel = ''
$script:runnerScenarioPhase = ''
$script:runnerProcessExitCode = -1
$script:runnerStatusWritten = $false
$script:runnerStatusExitCode = -1
$script:runnerStatusOutcome = ''
$script:runnerStatusFailureCode = ''
$script:runnerStatusActivationAttempted = $false
$script:runnerStatusMutationAttempted = $false
$script:runnerStatusRollbackAttempted = $false
$script:runnerStatusRollbackSucceeded = $false
$script:runnerStandardErrorBytes = 0
$script:runnerErrorType = ''
$script:runnerErrorId = ''
$script:runnerErrorCategory = ''
$script:runnerErrorLine = 0
$script:runnerErrorColumn = 0
$script:runnerErrorCommand = ''
$script:finalErrorId = ''
$script:finalErrorCategory = ''
$script:finalErrorLine = 0
$script:finalErrorColumn = 0
$script:finalErrorCommand = ''
$script:secretBytes = [Text.UTF8Encoding]::new($false).GetBytes(
    'saef-windows-qualification-secret-32-bytes-minimum'
)
$script:approverIdentity = 'saef-qualification-approver'
$script:executionHostIdentity = 'saef-qualification-controller'
$script:channelHostBindingSha256 = ''

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Get-TextSha256 {
    param([Parameter(Mandatory = $true)][string] $Text)
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        return ([BitConverter]::ToString($algorithm.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant()
    } finally {
        $algorithm.Dispose()
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Get-HmacSha256 {
    param(
        [Parameter(Mandatory = $true)][string] $Text,
        [Parameter(Mandatory = $true)][byte[]] $Secret
    )
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    $hmac = [Security.Cryptography.HMACSHA256]::new($Secret)
    try {
        return ([BitConverter]::ToString($hmac.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant()
    } finally {
        $hmac.Dispose()
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function ConvertTo-CanonicalValue {
    param([Parameter(Mandatory = $true)] $Value)
    if ($Value -is [Array]) {
        $items = @()
        foreach ($item in @($Value)) {
            $items += ,(ConvertTo-CanonicalValue -Value $item)
        }
        return ,$items
    }
    if ($null -eq $Value -or $Value -is [string] -or $Value -is [ValueType]) {
        return $Value
    }
    if ($Value -is [Collections.IDictionary]) {
        $dictionary = [Collections.IDictionary] $Value
        $result = [ordered]@{}
        [string[]] $names = @($dictionary.Keys | ForEach-Object { [string] $_ })
        [Array]::Sort($names, [StringComparer]::Ordinal)
        foreach ($name in $names) {
            $result[$name] = ConvertTo-CanonicalValue -Value $dictionary[$name]
        }
        return $result
    }
    $properties = [Collections.Generic.Dictionary[string, object]]::new(
        [StringComparer]::Ordinal
    )
    foreach ($property in @($Value.PSObject.Properties)) {
        $properties.Add([string] $property.Name, $property)
    }
    [string[]] $names = @($properties.Keys)
    [Array]::Sort($names, [StringComparer]::Ordinal)
    $result = [ordered]@{}
    foreach ($name in $names) {
        $result[$name] = ConvertTo-CanonicalValue -Value $properties[$name].Value
    }
    return $result
}

function ConvertTo-CanonicalJson {
    param([Parameter(Mandatory = $true)] $Value)
    return ConvertTo-CanonicalValue -Value $Value | ConvertTo-Json -Depth 16 -Compress
}

function Assert-CultureInvariantCanonicalization {
    $expectedJson = '{"Beta":2,"Zeta":4,"alpha":1,"item":3,"nested":{"Delta":6,"charlie":5}}'
    $expectedSha256 = '8b5c8f1ad3815fcd35b593c95d78af0776d33d0b4e29242ca92f4f07d0a6a0a7'
    $value = [pscustomobject] [ordered]@{
        item = 3
        alpha = 1
        Zeta = 4
        Beta = 2
        nested = [pscustomobject] [ordered]@{
            charlie = 5
            Delta = 6
        }
    }
    $thread = [Threading.Thread]::CurrentThread
    $originalCulture = $thread.CurrentCulture
    $originalUiCulture = $thread.CurrentUICulture
    try {
        foreach ($cultureName in @('en-US', 'de-DE', 'tr-TR')) {
            $culture = [Globalization.CultureInfo]::GetCultureInfo($cultureName)
            $thread.CurrentCulture = $culture
            $thread.CurrentUICulture = $culture
            $actualJson = ConvertTo-CanonicalJson -Value $value
            if ($actualJson -cne $expectedJson -or
                (Get-TextSha256 -Text $actualJson) -cne $expectedSha256) {
                throw [InvalidOperationException]::new(
                    'Canonical approval serialization depends on the current culture.'
                )
            }
        }
    } finally {
        $thread.CurrentCulture = $originalCulture
        $thread.CurrentUICulture = $originalUiCulture
    }
}

function ConvertTo-Base64Url {
    param([Parameter(Mandatory = $true)][string] $Text)
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    try {
        return [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Write-Json {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value
    )
    [IO.File]::WriteAllText(
        $Path,
        ($Value | ConvertTo-Json -Depth 16) + [Environment]::NewLine,
        [Text.UTF8Encoding]::new($false)
    )
}

function Assert-PlainLeaf {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (((Get-Item -LiteralPath $Path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.FileNotFoundException]::new('Qualification source is missing or unsafe.')
    }
}

function Assert-PowerShellSyntax {
    param([Parameter(Mandatory = $true)][string] $Path)
    $tokens = $null
    $parseErrors = $null
    [Management.Automation.Language.Parser]::ParseFile($Path, [ref] $tokens, [ref] $parseErrors) | Out-Null
    if (@($parseErrors).Count -ne 0) {
        throw [InvalidOperationException]::new('PowerShell 5.1 parser rejected a qualification source.')
    }
}

function Set-ScratchAcl {
    param([Parameter(Mandatory = $true)][string] $Path)
    & icacls.exe $Path '/inheritance:r' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot disable qualification ACL inheritance.')
    }
    & icacls.exe $Path '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot grant qualification ACL.')
    }
    & icacls.exe $Path '/setowner' '*S-1-5-32-544' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot protect qualification scratch path.')
    }
}

function New-ApprovalEnvelope {
    param(
        [Parameter(Mandatory = $true)] $Plan,
        [Parameter(Mandatory = $true)][long] $IssuedAt,
        [Parameter(Mandatory = $true)][long] $ExpiresAt,
        [Parameter()][string] $ApproverIdentity = $script:approverIdentity,
        [Parameter()][string] $ExecutionHostIdentity = $script:executionHostIdentity,
        [Parameter()][switch] $InvalidSignature
    )
    $planSha256 = Get-TextSha256 -Text (ConvertTo-CanonicalJson -Value $Plan)
    $approval = [ordered]@{
        formatVersion = 1
        algorithm = 'hmac-sha256'
        planSha256 = $planSha256
        targetId = [string] $Plan.targetId
        adapterProfile = [string] $Plan.adapterProfile
        allowedOperations = @($Plan.operations)
        expectedBaselineIdentities = $Plan.expectedBaselineIdentities
        channelHostBindingSha256 = [string] $Plan.channelHostBindingSha256
        approverIdentitySha256 = Get-TextSha256 -Text $ApproverIdentity
        executionHostIdentitySha256 = Get-TextSha256 -Text $ExecutionHostIdentity
        issuedAt = $IssuedAt
        expiresAt = $ExpiresAt
        nonce = Get-TextSha256 -Text ($planSha256 + ':' + [string] $IssuedAt + ':' + [Guid]::NewGuid().ToString('N'))
    }
    $approval['signature'] = Get-HmacSha256 `
        -Text (ConvertTo-CanonicalJson -Value ([pscustomobject] $approval)) `
        -Secret $script:secretBytes
    if ([bool] $InvalidSignature) {
        $approval['signature'] = if ([string] $approval.signature -clike '0*') {
            '1' + ([string] $approval.signature).Substring(1)
        } else {
            '0' + ([string] $approval.signature).Substring(1)
        }
    }
    $envelope = [ordered]@{ formatVersion = 1; plan = $Plan; approval = [pscustomobject] $approval }
    return ConvertTo-Base64Url -Text (ConvertTo-CanonicalJson -Value ([pscustomobject] $envelope))
}

function New-QualificationEvidence {
    param(
        [Parameter(Mandatory = $true)][string] $AdapterSha256,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string] $ResealSha256
    )
    return [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'windows_qualification'
        outcome = 'passed'
        exitCode = 0
        expectedChannelVersion = 8
        childProcessContractSha256 = $script:childProcessContractSha256
        runnerSha256 = $script:runnerSha256
        adapterSha256 = $AdapterSha256
        resealSha256 = $ResealSha256
        positiveCaseCount = 6
        negativeCaseCount = 8
        scratchMutationAttempted = $true
        scratchCleanupSucceeded = $true
        productionMutationAttempted = $false
        serviceRestartAttempted = $false
        failedCheck = ''
        errorType = ''
    }
}

function Write-ChildStandardError {
    param(
        [Parameter(Mandatory = $true)] $Result,
        [Parameter(Mandatory = $true)][string] $Path
    )
    if ([long] $Result.standardErrorBytes -gt 0) {
        [IO.File]::WriteAllBytes($Path, [byte[]] $Result.standardError)
    } elseif (Test-Path -LiteralPath $Path -PathType Leaf) {
        Remove-Item -LiteralPath $Path -Force
    }
}

function Update-ProfileInstallerDiagnostics {
    param(
        [Parameter(Mandatory = $true)][string] $Phase,
        [Parameter(Mandatory = $true)][int] $ProcessExitCode,
        [Parameter(Mandatory = $true)][string] $StatusPath,
        [Parameter(Mandatory = $true)][string] $StandardErrorPath
    )
    $script:profileInstallerPhase = $Phase
    $script:profileInstallerProcessExitCode = $ProcessExitCode
    $script:profileInstallerStatusWritten = Test-Path -LiteralPath $StatusPath -PathType Leaf
    $script:profileInstallerStandardErrorBytes = if (
        Test-Path -LiteralPath $StandardErrorPath -PathType Leaf
    ) {
        [long] (Get-Item -LiteralPath $StandardErrorPath -Force).Length
    } else { 0 }
    if (-not $script:profileInstallerStatusWritten) {
        return
    }
    $status = Get-Content -LiteralPath $StatusPath -Raw | ConvertFrom-Json
    $names = @($status.PSObject.Properties | ForEach-Object { [string] $_.Name })
    $script:profileInstallerStatusExitCode = if ($names -contains 'exitCode') {
        [int] $status.exitCode
    } else { -1 }
    $script:profileInstallerOutcome = if ($names -contains 'outcome') {
        [string] $status.outcome
    } else { '' }
    $script:profileInstallerFailedStep = if ($names -contains 'failedStep') {
        [string] $status.failedStep
    } else { '' }
    $script:profileInstallerErrorType = if ($names -contains 'errorType') {
        [string] $status.errorType
    } else { '' }
    $script:profileInstallerRollbackAttempted = if ($names -contains 'rollbackAttempted') {
        [bool] $status.rollbackAttempted
    } else { $false }
    $script:profileInstallerRollbackSucceeded = if ($names -contains 'rollbackSucceeded') {
        [bool] $status.rollbackSucceeded
    } else { $false }
}

function Update-RunnerScenarioDiagnostics {
    param(
        [Parameter(Mandatory = $true)][string] $Label,
        [Parameter(Mandatory = $true)][int] $ProcessExitCode,
        [Parameter(Mandatory = $true)][string] $StatusPath,
        [Parameter(Mandatory = $true)][string] $StandardErrorPath
    )
    $script:runnerScenarioLabel = $Label
    $script:runnerScenarioPhase = 'scratch_setup'
    $script:runnerProcessExitCode = $ProcessExitCode
    $script:runnerStatusWritten = Test-Path -LiteralPath $StatusPath -PathType Leaf
    $script:runnerStandardErrorBytes = if (
        Test-Path -LiteralPath $StandardErrorPath -PathType Leaf
    ) {
        [long] (Get-Item -LiteralPath $StandardErrorPath -Force).Length
    } else { 0 }
    if (-not $script:runnerStatusWritten) {
        return
    }
    $status = Get-Content -LiteralPath $StatusPath -Raw | ConvertFrom-Json
    $names = @($status.PSObject.Properties | ForEach-Object { [string] $_.Name })
    $script:runnerStatusExitCode = if ($names -contains 'exitCode') {
        [int] $status.exitCode
    } else { -1 }
    $script:runnerStatusOutcome = if ($names -contains 'outcome') {
        [string] $status.outcome
    } else { '' }
    $script:runnerStatusFailureCode = if ($names -contains 'failureCode') {
        [string] $status.failureCode
    } else { '' }
    $script:runnerStatusActivationAttempted = if ($names -contains 'activationAttempted') {
        [bool] $status.activationAttempted
    } else { $false }
    $script:runnerStatusMutationAttempted = if ($names -contains 'mutationAttempted') {
        [bool] $status.mutationAttempted
    } else { $false }
    $script:runnerStatusRollbackAttempted = if ($names -contains 'rollbackAttempted') {
        [bool] $status.rollbackAttempted
    } else { $false }
    $script:runnerStatusRollbackSucceeded = if ($names -contains 'rollbackSucceeded') {
        [bool] $status.rollbackSucceeded
    } else { $false }
    $script:runnerErrorType = if ($names -contains 'errorType') {
        [string] $status.errorType
    } else { '' }
    $script:runnerErrorId = if ($names -contains 'errorId') {
        [string] $status.errorId
    } else { '' }
    $script:runnerErrorCategory = if ($names -contains 'errorCategory') {
        [string] $status.errorCategory
    } else { '' }
    $script:runnerErrorLine = if ($names -contains 'errorLine') {
        [int] $status.errorLine
    } else { 0 }
    $script:runnerErrorColumn = if ($names -contains 'errorColumn') {
        [int] $status.errorColumn
    } else { 0 }
    $script:runnerErrorCommand = if ($names -contains 'errorCommand') {
        [string] $status.errorCommand
    } else { '' }
}

function Invoke-ProfileInstallerScenario {
    $scenarioRoot = Join-Path $script:scratchRoot 'profile-installer-positive'
    $targetRoot = Join-Path $scenarioRoot 'target'
    $channelRoot = Join-Path $scenarioRoot 'channel'
    $approvalRoot = Join-Path $scenarioRoot 'approvals'
    foreach ($directory in @($scenarioRoot, $targetRoot, $channelRoot, $approvalRoot)) {
        [IO.Directory]::CreateDirectory($directory) | Out-Null
    }

    $targetId = 'saef-qualification-target'
    $adapterProfile = 'saef-qualification-adapter-v1'
    $targetAdapterPath = Join-Path $targetRoot 'synthetic-adapter.ps1'
    $targetResealPath = Join-Path $targetRoot 'synthetic-reseal.ps1'
    $adapterPolicyPath = Join-Path $targetRoot 'adapter-policy.json'
    $channelPolicyPath = Join-Path $channelRoot 'deployment-channel.local.json'
    $secretPath = Join-Path $scenarioRoot 'approval-secret.json'
    $evidencePath = Join-Path $scenarioRoot 'qualification.json'
    $statusPath = Join-Path $scenarioRoot 'profile-status.json'
    $standardErrorPath = Join-Path $scenarioRoot 'profile-standard-error.local.txt'
    Copy-Item -LiteralPath $SyntheticAdapterPath -Destination $targetAdapterPath
    Copy-Item -LiteralPath $SyntheticResealPath -Destination $targetResealPath
    Write-Json -Path $adapterPolicyPath -Value ([ordered]@{
        formatVersion = 1
        adapterProfile = $adapterProfile
    })
    $syntheticAdapterSha256 = Get-Sha256 -Path $targetAdapterPath
    $syntheticResealSha256 = Get-Sha256 -Path $targetResealPath
    Write-Json -Path $channelPolicyPath -Value ([ordered]@{
        formatVersion = 1
        deploymentUser = 'saefdeploy'
        expectedChildProcessContractSha256 = $script:childProcessContractSha256
        standaloneModuleTargets = @([ordered]@{
            targetId = $targetId
            adapterProfile = $adapterProfile
            libraryGuid = '{00000000-0000-0000-0000-000000000001}'
            adapterPath = $targetAdapterPath
            adapterPolicyPath = $adapterPolicyPath
            expectedAdapterSha256 = $syntheticAdapterSha256
        })
    })
    Write-Json -Path $secretPath -Value ([ordered]@{
        formatVersion = 1
        encoding = 'base64'
        secretBase64 = [Convert]::ToBase64String($script:secretBytes)
    })
    Write-Json -Path $evidencePath -Value (
        New-QualificationEvidence -AdapterSha256 $syntheticAdapterSha256 `
            -ResealSha256 $syntheticResealSha256
    )
    $evidenceSha256 = Get-Sha256 -Path $evidencePath
    Set-ScratchAcl -Path $scenarioRoot

    $commonArguments = @(
        '-DeploymentUser', 'saefdeploy',
        '-TargetId', $targetId,
        '-QualificationProfile', 'saef-windows-powershell-5.1-qualification-v1',
        '-PostflightProfile', 'saef-qualification-health-v1',
        '-ChannelHostBindingSha256', $script:channelHostBindingSha256,
        '-ApproverIdentitySha256', (Get-TextSha256 -Text $script:approverIdentity),
        '-ExecutionHostIdentitySha256', (Get-TextSha256 -Text $script:executionHostIdentity),
        '-ApprovalSecretRecordPath', $secretPath,
        '-QualificationEvidencePath', $evidencePath,
        '-ExpectedQualificationEvidenceSha256', $evidenceSha256,
        '-ExpectedRunnerSha256', $script:runnerSha256,
        '-RunnerSourcePath', $RunnerPath,
        '-ResealEnabled',
        '-ResealSourcePath', $targetResealPath,
        '-ExpectedResealScriptSha256', $syntheticResealSha256,
        '-MaximumStateFiles', '32',
        '-ChannelInstallRoot', $channelRoot,
        '-ApprovalRoot', $approvalRoot,
        '-StatusPath', $statusPath
    )
    $profileInitializerSha256 = Get-Sha256 -Path $ProfileInitializerPath

    $script:profileInstallerPhase = 'preflight_process'
    $result = Invoke-SaefPowerShellChildProcess -ScriptPath $ProfileInitializerPath `
        -ExpectedScriptSha256 $profileInitializerSha256 `
        -Arguments @($commonArguments + '-PreflightOnly') `
        -TimeoutSeconds 120 -MaximumOutputBytes 65536
    Write-ChildStandardError -Result $result -Path $standardErrorPath
    $processExitCode = [int] $result.exitCode
    Update-ProfileInstallerDiagnostics -Phase 'preflight_result' `
        -ProcessExitCode $processExitCode -StatusPath $statusPath `
        -StandardErrorPath $standardErrorPath
    if ($processExitCode -ne 0 -or -not $script:profileInstallerStatusWritten) {
        throw [InvalidOperationException]::new('Approval profile scratch preflight failed.')
    }
    $preflight = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
    if ([string] $preflight.outcome -cne 'passed' -or [int] $preflight.exitCode -ne 0 -or
        [bool] $preflight.mutationAttempted -or [bool] $preflight.activeMutationAttempted) {
        throw [InvalidOperationException]::new('Approval profile scratch preflight mutated state.')
    }

    Remove-Item -LiteralPath $statusPath -Force
    Remove-Item -LiteralPath $standardErrorPath -Force -ErrorAction SilentlyContinue
    $script:profileInstallerPhase = 'install_process'
    $result = Invoke-SaefPowerShellChildProcess -ScriptPath $ProfileInitializerPath `
        -ExpectedScriptSha256 $profileInitializerSha256 -Arguments $commonArguments `
        -TimeoutSeconds 120 -MaximumOutputBytes 65536
    Write-ChildStandardError -Result $result -Path $standardErrorPath
    $processExitCode = [int] $result.exitCode
    Update-ProfileInstallerDiagnostics -Phase 'install_result' `
        -ProcessExitCode $processExitCode -StatusPath $statusPath `
        -StandardErrorPath $standardErrorPath
    if ($processExitCode -ne 0 -or -not $script:profileInstallerStatusWritten) {
        throw [InvalidOperationException]::new('Approval profile scratch installation failed.')
    }
    $installed = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
    if ([string] $installed.outcome -cne 'installed' -or [int] $installed.exitCode -ne 0 -or
        -not [bool] $installed.mutationAttempted -or -not [bool] $installed.activeMutationAttempted -or
        [bool] $installed.serviceRestartAttempted) {
        throw [InvalidOperationException]::new('Approval profile scratch installation status differs.')
    }

    Remove-Item -LiteralPath $statusPath -Force
    Remove-Item -LiteralPath $standardErrorPath -Force -ErrorAction SilentlyContinue
    $script:profileInstallerPhase = 'postflight_process'
    $result = Invoke-SaefPowerShellChildProcess -ScriptPath $ProfileInitializerPath `
        -ExpectedScriptSha256 $profileInitializerSha256 `
        -Arguments @($commonArguments + '-PreflightOnly') `
        -TimeoutSeconds 120 -MaximumOutputBytes 65536
    Write-ChildStandardError -Result $result -Path $standardErrorPath
    $processExitCode = [int] $result.exitCode
    Update-ProfileInstallerDiagnostics -Phase 'postflight_result' `
        -ProcessExitCode $processExitCode -StatusPath $statusPath `
        -StandardErrorPath $standardErrorPath
    if ($processExitCode -ne 0) {
        throw [InvalidOperationException]::new('Installed approval profile scratch preflight failed.')
    }
    $postflight = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
    if ([string] $postflight.outcome -cne 'passed' -or [bool] $postflight.repairRequired -or
        [bool] $postflight.mutationAttempted -or [bool] $postflight.activeMutationAttempted) {
        throw [InvalidOperationException]::new('Installed approval profile does not pass exact preflight.')
    }

    $postPolicy = Get-Content -LiteralPath $channelPolicyPath -Raw | ConvertFrom-Json
    $postTarget = @($postPolicy.standaloneModuleTargets | Where-Object {
        [string] $_.targetId -ceq $targetId
    })
    if ($postTarget.Count -ne 1 -or
        (Get-Sha256 -Path ([string] $postTarget[0].approvalRunnerPath)) -cne $script:runnerSha256 -or
        (Get-Sha256 -Path ([string] $postTarget[0].approvalPolicyPath)) -cne
            [string] $postflight.expectedApprovalPolicySha256) {
        throw [InvalidOperationException]::new('Installed approval profile binding differs.')
    }
}

function Invoke-RunnerScenario {
    param(
        [Parameter(Mandatory = $true)][string] $Label,
        [Parameter()][switch] $Reseal,
        [Parameter()][ValidateSet(
            'normal', 'expired', 'wrong_user', 'wrong_host', 'bad_signature', 'drift', 'risk', 'lock'
        )]
        [string] $Mode = 'normal',
        [Parameter()][ValidateRange(0, 2)][int] $FailPostflightAt = 0,
        [Parameter()][switch] $CrashActivate,
        [Parameter(Mandatory = $true)][int] $ExpectedExitCode
    )
    $script:runnerScenarioLabel = $Label
    $script:runnerProcessExitCode = -1
    $script:runnerStatusWritten = $false
    $script:runnerStatusExitCode = -1
    $script:runnerStatusOutcome = ''
    $script:runnerStatusFailureCode = ''
    $script:runnerStatusActivationAttempted = $false
    $script:runnerStatusMutationAttempted = $false
    $script:runnerStatusRollbackAttempted = $false
    $script:runnerStatusRollbackSucceeded = $false
    $script:runnerStandardErrorBytes = 0
    $script:runnerErrorType = ''
    $script:runnerErrorId = ''
    $script:runnerErrorCategory = ''
    $script:runnerErrorLine = 0
    $script:runnerErrorColumn = 0
    $script:runnerErrorCommand = ''
    $scenarioRoot = Join-Path $script:scratchRoot $Label
    [IO.Directory]::CreateDirectory($scenarioRoot) | Out-Null
    Set-ScratchAcl -Path $scenarioRoot
    $candidatePath = Join-Path $scenarioRoot 'candidate'
    $stateRoot = Join-Path $scenarioRoot 'approval-state'
    [IO.Directory]::CreateDirectory($candidatePath) | Out-Null
    [IO.Directory]::CreateDirectory($stateRoot) | Out-Null
    $manifestPath = Join-Path $scenarioRoot 'deployment.json'
    $transactionPath = Join-Path $scenarioRoot 'module-transaction.json'
    $transferPath = Join-Path $scenarioRoot 'package-transfer.json'
    $adapterPolicyPath = Join-Path $scenarioRoot 'adapter-policy.json'
    $channelPolicyPath = Join-Path $scenarioRoot 'channel-policy.json'
    $secretPath = Join-Path $scenarioRoot 'approval-secret.json'
    $evidencePath = Join-Path $scenarioRoot 'qualification.json'
    $approvalPolicyPath = Join-Path $scenarioRoot 'approval-policy.json'
    $credentialPath = Join-Path $scenarioRoot 'credential.json'
    $deploymentStatusPath = Join-Path $scenarioRoot 'deployment-status.json'
    $runnerStatusPath = Join-Path $scenarioRoot 'runner-status.json'
    $runnerStandardErrorPath = Join-Path $scenarioRoot 'runner-standard-error.local.txt'
    $activePath = Join-Path $scenarioRoot 'synthetic-active.local.txt'
    $previousPackage = Get-TextSha256 -Text ('previous:' + $Label)
    $candidatePackage = Get-TextSha256 -Text ('candidate:' + $Label)
    $packageSha256 = Get-TextSha256 -Text ('archive:' + $Label)
    $deploymentId = 'saef-qualification-' + $Label.Replace('_', '-')
    $targetId = 'saef-qualification-target'
    $adapterProfile = 'saef-qualification-adapter-v1'

    $script:runnerScenarioPhase = 'fixture_files'
    Write-Json -Path $transactionPath -Value ([ordered]@{ formatVersion = 1 })
    $manifest = [ordered]@{
        formatVersion = 1
        deploymentKind = 'standalone-module'
        deploymentId = $deploymentId
        targetDirectoryName = 'saef-qualification-target'
        module = [ordered]@{
            targetId = $targetId
            libraryGuid = '{00000000-0000-0000-0000-000000000001}'
            packageIdentitySha256 = $candidatePackage
            transactionContractSha256 = Get-Sha256 -Path $transactionPath
        }
        files = @()
    }
    Write-Json -Path $manifestPath -Value $manifest
    Write-Json -Path $transferPath -Value ([ordered]@{
        formatVersion = 1
        packageSha256 = $packageSha256
        packageBytes = 1024
    })
    $adapterPolicy = [ordered]@{
        formatVersion = 1
        adapterProfile = $adapterProfile
        expectedActivePackageIdentitySha256 = $previousPackage
    }
    Write-Json -Path $adapterPolicyPath -Value $adapterPolicy
    Write-Json -Path $channelPolicyPath -Value ([ordered]@{
        formatVersion = 1
        expectedChildProcessContractSha256 = $script:childProcessContractSha256
    })
    Write-Json -Path $secretPath -Value ([ordered]@{
        formatVersion = 1
        encoding = 'base64'
        secretBase64 = [Convert]::ToBase64String($script:secretBytes)
    })
    Write-Json -Path $credentialPath -Value ([ordered]@{ formatVersion = 1 })
    Write-Json -Path $deploymentStatusPath -Value ([ordered]@{ formatVersion = 1 })
    [IO.File]::WriteAllText($activePath, $previousPackage, [Text.UTF8Encoding]::new($false))

    $script:runnerScenarioPhase = 'qualification_evidence'
    $syntheticAdapterSha256 = Get-Sha256 -Path $SyntheticAdapterPath
    $syntheticResealSha256 = if ([bool] $Reseal) { Get-Sha256 -Path $SyntheticResealPath } else { '' }
    $fixtureEvidence = New-QualificationEvidence -AdapterSha256 $syntheticAdapterSha256 `
        -ResealSha256 $syntheticResealSha256
    Write-Json -Path $evidencePath -Value $fixtureEvidence
    $qualificationEvidenceSha256 = Get-Sha256 -Path $evidencePath
    $script:runnerScenarioPhase = 'approval_policy'
    $approvalPolicy = [ordered]@{
        formatVersion = 1
        runnerProfile = 'saef-channel-v8-one-click-v1'
        targetId = $targetId
        adapterProfile = $adapterProfile
        qualificationProfile = 'saef-windows-powershell-5.1-qualification-v1'
        postflightProfile = 'saef-qualification-health-v1'
        approvalStateRoot = $stateRoot
        approvalSecretPath = $secretPath
        qualificationEvidencePath = $evidencePath
        expectedQualificationEvidenceSha256 = $qualificationEvidenceSha256
        expectedChildProcessContractSha256 = $script:childProcessContractSha256
        channelHostBindingSha256 = $script:channelHostBindingSha256
        approverIdentitySha256 = Get-TextSha256 -Text $script:approverIdentity
        executionHostIdentitySha256 = Get-TextSha256 -Text $script:executionHostIdentity
        maximumStateFiles = 32
        resealEnabled = [bool] $Reseal
        resealScriptPath = if ([bool] $Reseal) { $SyntheticResealPath } else { '' }
        expectedResealScriptSha256 = $syntheticResealSha256
    }
    Write-Json -Path $approvalPolicyPath -Value $approvalPolicy
    Set-ScratchAcl -Path $scenarioRoot

    $script:runnerScenarioPhase = 'approval_plan'
    $operations = if ([bool] $Reseal) {
        @('qualify', 'stage', 'preflight', 'activate', 'postflight', 'reseal', 'final_postflight', 'rollback')
    } else {
        @('qualify', 'stage', 'preflight', 'activate', 'postflight', 'rollback')
    }
    $riskScope = [ordered]@{
        allowlistChange = $false
        serviceRestart = $false
        providerContact = $false
        publication = $false
        retentionDeletion = $false
        activeIdentityReseal = [bool] $Reseal
    }
    if ($Mode -ceq 'risk') {
        $riskScope.allowlistChange = $true
    }
    $plan = [ordered]@{
        formatVersion = 1
        channelVersion = 8
        deploymentId = $deploymentId
        targetId = $targetId
        adapterProfile = $adapterProfile
        qualificationProfile = 'saef-windows-powershell-5.1-qualification-v1'
        postflightProfile = 'saef-qualification-health-v1'
        package = [ordered]@{ sha256 = $packageSha256; bytes = 1024 }
        operations = $operations
        expectedBaselineIdentities = [ordered]@{
            activePackageSha256 = $previousPackage
            adapterPolicySha256 = Get-Sha256 -Path $adapterPolicyPath
            channelPolicySha256 = Get-Sha256 -Path $channelPolicyPath
        }
        channelHostBindingSha256 = $script:channelHostBindingSha256
        riskScope = $riskScope
    }
    $now = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $issuedAt = if ($Mode -ceq 'expired') { $now - 600 } else { $now }
    $expiresAt = if ($Mode -ceq 'expired') { $now - 300 } else { $now + 300 }
    $proofUser = if ($Mode -ceq 'wrong_user') { 'wrong-approver' } else { $script:approverIdentity }
    $proofHost = if ($Mode -ceq 'wrong_host') { 'wrong-controller' } else { $script:executionHostIdentity }
    $script:runnerScenarioPhase = 'approval_envelope'
    $envelope = New-ApprovalEnvelope -Plan ([pscustomobject] $plan) -IssuedAt $issuedAt `
        -ExpiresAt $expiresAt -ApproverIdentity $proofUser -ExecutionHostIdentity $proofHost `
        -InvalidSignature:($Mode -ceq 'bad_signature')
    if ($Mode -ceq 'drift') {
        $script:runnerScenarioPhase = 'baseline_drift_fixture'
        $adapterPolicy['drift'] = $true
        Write-Json -Path $adapterPolicyPath -Value $adapterPolicy
    }

    $heldMutex = $null
    $heldMutexAcquired = $false
    if ($Mode -ceq 'lock') {
        $heldMutex = [Threading.Mutex]::new($false, 'Global\SAEF.DeploymentApproval')
        $heldMutexAcquired = $heldMutex.WaitOne(0)
        if (-not $heldMutexAcquired) {
            throw [InvalidOperationException]::new('Qualification could not acquire approval lock fixture.')
        }
    }
    $script:runnerScenarioPhase = 'runner_environment'
    try {
        $script:runnerScenarioPhase = 'process_start'
        $runnerArguments = @(
            '-ChildProcessContractPath', $ChildProcessContractPath,
            '-ExpectedChildProcessContractSha256', $script:childProcessContractSha256,
            '-ChannelPolicyPath', $channelPolicyPath,
            '-ManifestPath', $manifestPath,
            '-CandidatePath', $candidatePath,
            '-TransactionContractPath', $transactionPath,
            '-PackageTransferPath', $transferPath,
            '-AdapterPath', $SyntheticAdapterPath,
            '-AdapterPolicyPath', $adapterPolicyPath,
            '-ApprovalPolicyPath', $approvalPolicyPath,
            '-RpcUri', 'http://127.0.0.1:3777/api/',
            '-CredentialPath', $credentialPath,
            '-DeploymentUser', 'saefdeploy',
            '-DeploymentStatusPath', $deploymentStatusPath,
            '-StatusPath', $runnerStatusPath
        )
        $runnerEnvironment = @{
            SAEF_APPROVAL_ENVELOPE = $envelope
            SAEF_APPROVAL_SYNTHETIC_FAIL_POSTFLIGHT_AT = [string] $FailPostflightAt
            SAEF_APPROVAL_SYNTHETIC_CRASH_ACTIVATE = if ([bool] $CrashActivate) { '1' } else { '0' }
        }
        $result = Invoke-SaefPowerShellChildProcess -ScriptPath $RunnerPath `
            -ExpectedScriptSha256 $script:runnerSha256 -Arguments $runnerArguments `
            -Environment $runnerEnvironment -TimeoutSeconds 180 -MaximumOutputBytes 65536
        Write-ChildStandardError -Result $result -Path $runnerStandardErrorPath
        $exitCode = [int] $result.exitCode
        Update-RunnerScenarioDiagnostics -Label $Label -ProcessExitCode $exitCode `
            -StatusPath $runnerStatusPath -StandardErrorPath $runnerStandardErrorPath
        $script:runnerScenarioPhase = 'process_result'
    } finally {
        if ($heldMutexAcquired) {
            $heldMutex.ReleaseMutex()
        }
        if ($null -ne $heldMutex) {
            $heldMutex.Dispose()
        }
    }
    $script:runnerScenarioPhase = 'result_validation'
    if ($exitCode -ne $ExpectedExitCode -or
        -not (Test-Path -LiteralPath $runnerStatusPath -PathType Leaf)) {
        throw [InvalidOperationException]::new('Qualification runner outcome differs: ' + $Label)
    }
    $status = Get-Content -LiteralPath $runnerStatusPath -Raw | ConvertFrom-Json
    if ([int] $status.exitCode -ne $ExpectedExitCode) {
        throw [InvalidOperationException]::new('Qualification runner status differs: ' + $Label)
    }
    if ($ExpectedExitCode -eq 0 -and [string] $status.outcome -cne 'activated') {
        throw [InvalidOperationException]::new('Qualification activation did not complete: ' + $Label)
    }
    if ($ExpectedExitCode -eq 30) {
        if ([string] $status.outcome -cne 'rolled_back' -or
            -not [bool] $status.rollbackSucceeded -or
            (Get-Content -LiteralPath $activePath -Raw).Trim() -cne $previousPackage) {
            throw [InvalidOperationException]::new('Qualification rollback was not byte-exact: ' + $Label)
        }
    }
    return [pscustomobject]@{
        envelope = $envelope
        runnerStatusPath = $runnerStatusPath
        expectedExitCode = $ExpectedExitCode
        scenarioRoot = $scenarioRoot
    }
}

function Invoke-ReplayCase {
    param(
        [Parameter(Mandatory = $true)] $Scenario,
        [Parameter()][int] $ExpectedExitCode = 10
    )
    $scenarioRoot = [string] $Scenario.scenarioRoot
    $runnerStatusPath = [string] $Scenario.runnerStatusPath
    $arguments = @(
        '-ChildProcessContractPath', $ChildProcessContractPath,
        '-ExpectedChildProcessContractSha256', $script:childProcessContractSha256,
        '-ChannelPolicyPath', (Join-Path $scenarioRoot 'channel-policy.json'),
        '-ManifestPath', (Join-Path $scenarioRoot 'deployment.json'),
        '-CandidatePath', (Join-Path $scenarioRoot 'candidate'),
        '-TransactionContractPath', (Join-Path $scenarioRoot 'module-transaction.json'),
        '-PackageTransferPath', (Join-Path $scenarioRoot 'package-transfer.json'),
        '-AdapterPath', $SyntheticAdapterPath,
        '-AdapterPolicyPath', (Join-Path $scenarioRoot 'adapter-policy.json'),
        '-ApprovalPolicyPath', (Join-Path $scenarioRoot 'approval-policy.json'),
        '-RpcUri', 'http://127.0.0.1:3777/api/',
        '-CredentialPath', (Join-Path $scenarioRoot 'credential.json'),
        '-DeploymentUser', 'saefdeploy',
        '-DeploymentStatusPath', (Join-Path $scenarioRoot 'deployment-status.json'),
        '-StatusPath', $runnerStatusPath
    )
    $result = Invoke-SaefPowerShellChildProcess -ScriptPath $RunnerPath `
        -ExpectedScriptSha256 $script:runnerSha256 -Arguments $arguments `
        -Environment @{ SAEF_APPROVAL_ENVELOPE = [string] $Scenario.envelope } `
        -TimeoutSeconds 180 -MaximumOutputBytes 65536
    if ([int] $result.exitCode -ne $ExpectedExitCode -or
        -not (Test-Path -LiteralPath $runnerStatusPath -PathType Leaf)) {
        throw [InvalidOperationException]::new('Approval reinvocation outcome differs.')
    }
    $status = Get-Content -LiteralPath $runnerStatusPath -Raw | ConvertFrom-Json
    if ([int] $status.exitCode -ne $ExpectedExitCode -or
        ($ExpectedExitCode -eq 0 -and [string] $status.outcome -cne 'activated')) {
        throw [InvalidOperationException]::new('Approval reinvocation status differs.')
    }
}

function Write-FinalStatus {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string] $ErrorType
    )
    $record = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'windows_qualification'
        outcome = $Outcome
        exitCode = $ExitCode
        expectedChannelVersion = 8
        childProcessContractSha256 = $script:childProcessContractSha256
        runnerSha256 = $script:runnerSha256
        adapterSha256 = $script:adapterSha256
        resealSha256 = $script:resealSha256
        positiveCaseCount = $script:positiveCaseCount
        negativeCaseCount = $script:negativeCaseCount
        scratchMutationAttempted = [bool] $script:scratchMutationAttempted
        scratchCleanupSucceeded = [bool] $script:scratchCleanupSucceeded
        productionMutationAttempted = $false
        serviceRestartAttempted = $false
        failedCheck = if ($Outcome -ceq 'passed') { '' } else { $script:failedCheck }
        errorType = $ErrorType
    }
    if ($Outcome -cne 'passed') {
        $record['profileInstallerPhase'] = $script:profileInstallerPhase
        $record['profileInstallerProcessExitCode'] = $script:profileInstallerProcessExitCode
        $record['profileInstallerStatusWritten'] = [bool] $script:profileInstallerStatusWritten
        $record['profileInstallerStatusExitCode'] = $script:profileInstallerStatusExitCode
        $record['profileInstallerOutcome'] = $script:profileInstallerOutcome
        $record['profileInstallerFailedStep'] = $script:profileInstallerFailedStep
        $record['profileInstallerErrorType'] = $script:profileInstallerErrorType
        $record['profileInstallerRollbackAttempted'] = [bool] $script:profileInstallerRollbackAttempted
        $record['profileInstallerRollbackSucceeded'] = [bool] $script:profileInstallerRollbackSucceeded
        $record['profileInstallerStandardErrorBytes'] = $script:profileInstallerStandardErrorBytes
        $record['runnerScenarioLabel'] = $script:runnerScenarioLabel
        $record['runnerScenarioPhase'] = $script:runnerScenarioPhase
        $record['runnerProcessExitCode'] = $script:runnerProcessExitCode
        $record['runnerStatusWritten'] = [bool] $script:runnerStatusWritten
        $record['runnerStatusExitCode'] = $script:runnerStatusExitCode
        $record['runnerStatusOutcome'] = $script:runnerStatusOutcome
        $record['runnerStatusFailureCode'] = $script:runnerStatusFailureCode
        $record['runnerStatusActivationAttempted'] = [bool] $script:runnerStatusActivationAttempted
        $record['runnerStatusMutationAttempted'] = [bool] $script:runnerStatusMutationAttempted
        $record['runnerStatusRollbackAttempted'] = [bool] $script:runnerStatusRollbackAttempted
        $record['runnerStatusRollbackSucceeded'] = [bool] $script:runnerStatusRollbackSucceeded
        $record['runnerStandardErrorBytes'] = $script:runnerStandardErrorBytes
        $record['runnerErrorType'] = $script:runnerErrorType
        $record['runnerErrorId'] = $script:runnerErrorId
        $record['runnerErrorCategory'] = $script:runnerErrorCategory
        $record['runnerErrorLine'] = $script:runnerErrorLine
        $record['runnerErrorColumn'] = $script:runnerErrorColumn
        $record['runnerErrorCommand'] = $script:runnerErrorCommand
        $record['errorId'] = $script:finalErrorId
        $record['errorCategory'] = $script:finalErrorCategory
        $record['errorLine'] = $script:finalErrorLine
        $record['errorColumn'] = $script:finalErrorColumn
        $record['errorCommand'] = $script:finalErrorCommand
    }
    Write-Json -Path $StatusPath -Value $record
}

$finalOutcome = 'failed'
$finalExitCode = $ExitFailed
$finalErrorType = ''
try {
    $script:failedCheck = 'elevation'
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [Security.SecurityException]::new('Windows qualification requires elevation.')
    }

    $script:failedCheck = 'source_identity'
    foreach ($path in @(
        $RunnerPath, $AdapterPath, $ResealPath, $ProfileInitializerPath,
        $SyntheticAdapterPath, $SyntheticResealPath, $ChildProcessContractPath
    )) {
        Assert-PlainLeaf -Path $path
        Assert-PowerShellSyntax -Path $path
    }
    $script:runnerSha256 = Get-Sha256 -Path $RunnerPath
    $script:adapterSha256 = Get-Sha256 -Path $AdapterPath
    $script:resealSha256 = Get-Sha256 -Path $ResealPath
    $script:childProcessContractSha256 = Get-Sha256 -Path $ChildProcessContractPath
    if ($script:runnerSha256 -cne $ExpectedRunnerSha256 -or
        $script:adapterSha256 -cne $ExpectedAdapterSha256 -or
        $script:resealSha256 -cne $ExpectedResealSha256 -or
        $script:childProcessContractSha256 -cne $ExpectedChildProcessContractSha256) {
        throw [Security.SecurityException]::new('Qualified source identity differs.')
    }

    $script:failedCheck = 'culture_invariant_canonicalization'
    Assert-CultureInvariantCanonicalization

    $script:failedCheck = 'scratch_setup'
    $scratchParent = Join-Path $env:ProgramData 'SAEF\QualificationScratch'
    if (-not (Test-Path -LiteralPath $scratchParent -PathType Container)) {
        [IO.Directory]::CreateDirectory($scratchParent) | Out-Null
    }
    Set-ScratchAcl -Path $scratchParent
    $script:scratchRoot = Join-Path $scratchParent ([Guid]::NewGuid().ToString('N'))
    [IO.Directory]::CreateDirectory($script:scratchRoot) | Out-Null
    Set-ScratchAcl -Path $script:scratchRoot
    $script:scratchMutationAttempted = $true
    $script:channelHostBindingSha256 = Get-TextSha256 -Text 'saef-qualification-channel-host'
    $qualifiedRunnerPath = Join-Path $script:scratchRoot 'approval-runner.ps1'
    $qualifiedProfileInitializerPath = Join-Path $script:scratchRoot 'approval-profile-initializer.ps1'
    $qualifiedSyntheticAdapterPath = Join-Path $script:scratchRoot 'synthetic-adapter.ps1'
    $qualifiedSyntheticResealPath = Join-Path $script:scratchRoot 'synthetic-reseal.ps1'
    $qualifiedChildProcessContractPath = Join-Path $script:scratchRoot 'child-process-contract.ps1'
    Copy-Item -LiteralPath $RunnerPath -Destination $qualifiedRunnerPath
    Copy-Item -LiteralPath $ProfileInitializerPath -Destination $qualifiedProfileInitializerPath
    Copy-Item -LiteralPath $SyntheticAdapterPath -Destination $qualifiedSyntheticAdapterPath
    Copy-Item -LiteralPath $SyntheticResealPath -Destination $qualifiedSyntheticResealPath
    Copy-Item -LiteralPath $ChildProcessContractPath -Destination $qualifiedChildProcessContractPath
    Set-ScratchAcl -Path $script:scratchRoot
    $RunnerPath = $qualifiedRunnerPath
    $ProfileInitializerPath = $qualifiedProfileInitializerPath
    $SyntheticAdapterPath = $qualifiedSyntheticAdapterPath
    $SyntheticResealPath = $qualifiedSyntheticResealPath
    $ChildProcessContractPath = $qualifiedChildProcessContractPath
    . $ChildProcessContractPath
    if ($null -eq (Get-Command Invoke-SaefPowerShellChildProcess -CommandType Function `
            -ErrorAction SilentlyContinue)) {
        throw [InvalidOperationException]::new('Child process contract function is unavailable.')
    }

    $script:failedCheck = 'profile_installer_positive'
    Invoke-ProfileInstallerScenario
    $script:positiveCaseCount++

    $script:failedCheck = 'base_positive'
    $base = Invoke-RunnerScenario -Label 'base-positive' -ExpectedExitCode 0
    $script:positiveCaseCount++
    $script:failedCheck = 'replay_negative'
    Invoke-ReplayCase -Scenario $base
    $script:negativeCaseCount++

    $script:failedCheck = 'reseal_positive'
    $null = Invoke-RunnerScenario -Label 'reseal-positive' -Reseal -ExpectedExitCode 0
    $script:positiveCaseCount++

    $script:failedCheck = 'pre_mutation_resume'
    $resume = Invoke-RunnerScenario -Label 'pre-mutation-resume' -CrashActivate -ExpectedExitCode 10
    Invoke-ReplayCase -Scenario $resume -ExpectedExitCode 0
    $script:positiveCaseCount++

    foreach ($negative in @(
        @{ label = 'expired'; mode = 'expired' },
        @{ label = 'wrong-user'; mode = 'wrong_user' },
        @{ label = 'wrong-host'; mode = 'wrong_host' },
        @{ label = 'bad-signature'; mode = 'bad_signature' },
        @{ label = 'baseline-drift'; mode = 'drift' },
        @{ label = 'forbidden-risk'; mode = 'risk' },
        @{ label = 'lock-contention'; mode = 'lock' }
    )) {
        $script:failedCheck = [string] $negative.label
        $null = Invoke-RunnerScenario -Label ([string] $negative.label) `
            -Mode ([string] $negative.mode) -ExpectedExitCode 10
        $script:negativeCaseCount++
    }

    $script:failedCheck = 'postflight_rollback'
    $null = Invoke-RunnerScenario -Label 'postflight-rollback' -FailPostflightAt 1 `
        -ExpectedExitCode 30
    $script:positiveCaseCount++
    $script:failedCheck = 'reseal_rollback'
    $null = Invoke-RunnerScenario -Label 'reseal-rollback' -Reseal -FailPostflightAt 2 `
        -ExpectedExitCode 30
    $script:positiveCaseCount++

    if ($script:positiveCaseCount -ne 6 -or $script:negativeCaseCount -ne 8) {
        throw [InvalidOperationException]::new('Windows qualification case count differs.')
    }

    $finalOutcome = 'passed'
    $finalExitCode = $ExitSuccess
} catch {
    $finalErrorType = $_.Exception.GetType().FullName
    $script:finalErrorId = [string] $_.FullyQualifiedErrorId
    $script:finalErrorCategory = [string] $_.CategoryInfo.Category
    $script:finalErrorLine = [int] $_.InvocationInfo.ScriptLineNumber
    $script:finalErrorColumn = [int] $_.InvocationInfo.OffsetInLine
    $script:finalErrorCommand = if ($null -ne $_.InvocationInfo.MyCommand) {
        [string] $_.InvocationInfo.MyCommand.Name
    } else { '' }
} finally {
    if (-not [string]::IsNullOrEmpty($script:scratchRoot) -and
        (Test-Path -LiteralPath $script:scratchRoot -PathType Container)) {
        try {
            Remove-Item -LiteralPath $script:scratchRoot -Recurse -Force
            $script:scratchCleanupSucceeded = -not (Test-Path -LiteralPath $script:scratchRoot)
        } catch {
            $script:scratchCleanupSucceeded = $false
        }
    } else {
        $script:scratchCleanupSucceeded = $true
    }
    if (-not $script:scratchCleanupSucceeded) {
        $finalOutcome = 'failed'
        $finalExitCode = $ExitFailed
        if ([string]::IsNullOrEmpty($finalErrorType)) {
            $script:failedCheck = 'scratch_cleanup'
            $finalErrorType = 'System.IO.IOException'
        }
    }
    try {
        Write-FinalStatus -Outcome $finalOutcome -ExitCode $finalExitCode -ErrorType $finalErrorType
    } finally {
        [Array]::Clear($script:secretBytes, 0, $script:secretBytes.Length)
    }
}

exit $finalExitCode
