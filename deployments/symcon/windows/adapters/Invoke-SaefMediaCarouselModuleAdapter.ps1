[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'activate', 'postflight', 'inspect', 'rollback')]
    [string] $Operation,

    [Parameter(Mandatory = $true)]
    [string] $ManifestPath,

    [Parameter(Mandatory = $true)]
    [string] $CandidatePath,

    [Parameter(Mandatory = $true)]
    [string] $TransactionContractPath,

    [Parameter(Mandatory = $true)]
    [string] $AdapterPolicyPath,

    [Parameter(Mandatory = $true)]
    [Uri] $RpcUri,

    [Parameter(Mandatory = $true)]
    [string] $CredentialPath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitRolledBack = 30
$ExitManualRecovery = 40
$script:manifest = $null
$script:manifestSha256 = ''
$script:packageIdentitySha256 = ''
$script:activationAttempted = ($Operation -eq 'activate')
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:failureCode = 'initialization'
$script:activeMoved = $false
$script:candidateActivated = $false
$script:rollbackPath = $null
$script:failedCandidatePath = $null
$script:transactionRoot = $null
$script:previousActiveState = $null
$script:activeStateWritten = $false
$script:snapshot = $null
$script:policy = $null
$script:credential = $null
$script:mutex = $null
$script:mutexAcquired = $false

function Test-HexSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)
    return $Value -match '^[a-f0-9]{64}$'
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

function Get-TextSha256 {
    param([Parameter(Mandatory = $true)][string] $Text)
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
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
        [Parameter(Mandatory = $true)] $Value,
        [Parameter()][int] $Depth = 10
    )
    $directory = Split-Path -Parent $Path
    if ([string]::IsNullOrWhiteSpace($directory) -or
        -not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Atomic output directory is missing.')
    }
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-media-carousel-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-media-carousel-' + $token + '.bak')
    try {
        $json = ($Value | ConvertTo-Json -Depth $Depth) + [Environment]::NewLine
        [IO.File]::WriteAllText($temporary, $json, [Text.UTF8Encoding]::new($false))
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

function Write-AtomicText {
    param([Parameter(Mandatory = $true)][string] $Path, [Parameter(Mandatory = $true)][string] $Text)
    $directory = Split-Path -Parent $Path
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-media-carousel-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-media-carousel-' + $token + '.bak')
    try {
        [IO.File]::WriteAllText($temporary, $Text, [Text.UTF8Encoding]::new($false))
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

function Write-AdapterStatus {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode
    )
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        operation = $Operation
        deploymentId = if ($null -ne $script:manifest) { [string] $script:manifest.deploymentId } else { '' }
        manifestSha256 = $script:manifestSha256
        packageIdentitySha256 = $script:packageIdentitySha256
        outcome = $Outcome
        exitCode = $ExitCode
        activationAttempted = [bool] $script:activationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        failureCode = $script:failureCode
    }
    Write-AtomicJson -Path $StatusPath -Value $status
}

function Assert-RootedLeaf {
    param([Parameter(Mandatory = $true)][string] $Path, [Parameter(Mandatory = $true)][long] $MaximumBytes)
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path).Length -gt $MaximumBytes -or
        (((Get-Item -LiteralPath $Path).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.FileNotFoundException]::new('Required bounded file is missing or unsafe.')
    }
}

function Assert-SafeDirectoryTree {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Required directory is missing.')
    }
    $root = Get-Item -LiteralPath $Path
    if (($root.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw [InvalidOperationException]::new('Directory root is a reparse point.')
    }
    foreach ($entry in @(Get-ChildItem -LiteralPath $Path -Force -Recurse)) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw [InvalidOperationException]::new('Directory tree contains a reparse point.')
        }
    }
}

function Assert-ProtectedAcl {
    param([Parameter(Mandatory = $true)][string] $Path)
    $acl = Get-Acl -LiteralPath $Path
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        $dangerous = [Security.AccessControl.FileSystemRights]::Write -bor
            [Security.AccessControl.FileSystemRights]::Modify -bor
            [Security.AccessControl.FileSystemRights]::FullControl
        if ($entry.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            (($entry.FileSystemRights -band $dangerous) -ne 0) -and
            $sid -in @('S-1-1-0', 'S-1-5-11', 'S-1-5-32-545')) {
            throw [UnauthorizedAccessException]::new('Adapter path grants write access to a broad principal.')
        }
    }
}

