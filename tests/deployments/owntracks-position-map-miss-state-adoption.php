<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksTileDeadline;
use OwnTracksPositionMap\Prototype\OwnTracksTileMissStateStore;

function failOwnTracksMissStateAdoption(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertOwnTracksMissStateAdoption(bool $condition, string $message): void
{
    if (!$condition) {
        failOwnTracksMissStateAdoption($message);
    }
}

function removeOwnTracksMissStateAdoptionTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

/** @param array<string, mixed> $store */
function ownTracksMissStateSemantics(array $store): array
{
    $result = [];
    foreach ($store['selections'] as $fingerprint => $entry) {
        $state = $entry['state'];
        unset($state['pendingReservations']);
        ksort($state['negativeCache'], SORT_STRING);
        $result[$fingerprint] = [
            'updatedAt' => $entry['updatedAt'],
            'state' => $state,
        ];
    }
    ksort($result, SORT_STRING);

    return $result;
}

/** @param array<string, mixed> $store */
function ownTracksAssertFormat2Candidate(array $store, array $source): void
{
    assertOwnTracksMissStateAdoption($store['version'] === 2, 'Candidate format differs.');
    assertOwnTracksMissStateAdoption(
        ownTracksMissStateSemantics($store) === ownTracksMissStateSemantics($source),
        'Candidate changed preserved state semantics.'
    );
    foreach ($store['selections'] as $entry) {
        assertOwnTracksMissStateAdoption(
            ($entry['state']['pendingReservations'] ?? null) === [],
            'Candidate did not initialize an empty pending-reservations ledger.'
        );
    }
}

$root = dirname(__DIR__, 2);
$scriptPath = $root
    . '/deployments/symcon/windows/adapters/Invoke-SaefOwnTracksPositionMapMissStateAdoption.ps1';
$contractPath = $root
    . '/deployments/symcon/windows/adapters/owntracks-position-map-miss-state-adoption.json';
$planPath = $root
    . '/deployments/symcon/windows/adapters/owntracks-position-map-miss-state-adoption-plan.example.json';
$storePath = $root
    . '/dist/symcon/saef-owntracks-position-map-module/OwnTracksPositionMap/libs/OwnTracks/'
    . 'OwnTracksTileMissStateStore.php';
$candidateStorePath = $root
    . '/case-studies/owntracks-position-map/candidate/OwnTracksTileMissStateStore.php';
$deadlinePath = $root
    . '/case-studies/owntracks-position-map/candidate/OwnTracksTileDeadline.php';

$script = file_get_contents($scriptPath);
$contract = json_decode((string) file_get_contents($contractPath), true, flags: JSON_THROW_ON_ERROR);
$plan = json_decode((string) file_get_contents($planPath), true, flags: JSON_THROW_ON_ERROR);

assertOwnTracksMissStateAdoption(is_string($script), 'State-adoption source is unreadable.');
assertOwnTracksMissStateAdoption(
    $contract['adapterProfile'] === 'saef-owntracks-position-map-miss-state-adoption-v1'
        && $contract['targetId'] === 'saef-owntracks-position-map'
        && $contract['sourceFormat'] === 1
        && $contract['candidateFormat'] === 2
        && $contract['maximumStateBytes'] === 262_144,
    'State-adoption identity or format contract differs.'
);
assertOwnTracksMissStateAdoption(
    $contract['quiescence']['lockOrder'] === [
        'dayCache',
        'providerCache',
        'tileBudget',
        'providerBudget',
        'missState',
    ],
    'State-adoption lock order differs from the module adapter.'
);
assertOwnTracksMissStateAdoption(
    $contract['transformation']['counterResetAllowed'] === false
        && $contract['transformation']['selectionRemovalAllowed'] === false
        && $contract['transformation']['initialize'] === 'empty-pending-reservations-map',
    'State-adoption preservation contract differs.'
);
assertOwnTracksMissStateAdoption(
    $contract['transaction']['backup'] === 'source-bytes-before-replace'
        && $contract['transaction']['rootLeafName'] === 'saef-owntracks-position-map-state-adoption'
        && $contract['transaction']['statusLocation'] === 'transaction-root'
        && $contract['transaction']['switch'] === 'same-volume-atomic-file-replace'
        && $contract['transaction']['failureRollback'] === 'byte-exact-source-restore'
        && $contract['transaction']['staleBackupRestoreAllowed'] === false,
    'State-adoption rollback contract differs.'
);
assertOwnTracksMissStateAdoption(
    $contract['retention']['owner'] === 'owntracks-position-map-state-adoption'
        && $contract['retention']['automaticCleanupAllowed'] === false
        && $contract['retention']['cleanupRequiresSeparateAuthorization'] === true,
    'State-adoption retention contract differs.'
);
assertOwnTracksMissStateAdoption(
    $plan['operation'] === 'preflight'
        && $plan['confirmation'] === 'preflight-only'
        && str_contains($plan['transactionRoot'], '<private-absolute-'),
    'Example adoption plan is runnable or mutating.'
);
assertOwnTracksMissStateAdoption(
    hash_file('sha256', $storePath) === $contract['runtimeCompatibility']['sha256']
        && hash_file('sha256', $candidateStorePath) === $contract['runtimeCompatibility']['sha256'],
    'Reviewed migration implementation hash differs.'
);

$requiredFragments = [
    "[ValidateSet('preflight', 'adopt')]",
    "[Threading.Mutex]::new(\$false, [string] \$script:policy.mutexName)",
    'Enter-RuntimeQuiescence',
    'function Test-PathOverlap',
    '[IO.FileShare]::ReadWrite',
    '$stream.Lock(0, 1)',
    "@('dayCache', 'providerCache', 'tileBudget', 'providerBudget', 'missState')",
    'Assert-ZeroActiveLeases',
    'expectedSourceSha256',
    'expectedCandidateSha256',
    'expectedActivePackageIdentitySha256',
    'runtimeCompatibility.sha256',
    '$ExpectedRuntimeCompatibilitySha256',
    "'source-v1.json'",
    "'candidate-v2.json'",
    'Write-AtomicBytes -Path $statePath -Bytes $script:candidateBytes',
    'Assert-AdoptedState',
    'Invoke-AdoptionRollback',
    'Write-AtomicBytes -Path $statePath -Bytes $rollbackBytes',
    "Write-AdoptionStatus -Outcome 'rolled_back' -ExitCode \$ExitRolledBack",
    "Write-AdoptionStatus -Outcome 'manual_recovery_required' -ExitCode \$ExitManualRecovery",
    'State adoption requires an elevated local administrator.',
    'State-adoption transaction root overlaps a protected owner.',
    'State-adoption status must stay in its transaction root.',
    'liveStateReadAttempted = [bool] $script:liveStateReadAttempted',
    'moduleReloadAttempted = $false',
    'channelInstallationAttempted = $false',
    'providerContactAttempted = $false',
    'symconRpcContactAttempted = $false',
    'exit $script:finalExitCode',
];
foreach ($requiredFragments as $fragment) {
    assertOwnTracksMissStateAdoption(
        str_contains($script, $fragment),
        "State-adoption fragment is missing: {$fragment}"
    );
}
foreach (['MC_ReloadModule', 'MC_UpdateModule', 'Restart-Service', 'Invoke-WebRequest', 'Invoke-RestMethod'] as $forbidden) {
    assertOwnTracksMissStateAdoption(
        !str_contains($script, $forbidden),
        "State adoption contains an out-of-scope action: {$forbidden}"
    );
}
assertOwnTracksMissStateAdoption(
    strpos($script, 'New-AdoptionTransaction')
        < strpos($script, 'Write-AtomicBytes -Path $statePath -Bytes $script:candidateBytes'),
    'Byte-exact transaction backup is not prepared before replacement.'
);
$rollbackStart = strpos($script, 'function Invoke-AdoptionRollback');
$mainStart = strpos($script, "try {\n    \$script:failureCode = 'contract'");
assertOwnTracksMissStateAdoption(
    is_int($rollbackStart) && is_int($mainStart) && $rollbackStart < $mainStart,
    'State-adoption rollback boundary is missing.'
);
$rollback = substr($script, $rollbackStart, $mainStart - $rollbackStart);
assertOwnTracksMissStateAdoption(
    strpos($rollback, 'Write-AtomicBytes -Path $statePath -Bytes $rollbackBytes')
        < strpos($rollback, "Complete-AdoptionTransaction -Outcome 'rolled_back'"),
    'Rollback evidence can precede the byte-exact restore.'
);

require_once $deadlinePath;
require_once $candidateStorePath;

$temporaryRoot = sys_get_temp_dir() . '/saef-owntracks-state-adoption-' . bin2hex(random_bytes(8));
$stateRoot = $temporaryRoot . '/miss-state';
mkdir($stateRoot, 0700, true);
try {
    $fingerprint = str_repeat('a', 64);
    $negativeKey = str_repeat('b', 64);
    $source = [
        'version' => 1,
        'selections' => [
            $fingerprint => [
                'updatedAt' => 1_000,
                'state' => [
                    'selectionFingerprint' => $fingerprint,
                    'upstreamRequests' => 7,
                    'upstreamSuccesses' => 5,
                    'upstreamBytes' => 65_543,
                    'negativeCacheHits' => 3,
                    'rejectedOutsideAllowlist' => 2,
                    'budgetRejections' => 1,
                    'negativeCache' => [$negativeKey => 1_050],
                ],
            ],
        ],
    ];
    $sourceBytes = json_encode(
        $source,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    $statePath = $stateRoot . '/state.json';
    $lockPath = $stateRoot . '/.state.lock';
    file_put_contents($statePath, $sourceBytes);
    touch($lockPath);
    $sourceSha256 = hash('sha256', $sourceBytes);

    $holderCode = <<<'PHP'
$handle = fopen($argv[1], 'c+b');
if ($handle === false || !flock($handle, LOCK_EX)) {
    exit(2);
}
fwrite(STDOUT, "LOCKED\n");
fflush(STDOUT);
usleep(350000);
flock($handle, LOCK_UN);
fclose($handle);
PHP;
    $holder = proc_open(
        [PHP_BINARY, '-r', $holderCode, $lockPath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $holderPipes
    );
    assertOwnTracksMissStateAdoption(is_resource($holder), 'Cannot start lock holder.');
    fclose($holderPipes[0]);
    assertOwnTracksMissStateAdoption(
        trim((string) fgets($holderPipes[1])) === 'LOCKED',
        'Synthetic writer did not acquire the miss-state lock.'
    );
    $blockedStore = new OwnTracksTileMissStateStore($stateRoot, new OwnTracksTileDeadline(50));
    $blocked = false;
    try {
        $blockedStore->withSelection($fingerprint, 1_001, static fn (array &$state): array => $state);
    } catch (RuntimeException) {
        $blocked = true;
    }
    assertOwnTracksMissStateAdoption($blocked, 'Migration bypassed the held writer lock.');
    assertOwnTracksMissStateAdoption(
        hash_file('sha256', $statePath) === $sourceSha256,
        'Failed lock acquisition changed the source bytes.'
    );
    stream_get_contents($holderPipes[1]);
    $holderError = stream_get_contents($holderPipes[2]);
    fclose($holderPipes[1]);
    fclose($holderPipes[2]);
    assertOwnTracksMissStateAdoption(
        proc_close($holder) === 0,
        'Synthetic lock holder failed: ' . $holderError
    );

    $candidate = $source;
    $candidate['version'] = 2;
    foreach ($candidate['selections'] as &$candidateEntry) {
        $candidateEntry['state']['pendingReservations'] = [];
    }
    unset($candidateEntry);
    $candidateBytes = json_encode($candidate, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    ownTracksAssertFormat2Candidate($candidate, $source);

    // The pinned runtime schema accepts the losslessly prepared candidate.
    file_put_contents($statePath, $candidateBytes);
    $store = new OwnTracksTileMissStateStore($stateRoot, new OwnTracksTileDeadline(500));
    $accepted = $store->withSelection(
        $fingerprint,
        1_000,
        static fn (array &$state): array => $state
    );
    assertOwnTracksMissStateAdoption(
        $accepted['upstreamBytes'] === $source['selections'][$fingerprint]['state']['upstreamBytes'],
        'Reviewed runtime rejected or reset the format-2 candidate.'
    );

    $backupPath = $temporaryRoot . '/source-v1.json';
    file_put_contents($backupPath, $sourceBytes);
    assertOwnTracksMissStateAdoption(
        hash_file('sha256', $backupPath) === $sourceSha256,
        'Synthetic transaction backup is not byte exact.'
    );

    // Model the adapter's post-replace failure boundary and exact automatic rollback.
    $replacementPath = $stateRoot . '/.synthetic-replacement';
    file_put_contents($replacementPath, $candidateBytes);
    rename($replacementPath, $statePath);
    $postconditionFailed = false;
    try {
        throw new RuntimeException('synthetic postcondition failure');
    } catch (RuntimeException) {
        $postconditionFailed = true;
        $rollbackPath = $stateRoot . '/.synthetic-rollback';
        file_put_contents($rollbackPath, file_get_contents($backupPath));
        rename($rollbackPath, $statePath);
    }
    assertOwnTracksMissStateAdoption($postconditionFailed, 'Synthetic failure was not injected.');
    assertOwnTracksMissStateAdoption(
        hash_file('sha256', $statePath) === $sourceSha256
            && file_get_contents($statePath) === $sourceBytes,
        'Synthetic rollback did not restore the exact format-1 bytes.'
    );

    $legacy = json_decode(
        OwnTracksTileMissStateStore::prepareLegacyRollback($candidateBytes),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    assertOwnTracksMissStateAdoption(
        $legacy['version'] === 1
            && ownTracksMissStateSemantics($legacy) === ownTracksMissStateSemantics($source),
        'Fresh format-2 rollback preparation changed preserved state semantics.'
    );
} finally {
    removeOwnTracksMissStateAdoptionTree($temporaryRoot);
}

fwrite(STDOUT, "PASS: OwnTracksPositionMap miss-state adoption contract\n");
