<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;
use Throwable;

/** Independent prospective evidence; reuses the existing observer semaphore and timer. */
trait FanComparisonObservation
{
    private function createFanComparison(): void
    {
        $this->RegisterPropertyString('FanComparisonModelJSON', '{}');
        $this->RegisterAttributeString('FanComparisonJournal', '{"schema":1,"discarded":0,"records":[]}');
        $this->RegisterVariableString('FanComparisonStatus', 'Ventilator-Vergleich – Status', '', 250);
        $this->RegisterVariableString('FanComparison', 'Nachtladung – Ventilator-Vergleich', '~HTMLBox', 260);
    }

    public function GetFanComparisonJournalJson(): string
    {
        return Forecast::encode(FanComparison::journal($this->ReadAttributeString('FanComparisonJournal')));
    }

    private function configureFanComparison(array $config): void
    {
        try {
            $c = $this->fanComparisonConfiguration($config);
            $this->fanComparisonDisplay(FanComparison::journal($this->ReadAttributeString('FanComparisonJournal')), $c === null
                ? 'Vergleich nicht konfiguriert' : 'Bereit – wartet auf zukünftige Aufwärmvorwahl');
        } catch (Throwable $e) {
            $this->fanComparisonDisplay([], 'Ventilator-Vergleich prüfen: ' . substr($e->getMessage(), 0, 220));
        }
    }

    private function fanComparisonConfiguration(array $config): ?array
    {
        $models = Forecast::decode($this->ReadPropertyString('FanComparisonModelJSON'));
        if ($models === []) {
            return null;
        }
        FanComparison::models($models);
        $c = $this->warmingConfiguration($config);
        if ($c === null) {
            throw new RuntimeException('Ventilator-Vergleich benötigt das Aufwärmmodell.');
        }
        $c['comparisonModels'] = $models;
        $c['comparisonMethodVersion'] = 2;
        $c['hash'] = SAEF_CreateConfigurationHash($c);
        return $c;
    }

    private function observeFanComparison(array $config, int $now): void
    {
        $j = [];
        try {
            $j = FanComparison::journal($this->ReadAttributeString('FanComparisonJournal'));
            $pending = array_keys(array_filter($j['records'], static fn(array $r): bool => $r['settledAt'] === null));
            usort($pending, static fn(int $a, int $b): int => ($j['records'][$a]['checkedAt'] ?? 0) <=> ($j['records'][$b]['checkedAt'] ?? 0));
            foreach (array_slice($pending, 0, 2) as $i) {
                $j['records'][$i] = $this->measureFanComparison($j['records'][$i], $now);
            }
            $this->saveFanComparison($j);
            $c = $this->fanComparisonConfiguration($config);
            $b = $this->planConfiguration($config);
            if ($c === null || $b === null) {
                $this->fanComparisonDisplay($j, 'Nicht konfiguriert; gespeicherte Vergleiche bleiben erhalten');
                return;
            }
            $plan = $this->readPlan($b, $config, $now);
            $v = $plan['inputs']['values'];
            if ($v['arrival'] <= $now || !$v['winter'] || $v['presence']) {
                $this->fanComparisonDisplay($j, 'Bereit – wartet auf zukünftige Aufwärmvorwahl');
                return;
            }
            // Never claim a fresh pre-phase forecast after either warming stage started.
            $projection = HeatingPlan::project($plan, ['start' => $now, 'end' => min($v['arrival'], $now + 48 * 3600)], $now);
            if ($v['storageRequested'] || $v['fanRequested'] || $projection['storageStart'] === null) {
                $this->fanComparisonDisplay($j, 'Aufwärmstart bereits erfolgt oder unbekannt; keine rückwirkende Vergleichsprognose');
                return;
            }
            $last = $j['records'] === [] ? null : end($j['records']);
            if ($last !== null && $last['configurationHash'] === $c['hash'] && $last['forecast']['plan']['hash'] === $plan['hash'] && $last['forecast']['end'] > $now) {
                $this->fanComparisonDisplay($j, 'Vorabvergleich gespeichert; Nachtmessungen werden automatisch ergänzt');
                return;
            }
            // Fresh inputs at this issue, not an old warming curve re-labelled as new.
            $r = $this->issueWarming($c, $config, $plan, $now);
            $r['forecast']['fanComparison'] = FanComparison::build($r['forecast'], $c['comparisonModels'], $now);
            if (
                $plan['hash'] !== $this->readPlan($b, $config, $now)['hash']
                || $c['hash'] !== $this->fanComparisonConfiguration($config)['hash']
            ) {
                throw new RuntimeException('Plan oder Modell während Vergleich geändert; kein Stand gespeichert.');
            }
            $issuedAt = $this->now();
            if ($issuedAt >= $r['forecast']['phaseStart']) {
                throw new RuntimeException('Aufwärmphase während Berechnung gestartet; kein Vorabvergleich.');
            }
            foreach ($r['forecast']['fanComparison']['nights'] as $n) {
                if ($issuedAt >= $n['cycle']['start']) {
                    throw new RuntimeException('Ladefenster während Berechnung gestartet; kein Vorabvergleich.');
                }
            }
            $r['forecast']['issuedAt'] = $issuedAt;
            $r['forecast']['beforePhase'] = true;
            $r['forecastHash'] = SAEF_CreateConfigurationHash($r['forecast']);
            $r['key'] = $r['forecastHash'];
            $r['nightActual'] = [];
            if ($j['records'] !== []) {
                $j['records'][array_key_last($j['records'])]['supersededAt'] = $issuedAt;
            }
            $j['records'][] = $r;
            $j = Warming::retain($j);
            $this->saveFanComparison($j);
            $this->fanComparisonDisplay($j, 'Neue Vergleichsprognose vor Aufwärmbeginn fest gespeichert');
        } catch (Throwable $e) {
            $this->fanComparisonDisplay($j, 'Ventilator-Vergleich: ' . substr($e->getMessage(), 0, 220));
        }
    }

