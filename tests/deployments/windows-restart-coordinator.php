<?php

declare(strict_types=1);

/** @param array<string, mixed> $trace
 *  @return array{exitCode: int, outcome: string}
 */
function evaluateRestartTrace(array $trace): array
{
    if ($trace['preflightReady'] !== true) {
        return ['exitCode' => 10, 'outcome' => 'preflight_failed'];
    }
    if (($trace['preflightOnly'] ?? false) === true) {
        return ['exitCode' => 0, 'outcome' => 'preflight_passed'];
    }

    $activation = $trace['activation'];
    if ($activation['stop'] === true && $activation['start'] === true && $activation['ready'] === true) {
        return ['exitCode' => 0, 'outcome' => 'activated'];
    }

    if ($trace['rollbackConfigured'] !== true) {
        return ['exitCode' => 20, 'outcome' => 'activation_failed'];
    }

    $rollback = $trace['rollback'];
    if (
        $rollback['restore'] === true
        && $rollback['stop'] === true
        && $rollback['start'] === true
        && $rollback['ready'] === true
    ) {
        return ['exitCode' => 30, 'outcome' => 'rolled_back'];
    }

    return ['exitCode' => 40, 'outcome' => 'rollback_failed'];
}

function fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$scriptPath = $root . '/deployments/symcon/windows/Invoke-SaefSymconRestart.ps1';
$policyPath = $root . '/deployments/symcon/windows/restart-policy.json';
$fixturePath = __DIR__ . '/fixtures/windows-restart-traces.json';

$script = file_get_contents($scriptPath);
$policy = json_decode((string) file_get_contents($policyPath), true, flags: JSON_THROW_ON_ERROR);
$traces = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

if ($script === false || !is_array($policy) || !is_array($traces)) {
    fail('Restart artifacts could not be loaded.');
}

$requiredScriptFragments = [
    'StopPending',
    'StartPending',
    'Wait-ServiceState',
    'Wait-SymconReady',
    "'IPS_GetKernelRunlevel'",
    "'IPS_GetKernelStartTime'",
    '[System.IO.File]::Replace',
    '$replacementBackup',
    'failedCheck = $preflightCheck',
    'Get-FileHash',
    '[PSCredential] $Credential',
    "Authorization = 'Basic ' + [Convert]::ToBase64String",
    '[System.Text.Encoding]::UTF8.GetBytes',
    '[Array]::Clear',
    '[switch] $PreflightOnly',
    "Outcome = 'passed'",
    'restartAttempted = $false',
    '$ExitActivated = 0',
    '$ExitPreflightFailed = 10',
    '$ExitActivationFailed = 20',
    '$ExitRolledBack = 30',
    '$ExitRollbackFailed = 40',
];
foreach ($requiredScriptFragments as $fragment) {
    if (!str_contains($script, $fragment)) {
        fail("Required PowerShell contract fragment is missing: {$fragment}");
    }
}

$forbiddenPatterns = [
    '/Start-Sleep/i',
    '/timeout\s+\/T/i',
    '/\bsc(?:\.exe)?\s+(?:start|stop)\b/i',
    '/[A-Z]:\\\\Users\\\\/i',
    '/Bearer\s+[A-Za-z0-9._-]+/i',
    "/\$request\['Credential'\]/",
    "/\$networkCredential\.Password\s*=/",
    '/File\]::Replace\([^)]*\$null/s',
];
foreach ($forbiddenPatterns as $pattern) {
    if (preg_match($pattern, $script) === 1) {
        fail("Forbidden fixed-delay, private-data or shell-control pattern found: {$pattern}");
    }
}

foreach (['{', '(', '['] as $opening) {
    $closing = ['{' => '}', '(' => ')', '[' => ']'][$opening];
    if (substr_count($script, $opening) !== substr_count($script, $closing)) {
        fail("Unbalanced PowerShell delimiter: {$opening}{$closing}");
    }
}

$requiredPolicy = [
    'formatVersion' => 1,
    'expectedReadyRunlevel' => 10103,
];
foreach ($requiredPolicy as $name => $expected) {
    if (($policy[$name] ?? null) !== $expected) {
        fail("Unexpected policy value: {$name}");
    }
}
foreach (
    [
        'pollIntervalMilliseconds',
        'rpcTimeoutSeconds',
        'stopTimeoutSeconds',
        'startTimeoutSeconds',
        'readyTimeoutSeconds',
        'rollbackReadyTimeoutSeconds',
    ] as $name
) {
    if (!is_int($policy[$name] ?? null) || $policy[$name] <= 0) {
        fail("Policy limit must be a positive integer: {$name}");
    }
}
if ($policy['readyTimeoutSeconds'] < 600 || $policy['rollbackReadyTimeoutSeconds'] < 600) {
    fail('Ready deadlines do not leave sufficient margin for the observed 176-second startup.');
}

foreach ($traces as $name => $trace) {
    $actual = evaluateRestartTrace($trace);
    if ($actual !== $trace['expected']) {
        fail("Restart trace produced an unexpected outcome: {$name}");
    }
    if (
        isset($trace['activation']['readyAfterSeconds'])
        && $trace['activation']['readyAfterSeconds'] >= $policy['readyTimeoutSeconds']
    ) {
        fail("Slow-start trace exceeds the configured ready deadline: {$name}");
    }
}

fwrite(STDOUT, sprintf(
    "PASS: Windows restart coordinator contract, policy and %d state traces verified.\n",
    count($traces)
));
