<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;
use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

require_once __DIR__ . '/DiagnosticsFakeSymconRuntime.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';

/** @return array<string, mixed> */
function cleanupConfiguration(
    int $gatewayID,
    bool $fullLight = true,
    bool $includeSpare = false,
    string $transport = 'server'
): array {
    $powerState = DiagnosticsFakeSymconRuntime::createStateVariable(0, true);
    $powerAction = DiagnosticsFakeSymconRuntime::createStateVariable(0, false, true);
    $entity = [
        'id' => 'main_light',
        'class' => 'light',
        'name' => 'Main Light',
        'stateID' => $powerState,
        'actionID' => $powerAction,
    ];

    if ($fullLight) {
        $entity += [
            'brightnessStateID' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 42),
            'brightnessActionID' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true),
            'colorStateID' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 0x123456),
            'colorActionID' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 0, true),
            'colorTempStateID' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 3000),
            'colorTempActionID' => DiagnosticsFakeSymconRuntime::createStateVariable(1, 2200, true),
            'minKelvin' => 2200,
            'maxKelvin' => 6500,
        ];
    }

    $entities = [$entity];
    if ($includeSpare) {
        $entities[] = [
            'id' => 'spare_switch',
            'class' => 'switch',
            'name' => 'Spare Switch',
            'stateID' => DiagnosticsFakeSymconRuntime::createStateVariable(0, false),
            'actionID' => DiagnosticsFakeSymconRuntime::createStateVariable(0, false, true),
        ];
    }

    return MqttDiscoveryExporterCore::normalizeConfiguration([
        'version' => 'candidate-cleanup-test',
        'location' => 'test_site',
        'mqtt' => [
            'transport' => $transport,
            'gatewayID' => $gatewayID,
            'baseTopic' => 'saef/export',
            'discoveryPrefix' => 'homeassistant',
        ],
        'defaults' => ['qos' => 0, 'retain' => true],
        'devices' => [[
            'id' => 'example_lamp',
            'topic' => 'example_lamp',
            'name' => 'Example Lamp',
            'entities' => $entities,
        ]],
    ]);
}

/** @param array<string, mixed> $configuration @return array<string, mixed> */
function withoutCleanupEntities(array $configuration): array
{
    array_shift($configuration['devices'][0]['entities']);

    return $configuration;
}

function assertCleanupSame(mixed $expected, mixed $actual, string $message): void
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

function assertCleanupTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $expectedClass */
function assertCleanupThrows(string $expectedClass, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) {
            return;
        }
        throw new RuntimeException($message . ' Unexpected exception: ' . $exception->getMessage());
    }

    throw new RuntimeException($message . ' Expected exception was not thrown.');
}

$tests = [];

$tests['requires cleanup for a server-to-client transport migration'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $serverConfiguration = cleanupConfiguration($serverID);
    MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $serverConfiguration);

    $clientID = DiagnosticsFakeSymconRuntime::createClientInstance();
    $clientConfiguration = $serverConfiguration;
    $clientConfiguration['mqtt']['transport'] = 'client';
    $clientConfiguration['mqtt']['gatewayID'] = $clientID;

    assertCleanupThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup(
            $ownerScriptID,
            $clientConfiguration
        ),
        'Transport migration was accepted without cleanup.'
    );

    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();
    $result = MqttDiscoveryExporterRuntime::executeReconcileWithCleanup(
        $ownerScriptID,
        $clientConfiguration
    );
    assertCleanupSame(1, $result['cleanup']['entities'], 'Transport migration did not replace the entity.');

    $clientAdapterCount = 0;
    foreach (DiagnosticsFakeSymconRuntime::instances() as $instance) {
        if ($instance['ModuleInfo']['ModuleID'] === '{01C00ADD-D04E-452E-B66A-D253278743FE}') {
            throw new RuntimeException('Server adapter remains after client migration.');
        }
        if ($instance['ModuleInfo']['ModuleID'] !== '{91D174F2-AE0F-B8D8-5EF4-6232B9083CCF}') {
            continue;
        }
        $clientAdapterCount++;
        assertCleanupSame($clientID, $instance['ConnectionID'], 'Migrated client connection differs.');
    }
    assertCleanupSame(10, $clientAdapterCount, 'Migrated client adapter count differs.');
};

