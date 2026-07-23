<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCore;
use SAEF\CaseStudy\ControlLight\ControlLightRuntime;

final class ControlLightTopologyFake
{
    public static int $nextID = 1000;
    /** @var array<int, array<string, mixed>> */
    public static array $objects = [];
    /** @var array<int, array<string, mixed>> */
    public static array $variables = [];
    /** @var array<int, array<string, mixed>> */
    public static array $events = [];
    /** @var array<int, array{TargetID: int}> */
    public static array $links = [];
    /** @var array<string, true> */
    public static array $profiles = [];
    /** @var array<int, mixed> */
    public static array $values = [];
    public static int $valueWrites = 0;

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [];
        self::$variables = [];
        self::$events = [];
        self::$links = [];
        self::$values = [];
        self::$valueWrites = 0;
        self::$profiles = [
            '~Switch' => true,
            '~Intensity.100' => true,
            '~TWColor' => true,
            '~HexColor' => true,
            '~UnixTimestamp' => true,
        ];

        self::object(100, 0, 0, '', 'User Container', 55, 'UserIcon', false);
        self::object(101, 3, 100, '', 'User Wrapper', 60, 'Script', false);
        self::object(200, 0, 0, '', 'Target', 0, '', false);
        self::variable(201, 200, 'state', 'Target State', 0, '~Switch', 500);
        self::variable(202, 200, 'brightness', 'Target Brightness', 1, '', 500);
        self::variable(203, 200, 'color_temp_kelvin', 'Target Temperature', 1, '', 500);
        self::variable(204, 200, 'device_status', 'Target Availability', 0, '', 0);

        self::object(150, 6, 100, 'LINK_TARGET_PARENT', 'User Target Link', 91, 'LinkIcon', true);
        self::$links[150] = ['TargetID' => 200];

        self::variable(160, 100, 'STATE', 'User State Name', 0, '~Switch', 101, 77, 'UserStateIcon');
        self::variable(161, 100, 'COLOR', 'User Old Color', 1, '~HexColor', 101, 78, 'UserColorIcon');
        self::variable(170, 100, 'FOREIGN', 'Foreign Sibling', 3, '', 0, 79, 'ForeignIcon');

        self::event(180, 101, 'EV_TARGET_STATE', 'User State Event', 88, false, true, 1, 202);
        self::event(181, 101, 'EV_TARGET_COLOR', 'User Color Event', 89, false, true, 1, 202);
    }

    public static function object(
        int $id,
        int $type,
        int $parentID,
        string $ident,
        string $name,
        int $position,
        string $icon,
        bool $hidden
    ): void {
        self::$objects[$id] = [
            'ObjectType' => $type,
            'ObjectParentID' => $parentID,
            'ObjectIdent' => $ident,
            'ObjectName' => $name,
            'ObjectPosition' => $position,
            'ObjectIcon' => $icon,
            'ObjectIsHidden' => $hidden,
        ];
    }

    public static function variable(
        int $id,
        int $parentID,
        string $ident,
        string $name,
        int $type,
        string $profile,
        int $action,
        int $position = 0,
        string $icon = ''
    ): void {
        self::object($id, 2, $parentID, $ident, $name, $position, $icon, false);
        self::$variables[$id] = [
            'VariableType' => $type,
            'VariableCustomProfile' => $profile,
            'VariableAction' => 0,
            'VariableCustomAction' => $action,
        ];
        self::$values[$id] = match ($type) {
            0 => false,
            1 => 0,
            2 => 0.0,
            3 => '',
        };
    }

    public static function event(
        int $id,
        int $parentID,
        string $ident,
        string $name,
        int $position,
        bool $hidden,
        bool $active,
        int $triggerType,
        int $triggerVariableID
    ): void {
        self::object($id, 4, $parentID, $ident, $name, $position, '', $hidden);
        self::$events[$id] = [
            'EventType' => 0,
            'EventActive' => $active,
            'TriggerType' => $triggerType,
            'TriggerVariableID' => $triggerVariableID,
            'EventActionID' => '',
        ];
    }
}

function IPS_ObjectExists(int $id): bool
{
    return isset(ControlLightTopologyFake::$objects[$id]);
}

function IPS_VariableExists(int $id): bool
{
    return isset(ControlLightTopologyFake::$variables[$id]);
}

