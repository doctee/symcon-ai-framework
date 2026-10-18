<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Observational adapter for an existing one-shot preheating selection. */
final class HeatingPlan
{
    public const LIMIT = 256;

    public static function journal(string $json): array
    {
        $j = Forecast::decode($json);
        if (
            ($j['schema'] ?? null) !== 1 || !is_array($j['plans'] ?? null) || !is_array($j['revisions'] ?? null)
            || !is_int($j['sequence'] ?? null) || !is_int($j['discarded'] ?? null)
            || count($j['plans']) > self::LIMIT || count($j['revisions']) > self::LIMIT
        ) {
            throw new RuntimeException('Invalid plan journal; restore a verified backup.');
        }
        foreach ($j['plans'] as $p) {
            if (!is_array($p) || !is_string($p['hash'] ?? null) || !is_int($p['observedAt'] ?? null) || !is_array($p['inputs'] ?? null)) {
                throw new RuntimeException('Invalid saved plan.');
            }
        }
        foreach ($j['revisions'] as $r) {
            if (
                !is_array($r) || !is_int($r['sequence'] ?? null) || !is_int($r['issuedAt'] ?? null)
                || !is_string($r['key'] ?? null) || !is_array($r['plan'] ?? null)
            ) {
                throw new RuntimeException('Invalid saved plan revision.');
            }
        }
        return $j;
    }

    public static function retain(array $j): array
    {
        foreach (['plans', 'revisions'] as $key) {
            while (count($j[$key]) > self::LIMIT) {
                array_shift($j[$key]);
                ++$j['discarded'];
            }
        }
        // Leave headroom below Forecast::decode's four MiB guard.
        while (strlen(Forecast::encode($j)) > 3 * 1024 * 1024) {
            if (count($j['plans']) > 1) {
                array_shift($j['plans']);
            } elseif (count($j['revisions']) > 1) {
                array_shift($j['revisions']);
            } else {
                throw new RuntimeException('Plan record exceeds storage bound.');
            }
            ++$j['discarded'];
        }
        return $j;
    }

    public static function cycle(int $now): array
    {
        $day = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Berlin'));
        if ((int) $day->format('G') < 7) {
            $day = $day->modify('-1 day');
        }
        return Forecast::cycle($day->format('Y-m-d'), 'Europe/Berlin');
    }

    public static function event(array $event): array
    {
        if (
            ($event['EventType'] ?? null) !== EVENTTYPE_CYCLIC
            || ($event['CyclicDateType'] ?? null) !== EVENTCYCLICDATETYPE_ONCE
            || ($event['CyclicTimeType'] ?? null) !== EVENTCYCLICTIMETYPE_ONCE
            || !is_bool($event['EventActive'] ?? null) || !is_int($event['NextRun'] ?? null)
        ) {
            throw new RuntimeException('Expected a one-shot cyclic preselection event.');
        }
        return array_intersect_key($event, array_flip([
            'EventActive', 'NextRun', 'LastRun', 'EventType', 'CyclicDateType', 'CyclicTimeType',
            'CyclicDateFrom', 'CyclicTimeFrom', 'EventActionID', 'EventActionParameters',
        ]));
    }

