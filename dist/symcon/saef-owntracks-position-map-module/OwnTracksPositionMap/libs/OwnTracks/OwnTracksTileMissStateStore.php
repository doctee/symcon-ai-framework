<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Locked, bounded state for per-selection miss budgets and negative caching.
 */
final class OwnTracksTileMissStateStore
{
    private const FORMAT_VERSION = 2;
    private const MAXIMUM_SELECTIONS = 16;
    private const SELECTION_TTL_SECONDS = 3600;
    private const MAXIMUM_STATE_BYTES = 256 * 1024;
    private const HASH_PATTERN = '/^[a-f0-9]{64}$/D';
    private const TEMP_PATTERN = '/^\.state-[a-f0-9]{32}$/D';

    public function __construct(
        private readonly string $directory,
        private readonly ?OwnTracksTileDeadline $deadline = null
    ) {
        if (
            $directory === ''
            || strlen($directory) > 512
            || str_contains($directory, "\0")
            || preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#D', $directory) !== 1
        ) {
            throw new InvalidArgumentException(
                'Tile miss-state directory is invalid.'
            );
        }
    }

    public static function forSymconInstance(
        int $instanceId,
        ?OwnTracksTileDeadline $deadline = null
    ): self {
        if ($instanceId <= 0) {
            throw new InvalidArgumentException(
                'Tile miss-state owner is invalid.'
            );
        }

        return new self(
            rtrim(sys_get_temp_dir(), '/\\')
                . DIRECTORY_SEPARATOR . 'saef-owntracks-position-map-miss-state'
                . DIRECTORY_SEPARATOR . 'instance-' . $instanceId,
            $deadline
        );
    }

    /**
     * @template T
     * @param callable(array<string, mixed>&): T $operation
     * @return T
     */
    public function withSelection(
        string $selectionFingerprint,
        int $now,
        callable $operation
    ): mixed {
        if (
            preg_match(self::HASH_PATTERN, $selectionFingerprint) !== 1
            || $now < 0
        ) {
            throw new InvalidArgumentException('Tile miss-state key is invalid.');
        }

        return $this->locked(function (array &$store) use (
            $selectionFingerprint,
            $now,
            $operation
        ): mixed {
            $this->prune($store, $now);
            $entry = $store['selections'][$selectionFingerprint] ?? null;
            $state = is_array($entry) && is_array($entry['state'] ?? null)
                ? $entry['state']
                : [];
            $result = $operation($state);
            if ($state === []) {
                return $result;
            }
            if (!self::isValidResolverState($state, $selectionFingerprint)) {
                throw new RuntimeException('Tile miss-state result is invalid.');
            }
            $store['selections'][$selectionFingerprint] = [
                'updatedAt' => $now,
                'state' => $state,
            ];
            $this->enforceSelectionLimit($store);

            return $result;
        });
    }

    /**
     * Pure, case-local rollback preparation; never restores a stale backup.
     * The caller must quiesce writers and hash-gate any separately approved apply.
     * Pending worst-case debits stay charged when the old runtime has no ledger.
     */
    public static function prepareLegacyRollback(string $json): string
    {
        if (strlen($json) > self::MAXIMUM_STATE_BYTES) {
            throw new RuntimeException('Rollback state exceeds its budget.');
        }
        try {
            $store = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Rollback state is invalid.');
        }
        if (!is_array($store) || !self::isValidStore($store)) {
            throw new RuntimeException('Rollback state is invalid.');
        }
        foreach ($store['selections'] as &$entry) {
            unset($entry['state']['pendingReservations']);
        }
        unset($entry);
        $store['version'] = 1;

        return json_encode($store, JSON_THROW_ON_ERROR);
    }

    public function clear(): void
    {
        $this->locked(function (array &$store): null {
            $store = self::emptyStore();

            return null;
        });
    }

