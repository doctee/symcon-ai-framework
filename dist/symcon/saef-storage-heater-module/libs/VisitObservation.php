<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/** Separate prospective observations from the first confirmed spontaneous arrival edge. */
trait VisitObservation
{
    private function createVisits(): void
    {
        $this->RegisterAttributeString('VisitJournal', '{"schema":1,"discarded":0,"records":[],"cursor":null,"active":null}');
        $this->RegisterVariableString('VisitStatus', 'Spontanbesuch – Status', '', 310);
        $this->RegisterVariableString('VisitComparison', 'Spontanbesuche – Temperaturen und Nachlauf', '~HTMLBox', 320);
    }

    public function GetVisitJournalJson(): string
    {
        return Forecast::encode(Warming::journal($this->ReadAttributeString('VisitJournal')));
    }

    private function visitProjection(array $plan, int $start, int $end, array $visit): array
    {
        $p = HeatingPlan::project($plan, ['start' => $start, 'end' => $end], $start);
        $stop = $visit['assumedFanStopAt'];
        if ($stop !== null && $stop < $end) {
            $first = $p['segments'][0];
            $after = $first;
            $after['from'] = max($start, $stop);
            $after['fanPermitted'] = false;
            $after['reduced'] = true;
            $first['to'] = $after['from'];
            $p['segments'] = $first['to'] > $start ? [$first, $after] : [$after];
            $p['fanRuntimeHours']['max'] = $first['fanPermitted'] ? ($first['to'] - $start) / 3600 : 0.0;
        }
        return $p;
    }

    private function visitTransition(array &$j, array $plan, int $now): void
    {
        $v = $plan['inputs']['values'];
        $changed = $plan['inputs']['changedAt'];
        $prior = $j['cursor'];
        $selected = $v['arrival'] > 0 || $v['storageRequested'] || $v['fanRequested'];
        $permission = $v['regulatorActive'] && $v['regulatorEnabled'];
        $j['cursor'] = ['presence' => $v['presence'], 'changed' => $changed['presence'],
            'permission' => $permission, 'selected' => $selected, 'winter' => $v['winter']];
        if ($prior === null || !$v['winter'] || $selected || !$prior['winter']) {
            $j['active'] = null;
            return;
        }
        $stage = null;
        $edge = $prior['presence'] !== $v['presence'];
        if ($edge && $v['presence']) {
            $j['active'] = null;
            if (!$prior['selected'] && $changed['presence'] <= $now && $changed['presence'] >= $now - 600) {
                $j['active'] = ['arrivalAt' => $changed['presence'], 'departureAt' => null];
                $stage = 'arrival';
            }
        } elseif ($j['active'] !== null) {
            if ($edge) {
                $j['active']['departureAt'] = $changed['presence'];
                $stage = 'departure';
            } elseif ($prior['changed'] !== $changed['presence']) {
                // An unobserved absence/return may separate two visits.
                $j['active'] = null;
                return;
            } elseif ($prior['permission'] !== $permission) {
                $stage = 'permission';
            }
        }
        if ($stage !== null) {
            $eventAt = $stage === 'permission' ? max($changed['regulatorEnabled'], $changed['regulatorActive']) : $changed['presence'];
            $j['active']['pending'] = ['stage' => $stage, 'eventAt' => $eventAt];
            $last = array_key_last($j['records']);
            if ($last !== null && $j['records'][$last]['forecast']['visit']['arrivalAt'] === $j['active']['arrivalAt'] && !isset($j['records'][$last]['validUntil'])) {
                $j['records'][$last]['validUntil'] = $eventAt;
            }
        }
    }

    private function observeVisits(array $config, int $now): void
    {
        try {
            $raw = $this->ReadAttributeString('VisitJournal');
            $j = Warming::journal($raw);
            $b = $this->planConfiguration($config);
            if ($b === null || Forecast::decode($this->ReadPropertyString('WarmingModelJSON')) === []) {
                $this->SetValue('VisitStatus', 'Aufwärmmodell oder Vorwahl fehlt');
                return;
            }
            $plan = $this->readPlan($b, $config, $now);
            $this->visitTransition($j, $plan, $now);
            $status = $j['active'] === null ? 'Bereit für spontane Ankunft' : 'Spontanbesuch erfasst';
            $pending = $j['active']['pending'] ?? null;
            if ($pending !== null) {
                try {
                    if ($pending['eventAt'] > $now || $now - $pending['eventAt'] > 600) {
                        throw new RuntimeException('Zeitfenster für diesen Prognosestand verpasst');
                    }
                    $c = $this->warmingConfiguration($config);
                    $departure = $j['active']['departureAt'];
                    $tail = $c['options']['visitRunOnMinutes'] ?? 240;
                    if (!is_int($tail) || $tail < 0 || $tail > 720) {
                        throw new RuntimeException('Nachlaufannahme außerhalb 0–720 Minuten');
                    }
                    $visit = $pending + ['arrivalAt' => $j['active']['arrivalAt'], 'departureAt' => $departure,
                        'assumedFanStopAt' => $departure !== null && $j['cursor']['permission'] ? $departure + $tail * 60 : null,
                        'horizonSeconds' => $departure === null ? 12 * 3600 : max(2 * 3600, $departure + ($tail + 120) * 60 - $now)];
                    $r = $this->issueWarming($c, $config, $plan, $now, $visit);
                    if ($plan['hash'] !== $this->readPlan($b, $config, $this->now())['hash'] || $c['hash'] !== $this->warmingConfiguration($config)['hash']) {
                        throw new RuntimeException('Betriebsstand hat sich während der Berechnung geändert');
                    }
                    $r['forecast']['issuedAt'] = $this->now();
                    if ($r['forecast']['issuedAt'] >= $r['forecast']['end'] || $r['forecast']['issuedAt'] > $pending['eventAt'] + 600) {
                        throw new RuntimeException('Vorhersage konnte nicht rechtzeitig gespeichert werden');
                    }
                    $r['key'] = $r['forecastHash'] = SAEF_CreateConfigurationHash($r['forecast']);
                    $j['records'][] = $r;
                    unset($j['active']['pending']);
                    if ($departure !== null && !$j['cursor']['permission']) {
                        $j['active'] = null;
                    }
                    $status = 'Besuchsprognose gespeichert';
                } catch (Throwable $e) {
                    $status = 'Besuchsprognose ausstehend: ' . substr($e->getMessage(), 0, 180);
                }
            }
            $open = array_keys(array_filter($j['records'], static fn(array $r): bool => $r['settledAt'] === null && $r['checkedAt'] <= $now - 300));
            usort($open, static fn(int $a, int $b): int => $j['records'][$a]['checkedAt'] <=> $j['records'][$b]['checkedAt']);
            foreach (array_slice($open, 0, 2) as $index) {
                $record = $this->measureWarming($j['records'][$index], $now);
                $j['records'][$index] = $this->visitOutcome($record, $now);
            }
            $j = Warming::retain($j);
            $encoded = Forecast::encode($j);
            if ($encoded !== $raw) {
                Warming::journal($encoded);
                $this->WriteAttributeString('VisitJournal', $encoded);
                $this->SetValue('VisitComparison', $this->visitView($j));
            }
            $this->SetValue('VisitStatus', $status);
        } catch (Throwable $e) {
            $this->SetValue('VisitStatus', 'Besuchsvergleich prüfen: ' . substr($e->getMessage(), 0, 180));
        }
    }

