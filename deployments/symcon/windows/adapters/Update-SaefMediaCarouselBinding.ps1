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
# exact reviewed existing sources. The channel initializer/adapter entrypoints
# never run; optional approval initialization sees only an unpublished shadow.
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
        [byte[]] $Adapter, [byte[]] $Policy, $ApprovalBootstrap = $null)
    # Exactly one authoritative pointer changes. Generation files are never
    # overwritten or deleted, including on failure or interrupted execution.
    if (Test-Path -LiteralPath $Generation) { throw 'Existing generation requires independent recovery review.' }
    $parent = Split-Path -Parent $Generation
    Assert-AdditionProtectedPath $parent
    if (@(Get-ChildItem -LiteralPath $parent -Directory -Force).Count -ge 16) { throw 'Retention gate required.' }
    $beforeHash = Get-BytesSha256 $Before; $afterHash = Get-BytesSha256 $After
    $null = Read-AdditionBoundBytes $ChannelPath $beforeHash
    $acl = Get-Acl -LiteralPath $ChannelPath
    if (-not $acl.AreAccessRulesProtected) { throw 'Channel policy requires an explicit protected ACL before replacement.' }
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
        if ($null -ne $ApprovalBootstrap) {
            $After = Stage-ApprovalProfile -Generation $Generation -ChannelBytes $After -Context $ApprovalBootstrap
            $afterHash = Get-BytesSha256 $After
            [IO.File]::WriteAllBytes($candidate, $After)
            $null = Read-AdditionBoundBytes $candidate $afterHash
        }
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
        $result.channelPolicySha256 = $afterHash
        $result.approvalProfileInstalled = $null -ne $ApprovalBootstrap
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

function Get-ApprovalBootstrapContext {
    param($Spec, [string] $Package, [string] $Windows, [string] $DeploymentUser, $Channel)
    $target = @($Channel.standaloneModuleTargets | Where-Object { $_.targetId -ceq 'saef-media-carousel' })
    if ($target.Count -ne 1 -or @($target[0].PSObject.Properties.Name | Where-Object {
        $_ -imatch '^(approvalRunnerPath|expectedApprovalRunnerSha256|approvalPolicyPath|expectedApprovalPolicySha256)$'
    }).Count -ne 0) { throw 'Approval bootstrap requires an unbound MediaCarousel target.' }
    $paths = @{
        initializer = Join-Path $Windows 'Initialize-SaefScopeBoundApprovalProfile.ps1'
        runner = Join-Path $Windows 'Invoke-SaefScopeBoundApprovalRunner.ps1'
        reseal = Join-Path $Windows 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1'
        child = Join-Path $Windows 'SaefChildProcess.ps1'
        qualification = Join-Path $Package 'qualification.local.json'
    }
    foreach ($name in $paths.Keys) {
        Assert-AdditionProtectedPath $paths[$name]
        $null = Read-AdditionBoundBytes $paths[$name] $Spec.sourceHashes.$name 4194304
    }
    foreach ($field in @('channelHostBindingSha256', 'approverIdentitySha256', 'executionHostIdentitySha256')) {
        if ($Spec.$field -isnot [string] -or $Spec.$field -cnotmatch '^[a-f0-9]{64}$') { throw 'Invalid approval identity binding.' }
    }
    if ($Spec.maximumStateFiles -isnot [int] -or $Spec.maximumStateFiles -lt 1 -or $Spec.maximumStateFiles -gt 4096 -or
        $Spec.sourceHashes.child -cne $Channel.expectedChildProcessContractSha256) { throw 'Invalid approval bootstrap contract.' }
    Assert-AdditionProtectedPath $Spec.approvalSecretRecordPath
    $secretBytes = Read-AdditionBoundBytes $Spec.approvalSecretRecordPath $Spec.approvalSecretSha256
    [Array]::Clear($secretBytes, 0, $secretBytes.Length)
    return @{ spec = $Spec; paths = $paths; deploymentUser = $DeploymentUser }
}

