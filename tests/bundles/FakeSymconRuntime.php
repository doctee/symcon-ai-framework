<?php

declare(strict_types=1);

/**
 * Minimal stateful IP-Symcon fake for the EnsureVariable bundle contract.
 *
 * This test double is intentionally limited to the runtime calls made by the
 * selected canonical helper closure. It does not model a complete installation.
 */
final class FakeSymconRuntime
{
    private static int $nextID = 1000;

    /** @var array<int, array<string, int|string>> */
    private static array $objects = [];

    /** @var array<int, array<string, int|string>> */
    private static array $variables = [];

    /** @var array<int, bool> */
    private static array $scripts = [];

    /** @var array<string, bool> */
    private static array $profiles = [];

    /** @var array<int, bool|int|float|string> */
    private static array $values = [];

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [];
        self::$variables = [];
        self::$scripts = [];
        self::$profiles = [];
        self::$values = [];
    }

    public static function createParent(string $name = 'Test Parent'): int
    {
        return self::createObject(0, 0, '', $name);
    }

    public static function createNonVariable(int $parentID, string $ident, string $name): int
    {
        return self::createObject(0, $parentID, $ident, $name);
    }

    public static function createExistingVariable(
        int $parentID,
        string $ident,
        string $name,
        int $type,
        bool|int|float|string $value
    ): int {
        $variableID = self::createVariable($type);
        self::setParent($variableID, $parentID);
        self::setIdent($variableID, $ident);
        self::setName($variableID, $name);
        self::$values[$variableID] = $value;

        return $variableID;
    }

    public static function addProfile(string $profile): void
    {
        self::$profiles[$profile] = true;
    }

    public static function createScript(string $name = 'Test Action'): int
    {
        $scriptID = self::createObject(3, 0, '', $name);
        self::$scripts[$scriptID] = true;

        return $scriptID;
    }

    public static function objectExists(int $objectID): bool
    {
        return isset(self::$objects[$objectID]);
    }

    /** @return array<string, int|string> */
    public static function getObject(int $objectID): array
    {
        if (!isset(self::$objects[$objectID])) {
            throw new RuntimeException('Fake object does not exist: ' . $objectID);
        }

        return self::$objects[$objectID];
    }

    /** @return array<string, int|string> */
    public static function getVariable(int $variableID): array
    {
        if (!isset(self::$variables[$variableID])) {
            throw new RuntimeException('Fake variable does not exist: ' . $variableID);
        }

        return self::$variables[$variableID];
    }

    public static function getObjectIDByIdent(string $ident, int $parentID): int|false
    {
        foreach (self::$objects as $objectID => $object) {
            if ($object['ParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
                return $objectID;
            }
        }

        trigger_error(
            sprintf('Ident "%s" below parent %d does not exist.', $ident, $parentID),
            E_USER_WARNING
        );

        return false;
    }

    public static function createVariable(int $type): int
    {
        $variableID = self::createObject(2, 0, '', '');
        self::$variables[$variableID] = [
            'VariableType' => $type,
            'VariableCustomProfile' => '',
            'VariableCustomAction' => 0,
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

    public static function profileExists(string $profile): bool
    {
        return isset(self::$profiles[$profile]);
    }

    public static function scriptExists(int $scriptID): bool
    {
        return isset(self::$scripts[$scriptID]);
    }

    public static function setValue(int $variableID, bool|int|float|string $value): void
    {
        self::assertVariable($variableID);
        self::$values[$variableID] = $value;
    }

    public static function getValue(int $variableID): bool|int|float|string
    {
        self::assertVariable($variableID);

        return self::$values[$variableID];
    }

    public static function variableCount(): int
    {
        return count(self::$variables);
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
    return FakeSymconRuntime::objectExists($objectID);
}

/** @return list<string> */
function IPS_GetModuleList(): array
{
    return [];
}

function IPS_VariableProfileExists(string $profile): bool
{
    return FakeSymconRuntime::profileExists($profile);
}

function IPS_ScriptExists(int $scriptID): bool
{
    return FakeSymconRuntime::scriptExists($scriptID);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    return FakeSymconRuntime::getObjectIDByIdent($ident, $parentID);
}

function IPS_CreateVariable(int $type): int
{
    return FakeSymconRuntime::createVariable($type);
}

function IPS_SetParent(int $objectID, int $parentID): void
{
    FakeSymconRuntime::setParent($objectID, $parentID);
}

function IPS_SetIdent(int $objectID, string $ident): void
{
    FakeSymconRuntime::setIdent($objectID, $ident);
}

/** @return array<string, int|string> */
function IPS_GetObject(int $objectID): array
{
    return FakeSymconRuntime::getObject($objectID);
}

/** @return array<string, int|string> */
function IPS_GetVariable(int $variableID): array
{
    return FakeSymconRuntime::getVariable($variableID);
}

function IPS_SetName(int $objectID, string $name): void
{
    FakeSymconRuntime::setName($objectID, $name);
}

function IPS_SetPosition(int $objectID, int $position): void
{
    FakeSymconRuntime::setPosition($objectID, $position);
}

function IPS_SetIcon(int $objectID, string $icon): void
{
    FakeSymconRuntime::setIcon($objectID, $icon);
}

function IPS_SetVariableCustomProfile(int $variableID, string $profile): void
{
    FakeSymconRuntime::setVariableProfile($variableID, $profile);
}

function IPS_SetVariableCustomAction(int $variableID, int $scriptID): void
{
    FakeSymconRuntime::setVariableAction($variableID, $scriptID);
}
