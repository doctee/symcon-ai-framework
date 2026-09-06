<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksSymconArchiveAdapter;

/** @var array<string, mixed> */
$GLOBALS['owntracksAdapterFake'] = [];

function IPS_InstanceExists(int $id): bool
{
    $instances = $GLOBALS['owntracksAdapterFake']['instances'] ?? [];

    return is_array($instances) && in_array($id, $instances, true);
}

/** @return list<int> */
function IPS_GetInstanceListByModuleID(string $moduleID): array
{
    $moduleInstances = $GLOBALS['owntracksAdapterFake']['moduleInstances'] ?? [];
    if (!is_array($moduleInstances)) {
        return [];
    }
    $ids = $moduleInstances[$moduleID] ?? [];

    return is_array($ids) ? array_values($ids) : [];
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    $variables = $GLOBALS['owntracksAdapterFake']['variablesByIdent'] ?? [];
    if (!is_array($variables)) {
        return false;
    }
    $id = $variables[$parentID . ':' . $ident] ?? false;

    return is_int($id) ? $id : false;
}

function IPS_VariableExists(int $id): bool
{
    $types = $GLOBALS['owntracksAdapterFake']['variableTypes'] ?? [];

    return is_array($types) && isset($types[$id]);
}

/** @return array<string, int> */
function IPS_GetVariable(int $id): array
{
    $types = $GLOBALS['owntracksAdapterFake']['variableTypes'] ?? [];
    if (!is_array($types) || !isset($types[$id])) {
        return ['VariableType' => -1];
    }

    return ['VariableType' => $types[$id]];
}

function AC_GetLoggingStatus(int $archiveID, int $variableID): bool
{
    $logging = $GLOBALS['owntracksAdapterFake']['logging'] ?? [];

    return is_array($logging) && ($logging[$variableID] ?? false) === true;
}

/** @return list<array<string, mixed>>|false */
function AC_GetLoggedValues(
    int $archiveID,
    int $variableID,
    int $startTime,
    int $endTime,
    int $limit
): array|false {
    $GLOBALS['owntracksAdapterFake']['archiveCalls'][] = [
        'archiveId' => $archiveID,
        'variableId' => $variableID,
        'from' => $startTime,
        'to' => $endTime,
        'limit' => $limit,
    ];
    if (($GLOBALS['owntracksAdapterFake']['failVariableId'] ?? null) === $variableID) {
        return false;
    }
    $recordsByVariable = $GLOBALS['owntracksAdapterFake']['records'] ?? [];
    $records = is_array($recordsByVariable)
        ? ($recordsByVariable[$variableID] ?? [])
        : [];
    if (!is_array($records)) {
        return false;
    }
    $filtered = array_values(array_filter(
        $records,
        static function (array $record) use ($startTime, $endTime): bool {
            $timestamp = $record['TimeStamp'] ?? null;

            return is_int($timestamp)
                && $timestamp >= $startTime
                && $timestamp <= $endTime;
        }
    ));
    usort(
        $filtered,
        static fn (array $left, array $right): int =>
            $right['TimeStamp'] <=> $left['TimeStamp']
    );

    return array_slice($filtered, 0, $limit);
}

require_once __DIR__ . '/bootstrap.php';

/**
 * @param class-string<Throwable> $expectedClass
 */
function assertAdapterThrows(
    string $expectedClass,
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (Throwable $throwable) {
        assertTrue($throwable instanceof $expectedClass, $message);

        return;
    }

    throw new RuntimeException($message . ': no exception was thrown.');
}

$fixture = syntheticFixture();
$positionId = 4101;
$accuracyId = 4102;
$activityId = 4103;
$sourceRootId = 3101;
$archiveControlId = 2101;

