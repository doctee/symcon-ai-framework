[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'apply', 'inspect', 'rollback')]
    [string] $Operation,

    [Parameter(Mandatory = $true)]
    [string] $PolicyPath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedPolicySha256,

    [Parameter(Mandatory = $true)]
    [string] $ReviewPlanPath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath,

    [Parameter()]
    [ValidatePattern('^$|^[a-f0-9]{64}$')]
    [string] $ExpectedPlanSha256 = '',

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
$MaximumContractBytes = 1048576
$MaximumPlanBytes = 2097152
$ApplyConfirmation = 'migrate-saef-media-carousel-package-ownership'
$RollbackConfirmation = 'rollback-saef-media-carousel-package-ownership'
$MigrationProfile = 'saef-media-carousel-package-ownership-v1'
$TargetId = 'saef-media-carousel'

$script:implementationPath = [IO.Path]::GetFullPath([string] $MyInvocation.MyCommand.Path)
$script:policy = $null
$script:transaction = $null
$script:manifest = $null
$script:plan = $null
$script:credential = $null
$script:policySha256 = ''
$script:transactionSha256 = ''
$script:manifestSha256 = ''
$script:implementationSha256 = ''
$script:planSha256 = ''
$script:sourceIdentity = $null
$script:candidateIdentity = $null
$script:snapshot = $null
$script:channelMutex = $null
$script:adapterMutex = $null
$script:channelMutexAcquired = $false
$script:adapterMutexAcquired = $false
$script:claimPath = ''
$script:transactionRoot = ''
$script:rollbackPath = ''
$script:candidateStagingPath = ''
$script:failedCandidatePath = ''
$script:claimCreated = $false
$script:transactionPrepared = $false
$script:sourceMoved = $false
$script:candidateActivated = $false
$script:reloadAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:productionMutationAttempted = $false
$script:privateEvidenceMutationAttempted = $false
$script:failureCode = 'initialization'
$script:finalOutcome = 'failed'
$script:finalExitCode = $ExitManualRecovery
$script:inspectionState = ''

function Test-HexSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -cmatch '^[a-f0-9]{64}$'
}

function Test-GitObjectId {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -cmatch '^(?:[a-f0-9]{40}|[a-f0-9]{64})$'
}

function Test-SafeGitBranch {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -cmatch '^[A-Za-z0-9._/-]{1,128}$' -and
        -not $Value.StartsWith('/') -and
        -not $Value.EndsWith('/') -and
        -not $Value.Contains('//') -and
        -not $Value.Contains('..')
}

function Test-SymconGuid {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -match '^\{[A-Fa-f0-9]{8}(?:-[A-Fa-f0-9]{4}){3}-[A-Fa-f0-9]{12}\}$'
}

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

