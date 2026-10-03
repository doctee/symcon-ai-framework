<?php

declare(strict_types=1);

use SAEF\CaseStudy\OpenMeteo\IntervalAligner;

require_once __DIR__ . '/../distribution/libs/OpenMeteo/IntervalAligner.php';

/**
 * Migration-local, pure candidate. No Symcon calls and no archive writer.
 * Inputs are bounded, coherently read module outputs, not archive aggregates.
 */
final class SolarDisplayPlan
{
    /**
     * @param array<array-key, array<string, mixed>> $sources
     * @return array<string, mixed>
     */
    public static function evaluate(array $sources, int $now, string $timezone, int $maxAgeSeconds): array
    {
        if ($now <= 0 || $maxAgeSeconds < 1 || $maxAgeSeconds > 86400 || !array_is_list($sources) || count($sources) !== 2) {
            throw new InvalidArgumentException('Invalid display evaluation configuration.');
        }
        $date = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
        $bounds = IntervalAligner::localDayBounds($date, $timezone);
        $values = [];
        $identities = [];
        try {
            foreach ($sources as $source) {
                $key = $source['key'] ?? null;
                if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $key) !== 1 || isset($values[$key])) {
                    throw new RuntimeException('source_identity_invalid');
                }
                $id = $source['instanceId'] ?? null;
                if (!is_int($id) || $id <= 0 || $id > 65535 || in_array($id, $identities, true)) {
                    throw new RuntimeException('source_identity_invalid');
                }
                $identities[] = $id;
                $values[$key] = self::source($source, $now, $maxAgeSeconds, $bounds);
            }
            $total = array_sum(array_column($values, 'energyKwh'));
            if (!is_finite($total)) {
                throw new RuntimeException('sum_invalid');
            }
        } catch (RuntimeException $error) {
            // Never substitute zero or publish one valid plant as the whole sum.
            return ['success' => false, 'code' => $error->getMessage(), 'localDay' => $date];
        }
        $series = [];
        foreach ($values as $key => $value) {
            $series[$key] = self::chartRecords($bounds['from'], $value['energyKwh']);
        }
        return [
            'success' => true,
            'code' => 'ready',
            'localDay' => $date,
            'timezone' => $timezone,
            'validFrom' => $bounds['from'],
            'validTo' => $bounds['to'],
            'evaluatedAt' => $now,
            'expiresAt' => min(
                $bounds['to'],
                min(array_column($values, 'lastSuccess')) + $maxAgeSeconds + 1
            ),
            'sources' => $values,
            'sumKwh' => $total,
            // A desired projection only, never permission or executable mutation.
            'chartProjection' => [
                'ownership' => 'new_dedicated_chart_only_variables',
                'replaceFrom' => $bounds['from'],
                'replaceToInclusive' => $bounds['to'] - 1,
                'records' => $series,
                'applyAllowed' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @param array{from: int, to: int} $bounds
     * @return array{energyKwh: float, lastSuccess: int, configurationHash: string, instanceId: int}
     */
    private static function source(array $source, int $now, int $maxAge, array $bounds): array
    {
        $last = $source['lastSuccess'] ?? null;
        $hash = $source['configurationHash'] ?? null;
        if (
            !is_int($last) || $last <= 0 || $last > $now || $now - $last > $maxAge
            || $last < $bounds['from']
        ) {
            throw new RuntimeException('source_stale_or_wrong_day');
        }
        if (($source['status'] ?? null) !== 102 || ($source['dataState'] ?? null) !== 2) {
            throw new RuntimeException('source_not_ready');
        }
        if (
            !is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1
            || ($source['readbackConfigurationHash'] ?? null) !== $hash
            || ($source['expectedConfigurationHash'] ?? null) !== $hash
            || ($source['readbackLastSuccess'] ?? null) !== $last
        ) {
            throw new RuntimeException('source_changed_or_unpinned');
        }
        $power = self::series($source['power'] ?? null);
        $daily = self::series($source['daily'] ?? null);
        $cursor = $bounds['from'];
        $energy = 0.0;
        $previousEnd = null;
        foreach ($power as $point) {
            self::point($point, 'kW', 'preceding_interval');
            $from = $point['validFrom'];
            $to = $point['validTo'];
            if ($point['sourceTimestamp'] !== $to || ($previousEnd !== null && $from < $previousEnd)) {
                throw new RuntimeException('power_overlap_or_order_invalid');
            }
            $previousEnd = $to;
            if ($to <= $bounds['from'] || $from >= $bounds['to']) {
                continue;
            }
            if ($from !== $cursor || $to > $bounds['to']) {
                throw new RuntimeException('day_coverage_incomplete');
            }
            $energy += (float) $point['value'] * (($to - $from) / 3600);
            $cursor = $to;
        }
        if ($cursor !== $bounds['to']) {
            throw new RuntimeException('day_coverage_incomplete');
        }
        $dayValue = null;
        foreach ($daily as $point) {
            self::point($point, 'kWh', 'local_day');
            if ($point['validFrom'] !== $bounds['from']) {
                continue;
            }
            if ($dayValue !== null || $point['validTo'] !== $bounds['to'] || $point['sourceTimestamp'] !== $bounds['from']) {
                throw new RuntimeException('daily_identity_invalid');
            }
            $dayValue = self::number($point['value']);
        }
        $published = self::number($source['todayValue'] ?? null);
        if (
            $dayValue === null || !is_finite($energy)
            || abs($energy - $dayValue) > 0.000001
            || abs($published - $dayValue) > 0.000001
        ) {
            throw new RuntimeException('daily_value_or_publication_inconsistent');
        }
        $instanceId = $source['instanceId'];
        if (!is_int($instanceId)) {
            throw new RuntimeException('source_identity_invalid');
        }
        return ['energyKwh' => $dayValue, 'lastSuccess' => $last, 'configurationHash' => $hash, 'instanceId' => $instanceId];
    }

    /** @return list<array<string, mixed>> */
    private static function series(mixed $response): array
    {
        if (
            !is_array($response) || ($response['success'] ?? null) !== true
            || ($response['breakdown'] ?? null) !== 'system'
            || !is_array($response['data'] ?? null)
        ) {
            throw new RuntimeException('forecast_response_invalid');
        }
        $points = $response['data']['system'] ?? null;
        if (!is_array($points) || !array_is_list($points) || count($points) < 1 || count($points) > 400) {
            throw new RuntimeException('forecast_series_invalid');
        }
        foreach ($points as $point) {
            if (!is_array($point)) {
                throw new RuntimeException('forecast_point_invalid');
            }
        }
        return $points;
    }

    /**
     * @param array<string, mixed> $point
     * @phpstan-assert array{validFrom: int, validTo: int, sourceTimestamp: int, value: int|float} $point
     */
    private static function point(array $point, string $unit, string $semantics): void
    {
        if (
            ($point['unit'] ?? null) !== $unit || ($point['semantics'] ?? null) !== $semantics
            || !is_int($point['validFrom'] ?? null) || !is_int($point['validTo'] ?? null)
            || !is_int($point['sourceTimestamp'] ?? null)
            || $point['validFrom'] <= 0 || $point['validTo'] <= $point['validFrom']
        ) {
            throw new RuntimeException('forecast_point_invalid');
        }
        self::number($point['value'] ?? null);
    }

    private static function number(mixed $value): float
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0) {
            throw new RuntimeException('forecast_value_invalid');
        }
        return (float) $value;
    }

    /** @return list<array{TimeStamp: int, Value: float}> */
    private static function chartRecords(int $dayStart, float $value): array
    {
        return [
            ['TimeStamp' => $dayStart, 'Value' => 0.0],
            ['TimeStamp' => $dayStart + 1, 'Value' => $value],
        ];
    }
}
