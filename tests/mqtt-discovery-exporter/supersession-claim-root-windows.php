<?php

declare(strict_types=1);

function failMqttSupersessionClaimRootWindows(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertMqttSupersessionClaimRootWindows(bool $condition, string $message): void
{
    if (!$condition) {
        failMqttSupersessionClaimRootWindows($message);
    }
}

$root = dirname(__DIR__, 2);
$windowsRoot = $root . '/case-studies/mqtt-discovery-exporter/deployment/windows';
$initializerPath = $windowsRoot . '/Initialize-SaefMqttSupersessionClaimRoot.ps1';
$qualificationPath = $windowsRoot
    . '/Invoke-SaefMqttSupersessionClaimRootWindowsQualification.ps1';
$initializer = file_get_contents($initializerPath);
$qualification = file_get_contents($qualificationPath);

assertMqttSupersessionClaimRootWindows(
    is_string($initializer),
    'MQTT supersession claim-root initializer is unreadable.'
);
assertMqttSupersessionClaimRootWindows(
    is_string($qualification),
    'MQTT supersession claim-root qualification is unreadable.'
);

$initializerFragments = [
    "[ValidateSet('preflight', 'install')]",
    "'MqttSupersessionOwnerMigrationClaims'",
    "'provision-saef-mqtt-supersession-claim-root'",
    "'Global\\SAEF.MqttSupersessionClaimRoot'",
    "[string] \$ClaimRootPath = (Join-Path",
    '[switch] $QualificationMode',
    '[switch] $InjectPostAclFailure',
    'Fault injection is qualification-only.',
    'Production claim-root path differs from the fixed boundary.',
    'Qualification claim-root path is outside scratch.',
    'Assert-PlainAncestorChain -Path $claimParent',
    'Assert-ProtectedParentAcl -Path $claimParent',
    'Assert-ProtectedClaimRootAcl -Path $script:claimRoot',
    '$entry.IsInherited',
    "& icacls.exe \$Path '/inheritance:r'",
    "'*S-1-5-18:(OI)(CI)F'",
    "'*S-1-5-32-544:(OI)(CI)F'",
    '[IO.Directory]::CreateDirectory($script:claimRoot)',
    'Remove-Item -LiteralPath $script:claimRoot -Force',
    "\$script:finalOutcome = 'manual_recovery_required'",
    'productionMutationAttempted = [bool] (',
    'liveSymconRpcContactAttempted = $false',
    'ownerMutationAttempted = $false',
    'eventMutationAttempted = $false',
    'mqttPublishAttempted = $false',
    'deviceActionAttempted = $false',
    'serviceRestartAttempted = $false',
    'retentionCleanupAttempted = $false',
    'exit $script:finalExitCode',
];
foreach ($initializerFragments as $fragment) {
    assertMqttSupersessionClaimRootWindows(
        str_contains($initializer, $fragment),
        "Claim-root initializer fragment is missing: {$fragment}"
    );
}

assertMqttSupersessionClaimRootWindows(
    substr_count($initializer, '[IO.Directory]::CreateDirectory($script:claimRoot)') === 1,
    'Claim root must have exactly one creation call site.'
);
assertMqttSupersessionClaimRootWindows(
    substr_count($initializer, 'Remove-Item -LiteralPath $script:claimRoot -Force') === 1
        && !str_contains($initializer, 'Remove-Item -LiteralPath $script:claimRoot -Recurse'),
    'Claim root must have one non-recursive rollback-removal call site.'
);
assertMqttSupersessionClaimRootWindows(
    is_int($preflightBranch = strpos($initializer, "} elseif (\$Operation -eq 'preflight') {"))
        && is_int($creation = strpos(
            $initializer,
            '[IO.Directory]::CreateDirectory($script:claimRoot)'
        ))
        && $preflightBranch < $creation,
    'Read-only preflight does not guard claim-root creation.'
);

$qualificationFragments = [
    '[Management.Automation.Language.Parser]::ParseFile',
    'Invoke-SaefPowerShellChildProcess',
    '-ExpectedScriptSha256 $ExpectedInitializerSha256',
    "'preflight-missing'",
    "'wrong-confirmation'",
    "'install-positive'",
    "'postflight-existing'",
    "'broad-acl'",
    "'path-collision'",
    "'post-acl-failure'",
    "'provision-saef-mqtt-supersession-claim-root'",
    '-InjectPostAclFailure',
    'positiveCaseCount -eq 5',
    'negativeCaseCount -eq 4',
    'productionMutationAttempted = $false',
    'liveSymconRpcContactAttempted = $false',
    'ownerMutationAttempted = $false',
    'eventMutationAttempted = $false',
    'mqttPublishAttempted = $false',
    'deviceActionAttempted = $false',
    'serviceRestartAttempted = $false',
    'publicationAttempted = $false',
    'retentionCleanupAttempted = $false',
];
foreach ($qualificationFragments as $fragment) {
    assertMqttSupersessionClaimRootWindows(
        str_contains($qualification, $fragment),
        "Claim-root Windows qualification fragment is missing: {$fragment}"
    );
}

foreach (
    [
        'Restart-Service',
        'Stop-Service',
        'Start-Service',
        'Invoke-RestMethod',
        'Invoke-WebRequest',
        'Invoke-Expression',
        'iex ',
        'ssh ',
        'IPS_',
        'RequestAction',
        'MC_ReloadModule',
    ] as $forbidden
) {
    assertMqttSupersessionClaimRootWindows(
        !str_contains($initializer, $forbidden) && !str_contains($qualification, $forbidden),
        "Claim-root Windows contract contains forbidden action: {$forbidden}"
    );
}

assertMqttSupersessionClaimRootWindows(
    !str_contains($qualification, '& $powerShell')
        && !str_contains($qualification, 'Start-Process'),
    'Claim-root qualification bypasses the secure child-process contract.'
);
assertMqttSupersessionClaimRootWindows(
    substr_count($initializer, 'exit $script:finalExitCode') === 1
        && substr_count($qualification, 'exit $ExitSuccess') === 1
        && substr_count($qualification, 'exit $ExitFailed') === 1,
    'Claim-root scripts have an unexpected native-exit contract.'
);

fwrite(STDOUT, "PASS: MQTT supersession Windows claim-root contract\n");
