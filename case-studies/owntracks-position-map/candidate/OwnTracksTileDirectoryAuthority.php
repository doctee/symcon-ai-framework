<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

/**
 * Case-study-local, read-only authority for pre-provisioned XYZ PNG tiles.
 */
final class OwnTracksTileDirectoryAuthority
{
    private const MAXIMUM_TILE_BYTES = 512 * 1024;
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";
    private const REVISION_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';

    /** @var array<string, mixed> */
    private readonly array $configuration;
    private readonly string $root;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->configuration = self::normalize($configuration);
        if (($this->configuration['enabled'] ?? false) !== true) {
            throw new InvalidArgumentException('Tile authority is disabled.');
        }
        $configuredRoot = $this->configuration['rootPath'];
        if (is_link($configuredRoot) || !is_dir($configuredRoot)) {
            throw new InvalidArgumentException('Tile authority root is unavailable.');
        }
        $canonicalRoot = realpath($configuredRoot);
        if (
            $canonicalRoot === false
            || !self::samePath($configuredRoot, $canonicalRoot)
        ) {
            throw new InvalidArgumentException(
                'Tile authority root must be a canonical directory without links.'
            );
        }
        $this->root = rtrim($canonicalRoot, '/\\');
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function normalize(array $configuration): array
    {
        $mode = $configuration['mode'] ?? null;
        if ($mode === 'none') {
            return [
                'mode' => 'none',
                'enabled' => false,
                'kind' => 'none',
            ];
        }
        if ($mode !== 'private-xyz-directory') {
            throw new InvalidArgumentException('Tile authority mode is invalid.');
        }
        $rootPath = $configuration['rootPath'] ?? null;
        if (!self::isSafeAbsoluteRoot($rootPath)) {
            throw new InvalidArgumentException('Tile authority root is invalid.');
        }
        $revision = $configuration['tileSetRevision'] ?? null;
        if (
            !is_string($revision)
            || preg_match(self::REVISION_PATTERN, $revision) !== 1
        ) {
            throw new InvalidArgumentException('Tile-set revision is invalid.');
        }
        $minimumZoom = $configuration['minimumZoom'] ?? null;
        $maximumZoom = $configuration['maximumZoom'] ?? null;
        $tileSize = $configuration['tileSizePixels'] ?? null;
        if (
            !is_int($minimumZoom)
            || !is_int($maximumZoom)
            || $minimumZoom < 0
            || $maximumZoom < $minimumZoom
            || $maximumZoom > 22
        ) {
            throw new InvalidArgumentException('Tile authority zoom range is invalid.');
        }
        if (!in_array($tileSize, [256, 512], true)) {
            throw new InvalidArgumentException('Tile authority tile size is invalid.');
        }

        return [
            'mode' => 'private-xyz-directory',
            'enabled' => true,
            'kind' => 'xyz-png-directory',
            'rootPath' => rtrim($rootPath, '/\\'),
            'tileSetRevision' => $revision,
            'minimumZoom' => $minimumZoom,
            'maximumZoom' => $maximumZoom,
            'tileSizePixels' => $tileSize,
            'networkAccess' => false,
            'readOnly' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        return $this->configuration;
    }

    /** @return array{content: string}|null */
    public function read(int $zoom, int $x, int $y): ?array
    {
        $minimumZoom = $this->configuration['minimumZoom'];
        $maximumZoom = $this->configuration['maximumZoom'];
        if ($zoom < $minimumZoom || $zoom > $maximumZoom) {
            throw new InvalidArgumentException('Tile zoom is outside authority bounds.');
        }
        $side = 2 ** $zoom;
        if ($x < 0 || $y < 0 || $x >= $side || $y >= $side) {
            throw new InvalidArgumentException('Tile index is outside XYZ bounds.');
        }

        $zoomDirectory = $this->root . DIRECTORY_SEPARATOR . $zoom;
        $xDirectory = $zoomDirectory . DIRECTORY_SEPARATOR . $x;
        $path = $xDirectory . DIRECTORY_SEPARATOR . $y . '.png';
        foreach ([$zoomDirectory, $xDirectory] as $directory) {
            if (is_link($directory) || !is_dir($directory)) {
                return null;
            }
        }
        if (is_link($path) || !is_file($path)) {
            return null;
        }
        $canonicalPath = realpath($path);
        if (
            $canonicalPath === false
            || !self::isContainedBy($this->root, $canonicalPath)
        ) {
            return null;
        }

        $stream = fopen($canonicalPath, 'rb');
        if ($stream === false) {
            return null;
        }
        try {
            if (!flock($stream, LOCK_SH)) {
                return null;
            }
            $stat = fstat($stream);
            $pathStat = lstat($path);
            $resolvedAfterOpen = realpath($path);
            if (
                $stat === false
                || $pathStat === false
                || $resolvedAfterOpen === false
                || ($pathStat['mode'] & 0170000) === 0120000
                || !self::samePath($canonicalPath, $resolvedAfterOpen)
                || $stat['dev'] !== $pathStat['dev']
                || $stat['ino'] !== $pathStat['ino']
                || ($stat['mode'] & 0170000) !== 0100000
                || $stat['size'] <= 0
                || $stat['size'] > self::MAXIMUM_TILE_BYTES
            ) {
                return null;
            }
            $content = stream_get_contents($stream, $stat['size'] + 1);
            if (!is_string($content) || strlen($content) !== $stat['size']) {
                return null;
            }
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
        if (!$this->isValidPng($content)) {
            return null;
        }

        return ['content' => $content];
    }

    private function isValidPng(string $content): bool
    {
        if (!str_starts_with($content, self::PNG_SIGNATURE)) {
            return false;
        }
        $length = strlen($content);
        $offset = strlen(self::PNG_SIGNATURE);
        $chunkIndex = 0;
        $hasImageData = false;
        while ($offset + 12 <= $length) {
            $lengthBytes = substr($content, $offset, 4);
            $decoded = unpack('Nlength', $lengthBytes);
            $chunkLength = $decoded['length'] ?? null;
            if (!is_int($chunkLength) || $chunkLength > self::MAXIMUM_TILE_BYTES) {
                return false;
            }
            $chunkEnd = $offset + 12 + $chunkLength;
            if ($chunkEnd > $length) {
                return false;
            }
            $type = substr($content, $offset + 4, 4);
            $data = substr($content, $offset + 8, $chunkLength);
            $crc = substr($content, $offset + 8 + $chunkLength, 4);
            if (hash('crc32b', $type . $data, true) !== $crc) {
                return false;
            }
            if ($chunkIndex === 0) {
                if ($type !== 'IHDR' || $chunkLength !== 13) {
                    return false;
                }
                $dimensions = unpack('Nwidth/Nheight', substr($data, 0, 8));
                if (
                    ($dimensions['width'] ?? null)
                        !== $this->configuration['tileSizePixels']
                    || ($dimensions['height'] ?? null)
                        !== $this->configuration['tileSizePixels']
                ) {
                    return false;
                }
            } elseif ($type === 'IHDR') {
                return false;
            }
            if ($type === 'IDAT') {
                $hasImageData = true;
            }
            $offset = $chunkEnd;
            $chunkIndex++;
            if ($type === 'IEND') {
                return $chunkLength === 0
                    && $hasImageData
                    && $offset === $length;
            }
        }

        return false;
    }

    private static function isSafeAbsoluteRoot(mixed $path): bool
    {
        if (
            !is_string($path)
            || $path === ''
            || strlen($path) > 512
            || str_contains($path, "\0")
            || preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#D', $path) !== 1
        ) {
            return false;
        }
        $trimmed = rtrim($path, '/\\');

        return $trimmed !== '' && preg_match('/^[A-Za-z]:$/D', $trimmed) !== 1;
    }

    private static function samePath(string $first, string $second): bool
    {
        $first = str_replace('\\', '/', rtrim($first, '/\\'));
        $second = str_replace('\\', '/', rtrim($second, '/\\'));
        if (DIRECTORY_SEPARATOR === '\\') {
            return strcasecmp($first, $second) === 0;
        }

        return $first === $second;
    }

    private static function isContainedBy(string $root, string $path): bool
    {
        $root = str_replace('\\', '/', rtrim($root, '/\\'));
        $path = str_replace('\\', '/', $path);
        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $path = strtolower($path);
        }

        return str_starts_with($path, $root . '/');
    }
}
