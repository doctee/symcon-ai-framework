<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;

/** Read-only inline SVG and per-revision scores; no controls or aggregate pseudo-validation. */
final class WarmingView
{
    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function time(int $t): string
    {
        return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i');
    }

    public static function render(array $j, string $status): string
    {
        $html = '<div style="font:15px/1.5 system-ui;padding:12px"><h3>Aufwärmen · Prognose und Messung</h3><p>' . self::e($status) . '</p>';
        $html .= '<p><b>Experimentelles Aufwärmmodell.</b> Ventilatorlauf aus Vorwahl und simuliertem Galerie-Thermostat; Ladung gleichmäßig zwischen 23 und 07 Uhr nach einer frühen Schätzung aus dem Nachtmodell. Netzunterbrechungen und künftige Tür-/Kaminzustände sind unbekannt. Sonnenwärme wird aus der Wetterprognose angenähert, der Badbetrieb aus den letzten 24 Stunden. Kein gemessener Speicherfüllstand, keine Komfortgarantie.</p>';
        $records = $j['records'] ?? [];
        if ($records === []) {
            $html .= '<p>Noch keine vorab gespeicherte Aufwärmkurve. Bei einer zukünftigen Heizungsvorwahl werden bis zu 48 Stunden vorausberechnet. Vergangene Aufwärmphasen werden nicht als Vorhersage nachgetragen.</p>';
        }
        foreach (array_reverse(array_slice($records, -6)) as $i => $r) {
            $f = $r['forecast'];
            $last = end($f['points']);
            $html .= '<details' . ($i === 0 ? ' open' : '') . '><summary>Planstand ' . self::time($f['issuedAt']) . ' · bis ' . self::time($f['end']) . '</summary>';
            $html .= '<p>' . ($f['beforePhase'] ? 'Vor Aufwärmbeginn gespeichert.' : 'Während der Aufwärmphase gespeichert; keine Vorhersage des bereits vergangenen Beginns.') . ' Ankunft laut diesem Plan: ' . self::time($f['plan']['inputs']['values']['arrival']) . '.</p>';
            if (isset($r['supersededAt'])) {
                $html .= '<p>Ein neuer beobachteter Plan-/Betriebsstand wurde am ' . self::time($r['supersededAt']) . ' gespeichert. Diese ältere Kurve wird unverändert weiter verglichen.</p>';
            }
            if (isset($r['operationActual']['fanHours'])) {
                $html .= '<p>Gemessener Ventilator-Relaisbetrieb bisher: ' . number_format($r['operationActual']['fanHours'], 1, ',', '') . ' h';
                if (($r['operationActual']['auxiliaryHours'] ?? 0) > 0) {
                    $html .= ' · Zusatzheizung war aktiv: Temperaturfehler ist kein isolierter Modelltest';
                }
                $html .= '.</p>';
            }
            $html .= '<p>Bis zum Horizont: Galerie <b>' . number_format($last['gallery'], 1, ',', '') . ' °C</b>, Wohnzimmer <b>' . number_format($last['sofa'], 1, ',', '') . ' °C</b>. Simulierter Ventilatorlauf ' . number_format($last['fanHours'], 1, ',', '') . ' h; angenommene weitere Ladung ' . number_format($last['chargeKwh'], 1, ',', '') . ' kWh.</p>';
            foreach (['gallery' => 'Galerie', 'sofa' => 'Wohnzimmer'] as $room => $label) {
                $score = Warming::metrics($r, $room);
                $html .= '<h4>' . $label . '</h4>' . self::chart($r, $room, $label);
                $html .= '<p>' . $score['n'] . '/' . $score['expected'] . ' Vergleichspunkte';
                if ($score['n'] > 0) {
                    $html .= ' · mittlerer absoluter Fehler ' . number_format($score['mae'], 2, ',', '') . ' °C · Prognose minus Messung ' . number_format($score['bias'], 2, ',', '') . ' °C';
                }
                $html .= '.</p>';
            }
            if (($r['measurementErrors'] ?? []) !== []) {
                $html .= '<p>Messdaten prüfen: ' . self::e(implode('; ', $r['measurementErrors'])) . '</p>';
            }
            $classification = $r['fireplaceAtComparison'] ?? $f['fireplaceKnownAtIssue'];
            $count = count($classification['reportedStarts'] ?? []);
            $html .= '<p>Kaminprotokoll: ' . ($count === 0 ? 'kein Start im Vergleichsfenster gemeldet' : $count . ' Start(s) im Vergleichsfenster gemeldet; mögliche zusätzliche Wärme') . '. Ohne Eintrag ist ein Kaminbetrieb nicht ausgeschlossen.</p>';
            $html .= '<p>' . ($r['settledAt'] === null ? 'Vergleich läuft.' : 'Messfenster abgeschlossen.') . ' Blau gestrichelt: unveränderte Vorhersage; orange: Archivmessung. Stundenpunkte, maximal 6 Stunden Interpolationsabstand; fehlende Messwerte bleiben Lücken. Der Startmesswert zählt nicht zur Genauigkeit. Geänderte Planstände sind eigene, überlappende Vorhersagen und keine unabhängigen Testphasen.</p></details>';
        }
        return $html . '<p>Letzte 6 Planstände · maximal 48 im Journal · ' . ($j['discarded'] ?? 0) . ' abgeschlossene Stände durch Aufbewahrungsgrenze entfernt. Die Heizungsregelung bleibt unverändert.</p></div>';
    }

