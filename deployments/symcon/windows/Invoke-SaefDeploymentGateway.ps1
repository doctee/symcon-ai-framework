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
$ChannelMutexName = 'Global\SAEF.DeploymentChannel'

function Test-HexSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -match '^[a-f0-9]{64}$'
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
    foreach ($name in @('scriptsRoot', 'managedFilesetRoot', 'stateRoot', 'restartCoordinatorPath', 'restartPolicyPath', 'credentialPath')) {
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
        -not (Test-HexSha256 -Value ([string] $policy.expectedRestartPolicySha256))) {
        throw [System.InvalidOperationException]::new('Pinned coordinator hashes are invalid.')
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
    foreach ($file in @($policy.restartCoordinatorPath, $policy.restartPolicyPath, $policy.credentialPath)) {
        if (-not (Test-Path -LiteralPath ([string] $file) -PathType Leaf)) {
            throw [System.IO.FileNotFoundException]::new('Configured deployment dependency is missing.')
        }
    }
    if ((Get-Sha256 -Path ([string] $policy.restartCoordinatorPath)) -ne [string] $policy.expectedRestartCoordinatorSha256 -or
        (Get-Sha256 -Path ([string] $policy.restartPolicyPath)) -ne [string] $policy.expectedRestartPolicySha256) {
        throw [System.InvalidOperationException]::new('Pinned restart dependency hash mismatch.')
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
        [Parameter()][string] $ExpectedRollbackSha256
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

function Receive-Package {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $ExpectedPackageSha256
    )

    if (-not (Test-HexSha256 -Value $ExpectedPackageSha256)) {
        throw [System.InvalidOperationException]::new('Package hash argument is invalid.')
    }
    $usage = Get-ManagedDeploymentUsage -Policy $Policy
    if ([int] $usage.deploymentCount -ge [int] $Policy.maxDeploymentCount) {
        throw [System.InvalidOperationException]::new('Managed deployment count reached policy.')
    }
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $incomingPath = Join-Path ([string] $Policy.stateRoot) ('.saef-incoming-' + [Guid]::NewGuid().ToString('N') + '.zip')
    $temporaryTarget = $null
    $finalTarget = $null
    $stageCommitted = $false
    $stateDirectory = $null
    try {
        $inputStream = [Console]::OpenStandardInput()
        $outputStream = [IO.File]::Create($incomingPath)
        try {
            $buffer = [byte[]]::new(65536)
            $total = 0L
            while (($read = $inputStream.Read($buffer, 0, $buffer.Length)) -gt 0) {
                $total += $read
                if ($total -gt [long] $Policy.maxPackageBytes) {
                    throw [System.InvalidOperationException]::new('Package exceeds configured byte limit.')
                }
                $outputStream.Write($buffer, 0, $read)
            }
        } finally {
            $outputStream.Dispose()
        }
        if ((Get-Sha256 -Path $incomingPath) -ne $ExpectedPackageSha256) {
            throw [System.InvalidOperationException]::new('Transferred package hash mismatch.')
        }

        $archive = [IO.Compression.ZipFile]::OpenRead($incomingPath)
        try {
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
            if (-not ([string] $manifest.bootstrap.newToken).Contains([string] $manifest.targetDirectoryName)) {
                throw [System.InvalidOperationException]::new('Candidate token does not identify the staged fileset.')
            }
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

            $targetDirectory = Join-Path ([string] $Policy.managedFilesetRoot) ([string] $manifest.targetDirectoryName)
            $finalTarget = $targetDirectory
            $stateDirectory = Join-Path ([string] $Policy.stateRoot) ([string] $manifest.deploymentId)
            if ((Test-Path -LiteralPath $targetDirectory) -or (Test-Path -LiteralPath $stateDirectory)) {
                throw [System.InvalidOperationException]::new('Deployment identity or target already exists.')
            }
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
        if (Test-Path -LiteralPath $incomingPath -PathType Leaf) {
            Remove-Item -LiteralPath $incomingPath -Force
        }
    }
}

function Invoke-DeploymentPreflight {
    param(
        [Parameter(Mandatory = $true)] $Policy,
        [Parameter(Mandatory = $true)][string] $DeploymentId
    )

    $deployment = Assert-StagedDeployment -Policy $Policy -DeploymentId $DeploymentId
    $restartExit = Invoke-RestartCoordinator -Policy $Policy -StatusPath $deployment.paths.restartStatusPath `
        -ActiveBootstrapPath $deployment.bootstrapPath `
        -ExpectedActiveSha256 ([string] $deployment.manifest.bootstrap.expectedActiveSha256) -PreflightOnly
    if ($restartExit -ne 0) {
        return Write-DeploymentStatus -Path $deployment.paths.statusPath -DeploymentId $DeploymentId `
            -Phase 'preflight' -Outcome 'failed' -ExitCode $ExitPreflightFailed `
            -Details @{ restartExitCode = $restartExit; activationAttempted = $false }
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
            -RollbackPath $paths.rollbackPath -ExpectedRollbackSha256 $rollbackSha256
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
        return Write-DeploymentStatus -Path $paths.statusPath -DeploymentId $DeploymentId `
            -Phase 'activation' -Outcome 'activated' -ExitCode $ExitSuccess `
            -Details @{ restartExitCode = $restartExit; activationAttempted = $true; rollbackAttempted = $false }
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
    $policy = Read-ChannelPolicy -Path $PolicyPath
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
            -Details @{ channelVersion = 1; allowedOperations = @('probe', 'stage', 'preflight', 'activate', 'status') }
        exit $ExitSuccess
    }
    if ($operation -eq 'stage') {
        $exitCode = $ExitStageFailed
        if ($parts.Count -ne 2) {
            throw [System.InvalidOperationException]::new('Stage requires exactly one package hash.')
        }
        $channelMutex = Enter-ChannelMutex
        $status = Receive-Package -Policy $policy -ExpectedPackageSha256 $parts[1]
        Write-JsonResponse -Success $true -Operation $operation -Outcome 'staged' -ExitCode $ExitSuccess `
            -Details @{ deploymentId = $status.deploymentId; packageSha256 = $status.packageSha256; fileCount = $status.fileCount }
        exit $ExitSuccess
    }
    if ($parts.Count -ne 2 -or -not (Test-SafeIdentifier -Value $parts[1])) {
        throw [System.InvalidOperationException]::new('Operation requires exactly one valid deployment identifier.')
    }
    $deploymentId = $parts[1]
    if ($operation -eq 'preflight') {
        $exitCode = $ExitPreflightFailed
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
    Write-JsonResponse -Success $true -Operation $operation -Outcome ([string] $status.outcome) -ExitCode $ExitSuccess `
        -Details @{
            deploymentId = $deploymentId
            phase = [string] $status.phase
            deploymentExitCode = [int] $status.exitCode
            statusTimestampUtc = [string] $status.timestampUtc
        }
    exit $ExitSuccess
} catch {
    Write-JsonResponse -Success $false -Operation $operation -Outcome 'rejected' -ExitCode $exitCode `
        -Details @{ errorType = $_.Exception.GetType().FullName }
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
