<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCore;
use SAEF\CaseStudy\ControlLight\ControlLightCommandException;
use SAEF\CaseStudy\ControlLight\ControlLightRuntime;

final class ControlLightFakeRuntime
{
    /** @var array<int, mixed> */
    public static array $values = [];
    /** @var array<int, array<string, int>> */
    public static array $variables = [];
    /** @var list<array{variableID: int, value: mixed}> */
    public static array $actions = [];
    /** @var list<string> */
    public static array $semaphoreEnters = [];
    /** @var list<string> */
    public static array $semaphoreLeaves = [];
    /** @var list<string> */
    public static array $logs = [];
    public static bool $semaphoreAvailable = true;
    public static bool $requestActionFails = false;
    public static bool $requestActionReturnsFalse = false;
    public static string $feedbackMode = 'immediate';
    public static ?array $pendingFeedback = null;
    public static ?string $normalizedColorFeedback = null;
    public static ?int $normalizedTemperatureFeedback = null;
    public static bool $colorImplicitPowerOn = false;
    public static ?Closure $actionHook = null;
    /** @var list<int> */
    public static array $sleeps = [];

    public static function reset(): void
    {
        self::$values = [];
        self::$variables = [];
        self::$actions = [];
        self::$semaphoreEnters = [];
        self::$semaphoreLeaves = [];
        self::$logs = [];
        self::$semaphoreAvailable = true;
        self::$requestActionFails = false;
        self::$requestActionReturnsFalse = false;
        self::$feedbackMode = 'immediate';
        self::$pendingFeedback = null;
        self::$normalizedColorFeedback = null;
        self::$normalizedTemperatureFeedback = null;
        self::$colorImplicitPowerOn = false;
        self::$actionHook = null;
        self::$sleeps = [];
    }

    public static function variable(int $id, int $type, mixed $value, bool $action = false): void
    {
        self::$values[$id] = $value;
        self::$variables[$id] = [
            'VariableType' => $type,
            'VariableAction' => $action ? 1 : 0,
            'VariableCustomAction' => 0,
            'VariableChanged' => time(),
            'VariableUpdated' => time(),
        ];
    }

    public static function applyPendingFeedback(): void
    {
        if (self::$pendingFeedback === null) {
            return;
        }
        $variableID = self::$pendingFeedback['variableID'];
        $value = self::$pendingFeedback['value'];
        self::$values[$variableID] = $value;
        self::$variables[$variableID]['VariableUpdated']++;
        if (self::$values[$variableID] !== $value) {
            self::$variables[$variableID]['VariableChanged']++;
        }
        self::$pendingFeedback = null;
    }
}

function IPS_VariableExists(int $variableID): bool
{
    return isset(ControlLightFakeRuntime::$variables[$variableID]);
}

/** @return array<string, int> */
function IPS_GetVariable(int $variableID): array
{
    if (!IPS_VariableExists($variableID)) {
        throw new RuntimeException('Fake variable does not exist.');
    }
    return ControlLightFakeRuntime::$variables[$variableID];
}

function GetValue(int $variableID): mixed
{
    return ControlLightFakeRuntime::$values[$variableID];
}

function SetValue(int $variableID, mixed $value): void
{
    $previous = ControlLightFakeRuntime::$values[$variableID];
    ControlLightFakeRuntime::$values[$variableID] = $value;
    ControlLightFakeRuntime::$variables[$variableID]['VariableUpdated']++;
    if ($previous !== $value) {
        ControlLightFakeRuntime::$variables[$variableID]['VariableChanged']++;
    }
}

function RequestAction(int $variableID, mixed $value): bool
{
    ControlLightFakeRuntime::$actions[] = ['variableID' => $variableID, 'value' => $value];
    if (ControlLightFakeRuntime::$requestActionFails) {
        throw new RuntimeException('Fake action failure.');
    }
    if (ControlLightFakeRuntime::$requestActionReturnsFalse) {
        return false;
    }
    if (ControlLightFakeRuntime::$actionHook !== null) {
        return (ControlLightFakeRuntime::$actionHook)($variableID, $value);
    }
    if ($variableID === 23 && ControlLightFakeRuntime::$normalizedTemperatureFeedback !== null) {
        SetValue($variableID, ControlLightFakeRuntime::$normalizedTemperatureFeedback);
        return true;
    }
    if ($variableID === 23 && ControlLightFakeRuntime::$normalizedColorFeedback !== null) {
        SetValue($variableID, ControlLightFakeRuntime::$normalizedColorFeedback);
        if (ControlLightFakeRuntime::$colorImplicitPowerOn) {
            SetValue(20, true);
        }
        return true;
    }
    if (ControlLightFakeRuntime::$feedbackMode === 'immediate') {
        SetValue($variableID, $value);
    } elseif (ControlLightFakeRuntime::$feedbackMode === 'delayed') {
        ControlLightFakeRuntime::$pendingFeedback = ['variableID' => $variableID, 'value' => $value];
    }
    return true;
}

/** @return array<string, mixed> */
function controlLightTemperatureFixture(): array
{
    ControlLightFakeRuntime::reset();
    ControlLightFakeRuntime::variable(10, 0, true);
    ControlLightFakeRuntime::variable(11, 1, 100);
    ControlLightFakeRuntime::variable(12, 1, 4000);
    ControlLightFakeRuntime::variable(20, 0, true, true);
    ControlLightFakeRuntime::variable(21, 1, 100, true);
    ControlLightFakeRuntime::variable(22, 0, true);
    ControlLightFakeRuntime::variable(23, 1, 4000, true);
    ControlLightFakeRuntime::variable(30, 1, 0);
    ControlLightFakeRuntime::variable(31, 1, 0);
    ControlLightFakeRuntime::variable(32, 1, 0);
    ControlLightFakeRuntime::variable(33, 1, 0);

    return [
        'configuration' => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'identColor' => '',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'confirmation' => ['timeoutMilliseconds' => 100, 'pollIntervalMilliseconds' => 50],
            'semaphore' => ['timeoutMilliseconds' => 100],
        ]),
        'resources' => [
            'localVariableIDs' => ['state' => 10, 'brightness' => 11, 'colorTemperature' => 12],
            'targetVariableIDs' => ['state' => 20, 'brightness' => 21, 'colorTemperature' => 23],
            'availabilityVariableID' => 22,
            'externalTriggers' => [],
        ],
        'diagnostics' => [
            'statisticIDs' => [
                'COMMANDS' => 30,
                'CONFIRMATION_TIMEOUTS' => 31,
                'LAST_FEEDBACK' => 32,
                'ERRORS' => 33,
            ],
        ],
    ];
}

