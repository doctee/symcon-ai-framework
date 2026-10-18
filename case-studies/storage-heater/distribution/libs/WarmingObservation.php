<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;
use Throwable;

/** Read-only inputs, immutable prospective curves and separate measured outcomes. */
trait WarmingObservation
{
    private function createWarming(): void
    {
        $this->RegisterPropertyString('WarmingModelJSON', '{}');
        $this->RegisterPropertyString('WarmingOptionsJSON', '{}');
        $this->RegisterAttributeString('WarmingJournal', '{"schema":1,"discarded":0,"records":[]}');
        $this->RegisterVariableString('WarmingStatus', 'Aufwärmmodell – Status', '', 230);
        $this->RegisterVariableString('WarmingComparison', 'Aufwärmen – Vorhersage und Messung', '~HTMLBox', 240);
    }

    public function GetWarmingJournalJson(): string
    {
        return Forecast::encode(Warming::journal($this->ReadAttributeString('WarmingJournal')));
    }

    private function warmingConfiguration(array $config): ?array
    {
        $model = Forecast::decode($this->ReadPropertyString('WarmingModelJSON'));
        if ($model === []) {
            return null;
        }
        Warming::model($model);
        $c = $this->coolingConfiguration($config);
        if ($c === null || $this->planConfiguration($config) === null) {
            throw new RuntimeException('Warming requires validated temperature and preselection bindings.');
        }
        $options = Forecast::decode($this->ReadPropertyString('WarmingOptionsJSON'));
        $id = $options['regulator'] ?? null;
        if (
            !is_int($id) || $id <= 0 || !IPS_InstanceExists($id)
            || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== '{FF7AF0F4-D616-4EF5-B73A-D49C56CF4C92}'
        ) {
            throw new RuntimeException('Invalid Heating Control source.');
        }
        $controller = Forecast::decode(IPS_GetConfiguration($id));
        if (
            ($controller['Type'] ?? null) !== 0 || ($controller['Mode'] ?? null) !== 0
            || ($controller['SourceID'] ?? null) !== $c['bindings']['gallery']
        ) {
            throw new RuntimeException('Only two-point heating with the gallery source is supported.');
        }
        foreach (['latitude' => [-90, 90], 'longitude' => [-180, 180], 'maxPowerKw' => [.1, 50], 'standbyKw' => [0, .1]] as $key => [$low, $high]) {
            $v = Forecast::number($options[$key] ?? null, $key);
            if ($v < $low || $v > $high) {
                throw new RuntimeException('Warming option outside range: ' . $key);
            }
        }
        foreach (['Hysteresis' => [0.01, 5], 'SetBack' => [0, 15]] as $key => [$low, $high]) {
            $v = Forecast::number($controller[$key] ?? null, $key);
            if ($v < $low || $v > $high) {
                throw new RuntimeException('Unsupported thermostat parameter: ' . $key);
            }
        }
        $targets = Forecast::decode($controller['TargetVariables'] ?? '[]');
        if (count($targets) !== 1 || ($targets[0]['TargetID'] ?? null) !== $c['bindings']['fan'] || ($targets[0]['Invert'] ?? null) !== false) {
            throw new RuntimeException('Thermostat target no longer matches fan relay.');
        }
        $c['model'] = $model;
        $c['options'] = $options;
        $c['controller'] = $controller;
        $c['hash'] = SAEF_CreateConfigurationHash([$c, $config['hash']]);
        return $c;
    }

