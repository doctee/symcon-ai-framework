<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Case-study-local provider cache metadata around the existing PNG byte store.
 */
final class OwnTracksProviderTileCache
{
    private const FORMAT_VERSION = 1;
    private const MAXIMUM_ENTRIES = 512;
    private const MAXIMUM_TOTAL_BYTES = 64 * 1024 * 1024;
    private const BYTE_RETENTION_SECONDS = 37 * 86400;
    private const MAXIMUM_STALE_SECONDS = 7 * 86400;
    private const MAXIMUM_ORIGIN_TTL_SECONDS = 30 * 86400;
    private const MAXIMUM_MANIFEST_BYTES = 512 * 1024;
    private const KEY_PATTERN = '/^[a-f0-9]{64}$/D';
    private const TEMP_PATTERN = '/^\.metadata-[a-f0-9]{32}$/D';

    private readonly OwnTracksTileFileCache $contentStore;

    public function __construct(
        private readonly string $directory,
        private readonly string $providerRevision,
        private readonly ?OwnTracksTileDeadline $deadline = null
    ) {
        if (
            $directory === ''
            || strlen($directory) > 512
            || str_contains($directory, "\0")
            || preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#D', $directory) !== 1
            || preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D',
                $providerRevision
            ) !== 1
        ) {
            throw new InvalidArgumentException('Provider tile-cache configuration is invalid.');
        }
        $this->contentStore = new OwnTracksTileFileCache(
            $directory . DIRECTORY_SEPARATOR . 'tiles',
            self::BYTE_RETENTION_SECONDS,
            self::MAXIMUM_ENTRIES,
            self::MAXIMUM_TOTAL_BYTES,
            $deadline
        );
    }

    public static function forSymconInstance(
        int $instanceId,
        string $providerRevision,
        ?OwnTracksTileDeadline $deadline = null
    ): self {
        if ($instanceId <= 0) {
            throw new InvalidArgumentException(
                'Provider tile-cache owner is invalid.'
            );
        }

        return new self(
            rtrim(sys_get_temp_dir(), '/\\')
                . DIRECTORY_SEPARATOR . 'saef-owntracks-position-map-provider-cache'
                . DIRECTORY_SEPARATOR . 'instance-' . $instanceId,
            $providerRevision,
            $deadline
        );
    }

    /**
     * @return array{
     *   state: 'fresh'|'stale'|'miss',
     *   content: string|null,
     *   conditionalHeaders: array<string, string>
     * }
     */
    public function lookup(int $zoom, int $x, int $y, int $now): array
    {
        $key = $this->key($zoom, $x, $y, $now);
        $entry = $this->locked(function (array &$manifest) use ($key, $now): ?array {
            $this->pruneMetadata($manifest, $now);
            $candidate = $manifest['entries'][$key] ?? null;
            if (!is_array($candidate)) {
                $manifest['misses']++;
                return null;
            }
            $manifest['sequence']++;
            $candidate['lastAccessedAt'] = $now;
            $candidate['lastAccessSequence'] = $manifest['sequence'];
            $manifest['entries'][$key] = $candidate;

            return $candidate;
        });
        if ($entry === null) {
            return self::miss();
        }
        $content = $this->contentStore->read(
            $this->providerRevision,
            $zoom,
            $x,
            $y,
            $now
        );
        if ($content === null) {
            $this->locked(function (array &$manifest) use ($key): null {
                unset($manifest['entries'][$key]);
                $manifest['misses']++;

                return null;
            });
            return self::miss();
        }
        $fresh = $entry['expiresAt'] > $now;
        $this->locked(function (array &$manifest) use ($fresh): null {
            if ($fresh) {
                $manifest['freshHits']++;
            } else {
                $manifest['staleHits']++;
            }

            return null;
        });

        return [
            'state' => $fresh ? 'fresh' : 'stale',
            // Stale bytes are retained only for a successful 304 refresh. They
            // must never become a fallback response after a provider failure.
            'content' => $fresh ? $content : null,
            'conditionalHeaders' => $fresh
                ? []
                : self::conditionalHeaders($entry),
        ];
    }

    /** @param array<string, mixed> $response */
    public function store200(
        int $zoom,
        int $x,
        int $y,
        array $response,
        int $now
    ): void {
        $metadata = $this->responseMetadata($response, 200, $now);
        if ($metadata === null) {
            $this->discard($zoom, $x, $y, $now);
            return;
        }
        $content = $response['body'] ?? null;
        if (!is_string($content)) {
            throw new InvalidArgumentException('Provider tile-cache body is invalid.');
        }
        $key = $this->key($zoom, $x, $y, $now);
        $this->contentStore->write(
            $this->providerRevision,
            $zoom,
            $x,
            $y,
            $content,
            $now
        );
        $this->locked(function (array &$manifest) use ($key, $metadata, $now): null {
            $this->pruneMetadata($manifest, $now);
            $manifest['sequence']++;
            $manifest['entries'][$key] = [
                'storedAt' => $now,
                'expiresAt' => $metadata['expiresAt'],
                'retainUntil' => $metadata['retainUntil'],
                'etag' => $metadata['etag'],
                'lastModified' => $metadata['lastModified'],
                'lastAccessedAt' => $now,
                'lastAccessSequence' => $manifest['sequence'],
            ];
            $manifest['writes']++;
            $this->enforceMetadataLimit($manifest);

            return null;
        });
    }

    /** @param array<string, mixed> $response */
    public function refresh304(
        int $zoom,
        int $x,
        int $y,
        array $response,
        int $now
    ): bool {
        $metadata = $this->responseMetadata($response, 304, $now);
        if ($metadata === null) {
            $this->discard($zoom, $x, $y, $now);
            return false;
        }
        $key = $this->key($zoom, $x, $y, $now);
        $content = $this->contentStore->read(
            $this->providerRevision,
            $zoom,
            $x,
            $y,
            $now
        );
        if ($content === null) {
            return false;
        }
        $this->contentStore->write(
            $this->providerRevision,
            $zoom,
            $x,
            $y,
            $content,
            $now
        );

        return $this->locked(function (array &$manifest) use (
            $key,
            $metadata,
            $now
        ): bool {
            $entry = $manifest['entries'][$key] ?? null;
            if (!is_array($entry)) {
                return false;
            }
            $manifest['sequence']++;
            $entry['storedAt'] = $now;
            $entry['expiresAt'] = $metadata['expiresAt'];
            $entry['retainUntil'] = $metadata['retainUntil'];
            $entry['etag'] = $metadata['etag'] ?? $entry['etag'];
            $entry['lastModified'] = $metadata['lastModified']
                ?? $entry['lastModified'];
            $entry['lastAccessedAt'] = $now;
            $entry['lastAccessSequence'] = $manifest['sequence'];
            $manifest['entries'][$key] = $entry;
            $manifest['revalidations']++;

            return true;
        });
    }

    public function discard(int $zoom, int $x, int $y, int $now): void
    {
        $key = $this->key($zoom, $x, $y, $now);
        $this->contentStore->delete(
            $this->providerRevision,
            $zoom,
            $x,
            $y,
            $now
        );
        $this->locked(function (array &$manifest) use ($key): null {
            unset($manifest['entries'][$key]);
            $manifest['discards']++;

            return null;
        });
    }

    /**
     * @return array{
     *   entries: int,
     *   totalBytes: int,
     *   freshHits: int,
     *   staleHits: int,
     *   misses: int,
     *   writes: int,
     *   revalidations: int,
     *   discards: int,
     *   evictions: int
     * }
     */
    public function statistics(int $now): array
    {
        if ($now < 0) {
            throw new InvalidArgumentException('Provider tile-cache time is invalid.');
        }
        $metadata = $this->locked(function (array &$manifest) use ($now): array {
            $this->pruneMetadata($manifest, $now);
            return $manifest;
        });
        $content = $this->contentStore->statistics($now);

        return [
            'entries' => count($metadata['entries']),
            'totalBytes' => $content['totalBytes'],
            'freshHits' => $metadata['freshHits'],
            'staleHits' => $metadata['staleHits'],
            'misses' => $metadata['misses'],
            'writes' => $metadata['writes'],
            'revalidations' => $metadata['revalidations'],
            'discards' => $metadata['discards'],
            'evictions' => $metadata['evictions'] + $content['evictions'],
        ];
    }

    public function clear(): void
    {
        $this->contentStore->clear();
        $this->locked(function (array &$manifest): null {
            $manifest = self::emptyManifest();

            return null;
        });
    }

    /**
     * @param array<string, mixed> $response
     * @return array{
     *   expiresAt: int,
     *   retainUntil: int,
     *   etag: string|null,
     *   lastModified: string|null
     * }|null
     */
    private function responseMetadata(array $response, int $status, int $now): ?array
    {
        if (($response['status'] ?? null) !== $status) {
            throw new InvalidArgumentException('Provider tile-cache status is invalid.');
        }
        if (($response['cacheable'] ?? null) !== true) {
            return null;
        }
        $ttl = $response['cacheTtlSeconds'] ?? null;
        if (
            !is_int($ttl)
            || $ttl < 1
            || $ttl > self::MAXIMUM_ORIGIN_TTL_SECONDS
            || $now < 0
            || $now > PHP_INT_MAX - $ttl - self::MAXIMUM_STALE_SECONDS
        ) {
            throw new InvalidArgumentException('Provider tile-cache TTL is invalid.');
        }
        $etag = self::validator($response['etag'] ?? null, 'ETag');
        $lastModified = self::validator(
            $response['lastModified'] ?? null,
            'Last-Modified'
        );
        $expiresAt = $now + $ttl;

        return [
            'expiresAt' => $expiresAt,
            'retainUntil' => $expiresAt + self::MAXIMUM_STALE_SECONDS,
            'etag' => $etag,
            'lastModified' => $lastModified,
        ];
    }

    private static function validator(mixed $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }
        if (
            !is_string($value)
            || $value === ''
            || strlen($value) > 512
            || preg_match('/[\x00-\x1f\x7f]/', $value) === 1
        ) {
            throw new InvalidArgumentException(
                'Provider tile-cache ' . $name . ' is invalid.'
            );
        }

        return $value;
    }

    /** @param array<string, mixed> $entry @return array<string, string> */
    private static function conditionalHeaders(array $entry): array
    {
        $headers = [];
        if (is_string($entry['etag'] ?? null)) {
            $headers['If-None-Match'] = $entry['etag'];
        }
        if (is_string($entry['lastModified'] ?? null)) {
            $headers['If-Modified-Since'] = $entry['lastModified'];
        }

        return $headers;
    }

    /** @return array{state: 'miss', content: null, conditionalHeaders: array{}} */
    private static function miss(): array
    {
        return [
            'state' => 'miss',
            'content' => null,
            'conditionalHeaders' => [],
        ];
    }

    private function key(int $zoom, int $x, int $y, int $now): string
    {
        if (
            $zoom < 0
            || $zoom > 22
            || $x < 0
            || $y < 0
            || $now < 0
        ) {
            throw new InvalidArgumentException('Provider tile-cache key is invalid.');
        }
        $side = 2 ** $zoom;
        if ($x >= $side || $y >= $side) {
            throw new InvalidArgumentException('Provider tile-cache key is invalid.');
        }

        return hash(
            'sha256',
            $this->providerRevision . "\0" . $zoom . '/' . $x . '/' . $y
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
        $lockPath = $this->directory . DIRECTORY_SEPARATOR . '.metadata.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Provider tile-cache lock must not be a link.');
        }
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Provider tile-cache lock is unavailable.');
        }
        @chmod($lockPath, 0600);
        try {
            ($this->deadline ?? new OwnTracksTileDeadline(250))->acquireLock($lock);
            $this->removeTemporaryFiles();
            $manifest = $this->readManifest();
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
            throw new RuntimeException('Provider tile-cache root must not be a link.');
        }
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                throw new RuntimeException('Provider tile-cache root cannot be created.');
            }
        }
        if (!is_writable($this->directory)) {
            throw new RuntimeException('Provider tile-cache root is unavailable.');
        }
        @chmod($this->directory, 0700);
    }

    /** @return array<string, mixed> */
    private function readManifest(): array
    {
        $path = $this->manifestPath();
        if (is_link($path)) {
            throw new RuntimeException('Provider tile-cache manifest must not be a link.');
        }
        if (!is_file($path)) {
            return self::emptyManifest();
        }
        try {
            $json = file_get_contents($path);
            if (!is_string($json) || strlen($json) > self::MAXIMUM_MANIFEST_BYTES) {
                throw new RuntimeException('Provider tile-cache manifest is invalid.');
            }
            $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !self::isValidManifest($manifest)) {
                throw new RuntimeException('Provider tile-cache manifest is invalid.');
            }

            return $manifest;
        } catch (Throwable) {
            if (!unlink($path)) {
                throw new RuntimeException('Provider tile-cache manifest cannot reset.');
            }
            return self::emptyManifest();
        }
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(array $manifest): void
    {
        if (!self::isValidManifest($manifest)) {
            throw new RuntimeException('Provider tile-cache manifest cannot be written.');
        }
        $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > self::MAXIMUM_MANIFEST_BYTES) {
            throw new RuntimeException('Provider tile-cache manifest exceeds its budget.');
        }
        $temporary = $this->directory . DIRECTORY_SEPARATOR . '.metadata-'
            . bin2hex(random_bytes(16));
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            @unlink($temporary);
            throw new RuntimeException('Provider tile-cache manifest write failed.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $this->manifestPath())) {
            @unlink($temporary);
            throw new RuntimeException('Provider tile-cache manifest replace failed.');
        }
    }

    /** @param array<string, mixed> $manifest */
    private function pruneMetadata(array &$manifest, int $now): void
    {
        foreach ($manifest['entries'] as $key => $entry) {
            if ($entry['retainUntil'] <= $now) {
                unset($manifest['entries'][$key]);
                $manifest['evictions']++;
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function enforceMetadataLimit(array &$manifest): void
    {
        uasort(
            $manifest['entries'],
            static fn (array $left, array $right): int =>
                $left['lastAccessSequence'] <=> $right['lastAccessSequence']
        );
        while (count($manifest['entries']) > self::MAXIMUM_ENTRIES) {
            $key = array_key_first($manifest['entries']);
            if (!is_string($key)) {
                throw new RuntimeException('Provider cache bounds cannot be enforced.');
            }
            unset($manifest['entries'][$key]);
            $manifest['evictions']++;
        }
    }

    private function removeTemporaryFiles(): void
    {
        $items = scandir($this->directory);
        if (!is_array($items)) {
            throw new RuntimeException('Provider tile-cache root cannot be scanned.');
        }
        foreach ($items as $item) {
            if (preg_match(self::TEMP_PATTERN, $item) !== 1) {
                continue;
            }
            $path = $this->directory . DIRECTORY_SEPARATOR . $item;
            if (is_link($path)) {
                throw new RuntimeException('Provider tile-cache temporary must not link.');
            }
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Provider tile-cache temporary cannot be removed.');
            }
        }
    }

    private function manifestPath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'metadata.json';
    }

    /** @param array<string, mixed> $manifest */
    private static function isValidManifest(array $manifest): bool
    {
        $keys = [
            'version',
            'sequence',
            'freshHits',
            'staleHits',
            'misses',
            'writes',
            'revalidations',
            'discards',
            'evictions',
            'entries',
        ];
        if (array_keys($manifest) !== $keys || $manifest['version'] !== self::FORMAT_VERSION) {
            return false;
        }
        foreach (array_slice($keys, 1, -1) as $counter) {
            if (!is_int($manifest[$counter]) || $manifest[$counter] < 0) {
                return false;
            }
        }
        if (!is_array($manifest['entries']) || count($manifest['entries']) > self::MAXIMUM_ENTRIES) {
            return false;
        }
        foreach ($manifest['entries'] as $key => $entry) {
            if (
                preg_match(self::KEY_PATTERN, $key) !== 1
                || !is_array($entry)
                || array_keys($entry) !== [
                    'storedAt',
                    'expiresAt',
                    'retainUntil',
                    'etag',
                    'lastModified',
                    'lastAccessedAt',
                    'lastAccessSequence',
                ]
                || !is_int($entry['storedAt'])
                || !is_int($entry['expiresAt'])
                || !is_int($entry['retainUntil'])
                || $entry['storedAt'] < 0
                || $entry['expiresAt'] <= $entry['storedAt']
                || $entry['retainUntil'] <= $entry['expiresAt']
                || $entry['expiresAt'] - $entry['storedAt']
                    > self::MAXIMUM_ORIGIN_TTL_SECONDS
                || $entry['retainUntil'] - $entry['expiresAt']
                    > self::MAXIMUM_STALE_SECONDS
                || !self::isStoredValidator($entry['etag'])
                || !self::isStoredValidator($entry['lastModified'])
                || !is_int($entry['lastAccessedAt'])
                || $entry['lastAccessedAt'] < $entry['storedAt']
                || !is_int($entry['lastAccessSequence'])
                || $entry['lastAccessSequence'] < 0
                || $entry['lastAccessSequence'] > $manifest['sequence']
            ) {
                return false;
            }
        }

        return true;
    }

    private static function isStoredValidator(mixed $value): bool
    {
        return $value === null
            || is_string($value)
                && $value !== ''
                && strlen($value) <= 512
                && preg_match('/[\x00-\x1f\x7f]/', $value) !== 1;
    }

    /** @return array<string, mixed> */
    private static function emptyManifest(): array
    {
        return [
            'version' => self::FORMAT_VERSION,
            'sequence' => 0,
            'freshHits' => 0,
            'staleHits' => 0,
            'misses' => 0,
            'writes' => 0,
            'revalidations' => 0,
            'discards' => 0,
            'evictions' => 0,
            'entries' => [],
        ];
    }
}
