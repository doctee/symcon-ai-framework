<?php
declare(strict_types=1);

final class DiagnosticsHelperFakeRuntime
{
    public static int $nextID = 1000;

    /** @var array<int, array<string, int|string>> */
    public static array $objects = [];

    /** @var array<int, array{VariableType: int, VariableCustomProfile: string}> */
    public static array $variables = [];

    /** @var array<int, mixed> */
    public static array $values = [];

    public static bool $semaphoreAvailable = true;

    /** @var list<string> */
    public static array $semaphoreEnters = [];

    /** @var list<string> */
    public static array $semaphoreLeaves = [];

    public static function reset(): void
    {
        self::$nextID = 1000;
        self::$objects = [
            100 => [
                'ObjectType' => 0,
                'ObjectParentID' => 0,
                'ObjectIdent' => 'DIAGNOSTICS',
                'ObjectName' => 'Diagnostics',
                'ObjectPosition' => 0,
                'ObjectIcon' => '',
            ],
        ];
        self::$variables = [];
        self::$values = [];
        self::$semaphoreAvailable = true;
        self::$semaphoreEnters = [];
        self::$semaphoreLeaves = [];
    }

    public static function addVariable(int $type, mixed $value, string $ident = ''): int
    {
        $variableID = self::createVariable($type);
        self::$objects[$variableID]['ObjectParentID'] = 100;
        self::$objects[$variableID]['ObjectIdent'] = $ident;
        self::$values[$variableID] = $value;

        return $variableID;
    }

    public static function createVariable(int $type): int
    {
        $variableID = self::$nextID++;
        self::$objects[$variableID] = [
            'ObjectType' => 2,
            'ObjectParentID' => 0,
            'ObjectIdent' => '',
            'ObjectName' => '',
            'ObjectPosition' => 0,
            'ObjectIcon' => '',
        ];
        self::$variables[$variableID] = [
            'VariableType' => $type,
            'VariableCustomProfile' => '',
        ];
        self::$values[$variableID] = match ($type) {
            0 => false,
            1 => 0,
            2 => 0.0,
            3 => '',
            default => null,
        };

        return $variableID;
    }
}

function IPS_ObjectExists(int $id): bool
{
    return isset(DiagnosticsHelperFakeRuntime::$objects[$id]);
}

function IPS_VariableExists(int $id): bool
{
    return isset(DiagnosticsHelperFakeRuntime::$variables[$id]);
}

function IPS_ScriptExists(int $id): bool
{
    return false;
}

function IPS_VariableProfileExists(string $name): bool
{
    return $name === '~UnixTimestamp';
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    foreach (DiagnosticsHelperFakeRuntime::$objects as $id => $object) {
        if ($object['ObjectParentID'] === $parentID && $object['ObjectIdent'] === $ident) {
            return $id;
        }
    }

    return false;
}

/** @return array<string, int|string> */
function IPS_GetObject(int $id): array
{
    return DiagnosticsHelperFakeRuntime::$objects[$id];
}

/** @return array{VariableType: int, VariableCustomProfile: string} */
function IPS_GetVariable(int $id): array
{
    return DiagnosticsHelperFakeRuntime::$variables[$id];
}

function IPS_CreateVariable(int $type): int
{
    return DiagnosticsHelperFakeRuntime::createVariable($type);
}

function IPS_SetParent(int $id, int $parentID): void
{
    DiagnosticsHelperFakeRuntime::$objects[$id]['ObjectParentID'] = $parentID;
}

function IPS_SetIdent(int $id, string $ident): void
{
    DiagnosticsHelperFakeRuntime::$objects[$id]['ObjectIdent'] = $ident;
}

function IPS_SetName(int $id, string $name): void
{
    DiagnosticsHelperFakeRuntime::$objects[$id]['ObjectName'] = $name;
}

function IPS_SetPosition(int $id, int $position): void
{
    DiagnosticsHelperFakeRuntime::$objects[$id]['ObjectPosition'] = $position;
}

function IPS_SetIcon(int $id, string $icon): void
{
    DiagnosticsHelperFakeRuntime::$objects[$id]['ObjectIcon'] = $icon;
}

