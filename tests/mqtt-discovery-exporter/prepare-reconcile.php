<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;
use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

require_once __DIR__ . '/DiagnosticsFakeSymconRuntime.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';

/**
 * @return array{configuration: array<string, mixed>, ids: array<string, int>}
 */
function reconcileConfiguration(
    int $gatewayID,
    bool $powerHasAction = true,
    bool $fullLight = true,
    string $transport = 'server'
): array {
    $ids = [
        'powerState' => DiagnosticsFakeSymconRuntime::createStateVariable(0, true),
        'powerAction' => DiagnosticsFakeSymconRuntime::createStateVariable(0, false, $powerHasAction),
    ];
    $entity = [
        'id' => 'main_light',
        'class' => 'light',
        'name' => 'Main Light',
        'stateID' => $ids['powerState'],
        'actionID' => $ids['powerAction'],
    ];

    if ($fullLight) {
        $ids['brightnessState'] = DiagnosticsFakeSymconRuntime::createStateVariable(1, 42);
        $ids['brightnessAction'] = DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true);
        $ids['rgbState'] = DiagnosticsFakeSymconRuntime::createStateVariable(1, 0x123456);
        $ids['rgbAction'] = DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true);
        $ids['colorTemperatureState'] = DiagnosticsFakeSymconRuntime::createStateVariable(1, 3000);
        $ids['colorTemperatureAction'] = DiagnosticsFakeSymconRuntime::createStateVariable(1, 2200, true);
        $entity += [
            'brightnessStateID' => $ids['brightnessState'],
            'brightnessActionID' => $ids['brightnessAction'],
            'colorStateID' => $ids['rgbState'],
            'colorActionID' => $ids['rgbAction'],
            'colorTempStateID' => $ids['colorTemperatureState'],
            'colorTempActionID' => $ids['colorTemperatureAction'],
            'minKelvin' => 2200,
            'maxKelvin' => 6500,
        ];
    }

    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration([
        'version' => 'candidate-reconcile-test',
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
            'entities' => [$entity],
        ]],
    ]);

    return ['configuration' => $configuration, 'ids' => $ids];
}

function assertReconcileSame(mixed $expected, mixed $actual, string $message): void
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

function assertReconcileTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $expectedClass */
function assertReconcileThrows(string $expectedClass, callable $operation, string $message): void
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

