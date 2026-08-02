<?php

declare(strict_types=1);

final class PresentationFakeSymconRuntime
{
    private static int $nextID = 1000;

    /** @var array<int, array<string, bool|int|string>> */
    private static array $objects = [];

    /** @var array<int, array<string, int|string>> */
    private static array $variables = [];

    /** @var array<int, array{ModuleInfo: array{ModuleID: string}}> */
    private static array $instances = [];

    /** @var array<int, int> */
    private static array $links = [];

    /** @var array<int, true> */
    private static array $scripts = [];

    /** @var array<string, true> */
    private static array $profiles = [];

    /** @var list<string> */
    private static array $modules = [];

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [];
        self::$variables = [];
        self::$instances = [];
        self::$links = [];
        self::$scripts = [];
        self::$profiles = [];
        self::$modules = [];
    }

    public static function createObject(int $type): int
    {
        $objectID = self::$nextID++;
        self::$objects[$objectID] = [
            'ObjectType' => $type,
            'ParentID' => 0,
            'ObjectIdent' => '',
            'ObjectName' => '',
            'ObjectPosition' => 0,
            'ObjectIcon' => '',
            'ObjectIsHidden' => false,
        ];

        return $objectID;
    }

    public static function createVariable(int $type): int
    {
        $variableID = self::createObject(2);
        self::$variables[$variableID] = [
            'VariableType' => $type,
            'VariableCustomProfile' => '',
            'VariableCustomAction' => 0,
        ];

        return $variableID;
    }

    public static function createInstance(string $moduleID): int
    {
        $instanceID = self::createObject(1);
        self::$instances[$instanceID] = ['ModuleInfo' => ['ModuleID' => $moduleID]];

        return $instanceID;
    }

    public static function createLink(): int
    {
        $linkID = self::createObject(6);
        self::$links[$linkID] = 0;

        return $linkID;
    }

    public static function createScript(): int
    {
        $scriptID = self::createObject(3);
        self::$scripts[$scriptID] = true;

        return $scriptID;
    }

    public static function addModule(string $moduleID): void
    {
        self::$modules[] = $moduleID;
    }

    public static function addProfile(string $profile): void
    {
        self::$profiles[$profile] = true;
    }

    public static function objectExists(int $objectID): bool
    {
        return isset(self::$objects[$objectID]);
    }

    /** @return list<string> */
    public static function modules(): array
    {
        return self::$modules;
    }

    public static function findByIdent(string $ident, int $parentID): int|false
    {
        foreach (self::$objects as $objectID => $object) {
            if ($object['ParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
                return $objectID;
            }
        }

        return false;
    }

    /** @return array<string, bool|int|string> */
    public static function object(int $objectID): array
    {
        self::assertObject($objectID);

        return self::$objects[$objectID];
    }

    /** @return array<string, int|string> */
    public static function variable(int $variableID): array
    {
        return self::$variables[$variableID];
    }

    /** @return array{ModuleInfo: array{ModuleID: string}} */
    public static function instance(int $instanceID): array
    {
        return self::$instances[$instanceID];
    }

    public static function linkTarget(int $linkID): int
    {
        return self::$links[$linkID];
    }

    public static function profileExists(string $profile): bool
    {
        return isset(self::$profiles[$profile]);
    }

    public static function scriptExists(int $scriptID): bool
    {
        return isset(self::$scripts[$scriptID]);
    }

    public static function setObjectField(int $objectID, string $field, bool|int|string $value): void
    {
        self::assertObject($objectID);
        self::$objects[$objectID][$field] = $value;
    }

    public static function setVariableField(int $variableID, string $field, int|string $value): void
    {
        self::$variables[$variableID][$field] = $value;
    }

    public static function setLinkTarget(int $linkID, int $targetID): void
    {
        self::$links[$linkID] = $targetID;
    }

    private static function assertObject(int $objectID): void
    {
        if (!isset(self::$objects[$objectID])) {
            throw new RuntimeException('Fake object does not exist: ' . $objectID);
        }
    }
}

function IPS_ObjectExists(int $objectID): bool
{
    return PresentationFakeSymconRuntime::objectExists($objectID);
}

/** @return list<string> */
function IPS_GetModuleList(): array
{
    return PresentationFakeSymconRuntime::modules();
}

function IPS_VariableProfileExists(string $profile): bool
{
    return PresentationFakeSymconRuntime::profileExists($profile);
}

function IPS_ScriptExists(int $scriptID): bool
{
    return PresentationFakeSymconRuntime::scriptExists($scriptID);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    return PresentationFakeSymconRuntime::findByIdent($ident, $parentID);
}

/** @return array<string, bool|int|string> */
function IPS_GetObject(int $objectID): array
{
    return PresentationFakeSymconRuntime::object($objectID);
}

/** @return array<string, int|string> */
function IPS_GetVariable(int $variableID): array
{
    return PresentationFakeSymconRuntime::variable($variableID);
}

