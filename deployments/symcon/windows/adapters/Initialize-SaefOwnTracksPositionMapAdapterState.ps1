[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'install')]
    [string] $Operation,

    [Parameter(Mandatory = $true)]
    [string] $AdapterPolicyPath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedAdapterPolicySha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_.-]{1,64}$')]
    [string] $DeploymentUser,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath,

    [Parameter()]
    [string] $Confirmation = ''
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitInstallFailed = 20
$ExitRolledBack = 30
$ExitManualRecovery = 40
$ExpectedConfirmation = 'provision-saef-owntracks-position-map-adapter-state'
$script:policy = $null
$script:stateRoot = ''
$script:activeModulePath = ''
$script:mutex = $null
$script:mutexAcquired = $false
$script:stateRootExistedBefore = $false
$script:stateRootCreated = $false
$script:creationAttempted = $false
$script:aclMutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:failureCode = 'initialization'
$script:finalOutcome = 'failed'
$script:finalExitCode = $ExitManualRecovery

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)

    return ([Security.Cryptography.SHA256]::Create().ComputeHash([IO.File]::ReadAllBytes($Path)) |
        ForEach-Object { $_.ToString('x2') }) -join ''
}

function Write-AtomicJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value
    )

    $directory = Split-Path -Parent $Path
    if ([string]::IsNullOrWhiteSpace($directory) -or
        -not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Status directory is missing or unsafe.')
    }
    $temporary = Join-Path $directory ('.saef-owntracks-state-root-' + [Guid]::NewGuid().ToString('N') + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-state-root-' + [Guid]::NewGuid().ToString('N') + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            ($Value | ConvertTo-Json -Depth 6) + [Environment]::NewLine,
            [Text.UTF8Encoding]::new($false)
        )
        if (Test-Path -LiteralPath $Path -PathType Leaf) {
            [IO.File]::Replace($temporary, $Path, $backup)
        } else {
            [IO.File]::Move($temporary, $Path)
        }
    } finally {
        if (Test-Path -LiteralPath $temporary) {
            Remove-Item -LiteralPath $temporary -Force -ErrorAction SilentlyContinue
        }
        if (Test-Path -LiteralPath $backup) {
            Remove-Item -LiteralPath $backup -Force -ErrorAction SilentlyContinue
        }
    }
}

function Write-StateRootStatus {
    Write-AtomicJson -Path $StatusPath -Value ([ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'owntracks_adapter_state_root'
        operation = $Operation
        outcome = $script:finalOutcome
        exitCode = $script:finalExitCode
        failureCode = $script:failureCode
        stateRootExistedBefore = [bool] $script:stateRootExistedBefore
        installRequired = -not [bool] $script:stateRootExistedBefore
        creationAttempted = [bool] $script:creationAttempted
        stateRootCreated = [bool] $script:stateRootCreated
        aclMutationAttempted = [bool] $script:aclMutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        activeModuleMutationAttempted = $false
        installedChannelMutationAttempted = $false
        targetAllowlistMutationAttempted = $false
        modulePreflightAttempted = $false
        moduleActivationAttempted = $false
        symconRpcContactAttempted = $false
        providerContactAttempted = $false
        publicationAttempted = $false
    })
}

function Assert-RootedLeaf {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )

    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [IO.FileNotFoundException]::new('Required bounded file is missing.')
    }
    $item = Get-Item -LiteralPath $Path
    if ([long] $item.Length -gt $MaximumBytes -or
        (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.IOException]::new('Required bounded file is unsafe.')
    }
}

function Assert-PlainDirectory {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Required directory is missing.')
    }
    $item = Get-Item -LiteralPath $Path
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw [InvalidOperationException]::new('Directory is a reparse point.')
    }
}

function Assert-PlainAncestorChain {
    param([Parameter(Mandatory = $true)][string] $Path)

    $current = [IO.DirectoryInfo]::new([IO.Path]::GetFullPath($Path))
    while ($null -ne $current) {
        Assert-PlainDirectory -Path $current.FullName
        $current = $current.Parent
    }
}

