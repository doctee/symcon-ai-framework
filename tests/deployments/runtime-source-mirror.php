<?php

declare(strict_types=1);

use SAEF\Deployment\SaefRuntimeSourceMirror;

final class RuntimeSourceMirrorFake
{
    public static int $nextID = 1000;
    /** @var array<int, array<string, int|string|bool>> */
    public static array $objects = [];
    /** @var array<int, string> */
    public static array $contents = [];
    /** @var array<int, list<int>> */
    public static array $children = [];
    public static int $contentWrites = 0;
    public static bool $rejectNextContentWrite = false;

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [100 => self::objectData(0, 0, '', 'SAEF', 0, '', false)];
        self::$contents = [];
        self::$children = [];
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
}

function IPS_ObjectExists(int $id): bool
{
    return isset(RuntimeSourceMirrorFake::$objects[$id]);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    foreach (RuntimeSourceMirrorFake::$objects as $id => $object) {
        if ($object['ObjectParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
            return $id;
        }
    }
    return false;
}

/** @return array<string, int|string|bool> */
function IPS_GetObject(int $id): array
{
    return RuntimeSourceMirrorFake::$objects[$id];
}

/** @return list<int> */
function IPS_GetChildrenIDs(int $id): array
{
    return RuntimeSourceMirrorFake::$children[$id] ?? [];
}

function IPS_CreateScript(int $type): int
{
    $id = RuntimeSourceMirrorFake::$nextID++;
    RuntimeSourceMirrorFake::$objects[$id] = RuntimeSourceMirrorFake::objectData(3, 0, '', '', 0, '', false);
    RuntimeSourceMirrorFake::$contents[$id] = '';
    return $id;
}

function IPS_SetParent(int $id, int $parentID): void
{
    RuntimeSourceMirrorFake::$objects[$id]['ObjectParentID'] = $parentID;
}

function IPS_SetIdent(int $id, string $ident): void
{
    RuntimeSourceMirrorFake::$objects[$id]['ObjectIdent'] = $ident;
}

function IPS_SetName(int $id, string $name): void
{
    RuntimeSourceMirrorFake::$objects[$id]['ObjectName'] = $name;
}

function IPS_SetPosition(int $id, int $position): void
{
    RuntimeSourceMirrorFake::$objects[$id]['ObjectPosition'] = $position;
}

function IPS_SetIcon(int $id, string $icon): void
{
    RuntimeSourceMirrorFake::$objects[$id]['ObjectIcon'] = $icon;
}

function IPS_SetHidden(int $id, bool $hidden): void
{
    RuntimeSourceMirrorFake::$objects[$id]['ObjectIsHidden'] = $hidden;
}

function IPS_GetScriptContent(int $id): string
{
    return RuntimeSourceMirrorFake::$contents[$id];
}

function IPS_SetScriptContent(int $id, string $content): bool
{
    RuntimeSourceMirrorFake::$contentWrites++;
    if (RuntimeSourceMirrorFake::$rejectNextContentWrite) {
        RuntimeSourceMirrorFake::$rejectNextContentWrite = false;
        return false;
    }
    RuntimeSourceMirrorFake::$contents[$id] = $content;
    return true;
}

function IPS_DeleteScript(int $id, bool $deleteFile): bool
{
    unset(RuntimeSourceMirrorFake::$objects[$id], RuntimeSourceMirrorFake::$contents[$id]);
    return true;
}

require_once __DIR__ . '/../../helpers/object/EnsureScript.php';
require_once __DIR__ . '/../../deployments/symcon/windows/SaefRuntimeSourceMirror.php';

/** @param mixed $actual @param mixed $expected */
function assertRuntimeMirrorSame($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
}

function assertRuntimeMirrorTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{root: string, filesetSha256: string} */
function writeRuntimeMirrorFileset(string $root, string $helperSource): array
{
    $sources = [
        'helpers/object/EnsureScript.php' => $helperSource,
        'case-studies/example/Runtime.php' => "<?php\nfinal class DomainRuntime {}\n",
    ];
    foreach ($sources as $relative => $source) {
        $path = $root . '/' . $relative;
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
            throw new RuntimeException('Cannot create synthetic fileset directory.');
        }
        if (file_put_contents($path, $source) === false) {
            throw new RuntimeException('Cannot write synthetic fileset source.');
        }
    }

    $filesetSha256 = hash('sha256', 'synthetic-fileset-' . hash('sha256', $helperSource));
    $orderedSources = [];
    foreach ($sources as $path => $source) {
        $orderedSources[] = ['path' => $path, 'sha256' => hash('sha256', $source)];
    }
    $sourceMap = [
        'formatVersion' => 1,
        'filesetSha256' => $filesetSha256,
        'orderedSources' => $orderedSources,
    ];
    file_put_contents(
        $root . '/fileset.sources.json',
        json_encode($sourceMap, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    file_put_contents($root . '/fileset.sha256', $filesetSha256 . "  fileset\n");

    return ['root' => $root, 'filesetSha256' => $filesetSha256];
}

function removeRuntimeMirrorTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

$filesetRoot = sys_get_temp_dir() . '/saef-runtime-mirror-' . bin2hex(random_bytes(8));
mkdir($filesetRoot, 0777, true);

try {
    $helperSource = "<?php\nfunction SAEF_Example(): void {}\n";
    $fileset = writeRuntimeMirrorFileset($filesetRoot, $helperSource);
    RuntimeSourceMirrorFake::reset();
    $config = [
        'filesetPath' => $fileset['root'],
        'parentID' => 100,
        'ident' => 'SAEF_RUNTIME_SOURCE_MIRROR',
        'defaultName' => 'SAEF Runtime Source Mirror',
        'defaultPosition' => 90,
    ];

    $created = SaefRuntimeSourceMirror::reconcile($config);
    assertRuntimeMirrorSame($created['outcome'], 'created', 'First reconciliation did not create the mirror.');
    assertRuntimeMirrorSame($created['helperSourceCount'], 1, 'Domain source leaked into the helper mirror.');
    $scriptID = $created['scriptID'];
    $config['expectedScriptID'] = $scriptID;
    $content = RuntimeSourceMirrorFake::$contents[$scriptID];
    $expectedPayload = "/* ===== SAEF SOURCE: helpers/object/EnsureScript.php (";
    $expectedPayload .= hash('sha256', $helperSource) . ") ===== */\n" . $helperSource;
    assertRuntimeMirrorSame(
        SaefRuntimeSourceMirror::extractPayload($content),
        $expectedPayload,
        'Helper source payload was not embedded byte-for-byte.'
    );
    assertRuntimeMirrorTrue(!str_contains($content, 'DomainRuntime'), 'Domain runtime was mirrored.');
    assertRuntimeMirrorTrue(str_contains($content, '__halt_compiler();'), 'Mirror is not inert.');

    $unownedConfig = $config;
    unset($unownedConfig['expectedScriptID']);
    $unownedRejected = false;
    try {
        SaefRuntimeSourceMirror::reconcile($unownedConfig);
    } catch (RuntimeException) {
        $unownedRejected = true;
    }
    assertRuntimeMirrorTrue($unownedRejected, 'Existing mirror was adopted without pinned deployment state.');

    $writesAfterCreate = RuntimeSourceMirrorFake::$contentWrites;
    $unchanged = SaefRuntimeSourceMirror::reconcile($config);
    assertRuntimeMirrorSame($unchanged['outcome'], 'unchanged', 'Identical mirror was not a no-op.');
    assertRuntimeMirrorSame(
        RuntimeSourceMirrorFake::$contentWrites,
        $writesAfterCreate,
        'Identical reconciliation rewrote the mirror.'
    );

    RuntimeSourceMirrorFake::$objects[$scriptID]['ObjectName'] = 'User Name';
    RuntimeSourceMirrorFake::$objects[$scriptID]['ObjectPosition'] = 123;
    $updatedHelper = "<?php\nfunction SAEF_Example(): void { echo 'updated'; }\n";
    writeRuntimeMirrorFileset($filesetRoot, $updatedHelper);
    $updated = SaefRuntimeSourceMirror::reconcile($config);
    assertRuntimeMirrorSame($updated['outcome'], 'updated', 'Changed source did not update the mirror.');
    assertRuntimeMirrorSame(
        RuntimeSourceMirrorFake::$objects[$scriptID]['ObjectName'],
        'User Name',
        'Update overwrote presentation metadata.'
    );

    $previousContent = RuntimeSourceMirrorFake::$contents[$scriptID];
    writeRuntimeMirrorFileset($filesetRoot, $helperSource);
    RuntimeSourceMirrorFake::$rejectNextContentWrite = true;
    $rolledBack = false;
    try {
        SaefRuntimeSourceMirror::reconcile($config);
    } catch (RuntimeException) {
        $rolledBack = true;
    }
    assertRuntimeMirrorTrue($rolledBack, 'Rejected content write did not fail reconciliation.');
    assertRuntimeMirrorSame(
        RuntimeSourceMirrorFake::$contents[$scriptID],
        $previousContent,
        'Rejected update did not preserve the previous mirror.'
    );

    RuntimeSourceMirrorFake::$children[$scriptID] = [2000];
    $childRejected = false;
    try {
        SaefRuntimeSourceMirror::reconcile($config);
    } catch (RuntimeException) {
        $childRejected = true;
    }
    assertRuntimeMirrorTrue($childRejected, 'Mirror with child objects was accepted.');
    RuntimeSourceMirrorFake::$children[$scriptID] = [];

    file_put_contents($filesetRoot . '/fileset.sha256', str_repeat('0', 64) . "  fileset\n");
    $hashRejected = false;
    try {
        SaefRuntimeSourceMirror::reconcile($config);
    } catch (RuntimeException) {
        $hashRejected = true;
    }
    assertRuntimeMirrorTrue($hashRejected, 'Fileset provenance drift was accepted.');

    fwrite(STDOUT, "Runtime source mirror tests passed.\n");
} finally {
    removeRuntimeMirrorTree($filesetRoot);
}
