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
    [string] $AdapterStateRoot,

    [Parameter()]
    [ValidateRange(1, 64)]
    [int] $MaxDeploymentCount = 16,

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

    # Additive maintenance is deliberately separate from initial SSH/channel setup.
    [Parameter()]
    [switch] $AddStandaloneModuleTarget,

    [Parameter()]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedChannelPolicySha256,

    [Parameter()]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedTargetManifestSha256,

    [Parameter()]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedAdditionPlanSha256,

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
        'SaefChildProcess.ps1',
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
        'Invoke-SaefSymconRestart.ps1',
        'SaefChildProcess.ps1'
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
    $record = Get-Content -LiteralPath $Path -Raw -Encoding UTF8 | ConvertFrom-Json
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
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyCollection()]
        [array] $Snapshots
    )

    foreach ($snapshot in $Snapshots) {
        if ($null -ne $snapshot.Bytes) {
            [Array]::Clear($snapshot.Bytes, 0, $snapshot.Bytes.Length)
        }
    }
}

function Assert-InitialSetupTargetSafety {
    $existingPolicyPath = Join-Path $InstallRoot 'deployment-channel.local.json'
    if (Test-Path -LiteralPath $existingPolicyPath) {
        # Never project an installed target back onto the older bootstrap schema.
        $existingPolicy = Get-Content -LiteralPath $existingPolicyPath -Raw | ConvertFrom-Json
        if ($null -ne $existingPolicy.PSObject.Properties['standaloneModuleTargets'] -and
            @($existingPolicy.standaloneModuleTargets).Count -gt 0) {
            throw [InvalidOperationException]::new('Installed module targets require additive maintenance; full initialization is blocked.')
        }
    }
}

