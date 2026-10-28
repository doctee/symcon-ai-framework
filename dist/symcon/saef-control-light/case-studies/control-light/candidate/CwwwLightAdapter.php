<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;
use JsonException;
use stdClass;

/**
 * Offline candidate for an ESPHome MQTT CWWW target. No I/O or status writes.
 * ControlLight owns alarm gating, serialization and bounded confirmation.
 * Feed complete device packets, never a mixture of separately updated fields.
 * Kelvin is an explicit linear channel-ratio approximation, not a colorimeter.
 */
final class CwwwLightAdapter
{
    private const MAX_PAYLOAD_BYTES = 8192;

    public function __construct(private int $warmKelvin, private int $coldKelvin)
    {
        if ($warmKelvin < 1000 || $coldKelvin > 10000 || $warmKelvin >= $coldKelvin) {
            throw new InvalidArgumentException('Invalid CWWW endpoints.');
        }
    }

    /** @return array<string, mixed> Native target values, not facade percentages. */
    public function command(string $capability, mixed $value): array
    {
        switch ($capability) {
            case 'state':
                if (!is_bool($value)) {
                    throw new InvalidArgumentException('STATE must be Boolean.');
                }
                // Do not overwrite retained brightness or white mix on ON/OFF.
                return ['state' => $value ? 'ON' : 'OFF'];
            case 'brightness':
                $brightness = self::byte($value);
                // One combined command avoids a preliminary full-brightness ON.
                return $brightness === 0
                    ? ['state' => 'OFF']
                    : ['state' => 'ON', 'brightness' => $brightness, 'transition' => 0];
            case 'colorTemperature':
                if (!is_int($value) || $value < $this->warmKelvin || $value > $this->coldKelvin) {
                    throw new InvalidArgumentException('Kelvin outside configured device range.');
                }
                $cold = (int)round(255 * ($value - $this->warmKelvin) / ($this->coldKelvin - $this->warmKelvin));
                // No state or brightness field: preserve both, also while OFF.
                return ['color_mode' => 'cwww', 'color' => ['c' => $cold, 'w' => 255 - $cold]];
            default:
                throw new InvalidArgumentException('Unsupported CWWW capability.');
        }
    }

    /**
     * Returns only independently present, valid feedback fields.
     * Missing attributes are not zero and must not overwrite retained feedback.
     * Invalid packets throw before the caller can apply any result.
     * @param array<string, mixed> $packet
     * @return array<string, bool|int>
     */
    public function feedback(array $packet): array
    {
        $result = [];
        if (array_key_exists('state', $packet)) {
            if (!in_array($packet['state'], ['ON', 'OFF'], true)) {
                throw new InvalidArgumentException('Invalid state feedback.');
            }
            $result['state'] = $packet['state'] === 'ON';
        }
        if (array_key_exists('brightness', $packet)) {
            $result['brightness'] = self::byte($packet['brightness']);
        }
        if (array_key_exists('color', $packet)) {
            if (($packet['color_mode'] ?? null) !== 'cwww' || !is_array($packet['color'])) {
                throw new InvalidArgumentException('CWWW color feedback requires explicit mode.');
            }
            $cold = self::byte($packet['color']['c'] ?? null);
            $warm = self::byte($packet['color']['w'] ?? null);
            if ($cold + $warm > 0) {
                $result['colorTemperature'] = (int)round(
                    $this->warmKelvin + ($this->coldKelvin - $this->warmKelvin) * $cold / ($cold + $warm)
                );
            }
            // Both channels zero carry no color-temperature information.
        }
        return $result;
    }

    /**
     * Decode one complete raw MQTT payload. Never merge C/W across packets.
     * This validates before any caller can publish target-variable updates.
     * Unknown device attributes are ignored, malformed known attributes fail.
     * @return array<string, bool|int>
     */
    public function feedbackJson(string $payload): array
    {
        if ($payload === '' || strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('Empty or oversized CWWW payload.');
        }
        try {
            $packet = json_decode($payload, false, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Invalid CWWW JSON payload.', 0, $error);
        }
        if (!$packet instanceof stdClass) {
            throw new InvalidArgumentException('CWWW payload must be a JSON object.');
        }
        $fields = get_object_vars($packet);
        if (array_key_exists('color', $fields)) {
            if (!$fields['color'] instanceof stdClass) {
                throw new InvalidArgumentException('CWWW color must be a JSON object.');
            }
            $fields['color'] = get_object_vars($fields['color']);
        }
        return $this->feedback($fields);
    }

    /**
     * Admission for initial adoption, not a command acknowledgement.
     * All target capabilities must be observed together before facade migration.
     * Availability/freshness and writer ownership remain external live gates.
     * @return array{state: bool, brightness: int, colorTemperature: int}
     */
    public function initialFeedback(string $payload): array
    {
        $values = $this->feedbackJson($payload);
        if (
            !isset($values['state'], $values['brightness'], $values['colorTemperature'])
            || !is_bool($values['state']) || !is_int($values['brightness'])
            || !is_int($values['colorTemperature'])
        ) {
            throw new InvalidArgumentException('Incomplete initial CWWW feedback.');
        }
        return [
            'state' => $values['state'],
            'brightness' => $values['brightness'],
            'colorTemperature' => $values['colorTemperature'],
        ];
    }

    private static function byte(mixed $value): int
    {
        if (!is_int($value) || $value < 0 || $value > 255) {
            throw new InvalidArgumentException('Expected an integer byte.');
        }
        return $value;
    }
}
