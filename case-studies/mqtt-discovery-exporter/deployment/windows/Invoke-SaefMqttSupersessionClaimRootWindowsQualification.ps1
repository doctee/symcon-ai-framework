[CmdletBinding()]
param(
    [Parameter()]
    [string] $InitializerPath = (Join-Path $PSScriptRoot `
        'Initialize-SaefMqttSupersessionClaimRoot.ps1'),

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedInitializerSha256,

    [Parameter()]
    [string] $ChildProcessContractPath = (Join-Path $PSScriptRoot 'SaefChildProcess.ps1'),

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-f0-9]{64}$')]
    [string] $ExpectedChildProcessContractSha256,

    [Parameter()]
    [string] $StatusPath = (Join-Path $PSScriptRoot `
        'mqtt-supersession-claim-root-windows-qualification.local.json')
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ExitSuccess = 0
$ExitFailed = 10
$script:positiveCaseCount = 0
$script:negativeCaseCount = 0
$script:scratchMutationAttempted = $false
$script:scratchCleanupSucceeded = $false
$script:failedCheck = 'source_identity'
$script:failureType = ''
$script:failureId = ''
$scratchRoot = Join-Path $env:TEMP (
    'saef-mqtt-supersession-qualification-' + [Guid]::NewGuid().ToString('N')
)

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)

    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Write-AtomicJson {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)] $Value
    )

    $directory = Split-Path -Parent $Path
    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Qualification status directory is missing.')
    }
    $temporary = Join-Path $directory ('.saef-mqtt-qualification-' + [Guid]::NewGuid().ToString('N') + '.tmp')
    $backup = Join-Path $directory ('.saef-mqtt-qualification-' + [Guid]::NewGuid().ToString('N') + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            ($Value | ConvertTo-Json -Depth 5) + [Environment]::NewLine,
            [Text.UTF8Encoding]::new($false)
        )
        if (Test-Path -LiteralPath $Path -PathType Leaf) {
            [IO.File]::Replace($temporary, $Path, $backup)
        } else {
            [IO.File]::Move($temporary, $Path)
        }
    } finally {
        foreach ($pathToRemove in @($temporary, $backup)) {
            if (Test-Path -LiteralPath $pathToRemove -PathType Leaf) {
                Remove-Item -LiteralPath $pathToRemove -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

function Write-QualificationStatus {
    param([Parameter(Mandatory = $true)][string] $Outcome)

    Write-AtomicJson -Path $StatusPath -Value ([ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'mqtt_supersession_claim_root_windows_qualification'
        outcome = $Outcome
        exitCode = if ($Outcome -ceq 'passed') { $ExitSuccess } else { $ExitFailed }
        windowsPowerShellVersion = [string] $PSVersionTable.PSVersion
        initializerSha256 = if (Test-Path -LiteralPath $InitializerPath -PathType Leaf) {
            Get-Sha256 -Path $InitializerPath
        } else { '' }
        childProcessContractSha256 = if (
            Test-Path -LiteralPath $ChildProcessContractPath -PathType Leaf
        ) {
            Get-Sha256 -Path $ChildProcessContractPath
        } else { '' }
        positiveCaseCount = $script:positiveCaseCount
        negativeCaseCount = $script:negativeCaseCount
        scratchMutationAttempted = [bool] $script:scratchMutationAttempted
        scratchCleanupSucceeded = [bool] $script:scratchCleanupSucceeded
        failedCheck = if ($Outcome -ceq 'passed') { '' } else { $script:failedCheck }
        errorType = if ($Outcome -ceq 'passed') { '' } else { $script:failureType }
        errorId = if ($Outcome -ceq 'passed') { '' } else { $script:failureId }
        productionMutationAttempted = $false
        liveSymconRpcContactAttempted = $false
        ownerMutationAttempted = $false
        eventMutationAttempted = $false
        mqttPublishAttempted = $false
        deviceActionAttempted = $false
        serviceRestartAttempted = $false
        publicationAttempted = $false
        retentionCleanupAttempted = $false
    })
}

function Assert-Condition {
    param(
        [Parameter(Mandatory = $true)][bool] $Condition,
        [Parameter(Mandatory = $true)][string] $Message
    )

    if (-not $Condition) {
        throw [InvalidOperationException]::new($Message)
    }
}

function Assert-BoundedSource {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $ExpectedSha256
    )

    if (-not [IO.Path]::IsPathRooted($Path) -or
        -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Sha256 -Path $Path) -cne $ExpectedSha256) {
        throw [Security.SecurityException]::new('Qualification source identity differs.')
    }
    $item = Get-Item -LiteralPath $Path -Force
    if ([long] $item.Length -gt 4194304 -or
        (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.IOException]::new('Qualification source is unsafe.')
    }
}

function Set-ProtectedScratchAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    & icacls.exe $Path '/inheritance:r' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot disable scratch ACL inheritance.')
    }
    & icacls.exe $Path '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' |
        Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot grant the protected scratch ACL.')
    }
    & icacls.exe $Path '/setowner' '*S-1-5-32-544' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot set the protected scratch owner.')
    }
}