function Test-PathContains {
    param(
        [Parameter(Mandatory = $true)][string] $Parent,
        [Parameter(Mandatory = $true)][string] $Candidate
    )

    $parentFull = [IO.Path]::GetFullPath($Parent).TrimEnd([char[]] @('\', '/'))
    $candidateFull = [IO.Path]::GetFullPath($Candidate).TrimEnd([char[]] @('\', '/'))
    if ($candidateFull.Equals($parentFull, [StringComparison]::OrdinalIgnoreCase)) {
        return $true
    }
    return $candidateFull.StartsWith(
        $parentFull + [IO.Path]::DirectorySeparatorChar,
        [StringComparison]::OrdinalIgnoreCase
    )
}

function Test-BroadWriteAccess {
    param([Parameter(Mandatory = $true)][Security.AccessControl.FileSystemRights] $Rights)

    $mutationRights = [Security.AccessControl.FileSystemRights]::WriteData -bor
        [Security.AccessControl.FileSystemRights]::AppendData -bor
        [Security.AccessControl.FileSystemRights]::WriteExtendedAttributes -bor
        [Security.AccessControl.FileSystemRights]::WriteAttributes -bor
        [Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles -bor
        [Security.AccessControl.FileSystemRights]::Delete -bor
        [Security.AccessControl.FileSystemRights]::ChangePermissions -bor
        [Security.AccessControl.FileSystemRights]::TakeOwnership

    return ($Rights -band $mutationRights) -ne 0
}

function Assert-ProtectedAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $DeploymentSid
    )

    $acl = Get-Acl -LiteralPath $Path
    if (-not $acl.AreAccessRulesProtected) {
        throw [Security.SecurityException]::new('Adapter state-root ACL inherits from its parent.')
    }
    $requiredSids = @('S-1-5-18', 'S-1-5-32-544', $DeploymentSid)
    $fullControlSids = @{}
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entry.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow) {
            throw [Security.SecurityException]::new('Adapter state-root ACL contains an unexpected rule.')
        }
        if ($sid -notin $requiredSids) {
            if (Test-BroadWriteAccess -Rights $entry.FileSystemRights) {
                throw [Security.SecurityException]::new('Adapter state-root grants broad write access.')
            }
            throw [Security.SecurityException]::new('Adapter state-root ACL contains an unexpected principal.')
        }
        if (($entry.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) -eq
            [Security.AccessControl.FileSystemRights]::FullControl -and
            ($entry.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ContainerInherit) -ne 0 -and
            ($entry.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ObjectInherit) -ne 0) {
            $fullControlSids[$sid] = $true
        }
    }
    foreach ($sid in $requiredSids) {
        if (-not $fullControlSids.ContainsKey($sid)) {
            throw [Security.SecurityException]::new('Adapter state-root ACL lacks a required principal rule.')
        }
    }
}

function Set-RestrictedAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $DeploymentSid
    )

    & icacls.exe $Path '/inheritance:r' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot disable adapter state-root ACL inheritance.')
    }
    & icacls.exe $Path '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' `
        ('*' + $DeploymentSid + ':(OI)(CI)F') | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot apply restricted adapter state-root ACL.')
    }
}

function Assert-Elevated {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [Security.SecurityException]::new('Installation requires an elevated administrator process.')
    }
}

try {
    $script:failureCode = 'contract'
    Assert-RootedLeaf -Path $AdapterPolicyPath -MaximumBytes 1048576
    if ((Get-Sha256 -Path $AdapterPolicyPath) -cne $ExpectedAdapterPolicySha256) {
        throw [Security.SecurityException]::new('Adapter policy hash differs.')
    }
    if (-not [IO.Path]::IsPathRooted($StatusPath)) {
        throw [InvalidOperationException]::new('Status path must be absolute.')
    }
    Assert-PlainAncestorChain -Path (Split-Path -Parent $StatusPath)
    if ($Operation -eq 'install' -and $Confirmation -cne $ExpectedConfirmation) {
        throw [Security.SecurityException]::new('Explicit state-root provisioning confirmation is missing.')
    }

    $script:failureCode = 'policy'
    $policyBytes = [IO.File]::ReadAllBytes($AdapterPolicyPath)
    try {
        $policyText = [Text.UTF8Encoding]::new($false, $true).GetString($policyBytes)
        $script:policy = $policyText | ConvertFrom-Json
    } finally {
        [Array]::Clear($policyBytes, 0, $policyBytes.Length)
    }
    if ($script:policy.formatVersion -ne 1 -or
        [string] $script:policy.adapterProfile -cne 'saef-owntracks-position-map-v1' -or
        [string] $script:policy.targetId -cne 'saef-owntracks-position-map' -or
        [string]::IsNullOrWhiteSpace([string] $script:policy.activeModulePath) -or
        [string]::IsNullOrWhiteSpace([string] $script:policy.adapterStateRoot) -or
        [string]::IsNullOrWhiteSpace([string] $script:policy.mutexName) -or
        [int] $script:policy.quiescenceTimeoutSeconds -lt 1 -or
        [int] $script:policy.quiescenceTimeoutSeconds -gt 300) {
        throw [InvalidOperationException]::new('OwnTracks adapter policy contract is invalid.')
    }
    $script:activeModulePath = [IO.Path]::GetFullPath([string] $script:policy.activeModulePath)
    $script:stateRoot = [IO.Path]::GetFullPath([string] $script:policy.adapterStateRoot)
    $stateVolumeRoot = [IO.Path]::GetPathRoot($script:stateRoot)
    if (-not [IO.Path]::IsPathRooted($script:activeModulePath) -or
        -not [IO.Path]::IsPathRooted($script:stateRoot) -or
        $script:stateRoot.TrimEnd([char[]] @('\', '/')).Equals(
            $stateVolumeRoot.TrimEnd([char[]] @('\', '/')),
            [StringComparison]::OrdinalIgnoreCase
        ) -or
        -not [IO.Path]::GetPathRoot($script:activeModulePath).Equals(
            $stateVolumeRoot,
            [StringComparison]::OrdinalIgnoreCase
        ) -or
        (Test-PathContains -Parent $script:activeModulePath -Candidate $script:stateRoot) -or
        (Test-PathContains -Parent $script:stateRoot -Candidate $script:activeModulePath)) {
        throw [InvalidOperationException]::new('Adapter state-root path boundary is invalid.')
    }
    Assert-PlainAncestorChain -Path $script:activeModulePath
    $stateParent = Split-Path -Parent $script:stateRoot
    Assert-PlainAncestorChain -Path $stateParent

    $script:failureCode = 'deployment_account'
    $deploymentAccount = Get-LocalUser -Name $DeploymentUser -ErrorAction Stop
    if (-not $deploymentAccount.Enabled) {
        throw [Security.SecurityException]::new('Deployment account is disabled.')
    }
    $administratorGroup = Get-LocalGroup -SID 'S-1-5-32-544' -ErrorAction Stop
    $administratorMembers = @(Get-LocalGroupMember -Group $administratorGroup.Name -ErrorAction Stop)
    if ($deploymentAccount.SID -notin $administratorMembers.SID) {
        throw [Security.SecurityException]::new('Deployment account must be a local administrator.')
    }
    $deploymentSid = [string] $deploymentAccount.SID.Value
    Assert-ProtectedAcl -Path $stateParent -DeploymentSid $deploymentSid

    if ($Operation -eq 'install') {
        $script:failureCode = 'elevation'
        Assert-Elevated
    }

    $script:failureCode = 'mutex'
    $script:mutex = [Threading.Mutex]::new($false, [string] $script:policy.mutexName)
    try {
        $script:mutexAcquired = $script:mutex.WaitOne([int] $script:policy.quiescenceTimeoutSeconds * 1000)
    } catch [Threading.AbandonedMutexException] {
        $script:mutexAcquired = $true
    }
    if (-not $script:mutexAcquired) {
        throw [TimeoutException]::new('OwnTracks adapter mutex remained busy.')
    }

    $script:failureCode = 'state_root'
    $script:stateRootExistedBefore = Test-Path -LiteralPath $script:stateRoot -PathType Container
    if (Test-Path -LiteralPath $script:stateRoot -PathType Leaf) {
        throw [IO.IOException]::new('Adapter state-root path is occupied by a file.')
    }
    if ($script:stateRootExistedBefore) {
        Assert-PlainDirectory -Path $script:stateRoot
        Assert-ProtectedAcl -Path $script:stateRoot -DeploymentSid $deploymentSid
        $script:failureCode = 'none'
        $script:finalOutcome = 'already_present'
        $script:finalExitCode = $ExitSuccess
    } elseif ($Operation -eq 'preflight') {
        $script:failureCode = 'none'
        $script:finalOutcome = 'ready'
        $script:finalExitCode = $ExitSuccess
    } else {
        $script:failureCode = 'state_root_create'
        $script:creationAttempted = $true
        [IO.Directory]::CreateDirectory($script:stateRoot) | Out-Null
        $script:stateRootCreated = $true

        $script:failureCode = 'state_root_acl'
        $script:aclMutationAttempted = $true
        Set-RestrictedAcl -Path $script:stateRoot -DeploymentSid $deploymentSid
        Assert-PlainDirectory -Path $script:stateRoot
        Assert-ProtectedAcl -Path $script:stateRoot -DeploymentSid $deploymentSid

        $script:failureCode = 'none'
        $script:finalOutcome = 'installed'
        $script:finalExitCode = $ExitSuccess
    }
    Write-StateRootStatus
} catch {
    if ($script:failureCode -eq 'none') {
        $script:failureCode = 'status'
    }
    if ($script:stateRootCreated) {
        $script:rollbackAttempted = $true
        try {
            Assert-PlainDirectory -Path $script:stateRoot
            if (@(Get-ChildItem -LiteralPath $script:stateRoot -Force).Count -ne 0) {
                throw [IO.IOException]::new('Created adapter state-root is not empty.')
            }
            Remove-Item -LiteralPath $script:stateRoot -Force
            $script:rollbackSucceeded = -not (Test-Path -LiteralPath $script:stateRoot)
        } catch {
            $script:rollbackSucceeded = $false
        }
    }
    if ($script:rollbackAttempted -and $script:rollbackSucceeded) {
        $script:finalOutcome = 'rolled_back'
        $script:finalExitCode = $ExitRolledBack
    } elseif ($script:rollbackAttempted) {
        $script:finalOutcome = 'manual_recovery_required'
        $script:finalExitCode = $ExitManualRecovery
    } elseif ($Operation -eq 'preflight') {
        $script:finalOutcome = 'failed'
        $script:finalExitCode = $ExitPreflightFailed
    } else {
        $script:finalOutcome = 'failed'
        $script:finalExitCode = $ExitInstallFailed
    }
    Write-StateRootStatus
} finally {
    if ($script:mutexAcquired -and $null -ne $script:mutex) {
        try { $script:mutex.ReleaseMutex() } catch { }
    }
    if ($null -ne $script:mutex) {
        $script:mutex.Dispose()
    }
}

exit $script:finalExitCode
