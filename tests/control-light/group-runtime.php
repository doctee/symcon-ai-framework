<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCommandException;
use SAEF\CaseStudy\ControlLight\ControlLightCore;
use SAEF\CaseStudy\ControlLight\ControlLightRuntime;

final class ControlLightGroupRuntimeFake
{
    /** @var array<int, mixed> */
    public static array $values = [];
    /** @var array<int, array<string, int>> */
    public static array $variables = [];
    /** @var list<array{variableID: int, value: mixed}> */
    public static array $actions = [];
    /** @var array<int, mixed> */
    public static array $memberFeedback = [];
    /** @var list<string> */
    public static array $semaphoreLeaves = [];
    public static string $endpointFeedback = 'immediate';
    public static int $sleepCalls = 0;

    public static function reset(): void
    {
        self::$values = [];
        self::$variables = [];
        self::$actions = [];
        self::$memberFeedback = [];
        self::$semaphoreLeaves = [];
        self::$endpointFeedback = 'immediate';
        self::$sleepCalls = 0;
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
    return isset(ControlLightGroupRuntimeFake::$variables[$variableID]);
}

/** @return array<string, int> */
function IPS_GetVariable(int $variableID): array
{
    return ControlLightGroupRuntimeFake::$variables[$variableID];
}

function GetValue(int $variableID): mixed
{
    return ControlLightGroupRuntimeFake::$values[$variableID];
}

function SetValue(int $variableID, mixed $value): void
{
    $previous = ControlLightGroupRuntimeFake::$values[$variableID];
    ControlLightGroupRuntimeFake::$values[$variableID] = $value;
    ControlLightGroupRuntimeFake::$variables[$variableID]['VariableUpdated']++;
    if ($previous !== $value) {
        ControlLightGroupRuntimeFake::$variables[$variableID]['VariableChanged']++;
    }
}

function RequestAction(int $variableID, mixed $value): bool
{
    ControlLightGroupRuntimeFake::$actions[] = ['variableID' => $variableID, 'value' => $value];
    if (ControlLightGroupRuntimeFake::$endpointFeedback === 'immediate') {
        SetValue($variableID, $value);
    } elseif (ControlLightGroupRuntimeFake::$endpointFeedback === 'mismatch') {
        SetValue($variableID, is_bool($value) ? !$value : max(0, (int)$value - 10));
    }
    foreach (ControlLightGroupRuntimeFake::$memberFeedback as $feedbackVariableID => $feedbackValue) {
        SetValue($feedbackVariableID, $feedbackValue);
    }

    return true;
}

function IPS_Sleep(int $milliseconds): void
{
    ControlLightGroupRuntimeFake::$sleepCalls++;
    usleep($milliseconds * 1000);
}

function IPS_SemaphoreEnter(string $name, int $milliseconds): bool
{
    return true;
}

function IPS_SemaphoreLeave(string $name): bool
{
    ControlLightGroupRuntimeFake::$semaphoreLeaves[] = $name;
    return true;
}

function IPS_LogMessage(string $sender, string $message): void
{
}

require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightRuntime.php';

function assertControlLightGroupSame(mixed $expected, mixed $actual, string $message): void
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
function controlLightGroupFixture(bool $withColorTemperature = false): array
{
    ControlLightGroupRuntimeFake::reset();
    ControlLightGroupRuntimeFake::variable(10, 0, false);
    ControlLightGroupRuntimeFake::variable(11, 1, 10);
    if ($withColorTemperature) {
        ControlLightGroupRuntimeFake::variable(12, 1, 2700);
    }
    ControlLightGroupRuntimeFake::variable(20, 0, false, true);
    ControlLightGroupRuntimeFake::variable(21, 1, 10, true);
    if ($withColorTemperature) {
        ControlLightGroupRuntimeFake::variable(22, 1, 2700, true);
    }
    ControlLightGroupRuntimeFake::variable(40, 0, false);
    ControlLightGroupRuntimeFake::variable(41, 1, 10);
    ControlLightGroupRuntimeFake::variable(42, 0, true);
    ControlLightGroupRuntimeFake::variable(43, 1, time());
    if ($withColorTemperature) {
        ControlLightGroupRuntimeFake::variable(44, 1, 2700);
    }
    ControlLightGroupRuntimeFake::variable(50, 0, false);
    ControlLightGroupRuntimeFake::variable(51, 1, 10);
    ControlLightGroupRuntimeFake::variable(52, 0, true);
    ControlLightGroupRuntimeFake::variable(53, 1, time());
    if ($withColorTemperature) {
        ControlLightGroupRuntimeFake::variable(54, 1, 2700);
    }
    foreach ([30, 31, 32] as $diagnosticID) {
        ControlLightGroupRuntimeFake::variable($diagnosticID, 1, 0);
    }

    $rawConfiguration = [
        'preset' => 'Z2M',
        'identColor' => '',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        'confirmation' => ['timeoutMilliseconds' => 30, 'pollIntervalMilliseconds' => 10],
        'semaphore' => ['timeoutMilliseconds' => 100],
        'groupFeedback' => [
            'mode' => ControlLightCore::FEEDBACK_MEMBER_CONFIRMED,
            'freshnessSeconds' => 900,
            'brightnessTolerance' => 1,
            'members' => [
                [
                    'key' => 'member-1',
                    'stateVariableID' => 40,
                    'brightnessVariableID' => 41,
                    'availabilityVariableID' => 42,
                    'lastSeenVariableID' => 43,
                    ...($withColorTemperature ? ['colorTemperatureVariableID' => 44] : []),
                ],
                [
                    'key' => 'member-2',
                    'stateVariableID' => 50,
                    'brightnessVariableID' => 51,
                    'availabilityVariableID' => 52,
                    'lastSeenVariableID' => 53,
                    ...($withColorTemperature ? ['colorTemperatureVariableID' => 54] : []),
                ],
            ],
        ],
    ];
    if (!$withColorTemperature) {
        $rawConfiguration['identTemp'] = '';
    }
    $configuration = ControlLightCore::normalizeConfiguration($rawConfiguration);

    $localVariableIDs = ['state' => 10, 'brightness' => 11];
    $targetVariableIDs = ['state' => 20, 'brightness' => 21];
    if ($withColorTemperature) {
        $localVariableIDs['colorTemperature'] = 12;
        $targetVariableIDs['colorTemperature'] = 22;
    }

    return [
        'configuration' => $configuration,
        'resources' => [
            'localVariableIDs' => $localVariableIDs,
            'targetVariableIDs' => $targetVariableIDs,
            'groupMembers' => $configuration['groupFeedback']['members'],
            'externalTriggers' => [],
        ],
        'diagnostics' => [
            'statisticIDs' => [
                'COMMANDS' => 30,
                'CONFIRMATION_TIMEOUTS' => 31,
                'LAST_FEEDBACK' => 32,
            ],
        ],
    ];
}

function controlLightGroupFailure(callable $operation): ControlLightCommandException
{
    try {
        $operation();
    } catch (ControlLightCommandException $exception) {
        return $exception;
    }
    throw new RuntimeException('Expected group command failure was not thrown.');
}

$tests = [];

$tests['uses one group command and confirms both members'] = static function (): void {
    $fixture = controlLightGroupFixture();
    ControlLightGroupRuntimeFake::$memberFeedback = [40 => true, 50 => true];
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightGroupSame('confirmed', $result['status'], 'Group result differs.');
    assertControlLightGroupSame(
        [['variableID' => 20, 'value' => true]],
        ControlLightGroupRuntimeFake::$actions,
        'Group action count or target differs.'
    );
    assertControlLightGroupSame(true, GetValue(10), 'Derived facade state differs.');
};

$tests['keeps fresh matching members idempotent'] = static function (): void {
    $fixture = controlLightGroupFixture();
    foreach ([20, 40, 50] as $variableID) {
        SetValue($variableID, true);
    }
    ControlLightGroupRuntimeFake::$actions = [];
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightGroupSame('already_confirmed', $result['status'], 'Idempotent result differs.');
    assertControlLightGroupSame([], ControlLightGroupRuntimeFake::$actions, 'Idempotent call sent a command.');
};

$tests['confirms group color temperature through every configured member'] = static function (): void {
    $fixture = controlLightGroupFixture(true);
    ControlLightGroupRuntimeFake::$memberFeedback = [44 => 3200, 54 => 3200];
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'colorTemperature',
        3200,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );

