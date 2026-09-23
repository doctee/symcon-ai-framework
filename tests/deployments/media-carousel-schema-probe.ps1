# Complete entry-point tests with synthetic RPC; real protected files, DPAPI and PowerShell 5.1.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) { throw 'Windows PowerShell 5.1 required.' }
$windowsRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
$imports = @(
    @{ path = (Join-Path $windowsRoot 'Initialize-SaefDeploymentChannel.ps1'); names = @('Set-RestrictedAcl', 'Assert-Elevated') },
    @{ path = (Join-Path $PSScriptRoot 'channel-target-addition.ps1'); names = @('Write-Json', 'Hash-File', 'Assert-Test') },
    @{ path = (Join-Path $windowsRoot 'adapters/Start-SaefMediaCarouselSchemaPackage.ps1'); names = @('Expand-SchemaPackage') },
    @{ path = (Join-Path $windowsRoot 'adapters/Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1'); names = @(
        'Assert-PlainDirectory', 'Set-ManagedTreeAcl', 'Assert-ManagedRootAcl') },
    @{ path = (Join-Path $windowsRoot 'adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1'); names = @(
        'Get-Sha256', 'Get-TextSha256', 'Assert-SafeDirectoryTree', 'Get-DirectoryPackageIdentity') }
)
foreach ($import in $imports) {
    $tokens = $null; $errors = $null
    $ast = [Management.Automation.Language.Parser]::ParseFile($import.path, [ref] $tokens, [ref] $errors)
    if (@($errors).Count) { throw 'Fixture source parse failed.' }
    foreach ($name in $import.names) {
        $fn = @($ast.FindAll({ param($n)
            $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq $name
        }, $false))
        if ($fn.Count -ne 1) { throw 'Fixture function missing.' }
        . ([scriptblock]::Create($fn[0].Extent.Text))
    }
}
. (Join-Path $windowsRoot 'SaefChildProcess.ps1')
Assert-Elevated | Out-Null
Add-Type -AssemblyName System.Security
$user = [Security.Principal.WindowsIdentity]::GetCurrent().Name.Split('\')[-1]
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('saef-schema-test-' + [guid]::NewGuid().ToString('N'))
$null = [IO.Directory]::CreateDirectory($scratch)
Set-RestrictedAcl $scratch ('*' + $sid) '(OI)(CI)F'
$passed = 0
try {
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        foreach ($scenario in @('success', 'schema-drift', 'zero-id', 'creation-response-lost',
            'cleanup-fails', 'foreign-child', 'production-drift', 'changed-plan',
            'shared-parent', 'parent-delete-child', 'parent-takeover')) {
            $fixture = Join-Path $scratch ($culture + '-' + $scenario)
            $null = [IO.Directory]::CreateDirectory($fixture)
            Copy-Item $windowsRoot (Join-Path $fixture 'windows') -Recurse
            Copy-Item (Join-Path $PSScriptRoot 'media-carousel-schema-probe-rpc.ps1') (Join-Path $fixture 'rpc.ps1')
            $bundle = Join-Path $fixture 'windows'
            $shared = Join-Path $fixture 'shared'
            $module = Join-Path $shared 'modules/saef-media-carousel'
            $null = [IO.Directory]::CreateDirectory((Join-Path $module 'MediaCarousel'))
            $script:policy = Get-Content (Join-Path $bundle 'adapters/media-carousel-adapter-policy.example.json') -Raw | ConvertFrom-Json
            $script:policy.activeModulePath = $module
            $script:policy.moduleControlInstanceId = 12345
            $script:policy.mutexName = 'Global\SAEF.SchemaSynthetic.' + [guid]::NewGuid().ToString('N')
            $configs = @{ '11111' = '{"FitMode":"cover","MediaItems":"[]"}'; '22222' = '{"FitMode":"contain","MediaItems":"[]"}' }
            $script:policy.expectedInstances = @(
                @{ instanceId = 11111; configurationSha256 = Get-TextSha256 $configs['11111'] },
                @{ instanceId = 22222; configurationSha256 = Get-TextSha256 $configs['22222'] })
            Write-Json (Join-Path $module 'library.json') @{ id = $script:policy.libraryGuid; name = $script:policy.libraryName; url = $script:policy.libraryUrl }
            Write-Json (Join-Path $module 'MediaCarousel/module.json') @{ id = $script:policy.moduleGuid; name = 'MediaCarousel' }
            Set-ManagedTreeAcl $module ([Security.Principal.SecurityIdentifier]::new($sid))
            if ($scenario -in @('shared-parent', 'parent-delete-child', 'parent-takeover')) {
                # Reproduce the reported inherited parent ACL via a synthetic grandparent.
                # Production leaf is protected before changing this scratch-only parent.
                $extra = ''
                if ($scenario -eq 'parent-delete-child') { $extra = '(A;OICI;0x40;;;BU)' }
                if ($scenario -eq 'parent-takeover') { $extra = '(A;OICI;0x40000;;;BU)' }
                $acl = [Security.AccessControl.DirectorySecurity]::new()
                $acl.SetSecurityDescriptorSddlForm('O:BAG:SYD:P(A;OICI;FA;;;SY)(A;OICI;FA;;;BA)' +
                    '(A;OICIIO;GA;;;CO)(A;OICI;0x1200a9;;;BU)(A;CI;DCLCRPCR;;;BU)' + $extra)
                Set-Acl -LiteralPath $shared -AclObject $acl
            }
            $parentBefore = (Get-Acl -LiteralPath (Split-Path -Parent $module)).Sddl
            $moduleBefore = (Get-Acl -LiteralPath $module).Sddl
            $script:policy.expectedActivePackageIdentitySha256 = Get-DirectoryPackageIdentity $module
            $policyPath = Join-Path $fixture 'adapter-policy.local.json'
            Write-Json $policyPath $script:policy
            $adapter = Join-Path $bundle 'adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1'
            $credential = Join-Path $fixture 'credential.local.json'
            $entropy = [Text.Encoding]::UTF8.GetBytes('SAEF.DeploymentChannel.RpcCredential.v1')
            $protected = [Security.Cryptography.ProtectedData]::Protect([Text.Encoding]::UTF8.GetBytes('synthetic'),
                $entropy, [Security.Cryptography.DataProtectionScope]::LocalMachine)
            Write-Json $credential @{ formatVersion = 1; protectionScope = 'LocalMachine'; username = 'synthetic'
                protectedPasswordBase64 = [Convert]::ToBase64String($protected) }
            $channel = Join-Path $fixture 'deployment-channel.local.json'
            Write-Json $channel @{ rpcUri = 'http://127.0.0.1:1234/api/'; credentialPath = $credential
                standaloneModuleTargets = @(@{ targetId = 'saef-media-carousel'; libraryGuid = $script:policy.libraryGuid
                    adapterPath = $adapter; expectedAdapterSha256 = Hash-File $adapter
                    adapterPolicyPath = $policyPath; expectedAdapterPolicySha256 = Hash-File $policyPath }) }
            $plan = @{ formatVersion = 1; targetId = 'saef-media-carousel'; parentId = 234; parentParentId = 345
                parentIdent = 'ProbeParent'; deploymentId = 'synthetic-schema'; candidatePackageIdentitySha256 = ('b' * 64)
                deploymentUser = $user; expectedDeploymentSid = $sid; installRoot = $fixture
                channelPolicySha256 = Hash-File $channel; adapterPolicySha256 = Hash-File $policyPath
                sourceHashes = @{ channel = Hash-File (Join-Path $bundle 'Initialize-SaefDeploymentChannel.ps1'); adapter = Hash-File $adapter
                    ownership = Hash-File (Join-Path $bundle 'adapters/Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1') }
                fixtureHashes = @{} }
            foreach ($name in @('library.json', 'SchemaProbe/module.json', 'legacy.php', 'candidate.php')) {
                $plan.fixtureHashes[$name] = Hash-File (Join-Path (Join-Path $bundle 'adapters/schema-probe') $name)
            }
            $planPath = Join-Path $fixture 'plan.local.json'
            Write-Json $planPath $plan
            $planHash = Hash-File $planPath
            if ($scenario -eq 'changed-plan') { $planHash = 'a' * 64 }
            $mockPath = Join-Path $fixture 'mock.local.json'
            $logPath = Join-Path $fixture 'log.local.json'
            Write-Json $mockPath @{ policy = $script:policy; configurations = $configs; scenario = $scenario; logPath = $logPath }
            $wrapper = Join-Path $fixture 'rpc.ps1'
            $child = Invoke-SaefPowerShellChildProcess -ScriptPath $wrapper -ExpectedScriptSha256 (Hash-File $wrapper) `
                -Arguments @('-PlanPath', $planPath, '-ExpectedPlanSha256', $planHash, '-FixturePath', $mockPath,
                    '-EntryPath', (Join-Path $bundle 'adapters/Test-SaefMediaCarouselSchema.ps1'), '-Culture', $culture) `
                -TimeoutSeconds 120 -MaximumOutputBytes 65536
            $stdout = [Text.Encoding]::UTF8.GetString($child.standardOutput)
            Assert-Test ($child.terminationReason -ceq 'exited') ('Child terminated: ' + $stdout)
            $result = $stdout | ConvertFrom-Json
            Assert-Test (-not $result.productionMutationAttempted -and -not $result.serviceRestartAttempted) 'Unexpected production action.'
            if ($scenario -in @('success', 'shared-parent')) {
                Assert-Test ($child.exitCode -eq 0 -and $result.outcome -ceq 'qualified' -and
                    $result.cleanupVerified -and $result.productionPreserved -and $result.qualifiedInstanceCount -eq 2) ('Success failed: ' + $stdout)
                Assert-Test (Test-Path (Join-Path $result.evidenceRoot 'configuration-transition.local.json')) 'Accepted evidence missing.'
            } else {
                Assert-Test ($child.exitCode -ne 0 -and $result.outcome -cne 'qualified') ('Unsafe acceptance: ' + $scenario)
                if ($scenario -notin @('changed-plan', 'parent-delete-child', 'parent-takeover')) {
                    Assert-Test (-not (Test-Path (Join-Path $result.evidenceRoot 'configuration-transition.local.json'))) 'Failed test accepted evidence.'
                    if ($scenario -in @('schema-drift', 'zero-id', 'creation-response-lost', 'production-drift')) {
                        Assert-Test $result.cleanupVerified ('Cleanup failed: ' + $stdout)
                    }
                    if ($scenario -in @('cleanup-fails', 'foreign-child')) {
                        Assert-Test ($result.outcome -ceq 'manual_recovery_required' -and -not $result.cleanupVerified) 'Ambiguous cleanup not retained.'
                    }
                }
                if ($scenario -in @('parent-delete-child', 'parent-takeover')) {
                    Assert-Test (-not $result.testMutationAttempted -and $result.stage -ceq 'shared_parent_preflight') 'Unsafe parent reached mutation.'
                }
            }
            if (Test-Path $logPath) {
                $log = Get-Content $logPath -Raw | ConvertFrom-Json
                Assert-Test ($log.productionWrites -eq 0) 'Production write occurred.'
            }
            Assert-Test ((Hash-File $channel) -ceq $plan.channelPolicySha256) 'Channel modified.'
            Assert-Test ((Get-Acl -LiteralPath (Split-Path -Parent $module)).Sddl -ceq $parentBefore) 'Shared parent ACL changed.'
            Assert-Test ((Get-Acl -LiteralPath $module).Sddl -ceq $moduleBefore) 'Production leaf ACL changed.'
            $passed++
        }
    }
    Add-Type -AssemblyName System.IO.Compression
    # Exercise exact self-extraction with the same private-plan shape and source files.
    $packageEntries = @('schema-plan.local.json', 'windows/Initialize-SaefDeploymentChannel.ps1',
        'windows/SaefChildProcess.ps1', 'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1',
        'windows/adapters/Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1',
        'windows/adapters/Test-SaefMediaCarouselSchema.ps1',
        'windows/adapters/schema-probe/library.json', 'windows/adapters/schema-probe/SchemaProbe/module.json',
        'windows/adapters/schema-probe/legacy.php', 'windows/adapters/schema-probe/candidate.php')
    foreach ($fault in @('none', 'wrong-hash', 'traversal', 'case-alias', 'missing', 'oversized')) {
        $zipPath = Join-Path $scratch ($fault + '.zip')
        $file = [IO.File]::Create($zipPath)
        $zip = [IO.Compression.ZipArchive]::new($file, [IO.Compression.ZipArchiveMode]::Create, $true)
        try {
            foreach ($name in $packageEntries) {
                if ($fault -eq 'missing' -and $name -eq 'schema-plan.local.json') { continue }
                $entryName = $name
                if ($name -eq 'schema-plan.local.json') {
                    if ($fault -eq 'traversal') { $entryName = '../escape.json' }
                    if ($fault -eq 'case-alias') { $entryName = 'SCHEMA-plan.local.json' }
                    $content = [IO.File]::ReadAllBytes($planPath)
                    if ($fault -eq 'oversized') { $content = New-Object byte[] 1048577 }
                } elseif ($fault -eq 'none' -and $name -eq 'windows/adapters/Test-SaefMediaCarouselSchema.ps1') {
                    # Bootstrap integration only: a hash-bound inert child, no RPC.
                    $content = [Text.Encoding]::UTF8.GetBytes("@{formatVersion=1;exitCode=0;outcome='synthetic-launch'} | ConvertTo-Json; exit 0")
                } else { $content = [IO.File]::ReadAllBytes((Join-Path $fixture $name)) }
                $stream = $zip.CreateEntry($entryName).Open()
                try { $stream.Write($content, 0, $content.Length) } finally { $stream.Dispose() }
            }
        } finally { $zip.Dispose(); $file.Dispose() }
        $hash = Hash-File $zipPath
        if ($fault -eq 'wrong-hash') { $hash = 'a' * 64 }
        $rejected = $false; $expanded = $null
        try { $expanded = Expand-SchemaPackage $zipPath $hash } catch { $rejected = $true }
        if ($fault -eq 'none') {
            Assert-Test (-not $rejected -and $null -ne $expanded) 'Self-extraction failed.'
            foreach ($name in $packageEntries) {
                Assert-Test ((Hash-File (Join-Path $expanded.root $name)) -ceq $expanded.hashes[$name]) 'Extracted bytes differ.'
            }
            $launcherText = [IO.File]::ReadAllText((Join-Path $windowsRoot 'adapters/Start-SaefMediaCarouselSchemaPackage.ps1'))
            $launcherText = $launcherText.Replace("'MediaCarousel-SchemaQualification.zip'", "'none.zip'")
            $launcherText = $launcherText.Replace("[Parameter(Mandatory = `$true)][ValidatePattern('^[a-f0-9]{64}$')][string] `$ExpectedZipSha256",
                "[Parameter()][ValidatePattern('^[a-f0-9]{64}$')][string] `$ExpectedZipSha256 = '$hash'")
            $launcherPath = Join-Path $scratch 'Run-NoArguments.ps1'
            [IO.File]::WriteAllText($launcherPath, $launcherText, [Text.UTF8Encoding]::new($false))
            $launch = Invoke-SaefPowerShellChildProcess -ScriptPath $launcherPath -ExpectedScriptSha256 (Hash-File $launcherPath) `
                -TimeoutSeconds 60 -MaximumOutputBytes 65536
            $launchText = [Text.Encoding]::UTF8.GetString($launch.standardOutput)
            Assert-Test ($launch.exitCode -eq 0 -and ($launchText | ConvertFrom-Json).outcome -ceq 'synthetic-launch') ('No-argument launcher failed: ' + $launchText)
            $passed++
        } else { Assert-Test $rejected ('Unsafe ZIP accepted: ' + $fault) }
        $passed++
    }
    Write-Output ('MediaCarousel schema probe and extraction: ' + $passed + ' scenarios passed.')
} finally { Remove-Item -LiteralPath $scratch -Recurse -Force }
