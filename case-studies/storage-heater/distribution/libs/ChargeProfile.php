<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;

/** Separate experiment: change only charge timing, preserving each charge-plan segment's energy. */
final class ChargeProfile
{
    public static function validate(array $p): void
    {
        if (
            ($p['schema'] ?? null) !== 1 || ($p['method'] ?? '') !== 'elapsed_eight_bins_v1'
            || !is_int($p['trainedThrough'] ?? null) || $p['trainedThrough'] <= 0
            || !is_array($p['weights'] ?? null) || !array_is_list($p['weights']) || count($p['weights']) !== 8
        ) {
            throw new RuntimeException('Invalid charge profile.');
        }
        foreach ($p['weights'] as $weight) {
            if (Forecast::number($weight, 'profile weight') < 0) {
                throw new RuntimeException('Negative charge profile weight.');
            }
        }
        if (abs(array_sum($p['weights']) - 1) > 1e-9) {
            throw new RuntimeException('Charge profile weights must sum to one.');
        }
    }

    /** Full-night elapsed bins; merge fan-only transitions before normalizing each charge state. */
    public static function schedule(array $f, array $profile): array
    {
        self::validate($profile);
        if ($profile['trainedThrough'] >= $f['start']) {
            throw new RuntimeException('Profile calibration must precede forecast.');
        }
        $result = [];
        foreach ($f['chargeNights'] as $date => $night) {
            $cycle = Forecast::cycle($date, 'Europe/Berlin');
            $cycle['start'] += 3600; // 22 to 23 local: DST transitions occur later.
            $width = ($cycle['end'] - $cycle['start']) / 8;
            $segments = [];
            foreach ($f['projection']['segments'] as $s) {
                $s['from'] = max($s['from'], $cycle['start'], $f['start']);
                $s['to'] = min($s['to'], $cycle['end'], $f['end']);
                if ($s['to'] <= $s['from']) {
                    continue;
                }
                $i = array_key_last($segments);
                if (
                    $i !== null && $segments[$i]['to'] === $s['from']
                    && $segments[$i]['enabled'] === $s['enabled'] && $segments[$i]['reduced'] === $s['reduced']
                ) {
                    $segments[$i]['to'] = $s['to'];
                } else {
                    $segments[] = $s;
                }
            }
            foreach ($segments as $s) {
                if (!$s['enabled']) {
                    continue;
                }
                $power = min($f['maxPowerKw'], $night[$s['reduced'] ? 'reduced' : 'normal']['kwh'] / (8 * $width / 3600));
                $budget = $power * ($s['to'] - $s['from']) / 3600;
                $bins = [];
                foreach ($profile['weights'] as $i => $weight) {
                    $from = max($s['from'], $cycle['start'] + $i * $width);
                    $to = min($s['to'], $cycle['start'] + ($i + 1) * $width);
                    if ($to > $from) {
                        $bins[] = ['from' => $from, 'to' => $to, 'weight' => $weight * ($to - $from) / $width,
                            'capacity' => $f['maxPowerKw'] * ($to - $from) / 3600, 'kwh' => 0.0];
                    }
                }
                // Water filling: saturation redistributes excess only inside this charge-state segment.
                $free = array_keys($bins);
                for ($iteration = 0; $iteration < 8 && $free !== [] && $budget > 1e-12; ++$iteration) {
                    $weights = array_sum(array_map(static fn(int $i): float => $bins[$i]['weight'], $free));
                    $duration = array_sum(array_map(static fn(int $i): float => $bins[$i]['to'] - $bins[$i]['from'], $free));
                    $spent = 0.0;
                    $next = [];
                    foreach ($free as $i) {
                        $share = $weights > 0 ? $bins[$i]['weight'] / $weights : ($bins[$i]['to'] - $bins[$i]['from']) / $duration;
                        $amount = min($budget * $share, $bins[$i]['capacity'] - $bins[$i]['kwh']);
                        $bins[$i]['kwh'] += $amount;
                        $spent += $amount;
                        if ($bins[$i]['kwh'] < $bins[$i]['capacity'] - 1e-12) {
                            $next[] = $i;
                        }
                    }
                    $budget -= $spent;
                    $free = $next;
                }
                foreach ($bins as $bin) {
                    $result[] = array_intersect_key($bin, array_flip(['from', 'to', 'kwh']));
                }
            }
        }
        return $result;
    }

