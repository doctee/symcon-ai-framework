<?php

declare(strict_types=1);

function failSecureChildProcess(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertSecureChildProcess(bool $condition, string $message): void
{
    if (!$condition) {
        failSecureChildProcess($message);
    }
}

$root = dirname(__DIR__, 2);
$windowsRoot = $root . '/deployments/symcon/windows';
$contract = file_get_contents($windowsRoot . '/SaefChildProcess.ps1');
$qualification = file_get_contents(
    $windowsRoot . '/Invoke-SaefChildProcessWindowsQualification.ps1'
);
$gateway = file_get_contents($windowsRoot . '/Invoke-SaefDeploymentGateway.ps1');
$runner = file_get_contents($windowsRoot . '/Invoke-SaefScopeBoundApprovalRunner.ps1');
$approvalQualification = file_get_contents(
    $windowsRoot . '/Invoke-SaefScopeBoundApprovalWindowsQualification.ps1'
);
$initializer = file_get_contents($windowsRoot . '/Initialize-SaefDeploymentChannel.ps1');

foreach (
    [
        'child process contract' => $contract,
        'child process qualification' => $qualification,
        'gateway' => $gateway,
        'approval runner' => $runner,
        'approval qualification' => $approvalQualification,
        'channel initializer' => $initializer,
    ] as $label => $source
) {
    assertSecureChildProcess(is_string($source), ucfirst($label) . ' is unreadable.');
}

foreach (
    [
        'function Invoke-SaefPowerShellChildProcess',
        'System32\WindowsPowerShell\v1.0\powershell.exe',
        "[ValidatePattern('^[a-f0-9]{64}$')]",
        'Get-FileHash -LiteralPath $ScriptPath -Algorithm SHA256',
        '[IO.FileAttributes]::ReparsePoint',
        '[ValidateRange(1, 900)]',
        '[ValidateRange(1, 1048576)]',
        '$Arguments.Count -gt 128',
        '$argumentCharacters -gt 24576',
        '$Environment.Count -gt 16',
        "'^SAEF_[A-Z0-9_]{1,59}$'",
        '$environmentCharacters -gt 65536',
        'UseShellExecute = false',
        'CreateNoWindow = true',
        'RedirectStandardInput = true',
        'RedirectStandardOutput = true',
        'RedirectStandardError = true',
        'inheritedSaefKeys',
        'process.StandardInput.Close();',
        'CreateJobObject',
        'JobObjectLimitKillOnJobClose',
        'JobObjectExtendedLimitInformationClass',
        'AssignProcessToJobObject',
        'TerminateJobObject',
        'Child process exceeded its runtime bound.',
        'Child process exceeded its output bound.',
        "terminationCode == 1 ? \"output_limit\"",
        'Child process host type already exists before contract import.',
    ] as $fragment
) {
    assertSecureChildProcess(
        str_contains($contract, $fragment),
        "Secure child process fragment is missing: {$fragment}"
    );
}

assertSecureChildProcess(
    !str_contains($contract, 'private const int JobObjectExtendedLimitInformation = 9;'),
    'C# Job Object information class collides with its structure name.'
);

foreach (
    [
        '/\bInvoke-Expression\b/i',
        '/\biex\b/i',
        '/cmd(?:\.exe)?\s+\/c/i',
        '/powershell(?:\.exe)?\s+-Command/i',
        '/\bStart-Process\b/i',
        '/\bStart-Job\b/i',
    ] as $pattern
) {
    assertSecureChildProcess(
        preg_match($pattern, $contract) !== 1,
        "Secure child process contract contains a forbidden path: {$pattern}"
    );
}

foreach (
    [
        'argument_environment_roundtrip',
        'standard_input',
        'nonzero_exit',
        'script_identity',
        'environment_allowlist',
        'output_limit',
        'timeout_process_tree',
        'result_bounds',
        'positiveCaseCount -eq 4',
        'negativeCaseCount -eq 4',
        "& icacls.exe \$Path '/inheritance:r'",
        "'*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F'",
        '$descendantDeadline = [DateTime]::UtcNow.AddSeconds(5)',
        'productionMutationAttempted = $false',
        'serviceRestartAttempted = $false',
        'scratchCleanupSucceeded',
        'Set-ScratchAcl -Path $scratchRoot',
        'SAEF_AMBIENT_SHOULD_NOT_LEAK',
        'Get-Process -Id $descendantPid',
    ] as $fragment
) {
    assertSecureChildProcess(
        str_contains($qualification, $fragment),
        "Child process qualification fragment is missing: {$fragment}"
    );
}

foreach ([$gateway, $runner, $approvalQualification] as $source) {
    assertSecureChildProcess(
        str_contains($source, 'Invoke-SaefPowerShellChildProcess'),
        'A migrated runtime owner does not use the secure child process contract.'
    );
    assertSecureChildProcess(
        !str_contains($source, '& $powerShell')
            && !str_contains($source, '& $powerShellExecutable'),
        'A migrated runtime owner still launches Windows PowerShell directly.'
    );
}

assertSecureChildProcess(
    str_contains($gateway, 'SAEF_APPROVAL_ENVELOPE = $ApprovalEnvelopeBase64Url')
        && !str_contains($gateway, "'-ApprovalEnvelopeBase64Url',"),
    'Gateway exposes the approval capability on a child command line.'
);
assertSecureChildProcess(
    str_contains($runner, '$env:SAEF_APPROVAL_ENVELOPE = $null')
        && str_contains($runner, 'approvalEnvelopeSourceConflict')
        && !str_contains($runner, "'-ApprovalEnvelopeBase64Url'"),
    'Approval runner does not consume and clear the environment capability safely.'
);
assertSecureChildProcess(
    str_contains($initializer, "'SaefChildProcess.ps1'")
        && str_contains($initializer, 'childProcessContractPath = Join-Path $InstallRoot')
        && str_contains($initializer, 'expectedChildProcessContractSha256 ='),
    'Channel initializer does not install and pin the child process contract.'
);

fwrite(STDOUT, "secure-child-process: ok\n");
