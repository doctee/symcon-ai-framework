[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $ZipPath,
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-f0-9]{64}$')][string] $ExpectedZipSha256
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

# Package-local bootstrap, not a new general archive extraction API. The entire
# archive is hash-bound before parsing; only these fixed regular files may exist.
function Expand-SchemaPackage { param([string] $Path, [string] $Hash)
    $allowed = @('schema-plan.local.json', 'windows/Initialize-SaefDeploymentChannel.ps1',
        'windows/SaefChildProcess.ps1', 'windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1',
        'windows/adapters/Test-SaefMediaCarouselSchema.ps1',
        'windows/adapters/schema-probe/library.json', 'windows/adapters/schema-probe/SchemaProbe/module.json',
        'windows/adapters/schema-probe/legacy.php', 'windows/adapters/schema-probe/candidate.php')
    if (-not [IO.Path]::IsPathRooted($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf) -or
        (Get-Item -LiteralPath $Path).Length -gt 4194304) { throw 'Missing or oversized schema archive.' }
    $bytes = [IO.File]::ReadAllBytes($Path)
    $sha = [Security.Cryptography.SHA256]::Create()
    $archive = $null; $memory = $null
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
        $destination = Join-Path $parent ('MediaCarousel-Schema-' + [guid]::NewGuid().ToString('N'))
        $null = New-Item -ItemType Directory -Path $destination -ErrorAction Stop
        Assert-AdditionProtectedPath $destination
        foreach ($name in $allowed) {
            $file = Join-Path $destination $name
            $null = [IO.Directory]::CreateDirectory((Split-Path -Parent $file))
            $output = [IO.File]::Open($file, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
            try { $output.Write($content[$name], 0, $content[$name].Length) } finally { $output.Dispose() }
            Assert-AdditionProtectedPath $file
        }
        return @{ root = $destination; hashes = $hashes }
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
    $package = Expand-SchemaPackage ([IO.Path]::GetFullPath($ZipPath)) $ExpectedZipSha256
    $launcher = Join-Path $package.root 'windows/SaefChildProcess.ps1'
    if ((Get-FileHash -LiteralPath $launcher -Algorithm SHA256).Hash.ToLowerInvariant() -cne
        $package.hashes['windows/SaefChildProcess.ps1']) { throw 'Extracted child-process helper changed.' }
    # Exact source, single script-scope import; uses the existing bounded Job Object runner.
    . $launcher
    $child = Invoke-SaefPowerShellChildProcess `
        -ScriptPath (Join-Path $package.root 'windows/adapters/Test-SaefMediaCarouselSchema.ps1') `
        -ExpectedScriptSha256 $package.hashes['windows/adapters/Test-SaefMediaCarouselSchema.ps1'] `
        -Arguments @('-PlanPath', (Join-Path $package.root 'schema-plan.local.json'),
            '-ExpectedPlanSha256', $package.hashes['schema-plan.local.json'], '-Confirmation', 'qualify-media-carousel-schema') `
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