/** @return array{ModuleInfo: array{ModuleID: string}} */
function IPS_GetInstance(int $instanceID): array
{
    return PresentationFakeSymconRuntime::instance($instanceID);
}

function IPS_CreateCategory(): int
{
    return PresentationFakeSymconRuntime::createObject(0);
}

function IPS_CreateVariable(int $type): int
{
    return PresentationFakeSymconRuntime::createVariable($type);
}

function IPS_CreateInstance(string $moduleID): int
{
    return PresentationFakeSymconRuntime::createInstance($moduleID);
}

function IPS_CreateLink(): int
{
    return PresentationFakeSymconRuntime::createLink();
}

function IPS_CreateScript(int $scriptType): int
{
    unset($scriptType);

    return PresentationFakeSymconRuntime::createScript();
}

function IPS_SetParent(int $objectID, int $parentID): void
{
    PresentationFakeSymconRuntime::setObjectField($objectID, 'ParentID', $parentID);
}

function IPS_SetIdent(int $objectID, string $ident): void
{
    PresentationFakeSymconRuntime::setObjectField($objectID, 'ObjectIdent', $ident);
}

function IPS_SetName(int $objectID, string $name): void
{
    PresentationFakeSymconRuntime::setObjectField($objectID, 'ObjectName', $name);
}

function IPS_SetPosition(int $objectID, int $position): void
{
    PresentationFakeSymconRuntime::setObjectField($objectID, 'ObjectPosition', $position);
}

function IPS_SetIcon(int $objectID, string $icon): void
{
    PresentationFakeSymconRuntime::setObjectField($objectID, 'ObjectIcon', $icon);
}

function IPS_SetHidden(int $objectID, bool $hidden): void
{
    PresentationFakeSymconRuntime::setObjectField($objectID, 'ObjectIsHidden', $hidden);
}

function IPS_SetVariableCustomProfile(int $variableID, string $profile): void
{
    PresentationFakeSymconRuntime::setVariableField($variableID, 'VariableCustomProfile', $profile);
}

function IPS_SetVariableCustomAction(int $variableID, int $actionID): void
{
    PresentationFakeSymconRuntime::setVariableField($variableID, 'VariableCustomAction', $actionID);
}

function IPS_SetLinkTargetID(int $linkID, int $targetID): void
{
    PresentationFakeSymconRuntime::setLinkTarget($linkID, $targetID);
}

require_once dirname(__DIR__, 2) . '/helpers/object/EnsureCategory.php';
require_once dirname(__DIR__, 2) . '/helpers/object/EnsureVariable.php';
require_once dirname(__DIR__, 2) . '/helpers/object/EnsureInstance.php';
require_once dirname(__DIR__, 2) . '/helpers/object/EnsureDummy.php';
require_once dirname(__DIR__, 2) . '/helpers/object/EnsureLink.php';
require_once dirname(__DIR__, 2) . '/helpers/object/EnsureScript.php';

const TEST_MODULE_GUID = '{11111111-1111-1111-1111-111111111111}';