function IPS_SetVariableCustomProfile(int $id, string $profile): void
{
    DiagnosticsHelperFakeRuntime::$variables[$id]['VariableCustomProfile'] = $profile;
}

function IPS_SetVariableCustomAction(int $id, int $scriptID): void
{
}

function GetValue(int $id): mixed
{
    return DiagnosticsHelperFakeRuntime::$values[$id];
}

function SetValue(int $id, mixed $value): void
{
    DiagnosticsHelperFakeRuntime::$values[$id] = $value;
}

function IPS_SemaphoreEnter(string $name, int $milliseconds): bool
{
    DiagnosticsHelperFakeRuntime::$semaphoreEnters[] = $name . ':' . $milliseconds;

    return DiagnosticsHelperFakeRuntime::$semaphoreAvailable;
}

function IPS_SemaphoreLeave(string $name): bool
{
    DiagnosticsHelperFakeRuntime::$semaphoreLeaves[] = $name;

    return true;
}

require_once __DIR__ . '/../../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../../helpers/diagnostics/Registry.php';
require_once __DIR__ . '/../../helpers/diagnostics/Statistics.php';
require_once __DIR__ . '/../../helpers/diagnostics/ErrorRingBuffer.php';

function assertDiagnosticsSame(mixed $expected, mixed $actual, string $message): void
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
function assertDiagnosticsThrows(string $expectedClass, callable $operation, string $message): void
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

$tests['normalizes configuration recursively and preserves list order'] = static function (): void {
    $first = [
        'runtime' => ['lastRun' => 10],
        'nested' => ['z' => 3, 'a' => 1, 'timestamp' => 20],
        'list' => ['first', 'second'],
    ];
    $second = [
        'list' => ['first', 'second'],
        'nested' => ['timestamp' => 99, 'a' => 1, 'z' => 3],
        'runtime' => ['lastRun' => 30],
    ];
    $ignoreKeys = ['timestamp', 'lastRun', 'runtime'];

    assertDiagnosticsSame(
        SAEF_CreateConfigurationHash($first, $ignoreKeys),
        SAEF_CreateConfigurationHash($second, $ignoreKeys),
        'Equivalent configuration hashes differ.'
    );
    assertDiagnosticsSame(
        ['list' => ['first', 'second'], 'nested' => ['a' => 1, 'z' => 3]],
        SAEF_NormalizeConfigurationForHash($first, $ignoreKeys),
        'Configuration normalization differs.'
    );
    assertDiagnosticsSame(
        false,
        SAEF_CreateConfigurationHash($first, $ignoreKeys)
            === SAEF_CreateConfigurationHash(['list' => ['second', 'first'], 'nested' => ['a' => 1, 'z' => 3]]),
        'List order did not affect the hash.'
    );

    assertDiagnosticsThrows(
        InvalidArgumentException::class,
        static fn(): array => SAEF_NormalizeConfigurationForHash([], [new stdClass()]),
        'Invalid ignored key was accepted.'
    );
};

$tests['ensures and round-trips registry metadata'] = static function (): void {
    DiagnosticsHelperFakeRuntime::reset();
    $variableID = SAEF_EnsureRegistryVariable(100, 'REGISTRY', 'Registry', 10, 'Database', false);

    assertDiagnosticsSame(3, IPS_GetVariable($variableID)['VariableType'], 'Registry type differs.');
    assertDiagnosticsSame([], SAEF_ReadRegistry($variableID), 'Empty registry differs.');

    SAEF_WriteRegistry($variableID, ['version' => 2, 'source' => 'path/segment']);
    $updated = SAEF_UpdateRegistryEntry($variableID, 'state', 'ready');

    assertDiagnosticsSame(
        ['version' => 2, 'source' => 'path/segment', 'state' => 'ready'],
        $updated,
        'Updated registry differs.'
    );
    assertDiagnosticsSame($updated, SAEF_ReadRegistry($variableID), 'Registry round-trip differs.');
};

