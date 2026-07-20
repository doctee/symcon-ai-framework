<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;

/**
 * Side-effect-free configuration and value-mapping core for ControlLight v2.
 *
 * IP-Symcon object handling, actions, feedback waiting and diagnostics belong
 * to ControlLightRuntime. Keeping the mapping core pure makes every installed
 * configuration variant reproducible without a live installation.
 */
final class ControlLightCore
{
    public const BRIGHTNESS_REPORTED = 'reported';
    public const BRIGHTNESS_EFFECTIVE = 'effective';

    /** @var list<string> */
    private const CAPABILITIES = ['state', 'brightness', 'colorTemperature', 'color'];

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function normalizeConfiguration(array $configuration): array
    {
        if (!array_key_exists('brightnessSemantics', $configuration)) {
            throw new InvalidArgumentException(
                'configuration.brightnessSemantics must be selected explicitly for every v2 instance.'
            );
        }
        $preset = strtoupper(self::optionalString($configuration, 'preset') ?? '');
        $presetConfiguration = self::presetConfiguration($preset);
        $merged = array_replace(self::defaultConfiguration(), $presetConfiguration, $configuration);

        $brightnessSemantics = self::requireEnum(
            $merged,
            'brightnessSemantics',
            [self::BRIGHTNESS_REPORTED, self::BRIGHTNESS_EFFECTIVE]
        );

        $confirmation = $merged['confirmation'];
        if (!is_array($confirmation)) {
            throw new InvalidArgumentException('configuration.confirmation must be an array.');
        }
        $timeoutMilliseconds = self::requireIntegerRange(
            $confirmation,
            'timeoutMilliseconds',
            0,
            30 * 1000,
            'configuration.confirmation'
        );
        $pollIntervalMilliseconds = self::requireIntegerRange(
            $confirmation,
            'pollIntervalMilliseconds',
            10,
            1000,
            'configuration.confirmation'
        );
        if ($timeoutMilliseconds > 0 && $pollIntervalMilliseconds > $timeoutMilliseconds) {
            throw new InvalidArgumentException(
                'configuration.confirmation.pollIntervalMilliseconds must not exceed timeoutMilliseconds.'
            );
        }

        $semaphore = $merged['semaphore'];
        if (!is_array($semaphore)) {
            throw new InvalidArgumentException('configuration.semaphore must be an array.');
        }

        $normalized = [
            'version' => self::optionalString($merged, 'version') ?? 'ControlLight-v2-candidate',
            'preset' => $preset,
            'alarmID' => self::optionalNonNegativeInteger($merged, 'alarmID'),
            'alarmIDIsAlarmActive' => self::requireBoolean($merged, 'alarmIDIsAlarmActive'),
            'authoritativeFeedback' => self::requireBoolean($merged, 'authoritativeFeedback'),
            'brightnessSemantics' => $brightnessSemantics,
            'confirmation' => [
                'timeoutMilliseconds' => $timeoutMilliseconds,
                'pollIntervalMilliseconds' => $pollIntervalMilliseconds,
            ],
            'semaphore' => [
                'timeoutMilliseconds' => self::requireIntegerRange(
                    $semaphore,
                    'timeoutMilliseconds',
                    0,
                    30 * 1000,
                    'configuration.semaphore'
                ),
            ],
            'debug' => self::requireBoolean($merged, 'debug'),
            'capabilities' => [],
            'externalTriggers' => self::normalizeExternalTriggers($merged['externalTriggers']),
        ];
        if ($normalized['authoritativeFeedback'] !== true) {
            throw new InvalidArgumentException('ControlLight v2 requires authoritativeFeedback=true.');
        }

        foreach (self::capabilityDefinitions() as $capability => $definition) {
            $ident = self::requireString($merged, $definition['identKey']);
            $type = self::requireIntegerRange($merged, $definition['typeKey'], 0, 3, 'configuration');
            $normalized['capabilities'][$capability] = [
                'enabled' => $ident !== '',
                'targetIdent' => $ident,
                'targetType' => $type,
                'localIdent' => $definition['localIdent'],
                'localName' => self::requireString($merged, $definition['nameKey']),
                'localType' => $definition['localType'],
                'profile' => $definition['profile'],
                'position' => $definition['position'],
            ];
        }

        $normalized['dimmerTargetMax'] = self::requireIntegerRange(
            $merged,
            'dimmerTargetMax',
            1,
            65535,
            'configuration'
        );
        $normalized['tempInputIsKelvin'] = self::requireBoolean($merged, 'tempInputIsKelvin');
        $normalized['tempTargetIsMired'] = self::requireBoolean($merged, 'tempTargetIsMired');
        $normalized['colorTargetFormat'] = self::requireEnum(
            $merged,
            'colorTargetFormat',
            ['INT_HEX', 'RGB_ARRAY_STRING', 'RGB_OBJECT_STRING', 'HS_ARRAY_STRING']
        );

        return $normalized;
    }

