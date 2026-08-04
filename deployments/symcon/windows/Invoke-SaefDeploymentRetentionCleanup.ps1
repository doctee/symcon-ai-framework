[CmdletBinding()]
param(
    [Parameter()][switch] $Apply,
    [Parameter(Mandatory = $true)][string] $PlanPath,
    [Parameter()][string] $PolicyPath = '',
    [Parameter()][string] $StatusPath = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$mutationAttempted = $false
$backupRoot = $null

if ([string]::IsNullOrWhiteSpace($PolicyPath)) {
    $PolicyPath = Join-Path $PSScriptRoot 'deployment-channel.local.json'
}
if ([string]::IsNullOrWhiteSpace($StatusPath)) {
    $StatusPath = Join-Path $PSScriptRoot 'deployment-retention-cleanup-status.local.json'
}

function Write-Status {
    param(
        [Parameter(Mandatory = $true)][string] $Phase,
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter()][hashtable] $Details = @{}
    )
    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = $Phase
        outcome = $Outcome
        exitCode = $ExitCode
        mutationAttempted = $mutationAttempted
    }
    foreach ($key in $Details.Keys) {
        $status[$key] = $Details[$key]
    }
    $status | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $StatusPath -Encoding UTF8
}

function Assert-SafeName {
    param([Parameter(Mandatory = $true)][string] $Value)
    if ($Value -notmatch '\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\z') {
        throw [System.InvalidOperationException]::new("Unsafe retained-directory name: $Value")
    }
}

function Get-ChildPath {
    param(
        [Parameter(Mandatory = $true)][string] $Root,
        [Parameter(Mandatory = $true)][string] $Name
    )
    Assert-SafeName -Value $Name
    $fullRoot = [IO.Path]::GetFullPath($Root).TrimEnd([char[]] @('\', '/'))
    $fullPath = [IO.Path]::GetFullPath((Join-Path $fullRoot $Name))
    if (-not $fullPath.StartsWith(
        $fullRoot + [IO.Path]::DirectorySeparatorChar,
        [StringComparison]::OrdinalIgnoreCase
    )) {
        throw [System.InvalidOperationException]::new('Candidate path escapes its configured root.')
    }
    return $fullPath
}

function Assert-PlainDirectory {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw [System.IO.DirectoryNotFoundException]::new("Required directory is missing: $Path")
    }
    $item = Get-Item -LiteralPath $Path -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw [System.InvalidOperationException]::new("Reparse points are not allowed: $Path")
    }
}

function Get-Inventory {
    param(
        [Parameter(Mandatory = $true)][string] $StateRoot,
        [Parameter(Mandatory = $true)][string] $FilesetRoot
    )
    $stateDirectories = @(Get-ChildItem -LiteralPath $StateRoot -Directory -Force | Sort-Object Name)
    $filesetDirectories = @(Get-ChildItem -LiteralPath $FilesetRoot -Directory -Force | Sort-Object Name)
    $deploymentToFileset = @{}
    $filesetToDeployment = @{}

    foreach ($directory in $stateDirectories) {
        Assert-SafeName -Value $directory.Name
        Assert-PlainDirectory -Path $directory.FullName
        $manifestPath = Join-Path $directory.FullName 'deployment.json'
        if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
            throw [System.IO.FileNotFoundException]::new("Deployment manifest is missing: $manifestPath")
        }
        $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
        $deployment = [string] $manifest.deploymentId
        $fileset = [string] $manifest.targetDirectoryName
        Assert-SafeName -Value $deployment
        Assert-SafeName -Value $fileset
        if ($deployment -ne $directory.Name) {
            throw [System.InvalidOperationException]::new("Deployment manifest identity mismatch: $($directory.Name)")
        }
        if ($deploymentToFileset.ContainsKey($deployment)) {
            throw [System.InvalidOperationException]::new("Duplicate deployment mapping: $deployment")
        }
        if ($filesetToDeployment.ContainsKey($fileset)) {
            throw [System.InvalidOperationException]::new("Fileset has more than one deployment owner: $fileset")
        }
        $deploymentToFileset[$deployment] = $fileset
        $filesetToDeployment[$fileset] = $deployment
    }

    $filesetNames = @()
    foreach ($directory in $filesetDirectories) {
        Assert-SafeName -Value $directory.Name
        Assert-PlainDirectory -Path $directory.FullName
        $filesetNames += $directory.Name
    }
    $mappedFilesets = @($filesetToDeployment.Keys | Sort-Object)
    $filesetNames = @($filesetNames | Sort-Object)
    if ($stateDirectories.Count -ne $filesetDirectories.Count -or
        (Compare-Object -ReferenceObject $mappedFilesets -DifferenceObject $filesetNames)) {
        throw [System.InvalidOperationException]::new(
            'Managed deployment roots violate the one-deployment-to-one-fileset invariant.'
        )
    }
    return [ordered]@{
        deploymentCount = $stateDirectories.Count
        filesetCount = $filesetDirectories.Count
        deploymentToFileset = $deploymentToFileset
        filesetToDeployment = $filesetToDeployment
    }
}

