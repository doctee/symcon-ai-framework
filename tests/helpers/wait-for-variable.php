<?php
declare(strict_types=1);

final class WaitForVariableFakeRuntime
{
    public static mixed $value = false;
    public static int $changed = 100;
    public static int $updated = 100;
    public static int $sleepCalls = 0;
    public static int $getVariableCalls = 0;
    public static int $getValueCalls = 0;

    /** @var list<int> */
    public static array $sleepDurations = [];

    /** @var array<int, callable(): void> */
    public static array $afterSleep = [];

    public static function reset(mixed $value = false): void
    {
        self::$value = $value;
        self::$changed = 100;
        self::$updated = 100;
        self::$sleepCalls = 0;
        self::$getVariableCalls = 0;
        self::$getValueCalls = 0;
        self::$sleepDurations = [];
        self::$afterSleep = [];
    }
}

function IPS_VariableExists(int $id): bool
{
    return $id === 200;
}

/** @return array{VariableChanged: int, VariableUpdated: int} */
function IPS_GetVariable(int $id): array
{
    if ($id !== 200) {
        throw new RuntimeException('Unexpected variable ID.');
    }
    WaitForVariableFakeRuntime::$getVariableCalls++;

    return [
        'VariableChanged' => WaitForVariableFakeRuntime::$changed,
        'VariableUpdated' => WaitForVariableFakeRuntime::$updated,
    ];
}

function GetValue(int $id): mixed
{
    if ($id !== 200) {
        throw new RuntimeException('Unexpected variable ID.');
    }
    WaitForVariableFakeRuntime::$getValueCalls++;

    return WaitForVariableFakeRuntime::$value;
}

function IPS_Sleep(int $milliseconds): void
{
    if ($milliseconds <= 0) {
        throw new RuntimeException('Sleep interval must be positive.');
    }
    WaitForVariableFakeRuntime::$sleepCalls++;
    WaitForVariableFakeRuntime::$sleepDurations[] = $milliseconds;
    $callback = WaitForVariableFakeRuntime::$afterSleep[WaitForVariableFakeRuntime::$sleepCalls] ?? null;
    if ($callback !== null) {
        $callback();
    }
}

require_once __DIR__ . '/../../helpers/variable/WaitForVariable.php';

function assertWaitSame(mixed $expected, mixed $actual, string $message): void
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

$tests = [];

$tests['detects predicate transition with unchanged second timestamp'] = static function (): void {
    WaitForVariableFakeRuntime::reset(false);
    WaitForVariableFakeRuntime::$afterSleep[1] = static function (): void {
        WaitForVariableFakeRuntime::$value = true;
    };

    $result = SAEF_WaitForVariable(
        200,
        500,
        100,
        null,
        SAEF_WAIT_UPDATED,
        0,
        static fn(mixed $value): bool => $value === true
    );

    assertWaitSame(true, $result, 'Same-second predicate transition was missed.');
    assertWaitSame(1, WaitForVariableFakeRuntime::$sleepCalls, 'Unexpected poll count.');
    assertWaitSame(2, WaitForVariableFakeRuntime::$getVariableCalls, 'Unexpected metadata read count.');
    assertWaitSame(2, WaitForVariableFakeRuntime::$getValueCalls, 'Value must be read once at baseline and per poll.');
};

$tests['detects expected-value transition with unchanged second timestamp'] = static function (): void {
    WaitForVariableFakeRuntime::reset(0);
    WaitForVariableFakeRuntime::$afterSleep[2] = static function (): void {
        WaitForVariableFakeRuntime::$value = 42;
    };

    $result = SAEF_WaitForVariable(200, 500, 100, 42, SAEF_WAIT_UPDATED);

    assertWaitSame(true, $result, 'Same-second expected value transition was missed.');
    assertWaitSame(2, WaitForVariableFakeRuntime::$sleepCalls, 'Unexpected poll count.');
    assertWaitSame(3, WaitForVariableFakeRuntime::$getValueCalls, 'More than one value read occurred per poll.');
};

$tests['does not accept a pre-existing matching value without evidence'] = static function (): void {
    WaitForVariableFakeRuntime::reset(true);

    $result = SAEF_WaitForVariable(
        200,
        300,
        100,
        true,
        SAEF_WAIT_UPDATED
    );

    assertWaitSame(false, $result, 'Pre-existing value was treated as new feedback.');
    assertWaitSame(3, WaitForVariableFakeRuntime::$sleepCalls, 'Timeout poll count differs.');
    assertWaitSame(4, WaitForVariableFakeRuntime::$getValueCalls, 'Conditioned wait read count differs.');
};

