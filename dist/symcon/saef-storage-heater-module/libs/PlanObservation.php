<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;
use Throwable;

/** Independent ledger and subscriptions; never issues a heater command. */
trait PlanObservation
{
    private function createPlan(): void
    {
        $this->RegisterPropertyString('PlanBindingsJSON', '{}');
        $this->RegisterAttributeString('PlanJournal', '{"schema":1,"sequence":0,"discarded":0,"plans":[],"revisions":[]}');
        $this->RegisterTimer('PlanRefresh', 0, 'SHF_RefreshPlan($_IPS["TARGET"]);');
        $this->RegisterVariableString('PlanStatus', 'Heizungsvorwahl – Prognosestatus', '', 210);
        $this->RegisterVariableString('PlanComparison', 'Heizungsvorwahl und Prognosestände', '~HTMLBox', 220);
    }

    public function GetPlanJournalJson(): string
    {
        return Forecast::encode(HeatingPlan::journal($this->ReadAttributeString('PlanJournal')));
    }

    private function stopPlan(): void
    {
        $this->SetTimerInterval('PlanRefresh', 0);
        foreach ($this->GetMessageList() as $sender => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage((int) $sender, $message);
            }
        }
        $this->SetValue('PlanStatus', 'Planbeobachtung nicht aktiv');
        $this->SetValue('PlanComparison', PlanView::render([], 'Planbeobachtung nicht aktiv'));
    }

    private function planConfiguration(array $config): ?array
    {
        $b = Forecast::decode($this->ReadPropertyString('PlanBindingsJSON'));
        if ($b === []) {
            return null;
        }
        $keys = ['arrival', 'storageRequested', 'fanRequested', 'storageEvent', 'fanEvent', 'resetEvent',
            'regulatorEnabled', 'regulatorActive', 'setpoint', 'setback', 'winter'];
        if (array_diff($keys, array_keys($b)) !== [] || array_diff(array_keys($b), $keys) !== []) {
            throw new RuntimeException('Incomplete or unknown preselection binding.');
        }
        if (!isset($config['bindings']['presence'])) {
            throw new RuntimeException('Presence source required for preselection semantics.');
        }
        $b += array_intersect_key($config['bindings'], array_flip(['enabled', 'reduced', 'presence']));
        foreach ($b as $key => $id) {
            if (!is_int($id) || $id <= 0 || !IPS_ObjectExists($id) || IPS_GetObject($id)['ParentID'] === $this->InstanceID) {
                throw new RuntimeException('Invalid external preselection source: ' . $key);
            }
            if (str_ends_with($key, 'Event')) {
                if (!IPS_EventExists($id)) {
                    throw new RuntimeException('Expected preselection event: ' . $key);
                }
                HeatingPlan::event(IPS_GetEvent($id));
            } else {
                $types = match ($key) {
                    'arrival' => [VARIABLETYPE_INTEGER],
                    'setpoint' => [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT],
                    default => [VARIABLETYPE_BOOLEAN],
                };
                if (!IPS_VariableExists($id) || !in_array(IPS_GetVariable($id)['VariableType'], $types, true)) {
                    throw new RuntimeException('Wrong preselection variable type: ' . $key);
                }
            }
        }
        return $b;
    }

    private function configurePlan(array $config): void
    {
        try {
            $b = $this->planConfiguration($config);
            if ($b === null) {
                return;
            }
            foreach ($b as $key => $id) {
                $this->RegisterReference($id);
                $messages = str_ends_with($key, 'Event')
                    ? [EM_UPDATE, EM_CHANGEACTIVE, EM_CHANGECYCLIC, EM_CHANGECYCLICDATEFROM, EM_CHANGECYCLICTIMEFROM]
                    : [VM_UPDATE];
                foreach ($messages as $message) {
                    $this->RegisterMessage($id, $message);
                }
            }
            $this->SetValue('PlanStatus', 'Vorwahl verbunden; Planübernahme steht an');
            $this->SetTimerInterval('PlanRefresh', 5000);
        } catch (Throwable $e) {
            $this->planError($e);
        }
    }

    /** Timestamp is a Symcon message counter, not wall-clock time. */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if (!$this->ReadPropertyBoolean('Enabled') || !is_int($SenderID) || $SenderID <= 0) {
            return;
        }
        if (in_array($Message, $this->GetMessageList()[$SenderID] ?? [], true)) {
            // Coalesce cascaded input/timer/control writes into a settled snapshot.
            $this->SetTimerInterval('PlanRefresh', 5000);
        }
    }

    public function RefreshPlan(): void
    {
        $this->SetTimerInterval('PlanRefresh', 0);
        if (!$this->ReadPropertyBoolean('Enabled')) {
            return;
        }
        $lock = 'StorageHeaterForecast.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 100)) {
            $this->SetTimerInterval('PlanRefresh', 5000);
            return;
        }
        try {
            $config = $this->configuration();
            $now = $this->now();
            $this->observePlan($config, $now);
            $this->observeWarming($config, $now);
        } catch (Throwable $e) {
            $this->planError($e);
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    private function readPlan(array $b, array $config, int $now): array
    {
        $input = ['bindings' => $b, 'configurationHash' => $config['hash'], 'values' => [], 'changedAt' => [], 'events' => []];
        foreach ($b as $key => $id) {
            if (str_ends_with($key, 'Event')) {
                $input['events'][$key] = HeatingPlan::event(IPS_GetEvent($id));
            } else {
                $input['values'][$key] = GetValue($id);
                $input['changedAt'][$key] = IPS_GetVariable($id)['VariableChanged'];
            }
        }
        if (
            $input['values']['arrival'] < 0 || $input['values']['arrival'] > $now + 15 * 86400
            || Forecast::number($input['values']['setpoint'], 'setpoint') < 5 || $input['values']['setpoint'] > 35
        ) {
            throw new RuntimeException('Preselection time or thermostat target outside supported range.');
        }
        $window = $this->readWindow();
        if ($window !== null) {
            $input['window'] = $window;
        }
        return ['observedAt' => $now, 'hash' => SAEF_CreateConfigurationHash($input), 'inputs' => $input];
    }

    private function observePlan(array $config, int $now): void
    {
        try {
            $b = $this->planConfiguration($config);
            if ($b === null) {
                return;
            }
            $j = HeatingPlan::journal($this->ReadAttributeString('PlanJournal'));
            $plan = $this->readPlan($b, $config, $now);
            if ($this->readPlan($b, $config, $now)['hash'] !== $plan['hash']) {
                $this->SetTimerInterval('PlanRefresh', 5000);
                return;
            }
            $lastPlan = $j['plans'] === [] ? null : end($j['plans']);
            if ($lastPlan === null || $lastPlan['hash'] !== $plan['hash']) {
                $j['plans'][] = $plan;
                // Persist input knowledge even if weather/archive calculation fails later.
                $j = HeatingPlan::retain($j);
                $this->WriteAttributeString('PlanJournal', Forecast::encode($j));
            } else {
                $plan = $lastPlan;
            }
            $cycle = HeatingPlan::cycle($now);
            $originalRecord = $this->journal()['records'][$cycle['date']] ?? [];
            $original = ($originalRecord['configurationHash'] ?? '') === $config['hash'] ? ($originalRecord['forecast'] ?? null) : null;
            $phase = $now < $cycle['issueFrom'] ? 'waiting' : ($now < $cycle['start'] ? 'before_charge' : 'during_charge');
            $key = SAEF_CreateConfigurationHash([$plan['hash'], $cycle['date'], $phase, $original['issuedAt'] ?? null]);
            $last = $j['revisions'] === [] ? null : end($j['revisions']);
            if (
                $last !== null && $last['key'] === $key
                && (($last['estimate']['status'] ?? '') !== 'data_error' || $now - $last['issuedAt'] < 300)
            ) {
                $this->planDisplay($j);
                return;
            }
            $projection = HeatingPlan::project($plan, $cycle, $now);
            $r = ['sequence' => ++$j['sequence'], 'key' => $key, 'issuedAt' => $now,
                'cycle' => $cycle, 'phase' => $phase, 'plan' => $plan, 'projection' => $projection,
                'originalIssuedAt' => $original['issuedAt'] ?? null, 'baseline' => null,
                'estimate' => ['status' => 'waiting_for_issue_window', 'kwh' => null]];
            if ($phase !== 'waiting') {
                try {
                    $archive = new ArchiveReader($config['bindings']['archive'], $config['historyStart']);
                    $baseline = $original;
                    if ($now < $cycle['start']) {
                        $assumptions = $projection['segments'][0];
                        $candidate = $this->makePrediction($this->emptyRecord($cycle, $config, $now, 'plan_revision'), $config, $archive, $now, $assumptions);
                        $baseline = $candidate['forecast'];
                    } elseif ($baseline === null) {
                        foreach (array_reverse($j['revisions']) as $previous) {
                            if (
                                $previous['cycle']['date'] === $cycle['date'] && $previous['baseline'] !== null
                                && $previous['plan']['inputs']['configurationHash'] === $config['hash']
                                && $previous['baseline']['issuedAt'] < $cycle['start']
                            ) {
                                $baseline = $previous['baseline'];
                                break;
                            }
                        }
                    }
                    $past = $this->planPast($config, $cycle, $archive, $now);
                    $r['past'] = $past;
                    $r['baseline'] = $baseline;
                    $r['estimate'] = $baseline === null
                        ? ['status' => 'no_precharge_baseline', 'kwh' => null]
                        : HeatingPlan::estimate($baseline, $projection, $past, $now);
                    // Reread after archive/weather calls: never persist a forecast for a torn plan.
                    if ($this->readPlan($b, $config, $now)['hash'] !== $plan['hash']) {
                        $this->SetTimerInterval('PlanRefresh', 5000);
                        $this->SetValue('PlanStatus', 'Vorwahl ändert sich; erneute Übernahme folgt');
                        return;
                    }
                } catch (Throwable $e) {
                    $r['estimate'] = ['status' => 'data_error', 'kwh' => null, 'error' => substr($e->getMessage(), 0, 220)];
                }
            }
            // Actual save time, never the source variable's older change timestamp.
            $r['issuedAt'] = $this->now();
            $j['revisions'][] = $r;
            $j = HeatingPlan::retain($j);
            $this->WriteAttributeString('PlanJournal', Forecast::encode($j));
            $this->planDisplay($j);
        } catch (Throwable $e) {
            $this->planError($e);
        }
    }

    private function planPast(array $config, array $cycle, ArchiveReader $archive, int $now): array
    {
        if ($now <= $cycle['start']) {
            return [];
        }
        $b = $config['bindings'];
        $past = ['mixed' => false, 'fanHours' => $archive->duty($b['fan'], $cycle['start'], $now)['hours']];
        $duration = ($now - $cycle['start']) / 3600;
        foreach (['enabled', 'reduced'] as $name) {
            $d = $archive->duty($b[$name], $cycle['start'], $now);
            $past[$name] = $d['hours'] === $duration;
            $past['mixed'] = $past['mixed'] || ($d['hours'] > 0 && $d['hours'] < $duration) || $d['changes'] > 0;
        }
        // Last fully settled hour only. The unmeasured tail is explicit.
        $until = max($cycle['start'], min($cycle['end'], intdiv($now, 3600) * 3600 - 3600));
        if ($until > $cycle['start']) {
            $past['kwh'] = array_sum($archive->hours($b['energy'], $cycle['start'], $until, true));
            $past['until'] = $until;
        }
        return $past;
    }

    private function planDisplay(array $j): void
    {
        $last = $j['revisions'] === [] ? [] : end($j['revisions']);
        $status = PlanView::status($last['estimate']['status'] ?? 'waiting_for_issue_window');
        $this->SetValue('PlanStatus', $status);
        $this->SetValue('PlanComparison', PlanView::render($j, $status));
    }

    private function planError(Throwable $e): void
    {
        $message = 'Vorwahl prüfen: ' . substr($e->getMessage(), 0, 220);
        $this->SetValue('PlanStatus', $message);
        $this->SetValue('PlanComparison', PlanView::render([], $message));
    }
}
