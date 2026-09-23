[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $PlanPath,
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-f0-9]{64}$')][string] $ExpectedPlanSha256,
    [Parameter(Mandatory = $true)][ValidateSet('preflight', 'install')][string] $Operation,
    [Parameter()][string] $Confirmation = ''
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$result = [ordered]@{ formatVersion = 1; operation = $Operation; outcome = 'failed'
    stage = 'inputs'; stateProvisioningAttempted = $false; targetInstallationAttempted = $false
    moduleActivationAttempted = $false; symconRpcContactAttempted = $false
    serviceRestartAttempted = $false; stateRootRetained = $null; exitCode = 10 }
$evidence = $null

# Bootstrap reads only bound data/functions; the existing shared validator then
# supplies path/ACL/JSON checks. No input selects a script or function to execute.
function Read-BoundSource { param([string] $Path, [string] $Hash)
    if ($Hash -cnotmatch '^[a-f0-9]{64}$' -or -not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path -Force).Length -gt 4194304) {
        throw 'Missing, oversized or changed installation input.'
    }
    $bytes = [IO.File]::ReadAllBytes($Path)
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        if ($bytes.Length -gt 4194304 -or
            ([BitConverter]::ToString($algorithm.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant() -cne $Hash) {
            throw 'Installation input hash differs.'
        }
        return [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
    } finally { $algorithm.Dispose() }
}
function Invoke-InstallationChild {
    param([string] $Path, [string] $Hash, [string[]] $Arguments, [string] $Status)
    Assert-AdditionProtectedPath $Path
    $child = Invoke-SaefPowerShellChildProcess -ScriptPath $Path -ExpectedScriptSha256 $Hash `
        -Arguments $Arguments -TimeoutSeconds 120 -MaximumOutputBytes 131072
    if ($child.terminationReason -cne 'exited' -or $child.exitCode -ne 0) {
        throw ('Installation child failed; inspect retained status. Exit: ' + $child.exitCode)
    }
    Assert-AdditionProtectedPath $Status
    if ((Get-Item -LiteralPath $Status).Length -gt 65536) { throw 'Child status exceeds limit.' }
    $record = ConvertFrom-AdditionJson ([IO.File]::ReadAllBytes($Status))
    if ($record.formatVersion -ne 1 -or $record.exitCode -ne 0) { throw 'Child status failed.' }
    return $record
}
try {
    if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
        throw 'Windows PowerShell 5.1 required.'
    }
    if ($Operation -ceq 'install' -and $Confirmation -cne 'install-saef-media-carousel-target') {
        throw 'Exact target-installation confirmation required.'
    }
    $planText = Read-BoundSource $PlanPath $ExpectedPlanSha256
    $plan = $planText | ConvertFrom-Json
    $windows = Split-Path -Parent $PSScriptRoot
    $package = Split-Path -Parent ([IO.Path]::GetFullPath($PlanPath))
    $initializer = Join-Path $windows 'Initialize-SaefDeploymentChannel.ps1'
    $stateInitializer = Join-Path $PSScriptRoot 'Initialize-SaefOwnTracksPositionMapAdapterState.ps1'
    $adapter = Join-Path $PSScriptRoot 'Invoke-SaefMediaCarouselModuleAdapter.ps1'
    $launcher = Join-Path $windows 'SaefChildProcess.ps1'
    $policyPath = Join-Path $package 'media-carousel-adapter-policy.local.json'
    $fixedSources = [ordered]@{
        channel = $initializer; state = $stateInitializer; adapter = $adapter; launcher = $launcher
        checksums = (Join-Path $windows 'SHA256SUMS')
    }
    $boundTexts = @{}
    foreach ($key in $fixedSources.Keys) {
        $boundTexts[$key] = Read-BoundSource $fixedSources[$key] ([string] $plan.sourceHashes.$key)
    }
    # Import only named pure/reviewed functions from exact bound source bytes.
    # Never dot-source the entry points: their top-level code performs operations.
    $imports = @(
        @{ key = 'channel'; names = @('Get-BytesSha256', 'Assert-AdditionPlainPath',
            'Assert-AdditionProtectedPath', 'Read-AdditionBoundBytes', 'ConvertFrom-AdditionJson',
            'Assert-AdditionBindings', 'Assert-Elevated') },
        @{ key = 'adapter'; names = @('Get-Sha256', 'Get-TextSha256', 'Assert-SafeDirectoryTree',
            'Assert-ModuleTreeIdentity', 'Get-DirectoryPackageIdentity') }
    )
    foreach ($import in $imports) {
        $tokens = $null; $errors = $null
        $ast = [Management.Automation.Language.Parser]::ParseInput($boundTexts[$import.key], [ref] $tokens, [ref] $errors)
        if (@($errors).Count) { throw 'Bound source has syntax errors.' }
        foreach ($name in $import.names) {
            $matches = @($ast.FindAll({ param($n)
                $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq $name
            }, $false))
            if ($matches.Count -ne 1) { throw 'Required installation function is missing or ambiguous.' }
            . ([scriptblock]::Create($matches[0].Extent.Text))
        }
    }
    $plan = ConvertFrom-AdditionJson ([Text.UTF8Encoding]::new($false).GetBytes($planText))
    if ($plan.formatVersion -ne 1 -or $plan.targetId -cne 'saef-media-carousel' -or
        [string] $plan.deploymentUser -cnotmatch '^[A-Za-z0-9_.-]{1,64}$') { throw 'Invalid installation plan.' }
    $account = Get-LocalUser -Name $plan.deploymentUser -ErrorAction Stop
    if ($account.SID.Value -cne [string] $plan.expectedDeploymentSid) { throw 'Deployment account identity changed.' }
    $additionDeploymentSid = $account.SID.Value
    foreach ($path in @($package, $PlanPath, $PSScriptRoot, $windows, $policyPath) + @($fixedSources.Values)) {
        Assert-AdditionProtectedPath $path
    }
    Assert-Elevated | Out-Null
    # Must remain at script scope for Windows PowerShell 5.1.
    . $launcher
    $script:policy = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $policyPath $plan.adapterPolicySha256)
    if ($script:policy.adapterProfile -cne 'saef-media-carousel-v1' -or
        $script:policy.targetId -cne 'saef-media-carousel' -or
        [long] $script:policy.maximumCandidateBytes -lt 1 -or
        [long] $script:policy.maximumCandidateBytes -gt 268435456) { throw 'Invalid target policy.' }
    $InstallRoot = [string] $plan.installRoot
    $channelPath = Join-Path $InstallRoot 'deployment-channel.local.json'
    Assert-AdditionProtectedPath $channelPath
    $channelBefore = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $channelPath $plan.channelPolicySha256)
    Assert-AdditionBindings $channelBefore.standaloneModuleTargets
    Assert-AdditionProtectedPath $script:policy.activeModulePath
    Assert-ModuleTreeIdentity $script:policy.activeModulePath
    if ((Get-DirectoryPackageIdentity $script:policy.activeModulePath) -cne
        $script:policy.expectedActivePackageIdentitySha256) { throw 'Active package differs from reviewed identity.' }
    $result.stage = 'preflight'
    $evidence = Join-Path $package ('installation-' + [Guid]::NewGuid().ToString('N'))
    $null = [IO.Directory]::CreateDirectory($evidence)
    Assert-AdditionProtectedPath $evidence
    $result.evidenceRoot = $evidence
    $manifest = Join-Path $evidence 'targets.local.json'
    $target = [ordered]@{ targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'
        libraryGuid = $script:policy.libraryGuid; adapterPath = $adapter
        expectedAdapterSha256 = $plan.sourceHashes.adapter; adapterPolicyPath = $policyPath
        expectedAdapterPolicySha256 = $plan.adapterPolicySha256 }
    [IO.File]::WriteAllText($manifest, (@{ formatVersion = 1; targets = @($target) } |
        ConvertTo-Json -Depth 6), [Text.UTF8Encoding]::new($false))
    $stateStatus = Join-Path $evidence 'state-preflight-status.local.json'
    $stateArgs = @('-AdapterProfile', 'saef-media-carousel-v1', '-AdapterPolicyPath', $policyPath,
        '-ExpectedAdapterPolicySha256', [string] $plan.adapterPolicySha256,
        '-DeploymentUser', [string] $plan.deploymentUser)
    $stateBefore = Invoke-InstallationChild $stateInitializer $plan.sourceHashes.state `
        ($stateArgs + @('-Operation', 'preflight', '-StatusPath', $stateStatus)) $stateStatus
    if ($stateBefore.outcome -cnotin @('ready', 'already_present')) { throw 'State preflight rejected.' }
    $channelStatus = Join-Path $evidence 'channel-preflight-status.local.json'
    $channelArgs = @('-AddStandaloneModuleTarget', '-DeploymentUser', [string] $plan.deploymentUser,
        '-InstallRoot', $InstallRoot, '-StandaloneModuleTargetsPath', $manifest,
        '-ExpectedChannelPolicySha256', [string] $plan.channelPolicySha256,
        '-ExpectedTargetManifestSha256', (Get-Sha256 $manifest))
    $channelPreflight = Invoke-InstallationChild $initializer $plan.sourceHashes.channel `
        ($channelArgs + @('-PreflightOnly', '-StatusPath', $channelStatus)) $channelStatus
    if ($channelPreflight.outcome -cne 'passed') { throw 'Channel preflight rejected.' }
    $result.additionPlanSha256 = $channelPreflight.additionPlanSha256
    if ($Operation -ceq 'install') {
        $result.stage = 'state_provisioning'
        $result.stateProvisioningAttempted = $true
        $stateStatus = Join-Path $evidence 'state-install-status.local.json'
        $stateAfter = Invoke-InstallationChild $stateInitializer $plan.sourceHashes.state `
            ($stateArgs + @('-Operation', 'install', '-Confirmation', 'provision-saef-media-carousel-adapter-state',
                '-StatusPath', $stateStatus)) $stateStatus
        if ($stateAfter.outcome -cnotin @('installed', 'already_present')) { throw 'State installation rejected.' }
        $result.stateRootRetained = $true
        $result.stage = 'target_installation'
        # Repeat runtime identity check immediately before target registration.
        if ((Get-DirectoryPackageIdentity $script:policy.activeModulePath) -cne
            $script:policy.expectedActivePackageIdentitySha256) { throw 'Runtime package changed during preflight.' }
        $result.targetInstallationAttempted = $true
        $channelStatus = Join-Path $evidence 'channel-install-status.local.json'
        $channelAfter = Invoke-InstallationChild $initializer $plan.sourceHashes.channel `
            ($channelArgs + @('-ExpectedAdditionPlanSha256', [string] $channelPreflight.additionPlanSha256,
                '-StatusPath', $channelStatus)) $channelStatus
        if ($channelAfter.outcome -cne 'installed') { throw 'Target installation rejected.' }
        $result.stage = 'postflight'
        $after = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $channelPath $channelAfter.channelPolicySha256)
        Assert-AdditionBindings $after.standaloneModuleTargets
        if (@($after.standaloneModuleTargets).Count -ne @($channelBefore.standaloneModuleTargets).Count + 1) {
            throw 'Unexpected postflight target count.'
        }
        $oldAfter = @($after.standaloneModuleTargets | Where-Object { $_.targetId -cne 'saef-media-carousel' })
        if (($oldAfter | ConvertTo-Json -Depth 100 -Compress) -cne
            ($channelBefore.standaloneModuleTargets | ConvertTo-Json -Depth 100 -Compress)) {
            throw 'Existing target records changed.'
        }
        if ((Get-DirectoryPackageIdentity $script:policy.activeModulePath) -cne
            $script:policy.expectedActivePackageIdentitySha256) { throw 'Runtime package changed.' }
        $result.channelPolicySha256 = $channelAfter.channelPolicySha256
        $result.channelEvidenceRoot = $channelAfter.evidenceRoot
        $result.outcome = 'installed'
    } else { $result.outcome = 'ready' }
    $result.stage = 'complete'
    $result.exitCode = 0
} catch {
    $result.error = $_.Exception.Message
    if ($result.stateProvisioningAttempted -or $result.targetInstallationAttempted) {
        $result.outcome = 'review_required'
        $result.exitCode = 40
    }
    # State and target are two separate transactions. Never recursively remove
    # adapter state or claim a combined atomic rollback; retain evidence for review.
} finally {
    $result.timestampUtc = [DateTime]::UtcNow.ToString('o')
    $json = $result | ConvertTo-Json -Depth 8
    if ($null -ne $evidence) {
        try { [IO.File]::WriteAllText((Join-Path $evidence 'result.local.json'), $json,
            [Text.UTF8Encoding]::new($false)) } catch {
            $result.exitCode = 40
            $result.outcome = 'review_required'
            $result.error = 'Cannot persist installation result; inspect child evidence.'
        }
    }
    $result | ConvertTo-Json -Depth 8 | Write-Output
}
exit $result.exitCode