    /** Optional window inputs; existing thermostat contacts remain the authoritative OR group. */
    private function windowConfiguration(): ?array
    {
        $o = Forecast::decode($this->ReadPropertyString('WarmingOptionsJSON'));
        if (!isset($o['windowRaw']) && !isset($o['windowContact'])) {
            return null;
        }
        foreach (['windowRaw' => VARIABLETYPE_INTEGER, 'windowContact' => VARIABLETYPE_BOOLEAN] as $key => $type) {
            $id = $o[$key] ?? null;
            if (
                !is_int($id) || $id <= 0 || !IPS_VariableExists($id)
                || IPS_GetVariable($id)['VariableType'] !== $type || IPS_GetObject($id)['ParentID'] === $this->InstanceID
            ) {
                throw new RuntimeException('Invalid external window binding: ' . $key);
            }
        }
        $id = $o['regulator'] ?? null;
        if (
            !is_int($id) || $id <= 0 || !IPS_InstanceExists($id)
            || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== '{FF7AF0F4-D616-4EF5-B73A-D49C56CF4C92}'
        ) {
            throw new RuntimeException('Invalid window thermostat source.');
        }
        $controller = Forecast::decode(IPS_GetConfiguration($id));
        $contacts = Forecast::decode($controller['SetBackVariables'] ?? '[]');
        if (!array_is_list($contacts) || count($contacts) < 1 || count($contacts) > 16) {
            throw new RuntimeException('Expected bounded thermostat setback contacts.');
        }
        $found = false;
        $ids = [];
        foreach ($contacts as $contact) {
            $source = $contact['VariableID'] ?? null;
            if (
                !is_int($source) || $source <= 0 || !IPS_VariableExists($source)
                || IPS_GetVariable($source)['VariableType'] !== VARIABLETYPE_BOOLEAN
                || IPS_GetObject($source)['ParentID'] === $this->InstanceID || !is_bool($contact['Invert'] ?? null)
                || in_array($source, $ids, true)
            ) {
                throw new RuntimeException('Invalid thermostat setback contact.');
            }
            $ids[] = $source;
            $found = $found || ($source === $o['windowContact'] && !$contact['Invert']);
        }
        if (!$found) {
            throw new RuntimeException('Window contact is not a non-inverted thermostat setback source.');
        }
        $delta = Forecast::number($controller['SetBack'] ?? null, 'SetBack');
        if ($delta < 0 || $delta > 15) {
            throw new RuntimeException('Invalid thermostat setback delta.');
        }
        return ['raw' => $o['windowRaw'], 'contact' => $o['windowContact'], 'contacts' => $contacts, 'setbackKelvin' => $delta];
    }

    private function readWindow(): ?array
    {
        $c = $this->windowConfiguration();
        if ($c === null) {
            return null;
        }
        $raw = GetValue($c['raw']);
        if (!is_int($raw) || !in_array($raw, [0, 1, 2], true)) {
            throw new RuntimeException('Window state must be 0 closed, 1 tilted or 2 open.');
        }
        $contacts = [];
        $setback = false;
        foreach ($c['contacts'] as $source) {
            $id = $source['VariableID'];
            $active = (bool) GetValue($id) !== $source['Invert'];
            $setback = $setback || $active;
            $contacts[] = $source + ['active' => $active, 'changedAt' => IPS_GetVariable($id)['VariableChanged']];
        }
        return ['bindings' => $c, 'raw' => $raw, 'open' => (bool) GetValue($c['contact']),
            'changedAt' => IPS_GetVariable($c['raw'])['VariableChanged'], 'contacts' => $contacts,
            'setback' => $setback, 'setbackKelvin' => $c['setbackKelvin'],
            'assumption' => 'current_contacts_held_until_horizon', 'heatLoss' => 'not_calibrated'];
    }

