[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('plan', 'apply')]
    [string] $Operation,

    [Parameter(Mandatory = $true)]
    [string] $AdapterPolicyPath,

    [Parameter(Mandatory = $true)]
    [string] $RetentionPlanPath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$ExitSuccess = 0
$ExitFailed = 10
$script:mutex = $null
$script:mutexAcquired = $false

function Write-AtomicJson {
    param([string] $Path, $Value)
    $directory = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Retention status directory is missing.')
    }
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-owntracks-position-map-retention-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-position-map-retention-' + $token + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            (($Value | ConvertTo-Json -Depth 10) + [Environment]::NewLine),
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

function Get-TextSha256 {
    param([string] $Text)
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    try {
        return ([Security.Cryptography.SHA256]::Create().ComputeHash($bytes) |
            ForEach-Object { $_.ToString('x2') }) -join ''
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Assert-SafeTree {
    param([string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Adapter state root is missing.')
    }
    foreach ($entry in @((Get-Item -LiteralPath $Path)) + @(Get-ChildItem -LiteralPath $Path -Recurse -Force)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [InvalidOperationException]::new('Adapter state contains a reparse point.')
        }
    }
}

function Get-DirectoryBytes {
    param([string] $Path)
    $total = 0L
    foreach ($file in @(Get-ChildItem -LiteralPath $Path -File -Recurse -Force)) {
        $total += [long] $file.Length
    }
    return $total
}

function Get-OrdinalSortedFileSystemInfos {
    param([Parameter(Mandatory = $true)][object[]] $Values)
    $result = [object[]] @($Values)
    [Array]::Sort($result, [Comparison[object]] {
        param($left, $right)
        return [string]::CompareOrdinal([string] $left.Name, [string] $right.Name)
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
        throw [InvalidOperationException]::new('Transaction timestamp is not exact round-trip format.')
    }
    return $parsed.ToUniversalTime()
}

function Get-OrdinalSortedRecords {
    param(
        [Parameter(Mandatory = $true)][object[]] $Values,
        [Parameter(Mandatory = $true)][ValidateSet('artifactId', 'completedUtc')][string] $Property,
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

function Read-Inventory {
    param($Policy)
    $stateRoot = [string] $Policy.adapterStateRoot
    Assert-SafeTree -Path $stateRoot
    $activeTransaction = ''
    $activePath = Join-Path $stateRoot 'active.json'
    if (Test-Path -LiteralPath $activePath -PathType Leaf) {
        $active = Get-Content -LiteralPath $activePath -Raw | ConvertFrom-Json
        if ($active.formatVersion -ne 1 -or
            [string] $active.adapterProfile -ne 'saef-owntracks-position-map-v1' -or
            [string] $active.transactionDirectoryName -notmatch '^saef-[a-z0-9.-]+-[0-9]{8}T[0-9]{6}Z$') {
            throw [InvalidOperationException]::new('Active adapter state is invalid.')
        }
        $activeTransaction = [string] $active.transactionDirectoryName
    }
    $records = @()
    $directories = @(Get-OrdinalSortedFileSystemInfos `
        -Values @(Get-ChildItem -LiteralPath $stateRoot -Directory -Force))
    foreach ($directory in $directories) {
        if ($directory.Name -notmatch '^saef-[a-z0-9.-]+-[0-9]{8}T[0-9]{6}Z$') {
            throw [InvalidOperationException]::new('Adapter state contains an unexpected directory.')
        }
        $transactionPath = Join-Path $directory.FullName 'transaction.json'
        if (-not (Test-Path -LiteralPath $transactionPath -PathType Leaf) -or
            (Get-Item -LiteralPath $transactionPath).Length -gt [int] $Policy.maximumStateBytes) {
            throw [InvalidOperationException]::new('Transaction record is missing or oversized.')
        }
        $transaction = Get-Content -LiteralPath $transactionPath -Raw | ConvertFrom-Json
        if ($transaction.formatVersion -ne 1 -or
            [string] $transaction.adapterProfile -ne 'saef-owntracks-position-map-v1' -or
            [string] $transaction.transactionDirectoryName -ne $directory.Name -or
            [string] $transaction.outcome -notin @('activated', 'rolled_back', 'manual_recovery_required') -or
            [string] $transaction.packageIdentitySha256 -notmatch '^[a-f0-9]{64}$') {
            throw [InvalidOperationException]::new('Transaction record contract is invalid.')
        }
        $completed = ConvertFrom-RoundtripUtcTimestamp -Value ([string] $transaction.completedUtc)
        $records += [pscustomobject]@{
            artifactId = $directory.Name
            outcome = [string] $transaction.outcome
            completedUtc = $completed.ToString('o')
            ageHours = [Math]::Floor(([DateTime]::UtcNow - $completed).TotalHours)
            bytes = Get-DirectoryBytes -Path $directory.FullName
            protected = ($directory.Name -eq $activeTransaction -or
                [string] $transaction.outcome -eq 'manual_recovery_required')
        }
    }
    if ($records.Count -gt [int] $Policy.retention.maximumArtifactCount) {
        throw [InvalidOperationException]::new('Adapter artifact count exceeds its hard safety bound.')
    }
    return @($records)
}

function Get-EligibleArtifacts {
    param($Policy, [object[]] $Inventory)
    $keepActivated = [int] $Policy.retention.keepSuccessfulRollbackCount
    $keepRolledBack = [int] $Policy.retention.keepFailedCandidateCount
    $minimumAge = [int] $Policy.retention.minimumAgeHours
    $kept = @{}
    foreach ($outcome in @('activated', 'rolled_back')) {
        $limit = if ($outcome -eq 'activated') { $keepActivated } else { $keepRolledBack }
        $matching = @(Get-OrdinalSortedRecords `
            -Values @($Inventory | Where-Object { $_.outcome -eq $outcome }) `
            -Property completedUtc -Descending)
        for ($index = 0; $index -lt [Math]::Min($limit, $matching.Count); $index++) {
            $kept[[string] $matching[$index].artifactId] = $true
        }
    }
    $eligible = @($Inventory | Where-Object {
        -not [bool] $_.protected -and
        -not $kept.ContainsKey([string] $_.artifactId) -and
        [long] $_.ageHours -ge $minimumAge
    })
    return @(Get-OrdinalSortedRecords -Values $eligible -Property artifactId)
}

$outcome = 'failed'
$exitCode = $ExitFailed
$details = [ordered]@{}
try {
    foreach ($path in @($AdapterPolicyPath, $RetentionPlanPath)) {
        if (-not [IO.Path]::IsPathRooted($path) -or -not (Test-Path -LiteralPath $path -PathType Leaf) -or
            (Get-Item -LiteralPath $path).Length -gt 1048576 -or
            (((Get-Item -LiteralPath $path).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
            throw [IO.FileNotFoundException]::new('Retention input is missing or unsafe.')
        }
    }
    $policy = Get-Content -LiteralPath $AdapterPolicyPath -Raw | ConvertFrom-Json
    $plan = Get-Content -LiteralPath $RetentionPlanPath -Raw | ConvertFrom-Json
    if ($Operation -eq 'apply') {
        throw [InvalidOperationException]::new(
            'Legacy adapter-only retention apply is retired; use the cross-root retention consumer.'
        )
    }
    if ($policy.formatVersion -ne 1 -or $plan.formatVersion -ne 1 -or
        [string] $policy.adapterProfile -ne 'saef-owntracks-position-map-v1' -or
        [string] $plan.adapterProfile -ne 'saef-owntracks-position-map-v1' -or
        [string] $plan.mode -ne $Operation -or
        [int] $policy.retention.minimumAgeHours -lt 24 -or
        [int] $policy.retention.keepSuccessfulRollbackCount -lt 1 -or
        [int] $policy.retention.keepFailedCandidateCount -lt 1 -or
        [int] $policy.retention.maximumArtifactCount -lt 4 -or
        [int] $policy.retention.maximumArtifactCount -gt 64) {
        throw [InvalidOperationException]::new('Retention policy or plan contract is invalid.')
    }
    $script:mutex = [Threading.Mutex]::new($false, [string] $policy.mutexName)
    $script:mutexAcquired = $script:mutex.WaitOne(0)
    if (-not $script:mutexAcquired) {
        throw [InvalidOperationException]::new('Another OwnTracksPositionMap adapter operation is active.')
    }
    $inventory = @(Read-Inventory -Policy $policy)
    $inventoryJson = @($inventory | Select-Object artifactId, outcome, completedUtc, bytes, protected) |
        ConvertTo-Json -Depth 5 -Compress
    $inventorySha256 = Get-TextSha256 -Text $inventoryJson
    $eligible = @(Get-EligibleArtifacts -Policy $policy -Inventory $inventory)
    $details = [ordered]@{
        inventorySha256 = $inventorySha256
        inventory = @($inventory)
        eligibleArtifactIds = @($eligible | ForEach-Object { [string] $_.artifactId })
        deletedArtifactIds = @()
    }
    $outcome = 'planned'
    $exitCode = $ExitSuccess
} catch {
    $details = [ordered]@{ failureCode = 'retention_contract' }
} finally {
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        operation = $Operation
        adapterProfile = 'saef-owntracks-position-map-v1'
        outcome = $outcome
        exitCode = $exitCode
        details = $details
    }
    Write-AtomicJson -Path $StatusPath -Value $status
    if ($script:mutexAcquired -and $null -ne $script:mutex) { $script:mutex.ReleaseMutex() }
    if ($null -ne $script:mutex) { $script:mutex.Dispose() }
}
exit $exitCode
