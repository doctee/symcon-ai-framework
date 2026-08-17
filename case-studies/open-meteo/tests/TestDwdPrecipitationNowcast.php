<?php

declare(strict_types=1);

class TestDwdPrecipitationNowcast extends DwdPrecipitationNowcast
{
    /** @var list<string|false> */
    private array $responses = [];
    /** @var list<string|null> */
    private array $warnings = [];
    private int $now = 1735718880;

    public function testQueueResponse(string|false $response): void
    {
        $this->responses[] = $response;
    }

    public function testQueueTransportWarning(?string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function testSetNow(int $now): void
    {
        $this->now = $now;
    }

    protected function performUrlRequest(string $url, int $timeoutMilliseconds): string|false
    {
        if (
            $timeoutMilliseconds !== 10000
            || !str_starts_with($url, 'https://maps.dwd.de/geoserver/wms?')
        ) {
            throw new RuntimeException('DWD nowcast transport contract differs.');
        }

        $warning = array_shift($this->warnings);
        if ($warning !== null) {
            trigger_error($warning, E_USER_WARNING);
        }

        return array_shift($this->responses) ?? false;
    }

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}