    /**
     * @template T
     * @param callable(array<string, mixed>&): T $operation
     * @return T
     */
    private function locked(callable $operation): mixed
    {
        $this->ensureDirectory();
        $lockPath = $this->directory . DIRECTORY_SEPARATOR . '.state.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Tile miss-state lock must not link.');
        }
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Tile miss-state lock is unavailable.');
        }
        @chmod($lockPath, 0600);
        try {
            ($this->deadline ?? new OwnTracksTileDeadline(250))->acquireLock($lock);
            $this->removeTemporaryFiles();
            $store = $this->readStore();
            $result = $operation($store);
            $this->writeStore($store);

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureDirectory(): void
    {
        if (is_link($this->directory)) {
            throw new RuntimeException('Tile miss-state root must not link.');
        }
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                throw new RuntimeException('Tile miss-state root cannot be created.');
            }
        }
        if (!is_writable($this->directory)) {
            throw new RuntimeException('Tile miss-state root is unavailable.');
        }
        @chmod($this->directory, 0700);
    }

    private function removeTemporaryFiles(): void
    {
        foreach (scandir($this->directory) ?: [] as $item) {
            if (preg_match(self::TEMP_PATTERN, $item) !== 1) {
                continue;
            }
            $path = $this->directory . DIRECTORY_SEPARATOR . $item;
            if (is_link($path) || !is_file($path) || !unlink($path)) {
                throw new RuntimeException('Tile miss-state temporary is unsafe.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function readStore(): array
    {
        $path = $this->statePath();
        if (is_link($path)) {
            throw new RuntimeException('Tile miss-state file must not link.');
        }
        if (!file_exists($path)) {
            return self::emptyStore();
        }
        if (!is_file($path)) {
            throw new RuntimeException('Tile miss-state file type is invalid.');
        }
        try {
            $size = filesize($path);
            if (!is_int($size) || $size > self::MAXIMUM_STATE_BYTES || $size < 2) {
                throw new RuntimeException('Tile miss-state file is invalid.');
            }
            $json = file_get_contents($path, false, null, 0, self::MAXIMUM_STATE_BYTES + 1);
            if (!is_string($json) || strlen($json) > self::MAXIMUM_STATE_BYTES) {
                throw new RuntimeException('Tile miss-state file is invalid.');
            }
            $store = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
            // One-way v1 migration preserves all consumed counters, never resets them.
            if (is_array($store) && ($store['version'] ?? null) === 1) {
                if (!is_array($store['selections'] ?? null)) {
                    throw new RuntimeException('Tile miss-state migration is invalid.');
                }
                foreach ($store['selections'] as &$entry) {
                    if (
                        !is_array($entry['state'] ?? null)
                        || array_key_exists('pendingReservations', $entry['state'])
                    ) {
                        throw new RuntimeException('Tile miss-state migration is invalid.');
                    }
                    $entry['state']['pendingReservations'] = [];
                }
                unset($entry);
                $store['version'] = self::FORMAT_VERSION;
            }
            if (!is_array($store) || !self::isValidStore($store)) {
                throw new RuntimeException('Tile miss-state file is invalid.');
            }

            return $store;
        } catch (Throwable) {
            throw new RuntimeException('Tile miss-state file requires explicit recovery.');
        }
    }

    /** @param array<string, mixed> $store */
    private function writeStore(array $store): void
    {
        if (!self::isValidStore($store)) {
            throw new RuntimeException('Tile miss-state cannot be written.');
        }
        $json = json_encode($store, JSON_THROW_ON_ERROR);
        if (strlen($json) > self::MAXIMUM_STATE_BYTES) {
            throw new RuntimeException('Tile miss-state exceeds its budget.');
        }
        $temporary = $this->directory . DIRECTORY_SEPARATOR . '.state-'
            . bin2hex(random_bytes(16));
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            @unlink($temporary);
            throw new RuntimeException('Tile miss-state write failed.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $this->statePath())) {
            @unlink($temporary);
            throw new RuntimeException('Tile miss-state replace failed.');
        }
    }

    /** @param array<string, mixed> $store */
    private function prune(array &$store, int $now): void
    {
        foreach ($store['selections'] as $fingerprint => $entry) {
            if ($entry['updatedAt'] < $now - self::SELECTION_TTL_SECONDS) {
                unset($store['selections'][$fingerprint]);
            }
        }
    }

    /** @param array<string, mixed> $store */
    private function enforceSelectionLimit(array &$store): void
    {
        uasort(
            $store['selections'],
            static fn (array $left, array $right): int =>
                $left['updatedAt'] <=> $right['updatedAt']
        );
        while (count($store['selections']) > self::MAXIMUM_SELECTIONS) {
            array_shift($store['selections']);
        }
    }

    /** @param array<string, mixed> $store */
    private static function isValidStore(array $store): bool
    {
        if (
            array_keys($store) !== ['version', 'selections']
            || $store['version'] !== self::FORMAT_VERSION
            || !is_array($store['selections'])
            || count($store['selections']) > self::MAXIMUM_SELECTIONS
        ) {
            return false;
        }
        foreach ($store['selections'] as $fingerprint => $entry) {
            if (
                preg_match(self::HASH_PATTERN, $fingerprint) !== 1
                || !is_array($entry)
                || array_keys($entry) !== ['updatedAt', 'state']
                || !is_int($entry['updatedAt'])
                || $entry['updatedAt'] < 0
                || !is_array($entry['state'])
                || !self::isValidResolverState($entry['state'], $fingerprint)
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $state */
    private static function isValidResolverState(
        array $state,
        string $selectionFingerprint
    ): bool {
        if (
            array_keys($state) !== [
                'selectionFingerprint',
                'upstreamRequests',
                'upstreamSuccesses',
                'upstreamBytes',
                'negativeCacheHits',
                'rejectedOutsideAllowlist',
                'budgetRejections',
                'negativeCache',
                'pendingReservations',
            ]
            || ($state['selectionFingerprint'] ?? null)
                !== $selectionFingerprint
        ) {
            return false;
        }
        foreach (array_slice(array_keys($state), 1, 6) as $counter) {
            if (!is_int($state[$counter]) || $state[$counter] < 0) {
                return false;
            }
        }
        if (!is_array($state['negativeCache']) || count($state['negativeCache']) > 256) {
            return false;
        }
        foreach ($state['negativeCache'] as $key => $expiresAt) {
            if (
                preg_match(self::HASH_PATTERN, $key) !== 1
                || !is_int($expiresAt)
                || $expiresAt < 0
            ) {
                return false;
            }
        }

        if (!is_array($state['pendingReservations']) || count($state['pendingReservations']) > 256) {
            return false;
        }
        foreach ($state['pendingReservations'] as $key => $reservation) {
            if (
                preg_match(self::HASH_PATTERN, $key) !== 1
                || !is_array($reservation)
                || array_keys($reservation) !== ['tileKey', 'bytes', 'expiresAt']
                || !is_string($reservation['tileKey'])
                || preg_match(self::HASH_PATTERN, $reservation['tileKey']) !== 1
                || !is_int($reservation['bytes']) || $reservation['bytes'] < 1
                || $reservation['bytes'] > 512 * 1024
                || !is_int($reservation['expiresAt']) || $reservation['expiresAt'] < 0
            ) {
                return false;
            }
        }

        return true;
    }

    /** @return array{version: int, selections: array<string, mixed>} */
    private static function emptyStore(): array
    {
        return ['version' => self::FORMAT_VERSION, 'selections' => []];
    }

    private function statePath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'state.json';
    }
}
