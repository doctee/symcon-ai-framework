# Synthetic qualification of production functions. No installed files, RPC or credentials.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
    throw 'Qualification requires Windows PowerShell 5.1.'
}
$adapterPath = Join-Path $PSScriptRoot '../../deployments/symcon/windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1'
$tokens = $null
$errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($adapterPath, [ref] $tokens, [ref] $errors)
if (@($errors).Count -ne 0) { throw 'Adapter parse failed.' }
$functions = @($ast.FindAll({ param($node)
    $node -is [Management.Automation.Language.FunctionDefinitionAst]
}, $false))
foreach ($function in $functions) {
    . ([scriptblock]::Create($function.Extent.Text))
}
function Assert-Test { param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}
function Assert-Rejected { param([scriptblock] $Action)
    $rejected = $false
    try { & $Action } catch { $rejected = $true }
    Assert-Test $rejected 'Invalid transition was accepted.'
}
function Encode-Test { param([string] $Text)
    return [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Text))
}
$source = 'a' * 64
$target = 'b' * 64
$script:manifest = [pscustomobject]@{ deploymentId = 'synthetic-fit-schema' }
$script:packageIdentitySha256 = $target
$before = '{"FitMode":"cover","MediaItems":"[{\"Title\":\"A/B\"}]","LoopSeconds":8}'
$after = '{"FitMode":"cover","ShowFitToggle":false,"MediaItems":"[{\"Title\":\"A/B\"}]","LoopSeconds":8}'
$snapshot = [pscustomobject]@{ instances = @([pscustomobject]@{
    instanceId = 12345; configurationBase64 = (Encode-Test $before)
    configurationSha256 = (Get-TextSha256 $before); objectIdent = 'Synthetic'
    objectName = 'Synthetic'; parentId = 123; position = 1; hidden = $false
    disabled = $false; readOnly = $false; status = 102
}) }
function New-TestPolicy {
    return [pscustomobject]@{
        maximumStateBytes = 65536; expectedActivePackageIdentitySha256 = $source
        moduleGuid = '{41D0C5ED-8331-4B26-A44E-6FDCEC1BC41F}'
        expectedInstances = @($snapshot.instances)
        configurationTransition = [pscustomobject]@{
            kind = 'show-fit-toggle-default-false-v1'; sourcePackageIdentitySha256 = $source
            candidatePackageIdentitySha256 = $target; deploymentId = 'synthetic-fit-schema'
            instances = @([pscustomobject]@{
                instanceId = 12345; configurationBase64 = (Encode-Test $after)
                configurationSha256 = (Get-TextSha256 $after)
            })
        }
    }
}
$savedCulture = [Threading.Thread]::CurrentThread.CurrentCulture
try {
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
        $script:policy = New-TestPolicy
        $candidate = Get-CandidateSnapshot -Snapshot $snapshot
        Assert-Test ($candidate.instances[0].configurationBase64 -ceq (Encode-Test $after)) 'After bytes differ.'
        Assert-Test ($snapshot.instances[0].configurationBase64 -ceq (Encode-Test $before)) 'Original mutated.'
        Assert-FitDefaultAddition -Before '{}' -After '{"ShowFitToggle":false}'
        Assert-FitDefaultAddition -Before '{"A":1}' -After '{"ShowFitToggle":false,"A":1}'
        Assert-FitDefaultAddition -Before '{"A":1}' -After '{"A":1,"ShowFitToggle":false}'
        foreach ($bad in @(
            '{"FitMode":"cover","ShowFitToggle":true}',
            '{"FitMode":"COVER","ShowFitToggle":false}',
            '{"FitMode":"cover","ShowFitToggle":0}',
            '{"FitMode":"cover","ShowFitToggle":"false"}',
            '{"FitMode":"cover","showFitToggle":false}',
            '{"FitMode":"cover","ShowFitToggle":false,"ShowFitToggle":false}',
            '{"FitMode":"cover","ShowFitToggle":false,"showfittoggle":false}',
            '{"ShowFitToggle":false, "FitMode":"cover"}',
            '{"ShowFitToggle":false,"FitMode":"cover","Unexpected":1}',
            '{"ShowFitToggle":false,"FitMode":{"value":"cover"}}'
        )) {
            Assert-Rejected { Assert-FitDefaultAddition -Before '{"FitMode":"cover"}' -After $bad }
        }
        Assert-Rejected { Assert-FitDefaultAddition -Before '{"ShowFitToggle":false}' -After '{"ShowFitToggle":false}' }
        foreach ($field in @('sourcePackageIdentitySha256', 'candidatePackageIdentitySha256', 'deploymentId', 'kind')) {
            $script:policy = New-TestPolicy
            $script:policy.configurationTransition.$field = 'wrong'
            Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        }
        $script:policy = New-TestPolicy
        $script:policy.configurationTransition.instances[0].configurationSha256 = 'c' * 64
        Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        $script:policy = New-TestPolicy
        $script:policy.configurationTransition.instances[0].instanceId = 0
        Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        $script:policy = New-TestPolicy
        $script:policy.configurationTransition.instances[0].instanceId = 23456
        Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        $script:policy = New-TestPolicy
        $script:policy.configurationTransition.instances = @()
        Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        $script:policy = New-TestPolicy
        $script:policy.configurationTransition.instances[0].configurationBase64 = '/w=='
        Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        $script:policy = New-TestPolicy
        $script:policy.maximumStateBytes = 1
        Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        $script:policy = New-TestPolicy
        $script:policy.configurationTransition.instances += $script:policy.configurationTransition.instances[0]
        Assert-Rejected { Get-CandidateSnapshot -Snapshot $snapshot }
        $script:policy = New-TestPolicy
        $script:policy.PSObject.Properties.Remove('configurationTransition')
        Assert-Test ([object]::ReferenceEquals((Get-CandidateSnapshot -Snapshot $snapshot), $snapshot)) 'Legacy path changed.'
    }
} finally { [Threading.Thread]::CurrentThread.CurrentCulture = $savedCulture }

