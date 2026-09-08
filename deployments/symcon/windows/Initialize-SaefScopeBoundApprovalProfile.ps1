[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_.-]{1,64}$')]
    [string] $DeploymentUser,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^saef-[a-z0-9][a-z0-9.-]{0,63}$')]
    [string] $TargetId,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^saef-[a-z0-9][a-z0-9.-]{0,63}$')]
    [string] $QualificationProfile,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^saef-[a-z0-9][a-z0-9.-]{0,63}$')]
    [string] $PostflightProfile,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ChannelHostBindingSha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ApproverIdentitySha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExecutionHostIdentitySha256,

    [Parameter(Mandatory = $true)]
    [string] $ApprovalSecretRecordPath,

    [Parameter(Mandatory = $true)]
    [string] $QualificationEvidencePath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedQualificationEvidenceSha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedRunnerSha256,

    [Parameter()]
    [string] $RunnerSourcePath = (Join-Path $PSScriptRoot 'Invoke-SaefScopeBoundApprovalRunner.ps1'),

    [Parameter()]
    [switch] $ResealEnabled,

    [Parameter()]
    [string] $ResealSourcePath = '',

    [Parameter()]
    [ValidatePattern('^(?:|[a-f0-9]{64})$')]
    [string] $ExpectedResealScriptSha256 = '',

    [Parameter()]
    [ValidateRange(1, 4096)]
    [int] $MaximumStateFiles = 256,

    [Parameter()]
    [string] $ChannelInstallRoot = (Join-Path $env:ProgramData 'SAEF\DeploymentChannel'),

    [Parameter()]
    [string] $ApprovalRoot = (Join-Path $env:ProgramData 'SAEF\DeploymentApprovals'),

    [Parameter()]
    [switch] $PreflightOnly,

    [Parameter()]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($StatusPath)) {
    $StatusPath = Join-Path $PSScriptRoot 'scope-bound-approval-profile-status.local.json'
}

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitInstallFailed = 20
$script:mutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:failedStep = 'initialization'
$script:fileSnapshots = @()
$script:directorySnapshots = @()
$script:createdDirectories = @()

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Get-BytesSha256 {
    param([Parameter(Mandatory = $true)][byte[]] $Bytes)
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        return ([BitConverter]::ToString($algorithm.ComputeHash($Bytes))).Replace('-', '').ToLowerInvariant()
    } finally {
        $algorithm.Dispose()
    }
}

function Assert-PlainLeaf {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )
    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [IO.FileNotFoundException]::new('Required bounded source is missing.')
    }
    $item = Get-Item -LiteralPath $Path -Force
    if ([long] $item.Length -lt 2 -or [long] $item.Length -gt $MaximumBytes -or
        (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.IOException]::new('Required bounded source is unsafe.')
    }
}

function Assert-PlainDirectory {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Container) -or
        (((Get-Item -LiteralPath $Path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.DirectoryNotFoundException]::new('Required plain directory is missing.')
    }
}

function Write-Status {
    param(
        [Parameter(Mandatory = $true)][string] $Phase,
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][hashtable] $Details = @{}
    )
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = $Phase
        outcome = $Outcome
        exitCode = $ExitCode
        targetId = $TargetId
        mutationAttempted = [bool] $script:mutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
    }
    foreach ($name in $Details.Keys) {
        $status[$name] = $Details[$name]
    }
    $directory = Split-Path -Parent $StatusPath
    Assert-PlainDirectory -Path $directory
    $temporary = Join-Path $directory ('.saef-approval-profile-' + [Guid]::NewGuid().ToString('N') + '.tmp')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            ($status | ConvertTo-Json -Depth 5) + [Environment]::NewLine,
            [Text.UTF8Encoding]::new($false)
        )
        if (Test-Path -LiteralPath $StatusPath -PathType Leaf) {
            [IO.File]::Replace($temporary, $StatusPath, $null)
        } else {
            [IO.File]::Move($temporary, $StatusPath)
        }
    } finally {
        if (Test-Path -LiteralPath $temporary -PathType Leaf) {
            Remove-Item -LiteralPath $temporary -Force
        }
    }
}

function Assert-Elevated {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [Security.SecurityException]::new('Approval profile installation requires elevation.')
    }
}

