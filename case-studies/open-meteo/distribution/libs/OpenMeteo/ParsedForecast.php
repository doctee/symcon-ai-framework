<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class ParsedForecast
{
    /**
     * @param array<string, ForecastSeries> $current
     * @param array<string, ForecastSeries> $hourly
     * @param array<string, ForecastSeries> $daily
     */
    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly string $timezone,
        private readonly int $utcOffsetSeconds,
        private readonly array $current,
        private readonly array $hourly,
        private readonly array $daily
    ) {
        if ($timezone === '') {
            throw new InvalidArgumentException('Parsed forecast timezone is missing.');
        }
    }

    public function latitude(): float
    {
        return $this->latitude;
    }

    public function longitude(): float
    {
        return $this->longitude;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function utcOffsetSeconds(): int
    {
        return $this->utcOffsetSeconds;
    }

    public function current(string $field): ForecastSeries
    {
        return $this->requireSeries($this->current, $field, 'current');
    }

    public function hourly(string $field): ForecastSeries
    {
        return $this->requireSeries($this->hourly, $field, 'hourly');
    }

    public function daily(string $field): ForecastSeries
    {
        return $this->requireSeries($this->daily, $field, 'daily');
    }

    /**
     * @param array<string, ForecastSeries> $series
     */
    private function requireSeries(array $series, string $field, string $section): ForecastSeries
    {
        if (!isset($series[$field])) {
            throw new InvalidArgumentException(
                sprintf('Parsed %s field "%s" does not exist.', $section, $field)
            );
        }

        return $series[$field];
    }
}
