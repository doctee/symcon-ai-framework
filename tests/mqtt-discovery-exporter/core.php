<?php
declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;

require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterCore.php';

/** @return array<string, mixed> */
function validExporterConfiguration(): array
{
    return [
        'version' => 'test-1',
        'location' => 'test_site',
        'mqtt' => [
            'serverID' => 1234,
            'baseTopic' => 'saef/export',
            'discoveryPrefix' => 'homeassistant',
            'configurationURL' => 'https://example.invalid/configuration',
        ],
        'defaults' => [
            'manufacturer' => 'Example Manufacturer',
            'model' => 'Example Model',
            'qos' => 1,
            'retain' => true,
        ],
        'devices' => [[
            'id' => 'example_lamp',
            'topic' => 'living_room_lamp',
            'name' => 'Example Lamp',
            'room' => 'Example Room',
            'entities' => [[
                'id' => 'main_light',
                'class' => 'light',
                'name' => 'Main Light',
                'stateID' => 1001,
                'actionID' => 1002,
                'brightnessID' => 1003,
                'colorID' => 1004,
                'colorTempStateID' => 1005,
                'colorTempActionID' => 1006,
                'minKelvin' => 2200,
                'maxKelvin' => 6500,
                'confirmation' => [
                    'timeoutMilliseconds' => 1500,
                    'pollIntervalMilliseconds' => 50,
                ],
            ]],
        ]],
    ];
}

function assertCoreSame(mixed $expected, mixed $actual, string $message): void
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

function assertCoreTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $expectedClass */
function assertCoreThrows(string $expectedClass, callable $operation, string $message): void
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

$tests['normalizes legacy server and explicit client transport contracts'] = static function (): void {
    $legacy = MqttDiscoveryExporterCore::normalizeConfiguration(validExporterConfiguration());
    assertCoreSame('server', $legacy['mqtt']['transport'], 'Legacy transport differs.');
    assertCoreSame(1234, $legacy['mqtt']['gatewayID'], 'Legacy gateway differs.');

    $clientRaw = validExporterConfiguration();
    unset($clientRaw['mqtt']['serverID']);
    $clientRaw['mqtt']['transport'] = 'client';
    $clientRaw['mqtt']['gatewayID'] = 5678;
    $client = MqttDiscoveryExporterCore::normalizeConfiguration($clientRaw);
    assertCoreSame('client', $client['mqtt']['transport'], 'Client transport differs.');
    assertCoreSame(5678, $client['mqtt']['gatewayID'], 'Client gateway differs.');
};

$tests['rejects unsupported or incomplete MQTT transport contracts'] = static function (): void {
    $unsupported = validExporterConfiguration();
    $unsupported['mqtt']['transport'] = 'bridge';
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::normalizeConfiguration($unsupported),
        'Unsupported MQTT transport was accepted.'
    );

    $missingGateway = validExporterConfiguration();
    unset($missingGateway['mqtt']['serverID']);
    $missingGateway['mqtt']['transport'] = 'client';
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::normalizeConfiguration($missingGateway),
        'Client transport without gateway was accepted.'
    );
};

$tests['normalizes complete aliases and separate state/action pairs'] = static function (): void {
    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration(validExporterConfiguration());
    $entity = $configuration['devices'][0]['entities'][0];

    assertCoreSame(
        ['stateVariableID' => 1001, 'actionVariableID' => 1002, 'invert' => false],
        $entity['capabilities']['power'],
        'Power contract differs.'
    );
    assertCoreSame(
        ['stateVariableID' => 1003, 'actionVariableID' => 1003],
        $entity['capabilities']['brightness'],
        'Combined brightness alias differs.'
    );
    assertCoreSame(
        ['stateVariableID' => 1004, 'actionVariableID' => 1004],
        $entity['capabilities']['rgb'],
        'Combined RGB alias differs.'
    );
    assertCoreSame(2200, $entity['capabilities']['colorTemperature']['minimumKelvin'], 'Minimum Kelvin differs.');
    assertCoreSame(6500, $entity['capabilities']['colorTemperature']['maximumKelvin'], 'Maximum Kelvin differs.');
};

