<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

/** Static comparison only; never offers an action or hides missing evidence. */
final class FanComparisonView
{
    private static function time(int $t): string
    {
        return (new \DateTimeImmutable('@' . $t))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');
    }

    public static function render(array $j, string $status, array $context = []): string
    {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $n = static fn(?float $v): string => $v === null ? '–' : number_format($v, 2, ',', '.');
        $html = '<div style="font:14px system-ui;padding:12px"><h3>Ventilator – zusätzliche Vergleichsprognose</h3>'
            . self::selection($j, $context) . '<p>' . $e($status) . '</p>'
            . '<p>Vergleich zweier gemeinsam kalibrierter Lademodelle; die reguläre Nachtprognose bleibt unverändert. '
            . 'Laufzeit aus der bisherigen Aufwärmsimulation, nicht aus späteren Messwerten. Keine Rückkopplung der Vergleichsladung in diese Laufzeitschätzung. Kein Fenster-Energiezuschlag.</p>';
        foreach (array_reverse(array_slice($j['records'] ?? [], -5)) as $r) {
            $f = $r['forecast'];
            $excluded = ($r['evaluation']['eligible'] ?? false) !== true;
            $html .= '<h4>Ausgabe ' . $e(self::time($f['issuedAt'])) . ' · Ankunft ' . $e(self::time($f['projection']['arrival'])) . '</h4>';
            $html .= '<p><strong>' . $e(ComparisonLifecycle::label($r)) . '</strong></p>';
            $window = FanComparison::issueWindow($f['phaseStart']);
            if (isset($f['fanComparison']['nights'][$window['cycle']['date']])) {
                $html .= self::schedule($window, 'Vorab gespeichert am ' . self::time($f['issuedAt']) . ' (dieser Planstand)');
            } else {
                $html .= '<p>Dieser ältere Planstand enthält keine vollständige erste Aufwärmnacht.</p>';
            }
            $html .= '<p>' . (isset($r['supersededAt']) ? 'Früherer Planstand – nicht überschrieben' : 'Gespeicherter Planstand') . '</p>'
                . '<table style="border-collapse:collapse;width:100%"><tr><th>Nacht ab</th><th>Referenz kWh</th><th>Mit Ventilator kWh</th><th>Gemessen kWh</th><th>Fehler Referenz / Ventilator kWh</th><th>Laufzeit Prognose / Messung h</th></tr>';
            foreach ($f['fanComparison']['nights'] as $date => $night) {
                $a = $r['nightActual'][$date] ?? [];
                if ($excluded) {
                    $a['errors'] = $a['diagnosticErrors'] ?? $a['errors'] ?? [];
                }
                $html .= '<tr><td>' . $e($date) . '</td><td>' . $n($night['predictions']['reference']['kwh'])
                    . '</td><td>' . $n($night['predictions']['candidate']['kwh']) . '</td><td>' . $n($a['kwh'] ?? null)
                    . '</td><td>' . ($excluded ? 'Nur Diagnose: ' : '') . $n($a['errors']['reference']['signedKwh'] ?? null) . ' / ' . $n($a['errors']['candidate']['signedKwh'] ?? null)
                    . '</td><td>' . $n($night['features']['night_fan_hours']) . ' / ' . $n($a['states']['fan']['hours'] ?? null) . '</td></tr>';
                if ($night['chargePlan']['mixed'] ?? false) {
                    $parts = [];
                    foreach ($night['chargePlan']['segments'] as $s) {
                        $parts[] = self::time($s['from']) . '–' . self::time($s['to']) . ': ' . ($s['reduced'] ? 'abgesenkt' : 'normal');
                    }
                    $html .= '<tr><td colspan="6">Absenkungswechsel – vorläufige zeitgewichtete Näherung: '
                        . $e(implode('; ', $parts)) . '. Anteil abgesenkt in der Ladezeit 23–07 Uhr: '
                        . $n(100 * $night['chargePlan']['reducedChargeFraction']) . '%.'
                        . (isset($a['reductionPlan']) ? ' Abweichung vom geplanten Zustand: ' . $n($a['reductionPlan']['mismatchSeconds'] / 60) . ' min.' : '')
                        . '</td></tr>';
                }
                $warnings = array_merge($night['predictions']['reference']['warnings'], $night['predictions']['candidate']['warnings'], $a['warnings'] ?? []);
                $message = $r['measurementErrors'][$date] ?? ($a['status'] ?? 'Messung steht aus');
                $html .= '<tr><td colspan="6">' . $e($message . ($warnings === [] ? '' : ' · ' . implode(', ', array_unique($warnings)))) . '</td></tr>';
            }
            $html .= '</table>';
            foreach ($f['fanComparison']['excluded'] as $date => $reason) {
                $html .= '<p>' . $e($date . ': ' . $reason) . '</p>';
            }
        }
        return $html . '<p>Fehler = Prognose minus Messung; negatives Vorzeichen bedeutet Unterschätzung. '
            . 'Frühe Abendtemperaturen und vorherige Ladung werden bis zum Horizont festgehalten. '
            . 'Planrevisionen und mehrere Nächte derselben Aufwärmphase sind keine unabhängigen Testphasen. '
            . 'Eine Verbesserung ist erst mit neuen vollständigen Vorabvergleichen belegt.</p></div>';
    }

