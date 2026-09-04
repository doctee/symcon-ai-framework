<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use RuntimeException;

/**
 * Case-study-local read-only bridge from IP-Symcon archives to the track core.
 */
final class OwnTracksSymconArchiveAdapter
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/D';
    private const IDENT_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D';
    private const ARCHIVE_MODULE_GUID =
        '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const MAX_SOURCES = 8;
    private const MAX_ARCHIVE_RECORDS = 10000;

    /**
     * Resolve one selector value, read only its bounded archives and project it.
     *
     * The generation callback is evaluated before work, after each archive
     * phase and after projection. A changed generation discards the result.
     *
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $query
     * @param callable(): int $activeGeneration
     * @return array<string, mixed>
     */
    public static function loadSelected(
        array $configuration,
        int $selectorValue,
        array $query,
        callable $activeGeneration
    ): array {
        $requestGeneration = self::requestGeneration($query);
        if (self::generationChanged($requestGeneration, $activeGeneration)) {
            return self::superseded($requestGeneration, 'before-read');
        }

        $configuration = self::configuration($configuration);
        $source = self::selectedSource(
            $configuration['sources'],
            $selectorValue
        );
        self::assertCoreQuery($query, $source['sourceKey']);
        $window = self::readWindow($query);
        $includeActivity = $query['includeActivityEvidence'] ?? false;
        if (!is_bool($includeActivity)) {
            throw new InvalidArgumentException(
                'Activity evidence selection is invalid.'
            );
        }

        $archiveControlId = self::archiveControlId();
        $variables = self::sourceVariables(
            $archiveControlId,
            $source,
            $includeActivity
        );

        $positionRecords = self::loggedValues(
            $archiveControlId,
            $variables['positionId'],
            $window['from'],
            $window['to'] - 1,
            $window['maxArchiveRecords'],
            'Position archive read failed.'
        );
        $positionLimitReached = count($positionRecords)
            === $window['maxArchiveRecords'];

        if (self::generationChanged($requestGeneration, $activeGeneration)) {
            return self::superseded($requestGeneration, 'position-read');
        }

        $accuracyRecords = self::accuracyRecords(
            $archiveControlId,
            $variables['accuracyId'],
            $window
        );

        if (self::generationChanged($requestGeneration, $activeGeneration)) {
            return self::superseded($requestGeneration, 'accuracy-read');
        }

        $activityRecords = [
            'records' => [],
            'limitReached' => false,
            'precedingRecordRead' => false,
        ];
        if ($includeActivity) {
            $activityId = $variables['activityId'];
            if (!is_int($activityId)) {
                throw new RuntimeException(
                    'Activity variable resolution is invalid.'
                );
            }
            $activityRecords = self::activityRecords(
                $archiveControlId,
                $activityId,
                $window
            );
            if (
                self::generationChanged(
                    $requestGeneration,
                    $activeGeneration
                )
            ) {
                return self::superseded(
                    $requestGeneration,
                    'activity-read'
                );
            }
        }

        $coreQuery = $query;
        $coreQuery['sourceKey'] = $source['sourceKey'];
        $coreQuery['archiveLimitReached'] = $positionLimitReached
            || $accuracyRecords['limitReached'];
        $result = OwnTracksTrackCore::project(
            $positionRecords,
            $accuracyRecords['records'],
            $coreQuery
        );
        $result['activityEvidence'] = $activityRecords['records'];

        if (self::generationChanged($requestGeneration, $activeGeneration)) {
            return self::superseded($requestGeneration, 'projection');
        }

        $result['adapter'] = [
            'positionArchiveLimitReached' => $positionLimitReached,
            'accuracyArchiveLimitReached' => $accuracyRecords['limitReached'],
            'accuracyPrecedingRecordRead' =>
                $accuracyRecords['precedingRecordRead'],
            'activityArchiveLimitReached' =>
                $activityRecords['limitReached'],
            'activityPrecedingRecordRead' =>
                $activityRecords['precedingRecordRead'],
        ];

        return [
            'outcome' => 'ready',
            'requestGeneration' => $requestGeneration,
            'result' => $result,
        ];
    }

    /** @param array<string, mixed> $query */
    private static function requestGeneration(array $query): int
    {
        $generation = $query['requestGeneration'] ?? null;
        if (!is_int($generation) || $generation <= 0) {
            throw new InvalidArgumentException(
                'Request generation must be a positive integer.'
            );
        }

        return $generation;
    }

    /**
     * @param callable(): int $activeGeneration
     * @phpstan-impure
     */
    private static function generationChanged(
        int $requestGeneration,
        callable $activeGeneration
    ): bool {
        $active = $activeGeneration();
        if ($active <= 0) {
            throw new RuntimeException(
                'Active request generation must be positive.'
            );
        }

        return OwnTracksTrackCore::isSuperseded(
            $requestGeneration,
            $active
        );
    }

    /** @return array<string, int|string> */
    private static function superseded(int $generation, string $stage): array
    {
        return [
            'outcome' => 'superseded',
            'requestGeneration' => $generation,
            'stage' => $stage,
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{sources: list<array<string, mixed>>}
     */
    private static function configuration(array $configuration): array
    {
        $sources = $configuration['sources'] ?? null;
        if (
            !is_array($sources)
            || $sources === []
            || count($sources) > self::MAX_SOURCES
        ) {
            throw new InvalidArgumentException(
                'Archive adapter configuration is invalid.'
            );
        }

        $normalized = [];
        $sourceKeys = [];
        $selectorValues = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                throw new InvalidArgumentException(
                    'Archive source configuration is invalid.'
                );
            }
            $sourceKey = $source['sourceKey'] ?? null;
            $selectorValue = $source['selectorValue'] ?? null;
            $sourceRootId = $source['sourceRootId'] ?? null;
            $positionIdent = $source['positionIdent'] ?? null;
            $accuracyIdent = $source['accuracyIdent'] ?? null;
            $activityIdent = $source['activityIdent'] ?? null;
            if (
                !is_string($sourceKey)
                || preg_match(self::KEY_PATTERN, $sourceKey) !== 1
                || !is_int($selectorValue)
                || !is_int($sourceRootId)
                || $sourceRootId <= 0
                || !self::validIdent($positionIdent)
                || !self::validIdent($accuracyIdent)
                || !self::validIdent($activityIdent)
                || isset($sourceKeys[$sourceKey])
                || isset($selectorValues[$selectorValue])
            ) {
                throw new InvalidArgumentException(
                    'Archive source mapping is invalid or duplicated.'
                );
            }
            $sourceKeys[$sourceKey] = true;
            $selectorValues[$selectorValue] = true;
            $normalized[] = [
                'sourceKey' => $sourceKey,
                'selectorValue' => $selectorValue,
                'sourceRootId' => $sourceRootId,
                'positionIdent' => $positionIdent,
                'accuracyIdent' => $accuracyIdent,
                'activityIdent' => $activityIdent,
            ];
        }

        return ['sources' => $normalized];
    }

    private static function validIdent(mixed $ident): bool
    {
        return is_string($ident)
            && preg_match(self::IDENT_PATTERN, $ident) === 1;
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return array<string, mixed>
     */
    private static function selectedSource(
        array $sources,
        int $selectorValue
    ): array {
        foreach ($sources as $source) {
            if ($source['selectorValue'] === $selectorValue) {
                return $source;
            }
        }

        throw new InvalidArgumentException(
            'Selector value has no configured source.'
        );
    }

    /** @param array<string, mixed> $query */
    private static function assertCoreQuery(array $query, mixed $sourceKey): void
    {
        if (!is_string($sourceKey)) {
            throw new RuntimeException('Selected source key is invalid.');
        }
        $validationQuery = $query;
        $validationQuery['sourceKey'] = $sourceKey;
        $validationQuery['archiveLimitReached'] = false;
        OwnTracksTrackCore::project([], [], $validationQuery);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{from: int, to: int, maxArchiveRecords: int}
     */
    private static function readWindow(array $query): array
    {
        $from = $query['from'] ?? null;
        $to = $query['to'] ?? null;
        $limit = $query['maxArchiveRecords'] ?? null;
        if (
            !is_int($from)
            || $from <= 1
            || !is_int($to)
            || $to <= $from
            || !is_int($limit)
            || $limit <= 0
            || $limit > self::MAX_ARCHIVE_RECORDS
        ) {
            throw new InvalidArgumentException(
                'Archive read window is invalid.'
            );
        }

        return [
            'from' => $from,
            'to' => $to,
            'maxArchiveRecords' => $limit,
        ];
    }

    private static function archiveControlId(): int
    {
        $archiveControlIds = IPS_GetInstanceListByModuleID(
            self::ARCHIVE_MODULE_GUID
        );
        if (count($archiveControlIds) !== 1) {
            throw new RuntimeException(
                'Expected exactly one Archive Control instance.'
            );
        }
        $archiveControlId = $archiveControlIds[0];
        if ($archiveControlId <= 0 || !IPS_InstanceExists($archiveControlId)) {
            throw new RuntimeException('Archive Control instance is missing.');
        }

        return $archiveControlId;
    }

    /**
     * @param array<string, mixed> $source
     * @return array{positionId: int, accuracyId: int, activityId: int|null}
     */
    private static function sourceVariables(
        int $archiveControlId,
        array $source,
        bool $includeActivity
    ): array {
        $sourceRootId = $source['sourceRootId'];
        if (!is_int($sourceRootId) || !IPS_InstanceExists($sourceRootId)) {
            throw new RuntimeException('Configured source root is missing.');
        }

        $positionId = self::variableByIdent(
            $sourceRootId,
            $source['positionIdent'],
            3
        );
        $accuracyId = self::variableByIdent(
            $sourceRootId,
            $source['accuracyIdent'],
            [1, 2]
        );
        $activityId = $includeActivity
            ? self::variableByIdent(
                $sourceRootId,
                $source['activityIdent'],
                1
            )
            : null;
        foreach ([$positionId, $accuracyId, $activityId] as $variableId) {
            if ($variableId === null) {
                continue;
            }
            if (!AC_GetLoggingStatus($archiveControlId, $variableId)) {
                throw new RuntimeException(
                    'Required source variable is not logged.'
                );
            }
        }

        return [
            'positionId' => $positionId,
            'accuracyId' => $accuracyId,
            'activityId' => $activityId,
        ];
    }

    /**
     * @param int|list<int> $expectedTypes
     */
    private static function variableByIdent(
        int $parentId,
        mixed $ident,
        int|array $expectedTypes
    ): int {
        if (!is_string($ident)) {
            throw new RuntimeException('Configured source Ident is invalid.');
        }
        $variableId = @IPS_GetObjectIDByIdent($ident, $parentId);
        if (
            $variableId === false
            || $variableId <= 0
            || !IPS_VariableExists($variableId)
        ) {
            throw new RuntimeException(
                'Required source variable is missing.'
            );
        }
        $variable = IPS_GetVariable($variableId);
        $variableType = $variable['VariableType'];
        $types = is_array($expectedTypes)
            ? $expectedTypes
            : [$expectedTypes];
        if (!in_array($variableType, $types, true)) {
            throw new RuntimeException(
                'Required source variable has an incompatible type.'
            );
        }

        return $variableId;
    }

    /**
     * @param array{from: int, to: int, maxArchiveRecords: int} $window
     * @return array{
     *   records: list<array<string, mixed>>,
     *   limitReached: bool,
     *   precedingRecordRead: bool
     * }
     */
    private static function accuracyRecords(
        int $archiveControlId,
        int $accuracyId,
        array $window
    ): array {
        $preceding = self::loggedValues(
            $archiveControlId,
            $accuracyId,
            0,
            $window['from'] - 1,
            1,
            'Preceding accuracy archive read failed.'
        );
        $windowLimit = $window['maxArchiveRecords'] - count($preceding);
        $inside = [];
        if ($windowLimit > 0) {
            $inside = self::loggedValues(
                $archiveControlId,
                $accuracyId,
                $window['from'],
                $window['to'] - 1,
                $windowLimit,
                'Accuracy archive read failed.'
            );
        }

        return [
            'records' => array_merge($inside, $preceding),
            'limitReached' => $windowLimit === 0
                || count($inside) === $windowLimit,
            'precedingRecordRead' => $preceding !== [],
        ];
    }

    /**
     * @param array{from: int, to: int, maxArchiveRecords: int} $window
     * @return array{
     *   records: list<array{observedAt: int, value: int}>,
     *   limitReached: bool,
     *   precedingRecordRead: bool
     * }
     */
    private static function activityRecords(
        int $archiveControlId,
        int $activityId,
        array $window
    ): array {
        $preceding = self::loggedValues(
            $archiveControlId,
            $activityId,
            0,
            $window['from'] - 1,
            1,
            'Preceding activity archive read failed.'
        );
        $windowLimit = $window['maxArchiveRecords'] - count($preceding);
        $inside = [];
        if ($windowLimit > 0) {
            $inside = self::loggedValues(
                $archiveControlId,
                $activityId,
                $window['from'],
                $window['to'] - 1,
                $windowLimit,
                'Activity archive read failed.'
            );
        }
        $normalized = [];
        foreach (array_merge($inside, $preceding) as $record) {
            $observedAt = $record['TimeStamp'] ?? null;
            $value = $record['Value'] ?? null;
            if (
                !is_int($observedAt)
                || (!is_int($value) && !is_float($value))
                || !is_finite((float) $value)
                || (float) (int) $value !== (float) $value
            ) {
                continue;
            }
            $normalized[] = [
                'observedAt' => $observedAt,
                'value' => (int) $value,
            ];
        }

        return [
            'records' => $normalized,
            'limitReached' => $windowLimit === 0
                || count($inside) === $windowLimit,
            'precedingRecordRead' => $preceding !== [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loggedValues(
        int $archiveControlId,
        int $variableId,
        int $from,
        int $to,
        int $limit,
        string $failureMessage
    ): array {
        $records = AC_GetLoggedValues(
            $archiveControlId,
            $variableId,
            $from,
            $to,
            $limit
        );
        if (!is_array($records) || count($records) > $limit) {
            throw new RuntimeException($failureMessage);
        }

        $normalized = [];
        foreach ($records as $record) {
            $normalized[] = $record;
        }

        return $normalized;
    }
}
