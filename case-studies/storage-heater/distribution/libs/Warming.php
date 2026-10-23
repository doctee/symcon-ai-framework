<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Prospective, experimental two-room response; never a physical SOC estimate. */
final class Warming
{
    public static function model(array $m): void
    {
        if (
            ($m['schema'] ?? null) !== 1 || !is_int($m['trainedThrough'] ?? null)
            || ($m['timezone'] ?? '') !== 'Europe/Berlin' || $m['trainedThrough'] <= 0
        ) {
            throw new RuntimeException('Invalid warming model schema.');
        }
        foreach (['gallery', 'sofa'] as $room) {
            $p = $m['rooms'][$room] ?? [];
            foreach (
                ['tau_hours' => [1, 200], 'fan_decay_per_hour' => [0, 1], 'k_per_hour' => [.0001, 1],
                'mass_coupling_per_hour' => [.001, 2], 'mass_time_hours' => [1, 200]] as $key => [$lo, $hi]
            ) {
                $v = Forecast::number($p[$key] ?? null, $key);
                if ($v < $lo || $v > $hi) {
                    throw new RuntimeException('Warming coefficient outside guard: ' . $key);
                }
            }
            if (!is_array($p['coefficients'] ?? null) || count($p['coefficients']) !== 4 || !array_is_list($p['coefficients'])) {
                throw new RuntimeException('Four warming coefficients required.');
            }
            foreach ($p['coefficients'] as $v) {
                if (Forecast::number($v, 'coefficient') < 0 || $v > 5) {
                    throw new RuntimeException('Invalid warming coefficient.');
                }
            }
        }
    }

    public static function journal(string $json): array
    {
        $j = Forecast::decode($json);
        if (
            ($j['schema'] ?? null) !== 1 || !is_array($j['records'] ?? null) || !array_is_list($j['records'])
            || !is_int($j['discarded'] ?? null) || count($j['records']) > 48
        ) {
            throw new RuntimeException('Invalid warming journal; restore verified backup.');
        }
        foreach ($j['records'] as $r) {
            if (
                !is_string($r['key'] ?? null) || !is_string($r['forecastHash'] ?? null)
                || !is_array($r['bindings'] ?? null) || !is_int($r['historyStart'] ?? null)
                || !is_int($r['forecast']['issuedAt'] ?? null)
                || !is_int($r['forecast']['start'] ?? null) || !is_int($r['forecast']['end'] ?? null)
                || !is_array($r['forecast']['points'] ?? null) || !is_array($r['actual'] ?? null)
                || count($r['forecast']['points']) < 2 || count($r['forecast']['points']) > 50
                || $r['forecast']['end'] <= $r['forecast']['start']
                || $r['forecast']['end'] - $r['forecast']['start'] > 48 * 3600
            ) {
                throw new RuntimeException('Invalid saved warming record.');
            }
            if (SAEF_CreateConfigurationHash($r['forecast']) !== $r['forecastHash']) {
                throw new RuntimeException('Saved warming forecast hash mismatch.');
            }
            foreach ($r['actual'] as $room => $actual) {
                if (!in_array($room, ['gallery', 'sofa'], true) || !is_array($actual)) {
                    throw new RuntimeException('Invalid saved warming measurement.');
                }
                foreach ($actual as $point) {
                    Forecast::number($point['value'] ?? null, 'saved measurement');
                    if (!is_int($point['from'] ?? null) || !is_int($point['to'] ?? null) || $point['from'] > $point['to']) {
                        throw new RuntimeException('Invalid saved measurement bracket.');
                    }
                }
            }
            $previous = 0;
            foreach ($r['forecast']['points'] as $p) {
                if (!is_int($p['time'] ?? null) || $p['time'] <= $previous) {
                    throw new RuntimeException('Invalid saved warming time grid.');
                }
                foreach (['gallery', 'sofa'] as $room) {
                    Forecast::number($p[$room] ?? null, $room);
                }
                $previous = $p['time'];
            }
        }
        return $j;
    }

    public static function retain(array $j): array
    {
        while (count($j['records']) > 48 || strlen(Forecast::encode($j)) > 3 * 1024 * 1024) {
            $removed = false;
            foreach ($j['records'] as $i => $r) {
                if (($r['settledAt'] ?? null) !== null) {
                    array_splice($j['records'], $i, 1);
                    ++$j['discarded'];
                    $removed = true;
                    break;
                }
            }
            if (!$removed) {
                throw new RuntimeException('Warming journal full of pending evidence; no forecast saved.');
            }
        }
        return $j;
    }

    public static function stateAt(array $rows, int $t): float
    {
        $value = null;
        foreach ($rows as $r) {
            if ($r['TimeStamp'] <= $t) {
                $value = $r['Value'];
            } else {
                break;
            }
        }
        if ($value === null) {
            throw new RuntimeException('Missing archive carry-in.');
        }
        return is_bool($value) ? (float) $value : Forecast::number($value, 'archive state');
    }