    assertControlLightGroupSame('confirmed', $result['status'], 'Temperature result differs.');
    assertControlLightGroupSame(
        [['variableID' => 22, 'value' => 3200]],
        ControlLightGroupRuntimeFake::$actions,
        'Temperature group action count or target differs.'
    );
    assertControlLightGroupSame(3200, GetValue(12), 'Temperature facade feedback differs.');
};

$tests['rejects partial group color-temperature feedback'] = static function (): void {
    $fixture = controlLightGroupFixture(true);
    ControlLightGroupRuntimeFake::$memberFeedback = [44 => 3200];
    $exception = controlLightGroupFailure(static fn(): array => ControlLightRuntime::dispatchTargetAction(
        1000,
        'colorTemperature',
        3200,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    ));

    assertControlLightGroupSame(
        ControlLightCommandException::FAILURE_GROUP_PARTIAL_FEEDBACK,
        $exception->failureClass(),
        'Partial temperature failure class differs.'
    );
    assertControlLightGroupSame(2700, GetValue(12), 'Failed temperature command changed facade value.');
};

$tests['classifies partial feedback without optimistic facade write'] = static function (): void {
    $fixture = controlLightGroupFixture();
    ControlLightGroupRuntimeFake::$memberFeedback = [40 => true];
    $exception = controlLightGroupFailure(static fn(): array => ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    ));

    assertControlLightGroupSame(
        ControlLightCommandException::FAILURE_GROUP_PARTIAL_FEEDBACK,
        $exception->failureClass(),
        'Partial failure class differs.'
    );
    assertControlLightGroupSame(false, GetValue(10), 'Failed group command changed facade state.');
    if (ControlLightGroupRuntimeFake::$sleepCalls > 4) {
        throw new RuntimeException('Shared deadline polling scaled with member count.');
    }
};

