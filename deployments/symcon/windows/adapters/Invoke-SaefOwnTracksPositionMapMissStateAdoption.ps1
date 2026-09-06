[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'adopt')]
    [string] $Operation,

    [Parameter(Mandatory = $true)]
    [string] $AdapterPolicyPath,

    [Parameter(Mandatory = $true)]
    [string] $AdoptionContractPath,

    [Parameter(Mandatory = $true)]
    [string] $AdoptionPlanPath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitRolledBack = 30
$ExitManualRecovery = 40
$ExpectedRuntimeCompatibilitySha256 = '1f3de77041faba15cf5f062dc742474a52aded6f7d75b1eef03efaa3b1a8fc6f'
$script:policy = $null
$script:contract = $null
$script:plan = $null
$script:mutex = $null
$script:mutexAcquired = $false
$script:runtimeLocks = @()
$script:sourceBytes = $null
$script:sourceSha256 = ''
$script:candidateBytes = $null
$script:candidateSha256 = ''
$script:semanticSha256 = ''
$script:selectionCount = 0
$script:transactionPath = $null
$script:transactionCreatedUtc = ''
$script:backupPath = $null
$script:stateMutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:liveStateReadAttempted = $false
$script:failureCode = 'initialization'
$script:finalExitCode = $ExitManualRecovery

function Test-HexSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)
    return $Value -match '^[a-f0-9]{64}$'
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

function Write-AtomicBytes {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][byte[]] $Bytes
    )
    $directory = Split-Path -Parent $Path
    if ([string]::IsNullOrWhiteSpace($directory) -or
        -not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Atomic output directory is missing.')
    }
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-owntracks-state-adoption-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-state-adoption-' + $token + '.bak')
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