/** @return array<string, mixed> */
function adapterConfiguration(): array
{
    return [
        'sources' => [
            [
                'sourceKey' => 'synthetic-a',
                'selectorValue' => 1,
                'sourceRootId' => 3101,
                'positionIdent' => 'position',
                'accuracyIdent' => 'acc',
                'activityIdent' => 'motionactivities',
            ],
            [
                'sourceKey' => 'synthetic-b',
                'selectorValue' => 2,
                'sourceRootId' => 3102,
                'positionIdent' => 'position',
                'accuracyIdent' => 'acc',
                'activityIdent' => 'motionactivities',
            ],
            [
                'sourceKey' => 'synthetic-c',
                'selectorValue' => 3,
                'sourceRootId' => 3103,
                'positionIdent' => 'position',
                'accuracyIdent' => 'acc',
                'activityIdent' => 'motionactivities',
            ],
        ],
    ];
}

$GLOBALS['owntracksAdapterFake'] = [
    'instances' => [$archiveControlId, $sourceRootId, 3102, 3103],
    'moduleInstances' => [
        '{43192F0B-135B-4CE7-A0A7-1475603F3060}' => [$archiveControlId],
    ],
    'variablesByIdent' => [
        $sourceRootId . ':position' => $positionId,
        $sourceRootId . ':acc' => $accuracyId,
        $sourceRootId . ':motionactivities' => $activityId,
    ],
    'variableTypes' => [
        $positionId => 3,
        $accuracyId => 2,
        $activityId => 1,
    ],
    'logging' => [
        $positionId => true,
        $accuracyId => true,
        $activityId => true,
    ],
    'records' => [
        $positionId => $fixture['positionRecordsNewestFirst'],
        $accuracyId => $fixture['accuracyRecordsNewestFirst'],
        $activityId => [
            ['TimeStamp' => 1704073500, 'Value' => 5],
            ['TimeStamp' => 1704067100, 'Value' => 1],
        ],
    ],
    'archiveCalls' => [],
];

$activityQuery = $fixture['query'];
$activityQuery['includeActivityEvidence'] = true;
$ready = OwnTracksSymconArchiveAdapter::loadSelected(
    adapterConfiguration(),
    1,
    $activityQuery,
    static fn (): int => 7
);
assertSameValue('ready', $ready['outcome'], 'Adapter ready outcome');
assertSameValue(
    'synthetic-a',
    $ready['result']['sourceKey'],
    'Selector mapping source key'
);
assertTrue(
    $ready['result']['adapter']['accuracyPrecedingRecordRead'],
    'Accuracy attribution must include one preceding change.'
);
assertSameValue(
    5,
    count($GLOBALS['owntracksAdapterFake']['archiveCalls']),
    'Adapter must perform bounded position, accuracy and activity reads.'
);
assertSameValue(
    2,
    count($ready['result']['activityEvidence']),
    'Activity evidence must include the preceding change.'
);
foreach ($GLOBALS['owntracksAdapterFake']['archiveCalls'] as $call) {
    assertTrue(
        $call['limit'] > 0
            && $call['limit'] <= $fixture['query']['maxArchiveRecords'],
        'Every archive read must be explicitly bounded.'
    );
}

$GLOBALS['owntracksAdapterFake']['archiveCalls'] = [];
$generationChecks = 0;
$superseded = OwnTracksSymconArchiveAdapter::loadSelected(
    adapterConfiguration(),
    1,
    $fixture['query'],
    static function () use (&$generationChecks): int {
        $generationChecks++;

        return $generationChecks === 1 ? 7 : 8;
    }
);
assertSameValue('superseded', $superseded['outcome'], 'Superseded outcome');
assertSameValue('position-read', $superseded['stage'], 'Superseded stage');
assertSameValue(
    1,
    count($GLOBALS['owntracksAdapterFake']['archiveCalls']),
    'A stale request must stop before accuracy reads.'
);

