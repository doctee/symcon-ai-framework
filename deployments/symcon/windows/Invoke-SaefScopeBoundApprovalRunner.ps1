[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_-]{1,32768}$')]
    [string] $ApprovalEnvelopeBase64Url,

    [Parameter(Mandatory = $true)]
    [string] $ChannelPolicyPath,

    [Parameter(Mandatory = $true)]
    [string] $ManifestPath,

    [Parameter(Mandatory = $true)]
    [string] $CandidatePath,

    [Parameter(Mandatory = $true)]
    [string] $TransactionContractPath,

    [Parameter(Mandatory = $true)]
    [string] $PackageTransferPath,

    [Parameter(Mandatory = $true)]
    [string] $AdapterPath,

    [Parameter(Mandatory = $true)]
    [string] $AdapterPolicyPath,

    [Parameter(Mandatory = $true)]
    [string] $ApprovalPolicyPath,

    [Parameter(Mandatory = $true)]
    [Uri] $RpcUri,

    [Parameter(Mandatory = $true)]
    [string] $CredentialPath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_.-]{1,64}$')]
    [string] $DeploymentUser,

    [Parameter(Mandatory = $true)]
    [string] $DeploymentStatusPath,

    [Parameter(Mandatory = $true)]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitPreflightFailed = 10
$ExitRolledBack = 30
$ExitManualRecovery = 40
$MaximumJsonBytes = 1048576
$MaximumApprovalLifetimeSeconds = 900
$RunnerProfile = 'saef-channel-v8-one-click-v1'
$script:plan = $null
$script:approval = $null
$script:manifest = $null
$script:transfer = $null
$script:policy = $null
$script:planSha256 = ''
$script:manifestSha256 = ''
$script:packageIdentitySha256 = ''
$script:previousPackageIdentitySha256 = ''
$script:failureCode = 'initialization'
$script:activationAttempted = $false
$script:mutationAttempted = $false
$script:rollbackAttempted = $false
$script:rollbackSucceeded = $false
$script:finalOutcome = 'aborted'
$script:finalExitCode = $ExitPreflightFailed
$script:mutex = $null
$script:mutexAcquired = $false
$script:secretBytes = $null
$script:state = $null
$script:statePath = ''
$script:stateDirectory = ''
$script:terminalReached = $false
$script:stateClaimed = $false

function Test-HexSha256 {
    param([Parameter(Mandatory = $true)][string] $Value)
    return $Value -cmatch '^[a-f0-9]{64}$'
}

function Test-FixedTimeTextEquals {
    param(
        [Parameter(Mandatory = $true)][string] $Left,
        [Parameter(Mandatory = $true)][string] $Right
    )
    if ($Left.Length -ne $Right.Length) {
        return $false
    }
    $difference = 0
    for ($index = 0; $index -lt $Left.Length; $index++) {
        $difference = $difference -bor ([int] $Left[$index] -bxor [int] $Right[$index])
    }
    return $difference -eq 0
}

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        return ([BitConverter]::ToString($algorithm.ComputeHash([IO.File]::ReadAllBytes($Path)))).Replace(
            '-', ''
        ).ToLowerInvariant()
    } finally {
        $algorithm.Dispose()
    }
}

function Get-TextSha256 {
    param([Parameter(Mandatory = $true)][string] $Text)
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        return ([BitConverter]::ToString($algorithm.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant()
    } finally {
        $algorithm.Dispose()
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Assert-RootedLeaf {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][long] $MaximumBytes
    )
    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [IO.FileNotFoundException]::new('Required bounded file is missing.')
    }
    $item = Get-Item -LiteralPath $Path -Force
    if ([long] $item.Length -lt 1 -or [long] $item.Length -gt $MaximumBytes -or
        (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.IOException]::new('Required bounded file is unsafe.')
    }
}

function Assert-PlainDirectory {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Required directory is missing.')
    }
    if (((Get-Item -LiteralPath $Path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw [IO.IOException]::new('Managed directory is a reparse point.')
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

function Assert-ProtectedPathAcl {
    param([Parameter(Mandatory = $true)][string] $Path)
    $acl = Get-Acl -LiteralPath $Path
    foreach ($entry in @($acl.Access)) {
        $sid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entry.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            (Test-BroadWriteAccess -Rights $entry.FileSystemRights) -and
            $sid -in @('S-1-1-0', 'S-1-5-11', 'S-1-5-32-545')) {
            throw [Security.SecurityException]::new('Managed path grants broad write access.')
        }
    }
}

function Read-BoundedJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter()][long] $MaximumBytes = $MaximumJsonBytes
    )
    Assert-RootedLeaf -Path $Path -MaximumBytes $MaximumBytes
    return Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
}