/** @return array<string, mixed> */
function controlLightColorTransitionFixture(string $mode = 'target-turns-on'): array
{
    ControlLightFakeRuntime::reset();
    ControlLightFakeRuntime::variable(10, 0, false);
    ControlLightFakeRuntime::variable(11, 1, 100);
    ControlLightFakeRuntime::variable(12, 1, 16749095);
    ControlLightFakeRuntime::variable(20, 0, false, true);
    ControlLightFakeRuntime::variable(21, 1, 255, true);
    ControlLightFakeRuntime::variable(23, 3, '[29.79,84.553]', true);
    ControlLightFakeRuntime::variable(30, 1, 0);
    ControlLightFakeRuntime::variable(31, 1, 0);
    ControlLightFakeRuntime::variable(32, 1, 0);
    ControlLightFakeRuntime::variable(33, 1, 0);

    return [
        'configuration' => ControlLightCore::normalizeConfiguration([
            'preset' => 'MATTER',
            'identTemp' => '',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'confirmation' => ['timeoutMilliseconds' => 100, 'pollIntervalMilliseconds' => 50],
            'semaphore' => ['timeoutMilliseconds' => 100],
            'colorOffStateTransition' => [
                'mode' => $mode,
                'hueToleranceDegrees' => 2.0,
                'saturationTolerancePercentagePoints' => 0.5,
            ],
        ]),
        'resources' => [
            'localVariableIDs' => ['state' => 10, 'brightness' => 11, 'color' => 12],
            'targetVariableIDs' => ['state' => 20, 'brightness' => 21, 'color' => 23],
            'externalTriggers' => [],
        ],
        'diagnostics' => [
            'statisticIDs' => [
                'COMMANDS' => 30,
                'CONFIRMATION_TIMEOUTS' => 31,
                'LAST_FEEDBACK' => 32,
                'ERRORS' => 33,
            ],
        ],
    ];
}

function IPS_Sleep(int $milliseconds): void
{
    ControlLightFakeRuntime::$sleeps[] = $milliseconds;
    if (ControlLightFakeRuntime::$feedbackMode === 'delayed') {
        ControlLightFakeRuntime::applyPendingFeedback();
    }
}

function IPS_SemaphoreEnter(string $name, int $milliseconds): bool
{
    if (str_starts_with($name, 'SAEF_STATISTIC_')) {
        return true;
    }

    ControlLightFakeRuntime::$semaphoreEnters[] = $name . ':' . (string)$milliseconds;
    return ControlLightFakeRuntime::$semaphoreAvailable;
}

function IPS_SemaphoreLeave(string $name): bool
{
    if (str_starts_with($name, 'SAEF_STATISTIC_')) {
        return true;
    }

    ControlLightFakeRuntime::$semaphoreLeaves[] = $name;
    return true;
}

function IPS_LogMessage(string $sender, string $message): void
{
    ControlLightFakeRuntime::$logs[] = $sender . ': ' . $message;
}

require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightRuntime.php';

function assertControlLightRuntimeSame(mixed $expected, mixed $actual, string $message): void
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

/** @return array<string, mixed> */
function controlLightRuntimeFixture(string $semantics = ControlLightCore::BRIGHTNESS_REPORTED): array
{
    ControlLightFakeRuntime::reset();
    ControlLightFakeRuntime::variable(10, 0, false);
    ControlLightFakeRuntime::variable(11, 1, 0);
    ControlLightFakeRuntime::variable(20, 0, false, true);
    ControlLightFakeRuntime::variable(21, 1, 10, true);
    ControlLightFakeRuntime::variable(22, 0, false);
    ControlLightFakeRuntime::variable(30, 1, 0);
    ControlLightFakeRuntime::variable(31, 1, 0);
    ControlLightFakeRuntime::variable(32, 1, 0);
    ControlLightFakeRuntime::variable(33, 1, 0);

    return [
        'configuration' => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'identTemp' => '',
            'identColor' => '',
            'brightnessSemantics' => $semantics,
            'confirmation' => ['timeoutMilliseconds' => 100, 'pollIntervalMilliseconds' => 50],
            'semaphore' => ['timeoutMilliseconds' => 100],
        ]),
        'resources' => [
            'localVariableIDs' => ['state' => 10, 'brightness' => 11],
            'targetVariableIDs' => ['state' => 20, 'brightness' => 21],
            'availabilityVariableID' => 22,
            'externalTriggers' => [],
        ],
        'diagnostics' => [
            'statisticIDs' => [
                'COMMANDS' => 30,
                'CONFIRMATION_TIMEOUTS' => 31,
                'LAST_FEEDBACK' => 32,
                'ERRORS' => 33,
            ],
        ],
    ];
}

$tests = [];

$tests['direct dim sends exactly one brightness request from off even at retained equality'] = static function (): void {
    foreach (['reported', 'effective'] as $semantics) {
        foreach ([10, 100] as $retained) {
            $fixture = controlLightRuntimeFixture($semantics);
            $fixture['configuration']['brightnessOffStateTransition'] = ['mode' => 'target-turns-on'];
            SetValue(21, $retained);
            ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value): bool {
                SetValue($id, $value);
                SetValue(20, true);
                return true;
            };
            $result = ControlLightRuntime::dispatchTargetAction(
                1000,
                'brightness',
                10,
                $fixture['resources'],
                $fixture['configuration'],
                $fixture['diagnostics']
            );
            assertControlLightRuntimeSame('confirmed', $result['status'], 'Direct dim not confirmed.');
            assertControlLightRuntimeSame([['variableID' => 21, 'value' => 10]], ControlLightFakeRuntime::$actions, 'Separate EIN or missing command.');
            assertControlLightRuntimeSame(true, GetValue(10), 'STATE not confirmed.');
            assertControlLightRuntimeSame(10, GetValue(11), 'Brightness not confirmed.');
            ControlLightRuntime::dispatchTargetAction(
                1000,
                'brightness',
                10,
                $fixture['resources'],
                $fixture['configuration'],
                $fixture['diagnostics']
            );
            assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Repeated direct dim not idempotent.');
        }
    }
};

$tests['direct dim accepts delayed state feedback within the same wait budget'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration']['brightnessOffStateTransition'] = ['mode' => 'target-turns-on'];
    ControlLightFakeRuntime::$feedbackMode = 'delayed';
    ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value): bool {
        SetValue($id, $value);
        ControlLightFakeRuntime::$pendingFeedback = ['variableID' => 20, 'value' => true];
        return true;
    };
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        10,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('confirmed', $result['status'], 'Delayed STATE not confirmed.');
    assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Retried instead of waiting.');
    if (array_sum(ControlLightFakeRuntime::$sleeps) > 100) {
        throw new RuntimeException('Direct dim exceeded one wait budget.');
    }
};

