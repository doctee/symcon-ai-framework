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

    public static function render(array $j, string $status): string
    {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $n = static fn(?float $v): string => $v === null ? '–' : number_format($v, 2, ',', '.');
        $html = '<div style="font:14px system-ui;padding:12px"><h3>Ventilator – zusätzliche Vergleichsprognose</h3><p>' . $e($status) . '</p>'
            . '<p>Vor Aufwärmbeginn gespeichert. Vergleich zweier gemeinsam kalibrierter Lademodelle; die reguläre Nachtprognose bleibt unverändert. '
            . 'Laufzeit aus der bisherigen Aufwärmsimulation, nicht aus späteren Messwerten. Keine Rückkopplung der Vergleichsladung in diese Laufzeitschätzung. Kein Fenster-Energiezuschlag.</p>';
        foreach (array_reverse(array_slice($j['records'] ?? [], -5)) as $r) {
            $f = $r['forecast'];
            $html .= '<h4>Ausgabe ' . $e(self::time($f['issuedAt'])) . ' · Ankunft ' . $e(self::time($f['projection']['arrival'])) . '</h4>';
            $html .= '<p>' . (isset($r['supersededAt']) ? 'Früherer Planstand – nicht überschrieben' : 'Gespeicherter Planstand') . '</p>'
                . '<table style="border-collapse:collapse;width:100%"><tr><th>Nacht ab</th><th>Referenz kWh</th><th>Mit Ventilator kWh</th><th>Gemessen kWh</th><th>Fehler Referenz / Ventilator kWh</th><th>Laufzeit Prognose / Messung h</th></tr>';
            foreach ($f['fanComparison']['nights'] as $date => $night) {
                $a = $r['nightActual'][$date] ?? [];
                $html .= '<tr><td>' . $e($date) . '</td><td>' . $n($night['predictions']['reference']['kwh'])
                    . '</td><td>' . $n($night['predictions']['candidate']['kwh']) . '</td><td>' . $n($a['kwh'] ?? null)
                    . '</td><td>' . $n($a['errors']['reference']['signedKwh'] ?? null) . ' / ' . $n($a['errors']['candidate']['signedKwh'] ?? null)
                    . '</td><td>' . $n($night['features']['night_fan_hours']) . ' / ' . $n($a['states']['fan']['hours'] ?? null) . '</td></tr>';
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
}
