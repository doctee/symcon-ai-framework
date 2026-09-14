[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'install')]
    [string] $Operation,

    [Parameter()]
    [string] $ClaimRootPath = (Join-Path `
        (Join-Path $env:ProgramData 'SAEF') `
        'MqttSupersessionOwnerMigrationClaims'),

    [Parameter(Mandatory = $true)]
    [string] $StatusPath,

    [Parameter()]
    [string] $Confirmation = '',

    [Parameter()]
    [switch] $QualificationMode,

    [Parameter()]
    [switch] $InjectPostAclFailure
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitInstallFailed = 20
$ExitRolledBack = 30
$ExitManualRecovery = 40
$ExpectedConfirmation = 'provision-saef-mqtt-supersession-claim-root'
$script:claimRoot = ''
$script:claimRootExistedBefore = $false
$script:claimRootCreated = $false
$script:creationAttempted = $false
$script:aclMutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:mutex = $null
$script:mutexAcquired = $false
$script:failureCode = 'initialization'
$script:failureType = ''
$script:failureId = ''
$script:finalOutcome = 'failed'
$script:finalExitCode = $ExitManualRecovery

function Get-TextSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)

    $bytes = [Text.Encoding]::UTF8.GetBytes($Value)
    try {
        return ([Security.Cryptography.SHA256]::Create().ComputeHash($bytes) |
            ForEach-Object { $_.ToString('x2') }) -join ''
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
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
    $temporary = Join-Path $directory ('.saef-mqtt-claim-root-' + [Guid]::NewGuid().ToString('N') + '.tmp')
    $backup = Join-Path $directory ('.saef-mqtt-claim-root-' + [Guid]::NewGuid().ToString('N') + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            ($Value | ConvertTo-Json -Depth 5) + [Environment]::NewLine,
            [Text.UTF8Encoding]::new($false)
        )
        if (Test-Path -LiteralPath $Path -PathType Leaf) {
            [IO.File]::Replace($temporary, $Path, $backup)
        } else {
            [IO.File]::Move($temporary, $Path)
        }
    } finally {
        foreach ($pathToRemove in @($temporary, $backup)) {
            if (Test-Path -LiteralPath $pathToRemove -PathType Leaf) {
                Remove-Item -LiteralPath $pathToRemove -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

function Write-ClaimRootStatus {
    Write-AtomicJson -Path $StatusPath -Value ([ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'mqtt_supersession_claim_root'
        operation = $Operation
        outcome = $script:finalOutcome
        exitCode = $script:finalExitCode
        failureCode = $script:failureCode
        failureType = $script:failureType
        failureId = $script:failureId
        claimRootSha256 = if ([string]::IsNullOrEmpty($script:claimRoot)) {
            ''
        } else {
            Get-TextSha256 -Value $script:claimRoot.ToLowerInvariant()
        }
        claimRootExistedBefore = [bool] $script:claimRootExistedBefore
        repairRequired = -not [bool] $script:claimRootExistedBefore
        creationAttempted = [bool] $script:creationAttempted
        claimRootCreated = [bool] $script:claimRootCreated
        aclMutationAttempted = [bool] $script:aclMutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        qualificationMode = [bool] $QualificationMode
        productionMutationAttempted = [bool] (
            $script:creationAttempted -and -not $QualificationMode
        )
        liveSymconRpcContactAttempted = $false
        ownerMutationAttempted = $false
        eventMutationAttempted = $false
        mqttPublishAttempted = $false
        deviceActionAttempted = $false
        serviceRestartAttempted = $false
        publicationAttempted = $false
        retentionCleanupAttempted = $false
    })
}

function Assert-PlainDirectory {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Required directory is missing.')
    }
    $item = Get-Item -LiteralPath $Path -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw [IO.IOException]::new('Directory is a reparse point.')
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

function Assert-ProtectedParentAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    $acl = Get-Acl -LiteralPath $Path
    if (-not $acl.AreAccessRulesProtected) {
        throw [Security.SecurityException]::new('Claim-root parent ACL inherits from its parent.')
    }
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate(
            [Security.Principal.SecurityIdentifier]
        ).Value
        if ($sid -notin @('S-1-5-18', 'S-1-5-32-544') -and
            (Test-BroadWriteAccess -Rights $entry.FileSystemRights)) {
            throw [Security.SecurityException]::new('Claim-root parent grants untrusted write access.')
        }
    }
}

function Assert-ProtectedClaimRootAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    $acl = Get-Acl -LiteralPath $Path
    if (-not $acl.AreAccessRulesProtected) {
        throw [Security.SecurityException]::new('Claim-root ACL inherits from its parent.')
    }
    $ownerSid = $acl.Owner
    try {
        $ownerSid = ([Security.Principal.NTAccount] $acl.Owner).Translate(
            [Security.Principal.SecurityIdentifier]
        ).Value
    } catch {
    }
    if ($ownerSid -cne 'S-1-5-32-544') {
        throw [Security.SecurityException]::new('Claim-root owner differs.')
    }
    $required = @{
        'S-1-5-18' = $false
        'S-1-5-32-544' = $false
    }
    $entries = @($acl.Access)
    if ($entries.Count -ne 2) {
        throw [Security.SecurityException]::new('Claim-root ACL entry count differs.')
    }
    foreach ($entry in $entries) {
        $sid = $entry.IdentityReference.Translate(
            [Security.Principal.SecurityIdentifier]
        ).Value
        if (-not $required.ContainsKey($sid) -or
            $entry.IsInherited -or
            $entry.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow -or
            ($entry.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) -ne
                [Security.AccessControl.FileSystemRights]::FullControl -or
            ($entry.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ContainerInherit) -eq 0 -or
            ($entry.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ObjectInherit) -eq 0 -or
            $entry.PropagationFlags -ne [Security.AccessControl.PropagationFlags]::None) {
            throw [Security.SecurityException]::new('Claim-root ACL entry differs.')
        }
        $required[$sid] = $true
    }
    foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
        if (-not $required[$sid]) {
            throw [Security.SecurityException]::new('Claim-root ACL lacks a required principal.')
        }
    }
}