$tests['direct dim fails closed on partial feedback without fallback EIN'] = static function (): void {
    foreach (['state-only', 'brightness-only', 'none'] as $feedback) {
        $fixture = controlLightRuntimeFixture();
        SetValue(21, 100);
        $fixture['configuration']['brightnessOffStateTransition'] = ['mode' => 'target-turns-on'];
        ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value) use ($feedback): bool {
            if ($feedback === 'state-only') {
                SetValue(20, true);
            }
            if ($feedback === 'brightness-only') {
                SetValue($id, $value);
            }
            return true;
        };
        try {
            ControlLightRuntime::dispatchTargetAction(
                1000,
                'brightness',
                10,
                $fixture['resources'],
                $fixture['configuration'],
                $fixture['diagnostics']
            );
            throw new RuntimeException('Partial feedback accepted.');
        } catch (ControlLightCommandException $error) {
            assertControlLightRuntimeSame(1, GetValue(31), 'Missing timeout diagnostic.');
        }
        assertControlLightRuntimeSame([['variableID' => 21, 'value' => 10]], ControlLightFakeRuntime::$actions, 'Unexpected power-on fallback.');
        assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$semaphoreLeaves), 'Lock not released.');
    }
};

$tests['direct dim guards missing state manual-on and group contracts'] = static function (): void {
    foreach (['missing', 'manual', 'group'] as $invalid) {
        $fixture = controlLightRuntimeFixture();
        $fixture['configuration']['brightnessOffStateTransition'] = ['mode' => 'target-turns-on'];
        if ($invalid === 'missing') {
            unset($fixture['resources']['targetVariableIDs']['state']);
        }
        if ($invalid === 'manual') {
            $fixture['configuration']['stateCommandMode'] = 'off-only';
        }
        if ($invalid === 'group') {
            $fixture['configuration']['groupFeedback']['enabled'] = true;
        }
        try {
            ControlLightRuntime::dispatchTargetAction(
                1000,
                'brightness',
                10,
                $fixture['resources'],
                $fixture['configuration'],
                $fixture['diagnostics']
            );
            throw new LogicException('Invalid target accepted.');
        } catch (RuntimeException $error) {
            assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Invalid target received action.');
        }
    }
};

$tests['direct dim preserves zero-off passive feedback and both alarm polarities'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration']['brightnessOffStateTransition'] = ['mode' => 'target-turns-on'];
    (new ReflectionMethod(ControlLightRuntime::class, 'syncAll'))->invoke(null, $fixture['resources'], $fixture['configuration']);
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Passive feedback sent command.');
    SetValue(20, true);
    (new ReflectionMethod(ControlLightRuntime::class, 'dispatchLocalAction'))->invoke(
        null,
        1000,
        'brightness',
        0,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame([['variableID' => 20, 'value' => false]], ControlLightFakeRuntime::$actions, 'Zero did not use STATE off.');
    $check = new ReflectionMethod(ControlLightRuntime::class, 'userMayControl');
    foreach ([true, false] as $polarity) {
        $configuration = $fixture['configuration'];
        $configuration['alarmID'] = 22;
        $configuration['alarmIDIsAlarmActive'] = $polarity;
        SetValue(22, $polarity);
        assertControlLightRuntimeSame(false, $check->invoke(null, $configuration, 'VoiceControl'), 'Alarm bypassed.');
        SetValue(22, !$polarity);
        assertControlLightRuntimeSame(true, $check->invoke(null, $configuration, 'VoiceControl'), 'Disarmed blocked.');
    }
};

$tests['direct dim rejects failed actions and obeys the existing command lock'] = static function (): void {
    foreach (['rejected', 'locked'] as $failure) {
        $fixture = controlLightRuntimeFixture();
        $fixture['configuration']['brightnessOffStateTransition'] = ['mode' => 'target-turns-on'];
        ControlLightFakeRuntime::$requestActionReturnsFalse = $failure === 'rejected';
        ControlLightFakeRuntime::$semaphoreAvailable = $failure !== 'locked';
        try {
            ControlLightRuntime::dispatchTargetAction(
                1000,
                'brightness',
                10,
                $fixture['resources'],
                $fixture['configuration'],
                $fixture['diagnostics']
            );
            throw new LogicException('Failure accepted.');
        } catch (RuntimeException $error) {
            assertControlLightRuntimeSame($failure === 'locked' ? 0 : 1, count(ControlLightFakeRuntime::$actions), 'Unexpected command count.');
        }
    }
};


/** @return array<string, mixed> */
function controlLightExplicitColorFixture(): array
{
    $fixture = controlLightColorTransitionFixture('unchanged');
    ControlLightFakeRuntime::variable(23, 1, 0x3366FF, true);
    SetValue(21, 40);
    $fixture['configuration'] = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'identTemp' => '',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        'colorOffStateTransition' => [
            'mode' => 'power-on-first',
            'hueToleranceDegrees' => 0.0,
            'saturationTolerancePercentagePoints' => 0.0,
        ],
        'confirmation' => ['timeoutMilliseconds' => 100, 'pollIntervalMilliseconds' => 50],
        'semaphore' => ['timeoutMilliseconds' => 100],
    ]);
    return $fixture;
}