    /** Presentation only: derive current eligibility without issuing or changing a forecast. */
    private static function selection(array $j, array $context): string
    {
        $heading = '<section style="border:1px solid #888;border-radius:8px;padding:12px;margin:12px 0"><h4>Erste Aufwärmnacht – aktuelle Vorwahl</h4>';
        if ($context === []) {
            return $heading . '<p>Zeitplan derzeit nicht verfügbar; Status und gespeicherte Planstände beachten.</p></section>';
        }
        $plan = $context['plan'];
        $now = $context['now'];
        $v = $plan['inputs']['values'];
        if ($v['arrival'] <= $now || !$v['winter'] || $v['presence']) {
            return $heading . '<p>Keine aktive zukünftige Aufwärmvorwahl. Speicherzeitpunkt noch nicht festgelegt.</p></section>';
        }
        $projection = HeatingPlan::project($plan, ['start' => $now, 'end' => min($v['arrival'], $now + 48 * 3600)], $now);
        if ($v['storageRequested'] || $v['fanRequested'] || $projection['storageStart'] === null) {
            return $heading . '<p>' . htmlspecialchars(self::phaseStatus($j, $v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></section>';
        }
        $phase = min(array_filter([$projection['storageStart'], $projection['fanStart']], static fn(?int $t): bool => $t !== null));
        $window = FanComparison::issueWindow($phase);
        $first = $window['cycle'];
        $last = $j['records'] === [] ? null : end($j['records']);
        if (
            $last !== null && $last['configurationHash'] === $context['configurationHash']
            && $last['forecast']['plan']['hash'] === $plan['hash']
            && $last['forecast']['phaseStart'] === $phase
            && $last['forecast']['issuedAt'] < $window['deadline']
            && ($last['forecast']['fanComparison']['nights'][$first['date']]['cycle'] ?? null) === $first
        ) {
            $state = 'Vorab gespeichert am ' . self::time($last['forecast']['issuedAt']);
        } elseif ($now >= $window['deadline']) {
            $state = 'Nicht vorab gespeichert – Vorabfrist verpasst';
        } elseif ($v['arrival'] < $first['end']) {
            $state = 'Nicht möglich – erste Nacht reicht über die Ankunft hinaus';
        } elseif ($now < $window['earliest']) {
            $state = 'Noch nicht gespeichert – wartet auf vollständigen Prognosehorizont';
        } else {
            $state = 'Noch nicht gespeichert – nächster erfolgreicher Prüflauf';
        }
        return $heading . self::schedule($window, $state)
            . '<p>Automatische Prüfung alle fünf Minuten; frische und vollständige Eingangsdaten erforderlich. Alle Zeiten: Europe/Berlin.</p></section>';
    }

    /** Wording only: no issuance decision or saved forecast is changed. */
    public static function phaseStatus(array $j, array $values): string
    {
        $started = $values['storageRequested'] || $values['fanRequested'];
        $message = $started ? 'Aufwärmphase gestartet.' : 'Aufwärmstart unbekannt – kein zukünftiger Speicherstart im Zeitplan.';
        return $message . (($j['records'] ?? []) === []
            ? ' Keine Vorabprognose gespeichert.'
            : ' Gespeicherte Vorabprognosen werden weiter mit Messwerten verglichen; Planstände siehe unten.');
    }

    private static function schedule(array $window, string $state): string
    {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<dl><dt>Erste Aufwärmnacht (Messfenster)</dt><dd>'
            . $e(self::time($window['cycle']['start']) . ' – ' . self::time($window['cycle']['end']))
            . '</dd><dt>Speicherung möglich ab</dt><dd>' . $e(self::time($window['earliest']))
            . '</dd><dt>Vorabfrist – Speicherung muss abgeschlossen sein vor</dt><dd>' . $e(self::time($window['deadline']))
            . '</dd><dt>Speicherstatus</dt><dd><strong>' . $e($state) . '</strong></dd></dl>';
    }
}
