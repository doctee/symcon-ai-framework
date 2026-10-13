<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;
use Throwable;

/** Separate domain journal; no changes to the electrical forecast or heater controls. */
trait CoolingObservation
{
    private function createCooling(): void
    {
        $this->RegisterPropertyString('CoolingModelJSON', '{}');
        $this->RegisterPropertyString('CoolingBindingsJSON', '{}');
        $this->RegisterAttributeString('CoolingJournal', '{"schema":1,"records":[]}');
        $this->RegisterVariableString('CoolingStatus', 'Auskühlmodell – Status', '', 190);
        $this->RegisterVariableString('CoolingComparison', 'Temperaturen – Prognose und Messung', '~HTMLBox', 200);
    }

    public function GetCoolingJournalJson(): string
    {
        return Forecast::encode(Cooling::journal($this->ReadAttributeString('CoolingJournal')));
    }

    private function coolingConfiguration(array $config): ?array
    {
        $model = Forecast::decode($this->ReadPropertyString('CoolingModelJSON'));
        if ($model === []) {
            return null;
        }
        Cooling::model($model);
        $extra = Forecast::decode($this->ReadPropertyString('CoolingBindingsJSON'));
        if (array_diff(array_keys($extra), ['sofaBattery', 'floor', 'supply']) !== []) {
            throw new RuntimeException('Unknown cooling source.');
        }
        foreach ($extra as $key => $id) {
            if (!is_int($id) || $id <= 0 || !IPS_VariableExists($id) || IPS_GetObject($id)['ParentID'] === $this->InstanceID) {
                throw new RuntimeException('Invalid external cooling source: ' . $key);
            }
            $types = $key === 'sofaBattery' ? [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT] : [VARIABLETYPE_BOOLEAN];
            if (!in_array(IPS_GetVariable($id)['VariableType'], $types, true)) {
                throw new RuntimeException('Wrong cooling source type: ' . $key);
            }
        }
        $c = ['model' => $model, 'bindings' => $config['bindings'] + $extra, 'historyStart' => $config['historyStart'],
            'sensorMaxAgeSeconds' => $this->ReadPropertyInteger('SensorMaxAgeMinutes') * 60,
            'weatherMaxAgeSeconds' => $this->ReadPropertyInteger('WeatherMaxAgeMinutes') * 60];
        $c['hash'] = SAEF_CreateConfigurationHash($c);
        return $c;
    }

    private function configureCooling(array $config): void
    {
        try {
            $c = $this->coolingConfiguration($config);
            if ($c !== null) {
                foreach ($c['bindings'] as $id) {
                    $this->RegisterReference($id);
                }
                SAEF_UpdateRegistryEntry($this->GetIDForIdent('Registry'), 'coolingConfigurationHash', $c['hash']);
            }
            $this->coolingDisplay([], [], $c === null ? 'Auskühlmodell nicht konfiguriert' : 'Bereit – neue Nachtkurve zwischen 23:00 und 23:15 Uhr');
        } catch (Throwable $e) {
            $this->coolingDisplay([], [], 'Auskühlmodell prüfen: ' . substr($e->getMessage(), 0, 200));
        }
    }

    private function coolingSensors(array $c, int $now): array
    {
        $values = [];
        foreach (['gallery', 'sofa', 'outside'] as $key) {
            $id = $c['bindings'][$key];
            $updated = IPS_GetVariable($id)['VariableUpdated'];
            $age = $now - $updated;
            $valid = $updated > 0 && $age >= 0 && $age <= $c['sensorMaxAgeSeconds'];
            if ($key === 'sofa' && isset($c['bindings']['sofaBattery']) && GetValue($c['bindings']['sofaBattery']) <= 0) {
                $valid = false;
            }
            $value = Forecast::number(GetValue($id), $key);
            $valid = $valid && $value >= -60 && $value <= 60;
            $values[$key] = ['value' => $valid ? $value : null, 'updatedAt' => $updated];
        }
        return $values;
    }

    private function observeCooling(array $config, int $now): void
    {
        $records = [];
        $current = [];
        try {
            $c = $this->coolingConfiguration($config);
            $records = Cooling::journal($this->ReadAttributeString('CoolingJournal'))['records'];
            if ($c === null) {
                $this->coolingDisplay($records, [], 'Auskühlmodell nicht konfiguriert');
                return;
            }
            $current = $this->coolingSensors($c, $now);
            $cycle = Cooling::cycle($now);
            $status = 'Nächste Nachtkurve: 23:00–23:15 Uhr; Messwerte werden ergänzt';
            if ($now >= $cycle['start'] && $now < $cycle['issueUntil'] && !isset($records[$cycle['date']])) {
                $records[$cycle['date']] = $this->issueCooling($c, $current, $cycle, $now);
            } elseif ($now >= $cycle['issueUntil'] && !isset($records[$cycle['date']])) {
                $status = 'Ausgabezeitfenster verpasst – keine nachträgliche Vorhersage';
            }
            // At most the two newest existing forecasts; never invent historical predictions.
            foreach (array_slice(array_keys($records), -2) as $date) {
                if (($records[$date]['settledAt'] ?? null) !== null) {
                    continue;
                }
                $records[$date] = $this->measureCooling($records[$date], $now);
            }
            ksort($records);
            while (count($records) > 120) {
                array_shift($records);
            }
            $encoded = Forecast::encode(['schema' => 1, 'records' => $records]);
            if ($encoded !== $this->ReadAttributeString('CoolingJournal')) {
                $this->WriteAttributeString('CoolingJournal', $encoded);
            }
            $latest = $records === [] ? null : end($records);
            if ($latest !== null && $now <= $latest['forecast']['end']) {
                $status = 'Nachtkurve gespeichert – Vergleich ohne zusätzliche Wärmezufuhr';
            }
            if (in_array(null, array_column($current, 'value'), true)) {
                $status .= '; mindestens ein Sensorwert veraltet oder unbrauchbar';
            }
            $this->coolingDisplay($records, $current, $status);
        } catch (Throwable $e) {
            // Cooling failures must not suppress the independent electrical forecast.
            $this->coolingDisplay($records, $current, 'Auskühlmodell: ' . substr($e->getMessage(), 0, 220));
        }
    }

