<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;
use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

require_once __DIR__ . '/DiagnosticsFakeSymconRuntime.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';

/** @return array{configuration: array<string, mixed>, ids: array<string, int>} */
function dispatchFixture(int $serverID, bool $mapBrightnessFeedback = true): array
{
    $ids = [
        'powerState' => DiagnosticsFakeSymconRuntime::createStateVariable(0, true),
        'powerAction' => DiagnosticsFakeSymconRuntime::createStateVariable(0, false, true),
        'brightnessState' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 42),
        'brightnessAction' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true),
        'rgbState' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 0x123456),
        'rgbAction' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true),
        'colorTemperatureState' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 3000),
        'colorTemperatureAction' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 2200, true),
    ];
    DiagnosticsFakeSymconRuntime::mapActionFeedback($ids['powerAction'], $ids['powerState']);
    DiagnosticsFakeSymconRuntime::mapActionFeedback($ids['rgbAction'], $ids['rgbState']);
    DiagnosticsFakeSymconRuntime::mapActionFeedback(
        $ids['colorTemperatureAction'],
        $ids['colorTemperatureState']
    );
    if ($mapBrightnessFeedback) {
        DiagnosticsFakeSymconRuntime::mapActionFeedback($ids['brightnessAction'], $ids['brightnessState']);
    }

    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration([
        'version' => 'candidate-dispatch-test',
        'location' => 'test_site',
        'mqtt' => [
            'serverID' => $serverID,
            'baseTopic' => 'saef/export',
            'discoveryPrefix' => 'homeassistant',
        ],
        'defaults' => [
            'qos' => 0,
            'retain' => true,
        ],
        'devices' => [[
            'id' => 'example_lamp',
            'topic' => 'example_lamp',
            'name' => 'Example Lamp',
            'entities' => [[
                'id' => 'main_light',
                'class' => 'light',
                'name' => 'Main Light',
                'stateID' => $ids['powerState'],
                'actionID' => $ids['powerAction'],
                'brightnessStateID' => $ids['brightnessState'],
                'brightnessActionID' => $ids['brightnessAction'],
                'colorStateID' => $ids['rgbState'],
                'colorActionID' => $ids['rgbAction'],
                'colorTempStateID' => $ids['colorTemperatureState'],
                'colorTempActionID' => $ids['colorTemperatureAction'],
                'minKelvin' => 2200,
                'maxKelvin' => 6500,
                'confirmation' => [
                    'timeoutMilliseconds' => 100,
                    'pollIntervalMilliseconds' => 50,
                ],
            ]],
        ]],
    ]);

    return ['configuration' => $configuration, 'ids' => $ids];
}

function assertDispatchSame(mixed $expected, mixed $actual, string $message): void
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
function assertDispatchThrows(string $expectedClass, callable $operation, string $message): void
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

/**
 * @return array{ownerScriptID: int, fixture: array{configuration: array<string, mixed>, ids: array<string, int>}, reconcile: array<string, mixed>}
 */
function initializedDispatchFixture(bool $mapBrightnessFeedback = true): array
{
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $fixture = dispatchFixture($serverID, $mapBrightnessFeedback);
    $reconcile = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup(
        $ownerScriptID,
        $fixture['configuration']
    );
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();

    return ['ownerScriptID' => $ownerScriptID, 'fixture' => $fixture, 'reconcile' => $reconcile];
}

/** @param array<string, mixed> $registry */
function dispatchCommandVariableID(array $registry, string $commandType): int
{
    $variableID = array_search(
        ['entityKey' => 'example_lamp.main_light', 'commandType' => $commandType],
        $registry['commandIndex'],
        true
    );
    if (!is_int($variableID) && !is_string($variableID)) {
        throw new RuntimeException('Command index is missing: ' . $commandType);
    }

    return (int)$variableID;
}

$tests = [];

$tests['confirms a strict command and republishes only its entity'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'brightness');
    SetValue($commandVariableID, '55');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('confirmed', $result['status'], 'Command status differs.');
    assertDispatchSame(5, $result['publishedMessages'], 'Affected runtime message count differs.');
    assertDispatchSame(55, GetValue($setup['fixture']['ids']['brightnessState']), 'Observed brightness differs.');
    $calls = DiagnosticsFakeSymconRuntime::requestActionCalls();
    assertDispatchSame(6, count($calls), 'Command and runtime action count differs.');
    assertDispatchSame($setup['fixture']['ids']['brightnessAction'], $calls[0]['variableID'], 'Wrong action variable.');
    assertDispatchSame(55, $calls[0]['value'], 'Command action type or value differs.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['COMMANDS']),
        'Command count differs.'
    );
};

