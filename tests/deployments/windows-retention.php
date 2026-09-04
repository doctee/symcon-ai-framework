<?php

declare(strict_types=1);

function failRetention(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertRetention(bool $condition, string $message): void
{
    if (!$condition) {
        failRetention($message);
    }
}

/**
 * @param array<string, string> $deploymentToFileset
 * @param list<string> $filesets
 * @param list<array{deployment: string, fileset: string}> $candidates
 */
function retentionPlanIsSafe(array $deploymentToFileset, array $filesets, array $candidates): bool
{
    if (count($deploymentToFileset) !== count($filesets)) {
        return false;
    }
    $mapped = array_values($deploymentToFileset);
    sort($mapped);
    sort($filesets);
    if ($mapped !== $filesets || count(array_unique($mapped)) !== count($mapped)) {
        return false;
    }
    foreach ($candidates as $candidate) {
        if (($deploymentToFileset[$candidate['deployment']] ?? null) !== $candidate['fileset']) {
            return false;
        }
        unset($deploymentToFileset[$candidate['deployment']]);
        $index = array_search($candidate['fileset'], $filesets, true);
        if ($index === false) {
            return false;
        }
        unset($filesets[$index]);
    }
    $mapped = array_values($deploymentToFileset);
    sort($mapped);
    sort($filesets);
    return $mapped === $filesets;
}

$root = dirname(__DIR__, 2);
$scriptPath = $root . '/deployments/symcon/windows/Invoke-SaefDeploymentRetentionCleanup.ps1';
$planPath = $root . '/deployments/symcon/windows/deployment-retention-plan.example.json';
$script = file_get_contents($scriptPath);
$plan = json_decode((string) file_get_contents($planPath), true, flags: JSON_THROW_ON_ERROR);

assertRetention(is_string($script), 'Retention cleanup script is unreadable.');
assertRetention(is_array($plan), 'Retention plan example is invalid.');
foreach (
    [
        'Get-Inventory',
        'Assert-SimulatedInventory',
        'one-deployment-to-one-fileset invariant',
        'Candidate is not an exact manifest pair',
        'Runtime file references a deletion candidate',
        'Standalone module cleanup requires its adapter-owned retention workflow.',
        'Copy-VerifiedDirectory',
        'Apply requires an elevated local administrator',
        'Get-Inventory -StateRoot $stateRoot -FilesetRoot $filesetRoot',
    ] as $fragment
) {
    assertRetention(str_contains($script, $fragment), "Required retention contract is missing: {$fragment}");
}
assertRetention(!str_contains($script, 'deleteFileset'), 'Deployment-only deletion must be unsupported.');
assertRetention(
    substr_count($script, 'Remove-Item -LiteralPath') === 2,
    'Cleanup must contain exactly one paired deletion block.'
);

$validMap = ['active' => 'fileset-active', 'obsolete' => 'fileset-obsolete'];
$validFilesets = ['fileset-active', 'fileset-obsolete'];
assertRetention(
    retentionPlanIsSafe(
        $validMap,
        $validFilesets,
        [['deployment' => 'obsolete', 'fileset' => 'fileset-obsolete']]
    ),
    'Exact pair deletion should be accepted.'
);
assertRetention(
    !retentionPlanIsSafe(
        $validMap,
        $validFilesets,
        [['deployment' => 'obsolete', 'fileset' => 'fileset-active']]
    ),
    'Cross-pair deletion must be rejected.'
);
assertRetention(
    !retentionPlanIsSafe(
        $validMap,
        ['fileset-active', 'orphan'],
        [['deployment' => 'obsolete', 'fileset' => 'fileset-obsolete']]
    ),
    'Pre-existing orphan inventory must be rejected.'
);
assertRetention(
    !retentionPlanIsSafe(
        ['active' => 'fileset-active', 'duplicate' => 'fileset-active'],
        ['fileset-active', 'fileset-other'],
        [['deployment' => 'duplicate', 'fileset' => 'fileset-active']]
    ),
    'Multiple deployment owners for one fileset must be rejected.'
);

fwrite(STDOUT, "PASS: Windows deployment retention invariant\n");
