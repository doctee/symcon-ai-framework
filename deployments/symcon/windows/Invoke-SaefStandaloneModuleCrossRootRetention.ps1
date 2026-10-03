[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('plan', 'apply', 'inspect')]
    [string] $Operation,

    [Parameter(Mandatory = $true)]
    [string] $TargetId,

    [Parameter(Mandatory = $true)]
    [string] $ChannelPolicyPath,

    [Parameter(Mandatory = $true)]
    [string] $ReviewPlanPath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath,

    [Parameter()]
    [string] $ExpectedReviewPlanSha256 = '',

    [Parameter()]
    [string] $Confirmation = ''
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$ExitSuccess = 0
$ExitRejected = 10
$ExitFailedBeforeMutation = 20
$ExitRolledBack = 30
$ExitManualRecovery = 40
$ChannelMutexName = 'Global\SAEF.DeploymentChannel'
$MaximumJsonBytes = 1048576L
$script:channelMutex = $null
$script:adapterMutex = $null
$script:channelMutexAcquired = $false
$script:adapterMutexAcquired = $false
$script:writerLocks = @()
$script:claimCreated = $false
$script:operationalMutationAttempted = $false
$script:backupMutationAttempted = $false
$script:quarantineMutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:movedArtifacts = @()
$script:failureCode = 'contract'
$script:backupPath = ''
$script:quarantinePath = ''
$script:claimPath = ''

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

function Get-TextSha256 {
    param([Parameter(Mandatory = $true)][string] $Text)
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    try {
        return Get-BytesSha256 -Bytes $bytes
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Test-HexSha256 {
    param([Parameter()][string] $Value)
    return $Value -cmatch '^[a-f0-9]{64}$'
}

function Test-SafeIdentifier {
    param([Parameter()][string] $Value)
    return $Value -cmatch '^[a-z0-9][a-z0-9._-]{0,127}$'
}

function Get-OrdinalSortedStrings {
    param([Parameter(Mandatory = $true)][object[]] $Values)
    $result = [string[]] @($Values | ForEach-Object { [string] $_ })
    [Array]::Sort($result, [StringComparer]::Ordinal)
    return @($result)
}

function Get-OrdinalSortedObjects {
    param(
        [Parameter(Mandatory = $true)][object[]] $Values,
        [Parameter(Mandatory = $true)][string] $Property,
        [Parameter()][switch] $Descending
    )
    $result = [object[]] @($Values)
    $propertyName = $Property
    $isDescending = [bool] $Descending
    [Array]::Sort($result, [Comparison[object]] {
        param($left, $right)
        $leftProperty = $left.PSObject.Properties[$propertyName]
        $rightProperty = $right.PSObject.Properties[$propertyName]
        if ($null -eq $leftProperty -or $null -eq $rightProperty) {
            throw [InvalidOperationException]::new('Ordinal sort property is missing.')
        }
        $comparison = [string]::CompareOrdinal(
            [string] $leftProperty.Value,
            [string] $rightProperty.Value
        )
        if ($isDescending) { return -$comparison }
        return $comparison
    })
    return @($result)
}

function ConvertFrom-RoundtripUtcTimestamp {
    param([Parameter(Mandatory = $true)][string] $Value)
    $parsed = [DateTimeOffset]::MinValue
    if (-not [DateTimeOffset]::TryParseExact(
            $Value,
            'o',
            [Globalization.CultureInfo]::InvariantCulture,
            [Globalization.DateTimeStyles]::RoundtripKind,
            [ref] $parsed
        )) {
        throw [InvalidOperationException]::new('Protocol timestamp is not exact round-trip format.')
    }
    return $parsed.ToUniversalTime()
}

function Assert-RootedLeaf {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter()][long] $MaximumBytes = $MaximumJsonBytes
    )
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [IO.FileNotFoundException]::new('Required rooted file is missing.')
    }
    $item = Get-Item -LiteralPath $Path -Force
    if ($item.Length -lt 1 -or $item.Length -gt $MaximumBytes -or
        (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.IOException]::new('Required rooted file is unsafe.')
    }
}

function Assert-PlainTree {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Required rooted directory is missing.')
    }
    foreach ($entry in @((Get-Item -LiteralPath $Path -Force)) +
        @(Get-ChildItem -LiteralPath $Path -Recurse -Force)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [IO.IOException]::new('Managed tree contains a reparse point.')
        }
    }
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
    param([Parameter(Mandatory = $true)][string] $Path)
    $acl = Get-Acl -LiteralPath $Path
    $trustedOwners = @(
        'S-1-5-18',
        'S-1-5-32-544',
        [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    )
    if (-not $acl.AreAccessRulesProtected -or
        $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -notin $trustedOwners) {
        throw [Security.SecurityException]::new('Managed path ACL is inherited or has an untrusted owner.')
    }
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entry.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            (Test-BroadWriteAccess -Rights $entry.FileSystemRights) -and
            $sid -in @('S-1-1-0', 'S-1-5-11', 'S-1-5-32-545')) {
            throw [Security.SecurityException]::new('Managed path grants broad write access.')
        }
    }
}

function Protect-ChildAcl {
    param([Parameter(Mandatory = $true)][string] $Path)
    $acl = Get-Acl -LiteralPath $Path
    $acl.SetAccessRuleProtection($true, $true)
    Set-Acl -LiteralPath $Path -AclObject $acl
    Assert-ProtectedAcl -Path $Path
}

function Get-ChildPath {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][string] $Name
    )
    if (-not (Test-SafeIdentifier -Value $Name)) {
        throw [InvalidOperationException]::new('Managed child name is unsafe.')
    }
    $fullRoot = [IO.Path]::GetFullPath($Root).TrimEnd([char[]] @('\', '/'))
    $fullPath = [IO.Path]::GetFullPath((Join-Path $fullRoot $Name))
    if (-not $fullPath.StartsWith(
            $fullRoot + [IO.Path]::DirectorySeparatorChar,
            [StringComparison]::OrdinalIgnoreCase
        )) {
        throw [InvalidOperationException]::new('Managed child escapes its configured root.')
    }
    return $fullPath
}

function Read-BoundedJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter()][long] $MaximumBytes = $MaximumJsonBytes
    )
    Assert-RootedLeaf -Path $Path -MaximumBytes $MaximumBytes
    return [Text.UTF8Encoding]::new($false, $true).GetString([IO.File]::ReadAllBytes($Path)) |
        ConvertFrom-Json
}

function Write-AtomicJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value
    )
    $directory = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Output directory is missing.')
    }
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-cross-root-retention-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-cross-root-retention-' + $token + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            (($Value | ConvertTo-Json -Depth 20) + [Environment]::NewLine),
            [Text.UTF8Encoding]::new($false)
        )
        if (Test-Path -LiteralPath $Path -PathType Leaf) {
            [IO.File]::Replace($temporary, $Path, $backup)
        } else {
            [IO.File]::Move($temporary, $Path)
        }
    } finally {
        if (Test-Path -LiteralPath $temporary) { Remove-Item -LiteralPath $temporary -Force }
        if (Test-Path -LiteralPath $backup) { Remove-Item -LiteralPath $backup -Force }
    }
}

