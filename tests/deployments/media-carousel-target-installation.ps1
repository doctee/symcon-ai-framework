# Synthetic qualification of the complete operator entry point; no live RPC.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) { throw 'Windows PowerShell 5.1 required.' }
$windowsRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
$imports = @(
    @{ path = (Join-Path $windowsRoot 'Initialize-SaefDeploymentChannel.ps1'); names = @(
        'Set-RestrictedAcl', 'Set-RestrictedFileAcl', 'Assert-Elevated') },
    @{ path = (Join-Path $PSScriptRoot 'channel-target-addition.ps1'); names = @(
        'New-Fixture', 'Write-Json', 'Hash-File', 'Assert-Test') },
    @{ path = (Join-Path $windowsRoot 'adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1'); names = @(
        'Get-Sha256', 'Get-TextSha256', 'Assert-SafeDirectoryTree', 'Get-DirectoryPackageIdentity') }
)
foreach ($import in $imports) {
    $tokens = $null; $errors = $null
    $ast = [Management.Automation.Language.Parser]::ParseFile($import.path, [ref] $tokens, [ref] $errors)
    if (@($errors).Count) { throw ($errors | Out-String) }
    foreach ($name in $import.names) {
        $fn = @($ast.FindAll({ param($n)
            $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq $name
        }, $false))
        if ($fn.Count -ne 1) { throw ('Missing fixture function: ' + $name) }
        . ([scriptblock]::Create($fn[0].Extent.Text))
    }
}
. (Join-Path $windowsRoot 'SaefChildProcess.ps1')
Assert-Elevated
$DeploymentUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name.Split('\')[-1]
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('saef-target-install-test-' + [Guid]::NewGuid().ToString('N'))
$null = [IO.Directory]::CreateDirectory($scratch)
Set-RestrictedAcl $scratch ('*' + $sid) '(OI)(CI)F'
$fixtureNumber = 0
$passed = 0
$savedCulture = [Threading.Thread]::CurrentThread.CurrentCulture
function Invoke-Coordinator { param([string] $Operation, [int] $ExpectedExit)
    $child = Invoke-SaefPowerShellChildProcess -ScriptPath $coordinator -ExpectedScriptSha256 (Hash-File $coordinator) `
        -Arguments @('-PlanPath', $planPath, '-ExpectedPlanSha256', (Hash-File $planPath),
            '-Operation', $Operation, '-Confirmation', 'install-saef-media-carousel-target') `
        -TimeoutSeconds 180 -MaximumOutputBytes 131072
    $output = [Text.Encoding]::UTF8.GetString($child.standardOutput)
    Assert-Test ($child.terminationReason -ceq 'exited' -and $child.exitCode -eq $ExpectedExit) `
        ('Coordinator failed: ' + $output + [Text.Encoding]::UTF8.GetString($child.standardError))
    $record = $output | ConvertFrom-Json
    Assert-Test (-not $record.moduleActivationAttempted -and -not $record.symconRpcContactAttempted -and
        -not $record.serviceRestartAttempted) 'Unexpected live action.'
    $script:passed++
    return $record
}
try {
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
        New-Fixture
        $package = Join-Path $fixture 'package'
        $null = [IO.Directory]::CreateDirectory($package)
        Set-RestrictedAcl $package ('*' + $sid) '(OI)(CI)F'
        Copy-Item -LiteralPath $windowsRoot -Destination (Join-Path $package 'windows') -Recurse
        $bundle = Join-Path $package 'windows'
        $coordinator = Join-Path $bundle 'adapters/Install-SaefMediaCarouselTarget.ps1'
        $module = Join-Path $fixture 'module'
        $null = [IO.Directory]::CreateDirectory((Join-Path $module 'MediaCarousel'))
        $stateParent = Join-Path $fixture 'adapter-states'
        $null = [IO.Directory]::CreateDirectory($stateParent)
        Set-RestrictedAcl $stateParent ('*' + $sid) '(OI)(CI)F'
        $stateRoot = Join-Path $stateParent 'media-carousel'
        $script:policy = Get-Content (Join-Path $bundle 'adapters/media-carousel-adapter-policy.example.json') -Raw | ConvertFrom-Json
        $script:policy.activeModulePath = $module
        $script:policy.adapterStateRoot = $stateRoot
        $script:policy.moduleControlInstanceId = 12345
        $script:policy.moduleControlModuleGuid = '{11111111-1111-1111-1111-111111111111}'
        $script:policy.expectedInstances = @(@{ instanceId = 23456; configurationSha256 = ('a' * 64) })
        $script:policy | Add-Member -NotePropertyName quiescenceTimeoutSeconds -NotePropertyValue 1
        Write-Json (Join-Path $module 'library.json') @{ id = $script:policy.libraryGuid
            name = $script:policy.libraryName; url = $script:policy.libraryUrl }
        Write-Json (Join-Path $module 'MediaCarousel/module.json') @{ id = $script:policy.moduleGuid; name = 'MediaCarousel' }
        $script:policy.expectedActivePackageIdentitySha256 = Get-DirectoryPackageIdentity $module
        $adapterPolicy = Join-Path $package 'media-carousel-adapter-policy.local.json'
        Write-Json $adapterPolicy $script:policy
        $plan = [ordered]@{ formatVersion = 1; targetId = 'saef-media-carousel'; deploymentUser = $DeploymentUser
            expectedDeploymentSid = $sid; installRoot = $InstallRoot; channelPolicySha256 = $ExpectedChannelPolicySha256
            adapterPolicySha256 = (Hash-File $adapterPolicy); sourceHashes = @{
                channel = (Hash-File (Join-Path $bundle 'Initialize-SaefDeploymentChannel.ps1'))
                state = (Hash-File (Join-Path $bundle 'adapters/Initialize-SaefOwnTracksPositionMapAdapterState.ps1'))
                adapter = (Hash-File (Join-Path $bundle 'adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1'))
                launcher = (Hash-File (Join-Path $bundle 'SaefChildProcess.ps1')) } }
        $plan.sourceHashes.checksums = Hash-File (Join-Path $bundle 'SHA256SUMS')
        $planPath = Join-Path $package 'installation-plan.local.json'
        Write-Json $planPath $plan
        $record = Invoke-Coordinator preflight 0
        Assert-Test ($record.outcome -ceq 'ready' -and -not (Test-Path -LiteralPath $stateRoot)) 'Preflight mutated state.'
        Assert-Test ((Hash-File $policyPath) -ceq $ExpectedChannelPolicySha256) 'Preflight changed channel.'
        $plan.sourceHashes.state = 'a' * 64
        Write-Json $planPath $plan
        $record = Invoke-Coordinator install 10
        Assert-Test (-not $record.stateProvisioningAttempted) 'Unbound source was attempted.'
        $plan.sourceHashes.state = Hash-File (Join-Path $bundle 'adapters/Initialize-SaefOwnTracksPositionMapAdapterState.ps1')
        Write-Json $planPath $plan
        $record = Invoke-Coordinator install 0
        Assert-Test ($record.outcome -ceq 'installed' -and (Test-Path -LiteralPath $stateRoot)) 'Installation incomplete.'
        $after = Get-Content -LiteralPath $policyPath -Raw | ConvertFrom-Json
        Assert-Test ($after.standaloneModuleTargets.Count -eq 2) 'Target not added.'
        Assert-Test (($after.standaloneModuleTargets[0] | ConvertTo-Json -Depth 50 -Compress) -ceq
            (($beforeText | ConvertFrom-Json).standaloneModuleTargets[0] | ConvertTo-Json -Depth 50 -Compress)) 'Existing bindings changed.'
        Assert-Test ((Get-DirectoryPackageIdentity $module) -ceq $script:policy.expectedActivePackageIdentitySha256) 'Module changed.'
        # A stale second install must stop before either live stage.
        $record = Invoke-Coordinator install 10
        Assert-Test (-not $record.stateProvisioningAttempted -and -not $record.targetInstallationAttempted) 'Stale plan executed.'
    }
    Write-Output ('PASS: MediaCarousel target coordinator, ' + $passed + ' real Windows scenarios / 3 cultures.')
} finally {
    [Threading.Thread]::CurrentThread.CurrentCulture = $savedCulture
    if ($scratch -and (Split-Path -Leaf $scratch) -match '^saef-target-install-test-[a-f0-9]{32}$') {
        Remove-Item -LiteralPath $scratch -Recurse -Force
    }
}
