<?php

declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\HueWallSwitchCore;
use SAEF\CaseStudy\ControlLight\HueWallSwitchRuntime;

final class HueWallTopologyFake
{
    public static int $nextID = 1000;
    /** @var array<int, array<string, mixed>> */
    public static array $objects = [];
    /** @var array<int, array<string, int|string>> */
    public static array $variables = [];
    /** @var array<int, array<string, mixed>> */
    public static array $events = [];

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [];
        self::$variables = [];
        self::$events = [];

        self::object(100, 3, 0, '', 'Hue Wall owner', 0, false);
        self::variable(201, 0, '', 'North action', 3, 0);
        self::variable(202, 0, '', 'South action', 3, 0);
        self::variable(301, 0, '', 'Globe facade', 0, 100);
        self::variable(302, 0, '', 'Ceiling facade', 0, 100);

        self::event(401, 100, 'LEGACY_NORTH_ACTION', 'User north event', 41, false, true, 1, 202);
        self::event(402, 100, 'LEGACY_GLOBE_FEEDBACK', 'User globe feedback', 42, false, true, 0, 302);
        self::event(403, 100, 'HWS_EV_ACTION_RETIRED', 'Retired owned event', 43, true, true, 0, 201);
        self::event(404, 100, '', 'Unidentified legacy event', 44, false, true, 1, 201);
        self::event(405, 100, 'FOREIGN_EVENT', 'Foreign event', 45, false, true, 1, 201);
    }

    public static function object(
        int $id,
        int $type,
        int $parentID,
        string $ident,
        string $name,
        int $position,
        bool $hidden
    ): void {
        self::$objects[$id] = [
            'ObjectType' => $type,
            'ObjectParentID' => $parentID,
            'ObjectIdent' => $ident,
            'ObjectName' => $name,
            'ObjectPosition' => $position,
            'ObjectIsHidden' => $hidden,
        ];
    }

    public static function variable(
        int $id,
        int $parentID,
        string $ident,
        string $name,
        int $type,
        int $action
    ): void {
        self::object($id, 2, $parentID, $ident, $name, 0, false);
        self::$variables[$id] = [
            'VariableType' => $type,
            'VariableAction' => 0,
            'VariableCustomAction' => $action,
            'VariableCustomProfile' => '',
        ];
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
        self::object($id, 4, $parentID, $ident, $name, $position, $hidden);
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
    return isset(HueWallTopologyFake::$objects[$id]);
}

function IPS_VariableExists(int $id): bool
{
    return isset(HueWallTopologyFake::$variables[$id]);
}

function IPS_ScriptExists(int $id): bool
{
    return IPS_ObjectExists($id) && HueWallTopologyFake::$objects[$id]['ObjectType'] === 3;
}

function IPS_EventExists(int $id): bool
{
    return isset(HueWallTopologyFake::$events[$id]);
}

