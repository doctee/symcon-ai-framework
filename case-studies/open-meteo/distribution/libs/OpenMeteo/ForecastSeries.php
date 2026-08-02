<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class ForecastSeries
{
    /** @var list<ForecastPoint> */
    private array $points;

    /** @param list<ForecastPoint> $points */
    public function __construct(
        private readonly string $field,
        private readonly string $unit,
        array $points
    ) {
        if ($field === '') {
            throw new InvalidArgumentException('Forecast series field must not be empty.');
        }

        $previousSourceTimestamp = null;
        foreach ($points as $point) {
            if ($point->field() !== $field || $point->unit() !== $unit) {
                throw new InvalidArgumentException('Forecast series point contract differs.');
            }
            if (
                $previousSourceTimestamp !== null
                && $point->sourceTimestamp() <= $previousSourceTimestamp
            ) {
                throw new InvalidArgumentException(
                    'Forecast series timestamps must be strictly increasing.'
                );
            }
            $previousSourceTimestamp = $point->sourceTimestamp();
        }

        $this->points = $points;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    /** @return list<ForecastPoint> */
    public function points(): array
    {
        return $this->points;
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function pointAtSourceTimestamp(int $timestamp): ?ForecastPoint
    {
        foreach ($this->points as $point) {
            if ($point->sourceTimestamp() === $timestamp) {
                return $point;
            }
        }

        return null;
    }
}
