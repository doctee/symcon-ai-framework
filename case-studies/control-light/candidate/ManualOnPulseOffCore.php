<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;

/**
 * Pure contract for lamps that can be switched on manually but are switched
 * off remotely by briefly interrupting and automatically restoring supply.
 */
final class ManualOnPulseOffCore
{
    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function normalizeConfiguration(array $configuration): array
    {
        $normalized = [
            'version' => self::string($configuration, 'version', 'manual-on-pulse-off-v1'),
            'powerVariableID' => self::positiveInteger($configuration, 'powerVariableID'),
            'relayVariableID' => self::positiveInteger($configuration, 'relayVariableID'),
            'powerOnThreshold' => self::nonNegativeNumber(
                $configuration,
                'powerOnThreshold',
                1.0
            ),
            'observationMilliseconds' => self::integerRange(
                $configuration,
                'observationMilliseconds',
                0,
                5_000,
                1_200
            ),
            'confirmationTimeoutMilliseconds' => self::integerRange(
                $configuration,
                'confirmationTimeoutMilliseconds',
                500,
                15_000,
                6_500
            ),
            'pollIntervalMilliseconds' => self::integerRange(
                $configuration,
                'pollIntervalMilliseconds',
                10,
                1_000,
                100
            ),
            'semaphoreTimeoutMilliseconds' => self::integerRange(
                $configuration,
                'semaphoreTimeoutMilliseconds',
                0,
                30_000,
                5_000
            ),
        ];

        if (
            $normalized['pollIntervalMilliseconds']
            > $normalized['confirmationTimeoutMilliseconds']
        ) {
            throw new InvalidArgumentException(
                'pollIntervalMilliseconds must not exceed confirmationTimeoutMilliseconds.'
            );
        }

        return $normalized;
    }

    public static function isLampOn(mixed $power, float $threshold): bool
    {
        if (!is_int($power) && !is_float($power)) {
            throw new InvalidArgumentException('Power feedback must be numeric.');
        }
        if (!is_finite((float)$power)) {
            throw new InvalidArgumentException('Power feedback must be finite.');
        }

        return (float)$power > $threshold;
    }

    public static function plan(bool $requestedState, bool $observedState): string
    {
        if ($requestedState) {
            return $observedState ? 'already_confirmed' : 'manual_activation_required';
        }

        return $observedState ? 'pulse_off' : 'observe_before_idempotent_off';
    }

    /** @param array<string, mixed> $configuration */
    private static function positiveInteger(array $configuration, string $key): int
    {
        $value = $configuration[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException($key . ' must be a positive integer.');
        }

        return $value;
    }

    /** @param array<string, mixed> $configuration */
    private static function integerRange(
        array $configuration,
        string $key,
        int $minimum,
        int $maximum,
        int $default
    ): int {
        $value = $configuration[$key] ?? $default;
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException(sprintf(
                '%s must be an integer from %d through %d.',
                $key,
                $minimum,
                $maximum
            ));
        }

        return $value;
    }

    /** @param array<string, mixed> $configuration */
    private static function nonNegativeNumber(
        array $configuration,
        string $key,
        float $default
    ): float {
        $value = $configuration[$key] ?? $default;
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0) {
            throw new InvalidArgumentException($key . ' must be a finite non-negative number.');
        }

        return (float)$value;
    }

    /** @param array<string, mixed> $configuration */
    private static function string(array $configuration, string $key, string $default): string
    {
        $value = $configuration[$key] ?? $default;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($key . ' must be a non-empty string.');
        }

        return trim($value);
    }
}