$tests['accepts an empty desired entity set for complete cleanup'] = static function (): void {
    $raw = validExporterConfiguration();
    $raw['devices'] = [];
    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration($raw);

    assertCoreSame([], $configuration['devices'], 'Empty desired device set differs.');
};

$tests['rejects incomplete and unsupported capability contracts'] = static function (): void {
    $incomplete = validExporterConfiguration();
    unset($incomplete['devices'][0]['entities'][0]['actionID']);
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::normalizeConfiguration($incomplete),
        'Incomplete power pair was accepted.'
    );

    $switchWithBrightness = validExporterConfiguration();
    $switchWithBrightness['devices'][0]['entities'][0]['class'] = 'switch';
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::normalizeConfiguration($switchWithBrightness),
        'Switch with light capability was accepted.'
    );
};

$tests['rejects duplicate topics and permissive configuration coercion'] = static function (): void {
    $duplicate = validExporterConfiguration();
    $duplicate['devices'][0]['entities'][] = [
        'id' => 'secondary_light',
        'class' => 'light',
        'name' => 'Secondary Light',
        'powerID' => 1100,
    ];
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::normalizeConfiguration($duplicate),
        'Duplicate runtime topic was accepted.'
    );

    $coerced = validExporterConfiguration();
    $coerced['defaults']['qos'] = '1';
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::normalizeConfiguration($coerced),
        'String QoS was coerced.'
    );

    $nonRetained = validExporterConfiguration();
    $nonRetained['defaults']['retain'] = false;
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::normalizeConfiguration($nonRetained),
        'Non-retained runtime state was accepted.'
    );
};

$tests['builds deterministic discovery identity and topics'] = static function (): void {
    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration(validExporterConfiguration());
    $device = $configuration['devices'][0];
    $entity = $device['entities'][0];
    $payload = MqttDiscoveryExporterCore::buildDiscoveryPayload($configuration, $device, $entity);

    assertCoreSame(
        'homeassistant/light/test_site/symcon_test_site_light_living_room_lamp/config',
        MqttDiscoveryExporterCore::discoveryTopic($configuration, $device, $entity),
        'Discovery topic differs.'
    );
    assertCoreSame(
        'saef/export/test_site/light/living_room_lamp/state',
        $payload['state_topic'],
        'State topic differs.'
    );
    assertCoreSame(
        'saef/export/test_site/light/living_room_lamp/color_mode/state',
        $payload['color_mode_state_topic'],
        'Color mode topic differs.'
    );
    assertCoreSame('rgb', MqttDiscoveryExporterCore::lightColorMode($entity), 'Color mode differs.');
    assertCoreTrue(
        preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $payload['unique_id']) === 1,
        'Unique ID is not a UUID v5.'
    );
    assertCoreSame(
        $payload,
        MqttDiscoveryExporterCore::buildDiscoveryPayload($configuration, $device, $entity),
        'Discovery payload is not deterministic.'
    );
};

$tests['strictly parses supported command payloads'] = static function (): void {
    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration(validExporterConfiguration());
    $entity = $configuration['devices'][0]['entities'][0];

    assertCoreSame(['capability' => 'power', 'value' => true], MqttDiscoveryExporterCore::parseCommand($entity, 'power', 'ON'), 'ON differs.');
    assertCoreSame(['capability' => 'brightness', 'value' => 100], MqttDiscoveryExporterCore::parseCommand($entity, 'brightness', '100'), 'Brightness differs.');
    assertCoreSame(['capability' => 'rgb', 'value' => 0x123456], MqttDiscoveryExporterCore::parseCommand($entity, 'rgb', '#123456'), 'Hex RGB differs.');
    assertCoreSame(['capability' => 'rgb', 'value' => 0x123456], MqttDiscoveryExporterCore::parseCommand($entity, 'rgb', '18,52,86'), 'CSV RGB differs.');
    assertCoreSame(['capability' => 'colorTemperature', 'value' => 3000], MqttDiscoveryExporterCore::parseCommand($entity, 'colorTemperature', '3000'), 'Kelvin differs.');
};

