[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [Uri] $RpcUri,

    [Parameter(Mandatory = $true)]
    [string] $CredentialPath,

    [Parameter(Mandatory = $true)]
    [string] $ReconcilerPath,

    [Parameter(Mandatory = $true)]
    [string] $ExpectedReconcilerSha256,

    [Parameter(Mandatory = $true)]
    [string] $FilesetPath,

    [Parameter(Mandatory = $true)]
    [ValidateRange(1, 2147483647)]
    [int] $ParentID,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_]{1,128}$')]
    [string] $Ident,

    [Parameter(Mandatory = $true)]
    [ValidateLength(1, 255)]
    [string] $Name,

    [Parameter()]
    [int] $Position = 0,

    [Parameter(Mandatory = $true)]
    [string] $StatePath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath,

    [Parameter()]
    [switch] $PreflightOnly
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitReconcileFailed = 20
$maximumStateBytes = 131072
$maximumRpcTimeoutSeconds = 30
$maximumParentChildren = 4096
$failureCode = 'request'

function Test-HexSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)

    return $Value -match '^[a-f0-9]{64}$'
}

function Write-AtomicText {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $Text
    )

    $directory = Split-Path -Parent $Path
    if ([string]::IsNullOrWhiteSpace($directory) -or
        -not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [System.IO.DirectoryNotFoundException]::new('Runtime mirror output directory is missing.')
    }
    $identifier = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-runtime-mirror-' + $identifier + '.tmp')
    $backup = Join-Path $directory ('.saef-runtime-mirror-' + $identifier + '.bak')
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

function Write-MirrorStatus {
    param(
        [Parameter(Mandatory = $true)][string] $Phase,
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][hashtable] $Details = @{}
    )

    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = $Phase
        outcome = $Outcome
        exitCode = $ExitCode
    }
    foreach ($key in $Details.Keys) {
        $status[$key] = $Details[$key]
    }
    Write-AtomicText -Path $StatusPath -Text (($status | ConvertTo-Json -Depth 5) + [Environment]::NewLine)
}

function Import-MachineCredential {
    param([Parameter(Mandatory = $true)][string] $Path)

    Add-Type -AssemblyName System.Security -ErrorAction Stop
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path).Length -gt $maximumStateBytes) {
        throw [System.IO.FileNotFoundException]::new('Credential source is missing or invalid.')
    }
    $record = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($record.formatVersion -ne 1 -or $record.protectionScope -ne 'LocalMachine' -or
        $record.username -isnot [string] -or [string]::IsNullOrWhiteSpace([string] $record.username) -or
        $record.protectedPasswordBase64 -isnot [string]) {
        throw [System.InvalidOperationException]::new('Credential source contract is invalid.')
    }

    $entropy = [Text.Encoding]::UTF8.GetBytes('SAEF.DeploymentChannel.RpcCredential.v1')
    $protectedBytes = $null
    $passwordBytes = $null
    try {
        $protectedBytes = [Convert]::FromBase64String([string] $record.protectedPasswordBase64)
        $passwordBytes = [Security.Cryptography.ProtectedData]::Unprotect(
            $protectedBytes,
            $entropy,
            [Security.Cryptography.DataProtectionScope]::LocalMachine
        )
        $password = [Text.Encoding]::UTF8.GetString($passwordBytes)
        if ([string]::IsNullOrEmpty($password)) {
            throw [System.InvalidOperationException]::new('Credential password is empty.')
        }
        return [PSCredential]::new(
            [string] $record.username,
            (ConvertTo-SecureString -String $password -AsPlainText -Force)
        )
    } finally {
        if ($null -ne $passwordBytes) {
            [Array]::Clear($passwordBytes, 0, $passwordBytes.Length)
        }
        if ($null -ne $protectedBytes) {
            [Array]::Clear($protectedBytes, 0, $protectedBytes.Length)
        }
        [Array]::Clear($entropy, 0, $entropy.Length)
        $password = $null
    }
}

