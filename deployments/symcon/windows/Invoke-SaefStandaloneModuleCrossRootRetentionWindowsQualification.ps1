[CmdletBinding()]
param(
    [Parameter()]
    [string] $RetentionScriptPath = (Join-Path $PSScriptRoot 'Invoke-SaefStandaloneModuleCrossRootRetention.ps1'),

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedRetentionScriptSha256,

    [Parameter()]
    [string] $ChildProcessContractPath = (Join-Path $PSScriptRoot 'SaefChildProcess.ps1'),

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedChildProcessContractSha256,

    [Parameter()]
    [string] $StatusPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($StatusPath)) {
    $StatusPath = Join-Path $PSScriptRoot 'standalone-module-cross-root-retention-windows-qualification.local.json'
}

$ExitSuccess = 0
$ExitFailed = 10
$script:positiveCaseCount = 0
$script:negativeCaseCount = 0
$script:passedScenarios = @()
$script:failedCheck = 'platform'
$script:scratchMutationAttempted = $false
$script:scratchCleanupSucceeded = $false
$script:productionMutationAttempted = $false
$script:operationalMutationAttempted = $false
$scratchRoot = Join-Path $env:TEMP ('saef-cross-root-retention-' + [Guid]::NewGuid().ToString('N'))
$currentSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Assert-Condition {
    param(
        [Parameter(Mandatory = $true)][bool] $Condition,
        [Parameter(Mandatory = $true)][string] $Message
    )
    if (-not $Condition) { throw [InvalidOperationException]::new($Message) }
}

function Add-PassedScenario {
    param(
        [Parameter(Mandatory = $true)][string] $Name,
        [Parameter(Mandatory = $true)][ValidateSet('positive', 'negative')][string] $Kind
    )
    $script:passedScenarios += $Name
    if ($Kind -ceq 'positive') { $script:positiveCaseCount++ } else { $script:negativeCaseCount++ }
}

function Write-Utf8Json {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value
    )
    [IO.File]::WriteAllText(
        $Path,
        (($Value | ConvertTo-Json -Depth 20) + [Environment]::NewLine),
        [Text.UTF8Encoding]::new($false)
    )
}