function Import-MachineCredential {
    param([Parameter(Mandatory = $true)][string] $Path)
    Add-Type -AssemblyName System.Security -ErrorAction Stop
    Assert-RootedLeaf -Path $Path -MaximumBytes 131072
    $record = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($record.formatVersion -ne 1 -or $record.protectionScope -ne 'LocalMachine' -or
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
        # JSON-RPC is UTF-8 in both directions, independently of Windows 5.1
        # string-body and missing-response-charset defaults.
        $utf8 = [Text.UTF8Encoding]::new($false, $true)
        $http = Invoke-WebRequest -UseBasicParsing -Uri $RpcUri -Method Post -ContentType 'application/json; charset=utf-8' `
            -Body ($utf8.GetBytes($body)) -TimeoutSec ([int] $script:policy.rpcTimeoutSeconds) -Headers @{
                Authorization = 'Basic ' + [Convert]::ToBase64String($authorization)
            }
        try { $response = $utf8.GetString($http.RawContentStream.ToArray()) | ConvertFrom-Json }
        finally { $http.RawContentStream.Dispose() }
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

function Read-Contracts {
    Assert-RootedLeaf -Path $ManifestPath -MaximumBytes 1048576
    Assert-RootedLeaf -Path $TransactionContractPath -MaximumBytes 1048576
    Assert-RootedLeaf -Path $AdapterPolicyPath -MaximumBytes 1048576
    $script:manifestSha256 = Get-Sha256 -Path $ManifestPath
    $script:manifest = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
    $transaction = Get-Content -LiteralPath $TransactionContractPath -Raw | ConvertFrom-Json
    $script:policy = Get-Content -LiteralPath $AdapterPolicyPath -Raw | ConvertFrom-Json
    $script:packageIdentitySha256 = [string] $script:manifest.module.packageIdentitySha256
    if ($script:manifest.formatVersion -ne 1 -or
        [string] $script:manifest.deploymentKind -ne 'standalone-module' -or
        [string] $script:manifest.module.targetId -ne 'saef-media-carousel' -or
        -not (Test-HexSha256 -Value $script:packageIdentitySha256) -or
        -not (Test-SymconGuid -Value ([string] $script:manifest.module.libraryGuid)) -or
        $transaction.formatVersion -ne 1 -or $script:policy.formatVersion -ne 1 -or
        [string] $transaction.adapterProfile -ne 'saef-media-carousel-v1' -or
        [string] $script:policy.adapterProfile -ne 'saef-media-carousel-v1' -or
        [string] $script:policy.targetId -ne 'saef-media-carousel' -or
        [string] $script:manifest.module.libraryGuid -ne [string] $script:policy.libraryGuid) {
        throw [InvalidOperationException]::new('MediaCarousel adapter identity contract is invalid.')
    }
    if ([string] $transaction.ownership.mode -ne 'adapter-owned-package-directory' -or
        [bool] $transaction.ownership.repositoryMetadataAllowed -or
        [string] $transaction.quiescence.writerModel -ne 'no-server-side-writers' -or
        [string] $transaction.state.rollbackPreparation -ne 'fresh-snapshot-no-conversion' -or
        [string] $transaction.reload.method -ne 'MC_ReloadModule' -or
        [string] $transaction.rollback.switch -ne 'same-volume-directory-rename' -or
        [string] $transaction.retention.owner -ne 'channel-v8-cross-root-contract' -or
        [bool] $transaction.retention.implemented) {
        throw [InvalidOperationException]::new('MediaCarousel transaction contract is unsupported.')
    }
    if ($script:policy.PSObject.Properties.Name -icontains 'configurationTransition' -and
        $script:policy.PSObject.Properties.Name -cnotcontains 'configurationTransition') {
        throw [InvalidOperationException]::new('Schema transition property casing is invalid.')
    }
    if ($script:policy.PSObject.Properties.Name -ccontains 'configurationTransition' -and
        ($transaction.configuration.PSObject.Properties.Name -cnotcontains 'optionalSchemaTransition' -or
        $transaction.configuration.optionalSchemaTransition -cne 'show-fit-toggle-default-false-v1')) {
        throw [InvalidOperationException]::new('Package transaction does not authorize the schema transition.')
    }
    foreach ($guid in @(
        [string] $script:policy.libraryGuid,
        [string] $script:policy.moduleGuid,
        [string] $script:policy.moduleControlModuleGuid
    )) {
        if (-not (Test-SymconGuid -Value $guid)) {
            throw [InvalidOperationException]::new('Adapter policy GUID is invalid.')
        }
    }
    if ([int] $script:policy.moduleControlInstanceId -le 0 -or
        [string] $script:policy.moduleDirectoryName -notmatch '^[A-Za-z0-9._-]{1,128}$' -or
        [int] $script:policy.moduleType -ne 3 -or
        [string] $script:policy.modulePrefix -ne 'SAEFMC' -or
        [string] $script:policy.mutexName -notmatch '^Global\\SAEF\.[A-Za-z0-9.]{1,96}$' -or
        [int] $script:policy.rpcTimeoutSeconds -lt 1 -or [int] $script:policy.rpcTimeoutSeconds -gt 60 -or
        [int] $script:policy.reloadTimeoutSeconds -lt 1 -or [int] $script:policy.reloadTimeoutSeconds -gt 300 -or
        [int] $script:policy.healthPollMilliseconds -lt 100 -or
        [int] $script:policy.healthPollMilliseconds -gt 5000 -or
        [int] $script:policy.maximumInstanceCount -lt 1 -or
        [int] $script:policy.maximumInstanceCount -gt 256 -or
        [long] $script:policy.maximumCandidateBytes -lt 1 -or
        [long] $script:policy.maximumCandidateBytes -gt 268435456 -or
        [int] $script:policy.maximumStateBytes -lt 65536 -or
        [int] $script:policy.maximumStateBytes -gt 4194304 -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedActivePackageIdentitySha256)) -or
        @($script:policy.expectedInstances).Count -lt 1 -or
        @($script:policy.expectedInstances).Count -gt [int] $script:policy.maximumInstanceCount -or
        @($script:policy.allowedInstanceStatuses).Count -lt 1 -or
        @($script:policy.allowedInstanceStatuses).Count -gt 8) {
        throw [InvalidOperationException]::new('Adapter policy limits are invalid.')
    }
    $expectedInstanceIDs = @{}
    foreach ($expectedInstance in @($script:policy.expectedInstances)) {
        if (@($expectedInstance.PSObject.Properties.Name).Count -ne 2 -or
            $expectedInstance.PSObject.Properties.Name -notcontains 'instanceId' -or
            $expectedInstance.PSObject.Properties.Name -notcontains 'configurationSha256' -or
            [int] $expectedInstance.instanceId -le 0 -or
            -not (Test-HexSha256 -Value ([string] $expectedInstance.configurationSha256)) -or
            $expectedInstanceIDs.ContainsKey([int] $expectedInstance.instanceId)) {
            throw [InvalidOperationException]::new('Expected MediaCarousel instance binding is invalid.')
        }
        $expectedInstanceIDs[[int] $expectedInstance.instanceId] = [string] $expectedInstance.configurationSha256
    }
    foreach ($status in @($script:policy.allowedInstanceStatuses)) {
        if ([int] $status -lt 100 -or [int] $status -gt 299) {
            throw [InvalidOperationException]::new('Adapter policy instance status is invalid.')
        }
    }
    if ($RpcUri.Scheme -notin @('http', 'https') -or $RpcUri.Host -notin @('127.0.0.1', 'localhost', '::1')) {
        throw [InvalidOperationException]::new('RPC URI must use a loopback endpoint.')
    }
}

function Get-ModuleTreePackageIdentity {
    param([Parameter(Mandatory = $true)][string] $Path)
    Assert-SafeDirectoryTree -Path $Path
    $expected = @($script:manifest.files)
    $actual = @(Get-ChildItem -LiteralPath $Path -File -Recurse)
    if ($expected.Count -ne $actual.Count -or $expected.Count -lt 1) {
        throw [InvalidOperationException]::new('Module tree file inventory differs from the manifest.')
    }
    $identity = [Text.StringBuilder]::new()
    $seen = @{}
    $totalBytes = 0L
    foreach ($file in $expected) {
        $manifestPath = [string] $file.path
        if (-not $manifestPath.StartsWith('module/') -or $manifestPath.Contains('\') -or
            $manifestPath.Contains('../') -or $manifestPath.EndsWith('/')) {
            throw [InvalidOperationException]::new('Candidate manifest path is unsafe.')
        }
        $relative = $manifestPath.Substring(7)
        $absolute = [IO.Path]::GetFullPath((Join-Path $Path $relative))
        $root = [IO.Path]::GetFullPath($Path).TrimEnd([char[]] @('\', '/')) + [IO.Path]::DirectorySeparatorChar
        if (-not $absolute.StartsWith($root, [StringComparison]::OrdinalIgnoreCase) -or
            $seen.ContainsKey($relative) -or -not (Test-Path -LiteralPath $absolute -PathType Leaf) -or
            [long] (Get-Item -LiteralPath $absolute).Length -ne [long] $file.size -or
            (Get-Sha256 -Path $absolute) -ne [string] $file.sha256) {
            throw [InvalidOperationException]::new('Candidate file hash contract failed.')
        }
        $seen[$relative] = $true
        $totalBytes += [long] $file.size
        $null = $identity.Append($relative).Append([char] 0).Append([long] $file.size).Append(
            [char] 0
        ).Append([string] $file.sha256).Append("`n")
    }
    $computed = Get-TextSha256 -Text $identity.ToString()
    if ($computed -ne $script:packageIdentitySha256) {
        throw [InvalidOperationException]::new('Module tree package identity differs from its manifest.')
    }
    if ($totalBytes -gt [long] $script:policy.maximumCandidateBytes) {
        throw [InvalidOperationException]::new('Module tree exceeds the adapter byte limit.')
    }
    return $computed
}

