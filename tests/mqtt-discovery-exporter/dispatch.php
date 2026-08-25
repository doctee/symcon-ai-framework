<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;
use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

require_once __DIR__ . '/DiagnosticsFakeSymconRuntime.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';

/** @return array{configuration: array<string, mixed>, ids: array<string, int>} */
function dispatchFixture(
    int $serverID,
    bool $mapBrightnessFeedback = true,
    bool $mapColorTemperatureFeedback = true
): array {
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
    if ($mapColorTemperatureFeedback) {
        DiagnosticsFakeSymconRuntime::mapActionFeedback(
            $ids['colorTemperatureAction'],
            $ids['colorTemperatureState']
        );
    }
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
function initializedDispatchFixture(
    bool $mapBrightnessFeedback = true,
    bool $mapColorTemperatureFeedback = true
): array {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $fixture = dispatchFixture(
        $serverID,
        $mapBrightnessFeedback,
        $mapColorTemperatureFeedback
    );
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

/** @param array<string, mixed> $setup */
function supersedeRegisteredCommand(array $setup, string $commandType): void
{
    $registryID = $setup['reconcile']['diagnostics']['arbitrationRegistryID'];
    $channelKey = hash('sha256', 'example_lamp.main_light' . "\0" . $commandType);
    $registry = SAEF_ReadRegistry($registryID);
    $slot = $registry['channels'][$channelKey] ?? null;
    if (!is_array($slot)) {
        throw new RuntimeException('Registered arbitration slot is missing.');
    }
    $registry['channels'][$channelKey] = [
        'generation' => $slot['generation'] + 1,
        'targetHash' => str_repeat('f', 64),
        'updatedAt' => time(),
    ];
    SAEF_WriteRegistry($registryID, $registry);
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

$tests['uses the immutable event payload instead of a later variable value'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    SetValue($commandVariableID, '99');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('confirmed', $result['status'], 'Snapshot command status differs.');
    assertDispatchSame(
        55,
        DiagnosticsFakeSymconRuntime::requestActionCalls()[0]['value'],
        'Dispatch used the later variable value.'
    );
};

$tests['supersedes an older target before device action'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    $mutated = false;
    DiagnosticsFakeSymconRuntime::setSemaphoreEnterCallback(
        static function (string $name, int $milliseconds) use ($setup, &$mutated): bool {
            if (str_starts_with($name, 'SAEF_MQTT_EXPORTER_DISPATCH_') && !$mutated) {
                supersedeRegisteredCommand($setup, 'brightness');
                $mutated = true;
            }
            return $milliseconds > 0;
        }
    );

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('superseded', $result['status'], 'Older command was not superseded.');
    assertDispatchSame([], DiagnosticsFakeSymconRuntime::requestActionCalls(), 'Superseded command caused an action.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['SUPERSEDED_COMMANDS']),
        'Superseded command count differs.'
    );
    assertDispatchSame(
        0,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']),
        'Supersession was counted as a failure.'
    );
};

$tests['lets only the newest of three different rapid targets act'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    $supersedeNext = true;
    DiagnosticsFakeSymconRuntime::setSemaphoreEnterCallback(
        static function (string $name, int $milliseconds) use ($setup, &$supersedeNext): bool {
            if (str_starts_with($name, 'SAEF_MQTT_EXPORTER_DISPATCH_') && $supersedeNext) {
                supersedeRegisteredCommand($setup, 'brightness');
                $supersedeNext = false;
            }
            return $milliseconds > 0;
        }
    );

    $first = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '25'
    );
    $supersedeNext = true;
    $second = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '50'
    );
    $supersedeNext = false;
    $third = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '75'
    );

    assertDispatchSame('superseded', $first['status'], 'First rapid target status differs.');
    assertDispatchSame('superseded', $second['status'], 'Second rapid target status differs.');
    assertDispatchSame('confirmed', $third['status'], 'Newest rapid target status differs.');
    assertDispatchSame(75, DiagnosticsFakeSymconRuntime::requestActionCalls()[0]['value'], 'Newest target did not act.');
    assertDispatchSame(6, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Rapid targets caused extra actions.');
};

$tests['keeps repeated equal targets as independent dispatches'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );

    $first = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );
    $second = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('confirmed', $first['status'], 'First equal command status differs.');
    assertDispatchSame('confirmed', $second['status'], 'Second equal command status differs.');
    assertDispatchSame(2, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['COMMANDS']), 'Equal command count differs.');
};