$tests['detects transition after timestamp advanced with wrong value'] = static function (): void {
    WaitForVariableFakeRuntime::reset(false);
    WaitForVariableFakeRuntime::$afterSleep[1] = static function (): void {
        WaitForVariableFakeRuntime::$updated = 101;
    };
    WaitForVariableFakeRuntime::$afterSleep[2] = static function (): void {
        WaitForVariableFakeRuntime::$value = true;
    };

    $result = SAEF_WaitForVariable(
        200,
        500,
        100,
        null,
        SAEF_WAIT_UPDATED,
        0,
        static fn(mixed $value): bool => $value === true
    );

    assertWaitSame(true, $result, 'Transition after non-matching update was missed.');
    assertWaitSame(2, WaitForVariableFakeRuntime::$sleepCalls, 'Unexpected poll count.');
};

$tests['keeps timestamp-only polling free of value reads'] = static function (): void {
    WaitForVariableFakeRuntime::reset(false);
    WaitForVariableFakeRuntime::$afterSleep[2] = static function (): void {
        WaitForVariableFakeRuntime::$changed = 101;
    };

    $result = SAEF_WaitForVariable(200, 500, 100, null, SAEF_WAIT_CHANGED);

    assertWaitSame(true, $result, 'Timestamp-only change was missed.');
    assertWaitSame(0, WaitForVariableFakeRuntime::$getValueCalls, 'Timestamp-only wait read the value.');
    assertWaitSame(3, WaitForVariableFakeRuntime::$getVariableCalls, 'Timestamp-only metadata reads differ.');
};

$tests['times out when the value transition occurs after the bound'] = static function (): void {
    WaitForVariableFakeRuntime::reset(false);
    WaitForVariableFakeRuntime::$afterSleep[4] = static function (): void {
        WaitForVariableFakeRuntime::$value = true;
    };

    $result = SAEF_WaitForVariable(
        200,
        300,
        100,
        null,
        SAEF_WAIT_UPDATED,
        0,
        static fn(mixed $value): bool => $value === true
    );

    assertWaitSame(false, $result, 'Transition outside the timeout was accepted.');
    assertWaitSame(3, WaitForVariableFakeRuntime::$sleepCalls, 'Bounded timeout poll count differs.');
    assertWaitSame(4, WaitForVariableFakeRuntime::$getValueCalls, 'Bounded timeout value reads differ.');
};

$tests['truncates the final polling interval to the timeout budget'] = static function (): void {
    WaitForVariableFakeRuntime::reset(false);

    $result = SAEF_WaitForVariable(200, 250, 100, null, SAEF_WAIT_UPDATED);

    assertWaitSame(false, $result, 'Timestamp-only wait unexpectedly matched.');
    assertWaitSame([100, 100, 50], WaitForVariableFakeRuntime::$sleepDurations, 'Sleep budget was exceeded.');
};

$tests['retains lookback confirmation without polling'] = static function (): void {
    WaitForVariableFakeRuntime::reset(true);
    WaitForVariableFakeRuntime::$updated = time();

    $result = SAEF_WaitForVariable(200, 500, 100, true, SAEF_WAIT_UPDATED, 100);

    assertWaitSame(true, $result, 'Valid lookback confirmation was rejected.');
    assertWaitSame(0, WaitForVariableFakeRuntime::$sleepCalls, 'Lookback confirmation polled.');
    assertWaitSame(1, WaitForVariableFakeRuntime::$getValueCalls, 'Lookback read value more than once.');
};

$tests['rejects metadata outside the rounded lookback window'] = static function (): void {
    WaitForVariableFakeRuntime::reset(true);
    WaitForVariableFakeRuntime::$updated = time() - 2;

    $result = SAEF_WaitForVariable(200, 0, 100, true, SAEF_WAIT_UPDATED, 1000);

    assertWaitSame(false, $result, 'Lookback accepted an undocumented extra second.');
    assertWaitSame(0, WaitForVariableFakeRuntime::$sleepCalls, 'Zero-timeout lookback polled.');
};

foreach ($tests as $name => $test) {
    $test();
    fwrite(STDOUT, 'PASS: ' . $name . PHP_EOL);
}

fwrite(STDOUT, 'PASS: ' . count($tests) . " WaitForVariable tests.\n");
