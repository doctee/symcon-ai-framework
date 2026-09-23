# Import only pure production functions; never execute installed runners or RPC.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
    throw 'Windows PowerShell 5.1 required.'
}
$sources = @{
    'Get-ResealTargetArguments' = '../../deployments/symcon/windows/Invoke-SaefScopeBoundApprovalRunner.ps1'
    'Assert-MediaCarouselCodeOnlyBaseline' = '../../deployments/symcon/windows/adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1'
}
foreach ($name in $sources.Keys) {
    $tokens = $null; $errors = $null
    $ast = [Management.Automation.Language.Parser]::ParseFile(
        (Join-Path $PSScriptRoot $sources[$name]), [ref] $tokens, [ref] $errors)
    if (@($errors).Count -ne 0) { throw 'Production source parser failed.' }
    $found = @($ast.FindAll({ param($node)
        $node -is [Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -ceq $name
    }, $false))
    if ($found.Count -ne 1) { throw 'Production function is not unique.' }
    . ([scriptblock]::Create($found[0].Extent.Text))
}
function New-Fixture {
    $instances = @(
        [ordered]@{ instanceId = 101; configurationSha256 = ('a' * 64); name = 'camera' },
        [ordered]@{ instanceId = 102; configurationSha256 = ('b' * 64); name = 'archive' }
    )
    return ([ordered]@{
        policy = @{ expectedInstances = $instances }
        before = @{ instances = $instances }
        after = @{ instances = $instances }
    } | ConvertTo-Json -Depth 20 | ConvertFrom-Json)
}
$cases = 0
$original = [Threading.Thread]::CurrentThread.CurrentCulture
try {
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
        $legacy = @(Get-ResealTargetArguments 'saef-owntracks-position-map' 'saef-owntracks-position-map-v1')
        if (($legacy -join '|') -cne '-Confirmation|reseal-saef-owntracks-position-map-active-identity') {
            throw 'Legacy OwnTracks invocation changed.'
        }
        $media = @(Get-ResealTargetArguments 'saef-media-carousel' 'saef-media-carousel-v1')
        if (($media -join '|') -cne '-TargetId|saef-media-carousel|-Confirmation|reseal-saef-media-carousel-active-identity') {
            throw 'MediaCarousel invocation differs.'
        }
        $cases += 2
        foreach ($pair in @(
            @('saef-media-carousel', 'saef-owntracks-position-map-v1'),
            @('saef-owntracks-position-map', 'saef-media-carousel-v1'),
            @('SAEF-MEDIA-CAROUSEL', 'saef-media-carousel-v1'),
            @('saef-unknown', 'saef-unknown-v1'), @('', '')
        )) {
            $rejected = $false
            try { $null = Get-ResealTargetArguments $pair[0] $pair[1] } catch { $rejected = $true }
            if (-not $rejected) { throw 'Unbound target/profile pair accepted.' }
            $cases++
        }
        $fixture = New-Fixture
        $originalJson = $fixture | ConvertTo-Json -Depth 20 -Compress
        Assert-MediaCarouselCodeOnlyBaseline $fixture.policy $fixture.before $fixture.after
        if (($fixture | ConvertTo-Json -Depth 20 -Compress) -cne $originalJson) { throw 'Validation mutated inputs.' }
        $cases++
        foreach ($mutate in @(
            { param($f) $f.policy | Add-Member NoteProperty configurationTransition $null },
            { param($f) $f.policy | Add-Member NoteProperty CONFIGURATIONTRANSITION @{} },
            { param($f) $f.policy.expectedInstances[0].configurationSha256 = ('c' * 64) },
            { param($f) $f.policy.expectedInstances[0].configurationSha256 = '' },
            { param($f) $f.policy.expectedInstances[0].instanceId = 0 },
            { param($f) $f.policy.expectedInstances[0].instanceId = '101' },
            { param($f) $f.policy.expectedInstances[0].instanceId = 101.1 },
            { param($f) $f.policy.expectedInstances[0].instanceId = [long]::MaxValue },
            { param($f) $f.policy.expectedInstances[1].instanceId = 101 },
            { param($f) $f.before.instances[1].instanceId = 101; $f.after.instances[1].instanceId = 101 },
            { param($f) $f.after.instances[0].name = 'foreign-metadata' },
            { param($f) $f.before.instances = @($f.before.instances[0]); $f.after.instances = @($f.after.instances[0]) },
            { param($f) $f.policy.expectedInstances = @(); $f.before.instances = @(); $f.after.instances = @() }
        )) {
            $fixture = New-Fixture
            & $mutate $fixture
            $originalJson = $fixture | ConvertTo-Json -Depth 20 -Compress
            $rejected = $false
            try { Assert-MediaCarouselCodeOnlyBaseline $fixture.policy $fixture.before $fixture.after } catch { $rejected = $true }
            if (-not $rejected -or ($fixture | ConvertTo-Json -Depth 20 -Compress) -cne $originalJson) {
                throw 'Invalid baseline accepted or validation mutated inputs.'
            }
            $cases++
        }
    }
} finally { [Threading.Thread]::CurrentThread.CurrentCulture = $original }
Write-Output "PASS: $cases reseal contract cases; no live mutation."