$tests['explicit RGB powers on first and preserves brightness under one lock'] = static function (): void {
    foreach ([0x3366FF, 0xFF6633, 0x33FF66] as $rgb) {
        $fixture = controlLightExplicitColorFixture();
        $result = ControlLightRuntime::dispatchTargetAction(1000, 'color', $rgb, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
        $expected = [['variableID' => 20, 'value' => true]];
        if ($rgb !== 0x3366FF) {
            $expected[] = ['variableID' => 23, 'value' => $rgb];
        }
        assertControlLightRuntimeSame($expected, ControlLightFakeRuntime::$actions, 'Power/color order differs.');
        assertControlLightRuntimeSame('confirmed', $result['status'], 'Power transition lost.');
        assertControlLightRuntimeSame(true, GetValue(10), 'Authoritative power missing.');
        assertControlLightRuntimeSame($rgb, GetValue(12), 'Authoritative color missing.');
        assertControlLightRuntimeSame(40, GetValue(21), 'Native brightness changed.');
        assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000:100'], ControlLightFakeRuntime::$semaphoreEnters, 'Expected one shared lock.');
        assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Lock not released.');
        ControlLightFakeRuntime::$actions = [];
        $repeat = ControlLightRuntime::dispatchTargetAction(1000, 'color', $rgb, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
        assertControlLightRuntimeSame('already_confirmed', $repeat['status'], 'Repeat not idempotent.');
        assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Repeat sent device commands.');
    }
};

$tests['explicit RGB on a powered light sends color only'] = static function (): void {
    $fixture = controlLightExplicitColorFixture();
    SetValue(20, true);
    ControlLightRuntime::dispatchTargetAction(1000, 'color', 0xFF6633, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
    assertControlLightRuntimeSame([['variableID' => 23, 'value' => 0xFF6633]], ControlLightFakeRuntime::$actions, 'Redundant power or dim command.');
};

$tests['explicit RGB stops before color when power is unconfirmed'] = static function (): void {
    $fixture = controlLightExplicitColorFixture();
    ControlLightFakeRuntime::$feedbackMode = 'none';
    try {
        ControlLightRuntime::dispatchTargetAction(1000, 'color', 0xFF6633, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
        throw new RuntimeException('Unconfirmed power accepted.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(1, GetValue(31), 'Timeout was not counted once.');
    }
    assertControlLightRuntimeSame([['variableID' => 20, 'value' => true]], ControlLightFakeRuntime::$actions, 'Color sent after failed power confirmation.');
    assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Failure leaked lock.');
};

$tests['explicit RGB uses the remaining shared deadline'] = static function (): void {
    $fixture = controlLightExplicitColorFixture();
    ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value): bool {
        SetValue($id, $value);
        usleep(120000);
        return true;
    };
    try {
        ControlLightRuntime::dispatchTargetAction(1000, 'color', 0xFF6633, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
        throw new RuntimeException('Color received a fresh timeout budget.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(1, GetValue(31), 'Deadline failure missing.');
    }
    assertControlLightRuntimeSame([['variableID' => 20, 'value' => true]], ControlLightFakeRuntime::$actions, 'Color ran after exhausted shared deadline.');
};

$tests['explicit RGB never bypasses manual-on protection'] = static function (): void {
    $fixture = controlLightExplicitColorFixture();
    $fixture['configuration']['stateCommandMode'] = ControlLightCore::STATE_COMMAND_OFF_ONLY;
    try {
        ControlLightRuntime::dispatchTargetAction(1000, 'color', 0xFF6633, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
        throw new RuntimeException('Manual-on protection bypassed.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(ControlLightCommandException::FAILURE_MANUAL_ACTIVATION_REQUIRED, $exception->failureClass(), 'Wrong failure class.');
    }
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Manual-only device switched.');
};

$tests['explicit RGB passive feedback never powers on'] = static function (): void {
    $fixture = controlLightExplicitColorFixture();
    SetValue(23, 0x33FF66);
    (new ReflectionMethod(ControlLightRuntime::class, 'syncAll'))->invoke(null, $fixture['resources'], $fixture['configuration']);
    assertControlLightRuntimeSame(false, GetValue(10), 'Feedback inferred power.');
    assertControlLightRuntimeSame(0x33FF66, GetValue(12), 'Feedback not synchronized.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Passive color sent commands.');
};

$tests['explicit RGB cannot confirm color after power is lost'] = static function (): void {
    $fixture = controlLightExplicitColorFixture();
    ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value): bool {
        SetValue($id, $value);
        if ($id === 23) {
            SetValue(20, false);
        }
        return true;
    };
    try {
        ControlLightRuntime::dispatchTargetAction(1000, 'color', 0xFF6633, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
        throw new RuntimeException('Color without power accepted.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(1, GetValue(31), 'Missing power not diagnosed.');
    }
};

$tests['explicit RGB fails closed without a state target'] = static function (): void {
    $fixture = controlLightExplicitColorFixture();
    unset($fixture['resources']['targetVariableIDs']['state']);
    try {
        ControlLightRuntime::dispatchTargetAction(1000, 'color', 0xFF6633, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
        throw new RuntimeException('Missing target accepted.');
    } catch (RuntimeException $exception) {
        assertControlLightRuntimeSame('Color power-on requires a STATE target.', $exception->getMessage(), 'Unexpected failure.');
    }
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Invalid resources sent commands.');
};

$tests['positive dim powers on even when retained brightness already matches'] = static function (): void {
    foreach ([ControlLightCore::BRIGHTNESS_REPORTED, ControlLightCore::BRIGHTNESS_EFFECTIVE] as $semantics) {
        $fixture = controlLightRuntimeFixture($semantics);
        SetValue(21, 100);
        $result = ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            100,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        assertControlLightRuntimeSame('confirmed', $result['status'], 'Off-state equality was treated as complete.');
        assertControlLightRuntimeSame([['variableID' => 20, 'value' => true]], ControlLightFakeRuntime::$actions, 'Redundant dim command.');
        assertControlLightRuntimeSame(true, GetValue(10), 'Authoritative state not synchronized.');
        assertControlLightRuntimeSame(100, GetValue(11), 'Authoritative brightness differs.');
        assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000:100'], ControlLightFakeRuntime::$semaphoreEnters, 'Nested/released command lock.');
    }
};

$tests['positive dim remains idempotent only when power and brightness match'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    SetValue(20, true);
    SetValue(21, 100);
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        100,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('already_confirmed', $result['status'], 'Idempotent result differs.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Already confirmed light received an action.');
};

$tests['already powered light needs only its changed dim command'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    SetValue(20, true);
    ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        55,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame([['variableID' => 21, 'value' => 55]], ControlLightFakeRuntime::$actions, 'Redundant power-on.');
};

$tests['passive positive brightness never becomes an on command'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    SetValue(21, 100);
    (new ReflectionMethod(ControlLightRuntime::class, 'syncAll'))->invoke(null, $fixture['resources'], $fixture['configuration']);
    assertControlLightRuntimeSame(false, GetValue(10), 'Feedback inferred power from brightness.');
    assertControlLightRuntimeSame(100, GetValue(11), 'Reported retained brightness lost.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Passive feedback switched a device.');
};

$tests['zero dim still delegates to off without changing stored brightness'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    SetValue(20, true);
    SetValue(21, 100);
    (new ReflectionMethod(ControlLightRuntime::class, 'dispatchLocalAction'))->invoke(
        null,
        1000,
        'brightness',
        0,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame([['variableID' => 20, 'value' => false]], ControlLightFakeRuntime::$actions, 'Zero dim did not use off.');
    assertControlLightRuntimeSame(100, GetValue(11), 'Reported retained brightness changed on off.');
};

$tests['off-only light cannot be powered on indirectly by positive dim'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration']['stateCommandMode'] = ControlLightCore::STATE_COMMAND_OFF_ONLY;
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            55,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Manual-on protection was bypassed.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(ControlLightCommandException::FAILURE_MANUAL_ACTIVATION_REQUIRED, $exception->failureClass(), 'Failure class differs.');
    }
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Manual-on light received a command while off.');
    SetValue(20, true);
    ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        55,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame([['variableID' => 21, 'value' => 55]], ControlLightFakeRuntime::$actions, 'Already manually powered light cannot dim.');
};

$tests['missing power confirmation stops before dimming'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$feedbackMode = 'none';
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            55,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Unconfirmed power-on accepted.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(ControlLightCommandException::FAILURE_DEVICE_OFFLINE, $exception->failureClass(), 'Offline diagnosis lost.');
    }
    assertControlLightRuntimeSame([['variableID' => 20, 'value' => true]], ControlLightFakeRuntime::$actions, 'Dimming continued after failed on.');
};

$tests['positive dim confirms state and brightness together after target action'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value): bool {
        SetValue($id, $value);
        if ($id === 21) {
            SetValue(20, false);
        }
        return true;
    };
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            55,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Power drift during dim accepted.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(1, GetValue(31), 'Joint confirmation timeout missing.');
    }
    assertControlLightRuntimeSame(false, GetValue(10), 'Power drift concealed by optimistic state.');
    assertControlLightRuntimeSame(2, count(ControlLightFakeRuntime::$actions), 'Unexpected retry after competing off.');
};

$tests['power and dim consume one shared confirmation budget'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value): bool {
        if ($id === 20) {
            usleep(80 * 1000);
            SetValue($id, $value);
        }
        return true;
    };
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            55,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Missing dim feedback accepted.');
    } catch (ControlLightCommandException $exception) {
        if (array_sum(ControlLightFakeRuntime::$sleeps) > 25) {
            throw new RuntimeException('Dimming reset the total confirmation budget.');
        }
    }
};

$tests['zero wait budget permits immediate positive dim confirmation'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration']['confirmation']['timeoutMilliseconds'] = 0;
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        55,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('confirmed', $result['status'], 'Immediate zero-budget action rejected.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$sleeps, 'Zero-budget action waited.');
};

$tests['positive dim retains Matter scaling and power semantics'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration'] = ControlLightCore::normalizeConfiguration([
        'preset' => 'MATTER', 'identTemp' => '', 'identColor' => '', 'brightnessSemantics' => 'reported',
    ]);
    $expected = ControlLightCore::localToTarget('brightness', 55, $fixture['configuration']);
    ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        55,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame(
        [['variableID' => 20, 'value' => true], ['variableID' => 21, 'value' => $expected]],
        ControlLightFakeRuntime::$actions,
        'Matter command conversion differs.'
    );
};

$tests['confirms immediate authoritative feedback'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        55,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('confirmed', $result['status'], 'Immediate result differs.');
    assertControlLightRuntimeSame(55, GetValue(11), 'Local feedback differs.');
    assertControlLightRuntimeSame(
        [['variableID' => 20, 'value' => true], ['variableID' => 21, 'value' => 55]],
        ControlLightFakeRuntime::$actions,
        'Action calls differ.'
    );
    assertControlLightRuntimeSame(true, GetValue(10), 'Positive dim did not power on.');
    assertControlLightRuntimeSame(2, GetValue(30), 'Command statistic differs.');
    assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Semaphore was not released.');
};

$tests['confirms delayed feedback through bounded waiting'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$feedbackMode = 'delayed';
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        60,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('confirmed', $result['status'], 'Delayed result differs.');
    assertControlLightRuntimeSame(60, GetValue(11), 'Delayed local feedback differs.');
};

$tests['confirms mired-quantized Kelvin feedback in the runtime path'] = static function (): void {
    $fixture = controlLightTemperatureFixture();
    ControlLightFakeRuntime::$normalizedTemperatureFeedback = 3906;

    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'colorTemperature',
        3900,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightRuntimeSame('confirmed', $result['status'], 'Quantized Kelvin result differs.');
    assertControlLightRuntimeSame(3906, GetValue(12), 'Reported Kelvin facade value differs.');
    assertControlLightRuntimeSame(
        [['variableID' => 23, 'value' => 3900]],
        ControlLightFakeRuntime::$actions,
        'Quantized Kelvin action differs.'
    );
    assertControlLightRuntimeSame(1, GetValue(30), 'Quantized Kelvin command statistic differs.');
    assertControlLightRuntimeSame(0, GetValue(31), 'Quantized Kelvin caused a false timeout.');
};

$tests['confirms truncated mired request feedback in the runtime path'] = static function (): void {
    $fixture = controlLightTemperatureFixture();
    ControlLightFakeRuntime::$normalizedTemperatureFeedback = 6535;

    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'colorTemperature',
        6500,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightRuntimeSame('confirmed', $result['status'], 'Truncated Kelvin result differs.');
    assertControlLightRuntimeSame(6535, GetValue(12), 'Truncated Kelvin facade value differs.');
    assertControlLightRuntimeSame(
        [['variableID' => 23, 'value' => 6500]],
        ControlLightFakeRuntime::$actions,
        'Truncated Kelvin action differs.'
    );
    assertControlLightRuntimeSame(1, GetValue(30), 'Truncated Kelvin command statistic differs.');
    assertControlLightRuntimeSame(0, GetValue(31), 'Truncated Kelvin caused a false timeout.');
};

$tests['dispatches during a stale offline indication and accepts immediate feedback'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    assertControlLightRuntimeSame(false, GetValue(22), 'Availability precondition differs.');

    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightRuntimeSame('confirmed', $result['status'], 'Hard-on command was not confirmed.');
    assertControlLightRuntimeSame(
        [['variableID' => 20, 'value' => true]],
        ControlLightFakeRuntime::$actions,
        'Stale offline indication blocked or duplicated the command.'
    );
};

$tests['confirms one-command off-state color transition with authoritative power-on'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    ControlLightFakeRuntime::$normalizedColorFeedback = '[28.346,84.646]';
    ControlLightFakeRuntime::$colorImplicitPowerOn = true;

    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'color',
        16749095,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightRuntimeSame('confirmed', $result['status'], 'Off-state color result differs.');
    assertControlLightRuntimeSame(
        [['variableID' => 23, 'value' => '[29.722,84.706]']],
        ControlLightFakeRuntime::$actions,
        'Off-state color transition issued an unexpected command sequence.'
    );
    assertControlLightRuntimeSame(true, GetValue(10), 'Authoritative powered-on state was not synchronized.');
    assertControlLightRuntimeSame(100, GetValue(11), 'Color transition changed independent brightness.');
    assertControlLightRuntimeSame(1, GetValue(30), 'One-command transition statistic differs.');
};

$tests['requires power-on feedback for off-state color transition'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    ControlLightFakeRuntime::$normalizedColorFeedback = '[28.346,84.646]';

    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'color',
            16749095,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Missing power-on feedback was accepted.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(
            ControlLightCommandException::FAILURE_FEEDBACK_TIMEOUT,
            $exception->failureClass(),
            'Missing power-on feedback failure class differs.'
        );
    }
    assertControlLightRuntimeSame(false, GetValue(10), 'Unconfirmed power state changed optimistically.');
    assertControlLightRuntimeSame(1, GetValue(31), 'Transition timeout statistic differs.');
};

$tests['keeps narrow color tolerance while target is already on'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    SetValue(20, true);
    SetValue(10, true);
    SetValue(23, '[100,100]');
    ControlLightFakeRuntime::$normalizedColorFeedback = '[28.346,84.646]';

    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'color',
            16749095,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('On-state color used the relaxed transition tolerance.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(
            ControlLightCommandException::FAILURE_FEEDBACK_TIMEOUT,
            $exception->failureClass(),
            'On-state mismatch failure class differs.'
        );
    }
};

$tests['reports timeout without optimistic local state'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$feedbackMode = 'none';
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            70,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Timeout was not reported.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'confirmation timed out')) {
            throw $exception;
        }
        if (!$exception instanceof ControlLightCommandException) {
            throw new RuntimeException('Timeout did not use the classified command exception.');
        }
        assertControlLightRuntimeSame(
            ControlLightCommandException::FAILURE_DEVICE_OFFLINE,
            $exception->failureClass(),
            'Offline timeout failure class differs.'
        );
    }
    assertControlLightRuntimeSame(10, GetValue(11), 'Unconfirmed local brightness changed.');
    assertControlLightRuntimeSame(1, GetValue(31), 'Timeout statistic differs.');
    assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Timeout leaked semaphore.');
};

