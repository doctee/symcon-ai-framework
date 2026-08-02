<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class ForecastPoint
{
    public function __construct(
        private readonly string $field,
        private readonly string $unit,
        private readonly string $semantics,
        private readonly int $sourceTimestamp,
        private readonly int $validFrom,
        private readonly int $validTo,
        private readonly int|float $value
    ) {
        if ($field === '') {
            throw new InvalidArgumentException('Forecast field must not be empty.');
        }
        if (
            !in_array($semantics, [
            FieldCatalog::SEMANTICS_INSTANT,
            FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
            FieldCatalog::SEMANTICS_LOCAL_DAY,
            ], true)
        ) {
            throw new InvalidArgumentException('Forecast point semantics are invalid.');
        }
        if ($validTo < $validFrom) {
            throw new InvalidArgumentException('Forecast point interval is inverted.');
        }
        if ($semantics !== FieldCatalog::SEMANTICS_INSTANT && $validTo === $validFrom) {
            throw new InvalidArgumentException('Forecast interval must have positive duration.');
        }
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('Forecast value must be finite.');
        }
    }

    public function field(): string
    {
        return $this->field;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function semantics(): string
    {
        return $this->semantics;
    }

    public function sourceTimestamp(): int
    {
        return $this->sourceTimestamp;
    }

    public function validFrom(): int
    {
        return $this->validFrom;
    }

    public function validTo(): int
    {
        return $this->validTo;
    }

    public function value(): int|float
    {
        return $this->value;
    }

    public function durationSeconds(): int
    {
        return $this->validTo - $this->validFrom;
    }

    public function intervalKey(): string
    {
        return sprintf('%d:%d', $this->validFrom, $this->validTo);
    }
}
