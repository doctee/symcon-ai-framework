[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_.-]{1,64}$')]
    [string] $DeploymentUser,

    [Parameter()]
    [switch] $PreflightOnly,

    [Parameter()]
    [string] $PublicKeyPath,

    [Parameter()]
    [PSCredential] $RpcCredential,

    [Parameter()]
    [string] $SymconScriptsRoot = (Join-Path $env:ProgramData 'Symcon\scripts'),

    [Parameter()]
    [string] $ActiveBootstrapRelativePath = 'System.Locals.ips.php',

    [Parameter()]
    [string] $InstallRoot = (Join-Path $env:ProgramData 'SAEF\DeploymentChannel'),

    [Parameter()]
    [string] $ManagedFilesetRoot,

    [Parameter()]
    [string] $StateRoot,

    [Parameter()]
    [Uri] $RpcUri = 'http://127.0.0.1:3777/api/',

    [Parameter()]
    [string] $ServiceName = 'IPSServer',

    [Parameter()]
    [ValidateRange(0, 2147483647)]
    [int] $RuntimeMirrorParentID = 0,

    [Parameter()]
    [ValidatePattern('^[A-Za-z0-9_]{1,128}$')]
    [string] $RuntimeMirrorIdent = 'SAEF_RUNTIME_SOURCE_MIRROR',

    [Parameter()]
    [ValidateLength(1, 255)]
    [string] $RuntimeMirrorName = 'SAEF Runtime Source Mirror',

    [Parameter()]
    [int] $RuntimeMirrorPosition = 90,

    [Parameter()]
    [ValidateRange(0, 2147483647)]
    [int] $RuntimeHealthProbeScriptID = 0,

    [Parameter()]
    [string] $StandaloneModuleTargetsPath,

    [Parameter()]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($StatusPath)) {
    $StatusPath = Join-Path $PSScriptRoot 'deployment-channel-bootstrap-status.local.json'
}

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitInstallFailed = 20
$markerStart = '# BEGIN SAEF DEPLOYMENT CHANNEL'
$markerEnd = '# END SAEF DEPLOYMENT CHANNEL'