$tests['keeps an available target timeout distinct from device offline'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$feedbackMode = 'none';
    SetValue(22, true);

    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            70,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Available-target timeout was not reported.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(
            ControlLightCommandException::FAILURE_FEEDBACK_TIMEOUT,
            $exception->failureClass(),
            'Available-target timeout failure class differs.'
        );
    }
    assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Available target command count differs.');
};

$tests['treats missing optional availability as an unclassified feedback timeout'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$feedbackMode = 'none';
    unset($fixture['resources']['availabilityVariableID']);

    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'brightness',
            70,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Missing-availability timeout was not reported.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(
            ControlLightCommandException::FAILURE_FEEDBACK_TIMEOUT,
            $exception->failureClass(),
            'Missing-availability timeout failure class differs.'
        );
    }
};

$tests['rejects parallel execution before device action'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$semaphoreAvailable = false;
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'state',
            true,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Semaphore timeout was not reported.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'semaphore timed out')) {
            throw $exception;
        }
    }
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Rejected parallel execution caused an action.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$semaphoreLeaves, 'Unacquired semaphore was released.');
};

$tests['releases semaphore after action failure'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$requestActionFails = true;
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'state',
            true,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Action failure was not reported.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Fake action failure.') {
            throw $exception;
        }
    }
    assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Action failure leaked semaphore.');
};