$tests['rejects malformed and unavailable command payloads'] = static function (): void {
    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration(validExporterConfiguration());
    $entity = $configuration['devices'][0]['entities'][0];

    foreach (
        [
            ['power', 'true'],
            ['brightness', '50.5'],
            ['brightness', '01'],
            ['brightness', '101'],
            ['rgb', '256,0,0'],
            ['rgb', '#12345G'],
            ['colorTemperature', '1000'],
        ] as [$type, $payload]
    ) {
        assertCoreThrows(
            InvalidArgumentException::class,
            static fn (): array => MqttDiscoveryExporterCore::parseCommand($entity, $type, $payload),
            sprintf('Malformed %s payload %s was accepted.', $type, $payload)
        );
    }

    $powerOnly = $entity;
    $powerOnly['capabilities'] = ['power' => $entity['capabilities']['power']];
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::parseCommand($powerOnly, 'brightness', '50'),
        'Unavailable brightness command was accepted.'
    );
};

$tests['builds observed runtime payloads without command echo'] = static function (): void {
    $configuration = MqttDiscoveryExporterCore::normalizeConfiguration(validExporterConfiguration());
    $device = $configuration['devices'][0];
    $entity = $device['entities'][0];
    $runtime = MqttDiscoveryExporterCore::buildRuntimePayloads(
        $configuration,
        $device,
        $entity,
        [
            'power' => true,
            'brightness' => 42,
            'rgb' => 0x123456,
            'colorTemperature' => 3000,
        ]
    );

    $base = 'saef/export/test_site/light/living_room_lamp';
    assertCoreSame('ON', $runtime['topics'][$base . '/state'], 'Power state differs.');
    assertCoreSame('42', $runtime['topics'][$base . '/brightness/state'], 'Brightness state differs.');
    assertCoreSame('18,52,86', $runtime['topics'][$base . '/rgb/state'], 'RGB state differs.');
    assertCoreSame('3000', $runtime['topics'][$base . '/color_temp/state'], 'Kelvin state differs.');
    assertCoreSame('rgb', $runtime['topics'][$base . '/color_mode/state'], 'Runtime color mode differs.');

    $wrongType = ['power' => 1, 'brightness' => 42, 'rgb' => 0, 'colorTemperature' => 3000];
    assertCoreThrows(
        InvalidArgumentException::class,
        static fn (): array => MqttDiscoveryExporterCore::buildRuntimePayloads(
            $configuration,
            $device,
            $entity,
            $wrongType
        ),
        'Integer power state was coerced.'
    );
};

$tests['plans exact removals and stable canonical hashes'] = static function (): void {
    $removed = MqttDiscoveryExporterCore::planRemovedEntries(
        [
            'device.keep' => ['topic' => 'keep'],
            'device.remove_b' => ['topic' => 'remove-b'],
            'device.remove_a' => ['topic' => 'remove-a'],
        ],
        ['device.keep']
    );

    assertCoreSame(['device.remove_a', 'device.remove_b'], array_keys($removed), 'Removal plan is not exact and sorted.');
    assertCoreSame(
        MqttDiscoveryExporterCore::payloadHash(['b' => 2, 'a' => ['d' => 4, 'c' => 3]]),
        MqttDiscoveryExporterCore::payloadHash(['a' => ['c' => 3, 'd' => 4], 'b' => 2]),
        'Canonical hash depends on associative key order.'
    );
};

$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Core test failed: %s\n%s\n", $name, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("MQTT Discovery Exporter core tests passed: %d.\n", $passed));