function Get-TextSha256 {
    param([Parameter(Mandatory = $true)][string] $Text)

    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    try {
        return Get-BytesSha256 -Bytes $bytes
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Assert-ExactProperties {
    param(
        [Parameter(Mandatory = $true)] $Value,
        [Parameter(Mandatory = $true)][string[]] $Expected,
        [Parameter(Mandatory = $true)][string] $Label
    )

    [string[]] $actual = @($Value.PSObject.Properties | ForEach-Object { [string] $_.Name })
    [string[]] $wanted = @($Expected)
    [Array]::Sort($actual, [StringComparer]::Ordinal)
    [Array]::Sort($wanted, [StringComparer]::Ordinal)
    if ($actual.Count -ne $wanted.Count) {
        throw [InvalidOperationException]::new($Label + ' fields are invalid.')
    }
    for ($index = 0; $index -lt $wanted.Count; $index++) {
        if ($actual[$index] -cne $wanted[$index]) {
            throw [InvalidOperationException]::new($Label + ' fields are invalid.')
        }
    }
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
    if ([long] $item.Length -lt 1 -or [long] $item.Length -gt $MaximumBytes -or
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

function Assert-PathsDoNotOverlap {
    param(
        [Parameter(Mandatory = $true)][string] $Left,
        [Parameter(Mandatory = $true)][string] $Right,
        [Parameter(Mandatory = $true)][string] $Label
    )

    if ((Test-PathContains -Parent $Left -Candidate $Right) -or
        (Test-PathContains -Parent $Right -Candidate $Left)) {
        throw [InvalidOperationException]::new($Label + ' paths overlap.')
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
    $temporary = Join-Path $directory ('.saef-media-carousel-ownership-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-media-carousel-ownership-' + $token + '.bak')
    try {
        [IO.File]::WriteAllBytes($temporary, $Bytes)
        if (Test-Path -LiteralPath $Path -PathType Leaf) {
            [IO.File]::Replace($temporary, $Path, $backup)
        } else {
            [IO.File]::Move($temporary, $Path)
        }
    } finally {
        if (Test-Path -LiteralPath $temporary -PathType Leaf) {
            Remove-Item -LiteralPath $temporary -Force -ErrorAction SilentlyContinue
        }
        if (Test-Path -LiteralPath $backup -PathType Leaf) {
            Remove-Item -LiteralPath $backup -Force -ErrorAction SilentlyContinue
        }
    }
}

function ConvertTo-Utf8JsonBytes {
    param(
        [Parameter(Mandatory = $true)] $Value,
        [Parameter()][int] $Depth = 12
    )

    $text = ($Value | ConvertTo-Json -Depth $Depth) + [Environment]::NewLine
    return [Text.UTF8Encoding]::new($false).GetBytes($text)
}

function Write-AtomicJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value,
        [Parameter()][int] $Depth = 12
    )

    $bytes = ConvertTo-Utf8JsonBytes -Value $Value -Depth $Depth
    try {
        Write-AtomicBytes -Path $Path -Bytes $bytes
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Write-MigrationStatus {
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'media_carousel_package_ownership_migration'
        operation = $Operation
        outcome = $script:finalOutcome
        exitCode = $script:finalExitCode
        failureCode = $script:failureCode
        inspectionState = $script:inspectionState
        policySha256 = $script:policySha256
        transactionContractSha256 = $script:transactionSha256
        implementationSha256 = $script:implementationSha256
        planSha256 = $script:planSha256
        sourceTreeSha256 = if ($null -eq $script:sourceIdentity) { '' } else {
            [string] $script:sourceIdentity.treeSha256
        }
        sourceAclSha256 = if ($null -eq $script:sourceIdentity) { '' } else {
            [string] $script:sourceIdentity.aclSha256
        }
        candidatePackageIdentitySha256 = if ($null -eq $script:candidateIdentity) { '' } else {
            [string] $script:candidateIdentity.packageIdentitySha256
        }
        instanceSnapshotSha256 = if ($null -eq $script:snapshot) { '' } else {
            [string] $script:snapshot.identitySha256
        }
        claimCreated = [bool] $script:claimCreated
        transactionPrepared = [bool] $script:transactionPrepared
        sourceMoved = [bool] $script:sourceMoved
        candidateActivated = [bool] $script:candidateActivated
        reloadAttempted = [bool] $script:reloadAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        privateEvidenceMutationAttempted = [bool] $script:privateEvidenceMutationAttempted
        productionMutationAttempted = [bool] $script:productionMutationAttempted
        serviceRestartAttempted = $false
        providerContactAttempted = $false
        publicationAttempted = $false
        retentionCleanupAttempted = $false
    }
    Write-AtomicJson -Path $StatusPath -Value $status
}

function Get-TreeIdentity {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][long] $MaximumBytes,
        [Parameter(Mandatory = $true)][int] $MaximumEntries
    )

    Assert-PlainDirectory -Path $Root
    $rootFull = [IO.Path]::GetFullPath($Root).TrimEnd([char[]] @('\', '/'))
    $records = New-Object Collections.Generic.List[string]
    $fileCount = 0
    $directoryCount = 0
    $totalBytes = 0L
    foreach ($entry in @(Get-ChildItem -LiteralPath $Root -Force -Recurse)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [IO.IOException]::new('Directory tree contains a reparse point.')
        }
        $relative = $entry.FullName.Substring($rootFull.Length + 1).Replace('\', '/')
        if ($entry.PSIsContainer) {
            $directoryCount++
            $records.Add('D' + [char] 0 + $relative)
        } else {
            $fileCount++
            $totalBytes += [long] $entry.Length
            if ($totalBytes -gt $MaximumBytes) {
                throw [IO.IOException]::new('Directory tree exceeds its byte bound.')
            }
            $records.Add(
                'F' + [char] 0 + $relative + [char] 0 + [long] $entry.Length + [char] 0 +
                (Get-Sha256 -Path $entry.FullName)
            )
        }
        if ($fileCount + $directoryCount -gt $MaximumEntries) {
            throw [IO.IOException]::new('Directory tree exceeds its entry bound.')
        }
    }
    if ($fileCount -lt 1) {
        throw [InvalidOperationException]::new('Directory tree is empty.')
    }
    [string[]] $ordered = @($records)
    [Array]::Sort($ordered, [StringComparer]::Ordinal)
    return [ordered]@{
        sha256 = Get-TextSha256 -Text ([string]::Join("`n", $ordered) + "`n")
        fileCount = $fileCount
        directoryCount = $directoryCount
        bytes = $totalBytes
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

function Get-AclTreeIdentity {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][int] $MaximumEntries
    )

    Assert-PlainDirectory -Path $Root
    $rootFull = [IO.Path]::GetFullPath($Root).TrimEnd([char[]] @('\', '/'))
    $paths = New-Object Collections.Generic.List[string]
    $paths.Add($rootFull)
    foreach ($entry in @(Get-ChildItem -LiteralPath $Root -Force -Recurse)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [IO.IOException]::new('ACL inventory contains a reparse point.')
        }
        $paths.Add($entry.FullName)
        if ($paths.Count -gt ($MaximumEntries + 1)) {
            throw [IO.IOException]::new('ACL inventory exceeds its entry bound.')
        }
    }
    $records = New-Object Collections.Generic.List[string]
    foreach ($path in $paths) {
        $relative = if ($path -ceq $rootFull) { '.' } else {
            $path.Substring($rootFull.Length + 1).Replace('\', '/')
        }
        $acl = Get-Acl -LiteralPath $path
        $owner = $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value
        $records.Add(
            'P' + [char] 0 + $relative + [char] 0 + $owner + [char] 0 +
            ([int] [bool] $acl.AreAccessRulesProtected)
        )
        foreach ($rule in @($acl.GetAccessRules(
            $true,
            $true,
            [Security.Principal.SecurityIdentifier]
        ))) {
            $sid = [string] $rule.IdentityReference.Value
            if ($rule.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
                $sid -in @('S-1-1-0', 'S-1-5-11', 'S-1-5-32-545') -and
                (Test-BroadWriteAccess -Rights $rule.FileSystemRights)) {
                throw [Security.SecurityException]::new('Directory tree grants broad write access.')
            }
            $records.Add(
                'A' + [char] 0 + $relative + [char] 0 + $sid + [char] 0 +
                [int] $rule.FileSystemRights + [char] 0 + [int] $rule.AccessControlType + [char] 0 +
                [int] $rule.InheritanceFlags + [char] 0 + [int] $rule.PropagationFlags + [char] 0 +
                ([int] [bool] $rule.IsInherited)
            )
        }
    }
    [string[]] $ordered = @($records)
    [Array]::Sort($ordered, [StringComparer]::Ordinal)
    return Get-TextSha256 -Text ([string]::Join("`n", $ordered) + "`n")
}

function Set-ManagedTreeAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][Security.Principal.SecurityIdentifier] $DeploymentSid
    )

    Assert-PlainDirectory -Path $Root
    $systemSid = [Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $administratorsSid = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    $inheritance = [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor
        [Security.AccessControl.InheritanceFlags]::ObjectInherit
    $acl = [Security.AccessControl.DirectorySecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetOwner($administratorsSid)
    foreach ($sid in @($systemSid, $administratorsSid, $DeploymentSid)) {
        $rule = [Security.AccessControl.FileSystemAccessRule]::new(
            $sid,
            [Security.AccessControl.FileSystemRights]::FullControl,
            $inheritance,
            [Security.AccessControl.PropagationFlags]::None,
            [Security.AccessControl.AccessControlType]::Allow
        )
        $null = $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Root -AclObject $acl
}

function Assert-ManagedRootAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][string] $DeploymentSid
    )

    $acl = Get-Acl -LiteralPath $Root
    if (-not $acl.AreAccessRulesProtected) {
        throw [Security.SecurityException]::new('Managed root ACL inherits from its parent.')
    }
    $required = @('S-1-5-18', 'S-1-5-32-544', $DeploymentSid)
    $full = @{}
    foreach ($rule in @($acl.GetAccessRules($true, $false, [Security.Principal.SecurityIdentifier]))) {
        $sid = [string] $rule.IdentityReference.Value
        if ($rule.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow -or
            $sid -notin $required) {
            throw [Security.SecurityException]::new('Managed root ACL contains an unexpected rule.')
        }
        if (($rule.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) -eq
            [Security.AccessControl.FileSystemRights]::FullControl -and
            ($rule.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ContainerInherit) -ne 0 -and
            ($rule.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ObjectInherit) -ne 0) {
            $full[$sid] = $true
        }
    }
    foreach ($sid in $required) {
        if (-not $full.ContainsKey($sid)) {
            throw [Security.SecurityException]::new('Managed root ACL lacks a required principal.')
        }
    }
}

function Get-GitCheckoutIdentity {
    param([Parameter(Mandatory = $true)][string] $Root)

    $gitRoot = Join-Path $Root '.git'
    Assert-PlainDirectory -Path $gitRoot
    $headPath = Join-Path $gitRoot 'HEAD'
    $configPath = Join-Path $gitRoot 'config'
    Assert-RootedLeaf -Path $headPath -MaximumBytes 4096
    Assert-RootedLeaf -Path $configPath -MaximumBytes 131072
    $head = (Get-Content -LiteralPath $headPath -Raw).Trim()
    if ($head -cne ('ref: refs/heads/' + [string] $script:policy.expectedSourceBranch)) {
        throw [InvalidOperationException]::new('Source checkout branch differs from policy.')
    }
    $reference = $head.Substring(5)
    $looseReferencePath = Join-Path $gitRoot $reference.Replace('/', [IO.Path]::DirectorySeparatorChar)
    $commits = New-Object Collections.Generic.List[string]
    if (Test-Path -LiteralPath $looseReferencePath -PathType Leaf) {
        Assert-RootedLeaf -Path $looseReferencePath -MaximumBytes 4096
        $commits.Add((Get-Content -LiteralPath $looseReferencePath -Raw).Trim())
    } else {
        $packedReferencesPath = Join-Path $gitRoot 'packed-refs'
        Assert-RootedLeaf -Path $packedReferencesPath -MaximumBytes 1048576
        foreach ($line in @(Get-Content -LiteralPath $packedReferencesPath)) {
            if ($line -cmatch '^((?:[a-f0-9]{40}|[a-f0-9]{64})) (refs/heads/[A-Za-z0-9._/-]+)$' -and
                $Matches[2] -ceq $reference) {
                $commits.Add($Matches[1])
            }
        }
    }
    if ($commits.Count -ne 1 -or $commits[0] -cne [string] $script:policy.expectedSourceCommit) {
        throw [InvalidOperationException]::new('Source checkout commit differs from policy.')
    }
    $insideOrigin = $false
    $originUrls = New-Object Collections.Generic.List[string]
    foreach ($line in @(Get-Content -LiteralPath $configPath)) {
        if ($line -match '^\s*\[([^]]+)\]\s*$') {
            $insideOrigin = $Matches[1] -ceq 'remote "origin"'
            continue
        }
        if ($insideOrigin -and $line -match '^\s*url\s*=\s*(\S.*?)\s*$') {
            $originUrls.Add($Matches[1])
        }
    }
    if ($originUrls.Count -ne 1 -or
        $originUrls[0] -cne [string] $script:policy.expectedSourceRepositoryUrl) {
        throw [InvalidOperationException]::new('Source checkout repository differs from policy.')
    }
    return [ordered]@{
        repositoryUrl = $originUrls[0]
        branch = [string] $script:policy.expectedSourceBranch
        commit = $commits[0]
    }
}

function Assert-ModuleMetadata {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][bool] $RepositoryMetadataRequired
    )

    Assert-PlainDirectory -Path $Root
    $gitPath = Join-Path $Root '.git'
    if ($RepositoryMetadataRequired -and -not (Test-Path -LiteralPath $gitPath -PathType Container)) {
        throw [InvalidOperationException]::new('Source checkout lacks repository metadata.')
    }
    if (-not $RepositoryMetadataRequired -and (Test-Path -LiteralPath $gitPath)) {
        throw [InvalidOperationException]::new('Package candidate contains repository metadata.')
    }
    $libraryPath = Join-Path $Root 'library.json'
    $modulePath = Join-Path $Root 'MediaCarousel\module.json'
    Assert-RootedLeaf -Path $libraryPath -MaximumBytes 1048576
    Assert-RootedLeaf -Path $modulePath -MaximumBytes 1048576
    $library = Get-Content -LiteralPath $libraryPath -Raw | ConvertFrom-Json
    $module = Get-Content -LiteralPath $modulePath -Raw | ConvertFrom-Json
    if ([string] $library.id -cne [string] $script:policy.libraryGuid -or
        [string] $library.name -cne [string] $script:policy.libraryName -or
        [string] $library.url -cne [string] $script:policy.libraryUrl -or
        [string] $module.id -cne [string] $script:policy.moduleGuid -or
        [string] $module.name -cne [string] $script:policy.moduleName -or
        [int] $module.type -ne [int] $script:policy.moduleType -or
        [string] $module.prefix -cne [string] $script:policy.modulePrefix) {
        throw [InvalidOperationException]::new('MediaCarousel module metadata differs from policy.')
    }
}

function Get-SourceIdentity {
    param([Parameter(Mandatory = $true)][string] $Root)

    Assert-ModuleMetadata -Root $Root -RepositoryMetadataRequired $true
    $git = Get-GitCheckoutIdentity -Root $Root
    $tree = Get-TreeIdentity `
        -Root $Root `
        -MaximumBytes ([long] $script:policy.maximumSourceBytes) `
        -MaximumEntries ([int] $script:policy.maximumSourceEntries)
    $aclSha256 = Get-AclTreeIdentity `
        -Root $Root `
        -MaximumEntries ([int] $script:policy.maximumSourceEntries)
    if ([string] $tree.sha256 -cne [string] $script:policy.expectedSourceTreeSha256 -or
        $aclSha256 -cne [string] $script:policy.expectedSourceAclSha256) {
        throw [InvalidOperationException]::new('Source checkout bytes or ACLs differ from policy.')
    }
    return [ordered]@{
        treeSha256 = [string] $tree.sha256
        aclSha256 = $aclSha256
        fileCount = [int] $tree.fileCount
        directoryCount = [int] $tree.directoryCount
        bytes = [long] $tree.bytes
        repositoryUrl = [string] $git.repositoryUrl
        branch = [string] $git.branch
        commit = [string] $git.commit
    }
}

function Read-CandidateManifest {
    Assert-RootedLeaf `
        -Path ([string] $script:policy.candidateManifestPath) `
        -MaximumBytes $MaximumContractBytes
    $script:manifestSha256 = Get-Sha256 -Path ([string] $script:policy.candidateManifestPath)
    if ($script:manifestSha256 -cne [string] $script:policy.expectedCandidateManifestSha256) {
        throw [InvalidOperationException]::new('Candidate manifest hash differs from policy.')
    }
    $script:manifest = Get-Content -LiteralPath ([string] $script:policy.candidateManifestPath) -Raw |
        ConvertFrom-Json
    if ($script:manifest.formatVersion -ne 1 -or
        [string] $script:manifest.deploymentKind -cne 'standalone-module' -or
        [string] $script:manifest.module.targetId -cne $TargetId -or
        [string] $script:manifest.module.libraryGuid -cne [string] $script:policy.libraryGuid -or
        [string] $script:manifest.module.packageIdentitySha256 -cne
            [string] $script:policy.expectedCandidatePackageIdentitySha256 -or
        [string] $script:manifest.module.transactionContractSha256 -cne
            [string] $script:policy.expectedTransactionContractSha256 -or
        @($script:manifest.files).Count -lt 1 -or
        @($script:manifest.files).Count -gt [int] $script:policy.maximumCandidateEntries) {
        throw [InvalidOperationException]::new('Candidate manifest contract is invalid.')
    }
}

function Get-CandidateIdentity {
    param([Parameter(Mandatory = $true)][string] $Root)

    Assert-ModuleMetadata -Root $Root -RepositoryMetadataRequired $false
    $candidateEntries = @(Get-ChildItem -LiteralPath $Root -Recurse -Force)
    if ($candidateEntries.Count -gt [int] $script:policy.maximumCandidateEntries) {
        throw [InvalidOperationException]::new('Candidate entry inventory exceeds its bound.')
    }
    foreach ($entry in $candidateEntries) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [IO.IOException]::new('Candidate contains a reparse point.')
        }
    }
    $actualFiles = @($candidateEntries | Where-Object { -not $_.PSIsContainer })
    $expectedFiles = @($script:manifest.files)
    if ($actualFiles.Count -ne $expectedFiles.Count) {
        throw [InvalidOperationException]::new('Candidate file inventory differs from its manifest.')
    }
    $seen = @{}
    $identity = [Text.StringBuilder]::new()
    $totalBytes = 0L
    foreach ($file in $expectedFiles) {
        $manifestPath = [string] $file.path
        if (-not $manifestPath.StartsWith('module/') -or $manifestPath.Contains('\') -or
            $manifestPath -match '(?:^|/)\.\.?($|/)' -or $manifestPath.Contains('//') -or
            $manifestPath.EndsWith('/')) {
            throw [InvalidOperationException]::new('Candidate manifest path is unsafe.')
        }
        $relative = $manifestPath.Substring(7)
        $absolute = [IO.Path]::GetFullPath((Join-Path $Root $relative))
        $rootPrefix = [IO.Path]::GetFullPath($Root).TrimEnd([char[]] @('\', '/')) +
            [IO.Path]::DirectorySeparatorChar
        if (-not $absolute.StartsWith($rootPrefix, [StringComparison]::OrdinalIgnoreCase) -or
            $seen.ContainsKey($relative) -or
            -not (Test-Path -LiteralPath $absolute -PathType Leaf) -or
            [long] (Get-Item -LiteralPath $absolute).Length -ne [long] $file.size -or
            (Get-Sha256 -Path $absolute) -cne [string] $file.sha256) {
            throw [InvalidOperationException]::new('Candidate file identity differs from its manifest.')
        }
        $seen[$relative] = $true
        $totalBytes += [long] $file.size
        if ($totalBytes -gt [long] $script:policy.maximumCandidateBytes) {
            throw [InvalidOperationException]::new('Candidate exceeds its byte bound.')
        }
        $null = $identity.Append($relative).Append([char] 0).Append([long] $file.size).Append(
            [char] 0
        ).Append([string] $file.sha256).Append("`n")
    }
    $packageIdentity = Get-TextSha256 -Text $identity.ToString()
    if ($packageIdentity -cne [string] $script:policy.expectedCandidatePackageIdentitySha256) {
        throw [InvalidOperationException]::new('Candidate package identity differs from policy.')
    }
    return [ordered]@{
        packageIdentitySha256 = $packageIdentity
        manifestSha256 = $script:manifestSha256
        fileCount = $expectedFiles.Count
        bytes = $totalBytes
    }
}

function Copy-CandidateToStaging {
    param(
        [Parameter(Mandatory = $true)][string] $Destination,
        [Parameter(Mandatory = $true)][Security.Principal.SecurityIdentifier] $DeploymentSid
    )

    if (Test-Path -LiteralPath $Destination) {
        throw [InvalidOperationException]::new('Candidate staging path already exists.')
    }
    [IO.Directory]::CreateDirectory($Destination) | Out-Null
    Set-ManagedTreeAcl -Root $Destination -DeploymentSid $DeploymentSid
    $candidateRoot = [IO.Path]::GetFullPath(
        [string] $script:policy.candidateModulePath
    ).TrimEnd([char[]] @('\', '/'))
    foreach ($source in @(Get-ChildItem -LiteralPath $candidateRoot -File -Recurse -Force)) {
        $relative = $source.FullName.Substring($candidateRoot.Length + 1)
        $target = Join-Path $Destination $relative
        $targetParent = Split-Path -Parent $target
        [IO.Directory]::CreateDirectory($targetParent) | Out-Null
        [IO.File]::Copy($source.FullName, $target, $false)
    }
    Assert-ManagedRootAcl -Root $Destination -DeploymentSid ([string] $DeploymentSid.Value)
    $null = Get-CandidateIdentity -Root $Destination
}

function Import-MachineCredential {
    param([Parameter(Mandatory = $true)][string] $Path)

    Add-Type -AssemblyName System.Security -ErrorAction Stop
    Assert-RootedLeaf -Path $Path -MaximumBytes 131072
    $record = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($record.formatVersion -ne 1 -or $record.protectionScope -cne 'LocalMachine' -or
        [string]::IsNullOrWhiteSpace([string] $record.username) -or
        [string]::IsNullOrWhiteSpace([string] $record.protectedPasswordBase64)) {
        throw [InvalidOperationException]::new('Credential file contract is invalid.')
    }
    $entropy = [Text.Encoding]::UTF8.GetBytes('SAEF.DeploymentChannel.RpcCredential.v1')
    $protected = $null
    $clear = $null
    $password = $null
    try {
        $protected = [Convert]::FromBase64String([string] $record.protectedPasswordBase64)
        $clear = [Security.Cryptography.ProtectedData]::Unprotect(
            $protected,
            $entropy,
            [Security.Cryptography.DataProtectionScope]::LocalMachine
        )
        $password = [Text.Encoding]::UTF8.GetString($clear)
        if ([string]::IsNullOrEmpty($password)) {
            throw [InvalidOperationException]::new('Credential password is empty.')
        }
        return [PSCredential]::new(
            [string] $record.username,
            (ConvertTo-SecureString -String $password -AsPlainText -Force)
        )
    } finally {
        if ($null -ne $clear) { [Array]::Clear($clear, 0, $clear.Length) }
        if ($null -ne $protected) { [Array]::Clear($protected, 0, $protected.Length) }
        [Array]::Clear($entropy, 0, $entropy.Length)
        $password = $null
    }
}

function Invoke-SymconRpc {
    param(
        [Parameter(Mandatory = $true)][string] $Method,
        [Parameter()][object[]] $Parameters = @()
    )

    $body = [ordered]@{ jsonrpc = '2.0'; method = $Method; params = $Parameters; id = 1 } |
        ConvertTo-Json -Depth 10 -Compress
    $networkCredential = $script:credential.GetNetworkCredential()
    $authorization = [Text.Encoding]::UTF8.GetBytes(
        $script:credential.UserName + ':' + $networkCredential.Password
    )
    try {
        $response = Invoke-RestMethod -Uri ([Uri] [string] $script:policy.rpcUri) -Method Post `
            -ContentType 'application/json' -Body $body `
            -TimeoutSec ([int] $script:policy.rpcTimeoutSeconds) -Headers @{
                Authorization = 'Basic ' + [Convert]::ToBase64String($authorization)
            }
    } finally {
        [Array]::Clear($authorization, 0, $authorization.Length)
        $networkCredential = $null
    }
    if (($response.PSObject.Properties.Name -contains 'error' -and $null -ne $response.error) -or
        $response.PSObject.Properties.Name -notcontains 'result') {
        throw [InvalidOperationException]::new('Symcon RPC request failed.')
    }
    return $response.result
}

function Get-ReferenceIdentity {
    param([Parameter(Mandatory = $true)][int] $InstanceId)

    [int[]] $references = @(Invoke-SymconRpc -Method 'IPS_GetReferenceList' -Parameters @($InstanceId) |
        ForEach-Object { [int] $_ })
    [Array]::Sort($references)
    $seen = @{}
    $identity = [Text.StringBuilder]::new()
    foreach ($referenceId in $references) {
        if ($referenceId -le 0 -or $seen.ContainsKey($referenceId) -or
            -not [bool] (Invoke-SymconRpc -Method 'IPS_ObjectExists' -Parameters @($referenceId))) {
            throw [InvalidOperationException]::new('MediaCarousel reference identity is invalid.')
        }
        $seen[$referenceId] = $true
        $null = $identity.Append($referenceId).Append("`n")
    }
    return [ordered]@{
        count = $references.Count
        sha256 = Get-TextSha256 -Text $identity.ToString()
        ids = @($references)
    }
}

function Get-InstanceSnapshot {
    $instanceIds = @(Invoke-SymconRpc -Method 'IPS_GetInstanceListByModuleID' -Parameters @(
        [string] $script:policy.moduleGuid
    ))
    $expectedInstances = @($script:policy.expectedInstances)
    if ($instanceIds.Count -ne $expectedInstances.Count -or
        $instanceIds.Count -lt 1 -or
        $instanceIds.Count -gt [int] $script:policy.maximumInstanceCount) {
        throw [InvalidOperationException]::new('MediaCarousel instance inventory differs from policy.')
    }
    $expectedById = @{}
    foreach ($expected in $expectedInstances) {
        $expectedById[[int] $expected.instanceId] = $expected
    }
    [int[]] $orderedIds = @($instanceIds | ForEach-Object { [int] $_ })
    [Array]::Sort($orderedIds)
    $records = @()
    $totalReferenceCount = 0
    $identity = [Text.StringBuilder]::new()
    foreach ($instanceId in $orderedIds) {
        if ($instanceId -le 0 -or -not $expectedById.ContainsKey($instanceId) -or
            -not [bool] (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @($instanceId))) {
            throw [InvalidOperationException]::new('MediaCarousel instance identity is invalid.')
        }
        $expected = $expectedById[$instanceId]
        $instance = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @($instanceId)
        $object = Invoke-SymconRpc -Method 'IPS_GetObject' -Parameters @($instanceId)
        $configuration = [string] (Invoke-SymconRpc -Method 'IPS_GetConfiguration' -Parameters @($instanceId))
        $configurationSha256 = Get-TextSha256 -Text $configuration
        $references = Get-ReferenceIdentity -InstanceId $instanceId
        if ([string] $instance.ModuleInfo.ModuleID -cne [string] $script:policy.moduleGuid -or
            [int] $object.ObjectType -ne 1 -or
            [int] $instance.InstanceStatus -notin @($script:policy.allowedInstanceStatuses) -or
            [bool] (Invoke-SymconRpc -Method 'IPS_HasChanges' -Parameters @($instanceId)) -or
            $configurationSha256 -cne [string] $expected.configurationSha256 -or
            [int] $references.count -ne [int] $expected.referenceCount -or
            [string] $references.sha256 -cne [string] $expected.referencesSha256) {
            throw [InvalidOperationException]::new('MediaCarousel instance baseline differs from policy.')
        }
        $configurationBytes = [Text.UTF8Encoding]::new($false).GetBytes($configuration)
        try {
            $record = [ordered]@{
                instanceId = $instanceId
                configurationBase64 = [Convert]::ToBase64String($configurationBytes)
                configurationSha256 = $configurationSha256
                references = @($references.ids)
                referencesSha256 = [string] $references.sha256
                objectIdent = [string] $object.ObjectIdent
                objectName = [string] $object.ObjectName
                parentId = if ($object.PSObject.Properties.Name -contains 'ParentID') {
                    [int] $object.ParentID
                } else {
                    [int] $object.ObjectParentID
                }
                position = [int] $object.ObjectPosition
                hidden = [bool] $object.ObjectIsHidden
                disabled = [bool] $object.ObjectIsDisabled
                readOnly = [bool] $object.ObjectIsReadOnly
                status = [int] $instance.InstanceStatus
            }
        } finally {
            [Array]::Clear($configurationBytes, 0, $configurationBytes.Length)
        }
        $records += $record
        $totalReferenceCount += [int] $references.count
        $null = $identity.Append([string] $record.instanceId).Append([char] 0)
        $null = $identity.Append([string] $record.configurationSha256).Append([char] 0)
        $null = $identity.Append([string] $record.referencesSha256).Append([char] 0)
        $null = $identity.Append([string] $record.objectIdent).Append([char] 0)
        $null = $identity.Append([string] $record.objectName).Append([char] 0)
        $null = $identity.Append([string] $record.parentId).Append([char] 0)
        $null = $identity.Append([string] $record.position).Append([char] 0)
        $null = $identity.Append([string] $record.hidden).Append([char] 0)
        $null = $identity.Append([string] $record.disabled).Append([char] 0)
        $null = $identity.Append([string] $record.readOnly).Append([char] 0)
        $null = $identity.Append([string] $record.status).Append([char] 0)
        $null = $identity.Append("`n")
    }
    $snapshot = [ordered]@{
        formatVersion = 1
        identitySha256 = Get-TextSha256 -Text $identity.ToString()
        instanceCount = $records.Count
        referenceCount = $totalReferenceCount
        instances = @($records)
    }
    $snapshotBytes = ConvertTo-Utf8JsonBytes -Value $snapshot -Depth 12
    try {
        if ($snapshotBytes.Length -gt [int] $script:policy.maximumStateBytes) {
            throw [InvalidOperationException]::new('MediaCarousel snapshot exceeds its byte bound.')
        }
    } finally {
        [Array]::Clear($snapshotBytes, 0, $snapshotBytes.Length)
    }
    return $snapshot
}

function Assert-SnapshotPreserved {
    param([Parameter(Mandatory = $true)] $Snapshot)

    $current = Get-InstanceSnapshot
    if ([string] $current.identitySha256 -cne [string] $Snapshot.identitySha256 -or
        [int] $current.instanceCount -ne [int] $Snapshot.instanceCount -or
        [int] $current.referenceCount -ne [int] $Snapshot.referenceCount) {
        throw [InvalidOperationException]::new('MediaCarousel runtime state changed.')
    }
}

function Assert-LoaderUniqueness {
    param([Parameter(Mandatory = $true)][string] $ExpectedPath)

    Assert-PlainDirectory -Path ([string] $script:policy.loaderRoot)
    $directories = @(Get-ChildItem -LiteralPath ([string] $script:policy.loaderRoot) -Directory -Force)
    if ($directories.Count -lt 1 -or $directories.Count -gt [int] $script:policy.maximumLoaderDirectoryCount) {
        throw [InvalidOperationException]::new('Module loader directory count is outside its bound.')
    }
    $matches = New-Object Collections.Generic.List[string]
    foreach ($directory in $directories) {
        if (($directory.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [IO.IOException]::new('Module loader contains a reparse point.')
        }
        $libraryPath = Join-Path $directory.FullName 'library.json'
        if (-not (Test-Path -LiteralPath $libraryPath -PathType Leaf)) {
            continue
        }
        Assert-RootedLeaf -Path $libraryPath -MaximumBytes 1048576
        $library = Get-Content -LiteralPath $libraryPath -Raw | ConvertFrom-Json
        $modulePath = Join-Path $directory.FullName 'MediaCarousel\module.json'
        $libraryMatches = [string] $library.id -ceq [string] $script:policy.libraryGuid
        $moduleMatches = $false
        if (Test-Path -LiteralPath $modulePath -PathType Leaf) {
            Assert-RootedLeaf -Path $modulePath -MaximumBytes 1048576
            $module = Get-Content -LiteralPath $modulePath -Raw | ConvertFrom-Json
            $moduleMatches = [string] $module.id -ceq [string] $script:policy.moduleGuid
        }
        if ($libraryMatches -or $moduleMatches) {
            $matches.Add([IO.Path]::GetFullPath($directory.FullName))
        }
    }
    if ($matches.Count -ne 1 -or -not (Test-SamePath -Left $matches[0] -Right $ExpectedPath)) {
        throw [InvalidOperationException]::new('MediaCarousel loader ownership is ambiguous.')
    }
}

function Assert-SymconOwnership {
    if ([int] (Invoke-SymconRpc -Method 'IPS_GetKernelRunlevel') -ne
        [int] $script:policy.expectedReadyRunlevel) {
        throw [InvalidOperationException]::new('Symcon is not at the required ready runlevel.')
    }
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_FunctionExists' -Parameters @('MC_ReloadModule'))) {
        throw [InvalidOperationException]::new('Targeted module reload is unavailable.')
    }
    $moduleControlId = [int] $script:policy.moduleControlInstanceId
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @($moduleControlId))) {
        throw [InvalidOperationException]::new('Pinned Module Control instance is absent.')
    }
    $moduleControl = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @($moduleControlId)
    if ([int] $moduleControl.InstanceStatus -ne 102 -or
        [string] $moduleControl.ModuleInfo.ModuleID -cne [string] $script:policy.moduleControlModuleGuid) {
        throw [InvalidOperationException]::new('Pinned Module Control instance is not healthy.')
    }
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_LibraryExists' -Parameters @(
        [string] $script:policy.libraryGuid
    )) -or -not [bool] (Invoke-SymconRpc -Method 'IPS_ModuleExists' -Parameters @(
        [string] $script:policy.moduleGuid
    ))) {
        throw [InvalidOperationException]::new('MediaCarousel library or module is absent.')
    }
    $library = Invoke-SymconRpc -Method 'IPS_GetLibrary' -Parameters @([string] $script:policy.libraryGuid)
    $module = Invoke-SymconRpc -Method 'IPS_GetModule' -Parameters @([string] $script:policy.moduleGuid)
    $libraryModules = @(Invoke-SymconRpc -Method 'IPS_GetLibraryModules' -Parameters @(
        [string] $script:policy.libraryGuid
    ))
    if ([string] $library.Name -cne [string] $script:policy.libraryName -or
        [string] $library.URL -cne [string] $script:policy.libraryUrl -or
        [string] $module.LibraryID -cne [string] $script:policy.libraryGuid -or
        [string] $module.ModuleName -cne [string] $script:policy.moduleName -or
        [int] $module.ModuleType -ne [int] $script:policy.moduleType -or
        [string] $module.Prefix -cne [string] $script:policy.modulePrefix -or
        $libraryModules.Count -ne 1 -or
        [string] $libraryModules[0] -cne [string] $script:policy.moduleGuid) {
        throw [InvalidOperationException]::new('MediaCarousel Symcon ownership is ambiguous.')
    }
}