function Invoke-SymconRpc {
    param(
        [Parameter(Mandatory = $true)][PSCredential] $Credential,
        [Parameter(Mandatory = $true)][string] $Method,
        [Parameter()][object[]] $Parameters = @()
    )

    $body = [ordered]@{
        jsonrpc = '2.0'
        method = $Method
        params = $Parameters
        id = 1
    } | ConvertTo-Json -Depth 8 -Compress
    $networkCredential = $Credential.GetNetworkCredential()
    $authorizationBytes = [Text.Encoding]::UTF8.GetBytes(
        $Credential.UserName + ':' + $networkCredential.Password
    )
    try {
        $response = Invoke-RestMethod -Uri $RpcUri -Method Post -ContentType 'application/json' `
            -Body $body -TimeoutSec $maximumRpcTimeoutSeconds -Headers @{
                Authorization = 'Basic ' + [Convert]::ToBase64String($authorizationBytes)
            }
    } finally {
        [Array]::Clear($authorizationBytes, 0, $authorizationBytes.Length)
        $networkCredential = $null
    }
    if ($response.PSObject.Properties.Name -contains 'error' -and $null -ne $response.error) {
        throw [System.InvalidOperationException]::new('Symcon RPC returned an error.')
    }
    if ($response.PSObject.Properties.Name -notcontains 'result') {
        throw [System.InvalidOperationException]::new('Symcon RPC response has no result.')
    }
    return $response.result
}

function Read-MirrorState {
    if (-not (Test-Path -LiteralPath $StatePath -PathType Leaf)) {
        return $null
    }
    if ((Get-Item -LiteralPath $StatePath).Length -gt $maximumStateBytes) {
        throw [System.InvalidOperationException]::new('Runtime mirror state exceeds its byte limit.')
    }
    $state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
    if ($state.formatVersion -ne 1 -or [int] $state.parentID -ne $ParentID -or
        [string] $state.ident -ne $Ident -or [int] $state.scriptID -le 0 -or
        -not (Test-HexSha256 -Value ([string] $state.filesetSha256)) -or
        -not (Test-HexSha256 -Value ([string] $state.mirrorSha256))) {
        throw [System.InvalidOperationException]::new('Runtime mirror state contract is invalid.')
    }
    return $state
}

function Assert-MirrorOwnership {
    param(
        [Parameter(Mandatory = $true)][PSCredential] $Credential,
        [Parameter()] $State
    )

    if (-not [bool] (Invoke-SymconRpc -Credential $Credential -Method 'IPS_ObjectExists' -Parameters @($ParentID))) {
        throw [System.InvalidOperationException]::new('Runtime mirror parent does not exist.')
    }
    if ($null -eq $State) {
        $parentChildren = @(Invoke-SymconRpc -Credential $Credential -Method 'IPS_GetChildrenIDs' `
            -Parameters @($ParentID))
        if ($parentChildren.Count -gt $maximumParentChildren) {
            throw [System.InvalidOperationException]::new('Runtime mirror parent exceeds its child limit.')
        }
        foreach ($childID in $parentChildren) {
            $child = Invoke-SymconRpc -Credential $Credential -Method 'IPS_GetObject' `
                -Parameters @([int] $childID)
            if ([string] $child.ObjectIdent -eq $Ident) {
                throw [System.InvalidOperationException]::new(
                    'Existing runtime mirror cannot be adopted without pinned deployment state.'
                )
            }
        }
        return
    }
    $scriptID = [int] $State.scriptID
    if (-not [bool] (Invoke-SymconRpc -Credential $Credential -Method 'IPS_ObjectExists' -Parameters @($scriptID))) {
        throw [System.InvalidOperationException]::new('Pinned runtime mirror no longer exists.')
    }
    $object = Invoke-SymconRpc -Credential $Credential -Method 'IPS_GetObject' -Parameters @($scriptID)
    $objectParentID = if ($object.PSObject.Properties.Name -contains 'ParentID') {
        [int] $object.ParentID
    } else {
        [int] $object.ObjectParentID
    }
    if ([int] $object.ObjectType -ne 3 -or $objectParentID -ne $ParentID -or
        [string] $object.ObjectIdent -ne $Ident) {
        throw [System.InvalidOperationException]::new('Pinned runtime mirror has ownership drift.')
    }
    $children = @(Invoke-SymconRpc -Credential $Credential -Method 'IPS_GetChildrenIDs' -Parameters @($scriptID))
    if ($children.Count -ne 0) {
        throw [System.InvalidOperationException]::new('Pinned runtime mirror owns child objects.')
    }
}

$phase = if ($PreflightOnly) { 'preflight' } else { 'reconcile' }
$exitCode = if ($PreflightOnly) { $ExitPreflightFailed } else { $ExitReconcileFailed }
try {
    $failureCode = 'path_contract'
    foreach ($path in @($CredentialPath, $ReconcilerPath, $FilesetPath, $StatePath, $StatusPath)) {
        if (-not [IO.Path]::IsPathRooted($path)) {
            throw [System.InvalidOperationException]::new('Runtime mirror paths must be absolute.')
        }
    }
    $failureCode = 'rpc_endpoint'
    if ($RpcUri.Scheme -notin @('http', 'https') -or $RpcUri.Host -notin @('127.0.0.1', 'localhost', '::1')) {
        throw [System.InvalidOperationException]::new('RPC URI must use an HTTP loopback endpoint.')
    }
    $failureCode = 'reconciler_integrity'
    if (-not (Test-HexSha256 -Value $ExpectedReconcilerSha256) -or
        -not (Test-Path -LiteralPath $ReconcilerPath -PathType Leaf) -or
        (Get-FileHash -LiteralPath $ReconcilerPath -Algorithm SHA256).Hash.ToLowerInvariant() -ne
            $ExpectedReconcilerSha256) {
        throw [System.InvalidOperationException]::new('Pinned runtime mirror reconciler hash mismatch.')
    }
    $failureCode = 'fileset_provenance'
    foreach ($provenanceName in @('fileset.sources.json', 'fileset.sha256')) {
        if (-not (Test-Path -LiteralPath (Join-Path $FilesetPath $provenanceName) -PathType Leaf)) {
            throw [System.IO.FileNotFoundException]::new('Runtime mirror fileset provenance is missing.')
        }
    }

    $failureCode = 'credential_source'
    $credential = Import-MachineCredential -Path $CredentialPath
    $failureCode = 'mirror_state'
    $state = Read-MirrorState
    $failureCode = 'mirror_ownership'
    Assert-MirrorOwnership -Credential $credential -State $state
    if ($PreflightOnly) {
        Write-MirrorStatus -Phase 'preflight' -Outcome 'passed' -ExitCode $ExitSuccess `
            -Details @{ mutationAttempted = $false; existingMirror = $null -ne $state }
        exit $ExitSuccess
    }

    $configuration = [ordered]@{
        filesetPath = $FilesetPath
        parentID = $ParentID
        ident = $Ident
        defaultName = $Name
        defaultPosition = $Position
    }
    if ($null -ne $state) {
        $configuration['expectedScriptID'] = [int] $state.scriptID
    }
    $failureCode = 'reconcile_execution'
    $configurationJson = $configuration | ConvertTo-Json -Depth 4 -Compress
    $reconcilerBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($ReconcilerPath))
    $configurationBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($configurationJson))
    $scriptText = @"
