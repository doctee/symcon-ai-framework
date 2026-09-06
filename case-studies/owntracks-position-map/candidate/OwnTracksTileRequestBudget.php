<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Locked, case-study-local admission state for separate WebHook executions.
 */
final class OwnTracksTileRequestBudget
{
    private const FORMAT_VERSION = 1;
    private const MAXIMUM_CLIENTS = 64;
    private const MAXIMUM_STATE_BYTES = 64 * 1024;
    private const LEASE_TTL_SECONDS = 30;
    private const HASH_PATTERN = '/^[a-f0-9]{64}$/D';

    public function __construct(
        private readonly string $directory,
        private readonly ?OwnTracksTileDeadline $deadline = null
    ) {
        $this->assertAbsolutePath($directory);
        $this->prepareDirectory();
    }

    public static function forSymconInstance(
        int $instanceID,
        ?OwnTracksTileDeadline $deadline = null
    ): self {
        if ($instanceID <= 0) {
            throw new InvalidArgumentException(
                'Tile request-budget instance ID is invalid.'
            );
        }
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'saef-owntracks-position-map-budget'
            . DIRECTORY_SEPARATOR . 'instance-' . $instanceID;

        return new self($root, $deadline);
    }

    /**
     * @return array{
     *   accepted: bool,
     *   classification: string,
     *   reservation: null|array{clientKey: string, leaseKey: string}
     * }
     */
    public function begin(
        string $capabilityId,
        int $now,
        int $maximumRequestsPerMinute,
        int $maximumConcurrentRequests
    ): array {
        if (
            $capabilityId === ''
            || strlen($capabilityId) > 128
            || $now < 0
            || $maximumRequestsPerMinute < 1
            || $maximumRequestsPerMinute > 1200
            || $maximumConcurrentRequests < 1
            || $maximumConcurrentRequests > 16
        ) {
            throw new InvalidArgumentException(
                'Tile request-budget input is invalid.'
            );
        }
        $clientKey = hash('sha256', $capabilityId);
        $leaseKey = hash('sha256', random_bytes(32));

        return $this->withLock(function (array &$state) use (
            $clientKey,
            $leaseKey,
            $now,
            $maximumRequestsPerMinute,
            $maximumConcurrentRequests
        ): array {
            $this->prune($state, $now);
            $windowStartedAt = intdiv($now, 60) * 60;
            $client = $state['clients'][$clientKey] ?? null;
            if (!is_array($client)) {
                $client = [
                    'windowStartedAt' => $windowStartedAt,
                    'requests' => 0,
                    'updatedAt' => $now,
                    'leases' => [],
                ];
            } elseif ($client['windowStartedAt'] !== $windowStartedAt) {
                $client['windowStartedAt'] = $windowStartedAt;
                $client['requests'] = 0;
                $client['updatedAt'] = $now;
            }
            if ($client['requests'] >= $maximumRequestsPerMinute) {
                return [
                    'accepted' => false,
                    'classification' => 'rate-limited',
                    'reservation' => null,
                ];
            }
            if (count($client['leases']) >= $maximumConcurrentRequests) {
                return [
                    'accepted' => false,
                    'classification' => 'concurrency-limited',
                    'reservation' => null,
                ];
            }
            if (
                !isset($state['clients'][$clientKey])
                && count($state['clients']) >= self::MAXIMUM_CLIENTS
            ) {
                return [
                    'accepted' => false,
                    'classification' => 'capacity-limited',
                    'reservation' => null,
                ];
            }
            $client['requests']++;
            $client['updatedAt'] = $now;
            $client['leases'][$leaseKey] = $now + self::LEASE_TTL_SECONDS;
            $state['clients'][$clientKey] = $client;

            return [
                'accepted' => true,
                'classification' => 'accepted',
                'reservation' => [
                    'clientKey' => $clientKey,
                    'leaseKey' => $leaseKey,
                ],
            ];
        });
    }

    /** @param array{clientKey: string, leaseKey: string} $reservation */
    public function finish(array $reservation, int $now): void
    {
        $clientKey = $reservation['clientKey'];
        $leaseKey = $reservation['leaseKey'];
        if (
            $now < 0
            || preg_match(self::HASH_PATTERN, $clientKey) !== 1
            || preg_match(self::HASH_PATTERN, $leaseKey) !== 1
        ) {
            throw new InvalidArgumentException(
                'Tile request-budget reservation is invalid.'
            );
        }
        $this->withLock(function (array &$state) use (
            $clientKey,
            $leaseKey,
            $now
        ): null {
            $this->prune($state, $now);
            $client = $state['clients'][$clientKey] ?? null;
            if (!is_array($client) || !isset($client['leases'][$leaseKey])) {
                return null;
            }
            unset($client['leases'][$leaseKey]);
            $client['updatedAt'] = $now;
            $state['clients'][$clientKey] = $client;

            return null;
        });
    }