function Assert-PowerShellSyntax {
    param([Parameter(Mandatory = $true)][string] $Path)
    $tokens = $null
    $parseErrors = $null
    [Management.Automation.Language.Parser]::ParseFile($Path, [ref] $tokens, [ref] $parseErrors) | Out-Null
    if (@($parseErrors).Count -ne 0) {
        throw [InvalidOperationException]::new('Approval profile PowerShell source is invalid.')
    }
}

function Set-RestrictedDirectoryAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $DeploymentIdentity,
        [Parameter()][ValidateSet('F', 'RX')][string] $DeploymentRights = 'F'
    )
    $system = [Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $administrators = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    $deployment = [Security.Principal.SecurityIdentifier]::new($DeploymentIdentity)
    $inheritance = [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor
        [Security.AccessControl.InheritanceFlags]::ObjectInherit
    $propagation = [Security.AccessControl.PropagationFlags]::None
    $allow = [Security.AccessControl.AccessControlType]::Allow
    $fullControl = [Security.AccessControl.FileSystemRights]::FullControl
    $readAndExecute = [Security.AccessControl.FileSystemRights]::ReadAndExecute -bor
        [Security.AccessControl.FileSystemRights]::Synchronize
    $deploymentDirectoryRights = if ($DeploymentRights -ceq 'F') {
        $fullControl
    } else {
        $readAndExecute
    }
    $acl = [Security.AccessControl.DirectorySecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetOwner($administrators)
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        $system, $fullControl, $inheritance, $propagation, $allow
    ))
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        $administrators, $fullControl, $inheritance, $propagation, $allow
    ))
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        $deployment,
        $deploymentDirectoryRights,
        $inheritance,
        $propagation,
        $allow
    ))
    Set-Acl -LiteralPath $Path -AclObject $acl
    Assert-RestrictedAcl -Path $Path -DeploymentIdentity $DeploymentIdentity `
        -DeploymentRights $DeploymentRights -Directory
}

function Assert-RestrictedAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $DeploymentIdentity,
        [Parameter()][ValidateSet('F', 'R', 'RX')][string] $DeploymentRights = 'R',
        [Parameter()][switch] $Directory
    )
    $fullControl = [Security.AccessControl.FileSystemRights]::FullControl
    $read = [Security.AccessControl.FileSystemRights]::Read -bor
        [Security.AccessControl.FileSystemRights]::Synchronize
    $readAndExecute = [Security.AccessControl.FileSystemRights]::ReadAndExecute -bor
        [Security.AccessControl.FileSystemRights]::Synchronize
    $expected = @{
        'S-1-5-18' = $fullControl
        'S-1-5-32-544' = $fullControl
    }
    $expected[$DeploymentIdentity] = if ($DeploymentRights -ceq 'F') {
        $fullControl
    } elseif ($DeploymentRights -ceq 'RX') {
        $readAndExecute
    } else {
        $read
    }
    $acl = Get-Acl -LiteralPath $Path
    $owner = $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value
    $rules = @($acl.Access)
    if (-not $acl.AreAccessRulesProtected -or $owner -cne 'S-1-5-32-544' -or
        $rules.Count -ne 3) {
        throw [Security.SecurityException]::new('Approval ACL boundary differs.')
    }
    foreach ($rule in $rules) {
        $sid = $rule.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        $expectedInheritance = if ([bool] $Directory) {
            [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor
                [Security.AccessControl.InheritanceFlags]::ObjectInherit
        } else {
            [Security.AccessControl.InheritanceFlags]::None
        }
        if (-not $expected.ContainsKey($sid) -or $rule.IsInherited -or
            $rule.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow -or
            [int] $rule.FileSystemRights -ne [int] $expected[$sid] -or
            $rule.InheritanceFlags -ne $expectedInheritance -or
            $rule.PropagationFlags -ne [Security.AccessControl.PropagationFlags]::None) {
            throw [Security.SecurityException]::new('Approval ACL entry differs.')
        }
    }
}

function Set-RestrictedRuntimeFileAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $DeploymentIdentity,
        [Parameter()][ValidateSet('R', 'RX')][string] $DeploymentRights = 'R'
    )
    $system = [Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $administrators = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    $deployment = [Security.Principal.SecurityIdentifier]::new($DeploymentIdentity)
    $allow = [Security.AccessControl.AccessControlType]::Allow
    $fullControl = [Security.AccessControl.FileSystemRights]::FullControl
    $deploymentFileRights = if ($DeploymentRights -ceq 'RX') {
        [Security.AccessControl.FileSystemRights]::ReadAndExecute -bor
            [Security.AccessControl.FileSystemRights]::Synchronize
    } else {
        [Security.AccessControl.FileSystemRights]::Read -bor
            [Security.AccessControl.FileSystemRights]::Synchronize
    }
    $acl = [Security.AccessControl.FileSecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetOwner($administrators)
    foreach ($rule in @(
        [Security.AccessControl.FileSystemAccessRule]::new($system, $fullControl, $allow),
        [Security.AccessControl.FileSystemAccessRule]::new($administrators, $fullControl, $allow),
        [Security.AccessControl.FileSystemAccessRule]::new($deployment, $deploymentFileRights, $allow)
    )) {
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Path -AclObject $acl
    Assert-RestrictedAcl -Path $Path -DeploymentIdentity $DeploymentIdentity `
        -DeploymentRights $DeploymentRights
}