$GLOBALS['owntracksAdapterFake']['archiveCalls'] = [];
assertAdapterThrows(
    InvalidArgumentException::class,
    static fn (): array => OwnTracksSymconArchiveAdapter::loadSelected(
        adapterConfiguration(),
        99,
        $fixture['query'],
        static fn (): int => 7
    ),
    'Unknown selector value must fail closed.'
);
assertSameValue(
    0,
    count($GLOBALS['owntracksAdapterFake']['archiveCalls']),
    'Unknown selector value must not read archives.'
);

$invalidQuery = $fixture['query'];
$invalidQuery['renderMode'] = 'unbounded-everything';
assertAdapterThrows(
    InvalidArgumentException::class,
    static fn (): array => OwnTracksSymconArchiveAdapter::loadSelected(
        adapterConfiguration(),
        1,
        $invalidQuery,
        static fn (): int => 7
    ),
    'Invalid projection query must fail before archive access.'
);
assertSameValue(
    0,
    count($GLOBALS['owntracksAdapterFake']['archiveCalls']),
    'Invalid projection query must not read archives.'
);

$archiveInstances = $GLOBALS['owntracksAdapterFake']['moduleInstances'];
$GLOBALS['owntracksAdapterFake']['moduleInstances'] = [
    '{43192F0B-135B-4CE7-A0A7-1475603F3060}' => [],
];
assertAdapterThrows(
    RuntimeException::class,
    static fn (): array => OwnTracksSymconArchiveAdapter::loadSelected(
        adapterConfiguration(),
        1,
        $fixture['query'],
        static fn (): int => 7
    ),
    'Missing Archive Control must fail before archive access.'
);
$GLOBALS['owntracksAdapterFake']['moduleInstances'] = $archiveInstances;

$GLOBALS['owntracksAdapterFake']['logging'][$accuracyId] = false;
assertAdapterThrows(
    RuntimeException::class,
    static fn (): array => OwnTracksSymconArchiveAdapter::loadSelected(
        adapterConfiguration(),
        1,
        $fixture['query'],
        static fn (): int => 7
    ),
    'Unlogged required variable must fail closed.'
);
$GLOBALS['owntracksAdapterFake']['logging'][$accuracyId] = true;

$GLOBALS['owntracksAdapterFake']['failVariableId'] = $positionId;
assertAdapterThrows(
    RuntimeException::class,
    static fn (): array => OwnTracksSymconArchiveAdapter::loadSelected(
        adapterConfiguration(),
        1,
        $fixture['query'],
        static fn (): int => 7
    ),
    'Failed archive transport must fail closed.'
);
unset($GLOBALS['owntracksAdapterFake']['failVariableId']);

$limitedQuery = $fixture['query'];
$limitedQuery['maxArchiveRecords'] = 2;
$limited = OwnTracksSymconArchiveAdapter::loadSelected(
    adapterConfiguration(),
    1,
    $limitedQuery,
    static fn (): int => 7
);
assertTrue(
    $limited['result']['historyWindow']['archiveLimitReached'],
    'An exhausted archive bound must report a partial result.'
);
assertTrue(
    $limited['result']['adapter']['positionArchiveLimitReached'],
    'Position limit exhaustion must remain distinguishable.'
);
assertTrue(
    $limited['result']['adapter']['accuracyArchiveLimitReached'],
    'Accuracy limit exhaustion must remain distinguishable.'
);

$candidateSource = file_get_contents(
    __DIR__ . '/../candidate/OwnTracksSymconArchiveAdapter.php'
);
assertTrue(is_string($candidateSource), 'Adapter source must be readable.');
foreach (
    [
        '/\bRequestAction\s*\(/',
        '/\bSetValue\s*\(/',
        '/\bIPS_Set[A-Za-z0-9_]*\s*\(/',
        '/\bAC_(?:Add|Delete|ReAggregate)[A-Za-z0-9_]*\s*\(/',
    ] as $mutationPattern
) {
    assertTrue(
        preg_match($mutationPattern, $candidateSource) !== 1,
        'Read-only adapter contains a forbidden mutation path.'
    );
}

fwrite(STDOUT, "OwnTracks Symcon archive adapter tests passed.\n");