$tests['rejects invalid registry state'] = static function (): void {
    DiagnosticsHelperFakeRuntime::reset();
    $stringID = DiagnosticsHelperFakeRuntime::addVariable(3, '{invalid');

    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): array => SAEF_ReadRegistry($stringID),
        'Invalid registry JSON was accepted.'
    );

    DiagnosticsHelperFakeRuntime::$values[$stringID] = '42';
    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): array => SAEF_ReadRegistry($stringID),
        'Scalar registry JSON was accepted.'
    );

    $integerID = DiagnosticsHelperFakeRuntime::addVariable(1, 0);
    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): array => SAEF_ReadRegistry($integerID),
        'Integer registry variable was accepted.'
    );
};

$tests['increments finite integer and float statistics'] = static function (): void {
    DiagnosticsHelperFakeRuntime::reset();
    $integerID = DiagnosticsHelperFakeRuntime::addVariable(1, 3);
    $floatID = DiagnosticsHelperFakeRuntime::addVariable(2, 1.5);

    assertDiagnosticsSame(5, SAEF_IncrementStatistic($integerID, 2.0), 'Integer increment differs.');
    assertDiagnosticsSame(2.0, SAEF_IncrementStatistic($floatID, 0.5), 'Float increment differs.');
    assertDiagnosticsSame(
        ['SAEF_STATISTIC_' . $integerID . ':1000', 'SAEF_STATISTIC_' . $floatID . ':1000'],
        DiagnosticsHelperFakeRuntime::$semaphoreEnters,
        'Statistic increments did not use variable-specific serialization.'
    );
    assertDiagnosticsSame(
        ['SAEF_STATISTIC_' . $integerID, 'SAEF_STATISTIC_' . $floatID],
        DiagnosticsHelperFakeRuntime::$semaphoreLeaves,
        'Statistic increment semaphores were not released.'
    );

    assertDiagnosticsThrows(
        InvalidArgumentException::class,
        static fn(): int|float => SAEF_IncrementStatistic($integerID, 0.5),
        'Fractional integer increment was accepted.'
    );
    assertDiagnosticsSame(5, GetValue($integerID), 'Rejected increment changed the integer statistic.');

    DiagnosticsHelperFakeRuntime::$values[$integerID] = PHP_INT_MAX;
    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): int|float => SAEF_IncrementStatistic($integerID),
        'Integer overflow was accepted.'
    );

    assertDiagnosticsThrows(
        InvalidArgumentException::class,
        static fn(): int|float => SAEF_IncrementStatistic($floatID, INF),
        'Infinite float result was accepted.'
    );

    DiagnosticsHelperFakeRuntime::$values[$integerID] = 5;
    DiagnosticsHelperFakeRuntime::$semaphoreAvailable = false;
    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): int|float => SAEF_IncrementStatistic($integerID),
        'Busy statistic increment was accepted.'
    );
    assertDiagnosticsSame(5, GetValue($integerID), 'Busy statistic increment changed the value.');
};

$tests['sets timestamps and rejects incompatible statistic types'] = static function (): void {
    DiagnosticsHelperFakeRuntime::reset();
    $timestampID = DiagnosticsHelperFakeRuntime::addVariable(1, 0);
    $booleanID = DiagnosticsHelperFakeRuntime::addVariable(0, false);

    SAEF_SetStatisticTimestamp($timestampID, 123);
    assertDiagnosticsSame(123, GetValue($timestampID), 'Explicit timestamp differs.');

    $before = time();
    SAEF_SetStatisticTimestamp($timestampID);
    $after = time();
    $timestamp = GetValue($timestampID);
    assertDiagnosticsSame(true, is_int($timestamp) && $timestamp >= $before && $timestamp <= $after, 'Default timestamp differs.');

    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): int|float => SAEF_IncrementStatistic($booleanID),
        'Boolean statistic was incremented.'
    );
    assertDiagnosticsThrows(
        RuntimeException::class,
        static function () use ($booleanID): void {
            SAEF_SetStatisticTimestamp($booleanID);
        },
        'Boolean timestamp variable was accepted.'
    );
};

