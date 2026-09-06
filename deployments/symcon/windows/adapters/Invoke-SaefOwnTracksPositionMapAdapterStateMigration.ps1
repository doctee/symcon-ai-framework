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
$ExpectedConfirmation = 'migrate-saef-owntracks-position-map-adapter-state'
$MaximumPolicyBytes = 1048576
$MaximumStateBytes = 67108864
$MaximumStateEntries = 4096
$TargetId = 'saef-owntracks-position-map'
$AdapterProfile = 'saef-owntracks-position-map-v1'
$LegacyStateDirectoryName = 'owntracks-position-map-adapter'
$SeparatedStateDirectoryName = 'owntracks-position-map'

$script:channelMutex = $null
$script:adapterMutex = $null
$script:channelMutexAcquired = $false
$script:adapterMutexAcquired = $false
$script:mutationAttempted = $false
$script:stateMoveAttempted = $false
$script:adapterPolicyMutationAttempted = $false
$script:channelPolicyMutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:failureCode = 'initialization'
$script:finalOutcome = 'failed'
$script:finalExitCode = $ExitManualRecovery
$script:sourceRoot = ''
$script:destinationRoot = ''
$script:adapterPolicyPath = ''
$script:channelBytes = $null
$script:adapterBytes = $null
$script:sourceIdentity = $null
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
    $item = Get-Item -LiteralPath $Path -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw [IO.IOException]::new('Directory is a reparse point.')
    }
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
        throw [Security.SecurityException]::new('Managed state ACL inherits from its parent.')
    }
    $requiredSids = @('S-1-5-18', 'S-1-5-32-544', $DeploymentSid)
    $fullControlSids = @{}
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entry.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow) {
            throw [Security.SecurityException]::new('Managed state ACL contains an unexpected rule.')
        }
        if ($sid -notin $requiredSids) {
            if (Test-BroadWriteAccess -Rights $entry.FileSystemRights) {
                throw [Security.SecurityException]::new('Managed state grants broad write access.')
            }
            throw [Security.SecurityException]::new('Managed state ACL contains an unexpected principal.')
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
            throw [Security.SecurityException]::new('Managed state ACL lacks a required principal rule.')
        }
    }
}

