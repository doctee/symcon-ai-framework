[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'apply')]
    [string] $Operation,

    [Parameter(Mandatory = $true)]
    [string] $ChannelPolicyPath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedChannelPolicySha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedPreviousPackageIdentitySha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedActivePackageIdentitySha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^saef-[a-z0-9][a-z0-9.-]{0,63}$')]
    [string] $ExpectedActiveDeploymentId,

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
$ExitApplyFailed = 20
$ExitRolledBack = 30
$ExitManualRecovery = 40
$ExpectedConfirmation = 'reseal-saef-owntracks-position-map-active-identity'
$MaximumPolicyBytes = 1048576
$MaximumPackageBytes = 67108864
$MaximumPackageFiles = 256
$TargetId = 'saef-owntracks-position-map'
$AdapterProfile = 'saef-owntracks-position-map-v1'

$script:channelMutex = $null
$script:adapterMutex = $null
$script:channelMutexAcquired = $false
$script:adapterMutexAcquired = $false
$script:mutationAttempted = $false
$script:adapterPolicyMutationAttempted = $false
$script:channelPolicyMutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:failureCode = 'initialization'
$script:failureDetail = ''
$script:failureType = ''
$script:failureHResult = $null
$script:finalOutcome = 'failed'
$script:finalExitCode = $ExitManualRecovery
$script:adapterPolicyPath = ''
$script:channelBytes = $null
$script:adapterBytes = $null
$script:activeStateSha256 = ''
$script:activePackageFileCount = 0
$script:rollbackPackageFileCount = 0
$script:proposedAdapterPolicySha256 = ''
$script:proposedChannelPolicySha256 = ''

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)

    return ([Security.Cryptography.SHA256]::Create().ComputeHash([IO.File]::ReadAllBytes($Path)) |
        ForEach-Object { $_.ToString('x2') }) -join ''
}

function Get-BytesSha256 {
    param([Parameter(Mandatory = $true)][byte[]] $Bytes)

    return ([Security.Cryptography.SHA256]::Create().ComputeHash($Bytes) |
        ForEach-Object { $_.ToString('x2') }) -join ''
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
    $item = Get-Item -LiteralPath $Path -Force
    if ([long] $item.Length -lt 1 -or
        [long] $item.Length -gt $MaximumBytes -or
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
    $item = Get-Item -LiteralPath $Path -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw [IO.IOException]::new('Directory is a reparse point.')
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

function Test-SamePath {
    param(
        [Parameter(Mandatory = $true)][string] $Left,
        [Parameter(Mandatory = $true)][string] $Right
    )

    return [IO.Path]::GetFullPath($Left).TrimEnd([char[]] @('\', '/')).Equals(
        [IO.Path]::GetFullPath($Right).TrimEnd([char[]] @('\', '/')),
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

function Assert-NoBroadWriteAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    $acl = Get-Acl -LiteralPath $Path
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entry.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            (Test-BroadWriteAccess -Rights $entry.FileSystemRights) -and
            $sid -in @('S-1-1-0', 'S-1-5-11', 'S-1-5-32-545')) {
            throw [UnauthorizedAccessException]::new('Active module grants broad write access.')
        }
    }
}

function Assert-ProtectedAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $DeploymentSid
    )

    $acl = Get-Acl -LiteralPath $Path
    if (-not $acl.AreAccessRulesProtected) {
        throw [Security.SecurityException]::new('Managed path ACL inherits from its parent.')
    }
    $requiredSids = @('S-1-5-18', 'S-1-5-32-544', $DeploymentSid)
    $fullControlSids = @{}
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entry.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow) {
            throw [Security.SecurityException]::new('Managed path ACL contains an unexpected rule.')
        }
        if ($sid -notin $requiredSids) {
            if (Test-BroadWriteAccess -Rights $entry.FileSystemRights) {
                throw [Security.SecurityException]::new('Managed path grants broad write access.')
            }
            throw [Security.SecurityException]::new('Managed path ACL contains an unexpected principal.')
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
            throw [Security.SecurityException]::new('Managed path ACL lacks a required principal rule.')
        }
    }
}

function Assert-SafeDirectoryTree {
    param([Parameter(Mandatory = $true)][string] $Path)

    Assert-PlainDirectory -Path $Path
    foreach ($entry in @(Get-ChildItem -LiteralPath $Path -Recurse -Force)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [IO.IOException]::new('Managed package tree contains a reparse point.')
        }
    }
}

