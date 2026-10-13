<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Conditional passive cooling only. Parameters belong to private configuration. */
final class Cooling
{
    public static function model(array $model): void
    {
        if (($model['schema'] ?? null) !== 1 || ($model['timezone'] ?? '') !== 'Europe/Berlin' || ($model['window'] ?? '') !== '23-05' || !is_int($model['trainedThrough'] ?? null) || $model['trainedThrough'] <= 0) {
            throw new RuntimeException('Invalid cooling model provenance/time contract.');
        }
        foreach (['gallery', 'sofa'] as $room) {
            $k = Forecast::number($model['kPerHour'][$room] ?? null, $room);
            if ($k <= 0 || $k > 1) {
                throw new RuntimeException('Cooling coefficient must be positive and at most 1/h.');
            }
        }
    }

    public static function warnings(array $model, array $initial, array $weather, array $points): array
    {
        $warnings = [];
        foreach (['gallery' => 'Galerie', 'sofa' => 'Wohnzimmer', 'outside' => 'Außentemperatur'] as $key => $label) {
            $range = $model['ranges'][$key] ?? null;
            if (!is_array($range) || count($range) !== 2) {
                $warnings[] = $label . ' (Kalibrierbereich nicht hinterlegt)';
                continue;
            }
            $values = $key === 'outside' ? array_column($weather['points'], 'temperature') : array_column($points, $key);
            if (min($values) < Forecast::number($range[0], 'range') || max($values) > Forecast::number($range[1], 'range')) {
                $warnings[] = $label;
            }
        }
        if (isset($model['minimumDeltaK'])) {
            $minimum = Forecast::number($model['minimumDeltaK'], 'minimumDeltaK');
            foreach (['gallery' => 'Galerie', 'sofa' => 'Wohnzimmer'] as $room => $label) {
                if (min(array_column($points, $room)) - max(array_column($weather['points'], 'temperature')) < $minimum) {
                    $warnings[] = $label . ' (geringe Temperaturdifferenz nach außen)';
                }
            }
        }
        return $warnings;
    }

    public static function cycle(int $now): array
    {
        $day = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Berlin'));
        $start = $day->setTime(23, 0)->getTimestamp();
        return ['date' => $day->format('Y-m-d'), 'start' => $start,
            'issueUntil' => $start + 15 * 60,
            'end' => min($start + 6 * 3600, $day->modify('+1 day')->setTime(5, 0)->getTimestamp())];
    }

    public static function predict(array $model, array $initial, array $weather, int $start, int $end): array
    {
        self::model($model);
        if ($start <= $model['trainedThrough'] || $end <= $start || $end - $start > 6 * 3600) {
            throw new RuntimeException('Cooling forecast outside model horizon/provenance.');
        }
        $outside = [];
        foreach ($weather['points'] as $p) {
            $outside[$p['time']] = Forecast::number($p['temperature'], 'weather');
        }
        $temperatures = [];
        foreach (['gallery', 'sofa'] as $room) {
            $temperatures[$room] = Forecast::number($initial[$room]['value'] ?? null, $room);
            if ($temperatures[$room] < -20 || $temperatures[$room] > 60) {
                throw new RuntimeException('Room temperature outside physical guard.');
            }
        }
        $points = [['time' => $start] + $temperatures];
        $cursor = $start;
        while ($cursor < $end) {
            $hour = intdiv($cursor, 3600) * 3600;
            if (!isset($outside[$hour], $outside[$hour + 3600])) {
                throw new RuntimeException('Cooling weather coverage incomplete.');
            }
            $next = min($end, $hour + 3600);
            $dt = ($next - $cursor) / 3600;
            $slope = $outside[$hour + 3600] - $outside[$hour];
            $out = $outside[$hour] + $slope * (($cursor - $hour) / 3600);
            // Exact first-order solution for linearly varying outside temperature.
            foreach (['gallery', 'sofa'] as $room) {
                $k = $model['kPerHour'][$room];
                $weight = -expm1(-$k * $dt);
                $temperatures[$room] += ($out - $temperatures[$room]) * $weight + $slope * ($dt - $weight / $k);
            }
            $points[] = ['time' => $next] + $temperatures;
            $cursor = $next;
        }
        return $points;
    }

    /** No held values or extrapolation. A bracket is evidence of changes, not a heartbeat. */
    public static function measured(array $rows, array $times, int $now, int $maxGap = 6 * 3600): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $t = $row['TimeStamp'] ?? null;
            if (!is_int($t) || $t > $now || isset($indexed[$t])) {
                throw new RuntimeException('Invalid or duplicate temperature archive timestamp.');
            }
            $v = Forecast::number($row['Value'] ?? null, 'temperature archive');
            if ($v < -20 || $v > 60) {
                throw new RuntimeException('Temperature archive outside physical guard.');
            }
            $indexed[$t] = $v;
        }
        ksort($indexed);
        $result = [];
        foreach ($times as $target) {
            $result[$target] = null;
            if ($target > $now) {
                continue;
            }
            if (isset($indexed[$target])) {
                $result[$target] = ['value' => $indexed[$target], 'from' => $target, 'to' => $target];
                continue;
            }
            $before = null;
            foreach ($indexed as $time => $value) {
                if ($time < $target) {
                    $before = $time;
                    continue;
                }
                if ($before !== null && $time - $before <= $maxGap) {
                    $result[$target] = ['value' => $indexed[$before] + ($value - $indexed[$before]) * ($target - $before) / ($time - $before), 'from' => $before, 'to' => $time];
                }
                break;
            }
        }
        return $result;
    }

    /** A zero-battery interval invalidates any temperature bracket touching it. */
    public static function maskBattery(array $actual, array $battery): array
    {
        usort($battery, static fn(array $a, array $b): int => $a['TimeStamp'] <=> $b['TimeStamp']);
        foreach ($actual as $time => $point) {
            if ($point === null) {
                continue;
            }
            $state = null;
            foreach ($battery as $sample) {
                if ($sample['TimeStamp'] > $point['to']) {
                    break;
                }
                $v = Forecast::number($sample['Value'], 'battery');
                if ($sample['TimeStamp'] <= $point['from']) {
                    $state = $v;
                } elseif ($v <= 0) {
                    $actual[$time] = null;
                }
            }
            if ($state === null || $state <= 0) {
                $actual[$time] = null;
            }
        }
        return $actual;
    }

    public static function journal(string $json): array
    {
        $j = Forecast::decode($json);
        if (($j['schema'] ?? null) !== 1 || !is_array($j['records'] ?? null) || count($j['records']) > 120) {
            throw new RuntimeException('Invalid cooling journal; restore verified backup.');
        }
        foreach ($j['records'] as $date => $r) {
            if (($r['date'] ?? null) !== $date || !is_array($r['forecast']['points'] ?? null) || !is_array($r['actual'] ?? null)) {
                throw new RuntimeException('Invalid cooling journal record.');
            }
        }
        return $j;
    }
}
