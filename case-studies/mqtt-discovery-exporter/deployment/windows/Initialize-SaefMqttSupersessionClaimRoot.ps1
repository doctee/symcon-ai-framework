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
    [switch] $InjectPostAclFailure,

    [Parameter()]
    [switch] $InjectCreationCollision
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
$script:parentAclProtected = $false
$script:parentAclSha256 = ''

$atomicDirectorySource = @'
using System;
using System.ComponentModel;
using System.Runtime.InteropServices;

public static class SaefMqttAtomicDirectory
{
    [StructLayout(LayoutKind.Sequential)]
    private struct SecurityAttributes
    {
        internal int Length;
        internal IntPtr SecurityDescriptor;

        [MarshalAs(UnmanagedType.Bool)]
        internal bool InheritHandle;
    }

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true,
        EntryPoint = "CreateDirectoryW")]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool CreateDirectoryNative(
        string path,
        ref SecurityAttributes securityAttributes
    );

    public static void Create(string path, byte[] securityDescriptor)
    {
        if (String.IsNullOrWhiteSpace(path))
        {
            throw new ArgumentException("Atomic directory path is missing.", "path");
        }
        if (securityDescriptor == null || securityDescriptor.Length == 0)
        {
            throw new ArgumentException(
                "Atomic directory security descriptor is missing.",
                "securityDescriptor"
            );
        }

        GCHandle pinnedDescriptor = GCHandle.Alloc(
            securityDescriptor,
            GCHandleType.Pinned
        );
        try
        {
            SecurityAttributes attributes = new SecurityAttributes();
            attributes.Length = Marshal.SizeOf(typeof(SecurityAttributes));
            attributes.SecurityDescriptor = pinnedDescriptor.AddrOfPinnedObject();
            attributes.InheritHandle = false;

            if (!CreateDirectoryNative(path, ref attributes))
            {
                throw new Win32Exception(Marshal.GetLastWin32Error());
            }
        }
        finally
        {
            pinnedDescriptor.Free();
        }
    }
}
'@

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
        parentAclProtected = [bool] $script:parentAclProtected
        parentAclSha256 = $script:parentAclSha256
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

