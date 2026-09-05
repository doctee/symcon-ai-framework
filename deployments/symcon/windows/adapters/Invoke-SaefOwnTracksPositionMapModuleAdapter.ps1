[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('preflight', 'activate')]
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
$script:finalExitCode = $ExitManualRecovery
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
$script:runtimeLocks = @()
$script:runtimeStateSnapshot = $null

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
    $temporary = Join-Path $directory ('.saef-owntracks-position-map-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-position-map-' + $token + '.bak')
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
            Remove-Item -LiteralPath $temporary -Force
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
    $temporary = Join-Path $directory ('.saef-owntracks-position-map-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-position-map-' + $token + '.bak')
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

function Write-AtomicBytes {
    param([Parameter(Mandatory = $true)][string] $Path, [Parameter(Mandatory = $true)][byte[]] $Bytes)
    $directory = Split-Path -Parent $Path
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-owntracks-position-map-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-owntracks-position-map-' + $token + '.bak')
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
        $response = Invoke-RestMethod -Uri $RpcUri -Method Post -ContentType 'application/json' `
            -Body $body -TimeoutSec ([int] $script:policy.rpcTimeoutSeconds) -Headers @{
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
        [string] $script:manifest.module.targetId -ne 'saef-owntracks-position-map' -or
        -not (Test-HexSha256 -Value $script:packageIdentitySha256) -or
        -not (Test-SymconGuid -Value ([string] $script:manifest.module.libraryGuid)) -or
        $transaction.formatVersion -ne 1 -or $script:policy.formatVersion -ne 1 -or
        [string] $transaction.adapterProfile -ne 'saef-owntracks-position-map-v1' -or
        [string] $script:policy.adapterProfile -ne 'saef-owntracks-position-map-v1' -or
        [string] $script:policy.targetId -ne 'saef-owntracks-position-map' -or
        [string] $script:manifest.module.libraryGuid -ne [string] $script:policy.libraryGuid) {
        throw [InvalidOperationException]::new('OwnTracksPositionMap adapter identity contract is invalid.')
    }
    if ([string] $transaction.ownership.mode -ne 'exactly-one-positive-module-instance' -or
        [bool] $transaction.ownership.repositoryMetadataAllowed -or
        [string] $transaction.ownership.activePackageOwner -ne 'owntracks-position-map-adapter' -or
        [string] $transaction.quiescence.writerModel -ne 'five-runtime-file-locks-and-zero-active-leases' -or
        [string] $transaction.quiescence.guard -ne 'named-adapter-mutex-plus-runtime-lock-set' -or
        [int] $transaction.state.candidateFormat -ne 2 -or
        [int] $transaction.state.rollbackFormat -ne 2 -or
        [bool] $transaction.state.formatChangeAllowed -or
        [string] $transaction.state.rollbackPreparation -ne 'fresh-v2-snapshot-byte-exact-restore' -or
        [string] $transaction.reload.method -ne 'MC_ReloadModule' -or
        [bool] $transaction.reload.serviceRestartAllowed -or
        [string] $transaction.rollback.switch -ne 'same-volume-directory-rename' -or
        [string] $transaction.rollback.stateSwitch -ne 'same-transaction-package-and-state' -or
        [string] $transaction.retention.owner -ne 'owntracks-position-map-adapter') {
        throw [InvalidOperationException]::new('OwnTracksPositionMap transaction contract is unsupported.')
    }
    foreach ($guid in @([string] $script:policy.libraryGuid, [string] $script:policy.moduleGuid)) {
        if (-not (Test-SymconGuid -Value $guid)) {
            throw [InvalidOperationException]::new('Adapter policy GUID is invalid.')
        }
    }
    if ([int] $script:policy.moduleControlInstanceId -le 0 -or
        [string] $script:policy.moduleDirectoryName -notmatch '^[A-Za-z0-9._-]{1,128}$' -or
        [string] $script:policy.mutexName -notmatch '^Global\\SAEF\.[A-Za-z0-9.]{1,96}$' -or
        [int] $script:policy.rpcTimeoutSeconds -lt 1 -or [int] $script:policy.rpcTimeoutSeconds -gt 60 -or
        [int] $script:policy.reloadTimeoutSeconds -lt 1 -or [int] $script:policy.reloadTimeoutSeconds -gt 300 -or
        [int] $script:policy.healthPollMilliseconds -lt 100 -or
        [int] $script:policy.healthPollMilliseconds -gt 5000 -or
        [int] $script:policy.quiescenceTimeoutSeconds -lt 1 -or
        [int] $script:policy.quiescenceTimeoutSeconds -gt 120 -or
        [int] $script:policy.requiredInstanceCount -ne 1 -or
        [int] $script:policy.expectedInstanceId -le 0 -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedConfigurationSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedActivePackageIdentitySha256)) -or
        [long] $script:policy.maximumCandidateBytes -lt 1 -or
        [long] $script:policy.maximumCandidateBytes -gt 268435456 -or
        [long] $script:policy.maximumStateBytes -lt 1024 -or
        [long] $script:policy.maximumStateBytes -gt 4194304) {
        throw [InvalidOperationException]::new('Adapter policy limits are invalid.')
    }
    $rootNames = @('dayCache', 'providerCache', 'tileBudget', 'providerBudget', 'missState')
    $rootPaths = @()
    foreach ($rootName in $rootNames) {
        $rootPath = [string] $script:policy.runtimeStateRoots.$rootName
        $lockName = [string] $script:policy.runtimeLockFiles.$rootName
        if (-not [IO.Path]::IsPathRooted($rootPath) -or
            $lockName -notmatch '^[A-Za-z0-9.][A-Za-z0-9._-]{0,63}$') {
            throw [InvalidOperationException]::new('Runtime state-root contract is invalid.')
        }
        $canonicalRoot = [IO.Path]::GetFullPath($rootPath).TrimEnd([char[]] @('\', '/'))
        if ($rootPaths -contains $canonicalRoot) {
            throw [InvalidOperationException]::new('Runtime state roots must be unique.')
        }
        $rootPaths += $canonicalRoot
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

function Get-DirectoryPackageIdentity {
    param([Parameter(Mandatory = $true)][string] $Path)
    Assert-SafeDirectoryTree -Path $Path
    $root = [IO.Path]::GetFullPath($Path).TrimEnd([char[]] @('\', '/')) + [IO.Path]::DirectorySeparatorChar
    $files = @(Get-ChildItem -LiteralPath $Path -File -Recurse | Sort-Object {
        $_.FullName.Substring($root.Length).Replace('\', '/')
    })
    if ($files.Count -lt 1) {
        throw [InvalidOperationException]::new('Module tree is empty.')
    }
    $identity = [Text.StringBuilder]::new()
    $totalBytes = 0L
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($root.Length).Replace('\', '/')
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

function Assert-ModuleTreeIdentity {
    param([Parameter(Mandatory = $true)][string] $Path)
    Assert-SafeDirectoryTree -Path $Path
    if (Test-Path -LiteralPath (Join-Path $Path '.git')) {
        throw [InvalidOperationException]::new('Git-managed module trees cannot be adopted by this adapter.')
    }
    $library = Get-Content -LiteralPath (Join-Path $Path 'library.json') -Raw | ConvertFrom-Json
    $module = Get-Content -LiteralPath (Join-Path $Path 'OwnTracksPositionMap\module.json') -Raw | ConvertFrom-Json
    if ([string] $library.id -ne [string] $script:policy.libraryGuid -or
        [string] $library.name -ne [string] $script:policy.libraryName -or
        [string] $library.url -ne [string] $script:policy.libraryUrl -or
        [string] $module.id -ne [string] $script:policy.moduleGuid -or
        [string] $module.name -ne [string] $script:policy.moduleName) {
        throw [InvalidOperationException]::new('Module tree ownership identity differs from policy.')
    }
}

function Get-InstanceSnapshot {
    $instanceIDs = @(Invoke-SymconRpc -Method 'IPS_GetInstanceListByModuleID' `
        -Parameters @([string] $script:policy.moduleGuid))
    if ($instanceIDs.Count -ne 1 -or [int] $instanceIDs[0] -ne [int] $script:policy.expectedInstanceId) {
        throw [InvalidOperationException]::new('OwnTracksPositionMap ownership is not exactly the pinned instance.')
    }
    $records = @()
    foreach ($rawID in @($instanceIDs | Sort-Object {[int] $_})) {
        $instanceID = [int] $rawID
        if ($instanceID -le 0 -or
            -not [bool] (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @($instanceID))) {
            throw [InvalidOperationException]::new('OwnTracksPositionMap instance identity is invalid.')
        }
        $instance = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @($instanceID)
        $object = Invoke-SymconRpc -Method 'IPS_GetObject' -Parameters @($instanceID)
        $configuration = [string] (Invoke-SymconRpc -Method 'IPS_GetConfiguration' -Parameters @($instanceID))
        if ([string] $instance.ModuleInfo.ModuleID -ne [string] $script:policy.moduleGuid -or
            [int] $object.ObjectType -ne 1 -or
            [int] $instance.InstanceStatus -notin @($script:policy.allowedInstanceStatuses) -or
            [bool] (Invoke-SymconRpc -Method 'IPS_HasChanges' -Parameters @($instanceID)) -or
            (Get-TextSha256 -Text $configuration) -ne [string] $script:policy.expectedConfigurationSha256) {
            throw [InvalidOperationException]::new('OwnTracksPositionMap instance is not in an admissible baseline state.')
        }
        $records += [ordered]@{
            instanceId = $instanceID
            configurationBase64 = [Convert]::ToBase64String([Text.UTF8Encoding]::new($false).GetBytes($configuration))
            configurationSha256 = Get-TextSha256 -Text $configuration
            objectIdent = [string] $object.ObjectIdent
            parentId = if ($object.PSObject.Properties.Name -contains 'ParentID') {
                [int] $object.ParentID
            } else { [int] $object.ObjectParentID }
            position = [int] $object.ObjectPosition
            hidden = [bool] $object.ObjectIsHidden
            disabled = [bool] $object.ObjectIsDisabled
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
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @($moduleControlID))) {
        throw [InvalidOperationException]::new('Pinned Module Control instance does not exist.')
    }
    $moduleControl = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @($moduleControlID)
    if ([int] $moduleControl.InstanceStatus -ne 102) {
        throw [InvalidOperationException]::new('Pinned Module Control instance is not active.')
    }
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_LibraryExists' -Parameters @(
        [string] $script:policy.libraryGuid
    )) -or -not [bool] (Invoke-SymconRpc -Method 'IPS_ModuleExists' -Parameters @(
        [string] $script:policy.moduleGuid
    ))) {
        throw [InvalidOperationException]::new('OwnTracksPositionMap library or module is absent.')
    }
    $library = Invoke-SymconRpc -Method 'IPS_GetLibrary' -Parameters @([string] $script:policy.libraryGuid)
    $module = Invoke-SymconRpc -Method 'IPS_GetModule' -Parameters @([string] $script:policy.moduleGuid)
    $libraryModules = @(Invoke-SymconRpc -Method 'IPS_GetLibraryModules' -Parameters @(
        [string] $script:policy.libraryGuid
    ))
    if ([string] $library.Name -ne [string] $script:policy.libraryName -or
        [string] $library.URL -ne [string] $script:policy.libraryUrl -or
        [string] $module.LibraryID -ne [string] $script:policy.libraryGuid -or
        $libraryModules.Count -ne 1 -or
        [string] $libraryModules[0] -ne [string] $script:policy.moduleGuid) {
        throw [InvalidOperationException]::new('OwnTracksPositionMap Symcon ownership is ambiguous.')
    }
}