$tests['suppresses old publication when superseded during confirmation'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    $mutated = false;
    DiagnosticsFakeSymconRuntime::setRequestActionCallback(
        static function (int $variableID, mixed $value) use ($setup, &$mutated): void {
            if ($variableID === $setup['fixture']['ids']['brightnessAction'] && !$mutated) {
                supersedeRegisteredCommand($setup, 'brightness');
                $mutated = true;
            }
        }
    );

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('superseded', $result['status'], 'Confirmed old command was not superseded.');
    assertDispatchSame(1, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Old command published runtime state.');
};

$tests['classifies an old confirmation timeout as superseded'] = static function (): void {
    $setup = initializedDispatchFixture(false);
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    DiagnosticsFakeSymconRuntime::setRequestActionCallback(
        static function (int $variableID, mixed $value) use ($setup): void {
            if ($variableID === $setup['fixture']['ids']['brightnessAction']) {
                supersedeRegisteredCommand($setup, 'brightness');
            }
        }
    );

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('superseded', $result['status'], 'Old confirmation timeout status differs.');
    assertDispatchSame(0, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']), 'Old timeout failed.');
};

$tests['classifies dispatch lock timeout by current generation'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    DiagnosticsFakeSymconRuntime::setSemaphoreEnterCallback(
        static fn (string $name, int $milliseconds): bool => !str_starts_with(
            $name,
            'SAEF_MQTT_EXPORTER_DISPATCH_'
        ) && $milliseconds > 0
    );

    assertDispatchThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
            $setup['ownerScriptID'],
            $setup['fixture']['configuration'],
            $commandVariableID,
            '55'
        ),
        'Current dispatch lock timeout was not rejected.'
    );
    assertDispatchSame(1, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']), 'Current lock failure count differs.');
};

$tests['derives command lock wait from confirmation timing'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    $dispatchWait = null;
    DiagnosticsFakeSymconRuntime::setSemaphoreEnterCallback(
        static function (string $name, int $milliseconds) use (&$dispatchWait): bool {
            if (str_starts_with($name, 'SAEF_MQTT_EXPORTER_DISPATCH_')) {
                $dispatchWait = $milliseconds;
            }
            return $milliseconds > 0;
        }
    );

    MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame(5100, $dispatchWait, 'Derived command lock wait differs.');
};

$tests['classifies an old dispatch lock timeout as superseded'] = static function (): void {
    $setup = initializedDispatchFixture();
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    $mutated = false;
    DiagnosticsFakeSymconRuntime::setSemaphoreEnterCallback(
        static function (string $name, int $milliseconds) use ($setup, &$mutated): bool {
            if (str_starts_with($name, 'SAEF_MQTT_EXPORTER_DISPATCH_')) {
                if (!$mutated) {
                    supersedeRegisteredCommand($setup, 'brightness');
                    $mutated = true;
                }
                return false;
            }
            return $milliseconds > 0;
        }
    );

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('superseded', $result['status'], 'Old lock timeout status differs.');
    assertDispatchSame(0, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']), 'Old lock timeout failed.');
};

$tests['keeps an action rejection as failure after supersession'] = static function (): void {
    $setup = initializedDispatchFixture(false);
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    DiagnosticsFakeSymconRuntime::failRequestActionAt(1);
    DiagnosticsFakeSymconRuntime::setRequestActionCallback(
        static function (int $variableID, mixed $value) use ($setup): void {
            if ($variableID === $setup['fixture']['ids']['brightnessAction']) {
                supersedeRegisteredCommand($setup, 'brightness');
            }
        }
    );

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('action_failed', $result['status'], 'Superseded action rejection was hidden.');
    assertDispatchSame(1, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']), 'Action rejection count differs.');
    assertDispatchSame(0, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['SUPERSEDED_COMMANDS']), 'Action rejection was counted as superseded.');
};

$tests['keeps an action exception as failure after supersession'] = static function (): void {
    $setup = initializedDispatchFixture(false);
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );
    DiagnosticsFakeSymconRuntime::setRequestActionCallback(
        static function (int $variableID, mixed $value) use ($setup): void {
            if ($variableID === $setup['fixture']['ids']['brightnessAction']) {
                supersedeRegisteredCommand($setup, 'brightness');
                throw new RuntimeException('Simulated action exception.');
            }
        }
    );

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID,
        '55'
    );

    assertDispatchSame('action_failed', $result['status'], 'Superseded action exception was hidden.');
    assertDispatchSame(1, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']), 'Action exception count differs.');
    assertDispatchSame(0, GetValue($setup['reconcile']['diagnostics']['statisticIDs']['SUPERSEDED_COMMANDS']), 'Action exception was superseded.');
};

$tests['keeps arbitration channels independent and bounded'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registry = $setup['reconcile']['diagnostics']['registry'];
    foreach (['power' => 'OFF', 'brightness' => '55', 'rgb' => '1,2,3', 'colorTemperature' => '3000'] as $type => $payload) {
        MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
            $setup['ownerScriptID'],
            $setup['fixture']['configuration'],
            dispatchCommandVariableID($registry, $type),
            $payload
        );
    }
    $arbitration = SAEF_ReadRegistry($setup['reconcile']['diagnostics']['arbitrationRegistryID']);

    assertDispatchSame(4, count($arbitration['channels']), 'Arbitration channel count differs.');
    assertDispatchSame(
        true,
        count($arbitration['channels']) <= count($registry['commandIndex']),
        'Arbitration Registry exceeded the command index.'
    );
};

