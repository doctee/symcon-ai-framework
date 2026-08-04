<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Navimow/MqttEnvelopeException.php';
require_once __DIR__ . '/../distribution/libs/Navimow/MqttEnvelopeParser.php';

use Navimow\MqttEnvelopeException;
use Navimow\MqttEnvelopeParser;

function loadEnvelopeFixture(string $name): array
{
    $contents = file_get_contents(__DIR__ . '/../fixtures/mqtt/' . $name);
    if ($contents === false) {
        throw new RuntimeException('Unable to read MQTT envelope fixture.');
    }

    $fixture = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
    if (
        !is_array($fixture)
        || !is_array($fixture['metadata'] ?? null)
        || !is_array($fixture['envelope'] ?? null)
    ) {
        throw new RuntimeException('MQTT envelope fixture is malformed.');
    }

    return $fixture;
}

function encodeEnvelope(array $envelope): string
{
    return json_encode($envelope, JSON_THROW_ON_ERROR);
}

function assertEnvelope(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertEnvelopeThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (MqttEnvelopeException) {
        return;
    }

    throw new RuntimeException($message);
}

foreach (
    [
        'symcon-envelope-location.json' => ['location', false],
        'symcon-envelope-state.json' => ['state', false],
        'symcon-envelope-retained.json' => ['state', true],
    ] as $name => [$channel, $retained]
) {
    $fixture = loadEnvelopeFixture($name);
    $metadata = $fixture['metadata'];
    assertEnvelope(
        ($metadata['synthetic'] ?? null) === true
            && ($metadata['sourceEvidence'] ?? null)
                === '94-native-mqtt-wss-symcon-live-spike-report.md'
            && ($metadata['channel'] ?? null) === $channel
            && ($metadata['retained'] ?? null) === $retained,
        sprintf('Envelope metadata changed for %s.', $name)
    );

    $result = MqttEnvelopeParser::parse(
        encodeEnvelope($fixture['envelope'])
    );
    assertEnvelope(
        $result['topic']
            === '/downlink/vehicle/DEVICE_001/realtimeDate/' . $channel
            && is_string($result['payload'])
            && $result['qualityOfService'] === 0
            && $result['retained'] === $retained
            && $result['packetType'] === 3,
        sprintf('Envelope normalization changed for %s.', $name)
    );
    assertEnvelope(
        array_keys($result) === [
            'topic',
            'payload',
            'qualityOfService',
            'retained',
            'packetType',
        ],
        'Envelope parser returned an unexpected field.'
    );
}

$invalidDataId = loadEnvelopeFixture(
    'symcon-envelope-invalid-data-id.json'
);
assertEnvelopeThrows(
    static fn (): array => MqttEnvelopeParser::parse(
        encodeEnvelope($invalidDataId['envelope'])
    ),
    'Envelope with another DataID was accepted.'
);

$base = loadEnvelopeFixture('symcon-envelope-state.json')['envelope'];
foreach (
    [
        'unknown key' => $base + ['Unknown' => true],
        'missing key' => array_diff_key($base, ['Topic' => true]),
        'QoS one' => array_replace($base, ['QualityOfService' => 1]),
        'string QoS' => array_replace($base, ['QualityOfService' => '0']),
        'invalid retain' => array_replace($base, ['Retain' => 0]),
        'invalid packet type' => array_replace($base, ['PacketType' => 16]),
        'empty topic' => array_replace($base, ['Topic' => '']),
        'non-string payload' => array_replace($base, ['Payload' => []]),
    ] as $label => $envelope
) {
    assertEnvelopeThrows(
        static fn (): array => MqttEnvelopeParser::parse(
            encodeEnvelope($envelope)
        ),
        sprintf('Envelope with %s was accepted.', $label)
    );
}

assertEnvelopeThrows(
    static fn (): array => MqttEnvelopeParser::parse(
        encodeEnvelope(array_replace(
            $base,
            ['Payload' => str_repeat('x', 32769)]
        ))
    ),
    'Oversized envelope payload was accepted.'
);
assertEnvelopeThrows(
    static fn (): array => MqttEnvelopeParser::parse(
        str_repeat(' ', 65537)
    ),
    'Oversized outer envelope was accepted.'
);
assertEnvelopeThrows(
    static fn (): array => MqttEnvelopeParser::parse("\xC3\x28"),
    'Invalid UTF-8 envelope was accepted.'
);

echo "Navimow native MQTT envelope checks passed.\n";