    /** @return list<string> */
    public static function capabilities(): array
    {
        return self::CAPABILITIES;
    }

    /**
     * Maps a user-facing ControlLight value to the configured target contract.
     *
     * @param array<string, mixed> $configuration Normalized configuration.
     */
    public static function localToTarget(string $capability, mixed $value, array $configuration): mixed
    {
        self::assertCapabilityEnabled($capability, $configuration);

        return match ($capability) {
            'state' => (bool)$value,
            'brightness' => self::scaleInteger(
                self::limitInteger((int)$value, 0, 100),
                100,
                (int)$configuration['dimmerTargetMax']
            ),
            'colorTemperature' => self::localTemperatureToTarget((int)$value, $configuration),
            'color' => self::localColorToTarget((int)$value, (string)$configuration['colorTargetFormat']),
            default => throw new InvalidArgumentException('Unsupported capability: ' . $capability),
        };
    }

    /**
     * Maps authoritative target feedback to the local ControlLight contract.
     *
     * @param array<string, mixed> $configuration Normalized configuration.
     */
    public static function targetToLocal(
        string $capability,
        mixed $value,
        array $configuration,
        ?bool $targetState = null
    ): mixed {
        self::assertCapabilityEnabled($capability, $configuration);

        return match ($capability) {
            'state' => (bool)$value,
            'brightness' => self::targetBrightnessToLocal((int)$value, $configuration, $targetState),
            'colorTemperature' => self::targetTemperatureToLocal((int)$value, $configuration),
            'color' => self::targetColorToLocal($value, (string)$configuration['colorTargetFormat']),
            default => throw new InvalidArgumentException('Unsupported capability: ' . $capability),
        };
    }

    /**
     * Target comparison supports module normalization and representation changes.
     *
     * @param array<string, mixed> $configuration Normalized configuration.
     */
    public static function targetValueMatches(
        string $capability,
        mixed $expectedTargetValue,
        mixed $actualTargetValue,
        array $configuration
    ): bool {
        return match ($capability) {
            'state' => (bool)$actualTargetValue === (bool)$expectedTargetValue,
            'brightness' => abs((int)$actualTargetValue - (int)$expectedTargetValue) <= 1,
            'colorTemperature' => abs((int)$actualTargetValue - (int)$expectedTargetValue) <= 5,
            'color' => self::targetToLocal('color', $actualTargetValue, $configuration)
                === self::targetToLocal('color', $expectedTargetValue, $configuration),
            default => throw new InvalidArgumentException('Unsupported capability: ' . $capability),
        };
    }