    private function measureFanComparison(array $r, int $now): array
    {
        $r['checkedAt'] = $now;
        $a = new ArchiveReader($r['bindings']['archive'], $r['historyStart']);
        $allSettled = true;
        foreach ($r['forecast']['fanComparison']['nights'] as $date => $night) {
            if (isset($r['nightActual'][$date])) {
                continue;
            }
            $cycle = $night['cycle'];
            if ($now < $cycle['end'] + 3600) {
                $allSettled = false;
                continue;
            }
            if ($now > $cycle['end'] + 7 * 86400) {
                $r['nightActual'][$date] = ['status' => 'expired_missing_data'];
                continue;
            }
            try {
                $energy = $a->hours($r['bindings']['energy'], $cycle['start'], $cycle['end'], true);
                $power = $a->hours($r['bindings']['power'], $cycle['start'], $cycle['end']);
                $kwh = array_sum($energy);
                $integral = array_sum($power);
                if ($kwh < 0 || abs($kwh - $integral) > max(1, .1 * $kwh)) {
                    throw new RuntimeException('Zähler und Leistungsintegral nicht plausibel.');
                }
                $states = [];
                foreach (['fan', 'enabled', 'reduced', 'auxiliary', 'presence'] as $key) {
                    if (isset($r['bindings'][$key])) {
                        $states[$key] = $a->duty($r['bindings'][$key], $cycle['start'], $cycle['end']);
                    }
                }
                $warnings = [];
                $hours = ($cycle['end'] - $cycle['start']) / 3600;
                $reductionPlan = $this->measureReductionPlan($a, $r['bindings']['reduced'], $night);
                if ($states['enabled']['hours'] != $hours || $reductionPlan['mismatchSeconds'] > 60) {
                    $warnings[] = 'actual_charge_plan_deviated';
                }
                if (($states['auxiliary']['hours'] ?? 0) > 0) {
                    $warnings[] = 'auxiliary_heat_present';
                }
                if (($states['presence']['hours'] ?? 0) > 0) {
                    $warnings[] = 'presence_during_warming_night';
                }
                $r['nightActual'][$date] = ['status' => 'complete', 'settledAt' => $now,
                    'from' => $cycle['start'], 'until' => $cycle['end'], 'kwh' => $kwh,
                    'powerIntegralKwh' => $integral, 'energyHours' => $energy, 'states' => $states,
                    'reductionPlan' => $reductionPlan,
                    'fanHoursError' => $night['features']['night_fan_hours'] - $states['fan']['hours'],
                    'errors' => FanComparison::errors($night, $kwh), 'warnings' => $warnings,
                    'fireplaceAtComparison' => FireplaceLog::classify(FireplaceLog::read($this->ReadAttributeString('FireplaceLog')), $cycle, $now)];
                unset($r['measurementErrors'][$date]);
            } catch (Throwable $e) {
                $r['measurementErrors'][$date] = substr($e->getMessage(), 0, 200);
                $allSettled = false;
            }
        }
        if ($allSettled) {
            $r['settledAt'] = $now;
        }
        return $r;
    }

    /** Compare transition timing as well as duration; old frozen records retain their constant plan. */
    private function measureReductionPlan(ArchiveReader $a, int $id, array $night): array
    {
        $segments = $night['chargePlan']['segments'] ?? [[
            'from' => $night['cycle']['start'], 'to' => $night['cycle']['end'], 'reduced' => $night['expectedReduced'],
        ]];
        $measured = [];
        $mismatch = 0.0;
        foreach ($segments as $s) {
            $actual = $a->duty($id, $s['from'], $s['to'])['hours'];
            $expected = $s['reduced'] ? ($s['to'] - $s['from']) / 3600 : 0.0;
            $seconds = abs($actual - $expected) * 3600;
            $mismatch += $seconds;
            $measured[] = ['from' => $s['from'], 'to' => $s['to'], 'expectedReducedHours' => $expected,
                'actualReducedHours' => $actual, 'mismatchSeconds' => $seconds];
        }
        return ['segments' => $measured, 'mismatchSeconds' => $mismatch, 'toleranceSeconds' => 60];
    }

    private function saveFanComparison(array $j): void
    {
        $json = Forecast::encode($j);
        FanComparison::journal($json);
        if ($json !== $this->ReadAttributeString('FanComparisonJournal')) {
            $this->WriteAttributeString('FanComparisonJournal', $json);
        }
    }

    private function fanComparisonDisplay(array $j, string $status): void
    {
        $this->SetValue('FanComparisonStatus', $status);
        $this->SetValue('FanComparison', FanComparisonView::render($j, $status));
    }
}
