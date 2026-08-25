<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

require_once __DIR__ . '/DiagnosticsFakeSymconRuntime.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';

/** @return array<string, mixed> */
function runtimeDiagnosticsConfiguration(string $version = 'candidate-test-1'): array
{
    return [
        'version' => $version,
        'location' => 'test_site',
        'uuidNamespace' => '36d5dfd1-d837-4e5f-8d67-0f41e3f0f2a1',
        'mqtt' => [
            'serverID' => 1234,
            'baseTopic' => 'saef/export',
            'discoveryPrefix' => 'homeassistant',
        ],
        'defaults' => [
            'qos' => 0,
            'retain' => true,
        ],
        'devices' => [[
            'id' => 'example_switch',
            'entities' => [[
                'id' => 'main',
                'class' => 'switch',
                'capabilities' => [
                    'power' => [
                        'stateVariableID' => 1001,
                        'actionVariableID' => 1002,
                        'invert' => false,
                    ],
                ],
            ]],
        ]],
    ];
}

function assertRuntimeSame(mixed $expected, mixed $actual, string $message): void
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

function assertRuntimeTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $expectedClass */
function assertRuntimeThrows(string $expectedClass, callable $operation, string $message): void
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

$tests['creates the complete owned diagnostics structure'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $configuration = runtimeDiagnosticsConfiguration();
    $diagnostics = MqttDiscoveryExporterRuntime::initializeDiagnostics($ownerScriptID, $configuration);

    assertRuntimeSame(1, DiagnosticsFakeSymconRuntime::categoryCount(), 'Unexpected category count.');
    assertRuntimeSame(13, DiagnosticsFakeSymconRuntime::variableCount(), 'Unexpected variable count.');
    assertRuntimeSame(
        [
            'EXECUTIONS',
            'SUCCESSES',
            'FAILURES',
            'COMMANDS',
            'PUBLISHES',
            'PUBLISH_SKIPS',
            'SUPERSEDED_COMMANDS',
            'LAST_RUN',
            'LAST_SUCCESS',
            'LAST_FAILURE',
        ],
        array_keys($diagnostics['statisticIDs']),
        'Statistic contract differs.'
    );
    assertRuntimeSame(1, $diagnostics['registry']['schemaVersion'], 'Registry schema differs.');
    assertRuntimeSame(
        SAEF_CreateConfigurationHash($configuration),
        $diagnostics['registry']['configurationHash'],
        'Configuration hash differs.'
    );
    assertRuntimeSame([], $diagnostics['registry']['managedEntities'], 'Managed entities are not initially empty.');
    assertRuntimeSame(
        ['schemaVersion' => 1, 'channels' => []],
        $diagnostics['arbitrationRegistry'],
        'Command arbitration Registry differs.'
    );
};

$tests['is idempotent and avoids unchanged registry writes'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $configuration = runtimeDiagnosticsConfiguration();
    $first = MqttDiscoveryExporterRuntime::initializeDiagnostics($ownerScriptID, $configuration);
    $writesAfterFirstRun = DiagnosticsFakeSymconRuntime::valueWriteCount();
    IPS_SetName($first['categoryID'], 'Meine Diagnose');
    IPS_SetPosition($first['categoryID'], 81);
    IPS_SetName($first['registryID'], 'Mein Register');
    IPS_SetPosition($first['registryID'], 82);
    $second = MqttDiscoveryExporterRuntime::initializeDiagnostics($ownerScriptID, $configuration);

    assertRuntimeSame($first['categoryID'], $second['categoryID'], 'Diagnostics category identity changed.');
    assertRuntimeSame($first['registryID'], $second['registryID'], 'Registry identity changed.');
    assertRuntimeSame(13, DiagnosticsFakeSymconRuntime::variableCount(), 'Repeated setup created variables.');
    assertRuntimeSame(
        $writesAfterFirstRun,
        DiagnosticsFakeSymconRuntime::valueWriteCount(),
        'Unchanged registry was written again.'
    );
    assertRuntimeSame('Meine Diagnose', IPS_GetObject($first['categoryID'])['ObjectName'], 'Diagnostics name was overwritten.');
    assertRuntimeSame(81, IPS_GetObject($first['categoryID'])['ObjectPosition'], 'Diagnostics position was overwritten.');
    assertRuntimeSame('Mein Register', IPS_GetObject($first['registryID'])['ObjectName'], 'Registry name was overwritten.');
    assertRuntimeSame(82, IPS_GetObject($first['registryID'])['ObjectPosition'], 'Registry position was overwritten.');
};

