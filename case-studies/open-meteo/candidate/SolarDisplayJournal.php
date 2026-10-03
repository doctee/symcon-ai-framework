<?php

declare(strict_types=1);

/** Case-study transaction evidence, not a metadata registry or retention engine. */
final class SolarDisplayJournal
{
    public function __construct(private string $directory)
    {
        if (!is_dir($directory) || is_link($directory) || realpath($directory) !== $directory) {
            throw new InvalidArgumentException('Use an existing canonical private journal directory.');
        }
    }

    public function read(string $name): ?string
    {
        $path = $this->path($name);
        if (!file_exists($path)) {
            return null;
        }
        $size = filesize($path);
        if ($size === false || $size > 128 * 1024) {
            throw new RuntimeException('Journal size invalid.');
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || strlen($bytes) !== $size) {
            throw new RuntimeException('Journal read failed.');
        }
        return $bytes;
    }

    /** Caller holds the owner semaphore. Never overwrite evidence. */
    public function put(string $name, string $bytes): void
    {
        if (strlen($bytes) > 128 * 1024) {
            throw new RuntimeException('Journal size exceeded.');
        }
        $existing = $this->read($name);
        if ($existing !== null) {
            if ($existing !== $bytes) {
                throw new RuntimeException('Conflicting journal evidence.');
            }
            return;
        }
        $path = $this->path($name);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Journal temporary file unavailable.');
        }
        try {
            if (!chmod($temporary, 0600) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || !fsync($handle)) {
                throw new RuntimeException('Journal flush failed.');
            }
        } finally {
            fclose($handle);
        }
        if (file_exists($path) || !rename($temporary, $path) || $this->read($name) !== $bytes) {
            throw new RuntimeException('Journal publication/readback failed.');
        }
    }

    /** Only retires the active pointer; immutable intent and receipts remain. */
    public function finish(string $expected): void
    {
        if ($this->read('pending') !== $expected || !unlink($this->path('pending'))) {
            throw new RuntimeException('Pending journal pointer changed.');
        }
    }

    private function path(string $name): string
    {
        if (preg_match('/^(pending|[a-f0-9]{64}\.(json|done|rollback|aggregate-[a-z][a-z0-9_]{0,39}))$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid journal name.');
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RuntimeException('Unsafe journal path.');
        }
        return $path;
    }
}