$tests['validates and prepares complete reconcile resources'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $fixture = reconcileConfiguration($serverID);
    $result = MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $fixture['configuration']);

    assertReconcileSame(
        ['entities' => 1, 'commandAdapters' => 4, 'stateEvents' => 4, 'messagesToPublish' => 6],
        $result['summary'],
        'Reconcile summary differs.'
    );
    assertReconcileSame(5, DiagnosticsFakeSymconRuntime::instanceCount(), 'Unexpected instance count.');
    assertReconcileSame(8, DiagnosticsFakeSymconRuntime::eventCount(), 'Unexpected event count.');
    assertReconcileSame(5, DiagnosticsFakeSymconRuntime::categoryCount(), 'Visible resource categories differ.');
    assertReconcileSame(4, count($result['diagnostics']['registry']['commandIndex']), 'Command index differs.');
    assertReconcileSame(4, count($result['diagnostics']['registry']['stateIndex']), 'State index differs.');

    $plan = $result['publishPlan']['example_lamp.main_light'];
    assertReconcileSame(true, $plan['discoveryChanged'], 'Initial discovery was not planned.');
    assertReconcileSame(true, $plan['runtimeChanged'], 'Initial runtime state was not planned.');
    assertReconcileSame(1, count($plan['discoveryMessages']), 'Discovery message count differs.');
    assertReconcileSame(5, count($plan['runtimeMessages']), 'Runtime message count differs.');
    assertReconcileTrue(
        str_starts_with($plan['discoveryMessages'][0]['topic'], 'homeassistant/light/'),
        'Discovery topic differs.'
    );

    $commandTopics = [];
    foreach (DiagnosticsFakeSymconRuntime::instances() as $instance) {
        if ($instance['ModuleInfo']['ModuleID'] !== '{01C00ADD-D04E-452E-B66A-D253278743FE}') {
            continue;
        }
        $adapterConfiguration = json_decode($instance['Configuration'], true, 512, JSON_THROW_ON_ERROR);
        assertReconcileSame($serverID, $instance['ConnectionID'], 'Command adapter connection differs.');
        assertReconcileSame(3, $adapterConfiguration['Type'], 'Command adapter type differs.');
        assertReconcileSame(false, $adapterConfiguration['Retain'], 'Command adapter retain differs.');
        $commandTopics[] = $adapterConfiguration['Topic'];
    }
    assertReconcileSame(4, count(array_unique($commandTopics)), 'Command topics are not unique.');

    $triggerTypes = array_count_values(array_column(DiagnosticsFakeSymconRuntime::events(), 'TriggerType'));
    assertReconcileSame(4, $triggerTypes[0] ?? 0, 'Command update trigger count differs.');
    assertReconcileSame(4, $triggerTypes[1] ?? 0, 'State change trigger count differs.');
    foreach (DiagnosticsFakeSymconRuntime::events() as $event) {
        assertReconcileSame(
            SAEF_RUN_AUTOMATION_ACTION_GUID,
            $event['ActionID'],
            'Event action binding differs.'
        );
    }

    $devicesID = IPS_GetObjectIDByIdent('MQTT_DISCOVERY_EXPORTER_DEVICES', $ownerScriptID);
    assertReconcileTrue(is_int($devicesID), 'Devices category is missing.');
    $deviceIdent = 'DEVICE_' . strtoupper(substr(hash('sha256', 'example_lamp'), 0, 16));
    $deviceID = IPS_GetObjectIDByIdent($deviceIdent, $devicesID);
    assertReconcileTrue(is_int($deviceID), 'Device category is missing.');
    $commandsID = IPS_GetObjectIDByIdent('COMMANDS', $deviceID);
    $publishersID = IPS_GetObjectIDByIdent('PUBLISHERS', $deviceID);
    assertReconcileTrue(is_int($commandsID) && is_int($publishersID), 'Device subcategories are missing.');
    assertReconcileSame(4, count(IPS_GetChildrenIDs($commandsID)), 'Command adapters are not grouped by device.');
    assertReconcileSame(0, count(IPS_GetChildrenIDs($publishersID)), 'Preparation created publisher adapters.');
    foreach (array_keys(DiagnosticsFakeSymconRuntime::events()) as $eventID) {
        assertReconcileSame(
            $ownerScriptID,
            IPS_GetObject($eventID)['ParentID'],
            'Hidden event is not a direct child of the owner script.'
        );
    }
};

$tests['prepares command resources through an MQTT Client gateway'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $clientID = DiagnosticsFakeSymconRuntime::createClientInstance(
        subscriptions: [['Topic' => 'saef/export/test_site/#', 'QoS' => 0]]
    );
    $fixture = reconcileConfiguration($clientID, true, true, 'client');
    $result = MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $fixture['configuration']);

    assertReconcileSame(
        ['entities' => 1, 'commandAdapters' => 4, 'stateEvents' => 4, 'messagesToPublish' => 6],
        $result['summary'],
        'Client reconcile summary differs.'
    );
    $clientAdapterCount = 0;
    foreach (DiagnosticsFakeSymconRuntime::instances() as $instance) {
        if ($instance['ModuleInfo']['ModuleID'] !== '{91D174F2-AE0F-B8D8-5EF4-6232B9083CCF}') {
            continue;
        }
        $clientAdapterCount++;
        assertReconcileSame($clientID, $instance['ConnectionID'], 'Client adapter connection differs.');
    }
    assertReconcileSame(4, $clientAdapterCount, 'Client adapter count differs.');

    $entry = $result['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];
    assertReconcileSame(
        '{91D174F2-AE0F-B8D8-5EF4-6232B9083CCF}',
        $entry['adapterModuleID'],
        'Managed client adapter module differs.'
    );
};

