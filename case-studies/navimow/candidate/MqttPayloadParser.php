<?php

declare(strict_types=1);

namespace Navimow;

use JsonException;

final class MqttPayloadParser
{
    private const MAX_PAYLOAD_BYTES = 1048576;
    private const MAX_JSON_DEPTH = 32;
    private const MAX_LOCATION_ENTRIES = 64;
    private const MAX_FIELDS_PER_ENTRY = 64;

    private const CHANNELS = [
        'state',
        'event',
        'attributes',
        'location',
    ];

    private const INTEGER_FIELDS = [
        'time',
        'type',
        'vehicleState',
    ];

    private const NUMERIC_FIELDS = [
        'postureTheta',
        'postureX',
        'postureY',
        'mowingPercentage',
    ];

    public static function parse(
        string $topic,
        string $payload,
        string $expectedDeviceId
    ): array {
        $channel = self::parseTopic($topic, $expectedDeviceId);
        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            throw new MqttPayloadException(
                'MQTT payload exceeds the one MiB limit.'
            );
        }

        try {
            $decoded = json_decode(
                $payload,
                true,
                self::MAX_JSON_DEPTH,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new MqttPayloadException(
                'MQTT payload is not valid UTF-8 JSON.',
                0,
                $exception
            );
        }

        if ($channel !== 'location') {
            throw new MqttPayloadException(
                sprintf(
                    'MQTT %s payload contract is not fixture-backed.',
                    $channel
                )
            );
        }

        return [
            'channel' => $channel,
            'deviceId' => $expectedDeviceId,
            'patches' => self::parseLocation($decoded),
        ];
    }

    private static function parseTopic(
        string $topic,
        string $expectedDeviceId
    ): string {
        if (
            $expectedDeviceId === ''
            || strpbrk($expectedDeviceId, '/#+') !== false
        ) {
            throw new MqttPayloadException(
                'Expected MQTT device ID is invalid.'
            );
        }

        foreach (self::CHANNELS as $channel) {
            $expectedTopic = sprintf(
                '/downlink/vehicle/%s/realtimeDate/%s',
                $expectedDeviceId,
                $channel
            );
            if (hash_equals($expectedTopic, $topic)) {
                return $channel;
            }
        }

        throw new MqttPayloadException(
            'MQTT topic is outside the exact per-device allowlist.'
        );
    }

    private static function parseLocation(mixed $decoded): array
    {
        if (
            !is_array($decoded)
            || !array_is_list($decoded)
            || $decoded === []
        ) {
            throw new MqttPayloadException(
                'MQTT location payload must be a non-empty JSON array.'
            );
        }

        if (count($decoded) > self::MAX_LOCATION_ENTRIES) {
            throw new MqttPayloadException(
                'MQTT location payload contains too many entries.'
            );
        }

        $patches = [];
        foreach ($decoded as $entry) {
            if (
                !is_array($entry)
                || $entry === []
                || array_is_list($entry)
            ) {
                throw new MqttPayloadException(
                    'MQTT location entry must be a non-empty JSON object.'
                );
            }
            if (count($entry) > self::MAX_FIELDS_PER_ENTRY) {
                throw new MqttPayloadException(
                    'MQTT location entry contains too many fields.'
                );
            }
            $patches[] = self::parseLocationEntry($entry);
        }

        return $patches;
    }

    private static function parseLocationEntry(array $entry): array
    {
        $fields = [];
        $nullFields = [];
        $unknownFields = [];

        foreach ($entry as $name => $value) {
            if ($value === null) {
                $nullFields[] = $name;
                continue;
            }

            if (in_array($name, self::INTEGER_FIELDS, true)) {
                if (!is_int($value)) {
                    throw new MqttPayloadException(
                        sprintf(
                            'MQTT location field %s must be an integer.',
                            $name
                        )
                    );
                }
                $fields[$name] = $value;
                continue;
            }

            if (in_array($name, self::NUMERIC_FIELDS, true)) {
                $fields[$name] = self::finiteNumber($name, $value);
                continue;
            }

            $unknownFields[] = $name;
        }

        sort($nullFields);
        sort($unknownFields);

        if (!isset($fields['time'])) {
            throw new MqttPayloadException(
                'MQTT location entry requires an integer time field.'
            );
        }

        return [
            'fields' => $fields,
            'presentFields' => array_keys($entry),
            'nullFields' => $nullFields,
            'unknownFields' => $unknownFields,
            'sourceTimestamp' => $fields['time'],
        ];
    }

    private static function finiteNumber(
        string $name,
        mixed $value
    ): float {
        if (
            !is_int($value)
            && !is_float($value)
            && !(
                is_string($value)
                && preg_match(
                    '/^-?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/D',
                    $value
                ) === 1
            )
        ) {
            throw new MqttPayloadException(
                sprintf(
                    'MQTT location field %s must be numeric.',
                    $name
                )
            );
        }

        $number = (float) $value;
        if (!is_finite($number)) {
            throw new MqttPayloadException(
                sprintf(
                    'MQTT location field %s must be finite.',
                    $name
                )
            );
        }

        return $number;
    }
}
