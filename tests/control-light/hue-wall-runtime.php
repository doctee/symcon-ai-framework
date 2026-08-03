<?php

declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\HueWallSwitchCore;
use SAEF\CaseStudy\ControlLight\HueWallSwitchRuntime;

final class HueWallRuntimeFake
{
    /** @var array<int, mixed> */
    public static array $values = [];
    /** @var array<int, array<string, int>> */
    public static array $variables = [];
    /** @var list<array{variableID: int, value: mixed}> */
    public static array $actions = [];
    /** @var list<string> */
    public static array $semaphoreEnters = [];
    public static bool $semaphoreAvailable = true;
    public static bool $requestReturnsFalse = false;
    public static bool $requestThrows = false;
    public static string $feedbackMode = 'immediate';
    /** @var array{variableID: int, value: mixed}|null */
    public static ?array $pendingFeedback = null;

    public static function reset(): void
    {
        self::$values = [];
        self::$variables = [];
        self::$actions = [];
        self::$semaphoreEnters = [];
        self::$semaphoreAvailable = true;
        self::$requestReturnsFalse = false;
        self::$requestThrows = false;
        self::$feedbackMode = 'immediate';
        self::$pendingFeedback = null;
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
}

function IPS_VariableExists(int $variableID): bool
{
    return isset(HueWallRuntimeFake::$variables[$variableID]);
}

/** @return array<string, int> */
function IPS_GetVariable(int $variableID): array
{
    return HueWallRuntimeFake::$variables[$variableID];
}

function GetValue(int $variableID): mixed
{
    return HueWallRuntimeFake::$values[$variableID];
}

function SetValue(int $variableID, mixed $value): void
{
    $previous = HueWallRuntimeFake::$values[$variableID];
    HueWallRuntimeFake::$values[$variableID] = $value;
    HueWallRuntimeFake::$variables[$variableID]['VariableUpdated']++;
    if ($previous !== $value) {
        HueWallRuntimeFake::$variables[$variableID]['VariableChanged']++;
    }
}

function RequestAction(int $variableID, mixed $value): bool
{
    HueWallRuntimeFake::$actions[] = ['variableID' => $variableID, 'value' => $value];
    if (HueWallRuntimeFake::$requestThrows) {
        throw new RuntimeException('Fake action exception.');
    }
    if (HueWallRuntimeFake::$requestReturnsFalse) {
        return false;
    }
    if (HueWallRuntimeFake::$feedbackMode === 'immediate') {
        SetValue($variableID, $value);
    } elseif (HueWallRuntimeFake::$feedbackMode === 'delayed') {
        HueWallRuntimeFake::$pendingFeedback = ['variableID' => $variableID, 'value' => $value];
    }

    return true;
}

function IPS_Sleep(int $milliseconds): void
{
    if (HueWallRuntimeFake::$pendingFeedback === null) {
        return;
    }
    $feedback = HueWallRuntimeFake::$pendingFeedback;
    HueWallRuntimeFake::$pendingFeedback = null;
    SetValue($feedback['variableID'], $feedback['value']);
}

function IPS_SemaphoreEnter(string $name, int $milliseconds): bool
{
    HueWallRuntimeFake::$semaphoreEnters[] = $name . ':' . (string) $milliseconds;

    if (str_starts_with($name, 'SAEF_STATISTIC_')) {
        return true;
    }

    return HueWallRuntimeFake::$semaphoreAvailable;
}

function IPS_SemaphoreLeave(string $name): bool
{
    return true;
}

function IPS_LogMessage(string $sender, string $message): void
{
}

require_once __DIR__ . '/../../case-studies/control-light/candidate/HueWallSwitchRuntime.php';

function assertHueRuntimeSame(mixed $expected, mixed $actual, string $message): void
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

/**
 * @return array{
 *     configuration: array<string, mixed>,
 *     resources: array<string, mixed>,
 *     diagnostics: array<string, mixed>
 * }
 */
function hueWallRuntimeFixture(int $debounceMilliseconds = 300, int $confirmationTimeout = 100): array
{
    HueWallRuntimeFake::reset();
    HueWallRuntimeFake::variable(10, 0, false, true);
    HueWallRuntimeFake::variable(12, 0, false, true);
    HueWallRuntimeFake::variable(11, 3, 'left_press_release');
    HueWallRuntimeFake::variable(20, 2, 0.0);
    HueWallRuntimeFake::variable(21, 2, 0.0);
    HueWallRuntimeFake::variable(30, 3, '[]');
    $statisticIDs = [];
    $statisticNames = [
        'HWS_ACTION_UPDATES',
        'HWS_COMMAND_ATTEMPTS',
        'HWS_CONFIRMED',
        'HWS_COMMAND_FAILURES',
        'HWS_CONFIRMATION_TIMEOUTS',
        'HWS_DEBOUNCED',
        'HWS_IGNORED_ACTIONS',
        'HWS_LAST_SUCCESS',
        'HWS_LAST_FEEDBACK',
    ];
    foreach ($statisticNames as $index => $ident) {
        $id = 40 + $index;
        HueWallRuntimeFake::variable($id, 1, 0);
        $statisticIDs[$ident] = $id;
    }

    $configuration = HueWallSwitchCore::normalizeConfiguration([
        'debounceMilliseconds' => $debounceMilliseconds,
        'confirmation' => [
            'timeoutMilliseconds' => $confirmationTimeout,
            'pollIntervalMilliseconds' => 10,
        ],
        'semaphore' => [
            'timeoutMilliseconds' => 4000,
        ],
        'targets' => [
            'globe' => ['stateVariableID' => 10],
            'ceiling' => ['stateVariableID' => 12],
        ],
        'sources' => [
            [
                'key' => 'north',
                'sourceVariableID' => 11,
                'leftTargetKey' => 'globe',
                'rightTargetKey' => 'ceiling',
            ],
        ],
    ]);

    return [
        'configuration' => $configuration,
        'resources' => [
            'debounceVariableIDs' => [
                'north' => [
                    'globe' => 20,
                    'ceiling' => 21,
                ],
            ],
        ],
        'diagnostics' => ['errorRingBufferID' => 30, 'statisticIDs' => $statisticIDs],
    ];
}

$fixture = hueWallRuntimeFixture();
$first = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics'],
    1.0
);
$second = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics'],
    2.0
);
assertHueRuntimeSame('confirmed', $first['status'], 'First identical action update failed.');
assertHueRuntimeSame('confirmed', $second['status'], 'Second identical action update failed.');
assertHueRuntimeSame(
    [
        ['variableID' => 10, 'value' => true],
        ['variableID' => 10, 'value' => false],
    ],
    HueWallRuntimeFake::$actions,
    'Repeated identical action payload did not produce two sequential toggles.'
);