$tests['classifies an unavailable pending member'] = static function (): void {
    $fixture = controlLightGroupFixture();
    SetValue(52, false);
    ControlLightGroupRuntimeFake::$memberFeedback = [40 => true];
    $exception = controlLightGroupFailure(static fn(): array => ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    ));
    assertControlLightGroupSame(
        ControlLightCommandException::FAILURE_GROUP_MEMBER_OFFLINE,
        $exception->failureClass(),
        'Offline member class differs.'
    );
};

$tests['requires freshness for pre-existing equality'] = static function (): void {
    $fixture = controlLightGroupFixture();
    foreach ([20, 40, 50] as $variableID) {
        ControlLightGroupRuntimeFake::$values[$variableID] = true;
    }
    foreach ([40, 41, 50, 51] as $variableID) {
        ControlLightGroupRuntimeFake::$variables[$variableID]['VariableUpdated'] = time() - 3600;
    }
    foreach ([43, 53] as $variableID) {
        ControlLightGroupRuntimeFake::$values[$variableID] = time() - 3600;
    }
    $exception = controlLightGroupFailure(static fn(): array => ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    ));
    assertControlLightGroupSame(
        ControlLightCommandException::FAILURE_GROUP_MEMBER_STALE,
        $exception->failureClass(),
        'Stale member class differs.'
    );
};

$tests['distinguishes projection mismatch from endpoint timeout'] = static function (): void {
    $fixture = controlLightGroupFixture();
    ControlLightGroupRuntimeFake::$endpointFeedback = 'mismatch';
    ControlLightGroupRuntimeFake::$memberFeedback = [40 => true, 50 => true];
    $mismatch = controlLightGroupFailure(static fn(): array => ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    ));
    assertControlLightGroupSame(
        ControlLightCommandException::FAILURE_GROUP_PROJECTION_MISMATCH,
        $mismatch->failureClass(),
        'Projection mismatch class differs.'
    );

    $fixture = controlLightGroupFixture();
    ControlLightGroupRuntimeFake::$endpointFeedback = 'none';
    ControlLightGroupRuntimeFake::$memberFeedback = [40 => true, 50 => true];
    $timeout = controlLightGroupFailure(static fn(): array => ControlLightRuntime::dispatchTargetAction(
        1000,
        'state',
        true,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    ));
    assertControlLightGroupSame(
        ControlLightCommandException::FAILURE_GROUP_ENDPOINT_TIMEOUT,
        $timeout->failureClass(),
        'Endpoint timeout class differs.'
    );
};

$tests['accepts bounded member brightness tolerance'] = static function (): void {
    $fixture = controlLightGroupFixture();
    ControlLightGroupRuntimeFake::$memberFeedback = [41 => 59, 51 => 61];
    $result = ControlLightRuntime::dispatchTargetAction(
        1000,
        'brightness',
        60,
        $fixture['resources'],
        $fixture['configuration'],
        $fixture['diagnostics']
    );
    assertControlLightGroupSame('confirmed', $result['status'], 'Brightness group result differs.');
    assertControlLightGroupSame(60, GetValue(11), 'Reported group brightness differs.');
};

$tests['derives passive any-member-on and retains stale off state'] = static function (): void {
    $fixture = controlLightGroupFixture();
    $syncAll = new ReflectionMethod(ControlLightRuntime::class, 'syncAll');

    SetValue(40, true);
    $syncAll->invoke(null, $fixture['resources'], $fixture['configuration']);
    assertControlLightGroupSame(true, GetValue(10), 'Any-member-on state differs.');

    ControlLightGroupRuntimeFake::$values[40] = false;
    foreach ([40, 41, 50, 51] as $variableID) {
        ControlLightGroupRuntimeFake::$variables[$variableID]['VariableUpdated'] = time() - 3600;
    }
    foreach ([43, 53] as $variableID) {
        ControlLightGroupRuntimeFake::$values[$variableID] = time() - 3600;
    }
    $syncAll->invoke(null, $fixture['resources'], $fixture['configuration']);
    assertControlLightGroupSame(true, GetValue(10), 'Stale all-off evidence collapsed facade state.');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    fwrite(STDOUT, 'PASS: ' . $name . PHP_EOL);
}
fwrite(STDOUT, sprintf('PASS: %d ControlLight group runtime tests.%s', $passed, PHP_EOL));
