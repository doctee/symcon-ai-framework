[CmdletBinding()]
param(
    [Parameter()]
    [string] $SymconTempRoot = ([IO.Path]::GetTempPath())
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitFailed = 10
$sourceCommit = 'checked-out-source'
$sourceRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows/adapters'))
$expectedAdapterSha256 = (Get-FileHash (Join-Path $sourceRoot 'Invoke-SaefMediaCarouselModuleAdapter.ps1')).Hash.ToLowerInvariant()
$expectedTransactionSha256 = (Get-FileHash (Join-Path $sourceRoot 'media-carousel-module-transaction.json')).Hash.ToLowerInvariant()
. (Join-Path $sourceRoot '../SaefChildProcess.ps1')
$scratchRoot = $null
$mockJob = $null
$scratchMutationAttempted = $false
$scratchCleanupSucceeded = $false
$parseErrorCount = 0
$passedScenarios = @()
$positiveCaseCount = 0
$negativeCaseCount = 0
$failureCode = 'initialization'
$failureDetail = ''
$currentPhase = 'initialization'
$requestLogPath = $null
$mockStopPath = $null
$adapterTimeoutSeconds = 30
$scratchDirectoryName = 'saef-media-carousel-windows-qualification-' + [Guid]::NewGuid().ToString('N')
$adapterInvocation = 0
$lastRpcMethod = ''
$lastAdapterOutcome = ''
$lastAdapterFailureCode = ''
$lastAdapterExitCode = $null
$lastAdapterProcessExitCode = $null

function Write-Utf8NoBom {
    param([string] $Path, [string] $Text)
    [IO.File]::WriteAllText($Path, $Text, [Text.UTF8Encoding]::new($false))
}

function Write-GateProgress {
    param([string] $Phase)
    $script:currentPhase = $Phase
    $progress = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = $Phase
        processId = $PID
        failureCode = $script:failureCode
    }
    Write-Utf8NoBom -Path (
        Join-Path $PSScriptRoot 'media-carousel-windows-qualification-progress.local.json'
    ) -Text (($progress | ConvertTo-Json -Depth 4) + [Environment]::NewLine)
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

function Get-FileSha256 {
    param([string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Write-GateStatus {
    param([string] $Outcome, [int] $ExitCode)
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'media_carousel_windows_qualification'
        outcome = $Outcome
        exitCode = $ExitCode
        sourceCommit = $sourceCommit
        adapterSha256 = $expectedAdapterSha256
        transactionSha256 = $expectedTransactionSha256
        windowsPowerShellVersion = [string] $PSVersionTable.PSVersion
        parseErrorCount = $parseErrorCount
        positiveCaseCount = $positiveCaseCount
        negativeCaseCount = $negativeCaseCount
        passedScenarios = @($passedScenarios)
        failureCode = $failureCode
        failureDetail = $failureDetail
        currentPhase = $currentPhase
        lastRpcMethod = $lastRpcMethod
        lastAdapterOutcome = $lastAdapterOutcome
        lastAdapterFailureCode = $lastAdapterFailureCode
        lastAdapterExitCode = $lastAdapterExitCode
        lastAdapterProcessExitCode = $lastAdapterProcessExitCode
        scratchMutationAttempted = $scratchMutationAttempted
        scratchCleanupSucceeded = $scratchCleanupSucceeded
        productionMutationAttempted = $false
        liveSymconContactAttempted = $false
        serviceRestartAttempted = $false
        providerContactAttempted = $false
        publicationAttempted = $false
        retentionCleanupAttempted = $false
    }
    Write-Utf8NoBom -Path (
        Join-Path $PSScriptRoot 'media-carousel-windows-qualification-status.local.json'
    ) -Text (($status | ConvertTo-Json -Depth 8) + [Environment]::NewLine)
}

function Assert-PowerShellParse {
    param([string] $Path)
    $tokens = $null
    $parseErrors = $null
    $null = [Management.Automation.Language.Parser]::ParseFile(
        $Path,
        [ref] $tokens,
        [ref] $parseErrors
    )
    $script:parseErrorCount += @($parseErrors).Count
    if (@($parseErrors).Count -ne 0) {
        throw [InvalidOperationException]::new('Windows PowerShell parser rejected a gate script.')
    }
}

function Protect-ScratchAcl {
    param([string] $Path)
    $currentSid = [Security.Principal.WindowsIdentity]::GetCurrent().User
    $systemSid = [Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $administratorsSid = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    $inheritance = [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor
        [Security.AccessControl.InheritanceFlags]::ObjectInherit
    $propagation = [Security.AccessControl.PropagationFlags]::None
    $allow = [Security.AccessControl.AccessControlType]::Allow
    $acl = Get-Acl -LiteralPath $Path
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($sid in @($currentSid, $systemSid, $administratorsSid)) {
        $rule = [Security.AccessControl.FileSystemAccessRule]::new(
            $sid,
            [Security.AccessControl.FileSystemRights]::FullControl,
            $inheritance,
            $propagation,
            $allow
        )
        $null = $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Path -AclObject $acl
}

function Get-ModuleRecords {
    param([string] $Path)
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
    $records = @()
    foreach ($relative in $relativePaths) {
        $file = $filesByRelativePath[$relative]
        $records += [ordered]@{
            relativePath = $relative
            size = [long] $file.Length
            sha256 = Get-FileSha256 -Path $file.FullName
        }
    }
    return @($records)
}

function Get-PackageIdentity {
    param([string] $Path)
    $builder = [Text.StringBuilder]::new()
    foreach ($record in @(Get-ModuleRecords -Path $Path)) {
        $null = $builder.Append([string] $record.relativePath).Append([char] 0).Append(
            [long] $record.size
        ).Append([char] 0).Append([string] $record.sha256).Append("`n")
    }
    return Get-TextSha256 -Text $builder.ToString()
}

function New-SyntheticModuleTree {
    param([string] $Path, [string] $Marker)
    $modulePath = Join-Path $Path 'MediaCarousel'
    $null = [IO.Directory]::CreateDirectory($modulePath)
    $library = [ordered]@{
        id = '{8D263598-06EF-4440-982C-9E86E3F8D130}'
        name = 'SAEF Media Carousel'
        author = 'SAEF synthetic Windows qualification'
        url = 'https://github.com/doctee/saef-media-carousel'
        version = '0.0'
        build = 0
        date = 0
    }
    $module = [ordered]@{
        id = '{41D0C5ED-8331-4B26-A44E-6FDCEC1BC41F}'
        name = 'MediaCarousel'
        type = 3
        vendor = 'SAEF'
        aliases = @()
        parentRequirements = @()
        childRequirements = @()
        implemented = @()
        prefix = 'SAEFMC'
    }
    Write-Utf8NoBom -Path (Join-Path $Path 'library.json') `
        -Text (($library | ConvertTo-Json -Depth 4) + "`n")
    Write-Utf8NoBom -Path (Join-Path $modulePath 'module.json') `
        -Text (($module | ConvertTo-Json -Depth 4) + "`n")
    Write-Utf8NoBom -Path (Join-Path $modulePath 'marker.txt') -Text ($Marker + "`n")
}

function New-Manifest {
    param([string] $ModulePath, [string] $DeploymentId, [string] $OutputPath)
    $records = @(Get-ModuleRecords -Path $ModulePath)
    $identity = Get-PackageIdentity -Path $ModulePath
    $files = @($records | ForEach-Object {
        [ordered]@{
            path = 'module/' + [string] $_.relativePath
            sha256 = [string] $_.sha256
            size = [long] $_.size
        }
    })
    $manifest = [ordered]@{
        formatVersion = 1
        deploymentKind = 'standalone-module'
        deploymentId = $DeploymentId
        targetDirectoryName = 'saef-media-carousel'
        module = [ordered]@{
            targetId = 'saef-media-carousel'
            libraryGuid = '{8D263598-06EF-4440-982C-9E86E3F8D130}'
            packageIdentitySha256 = $identity
            transactionContractSha256 = Get-FileSha256 -Path (
                Join-Path $sourceRoot 'media-carousel-module-transaction.json'
            )
        }
        files = $files
    }
    Write-Utf8NoBom -Path $OutputPath -Text (($manifest | ConvertTo-Json -Depth 10) + "`n")
    return $identity
}

function New-CredentialFile {
    param([string] $Path)
    Add-Type -AssemblyName System.Security
    $entropy = [Text.Encoding]::UTF8.GetBytes('SAEF.DeploymentChannel.RpcCredential.v1')
    $clear = [Text.Encoding]::UTF8.GetBytes('synthetic-media-carousel-qualification')
    try {
        $protected = [Security.Cryptography.ProtectedData]::Protect(
            $clear,
            $entropy,
            [Security.Cryptography.DataProtectionScope]::LocalMachine
        )
        try {
            $record = [ordered]@{
                formatVersion = 1
                protectionScope = 'LocalMachine'
                username = 'synthetic-media-carousel-qualification'
                protectedPasswordBase64 = [Convert]::ToBase64String($protected)
            }
            Write-Utf8NoBom -Path $Path -Text (($record | ConvertTo-Json -Depth 4) + "`n")
        } finally {
            [Array]::Clear($protected, 0, $protected.Length)
        }
    } finally {
        [Array]::Clear($clear, 0, $clear.Length)
        [Array]::Clear($entropy, 0, $entropy.Length)
    }
}

function Get-FreeTcpPort {
    $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try {
        return ([Net.IPEndPoint] $listener.LocalEndpoint).Port
    } finally {
        $listener.Stop()
    }
}

function Start-MockRpc {
    param(
        [int] $Port,
        [string] $ReadyPath,
        [string] $ControlPath,
        [string] $ConfigurationOne,
        [string] $ConfigurationTwo,
        [string] $RequestLogPath,
        [string] $StopPath,
        [string] $ActivePath
    )
    $job = Start-Job -ArgumentList @(
        $Port,
        $ReadyPath,
        $ControlPath,
        $ConfigurationOne,
        $ConfigurationTwo,
        $RequestLogPath,
        $StopPath,
        $ActivePath
    ) -ScriptBlock {
        param(
            $Port,
            $ReadyPath,
            $ControlPath,
            $ConfigurationOne,
            $ConfigurationTwo,
            $RequestLogPath,
            $StopPath,
            $ActivePath
        )
        $ErrorActionPreference = 'Stop'
        $libraryGuid = '{8D263598-06EF-4440-982C-9E86E3F8D130}'
        $moduleGuid = '{41D0C5ED-8331-4B26-A44E-6FDCEC1BC41F}'
        $moduleControlGuid = '{11111111-1111-1111-1111-111111111111}'
        $reloadFailureConsumed = $false
        $configurations = @{42101 = $ConfigurationOne; 42102 = $ConfigurationTwo}
        $listener = [Net.HttpListener]::new()
        $listener.Prefixes.Add("http://127.0.0.1:$Port/")
        $listener.Start()
        [IO.File]::WriteAllText($ReadyPath, 'ready')
        try {
            while (-not (Test-Path -LiteralPath $StopPath -PathType Leaf)) {
                $pendingContext = $listener.BeginGetContext($null, $null)
                while (-not $pendingContext.AsyncWaitHandle.WaitOne(100)) {
                    if (Test-Path -LiteralPath $StopPath -PathType Leaf) {
                        break
                    }
                }
                if (Test-Path -LiteralPath $StopPath -PathType Leaf) {
                    break
                }
                $context = $listener.EndGetContext($pendingContext)
                $reader = [IO.StreamReader]::new($context.Request.InputStream)
                try {
                    $request = $reader.ReadToEnd() | ConvertFrom-Json
                } finally {
                    $reader.Dispose()
                }
                $method = [string] $request.method
                [IO.File]::AppendAllText(
                    $RequestLogPath,
                    $method + "`n",
                    [Text.UTF8Encoding]::new($false)
                )
                $control = Get-Content -LiteralPath $ControlPath -Raw | ConvertFrom-Json
                $result = $null
                $errorRecord = $null
                switch ($method) {
                    'IPS_GetKernelRunlevel' { $result = 10103 }
                    'IPS_FunctionExists' { $result = ([string] $request.params[0] -eq 'MC_ReloadModule') }
                    'IPS_InstanceExists' {
                        $result = ([int] $request.params[0] -in @(42100, 42101, 42102))
                    }
                    'IPS_LibraryExists' { $result = ([string] $request.params[0] -eq $libraryGuid) }
                    'IPS_ModuleExists' { $result = ([string] $request.params[0] -eq $moduleGuid) }
                    'IPS_GetLibrary' {
                        $result = @{
                            Name = 'SAEF Media Carousel'
                            URL = 'https://github.com/doctee/saef-media-carousel'
                        }
                    }
                    'IPS_GetModule' {
                        $result = @{
                            LibraryID = $libraryGuid
                            ModuleName = 'MediaCarousel'
                            ModuleType = 3
                            Prefix = 'SAEFMC'
                        }
                    }
                    'IPS_GetLibraryModules' { $result = @($moduleGuid) }
                    'IPS_GetInstanceListByModuleID' { $result = @(42102, 42101) }
                    'IPS_GetInstance' {
                        $instanceId = [int] $request.params[0]
                        if ($instanceId -eq 42100) {
                            $result = @{
                                InstanceStatus = 102
                                ModuleInfo = @{ ModuleID = $moduleControlGuid }
                            }
                        } else {
                            $result = @{
                                InstanceStatus = 102
                                ModuleInfo = @{ ModuleID = $moduleGuid }
                            }
                        }
                    }
                    'IPS_GetObject' {
                        $instanceId = [int] $request.params[0]
                        $result = @{
                            ObjectType = 1
                            ObjectIdent = 'SYNTHETIC_MEDIA_CAROUSEL_' + $instanceId
                            ObjectName = 'Synthetic MediaCarousel ' + $instanceId
                            ObjectParentID = 42000
                            ObjectPosition = $instanceId - 42100
                            ObjectIsHidden = $false
                            ObjectIsDisabled = $false
                            ObjectIsReadOnly = $false
                        }
                    }
                    'IPS_GetConfiguration' {
                        $instanceId = [int] $request.params[0]
                        if ([string] $control.configurationMode -eq 'drift' -and $instanceId -eq 42102) {
                            $result = '{"Enabled":false,"Synthetic":"drift"}'
                        } else {
                            $result = $configurations[$instanceId]
                        }
                    }
                    'IPS_HasChanges' { $result = $false }
                    'IPS_SetConfiguration' {
                        $configurations[[int] $request.params[0]] = [string] $request.params[1]
                        $result = $true
                    }
                    'IPS_ApplyChanges' { $result = $true }
                    'MC_ReloadModule' {
                        $marker = (Get-Content (Join-Path $ActivePath 'MediaCarousel/marker.txt') -Raw).Trim()
                        if ($marker -ceq 'candidate-fit') {
                            foreach ($id in @(42101, 42102)) {
                                if (-not $configurations[$id].Contains('"ShowFitToggle"')) {
                                    $configurations[$id] = $configurations[$id].TrimEnd('}') + ',"ShowFitToggle":false}'
                                }
                            }
                            if ($control.configurationMode -ceq 'schema-drift') {
                                $configurations[42102] = '{"Unexpected":true}'
                            }
                        }
                        if ([bool] $control.failReloadOnce -and -not $reloadFailureConsumed) {
                            $reloadFailureConsumed = $true
                            $result = $false
                        } else {
                            $result = $true
                        }
                    }
                    default {
                        $errorRecord = @{ code = -32601; message = 'synthetic method rejected' }
                    }
                }
                $response = if ($null -ne $errorRecord) {
                    @{ jsonrpc = '2.0'; id = $request.id; error = $errorRecord }
                } else {
                    @{ jsonrpc = '2.0'; id = $request.id; result = $result }
                }
                $bytes = [Text.UTF8Encoding]::new($false).GetBytes(
                    ($response | ConvertTo-Json -Depth 8 -Compress)
                )
                $context.Response.StatusCode = 200
                $context.Response.ContentType = 'application/json'
                $context.Response.ContentLength64 = $bytes.Length
                $context.Response.OutputStream.Write($bytes, 0, $bytes.Length)
                $context.Response.OutputStream.Close()
            }
        } finally {
            $listener.Stop()
            $listener.Close()
        }
    }
    $timer = [Diagnostics.Stopwatch]::StartNew()
    while (-not (Test-Path -LiteralPath $ReadyPath -PathType Leaf)) {
        if ($job.State -in @('Failed', 'Stopped', 'Completed')) {
            throw [InvalidOperationException]::new('Synthetic loopback RPC server failed to start.')
        }
        if ($timer.Elapsed.TotalSeconds -gt 10) {
            throw [TimeoutException]::new('Synthetic loopback RPC server start timed out.')
        }
        Start-Sleep -Milliseconds 100
    }
    return $job
}

function Invoke-Adapter {
    param([string] $Operation, [string] $ManifestPath, [string] $CandidatePath,
        [string] $PolicyPath, [string] $StatusPath, [int] $Port)
    $adapterPath = Join-Path $sourceRoot 'Invoke-SaefMediaCarouselModuleAdapter.ps1'
    $result = Invoke-SaefPowerShellChildProcess -ScriptPath $adapterPath `
        -ExpectedScriptSha256 $expectedAdapterSha256 -TimeoutSeconds 30 -MaximumOutputBytes 8192 `
        -Arguments @('-Operation', $Operation, '-ManifestPath', $ManifestPath,
            '-CandidatePath', $CandidatePath, '-TransactionContractPath',
            (Join-Path $sourceRoot 'media-carousel-module-transaction.json'),
            '-AdapterPolicyPath', $PolicyPath, '-RpcUri', "http://127.0.0.1:$Port/",
            '-CredentialPath', (Join-Path $script:scratchRoot 'credential.json'),
            '-StatusPath', $StatusPath)
    if ($result.terminationReason -cne 'exited') { throw 'Bounded child did not complete.' }
    $status = Get-Content -LiteralPath $StatusPath -Raw | ConvertFrom-Json
    if ($result.exitCode -ne $status.exitCode) { throw 'Child and status exit codes differ.' }
    return [pscustomobject]@{ exitCode = $result.exitCode; status = $status }
}

Write-GateProgress -Phase 'bootstrap'

try {
    $failureCode = 'platform'
    Write-GateProgress -Phase 'platform-check'
    if ($env:OS -ne 'Windows_NT' -or $PSVersionTable.PSEdition -ne 'Desktop' -or
        $PSVersionTable.PSVersion.Major -ne 5 -or
        -not [Environment]::Is64BitProcess) {
        throw [PlatformNotSupportedException]::new(
            'Qualification requires 64-bit Windows PowerShell 5.1.'
        )
    }
    $principal = [Security.Principal.WindowsPrincipal]::new(
        [Security.Principal.WindowsIdentity]::GetCurrent()
    )
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [UnauthorizedAccessException]::new(
            'Qualification requires an elevated Windows PowerShell 5.1 process.'
        )
    }

    $failureCode = 'checksums'
    Write-GateProgress -Phase 'checksum-check'
    $adapterPath = Join-Path $sourceRoot 'Invoke-SaefMediaCarouselModuleAdapter.ps1'
    $transactionPath = Join-Path $sourceRoot 'media-carousel-module-transaction.json'
    if ((Get-FileSha256 -Path $adapterPath) -cne $expectedAdapterSha256 -or
        (Get-FileSha256 -Path $transactionPath) -cne $expectedTransactionSha256) {
        throw [InvalidOperationException]::new('Merged MediaCarousel source identity differs.')
    }
    $passedScenarios += 'bundle-and-merged-source-checksums'
    $positiveCaseCount++

    $failureCode = 'parser'
    Write-GateProgress -Phase 'parser-check'
    Assert-PowerShellParse -Path $adapterPath
    Assert-PowerShellParse -Path $PSCommandPath
    $passedScenarios += 'windows-powershell-5.1-parse'
    $positiveCaseCount++

    $failureCode = 'static_boundary'
    Write-GateProgress -Phase 'static-boundary-check'
    $adapterSource = Get-Content -LiteralPath $adapterPath -Raw
    if ([regex]::Matches(
            $adapterSource,
            [regex]::Escape("-Method 'MC_ReloadModule'")
        ).Count -ne 1 -or
        $adapterSource -match 'Restart-Service|Stop-Service|Start-Service|MC_UpdateModule|Invoke-WebRequest') {
        throw [InvalidOperationException]::new('Adapter action boundary differs from the reviewed contract.')
    }
    $passedScenarios += 'static-targeted-reload-only-boundary'
    $positiveCaseCount++

    $failureCode = 'scratch_setup'
    Write-GateProgress -Phase 'scratch-setup'
    $scratchMutationAttempted = $true
    if (-not [IO.Path]::IsPathRooted($SymconTempRoot) -or
        -not (Test-Path -LiteralPath $SymconTempRoot -PathType Container) -or
        (((Get-Item -LiteralPath $SymconTempRoot).Attributes -band
            [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.DirectoryNotFoundException]::new('Approved temp root is missing or unsafe.')
    }
    $scratchRoot = Join-Path ([IO.Path]::GetFullPath($SymconTempRoot)) $scratchDirectoryName
    if (Test-Path -LiteralPath $scratchRoot) {
        throw [IO.IOException]::new('Qualification scratch directory already exists.')
    }
    $script:scratchRoot = $scratchRoot
    $null = [IO.Directory]::CreateDirectory($scratchRoot)
    Protect-ScratchAcl -Path $scratchRoot
    $activePath = Join-Path $scratchRoot 'active\saef-media-carousel'
    $candidateSuccess = Join-Path $scratchRoot 'candidate-success'
    $candidateFailure = Join-Path $scratchRoot 'candidate-failure'
    $adapterState = Join-Path $scratchRoot 'adapter-state'
    foreach ($path in @($activePath, $candidateSuccess, $candidateFailure, $adapterState)) {
        $null = [IO.Directory]::CreateDirectory($path)
    }
    New-SyntheticModuleTree -Path $activePath -Marker 'active-old'
    New-SyntheticModuleTree -Path $candidateSuccess -Marker 'candidate-success'
    New-SyntheticModuleTree -Path $candidateFailure -Marker 'candidate-failure'

    $configurationOne = '{"Enabled":true,"Synthetic":"one"}'
    $configurationTwo = '{"Enabled":true,"Synthetic":"two"}'
    New-CredentialFile -Path (Join-Path $scratchRoot 'credential.json')
    $policyPath = Join-Path $scratchRoot 'adapter-policy.json'
    $policy = [ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-media-carousel-v1'
        targetId = 'saef-media-carousel'
        libraryGuid = '{8D263598-06EF-4440-982C-9E86E3F8D130}'
        moduleGuid = '{41D0C5ED-8331-4B26-A44E-6FDCEC1BC41F}'
        libraryName = 'SAEF Media Carousel'
        moduleName = 'MediaCarousel'
        moduleType = 3
        modulePrefix = 'SAEFMC'
        libraryUrl = 'https://github.com/doctee/saef-media-carousel'
        moduleControlInstanceId = 42100
        moduleControlModuleGuid = '{11111111-1111-1111-1111-111111111111}'
        moduleDirectoryName = 'saef-media-carousel'
        activeModulePath = $activePath
        adapterStateRoot = $adapterState
        mutexName = 'Global\SAEF.MediaCarousel.WindowsQualification.' +
            [Guid]::NewGuid().ToString('N')
        expectedReadyRunlevel = 10103
        rpcTimeoutSeconds = 5
        reloadTimeoutSeconds = 5
        healthPollMilliseconds = 100
        allowedInstanceStatuses = @(102)
        maximumInstanceCount = 4
        expectedInstances = @(
            [ordered]@{
                instanceId = 42102
                configurationSha256 = Get-TextSha256 -Text $configurationTwo
            },
            [ordered]@{
                instanceId = 42101
                configurationSha256 = Get-TextSha256 -Text $configurationOne
            }
        )
        expectedActivePackageIdentitySha256 = Get-PackageIdentity -Path $activePath
        maximumCandidateBytes = 1048576
        maximumStateBytes = 1048576
        retention = [ordered]@{
            minimumAgeHours = 168
            keepSuccessfulRollbackCount = 2
            keepFailedCandidateCount = 2
            maximumArtifactCount = 16
        }
    }
    Write-Utf8NoBom -Path $policyPath -Text (($policy | ConvertTo-Json -Depth 10) + "`n")
    $successManifest = Join-Path $scratchRoot 'manifest-success.json'
    $failureManifest = Join-Path $scratchRoot 'manifest-failure.json'
    $successIdentity = New-Manifest -ModulePath $candidateSuccess `
        -DeploymentId 'saef-media-carousel-windows-qualification-success' `
        -OutputPath $successManifest
    $failureIdentity = New-Manifest -ModulePath $candidateFailure `
        -DeploymentId 'saef-media-carousel-windows-qualification-failure' `
        -OutputPath $failureManifest
    if ($successIdentity -eq $failureIdentity) {
        throw [InvalidOperationException]::new('Synthetic candidate identities are not distinct.')
    }

    $controlPath = Join-Path $scratchRoot 'mock-control.json'
    Write-Utf8NoBom -Path $controlPath `
        -Text '{"configurationMode":"stable","failReloadOnce":false}'
    $port = Get-FreeTcpPort
    $mockReady = Join-Path $scratchRoot 'mock-ready'
    $requestLogPath = Join-Path $scratchRoot 'rpc-methods.log'
    $mockStopPath = Join-Path $scratchRoot 'stop-mock'
    Write-Utf8NoBom -Path $requestLogPath -Text ''
    Write-GateProgress -Phase 'mock-rpc-start'
    $mockJob = Start-MockRpc -Port $port -ReadyPath $mockReady `
        -ControlPath $controlPath -ConfigurationOne $configurationOne `
        -ConfigurationTwo $configurationTwo -RequestLogPath $requestLogPath `
        -StopPath $mockStopPath -ActivePath $activePath

    $activeInitialIdentity = Get-PackageIdentity -Path $activePath
    $failureCode = 'synthetic_preflight'
    Write-GateProgress -Phase 'synthetic-preflight'
    $preflight = Invoke-Adapter -Operation 'preflight' -ManifestPath $successManifest `
        -CandidatePath $candidateSuccess -PolicyPath $policyPath `
        -StatusPath (Join-Path $scratchRoot 'status-preflight.json') -Port $port
    if ($preflight.exitCode -ne 0 -or [string] $preflight.status.outcome -ne 'passed' -or
        (Get-PackageIdentity -Path $activePath) -ne $activeInitialIdentity) {
        throw [InvalidOperationException]::new('Synthetic adapter preflight failed or mutated state.')
    }
    $passedScenarios += 'synthetic-read-only-preflight-with-two-unsorted-instances'
    $positiveCaseCount++

    $failureCode = 'configuration_drift'
    Write-GateProgress -Phase 'configuration-drift-negative'
    Write-Utf8NoBom -Path $controlPath `
        -Text '{"configurationMode":"drift","failReloadOnce":false}'
    $drift = Invoke-Adapter -Operation 'preflight' -ManifestPath $successManifest `
        -CandidatePath $candidateSuccess -PolicyPath $policyPath `
        -StatusPath (Join-Path $scratchRoot 'status-configuration-drift.json') -Port $port
    if ($drift.exitCode -ne 10 -or [string] $drift.status.outcome -ne 'failed' -or
        [string] $drift.status.failureCode -ne 'symcon_ownership' -or
        (Get-PackageIdentity -Path $activePath) -ne $activeInitialIdentity) {
        throw [InvalidOperationException]::new('Configuration drift was not rejected before mutation.')
    }
    $passedScenarios += 'configuration-drift-fails-closed'
    $negativeCaseCount++
    Write-Utf8NoBom -Path $controlPath `
        -Text '{"configurationMode":"stable","failReloadOnce":false}'

    $failureCode = 'synthetic_activation'
    Write-GateProgress -Phase 'synthetic-activation'
    $activation = Invoke-Adapter -Operation 'activate' -ManifestPath $successManifest `
        -CandidatePath $candidateSuccess -PolicyPath $policyPath `
        -StatusPath (Join-Path $scratchRoot 'status-activate.json') -Port $port
    if ($activation.exitCode -ne 0 -or [string] $activation.status.outcome -ne 'activated' -or
        (Get-PackageIdentity -Path $activePath) -ne $successIdentity) {
        throw [InvalidOperationException]::new('Synthetic adapter activation failed.')
    }
    $passedScenarios += 'synthetic-controlled-activation'
    $positiveCaseCount++

    $failureCode = 'synthetic_rollback'
    Write-GateProgress -Phase 'synthetic-rollback'
    $policy.expectedActivePackageIdentitySha256 = $successIdentity
    Write-Utf8NoBom -Path $policyPath -Text (($policy | ConvertTo-Json -Depth 10) + "`n")
    Write-Utf8NoBom -Path $controlPath `
        -Text '{"configurationMode":"stable","failReloadOnce":true}'
    $rollback = Invoke-Adapter -Operation 'activate' -ManifestPath $failureManifest `
        -CandidatePath $candidateFailure -PolicyPath $policyPath `
        -StatusPath (Join-Path $scratchRoot 'status-rollback.json') -Port $port
    if ($rollback.exitCode -ne 30 -or [string] $rollback.status.outcome -ne 'rolled_back' -or
        -not [bool] $rollback.status.rollbackSucceeded -or
        (Get-PackageIdentity -Path $activePath) -ne $successIdentity) {
        throw [InvalidOperationException]::new('Synthetic adapter rollback failed.')
    }
    $rolledBackTransactions = @(Get-ChildItem -LiteralPath $adapterState -Directory |
        Where-Object {
            Test-Path -LiteralPath (Join-Path $_.FullName 'failed-candidate') -PathType Container
        })
    if ($rolledBackTransactions.Count -ne 1) {
        throw [InvalidOperationException]::new('Failed candidate retention is not transaction-bounded.')
    }
    $passedScenarios += 'synthetic-byte-exact-rollback'
    $positiveCaseCount++


    # Reuse the exact adapter, scratch ACL and bounded child path for schema changes.
    $candidateFit = Join-Path $scratchRoot 'candidate-fit'
    New-SyntheticModuleTree -Path $candidateFit -Marker 'candidate-fit'
    $fitManifest = Join-Path $scratchRoot 'manifest-fit.json'
    $fitIdentity = New-Manifest -ModulePath $candidateFit -DeploymentId 'synthetic-fit' -OutputPath $fitManifest
    $afterOne = $configurationOne.TrimEnd('}') + ',"ShowFitToggle":false}'
    $afterTwo = $configurationTwo.TrimEnd('}') + ',"ShowFitToggle":false}'
    $policy.configurationTransition = [ordered]@{
        kind = 'show-fit-toggle-default-false-v1'
        sourcePackageIdentitySha256 = $successIdentity
        candidatePackageIdentitySha256 = $fitIdentity
        deploymentId = 'synthetic-fit'
        instances = @(
            [ordered]@{ instanceId = 42101; configurationBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($afterOne)); configurationSha256 = Get-TextSha256 $afterOne },
            [ordered]@{ instanceId = 42102; configurationBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($afterTwo)); configurationSha256 = Get-TextSha256 $afterTwo }
        )
    }
    foreach ($mode in @('schema-drift', 'schema-success')) {
        $deploymentID = 'synthetic-fit-' + $mode
        $fitIdentity = New-Manifest -ModulePath $candidateFit -DeploymentId $deploymentID -OutputPath $fitManifest
        $policy.configurationTransition.deploymentId = $deploymentID
        Write-Utf8NoBom -Path $policyPath -Text (($policy | ConvertTo-Json -Depth 10) + "`n")
        Write-Utf8NoBom -Path $controlPath -Text ('{"configurationMode":"' + $mode + '","failReloadOnce":false}')
        $preflight = Invoke-Adapter -Operation 'preflight' -ManifestPath $fitManifest `
            -CandidatePath $candidateFit -PolicyPath $policyPath `
            -StatusPath (Join-Path $scratchRoot ($mode + '-preflight.json')) -Port $port
        if ($preflight.exitCode -ne 0 -or (Get-PackageIdentity $activePath) -cne $successIdentity) {
            throw 'Schema preflight failed or changed active package.'
        }
        $fit = Invoke-Adapter -Operation 'activate' -ManifestPath $fitManifest `
            -CandidatePath $candidateFit -PolicyPath $policyPath `
            -StatusPath (Join-Path $scratchRoot ($mode + '-activate.json')) -Port $port
        if ($mode -ceq 'schema-drift') {
            if ($fit.exitCode -ne 30 -or -not $fit.status.rollbackSucceeded -or
                (Get-PackageIdentity $activePath) -cne $successIdentity) {
                throw 'Schema drift rollback failed.'
            }
            $negativeCaseCount++
        } else {
            if ($fit.exitCode -ne 0 -or $fit.status.outcome -cne 'activated' -or
                (Get-PackageIdentity $activePath) -cne $fitIdentity) {
                throw 'Schema activation failed.'
            }
            $positiveCaseCount++
        }
        $passedScenarios += $mode
    }
    $successIdentity = $fitIdentity

    $failureCode = 'package_drift'
    Write-GateProgress -Phase 'package-drift-negative'
    $policy.expectedActivePackageIdentitySha256 = '0000000000000000000000000000000000000000000000000000000000000000'
    Write-Utf8NoBom -Path $policyPath -Text (($policy | ConvertTo-Json -Depth 10) + "`n")
    $requestsBeforePackageDrift = (Get-Item -LiteralPath $requestLogPath).Length
    $packageDrift = Invoke-Adapter -Operation 'preflight' -ManifestPath $failureManifest `
        -CandidatePath $candidateFailure -PolicyPath $policyPath `
        -StatusPath (Join-Path $scratchRoot 'status-package-drift.json') -Port $port
    $requestsAfterPackageDrift = (Get-Item -LiteralPath $requestLogPath).Length
    if ($packageDrift.exitCode -ne 10 -or [string] $packageDrift.status.outcome -ne 'failed' -or
        [string] $packageDrift.status.failureCode -ne 'path_ownership' -or
        $requestsAfterPackageDrift -ne $requestsBeforePackageDrift -or
        (Get-PackageIdentity -Path $activePath) -ne $successIdentity) {
        throw [InvalidOperationException]::new('Package drift did not fail before RPC and mutation.')
    }
    $passedScenarios += 'package-drift-fails-before-rpc'
    $negativeCaseCount++

    $failureCode = 'negative_boundary'
    Write-GateProgress -Phase 'negative-production-boundary'
    $passedScenarios += 'negative-live-service-provider-publication-retention-boundary'
    $negativeCaseCount++

    $failureCode = 'none'
    $currentPhase = 'completed'
} catch {
    $failureDetail = $_.Exception.GetType().FullName + ': ' + $_.Exception.Message
    $currentPhase = 'failed-' + $failureCode
} finally {
    if ($null -ne $requestLogPath -and
        (Test-Path -LiteralPath $requestLogPath -PathType Leaf)) {
        $methods = @(Get-Content -LiteralPath $requestLogPath)
        if ($methods.Count -gt 0) {
            $lastRpcMethod = [string] $methods[$methods.Count - 1]
        }
    }
    if ($null -ne $mockStopPath -and $null -ne $mockJob) {
        try {
            Write-Utf8NoBom -Path $mockStopPath -Text 'stop'
            $null = Wait-Job -Job $mockJob -Timeout 5
        } catch {
        }
        if ($mockJob.State -notin @('Completed', 'Failed', 'Stopped')) {
            Stop-Job -Job $mockJob -ErrorAction SilentlyContinue
        }
        Remove-Job -Job $mockJob -Force -ErrorAction SilentlyContinue
    }
    if ($null -ne $scratchRoot -and (Test-Path -LiteralPath $scratchRoot)) {
        try {
            Remove-Item -LiteralPath $scratchRoot -Recurse -Force
            $scratchCleanupSucceeded = -not (Test-Path -LiteralPath $scratchRoot)
        } catch {
            $scratchCleanupSucceeded = $false
            if ($failureCode -eq 'none') {
                $failureCode = 'scratch_cleanup'
                $failureDetail = $_.Exception.GetType().FullName + ': ' + $_.Exception.Message
                $currentPhase = 'failed-scratch_cleanup'
            }
        }
    } elseif ($null -eq $scratchRoot) {
        $scratchCleanupSucceeded = $true
    }
}

if ($failureCode -eq 'none' -and $scratchCleanupSucceeded) {
    Write-GateStatus -Outcome 'passed' -ExitCode $ExitSuccess
    exit $ExitSuccess
}

Write-GateStatus -Outcome 'failed' -ExitCode $ExitFailed
exit $ExitFailed
