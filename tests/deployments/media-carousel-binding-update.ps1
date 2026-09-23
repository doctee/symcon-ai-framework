# Real Windows filesystem/ACL/single-pointer transaction. Runtime reads are
# substituted only at their explicit boundary; no live Symcon or services.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$windows = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
foreach ($path in @((Join-Path $windows 'Initialize-SaefDeploymentChannel.ps1'),
    (Join-Path $windows 'adapters/Start-SaefMediaCarouselSchemaPackage.ps1'),
    (Join-Path $windows 'adapters/Update-SaefMediaCarouselBinding.ps1'))) {
    $tokens = $null; $errors = $null
    $ast = [Management.Automation.Language.Parser]::ParseFile($path, [ref] $tokens, [ref] $errors)
    if (@($errors).Count) { throw ($errors | Out-String) }
    foreach ($fn in @($ast.FindAll({ param($n)
        $n -is [Management.Automation.Language.FunctionDefinitionAst]
    }, $false))) { . ([scriptblock]::Create($fn.Extent.Text)) }
}
Assert-Elevated | Out-Null
. (Join-Path $windows 'SaefChildProcess.ps1')
function Assert-Test { param([bool] $Ok, [string] $Message) if (-not $Ok) { throw $Message } }
function Assert-UpdateRuntime {
    $script:runtimeCalls++
    if ($script:scenario -ceq 'postflight-failure' -and $script:runtimeCalls -eq 2) { throw 'Synthetic postflight failure.' }
    if ($script:scenario -ceq 'prepublish-failure') { throw 'Synthetic runtime drift.' }
    if ($script:scenario -ceq 'external-drift' -and $script:runtimeCalls -eq 2) {
        [IO.File]::WriteAllText($channelPath, 'external edit'); throw 'Synthetic external drift.'
    }
}
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('saef-binding-test-' + [guid]::NewGuid().ToString('N'))
$null = [IO.Directory]::CreateDirectory($scratch)
Set-RestrictedAcl $scratch '*S-1-5-32-544' '(OI)(CI)F'
$saved = [Threading.Thread]::CurrentThread.CurrentCulture
$utf8 = [Text.UTF8Encoding]::new($false)
$passed = 0
try {
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
        foreach ($scenario in @('success', 'postflight-failure', 'prepublish-failure', 'external-drift', 'existing-generation', 'unrelated-change')) {
            $root = Join-Path $scratch ($culture + '-' + $scenario)
            $null = [IO.Directory]::CreateDirectory($root)
            $channelPath = Join-Path $root 'channel.local.json'
            $generation = Join-Path $root 'generation'
            $oldAdapter = Join-Path $root 'old.ps1'; $oldPolicy = Join-Path $root 'old.local.json'
            [IO.File]::WriteAllText($oldAdapter, '# old'); [IO.File]::WriteAllText($oldPolicy, '{}')
            $a = Get-BytesSha256 ([IO.File]::ReadAllBytes($oldAdapter))
            $p = Get-BytesSha256 ([IO.File]::ReadAllBytes($oldPolicy))
            $old = [ordered]@{ formatVersion = 1; futureField = @{ values = @('preserved', $true, 17) }
                standaloneModuleTargets = @(
                    @{ targetId = 'saef-other'; libraryGuid = '{11111111-1111-1111-1111-111111111111}'
                        adapterPath = $oldAdapter; expectedAdapterSha256 = $a; adapterPolicyPath = $oldPolicy; expectedAdapterPolicySha256 = $p
                        approvalRunnerPath = $oldAdapter; expectedApprovalRunnerSha256 = $a
                        approvalPolicyPath = $oldPolicy; expectedApprovalPolicySha256 = $p },
                    @{ targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'
                        libraryGuid = '{22222222-2222-2222-2222-222222222222}'
                        adapterPath = $oldAdapter; expectedAdapterSha256 = $a; adapterPolicyPath = $oldPolicy; expectedAdapterPolicySha256 = $p }) }
            $before = $utf8.GetBytes(($old | ConvertTo-Json -Depth 20))
            [IO.File]::WriteAllBytes($channelPath, $before); Set-RestrictedFileAcl $channelPath
            $acl = (Get-Acl -LiteralPath $channelPath).Sddl
            $candidateAdapter = $utf8.GetBytes('# new'); $candidatePolicy = $utf8.GetBytes('{"new":true}')
            $new = ConvertFrom-AdditionJson $before
            $new.standaloneModuleTargets[1].adapterPath = Join-Path $generation 'adapter.ps1'
            $new.standaloneModuleTargets[1].adapterPolicyPath = Join-Path $generation 'adapter-policy.local.json'
            $new.standaloneModuleTargets[1].expectedAdapterSha256 = Get-BytesSha256 $candidateAdapter
            $new.standaloneModuleTargets[1].expectedAdapterPolicySha256 = Get-BytesSha256 $candidatePolicy
            if ($scenario -ceq 'unrelated-change') { $new.futureField.values[0] = 'changed' }
            $after = $utf8.GetBytes(($new | ConvertTo-Json -Depth 20))
            $result = [ordered]@{ bindingMutationAttempted = $false; rollbackSucceeded = $null }
            $runtimeCalls = 0; $evidence = $null; $threw = $false
            if ($scenario -ceq 'existing-generation') { $null = [IO.Directory]::CreateDirectory($generation) }
            try {
                Assert-UpdatePreservation (ConvertFrom-AdditionJson $before) $new $generation `
                    (Get-BytesSha256 $candidateAdapter) (Get-BytesSha256 $candidatePolicy)
                Publish-UpdateGeneration $channelPath $generation $before $after $candidateAdapter $candidatePolicy
            } catch { $threw = $true }
            Assert-Test ($threw -eq ($scenario -cne 'success')) ('Unexpected outcome: ' + $scenario)
            $current = [IO.File]::ReadAllBytes($channelPath)
            if ($scenario -ceq 'success') {
                Assert-Test ((Get-BytesSha256 $current) -ceq (Get-BytesSha256 $after)) 'Publish differs.'
                Assert-AdditionBindings (ConvertFrom-AdditionJson $current).standaloneModuleTargets
            } elseif ($scenario -ceq 'external-drift') {
                Assert-Test ($utf8.GetString($current) -ceq 'external edit' -and $result.rollbackSucceeded -eq $false) 'External edit overwritten.'
            } else {
                Assert-Test ((Get-BytesSha256 $current) -ceq (Get-BytesSha256 $before)) 'Original channel not preserved.'
            }
            Assert-Test ((Get-Acl -LiteralPath $channelPath).Sddl -ceq $acl) 'ACL changed.'
            Assert-Test ((Get-BytesSha256 ([IO.File]::ReadAllBytes($oldAdapter))) -ceq $a) 'Old adapter changed.'
            Assert-Test ((Get-BytesSha256 ([IO.File]::ReadAllBytes($oldPolicy))) -ceq $p) 'Old policy changed.'
            if ($scenario -ceq 'postflight-failure') { Assert-Test ($result.rollbackSucceeded -eq $true) 'Rollback unproved.' }
            $passed++
        }
    }
    Add-Type -AssemblyName System.IO.Compression
    $zipPath = Join-Path $scratch 'binding.zip'
    $stream = [IO.File]::Create($zipPath)
    $zip = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create, $true)
    try {
        foreach ($name in @('binding-plan.local.json', 'candidate-policy.local.json', 'candidate-channel.local.json',
            'windows/Initialize-SaefDeploymentChannel.ps1', 'windows/SaefChildProcess.ps1',
            'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1', 'windows/adapters/Update-SaefMediaCarouselBinding.ps1')) {
            $bytes = $utf8.GetBytes('{}')
            if ($name -ceq 'windows/adapters/Update-SaefMediaCarouselBinding.ps1') {
                $bytes = $utf8.GetBytes(@'
param($PlanPath, $ExpectedPlanSha256, $Operation, $Confirmation)
if ($Operation -cne 'install' -or $Confirmation -cne 'update-saef-media-carousel-binding') { exit 10 }
@{formatVersion=1;exitCode=0;outcome='synthetic-binding-launch'} | ConvertTo-Json
exit 0
'@)
            } elseif ($name.StartsWith('windows/')) { $bytes = [IO.File]::ReadAllBytes((Join-Path $windows $name.Substring(8))) }
            $entry = $zip.CreateEntry($name).Open()
            try { $entry.Write($bytes, 0, $bytes.Length) } finally { $entry.Dispose() }
        }
    } finally { $zip.Dispose(); $stream.Dispose() }
    $hash = Get-BytesSha256 ([IO.File]::ReadAllBytes($zipPath))
    $expanded = Expand-SchemaPackage $zipPath $hash binding
    Assert-Test (Test-Path (Join-Path $expanded.root 'binding-plan.local.json')) 'Binding extraction missing.'
    foreach ($bad in @('profile', 'hash')) {
        $rejected = $false
        try {
            if ($bad -ceq 'profile') { $null = Expand-SchemaPackage $zipPath $hash schema }
            else { $null = Expand-SchemaPackage $zipPath ('a' * 64) binding }
        } catch { $rejected = $true }
        Assert-Test $rejected 'Wrong binding profile/hash accepted.'
    }
    $launcher = Join-Path $windows 'adapters/Start-SaefMediaCarouselSchemaPackage.ps1'
    $child = Invoke-SaefPowerShellChildProcess -ScriptPath $launcher `
        -ExpectedScriptSha256 (Get-BytesSha256 ([IO.File]::ReadAllBytes($launcher))) `
        -Arguments @('-ZipPath', $zipPath, '-ExpectedZipSha256', $hash, '-PackageKind', 'binding') `
        -TimeoutSeconds 90 -MaximumOutputBytes 65536
    $output = $utf8.GetString($child.standardOutput)
    Assert-Test ($child.terminationReason -ceq 'exited' -and $child.exitCode -eq 0 -and
        ($output | ConvertFrom-Json).outcome -ceq 'synthetic-binding-launch') ('Binding launcher failed: ' + $output)
    Write-Output ('PASS: MediaCarousel binding update: ' + $passed + ' Windows transaction cases / 3 cultures; binding extraction and launcher passed.')
} finally {
    [Threading.Thread]::CurrentThread.CurrentCulture = $saved
    if ((Split-Path -Leaf $scratch) -cmatch '^saef-binding-test-[a-f0-9]{32}$') { Remove-Item -LiteralPath $scratch -Recurse -Force }
}
