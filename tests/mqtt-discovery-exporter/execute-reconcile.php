<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;
use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

require_once __DIR__ . '/DiagnosticsFakeSymconRuntime.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';

/** @return array<string, mixed> */
function executableReconcileConfiguration(int $gatewayID, string $transport = 'server'): array
{
    $powerState = DiagnosticsFakeSymconRuntime::createStateVariable(0, true);
    $powerAction = DiagnosticsFakeSymconRuntime::createStateVariable(0, false, true);
    $brightnessState = DiagnosticsFakeSymconRuntime::createStateVariable(1, 42);
    $brightnessAction = DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true);
    $rgbState = DiagnosticsFakeSymconRuntime::createStateVariable(1, 0x123456);
    $rgbAction = DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true);
    $colorTemperatureState = DiagnosticsFakeSymconRuntime::createStateVariable(1, 3000);
    $colorTemperatureAction = DiagnosticsFakeSymconRuntime::createStateVariable(1, 2200, true);

    return MqttDiscoveryExporterCore::normalizeConfiguration([
        'version' => 'candidate-execution-test',
        'location' => 'test_site',
        'mqtt' => [
            'transport' => $transport,
            'gatewayID' => $gatewayID,
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
                'stateID' => $powerState,
                'actionID' => $powerAction,
                'brightnessStateID' => $brightnessState,
                'brightnessActionID' => $brightnessAction,
                'colorStateID' => $rgbState,
                'colorActionID' => $rgbAction,
                'colorTempStateID' => $colorTemperatureState,
                'colorTempActionID' => $colorTemperatureAction,
                'minKelvin' => 2200,
                'maxKelvin' => 6500,
            ]],
        ]],
    ]);
}

function assertExecutionSame(mixed $expected, mixed $actual, string $message): void
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

function assertExecutionTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $expectedClass */
function assertExecutionThrows(string $expectedClass, callable $operation, string $message): void
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

/** @return array{registryID: int, errorsID: int, failuresID: int, successesID: int, publishesID: int} */
function executionDiagnosticIDs(int $ownerScriptID): array
{
    $categoryID = IPS_GetObjectIDByIdent('MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS', $ownerScriptID);
    if ($categoryID === false) {
        throw new RuntimeException('Diagnostics category is missing.');
    }

    $ids = [];
    foreach (
        [
            'registryID' => 'MANAGED_STATE_REGISTRY',
            'errorsID' => 'ERROR_HISTORY',
            'failuresID' => 'FAILURES',
            'successesID' => 'SUCCESSES',
            'publishesID' => 'PUBLISHES',
        ] as $key => $ident
    ) {
        $objectID = IPS_GetObjectIDByIdent($ident, $categoryID);
        if ($objectID === false) {
            throw new RuntimeException('Diagnostic variable is missing: ' . $ident);
        }
        $ids[$key] = $objectID;
    }

    return $ids;
}

$tests = [];