$tests['rejects a false action result'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::$requestActionReturnsFalse = true;
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'state',
            true,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Rejected action was accepted.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'rejected the requested value')) {
            throw $exception;
        }
    }
    assertControlLightRuntimeSame(0, GetValue(30), 'Rejected action was counted as a command.');
    assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Rejected action leaked semaphore.');
};

$tests['classifies unsupported remote on without issuing a target action'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration']['stateCommandMode'] = ControlLightCore::STATE_COMMAND_OFF_ONLY;

    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'state',
            true,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Unsupported remote on was accepted.');
    } catch (ControlLightCommandException $exception) {
        assertControlLightRuntimeSame(
            ControlLightCommandException::FAILURE_MANUAL_ACTIVATION_REQUIRED,
            $exception->failureClass(),
            'Manual activation failure class differs.'
        );
        assertControlLightRuntimeSame(
            ['requestedState' => true],
            $exception->details(),
            'Manual activation diagnostics differ.'
        );
    }

    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Remote on issued a target action.');
    assertControlLightRuntimeSame(0, GetValue(30), 'Rejected remote on was counted as a command.');
    assertControlLightRuntimeSame(
        ['SAEF_CONTROL_LIGHT_1000'],
        ControlLightFakeRuntime::$semaphoreLeaves,
        'Rejected remote on leaked the semaphore.'
    );
};

$tests['keeps off-only state idempotent when authoritative feedback is already on'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration']['stateCommandMode'] = ControlLightCore::STATE_COMMAND_OFF_ONLY;
    SetValue(20, true);

    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightRuntimeSame('already_confirmed', $result['status'], 'Confirmed manual-on state differs.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Confirmed manual-on issued an action.');
};

$tests['delegates off-only off commands even while facade feedback is still false'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $fixture['configuration']['stateCommandMode'] = ControlLightCore::STATE_COMMAND_OFF_ONLY;

    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        false,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightRuntimeSame('confirmed', $result['status'], 'Delegated off result differs.');
    assertControlLightRuntimeSame(
        [['variableID' => 20, 'value' => false]],
        ControlLightFakeRuntime::$actions,
        'Off-only off command was not delegated to the target adapter.'
    );
    assertControlLightRuntimeSame(1, GetValue(30), 'Delegated off command statistic differs.');
};

$tests['blocks voice control under the inverse alarm contract while retaining Action access'] = static function (): void {
    controlLightRuntimeFixture();
    $configuration = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'identTemp' => '',
        'identColor' => '',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        'alarmID' => 22,
        'alarmIDIsAlarmActive' => false,
    ]);
    $userMayControl = new ReflectionMethod(ControlLightRuntime::class, 'userMayControl');

    assertControlLightRuntimeSame(
        false,
        $userMayControl->invoke(null, $configuration, 'VoiceControl'),
        'Active inverse alarm contract permitted voice control.'
    );
    assertControlLightRuntimeSame(
        false,
        $userMayControl->invoke(null, $configuration, 'WebFront'),
        'Active inverse alarm contract permitted WebFront control.'
    );
    assertControlLightRuntimeSame(
        false,
        $userMayControl->invoke(null, $configuration, 'External'),
        'Active inverse alarm contract permitted an alarm-aware external trigger.'
    );
    assertControlLightRuntimeSame(
        true,
        $userMayControl->invoke(null, $configuration, 'Action'),
        'Action sender must retain explicit bypass access.'
    );

    SetValue(22, true);
    assertControlLightRuntimeSame(
        true,
        $userMayControl->invoke(null, $configuration, 'VoiceControl'),
        'Inactive inverse alarm contract blocked voice control.'
    );
};

$tests['maps independent external on and off update triggers deterministically'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    $on = [
        'resolvedVariableID' => 40,
        'action' => 'on',
        'invert' => false,
    ];
    $off = [
        'resolvedVariableID' => 41,
        'action' => 'off',
        'invert' => false,
    ];
    $resources = $fixture['resources'];
    $resources['externalTriggers'] = [$on, $off];
    $findTrigger = new ReflectionMethod(ControlLightRuntime::class, 'externalTriggerForVariable');
    $triggerState = new ReflectionMethod(ControlLightRuntime::class, 'externalTriggerState');

    assertControlLightRuntimeSame(
        $on,
        $findTrigger->invoke(null, 40, $resources),
        'External on trigger mapping differs.'
    );
    assertControlLightRuntimeSame(
        $off,
        $findTrigger->invoke(null, 41, $resources),
        'External off trigger mapping differs.'
    );
    assertControlLightRuntimeSame(
        null,
        $findTrigger->invoke(null, 42, $resources),
        'Unknown external trigger was accepted.'
    );
    assertControlLightRuntimeSame(
        true,
        $triggerState->invoke(null, $on, true, $resources),
        'External on trigger state differs.'
    );
    assertControlLightRuntimeSame(
        false,
        $triggerState->invoke(null, $off, true, $resources),
        'External off trigger state differs.'
    );
};

