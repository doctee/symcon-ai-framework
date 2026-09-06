<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Case-study-local, rebuildable filesystem cache for protected XYZ PNGs.
 */
final class OwnTracksTileFileCache
{
    private const FORMAT_VERSION = 1;
    private const DEFAULT_TILE_TTL_SECONDS = 300;
    private const DEFAULT_MAXIMUM_ENTRIES = 256;
    private const DEFAULT_MAXIMUM_TOTAL_BYTES = 16 * 1024 * 1024;
    private const MAXIMUM_TILE_BYTES = 512 * 1024;
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";
    private const TILE_FILE_PATTERN = '/^[a-f0-9]{64}\.png$/D';
    private const TEMP_FILE_PATTERN = '/^\.tmp-[a-f0-9]{32}$/D';

    public function __construct(
        private readonly string $directory,
        private readonly int $tileTtlSeconds = self::DEFAULT_TILE_TTL_SECONDS,
        private readonly int $maximumEntries = self::DEFAULT_MAXIMUM_ENTRIES,
        private readonly int $maximumTotalBytes = self::DEFAULT_MAXIMUM_TOTAL_BYTES,
        private readonly ?OwnTracksTileDeadline $deadline = null
    ) {
        if (
            $directory === ''
            || strlen($directory) > 512
            || str_contains($directory, "\0")
            || preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#D', $directory) !== 1
        ) {
            throw new InvalidArgumentException('Tile cache directory is invalid.');
        }
        if (
            $tileTtlSeconds < 60
            || $tileTtlSeconds > 45 * 86400
            || $maximumEntries < 1
            || $maximumEntries > 2048
            || $maximumTotalBytes < self::MAXIMUM_TILE_BYTES
            || $maximumTotalBytes > 128 * 1024 * 1024
        ) {
            throw new InvalidArgumentException('Tile cache retention policy is invalid.');
        }
    }

    public static function forSymconInstance(
        int $instanceId,
        ?OwnTracksTileDeadline $deadline = null
    ): self {
        if ($instanceId <= 0) {
            throw new InvalidArgumentException('Tile cache owner is invalid.');
        }
        $directory = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR . 'saef-owntracks-position-map-cache'
            . DIRECTORY_SEPARATOR . 'instance-' . $instanceId;

        return new self($directory, deadline: $deadline);
    }

    public function read(
        string $tileSetRevision,
        int $zoom,
        int $x,
        int $y,
        int $now
    ): ?string {
        $key = self::key($tileSetRevision, $zoom, $x, $y, $now);

        return $this->locked(function (array &$manifest) use ($key, $now): ?string {
            $this->prune($manifest, $now);
            $entry = $manifest['entries'][$key] ?? null;
            if (!is_array($entry)) {
                $manifest['misses']++;
                return null;
            }
            $path = $this->tilePath($key);
            $content = $this->readTileFile($path, $entry['bytes']);
            if ($content === null) {
                $this->removeEntry($manifest, $key);
                $manifest['misses']++;
                return null;
            }
            $manifest['sequence']++;
            $entry['lastAccessedAt'] = $now;
            $entry['lastAccessSequence'] = $manifest['sequence'];
            $manifest['entries'][$key] = $entry;
            $manifest['hits']++;

            return $content;
        });
    }

    public function write(
        string $tileSetRevision,
        int $zoom,
        int $x,
        int $y,
        string $content,
        int $now
    ): void {
        if (!self::isValidPng($content)) {
            throw new InvalidArgumentException('Tile cache content is invalid.');
        }
        $key = self::key($tileSetRevision, $zoom, $x, $y, $now);
        $this->locked(function (array &$manifest) use (
            $key,
            $content,
            $now
        ): null {
            $this->prune($manifest, $now);
            if (isset($manifest['entries'][$key])) {
                $this->removeEntry($manifest, $key);
            }
            $this->writeTileFile($this->tilePath($key), $content);
            $bytes = strlen($content);
            $manifest['sequence']++;
            $manifest['entries'][$key] = [
                'bytes' => $bytes,
                'storedAt' => $now,
                'lastAccessedAt' => $now,
                'lastAccessSequence' => $manifest['sequence'],
            ];
            $manifest['totalBytes'] += $bytes;
            $this->enforceLimits($manifest);

            return null;
        });
    }

    public function delete(
        string $tileSetRevision,
        int $zoom,
        int $x,
        int $y,
        int $now
    ): void {
        $key = self::key($tileSetRevision, $zoom, $x, $y, $now);
        $this->locked(function (array &$manifest) use ($key, $now): null {
            $this->prune($manifest, $now);
            $this->removeEntry($manifest, $key);

            return null;
        });
    }

