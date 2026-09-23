[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $PlanPath,
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-f0-9]{64}$')][string] $ExpectedPlanSha256,
    [Parameter(Mandatory = $true)][ValidateSet('preflight', 'apply')][string] $Operation,
    [Parameter()][string] $Confirmation = ''
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$result = [ordered]@{ formatVersion = 1; operation = 'enable_fit_toggle'; outcome = 'failed'; stage = 'inputs'
    productionMutationAttempted = $false; serviceRestartAttempted = $false; rollbackSucceeded = $null; exitCode = 10 }
$locks = @(); $held = @(); $attempted = @(); $evidence = $null

# Bootstrap only; import reviewed functions rather than another JSON, ACL,
# credential, RPC, snapshot or configuration parser implementation.
function Import-FitSource {
    param([string] $Path, [string] $Hash, [string[]] $Names)
    if (-not [IO.Path]::IsPathRooted($Path) -or (Get-Item -LiteralPath $Path).Length -gt 4194304 -or
        (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant() -cne $Hash) {
        throw 'Bound source differs.'
    }
    $text = [Text.UTF8Encoding]::new($false, $true).GetString([IO.File]::ReadAllBytes($Path))
    $sha = [Security.Cryptography.SHA256]::Create()
    try {
        if (([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($text)))).Replace('-', '').ToLowerInvariant() -cne $Hash) {
            throw 'Bound source changed while reading.'
        }
    } finally { $sha.Dispose() }
    $tokens = $null; $errors = $null
    $ast = [Management.Automation.Language.Parser]::ParseInput($text, [ref] $tokens, [ref] $errors)
    if (@($errors).Count) { throw 'Bound source parse failed.' }
    foreach ($name in $Names) {
        $fn = @($ast.FindAll({ param($n)
            $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq $name
        }, $false))
        if ($fn.Count -ne 1) { throw 'Bound function missing or ambiguous.' }
        # Imported functions must live in the caller script scope, not this function.
        $body = $fn[0].Body.Extent.Text
        Set-Item -Path ('Function:script:' + $name) -Value ([scriptblock]::Create($body.Substring(1, $body.Length - 2)))
    }
}

function Assert-FitScope {
    foreach ($ancestor in @($plan.ancestors)) {
        if ($ancestor.instanceId -isnot [int] -or $ancestor.instanceId -le 0 -or $ancestor.parentId -le 0) { throw 'Positive category references required.' }
        $object = Invoke-SymconRpc -Method 'IPS_GetObject' -Parameters @([int] $ancestor.instanceId)
        if ($object.ObjectType -ne 0 -or $object.ParentID -ne $ancestor.parentId) { throw 'Category ancestry changed.' }
    }
    foreach ($entry in @($plan.targets)) {
        if ($entry.instanceId -isnot [int] -or $entry.instanceId -le 0) { throw 'Positive instance required.' }
        if (-not (Invoke-SymconRpc -Method 'IPS_InstanceExists' -Parameters @([int] $entry.instanceId))) { throw 'Instance missing.' }
        $object = Invoke-SymconRpc -Method 'IPS_GetObject' -Parameters @([int] $entry.instanceId)
        $instance = Invoke-SymconRpc -Method 'IPS_GetInstance' -Parameters @([int] $entry.instanceId)
        if ($object.ObjectType -ne 1 -or $object.ParentID -ne $entry.parentId -or
            $instance.ModuleInfo.ModuleID -cne $script:policy.moduleGuid) { throw 'Instance scope changed.' }
    }
}

function Assert-FitRuntime {
    $null = Read-AdditionBoundBytes $channelPath $plan.channelSha256
    Assert-AdditionBindings $channel.standaloneModuleTargets
    Assert-SymconOwnership
    Assert-ModuleTreeIdentity $script:policy.activeModulePath
    if ((Get-DirectoryPackageIdentity $script:policy.activeModulePath) -cne $plan.activePackageIdentitySha256) { throw 'Live package changed.' }
    Assert-FitScope
}