$tests['preserves managed state and advances configuration history once'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $first = MqttDiscoveryExporterRuntime::initializeDiagnostics(
        $ownerScriptID,
        runtimeDiagnosticsConfiguration('candidate-test-1')
    );
    $registry = SAEF_ReadRegistry($first['registryID']);
    $registry['managedEntities']['example_switch.main'] = ['schemaVersion' => 1];
    SAEF_WriteRegistry($first['registryID'], $registry);

    $second = MqttDiscoveryExporterRuntime::initializeDiagnostics(
        $ownerScriptID,
        runtimeDiagnosticsConfiguration('candidate-test-2')
    );
    $third = MqttDiscoveryExporterRuntime::initializeDiagnostics(
        $ownerScriptID,
        runtimeDiagnosticsConfiguration('candidate-test-2')
    );

    assertRuntimeSame(
        $first['configurationHash'],
        $second['registry']['previousConfigurationHash'],
        'Previous configuration hash was not retained.'
    );
    assertRuntimeSame(
        $first['configurationHash'],
        $third['registry']['previousConfigurationHash'],
        'Unchanged configuration advanced hash history again.'
    );
    assertRuntimeTrue(
        isset($third['registry']['managedEntities']['example_switch.main']),
        'Managed entity metadata was discarded.'
    );
};

$tests['records failures after diagnostics storage exists'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $diagnostics = MqttDiscoveryExporterRuntime::initializeDiagnostics(
        $ownerScriptID,
        runtimeDiagnosticsConfiguration()
    );
    $registry = SAEF_ReadRegistry($diagnostics['registryID']);
    $registry['schemaVersion'] = 999;
    SAEF_WriteRegistry($diagnostics['registryID'], $registry);

    assertRuntimeThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::initializeDiagnostics(
            $ownerScriptID,
            runtimeDiagnosticsConfiguration()
        ),
        'Unsupported registry schema was accepted.'
    );

    $errors = SAEF_ReadErrorRingBuffer($diagnostics['errorRingBufferID']);
    assertRuntimeSame(1, count($errors), 'Initialization failure was not recorded once.');
    assertRuntimeSame(
        'diagnostics_initialization',
        $errors[0]['context']['phase'],
        'Error phase differs.'
    );
    assertRuntimeSame(
        1,
        GetValue($diagnostics['statisticIDs']['FAILURES']),
        'Failure counter differs.'
    );
    assertRuntimeTrue(
        GetValue($diagnostics['statisticIDs']['LAST_FAILURE']) > 0,
        'Last failure timestamp was not set.'
    );
};

$tests['rejects a missing owner before creating diagnostics'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();

    assertRuntimeThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::initializeDiagnostics(
            9999,
            runtimeDiagnosticsConfiguration()
        ),
        'Missing owner script was accepted.'
    );

    assertRuntimeSame(0, DiagnosticsFakeSymconRuntime::categoryCount(), 'Invalid owner created a category.');
    assertRuntimeSame(1, count(DiagnosticsFakeSymconRuntime::logs()), 'Early failure was not logged once.');
};

$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Runtime diagnostics test failed: %s\n%s\n", $name, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("MQTT Discovery Exporter runtime diagnostics tests passed: %d.\n", $passed));
