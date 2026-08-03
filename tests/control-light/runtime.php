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
    assertControlLightRuntimeSame([['variableID' => 21, 'value' => 55]], ControlLightFakeRuntime::$actions, 'Action calls differ.');
    assertControlLightRuntimeSame(1, GetValue(30), 'Command statistic differs.');
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

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    fwrite(STDOUT, 'PASS: ' . $name . PHP_EOL);
}
fwrite(STDOUT, sprintf('PASS: %d ControlLight runtime tests.%s', $passed, PHP_EOL));