$tests['rejects invalid arbitration JSON without a device action'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registryID = $setup['reconcile']['diagnostics']['arbitrationRegistryID'];
    SetValue($registryID, '{invalid');
    $commandVariableID = dispatchCommandVariableID(
        $setup['reconcile']['diagnostics']['registry'],
        'brightness'
    );

    assertDispatchThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
            $setup['ownerScriptID'],
            $setup['fixture']['configuration'],
            $commandVariableID,
            '55'
        ),
        'Invalid arbitration JSON was accepted.'
    );
    assertDispatchSame([], DiagnosticsFakeSymconRuntime::requestActionCalls(), 'Invalid arbitration JSON caused an action.');
};

$tests['prunes stale arbitration metadata without replay'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registryID = $setup['reconcile']['diagnostics']['arbitrationRegistryID'];
    $registry = SAEF_ReadRegistry($registryID);
    $registry['channels'][str_repeat('a', 64)] = [
        'generation' => 9,
        'targetHash' => str_repeat('b', 64),
        'updatedAt' => time(),
    ];
    SAEF_WriteRegistry($registryID, $registry);
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();

    MqttDiscoveryExporterRuntime::prepareReconcile(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration']
    );

    $pruned = SAEF_ReadRegistry($registryID);
    assertDispatchSame(false, isset($pruned['channels'][str_repeat('a', 64)]), 'Stale arbitration slot survived.');
    assertDispatchSame([], DiagnosticsFakeSymconRuntime::requestActionCalls(), 'Arbitration metadata replayed a command.');
};

$tests['accepts brightness feedback within one percentage point'] = static function (): void {
    $setup = initializedDispatchFixture(false);
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'brightness');
    SetValue($setup['fixture']['ids']['brightnessState'], 54);
    SetValue($commandVariableID, '55');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('confirmed', $result['status'], 'Tolerated brightness status differs.');
    assertDispatchSame(54, GetValue($setup['fixture']['ids']['brightnessState']), 'Tolerated brightness feedback changed.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['COMMANDS']),
        'Tolerated brightness command was not counted.'
    );
};

$tests['rejects brightness feedback outside one percentage point'] = static function (): void {
    $setup = initializedDispatchFixture(false);
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'brightness');
    SetValue($setup['fixture']['ids']['brightnessState'], 53);
    SetValue($commandVariableID, '55');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('confirmation_timeout', $result['status'], 'Out-of-range brightness was accepted.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']),
        'Out-of-range brightness failure was not counted.'
    );
};

$tests['accepts color temperature feedback within ten kelvin'] = static function (): void {
    $setup = initializedDispatchFixture(true, false);
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'colorTemperature');
    SetValue($commandVariableID, '3010');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('confirmed', $result['status'], 'Tolerated color-temperature status differs.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['COMMANDS']),
        'Tolerated color-temperature command was not counted.'
    );
};

$tests['rejects color temperature feedback outside ten kelvin'] = static function (): void {
    $setup = initializedDispatchFixture(true, false);
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'colorTemperature');
    SetValue($commandVariableID, '3011');

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('confirmation_timeout', $result['status'], 'Out-of-range color temperature was accepted.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']),
        'Out-of-range color-temperature failure was not counted.'
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

$tests['accepts confirmed feedback when the device action returns false'] = static function (): void {
    $setup = initializedDispatchFixture();
    $registry = $setup['reconcile']['diagnostics']['registry'];
    $commandVariableID = dispatchCommandVariableID($registry, 'colorTemperature');
    SetValue($commandVariableID, '3010');
    DiagnosticsFakeSymconRuntime::failRequestActionAfterFeedbackAt(1);

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $commandVariableID
    );

    assertDispatchSame('confirmed', $result['status'], 'Confirmed false-return action status differs.');
    assertDispatchSame(
        1,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['COMMANDS']),
        'Confirmed false-return action was not counted.'
    );
    assertDispatchSame(
        0,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']),
        'Confirmed false-return action was counted as failure.'
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

$tests['coalesces a state trigger while command dispatch owns the lock'] = static function (): void {
    $setup = initializedDispatchFixture();
    DiagnosticsFakeSymconRuntime::setSemaphoreEnterResult(false);

    $result = MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
        $setup['ownerScriptID'],
        $setup['fixture']['configuration'],
        $setup['fixture']['ids']['colorTemperatureState']
    );

    assertDispatchSame('coalesced', $result['status'], 'Contended state status differs.');
    assertDispatchSame(0, $result['publishedMessages'], 'Contended state was published.');
    assertDispatchSame(
        0,
        GetValue($setup['reconcile']['diagnostics']['statisticIDs']['FAILURES']),
        'Contended state was counted as failure.'
    );
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