    public static function storeStep(float $s, float $power, float $fan, float $hours, array $p): float
    {
        $rate = 1 / $p['tau_hours'] + $p['fan_decay_per_hour'] * $fan;
        $weight = -expm1(-$rate * $hours);
        return $s * (1 - $weight) + $power * $weight / $rate;
    }

    /** Exact air/mass solution for a constant drive over one integration interval. */
    public static function roomStep(float $air, float $mass, float $outside, float $s, float $fan, float $solar, float $floor, float $hours, array $p): array
    {
        $k = $p['k_per_hour'];
        $km = $p['mass_coupling_per_hour'];
        $tm = $p['mass_time_hours'];
        $a = -$k - $km;
        $d = -1 / $tm;
        $trace = ($a + $d) / 2;
        $delta = sqrt(($a - $d) ** 2 / 4 + $km / $tm);
        $factor = exp($trace * $hours);
        $c = cosh($delta * $hours);
        $h = sinh($delta * $hours) / $delta;
        $m00 = $factor * ($c + $h * ($a - $trace));
        $m01 = $factor * $h * $km;
        $m10 = $factor * $h / $tm;
        $m11 = $factor * ($c + $h * ($d - $trace));
        $b = $p['coefficients'];
        $drive = $k * $outside + ($b[0] + $b[1] * $fan) * $s + $b[2] * $solar / 100 + $b[3] * $floor;
        $det = $k / $tm;
        $h0 = ($d * ($m00 - 1) - $km * $m10) / $det;
        $h1 = (-($m00 - 1) / $tm + $a * $m10) / $det;
        return [$m00 * $air + $m01 * $mass + $h0 * $drive,
            $m10 * $air + $m11 * $mass + $h1 * $drive];
    }

    public static function segment(array $projection, int $t): array
    {
        foreach ($projection['segments'] as $s) {
            if ($s['from'] <= $t && $s['to'] > $t) {
                return $s;
            }
        }
        throw new RuntimeException('Missing preselection projection segment.');
    }

