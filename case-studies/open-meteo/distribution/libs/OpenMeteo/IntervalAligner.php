<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class IntervalAligner
{
    public static function containing(
        ForecastSeries $series,
        int $timestamp
    ): ?ForecastPoint {
        foreach ($series->points() as $point) {
            if (
                $point->semantics() !== FieldCatalog::SEMANTICS_INSTANT
                && $point->validFrom() <= $timestamp
                && $timestamp < $point->validTo()
            ) {
                return $point;
            }
        }

        return null;
    }

    /**
     * @return array{from: int, to: int}
     */
    public static function localDayBounds(string $date, string $timezone): array
    {
        $zone = self::timezone($timezone);
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $from === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $from->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException('Local day date is invalid.');
        }

        $to = $from->modify('+1 day');

        return [
            'from' => $from->getTimestamp(),
            'to' => $to->getTimestamp(),
        ];
    }

    /**
     * @return list<array{left: ForecastPoint, right: ForecastPoint}>
     */
    public static function exactIntervals(
        ForecastSeries $left,
        ForecastSeries $right
    ): array {
        $rightByInterval = [];
        foreach ($right->points() as $point) {
            $rightByInterval[$point->intervalKey()] = $point;
        }

        $aligned = [];
        foreach ($left->points() as $point) {
            $other = $rightByInterval[$point->intervalKey()] ?? null;
            if ($other !== null) {
                $aligned[] = [
                    'left' => $point,
                    'right' => $other,
                ];
            }
        }

        return $aligned;
    }

    private static function timezone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new InvalidArgumentException('Time zone is invalid.');
        }
    }
}
