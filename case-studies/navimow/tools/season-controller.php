<?php

declare(strict_types=1);

/** Installation binding supplies positive IDs; this file never creates objects or moves the mower. */
function navimowSeasonController(array $config, array $request): void
{
    foreach (['state', 'mode', 'info', 'supply', 'rawSupply', 'power', 'online', 'vehicle', 'lastStatus'] as $key) {
        if (
            !isset($config[$key]) || !is_int($config[$key]) || $config[$key] <= 0
            || !IPS_VariableExists($config[$key])
        ) {
            throw new RuntimeException('Invalid variable binding: ' . $key);
        }
    }
    foreach (['account', 'device', 'batteryMonitor'] as $key) {
        if (
            !isset($config[$key]) || !is_int($config[$key]) || $config[$key] <= 0
            || !IPS_InstanceExists($config[$key])
        ) {
            throw new RuntimeException('Invalid instance binding: ' . $key);
        }
        if (IPS_HasChanges($config[$key])) {
            throw new RuntimeException('Pending instance configuration: ' . $key);
        }
    }
    if (!isset($config['powerScript']) || $config['powerScript'] <= 0 || !IPS_ScriptExists($config['powerScript'])) {
        throw new RuntimeException('Invalid power script.');
    }
    $lock = 'NavimowSeason:' . $config['state'];
    if (!IPS_SemaphoreEnter($lock, 3000)) {
        throw new RuntimeException('Season transition busy; no change.');
    }
    try {
        $state = json_decode(GetValueString($config['state']), true, 16, JSON_THROW_ON_ERROR);
        Navimow\SeasonState::decode(json_encode($state, JSON_THROW_ON_ERROR));
        $mode = $state['mode'];
        $requested = null;
        if (($request['SENDER'] ?? '') === 'WebFront' || ($request['SENDER'] ?? '') === 'Action') {
            if (($request['VARIABLE'] ?? null) !== $config['mode'] || !is_int($request['VALUE'] ?? null)) {
                throw new RuntimeException('Invalid season action.');
            }
            $requested = [0 => 'season', 1 => 'winter', 2 => 'commissioning'][$request['VALUE']] ?? null;
            if ($requested === null) {
                throw new RuntimeException('Invalid requested mode.');
            }
        }
        if ($requested !== null && ($requested !== $mode || !$state['applied'])) {
            if ($requested === 'season') {
                if ($mode !== 'commissioning' || !isset($state['commissioningAt'])) {
                    throw new RuntimeException('Zuerst Wiederinbetriebnahme waehlen.');
                }
                $freshSince = max(time() - 300, $state['commissioningAt']);
                if (
                    !GetValueBoolean($config['rawSupply']) || !GetValueBoolean($config['online'])
                    || GetValueInteger($config['vehicle']) !== 2
                    || GetValueInteger($config['lastStatus']) < $freshSince
                    || IPS_GetVariable($config['power'])['VariableUpdated'] < $freshSince
                    || GetValueFloat($config['power']) < 2.0
                ) {
                    throw new RuntimeException('Freigabe fehlt: frischer Dockstatus und Stationsleistung erforderlich.');
                }
            }
            $state['mode'] = $requested;
            $state['applied'] = false;
            $state['changedAt'] = time();
            if ($requested === 'winter' && $mode !== 'winter') {
                $state['since'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
            }
            if ($requested === 'commissioning') {
                $state['commissioningAt'] = time();
            }
            SetValueString($config['state'], json_encode($state, JSON_THROW_ON_ERROR));
            $mode = $requested;
        }
        // Only explicit actions reconcile configuration; periodic checks never silently repair it.
        if ($requested !== null) {
            $settings = json_decode(IPS_GetProperty($config['batteryMonitor'], 'DeviceSettings'), true, 64, JSON_THROW_ON_ERROR);
            $matches = array_keys(array_filter($settings, static fn (array $row): bool =>
                ($row['Instance'] ?? null) === $config['batterySource']));
            if (count($matches) !== 1 || !is_bool($state['batteryExcludedBefore'] ?? null)) {
                throw new RuntimeException('Battery binding or restore baseline missing.');
            }
            $index = $matches[0];
            $excluded = $mode === 'season' ? $state['batteryExcludedBefore'] : true;
            if ($settings[$index]['Excluded'] !== $excluded) {
                $settings[$index]['Excluded'] = $excluded;
                IPS_SetProperty($config['batteryMonitor'], 'DeviceSettings', json_encode($settings, JSON_THROW_ON_ERROR));
                IPS_ApplyChanges($config['batteryMonitor']);
                $readback = json_decode(IPS_GetProperty($config['batteryMonitor'], 'DeviceSettings'), true, 64, JSON_THROW_ON_ERROR);
                if ($readback !== $settings) {
                    throw new RuntimeException('Battery configuration readback failed.');
                }
            }
            if ($mode !== 'season') {
                IPS_SetScriptTimer($config['powerScript'], 0);
            }
            foreach (['account', 'device'] as $key) {
                if (IPS_GetProperty($config[$key], 'SeasonStateVariable') !== (string) $config['state']) {
                    throw new RuntimeException('Season binding mismatch: ' . $key);
                }
            }
            $state['applied'] = true;
            SetValueString($config['state'], json_encode($state, JSON_THROW_ON_ERROR));
            IPS_ApplyChanges($config['account']);
            IPS_ApplyChanges($config['device']);
            NAVDV_RefreshLocalMap($config['device']);
        }
        SetValueInteger($config['mode'], ['season' => 0, 'winter' => 1, 'commissioning' => 2][$mode]);
        SetValueBoolean($config['supply'], $mode !== 'season' || GetValueBoolean($config['rawSupply']));
        $label = Navimow\SeasonState::label($state);
        SetValueString($config['info'], $label !== '' ? $label : 'Saisonbetrieb');
    } catch (Throwable $exception) {
        if (
            isset($state) && is_array($state) && isset($requested) && $requested !== null
            && ($state['mode'] ?? null) === $requested
        ) {
            $state['applied'] = false;
            SetValueString($config['state'], json_encode($state, JSON_THROW_ON_ERROR));
        }
        SetValueString($config['info'], 'Wintersteuerung: ' . $exception->getMessage());
        throw $exception;
    } finally {
        IPS_SemaphoreLeave($lock);
    }
}
