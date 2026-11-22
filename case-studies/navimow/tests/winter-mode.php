<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
if (!defined('VARIABLETYPE_STRING')) {
    define('VARIABLETYPE_STRING', 3);
}
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';
require_once __DIR__ . '/../distribution/NavimowAccount/module.php';

$seasonJson = json_encode(['version' => 1, 'mode' => 'winter', 'since' => '2026-11-22', 'applied' => true]);
function IPS_VariableExists(int $id): bool
{
    return $id === 9876;
}
function IPS_GetVariable(int $id): array
{
    return ['VariableType' => 3];
}
function GetValueString(int $id): string
{
    return $GLOBALS['seasonJson'];
}
function winterAssert(bool $pass, string $message): void
{
    if (!$pass) {
        throw new RuntimeException($message);
    }
}

$device = new NavimowDevice(4444);
$device->Create();
$device->testSetProperty('DeviceId', 'SYNTHETIC');
$device->testSetProperty('SeasonStateVariable', '9876');
$device->ApplyChanges();
$device->testSetVariable('BatteryLevel', 97);
$before = $device->testSnapshotPersistentState();
$calls = 0;
$device->testSetParentHandler(static function () use (&$calls): string {
    ++$calls;
    return '{}';
});
foreach (['RefreshStatus', 'RefreshLocalMap', 'Dock', 'Pause', 'Resume'] as $method) {
    $device->$method();
}
winterAssert($calls === 0, 'Winter operation contacted parent.');
winterAssert($device->testReadVariable('BatteryLevel') === 97, 'Stored battery changed.');
winterAssert($device->testSnapshotPersistentState()['attributes'] === $before['attributes'], 'Winter changed retained data.');
winterAssert($device->testTimerInterval('LocalMapRefresh') === 0, 'Winter map timer active.');
winterAssert(str_contains($device->GetVisualizationTile(), 'Winterpause'), 'Winter label absent.');

$account = new NavimowAccount(4445);
$account->Create();
$account->testSetProperty('SeasonStateVariable', '9876');
$account->PollReadOnlyStatus();
$account->WakePolling();
winterAssert($account->testTimerInterval('PollStatus') === 0, 'Winter polling timer active.');
winterAssert($account->testChildMessages() === [], 'Winter polling broadcast.');
$result = json_decode($account->ForwardData(json_encode([
    'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}', 'SchemaVersion' => 1,
    'Function' => 'SendCommand', 'DeviceId' => 'SYNTHETIC', 'Command' => 'Dock',
])), true);
winterAssert(($result['kind'] ?? null) === 'season', 'Direct account command not blocked.');

foreach (['', 'broken', '{}', '{"version":1,"mode":"season","since":"","applied":false}'] as $bad) {
    $seasonJson = $bad;
    winterAssert(!Navimow\SeasonState::allowsCommands(Navimow\SeasonState::read('9876')), 'Invalid state allowed commands.');
}
winterAssert(!Navimow\SeasonState::allowsRead(Navimow\SeasonState::read('0')), 'Root reference accepted.');
$seasonJson = json_encode(['version' => 1, 'mode' => 'commissioning', 'since' => '2026-11-22', 'applied' => true]);
$state = Navimow\SeasonState::read('9876');
winterAssert(Navimow\SeasonState::allowsRead($state) && !Navimow\SeasonState::allowsCommands($state), 'Commissioning bypasses command guard.');
winterAssert(Navimow\SeasonState::allowsCommands(Navimow\SeasonState::read('')), 'Unconfigured devices changed.');
echo "winter-mode: PASS\n";
