<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

/** Evaluation metadata only: frozen forecasts and measured outcomes remain untouched. */
final class ComparisonLifecycle
{
    public static function assess(array $r, array $plans, int $now): array
    {
        $f = $r['forecast'];
        $old = $r['evaluation'] ?? null;
        if ($old !== null && in_array($old['state'], ['cancelled', 'replaced', 'arrived', 'completed'], true)) {
            return $old;
        }
        $base = $f['plan'];
        $v = $base['inputs']['values'];
        $result = ['schema' => 1, 'state' => 'active', 'eligible' => ($f['beforePhase'] ?? false) === true,
            'reason' => ($f['beforePhase'] ?? false) ? 'plan_unchanged' : 'issued_after_phase_start',
            'phaseKey' => SAEF_CreateConfigurationHash([$v['arrival'], $base['inputs']['changedAt']['arrival'] ?? null]),
            'checkedThrough' => min($now, $f['end']), 'observedAt' => $now, 'evidencePlanHash' => $base['hash']];
        usort($plans, static fn(array $a, array $b): int => $a['observedAt'] <=> $b['observedAt']);
        $covered = $plans !== [] && $plans[0]['observedAt'] <= (($old['state'] ?? '') === 'active' ? $old['checkedThrough'] : $base['observedAt']);
        $baseIndex = null;
        foreach ($plans as $i => $p) {
            if ($p['hash'] === $base['hash'] && $p['observedAt'] === $base['observedAt']) {
                $baseIndex = $i;
                break;
            }
        }
        $completedStages = [];
        foreach ($plans as $i => $p) {
            $t = $p['observedAt'];
            if (($baseIndex !== null ? $i <= $baseIndex : $t <= $base['observedAt']) || $t > $now || $t >= $f['end']) {
                continue;
            }
            $current = $p['inputs']['values'];
            $state = null;
            $reason = null;
            if (
                $current['arrival'] > 0 && ($current['arrival'] !== $v['arrival']
                || ($p['inputs']['changedAt']['arrival'] ?? null) !== ($base['inputs']['changedAt']['arrival'] ?? null))
            ) {
                $state = 'replaced';
                $reason = 'arrival_reselected';
            } elseif (!$current['winter']) {
                $state = 'cancelled';
                $reason = 'winter_disabled';
            } elseif ($current['presence']) {
                // Arrival clears the selection and one-shot timers in the existing controller.
                // Preserve that normal handover; never infer cancellation from cleared flags alone.
                $state = 'arrived';
                $reason = 'arrival_observed';
            } elseif ($current['arrival'] === 0) {
                $state = 'cancelled';
                $reason = 'selection_cleared_without_arrival';
            } else {
                foreach (['storageStart' => 'storageEvent', 'fanStart' => 'fanEvent'] as $start => $event) {
                    $due = $f['projection'][$start];
                    if ($due === null || $due >= $f['end']) {
                        continue;
                    }
                    $e = $p['inputs']['events'][$event];
                    $requested = $start === 'storageStart' ? 'storageRequested' : 'fanRequested';
                    $ran = abs(($e['LastRun'] ?? 0) - $due) <= 60;
                    if ($ran && $t >= $due) {
                        $completedStages[$start] = true;
                    }
                    if (($completedStages[$start] ?? false) && ($current[$requested] ?? true)) {
                        continue;
                    }
                    if ($e['EventActive'] && $e['NextRun'] > $t && $e['NextRun'] !== $due) {
                        $state = 'replaced';
                        $reason = 'start_rescheduled';
                        break;
                    }
                    // A consumed one-shot is not a cancelled timer. Allow normal execution lag.
                    if (!$e['EventActive'] && !$ran) {
                        $state = 'cancelled';
                        $reason = 'pending_start_disabled';
                        break;
                    }
                }
            }
            if ($state !== null) {
                $result['state'] = $state;
                $result['reason'] = $reason;
                $result['observedAt'] = $t;
                $result['evidencePlanHash'] = $p['hash'];
                $result['eligible'] = $result['eligible'] && $covered && $state === 'arrived';
                return $result;
            }
        }
        if (!$covered) {
            $result['state'] = 'unknown';
            $result['reason'] = 'plan_history_incomplete';
            $result['eligible'] = false;
        } elseif ($now >= $f['end']) {
            $result['state'] = 'completed';
        }
        return $result;
    }

    public static function status(array $j, string $status): string
    {
        $last = ($j['records'] ?? []) === [] ? null : end($j['records']);
        $state = $last['evaluation']['state'] ?? '';
        if (in_array($state, ['cancelled', 'replaced'], true)) {
            $status .= ' · Letzte gespeicherte Vorwahl ' . ($state === 'cancelled' ? 'abgebrochen' : 'ersetzt')
                . '; von der Modellbewertung ausgeschlossen';
        }
        return $status;
    }

    public static function label(array $r): string
    {
        $e = $r['evaluation'] ?? null;
        if ($e === null) {
            return 'Planverlauf noch nicht geprüft – keine reguläre Modellbewertung.';
        }
        $state = match ($e['state']) {
            'cancelled' => 'Vorwahl abgebrochen',
            'replaced' => 'Vorwahl ersetzt',
            'arrived' => 'Ankunft erkannt; Vorwahl regulär beendet',
            'completed' => 'Prognosehorizont abgeschlossen',
            'unknown' => 'Planverlauf unvollständig',
            default => 'Vorwahl unverändert',
        };
        $when = (new \DateTimeImmutable('@' . $e['observedAt']))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m. H:i');
        return $state . ' (beobachtet ' . $when . '). ' . ($e['eligible']
            ? 'Planverlauf zulässig; Messdaten und Betriebsabweichungen bleiben gesondert zu prüfen.'
            : 'Von der regulären Modellbewertung ausgeschlossen. Kurven und Fehler dienen nur der Diagnose.');
    }
}
