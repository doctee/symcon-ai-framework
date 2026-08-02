<?php

declare(strict_types=1);

class TestOpenMeteoSolarForecast extends OpenMeteoSolarForecast
{
    /** @var list<string|false> */
    private array $responses = [];
    /** @var list<string> */
    private array $requestedUrls = [];
    private int $now = 1735716600;

    public function testQueueResponse(string|false $response): void
    {
        $this->responses[] = $response;
    }

    public function testSetNow(int $now): void
    {
        $this->now = $now;
    }

    /** @return list<string> */
    public function testRequestedUrls(): array
    {
        return $this->requestedUrls;
    }

    protected function fetchUrl(string $url, int $timeoutMilliseconds): string|false
    {
        if ($timeoutMilliseconds !== 10000 || !str_starts_with($url, 'https://api.open-meteo.com/')) {
            throw new RuntimeException('Solar transport contract differs.');
        }
        $this->requestedUrls[] = $url;

        return array_shift($this->responses) ?? false;
    }

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}
