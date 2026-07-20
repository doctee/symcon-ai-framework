<?php
declare(strict_types=1);

/**
 * Deterministic tests for the SAEF event Ensure helper.
 *
 * The fake runtime records IP-Symcon calls without executing live automation.
 */

final class EnsureEventFakeRuntime
{
    public static int $nextID = 1000;

    /** @var array<int, array<string, mixed>> */
    public static array $objects = [];

    /** @var array<int, array<string, mixed>> */
    public static array $events = [];

    /** @var array<int, true> */
    public static array $scripts = [];

    /** @var array<int, true> */
    public static array $variables = [];

    /** @var list<array{eventID: int, script: string}> */
    public static array $eventScriptCalls = [];

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [];
        self::$events = [];
        self::$scripts = [100 => true, 101 => true];
        self::$variables = [200 => true, 201 => true];
        self::$eventScriptCalls = [];

        self::addObject(100, 3, 0, 'OWNER_SCRIPT');
        self::addObject(101, 3, 0, 'OTHER_SCRIPT');
        self::addObject(200, 2, 0, 'STATE_A');
        self::addObject(201, 2, 0, 'STATE_B');
    }

    public static function addObject(int $id, int $type, int $parentID, string $ident): void
    {
        self::$objects[$id] = [
            'ObjectType' => $type,
            'ObjectParentID' => $parentID,
            'ObjectIdent' => $ident,
            'ObjectName' => '',
            'ObjectPosition' => 0,
            'ObjectIsHidden' => false,
        ];
    }

    public static function addEvent(int $id, int $eventType, int $parentID, string $ident): void
    {
        self::addObject($id, 4, $parentID, $ident);
        self::$events[$id] = [
            'EventType' => $eventType,
            'EventActive' => false,
            'ActionID' => '',
            'ActionParameters' => [],
        ];
    }

    public static function eventCount(): int
    {
        return count(self::$events);
    }
}

function IPS_ObjectExists(int $id): bool
{
    return isset(EnsureEventFakeRuntime::$objects[$id]);
}

function IPS_ScriptExists(int $id): bool
{
    return isset(EnsureEventFakeRuntime::$scripts[$id]);
}

function IPS_VariableExists(int $id): bool
{
    return isset(EnsureEventFakeRuntime::$variables[$id]);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    foreach (EnsureEventFakeRuntime::$objects as $id => $object) {
        if ($object['ObjectParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
            return $id;
        }
    }

    return false;
}

/** @return array<string, mixed> */
function IPS_GetObject(int $id): array
{
    return EnsureEventFakeRuntime::$objects[$id];
}

/** @return array<string, mixed> */
function IPS_GetEvent(int $id): array
{
    return EnsureEventFakeRuntime::$events[$id];
}

function IPS_CreateEvent(int $type): int
{
    $id = EnsureEventFakeRuntime::$nextID++;
    EnsureEventFakeRuntime::addEvent($id, $type, 0, '');

    return $id;
}

function IPS_SetParent(int $id, int $parentID): void
{
    EnsureEventFakeRuntime::$objects[$id]['ObjectParentID'] = $parentID;
}

function IPS_SetIdent(int $id, string $ident): void
{
    EnsureEventFakeRuntime::$objects[$id]['ObjectIdent'] = $ident;
}

function IPS_SetName(int $id, string $name): void
{
    EnsureEventFakeRuntime::$objects[$id]['ObjectName'] = $name;
}

function IPS_SetPosition(int $id, int $position): void
{
    EnsureEventFakeRuntime::$objects[$id]['ObjectPosition'] = $position;
}

function IPS_SetHidden(int $id, bool $hidden): void
{
    EnsureEventFakeRuntime::$objects[$id]['ObjectIsHidden'] = $hidden;
}

function IPS_SetEventCyclic(
    int $id,
    int $dateType,
    int $dateInterval,
    int $dateDays,
    int $dateDayInterval,
    int $timeType,
    int $timeInterval
): void {
    EnsureEventFakeRuntime::$events[$id]['Cyclic'] = [
        $dateType,
        $dateInterval,
        $dateDays,
        $dateDayInterval,
        $timeType,
        $timeInterval,
    ];
}

function IPS_SetEventScript(int $id, string $script): void
{
    EnsureEventFakeRuntime::$eventScriptCalls[] = [
        'eventID' => $id,
        'script' => $script,
    ];
}

/** @param array<string, mixed> $parameters */
function IPS_SetEventAction(int $id, string $actionID, array $parameters): void
{
    EnsureEventFakeRuntime::$events[$id]['ActionID'] = $actionID;
    EnsureEventFakeRuntime::$events[$id]['ActionParameters'] = $parameters;
}

function IPS_SetEventActive(int $id, bool $active): void
{
    EnsureEventFakeRuntime::$events[$id]['EventActive'] = $active;
}

function IPS_SetEventTrigger(int $id, int $triggerType, int $variableID): void
{
    EnsureEventFakeRuntime::$events[$id]['TriggerType'] = $triggerType;
    EnsureEventFakeRuntime::$events[$id]['TriggerVariableID'] = $variableID;
}

require_once __DIR__ . '/../../helpers/object/EnsureEvent.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
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

/** @param class-string<Throwable> $expectedClass */
function assertThrows(string $expectedClass, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s: %s',
            $message,
            $expectedClass,
            $exception::class,
            $exception->getMessage()
        ));
    }

    throw new RuntimeException($message . ' Expected exception was not thrown.');
}

