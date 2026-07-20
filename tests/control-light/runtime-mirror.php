<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightRuntimeMirror;

final class ControlLightRuntimeMirrorFake
{
    public static int $nextID = 1000;
    /** @var array<int, array<string, int|string|bool>> */
    public static array $objects = [];
    /** @var array<int, string> */
    public static array $contents = [];
    public static int $contentWrites = 0;
    public static bool $rejectNextContentWrite = false;

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [
            100 => self::objectData(0, 0, '', 'ControlLight', 0, '', false),
        ];
        self::$contents = [];
        self::$contentWrites = 0;
        self::$rejectNextContentWrite = false;
    }

    /** @return array<string, int|string|bool> */
    public static function objectData(
        int $type,
        int $parentID,
        string $ident,
        string $name,
        int $position,
        string $icon,
        bool $hidden
    ): array {
        return [
            'ObjectType' => $type,
            'ObjectParentID' => $parentID,
            'ObjectIdent' => $ident,
            'ObjectName' => $name,
            'ObjectPosition' => $position,
            'ObjectIcon' => $icon,
            'ObjectIsHidden' => $hidden,
        ];
    }

    public static function createExistingScript(string $content): int
    {
        $id = self::$nextID++;
        self::$objects[$id] = self::objectData(
            3,
            100,
            'SAEF_CONTROL_LIGHT_RUNTIME_MIRROR',
            'User Mirror Name',
            77,
            'UserIcon',
            true
        );
        self::$contents[$id] = $content;
        return $id;
    }
}
function IPS_ObjectExists(int $id): bool
{
    return isset(ControlLightRuntimeMirrorFake::$objects[$id]);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    foreach (ControlLightRuntimeMirrorFake::$objects as $id => $object) {
        if ($object['ObjectParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
            return $id;
        }
    }
    return false;
}

/** @return array<string, int|string|bool> */
function IPS_GetObject(int $id): array
{
    return ControlLightRuntimeMirrorFake::$objects[$id];
}

function IPS_CreateScript(int $type): int
{
    $id = ControlLightRuntimeMirrorFake::$nextID++;
    ControlLightRuntimeMirrorFake::$objects[$id] = ControlLightRuntimeMirrorFake::objectData(
        3,
        0,
        '',
        '',
        0,
        '',
        false
    );
    ControlLightRuntimeMirrorFake::$contents[$id] = '';
    return $id;
}

function IPS_SetParent(int $id, int $parentID): void
{
    ControlLightRuntimeMirrorFake::$objects[$id]['ObjectParentID'] = $parentID;
}

function IPS_SetIdent(int $id, string $ident): void
{
    ControlLightRuntimeMirrorFake::$objects[$id]['ObjectIdent'] = $ident;
}

function IPS_SetName(int $id, string $name): void
{
    ControlLightRuntimeMirrorFake::$objects[$id]['ObjectName'] = $name;
}

function IPS_SetPosition(int $id, int $position): void
{
    ControlLightRuntimeMirrorFake::$objects[$id]['ObjectPosition'] = $position;
}

function IPS_SetIcon(int $id, string $icon): void
{
    ControlLightRuntimeMirrorFake::$objects[$id]['ObjectIcon'] = $icon;
}

function IPS_SetHidden(int $id, bool $hidden): void
{
    ControlLightRuntimeMirrorFake::$objects[$id]['ObjectIsHidden'] = $hidden;
}

function IPS_GetScriptContent(int $id): string
{
    return ControlLightRuntimeMirrorFake::$contents[$id];
}

function IPS_SetScriptContent(int $id, string $content): bool
{
    ControlLightRuntimeMirrorFake::$contentWrites++;
    if (ControlLightRuntimeMirrorFake::$rejectNextContentWrite) {
        ControlLightRuntimeMirrorFake::$rejectNextContentWrite = false;
        return false;
    }
    ControlLightRuntimeMirrorFake::$contents[$id] = $content;
    return true;
}

function IPS_DeleteScript(int $id, bool $deleteFile): bool
{
    unset(ControlLightRuntimeMirrorFake::$objects[$id], ControlLightRuntimeMirrorFake::$contents[$id]);
    return true;
}

require_once __DIR__ . '/../../helpers/object/EnsureScript.php';
require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightRuntimeMirror.php';

/** @param mixed $actual @param mixed $expected */
function assertMirrorSame($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
}

function assertMirrorTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function mirrorConfig(string $runtimePath, string $runtimeSource): array
{
    return [
        'parentID' => 100,
        'ident' => 'SAEF_CONTROL_LIGHT_RUNTIME_MIRROR',
        'defaultName' => 'SAEF ControlLight Runtime Mirror',
        'defaultPosition' => 90,
        'runtimePath' => $runtimePath,
        'expectedRuntimeSha256' => hash('sha256', $runtimeSource),
        'referenceIDs' => [30003, 10001, 20002, 10001],
    ];
}

$runtimePath = tempnam(sys_get_temp_dir(), 'saef-control-light-runtime-');
if ($runtimePath === false) {
    throw new RuntimeException('Cannot create runtime mirror test file.');
}

try {
    $runtimeSource = "<?php\ndeclare(strict_types=1);\nfinal class SyntheticControlLightRuntime {}\n";
    if (file_put_contents($runtimePath, $runtimeSource) === false) {
        throw new RuntimeException('Cannot write runtime mirror test file.');
    }

    $runtimeSha256 = hash('sha256', $runtimeSource);
    $firstRender = ControlLightRuntimeMirror::render(
        $runtimeSource,
        $runtimeSha256,
        [30003, 10001, 20002, 10001]
    );
    $secondRender = ControlLightRuntimeMirror::render(
        $runtimeSource,
        $runtimeSha256,
        [20002, 30003, 10001]
    );
    assertMirrorSame($firstRender, $secondRender, 'Equivalent reference sets must render identically.');
    assertMirrorSame(
        ControlLightRuntimeMirror::extractRuntimePayload($firstRender),
        $runtimeSource,
        'The authoritative runtime payload was not embedded byte-for-byte.'
    );
    assertMirrorTrue(
        strpos($firstRender, '10001,') < strpos($firstRender, '20002,'),
        'Reference IDs were not sorted deterministically.'
    );
    assertMirrorSame(substr_count($firstRender, '10001,'), 1, 'Reference IDs were not deduplicated.');
    assertMirrorTrue(!str_contains($firstRender, 'RequestAction('), 'Mirror preamble contains an action call.');

    $hashRejected = false;
    try {
        ControlLightRuntimeMirror::render($runtimeSource, str_repeat('0', 64), [10001]);
    } catch (RuntimeException) {
        $hashRejected = true;
    }
    assertMirrorTrue($hashRejected, 'A mismatched authoritative runtime hash was accepted.');

    ControlLightRuntimeMirrorFake::reset();
    $config = mirrorConfig($runtimePath, $runtimeSource);
    $created = ControlLightRuntimeMirror::reconcile($config);
    assertMirrorSame($created['outcome'], 'created', 'First reconciliation did not create the mirror.');
    $scriptID = $created['scriptID'];
    $config['expectedScriptID'] = $scriptID;
    assertMirrorTrue(
        preg_match('/^[a-f0-9]{64}$/', $created['referenceIndexSha256']) === 1,
        'Reference-index evidence hash is missing.'
    );
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectName'],
        'SAEF ControlLight Runtime Mirror',
        'Creation default name was not applied.'
    );
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectPosition'],
        90,
        'Creation default position was not applied.'
    );

    $writesAfterCreate = ControlLightRuntimeMirrorFake::$contentWrites;
    $unchanged = ControlLightRuntimeMirror::reconcile($config);
    assertMirrorSame($unchanged['outcome'], 'unchanged', 'Identical reconciliation was not idempotent.');
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$contentWrites,
        $writesAfterCreate,
        'Identical reconciliation rewrote script content.'
    );

    ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectName'] = 'User Renamed Mirror';
    ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectPosition'] = 123;
    ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectIcon'] = 'UserIcon';
    ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectInfo'] = 'User information';
    ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectIsHidden'] = true;
    $runtimeSource .= "// updated\n";
    file_put_contents($runtimePath, $runtimeSource);
    $config = mirrorConfig($runtimePath, $runtimeSource);
    $config['expectedScriptID'] = $scriptID;
    $updated = ControlLightRuntimeMirror::reconcile($config);
    assertMirrorSame($updated['outcome'], 'updated', 'Changed runtime did not update the mirror.');
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectName'],
        'User Renamed Mirror',
        'Existing user name was overwritten.'
    );
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectPosition'],
        123,
        'Existing user position was overwritten.'
    );
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectIcon'],
        'UserIcon',
        'Existing user icon was overwritten.'
    );
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectInfo'],
        'User information',
        'Existing user information was overwritten.'
    );
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$objects[$scriptID]['ObjectIsHidden'],
        true,
        'Existing user visibility was overwritten.'
    );

    $previousSource = ControlLightRuntimeMirrorFake::$contents[$scriptID];
    $runtimeSource .= "// rollback probe\n";
    file_put_contents($runtimePath, $runtimeSource);
    $config = mirrorConfig($runtimePath, $runtimeSource);
    $config['expectedScriptID'] = $scriptID;
    ControlLightRuntimeMirrorFake::$rejectNextContentWrite = true;
    $rollbackReported = false;
    try {
        ControlLightRuntimeMirror::reconcile($config);
    } catch (RuntimeException $exception) {
        $rollbackReported = str_contains($exception->getMessage(), 'previous state was restored');
    }
    assertMirrorTrue($rollbackReported, 'Failed update did not report a successful rollback.');
    assertMirrorSame(
        ControlLightRuntimeMirrorFake::$contents[$scriptID],
        $previousSource,
        'Failed update did not restore exact previous source.'
    );

    ControlLightRuntimeMirrorFake::reset();
    unset($config['expectedScriptID']);
    ControlLightRuntimeMirrorFake::$rejectNextContentWrite = true;
    $createRollbackReported = false;
    try {
        ControlLightRuntimeMirror::reconcile($config);
    } catch (RuntimeException $exception) {
        $createRollbackReported = str_contains($exception->getMessage(), 'previous state was restored');
    }
    assertMirrorTrue($createRollbackReported, 'Failed first creation did not report cleanup.');
    assertMirrorSame(count(ControlLightRuntimeMirrorFake::$contents), 0, 'Failed first creation left a script behind.');

    ControlLightRuntimeMirrorFake::reset();
    ControlLightRuntimeMirrorFake::$objects[1000] = ControlLightRuntimeMirrorFake::objectData(
        2,
        100,
        'SAEF_CONTROL_LIGHT_RUNTIME_MIRROR',
        'Conflict',
        0,
        '',
        false
    );
    $typeConflictRejected = false;
    try {
        ControlLightRuntimeMirror::reconcile($config);
    } catch (RuntimeException $exception) {
        $typeConflictRejected = str_contains($exception->getMessage(), 'is not a script');
    }
    assertMirrorTrue($typeConflictRejected, 'Object type ownership conflict was accepted.');

    ControlLightRuntimeMirrorFake::reset();
    $movedID = ControlLightRuntimeMirrorFake::createExistingScript('previous');
    ControlLightRuntimeMirrorFake::$objects[$movedID]['ObjectParentID'] = 0;
    $config['expectedScriptID'] = $movedID;
    $ownershipDriftRejected = false;
    try {
        ControlLightRuntimeMirror::reconcile($config);
    } catch (RuntimeException $exception) {
        $ownershipDriftRejected = str_contains($exception->getMessage(), 'ownership drift');
    }
    assertMirrorTrue($ownershipDriftRejected, 'Moved owned mirror script was silently recreated.');
    assertMirrorSame(
        count(ControlLightRuntimeMirrorFake::$contents),
        1,
        'Ownership-drift rejection created another mirror script.'
    );

    echo "ControlLight managed runtime mirror tests passed.\n";
} finally {
    if (is_file($runtimePath)) {
        unlink($runtimePath);
    }
}
