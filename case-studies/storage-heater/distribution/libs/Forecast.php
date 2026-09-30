<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Pure, deterministic calculations. No device actions or archive writes. */
final class Forecast
{
    public const FEATURES = ['hdd_night', 'reduced', 'hdd_reduced', 'gallery', 'sofa', 'previous_charge', 'fan_hours', 'outside_previous'];

    public static function cycle(string $date, string $timezone): array
    {
        $tz = new DateTimeZone($timezone);
        $day = new DateTimeImmutable($date . ' 12:00:00', $tz);
        if ($day->format('Y-m-d') !== $date) {
            throw new RuntimeException('Invalid cycle date.');
        }
        return [
            'date' => $date,
            'start' => $day->setTime(22, 0)->getTimestamp(),
            'end' => $day->modify('+1 day')->setTime(7, 0)->getTimestamp(),
            'issueFrom' => $day->setTime(21, 45)->getTimestamp(),
            'cutoff' => $day->setTime(21, 0)->getTimestamp(),
        ];
    }

    public static function number(mixed $value, string $label): float
    {
        if ((!is_float($value) && !is_int($value)) || !is_finite((float) $value)) {
            throw new RuntimeException('Non-finite numeric value: ' . $label);
        }
        return (float) $value;
    }

    public static function hourly(array $rows, int $from, int $to): array
    {
        if ($to <= $from || ($to - $from) % 3600 !== 0 || $to - $from > 48 * 3600) {
            throw new RuntimeException('Invalid bounded hourly window.');
        }
        $indexed = [];
        foreach ($rows as $row) {
            $t = $row['TimeStamp'] ?? null;
            if (!is_int($t) || $t < $from || $t >= $to || isset($indexed[$t])) {
                throw new RuntimeException('Duplicate or out-of-window hour.');
            }
            if (($row['Duration'] ?? null) !== 3600) {
                throw new RuntimeException('Incomplete hourly aggregate.');
            }
            $indexed[$t] = self::number($row['Avg'] ?? null, 'hour');
        }
        for ($t = $from; $t < $to; $t += 3600) {
            if (!array_key_exists($t, $indexed)) {
                throw new RuntimeException('Missing hour: ' . $t);
            }
        }
        ksort($indexed);
        return $indexed;
    }

    /** Integrate a changed-only boolean series, including a carry-in record. */
    public static function duty(array $rows, int $from, int $to): array
    {
        if ($to <= $from || $to - $from > 48 * 3600) {
            throw new RuntimeException('Invalid duty window.');
        }
        usort($rows, static fn(array $a, array $b): int => $a['TimeStamp'] <=> $b['TimeStamp']);
        $state = null;
        $cursor = $from;
        $seconds = 0;
        $changes = 0;
        $last = null;
        foreach ($rows as $row) {
            $t = $row['TimeStamp'] ?? null;
            $v = $row['Value'] ?? null;
            if (!is_int($t) || ($last !== null && $t <= $last) || !in_array($v, [false, true, 0, 1], true)) {
                throw new RuntimeException('Invalid boolean archive record.');
            }
            $last = $t;
            if ($t <= $from) {
                $state = (int) $v;
                continue;
            }
            if ($t >= $to) {
                continue;
            }
            if ($state === null) {
                throw new RuntimeException('Missing boolean carry-in.');
            }
            $seconds += ($t - $cursor) * $state;
            $changes += $state !== (int) $v ? 1 : 0;
            $cursor = $t;
            $state = (int) $v;
        }
        if ($state === null) {
            throw new RuntimeException('Missing boolean carry-in.');
        }
        $seconds += ($to - $cursor) * $state;
        return ['hours' => $seconds / 3600, 'changes' => $changes];
    }

    /** Provider points are instantaneous temperatures; pairwise trapezoidal integration. */
    public static function weather(array $points, int $from, int $to): array
    {
        $indexed = [];
        foreach ($points as $point) {
            $t = $point['validFrom'] ?? null;
            if (!is_int($t) || ($point['validTo'] ?? null) !== $t || ($point['semantics'] ?? '') !== 'instant' || ($point['unit'] ?? '') !== '°C' || isset($indexed[$t])) {
                throw new RuntimeException('Invalid weather point.');
            }
            $value = self::number($point['value'] ?? null, 'weather');
            if ($value < -60 || $value > 60) {
                throw new RuntimeException('Weather temperature outside physical guard.');
            }
            $indexed[$t] = $value;
        }
        $sum = 0.0;
        $used = [];
        for ($t = $from; $t < $to; $t += 3600) {
            if (!isset($indexed[$t], $indexed[$t + 3600])) {
                throw new RuntimeException('Weather does not cover entire charge window.');
            }
            $sum += ($indexed[$t] + $indexed[$t + 3600]) / 2;
            $used[] = ['time' => $t, 'temperature' => $indexed[$t]];
        }
        $used[] = ['time' => $to, 'temperature' => $indexed[$to]];
        return ['mean' => $sum / (($to - $from) / 3600), 'points' => $used];
    }

    public static function validateModel(array $model): void
    {
        if (($model['schema'] ?? null) !== 1 || ($model['features'] ?? null) !== self::FEATURES || ($model['cutoffHour'] ?? null) !== 21 || ($model['window'] ?? null) !== '22-07' || ($model['timezone'] ?? null) !== 'Europe/Berlin') {
            throw new RuntimeException('Unsupported model feature/time contract.');
        }
        if (!is_int($model['trainedThrough'] ?? null) || !is_array($model['ranges'] ?? null)) {
            throw new RuntimeException('Missing model provenance.');
        }
        foreach (['mean', 'scale', 'coefficients', 'ranges'] as $key) {
            if (!is_array($model[$key] ?? null) || count($model[$key]) !== count(self::FEATURES)) {
                throw new RuntimeException('Invalid model vector: ' . $key);
            }
        }
        self::number($model['intercept'] ?? null, 'intercept');
        foreach (self::FEATURES as $i => $name) {
            self::number($model['mean'][$i], $name);
            self::number($model['coefficients'][$i], $name);
            if (self::number($model['scale'][$i], $name) <= 0) {
                throw new RuntimeException('Invalid model scale.');
            }
            if (!is_array($model['ranges'][$i]) || count($model['ranges'][$i]) !== 2 || self::number($model['ranges'][$i][0], $name) > self::number($model['ranges'][$i][1], $name)) {
                throw new RuntimeException('Invalid model range.');
            }
        }
    }

    public static function predict(array $model, array $features, int $issuedAt): array
    {
        self::validateModel($model);
        if ($model['trainedThrough'] >= $issuedAt) {
            throw new RuntimeException('Model contains future training data.');
        }
        $prediction = (float) $model['intercept'];
        $warnings = [];
        foreach (self::FEATURES as $i => $name) {
            $value = self::number($features[$name] ?? null, $name);
            $prediction += (($value - $model['mean'][$i]) / $model['scale'][$i]) * $model['coefficients'][$i];
            if ($value < $model['ranges'][$i][0] || $value > $model['ranges'][$i][1]) {
                $warnings[] = 'outside_training_range:' . $name;
            }
        }
        return ['kwh' => max(0.0, self::number($prediction, 'prediction')), 'warnings' => $warnings];
    }

    public static function decode(string $json): array
    {
        if (strlen($json) > 4 * 1024 * 1024) {
            throw new RuntimeException('JSON exceeds bounded storage contract.');
        }
        $value = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new RuntimeException('Expected JSON object/array.');
        }
        return $value;
    }

    public static function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