function Write-BootstrapStatus {
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
    if ([string]::IsNullOrWhiteSpace($directory) -or -not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [System.IO.DirectoryNotFoundException]::new('Bootstrap status directory is missing.')
    }
    $temporary = Join-Path $directory ('.saef-bootstrap-' + [Guid]::NewGuid().ToString('N') + '.tmp')
    $backup = Join-Path $directory ('.saef-bootstrap-' + [Guid]::NewGuid().ToString('N') + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            ($status | ConvertTo-Json -Depth 5) + [Environment]::NewLine,
            [Text.UTF8Encoding]::new($false)
        )
        if (Test-Path -LiteralPath $StatusPath -PathType Leaf) {
            [IO.File]::Replace($temporary, $StatusPath, $backup)
        } else {
            [IO.File]::Move($temporary, $StatusPath)
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

function Assert-Elevated {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [System.Security.SecurityException]::new('Bootstrap requires an elevated PowerShell process.')
    }
    return $identity
}

function Assert-SourceChecksums {
    $checksumPath = Join-Path $PSScriptRoot 'SHA256SUMS'
    if (-not (Test-Path -LiteralPath $checksumPath -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Windows deployment checksum inventory is missing.')
    }
    $required = @(
        'Invoke-SaefDeploymentGateway.ps1',
        'Invoke-SaefDeploymentRetentionCleanup.ps1',
        'Invoke-SaefRuntimeMirror.ps1',
        'Invoke-SaefSymconRestart.ps1',
        'SaefRuntimeHealthProbe.php',
        'SaefRuntimeSourceMirror.php',
        'restart-policy.json'
    )
    $checksums = @{}
    foreach ($line in Get-Content -LiteralPath $checksumPath) {
        if ($line -match '^([a-f0-9]{64})  ([A-Za-z0-9_.-]+)$') {
            $checksums[$Matches[2]] = $Matches[1]
        }
    }
    foreach ($name in $required) {
        $path = Join-Path $PSScriptRoot $name
        if (-not $checksums.ContainsKey($name) -or -not (Test-Path -LiteralPath $path -PathType Leaf)) {
            throw [System.IO.FileNotFoundException]::new('Required Windows deployment artifact is missing.')
        }
        $actual = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($actual -ne $checksums[$name]) {
            throw [System.InvalidOperationException]::new('Windows deployment artifact hash mismatch.')
        }
    }
}

function Assert-PowerShellSourceSyntax {
    foreach ($name in @(
        'Initialize-SaefDeploymentChannel.ps1',
        'Invoke-SaefDeploymentGateway.ps1',
        'Invoke-SaefDeploymentRetentionCleanup.ps1',
        'Invoke-SaefRuntimeMirror.ps1',
        'Invoke-SaefSymconRestart.ps1'
    )) {
        $tokens = $null
        $parseErrors = $null
        $path = Join-Path $PSScriptRoot $name
        [Management.Automation.Language.Parser]::ParseFile(
            $path,
            [ref] $tokens,
            [ref] $parseErrors
        ) | Out-Null
        if (@($parseErrors).Count -ne 0) {
            throw [System.InvalidOperationException]::new("PowerShell source syntax is invalid: $name")
        }
    }
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

function Read-StandaloneModuleTargets {
    param([Parameter()][string] $Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        return @()
    }
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path).Length -gt 1048576 -or
        (((Get-Item -LiteralPath $Path).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [System.IO.FileNotFoundException]::new('Standalone module target policy is missing or invalid.')
    }
    $record = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    $targets = @($record.targets)
    if ($record.formatVersion -ne 1 -or $targets.Count -gt 16) {
        throw [System.InvalidOperationException]::new('Standalone module target policy format is invalid.')
    }
    $targetIds = @{}
    $validated = @()
    foreach ($target in $targets) {
        $targetId = [string] $target.targetId
        if ($targetId -notmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$' -or $targetIds.ContainsKey($targetId) -or
            [string] $target.adapterProfile -notmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$' -or
            [string] $target.libraryGuid -notmatch '^\{[A-Fa-f0-9]{8}(?:-[A-Fa-f0-9]{4}){3}-[A-Fa-f0-9]{12}\}$' -or
            -not [IO.Path]::IsPathRooted([string] $target.adapterPath) -or
            -not [IO.Path]::IsPathRooted([string] $target.adapterPolicyPath)) {
            throw [System.InvalidOperationException]::new('Standalone module target entry is invalid.')
        }
        foreach ($dependencyPath in @([string] $target.adapterPath, [string] $target.adapterPolicyPath)) {
            if (-not (Test-Path -LiteralPath $dependencyPath -PathType Leaf) -or
                ((Get-Item -LiteralPath $dependencyPath).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw [System.IO.FileNotFoundException]::new('Standalone module target dependency is missing.')
            }
        }
        $adapterFile = Get-Item -LiteralPath ([string] $target.adapterPath)
        $adapterPolicyFile = Get-Item -LiteralPath ([string] $target.adapterPolicyPath)
        if ($adapterFile.Length -gt 4194304 -or $adapterPolicyFile.Length -gt 1048576) {
            throw [System.InvalidOperationException]::new('Standalone module target dependency exceeds its byte limit.')
        }
        $adapterBytes = [IO.File]::ReadAllBytes([string] $target.adapterPath)
        $adapterPolicyBytes = [IO.File]::ReadAllBytes([string] $target.adapterPolicyPath)
        $adapterPolicy = [Text.UTF8Encoding]::new($false, $true).GetString($adapterPolicyBytes) | ConvertFrom-Json
        if ($adapterPolicy.formatVersion -ne 1 -or
            [string] $adapterPolicy.adapterProfile -ne [string] $target.adapterProfile) {
            throw [System.InvalidOperationException]::new('Standalone module adapter policy identity is invalid.')
        }
        $tokens = $null
        $parseErrors = $null
        [Management.Automation.Language.Parser]::ParseInput(
            [Text.UTF8Encoding]::new($false, $true).GetString($adapterBytes),
            [ref] $tokens,
            [ref] $parseErrors
        ) | Out-Null
        if (@($parseErrors).Count -ne 0) {
            throw [System.InvalidOperationException]::new('Standalone module adapter syntax is invalid.')
        }
        $targetIds[$targetId] = $true
        $validated += [ordered]@{
            targetId = $targetId
            adapterProfile = [string] $target.adapterProfile
            libraryGuid = ([string] $target.libraryGuid).ToUpperInvariant()
            adapterBytes = $adapterBytes
            adapterSha256 = Get-BytesSha256 -Bytes $adapterBytes
            adapterPolicyBytes = $adapterPolicyBytes
            adapterPolicySha256 = Get-BytesSha256 -Bytes $adapterPolicyBytes
        }
    }
    return @($validated)
}

function Set-RestrictedAcl {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $Identity,
        [Parameter(Mandatory = $true)][string] $IdentityRights
    )

    & icacls.exe $Path '/inheritance:r' | Out-Null
    & icacls.exe $Path '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' ($Identity + ':' + $IdentityRights) | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [System.InvalidOperationException]::new('Cannot apply restricted filesystem ACL.')
    }
}

function Set-RestrictedFileAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    & icacls.exe $Path '/inheritance:r' | Out-Null
    & icacls.exe $Path '/grant:r' '*S-1-5-18:F' '*S-1-5-32-544:F' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [System.InvalidOperationException]::new('Cannot apply restricted file ACL.')
    }
}

