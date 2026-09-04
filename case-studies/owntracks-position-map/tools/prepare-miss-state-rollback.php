<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksTileMissStateStore;

require_once __DIR__ . '/../candidate/OwnTracksTileMissStateStore.php';

/** Offline preparation only. No Symcon connection, no active-state replacement. */
try {
    if (count($argv) !== 5 || $argv[1] !== '--prepare-legacy') {
        throw new RuntimeException('Usage: --prepare-legacy INPUT EXPECTED_SHA256 NEW_OUTPUT');
    }
    [$unused, $mode, $input, $expectedHash, $output] = $argv;
    if (
        !is_file($input) || is_link($input) || !is_readable($input)
        || file_exists($output) || is_link($output)
        || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1
        || filesize($input) > 256 * 1024
    ) {
        throw new RuntimeException('Rollback input or new output is invalid.');
    }
    $json = file_get_contents($input, false, null, 0, 256 * 1024 + 1);
    if (!is_string($json) || !hash_equals($expectedHash, hash('sha256', $json))) {
        throw new RuntimeException('Rollback snapshot hash differs.');
    }
    $legacy = OwnTracksTileMissStateStore::prepareLegacyRollback($json);
    $handle = fopen($output, 'xb');
    if ($handle === false) {
        throw new RuntimeException('Rollback output cannot be created exclusively.');
    }
    try {
        chmod($output, 0600);
        if (fwrite($handle, $legacy) !== strlen($legacy) || !fflush($handle)) {
            throw new RuntimeException('Rollback output is incomplete; do not apply.');
        }
    } finally {
        fclose($handle);
    }
    echo json_encode(['preparedOnly' => true, 'sourceUnchanged' => hash_file('sha256', $input) === $expectedHash,
        'outputSha256' => hash_file('sha256', $output), 'bytes' => strlen($legacy)], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