function Write-AtomicJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value
    )
    $json = ($Value | ConvertTo-Json -Depth 16) + [Environment]::NewLine
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($json)
    try {
        Write-AtomicBytes -Path $Path -Bytes $bytes
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Write-AdoptionStatus {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode
    )
    Write-AtomicJson -Path $StatusPath -Value ([ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'owntracks_miss_state_adoption'
        operation = $Operation
        outcome = $Outcome
        exitCode = $ExitCode
        sourceFormat = 1
        candidateFormat = 2
        sourceSha256 = $script:sourceSha256
        candidateSha256 = $script:candidateSha256
        semanticSha256 = $script:semanticSha256
        selectionCount = $script:selectionCount
        transactionCreated = ($null -ne $script:transactionPath)
        stateMutationAttempted = [bool] $script:stateMutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        failureCode = $script:failureCode
        liveStateReadAttempted = [bool] $script:liveStateReadAttempted
        moduleReloadAttempted = $false
        channelInstallationAttempted = $false
        providerContactAttempted = $false
        symconRpcContactAttempted = $false
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

function Assert-SafeDirectoryTree {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Required directory is missing.')
    }
    foreach ($entry in @((Get-Item -LiteralPath $Path)) +
        @(Get-ChildItem -LiteralPath $Path -Force -Recurse)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [InvalidOperationException]::new('Directory tree contains a reparse point.')
        }
    }
}

function Test-PathOverlap {
    param(
        [Parameter(Mandatory = $true)][string] $Left,
        [Parameter(Mandatory = $true)][string] $Right
    )
    $leftPath = [IO.Path]::GetFullPath($Left).TrimEnd([char[]] @('\', '/'))
    $rightPath = [IO.Path]::GetFullPath($Right).TrimEnd([char[]] @('\', '/'))
    $separator = [IO.Path]::DirectorySeparatorChar
    return $leftPath.Equals($rightPath, [StringComparison]::OrdinalIgnoreCase) -or
        $leftPath.StartsWith($rightPath + $separator, [StringComparison]::OrdinalIgnoreCase) -or
        $rightPath.StartsWith($leftPath + $separator, [StringComparison]::OrdinalIgnoreCase)
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
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entry.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            (Test-BroadWriteAccess -Rights $entry.FileSystemRights) -and
            $sid -in @('S-1-1-0', 'S-1-5-11', 'S-1-5-32-545')) {
            throw [UnauthorizedAccessException]::new('State-adoption path grants broad write access.')
        }
    }
}

function Get-DirectoryPackageIdentity {
    param([Parameter(Mandatory = $true)][string] $Path)
    Assert-SafeDirectoryTree -Path $Path
    $root = [IO.Path]::GetFullPath($Path).TrimEnd([char[]] @('\', '/')) +
        [IO.Path]::DirectorySeparatorChar
    $files = [Collections.Generic.Dictionary[string,IO.FileInfo]]::new([StringComparer]::Ordinal)
    foreach ($file in @(Get-ChildItem -LiteralPath $Path -File -Recurse)) {
        $relative = $file.FullName.Substring($root.Length).Replace('\', '/')
        $files.Add($relative, $file)
    }
    [string[]] $relativePaths = @($files.Keys)
    [Array]::Sort($relativePaths, [StringComparer]::Ordinal)
    if ($relativePaths.Count -lt 1) {
        throw [InvalidOperationException]::new('Active module tree is empty.')
    }
    $identity = [Text.StringBuilder]::new()
    $totalBytes = 0L
    foreach ($relative in $relativePaths) {
        $file = $files[$relative]
        $totalBytes += [long] $file.Length
        if ($totalBytes -gt [long] $script:policy.maximumCandidateBytes) {
            throw [InvalidOperationException]::new('Active module tree exceeds its byte limit.')
        }
        $null = $identity.Append($relative).Append([char] 0).Append([long] $file.Length).Append(
            [char] 0
        ).Append((Get-Sha256 -Path $file.FullName)).Append("`n")
    }
    return Get-TextSha256 -Text $identity.ToString()
}

function Get-PropertyNames {
    param([Parameter(Mandatory = $true)] $Value)
    if ($Value -is [Array]) {
        if (@($Value).Count -eq 0) {
            return @()
        }
        throw [InvalidOperationException]::new('JSON map cannot be a non-empty array.')
    }
    [string[]] $names = @()
    foreach ($property in @($Value.PSObject.Properties)) {
        $names += [string] $property.Name
    }
    return @($names)
}

function Assert-JsonMap {
    param([Parameter(Mandatory = $true)] $Value, [Parameter(Mandatory = $true)][string] $Message)
    if ($Value -is [Array]) {
        if (@($Value).Count -eq 0) {
            return
        }
        throw [InvalidOperationException]::new($Message)
    }
    if ($null -eq $Value -or $Value -is [string] -or
        $Value -is [ValueType]) {
        throw [InvalidOperationException]::new($Message)
    }
}

function Assert-ExactProperties {
    param(
        [Parameter(Mandatory = $true)] $Value,
        [Parameter(Mandatory = $true)][string[]] $Expected,
        [Parameter(Mandatory = $true)][string] $Message
    )
    Assert-JsonMap -Value $Value -Message $Message
    [string[]] $actual = @(Get-PropertyNames -Value $Value)
    [string[]] $wanted = @($Expected)
    [Array]::Sort($actual, [StringComparer]::Ordinal)
    [Array]::Sort($wanted, [StringComparer]::Ordinal)
    if ($actual.Count -ne $wanted.Count) {
        throw [InvalidOperationException]::new($Message)
    }
    for ($index = 0; $index -lt $actual.Count; $index++) {
        if ($actual[$index] -cne $wanted[$index]) {
            throw [InvalidOperationException]::new($Message)
        }
    }
}

function Test-NonNegativeInteger {
    param([Parameter(Mandatory = $true)] $Value)
    if ($Value -isnot [byte] -and $Value -isnot [sbyte] -and
        $Value -isnot [int16] -and $Value -isnot [uint16] -and
        $Value -isnot [int32] -and $Value -isnot [uint32] -and
        $Value -isnot [int64]) {
        return $false
    }
    return [long] $Value -ge 0
}

function Assert-MissState {
    param(
        [Parameter(Mandatory = $true)] $Store,
        [Parameter(Mandatory = $true)][ValidateSet(1, 2)][int] $Format
    )
    Assert-ExactProperties -Value $Store -Expected @('version', 'selections') `
        -Message 'Miss-state root contract is invalid.'
    if (-not (Test-NonNegativeInteger -Value $Store.version) -or [int] $Store.version -ne $Format) {
        throw [InvalidOperationException]::new('Miss-state format is invalid.')
    }
    Assert-JsonMap -Value $Store.selections -Message 'Miss-state selections map is invalid.'
    $selectionProperties = @(Get-PropertyNames -Value $Store.selections)
    if ($selectionProperties.Count -gt 16) {
        throw [InvalidOperationException]::new('Miss-state selection limit is exceeded.')
    }
    $stateProperties = @(
        'selectionFingerprint',
        'upstreamRequests',
        'upstreamSuccesses',
        'upstreamBytes',
        'negativeCacheHits',
        'rejectedOutsideAllowlist',
        'budgetRejections',
        'negativeCache'
    )
    if ($Format -eq 2) {
        $stateProperties += 'pendingReservations'
    }
    foreach ($fingerprint in $selectionProperties) {
        if ($fingerprint -cnotmatch '^[a-f0-9]{64}$') {
            throw [InvalidOperationException]::new('Miss-state selection fingerprint is invalid.')
        }
        $entry = $Store.selections.PSObject.Properties[$fingerprint].Value
        Assert-ExactProperties -Value $entry -Expected @('updatedAt', 'state') `
            -Message 'Miss-state selection entry is invalid.'
        if (-not (Test-NonNegativeInteger -Value $entry.updatedAt)) {
            throw [InvalidOperationException]::new('Miss-state update timestamp is invalid.')
        }
        Assert-ExactProperties -Value $entry.state -Expected $stateProperties `
            -Message 'Miss-state resolver state is invalid.'
        if ([string] $entry.state.selectionFingerprint -cne $fingerprint) {
            throw [InvalidOperationException]::new('Miss-state resolver ownership differs.')
        }
        foreach ($counter in @(
            'upstreamRequests',
            'upstreamSuccesses',
            'upstreamBytes',
            'negativeCacheHits',
            'rejectedOutsideAllowlist',
            'budgetRejections'
        )) {
            if (-not (Test-NonNegativeInteger -Value $entry.state.$counter)) {
                throw [InvalidOperationException]::new('Miss-state counter is invalid.')
            }
        }
        Assert-JsonMap -Value $entry.state.negativeCache -Message 'Miss-state negative cache is invalid.'
        $negativeProperties = @(Get-PropertyNames -Value $entry.state.negativeCache)
        if ($negativeProperties.Count -gt 256) {
            throw [InvalidOperationException]::new('Miss-state negative-cache limit is exceeded.')
        }
        foreach ($negativeKey in $negativeProperties) {
            $expiresAt = $entry.state.negativeCache.PSObject.Properties[$negativeKey].Value
            if ($negativeKey -cnotmatch '^[a-f0-9]{64}$' -or
                -not (Test-NonNegativeInteger -Value $expiresAt)) {
                throw [InvalidOperationException]::new('Miss-state negative-cache entry is invalid.')
            }
        }
        if ($Format -eq 2) {
            Assert-JsonMap -Value $entry.state.pendingReservations `
                -Message 'Adopted pending-reservations map is invalid.'
            if ((@(Get-PropertyNames -Value $entry.state.pendingReservations)).Count -ne 0) {
                throw [InvalidOperationException]::new('Adopted pending-reservations map is not empty.')
            }
        }
    }
}

function Get-SemanticStateSha256 {
    param([Parameter(Mandatory = $true)] $Store)
    [string[]] $fingerprints = @(Get-PropertyNames -Value $Store.selections)
    [Array]::Sort($fingerprints, [StringComparer]::Ordinal)
    $selections = [ordered]@{}
    foreach ($fingerprint in $fingerprints) {
        $entry = $Store.selections.PSObject.Properties[$fingerprint].Value
        [string[]] $negativeKeys = @(Get-PropertyNames -Value $entry.state.negativeCache)
        [Array]::Sort($negativeKeys, [StringComparer]::Ordinal)
        $negativeCache = [ordered]@{}
        foreach ($negativeKey in $negativeKeys) {
            $negativeCache[$negativeKey] = [long] $entry.state.negativeCache.PSObject.Properties[$negativeKey].Value
        }
        $selections[$fingerprint] = [ordered]@{
            updatedAt = [long] $entry.updatedAt
            selectionFingerprint = [string] $entry.state.selectionFingerprint
            upstreamRequests = [long] $entry.state.upstreamRequests
            upstreamSuccesses = [long] $entry.state.upstreamSuccesses
            upstreamBytes = [long] $entry.state.upstreamBytes
            negativeCacheHits = [long] $entry.state.negativeCacheHits
            rejectedOutsideAllowlist = [long] $entry.state.rejectedOutsideAllowlist
            budgetRejections = [long] $entry.state.budgetRejections
            negativeCache = $negativeCache
        }
    }
    return Get-TextSha256 -Text ($selections | ConvertTo-Json -Depth 12 -Compress)
}

function New-Format2Candidate {
    param([Parameter(Mandatory = $true)] $Source)
    $Source.version = 2
    foreach ($fingerprint in @(Get-PropertyNames -Value $Source.selections)) {
        $entry = $Source.selections.PSObject.Properties[$fingerprint].Value
        $entry.state | Add-Member -MemberType NoteProperty `
            -Name 'pendingReservations' -Value ([pscustomobject]@{})
    }
    Assert-MissState -Store $Source -Format 2
    $json = $Source | ConvertTo-Json -Depth 16 -Compress
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($json)
    if ($bytes.Length -gt [int] $script:contract.maximumStateBytes) {
        [Array]::Clear($bytes, 0, $bytes.Length)
        throw [InvalidOperationException]::new('Format-2 candidate exceeds its byte limit.')
    }
    return ,$bytes
}

function Read-BoundedJson {
    param([Parameter(Mandatory = $true)][string] $Path)
    Assert-RootedLeaf -Path $Path -MaximumBytes ([long] $script:contract.maximumStateBytes)
    return Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
}

function Assert-ZeroActiveLeases {
    $now = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    foreach ($name in @('tileBudget', 'providerBudget')) {
        $root = [string] $script:policy.runtimeStateRoots.$name
        $path = Join-Path $root 'budget.json'
        if (-not (Test-Path -LiteralPath $path)) {
            continue
        }
        $state = Read-BoundedJson -Path $path
        if (-not (Test-NonNegativeInteger -Value $state.version) -or [int] $state.version -ne 1) {
            throw [InvalidOperationException]::new('Request-budget state format is unsupported.')
        }
        Assert-JsonMap -Value $state.clients -Message 'Request-budget clients map is invalid.'
        foreach ($clientKey in @(Get-PropertyNames -Value $state.clients)) {
            $client = $state.clients.PSObject.Properties[$clientKey].Value
            Assert-JsonMap -Value $client.leases -Message 'Request-budget leases map is invalid.'
            foreach ($leaseKey in @(Get-PropertyNames -Value $client.leases)) {
                $expiresAt = $client.leases.PSObject.Properties[$leaseKey].Value
                if (-not (Test-NonNegativeInteger -Value $expiresAt) -or
                    [long] $expiresAt -gt $now) {
                    throw [InvalidOperationException]::new('OwnTracks runtime still has an active request lease.')
                }
            }
        }
    }
}

function Exit-RuntimeQuiescence {
    foreach ($record in @($script:runtimeLocks | Sort-Object order -Descending)) {
        try {
            $record.stream.Unlock(0, 1)
        } catch {
        }
        $record.stream.Dispose()
    }
    $script:runtimeLocks = @()
}

function Enter-RuntimeQuiescence {
    $timer = [Diagnostics.Stopwatch]::StartNew()
    $lockOrder = @($script:contract.quiescence.lockOrder)
    do {
        Exit-RuntimeQuiescence
        try {
            $order = 0
            foreach ($name in $lockOrder) {
                $root = [string] $script:policy.runtimeStateRoots.$name
                Assert-SafeDirectoryTree -Path $root
                Assert-ProtectedAcl -Path $root
                $lockPath = Join-Path $root ([string] $script:policy.runtimeLockFiles.$name)
                Assert-RootedLeaf -Path $lockPath -MaximumBytes 1048576
                Assert-ProtectedAcl -Path $lockPath
                $stream = [IO.File]::Open(
                    $lockPath,
                    [IO.FileMode]::Open,
                    [IO.FileAccess]::ReadWrite,
                    [IO.FileShare]::ReadWrite
                )
                try {
                    $stream.Lock(0, 1)
                } catch {
                    $stream.Dispose()
                    throw
                }
                $script:runtimeLocks += [pscustomobject]@{ order = $order; stream = $stream }
                $order++
            }
            Assert-ZeroActiveLeases
            return
        } catch {
            Exit-RuntimeQuiescence
            if ($timer.Elapsed.TotalSeconds -ge [int] $script:policy.quiescenceTimeoutSeconds) {
                throw [InvalidOperationException]::new('OwnTracks runtime did not reach bounded quiescence.')
            }
            [Threading.Thread]::Sleep([int] $script:policy.healthPollMilliseconds)
        }
    } while ($true)
}

function Assert-Inputs {
    foreach ($path in @($AdapterPolicyPath, $AdoptionContractPath, $AdoptionPlanPath)) {
        Assert-RootedLeaf -Path $path -MaximumBytes 1048576
    }
    $script:policy = Get-Content -LiteralPath $AdapterPolicyPath -Raw | ConvertFrom-Json
    $script:contract = Get-Content -LiteralPath $AdoptionContractPath -Raw | ConvertFrom-Json
    $script:plan = Get-Content -LiteralPath $AdoptionPlanPath -Raw | ConvertFrom-Json
    Assert-ProtectedAcl -Path $AdapterPolicyPath
    Assert-ProtectedAcl -Path $AdoptionContractPath
    Assert-ProtectedAcl -Path $AdoptionPlanPath
    $statusRoot = Split-Path -Parent $StatusPath
    if (-not [IO.Path]::IsPathRooted($StatusPath) -or
        [string]::IsNullOrWhiteSpace($statusRoot) -or
        -not (Test-Path -LiteralPath $statusRoot -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Private status directory is missing.')
    }
    Assert-SafeDirectoryTree -Path $statusRoot
    Assert-ProtectedAcl -Path $statusRoot
    if (Test-Path -LiteralPath $StatusPath) {
        Assert-RootedLeaf -Path $StatusPath -MaximumBytes 1048576
        Assert-ProtectedAcl -Path $StatusPath
    }
    $preserve = @(
        'selectionFingerprint', 'updatedAt', 'upstreamRequests', 'upstreamSuccesses',
        'upstreamBytes', 'negativeCacheHits', 'rejectedOutsideAllowlist',
        'budgetRejections', 'negativeCache'
    ) -join ','
    if ($script:policy.formatVersion -ne 1 -or
        [string] $script:policy.adapterProfile -ne 'saef-owntracks-position-map-v1' -or
        [string] $script:policy.targetId -ne 'saef-owntracks-position-map' -or
        $script:contract.formatVersion -ne 1 -or
        [string] $script:contract.adapterProfile -ne 'saef-owntracks-position-map-miss-state-adoption-v1' -or
        [string] $script:contract.targetId -ne 'saef-owntracks-position-map' -or
        [int] $script:contract.sourceFormat -ne 1 -or
        [int] $script:contract.candidateFormat -ne 2 -or
        [int] $script:contract.maximumStateBytes -ne 262144 -or
        [string] $script:contract.runtimeCompatibility.relativePath -ne
            'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileMissStateStore.php' -or
        [string] $script:contract.runtimeCompatibility.sha256 -ne
            $ExpectedRuntimeCompatibilitySha256 -or
        [string] $script:contract.quiescence.writerModel -ne 'five-runtime-file-locks-and-zero-active-leases' -or
        [string] $script:contract.quiescence.guard -ne 'named-adapter-mutex-plus-runtime-lock-set' -or
        (@($script:contract.quiescence.lockOrder) -join ',') -ne
            'dayCache,providerCache,tileBudget,providerBudget,missState' -or
        (@($script:contract.transformation.preserve) -join ',') -ne $preserve -or
        [string] $script:contract.transformation.initialize -ne 'empty-pending-reservations-map' -or
        [bool] $script:contract.transformation.counterResetAllowed -or
        [bool] $script:contract.transformation.selectionRemovalAllowed -or
        [string] $script:contract.transaction.rootLeafName -ne
            'saef-owntracks-position-map-state-adoption' -or
        [string] $script:contract.transaction.statusLocation -ne 'transaction-root' -or
        [string] $script:contract.transaction.backup -ne 'source-bytes-before-replace' -or
        [string] $script:contract.transaction.switch -ne 'same-volume-atomic-file-replace' -or
        [string] $script:contract.transaction.postcondition -ne 'format-2-hash-and-semantics' -or
        [string] $script:contract.transaction.failureRollback -ne 'byte-exact-source-restore' -or
        [bool] $script:contract.transaction.staleBackupRestoreAllowed -or
        [string] $script:contract.retention.owner -ne 'owntracks-position-map-state-adoption' -or
        [bool] $script:contract.retention.automaticCleanupAllowed -or
        -not [bool] $script:contract.retention.cleanupRequiresSeparateAuthorization) {
        throw [InvalidOperationException]::new('Miss-state adoption contract is unsupported.')
    }
    if ($script:plan.formatVersion -ne 1 -or
        [string] $script:plan.adapterProfile -ne [string] $script:contract.adapterProfile -or
        [string] $script:plan.operation -ne $Operation -or
        -not (Test-HexSha256 -Value ([string] $script:plan.expectedSourceSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:plan.expectedActivePackageIdentitySha256)) -or
        [string] $script:plan.expectedActivePackageIdentitySha256 -ne
            [string] $script:policy.expectedActivePackageIdentitySha256 -or
        [string] $script:policy.mutexName -notmatch '^Global\\SAEF\.[A-Za-z0-9.]{1,96}$' -or
        [int] $script:policy.healthPollMilliseconds -lt 100 -or
        [int] $script:policy.healthPollMilliseconds -gt 5000 -or
        [int] $script:policy.quiescenceTimeoutSeconds -lt 1 -or
        [int] $script:policy.quiescenceTimeoutSeconds -gt 120 -or
        [long] $script:policy.maximumCandidateBytes -lt 1 -or
        [long] $script:policy.maximumCandidateBytes -gt 268435456 -or
        [long] $script:policy.maximumStateBytes -lt [long] $script:contract.maximumStateBytes) {
        throw [InvalidOperationException]::new('Miss-state adoption plan or private policy is invalid.')
    }
    if ($Operation -eq 'preflight') {
        if ([string] $script:plan.confirmation -ne 'preflight-only') {
            throw [InvalidOperationException]::new('Preflight confirmation is invalid.')
        }
    } elseif ([string] $script:plan.confirmation -ne 'adopt-format-1-to-2' -or
        -not (Test-HexSha256 -Value ([string] $script:plan.expectedCandidateSha256)) -or
        [string] $script:plan.expectedCandidateSha256 -eq ('0' * 64)) {
        throw [InvalidOperationException]::new('Adoption candidate is not explicitly approved.')
    }
    if ($Operation -eq 'adopt') {
        $principal = [Security.Principal.WindowsPrincipal]::new(
            [Security.Principal.WindowsIdentity]::GetCurrent()
        )
        if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
            throw [UnauthorizedAccessException]::new('State adoption requires an elevated local administrator.')
        }
    }
    Assert-SafeDirectoryTree -Path ([string] $script:policy.activeModulePath)
    Assert-ProtectedAcl -Path ([string] $script:policy.activeModulePath)
    $activeIdentity = Get-DirectoryPackageIdentity -Path ([string] $script:policy.activeModulePath)
    if ($activeIdentity -ne [string] $script:plan.expectedActivePackageIdentitySha256) {
        throw [InvalidOperationException]::new('Active module package identity differs from the adoption plan.')
    }
    $implementationPath = Join-Path ([string] $script:policy.activeModulePath) `
        ([string] $script:contract.runtimeCompatibility.relativePath)
    Assert-RootedLeaf -Path $implementationPath -MaximumBytes 1048576
    if (-not (Test-HexSha256 -Value ([string] $script:contract.runtimeCompatibility.sha256)) -or
        (Get-Sha256 -Path $implementationPath) -ne
            [string] $script:contract.runtimeCompatibility.sha256) {
        throw [InvalidOperationException]::new('Active runtime does not own the reviewed state migration.')
    }
    [string[]] $rootNames = @('dayCache', 'providerCache', 'tileBudget', 'providerBudget', 'missState')
    [string[]] $canonicalRoots = @()
    foreach ($name in $rootNames) {
        $root = [string] $script:policy.runtimeStateRoots.$name
        $lockName = [string] $script:policy.runtimeLockFiles.$name
        if (-not [IO.Path]::IsPathRooted($root) -or
            $lockName -notmatch '^[A-Za-z0-9.][A-Za-z0-9._-]{0,63}$') {
            throw [InvalidOperationException]::new('Runtime state-root contract is invalid.')
        }
        $canonical = [IO.Path]::GetFullPath($root).TrimEnd([char[]] @('\', '/'))
        if ($canonicalRoots -contains $canonical) {
            throw [InvalidOperationException]::new('Runtime state roots must be unique.')
        }
        $canonicalRoots += $canonical
    }
    $transactionRoot = [IO.Path]::GetFullPath([string] $script:plan.transactionRoot).TrimEnd(
        [char[]] @('\', '/')
    )
    Assert-SafeDirectoryTree -Path $transactionRoot
    Assert-ProtectedAcl -Path $transactionRoot
    if ((Split-Path -Leaf $transactionRoot) -cne [string] $script:contract.transaction.rootLeafName) {
        throw [InvalidOperationException]::new('State-adoption transaction root has the wrong owner name.')
    }
    foreach ($protectedRoot in @([string] $script:policy.activeModulePath) +
        @($canonicalRoots) + @([string] $script:policy.adapterStateRoot)) {
        if (Test-PathOverlap -Left $transactionRoot -Right $protectedRoot) {
            throw [InvalidOperationException]::new('State-adoption transaction root overlaps a protected owner.')
        }
    }
    $canonicalStatusRoot = [IO.Path]::GetFullPath($statusRoot).TrimEnd([char[]] @('\', '/'))
    if (-not $canonicalStatusRoot.Equals($transactionRoot, [StringComparison]::OrdinalIgnoreCase)) {
        throw [InvalidOperationException]::new('State-adoption status must stay in its transaction root.')
    }
    $missStateRoot = [string] $script:policy.runtimeStateRoots.missState
    if ([IO.Path]::GetPathRoot($transactionRoot) -ne [IO.Path]::GetPathRoot($missStateRoot)) {
        throw [InvalidOperationException]::new('State adoption and miss-state must share one volume.')
    }
}

function Read-AndPrepareState {
    $script:liveStateReadAttempted = $true
    $statePath = Join-Path ([string] $script:policy.runtimeStateRoots.missState) 'state.json'
    Assert-RootedLeaf -Path $statePath -MaximumBytes ([long] $script:contract.maximumStateBytes)
    Assert-ProtectedAcl -Path $statePath
    $script:sourceBytes = [IO.File]::ReadAllBytes($statePath)
    $script:sourceSha256 = Get-BytesSha256 -Bytes $script:sourceBytes
    if ($script:sourceSha256 -ne [string] $script:plan.expectedSourceSha256) {
        throw [InvalidOperationException]::new('Miss-state source changed after private approval.')
    }
    $sourceText = [Text.UTF8Encoding]::new($false, $true).GetString($script:sourceBytes)
    $source = $sourceText | ConvertFrom-Json
    Assert-MissState -Store $source -Format 1
    $script:selectionCount = (@(Get-PropertyNames -Value $source.selections)).Count
    $script:semanticSha256 = Get-SemanticStateSha256 -Store $source
    $script:candidateBytes = New-Format2Candidate -Source $source
    $script:candidateSha256 = Get-BytesSha256 -Bytes $script:candidateBytes
    $candidateText = [Text.UTF8Encoding]::new($false, $true).GetString($script:candidateBytes)
    $candidate = $candidateText | ConvertFrom-Json
    Assert-MissState -Store $candidate -Format 2
    if ((Get-SemanticStateSha256 -Store $candidate) -ne $script:semanticSha256) {
        throw [InvalidOperationException]::new('Miss-state semantics changed during preparation.')
    }
    if ($Operation -eq 'adopt' -and
        $script:candidateSha256 -ne [string] $script:plan.expectedCandidateSha256) {
        throw [InvalidOperationException]::new('Miss-state candidate changed after preflight approval.')
    }
}

function New-AdoptionTransaction {
    $name = 'saef-owntracks-state-adoption-' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' +
        [Guid]::NewGuid().ToString('N').Substring(0, 8)
    $script:transactionPath = Join-Path ([string] $script:plan.transactionRoot) $name
    if (Test-Path -LiteralPath $script:transactionPath) {
        throw [InvalidOperationException]::new('State-adoption transaction already exists.')
    }
    [IO.Directory]::CreateDirectory($script:transactionPath) | Out-Null
    Assert-ProtectedAcl -Path $script:transactionPath
    $script:transactionCreatedUtc = [DateTime]::UtcNow.ToString('o')
    $script:backupPath = Join-Path $script:transactionPath 'source-v1.json'
    $candidatePath = Join-Path $script:transactionPath 'candidate-v2.json'
    [IO.File]::WriteAllBytes($script:backupPath, $script:sourceBytes)
    [IO.File]::WriteAllBytes($candidatePath, $script:candidateBytes)
    Assert-ProtectedAcl -Path $script:backupPath
    Assert-ProtectedAcl -Path $candidatePath
    if ((Get-Sha256 -Path $script:backupPath) -ne $script:sourceSha256 -or
        (Get-Sha256 -Path $candidatePath) -ne $script:candidateSha256) {
        throw [IO.IOException]::new('State-adoption transaction bytes differ.')
    }
    Write-AtomicJson -Path (Join-Path $script:transactionPath 'transaction.json') -Value ([ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-owntracks-position-map-miss-state-adoption-v1'
        transactionDirectoryName = $name
        createdUtc = $script:transactionCreatedUtc
        completedUtc = ''
        outcome = 'prepared'
        sourceSha256 = $script:sourceSha256
        candidateSha256 = $script:candidateSha256
        semanticSha256 = $script:semanticSha256
        selectionCount = $script:selectionCount
    })
}

function Complete-AdoptionTransaction {
    param([Parameter(Mandatory = $true)][string] $Outcome)
    Write-AtomicJson -Path (Join-Path $script:transactionPath 'transaction.json') -Value ([ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-owntracks-position-map-miss-state-adoption-v1'
        transactionDirectoryName = Split-Path -Leaf $script:transactionPath
        createdUtc = $script:transactionCreatedUtc
        completedUtc = [DateTime]::UtcNow.ToString('o')
        outcome = $Outcome
        sourceSha256 = $script:sourceSha256
        candidateSha256 = $script:candidateSha256
        semanticSha256 = $script:semanticSha256
        selectionCount = $script:selectionCount
    })
}

function Assert-AdoptedState {
    $statePath = Join-Path ([string] $script:policy.runtimeStateRoots.missState) 'state.json'
    Assert-RootedLeaf -Path $statePath -MaximumBytes ([long] $script:contract.maximumStateBytes)
    if ((Get-Sha256 -Path $statePath) -ne $script:candidateSha256) {
        throw [InvalidOperationException]::new('Adopted miss-state hash differs.')
    }
    $candidate = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    Assert-MissState -Store $candidate -Format 2
    if ((Get-SemanticStateSha256 -Store $candidate) -ne $script:semanticSha256) {
        throw [InvalidOperationException]::new('Adopted miss-state semantics differ.')
    }
    Assert-ZeroActiveLeases
}

function Invoke-AdoptionRollback {
    $script:rollbackAttempted = $true
    try {
        Assert-RootedLeaf -Path $script:backupPath -MaximumBytes ([long] $script:contract.maximumStateBytes)
        if ((Get-Sha256 -Path $script:backupPath) -ne $script:sourceSha256) {
            throw [InvalidOperationException]::new('State-adoption rollback backup differs.')
        }
        $rollbackBytes = [IO.File]::ReadAllBytes($script:backupPath)
        try {
            $statePath = Join-Path ([string] $script:policy.runtimeStateRoots.missState) 'state.json'
            Write-AtomicBytes -Path $statePath -Bytes $rollbackBytes
            if ((Get-Sha256 -Path $statePath) -ne $script:sourceSha256) {
                throw [InvalidOperationException]::new('Byte-exact state rollback failed.')
            }
            $restored = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            Assert-MissState -Store $restored -Format 1
            if ((Get-SemanticStateSha256 -Store $restored) -ne $script:semanticSha256) {
                throw [InvalidOperationException]::new('Restored miss-state semantics differ.')
            }
        } finally {
            [Array]::Clear($rollbackBytes, 0, $rollbackBytes.Length)
        }
        $script:rollbackSucceeded = $true
        Complete-AdoptionTransaction -Outcome 'rolled_back'
    } catch {
        $script:rollbackSucceeded = $false
    }
}

try {
    $script:failureCode = 'contract'
    Assert-Inputs
    $script:mutex = [Threading.Mutex]::new($false, [string] $script:policy.mutexName)
    $script:mutexAcquired = $script:mutex.WaitOne(0)
    if (-not $script:mutexAcquired) {
        throw [InvalidOperationException]::new('Another OwnTracksPositionMap adapter operation is active.')
    }
    $script:failureCode = 'quiescence'
    Enter-RuntimeQuiescence
    $script:failureCode = 'state_validation'
    Read-AndPrepareState
    if ($Operation -eq 'preflight') {
        $script:failureCode = 'none'
        Write-AdoptionStatus -Outcome 'passed' -ExitCode $ExitSuccess
        $script:finalExitCode = $ExitSuccess
    } else {
        $script:failureCode = 'backup_preparation'
        New-AdoptionTransaction
        $script:failureCode = 'state_replace'
        $script:stateMutationAttempted = $true
        $statePath = Join-Path ([string] $script:policy.runtimeStateRoots.missState) 'state.json'
        Write-AtomicBytes -Path $statePath -Bytes $script:candidateBytes
        $script:failureCode = 'postcondition'
        Assert-AdoptedState
        Complete-AdoptionTransaction -Outcome 'adopted'
        $script:failureCode = 'none'
        Write-AdoptionStatus -Outcome 'adopted' -ExitCode $ExitSuccess
        $script:finalExitCode = $ExitSuccess
    }
} catch {
    $originalFailureCode = $script:failureCode
    if ($Operation -eq 'adopt' -and $script:stateMutationAttempted -and
        $null -ne $script:transactionPath) {
        Invoke-AdoptionRollback
        $script:failureCode = $originalFailureCode
        if ($script:rollbackSucceeded) {
            Write-AdoptionStatus -Outcome 'rolled_back' -ExitCode $ExitRolledBack
            $script:finalExitCode = $ExitRolledBack
        } else {
            Write-AdoptionStatus -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery
            $script:finalExitCode = $ExitManualRecovery
        }
    } else {
        Write-AdoptionStatus -Outcome 'failed' -ExitCode $ExitPreflightFailed
        $script:finalExitCode = $ExitPreflightFailed
    }
} finally {
    Exit-RuntimeQuiescence
    if ($script:mutexAcquired -and $null -ne $script:mutex) {
        $script:mutex.ReleaseMutex()
    }
    if ($null -ne $script:mutex) {
        $script:mutex.Dispose()
    }
    if ($null -ne $script:sourceBytes) {
        [Array]::Clear($script:sourceBytes, 0, $script:sourceBytes.Length)
    }
    if ($null -ne $script:candidateBytes) {
        [Array]::Clear($script:candidateBytes, 0, $script:candidateBytes.Length)
    }
}

exit $script:finalExitCode