function IPS_VariableProfileExists(string $name): bool
{
    return false;
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    foreach (HueWallTopologyFake::$objects as $id => $object) {
        if ($object['ObjectParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
            return $id;
        }
    }

    return false;
}

/** @return array<string, mixed> */
function IPS_GetObject(int $id): array
{
    return HueWallTopologyFake::$objects[$id];
}

/** @return list<int> */
function IPS_GetChildrenIDs(int $parentID): array
{
    $children = [];
    foreach (HueWallTopologyFake::$objects as $id => $object) {
        if ($object['ObjectParentID'] === $parentID) {
            $children[] = $id;
        }
    }
    sort($children);

    return $children;
}

/** @return array<string, int|string> */
function IPS_GetVariable(int $id): array
{
    return HueWallTopologyFake::$variables[$id];
}

/** @return array<string, mixed> */
function IPS_GetEvent(int $id): array
{
    return HueWallTopologyFake::$events[$id];
}

function IPS_CreateVariable(int $type): int
{
    $id = HueWallTopologyFake::$nextID++;
    HueWallTopologyFake::variable($id, 0, '', '', $type, 0);

    return $id;
}

function IPS_CreateEvent(int $type): int
{
    $id = HueWallTopologyFake::$nextID++;
    HueWallTopologyFake::event($id, 0, '', '', 0, false, false, 0, 0);
    HueWallTopologyFake::$events[$id]['EventType'] = $type;

    return $id;
}

function IPS_SetParent(int $id, int $parentID): void
{
    HueWallTopologyFake::$objects[$id]['ObjectParentID'] = $parentID;
}

function IPS_SetIdent(int $id, string $ident): void
{
    HueWallTopologyFake::$objects[$id]['ObjectIdent'] = $ident;
}

function IPS_SetName(int $id, string $name): void
{
    HueWallTopologyFake::$objects[$id]['ObjectName'] = $name;
}

function IPS_SetPosition(int $id, int $position): void
{
    HueWallTopologyFake::$objects[$id]['ObjectPosition'] = $position;
}

function IPS_SetHidden(int $id, bool $hidden): void
{
    HueWallTopologyFake::$objects[$id]['ObjectIsHidden'] = $hidden;
}

function IPS_SetIcon(int $id, string $icon): void
{
}

function IPS_SetVariableCustomProfile(int $id, string $profile): void
{
    HueWallTopologyFake::$variables[$id]['VariableCustomProfile'] = $profile;
}

function IPS_SetVariableCustomAction(int $id, int $actionID): void
{
    HueWallTopologyFake::$variables[$id]['VariableCustomAction'] = $actionID;
}

function IPS_SetEventTrigger(int $id, int $triggerType, int $variableID): void
{
    HueWallTopologyFake::$events[$id]['TriggerType'] = $triggerType;
    HueWallTopologyFake::$events[$id]['TriggerVariableID'] = $variableID;
}

function IPS_SetEventAction(int $id, string $actionID, array $parameters): void
{
    HueWallTopologyFake::$events[$id]['EventActionID'] = $actionID;
}

function IPS_SetEventActive(int $id, bool $active): void
{
    HueWallTopologyFake::$events[$id]['EventActive'] = $active;
}

require_once __DIR__ . '/../../case-studies/control-light/candidate/HueWallSwitchRuntime.php';

function assertHueTopologySame(mixed $expected, mixed $actual, string $message): void
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

HueWallTopologyFake::reset();
$configuration = HueWallSwitchCore::normalizeConfiguration([
    'targets' => [
        'globe' => [
            'stateVariableID' => 301,
            'feedbackEventIdent' => 'LEGACY_GLOBE_FEEDBACK',
        ],
        'ceiling' => ['stateVariableID' => 302],
    ],
    'sources' => [
        [
            'key' => 'north',
            'sourceVariableID' => 201,
            'actionEventIdent' => 'LEGACY_NORTH_ACTION',
            'leftTargetKey' => 'globe',
            'rightTargetKey' => 'ceiling',
        ],
        [
            'key' => 'south',
            'sourceVariableID' => 202,
            'leftTargetKey' => 'globe',
            'rightTargetKey' => 'ceiling',
        ],
    ],
]);

$first = HueWallSwitchRuntime::reconcileResources(100, $configuration);
$objectCount = count(HueWallTopologyFake::$objects);
$eventCount = count(HueWallTopologyFake::$events);
$second = HueWallSwitchRuntime::reconcileResources(100, $configuration);

assertHueTopologySame($first, $second, 'Repeated reconciliation changed resource IDs.');
assertHueTopologySame($objectCount, count(HueWallTopologyFake::$objects), 'Repeated reconciliation created objects.');
assertHueTopologySame($eventCount, count(HueWallTopologyFake::$events), 'Repeated reconciliation created events.');
assertHueTopologySame('User north event', HueWallTopologyFake::$objects[401]['ObjectName'], 'User event name was overwritten.');
assertHueTopologySame(41, HueWallTopologyFake::$objects[401]['ObjectPosition'], 'User event position was overwritten.');
assertHueTopologySame(0, HueWallTopologyFake::$events[401]['TriggerType'], 'Action event is not OnUpdate.');
assertHueTopologySame(201, HueWallTopologyFake::$events[401]['TriggerVariableID'], 'Action trigger source differs.');
assertHueTopologySame(1, HueWallTopologyFake::$events[402]['TriggerType'], 'Feedback event is not OnChange.');
assertHueTopologySame(301, HueWallTopologyFake::$events[402]['TriggerVariableID'], 'Feedback trigger target differs.');
assertHueTopologySame(false, HueWallTopologyFake::$events[403]['EventActive'], 'Obsolete owned event remained active.');
assertHueTopologySame(true, HueWallTopologyFake::$events[404]['EventActive'], 'Unidentified legacy event was mutated.');
assertHueTopologySame(true, HueWallTopologyFake::$events[405]['EventActive'], 'Foreign event was mutated.');
assertHueTopologySame(2, count($first['actionEventIDs']), 'Action event count differs.');
assertHueTopologySame(2, count($first['feedbackEventIDs']), 'Feedback event count differs.');
assertHueTopologySame(2, count($first['debounceVariableIDs']), 'Debounce state count differs.');
assertHueTopologySame(
    ['globe', 'ceiling'],
    array_keys($first['debounceVariableIDs']['north']),
    'North target-specific debounce map differs.'
);
assertHueTopologySame(
    ['globe', 'ceiling'],
    array_keys($first['debounceVariableIDs']['south']),
    'South target-specific debounce map differs.'
);

fwrite(STDOUT, 'PASS: Hue Wall topology ownership, trigger and idempotency contract.' . PHP_EOL);
