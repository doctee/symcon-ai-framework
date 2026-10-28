<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\CwwwLightAdapter;
use SAEF\CaseStudy\ControlLight\CwwwLightTargetRuntime;

// Standalone offline process: no bootstrap, credentials, network or live bindings.
final class CwwwTargetFake
{
    public static array $objects = [];
    public static array $variables = [];
    public static array $values = [];
    public static array $writes = [];
    public static array $actions = [];
    public static bool $locked = false;
    public static bool $rejectAction = false;
    public static ?int $failWrite = null;
    public static ?Closure $writeHook = null;
    public static int $nextID = 20000;
    public static array $events = [];
    public static bool $failCreate = false;
    public static ?Closure $actionHook = null;
    public static ?Closure $sleepHook = null;
    public static array $otherLocks = [];
    public static array $logs = [];
}
function IPS_ScriptExists(int $id): bool
{
    return (CwwwTargetFake::$objects[$id]['ObjectType'] ?? null) === 3;
}
function IPS_VariableExists(int $id): bool
{
    return isset(CwwwTargetFake::$variables[$id]);
}
function IPS_GetObjectIDByIdent(string $ident, int $parent): int|false
{
    foreach (CwwwTargetFake::$objects as $id => $object) {
        if (($object['ParentID'] ?? null) === $parent && ($object['ObjectIdent'] ?? null) === $ident) {
            return $id;
        }
    }
    return false;
}
function IPS_CreateVariable(int $type): int
{
    if (CwwwTargetFake::$failCreate) {
        return 0; // Fault injection only; runtime must stop before using this as an ID.
    }
    $id = CwwwTargetFake::$nextID++;
    CwwwTargetFake::$objects[$id] = ['ObjectType' => 2];
    CwwwTargetFake::$variables[$id] = ['VariableType' => $type, 'VariableAction' => 0, 'VariableCustomAction' => 0];
    CwwwTargetFake::$values[$id] = match ($type) {
        0 => false,
        1 => 0,
        2 => 0.0,
        3 => '',
        default => throw new InvalidArgumentException('Unsupported fixture type'),
    };
    return $id;
}
function IPS_SetParent(int $id, int $parent): void
{
    if ($id <= 0 || $parent <= 0) {
        throw new LogicException('Invalid parent mutation');
    }
    CwwwTargetFake::$objects[$id]['ParentID'] = $parent;
}
function IPS_SetIdent(int $id, string $ident): void
{
    CwwwTargetFake::$objects[$id]['ObjectIdent'] = $ident;
}
function IPS_SetName(int $id, string $name): void
{
    CwwwTargetFake::$objects[$id]['ObjectName'] = $name;
}
function IPS_SetPosition(int $id, int $position): void
{
    CwwwTargetFake::$objects[$id]['ObjectPosition'] = $position;
}
function IPS_SetHidden(int $id, bool $hidden): void
{
    CwwwTargetFake::$objects[$id]['ObjectIsHidden'] = $hidden;
}
function IPS_SetVariableCustomAction(int $id, int $action): void
{
    CwwwTargetFake::$variables[$id]['VariableCustomAction'] = $action;
}
function IPS_CreateEvent(int $type): int
{
    $id = CwwwTargetFake::$nextID++;
    CwwwTargetFake::$objects[$id] = ['ObjectType' => 4];
    CwwwTargetFake::$events[$id] = ['EventType' => $type];
    return $id;
}
function IPS_GetEvent(int $id): array
{
    return CwwwTargetFake::$events[$id];
}
function IPS_SetEventTrigger(int $id, int $type, int $variable): void
{
    CwwwTargetFake::$events[$id]['TriggerType'] = $type;
    CwwwTargetFake::$events[$id]['TriggerVariableID'] = $variable;
}
function IPS_SetEventAction(int $id, string $action, array $parameters): void
{
    CwwwTargetFake::$events[$id]['EventActionID'] = $action;
    CwwwTargetFake::$events[$id]['EventActionParameters'] = $parameters;
}
function IPS_SetEventActive(int $id, bool $active): void
{
    CwwwTargetFake::$events[$id]['EventActive'] = $active;
}
function IPS_ObjectExists(int $id): bool
{
    if ($id <= 0) {
        throw new LogicException('Root must never be queried.');
    }
    return isset(CwwwTargetFake::$objects[$id]);
}
function IPS_GetObject(int $id): array
{
    return CwwwTargetFake::$objects[$id];
}
function IPS_GetVariable(int $id): array
{
    return CwwwTargetFake::$variables[$id] + ['VariableChanged' => 1, 'VariableUpdated' => 1];
}
function GetValue(int $id): mixed
{
    return CwwwTargetFake::$values[$id];
}
function SetValue(int $id, mixed $value): bool
{
    if ($id === CwwwTargetFake::$failWrite) {
        return false;
    }
    CwwwTargetFake::$values[$id] = $value;
    CwwwTargetFake::$variables[$id]['VariableUpdated'] = (CwwwTargetFake::$variables[$id]['VariableUpdated'] ?? 1) + 1;
    CwwwTargetFake::$writes[] = [$id, $value];
    if (CwwwTargetFake::$writeHook !== null) {
        (CwwwTargetFake::$writeHook)($id);
    }
    return true;
}
function RequestAction(int $id, mixed $value): bool
{
    if (CwwwTargetFake::$actionHook !== null) {
        return (CwwwTargetFake::$actionHook)($id, $value);
    }
    CwwwTargetFake::$actions[] = [$id, json_decode($value, true, 8, JSON_THROW_ON_ERROR)];
    return !CwwwTargetFake::$rejectAction;
}
function IPS_SemaphoreEnter(string $name, int $timeout): bool
{
    if (str_starts_with($name, 'SAEF_CONTROL_LIGHT_') || str_starts_with($name, 'SAEF_STATISTIC_')) {
        if (CwwwTargetFake::$otherLocks[$name] ?? false) {
            return false;
        }
        CwwwTargetFake::$otherLocks[$name] = true;
        return true;
    }
    if ($name !== 'SAEF_CWWW_TARGET_11000' || $timeout !== 1000) {
        throw new LogicException('Wrong lock contract.');
    }
    if (CwwwTargetFake::$locked) {
        return false;
    }
    CwwwTargetFake::$locked = true;
    return true;
}
function IPS_SemaphoreLeave(string $name): bool
{
    if (array_key_exists($name, CwwwTargetFake::$otherLocks)) {
        CwwwTargetFake::$otherLocks[$name] = false;
        return true;
    }
    CwwwTargetFake::$locked = false;
    return true;
}
function IPS_Sleep(int $milliseconds): void
{
    if (CwwwTargetFake::$sleepHook !== null) {
        (CwwwTargetFake::$sleepHook)();
    }
}
function IPS_LogMessage(string $sender, string $message): void
{
    CwwwTargetFake::$logs[] = [$sender, $message];
}
require_once __DIR__ . '/../../case-studies/control-light/candidate/CwwwLightTargetRuntime.php';
$ids = ['raw' => 11001, 'command' => 11002, 'online' => 11003, 'ready' => 11004,
    'state' => 11005, 'brightness' => 11006, 'colorTemperature' => 11007];
