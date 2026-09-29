<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Forecast.php';
require_once __DIR__ . '/../libs/ArchiveReader.php';
require_once __DIR__ . '/../libs/FireplaceLog.php';
require_once __DIR__ . '/../libs/saef/diagnostics/Registry.php';
require_once __DIR__ . '/../libs/saef/diagnostics/Statistics.php';
require_once __DIR__ . '/../libs/saef/diagnostics/ErrorRingBuffer.php';
require_once __DIR__ . '/../libs/saef/diagnostics/ConfigurationHash.php';

use SAEF\StorageHeater\ArchiveReader;
use SAEF\StorageHeater\Forecast;
use SAEF\StorageHeater\FireplaceLog;

/** Observational module. Only its own variables/attributes are writable. */
class StorageHeaterForecast extends IPSModule
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Enabled', false);
        $this->RegisterPropertyString('BindingsJSON', '{}');
        $this->RegisterPropertyString('ModelJSON', '{}');
        $this->RegisterPropertyString('HistoryStart', '');
        $this->RegisterPropertyInteger('WeatherMaxAgeMinutes', 180);
        $this->RegisterPropertyInteger('SensorMaxAgeMinutes', 180);
        $this->RegisterAttributeString('Journal', '{"schema":1,"records":[]}');
        $this->RegisterAttributeString('FireplaceLog', '{"schema":1,"entries":[]}');
        $this->RegisterTimer('Observe', 0, 'SHF_Tick($_IPS["TARGET"]);');
        $this->RegisterVariableString('StatusText', 'Status', '', 10);
        $this->RegisterVariableBoolean('ForecastValid', 'Prognose gültig', '', 20);
        $this->RegisterVariableFloat('ForecastKWh', 'Erwartete Nachtladung (kWh)', '', 30);
        $this->RegisterVariableString('ForecastDate', 'Nacht der Prognose', '', 40);
        $this->RegisterVariableFloat('LastChargeKWh', 'Letzte gemessene Nachtladung (kWh)', '', 50);
        $this->RegisterVariableString('LastChargeDate', 'Nacht der Messung', '', 60);
        $this->RegisterVariableFloat('MeanAbsoluteErrorKWh', 'Mittlerer Prognosefehler (kWh)', '', 70);
        $this->RegisterVariableInteger('ScoredNights', 'Bewertete Nächte', '', 80);
        $this->RegisterVariableString('LatestRecord', 'Letzter vollständiger Datensatz (JSON)', '', 90);
        // Native lifecycle owns the objects; existing diagnostics helpers own their data contracts.
        $this->RegisterVariableString('Registry', 'Diagnose-Metadaten', '', 100);
        $this->RegisterVariableString('Errors', 'Letzte Fehler', '', 110);
        $this->RegisterVariableInteger('Runs', 'Beobachtungsläufe', '', 120);
        $this->RegisterVariableInteger('LastSuccess', 'Letzter erfolgreicher Lauf', '~UnixTimestamp', 130);
        $this->RegisterVariableString('FireplaceStatus', 'Kaminprotokoll – letzte Eingabe', '', 140);
        $this->RegisterVariableString('FireplaceEntries', 'Kamin – erfasste Starts', '', 150);
        $this->RegisterVariableString('FireplaceComparison', 'Prognosefehler nach Kamineinträgen', '', 160);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('Observe', 0);
        $this->SetValue('ForecastValid', false);
        foreach (IPS_GetReferenceList($this->InstanceID) as $id) {
            $this->UnregisterReference($id);
        }
        if (!$this->ReadPropertyBoolean('Enabled')) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetValue('StatusText', 'Beobachtung deaktiviert');
            return;
        }
        try {
            $config = $this->configuration();
            foreach ($config['bindings'] as $id) {
                $this->RegisterReference($id);
            }
            SAEF_UpdateRegistryEntry($this->GetIDForIdent('Registry'), 'configurationHash', $config['hash']);
            SAEF_UpdateRegistryEntry($this->GetIDForIdent('Registry'), 'version', '0.2.0');
            $this->SetTimerInterval('Observe', 5 * 60 * 1000);
            $this->SetStatus(IS_ACTIVE);
            $this->SetValue('StatusText', 'Bereit – Prognose täglich zwischen 21:45 und 22:00 Uhr');
        } catch (Throwable $error) {
            $this->reportError($error);
        }
    }

    /** No manual force/override: never backdate a prediction after charging began. */
    public function Tick(): void
    {
        if (!$this->ReadPropertyBoolean('Enabled')) {
            return;
        }
        $lock = 'StorageHeaterForecast.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 100)) {
            return;
        }
        try {
            $config = $this->configuration();
            $now = $this->now();
            $day = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Berlin'));
            $journal = $this->journal();
            $records = $journal['records'];
            $archive = new ArchiveReader($config['bindings']['archive'], $config['historyStart']);
            // Recover yesterday after a restart; do not invent old forecasts or bulk-replay seasons.
            $yesterday = Forecast::cycle($day->modify('-1 day')->format('Y-m-d'), 'Europe/Berlin');
            if ($now >= $yesterday['end'] + 3600 && !isset($records[$yesterday['date']])) {
                $records[$yesterday['date']] = $this->emptyRecord($yesterday, $config, $now, 'not_recorded');
            }
            $cycle = Forecast::cycle($day->format('Y-m-d'), 'Europe/Berlin');
            if (!isset($records[$cycle['date']]) && $now >= $cycle['issueFrom']) {
                $record = $this->emptyRecord($cycle, $config, $now, 'missed_issue_window');
                if ($now < $cycle['start']) {
                    // A data failure is visible and retried by the next bounded timer tick.
                    $record = $this->makePrediction($record, $config, $archive, $now);
                }
                $records[$cycle['date']] = $record;
            }
            $attempts = 0;
            foreach ($records as &$record) {
                if ($record['measurement'] !== null || $now < $record['cycle']['end'] + 3600 || $attempts >= 2) {
                    continue;
                }
                ++$attempts;
                if ($now - $record['cycle']['end'] > 7 * 24 * 3600) {
                    $record['measurement'] = ['status' => 'expired_missing_data', 'settledAt' => $now];
                    continue;
                }
                try {
                    $record['measurement'] = $this->measure($record, $now);
                    unset($record['measurementError']);
                } catch (Throwable $error) {
                    $record['measurementError'] = substr($error->getMessage(), 0, 300);
                }
            }
            unset($record);
            ksort($records);
            // Rolling domain ledger: approximately thirteen months, bounded independently of diagnostics.
            while (count($records) > 400) {
                array_shift($records);
            }
            $encodedJournal = Forecast::encode(['schema' => 1, 'records' => $records]);
            if ($encodedJournal !== $this->ReadAttributeString('Journal')) {
                $this->WriteAttributeString('Journal', $encodedJournal);
            }
            $this->publish($records, $now, $config['hash']);
            SAEF_IncrementStatistic($this->GetIDForIdent('Runs'));
            SAEF_SetStatisticTimestamp($this->GetIDForIdent('LastSuccess'), $now);
            $this->SetStatus(IS_ACTIVE);
        } catch (Throwable $error) {
            $this->SetValue('ForecastValid', false);
            $this->reportError($error);
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    public function GetJournalJson(): string
    {
        $journal = $this->journal();
        $log = FireplaceLog::read($this->ReadAttributeString('FireplaceLog'));
        foreach ($journal['records'] as &$record) {
            $record['fireplaceCurrentClassification'] = FireplaceLog::classify($log, $record['cycle']);
        }
        unset($record);
        return Forecast::encode($journal + ['fireplaceLog' => $log, 'fireplaceComparison' => FireplaceLog::score($journal['records'], $log)]);
    }

    public function RecordFireplaceStart(string $localDateTime): string
    {
        return $this->editFireplace($localDateTime, false);
    }

    public function CancelFireplaceStart(string $localDateTime): string
    {
        return $this->editFireplace($localDateTime, true);
    }

    private function editFireplace(string $localDateTime, bool $cancel): string
    {
        $lock = 'StorageHeaterForecast.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 1000)) {
            return 'Bitte erneut versuchen: Protokoll wird gerade verarbeitet.';
        }
        $saved = false;
        try {
            $start = FireplaceLog::localStart($localDateTime);
            $log = FireplaceLog::read($this->ReadAttributeString('FireplaceLog'));
            $log = $cancel ? FireplaceLog::cancel($log, $start, $this->now()) : FireplaceLog::record($log, $start, $this->now());
            $records = $this->journal()['records'];
            $this->WriteAttributeString('FireplaceLog', Forecast::encode($log));
            $saved = true;
            $this->publishFireplace($records, $log);
            $text = (new DateTimeImmutable('@' . $start))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');
            $message = $cancel ? 'Kaminstart storniert: ' . $text : 'Kaminstart gespeichert: ' . $text;
        } catch (Throwable $error) {
            $message = ($saved ? 'Gespeichert; Anzeige konnte nicht aktualisiert werden: ' : 'Nicht gespeichert: ') . $error->getMessage();
        } finally {
            IPS_SemaphoreLeave($lock);
        }
        $this->SetValue('FireplaceStatus', $message);
        return $message;
    }

    private function publishFireplace(array $records, array $log): void
    {
        $lines = [];
        foreach (array_reverse($log['entries']) as $entry) {
            $start = (new DateTimeImmutable('@' . $entry['startedAt']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');
            $lines[] = $start . ($entry['cancelledAt'] === null ? '' : ' (storniert)');
        }
        $this->SetValue('FireplaceEntries', implode("\n", array_slice($lines, 0, 30)));
        $groups = FireplaceLog::score($records, $log);
        $lines = [];
        foreach (['reported_start' => 'Mit gemeldetem Kaminstart', 'no_reported_start' => 'Ohne gemeldeten Kaminstart'] as $key => $label) {
            $group = $groups[$key];
            $lines[] = $label . ': ' . $group['nights'] . ' Nächte; ' . ($group['maeKwh'] === null ? 'noch kein Vergleich' : number_format($group['maeKwh'], 2, ',', '') . ' kWh mittlerer Fehler');
        }
        $this->SetValue('FireplaceComparison', implode("\n", $lines));
    }

    protected function now(): int
    {
        return time();
    }

    private function configuration(): array
    {
        if (PHP_VERSION_ID < 80200) {
            throw new RuntimeException('PHP 8.2 or newer is required.');
        }
        $b = Forecast::decode($this->ReadPropertyString('BindingsJSON'));
        $required = ['archive', 'weather', 'energy', 'power', 'outside', 'gallery', 'sofa', 'fan', 'enabled', 'reduced'];
        foreach ($required as $key) {
            if (!isset($b[$key]) || !is_int($b[$key]) || $b[$key] <= 0) {
                throw new RuntimeException('Positive configured source ID required: ' . $key);
            }
        }
        if (array_diff(array_keys($b), array_merge($required, ['presence', 'auxiliary'])) !== []) {
            throw new RuntimeException('Unknown binding key.');
        }
        foreach ($b as $key => $id) {
            if (!is_int($id) || $id <= 0) {
                throw new RuntimeException('Invalid source ID: ' . $key);
            }
            if (in_array($key, ['archive', 'weather'], true)) {
                if (!IPS_InstanceExists($id)) {
                    throw new RuntimeException('Missing source instance: ' . $key);
                }
                continue;
            }
            if (!IPS_VariableExists($id) || IPS_GetObject($id)['ParentID'] === $this->InstanceID) {
                throw new RuntimeException('Invalid external variable: ' . $key);
            }
            $boolean = in_array($key, ['fan', 'enabled', 'reduced', 'presence', 'auxiliary'], true);
            $types = $boolean ? [VARIABLETYPE_BOOLEAN] : [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT];
            if (!in_array(IPS_GetVariable($id)['VariableType'], $types, true)) {
                throw new RuntimeException('Incompatible source type: ' . $key);
            }
        }
        if (IPS_GetInstance($b['weather'])['ModuleInfo']['ModuleID'] !== '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}' || !function_exists('OMWEATHER_GetHourlyForecastJson')) {
            throw new RuntimeException('Compatible Open-Meteo Weather module required.');
        }
        $history = DateTimeImmutable::createFromFormat('!Y-m-d', $this->ReadPropertyString('HistoryStart'), new DateTimeZone('Europe/Berlin'));
        if ($history === false || $history->format('Y-m-d') !== $this->ReadPropertyString('HistoryStart')) {
            throw new RuntimeException('HistoryStart must be a valid YYYY-MM-DD date.');
        }
        foreach (['WeatherMaxAgeMinutes', 'SensorMaxAgeMinutes'] as $property) {
            if ($this->ReadPropertyInteger($property) < 5 || $this->ReadPropertyInteger($property) > 24 * 60) {
                throw new RuntimeException('Freshness limit must be between 5 and 1440 minutes.');
            }
        }
        $model = Forecast::decode($this->ReadPropertyString('ModelJSON'));
        Forecast::validateModel($model);
        $config = ['bindings' => $b, 'model' => $model, 'historyStart' => $history->getTimestamp()];
        $config['hash'] = SAEF_CreateConfigurationHash($config);
        return $config;
    }

    private function emptyRecord(array $cycle, array $config, int $now, string $reason): array
    {
        return ['cycle' => $cycle, 'createdAt' => $now, 'configurationHash' => $config['hash'],
            'bindings' => $config['bindings'], 'historyStart' => $config['historyStart'],
            'forecast' => null, 'forecastReason' => $reason, 'measurement' => null];
    }

    private function makePrediction(array $record, array $config, ArchiveReader $archive, int $now): array
    {
        $b = $config['bindings'];
        $cycle = $record['cycle'];
        $cutoff = $cycle['cutoff'];
        $enabled = GetValue($b['enabled']);
        $reduced = GetValue($b['reduced']);
        $context = ['enabled' => $enabled, 'reduced' => $reduced, 'fan' => GetValue($b['fan'])];
        foreach (['presence', 'auxiliary'] as $key) {
            if (isset($b[$key])) {
                $context[$key] = GetValue($b[$key]);
            }
        }
        $record['context'] = $context;
        if (!$enabled) {
            $record['forecastReason'] = 'heating_disabled_no_prediction';
            return $record;
        }
        $freshness = [];
        foreach (['outside', 'gallery', 'sofa', 'energy'] as $key) {
            $v = IPS_GetVariable($b[$key]);
            $age = $now - $v['VariableUpdated'];
            if ($age < 0 || $age > $this->ReadPropertyInteger('SensorMaxAgeMinutes') * 60) {
                throw new RuntimeException('Source update too old: ' . $key);
            }
            $freshness[$key] = ['updatedAt' => $v['VariableUpdated'], 'value' => Forecast::number(GetValue($b[$key]), $key)];
        }
        $weatherLast = null;
        foreach (IPS_GetChildrenIDs($b['weather']) as $id) {
            if (IPS_GetObject($id)['ObjectIdent'] === 'LastSuccess' && IPS_VariableExists($id)) {
                $weatherLast = GetValue($id);
            }
        }
        if (!is_int($weatherLast) || $weatherLast > $now || $now - $weatherLast > $this->ReadPropertyInteger('WeatherMaxAgeMinutes') * 60) {
            throw new RuntimeException('Weather forecast unavailable or too old.');
        }
        $response = Forecast::decode(OMWEATHER_GetHourlyForecastJson($b['weather'], $cycle['start'], $cycle['end'] + 1, '["temperature_2m"]'));
        if (($response['success'] ?? false) !== true || !is_array($response['data']['temperature_2m'] ?? null)) {
            throw new RuntimeException('Weather API returned no usable temperature series.');
        }
        $weather = Forecast::weather($response['data']['temperature_2m'], $cycle['start'], $cycle['end']);
        $previousDate = (new DateTimeImmutable($cycle['date']))->modify('-1 day')->format('Y-m-d');
        $previous = Forecast::cycle($previousDate, 'Europe/Berlin');
        $outside = $archive->hours($b['outside'], $cutoff - 24 * 3600, $cutoff);
        $features = [
            'hdd_night' => max(0.0, 15 - $weather['mean']), 'reduced' => (int) $reduced,
            'hdd_reduced' => max(0.0, 15 - $weather['mean']) * (int) $reduced,
            'gallery' => array_values($archive->hours($b['gallery'], $cutoff - 3600, $cutoff))[0],
            'sofa' => array_values($archive->hours($b['sofa'], $cutoff - 3600, $cutoff))[0],
            'previous_charge' => array_sum($archive->hours($b['energy'], $previous['start'], $previous['end'], true)),
            'fan_hours' => $archive->duty($b['fan'], $cutoff - 24 * 3600, $cutoff)['hours'],
            'outside_previous' => array_sum($outside) / count($outside),
        ];
        $issuedAt = $this->now();
        if ($issuedAt >= $cycle['start']) {
            throw new RuntimeException('Issue window ended during calculation; no forecast saved.');
        }
        $result = Forecast::predict($config['model'], $features, $issuedAt);
        $record['forecast'] = $result + ['issuedAt' => $issuedAt, 'contextReadAt' => $now, 'features' => $features, 'weather' => $weather,
            'weatherFetchedAt' => $weatherLast, 'freshness' => $freshness, 'model' => $config['model']];
        $record['forecast']['fireplaceKnownAtIssue'] = FireplaceLog::classify(FireplaceLog::read($this->ReadAttributeString('FireplaceLog')), $cycle, $issuedAt);
        $record['forecastReason'] = 'forecast_saved';
        return $record;
    }

    private function measure(array $record, int $now): array
    {
        $b = $record['bindings'];
        $cycle = $record['cycle'];
        $archive = new ArchiveReader($b['archive'], $record['historyStart']);
        $energy = $archive->hours($b['energy'], $cycle['start'], $cycle['end'], true);
        $power = $archive->hours($b['power'], $cycle['start'], $cycle['end']);
        $states = [];
        foreach (['enabled', 'reduced', 'fan', 'auxiliary', 'presence'] as $key) {
            if (isset($b[$key])) {
                $states[$key] = $archive->duty($b[$key], $cycle['start'], $cycle['end']);
            }
        }
        $changes = 0;
        foreach (['enabled', 'reduced'] as $key) {
            $from = $record['forecast']['issuedAt'] ?? $cycle['start'];
            $changes += $archive->duty($b[$key], $from, $cycle['end'])['changes'];
        }
        $charge = array_sum($energy);
        $difference = abs($charge - array_sum($power));
        return ['status' => 'complete', 'settledAt' => $now, 'kwh' => $charge,
            'warnings' => $difference > max(0.5, $charge * 0.05) ? ['energy_power_mismatch'] : [],
            'powerIntegralKwh' => array_sum($power), 'energyHours' => $energy, 'powerHoursKw' => $power,
            'states' => $states, 'controlChangesAfterIssue' => $changes,
            'errorKwh' => $record['forecast'] === null ? null : $record['forecast']['kwh'] - $charge];
    }

    private function journal(): array
    {
        $journal = Forecast::decode($this->ReadAttributeString('Journal'));
        if (($journal['schema'] ?? null) !== 1 || !is_array($journal['records'] ?? null) || count($journal['records']) > 400) {
            throw new RuntimeException('Invalid journal; restore a verified backup.');
        }
        foreach ($journal['records'] as $date => $record) {
            if (!is_array($record) || ($record['cycle']['date'] ?? null) !== $date || !array_key_exists('forecast', $record) || !array_key_exists('measurement', $record)) {
                throw new RuntimeException('Corrupt journal record.');
            }
        }
        return $journal;
    }

    private function publish(array $records, int $now, string $hash): void
    {
        $this->publishFireplace($records, FireplaceLog::read($this->ReadAttributeString('FireplaceLog')));
        $valid = false;
        $errors = [];
        $pending = 0;
        $warnings = [];
        $lastMeasurement = null;
        foreach ($records as $record) {
            if (($record['measurement']['status'] ?? '') === 'complete') {
                $lastMeasurement = $record;
                if ($record['measurement']['errorKwh'] !== null) {
                    $errors[] = abs($record['measurement']['errorKwh']);
                }
            }
            $pending += isset($record['measurementError']) ? 1 : 0;
            if ($record['forecast'] !== null && $record['configurationHash'] === $hash && $now < $record['cycle']['end']) {
                $valid = true;
                $this->SetValue('ForecastKWh', $record['forecast']['kwh']);
                $this->SetValue('ForecastDate', $record['cycle']['date']);
                $warnings = $record['forecast']['warnings'];
            }
        }
        if ($lastMeasurement !== null) {
            $this->SetValue('LastChargeKWh', $lastMeasurement['measurement']['kwh']);
            $this->SetValue('LastChargeDate', $lastMeasurement['cycle']['date']);
            $this->SetValue('LatestRecord', Forecast::encode($lastMeasurement));
        }
        $this->SetValue('ForecastValid', $valid);
        $this->SetValue('ScoredNights', count($errors));
        $this->SetValue('MeanAbsoluteErrorKWh', $errors === [] ? 0.0 : array_sum($errors) / count($errors));
        $status = $valid ? 'Nachtprognose gespeichert; bisherige Schaltzustände vorausgesetzt' : 'Keine aktuelle Nachtprognose; nächstes Zeitfenster 21:45–22:00';
        if ($pending > 0) {
            $status .= '; ' . $pending . ' Messungen warten auf vollständige Archivdaten';
        }
        if ($warnings !== []) {
            $status .= '; außerhalb des Trainingsbereichs: ' . implode(', ', $warnings);
        }
        $this->SetValue('StatusText', $status);
    }

    private function reportError(Throwable $error): void
    {
        $message = substr($error->getMessage(), 0, 300);
        $this->SetStatus(201);
        $this->SetValue('StatusText', 'Daten/Konfiguration prüfen: ' . $message);
        SAEF_AppendErrorRingBufferEntry($this->GetIDForIdent('Errors'), $message, 20);
    }
}
