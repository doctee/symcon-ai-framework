<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;
use JsonException;

final class LocalHorizonProfile
{
    private const MINIMUM_POINT_COUNT = 8;
    private const MAXIMUM_POINT_COUNT = 720;

    /** @var list<float> */
    private array $elevationDegrees;

    /** @param list<int|float> $elevationDegrees */
    public function __construct(array $elevationDegrees)
    {
        $count = count($elevationDegrees);
        if ($count < self::MINIMUM_POINT_COUNT || $count > self::MAXIMUM_POINT_COUNT) {
            throw new InvalidArgumentException('Local horizon point count is outside the supported range.');
        }

        $normalized = [];
        foreach ($elevationDegrees as $elevation) {
            $value = (float) $elevation;
            if (!is_finite($value) || $value < 0.0 || $value > 90.0) {
                throw new InvalidArgumentException('Local horizon elevation is outside the supported range.');
            }
            $normalized[] = $value;
        }
        $this->elevationDegrees = $normalized;
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Local horizon profile is not valid JSON.');
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new InvalidArgumentException('Local horizon profile must be a JSON list.');
        }

        $values = [];
        foreach ($decoded as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Local horizon profile contains a non-numeric value.');
            }
            $values[] = $value;
        }

        return new self($values);
    }

    /** @return list<float> */
    public function values(): array
    {
        return $this->elevationDegrees;
    }

    public function elevationAt(float $azimuthDegreesNorthClockwise): float
    {
        if (!is_finite($azimuthDegreesNorthClockwise)) {
            throw new InvalidArgumentException('Local horizon azimuth is invalid.');
        }

        $azimuth = fmod($azimuthDegreesNorthClockwise, 360.0);
        if ($azimuth < 0.0) {
            $azimuth += 360.0;
        }

        $count = count($this->elevationDegrees);
        $position = $azimuth * $count / 360.0;
        $lowerIndex = (int) floor($position) % $count;
        $upperIndex = ($lowerIndex + 1) % $count;
        $fraction = $position - floor($position);

        return $this->elevationDegrees[$lowerIndex]
            + ($this->elevationDegrees[$upperIndex] - $this->elevationDegrees[$lowerIndex])
            * $fraction;
    }
}