function Set-ProtectedScratchAcl {
    param([Parameter(Mandatory = $true)][string] $Path)
    & icacls.exe $Path '/inheritance:r' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Cannot disable scratch ACL inheritance.' }
    & icacls.exe $Path '/remove:g' '*S-1-1-0' '*S-1-5-11' '*S-1-5-32-545' | Out-Null
    & icacls.exe $Path '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' `
        ($currentSid + ':(OI)(CI)F') | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Cannot grant scratch ACL.' }
    & icacls.exe $Path '/setowner' '*S-1-5-32-544' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Cannot set scratch ACL owner.' }
}

function New-ProtectedDirectory {
    param([Parameter(Mandatory = $true)][string] $Path)
    [IO.Directory]::CreateDirectory($Path) | Out-Null
    Set-ProtectedScratchAcl -Path $Path
}

function New-DeploymentUnit {
    param(
        [Parameter(Mandatory = $true)] $Context,
        [Parameter(Mandatory = $true)][string] $DeploymentId,
        [Parameter(Mandatory = $true)][string] $TargetId,
        [Parameter(Mandatory = $true)][string] $PackageSha256,
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][string] $CompletedUtc,
        [Parameter()][switch] $WithoutTransaction
    )
    $filesetName = $DeploymentId + '-fileset'
    $statePath = Join-Path $Context.stateRoot $DeploymentId
    $filesetPath = Join-Path $Context.filesetRoot $filesetName
    [IO.Directory]::CreateDirectory($statePath) | Out-Null
    [IO.Directory]::CreateDirectory($filesetPath) | Out-Null
    Write-Utf8Json -Path (Join-Path $statePath 'deployment.json') -Value ([ordered]@{
        formatVersion = 1
        deploymentId = $DeploymentId
        targetDirectoryName = $filesetName
        deploymentKind = 'standalone-module'
        module = [ordered]@{
            targetId = $TargetId
            packageIdentitySha256 = $PackageSha256
        }
    })
    Write-Utf8Json -Path (Join-Path $statePath 'status.json') -Value ([ordered]@{
        formatVersion = 1
        outcome = $Outcome
    })
    [IO.File]::WriteAllText(
        (Join-Path $filesetPath 'payload.txt'),
        ('payload-' + $DeploymentId),
        [Text.UTF8Encoding]::new($false)
    )
    $transactionName = ''
    if (-not $WithoutTransaction) {
        $transactionName = $DeploymentId + '-20260101T000000Z'
        $transactionPath = Join-Path $Context.adapterStateRoot $transactionName
        [IO.Directory]::CreateDirectory($transactionPath) | Out-Null
        [IO.Directory]::CreateDirectory((Join-Path $transactionPath 'rollback')) | Out-Null
        [IO.File]::WriteAllText(
            (Join-Path $transactionPath 'rollback/previous.txt'),
            ('previous-' + $DeploymentId),
            [Text.UTF8Encoding]::new($false)
        )
        Write-Utf8Json -Path (Join-Path $transactionPath 'transaction.json') -Value ([ordered]@{
            formatVersion = 1
            adapterProfile = 'saef-owntracks-position-map-v1'
            transactionDirectoryName = $transactionName
            deploymentId = $DeploymentId
            packageIdentitySha256 = $PackageSha256
            completedUtc = $CompletedUtc
            outcome = $Outcome
        })
    }
    return [pscustomobject]@{
        deploymentId = $DeploymentId
        filesetName = $filesetName
        transactionName = $transactionName
        packageIdentitySha256 = $PackageSha256
        statePath = $statePath
        filesetPath = $filesetPath
        transactionPath = if ($transactionName) { Join-Path $Context.adapterStateRoot $transactionName } else { '' }
    }
}

function New-RetentionFixture {
    param([Parameter(Mandatory = $true)][string] $Name)
    $root = Join-Path $scratchRoot $Name
    New-ProtectedDirectory -Path $root
    $context = [ordered]@{
        root = $root
        scriptsRoot = Join-Path $root 'scripts'
        filesetRoot = Join-Path $root 'filesets'
        stateRoot = Join-Path $root 'states'
        adapterStateRoot = Join-Path $root 'adapter-state'
        backupRoot = Join-Path $root 'backups'
        quarantineRoot = Join-Path $root 'quarantine'
        claimRoot = Join-Path $root 'claims'
        approvalRoot = Join-Path $root 'approvals'
        runtimeRoot = Join-Path $root 'runtime'
        outputRoot = Join-Path $root 'output'
    }
    foreach ($path in @(
        $context.scriptsRoot, $context.filesetRoot, $context.stateRoot, $context.adapterStateRoot,
        $context.backupRoot, $context.quarantineRoot, $context.claimRoot, $context.approvalRoot,
        $context.runtimeRoot, $context.outputRoot
    )) { New-ProtectedDirectory -Path $path }
    [IO.File]::WriteAllText((Join-Path $context.runtimeRoot '.lock'), 'x', [Text.UTF8Encoding]::new($false))

    $now = [DateTimeOffset]::UtcNow
    $context.active = New-DeploymentUnit -Context $context -DeploymentId 'saef-active' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('1' * 64) -Outcome 'activated' `
        -CompletedUtc $now.AddDays(-10).ToString('o')
    $context.recent = New-DeploymentUnit -Context $context -DeploymentId 'saef-recent' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('2' * 64) -Outcome 'activated' `
        -CompletedUtc $now.AddDays(-20).ToString('o')
    $context.young = New-DeploymentUnit -Context $context -DeploymentId 'saef-young' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('3' * 64) -Outcome 'activated' `
        -CompletedUtc $now.AddHours(-2).ToString('o')
    $context.candidateActivated = New-DeploymentUnit -Context $context -DeploymentId 'saef-old-activated' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('4' * 64) -Outcome 'activated' `
        -CompletedUtc $now.AddDays(-40).ToString('o')
    $context.recentRollback = New-DeploymentUnit -Context $context -DeploymentId 'saef-recent-rollback' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('5' * 64) -Outcome 'rolled_back' `
        -CompletedUtc $now.AddDays(-20).ToString('o')
    $context.candidateRollback = New-DeploymentUnit -Context $context -DeploymentId 'saef-old-rollback' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('6' * 64) -Outcome 'rolled_back' `
        -CompletedUtc $now.AddDays(-50).ToString('o')
    $context.referenced = New-DeploymentUnit -Context $context -DeploymentId 'saef-referenced' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('7' * 64) -Outcome 'activated' `
        -CompletedUtc $now.AddDays(-60).ToString('o')
    $context.manual = New-DeploymentUnit -Context $context -DeploymentId 'saef-manual' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('8' * 64) `
        -Outcome 'manual_recovery_required' -CompletedUtc $now.AddDays(-60).ToString('o')
    $context.staged = New-DeploymentUnit -Context $context -DeploymentId 'saef-staged' `
        -TargetId 'saef-owntracks-position-map' -PackageSha256 ('9' * 64) -Outcome 'staged' `
        -CompletedUtc $now.ToString('o') -WithoutTransaction
    $context.otherTarget = New-DeploymentUnit -Context $context -DeploymentId 'saef-other-target' `
        -TargetId 'saef-other-module' -PackageSha256 ('a' * 64) -Outcome 'staged' `
        -CompletedUtc $now.ToString('o') -WithoutTransaction

    Write-Utf8Json -Path (Join-Path $context.adapterStateRoot 'active.json') -Value ([ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-owntracks-position-map-v1'
        deploymentId = $context.active.deploymentId
        packageIdentitySha256 = $context.active.packageIdentitySha256
        transactionDirectoryName = $context.active.transactionName
    })
    Write-Utf8Json -Path (Join-Path $context.approvalRoot 'retained-reference.local.json') -Value ([ordered]@{
        deploymentId = $context.referenced.deploymentId
        packageIdentitySha256 = $context.referenced.packageIdentitySha256
    })

    $approvalPolicyPath = Join-Path $root 'approval-policy.local.json'
    Write-Utf8Json -Path $approvalPolicyPath -Value ([ordered]@{
        formatVersion = 1
        approvalStateRoot = $context.approvalRoot
    })
    $adapterPolicyPath = Join-Path $root 'adapter-policy.local.json'
    $confirmation = 'quarantine-saef-owntracks-position-map-cross-root-retention'
    Write-Utf8Json -Path $adapterPolicyPath -Value ([ordered]@{
        formatVersion = 1
        adapterProfile = 'saef-owntracks-position-map-v1'
        targetId = 'saef-owntracks-position-map'
        adapterStateRoot = $context.adapterStateRoot
        runtimeStateRoots = [ordered]@{ primary = $context.runtimeRoot }
        runtimeLockFiles = [ordered]@{ primary = '.lock' }
        mutexName = 'Global\SAEF.OwnTracksPositionMap.ModuleAdapter'
        expectedActivePackageIdentitySha256 = ('b' * 64)
        maximumStateBytes = 1048576
        retention = [ordered]@{
            profile = 'saef-channel-v8-standalone-module-cross-root-v1'
            implemented = $true
            backupRoot = $context.backupRoot
            quarantineRoot = $context.quarantineRoot
            claimRoot = $context.claimRoot
            requiredConfirmation = $confirmation
            minimumAgeHours = 24
            keepSuccessfulRollbackCount = 3
            keepFailedCandidateCount = 1
            maximumArtifactCount = 16
            maximumUnitBytes = 1048576
            maximumPlanBytes = 16777216
            maximumPlanAgeSeconds = 900
            maximumReferenceFiles = 16
            maximumReferenceBytes = 1048576
        }
    })
    $channelPolicyPath = Join-Path $root 'channel-policy.local.json'
    Write-Utf8Json -Path $channelPolicyPath -Value ([ordered]@{
        formatVersion = 1
        scriptsRoot = $context.scriptsRoot
        managedFilesetRoot = $context.filesetRoot
        stateRoot = $context.stateRoot
        adapterStateRoot = $context.adapterStateRoot
        standaloneModuleCrossRootRetentionPath = $RetentionScriptPath
        expectedStandaloneModuleCrossRootRetentionSha256 = $ExpectedRetentionScriptSha256
        standaloneModuleTargets = @([ordered]@{
            targetId = 'saef-owntracks-position-map'
            adapterProfile = 'saef-owntracks-position-map-v1'
            adapterPolicyPath = $adapterPolicyPath
            expectedAdapterPolicySha256 = Get-Sha256 -Path $adapterPolicyPath
            approvalPolicyPath = $approvalPolicyPath
            expectedApprovalPolicySha256 = Get-Sha256 -Path $approvalPolicyPath
        })
    })
    $context.adapterPolicyPath = $adapterPolicyPath
    $context.channelPolicyPath = $channelPolicyPath
    $context.confirmation = $confirmation
    return [pscustomobject] $context
}

function Invoke-Retention {
    param(
        [Parameter(Mandatory = $true)] $Context,
        [Parameter(Mandatory = $true)][ValidateSet('plan', 'apply', 'inspect')][string] $Operation,
        [Parameter(Mandatory = $true)][string] $PlanPath,
        [Parameter(Mandatory = $true)][string] $StatusPath,
        [Parameter()][string] $PlanSha256 = '',
        [Parameter()][string] $Confirmation = '',
        [Parameter(Mandatory = $true)][int] $ExpectedExitCode,
        [Parameter(Mandatory = $true)][string] $ExpectedOutcome
    )
    $arguments = @(
        '-Operation', $Operation,
        '-TargetId', 'saef-owntracks-position-map',
        '-ChannelPolicyPath', $Context.channelPolicyPath,
        '-ReviewPlanPath', $PlanPath,
        '-StatusPath', $StatusPath
    )
    if ($PlanSha256) { $arguments += @('-ExpectedReviewPlanSha256', $PlanSha256) }
    if ($Confirmation) { $arguments += @('-Confirmation', $Confirmation) }
    $child = Invoke-SaefPowerShellChildProcess -ScriptPath $RetentionScriptPath `
        -ExpectedScriptSha256 $ExpectedRetentionScriptSha256 -Arguments $arguments `
        -TimeoutSeconds 90 -MaximumOutputBytes 131072
    $stderr = [Text.Encoding]::UTF8.GetString([byte[]] $child.standardError)
    Assert-Condition -Condition ($child.terminationReason -ceq 'exited' -and
        $child.exitCode -eq $ExpectedExitCode) `
        -Message ('Unexpected retention child result: ' + $child.exitCode + ' ' + $stderr)
    Assert-Condition -Condition (Test-Path -LiteralPath $StatusPath -PathType Leaf) `
        -Message 'Retention child did not write its status.'
    $status = Get-Content -LiteralPath $StatusPath -Raw -Encoding UTF8 | ConvertFrom-Json
    Assert-Condition -Condition ([string] $status.outcome -ceq $ExpectedOutcome -and
        [int] $status.exitCode -eq $ExpectedExitCode) -Message 'Retention status differs.'
    foreach ($flag in @(
        'serviceRestartAttempted', 'liveSymconRpcContactAttempted', 'mqttPublishAttempted',
        'ownerMutationAttempted', 'eventMutationAttempted', 'deviceActionAttempted',
        'publicationAttempted', 'retentionDeletionAttempted'
    )) {
        Assert-Condition -Condition (-not [bool] $status.$flag) `
            -Message ('Forbidden action flag changed: ' + $flag)
    }
    return $status
}