    /** @return array{entries: int, totalBytes: int, hits: int, misses: int, evictions: int} */
    public function statistics(int $now): array
    {
        if ($now < 0) {
            throw new InvalidArgumentException('Tile cache time is invalid.');
        }

        return $this->locked(function (array &$manifest) use ($now): array {
            $this->prune($manifest, $now);

            return [
                'entries' => count($manifest['entries']),
                'totalBytes' => $manifest['totalBytes'],
                'hits' => $manifest['hits'],
                'misses' => $manifest['misses'],
                'evictions' => $manifest['evictions'],
            ];
        });
    }

    public function clear(): void
    {
        $this->locked(function (array &$manifest): null {
            foreach (array_keys($manifest['entries']) as $key) {
                $this->removeEntry($manifest, $key);
            }
            $manifest = self::emptyManifest();

            return null;
        });
    }

    private static function key(
        string $tileSetRevision,
        int $zoom,
        int $x,
        int $y,
        int $now
    ): string {
        if (
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D',
                $tileSetRevision
            ) !== 1
            || $zoom < 0
            || $zoom > 22
            || $x < 0
            || $y < 0
            || $now < 0
        ) {
            throw new InvalidArgumentException('Tile cache key is invalid.');
        }

        return hash(
            'sha256',
            $tileSetRevision . "\0" . $zoom . '/' . $x . '/' . $y
        );
    }

    /**
     * @template T
     * @param callable(array<string, mixed> &$manifest): T $operation
     * @return T
     */
    private function locked(callable $operation): mixed
    {
        $this->ensureDirectory();
        $lockPath = $this->directory . DIRECTORY_SEPARATOR . '.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Tile cache lock must not be a link.');
        }
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Tile cache lock is unavailable.');
        }
        @chmod($lockPath, 0600);
        try {
            ($this->deadline ?? new OwnTracksTileDeadline(250))->acquireLock($lock);
            $manifest = $this->readManifest();
            $this->removeOrphans($manifest);
            $result = $operation($manifest);
            $this->writeManifest($manifest);

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureDirectory(): void
    {
        if (is_link($this->directory)) {
            throw new RuntimeException('Tile cache directory must not be a link.');
        }
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                throw new RuntimeException('Tile cache directory cannot be created.');
            }
        }
        if (!is_writable($this->directory)) {
            throw new RuntimeException('Tile cache directory is unsafe.');
        }
        @chmod($this->directory, 0700);
    }

    /** @return array<string, mixed> */
    private function readManifest(): array
    {
        $path = $this->manifestPath();
        if (is_link($path)) {
            throw new RuntimeException('Tile cache manifest must not be a link.');
        }
        if (!is_file($path)) {
            return self::emptyManifest();
        }
        try {
            $json = file_get_contents($path);
            if (!is_string($json) || strlen($json) > 256 * 1024) {
                throw new RuntimeException('Tile cache manifest is invalid.');
            }
            $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !$this->isValidManifest($manifest)) {
                throw new RuntimeException('Tile cache manifest is invalid.');
            }

            return $manifest;
        } catch (Throwable) {
            $this->resetOwnedFiles();
            return self::emptyManifest();
        }
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(array $manifest): void
    {
        if (!$this->isValidManifest($manifest)) {
            throw new RuntimeException('Tile cache manifest cannot be written.');
        }
        $json = json_encode(
            $manifest,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        $temporary = $this->temporaryPath();
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            @unlink($temporary);
            throw new RuntimeException('Tile cache manifest write failed.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $this->manifestPath())) {
            @unlink($temporary);
            throw new RuntimeException('Tile cache manifest replace failed.');
        }
    }

    /** @param array<string, mixed> $manifest */
    private function prune(array &$manifest, int $now): void
    {
        foreach ($manifest['entries'] as $key => $entry) {
            if ($entry['storedAt'] + $this->tileTtlSeconds <= $now) {
                $this->removeEntry($manifest, $key);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function enforceLimits(array &$manifest): void
    {
        uasort(
            $manifest['entries'],
            static fn (array $left, array $right): int =>
                $left['lastAccessSequence'] <=> $right['lastAccessSequence']
        );
        while (
            count($manifest['entries']) > $this->maximumEntries
            || $manifest['totalBytes'] > $this->maximumTotalBytes
        ) {
            $key = array_key_first($manifest['entries']);
            if (!is_string($key)) {
                throw new RuntimeException('Tile cache bounds cannot be enforced.');
            }
            $this->removeEntry($manifest, $key);
        }
    }

    /** @param array<string, mixed> $manifest */
    private function removeEntry(array &$manifest, mixed $key): void
    {
        if (!is_string($key) || !isset($manifest['entries'][$key])) {
            return;
        }
        $entry = $manifest['entries'][$key];
        unset($manifest['entries'][$key]);
        $manifest['totalBytes'] = max(
            0,
            $manifest['totalBytes'] - $entry['bytes']
        );
        $manifest['evictions']++;
        $path = $this->tilePath($key);
        if (is_link($path)) {
            throw new RuntimeException('Tile cache entry must not be a link.');
        }
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Tile cache entry cannot be removed.');
        }
    }

    /** @param array<string, mixed> $manifest */
    private function removeOrphans(array $manifest): void
    {
        $items = scandir($this->directory);
        if (!is_array($items)) {
            throw new RuntimeException('Tile cache directory cannot be scanned.');
        }
        foreach ($items as $item) {
            $path = $this->directory . DIRECTORY_SEPARATOR . $item;
            $ownedTemporary = preg_match(self::TEMP_FILE_PATTERN, $item) === 1;
            $ownedTile = preg_match(self::TILE_FILE_PATTERN, $item) === 1;
            if (($ownedTemporary || $ownedTile) && is_link($path)) {
                throw new RuntimeException('Tile cache owned path must not be a link.');
            }
            if (
                ($ownedTemporary
                    || ($ownedTile
                        && !isset($manifest['entries'][substr($item, 0, 64)])))
                && is_file($path)
                && !unlink($path)
            ) {
                throw new RuntimeException('Tile cache orphan cannot be removed.');
            }
        }
    }

    private function resetOwnedFiles(): void
    {
        $items = scandir($this->directory);
        if (!is_array($items)) {
            throw new RuntimeException('Tile cache directory cannot be reset.');
        }
        foreach ($items as $item) {
            if (
                preg_match(self::TILE_FILE_PATTERN, $item) !== 1
                && preg_match(self::TEMP_FILE_PATTERN, $item) !== 1
                && $item !== 'manifest.json'
            ) {
                continue;
            }
            $path = $this->directory . DIRECTORY_SEPARATOR . $item;
            if (is_link($path)) {
                throw new RuntimeException('Tile cache reset path must not be a link.');
            }
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Tile cache reset failed.');
            }
        }
    }

    private function readTileFile(string $path, int $expectedBytes): ?string
    {
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        $content = file_get_contents($path);
        if (
            !is_string($content)
            || strlen($content) !== $expectedBytes
            || !self::isValidPng($content)
        ) {
            return null;
        }

        return $content;
    }

    private function writeTileFile(string $path, string $content): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Tile cache entry must not be a link.');
        }
        $temporary = $this->temporaryPath();
        if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) {
            @unlink($temporary);
            throw new RuntimeException('Tile cache entry write failed.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Tile cache entry replace failed.');
        }
    }

    private function tilePath(string $key): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) {
            throw new RuntimeException('Tile cache entry key is invalid.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $key . '.png';
    }

    private function manifestPath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'manifest.json';
    }

    private function temporaryPath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . '.tmp-'
            . bin2hex(random_bytes(16));
    }

    private static function isValidPng(string $content): bool
    {
        return $content !== ''
            && strlen($content) <= self::MAXIMUM_TILE_BYTES
            && str_starts_with($content, self::PNG_SIGNATURE);
    }

    /** @param array<string, mixed> $manifest */
    private function isValidManifest(array $manifest): bool
    {
        if (
            array_keys($manifest) !== [
                'version',
                'sequence',
                'totalBytes',
                'hits',
                'misses',
                'evictions',
                'entries',
            ]
            || $manifest['version'] !== self::FORMAT_VERSION
            || !is_int($manifest['sequence'])
            || !is_int($manifest['totalBytes'])
            || !is_int($manifest['hits'])
            || !is_int($manifest['misses'])
            || !is_int($manifest['evictions'])
            || $manifest['sequence'] < 0
            || $manifest['totalBytes'] < 0
            || $manifest['hits'] < 0
            || $manifest['misses'] < 0
            || $manifest['evictions'] < 0
            || !is_array($manifest['entries'])
            || count($manifest['entries']) > $this->maximumEntries
        ) {
            return false;
        }
        $totalBytes = 0;
        foreach ($manifest['entries'] as $key => $entry) {
            if (
                !is_string($key)
                || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1
                || !is_array($entry)
                || array_keys($entry) !== [
                    'bytes',
                    'storedAt',
                    'lastAccessedAt',
                    'lastAccessSequence',
                ]
                || !is_int($entry['bytes'])
                || $entry['bytes'] <= 0
                || $entry['bytes'] > self::MAXIMUM_TILE_BYTES
                || !is_int($entry['storedAt'])
                || $entry['storedAt'] < 0
                || !is_int($entry['lastAccessedAt'])
                || $entry['lastAccessedAt'] < $entry['storedAt']
                || !is_int($entry['lastAccessSequence'])
                || $entry['lastAccessSequence'] < 0
                || $entry['lastAccessSequence'] > $manifest['sequence']
            ) {
                return false;
            }
            $totalBytes += $entry['bytes'];
        }

        return $totalBytes === $manifest['totalBytes']
            && $totalBytes <= $this->maximumTotalBytes;
    }

    /** @return array<string, mixed> */
    private static function emptyManifest(): array
    {
        return [
            'version' => self::FORMAT_VERSION,
            'sequence' => 0,
            'totalBytes' => 0,
            'hits' => 0,
            'misses' => 0,
            'evictions' => 0,
            'entries' => [],
        ];
    }
}