function Protect-MachineCredential {
    param([Parameter(Mandatory = $true)][PSCredential] $Credential)

    Add-Type -AssemblyName System.Security -ErrorAction Stop
    $networkCredential = $Credential.GetNetworkCredential()
    if ([string]::IsNullOrWhiteSpace($Credential.UserName) -or
        [string]::IsNullOrEmpty($networkCredential.Password)) {
        throw [System.InvalidOperationException]::new('RPC credential must contain username and password.')
    }
    $entropy = [Text.Encoding]::UTF8.GetBytes('SAEF.DeploymentChannel.RpcCredential.v1')
    $passwordBytes = [Text.Encoding]::UTF8.GetBytes($networkCredential.Password)
    $protectedBytes = $null
    try {
        $protectedBytes = [Security.Cryptography.ProtectedData]::Protect(
            $passwordBytes,
            $entropy,
            [Security.Cryptography.DataProtectionScope]::LocalMachine
        )
        $record = [ordered]@{
            formatVersion = 1
            protectionScope = 'LocalMachine'
            username = $Credential.UserName
            protectedPasswordBase64 = [Convert]::ToBase64String($protectedBytes)
        }
        return ($record | ConvertTo-Json -Depth 3) + [Environment]::NewLine
    } finally {
        [Array]::Clear($passwordBytes, 0, $passwordBytes.Length)
        [Array]::Clear($entropy, 0, $entropy.Length)
        if ($null -ne $protectedBytes) {
            [Array]::Clear($protectedBytes, 0, $protectedBytes.Length)
        }
        $networkCredential = $null
    }
}

function Get-FileSnapshot {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return [pscustomobject]@{ Path = $Path; Exists = $false; Bytes = $null; Acl = $null }
    }
    return [pscustomobject]@{
        Path = $Path
        Exists = $true
        Bytes = [IO.File]::ReadAllBytes($Path)
        Acl = Get-Acl -LiteralPath $Path
    }
}

function Restore-FileSnapshots {
    param([Parameter(Mandatory = $true)][array] $Snapshots)

    foreach ($snapshot in $Snapshots) {
        if ($snapshot.Exists) {
            [IO.File]::WriteAllBytes($snapshot.Path, $snapshot.Bytes)
            Set-Acl -LiteralPath $snapshot.Path -AclObject $snapshot.Acl
        } elseif (Test-Path -LiteralPath $snapshot.Path -PathType Leaf) {
            Remove-Item -LiteralPath $snapshot.Path -Force
        }
    }
}