$tests['ensures statistic definitions idempotently'] = static function (): void {
    DiagnosticsHelperFakeRuntime::reset();
    $definitions = [
        ['ident' => 'EXECUTIONS', 'name' => 'Executions', 'type' => 1],
        ['ident' => 'LAST_RUN', 'name' => 'Last Run', 'type' => 1, 'profile' => '~UnixTimestamp'],
    ];

    $first = SAEF_EnsureStatisticsVariables(100, $definitions, false);
    $second = SAEF_EnsureStatisticsVariables(100, $definitions, false);

    assertDiagnosticsSame($first, $second, 'Statistic Ensures were not idempotent.');
    assertDiagnosticsSame(['EXECUTIONS', 'LAST_RUN'], array_keys($first), 'Statistic result keys differ.');

    assertDiagnosticsThrows(
        InvalidArgumentException::class,
        static fn(): array => SAEF_EnsureStatisticsVariables(100, [['ident' => 'BROKEN']]),
        'Incomplete statistic definition was accepted.'
    );
};

$tests['keeps and clears only the newest bounded error entries'] = static function (): void {
    DiagnosticsHelperFakeRuntime::reset();
    $variableID = SAEF_EnsureErrorRingBufferVariable(100, 'ERRORS', 'Errors', null, null, false);

    SAEF_AppendErrorRingBufferEntry($variableID, 'first', 2, ['attempt' => 1]);
    SAEF_AppendErrorRingBufferEntry($variableID, 'second', 2);
    $entries = SAEF_AppendErrorRingBufferEntry($variableID, 'third', 2);

    assertDiagnosticsSame(2, count($entries), 'Ring buffer capacity differs.');
    assertDiagnosticsSame('second', $entries[0]['message'], 'Oldest retained entry differs.');
    assertDiagnosticsSame('third', $entries[1]['message'], 'Newest retained entry differs.');
    assertDiagnosticsSame($entries, SAEF_ReadErrorRingBuffer($variableID), 'Ring buffer round-trip differs.');

    SAEF_ClearErrorRingBuffer($variableID);
    assertDiagnosticsSame([], SAEF_ReadErrorRingBuffer($variableID), 'Cleared ring buffer is not empty.');
};

$tests['rejects corrupt or unbounded error history'] = static function (): void {
    DiagnosticsHelperFakeRuntime::reset();
    $variableID = DiagnosticsHelperFakeRuntime::addVariable(3, '{invalid');

    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): array => SAEF_ReadErrorRingBuffer($variableID),
        'Invalid ring buffer JSON was accepted.'
    );

    DiagnosticsHelperFakeRuntime::$values[$variableID] = '{"entry":{"timestamp":1,"message":"x","context":[]}}';
    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): array => SAEF_ReadErrorRingBuffer($variableID),
        'Associative ring buffer JSON was accepted.'
    );

    DiagnosticsHelperFakeRuntime::$values[$variableID] = '[{"message":"missing fields"}]';
    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): array => SAEF_ReadErrorRingBuffer($variableID),
        'Malformed ring buffer entry was accepted.'
    );

    $entry = ['timestamp' => 1, 'message' => 'x', 'context' => []];
    DiagnosticsHelperFakeRuntime::$values[$variableID] = json_encode(
        array_fill(0, SAEF_ERROR_RING_BUFFER_MAX_CAPACITY + 1, $entry),
        JSON_THROW_ON_ERROR
    );
    assertDiagnosticsThrows(
        RuntimeException::class,
        static fn(): array => SAEF_ReadErrorRingBuffer($variableID),
        'Oversized ring buffer was accepted.'
    );

    assertDiagnosticsThrows(
        InvalidArgumentException::class,
        static fn(): array => SAEF_AppendErrorRingBufferEntry($variableID, '', 1),
        'Empty ring buffer message was accepted.'
    );

    DiagnosticsHelperFakeRuntime::$values[$variableID] = '[]';
    assertDiagnosticsThrows(
        InvalidArgumentException::class,
        static fn(): array => SAEF_AppendErrorRingBufferEntry(
            $variableID,
            'too much history',
            SAEF_ERROR_RING_BUFFER_MAX_CAPACITY + 1
        ),
        'Oversized ring buffer capacity was accepted.'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
        fwrite(STDOUT, 'PASS: ' . $name . PHP_EOL);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Diagnostics helper test failed: %s\n%s\n", $name, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("PASS: %d diagnostics helper tests.\n", $passed));