$tests['rejects uncovered MQTT Client command topics before adapters are created'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $clientID = DiagnosticsFakeSymconRuntime::createClientInstance(
        subscriptions: [['Topic' => 'saef/other_site/#', 'QoS' => 0]]
    );
    $fixture = reconcileConfiguration($clientID, true, true, 'client');

    assertReconcileThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::prepareReconcile(
            $ownerScriptID,
            $fixture['configuration']
        ),
        'Uncovered MQTT Client command topics were accepted.'
    );

    assertReconcileSame(1, DiagnosticsFakeSymconRuntime::instanceCount(), 'Coverage failure created adapters.');
    assertReconcileSame(0, DiagnosticsFakeSymconRuntime::eventCount(), 'Coverage failure created events.');
};

$tests['accepts single-level MQTT Client subscription wildcards'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $clientID = DiagnosticsFakeSymconRuntime::createClientInstance(
        subscriptions: [
            ['Topic' => 'saef/+/test_site/light/example_lamp/set', 'QoS' => 0],
            ['Topic' => 'saef/+/test_site/light/example_lamp/+/set', 'QoS' => 0],
        ]
    );
    $fixture = reconcileConfiguration($clientID, true, true, 'client');
    $result = MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $fixture['configuration']);

    assertReconcileSame(4, $result['summary']['commandAdapters'], 'Wildcard coverage was not accepted.');
};

$tests['updates MQTT runtime namespaces in place while cleanup is disabled'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $clientID = DiagnosticsFakeSymconRuntime::createClientInstance(
        subscriptions: [['Topic' => 'saef/#', 'QoS' => 0]]
    );
    $fixture = reconcileConfiguration($clientID, true, true, 'client');
    $first = MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $fixture['configuration']);
    $firstEntry = $first['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];

    $migratedConfiguration = $fixture['configuration'];
    $migratedConfiguration['mqtt']['baseTopic'] = 'saef/migrated';
    $second = MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $migratedConfiguration);
    $secondEntry = $second['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];

    assertReconcileSame(5, DiagnosticsFakeSymconRuntime::instanceCount(), 'Namespace migration recreated adapters.');
    assertReconcileSame(8, DiagnosticsFakeSymconRuntime::eventCount(), 'Namespace migration recreated events.');
    assertReconcileSame(
        $firstEntry['commandInstanceIDs'],
        $secondEntry['commandInstanceIDs'],
        'Namespace migration changed command adapter identities.'
    );
    assertReconcileSame(
        $firstEntry['commandEventIDs'],
        $secondEntry['commandEventIDs'],
        'Namespace migration changed command event identities.'
    );
    foreach ($secondEntry['commandInstanceIDs'] as $commandType => $instanceID) {
        $adapterConfiguration = json_decode(
            IPS_GetConfiguration($instanceID),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        assertReconcileSame(
            $secondEntry['commandTopics'][$commandType],
            $adapterConfiguration['Topic'] ?? null,
            'Namespace migration did not update an adapter topic.'
        );
        assertReconcileTrue(
            str_starts_with($adapterConfiguration['Topic'], 'saef/migrated/'),
            'Namespace migration retained an old adapter topic.'
        );
    }
};

$tests['rejects malformed MQTT Client subscription wildcards'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $clientID = DiagnosticsFakeSymconRuntime::createClientInstance(
        subscriptions: [['Topic' => 'saef/export/#/set', 'QoS' => 0]]
    );
    $fixture = reconcileConfiguration($clientID, true, true, 'client');

    assertReconcileThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::prepareReconcile(
            $ownerScriptID,
            $fixture['configuration']
        ),
        'Malformed MQTT Client subscription wildcard was accepted.'
    );

    assertReconcileSame(1, DiagnosticsFakeSymconRuntime::instanceCount(), 'Wildcard failure created adapters.');
    assertReconcileSame(0, DiagnosticsFakeSymconRuntime::eventCount(), 'Wildcard failure created events.');
};