    public static function chargeCycle(int $t): array
    {
        $day = (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Berlin'));
        if ((int) $day->format('G') < 7) {
            $day = $day->modify('-1 day');
        }
        $c = Forecast::cycle($day->format('Y-m-d'), 'Europe/Berlin');
        $c['start'] = $day->setTime(23, 0)->getTimestamp();
        return $c;
    }

    public static function predict(array $f, ?array $chargeSchedule = null): array
    {
        self::model($f['model']);
        $start = $f['start'];
        $end = $f['end'];
        if ($end <= $start || $end - $start > 48 * 3600 || $f['model']['trainedThrough'] >= $start) {
            throw new RuntimeException('Invalid warming horizon or model knowledge time.');
        }
        $air = $mass = array_map(static fn(array $v): float => Forecast::number($v['value'], 'initial'), $f['initial']);
        $store = $f['store'];
        $outside = array_column($f['weather']['points'], 'temperature', 'time');
        $fan = $f['fanInitially'];
        $fanHours = $charge = 0.0;
        $points = [['time' => $start, 'gallery' => $air['gallery'], 'sofa' => $air['sofa'], 'fanHours' => 0.0, 'chargeKwh' => 0.0]];
        // Storage charging reduction is independent of the thermostat/window setback.
        $setback = $f['window']['setback'] ?? $f['plan']['inputs']['values']['setback'];
        $target = $f['projection']['setpoint'] - ($setback ? $f['controller']['SetBack'] : 0);
        for ($t = $start; $t < $end; $t = $next) {
            $segment = self::segment($f['projection'], $t);
            $next = min($end, (intdiv($t, 900) + 1) * 900, $segment['to']);
            $hours = ($next - $t) / 3600;
            $hour = intdiv($t, 3600) * 3600;
            if (!isset($outside[$hour], $outside[$hour + 3600], $f['solar'][$hour])) {
                throw new RuntimeException('Incomplete prospective weather.');
            }
            $out = $outside[$hour] + ($outside[$hour + 3600] - $outside[$hour]) * (($t + $next) / 2 - $hour) / 3600;
            if (!$segment['fanPermitted']) {
                $fan = false;
            } elseif ($air['gallery'] < $target - $f['controller']['Hysteresis'] / 2) {
                $fan = true;
            } elseif ($air['gallery'] > $target + $f['controller']['Hysteresis'] / 2) {
                $fan = false;
            }
            $cycle = self::chargeCycle($t);
            $power = 0.0;
            if ($segment['enabled'] && $t >= $cycle['start'] && $t < $cycle['end']) {
                $night = $f['chargeNights'][$cycle['date']] ?? null;
                if ($night === null) {
                    throw new RuntimeException('Missing prospective charge assumption.');
                }
                $key = $segment['reduced'] ? 'reduced' : 'normal';
                $power = min($f['maxPowerKw'], $night[$key]['kwh'] / (($cycle['end'] - $cycle['start']) / 3600));
            }
            if ($chargeSchedule !== null) {
                $power = ChargeProfile::energy($chargeSchedule, $t, $next) / $hours;
            }
            foreach (['gallery', 'sofa'] as $room) {
                $p = $f['model']['rooms'][$room];
                [$air[$room], $mass[$room]] = self::roomStep($air[$room], $mass[$room], $out, $store[$room], (float) $fan, $f['solar'][$hour], $f['floorDuty'], $hours, $p);
                $store[$room] = self::storeStep($store[$room], $power, (float) $fan, $hours, $p);
                if (!is_finite($air[$room]) || $air[$room] < -20 || $air[$room] > 60) {
                    throw new RuntimeException('Warming result outside physical guard.');
                }
            }
            $fanHours += $fan ? $hours : 0;
            $charge += $power * $hours;
            if ($next % 3600 === 0 || $next === $end) {
                $points[] = ['time' => $next, 'gallery' => $air['gallery'], 'sofa' => $air['sofa'], 'fanHours' => $fanHours, 'chargeKwh' => $charge];
            }
        }
        return $points;
    }

    /** Actual contact exposure, separate from the immutable held-state assumption. */
    public static function windowOutcome(array $raw, array $contacts, int $from, int $until, int $assumed): array
    {
        if ($until <= $from || $until - $from > 48 * 3600 || count($contacts) > 16) {
            throw new RuntimeException('Window comparison outside bounded horizon.');
        }
        $times = [$from, $until];
        $changes = [];
        $states = [];
        foreach (array_merge([$raw], array_column($contacts, 'rows')) as $channel => $rows) {
            $states[$channel] = self::stateAt($rows, $from);
            foreach ($rows as $p) {
                if ($p['TimeStamp'] > $from && $p['TimeStamp'] < $until) {
                    $times[] = $p['TimeStamp'];
                    $changes[$p['TimeStamp']][$channel] = $p['Value'];
                }
            }
        }
        sort($times);
        $times = array_values(array_unique($times));
        $r = ['closedHours' => 0.0, 'tiltedHours' => 0.0, 'openHours' => 0.0,
            'setbackHours' => 0.0, 'differentFromAssumptionHours' => 0.0];
        for ($i = 0; $i < count($times) - 1; ++$i) {
            $t = $times[$i];
            foreach ($changes[$t] ?? [] as $channel => $value) {
                $states[$channel] = is_bool($value) ? (float) $value : Forecast::number($value, 'contact state');
            }
            $value = $states[0];
            if (!in_array($value, [0.0, 1.0, 2.0], true)) {
                throw new RuntimeException('Invalid archived window state.');
            }
            $hours = ($times[$i + 1] - $t) / 3600;
            $r[['closedHours', 'tiltedHours', 'openHours'][(int) $value]] += $hours;
            $r['differentFromAssumptionHours'] += (int) $value !== $assumed ? $hours : 0;
            $active = false;
            foreach ($contacts as $channel => $c) {
                $v = $states[$channel + 1];
                if (!in_array($v, [0.0, 1.0], true)) {
                    throw new RuntimeException('Invalid archived setback contact.');
                }
                $active = $active || ((bool) $v !== $c['Invert']);
            }
            $r['setbackHours'] += $active ? $hours : 0;
        }
        return $r;
    }

    /** Conservative zero-battery intervals include six hours after recovery. */
    public static function maskBattery(array $actual, array $battery): array
    {
        $actual = Cooling::maskBattery($actual, $battery);
        $emptySince = null;
        foreach ($battery as $p) {
            if ($p['Value'] <= 0) {
                $emptySince ??= $p['TimeStamp'];
            } elseif ($emptySince !== null) {
                foreach ($actual as $t => $point) {
                    if ($point !== null && $point['to'] >= $emptySince && $point['from'] <= $p['TimeStamp'] + 21600) {
                        $actual[$t] = null;
                    }
                }
                $emptySince = null;
            }
        }
        return $actual;
    }

    public static function metrics(array $r, string $room): array
    {
        $errors = [];
        foreach (array_slice($r['forecast']['points'], 1) as $p) {
            if (isset($r['actual'][$room][$p['time']])) {
                $errors[] = $p[$room] - $r['actual'][$room][$p['time']]['value'];
            }
        }
        return ['n' => count($errors), 'expected' => count($r['forecast']['points']) - 1,
            'mae' => $errors === [] ? null : array_sum(array_map('abs', $errors)) / count($errors),
            'bias' => $errors === [] ? null : array_sum($errors) / count($errors)];
    }
}