function Get-FileSnapshot {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return [pscustomobject]@{ path = $Path; exists = $false; bytes = $null; acl = $null }
    }
    return [pscustomobject]@{
        path = $Path
        exists = $true
        bytes = [IO.File]::ReadAllBytes($Path)
        acl = Get-Acl -LiteralPath $Path
    }
}

function Restore-Snapshots {
    foreach ($snapshot in @($script:fileSnapshots)) {
        if ([bool] $snapshot.exists) {
            [IO.File]::WriteAllBytes([string] $snapshot.path, [byte[]] $snapshot.bytes)
            Set-Acl -LiteralPath ([string] $snapshot.path) -AclObject $snapshot.acl
        } elseif (Test-Path -LiteralPath ([string] $snapshot.path) -PathType Leaf) {
            Remove-Item -LiteralPath ([string] $snapshot.path) -Force
        }
    }
    foreach ($directory in @($script:createdDirectories | Sort-Object Length -Descending)) {
        if ((Test-Path -LiteralPath $directory -PathType Container) -and
            @(Get-ChildItem -LiteralPath $directory -Force).Count -eq 0) {
            Remove-Item -LiteralPath $directory -Force
        }
    }
    foreach ($snapshot in @($script:directorySnapshots)) {
        if ([bool] $snapshot.exists -and
            (Test-Path -LiteralPath ([string] $snapshot.path) -PathType Container)) {
            Set-Acl -LiteralPath ([string] $snapshot.path) -AclObject $snapshot.acl
        }
    }
}

function Write-AtomicBytes {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][byte[]] $Bytes
    )
    $directory = Split-Path -Parent $Path
    Assert-PlainDirectory -Path $directory
    $temporary = Join-Path $directory ('.saef-approval-profile-' + [Guid]::NewGuid().ToString('N') + '.tmp')
    $backup = Join-Path $directory ('.saef-approval-profile-' + [Guid]::NewGuid().ToString('N') + '.bak')
    try {
        [IO.File]::WriteAllBytes($temporary, $Bytes)
        if (Test-Path -LiteralPath $Path -PathType Leaf) {
            [IO.File]::Replace($temporary, $Path, $backup)
        } else {
            [IO.File]::Move($temporary, $Path)
        }
    } finally {
        if (Test-Path -LiteralPath $temporary -PathType Leaf) {
            Remove-Item -LiteralPath $temporary -Force
        }
        if (Test-Path -LiteralPath $backup -PathType Leaf) {
            Remove-Item -LiteralPath $backup -Force -ErrorAction SilentlyContinue
        }
    }
}

function Assert-ExactProperties {
    param(
        [Parameter(Mandatory = $true)] $Value,
        [Parameter(Mandatory = $true)][string[]] $Names,
        [Parameter(Mandatory = $true)][string] $Label
    )
    [string[]] $actual = @(
        $Value.PSObject.Properties | ForEach-Object { [string] $_.Name }
    )
    [string[]] $expected = @($Names | ForEach-Object { [string] $_ })
    [Array]::Sort($actual, [StringComparer]::Ordinal)
    [Array]::Sort($expected, [StringComparer]::Ordinal)
    if (($actual -join [char] 0) -cne ($expected -join [char] 0)) {
        throw [InvalidOperationException]::new($Label + ' fields differ.')
    }
}

