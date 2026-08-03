<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;

/**
 * Pure configuration and mapping logic for the Hue Wall adapter candidate.
 *
 * The Hue Wall Module action payload only reports a side and a press/release
 * gesture. It does not report an absolute rocker position. Consequently, every
 * accepted press toggles the currently confirmed ControlLight STATE facade.
 * The legacy invertTopBottom setting is retained as migration metadata only;
 * after feedback synchronization, the legacy algorithm was algebraically
 * equivalent to the same toggle operation.
 */
final class HueWallSwitchCore
{
    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function normalizeConfiguration(array $configuration): array
    {
        $version = self::nonEmptyString($configuration['version'] ?? 'HueWallSwitch-v2-candidate', 'version');
        $debounceMilliseconds = self::boundedInteger(
            $configuration['debounceMilliseconds'] ?? 300,
            'debounceMilliseconds',
            0,
            2000
        );

        $confirmation = self::arrayValue($configuration['confirmation'] ?? [], 'confirmation');
        $confirmationTimeoutMilliseconds = self::boundedInteger(
            $confirmation['timeoutMilliseconds'] ?? 500,
            'confirmation.timeoutMilliseconds',
            0,
            5000
        );
        $confirmationPollIntervalMilliseconds = self::boundedInteger(
            $confirmation['pollIntervalMilliseconds'] ?? 50,
            'confirmation.pollIntervalMilliseconds',
            10,
            1000
        );

        $semaphore = self::arrayValue($configuration['semaphore'] ?? [], 'semaphore');
        $semaphoreTimeoutMilliseconds = self::boundedInteger(
            $semaphore['timeoutMilliseconds'] ?? 4000,
            'semaphore.timeoutMilliseconds',
            0,
            5000
        );

        $rawTargets = self::arrayValue($configuration['targets'] ?? null, 'targets');
        if ($rawTargets === []) {
            throw new InvalidArgumentException('targets must not be empty.');
        }

        $targets = [];
        $targetVariableIDs = [];
        $eventIdents = [];
        foreach ($rawTargets as $targetKey => $rawTarget) {
            $normalizedKey = self::stableKey($targetKey, 'targets key');
            $target = self::arrayValue($rawTarget, sprintf('targets.%s', $normalizedKey));
            $stateVariableID = self::positiveInteger(
                $target['stateVariableID'] ?? null,
                sprintf('targets.%s.stateVariableID', $normalizedKey)
            );

            if (isset($targetVariableIDs[$stateVariableID])) {
                throw new InvalidArgumentException(sprintf(
                    'Target stateVariableID %d is configured more than once.',
                    $stateVariableID
                ));
            }

            $targetVariableIDs[$stateVariableID] = true;
            $feedbackEventIdent = self::technicalIdent(
                $target['feedbackEventIdent'] ?? 'HWS_EV_FEEDBACK_' . strtoupper($normalizedKey),
                sprintf('targets.%s.feedbackEventIdent', $normalizedKey)
            );
            self::assertUniqueEventIdent($feedbackEventIdent, $eventIdents);
            $targets[$normalizedKey] = [
                'key' => $normalizedKey,
                'name' => self::nonEmptyString($target['name'] ?? $normalizedKey, sprintf('targets.%s.name', $normalizedKey)),
                'stateVariableID' => $stateVariableID,
                'feedbackEventIdent' => $feedbackEventIdent,
            ];
        }

        $rawSources = self::arrayValue($configuration['sources'] ?? null, 'sources');
        if ($rawSources === []) {
            throw new InvalidArgumentException('sources must not be empty.');
        }

        $sources = [];
        $sourceVariableIDs = [];
        foreach ($rawSources as $index => $rawSource) {
            $source = self::arrayValue($rawSource, sprintf('sources.%s', (string) $index));
            $sourceKey = self::stableKey($source['key'] ?? null, sprintf('sources.%s.key', (string) $index));
            if (isset($sources[$sourceKey])) {
                throw new InvalidArgumentException(sprintf('Source key "%s" is configured more than once.', $sourceKey));
            }

            $sourceVariableID = self::positiveInteger(
                $source['sourceVariableID'] ?? null,
                sprintf('sources.%s.sourceVariableID', $sourceKey)
            );
            if (isset($sourceVariableIDs[$sourceVariableID])) {
                throw new InvalidArgumentException(sprintf(
                    'Source variable ID %d is configured more than once.',
                    $sourceVariableID
                ));
            }

            $leftTargetKey = self::stableKey(
                $source['leftTargetKey'] ?? null,
                sprintf('sources.%s.leftTargetKey', $sourceKey)
            );
            $rightTargetKey = self::stableKey(
                $source['rightTargetKey'] ?? null,
                sprintf('sources.%s.rightTargetKey', $sourceKey)
            );
            foreach ([$leftTargetKey, $rightTargetKey] as $targetKey) {
                if (!isset($targets[$targetKey])) {
                    throw new InvalidArgumentException(sprintf(
                        'Source "%s" references unknown target "%s".',
                        $sourceKey,
                        $targetKey
                    ));
                }
            }

            $actionEventIdent = self::technicalIdent(
                $source['actionEventIdent'] ?? 'HWS_EV_ACTION_' . strtoupper($sourceKey),
                sprintf('sources.%s.actionEventIdent', $sourceKey)
            );
            self::assertUniqueEventIdent($actionEventIdent, $eventIdents);
            $sourceVariableIDs[$sourceVariableID] = true;
            $sources[$sourceKey] = [
                'key' => $sourceKey,
                'name' => self::nonEmptyString($source['name'] ?? $sourceKey, sprintf('sources.%s.name', $sourceKey)),
                'sourceVariableID' => $sourceVariableID,
                'actionEventIdent' => $actionEventIdent,
                'swapLeftRight' => self::booleanValue($source['swapLeftRight'] ?? false, sprintf('sources.%s.swapLeftRight', $sourceKey)),
                'invertTopBottom' => self::booleanValue($source['invertTopBottom'] ?? false, sprintf('sources.%s.invertTopBottom', $sourceKey)),
                'leftTargetKey' => $leftTargetKey,
                'rightTargetKey' => $rightTargetKey,
            ];
        }

        return [
            'version' => $version,
            'debounceMilliseconds' => $debounceMilliseconds,
            'confirmation' => [
                'timeoutMilliseconds' => $confirmationTimeoutMilliseconds,
                'pollIntervalMilliseconds' => $confirmationPollIntervalMilliseconds,
            ],
            'semaphore' => [
                'timeoutMilliseconds' => $semaphoreTimeoutMilliseconds,
            ],
            'debug' => self::booleanValue($configuration['debug'] ?? false, 'debug'),
            'targets' => $targets,
            'sources' => $sources,
        ];
    }

