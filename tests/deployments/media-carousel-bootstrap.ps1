# Actual profile initializer plus atomic binding publication in isolated files.
# Only the explicit runtime/RPC inspection boundary is substituted.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$windows = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
foreach ($path in @((Join-Path $windows 'Initialize-SaefDeploymentChannel.ps1'),
    (Join-Path $windows 'adapters/Update-SaefMediaCarouselBinding.ps1'))) {
    $tokens = $null; $errors = $null
    $ast = [Management.Automation.Language.Parser]::ParseFile($path, [ref] $tokens, [ref] $errors)
    if (@($errors).Count) { throw 'Production source parser failed.' }
    foreach ($fn in @($ast.FindAll({ param($node)
        $node -is [Management.Automation.Language.FunctionDefinitionAst]
    }, $false))) { . ([scriptblock]::Create($fn.Extent.Text)) }
}
Assert-Elevated | Out-Null
. (Join-Path $windows 'SaefChildProcess.ps1')
function Hash-File { param([string] $Path) return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant() }
function Write-Json { param([string] $Path, $Value) [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 30), [Text.UTF8Encoding]::new($false)); Set-RestrictedFileAcl $Path }
function Assert-UpdateRuntime {
    $script:runtimeCalls++
    if ($script:scenario -ceq 'postflight-failure' -and $script:runtimeCalls -eq 2) { throw 'Synthetic postflight failure.' }
}
# Execute the actual resealer guards with the real initializer's output paths.
$resealAst = [Management.Automation.Language.Parser]::ParseFile(
    (Join-Path $windows 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1'), [ref] $tokens, [ref] $errors)
$contains = @($resealAst.FindAll({ param($n)
    $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq 'Test-PathContains'
}, $false))
$guards = @($resealAst.FindAll({ param($n)
    $n -is [Management.Automation.Language.ForEachStatementAst] -and $n.Variable.VariablePath.UserPath -ceq 'managedRoot'
}, $true))
if ($contains.Count -ne 1 -or $guards.Count -ne 2 -or @($errors).Count) { throw 'Actual resealer path guard boundary changed.' }
. ([scriptblock]::Create($contains[0].Extent.Text))
function Test-InstalledResealPaths {
    param($Channel, [string] $ChannelPath, [string] $Status)
    $channelPolicy = $Channel; $ChannelPolicyPath = $ChannelPath; $StatusPath = $Status
    $script:adapterPolicyPath = $Channel.standaloneModuleTargets[1].adapterPolicyPath
    $adapterPolicy = ConvertFrom-AdditionJson ([IO.File]::ReadAllBytes($script:adapterPolicyPath))
    foreach ($guard in $guards) { . ([scriptblock]::Create($guard.Extent.Text)) }
}
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('saef-bootstrap-test-' + [guid]::NewGuid().ToString('N'))
$null = [IO.Directory]::CreateDirectory($scratch)
Set-RestrictedAcl $scratch '*S-1-5-32-544' '(OI)(CI)F'
$account = Get-LocalUser -SID ([Security.Principal.WindowsIdentity]::GetCurrent().User)
$additionDeploymentSid = $account.SID.Value
$utf8 = [Text.UTF8Encoding]::new($false)
$savedCulture = [Threading.Thread]::CurrentThread.CurrentCulture
$passed = 0
try {
    $package = Join-Path $scratch 'package'
    $packageWindows = Join-Path $package 'windows'
    $null = [IO.Directory]::CreateDirectory((Join-Path $packageWindows 'adapters'))
    foreach ($name in @('Initialize-SaefScopeBoundApprovalProfile.ps1', 'Invoke-SaefScopeBoundApprovalRunner.ps1',
        'SaefChildProcess.ps1', 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1')) {
        Copy-Item -LiteralPath (Join-Path $windows $name) -Destination (Join-Path $packageWindows $name)
        Set-RestrictedFileAcl (Join-Path $packageWindows $name)
    }
    $secretPath = Join-Path $scratch 'controller-secret.local.json'
    Write-Json $secretPath @{ formatVersion = 1; encoding = 'base64'; secretBase64 = [Convert]::ToBase64String($utf8.GetBytes(('synthetic-only-' * 3))) }
    $adapterBytes = [IO.File]::ReadAllBytes((Join-Path $windows 'adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1'))
    $policyBytes = $utf8.GetBytes((@{ formatVersion = 1; adapterProfile = 'saef-media-carousel-v1'
        activeModulePath = (Join-Path $scratch 'active'); adapterStateRoot = (Join-Path $scratch 'adapter-state') } | ConvertTo-Json))
    $spec = [pscustomobject]@{
        approvalSecretRecordPath = $secretPath; approvalSecretSha256 = (Hash-File $secretPath)
        channelHostBindingSha256 = ('a' * 64); approverIdentitySha256 = ('b' * 64); executionHostIdentitySha256 = ('c' * 64)
        maximumStateFiles = 32
        sourceHashes = [pscustomobject]@{
            initializer = Hash-File (Join-Path $packageWindows 'Initialize-SaefScopeBoundApprovalProfile.ps1')
            runner = Hash-File (Join-Path $packageWindows 'Invoke-SaefScopeBoundApprovalRunner.ps1')
            reseal = Hash-File (Join-Path $packageWindows 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1')
            child = Hash-File (Join-Path $packageWindows 'SaefChildProcess.ps1')
            qualification = ''
        }
    }
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
        foreach ($scenario in @('success', 'invalid-qualification', 'postflight-failure')) {
            $root = Join-Path $scratch ($culture + '-' + $scenario)
            $null = [IO.Directory]::CreateDirectory($root)
            $generation = Join-Path $root 'generation'
            $spec | Add-Member NoteProperty approvalRoot (Join-Path $scratch ($culture + '-' + $scenario + '-approvals')) -Force
            $channelPath = Join-Path $root 'channel.json'
            $oldAdapter = Join-Path $root 'old-adapter.ps1'; $oldPolicy = Join-Path $root 'old-policy.json'
            [IO.File]::WriteAllBytes($oldAdapter, $adapterBytes); Set-RestrictedFileAcl $oldAdapter
            [IO.File]::WriteAllBytes($oldPolicy, $policyBytes); Set-RestrictedFileAcl $oldPolicy
            $old = [ordered]@{
                formatVersion = 1; deploymentUser = $account.Name.ToLowerInvariant()
                expectedChildProcessContractSha256 = $spec.sourceHashes.child
                stateRoot = (Join-Path $root 'deployments'); managedFilesetRoot = (Join-Path $root 'filesets')
                adapterStateRoot = (Join-Path $root 'adapter-states')
                unrelatedField = @{ preserved = @('yes', 42) }
                standaloneModuleTargets = @(
                    @{ targetId = 'saef-owntracks-position-map'; libraryGuid = '{11111111-1111-1111-1111-111111111111}'
                        adapterPath = $oldAdapter; expectedAdapterSha256 = (Hash-File $oldAdapter)
                        adapterPolicyPath = $oldPolicy; expectedAdapterPolicySha256 = (Hash-File $oldPolicy)
                        approvalRunnerPath = $oldAdapter; expectedApprovalRunnerSha256 = (Hash-File $oldAdapter)
                        approvalPolicyPath = $oldPolicy; expectedApprovalPolicySha256 = (Hash-File $oldPolicy) },
                    @{ targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'
                        libraryGuid = '{22222222-2222-2222-2222-222222222222}'
                        adapterPath = $oldAdapter; expectedAdapterSha256 = (Hash-File $oldAdapter)
                        adapterPolicyPath = $oldPolicy; expectedAdapterPolicySha256 = (Hash-File $oldPolicy) }
                )
            }
            Write-Json $channelPath $old
            $before = [IO.File]::ReadAllBytes($channelPath)
            $originalAcl = (Get-Acl -LiteralPath $channelPath).Sddl
            $candidate = ConvertFrom-AdditionJson $before
            $candidate.standaloneModuleTargets[1].adapterPath = Join-Path $generation 'adapter.ps1'
            $candidate.standaloneModuleTargets[1].adapterPolicyPath = Join-Path $generation 'adapter-policy.local.json'
            $after = $utf8.GetBytes(($candidate | ConvertTo-Json -Depth 30))
            $qualification = [ordered]@{
                formatVersion = 1; timestampUtc = [DateTime]::UtcNow.ToString('o'); phase = 'windows_qualification'
                outcome = if ($scenario -ceq 'invalid-qualification') { 'failed' } else { 'passed' }
                exitCode = 0; expectedChannelVersion = 8
                childProcessContractSha256 = $spec.sourceHashes.child; runnerSha256 = $spec.sourceHashes.runner
                adapterSha256 = (Get-BytesSha256 $adapterBytes); resealSha256 = $spec.sourceHashes.reseal
                positiveCaseCount = 6; negativeCaseCount = 8
                scratchMutationAttempted = $true; scratchCleanupSucceeded = $true
                productionMutationAttempted = $false; serviceRestartAttempted = $false; failedCheck = ''; errorType = ''
            }
            Write-Json (Join-Path $package 'qualification.local.json') $qualification
            $spec.sourceHashes.qualification = Hash-File (Join-Path $package 'qualification.local.json')
            $context = Get-ApprovalBootstrapContext $spec $package $packageWindows $account.Name (ConvertFrom-AdditionJson $before) $root
            $result = [ordered]@{ bindingMutationAttempted = $false; rollbackSucceeded = $null }
            $runtimeCalls = 0; $threw = $false
            try { Publish-UpdateGeneration $channelPath $generation $before $after $adapterBytes $policyBytes $context }
            catch { $threw = $true; if ($scenario -ceq 'success') { throw } }
            if ($threw -ne ($scenario -cne 'success')) { throw 'Unexpected bootstrap outcome.' }
            if ($scenario -ceq 'success') {
                $actual = ConvertFrom-AdditionJson ([IO.File]::ReadAllBytes($channelPath))
                Assert-AdditionBindings $actual.standaloneModuleTargets
                if (($actual.standaloneModuleTargets[0] | ConvertTo-Json -Depth 30 -Compress) -cne
                    ($candidate.standaloneModuleTargets[0] | ConvertTo-Json -Depth 30 -Compress) -or
                    -not $result.approvalProfileInstalled -or $result.channelPolicySha256 -cne (Hash-File $channelPath)) { throw 'Bootstrap preservation/readback differs.' }
                # Exercise the same installed profile through a second generation.
                # The consumed record must survive byte-for-byte, never reset.
                $oldApproval = ConvertFrom-AdditionJson ([IO.File]::ReadAllBytes($actual.standaloneModuleTargets[1].approvalPolicyPath))
                $statusSuffix = ('d' * 64) + '/reseal-status.json'
                Test-InstalledResealPaths $actual $channelPath (Join-Path $oldApproval.approvalStateRoot $statusSuffix)
                $guardRejected = $false
                try { Test-InstalledResealPaths $actual $channelPath (Join-Path $generation ('approval/state/' + $statusSuffix)) }
                catch { $guardRejected = $_.Exception.Message -ceq 'Status path is inside a managed channel or module root.' }
                if (-not $guardRejected) { throw 'Original live path defect was not reproduced.' }
                $recordPath = Join-Path $oldApproval.approvalStateRoot (('d' * 64) + '.json')
                Write-Json $recordPath @{ targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'
                    outcome = 'rolled_back'; phaseState = 'completed'; stateSignature = 'synthetic-preserved-signature' }
                $ledgerHash = (Get-ApprovalStateInventory $oldApproval.approvalStateRoot).sha256
                $spec | Add-Member NoteProperty approvalStateSha256 $ledgerHash -Force
                $spec.approvalRoot = Join-Path $scratch ($culture + '-migrated-approvals')
                $second = Join-Path $root 'second-generation'
                $prior = [IO.File]::ReadAllBytes($channelPath)
                $next = ConvertFrom-AdditionJson $prior
                $next.standaloneModuleTargets[1].adapterPath = Join-Path $second 'adapter.ps1'
                $next.standaloneModuleTargets[1].adapterPolicyPath = Join-Path $second 'adapter-policy.local.json'
                $migration = Get-ApprovalBootstrapContext $spec $package $packageWindows $account.Name $actual $root
                $recordBytes = [IO.File]::ReadAllBytes($recordPath)
                foreach ($badOutcome in @('running', 'manual_recovery_required')) {
                    $badRecord = ConvertFrom-AdditionJson $recordBytes
                    $badRecord.outcome = $badOutcome
                    Write-Json $recordPath $badRecord
                    $rejected = $false
                    try { $null = Get-ApprovalStateInventory $oldApproval.approvalStateRoot } catch { $rejected = $true }
                    if (-not $rejected) { throw 'Uncertain approval state admitted.' }
                }
                [IO.File]::WriteAllBytes($recordPath, $recordBytes)
                [IO.File]::AppendAllText($recordPath, ' ', $utf8)
                $rejected = $false
                try { $null = Get-ApprovalBootstrapContext $spec $package $packageWindows $account.Name $actual $root }
                catch { $rejected = $_.Exception.Message -ceq 'Reviewed approval migration baseline differs.' }
                if (-not $rejected) { throw 'Ledger drift admitted.' }
                [IO.File]::WriteAllBytes($recordPath, $recordBytes)
                Publish-UpdateGeneration $channelPath $second $prior ($utf8.GetBytes(($next | ConvertTo-Json -Depth 30))) $adapterBytes $policyBytes $migration
                $migrated = Join-Path $spec.approvalRoot 'saef-media-carousel/state'
                if ((Get-ApprovalStateInventory $migrated).sha256 -cne $ledgerHash -or
                    (Get-ApprovalStateInventory $oldApproval.approvalStateRoot).sha256 -cne $ledgerHash) { throw 'Replay ledger was not retained.' }
                $migratedChannel = ConvertFrom-AdditionJson ([IO.File]::ReadAllBytes($channelPath))
                Test-InstalledResealPaths $migratedChannel $channelPath (Join-Path $migrated $statusSuffix)
                # A managed-root candidate is rejected without creating it.
                $spec.approvalRoot = Join-Path $root 'forbidden-approvals'
                $rejected = $false
                try { $null = Get-ApprovalBootstrapContext $spec $package $packageWindows $account.Name $actual $root }
                catch { $rejected = $_.Exception.Message -ceq 'Approval root overlaps a managed channel or module root.' }
                if (-not $rejected -or (Test-Path $spec.approvalRoot)) { throw 'Managed-root approval path was admitted.' }
            } elseif ((Hash-File $channelPath) -cne (Get-BytesSha256 $before) -or -not $result.rollbackSucceeded) { throw 'Original channel not restored.' }
            if ((Get-Acl -LiteralPath $channelPath).Sddl -cne $originalAcl -or
                (Hash-File $oldAdapter) -cne (Get-BytesSha256 $adapterBytes) -or
                (Hash-File $oldPolicy) -cne (Get-BytesSha256 $policyBytes) -or
                (Hash-File $secretPath) -cne $spec.approvalSecretSha256) { throw 'Existing source or ACL modified.' }
            if (-not (Test-Path -LiteralPath $generation -PathType Container)) { throw 'Recovery generation not retained.' }
            $passed++
        }
    }
    Write-Output "PASS: $passed actual profile bootstrap transactions across three cultures; no live mutation."
} finally {
    [Threading.Thread]::CurrentThread.CurrentCulture = $savedCulture
    if ((Split-Path -Leaf $scratch) -cmatch '^saef-bootstrap-test-[a-f0-9]{32}$') { Remove-Item -LiteralPath $scratch -Recurse -Force }
}
