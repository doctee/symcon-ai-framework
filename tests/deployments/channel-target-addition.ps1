# Windows-only synthetic qualification; never touches an installed channel or services.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
    throw 'Qualification requires Windows PowerShell 5.1.'
}
$windowsRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
$initializerPath = Join-Path $windowsRoot 'Initialize-SaefDeploymentChannel.ps1'
$tokens = $null
$parseErrors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($initializerPath, [ref] $tokens, [ref] $parseErrors)
if (@($parseErrors).Count -ne 0) { throw ($parseErrors | Out-String) }
foreach ($definition in @($ast.FindAll({ param($node)
    $node -is [Management.Automation.Language.FunctionDefinitionAst]
}, $false))) { . ([scriptblock]::Create($definition.Extent.Text)) }
. (Join-Path $windowsRoot 'SaefChildProcess.ps1')
Assert-Elevated | Out-Null
# Unit calls retain production filesystem, ACL, hash, mutex and transaction functions.
# Bundle checks use the original entry point in the separate real CLI tests below.
function Assert-SourceChecksums { }
function Assert-PowerShellSourceSyntax { }
function Get-Service { throw 'Add mode must not query services.' }
function Restart-Service { throw 'Add mode must not restart services.' }
function Assert-Test { param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}
function Write-Json { param([string] $Path, $Value)
    [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 50), [Text.UTF8Encoding]::new($false))
}
function Hash-File { param([string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}
$DeploymentUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name.Split('\')[-1]
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('saef-addition-test-' + [Guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $scratch
Set-RestrictedAcl $scratch '*S-1-5-32-544' '(OI)(CI)F'
$originalBindingCheck = (Get-Command Assert-AdditionBindings).ScriptBlock
$fixtureNumber = 0
$passed = 0

function New-Fixture {
    $script:fixtureNumber++
    $script:fixture = Join-Path $scratch ([string] $fixtureNumber)
    $null = New-Item -ItemType Directory -Path $fixture
    $script:InstallRoot = Join-Path $fixture 'channel'
    $null = New-Item -ItemType Directory -Path $InstallRoot
    Set-RestrictedAcl $InstallRoot '*S-1-5-32-544' '(OI)(CI)F'
    $oldRoot = Join-Path $InstallRoot 'standalone-modules/saef-existing'
    $null = New-Item -ItemType Directory -Path $oldRoot -Force
    $old = [ordered]@{ targetId = 'saef-existing'; adapterProfile = 'saef-existing-v1'
        libraryGuid = '{11111111-1111-1111-1111-111111111111}' }
    foreach ($binding in @(@('adapterPath', 'expectedAdapterSha256'),
        @('adapterPolicyPath', 'expectedAdapterPolicySha256'),
        @('approvalRunnerPath', 'expectedApprovalRunnerSha256'),
        @('approvalPolicyPath', 'expectedApprovalPolicySha256'))) {
        $path = Join-Path $oldRoot ($binding[0] + '.txt')
        [IO.File]::WriteAllText($path, 'synthetic-existing-' + $binding[0])
        Set-RestrictedFileAcl $path
        $old[$binding[0]] = $path
        $old[$binding[1]] = Hash-File $path
    }
    $old.futureExtension = @{ nested = @{ values = @('unchanged', 17, $false); text = 'Ä/I/ı' } }
    $script:policyPath = Join-Path $InstallRoot 'deployment-channel.local.json'
    Write-Json $policyPath ([ordered]@{ formatVersion = 1; deploymentUser = $DeploymentUser.ToLowerInvariant()
        standaloneModuleTargets = @($old); futureTopLevel = @{ flags = @($true, $false); value = 'preserve' }
        credentialPath = 'unchanged-credential'; serviceName = 'unchanged-service' })
    Set-RestrictedFileAcl $policyPath
    $script:beforeText = [IO.File]::ReadAllText($policyPath)
    $script:beforeAcl = (Get-Acl -LiteralPath $policyPath).Sddl
    $adapterPath = Join-Path $fixture 'source.ps1'
    [IO.File]::WriteAllText($adapterPath, '# synthetic adapter, never executed')
    $adapterPolicy = Join-Path $fixture 'policy.local.json'
    Write-Json $adapterPolicy @{ formatVersion = 1; adapterProfile = 'saef-new-v1' }
    $script:StandaloneModuleTargetsPath = Join-Path $fixture 'targets.local.json'
    Write-Json $StandaloneModuleTargetsPath @{ formatVersion = 1; targets = @([ordered]@{
        targetId = 'saef-new'; adapterProfile = 'saef-new-v1'
        libraryGuid = '{22222222-2222-2222-2222-222222222222}'
        adapterPath = $adapterPath; expectedAdapterSha256 = (Hash-File $adapterPath)
        adapterPolicyPath = $adapterPolicy; expectedAdapterPolicySha256 = (Hash-File $adapterPolicy)
    }) }
    $script:ExpectedChannelPolicySha256 = Hash-File $policyPath
    $script:ExpectedTargetManifestSha256 = Hash-File $StandaloneModuleTargetsPath
    $script:ExpectedAdditionPlanSha256 = ''
    $script:PreflightOnly = $true
}
function Assert-Unchanged {
    Assert-Test ((Hash-File $policyPath) -ceq $ExpectedChannelPolicySha256) 'Existing policy changed.'
    Assert-Test ((Get-Acl -LiteralPath $policyPath).Sddl -ceq $beforeAcl) 'Existing ACL changed.'
    Assert-Test (-not (Test-Path -LiteralPath (Join-Path $InstallRoot 'standalone-modules/saef-new'))) 'New target leaked.'
}
function Approve-Plan {
    $preflight = Invoke-StandaloneTargetAddition
    Assert-Test ($preflight.outcome -ceq 'passed') ('Preflight failed: ' + ($preflight.details | ConvertTo-Json))
    Assert-Unchanged
    $script:ExpectedAdditionPlanSha256 = $preflight.details.additionPlanSha256
    $script:PreflightOnly = $false
}
function Expect-Rejected {
    $result = Invoke-StandaloneTargetAddition
    Assert-Test ($result.exitCode -eq 10 -and -not $result.details.mutationAttempted) 'Expected pre-mutation rejection.'
    Assert-Unchanged
    $script:passed++
}

$savedCulture = [Threading.Thread]::CurrentThread.CurrentCulture
try {
    foreach ($unsafePath in @('C:relative', '\root-relative', '\\server\share\file', 'C:\safe\file:stream')) {
        $rejected = $false
        try { Assert-AdditionPlainPath $unsafePath } catch { $rejected = $true }
        Assert-Test $rejected ('Unsafe path accepted: ' + $unsafePath)
        $passed++
    }
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
        New-Fixture
        Approve-Plan
        $result = Invoke-StandaloneTargetAddition
        Assert-Test ($result.outcome -ceq 'installed' -and -not $result.details.sshdRestartAttempted) ($result.details | ConvertTo-Json)
        $after = Get-Content -LiteralPath $policyPath -Raw -Encoding UTF8 | ConvertFrom-Json
        $before = $beforeText | ConvertFrom-Json
        Assert-Test ($after.standaloneModuleTargets.Count -eq 2) 'New target absent.'
        Assert-Test (($after.standaloneModuleTargets[0] | ConvertTo-Json -Depth 50 -Compress) -ceq
            ($before.standaloneModuleTargets[0] | ConvertTo-Json -Depth 50 -Compress)) 'Existing approval/extension fields lost.'
        $after.standaloneModuleTargets = @($before.standaloneModuleTargets)
        Assert-Test (($after | ConvertTo-Json -Depth 50 -Compress) -ceq
            ($before | ConvertTo-Json -Depth 50 -Compress)) 'Unrelated policy changed.'
        Assert-Test ((Get-Acl -LiteralPath $policyPath).Sddl -ceq $beforeAcl) 'Policy ACL drifted.'
        Assert-Test ((Hash-File (Join-Path $result.details.evidenceRoot 'channel-before.local.json')) -ceq
            $ExpectedChannelPolicySha256) 'Retained rollback bytes missing.'
        $passed++
    }
    New-Fixture
    $empty = $beforeText | ConvertFrom-Json
    $empty.standaloneModuleTargets = @()
    Write-Json $policyPath $empty
    $ExpectedChannelPolicySha256 = Hash-File $policyPath
    Approve-Plan
    $result = Invoke-StandaloneTargetAddition
    Assert-Test ($result.outcome -ceq 'installed') 'First target addition failed.'
    $passed++

    foreach ($bad in @('stale-plan', 'source-drift', 'existing-binding-drift', 'duplicate-target',
        'unbound-source', 'extra-field', 'existing-directory', 'partial-approval', 'json-alias', 'incomplete-journal')) {
        New-Fixture
        Approve-Plan
        $manifest = Get-Content -LiteralPath $StandaloneModuleTargetsPath -Raw | ConvertFrom-Json
        switch ($bad) {
            'stale-plan' { $ExpectedAdditionPlanSha256 = 'a' * 64 }
            'source-drift' { [IO.File]::AppendAllText($manifest.targets[0].adapterPath, '# changed') }
            'existing-binding-drift' {
                $p = $beforeText | ConvertFrom-Json
                [IO.File]::AppendAllText($p.standaloneModuleTargets[0].approvalRunnerPath, 'changed')
            }
            'duplicate-target' { $manifest.targets[0].targetId = 'saef-existing' }
            'unbound-source' { $manifest.targets[0].PSObject.Properties.Remove('expectedAdapterSha256') }
            'extra-field' { $manifest.targets[0] | Add-Member -NotePropertyName unexpected -NotePropertyValue 'reject' }
            'existing-directory' { $null = New-Item -ItemType Directory -Path (Join-Path $InstallRoot 'standalone-modules/saef-new') }
            'partial-approval' {
                $p = $beforeText | ConvertFrom-Json
                $p.standaloneModuleTargets[0].PSObject.Properties.Remove('expectedApprovalPolicySha256')
                Write-Json $policyPath $p
                $ExpectedChannelPolicySha256 = Hash-File $policyPath
            }
            'json-alias' { }
            'incomplete-journal' { $null = New-Item -ItemType Directory -Path (Join-Path $InstallRoot 'target-additions/incomplete') -Force }
        }
        if ($bad -in @('duplicate-target', 'unbound-source', 'extra-field')) {
            Write-Json $StandaloneModuleTargetsPath $manifest
            $ExpectedTargetManifestSha256 = Hash-File $StandaloneModuleTargetsPath
        }
        if ($bad -eq 'json-alias') {
            [IO.File]::WriteAllText($StandaloneModuleTargetsPath, '{"formatVersion":1,"FormatVersion":1,"targets":[]}')
            $ExpectedTargetManifestSha256 = Hash-File $StandaloneModuleTargetsPath
        }
        $result = Invoke-StandaloneTargetAddition
        Assert-Test ($result.exitCode -eq 10 -and -not $result.details.mutationAttempted) ('Accepted ' + $bad)
        Assert-Test ((Hash-File $policyPath) -ceq $ExpectedChannelPolicySha256) ('Mutation on ' + $bad)
        $passed++
    }
    # Controlled failure of a dependency AFTER the atomic policy publication.
    New-Fixture
    Approve-Plan
    $script:injected = $false
    function Assert-AdditionBindings { param($Targets)
        & $originalBindingCheck $Targets
        if (-not $script:injected -and @($Targets).Count -eq 2) {
            $script:injected = $true
            throw 'Synthetic post-publication failure.'
        }
    }
    $result = Invoke-StandaloneTargetAddition
    Assert-Test ($injected -and $result.exitCode -eq 20 -and $result.details.rollbackSucceeded) 'Atomic rollback failed.'
    Assert-Unchanged
    Assert-Test (Test-Path -LiteralPath (Join-Path $result.details.evidenceRoot 'target/adapter.ps1')) 'Failed target not retained.'
    Set-Item Function:Assert-AdditionBindings $originalBindingCheck
    $passed++

    # A concurrent external policy change must never be overwritten by rollback.
    New-Fixture
    Approve-Plan
    $script:injected = $false
    function Assert-AdditionBindings { param($Targets)
        & $originalBindingCheck $Targets
        if (-not $script:injected -and @($Targets).Count -eq 2) {
            $script:injected = $true
            [IO.File]::AppendAllText($policyPath, ' ')
            $script:externalHash = Hash-File $policyPath
            throw 'Synthetic intervening policy change.'
        }
    }
    $result = Invoke-StandaloneTargetAddition
    Assert-Test ($injected -and $result.exitCode -eq 20 -and -not $result.details.rollbackSucceeded) 'Intervening change not detected.'
    Assert-Test ((Hash-File $policyPath) -ceq $externalHash) 'Intervening change overwritten.'
    Set-Item Function:Assert-AdditionBindings $originalBindingCheck
    $passed++

    # Original CLI, exact production source and checksum checks, bounded child helper.
    New-Fixture
    $StatusPath = Join-Path $fixture 'bootstrap-status.local.json'
    $args = @('-DeploymentUser', $DeploymentUser, '-AddStandaloneModuleTarget', '-InstallRoot', $InstallRoot,
        '-StandaloneModuleTargetsPath', $StandaloneModuleTargetsPath,
        '-ExpectedChannelPolicySha256', $ExpectedChannelPolicySha256,
        '-ExpectedTargetManifestSha256', $ExpectedTargetManifestSha256, '-StatusPath', $StatusPath)
    foreach ($step in @('preflight', 'apply', 'legacy-guard')) {
        $childArgs = $args
        if ($step -eq 'preflight') { $childArgs += '-PreflightOnly' }
        elseif ($step -eq 'apply') {
            $preflightStatus = Get-Content -LiteralPath $StatusPath -Raw | ConvertFrom-Json
            $childArgs += @('-ExpectedAdditionPlanSha256', $preflightStatus.additionPlanSha256)
        } else {
            $childArgs = @('-DeploymentUser', $DeploymentUser, '-PreflightOnly', '-InstallRoot', $InstallRoot, '-StatusPath', $StatusPath)
        }
        $child = Invoke-SaefPowerShellChildProcess -ScriptPath $initializerPath -ExpectedScriptSha256 (Hash-File $initializerPath) `
            -Arguments $childArgs -TimeoutSeconds 60 -MaximumOutputBytes 65536
        $expectedExit = $(if ($step -eq 'legacy-guard') { 10 } else { 0 })
        Assert-Test ($child.terminationReason -ceq 'exited' -and $child.exitCode -eq $expectedExit) `
            ($step + ': ' + [Text.Encoding]::UTF8.GetString($child.standardError) + (Get-Content $StatusPath -Raw))
        $passed++
    }
    New-Fixture
    $StatusPath = Join-Path $fixture 'bootstrap-status.local.json'
    $args = @('-DeploymentUser', $DeploymentUser, '-AddStandaloneModuleTarget', '-PreflightOnly', '-InstallRoot', $InstallRoot,
        '-StandaloneModuleTargetsPath', $StandaloneModuleTargetsPath,
        '-ExpectedChannelPolicySha256', $ExpectedChannelPolicySha256,
        '-ExpectedTargetManifestSha256', $ExpectedTargetManifestSha256, '-StatusPath', $StatusPath)
    $heldMutex = [Threading.Mutex]::new($false, 'Global\SAEF.DeploymentChannel')
    Assert-Test ($heldMutex.WaitOne(0)) 'Cannot acquire synthetic contention lock.'
    try {
        $child = Invoke-SaefPowerShellChildProcess -ScriptPath $initializerPath -ExpectedScriptSha256 (Hash-File $initializerPath) `
            -Arguments $args -TimeoutSeconds 60 -MaximumOutputBytes 65536
        Assert-Test ($child.exitCode -eq 10) 'Concurrent channel operation accepted.'
        Assert-Unchanged
        $passed++
    } finally { $heldMutex.ReleaseMutex(); $heldMutex.Dispose() }
    $args[-1] = $policyPath
    $child = Invoke-SaefPowerShellChildProcess -ScriptPath $initializerPath -ExpectedScriptSha256 (Hash-File $initializerPath) `
        -Arguments $args -TimeoutSeconds 60 -MaximumOutputBytes 65536
    Assert-Test ($child.exitCode -eq 10) 'Unsafe status destination accepted.'
    Assert-Unchanged
    $passed++
    Write-Output ('Target addition Windows qualification passed: ' + $passed + ' scenarios, 3 cultures, parser errors 0.')
} finally {
    [Threading.Thread]::CurrentThread.CurrentCulture = $savedCulture
    # Only the newly created synthetic scratch tree; no installation or retained live evidence.
    if ($scratch.StartsWith([IO.Path]::GetTempPath(), [StringComparison]::OrdinalIgnoreCase) -and
        (Split-Path -Leaf $scratch) -cmatch '^saef-addition-test-[a-f0-9]{32}$') {
        Remove-Item -LiteralPath $scratch -Recurse -Force
    }
}