function Add-DenyDeleteChildrenRule {
    param([Parameter(Mandatory = $true)][string] $Path)
    $acl = Get-Acl -LiteralPath $Path
    $rule = [Security.AccessControl.FileSystemAccessRule]::new(
        $currentSid,
        [Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles,
        [Security.AccessControl.InheritanceFlags]::None,
        [Security.AccessControl.PropagationFlags]::None,
        [Security.AccessControl.AccessControlType]::Deny
    )
    $null = $acl.AddAccessRule($rule)
    Set-Acl -LiteralPath $Path -AclObject $acl
}

function Add-DenyWriteDataRule {
    param([Parameter(Mandatory = $true)][string] $Path)
    $acl = Get-Acl -LiteralPath $Path
    $rule = [Security.AccessControl.FileSystemAccessRule]::new(
        $currentSid,
        [Security.AccessControl.FileSystemRights]::WriteData,
        [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor
            [Security.AccessControl.InheritanceFlags]::ObjectInherit,
        [Security.AccessControl.PropagationFlags]::None,
        [Security.AccessControl.AccessControlType]::Deny
    )
    $null = $acl.AddAccessRule($rule)
    Set-Acl -LiteralPath $Path -AclObject $acl
}

function Remove-CurrentUserDenyRules {
    param([Parameter(Mandatory = $true)][string] $Path)
    & icacls.exe $Path '/remove:d' ('*' + $currentSid) '/T' '/C' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Cannot remove synthetic deny rule.' }
}

function Write-QualificationStatus {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][string] $ErrorType = '',
        [Parameter()][string] $ErrorId = ''
    )
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'standalone_module_cross_root_retention_windows_qualification'
        outcome = $Outcome
        exitCode = $ExitCode
        windowsPowerShellVersion = [string] $PSVersionTable.PSVersion
        retentionScriptSha256 = if (Test-Path -LiteralPath $RetentionScriptPath) {
            Get-Sha256 -Path $RetentionScriptPath
        } else { '' }
        childProcessContractSha256 = if (Test-Path -LiteralPath $ChildProcessContractPath) {
            Get-Sha256 -Path $ChildProcessContractPath
        } else { '' }
        positiveCaseCount = $script:positiveCaseCount
        negativeCaseCount = $script:negativeCaseCount
        passedScenarios = @($script:passedScenarios)
        failedCheck = if ($ExitCode -eq 0) { '' } else { $script:failedCheck }
        errorType = $ErrorType
        errorId = $ErrorId
        scratchMutationAttempted = [bool] $script:scratchMutationAttempted
        scratchCleanupSucceeded = [bool] $script:scratchCleanupSucceeded
        productionMutationAttempted = [bool] $script:productionMutationAttempted
        operationalMutationAttempted = [bool] $script:operationalMutationAttempted
        liveSymconRpcContactAttempted = $false
        serviceRestartAttempted = $false
        mqttPublishAttempted = $false
        ownerMutationAttempted = $false
        eventMutationAttempted = $false
        deviceActionAttempted = $false
        publicationAttempted = $false
        retentionDeletionAttempted = $false
    }
    Write-Utf8Json -Path $StatusPath -Value $status
}