$types = ['raw' => 3, 'command' => 3, 'online' => 0, 'ready' => 0, 'state' => 0,
    'brightness' => 1, 'colorTemperature' => 1];
$idents = ['ready' => 'available', 'colorTemperature' => 'color_temp_kelvin'];
CwwwTargetFake::$objects[11000] = ['ObjectType' => 3];
foreach ($ids as $key => $id) {
    CwwwTargetFake::$objects[$id] = ['ObjectType' => 2, 'ParentID' => 11000,
        'ObjectIdent' => $idents[$key] ?? $key];
    CwwwTargetFake::$variables[$id] = ['VariableType' => $types[$key],
        'VariableCustomAction' => in_array($key, ['state', 'brightness', 'colorTemperature'], true) ? 11000 : 0,
        'VariableAction' => $key === 'command' ? 12000 : 0];
}
$packet = '{"state":"OFF","brightness":128,"color_mode":"cwww","color":{"c":0,"w":255}}';
CwwwTargetFake::$values = [11001 => $packet, 11002 => '', 11003 => true,
    11004 => false, 11005 => false, 11006 => 0, 11007 => 0];
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$reject = static function (callable $fn) use ($assert): void {
    try {
        $fn();
    } catch (InvalidArgumentException | RuntimeException) {
        $assert(true, 'Expected rejection');
        return;
    }
    throw new LogicException('Operation should have failed.');
};
$runtime = new CwwwLightTargetRuntime(11000, $ids, new CwwwLightAdapter(2650, 4800));
$reject(fn() => $runtime->assertReady());
$reject(fn() => $runtime->dispatch(11006, 26));
$assert(CwwwTargetFake::$actions === [], 'No startup command');
$runtime->receive(11001);
$runtime->assertReady();
$assert(GetValue(11006) === 128 && GetValue(11007) === 2650 && GetValue(11005) === false, 'Coherent native feedback while OFF');
$assert(CwwwTargetFake::$writes === [[11004, false], [11006, 128], [11007, 2650], [11005, false], [11004, true]], 'Publish ready last');
$before = CwwwTargetFake::$values;
$runtime->receive(11001);
$assert(CwwwTargetFake::$values === $before, 'Repeated receive idempotent values');
CwwwTargetFake::$writes = [];
$runtime->dispatch(11006, 26);
$assert(CwwwTargetFake::$actions === [[11002, ['state' => 'ON', 'brightness' => 26, 'transition' => 0]]], 'One combined native request');
$assert(CwwwTargetFake::$values === $before && CwwwTargetFake::$writes === [], 'Dispatch does not fabricate feedback');
$runtime->dispatch(11005, true);
$runtime->dispatch(11007, 4800);
$assert(CwwwTargetFake::$actions[1][1] === ['state' => 'ON'], 'ON retains mix');
$assert(!isset(CwwwTargetFake::$actions[2][1]['state']), 'Kelvin preserves power');
foreach ([11001, 11004, 12000] as $badID) {
    $reject(fn() => $runtime->dispatch($badID, true));
}
$reject(fn() => $runtime->receive(11002));
CwwwTargetFake::$locked = true;
$reject(fn() => $runtime->receive(11001));
$assert(CwwwTargetFake::$writes === [], 'Lock contention causes no writes');
CwwwTargetFake::$locked = false;
foreach (['{broken', '{"state":"ON"}', '{"state":"ON","brightness":128,"color_mode":"cwww","color":{"c":5}}'] as $bad) {
    CwwwTargetFake::$values[11001] = $bad;
    $reject(fn() => $runtime->receive(11001));
    $assert(!GetValue(11004) && GetValue(11006) === 128 && GetValue(11005) === false, 'Invalid packet cannot partially overwrite targets');
    $assert(!CwwwTargetFake::$locked, 'Exception releases lock');
}
CwwwTargetFake::$values[11001] = $packet;
$runtime->receive(11001);
CwwwTargetFake::$values[11003] = false;
$reject(fn() => $runtime->assertReady());
$reject(fn() => $runtime->dispatch(11005, true));
$runtime->receive(11003);
CwwwTargetFake::$values[11003] = true;
$runtime->receive(11003);
$reject(fn() => $runtime->assertReady());
$assert(!GetValue(11004), 'Reconnect alone cannot admit retained state');
$runtime->receive(11001);
CwwwTargetFake::$failWrite = 11007;
$reject(fn() => $runtime->receive(11001));
$assert(!GetValue(11004) && !CwwwTargetFake::$locked, 'Failed publish remains unavailable');
CwwwTargetFake::$failWrite = null;
$runtime->receive(11001);
$runtime->assertReady();
CwwwTargetFake::$rejectAction = true;
$reject(fn() => $runtime->dispatch(11005, true));
$assert(GetValue(11005) === false && !CwwwTargetFake::$locked, 'Transport failure does not turn state on');
CwwwTargetFake::$rejectAction = false;
CwwwTargetFake::$writeHook = static function (int $id): void {
    if ($id === 11006) {
        CwwwTargetFake::$values[11001] = '{"state":"ON","brightness":26,"color_mode":"cwww","color":{"c":255,"w":0}}';
    }
};
$runtime->receive(11001);
$assert(!GetValue(11004), 'Newer packet during publish invalidates readiness');
CwwwTargetFake::$writeHook = null;
$runtime->receive(11001);
$assert(GetValue(11005) === true && GetValue(11006) === 26 && GetValue(11007) === 4800, 'Queued receive reads latest coherent packet');
$snapshot = CwwwTargetFake::$objects[11006];
CwwwTargetFake::$objects[11006]['ParentID'] = 12000;
$reject(fn() => $runtime->dispatch(11006, 100));
CwwwTargetFake::$objects[11006] = $snapshot;
foreach ([0, -1, 11002, 11000] as $bad) {
    $changed = $ids;
    $changed['raw'] = $bad;
    $reject(fn() => new CwwwLightTargetRuntime(11000, $changed, new CwwwLightAdapter(2650, 4800)));
}
$assert(!CwwwTargetFake::$locked, 'All operations leave lock released');
$reject(fn() => $runtime->handle(['SENDER' => 'Execute', 'VARIABLE' => 11005]));
$reject(fn() => $runtime->handle(['SENDER' => 'Action', 'VARIABLE' => null, 'VALUE' => true]));
$runtime->handle(['SENDER' => 'Variable', 'VARIABLE' => 11001, 'VALUE' => '{stale ignored}']);
$assert(GetValue(11006) === 26, 'Event VALUE never replays an old packet');
CwwwTargetFake::$objects[13000] = ['ObjectType' => 3];
$external = array_intersect_key($ids, array_flip(['raw', 'command', 'online']));
$resources = CwwwLightTargetRuntime::reconcile(13000, $external);
$assert(count($resources) === 7 && GetValue($resources['ready']) === false, 'Reconcile creates typed unready target');
$assert(CwwwTargetFake::$variables[$resources['state']]['VariableCustomAction'] === 13000, 'Target action owner set');
$event = IPS_GetObjectIDByIdent('EV_CWWW_RAW', 13000);
$assert(CwwwTargetFake::$events[$event]['TriggerType'] === 0, 'Raw receiver is update-triggered');
$assert(CwwwTargetFake::$events[$event]['EventActionID'] === SAEF_RUN_AUTOMATION_ACTION_GUID, 'Explicit event action binding');
$assert(CwwwTargetFake::$events[$event]['EventActive'] === false, 'No implicit activation');
CwwwTargetFake::$objects[$resources['state']]['ObjectName'] = 'User name';
CwwwTargetFake::$objects[$resources['state']]['ObjectPosition'] = 77;
CwwwTargetFake::$objects[$resources['state']]['ObjectIsHidden'] = true;
$objectsBefore = CwwwTargetFake::$objects;
$second = CwwwLightTargetRuntime::reconcile(13000, $external);
$assert($resources === $second && CwwwTargetFake::$objects === $objectsBefore, 'Reconcile twice preserves IDs and presentation');
CwwwTargetFake::$events[$event]['EventActive'] = true;
$writesBefore = count(CwwwTargetFake::$writes);
$reject(fn() => CwwwLightTargetRuntime::reconcile(13000, $external));
$assert(count(CwwwTargetFake::$writes) === $writesBefore && CwwwTargetFake::$events[$event]['EventActive'], 'Reconcile cannot deactivate a live receiver');
CwwwTargetFake::$events[$event]['EventActive'] = false;
CwwwTargetFake::$variables[$resources['brightness']]['VariableCustomAction'] = 14000;
$writesBefore = count(CwwwTargetFake::$writes);
$reject(fn() => CwwwLightTargetRuntime::reconcile(13000, $external));
$assert(count(CwwwTargetFake::$writes) === $writesBefore, 'Foreign ownership drift rejected before writes');
CwwwTargetFake::$objects[15000] = ['ObjectType' => 3];
CwwwTargetFake::$failCreate = true;
$reject(fn() => CwwwLightTargetRuntime::reconcile(15000, $external));
$assert(!isset(CwwwTargetFake::$objects[0]), 'Failed creation never mutates root');
echo "CWWW target runtime: $count assertions passed; offline only.\n";