function ConvertFrom-Base64UrlJson {
    param([Parameter(Mandatory = $true)][string] $Value)
    $base64 = $Value.Replace('-', '+').Replace('_', '/')
    switch ($base64.Length % 4) {
        0 { }
        2 { $base64 += '==' }
        3 { $base64 += '=' }
        default { throw [InvalidOperationException]::new('Approval envelope encoding is invalid.') }
    }
    $bytes = [Convert]::FromBase64String($base64)
    try {
        if ($bytes.Length -lt 2 -or $bytes.Length -gt 24576) {
            throw [InvalidOperationException]::new('Approval envelope size is invalid.')
        }
        $text = [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
        return $text | ConvertFrom-Json
    } finally {
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Assert-ExactProperties {
    param(
        [Parameter(Mandatory = $true)] $Value,
        [Parameter(Mandatory = $true)][string[]] $Names,
        [Parameter(Mandatory = $true)][string] $Label
    )
    if ($null -eq $Value -or $Value -is [Array] -or $Value -is [string] -or $Value -is [ValueType]) {
        throw [InvalidOperationException]::new($Label + ' must be an object.')
    }
    $actual = @($Value.PSObject.Properties.Name | Sort-Object)
    $expected = @($Names | Sort-Object)
    if ($actual.Count -ne $expected.Count) {
        throw [InvalidOperationException]::new($Label + ' fields differ.')
    }
    for ($index = 0; $index -lt $actual.Count; $index++) {
        if ([string] $actual[$index] -cne [string] $expected[$index]) {
            throw [InvalidOperationException]::new($Label + ' fields differ.')
        }
    }
}

function ConvertTo-CanonicalValue {
    param([Parameter(Mandatory = $true)] $Value)
    if ($Value -is [Array]) {
        $items = @()
        foreach ($item in @($Value)) {
            $items += ,(ConvertTo-CanonicalValue -Value $item)
        }
        return ,$items
    }
    if ($null -eq $Value -or $Value -is [string] -or $Value -is [ValueType]) {
        return $Value
    }
    if ($Value -is [Collections.IDictionary]) {
        $dictionary = [Collections.IDictionary] $Value
        $result = [ordered]@{}
        foreach ($name in @($dictionary.Keys | ForEach-Object { [string] $_ } | Sort-Object)) {
            $result[$name] = ConvertTo-CanonicalValue -Value $dictionary[$name]
        }
        return $result
    }
    $result = [ordered]@{}
    foreach ($name in @($Value.PSObject.Properties.Name | Sort-Object)) {
        $result[$name] = ConvertTo-CanonicalValue -Value $Value.$name
    }
    return $result
}

function ConvertTo-CanonicalJson {
    param([Parameter(Mandatory = $true)] $Value)
    return ConvertTo-CanonicalValue -Value $Value | ConvertTo-Json -Depth 20 -Compress
}

function Get-HmacSha256 {
    param(
        [Parameter(Mandatory = $true)][string] $Text,
        [Parameter(Mandatory = $true)][byte[]] $Secret
    )
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
    $hmac = [Security.Cryptography.HMACSHA256]::new()
    try {
        $hmac.Key = $Secret
        return ([BitConverter]::ToString($hmac.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant()
    } finally {
        $hmac.Dispose()
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Write-AtomicJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value
    )
    $directory = Split-Path -Parent $Path
    Assert-PlainDirectory -Path $directory
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-approval-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-approval-' + $token + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            ($Value | ConvertTo-Json -Depth 20) + [Environment]::NewLine,
            [Text.UTF8Encoding]::new($false)
        )
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
    Assert-PlainDirectory -Path $directory
    $token = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path $directory ('.saef-approval-' + $token + '.tmp')
    $backup = Join-Path $directory ('.saef-approval-' + $token + '.bak')
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

function Write-SignedState {
    $unsigned = [ordered]@{}
    foreach ($name in @($script:state.Keys | Where-Object { $_ -ne 'stateSignature' })) {
        $unsigned[$name] = $script:state[$name]
    }
    $script:state['stateSignature'] = Get-HmacSha256 `
        -Text (ConvertTo-CanonicalJson -Value ([pscustomobject] $unsigned)) `
        -Secret $script:secretBytes
    Write-AtomicJson -Path $script:statePath -Value $script:state
}

function Read-SignedState {
    if (-not (Test-Path -LiteralPath $script:statePath -PathType Leaf)) {
        return $null
    }
    $state = Read-BoundedJson -Path $script:statePath -MaximumBytes 262144
    Assert-ExactProperties -Value $state -Names @(
        'formatVersion', 'planSha256', 'targetId', 'adapterProfile', 'nonceSha256',
        'approvalExpiresAt', 'phase', 'phaseState', 'completedPhaseCount',
        'activationCompleted', 'resealMutationCompleted', 'outcome', 'evidence',
        'createdUtc', 'updatedUtc', 'stateSignature'
    ) -Label 'Approval state'
    if ($state.formatVersion -ne 1 -or -not (Test-HexSha256 -Value ([string] $state.stateSignature)) -or
        -not (Test-HexSha256 -Value ([string] $state.planSha256)) -or
        -not (Test-HexSha256 -Value ([string] $state.nonceSha256)) -or
        [int] $state.completedPhaseCount -lt 0 -or [int] $state.completedPhaseCount -gt 8 -or
        $state.activationCompleted -isnot [bool] -or $state.resealMutationCompleted -isnot [bool]) {
        throw [InvalidOperationException]::new('Approval state format is invalid.')
    }
    $unsigned = [ordered]@{}
    foreach ($name in @($state.PSObject.Properties.Name | Where-Object { $_ -ne 'stateSignature' })) {
        $unsigned[$name] = $state.$name
    }
    $expected = Get-HmacSha256 `
        -Text (ConvertTo-CanonicalJson -Value ([pscustomobject] $unsigned)) `
        -Secret $script:secretBytes
    if (-not (Test-FixedTimeTextEquals -Left $expected -Right ([string] $state.stateSignature))) {
        throw [Security.SecurityException]::new('Approval state integrity differs.')
    }
    $map = [ordered]@{}
    foreach ($name in @($state.PSObject.Properties.Name)) {
        $map[$name] = $state.$name
    }
    return $map
}

function Assert-RunningStatePhaseOrder {
    $phases = @(Get-ExecutionPhases)
    $completed = [int] $script:state.completedPhaseCount
    if ([string] $script:state.outcome -cne 'running' -or
        [string] $script:state.phaseState -notin @('completed', 'started') -or
        $completed -gt $phases.Count) {
        throw [InvalidOperationException]::new('Approval state phase order is invalid.')
    }
    if ([string] $script:state.phaseState -ceq 'started') {
        if ($completed -ge $phases.Count -or
            [string] $script:state.phase -cne [string] $phases[$completed]) {
            throw [InvalidOperationException]::new('Approval state phase order is invalid.')
        }
    } elseif ($completed -eq 0) {
        if ([string] $script:state.phase -cne 'claimed') {
            throw [InvalidOperationException]::new('Approval state phase order is invalid.')
        }
    } elseif ([string] $script:state.phase -cne [string] $phases[$completed - 1]) {
        throw [InvalidOperationException]::new('Approval state phase order is invalid.')
    }

    $evidenceNames = @($script:state.evidence.PSObject.Properties.Name)
    if ($evidenceNames.Count -ne $completed) {
        throw [InvalidOperationException]::new('Approval state evidence count is invalid.')
    }
    for ($index = 0; $index -lt $completed; $index++) {
        $phase = [string] $phases[$index]
        if ($evidenceNames -notcontains $phase -or
            -not (Test-HexSha256 -Value ([string] $script:state.evidence.$phase))) {
            throw [InvalidOperationException]::new('Approval state evidence order is invalid.')
        }
    }
}

function Start-Phase {
    param([Parameter(Mandatory = $true)][string] $Phase)
    $script:state['phase'] = $Phase
    $script:state['phaseState'] = 'started'
    $script:state['updatedUtc'] = [DateTime]::UtcNow.ToString('o')
    Write-SignedState
}

function Complete-Phase {
    param(
        [Parameter(Mandatory = $true)][string] $Phase,
        [Parameter(Mandatory = $true)][string] $EvidenceSha256
    )
    if (-not (Test-HexSha256 -Value $EvidenceSha256)) {
        throw [InvalidOperationException]::new('Phase evidence identity is invalid.')
    }
    $evidence = [ordered]@{}
    foreach ($name in @($script:state.evidence.PSObject.Properties.Name)) {
        $evidence[$name] = [string] $script:state.evidence.$name
    }
    $evidence[$Phase] = $EvidenceSha256
    $script:state['evidence'] = [pscustomobject] $evidence
    $script:state['completedPhaseCount'] = [int] $script:state.completedPhaseCount + 1
    $script:state['phaseState'] = 'completed'
    $script:state['updatedUtc'] = [DateTime]::UtcNow.ToString('o')
    Write-SignedState
}

function Set-TerminalState {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][ValidateSet('completed', 'failed', 'uncertain')][string] $PhaseState = 'completed'
    )
    $script:state['phaseState'] = $PhaseState
    $script:state['outcome'] = $Outcome
    $script:state['updatedUtc'] = [DateTime]::UtcNow.ToString('o')
    Write-SignedState
    $script:finalOutcome = $Outcome
    $script:finalExitCode = $ExitCode
    $script:terminalReached = $true
}

function Invoke-Adapter {
    param(
        [Parameter(Mandatory = $true)][ValidateSet('preflight', 'activate', 'postflight', 'inspect', 'rollback')]
        [string] $Operation,
        [Parameter(Mandatory = $true)][string] $ChildStatusPath
    )
    if (Test-Path -LiteralPath $ChildStatusPath -PathType Leaf) {
        Remove-Item -LiteralPath $ChildStatusPath -Force
    }
    $powerShell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    & $powerShell '-NoLogo' '-NoProfile' '-NonInteractive' '-ExecutionPolicy' 'Bypass' `
        '-File' $AdapterPath '-Operation' $Operation '-ManifestPath' $ManifestPath `
        '-CandidatePath' $CandidatePath '-TransactionContractPath' $TransactionContractPath `
        '-AdapterPolicyPath' $AdapterPolicyPath '-RpcUri' ([string] $RpcUri) `
        '-CredentialPath' $CredentialPath '-StatusPath' $ChildStatusPath | Out-Null
    $exitCode = [int] $LASTEXITCODE
    $status = Read-BoundedJson -Path $ChildStatusPath -MaximumBytes 65536
    if ([int] $status.exitCode -ne $exitCode -or [string] $status.operation -cne $Operation -or
        [string] $status.deploymentId -cne [string] $script:manifest.deploymentId -or
        [string] $status.manifestSha256 -cne $script:manifestSha256 -or
        [string] $status.packageIdentitySha256 -cne $script:packageIdentitySha256) {
        throw [InvalidOperationException]::new('Target adapter result contract is invalid.')
    }
    return $status
}

function Assert-AdapterOutcome {
    param(
        [Parameter(Mandatory = $true)] $Status,
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode
    )
    if ([string] $Status.outcome -cne $Outcome -or [int] $Status.exitCode -ne $ExitCode) {
        throw [InvalidOperationException]::new('Target adapter did not reach its required outcome.')
    }
}

function Invoke-Reseal {
    param([Parameter(Mandatory = $true)][string] $ChildStatusPath)
    if (-not [bool] $script:policy.resealEnabled) {
        throw [InvalidOperationException]::new('Approval plan requests an unavailable reseal profile.')
    }
    if ((Get-Sha256 -Path ([string] $script:policy.resealScriptPath)) -cne
        [string] $script:policy.expectedResealScriptSha256) {
        throw [Security.SecurityException]::new('Reseal source identity differs.')
    }
    $powerShell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    & $powerShell '-NoLogo' '-NoProfile' '-NonInteractive' '-ExecutionPolicy' 'Bypass' `
        '-File' ([string] $script:policy.resealScriptPath) '-Operation' 'apply' `
        '-ChannelPolicyPath' $ChannelPolicyPath `
        '-ExpectedChannelPolicySha256' ([string] $script:plan.expectedBaselineIdentities.channelPolicySha256) `
        '-ExpectedPreviousPackageIdentitySha256' $script:previousPackageIdentitySha256 `
        '-ExpectedActivePackageIdentitySha256' $script:packageIdentitySha256 `
        '-ExpectedActiveDeploymentId' ([string] $script:manifest.deploymentId) `
        '-DeploymentUser' $DeploymentUser '-StatusPath' $ChildStatusPath `
        '-ChannelMutexAlreadyHeld' '-CoordinatorPlanSha256' $script:planSha256 `
        '-ActivationStatusPath' (Join-Path $script:stateDirectory 'activation-status.json') `
        '-Confirmation' 'reseal-saef-owntracks-position-map-active-identity' | Out-Null
    $exitCode = [int] $LASTEXITCODE
    $status = Read-BoundedJson -Path $ChildStatusPath -MaximumBytes 65536
    $currentChannelPolicySha256 = Get-Sha256 -Path $ChannelPolicyPath
    $currentAdapterPolicySha256 = Get-Sha256 -Path $AdapterPolicyPath
    if ([bool] $status.mutationAttempted -and
        ($currentChannelPolicySha256 -cne
            [string] $script:plan.expectedBaselineIdentities.channelPolicySha256 -or
        $currentAdapterPolicySha256 -cne
            [string] $script:plan.expectedBaselineIdentities.adapterPolicySha256)) {
        $script:state['resealMutationCompleted'] = $true
        Write-SignedState
    }
    if ($exitCode -ne 0 -or [int] $status.exitCode -ne 0 -or
        [string] $status.outcome -cne 'resealed' -or -not [bool] $status.mutationAttempted -or
        -not [bool] $status.channelMutexInherited -or
        [string] $status.coordinatorPlanSha256 -cne $script:planSha256) {
        throw [InvalidOperationException]::new('Active-identity reseal did not reach its required outcome.')
    }
    return $status
}

function Restore-ResealPolicies {
    if (-not [bool] $script:state.resealMutationCompleted) {
        return
    }
    $channelBackupPath = Join-Path $script:stateDirectory 'channel-policy.rollback.bin'
    $adapterBackupPath = Join-Path $script:stateDirectory 'adapter-policy.rollback.bin'
    Assert-RootedLeaf -Path $channelBackupPath -MaximumBytes $MaximumJsonBytes
    Assert-RootedLeaf -Path $adapterBackupPath -MaximumBytes $MaximumJsonBytes
    if ((Get-Sha256 -Path $channelBackupPath) -cne
            [string] $script:plan.expectedBaselineIdentities.channelPolicySha256 -or
        (Get-Sha256 -Path $adapterBackupPath) -cne
            [string] $script:plan.expectedBaselineIdentities.adapterPolicySha256) {
        throw [Security.SecurityException]::new('Reseal rollback bytes differ from approved baselines.')
    }
    $channelBytes = [IO.File]::ReadAllBytes($channelBackupPath)
    $adapterBytes = [IO.File]::ReadAllBytes($adapterBackupPath)
    try {
        Write-AtomicBytes -Path $ChannelPolicyPath -Bytes $channelBytes
        Write-AtomicBytes -Path $AdapterPolicyPath -Bytes $adapterBytes
    } finally {
        [Array]::Clear($channelBytes, 0, $channelBytes.Length)
        [Array]::Clear($adapterBytes, 0, $adapterBytes.Length)
    }
    if ((Get-Sha256 -Path $ChannelPolicyPath) -cne
            [string] $script:plan.expectedBaselineIdentities.channelPolicySha256 -or
        (Get-Sha256 -Path $AdapterPolicyPath) -cne
            [string] $script:plan.expectedBaselineIdentities.adapterPolicySha256) {
        throw [InvalidOperationException]::new('Reseal policy rollback could not be proven.')
    }
    $script:state['resealMutationCompleted'] = $false
    Write-SignedState
}

function Invoke-PostSuccessRollback {
    $script:rollbackAttempted = $true
    Start-Phase -Phase 'rollback'
    Restore-ResealPolicies
    $rollbackStatusPath = Join-Path $script:stateDirectory 'rollback-status.json'
    $rollbackStatus = Invoke-Adapter -Operation 'rollback' -ChildStatusPath $rollbackStatusPath
    Assert-AdapterOutcome -Status $rollbackStatus -Outcome 'rolled_back' -ExitCode $ExitRolledBack
    if (-not [bool] $rollbackStatus.rollbackAttempted -or -not [bool] $rollbackStatus.rollbackSucceeded) {
        throw [InvalidOperationException]::new('Target rollback evidence is incomplete.')
    }
    $script:rollbackSucceeded = $true
    Complete-Phase -Phase 'rollback' -EvidenceSha256 (Get-Sha256 -Path $rollbackStatusPath)
}

function Get-ExecutionPhases {
    return @($script:plan.operations | Where-Object { [string] $_ -cne 'rollback' })
}

function Invoke-QualificationPhase {
    $qualification = Read-BoundedJson -Path ([string] $script:policy.qualificationEvidencePath)
    if ((Get-Sha256 -Path ([string] $script:policy.qualificationEvidencePath)) -cne
            [string] $script:policy.expectedQualificationEvidenceSha256 -or
        $qualification.formatVersion -ne 1 -or [string] $qualification.outcome -cne 'passed' -or
        [int] $qualification.exitCode -ne 0 -or [int] $qualification.expectedChannelVersion -ne 8 -or
        [bool] $qualification.productionMutationAttempted -or [bool] $qualification.serviceRestartAttempted -or
        [string] $qualification.runnerSha256 -cne (Get-Sha256 -Path $PSCommandPath) -or
        [string] $qualification.adapterSha256 -cne (Get-Sha256 -Path $AdapterPath) -or
        ([bool] $script:policy.resealEnabled -and
            [string] $qualification.resealSha256 -cne
                (Get-Sha256 -Path ([string] $script:policy.resealScriptPath)))) {
        throw [Security.SecurityException]::new('Windows qualification evidence differs.')
    }
    return Get-Sha256 -Path ([string] $script:policy.qualificationEvidencePath)
}

function Invoke-StageVerificationPhase {
    if ([string] $script:manifest.deploymentKind -cne 'standalone-module' -or
        [string] $script:manifest.deploymentId -cne [string] $script:plan.deploymentId -or
        [string] $script:transfer.packageSha256 -cne [string] $script:plan.package.sha256 -or
        [long] $script:transfer.packageBytes -ne [long] $script:plan.package.bytes) {
        throw [Security.SecurityException]::new('Staged package evidence differs.')
    }
    return Get-Sha256 -Path $PackageTransferPath
}

function Get-CurrentBaseline {
    $adapterPolicy = Read-BoundedJson -Path $AdapterPolicyPath
    return [ordered]@{
        activePackageSha256 = [string] $adapterPolicy.expectedActivePackageIdentitySha256
        adapterPolicySha256 = Get-Sha256 -Path $AdapterPolicyPath
        channelPolicySha256 = Get-Sha256 -Path $ChannelPolicyPath
    }
}

function Assert-ApprovedBaseline {
    param([Parameter(Mandatory = $true)] $Baseline)
    if ((ConvertTo-CanonicalJson -Value ([pscustomobject] $Baseline)) -cne
        (ConvertTo-CanonicalJson -Value $script:plan.expectedBaselineIdentities)) {
        throw [Security.SecurityException]::new('Deployment baseline drifted before activation.')
    }
}

function Write-ResealRollbackBackups {
    $channelBackupPath = Join-Path $script:stateDirectory 'channel-policy.rollback.bin'
    $adapterBackupPath = Join-Path $script:stateDirectory 'adapter-policy.rollback.bin'
    if (-not (Test-Path -LiteralPath $channelBackupPath -PathType Leaf)) {
        $channelBytes = [IO.File]::ReadAllBytes($ChannelPolicyPath)
        try { Write-AtomicBytes -Path $channelBackupPath -Bytes $channelBytes } finally {
            [Array]::Clear($channelBytes, 0, $channelBytes.Length)
        }
    }
    if (-not (Test-Path -LiteralPath $adapterBackupPath -PathType Leaf)) {
        $adapterBytes = [IO.File]::ReadAllBytes($AdapterPolicyPath)
        try { Write-AtomicBytes -Path $adapterBackupPath -Bytes $adapterBytes } finally {
            [Array]::Clear($adapterBytes, 0, $adapterBytes.Length)
        }
    }
    if ((Get-Sha256 -Path $channelBackupPath) -cne
            [string] $script:plan.expectedBaselineIdentities.channelPolicySha256 -or
        (Get-Sha256 -Path $adapterBackupPath) -cne
            [string] $script:plan.expectedBaselineIdentities.adapterPolicySha256) {
        throw [InvalidOperationException]::new('Reseal rollback backup differs.')
    }
}

function Invoke-ApprovalPhase {
    param([Parameter(Mandatory = $true)][string] $Phase)
    switch ($Phase) {
        'qualify' {
            $script:failureCode = 'qualification'
            return Invoke-QualificationPhase
        }
        'stage' {
            $script:failureCode = 'stage'
            return Invoke-StageVerificationPhase
        }
        'preflight' {
            $script:failureCode = 'fresh_preflight'
            $baseline = Get-CurrentBaseline
            Assert-ApprovedBaseline -Baseline $baseline
            $script:previousPackageIdentitySha256 = [string] $baseline.activePackageSha256
            $path = Join-Path $script:stateDirectory 'preflight-status.json'
            $status = Invoke-Adapter -Operation 'preflight' -ChildStatusPath $path
            Assert-AdapterOutcome -Status $status -Outcome 'passed' -ExitCode 0
            return Get-Sha256 -Path $path
        }
        'activate' {
            $script:failureCode = 'activation'
            $script:activationAttempted = $true
            $script:mutationAttempted = $true
            $path = Join-Path $script:stateDirectory 'activation-status.json'
            $status = Invoke-Adapter -Operation 'activate' -ChildStatusPath $path
            if ([string] $status.outcome -ceq 'activated' -and [int] $status.exitCode -eq 0) {
                $script:state['activationCompleted'] = $true
                Write-SignedState
                return Get-Sha256 -Path $path
            }
            if ([string] $status.outcome -ceq 'rolled_back' -and
                [int] $status.exitCode -eq $ExitRolledBack -and
                [bool] $status.rollbackAttempted -and [bool] $status.rollbackSucceeded) {
                $script:rollbackAttempted = $true
                $script:rollbackSucceeded = $true
                Set-TerminalState -Outcome 'rolled_back' -ExitCode $ExitRolledBack
                return ''
            }
            $script:state['phase'] = 'manual_recovery'
            Set-TerminalState -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                -PhaseState 'uncertain'
            return ''
        }
        'postflight' {
            $script:failureCode = 'postflight'
            $path = Join-Path $script:stateDirectory 'postflight-status.json'
            $status = Invoke-Adapter -Operation 'postflight' -ChildStatusPath $path
            Assert-AdapterOutcome -Status $status -Outcome 'passed' -ExitCode 0
            return Get-Sha256 -Path $path
        }
        'reseal' {
            $script:failureCode = 'reseal_backup'
            Write-ResealRollbackBackups
            $script:failureCode = 'reseal'
            $path = Join-Path $script:stateDirectory 'reseal-status.json'
            $null = Invoke-Reseal -ChildStatusPath $path
            $script:state['resealMutationCompleted'] = $true
            Write-SignedState
            return Get-Sha256 -Path $path
        }
        'final_postflight' {
            $script:failureCode = 'final_postflight'
            $path = Join-Path $script:stateDirectory 'final-postflight-status.json'
            $status = Invoke-Adapter -Operation 'postflight' -ChildStatusPath $path
            Assert-AdapterOutcome -Status $status -Outcome 'passed' -ExitCode 0
            return Get-Sha256 -Path $path
        }
        default {
            throw [InvalidOperationException]::new('Approval phase is unsupported.')
        }
    }
}

function Reconcile-InterruptedPhase {
    $phases = @(Get-ExecutionPhases)
    $completed = [int] $script:state.completedPhaseCount
    if ($completed -ge $phases.Count -or [string] $script:state.phase -cne [string] $phases[$completed]) {
        throw [InvalidOperationException]::new('Interrupted approval phase order is invalid.')
    }
    $interruptedPhase = [string] $script:state.phase
    if ($interruptedPhase -in @('qualify', 'stage', 'preflight', 'postflight', 'final_postflight')) {
        return
    }
    if ($interruptedPhase -eq 'activate') {
        $inspectPath = Join-Path $script:stateDirectory 'resume-inspect-status.json'
        $inspection = Invoke-Adapter -Operation 'inspect' -ChildStatusPath $inspectPath
        if ([string] $inspection.outcome -ceq 'active') {
            $script:state['activationCompleted'] = $true
            Complete-Phase -Phase 'activate' -EvidenceSha256 (Get-Sha256 -Path $inspectPath)
            return
        }
        if ([string] $inspection.outcome -ceq 'rolled_back') {
            $script:rollbackAttempted = $true
            $script:rollbackSucceeded = $true
            Set-TerminalState -Outcome 'rolled_back' -ExitCode $ExitRolledBack
            return
        }
        if ([string] $inspection.outcome -ceq 'not_applied') {
            return
        }
        throw [InvalidOperationException]::new('Interrupted activation is uncertain.')
    }
    if ($interruptedPhase -eq 'reseal') {
        $resealStatusPath = Join-Path $script:stateDirectory 'reseal-status.json'
        if (Test-Path -LiteralPath $resealStatusPath -PathType Leaf) {
            $resealStatus = Read-BoundedJson -Path $resealStatusPath -MaximumBytes 65536
            if ([string] $resealStatus.outcome -ceq 'resealed' -and
                (Get-Sha256 -Path $ChannelPolicyPath) -ceq
                    [string] $resealStatus.proposedChannelPolicySha256 -and
                (Get-Sha256 -Path $AdapterPolicyPath) -ceq
                    [string] $resealStatus.proposedAdapterPolicySha256) {
                $script:state['resealMutationCompleted'] = $true
                Complete-Phase -Phase 'reseal' -EvidenceSha256 (Get-Sha256 -Path $resealStatusPath)
                return
            }
        }
        $baseline = Get-CurrentBaseline
        try {
            Assert-ApprovedBaseline -Baseline $baseline
            return
        } catch {
            $script:state['resealMutationCompleted'] = $true
            Write-SignedState
            throw [InvalidOperationException]::new('Interrupted reseal is uncertain.')
        }
    }
    throw [InvalidOperationException]::new('Interrupted rollback requires manual recovery.')
}

function Write-RunnerStatus {
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        operation = 'activate'
        deploymentId = if ($null -ne $script:manifest) { [string] $script:manifest.deploymentId } else { '' }
        manifestSha256 = $script:manifestSha256
        packageIdentitySha256 = $script:packageIdentitySha256
        previousPackageIdentitySha256 = $script:previousPackageIdentitySha256
        approvalPlanSha256 = $script:planSha256
        runnerProfile = $RunnerProfile
        channelPolicySha256 = if (Test-Path -LiteralPath $ChannelPolicyPath -PathType Leaf) {
            Get-Sha256 -Path $ChannelPolicyPath
        } else { '' }
        adapterPolicySha256 = if (Test-Path -LiteralPath $AdapterPolicyPath -PathType Leaf) {
            Get-Sha256 -Path $AdapterPolicyPath
        } else { '' }
        outcome = $script:finalOutcome
        exitCode = $script:finalExitCode
        activationAttempted = [bool] $script:activationAttempted
        mutationAttempted = [bool] $script:mutationAttempted
        rollbackAttempted = [bool] $script:rollbackAttempted
        rollbackSucceeded = [bool] $script:rollbackSucceeded
        failureCode = $script:failureCode
    }
    Write-AtomicJson -Path $StatusPath -Value $status
}

try {
    $script:failureCode = 'input'
    foreach ($path in @(
        $ChannelPolicyPath, $ManifestPath, $TransactionContractPath, $PackageTransferPath,
        $AdapterPath, $AdapterPolicyPath, $ApprovalPolicyPath, $CredentialPath, $DeploymentStatusPath
    )) {
        Assert-RootedLeaf -Path $path -MaximumBytes 4194304
    }
    Assert-PlainDirectory -Path $CandidatePath
    Assert-PlainDirectory -Path (Split-Path -Parent $StatusPath)
    foreach ($path in @(
        $PSCommandPath, $ChannelPolicyPath, $AdapterPath, $AdapterPolicyPath,
        $ApprovalPolicyPath, $CredentialPath
    )) {
        Assert-ProtectedPathAcl -Path $path
    }
    $script:manifest = Read-BoundedJson -Path $ManifestPath
    $script:manifestSha256 = Get-Sha256 -Path $ManifestPath
    $script:packageIdentitySha256 = [string] $script:manifest.module.packageIdentitySha256
    $script:policy = Read-BoundedJson -Path $ApprovalPolicyPath
    $script:transfer = Read-BoundedJson -Path $PackageTransferPath -MaximumBytes 4096
    $envelope = ConvertFrom-Base64UrlJson -Value $ApprovalEnvelopeBase64Url
    Assert-ExactProperties -Value $envelope -Names @('formatVersion', 'plan', 'approval') `
        -Label 'Approval envelope'
    if ($envelope.formatVersion -ne 1) {
        throw [InvalidOperationException]::new('Approval envelope version is unsupported.')
    }
    $script:plan = $envelope.plan
    $script:approval = $envelope.approval

    $script:failureCode = 'policy'
    Assert-ExactProperties -Value $script:policy -Names @(
        'formatVersion', 'runnerProfile', 'targetId', 'adapterProfile', 'qualificationProfile',
        'postflightProfile', 'approvalStateRoot', 'approvalSecretPath', 'qualificationEvidencePath',
        'expectedQualificationEvidenceSha256', 'channelHostBindingSha256',
        'approverIdentitySha256', 'executionHostIdentitySha256', 'resealEnabled',
        'resealScriptPath', 'expectedResealScriptSha256', 'maximumStateFiles'
    ) -Label 'Approval runner policy'
    if ($script:policy.formatVersion -ne 1 -or
        [string] $script:policy.runnerProfile -cne $RunnerProfile -or
        [string] $script:policy.targetId -cne [string] $script:manifest.module.targetId -or
        [string] $script:policy.adapterProfile -cne [string] $script:plan.adapterProfile -or
        [int] $script:policy.maximumStateFiles -lt 1 -or
        [int] $script:policy.maximumStateFiles -gt 4096 -or
        -not (Test-HexSha256 -Value ([string] $script:policy.expectedQualificationEvidenceSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.channelHostBindingSha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.approverIdentitySha256)) -or
        -not (Test-HexSha256 -Value ([string] $script:policy.executionHostIdentitySha256))) {
        throw [InvalidOperationException]::new('Approval runner policy identity differs.')
    }
    Assert-PlainDirectory -Path ([string] $script:policy.approvalStateRoot)
    Assert-ProtectedPathAcl -Path ([string] $script:policy.approvalStateRoot)
    Assert-RootedLeaf -Path ([string] $script:policy.approvalSecretPath) -MaximumBytes 4096
    Assert-ProtectedPathAcl -Path ([string] $script:policy.approvalSecretPath)
    $secretRecord = Read-BoundedJson -Path ([string] $script:policy.approvalSecretPath) -MaximumBytes 4096
    Assert-ExactProperties -Value $secretRecord -Names @('formatVersion', 'encoding', 'secretBase64') `
        -Label 'Approval secret record'
    if ($secretRecord.formatVersion -ne 1 -or [string] $secretRecord.encoding -cne 'base64') {
        throw [Security.SecurityException]::new('Approval secret record is invalid.')
    }
    $script:secretBytes = [Convert]::FromBase64String([string] $secretRecord.secretBase64)
    if ($script:secretBytes.Length -lt 32 -or $script:secretBytes.Length -gt 64) {
        throw [Security.SecurityException]::new('Approval secret length is invalid.')
    }
    Assert-RootedLeaf -Path ([string] $script:policy.qualificationEvidencePath) `
        -MaximumBytes $MaximumJsonBytes
    Assert-ProtectedPathAcl -Path ([string] $script:policy.qualificationEvidencePath)
    if ([bool] $script:policy.resealEnabled) {
        Assert-RootedLeaf -Path ([string] $script:policy.resealScriptPath) -MaximumBytes 4194304
        Assert-ProtectedPathAcl -Path ([string] $script:policy.resealScriptPath)
        if (-not (Test-HexSha256 -Value ([string] $script:policy.expectedResealScriptSha256))) {
            throw [Security.SecurityException]::new('Approval reseal source identity is invalid.')
        }
    } elseif (-not [string]::IsNullOrEmpty([string] $script:policy.resealScriptPath) -or
        -not [string]::IsNullOrEmpty([string] $script:policy.expectedResealScriptSha256)) {
        throw [Security.SecurityException]::new('Disabled approval reseal profile contains authority.')
    }

    $script:failureCode = 'plan'
    Assert-ExactProperties -Value $script:plan -Names @(
        'formatVersion', 'channelVersion', 'deploymentId', 'targetId', 'adapterProfile',
        'qualificationProfile', 'postflightProfile', 'package', 'operations',
        'expectedBaselineIdentities', 'channelHostBindingSha256', 'riskScope'
    ) -Label 'Approval plan'
    if ($script:plan.formatVersion -ne 1 -or $script:plan.channelVersion -ne 8 -or
        [string] $script:plan.deploymentId -notmatch '^saef-[a-z0-9][a-z0-9.-]{0,63}$' -or
        [string] $script:plan.targetId -notmatch '^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$' -or
        [string] $script:plan.deploymentId -cne [string] $script:manifest.deploymentId -or
        [string] $script:plan.targetId -cne [string] $script:policy.targetId -or
        [string] $script:plan.adapterProfile -cne [string] $script:policy.adapterProfile -or
        [string] $script:plan.qualificationProfile -cne [string] $script:policy.qualificationProfile -or
        [string] $script:plan.postflightProfile -cne [string] $script:policy.postflightProfile -or
        [string] $script:plan.channelHostBindingSha256 -cne [string] $script:policy.channelHostBindingSha256) {
        throw [Security.SecurityException]::new('Approval plan binding differs.')
    }
    $baseOperations = @('qualify', 'stage', 'preflight', 'activate', 'postflight', 'rollback')
    $resealOperations = @(
        'qualify', 'stage', 'preflight', 'activate', 'postflight', 'reseal', 'final_postflight', 'rollback'
    )
    $operations = @($script:plan.operations)
    $expectedOperations = if ([bool] $script:policy.resealEnabled) { $resealOperations } else { $baseOperations }
    if (($operations -join [char] 0) -cne ($expectedOperations -join [char] 0)) {
        throw [Security.SecurityException]::new('Approval operation sequence differs.')
    }
    Assert-ExactProperties -Value $script:plan.package -Names @('sha256', 'bytes') `
        -Label 'Approval package'
    if (-not (Test-HexSha256 -Value ([string] $script:plan.package.sha256)) -or
        [long] $script:plan.package.bytes -lt 1 -or [long] $script:plan.package.bytes -gt 67108864) {
        throw [Security.SecurityException]::new('Approval package identity is invalid.')
    }
    Assert-ExactProperties -Value $script:plan.riskScope -Names @(
        'allowlistChange', 'serviceRestart', 'providerContact', 'publication',
        'retentionDeletion', 'activeIdentityReseal'
    ) -Label 'Approval risk scope'
    foreach ($name in @('allowlistChange', 'serviceRestart', 'providerContact', 'publication', 'retentionDeletion')) {
        if ($script:plan.riskScope.$name -isnot [bool] -or [bool] $script:plan.riskScope.$name) {
            throw [Security.SecurityException]::new('Approval plan contains a forbidden risk scope.')
        }
    }
    if ($script:plan.riskScope.activeIdentityReseal -isnot [bool] -or
        [bool] $script:plan.riskScope.activeIdentityReseal -ne [bool] $script:policy.resealEnabled) {
        throw [Security.SecurityException]::new('Approval reseal scope differs.')
    }
    Assert-ExactProperties -Value $script:plan.expectedBaselineIdentities -Names @(
        'activePackageSha256', 'adapterPolicySha256', 'channelPolicySha256'
    ) -Label 'Approval baseline'
    foreach ($name in @('activePackageSha256', 'adapterPolicySha256', 'channelPolicySha256')) {
        if (-not (Test-HexSha256 -Value ([string] $script:plan.expectedBaselineIdentities.$name))) {
            throw [Security.SecurityException]::new('Approval baseline identity is invalid.')
        }
    }
    if ([string] $script:plan.package.sha256 -cne [string] $script:transfer.packageSha256 -or
        [long] $script:plan.package.bytes -ne [long] $script:transfer.packageBytes) {
        throw [Security.SecurityException]::new('Approval package transfer binding differs.')
    }
    Assert-ExactProperties -Value $script:transfer -Names @('formatVersion', 'packageSha256', 'packageBytes') `
        -Label 'Package transfer identity'
    if ($script:transfer.formatVersion -ne 1) {
        throw [Security.SecurityException]::new('Package transfer identity version is unsupported.')
    }

    $script:failureCode = 'approval'
    Assert-ExactProperties -Value $script:approval -Names @(
        'formatVersion', 'algorithm', 'planSha256', 'targetId', 'adapterProfile',
        'allowedOperations', 'expectedBaselineIdentities', 'channelHostBindingSha256',
        'approverIdentitySha256', 'executionHostIdentitySha256', 'issuedAt', 'expiresAt',
        'nonce', 'signature'
    ) -Label 'Approval proof'
    $script:planSha256 = Get-TextSha256 -Text (ConvertTo-CanonicalJson -Value $script:plan)
    $unsignedApproval = [ordered]@{}
    foreach ($name in @($script:approval.PSObject.Properties.Name | Where-Object { $_ -ne 'signature' })) {
        $unsignedApproval[$name] = $script:approval.$name
    }
    $expectedSignature = Get-HmacSha256 `
        -Text (ConvertTo-CanonicalJson -Value ([pscustomobject] $unsignedApproval)) `
        -Secret $script:secretBytes
    $now = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    if ($script:approval.formatVersion -ne 1 -or
        [string] $script:approval.algorithm -cne 'hmac-sha256' -or
        -not (Test-HexSha256 -Value ([string] $script:approval.signature)) -or
        -not (Test-FixedTimeTextEquals -Left ([string] $script:approval.signature) `
            -Right $expectedSignature) -or
        [string] $script:approval.planSha256 -cne $script:planSha256 -or
        [string] $script:approval.targetId -cne [string] $script:plan.targetId -or
        [string] $script:approval.adapterProfile -cne [string] $script:plan.adapterProfile -or
        [string] $script:approval.channelHostBindingSha256 -cne [string] $script:plan.channelHostBindingSha256 -or
        [string] $script:approval.approverIdentitySha256 -cne [string] $script:policy.approverIdentitySha256 -or
        [string] $script:approval.executionHostIdentitySha256 -cne
            [string] $script:policy.executionHostIdentitySha256 -or
        [long] $script:approval.issuedAt -gt $now + 60 -or [long] $script:approval.expiresAt -lt $now -or
        [long] $script:approval.expiresAt - [long] $script:approval.issuedAt -lt 1 -or
        [long] $script:approval.expiresAt - [long] $script:approval.issuedAt -gt
            $MaximumApprovalLifetimeSeconds -or
        -not (Test-HexSha256 -Value ([string] $script:approval.nonce)) -or
        (ConvertTo-CanonicalJson -Value $script:approval.allowedOperations) -cne
            (ConvertTo-CanonicalJson -Value $script:plan.operations) -or
        (ConvertTo-CanonicalJson -Value $script:approval.expectedBaselineIdentities) -cne
            (ConvertTo-CanonicalJson -Value $script:plan.expectedBaselineIdentities)) {
        throw [Security.SecurityException]::new('Approval proof validation failed.')
    }

    $script:failureCode = 'claim_lock'
    $script:mutex = [Threading.Mutex]::new($false, 'Global\SAEF.DeploymentApproval')
    try {
        $script:mutexAcquired = $script:mutex.WaitOne(0)
    } catch [Threading.AbandonedMutexException] {
        $script:mutexAcquired = $true
    }
    if (-not $script:mutexAcquired) {
        throw [InvalidOperationException]::new('Another approved deployment is active.')
    }

    $script:failureCode = 'claim'
    $script:statePath = Join-Path ([string] $script:policy.approvalStateRoot) ($script:planSha256 + '.json')
    $script:stateDirectory = Join-Path ([string] $script:policy.approvalStateRoot) $script:planSha256
    $nonceSha256 = Get-TextSha256 -Text ([string] $script:approval.nonce)
    if (Test-Path -LiteralPath $script:statePath -PathType Leaf) {
        $script:state = Read-SignedState
        if ([string] $script:state.planSha256 -cne $script:planSha256 -or
            [string] $script:state.targetId -cne [string] $script:plan.targetId -or
            [string] $script:state.adapterProfile -cne [string] $script:plan.adapterProfile -or
            [string] $script:state.nonceSha256 -cne $nonceSha256 -or
            [long] $script:state.approvalExpiresAt -ne [long] $script:approval.expiresAt) {
            throw [Security.SecurityException]::new('Approval plan was claimed by another proof.')
        }
        if ([string] $script:state.outcome -cne 'running') {
            throw [Security.SecurityException]::new('Approval proof has already reached a terminal outcome.')
        }
        Assert-PlainDirectory -Path $script:stateDirectory
        Assert-ProtectedPathAcl -Path $script:stateDirectory
        $script:stateClaimed = $true
    } else {
        $stateFiles = @(Get-ChildItem -LiteralPath ([string] $script:policy.approvalStateRoot) `
            -File -Filter '*.json' -Force)
        if ($stateFiles.Count -ge [int] $script:policy.maximumStateFiles) {
            throw [InvalidOperationException]::new('Approval state capacity is exhausted.')
        }
        if (Test-Path -LiteralPath $script:stateDirectory) {
            throw [InvalidOperationException]::new('Approval state directory exists without a claim.')
        }
        [IO.Directory]::CreateDirectory($script:stateDirectory) | Out-Null
        Assert-ProtectedPathAcl -Path $script:stateDirectory
        $script:state = [ordered]@{
            formatVersion = 1
            planSha256 = $script:planSha256
            targetId = [string] $script:plan.targetId
            adapterProfile = [string] $script:plan.adapterProfile
            nonceSha256 = $nonceSha256
            approvalExpiresAt = [long] $script:approval.expiresAt
            phase = 'claimed'
            phaseState = 'completed'
            completedPhaseCount = 0
            activationCompleted = $false
            resealMutationCompleted = $false
            outcome = 'running'
            evidence = [pscustomobject]@{}
            createdUtc = [DateTime]::UtcNow.ToString('o')
            updatedUtc = [DateTime]::UtcNow.ToString('o')
        }
        Write-SignedState
        $script:stateClaimed = $true
    }
    if ([string] $script:state.phaseState -ceq 'started' -and
        [string] $script:state.phase -ceq 'rollback') {
        $script:state['phase'] = 'manual_recovery'
        Set-TerminalState -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
            -PhaseState 'uncertain'
    }
    if (-not $script:terminalReached) {
        Assert-RunningStatePhaseOrder
    }
    $script:previousPackageIdentitySha256 =
        [string] $script:plan.expectedBaselineIdentities.activePackageSha256

    if (-not $script:terminalReached -and [string] $script:state.phaseState -eq 'started') {
        $script:failureCode = 'resume_inspection'
        Reconcile-InterruptedPhase
    }

    if (-not $script:terminalReached) {
        $phases = @(Get-ExecutionPhases)
        $startIndex = [int] $script:state.completedPhaseCount
        if ($startIndex -gt $phases.Count) {
            throw [InvalidOperationException]::new('Approval phase count is invalid.')
        }
        for ($index = $startIndex; $index -lt $phases.Count; $index++) {
            $phase = [string] $phases[$index]
            Start-Phase -Phase $phase
            $evidenceSha256 = Invoke-ApprovalPhase -Phase $phase
            if ($script:terminalReached) {
                break
            }
            Complete-Phase -Phase $phase -EvidenceSha256 $evidenceSha256
        }
    }

    if (-not $script:terminalReached) {
        $script:failureCode = 'none'
        $script:state['phase'] = 'complete'
        Set-TerminalState -Outcome 'completed' -ExitCode $ExitSuccess
        $script:finalOutcome = 'activated'
    }
} catch {
    if ($script:stateClaimed -and $script:activationAttempted -and
        -not [bool] $script:state.activationCompleted) {
        try {
            $inspectPath = Join-Path $script:stateDirectory 'failure-inspect-status.json'
            $inspection = Invoke-Adapter -Operation 'inspect' -ChildStatusPath $inspectPath
            if ([string] $inspection.outcome -ceq 'active') {
                $script:state['activationCompleted'] = $true
                Write-SignedState
            } elseif ([string] $inspection.outcome -ceq 'rolled_back') {
                $script:rollbackAttempted = $true
                $script:rollbackSucceeded = $true
                Set-TerminalState -Outcome 'rolled_back' -ExitCode $ExitRolledBack
            } elseif ([string] $inspection.outcome -ceq 'not_applied') {
                $script:activationAttempted = $false
                $script:mutationAttempted = $false
                $script:state['phase'] = 'activate'
                $script:state['phaseState'] = 'started'
                $script:state['updatedUtc'] = [DateTime]::UtcNow.ToString('o')
                Write-SignedState
                $script:finalOutcome = 'aborted'
                $script:finalExitCode = $ExitPreflightFailed
                $script:terminalReached = $true
            } else {
                $script:state['phase'] = 'manual_recovery'
                Set-TerminalState -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                    -PhaseState 'uncertain'
            }
        } catch {
            $script:state['phase'] = 'manual_recovery'
            Set-TerminalState -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                -PhaseState 'uncertain'
        }
    }
    if (-not $script:terminalReached -and $script:stateClaimed -and
        [bool] $script:state.activationCompleted) {
        try {
            Invoke-PostSuccessRollback
            Set-TerminalState -Outcome 'rolled_back' -ExitCode $ExitRolledBack
        } catch {
            $script:state['phase'] = 'manual_recovery'
            Set-TerminalState -Outcome 'manual_recovery_required' -ExitCode $ExitManualRecovery `
                -PhaseState 'uncertain'
        }
    } elseif (-not $script:terminalReached) {
        if ($script:stateClaimed) {
            $script:state['phase'] = 'aborted'
            $script:state['phaseState'] = 'failed'
            $script:state['outcome'] = 'aborted'
            $script:state['updatedUtc'] = [DateTime]::UtcNow.ToString('o')
            try { Write-SignedState } catch { }
        }
        $script:finalOutcome = 'aborted'
        $script:finalExitCode = $ExitPreflightFailed
    }
} finally {
    try { Write-RunnerStatus } catch {
        if ($script:finalExitCode -eq $ExitSuccess) {
            $script:finalExitCode = $ExitManualRecovery
        }
    }
    if ($script:mutexAcquired -and $null -ne $script:mutex) {
        try { $script:mutex.ReleaseMutex() } catch { }
    }
    if ($null -ne $script:mutex) {
        $script:mutex.Dispose()
    }
    if ($null -ne $script:secretBytes) {
        [Array]::Clear($script:secretBytes, 0, $script:secretBytes.Length)
    }
}

exit $script:finalExitCode