$tests['rejects malformed command payload without a device action'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'brightness');
    SetValue($commandVariableID, '55.5');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('invalid_payload', $result['status'], 'Invalid payload status differs.');
    assertDispatchSame([], DiagnosticsFakeSymconRuntime::requestActionCalls(), 'Invalid payload caused an action.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']),
        'Invalid payload failure count differs.'
    );
};

$tests['reports a bounded confirmation timeout without publishing'] = static function (): void {
    $setup = initializedDispatchFixture(false);
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'brightness');
    SetValue($commandVariableID, '55');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('confirmation_timeout', $result['status'], 'Timeout status differs.');
    assertDispatchSame(1, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Timeout published state.');
    assertDispatchSame(42, GetValue($setup['fixture']['ids']['brightnessState']), 'Unconfirmed state changed.');
};

$tests['returns action_failed when the device action rejects the command'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'brightness');
    SetValue($commandVariableID, '55');
    DiagnosticsFakeSymconRuntime::failRequestActionAt(1);

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('action_failed', $result['status'], 'Rejected action status differs.');
    assertDispatchSame(1, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Rejected action published state.');
    assertDispatchSame(42, GetValue($setup['fixture']['ids']['brightnessState']), 'Rejected action changed state.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['SUCCESSES']),
        'Rejected command counted as success.'
    );
};

$tests['returns publish_failed without committing a partial runtime channel'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'brightness');
    SetValue($commandVariableID, '55');
    DiagnosticsFakeSymconRuntime::failRequestActionAt(3);

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('publish_failed', $result['status'], 'Dispatch publish failure status differs.');
    $currentRegistry = SAEF_ReadRegistry($setup['reconcile']['diagnostics']['registryID']);
    $entry = $currentRegistry['managedEntities']['example_lamp.main_light'];
    assertDispatchSame(
        $registry['managedEntities']['example_lamp.main_light']['runtimeHash'],
        $entry['runtimeHash'],
        'Partial dispatch runtime channel was committed.'
    );
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['SUCCESSES']),
        'Dispatch publish failure counted as success.'
    );
};

$tests['publishes and then skips one indexed state entity'] = static function (): void {
    $setup = initializedDispatchFixture();
    SetValue($setup['fixture']['ids']['powerState'], false);

    $first = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $setup['fixture']['ids']['powerState']
    );
    $second = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $setup['fixture']['ids']['powerState']
    );

    assertDispatchSame('published', $first['status'], 'First state status differs.');
    assertDispatchSame(5, $first['publishedMessages'], 'State publish count differs.');
    assertDispatchSame('skipped', $second['status'], 'Unchanged state was not skipped.');
    assertDispatchSame(5, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'State skip caused actions.');
};

$tests['rejects unknown triggers without full reconciliation'] = static function (): void {
    $setup = initializedDispatchFixture();
    $unknownVariableID = DiagnosticsFakeSymconRuntime::createStateVariable(1, 1);
    $instanceCount = DiagnosticsFakeSymconRuntime::instanceCount();
    $eventCount = DiagnosticsFakeSymconRuntime::eventCount();

    assertDispatchThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
            $setup['ownerScriptID'],
            $setup['fixture']['configuration'],
            $unknownVariableID
        ),
        'Unknown trigger was accepted.'
    );

    assertDispatchSame($instanceCount, DiagnosticsFakeSymconRuntime::instanceCount(), 'Unknown trigger reconciled instances.');
    assertDispatchSame($eventCount, DiagnosticsFakeSymconRuntime::eventCount(), 'Unknown trigger reconciled events.');
};

$tests['rejects configuration not matching the published registry'] = static function (): void {
    $setup = initializedDispatchFixture();
    $changedConfiguration = $setup['fixture']['configuration'];
    $changedConfiguration['version'] = 'changed-without-reconcile';
    $triggerVariableID = $setup['fixture']['ids']['powerState'];

    assertDispatchThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
            $setup['ownerScriptID'],
            $changedConfiguration,
            $triggerVariableID
        ),
        'Unreconciled configuration was accepted.'
    );
};

$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Dispatch test failed: %s\n%s\n", $name, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("MQTT Discovery Exporter dispatch tests passed: %d.\n", $passed));
