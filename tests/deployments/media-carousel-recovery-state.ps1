# Production function tests only; no installed policies, credentials or RPC.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
    throw 'Windows PowerShell 5.1 required.'
}
$path = Join-Path $PSScriptRoot '../../deployments/symcon/windows/adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1'
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($path, [ref] $tokens, [ref] $errors)
if (@($errors).Count -ne 0) { throw 'Adapter parser failed.' }
$names = @('Get-TextSha256', 'Get-PreviousActiveStateEvidence', 'Restore-PreviousActiveStateEvidence')
foreach ($name in $names) {
    $found = @($ast.FindAll({ param($node)
        $node -is [Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -ceq $name
    }, $false))
    if ($found.Count -ne 1) { throw 'Production function is not unique.' }
    . ([scriptblock]::Create($found[0].Extent.Text))
}
$script:policy = [pscustomobject]@{ maximumStateBytes = 4096 }
$original = [Threading.Thread]::CurrentThread.CurrentCulture
$cases = 0
try {
    foreach ($culture in @('en-US', 'de-DE', 'tr-TR')) {
        [Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($culture)
        foreach ($value in @($null, "{`"label`":`"$([char]0x00fc)`"}`r`n")) {
            $script:previousActiveState = $value
            $record = Get-PreviousActiveStateEvidence | ConvertTo-Json | ConvertFrom-Json
            $script:previousActiveState = 'unchanged-sentinel'
            Restore-PreviousActiveStateEvidence $record
            if ($script:previousActiveState -cne $value) { throw 'Previous-state roundtrip differs.' }
            $cases++
        }
        foreach ($bad in @(
            @{ existed = $false; sha256 = 'a'; contentBase64 = '' },
            @{ existed = $false; sha256 = ''; contentBase64 = 'e30=' },
            @{ existed = 'false'; sha256 = ''; contentBase64 = '' },
            @{ existed = $true; sha256 = ('a' * 64); contentBase64 = 'e30=' },
            @{ existed = $true; sha256 = ('a' * 64); contentBase64 = '/w==' },
            @{ existed = $true; sha256 = ('a' * 64); contentBase64 = ('A' * 8193) }
        )) {
            $script:previousActiveState = 'unchanged-sentinel'
            $rejected = $false
            try { Restore-PreviousActiveStateEvidence ([pscustomobject] $bad) } catch { $rejected = $true }
            if (-not $rejected -or $script:previousActiveState -cne 'unchanged-sentinel') {
                throw 'Invalid previous-state evidence accepted or changed recovery state.'
            }
            $cases++
        }
    }
} finally { [Threading.Thread]::CurrentThread.CurrentCulture = $original }
Write-Output "PASS: $cases recovery-state cases; no live mutation."