function Assert-SnapshotPreserved {
    param([Parameter(Mandatory = $true)] $Snapshot)
    $current = @(Get-InstanceSnapshot)
    if ($current.Count -ne @($Snapshot.instances).Count) {
        throw [InvalidOperationException]::new('OwnTracksPositionMap instance inventory changed during deployment.')
    }
    for ($index = 0; $index -lt $current.Count; $index++) {
        $before = $Snapshot.instances[$index]
        $after = $current[$index]
        foreach ($field in @(
            'instanceId', 'configurationBase64', 'configurationSha256', 'objectIdent',
            'parentId', 'position', 'hidden', 'disabled', 'status'
        )) {
            if ([string] $before.$field -ne [string] $after.$field) {
                throw [InvalidOperationException]::new('OwnTracksPositionMap configuration or object state changed.')
            }
        }
    }
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
    param(
        [Parameter(Mandatory = $true)] $Snapshot,
        [Parameter(Mandatory = $true)][string] $ExpectedPackageIdentitySha256
    )
    $timer = [Diagnostics.Stopwatch]::StartNew()
    do {
        try {
            Assert-SymconOwnership
            Assert-SnapshotPreserved -Snapshot $Snapshot
            Assert-RuntimeStateSnapshot -Snapshot $script:runtimeStateSnapshot
            Assert-ModuleTreeIdentity -Path ([string] $script:policy.activeModulePath)
            if ((Get-DirectoryPackageIdentity -Path ([string] $script:policy.activeModulePath)) -ne
                $ExpectedPackageIdentitySha256) {
                throw [InvalidOperationException]::new('Active OwnTracks package identity differs after reload.')
            }
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

function Get-RuntimeStateFilePath {
    param(
        [Parameter(Mandatory = $true)][ValidateSet('tileBudget', 'providerBudget', 'missState')][string] $Name
    )
    $root = [string] $script:policy.runtimeStateRoots.$Name
    $fileName = if ($Name -eq 'missState') { 'state.json' } else { 'budget.json' }
    return Join-Path $root $fileName
}

function Read-BoundedStateJson {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        return $null
    }
    Assert-RootedLeaf -Path $Path -MaximumBytes ([long] $script:policy.maximumStateBytes)
    return Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
}

function Assert-ZeroActiveLeases {
    $now = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    foreach ($name in @('tileBudget', 'providerBudget')) {
        $state = Read-BoundedStateJson -Path (Get-RuntimeStateFilePath -Name $name)
        if ($null -eq $state) {
            continue
        }
        if ([int] $state.version -ne 1) {
            throw [InvalidOperationException]::new('OwnTracks request-budget state format is unsupported.')
        }
        foreach ($clientProperty in $state.clients.PSObject.Properties) {
            $client = $clientProperty.Value
            foreach ($leaseProperty in $client.leases.PSObject.Properties) {
                if ([long] $leaseProperty.Value -gt $now) {
                    throw [InvalidOperationException]::new('OwnTracks runtime still has an active request lease.')
                }
            }
        }
    }
    $missState = Read-BoundedStateJson -Path (Get-RuntimeStateFilePath -Name 'missState')
    if ($null -eq $missState) {
        return
    }
    if ([int] $missState.version -ne 2) {
        throw [InvalidOperationException]::new('OwnTracks miss-state requires format 2 before adapter adoption.')
    }
    foreach ($selectionProperty in $missState.selections.PSObject.Properties) {
        $selection = $selectionProperty.Value
        foreach ($reservationProperty in $selection.state.pendingReservations.PSObject.Properties) {
            if ([long] $reservationProperty.Value.expiresAt -gt $now) {
                throw [InvalidOperationException]::new('OwnTracks miss-state still has an active reservation.')
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
    $rootNames = @('dayCache', 'providerCache', 'tileBudget', 'providerBudget', 'missState')
    do {
        Exit-RuntimeQuiescence
        try {
            $order = 0
            foreach ($name in $rootNames) {
                $root = [string] $script:policy.runtimeStateRoots.$name
                Assert-SafeDirectoryTree -Path $root
                Assert-ProtectedAcl -Path $root
                $lockPath = Join-Path $root ([string] $script:policy.runtimeLockFiles.$name)
                Assert-RootedLeaf -Path $lockPath -MaximumBytes 1048576
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

function Get-RuntimeStateSnapshot {
    $records = @()
    $totalBytes = 0L
    foreach ($name in @('tileBudget', 'providerBudget', 'missState')) {
        $path = Get-RuntimeStateFilePath -Name $name
        if (-not (Test-Path -LiteralPath $path)) {
            $records += [ordered]@{ name = $name; existed = $false; bytes = 0; sha256 = ''; contentBase64 = '' }
            continue
        }
        Assert-RootedLeaf -Path $path -MaximumBytes ([long] $script:policy.maximumStateBytes)
        $bytes = [IO.File]::ReadAllBytes($path)
        try {
            $totalBytes += $bytes.Length
            if ($totalBytes -gt [long] $script:policy.maximumStateBytes) {
                throw [InvalidOperationException]::new('OwnTracks authoritative state exceeds snapshot budget.')
            }
            $records += [ordered]@{
                name = $name
                existed = $true
                bytes = $bytes.Length
                sha256 = Get-Sha256 -Path $path
                contentBase64 = [Convert]::ToBase64String($bytes)
            }
        } finally {
            [Array]::Clear($bytes, 0, $bytes.Length)
        }
    }
    Assert-ZeroActiveLeases
    return [ordered]@{ formatVersion = 1; stateFormat = 2; files = @($records) }
}

function Assert-RuntimeStateSnapshot {
    param([Parameter(Mandatory = $true)] $Snapshot)
    Assert-ZeroActiveLeases
    foreach ($record in @($Snapshot.files)) {
        $path = Get-RuntimeStateFilePath -Name ([string] $record.name)
        if ([bool] $record.existed) {
            if (-not (Test-Path -LiteralPath $path -PathType Leaf) -or
                [long] (Get-Item -LiteralPath $path).Length -ne [long] $record.bytes -or
                (Get-Sha256 -Path $path) -ne [string] $record.sha256) {
                throw [InvalidOperationException]::new('OwnTracks authoritative state changed during deployment.')
            }
        } elseif (Test-Path -LiteralPath $path) {
            throw [InvalidOperationException]::new('OwnTracks authoritative state appeared during deployment.')
        }
    }
}

function Restore-RuntimeStateSnapshot {
    param([Parameter(Mandatory = $true)] $Snapshot)
    foreach ($record in @($Snapshot.files)) {
        $path = Get-RuntimeStateFilePath -Name ([string] $record.name)
        if (-not [bool] $record.existed) {
            if (Test-Path -LiteralPath $path -PathType Leaf) {
                Remove-Item -LiteralPath $path -Force
            }
            continue
        }
        $bytes = [Convert]::FromBase64String([string] $record.contentBase64)
        try {
            Write-AtomicBytes -Path $path -Bytes $bytes
        } finally {
            [Array]::Clear($bytes, 0, $bytes.Length)
        }
    }
    Assert-RuntimeStateSnapshot -Snapshot $Snapshot
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
        Restore-RuntimeStateSnapshot -Snapshot $script:runtimeStateSnapshot
        Invoke-TargetedReload
        Restore-Configurations -Snapshot $script:snapshot
        Wait-Healthy -Snapshot $script:snapshot `
            -ExpectedPackageIdentitySha256 ([string] $script:snapshot.activePackageIdentitySha256)
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

try {
    $script:failureCode = 'contract'
    Read-Contracts
    $script:failureCode = 'path_ownership'
    Assert-SafeDirectoryTree -Path ([string] $script:policy.adapterStateRoot)
    Assert-SafeDirectoryTree -Path ([string] $script:policy.activeModulePath)
    if ([IO.Path]::GetPathRoot([string] $script:policy.adapterStateRoot) -ne
        [IO.Path]::GetPathRoot([string] $script:policy.activeModulePath)) {
        throw [InvalidOperationException]::new('Adapter state and active module must share one volume.')
    }
    Assert-ProtectedAcl -Path ([string] $script:policy.adapterStateRoot)
    Assert-ProtectedAcl -Path ([string] $script:policy.activeModulePath)
    Assert-ModuleTreeIdentity -Path ([string] $script:policy.activeModulePath)
    $activePackageIdentity = Get-DirectoryPackageIdentity -Path ([string] $script:policy.activeModulePath)
    if ($activePackageIdentity -ne [string] $script:policy.expectedActivePackageIdentitySha256) {
        throw [InvalidOperationException]::new('Active OwnTracks package identity differs from private policy.')
    }
    Assert-ProtectedAcl -Path $CandidatePath
    $null = Get-ModuleTreePackageIdentity -Path $CandidatePath
    Assert-ModuleTreeIdentity -Path $CandidatePath

    $script:mutex = [Threading.Mutex]::new($false, [string] $script:policy.mutexName)
    $script:mutexAcquired = $script:mutex.WaitOne(0)
    if (-not $script:mutexAcquired) {
        throw [InvalidOperationException]::new('Another OwnTracksPositionMap adapter operation is active.')
    }
    $script:credential = Import-MachineCredential -Path $CredentialPath
    $script:failureCode = 'symcon_ownership'
    Assert-SymconOwnership
    $instanceSnapshot = @(Get-InstanceSnapshot)
    $script:failureCode = 'quiescence'
    Enter-RuntimeQuiescence
    $script:runtimeStateSnapshot = Get-RuntimeStateSnapshot
    $script:snapshot = [ordered]@{
        formatVersion = 1
        capturedUtc = [DateTime]::UtcNow.ToString('o')
        deploymentId = [string] $script:manifest.deploymentId
        packageIdentitySha256 = $script:packageIdentitySha256
        activePackageIdentitySha256 = $activePackageIdentity
        instances = $instanceSnapshot
        runtimeState = $script:runtimeStateSnapshot
    }

    if ($Operation -eq 'preflight') {
        $script:failureCode = 'none'
        Write-AdapterStatus -Outcome 'passed' -ExitCode $ExitSuccess
        $script:finalExitCode = $ExitSuccess
    } else {
        $script:failureCode = 'rollback_preparation'
    $activeStatePath = Join-Path ([string] $script:policy.adapterStateRoot) 'active.json'
    if (Test-Path -LiteralPath $activeStatePath -PathType Leaf) {
        if ((Get-Item -LiteralPath $activeStatePath).Length -gt [int] $script:policy.maximumStateBytes) {
            throw [InvalidOperationException]::new('Existing active adapter state is oversized.')
        }
        $script:previousActiveState = Get-Content -LiteralPath $activeStatePath -Raw
    }
    $transactionID = [string] $script:manifest.deploymentId + '-' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ')
    $script:transactionRoot = Join-Path ([string] $script:policy.adapterStateRoot) $transactionID
    $candidateTransactionPath = Join-Path $script:transactionRoot 'candidate'
    $script:rollbackPath = Join-Path $script:transactionRoot 'rollback'
    $script:failedCandidatePath = Join-Path $script:transactionRoot 'failed-candidate'
    [IO.Directory]::CreateDirectory($script:transactionRoot) | Out-Null
    Copy-CandidateToTransaction -Destination $candidateTransactionPath
    Write-AtomicJson -Path (Join-Path $script:transactionRoot 'snapshot.json') -Value $script:snapshot

    $script:failureCode = 'package_switch'
    [IO.Directory]::Move([string] $script:policy.activeModulePath, $script:rollbackPath)
    $script:activeMoved = $true
    [IO.Directory]::Move($candidateTransactionPath, [string] $script:policy.activeModulePath)
    $script:candidateActivated = $true

    $script:failureCode = 'targeted_reload'
    Invoke-TargetedReload
    $script:failureCode = 'post_activation_health'
    $null = Get-ModuleTreePackageIdentity -Path ([string] $script:policy.activeModulePath)
    Wait-Healthy -Snapshot $script:snapshot -ExpectedPackageIdentitySha256 $script:packageIdentitySha256
    Assert-RuntimeStateSnapshot -Snapshot $script:runtimeStateSnapshot
    $activeRecord = [ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-owntracks-position-map-v1'
        deploymentId = [string] $script:manifest.deploymentId
        packageIdentitySha256 = $script:packageIdentitySha256
        activatedUtc = [DateTime]::UtcNow.ToString('o')
        transactionDirectoryName = $transactionID
        rollbackDirectoryName = 'rollback'
        snapshotFileName = 'snapshot.json'
    }
    Write-AtomicJson -Path (Join-Path $script:transactionRoot 'transaction.json') -Value ([ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-owntracks-position-map-v1'
        transactionDirectoryName = $transactionID
        deploymentId = [string] $script:manifest.deploymentId
        packageIdentitySha256 = $script:packageIdentitySha256
        completedUtc = [DateTime]::UtcNow.ToString('o')
        outcome = 'activated'
    })
    Write-AtomicJson -Path $activeStatePath -Value $activeRecord
    $script:activeStateWritten = $true
        $script:failureCode = 'none'
        Write-AdapterStatus -Outcome 'activated' -ExitCode $ExitSuccess
        $script:finalExitCode = $ExitSuccess
    }
} catch {
    if ($Operation -eq 'activate' -and $script:activationAttempted) {
        $originalFailureCode = $script:failureCode
        Invoke-Rollback
        $script:failureCode = $originalFailureCode
        if ($script:rollbackSucceeded) {
            if ($null -ne $script:transactionRoot -and
                (Test-Path -LiteralPath $script:transactionRoot -PathType Container)) {
                Write-AtomicJson -Path (Join-Path $script:transactionRoot 'transaction.json') -Value ([ordered]@{
                    formatVersion = 1
                    adapterProfile = 'saef-owntracks-position-map-v1'
                    transactionDirectoryName = Split-Path -Leaf $script:transactionRoot
                    deploymentId = [string] $script:manifest.deploymentId
                    packageIdentitySha256 = $script:packageIdentitySha256
                    completedUtc = [DateTime]::UtcNow.ToString('o')
                    outcome = 'rolled_back'
                })
            }
            Write-AdapterStatus -Outcome 'rolled_back' -ExitCode $ExitRolledBack
            $script:finalExitCode = $ExitRolledBack
        } else {
            if ($null -ne $script:transactionRoot -and
                (Test-Path -LiteralPath $script:transactionRoot -PathType Container)) {
                Write-AtomicJson -Path (Join-Path $script:transactionRoot 'transaction.json') -Value ([ordered]@{
                    formatVersion = 1
                    adapterProfile = 'saef-owntracks-position-map-v1'
                    transactionDirectoryName = Split-Path -Leaf $script:transactionRoot
                    deploymentId = [string] $script:manifest.deploymentId
                    packageIdentitySha256 = $script:packageIdentitySha256
                    completedUtc = [DateTime]::UtcNow.ToString('o')
                    outcome = 'manual_recovery_required'
                })
            }
            Write-AdapterStatus -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery
            $script:finalExitCode = $ExitManualRecovery
        }
    } else {
        Write-AdapterStatus -Outcome 'failed' -ExitCode $ExitPreflightFailed
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
    $script:credential = $null
}

exit $script:finalExitCode
