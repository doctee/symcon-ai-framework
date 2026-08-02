<?php

declare(strict_types=1);

class TestOpenMeteoWeather extends OpenMeteoWeather
{
    /** @var list<string|false> */
    private array $responses = [];
    private int $now = 1735718400;

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
        if ($timeoutMilliseconds !== 10000 || !str_starts_with($url, 'https://api.open-meteo.com/')) {
            throw new RuntimeException('Weather transport contract differs.');
        }

        return array_shift($this->responses) ?? false;
    }

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}