    /** @return array<string, mixed> */
    private static function defaultConfiguration(): array
    {
        return [
            'version' => 'ControlLight-v2-candidate',
            'preset' => '',
            'alarmID' => 0,
            'alarmIDIsAlarmActive' => true,
            'authoritativeFeedback' => true,
            // Normalization still requires the caller to provide this key explicitly.
            'brightnessSemantics' => self::BRIGHTNESS_REPORTED,
            'identState' => '',
            'identDim' => '',
            'identTemp' => '',
            'identColor' => '',
            'typeState' => 0,
            'typeDim' => 1,
            'typeTemp' => 1,
            'typeColor' => 1,
            'dimmerTargetMax' => 100,
            'tempInputIsKelvin' => true,
            'tempTargetIsMired' => false,
            'colorTargetFormat' => 'INT_HEX',
            'nameState' => 'Licht',
            'nameDim' => 'Helligkeit',
            'nameTemp' => 'Farbtemperatur Kelvin',
            'nameColor' => 'Farbe',
            'confirmation' => [
                'timeoutMilliseconds' => 3 * 1000,
                'pollIntervalMilliseconds' => 100,
            ],
            'semaphore' => [
                'timeoutMilliseconds' => 5 * 1000,
            ],
            'debug' => false,
            'externalTriggers' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function presetConfiguration(string $preset): array
    {
        return match ($preset) {
            'Z2M' => [
                'identState' => 'state',
                'identDim' => 'brightness',
                'identTemp' => 'color_temp_kelvin',
                'identColor' => 'color',
            ],
            'MATTER', 'HA', 'HOMEASSISTANT' => [
                'identState' => 'light_status',
                'identDim' => 'light_brightness',
                'identTemp' => 'light_color_temp_kelvin',
                'identColor' => 'light_hs_color',
                'typeColor' => 3,
                'colorTargetFormat' => 'HS_ARRAY_STRING',
            ],
            'HOMEMATIC' => [
                'identState' => 'state',
            ],
            '' => [],
            default => throw new InvalidArgumentException('Unsupported preset: ' . $preset),
        };
    }

    /**
     * @return array<string, array{
     *   identKey: string, typeKey: string, nameKey: string, localIdent: string,
     *   localType: int, profile: string, position: int
     * }>
     */
    private static function capabilityDefinitions(): array
    {
        return [
            'state' => [
                'identKey' => 'identState', 'typeKey' => 'typeState', 'nameKey' => 'nameState',
                'localIdent' => 'STATE', 'localType' => 0, 'profile' => '~Switch', 'position' => 10,
            ],
            'brightness' => [
                'identKey' => 'identDim', 'typeKey' => 'typeDim', 'nameKey' => 'nameDim',
                'localIdent' => 'DIMMER', 'localType' => 1, 'profile' => '~Intensity.100', 'position' => 20,
            ],
            'colorTemperature' => [
                'identKey' => 'identTemp', 'typeKey' => 'typeTemp', 'nameKey' => 'nameTemp',
                'localIdent' => 'COLOR_TEMPERATURE', 'localType' => 1, 'profile' => '~TWColor', 'position' => 30,
            ],
            'color' => [
                'identKey' => 'identColor', 'typeKey' => 'typeColor', 'nameKey' => 'nameColor',
                'localIdent' => 'COLOR', 'localType' => 1, 'profile' => '~HexColor', 'position' => 40,
            ],
        ];
    }

    /**
     * @param mixed $triggers
     *
     * @return list<array<string, mixed>>
     */
    private static function normalizeExternalTriggers(mixed $triggers): array
    {
        if (!is_array($triggers)) {
            throw new InvalidArgumentException('configuration.externalTriggers must be an array.');
        }

        $normalized = [];
        foreach ($triggers as $index => $trigger) {
            if (!is_array($trigger)) {
                throw new InvalidArgumentException(sprintf(
                    'configuration.externalTriggers[%s] must be an array.',
                    (string)$index
                ));
            }
            $sourceID = $trigger['sourceID'] ?? $trigger['linkID'] ?? null;
            if (!is_int($sourceID) || $sourceID <= 0) {
                throw new InvalidArgumentException(sprintf(
                    'configuration.externalTriggers[%s] requires a positive sourceID or linkID.',
                    (string)$index
                ));
            }
            $normalized[] = [
                'sourceID' => $sourceID,
                'sourceIsLink' => array_key_exists('linkID', $trigger),
                'event' => self::normalizeEnumValue(
                    $trigger['event'] ?? 'change',
                    ['change', 'update'],
                    sprintf('configuration.externalTriggers[%s].event', (string)$index)
                ),
                'action' => self::normalizeEnumValue(
                    $trigger['action'] ?? 'value',
                    ['on', 'off', 'toggle', 'value'],
                    sprintf('configuration.externalTriggers[%s].action', (string)$index)
                ),
                'invert' => self::normalizeBooleanValue(
                    $trigger['invert'] ?? false,
                    sprintf('configuration.externalTriggers[%s].invert', (string)$index)
                ),
                'respectAlarm' => self::normalizeBooleanValue(
                    $trigger['respectAlarm'] ?? true,
                    sprintf('configuration.externalTriggers[%s].respectAlarm', (string)$index)
                ),
            ];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $configuration */
    private static function assertCapabilityEnabled(string $capability, array $configuration): void
    {
        if (!in_array($capability, self::CAPABILITIES, true)) {
            throw new InvalidArgumentException('Unsupported capability: ' . $capability);
        }
        if (($configuration['capabilities'][$capability]['enabled'] ?? false) !== true) {
            throw new InvalidArgumentException('Capability is disabled: ' . $capability);
        }
    }

    /** @param array<string, mixed> $configuration */
    private static function targetBrightnessToLocal(int $value, array $configuration, ?bool $targetState): int
    {
        if (
            $configuration['brightnessSemantics'] === self::BRIGHTNESS_EFFECTIVE
            && $targetState === false
        ) {
            return 0;
        }

        return self::scaleInteger($value, (int)$configuration['dimmerTargetMax'], 100);
    }

    /** @param array<string, mixed> $configuration */
    private static function localTemperatureToTarget(int $value, array $configuration): int
    {
        if ($configuration['tempInputIsKelvin'] === true && $configuration['tempTargetIsMired'] === true) {
            return self::kelvinToMired($value);
        }

        return $value;
    }

    /** @param array<string, mixed> $configuration */
    private static function targetTemperatureToLocal(int $value, array $configuration): int
    {
        if ($configuration['tempInputIsKelvin'] === true && $configuration['tempTargetIsMired'] === true) {
            return self::miredToKelvin($value);
        }

        return $value;
    }

    private static function localColorToTarget(int $color, string $format): int|string
    {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        return match ($format) {
            'INT_HEX' => $color,
            'RGB_ARRAY_STRING' => self::encodeJson([$red, $green, $blue]),
            'RGB_OBJECT_STRING' => self::encodeJson(['r' => $red, 'g' => $green, 'b' => $blue]),
            'HS_ARRAY_STRING' => self::rgbToHueSaturation($red, $green, $blue),
            default => throw new InvalidArgumentException('Unsupported color target format: ' . $format),
        };
    }

    private static function targetColorToLocal(mixed $value, string $format): int
    {
        if ($format === 'INT_HEX') {
            return self::limitInteger((int)$value, 0, 0xFFFFFF);
        }

        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Target color is not valid JSON.');
        }

        if ($format === 'HS_ARRAY_STRING') {
            if (!isset($decoded[0], $decoded[1])) {
                throw new InvalidArgumentException('HS target color requires hue and saturation.');
            }
            return self::hueSaturationToRgb((float)$decoded[0], (float)$decoded[1]);
        }

        if (isset($decoded[0], $decoded[1], $decoded[2])) {
            [$red, $green, $blue] = [$decoded[0], $decoded[1], $decoded[2]];
        } elseif (isset($decoded['r'], $decoded['g'], $decoded['b'])) {
            [$red, $green, $blue] = [$decoded['r'], $decoded['g'], $decoded['b']];
        } else {
            throw new InvalidArgumentException('RGB target color requires three channels.');
        }

        return (self::limitInteger((int)round((float)$red), 0, 255) << 16)
            + (self::limitInteger((int)round((float)$green), 0, 255) << 8)
            + self::limitInteger((int)round((float)$blue), 0, 255);
    }

    private static function rgbToHueSaturation(int $red, int $green, int $blue): string
    {
        $r = $red / 255;
        $g = $green / 255;
        $b = $blue / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $hue = 0.0;
        if ($delta !== 0.0) {
            if ($max === $r) {
                $hue = 60 * fmod((($g - $b) / $delta), 6);
            } elseif ($max === $g) {
                $hue = 60 * ((($b - $r) / $delta) + 2);
            } else {
                $hue = 60 * ((($r - $g) / $delta) + 4);
            }
        }
        if ($hue < 0) {
            $hue += 360;
        }
        $saturation = $max === 0.0 ? 0.0 : ($delta / $max) * 100;

        return self::encodeJson([round($hue, 3), round($saturation, 3)]);
    }

    private static function hueSaturationToRgb(float $hue, float $saturation): int
    {
        $hue = fmod($hue, 360);
        if ($hue < 0) {
            $hue += 360;
        }
        $saturation = max(0.0, min(100.0, $saturation)) / 100;
        $chroma = $saturation;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        [$red, $green, $blue] = match (true) {
            $hue < 60 => [$chroma, $x, 0.0],
            $hue < 120 => [$x, $chroma, 0.0],
            $hue < 180 => [0.0, $chroma, $x],
            $hue < 240 => [0.0, $x, $chroma],
            $hue < 300 => [$x, 0.0, $chroma],
            default => [$chroma, 0.0, $x],
        };
        $match = 1 - $chroma;

        return (self::limitInteger((int)round(($red + $match) * 255), 0, 255) << 16)
            + (self::limitInteger((int)round(($green + $match) * 255), 0, 255) << 8)
            + self::limitInteger((int)round(($blue + $match) * 255), 0, 255);
    }

    private static function scaleInteger(int $value, int $sourceMax, int $targetMax): int
    {
        return self::limitInteger((int)round($value * $targetMax / $sourceMax), 0, $targetMax);
    }

    private static function kelvinToMired(int $kelvin): int
    {
        return $kelvin > 0 ? (int)round(1000000 / $kelvin) : 0;
    }

    private static function miredToKelvin(int $mired): int
    {
        return $mired > 0 ? (int)round(1000000 / $mired) : 0;
    }

    private static function limitInteger(int $value, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, $value));
    }

    /** @param array<string, mixed> $source */
    private static function requireString(array $source, string $key): string
    {
        if (!array_key_exists($key, $source) || !is_string($source[$key])) {
            throw new InvalidArgumentException('configuration.' . $key . ' must be a string.');
        }
        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private static function optionalString(array $source, string $key): ?string
    {
        if (!array_key_exists($key, $source) || $source[$key] === null) {
            return null;
        }
        if (!is_string($source[$key])) {
            throw new InvalidArgumentException('configuration.' . $key . ' must be a string.');
        }
        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private static function requireBoolean(array $source, string $key): bool
    {
        if (!array_key_exists($key, $source) || !is_bool($source[$key])) {
            throw new InvalidArgumentException('configuration.' . $key . ' must be boolean.');
        }
        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private static function optionalNonNegativeInteger(array $source, string $key): int
    {
        if (!array_key_exists($key, $source) || !is_int($source[$key]) || $source[$key] < 0) {
            throw new InvalidArgumentException('configuration.' . $key . ' must be a non-negative integer.');
        }
        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private static function requireIntegerRange(
        array $source,
        string $key,
        int $minimum,
        int $maximum,
        string $path
    ): int {
        if (!array_key_exists($key, $source) || !is_int($source[$key])) {
            throw new InvalidArgumentException($path . '.' . $key . ' must be an integer.');
        }
        if ($source[$key] < $minimum || $source[$key] > $maximum) {
            throw new InvalidArgumentException(sprintf(
                '%s.%s must be between %d and %d.',
                $path,
                $key,
                $minimum,
                $maximum
            ));
        }
        return $source[$key];
    }

    /** @param array<string, mixed> $source @param list<string> $allowed */
    private static function requireEnum(array $source, string $key, array $allowed): string
    {
        return self::normalizeEnumValue($source[$key] ?? null, $allowed, 'configuration.' . $key);
    }

    /** @param list<string> $allowed */
    private static function normalizeEnumValue(mixed $value, array $allowed, string $path): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($path . ' has an unsupported value.');
        }
        return $value;
    }

    private static function normalizeBooleanValue(mixed $value, string $path): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($path . ' must be boolean.');
        }
        return $value;
    }

    /** @param array<mixed> $value */
    private static function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return $encoded;
    }
}