function Read-ScenarioStatus {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw [InvalidOperationException]::new('Initializer scenario did not write status.')
    }
    return Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
}

function Invoke-InitializerScenario {
    param(
        [Parameter(Mandatory = $true)][string] $Label,
        [Parameter(Mandatory = $true)][string] $Operation,
        [Parameter(Mandatory = $true)][string] $ClaimRoot,
        [Parameter(Mandatory = $true)][int] $ExpectedExitCode,
        [Parameter()][string] $Confirmation = '',
        [Parameter()][switch] $InjectPostAclFailure,
        [Parameter()][switch] $InjectCreationCollision
    )

    Write-Host ('[SAEF] scenario-start: ' + $Label)
    $statusPath = Join-Path (Split-Path -Parent $ClaimRoot) ($Label + '-status.local.json')
    $arguments = @(
        '-Operation', $Operation,
        '-ClaimRootPath', $ClaimRoot,
        '-StatusPath', $statusPath,
        '-QualificationMode'
    )
    if (-not [string]::IsNullOrEmpty($Confirmation)) {
        $arguments += @('-Confirmation', $Confirmation)
    }
    if ($InjectPostAclFailure) {
        $arguments += '-InjectPostAclFailure'
    }
    if ($InjectCreationCollision) {
        $arguments += '-InjectCreationCollision'
    }
    $result = Invoke-SaefPowerShellChildProcess -ScriptPath $InitializerPath `
        -ExpectedScriptSha256 $ExpectedInitializerSha256 `
        -Arguments $arguments -TimeoutSeconds 60 -MaximumOutputBytes 65536
    $status = Read-ScenarioStatus -Path $statusPath
    Assert-Condition -Condition ([int] $result.exitCode -eq $ExpectedExitCode) `
        -Message ('Initializer process exit differs: ' + $Label)
    Assert-Condition -Condition ([int] $status.exitCode -eq $ExpectedExitCode) `
        -Message ('Initializer status exit differs: ' + $Label)
    Assert-Condition -Condition (-not [bool] $status.productionMutationAttempted -and
        -not [bool] $status.liveSymconRpcContactAttempted -and
        -not [bool] $status.ownerMutationAttempted -and
        -not [bool] $status.eventMutationAttempted -and
        -not [bool] $status.mqttPublishAttempted -and
        -not [bool] $status.deviceActionAttempted -and
        -not [bool] $status.serviceRestartAttempted -and
        -not [bool] $status.publicationAttempted -and
        -not [bool] $status.retentionCleanupAttempted) `
        -Message ('Initializer scenario escaped the scratch boundary: ' + $Label)
    Write-Host ('[SAEF] scenario-finished: ' + $Label + ' exit=' + $ExpectedExitCode)
    return $status
}

function New-ScenarioParent {
    param([Parameter(Mandatory = $true)][string] $Label)

    $path = Join-Path $scratchRoot (
        'saef-mqtt-supersession-qualification-' + $Label + '-' + [Guid]::NewGuid().ToString('N')
    )
    [IO.Directory]::CreateDirectory($path) | Out-Null
    Set-ProtectedScratchAcl -Path $path
    return $path
}

function New-ProductionLikeParent {
    param([Parameter(Mandatory = $true)][string] $Label)

    $container = Join-Path $scratchRoot (
        'programdata-like-' + $Label + '-' + [Guid]::NewGuid().ToString('N')
    )
    [IO.Directory]::CreateDirectory($container) | Out-Null

    $system = [Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $administrators = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    $creatorOwner = [Security.Principal.SecurityIdentifier]::new('S-1-3-0')
    $users = [Security.Principal.SecurityIdentifier]::new('S-1-5-32-545')
    $containerAndObjects = [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor
        [Security.AccessControl.InheritanceFlags]::ObjectInherit
    $allow = [Security.AccessControl.AccessControlType]::Allow
    $acl = [Security.AccessControl.DirectorySecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetOwner($administrators)
    foreach ($sid in @($system, $administrators)) {
        $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
            $sid,
            [Security.AccessControl.FileSystemRights]::FullControl,
            $containerAndObjects,
            [Security.AccessControl.PropagationFlags]::None,
            $allow
        ))
    }
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        $creatorOwner,
        [Security.AccessControl.FileSystemRights]::FullControl,
        $containerAndObjects,
        [Security.AccessControl.PropagationFlags]::InheritOnly,
        $allow
    ))
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        $users,
        [Security.AccessControl.FileSystemRights]::ReadAndExecute -bor
            [Security.AccessControl.FileSystemRights]::Synchronize,
        $containerAndObjects,
        [Security.AccessControl.PropagationFlags]::None,
        $allow
    ))
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        $users,
        [Security.AccessControl.FileSystemRights]::Write,
        [Security.AccessControl.InheritanceFlags]::ContainerInherit,
        [Security.AccessControl.PropagationFlags]::None,
        $allow
    ))
    Set-Acl -LiteralPath $container -AclObject $acl

    $parent = Join-Path $container (
        'saef-mqtt-supersession-qualification-' + $Label + '-' +
        [Guid]::NewGuid().ToString('N')
    )
    [IO.Directory]::CreateDirectory($parent) | Out-Null
    & icacls.exe $parent '/setowner' '*S-1-5-32-544' | Out-Null
    Assert-Condition -Condition ($LASTEXITCODE -eq 0) `
        -Message 'Production-like parent owner setup failed.'
    $parentAcl = Get-Acl -LiteralPath $parent
    Assert-Condition -Condition (-not [bool] $parentAcl.AreAccessRulesProtected) `
        -Message 'Production-like parent unexpectedly has a protected DACL.'
    return $parent
}

function Add-UntrustedParentDeleteChildAccess {
    param([Parameter(Mandatory = $true)][string] $Path)

    $acl = Get-Acl -LiteralPath $Path
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        [Security.Principal.SecurityIdentifier]::new('S-1-5-32-545'),
        [Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles,
        [Security.AccessControl.InheritanceFlags]::None,
        [Security.AccessControl.PropagationFlags]::None,
        [Security.AccessControl.AccessControlType]::Allow
    ))
    Set-Acl -LiteralPath $Path -AclObject $acl
}

try {
    Write-Host '[SAEF] platform-check'
    if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT -or
        $PSVersionTable.PSVersion.Major -ne 5 -or
        $PSVersionTable.PSEdition -cne 'Desktop') {
        throw [PlatformNotSupportedException]::new('Qualification requires Windows PowerShell 5.1.')
    }

    Write-Host '[SAEF] source-identity'
    Assert-BoundedSource -Path $InitializerPath -ExpectedSha256 $ExpectedInitializerSha256
    Assert-BoundedSource -Path $ChildProcessContractPath `
        -ExpectedSha256 $ExpectedChildProcessContractSha256
    $script:positiveCaseCount++

    Write-Host '[SAEF] windows-powershell-5.1-parser'
    foreach ($path in @($InitializerPath, $ChildProcessContractPath)) {
        $tokens = $null
        $parseErrors = $null
        [Management.Automation.Language.Parser]::ParseFile(
            $path,
            [ref] $tokens,
            [ref] $parseErrors
        ) | Out-Null
        Assert-Condition -Condition (@($parseErrors).Count -eq 0) `
            -Message 'Windows PowerShell parser rejected a qualification source.'
    }
    $script:positiveCaseCount++

    [IO.Directory]::CreateDirectory($scratchRoot) | Out-Null
    Set-ProtectedScratchAcl -Path $scratchRoot
    $script:scratchMutationAttempted = $true
    . $ChildProcessContractPath
    if ($null -eq (Get-Command Invoke-SaefPowerShellChildProcess -CommandType Function `
            -ErrorAction SilentlyContinue)) {
        throw [InvalidOperationException]::new('Secure child-process function is unavailable.')
    }

    $script:failedCheck = 'read_only_preflight'
    $productionLikeParent = New-ProductionLikeParent -Label 'positive'
    $claimRoot = Join-Path $productionLikeParent 'MqttSupersessionOwnerMigrationClaims'
    $preflight = Invoke-InitializerScenario -Label 'preflight-missing' -Operation 'preflight' `
        -ClaimRoot $claimRoot -ExpectedExitCode 0
    Assert-Condition -Condition ([string] $preflight.outcome -ceq 'passed' -and
        [bool] $preflight.repairRequired -and
        -not [bool] $preflight.creationAttempted -and
        -not (Test-Path -LiteralPath $claimRoot)) `
        -Message 'Missing-root preflight changed scratch state.'
    $script:positiveCaseCount++

    $script:failedCheck = 'confirmation_negative'
    $wrongConfirmation = Invoke-InitializerScenario -Label 'wrong-confirmation' `
        -Operation 'install' -ClaimRoot $claimRoot -ExpectedExitCode 20 `
        -Confirmation 'wrong-confirmation'
    Assert-Condition -Condition ([string] $wrongConfirmation.outcome -ceq 'failed' -and
        -not [bool] $wrongConfirmation.creationAttempted -and
        -not (Test-Path -LiteralPath $claimRoot)) `
        -Message 'Wrong confirmation reached claim-root creation.'
    $script:negativeCaseCount++

    $script:failedCheck = 'protected_installation'
    $installed = Invoke-InitializerScenario -Label 'install-positive' -Operation 'install' `
        -ClaimRoot $claimRoot -ExpectedExitCode 0 `
        -Confirmation 'provision-saef-mqtt-supersession-claim-root'
    Assert-Condition -Condition ([string] $installed.outcome -ceq 'installed' -and
        [bool] $installed.claimRootCreated -and [bool] $installed.aclMutationAttempted -and
        (Test-Path -LiteralPath $claimRoot -PathType Container)) `
        -Message 'Protected scratch claim-root installation differs.'
    $script:positiveCaseCount++

    $script:failedCheck = 'idempotent_postflight'
    $postflight = Invoke-InitializerScenario -Label 'postflight-existing' -Operation 'preflight' `
        -ClaimRoot $claimRoot -ExpectedExitCode 0
    Assert-Condition -Condition ([string] $postflight.outcome -ceq 'passed' -and
        -not [bool] $postflight.repairRequired -and
        -not [bool] $postflight.creationAttempted -and
        -not [bool] $postflight.aclMutationAttempted) `
        -Message 'Existing protected claim-root postflight differs.'
    $script:positiveCaseCount++

    $script:failedCheck = 'broad_acl_negative'
    $broadParent = New-ScenarioParent -Label 'broad'
    $broadRoot = Join-Path $broadParent 'MqttSupersessionOwnerMigrationClaims'
    [IO.Directory]::CreateDirectory($broadRoot) | Out-Null
    & icacls.exe $broadRoot '/inheritance:e' '/grant' '*S-1-5-32-545:(OI)(CI)M' | Out-Null
    Assert-Condition -Condition ($LASTEXITCODE -eq 0) -Message 'Broad ACL fixture setup failed.'
    $broad = Invoke-InitializerScenario -Label 'broad-acl' -Operation 'preflight' `
        -ClaimRoot $broadRoot -ExpectedExitCode 10
    Assert-Condition -Condition ([string] $broad.outcome -ceq 'failed' -and
        -not [bool] $broad.aclMutationAttempted) `
        -Message 'Broad existing ACL was changed or accepted.'
    $script:negativeCaseCount++

    $script:failedCheck = 'path_collision_negative'
    $collisionParent = New-ScenarioParent -Label 'collision'
    $collisionRoot = Join-Path $collisionParent 'MqttSupersessionOwnerMigrationClaims'
    [IO.File]::WriteAllText($collisionRoot, 'collision', [Text.UTF8Encoding]::new($false))
    $collision = Invoke-InitializerScenario -Label 'path-collision' -Operation 'preflight' `
        -ClaimRoot $collisionRoot -ExpectedExitCode 10
    Assert-Condition -Condition ([string] $collision.outcome -ceq 'failed' -and
        -not [bool] $collision.creationAttempted) `
        -Message 'Claim-root path collision was changed or accepted.'
    $script:negativeCaseCount++

    $script:failedCheck = 'parent_delete_child_negative'
    $unsafeParent = New-ProductionLikeParent -Label 'delete-child'
    Add-UntrustedParentDeleteChildAccess -Path $unsafeParent
    $unsafeRoot = Join-Path $unsafeParent 'MqttSupersessionOwnerMigrationClaims'
    $unsafe = Invoke-InitializerScenario -Label 'parent-delete-child' -Operation 'preflight' `
        -ClaimRoot $unsafeRoot -ExpectedExitCode 10
    Assert-Condition -Condition ([string] $unsafe.outcome -ceq 'failed' -and
        [string] $unsafe.failureCode -ceq 'claim_root_parent' -and
        -not [bool] $unsafe.creationAttempted -and -not (Test-Path -LiteralPath $unsafeRoot)) `
        -Message 'Untrusted parent delete-child access was accepted or changed.'
    $script:negativeCaseCount++

    $script:failedCheck = 'atomic_collision_negative'
    $raceParent = New-ProductionLikeParent -Label 'atomic-collision'
    $raceRoot = Join-Path $raceParent 'MqttSupersessionOwnerMigrationClaims'
    $race = Invoke-InitializerScenario -Label 'atomic-collision' -Operation 'install' `
        -ClaimRoot $raceRoot -ExpectedExitCode 20 `
        -Confirmation 'provision-saef-mqtt-supersession-claim-root' `
        -InjectCreationCollision
    $collisionMarker = Join-Path $raceRoot 'untrusted-collision.txt'
    Assert-Condition -Condition ([string] $race.outcome -ceq 'failed' -and
        [string] $race.failureCode -ceq 'claim_root_creation' -and
        [bool] $race.creationAttempted -and -not [bool] $race.claimRootCreated -and
        -not [bool] $race.rollbackAttempted -and
        (Test-Path -LiteralPath $collisionMarker -PathType Leaf)) `
        -Message 'Atomic creation did not preserve a competing path fail-closed.'
    $script:negativeCaseCount++

    $script:failedCheck = 'automatic_rollback'
    $rollbackParent = New-ProductionLikeParent -Label 'rollback'
    $rollbackRoot = Join-Path $rollbackParent 'MqttSupersessionOwnerMigrationClaims'
    $rolledBack = Invoke-InitializerScenario -Label 'post-acl-failure' -Operation 'install' `
        -ClaimRoot $rollbackRoot -ExpectedExitCode 30 `
        -Confirmation 'provision-saef-mqtt-supersession-claim-root' `
        -InjectPostAclFailure
    Assert-Condition -Condition ([string] $rolledBack.outcome -ceq 'rolled_back' -and
        [bool] $rolledBack.rollbackAttempted -and [bool] $rolledBack.rollbackSucceeded -and
        -not (Test-Path -LiteralPath $rollbackRoot)) `
        -Message 'Post-ACL failure did not roll back the empty root.'
    $script:negativeCaseCount++

    $script:failedCheck = 'case_counts'
    Assert-Condition -Condition ($script:positiveCaseCount -eq 5 -and
        $script:negativeCaseCount -eq 6) -Message 'Qualification case counts differ.'

    Remove-Item -LiteralPath $scratchRoot -Recurse -Force
    $script:scratchCleanupSucceeded = -not (Test-Path -LiteralPath $scratchRoot)
    Assert-Condition -Condition $script:scratchCleanupSucceeded `
        -Message 'Qualification scratch cleanup failed.'
    Write-Host '[SAEF] qualification-passed'
    Write-QualificationStatus -Outcome 'passed'
    exit $ExitSuccess
} catch {
    $script:failureType = $_.Exception.GetType().FullName
    $script:failureId = [string] $_.FullyQualifiedErrorId
    if (Test-Path -LiteralPath $scratchRoot -PathType Container) {
        try {
            Remove-Item -LiteralPath $scratchRoot -Recurse -Force
        } catch {
        }
    }
    $script:scratchCleanupSucceeded = -not (Test-Path -LiteralPath $scratchRoot)
    Write-Host ('[SAEF] qualification-failed: ' + $script:failedCheck)
    Write-QualificationStatus -Outcome 'failed'
    exit $ExitFailed
}
