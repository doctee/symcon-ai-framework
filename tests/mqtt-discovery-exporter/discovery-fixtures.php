<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;

require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterCore.php';

/**
 * @return array<string, array{topic: string, payload: array<string, mixed>, runtimeTopics: list<string>}>
 */
function buildDiscoveryFixtureMatrix(): array
{
    $scenarios = [
        'switch' => ['class' => 'switch'],
        'light_onoff' => ['class' => 'light'],
        'light_brightness' => ['class' => 'light', 'brightness' => true],
        'light_brightness_rgb' => ['class' => 'light', 'brightness' => true, 'rgb' => true],
        'light_brightness_color_temperature' => [
            'class' => 'light',
            'brightness' => true,
            'colorTemperature' => true,
        ],
        'light_brightness_rgb_color_temperature' => [
            'class' => 'light',
            'brightness' => true,
            'rgb' => true,
            'colorTemperature' => true,
        ],
    ];
    $fixtures = [];

    foreach ($scenarios as $scenario => $capabilities) {
        $entity = [
            'id' => 'fixture_entity',
            'topic' => $scenario,
            'class' => $capabilities['class'],
            'name' => 'Fixture Entity',
            'stateID' => 1001,
            'actionID' => 1002,
        ];
        if (isset($capabilities['brightness'])) {
            $entity['brightnessStateID'] = 1003;
            $entity['brightnessActionID'] = 1004;
        }
        if (isset($capabilities['rgb'])) {
            $entity['colorStateID'] = 1005;
            $entity['colorActionID'] = 1006;
        }
        if (isset($capabilities['colorTemperature'])) {
            $entity['colorTempStateID'] = 1007;
            $entity['colorTempActionID'] = 1008;
            $entity['minKelvin'] = 2200;
            $entity['maxKelvin'] = 6500;
        }

        $configuration = MqttDiscoveryExporterCore::normalizeConfiguration([
            'version' => 'fixture-1',
            'location' => 'fixture_site',
            'mqtt' => [
                'serverID' => 9999,
                'baseTopic' => 'saef/fixture',
                'discoveryPrefix' => 'homeassistant',
                'configurationURL' => 'https://example.invalid/fixture',
            ],
            'defaults' => [
                'manufacturer' => 'Example Manufacturer',
                'model' => 'Example Model',
                'qos' => 1,
                'retain' => true,
            ],
            'devices' => [[
                'id' => 'fixture_device',
                'topic' => 'fixture_device',
                'name' => 'Fixture Device',
                'room' => 'Fixture Room',
                'entities' => [$entity],
            ]],
        ]);
        $device = $configuration['devices'][0];
        $normalizedEntity = $device['entities'][0];
        $payload = MqttDiscoveryExporterCore::buildDiscoveryPayload(
            $configuration,
            $device,
            $normalizedEntity
        );
        $observed = ['power' => true];
        if (isset($capabilities['brightness'])) {
            $observed['brightness'] = 42;
        }
        if (isset($capabilities['rgb'])) {
            $observed['rgb'] = 0x123456;
        }
        if (isset($capabilities['colorTemperature'])) {
            $observed['colorTemperature'] = 3000;
        }
        $runtime = MqttDiscoveryExporterCore::buildRuntimePayloads(
            $configuration,
            $device,
            $normalizedEntity,
            $observed
        );

        $fixtures[$scenario] = [
            'topic' => MqttDiscoveryExporterCore::discoveryTopic(
                $configuration,
                $device,
                $normalizedEntity
            ),
            'payload' => $payload,
            'runtimeTopics' => array_keys($runtime['topics']),
        ];
    }

    return $fixtures;
}

$actual = buildDiscoveryFixtureMatrix();
$encoded = json_encode(
    $actual,
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) . PHP_EOL;

if (($argv[1] ?? null) === '--print') {
    fwrite(STDOUT, $encoded);
    exit(0);
}

$fixturePath = __DIR__ . '/fixtures/discovery-capabilities.json';
$expectedSource = file_get_contents($fixturePath);
if ($expectedSource === false) {
    throw new RuntimeException('Discovery fixture file cannot be read.');
}
if ($encoded !== $expectedSource) {
    fwrite(STDERR, "Discovery fixture comparison failed. Regenerate and review the fixture explicitly.\n");
    exit(1);
}

fwrite(STDOUT, sprintf("MQTT Discovery Exporter discovery fixture scenarios passed: %d.\n", count($actual)));
