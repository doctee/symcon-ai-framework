<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\DwdNowcast;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final class ResponseParser
{
    private const VALUE_PROPERTY = 'RV_ANALYSIS';
    private const MAXIMUM_FEATURE_COUNT = 64;
    private const NEGATIVE_ZERO_LIMIT = -0.01;
    private const MAXIMUM_INTENSITY_MM_PER_HOUR = 1000.0;

    /**
     * @return array{
     *     productTime: int,
     *     validFrom: int,
     *     validTo: int,
     *     resolutionMinutes: int,
     *     points: list<array{
     *         validAt: int,
     *         leadMinutes: int,
     *         intensityMmPerHour: float,
     *         accumulationMm: float
     *     }>
     * }
     */
    public static function parse(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('DWD response is not valid JSON.', 0, $exception);
        }
        $features = is_array($decoded) ? ($decoded['features'] ?? null) : null;
        if (!is_array($features) || $features === [] || count($features) > self::MAXIMUM_FEATURE_COUNT) {
            throw new InvalidArgumentException('DWD response feature collection is invalid.');
        }

        $candidates = [];
        $latestReference = null;
        foreach ($features as $feature) {
            $properties = is_array($feature) ? ($feature['properties'] ?? null) : null;
            if (!is_array($properties)) {
                throw new InvalidArgumentException('DWD response feature properties are invalid.');
            }
            $validAt = self::timestamp($properties['TIME'] ?? null, 'TIME');
            $reference = self::timestamp($properties['REFERENCE_TIME'] ?? null, 'REFERENCE_TIME');
            $intensity = self::intensity($properties[self::VALUE_PROPERTY] ?? null);
            $latestReference = $latestReference === null ? $reference : max($latestReference, $reference);
            $candidates[] = [
                'validAt' => $validAt,
                'reference' => $reference,
                'intensity' => $intensity,
            ];
        }
        $byLead = [];
        foreach ($candidates as $candidate) {
            if ($candidate['reference'] !== $latestReference) {
                continue;
            }
            $leadSeconds = $candidate['validAt'] - $latestReference;
            if ($leadSeconds % 60 !== 0) {
                throw new InvalidArgumentException('DWD forecast lead is not minute-aligned.');
            }
            $leadMinutes = intdiv($leadSeconds, 60);
            if (
                $leadMinutes < RequestBuilder::NATIVE_RESOLUTION_MINUTES
                || $leadMinutes > RequestBuilder::MAXIMUM_HORIZON_MINUTES
                || $leadMinutes % RequestBuilder::NATIVE_RESOLUTION_MINUTES !== 0
            ) {
                continue;
            }
            if (isset($byLead[$leadMinutes])) {
                throw new InvalidArgumentException('DWD response contains duplicate forecast leads.');
            }
            $byLead[$leadMinutes] = [
                'validAt' => $candidate['validAt'],
                'leadMinutes' => $leadMinutes,
                'intensityMmPerHour' => $candidate['intensity'],
                'accumulationMm' => round(
                    $candidate['intensity'] * RequestBuilder::NATIVE_RESOLUTION_MINUTES / 60,
                    4
                ),
            ];
        }
        ksort($byLead, SORT_NUMERIC);
        $expectedPointCount = intdiv(
            RequestBuilder::MAXIMUM_HORIZON_MINUTES,
            RequestBuilder::NATIVE_RESOLUTION_MINUTES
        );
        if (count($byLead) !== $expectedPointCount) {
            throw new InvalidArgumentException('DWD response does not contain a complete forecast horizon.');
        }
        $points = array_values($byLead);
        $lastPointIndex = $expectedPointCount - 1;
        if (!isset($points[$lastPointIndex])) {
            throw new InvalidArgumentException('DWD response has no final forecast point.');
        }

        return [
            'productTime' => $latestReference,
            'validFrom' => $points[0]['validAt'],
            'validTo' => $points[$lastPointIndex]['validAt'],
            'resolutionMinutes' => RequestBuilder::NATIVE_RESOLUTION_MINUTES,
            'points' => $points,
        ];
    }

    private static function timestamp(mixed $value, string $field): int
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('DWD response field ' . $field . ' is invalid.');
        }
        try {
            return (new DateTimeImmutable($value))->getTimestamp();
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                'DWD response field ' . $field . ' is not a timestamp.',
                0,
                $exception
            );
        }
    }

    private static function intensity(mixed $value): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('DWD precipitation intensity is invalid.');
        }
        $intensity = (float) $value;
        if (!is_finite($intensity) || $intensity > self::MAXIMUM_INTENSITY_MM_PER_HOUR) {
            throw new InvalidArgumentException('DWD precipitation intensity is outside its bounds.');
        }
        if ($intensity < 0.0) {
            if ($intensity < self::NEGATIVE_ZERO_LIMIT) {
                throw new InvalidArgumentException('DWD precipitation intensity is negative.');
            }

            return 0.0;
        }

        return round($intensity, 3);
    }
}
