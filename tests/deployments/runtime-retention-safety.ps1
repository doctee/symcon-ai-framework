[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$consumer = Join-Path $PSScriptRoot '../../deployments/symcon/windows/Invoke-SaefDeploymentRetentionCleanup.ps1'
$fixtureRoot = Join-Path ([IO.Path]::GetTempPath()) ('saef-retention-safety-' + [Guid]::NewGuid().ToString('N'))
[IO.Directory]::CreateDirectory($fixtureRoot) | Out-Null

function Assert-Test {
    param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}

function Test-Case {
    param([string] $Name, [string] $ReferenceLocation, [string] $Source, [string] $Expected, [switch] $Apply)
    $base = Join-Path $fixtureRoot $Name
    $scripts = Join-Path $base 'scripts'
    $state = Join-Path $scripts '.saef-deployments'
    $filesets = Join-Path $scripts '.saef-filesets'
    $modules = Join-Path $base 'modules'
    foreach ($path in @($scripts, $state, $filesets, $modules)) {
        [IO.Directory]::CreateDirectory($path) | Out-Null
    }
    foreach ($packageName in @('saef-retained', 'saef-staged')) {
        $deployment = Join-Path $state $packageName
        $fileset = Join-Path $filesets $packageName
        [IO.Directory]::CreateDirectory($deployment) | Out-Null
        [IO.Directory]::CreateDirectory($fileset) | Out-Null
        @{ deploymentId = $packageName; targetDirectoryName = $packageName } | ConvertTo-Json |
            Set-Content -LiteralPath (Join-Path $deployment 'deployment.json') -Encoding UTF8
        @{ phase = 'stage'; outcome = 'staged'; activationAttempted = $false } | ConvertTo-Json |
            Set-Content -LiteralPath (Join-Path $deployment 'status.json') -Encoding UTF8
        'immutable runtime bytes' | Set-Content -LiteralPath (Join-Path $fileset 'runtime.php') -Encoding UTF8
    }
    $sourcePath = Join-Path $base $ReferenceLocation
    [IO.Directory]::CreateDirectory((Split-Path -Parent $sourcePath)) | Out-Null
    $Source | Set-Content -LiteralPath $sourcePath -Encoding UTF8
    $policy = Join-Path $base 'policy.json'
    $plan = Join-Path $base 'plan.json'
    $status = Join-Path $base 'status.json'
    @{ scriptsRoot = $scripts; managedFilesetRoot = $filesets; stateRoot = $state;
        activeBootstrapRelativePath = 'System.Locals.ips.php' } | ConvertTo-Json |
        Set-Content -LiteralPath $policy -Encoding UTF8
    @{ formatVersion = 1; expectedDeploymentCount = 2; expectedFilesetCount = 2;
        candidates = @(@{ deployment = 'saef-staged'; fileset = 'saef-staged' });
        protectedDeployments = @('saef-retained'); protectedFilesets = @('saef-retained') } |
        ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $plan -Encoding UTF8
    $before = @{}
    foreach ($file in @(Get-ChildItem -LiteralPath $scripts -File -Recurse -Force)) {
        $before[$file.FullName] = (Get-FileHash -LiteralPath $file.FullName).Hash
    }
    $arguments = @('-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $consumer,
        '-PolicyPath', $policy, '-PlanPath', $plan, '-StatusPath', $status)
    if ($Apply) { $arguments += '-Apply' }
    & powershell.exe @arguments
    Assert-Test ($LASTEXITCODE -eq 10) "$Name must reject or require review, not pass"
    $result = Get-Content -LiteralPath $status -Raw | ConvertFrom-Json
    Assert-Test (-not $result.mutationAttempted) "$Name must not attempt cleanup"
    if ($Expected -eq 'review_required') {
        Assert-Test ($result.outcome -eq 'review_required' -and -not $result.deletionEligible -and
            -not $result.liveConsumerClosureVerified) "$Name must explicitly remain unverified"
    } else {
        Assert-Test ($result.outcome -eq 'failed' -and $result.errorMessage.Contains($Expected)) "$Name wrong failure"
    }
    foreach ($path in $before.Keys) {
        Assert-Test (Test-Path -LiteralPath $path -PathType Leaf) "$Name deleted a file"
        Assert-Test ((Get-FileHash -LiteralPath $path).Hash -eq $before[$path]) "$Name changed a file"
    }
    Write-Output "PASS: $Name"
}

Test-Case 'direct-staged-wrapper' 'scripts/wrapper.php' '<?php require "saef-staged/runtime.php";' 'Runtime file references'
Test-Case 'retained-fileset-dependency' 'scripts/.saef-filesets/saef-retained/consumer.php' '<?php require "../saef-staged/runtime.php";' 'Runtime file references'
Test-Case 'module-dependency' 'modules/example/module.php' '<?php require "saef-staged/runtime.php";' 'Runtime file references'
Test-Case 'assembled-path-unknown' 'scripts/wrapper.php' '<?php $p = "saef-" . "staged"; require $p;' 'review_required'
Test-Case 'no-text-hit-not-proof' 'scripts/wrapper.php' '<?php require getenv("RUNTIME_PATH");' 'review_required'
Test-Case 'candidate-self-reference' 'scripts/.saef-filesets/saef-staged/self.php' 'saef-staged' 'review_required'
Test-Case 'apply-always-fails-closed' 'scripts/wrapper.php' '<?php // no visible dependency' 'Runtime retention apply is disabled' -Apply
# Synthetic evidence is retained in the disposable runner temp directory.
Write-Output "PASS: runtime retention safety; fixtures retained at $fixtureRoot"