function Assert-AdditionPlainPath {
    param([string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or $Path.StartsWith('\\') -or
        $Path.Substring(2).Contains(':')) {
        throw [IO.IOException]::new('Target addition requires local absolute paths without alternate streams.')
    }
    $cursor = [IO.Path]::GetFullPath($Path)
    while (-not [string]::IsNullOrEmpty($cursor)) {
        if (Test-Path -LiteralPath $cursor) {
            if (((Get-Item -LiteralPath $cursor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw [IO.IOException]::new('Target addition does not traverse reparse points.')
            }
        }
        $cursor = Split-Path -Parent $cursor
    }
}

function Assert-AdditionProtectedPath {
    param([string] $Path)
    Assert-AdditionPlainPath $Path
    $acl = Get-Acl -LiteralPath $Path
    $trusted = @('S-1-5-18', 'S-1-5-32-544',
        [Security.Principal.WindowsIdentity]::GetCurrent().User.Value)
    if ($null -ne (Get-Variable -Name additionDeploymentSid -ErrorAction SilentlyContinue)) {
        $trusted += $additionDeploymentSid
    }
    $writeRights = [Security.AccessControl.FileSystemRights]::Write -bor
        [Security.AccessControl.FileSystemRights]::Delete -bor
        [Security.AccessControl.FileSystemRights]::ChangePermissions -bor
        [Security.AccessControl.FileSystemRights]::TakeOwnership -bor
        [Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles
    if ($acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -notin $trusted) {
        throw [Security.SecurityException]::new('Target addition requires a trusted filesystem owner.')
    }
    foreach ($rule in $acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) {
        if ($rule.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            ($rule.FileSystemRights -band $writeRights) -ne 0 -and $rule.IdentityReference.Value -notin $trusted) {
            throw [Security.SecurityException]::new('Target addition requires protected filesystem ACLs.')
        }
    }
}

function Assert-AdditionStatusDestination {
    Assert-AdditionPlainPath $StatusPath
    $destination = [IO.Path]::GetFullPath($StatusPath)
    $protectedRoot = [IO.Path]::GetFullPath($InstallRoot).TrimEnd('\')
    if ($destination.Equals($protectedRoot, [StringComparison]::OrdinalIgnoreCase) -or
        $destination.StartsWith($protectedRoot + '\', [StringComparison]::OrdinalIgnoreCase) -or
        (Split-Path -Leaf $destination) -cnotmatch '^[a-z0-9.-]*status\.local\.json$') {
        throw [IO.IOException]::new('Addition status must be a dedicated status.local.json file outside the installed channel.')
    }
    $sources = @($StandaloneModuleTargetsPath)
    if (Test-Path -LiteralPath $StandaloneModuleTargetsPath -PathType Leaf) {
        if ((Get-Item -LiteralPath $StandaloneModuleTargetsPath).Length -gt 1048576) {
            throw [IO.IOException]::new('Target manifest exceeds its byte limit.')
        }
        $record = ConvertFrom-AdditionJson ([IO.File]::ReadAllBytes($StandaloneModuleTargetsPath))
        foreach ($target in @($record.targets)) {
            $sources += @([string] $target.adapterPath, [string] $target.adapterPolicyPath)
        }
    }
    foreach ($source in $sources) {
        if ($destination.Equals([IO.Path]::GetFullPath($source), [StringComparison]::OrdinalIgnoreCase)) {
            throw [IO.IOException]::new('Addition status must not overwrite an input source.')
        }
    }
}

function Read-AdditionBoundBytes {
    param([string] $Path, [string] $ExpectedHash, [long] $MaximumBytes = 1048576)
    Assert-AdditionPlainPath $Path
    if ($ExpectedHash -cnotmatch '^[a-f0-9]{64}$' -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path).Length -gt $MaximumBytes) {
        throw [IO.IOException]::new('Target addition dependency is missing, unbound or oversized.')
    }
    $bytes = [IO.File]::ReadAllBytes($Path)
    if ((Get-BytesSha256 $bytes) -cne $ExpectedHash) {
        throw [IO.IOException]::new('Target addition dependency hash changed.')
    }
    return ,$bytes
}

function ConvertFrom-AdditionJson {
    param([byte[]] $Bytes)
    $text = [Text.UTF8Encoding]::new($false, $true).GetString($Bytes)
    # ConvertFrom-Json alone silently accepts duplicate keys. Bound depth and reject
    # case aliases before deserializing, including inside future extension records.
    $objects = [Collections.Generic.Stack[object]]::new()
    for ($index = 0; $index -lt $text.Length; $index++) {
        $char = $text[$index]
        if ($char -eq '{' -or $char -eq '[') {
            $objects.Push($(if ($char -eq '{') { @{} } else { $null }))
            if ($objects.Count -gt 32) { throw [InvalidOperationException]::new('Addition JSON exceeds its nesting limit.') }
        } elseif ($char -eq '}' -or $char -eq ']') {
            if ($objects.Count -eq 0) { throw [InvalidOperationException]::new('Invalid addition JSON.') }
            $null = $objects.Pop()
        } elseif ($char -eq '"') {
            $start = $index
            $index++
            while ($index -lt $text.Length -and $text[$index] -ne '"') {
                if ($text[$index] -eq '\') { $index++ }
                $index++
            }
            if ($index -ge $text.Length) { throw [InvalidOperationException]::new('Invalid addition JSON string.') }
            $next = $index + 1
            while ($next -lt $text.Length -and [char]::IsWhiteSpace($text[$next])) { $next++ }
            if ($next -lt $text.Length -and $text[$next] -eq ':') {
                $name = ('{"key":' + $text.Substring($start, $index - $start + 1) + '}') | ConvertFrom-Json
                if ($objects.Count -eq 0 -or $null -eq $objects.Peek() -or $objects.Peek().ContainsKey($name.key)) {
                    throw [InvalidOperationException]::new('Duplicate or invalid addition JSON property.')
                }
                $objects.Peek()[$name.key] = $true
            }
        }
    }
    return ($text | ConvertFrom-Json)
}

function Assert-AdditionBindings {
    param($Targets)
    $ids = @{}
    $guids = @{}
    foreach ($target in @($Targets)) {
        if ([string] $target.targetId -cnotmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$' -or
            $ids.ContainsKey([string] $target.targetId) -or $guids.ContainsKey([string] $target.libraryGuid)) {
            throw [InvalidOperationException]::new('Duplicate or invalid installed target identity.')
        }
        $ids[[string] $target.targetId] = $true
        $guids[[string] $target.libraryGuid] = $true
        foreach ($binding in @(
            @('adapterPath', 'expectedAdapterSha256'),
            @('adapterPolicyPath', 'expectedAdapterPolicySha256'),
            @('approvalRunnerPath', 'expectedApprovalRunnerSha256'),
            @('approvalPolicyPath', 'expectedApprovalPolicySha256')
        )) {
            $hasPath = $null -ne $target.PSObject.Properties[$binding[0]]
            $hasHash = $null -ne $target.PSObject.Properties[$binding[1]]
            if ($hasPath -ne $hasHash -or (-not $hasPath -and $binding[0].StartsWith('adapter'))) {
                throw [InvalidOperationException]::new('Installed target has an incomplete binding.')
            }
            if ($hasPath) {
                $path = [string] $target.($binding[0])
                Assert-AdditionProtectedPath $path
                $null = Read-AdditionBoundBytes $path ([string] $target.($binding[1])) 4194304
            }
        }
    }
}

function Invoke-StandaloneTargetAddition {
    # Same mutex as the gateway: no deployment may overlap publication of policy.
    $mutex = [Threading.Mutex]::new($false, 'Global\SAEF.DeploymentChannel')
    $locked = $false
    $published = $false
    $moved = $false
    $phase = 'preflight'
    $details = @{ mutationAttempted = $false; sshdRestartAttempted = $false
        rollbackAttempted = $false; rollbackSucceeded = $false }
    try {
        try { $locked = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] {
            $locked = $true
            throw [InvalidOperationException]::new('Abandoned channel lock requires recovery review.')
        }
        if (-not $locked) { throw [InvalidOperationException]::new('Another deployment operation is active.') }
        Assert-SourceChecksums
        Assert-PowerShellSourceSyntax
        $account = Get-LocalUser -Name $DeploymentUser -ErrorAction Stop
        $group = Get-LocalGroup -SID 'S-1-5-32-544' -ErrorAction Stop
        if (-not $account.Enabled -or $account.SID -notin @(Get-LocalGroupMember -Group $group.Name).SID) {
            throw [Security.SecurityException]::new('Existing deployment account must remain an enabled local administrator.')
        }
        $additionDeploymentSid = $account.SID.Value
        Assert-AdditionProtectedPath $InstallRoot
        $policyPath = Join-Path $InstallRoot 'deployment-channel.local.json'
        Assert-AdditionProtectedPath $policyPath
        $before = Read-AdditionBoundBytes $policyPath $ExpectedChannelPolicySha256
        $manifestBytes = Read-AdditionBoundBytes $StandaloneModuleTargetsPath $ExpectedTargetManifestSha256
        $utf8 = [Text.UTF8Encoding]::new($false, $true)
        $policy = ConvertFrom-AdditionJson $before
        $manifest = ConvertFrom-AdditionJson $manifestBytes
        if ($policy.formatVersion -ne 1 -or
            [string] $policy.deploymentUser -cne $DeploymentUser.ToLowerInvariant() -or
            @($policy.standaloneModuleTargets).Count -ge 16 -or @($manifest.targets).Count -ne 1) {
            throw [InvalidOperationException]::new('Target addition requires the bound installed policy and exactly one new target.')
        }
        $originalTargets = @($policy.standaloneModuleTargets)
        Assert-AdditionBindings $originalTargets
        $targets = @(Read-StandaloneModuleTargets -Path $StandaloneModuleTargetsPath)
        if ($targets.Count -ne 1) { throw [InvalidOperationException]::new('Exactly one new target is required.') }
        $target = $targets[0]
        # The reusable bootstrap reader uses paths; close the manifest reread race.
        $null = Read-AdditionBoundBytes $StandaloneModuleTargetsPath $ExpectedTargetManifestSha256
        $null = ConvertFrom-AdditionJson $target.adapterPolicyBytes
        $inputTarget = @($manifest.targets)[0]
        # Require explicit reviewed source identities, not freshly inferred approval hashes.
        if ([string] $inputTarget.targetId -cnotmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$' -or
            $target.targetId -cne [string] $inputTarget.targetId -or
            $target.adapterProfile -cne [string] $inputTarget.adapterProfile -or
            $target.libraryGuid -cne ([string] $inputTarget.libraryGuid).ToUpperInvariant() -or
            $target.adapterSha256 -cne [string] $inputTarget.expectedAdapterSha256 -or
            $target.adapterPolicySha256 -cne [string] $inputTarget.expectedAdapterPolicySha256) {
            throw [InvalidOperationException]::new('New target source hashes do not match reviewed bindings.')
        }
        $allowed = @('targetId', 'adapterProfile', 'libraryGuid', 'adapterPath', 'adapterPolicyPath',
            'expectedAdapterSha256', 'expectedAdapterPolicySha256')
        foreach ($property in $inputTarget.PSObject.Properties) {
            if ($property.Name -cnotin $allowed) {
                throw [InvalidOperationException]::new('Unsupported new-target field; use its separately qualified installer.')
            }
        }
        foreach ($existing in $originalTargets) {
            if ([string] $existing.targetId -ieq $target.targetId -or
                [string] $existing.libraryGuid -ieq $target.libraryGuid) {
                throw [InvalidOperationException]::new('Target addition cannot replace an existing target or library.')
            }
        }
        $targetsRoot = Join-Path $InstallRoot 'standalone-modules'
        Assert-AdditionPlainPath $targetsRoot
        if (Test-Path -LiteralPath $targetsRoot) { Assert-AdditionProtectedPath $targetsRoot }
        $targetRoot = Join-Path $targetsRoot $target.targetId
        Assert-AdditionPlainPath $targetRoot
        if (Test-Path -LiteralPath $targetRoot) {
            throw [IO.IOException]::new('Target directory already exists; recovery review is required.')
        }
        $installed = [pscustomobject][ordered]@{
            targetId = $target.targetId; adapterProfile = $target.adapterProfile; libraryGuid = $target.libraryGuid
            adapterPath = Join-Path $targetRoot 'adapter.ps1'; expectedAdapterSha256 = $target.adapterSha256
            adapterPolicyPath = Join-Path $targetRoot 'adapter-policy.local.json'
            expectedAdapterPolicySha256 = $target.adapterPolicySha256
        }
        # Preserve the complete existing records, including future extension fields.
        $policy.standaloneModuleTargets = @($originalTargets) + @($installed)
        $after = $utf8.GetBytes(($policy | ConvertTo-Json -Depth 100) + [Environment]::NewLine)
        if ($after.Length -gt 1048576) { throw [IO.IOException]::new('Candidate channel policy exceeds its byte limit.') }
        $afterHash = Get-BytesSha256 $after
        $plan = $utf8.GetBytes('saef-add-target-v1' + "`n" + $ExpectedChannelPolicySha256 + "`n" +
            $ExpectedTargetManifestSha256 + "`n" + $afterHash)
        $planHash = Get-BytesSha256 $plan
        $details.additionPlanSha256 = $planHash
        $details.beforeChannelPolicySha256 = $ExpectedChannelPolicySha256
        $details.channelPolicySha256 = $afterHash
        $details.targetId = $target.targetId
        $retentionRoot = Join-Path $InstallRoot 'target-additions'
        Assert-AdditionPlainPath $retentionRoot
        if (Test-Path -LiteralPath $retentionRoot) {
            Assert-AdditionProtectedPath $retentionRoot
            if (@(Get-ChildItem -LiteralPath $retentionRoot -Force).Count -ge 16) {
                throw [IO.IOException]::new('Target-addition evidence limit reached; separately approved retention is required.')
            }
            foreach ($retained in @(Get-ChildItem -LiteralPath $retentionRoot -Force)) {
                $resultPath = Join-Path $retained.FullName 'result.local.json'
                Assert-AdditionProtectedPath $resultPath
                $result = Get-Content -LiteralPath $resultPath -Raw | ConvertFrom-Json
                if ([string] $result.outcome -cnotin @('installed', 'rolled_back')) {
                    throw [InvalidOperationException]::new('Incomplete target addition requires recovery review.')
                }
            }
        }
        if ($PreflightOnly) {
            return @{ phase = $phase; outcome = 'passed'; exitCode = 0; details = $details }
        }
        if ($ExpectedAdditionPlanSha256 -cne $planHash) {
            throw [InvalidOperationException]::new('Apply requires the exact reviewed addition plan hash.')
        }
        $phase = 'install'
        $details.mutationAttempted = $true
        if (-not (Test-Path -LiteralPath $retentionRoot)) {
            $null = New-Item -ItemType Directory -Path $retentionRoot
            Set-RestrictedAcl $retentionRoot '*S-1-5-32-544' '(OI)(CI)F'
        }
        $evidenceRoot = Join-Path $retentionRoot ([Guid]::NewGuid().ToString('N'))
        $null = New-Item -ItemType Directory -Path $evidenceRoot
        Set-RestrictedAcl $evidenceRoot '*S-1-5-32-544' '(OI)(CI)F'
        $details.evidenceRoot = $evidenceRoot
        $backupPath = Join-Path $evidenceRoot 'channel-before.local.json'
        [IO.File]::WriteAllBytes($backupPath, $before)
        Set-RestrictedFileAcl $backupPath
        $beforeAcl = Get-Acl -LiteralPath $policyPath
        [IO.File]::WriteAllText((Join-Path $evidenceRoot 'channel-before-acl.local.txt'), $beforeAcl.Sddl, $utf8)
        [IO.File]::WriteAllBytes((Join-Path $evidenceRoot 'channel-after.local.json'), $after)
        [IO.File]::WriteAllBytes((Join-Path $evidenceRoot 'target-manifest.local.json'), $manifestBytes)
        $staging = Join-Path $evidenceRoot 'target'
        $null = New-Item -ItemType Directory -Path $staging
        Set-RestrictedAcl $staging '*S-1-5-32-544' '(OI)(CI)F'
        [IO.File]::WriteAllBytes((Join-Path $staging 'adapter.ps1'), $target.adapterBytes)
        [IO.File]::WriteAllBytes((Join-Path $staging 'adapter-policy.local.json'), $target.adapterPolicyBytes)
        foreach ($file in @(Get-ChildItem -LiteralPath $staging -File)) { Set-RestrictedFileAcl $file.FullName }
        $candidatePath = Join-Path $evidenceRoot 'channel-candidate.local.json'
        [IO.File]::WriteAllBytes($candidatePath, $after)
        Set-Acl -LiteralPath $candidatePath -AclObject $beforeAcl
        # Revalidate immediately before publishing. No existing source is rewritten.
        $null = Read-AdditionBoundBytes $policyPath $ExpectedChannelPolicySha256
        $null = Read-AdditionBoundBytes $StandaloneModuleTargetsPath $ExpectedTargetManifestSha256
        Assert-AdditionBindings $originalTargets
        if (-not (Test-Path -LiteralPath $targetsRoot)) {
            $null = New-Item -ItemType Directory -Path $targetsRoot
            Set-RestrictedAcl $targetsRoot '*S-1-5-32-544' '(OI)(CI)F'
        }
        [IO.Directory]::Move($staging, $targetRoot)
        $moved = $true
        Assert-AdditionBindings @($installed)
        [IO.File]::Replace($candidatePath, $policyPath, (Join-Path $evidenceRoot 'channel-replaced.local.json'))
        $published = $true
        $null = Read-AdditionBoundBytes $policyPath $afterHash
        Assert-AdditionProtectedPath $policyPath
        if ((Get-Acl -LiteralPath $policyPath).Sddl -cne $beforeAcl.Sddl) {
            throw [Security.SecurityException]::new('Channel policy ACL changed during publication.')
        }
        Assert-AdditionBindings $policy.standaloneModuleTargets
        $details.outcome = 'installed'
        [IO.File]::WriteAllText((Join-Path $evidenceRoot 'result.local.json'), ($details | ConvertTo-Json), $utf8)
        $details.Remove('outcome')
        return @{ phase = $phase; outcome = 'installed'; exitCode = 0; details = $details }
    } catch {
        $details.errorType = $_.Exception.GetType().FullName
        $details.failureReason = $_.Exception.Message
        $details.rollbackAttempted = $published -or $moved
        try {
            if ($published) {
                # Do not overwrite an intervening external edit, even during recovery.
                $null = Read-AdditionBoundBytes $policyPath $afterHash
                $restorePath = Join-Path $evidenceRoot 'channel-restore.local.json'
                [IO.File]::WriteAllBytes($restorePath, $before)
                Set-Acl -LiteralPath $restorePath -AclObject $beforeAcl
                [IO.File]::Replace($restorePath, $policyPath, (Join-Path $evidenceRoot 'channel-failed.local.json'))
                $null = Read-AdditionBoundBytes $policyPath $ExpectedChannelPolicySha256
                if ((Get-Acl -LiteralPath $policyPath).Sddl -cne $beforeAcl.Sddl) {
                    throw [Security.SecurityException]::new('Channel policy ACL rollback failed.')
                }
            }
            if ($moved) {
                $null = Read-AdditionBoundBytes $policyPath $ExpectedChannelPolicySha256
                Assert-AdditionBindings @($installed)
                [IO.Directory]::Move($targetRoot, $staging)
            }
            $details.rollbackSucceeded = $true
        } catch { $details.rollbackSucceeded = $false }
        if ($details.ContainsKey('evidenceRoot')) {
            try {
                $details.outcome = $(if ($details.rollbackSucceeded) { 'rolled_back' } else { 'recovery_required' })
                [IO.File]::WriteAllText((Join-Path $details.evidenceRoot 'result.local.json'), ($details | ConvertTo-Json), $utf8)
            } catch { $details.rollbackSucceeded = $false }
            $details.Remove('outcome')
        }
        return @{ phase = $phase; outcome = 'failed'; exitCode = $(if ($phase -eq 'preflight') { 10 } else { 20 }); details = $details }
    } finally {
        if ($locked) { $mutex.ReleaseMutex() }
        $mutex.Dispose()
    }
}

$phase = 'preflight'
$failedStep = 'elevation'
$sshdConfigBackup = $null
$sshdConfigChanged = $false
$fileSnapshots = @()
$mutationsStarted = $false
$statusSafe = $true
$bootstrapMutex = $null
$bootstrapLocked = $false
try {
    Assert-Elevated | Out-Null
    if ($AddStandaloneModuleTarget) {
        $statusSafe = $false
        Assert-AdditionStatusDestination
        $statusSafe = $true
        $failedStep = 'standalone_target_addition'
        $addition = Invoke-StandaloneTargetAddition
        try {
            Write-BootstrapStatus -Phase $addition.phase -Outcome $addition.outcome `
                -ExitCode $addition.exitCode -Details $addition.details
        } catch {
            # Installation has its own protected result. A reporting failure must
            # not be presented as a no-mutation preflight failure or rolled back.
            [Console]::Error.WriteLine('Addition status write failed; inspect retained target-additions evidence before retrying.')
            exit $ExitInstallFailed
        }
        exit $addition.exitCode
    }
    if ($ExpectedChannelPolicySha256 -or $ExpectedTargetManifestSha256 -or $ExpectedAdditionPlanSha256) {
        throw [InvalidOperationException]::new('Addition hash parameters require AddStandaloneModuleTarget mode.')
    }
    $bootstrapMutex = [Threading.Mutex]::new($false, 'Global\SAEF.DeploymentChannel')
    try { $bootstrapLocked = $bootstrapMutex.WaitOne(0) } catch [Threading.AbandonedMutexException] {
        $bootstrapLocked = $true
        throw [InvalidOperationException]::new('Abandoned channel lock requires recovery review.')
    }
    if (-not $bootstrapLocked) { throw [InvalidOperationException]::new('Another deployment operation is active.') }
    Assert-InitialSetupTargetSafety
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
    if ([string]::IsNullOrWhiteSpace($AdapterStateRoot)) {
        $AdapterStateRoot = Join-Path $SymconScriptsRoot '.saef-adapter-states'
    }
    foreach ($path in @(
        $SymconScriptsRoot,
        $InstallRoot,
        $ManagedFilesetRoot,
        $StateRoot,
        $AdapterStateRoot,
        $StatusPath
    )) {
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
    $managedRoots = @(
        [IO.Path]::GetFullPath($ManagedFilesetRoot).TrimEnd([char[]] @('\', '/')),
        [IO.Path]::GetFullPath($StateRoot).TrimEnd([char[]] @('\', '/')),
        [IO.Path]::GetFullPath($AdapterStateRoot).TrimEnd([char[]] @('\', '/'))
    )
    foreach ($managedRoot in $managedRoots) {
        if (-not $managedRoot.StartsWith($scriptsPrefix, [StringComparison]::OrdinalIgnoreCase)) {
            throw [System.InvalidOperationException]::new(
                'Managed deployment roots must be below the Symcon scripts root.'
            )
        }
    }
    for ($leftIndex = 0; $leftIndex -lt $managedRoots.Count; $leftIndex++) {
        for ($rightIndex = $leftIndex + 1; $rightIndex -lt $managedRoots.Count; $rightIndex++) {
            $left = $managedRoots[$leftIndex]
            $right = $managedRoots[$rightIndex]
            if ($left.Equals($right, [StringComparison]::OrdinalIgnoreCase) -or
                $left.StartsWith($right + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -or
                $right.StartsWith($left + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
                throw [System.InvalidOperationException]::new(
                    'Deployment, fileset and adapter-state roots must be pairwise disjoint.'
                )
            }
        }
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
    $gatewayPath = Join-Path $InstallRoot 'Invoke-SaefDeploymentGateway.ps1'
    $normalizedDeploymentUser = $DeploymentUser.ToLowerInvariant()
    $deploymentMatchUsers = $normalizedDeploymentUser + ',.\' + $normalizedDeploymentUser
    $matchBlock = @"
$markerStart
Match User $deploymentMatchUsers
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
    $matchContentValid = $saefBlockMatches.Count -eq 1 -and
        $saefBlockMatches[0].Value.TrimEnd([char[]] @("`r", "`n")) -ceq $matchBlock
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
                repairRequired = $saefBlockMatches.Count -eq 1 -and
                    (-not $matchOrderValid -or -not $matchContentValid)
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
    $runtimeArtifactPaths = @(
        $gatewayPath,
        (Join-Path $InstallRoot 'Invoke-SaefRuntimeMirror.ps1'),
        (Join-Path $InstallRoot 'Invoke-SaefSymconRestart.ps1'),
        (Join-Path $InstallRoot 'SaefChildProcess.ps1'),
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
    foreach ($directory in @($InstallRoot, $ManagedFilesetRoot, $StateRoot, $AdapterStateRoot)) {
        if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
            [IO.Directory]::CreateDirectory($directory) | Out-Null
        }
    }
    Set-RestrictedAcl -Path $InstallRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)RX'
    Set-RestrictedAcl -Path $ManagedFilesetRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)F'
    Set-RestrictedAcl -Path $StateRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)F'
    Set-RestrictedAcl -Path $AdapterStateRoot -Identity $deploymentAclIdentity -IdentityRights '(OI)(CI)F'
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
        'SaefChildProcess.ps1',
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
        adapterStateRoot = [IO.Path]::GetFullPath($AdapterStateRoot)
        activeBootstrapRelativePath = $ActiveBootstrapRelativePath.Replace('\', '/')
        childProcessContractPath = Join-Path $InstallRoot 'SaefChildProcess.ps1'
        expectedChildProcessContractSha256 = (Get-FileHash -LiteralPath (Join-Path $InstallRoot 'SaefChildProcess.ps1') -Algorithm SHA256).Hash.ToLowerInvariant()
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
        deploymentUser = $normalizedDeploymentUser
        credentialPath = $credentialPath
        rpcUri = $RpcUri.AbsoluteUri
        serviceName = $ServiceName
        maxPackageBytes = 33554432
        maxExpandedBytes = 67108864
        maxFileCount = 256
        maxPreflightAgeSeconds = 900
        maxDeploymentCount = $MaxDeploymentCount
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
    if (-not $statusSafe) {
        [Console]::Error.WriteLine('Unsafe addition status destination or malformed input; no status file written.')
        exit $exitCode
    }
    Write-BootstrapStatus -Phase $phase -Outcome 'failed' -ExitCode $exitCode `
        -Details @{
            errorType = $failureException.GetType().FullName
            failedStep = $failedStep
            rollbackAttempted = $rollbackAttempted
            rollbackSucceeded = $rollbackSucceeded
        }
    exit $exitCode
} finally {
    if ($bootstrapLocked) { $bootstrapMutex.ReleaseMutex() }
    if ($null -ne $bootstrapMutex) { $bootstrapMutex.Dispose() }
}