    private function configureWarming(array $config): void
    {
        try {
            $c = $this->warmingConfiguration($config);
            if ($c !== null) {
                $this->RegisterReference($c['options']['regulator']);
                $window = $this->windowConfiguration();
                if ($window !== null) {
                    foreach (array_unique(array_merge([$window['raw']], array_column($window['contacts'], 'VariableID'))) as $id) {
                        $this->RegisterReference($id);
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
            $this->warmingDisplay(Warming::journal($this->ReadAttributeString('WarmingJournal')), $c === null
                ? 'Aufwärmmodell nicht konfiguriert' : 'Bereit – wartet auf Heizungsvorwahl');
        } catch (Throwable $e) {
            $this->warmingDisplay([], 'Aufwärmmodell prüfen: ' . $e->getMessage());
        }
    }

    private function observeWarming(array $config, int $now): void
    {
        $j = [];
        try {
            $j = Warming::journal($this->ReadAttributeString('WarmingJournal'));
            $log = FireplaceLog::read($this->ReadAttributeString('FireplaceLog'));
            foreach ($j['records'] as &$saved) {
                $saved['fireplaceAtComparison'] = FireplaceLog::classify(
                    $log,
                    ['start' => $saved['forecast']['start'], 'end' => $saved['forecast']['end']],
                    $now
                );
            }
            unset($saved);
            // Outcomes remain eligible after clearing the preselection or model configuration.
            $pending = array_keys(array_filter($j['records'], static fn(array $r): bool => $r['settledAt'] === null));
            usort($pending, static fn(int $a, int $b): int => ($j['records'][$a]['checkedAt'] ?? 0) <=> ($j['records'][$b]['checkedAt'] ?? 0));
            foreach (array_slice($pending, 0, 2) as $i) {
                $j['records'][$i] = $this->measureWarming($j['records'][$i], $now);
            }
            $this->saveWarming($j);
            $c = $this->warmingConfiguration($config);
            $b = $this->planConfiguration($config);
            if ($c === null || $b === null) {
                $this->warmingDisplay($j, 'Aufwärmmodell nicht konfiguriert; gespeicherte Vergleiche bleiben erhalten');
                return;
            }
            $plan = $this->readPlan($b, $config, $now);
            if ($plan['hash'] !== $this->readPlan($b, $config, $now)['hash']) {
                throw new RuntimeException('Vorwahl ändert sich; nächster Lauf übernimmt sie.');
            }
            $v = $plan['inputs']['values'];
            if ($v['arrival'] <= $now || !$v['winter'] || $v['presence']) {
                $this->warmingDisplay($j, 'Bereit – keine zukünftige Aufwärmvorwahl; vorhandene Vorhersagen werden weiter verglichen');
                return;
            }
            $last = $j['records'] === [] ? null : end($j['records']);
            if ($last !== null && $last['configurationHash'] === $c['hash'] && $last['forecast']['plan']['hash'] === $plan['hash'] && $last['forecast']['end'] > $now) {
                $this->warmingDisplay($j, 'Vorhersage fest gespeichert; Messwerte werden automatisch ergänzt');
                return;
            }
            $r = $this->issueWarming($c, $config, $plan, $now);
            if ($plan['hash'] !== $this->readPlan($b, $config, $now)['hash'] || $c['hash'] !== $this->warmingConfiguration($config)['hash']) {
                throw new RuntimeException('Plan oder Modell während Berechnung geändert; keine Kurve gespeichert.');
            }
            $r['forecast']['issuedAt'] = $this->now();
            if ($r['forecast']['issuedAt'] >= $r['forecast']['end']) {
                throw new RuntimeException('Vorhersagehorizont während Berechnung abgelaufen.');
            }
            $r['forecast']['beforePhase'] = $r['forecast']['phaseStart'] > $r['forecast']['issuedAt'];
            $r['forecastHash'] = SAEF_CreateConfigurationHash($r['forecast']);
            $r['key'] = $r['forecastHash'];
            if ($j['records'] !== []) {
                $index = array_key_last($j['records']);
                $j['records'][$index]['supersededAt'] = $r['forecast']['issuedAt'];
            }
            $j['records'][] = $r;
            $j = Warming::retain($j);
            $this->saveWarming($j);
            $this->warmingDisplay($j, 'Neue experimentelle Aufwärmkurve gespeichert; frühere Planstände bleiben erhalten');
        } catch (Throwable $e) {
            $this->warmingDisplay($j, 'Aufwärmmodell: ' . substr($e->getMessage(), 0, 220));
        }
    }

    private function saveWarming(array $j): void
    {
        $json = Forecast::encode($j);
        Warming::journal($json);
        if ($json !== $this->ReadAttributeString('WarmingJournal')) {
            $this->WriteAttributeString('WarmingJournal', $json);
        }
    }

    /** Up to nine bounded reads per seven-day signal, with strict carry-in and no extrapolated temperatures. */
    private function warmingRows(ArchiveReader $a, int $id, int $from, int $to, bool $carry = false): array
    {
        $rows = [];
        for ($cursor = $from; $cursor < $to; $cursor = $end + 1) {
            $end = min($to, $cursor + 24 * 3600);
            $rows = array_merge($rows, $a->changes($id, $cursor, $end, $carry && $cursor === $from));
        }
        usort($rows, static fn(array $a, array $b): int => $a['TimeStamp'] <=> $b['TimeStamp']);
        return $rows;
    }

    private function warmingHistory(array $c, int $now, ArchiveReader $a): array
    {
        $b = $c['bindings'];
        $end = intdiv($now, 3600) * 3600 - 3600;
        $from = $end - 7 * 86400;
        $hours = [];
        for ($t = $from; $t < $end; $t += 48 * 3600) {
            $hours += $a->hours($b['energy'], $t, min($end, $t + 48 * 3600), true);
        }
        $fan = $this->warmingRows($a, $b['fan'], $from, $now, true);
        $power = $a->changes($b['power'], $end, $now, true);
        usort($power, static fn(array $a, array $b): int => $a['TimeStamp'] <=> $b['TimeStamp']);
        $last = $power === [] ? null : end($power);
        if (
            $last === null || IPS_GetVariable($b['energy'])['VariableUpdated'] < $now - $c['sensorMaxAgeSeconds']
            || IPS_GetVariable($b['energy'])['VariableUpdated'] > $now
        ) {
            throw new RuntimeException('Leistungshistorie fehlt oder Energiezähler ist veraltet.');
        }
        $store = ['gallery' => 0.0, 'sofa' => 0.0];
        for ($t = $from; $t < $now; $t = $next) {
            $next = min($now, $t + 900);
            $u = Warming::stateAt($fan, $t);
            $p = $t < $end ? $hours[intdiv($t, 3600) * 3600] : Warming::stateAt($power, $t);
            if ($p < 0 || $p > $c['options']['maxPowerKw'] * 1.1 || $u < 0 || $u > 1) {
                throw new RuntimeException('Lade-/Ventilatorhistorie außerhalb des gültigen Bereichs.');
            }
            foreach ($store as $room => $s) {
                $store[$room] = Warming::storeStep($s, max(0, $p - $c['options']['standbyKw']), $u, ($next - $t) / 3600, $c['model']['rooms'][$room]);
            }
        }
        return ['from' => $from, 'until' => $now, 'settledUntil' => $end, 'energyHours' => $hours,
            'fanChanges' => $fan, 'powerTail' => $power, 'store' => $store,
            'assumption' => 'Effective charge-memory initialized to zero seven days earlier; recent tail uses total kW.'];
    }

    private function issueWarming(array $c, array $config, array $plan, int $now): array
    {
        $b = $c['bindings'];
        $window = $plan['inputs']['window'] ?? null;
        if ($window !== null) {
            $updated = IPS_GetVariable($window['bindings']['raw'])['VariableUpdated'];
            if (
                $updated > $now || $now - $updated > $c['sensorMaxAgeSeconds']
                || ($window['raw'] !== 0) !== $window['open']
                || $window['setback'] !== $plan['inputs']['values']['setback']
            ) {
                throw new RuntimeException('Fensterdaten veraltet oder Fenster/Regler-Absenkung noch widersprüchlich.');
            }
            $window['updatedAt'] = $updated;
        }
        $a = new ArchiveReader($b['archive'], $c['historyStart']);
        $current = $this->coolingSensors($c, $now);
        if (in_array(null, array_column($current, 'value'), true) || (isset($b['auxiliary']) && GetValue($b['auxiliary']))) {
            throw new RuntimeException('Frische Temperaturwerte erforderlich; Zusatzheizung muss aus sein.');
        }
        $end = min($plan['inputs']['values']['arrival'], $now + 48 * 3600);
        $projection = HeatingPlan::project($plan, ['start' => $now, 'end' => $end], $now);
        $phase = [];
        foreach (['storage', 'fan'] as $name) {
            if ($plan['inputs']['values'][$name . 'Requested']) {
                $phase[] = $plan['inputs']['changedAt'][$name . 'Requested'];
            } elseif ($projection[$name . 'Start'] !== null) {
                $phase[] = $projection[$name . 'Start'];
            }
        }
        if ($phase === []) {
            throw new RuntimeException('Vorwahl enthält keinen gültigen Aufwärmstart.');
        }
        $history = $this->warmingHistory($c, $now, $a);
        $weatherEnd = (int) (ceil(($end + 3600) / 3600) * 3600);
        // Include the full last charging window, even when arrival is earlier in the morning.
        $weatherEnd = max($weatherEnd, Warming::chargeCycle($end)['end']);
        $from = intdiv($now, 3600) * 3600;
        $fetched = null;
        foreach (IPS_GetChildrenIDs($b['weather']) as $id) {
            if (IPS_GetObject($id)['ObjectIdent'] === 'LastSuccess' && IPS_VariableExists($id)) {
                $fetched = GetValue($id);
            }
        }
        if (!is_int($fetched) || $fetched <= 0 || $fetched > $now || $now - $fetched > $c['weatherMaxAgeSeconds']) {
            throw new RuntimeException('Wettervorhersage fehlt oder ist veraltet.');
        }
        $response = Forecast::decode(OMWEATHER_GetHourlyForecastJson(
            $b['weather'],
            $from,
            $weatherEnd + 1,
            '["temperature_2m","direct_normal_irradiance","diffuse_radiation"]'
        ));
        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException('Wetterabruf fehlgeschlagen.');
        }
        $weather = Forecast::weather($response['data']['temperature_2m'] ?? [], $from, $weatherEnd);
        $solar = WarmingWeather::solar($response['data'], $from, $weatherEnd, $c['options']['latitude'], $c['options']['longitude']);
        $floor = isset($b['floor']) ? $a->duty($b['floor'], $now - 86400, $now)['hours'] / 24 : 0.0;
        $fanHours = $a->duty($b['fan'], $now - 86400, $now)['hours'];
        $night = HeatingPlan::cycle($now - 86400);
        $previous = array_sum($a->hours($b['energy'], $night['start'], $night['end'], true));
        $pastOutside = $a->hours($b['outside'], $history['settledUntil'] - 86400, $history['settledUntil']);
        $nights = [];
        $outside = array_column($weather['points'], 'temperature', 'time');
        for ($t = $now; $t < $end; $t = (intdiv($t, 900) + 1) * 900) {
            $cycle = Warming::chargeCycle($t);
            if ($t < $cycle['start'] || isset($nights[$cycle['date']])) {
                continue;
            }
            $temps = array_filter($outside, static fn(int $h): bool => $h >= max($from, $cycle['start']) && $h <= $cycle['end'], ARRAY_FILTER_USE_KEY);
            if ($temps === []) {
                throw new RuntimeException('Ladewetter fehlt.');
            }
            $hdd = max(0, 15 - array_sum($temps) / count($temps));
            foreach (['normal' => 0.0, 'reduced' => 1.0] as $key => $reduced) {
                $features = ['hdd_night' => $hdd, 'reduced' => $reduced, 'hdd_reduced' => $hdd * $reduced,
                    'gallery' => $current['gallery']['value'], 'sofa' => $current['sofa']['value'],
                    'previous_charge' => $previous, 'fan_hours' => $fanHours,
                    'outside_previous' => array_sum($pastOutside) / count($pastOutside)];
                $nights[$cycle['date']][$key] = Forecast::predict($config['model'], $features, $now) + ['features' => $features];
            }
        }
        $f = ['issuedAt' => $now, 'start' => $now, 'end' => $end, 'phaseStart' => min($phase), 'beforePhase' => false,
            'model' => $c['model'], 'window' => $window, 'plan' => $plan, 'projection' => $projection, 'controller' => $c['controller'],
            'initial' => $current, 'store' => $history['store'], 'history' => $history,
            'weather' => $weather, 'weatherFetchedAt' => $fetched, 'radiationSource' => $response['data'], 'solar' => $solar,
            'location' => array_intersect_key($c['options'], array_flip(['latitude', 'longitude'])),
            'chargeNights' => $nights, 'energyModel' => $config['model'], 'maxPowerKw' => $c['options']['maxPowerKw'],
            'fanInitially' => (bool) GetValue($b['fan']), 'floorDuty' => $floor,
            'fireplaceKnownAtIssue' => FireplaceLog::classify(FireplaceLog::read($this->ReadAttributeString('FireplaceLog')), ['start' => $now, 'end' => $end], $now),
            'warnings' => ['experimental_prospective', 'charge_regression_uses_issue_time_features_not_21h_features',
                'charge_uniform_23_07_no_grid_interruptions', 'mass_initialized_equal_to_air', 'effective_store_not_SOC',
                'solar_DNI_DHI_horizontal_approximation', 'floor_duty_last_24h_held', 'future_door_states_unknown',
                'window_ventilation_heat_loss_not_calibrated', 'future_fan_charge_feedback_not_calibrated', 'living_fan_coefficient_not_identifiable', '48h_and_living_18C_not_validated']];
        $f['points'] = Warming::predict($f);
        return ['configurationHash' => $c['hash'], 'bindings' => $b, 'historyStart' => $c['historyStart'],
            'forecast' => $f, 'actual' => [], 'measurementErrors' => [], 'settledAt' => null, 'checkedAt' => 0];
    }

    private function measureWindow(ArchiveReader $a, array $window, int $from, int $until): array
    {
        $w = $window['bindings'];
        $raw = $this->warmingRows($a, $w['raw'], $from, $until, true);
        $contacts = [];
        foreach ($w['contacts'] as $source) {
            $contacts[] = $source + ['rows' => $this->warmingRows($a, $source['VariableID'], $from, $until, true)];
        }
        return Warming::windowOutcome($raw, $contacts, $from, $until, $window['raw']);
    }

    private function measureWarming(array $r, int $now): array
    {
        $f = $r['forecast'];
        if (SAEF_CreateConfigurationHash($f) !== $r['forecastHash']) {
            throw new RuntimeException('Gespeicherte Vorhersage verändert; Vergleich gestoppt.');
        }
        $r['checkedAt'] = $now;
        if ($now > $f['end'] + 7 * 86400) {
            $r['settledAt'] = $now;
            $r['measurementErrors']['expired'] = 'Messfenster abgelaufen';
            return $r;
        }
        $a = new ArchiveReader($r['bindings']['archive'], $r['historyStart']);
        $from = max($r['historyStart'], $f['start'] - 6 * 3600);
        $to = min($now, $f['end'] + 6 * 3600);
        $times = array_values(array_filter(array_column($f['points'], 'time'), static fn(int $t): bool => $t > $f['issuedAt']));
        foreach (['gallery', 'sofa'] as $room) {
            try {
                $rows = $this->warmingRows($a, $r['bindings'][$room], $from, $to);
                $actual = Cooling::measured($rows, $times, $now);
                if ($room === 'sofa' && isset($r['bindings']['sofaBattery'])) {
                    $battery = $this->warmingRows($a, $r['bindings']['sofaBattery'], $from, $to, true);
                    // Duplicate battery reports at one second use the conservative minimum.
                    $indexed = [];
                    foreach ($battery as $p) {
                        $t = $p['TimeStamp'];
                        $indexed[$t] = min($indexed[$t] ?? INF, Forecast::number($p['Value'], 'battery'));
                    }
                    $battery = [];
                    foreach ($indexed as $t => $value) {
                        $battery[] = ['TimeStamp' => $t, 'Value' => $value];
                    }
                    $actual = Warming::maskBattery($actual, $battery);
                }
                foreach ($actual as $t => $p) {
                    if ($p !== null && !isset($r['actual'][$room][$t])) {
                        $r['actual'][$room][$t] = $p;
                    }
                }
                unset($r['measurementErrors'][$room]);
            } catch (Throwable $e) {
                $r['measurementErrors'][$room] = substr($e->getMessage(), 0, 160);
            }
        }
        $r['fireplaceAtComparison'] = FireplaceLog::classify(FireplaceLog::read($this->ReadAttributeString('FireplaceLog')), ['start' => $f['start'], 'end' => $f['end']], $now);
        try {
            $until = min($now, $f['end']);
            if ($until > $f['start']) {
                $r['operationActual'] = ['until' => $until];
                foreach (['fan', 'auxiliary', 'presence', 'floor'] as $key) {
                    if (isset($r['bindings'][$key])) {
                        $r['operationActual'][$key . 'Hours'] = $a->duty($r['bindings'][$key], $f['start'], $until)['hours'];
                    }
                }
                if (isset($f['window']['bindings'])) {
                    $r['operationActual']['window'] = $this->measureWindow($a, $f['window'], $f['start'], $until);
                }
                $energyFrom = (int) (ceil($f['start'] / 3600) * 3600);
                $energyUntil = intdiv($until, 3600) * 3600 - 3600;
                if ($energyUntil > $energyFrom) {
                    $r['operationActual']['energy'] = ['from' => $energyFrom, 'until' => $energyUntil,
                        'kwh' => array_sum($a->hours($r['bindings']['energy'], $energyFrom, $energyUntil, true))];
                }
            }
            unset($r['measurementErrors']['operation']);
        } catch (Throwable $e) {
            $r['measurementErrors']['operation'] = substr($e->getMessage(), 0, 160);
        }
        $r['metrics'] = ['gallery' => Warming::metrics($r, 'gallery'), 'sofa' => Warming::metrics($r, 'sofa')];
        if ($now >= $f['end'] + 6 * 3600) {
            $r['settledAt'] = $now;
        }
        return $r;
    }

    private function warmingDisplay(array $journal, string $status): void
    {
        $this->SetValue('WarmingStatus', $status);
        $this->SetValue('WarmingComparison', WarmingView::render($journal, $status));
    }
}
