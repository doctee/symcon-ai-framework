<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;

/** Self-contained display: no scripts, network calls, input controls or device actions. */
final class CoolingView
{
    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function temperature(?float $v): string
    {
        return $v === null ? '—' : number_format($v, 2, ',', '') . ' °C';
    }

    private static function local(int $t, string $format = 'H:i'): string
    {
        return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Berlin'))->format($format);
    }

    public static function render(array $records, array $current, string $status): string
    {
        $html = '<div class="shf-cooling"><style>.shf-cooling{font:15px/1.5 system-ui,sans-serif;padding:12px;color:inherit}.shf-cooling h3{margin:8px 0}.shf-cooling .cards{display:flex;flex-wrap:wrap;gap:12px}.shf-cooling .card{border:1px solid #8886;border-radius:10px;padding:12px;min-width:120px}.shf-cooling .small{font-size:12px;opacity:.8}.shf-cooling .note{padding:10px;border-left:4px solid #d68b32;background:#d68b3212}.shf-cooling svg{width:100%;height:auto;min-width:280px}.shf-cooling table{border-collapse:collapse;width:100%;font-size:13px}.shf-cooling th,.shf-cooling td{padding:6px;text-align:right;border-bottom:1px solid #8884;white-space:nowrap}.shf-cooling .scroll{overflow:auto}.shf-cooling details{margin:12px 0}.shf-cooling summary{cursor:pointer;font-weight:600}</style>';
        $html .= '<h3>Auskühlmodell · Prognose und Messung</h3><p>' . self::escape($status) . '</p><div class="cards">';
        foreach (['gallery' => 'Galerie jetzt', 'sofa' => 'Wohnzimmer jetzt', 'outside' => 'Froggit außen jetzt'] as $key => $label) {
            $v = $current[$key] ?? ['value' => null, 'updatedAt' => 0];
            $html .= '<div class="card">' . $label . '<br><b>' . self::temperature($v['value']) . '</b><div class="small">';
            $html .= $v['value'] === null ? 'Keine frische Messung' : 'Aktualisiert ' . self::local($v['updatedAt'], 'd.m. H:i');
            $html .= '</div></div>';
        }
        $html .= '</div><p class="note"><b>Vergleich ohne zusätzliche Wärmezufuhr.</b> Heizung, Speicherrestwärme, Kamin und Sonne sind nicht eingerechnet. Bisher an heizungsfreien Nächten geprüft; keine Komfort- oder Frostschutzprognose. Die Heizungsregelung wird nicht beeinflusst.</p>';
        if ($records === []) {
            $html .= '<p>Noch keine gespeicherte Temperaturvorhersage. Die erste Kurve entsteht im nächsten Ausgabezeitfenster ab 23:00 Uhr. Vergangene Vorhersagen werden nicht nachträglich erzeugt.</p>';
        }
        foreach (array_reverse(array_slice($records, -7, null, true), true) as $date => $r) {
            $html .= '<details' . ($date === array_key_last($records) ? ' open' : '') . '><summary>Nacht ' . self::escape($date) . '</summary>';
            $f = $r['forecast'];
            $html .= '<p class="small">Fest gespeichert: ' . self::local($f['issuedAt'], 'd.m.Y H:i:s T') . ' · bis ' . self::local($f['end'], 'H:i T') . '. Wetter: damalige Open-Meteo-Vorhersage.</p>';
            $active = [];
            foreach (['enabled' => 'Heizungsfreigabe', 'fan' => 'Ventilator', 'auxiliary' => 'Zusatzheizung', 'floor' => 'Bad-Fußbodenheizung', 'presence' => 'Anwesenheit'] as $key => $label) {
                if (($f['context'][$key] ?? false) === true) {
                    $active[] = $label;
                }
            }
            $html .= '<p class="small">Beim Start aktiv: ' . ($active === [] ? 'keine der erfassten Quellen; Restwärme und weitere Einflüsse unbekannt' : implode(', ', $active)) . '.</p>';
            if (($f['warnings'] ?? []) !== []) {
                $html .= '<p class="note">Außerhalb des kalibrierten Bereichs: ' . self::escape(implode(', ', $f['warnings'])) . '.</p>';
            }
            foreach (['gallery' => 'Galerie', 'sofa' => 'Wohnzimmer'] as $room => $label) {
                $html .= '<h3>' . $label . '</h3>' . self::chart($r, $room, $label);
            }
            $count = count($r['actual']['gallery'] ?? []) + count($r['actual']['sofa'] ?? []);
            $html .= '<p class="small">' . (($r['settledAt'] ?? null) === null ? 'Messung läuft / wartet auf Archivwerte' : 'Messung abgeschlossen') . ': ' . $count . ' von ' . (2 * count($f['points'])) . ' Temperaturpunkten vorhanden.</p>';
            $html .= '<div class="scroll"><table><tr><th>Zeit</th><th>Galerie Prognose</th><th>Galerie Messung</th><th>Wohnzimmer Prognose</th><th>Wohnzimmer Messung</th></tr>';
            foreach ($f['points'] as $p) {
                $html .= '<tr><td>' . self::local($p['time']) . '</td>';
                foreach (['gallery', 'sofa'] as $room) {
                    $html .= '<td>' . self::temperature($p[$room]) . '</td><td>' . self::temperature($r['actual'][$room][$p['time']]['value'] ?? null) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table></div><p class="small">Blau gestrichelt: gespeicherte Prognose. Orange: Messung. Messwerte am Zeitraster sind zwischen archivierten Änderungen interpoliert (maximal 6 Stunden Abstand). Fehlende Werte bleiben offen und werden höchstens bis 6 Stunden nach Nachtende ergänzt. Lücken sind keine Nullwerte.</p>';
            if (($r['measurementErrors'] ?? []) !== []) {
                $html .= '<p class="note">Messdaten teilweise nicht verfügbar: ' . self::escape(implode('; ', $r['measurementErrors'])) . '</p>';
            }
            $html .= '</details>';
        }
        return $html . '<p class="small">Anzeige: letzte 7 gespeicherte Nächte · Journal: maximal 120 Nächte · Ortszeit Europe/Berlin.</p></div>';
    }

    private static function chart(array $r, string $room, string $label): string
    {
        $points = $r['forecast']['points'];
        $values = array_column($points, $room);
        foreach ($r['actual'][$room] ?? [] as $p) {
            $values[] = $p['value'];
        }
        $low = floor((min($values) - .2) * 2) / 2;
        $high = ceil((max($values) + .2) * 2) / 2;
        $from = $points[0]['time'];
        $end = $r['forecast']['end'];
        $x = static fn(int $t): float => 55 + ($t - $from) / ($end - $from) * 620;
        $y = static fn(float $v): float => 205 - ($v - $low) / ($high - $low) * 180;
        $svg = '<svg viewBox="0 0 700 250" role="img" aria-label="' . self::escape($label . ' – gespeicherte Prognose und Messwerte') . '">';
        for ($i = 0; $i <= 4; ++$i) {
            $v = $low + ($high - $low) * $i / 4;
            $yy = $y($v);
            $svg .= '<path d="M55 ' . $yy . 'H675" stroke="#8884"/><text x="48" y="' . ($yy + 4) . '" fill="currentColor" font-size="12" text-anchor="end">' . number_format($v, 1, ',', '') . '</text>';
        }
        $forecast = [];
        $actualPath = '';
        $pen = 'M';
        $dots = '';
        foreach ($points as $p) {
            $xx = $x($p['time']);
            $forecast[] = $xx . ',' . $y($p[$room]);
            $v = $r['actual'][$room][$p['time']]['value'] ?? null;
            if ($v === null) {
                $pen = 'M';
            } else {
                $actualPath .= $pen . $xx . ' ' . $y($v) . ' ';
                $pen = 'L';
                $dots .= '<circle cx="' . $xx . '" cy="' . $y($v) . '" r="3" fill="#d17628"/>';
            }
            $svg .= '<text x="' . $xx . '" y="231" fill="currentColor" font-size="11" text-anchor="middle">' . self::local($p['time']) . '</text>';
        }
        return $svg . '<text x="10" y="16" fill="currentColor" font-size="12">°C</text><polyline points="' . implode(' ', $forecast) . '" fill="none" stroke="#3981d5" stroke-width="2.5" stroke-dasharray="6 4"/><path d="' . $actualPath . '" fill="none" stroke="#d17628" stroke-width="2.5"/>' . $dots . '</svg>';
    }
}
