<?php
declare(strict_types=1);

define('VARIABLETYPE_STRING', 3);
require_once __DIR__ . '/../distribution/libs/Navimow/SeasonState.php';
require_once __DIR__ . '/../tools/season-controller.php';

$config = ['state' => 1, 'mode' => 2, 'info' => 3, 'supply' => 4, 'rawSupply' => 5,
    'power' => 6, 'online' => 7, 'vehicle' => 8, 'lastStatus' => 9,
    'account' => 10, 'device' => 11, 'batteryMonitor' => 12, 'powerScript' => 13, 'batterySource' => 14];
$values = [1 => json_encode(['version' => 1, 'mode' => 'winter', 'since' => '2026-11-22',
    'applied' => false, 'batteryExcludedBefore' => false]), 2 => 1, 3 => '', 4 => false,
    5 => false, 6 => 0.0, 7 => false, 8 => 0, 9 => 0];
$settings = [['Instance' => 14, 'Excluded' => false], ['Instance' => 15, 'Excluded' => false, 'Alias' => 'Camera']];
$properties = [10 => ['SeasonStateVariable' => '1'], 11 => ['SeasonStateVariable' => '1'],
    12 => ['DeviceSettings' => json_encode($settings)]];
$timers = [];
$calls = [];
function IPS_VariableExists(int $id): bool
{
    return $id > 0 && $id < 10;
}
function IPS_InstanceExists(int $id): bool
{
    return in_array($id, [10, 11, 12], true);
}
function IPS_HasChanges(int $id): bool
{
    return false;
}
function IPS_ScriptExists(int $id): bool
{
    return $id === 13;
}
function IPS_SemaphoreEnter(string $name, int $wait): bool
{
    return true;
}
function IPS_SemaphoreLeave(string $name): void
{
}
function GetValueString(int $id): string
{
    return $GLOBALS['values'][$id];
}
function GetValueInteger(int $id): int
{
    return $GLOBALS['values'][$id];
}
function GetValueFloat(int $id): float
{
    return $GLOBALS['values'][$id];
}
function GetValueBoolean(int $id): bool
{
    return $GLOBALS['values'][$id];
}
function SetValueString(int $id, string $value): void
{
    $GLOBALS['values'][$id] = $value;
}
function SetValueInteger(int $id, int $value): void
{
    $GLOBALS['values'][$id] = $value;
}
function SetValueBoolean(int $id, bool $value): void
{
    $GLOBALS['values'][$id] = $value;
}
function IPS_GetProperty(int $id, string $key): string
{
    return $GLOBALS['properties'][$id][$key];
}
function IPS_SetProperty(int $id, string $key, string $value): void
{
    $GLOBALS['properties'][$id][$key] = $value;
}
function IPS_ApplyChanges(int $id): void
{
    $GLOBALS['calls'][] = $id;
}
function IPS_SetScriptTimer(int $id, int $interval): void
{
    $GLOBALS['timers'][$id] = $interval;
}
function NAVDV_RefreshLocalMap(int $id): void
{
}
function IPS_GetVariable(int $id): array
{
    return ['VariableUpdated' => time()];
}
function checkSeason(bool $pass, string $message): void
{
    if (!$pass) {
        throw new RuntimeException($message);
    }
}
function changeSeason(int $value): void
{
    navimowSeasonController($GLOBALS['config'], ['SENDER' => 'WebFront', 'VARIABLE' => 2, 'VALUE' => $value]);
}
changeSeason(1);
$actual = json_decode($properties[12]['DeviceSettings'], true);
checkSeason($actual[0]['Excluded'] && $actual[1] === $settings[1], 'Winter changed camera or failed exclusion.');
checkSeason($values[4] && $timers[13] === 0, 'Winter power warning or timer not suppressed.');
checkSeason(json_decode($values[1], true)['applied'], 'Winter incomplete.');
$beforeCalls = $calls;
navimowSeasonController($config, ['SENDER' => 'Variable']);
checkSeason($beforeCalls === $calls, 'Passive event reconfigured instances.');
try {
    changeSeason(0);
    throw new LogicException('Direct season entry accepted.');
} catch (RuntimeException $e) {
    checkSeason(str_contains($e->getMessage(), 'Zuerst'), 'Wrong transition error.');
}
changeSeason(2);
try {
    changeSeason(0);
    throw new LogicException('Stale commissioning accepted.');
} catch (RuntimeException $e) {
    checkSeason(str_contains($e->getMessage(), 'Freigabe'), 'Wrong freshness error.');
}
$values[5] = true;
$values[6] = 4.0;
$values[7] = true;
$values[8] = 2;
$values[9] = time();
changeSeason(0);
checkSeason(!json_decode($properties[12]['DeviceSettings'], true)[0]['Excluded'], 'Battery baseline not restored.');
checkSeason(json_decode($values[1], true)['mode'] === 'season', 'Season not restored.');
checkSeason(json_decode($properties[12]['DeviceSettings'], true)[1] === $settings[1], 'Camera changed.');
echo "season-controller: PASS\n";