$tests['publishes through deterministic retained topic adapters'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = executableReconcileConfiguration($serverID);
    $result = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup(
        $ownerScriptID,
        $configuration
    );

    assertExecutionSame(
        ['publishers' => 6, 'publishedMessages' => 6, 'skippedChannels' => 0],
        $result['summary'],
        'Execution summary differs.'
    );
    assertExecutionSame(11, DiagnosticsFakeSymconRuntime::instanceCount(), 'Publisher count differs.');
    assertExecutionSame(6, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Publish calls differ.');
    foreach (DiagnosticsFakeSymconRuntime::requestActionCalls() as $call) {
        assertExecutionTrue(is_string($call['value']), 'MQTT payload was not sent as a string.');
    }

    $retainCounts = [true => 0, false => 0];
    foreach (DiagnosticsFakeSymconRuntime::instances() as $instance) {
        if ($instance['ModuleInfo']['ModuleID'] !== '{01C00ADD-D04E-452E-B66A-D253278743FE}') {
            continue;
        }
        $adapterConfiguration = json_decode($instance['Configuration'], true, 512, JSON_THROW_ON_ERROR);
        $retainCounts[$adapterConfiguration['Retain']]++;
    }
    assertExecutionSame(6, $retainCounts[true], 'Retained publisher adapter count differs.');
    assertExecutionSame(4, $retainCounts[false], 'Non-retained command adapter count differs.');

    $entry = $result['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];
    assertExecutionSame(
        6,
        count(IPS_GetChildrenIDs($entry['publisherParentCategoryID'])),
        'Publisher adapters are not grouped below the device publisher category.'
    );
    foreach ($result['diagnostics']['registry']['publishers'] as $publisher) {
        assertExecutionSame(
            $entry['publisherParentCategoryID'],
            IPS_GetObject($publisher['instanceID'])['ParentID'],
            'Publisher parent differs from the managed device category.'
        );
    }
    assertExecutionSame($entry['desiredDiscoveryHash'], $entry['discoveryHash'], 'Discovery hash was not committed.');
    assertExecutionSame($entry['desiredRuntimeHash'], $entry['runtimeHash'], 'Runtime hash was not committed.');
    assertExecutionSame(1, GetValue($result['diagnostics']['statisticIDs']['SUCCESSES']), 'Success count differs.');
    assertExecutionSame(6, GetValue($result['diagnostics']['statisticIDs']['PUBLISHES']), 'Publish count differs.');
};

$tests['publishes through MQTT Client Device adapters'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $clientID = DiagnosticsFakeSymconRuntime::createClientInstance();
    $configuration = executableReconcileConfiguration($clientID, 'client');
    $result = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup(
        $ownerScriptID,
        $configuration
    );

    assertExecutionSame(
        ['publishers' => 6, 'publishedMessages' => 6, 'skippedChannels' => 0],
        $result['summary'],
        'Client execution summary differs.'
    );
    assertExecutionSame(6, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Client publish calls differ.');

    $clientAdapterCount = 0;
    foreach (DiagnosticsFakeSymconRuntime::instances() as $instance) {
        if ($instance['ModuleInfo']['ModuleID'] !== '{91D174F2-AE0F-B8D8-5EF4-6232B9083CCF}') {
            continue;
        }
        $clientAdapterCount++;
        assertExecutionSame($clientID, $instance['ConnectionID'], 'Client publisher connection differs.');
    }
    assertExecutionSame(10, $clientAdapterCount, 'Client command and publisher adapter count differs.');
};

$tests['skips unchanged channels without republishing'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = executableReconcileConfiguration($serverID);
    MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    $second = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);

    assertExecutionSame(
        ['publishers' => 6, 'publishedMessages' => 0, 'skippedChannels' => 2],
        $second['summary'],
        'Unchanged execution summary differs.'
    );
    assertExecutionSame(6, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Unchanged state was republished.');
    assertExecutionSame(2, GetValue($second['diagnostics']['statisticIDs']['SUCCESSES']), 'Success count differs.');
    assertExecutionSame(2, GetValue($second['diagnostics']['statisticIDs']['PUBLISH_SKIPS']), 'Skip count differs.');
};

$tests['commits hashes only after each channel succeeds'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = executableReconcileConfiguration($serverID);
    DiagnosticsFakeSymconRuntime::failRequestActionAt(3);

    assertExecutionThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup(
            $ownerScriptID,
            $configuration
        ),
        'Failed MQTT action was accepted.'
    );

    $ids = executionDiagnosticIDs($ownerScriptID);
    $registry = SAEF_ReadRegistry($ids['registryID']);
    $entry = $registry['managedEntities']['example_lamp.main_light'];
    assertExecutionSame(true, $entry['discoveryPublished'], 'Successful discovery channel was not committed.');
    assertExecutionSame(false, $entry['runtimePublished'], 'Partial runtime channel was committed.');
    assertExecutionSame(2, GetValue($ids['publishesID']), 'Successful partial publish count differs.');
    assertExecutionSame(1, GetValue($ids['failuresID']), 'Failure count differs.');
    assertExecutionSame(0, GetValue($ids['successesID']), 'Failed execution counted as success.');
    $errors = SAEF_ReadErrorRingBuffer($ids['errorsID']);
    assertExecutionSame('reconcile_publish', $errors[0]['context']['phase'], 'Failure phase differs.');

    DiagnosticsFakeSymconRuntime::failRequestActionAt(null);
    $retry = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    assertExecutionSame(5, $retry['summary']['publishedMessages'], 'Retry did not publish the complete runtime channel.');
    assertExecutionSame(1, $retry['summary']['skippedChannels'], 'Retry did not skip committed discovery.');
    assertExecutionSame(1, GetValue($ids['successesID']), 'Successful retry count differs.');
    assertExecutionSame(7, GetValue($ids['publishesID']), 'Cumulative successful publish count differs.');
};

$tests['records preparation failures once during execution'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = executableReconcileConfiguration($serverID);
    $configuration['devices'][0]['entities'][0]['capabilities']['power']['stateVariableID'] = 9999;

    assertExecutionThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup(
            $ownerScriptID,
            $configuration
        ),
        'Missing state variable was accepted.'
    );

    $ids = executionDiagnosticIDs($ownerScriptID);
    assertExecutionSame(1, GetValue($ids['failuresID']), 'Preparation failure was counted more than once.');
    $errors = SAEF_ReadErrorRingBuffer($ids['errorsID']);
    assertExecutionSame(1, count($errors), 'Preparation failure was recorded more than once.');
    assertExecutionSame('reconcile_publish', $errors[0]['context']['phase'], 'Failure phase differs.');
};

$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Execute reconcile test failed: %s\n%s\n", $name, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("MQTT Discovery Exporter execute reconcile tests passed: %d.\n", $passed));