function Test-ParentControlAccess {
    param([Parameter(Mandatory = $true)][Security.AccessControl.FileSystemRights] $Rights)

    $controlRights = [Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles -bor
        [Security.AccessControl.FileSystemRights]::Delete -bor
        [Security.AccessControl.FileSystemRights]::ChangePermissions -bor
        [Security.AccessControl.FileSystemRights]::TakeOwnership

    return ($Rights -band $controlRights) -ne 0
}

function Assert-SafeClaimRootParentAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    $acl = Get-Acl -LiteralPath $Path
    $script:parentAclProtected = [bool] $acl.AreAccessRulesProtected
    $parentSections = [Security.AccessControl.AccessControlSections]::Access -bor
        [Security.AccessControl.AccessControlSections]::Owner -bor
        [Security.AccessControl.AccessControlSections]::Group
    $parentSddl = $acl.GetSecurityDescriptorSddlForm($parentSections)
    $script:parentAclSha256 = Get-TextSha256 -Value $parentSddl
    $ownerSid = $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value
    if ($ownerSid -notin @('S-1-5-18', 'S-1-5-32-544')) {
        throw [Security.SecurityException]::new('Claim-root parent owner is untrusted.')
    }
    $entries = @($acl.GetAccessRules(
        $true,
        $true,
        [Security.Principal.SecurityIdentifier]
    ))
    $requiredFullControl = @{
        'S-1-5-18' = $false
        'S-1-5-32-544' = $false
    }
    foreach ($entry in $entries) {
        $appliesToParent = ($entry.PropagationFlags -band
            [Security.AccessControl.PropagationFlags]::InheritOnly) -eq 0
        $sid = [string] $entry.IdentityReference.Value
        if ($appliesToParent -and
            $entry.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            $requiredFullControl.ContainsKey($sid) -and
            ($entry.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) -eq
                [Security.AccessControl.FileSystemRights]::FullControl) {
            $requiredFullControl[$sid] = $true
        }
        if ($appliesToParent -and
            $entry.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            $sid -notin @('S-1-5-18', 'S-1-5-32-544') -and
            (Test-ParentControlAccess -Rights $entry.FileSystemRights)) {
            throw [Security.SecurityException]::new(
                'Claim-root parent grants untrusted delete or ACL-control access.'
            )
        }
    }
    foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
        if (-not $requiredFullControl[$sid]) {
            throw [Security.SecurityException]::new(
                'Claim-root parent lacks required trusted full control.'
            )
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

function New-ClaimRootSecurityDescriptor {
    $system = [Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $administrators = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    $inheritance = [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor
        [Security.AccessControl.InheritanceFlags]::ObjectInherit
    $acl = [Security.AccessControl.DirectorySecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetOwner($administrators)
    $acl.SetGroup($administrators)
    foreach ($sid in @($system, $administrators)) {
        $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
            $sid,
            [Security.AccessControl.FileSystemRights]::FullControl,
            $inheritance,
            [Security.AccessControl.PropagationFlags]::None,
            [Security.AccessControl.AccessControlType]::Allow
        ))
    }
    [byte[]] $securityDescriptor = $acl.GetSecurityDescriptorBinaryForm()
    return ,$securityDescriptor
}

function New-AtomicProtectedClaimRoot {
    param([Parameter(Mandatory = $true)][string] $Path)

    [byte[]] $securityDescriptor = New-ClaimRootSecurityDescriptor
    try {
        [SaefMqttAtomicDirectory]::Create($Path, $securityDescriptor)
    } finally {
        [Array]::Clear($securityDescriptor, 0, $securityDescriptor.Length)
    }
}

function Assert-EmptyClaimRoot {
    param([Parameter(Mandatory = $true)][string] $Path)

    if ($null -ne (Get-ChildItem -LiteralPath $Path -Force | Select-Object -First 1)) {
        throw [Security.SecurityException]::new('New claim root is not empty.')
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
    $script:failureCode = 'atomic_directory_contract'
    if ($null -ne ('SaefMqttAtomicDirectory' -as [type])) {
        throw [Security.SecurityException]::new(
            'Atomic directory host type already exists before contract import.'
        )
    }
    Add-Type -TypeDefinition $atomicDirectorySource -Language CSharp -ErrorAction Stop
    if ($null -eq ('SaefMqttAtomicDirectory' -as [type])) {
        throw [InvalidOperationException]::new('Atomic directory host type is unavailable.')
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
    if (($InjectPostAclFailure -or $InjectCreationCollision) -and -not $QualificationMode) {
        throw [Security.SecurityException]::new('Fault injection is qualification-only.')
    }

    $script:failureCode = 'claim_root_parent'
    Assert-PlainAncestorChain -Path $claimParent
    Assert-SafeClaimRootParentAcl -Path $claimParent

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
        $script:failureCode = 'fresh_parent_baseline'
        Assert-PlainAncestorChain -Path $claimParent
        Assert-SafeClaimRootParentAcl -Path $claimParent
        if (Test-Path -LiteralPath $script:claimRoot) {
            throw [IO.IOException]::new('Claim-root path appeared after baseline validation.')
        }
        if ($InjectCreationCollision) {
            [IO.Directory]::CreateDirectory($script:claimRoot) | Out-Null
            [IO.File]::WriteAllText(
                (Join-Path $script:claimRoot 'untrusted-collision.txt'),
                'qualification collision',
                [Text.UTF8Encoding]::new($false)
            )
        }
        $script:failureCode = 'claim_root_creation'
        $script:creationAttempted = $true
        $script:aclMutationAttempted = $true
        New-AtomicProtectedClaimRoot -Path $script:claimRoot
        $script:claimRootCreated = $true
        $script:failureCode = 'claim_root_postflight'
        Assert-PlainDirectory -Path $script:claimRoot
        Assert-EmptyClaimRoot -Path $script:claimRoot
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
            Assert-EmptyClaimRoot -Path $script:claimRoot
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
