[CmdletBinding()]
param(
    [Parameter()][string] $ZipPath = '',
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-f0-9]{64}$')][string] $ExpectedZipSha256,
    [Parameter()][ValidateSet('schema', 'binding', 'settings', 'repeatable')][string] $PackageKind = 'schema',
    [Parameter()][string] $ArtifactRoot = ''
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$DefaultZipName = 'MediaCarousel-SchemaQualification.zip'

# Package-local bootstrap, not a new general archive extraction API. The entire
# archive is hash-bound before parsing; only these fixed regular files may exist.
function Expand-SchemaPackage { param([string] $Path, [string] $Hash,
    [ValidateSet('schema', 'binding', 'settings', 'repeatable')][string] $Kind = 'schema',
    [string] $ArtifactRoot = '', [string] $ProducerSha256 = '')
    $allowed = @('schema-plan.local.json', 'windows/Initialize-SaefDeploymentChannel.ps1',
        'windows/SaefChildProcess.ps1', 'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1',
        'windows/adapters/Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1',
        'windows/adapters/Test-SaefMediaCarouselSchema.ps1',
        'windows/adapters/schema-probe/library.json', 'windows/adapters/schema-probe/SchemaProbe/module.json',
        'windows/adapters/schema-probe/legacy.php', 'windows/adapters/schema-probe/candidate.php')
    if ($Kind -cin @('binding', 'repeatable')) {
        $allowed = @('binding-plan.local.json', 'candidate-policy.local.json', 'candidate-channel.local.json',
            'windows/Initialize-SaefDeploymentChannel.ps1', 'windows/SaefChildProcess.ps1',
            'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1', 'windows/adapters/Update-SaefMediaCarouselBinding.ps1')
    }
    if ($Kind -ceq 'repeatable') {
        $allowed += @('reviewed-baseline.local.json', 'qualification.local.json',
            'windows/Initialize-SaefScopeBoundApprovalProfile.ps1',
            'windows/Invoke-SaefScopeBoundApprovalRunner.ps1',
            'windows/adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1')
    }
    if ($Kind -ceq 'settings') {
        $allowed = @('settings-plan.local.json', 'windows/Initialize-SaefDeploymentChannel.ps1',
            'windows/SaefChildProcess.ps1', 'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1',
            'windows/adapters/Set-SaefMediaCarouselFitToggle.ps1')
    }
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path).Length -gt 4194304) { throw 'Missing or oversized schema archive.' }
    $bytes = [IO.File]::ReadAllBytes($Path)
    $sha = [Security.Cryptography.SHA256]::Create()
    $archive = $null; $memory = $null; $destination = ''
    try {
        if ($bytes.Length -gt 4194304 -or
            ([BitConverter]::ToString($sha.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant() -cne $Hash) {
            throw 'Schema archive hash differs.'
        }
        Add-Type -AssemblyName System.IO.Compression
        $memory = [IO.MemoryStream]::new($bytes, $false)
        $archive = [IO.Compression.ZipArchive]::new($memory, [IO.Compression.ZipArchiveMode]::Read, $true)
        if ($archive.Entries.Count -ne $allowed.Count) { throw 'Unexpected schema archive entries.' }
        $content = @{}; $hashes = @{}; $expanded = 0L
        foreach ($entry in $archive.Entries) {
            $name = $entry.FullName
            if ($name -cnotin $allowed -or $content.ContainsKey($name) -or $entry.Length -lt 1 -or
                $entry.Length -gt 1048576) { throw 'Unexpected, duplicate or oversized schema archive entry.' }
            $expanded += $entry.Length
            if ($expanded -gt 4194304) { throw 'Schema archive expansion exceeds limit.' }
            $stream = $entry.Open(); $out = [IO.MemoryStream]::new()
            try {
                $buffer = New-Object byte[] 8192
                while (($count = $stream.Read($buffer, 0, $buffer.Length)) -gt 0) {
                    if ($out.Length + $count -gt $entry.Length) { throw 'Schema entry length differs.' }
                    $out.Write($buffer, 0, $count)
                }
                if ($out.Length -ne $entry.Length) { throw 'Schema entry truncated.' }
                $content[$name] = $out.ToArray()
                $hashes[$name] = ([BitConverter]::ToString($sha.ComputeHash($content[$name]))).Replace('-', '').ToLowerInvariant()
            } finally { $stream.Dispose(); $out.Dispose() }
        }
        # Import the established ACL/path validator from the already bound ZIP.
        $source = [Text.UTF8Encoding]::new($false, $true).GetString($content['windows/Initialize-SaefDeploymentChannel.ps1'])
        $tokens = $null; $errors = $null
        $ast = [Management.Automation.Language.Parser]::ParseInput($source, [ref] $tokens, [ref] $errors)
        if (@($errors).Count) { throw 'Bound initializer parse failed.' }
        foreach ($name in @('Assert-AdditionPlainPath', 'Assert-AdditionProtectedPath', 'Assert-Elevated')) {
            $fn = @($ast.FindAll({ param($n)
                $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ceq $name
            }, $false))
            if ($fn.Count -ne 1) { throw 'Bound bootstrap function missing.' }
            . ([scriptblock]::Create($fn[0].Extent.Text))
        }
        Assert-Elevated | Out-Null
        Assert-AdditionProtectedPath $Path
        $parent = Split-Path -Parent $Path
        Assert-AdditionProtectedPath $parent
        $runId = ''
        if ($ArtifactRoot -cne '') {
            # Reuse the bound initializer's ACL/path contract, never repair ACLs.
            Assert-AdditionProtectedPath $ArtifactRoot
            if (-not (Test-Path -LiteralPath $ArtifactRoot -PathType Container) -or
                [IO.Path]::GetFullPath($ArtifactRoot).TrimEnd('\') -cne $ArtifactRoot -or
                $ArtifactRoot.TrimEnd('\') -ceq [IO.Path]::GetPathRoot($ArtifactRoot).TrimEnd('\') -or
                $ProducerSha256 -cnotmatch '\A[a-f0-9]{64}\z') {
                throw 'Artifact root must be an existing canonical non-drive-root directory; producer hash required.'
            }
            $longestEntry = 0
            foreach ($entryName in $allowed) { $longestEntry = [Math]::Max($longestEntry, $entryName.Length) }
            if (($ArtifactRoot.Length + 1 + 'media-carousel-package'.Length + 1 + 29 + 1 + $longestEntry) -gt 240) {
                throw 'Artifact root is too long for the bounded Windows extraction layout.'
            }
            $parent = Join-Path $ArtifactRoot 'media-carousel-package'
            Assert-AdditionPlainPath $parent
            if (-not (Test-Path -LiteralPath $parent)) {
                $null = New-Item -ItemType Directory -Path $parent -ErrorAction Stop
            }
            if (-not (Test-Path -LiteralPath $parent -PathType Container)) { throw 'Artifact workstream is not a directory.' }
            Assert-AdditionProtectedPath $parent
            $runId = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ', [Globalization.CultureInfo]::InvariantCulture) +
                '-' + [guid]::NewGuid().ToString('N').Substring(0, 12)
        }
        $destination = Join-Path $parent ('MediaCarousel-Schema-' + [guid]::NewGuid().ToString('N'))
        if ($ArtifactRoot -cne '') { $destination = Join-Path $parent $runId }
        $null = New-Item -ItemType Directory -Path $destination -ErrorAction Stop
        Assert-AdditionProtectedPath $destination
        if ($ArtifactRoot -cne '') {
            # Written before extraction/dispatch. This is provenance, not a success receipt.
            $record = [ordered]@{
                formatVersion = 1; artifactId = $runId; workstream = 'media-carousel-package'
                owner = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
                purpose = ('Retained extraction of a hash-bound MediaCarousel ' + $Kind + ' package')
                producer = @{ name = 'Start-SaefMediaCarouselSchemaPackage.ps1'; revision = $ProducerSha256 }
                role = 'package'; location = @{ path = $destination; basis = 'absolute' }; state = 'review-required'
                identity = @{ manifestRef = $null; sha256 = $null }
                evidence = @{
                    basis = 'local-observation'; checkedAtUtc = [DateTime]::UtcNow.ToString('o')
                    references = @(('source-zip: ' + $Path), ('source-zip-sha256: ' + $Hash))
                }
                retention = @{
                    decision = 'retain'; minimumUntilUtc = $null; reviewAfterUtc = $null
                    holds = @('Extraction and child outcome require separate closure; no automatic cleanup')
                    dependencies = @('Package plan and child-produced backup/evidence paths remain authoritative')
                }
                copyOf = $Path
            }
            $notes = [ordered]@{
                'artifact-record.local.json' = ($record | ConvertTo-Json -Depth 8)
                'summary.local.txt' = (@(
                    ('Run: ' + $destination), ('Kind: ' + $Kind), ('Source ZIP: ' + $Path),
                    ('Source SHA-256: ' + $Hash), ('Producer SHA-256: ' + $ProducerSha256),
                    'State: review-required. Created before extraction and child dispatch; NOT proof of success.',
                    'Retain this run and original ZIP. Do not automatically retry or clean up.',
                    'The package plan still owns target, backup, scratch and result paths. No paths were migrated.',
                    'Use child output and independent postflight for closure; this record grants no live authority.'
                ) -join "`r`n")
            }
            foreach ($note in $notes.Keys) {
                $notePath = Join-Path $destination $note
                $noteBytes = [Text.UTF8Encoding]::new($false).GetBytes($notes[$note])
                $noteStream = [IO.File]::Open($notePath, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
                try { $noteStream.Write($noteBytes, 0, $noteBytes.Length) } finally { $noteStream.Dispose() }
                Assert-AdditionProtectedPath $notePath
            }
        }
        foreach ($name in $allowed) {
            $file = Join-Path $destination $name
            $null = [IO.Directory]::CreateDirectory((Split-Path -Parent $file))
            $output = [IO.File]::Open($file, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
            try { $output.Write($content[$name], 0, $content[$name].Length) } finally { $output.Dispose() }
            Assert-AdditionProtectedPath $file
        }
        return @{ root = $destination; hashes = $hashes }
    } catch {
        if ($ArtifactRoot -cne '' -and $destination -cne '') {
            throw [IO.IOException]::new(('Artifact extraction failed; inspect and retain run path: ' + $destination), $_.Exception)
        }
        throw
    } finally {
        if ($null -ne $archive) { $archive.Dispose() }
        if ($null -ne $memory) { $memory.Dispose() }
        $sha.Dispose()
    }
}
try {
    if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
        throw 'Elevated Windows PowerShell 5.1 required.'
    }
    if ([string]::IsNullOrWhiteSpace($ZipPath)) { $ZipPath = Join-Path $PSScriptRoot $DefaultZipName }
    $producerHash = ''
    if ($ArtifactRoot -cne '') { $producerHash = (Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash.ToLowerInvariant() }
    $package = Expand-SchemaPackage ([IO.Path]::GetFullPath($ZipPath)) $ExpectedZipSha256 $PackageKind `
        -ArtifactRoot $ArtifactRoot -ProducerSha256 $producerHash
    if ($ArtifactRoot -cne '') { Write-Verbose ('Retained artifact run: ' + $package.root) }
    $launcher = Join-Path $package.root 'windows/SaefChildProcess.ps1'
    if ((Get-FileHash -LiteralPath $launcher -Algorithm SHA256).Hash.ToLowerInvariant() -cne
        $package.hashes['windows/SaefChildProcess.ps1']) { throw 'Extracted child-process helper changed.' }
    # Exact source, single script-scope import; uses the existing bounded Job Object runner.
    . $launcher
    $entry = 'windows/adapters/Test-SaefMediaCarouselSchema.ps1'
    $planName = 'schema-plan.local.json'
    $confirmation = 'qualify-media-carousel-schema'
    $extra = @()
    if ($PackageKind -cin @('binding', 'repeatable')) {
        $entry = 'windows/adapters/Update-SaefMediaCarouselBinding.ps1'
        $planName = 'binding-plan.local.json'
        $confirmation = 'update-saef-media-carousel-binding'
        $extra = @('-Operation', 'install')
    }
    if ($PackageKind -ceq 'settings') {
        $entry = 'windows/adapters/Set-SaefMediaCarouselFitToggle.ps1'
        $planName = 'settings-plan.local.json'
        $confirmation = 'enable-media-carousel-fit-toggle'
        $extra = @('-Operation', 'apply')
    }
    $child = Invoke-SaefPowerShellChildProcess `
        -ScriptPath (Join-Path $package.root $entry) `
        -ExpectedScriptSha256 $package.hashes[$entry] `
        -Arguments (@('-PlanPath', (Join-Path $package.root $planName),
            '-ExpectedPlanSha256', $package.hashes[$planName], '-Confirmation', $confirmation) + $extra) `
        -TimeoutSeconds 900 -MaximumOutputBytes 65536
    if ($child.terminationReason -cne 'exited') {
        throw ('Schema child did not finish. Retain and inspect evidence below: ' + $package.root)
    }
    $report = [Text.UTF8Encoding]::new($false, $true).GetString($child.standardOutput) | ConvertFrom-Json
    if ($report.formatVersion -ne 1 -or $report.exitCode -ne $child.exitCode) { throw 'Schema child returned no valid report.' }
    $report | ConvertTo-Json -Depth 8
    exit $child.exitCode
} catch {
    @{ formatVersion = 1; operation = 'schema_package_launch'; outcome = 'review_required'
        error = $_.Exception.Message; exitCode = 40 } | ConvertTo-Json
    exit 40
}