<?php
declare(strict_types=1);
require_once base64_decode('$reconcilerBase64', true);
`$configuration = json_decode(base64_decode('$configurationBase64', true), true, 32, JSON_THROW_ON_ERROR);
echo json_encode(\SAEF\Deployment\SaefRuntimeSourceMirror::reconcile(`$configuration), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
"@
    $resultText = [string] (Invoke-SymconRpc -Credential $credential -Method 'IPS_RunScriptTextWait' `
        -Parameters @($scriptText))
    if ($resultText.Length -gt $maximumStateBytes) {
        throw [System.InvalidOperationException]::new('Runtime mirror result exceeds its byte limit.')
    }
    $failureCode = 'reconcile_result'
    $result = $resultText | ConvertFrom-Json
    if ([int] $result.scriptID -le 0 -or [string] $result.outcome -notin @('created', 'updated', 'unchanged') -or
        -not (Test-HexSha256 -Value ([string] $result.filesetSha256)) -or
        -not (Test-HexSha256 -Value ([string] $result.mirrorSha256)) -or
        [int] $result.helperSourceCount -le 0) {
        throw [System.InvalidOperationException]::new('Runtime mirror result contract is invalid.')
    }
    $newState = [ordered]@{
        formatVersion = 1
        parentID = $ParentID
        ident = $Ident
        scriptID = [int] $result.scriptID
        filesetSha256 = [string] $result.filesetSha256
        mirrorSha256 = [string] $result.mirrorSha256
    }
    $failureCode = 'state_commit'
    Write-AtomicText -Path $StatePath -Text (($newState | ConvertTo-Json -Depth 4) + [Environment]::NewLine)
    Write-MirrorStatus -Phase 'reconcile' -Outcome ([string] $result.outcome) -ExitCode $ExitSuccess `
        -Details @{
            scriptID = [int] $result.scriptID
            filesetSha256 = [string] $result.filesetSha256
            mirrorSha256 = [string] $result.mirrorSha256
            helperSourceCount = [int] $result.helperSourceCount
            mutationAttempted = [string] $result.outcome -ne 'unchanged'
        }
    exit $ExitSuccess
} catch {
    Write-MirrorStatus -Phase $phase -Outcome 'failed' -ExitCode $exitCode `
        -Details @{
            errorType = $_.Exception.GetType().FullName
            failureCode = $failureCode
            mutationAttempted = -not $PreflightOnly
        }
    exit $exitCode
}