    /**
     * @return array{physicalSide: string}|null
     */
    public static function normalizeAction(string $rawAction): ?array
    {
        $action = strtolower(trim($rawAction));
        $leftActions = [
            'left_press_release',
            'left press release',
            'links drücken und loslassen',
            'links druecken und loslassen',
        ];
        $rightActions = [
            'right_press_release',
            'right press release',
            'rechts drücken und loslassen',
            'rechts druecken und loslassen',
        ];

        if (in_array($action, $leftActions, true)) {
            return ['physicalSide' => 'left'];
        }
        if (in_array($action, $rightActions, true)) {
            return ['physicalSide' => 'right'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $normalizedSource
     */
    public static function targetKeyForAction(array $normalizedSource, string $rawAction): ?string
    {
        $action = self::normalizeAction($rawAction);
        if ($action === null) {
            return null;
        }

        $physicalSide = $action['physicalSide'];
        $logicalSide = $physicalSide;
        if ((bool) $normalizedSource['swapLeftRight']) {
            $logicalSide = $physicalSide === 'left' ? 'right' : 'left';
        }

        return (string) $normalizedSource[$logicalSide . 'TargetKey'];
    }

    public static function desiredState(bool $confirmedState): bool
    {
        return !$confirmedState;
    }

    private static function stableKey(mixed $value, string $path): string
    {
        $key = self::nonEmptyString($value, $path);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $key) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '%s must start with a letter and contain only letters, digits and underscores.',
                $path
            ));
        }

        return $key;
    }

    private static function technicalIdent(mixed $value, string $path): string
    {
        $ident = self::nonEmptyString($value, $path);
        if (preg_match('/^[A-Za-z0-9_]+$/', $ident) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '%s may contain only letters, digits and underscores.',
                $path
            ));
        }

        return $ident;
    }

    /**
     * @param array<string, true> $eventIdents
     */
    private static function assertUniqueEventIdent(string $ident, array &$eventIdents): void
    {
        if (isset($eventIdents[$ident])) {
            throw new InvalidArgumentException('Hue Wall event Ident is configured more than once: ' . $ident);
        }
        $eventIdents[$ident] = true;
    }

    private static function nonEmptyString(mixed $value, string $path): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must be a non-empty string.', $path));
        }

        return trim($value);
    }

    private static function positiveInteger(mixed $value, string $path): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $path));
        }

        return $value;
    }

    private static function boundedInteger(mixed $value, string $path, int $minimum, int $maximum): int
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException(sprintf(
                '%s must be an integer between %d and %d.',
                $path,
                $minimum,
                $maximum
            ));
        }

        return $value;
    }

    private static function booleanValue(mixed $value, string $path): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a boolean.', $path));
        }

        return $value;
    }

    /**
     * @return array<mixed>
     */
    private static function arrayValue(mixed $value, string $path): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('%s must be an array.', $path));
        }

        return $value;
    }
}