function Stage-ApprovalProfile {
    param([string] $Generation, [byte[]] $ChannelBytes, $Context)
    # Both live locks are already held by the caller. The existing initializer
    # sees only a shadow channel; no second live publication or mutex bypass.
    $spec = $Context.spec
    $shadow = Join-Path $Generation 'profile-staging'
    $approvalRoot = Join-Path $Generation 'approval'
    $null = [IO.Directory]::CreateDirectory($shadow)
    Set-RestrictedAcl $shadow '*S-1-5-32-544' '(OI)(CI)F'
    $shadowPolicy = Join-Path $shadow 'deployment-channel.local.json'
    [IO.File]::WriteAllBytes($shadowPolicy, $ChannelBytes)
    Set-RestrictedFileAcl $shadowPolicy
    $secretBytes = Read-AdditionBoundBytes $spec.approvalSecretRecordPath $spec.approvalSecretSha256
    [Array]::Clear($secretBytes, 0, $secretBytes.Length)
    $arguments = @('-DeploymentUser', $Context.deploymentUser, '-TargetId', 'saef-media-carousel',
        '-QualificationProfile', 'saef-windows-powershell-5.1-media-carousel-v1',
        '-PostflightProfile', 'saef-media-carousel-health-v1',
        '-ChannelHostBindingSha256', $spec.channelHostBindingSha256,
        '-ApproverIdentitySha256', $spec.approverIdentitySha256,
        '-ExecutionHostIdentitySha256', $spec.executionHostIdentitySha256,
        '-ApprovalSecretRecordPath', $spec.approvalSecretRecordPath,
        '-QualificationEvidencePath', $Context.paths.qualification,
        '-ExpectedQualificationEvidenceSha256', $spec.sourceHashes.qualification,
        '-ExpectedRunnerSha256', $spec.sourceHashes.runner, '-RunnerSourcePath', $Context.paths.runner,
        '-ResealEnabled', '-ResealSourcePath', $Context.paths.reseal,
        '-ExpectedResealScriptSha256', $spec.sourceHashes.reseal,
        '-MaximumStateFiles', [string] $spec.maximumStateFiles,
        '-ChannelInstallRoot', $shadow, '-ApprovalRoot', $approvalRoot)
    foreach ($phase in @('preflight', 'install', 'postflight')) {
        $statusPath = Join-Path $shadow ($phase + '.local.json')
        $extra = if ($phase -ceq 'install') { @() } else { @('-PreflightOnly') }
        $child = Invoke-SaefPowerShellChildProcess -ScriptPath $Context.paths.initializer `
            -ExpectedScriptSha256 $spec.sourceHashes.initializer `
            -Arguments (@($arguments + @('-StatusPath', $statusPath)) + $extra) `
            -TimeoutSeconds 120 -MaximumOutputBytes 8192
        if ($child.terminationReason -cne 'exited' -or $child.exitCode -ne 0) { throw 'Staged approval initializer failed.' }
        $status = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $statusPath (Get-BytesSha256 ([IO.File]::ReadAllBytes($statusPath))))
        $required = if ($phase -ceq 'install') { 'installed' } else { 'passed' }
        if ($status.outcome -cne $required -or $status.exitCode -ne 0 -or
            ($phase -ceq 'postflight' -and $status.repairRequired)) { throw 'Staged approval postflight differs.' }
    }
    $after = [IO.File]::ReadAllBytes($shadowPolicy)
    $expected = ConvertFrom-AdditionJson $ChannelBytes
    $target = @($expected.standaloneModuleTargets | Where-Object { $_.targetId -ceq 'saef-media-carousel' })[0]
    foreach ($binding in @(
        @('approvalRunnerPath', (Join-Path $Generation 'approval-runner.ps1')),
        @('approvalPolicyPath', (Join-Path $Generation 'approval-policy.local.json'))
    )) { $target | Add-Member NoteProperty $binding[0] $binding[1] }
    $target | Add-Member NoteProperty expectedApprovalRunnerSha256 $spec.sourceHashes.runner
    $target | Add-Member NoteProperty expectedApprovalPolicySha256 (Get-BytesSha256 ([IO.File]::ReadAllBytes($target.approvalPolicyPath)))
    if (($expected | ConvertTo-Json -Depth 100 -Compress) -cne
        ((ConvertFrom-AdditionJson $after) | ConvertTo-Json -Depth 100 -Compress)) { throw 'Staged profile changed unrelated channel fields.' }
    foreach ($binding in @(
        @($target.approvalRunnerPath, $spec.sourceHashes.runner),
        @((Join-Path $Generation 'active-identity-reseal.ps1'), $spec.sourceHashes.reseal),
        @((Join-Path $Generation 'approval-qualification.local.json'), $spec.sourceHashes.qualification),
        @((Join-Path $approvalRoot 'saef-media-carousel/approval-secret.local.json'), $spec.approvalSecretSha256)
    )) {
        Assert-AdditionProtectedPath $binding[0]
        $bound = Read-AdditionBoundBytes $binding[0] $binding[1] 4194304
        [Array]::Clear($bound, 0, $bound.Length)
    }
    return ,$after
}

function Assert-UpdateRuntime {
    Assert-SymconOwnership
    Assert-SnapshotPreserved $snapshot
    Assert-ModuleTreeIdentity $script:policy.activeModulePath
    if ((Get-DirectoryPackageIdentity $script:policy.activeModulePath) -cne $script:policy.expectedActivePackageIdentitySha256) {
        throw 'Production package changed.'
    }
}

function Assert-ReconciledBaseline {
    param($Before, $After, $Evidence)
    # Administrative bootstrap only: the separately reviewed evidence is bound
    # by the exact plan hash. Never generate it from arbitrary current drift.
    if ($Evidence.formatVersion -ne 1 -or $Evidence.targetId -cne 'saef-media-carousel' -or
        $Evidence.operation -cne 'reviewed_baseline_reconciliation' -or
        $Evidence.activePackageIdentitySha256 -cnotmatch '^[a-f0-9]{64}$' -or
        @($Evidence.sourceEvidenceSha256).Count -lt 1 -or
        $After.PSObject.Properties.Name -icontains 'configurationTransition') {
        throw 'Invalid reviewed reconciliation evidence.'
    }
    foreach ($hash in @($Evidence.sourceEvidenceSha256)) {
        if ($hash -isnot [string] -or $hash -cnotmatch '^[a-f0-9]{64}$') { throw 'Invalid prior evidence identity.' }
    }
    $baseline = @{}
    foreach ($instance in @($Evidence.expectedInstances)) {
        if (($instance.instanceId -isnot [int] -and $instance.instanceId -isnot [long]) -or
            $instance.instanceId -le 0 -or $instance.instanceId -gt [int]::MaxValue -or
            $baseline.ContainsKey([int] $instance.instanceId) -or
            $instance.configurationSha256 -isnot [string] -or
            $instance.configurationSha256 -cnotmatch '^[a-f0-9]{64}$') { throw 'Invalid reviewed instance baseline.' }
        $baseline[[int] $instance.instanceId] = $instance.configurationSha256
    }
    if ($baseline.Count -lt 1 -or @($Before.expectedInstances).Count -ne $baseline.Count) {
        throw 'Reconciliation cannot change instance membership.'
    }
    $copy = ConvertFrom-AdditionJson ([Text.Encoding]::UTF8.GetBytes(($Before | ConvertTo-Json -Depth 100)))
    $copy.PSObject.Properties.Remove('configurationTransition')
    $copy.expectedActivePackageIdentitySha256 = $Evidence.activePackageIdentitySha256
    foreach ($instance in @($copy.expectedInstances)) {
        $id = [int] $instance.instanceId
        if ($id -le 0 -or -not $baseline.ContainsKey($id)) { throw 'Reconciliation instance membership differs.' }
        $instance.configurationSha256 = $baseline[$id]
        $baseline.Remove($id)
    }
    if ($baseline.Count -ne 0 -or
        ($copy | ConvertTo-Json -Depth 100 -Compress) -cne ($After | ConvertTo-Json -Depth 100 -Compress)) {
        throw 'Policy exceeds the exact reviewed baseline reconciliation.'
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
    $reconcile = $false
    if ($plan.PSObject.Properties.Name -icontains 'updateKind') {
        if ($plan.updateKind -cne 'reviewed_baseline_reconciliation') { throw 'Unsupported binding update kind.' }
        $reconcile = $true
    }
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
        $script:policy.adapterProfile -cne 'saef-media-carousel-v1') { throw 'Unexpected adapter policy target.' }
    if ($reconcile) {
        $baselinePath = Join-Path $package 'reviewed-baseline.local.json'
        Assert-AdditionProtectedPath $baselinePath
        $baselineEvidence = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $baselinePath $plan.reviewedBaselineSha256)
        Assert-ReconciledBaseline $oldPolicy $script:policy $baselineEvidence
    } else {
        if ($script:policy.PSObject.Properties.Name -cnotcontains 'configurationTransition') { throw 'Required transition missing.' }
        if ($oldPolicy.PSObject.Properties.Name -icontains 'configurationTransition') { throw 'Existing transition requires separate review.' }
        $comparison = ConvertFrom-AdditionJson $policyBytes
        $comparison.PSObject.Properties.Remove('configurationTransition')
        if (($comparison | ConvertTo-Json -Depth 100 -Compress) -cne ($oldPolicy | ConvertTo-Json -Depth 100 -Compress)) {
            throw 'Adapter policy changes exceed the accepted transition.'
        }
    }
    $generation = Join-Path (Join-Path $plan.installRoot 'standalone-modules/saef-media-carousel') $plan.updateId
    $after = Read-AdditionBoundBytes $afterPath $plan.candidateChannelSha256
    Assert-UpdatePreservation $channel (ConvertFrom-AdditionJson $after) $generation $plan.sourceHashes.adapter $plan.candidatePolicySha256
    $approvalContext = $null
    if ($plan.PSObject.Properties.Name -icontains 'approvalBootstrap') {
        if (-not $reconcile) { throw 'Approval bootstrap requires reviewed baseline reconciliation.' }
        $approvalContext = Get-ApprovalBootstrapContext $plan.approvalBootstrap $package $windows $plan.deploymentUser $channel
        # One exact helper import in this isolated updater process.
        . $approvalContext.paths.child
    }
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
    if (-not $reconcile) {
        $script:manifest = @{ deploymentId = $plan.deploymentId }
        $script:packageIdentitySha256 = $plan.candidatePackageIdentitySha256
        $null = Get-CandidateSnapshot $snapshot
    }
    Assert-UpdateRuntime
    $result.stage = 'preflight'
    if (-not (Get-Acl -LiteralPath $channelPath).AreAccessRulesProtected) { throw 'Inherited channel ACL requires separate review.' }
    Assert-AdditionProtectedPath (Split-Path -Parent $generation)
    if (Test-Path -LiteralPath $generation) { throw 'Existing generation requires recovery review.' }
    if ($Operation -ceq 'install') {
        $result.stage = 'publish'
        Publish-UpdateGeneration $channelPath $generation $before $after `
            (Read-AdditionBoundBytes $sources.adapter $plan.sourceHashes.adapter 4194304) $policyBytes $approvalContext
        $result.outcome = 'installed'
    } else { $result.outcome = 'ready' }
    if ($Operation -cne 'install') { $result.channelPolicySha256 = $plan.candidateChannelSha256 }
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
