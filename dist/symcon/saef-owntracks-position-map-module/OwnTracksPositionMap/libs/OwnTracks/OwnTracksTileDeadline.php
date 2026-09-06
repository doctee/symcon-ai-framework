<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use RuntimeException;

/** Case-study-local monotonic budget; never a cross-request state store. */
final class OwnTracksTileDeadline
{
    private readonly int $expiresAt;

    public function __construct(int $milliseconds = 8000)
    {
        if ($milliseconds < 1 || $milliseconds > 10000) {
            throw new InvalidArgumentException('Tile deadline is invalid.');
        }
        $this->expiresAt = hrtime(true) + $milliseconds * 1000000;
    }

    public function remainingMilliseconds(): int
    {
        $remaining = (int) floor(($this->expiresAt - hrtime(true)) / 1000000);
        if ($remaining < 1) {
            throw new RuntimeException('Tile operation deadline exceeded.');
        }

        return $remaining;
    }

    /** @param resource $handle */
    public function acquireLock($handle): void
    {
        $stop = min($this->expiresAt, hrtime(true) + 250 * 1000000);
        do {
            $this->remainingMilliseconds();
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }
            usleep(1000);
        } while (hrtime(true) < $stop);

        throw new RuntimeException('Tile state lock deadline exceeded.');
    }
}
