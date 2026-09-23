# Execute the actual resealer against isolated Windows files/ACLs. No Symcon RPC.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) { throw 'Windows PowerShell 5.1 required.' }
$windows = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
$source = Join-Path $windows 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1'
. (Join-Path $windows 'SaefChildProcess.ps1')
# Use the actual binding publisher to produce the policy generation. Hand-built
# policy ACLs previously hid the installer/resealer contract mismatch.
foreach ($inputSource in @('Initialize-SaefDeploymentChannel.ps1', 'adapters/Update-SaefMediaCarouselBinding.ps1')) {
    $tokens = $null; $errors = $null
    $inputAst = [Management.Automation.Language.Parser]::ParseFile((Join-Path $windows $inputSource), [ref] $tokens, [ref] $errors)
    if (@($errors).Count) { throw 'Binding source does not parse.' }
    foreach ($fn in @($inputAst.FindAll({ param($node)
        $node -is [Management.Automation.Language.FunctionDefinitionAst]
    }, $false))) { . ([scriptblock]::Create($fn.Extent.Text)) }
}
function Assert-UpdateRuntime { } # Explicit no-RPC boundary in this filesystem integration.
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($source, [ref] $tokens, [ref] $errors)
if (@($errors).Count) { throw 'Reseal source does not parse.' }
foreach ($name in @('Get-Sha256', 'Get-BytesSha256', 'Assert-PlainDirectory', 'Assert-SafeDirectoryTree', 'Get-DirectoryPackageIdentity')) {
    $found = @($ast.FindAll({ param($node)
        $node -is [Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -ceq $name
    }, $false))
    if ($found.Count -ne 1) { throw 'Required production function is not unique.' }
    . ([scriptblock]::Create($found[0].Extent.Text))
}
$MaximumPackageBytes = 67108864; $MaximumPackageFiles = 256
$utf8 = [Text.UTF8Encoding]::new($false)
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User
$account = Get-LocalUser -SID $sid
$additionDeploymentSid = $sid.Value
$sourceHash = Get-Sha256 $source
function New-Directory { param([string] $Path) $null = [IO.Directory]::CreateDirectory($Path) }
function Write-Json { param([string] $Path, $Value) [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 30), $utf8) }
function Protect-Directory {
    param([string] $Path, [switch] $Policy)
    $acl = [Security.AccessControl.DirectorySecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $ba = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    $acl.SetOwner($ba)
    foreach ($principal in @([Security.Principal.SecurityIdentifier]::new('S-1-5-18'), $ba, $sid)) {
        $rights = if ($Policy -and $principal.Value -ceq $sid.Value) {
            [Security.AccessControl.FileSystemRights]::ReadAndExecute
        } else { [Security.AccessControl.FileSystemRights]::FullControl }
        $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
            $principal, $rights, 'ContainerInherit,ObjectInherit', 'None', 'Allow'))
    }
    Set-Acl -LiteralPath $Path -AclObject $acl
}
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('saef-reseal-test-' + [guid]::NewGuid().ToString('N'))
New-Directory $scratch
Protect-Directory $scratch
$cases = 0
try {
    $channelRoot = Join-Path $scratch 'channel'
    $policyRoot = Join-Path $scratch 'policy'
    $state = Join-Path $scratch 'state'
    $managed = Join-Path $scratch 'managed'
    $adapters = Join-Path $scratch 'adapters'
    $adapterState = Join-Path $adapters 'saef-media-carousel'
    $active = Join-Path $scratch 'active'
    foreach ($path in @($channelRoot, $policyRoot, $state, $managed, $adapters, $adapterState, $active)) { New-Directory $path }
    Protect-Directory $channelRoot -Policy
    Protect-Directory $policyRoot -Policy
    Protect-Directory $adapterState
    $channelPath = Join-Path $channelRoot 'channel.json'
    $policyPath = Join-Path $policyRoot 'adapter.json'
    $configurationHash = 'd' * 64
    $policy = [ordered]@{
        formatVersion = 1; targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'
        activeModulePath = $active; adapterStateRoot = $adapterState
        mutexName = 'Global\SAEF.MediaCarousel.ResealQualification'
        expectedActivePackageIdentitySha256 = ''
        expectedInstances = @(@{ instanceId = 101; configurationSha256 = $configurationHash })
        futureField = @{ keep = @('unchanged', 42, $true) }
    }
    [IO.File]::WriteAllText((Join-Path $active 'module.txt'), 'predecessor', $utf8)
    $policy.expectedActivePackageIdentitySha256 = (Get-DirectoryPackageIdentity $active).sha256
    Write-Json $policyPath $policy
    $adapterSource = Join-Path $policyRoot 'original-adapter.ps1'
    Copy-Item -LiteralPath (Join-Path $windows 'adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1') -Destination $adapterSource
    Set-RestrictedFileAcl $adapterSource
    Set-RestrictedFileAcl $policyPath
    $unrelated = [ordered]@{ targetId = 'saef-owntracks-position-map'; adapterProfile = 'saef-owntracks-position-map-v1'
        libraryGuid = '{11111111-1111-1111-1111-111111111111}'
        adapterPath = $adapterSource; expectedAdapterSha256 = (Get-Sha256 $adapterSource)
        adapterPolicyPath = $policyPath; expectedAdapterPolicySha256 = (Get-Sha256 $policyPath) }
    $channel = [ordered]@{
        formatVersion = 1; stateRoot = $state; managedFilesetRoot = $managed; adapterStateRoot = $adapters
        standaloneModuleTargets = @($unrelated, [ordered]@{
            targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'
            libraryGuid = '{22222222-2222-2222-2222-222222222222}'
            adapterPath = $adapterSource; expectedAdapterSha256 = (Get-Sha256 $adapterSource)
            adapterPolicyPath = $policyPath; expectedAdapterPolicySha256 = (Get-Sha256 $policyPath)
        })
    }
    Write-Json $channelPath $channel
    Set-RestrictedFileAcl $channelPath
    $before = [IO.File]::ReadAllBytes($channelPath)
    $generation = Join-Path $channelRoot 'installed-generation'
    $candidate = ConvertFrom-AdditionJson $before
    $candidate.standaloneModuleTargets[1].adapterPath = Join-Path $generation 'adapter.ps1'
    $candidate.standaloneModuleTargets[1].adapterPolicyPath = Join-Path $generation 'adapter-policy.local.json'
    $result = [ordered]@{ bindingMutationAttempted = $false; rollbackSucceeded = $null }
    Publish-UpdateGeneration $channelPath $generation $before ($utf8.GetBytes(($candidate | ConvertTo-Json -Depth 30))) `
        ([IO.File]::ReadAllBytes($adapterSource)) ([IO.File]::ReadAllBytes($policyPath)) -DeploymentSid $sid.Value
    $policyPath = $candidate.standaloneModuleTargets[1].adapterPolicyPath
    foreach ($step in @(1, 2)) {
        $previousHash = (Get-DirectoryPackageIdentity $active).sha256
        $deploymentId = 'saef-reseal-test-' + $step
        $transactionName = $deploymentId + '-20260923T010101Z'
        $transactionRoot = Join-Path $adapterState $transactionName
        $rollback = Join-Path $transactionRoot 'rollback'
        $deploymentRoot = Join-Path $state $deploymentId
        $staged = Join-Path $managed ($deploymentId + '-module')
        foreach ($path in @($transactionRoot, $rollback, $deploymentRoot, $staged)) { New-Directory $path }
        Copy-Item -LiteralPath (Join-Path $active 'module.txt') -Destination $rollback
        [IO.File]::WriteAllText((Join-Path $active 'module.txt'), ('candidate-' + $step), $utf8)
        Copy-Item -LiteralPath (Join-Path $active 'module.txt') -Destination $staged
        $candidateHash = (Get-DirectoryPackageIdentity $active).sha256
        $snapshot = [ordered]@{ deploymentId = $deploymentId; packageIdentitySha256 = $candidateHash
            activePackageIdentitySha256 = $previousHash
            instances = @(@{ instanceId = 101; configurationSha256 = $configurationHash; objectName = 'camera' }) }
        Write-Json (Join-Path $transactionRoot 'snapshot.json') $snapshot
        Write-Json (Join-Path $transactionRoot 'candidate-snapshot.json') $snapshot
        Write-Json (Join-Path $deploymentRoot 'deployment.json') @{
            deploymentId = $deploymentId; targetDirectoryName = ($deploymentId + '-module')
            module = @{ packageIdentitySha256 = $candidateHash }
        }
        Write-Json (Join-Path $deploymentRoot 'status.json') @{ phase = 'activation'; outcome = 'activated'; exitCode = 0 }
        Write-Json (Join-Path $deploymentRoot 'module-adapter-status.json') @{
            operation = 'activate'; outcome = 'activated'; exitCode = 0; rollbackAttempted = $false }
        $transaction = [ordered]@{
            transactionDirectoryName = $transactionName; deploymentId = $deploymentId
            packageIdentitySha256 = $candidateHash; outcome = 'activated'
            manifestSha256 = (Get-Sha256 (Join-Path $deploymentRoot 'deployment.json'))
            snapshotSha256 = (Get-Sha256 (Join-Path $transactionRoot 'snapshot.json'))
            candidateSnapshotSha256 = (Get-Sha256 (Join-Path $transactionRoot 'candidate-snapshot.json'))
        }
        Write-Json (Join-Path $transactionRoot 'transaction.json') $transaction
        Write-Json (Join-Path $adapterState 'active.json') @{
            formatVersion = 1; adapterProfile = 'saef-media-carousel-v1'; deploymentId = $deploymentId
            packageIdentitySha256 = $candidateHash; transactionDirectoryName = $transactionName
            rollbackDirectoryName = 'rollback'; snapshotFileName = 'snapshot.json'
        }
        foreach ($scenario in @('missing-deployment-acl', 'tampered-snapshot', 'configuration-drift', 'consumed-transition', 'success')) {
            $installedAcl = Get-Acl -LiteralPath $generation
            if ($scenario -ceq 'missing-deployment-acl') {
                $brokenAcl = Get-Acl -LiteralPath $generation
                $brokenAcl.PurgeAccessRules($sid)
                Set-Acl -LiteralPath $generation -AclObject $brokenAcl
            }
            $beforePolicy = [IO.File]::ReadAllBytes($policyPath)
            $beforeChannel = [IO.File]::ReadAllBytes($channelPath)
            $beforeSnapshot = [IO.File]::ReadAllBytes((Join-Path $transactionRoot 'candidate-snapshot.json'))
            $beforeRecord = [IO.File]::ReadAllBytes((Join-Path $transactionRoot 'transaction.json'))
            if ($scenario -ceq 'tampered-snapshot') { [IO.File]::AppendAllText((Join-Path $transactionRoot 'candidate-snapshot.json'), ' ', $utf8) }
            if ($scenario -ceq 'configuration-drift') {
                $changed = $snapshot | ConvertTo-Json -Depth 20 | ConvertFrom-Json
                $changed.instances[0].configurationSha256 = ('f' * 64)
                Write-Json (Join-Path $transactionRoot 'candidate-snapshot.json') $changed
                $transaction.candidateSnapshotSha256 = Get-Sha256 (Join-Path $transactionRoot 'candidate-snapshot.json')
                Write-Json (Join-Path $transactionRoot 'transaction.json') $transaction
            }
            if ($scenario -ceq 'consumed-transition') {
                $changedPolicy = Get-Content -LiteralPath $policyPath -Raw | ConvertFrom-Json
                $changedPolicy | Add-Member NoteProperty configurationTransition @{}
                Write-Json $policyPath $changedPolicy
                $changedChannel = Get-Content -LiteralPath $channelPath -Raw | ConvertFrom-Json
                $changedChannel.standaloneModuleTargets[1].expectedAdapterPolicySha256 = Get-Sha256 $policyPath
                Write-Json $channelPath $changedChannel
            }
            $invokedPolicyHash = Get-Sha256 $policyPath; $invokedChannelHash = Get-Sha256 $channelPath
            $statusPath = Join-Path $scratch ('status-' + $step + '-' + $scenario + '.json')
            $child = Invoke-SaefPowerShellChildProcess -ScriptPath $source -ExpectedScriptSha256 $sourceHash `
                -Arguments @('-Operation', 'apply', '-TargetId', 'saef-media-carousel',
                    '-ChannelPolicyPath', $channelPath, '-ExpectedChannelPolicySha256', $invokedChannelHash,
                    '-ExpectedPreviousPackageIdentitySha256', $previousHash, '-ExpectedActivePackageIdentitySha256', $candidateHash,
                    '-ExpectedActiveDeploymentId', $deploymentId, '-DeploymentUser', $account.Name,
                    '-StatusPath', $statusPath, '-Confirmation', 'reseal-saef-media-carousel-active-identity') `
                -TimeoutSeconds 60 -MaximumOutputBytes 8192
            $status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
            Set-Acl -LiteralPath $generation -AclObject $installedAcl
            if ($scenario -ceq 'missing-deployment-acl' -and
                ($status.failureCode -cne 'adapter_policy_acl' -or $status.failureDetail -cne 'Managed policy ACL lacks a required principal rule.')) {
                throw 'Original missing deployment rule was not reproduced by the full resealer.'
            }
            if ($scenario -ceq 'success') {
                if ($child.exitCode -ne 0 -or $status.outcome -cne 'resealed') { throw ('Actual reseal failed: ' + ($status | ConvertTo-Json -Compress)) }
                $actualPolicy = Get-Content -LiteralPath $policyPath -Raw | ConvertFrom-Json
                $expectedPolicy = $utf8.GetString($beforePolicy) | ConvertFrom-Json
                $expectedPolicy.expectedActivePackageIdentitySha256 = $candidateHash
                if (($actualPolicy | ConvertTo-Json -Depth 20 -Compress) -cne ($expectedPolicy | ConvertTo-Json -Depth 20 -Compress)) { throw 'Reseal changed unrelated policy fields.' }
                $actualChannel = Get-Content -LiteralPath $channelPath -Raw | ConvertFrom-Json
                $expectedChannel = $utf8.GetString($beforeChannel) | ConvertFrom-Json
                $expectedChannel.standaloneModuleTargets[1].expectedAdapterPolicySha256 = Get-Sha256 $policyPath
                if (($actualChannel | ConvertTo-Json -Depth 20 -Compress) -cne ($expectedChannel | ConvertTo-Json -Depth 20 -Compress)) { throw 'Reseal changed unrelated channel/OwnTracks fields.' }
            } else {
                if ($child.exitCode -eq 0 -or $status.mutationAttempted -or
                    (Get-Sha256 $policyPath) -cne $invokedPolicyHash -or (Get-Sha256 $channelPath) -cne $invokedChannelHash) {
                    throw 'Invalid reseal evidence accepted or mutated policy.'
                }
                [IO.File]::WriteAllBytes($policyPath, $beforePolicy)
                [IO.File]::WriteAllBytes($channelPath, $beforeChannel)
            }
            [IO.File]::WriteAllBytes((Join-Path $transactionRoot 'candidate-snapshot.json'), $beforeSnapshot)
            [IO.File]::WriteAllBytes((Join-Path $transactionRoot 'transaction.json'), $beforeRecord)
            $transaction = $utf8.GetString($beforeRecord) | ConvertFrom-Json
            if ((Get-DirectoryPackageIdentity $active).sha256 -cne $candidateHash -or
                (Get-DirectoryPackageIdentity $rollback).sha256 -cne $previousHash) { throw 'Reseal mutated package bytes.' }
            $cases++
        }
    }
    Write-Output "PASS: $cases actual reseal cases, including two consecutive baseline advances; no live mutation."
} finally {
    if ((Split-Path -Leaf $scratch) -cmatch '^saef-reseal-test-[a-f0-9]{32}$') { Remove-Item -LiteralPath $scratch -Recurse -Force }
}
