<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/** Independent prospective evidence; reuses the existing observer semaphore and timer. */
trait ProfileComparisonObservation
{
    private function createProfileComparison(): void
    {
        $this->RegisterPropertyString('ChargeProfileJSON', '{}');
        $this->RegisterAttributeString('ProfileComparisonJournal', '{"schema":1,"discarded":0,"records":[]}');
        $this->RegisterVariableString('ProfileComparisonStatus', 'Ladeprofil-Vergleich – Status', '', 270);
        $this->RegisterVariableString('ProfileComparison', 'Temperaturen – Ladeprofil-Vergleich', '~HTMLBox', 280);
    }

    public function GetProfileComparisonJournalJson(): string
    {
        return Forecast::encode(ChargeProfile::journal($this->ReadAttributeString('ProfileComparisonJournal')));
    }

    private function configureProfileComparison(array $config): void
    {
        try {
            $c = $this->profileComparisonConfiguration($config);
            $b = $this->planConfiguration($config);
            $now = $this->now();
            $context = $c === null || $b === null ? [] : ['plan' => $this->readPlan($b, $config, $now), 'now' => $now, 'configurationHash' => $c['hash']];
            $this->profileComparisonDisplay(ChargeProfile::journal($this->ReadAttributeString('ProfileComparisonJournal')), $c === null
                ? 'Vergleich nicht konfiguriert' : 'Anzeige aktualisiert; automatische Prüfung des Vorabvergleichs folgt', $context);
        } catch (Throwable $e) {
            $this->profileComparisonDisplay([], 'Ladeprofil-Vergleich prüfen: ' . substr($e->getMessage(), 0, 220));
        }
    }

    private function profileComparisonConfiguration(array $config): ?array
    {
        $models = Forecast::decode($this->ReadPropertyString('ChargeProfileJSON'));
        if ($models === []) {
            return null;
        }
        ChargeProfile::validate($models);
        $c = $this->warmingConfiguration($config);
        if ($c === null) {
            throw new RuntimeException('Ladeprofil-Vergleich benötigt das Aufwärmmodell.');
        }
        $c['chargeProfile'] = $models;
        $c['comparisonMethodVersion'] = 1;
        $c['hash'] = SAEF_CreateConfigurationHash($c);
        return $c;
    }

