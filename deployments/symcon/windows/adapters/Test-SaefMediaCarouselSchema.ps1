[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $PlanPath,
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-f0-9]{64}$')][string] $ExpectedPlanSha256,
    [Parameter(Mandatory = $true)][ValidateSet('qualify-media-carousel-schema')] [string] $Confirmation
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$result = [ordered]@{ formatVersion = 1; operation = 'isolated_schema_qualification'; outcome = 'failed'
    stage = 'inputs'; testMutationAttempted = $false; productionMutationAttempted = $false
    serviceRestartAttempted = $false; cleanupVerified = $false; productionPreserved = $false; exitCode = 10 }
$evidence = $null
$testID = $null
$owned = $false
$createAttempted = $false
$snapshot = $null
$lock = $null
$locked = $false
$testLibrary = '{85DE8006-9775-49E6-BB7E-1924BAA5459A}'
$testModule = '{7CB1E964-8C34-4B61-B255-68D86AEC99F2}'
$testFolder = 'saef-media-carousel-schema-probe'
$testIdent = 'SAEFMediaCarouselSchemaProbe'

function Read-ProbeBoundSource { param([string] $Path, [string] $Hash)
    if ($Hash -cnotmatch '^[a-f0-9]{64}$' -or -not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf) -or (Get-Item -LiteralPath $Path).Length -gt 4194304) {
        throw 'Missing or oversized bound schema input.'
    }
    $bytes = [IO.File]::ReadAllBytes($Path)
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        if ($bytes.Length -gt 4194304 -or
            ([BitConverter]::ToString($algorithm.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant() -cne $Hash) {
            throw 'Schema input hash differs.'
        }
        return [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
    } finally { $algorithm.Dispose() }
}
function Assert-ProbeInstance {
    if ($null -eq $testID -or $testID -le 0 -or
        -not (Invoke-SymconRpc 'IPS_InstanceExists' @($testID))) { throw 'Positive owned test instance required.' }
    $object = Invoke-SymconRpc 'IPS_GetObject' @($testID)
    $instance = Invoke-SymconRpc 'IPS_GetInstance' @($testID)
    if ($object.ObjectType -ne 1 -or $instance.ModuleInfo.ModuleID -cne $testModule -or
        @(Invoke-SymconRpc 'IPS_GetChildrenIDs' @($testID)).Count -ne 0) { throw 'Test instance ownership changed.' }
}
function Invoke-ProbeMutation { param([string] $Method, [object[]] $Arguments = @())
    Assert-ProbeInstance
    if ($Method -cnotin @('IPS_SetParent', 'IPS_SetIdent', 'IPS_SetName', 'IPS_SetHidden',
        'IPS_SetConfiguration', 'IPS_ApplyChanges', 'IPS_DeleteInstance')) { throw 'Unapproved test mutation.' }
    if ((Invoke-SymconRpc $Method (@($testID) + $Arguments)) -ne $true) { throw 'Test mutation failed.' }
}
function Save-ProbeJournal {
    Write-AtomicJson (Join-Path $evidence 'journal.local.json') ([ordered]@{
        stage = $result.stage; testPath = $testPath; testInstanceId = $testID
        createAttempted = $createAttempted; ownedDirectory = $owned
        productionMutationAttempted = $false; timestampUtc = [DateTime]::UtcNow.ToString('o')
    })
}
function Assert-ProbeTree {
    Assert-AdditionProtectedPath $testPath
    Assert-SafeDirectoryTree $testPath
    $actual = @(Get-ChildItem -LiteralPath $testPath -Recurse -Force -File)
    if ($actual.Count -ne 3 -or @(Get-ChildItem -LiteralPath $testPath -Recurse -Force -Directory).Count -ne 1) {
        throw 'Unexpected test tree entries; retain for review.'
    }
    foreach ($name in @('library.json', 'SchemaProbe/module.json')) {
        if ((Get-Sha256 (Join-Path $testPath $name)) -cne (Get-TextSha256 $fixture[$name])) {
            throw 'Test metadata changed; retain for review.'
        }
    }
    if ((Get-Sha256 (Join-Path $testPath 'SchemaProbe/module.php')) -cnotin
        @((Get-TextSha256 $fixture['legacy.php']), (Get-TextSha256 $fixture['candidate.php']))) {
        throw 'Test code changed; retain for review.'
    }
}
function Assert-ProbeProduction {
    Assert-SnapshotPreserved $snapshot
    Assert-ModuleTreeIdentity $script:policy.activeModulePath
    if ((Get-DirectoryPackageIdentity $script:policy.activeModulePath) -cne
        $script:policy.expectedActivePackageIdentitySha256 -or
        (Get-Sha256 $channelPath) -cne $plan.channelPolicySha256 -or
        (Get-Sha256 $target.adapterPolicyPath) -cne $plan.adapterPolicySha256) {
        throw 'Production package or protected policy changed.'
    }
}
try {
    if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
        throw 'Windows PowerShell 5.1 required.'
    }
    $planText = Read-ProbeBoundSource $PlanPath $ExpectedPlanSha256
    $plan = $planText | ConvertFrom-Json
    $windows = Split-Path -Parent $PSScriptRoot
    $sources = @{
        channel = Join-Path $windows 'Initialize-SaefDeploymentChannel.ps1'
        adapter = Join-Path $PSScriptRoot 'Invoke-SaefMediaCarouselModuleAdapter.ps1'
    }
    $imports = @{
        channel = @('Get-BytesSha256', 'Assert-AdditionPlainPath', 'Assert-AdditionProtectedPath',
            'Read-AdditionBoundBytes', 'ConvertFrom-AdditionJson', 'Assert-AdditionBindings', 'Assert-Elevated')
        adapter = @('Get-Sha256', 'Get-TextSha256', 'Assert-RootedLeaf', 'Assert-SafeDirectoryTree',
            'Import-MachineCredential', 'Invoke-SymconRpc', 'Get-InstanceSnapshot', 'Assert-SnapshotPreserved',
            'Get-DirectoryPackageIdentity', 'Assert-ModuleTreeIdentity', 'Assert-SymconOwnership',
            'Get-ConfigurationTokens', 'Assert-FitDefaultAddition', 'Write-AtomicJson', 'Write-AtomicText')
    }
    foreach ($key in @('channel', 'adapter')) {
        $text = Read-ProbeBoundSource $sources[$key] $plan.sourceHashes.$key
        $tokens = $null; $errors = $null
        $ast = [Management.Automation.Language.Parser]::ParseInput($text, [ref] $tokens, [ref] $errors)
        if (@($errors).Count) { throw 'Bound source parse failed.' }
        foreach ($name in $imports[$key]) {
            $matches = @($ast.FindAll({ param($n)
                $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq $name
            }, $false))
            if ($matches.Count -ne 1) { throw 'Required bound function missing or ambiguous.' }
            . ([scriptblock]::Create($matches[0].Extent.Text))
        }
    }
    $plan = ConvertFrom-AdditionJson ([Text.Encoding]::UTF8.GetBytes($planText))
    if ($plan.formatVersion -ne 1 -or $plan.targetId -cne 'saef-media-carousel' -or
        [int] $plan.parentId -le 0 -or [int] $plan.parentParentId -le 0 -or
        $plan.deploymentId -cnotmatch '^[a-z0-9][a-z0-9-]{1,95}$' -or
        $plan.candidatePackageIdentitySha256 -cnotmatch '^[a-f0-9]{64}$') { throw 'Invalid schema plan.' }
    Assert-Elevated | Out-Null
    $additionDeploymentSid = (Get-LocalUser $plan.deploymentUser).SID.Value
    if ($additionDeploymentSid -cne $plan.expectedDeploymentSid) { throw 'Deployment account changed.' }
    $channelPath = Join-Path $plan.installRoot 'deployment-channel.local.json'
    foreach ($path in @($PlanPath, $PSScriptRoot, $channelPath) + @($sources.Values)) { Assert-AdditionProtectedPath $path }
    $channel = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $channelPath $plan.channelPolicySha256)
    Assert-AdditionBindings $channel.standaloneModuleTargets
    $targets = @($channel.standaloneModuleTargets | Where-Object { $_.targetId -ceq 'saef-media-carousel' })
    if ($targets.Count -ne 1) { throw 'Installed target missing or ambiguous.' }
    $target = $targets[0]
    if ($target.expectedAdapterSha256 -cne $plan.sourceHashes.adapter -or
        $target.expectedAdapterPolicySha256 -cne $plan.adapterPolicySha256) { throw 'Installed binding changed.' }
    $script:policy = ConvertFrom-AdditionJson (Read-AdditionBoundBytes $target.adapterPolicyPath $plan.adapterPolicySha256)
    if ($script:policy.targetId -cne 'saef-media-carousel' -or $script:policy.adapterProfile -cne 'saef-media-carousel-v1' -or
        $script:policy.moduleGuid -ceq $testModule -or [int] $script:policy.moduleControlInstanceId -le 0 -or
        [int] $script:policy.maximumInstanceCount -gt 64 -or [int] $script:policy.rpcTimeoutSeconds -gt 30) {
        throw 'Unsupported adapter policy.'
    }
    $RpcUri = [string] $channel.rpcUri
    $uri = [uri] $RpcUri
    if (-not $uri.IsLoopback -or $uri.Scheme -cne 'http' -or $uri.UserInfo) { throw 'Loopback RPC required.' }
    Assert-AdditionProtectedPath $channel.credentialPath
    $script:credential = Import-MachineCredential $channel.credentialPath
    $lock = [Threading.Mutex]::new($false, [string] $script:policy.mutexName)
    $locked = $lock.WaitOne(0)
    if (-not $locked) { throw 'MediaCarousel adapter is busy.' }
    Assert-SymconOwnership
    $snapshot = @{ instances = @(Get-InstanceSnapshot) }
    if ($snapshot.instances.Count -lt 1) { throw 'No production baseline.' }
    Assert-ProbeProduction
    $parent = Invoke-SymconRpc 'IPS_GetObject' @([int] $plan.parentId)
    $parentParent = if ($parent.PSObject.Properties.Name -contains 'ParentID') { $parent.ParentID } else { $parent.ObjectParentID }
    if ($parent.ObjectType -ne 0 -or $parent.ObjectIdent -cne $plan.parentIdent -or
        $parentParent -ne $plan.parentParentId) { throw 'Private test parent identity changed.' }
    $testPath = Join-Path (Split-Path -Parent $script:policy.activeModulePath) $testFolder
    Assert-AdditionProtectedPath (Split-Path -Parent $testPath)
    if ((Test-Path -LiteralPath $testPath) -or (Invoke-SymconRpc 'IPS_LibraryExists' @($testLibrary)) -or
        (Invoke-SymconRpc 'IPS_ModuleExists' @($testModule))) { throw 'Existing test artifacts require separate recovery.' }
    foreach ($method in @('MC_ReloadModule', 'MC_DeleteModule')) {
        $signature = Invoke-SymconRpc 'IPS_GetFunction' @($method)
        if ($signature.Parameters.Count -ne 2 -or $signature.Parameters[0].Type_ -ne 1 -or
            $signature.Parameters[1].Type_ -ne 3) { throw 'Unexpected test module control signature.' }
    }
    $fixture = @{}
    foreach ($name in @('library.json', 'SchemaProbe/module.json', 'legacy.php', 'candidate.php')) {
        $source = Join-Path (Join-Path $PSScriptRoot 'schema-probe') $name
        Assert-AdditionProtectedPath $source
        $fixture[$name] = Read-ProbeBoundSource $source $plan.fixtureHashes.$name
    }
    $evidence = Join-Path (Split-Path -Parent $PlanPath) ('schema-evidence-' + [guid]::NewGuid().ToString('N'))
    $null = [IO.Directory]::CreateDirectory($evidence)
    Assert-AdditionProtectedPath $evidence
    $result.evidenceRoot = $evidence
    Write-AtomicJson (Join-Path $evidence 'before.local.json') $snapshot
    $result.stage = 'test_registration'
    Save-ProbeJournal
    # Fixed new directory only. No adoption, production reload or camera logic.
    $result.testMutationAttempted = $true
    $null = New-Item -ItemType Directory -Path $testPath -ErrorAction Stop
    $owned = $true
    $null = [IO.Directory]::CreateDirectory((Join-Path $testPath 'SchemaProbe'))
    foreach ($name in @('library.json', 'SchemaProbe/module.json')) { Write-AtomicText (Join-Path $testPath $name) $fixture[$name] }
    Write-AtomicText (Join-Path $testPath 'SchemaProbe/module.php') $fixture['legacy.php']
    Assert-ProbeTree
    Save-ProbeJournal
    if ((Invoke-SymconRpc 'MC_ReloadModule' @([int] $script:policy.moduleControlInstanceId, $testFolder)) -ne $true -or
        -not (Invoke-SymconRpc 'IPS_ModuleExists' @($testModule))) { throw 'Test library registration failed.' }
    if (@(Invoke-SymconRpc 'IPS_GetInstanceListByModuleID' @($testModule)).Count -ne 0) { throw 'Unexpected test instance.' }
    $createAttempted = $true
    Save-ProbeJournal
    $newID = Invoke-SymconRpc 'IPS_CreateInstance' @($testModule)
    if ($null -eq $newID -or [string] $newID -cnotmatch '^[1-9][0-9]*$') { throw 'No positive test instance ID returned.' }
    $testID = [int] $newID
    Save-ProbeJournal
    Invoke-ProbeMutation 'IPS_SetParent' @([int] $plan.parentId)
    Invoke-ProbeMutation 'IPS_SetIdent' @($testIdent)
    Invoke-ProbeMutation 'IPS_SetName' @('SAEF MediaCarousel isolated schema test')
    Invoke-ProbeMutation 'IPS_SetHidden' @($true)
    $first = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($snapshot.instances[0].configurationBase64))
    Invoke-ProbeMutation 'IPS_SetConfiguration' @($first)
    Invoke-ProbeMutation 'IPS_ApplyChanges' @()
    if ((Invoke-SymconRpc 'IPS_GetConfiguration' @($testID)) -cne $first) { throw 'Legacy test registration does not reproduce baseline.' }
    $result.stage = 'test_default_registration'
    Save-ProbeJournal
    Write-AtomicText (Join-Path $testPath 'SchemaProbe/module.php') $fixture['candidate.php']
    Assert-ProbeTree
    if ((Invoke-SymconRpc 'MC_ReloadModule' @([int] $script:policy.moduleControlInstanceId, $testFolder)) -ne $true) {
        throw 'Test candidate reload failed.'
    }
    $registered = [string] (Invoke-SymconRpc 'IPS_GetConfiguration' @($testID))
    Assert-FitDefaultAddition $first $registered
    $transition = [ordered]@{ kind = 'show-fit-toggle-default-false-v1'; deploymentId = $plan.deploymentId
        sourcePackageIdentitySha256 = $script:policy.expectedActivePackageIdentitySha256
        candidatePackageIdentitySha256 = $plan.candidatePackageIdentitySha256; instances = @() }
    foreach ($record in $snapshot.instances) {
        $before = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($record.configurationBase64))
        Invoke-ProbeMutation 'IPS_SetConfiguration' @($before)
        Invoke-ProbeMutation 'IPS_ApplyChanges' @()
        $after = [string] (Invoke-SymconRpc 'IPS_GetConfiguration' @($testID))
        Assert-FitDefaultAddition $before $after
        $transition.instances += [ordered]@{ instanceId = $record.instanceId
            configurationBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($after))
            configurationSha256 = Get-TextSha256 $after }
    }
    Write-AtomicJson (Join-Path $evidence 'transition.unaccepted.local.json') $transition
    $result.qualifiedInstanceCount = $transition.instances.Count
    $result.outcome = 'schema_observed'
} catch { $result.error = $_.Exception.Message }
finally {
    if ($owned) {
        try {
            $result.stage = 'test_cleanup'
            Save-ProbeJournal
            Assert-ProbeTree
            $ids = @(Invoke-SymconRpc 'IPS_GetInstanceListByModuleID' @($testModule))
            if ($ids.Count -gt 1 -or ($ids.Count -eq 1 -and -not $createAttempted)) { throw 'Ambiguous test cleanup ownership.' }
            if ($ids.Count -eq 1) {
                if ($null -ne $testID -and $testID -ne $ids[0]) { throw 'Test instance ID changed.' }
                $testID = [int] $ids[0]
                Save-ProbeJournal
                Invoke-ProbeMutation 'IPS_DeleteInstance' @()
                if (Invoke-SymconRpc 'IPS_InstanceExists' @($testID)) { throw 'Test instance deletion incomplete.' }
            }
            Assert-ProbeTree
            if ((Invoke-SymconRpc 'MC_DeleteModule' @([int] $script:policy.moduleControlInstanceId, $testFolder)) -ne $true -or
                (Test-Path -LiteralPath $testPath) -or (Invoke-SymconRpc 'IPS_ModuleExists' @($testModule)) -or
                (Invoke-SymconRpc 'IPS_LibraryExists' @($testLibrary))) { throw 'Test library removal incomplete.' }
            $result.cleanupVerified = $true
        } catch { $result.cleanupError = $_.Exception.Message; $result.outcome = 'manual_recovery_required' }
    }
    if ($null -ne $snapshot) {
        try { Assert-ProbeProduction; $result.productionPreserved = $true }
        catch { $result.postflightError = $_.Exception.Message; $result.outcome = 'manual_recovery_required' }
    }
    try {
        if ($result.outcome -ceq 'schema_observed' -and $result.cleanupVerified -and $result.productionPreserved) {
            $accepted = Join-Path $evidence 'configuration-transition.local.json'
            Write-AtomicJson $accepted $transition
            $result.transitionSha256 = Get-Sha256 $accepted
            $result.outcome = 'qualified'; $result.exitCode = 0; $result.stage = 'complete'
        } elseif ($result.testMutationAttempted) { $result.exitCode = 40 }
    } catch { $result.outcome = 'evidence_write_failed'; $result.exitCode = 40; $result.error = $_.Exception.Message }
    if ($locked) { $lock.ReleaseMutex() }
    if ($null -ne $lock) { $lock.Dispose() }
    $script:credential = $null
    $result.timestampUtc = [DateTime]::UtcNow.ToString('o')
    if ($null -ne $evidence) {
        try { Write-AtomicJson (Join-Path $evidence 'result.local.json') $result }
        catch { $result.outcome = 'evidence_write_failed'; $result.exitCode = 40 }
    }
    $result | ConvertTo-Json -Depth 8
}
exit $result.exitCode