try {
    verifyMutableObjectGuard();
    verifyVariablePresentationPolicy();
    verifyCategoryPresentationPolicy();
    verifyInstanceAndDummyPresentationPolicy();
    verifyLinkPresentationPolicy();
    verifyScriptPresentationPolicy();
    fwrite(STDOUT, "PASS: Object Ensure presentation ownership contracts.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}

function verifyMutableObjectGuard(): void
{
    PresentationFakeSymconRuntime::reset();
    $categoryID = PresentationFakeSymconRuntime::createObject(0);
    SAEF_ValidateMutableObject($categoryID, 0);

    assertThrows(
        static fn () => SAEF_ValidateMutableObject(0),
        InvalidArgumentException::class,
        'Root object ID 0 must never be accepted as a mutation target.'
    );
    assertThrows(
        static fn () => SAEF_ValidateMutableObject($categoryID, 1),
        RuntimeException::class,
        'A mutation target with an unexpected object type must be rejected.'
    );
}

function verifyVariablePresentationPolicy(): void
{
    PresentationFakeSymconRuntime::reset();
    PresentationFakeSymconRuntime::addProfile('SAEF.Test');
    $parentID = PresentationFakeSymconRuntime::createObject(0);
    $actionID = PresentationFakeSymconRuntime::createScript();
    $variableID = SAEF_EnsureVariable(
        $parentID,
        'STATE',
        'Initial Name',
        1,
        '',
        null,
        10,
        'InitialIcon',
        false
    );
    assertPresentation($variableID, 'Initial Name', 10, 'InitialIcon', false);

    applyUserPresentation($variableID);
    SAEF_EnsureVariable(
        $parentID,
        'STATE',
        'Managed Name',
        1,
        'SAEF.Test',
        $actionID,
        20,
        'ManagedIcon',
        false
    );
    assertPresentation($variableID, 'User Name', 99, 'UserIcon', true);
    assertSameValue(
        'SAEF.Test',
        PresentationFakeSymconRuntime::variable($variableID)['VariableCustomProfile'],
        'Variable profile was not reconciled independently from presentation.'
    );
    assertSameValue(
        $actionID,
        PresentationFakeSymconRuntime::variable($variableID)['VariableCustomAction'],
        'Variable action was not reconciled independently from presentation.'
    );

    SAEF_EnsureVariable($parentID, 'STATE', 'Managed Name', 1, 'SAEF.Test', $actionID, 20, 'ManagedIcon');
    assertPresentation($variableID, 'Managed Name', 20, 'ManagedIcon', true);
}

function verifyCategoryPresentationPolicy(): void
{
    PresentationFakeSymconRuntime::reset();
    $parentID = PresentationFakeSymconRuntime::createObject(0);
    $categoryID = SAEF_EnsureCategory($parentID, 'CATEGORY', 'Initial', 10, 'InitialIcon', false);
    applyUserPresentation($categoryID);
    SAEF_EnsureCategory($parentID, 'CATEGORY', 'Managed', 20, 'ManagedIcon', false);
    assertPresentation($categoryID, 'User Name', 99, 'UserIcon', true);
}

function verifyInstanceAndDummyPresentationPolicy(): void
{
    PresentationFakeSymconRuntime::reset();
    PresentationFakeSymconRuntime::addModule(TEST_MODULE_GUID);
    PresentationFakeSymconRuntime::addModule(SAEF_DUMMY_MODULE_GUID);
    $parentID = PresentationFakeSymconRuntime::createObject(0);
    $instanceID = SAEF_EnsureInstance(
        $parentID,
        'INSTANCE',
        'Initial',
        TEST_MODULE_GUID,
        10,
        'InitialIcon',
        false,
        false
    );
    applyUserPresentation($instanceID);
    SAEF_EnsureInstance($parentID, 'INSTANCE', 'Managed', TEST_MODULE_GUID, 20, 'ManagedIcon', false, false);
    assertPresentation($instanceID, 'User Name', 99, 'UserIcon', true);

    $dummyID = SAEF_EnsureDummy($parentID, 'DUMMY', 'Initial Dummy', 30, 'DummyIcon', false, false);
    applyUserPresentation($dummyID);
    SAEF_EnsureDummy($parentID, 'DUMMY', 'Managed Dummy', 40, 'ManagedDummyIcon', false, false);
    assertPresentation($dummyID, 'User Name', 99, 'UserIcon', true);
}

function verifyLinkPresentationPolicy(): void
{
    PresentationFakeSymconRuntime::reset();
    $parentID = PresentationFakeSymconRuntime::createObject(0);
    $firstTargetID = PresentationFakeSymconRuntime::createObject(0);
    $secondTargetID = PresentationFakeSymconRuntime::createObject(0);
    $linkID = SAEF_EnsureLink(
        $parentID,
        'LINK',
        'Initial',
        $firstTargetID,
        10,
        'InitialIcon',
        false,
        false
    );
    applyUserPresentation($linkID);
    SAEF_EnsureLink($parentID, 'LINK', 'Managed', $secondTargetID, 20, 'ManagedIcon', false, false);
    assertPresentation($linkID, 'User Name', 99, 'UserIcon', true);
    assertSameValue(
        $secondTargetID,
        PresentationFakeSymconRuntime::linkTarget($linkID),
        'Link target was not reconciled independently from presentation.'
    );
}

function verifyScriptPresentationPolicy(): void
{
    PresentationFakeSymconRuntime::reset();
    $parentID = PresentationFakeSymconRuntime::createObject(0);
    $scriptID = SAEF_EnsureScript($parentID, 'SCRIPT', 'Initial', 0, 10, 'InitialIcon', false, false);
    applyUserPresentation($scriptID);
    SAEF_EnsureScript($parentID, 'SCRIPT', 'Managed', 0, 20, 'ManagedIcon', false, false);
    assertPresentation($scriptID, 'User Name', 99, 'UserIcon', true);
}

function applyUserPresentation(int $objectID): void
{
    IPS_SetName($objectID, 'User Name');
    IPS_SetPosition($objectID, 99);
    IPS_SetIcon($objectID, 'UserIcon');
    IPS_SetHidden($objectID, true);
}

function assertPresentation(
    int $objectID,
    string $name,
    int $position,
    string $icon,
    bool $hidden
): void {
    $object = PresentationFakeSymconRuntime::object($objectID);
    assertSameValue($name, $object['ObjectName'], 'Object name differs.');
    assertSameValue($position, $object['ObjectPosition'], 'Object position differs.');
    assertSameValue($icon, $object['ObjectIcon'], 'Object icon differs.');
    assertSameValue($hidden, $object['ObjectIsHidden'], 'Object visibility differs.');
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $expectedClass */
function assertThrows(callable $operation, string $expectedClass, string $message): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException($message . ' Unexpected exception: ' . $exception::class);
    }

    throw new RuntimeException($message);
}
