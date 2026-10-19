# Extraction only: no package child, RPC, service, installation or cleanup.
[CmdletBinding()]
param(
    [switch] $RunIsolatedTests,
    [Parameter(Mandatory = $true)][string] $FixtureParent
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if (-not $RunIsolatedTests) { throw 'Explicit isolated-test authorization required.' }
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5 -or
    $PSVersionTable.PSVersion.Minor -ne 1) { throw 'Windows PowerShell 5.1 required.' }
$repo = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$windows = Join-Path $repo 'deployments/symcon/windows'
$starter = Join-Path $windows 'adapters/Start-SaefMediaCarouselSchemaPackage.ps1'
$initializer = Join-Path $windows 'Initialize-SaefDeploymentChannel.ps1'
foreach ($import in @(
    @{ path = $starter; names = @('Expand-SchemaPackage') },
    @{ path = $initializer; names = @('Set-RestrictedAcl', 'Assert-Elevated', 'Assert-AdditionPlainPath') }
)) {
    $tokens = $null; $errors = $null
    $ast = [Management.Automation.Language.Parser]::ParseFile($import.path, [ref] $tokens, [ref] $errors)
    if (@($errors).Count) { throw ($errors | Out-String) }
    foreach ($name in $import.names) {
        $functions = @($ast.FindAll({ param($node)
            $node -is [Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -ceq $name
        }, $false))
        if ($functions.Count -ne 1) { throw 'Missing or ambiguous test import.' }
        . ([scriptblock]::Create($functions[0].Extent.Text))
    }
}
Assert-Elevated | Out-Null
function Assert-StorageTest { param([bool] $Condition, [string] $Label)
    if (-not $Condition) { throw $Label }; $script:passed++
}
function Hash-StorageFile { param([string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}
$passed = 0
Assert-AdditionPlainPath $FixtureParent
if (-not (Test-Path -LiteralPath $FixtureParent -PathType Container) -or
    [IO.Path]::GetFullPath($FixtureParent).TrimEnd('\') -cne $FixtureParent -or
    $FixtureParent.TrimEnd('\') -ceq [IO.Path]::GetPathRoot($FixtureParent).TrimEnd('\')) {
    throw 'Choose an existing canonical non-drive-root qualification scratch parent.'
}
$scratch = Join-Path $FixtureParent ([DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ', [Globalization.CultureInfo]::InvariantCulture) +
    '-' + [guid]::NewGuid().ToString('N').Substring(0, 12))
# Fail before creating fixtures if the nested package could hit legacy MAX_PATH.
if (($scratch.Length + 1 + 'media-carousel-package'.Length + 1 + 29 + 1 + 100) -gt 240) {
    throw 'Qualification scratch parent is too long; select an approved shorter parent, do not relax path limits.'
}
$null = New-Item -ItemType Directory -Path $scratch -ErrorAction Stop
# Only this newly created synthetic root gets the existing test ACL helper.
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
Set-RestrictedAcl $scratch ('*' + $sid) '(OI)(CI)F'
$beforeAcl = (Get-Acl -LiteralPath $scratch).Sddl
$producerHash = Hash-StorageFile $starter
$savedCulture = [Threading.Thread]::CurrentThread.CurrentCulture
$utf8 = [Text.UTF8Encoding]::new($false)
Add-Type -AssemblyName System.IO.Compression
try {
    foreach ($kind in @('schema', 'binding', 'settings', 'repeatable')) {
        $names = @('schema-plan.local.json', 'windows/Initialize-SaefDeploymentChannel.ps1',
            'windows/SaefChildProcess.ps1', 'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1',
            'windows/adapters/Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1',
            'windows/adapters/Test-SaefMediaCarouselSchema.ps1', 'windows/adapters/schema-probe/library.json',
            'windows/adapters/schema-probe/SchemaProbe/module.json', 'windows/adapters/schema-probe/legacy.php',
            'windows/adapters/schema-probe/candidate.php')
        if ($kind -cin @('binding', 'repeatable')) {
            $names = @('binding-plan.local.json', 'candidate-policy.local.json', 'candidate-channel.local.json',
                'windows/Initialize-SaefDeploymentChannel.ps1', 'windows/SaefChildProcess.ps1',
                'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1', 'windows/adapters/Update-SaefMediaCarouselBinding.ps1')
        }
        if ($kind -ceq 'repeatable') {
            $names += @('reviewed-baseline.local.json', 'qualification.local.json',
                'windows/Initialize-SaefScopeBoundApprovalProfile.ps1', 'windows/Invoke-SaefScopeBoundApprovalRunner.ps1',
                'windows/adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1')
        }
        if ($kind -ceq 'settings') {
            $names = @('settings-plan.local.json', 'windows/Initialize-SaefDeploymentChannel.ps1',
                'windows/SaefChildProcess.ps1', 'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1',
                'windows/adapters/Set-SaefMediaCarouselFitToggle.ps1')
        }
        $zipPath = Join-Path $scratch ($kind + '.zip')
        $stream = [IO.File]::Open($zipPath, 'CreateNew', 'Write', 'None')
        $archive = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create, $true)
        try {
            foreach ($name in $names) {
                $bytes = $utf8.GetBytes('synthetic payload; must never execute')
                if ($name -ceq 'windows/Initialize-SaefDeploymentChannel.ps1') { $bytes = [IO.File]::ReadAllBytes($initializer) }
                $entry = $archive.CreateEntry($name).Open()
                try { $entry.Write($bytes, 0, $bytes.Length) } finally { $entry.Dispose() }
            }
        } finally { $archive.Dispose(); $stream.Dispose() }
        $hash = Hash-StorageFile $zipPath
        $legacy = Expand-SchemaPackage $zipPath $hash $kind
        Assert-StorageTest ((Split-Path -Leaf $legacy.root) -cmatch '^MediaCarousel-Schema-[a-f0-9]{32}$') 'Legacy name changed.'
        Assert-StorageTest (-not (Test-Path (Join-Path $legacy.root 'artifact-record.local.json'))) 'Legacy contents changed.'
        $lastRun = ''
        foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
            [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
            $expanded = Expand-SchemaPackage $zipPath $hash $kind -ArtifactRoot $scratch -ProducerSha256 $producerHash
            Assert-StorageTest ((Split-Path -Parent $expanded.root) -ceq (Join-Path $scratch 'media-carousel-package')) 'Wrong workstream.'
            Assert-StorageTest ((Split-Path -Leaf $expanded.root) -cmatch '^\d{8}T\d{6}Z-[a-f0-9]{12}$') 'Wrong UTC name.'
            Assert-StorageTest ($expanded.root -cne $lastRun) 'Run reused.'
            $lastRun = $expanded.root
            $record = Get-Content -LiteralPath (Join-Path $expanded.root 'artifact-record.local.json') -Raw | ConvertFrom-Json
            Assert-StorageTest ($record.state -ceq 'review-required' -and $record.retention.decision -ceq 'retain') 'Unsafe lifecycle.'
            Assert-StorageTest ($record.producer.revision -ceq $producerHash -and $record.copyOf -ceq $zipPath) 'Missing source binding.'
            Assert-StorageTest (@($record.evidence.references).Count -eq 2 -and
                $record.evidence.references[1] -ceq ('source-zip-sha256: ' + $hash)) 'Reference serialization differs.'
            Assert-StorageTest ($null -eq $record.identity.sha256 -and $null -eq $record.identity.manifestRef) 'False tree identity.'
            Assert-StorageTest ((Get-Content -LiteralPath (Join-Path $expanded.root 'summary.local.txt') -Raw).Contains('NOT proof of success')) 'Closure warning absent.'
            foreach ($name in $names) {
                Assert-StorageTest ((Hash-StorageFile (Join-Path $expanded.root $name)) -ceq
                    (Hash-StorageFile (Join-Path $legacy.root $name))) ('Payload changed: ' + $name)
            }
            Assert-StorageTest ((Hash-StorageFile $zipPath) -ceq $hash -and (Get-Acl -LiteralPath $scratch).Sddl -ceq $beforeAcl) 'Source or root ACL changed.'
        }
        foreach ($bad in @('hash', 'missing-root', 'file-root', 'relative-root', 'producer-hash')) {
            $badHash = $hash; $badRoot = $scratch; $badProducer = $producerHash
            switch ($bad) {
                'hash' { $badHash = 'a' * 64 }
                'missing-root' { $badRoot = Join-Path $scratch 'missing' }
                'file-root' { $badRoot = $zipPath }
                'relative-root' { $badRoot = 'relative' }
                'producer-hash' { $badProducer = '' }
            }
            $beforeRuns = @(Get-ChildItem -LiteralPath (Join-Path $scratch 'media-carousel-package')).Count
            $rejected = $false
            try { $null = Expand-SchemaPackage $zipPath $badHash $kind -ArtifactRoot $badRoot -ProducerSha256 $badProducer }
            catch { $rejected = $true }
            Assert-StorageTest $rejected ('Accepted invalid input: ' + $bad)
            Assert-StorageTest (@(Get-ChildItem -LiteralPath (Join-Path $scratch 'media-carousel-package')).Count -eq $beforeRuns) 'Rejected input created a run.'
        }
    }
    foreach ($bad in @('workstream-file', 'untrusted-writer', 'junction-root', 'long-root')) {
        $badRoot = Join-Path $scratch $bad
        if ($bad -ceq 'long-root') { $badRoot = Join-Path $scratch ('x' * (130 - $scratch.Length)) }
        if ($bad -ceq 'junction-root') {
            $null = New-Item -ItemType Junction -Path $badRoot -Target $scratch -ErrorAction Stop
        } else {
            $null = New-Item -ItemType Directory -Path $badRoot -ErrorAction Stop
        }
        if ($bad -ceq 'workstream-file') {
            [IO.File]::WriteAllText((Join-Path $badRoot 'media-carousel-package'), 'retained sentinel')
        }
        if ($bad -ceq 'untrusted-writer') {
            $acl = Get-Acl -LiteralPath $badRoot
            $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
                [Security.Principal.SecurityIdentifier]::new('S-1-1-0'),
                [Security.AccessControl.FileSystemRights]::Write, [Security.AccessControl.AccessControlType]::Allow))
            Set-Acl -LiteralPath $badRoot -AclObject $acl
        }
        $snapshotAcl = (Get-Acl -LiteralPath $badRoot).Sddl
        $rejected = $false
        try { $null = Expand-SchemaPackage $zipPath $hash $kind -ArtifactRoot $badRoot -ProducerSha256 $producerHash }
        catch { $rejected = $true }
        Assert-StorageTest $rejected ('Accepted unsafe storage: ' + $bad)
        Assert-StorageTest ((Get-Acl -LiteralPath $badRoot).Sddl -ceq $snapshotAcl) 'Rejected ACL was repaired.'
        if ($bad -ceq 'long-root') {
            Assert-StorageTest (-not (Test-Path -LiteralPath (Join-Path $badRoot 'media-carousel-package'))) 'Long path rejection created a workstream.'
        }
    }
    Assert-StorageTest ([IO.File]::ReadAllText((Join-Path $scratch 'workstream-file/media-carousel-package')) -ceq 'retained sentinel') 'Existing file changed.'
    Write-Output ('PASS: ' + $passed + ' extraction storage checks; retained fixtures: ' + $scratch)
} finally {
    [Threading.Thread]::CurrentThread.CurrentCulture = $savedCulture
    Write-Output ('No cleanup. Retain: ' + $scratch)
}
