<?php
declare(strict_types=1);

/**
 * Minimal stateful IP-Symcon fake for exporter diagnostics initialization.
 */
final class DiagnosticsFakeSymconRuntime
{
    private static int $nextID = 1000;

    /** @var array<int, array<string, int|string>> */
    private static array $objects = [];

    /** @var array<int, array<string, int|string>> */
    private static array $variables = [];

    /** @var array<int, true> */
    private static array $scripts = [];

    /** @var array<int, array{ModuleInfo: array{ModuleID: string}, ConnectionID: int, Configuration: string}> */
    private static array $instances = [];

    /** @var array<int, array<string, mixed>> */
    private static array $events = [];

    /** @var array<int, true> */
    private static array $variableActions = [];

    /** @var array<int, int> */
    private static array $actionFeedbackTargets = [];

    /** @var array<string, true> */
    private static array $profiles = [];

    /** @var array<int, bool|int|float|string> */
    private static array $values = [];

    /** @var list<array{sender: string, message: string}> */
    private static array $logs = [];

    private static int $valueWriteCount = 0;

    /** @var list<array{variableID: int, value: mixed}> */
    private static array $requestActionCalls = [];

    private static ?int $requestActionFailureCall = null;

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [];
        self::$variables = [];
        self::$scripts = [];
        self::$instances = [];
        self::$events = [];
        self::$variableActions = [];
        self::$actionFeedbackTargets = [];
        self::$profiles = ['~UnixTimestamp' => true];
        self::$values = [];
        self::$logs = [];
        self::$valueWriteCount = 0;
        self::$requestActionCalls = [];
        self::$requestActionFailureCall = null;
    }

    public static function createScript(string $name = 'Exporter'): int
    {
        $scriptID = self::createObject(3, 0, '', $name);
        self::$scripts[$scriptID] = true;

        return $scriptID;
    }

    public static function createServerInstance(string $name = 'MQTT Server'): int
    {
        return self::createInstance('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}', $name);
    }

    public static function createClientInstance(string $name = 'MQTT Client'): int
    {
        return self::createInstance('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}', $name);
    }

    public static function createStateVariable(
        int $type,
        bool|int|float|string $value,
        bool $hasAction = false
    ): int {
        $variableID = self::createVariable($type);
        self::$values[$variableID] = $value;
        if ($hasAction) {
            self::$variableActions[$variableID] = true;
        }

        return $variableID;
    }

    public static function objectExists(int $objectID): bool
    {
        return isset(self::$objects[$objectID]);
    }

    public static function variableExists(int $variableID): bool
    {
        return isset(self::$variables[$variableID]);
    }

    public static function instanceExists(int $instanceID): bool
    {
        return isset(self::$instances[$instanceID]);
    }

    public static function hasAction(int $variableID): bool
    {
        return isset(self::$variableActions[$variableID]);
    }

    /** @return list<string> */
    public static function moduleList(): array
    {
        return [
            '{01C00ADD-D04E-452E-B66A-D253278743FE}',
            '{91D174F2-AE0F-B8D8-5EF4-6232B9083CCF}',
        ];
    }

    public static function scriptExists(int $scriptID): bool
    {
        return isset(self::$scripts[$scriptID]);
    }

    public static function profileExists(string $profile): bool
    {
        return isset(self::$profiles[$profile]);
    }

    public static function getObjectIDByIdent(string $ident, int $parentID): int|false
    {
        foreach (self::$objects as $objectID => $object) {
            if ($object['ParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
                return $objectID;
            }
        }

        trigger_error('Fake Ident was not found.', E_USER_WARNING);

        return false;
    }

    /** @return array<string, int|string> */
    public static function getObject(int $objectID): array
    {
        self::assertObject($objectID);

        return self::$objects[$objectID];
    }

    /** @return array<string, int|string> */
    public static function getVariable(int $variableID): array
    {
        self::assertVariable($variableID);

        return self::$variables[$variableID];
    }

    public static function createCategory(): int
    {
        return self::createObject(0, 0, '', '');
    }

    public static function createVariable(int $type): int
    {
        $variableID = self::createObject(2, 0, '', '');
        self::$variables[$variableID] = [
            'VariableType' => $type,
            'VariableCustomProfile' => '',
            'VariableCustomAction' => 0,
            'VariableChanged' => time(),
            'VariableUpdated' => time(),
        ];
        self::$values[$variableID] = match ($type) {
            0 => false,
            1 => 0,
            2 => 0.0,
            3 => '',
            default => throw new InvalidArgumentException('Unsupported fake variable type.'),
        };

        return $variableID;
    }

    public static function createInstance(string $moduleID, string $name = ''): int
    {
        $instanceID = self::createObject(1, 0, '', $name);
        self::$instances[$instanceID] = [
            'ModuleInfo' => ['ModuleID' => $moduleID],
            'ConnectionID' => 0,
            'Configuration' => '{}',
        ];

        return $instanceID;
    }

    /** @return array{ModuleInfo: array{ModuleID: string}, ConnectionID: int, Configuration: string} */
    public static function getInstance(int $instanceID): array
    {
        if (!isset(self::$instances[$instanceID])) {
            throw new RuntimeException('Fake instance does not exist: ' . $instanceID);
        }

        return self::$instances[$instanceID];
    }

    public static function connectInstance(int $instanceID, int $connectionID): void
    {
        self::getInstance($instanceID);
        self::getInstance($connectionID);
        self::$instances[$instanceID]['ConnectionID'] = $connectionID;
    }

    public static function disconnectInstance(int $instanceID): void
    {
        self::getInstance($instanceID);
        self::$instances[$instanceID]['ConnectionID'] = 0;
    }

    public static function getConfiguration(int $instanceID): string
    {
        return self::getInstance($instanceID)['Configuration'];
    }

    public static function setConfiguration(int $instanceID, string $configuration): void
    {
        self::getInstance($instanceID);
        json_decode($configuration, true, 512, JSON_THROW_ON_ERROR);
        self::$instances[$instanceID]['Configuration'] = $configuration;
    }

    public static function applyChanges(int $instanceID): void
    {
        $instance = self::getInstance($instanceID);
        if (
            !in_array(
                $instance['ModuleInfo']['ModuleID'],
                [
                    '{01C00ADD-D04E-452E-B66A-D253278743FE}',
                    '{91D174F2-AE0F-B8D8-5EF4-6232B9083CCF}',
                ],
                true
            )
        ) {
            return;
        }

        $valueVariableID = @self::getObjectIDByIdent('Value', $instanceID);
        if ($valueVariableID === false) {
            $configuration = json_decode($instance['Configuration'], true, 512, JSON_THROW_ON_ERROR);
            $type = $configuration['Type'] ?? null;
            if (!is_int($type)) {
                throw new RuntimeException('Fake MQTT Device Type is missing.');
            }
            $valueVariableID = self::createVariable($type);
            self::setParent($valueVariableID, $instanceID);
            self::setIdent($valueVariableID, 'Value');
            self::setName($valueVariableID, 'Value');
            self::$variableActions[$valueVariableID] = true;
        }
    }

    public static function requestAction(int $variableID, mixed $value): bool
    {
        self::assertVariable($variableID);
        if (!self::hasAction($variableID)) {
            throw new RuntimeException('Fake RequestAction variable has no action.');
        }

        self::$requestActionCalls[] = ['variableID' => $variableID, 'value' => $value];
        $callNumber = count(self::$requestActionCalls);
        if (self::$requestActionFailureCall === $callNumber) {
            return false;
        }

        self::setValue($variableID, $value);
        if (isset(self::$actionFeedbackTargets[$variableID])) {
            self::setValue(self::$actionFeedbackTargets[$variableID], $value);
        }

        return true;
    }

    public static function mapActionFeedback(int $actionVariableID, int $stateVariableID): void
    {
        self::assertVariable($actionVariableID);
        self::assertVariable($stateVariableID);
        self::$actionFeedbackTargets[$actionVariableID] = $stateVariableID;
    }

    public static function failRequestActionAt(?int $callNumber): void
    {
        self::$requestActionFailureCall = $callNumber;
    }

    /** @return list<array{variableID: int, value: mixed}> */
    public static function requestActionCalls(): array
    {
        return self::$requestActionCalls;
    }

    public static function clearRequestActionCalls(): void
    {
        self::$requestActionCalls = [];
        self::$requestActionFailureCall = null;
    }

    public static function createEvent(int $type): int
    {
        $eventID = self::createObject(4, 0, '', '');
        self::$events[$eventID] = [
            'EventType' => $type,
            'EventActive' => false,
            'ActionID' => '',
            'ActionParameters' => [],
        ];

        return $eventID;
    }

    /** @return array<string, mixed> */
    public static function getEvent(int $eventID): array
    {
        if (!isset(self::$events[$eventID])) {
            throw new RuntimeException('Fake event does not exist: ' . $eventID);
        }

        return self::$events[$eventID];
    }

    public static function setEventTrigger(int $eventID, int $type, int $variableID): void
    {
        self::getEvent($eventID);
        self::assertVariable($variableID);
        self::$events[$eventID]['TriggerType'] = $type;
        self::$events[$eventID]['TriggerVariableID'] = $variableID;
    }

    /** @param array<string, mixed> $parameters */
    public static function setEventAction(int $eventID, string $actionID, array $parameters): void
    {
        self::getEvent($eventID);
        self::$events[$eventID]['ActionID'] = $actionID;
        self::$events[$eventID]['ActionParameters'] = $parameters;
    }

    public static function setEventActive(int $eventID, bool $active): void
    {
        self::getEvent($eventID);
        self::$events[$eventID]['EventActive'] = $active;
    }

    public static function setParent(int $objectID, int $parentID): void
    {
        self::assertObject($objectID);
        self::assertObject($parentID);
        self::$objects[$objectID]['ParentID'] = $parentID;
    }

    public static function setIdent(int $objectID, string $ident): void
    {
        self::assertObject($objectID);
        self::$objects[$objectID]['ObjectIdent'] = $ident;
    }

    public static function setName(int $objectID, string $name): void
    {
        self::assertObject($objectID);
        self::$objects[$objectID]['ObjectName'] = $name;
    }

    public static function setPosition(int $objectID, int $position): void
    {
        self::assertObject($objectID);
        self::$objects[$objectID]['ObjectPosition'] = $position;
    }

    public static function setIcon(int $objectID, string $icon): void
    {
        self::assertObject($objectID);
        self::$objects[$objectID]['ObjectIcon'] = $icon;
    }

    public static function setHidden(int $objectID, bool $hidden): void
    {
        self::assertObject($objectID);
        self::$objects[$objectID]['ObjectIsHidden'] = $hidden ? 1 : 0;
    }

    public static function setVariableProfile(int $variableID, string $profile): void
    {
        self::assertVariable($variableID);
        self::$variables[$variableID]['VariableCustomProfile'] = $profile;
    }

    public static function setVariableAction(int $variableID, int $scriptID): void
    {
        self::assertVariable($variableID);
        self::$variables[$variableID]['VariableCustomAction'] = $scriptID;
    }

    public static function getValue(int $variableID): bool|int|float|string
    {
        self::assertVariable($variableID);

        return self::$values[$variableID];
    }

    public static function setValue(int $variableID, mixed $value): void
    {
        self::assertVariable($variableID);

        $type = self::$variables[$variableID]['VariableType'];
        $valid = match ($type) {
            0 => is_bool($value),
            1 => is_int($value),
            2 => is_float($value),
            3 => is_string($value),
            default => false,
        };

        if (!$valid) {
            throw new RuntimeException('Fake SetValue type mismatch.');
        }

        self::$values[$variableID] = $value;
        $timestamp = time();
        self::$variables[$variableID]['VariableUpdated'] = $timestamp;
        self::$variables[$variableID]['VariableChanged'] = $timestamp;
        self::$valueWriteCount++;
    }

    public static function log(string $sender, string $message): void
    {
        self::$logs[] = ['sender' => $sender, 'message' => $message];
    }

    public static function variableCount(): int
    {
        return count(self::$variables);
    }

    public static function categoryCount(): int
    {
        return count(array_filter(
            self::$objects,
            static fn (array $object): bool => $object['ObjectType'] === 0
        ));
    }

    public static function instanceCount(): int
    {
        return count(self::$instances);
    }

    /** @return array<int, array{ModuleInfo: array{ModuleID: string}, ConnectionID: int, Configuration: string}> */
    public static function instances(): array
    {
        return self::$instances;
    }

    public static function eventCount(): int
    {
        return count(self::$events);
    }

    /** @return list<int> */
    public static function getChildrenIDs(int $parentID): array
    {
        self::assertObject($parentID);
        $children = [];
        foreach (self::$objects as $objectID => $object) {
            if ($object['ParentID'] === $parentID) {
                $children[] = $objectID;
            }
        }
        sort($children);

        return $children;
    }

    public static function deleteEvent(int $eventID): bool
    {
        if (!isset(self::$events[$eventID]) || self::getChildrenIDs($eventID) !== []) {
            return false;
        }
        unset(self::$events[$eventID], self::$objects[$eventID]);

        return true;
    }

    public static function deleteCategory(int $categoryID): bool
    {
        if (
            !isset(self::$objects[$categoryID])
            || self::$objects[$categoryID]['ObjectType'] !== 0
            || self::getChildrenIDs($categoryID) !== []
        ) {
            return false;
        }
        unset(self::$objects[$categoryID]);

        return true;
    }

    public static function deleteVariable(int $variableID): bool
    {
        if (!isset(self::$variables[$variableID]) || self::getChildrenIDs($variableID) !== []) {
            return false;
        }
        unset(
            self::$variables[$variableID],
            self::$values[$variableID],
            self::$variableActions[$variableID],
            self::$actionFeedbackTargets[$variableID],
            self::$objects[$variableID]
        );

        return true;
    }

    public static function deleteInstance(int $instanceID): bool
    {
        if (!isset(self::$instances[$instanceID]) || self::getChildrenIDs($instanceID) !== []) {
            return false;
        }
        unset(self::$instances[$instanceID], self::$objects[$instanceID]);

        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public static function events(): array
    {
        return self::$events;
    }

    public static function valueWriteCount(): int
    {
        return self::$valueWriteCount;
    }

    /** @return list<array{sender: string, message: string}> */
    public static function logs(): array
    {
        return self::$logs;
    }

    private static function createObject(int $type, int $parentID, string $ident, string $name): int
    {
        $objectID = self::$nextID++;
        self::$objects[$objectID] = [
            'ObjectID' => $objectID,
            'ObjectType' => $type,
            'ParentID' => $parentID,
            'ObjectIdent' => $ident,
            'ObjectName' => $name,
            'ObjectPosition' => 0,
            'ObjectIcon' => '',
        ];

        return $objectID;
    }

    private static function assertObject(int $objectID): void
    {
        if (!isset(self::$objects[$objectID])) {
            throw new RuntimeException('Fake object does not exist: ' . $objectID);
        }
    }

    private static function assertVariable(int $variableID): void
    {
        if (!isset(self::$variables[$variableID])) {
            throw new RuntimeException('Fake variable does not exist: ' . $variableID);
        }
    }
}

function IPS_ObjectExists(int $objectID): bool
{
    return DiagnosticsFakeSymconRuntime::objectExists($objectID);
}

function IPS_VariableExists(int $variableID): bool
{
    return DiagnosticsFakeSymconRuntime::variableExists($variableID);
}

function IPS_ScriptExists(int $scriptID): bool
{
    return DiagnosticsFakeSymconRuntime::scriptExists($scriptID);
}

function IPS_InstanceExists(int $instanceID): bool
{
    return DiagnosticsFakeSymconRuntime::instanceExists($instanceID);
}

function HasAction(int $variableID): bool
{
    return DiagnosticsFakeSymconRuntime::hasAction($variableID);
}

/** @return list<string> */
function IPS_GetModuleList(): array
{
    return DiagnosticsFakeSymconRuntime::moduleList();
}

function IPS_VariableProfileExists(string $profile): bool
{
    return DiagnosticsFakeSymconRuntime::profileExists($profile);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    return DiagnosticsFakeSymconRuntime::getObjectIDByIdent($ident, $parentID);
}

/** @return array<string, int|string> */
function IPS_GetObject(int $objectID): array
{
    return DiagnosticsFakeSymconRuntime::getObject($objectID);
}

/** @return list<int> */
function IPS_GetChildrenIDs(int $parentID): array
{
    return DiagnosticsFakeSymconRuntime::getChildrenIDs($parentID);
}

/** @return array<string, int|string> */
function IPS_GetVariable(int $variableID): array
{
    return DiagnosticsFakeSymconRuntime::getVariable($variableID);
}

function IPS_CreateCategory(): int
{
    return DiagnosticsFakeSymconRuntime::createCategory();
}

function IPS_DeleteCategory(int $categoryID): bool
{
    return DiagnosticsFakeSymconRuntime::deleteCategory($categoryID);
}

function IPS_CreateVariable(int $type): int
{
    return DiagnosticsFakeSymconRuntime::createVariable($type);
}

function IPS_CreateInstance(string $moduleID): int
{
    return DiagnosticsFakeSymconRuntime::createInstance($moduleID);
}

function IPS_DeleteInstance(int $instanceID): bool
{
    return DiagnosticsFakeSymconRuntime::deleteInstance($instanceID);
}

/** @return array<string, mixed> */
function IPS_GetInstance(int $instanceID): array
{
    return DiagnosticsFakeSymconRuntime::getInstance($instanceID);
}

function IPS_ConnectInstance(int $instanceID, int $connectionID): void
{
    DiagnosticsFakeSymconRuntime::connectInstance($instanceID, $connectionID);
}

function IPS_DisconnectInstance(int $instanceID): void
{
    DiagnosticsFakeSymconRuntime::disconnectInstance($instanceID);
}

function IPS_GetConfiguration(int $instanceID): string
{
    return DiagnosticsFakeSymconRuntime::getConfiguration($instanceID);
}

function IPS_SetConfiguration(int $instanceID, string $configuration): void
{
    DiagnosticsFakeSymconRuntime::setConfiguration($instanceID, $configuration);
}

function IPS_ApplyChanges(int $instanceID): void
{
    DiagnosticsFakeSymconRuntime::applyChanges($instanceID);
}

function IPS_CreateEvent(int $type): int
{
    return DiagnosticsFakeSymconRuntime::createEvent($type);
}

function IPS_DeleteEvent(int $eventID): bool
{
    return DiagnosticsFakeSymconRuntime::deleteEvent($eventID);
}

/** @return array<string, mixed> */
function IPS_GetEvent(int $eventID): array
{
    return DiagnosticsFakeSymconRuntime::getEvent($eventID);
}

function IPS_SetEventTrigger(int $eventID, int $type, int $variableID): void
{
    DiagnosticsFakeSymconRuntime::setEventTrigger($eventID, $type, $variableID);
}

/** @param array<string, mixed> $parameters */
function IPS_SetEventAction(int $eventID, string $actionID, array $parameters): void
{
    DiagnosticsFakeSymconRuntime::setEventAction($eventID, $actionID, $parameters);
}

function IPS_SetEventActive(int $eventID, bool $active): void
{
    DiagnosticsFakeSymconRuntime::setEventActive($eventID, $active);
}

function IPS_SetParent(int $objectID, int $parentID): void
{
    DiagnosticsFakeSymconRuntime::setParent($objectID, $parentID);
}

function IPS_SetIdent(int $objectID, string $ident): void
{
    DiagnosticsFakeSymconRuntime::setIdent($objectID, $ident);
}

function IPS_SetName(int $objectID, string $name): void
{
    DiagnosticsFakeSymconRuntime::setName($objectID, $name);
}

function IPS_SetPosition(int $objectID, int $position): void
{
    DiagnosticsFakeSymconRuntime::setPosition($objectID, $position);
}

function IPS_SetIcon(int $objectID, string $icon): void
{
    DiagnosticsFakeSymconRuntime::setIcon($objectID, $icon);
}

function IPS_SetHidden(int $objectID, bool $hidden): void
{
    DiagnosticsFakeSymconRuntime::setHidden($objectID, $hidden);
}

function IPS_SetVariableCustomProfile(int $variableID, string $profile): void
{
    DiagnosticsFakeSymconRuntime::setVariableProfile($variableID, $profile);
}

function IPS_SetVariableCustomAction(int $variableID, int $scriptID): void
{
    DiagnosticsFakeSymconRuntime::setVariableAction($variableID, $scriptID);
}

function IPS_DeleteVariable(int $variableID): bool
{
    return DiagnosticsFakeSymconRuntime::deleteVariable($variableID);
}

function GetValue(int $variableID): mixed
{
    return DiagnosticsFakeSymconRuntime::getValue($variableID);
}

function SetValue(int $variableID, mixed $value): void
{
    DiagnosticsFakeSymconRuntime::setValue($variableID, $value);
}

function IPS_LogMessage(string $sender, string $message): void
{
    DiagnosticsFakeSymconRuntime::log($sender, $message);
}

function IPS_Sleep(int $milliseconds): void
{
    if ($milliseconds < 0) {
        throw new InvalidArgumentException('Fake sleep must not be negative.');
    }
}

function IPS_SemaphoreEnter(string $name, int $milliseconds): bool
{
    return $name !== '' && $milliseconds > 0;
}

function IPS_SemaphoreLeave(string $name): bool
{
    return $name !== '';
}

function RequestAction(int $variableID, mixed $value): bool
{
    return DiagnosticsFakeSymconRuntime::requestAction($variableID, $value);
}