$exitCode = $ExitFailed
$errorType = ''
$errorId = ''
try {
    if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
        throw [PlatformNotSupportedException]::new('Windows PowerShell 5.1 is required.')
    }
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [UnauthorizedAccessException]::new('Qualification requires an elevated local administrator.')
    }
    foreach ($binding in @(
        @{ path = $RetentionScriptPath; hash = $ExpectedRetentionScriptSha256 },
        @{ path = $ChildProcessContractPath; hash = $ExpectedChildProcessContractSha256 }
    )) {
        if (-not [IO.Path]::IsPathRooted([string] $binding.path) -or
            -not (Test-Path -LiteralPath ([string] $binding.path) -PathType Leaf) -or
            (Get-Sha256 -Path ([string] $binding.path)) -cne [string] $binding.hash) {
            throw [Security.SecurityException]::new('Qualification source identity differs.')
        }
    }
    . $ChildProcessContractPath

    $script:failedCheck = 'windows-powershell-5.1-parser'
    $tokens = $null
    $parseErrors = $null
    $ast = [Management.Automation.Language.Parser]::ParseFile(
        $RetentionScriptPath,
        [ref] $tokens,
        [ref] $parseErrors
    )
    Assert-Condition -Condition (@($parseErrors).Count -eq 0) -Message 'Retention source does not parse.'
    Add-PassedScenario -Name 'windows-powershell-5.1-parse' -Kind positive

    $script:failedCheck = 'culture-invariant-functions'
    $functionNames = @('Get-OrdinalSortedObjects', 'ConvertFrom-RoundtripUtcTimestamp')
    foreach ($functionName in $functionNames) {
        $definition = @($ast.FindAll({ param($node)
            $node -is [Management.Automation.Language.FunctionDefinitionAst] -and
                $node.Name -ceq $functionName
        }, $false))
        Assert-Condition -Condition ($definition.Count -eq 1) `
            -Message ('Function not unique: ' + $functionName)
        . ([scriptblock]::Create($definition[0].Extent.Text))
    }
    foreach ($cultureName in @('en-US', 'de-DE', 'tr-TR')) {
        $previousCulture = [Threading.Thread]::CurrentThread.CurrentCulture
        $previousUiCulture = [Threading.Thread]::CurrentThread.CurrentUICulture
        try {
            $culture = [Globalization.CultureInfo]::GetCultureInfo($cultureName)
            [Threading.Thread]::CurrentThread.CurrentCulture = $culture
            [Threading.Thread]::CurrentThread.CurrentUICulture = $culture
            $values = @(
                [pscustomobject]@{ key = 'i' }, [pscustomobject]@{ key = 'A' },
                [pscustomobject]@{ key = 'a' }, [pscustomobject]@{ key = 'I' }
            )
            $ordered = @(Get-OrdinalSortedObjects -Values $values -Property key)
            Assert-Condition -Condition ((@($ordered | ForEach-Object { $_.key }) -join ',') -ceq 'A,I,a,i') `
                -Message ('Ordinal vector differs under ' + $cultureName)
            $timestamp = ConvertFrom-RoundtripUtcTimestamp -Value '2026-10-03T08:15:30.1234567+02:00'
            Assert-Condition -Condition ($timestamp.ToString('o') -ceq '2026-10-03T06:15:30.1234567+00:00') `
                -Message ('Round-trip timestamp differs under ' + $cultureName)
        } finally {
            [Threading.Thread]::CurrentThread.CurrentCulture = $previousCulture
            [Threading.Thread]::CurrentThread.CurrentUICulture = $previousUiCulture
        }
    }
    Add-PassedScenario -Name 'ordinal-and-roundtrip-vectors-under-three-cultures' -Kind positive

    New-ProtectedDirectory -Path $scratchRoot
    $script:scratchMutationAttempted = $true
    $context = New-RetentionFixture -Name 'main'

    $script:failedCheck = 'reparse-negative'
    $junctionTarget = Join-Path $context.root 'junction-target'
    [IO.Directory]::CreateDirectory($junctionTarget) | Out-Null
    $junctionPath = Join-Path $context.candidateActivated.filesetPath 'unexpected-junction'
    New-Item -ItemType Junction -Path $junctionPath -Target $junctionTarget | Out-Null
    $null = Invoke-Retention -Context $context -Operation plan `
        -PlanPath (Join-Path $context.outputRoot 'reparse-plan.json') `
        -StatusPath (Join-Path $context.outputRoot 'reparse-status.json') `
        -ExpectedExitCode 10 -ExpectedOutcome failed
    Remove-Item -LiteralPath $junctionPath -Force
    Add-PassedScenario -Name 'reparse-point-fails-before-plan' -Kind negative

    $script:failedCheck = 'broad-acl-negative'
    & icacls.exe $context.backupRoot '/grant' '*S-1-5-32-545:(OI)(CI)M' | Out-Null
    Assert-Condition -Condition ($LASTEXITCODE -eq 0) -Message 'Cannot create broad ACL fixture.'
    $null = Invoke-Retention -Context $context -Operation plan `
        -PlanPath (Join-Path $context.outputRoot 'acl-plan.json') `
        -StatusPath (Join-Path $context.outputRoot 'acl-status.json') `
        -ExpectedExitCode 10 -ExpectedOutcome failed
    Set-ProtectedScratchAcl -Path $context.backupRoot
    Add-PassedScenario -Name 'broad-write-acl-fails-before-plan' -Kind negative

    $script:failedCheck = 'unpaired-negative'
    $orphan = Join-Path $context.adapterStateRoot 'saef-orphan-20260101T000000Z'
    [IO.Directory]::CreateDirectory($orphan) | Out-Null
    Write-Utf8Json -Path (Join-Path $orphan 'transaction.json') -Value ([ordered]@{
        formatVersion = 1; adapterProfile = 'saef-owntracks-position-map-v1'
        transactionDirectoryName = 'saef-orphan-20260101T000000Z'; deploymentId = 'saef-orphan'
        packageIdentitySha256 = ('c' * 64); completedUtc = [DateTimeOffset]::UtcNow.AddDays(-30).ToString('o')
        outcome = 'activated'
    })
    $null = Invoke-Retention -Context $context -Operation plan `
        -PlanPath (Join-Path $context.outputRoot 'orphan-plan.json') `
        -StatusPath (Join-Path $context.outputRoot 'orphan-status.json') `
        -ExpectedExitCode 10 -ExpectedOutcome failed
    Remove-Item -LiteralPath $orphan -Recurse -Force
    Add-PassedScenario -Name 'unpaired-cross-root-artifact-fails-before-plan' -Kind negative

    $script:failedCheck = 'read-only-plan-positive'
    $planPath = Join-Path $context.outputRoot 'review-plan.local.json'
    $planStatusPath = Join-Path $context.outputRoot 'plan-status.local.json'
    $planStatus = Invoke-Retention -Context $context -Operation plan -PlanPath $planPath `
        -StatusPath $planStatusPath -ExpectedExitCode 0 -ExpectedOutcome planned
    $plan = Get-Content -LiteralPath $planPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $candidateIds = @($plan.candidates | ForEach-Object { [string] $_.deploymentId })
    Assert-Condition -Condition ($plan.candidateCount -eq 2 -and
        ($candidateIds -join ',') -ceq 'saef-old-activated,saef-old-rollback' -and
        -not $planStatus.operationalMutationAttempted -and
        -not $planStatus.backupMutationAttempted) -Message 'Read-only plan selection differs.'
    foreach ($protected in @(
        $context.active, $context.recent, $context.young, $context.referenced,
        $context.manual, $context.staged, $context.otherTarget
    )) {
        Assert-Condition -Condition (Test-Path -LiteralPath $protected.statePath -PathType Container) `
            -Message ('Protected unit changed during plan: ' + $protected.deploymentId)
    }
    Add-PassedScenario -Name 'three-root-plan-protects-active-staged-recent-young-referenced-manual-and-cross-target' `
        -Kind positive
    $planSha256 = Get-Sha256 -Path $planPath

    $script:failedCheck = 'plan-hash-negative'
    $null = Invoke-Retention -Context $context -Operation apply -PlanPath $planPath `
        -StatusPath (Join-Path $context.outputRoot 'wrong-hash-status.json') `
        -PlanSha256 ('d' * 64) -Confirmation $context.confirmation `
        -ExpectedExitCode 10 -ExpectedOutcome failed
    Assert-Condition -Condition (@(Get-ChildItem -LiteralPath $context.claimRoot -Force).Count -eq 0) `
        -Message 'Wrong plan hash created a claim.'
    Add-PassedScenario -Name 'plan-hash-mismatch-fails-before-claim' -Kind negative

    $script:failedCheck = 'confirmation-negative'
    $null = Invoke-Retention -Context $context -Operation apply -PlanPath $planPath `
        -StatusPath (Join-Path $context.outputRoot 'wrong-confirmation-status.json') `
        -PlanSha256 $planSha256 -Confirmation 'wrong' -ExpectedExitCode 10 -ExpectedOutcome failed
    Add-PassedScenario -Name 'wrong-confirmation-fails-before-claim' -Kind negative

    $script:failedCheck = 'channel-lock-negative'
    $heldMutex = [Threading.Mutex]::new($false, 'Global\SAEF.DeploymentChannel')
    $held = $heldMutex.WaitOne(0)
    Assert-Condition -Condition $held -Message 'Cannot acquire qualification channel mutex.'
    try {
        $null = Invoke-Retention -Context $context -Operation apply -PlanPath $planPath `
            -StatusPath (Join-Path $context.outputRoot 'mutex-status.json') `
            -PlanSha256 $planSha256 -Confirmation $context.confirmation `
            -ExpectedExitCode 10 -ExpectedOutcome failed
    } finally {
        if ($held) { $heldMutex.ReleaseMutex() }
        $heldMutex.Dispose()
    }
    Add-PassedScenario -Name 'channel-lock-contention-fails-before-claim' -Kind negative

    $script:failedCheck = 'inventory-drift-negative'
    $driftPath = Join-Path $context.candidateActivated.filesetPath 'drift.txt'
    [IO.File]::WriteAllText($driftPath, 'drift', [Text.UTF8Encoding]::new($false))
    $null = Invoke-Retention -Context $context -Operation apply -PlanPath $planPath `
        -StatusPath (Join-Path $context.outputRoot 'drift-status.json') `
        -PlanSha256 $planSha256 -Confirmation $context.confirmation `
        -ExpectedExitCode 10 -ExpectedOutcome failed
    Remove-Item -LiteralPath $driftPath -Force
    Add-PassedScenario -Name 'inventory-drift-fails-before-claim' -Kind negative

    $script:failedCheck = 'backup-failure-negative'
    $backupContext = New-RetentionFixture -Name 'backup-failure'
    $backupPlanPath = Join-Path $backupContext.outputRoot 'review-plan.local.json'
    $null = Invoke-Retention -Context $backupContext -Operation plan -PlanPath $backupPlanPath `
        -StatusPath (Join-Path $backupContext.outputRoot 'plan-status.json') `
        -ExpectedExitCode 0 -ExpectedOutcome planned
    $backupPlanSha256 = Get-Sha256 -Path $backupPlanPath
    Add-DenyWriteDataRule -Path $backupContext.backupRoot
    $backupFailure = Invoke-Retention -Context $backupContext -Operation apply -PlanPath $backupPlanPath `
        -StatusPath (Join-Path $backupContext.outputRoot 'apply-status.json') `
        -PlanSha256 $backupPlanSha256 -Confirmation $backupContext.confirmation `
        -ExpectedExitCode 20 -ExpectedOutcome failed
    Remove-CurrentUserDenyRules -Path $backupContext.backupRoot
    Set-ProtectedScratchAcl -Path $backupContext.backupRoot
    Assert-Condition -Condition (-not $backupFailure.operationalMutationAttempted -and
        (Test-Path -LiteralPath $backupFailure.backupPath -PathType Container) -and
        (Test-Path -LiteralPath $backupContext.candidateActivated.statePath -PathType Container)) `
        -Message 'Backup failure boundary differs.'
    $backupInspect = Invoke-Retention -Context $backupContext -Operation inspect -PlanPath $backupPlanPath `
        -StatusPath (Join-Path $backupContext.outputRoot 'inspect-status.json') `
        -PlanSha256 $backupPlanSha256 -ExpectedExitCode 0 `
        -ExpectedOutcome failed_before_operational_mutation
    Assert-Condition -Condition (-not $backupInspect.operationalMutationAttempted) `
        -Message 'Backup-failure inspect mutated the fixture.'
    Add-PassedScenario -Name 'backup-failure-retains-evidence-and-terminal-read-only-inspect' -Kind negative

    $script:failedCheck = 'partial-move-rollback'
    $rollbackContext = New-RetentionFixture -Name 'partial-move'
    $rollbackPlanPath = Join-Path $rollbackContext.outputRoot 'review-plan.local.json'
    $null = Invoke-Retention -Context $rollbackContext -Operation plan -PlanPath $rollbackPlanPath `
        -StatusPath (Join-Path $rollbackContext.outputRoot 'plan-status.json') `
        -ExpectedExitCode 0 -ExpectedOutcome planned
    $rollbackPlanSha256 = Get-Sha256 -Path $rollbackPlanPath
    Add-DenyDeleteChildrenRule -Path $rollbackContext.filesetRoot
    $rollbackStatus = Invoke-Retention -Context $rollbackContext -Operation apply -PlanPath $rollbackPlanPath `
        -StatusPath (Join-Path $rollbackContext.outputRoot 'apply-status.json') `
        -PlanSha256 $rollbackPlanSha256 -Confirmation $rollbackContext.confirmation `
        -ExpectedExitCode 30 -ExpectedOutcome rolled_back
    Assert-Condition -Condition ($rollbackStatus.rollbackAttempted -and $rollbackStatus.rollbackSucceeded -and
        (Test-Path -LiteralPath $rollbackContext.candidateActivated.transactionPath -PathType Container) -and
        (Test-Path -LiteralPath $rollbackContext.candidateActivated.statePath -PathType Container) -and
        (Test-Path -LiteralPath $rollbackContext.candidateActivated.filesetPath -PathType Container)) `
        -Message 'Partial-move rollback did not restore all roots.'
    Remove-CurrentUserDenyRules -Path $rollbackContext.filesetRoot
    Set-ProtectedScratchAcl -Path $rollbackContext.filesetRoot
    Add-PassedScenario -Name 'partial-move-failure-restores-all-moved-roots' -Kind positive

    $script:failedCheck = 'apply-positive'
    $applyStatus = Invoke-Retention -Context $context -Operation apply -PlanPath $planPath `
        -StatusPath (Join-Path $context.outputRoot 'apply-status.local.json') `
        -PlanSha256 $planSha256 -Confirmation $context.confirmation `
        -ExpectedExitCode 0 -ExpectedOutcome quarantined
    $script:operationalMutationAttempted = [bool] $applyStatus.operationalMutationAttempted
    Assert-Condition -Condition ($applyStatus.operationalMutationAttempted -and
        $applyStatus.backupMutationAttempted -and $applyStatus.quarantineMutationAttempted -and
        -not (Test-Path -LiteralPath $context.candidateActivated.statePath) -and
        -not (Test-Path -LiteralPath $context.candidateRollback.statePath) -and
        (Test-Path -LiteralPath $applyStatus.backupPath -PathType Container) -and
        (Test-Path -LiteralPath $applyStatus.quarantinePath -PathType Container)) `
        -Message 'Positive quarantine result differs.'
    Add-PassedScenario -Name 'one-time-byte-exact-backup-and-three-root-quarantine' -Kind positive

    $script:failedCheck = 'inspect-positive'
    $inspectStatus = Invoke-Retention -Context $context -Operation inspect -PlanPath $planPath `
        -StatusPath (Join-Path $context.outputRoot 'inspect-status.local.json') `
        -PlanSha256 $planSha256 -ExpectedExitCode 0 -ExpectedOutcome quarantined
    Assert-Condition -Condition (-not $inspectStatus.operationalMutationAttempted -and
        [string] $inspectStatus.claimPhase -ceq 'completed') -Message 'Read-only terminal inspect differs.'
    Add-PassedScenario -Name 'terminal-read-only-inspect' -Kind positive

    $script:failedCheck = 'replay-negative'
    $null = Invoke-Retention -Context $context -Operation apply -PlanPath $planPath `
        -StatusPath (Join-Path $context.outputRoot 'replay-status.json') `
        -PlanSha256 $planSha256 -Confirmation $context.confirmation `
        -ExpectedExitCode 10 -ExpectedOutcome failed
    Add-PassedScenario -Name 'claim-replay-fails-before-second-mutation' -Kind negative

    $exitCode = $ExitSuccess
} catch {
    $errorType = $_.Exception.GetType().FullName
    $errorId = $_.FullyQualifiedErrorId
} finally {
    if ($scratchRoot -and (Split-Path -Leaf $scratchRoot) -match '^saef-cross-root-retention-[a-f0-9]{32}$' -and
        (Test-Path -LiteralPath $scratchRoot -PathType Container)) {
        try {
            & icacls.exe $scratchRoot '/remove:d' ('*' + $currentSid) '/T' '/C' | Out-Null
            Remove-Item -LiteralPath $scratchRoot -Recurse -Force
            $script:scratchCleanupSucceeded = -not (Test-Path -LiteralPath $scratchRoot)
        } catch { $script:scratchCleanupSucceeded = $false }
    } else { $script:scratchCleanupSucceeded = $true }
    if ($exitCode -eq 0 -and -not $script:scratchCleanupSucceeded) {
        $exitCode = $ExitFailed
        $script:failedCheck = 'scratch-cleanup'
        $errorType = 'System.IO.IOException'
        $errorId = 'Synthetic scratch cleanup failed.'
    }
    Write-QualificationStatus -Outcome $(if ($exitCode -eq 0) { 'passed' } else { 'failed' }) `
        -ExitCode $exitCode -ErrorType $errorType -ErrorId $errorId
}

exit $exitCode
