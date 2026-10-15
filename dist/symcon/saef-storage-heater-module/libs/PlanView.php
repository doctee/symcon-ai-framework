<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;

final class PlanView
{
    public static function status(string $status): string
    {
        return match ($status) {
            'planned_state_baseline' => 'Energieabschätzung mit geplanter Ladefreigabe und Absenkung',
            'updated_whole_night_baseline' => 'Aktualisierte Ganznacht-Abschätzung; keine Restenergieprognose',
            'fan_effect_not_calibrated' => 'Ventilatorbetrieb berücksichtigt: kWh-Einfluss noch nicht kalibriert',
            'mixed_night_not_calibrated' => 'Wechsel während der Ladenacht: noch keine belastbare Energieabschätzung',
            'conditional_arrival' => 'Weiterer Betrieb hängt von der tatsächlichen Ankunft ab',
            'heating_disabled' => 'Keine Ladefreigabe geplant; keine Heizenergieprognose',
            'no_precharge_baseline' => 'Keine vor Ladebeginn gespeicherte Berechnungsgrundlage',
            'data_error' => 'Prognose wartet auf gültige Daten',
            default => 'Plan gespeichert; nächste Energieabschätzung ab 21:45 Uhr',
        };
    }

    private static function time(?int $t): string
    {
        return $t === null || $t <= 0 ? '—' : (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i');
    }

    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function render(array $journal, string $status): string
    {
        $html = '<div style="font:15px system-ui;line-height:1.5;padding:12px"><strong>Heizungsvorwahl · Prognose</strong><p>'
            . self::escape($status) . '</p>';
        if (($journal['revisions'] ?? []) === []) {
            return $html . '</div>';
        }
        $r = end($journal['revisions']);
        $p = $r['projection'];
        $v = $r['plan']['inputs']['values'];
        $e = $r['estimate'];
        $rows = [
            'Plan beobachtet' => self::time($r['plan']['observedAt']),
            'Prognosestand' => '#' . $r['sequence'] . ' · ' . self::time($r['issuedAt']),
            'Ankunft vorgewählt' => self::time($p['arrival']),
            'Speicher-Aufwärmen' => $v['storageRequested'] ? 'bereits aktiviert' : self::time($p['storageStart']),
            'Ventilator-Aufwärmen' => $v['fanRequested'] ? 'bereits aktiviert' : self::time($p['fanStart']),
            'Galerie-Sollwert' => number_format($p['setpoint'], 1, ',', '') . ' °C' . ($v['setback'] ? ' · Thermostat-Absenkung aktiv' : ''),
            'Restliches Modellfenster' => self::time($p['from']) . ' – ' . self::time($p['to']),
            'Mögliche Ventilatorlaufzeit darin' => '0 – ' . number_format($p['fanRuntimeHours']['max'], 1, ',', '') . ' Stunden',
        ];
        if (($e['baselineKwh'] ?? null) !== null) {
            $rows['Vergleichsbasis ohne zukünftigen Ventilatoreffekt'] = number_format($e['baselineKwh'], 2, ',', '') . ' kWh · erstellt ' . self::time($e['baselineIssuedAt']);
        }
        if (($e['kwh'] ?? null) !== null) {
            $rows['Abschätzung für die ganze Nacht'] = number_format($e['kwh'], 2, ',', '') . ' kWh';
        }
        if (isset($r['past']['kwh'])) {
            $rows['Davon tatsächlich gemessen bis ' . self::time($r['past']['until'])] = number_format($r['past']['kwh'], 2, ',', '') . ' kWh';
        }
        $html .= '<dl>';
        foreach ($rows as $label => $value) {
            $html .= '<dt style="opacity:.7;margin-top:7px">' . self::escape($label) . '</dt><dd style="margin:0">' . self::escape($value) . '</dd>';
        }
        $html .= '</dl><p>Freigabe bedeutet keine Dauerlaufzeit: Der Galerieregler schaltet nach Temperatur. '
            . 'Für Ventilator-Wärmeabgabe, Restenergie und Aufwärmtemperaturen fehlt noch eine belastbare Kalibrierung. '
            . 'Die ursprüngliche Nachtprognose bleibt als Vergleich erhalten.</p>';
        $labels = [
            'reset_conditional_on_no_arrival' => 'Rücksetzung ist hier unter der Annahme dargestellt, dass bis dahin keine Anwesenheit gemeldet wird.',
            'storage_start_missing_or_expired' => 'Kein gültiger zukünftiger Speicher-Starttimer gefunden.',
            'fan_start_missing_or_expired' => 'Kein gültiger zukünftiger Ventilator-Starttimer gefunden.',
        ];
        foreach ($p['warnings'] as $warning) {
            if (isset($labels[$warning])) {
                $html .= '<p>' . self::escape($labels[$warning]) . '</p>';
            }
        }
        if (isset($e['error'])) {
            $html .= '<p>' . self::escape($e['error']) . '</p>';
        }
        $html .= '<details><summary>Letzte Plan- und Prognosestände</summary><ul>';
        foreach (array_slice(array_reverse($journal['revisions']), 0, 12) as $entry) {
            $html .= '<li>#' . $entry['sequence'] . ' · ' . self::time($entry['issuedAt']) . ' · '
                . self::escape(self::status($entry['estimate']['status'])) . '</li>';
        }
        return $html . '</ul><p>Je höchstens 256 Planstände und Prognoseversionen; zusätzlich 3 MiB Gesamtgrenze. '
            . 'Alte Einträge durch Begrenzung entfernt: ' . $journal['discarded'] . '.</p></details></div>';
    }
}
