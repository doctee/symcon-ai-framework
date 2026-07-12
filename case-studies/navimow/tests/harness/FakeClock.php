<?php

declare(strict_types=1);

final class NavimowTestFakeClock
{
    public function __construct(private int $timestamp)
    {
        if ($timestamp <= 0) {
            throw new InvalidArgumentException(
                'Fake clock requires a positive timestamp.'
            );
        }
    }

    public function now(): int
    {
        return $this->timestamp;
    }

    public function advance(int $seconds): void
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException(
                'Fake clock cannot move backwards.'
            );
        }

        $this->timestamp += $seconds;
    }

    public function set(int $timestamp): void
    {
        if ($timestamp < $this->timestamp) {
            throw new InvalidArgumentException(
                'Fake clock cannot move backwards.'
            );
        }

        $this->timestamp = $timestamp;
    }
}