function Get-DirectoryPackageIdentity {
    param([Parameter(Mandatory = $true)][string] $Path)

    Assert-SafeDirectoryTree -Path $Path
    $root = [IO.Path]::GetFullPath($Path).TrimEnd([char[]] @('\', '/')) +
        [IO.Path]::DirectorySeparatorChar
    $filesByRelativePath = [Collections.Generic.Dictionary[string,IO.FileInfo]]::new(
        [StringComparer]::Ordinal
    )
    foreach ($file in @(Get-ChildItem -LiteralPath $Path -File -Recurse -Force)) {
        $relative = $file.FullName.Substring($root.Length).Replace('\', '/')
        $filesByRelativePath.Add($relative, $file)
    }
    [string[]] $relativePaths = @($filesByRelativePath.Keys)
    [Array]::Sort($relativePaths, [StringComparer]::Ordinal)
    if ($relativePaths.Count -lt 1 -or $relativePaths.Count -gt $MaximumPackageFiles) {
        throw [InvalidOperationException]::new('Managed package file count is outside its bound.')
    }
    $identity = [Text.StringBuilder]::new()
    $totalBytes = 0L
    foreach ($relative in $relativePaths) {
        $file = $filesByRelativePath[$relative]
        $totalBytes += [long] $file.Length
        if ($totalBytes -gt $MaximumPackageBytes) {
            throw [InvalidOperationException]::new('Managed package exceeds its byte bound.')
        }
        $null = $identity.Append($relative).Append([char] 0).Append([long] $file.Length).Append(
            [char] 0
        ).Append((Get-Sha256 -Path $file.FullName)).Append([char] 10)
    }
    $identityBytes = [Text.UTF8Encoding]::new($false).GetBytes($identity.ToString())
    try {
        return [ordered]@{
            sha256 = Get-BytesSha256 -Bytes $identityBytes
            fileCount = $relativePaths.Count
            bytes = $totalBytes
        }
    } finally {
        [Array]::Clear($identityBytes, 0, $identityBytes.Length)
    }
}

function Read-BoundedJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter()][long] $MaximumBytes = $MaximumPolicyBytes
    )

    Assert-RootedLeaf -Path $Path -MaximumBytes $MaximumBytes
    $bytes = [IO.File]::ReadAllBytes($Path)
    try {
        $text = [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
        return $text | ConvertFrom-Json
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function ConvertTo-Utf8JsonBytes {
    param([Parameter(Mandatory = $true)] $Value)

    $text = ($Value | ConvertTo-Json -Depth 12) + [Environment]::NewLine
    return [Text.UTF8Encoding]::new($false).GetBytes($text)
}

function Write-AtomicBytes {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][byte[]] $Bytes
    )

    $directory = Split-Path -Parent $Path
    Assert-PlainDirectory -Path $directory
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-owntracks-identity-reseal-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-identity-reseal-' + $token + '.bak')
    try {
        [IO.File]::WriteAllBytes($temporary, $Bytes)
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

function Write-ResealStatus {
    $details = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'owntracks_active_identity_reseal'
        operation = $Operation
        outcome = $script:finalOutcome
        exitCode = $script:finalExitCode
        failureCode = $script:failureCode
        failureDetail = $script:failureDetail
        failureType = $script:failureType
        failureHResult = $script:failureHResult
        activeDeploymentId = $ExpectedActiveDeploymentId
        previousPackageIdentitySha256 = $ExpectedPreviousPackageIdentitySha256
        activePackageIdentitySha256 = $ExpectedActivePackageIdentitySha256
        activeStateSha256 = $script:activeStateSha256
        activePackageFileCount = $script:activePackageFileCount
        rollbackPackageFileCount = $script:rollbackPackageFileCount
        proposedAdapterPolicySha256 = $script:proposedAdapterPolicySha256
        proposedChannelPolicySha256 = $script:proposedChannelPolicySha256
        mutationAttempted = [bool] $script:mutationAttempted
        adapterPolicyMutationAttempted = [bool] $script:adapterPolicyMutationAttempted
        channelPolicyMutationAttempted = [bool] $script:channelPolicyMutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        moduleReloadAttempted = $false
        moduleActivationAttempted = $false
        symconRpcContactAttempted = $false
        sshdRestartAttempted = $false
        providerContactAttempted = $false
        publicationAttempted = $false
        cleanupAttempted = $false
    }
    $statusBytes = ConvertTo-Utf8JsonBytes -Value $details
    try {
        Write-AtomicBytes -Path $StatusPath -Bytes $statusBytes
    } finally {
        [Array]::Clear($statusBytes, 0, $statusBytes.Length)
    }
}

try {
    $script:failureCode = 'input'
    Assert-RootedLeaf -Path $ChannelPolicyPath -MaximumBytes $MaximumPolicyBytes
    if (-not [IO.Path]::IsPathRooted($StatusPath)) {
        throw [InvalidOperationException]::new('Status path must be absolute.')
    }
    Assert-PlainDirectory -Path (Split-Path -Parent $StatusPath)
    if (Test-SamePath -Left $StatusPath -Right $ChannelPolicyPath) {
        throw [InvalidOperationException]::new('Status path overlaps the installed channel policy.')
    }
    if ($Operation -eq 'apply' -and $Confirmation -cne $ExpectedConfirmation) {
        throw [Security.SecurityException]::new('Explicit active-identity reseal confirmation is missing.')
    }
    if ($ExpectedPreviousPackageIdentitySha256 -ceq $ExpectedActivePackageIdentitySha256) {
        throw [InvalidOperationException]::new('Reseal requires a changed active package identity.')
    }
    if ((Get-Sha256 -Path $ChannelPolicyPath) -cne $ExpectedChannelPolicySha256) {
        throw [Security.SecurityException]::new('Installed channel policy hash differs.')
    }

    $script:channelBytes = [IO.File]::ReadAllBytes($ChannelPolicyPath)
    $channelText = [Text.UTF8Encoding]::new($false, $true).GetString($script:channelBytes)
    $channelPolicy = $channelText | ConvertFrom-Json
    if ($channelPolicy.formatVersion -ne 1 -or
        -not [IO.Path]::IsPathRooted([string] $channelPolicy.stateRoot) -or
        -not [IO.Path]::IsPathRooted([string] $channelPolicy.managedFilesetRoot) -or
        -not [IO.Path]::IsPathRooted([string] $channelPolicy.adapterStateRoot)) {
        throw [InvalidOperationException]::new('Installed channel policy contract is invalid.')
    }
    Assert-PlainDirectory -Path ([string] $channelPolicy.stateRoot)
    Assert-PlainDirectory -Path ([string] $channelPolicy.managedFilesetRoot)
    Assert-PlainDirectory -Path ([string] $channelPolicy.adapterStateRoot)
    foreach ($managedRoot in @(
        (Split-Path -Parent $ChannelPolicyPath),
        ([string] $channelPolicy.stateRoot),
        ([string] $channelPolicy.managedFilesetRoot),
        ([string] $channelPolicy.adapterStateRoot)
    )) {
        if (Test-PathContains -Parent $managedRoot -Candidate $StatusPath) {
            throw [InvalidOperationException]::new('Status path is inside a managed channel or module root.')
        }
    }
    $targets = @($channelPolicy.standaloneModuleTargets | Where-Object {
        [string] $_.targetId -ceq $TargetId -and [string] $_.adapterProfile -ceq $AdapterProfile
    })
    if ($targets.Count -ne 1) {
        throw [InvalidOperationException]::new('OwnTracks target is not uniquely installed.')
    }
    $target = $targets[0]
    $script:adapterPolicyPath = [IO.Path]::GetFullPath([string] $target.adapterPolicyPath)
    Assert-RootedLeaf -Path $script:adapterPolicyPath -MaximumBytes $MaximumPolicyBytes
    if (Test-SamePath -Left $StatusPath -Right $script:adapterPolicyPath) {
        throw [InvalidOperationException]::new('Status path overlaps the installed adapter policy.')
    }
    if ((Get-Sha256 -Path $script:adapterPolicyPath) -cne [string] $target.expectedAdapterPolicySha256) {
        throw [Security.SecurityException]::new('Installed adapter policy hash differs from its target binding.')
    }
    $script:adapterBytes = [IO.File]::ReadAllBytes($script:adapterPolicyPath)
    $adapterText = [Text.UTF8Encoding]::new($false, $true).GetString($script:adapterBytes)
    $adapterPolicy = $adapterText | ConvertFrom-Json
    if ($adapterPolicy.formatVersion -ne 1 -or
        [string] $adapterPolicy.targetId -cne $TargetId -or
        [string] $adapterPolicy.adapterProfile -cne $AdapterProfile -or
        [string] $adapterPolicy.expectedActivePackageIdentitySha256 -cne
            $ExpectedPreviousPackageIdentitySha256 -or
        -not [IO.Path]::IsPathRooted([string] $adapterPolicy.activeModulePath) -or
        -not [IO.Path]::IsPathRooted([string] $adapterPolicy.adapterStateRoot) -or
        [string]::IsNullOrWhiteSpace([string] $adapterPolicy.mutexName)) {
        throw [InvalidOperationException]::new('Installed OwnTracks adapter policy contract is invalid.')
    }
    if (-not (Test-PathContains -Parent ([string] $channelPolicy.adapterStateRoot) -Candidate ([string] $adapterPolicy.adapterStateRoot))) {
        throw [InvalidOperationException]::new('OwnTracks adapter state is outside the protected channel root.')
    }
    if (Test-SamePath -Left ([string] $channelPolicy.adapterStateRoot) -Right ([string] $adapterPolicy.adapterStateRoot)) {
        throw [InvalidOperationException]::new('OwnTracks adapter state must use a target-owned child root.')
    }
    foreach ($managedRoot in @(
        (Split-Path -Parent $script:adapterPolicyPath),
        ([string] $adapterPolicy.adapterStateRoot),
        ([string] $adapterPolicy.activeModulePath)
    )) {
        if (Test-PathContains -Parent $managedRoot -Candidate $StatusPath) {
            throw [InvalidOperationException]::new('Status path is inside a managed channel or module root.')
        }
    }

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
    Assert-ProtectedAcl -Path (Split-Path -Parent $ChannelPolicyPath) -DeploymentSid $deploymentSid
    Assert-ProtectedAcl -Path (Split-Path -Parent $script:adapterPolicyPath) -DeploymentSid $deploymentSid
    Assert-ProtectedAcl -Path ([string] $adapterPolicy.adapterStateRoot) -DeploymentSid $deploymentSid
    Assert-NoBroadWriteAcl -Path ([string] $adapterPolicy.activeModulePath)

    $script:channelMutex = [Threading.Mutex]::new($false, 'Global\SAEF.DeploymentChannel')
    try {
        $script:channelMutexAcquired = $script:channelMutex.WaitOne(0)
    } catch [Threading.AbandonedMutexException] {
        $script:channelMutexAcquired = $true
    }
    if (-not $script:channelMutexAcquired) {
        throw [TimeoutException]::new('Deployment channel operation is active.')
    }
    $script:adapterMutex = [Threading.Mutex]::new($false, [string] $adapterPolicy.mutexName)
    try {
        $script:adapterMutexAcquired = $script:adapterMutex.WaitOne(0)
    } catch [Threading.AbandonedMutexException] {
        $script:adapterMutexAcquired = $true
    }
    if (-not $script:adapterMutexAcquired) {
        throw [TimeoutException]::new('OwnTracks adapter operation is active.')
    }
    if ((Get-Sha256 -Path $ChannelPolicyPath) -cne $ExpectedChannelPolicySha256 -or
        (Get-Sha256 -Path $script:adapterPolicyPath) -cne [string] $target.expectedAdapterPolicySha256) {
        throw [Security.SecurityException]::new('Installed policy changed before reseal inspection.')
    }

    $script:failureCode = 'active_transaction'
    $activeStatePath = Join-Path ([string] $adapterPolicy.adapterStateRoot) 'active.json'
    $activeState = Read-BoundedJson -Path $activeStatePath
    $script:activeStateSha256 = Get-Sha256 -Path $activeStatePath
    $transactionName = [string] $activeState.transactionDirectoryName
    if ($activeState.formatVersion -ne 1 -or
        [string] $activeState.adapterProfile -cne $AdapterProfile -or
        [string] $activeState.deploymentId -cne $ExpectedActiveDeploymentId -or
        [string] $activeState.packageIdentitySha256 -cne $ExpectedActivePackageIdentitySha256 -or
        $transactionName -notmatch '^saef-[a-z0-9.-]+-[0-9]{8}T[0-9]{6}Z$' -or
        [string] $activeState.rollbackDirectoryName -cne 'rollback' -or
        [string] $activeState.snapshotFileName -cne 'snapshot.json') {
        throw [InvalidOperationException]::new('Active adapter record differs.')
    }
    $transactionRoot = Join-Path ([string] $adapterPolicy.adapterStateRoot) $transactionName
    Assert-PlainDirectory -Path $transactionRoot
    $transaction = Read-BoundedJson -Path (Join-Path $transactionRoot 'transaction.json')
    $snapshot = Read-BoundedJson -Path (Join-Path $transactionRoot 'snapshot.json')
    if ([string] $transaction.transactionDirectoryName -cne $transactionName -or
        [string] $transaction.deploymentId -cne $ExpectedActiveDeploymentId -or
        [string] $transaction.packageIdentitySha256 -cne $ExpectedActivePackageIdentitySha256 -or
        [string] $transaction.outcome -cne 'activated' -or
        [string] $snapshot.deploymentId -cne $ExpectedActiveDeploymentId -or
        [string] $snapshot.packageIdentitySha256 -cne $ExpectedActivePackageIdentitySha256 -or
        [string] $snapshot.activePackageIdentitySha256 -cne $ExpectedPreviousPackageIdentitySha256 -or
        (Test-Path -LiteralPath (Join-Path $transactionRoot 'candidate')) -or
        (Test-Path -LiteralPath (Join-Path $transactionRoot 'failed-candidate'))) {
        throw [InvalidOperationException]::new('Completed activation transaction differs.')
    }

    $deploymentRoot = Join-Path ([string] $channelPolicy.stateRoot) $ExpectedActiveDeploymentId
    $deploymentStatus = Read-BoundedJson -Path (Join-Path $deploymentRoot 'status.json')
    $adapterStatus = Read-BoundedJson -Path (Join-Path $deploymentRoot 'module-adapter-status.json')
    $manifest = Read-BoundedJson -Path (Join-Path $deploymentRoot 'deployment.json')
    if ([string] $deploymentStatus.phase -cne 'activation' -or
        [string] $deploymentStatus.outcome -cne 'activated' -or
        [int] $deploymentStatus.exitCode -ne 0 -or
        [string] $adapterStatus.operation -cne 'activate' -or
        [string] $adapterStatus.outcome -cne 'activated' -or
        [int] $adapterStatus.exitCode -ne 0 -or
        [bool] $adapterStatus.rollbackAttempted -or
        [string] $manifest.deploymentId -cne $ExpectedActiveDeploymentId -or
        [string] $manifest.targetDirectoryName -cne ($ExpectedActiveDeploymentId + '-module') -or
        [string] $manifest.module.packageIdentitySha256 -cne $ExpectedActivePackageIdentitySha256) {
        throw [InvalidOperationException]::new('Channel activation evidence differs.')
    }

    $activeIdentity = Get-DirectoryPackageIdentity -Path ([string] $adapterPolicy.activeModulePath)
    $rollbackIdentity = Get-DirectoryPackageIdentity -Path (Join-Path $transactionRoot 'rollback')
    $stagedRoot = Join-Path ([string] $channelPolicy.managedFilesetRoot) ([string] $manifest.targetDirectoryName)
    $stagedIdentity = Get-DirectoryPackageIdentity -Path $stagedRoot
    $script:activePackageFileCount = [int] $activeIdentity.fileCount
    $script:rollbackPackageFileCount = [int] $rollbackIdentity.fileCount
    if ([string] $activeIdentity.sha256 -cne $ExpectedActivePackageIdentitySha256 -or
        [string] $stagedIdentity.sha256 -cne $ExpectedActivePackageIdentitySha256 -or
        [string] $rollbackIdentity.sha256 -cne $ExpectedPreviousPackageIdentitySha256) {
        throw [InvalidOperationException]::new('Active, staged or rollback package identity differs.')
    }

    $adapterPolicy.expectedActivePackageIdentitySha256 = $ExpectedActivePackageIdentitySha256
    $candidateAdapterBytes = ConvertTo-Utf8JsonBytes -Value $adapterPolicy
    $script:proposedAdapterPolicySha256 = Get-BytesSha256 -Bytes $candidateAdapterBytes
    $target.expectedAdapterPolicySha256 = $script:proposedAdapterPolicySha256
    $candidateChannelBytes = ConvertTo-Utf8JsonBytes -Value $channelPolicy
    $script:proposedChannelPolicySha256 = Get-BytesSha256 -Bytes $candidateChannelBytes

    if ($Operation -eq 'preflight') {
        $script:failureCode = 'none'
        $script:finalOutcome = 'ready'
        $script:finalExitCode = $ExitSuccess
    } else {
        $principal = [Security.Principal.WindowsPrincipal]::new(
            [Security.Principal.WindowsIdentity]::GetCurrent()
        )
        if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
            throw [Security.SecurityException]::new('Reseal apply requires an elevated local administrator.')
        }
        $script:mutationAttempted = $true
        $script:failureCode = 'adapter_policy_replace'
        $script:adapterPolicyMutationAttempted = $true
        Write-AtomicBytes -Path $script:adapterPolicyPath -Bytes $candidateAdapterBytes
        if ((Get-Sha256 -Path $script:adapterPolicyPath) -cne $script:proposedAdapterPolicySha256) {
            throw [IO.IOException]::new('Updated adapter policy hash differs.')
        }

        $script:failureCode = 'channel_policy_replace'
        $script:channelPolicyMutationAttempted = $true
        Write-AtomicBytes -Path $ChannelPolicyPath -Bytes $candidateChannelBytes
        if ((Get-Sha256 -Path $ChannelPolicyPath) -cne $script:proposedChannelPolicySha256) {
            throw [IO.IOException]::new('Updated channel policy hash differs.')
        }

        $script:failureCode = 'postflight'
        $postChannel = Read-BoundedJson -Path $ChannelPolicyPath
        $postTargets = @($postChannel.standaloneModuleTargets | Where-Object {
            [string] $_.targetId -ceq $TargetId -and [string] $_.adapterProfile -ceq $AdapterProfile
        })
        $postAdapter = Read-BoundedJson -Path $script:adapterPolicyPath
        $postActiveIdentity = Get-DirectoryPackageIdentity -Path ([string] $adapterPolicy.activeModulePath)
        if ($postTargets.Count -ne 1 -or
            [string] $postTargets[0].expectedAdapterPolicySha256 -cne
                $script:proposedAdapterPolicySha256 -or
            [string] $postAdapter.expectedActivePackageIdentitySha256 -cne
                $ExpectedActivePackageIdentitySha256 -or
            (Get-Sha256 -Path $activeStatePath) -cne $script:activeStateSha256 -or
            [string] $postActiveIdentity.sha256 -cne $ExpectedActivePackageIdentitySha256) {
            throw [InvalidOperationException]::new('Active-identity reseal postflight differs.')
        }
        $script:failureCode = 'none'
        $script:finalOutcome = 'resealed'
        $script:finalExitCode = $ExitSuccess
    }
} catch {
    $failureException = $_.Exception
    $script:failureDetail = $failureException.Message
    $script:failureType = $failureException.GetType().FullName
    $script:failureHResult = $failureException.HResult
    if ($script:mutationAttempted) {
        $script:rollbackAttempted = $true
        try {
            if ($null -ne $script:channelBytes -and $script:channelPolicyMutationAttempted) {
                Write-AtomicBytes -Path $ChannelPolicyPath -Bytes $script:channelBytes
            }
            if ($null -ne $script:adapterBytes -and $script:adapterPolicyMutationAttempted) {
                Write-AtomicBytes -Path $script:adapterPolicyPath -Bytes $script:adapterBytes
            }
            $script:rollbackSucceeded =
                (Get-Sha256 -Path $ChannelPolicyPath) -ceq $ExpectedChannelPolicySha256 -and
                (Get-Sha256 -Path $script:adapterPolicyPath) -ceq
                    (Get-BytesSha256 -Bytes $script:adapterBytes)
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
        $script:finalExitCode = $ExitApplyFailed
    }
} finally {
    try {
        Write-ResealStatus
    } catch {
        if ($script:finalExitCode -eq $ExitSuccess) {
            $script:finalExitCode = $ExitManualRecovery
        }
    }
    if ($script:adapterMutexAcquired -and $null -ne $script:adapterMutex) {
        try { $script:adapterMutex.ReleaseMutex() } catch { }
    }
    if ($null -ne $script:adapterMutex) {
        $script:adapterMutex.Dispose()
    }
    if ($script:channelMutexAcquired -and $null -ne $script:channelMutex) {
        try { $script:channelMutex.ReleaseMutex() } catch { }
    }
    if ($null -ne $script:channelMutex) {
        $script:channelMutex.Dispose()
    }
    if ($null -ne $script:adapterBytes) {
        [Array]::Clear($script:adapterBytes, 0, $script:adapterBytes.Length)
    }
    if ($null -ne $script:channelBytes) {
        [Array]::Clear($script:channelBytes, 0, $script:channelBytes.Length)
    }
}

exit $script:finalExitCode