    /** Pending timers, never an expired timer's date, determine future starts. */
    public static function project(array $plan, array $cycle, int $now): array
    {
        $v = $plan['inputs']['values'];
        $events = $plan['inputs']['events'];
        $pending = [];
        foreach (['storageEvent', 'fanEvent', 'resetEvent'] as $name) {
            $e = $events[$name];
            $pending[$name] = $e['EventActive'] && $e['NextRun'] > $now ? $e['NextRun'] : null;
        }
        $selected = $v['arrival'] > 0 && $v['winter'] && !$v['presence'];
        $storage = $selected && !$v['storageRequested'] ? $pending['storageEvent'] : null;
        $fan = $selected && !$v['fanRequested'] ? $pending['fanEvent'] : null;
        if (!$v['storageRequested'] && ($storage === null || ($fan !== null && $storage > $fan))) {
            $fan = null;
        }
        $reset = $selected ? $pending['resetEvent'] : null;
        $from = max($now, $cycle['start']);
        $until = $cycle['end'];
        $boundaries = [$from, $until];
        foreach ([$storage, $fan, $reset] as $t) {
            if ($t !== null && $t > $from && $t < $until) {
                $boundaries[] = $t;
            }
        }
        sort($boundaries);
        $boundaries = array_values(array_unique($boundaries));
        $segments = [];
        $hours = 0.0;
        for ($i = 0; $i < count($boundaries) - 1; ++$i) {
            $t = $boundaries[$i];
            $enabled = $v['enabled'];
            $reduced = $v['reduced'];
            $permission = $v['regulatorEnabled'] && $v['regulatorActive'];
            if ($storage !== null && $storage <= $t) {
                $enabled = true;
                $reduced = false;
                $permission = false;
            }
            if ($fan !== null && $fan <= $t) {
                $enabled = true;
                $reduced = false;
                $permission = $v['regulatorActive'];
            }
            if ($reset !== null && $reset <= $t) {
                // Conditional branch: no arrival has actually been observed yet.
                $reduced = true;
                $permission = false;
            }
            $seconds = $boundaries[$i + 1] - $t;
            $hours += $permission ? $seconds / 3600 : 0;
            $segments[] = ['from' => $t, 'to' => $boundaries[$i + 1],
                'enabled' => $enabled, 'reduced' => $reduced, 'fanPermitted' => $permission];
        }
        $warnings = ['fan_permission_is_not_runtime', 'future_fan_charge_feedback_not_calibrated'];
        if ($selected && $v['arrival'] <= $until) {
            $warnings[] = 'arrival_not_observed';
            if ($v['regulatorActive']) {
                // Actual arrival may clear the selection and keep the regulator on.
                // Bound both branches, rather than presenting absence-only hours as a maximum.
                $arrival = max($from, $v['arrival']);
                $arrivalHours = max(0, $until - $arrival) / 3600;
                foreach ($segments as $segment) {
                    if ($segment['fanPermitted']) {
                        $arrivalHours += max(0, min($arrival, $segment['to']) - $segment['from']) / 3600;
                    }
                }
                $hours = max($hours, $arrivalHours);
            }
            if ($reset !== null) {
                $warnings[] = 'reset_conditional_on_no_arrival';
            }
        }
        if ($selected && !$v['storageRequested'] && $storage === null) {
            $warnings[] = 'storage_start_missing_or_expired';
        }
        if ($selected && !$v['fanRequested'] && $fan === null) {
            $warnings[] = 'fan_start_missing_or_expired';
        }
        if ($v['setback']) {
            $warnings[] = 'thermostat_setback_active';
        }
        return ['from' => $from, 'to' => $until, 'segments' => $segments,
            'arrival' => $v['arrival'], 'storageStart' => $storage, 'fanStart' => $fan, 'resetAt' => $reset,
            'fanRuntimeHours' => ['min' => 0.0, 'max' => $hours],
            'setpoint' => $v['setpoint'], 'warnings' => $warnings];
    }

    /** Plan-conditioned baseline only; no invented fan heat coefficient. */
    public static function estimate(array $baseline, array $projection, array $past, int $now): array
    {
        $result = ['status' => 'unavailable', 'baselineKwh' => $baseline['kwh'],
            'baselineIssuedAt' => $baseline['issuedAt'], 'kwh' => null,
            'remainingKwh' => null, 'measuredKwh' => $past['kwh'] ?? null,
            'measuredUntil' => $past['until'] ?? null, 'warnings' => $projection['warnings']];
        if ($projection['fanRuntimeHours']['max'] > 0 || ($past['fanHours'] ?? 0) > 0) {
            $result['status'] = 'fan_effect_not_calibrated';
            return $result;
        }
        if (
            in_array('arrival_not_observed', $projection['warnings'], true)
            || in_array('reset_conditional_on_no_arrival', $projection['warnings'], true)
        ) {
            $result['status'] = 'conditional_arrival';
            return $result;
        }
        $enabled = array_unique(array_column($projection['segments'], 'enabled'));
        $reduced = array_unique(array_column($projection['segments'], 'reduced'));
        if (
            count($enabled) !== 1 || count($reduced) !== 1
            || (isset($past['enabled']) && $past['enabled'] !== reset($enabled))
            || (isset($past['reduced']) && $past['reduced'] !== reset($reduced))
            || ($past['mixed'] ?? false)
        ) {
            $result['status'] = 'mixed_night_not_calibrated';
            return $result;
        }
        if (reset($enabled) === false) {
            $result['status'] = 'heating_disabled';
            return $result;
        }
        $features = $baseline['features'];
        $features['reduced'] = (int) reset($reduced);
        $features['hdd_reduced'] = $features['hdd_night'] * $features['reduced'];
        $forecast = Forecast::predict($baseline['model'], $features, $now);
        $result['status'] = isset($past['until']) ? 'updated_whole_night_baseline' : 'planned_state_baseline';
        $result['kwh'] = $forecast['kwh'];
        $result['features'] = $features;
        $result['warnings'] = array_merge($result['warnings'], $forecast['warnings']);
        // Whole-night regression has no calibrated intra-night discharge profile.
        // Do not subtract the meter and claim a defensible remaining-energy forecast.
        return $result;
    }
}
