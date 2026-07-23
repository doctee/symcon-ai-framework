[CmdletBinding()]
param(
    [Parameter()]
    [string] $PolicyPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($PolicyPath)) {
    $PolicyPath = Join-Path $PSScriptRoot 'deployment-channel.local.json'
}

$ExitSuccess = 0
$ExitRequestRejected = 10
$ExitStageFailed = 20
$ExitPreflightFailed = 30
$ExitActivationFailed = 40
$ExitManualRecovery = 50
$ChannelVersion = 7
$ChannelMutexName = 'Global\SAEF.DeploymentChannel'
$UploadChunkBytes = 4096
$script:failureCode = 'request'

function Test-HexSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -match '^[a-f0-9]{64}$'
}

function Assert-RuntimeHealthContract {
    param([Parameter(Mandatory = $true)] $RuntimeHealth)

    $requiredFunctions = @($RuntimeHealth.requiredFunctions)
    if ($requiredFunctions.Count -lt 1 -or $requiredFunctions.Count -gt 256) {
        throw [System.InvalidOperationException]::new('Runtime health function list is outside policy.')
    }
    $previous = $null
    foreach ($function in $requiredFunctions) {
        $name = [string] $function
        if ($function -isnot [string] -or $name -notmatch '^[A-Za-z_][A-Za-z0-9_]{0,127}$' -or
            ($null -ne $previous -and [string]::CompareOrdinal($previous, $name) -ge 0)) {
            throw [System.InvalidOperationException]::new('Runtime health function contract is invalid.')
        }
        $previous = $name
    }
    return $requiredFunctions
}

function Test-SafeIdentifier {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -match '^saef-[a-z0-9][a-z0-9.-]{0,63}$'
}

function Test-SafeRelativePath {
    param([Parameter(Mandatory = $true)][string] $Value)

    if ([string]::IsNullOrWhiteSpace($Value) -or [IO.Path]::IsPathRooted($Value)) {
        return $false
    }
    if ($Value.Contains('\') -or $Value.Contains(':') -or $Value.StartsWith('/')) {
        return $false
    }
    foreach ($segment in $Value.Split('/')) {
        if ([string]::IsNullOrWhiteSpace($segment) -or $segment -eq '.' -or $segment -eq '..') {
            return $false
        }
    }
    return $true
}

function Enter-ChannelMutex {
    $mutex = [Threading.Mutex]::new($false, $ChannelMutexName)
    $acquired = $false
    try {
        try {
            $acquired = $mutex.WaitOne(0)
        } catch [Threading.AbandonedMutexException] {
            $acquired = $true
        }
        if (-not $acquired) {
            throw [System.InvalidOperationException]::new('Another deployment operation is active.')
        }
        return $mutex
    } catch {
        if (-not $acquired) {
            $mutex.Dispose()
        }
        throw
    }
}

function Get-FullPathInsideRoot {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][string] $RelativePath
    )

    if (-not (Test-SafeRelativePath -Value $RelativePath)) {
        throw [System.InvalidOperationException]::new('Relative path is invalid.')
    }
    $rootPath = [IO.Path]::GetFullPath($Root).TrimEnd([char[]] @('\', '/')) + [IO.Path]::DirectorySeparatorChar
    $candidate = [IO.Path]::GetFullPath((Join-Path $rootPath ($RelativePath.Replace('/', [IO.Path]::DirectorySeparatorChar))))
    if (-not $candidate.StartsWith($rootPath, [StringComparison]::OrdinalIgnoreCase)) {
        throw [System.InvalidOperationException]::new('Resolved path leaves its configured root.')
    }
    return $candidate
}

function Test-PathInsideRoot {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][string] $Path
    )

    $rootPath = [IO.Path]::GetFullPath($Root).TrimEnd([char[]] @('\', '/')) + [IO.Path]::DirectorySeparatorChar
    $candidate = [IO.Path]::GetFullPath($Path)
    return $candidate.StartsWith($rootPath, [StringComparison]::OrdinalIgnoreCase)
}

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

function Read-BoundedStreamBytes {
    param(
        [Parameter(Mandatory = $true)][IO.Stream] $Stream,
        [Parameter(Mandatory = $true)][long] $ExpectedBytes,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )

    if ($ExpectedBytes -lt 0 -or $ExpectedBytes -gt $MaximumBytes) {
        throw [System.InvalidOperationException]::new('Stream length is outside its byte limit.')
    }
    $memory = [IO.MemoryStream]::new()
    try {
        $buffer = [byte[]]::new(65536)
        $total = 0L
        while (($read = $Stream.Read($buffer, 0, $buffer.Length)) -gt 0) {
            $total += $read
            if ($total -gt $ExpectedBytes -or $total -gt $MaximumBytes) {
                throw [System.InvalidOperationException]::new('Stream exceeded its declared byte length.')
            }
            $memory.Write($buffer, 0, $read)
        }
        if ($total -ne $ExpectedBytes) {
            throw [System.InvalidOperationException]::new('Stream length differs from its declared byte length.')
        }
        return $memory.ToArray()
    } finally {
        $memory.Dispose()
    }
}

function Get-BoundedStreamSha256 {
    param(
        [Parameter(Mandatory = $true)][IO.Stream] $Stream,
        [Parameter(Mandatory = $true)][long] $ExpectedBytes,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )

    if ($ExpectedBytes -lt 0 -or $ExpectedBytes -gt $MaximumBytes) {
        throw [System.InvalidOperationException]::new('Stream length is outside its byte limit.')
    }
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        $buffer = [byte[]]::new(65536)
        $total = 0L
        while (($read = $Stream.Read($buffer, 0, $buffer.Length)) -gt 0) {
            $total += $read
            if ($total -gt $ExpectedBytes -or $total -gt $MaximumBytes) {
                throw [System.InvalidOperationException]::new('Stream exceeded its declared byte length.')
            }
            $null = $algorithm.TransformBlock($buffer, 0, $read, $buffer, 0)
        }
        if ($total -ne $ExpectedBytes) {
            throw [System.InvalidOperationException]::new('Stream length differs from its declared byte length.')
        }
        $null = $algorithm.TransformFinalBlock([byte[]]::new(0), 0, 0)
        return ([BitConverter]::ToString($algorithm.Hash)).Replace('-', '').ToLowerInvariant()
    } finally {
        $algorithm.Dispose()
    }
}

function Copy-BoundedStream {
    param(
        [Parameter(Mandatory = $true)][IO.Stream] $Source,
        [Parameter(Mandatory = $true)][IO.Stream] $Destination,
        [Parameter(Mandatory = $true)][long] $ExpectedBytes,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )

    if ($ExpectedBytes -lt 0 -or $ExpectedBytes -gt $MaximumBytes) {
        throw [System.InvalidOperationException]::new('Stream length is outside its byte limit.')
    }
    $buffer = [byte[]]::new(65536)
    $total = 0L
    while (($read = $Source.Read($buffer, 0, $buffer.Length)) -gt 0) {
        $total += $read
        if ($total -gt $ExpectedBytes -or $total -gt $MaximumBytes) {
            throw [System.InvalidOperationException]::new('Stream exceeded its declared byte length.')
        }
        $Destination.Write($buffer, 0, $read)
    }
    if ($total -ne $ExpectedBytes) {
        throw [System.InvalidOperationException]::new('Stream length differs from its declared byte length.')
    }
}

function Write-AtomicText {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $Text
    )

    $directory = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [System.IO.DirectoryNotFoundException]::new('Atomic target directory is missing.')
    }
    $identifier = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-write-' + $identifier + '.tmp')
    $backup = Join-Path $directory ('.saef-write-' + $identifier + '.bak')
    try {
        [IO.File]::WriteAllText($temporary, $Text, [Text.UTF8Encoding]::new($false))
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

function Write-AtomicBytes {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][byte[]] $Bytes
    )

    $directory = Split-Path -Parent $Path
    $identifier = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-bytes-' + $identifier + '.tmp')
    $backup = Join-Path $directory ('.saef-bytes-' + $identifier + '.bak')
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

function Write-JsonResponse {
    param(
        [Parameter(Mandatory = $true)][bool] $Success,
        [Parameter(Mandatory = $true)][string] $Operation,
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][hashtable] $Details = @{}
    )

    $response = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        success = $Success
        operation = $Operation
        outcome = $Outcome
        exitCode = $ExitCode
    }
    foreach ($key in $Details.Keys) {
        $response[$key] = $Details[$key]
    }
    [Console]::Out.WriteLine(($response | ConvertTo-Json -Depth 6 -Compress))
}