    /** Compare the part of this scenario that was actually in force, excluding overlapping revisions. */
    private function visitOutcome(array $r, int $now): array
    {
        $f = $r['forecast'];
        $end = min($r['validUntil'] ?? $f['end'], $f['end']);
        $view = $r;
        $view['forecast']['points'] = array_values(array_filter($f['points'], static fn(array $p): bool =>
            $p['time'] === $f['start'] || ($p['time'] > $f['issuedAt'] && $p['time'] < $end) || ($p['time'] === $f['end'] && $end === $f['end'] && $p['time'] > $f['issuedAt'])));
        $r['visitMetrics'] = ['until' => $end];
        foreach (['gallery', 'sofa'] as $room) {
            $r['visitMetrics'][$room] = Warming::metrics($view, $room);
        }
        $until = min($end, $now);
        if ($until > $f['issuedAt'] + 1) {
            try {
                $archive = new ArchiveReader($r['bindings']['archive'], $r['historyStart']);
                $r['visitMetrics']['fanHours'] = $archive->duty($r['bindings']['fan'], $f['issuedAt'], $until)['hours'];
                $r['visitMetrics']['fanMeasuredUntil'] = $until;
                unset($r['measurementErrors']['visitFan']);
            } catch (Throwable $e) {
                $r['measurementErrors']['visitFan'] = substr($e->getMessage(), 0, 160);
            }
        }
        return $r;
    }

    private function visitView(array $j): string
    {
        $html = '<div><h3>Spontanbesuche</h3><p>Gestrichelt: Vorhersage. Orange: archivierte Temperatur. Mehrere Stände derselben Ankunft gehören zu einem Besuch.</p>';
        $labels = ['arrival' => 'Ankunft', 'departure' => 'Abreise', 'permission' => 'Lüfterfreigabe geändert'];
        $time = static fn(int $t): string => (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i');
        foreach (array_reverse(array_slice($j['records'], -8)) as $i => $r) {
            $f = $r['forecast'];
            $v = $f['visit'];
            $html .= '<details' . ($i === 0 ? ' open' : '') . '><summary>Besuch ' . $time($v['arrivalAt']) . ' · ' . $labels[$v['stage']] . ' · Prognose vom ' . $time($f['issuedAt']) . '</summary>';
            if ($v['assumedFanStopAt'] !== null) {
                $html .= '<p>Angenommenes Ende der Lüfterfreigabe ' . $time($v['assumedFanStopAt']) . '.</p>';
            }
            $last = end($f['points']);
            $hours = $r['operationActual']['fanHours'] ?? null;
            $html .= '<p>Lüfterlauf bis ' . $time($f['end']) . ' prognostiziert ' . number_format($last['fanHours'], 2, ',', '') . ' h';
            if ($hours !== null) {
                $html .= ', gemessen bis ' . $time($r['operationActual']['until']) . ' ' . number_format($hours, 2, ',', '') . ' h';
            }
            $html .= '.</p>';
            foreach (['gallery' => 'Galerie', 'sofa' => 'Wohnzimmer'] as $room => $label) {
                $m = $r['visitMetrics'][$room] ?? Warming::metrics($r, $room);
                $html .= '<p>' . $label . ' · ' . $m['n'] . '/' . $m['expected'] . ' Messpunkte';
                if ($m['mae'] !== null) {
                    $html .= ' im gültigen Abschnitt · mittlerer absoluter Fehler ' . number_format($m['mae'], 2, ',', '') . ' K';
                }
                $html .= '</p>' . WarmingView::chart($r, $room, $label);
            }
            $html .= '</details>';
        }
        return $html . '</div>';
    }
}
