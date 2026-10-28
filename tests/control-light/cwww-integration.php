<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCore;
use SAEF\CaseStudy\ControlLight\ControlLightRuntime;

// Reuse the exact adapter/Ensure fake and its tests; then cross the real boundary.
require __DIR__ . '/cwww-target-runtime.php';
require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightRuntime.php';
CwwwTargetFake::$failCreate = false;
CwwwTargetFake::$writeHook = null;
$configuration = ControlLightCore::normalizeConfiguration([
    'identState' => 'state', 'identDim' => 'brightness', 'identTemp' => 'color_temp_kelvin',
    'identColor' => '', 'dimmerTargetMax' => 255, 'brightnessSemantics' => 'reported',
    'brightnessOffStateTransition' => ['mode' => 'target-turns-on'],
    'colorTemperatureTolerance' => 5,
    'confirmation' => ['timeoutMilliseconds' => 50, 'pollIntervalMilliseconds' => 10],
]);
$facades = [];
foreach (['state' => 0, 'brightness' => 1, 'colorTemperature' => 1] as $key => $type) {
    $facades[$key] = IPS_CreateVariable($type);
}
$statistics = [];
foreach (['COMMANDS', 'CONFIRMATION_TIMEOUTS', 'LAST_FEEDBACK', 'ERRORS'] as $key) {
    $statistics[$key] = IPS_CreateVariable(1);
}
$resources = ['localVariableIDs' => $facades,
    'targetVariableIDs' => ['state' => 11005, 'brightness' => 11006, 'colorTemperature' => 11007],
    'availabilityVariableID' => 11004, 'externalTriggers' => []];
$diagnostics = ['statisticIDs' => $statistics];
$sendQueue = [];
$wire = [];
$mode = 'valid';
$device = ['state' => 'OFF', 'brightness' => 128, 'color_mode' => 'cwww', 'color' => ['c' => 0, 'w' => 255]];
$observe = static function () use (&$device, $runtime): void {
    CwwwTargetFake::$values[11001] = json_encode($device, JSON_THROW_ON_ERROR);
    $runtime->receive(11001);
};
$observe();
CwwwTargetFake::$actionHook = static function (int $id, mixed $value) use ($runtime, &$sendQueue, &$wire): bool {
    if ($id === 11002) {
        $command = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
        $wire[] = $command;
        $sendQueue[] = $command;
    } else {
        $runtime->dispatch($id, $value);
    }
    return true;
};
CwwwTargetFake::$sleepHook = static function () use (&$sendQueue, &$device, &$mode, $observe, $runtime, $assert): void {
    if ($sendQueue === []) {
        return;
    }
    $assert(!CwwwTargetFake::$locked, 'Target transport lock released before confirmation wait');
    $assert(CwwwTargetFake::$otherLocks['SAEF_CONTROL_LIGHT_16000'] === true, 'Common command lock remains held');
    $command = array_shift($sendQueue);
    if ($mode === 'missing') {
        return;
    }
    if ($mode === 'partial') {
        CwwwTargetFake::$values[11001] = '{"state":"ON"}';
        try {
            $runtime->receive(11001);
        } catch (InvalidArgumentException) {
            // Separate receive process reports failure; command continues to timeout.
        }
        return;
    }
    $device = array_replace($device, $command);
    $observe();
};
$dispatch = static function (string $key, mixed $value) use ($runtime, $resources, $configuration, $diagnostics): array {
    $runtime->assertReady();
    $result = ControlLightRuntime::dispatchTargetAction(16000, $key, $value, $resources, $configuration, $diagnostics);
    $runtime->assertReady();
    return $result;
};
$startCount = $count;
$result = $dispatch('brightness', 10);
$assert($result['status'] === 'confirmed', 'Common runtime confirms delayed packet');
$assert($wire === [['state' => 'ON', 'brightness' => 26, 'transition' => 0]], 'No preliminary ON or full-bright flash command');
$assert(GetValue($facades['state']) === true && GetValue($facades['brightness']) === 10, 'Facade maps observed bytes to percent');
$before = count($wire);
$assert($dispatch('brightness', 10)['status'] === 'already_confirmed' && count($wire) === $before, 'Idempotent command does not resend');
$dispatch('colorTemperature', 4800);
$assert(GetValue($facades['colorTemperature']) === 4800 && GetValue($facades['brightness']) === 10, 'Kelvin preserves brightness');
$dispatch('state', false);
$assert(GetValue($facades['state']) === false && GetValue($facades['brightness']) === 10, 'OFF retains reported brightness');
$mode = 'missing';
$beforeState = GetValue($facades['state']);
$reject(fn() => $dispatch('brightness', 30));
$assert(GetValue($facades['state']) === $beforeState && GetValue($facades['brightness']) === 10, 'Missing feedback never becomes optimistic success');
$assert(GetValue($statistics['CONFIRMATION_TIMEOUTS']) === 1, 'Common timeout diagnostic incremented');
$mode = 'partial';
$reject(fn() => $dispatch('brightness', 40));
$assert(GetValue(11004) === false && GetValue($statistics['CONFIRMATION_TIMEOUTS']) === 2, 'Partial packet fails confirmation and readiness');
$before = count($wire);
$reject(fn() => $dispatch('brightness', 10));
$assert(count($wire) === $before, 'Readiness gate also blocks already-matching stale values');
$mode = 'valid';
$observe();
CwwwTargetFake::$values[11003] = false;
$reject(fn() => $dispatch('state', true));
$assert(count($wire) === $before, 'Offline blocks command');
CwwwTargetFake::$values[11003] = true;
$runtime->receive(11003);
$reject(fn() => $dispatch('state', true));
$observe();
$dispatch('brightness', 20);
$assert(GetValue($facades['brightness']) === 20 && GetValue($facades['state']) === true, 'Recovery from complete new observation');
$assert(!in_array(true, CwwwTargetFake::$otherLocks, true) && !CwwwTargetFake::$locked, 'All locks released');
echo 'CWWW/common integration: ' . ($count - $startCount) . " assertions passed; offline only.\n";