function Get-Target {
    param([Parameter(Mandatory = $true)] $Policy)
    $targets = @($Policy.standaloneModuleTargets | Where-Object { [string] $_.targetId -ceq $TargetId })
    if ($targets.Count -ne 1) {
        throw [InvalidOperationException]::new('Approval target is not uniquely allowlisted.')
    }
    return $targets[0]
}

function Set-ObjectProperty {
    param(
        [Parameter(Mandatory = $true)] $Value,
        [Parameter(Mandatory = $true)][string] $Name,
        [Parameter(Mandatory = $true)] $PropertyValue
    )
    if ($Value.PSObject.Properties.Name -contains $Name) {
        $Value.$Name = $PropertyValue
    } else {
        $Value | Add-Member -NotePropertyName $Name -NotePropertyValue $PropertyValue
    }
}

try {
    $script:failedStep = 'elevation'
    Assert-Elevated
    $script:failedStep = 'deployment_account'
    $account = Get-LocalUser -Name $DeploymentUser -ErrorAction Stop
    if (-not $account.Enabled) {
        throw [Security.SecurityException]::new('Deployment account is disabled.')
    }
    $deploymentIdentity = $account.SID.Value

    $script:failedStep = 'source_contract'
    foreach ($source in @($RunnerSourcePath, $ApprovalSecretRecordPath, $QualificationEvidencePath)) {
        Assert-PlainLeaf -Path $source -MaximumBytes 4194304
    }
    Assert-PowerShellSyntax -Path $RunnerSourcePath
    if ((Get-Sha256 -Path $RunnerSourcePath) -cne $ExpectedRunnerSha256 -or
        (Get-Sha256 -Path $QualificationEvidencePath) -cne $ExpectedQualificationEvidenceSha256) {
        throw [Security.SecurityException]::new('Approval profile source identity differs.')
    }
    if ([bool] $ResealEnabled) {
        Assert-PlainLeaf -Path $ResealSourcePath -MaximumBytes 4194304
        Assert-PowerShellSyntax -Path $ResealSourcePath
        if ([string]::IsNullOrEmpty($ExpectedResealScriptSha256) -or
            (Get-Sha256 -Path $ResealSourcePath) -cne $ExpectedResealScriptSha256) {
            throw [Security.SecurityException]::new('Approval reseal source identity differs.')
        }
    } elseif (-not [string]::IsNullOrEmpty($ResealSourcePath) -or
        -not [string]::IsNullOrEmpty($ExpectedResealScriptSha256)) {
        throw [Security.SecurityException]::new('Disabled approval reseal profile contains source authority.')
    }

    $secretRecord = Get-Content -LiteralPath $ApprovalSecretRecordPath -Raw | ConvertFrom-Json
    Assert-ExactProperties -Value $secretRecord -Names @('formatVersion', 'encoding', 'secretBase64') `
        -Label 'Approval secret record'
    $secretBytes = [Convert]::FromBase64String([string] $secretRecord.secretBase64)
    try {
        if ($secretRecord.formatVersion -ne 1 -or [string] $secretRecord.encoding -cne 'base64' -or
            $secretBytes.Length -lt 32 -or $secretBytes.Length -gt 64) {
            throw [Security.SecurityException]::new('Approval secret record is invalid.')
        }
    } finally {
        [Array]::Clear($secretBytes, 0, $secretBytes.Length)
    }

    $evidence = Get-Content -LiteralPath $QualificationEvidencePath -Raw | ConvertFrom-Json
    Assert-ExactProperties -Value $evidence -Names @(
        'formatVersion', 'timestampUtc', 'phase', 'outcome', 'exitCode', 'expectedChannelVersion',
        'runnerSha256', 'adapterSha256', 'resealSha256', 'positiveCaseCount', 'negativeCaseCount',
        'scratchMutationAttempted', 'scratchCleanupSucceeded', 'productionMutationAttempted',
        'serviceRestartAttempted', 'failedCheck', 'errorType'
    ) -Label 'Approval qualification evidence'
    if ($evidence.formatVersion -ne 1 -or [string] $evidence.phase -cne 'windows_qualification' -or
        [string] $evidence.outcome -cne 'passed' -or [int] $evidence.exitCode -ne 0 -or
        [int] $evidence.expectedChannelVersion -ne 8 -or
        [string] $evidence.runnerSha256 -cne $ExpectedRunnerSha256 -or
        [int] $evidence.positiveCaseCount -ne 6 -or [int] $evidence.negativeCaseCount -ne 8 -or
        $evidence.scratchMutationAttempted -isnot [bool] -or
        $evidence.scratchCleanupSucceeded -isnot [bool] -or
        $evidence.productionMutationAttempted -isnot [bool] -or
        $evidence.serviceRestartAttempted -isnot [bool] -or
        [bool] $evidence.productionMutationAttempted -or [bool] $evidence.serviceRestartAttempted -or
        -not [bool] $evidence.scratchCleanupSucceeded) {
        throw [Security.SecurityException]::new('Approval qualification evidence is not acceptable.')
    }

    $script:failedStep = 'active_policy'
    $channelPolicyPath = Join-Path $ChannelInstallRoot 'deployment-channel.local.json'
    Assert-PlainLeaf -Path $channelPolicyPath -MaximumBytes 1048576
    $channelPolicy = Get-Content -LiteralPath $channelPolicyPath -Raw | ConvertFrom-Json
    if ($channelPolicy.formatVersion -ne 1 -or [string] $channelPolicy.deploymentUser -cne
        $DeploymentUser.ToLowerInvariant()) {
        throw [Security.SecurityException]::new('Active channel policy identity differs.')
    }
    $target = Get-Target -Policy $channelPolicy
    if ([string] $evidence.adapterSha256 -cne [string] $target.expectedAdapterSha256 -or
        (([bool] $ResealEnabled) -and
            [string] $evidence.resealSha256 -cne $ExpectedResealScriptSha256) -or
        ((-not [bool] $ResealEnabled) -and -not [string]::IsNullOrEmpty([string] $evidence.resealSha256))) {
        throw [Security.SecurityException]::new('Qualification target identity differs.')
    }

    $targetRoot = Split-Path -Parent ([string] $target.adapterPath)
    Assert-PlainDirectory -Path $targetRoot
    $approvalTargetRoot = Join-Path $ApprovalRoot $TargetId
    $approvalStateRoot = Join-Path $approvalTargetRoot 'state'
    $runnerPath = Join-Path $targetRoot 'approval-runner.ps1'
    $policyPath = Join-Path $targetRoot 'approval-policy.local.json'
    $installedEvidencePath = Join-Path $targetRoot 'approval-qualification.local.json'
    $installedResealPath = if ([bool] $ResealEnabled) {
        Join-Path $targetRoot 'active-identity-reseal.ps1'
    } else { '' }
    $installedSecretPath = Join-Path $approvalTargetRoot 'approval-secret.local.json'
    $installedFields = @(
        'approvalRunnerPath', 'expectedApprovalRunnerSha256',
        'approvalPolicyPath', 'expectedApprovalPolicySha256'
    )
    $presentFields = @($installedFields | Where-Object { $target.PSObject.Properties.Name -contains $_ })
    if ($presentFields.Count -notin @(0, $installedFields.Count)) {
        throw [Security.SecurityException]::new('Existing approval target profile is incomplete.')
    }

    $expectedPolicy = [ordered]@{
        formatVersion = 1
        runnerProfile = 'saef-channel-v8-one-click-v1'
        targetId = $TargetId
        adapterProfile = [string] $target.adapterProfile
        qualificationProfile = $QualificationProfile
        postflightProfile = $PostflightProfile
        approvalStateRoot = $approvalStateRoot
        approvalSecretPath = $installedSecretPath
        qualificationEvidencePath = $installedEvidencePath
        expectedQualificationEvidenceSha256 = $ExpectedQualificationEvidenceSha256
        channelHostBindingSha256 = $ChannelHostBindingSha256
        approverIdentitySha256 = $ApproverIdentitySha256
        executionHostIdentitySha256 = $ExecutionHostIdentitySha256
        maximumStateFiles = $MaximumStateFiles
        resealEnabled = [bool] $ResealEnabled
        resealScriptPath = $installedResealPath
        expectedResealScriptSha256 = if ([bool] $ResealEnabled) { $ExpectedResealScriptSha256 } else { '' }
    }
    $expectedPolicyBytes = [Text.UTF8Encoding]::new($false).GetBytes(
        ($expectedPolicy | ConvertTo-Json -Depth 5) + [Environment]::NewLine
    )
    $expectedPolicySha256 = Get-BytesSha256 -Bytes $expectedPolicyBytes
    $repairRequired = $presentFields.Count -eq 0 -or
        -not (Test-Path -LiteralPath $runnerPath -PathType Leaf) -or
        -not (Test-Path -LiteralPath $policyPath -PathType Leaf) -or
        -not (Test-Path -LiteralPath $installedEvidencePath -PathType Leaf) -or
        -not (Test-Path -LiteralPath $installedSecretPath -PathType Leaf) -or
        (Get-Sha256 -Path $runnerPath) -cne $ExpectedRunnerSha256 -or
        (Get-Sha256 -Path $policyPath) -cne $expectedPolicySha256 -or
        (Get-Sha256 -Path $installedEvidencePath) -cne $ExpectedQualificationEvidenceSha256 -or
        (([bool] $ResealEnabled) -and
            (-not (Test-Path -LiteralPath $installedResealPath -PathType Leaf) -or
                (Get-Sha256 -Path $installedResealPath) -cne $ExpectedResealScriptSha256))

    if ([bool] $PreflightOnly) {
        Write-Status -Phase 'preflight' -Outcome 'passed' -ExitCode $ExitSuccess -Details @{
            repairRequired = [bool] $repairRequired
            activeMutationAttempted = $false
            expectedRunnerSha256 = $ExpectedRunnerSha256
            expectedApprovalPolicySha256 = $expectedPolicySha256
        }
        exit $ExitSuccess
    }

    $script:failedStep = 'rollback_snapshot'
    $destinationPaths = @(
        $channelPolicyPath, $runnerPath, $policyPath, $installedEvidencePath, $installedSecretPath
    )
    if ([bool] $ResealEnabled) {
        $destinationPaths += $installedResealPath
    }
    foreach ($path in $destinationPaths) {
        $script:fileSnapshots += Get-FileSnapshot -Path $path
    }
    $script:mutationAttempted = $true

    $script:failedStep = 'approval_directories'
    foreach ($directory in @($ApprovalRoot, $approvalTargetRoot, $approvalStateRoot)) {
        $directoryExists = Test-Path -LiteralPath $directory -PathType Container
        $script:directorySnapshots += [pscustomobject]@{
            path = $directory
            exists = $directoryExists
            acl = if ($directoryExists) { Get-Acl -LiteralPath $directory } else { $null }
        }
        if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
            [IO.Directory]::CreateDirectory($directory) | Out-Null
            $script:createdDirectories += $directory
        }
        Assert-PlainDirectory -Path $directory
    }
    Set-RestrictedDirectoryAcl -Path $ApprovalRoot -DeploymentIdentity $deploymentIdentity `
        -DeploymentRights 'RX'
    Set-RestrictedDirectoryAcl -Path $approvalTargetRoot -DeploymentIdentity $deploymentIdentity `
        -DeploymentRights 'RX'
    Set-RestrictedDirectoryAcl -Path $approvalStateRoot -DeploymentIdentity $deploymentIdentity

    $script:failedStep = 'profile_files'
    Write-AtomicBytes -Path $runnerPath -Bytes ([IO.File]::ReadAllBytes($RunnerSourcePath))
    Write-AtomicBytes -Path $policyPath -Bytes $expectedPolicyBytes
    Write-AtomicBytes -Path $installedEvidencePath -Bytes ([IO.File]::ReadAllBytes($QualificationEvidencePath))
    Write-AtomicBytes -Path $installedSecretPath -Bytes ([IO.File]::ReadAllBytes($ApprovalSecretRecordPath))
    if ([bool] $ResealEnabled) {
        Write-AtomicBytes -Path $installedResealPath -Bytes ([IO.File]::ReadAllBytes($ResealSourcePath))
    }
    Set-RestrictedRuntimeFileAcl -Path $runnerPath -DeploymentIdentity $deploymentIdentity `
        -DeploymentRights 'RX'
    foreach ($path in @($policyPath, $installedEvidencePath, $installedSecretPath)) {
        Set-RestrictedRuntimeFileAcl -Path $path -DeploymentIdentity $deploymentIdentity
    }
    if ([bool] $ResealEnabled) {
        Set-RestrictedRuntimeFileAcl -Path $installedResealPath -DeploymentIdentity $deploymentIdentity `
            -DeploymentRights 'RX'
    }

    $script:failedStep = 'channel_policy'
    Set-ObjectProperty -Value $target -Name 'approvalRunnerPath' -PropertyValue $runnerPath
    Set-ObjectProperty -Value $target -Name 'expectedApprovalRunnerSha256' `
        -PropertyValue $ExpectedRunnerSha256
    Set-ObjectProperty -Value $target -Name 'approvalPolicyPath' -PropertyValue $policyPath
    Set-ObjectProperty -Value $target -Name 'expectedApprovalPolicySha256' `
        -PropertyValue $expectedPolicySha256
    $channelPolicyBytes = [Text.UTF8Encoding]::new($false).GetBytes(
        ($channelPolicy | ConvertTo-Json -Depth 8) + [Environment]::NewLine
    )
    Write-AtomicBytes -Path $channelPolicyPath -Bytes $channelPolicyBytes

    $script:failedStep = 'postflight'
    foreach ($identity in @(
        @{ path = $runnerPath; sha256 = $ExpectedRunnerSha256 },
        @{ path = $policyPath; sha256 = $expectedPolicySha256 },
        @{ path = $installedEvidencePath; sha256 = $ExpectedQualificationEvidenceSha256 }
    )) {
        if ((Get-Sha256 -Path ([string] $identity.path)) -cne [string] $identity.sha256) {
            throw [InvalidOperationException]::new('Installed approval profile identity differs.')
        }
    }
    $postPolicy = Get-Content -LiteralPath $channelPolicyPath -Raw | ConvertFrom-Json
    $postTarget = Get-Target -Policy $postPolicy
    if ([string] $postTarget.expectedApprovalRunnerSha256 -cne $ExpectedRunnerSha256 -or
        [string] $postTarget.expectedApprovalPolicySha256 -cne $expectedPolicySha256) {
        throw [InvalidOperationException]::new('Installed channel approval binding differs.')
    }

    Write-Status -Phase 'install' -Outcome 'installed' -ExitCode $ExitSuccess -Details @{
        activeMutationAttempted = $true
        expectedRunnerSha256 = $ExpectedRunnerSha256
        expectedApprovalPolicySha256 = $expectedPolicySha256
        channelPolicySha256 = Get-Sha256 -Path $channelPolicyPath
        serviceRestartAttempted = $false
    }
    exit $ExitSuccess
} catch {
    if ($script:mutationAttempted) {
        $script:rollbackAttempted = $true
        try {
            Restore-Snapshots
            $script:rollbackSucceeded = $true
        } catch {
            $script:rollbackSucceeded = $false
        }
    }
    try {
        Write-Status -Phase (if ([bool] $PreflightOnly) { 'preflight' } else { 'install' }) `
            -Outcome 'failed' -ExitCode (if ([bool] $PreflightOnly) { $ExitPreflightFailed } else { $ExitInstallFailed }) `
            -Details @{
                failedStep = $script:failedStep
                activeMutationAttempted = [bool] $script:mutationAttempted
                serviceRestartAttempted = $false
                errorType = $_.Exception.GetType().FullName
            }
    } catch { }
    if ([bool] $PreflightOnly) {
        exit $ExitPreflightFailed
    }
    exit $ExitInstallFailed
} finally {
    foreach ($snapshot in @($script:fileSnapshots)) {
        if ($null -ne $snapshot.bytes) {
            [Array]::Clear([byte[]] $snapshot.bytes, 0, ([byte[]] $snapshot.bytes).Length)
        }
    }
}
