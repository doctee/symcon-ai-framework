<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;

/** Separate one-pass charge comparison, driven only by frozen prospective fan hours. */
final class FanComparison
{
    public static function models(array $models): void
    {
        if (($models['schema'] ?? null) !== 1) {
            throw new RuntimeException('Invalid fan comparison model schema.');
        }
        Forecast::validateModel($models['reference'] ?? []);
        $candidate = $models['candidate'] ?? [];
        if (($candidate['features'] ?? []) !== [...Forecast::FEATURES, 'night_fan_hours']) {
            throw new RuntimeException('Only the calibrated night fan feature is permitted; no window surcharge.');
        }
        foreach (['mean', 'scale', 'coefficients', 'ranges'] as $key) {
            if (!is_array($candidate[$key] ?? null) || !array_is_list($candidate[$key]) || count($candidate[$key]) !== 9) {
                throw new RuntimeException('Invalid comparison model vector.');
            }
        }
        $base = $candidate;
        $base['features'] = Forecast::FEATURES;
        foreach (['mean', 'scale', 'coefficients', 'ranges'] as $key) {
            $base[$key] = array_slice($base[$key], 0, 8);
        }
        Forecast::validateModel($base);
        Forecast::number($candidate['mean'][8], 'fan mean');
        $scale = Forecast::number($candidate['scale'][8], 'fan scale');
        $coef = Forecast::number($candidate['coefficients'][8], 'fan coefficient');
        $range = $candidate['ranges'][8];
        if (
            $scale <= 0 || $coef < 0 || $coef / $scale > 10
            || !is_array($range) || count($range) !== 2
            || Forecast::number($range[0], 'fan minimum') < 0
            || Forecast::number($range[1], 'fan maximum') > 10 || $range[0] > $range[1]
            || $candidate['trainedThrough'] <= 0
            || $candidate['trainedThrough'] !== $models['reference']['trainedThrough']
        ) {
            throw new RuntimeException('Invalid fan calibration range or provenance.');
        }
    }

    public static function predict(array $model, array $features, int $issuedAt): array
    {
        if ($model['trainedThrough'] >= $issuedAt) {
            throw new RuntimeException('Comparison model contains future training data.');
        }
        $kwh = $model['intercept'];
        $warnings = [];
        foreach ($model['features'] as $i => $name) {
            $x = Forecast::number($features[$name] ?? null, $name);
            $kwh += ($x - $model['mean'][$i]) / $model['scale'][$i] * $model['coefficients'][$i];
            if ($x < $model['ranges'][$i][0] || $x > $model['ranges'][$i][1]) {
                $warnings[] = 'outside_training_range:' . $name;
            }
        }
        return ['kwh' => max(0.0, Forecast::number($kwh, 'comparison prediction')), 'warnings' => $warnings];
    }

    public static function build(array $f, array $models, int $issuedAt): array
    {
        self::models($models);
        if ($issuedAt >= $f['phaseStart'] || $issuedAt < $f['start']) {
            throw new RuntimeException('Vergleich nur vor Beginn der Aufwärmphase; keine Rückrechnung.');
        }
        $outside = array_column($f['weather']['points'], 'temperature', 'time');
        $fan = array_column($f['points'], 'fanHours', 'time');
        $nights = $excluded = [];
        foreach ($f['chargeNights'] as $date => $scenarios) {
            $cycle = Forecast::cycle($date, 'Europe/Berlin');
            if ($cycle['start'] <= $issuedAt || $cycle['end'] > $f['end']) {
                $excluded[$date] = 'Nur vollständige zukünftige Ladefenster 22–07 Uhr werden verglichen.';
                continue;
            }
            $segments = array_values(array_filter($f['projection']['segments'], static fn(array $s): bool => $s['from'] < $cycle['end'] && $s['to'] > $cycle['start']));
            $enabled = array_unique(array_column($segments, 'enabled'));
            $reduced = array_unique(array_column($segments, 'reduced'));
            if (count($enabled) !== 1 || reset($enabled) !== true || count($reduced) !== 1) {
                $excluded[$date] = 'Wechselnde Ladefreigabe oder Speicher-Absenkung: nicht kalibriert.';
                continue;
            }
            $temperatures = [];
            for ($t = $cycle['start']; $t < $cycle['end']; $t += 3600) {
                if (!isset($outside[$t], $outside[$t + 3600])) {
                    throw new RuntimeException('Vergleichswetter deckt 22–07 Uhr nicht ab.');
                }
                $temperatures[] = ($outside[$t] + $outside[$t + 3600]) / 2;
            }
            if (!isset($fan[$cycle['start']], $fan[$cycle['end']])) {
                throw new RuntimeException('Simulierte Ventilatorlaufzeit deckt die Nacht nicht ab.');
            }
            $red = (bool) reset($reduced);
            $features = $scenarios[$red ? 'reduced' : 'normal']['features'];
            $features['hdd_night'] = max(0, 15 - array_sum($temperatures) / count($temperatures));
            $features['hdd_reduced'] = $features['hdd_night'] * (float) $red;
            $features['night_fan_hours'] = $fan[$cycle['end']] - $fan[$cycle['start']];
            $cap = $f['maxPowerKw'] * (($cycle['end'] - Warming::chargeCycle($cycle['start'] + 3600)['start']) / 3600);
            $predictions = [];
            foreach (['reference', 'candidate'] as $name) {
                $p = self::predict($models[$name], $features, $issuedAt);
                if ($p['kwh'] > $cap) {
                    $p['warnings'][] = 'physical_charge_cap';
                    $p['kwh'] = $cap;
                }
                $predictions[$name] = $p;
            }
            $nights[$date] = ['cycle' => $cycle, 'features' => $features, 'predictions' => $predictions,
                'fanSource' => 'frozen_baseline_thermal_simulation', 'expectedReduced' => $red];
        }
        if ($nights === []) {
            throw new RuntimeException('Keine vollständige, stabile zukünftige Ladenacht im Aufwärmhorizont.');
        }
        return ['models' => $models, 'nights' => $nights, 'excluded' => $excluded,
            'method' => 'one_pass_no_feedback', 'windowHeatLossEnabled' => false,
            'assumptions' => ['issue_time_features_held', 'future_runtime_simulated_not_permission',
                'baseline_charge_drives_fan_simulation', 'no_candidate_charge_feedback',
                'no_future_actual_inputs', 'no_window_energy_surcharge']];
    }

    public static function journal(string $json): array
    {
        $j = Warming::journal($json);
        foreach ($j['records'] as $r) {
            $f = $r['forecast'];
            if (
                !($f['beforePhase'] ?? false) || $f['issuedAt'] >= $f['phaseStart']
                || ($f['fanComparison']['windowHeatLossEnabled'] ?? null) !== false
                || !is_array($f['fanComparison']['nights'] ?? null) || $f['fanComparison']['nights'] === []
            ) {
                throw new RuntimeException('Invalid prospective fan comparison journal.');
            }
            self::models($f['fanComparison']['models']);
            foreach ($f['fanComparison']['nights'] as $n) {
                if ($n['cycle']['start'] <= $f['issuedAt'] || $n['cycle']['end'] > $f['end']) {
                    throw new RuntimeException('Saved comparison is not fully prospective.');
                }
            }
        }
        return $j;
    }

    public static function errors(array $night, float $actual): array
    {
        $result = [];
        foreach ($night['predictions'] as $name => $p) {
            $error = $p['kwh'] - $actual;
            $result[$name] = ['signedKwh' => $error, 'absoluteKwh' => abs($error),
                'percent' => $actual >= 1 ? 100 * $error / $actual : null];
        }
        return $result;
    }
}