    /**
     * @template T
     * @param callable(array<string, mixed>&): T $operation
     * @return T
     */
    private function withLock(callable $operation): mixed
    {
        $lockPath = $this->directory . DIRECTORY_SEPARATOR . 'budget.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Tile request-budget lock is unsafe.');
        }
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Tile request-budget lock failed.');
        }
        @chmod($lockPath, 0600);
        try {
            ($this->deadline ?? new OwnTracksTileDeadline(250))->acquireLock($lock);
            $state = $this->readState();
            $result = $operation($state);
            $this->writeState($state);

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        $path = $this->statePath();
        if (!file_exists($path)) {
            return self::emptyState();
        }
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Tile request-budget state is unsafe.');
        }
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > self::MAXIMUM_STATE_BYTES) {
            throw new RuntimeException('Tile request-budget state is invalid.');
        }
        $json = file_get_contents($path);
        if (!is_string($json)) {
            throw new RuntimeException('Tile request-budget state cannot be read.');
        }
        try {
            $state = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Tile request-budget state is invalid.');
        }
        if (!is_array($state) || !self::isValidState($state)) {
            throw new RuntimeException('Tile request-budget state is invalid.');
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function writeState(array $state): void
    {
        if (!self::isValidState($state)) {
            throw new RuntimeException('Tile request-budget state is invalid.');
        }
        $json = json_encode(
            $state,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if (strlen($json) > self::MAXIMUM_STATE_BYTES) {
            throw new RuntimeException('Tile request-budget state is too large.');
        }
        $temporary = $this->directory . DIRECTORY_SEPARATOR . '.tmp-'
            . bin2hex(random_bytes(16));
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            @unlink($temporary);
            throw new RuntimeException('Tile request-budget write failed.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $this->statePath())) {
            @unlink($temporary);
            throw new RuntimeException('Tile request-budget replace failed.');
        }
    }

    /** @param array<string, mixed> $state */
    private function prune(array &$state, int $now): void
    {
        foreach ($state['clients'] as $clientKey => $client) {
            foreach ($client['leases'] as $leaseKey => $expiresAt) {
                if ($expiresAt <= $now) {
                    unset($client['leases'][$leaseKey]);
                }
            }
            if (
                $client['leases'] === []
                && $client['updatedAt'] < $now - 120
            ) {
                unset($state['clients'][$clientKey]);
                continue;
            }
            $state['clients'][$clientKey] = $client;
        }
        if (count($state['clients']) < self::MAXIMUM_CLIENTS) {
            return;
        }
        uasort(
            $state['clients'],
            static fn (array $left, array $right): int =>
                $left['updatedAt'] <=> $right['updatedAt']
        );
        foreach ($state['clients'] as $clientKey => $client) {
            if (count($state['clients']) < self::MAXIMUM_CLIENTS) {
                break;
            }
            if ($client['leases'] === []) {
                unset($state['clients'][$clientKey]);
            }
        }
    }

    /** @param array<string, mixed> $state */
    private static function isValidState(array $state): bool
    {
        if (
            array_keys($state) !== ['version', 'clients']
            || $state['version'] !== self::FORMAT_VERSION
            || !is_array($state['clients'])
            || count($state['clients']) > self::MAXIMUM_CLIENTS
        ) {
            return false;
        }
        foreach ($state['clients'] as $clientKey => $client) {
            if (
                !is_string($clientKey)
                || preg_match(self::HASH_PATTERN, $clientKey) !== 1
                || !is_array($client)
                || array_keys($client) !== [
                    'windowStartedAt',
                    'requests',
                    'updatedAt',
                    'leases',
                ]
                || !is_int($client['windowStartedAt'])
                || $client['windowStartedAt'] < 0
                || !is_int($client['requests'])
                || $client['requests'] < 0
                || $client['requests'] > 1200
                || !is_int($client['updatedAt'])
                || $client['updatedAt'] < 0
                || !is_array($client['leases'])
                || count($client['leases']) > 16
            ) {
                return false;
            }
            foreach ($client['leases'] as $leaseKey => $expiresAt) {
                if (
                    !is_string($leaseKey)
                    || preg_match(self::HASH_PATTERN, $leaseKey) !== 1
                    || !is_int($expiresAt)
                    || $expiresAt < 0
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array{version: int, clients: array<string, mixed>} */
    private static function emptyState(): array
    {
        return ['version' => self::FORMAT_VERSION, 'clients' => []];
    }

    private function prepareDirectory(): void
    {
        if (!file_exists($this->directory) && !mkdir($this->directory, 0700, true)) {
            throw new RuntimeException(
                'Tile request-budget directory cannot be created.'
            );
        }
        $this->assertPreparedDirectory();
        @chmod($this->directory, 0700);
    }

    private function assertPreparedDirectory(): void
    {
        if (!is_dir($this->directory) || is_link($this->directory)) {
            throw new RuntimeException('Tile request-budget directory is unsafe.');
        }
    }

    private function assertAbsolutePath(string $path): void
    {
        $isUnix = str_starts_with($path, DIRECTORY_SEPARATOR);
        $isWindows = preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1;
        if ($path === '' || (!$isUnix && !$isWindows)) {
            throw new InvalidArgumentException(
                'Tile request-budget path must be absolute.'
            );
        }
    }

    private function statePath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'budget.json';
    }
}
