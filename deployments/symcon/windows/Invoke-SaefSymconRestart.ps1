[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [Uri] $RpcUri,

    [Parameter()]
    [PSCredential] $Credential,

    [Parameter()]
    [string] $CredentialPath,

    [Parameter()]
    [string] $PolicyPath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath,

    [Parameter()]
    [string] $ServiceName,

    [Parameter()]
    [switch] $PreflightOnly,

    [Parameter()]
    [string] $ActiveBootstrapPath,

    [Parameter()]
    [string] $ExpectedActiveBootstrapSha256,

    [Parameter()]
    [string] $RollbackBootstrapPath,

    [Parameter()]
    [string] $ExpectedRollbackBootstrapSha256,

    [Parameter()]
    [int] $RuntimeHealthProbeScriptID = 0,

    [Parameter()]
    [string] $ExpectedRuntimeHealthProbeSha256,

    [Parameter()]
    [string] $RequiredRuntimeFunctionsBase64
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($PolicyPath)) {
    $PolicyPath = Join-Path $PSScriptRoot 'restart-policy.json'
}

$ExitActivated = 0
$ExitPreflightFailed = 10
$ExitActivationFailed = 20
$ExitRolledBack = 30
$ExitRollbackFailed = 40
$script:runtimeHealthCheck = 'not_started'

function Read-RestartPolicy {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Restart policy is missing.')
    }

    $policy = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($policy.formatVersion -ne 1) {
        throw [System.InvalidOperationException]::new('Unsupported restart policy format.')
    }

    foreach ($name in @(
        'serviceName',
        'expectedReadyRunlevel',
        'pollIntervalMilliseconds',
        'rpcTimeoutSeconds',
        'stopTimeoutSeconds',
        'startTimeoutSeconds',
        'readyTimeoutSeconds',
        'rollbackReadyTimeoutSeconds'
    )) {
        if ($null -eq $policy.$name) {
            throw [System.InvalidOperationException]::new('Restart policy is incomplete.')
        }
    }

    foreach ($name in @(
        'pollIntervalMilliseconds',
        'rpcTimeoutSeconds',
        'stopTimeoutSeconds',
        'startTimeoutSeconds',
        'readyTimeoutSeconds',
        'rollbackReadyTimeoutSeconds'
    )) {
        if ([int] $policy.$name -le 0) {
            throw [System.InvalidOperationException]::new('Restart policy contains a non-positive limit.')
        }
    }

    return $policy
}

function Import-MachineCredential {
    param([Parameter(Mandatory = $true)][string] $Path)

    Add-Type -AssemblyName System.Security -ErrorAction Stop
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Credential file is missing.')
    }
    if ((Get-Item -LiteralPath $Path).Length -gt 131072) {
        throw [System.InvalidOperationException]::new('Credential file exceeds its byte limit.')
    }
    $record = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($record.formatVersion -ne 1 -or $record.protectionScope -ne 'LocalMachine' -or
        $record.username -isnot [string] -or [string]::IsNullOrWhiteSpace([string] $record.username) -or
        ([string] $record.username).Length -gt 512 -or $record.protectedPasswordBase64 -isnot [string] -or
        ([string] $record.protectedPasswordBase64).Length -gt 131072) {
        throw [System.InvalidOperationException]::new('Credential file contract is invalid.')
    }

    $entropy = [Text.Encoding]::UTF8.GetBytes('SAEF.DeploymentChannel.RpcCredential.v1')
    $protectedBytes = $null
    $passwordBytes = $null
    $passwordText = $null
    try {
        $protectedBytes = [Convert]::FromBase64String([string] $record.protectedPasswordBase64)
        $passwordBytes = [Security.Cryptography.ProtectedData]::Unprotect(
            $protectedBytes,
            $entropy,
            [Security.Cryptography.DataProtectionScope]::LocalMachine
        )
        $passwordText = [Text.Encoding]::UTF8.GetString($passwordBytes)
        if ([string]::IsNullOrEmpty($passwordText)) {
            throw [System.InvalidOperationException]::new('Credential password is empty.')
        }
        $securePassword = ConvertTo-SecureString -String $passwordText -AsPlainText -Force
        return [PSCredential]::new([string] $record.username, $securePassword)
    } finally {
        if ($null -ne $passwordBytes) {
            [Array]::Clear($passwordBytes, 0, $passwordBytes.Length)
        }
        if ($null -ne $protectedBytes) {
            [Array]::Clear($protectedBytes, 0, $protectedBytes.Length)
        }
        [Array]::Clear($entropy, 0, $entropy.Length)
        $passwordText = $null
    }
}