function Clear-FileSnapshots {
    param([Parameter(Mandatory = $true)][array] $Snapshots)

    foreach ($snapshot in $Snapshots) {
        if ($null -ne $snapshot.Bytes) {
            [Array]::Clear($snapshot.Bytes, 0, $snapshot.Bytes.Length)
        }
    }
}

$phase = 'preflight'
$failedStep = 'elevation'
$sshdConfigBackup = $null
$sshdConfigChanged = $false
$fileSnapshots = @()
$mutationsStarted = $false
try {
    Assert-Elevated | Out-Null
    $failedStep = 'deployment_account'
    $deploymentAccount = Get-LocalUser -Name $DeploymentUser -ErrorAction Stop
    if (-not $deploymentAccount.Enabled) {
        throw [System.Security.SecurityException]::new('Deployment account is disabled.')
    }
    $administratorGroup = Get-LocalGroup -SID 'S-1-5-32-544' -ErrorAction Stop
    $administratorMembers = @(Get-LocalGroupMember -Group $administratorGroup.Name -ErrorAction Stop)
    if ($deploymentAccount.SID -notin $administratorMembers.SID) {
        throw [System.Security.SecurityException]::new('Deployment account must be a local administrator.')
    }
    $deploymentAclIdentity = '*' + $deploymentAccount.SID.Value
    $failedStep = 'source_checksums'
    Assert-SourceChecksums
    $failedStep = 'source_syntax'
    Assert-PowerShellSourceSyntax
    $failedStep = 'standalone_module_targets'
    $standaloneModuleTargets = @(Read-StandaloneModuleTargets -Path $StandaloneModuleTargetsPath)

    if ([string]::IsNullOrWhiteSpace($ManagedFilesetRoot)) {
        $ManagedFilesetRoot = Join-Path $SymconScriptsRoot '.saef-filesets'
    }
    if ([string]::IsNullOrWhiteSpace($StateRoot)) {
        $StateRoot = Join-Path $SymconScriptsRoot '.saef-deployments'
    }
    foreach ($path in @($SymconScriptsRoot, $InstallRoot, $ManagedFilesetRoot, $StateRoot, $StatusPath)) {
        if (-not [IO.Path]::IsPathRooted($path)) {
            throw [System.InvalidOperationException]::new('Bootstrap paths must be absolute.')
        }
    }
    if (-not (Test-Path -LiteralPath $SymconScriptsRoot -PathType Container)) {
        throw [System.IO.DirectoryNotFoundException]::new('Symcon scripts root is missing.')
    }
    $activeBootstrapPath = [IO.Path]::GetFullPath((Join-Path $SymconScriptsRoot $ActiveBootstrapRelativePath))
    $scriptsRootFullPath = [IO.Path]::GetFullPath($SymconScriptsRoot)
    $scriptsPrefix = $scriptsRootFullPath.TrimEnd([char[]] @('\', '/')) + [IO.Path]::DirectorySeparatorChar
    if (-not $activeBootstrapPath.StartsWith($scriptsPrefix, [StringComparison]::OrdinalIgnoreCase) -or
        -not (Test-Path -LiteralPath $activeBootstrapPath -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Active bootstrap is missing or outside the scripts root.')
    }
    if ($RpcUri.Scheme -notin @('http', 'https') -or $RpcUri.Host -notin @('127.0.0.1', 'localhost', '::1')) {
        throw [System.InvalidOperationException]::new('RPC URI must use an HTTP loopback endpoint.')
    }
    $symconService = Get-Service -Name $ServiceName -ErrorAction Stop
    if ($symconService.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Running) {
        throw [System.InvalidOperationException]::new('Symcon service is not running.')
    }
    $sshdService = Get-Service -Name 'sshd' -ErrorAction Stop
    if ($sshdService.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Running) {
        throw [System.InvalidOperationException]::new('OpenSSH service is not running.')
    }
    $sshdExecutable = Join-Path $env:SystemRoot 'System32\OpenSSH\sshd.exe'
    if (-not (Test-Path -LiteralPath $sshdExecutable -PathType Leaf)) {
        $sshdExecutable = (Get-Command 'sshd.exe' -ErrorAction Stop).Source
    }
    $sshdConfigPath = Join-Path $env:ProgramData 'ssh\sshd_config'
    if (-not (Test-Path -LiteralPath $sshdConfigPath -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('OpenSSH server configuration is missing.')
    }
    $sshdConfig = Get-Content -LiteralPath $sshdConfigPath -Raw
    $saefBlockPattern = '(?ms)^[\t ]*' + [regex]::Escape($markerStart) +
        '[\t ]*\r?\n.*?^[\t ]*' + [regex]::Escape($markerEnd) + '[\t ]*(?:\r?\n)?'
    $saefBlockRegex = [regex]::new($saefBlockPattern)
    $saefBlockMatches = $saefBlockRegex.Matches($sshdConfig)
    $containsSaefMarker = $sshdConfig.Contains($markerStart) -or $sshdConfig.Contains($markerEnd)
    if ($saefBlockMatches.Count -gt 1 -or ($containsSaefMarker -and $saefBlockMatches.Count -ne 1)) {
        throw [System.InvalidOperationException]::new('SAEF deployment SSH block is malformed.')
    }
    $firstActiveMatch = [regex]::Match($sshdConfig, '(?mi)^[\t ]*Match[\t ]+')
    $matchOrderValid = $saefBlockMatches.Count -eq 1 -and
        (-not $firstActiveMatch.Success -or $saefBlockMatches[0].Index -le $firstActiveMatch.Index)
    $baseSshdConfig = if ($saefBlockMatches.Count -eq 1) {
        $saefBlockRegex.Replace($sshdConfig, '', 1)
    } else {
        $sshdConfig
    }

    if ($PreflightOnly) {
        Write-BootstrapStatus -Phase 'preflight' -Outcome 'passed' -ExitCode $ExitSuccess `
            -Details @{
                mutationAttempted = $false
                sshdRestartAttempted = $false
                existingConfiguration = $saefBlockMatches.Count -eq 1
                repairRequired = $saefBlockMatches.Count -eq 1 -and -not $matchOrderValid
            }
        exit $ExitSuccess
    }

    $phase = 'install'
    if ([string]::IsNullOrWhiteSpace($PublicKeyPath) -or -not (Test-Path -LiteralPath $PublicKeyPath -PathType Leaf)) {
        throw [System.IO.FileNotFoundException]::new('Dedicated SSH public key is missing.')
    }
    if ($null -eq $RpcCredential) {
        throw [System.InvalidOperationException]::new('RPC credential is required for installation.')
    }
    foreach ($managedRoot in @($ManagedFilesetRoot, $StateRoot)) {
        $managedRootFullPath = [IO.Path]::GetFullPath($managedRoot)
        if (-not $managedRootFullPath.StartsWith($scriptsPrefix, [StringComparison]::OrdinalIgnoreCase)) {
            throw [System.InvalidOperationException]::new('Managed deployment roots must be below the Symcon scripts root.')
        }
    }
    $publicKeyLines = @(Get-Content -LiteralPath $PublicKeyPath | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($publicKeyLines.Count -ne 1 -or $publicKeyLines[0] -notmatch '^(?:ssh-ed25519|sk-ssh-ed25519@openssh.com) [A-Za-z0-9+/]+={0,3}(?: .*)?$') {
        throw [System.InvalidOperationException]::new('SSH public key must contain exactly one Ed25519 key.')
    }

    $failedStep = 'credential_protection'
    $credentialJson = Protect-MachineCredential -Credential $RpcCredential
    $credentialPath = Join-Path $InstallRoot 'rpc-credential.local.json'
    $legacyCredentialPath = Join-Path $InstallRoot 'rpc-credential.local.xml'
    $policyPath = Join-Path $InstallRoot 'deployment-channel.local.json'
    $authorizedKeyPath = Join-Path $env:ProgramData 'ssh\saef_deploy_authorized_keys'
    $gatewayPath = Join-Path $InstallRoot 'Invoke-SaefDeploymentGateway.ps1'
    $runtimeArtifactPaths = @(
        $gatewayPath,
        (Join-Path $InstallRoot 'Invoke-SaefRuntimeMirror.ps1'),
        (Join-Path $InstallRoot 'Invoke-SaefSymconRestart.ps1'),
        (Join-Path $InstallRoot 'SaefRuntimeHealthProbe.php'),
        (Join-Path $InstallRoot 'SaefRuntimeSourceMirror.php'),
        (Join-Path $InstallRoot 'restart-policy.json')
    )
    $installedStandaloneModuleTargets = @()
    $standaloneModuleTargetPaths = @()
    foreach ($target in $standaloneModuleTargets) {
        $targetRoot = Join-Path (Join-Path $InstallRoot 'standalone-modules') ([string] $target.targetId)
        $adapterPath = Join-Path $targetRoot 'adapter.ps1'
        $adapterPolicyPath = Join-Path $targetRoot 'adapter-policy.local.json'
        $standaloneModuleTargetPaths += @($adapterPath, $adapterPolicyPath)
        $installedStandaloneModuleTargets += [ordered]@{
            targetId = [string] $target.targetId
            adapterProfile = [string] $target.adapterProfile
            libraryGuid = [string] $target.libraryGuid
            adapterPath = $adapterPath
            expectedAdapterSha256 = [string] $target.adapterSha256
            adapterPolicyPath = $adapterPolicyPath
            expectedAdapterPolicySha256 = [string] $target.adapterPolicySha256
        }
    }
    $failedStep = 'rollback_snapshot'
    foreach ($path in @($runtimeArtifactPaths + $standaloneModuleTargetPaths + @(
        $credentialPath, $legacyCredentialPath, $policyPath, $authorizedKeyPath
    ))) {
        $fileSnapshots += Get-FileSnapshot -Path $path
    }
    $mutationsStarted = $true

    $failedStep = 'managed_directories'
    foreach ($directory in @($InstallRoot, $ManagedFilesetRoot, $StateRoot)) {
        if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
            [IO.Directory]::CreateDirectory($directory) | Out-Null
        }
    }
    Set-RestrictedAcl -Path $InstallRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)RX'
    Set-RestrictedAcl -Path $ManagedFilesetRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)F'
    Set-RestrictedAcl -Path $StateRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)F'
    if ($standaloneModuleTargets.Count -gt 0) {
        $moduleTargetsRoot = Join-Path $InstallRoot 'standalone-modules'
        if (-not (Test-Path -LiteralPath $moduleTargetsRoot -PathType Container)) {
            [IO.Directory]::CreateDirectory($moduleTargetsRoot) | Out-Null
        }
        Set-RestrictedAcl -Path $moduleTargetsRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)RX'
        foreach ($target in $standaloneModuleTargets) {
            $targetRoot = Join-Path $moduleTargetsRoot ([string] $target.targetId)
            if (-not (Test-Path -LiteralPath $targetRoot -PathType Container)) {
                [IO.Directory]::CreateDirectory($targetRoot) | Out-Null
            }
            Set-RestrictedAcl -Path $targetRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)RX'
            [IO.File]::WriteAllBytes((Join-Path $targetRoot 'adapter.ps1'), [byte[]] $target.adapterBytes)
            [IO.File]::WriteAllBytes(
                (Join-Path $targetRoot 'adapter-policy.local.json'),
                [byte[]] $target.adapterPolicyBytes
            )
            Set-RestrictedFileAcl -Path (Join-Path $targetRoot 'adapter.ps1')
            Set-RestrictedFileAcl -Path (Join-Path $targetRoot 'adapter-policy.local.json')
        }
    }

    $failedStep = 'runtime_artifacts'
    foreach ($name in @(
        'Invoke-SaefDeploymentGateway.ps1',
        'Invoke-SaefDeploymentRetentionCleanup.ps1',
        'Invoke-SaefRuntimeMirror.ps1',
        'Invoke-SaefSymconRestart.ps1',
        'SaefRuntimeHealthProbe.php',
        'SaefRuntimeSourceMirror.php',
        'restart-policy.json'
    )) {
        Copy-Item -LiteralPath (Join-Path $PSScriptRoot $name) -Destination (Join-Path $InstallRoot $name) -Force
    }
    [IO.File]::WriteAllText($credentialPath, $credentialJson, [Text.UTF8Encoding]::new($false))
    $credentialJson = $null

    $failedStep = 'local_policy'
    $policy = [ordered]@{
        formatVersion = 1
        scriptsRoot = [IO.Path]::GetFullPath($SymconScriptsRoot)
        managedFilesetRoot = [IO.Path]::GetFullPath($ManagedFilesetRoot)
        stateRoot = [IO.Path]::GetFullPath($StateRoot)
        activeBootstrapRelativePath = $ActiveBootstrapRelativePath.Replace('\', '/')
        restartCoordinatorPath = Join-Path $InstallRoot 'Invoke-SaefSymconRestart.ps1'
        expectedRestartCoordinatorSha256 = (Get-FileHash -LiteralPath (Join-Path $InstallRoot 'Invoke-SaefSymconRestart.ps1') -Algorithm SHA256).Hash.ToLowerInvariant()
        restartPolicyPath = Join-Path $InstallRoot 'restart-policy.json'
        expectedRestartPolicySha256 = (Get-FileHash -LiteralPath (Join-Path $InstallRoot 'restart-policy.json') -Algorithm SHA256).Hash.ToLowerInvariant()
        runtimeHealthProbeEnabled = $RuntimeHealthProbeScriptID -gt 0
        runtimeHealthProbeScriptID = $RuntimeHealthProbeScriptID
        expectedRuntimeHealthProbeSha256 = (Get-FileHash -LiteralPath (Join-Path $InstallRoot 'SaefRuntimeHealthProbe.php') -Algorithm SHA256).Hash.ToLowerInvariant()
        runtimeMirrorEnabled = $RuntimeMirrorParentID -gt 0
        runtimeMirrorCoordinatorPath = Join-Path $InstallRoot 'Invoke-SaefRuntimeMirror.ps1'
        expectedRuntimeMirrorCoordinatorSha256 = (Get-FileHash -LiteralPath (Join-Path $InstallRoot 'Invoke-SaefRuntimeMirror.ps1') -Algorithm SHA256).Hash.ToLowerInvariant()
        runtimeMirrorReconcilerPath = Join-Path $InstallRoot 'SaefRuntimeSourceMirror.php'
        expectedRuntimeMirrorReconcilerSha256 = (Get-FileHash -LiteralPath (Join-Path $InstallRoot 'SaefRuntimeSourceMirror.php') -Algorithm SHA256).Hash.ToLowerInvariant()
        runtimeMirrorParentID = $RuntimeMirrorParentID
        runtimeMirrorIdent = $RuntimeMirrorIdent
        runtimeMirrorName = $RuntimeMirrorName
        runtimeMirrorPosition = $RuntimeMirrorPosition
        standaloneModuleTargets = $installedStandaloneModuleTargets
        credentialPath = $credentialPath
        rpcUri = $RpcUri.AbsoluteUri
        serviceName = $ServiceName
        maxPackageBytes = 33554432
        maxExpandedBytes = 67108864
        maxFileCount = 256
        maxPreflightAgeSeconds = 900
        maxDeploymentCount = 16
        maxManagedBytes = 536870912
    }
    [IO.File]::WriteAllText(
        $policyPath,
        ($policy | ConvertTo-Json -Depth 5) + [Environment]::NewLine,
        [Text.UTF8Encoding]::new($false)
    )
    Set-RestrictedFileAcl -Path $credentialPath
    Set-RestrictedFileAcl -Path $policyPath

    $failedStep = 'authorized_key'
    [IO.File]::WriteAllText(
        $authorizedKeyPath,
        $publicKeyLines[0] + [Environment]::NewLine,
        [Text.UTF8Encoding]::new($false)
    )
    Set-RestrictedFileAcl -Path $authorizedKeyPath

    $failedStep = 'sshd_configuration'
    $matchBlock = @"
$markerStart
Match User $($DeploymentUser.ToLowerInvariant())
    AuthenticationMethods publickey
    PasswordAuthentication no
    PubkeyAuthentication yes
    AuthorizedKeysFile __PROGRAMDATA__/ssh/saef_deploy_authorized_keys
    PermitTTY no
    AllowTcpForwarding no
    PermitOpen none
    ForceCommand powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$gatewayPath`"
$markerEnd
"@
    $firstBaseMatch = [regex]::Match($baseSshdConfig, '(?mi)^[\t ]*Match[\t ]+')
    if ($firstBaseMatch.Success) {
        $configPrefix = $baseSshdConfig.Substring(0, $firstBaseMatch.Index).TrimEnd([char[]] @("`r", "`n"))
        $configSuffix = $baseSshdConfig.Substring($firstBaseMatch.Index).TrimStart([char[]] @("`r", "`n"))
        $updatedSshdConfig = $configPrefix + [Environment]::NewLine + [Environment]::NewLine +
            $matchBlock + [Environment]::NewLine + $configSuffix
    } else {
        $updatedSshdConfig = $baseSshdConfig.TrimEnd([char[]] @("`r", "`n")) +
            [Environment]::NewLine + [Environment]::NewLine + $matchBlock + [Environment]::NewLine
    }
    $sshdConfigBackup = $sshdConfigPath + '.saef-backup-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmss')
    Copy-Item -LiteralPath $sshdConfigPath -Destination $sshdConfigBackup
    [IO.File]::WriteAllText($sshdConfigPath, $updatedSshdConfig, [Text.UTF8Encoding]::new($false))
    $sshdConfigChanged = $true

    & $sshdExecutable '-t' '-f' $sshdConfigPath
    if ($LASTEXITCODE -ne 0) {
        throw [System.InvalidOperationException]::new('OpenSSH rejected the SAEF configuration.')
    }
    $failedStep = 'sshd_restart'
    Restart-Service -Name 'sshd' -ErrorAction Stop
    $sshdService = Get-Service -Name 'sshd' -ErrorAction Stop
    if ($sshdService.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Running) {
        throw [System.InvalidOperationException]::new('OpenSSH did not return to Running state.')
    }

    $failedStep = 'legacy_credential_cleanup'
    if (Test-Path -LiteralPath $legacyCredentialPath -PathType Leaf) {
        Remove-Item -LiteralPath $legacyCredentialPath -Force
    }

    Write-BootstrapStatus -Phase 'install' -Outcome 'installed' -ExitCode $ExitSuccess `
        -Details @{ mutationAttempted = $true; sshdRestartAttempted = $true; rollbackAttempted = $false }
    Clear-FileSnapshots -Snapshots $fileSnapshots
    exit $ExitSuccess
} catch {
    $failureException = $_.Exception
    $rollbackAttempted = $mutationsStarted -or $sshdConfigChanged
    $rollbackSucceeded = -not $rollbackAttempted
    if ($rollbackAttempted) {
        try {
            if ($mutationsStarted) {
                Restore-FileSnapshots -Snapshots $fileSnapshots
            }
            if ($sshdConfigChanged -and $null -ne $sshdConfigBackup -and
                (Test-Path -LiteralPath $sshdConfigBackup -PathType Leaf)) {
                Copy-Item -LiteralPath $sshdConfigBackup -Destination $sshdConfigPath -Force
                Restart-Service -Name 'sshd' -ErrorAction Stop
                if ((Get-Service -Name 'sshd' -ErrorAction Stop).Status -ne
                    [System.ServiceProcess.ServiceControllerStatus]::Running) {
                    throw [System.InvalidOperationException]::new('OpenSSH rollback did not return to Running state.')
                }
            }
            $rollbackSucceeded = $true
        } catch {
            $rollbackSucceeded = $false
        }
    }
    Clear-FileSnapshots -Snapshots $fileSnapshots
    $exitCode = if ($phase -eq 'preflight') { $ExitPreflightFailed } else { $ExitInstallFailed }
    Write-BootstrapStatus -Phase $phase -Outcome 'failed' -ExitCode $exitCode `
        -Details @{
            errorType = $failureException.GetType().FullName
            failedStep = $failedStep
            rollbackAttempted = $rollbackAttempted
            rollbackSucceeded = $rollbackSucceeded
        }
    exit $exitCode
}