$tests['repeated preparation is object-idempotent'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $fixture = reconcileConfiguration($serverID);
    $first = MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $fixture['configuration']);
    $devicesID = IPS_GetObjectIDByIdent('MQTT_DISCOVERY_EXPORTER_DEVICES', $ownerScriptID);
    assertReconcileTrue(is_int($devicesID), 'Devices category is missing before presentation test.');
    IPS_SetName($devicesID, 'Meine Geräte');
    IPS_SetPosition($devicesID, 77);
    $entry = $first['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];
    $commandInstanceID = reset($entry['commandInstanceIDs']);
    $commandEventID = reset($entry['commandEventIDs']);
    assertReconcileTrue(is_int($commandInstanceID) && is_int($commandEventID), 'Managed resources are missing.');
    IPS_SetName($commandInstanceID, 'Mein MQTT-Befehl');
    IPS_SetPosition($commandInstanceID, 78);
    IPS_SetName($commandEventID, 'Mein verborgenes Ereignis');
    $second = MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $fixture['configuration']);

    assertReconcileSame(5, DiagnosticsFakeSymconRuntime::instanceCount(), 'Repeated preparation created instances.');
    assertReconcileSame(8, DiagnosticsFakeSymconRuntime::eventCount(), 'Repeated preparation created events.');
    assertReconcileSame(
        array_keys($first['diagnostics']['registry']['commandIndex']),
        array_keys($second['diagnostics']['registry']['commandIndex']),
        'Command adapter identities changed.'
    );
    assertReconcileSame('Meine Geräte', IPS_GetObject($devicesID)['ObjectName'], 'User category name was overwritten.');
    assertReconcileSame(77, IPS_GetObject($devicesID)['ObjectPosition'], 'User category position was overwritten.');
    assertReconcileSame(
        'Mein MQTT-Befehl',
        IPS_GetObject($commandInstanceID)['ObjectName'],
        'User MQTT-device name was overwritten.'
    );
    assertReconcileSame(78, IPS_GetObject($commandInstanceID)['ObjectPosition'], 'User MQTT-device position was overwritten.');
    assertReconcileSame(
        'Mein verborgenes Ereignis',
        IPS_GetObject($commandEventID)['ObjectName'],
        'User event name was overwritten.'
    );
};

$tests['rejects missing actions before command resources are created'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $fixture = reconcileConfiguration($serverID, false);

    assertReconcileThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::prepareReconcile(
            $ownerScriptID,
            $fixture['configuration']
        ),
        'Actionless command variable was accepted.'
    );

    assertReconcileSame(1, DiagnosticsFakeSymconRuntime::instanceCount(), 'Validation failure created adapters.');
    assertReconcileSame(0, DiagnosticsFakeSymconRuntime::eventCount(), 'Validation failure created events.');
};

$tests['rejects incompatible variable types before command resources are created'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $fixture = reconcileConfiguration($serverID);
    $fixture['configuration']['devices'][0]['entities'][0]['capabilities']['brightness']['stateVariableID'] =
        DiagnosticsFakeSymconRuntime::createStateVariable(3, '42');

    assertReconcileThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::prepareReconcile(
            $ownerScriptID,
            $fixture['configuration']
        ),
        'String brightness state was accepted.'
    );

    assertReconcileSame(1, DiagnosticsFakeSymconRuntime::instanceCount(), 'Type failure created adapters.');
    assertReconcileSame(0, DiagnosticsFakeSymconRuntime::eventCount(), 'Type failure created events.');
};

$tests['blocks capability contraction while cleanup is disabled'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $full = reconcileConfiguration($serverID, true, true);
    MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $full['configuration']);

    $powerOnly = $full['configuration'];
    $powerOnly['devices'][0]['entities'][0]['capabilities'] = [
        'power' => $powerOnly['devices'][0]['entities'][0]['capabilities']['power'],
    ];

    assertReconcileThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::prepareReconcile($ownerScriptID, $powerOnly),
        'Capability contraction was accepted without cleanup.'
    );

    assertReconcileSame(5, DiagnosticsFakeSymconRuntime::instanceCount(), 'Cleanup gate changed instances.');
    assertReconcileSame(8, DiagnosticsFakeSymconRuntime::eventCount(), 'Cleanup gate changed events.');
};

$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Prepare reconcile test failed: %s\n%s\n", $name, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("MQTT Discovery Exporter prepare reconcile tests passed: %d.\n", $passed));