    private static function chart(array $r, string $room, string $label): string
    {
        $f = $r['forecast'];
        $values = array_column($f['points'], $room);
        foreach ($r['actual'][$room] ?? [] as $p) {
            $values[] = $p['value'];
        }
        $low = floor(min($values) - .5);
        $high = ceil(max($values) + .5);
        $x = static fn(int $t): float => 45 + 620 * ($t - $f['start']) / ($f['end'] - $f['start']);
        $y = static fn(float $v): float => 200 - 175 * ($v - $low) / ($high - $low);
        $svg = '<svg viewBox="0 0 700 245" style="width:100%;max-width:1000px" role="img" aria-label="' . self::e($label) . ' Vorhersage und Messung">';
        for ($i = 0; $i <= 4; ++$i) {
            $v = $low + ($high - $low) * $i / 4;
            $yy = $y($v);
            $svg .= '<path d="M45 ' . $yy . 'H665" stroke="#8884"/><text x="40" y="' . ($yy + 4) . '" text-anchor="end" fill="currentColor" font-size="12">' . number_format($v, 1, ',', '') . '</text>';
        }
        $pred = [];
        $path = '';
        $dots = '';
        $pen = 'M';
        foreach ($f['points'] as $i => $p) {
            $xx = $x($p['time']);
            $pred[] = $xx . ',' . $y($p[$room]);
            $actual = $r['actual'][$room][$p['time']]['value'] ?? null;
            if ($actual === null) {
                $pen = 'M';
            } else {
                $path .= $pen . $xx . ' ' . $y($actual) . ' ';
                $pen = 'L';
                $dots .= '<circle cx="' . $xx . '" cy="' . $y($actual) . '" r="2.5" fill="#d17628"/>';
            }
        }
        for ($i = 0; $i <= 4; ++$i) {
            $t = (int) round($f['start'] + ($f['end'] - $f['start']) * $i / 4);
            $svg .= '<text x="' . $x($t) . '" y="227" text-anchor="middle" fill="currentColor" font-size="11">' . self::time($t) . '</text>';
        }

        return $svg . '<polyline points="' . implode(' ', $pred) . '" stroke="#3981d5" stroke-width="2.5" stroke-dasharray="6 4" fill="none"/><path d="' . $path . '" stroke="#d17628" stroke-width="2.5" fill="none"/>' . $dots . '</svg>';
    }
}