    public static function energy(array $schedule, int $from, int $to): float
    {
        $energy = 0.0;
        foreach ($schedule as $s) {
            $overlap = max(0, min($to, $s['to']) - max($from, $s['from']));
            $energy += $s['kwh'] * $overlap / ($s['to'] - $s['from']);
        }
        return $energy;
    }

    public static function build(array $f, array $profile, array $first): array
    {
        if ($f['issuedAt'] >= min($f['phaseStart'], $first['start']) || $f['end'] < $first['end']) {
            throw new RuntimeException('Full first warming night must be forecast before its deadline.');
        }
        $schedule = self::schedule($f, $profile);
        $points = Warming::predict($f, $schedule);
        if (abs(end($points)['chargeKwh'] - end($f['points'])['chargeKwh']) > 1e-8) {
            throw new RuntimeException('Paired energy budgets differ.');
        }
        return ['method' => 'paired_charge_profile_segment_normalized_v1', 'profile' => $profile,
            'firstWarmingNight' => $first, 'schedule' => $schedule, 'points' => $points,
            'assumption' => 'Same energy per charge-state segment; elapsed-bin shape, capped and renormalized. No grid interruptions known.'];
    }

    public static function journal(string $json): array
    {
        $j = Warming::journal($json);
        foreach ($j['records'] as $r) {
            $f = $r['forecast'];
            $p = $f['profileComparison'] ?? [];
            self::validate($p['profile'] ?? []);
            if (
                ($p['method'] ?? '') !== 'paired_charge_profile_segment_normalized_v1'
                || !is_array($p['points'] ?? null) || !is_array($p['schedule'] ?? null)
                || ($f['beforePhase'] ?? false) !== true || $f['issuedAt'] >= $f['phaseStart']
                || $f['issuedAt'] >= ($p['firstWarmingNight']['start'] ?? 0)
                || $f['end'] < ($p['firstWarmingNight']['end'] ?? PHP_INT_MAX)
                || array_column($p['points'], 'time') !== array_column($f['points'], 'time')
            ) {
                throw new RuntimeException('Invalid prospective profile comparison.');
            }
            foreach ($p['points'] as $point) {
                Forecast::number($point['gallery'] ?? null, 'gallery');
                Forecast::number($point['sofa'] ?? null, 'sofa');
            }
        }
        return $j;
    }

    public static function metrics(array $r): array
    {
        $candidate = $r;
        $candidate['forecast']['points'] = $r['forecast']['profileComparison']['points'];
        $metrics = [];
        foreach (['gallery', 'sofa'] as $room) {
            $metrics[$room] = ['reference' => Warming::metrics($r, $room), 'profile' => Warming::metrics($candidate, $room)];
        }
        return $metrics;
    }

    public static function render(array $j, string $status): string
    {
        $html = '<div style="font:15px/1.5 system-ui;padding:12px"><h3>Ladeprofil · zusätzlicher Temperaturvergleich</h3><p>'
            . htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p><p>Gleiche Nachtenergie, Wetterdaten und Modellparameter; nur die zeitliche Ladungsverteilung unterscheidet sich. '
            . 'Vor Aufwärmbeginn eingefroren. Die gepaarte Referenz ist keine spätere tägliche Prognose. '
            . 'Mehrere Planstände sind keine unabhängigen Aufwärmphasen.</p>';
        foreach (array_reverse(array_slice($j['records'] ?? [], -3)) as $r) {
            $reference = ['records' => [$r]];
            $candidate = $r;
            $candidate['forecast']['points'] = $r['forecast']['profileComparison']['points'];
            $html .= '<h4>Referenz: gleichmäßige Ladung</h4>' . WarmingView::render($reference, 'Gepaarte Referenz');
            $view = WarmingView::render(['records' => [$candidate]], 'Zusätzliches Ladeprofil');
            $view = str_replace('Ladung gleichmäßig zwischen 23 und 07 Uhr', 'Ladung nach eingefrorenem Zeitprofil zwischen 23 und 07 Uhr', $view);
            $html .= '<h4>Vergleich: neues Ladeprofil</h4>' . $view;
        }
        return $html . '</div>';
    }
}