# Exercise the production snapshot comparator and rollback configuration writer with RPC doubles.
$script:policy = New-TestPolicy
$candidate = Get-CandidateSnapshot -Snapshot $snapshot
$script:currentSnapshot = $candidate
function Get-InstanceSnapshot { param([object[]] $ExpectedInstances)
    Assert-Test ($ExpectedInstances[0].configurationSha256 -ceq $script:currentSnapshot.instances[0].configurationSha256) 'Wrong phase baseline.'
    return $script:currentSnapshot.instances
}
Assert-SnapshotPreserved -Snapshot $candidate
Assert-Rejected { Assert-SnapshotPreserved -Snapshot $snapshot }
$script:currentSnapshot.instances[0].objectName = 'synthetic'
$freshCandidate = Get-CandidateSnapshot -Snapshot $snapshot
Assert-Rejected { Assert-SnapshotPreserved -Snapshot $freshCandidate }
$script:currentSnapshot = $snapshot
Assert-SnapshotPreserved -Snapshot $snapshot

$script:wire = $after
$script:mutations = 0
$script:exists = $true
$script:objectType = 1
function Invoke-SymconRpc { param([string] $Method, [object[]] $Parameters)
    switch ($Method) {
        'IPS_InstanceExists' { return $script:exists }
        'IPS_GetInstance' { return [pscustomobject]@{ ModuleInfo = [pscustomobject]@{ ModuleID = $script:policy.moduleGuid } } }
        'IPS_GetObject' { return [pscustomobject]@{ ObjectType = $script:objectType } }
        'IPS_GetConfiguration' { return $script:wire }
        'IPS_SetConfiguration' { $script:wire = $Parameters[1]; $script:mutations++; return }
        'IPS_ApplyChanges' { $script:mutations++; return }
        default { throw 'Unexpected RPC.' }
    }
}
Restore-Configurations -Snapshot $snapshot
Assert-Test ($script:wire -ceq $before -and $script:mutations -eq 2) 'Rollback bytes differ.'
Restore-Configurations -Snapshot $snapshot
Assert-Test ($script:mutations -eq 2) 'Rollback repeated an unchanged mutation.'
$script:exists = $false
Assert-Rejected { Restore-Configurations -Snapshot $snapshot }
Assert-Test ($script:mutations -eq 2) 'Missing instance was mutated.'
$script:exists = $true
$script:objectType = 0
Assert-Rejected { Restore-Configurations -Snapshot $snapshot }
Assert-Test ($script:mutations -eq 2) 'Wrong object type was mutated.'
Write-Output 'PASS: MediaCarousel schema functions, phase preservation and rollback (synthetic, three cultures)'