function Assert-ModuleTreeIdentity {
    param([Parameter(Mandatory = $true)][string] $Path)
    Assert-SafeDirectoryTree -Path $Path
    if (Test-Path -LiteralPath (Join-Path $Path '.git')) {
        throw [InvalidOperationException]::new('Git-managed module trees cannot be adopted by this adapter.')
    }
    $library = Get-Content -LiteralPath (Join-Path $Path 'library.json') -Raw | ConvertFrom-Json
    $module = Get-Content -LiteralPath (Join-Path $Path 'MediaCarousel\module.json') -Raw | ConvertFrom-Json
    if ([string] $library.id -ne [string] $script:policy.libraryGuid -or
        [string] $library.name -ne [string] $script:policy.libraryName -or
        [string] $library.url -ne [string] $script:policy.libraryUrl -or
        [string] $module.id -ne [string] $script:policy.moduleGuid -or
        [string] $module.name -ne [string] $script:policy.moduleName) {
        throw [InvalidOperationException]::new('Module tree ownership identity differs from policy.')
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
    foreach ($file in @(Get-ChildItem -LiteralPath $Path -File -Recurse)) {
        $relative = $file.FullName.Substring($root.Length).Replace('\', '/')
        $filesByRelativePath.Add($relative, $file)
    }
    [string[]] $relativePaths = @($filesByRelativePath.Keys)
    [Array]::Sort($relativePaths, [StringComparer]::Ordinal)
    if ($relativePaths.Count -lt 1) {
        throw [InvalidOperationException]::new('Module tree is empty.')
    }
    $identity = [Text.StringBuilder]::new()
    $totalBytes = 0L
    foreach ($relative in $relativePaths) {
        $file = $filesByRelativePath[$relative]
        $totalBytes += [long] $file.Length
        if ($totalBytes -gt [long] $script:policy.maximumCandidateBytes) {
            throw [InvalidOperationException]::new('Module tree exceeds the adapter byte limit.')
        }
        $null = $identity.Append($relative).Append([char] 0).Append([long] $file.Length).Append(
            [char] 0
        ).Append((Get-Sha256 -Path $file.FullName)).Append("`n")
    }
    return Get-TextSha256 -Text $identity.ToString()
}

function Get-InstanceSnapshot {
    param([Parameter()][object[]] $ExpectedInstances = @($script:policy.expectedInstances))
    $instanceIDs = @(Invoke-SymconRpc -Method 'IPS_GetInstanceListByModuleID' `
        -Parameters @([string] $script:policy.moduleGuid))
    $expectedInstances = @($ExpectedInstances)
    if ($instanceIDs.Count -ne $expectedInstances.Count -or
        $instanceIDs.Count -gt [int] $script:policy.maximumInstanceCount) {
        throw [InvalidOperationException]::new('MediaCarousel instance inventory differs from policy.')
    }
    $expectedByID = @{}
    foreach ($expectedInstance in $expectedInstances) {
        $expectedByID[[int] $expectedInstance.instanceId] = $expectedInstance
    }
    [int[]] $orderedInstanceIDs = @($instanceIDs | ForEach-Object { [int] $_ })
    [Array]::Sort($orderedInstanceIDs)
    $records = @()
    foreach ($rawID in $orderedInstanceIDs) {
        $instanceID = [int] $rawID
        if ($instanceID -le 0 -or
            -not $expectedByID.ContainsKey($instanceID) -or
            -not [bool] (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @($instanceID))) {
            throw [InvalidOperationException]::new('MediaCarousel instance identity is invalid.')
        }
        $expectedInstance = $expectedByID[$instanceID]
        $instance = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @($instanceID)
        $object = Invoke-SymconRpc -Method 'IPS_GetObject' -Parameters @($instanceID)
        $configuration = [string] (Invoke-SymconRpc -Method 'IPS_GetConfiguration' -Parameters @($instanceID))
        if ([string] $instance.ModuleInfo.ModuleID -ne [string] $script:policy.moduleGuid -or
            [int] $object.ObjectType -ne 1 -or
            [int] $instance.InstanceStatus -notin @($script:policy.allowedInstanceStatuses) -or
            [bool] (Invoke-SymconRpc -Method 'IPS_HasChanges' -Parameters @($instanceID)) -or
            (Get-TextSha256 -Text $configuration) -ne
                [string] $expectedInstance.configurationSha256) {
            throw [InvalidOperationException]::new('MediaCarousel instance is not in an admissible baseline state.')
        }
        $records += [ordered]@{
            instanceId = $instanceID
            configurationBase64 = [Convert]::ToBase64String([Text.UTF8Encoding]::new($false).GetBytes($configuration))
            configurationSha256 = Get-TextSha256 -Text $configuration
            objectIdent = [string] $object.ObjectIdent
            objectName = [string] $object.ObjectName
            parentId = if ($object.PSObject.Properties.Name -contains 'ParentID') {
                [int] $object.ParentID
            } else { [int] $object.ObjectParentID }
            position = [int] $object.ObjectPosition
            hidden = [bool] $object.ObjectIsHidden
            disabled = [bool] $object.ObjectIsDisabled
            readOnly = [bool] $object.ObjectIsReadOnly
            status = [int] $instance.InstanceStatus
        }
    }
    return @($records)
}

function Assert-SymconOwnership {
    if ([int] (Invoke-SymconRpc -Method 'IPS_GetKernelRunlevel') -ne
        [int] $script:policy.expectedReadyRunlevel) {
        throw [InvalidOperationException]::new('Symcon is not at the required ready runlevel.')
    }
    $moduleControlID = [int] $script:policy.moduleControlInstanceId
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_FunctionExists' -Parameters @('MC_ReloadModule'))) {
        throw [InvalidOperationException]::new('Targeted Module Control reload is unavailable.')
    }
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @($moduleControlID))) {
        throw [InvalidOperationException]::new('Pinned Module Control instance does not exist.')
    }
    $moduleControl = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @($moduleControlID)
    if ([int] $moduleControl.InstanceStatus -ne 102 -or
        [string] $moduleControl.ModuleInfo.ModuleID -ne [string] $script:policy.moduleControlModuleGuid) {
        throw [InvalidOperationException]::new('Pinned Module Control instance is not active.')
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
    if ([string] $library.Name -ne [string] $script:policy.libraryName -or
        [string] $library.URL -ne [string] $script:policy.libraryUrl -or
        [string] $module.LibraryID -ne [string] $script:policy.libraryGuid -or
        [string] $module.ModuleName -ne [string] $script:policy.moduleName -or
        [int] $module.ModuleType -ne [int] $script:policy.moduleType -or
        [string] $module.Prefix -ne [string] $script:policy.modulePrefix -or
        $libraryModules.Count -ne 1 -or
        [string] $libraryModules[0] -ne [string] $script:policy.moduleGuid) {
        throw [InvalidOperationException]::new('MediaCarousel Symcon ownership is ambiguous.')
    }
}

function Assert-SnapshotPreserved {
    param([Parameter(Mandatory = $true)] $Snapshot)
    $current = @(Get-InstanceSnapshot -ExpectedInstances @($Snapshot.instances))
    if ($current.Count -ne @($Snapshot.instances).Count) {
        throw [InvalidOperationException]::new('MediaCarousel instance inventory changed during deployment.')
    }
    for ($index = 0; $index -lt $current.Count; $index++) {
        $before = $Snapshot.instances[$index]
        $after = $current[$index]
        foreach ($field in @(
            'instanceId', 'configurationBase64', 'configurationSha256', 'objectIdent', 'objectName',
            'parentId', 'position', 'hidden', 'disabled', 'readOnly', 'status'
        )) {
            if (-not [string]::Equals([string] $before.$field, [string] $after.$field, [StringComparison]::Ordinal)) {
                throw [InvalidOperationException]::new('MediaCarousel configuration or object state changed.')
            }
        }
    }
}

function Get-ConfigurationTokens {
    param([Parameter(Mandatory = $true)][string] $Text)
    # Symcon properties are scalar values; complex lists are JSON inside strings.
    # Parse the compact wire representation without reserializing any old bytes.
    $stringValue = '"(?:[^"\\\x00-\x1f]|\\(?:["\\/bfnrt]|u[0-9a-fA-F]{4}))*"'
    $number = '-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?'
    $pattern = '\G(?<separator>[{,])"(?<name>[A-Za-z][A-Za-z0-9]*)":(?<value>' +
        $stringValue + '|true|false|null|' + $number + ')'
    $regex = [regex]::new($pattern, [Text.RegularExpressions.RegexOptions]::CultureInvariant,
        [TimeSpan]::FromSeconds(1))
    $names = [Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
    $offset = 0
    $tokens = @()
    if ($Text -ceq '{}') { return @() }
    while ($offset -lt $Text.Length - 1) {
        $match = $regex.Match($Text, $offset)
        $separator = if ($offset -eq 0) { '{' } else { ',' }
        if (-not $match.Success -or $match.Index -ne $offset -or
            $match.Groups['separator'].Value -cne $separator -or
            -not $names.Add($match.Groups['name'].Value) -or $tokens.Count -ge 128) {
            throw [InvalidOperationException]::new('Configuration wire format is ambiguous or unsupported.')
        }
        $tokens += $match
        $offset += $match.Length
    }
    if ($offset -ne $Text.Length - 1 -or $Text[$offset] -cne '}') {
        throw [InvalidOperationException]::new('Configuration wire format is incomplete.')
    }
    return $tokens
}

function Assert-FitDefaultAddition {
    param([Parameter(Mandatory = $true)][string] $Before, [Parameter(Mandatory = $true)][string] $After)
    $beforeTokens = @(Get-ConfigurationTokens -Text $Before)
    $afterTokens = @(Get-ConfigurationTokens -Text $After)
    $added = @($afterTokens | Where-Object { $_.Groups['name'].Value -ceq 'ShowFitToggle' })
    if (@($beforeTokens | Where-Object { $_.Groups['name'].Value -ieq 'ShowFitToggle' }).Count -ne 0 -or
        $afterTokens.Count -ne $beforeTokens.Count + 1 -or $added.Count -ne 1 -or
        $added[0].Groups['value'].Value -cne 'false') {
        throw [InvalidOperationException]::new('Only the absent ShowFitToggle false default may be added.')
    }
    $token = $added[0]
    if ($token.Index -eq 0) {
        $without = if ($afterTokens.Count -eq 1) { '{}' } else {
            '{' + $After.Substring($token.Length + 1)
        }
    } else {
        $without = $After.Remove($token.Index, $token.Length)
    }
    if (-not [string]::Equals($Before, $without, [StringComparison]::Ordinal)) {
        throw [InvalidOperationException]::new('Schema transition changed existing configuration bytes.')
    }
}

function Get-CandidateSnapshot {
    param([Parameter(Mandatory = $true)] $Snapshot)
    if ($script:policy.PSObject.Properties.Name -cnotcontains 'configurationTransition') {
        return $Snapshot
    }
    $transition = $script:policy.configurationTransition
    foreach ($name in @('kind', 'sourcePackageIdentitySha256', 'candidatePackageIdentitySha256', 'deploymentId', 'instances')) {
        if ($transition.PSObject.Properties.Name -cnotcontains $name) {
            throw [InvalidOperationException]::new('Configuration transition property is missing.')
        }
    }
    if (@($transition.PSObject.Properties.Name).Count -ne 5 -or
        $transition.kind -cne 'show-fit-toggle-default-false-v1' -or
        $transition.sourcePackageIdentitySha256 -cne $script:policy.expectedActivePackageIdentitySha256 -or
        $transition.candidatePackageIdentitySha256 -cne $script:packageIdentitySha256 -or
        $transition.deploymentId -cne $script:manifest.deploymentId -or
        @($transition.instances).Count -ne @($Snapshot.instances).Count) {
        throw [InvalidOperationException]::new('Configuration transition is not bound to this deployment.')
    }
    $byID = @{}
    $totalBytes = 0L
    foreach ($entry in @($transition.instances)) {
        foreach ($name in @('instanceId', 'configurationBase64', 'configurationSha256')) {
            if ($entry.PSObject.Properties.Name -cnotcontains $name) {
                throw [InvalidOperationException]::new('Configuration transition instance property is missing.')
            }
        }
        if (@($entry.PSObject.Properties.Name).Count -ne 3 -or
            $entry.instanceId -isnot [int] -or $entry.instanceId -le 0 -or
            $entry.configurationBase64 -isnot [string] -or
            $entry.configurationSha256 -isnot [string] -or
            -not (Test-HexSha256 -Value $entry.configurationSha256) -or
            $byID.ContainsKey($entry.instanceId) -or
            $entry.configurationBase64.Length -gt [int] $script:policy.maximumStateBytes * 2) {
            throw [InvalidOperationException]::new('Configuration transition instance binding is invalid.')
        }
        $bytes = [Convert]::FromBase64String($entry.configurationBase64)
        $text = [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
        $totalBytes += $bytes.Length
        if ([Convert]::ToBase64String($bytes) -cne $entry.configurationBase64 -or
            (Get-TextSha256 -Text $text) -cne $entry.configurationSha256 -or
            $totalBytes -gt [int] $script:policy.maximumStateBytes) {
            throw [InvalidOperationException]::new('Configuration transition bytes differ from their binding.')
        }
        $byID[$entry.instanceId] = $text
    }
    $candidate = $Snapshot | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    foreach ($record in @($candidate.instances)) {
        $id = [int] $record.instanceId
        if (-not $byID.ContainsKey($id)) {
            throw [InvalidOperationException]::new('Configuration transition instance set differs.')
        }
        $before = [Text.UTF8Encoding]::new($false, $true).GetString(
            [Convert]::FromBase64String([string] $record.configurationBase64))
        Assert-FitDefaultAddition -Before $before -After $byID[$id]
        $record.configurationBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($byID[$id]))
        $record.configurationSha256 = Get-TextSha256 -Text $byID[$id]
    }
    return $candidate
}

function Invoke-TargetedReload {
    $result = Invoke-SymconRpc -Method 'MC_ReloadModule' -Parameters @(
        [int] $script:policy.moduleControlInstanceId,
        [string] $script:policy.moduleDirectoryName
    )
    if (-not [bool] $result) {
        throw [InvalidOperationException]::new('Targeted Module Control reload returned failure.')
    }
}

function Wait-Healthy {
    param([Parameter(Mandatory = $true)] $Snapshot)
    $timer = [Diagnostics.Stopwatch]::StartNew()
    do {
        try {
            Assert-SymconOwnership
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

function Restore-Configurations {
    param([Parameter(Mandatory = $true)] $Snapshot)
    foreach ($record in @($Snapshot.instances)) {
        $instanceID = [int] $record.instanceId
        if ($instanceID -le 0 -or
            -not [bool] (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @($instanceID))) {
            throw [InvalidOperationException]::new('Rollback instance is missing.')
        }
        $instance = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @($instanceID)
        $object = Invoke-SymconRpc -Method 'IPS_GetObject' -Parameters @($instanceID)
        if ([int] $object.ObjectType -ne 1 -or
            [string] $instance.ModuleInfo.ModuleID -cne [string] $script:policy.moduleGuid) {
            throw [InvalidOperationException]::new('Rollback instance ownership differs.')
        }
        $current = [string] (Invoke-SymconRpc -Method 'IPS_GetConfiguration' -Parameters @($instanceID))
        if ((Get-TextSha256 -Text $current) -ne [string] $record.configurationSha256) {
            $bytes = [Convert]::FromBase64String([string] $record.configurationBase64)
            try {
                $configuration = [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
                $null = Invoke-SymconRpc -Method 'IPS_SetConfiguration' -Parameters @($instanceID, $configuration)
                $null = Invoke-SymconRpc -Method 'IPS_ApplyChanges' -Parameters @($instanceID)
            } finally {
                [Array]::Clear($bytes, 0, $bytes.Length)
            }
        }
    }
}

function Copy-CandidateToTransaction {
    param([Parameter(Mandatory = $true)][string] $Destination)
    [IO.Directory]::CreateDirectory($Destination) | Out-Null
    foreach ($source in @(Get-ChildItem -LiteralPath $CandidatePath -File -Recurse)) {
        $relative = $source.FullName.Substring($CandidatePath.TrimEnd([char[]] @('\', '/')).Length + 1)
        $target = Join-Path $Destination $relative
        [IO.Directory]::CreateDirectory((Split-Path -Parent $target)) | Out-Null
        [IO.File]::Copy($source.FullName, $target, $false)
    }
    $null = Get-ModuleTreePackageIdentity -Path $Destination
    Assert-ModuleTreeIdentity -Path $Destination
}

function Invoke-Rollback {
    $script:rollbackAttempted = $true
    try {
        if (-not $script:activeMoved -and -not $script:candidateActivated) {
            $script:rollbackSucceeded = $true
            return
        }
        if ($script:candidateActivated -and (Test-Path -LiteralPath ([string] $script:policy.activeModulePath))) {
            if (Test-Path -LiteralPath $script:failedCandidatePath) {
                throw [InvalidOperationException]::new('Failed-candidate retention path already exists.')
            }
            [IO.Directory]::Move([string] $script:policy.activeModulePath, $script:failedCandidatePath)
            $script:candidateActivated = $false
        }
        if ($script:activeMoved) {
            [IO.Directory]::Move($script:rollbackPath, [string] $script:policy.activeModulePath)
            $script:activeMoved = $false
        }
        if ((Get-DirectoryPackageIdentity -Path ([string] $script:policy.activeModulePath)) -ne
            [string] $script:policy.expectedActivePackageIdentitySha256) {
            throw [InvalidOperationException]::new('Restored active package identity differs from policy.')
        }
        Invoke-TargetedReload
        Restore-Configurations -Snapshot $script:snapshot
        Wait-Healthy -Snapshot $script:snapshot
        $activeStatePath = Join-Path ([string] $script:policy.adapterStateRoot) 'active.json'
        if ($script:activeStateWritten) {
            if ($null -ne $script:previousActiveState) {
                Write-AtomicText -Path $activeStatePath -Text ([string] $script:previousActiveState)
            } elseif (Test-Path -LiteralPath $activeStatePath -PathType Leaf) {
                Remove-Item -LiteralPath $activeStatePath -Force
            }
            $script:activeStateWritten = $false
        }
        $script:rollbackSucceeded = $true
    } catch {
        $script:rollbackSucceeded = $false
    }
}

function Read-RecoveryJson {
    param([Parameter(Mandatory = $true)][string] $Path)
    Assert-RootedLeaf -Path $Path -MaximumBytes ([int] $script:policy.maximumStateBytes)
    $bytes = [IO.File]::ReadAllBytes($Path)
    if ($bytes.Length -gt [int] $script:policy.maximumStateBytes) { throw 'Recovery evidence exceeds limit.' }
    return ([Text.UTF8Encoding]::new($false, $true).GetString($bytes) | ConvertFrom-Json)
}

function Get-PreviousActiveStateEvidence {
    if ($null -eq $script:previousActiveState) {
        return [ordered]@{ existed = $false; sha256 = ''; contentBase64 = '' }
    }
    return [ordered]@{
        existed = $true
        sha256 = Get-TextSha256 ([string] $script:previousActiveState)
        contentBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes([string] $script:previousActiveState))
    }
}

function Restore-PreviousActiveStateEvidence {
    param([Parameter(Mandatory = $true)] $Record)
    # Restore process-local evidence only. No filesystem mutation occurs here.
    if ($Record.existed -isnot [bool] -or $Record.sha256 -isnot [string] -or
        $Record.contentBase64 -isnot [string] -or
        $Record.contentBase64.Length -gt ([int] $script:policy.maximumStateBytes * 2)) {
        throw 'Invalid previous active-state evidence.'
    }
    if (-not $Record.existed) {
        if ($Record.sha256 -cne '' -or $Record.contentBase64 -cne '') { throw 'Absent state contains bytes.' }
        $script:previousActiveState = $null
        return
    }
    $bytes = [Convert]::FromBase64String($Record.contentBase64)
    $text = [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
    if ($bytes.Length -gt [int] $script:policy.maximumStateBytes -or
        [Convert]::ToBase64String($bytes) -cne $Record.contentBase64 -or
        (Get-TextSha256 $text) -cne $Record.sha256) { throw 'Previous active-state bytes differ.' }
    $script:previousActiveState = $text
}

function Read-CompletedActivationContext {
    $active = Read-RecoveryJson (Join-Path $script:policy.adapterStateRoot 'active.json')
    $name = [string] $active.transactionDirectoryName
    if ($active.formatVersion -ne 1 -or $active.adapterProfile -cne 'saef-media-carousel-v1' -or
        $active.deploymentId -cne $script:manifest.deploymentId -or
        $active.packageIdentitySha256 -cne $script:packageIdentitySha256 -or
        $name -cnotmatch '^[a-z0-9][a-z0-9.-]+-[0-9]{8}T[0-9]{6}Z$' -or
        -not $name.StartsWith(([string] $script:manifest.deploymentId + '-'), [StringComparison]::Ordinal) -or
        $active.rollbackDirectoryName -cne 'rollback' -or $active.snapshotFileName -cne 'snapshot.json') {
        throw 'Active recovery identity differs.'
    }
    $root = Join-Path $script:policy.adapterStateRoot $name
    Assert-SafeDirectoryTree $root
    $record = Read-RecoveryJson (Join-Path $root 'transaction.json')
    if ($record.formatVersion -ne 1 -or $record.adapterProfile -cne 'saef-media-carousel-v1' -or
        $record.transactionDirectoryName -cne $name -or $record.outcome -cne 'activated' -or
        $record.deploymentId -cne $script:manifest.deploymentId -or
        $record.packageIdentitySha256 -cne $script:packageIdentitySha256 -or
        $record.manifestSha256 -cne $script:manifestSha256 -or
        (Test-Path -LiteralPath (Join-Path $root 'candidate')) -or
        (Test-Path -LiteralPath (Join-Path $root 'failed-candidate'))) { throw 'Completed recovery evidence differs.' }
    foreach ($entry in @(@('snapshot.json', 'snapshotSha256'), @('candidate-snapshot.json', 'candidateSnapshotSha256'))) {
        $path = Join-Path $root $entry[0]
        Assert-RootedLeaf $path ([int] $script:policy.maximumStateBytes)
        if ((Get-Sha256 $path) -cne [string] $record.($entry[1])) { throw 'Recovery snapshot hash differs.' }
    }
    $before = Read-RecoveryJson (Join-Path $root 'snapshot.json')
    $after = Read-RecoveryJson (Join-Path $root 'candidate-snapshot.json')
    foreach ($snapshot in @($before, $after)) {
        if ($snapshot.formatVersion -ne 1 -or $snapshot.deploymentId -cne $script:manifest.deploymentId -or
            $snapshot.packageIdentitySha256 -cne $script:packageIdentitySha256 -or
            -not (Test-HexSha256 ([string] $snapshot.activePackageIdentitySha256))) {
            throw 'Recovery snapshot identity differs.'
        }
    }
    if ($before.activePackageIdentitySha256 -cne $after.activePackageIdentitySha256 -or
        $before.activePackageIdentitySha256 -cne $record.previousPackageIdentitySha256 -or
        (Get-DirectoryPackageIdentity (Join-Path $root 'rollback')) -cne $before.activePackageIdentitySha256) {
        throw 'Rollback package identity differs.'
    }
    Restore-PreviousActiveStateEvidence $before.previousActiveState
    return [ordered]@{ root = $root; before = $before; after = $after; record = $record }
}

try {
    $script:failureCode = 'contract'
    Read-Contracts
    $script:failureCode = 'path_ownership'
    Assert-SafeDirectoryTree -Path ([string] $script:policy.adapterStateRoot)
    Assert-SafeDirectoryTree -Path ([string] $script:policy.activeModulePath)
    if ((Split-Path -Leaf ([string] $script:policy.activeModulePath)) -ne
        [string] $script:policy.moduleDirectoryName) {
        throw [InvalidOperationException]::new('Active module path does not match the reload directory identity.')
    }
    if ([IO.Path]::GetPathRoot([string] $script:policy.adapterStateRoot) -ne
        [IO.Path]::GetPathRoot([string] $script:policy.activeModulePath)) {
        throw [InvalidOperationException]::new('Adapter state and active module must share one volume.')
    }
    $activeFull = [IO.Path]::GetFullPath([string] $script:policy.activeModulePath).TrimEnd(
        [char[]] @('\', '/')
    ) + [IO.Path]::DirectorySeparatorChar
    $stateFull = [IO.Path]::GetFullPath([string] $script:policy.adapterStateRoot).TrimEnd(
        [char[]] @('\', '/')
    ) + [IO.Path]::DirectorySeparatorChar
    $candidateFull = [IO.Path]::GetFullPath($CandidatePath).TrimEnd(
        [char[]] @('\', '/')
    ) + [IO.Path]::DirectorySeparatorChar
    if ($activeFull.StartsWith($stateFull, [StringComparison]::OrdinalIgnoreCase) -or
        $stateFull.StartsWith($activeFull, [StringComparison]::OrdinalIgnoreCase) -or
        $activeFull.StartsWith($candidateFull, [StringComparison]::OrdinalIgnoreCase) -or
        $candidateFull.StartsWith($activeFull, [StringComparison]::OrdinalIgnoreCase) -or
        $stateFull.StartsWith($candidateFull, [StringComparison]::OrdinalIgnoreCase) -or
        $candidateFull.StartsWith($stateFull, [StringComparison]::OrdinalIgnoreCase)) {
        throw [InvalidOperationException]::new('Adapter module, state and candidate paths must not overlap.')
    }
    Assert-ProtectedAcl -Path ([string] $script:policy.adapterStateRoot)
    Assert-ProtectedAcl -Path ([string] $script:policy.activeModulePath)
    Assert-ModuleTreeIdentity -Path ([string] $script:policy.activeModulePath)
    $activeIdentity = Get-DirectoryPackageIdentity -Path ([string] $script:policy.activeModulePath)
    $expectedIdentity = if ($Operation -in @('postflight', 'rollback')) {
        $script:packageIdentitySha256
    } else { [string] $script:policy.expectedActivePackageIdentitySha256 }
    if ($Operation -ne 'inspect' -and $activeIdentity -cne $expectedIdentity) {
        throw [InvalidOperationException]::new('Active package identity differs from policy.')
    }
    Assert-ProtectedAcl -Path $CandidatePath
    $null = Get-ModuleTreePackageIdentity -Path $CandidatePath
    Assert-ModuleTreeIdentity -Path $CandidatePath

    $script:mutex = [Threading.Mutex]::new($false, [string] $script:policy.mutexName)
    $script:mutexAcquired = $script:mutex.WaitOne(0)
    if (-not $script:mutexAcquired) {
        throw [InvalidOperationException]::new('Another MediaCarousel adapter operation is active.')
    }
    $script:credential = Import-MachineCredential -Path $CredentialPath
    $script:failureCode = 'symcon_ownership'
    Assert-SymconOwnership
    if ($Operation -in @('postflight', 'inspect', 'rollback')) {
        $script:failureCode = 'recovery_evidence'
        # Conservatively classify only fully completed activations. Incomplete
        # or legacy evidence must never cause a second switch or reload.
        $context = Read-CompletedActivationContext
        $null = Get-ModuleTreePackageIdentity $script:policy.activeModulePath
        Assert-SnapshotPreserved $context.after
        if ($Operation -ne 'rollback') {
            $script:failureCode = 'none'
            $outcome = if ($Operation -eq 'inspect') { 'active' } else { 'passed' }
            Write-AdapterStatus -Outcome $outcome -ExitCode $ExitSuccess
            exit $ExitSuccess
        }
        if ($script:policy.expectedActivePackageIdentitySha256 -cne $context.before.activePackageIdentitySha256) {
            throw 'Restore the protected predecessor policy before rollback.'
        }
        $script:transactionRoot = $context.root
        $script:snapshot = $context.before
        $script:rollbackPath = Join-Path $context.root 'rollback'
        $script:failedCandidatePath = Join-Path $context.root 'failed-candidate'
        $context.record.outcome = 'rollback_started'
        Write-AtomicJson (Join-Path $context.root 'transaction.json') $context.record
        $script:activeMoved = $true
        $script:candidateActivated = $true
        $script:activeStateWritten = $true
        Invoke-Rollback
        if (-not $script:rollbackSucceeded) { throw 'Post-success rollback is unproven.' }
        $context.record.outcome = 'rolled_back'
        Write-AtomicJson (Join-Path $context.root 'transaction.json') $context.record
        $script:failureCode = 'none'
        Write-AdapterStatus -Outcome 'rolled_back' -ExitCode $ExitRolledBack
        exit $ExitRolledBack
    }
    $script:snapshot = [ordered]@{
        formatVersion = 1
        capturedUtc = [DateTime]::UtcNow.ToString('o')
        deploymentId = [string] $script:manifest.deploymentId
        packageIdentitySha256 = $script:packageIdentitySha256
        activePackageIdentitySha256 = $activeIdentity
        instances = @(Get-InstanceSnapshot)
    }
    $candidateSnapshot = Get-CandidateSnapshot -Snapshot $script:snapshot

    if ($Operation -eq 'preflight') {
        $script:failureCode = 'none'
        Write-AdapterStatus -Outcome 'passed' -ExitCode $ExitSuccess
        exit $ExitSuccess
    }

    $script:failureCode = 'rollback_preparation'
    $activeStatePath = Join-Path ([string] $script:policy.adapterStateRoot) 'active.json'
    if (Test-Path -LiteralPath $activeStatePath -PathType Leaf) {
        if ((Get-Item -LiteralPath $activeStatePath).Length -gt [int] $script:policy.maximumStateBytes) {
            throw [InvalidOperationException]::new('Existing active adapter state is oversized.')
        }
        Assert-RootedLeaf $activeStatePath ([int] $script:policy.maximumStateBytes)
        $script:previousActiveState = [Text.UTF8Encoding]::new($false, $true).GetString([IO.File]::ReadAllBytes($activeStatePath))
    }
    $script:snapshot.previousActiveState = Get-PreviousActiveStateEvidence
    $candidateSnapshot = Get-CandidateSnapshot -Snapshot $script:snapshot
    $transactionID = [string] $script:manifest.deploymentId + '-' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ')
    $script:transactionRoot = Join-Path ([string] $script:policy.adapterStateRoot) $transactionID
    if (Test-Path -LiteralPath $script:transactionRoot) { throw 'Existing transaction requires recovery review.' }
    $candidateTransactionPath = Join-Path $script:transactionRoot 'candidate'
    $script:rollbackPath = Join-Path $script:transactionRoot 'rollback'
    $script:failedCandidatePath = Join-Path $script:transactionRoot 'failed-candidate'
    [IO.Directory]::CreateDirectory($script:transactionRoot) | Out-Null
    Copy-CandidateToTransaction -Destination $candidateTransactionPath
    $snapshotText = ($script:snapshot | ConvertTo-Json -Depth 10) + [Environment]::NewLine
    if ([Text.UTF8Encoding]::new($false).GetByteCount($snapshotText) -gt
        [int] $script:policy.maximumStateBytes) {
        throw [InvalidOperationException]::new('Fresh configuration snapshot exceeds the adapter state limit.')
    }
    Write-AtomicText -Path (Join-Path $script:transactionRoot 'snapshot.json') -Text $snapshotText
    Write-AtomicJson -Path (Join-Path $script:transactionRoot 'candidate-snapshot.json') -Value $candidateSnapshot
    $recoveryRecord = [ordered]@{
        formatVersion = 1; adapterProfile = 'saef-media-carousel-v1'
        transactionDirectoryName = $transactionID
        deploymentId = [string] $script:manifest.deploymentId
        packageIdentitySha256 = $script:packageIdentitySha256
        previousPackageIdentitySha256 = $activeIdentity
        manifestSha256 = $script:manifestSha256
        snapshotSha256 = Get-Sha256 (Join-Path $script:transactionRoot 'snapshot.json')
        candidateSnapshotSha256 = Get-Sha256 (Join-Path $script:transactionRoot 'candidate-snapshot.json')
        outcome = 'activation_started'
    }
    Write-AtomicJson (Join-Path $script:transactionRoot 'transaction.json') $recoveryRecord

    $script:failureCode = 'pre_mutation_recheck'
    Assert-SymconOwnership
    Assert-SnapshotPreserved -Snapshot $script:snapshot
    if ((Get-DirectoryPackageIdentity -Path ([string] $script:policy.activeModulePath)) -ne
        [string] $script:policy.expectedActivePackageIdentitySha256) {
        throw [InvalidOperationException]::new('Active package identity drifted before mutation.')
    }

    $script:failureCode = 'package_switch'
    [IO.Directory]::Move([string] $script:policy.activeModulePath, $script:rollbackPath)
    $script:activeMoved = $true
    [IO.Directory]::Move($candidateTransactionPath, [string] $script:policy.activeModulePath)
    $script:candidateActivated = $true

    $script:failureCode = 'targeted_reload'
    Invoke-TargetedReload
    $script:failureCode = 'post_activation_health'
    $null = Get-ModuleTreePackageIdentity -Path ([string] $script:policy.activeModulePath)
    Wait-Healthy -Snapshot $candidateSnapshot
    $activeRecord = [ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-media-carousel-v1'
        deploymentId = [string] $script:manifest.deploymentId
        packageIdentitySha256 = $script:packageIdentitySha256
        activatedUtc = [DateTime]::UtcNow.ToString('o')
        transactionDirectoryName = $transactionID
        rollbackDirectoryName = 'rollback'
        snapshotFileName = 'snapshot.json'
    }
    $recoveryRecord.outcome = 'activated'
    Write-AtomicJson -Path (Join-Path $script:transactionRoot 'transaction.json') -Value $recoveryRecord
    Write-AtomicJson -Path $activeStatePath -Value $activeRecord
    $script:activeStateWritten = $true
    $script:failureCode = 'none'
    Write-AdapterStatus -Outcome 'activated' -ExitCode $ExitSuccess
    exit $ExitSuccess
} catch {
    if ($Operation -in @('inspect', 'rollback')) {
        Write-AdapterStatus -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery
        exit $ExitManualRecovery
    }
    if ($Operation -eq 'activate' -and $script:activationAttempted) {
        $originalFailureCode = $script:failureCode
        Invoke-Rollback
        $script:failureCode = $originalFailureCode
        if ($script:rollbackSucceeded) {
            if ($null -ne $script:transactionRoot -and
                (Test-Path -LiteralPath $script:transactionRoot -PathType Container)) {
                Write-AtomicJson -Path (Join-Path $script:transactionRoot 'transaction.json') -Value ([ordered]@{
                    formatVersion = 1
                    adapterProfile = 'saef-media-carousel-v1'
                    transactionDirectoryName = Split-Path -Leaf $script:transactionRoot
                    deploymentId = [string] $script:manifest.deploymentId
                    packageIdentitySha256 = $script:packageIdentitySha256
                    completedUtc = [DateTime]::UtcNow.ToString('o')
                    outcome = 'rolled_back'
                })
            }
            Write-AdapterStatus -Outcome 'rolled_back' -ExitCode $ExitRolledBack
            exit $ExitRolledBack
        }
        if ($null -ne $script:transactionRoot -and
            (Test-Path -LiteralPath $script:transactionRoot -PathType Container)) {
            Write-AtomicJson -Path (Join-Path $script:transactionRoot 'transaction.json') -Value ([ordered]@{
                formatVersion = 1
                adapterProfile = 'saef-media-carousel-v1'
                transactionDirectoryName = Split-Path -Leaf $script:transactionRoot
                deploymentId = [string] $script:manifest.deploymentId
                packageIdentitySha256 = $script:packageIdentitySha256
                completedUtc = [DateTime]::UtcNow.ToString('o')
                outcome = 'manual_recovery_required'
            })
        }
        Write-AdapterStatus -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery
        exit $ExitManualRecovery
    }
    Write-AdapterStatus -Outcome 'failed' -ExitCode $ExitPreflightFailed
    exit $ExitPreflightFailed
} finally {
    if ($script:mutexAcquired -and $null -ne $script:mutex) {
        $script:mutex.ReleaseMutex()
    }
    if ($null -ne $script:mutex) {
        $script:mutex.Dispose()
    }
    $script:credential = $null
}