function IPS_ScriptExists(int $id): bool
{
    return IPS_ObjectExists($id) && ControlLightTopologyFake::$objects[$id]['ObjectType'] === 3;
}

function IPS_EventExists(int $id): bool
{
    return isset(ControlLightTopologyFake::$events[$id]);
}

function IPS_VariableProfileExists(string $name): bool
{
    return isset(ControlLightTopologyFake::$profiles[$name]);
}

function IPS_GetParent(int $id): int
{
    return ControlLightTopologyFake::$objects[$id]['ObjectParentID'];
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    foreach (ControlLightTopologyFake::$objects as $id => $object) {
        if ($object['ObjectParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
            return $id;
        }
    }
    return false;
}

/** @return array<string, mixed> */
function IPS_GetObject(int $id): array
{
    return ControlLightTopologyFake::$objects[$id];
}

/** @return list<int> */
function IPS_GetChildrenIDs(int $id): array
{
    $children = [];
    foreach (ControlLightTopologyFake::$objects as $objectID => $object) {
        if ($object['ObjectParentID'] === $id) {
            $children[] = $objectID;
        }
    }
    sort($children);
    return $children;
}

/** @return array<string, mixed> */
function IPS_GetVariable(int $id): array
{
    return ControlLightTopologyFake::$variables[$id];
}

/** @return array<string, mixed> */
function IPS_GetEvent(int $id): array
{
    return ControlLightTopologyFake::$events[$id];
}

/** @return array{TargetID: int} */
function IPS_GetLink(int $id): array
{
    return ControlLightTopologyFake::$links[$id];
}

function IPS_CreateVariable(int $type): int
{
    $id = ControlLightTopologyFake::$nextID++;
    ControlLightTopologyFake::variable($id, 0, '', '', $type, '', 0);
    return $id;
}

function IPS_CreateEvent(int $type): int
{
    $id = ControlLightTopologyFake::$nextID++;
    ControlLightTopologyFake::event($id, 0, '', '', 0, false, false, 0, 0);
    ControlLightTopologyFake::$events[$id]['EventType'] = $type;
    return $id;
}

function IPS_CreateLink(): int
{
    $id = ControlLightTopologyFake::$nextID++;
    ControlLightTopologyFake::object($id, 6, 0, '', '', 0, '', false);
    ControlLightTopologyFake::$links[$id] = ['TargetID' => 0];
    return $id;
}

function IPS_SetParent(int $id, int $parentID): void
{
    ControlLightTopologyFake::$objects[$id]['ObjectParentID'] = $parentID;
}

function IPS_SetIdent(int $id, string $ident): void
{
    ControlLightTopologyFake::$objects[$id]['ObjectIdent'] = $ident;
}

function IPS_SetName(int $id, string $name): void
{
    ControlLightTopologyFake::$objects[$id]['ObjectName'] = $name;
}

function IPS_SetPosition(int $id, int $position): void
{
    ControlLightTopologyFake::$objects[$id]['ObjectPosition'] = $position;
}

function IPS_SetIcon(int $id, string $icon): void
{
    ControlLightTopologyFake::$objects[$id]['ObjectIcon'] = $icon;
}

function IPS_SetHidden(int $id, bool $hidden): void
{
    ControlLightTopologyFake::$objects[$id]['ObjectIsHidden'] = $hidden;
}

function IPS_SetVariableCustomProfile(int $id, string $profile): void
{
    ControlLightTopologyFake::$variables[$id]['VariableCustomProfile'] = $profile;
}

function IPS_SetVariableCustomAction(int $id, int $scriptID): void
{
    ControlLightTopologyFake::$variables[$id]['VariableCustomAction'] = $scriptID;
}

function IPS_SetEventTrigger(int $id, int $triggerType, int $variableID): void
{
    ControlLightTopologyFake::$events[$id]['TriggerType'] = $triggerType;
    ControlLightTopologyFake::$events[$id]['TriggerVariableID'] = $variableID;
}

/** @param array<string, mixed> $parameters */
function IPS_SetEventAction(int $id, string $actionID, array $parameters): void
{
    ControlLightTopologyFake::$events[$id]['EventActionID'] = $actionID;
}

function IPS_SetEventActive(int $id, bool $active): void
{
    ControlLightTopologyFake::$events[$id]['EventActive'] = $active;
}

function IPS_SetLinkTargetID(int $id, int $targetID): void
{
    ControlLightTopologyFake::$links[$id]['TargetID'] = $targetID;
}

function GetValue(int $id): mixed
{
    return ControlLightTopologyFake::$values[$id];
}

function SetValue(int $id, mixed $value): void
{
    ControlLightTopologyFake::$values[$id] = $value;
    ControlLightTopologyFake::$valueWrites++;
}

require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightRuntime.php';

function assertTopologySame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s.',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

ControlLightTopologyFake::reset();
$configuration = ControlLightCore::normalizeConfiguration([
    'preset' => 'Z2M',
    'identColor' => '',
    'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
]);
$first = ControlLightRuntime::reconcileResources(101, $configuration);
$objectCount = count(ControlLightTopologyFake::$objects);
$eventCount = count(ControlLightTopologyFake::$events);
$second = ControlLightRuntime::reconcileResources(101, $configuration);

assertTopologySame($first, $second, 'Repeated reconciliation changed resource IDs.');
assertTopologySame($objectCount, count(ControlLightTopologyFake::$objects), 'Repeated reconciliation created objects.');
assertTopologySame($eventCount, count(ControlLightTopologyFake::$events), 'Repeated reconciliation created events.');
assertTopologySame('User Container', ControlLightTopologyFake::$objects[100]['ObjectName'], 'Parent name was overwritten.');
assertTopologySame('User State Name', ControlLightTopologyFake::$objects[160]['ObjectName'], 'Variable name was overwritten.');
assertTopologySame(77, ControlLightTopologyFake::$objects[160]['ObjectPosition'], 'Variable position was overwritten.');
assertTopologySame('UserStateIcon', ControlLightTopologyFake::$objects[160]['ObjectIcon'], 'Variable icon was overwritten.');
assertTopologySame('Foreign Sibling', ControlLightTopologyFake::$objects[170]['ObjectName'], 'Foreign sibling name changed.');
assertTopologySame(false, ControlLightTopologyFake::$objects[170]['ObjectIsHidden'], 'Foreign sibling visibility changed.');
assertTopologySame('User State Event', ControlLightTopologyFake::$objects[180]['ObjectName'], 'Event name was overwritten.');
assertTopologySame(88, ControlLightTopologyFake::$objects[180]['ObjectPosition'], 'Event position was overwritten.');
assertTopologySame(0, ControlLightTopologyFake::$events[180]['TriggerType'], 'State event is not OnUpdate.');
assertTopologySame(201, ControlLightTopologyFake::$events[180]['TriggerVariableID'], 'State event target differs.');
assertTopologySame(true, ControlLightTopologyFake::$events[180]['EventActive'], 'State event is inactive.');
assertTopologySame(false, ControlLightTopologyFake::$events[181]['EventActive'], 'Disabled color event remained active.');
assertTopologySame(true, ControlLightTopologyFake::$objects[161]['ObjectIsHidden'], 'Disabled color variable remained visible.');
assertTopologySame(0, ControlLightTopologyFake::$variables[161]['VariableCustomAction'], 'Disabled color action remained active.');
assertTopologySame(101, ControlLightTopologyFake::$variables[$first['localVariableIDs']['brightness']]['VariableCustomAction'], 'Brightness action differs.');
assertTopologySame(101, ControlLightTopologyFake::$variables[$first['localVariableIDs']['colorTemperature']]['VariableCustomAction'], 'Temperature action differs.');
assertTopologySame(204, $first['availabilityVariableID'], 'Availability variable resolution differs.');

$firstDiagnostics = ControlLightRuntime::initializeDiagnostics(101, $configuration);
$writesAfterFirstDiagnostics = ControlLightTopologyFake::$valueWrites;
$secondDiagnostics = ControlLightRuntime::initializeDiagnostics(101, $configuration);
assertTopologySame(
    $firstDiagnostics,
    $secondDiagnostics,
    'Repeated diagnostics initialization changed the diagnostics contract.'
);
assertTopologySame(
    $writesAfterFirstDiagnostics,
    ControlLightTopologyFake::$valueWrites,
    'Unchanged diagnostics initialization rewrote the Registry.'
);

fwrite(STDOUT, 'PASS: ControlLight topology ownership, presentation and idempotency contract.' . PHP_EOL);