function Write-FitEvidence { param([string] $Name, $Value)
    $path = Join-Path $evidence $Name
    $bytes = [Text.UTF8Encoding]::new($false).GetBytes(($Value | ConvertTo-Json -Depth 40))
    $file = [IO.File]::Open($path, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
    try { $file.Write($bytes, 0, $bytes.Length); $file.Flush($true) } finally { $file.Dispose() }
    $null = Read-AdditionBoundBytes $path (Get-BytesSha256 $bytes)
}

try {
    if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) { throw 'Windows PowerShell 5.1 required.' }
    if ($Operation -ceq 'apply' -and $Confirmation -cne 'enable-media-carousel-fit-toggle') { throw 'Exact confirmation required.' }
    # The launcher hash-binds this whole package before extraction. Standalone
    # execution additionally pins the plan before it supplies source hashes.
    if ((Get-Item -LiteralPath $PlanPath).Length -gt 1048576 -or
        (Get-FileHash -LiteralPath $PlanPath -Algorithm SHA256).Hash.ToLowerInvariant() -cne $ExpectedPlanSha256) { throw 'Plan differs.' }
    $planBytes = [IO.File]::ReadAllBytes($PlanPath)
    $sha = [Security.Cryptography.SHA256]::Create()
    try {
        if ($planBytes.Length -gt 1048576 -or
            ([BitConverter]::ToString($sha.ComputeHash($planBytes))).Replace('-', '').ToLowerInvariant() -cne $ExpectedPlanSha256) {
            throw 'Plan changed while reading.'
        }
    } finally { $sha.Dispose() }
    $plan = [Text.UTF8Encoding]::new($false, $true).GetString($planBytes) | ConvertFrom-Json
    $windows = Split-Path -Parent $PSScriptRoot
    $sources = @{ channel = (Join-Path $windows 'Initialize-SaefDeploymentChannel.ps1')
        adapter = (Join-Path $PSScriptRoot 'Invoke-SaefMediaCarouselModuleAdapter.ps1') }
    Import-FitSource $sources.channel $plan.sourceHashes.channel @('Get-BytesSha256', 'Assert-AdditionPlainPath',
        'Assert-AdditionProtectedPath', 'Read-AdditionBoundBytes', 'ConvertFrom-AdditionJson', 'Assert-AdditionBindings', 'Assert-Elevated')
    Import-FitSource $sources.adapter $plan.sourceHashes.adapter @('Get-Sha256', 'Get-TextSha256', 'Test-HexSha256',
        'Assert-RootedLeaf', 'Assert-SafeDirectoryTree', 'Import-MachineCredential', 'Invoke-SymconRpc',
        'Get-InstanceSnapshot', 'Assert-SnapshotPreserved', 'Get-DirectoryPackageIdentity',
        'Assert-ModuleTreeIdentity', 'Assert-SymconOwnership', 'Get-ConfigurationTokens')
    if ((Get-BytesSha256 $planBytes) -cne $ExpectedPlanSha256) { throw 'Plan changed while reading.' }
    $plan = ConvertFrom-AdditionJson $planBytes
    if ($plan.formatVersion -ne 1 -or $plan.targetId -cne 'saef-media-carousel' -or
        @($plan.targets).Count -lt 1 -or @($plan.targets).Count -gt 64 -or
        @($plan.ancestors).Count -lt 1 -or @($plan.ancestors).Count -gt 16 -or
        @($plan.instances).Count -gt 64 -or $plan.scopeRootId -le 0) { throw 'Invalid bounded settings plan.' }
    if (@($plan.ancestors | Where-Object { $_.instanceId -eq $plan.scopeRootId }).Count -ne 1) { throw 'Scope root must be pinned.' }
    Assert-Elevated | Out-Null
    $additionDeploymentSid = (Get-LocalUser $plan.deploymentUser).SID.Value
    if ($additionDeploymentSid -cne $plan.expectedDeploymentSid) { throw 'Deployment identity changed.' }
    $channelPath = Join-Path $plan.installRoot 'deployment-channel.local.json'
    foreach ($path in @($PlanPath, $PSScriptRoot, $channelPath) + @($sources.Values)) { Assert-AdditionProtectedPath $path }
    $channel = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $channelPath $plan.channelSha256)
    Assert-AdditionBindings $channel.standaloneModuleTargets
    $target = @($channel.standaloneModuleTargets | Where-Object { $_.targetId -ceq 'saef-media-carousel' })
    if ($target.Count -ne 1 -or $target[0].expectedAdapterSha256 -cne $plan.sourceHashes.adapter -or
        $target[0].expectedAdapterPolicySha256 -cne $plan.installedPolicySha256) { throw 'Installed binding changed.' }
    $script:policy = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $target[0].adapterPolicyPath $plan.installedPolicySha256)
    # This operation has its own fresh snapshot, not the historic upgrade baseline.
    # Neither the installed adapter policy nor its channel binding is rewritten.
    if ($script:policy.mutexName -cne 'Global\SAEF.MediaCarousel.ModuleAdapter') { throw 'Unexpected adapter lock.' }
    $RpcUri = [string] $channel.rpcUri; $uri = [uri] $RpcUri
    if (-not $uri.IsLoopback -or $uri.Scheme -cne 'http' -or $uri.UserInfo) { throw 'Loopback RPC required.' }
    Assert-AdditionProtectedPath $channel.credentialPath
    $script:credential = Import-MachineCredential $channel.credentialPath
    foreach ($name in @('Global\SAEF.DeploymentChannel', [string] $script:policy.mutexName)) {
        $lock = [Threading.Mutex]::new($false, $name); $locks += $lock
        try { $acquired = $lock.WaitOne(0) } catch [Threading.AbandonedMutexException] { $held += $lock; throw 'Abandoned lock requires review.' }
        if (-not $acquired) { throw 'Deployment busy.' }; $held += $lock
    }
    $before = @{ instances = @(Get-InstanceSnapshot -ExpectedInstances @($plan.instances)) }
    $after = $before | ConvertTo-Json -Depth 40 | ConvertFrom-Json
    $seen = @{}
    foreach ($entry in @($plan.targets)) {
        if ($seen.ContainsKey([string] $entry.instanceId)) { throw 'Duplicate target.' }; $seen[[string] $entry.instanceId] = $true
        $match = @($after.instances | Where-Object { $_.instanceId -eq $entry.instanceId })
        if ($match.Count -ne 1 -or $match[0].parentId -ne $entry.parentId) { throw 'Target outside baseline.' }
        $cursor = [int] $entry.parentId; $visited = @{}
        while ($cursor -ne $plan.scopeRootId) {
            if ($visited.ContainsKey($cursor)) { throw 'Ancestry cycle.' }; $visited[$cursor] = $true
            $ancestor = @($plan.ancestors | Where-Object { $_.instanceId -eq $cursor })
            if ($ancestor.Count -ne 1) { throw 'Target outside approved scope.' }; $cursor = [int] $ancestor[0].parentId
        }
        $config = [Text.UTF8Encoding]::new($false, $true).GetString([Convert]::FromBase64String($match[0].configurationBase64))
        $tokens = @(Get-ConfigurationTokens $config)
        $fit = @($tokens | Where-Object { $_.Groups['name'].Value -ceq 'ShowFitToggle' })
        if ($fit.Count -ne 1 -or $fit[0].Groups['value'].Value -cne 'false') { throw 'Expected disabled fit toggle.' }
        $config = $config.Replace('"ShowFitToggle":false', '"ShowFitToggle":true')
        $match[0].configurationBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($config))
        $match[0].configurationSha256 = Get-TextSha256 $config
    }
    Assert-FitRuntime
    Assert-SnapshotPreserved $before
    $result.stage = 'preflight'
    if ($Operation -ceq 'apply') {
        # Unique retained journal; no automatic deletion, pruning or blind retry.
        $evidence = Join-Path (Split-Path -Parent $PlanPath) ('fit-evidence-' + [guid]::NewGuid().ToString('N'))
        $null = New-Item -ItemType Directory -Path $evidence -ErrorAction Stop
        Assert-AdditionProtectedPath $evidence
        Write-FitEvidence 'before.local.json' $before
        Write-FitEvidence 'after.local.json' $after
        $current = $before | ConvertTo-Json -Depth 40 | ConvertFrom-Json
        $result.stage = 'apply'
        foreach ($entry in @($plan.targets)) {
            Assert-FitRuntime
            Assert-SnapshotPreserved $current
            $id = [int] $entry.instanceId
            Write-FitEvidence ('intent-' + $id + '.local.json') @{ instanceId = $id; property = 'ShowFitToggle'; value = $true }
            $attempted += $id; $result.productionMutationAttempted = $true
            $null = Invoke-SymconRpc -Method 'IPS_SetProperty' -Parameters @($id, 'ShowFitToggle', $true)
            # Recheck identity immediately before the second mutator, too.
            Assert-FitScope
            $null = Invoke-SymconRpc -Method 'IPS_ApplyChanges' -Parameters @($id)
            $record = @($current.instances | Where-Object { $_.instanceId -eq $id })[0]
            $desired = @($after.instances | Where-Object { $_.instanceId -eq $id })[0]
            $record.configurationBase64 = $desired.configurationBase64; $record.configurationSha256 = $desired.configurationSha256
            Assert-SnapshotPreserved $current
            Write-FitEvidence ('verified-' + $id + '.local.json') @{ instanceId = $id; configurationSha256 = $desired.configurationSha256 }
        }
        $result.stage = 'postflight'; Assert-FitRuntime; Assert-SnapshotPreserved $after
        $result.outcome = 'applied'
    } else { $result.outcome = 'ready' }
    $result.changedInstanceCount = if ($Operation -ceq 'apply') { $attempted.Count } else { 0 }
    $result.preservedInstanceCount = @($before.instances).Count - @($plan.targets).Count
    $result.stage = 'complete'; $result.exitCode = 0
} catch {
    $result.failure = @{ stage = $result.stage; errorType = $_.Exception.GetType().FullName; line = $_.InvocationInfo.ScriptLineNumber }
    if ($attempted.Count) {
        $result.exitCode = 40; $result.outcome = 'review_required'
        try {
            for ($index = $attempted.Count - 1; $index -ge 0; $index--) {
                $id = [int] $attempted[$index]
                Assert-FitRuntime
                $old = @($before.instances | Where-Object { $_.instanceId -eq $id })[0]
                $new = @($after.instances | Where-Object { $_.instanceId -eq $id })[0]
                $hash = Get-TextSha256 ([string] (Invoke-SymconRpc -Method 'IPS_GetConfiguration' -Parameters @($id)))
                if ($hash -cne $old.configurationSha256 -and $hash -cne $new.configurationSha256) { throw 'Foreign drift blocks rollback.' }
                $null = Invoke-SymconRpc -Method 'IPS_SetProperty' -Parameters @($id, 'ShowFitToggle', $false)
                Assert-FitScope
                $null = Invoke-SymconRpc -Method 'IPS_ApplyChanges' -Parameters @($id)
            }
            Assert-SnapshotPreserved $before
            $result.rollbackSucceeded = $true
        } catch {
            $result.rollbackSucceeded = $false
            $result.rollbackFailure = @{ errorType = $_.Exception.GetType().FullName; line = $_.InvocationInfo.ScriptLineNumber }
        }
    }
} finally {
    $script:credential = $null; $result.timestampUtc = [DateTime]::UtcNow.ToString('o')
    if ($null -ne $evidence) {
        $result.evidenceRoot = $evidence
        try { Write-FitEvidence 'result.local.json' $result } catch { $result.exitCode = 40; $result.outcome = 'review_required' }
    }
    for ($i = $held.Count - 1; $i -ge 0; $i--) { $held[$i].ReleaseMutex() }
    foreach ($lock in $locks) { $lock.Dispose() }
    $result | ConvertTo-Json -Depth 8
}
exit $result.exitCode