function Read-ChannelPolicy {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Deployment channel policy is missing.')
    }
    $policy = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($policy.formatVersion -ne 1) {
        throw [System.InvalidOperationException]::new('Unsupported deployment channel policy format.')
    }
    foreach ($name in @(
        'scriptsRoot',
        'managedFilesetRoot',
        'stateRoot',
        'activeBootstrapRelativePath',
        'restartCoordinatorPath',
        'expectedRestartCoordinatorSha256',
        'restartPolicyPath',
        'expectedRestartPolicySha256',
        'runtimeHealthProbeEnabled',
        'runtimeHealthProbeScriptID',
        'expectedRuntimeHealthProbeSha256',
        'runtimeMirrorEnabled',
        'runtimeMirrorCoordinatorPath',
        'expectedRuntimeMirrorCoordinatorSha256',
        'runtimeMirrorReconcilerPath',
        'expectedRuntimeMirrorReconcilerSha256',
        'runtimeMirrorParentID',
        'runtimeMirrorIdent',
        'runtimeMirrorName',
        'runtimeMirrorPosition',
        'credentialPath',
        'rpcUri',
        'serviceName',
        'maxPackageBytes',
        'maxExpandedBytes',
        'maxFileCount',
        'maxPreflightAgeSeconds',
        'maxDeploymentCount',
        'maxManagedBytes'
    )) {
        if ($null -eq $policy.$name) {
            throw [System.InvalidOperationException]::new('Deployment channel policy is incomplete.')
        }
    }
    $maximumPolicyLimits = @{
        maxPackageBytes = 67108864L
        maxExpandedBytes = 268435456L
        maxFileCount = 1024L
        maxPreflightAgeSeconds = 3600L
        maxDeploymentCount = 64L
        maxManagedBytes = 1073741824L
    }
    foreach ($name in $maximumPolicyLimits.Keys) {
        if ([long] $policy.$name -le 0 -or [long] $policy.$name -gt [long] $maximumPolicyLimits[$name]) {
            throw [System.InvalidOperationException]::new('Deployment channel policy limit is outside its hard bound.')
        }
    }
    foreach ($name in @(
        'scriptsRoot',
        'managedFilesetRoot',
        'stateRoot',
        'restartCoordinatorPath',
        'restartPolicyPath',
        'runtimeMirrorCoordinatorPath',
        'runtimeMirrorReconcilerPath',
        'credentialPath'
    )) {
        if (-not [IO.Path]::IsPathRooted([string] $policy.$name)) {
            throw [System.InvalidOperationException]::new('Deployment channel policy requires absolute local paths.')
        }
    }
    if (-not (Test-PathInsideRoot -Root ([string] $policy.scriptsRoot) -Path ([string] $policy.managedFilesetRoot)) -or
        -not (Test-PathInsideRoot -Root ([string] $policy.scriptsRoot) -Path ([string] $policy.stateRoot))) {
        throw [System.InvalidOperationException]::new('Managed deployment roots must be below the Symcon scripts root.')
    }
    $managedRoot = [IO.Path]::GetFullPath([string] $policy.managedFilesetRoot).TrimEnd([char[]] @('\', '/'))
    $stateRoot = [IO.Path]::GetFullPath([string] $policy.stateRoot).TrimEnd([char[]] @('\', '/'))
    if ($managedRoot.Equals($stateRoot, [StringComparison]::OrdinalIgnoreCase) -or
        (Test-PathInsideRoot -Root $managedRoot -Path $stateRoot) -or
        (Test-PathInsideRoot -Root $stateRoot -Path $managedRoot)) {
        throw [System.InvalidOperationException]::new('Managed fileset and state roots must be disjoint.')
    }
    if (-not (Test-SafeRelativePath -Value ([string] $policy.activeBootstrapRelativePath))) {
        throw [System.InvalidOperationException]::new('Configured bootstrap path must be relative and safe.')
    }
    $configuredBootstrapPath = Get-FullPathInsideRoot -Root ([string] $policy.scriptsRoot) `
        -RelativePath ([string] $policy.activeBootstrapRelativePath)
    if ((Test-PathInsideRoot -Root $managedRoot -Path $configuredBootstrapPath) -or
        (Test-PathInsideRoot -Root $stateRoot -Path $configuredBootstrapPath)) {
        throw [System.InvalidOperationException]::new('Active bootstrap must be outside managed deployment roots.')
    }
    if (-not (Test-HexSha256 -Value ([string] $policy.expectedRestartCoordinatorSha256)) -or
        -not (Test-HexSha256 -Value ([string] $policy.expectedRestartPolicySha256)) -or
        -not (Test-HexSha256 -Value ([string] $policy.expectedRuntimeMirrorCoordinatorSha256)) -or
        -not (Test-HexSha256 -Value ([string] $policy.expectedRuntimeMirrorReconcilerSha256))) {
        throw [System.InvalidOperationException]::new('Pinned coordinator hashes are invalid.')
    }
    if ($policy.runtimeMirrorEnabled -isnot [bool] -or
        [int] $policy.runtimeMirrorParentID -lt 0 -or
        ([bool] $policy.runtimeMirrorEnabled -and [int] $policy.runtimeMirrorParentID -le 0) -or
        [string] $policy.runtimeMirrorIdent -notmatch '^[A-Za-z0-9_]{1,128}$' -or
        [string]::IsNullOrWhiteSpace([string] $policy.runtimeMirrorName) -or
        ([string] $policy.runtimeMirrorName).Length -gt 255) {
        throw [System.InvalidOperationException]::new('Runtime mirror policy is invalid.')
    }
    if ($policy.runtimeHealthProbeEnabled -isnot [bool] -or
        [int] $policy.runtimeHealthProbeScriptID -lt 0 -or
        ([bool] $policy.runtimeHealthProbeEnabled -and [int] $policy.runtimeHealthProbeScriptID -le 0) -or
        -not (Test-HexSha256 -Value ([string] $policy.expectedRuntimeHealthProbeSha256))) {
        throw [System.InvalidOperationException]::new('Runtime health probe policy is invalid.')
    }
    $uri = [Uri] ([string] $policy.rpcUri)
    if ($uri.Scheme -notin @('http', 'https') -or $uri.Host -notin @('127.0.0.1', 'localhost', '::1')) {
        throw [System.InvalidOperationException]::new('RPC URI must use an HTTP loopback endpoint.')
    }
    if ([string] $policy.serviceName -notmatch '^[A-Za-z0-9_.-]{1,64}$') {
        throw [System.InvalidOperationException]::new('Configured service name is invalid.')
    }
    foreach ($directory in @($policy.scriptsRoot, $policy.managedFilesetRoot, $policy.stateRoot)) {
        if (-not (Test-Path -LiteralPath ([string] $directory) -PathType Container)) {
            throw [System.IO.DirectoryNotFoundException]::new('Configured deployment directory is missing.')
        }
    }
    foreach ($file in @(
        $policy.restartCoordinatorPath,
        $policy.restartPolicyPath,
        $policy.runtimeMirrorCoordinatorPath,
        $policy.runtimeMirrorReconcilerPath,
        $policy.credentialPath
    )) {
        if (-not (Test-Path -LiteralPath ([string] $file) -PathType Leaf)) {
            throw [System.IO.FileNotFoundException]::new('Configured deployment dependency is missing.')
        }
    }
    if ((Get-Sha256 -Path ([string] $policy.restartCoordinatorPath)) -ne [string] $policy.expectedRestartCoordinatorSha256 -or
        (Get-Sha256 -Path ([string] $policy.restartPolicyPath)) -ne [string] $policy.expectedRestartPolicySha256 -or
        (Get-Sha256 -Path ([string] $policy.runtimeMirrorCoordinatorPath)) -ne
            [string] $policy.expectedRuntimeMirrorCoordinatorSha256 -or
        (Get-Sha256 -Path ([string] $policy.runtimeMirrorReconcilerPath)) -ne
            [string] $policy.expectedRuntimeMirrorReconcilerSha256) {
        throw [System.InvalidOperationException]::new('Pinned deployment dependency hash mismatch.')
    }
    return $policy
}

function Get-TokenReplacement {
    param(
        [Parameter(Mandatory = $true)][byte[]] $Source,
        [Parameter(Mandatory = $true)][string] $OldToken,
        [Parameter(Mandatory = $true)][string] $NewToken
    )

    if ($OldToken -notmatch '^[\x20-\x7E]+$' -or $NewToken -notmatch '^[\x20-\x7E]+$') {
        throw [System.InvalidOperationException]::new('Bootstrap tokens must be printable ASCII.')
    }
    $oldBytes = [Text.Encoding]::UTF8.GetBytes($OldToken)
    $newBytes = [Text.Encoding]::UTF8.GetBytes($NewToken)
    if ($OldToken -eq $NewToken -or $oldBytes.Length -ne $newBytes.Length) {
        throw [System.InvalidOperationException]::new('Bootstrap tokens must have equal byte length.')
    }
    $matchIndex = -1
    $matchCount = 0
    for ($offset = 0; $offset -le $Source.Length - $oldBytes.Length; $offset++) {
        $matches = $true
        for ($index = 0; $index -lt $oldBytes.Length; $index++) {
            if ($Source[$offset + $index] -ne $oldBytes[$index]) {
                $matches = $false
                break
            }
        }
        if ($matches) {
            $matchCount++
            $matchIndex = $offset
        }
    }
    if ($matchCount -ne 1) {
        throw [System.InvalidOperationException]::new('Bootstrap must contain exactly one active token.')
    }
    $newTokenMatchCount = 0
    for ($offset = 0; $offset -le $Source.Length - $newBytes.Length; $offset++) {
        $matches = $true
        for ($index = 0; $index -lt $newBytes.Length; $index++) {
            if ($Source[$offset + $index] -ne $newBytes[$index]) {
                $matches = $false
                break
            }
        }
        if ($matches) {
            $newTokenMatchCount++
        }
    }
    if ($newTokenMatchCount -ne 0) {
        throw [System.InvalidOperationException]::new('Bootstrap already contains the candidate token.')
    }
    $candidate = [byte[]]::new($Source.Length)
    [Array]::Copy($Source, $candidate, $Source.Length)
    [Array]::Copy($newBytes, 0, $candidate, $matchIndex, $newBytes.Length)
    return $candidate
}

function Get-ManagedFilesetBootstrapToken {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $TargetDirectoryName
    )

    if (-not (Test-SafeIdentifier -Value $TargetDirectoryName)) {
        throw [System.InvalidOperationException]::new('Managed fileset target identity is invalid.')
    }
    $scriptsRoot = [IO.Path]::GetFullPath([string] $Policy.scriptsRoot)
    $scriptsPrefix = $scriptsRoot.TrimEnd([char[]] @('\', '/')) + [IO.Path]::DirectorySeparatorChar
    $targetBootstrap = [IO.Path]::GetFullPath((Join-Path `
        (Join-Path ([string] $Policy.managedFilesetRoot) $TargetDirectoryName) `
        'bootstrap.php'))
    if (-not $targetBootstrap.StartsWith($scriptsPrefix, [StringComparison]::OrdinalIgnoreCase)) {
        throw [System.InvalidOperationException]::new('Managed fileset bootstrap is outside the scripts root.')
    }
    return $targetBootstrap.Substring($scriptsPrefix.Length).Replace('\', '/')
}

function Read-DeploymentManifest {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Staged deployment manifest is missing.')
    }
    $manifestText = Get-Content -LiteralPath $Path -Raw
    $manifest = $manifestText | ConvertFrom-Json
    if ($manifest.formatVersion -ne 1 -or -not (Test-SafeIdentifier -Value ([string] $manifest.deploymentId)) -or
        -not (Test-SafeIdentifier -Value ([string] $manifest.targetDirectoryName))) {
        throw [System.InvalidOperationException]::new('Deployment manifest identity is invalid.')
    }
    foreach ($hashName in @('expectedActiveSha256', 'expectedCandidateSha256')) {
        if (-not (Test-HexSha256 -Value ([string] $manifest.bootstrap.$hashName))) {
            throw [System.InvalidOperationException]::new('Deployment manifest contains an invalid bootstrap hash.')
        }
    }
    if ($manifest.bootstrap.oldToken -isnot [string] -or $manifest.bootstrap.newToken -isnot [string]) {
        throw [System.InvalidOperationException]::new('Deployment manifest bootstrap tokens are invalid.')
    }
    $null = Assert-RuntimeHealthContract -RuntimeHealth $manifest.runtimeHealth
    return [ordered]@{ manifest = $manifest; text = $manifestText }
}

function Get-DeploymentPaths {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $DeploymentId
    )

    if (-not (Test-SafeIdentifier -Value $DeploymentId)) {
        throw [System.InvalidOperationException]::new('Deployment identifier is invalid.')
    }
    $stateDirectory = Join-Path ([string] $Policy.stateRoot) $DeploymentId
    return [ordered]@{
        stateDirectory = $stateDirectory
        manifestPath = Join-Path $stateDirectory 'deployment.json'
        statusPath = Join-Path $stateDirectory 'status.json'
        restartStatusPath = Join-Path $stateDirectory 'restart-status.json'
        mirrorStatusPath = Join-Path $stateDirectory 'runtime-mirror-status.json'
        rollbackPath = Join-Path $stateDirectory 'rollback-bootstrap.bin'
    }
}

function Write-DeploymentStatus {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $DeploymentId,
        [Parameter(Mandatory = $true)][string] $Phase,
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][hashtable] $Details = @{}
    )

    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        deploymentId = $DeploymentId
        phase = $Phase
        outcome = $Outcome
        exitCode = $ExitCode
    }
    foreach ($key in $Details.Keys) {
        $status[$key] = $Details[$key]
    }
    Write-AtomicText -Path $Path -Text (($status | ConvertTo-Json -Depth 6) + [Environment]::NewLine)
    return $status
}

function Assert-StagedDeployment {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $DeploymentId
    )

    $paths = Get-DeploymentPaths -Policy $Policy -DeploymentId $DeploymentId
    $manifestRecord = Read-DeploymentManifest -Path $paths.manifestPath
    $manifest = $manifestRecord.manifest
    if ([string] $manifest.deploymentId -ne $DeploymentId) {
        throw [System.InvalidOperationException]::new('Deployment manifest identity mismatch.')
    }
    $targetDirectory = Join-Path ([string] $Policy.managedFilesetRoot) ([string] $manifest.targetDirectoryName)
    if (-not (Test-Path -LiteralPath $targetDirectory -PathType Container)) {
        throw [System.IO.DirectoryNotFoundException]::new('Staged fileset directory is missing.')
    }
    $expectedNewToken = Get-ManagedFilesetBootstrapToken -Policy $Policy `
        -TargetDirectoryName ([string] $manifest.targetDirectoryName)
    if ([string] $manifest.bootstrap.newToken -ne $expectedNewToken) {
        throw [System.InvalidOperationException]::new('Candidate token does not select the managed fileset bootstrap.')
    }
    $files = @($manifest.files)
    if ($files.Count -lt 1 -or $files.Count -gt [int] $Policy.maxFileCount) {
        throw [System.InvalidOperationException]::new('Staged file count is outside policy.')
    }
    $expectedRelativePaths = @{}
    foreach ($file in $files) {
        $relative = [string] $file.path
        if (-not (Test-SafeRelativePath -Value $relative) -or $relative.StartsWith('fileset/') -eq $false -or
            -not (Test-HexSha256 -Value ([string] $file.sha256)) -or [long] $file.size -lt 0) {
            throw [System.InvalidOperationException]::new('Staged file contract is invalid.')
        }
        $filesetRelative = $relative.Substring('fileset/'.Length)
        if ($expectedRelativePaths.ContainsKey($filesetRelative)) {
            throw [System.InvalidOperationException]::new('Staged file contract contains a duplicate path.')
        }
        $expectedRelativePaths[$filesetRelative] = $true
        $targetPath = Get-FullPathInsideRoot -Root $targetDirectory -RelativePath $filesetRelative
        if (-not (Test-Path -LiteralPath $targetPath -PathType Leaf) -or
            (Get-Item -LiteralPath $targetPath).Length -ne [long] $file.size -or
            (Get-Sha256 -Path $targetPath) -ne [string] $file.sha256) {
            throw [System.InvalidOperationException]::new('Staged file hash or size mismatch.')
        }
    }
    $actualFiles = @(Get-ChildItem -LiteralPath $targetDirectory -File -Recurse)
    if ($actualFiles.Count -ne $expectedRelativePaths.Count) {
        throw [System.InvalidOperationException]::new('Staged fileset has missing or additional files.')
    }
    foreach ($actualFile in $actualFiles) {
        $relative = $actualFile.FullName.Substring($targetDirectory.TrimEnd([char[]] @('\', '/')).Length + 1).Replace('\', '/')
        if (-not $expectedRelativePaths.ContainsKey($relative)) {
            throw [System.InvalidOperationException]::new('Staged fileset contains an unlisted file.')
        }
    }
    $bootstrapPath = Get-FullPathInsideRoot -Root ([string] $Policy.scriptsRoot) -RelativePath ([string] $Policy.activeBootstrapRelativePath)
    if (-not (Test-Path -LiteralPath $bootstrapPath -PathType Leaf) -or
        (Get-Sha256 -Path $bootstrapPath) -ne [string] $manifest.bootstrap.expectedActiveSha256) {
        throw [System.InvalidOperationException]::new('Active bootstrap drift detected.')
    }
    $activeBytes = [IO.File]::ReadAllBytes($bootstrapPath)
    $candidateBytes = Get-TokenReplacement -Source $activeBytes -OldToken ([string] $manifest.bootstrap.oldToken) -NewToken ([string] $manifest.bootstrap.newToken)
    if ((Get-BytesSha256 -Bytes $candidateBytes) -ne [string] $manifest.bootstrap.expectedCandidateSha256) {
        throw [System.InvalidOperationException]::new('Candidate bootstrap hash mismatch.')
    }
    return [ordered]@{
        paths = $paths
        manifest = $manifest
        manifestHash = Get-BytesSha256 -Bytes ([Text.Encoding]::UTF8.GetBytes($manifestRecord.text))
        targetDirectory = $targetDirectory
        bootstrapPath = $bootstrapPath
        activeBytes = $activeBytes
        candidateBytes = $candidateBytes
    }
}

function Invoke-RestartCoordinator {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $StatusPath,
        [Parameter(Mandatory = $true)][string] $ActiveBootstrapPath,
        [Parameter(Mandatory = $true)][string] $ExpectedActiveSha256,
        [Parameter()][switch] $PreflightOnly,
        [Parameter()][string] $RollbackPath,
        [Parameter()][string] $ExpectedRollbackSha256,
        [Parameter()][array] $RequiredRuntimeFunctions
    )

    $powerShellExecutable = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $arguments = @(
        '-NoLogo',
        '-NoProfile',
        '-NonInteractive',
        '-ExecutionPolicy', 'Bypass',
        '-File', [string] $Policy.restartCoordinatorPath,
        '-RpcUri', [string] $Policy.rpcUri,
        '-CredentialPath', [string] $Policy.credentialPath,
        '-PolicyPath', [string] $Policy.restartPolicyPath,
        '-StatusPath', $StatusPath,
        '-ServiceName', [string] $Policy.serviceName,
        '-ActiveBootstrapPath', $ActiveBootstrapPath,
        '-ExpectedActiveBootstrapSha256', $ExpectedActiveSha256
    )
    if ($PreflightOnly) {
        $arguments += '-PreflightOnly'
    } else {
        $arguments += @(
            '-RollbackBootstrapPath', $RollbackPath,
            '-ExpectedRollbackBootstrapSha256', $ExpectedRollbackSha256
        )
    }
    if ($null -ne $RequiredRuntimeFunctions) {
        if (-not [bool] $Policy.runtimeHealthProbeEnabled) {
            throw [System.InvalidOperationException]::new('Runtime health probe is required but not configured.')
        }
        $contractJson = ConvertTo-Json -InputObject @($RequiredRuntimeFunctions) -Compress
        $arguments += @(
            '-RuntimeHealthProbeScriptID', [string] $Policy.runtimeHealthProbeScriptID,
            '-ExpectedRuntimeHealthProbeSha256', [string] $Policy.expectedRuntimeHealthProbeSha256,
            '-RequiredRuntimeFunctionsBase64',
                [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($contractJson))
        )
    }
    & $powerShellExecutable @arguments | Out-Null
    return [int] $LASTEXITCODE
}

function Invoke-RuntimeMirrorCoordinator {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $FilesetPath,
        [Parameter(Mandatory = $true)][string] $StatusPath,
        [Parameter()][switch] $PreflightOnly
    )

    $powerShellExecutable = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $arguments = @(
        '-NoLogo',
        '-NoProfile',
        '-NonInteractive',
        '-ExecutionPolicy', 'Bypass',
        '-File', [string] $Policy.runtimeMirrorCoordinatorPath,
        '-RpcUri', [string] $Policy.rpcUri,
        '-CredentialPath', [string] $Policy.credentialPath,
        '-ReconcilerPath', [string] $Policy.runtimeMirrorReconcilerPath,
        '-ExpectedReconcilerSha256', [string] $Policy.expectedRuntimeMirrorReconcilerSha256,
        '-FilesetPath', $FilesetPath,
        '-ParentID', [string] $Policy.runtimeMirrorParentID,
        '-Ident', [string] $Policy.runtimeMirrorIdent,
        '-Name', [string] $Policy.runtimeMirrorName,
        '-Position', [string] $Policy.runtimeMirrorPosition,
        '-StatePath', (Join-Path ([string] $Policy.stateRoot) 'runtime-source-mirror.local.json'),
        '-StatusPath', $StatusPath
    )
    if ($PreflightOnly) {
        $arguments += '-PreflightOnly'
    }
    & $powerShellExecutable @arguments | Out-Null
    return [int] $LASTEXITCODE
}

function Get-ManagedDeploymentUsage {
    param([Parameter(Mandatory = $true)] $Policy)

    $stateDirectories = @(Get-ChildItem -LiteralPath ([string] $Policy.stateRoot) -Directory -Force)
    $managedDirectories = @(Get-ChildItem -LiteralPath ([string] $Policy.managedFilesetRoot) -Directory -Force)
    foreach ($directory in $stateDirectories) {
        if (-not (Test-SafeIdentifier -Value $directory.Name)) {
            throw [System.InvalidOperationException]::new('Deployment state root contains an unexpected directory.')
        }
    }
    foreach ($directory in $managedDirectories) {
        if (-not (Test-SafeIdentifier -Value $directory.Name)) {
            throw [System.InvalidOperationException]::new('Managed fileset root contains an unexpected directory.')
        }
    }
    if ($stateDirectories.Count -ne $managedDirectories.Count) {
        throw [System.InvalidOperationException]::new('Managed deployment roots are inconsistent.')
    }
    $managedBytes = 0L
    $managedEntries = @(Get-ChildItem -LiteralPath ([string] $Policy.managedFilesetRoot) -Recurse -Force)
    foreach ($entry in $managedEntries) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [System.InvalidOperationException]::new('Managed deployment contains a reparse point.')
        }
        if (-not $entry.PSIsContainer) {
            $managedBytes += [long] $entry.Length
        }
        if ($managedBytes -gt [long] $Policy.maxManagedBytes) {
            throw [System.InvalidOperationException]::new('Managed deployment storage exceeds policy.')
        }
    }
    return [ordered]@{
        deploymentCount = $stateDirectories.Count
        managedBytes = $managedBytes
    }
}

function Get-UploadPaths {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $PackageSha256
    )

    if (-not (Test-HexSha256 -Value $PackageSha256)) {
        throw [System.InvalidOperationException]::new('Upload package hash is invalid.')
    }
    return [ordered]@{
        dataPath = Join-Path ([string] $Policy.stateRoot) ('.saef-upload-' + $PackageSha256 + '.bin')
        statePath = Join-Path ([string] $Policy.stateRoot) ('.saef-upload-' + $PackageSha256 + '.local.json')
    }
}

function Read-UploadState {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $ExpectedPackageSha256
    )

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path).Length -gt 65536) {
        throw [System.IO.FileNotFoundException]::new('Upload state is missing or invalid.')
    }
    $state = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($state.formatVersion -ne 1 -or [string] $state.packageSha256 -ne $ExpectedPackageSha256 -or
        [long] $state.expectedBytes -le 0 -or [int] $state.chunkBytes -ne $UploadChunkBytes -or
        [int] $state.expectedChunks -le 0 -or [int] $state.nextIndex -lt 0 -or
        [long] $state.receivedBytes -lt 0 -or $state.createdUtc -isnot [string]) {
        throw [System.InvalidOperationException]::new('Upload state contract is invalid.')
    }
    return $state
}

