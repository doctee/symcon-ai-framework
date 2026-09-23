[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $PlanPath,
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-f0-9]{64}$')][string] $ExpectedPlanSha256,
    [Parameter(Mandatory = $true)][ValidateSet('preflight', 'install')][string] $Operation,
    [Parameter()][string] $Confirmation = ''
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$result = [ordered]@{ formatVersion = 1; operation = $Operation; outcome = 'failed'; stage = 'inputs'
    bindingMutationAttempted = $false; productionMutationAttempted = $false
    serviceRestartAttempted = $false; rollbackSucceeded = $null; exitCode = 10 }
$locks = @(); $held = @(); $snapshot = $null; $evidence = $null

# Bootstrap only. All subsequent validation/ACL/RPC behavior is imported from
# exact reviewed existing sources; no initializer or adapter entry point runs.
function Read-UpdateSource { param([string] $Path, [string] $Hash)
    if ($Hash -cnotmatch '^[a-f0-9]{64}$' -or -not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf) -or (Get-Item -LiteralPath $Path).Length -gt 4194304) {
        throw 'Invalid bound update input.'
    }
    $bytes = [IO.File]::ReadAllBytes($Path)
    $sha = [Security.Cryptography.SHA256]::Create()
    try {
        if ($bytes.Length -gt 4194304 -or
            ([BitConverter]::ToString($sha.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant() -cne $Hash) {
            throw 'Update input hash differs.'
        }
        return [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
    } finally { $sha.Dispose() }
}

function Assert-UpdatePreservation { param($Before, $After, [string] $Generation, [string] $AdapterHash, [string] $PolicyHash)
    $copy = ConvertFrom-AdditionJson ([Text.Encoding]::UTF8.GetBytes(($Before | ConvertTo-Json -Depth 100)))
    $targets = @($copy.standaloneModuleTargets | Where-Object { $_.targetId -ceq 'saef-media-carousel' })
    if ($targets.Count -ne 1 -or $targets[0].adapterProfile -cne 'saef-media-carousel-v1') { throw 'Target is not unique.' }
    $targets[0].adapterPath = Join-Path $Generation 'adapter.ps1'
    $targets[0].expectedAdapterSha256 = $AdapterHash
    $targets[0].adapterPolicyPath = Join-Path $Generation 'adapter-policy.local.json'
    $targets[0].expectedAdapterPolicySha256 = $PolicyHash
    if (($copy | ConvertTo-Json -Depth 100 -Compress) -cne ($After | ConvertTo-Json -Depth 100 -Compress)) {
        throw 'Channel changes exceed the four reviewed binding fields.'
    }
}

function Publish-UpdateGeneration {
    param([string] $ChannelPath, [string] $Generation, [byte[]] $Before, [byte[]] $After,
        [byte[]] $Adapter, [byte[]] $Policy)
    # Exactly one authoritative pointer changes. Generation files are never
    # overwritten or deleted, including on failure or interrupted execution.
    if (Test-Path -LiteralPath $Generation) { throw 'Existing generation requires independent recovery review.' }
    $parent = Split-Path -Parent $Generation
    Assert-AdditionProtectedPath $parent
    if (@(Get-ChildItem -LiteralPath $parent -Directory -Force).Count -ge 16) { throw 'Retention gate required.' }
    $beforeHash = Get-BytesSha256 $Before; $afterHash = Get-BytesSha256 $After
    $null = Read-AdditionBoundBytes $ChannelPath $beforeHash
    $acl = Get-Acl -LiteralPath $ChannelPath
    $null = [IO.Directory]::CreateDirectory($Generation)
    Set-RestrictedAcl $Generation '*S-1-5-32-544' '(OI)(CI)F'
    $script:evidence = $Generation
    $backup = Join-Path $Generation 'channel-before.local.json'
    $candidate = Join-Path $Generation 'channel-candidate.local.json'
    $entries = [ordered]@{
        'adapter.ps1' = $Adapter; 'adapter-policy.local.json' = $Policy
        'channel-before.local.json' = $Before; 'channel-candidate.local.json' = $After
    }
    foreach ($name in $entries.Keys) {
        $path = Join-Path $Generation $name
        [IO.File]::WriteAllBytes($path, $entries[$name]); Set-RestrictedFileAcl $path
        $null = Read-AdditionBoundBytes $path (Get-BytesSha256 $entries[$name]) 4194304
    }
    [IO.File]::WriteAllText((Join-Path $Generation 'channel-before-acl.local.txt'), $acl.Sddl, [Text.Encoding]::UTF8)
    Set-Acl -LiteralPath $candidate -AclObject $acl
    $published = $false
    try {
        Assert-AdditionBindings (ConvertFrom-AdditionJson $After).standaloneModuleTargets
        Assert-UpdateRuntime
        $null = Read-AdditionBoundBytes $ChannelPath $beforeHash
        if ((Get-Acl -LiteralPath $ChannelPath).Sddl -cne $acl.Sddl) { throw 'Channel ACL changed.' }
        $result.bindingMutationAttempted = $true
        [IO.File]::Replace($candidate, $ChannelPath, (Join-Path $Generation 'channel-replaced.local.json'))
        $published = $true
        $null = Read-AdditionBoundBytes $ChannelPath $afterHash
        if ((Get-Acl -LiteralPath $ChannelPath).Sddl -cne $acl.Sddl) { throw 'Publication changed ACL.' }
        Assert-AdditionBindings (ConvertFrom-AdditionJson $After).standaloneModuleTargets
        Assert-UpdateRuntime
    } catch {
        $failure = $_
        try {
            if ($published) {
                $null = Read-AdditionBoundBytes $ChannelPath $afterHash
                $restore = Join-Path $Generation 'channel-restore.local.json'
                [IO.File]::WriteAllBytes($restore, (Read-AdditionBoundBytes $backup $beforeHash))
                Set-Acl -LiteralPath $restore -AclObject $acl
                [IO.File]::Replace($restore, $ChannelPath, (Join-Path $Generation 'channel-failed.local.json'))
            }
            $null = Read-AdditionBoundBytes $ChannelPath $beforeHash
            if ((Get-Acl -LiteralPath $ChannelPath).Sddl -cne $acl.Sddl) { throw 'Rollback ACL differs.' }
            Assert-AdditionBindings (ConvertFrom-AdditionJson $Before).standaloneModuleTargets
            $result.rollbackSucceeded = $true
        } catch { $result.rollbackSucceeded = $false }
        throw $failure
    }
}

function Assert-UpdateRuntime {
    Assert-SymconOwnership
    Assert-SnapshotPreserved $snapshot
    Assert-ModuleTreeIdentity $script:policy.activeModulePath
    if ((Get-DirectoryPackageIdentity $script:policy.activeModulePath) -cne $script:policy.expectedActivePackageIdentitySha256) {
        throw 'Production package changed.'
    }
}

try {
    if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) { throw 'Windows PowerShell 5.1 required.' }
    if ($Operation -ceq 'install' -and $Confirmation -cne 'update-saef-media-carousel-binding') { throw 'Exact confirmation required.' }
    $planText = Read-UpdateSource $PlanPath $ExpectedPlanSha256
    $plan = $planText | ConvertFrom-Json
    $windows = Split-Path -Parent $PSScriptRoot
    $package = Split-Path -Parent $PlanPath
    $sources = @{ channel = (Join-Path $windows 'Initialize-SaefDeploymentChannel.ps1')
        adapter = (Join-Path $PSScriptRoot 'Invoke-SaefMediaCarouselModuleAdapter.ps1') }
    $imports = @{
        channel = @('Get-BytesSha256', 'Assert-AdditionPlainPath', 'Assert-AdditionProtectedPath', 'Read-AdditionBoundBytes',
            'ConvertFrom-AdditionJson', 'Assert-AdditionBindings', 'Assert-Elevated', 'Set-RestrictedAcl', 'Set-RestrictedFileAcl')
        adapter = @('Get-Sha256', 'Get-TextSha256', 'Test-HexSha256', 'Assert-RootedLeaf', 'Assert-SafeDirectoryTree',
            'Import-MachineCredential', 'Invoke-SymconRpc', 'Get-InstanceSnapshot', 'Assert-SnapshotPreserved',
            'Get-DirectoryPackageIdentity', 'Assert-ModuleTreeIdentity', 'Assert-SymconOwnership',
            'Get-ConfigurationTokens', 'Assert-FitDefaultAddition', 'Get-CandidateSnapshot')
    }
    foreach ($key in @('channel', 'adapter')) {
        $text = Read-UpdateSource $sources[$key] $plan.sourceHashes.$key
        $tokens = $null; $errors = $null
        $ast = [Management.Automation.Language.Parser]::ParseInput($text, [ref] $tokens, [ref] $errors)
        if (@($errors).Count) { throw 'Bound source parse failed.' }
        foreach ($name in $imports[$key]) {
            $fn = @($ast.FindAll({ param($n)
                $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq $name
            }, $false))
            if ($fn.Count -ne 1) { throw 'Bound function missing or ambiguous.' }
            . ([scriptblock]::Create($fn[0].Extent.Text))
        }
    }
    $plan = ConvertFrom-AdditionJson ([Text.Encoding]::UTF8.GetBytes($planText))
    if ($plan.formatVersion -ne 1 -or $plan.targetId -cne 'saef-media-carousel' -or
        $plan.updateId -cnotmatch '^[a-z0-9][a-z0-9-]{1,63}$') { throw 'Invalid update plan.' }
    Assert-Elevated | Out-Null
    $additionDeploymentSid = (Get-LocalUser $plan.deploymentUser).SID.Value
    if ($additionDeploymentSid -cne $plan.expectedDeploymentSid) { throw 'Deployment identity changed.' }
    $channelPath = Join-Path $plan.installRoot 'deployment-channel.local.json'
    foreach ($path in @($PlanPath, $package, $PSScriptRoot, $plan.installRoot, $channelPath) + @($sources.Values)) {
        Assert-AdditionProtectedPath $path
    }
    $before = Read-AdditionBoundBytes $channelPath $plan.channelSha256
    $channel = ConvertFrom-AdditionJson $before
    Assert-AdditionBindings $channel.standaloneModuleTargets
    $target = @($channel.standaloneModuleTargets | Where-Object { $_.targetId -ceq 'saef-media-carousel' })
    if ($target.Count -ne 1) { throw 'Unique installed target required.' }; $target = $target[0]
    if ($target.expectedAdapterSha256 -cne $plan.installedAdapterSha256 -or
        $target.expectedAdapterPolicySha256 -cne $plan.installedPolicySha256) { throw 'Installed binding changed.' }
    $oldPolicy = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $target.adapterPolicyPath $plan.installedPolicySha256)
    $policyPath = Join-Path $package 'candidate-policy.local.json'
    $afterPath = Join-Path $package 'candidate-channel.local.json'
    foreach ($path in @($policyPath, $afterPath)) { Assert-AdditionProtectedPath $path }
    $policyBytes = Read-AdditionBoundBytes $policyPath $plan.candidatePolicySha256
    $script:policy = ConvertFrom-AdditionJson $policyBytes
    if ($script:policy.targetId -cne 'saef-media-carousel' -or
        $script:policy.adapterProfile -cne 'saef-media-carousel-v1' -or
        $script:policy.PSObject.Properties.Name -cnotcontains 'configurationTransition') { throw 'Required transition missing.' }
    if ($oldPolicy.PSObject.Properties.Name -icontains 'configurationTransition') { throw 'Existing transition requires separate review.' }
    $comparison = ConvertFrom-AdditionJson $policyBytes
    $comparison.PSObject.Properties.Remove('configurationTransition')
    if (($comparison | ConvertTo-Json -Depth 100 -Compress) -cne ($oldPolicy | ConvertTo-Json -Depth 100 -Compress)) {
        throw 'Adapter policy changes exceed the accepted transition.'
    }
    $generation = Join-Path (Join-Path $plan.installRoot 'standalone-modules/saef-media-carousel') $plan.updateId
    $after = Read-AdditionBoundBytes $afterPath $plan.candidateChannelSha256
    Assert-UpdatePreservation $channel (ConvertFrom-AdditionJson $after) $generation $plan.sourceHashes.adapter $plan.candidatePolicySha256
    $RpcUri = [string] $channel.rpcUri
    $uri = [uri] $RpcUri
    if (-not $uri.IsLoopback -or $uri.Scheme -cne 'http' -or $uri.UserInfo) { throw 'Loopback RPC required.' }
    Assert-AdditionProtectedPath $channel.credentialPath
    $script:credential = Import-MachineCredential $channel.credentialPath
    if ($script:policy.mutexName -cne 'Global\SAEF.MediaCarousel.ModuleAdapter') { throw 'Unexpected adapter mutex.' }
    foreach ($name in @('Global\SAEF.DeploymentChannel', [string] $script:policy.mutexName)) {
        $lock = [Threading.Mutex]::new($false, $name); $locks += $lock
        try { $acquired = $lock.WaitOne(0) } catch [Threading.AbandonedMutexException] { $held += $lock; throw 'Abandoned lock requires recovery review.' }
        if (-not $acquired) { throw 'Deployment is busy.' }; $held += $lock
    }
    $null = Read-AdditionBoundBytes $channelPath $plan.channelSha256
    Assert-AdditionBindings $channel.standaloneModuleTargets
    $snapshot = @{ instances = @(Get-InstanceSnapshot) }
    if ($snapshot.instances.Count -lt 1) { throw 'Empty production inventory.' }
    $script:manifest = @{ deploymentId = $plan.deploymentId }
    $script:packageIdentitySha256 = $plan.candidatePackageIdentitySha256
    $null = Get-CandidateSnapshot $snapshot
    Assert-UpdateRuntime
    $result.stage = 'preflight'
    Assert-AdditionProtectedPath (Split-Path -Parent $generation)
    if (Test-Path -LiteralPath $generation) { throw 'Existing generation requires recovery review.' }
    if ($Operation -ceq 'install') {
        $result.stage = 'publish'
        Publish-UpdateGeneration $channelPath $generation $before $after `
            (Read-AdditionBoundBytes $sources.adapter $plan.sourceHashes.adapter 4194304) $policyBytes
        $result.outcome = 'installed'
    } else { $result.outcome = 'ready' }
    $result.channelPolicySha256 = $plan.candidateChannelSha256
    $result.adapterSha256 = $plan.sourceHashes.adapter
    $result.adapterPolicySha256 = $plan.candidatePolicySha256
    $result.stage = 'complete'; $result.exitCode = 0
} catch {
    $result.failure = @{ stage = $result.stage; errorType = $_.Exception.GetType().FullName
        line = $_.InvocationInfo.ScriptLineNumber }
    if ($null -ne $evidence) { $result.exitCode = 40; $result.outcome = 'review_required' }
} finally {
    $script:credential = $null
    $result.timestampUtc = [DateTime]::UtcNow.ToString('o')
    if ($null -ne $evidence) {
        $result.evidenceRoot = $evidence
        try { [IO.File]::WriteAllText((Join-Path $evidence 'result.local.json'), ($result | ConvertTo-Json -Depth 8), [Text.Encoding]::UTF8) }
        catch { $result.outcome = 'review_required'; $result.exitCode = 40 }
    }
    for ($i = $held.Count - 1; $i -ge 0; $i--) { $held[$i].ReleaseMutex() }
    foreach ($lock in $locks) { $lock.Dispose() }
    $result | ConvertTo-Json -Depth 8
}
exit $result.exitCode