$fixture = hueWallRuntimeFixture();
HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics'],
    1.0
);
$debounced = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics'],
    1.1
);
assertHueRuntimeSame('debounced', $debounced['status'], 'Burst duplicate was not debounced.');
assertHueRuntimeSame(1, count(HueWallRuntimeFake::$actions), 'Debounced event emitted a device action.');

$fixture = hueWallRuntimeFixture();
$globe = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics'],
    1.0
);
$ceiling = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'right_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics'],
    1.1
);
assertHueRuntimeSame('confirmed', $globe['status'], 'First target-specific action failed.');
assertHueRuntimeSame('confirmed', $ceiling['status'], 'Other target was incorrectly debounced.');
assertHueRuntimeSame(
    [
        ['variableID' => 10, 'value' => true],
        ['variableID' => 12, 'value' => true],
    ],
    HueWallRuntimeFake::$actions,
    'Independent targets did not retain independent debounce state.'
);
assertHueRuntimeSame(
    ['SAEF_HUE_WALL_100_GLOBE:4000', 'SAEF_HUE_WALL_100_CEILING:4000'],
    array_values(array_filter(
        HueWallRuntimeFake::$semaphoreEnters,
        static fn(string $entry): bool => str_starts_with($entry, 'SAEF_HUE_WALL_')
    )),
    'Target-specific semaphore contract differs.'
);
assertHueRuntimeSame(
    6,
    count(array_filter(
        HueWallRuntimeFake::$semaphoreEnters,
        static fn(string $entry): bool => str_starts_with($entry, 'SAEF_STATISTIC_')
    )),
    'Parallel target scenario did not serialize every statistic increment.'
);

$fixture = hueWallRuntimeFixture();
HueWallRuntimeFake::$semaphoreAvailable = false;
$busy = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
assertHueRuntimeSame('busy', $busy['failureClass'], 'Parallel target contention was not classified.');
assertHueRuntimeSame(0, count(HueWallRuntimeFake::$actions), 'Busy target emitted a device action.');

$fixture = hueWallRuntimeFixture();
HueWallRuntimeFake::$requestReturnsFalse = true;
$rejected = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
assertHueRuntimeSame('action_rejected', $rejected['failureClass'], 'Rejected action was not classified.');
assertHueRuntimeSame(false, GetValue(10), 'Rejected action changed confirmed facade optimistically.');

$fixture = hueWallRuntimeFixture(0, 20);
HueWallRuntimeFake::$feedbackMode = 'none';
$timeout = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
assertHueRuntimeSame('feedback_timeout', $timeout['failureClass'], 'Missing feedback was not classified.');
assertHueRuntimeSame(1, count(HueWallRuntimeFake::$actions), 'Feedback timeout retried the command.');
assertHueRuntimeSame(false, GetValue(10), 'Feedback timeout changed confirmed facade optimistically.');

$fixture = hueWallRuntimeFixture(0, 20);
HueWallRuntimeFake::$feedbackMode = 'delayed';
$delayed = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_press_release',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
assertHueRuntimeSame('confirmed', $delayed['status'], 'Delayed authoritative feedback was not accepted.');

$fixture = hueWallRuntimeFixture();
$ignored = HueWallSwitchRuntime::dispatchActionUpdate(
    100,
    'north',
    'left_hold',
    $fixture['resources'],
    $fixture['configuration'],
    $fixture['diagnostics']
);
assertHueRuntimeSame('ignored_action', $ignored['status'], 'Unrelated Hue action was not ignored.');
assertHueRuntimeSame(0, count(HueWallRuntimeFake::$actions), 'Ignored Hue action emitted a command.');

fwrite(STDOUT, 'PASS: Hue Wall runtime feedback, failure and concurrency contract.' . PHP_EOL);