function Invoke-TargetedReload {
    $script:reloadAttempted = $true
    $result = Invoke-SymconRpc -Method 'MC_ReloadModule' -Parameters @(
        [int] $script:policy.moduleControlInstanceId,
        [string] $script:policy.moduleDirectoryName
    )
    if (-not [bool] $result) {
        throw [InvalidOperationException]::new('Targeted module reload returned failure.')
    }
}

function Wait-Healthy {
    param([Parameter(Mandatory = $true)] $Snapshot)

    $timer = [Diagnostics.Stopwatch]::StartNew()
    do {
        try {
            Assert-SymconOwnership
            Assert-LoaderUniqueness -ExpectedPath ([string] $script:policy.activeModulePath)
            Assert-SnapshotPreserved -Snapshot $Snapshot
            return
        } catch {
            if ($timer.Elapsed.TotalSeconds -ge [int] $script:policy.reloadTimeoutSeconds) {
                throw
            }
            [Threading.Thread]::Sleep([int] $script:policy.healthPollMilliseconds)
        }
    } while ($true)
}

function Restore-RuntimeSnapshot {
    param([Parameter(Mandatory = $true)] $Snapshot)

    foreach ($record in @($Snapshot.instances)) {
        $instanceId = [int] $record.instanceId
        $current = [string] (Invoke-SymconRpc -Method 'IPS_GetConfiguration' -Parameters @($instanceId))
        $applyRequired = $false
        if ((Get-TextSha256 -Text $current) -cne [string] $record.configurationSha256) {
            $bytes = [Convert]::FromBase64String([string] $record.configurationBase64)
            try {
                $configuration = [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
                $null = Invoke-SymconRpc -Method 'IPS_SetConfiguration' -Parameters @(
                    $instanceId,
                    $configuration
                )
                $applyRequired = $true
            } finally {
                [Array]::Clear($bytes, 0, $bytes.Length)
            }
        }
        $references = Get-ReferenceIdentity -InstanceId $instanceId
        if ([string] $references.sha256 -cne [string] $record.referencesSha256) {
            $applyRequired = $true
        }
        if ($applyRequired) {
            $null = Invoke-SymconRpc -Method 'IPS_ApplyChanges' -Parameters @($instanceId)
        }
    }
}

function Read-PolicyAndContracts {
    Assert-RootedLeaf -Path $script:implementationPath -MaximumBytes 4194304
    Assert-RootedLeaf -Path $PolicyPath -MaximumBytes $MaximumContractBytes
    $script:implementationSha256 = Get-Sha256 -Path $script:implementationPath
    $script:policySha256 = Get-Sha256 -Path $PolicyPath
    if ($script:policySha256 -cne $ExpectedPolicySha256) {
        throw [Security.SecurityException]::new('Migration policy hash differs.')
    }
    $script:policy = Get-Content -LiteralPath $PolicyPath -Raw | ConvertFrom-Json
    Assert-ExactProperties -Value $script:policy -Label 'Migration policy' -Expected @(
        'formatVersion', 'migrationProfile', 'targetId', 'adapterProfile', 'libraryGuid',
        'moduleGuid', 'libraryName', 'moduleName', 'moduleType', 'modulePrefix', 'libraryUrl',
        'moduleControlInstanceId', 'moduleControlModuleGuid', 'moduleDirectoryName', 'loaderRoot',
        'activeModulePath', 'candidateModulePath', 'candidateManifestPath', 'migrationStateRoot',
        'credentialPath', 'rpcUri', 'deploymentUser', 'channelMutexName', 'adapterMutexName',
        'expectedSourceRepositoryUrl', 'expectedSourceBranch', 'expectedSourceCommit',
        'expectedSourceTreeSha256', 'expectedSourceAclSha256', 'expectedCandidateManifestSha256',
        'expectedCandidatePackageIdentitySha256', 'expectedImplementationSha256',
        'transactionContractPath', 'expectedTransactionContractSha256', 'expectedReadyRunlevel',
        'rpcTimeoutSeconds', 'reloadTimeoutSeconds', 'healthPollMilliseconds',
        'planLifetimeSeconds', 'allowedInstanceStatuses', 'maximumInstanceCount',
        'expectedInstances', 'maximumSourceBytes', 'maximumSourceEntries',
        'maximumCandidateBytes', 'maximumCandidateEntries', 'maximumStateBytes',
        'maximumLoaderDirectoryCount'
    )
    if ($script:policy.formatVersion -ne 1 -or
        [string] $script:policy.migrationProfile -cne $MigrationProfile -or
        [string] $script:policy.targetId -cne $TargetId -or
        [string] $script:policy.adapterProfile -cne 'saef-media-carousel-v1' -or
        -not (Test-SymconGuid -Value ([string] $script:policy.libraryGuid)) -or
        -not (Test-SymconGuid -Value ([string] $script:policy.moduleGuid)) -or
        -not (Test-SymconGuid -Value ([string] $script:policy.moduleControlModuleGuid) ) -or
        [int] $script:policy.moduleControlInstanceId -le 0 -or
        [string] $script:policy.moduleDirectoryName -notmatch '^[A-Za-z0-9._-]{1,128}$' -or
        -not (Test-SafeGitBranch -Value ([string] $script:policy.expectedSourceBranch)) -or
        -not (Test-GitObjectId -Value ([string] $script:policy.expectedSourceCommit)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedSourceTreeSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedSourceAclSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedCandidateManifestSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedCandidatePackageIdentitySha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedImplementationSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedTransactionContractSha256)) -or
        [string] $script:policy.expectedImplementationSha256 -cne $script:implementationSha256 -or
        [int] $script:policy.expectedReadyRunlevel -ne 10103 -or
        [int] $script:policy.rpcTimeoutSeconds -lt 1 -or [int] $script:policy.rpcTimeoutSeconds -gt 60 -or
        [int] $script:policy.reloadTimeoutSeconds -lt 1 -or
        [int] $script:policy.reloadTimeoutSeconds -gt 300 -or
        [int] $script:policy.healthPollMilliseconds -lt 100 -or
        [int] $script:policy.healthPollMilliseconds -gt 5000 -or
        [int] $script:policy.planLifetimeSeconds -lt 60 -or
        [int] $script:policy.planLifetimeSeconds -gt 3600 -or
        [int] $script:policy.maximumInstanceCount -lt 1 -or
        [int] $script:policy.maximumInstanceCount -gt 256 -or
        [long] $script:policy.maximumSourceBytes -lt 1 -or
        [long] $script:policy.maximumSourceBytes -gt 268435456 -or
        [int] $script:policy.maximumSourceEntries -lt 1 -or
        [int] $script:policy.maximumSourceEntries -gt 16384 -or
        [long] $script:policy.maximumCandidateBytes -lt 1 -or
        [long] $script:policy.maximumCandidateBytes -gt 268435456 -or
        [int] $script:policy.maximumCandidateEntries -lt 1 -or
        [int] $script:policy.maximumCandidateEntries -gt 4096 -or
        [int] $script:policy.maximumStateBytes -lt 65536 -or
        [int] $script:policy.maximumStateBytes -gt 16777216 -or
        [int] $script:policy.maximumLoaderDirectoryCount -lt 1 -or
        [int] $script:policy.maximumLoaderDirectoryCount -gt 1024 -or
        @($script:policy.allowedInstanceStatuses).Count -ne 1 -or
        [int] (@($script:policy.allowedInstanceStatuses)[0]) -ne 102) {
        throw [InvalidOperationException]::new('Migration policy contract is invalid.')
    }
    $rpcUri = [Uri] [string] $script:policy.rpcUri
    if ($rpcUri.Scheme -notin @('http', 'https') -or $rpcUri.Host -notin @('127.0.0.1', 'localhost', '::1')) {
        throw [InvalidOperationException]::new('Migration RPC endpoint is not loopback.')
    }
    foreach ($path in @(
        [string] $script:policy.loaderRoot,
        [string] $script:policy.activeModulePath,
        [string] $script:policy.candidateModulePath,
        [string] $script:policy.candidateManifestPath,
        [string] $script:policy.migrationStateRoot,
        [string] $script:policy.credentialPath,
        [string] $script:policy.transactionContractPath
    )) {
        if (-not [IO.Path]::IsPathRooted($path)) {
            throw [InvalidOperationException]::new('Migration policy contains a relative path.')
        }
    }
    if (-not (Test-PathContains -Parent ([string] $script:policy.loaderRoot) -Candidate (
        [string] $script:policy.activeModulePath
    )) -or
        (Split-Path -Leaf ([string] $script:policy.activeModulePath)) -cne
            [string] $script:policy.moduleDirectoryName -or
        -not [IO.Path]::GetPathRoot([string] $script:policy.activeModulePath).Equals(
            [IO.Path]::GetPathRoot([string] $script:policy.migrationStateRoot),
            [StringComparison]::OrdinalIgnoreCase
        )) {
        throw [InvalidOperationException]::new('Migration ownership paths are invalid.')
    }
    Assert-PathsDoNotOverlap `
        -Left ([string] $script:policy.activeModulePath) `
        -Right ([string] $script:policy.migrationStateRoot) `
        -Label 'Active and migration-state'
    Assert-PathsDoNotOverlap `
        -Left ([string] $script:policy.activeModulePath) `
        -Right ([string] $script:policy.candidateModulePath) `
        -Label 'Active and candidate'
    Assert-PathsDoNotOverlap `
        -Left ([string] $script:policy.migrationStateRoot) `
        -Right ([string] $script:policy.candidateModulePath) `
        -Label 'Migration-state and candidate'
    Assert-PlainDirectory -Path ([string] $script:policy.loaderRoot)
    Assert-PlainDirectory -Path ([string] $script:policy.migrationStateRoot)
    Assert-PlainDirectory -Path (Split-Path -Parent $ReviewPlanPath)
    Assert-PlainDirectory -Path (Split-Path -Parent $StatusPath)
    if (-not (Test-PathContains -Parent ([string] $script:policy.migrationStateRoot) -Candidate (
        $ReviewPlanPath
    )) -or
        -not (Test-PathContains -Parent ([string] $script:policy.migrationStateRoot) -Candidate (
            $StatusPath
        )) -or
        (Test-SamePath -Left $ReviewPlanPath -Right $StatusPath) -or
        (Test-SamePath -Left $ReviewPlanPath -Right $PolicyPath) -or
        (Test-SamePath -Left $StatusPath -Right $PolicyPath) -or
        (Test-SamePath -Left $ReviewPlanPath -Right ([string] $script:policy.candidateManifestPath)) -or
        (Test-SamePath -Left $StatusPath -Right ([string] $script:policy.candidateManifestPath)) -or
        (Test-SamePath -Left $ReviewPlanPath -Right ([string] $script:policy.transactionContractPath)) -or
        (Test-SamePath -Left $StatusPath -Right ([string] $script:policy.transactionContractPath))) {
        throw [Security.SecurityException]::new('Migration evidence paths are unsafe.')
    }
    $deploymentAccount = Get-LocalUser -Name ([string] $script:policy.deploymentUser) -ErrorAction Stop
    if (-not $deploymentAccount.Enabled) {
        throw [Security.SecurityException]::new('Deployment account is disabled.')
    }
    $administratorGroup = Get-LocalGroup -SID 'S-1-5-32-544' -ErrorAction Stop
    $administratorMembers = @(Get-LocalGroupMember -Group $administratorGroup.Name -ErrorAction Stop)
    if ($deploymentAccount.SID -notin $administratorMembers.SID) {
        throw [Security.SecurityException]::new('Deployment account is not a local administrator.')
    }
    Assert-ManagedRootAcl `
        -Root ([string] $script:policy.migrationStateRoot) `
        -DeploymentSid ([string] $deploymentAccount.SID.Value)
    Assert-RootedLeaf `
        -Path ([string] $script:policy.transactionContractPath) `
        -MaximumBytes $MaximumContractBytes
    $script:transactionSha256 = Get-Sha256 -Path ([string] $script:policy.transactionContractPath)
    if ($script:transactionSha256 -cne [string] $script:policy.expectedTransactionContractSha256) {
        throw [Security.SecurityException]::new('Migration transaction hash differs from policy.')
    }
    $script:transaction = Get-Content -LiteralPath ([string] $script:policy.transactionContractPath) -Raw |
        ConvertFrom-Json
    if ($script:transaction.formatVersion -ne 1 -or
        [string] $script:transaction.migrationProfile -cne $MigrationProfile -or
        [string] $script:transaction.source.owner -cne 'module-control-git-checkout' -or
        -not [bool] $script:transaction.source.repositoryMetadataRequired -or
        -not [bool] $script:transaction.source.preserveByteExactRollback -or
        [string] $script:transaction.candidate.owner -cne 'channel-v8-adapter-package' -or
        [bool] $script:transaction.candidate.repositoryMetadataAllowed -or
        [string] $script:transaction.approval.nonceClaimMode -cne 'create-new-file' -or
        [string] $script:transaction.switch.mode -cne 'same-volume-directory-move' -or
        -not [bool] $script:transaction.rollback.automaticAfterSourceMove -or
        -not [bool] $script:transaction.rollback.manualRecoveryOnUnprovenRestore -or
        -not [bool] $script:transaction.forbidden.moduleUpdate -or
        -not [bool] $script:transaction.forbidden.serviceRestart -or
        -not [bool] $script:transaction.forbidden.providerContact -or
        -not [bool] $script:transaction.forbidden.publication -or
        -not [bool] $script:transaction.forbidden.retentionDeletion) {
        throw [InvalidOperationException]::new('Migration transaction contract is invalid.')
    }
    [string[]] $lockOrder = @($script:transaction.locks.order | ForEach-Object { [string] $_ })
    if ([string]::Join([char] 0, $lockOrder) -cne
        [string]::Join([char] 0, @('channel', 'adapter')) -or
        [int] $script:transaction.locks.waitMilliseconds -ne 0) {
        throw [InvalidOperationException]::new('Migration lock order is invalid.')
    }
    foreach ($expected in @($script:policy.expectedInstances)) {
        Assert-ExactProperties -Value $expected -Label 'Expected instance' -Expected @(
            'instanceId', 'configurationSha256', 'referenceCount', 'referencesSha256'
        )
        if ([int] $expected.instanceId -le 0 -or
            -not (Test-HexSha256 -Value ([string] $expected.configurationSha256)) -or
            [int] $expected.referenceCount -lt 0 -or
            -not (Test-HexSha256 -Value ([string] $expected.referencesSha256))) {
            throw [InvalidOperationException]::new('Expected instance binding is invalid.')
        }
    }
    Read-CandidateManifest
}

function Acquire-MigrationLocks {
    $script:channelMutex = [Threading.Mutex]::new($false, [string] $script:policy.channelMutexName)
    try {
        $script:channelMutexAcquired = $script:channelMutex.WaitOne(0)
    } catch [Threading.AbandonedMutexException] {
        $script:channelMutexAcquired = $true
    }
    if (-not $script:channelMutexAcquired) {
        throw [TimeoutException]::new('Deployment channel operation is active.')
    }
    $script:adapterMutex = [Threading.Mutex]::new($false, [string] $script:policy.adapterMutexName)
    try {
        $script:adapterMutexAcquired = $script:adapterMutex.WaitOne(0)
    } catch [Threading.AbandonedMutexException] {
        $script:adapterMutexAcquired = $true
    }
    if (-not $script:adapterMutexAcquired) {
        throw [TimeoutException]::new('MediaCarousel adapter operation is active.')
    }
}

function New-ReviewPlan {
    param(
        [Parameter(Mandatory = $true)] $Source,
        [Parameter(Mandatory = $true)] $Candidate,
        [Parameter(Mandatory = $true)] $Snapshot
    )

    if (Test-Path -LiteralPath $ReviewPlanPath) {
        throw [InvalidOperationException]::new('Review plan output already exists.')
    }
    $now = [DateTime]::UtcNow
    $operatorSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    $plan = [ordered]@{
        formatVersion = 1
        migrationProfile = $MigrationProfile
        targetId = $TargetId
        adapterProfile = [string] $script:policy.adapterProfile
        allowedOperation = 'apply'
        sourceOwner = [string] $script:transaction.source.owner
        candidateOwner = [string] $script:transaction.candidate.owner
        generatedAtUtc = $now.ToString('o')
        expiresAtUtc = $now.AddSeconds([int] $script:policy.planLifetimeSeconds).ToString('o')
        nonce = [Guid]::NewGuid().ToString('N')
        machineName = [Environment]::MachineName
        operatorSid = $operatorSid
        policySha256 = $script:policySha256
        implementationSha256 = $script:implementationSha256
        transactionContractSha256 = $script:transactionSha256
        sourcePath = [IO.Path]::GetFullPath([string] $script:policy.activeModulePath)
        sourceTreeSha256 = [string] $Source.treeSha256
        sourceAclSha256 = [string] $Source.aclSha256
        sourceRepositoryUrl = [string] $Source.repositoryUrl
        sourceBranch = [string] $Source.branch
        sourceCommit = [string] $Source.commit
        candidatePath = [IO.Path]::GetFullPath([string] $script:policy.candidateModulePath)
        candidateManifestSha256 = [string] $Candidate.manifestSha256
        candidatePackageIdentitySha256 = [string] $Candidate.packageIdentitySha256
        instanceSnapshotSha256 = [string] $Snapshot.identitySha256
        instanceCount = [int] $Snapshot.instanceCount
        referenceCount = [int] $Snapshot.referenceCount
        operationSequence = @($script:transaction.operationSequence)
    }
    $bytes = ConvertTo-Utf8JsonBytes -Value $plan -Depth 10
    try {
        if ($bytes.Length -gt $MaximumPlanBytes) {
            throw [InvalidOperationException]::new('Review plan exceeds its byte bound.')
        }
        Write-AtomicBytes -Path $ReviewPlanPath -Bytes $bytes
        $script:privateEvidenceMutationAttempted = $true
        $script:planSha256 = Get-BytesSha256 -Bytes $bytes
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
    $script:plan = $plan
}

function Read-And-ValidatePlan {
    param([Parameter(Mandatory = $true)][bool] $RequireUnexpired)

    Assert-RootedLeaf -Path $ReviewPlanPath -MaximumBytes $MaximumPlanBytes
    if (-not (Test-HexSha256 -Value $ExpectedPlanSha256)) {
        throw [Security.SecurityException]::new('Expected review plan hash is missing.')
    }
    $script:planSha256 = Get-Sha256 -Path $ReviewPlanPath
    if ($script:planSha256 -cne $ExpectedPlanSha256) {
        throw [Security.SecurityException]::new('Review plan hash differs.')
    }
    $script:plan = Get-Content -LiteralPath $ReviewPlanPath -Raw | ConvertFrom-Json
    Assert-ExactProperties -Value $script:plan -Label 'Review plan' -Expected @(
        'formatVersion', 'migrationProfile', 'targetId', 'adapterProfile', 'allowedOperation',
        'sourceOwner', 'candidateOwner', 'generatedAtUtc', 'expiresAtUtc', 'nonce',
        'machineName', 'operatorSid', 'policySha256', 'implementationSha256',
        'transactionContractSha256', 'sourcePath', 'sourceTreeSha256', 'sourceAclSha256',
        'sourceRepositoryUrl', 'sourceBranch', 'sourceCommit', 'candidatePath',
        'candidateManifestSha256', 'candidatePackageIdentitySha256',
        'instanceSnapshotSha256', 'instanceCount', 'referenceCount', 'operationSequence'
    )
    $currentSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    if ($script:plan.formatVersion -ne 1 -or
        [string] $script:plan.migrationProfile -cne $MigrationProfile -or
        [string] $script:plan.targetId -cne $TargetId -or
        [string] $script:plan.adapterProfile -cne [string] $script:policy.adapterProfile -or
        [string] $script:plan.allowedOperation -cne 'apply' -or
        [string] $script:plan.sourceOwner -cne [string] $script:transaction.source.owner -or
        [string] $script:plan.candidateOwner -cne [string] $script:transaction.candidate.owner -or
        [string] $script:plan.nonce -notmatch '^[a-f0-9]{32}$' -or
        [string] $script:plan.machineName -cne [Environment]::MachineName -or
        [string] $script:plan.operatorSid -cne $currentSid -or
        [string] $script:plan.policySha256 -cne $script:policySha256 -or
        [string] $script:plan.implementationSha256 -cne $script:implementationSha256 -or
        [string] $script:plan.transactionContractSha256 -cne $script:transactionSha256 -or
        -not (Test-SamePath -Left ([string] $script:plan.sourcePath) -Right (
            [string] $script:policy.activeModulePath
        )) -or
        -not (Test-SamePath -Left ([string] $script:plan.candidatePath) -Right (
            [string] $script:policy.candidateModulePath
        )) -or
        [string] $script:plan.sourceTreeSha256 -cne
            [string] $script:policy.expectedSourceTreeSha256 -or
        [string] $script:plan.sourceAclSha256 -cne [string] $script:policy.expectedSourceAclSha256 -or
        [string] $script:plan.sourceRepositoryUrl -cne
            [string] $script:policy.expectedSourceRepositoryUrl -or
        [string] $script:plan.sourceBranch -cne [string] $script:policy.expectedSourceBranch -or
        [string] $script:plan.sourceCommit -cne [string] $script:policy.expectedSourceCommit -or
        [string] $script:plan.candidateManifestSha256 -cne
            [string] $script:policy.expectedCandidateManifestSha256 -or
        [string] $script:plan.candidatePackageIdentitySha256 -cne
            [string] $script:policy.expectedCandidatePackageIdentitySha256) {
        throw [Security.SecurityException]::new('Review plan binding is invalid.')
    }
    [string[]] $planSequence = @($script:plan.operationSequence | ForEach-Object { [string] $_ })
    [string[]] $contractSequence = @($script:transaction.operationSequence | ForEach-Object { [string] $_ })
    if ([string]::Join([char] 0, $planSequence) -cne [string]::Join([char] 0, $contractSequence)) {
        throw [Security.SecurityException]::new('Review plan operation sequence differs.')
    }
    $expiresAt = [DateTime]::Parse([string] $script:plan.expiresAtUtc).ToUniversalTime()
    $generatedAt = [DateTime]::Parse([string] $script:plan.generatedAtUtc).ToUniversalTime()
    if ($expiresAt -le $generatedAt -or
        ($expiresAt - $generatedAt).TotalSeconds -gt [int] $script:policy.planLifetimeSeconds) {
        throw [Security.SecurityException]::new('Review plan time binding is invalid.')
    }
    if ($RequireUnexpired -and [DateTime]::UtcNow -gt $expiresAt) {
        throw [Security.SecurityException]::new('Review plan has expired.')
    }
    $script:claimPath = Join-Path ([string] $script:policy.migrationStateRoot) (
        'claim-' + [string] $script:plan.nonce + '.json'
    )
    $script:transactionRoot = Join-Path ([string] $script:policy.migrationStateRoot) (
        'transaction-' + [string] $script:plan.nonce
    )
    $script:rollbackPath = Join-Path $script:transactionRoot 'rollback'
    $script:candidateStagingPath = Join-Path $script:transactionRoot 'candidate'
    $script:failedCandidatePath = Join-Path $script:transactionRoot 'failed-candidate'
    if ((Test-SamePath -Left $ReviewPlanPath -Right $script:claimPath) -or
        (Test-PathContains -Parent $script:transactionRoot -Candidate $ReviewPlanPath) -or
        (Test-PathContains -Parent $script:transactionRoot -Candidate $StatusPath)) {
        throw [Security.SecurityException]::new('Review plan collides with transaction evidence.')
    }
}

function New-PlanClaim {
    $claim = [ordered]@{
        formatVersion = 1
        migrationProfile = $MigrationProfile
        targetId = $TargetId
        planSha256 = $script:planSha256
        nonce = [string] $script:plan.nonce
        claimedAtUtc = [DateTime]::UtcNow.ToString('o')
        machineName = [Environment]::MachineName
        operatorSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    }
    $bytes = ConvertTo-Utf8JsonBytes -Value $claim -Depth 6
    $stream = $null
    try {
        $stream = [IO.File]::Open(
            $script:claimPath,
            [IO.FileMode]::CreateNew,
            [IO.FileAccess]::Write,
            [IO.FileShare]::None
        )
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Flush($true)
        $script:claimCreated = $true
        $script:privateEvidenceMutationAttempted = $true
    } finally {
        if ($null -ne $stream) { $stream.Dispose() }
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Write-TransactionState {
    param([Parameter(Mandatory = $true)][string] $Outcome)

    $state = [ordered]@{
        formatVersion = 1
        migrationProfile = $MigrationProfile
        targetId = $TargetId
        planSha256 = $script:planSha256
        nonce = [string] $script:plan.nonce
        outcome = $Outcome
        updatedAtUtc = [DateTime]::UtcNow.ToString('o')
        sourceTreeSha256 = [string] $script:policy.expectedSourceTreeSha256
        sourceAclSha256 = [string] $script:policy.expectedSourceAclSha256
        candidatePackageIdentitySha256 = [string] $script:policy.expectedCandidatePackageIdentitySha256
        rollbackDirectoryName = 'rollback'
        candidateDirectoryName = 'candidate'
        failedCandidateDirectoryName = 'failed-candidate'
        snapshotFileName = 'snapshot.json'
        policyFileName = 'policy.json'
        transactionContractFileName = 'migration-transaction.json'
        candidateManifestFileName = 'candidate-manifest.json'
        implementationFileName = 'Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1'
        policySha256 = $script:policySha256
        transactionContractSha256 = $script:transactionSha256
        candidateManifestSha256 = $script:manifestSha256
        implementationSha256 = $script:implementationSha256
    }
    Write-AtomicJson -Path (Join-Path $script:transactionRoot 'transaction.json') -Value $state
    $script:privateEvidenceMutationAttempted = $true
}

function Assert-FreshPlanBaseline {
    $source = Get-SourceIdentity -Root ([string] $script:policy.activeModulePath)
    $candidate = Get-CandidateIdentity -Root ([string] $script:policy.candidateModulePath)
    Assert-SymconOwnership
    Assert-LoaderUniqueness -ExpectedPath ([string] $script:policy.activeModulePath)
    $snapshot = Get-InstanceSnapshot
    if ([string] $source.treeSha256 -cne [string] $script:plan.sourceTreeSha256 -or
        [string] $source.aclSha256 -cne [string] $script:plan.sourceAclSha256 -or
        [string] $candidate.packageIdentitySha256 -cne
            [string] $script:plan.candidatePackageIdentitySha256 -or
        [string] $candidate.manifestSha256 -cne [string] $script:plan.candidateManifestSha256 -or
        [string] $snapshot.identitySha256 -cne [string] $script:plan.instanceSnapshotSha256 -or
        [int] $snapshot.instanceCount -ne [int] $script:plan.instanceCount -or
        [int] $snapshot.referenceCount -ne [int] $script:plan.referenceCount) {
        throw [InvalidOperationException]::new('Fresh baseline differs from the reviewed plan.')
    }
    $script:sourceIdentity = $source
    $script:candidateIdentity = $candidate
    $script:snapshot = $snapshot
}

function Read-TransactionSnapshot {
    $snapshotPath = Join-Path $script:transactionRoot 'snapshot.json'
    Assert-RootedLeaf -Path $snapshotPath -MaximumBytes ([int] $script:policy.maximumStateBytes)
    $snapshot = Get-Content -LiteralPath $snapshotPath -Raw | ConvertFrom-Json
    if ($snapshot.formatVersion -ne 1 -or
        -not (Test-HexSha256 -Value ([string] $snapshot.identitySha256)) -or
        [int] $snapshot.instanceCount -lt 1 -or
        @($snapshot.instances).Count -ne [int] $snapshot.instanceCount) {
        throw [InvalidOperationException]::new('Retained runtime snapshot is invalid.')
    }
    return $snapshot
}

function Get-TransactionInspectionState {
    $claimExists = Test-Path -LiteralPath $script:claimPath -PathType Leaf
    $transactionExists = Test-Path -LiteralPath $script:transactionRoot -PathType Container
    if (-not $claimExists -and -not $transactionExists) {
        return 'not_started'
    }
    if (-not $claimExists -or -not $transactionExists) {
        return 'manual_recovery_required'
    }
    $transactionPath = Join-Path $script:transactionRoot 'transaction.json'
    if (-not (Test-Path -LiteralPath $transactionPath -PathType Leaf)) {
        return 'claimed_without_transaction'
    }
    Assert-RootedLeaf -Path $transactionPath -MaximumBytes $MaximumContractBytes
    $state = Get-Content -LiteralPath $transactionPath -Raw | ConvertFrom-Json
    if ($state.formatVersion -ne 1 -or
        [string] $state.migrationProfile -cne $MigrationProfile -or
        [string] $state.planSha256 -cne $script:planSha256 -or
        [string] $state.nonce -cne [string] $script:plan.nonce) {
        return 'manual_recovery_required'
    }
    if ([string] $state.outcome -notin @(
        'prepared', 'source_moved', 'candidate_active', 'migrated', 'rolled_back',
        'manual_recovery_required'
    )) {
        return 'manual_recovery_required'
    }
    return [string] $state.outcome
}

function Invoke-MigrationRollback {
    param([Parameter(Mandatory = $true)] $Snapshot)

    $script:rollbackAttempted = $true
    try {
        $activePath = [string] $script:policy.activeModulePath
        $activeIsSource = $false
        $activeIsCandidate = $false
        if (Test-Path -LiteralPath $activePath -PathType Container) {
            try {
                $source = Get-SourceIdentity -Root $activePath
                $activeIsSource = [string] $source.treeSha256 -ceq
                    [string] $script:policy.expectedSourceTreeSha256
            } catch {
                $activeIsSource = $false
            }
            if (-not $activeIsSource) {
                try {
                    $candidate = Get-CandidateIdentity -Root $activePath
                    $activeIsCandidate = [string] $candidate.packageIdentitySha256 -ceq
                        [string] $script:policy.expectedCandidatePackageIdentitySha256
                } catch {
                    $activeIsCandidate = $false
                }
            }
        }
        $reloadRequired = $false
        if ($activeIsCandidate) {
            if (Test-Path -LiteralPath $script:failedCandidatePath) {
                throw [InvalidOperationException]::new('Failed-candidate retention path already exists.')
            }
            [IO.Directory]::Move($activePath, $script:failedCandidatePath)
            $script:productionMutationAttempted = $true
            $script:candidateActivated = $false
            $reloadRequired = $true
        } elseif ((Test-Path -LiteralPath $activePath) -and -not $activeIsSource) {
            throw [InvalidOperationException]::new('Active module path has an unknown identity.')
        }
        if (Test-Path -LiteralPath $script:rollbackPath -PathType Container) {
            if (Test-Path -LiteralPath $activePath) {
                throw [InvalidOperationException]::new('Active source and rollback source both exist.')
            }
            $restored = Get-SourceIdentity -Root $script:rollbackPath
            if ([string] $restored.treeSha256 -cne [string] $script:policy.expectedSourceTreeSha256) {
                throw [InvalidOperationException]::new('Rollback source identity differs.')
            }
            [IO.Directory]::Move($script:rollbackPath, $activePath)
            $script:productionMutationAttempted = $true
            $script:sourceMoved = $false
            $reloadRequired = $true
            $activeIsSource = $true
        }
        if (-not $activeIsSource) {
            throw [InvalidOperationException]::new('Byte-exact source cannot be restored.')
        }
        if (Test-Path -LiteralPath $script:candidateStagingPath -PathType Container) {
            if (Test-Path -LiteralPath $script:failedCandidatePath) {
                throw [InvalidOperationException]::new('Candidate retention paths are ambiguous.')
            }
            [IO.Directory]::Move($script:candidateStagingPath, $script:failedCandidatePath)
            $script:privateEvidenceMutationAttempted = $true
        }
        if ($reloadRequired) {
            Invoke-TargetedReload
        }
        Restore-RuntimeSnapshot -Snapshot $Snapshot
        $restoredSource = Get-SourceIdentity -Root $activePath
        Assert-LoaderUniqueness -ExpectedPath $activePath
        Wait-Healthy -Snapshot $Snapshot
        if ([string] $restoredSource.treeSha256 -cne [string] $script:policy.expectedSourceTreeSha256) {
            throw [InvalidOperationException]::new('Restored source postflight failed.')
        }
        $script:rollbackSucceeded = $true
        Write-TransactionState -Outcome 'rolled_back'
    } catch {
        $script:rollbackSucceeded = $false
        try { Write-TransactionState -Outcome 'manual_recovery_required' } catch { }
    }
}

try {
    $script:failureCode = 'contract'
    Read-PolicyAndContracts
    if ($Operation -eq 'apply' -and $Confirmation -cne $ApplyConfirmation) {
        throw [Security.SecurityException]::new('Explicit migration confirmation is missing.')
    }
    if ($Operation -eq 'rollback' -and $Confirmation -cne $RollbackConfirmation) {
        throw [Security.SecurityException]::new('Explicit rollback confirmation is missing.')
    }

    if ($Operation -eq 'preflight') {
        if (-not [string]::IsNullOrEmpty($ExpectedPlanSha256)) {
            throw [InvalidOperationException]::new('Preflight must not receive an expected plan hash.')
        }
        $script:failureCode = 'locks'
        Acquire-MigrationLocks
        $script:failureCode = 'source'
        $script:sourceIdentity = Get-SourceIdentity -Root ([string] $script:policy.activeModulePath)
        $script:failureCode = 'candidate'
        $script:candidateIdentity = Get-CandidateIdentity -Root ([string] $script:policy.candidateModulePath)
        $script:credential = Import-MachineCredential -Path ([string] $script:policy.credentialPath)
        $script:failureCode = 'runtime'
        Assert-SymconOwnership
        Assert-LoaderUniqueness -ExpectedPath ([string] $script:policy.activeModulePath)
        $script:snapshot = Get-InstanceSnapshot
        $script:failureCode = 'plan'
        New-ReviewPlan `
            -Source $script:sourceIdentity `
            -Candidate $script:candidateIdentity `
            -Snapshot $script:snapshot
        $script:failureCode = 'none'
        $script:finalOutcome = 'ready'
        $script:finalExitCode = $ExitSuccess
    } elseif ($Operation -eq 'inspect') {
        $script:failureCode = 'plan'
        Read-And-ValidatePlan -RequireUnexpired $false
        $script:failureCode = 'locks'
        Acquire-MigrationLocks
        $script:inspectionState = Get-TransactionInspectionState
        $script:failureCode = 'none'
        $script:finalOutcome = 'inspected'
        $script:finalExitCode = $ExitSuccess
    } elseif ($Operation -eq 'apply') {
        $script:failureCode = 'plan'
        Read-And-ValidatePlan -RequireUnexpired $true
        $script:failureCode = 'locks'
        Acquire-MigrationLocks
        if ((Test-Path -LiteralPath $script:claimPath) -or
            (Test-Path -LiteralPath $script:transactionRoot)) {
            throw [Security.SecurityException]::new('Review plan nonce has already been claimed.')
        }
        $script:failureCode = 'initial_baseline'
        $script:credential = Import-MachineCredential -Path ([string] $script:policy.credentialPath)
        Assert-FreshPlanBaseline
        $deploymentAccount = Get-LocalUser -Name ([string] $script:policy.deploymentUser) -ErrorAction Stop
        $script:failureCode = 'claim'
        New-PlanClaim
        [IO.Directory]::CreateDirectory($script:transactionRoot) | Out-Null
        Set-ManagedTreeAcl -Root $script:transactionRoot -DeploymentSid $deploymentAccount.SID
        Assert-ManagedRootAcl `
            -Root $script:transactionRoot `
            -DeploymentSid ([string] $deploymentAccount.SID.Value)
        [IO.File]::Copy($ReviewPlanPath, (Join-Path $script:transactionRoot 'plan.json'), $false)
        [IO.File]::Copy($PolicyPath, (Join-Path $script:transactionRoot 'policy.json'), $false)
        [IO.File]::Copy(
            [string] $script:policy.transactionContractPath,
            (Join-Path $script:transactionRoot 'migration-transaction.json'),
            $false
        )
        [IO.File]::Copy(
            [string] $script:policy.candidateManifestPath,
            (Join-Path $script:transactionRoot 'candidate-manifest.json'),
            $false
        )
        [IO.File]::Copy(
            $script:implementationPath,
            (Join-Path $script:transactionRoot 'Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1'),
            $false
        )
        Write-AtomicJson -Path (Join-Path $script:transactionRoot 'snapshot.json') `
            -Value $script:snapshot -Depth 12
        $script:privateEvidenceMutationAttempted = $true
        $script:failureCode = 'candidate_staging'
        Copy-CandidateToStaging `
            -Destination $script:candidateStagingPath `
            -DeploymentSid $deploymentAccount.SID
        Write-TransactionState -Outcome 'prepared'
        $script:transactionPrepared = $true
        $script:failureCode = 'pre_mutation_recheck'
        Assert-FreshPlanBaseline
        $staged = Get-CandidateIdentity -Root $script:candidateStagingPath
        if ([string] $staged.packageIdentitySha256 -cne
            [string] $script:plan.candidatePackageIdentitySha256) {
            throw [InvalidOperationException]::new('Staged candidate drifted before mutation.')
        }
        $script:failureCode = 'source_move'
        [IO.Directory]::Move([string] $script:policy.activeModulePath, $script:rollbackPath)
        $script:productionMutationAttempted = $true
        $script:sourceMoved = $true
        Write-TransactionState -Outcome 'source_moved'
        $movedSource = Get-SourceIdentity -Root $script:rollbackPath
        $staged = Get-CandidateIdentity -Root $script:candidateStagingPath
        if ([string] $movedSource.treeSha256 -cne [string] $script:plan.sourceTreeSha256 -or
            [string] $movedSource.aclSha256 -cne [string] $script:plan.sourceAclSha256 -or
            [string] $staged.packageIdentitySha256 -cne
                [string] $script:plan.candidatePackageIdentitySha256) {
            throw [InvalidOperationException]::new('Moved source or staged candidate identity drifted.')
        }
        $script:failureCode = 'candidate_move'
        [IO.Directory]::Move($script:candidateStagingPath, [string] $script:policy.activeModulePath)
        $script:candidateActivated = $true
        Write-TransactionState -Outcome 'candidate_active'
        $script:failureCode = 'targeted_reload'
        Invoke-TargetedReload
        $script:failureCode = 'postflight'
        $activeCandidate = Get-CandidateIdentity -Root ([string] $script:policy.activeModulePath)
        Assert-LoaderUniqueness -ExpectedPath ([string] $script:policy.activeModulePath)
        Wait-Healthy -Snapshot $script:snapshot
        if ([string] $activeCandidate.packageIdentitySha256 -cne
            [string] $script:plan.candidatePackageIdentitySha256) {
            throw [InvalidOperationException]::new('Active candidate identity differs after reload.')
        }
        Write-TransactionState -Outcome 'migrated'
        $script:failureCode = 'none'
        $script:inspectionState = 'migrated'
        $script:finalOutcome = 'migrated'
        $script:finalExitCode = $ExitSuccess
    } else {
        $script:failureCode = 'plan'
        Read-And-ValidatePlan -RequireUnexpired $false
        $script:failureCode = 'locks'
        Acquire-MigrationLocks
        $script:failureCode = 'transaction'
        if (-not (Test-Path -LiteralPath $script:claimPath -PathType Leaf) -or
            -not (Test-Path -LiteralPath $script:transactionRoot -PathType Container)) {
            throw [InvalidOperationException]::new('Retained migration transaction is incomplete.')
        }
        $script:snapshot = Read-TransactionSnapshot
        $script:credential = Import-MachineCredential -Path ([string] $script:policy.credentialPath)
        $script:failureCode = 'rollback'
        Invoke-MigrationRollback -Snapshot $script:snapshot
        if (-not $script:rollbackSucceeded) {
            throw [InvalidOperationException]::new('Byte-exact migration rollback is unproven.')
        }
        $script:failureCode = 'none'
        $script:inspectionState = 'rolled_back'
        $script:finalOutcome = 'rolled_back'
        $script:finalExitCode = $ExitRolledBack
    }
} catch {
    if ($Operation -eq 'apply' -and ($script:sourceMoved -or $script:candidateActivated)) {
        $originalFailureCode = $script:failureCode
        Invoke-MigrationRollback -Snapshot $script:snapshot
        $script:failureCode = $originalFailureCode
        if ($script:rollbackSucceeded) {
            $script:inspectionState = 'rolled_back'
            $script:finalOutcome = 'rolled_back'
            $script:finalExitCode = $ExitRolledBack
        } else {
            $script:inspectionState = 'manual_recovery_required'
            $script:finalOutcome = 'manual_recovery_required'
            $script:finalExitCode = $ExitManualRecovery
        }
    } elseif ($Operation -eq 'apply') {
        $script:finalOutcome = 'failed'
        $script:finalExitCode = $ExitApplyFailed
    } elseif ($Operation -eq 'rollback') {
        $script:inspectionState = 'manual_recovery_required'
        $script:finalOutcome = 'manual_recovery_required'
        $script:finalExitCode = $ExitManualRecovery
    } else {
        $script:finalOutcome = 'failed'
        $script:finalExitCode = $ExitPreflightFailed
    }
} finally {
    try { Write-MigrationStatus } catch { }
    if ($script:adapterMutexAcquired -and $null -ne $script:adapterMutex) {
        $script:adapterMutex.ReleaseMutex()
    }
    if ($null -ne $script:adapterMutex) { $script:adapterMutex.Dispose() }
    if ($script:channelMutexAcquired -and $null -ne $script:channelMutex) {
        $script:channelMutex.ReleaseMutex()
    }
    if ($null -ne $script:channelMutex) { $script:channelMutex.Dispose() }
    $script:credential = $null
}

exit $script:finalExitCode
