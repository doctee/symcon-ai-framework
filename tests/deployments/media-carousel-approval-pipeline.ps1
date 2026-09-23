# Invoked by the existing Windows loopback-RPC fixture, never standalone/live.
# Reuse its module/configuration fixtures, mock server and cleanup ownership.
function Invoke-MediaCarouselApprovalPipeline {
    $windows = Split-Path -Parent $sourceRoot
    foreach ($inputSource in @('Initialize-SaefDeploymentChannel.ps1', 'adapters/Update-SaefMediaCarouselBinding.ps1',
        'Invoke-SaefScopeBoundApprovalWindowsQualification.ps1')) {
        $tokens = $null; $errors = $null
        $ast = [Management.Automation.Language.Parser]::ParseFile((Join-Path $windows $inputSource), [ref] $tokens, [ref] $errors)
        if (@($errors).Count) { throw 'Pipeline source parser failed.' }
        foreach ($fn in @($ast.FindAll({ param($node)
            $node -is [Management.Automation.Language.FunctionDefinitionAst]
        }, $false))) { . ([scriptblock]::Create($fn.Extent.Text)) }
    }
    # Only installer runtime inspection is substituted. The real runner invokes
    # the real adapter over the fixture's HTTP RPC boundary for every phase.
    function Assert-UpdateRuntime { }
    $account = Get-LocalUser -SID ([Security.Principal.WindowsIdentity]::GetCurrent().User)
    $additionDeploymentSid = $account.SID.Value
    $utf8 = [Text.UTF8Encoding]::new($false)
    $package = Join-Path $scratchRoot 'bootstrap-package'
    $packageWindows = Join-Path $package 'windows'
    $channelRoot = Join-Path $scratchRoot 'channel'
    $stateRoot = Join-Path $scratchRoot 'deployments'
    $managedRoot = Join-Path $scratchRoot 'filesets'
    foreach ($path in @((Join-Path $packageWindows 'adapters'), $channelRoot, $stateRoot, $managedRoot)) {
        $null = [IO.Directory]::CreateDirectory($path)
    }
    Set-RestrictedAcl $channelRoot ('*' + $additionDeploymentSid) '(OI)(CI)RX'
    Set-RestrictedAcl $adapterState ('*' + $additionDeploymentSid) '(OI)(CI)F'
    foreach ($name in @('Initialize-SaefScopeBoundApprovalProfile.ps1', 'Invoke-SaefScopeBoundApprovalRunner.ps1',
        'SaefChildProcess.ps1', 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1')) {
        Copy-Item -LiteralPath (Join-Path $windows $name) -Destination (Join-Path $packageWindows $name)
        Set-RestrictedFileAcl (Join-Path $packageWindows $name)
    }
    $script:childProcessContractSha256 = Get-Sha256 (Join-Path $packageWindows 'SaefChildProcess.ps1')
    $script:runnerSha256 = Get-Sha256 (Join-Path $packageWindows 'Invoke-SaefScopeBoundApprovalRunner.ps1')
    $script:secretBytes = $utf8.GetBytes(('isolated-test-secret-' * 3))
    $secretPath = Join-Path $package 'controller-secret.json'
    Write-Json $secretPath @{ formatVersion = 1; encoding = 'base64'; secretBase64 = [Convert]::ToBase64String($script:secretBytes) }
    Set-RestrictedFileAcl $secretPath
    $adapterBytes = [IO.File]::ReadAllBytes($adapterPath)
    $originalAdapter = Join-Path $package 'original-adapter.ps1'
    [IO.File]::WriteAllBytes($originalAdapter, $adapterBytes)
    Set-RestrictedFileAcl $originalAdapter
    Set-RestrictedFileAcl $policyPath
    $channelPath = Join-Path $channelRoot 'deployment-channel.local.json'
    $channel = [ordered]@{
        formatVersion = 1; deploymentUser = $account.Name.ToLowerInvariant()
        expectedChildProcessContractSha256 = $script:childProcessContractSha256
        stateRoot = $stateRoot; managedFilesetRoot = $managedRoot; adapterStateRoot = $adapterState
        standaloneModuleTargets = @(
            @{ targetId = 'saef-owntracks-position-map'; libraryGuid = '{11111111-1111-1111-1111-111111111111}'
                adapterPath = $originalAdapter; expectedAdapterSha256 = (Get-Sha256 $originalAdapter)
                adapterPolicyPath = $policyPath; expectedAdapterPolicySha256 = (Get-Sha256 $policyPath) },
            @{ targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'; libraryGuid = $policy.libraryGuid
                adapterPath = $originalAdapter; expectedAdapterSha256 = (Get-Sha256 $originalAdapter)
                adapterPolicyPath = $policyPath; expectedAdapterPolicySha256 = (Get-Sha256 $policyPath) }
        )
    }
    Write-Json $channelPath $channel
    Set-RestrictedFileAcl $channelPath
    $resealHash = Get-Sha256 (Join-Path $packageWindows 'adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1')
    # Synthetic admission evidence belongs only to this isolated integration
    # fixture. Source identities are real; independent native matrices run in CI.
    $evidencePath = Join-Path $package 'qualification.local.json'
    Write-Json $evidencePath (New-QualificationEvidence -AdapterSha256 (Get-Sha256 $originalAdapter) -ResealSha256 $resealHash)
    Set-RestrictedFileAcl $evidencePath
    $spec = [pscustomobject]@{
        approvalRoot = (Join-Path $scratchRoot 'external-approvals')
        approvalSecretRecordPath = $secretPath; approvalSecretSha256 = (Get-Sha256 $secretPath)
        channelHostBindingSha256 = ('a' * 64)
        approverIdentitySha256 = (Get-TextSha256 'isolated-approver')
        executionHostIdentitySha256 = (Get-TextSha256 'isolated-controller')
        maximumStateFiles = 32
        sourceHashes = [pscustomobject]@{
            initializer = (Get-Sha256 (Join-Path $packageWindows 'Initialize-SaefScopeBoundApprovalProfile.ps1'))
            runner = $script:runnerSha256; reseal = $resealHash; child = $script:childProcessContractSha256
            qualification = (Get-Sha256 $evidencePath)
        }
    }
    $before = [IO.File]::ReadAllBytes($channelPath)
    $generation = Join-Path $channelRoot 'installed-generation'
    $next = ConvertFrom-AdditionJson $before
    $next.standaloneModuleTargets[1].adapterPath = Join-Path $generation 'adapter.ps1'
    $next.standaloneModuleTargets[1].adapterPolicyPath = Join-Path $generation 'adapter-policy.local.json'
    $context = Get-ApprovalBootstrapContext $spec $package $packageWindows $account.Name (ConvertFrom-AdditionJson $before) $channelRoot
    $result = [ordered]@{ bindingMutationAttempted = $false; rollbackSucceeded = $null }
    Publish-UpdateGeneration $channelPath $generation $before ($utf8.GetBytes(($next | ConvertTo-Json -Depth 30))) `
        $adapterBytes ([IO.File]::ReadAllBytes($policyPath)) $context -DeploymentSid $additionDeploymentSid
    $installedChannel = Get-Content -LiteralPath $channelPath -Raw | ConvertFrom-Json
    $target = $installedChannel.standaloneModuleTargets[1]
    $approvalPolicy = Get-Content -LiteralPath $target.approvalPolicyPath -Raw | ConvertFrom-Json
    $ownTracksBefore = $installedChannel.standaloneModuleTargets[0] | ConvertTo-Json -Depth 30 -Compress
    foreach ($step in @(1, 2)) {
        $deploymentId = 'saef-pipeline-test-' + $step
        $deploymentRoot = Join-Path $stateRoot $deploymentId
        $candidatePath = Join-Path $managedRoot ($deploymentId + '-module')
        $null = [IO.Directory]::CreateDirectory($deploymentRoot)
        $inputCandidate = if ($step -eq 1) { $candidateSuccess } else { $candidateFailure }
        Copy-Item -LiteralPath $inputCandidate -Destination $candidatePath -Recurse
        $manifestPath = Join-Path $deploymentRoot 'deployment.json'
        $candidateIdentity = New-Manifest $candidatePath $deploymentId $manifestPath
        $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
        $manifest.targetDirectoryName = $deploymentId + '-module'
        Write-Json $manifestPath $manifest
        $deploymentStatusPath = Join-Path $deploymentRoot 'status.json'
        Write-Json $deploymentStatusPath @{ formatVersion = 1; phase = 'activation'; outcome = 'running'; exitCode = 0 }
        $transferPath = Join-Path $deploymentRoot 'package-transfer.json'
        Write-Json $transferPath @{ formatVersion = 1; packageSha256 = ('f' * 64); packageBytes = 1024 }
        $currentPolicy = Get-Content -LiteralPath $target.adapterPolicyPath -Raw | ConvertFrom-Json
        $plan = [pscustomobject]@{
            formatVersion = 1; channelVersion = 8; deploymentId = $deploymentId
            targetId = 'saef-media-carousel'; adapterProfile = 'saef-media-carousel-v1'
            qualificationProfile = $approvalPolicy.qualificationProfile; postflightProfile = $approvalPolicy.postflightProfile
            package = @{ sha256 = ('f' * 64); bytes = 1024 }
            operations = @('qualify', 'stage', 'preflight', 'activate', 'postflight', 'reseal', 'final_postflight', 'rollback')
            expectedBaselineIdentities = @{
                activePackageSha256 = $currentPolicy.expectedActivePackageIdentitySha256
                adapterPolicySha256 = (Get-Sha256 $target.adapterPolicyPath); channelPolicySha256 = (Get-Sha256 $channelPath)
            }
            channelHostBindingSha256 = $spec.channelHostBindingSha256
            riskScope = @{ allowlistChange = $false; serviceRestart = $false; providerContact = $false
                publication = $false; retentionDeletion = $false; activeIdentityReseal = $true }
        }
        $now = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
        $envelope = New-ApprovalEnvelope -Plan $plan -IssuedAt $now -ExpiresAt ($now + 600) `
            -ApproverIdentity 'isolated-approver' -ExecutionHostIdentity 'isolated-controller'
        $statusPath = Join-Path $deploymentRoot 'runner-status.json'
        $child = Invoke-SaefPowerShellChildProcess -ScriptPath $target.approvalRunnerPath `
            -ExpectedScriptSha256 $target.expectedApprovalRunnerSha256 -TimeoutSeconds 240 -MaximumOutputBytes 16384 `
            -Environment @{ SAEF_APPROVAL_ENVELOPE = $envelope } -Arguments @(
                '-ChildProcessContractPath', (Join-Path $packageWindows 'SaefChildProcess.ps1'),
                '-ExpectedChildProcessContractSha256', $script:childProcessContractSha256,
                '-ChannelPolicyPath', $channelPath, '-ManifestPath', $manifestPath, '-CandidatePath', $candidatePath,
                '-TransactionContractPath', $transactionPath, '-PackageTransferPath', $transferPath,
                '-AdapterPath', $target.adapterPath, '-AdapterPolicyPath', $target.adapterPolicyPath,
                '-ApprovalPolicyPath', $target.approvalPolicyPath, '-RpcUri', $RpcUri,
                '-CredentialPath', (Join-Path $scratchRoot 'credential.json'), '-DeploymentUser', $account.Name,
                '-DeploymentStatusPath', $deploymentStatusPath, '-StatusPath', $statusPath)
        if (-not (Test-Path -LiteralPath $statusPath)) { throw 'Pipeline runner produced no status.' }
        $status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
        if ($child.exitCode -ne 0 -or $status.outcome -cne 'activated') {
            Write-Output ($status | ConvertTo-Json -Depth 10 -Compress)
            throw 'Complete installed approval pipeline failed.'
        }
        $postChannel = Get-Content -LiteralPath $channelPath -Raw | ConvertFrom-Json
        $postPolicy = Get-Content -LiteralPath $target.adapterPolicyPath -Raw | ConvertFrom-Json
        if ((Get-PackageIdentity $activePath) -cne $candidateIdentity -or
            $postPolicy.expectedActivePackageIdentitySha256 -cne $candidateIdentity -or
            $postChannel.standaloneModuleTargets[1].expectedAdapterPolicySha256 -cne (Get-Sha256 $target.adapterPolicyPath) -or
            ($postPolicy.expectedInstances | ConvertTo-Json -Depth 10 -Compress) -cne ($policy.expectedInstances | ConvertTo-Json -Depth 10 -Compress) -or
            ($postChannel.standaloneModuleTargets[0] | ConvertTo-Json -Depth 30 -Compress) -cne $ownTracksBefore) {
            throw 'Pipeline changed unrelated bindings/configurations or did not advance active identity.'
        }
    }
    [Array]::Clear($script:secretBytes, 0, $script:secretBytes.Length)
    Write-Output 'PASS: two consecutive installed-profile/runner/adapter/resealer activations through loopback RPC.'
}