function Write-RestartStatus {
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

    $directory = Split-Path -Parent $StatusPath
    if ([string]::IsNullOrWhiteSpace($directory)) {
        throw [System.InvalidOperationException]::new('Status path requires a parent directory.')
    }
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [System.IO.DirectoryNotFoundException]::new('Status directory is missing.')
    }

    $identifier = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-status-' + $identifier + '.tmp')
    $replacementBackup = Join-Path $directory ('.saef-status-' + $identifier + '.bak')
    try {
        $json = $status | ConvertTo-Json -Depth 4
        [System.IO.File]::WriteAllText($temporary, $json + [Environment]::NewLine)
        if (Test-Path -LiteralPath $StatusPath -PathType Leaf) {
            [System.IO.File]::Replace(
                [string] $temporary,
                [string] $StatusPath,
                [string] $replacementBackup
            )
        } else {
            [System.IO.File]::Move($temporary, $StatusPath)
        }
    } finally {
        if (Test-Path -LiteralPath $temporary -PathType Leaf) {
            Remove-Item -LiteralPath $temporary -Force
        }
        if (Test-Path -LiteralPath $replacementBackup -PathType Leaf) {
            Remove-Item -LiteralPath $replacementBackup -Force -ErrorAction SilentlyContinue
        }
    }
}

function Get-SymconService {
    param([Parameter(Mandatory = $true)][string] $Name)

    return Get-Service -Name $Name -ErrorAction Stop
}

function Wait-ServiceState {
    param(
        [Parameter(Mandatory = $true)][string] $Name,
        [Parameter(Mandatory = $true)][System.ServiceProcess.ServiceControllerStatus] $DesiredState,
        [Parameter(Mandatory = $true)][int] $TimeoutSeconds,
        [Parameter(Mandatory = $true)][int] $PollIntervalMilliseconds
    )

    $timer = [System.Diagnostics.Stopwatch]::StartNew()
    do {
        $service = Get-SymconService -Name $Name
        if ($service.Status -eq $DesiredState) {
            return $service
        }
        [System.Threading.Thread]::Sleep($PollIntervalMilliseconds)
    } while ($timer.Elapsed.TotalSeconds -lt $TimeoutSeconds)

    throw [System.TimeoutException]::new('Service state transition timed out.')
}

function Stop-SymconService {
    param(
        [Parameter(Mandatory = $true)][string] $Name,
        [Parameter(Mandatory = $true)][int] $TimeoutSeconds,
        [Parameter(Mandatory = $true)][int] $PollIntervalMilliseconds
    )

    $service = Get-SymconService -Name $Name
    if ($service.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Stopped -and
        $service.Status -ne [System.ServiceProcess.ServiceControllerStatus]::StopPending) {
        Stop-Service -Name $Name -ErrorAction Stop
    }
    $waitParameters = @{
        Name = $Name
        DesiredState = [System.ServiceProcess.ServiceControllerStatus]::Stopped
        TimeoutSeconds = $TimeoutSeconds
        PollIntervalMilliseconds = $PollIntervalMilliseconds
    }
    return Wait-ServiceState @waitParameters
}

