<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ManualOnPulseOffCore;
use SAEF\CaseStudy\ControlLight\ManualOnPulseOffRuntime;

final class PulseOffFake
{
    /** @var array<int, array{type: int, value: mixed, updated: int, changed: int}> */
    public static array $variables = [];
    /** @var list<array{variableID: int, value: mixed}> */
    public static array $actions = [];
    /** @var list<callable> */
    public static array $sleepSteps = [];
    public static int $sleepCalls = 0;

    public static function reset(float $power): void
    {
        self::$variables = [
            10 => ['type' => 0, 'value' => $power > 1.0, 'updated' => 1, 'changed' => 1],
            20 => ['type' => 2, 'value' => $power, 'updated' => 1, 'changed' => 1],
            21 => ['type' => 0, 'value' => true, 'updated' => 1, 'changed' => 1],
            30 => ['type' => 1, 'value' => 0, 'updated' => 1, 'changed' => 1],
            31 => ['type' => 1, 'value' => 0, 'updated' => 1, 'changed' => 1],
            32 => ['type' => 1, 'value' => 0, 'updated' => 1, 'changed' => 1],
            33 => ['type' => 1, 'value' => 0, 'updated' => 1, 'changed' => 1],
        ];
        self::$actions = [];
        self::$sleepSteps = [];
        self::$sleepCalls = 0;
    }

    public static function set(int $id, mixed $value): void
    {
        $changed = self::$variables[$id]['value'] !== $value;
        self::$variables[$id]['value'] = $value;
        self::$variables[$id]['updated']++;
        if ($changed) {
            self::$variables[$id]['changed']++;
        }
    }
}

function IPS_VariableExists(int $id): bool
{
    return isset(PulseOffFake::$variables[$id]);
}

function IPS_GetVariable(int $id): array
{
    $variable = PulseOffFake::$variables[$id];

    return [
        'VariableType' => $variable['type'],
        'VariableUpdated' => $variable['updated'],
        'VariableChanged' => $variable['changed'],
    ];
}

function GetValue(int $id): mixed
{
    return PulseOffFake::$variables[$id]['value'];
}

function SetValue(int $id, mixed $value): void
{
    PulseOffFake::set($id, $value);
}

function IPS_Sleep(int $milliseconds): void
{
    PulseOffFake::$sleepCalls++;
    $step = array_shift(PulseOffFake::$sleepSteps);
    if ($step !== null) {
        $step();
    }
}

function IPS_SemaphoreEnter(string $name, int $timeout): bool
{
    return true;
}

function IPS_SemaphoreLeave(string $name): bool
{
    return true;
}

function RequestAction(int $variableID, mixed $value): bool
{
    PulseOffFake::$actions[] = ['variableID' => $variableID, 'value' => $value];
    PulseOffFake::set($variableID, $value);
    PulseOffFake::$sleepSteps[] = static fn() => PulseOffFake::set(20, 0.0);
    PulseOffFake::$sleepSteps[] = static fn() => PulseOffFake::set(21, true);

    return true;
}

function IPS_LogMessage(string $sender, string $message): void
{
}

require_once __DIR__ . '/../../case-studies/control-light/candidate/ManualOnPulseOffRuntime.php';

function pulseRuntimeFixture(): array
{
    return [
        'configuration' => ManualOnPulseOffCore::normalizeConfiguration([
            'powerVariableID' => 20,
            'relayVariableID' => 21,
            'observationMilliseconds' => 200,
            'confirmationTimeoutMilliseconds' => 1_000,
            'pollIntervalMilliseconds' => 100,
        ]),
        'resources' => ['stateVariableID' => 10],
        'diagnostics' => [
            'statistics' => [
                'PULSE_COMMANDS' => 30,
                'MANUAL_ACTIVATION_REQUIRED' => 31,
                'CONFIRMATION_TIMEOUTS' => 32,
                'LAST_FEEDBACK' => 33,
            ],
        ],
    ];
}

PulseOffFake::reset(0.0);
$fixture = pulseRuntimeFixture();
$manual = ManualOnPulseOffRuntime::dispatch(
    100,
    true,
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
if (($manual['failureClass'] ?? null) !== 'manual_activation_required' || PulseOffFake::$actions !== []) {
    throw new RuntimeException('Manual-on request was not rejected without a device command.');
}

PulseOffFake::reset(0.0);
$fixture = pulseRuntimeFixture();
$off = ManualOnPulseOffRuntime::dispatch(
    100,
    false,
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
if ($off['status'] !== 'already_confirmed' || PulseOffFake::$actions !== []) {
    throw new RuntimeException('Stable off state was not idempotent.');
}

PulseOffFake::reset(0.0);
$fixture = pulseRuntimeFixture();
PulseOffFake::$sleepSteps[] = static fn() => PulseOffFake::set(20, 12.0);
$race = ManualOnPulseOffRuntime::dispatch(
    100,
    false,
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
if (
    $race['status'] !== 'confirmed'
    || PulseOffFake::$actions !== [['variableID' => 21, 'value' => false]]
    || GetValue(20) !== 0.0
    || GetValue(21) !== true
) {
    throw new RuntimeException('Manual-on/voice-off race was not confirmed by one restored pulse.');
}

PulseOffFake::reset(0.0);
$delayedFixture = pulseRuntimeFixture();
$delayedFixture['configuration']['observationMilliseconds'] = 3_000;
for ($index = 0; $index < 20; $index++) {
    PulseOffFake::$sleepSteps[] = static fn() => null;
}
PulseOffFake::$sleepSteps[] = static fn() => PulseOffFake::set(20, 12.0);
$delayedRace = ManualOnPulseOffRuntime::dispatch(
    100,
    false,
    $delayedFixture['resources'],
    $delayedFixture['configuration'],
    $delayedFixture['diagnostics']
);
if (
    $delayedRace['status'] !== 'confirmed'
    || PulseOffFake::$actions !== [['variableID' => 21, 'value' => false]]
    || PulseOffFake::$sleepCalls !== 23
) {
    throw new RuntimeException(
        'Delayed real-feedback race was not confirmed within the bounded three-second window.'
    );
}

echo "PASS: Manual-on pulse-off runtime contract.\n";