$tests['applies effective brightness when confirmed state turns off'] = static function (): void {
    $fixture = controlLightRuntimeFixture(ControlLightCore::BRIGHTNESS_EFFECTIVE);
    SetValue(20, true);
    SetValue(21, 42);
    SetValue(10, true);
    SetValue(11, 42);
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        false,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('confirmed', $result['status'], 'Off command result differs.');
    assertControlLightRuntimeSame(false, GetValue(10), 'Local state differs.');
    assertControlLightRuntimeSame(0, GetValue(11), 'Effective brightness did not become zero.');
    assertControlLightRuntimeSame(42, GetValue(21), 'Retained target brightness was modified.');
};

$tests['keeps diagnostic failures secondary and error history generic'] = static function (): void {
    $fixture = controlLightRuntimeFixture();
    ControlLightFakeRuntime::variable(34, 3, '');
    $recordFailure = new ReflectionMethod(ControlLightRuntime::class, 'recordFailure');
    $original = new RuntimeException('Sensitive runtime detail.');
    $recordFailure->invoke(
        null,
        [
            'statisticIDs' => ['ERRORS' => 33],
            'errorRingBufferID' => 34,
        ],
        $original,
        'runtime'
    );

    $history = SAEF_ReadErrorRingBuffer(34);
    assertControlLightRuntimeSame(1, GetValue(33), 'Runtime failure statistic differs.');
    assertControlLightRuntimeSame('ControlLight failure during runtime.', $history[0]['message'], 'Error message differs.');
    if (str_contains((string)$history[0]['message'], $original->getMessage())) {
        throw new RuntimeException('Error history retained the original runtime detail.');
    }

    $recordFailure->invoke(
        null,
        [
            'statisticIDs' => ['ERRORS' => 33],
            'errorRingBufferID' => 34,
        ],
        new ControlLightCommandException(ControlLightCommandException::FAILURE_DEVICE_OFFLINE, 'state'),
        'runtime'
    );
    $history = SAEF_ReadErrorRingBuffer(34);
    assertControlLightRuntimeSame('device_offline', $history[1]['context']['failureClass'], 'Failure class context differs.');
    assertControlLightRuntimeSame('state', $history[1]['context']['capability'], 'Failure capability context differs.');

    $recordFailure->invoke(
        null,
        [
            'statisticIDs' => ['ERRORS' => 9998],
            'errorRingBufferID' => 9999,
        ],
        $original,
        'runtime'
    );
    assertControlLightRuntimeSame(2, count(ControlLightFakeRuntime::$logs), 'Secondary failures were not logged.');
};

$tests['converts classified command failures at the Symcon action boundary'] = static function (): void {
    $resultMethod = new ReflectionMethod(ControlLightRuntime::class, 'commandFailureResult');
    $result = $resultMethod->invoke(
        null,
        ['SENDER' => 'Action'],
        new ControlLightCommandException(
            ControlLightCommandException::FAILURE_DEVICE_OFFLINE,
            'state'
        )
    );

    assertControlLightRuntimeSame(
        [
            'status' => 'command_failed',
            'failureClass' => 'device_offline',
            'capability' => 'state',
            'sender' => 'Action',
        ],
        $result,
        'Classified action-boundary result differs.'
    );
};

foreach (['', '   ', 'null', null] as $missingIndex => $missingColor) {
    $tests['missing HA color does not block state or dim ' . $missingIndex] = static function () use ($missingColor): void {
        $fixture = controlLightColorTransitionFixture();
        SetValue(23, $missingColor);
        foreach ([['state', true], ['brightness', 40], ['state', false]] as [$capability, $value]) {
            $result = ControlLightRuntime::dispatchTargetAction(
                1000,
                $capability,
                $value,
                $fixture['resources'],
                $fixture['configuration'],
                $fixture['diagnostics']
            );
            assertControlLightRuntimeSame('confirmed', $result['status'], 'Independent command failed.');
        }
        assertControlLightRuntimeSame(16749095, GetValue(12), 'Missing color overwrote retained facade.');
        assertControlLightRuntimeSame(false, GetValue(10), 'Off feedback not synchronized.');
        assertControlLightRuntimeSame([], ControlLightFakeRuntime::$sleeps, 'Missing side attribute added latency.');
        assertControlLightRuntimeSame(0, GetValue(31), 'Independent commands timed out.');
    };
}

$tests['missing color feedback is pending then recovers without commands'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    SetValue(23, '');
    $sync = new ReflectionMethod(ControlLightRuntime::class, 'syncCapability');
    assertControlLightRuntimeSame(false, $sync->invoke(null, 'color', $fixture['resources'], $fixture['configuration']), 'Absent feedback accepted.');
    SetValue(23, '[120,100]');
    assertControlLightRuntimeSame(true, $sync->invoke(null, 'color', $fixture['resources'], $fixture['configuration']), 'Valid feedback not synchronized.');
    assertControlLightRuntimeSame(0x00FF00, GetValue(12), 'Recovered color differs.');
    assertControlLightRuntimeSame(false, GetValue(10), 'Passive color powered on facade.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Passive color dispatched a command.');
};