function Write-Status {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][hashtable] $Details = @{}
    )
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'standalone_module_cross_root_retention'
        operation = $Operation
        outcome = $Outcome
        exitCode = $ExitCode
        targetId = $TargetId
        failureCode = $script:failureCode
        claimCreated = [bool] $script:claimCreated
        backupMutationAttempted = [bool] $script:backupMutationAttempted
        operationalMutationAttempted = [bool] $script:operationalMutationAttempted
        quarantineMutationAttempted = [bool] $script:quarantineMutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        backupPath = $script:backupPath
        quarantinePath = $script:quarantinePath
        claimPath = $script:claimPath
        serviceRestartAttempted = $false
        liveSymconRpcContactAttempted = $false
        mqttPublishAttempted = $false
        ownerMutationAttempted = $false
        eventMutationAttempted = $false
        deviceActionAttempted = $false
        publicationAttempted = $false
        retentionDeletionAttempted = $false
    }
    foreach ($key in $Details.Keys) { $status[$key] = $Details[$key] }
    Write-AtomicJson -Path $StatusPath -Value $status
}

function Get-TreeIdentity {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )
    Assert-PlainTree -Path $Path
    $root = [IO.Path]::GetFullPath($Path).TrimEnd([char[]] @('\', '/'))
    $records = @()
    $totalBytes = 0L
    foreach ($entry in @(Get-ChildItem -LiteralPath $root -Recurse -Force)) {
        $relative = $entry.FullName.Substring($root.Length + 1).Replace('\', '/')
        if ($relative -notmatch '^[\x20-\x7E]+$') {
            throw [InvalidOperationException]::new('Managed tree contains a non-ASCII relative path.')
        }
        if ($entry.PSIsContainer) {
            $records += [pscustomobject]@{ key = 'D:' + $relative; line = 'D' + [char] 0 + $relative + "`n" }
        } else {
            $totalBytes += [long] $entry.Length
            if ($totalBytes -gt $MaximumBytes) {
                throw [InvalidOperationException]::new('Managed tree exceeds its byte bound.')
            }
            $hash = Get-Sha256 -Path $entry.FullName
            $line = 'F' + [char] 0 + $relative + [char] 0 + [string] $entry.Length +
                [char] 0 + $hash + "`n"
            $records += [pscustomobject]@{ key = 'F:' + $relative; line = $line }
        }
    }
    $records = @(Get-OrdinalSortedObjects -Values $records -Property key)
    $identity = [Text.StringBuilder]::new()
    foreach ($record in $records) { $null = $identity.Append([string] $record.line) }
    return [ordered]@{
        bytes = $totalBytes
        entryCount = $records.Count
        sha256 = Get-TextSha256 -Text $identity.ToString()
    }
}

function Get-ArtifactRecord {
    param(
        [Parameter(Mandatory = $true)][string] $Role,
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][string] $Name,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )
    $path = Get-ChildPath -Root $Root -Name $Name
    $identity = Get-TreeIdentity -Path $path -MaximumBytes $MaximumBytes
    return [pscustomobject]@{
        key = $Role + ':' + $Name
        role = $Role
        name = $Name
        bytes = [long] $identity.bytes
        entryCount = [int] $identity.entryCount
        sha256 = [string] $identity.sha256
    }
}

function Get-InventorySha256 {
    param(
        [Parameter(Mandatory = $true)][object[]] $Artifacts,
        [Parameter(Mandatory = $true)][string] $ActiveBinding
    )
    $records = @(Get-OrdinalSortedObjects -Values $Artifacts -Property key)
    $builder = [Text.StringBuilder]::new()
    $null = $builder.Append('active').Append([char] 0).Append($ActiveBinding).Append("`n")
    foreach ($record in $records) {
        $null = $builder.Append([string] $record.role).Append([char] 0)
            .Append([string] $record.name).Append([char] 0)
            .Append([long] $record.bytes).Append([char] 0)
            .Append([int] $record.entryCount).Append([char] 0)
            .Append([string] $record.sha256).Append("`n")
    }
    return Get-TextSha256 -Text $builder.ToString()
}

function Read-Contracts {
    Assert-RootedLeaf -Path $ChannelPolicyPath
    $channelPolicySha256 = Get-Sha256 -Path $ChannelPolicyPath
    $channel = Read-BoundedJson -Path $ChannelPolicyPath
    if ($channel.formatVersion -ne 1) {
        throw [InvalidOperationException]::new('Channel policy format is unsupported.')
    }
    foreach ($name in @(
        'scriptsRoot', 'managedFilesetRoot', 'stateRoot', 'adapterStateRoot',
        'standaloneModuleCrossRootRetentionPath'
    )) {
        $property = $channel.PSObject.Properties[$name]
        if ($null -eq $property -or -not [IO.Path]::IsPathRooted([string] $property.Value)) {
            throw [InvalidOperationException]::new('Channel policy root is invalid.')
        }
    }
    if (-not (Test-HexSha256 -Value ([string] $channel.expectedStandaloneModuleCrossRootRetentionSha256)) -or
        [IO.Path]::GetFullPath([string] $channel.standaloneModuleCrossRootRetentionPath) -cne
            [IO.Path]::GetFullPath($PSCommandPath) -or
        (Get-Sha256 -Path $PSCommandPath) -cne
            [string] $channel.expectedStandaloneModuleCrossRootRetentionSha256) {
        throw [Security.SecurityException]::new('Cross-root retention source identity differs.')
    }
    $targets = @($channel.standaloneModuleTargets | Where-Object {
        [string] $_.targetId -ceq $TargetId
    })
    if ($targets.Count -ne 1) {
        throw [InvalidOperationException]::new('Target is not uniquely allowlisted by the channel.')
    }
    $target = $targets[0]
    if (-not (Test-HexSha256 -Value ([string] $target.expectedAdapterPolicySha256))) {
        throw [InvalidOperationException]::new('Target adapter policy hash is invalid.')
    }
    $adapterPolicyPath = [string] $target.adapterPolicyPath
    Assert-RootedLeaf -Path $adapterPolicyPath
    if ((Get-Sha256 -Path $adapterPolicyPath) -cne [string] $target.expectedAdapterPolicySha256) {
        throw [InvalidOperationException]::new('Target adapter policy differs from the channel binding.')
    }
    $adapter = Read-BoundedJson -Path $adapterPolicyPath
    if ($adapter.formatVersion -ne 1 -or [string] $adapter.targetId -cne $TargetId -or
        [string] $adapter.adapterProfile -cne [string] $target.adapterProfile -or
        [string] $adapter.adapterProfile -cnotmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$' -or
        [string] $adapter.mutexName -cnotmatch '^Global\\SAEF\.[A-Za-z0-9.]{1,96}$') {
        throw [InvalidOperationException]::new('Target adapter policy identity is invalid.')
    }
    $retention = $adapter.retention
    foreach ($name in @(
        'profile', 'implemented', 'backupRoot', 'quarantineRoot', 'claimRoot', 'requiredConfirmation',
        'minimumAgeHours', 'keepSuccessfulRollbackCount', 'keepFailedCandidateCount',
        'maximumArtifactCount', 'maximumUnitBytes', 'maximumPlanBytes',
        'maximumPlanAgeSeconds', 'maximumReferenceFiles', 'maximumReferenceBytes'
    )) {
        if ($null -eq $retention.PSObject.Properties[$name]) {
            throw [InvalidOperationException]::new('Adapter retention policy is incomplete.')
        }
    }
    if ([string] $retention.profile -cne 'saef-channel-v8-standalone-module-cross-root-v1' -or
        $retention.implemented -isnot [bool] -or -not [bool] $retention.implemented -or
        [string] $retention.requiredConfirmation -cne ('quarantine-' + $TargetId + '-cross-root-retention') -or
        [int] $retention.minimumAgeHours -lt 24 -or
        [int] $retention.keepSuccessfulRollbackCount -lt 1 -or
        [int] $retention.keepFailedCandidateCount -lt 1 -or
        [int] $retention.maximumArtifactCount -lt 4 -or [int] $retention.maximumArtifactCount -gt 64 -or
        [long] $retention.maximumUnitBytes -lt 1048576 -or [long] $retention.maximumUnitBytes -gt 1073741824 -or
        [long] $retention.maximumPlanBytes -lt [long] $retention.maximumUnitBytes -or
        [long] $retention.maximumPlanBytes -gt 2147483648 -or
        [int] $retention.maximumPlanAgeSeconds -lt 60 -or [int] $retention.maximumPlanAgeSeconds -gt 3600 -or
        [int] $retention.maximumReferenceFiles -lt 1 -or [int] $retention.maximumReferenceFiles -gt 1024 -or
        [long] $retention.maximumReferenceBytes -lt 4096 -or
        [long] $retention.maximumReferenceBytes -gt 67108864) {
        throw [InvalidOperationException]::new('Adapter retention policy bounds are invalid.')
    }
    foreach ($path in @(
        [string] $channel.managedFilesetRoot,
        [string] $channel.stateRoot,
        [string] $adapter.adapterStateRoot,
        [string] $retention.backupRoot,
        [string] $retention.quarantineRoot,
        [string] $retention.claimRoot
    )) {
        Assert-PlainTree -Path $path
        Assert-ProtectedAcl -Path $path
    }
    $operationalRoots = @(
        [IO.Path]::GetFullPath([string] $channel.managedFilesetRoot).TrimEnd([char[]] @('\', '/')),
        [IO.Path]::GetFullPath([string] $channel.stateRoot).TrimEnd([char[]] @('\', '/')),
        [IO.Path]::GetFullPath([string] $adapter.adapterStateRoot).TrimEnd([char[]] @('\', '/'))
    )
    $managedRoots = @($operationalRoots) + @(
        [IO.Path]::GetFullPath([string] $retention.backupRoot).TrimEnd([char[]] @('\', '/')),
        [IO.Path]::GetFullPath([string] $retention.quarantineRoot).TrimEnd([char[]] @('\', '/')),
        [IO.Path]::GetFullPath([string] $retention.claimRoot).TrimEnd([char[]] @('\', '/'))
    )
    for ($leftIndex = 0; $leftIndex -lt $managedRoots.Count; $leftIndex++) {
        for ($rightIndex = $leftIndex + 1; $rightIndex -lt $managedRoots.Count; $rightIndex++) {
            $left = $managedRoots[$leftIndex]
            $right = $managedRoots[$rightIndex]
            if ($left.Equals($right, [StringComparison]::OrdinalIgnoreCase) -or
                $left.StartsWith($right + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -or
                $right.StartsWith($left + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
                throw [InvalidOperationException]::new('Retention roots are not pairwise disjoint.')
            }
        }
    }
    $quarantineVolume = [IO.Path]::GetPathRoot([IO.Path]::GetFullPath([string] $retention.quarantineRoot))
    foreach ($root in $operationalRoots) {
        if (-not [string]::Equals([IO.Path]::GetPathRoot($root), $quarantineVolume,
                [StringComparison]::OrdinalIgnoreCase)) {
            throw [InvalidOperationException]::new('Operational roots and quarantine must share one volume.')
        }
    }
    return [ordered]@{
        channel = $channel
        channelPolicySha256 = $channelPolicySha256
        target = $target
        adapter = $adapter
        adapterPolicyPath = $adapterPolicyPath
        adapterPolicySha256 = [string] $target.expectedAdapterPolicySha256
        retention = $retention
    }
}

function Get-ReferenceTexts {
    param([Parameter(Mandatory = $true)] $Contracts)
    $texts = @()
    if ($Contracts.target.PSObject.Properties.Name -notcontains 'approvalPolicyPath') {
        return @($texts)
    }
    Assert-RootedLeaf -Path ([string] $Contracts.target.approvalPolicyPath)
    if ((Get-Sha256 -Path ([string] $Contracts.target.approvalPolicyPath)) -cne
            [string] $Contracts.target.expectedApprovalPolicySha256)) {
        throw [InvalidOperationException]::new('Approval policy differs from its channel binding.')
    }
    $approval = Read-BoundedJson -Path ([string] $Contracts.target.approvalPolicyPath)
    $root = [string] $approval.approvalStateRoot
    Assert-PlainTree -Path $root
    Assert-ProtectedAcl -Path $root
    $files = @(Get-ChildItem -LiteralPath $root -File -Recurse -Force)
    if ($files.Count -gt [int] $Contracts.retention.maximumReferenceFiles) {
        throw [InvalidOperationException]::new('Approval reference inventory exceeds its file bound.')
    }
    $total = 0L
    foreach ($file in $files) {
        $total += [long] $file.Length
        if ($total -gt [long] $Contracts.retention.maximumReferenceBytes) {
            throw [InvalidOperationException]::new('Approval reference inventory exceeds its byte bound.')
        }
        $texts += [Text.UTF8Encoding]::new($false, $true).GetString([IO.File]::ReadAllBytes($file.FullName))
    }
    return @($texts)
}

function Test-Referenced {
    param(
        [Parameter(Mandatory = $true)][string[]] $Texts,
        [Parameter(Mandatory = $true)][string] $DeploymentId,
        [Parameter(Mandatory = $true)][string] $PackageIdentitySha256
    )
    foreach ($text in $Texts) {
        if ($text.Contains($DeploymentId) -or $text.Contains($PackageIdentitySha256)) { return $true }
    }
    return $false
}

function Get-Inventory {
    param([Parameter(Mandatory = $true)] $Contracts)
    $channel = $Contracts.channel
    $adapter = $Contracts.adapter
    $maximumBytes = [long] $Contracts.retention.maximumUnitBytes
    $stateDirectories = @(Get-OrdinalSortedObjects `
        -Values @(Get-ChildItem -LiteralPath ([string] $channel.stateRoot) -Directory -Force) -Property Name)
    $filesetDirectories = @(Get-OrdinalSortedObjects `
        -Values @(Get-ChildItem -LiteralPath ([string] $channel.managedFilesetRoot) -Directory -Force) -Property Name)
    $filesets = @{}
    foreach ($directory in $filesetDirectories) {
        if (-not (Test-SafeIdentifier -Value $directory.Name) -or $filesets.ContainsKey($directory.Name)) {
            throw [InvalidOperationException]::new('Managed fileset inventory is invalid.')
        }
        $filesets[$directory.Name] = $directory
    }
    $deployments = @{}
    $filesetOwners = @{}
    $artifacts = @()
    foreach ($directory in $stateDirectories) {
        if (-not (Test-SafeIdentifier -Value $directory.Name) -or $deployments.ContainsKey($directory.Name)) {
            throw [InvalidOperationException]::new('Deployment-state inventory is invalid.')
        }
        $manifest = Read-BoundedJson -Path (Join-Path $directory.FullName 'deployment.json')
        $deploymentId = [string] $manifest.deploymentId
        $filesetName = [string] $manifest.targetDirectoryName
        $kind = if ($manifest.PSObject.Properties.Name -contains 'deploymentKind') {
            [string] $manifest.deploymentKind
        } else { 'runtime-fileset' }
        if ($deploymentId -cne $directory.Name -or -not (Test-SafeIdentifier -Value $filesetName) -or
            $kind -notin @('runtime-fileset', 'standalone-module') -or
            -not $filesets.ContainsKey($filesetName) -or $filesetOwners.ContainsKey($filesetName)) {
            throw [InvalidOperationException]::new('Channel deployment/fileset correlation is invalid.')
        }
        $filesetOwners[$filesetName] = $deploymentId
        $status = $null
        $statusPath = Join-Path $directory.FullName 'status.json'
        if (Test-Path -LiteralPath $statusPath -PathType Leaf) { $status = Read-BoundedJson -Path $statusPath }
        $record = [pscustomobject]@{
            deploymentId = $deploymentId
            filesetName = $filesetName
            kind = $kind
            manifest = $manifest
            status = $status
        }
        $deployments[$deploymentId] = $record
        $artifacts += Get-ArtifactRecord -Role 'channel-state' -Root ([string] $channel.stateRoot) `
            -Name $deploymentId -MaximumBytes $maximumBytes
        $artifacts += Get-ArtifactRecord -Role 'managed-fileset' -Root ([string] $channel.managedFilesetRoot) `
            -Name $filesetName -MaximumBytes $maximumBytes
    }
    if ($filesetOwners.Count -ne $filesets.Count) {
        throw [InvalidOperationException]::new('Channel roots contain an unpaired managed fileset.')
    }
    $activeBinding = ''
    $activeDeploymentId = ''
    $activeTransactionName = ''
    $activePackageIdentitySha256 = ''
    $activePath = Join-Path ([string] $adapter.adapterStateRoot) 'active.json'
    if (Test-Path -LiteralPath $activePath -PathType Leaf) {
        $active = Read-BoundedJson -Path $activePath
        if ($active.formatVersion -ne 1 -or [string] $active.adapterProfile -cne
                [string] $adapter.adapterProfile -or
            -not (Test-SafeIdentifier -Value ([string] $active.deploymentId)) -or
            -not (Test-SafeIdentifier -Value ([string] $active.transactionDirectoryName)) -or
            -not (Test-HexSha256 -Value ([string] $active.packageIdentitySha256))) {
            throw [InvalidOperationException]::new('Active adapter binding is invalid.')
        }
        $activeDeploymentId = [string] $active.deploymentId
        $activeTransactionName = [string] $active.transactionDirectoryName
        $activePackageIdentitySha256 = [string] $active.packageIdentitySha256
        $activeBinding = $activeDeploymentId + [char] 0 + $activeTransactionName + [char] 0 +
            $activePackageIdentitySha256
    }
    $transactions = @{}
    $transactionDirectories = @(Get-OrdinalSortedObjects `
        -Values @(Get-ChildItem -LiteralPath ([string] $adapter.adapterStateRoot) -Directory -Force) -Property Name)
    foreach ($directory in $transactionDirectories) {
        if (-not (Test-SafeIdentifier -Value $directory.Name)) {
            throw [InvalidOperationException]::new('Adapter transaction name is invalid.')
        }
        $transactionPath = Join-Path $directory.FullName 'transaction.json'
        $snapshotPath = Join-Path $directory.FullName 'snapshot.json'
        $transaction = $null
        $snapshot = $null
        if (Test-Path -LiteralPath $transactionPath -PathType Leaf) {
            $transaction = Read-BoundedJson -Path $transactionPath -MaximumBytes ([long] $adapter.maximumStateBytes)
        }
        if (Test-Path -LiteralPath $snapshotPath -PathType Leaf) {
            $snapshot = Read-BoundedJson -Path $snapshotPath -MaximumBytes ([long] $adapter.maximumStateBytes)
        }
        $deploymentId = if ($null -ne $transaction) { [string] $transaction.deploymentId } elseif ($null -ne $snapshot) {
            [string] $snapshot.deploymentId
        } else { '' }
        $packageIdentity = if ($null -ne $transaction) { [string] $transaction.packageIdentitySha256 } elseif ($null -ne $snapshot) {
            [string] $snapshot.packageIdentitySha256
        } else { '' }
        if (-not (Test-SafeIdentifier -Value $deploymentId) -or
            -not (Test-HexSha256 -Value $packageIdentity) -or $transactions.ContainsKey($deploymentId)) {
            throw [InvalidOperationException]::new('Adapter transaction correlation is invalid.')
        }
        if ($null -ne $transaction -and
            ($transaction.formatVersion -ne 1 -or [string] $transaction.adapterProfile -cne
                [string] $adapter.adapterProfile -or
                [string] $transaction.transactionDirectoryName -cne $directory.Name -or
                [string] $transaction.outcome -notin @(
                    'activated', 'rolled_back', 'manual_recovery_required'
                ))) {
            throw [InvalidOperationException]::new('Adapter transaction identity is invalid.')
        }
        $record = [pscustomobject]@{
            deploymentId = $deploymentId
            packageIdentitySha256 = $packageIdentity
            transactionName = $directory.Name
            transaction = $transaction
            interrupted = $null -eq $transaction
        }
        $transactions[$deploymentId] = $record
        $artifacts += Get-ArtifactRecord -Role 'adapter-transaction' `
            -Root ([string] $adapter.adapterStateRoot) -Name $directory.Name -MaximumBytes $maximumBytes
    }
    if (-not [string]::IsNullOrEmpty($activeBinding)) {
        if (-not $transactions.ContainsKey($activeDeploymentId) -or
            -not $deployments.ContainsKey($activeDeploymentId)) {
            throw [InvalidOperationException]::new('Active adapter binding lacks its cross-root pair.')
        }
        $activeTransaction = $transactions[$activeDeploymentId]
        $activeDeployment = $deployments[$activeDeploymentId]
        if ([string] $activeTransaction.transactionName -cne $activeTransactionName -or
            [string] $activeTransaction.packageIdentitySha256 -cne $activePackageIdentitySha256 -or
            [string] $activeDeployment.manifest.module.targetId -cne $TargetId -or
            [string] $activeDeployment.manifest.module.packageIdentitySha256 -cne
                $activePackageIdentitySha256) {
            throw [InvalidOperationException]::new('Active adapter binding differs across roots.')
        }
    }
    if ($artifacts.Count -gt ([int] $Contracts.retention.maximumArtifactCount * 3)) {
        throw [InvalidOperationException]::new('Cross-root artifact count exceeds its safety bound.')
    }
    $referenceTexts = @(Get-ReferenceTexts -Contracts $Contracts)
    $units = @()
    foreach ($deploymentId in @(Get-OrdinalSortedStrings -Values @($deployments.Keys))) {
        $deployment = $deployments[$deploymentId]
        if ([string] $deployment.kind -cne 'standalone-module' -or
            [string] $deployment.manifest.module.targetId -cne $TargetId) { continue }
        $transaction = if ($transactions.ContainsKey($deploymentId)) { $transactions[$deploymentId] } else { $null }
        $statusOutcome = if ($null -ne $deployment.status) { [string] $deployment.status.outcome } else { '' }
        $isStaged = $statusOutcome -in @('', 'staged', 'passed', 'ready')
        if ($null -eq $transaction) {
            if (-not $isStaged) {
                throw [InvalidOperationException]::new('Terminal target deployment lacks its adapter transaction.')
            }
            continue
        }
        if ([string] $deployment.manifest.module.packageIdentitySha256 -cne
            [string] $transaction.packageIdentitySha256) {
            throw [InvalidOperationException]::new('Target package identity differs across roots.')
        }
        $outcome = if ($transaction.interrupted) { 'interrupted' } else { [string] $transaction.transaction.outcome }
        $completedUtc = ''
        $completed = $null
        if ($outcome -in @('activated', 'rolled_back')) {
            $completed = ConvertFrom-RoundtripUtcTimestamp -Value ([string] $transaction.transaction.completedUtc)
            $completedUtc = $completed.ToString('o')
        }
        $reasons = @()
        if ($transaction.interrupted) { $reasons += 'interrupted' }
        if ($outcome -eq 'manual_recovery_required') { $reasons += 'manual-recovery' }
        if ($activeBinding.StartsWith($deploymentId + [char] 0, [StringComparison]::Ordinal)) {
            $reasons += 'active-transaction'
        }
        if ($isStaged) { $reasons += 'staged-deployment' }
        if ($null -ne $completed -and
            ([DateTimeOffset]::UtcNow - $completed).TotalHours -lt [int] $Contracts.retention.minimumAgeHours) {
            $reasons += 'minimum-age'
        }
        if (Test-Referenced -Texts $referenceTexts -DeploymentId $deploymentId `
                -PackageIdentitySha256 ([string] $transaction.packageIdentitySha256)) {
            $reasons += 'approval-or-recovery-reference'
        }
        $units += [pscustomobject]@{
            key = $deploymentId
            deploymentId = $deploymentId
            filesetName = [string] $deployment.filesetName
            transactionName = [string] $transaction.transactionName
            packageIdentitySha256 = [string] $transaction.packageIdentitySha256
            outcome = $outcome
            completedUtc = $completedUtc
            protectionReasons = @($reasons)
        }
    }
    foreach ($transactionId in @($transactions.Keys)) {
        if (-not $deployments.ContainsKey($transactionId)) {
            throw [InvalidOperationException]::new('Adapter transaction lacks its channel deployment pair.')
        }
    }
    foreach ($outcome in @('activated', 'rolled_back')) {
        $keep = if ($outcome -eq 'activated') {
            [int] $Contracts.retention.keepSuccessfulRollbackCount
        } else { [int] $Contracts.retention.keepFailedCandidateCount }
        $matching = @(Get-OrdinalSortedObjects `
            -Values @($units | Where-Object { $_.outcome -ceq $outcome }) `
            -Property completedUtc -Descending)
        for ($index = 0; $index -lt [Math]::Min($keep, $matching.Count); $index++) {
            $matching[$index].protectionReasons = @($matching[$index].protectionReasons) + @('recent-history')
        }
    }
    $eligible = @($units | Where-Object {
        @($_.protectionReasons).Count -eq 0 -and $_.outcome -in @('activated', 'rolled_back')
    })
    $eligible = @(Get-OrdinalSortedObjects -Values $eligible -Property deploymentId)
    $inventoryHash = Get-InventorySha256 -Artifacts $artifacts -ActiveBinding $activeBinding
    return [ordered]@{
        activeBinding = $activeBinding
        artifacts = @($artifacts)
        units = @($units)
        eligible = @($eligible)
        sha256 = $inventoryHash
    }
}

function Get-UnitArtifacts {
    param(
        [Parameter(Mandatory = $true)] $Inventory,
        [Parameter(Mandatory = $true)] $Unit
    )
    $keys = @(
        'channel-state:' + [string] $Unit.deploymentId,
        'managed-fileset:' + [string] $Unit.filesetName,
        'adapter-transaction:' + [string] $Unit.transactionName
    )
    $result = @($Inventory.artifacts | Where-Object { [string] $_.key -in $keys })
    if ($result.Count -ne 3) {
        throw [InvalidOperationException]::new('Retention unit is not an exact three-root artifact set.')
    }
    return @(Get-OrdinalSortedObjects -Values $result -Property key)
}

function Get-PostInventorySha256 {
    param(
        [Parameter(Mandatory = $true)] $Inventory,
        [Parameter(Mandatory = $true)][object[]] $Candidates
    )
    $removed = @{}
    foreach ($candidate in $Candidates) {
        foreach ($artifact in @(Get-UnitArtifacts -Inventory $Inventory -Unit $candidate)) {
            $removed[[string] $artifact.key] = $true
        }
    }
    $remaining = @($Inventory.artifacts | Where-Object { -not $removed.ContainsKey([string] $_.key) })
    return Get-InventorySha256 -Artifacts $remaining -ActiveBinding ([string] $Inventory.activeBinding)
}

function New-ReviewPlan {
    param(
        [Parameter(Mandatory = $true)] $Contracts,
        [Parameter(Mandatory = $true)] $Inventory
    )
    if (Test-Path -LiteralPath $ReviewPlanPath) {
        throw [InvalidOperationException]::new('Review plan output already exists.')
    }
    $candidates = @()
    $totalBytes = 0L
    foreach ($unit in @($Inventory.eligible)) {
        $artifacts = @(Get-UnitArtifacts -Inventory $Inventory -Unit $unit)
        foreach ($artifact in $artifacts) { $totalBytes += [long] $artifact.bytes }
        if ($totalBytes -gt [long] $Contracts.retention.maximumPlanBytes) {
            throw [InvalidOperationException]::new('Retention plan exceeds its total byte bound.')
        }
        $candidates += [ordered]@{
            deploymentId = [string] $unit.deploymentId
            filesetName = [string] $unit.filesetName
            transactionName = [string] $unit.transactionName
            packageIdentitySha256 = [string] $unit.packageIdentitySha256
            outcome = [string] $unit.outcome
            completedUtc = [string] $unit.completedUtc
            artifacts = @($artifacts | ForEach-Object {
                [ordered]@{
                    role = [string] $_.role
                    name = [string] $_.name
                    bytes = [long] $_.bytes
                    entryCount = [int] $_.entryCount
                    sha256 = [string] $_.sha256
                }
            })
        }
    }
    $generated = [DateTimeOffset]::UtcNow
    $plan = [ordered]@{
        formatVersion = 1
        purpose = 'saef-channel-v8-standalone-module-cross-root-retention'
        profile = [string] $Contracts.retention.profile
        targetId = $TargetId
        adapterProfile = [string] $Contracts.adapter.adapterProfile
        allowedOperation = 'quarantine'
        generatedAtUtc = $generated.ToString('o')
        expiresAtUtc = $generated.AddSeconds([int] $Contracts.retention.maximumPlanAgeSeconds).ToString('o')
        channelPolicySha256 = [string] $Contracts.channelPolicySha256
        adapterPolicySha256 = [string] $Contracts.adapterPolicySha256
        activePackageIdentitySha256 = [string] $Contracts.adapter.expectedActivePackageIdentitySha256
        activeBinding = [string] $Inventory.activeBinding
        inventorySha256 = [string] $Inventory.sha256
        expectedPostInventorySha256 = Get-PostInventorySha256 -Inventory $Inventory -Candidates $candidates
        candidateCount = $candidates.Count
        candidateBytes = $totalBytes
        candidates = @($candidates)
        requiredConfirmation = [string] $Contracts.retention.requiredConfirmation
        forbiddenActions = @(
            'service-restart', 'live-symcon-rpc', 'mqtt-publish', 'owner-mutation',
            'event-mutation', 'device-action', 'publication', 'retention-deletion'
        )
    }
    Write-AtomicJson -Path $ReviewPlanPath -Value $plan
    return $plan
}

function Assert-Plan {
    param(
        [Parameter(Mandatory = $true)] $Contracts,
        [Parameter(Mandatory = $true)] $Plan,
        [Parameter()][switch] $AllowExpired
    )
    if ($Plan.formatVersion -ne 1 -or
        [string] $Plan.purpose -cne 'saef-channel-v8-standalone-module-cross-root-retention' -or
        [string] $Plan.profile -cne [string] $Contracts.retention.profile -or
        [string] $Plan.targetId -cne $TargetId -or
        [string] $Plan.adapterProfile -cne [string] $Contracts.adapter.adapterProfile -or
        [string] $Plan.allowedOperation -cne 'quarantine' -or
        [string] $Plan.channelPolicySha256 -cne [string] $Contracts.channelPolicySha256 -or
        [string] $Plan.adapterPolicySha256 -cne [string] $Contracts.adapterPolicySha256 -or
        [string] $Plan.activePackageIdentitySha256 -cne
            [string] $Contracts.adapter.expectedActivePackageIdentitySha256 -or
        [string] $Plan.requiredConfirmation -cne [string] $Contracts.retention.requiredConfirmation -or
        -not (Test-HexSha256 -Value ([string] $Plan.inventorySha256)) -or
        -not (Test-HexSha256 -Value ([string] $Plan.expectedPostInventorySha256)) -or
        [int] $Plan.candidateCount -ne @($Plan.candidates).Count -or [int] $Plan.candidateCount -lt 1 -or
        [long] $Plan.candidateBytes -lt 0 -or
        [long] $Plan.candidateBytes -gt [long] $Contracts.retention.maximumPlanBytes) {
        throw [Security.SecurityException]::new('Review plan contract differs.')
    }
    $generated = ConvertFrom-RoundtripUtcTimestamp -Value ([string] $Plan.generatedAtUtc)
    $expires = ConvertFrom-RoundtripUtcTimestamp -Value ([string] $Plan.expiresAtUtc)
    if ($expires -le $generated -or
        ($expires - $generated).TotalSeconds -gt [int] $Contracts.retention.maximumPlanAgeSeconds -or
        (-not $AllowExpired -and [DateTimeOffset]::UtcNow -ge $expires)) {
        throw [Security.SecurityException]::new('Review plan lifetime is invalid or expired.')
    }
}

function Enter-Locks {
    param([Parameter(Mandatory = $true)] $Contracts)
    $script:channelMutex = [Threading.Mutex]::new($false, $ChannelMutexName)
    try { $script:channelMutexAcquired = $script:channelMutex.WaitOne(0) } catch [Threading.AbandonedMutexException] {
        $script:channelMutexAcquired = $true
        throw [InvalidOperationException]::new('Abandoned channel mutex requires manual recovery review.')
    }
    if (-not $script:channelMutexAcquired) {
        throw [TimeoutException]::new('Deployment channel mutex is busy.')
    }
    $script:adapterMutex = [Threading.Mutex]::new($false, [string] $Contracts.adapter.mutexName)
    try { $script:adapterMutexAcquired = $script:adapterMutex.WaitOne(0) } catch [Threading.AbandonedMutexException] {
        $script:adapterMutexAcquired = $true
        throw [InvalidOperationException]::new('Abandoned adapter mutex requires manual recovery review.')
    }
    if (-not $script:adapterMutexAcquired) {
        throw [TimeoutException]::new('Target adapter mutex is busy.')
    }
    if ($Contracts.adapter.PSObject.Properties.Name -notcontains 'runtimeStateRoots') { return }
    $rootProperties = @($Contracts.adapter.runtimeStateRoots.PSObject.Properties)
    $names = @(Get-OrdinalSortedStrings -Values @($rootProperties | ForEach-Object { $_.Name }))
    foreach ($name in $names) {
        $rootProperty = $Contracts.adapter.runtimeStateRoots.PSObject.Properties[$name]
        $lockProperty = $Contracts.adapter.runtimeLockFiles.PSObject.Properties[$name]
        if ($null -eq $rootProperty -or $null -eq $lockProperty) {
            throw [InvalidOperationException]::new('Writer-lock policy is incomplete.')
        }
        $root = [string] $rootProperty.Value
        Assert-PlainTree -Path $root
        Assert-ProtectedAcl -Path $root
        $lockPath = Join-Path $root ([string] $lockProperty.Value)
        Assert-RootedLeaf -Path $lockPath
        $stream = [IO.File]::Open($lockPath, [IO.FileMode]::Open, [IO.FileAccess]::ReadWrite,
            [IO.FileShare]::ReadWrite)
        try {
            $stream.Lock(0, 1)
            $script:writerLocks += $stream
        } catch {
            $stream.Dispose()
            throw [TimeoutException]::new('Target writer lock is busy.')
        }
    }
}

function Exit-Locks {
    for ($index = $script:writerLocks.Count - 1; $index -ge 0; $index--) {
        try { $script:writerLocks[$index].Unlock(0, 1) } catch { }
        $script:writerLocks[$index].Dispose()
    }
    $script:writerLocks = @()
    if ($script:adapterMutexAcquired -and $null -ne $script:adapterMutex) {
        $script:adapterMutex.ReleaseMutex()
    }
    if ($null -ne $script:adapterMutex) { $script:adapterMutex.Dispose() }
    if ($script:channelMutexAcquired -and $null -ne $script:channelMutex) {
        $script:channelMutex.ReleaseMutex()
    }
    if ($null -ne $script:channelMutex) { $script:channelMutex.Dispose() }
}

function Get-RoleRoot {
    param(
        [Parameter(Mandatory = $true)] $Contracts,
        [Parameter(Mandatory = $true)][string] $Role
    )
    switch ($Role) {
        'channel-state' { return [string] $Contracts.channel.stateRoot }
        'managed-fileset' { return [string] $Contracts.channel.managedFilesetRoot }
        'adapter-transaction' { return [string] $Contracts.adapter.adapterStateRoot }
        default { throw [InvalidOperationException]::new('Retention artifact role is unsupported.') }
    }
}

function Copy-VerifiedTree {
    param(
        [Parameter(Mandatory = $true)][string] $Source,
        [Parameter(Mandatory = $true)][string] $Destination,
        [Parameter(Mandatory = $true)][long] $MaximumBytes,
        [Parameter(Mandatory = $true)][string] $ExpectedSha256
    )
    Copy-Item -LiteralPath $Source -Destination $Destination -Recurse
    $identity = Get-TreeIdentity -Path $Destination -MaximumBytes $MaximumBytes
    if ([string] $identity.sha256 -cne $ExpectedSha256) {
        throw [InvalidOperationException]::new('Byte-exact backup verification failed.')
    }
    return $identity
}

function Write-Claim {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $Phase,
        [Parameter(Mandatory = $true)][string] $PlanSha256
    )
    $record = [ordered]@{
        formatVersion = 1
        targetId = $TargetId
        reviewPlanSha256 = $PlanSha256
        phase = $Phase
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        backupPath = $script:backupPath
        quarantinePath = $script:quarantinePath
    }
    Write-AtomicJson -Path $Path -Value $record
}

function New-Claim {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $PlanSha256
    )
    $record = [ordered]@{
        formatVersion = 1
        targetId = $TargetId
        reviewPlanSha256 = $PlanSha256
        phase = 'started'
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        backupPath = ''
        quarantinePath = ''
    }
    $text = ($record | ConvertTo-Json -Depth 8) + [Environment]::NewLine
    $stream = [IO.File]::Open($Path, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
    try {
        $bytes = [Text.UTF8Encoding]::new($false).GetBytes($text)
        try { $stream.Write($bytes, 0, $bytes.Length); $stream.Flush($true) } finally {
            [Array]::Clear($bytes, 0, $bytes.Length)
        }
    } finally { $stream.Dispose() }
    $script:claimCreated = $true
}

function Restore-MovedArtifacts {
    param(
        [Parameter(Mandatory = $true)] $Contracts,
        [Parameter(Mandatory = $true)][string] $ExpectedInventorySha256
    )
    $script:rollbackAttempted = $true
    try {
        for ($index = $script:movedArtifacts.Count - 1; $index -ge 0; $index--) {
            $record = $script:movedArtifacts[$index]
            if ((Test-Path -LiteralPath ([string] $record.source)) -or
                -not (Test-Path -LiteralPath ([string] $record.quarantine) -PathType Container)) {
                throw [InvalidOperationException]::new('Quarantine restore path is not exclusive.')
            }
            [IO.Directory]::Move([string] $record.quarantine, [string] $record.source)
        }
        $restored = Get-Inventory -Contracts $Contracts
        if ([string] $restored.sha256 -cne $ExpectedInventorySha256) {
            throw [InvalidOperationException]::new('Restored inventory identity differs.')
        }
        $script:rollbackSucceeded = $true
    } catch {
        $script:rollbackSucceeded = $false
        throw
    }
}

$exitCode = $ExitRejected
$outcome = 'failed'
$details = [ordered]@{}
try {
    if (-not (Test-SafeIdentifier -Value $TargetId)) {
        throw [InvalidOperationException]::new('Target identity is invalid.')
    }
    if (-not [IO.Path]::IsPathRooted($ReviewPlanPath) -or
        -not [IO.Path]::IsPathRooted($StatusPath)) {
        throw [InvalidOperationException]::new('Output paths must be absolute.')
    }
    $contracts = Read-Contracts
    if ($Operation -eq 'plan') {
        if (-not [string]::IsNullOrEmpty($ExpectedReviewPlanSha256) -or
            -not [string]::IsNullOrEmpty($Confirmation)) {
            throw [InvalidOperationException]::new('Plan mode accepts no apply authority.')
        }
        $script:failureCode = 'locks'
        Enter-Locks -Contracts $contracts
        $script:failureCode = 'inventory'
        $inventory = Get-Inventory -Contracts $contracts
        if (@($inventory.eligible).Count -eq 0) {
            $script:failureCode = 'none'
            $outcome = 'no_candidates'
            $exitCode = $ExitSuccess
            $details = [ordered]@{
                inventorySha256 = [string] $inventory.sha256
                candidateCount = 0
                protectedUnitCount = @($inventory.units).Count
            }
        } else {
            $script:failureCode = 'review_plan'
            $plan = New-ReviewPlan -Contracts $contracts -Inventory $inventory
            $script:failureCode = 'none'
            $outcome = 'planned'
            $exitCode = $ExitSuccess
            $details = [ordered]@{
                inventorySha256 = [string] $inventory.sha256
                reviewPlanSha256 = Get-Sha256 -Path $ReviewPlanPath
                reviewPlanExpiresAtUtc = [string] $plan.expiresAtUtc
                candidateCount = [int] $plan.candidateCount
                candidateBytes = [long] $plan.candidateBytes
            }
        }
    } else {
        Assert-RootedLeaf -Path $ReviewPlanPath
        $actualPlanSha256 = Get-Sha256 -Path $ReviewPlanPath
        if (-not (Test-HexSha256 -Value $ExpectedReviewPlanSha256) -or
            $actualPlanSha256 -cne $ExpectedReviewPlanSha256) {
            throw [Security.SecurityException]::new('Review plan hash differs.')
        }
        $plan = Read-BoundedJson -Path $ReviewPlanPath
        Assert-Plan -Contracts $contracts -Plan $plan -AllowExpired:($Operation -eq 'inspect')
        $script:claimPath = Join-Path ([string] $contracts.retention.claimRoot) ($actualPlanSha256 + '.json')
        $script:failureCode = 'locks'
        Enter-Locks -Contracts $contracts
        $script:failureCode = 'fresh_inventory'
        $inventory = Get-Inventory -Contracts $contracts
        if ($Operation -eq 'inspect') {
            Assert-RootedLeaf -Path $script:claimPath
            $claim = Read-BoundedJson -Path $script:claimPath
            if ($claim.formatVersion -ne 1 -or [string] $claim.targetId -cne $TargetId -or
                [string] $claim.reviewPlanSha256 -cne $actualPlanSha256 -or
                [string] $claim.phase -notin @(
                    'completed', 'rolled_back', 'manual_recovery', 'failed_before_operational_mutation'
                )) {
                throw [Security.SecurityException]::new('Retention claim is not terminal and bound.')
            }
            if ([string] $claim.phase -ceq 'completed' -and
                [string] $inventory.sha256 -ceq [string] $plan.expectedPostInventorySha256) {
                $outcome = 'quarantined'
            } elseif ([string] $claim.phase -ceq 'rolled_back' -and
                [string] $inventory.sha256 -ceq [string] $plan.inventorySha256) {
                $outcome = 'rolled_back'
            } elseif ([string] $claim.phase -ceq 'failed_before_operational_mutation' -and
                [string] $inventory.sha256 -ceq [string] $plan.inventorySha256) {
                $outcome = 'failed_before_operational_mutation'
            } else {
                throw [InvalidOperationException]::new('Terminal retention inventory differs.')
            }
            $script:failureCode = 'none'
            $exitCode = $ExitSuccess
            $details = [ordered]@{
                reviewPlanSha256 = $actualPlanSha256
                claimPhase = [string] $claim.phase
                inventorySha256 = [string] $inventory.sha256
            }
        } else {
            $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
            $principal = [Security.Principal.WindowsPrincipal]::new($identity)
            if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
                throw [UnauthorizedAccessException]::new('Retention apply requires an elevated local administrator.')
            }
            if ($Confirmation -cne [string] $contracts.retention.requiredConfirmation) {
                throw [Security.SecurityException]::new('Retention confirmation differs.')
            }
            if ((Test-Path -LiteralPath $script:claimPath) -or
                [string] $inventory.sha256 -cne [string] $plan.inventorySha256 -or
                [string] $inventory.activeBinding -cne [string] $plan.activeBinding) {
                throw [InvalidOperationException]::new('Retention plan is consumed or the inventory drifted.')
            }
            foreach ($candidate in @($plan.candidates)) {
                $matches = @($inventory.eligible | Where-Object {
                    [string] $_.deploymentId -ceq [string] $candidate.deploymentId -and
                    [string] $_.filesetName -ceq [string] $candidate.filesetName -and
                    [string] $_.transactionName -ceq [string] $candidate.transactionName -and
                    [string] $_.packageIdentitySha256 -ceq [string] $candidate.packageIdentitySha256
                })
                if ($matches.Count -ne 1) {
                    throw [InvalidOperationException]::new('Approved retention unit is no longer eligible.')
                }
                $actualArtifacts = @(Get-UnitArtifacts -Inventory $inventory -Unit $matches[0])
                $expectedArtifacts = @($candidate.artifacts)
                if ($actualArtifacts.Count -ne $expectedArtifacts.Count) {
                    throw [InvalidOperationException]::new('Approved artifact set differs.')
                }
                for ($index = 0; $index -lt $actualArtifacts.Count; $index++) {
                    $actual = $actualArtifacts[$index]
                    $expected = $expectedArtifacts[$index]
                    if ([string] $actual.role -cne [string] $expected.role -or
                        [string] $actual.name -cne [string] $expected.name -or
                        [long] $actual.bytes -ne [long] $expected.bytes -or
                        [int] $actual.entryCount -ne [int] $expected.entryCount -or
                        [string] $actual.sha256 -cne [string] $expected.sha256) {
                        throw [InvalidOperationException]::new('Approved artifact identity differs.')
                    }
                }
            }
            $script:failureCode = 'claim'
            New-Claim -Path $script:claimPath -PlanSha256 $actualPlanSha256
            $script:backupPath = Join-Path ([string] $contracts.retention.backupRoot) $actualPlanSha256
            $script:quarantinePath = Join-Path ([string] $contracts.retention.quarantineRoot) $actualPlanSha256
            if ((Test-Path -LiteralPath $script:backupPath) -or
                (Test-Path -LiteralPath $script:quarantinePath)) {
                throw [InvalidOperationException]::new('Retention backup or quarantine identity already exists.')
            }
            $script:failureCode = 'backup'
            [IO.Directory]::CreateDirectory($script:backupPath) | Out-Null
            [IO.Directory]::CreateDirectory($script:quarantinePath) | Out-Null
            $script:backupMutationAttempted = $true
            Protect-ChildAcl -Path $script:backupPath
            Protect-ChildAcl -Path $script:quarantinePath
            $backupEntries = @()
            foreach ($candidate in @($plan.candidates)) {
                foreach ($artifact in @($candidate.artifacts)) {
                    $sourceRoot = Get-RoleRoot -Contracts $contracts -Role ([string] $artifact.role)
                    $source = Get-ChildPath -Root $sourceRoot -Name ([string] $artifact.name)
                    $roleBackup = Join-Path $script:backupPath ([string] $artifact.role)
                    if (-not (Test-Path -LiteralPath $roleBackup)) {
                        [IO.Directory]::CreateDirectory($roleBackup) | Out-Null
                    }
                    $destination = Join-Path $roleBackup ([string] $artifact.name)
                    $verified = Copy-VerifiedTree -Source $source -Destination $destination `
                        -MaximumBytes ([long] $contracts.retention.maximumUnitBytes) `
                        -ExpectedSha256 ([string] $artifact.sha256)
                    $backupEntries += [ordered]@{
                        role = [string] $artifact.role
                        name = [string] $artifact.name
                        bytes = [long] $verified.bytes
                        entryCount = [int] $verified.entryCount
                        sha256 = [string] $verified.sha256
                    }
                }
            }
            Write-AtomicJson -Path (Join-Path $script:backupPath 'backup-manifest.local.json') -Value ([ordered]@{
                formatVersion = 1
                timestampUtc = [DateTime]::UtcNow.ToString('o')
                targetId = $TargetId
                reviewPlanSha256 = $actualPlanSha256
                preInventorySha256 = [string] $plan.inventorySha256
                expectedPostInventorySha256 = [string] $plan.expectedPostInventorySha256
                entries = @($backupEntries)
            })
            $script:failureCode = 'quarantine'
            $script:operationalMutationAttempted = $true
            foreach ($candidate in @($plan.candidates)) {
                foreach ($artifact in @($candidate.artifacts)) {
                    $sourceRoot = Get-RoleRoot -Contracts $contracts -Role ([string] $artifact.role)
                    $source = Get-ChildPath -Root $sourceRoot -Name ([string] $artifact.name)
                    $roleQuarantine = Join-Path $script:quarantinePath ([string] $artifact.role)
                    if (-not (Test-Path -LiteralPath $roleQuarantine)) {
                        [IO.Directory]::CreateDirectory($roleQuarantine) | Out-Null
                    }
                    $destination = Join-Path $roleQuarantine ([string] $artifact.name)
                    [IO.Directory]::Move($source, $destination)
                    $script:quarantineMutationAttempted = $true
                    $script:movedArtifacts += [ordered]@{
                        source = $source
                        quarantine = $destination
                    }
                }
            }
            $script:failureCode = 'postflight'
            $postInventory = Get-Inventory -Contracts $contracts
            if ([string] $postInventory.sha256 -cne [string] $plan.expectedPostInventorySha256) {
                throw [InvalidOperationException]::new('Post-quarantine inventory differs.')
            }
            Write-Claim -Path $script:claimPath -Phase 'completed' -PlanSha256 $actualPlanSha256
            $script:failureCode = 'none'
            $outcome = 'quarantined'
            $exitCode = $ExitSuccess
            $details = [ordered]@{
                reviewPlanSha256 = $actualPlanSha256
                backupManifestSha256 = Get-Sha256 -Path (Join-Path $script:backupPath 'backup-manifest.local.json')
                inventorySha256 = [string] $postInventory.sha256
                quarantinedUnitCount = @($plan.candidates).Count
            }
        }
    }
} catch {
    $details = [ordered]@{
        errorType = $_.Exception.GetType().FullName
        errorId = $_.FullyQualifiedErrorId
        errorMessage = $_.Exception.Message
    }
    if ($Operation -eq 'apply' -and $script:operationalMutationAttempted) {
        try {
            Restore-MovedArtifacts -Contracts $contracts -ExpectedInventorySha256 ([string] $plan.inventorySha256)
            if ($script:claimCreated) {
                Write-Claim -Path $script:claimPath -Phase 'rolled_back' -PlanSha256 $actualPlanSha256
            }
            $outcome = 'rolled_back'
            $exitCode = $ExitRolledBack
        } catch {
            if ($script:claimCreated) {
                try { Write-Claim -Path $script:claimPath -Phase 'manual_recovery' -PlanSha256 $actualPlanSha256 } catch { }
            }
            $outcome = 'manual_recovery_required'
            $exitCode = $ExitManualRecovery
            $details.rollbackErrorType = $_.Exception.GetType().FullName
            $details.rollbackErrorMessage = $_.Exception.Message
        }
    } elseif ($Operation -eq 'apply' -and $script:claimCreated) {
        try { Write-Claim -Path $script:claimPath -Phase 'failed_before_operational_mutation' `
            -PlanSha256 $actualPlanSha256 } catch { }
        $exitCode = $ExitFailedBeforeMutation
    } else {
        $exitCode = $ExitRejected
    }
} finally {
    Exit-Locks
    Write-Status -Outcome $outcome -ExitCode $exitCode -Details $details
}
exit $exitCode
