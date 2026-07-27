<?php

declare(strict_types=1);

namespace Navimow;

final class MqttPartialStateAccumulator
{
    private array $fields = [];
    private ?int $lastTimestamp = null;

    public function apply(array $patch): array
    {
        $fields = $patch['fields'] ?? null;
        $nullFields = $patch['nullFields'] ?? null;
        $unknownFields = $patch['unknownFields'] ?? null;
        $sourceTimestamp = $patch['sourceTimestamp'] ?? null;

        if (
            !is_array($fields)
            || !is_array($nullFields)
            || !is_array($unknownFields)
            || ($sourceTimestamp !== null && !is_int($sourceTimestamp))
        ) {
            throw new MqttPayloadException(
                'MQTT partial-state patch is malformed.'
            );
        }

        if (
            $sourceTimestamp !== null
            && $this->lastTimestamp !== null
            && $sourceTimestamp < $this->lastTimestamp
        ) {
            return $this->result(
                false,
                'out-of-order',
                $nullFields,
                $unknownFields
            );
        }

        foreach ($fields as $name => $value) {
            if ($value !== null) {
                $this->fields[$name] = $value;
            }
        }
        if (
            $sourceTimestamp !== null
            && (
                $this->lastTimestamp === null
                || $sourceTimestamp > $this->lastTimestamp
            )
        ) {
            $this->lastTimestamp = $sourceTimestamp;
        }

        return $this->result(
            true,
            'applied',
            $nullFields,
            $unknownFields
        );
    }

    public function snapshot(): array
    {
        return [
            'fields' => $this->fields,
            'lastTimestamp' => $this->lastTimestamp,
        ];
    }

    private function result(
        bool $accepted,
        string $reason,
        array $nullFields,
        array $unknownFields
    ): array {
        return [
            'accepted' => $accepted,
            'reason' => $reason,
            'state' => $this->snapshot(),
            'ignoredNullFields' => array_values($nullFields),
            'unknownFields' => array_values($unknownFields),
        ];
    }
}
