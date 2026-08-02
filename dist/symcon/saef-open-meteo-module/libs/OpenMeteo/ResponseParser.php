<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use UnexpectedValueException;

final class ResponseParser
{
    /**
     * @param list<string> $currentFields
     * @param list<string> $hourlyFields
     * @param list<string> $dailyFields
     */
    public static function parse(
        string $json,
        array $currentFields,
        array $hourlyFields,
        array $dailyFields
    ): ParsedForecast {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnexpectedValueException('Open-Meteo response is not valid JSON.');
        }
        if (!is_array($payload)) {
            throw new UnexpectedValueException('Open-Meteo response must be an object.');
        }
        if (($payload['error'] ?? false) === true) {
            throw new UnexpectedValueException('Open-Meteo returned an error response.');
        }

        $latitude = self::finiteFloat($payload['latitude'] ?? null, 'latitude');
        $longitude = self::finiteFloat($payload['longitude'] ?? null, 'longitude');
        $timezone = $payload['timezone'] ?? null;
        $utcOffsetSeconds = $payload['utc_offset_seconds'] ?? null;
        if (!is_string($timezone) || $timezone === '') {
            throw new UnexpectedValueException('Open-Meteo response timezone is invalid.');
        }
        self::timezone($timezone);
        if (!is_int($utcOffsetSeconds)) {
            throw new UnexpectedValueException('Open-Meteo UTC offset is invalid.');
        }

        $current = self::parseCurrent($payload, $currentFields);
        $hourly = self::parseParallelSection($payload, 'hourly', $hourlyFields, $timezone, $utcOffsetSeconds);
        $daily = self::parseParallelSection($payload, 'daily', $dailyFields, $timezone, $utcOffsetSeconds);