$tests = [];

$tests['creates triggered update event with explicit action binding'] = static function (): void {
    EnsureEventFakeRuntime::reset();

    $eventID = SAEF_EnsureTriggeredScriptEvent(
        100,
        'STATE_UPDATE',
        'State Update',
        100,
        200,
        0,
        false,
        25,
        true
    );

    assertSameValue(1000, $eventID, 'Unexpected event ID.');
    assertSameValue(1, EnsureEventFakeRuntime::eventCount(), 'Unexpected event count.');
    assertSameValue(0, EnsureEventFakeRuntime::$events[$eventID]['EventType'], 'Wrong event type.');
    assertSameValue(0, EnsureEventFakeRuntime::$events[$eventID]['TriggerType'], 'Wrong trigger type.');
    assertSameValue(200, EnsureEventFakeRuntime::$events[$eventID]['TriggerVariableID'], 'Wrong trigger variable.');
    assertSameValue(
        SAEF_RUN_AUTOMATION_ACTION_GUID,
        EnsureEventFakeRuntime::$events[$eventID]['ActionID'],
        'Wrong event action.'
    );
    assertSameValue([], EnsureEventFakeRuntime::$events[$eventID]['ActionParameters'], 'Unexpected action parameters.');
    assertSameValue(false, EnsureEventFakeRuntime::$events[$eventID]['EventActive'], 'Wrong active state.');
    assertSameValue(25, EnsureEventFakeRuntime::$objects[$eventID]['ObjectPosition'], 'Wrong position.');
    assertSameValue(true, EnsureEventFakeRuntime::$objects[$eventID]['ObjectIsHidden'], 'Wrong hidden state.');
    assertSameValue([], EnsureEventFakeRuntime::$eventScriptCalls, 'IPS_SetEventScript() must not be used.');
};

$tests['updates compatible event without creating a duplicate'] = static function (): void {
    EnsureEventFakeRuntime::reset();

    $firstID = SAEF_EnsureTriggeredScriptEvent(100, 'STATE_EVENT', 'Old Name', 100, 200, 0);
    $secondID = SAEF_EnsureTriggeredScriptEvent(100, 'STATE_EVENT', 'New Name', 100, 201, 1, true, 40, false);

    assertSameValue($firstID, $secondID, 'Existing event was not reused.');
    assertSameValue(1, EnsureEventFakeRuntime::eventCount(), 'Duplicate event was created.');
    assertSameValue('New Name', EnsureEventFakeRuntime::$objects[$secondID]['ObjectName'], 'Name was not updated.');
    assertSameValue(1, EnsureEventFakeRuntime::$events[$secondID]['TriggerType'], 'Trigger type was not updated.');
    assertSameValue(201, EnsureEventFakeRuntime::$events[$secondID]['TriggerVariableID'], 'Trigger variable was not updated.');
    assertSameValue(true, EnsureEventFakeRuntime::$events[$secondID]['EventActive'], 'Active state was not updated.');
};