function Set-ProtectedClaimRootAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    & icacls.exe $Path '/inheritance:r' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot disable claim-root ACL inheritance.')
    }
    & icacls.exe $Path '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' |
        Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot apply the protected claim-root ACL.')
    }
    & icacls.exe $Path '/setowner' '*S-1-5-32-544' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot set the protected claim-root owner.')
    }
}

function Assert-Elevated {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [Security.SecurityException]::new('Claim-root installation requires elevation.')
    }
}

try {
    $script:failureCode = 'platform'
    if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT) {
        throw [PlatformNotSupportedException]::new('Claim-root installation requires Windows.')
    }
    $script:failureCode = 'status_path'
    if (-not [IO.Path]::IsPathRooted($StatusPath)) {
        throw [InvalidOperationException]::new('Status path must be absolute.')
    }
    Assert-PlainAncestorChain -Path (Split-Path -Parent $StatusPath)

    $script:failureCode = 'claim_root_path'
    $script:claimRoot = [IO.Path]::GetFullPath($ClaimRootPath).TrimEnd([char[]] @('\', '/'))
    $claimParent = Split-Path -Parent $script:claimRoot
    $claimLeaf = Split-Path -Leaf $script:claimRoot
    $productionRoot = [IO.Path]::GetFullPath((Join-Path `
        (Join-Path $env:ProgramData 'SAEF') `
        'MqttSupersessionOwnerMigrationClaims')).TrimEnd([char[]] @('\', '/'))
    if ($QualificationMode) {
        $qualificationParent = Split-Path -Parent $script:claimRoot
        if ($claimLeaf -cne 'MqttSupersessionOwnerMigrationClaims' -or
            -not (Test-PathContains -Parent $env:TEMP -Candidate $qualificationParent) -or
            (Split-Path -Leaf $qualificationParent) -notlike 'saef-mqtt-supersession-qualification-*') {
            throw [Security.SecurityException]::new('Qualification claim-root path is outside scratch.')
        }
    } elseif (-not $script:claimRoot.Equals($productionRoot, [StringComparison]::OrdinalIgnoreCase)) {
        throw [Security.SecurityException]::new('Production claim-root path differs from the fixed boundary.')
    }
    if ($InjectPostAclFailure -and -not $QualificationMode) {
        throw [Security.SecurityException]::new('Fault injection is qualification-only.')
    }

    $script:failureCode = 'claim_root_parent'
    Assert-PlainAncestorChain -Path $claimParent
    Assert-ProtectedParentAcl -Path $claimParent

    $script:failureCode = 'mutex'
    $script:mutex = [Threading.Mutex]::new($false, 'Global\SAEF.MqttSupersessionClaimRoot')
    $script:mutexAcquired = $script:mutex.WaitOne([TimeSpan]::FromSeconds(30))
    if (-not $script:mutexAcquired) {
        throw [TimeoutException]::new('Claim-root mutex acquisition timed out.')
    }

    $script:failureCode = 'claim_root_baseline'
    if (Test-Path -LiteralPath $script:claimRoot) {
        if (-not (Test-Path -LiteralPath $script:claimRoot -PathType Container)) {
            throw [IO.IOException]::new('Claim-root path is not a directory.')
        }
        Assert-PlainDirectory -Path $script:claimRoot
        $script:claimRootExistedBefore = $true
        Assert-ProtectedClaimRootAcl -Path $script:claimRoot
        $script:finalOutcome = 'passed'
        $script:finalExitCode = $ExitSuccess
    } elseif ($Operation -eq 'preflight') {
        $script:finalOutcome = 'passed'
        $script:finalExitCode = $ExitSuccess
    } else {
        $script:failureCode = 'confirmation'
        if ($Confirmation -cne $ExpectedConfirmation) {
            throw [Security.SecurityException]::new('Explicit claim-root confirmation is missing.')
        }
        Assert-Elevated
        $script:failureCode = 'claim_root_creation'
        $script:creationAttempted = $true
        [IO.Directory]::CreateDirectory($script:claimRoot) | Out-Null
        $script:claimRootCreated = $true
        $script:failureCode = 'claim_root_acl'
        $script:aclMutationAttempted = $true
        Set-ProtectedClaimRootAcl -Path $script:claimRoot
        if ($InjectPostAclFailure) {
            throw [InvalidOperationException]::new('Injected post-ACL qualification failure.')
        }
        Assert-ProtectedClaimRootAcl -Path $script:claimRoot
        $script:finalOutcome = 'installed'
        $script:finalExitCode = $ExitSuccess
    }
} catch {
    $script:failureType = $_.Exception.GetType().FullName
    $script:failureId = [string] $_.FullyQualifiedErrorId
    if ($script:claimRootCreated) {
        $script:rollbackAttempted = $true
        try {
            Remove-Item -LiteralPath $script:claimRoot -Force
            $script:rollbackSucceeded = -not (Test-Path -LiteralPath $script:claimRoot)
        } catch {
            $script:rollbackSucceeded = $false
        }
        if ($script:rollbackSucceeded) {
            $script:finalOutcome = 'rolled_back'
            $script:finalExitCode = $ExitRolledBack
        } else {
            $script:finalOutcome = 'manual_recovery_required'
            $script:finalExitCode = $ExitManualRecovery
        }
    } else {
        $script:finalOutcome = 'failed'
        $script:finalExitCode = if ($Operation -eq 'preflight') {
            $ExitPreflightFailed
        } else {
            $ExitInstallFailed
        }
    }
} finally {
    if ($script:mutexAcquired -and $null -ne $script:mutex) {
        $script:mutex.ReleaseMutex()
        $script:mutexAcquired = $false
    }
    if ($null -ne $script:mutex) {
        $script:mutex.Dispose()
    }
    Write-ClaimRootStatus
}

exit $script:finalExitCode
