[CmdletBinding()]
param(
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
    $StatusPath = Join-Path $PSScriptRoot 'secure-child-process-windows-qualification.local.json'
}

$ExitSuccess = 0
$ExitFailed = 10
$script:positiveCaseCount = 0
$script:negativeCaseCount = 0
$script:scratchMutationAttempted = $false
$script:scratchCleanupSucceeded = $false
$script:failedCheck = 'source_identity'
$scratchRoot = Join-Path $env:TEMP ('saef-child-process-' + [Guid]::NewGuid().ToString('N'))

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Write-QualificationStatus {
    param(
        [Parameter(Mandatory = $true)][string] $Outcome,
        [Parameter(Mandatory = $true)][int] $ExitCode,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string] $ErrorType
    )

    $status = [ordered]@{
        formatVersion = 1
        timestampUtc = [DateTime]::UtcNow.ToString('o')
        phase = 'secure_child_process_windows_qualification'
        outcome = $Outcome
        exitCode = $ExitCode
        childProcessContractSha256 = if (Test-Path -LiteralPath $ChildProcessContractPath -PathType Leaf) {
            Get-Sha256 -Path $ChildProcessContractPath
        } else { '' }
        positiveCaseCount = $script:positiveCaseCount
        negativeCaseCount = $script:negativeCaseCount
        scratchMutationAttempted = [bool] $script:scratchMutationAttempted
        scratchCleanupSucceeded = [bool] $script:scratchCleanupSucceeded
        productionMutationAttempted = $false
        serviceRestartAttempted = $false
        failedCheck = if ($ExitCode -eq 0) { '' } else { $script:failedCheck }
        errorType = $ErrorType
    }
    $directory = Split-Path -Parent $StatusPath
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
        throw [IO.DirectoryNotFoundException]::new('Qualification status directory is missing.')
    }
    $temporary = Join-Path $directory ('.saef-child-process-' + [Guid]::NewGuid().ToString('N') + '.tmp')
    $backup = Join-Path $directory ('.saef-child-process-' + [Guid]::NewGuid().ToString('N') + '.bak')
    try {
        [IO.File]::WriteAllText(
            $temporary,
            ($status | ConvertTo-Json -Depth 4) + [Environment]::NewLine,
            [Text.UTF8Encoding]::new($false)
        )
        if (Test-Path -LiteralPath $StatusPath -PathType Leaf) {
            [IO.File]::Replace($temporary, $StatusPath, $backup)
        } else {
            [IO.File]::Move($temporary, $StatusPath)
        }
    } finally {
        foreach ($path in @($temporary, $backup)) {
            if (Test-Path -LiteralPath $path -PathType Leaf) {
                Remove-Item -LiteralPath $path -Force
            }
        }
    }
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

function Set-ScratchAcl {
    param([Parameter(Mandatory = $true)][string] $Path)

    & icacls.exe $Path '/inheritance:r' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot disable child-process qualification ACL inheritance.')
    }
    & icacls.exe $Path '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot grant child-process qualification ACL.')
    }
    & icacls.exe $Path '/setowner' '*S-1-5-32-544' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw [InvalidOperationException]::new('Cannot protect child-process qualification scratch path.')
    }
}