$tests['preserves existing presentation while reconciling the event contract'] = static function (): void {
    EnsureEventFakeRuntime::reset();

    $eventID = SAEF_EnsureTriggeredScriptEvent(
        100,
        'STATE_EVENT',
        'Creation Default',
        100,
        200,
        0,
        false,
        10,
        true,
        false
    );

    EnsureEventFakeRuntime::$objects[$eventID]['ObjectName'] = 'User Name';
    EnsureEventFakeRuntime::$objects[$eventID]['ObjectPosition'] = 99;
    EnsureEventFakeRuntime::$objects[$eventID]['ObjectIsHidden'] = false;

    $reusedID = SAEF_EnsureTriggeredScriptEvent(
        100,
        'STATE_EVENT',
        'Managed Name',
        100,
        201,
        1,
        true,
        20,
        true,
        false
    );

    assertSameValue($eventID, $reusedID, 'Existing event was not reused.');
    assertSameValue('User Name', EnsureEventFakeRuntime::$objects[$eventID]['ObjectName'], 'Name was overwritten.');
    assertSameValue(99, EnsureEventFakeRuntime::$objects[$eventID]['ObjectPosition'], 'Position was overwritten.');
    assertSameValue(false, EnsureEventFakeRuntime::$objects[$eventID]['ObjectIsHidden'], 'Visibility was overwritten.');
    assertSameValue(1, EnsureEventFakeRuntime::$events[$eventID]['TriggerType'], 'Trigger type was not reconciled.');
    assertSameValue(201, EnsureEventFakeRuntime::$events[$eventID]['TriggerVariableID'], 'Trigger was not reconciled.');
    assertSameValue(
        SAEF_RUN_AUTOMATION_ACTION_GUID,
        EnsureEventFakeRuntime::$events[$eventID]['ActionID'],
        'Action was not reconciled.'
    );
    assertSameValue(true, EnsureEventFakeRuntime::$events[$eventID]['EventActive'], 'Active state was not reconciled.');
};

$tests['rejects parent that is not the target script'] = static function (): void {
    EnsureEventFakeRuntime::reset();

    assertThrows(
        InvalidArgumentException::class,
        static fn(): int => SAEF_EnsureTriggeredScriptEvent(100, 'STATE_EVENT', 'State Event', 101, 200, 0),
        'Mismatched parent and target script must fail.'
    );
};

$tests['rejects missing trigger variable and unsupported trigger type'] = static function (): void {
    EnsureEventFakeRuntime::reset();

    assertThrows(
        InvalidArgumentException::class,
        static fn(): int => SAEF_EnsureTriggeredScriptEvent(100, 'MISSING_VARIABLE', 'Missing', 100, 999, 0),
        'Missing trigger variable must fail.'
    );

    assertThrows(
        InvalidArgumentException::class,
        static fn(): int => SAEF_EnsureTriggeredScriptEvent(100, 'BAD_TRIGGER', 'Bad Trigger', 100, 200, 2),
        'Unsupported trigger type must fail.'
    );
};

$tests['rejects missing target scripts and invalid cyclic intervals'] = static function (): void {
    EnsureEventFakeRuntime::reset();

    assertThrows(
        InvalidArgumentException::class,
        static fn(): int => SAEF_EnsureTriggeredScriptEvent(100, 'MISSING_SCRIPT', 'Missing', 999, 200, 0),
        'Missing target script must fail.'
    );

    assertThrows(
        InvalidArgumentException::class,
        static fn(): int => SAEF_EnsureCyclicScriptEvent(100, 'BAD_INTERVAL', 'Bad Interval', 100, 0),
        'Non-positive cyclic interval must fail.'
    );
};

$tests['rejects incompatible existing object and event types'] = static function (): void {
    EnsureEventFakeRuntime::reset();
    EnsureEventFakeRuntime::addObject(300, 2, 100, 'OBJECT_COLLISION');

    assertThrows(
        RuntimeException::class,
        static fn(): int => SAEF_EnsureTriggeredScriptEvent(100, 'OBJECT_COLLISION', 'Collision', 100, 200, 0),
        'Non-event Ident collision must fail.'
    );

    EnsureEventFakeRuntime::addEvent(301, 1, 100, 'CYCLIC_COLLISION');

    assertThrows(
        RuntimeException::class,
        static fn(): int => SAEF_EnsureTriggeredScriptEvent(100, 'CYCLIC_COLLISION', 'Collision', 100, 200, 0),
        'Incompatible event type must fail.'
    );
};

$tests['cyclic event uses parent automation action without event script source'] = static function (): void {
    EnsureEventFakeRuntime::reset();

    $eventID = SAEF_EnsureCyclicScriptEvent(100, 'PERIODIC', 'Periodic', 100, 300, false);

    assertSameValue(1, EnsureEventFakeRuntime::$events[$eventID]['EventType'], 'Wrong cyclic event type.');
    assertSameValue(
        SAEF_RUN_AUTOMATION_ACTION_GUID,
        EnsureEventFakeRuntime::$events[$eventID]['ActionID'],
        'Wrong cyclic event action.'
    );
    assertSameValue([], EnsureEventFakeRuntime::$eventScriptCalls, 'Cyclic helper must not call IPS_SetEventScript().');
};

$failures = [];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $failures[] = $name . ': ' . $exception->getMessage();
        echo '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL;
    }
}

if ($failures !== []) {
    exit(1);
}

echo sprintf('All %d EnsureEvent tests passed.%s', count($tests), PHP_EOL);