$tests['removes a complete entity from exact Registry ownership'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = cleanupConfiguration($serverID, true, true);
    MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();

    $result = MqttDiscoveryExporterRuntime::executeReconcileWithCleanup(
        $ownerScriptID,
        withoutCleanupEntities($configuration)
    );

    assertCleanupSame(
        ['entities' => 1, 'events' => 8, 'instances' => 10, 'categories' => 0, 'retainedTopics' => 6],
        $result['cleanup'],
        'Complete cleanup summary differs.'
    );
    assertCleanupSame(6, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Tombstone count differs.');
    foreach (DiagnosticsFakeSymconRuntime::requestActionCalls() as $call) {
        assertCleanupSame('', $call['value'], 'Cleanup payload is not an empty retained tombstone.');
    }
    assertCleanupSame(4, DiagnosticsFakeSymconRuntime::instanceCount(), 'Unrelated instances changed during cleanup.');
    assertCleanupSame(2, DiagnosticsFakeSymconRuntime::eventCount(), 'Unrelated events changed during cleanup.');
    assertCleanupTrue(
        !isset($result['diagnostics']['registry']['managedEntities']['example_lamp.main_light']),
        'Removed entity remains in the Registry.'
    );
    assertCleanupTrue(
        isset($result['diagnostics']['registry']['managedEntities']['example_lamp.spare_switch']),
        'Unrelated entity was removed from the Registry.'
    );
};

$tests['removes the final entity for a complete exporter cleanup'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = cleanupConfiguration($serverID);
    MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();
    $empty = $configuration;
    $empty['devices'] = [];

    $result = MqttDiscoveryExporterRuntime::executeReconcileWithCleanup($ownerScriptID, $empty);

    assertCleanupSame(1, $result['cleanup']['entities'], 'Final entity was not removed.');
    assertCleanupSame(1, DiagnosticsFakeSymconRuntime::instanceCount(), 'Final owned instance remains.');
    assertCleanupSame(0, DiagnosticsFakeSymconRuntime::eventCount(), 'Final owned event remains.');
    assertCleanupSame([], $result['diagnostics']['registry']['managedEntities'], 'Final Registry entity remains.');
    assertCleanupSame([], $result['diagnostics']['registry']['publishers'], 'Final Registry publisher remains.');
    assertCleanupSame(1, DiagnosticsFakeSymconRuntime::categoryCount(), 'Final owned resource categories remain.');
    assertCleanupSame(
        ['devices' => []],
        $result['diagnostics']['registry']['resourceTree'],
        'Final Registry resource tree remains.'
    );
};

$tests['replaces a contracted capability set and republishes desired state'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $full = cleanupConfiguration($serverID, true);
    MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $full);
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();
    $powerOnly = cleanupConfiguration($serverID, false);

    $result = MqttDiscoveryExporterRuntime::executeReconcileWithCleanup($ownerScriptID, $powerOnly);

    assertCleanupSame(1, $result['cleanup']['entities'], 'Contracted entity was not replaced.');
    assertCleanupSame(6, $result['cleanup']['retainedTopics'], 'Old retained topics were not cleared.');
    assertCleanupSame(3, $result['summary']['publishedMessages'], 'Desired power-only channels were not published.');
    assertCleanupSame(9, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Cleanup and publish calls differ.');
    assertCleanupSame(5, DiagnosticsFakeSymconRuntime::instanceCount(), 'Power-only adapter count differs.');
    assertCleanupSame(2, DiagnosticsFakeSymconRuntime::eventCount(), 'Power-only event count differs.');
    $entry = $result['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];
    assertCleanupSame(['power'], array_keys($entry['commandTopics']), 'Command contract was not contracted.');
    assertCleanupSame(['power'], array_keys($entry['stateEventIDs']), 'State contract was not contracted.');
};

$tests['refuses deletion when an owned Ident no longer matches'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = cleanupConfiguration($serverID, true, true);
    $initial = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    $entry = $initial['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];
    $eventID = reset($entry['commandEventIDs']);
    DiagnosticsFakeSymconRuntime::setIdent($eventID, 'FOREIGN_IDENT');
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();
    $instanceCount = DiagnosticsFakeSymconRuntime::instanceCount();
    $eventCount = DiagnosticsFakeSymconRuntime::eventCount();

    assertCleanupThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::executeReconcileWithCleanup(
            $ownerScriptID,
            withoutCleanupEntities($configuration)
        ),
        'Mismatched event ownership was accepted.'
    );
    assertCleanupSame(0, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Cleanup published before ownership validation.');
    assertCleanupSame($instanceCount, DiagnosticsFakeSymconRuntime::instanceCount(), 'Instance changed after ownership refusal.');
    assertCleanupSame($eventCount, DiagnosticsFakeSymconRuntime::eventCount(), 'Event changed after ownership refusal.');
};

$tests['refuses unmanaged category children before cleanup side effects'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = cleanupConfiguration($serverID);
    $initial = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();

    $entry = $initial['diagnostics']['registry']['managedEntities']['example_lamp.main_light'];
    $foreignCategoryID = DiagnosticsFakeSymconRuntime::createCategory();
    IPS_SetParent($foreignCategoryID, $entry['publisherParentCategoryID']);
    IPS_SetIdent($foreignCategoryID, 'USER_OBJECT');
    IPS_SetName($foreignCategoryID, 'User Object');
    $empty = $configuration;
    $empty['devices'] = [];

    assertCleanupThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::executeReconcileWithCleanup(
            $ownerScriptID,
            $empty
        ),
        'Unmanaged category child was accepted.'
    );
    assertCleanupSame(0, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Failed preflight published tombstones.');
    $diagnosticsID = IPS_GetObjectIDByIdent('MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS', $ownerScriptID);
    assertCleanupTrue(is_int($diagnosticsID), 'Diagnostics category is missing.');
    $registryID = IPS_GetObjectIDByIdent('MANAGED_STATE_REGISTRY', $diagnosticsID);
    assertCleanupTrue(is_int($registryID), 'Registry variable is missing.');
    $registry = json_decode((string)GetValue($registryID), true, 512, JSON_THROW_ON_ERROR);
    assertCleanupTrue(
        isset($registry['managedEntities']['example_lamp.main_light']),
        'Failed category preflight lost entity ownership.'
    );
};

$tests['preserves Registry ownership after a tombstone failure and retries'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = cleanupConfiguration($serverID, true, true);
    $initial = MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();
    DiagnosticsFakeSymconRuntime::failRequestActionAt(1);

    assertCleanupThrows(
        RuntimeException::class,
        static fn (): array => MqttDiscoveryExporterRuntime::executeReconcileWithCleanup(
            $ownerScriptID,
            withoutCleanupEntities($configuration)
        ),
        'Failed tombstone was accepted.'
    );
    $registry = SAEF_ReadRegistry($initial['diagnostics']['registryID']);
    assertCleanupTrue(isset($registry['managedEntities']['example_lamp.main_light']), 'Failed cleanup lost entity ownership.');
    assertCleanupSame(14, DiagnosticsFakeSymconRuntime::instanceCount(), 'Failed cleanup deleted an instance.');
    assertCleanupSame(10, DiagnosticsFakeSymconRuntime::eventCount(), 'Failed cleanup deleted an event.');

    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();
    $retry = MqttDiscoveryExporterRuntime::executeReconcileWithCleanup(
        $ownerScriptID,
        withoutCleanupEntities($configuration)
    );
    assertCleanupSame(1, $retry['cleanup']['entities'], 'Retry did not complete cleanup.');
    assertCleanupTrue(
        !isset($retry['diagnostics']['registry']['managedEntities']['example_lamp.main_light']),
        'Retry left removed entity ownership.'
    );
};

$tests['does not clean or republish an unchanged configuration'] = static function (): void {
    DiagnosticsFakeSymconRuntime::reset();
    $ownerScriptID = DiagnosticsFakeSymconRuntime::createScript();
    $serverID = DiagnosticsFakeSymconRuntime::createServerInstance();
    $configuration = cleanupConfiguration($serverID);
    MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup($ownerScriptID, $configuration);
    DiagnosticsFakeSymconRuntime::clearRequestActionCalls();

    $result = MqttDiscoveryExporterRuntime::executeReconcileWithCleanup($ownerScriptID, $configuration);
    assertCleanupSame(
        ['entities' => 0, 'events' => 0, 'instances' => 0, 'categories' => 0, 'retainedTopics' => 0],
        $result['cleanup'],
        'Unchanged cleanup summary differs.'
    );
    assertCleanupSame(0, count(DiagnosticsFakeSymconRuntime::requestActionCalls()), 'Unchanged configuration caused MQTT actions.');
};

$passed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Cleanup test failed: %s\n%s\n", $name, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("MQTT Discovery Exporter cleanup tests passed: %d.\n", $passed));