function Start-SymconService {
    param(
        [Parameter(Mandatory = $true)][string] $Name,
        [Parameter(Mandatory = $true)][int] $TimeoutSeconds,
        [Parameter(Mandatory = $true)][int] $PollIntervalMilliseconds
    )

    $service = Get-SymconService -Name $Name
    if ($service.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Running -and
        $service.Status -ne [System.ServiceProcess.ServiceControllerStatus]::StartPending) {
        Start-Service -Name $Name -ErrorAction Stop
    }
    $waitParameters = @{
        Name = $Name
        DesiredState = [System.ServiceProcess.ServiceControllerStatus]::Running
        TimeoutSeconds = $TimeoutSeconds
        PollIntervalMilliseconds = $PollIntervalMilliseconds
    }
    return Wait-ServiceState @waitParameters
}

function Invoke-SymconRpc {
    param(
        [Parameter(Mandatory = $true)][string] $Method,
        [Parameter(Mandatory = $true)][int] $TimeoutSeconds,
        [Parameter()][array] $Parameters = @()
    )

    $body = [ordered]@{
        jsonrpc = '2.0'
        method = $Method
        params = $Parameters
        id = 1
    } | ConvertTo-Json -Depth 8 -Compress

    $request = @{
        Uri = $RpcUri
        Method = 'Post'
        ContentType = 'application/json'
        Body = $body
        TimeoutSec = $TimeoutSeconds
        ErrorAction = 'Stop'
    }
    $authorizationBytes = $null
    $authorizationText = $null
    $networkCredential = $null
    try {
        if ($null -ne $Credential) {
            $networkCredential = $Credential.GetNetworkCredential()
            $authorizationText = $Credential.UserName + ':' + $networkCredential.Password
            $authorizationBytes = [System.Text.Encoding]::UTF8.GetBytes($authorizationText)
            $request['Headers'] = @{
                Authorization = 'Basic ' + [Convert]::ToBase64String($authorizationBytes)
            }
        }

        $response = Invoke-RestMethod @request
    } finally {
        if ($null -ne $authorizationBytes) {
            [Array]::Clear($authorizationBytes, 0, $authorizationBytes.Length)
        }
        $authorizationText = $null
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

function Invoke-RuntimeHealthProbe {
    param([Parameter(Mandatory = $true)][int] $TimeoutSeconds)

    $script:runtimeHealthCheck = 'parameters'
    if ($RuntimeHealthProbeScriptID -le 0 -or
        $ExpectedRuntimeHealthProbeSha256 -notmatch '^[a-f0-9]{64}$' -or
        [string]::IsNullOrWhiteSpace($RequiredRuntimeFunctionsBase64) -or
        $RequiredRuntimeFunctionsBase64.Length -gt 65536) {
        throw [System.InvalidOperationException]::new('Runtime health probe parameters are invalid.')
    }
    $script:runtimeHealthCheck = 'contract_decode'
    $contractBytes = [Convert]::FromBase64String($RequiredRuntimeFunctionsBase64)
    try {
        if ($contractBytes.Length -le 0 -or $contractBytes.Length -gt 32768) {
            throw [System.InvalidOperationException]::new('Runtime health contract is outside policy.')
        }
        $contractJson = [Text.UTF8Encoding]::new($false, $true).GetString($contractBytes)
        $decodedFunctions = $contractJson | ConvertFrom-Json
        if ($decodedFunctions -isnot [System.Array]) {
            throw [System.InvalidOperationException]::new('Runtime health function list is not an array.')
        }
        $requiredFunctions = [object[]] $decodedFunctions
        if ($requiredFunctions.Count -lt 1 -or $requiredFunctions.Count -gt 256) {
            throw [System.InvalidOperationException]::new('Runtime health function list is outside policy.')
        }
        $contractSha256 = [BitConverter]::ToString(
            [Security.Cryptography.SHA256]::Create().ComputeHash($contractBytes)
        ).Replace('-', '').ToLowerInvariant()
    } finally {
        [Array]::Clear($contractBytes, 0, $contractBytes.Length)
    }

    $script:runtimeHealthCheck = 'object_exists'
    if (-not [bool] (Invoke-SymconRpc -Method 'IPS_ObjectExists' -Parameters @($RuntimeHealthProbeScriptID) `
        -TimeoutSeconds $TimeoutSeconds)) {
        throw [System.InvalidOperationException]::new('Runtime health probe script is missing.')
    }
    $script:runtimeHealthCheck = 'object_type'
    $probeObject = Invoke-SymconRpc -Method 'IPS_GetObject' -Parameters @($RuntimeHealthProbeScriptID) `
        -TimeoutSeconds $TimeoutSeconds
    if ([int] $probeObject.ObjectType -ne 3) {
        throw [System.InvalidOperationException]::new('Runtime health probe object type is invalid.')
    }
    $script:runtimeHealthCheck = 'source_hash'
    $probeSource = [string] (Invoke-SymconRpc -Method 'IPS_GetScriptContent' `
        -Parameters @($RuntimeHealthProbeScriptID) -TimeoutSeconds $TimeoutSeconds)
    $probeSourceBytes = [Text.Encoding]::UTF8.GetBytes($probeSource)
    try {
        $probeSha256 = [BitConverter]::ToString(
            [Security.Cryptography.SHA256]::Create().ComputeHash($probeSourceBytes)
        ).Replace('-', '').ToLowerInvariant()
    } finally {
        [Array]::Clear($probeSourceBytes, 0, $probeSourceBytes.Length)
    }
    if ($probeSha256 -ne $ExpectedRuntimeHealthProbeSha256) {
        throw [System.InvalidOperationException]::new('Runtime health probe source hash mismatch.')
    }
    $script:runtimeHealthCheck = 'execution'
    $probeResultText = [string] (Invoke-SymconRpc -Method 'IPS_RunScriptWaitEx' `
        -Parameters @($RuntimeHealthProbeScriptID, @{ SAEF_RUNTIME_HEALTH_CONTRACT = $contractJson }) `
        -TimeoutSeconds $TimeoutSeconds)
    if ($probeResultText.Length -le 0 -or $probeResultText.Length -gt 8192) {
        throw [System.InvalidOperationException]::new('Runtime health probe result is outside policy.')
    }
    $script:runtimeHealthCheck = 'result_contract'
    $probeResult = $probeResultText | ConvertFrom-Json
    if ($probeResult.formatVersion -ne 1 -or $probeResult.success -ne $true -or
        [int] $probeResult.requiredFunctionCount -ne $requiredFunctions.Count -or
        [int] $probeResult.missingFunctionCount -ne 0 -or
        [string] $probeResult.contractSha256 -ne $contractSha256) {
        throw [System.InvalidOperationException]::new('Runtime health probe rejected the active function contract.')
    }
    $script:runtimeHealthCheck = 'passed'
}

function Wait-SymconReady {
    param(
        [Parameter(Mandatory = $true)][long] $PreviousKernelStartTime,
        [Parameter(Mandatory = $true)][int] $ExpectedRunlevel,
        [Parameter(Mandatory = $true)][int] $TimeoutSeconds,
        [Parameter(Mandatory = $true)][int] $RpcTimeoutSeconds,
        [Parameter(Mandatory = $true)][int] $PollIntervalMilliseconds
    )

    $timer = [System.Diagnostics.Stopwatch]::StartNew()
    do {
        try {
            $runlevel = [int] (Invoke-SymconRpc -Method 'IPS_GetKernelRunlevel' -TimeoutSeconds $RpcTimeoutSeconds)
            if ($runlevel -eq $ExpectedRunlevel) {
                $startTime = [long] (Invoke-SymconRpc -Method 'IPS_GetKernelStartTime' -TimeoutSeconds $RpcTimeoutSeconds)
                if ($startTime -gt $PreviousKernelStartTime) {
                    return [ordered]@{
                        runlevel = $runlevel
                        kernelStartTime = $startTime
                    }
                }
            }
        } catch {
            # Connection failures are expected while the service initializes.
        }
        [System.Threading.Thread]::Sleep($PollIntervalMilliseconds)
    } while ($timer.Elapsed.TotalSeconds -lt $TimeoutSeconds)

    throw [System.TimeoutException]::new('Symcon ready probe timed out.')
}

function Test-Sha256 {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $Expected
    )

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return $false
    }
    $actual = (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
    return $actual -eq $Expected.ToLowerInvariant()
}

function Restore-Bootstrap {
    param(
        [Parameter(Mandatory = $true)][string] $SourcePath,
        [Parameter(Mandatory = $true)][string] $TargetPath,
        [Parameter(Mandatory = $true)][string] $ExpectedSourceSha256
    )

    if (-not (Test-Sha256 -Path $SourcePath -Expected $ExpectedSourceSha256)) {
        throw [System.InvalidOperationException]::new('Rollback bootstrap hash mismatch.')
    }
    if (-not (Test-Path -LiteralPath $TargetPath -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Active bootstrap target is missing.')
    }

    $directory = Split-Path -Parent $TargetPath
    $identifier = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-rollback-' + $identifier + '.tmp')
    $replacementBackup = Join-Path $directory ('.saef-rollback-' + $identifier + '.bak')
    try {
        Copy-Item -LiteralPath $SourcePath -Destination $temporary
        if (-not (Test-Sha256 -Path $temporary -Expected $ExpectedSourceSha256)) {
            throw [System.InvalidOperationException]::new('Temporary rollback copy hash mismatch.')
        }
        [System.IO.File]::Replace(
            [string] $temporary,
            [string] $TargetPath,
            [string] $replacementBackup
        )
        if (-not (Test-Sha256 -Path $TargetPath -Expected $ExpectedSourceSha256)) {
            throw [System.InvalidOperationException]::new('Restored bootstrap hash mismatch.')
        }
    } finally {
        if (Test-Path -LiteralPath $temporary -PathType Leaf) {
            Remove-Item -LiteralPath $temporary -Force
        }
        if (Test-Path -LiteralPath $replacementBackup -PathType Leaf) {
            Remove-Item -LiteralPath $replacementBackup -Force -ErrorAction SilentlyContinue
        }
    }
}

function Restart-SymconAndWait {
    param(
        [Parameter(Mandatory = $true)][string] $Name,
        [Parameter(Mandatory = $true)][long] $PreviousKernelStartTime,
        [Parameter(Mandatory = $true)][int] $ReadyTimeoutSeconds,
        [Parameter(Mandatory = $true)] $Policy
    )

    $stopParameters = @{
        Name = $Name
        TimeoutSeconds = [int] $Policy.stopTimeoutSeconds
        PollIntervalMilliseconds = [int] $Policy.pollIntervalMilliseconds
    }
    Stop-SymconService @stopParameters | Out-Null

    $startParameters = @{
        Name = $Name
        TimeoutSeconds = [int] $Policy.startTimeoutSeconds
        PollIntervalMilliseconds = [int] $Policy.pollIntervalMilliseconds
    }
    Start-SymconService @startParameters | Out-Null

    $readyParameters = @{
        PreviousKernelStartTime = $PreviousKernelStartTime
        ExpectedRunlevel = [int] $Policy.expectedReadyRunlevel
        TimeoutSeconds = $ReadyTimeoutSeconds
        RpcTimeoutSeconds = [int] $Policy.rpcTimeoutSeconds
        PollIntervalMilliseconds = [int] $Policy.pollIntervalMilliseconds
    }
    return Wait-SymconReady @readyParameters
}

$policy = $null
$effectiveServiceName = $null
$baselineStartTime = $null
$rollbackConfigured = $false
$runtimeHealthConfigured = $RuntimeHealthProbeScriptID -gt 0 -or
    -not [string]::IsNullOrWhiteSpace($ExpectedRuntimeHealthProbeSha256) -or
    -not [string]::IsNullOrWhiteSpace($RequiredRuntimeFunctionsBase64)
$preflightCheck = 'policy'

try {
    $policy = Read-RestartPolicy -Path $PolicyPath
    $preflightCheck = 'credential_source'
    if ($null -ne $Credential -and -not [string]::IsNullOrWhiteSpace($CredentialPath)) {
        throw [System.InvalidOperationException]::new('Credential sources are mutually exclusive.')
    }
    if ($null -eq $Credential -and -not [string]::IsNullOrWhiteSpace($CredentialPath)) {
        $Credential = Import-MachineCredential -Path $CredentialPath
    }
    if ($runtimeHealthConfigured -and ($RuntimeHealthProbeScriptID -le 0 -or
        [string]::IsNullOrWhiteSpace($ExpectedRuntimeHealthProbeSha256) -or
        [string]::IsNullOrWhiteSpace($RequiredRuntimeFunctionsBase64))) {
        throw [System.InvalidOperationException]::new('Runtime health probe parameters must be complete.')
    }
    $preflightCheck = 'service_name'
    $effectiveServiceName = if ([string]::IsNullOrWhiteSpace($ServiceName)) {
        [string] $policy.serviceName
    } else {
        $ServiceName
    }

    $preflightCheck = 'rollback_parameters'
    $rollbackConfigured = (
        -not [string]::IsNullOrWhiteSpace($RollbackBootstrapPath) -or
        -not [string]::IsNullOrWhiteSpace($ExpectedRollbackBootstrapSha256)
    )
    if ($rollbackConfigured -and (
        [string]::IsNullOrWhiteSpace($ActiveBootstrapPath) -or
        [string]::IsNullOrWhiteSpace($RollbackBootstrapPath) -or
        [string]::IsNullOrWhiteSpace($ExpectedRollbackBootstrapSha256)
    )) {
        throw [System.InvalidOperationException]::new('Rollback parameters must be complete.')
    }
    $preflightCheck = 'active_bootstrap_hash'
    if (-not [string]::IsNullOrWhiteSpace($ExpectedActiveBootstrapSha256)) {
        if ([string]::IsNullOrWhiteSpace($ActiveBootstrapPath) -or
            -not (Test-Sha256 -Path $ActiveBootstrapPath -Expected $ExpectedActiveBootstrapSha256)) {
            throw [System.InvalidOperationException]::new('Active bootstrap preflight failed.')
        }
    }

    $preflightCheck = 'service_state'
    $service = Get-SymconService -Name $effectiveServiceName
    if ($service.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Running) {
        throw [System.InvalidOperationException]::new('Symcon service is not running before restart.')
    }
    $preflightCheck = 'kernel_runlevel'
    $runlevel = [int] (Invoke-SymconRpc -Method 'IPS_GetKernelRunlevel' -TimeoutSeconds ([int] $policy.rpcTimeoutSeconds))
    if ($runlevel -ne [int] $policy.expectedReadyRunlevel) {
        throw [System.InvalidOperationException]::new('Symcon is not ready before restart.')
    }
    $preflightCheck = 'kernel_start_time'
    $baselineStartTime = [long] (Invoke-SymconRpc -Method 'IPS_GetKernelStartTime' -TimeoutSeconds ([int] $policy.rpcTimeoutSeconds))
    if ($runtimeHealthConfigured) {
        $preflightCheck = 'runtime_health_probe'
        Invoke-RuntimeHealthProbe -TimeoutSeconds ([int] $policy.rpcTimeoutSeconds)
    }
} catch {
    $statusParameters = @{
        Phase = 'preflight'
        Outcome = 'failed'
        ExitCode = $ExitPreflightFailed
        Details = @{
            failedCheck = $preflightCheck
            errorType = $_.Exception.GetType().FullName
            runtimeHealthCheck = $script:runtimeHealthCheck
        }
    }
    Write-RestartStatus @statusParameters
    exit $ExitPreflightFailed
}

if ($PreflightOnly) {
    $preflightPassedStatus = @{
        Phase = 'preflight'
        Outcome = 'passed'
        ExitCode = $ExitActivated
        Details = @{
            previousKernelStartTime = $baselineStartTime
            runlevel = [int] $policy.expectedReadyRunlevel
            serviceState = 'Running'
            restartAttempted = $false
            rollbackAttempted = $false
        }
    }
    Write-RestartStatus @preflightPassedStatus
    exit $ExitActivated
}

try {
    $runningStatus = @{
        Phase = 'activation_restart'
        Outcome = 'running'
        ExitCode = $ExitActivationFailed
        Details = @{ previousKernelStartTime = $baselineStartTime }
    }
    Write-RestartStatus @runningStatus
    $restartParameters = @{
        Name = $effectiveServiceName
        PreviousKernelStartTime = $baselineStartTime
        ReadyTimeoutSeconds = [int] $policy.readyTimeoutSeconds
        Policy = $policy
    }
    $ready = Restart-SymconAndWait @restartParameters
    if ($runtimeHealthConfigured) {
        Invoke-RuntimeHealthProbe -TimeoutSeconds ([int] $policy.rpcTimeoutSeconds)
    }
    $activatedStatus = @{
        Phase = 'activation_restart'
        Outcome = 'activated'
        ExitCode = $ExitActivated
        Details = @{
            previousKernelStartTime = $baselineStartTime
            kernelStartTime = $ready.kernelStartTime
            runlevel = $ready.runlevel
            rollbackAttempted = $false
        }
    }
    Write-RestartStatus @activatedStatus
    exit $ExitActivated
} catch {
    $activationErrorType = $_.Exception.GetType().FullName
    if (-not $rollbackConfigured) {
        $failedStatus = @{
            Phase = 'activation_restart'
            Outcome = 'failed'
            ExitCode = $ExitActivationFailed
            Details = @{
                previousKernelStartTime = $baselineStartTime
                errorType = $activationErrorType
                rollbackAttempted = $false
            }
        }
        Write-RestartStatus @failedStatus
        exit $ExitActivationFailed
    }
}

try {
    $rollbackRunningStatus = @{
        Phase = 'rollback'
        Outcome = 'running'
        ExitCode = $ExitRollbackFailed
        Details = @{
            previousKernelStartTime = $baselineStartTime
            rollbackAttempted = $true
        }
    }
    Write-RestartStatus @rollbackRunningStatus
    $restoreParameters = @{
        SourcePath = $RollbackBootstrapPath
        TargetPath = $ActiveBootstrapPath
        ExpectedSourceSha256 = $ExpectedRollbackBootstrapSha256
    }
    Restore-Bootstrap @restoreParameters
    $rollbackRestartParameters = @{
        Name = $effectiveServiceName
        PreviousKernelStartTime = $baselineStartTime
        ReadyTimeoutSeconds = [int] $policy.rollbackReadyTimeoutSeconds
        Policy = $policy
    }
    $ready = Restart-SymconAndWait @rollbackRestartParameters
    if ($runtimeHealthConfigured) {
        Invoke-RuntimeHealthProbe -TimeoutSeconds ([int] $policy.rpcTimeoutSeconds)
    }
    $rolledBackStatus = @{
        Phase = 'rollback'
        Outcome = 'rolled_back'
        ExitCode = $ExitRolledBack
        Details = @{
            previousKernelStartTime = $baselineStartTime
            kernelStartTime = $ready.kernelStartTime
            runlevel = $ready.runlevel
            rollbackAttempted = $true
            rollbackSucceeded = $true
        }
    }
    Write-RestartStatus @rolledBackStatus
    exit $ExitRolledBack
} catch {
    $rollbackFailedStatus = @{
        Phase = 'rollback'
        Outcome = 'failed'
        ExitCode = $ExitRollbackFailed
        Details = @{
            previousKernelStartTime = $baselineStartTime
            errorType = $_.Exception.GetType().FullName
            rollbackAttempted = $true
            rollbackSucceeded = $false
        }
    }
    Write-RestartStatus @rollbackFailedStatus
    exit $ExitRollbackFailed
}