function Remove-Upload {
    param(
        [Parameter(Mandatory = $true)][string] $DataPath,
        [Parameter(Mandatory = $true)][string] $StatePath
    )

    foreach ($path in @($DataPath, $StatePath)) {
        if (Test-Path -LiteralPath $path -PathType Leaf) {
            Remove-Item -LiteralPath $path -Force
        }
    }
}

function Start-PackageUpload {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $PackageSha256,
        [Parameter(Mandatory = $true)][long] $ExpectedPackageBytes
    )

    $script:failureCode = 'stage_begin'
    if (-not (Test-HexSha256 -Value $PackageSha256) -or $ExpectedPackageBytes -le 0 -or
        $ExpectedPackageBytes -gt [long] $Policy.maxPackageBytes) {
        throw [System.InvalidOperationException]::new('Upload declaration is outside policy.')
    }
    $activeStates = @(Get-ChildItem -LiteralPath ([string] $Policy.stateRoot) `
        -Filter '.saef-upload-*.local.json' -File -Force)
    foreach ($activeStateFile in $activeStates) {
        if ($activeStateFile.Name -notmatch '^\.saef-upload-([a-f0-9]{64})\.local\.json$') {
            throw [System.InvalidOperationException]::new('Upload state root contains an unexpected file.')
        }
        $activeHash = $Matches[1]
        $activePaths = Get-UploadPaths -Policy $Policy -PackageSha256 $activeHash
        $activeState = Read-UploadState -Path $activePaths.statePath -ExpectedPackageSha256 $activeHash
        $createdUtc = [DateTime]::Parse([string] $activeState.createdUtc).ToUniversalTime()
        if (([DateTime]::UtcNow - $createdUtc).TotalSeconds -le [int] $Policy.maxPreflightAgeSeconds) {
            throw [System.InvalidOperationException]::new('Another package upload is active.')
        }
        Remove-Upload -DataPath $activePaths.dataPath -StatePath $activePaths.statePath
    }

    $paths = Get-UploadPaths -Policy $Policy -PackageSha256 $PackageSha256
    if ((Test-Path -LiteralPath $paths.dataPath) -or (Test-Path -LiteralPath $paths.statePath)) {
        throw [System.InvalidOperationException]::new('Upload identity already exists.')
    }
    [IO.File]::WriteAllBytes($paths.dataPath, [byte[]]::new(0))
    $expectedChunks = [int] [Math]::Ceiling($ExpectedPackageBytes / [double] $UploadChunkBytes)
    $state = [ordered]@{
        formatVersion = 1
        createdUtc = [DateTime]::UtcNow.ToString('o')
        packageSha256 = $PackageSha256
        expectedBytes = $ExpectedPackageBytes
        chunkBytes = $UploadChunkBytes
        expectedChunks = $expectedChunks
        nextIndex = 0
        receivedBytes = 0
    }
    try {
        Write-AtomicText -Path $paths.statePath -Text (($state | ConvertTo-Json -Depth 4) + [Environment]::NewLine)
    } catch {
        Remove-Upload -DataPath $paths.dataPath -StatePath $paths.statePath
        throw
    }
    return $state
}

function Add-PackageUploadChunk {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $PackageSha256,
        [Parameter(Mandatory = $true)][int] $Index,
        [Parameter(Mandatory = $true)][string] $Base64Chunk
    )

    $script:failureCode = 'stage_chunk'
    if ($Index -lt 0 -or $Base64Chunk.Length -gt 8192 -or
        $Base64Chunk -notmatch '^[A-Za-z0-9+/]+={0,2}$') {
        throw [System.InvalidOperationException]::new('Upload chunk argument is invalid.')
    }
    $paths = Get-UploadPaths -Policy $Policy -PackageSha256 $PackageSha256
    $state = Read-UploadState -Path $paths.statePath -ExpectedPackageSha256 $PackageSha256
    if ($Index -ne [int] $state.nextIndex -or $Index -ge [int] $state.expectedChunks -or
        -not (Test-Path -LiteralPath $paths.dataPath -PathType Leaf)) {
        throw [System.InvalidOperationException]::new('Upload chunk sequence is invalid.')
    }
    $chunk = [Convert]::FromBase64String($Base64Chunk)
    $remaining = [long] $state.expectedBytes - [long] $state.receivedBytes
    $expectedChunkBytes = [int] [Math]::Min([long] $UploadChunkBytes, $remaining)
    if ($chunk.Length -ne $expectedChunkBytes) {
        throw [System.InvalidOperationException]::new('Upload chunk byte count is invalid.')
    }

    $dataLength = (Get-Item -LiteralPath $paths.dataPath).Length
    if ($dataLength -eq [long] $state.receivedBytes) {
        $stream = [IO.File]::Open($paths.dataPath, [IO.FileMode]::Append, [IO.FileAccess]::Write, [IO.FileShare]::None)
        try {
            $stream.Write($chunk, 0, $chunk.Length)
            $stream.Flush($true)
        } finally {
            $stream.Dispose()
        }
    } elseif ($dataLength -eq [long] $state.receivedBytes + $chunk.Length) {
        $stream = [IO.File]::OpenRead($paths.dataPath)
        try {
            $null = $stream.Seek(-$chunk.Length, [IO.SeekOrigin]::End)
            $existingChunk = [byte[]]::new($chunk.Length)
            $read = $stream.Read($existingChunk, 0, $existingChunk.Length)
        } finally {
            $stream.Dispose()
        }
        if ($read -ne $chunk.Length -or (Get-BytesSha256 -Bytes $existingChunk) -ne
            (Get-BytesSha256 -Bytes $chunk)) {
            throw [System.InvalidOperationException]::new('Upload chunk recovery hash mismatch.')
        }
    } else {
        throw [System.InvalidOperationException]::new('Upload data length differs from state.')
    }

    $state.nextIndex = [int] $state.nextIndex + 1
    $state.receivedBytes = [long] $state.receivedBytes + $chunk.Length
    Write-AtomicText -Path $paths.statePath -Text (($state | ConvertTo-Json -Depth 4) + [Environment]::NewLine)
    return $state
}

function Receive-Package {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $ExpectedPackageSha256,
        [Parameter(Mandatory = $true)][long] $ExpectedPackageBytes,
        [Parameter(Mandatory = $true)][string] $IncomingPath
    )

    $script:failureCode = 'stage_argument'
    if (-not (Test-HexSha256 -Value $ExpectedPackageSha256)) {
        throw [System.InvalidOperationException]::new('Package hash argument is invalid.')
    }
    if ($ExpectedPackageBytes -le 0 -or $ExpectedPackageBytes -gt [long] $Policy.maxPackageBytes) {
        throw [System.InvalidOperationException]::new('Package byte count is outside policy.')
    }
    $script:failureCode = 'stage_usage'
    $usage = Get-ManagedDeploymentUsage -Policy $Policy
    if ([int] $usage.deploymentCount -ge [int] $Policy.maxDeploymentCount) {
        throw [System.InvalidOperationException]::new('Managed deployment count reached policy.')
    }
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $temporaryTarget = $null
    $finalTarget = $null
    $stageCommitted = $false
    $stateDirectory = $null
    try {
        $script:failureCode = 'stage_upload_contract'
        if (-not (Test-Path -LiteralPath $IncomingPath -PathType Leaf) -or
            (Get-Item -LiteralPath $IncomingPath).Length -ne $ExpectedPackageBytes) {
            throw [System.InvalidOperationException]::new('Uploaded package byte count differs from its declaration.')
        }
        $script:failureCode = 'stage_transfer_hash'
        if ((Get-Sha256 -Path $IncomingPath) -ne $ExpectedPackageSha256) {
            throw [System.InvalidOperationException]::new('Transferred package hash mismatch.')
        }

        $script:failureCode = 'stage_archive_open'
        $archive = [IO.Compression.ZipFile]::OpenRead($IncomingPath)
        try {
            $script:failureCode = 'stage_manifest'
            $entries = @($archive.Entries)
            $manifestEntries = @($entries | Where-Object { $_.FullName -eq 'deployment.json' })
            if ($manifestEntries.Count -ne 1) {
                throw [System.InvalidOperationException]::new('Package requires exactly one deployment manifest.')
            }
            $manifestEntry = $manifestEntries[0]
            if ($manifestEntry.Length -gt 1048576) {
                throw [System.InvalidOperationException]::new('Deployment manifest exceeds its byte limit.')
            }
            $manifestStream = $manifestEntry.Open()
            try {
                $manifestBytes = Read-BoundedStreamBytes -Stream $manifestStream `
                    -ExpectedBytes ([long] $manifestEntry.Length) -MaximumBytes 1048576
                $manifestText = [Text.UTF8Encoding]::new($false, $true).GetString($manifestBytes)
            } finally {
                $manifestStream.Dispose()
            }
            $manifest = $manifestText | ConvertFrom-Json
            if ($manifest.formatVersion -ne 1 -or -not (Test-SafeIdentifier -Value ([string] $manifest.deploymentId)) -or
                -not (Test-SafeIdentifier -Value ([string] $manifest.targetDirectoryName))) {
                throw [System.InvalidOperationException]::new('Package manifest identity is invalid.')
            }
            foreach ($hashName in @('expectedActiveSha256', 'expectedCandidateSha256')) {
                if (-not (Test-HexSha256 -Value ([string] $manifest.bootstrap.$hashName))) {
                    throw [System.InvalidOperationException]::new('Package bootstrap hash is invalid.')
                }
            }
            if ($manifest.bootstrap.oldToken -isnot [string] -or $manifest.bootstrap.newToken -isnot [string]) {
                throw [System.InvalidOperationException]::new('Package bootstrap token is invalid.')
            }
            $null = Assert-RuntimeHealthContract -RuntimeHealth $manifest.runtimeHealth
            if (-not ([string] $manifest.bootstrap.newToken).Contains([string] $manifest.targetDirectoryName)) {
                throw [System.InvalidOperationException]::new('Candidate token does not identify the staged fileset.')
            }
            $expectedNewToken = Get-ManagedFilesetBootstrapToken -Policy $Policy `
                -TargetDirectoryName ([string] $manifest.targetDirectoryName)
            if ([string] $manifest.bootstrap.newToken -ne $expectedNewToken) {
                throw [System.InvalidOperationException]::new(
                    'Candidate token does not select the managed fileset bootstrap.'
                )
            }
            $script:failureCode = 'stage_archive_contract'
            $files = @($manifest.files)
            if ($files.Count -lt 1 -or $files.Count -gt [int] $Policy.maxFileCount -or
                $entries.Count -ne $files.Count + 1) {
                throw [System.InvalidOperationException]::new('Package file count is outside its exact contract.')
            }
            $entryMap = @{}
            $expandedBytes = 0L
            foreach ($entry in $entries) {
                if ($entry.FullName.EndsWith('/') -or $entry.FullName.Contains('\') -or
                    ($entry.FullName -ne 'deployment.json' -and -not (Test-SafeRelativePath -Value $entry.FullName))) {
                    throw [System.InvalidOperationException]::new('Package contains an unsafe archive entry.')
                }
                if ($entryMap.ContainsKey($entry.FullName)) {
                    throw [System.InvalidOperationException]::new('Package contains a duplicate archive entry.')
                }
                $entryMap[$entry.FullName] = $entry
                $expandedBytes += [long] $entry.Length
            }
            if ($expandedBytes -gt [long] $Policy.maxExpandedBytes) {
                throw [System.InvalidOperationException]::new('Expanded package exceeds configured byte limit.')
            }
            if ([long] $usage.managedBytes + $expandedBytes -gt [long] $Policy.maxManagedBytes) {
                throw [System.InvalidOperationException]::new('Managed deployment storage would exceed policy.')
            }
            $script:failureCode = 'stage_entry_hashes'
            $manifestFilePaths = @{}
            foreach ($file in $files) {
                $relative = [string] $file.path
                if (-not $relative.StartsWith('fileset/') -or -not (Test-SafeRelativePath -Value $relative) -or
                    -not (Test-HexSha256 -Value ([string] $file.sha256)) -or [long] $file.size -lt 0 -or
                    -not $entryMap.ContainsKey($relative)) {
                    throw [System.InvalidOperationException]::new('Package file contract is invalid.')
                }
                if ($manifestFilePaths.ContainsKey($relative)) {
                    throw [System.InvalidOperationException]::new('Package file contract contains a duplicate path.')
                }
                $manifestFilePaths[$relative] = $true
                $entry = $entryMap[$relative]
                if ([long] $entry.Length -ne [long] $file.size) {
                    throw [System.InvalidOperationException]::new('Package entry size mismatch.')
                }
                $entryStream = $entry.Open()
                try {
                    $entryHash = Get-BoundedStreamSha256 -Stream $entryStream `
                        -ExpectedBytes ([long] $file.size) -MaximumBytes ([long] $Policy.maxExpandedBytes)
                } finally {
                    $entryStream.Dispose()
                }
                if ($entryHash -ne [string] $file.sha256) {
                    throw [System.InvalidOperationException]::new('Package entry hash mismatch.')
                }
            }

            $script:failureCode = 'stage_identity'
            $targetDirectory = Join-Path ([string] $Policy.managedFilesetRoot) ([string] $manifest.targetDirectoryName)
            $finalTarget = $targetDirectory
            $stateDirectory = Join-Path ([string] $Policy.stateRoot) ([string] $manifest.deploymentId)
            if ((Test-Path -LiteralPath $targetDirectory) -or (Test-Path -LiteralPath $stateDirectory)) {
                throw [System.InvalidOperationException]::new('Deployment identity or target already exists.')
            }
            $script:failureCode = 'stage_extract'
            $temporaryTarget = Join-Path ([string] $Policy.managedFilesetRoot) ('.saef-stage-' + [Guid]::NewGuid().ToString('N'))
            [IO.Directory]::CreateDirectory($temporaryTarget) | Out-Null
            foreach ($file in $files) {
                $relative = ([string] $file.path).Substring('fileset/'.Length)
                $destination = Get-FullPathInsideRoot -Root $temporaryTarget -RelativePath $relative
                [IO.Directory]::CreateDirectory((Split-Path -Parent $destination)) | Out-Null
                $entryStream = $entryMap[[string] $file.path].Open()
                $fileStream = [IO.File]::Create($destination)
                try {
                    Copy-BoundedStream -Source $entryStream -Destination $fileStream `
                        -ExpectedBytes ([long] $file.size) -MaximumBytes ([long] $Policy.maxExpandedBytes)
                } finally {
                    $fileStream.Dispose()
                    $entryStream.Dispose()
                }
            }
            $script:failureCode = 'stage_commit'
            [IO.Directory]::Move($temporaryTarget, $targetDirectory)
            $temporaryTarget = $null
            [IO.Directory]::CreateDirectory($stateDirectory) | Out-Null
            Write-AtomicText -Path (Join-Path $stateDirectory 'deployment.json') -Text ($manifestText + [Environment]::NewLine)
            $status = Write-DeploymentStatus -Path (Join-Path $stateDirectory 'status.json') `
                -DeploymentId ([string] $manifest.deploymentId) -Phase 'stage' -Outcome 'staged' -ExitCode $ExitSuccess `
                -Details @{ packageSha256 = $ExpectedPackageSha256; fileCount = $files.Count; activationAttempted = $false }
            $stageCommitted = $true
            return $status
        } finally {
            $archive.Dispose()
        }
    } finally {
        if ($null -ne $temporaryTarget -and (Test-Path -LiteralPath $temporaryTarget -PathType Container)) {
            Remove-Item -LiteralPath $temporaryTarget -Recurse -Force
        }
        if (-not $stageCommitted -and $null -ne $finalTarget -and (Test-Path -LiteralPath $finalTarget -PathType Container)) {
            Remove-Item -LiteralPath $finalTarget -Recurse -Force
        }
        if ($null -ne $stateDirectory -and (Test-Path -LiteralPath $stateDirectory -PathType Container) -and
            -not (Test-Path -LiteralPath (Join-Path $stateDirectory 'deployment.json') -PathType Leaf)) {
            Remove-Item -LiteralPath $stateDirectory -Recurse -Force
        }
        if (Test-Path -LiteralPath $IncomingPath -PathType Leaf) {
            Remove-Item -LiteralPath $IncomingPath -Force
        }
    }
}

function Invoke-DeploymentPreflight {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $DeploymentId
    )

    $deployment = Assert-StagedDeployment -Policy $Policy -DeploymentId $DeploymentId
    $requiredRuntimeFunctions = Assert-RuntimeHealthContract -RuntimeHealth $deployment.manifest.runtimeHealth
    $restartExit = Invoke-RestartCoordinator -Policy $Policy -StatusPath $deployment.paths.restartStatusPath `
        -ActiveBootstrapPath $deployment.bootstrapPath `
        -ExpectedActiveSha256 ([string] $deployment.manifest.bootstrap.expectedActiveSha256) -PreflightOnly `
        -RequiredRuntimeFunctions $requiredRuntimeFunctions
    if ($restartExit -ne 0) {
        return Write-DeploymentStatus -Path $deployment.paths.statusPath -DeploymentId $DeploymentId `
            -Phase 'preflight' -Outcome 'failed' -ExitCode $ExitPreflightFailed `
            -Details @{ restartExitCode = $restartExit; activationAttempted = $false }
    }
    if ([bool] $Policy.runtimeMirrorEnabled) {
        try {
            $mirrorExit = Invoke-RuntimeMirrorCoordinator -Policy $Policy `
                -FilesetPath $deployment.targetDirectory -StatusPath $deployment.paths.mirrorStatusPath -PreflightOnly
        } catch {
            $mirrorExit = -1
        }
        if ($mirrorExit -ne 0) {
            return Write-DeploymentStatus -Path $deployment.paths.statusPath -DeploymentId $DeploymentId `
                -Phase 'preflight' -Outcome 'failed' -ExitCode $ExitPreflightFailed `
                -Details @{
                    restartExitCode = $restartExit
                    mirrorExitCode = $mirrorExit
                    activationAttempted = $false
                }
        }
    }
    return Write-DeploymentStatus -Path $deployment.paths.statusPath -DeploymentId $DeploymentId `
        -Phase 'preflight' -Outcome 'passed' -ExitCode $ExitSuccess `
        -Details @{ manifestSha256 = $deployment.manifestHash; activationAttempted = $false }
}

function Invoke-DeploymentActivation {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $DeploymentId
    )

    $paths = Get-DeploymentPaths -Policy $Policy -DeploymentId $DeploymentId
    if (-not (Test-Path -LiteralPath $paths.statusPath -PathType Leaf)) {
        throw [System.InvalidOperationException]::new('Deployment has no preflight status.')
    }
    $preflightStatus = Get-Content -LiteralPath $paths.statusPath -Raw | ConvertFrom-Json
    $preflightTime = [DateTime]::Parse([string] $preflightStatus.timestampUtc).ToUniversalTime()
    if ($preflightStatus.phase -ne 'preflight' -or $preflightStatus.outcome -ne 'passed' -or
        ([DateTime]::UtcNow - $preflightTime).TotalSeconds -gt [int] $Policy.maxPreflightAgeSeconds) {
        throw [System.InvalidOperationException]::new('Deployment requires a fresh successful preflight.')
    }
    $deployment = Assert-StagedDeployment -Policy $Policy -DeploymentId $DeploymentId
    $requiredRuntimeFunctions = Assert-RuntimeHealthContract -RuntimeHealth $deployment.manifest.runtimeHealth
    if ([string] $preflightStatus.manifestSha256 -ne $deployment.manifestHash) {
        throw [System.InvalidOperationException]::new('Deployment manifest changed after preflight.')
    }

    [IO.File]::WriteAllBytes($paths.rollbackPath, $deployment.activeBytes)
    $rollbackSha256 = Get-Sha256 -Path $paths.rollbackPath
    if ($rollbackSha256 -ne [string] $deployment.manifest.bootstrap.expectedActiveSha256) {
        throw [System.InvalidOperationException]::new('Rollback bootstrap hash mismatch.')
    }
    Write-AtomicBytes -Path $deployment.bootstrapPath -Bytes $deployment.candidateBytes
    if ((Get-Sha256 -Path $deployment.bootstrapPath) -ne [string] $deployment.manifest.bootstrap.expectedCandidateSha256) {
        Write-AtomicBytes -Path $deployment.bootstrapPath -Bytes $deployment.activeBytes
        throw [System.InvalidOperationException]::new('Activated bootstrap hash mismatch.')
    }

    try {
        $restartExit = Invoke-RestartCoordinator -Policy $Policy -StatusPath $paths.restartStatusPath `
            -ActiveBootstrapPath $deployment.bootstrapPath `
            -ExpectedActiveSha256 ([string] $deployment.manifest.bootstrap.expectedCandidateSha256) `
            -RollbackPath $paths.rollbackPath -ExpectedRollbackSha256 $rollbackSha256 `
            -RequiredRuntimeFunctions $requiredRuntimeFunctions
    } catch {
        Write-AtomicBytes -Path $deployment.bootstrapPath -Bytes $deployment.activeBytes
        if ((Get-Sha256 -Path $deployment.bootstrapPath) -ne $rollbackSha256) {
            return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
                -Phase 'activation' -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                -Details @{ activationAttempted = $true; restartAttempted = $false; rollbackAttempted = $true; rollbackSucceeded = $false }
        }
        return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
            -Phase 'activation' -Outcome 'restart_launch_failed_rolled_back' -ExitCode $ExitActivationFailed `
            -Details @{ activationAttempted = $true; restartAttempted = $false; rollbackAttempted = $true; rollbackSucceeded = $true }
    }
    if ($restartExit -eq 0) {
        if ((Get-Sha256 -Path $deployment.bootstrapPath) -ne [string] $deployment.manifest.bootstrap.expectedCandidateSha256) {
            return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
                -Phase 'activation' -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                -Details @{ restartExitCode = $restartExit; activationAttempted = $true; rollbackAttempted = $false }
        }
        $mirrorExit = $null
        if ([bool] $Policy.runtimeMirrorEnabled) {
            try {
                $mirrorExit = Invoke-RuntimeMirrorCoordinator -Policy $Policy `
                    -FilesetPath $deployment.targetDirectory -StatusPath $paths.mirrorStatusPath
            } catch {
                $mirrorExit = -1
            }
            if ($mirrorExit -ne 0) {
                return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
                    -Phase 'activation' -Outcome 'activated_mirror_degraded' -ExitCode $ExitSuccess `
                    -Details @{
                        restartExitCode = $restartExit
                        mirrorExitCode = $mirrorExit
                        activationAttempted = $true
                        rollbackAttempted = $false
                        runtimeActivated = $true
                    }
            }
        }
        return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
            -Phase 'activation' -Outcome 'activated' -ExitCode $ExitSuccess `
            -Details @{
                restartExitCode = $restartExit
                mirrorExitCode = $mirrorExit
                activationAttempted = $true
                rollbackAttempted = $false
            }
    }
    if ($restartExit -eq 10) {
        Write-AtomicBytes -Path $deployment.bootstrapPath -Bytes $deployment.activeBytes
        if ((Get-Sha256 -Path $deployment.bootstrapPath) -ne $rollbackSha256) {
            return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
                -Phase 'activation' -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                -Details @{ restartExitCode = $restartExit; activationAttempted = $true; restartAttempted = $false; rollbackAttempted = $true; rollbackSucceeded = $false }
        }
        return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
            -Phase 'activation' -Outcome 'restart_preflight_failed_rolled_back' -ExitCode $ExitActivationFailed `
            -Details @{ restartExitCode = $restartExit; activationAttempted = $true; restartAttempted = $false; rollbackAttempted = $true }
    }
    if ($restartExit -eq 30) {
        if ((Get-Sha256 -Path $deployment.bootstrapPath) -ne $rollbackSha256) {
            return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
                -Phase 'activation' -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                -Details @{ restartExitCode = $restartExit; activationAttempted = $true; rollbackAttempted = $true; rollbackSucceeded = $false }
        }
        return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
            -Phase 'activation' -Outcome 'rolled_back' -ExitCode $ExitActivationFailed `
            -Details @{ restartExitCode = $restartExit; activationAttempted = $true; rollbackAttempted = $true; rollbackSucceeded = $true }
    }
    return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
        -Phase 'activation' -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
        -Details @{ restartExitCode = $restartExit; activationAttempted = $true; rollbackAttempted = $true; rollbackSucceeded = $false }
}

$operation = 'request'
$exitCode = $ExitRequestRejected
$channelMutex = $null
try {
    $script:failureCode = 'policy'
    $policy = Read-ChannelPolicy -Path $PolicyPath
    $script:failureCode = 'command'
    $originalCommand = [string] $env:SSH_ORIGINAL_COMMAND
    if ([string]::IsNullOrWhiteSpace($originalCommand)) {
        throw [System.InvalidOperationException]::new('A deployment operation is required.')
    }
    $parts = @($originalCommand.Trim().Split(' ', [StringSplitOptions]::RemoveEmptyEntries))
    $operation = $parts[0].ToLowerInvariant()
    if ($operation -notin @('probe', 'stage', 'preflight', 'activate', 'status')) {
        throw [System.InvalidOperationException]::new('Deployment operation is not allowed.')
    }
    if ($operation -eq 'probe') {
        if ($parts.Count -ne 1) {
            throw [System.InvalidOperationException]::new('Probe accepts no arguments.')
        }
        $channelMutex = Enter-ChannelMutex
        $probeBootstrapPath = Get-FullPathInsideRoot -Root ([string] $policy.scriptsRoot) `
            -RelativePath ([string] $policy.activeBootstrapRelativePath)
        $probeStatusPath = Join-Path ([string] $policy.stateRoot) 'channel-probe-status.local.json'
        $restartExit = Invoke-RestartCoordinator -Policy $policy -StatusPath $probeStatusPath `
            -ActiveBootstrapPath $probeBootstrapPath -ExpectedActiveSha256 (Get-Sha256 -Path $probeBootstrapPath) `
            -PreflightOnly
        if ($restartExit -ne 0) {
            throw [System.InvalidOperationException]::new('Runtime readiness probe failed.')
        }
        Write-JsonResponse -Success $true -Operation $operation -Outcome 'ready' -ExitCode $ExitSuccess `
            -Details @{
                channelVersion = $ChannelVersion
                allowedOperations = @('probe', 'stage', 'preflight', 'activate', 'status')
            }
        exit $ExitSuccess
    }
    if ($operation -eq 'stage') {
        $exitCode = $ExitStageFailed
        if ($parts.Count -lt 3) {
            throw [System.InvalidOperationException]::new('Stage requires a bounded subcommand.')
        }
        $channelMutex = Enter-ChannelMutex
        $stageMode = $parts[1].ToLowerInvariant()
        $packageSha256 = $parts[2]
        if ($stageMode -eq 'begin') {
            $packageBytes = 0L
            if ($parts.Count -ne 4 -or
                -not [long]::TryParse($parts[3], [Globalization.NumberStyles]::None, [Globalization.CultureInfo]::InvariantCulture, [ref] $packageBytes)) {
                throw [System.InvalidOperationException]::new('Stage begin requires one hash and one byte count.')
            }
            $uploadState = Start-PackageUpload -Policy $policy -PackageSha256 $packageSha256 `
                -ExpectedPackageBytes $packageBytes
            Write-JsonResponse -Success $true -Operation $operation -Outcome 'upload_started' -ExitCode $ExitSuccess `
                -Details @{ packageSha256 = $packageSha256; expectedChunks = [int] $uploadState.expectedChunks }
            exit $ExitSuccess
        }
        if ($stageMode -eq 'chunk') {
            $chunkIndex = -1
            if ($parts.Count -ne 5 -or
                -not [int]::TryParse($parts[3], [Globalization.NumberStyles]::None, [Globalization.CultureInfo]::InvariantCulture, [ref] $chunkIndex)) {
                throw [System.InvalidOperationException]::new('Stage chunk requires hash, index and data.')
            }
            $uploadState = Add-PackageUploadChunk -Policy $policy -PackageSha256 $packageSha256 `
                -Index $chunkIndex -Base64Chunk $parts[4]
            Write-JsonResponse -Success $true -Operation $operation -Outcome 'chunk_accepted' -ExitCode $ExitSuccess `
                -Details @{ packageSha256 = $packageSha256; nextIndex = [int] $uploadState.nextIndex }
            exit $ExitSuccess
        }
        if ($stageMode -eq 'commit') {
            if ($parts.Count -ne 3) {
                throw [System.InvalidOperationException]::new('Stage commit requires one package hash.')
            }
            $uploadPaths = Get-UploadPaths -Policy $policy -PackageSha256 $packageSha256
            $uploadState = Read-UploadState -Path $uploadPaths.statePath `
                -ExpectedPackageSha256 $packageSha256
            if ([int] $uploadState.nextIndex -ne [int] $uploadState.expectedChunks -or
                [long] $uploadState.receivedBytes -ne [long] $uploadState.expectedBytes) {
                throw [System.InvalidOperationException]::new('Package upload is incomplete.')
            }
            try {
                $status = Receive-Package -Policy $policy -ExpectedPackageSha256 $packageSha256 `
                    -ExpectedPackageBytes ([long] $uploadState.expectedBytes) `
                    -IncomingPath $uploadPaths.dataPath
            } finally {
                Remove-Upload -DataPath $uploadPaths.dataPath -StatePath $uploadPaths.statePath
            }
            Write-JsonResponse -Success $true -Operation $operation -Outcome 'staged' -ExitCode $ExitSuccess `
                -Details @{
                    deploymentId = $status.deploymentId
                    packageSha256 = $status.packageSha256
                    fileCount = $status.fileCount
                }
            exit $ExitSuccess
        }
        throw [System.InvalidOperationException]::new('Stage subcommand is not allowed.')
    }
    if ($parts.Count -ne 2 -or -not (Test-SafeIdentifier -Value $parts[1])) {
        throw [System.InvalidOperationException]::new('Operation requires exactly one valid deployment identifier.')
    }
    $deploymentId = $parts[1]
    if ($operation -eq 'preflight') {
        $exitCode = $ExitPreflightFailed
        $script:failureCode = 'preflight_contract'
        $channelMutex = Enter-ChannelMutex
        $status = Invoke-DeploymentPreflight -Policy $policy -DeploymentId $deploymentId
        $success = [int] $status.exitCode -eq 0
        Write-JsonResponse -Success $success -Operation $operation -Outcome ([string] $status.outcome) `
            -ExitCode ([int] $status.exitCode) -Details @{ deploymentId = $deploymentId }
        exit ([int] $status.exitCode)
    }
    if ($operation -eq 'activate') {
        $exitCode = $ExitActivationFailed
        $channelMutex = Enter-ChannelMutex
        $status = Invoke-DeploymentActivation -Policy $policy -DeploymentId $deploymentId
        $success = [int] $status.exitCode -eq 0
        Write-JsonResponse -Success $success -Operation $operation -Outcome ([string] $status.outcome) `
            -ExitCode ([int] $status.exitCode) -Details @{ deploymentId = $deploymentId }
        exit ([int] $status.exitCode)
    }
    $paths = Get-DeploymentPaths -Policy $policy -DeploymentId $deploymentId
    if (-not (Test-Path -LiteralPath $paths.statusPath -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Deployment status is missing.')
    }
    $status = Get-Content -LiteralPath $paths.statusPath -Raw | ConvertFrom-Json
    $statusDetails = @{
        deploymentId = $deploymentId
        phase = [string] $status.phase
        deploymentExitCode = [int] $status.exitCode
        statusTimestampUtc = [string] $status.timestampUtc
    }
    if (Test-Path -LiteralPath $paths.mirrorStatusPath -PathType Leaf) {
        $mirrorStatusFile = Get-Item -LiteralPath $paths.mirrorStatusPath
        if ($mirrorStatusFile.Length -le 65536) {
            $mirrorStatus = Get-Content -LiteralPath $paths.mirrorStatusPath -Raw | ConvertFrom-Json
            $allowedMirrorFailureCodes = @(
                'request', 'path_contract', 'rpc_endpoint', 'reconciler_integrity',
                'fileset_provenance', 'credential_source', 'mirror_state', 'mirror_ownership',
                'reconcile_execution', 'reconcile_result', 'state_commit'
            )
            $statusDetails['mirrorOutcome'] = [string] $mirrorStatus.outcome
            if ($mirrorStatus.PSObject.Properties.Name -contains 'failureCode' -and
                [string] $mirrorStatus.failureCode -in $allowedMirrorFailureCodes) {
                $statusDetails['mirrorFailureCode'] = [string] $mirrorStatus.failureCode
            }
        }
    }
    if (Test-Path -LiteralPath $paths.restartStatusPath -PathType Leaf) {
        $restartStatusFile = Get-Item -LiteralPath $paths.restartStatusPath
        if ($restartStatusFile.Length -le 65536) {
            $restartStatus = Get-Content -LiteralPath $paths.restartStatusPath -Raw | ConvertFrom-Json
            $allowedRestartChecks = @(
                'policy', 'credential_source', 'service_name', 'rollback_parameters',
                'active_bootstrap_hash', 'service_state', 'kernel_runlevel',
                'kernel_start_time', 'runtime_health_probe'
            )
            $allowedRuntimeHealthChecks = @(
                'not_started', 'parameters', 'contract_decode', 'object_exists',
                'object_type', 'source_hash', 'execution', 'result_contract', 'passed'
            )
            $statusDetails['restartOutcome'] = [string] $restartStatus.outcome
            if ($restartStatus.PSObject.Properties.Name -contains 'failedCheck' -and
                [string] $restartStatus.failedCheck -in $allowedRestartChecks) {
                $statusDetails['restartFailedCheck'] = [string] $restartStatus.failedCheck
            }
            if ($restartStatus.PSObject.Properties.Name -contains 'runtimeHealthCheck' -and
                [string] $restartStatus.runtimeHealthCheck -in $allowedRuntimeHealthChecks) {
                $statusDetails['runtimeHealthCheck'] = [string] $restartStatus.runtimeHealthCheck
            }
        }
    }
    Write-JsonResponse -Success $true -Operation $operation -Outcome ([string] $status.outcome) -ExitCode $ExitSuccess `
        -Details $statusDetails
    exit $ExitSuccess
} catch {
    Write-JsonResponse -Success $false -Operation $operation -Outcome 'rejected' -ExitCode $exitCode `
        -Details @{ errorType = $_.Exception.GetType().FullName; failureCode = $script:failureCode }
    exit $exitCode
} finally {
    if ($null -ne $channelMutex) {
        try {
            $channelMutex.ReleaseMutex()
        } finally {
            $channelMutex.Dispose()
        }
    }
}