$tests['color request waits through absent feedback and confirms once'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    SetValue(23, '');
    ControlLightFakeRuntime::$feedbackMode = 'delayed';
    ControlLightFakeRuntime::$actionHook = static function (int $id, mixed $value): bool {
        SetValue(20, true);
        ControlLightFakeRuntime::$pendingFeedback = ['variableID' => $id, 'value' => $value];
        return true;
    };
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'color',
        0x00FF00,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('confirmed', $result['status'], 'Delayed color failed.');
    assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Color was retried.');
    assertControlLightRuntimeSame(0x00FF00, GetValue(12), 'Color not confirmed.');
    assertControlLightRuntimeSame(100, GetValue(11), 'Color changed brightness.');
    $again = ControlLightRuntime::dispatchTargetAction(
        1000,
        'color',
        0x00FF00,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('already_confirmed', $again['status'], 'Repeated color not idempotent.');
    assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Repeated color dispatched again.');
};

$tests['permanently missing color remains a bounded classified timeout'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    SetValue(23, '');
    ControlLightFakeRuntime::$feedbackMode = 'none';
    try {
        ControlLightRuntime::dispatchTargetAction(
            1000,
            'color',
            0x00FF00,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        throw new RuntimeException('Missing color falsely confirmed.');
    } catch (ControlLightCommandException $error) {
        assertControlLightRuntimeSame(ControlLightCommandException::FAILURE_FEEDBACK_TIMEOUT, $error->failureClass(), 'Missing color classification differs.');
    }
    assertControlLightRuntimeSame(1, GetValue(31), 'Timeout missing from diagnostics.');
    assertControlLightRuntimeSame(16749095, GetValue(12), 'Missing feedback overwrote retained color.');
    assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Timeout caused retries.');
    if (array_sum(ControlLightFakeRuntime::$sleeps) > 100) {
        throw new RuntimeException('Missing feedback exceeded shared timeout.');
    }
    assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Semaphore leaked.');
};

foreach (['broken', '[]', '[400,10]', '{"h":20}'] as $invalidIndex => $invalidColor) {
    $tests['malformed nonempty color remains visible ' . $invalidIndex] = static function () use ($invalidColor): void {
        $fixture = controlLightColorTransitionFixture();
        SetValue(23, $invalidColor);
        try {
            (new ReflectionMethod(ControlLightRuntime::class, 'syncAll'))->invoke(null, $fixture['resources'], $fixture['configuration']);
            throw new RuntimeException('Malformed feedback was hidden.');
        } catch (InvalidArgumentException) {
            assertControlLightRuntimeSame(16749095, GetValue(12), 'Malformed feedback overwrote color.');
        }
    };
}

$tests['integer black remains valid feedback'] = static function (): void {
    $fixture = controlLightColorTransitionFixture('unchanged');
    $fixture['configuration']['colorTargetFormat'] = 'INT_HEX';
    SetValue(23, 0);
    (new ReflectionMethod(ControlLightRuntime::class, 'syncAll'))->invoke(null, $fixture['resources'], $fixture['configuration']);
    assertControlLightRuntimeSame(0, GetValue(12), 'Black mistaken for unavailable.');
};

foreach (['HS_ARRAY_STRING', 'RGB_ARRAY_STRING', 'RGB_OBJECT_STRING'] as $colorFormat) {
    $tests['missing color while on does not stop dispatch ' . $colorFormat] = static function () use ($colorFormat): void {
        $fixture = controlLightColorTransitionFixture('unchanged');
        $fixture['configuration']['colorTargetFormat'] = $colorFormat;
        SetValue(20, true);
        SetValue(23, '');
        $result = ControlLightRuntime::dispatchTargetAction(
            1000,
            'color',
            0x00FF00,
            $fixture['resources'],
            $fixture['configuration'],
            $fixture['diagnostics']
        );
        assertControlLightRuntimeSame('confirmed', $result['status'], 'Color with absent baseline failed.');
        assertControlLightRuntimeSame(0x00FF00, GetValue(12), 'Confirmed color differs.');
        assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Unexpected commands.');
    };
}

$tests['already confirmed state remains idempotent with absent color'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    SetValue(23, '');
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        false,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightRuntimeSame('already_confirmed', $result['status'], 'OFF no longer idempotent.');
    assertControlLightRuntimeSame([], ControlLightFakeRuntime::$actions, 'Redundant OFF command.');
};

foreach ([false, true] as $initialOn) {
    foreach ([0xFF0000 => '[0,100]', 0x00FF00 => '[119.055,100]', 0x0000FF => '[239.528,100]'] as $rgb => $feedback) {
        $tests['quantized RGB confirms and repeats without a command ' . (int)$initialOn . '/' . $rgb] = static function () use ($initialOn, $rgb, $feedback): void {
            $fixture = controlLightColorTransitionFixture();
            $fixture['configuration']['colorFeedbackQuantization'] = 'ha-matter-hs-254-truncate';
            ControlLightFakeRuntime::$values[20] = $initialOn;
            ControlLightFakeRuntime::$normalizedColorFeedback = $feedback;
            ControlLightFakeRuntime::$colorImplicitPowerOn = true;
            $dispatch = static fn(): array => ControlLightRuntime::dispatchTargetAction(1000, 'color', $rgb, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
            assertControlLightRuntimeSame('confirmed', $dispatch()['status'], 'Quantized color failed.');
            assertControlLightRuntimeSame('already_confirmed', $dispatch()['status'], 'Repeat not idempotent.');
            assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Unexpected command count.');
            assertControlLightRuntimeSame(true, GetValue(10), 'Missing on confirmation.');
            assertControlLightRuntimeSame(100, GetValue(11), 'Color changed brightness.');
            assertControlLightRuntimeSame(0, GetValue(31), 'False timeout.');
            assertControlLightRuntimeSame([], ControlLightFakeRuntime::$sleeps, 'Immediate feedback waited.');
        };
    }
    $tests['quantization rejects wrong bin with on-state ' . (int)$initialOn] = static function () use ($initialOn): void {
        $fixture = controlLightColorTransitionFixture();
        $fixture['configuration']['colorFeedbackQuantization'] = 'ha-matter-hs-254-truncate';
        ControlLightFakeRuntime::$values[20] = $initialOn;
        ControlLightFakeRuntime::$normalizedColorFeedback = '[120.472,100]';
        ControlLightFakeRuntime::$colorImplicitPowerOn = true;
        try {
            ControlLightRuntime::dispatchTargetAction(1000, 'color', 0x00FF00, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
            throw new RuntimeException('Wrong hue bin accepted by broad transition tolerance.');
        } catch (ControlLightCommandException) {
            assertControlLightRuntimeSame(1, GetValue(31), 'Timeout not counted.');
            assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Unexpected retry.');
            assertControlLightRuntimeSame(['SAEF_CONTROL_LIGHT_1000'], ControlLightFakeRuntime::$semaphoreLeaves, 'Lock leaked.');
        }
    };
}

$tests['quantization waits through missing feedback and uses reported facade color'] = static function (): void {
    $fixture = controlLightColorTransitionFixture();
    $fixture['configuration']['colorFeedbackQuantization'] = 'ha-matter-hs-254-truncate';
    ControlLightFakeRuntime::$values[23] = '';
    ControlLightFakeRuntime::$normalizedColorFeedback = '[119.055,100]';
    ControlLightFakeRuntime::$colorImplicitPowerOn = true;
    ControlLightFakeRuntime::$feedbackMode = 'delayed';
    $result = ControlLightRuntime::dispatchTargetAction(1000, 'color', 0x00FF00, $fixture['resources'], $fixture['configuration'], $fixture['diagnostics']);
    assertControlLightRuntimeSame('confirmed', $result['status'], 'Missing feedback did not recover.');
    assertControlLightRuntimeSame(ControlLightCore::targetToLocal('color', '[119.055,100]', $fixture['configuration']), GetValue(12), 'Facade does not show actual chromaticity.');
    assertControlLightRuntimeSame(1, count(ControlLightFakeRuntime::$actions), 'Delayed feedback retried.');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    fwrite(STDOUT, 'PASS: ' . $name . PHP_EOL);
}
fwrite(STDOUT, sprintf('PASS: %d ControlLight runtime tests.%s', $passed, PHP_EOL));