function Assert-SimulatedInventory {
    param(
        [Parameter(Mandatory = $true)] $Inventory,
        [Parameter(Mandatory = $true)][array] $Candidates
    )
    $remainingDeployments = @($Inventory.deploymentToFileset.Keys)
    $remainingFilesets = @($Inventory.filesetToDeployment.Keys)
    foreach ($candidate in $Candidates) {
        $remainingDeployments = @($remainingDeployments | Where-Object { $_ -ne [string] $candidate.deployment })
        $remainingFilesets = @($remainingFilesets | Where-Object { $_ -ne [string] $candidate.fileset })
    }
    if ($remainingDeployments.Count -ne $remainingFilesets.Count) {
        throw [System.InvalidOperationException]::new('Candidate cleanup would break paired retention counts.')
    }
    foreach ($deployment in $remainingDeployments) {
        if ($remainingFilesets -notcontains [string] $Inventory.deploymentToFileset[$deployment]) {
            throw [System.InvalidOperationException]::new("Candidate cleanup would orphan deployment: $deployment")
        }
    }
}

function Copy-VerifiedDirectory {
    param(
        [Parameter(Mandatory = $true)][string] $Source,
        [Parameter(Mandatory = $true)][string] $Destination
    )
    Copy-Item -LiteralPath $Source -Destination $Destination -Recurse -Force
    $records = @()
    foreach ($file in @(Get-ChildItem -LiteralPath $Source -File -Recurse -Force | Sort-Object FullName)) {
        $relative = $file.FullName.Substring($Source.TrimEnd([char[]] @('\', '/')).Length + 1)
        $backupPath = Join-Path $Destination $relative
        if (-not (Test-Path -LiteralPath $backupPath -PathType Leaf)) {
            throw [System.IO.FileNotFoundException]::new("Backup file is missing: $relative")
        }
        $sourceHash = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        $backupHash = (Get-FileHash -LiteralPath $backupPath -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($sourceHash -ne $backupHash -or $file.Length -ne (Get-Item -LiteralPath $backupPath).Length) {
            throw [System.InvalidOperationException]::new("Backup verification failed: $relative")
        }
        $records += [ordered]@{ path = $relative.Replace('\', '/'); size = $file.Length; sha256 = $sourceHash }
    }
    return $records
}

try {
    foreach ($requiredPath in @($PolicyPath, $PlanPath)) {
        if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
            throw [System.IO.FileNotFoundException]::new("Required configuration is missing: $requiredPath")
        }
    }
    $policy = Get-Content -LiteralPath $PolicyPath -Raw | ConvertFrom-Json
    $plan = Get-Content -LiteralPath $PlanPath -Raw | ConvertFrom-Json
    foreach ($property in @('scriptsRoot', 'managedFilesetRoot', 'stateRoot', 'activeBootstrapRelativePath')) {
        if ($policy.PSObject.Properties.Name -notcontains $property -or
            [string]::IsNullOrWhiteSpace([string] $policy.$property)) {
            throw [System.InvalidOperationException]::new("Policy property is missing: $property")
        }
    }
    if ([int] $plan.formatVersion -ne 1 -or @($plan.candidates).Count -lt 1) {
        throw [System.InvalidOperationException]::new('Retention plan contract is invalid.')
    }

    $scriptsRoot = [IO.Path]::GetFullPath([string] $policy.scriptsRoot)
    $filesetRoot = [IO.Path]::GetFullPath([string] $policy.managedFilesetRoot)
    $stateRoot = [IO.Path]::GetFullPath([string] $policy.stateRoot)
    Assert-PlainDirectory -Path $scriptsRoot
    Assert-PlainDirectory -Path $filesetRoot
    Assert-PlainDirectory -Path $stateRoot
    $inventory = Get-Inventory -StateRoot $stateRoot -FilesetRoot $filesetRoot
    if ($inventory.deploymentCount -ne [int] $plan.expectedDeploymentCount -or
        $inventory.filesetCount -ne [int] $plan.expectedFilesetCount) {
        throw [System.InvalidOperationException]::new(
            "Retention inventory drifted: deployments=$($inventory.deploymentCount), filesets=$($inventory.filesetCount)"
        )
    }

    $candidateDeployments = @()
    $candidateFilesets = @()
    foreach ($candidate in @($plan.candidates)) {
        $deployment = [string] $candidate.deployment
        $fileset = [string] $candidate.fileset
        Assert-SafeName -Value $deployment
        Assert-SafeName -Value $fileset
        if ($candidateDeployments -contains $deployment -or $candidateFilesets -contains $fileset) {
            throw [System.InvalidOperationException]::new('Retention candidates must be unique pairs.')
        }
        if (-not $inventory.deploymentToFileset.ContainsKey($deployment) -or
            [string] $inventory.deploymentToFileset[$deployment] -ne $fileset) {
            throw [System.InvalidOperationException]::new("Candidate is not an exact manifest pair: $deployment")
        }
        $candidateDeployments += $deployment
        $candidateFilesets += $fileset
    }
    foreach ($deployment in @($plan.protectedDeployments)) {
        if ($candidateDeployments -contains [string] $deployment -or
            -not $inventory.deploymentToFileset.ContainsKey([string] $deployment)) {
            throw [System.InvalidOperationException]::new("Protected deployment conflict: $deployment")
        }
    }
    foreach ($fileset in @($plan.protectedFilesets)) {
        if ($candidateFilesets -contains [string] $fileset -or
            -not $inventory.filesetToDeployment.ContainsKey([string] $fileset)) {
            throw [System.InvalidOperationException]::new("Protected fileset conflict: $fileset")
        }
    }
    Assert-SimulatedInventory -Inventory $inventory -Candidates @($plan.candidates)

    $runtimeFiles = @(
        Get-ChildItem -LiteralPath $scriptsRoot -File -Recurse -Force |
            Where-Object {
                -not $_.FullName.StartsWith($filesetRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -and
                -not $_.FullName.StartsWith($stateRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)
            }
    )
    foreach ($runtimeFile in $runtimeFiles) {
        $content = Get-Content -LiteralPath $runtimeFile.FullName -Raw -ErrorAction SilentlyContinue
        foreach ($fileset in $candidateFilesets) {
            if ($null -ne $content -and $content -like "*$fileset*") {
                throw [System.InvalidOperationException]::new(
                    "Runtime file references a deletion candidate: $($runtimeFile.FullName)"
                )
            }
        }
    }

    if (-not $Apply) {
        Write-Status -Phase 'preflight' -Outcome 'passed' -ExitCode 0 -Details @{
            deploymentCount = $inventory.deploymentCount
            filesetCount = $inventory.filesetCount
            candidatePairCount = $candidateDeployments.Count
            postCleanupPairCount = $inventory.deploymentCount - $candidateDeployments.Count
        }
        exit 0
    }

    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw [System.UnauthorizedAccessException]::new('Apply requires an elevated local administrator.')
    }
    $mutationAttempted = $true
    $backupBase = Join-Path $env:ProgramData 'SAEF\DeploymentRetentionBackups'
    [IO.Directory]::CreateDirectory($backupBase) | Out-Null
    $backupRoot = Join-Path $backupBase ([DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' + [Guid]::NewGuid().ToString('N'))
    $backupStateRoot = Join-Path $backupRoot 'state'
    $backupFilesetRoot = Join-Path $backupRoot 'filesets'
    [IO.Directory]::CreateDirectory($backupStateRoot) | Out-Null
    [IO.Directory]::CreateDirectory($backupFilesetRoot) | Out-Null
    $backupEntries = @()
    foreach ($candidate in @($plan.candidates)) {
        foreach ($item in @(
            [ordered]@{ kind = 'deployment'; root = $stateRoot; name = [string] $candidate.deployment; backup = $backupStateRoot },
            [ordered]@{ kind = 'fileset'; root = $filesetRoot; name = [string] $candidate.fileset; backup = $backupFilesetRoot }
        )) {
            $source = Get-ChildPath -Root $item.root -Name $item.name
            $destination = Join-Path $item.backup $item.name
            $backupEntries += [ordered]@{
                kind = $item.kind
                name = $item.name
                files = @(Copy-VerifiedDirectory -Source $source -Destination $destination)
            }
        }
    }
    [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        sourceStateRoot = $stateRoot
        sourceFilesetRoot = $filesetRoot
        entries = $backupEntries
    } | ConvertTo-Json -Depth 12 |
        Set-Content -LiteralPath (Join-Path $backupRoot 'backup-manifest.local.json') -Encoding UTF8

    foreach ($candidate in @($plan.candidates)) {
        Remove-Item -LiteralPath (Get-ChildPath -Root $stateRoot -Name ([string] $candidate.deployment)) -Recurse -Force
        Remove-Item -LiteralPath (Get-ChildPath -Root $filesetRoot -Name ([string] $candidate.fileset)) -Recurse -Force
    }
    $afterInventory = Get-Inventory -StateRoot $stateRoot -FilesetRoot $filesetRoot
    $expectedAfterCount = $inventory.deploymentCount - $candidateDeployments.Count
    if ($afterInventory.deploymentCount -ne $expectedAfterCount -or
        $afterInventory.filesetCount -ne $expectedAfterCount) {
        throw [System.InvalidOperationException]::new('Unexpected post-cleanup paired inventory count.')
    }

    Write-Status -Phase 'cleanup' -Outcome 'completed' -ExitCode 0 -Details @{
        backupRoot = $backupRoot
        removedPairCount = $candidateDeployments.Count
        deploymentCount = $afterInventory.deploymentCount
        filesetCount = $afterInventory.filesetCount
    }
    exit 0
} catch {
    $exitCode = if ($mutationAttempted) { 20 } else { 10 }
    Write-Status -Phase $(if ($mutationAttempted) { 'cleanup' } else { 'preflight' }) `
        -Outcome 'failed' -ExitCode $exitCode -Details @{
            backupRoot = $backupRoot
            errorType = $_.Exception.GetType().FullName
            errorMessage = $_.Exception.Message
        }
    exit $exitCode
}