    private function issueCooling(array $c, array $current, array $cycle, int $now): array
    {
        if (in_array(null, array_column($current, 'value'), true)) {
            throw new RuntimeException('Keine neue Kurve: Sensorwert fehlt, Batterie leer oder Aktualisierung zu alt.');
        }
        $b = $c['bindings'];
        $fetched = null;
        foreach (IPS_GetChildrenIDs($b['weather']) as $id) {
            if (IPS_GetObject($id)['ObjectIdent'] === 'LastSuccess' && IPS_VariableExists($id)) {
                $fetched = GetValue($id);
            }
        }
        if (!is_int($fetched) || $fetched <= 0 || $fetched > $now || $now - $fetched > $c['weatherMaxAgeSeconds']) {
            throw new RuntimeException('Keine neue Kurve: Wettervorhersage fehlt oder ist veraltet.');
        }
        $from = intdiv($now, 3600) * 3600;
        $response = Forecast::decode(OMWEATHER_GetHourlyForecastJson($b['weather'], $from, $cycle['end'] + 1, '["temperature_2m"]'));
        if (($response['success'] ?? false) !== true || !is_array($response['data']['temperature_2m'] ?? null)) {
            throw new RuntimeException('Keine nutzbare Wettervorhersage.');
        }
        $weather = Forecast::weather($response['data']['temperature_2m'], $from, $cycle['end']);
        if ($this->now() >= $cycle['issueUntil']) {
            throw new RuntimeException('Ausgabezeitfenster während Berechnung abgelaufen.');
        }
        $context = [];
        foreach (['enabled', 'fan', 'presence', 'auxiliary', 'floor', 'supply'] as $key) {
            if (isset($b[$key])) {
                $context[$key] = GetValue($b[$key]);
            }
        }
        $points = Cooling::predict($c['model'], $current, $weather, $now, $cycle['end']);
        return ['date' => $cycle['date'], 'configurationHash' => $c['hash'], 'bindings' => $b,
            'historyStart' => $c['historyStart'], 'forecast' => ['issuedAt' => $now, 'end' => $cycle['end'],
                'model' => $c['model'], 'initial' => $current, 'weather' => $weather, 'weatherFetchedAt' => $fetched,
                'context' => $context, 'fireplaceKnownAtIssue' => FireplaceLog::classify(FireplaceLog::read($this->ReadAttributeString('FireplaceLog')), ['start' => $now, 'end' => $cycle['end']], $now),
                'warnings' => Cooling::warnings($c['model'], $current, $weather, $points),
                'points' => $points],
            'actual' => [], 'measurementErrors' => [], 'settledAt' => null];
    }

    private function measureCooling(array $r, int $now): array
    {
        $f = $r['forecast'];
        if ($now - $f['end'] > 7 * 86400) {
            $r['settledAt'] = $now;
            $r['measurementErrors']['expired'] = 'Messfenster abgelaufen';
            return $r;
        }
        $from = max($r['historyStart'], $f['issuedAt'] - 6 * 3600);
        $to = min($now, $f['end'] + 6 * 3600);
        $times = array_column($f['points'], 'time');
        foreach (['gallery', 'sofa'] as $room) {
            try {
                $archive = new ArchiveReader($r['bindings']['archive'], $r['historyStart']);
                $rows = $archive->changes($r['bindings'][$room], $from, $to);
                $actual = Cooling::measured($rows, $times, $now);
                if ($room === 'sofa' && isset($r['bindings']['sofaBattery'])) {
                    $battery = $archive->changes($r['bindings']['sofaBattery'], $from, $to, true);
                    $actual = Cooling::maskBattery($actual, $battery);
                }
                foreach ($actual as $t => $point) {
                    if ($point !== null && !isset($r['actual'][$room][$t])) {
                        $r['actual'][$room][$t] = $point;
                    }
                }
                unset($r['measurementErrors'][$room]);
            } catch (Throwable $e) {
                $r['measurementErrors'][$room] = substr($e->getMessage(), 0, 120);
            }
        }
        // Wait for the right interpolation bracket, then retain missing points as gaps.
        if ($now >= $f['end'] + 6 * 3600) {
            $r['settledAt'] = $now;
        }
        return $r;
    }

    private function coolingDisplay(array $records, array $current, string $status): void
    {
        try {
            $html = CoolingView::render($records, $current, $status);
        } catch (Throwable $e) {
            $status = 'Temperaturanzeige prüfen: ' . substr($e->getMessage(), 0, 180);
            $html = CoolingView::render([], [], $status);
        }
        $this->SetValue('CoolingStatus', $status);
        $this->SetValue('CoolingComparison', $html);
    }
}
