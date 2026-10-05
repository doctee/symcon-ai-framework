<?php

declare(strict_types=1);

function failCrossRootRetention(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertCrossRootRetention(bool $condition, string $message): void
{
    if (!$condition) {
        failCrossRootRetention($message);
    }
}

$root = dirname(__DIR__, 2);
$windowsRoot = $root . '/deployments/symcon/windows';
$consumer = file_get_contents($windowsRoot . '/Invoke-SaefStandaloneModuleCrossRootRetention.ps1');
$qualification = file_get_contents(
    $windowsRoot . '/Invoke-SaefStandaloneModuleCrossRootRetentionWindowsQualification.ps1'
);
$genericRetention = file_get_contents($windowsRoot . '/Invoke-SaefDeploymentRetentionCleanup.ps1');
$gateway = file_get_contents($windowsRoot . '/Invoke-SaefDeploymentGateway.ps1');
$legacyRetention = file_get_contents(
    $windowsRoot . '/adapters/Invoke-SaefOwnTracksPositionMapModuleRetention.ps1'
);
$ownTracksPolicy = json_decode(
    (string) file_get_contents(
        $windowsRoot . '/adapters/owntracks-position-map-adapter-policy.example.json'
    ),
    true,
    flags: JSON_THROW_ON_ERROR
);
$mediaCarouselPolicy = json_decode(
    (string) file_get_contents(
        $windowsRoot . '/adapters/media-carousel-adapter-policy.example.json'
    ),
    true,
    flags: JSON_THROW_ON_ERROR
);
$transaction = json_decode(
    (string) file_get_contents(
        $windowsRoot . '/adapters/owntracks-position-map-module-transaction.json'
    ),
    true,
    flags: JSON_THROW_ON_ERROR
);

foreach (
    [
        'cross-root consumer' => $consumer,
        'Windows qualification' => $qualification,
        'generic retention' => $genericRetention,
        'deployment gateway' => $gateway,
        'legacy OwnTracks retention' => $legacyRetention,
    ] as $label => $source
) {
    assertCrossRootRetention(is_string($source), "Unreadable {$label} source.");
}

foreach (
    [
        "[ValidateSet('plan', 'apply', 'inspect')]",
        "'channel-state:'",
        "'managed-fileset:'",
        "'adapter-transaction:'",
        'Retention unit is not an exact three-root artifact set.',
        'Active adapter binding differs across roots.',
        'Adapter transaction lacks its channel deployment pair.',
        'Channel roots contain an unpaired managed fileset.',
        'Retention roots are not pairwise disjoint.',
        'Operational roots and quarantine must share one volume.',
        'approval-or-recovery-reference',
        'active-transaction',
        'staged-deployment',
        'minimum-age',
        'recent-history',
        "[Threading.Mutex]::new(\$false, \$ChannelMutexName)",
        "[Threading.Mutex]::new(\$false, [string] \$Contracts.adapter.mutexName)",
        'Target writer lock is busy.',
        '[IO.FileMode]::CreateNew',
        'Copy-VerifiedTree',
        '[IO.Directory]::Move',
        'Restore-MovedArtifacts',
        "Phase 'manual_recovery'",
        "-AllowExpired:(\$Operation -eq 'inspect')",
        "'failed_before_operational_mutation'",
        'retentionDeletionAttempted = $false',
    ] as $fragment
) {
    assertCrossRootRetention(
        str_contains($consumer, $fragment),
        "Cross-root contract fragment is missing: {$fragment}"
    );
}

assertCrossRootRetention(
    !str_contains($consumer, 'Sort-Object')
        && !str_contains($consumer, '[DateTime]::Parse(')
        && !preg_match('/Remove-Item[^\r\n]*-Recurse/', $consumer),
    'Cross-root consumer contains culture-sensitive ordering, general timestamp parsing, or deletion.'
);
assertCrossRootRetention(
    str_contains($consumer, '[Array]::Sort')
        && str_contains($consumer, '[StringComparer]::Ordinal')
        && str_contains($consumer, '[DateTimeOffset]::TryParseExact')
        && str_contains($consumer, '[Globalization.CultureInfo]::InvariantCulture'),
    'Cross-root identities are not explicitly culture invariant.'
);
assertCrossRootRetention(
    str_contains($consumer, 'expectedStandaloneModuleCrossRootRetentionSha256')
        && str_contains($consumer, 'Cross-root retention source identity differs.'),
    'Cross-root consumer is not pinned by the installed channel policy.'
);

foreach (
    [
        'windows-powershell-5.1-parse',
        'ordinal-and-roundtrip-vectors-under-three-cultures',
        'transaction-name-and-exact-three-root-artifact-vectors',
        'reparse-point-fails-before-plan',
        'broad-write-acl-fails-before-plan',
        'unpaired-cross-root-artifact-fails-before-plan',
        'three-root-plan-protects-active-staged-recent-young-referenced-manual-and-cross-target',
        'plan-hash-mismatch-fails-before-claim',
        'wrong-confirmation-fails-before-claim',
        'channel-lock-contention-fails-before-claim',
        'inventory-drift-fails-before-claim',
        'backup-failure-retains-evidence-and-terminal-read-only-inspect',
        'partial-move-failure-restores-all-moved-roots',
        'one-time-byte-exact-backup-and-three-root-quarantine',
        'terminal-read-only-inspect',
        'claim-replay-fails-before-second-mutation',
        "@('en-US', 'de-DE', 'tr-TR')",
        '$currentSidReference = [Security.Principal.SecurityIdentifier]::new($currentSid)',
        "('*' + \$currentSid + ':(OI)(CI)F')",
        '[Security.AccessControl.FileSystemAccessRule]::new(',
        '$currentSidReference,',
        'lastChildDiagnostics = $script:lastChildDiagnostics',
        'Retention failed for an unexpected reason.',
        '[Security.AccessControl.FileSystemRights]::Delete,',
        'productionMutationAttempted = [bool] $script:productionMutationAttempted',
        'retentionDeletionAttempted = $false',
    ] as $fragment
) {
    assertCrossRootRetention(
        str_contains($qualification, $fragment),
        "Windows qualification fragment is missing: {$fragment}"
    );
}

assertCrossRootRetention(
    !str_contains($genericRetention, 'Sort-Object')
        && !str_contains($gateway, '[DateTime]::Parse(')
        && !str_contains($legacyRetention, 'Sort-Object')
        && !str_contains($legacyRetention, '[DateTime]::Parse('),
    'A corrected retention or gateway surface still uses ambient culture.'
);
assertCrossRootRetention(
    str_contains($legacyRetention, 'Legacy adapter-only retention apply is retired')
        && !preg_match('/Remove-Item[^\r\n]*\$artifactPath[^\r\n]*-Recurse/', $legacyRetention),
    'Legacy OwnTracks retention still exposes an adapter-only deletion path.'
);

$retention = $ownTracksPolicy['retention'];
assertCrossRootRetention(
    $retention['profile'] === 'saef-channel-v8-standalone-module-cross-root-v1'
        && $retention['implemented'] === true
        && $retention['requiredConfirmation']
            === 'quarantine-saef-owntracks-position-map-cross-root-retention'
        && $retention['minimumAgeHours'] >= 24
        && $retention['keepSuccessfulRollbackCount'] >= 1
        && $retention['keepFailedCandidateCount'] >= 1,
    'OwnTracks reference retention profile is not enabled with bounded protection.'
);
assertCrossRootRetention(
    $mediaCarouselPolicy['retention']['profile']
        === 'saef-channel-v8-standalone-module-cross-root-v1'
        && $mediaCarouselPolicy['retention']['implemented'] === false,
    'MediaCarousel must remain a disabled later reuse target.'
);
assertCrossRootRetention(
    $transaction['retention']['owner'] === 'channel-v8-cross-root-contract'
        && $transaction['retention']['implemented'] === true
        && $transaction['retention']['genericCleanupAllowed'] === false
        && $transaction['retention']['applyRequiresSeparateAuthorization'] === true,
    'OwnTracks transaction retention contract differs.'
);

fwrite(STDOUT, "standalone-module-cross-root-retention: ok\n");