        return new ParsedForecast(
            $latitude,
            $longitude,
            $timezone,
            $utcOffsetSeconds,
            $current,
            $hourly,
            $daily
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $fields
     *
     * @return array<string, ForecastSeries>
     */
    private static function parseCurrent(array $payload, array $fields): array
    {
        if ($fields === []) {
            return [];
        }
        $section = self::requireArray($payload, 'current');
        $units = self::requireArray($payload, 'current_units');
        $timestamp = $section['time'] ?? null;
        $interval = $section['interval'] ?? null;
        if (!is_int($timestamp) || !is_int($interval) || $interval <= 0) {
            throw new UnexpectedValueException('Open-Meteo current timing is invalid.');
        }

        $result = [];
        foreach ($fields as $field) {
            $unit = self::unit($units, $field);
            $value = self::numericValue($section[$field] ?? null, $field);
            $semantics = FieldCatalog::semantics('current', $field);
            $validFrom = $semantics === FieldCatalog::SEMANTICS_INSTANT
                ? $timestamp
                : $timestamp - $interval;
            $point = new ForecastPoint(
                $field,
                $unit,
                $semantics,
                $timestamp,
                $validFrom,
                $timestamp,
                $value
            );
            $result[$field] = new ForecastSeries($field, $unit, [$point]);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $fields
     *
     * @return array<string, ForecastSeries>
     */
    private static function parseParallelSection(
        array $payload,
        string $sectionName,
        array $fields,
        string $timezone,
        int $utcOffsetSeconds
    ): array {
        if ($fields === []) {
            return [];
        }
        $section = self::requireArray($payload, $sectionName);
        $units = self::requireArray($payload, $sectionName . '_units');
        $rawTimes = $section['time'] ?? null;
        if (!is_array($rawTimes) || $rawTimes === []) {
            throw new UnexpectedValueException(
                sprintf('Open-Meteo %s timestamps are missing.', $sectionName)
            );
        }
        $times = [];
        foreach ($rawTimes as $timestamp) {
            if (!is_int($timestamp)) {
                throw new UnexpectedValueException(
                    sprintf('Open-Meteo %s timestamp is invalid.', $sectionName)
                );
            }
            $times[] = $timestamp;
        }
        self::assertStrictlyIncreasing($times, $sectionName);

        $result = [];
        foreach ($fields as $field) {
            $values = $section[$field] ?? null;
            if (!is_array($values) || count($values) !== count($times)) {
                throw new UnexpectedValueException(
                    sprintf('Open-Meteo field "%s" length differs from time.', $field)
                );
            }
            $unit = self::unit($units, $field);
            $points = [];
            foreach ($times as $index => $timestamp) {
                $rawValue = $values[$index] ?? null;
                if ($rawValue === null && FieldCatalog::permitsNullGap($sectionName, $field)) {
                    continue;
                }
                $value = self::numericValue($rawValue, $field);
                $semantics = FieldCatalog::semantics($sectionName, $field);
                [$validFrom, $validTo] = self::bounds(
                    $sectionName,
                    $semantics,
                    $timestamp,
                    $timezone,
                    $utcOffsetSeconds
                );
                $points[] = new ForecastPoint(
                    $field,
                    $unit,
                    $semantics,
                    $timestamp,
                    $validFrom,
                    $validTo,
                    $value
                );
            }
            if ($points === []) {
                throw new UnexpectedValueException(
                    sprintf('Open-Meteo field "%s" contains no usable values.', $field)
                );
            }
            $result[$field] = new ForecastSeries($field, $unit, $points);
        }

        return $result;
    }

    /**
     * @return array{int, int}
     */
    private static function bounds(
        string $section,
        string $semantics,
        int $timestamp,
        string $timezone,
        int $utcOffsetSeconds
    ): array {
        if ($semantics === FieldCatalog::SEMANTICS_INSTANT) {
            return [$timestamp, $timestamp];
        }
        if ($semantics === FieldCatalog::SEMANTICS_PRECEDING_INTERVAL) {
            return [$timestamp - 3600, $timestamp];
        }
        if ($section !== 'daily') {
            throw new UnexpectedValueException('Local-day semantics used outside daily data.');
        }

        $localDate = gmdate('Y-m-d', $timestamp + $utcOffsetSeconds);
        $bounds = IntervalAligner::localDayBounds($localDate, $timezone);

        return [$bounds['from'], $bounds['to']];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function requireArray(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value)) {
            throw new UnexpectedValueException(
                sprintf('Open-Meteo section "%s" is missing.', $key)
            );
        }

        return $value;
    }

    /** @param array<string, mixed> $units */
    private static function unit(array $units, string $field): string
    {
        $unit = $units[$field] ?? null;
        if (!is_string($unit)) {
            throw new UnexpectedValueException(
                sprintf('Open-Meteo unit for field "%s" is missing.', $field)
            );
        }
        try {
            FieldCatalog::assertUnit($field, $unit);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedValueException($exception->getMessage());
        }

        return $unit;
    }

    private static function numericValue(mixed $value, string $field): int|float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new UnexpectedValueException(
                sprintf('Open-Meteo field "%s" contains a non-numeric value.', $field)
            );
        }
        if (is_float($value) && !is_finite($value)) {
            throw new UnexpectedValueException(
                sprintf('Open-Meteo field "%s" contains a non-finite value.', $field)
            );
        }
        if ($field === 'global_tilted_irradiance' && $value < 0) {
            throw new UnexpectedValueException(
                'Open-Meteo tilted irradiance must not be negative.'
            );
        }

        return $value;
    }

    private static function finiteFloat(mixed $value, string $field): float
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new UnexpectedValueException(
                sprintf('Open-Meteo %s is invalid.', $field)
            );
        }

        return (float) $value;
    }

    /** @param list<int> $timestamps */
    private static function assertStrictlyIncreasing(array $timestamps, string $section): void
    {
        $previous = null;
        foreach ($timestamps as $timestamp) {
            if ($previous !== null && $timestamp <= $previous) {
                throw new UnexpectedValueException(
                    sprintf('Open-Meteo %s timestamps are not strictly increasing.', $section)
                );
            }
            $previous = $timestamp;
        }
    }

    private static function timezone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new UnexpectedValueException('Open-Meteo response timezone is invalid.');
        }
    }
}
