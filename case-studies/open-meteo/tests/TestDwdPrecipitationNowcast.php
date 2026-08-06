<?php

declare(strict_types=1);

class TestDwdPrecipitationNowcast extends DwdPrecipitationNowcast
{
    /** @var list<string|false> */
    private array $responses = [];
    private int $now = 1735718880;

    public function testQueueResponse(string|false $response): void
    {
        $this->responses[] = $response;
    }

    public function testSetNow(int $now): void
    {
        $this->now = $now;
    }

    protected function fetchUrl(string $url, int $timeoutMilliseconds): string|false
    {
        if (
            $timeoutMilliseconds !== 10000
            || !str_starts_with($url, 'https://maps.dwd.de/geoserver/wms?')
        ) {
            throw new RuntimeException('DWD nowcast transport contract differs.');
        }

        return array_shift($this->responses) ?? false;
    }

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}