    private function observeProfileComparison(array $config, int $now): void
    {
        $j = [];
        $context = [];
        try {
            $j = ChargeProfile::journal($this->ReadAttributeString('ProfileComparisonJournal'));
            $pending = array_keys(array_filter($j['records'], static fn(array $r): bool => $r['settledAt'] === null));
            usort($pending, static fn(int $a, int $b): int => ($j['records'][$a]['checkedAt'] ?? 0) <=> ($j['records'][$b]['checkedAt'] ?? 0));
            foreach (array_slice($pending, 0, 2) as $i) {
                $j['records'][$i] = $this->measureWarming($j['records'][$i], $now);
                $j['records'][$i]['profileMetrics'] = ChargeProfile::metrics($j['records'][$i]);
            }
            $this->saveProfileComparison($j);
            $c = $this->profileComparisonConfiguration($config);
            $b = $this->planConfiguration($config);
            if ($c === null || $b === null) {
                $this->profileComparisonDisplay($j, 'Nicht konfiguriert; gespeicherte Vergleiche bleiben erhalten', $context);
                return;
            }
            $plan = $this->readPlan($b, $config, $now);
            $context = ['plan' => $plan, 'now' => $now, 'configurationHash' => $c['hash']];
            $v = $plan['inputs']['values'];
            if ($v['arrival'] <= $now || !$v['winter'] || $v['presence']) {
                $this->profileComparisonDisplay($j, 'Bereit – wartet auf zukünftige Aufwärmvorwahl', $context);
                return;
            }
            // Never claim a fresh pre-phase forecast after either warming stage started.
            $projection = HeatingPlan::project($plan, ['start' => $now, 'end' => min($v['arrival'], $now + 48 * 3600)], $now);
            if ($v['storageRequested'] || $v['fanRequested'] || $projection['storageStart'] === null) {
                $this->profileComparisonDisplay($j, FanComparisonView::phaseStatus($j, $v), $context);
                return;
            }
            $phaseStart = min(array_filter([$projection['storageStart'], $projection['fanStart']], static fn(?int $t): bool => $t !== null));
            $window = FanComparison::issueWindow($phaseStart);
            $first = $window['cycle'];
            $last = $j['records'] === [] ? null : end($j['records']);
            if (
                $last !== null && $last['configurationHash'] === $c['hash']
                && $last['forecast']['plan']['hash'] === $plan['hash']
                && $last['forecast']['phaseStart'] === $phaseStart
                && $last['forecast']['issuedAt'] < $window['deadline']
                && ($last['forecast']['profileComparison']['firstWarmingNight'] ?? null) === $first
            ) {
                $this->profileComparisonDisplay($j, 'Vorabvergleich gespeichert; Nachtmessungen werden automatisch ergänzt', $context);
                return;
            }
            if ($now >= $window['deadline']) {
                $this->profileComparisonDisplay($j, 'Vorabfrist der ersten Aufwärmnacht verpasst; keine rückwirkende Vergleichsprognose', $context);
                return;
            }
            if ($v['arrival'] < $first['end']) {
                $this->profileComparisonDisplay($j, 'Erste Aufwärmnacht reicht über die Ankunft hinaus; kein vollständiger Nachtvergleich', $context);
                return;
            }
            if ($now < $window['earliest']) {
                $when = (new DateTimeImmutable('@' . $window['earliest']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');
                $this->profileComparisonDisplay($j, 'Vorwahl erkannt – wartet bis ' . $when . ' auf vollständige erste Aufwärmnacht ' . $first['date'], $context);
                return;
            }
            // Fresh inputs at this issue, not an old warming curve re-labelled as new.
            $r = $this->issueWarming($c, $config, $plan, $now);
            $r['forecast']['profileComparison'] = ChargeProfile::build($r['forecast'], $c['chargeProfile'], $first);
            if (
                $plan['hash'] !== $this->readPlan($b, $config, $now)['hash']
                || $c['hash'] !== $this->profileComparisonConfiguration($config)['hash']
            ) {
                throw new RuntimeException('Plan oder Modell während Vergleich geändert; kein Stand gespeichert.');
            }
            $issuedAt = $this->now();
            if ($issuedAt >= $r['forecast']['phaseStart']) {
                throw new RuntimeException('Aufwärmphase während Berechnung gestartet; kein Vorabvergleich.');
            }
            if ($issuedAt >= $window['deadline']) {
                throw new RuntimeException('Vorabfrist während Berechnung abgelaufen.');
            }
            $r['forecast']['issuedAt'] = $issuedAt;
            $r['forecast']['beforePhase'] = true;
            $r['forecastHash'] = SAEF_CreateConfigurationHash($r['forecast']);
            $r['key'] = $r['forecastHash'];
            if ($j['records'] !== []) {
                $j['records'][array_key_last($j['records'])]['supersededAt'] = $issuedAt;
            }
            $j['records'][] = $r;
            $j = Warming::retain($j);
            $this->saveProfileComparison($j);
            $this->profileComparisonDisplay($j, 'Neue Vergleichsprognose vor Aufwärmbeginn fest gespeichert', $context);
        } catch (Throwable $e) {
            $this->profileComparisonDisplay($j, 'Ladeprofil-Vergleich: ' . substr($e->getMessage(), 0, 220), $context);
        }
    }


    private function saveProfileComparison(array &$j): void
    {
        $j = $this->assessComparisons($j);
        $json = Forecast::encode($j);
        ChargeProfile::journal($json);
        if ($json !== $this->ReadAttributeString('ProfileComparisonJournal')) {
            $this->WriteAttributeString('ProfileComparisonJournal', $json);
        }
    }

    private function profileComparisonDisplay(array $j, string $status, array $context = []): void
    {
        $status = ComparisonLifecycle::status($j, $status);
        $this->SetValue('ProfileComparisonStatus', $status);
        $this->SetValue('ProfileComparison', ChargeProfile::render($j, $status));
    }
}
