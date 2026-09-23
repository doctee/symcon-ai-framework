# Dot-sourced by the existing Windows schema qualification fixture. Do not
# duplicate its protected-tree, DPAPI, process-launch or RPC infrastructure.
$settingsConfigs = @{
    '11111' = '{"FitMode":"cover","MediaItems":"[{\"Title\":\"T\u00fcr\"}]","ShowFitToggle":false}'
    '22222' = '{"FitMode":"contain","MediaItems":"[]","ShowFitToggle":false}'
}
$script:policy.expectedInstances = @(
    @{ instanceId = 11111; configurationSha256 = Get-TextSha256 $settingsConfigs['11111'] },
    @{ instanceId = 22222; configurationSha256 = Get-TextSha256 $settingsConfigs['22222'] })
Write-Json $policyPath $script:policy
$settingsChannel = [Text.Encoding]::UTF8.GetString($bindingBefore) | ConvertFrom-Json
$settingsChannel.standaloneModuleTargets[0].adapterPath = $adapter
$settingsChannel.standaloneModuleTargets[0].expectedAdapterSha256 = Hash-File $adapter
$settingsChannel.standaloneModuleTargets[0].expectedAdapterPolicySha256 = Hash-File $policyPath
Write-Json $channel $settingsChannel
Set-RestrictedFileAcl $channel
foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
    foreach ($scenario in @('fit-success', 'fit-preflight', 'fit-response-lost', 'fit-apply-fails',
        'fit-drift', 'fit-zero', 'fit-outside', 'fit-duplicate', 'fit-stale', 'fit-package', 'fit-enabled', 'fit-multi-fails')) {
        $settingsPlan = @{ formatVersion = 1; targetId = 'saef-media-carousel'
            deploymentUser = $user; expectedDeploymentSid = $sid; installRoot = $fixture
            channelSha256 = Hash-File $channel; installedPolicySha256 = Hash-File $policyPath
            sourceHashes = $plan.sourceHashes; activePackageIdentitySha256 = $script:policy.expectedActivePackageIdentitySha256
            scopeRootId = 234; ancestors = @(@{ instanceId = 234; parentId = 345 })
            instances = $script:policy.expectedInstances; targets = @(@{ instanceId = 11111; parentId = 234 }) }
        $actual = $settingsConfigs.Clone()
        switch ($scenario) {
            'fit-zero' { $settingsPlan.targets[0].instanceId = 0 }
            'fit-outside' { $settingsPlan.scopeRootId = 999 }
            'fit-duplicate' { $settingsPlan.targets += $settingsPlan.targets[0] }
            'fit-stale' { $actual['11111'] += ' ' }
            'fit-package' { $settingsPlan.activePackageIdentitySha256 = 'a' * 64 }
            'fit-multi-fails' {
                $actual['33333'] = $settingsConfigs['11111']
                $settingsPlan.instances += @{ instanceId = 33333; configurationSha256 = Get-TextSha256 $actual['33333'] }
                $settingsPlan.targets += @{ instanceId = 33333; parentId = 234 }
            }
            'fit-enabled' {
                $actual['11111'] = $actual['11111'].Replace('false', 'true')
                $settingsPlan.instances = @(
                    @{ instanceId = 11111; configurationSha256 = Get-TextSha256 $actual['11111'] },
                    $script:policy.expectedInstances[1])
            }
        }
        $settingsPath = Join-Path $fixture 'settings-plan.local.json'
        Write-Json $settingsPath $settingsPlan
        $mockPolicy = $script:policy | ConvertTo-Json -Depth 40 | ConvertFrom-Json
        $mockPolicy.expectedInstances = $settingsPlan.instances
        Write-Json $mockPath @{ policy = $mockPolicy; configurations = $actual; scenario = $scenario; logPath = $logPath }
        $operation = if ($scenario -ceq 'fit-preflight') { 'preflight' } else { 'apply' }
        $child = Invoke-SaefPowerShellChildProcess -ScriptPath $wrapper -ExpectedScriptSha256 (Hash-File $wrapper) `
            -Arguments @('-PlanPath', $settingsPath, '-ExpectedPlanSha256', (Hash-File $settingsPath),
                '-FixturePath', $mockPath, '-EntryPath', (Join-Path $bundle 'adapters/Set-SaefMediaCarouselFitToggle.ps1'),
                '-Culture', $culture, '-SettingsOperation', $operation) -TimeoutSeconds 120 -MaximumOutputBytes 65536
        $text = [Text.Encoding]::UTF8.GetString($child.standardOutput)
        $record = $text | ConvertFrom-Json
        $expectedExit = if ($scenario -in @('fit-success', 'fit-preflight')) { 0 }
            elseif ($scenario -in @('fit-response-lost', 'fit-apply-fails', 'fit-drift', 'fit-multi-fails')) { 40 } else { 10 }
        Assert-Test ($child.terminationReason -ceq 'exited' -and $child.exitCode -eq $expectedExit) ('Settings ' + $scenario + ': ' + $text)
        Assert-Test (-not $record.serviceRestartAttempted) 'Settings restarted service.'
        if ($scenario -in @('fit-success', 'fit-response-lost', 'fit-apply-fails', 'fit-drift', 'fit-multi-fails')) {
            $log = Get-Content $logPath -Raw | ConvertFrom-Json
            Assert-Test ($log.reloads -eq 0 -and $log.creates -eq 0 -and $log.deletes -eq 0) 'Settings changed module or objects.'
            Assert-Test ($log.configurations.'22222' -ceq $settingsConfigs['22222']) 'Excluded instance changed.'
            if ($scenario -ceq 'fit-success') {
                Assert-Test ($record.changedInstanceCount -eq 1 -and $record.preservedInstanceCount -eq 1 -and
                    $log.mutations -eq 1 -and $log.applies -eq 1 -and
                    $log.configurations.'11111' -ceq $settingsConfigs['11111'].Replace('"ShowFitToggle":false', '"ShowFitToggle":true')) 'Settings preservation failed.'
            } elseif ($scenario -ceq 'fit-drift') {
                Assert-Test ($record.rollbackSucceeded -eq $false -and $log.mutations -eq 1) 'Foreign drift overwritten.'
            } else {
                Assert-Test ($record.rollbackSucceeded -eq $true -and $log.configurations.'11111' -ceq $settingsConfigs['11111']) ('Settings rollback failed: ' + $scenario + ' ' + $text + ' ' + ($log | ConvertTo-Json -Depth 10 -Compress))
                if ($scenario -ceq 'fit-multi-fails') {
                    Assert-Test (($log.settingsWrites -join ',') -ceq '11111:True,33333:True,33333:False,11111:False' -and
                        $log.configurations.'33333' -ceq $settingsConfigs['11111']) 'Reverse multi-instance rollback failed.'
                }
            }
        } else { Assert-Test (-not $record.productionMutationAttempted) 'Rejected plan reached settings mutation.' }
        Assert-Test ((Hash-File $channel) -ceq $settingsPlan.channelSha256 -and
            (Hash-File $policyPath) -ceq $settingsPlan.installedPolicySha256) 'Settings changed protected binding.'
        $passed++
    }
}

Add-Type -AssemblyName System.IO.Compression
# Real settings launcher dispatch, rejected before any RPC by a stale binding.
$settingsPlan.channelSha256 = 'a' * 64
Write-Json $settingsPath $settingsPlan
$settingsEntries = @('settings-plan.local.json', 'windows/Initialize-SaefDeploymentChannel.ps1',
    'windows/SaefChildProcess.ps1', 'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1',
    'windows/adapters/Set-SaefMediaCarouselFitToggle.ps1')
$settingsZipPath = Join-Path $scratch 'settings.zip'
$file = [IO.File]::Create($settingsZipPath)
$zip = [IO.Compression.ZipArchive]::new($file, [IO.Compression.ZipArchiveMode]::Create, $true)
try {
    foreach ($name in $settingsEntries) {
        $bytes = [IO.File]::ReadAllBytes((Join-Path $fixture $name))
        $stream = $zip.CreateEntry($name).Open()
        try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Dispose() }
    }
} finally { $zip.Dispose(); $file.Dispose() }
$expanded = Expand-SchemaPackage $settingsZipPath (Hash-File $settingsZipPath) settings
foreach ($name in $settingsEntries) {
    Assert-Test ((Hash-File (Join-Path $expanded.root $name)) -ceq (Hash-File (Join-Path $fixture $name))) 'Settings extraction differs.'
}
$passed++
$launch = Invoke-SaefPowerShellChildProcess `
    -ScriptPath (Join-Path $windowsRoot 'adapters/Start-SaefMediaCarouselSchemaPackage.ps1') `
    -ExpectedScriptSha256 (Hash-File (Join-Path $windowsRoot 'adapters/Start-SaefMediaCarouselSchemaPackage.ps1')) `
    -Arguments @('-ZipPath', $settingsZipPath, '-ExpectedZipSha256', (Hash-File $settingsZipPath), '-PackageKind', 'settings') `
    -TimeoutSeconds 60 -MaximumOutputBytes 65536
$launchText = [Text.Encoding]::UTF8.GetString($launch.standardOutput)
$launchResult = $launchText | ConvertFrom-Json
Assert-Test ($launch.terminationReason -ceq 'exited' -and $launch.exitCode -eq 10 -and
    $launchResult.operation -ceq 'enable_fit_toggle' -and -not $launchResult.productionMutationAttempted) ('Settings launcher: ' + $launchText)
$passed++