try {
    if (-not [IO.Path]::IsPathRooted($ChildProcessContractPath) -or
        -not (Test-Path -LiteralPath $ChildProcessContractPath -PathType Leaf) -or
        (Get-Sha256 -Path $ChildProcessContractPath) -cne $ExpectedChildProcessContractSha256) {
        throw [Security.SecurityException]::new('Child process contract source identity differs.')
    }
    $item = Get-Item -LiteralPath $ChildProcessContractPath -Force
    if ([long] $item.Length -gt 4194304 -or
        (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.IOException]::new('Child process contract source is unsafe.')
    }
    . $ChildProcessContractPath
    if ($null -eq (Get-Command Invoke-SaefPowerShellChildProcess -CommandType Function -ErrorAction SilentlyContinue)) {
        throw [InvalidOperationException]::new('Child process contract function is unavailable.')
    }

    [IO.Directory]::CreateDirectory($scratchRoot) | Out-Null
    $script:scratchMutationAttempted = $true
    Set-ScratchAcl -Path $scratchRoot
    $fixturePath = Join-Path $scratchRoot 'child-fixture.ps1'
    $fixtureSource = @'
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $Mode,
    [Parameter()][string] $Value = '',
    [Parameter()][string] $MarkerPath = '',
    [Parameter()][string] $DescendantPidPath = ''
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
switch ($Mode) {
    'normal' {
        $ownProcess = Get-CimInstance Win32_Process -Filter ('ProcessId = ' + $PID)
        if ([string] $ownProcess.CommandLine -like ('*' + $env:SAEF_TEST_CAPABILITY + '*')) {
            exit 24
        }
        [Console]::Out.WriteLine('argument=' + $Value)
        [Console]::Out.WriteLine('environment=' + $env:SAEF_TEST_CAPABILITY)
        [Console]::Out.WriteLine('ambient=' + $env:SAEF_AMBIENT_SHOULD_NOT_LEAK)
        exit 0
    }
    'stdin' {
        [Console]::Out.WriteLine('stdin=' + [Console]::In.ReadToEnd().Length)
        exit 0
    }
    'nonzero' { exit 23 }
    'noisy' {
        [Console]::Out.Write(('X' * 8192))
        Start-Sleep -Seconds 30
        exit 0
    }
    'hang' {
        $powerShell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
        $descendant = Start-Process -FilePath $powerShell -ArgumentList @(
            '-NoLogo', '-NoProfile', '-NonInteractive', '-Command', 'Start-Sleep -Seconds 120'
        ) -WindowStyle Hidden -PassThru
        [IO.File]::WriteAllText($DescendantPidPath, [string] $descendant.Id)
        Start-Sleep -Seconds 120
        exit 0
    }
    'marker' {
        [IO.File]::WriteAllText($MarkerPath, 'started')
        exit 0
    }
    default { exit 25 }
}
'@
    [IO.File]::WriteAllText($fixturePath, $fixtureSource, [Text.UTF8Encoding]::new($false))
    $fixtureSha256 = Get-Sha256 -Path $fixturePath

    $script:failedCheck = 'argument_environment_roundtrip'
    $capability = 'qualification-capability-' + [Guid]::NewGuid().ToString('N')
    $ambientValue = 'ambient-' + [Guid]::NewGuid().ToString('N')
    $hadAmbientValue = Test-Path Env:SAEF_AMBIENT_SHOULD_NOT_LEAK
    $previousAmbientValue = [string] $env:SAEF_AMBIENT_SHOULD_NOT_LEAK
    $value = 'space quote" trailing\'
    try {
        $env:SAEF_AMBIENT_SHOULD_NOT_LEAK = $ambientValue
        $normal = Invoke-SaefPowerShellChildProcess -ScriptPath $fixturePath `
            -ExpectedScriptSha256 $fixtureSha256 `
            -Arguments @('-Mode', 'normal', '-Value', $value) `
            -Environment @{ SAEF_TEST_CAPABILITY = $capability } `
            -TimeoutSeconds 15 -MaximumOutputBytes 4096
    } finally {
        if ($hadAmbientValue) {
            $env:SAEF_AMBIENT_SHOULD_NOT_LEAK = $previousAmbientValue
        } else {
            Remove-Item Env:SAEF_AMBIENT_SHOULD_NOT_LEAK -ErrorAction SilentlyContinue
        }
    }
    $normalOutput = [Text.Encoding]::UTF8.GetString([byte[]] $normal.standardOutput)
    Assert-Condition -Condition ($normal.exitCode -eq 0 -and
        $normal.terminationReason -ceq 'exited' -and
        $normalOutput.Contains('argument=' + $value) -and
        $normalOutput.Contains('environment=' + $capability) -and
        -not $normalOutput.Contains($ambientValue)) `
        -Message 'Argument or environment roundtrip differs.'
    $script:positiveCaseCount++

    $script:failedCheck = 'standard_input'
    $standardInput = Invoke-SaefPowerShellChildProcess -ScriptPath $fixturePath `
        -ExpectedScriptSha256 $fixtureSha256 -Arguments @('-Mode', 'stdin') `
        -TimeoutSeconds 15 -MaximumOutputBytes 4096
    $standardInputOutput = [Text.Encoding]::UTF8.GetString([byte[]] $standardInput.standardOutput)
    Assert-Condition -Condition ($standardInput.exitCode -eq 0 -and
        $standardInputOutput.Contains('stdin=0')) -Message 'Child standard input is not closed.'
    $script:positiveCaseCount++

    $script:failedCheck = 'nonzero_exit'
    $nonzero = Invoke-SaefPowerShellChildProcess -ScriptPath $fixturePath `
        -ExpectedScriptSha256 $fixtureSha256 -Arguments @('-Mode', 'nonzero') `
        -TimeoutSeconds 15 -MaximumOutputBytes 4096
    Assert-Condition -Condition ($nonzero.exitCode -eq 23 -and
        $nonzero.terminationReason -ceq 'exited') -Message 'Nonzero exit code was not preserved.'
    $script:positiveCaseCount++

    $script:failedCheck = 'script_identity'
    $markerPath = Join-Path $scratchRoot 'unexpected-marker.local.txt'
    try {
        Invoke-SaefPowerShellChildProcess -ScriptPath $fixturePath `
            -ExpectedScriptSha256 ('0' * 64) `
            -Arguments @('-Mode', 'marker', '-MarkerPath', $markerPath) | Out-Null
        throw [InvalidOperationException]::new('Wrong script hash was accepted.')
    } catch [Security.SecurityException] {
        Assert-Condition -Condition (-not (Test-Path -LiteralPath $markerPath)) `
            -Message 'Hash-rejected child process still started.'
    }
    $script:negativeCaseCount++

    $script:failedCheck = 'environment_allowlist'
    try {
        Invoke-SaefPowerShellChildProcess -ScriptPath $fixturePath `
            -ExpectedScriptSha256 $fixtureSha256 -Arguments @('-Mode', 'normal') `
            -Environment @{ PATH = 'forbidden' } | Out-Null
        throw [InvalidOperationException]::new('Unsafe environment key was accepted.')
    } catch [ArgumentException] {
    }
    $script:negativeCaseCount++

    $script:failedCheck = 'output_limit'
    try {
        Invoke-SaefPowerShellChildProcess -ScriptPath $fixturePath `
            -ExpectedScriptSha256 $fixtureSha256 -Arguments @('-Mode', 'noisy') `
            -TimeoutSeconds 15 -MaximumOutputBytes 1024 | Out-Null
        throw [InvalidOperationException]::new('Noisy child process was accepted.')
    } catch [InvalidOperationException] {
        Assert-Condition -Condition ([string] $_.Exception.Data['terminationReason'] -ceq 'output_limit') `
            -Message 'Output-limit failure was not classified.'
    }
    $script:negativeCaseCount++

    $script:failedCheck = 'timeout_process_tree'
    $descendantPidPath = Join-Path $scratchRoot 'descendant-pid.local.txt'
    try {
        Invoke-SaefPowerShellChildProcess -ScriptPath $fixturePath `
            -ExpectedScriptSha256 $fixtureSha256 `
            -Arguments @('-Mode', 'hang', '-DescendantPidPath', $descendantPidPath) `
            -TimeoutSeconds 2 -MaximumOutputBytes 4096 | Out-Null
        throw [InvalidOperationException]::new('Hanging child process was accepted.')
    } catch [TimeoutException] {
        Assert-Condition -Condition ([string] $_.Exception.Data['terminationReason'] -ceq 'timeout') `
            -Message 'Timeout failure was not classified.'
    }
    Assert-Condition -Condition (Test-Path -LiteralPath $descendantPidPath -PathType Leaf) `
        -Message 'Timeout fixture did not create descendant evidence.'
    $descendantPid = [int] (Get-Content -LiteralPath $descendantPidPath -Raw)
    $descendantDeadline = [DateTime]::UtcNow.AddSeconds(5)
    while ($null -ne (Get-Process -Id $descendantPid -ErrorAction SilentlyContinue) -and
        [DateTime]::UtcNow -lt $descendantDeadline) {
        Start-Sleep -Milliseconds 100
    }
    Assert-Condition -Condition ($null -eq (Get-Process -Id $descendantPid -ErrorAction SilentlyContinue)) `
        -Message 'Timed-out child process left a descendant running.'
    $script:negativeCaseCount++

    $script:failedCheck = 'result_bounds'
    Assert-Condition -Condition ($normal.standardOutputBytes -le 4096 -and
        $normal.standardErrorBytes -le 4096 -and $normal.durationMilliseconds -ge 0) `
        -Message 'Successful child result is outside its bounded contract.'
   $script:positiveCaseCount++

    $script:failedCheck = 'case_counts'
    Assert-Condition -Condition ($script:positiveCaseCount -eq 4 -and
        $script:negativeCaseCount -eq 4) -Message 'Qualification case counts differ.'

   Remove-Item -LiteralPath $scratchRoot -Recurse -Force
    $script:scratchCleanupSucceeded = -not (Test-Path -LiteralPath $scratchRoot)
    Assert-Condition -Condition $script:scratchCleanupSucceeded -Message 'Qualification scratch cleanup failed.'
    Write-QualificationStatus -Outcome 'passed' -ExitCode $ExitSuccess -ErrorType ''
    exit $ExitSuccess
} catch {
    $failure = $_.Exception
    if (Test-Path -LiteralPath $scratchRoot -PathType Container) {
        try {
            Remove-Item -LiteralPath $scratchRoot -Recurse -Force
        } catch {
        }
    }
    $script:scratchCleanupSucceeded = -not (Test-Path -LiteralPath $scratchRoot)
    Write-QualificationStatus -Outcome 'failed' -ExitCode $ExitFailed `
        -ErrorType $failure.GetType().FullName
    exit $ExitFailed
}