function Get-StateTreeIdentity {
    param([Parameter(Mandatory = $true)][string] $Root)

    Assert-PlainDirectory -Path $Root
    $records = New-Object System.Collections.Generic.List[string]
    $fileCount = 0
    $directoryCount = 0
    $totalBytes = 0L
    foreach ($entry in @(Get-ChildItem -LiteralPath $Root -Recurse -Force)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [IO.IOException]::new('Adapter state contains a reparse point.')
        }
        $relative = $entry.FullName.Substring($Root.TrimEnd([char[]] @('\', '/')).Length + 1).Replace('\', '/')
        if ($entry.PSIsContainer) {
            $directoryCount++
            $records.Add('D' + [char] 0 + $relative)
        } else {
            $fileCount++
            $totalBytes += [long] $entry.Length
            if ($totalBytes -gt $MaximumStateBytes) {
                throw [IO.IOException]::new('Adapter state exceeds its migration byte bound.')
            }
            $records.Add(
                'F' + [char] 0 + $relative + [char] 0 + [long] $entry.Length + [char] 0 +
                (Get-Sha256 -Path $entry.FullName)
            )
        }
        if ($fileCount + $directoryCount -gt $MaximumStateEntries) {
            throw [IO.IOException]::new('Adapter state exceeds its migration entry bound.')
        }
    }
    [string[]] $ordered = @($records)
    [Array]::Sort($ordered, [StringComparer]::Ordinal)
    $identityText = [string]::Join("`n", $ordered) + "`n"
    $identityBytes = [Text.UTF8Encoding]::new($false).GetBytes($identityText)
    try {
        return [ordered]@{
            sha256 = Get-BytesSha256 -Bytes $identityBytes
            fileCount = $fileCount
            directoryCount = $directoryCount
            bytes = $totalBytes
        }
    } finally {
        [Array]::Clear($identityBytes, 0, $identityBytes.Length)
    }
}

function Write-AtomicBytes {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][byte[]] $Bytes
    )

    $directory = Split-Path -Parent $Path
    Assert-PlainDirectory -Path $directory
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-owntracks-state-migration-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-state-migration-' + $token + '.bak')
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

function ConvertTo-Utf8JsonBytes {
    param([Parameter(Mandatory = $true)] $Value)

    $text = ($Value | ConvertTo-Json -Depth 12) + [Environment]::NewLine
    return [Text.UTF8Encoding]::new($false).GetBytes($text)
}

function Assert-GenericDeploymentInventory {
    param(
        [Parameter(Mandatory = $true)] $ChannelPolicy,
        [Parameter(Mandatory = $true)][string] $ExcludedStateRoot
    )

    $stateDirectories = @(Get-ChildItem -LiteralPath ([string] $ChannelPolicy.stateRoot) -Directory -Force |
        Where-Object { -not (Test-SamePath -Left $_.FullName -Right $ExcludedStateRoot) })
    $filesetDirectories = @(Get-ChildItem -LiteralPath ([string] $ChannelPolicy.managedFilesetRoot) -Directory -Force)
    $filesetNames = @{}
    foreach ($directory in $filesetDirectories) {
        if ($directory.Name -notmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$') {
            throw [InvalidOperationException]::new('Managed fileset root contains an unexpected directory.')
        }
        $filesetNames[$directory.Name] = $true
    }
    $mappedFilesets = @{}
    foreach ($directory in $stateDirectories) {
        if ($directory.Name -notmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$') {
            throw [InvalidOperationException]::new('Deployment state root contains an unexpected directory.')
        }
        $manifestPath = Join-Path $directory.FullName 'deployment.json'
        Assert-RootedLeaf -Path $manifestPath -MaximumBytes $MaximumPolicyBytes
        $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
        if ([string] $manifest.deploymentId -cne $directory.Name -or
            [string] $manifest.targetDirectoryName -notmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$' -or
            -not $filesetNames.ContainsKey([string] $manifest.targetDirectoryName) -or
            $mappedFilesets.ContainsKey([string] $manifest.targetDirectoryName)) {
            throw [InvalidOperationException]::new('Managed deployment pairing is invalid.')
        }
        $mappedFilesets[[string] $manifest.targetDirectoryName] = $true
    }
    if ($stateDirectories.Count -ne $filesetDirectories.Count -or
        $mappedFilesets.Count -ne $filesetNames.Count) {
        throw [InvalidOperationException]::new('Managed deployment roots are inconsistent.')
    }
    return $stateDirectories.Count
}

function Write-MigrationStatus {
    $details = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'owntracks_adapter_state_migration'
        operation = $Operation
        outcome = $script:finalOutcome
        exitCode = $script:finalExitCode
        failureCode = $script:failureCode
        sourceIdentitySha256 = if ($null -eq $script:sourceIdentity) { '' } else { [string] $script:sourceIdentity.sha256 }
        sourceFileCount = if ($null -eq $script:sourceIdentity) { 0 } else { [int] $script:sourceIdentity.fileCount }
        sourceDirectoryCount = if ($null -eq $script:sourceIdentity) { 0 } else { [int] $script:sourceIdentity.directoryCount }
        sourceBytes = if ($null -eq $script:sourceIdentity) { 0 } else { [long] $script:sourceIdentity.bytes }
        proposedAdapterPolicySha256 = $script:proposedAdapterPolicySha256
        proposedChannelPolicySha256 = $script:proposedChannelPolicySha256
        mutationAttempted = [bool] $script:mutationAttempted
        stateMoveAttempted = [bool] $script:stateMoveAttempted
        adapterPolicyMutationAttempted = [bool] $script:adapterPolicyMutationAttempted
        channelPolicyMutationAttempted = [bool] $script:channelPolicyMutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        moduleReloadAttempted = $false
        moduleActivationAttempted = $false
        symconRpcContactAttempted = $false
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
    if ($Operation -eq 'apply' -and $Confirmation -cne $ExpectedConfirmation) {
        throw [Security.SecurityException]::new('Explicit adapter-state migration confirmation is missing.')
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
        -not [IO.Path]::IsPathRooted([string] $channelPolicy.adapterStateRoot) -or
        [int] $channelPolicy.maxDeploymentCount -lt 17 -or
        [int] $channelPolicy.maxDeploymentCount -gt 64) {
        throw [InvalidOperationException]::new('Separated channel policy contract is invalid.')
    }
    Assert-PlainDirectory -Path ([string] $channelPolicy.stateRoot)
    Assert-PlainDirectory -Path ([string] $channelPolicy.managedFilesetRoot)
    Assert-PlainDirectory -Path ([string] $channelPolicy.adapterStateRoot)
    $targets = @($channelPolicy.standaloneModuleTargets | Where-Object {
        [string] $_.targetId -ceq $TargetId -and [string] $_.adapterProfile -ceq $AdapterProfile
    })
    if ($targets.Count -ne 1) {
        throw [InvalidOperationException]::new('OwnTracks target is not uniquely installed.')
    }
    $target = $targets[0]
    $script:adapterPolicyPath = [IO.Path]::GetFullPath([string] $target.adapterPolicyPath)
    Assert-RootedLeaf -Path $script:adapterPolicyPath -MaximumBytes $MaximumPolicyBytes
    if ((Get-Sha256 -Path $script:adapterPolicyPath) -cne [string] $target.expectedAdapterPolicySha256) {
        throw [Security.SecurityException]::new('Installed adapter policy hash differs from its target binding.')
    }
    $script:adapterBytes = [IO.File]::ReadAllBytes($script:adapterPolicyPath)
    $adapterText = [Text.UTF8Encoding]::new($false, $true).GetString($script:adapterBytes)
    $adapterPolicy = $adapterText | ConvertFrom-Json
    if ($adapterPolicy.formatVersion -ne 1 -or
        [string] $adapterPolicy.targetId -cne $TargetId -or
        [string] $adapterPolicy.adapterProfile -cne $AdapterProfile -or
        [string]::IsNullOrWhiteSpace([string] $adapterPolicy.mutexName)) {
        throw [InvalidOperationException]::new('Installed OwnTracks adapter policy contract is invalid.')
    }

    $script:sourceRoot = [IO.Path]::GetFullPath([string] $adapterPolicy.adapterStateRoot)
    $expectedSource = Join-Path ([string] $channelPolicy.stateRoot) $LegacyStateDirectoryName
    $script:destinationRoot = [IO.Path]::GetFullPath(
        (Join-Path ([string] $channelPolicy.adapterStateRoot) $SeparatedStateDirectoryName)
    )
    if (-not (Test-SamePath -Left $script:sourceRoot -Right $expectedSource) -or
        (Test-Path -LiteralPath $script:destinationRoot) -or
        (Test-PathContains -Parent ([string] $channelPolicy.stateRoot) -Candidate $script:destinationRoot) -or
        (Test-PathContains -Parent ([string] $channelPolicy.managedFilesetRoot) -Candidate $script:destinationRoot) -or
        -not [IO.Path]::GetPathRoot($script:sourceRoot).Equals(
            [IO.Path]::GetPathRoot($script:destinationRoot),
            [StringComparison]::OrdinalIgnoreCase
        )) {
        throw [InvalidOperationException]::new('Adapter-state migration path contract is invalid.')
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
    Assert-ProtectedAcl -Path $script:sourceRoot -DeploymentSid $deploymentSid
    Assert-ProtectedAcl -Path ([string] $channelPolicy.adapterStateRoot) -DeploymentSid $deploymentSid

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

    $script:failureCode = 'source_inventory'
    $script:sourceIdentity = Get-StateTreeIdentity -Root $script:sourceRoot
    $genericDeploymentCount = Assert-GenericDeploymentInventory `
        -ChannelPolicy $channelPolicy `
        -ExcludedStateRoot $script:sourceRoot
    if ($genericDeploymentCount -ge [int] $channelPolicy.maxDeploymentCount) {
        throw [InvalidOperationException]::new('Configured deployment count leaves no post-migration staging capacity.')
    }

    $adapterPolicy.adapterStateRoot = $script:destinationRoot
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
            throw [Security.SecurityException]::new('Migration apply requires an elevated local administrator.')
        }
        $script:mutationAttempted = $true
        $script:failureCode = 'state_move'
        $script:stateMoveAttempted = $true
        [IO.Directory]::Move($script:sourceRoot, $script:destinationRoot)
        $destinationIdentity = Get-StateTreeIdentity -Root $script:destinationRoot
        if ([string] $destinationIdentity.sha256 -cne [string] $script:sourceIdentity.sha256 -or
            [long] $destinationIdentity.bytes -ne [long] $script:sourceIdentity.bytes -or
            [int] $destinationIdentity.fileCount -ne [int] $script:sourceIdentity.fileCount -or
            [int] $destinationIdentity.directoryCount -ne [int] $script:sourceIdentity.directoryCount) {
            throw [IO.IOException]::new('Moved adapter-state identity differs.')
        }

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
        if ((Test-Path -LiteralPath $script:sourceRoot) -or
            -not (Test-Path -LiteralPath $script:destinationRoot -PathType Container) -or
            (Get-Sha256 -Path $script:adapterPolicyPath) -cne
                [string] $target.expectedAdapterPolicySha256 -or
            (Assert-GenericDeploymentInventory `
                -ChannelPolicy $channelPolicy `
                -ExcludedStateRoot $script:sourceRoot) -ne $genericDeploymentCount) {
            throw [InvalidOperationException]::new('Adapter-state migration postflight differs.')
        }
        Assert-ProtectedAcl -Path $script:destinationRoot -DeploymentSid $deploymentSid
        $script:failureCode = 'none'
        $script:finalOutcome = 'migrated'
        $script:finalExitCode = $ExitSuccess
    }
} catch {
    if ($script:mutationAttempted) {
        $script:rollbackAttempted = $true
        try {
            if ($null -ne $script:channelBytes -and $script:channelPolicyMutationAttempted) {
                Write-AtomicBytes -Path $ChannelPolicyPath -Bytes $script:channelBytes
            }
            if ($null -ne $script:adapterBytes -and $script:adapterPolicyMutationAttempted) {
                Write-AtomicBytes -Path $script:adapterPolicyPath -Bytes $script:adapterBytes
            }
            if ($script:destinationRoot -ne '' -and $script:sourceRoot -ne '' -and
                (Test-Path -LiteralPath $script:destinationRoot -PathType Container) -and
                -not (Test-Path -LiteralPath $script:sourceRoot)) {
                [IO.Directory]::Move($script:destinationRoot, $script:sourceRoot)
            }
            $script:rollbackSucceeded = (Test-Path -LiteralPath $script:sourceRoot -PathType Container) -and
                -not (Test-Path -LiteralPath $script:destinationRoot) -and
                (Get-Sha256 -Path $ChannelPolicyPath) -ceq $ExpectedChannelPolicySha256 -and
                (Get-BytesSha256 -Bytes $script:adapterBytes) -ceq (Get-Sha256 -Path $script:adapterPolicyPath)
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
        Write-MigrationStatus
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
