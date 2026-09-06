<?php

declare(strict_types=1);

const IS_ACTIVE = 102;
const INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN = 2;

abstract class IPSModuleStrict
{
    protected int $InstanceID = 7001;
    /** @var array<string, mixed> */
    private array $properties = [];
    /** @var array<string, string> */
    private array $attributes = [];
    /** @var list<int> */
    private array $references = [];
    /** @var list<array<string, mixed>> */
    private array $updates = [];
    /** @var list<array<string, string>> */
    private array $debug = [];
    private int $status = 0;
    private int $visualizationType = 0;
    private int $applyCalls = 0;
    /** @var list<array{action: string, address: string}> */
    private array $hookCalls = [];

    public function Create(): void
    {
    }

    public function ApplyChanges(): void
    {
        $this->applyCalls++;
    }

    protected function RegisterPropertyString(string $name, string $value): void
    {
        $this->properties[$name] ??= $value;
    }

    protected function RegisterPropertyInteger(string $name, int $value): void
    {
        $this->properties[$name] ??= $value;
    }

    protected function RegisterPropertyFloat(string $name, float $value): void
    {
        $this->properties[$name] ??= $value;
    }

    protected function RegisterPropertyBoolean(string $name, bool $value): void
    {
        $this->properties[$name] ??= $value;
    }

    protected function RegisterAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] ??= $value;
    }

    protected function RegisterHook(string $address): bool
    {
        $this->hookCalls[] = ['action' => 'register', 'address' => $address];

        return true;
    }

    protected function UnregisterHook(string $address): bool
    {
        $this->hookCalls[] = ['action' => 'unregister', 'address' => $address];

        return true;
    }

    protected function ReadPropertyString(string $name): string
    {
        return (string) ($this->properties[$name] ?? '');
    }

    protected function ReadPropertyInteger(string $name): int
    {
        return (int) ($this->properties[$name] ?? 0);
    }

    protected function ReadPropertyFloat(string $name): float
    {
        return (float) ($this->properties[$name] ?? 0.0);
    }

    protected function ReadPropertyBoolean(string $name): bool
    {
        return (bool) ($this->properties[$name] ?? false);
    }

    protected function ReadAttributeString(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    protected function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    protected function RegisterReference(int $objectID): void
    {
        if (!in_array($objectID, $this->references, true)) {
            $this->references[] = $objectID;
        }
    }

    protected function UnregisterReference(int $objectID): void
    {
        $this->references = array_values(array_filter(
            $this->references,
            static fn (int $referenceID): bool => $referenceID !== $objectID
        ));
    }

    protected function SetVisualizationType(int $type): void
    {
        $this->visualizationType = $type;
    }

    protected function SetStatus(int $status): void
    {
        $this->status = $status;
    }

    protected function UpdateVisualizationValue(string $value): void
    {
        $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Visualization update is invalid.');
        }
        $this->updates[] = $decoded;
    }

    protected function SendDebug(string $message, string $data, int $format): void
    {
        $this->debug[] = ['message' => $message, 'data' => $data];
    }

    public function testSetProperty(string $name, mixed $value): void
    {
        $this->properties[$name] = $value;
    }

    public function testProperty(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }

    /** @return list<int> */
    public function testReferences(): array
    {
        sort($this->references, SORT_NUMERIC);

        return $this->references;
    }

    /** @return array<string, mixed> */
    public function testLastUpdate(): array
    {
        return $this->updates[count($this->updates) - 1] ?? [];
    }

    /** @return list<array<string, string>> */
    public function testDebug(): array
    {
        return $this->debug;
    }

    public function testStatus(): int
    {
        return $this->status;
    }

    public function testVisualizationType(): int
    {
        return $this->visualizationType;
    }

    public function testApplyCalls(): int
    {
        return $this->applyCalls;
    }

    public function testAttribute(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    public function testSetAttribute(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    /** @return list<array{action: string, address: string}> */
    public function testHookCalls(): array
    {
        return $this->hookCalls;
    }
}

/** @var array<string, mixed> */
$GLOBALS['ownTracksRuntimeFake'] = [];

function IPS_InstanceExists(int $id): bool
{
    $instances = $GLOBALS['ownTracksRuntimeFake']['instances'] ?? [];

    return is_array($instances) && in_array($id, $instances, true);
}

function IPS_ObjectExists(int $id): bool
{
    $objects = $GLOBALS['ownTracksRuntimeFake']['objects'] ?? [];

    return is_array($objects) && in_array($id, $objects, true);
}

/** @return array<string, mixed> */
function IPS_GetInstance(int $id): array
{
    $moduleIds = $GLOBALS['ownTracksRuntimeFake']['instanceModuleIds'] ?? [];

    return [
        'ModuleInfo' => [
            'ModuleID' => is_array($moduleIds)
                ? ($moduleIds[$id] ?? '')
                : '',
        ],
    ];
}

function SAEFLOCATION_GetDescriptor(int $instanceId): string
{
    $descriptors = $GLOBALS['ownTracksRuntimeFake']['locationDescriptors'] ?? [];
    $descriptor = is_array($descriptors)
        ? ($descriptors[$instanceId] ?? ['success' => false])
        : ['success' => false];

    return json_encode($descriptor, JSON_THROW_ON_ERROR);
}

function GetValue(int $id): mixed
{
    $values = $GLOBALS['ownTracksRuntimeFake']['values'] ?? [];

    return is_array($values) ? ($values[$id] ?? null) : null;
}

/** @return list<int> */
function IPS_GetInstanceListByModuleID(string $moduleID): array
{
    $instances = $GLOBALS['ownTracksRuntimeFake']['moduleInstances'] ?? [];
    $result = is_array($instances) ? ($instances[$moduleID] ?? []) : [];

    return is_array($result) ? array_values($result) : [];
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
{
    $mapping = $GLOBALS['ownTracksRuntimeFake']['variablesByIdent'] ?? [];
    $id = is_array($mapping) ? ($mapping[$parentID . ':' . $ident] ?? false) : false;

    return is_int($id) ? $id : false;
}

function IPS_VariableExists(int $id): bool
{
    $types = $GLOBALS['ownTracksRuntimeFake']['variableTypes'] ?? [];

    return is_array($types) && isset($types[$id]);
}

/** @return array<string, int> */
function IPS_GetVariable(int $id): array
{
    $types = $GLOBALS['ownTracksRuntimeFake']['variableTypes'] ?? [];
    $updated = $GLOBALS['ownTracksRuntimeFake']['variableUpdated'] ?? [];

    return [
        'VariableType' => is_array($types) ? ($types[$id] ?? -1) : -1,
        'VariableUpdated' => is_array($updated) ? ($updated[$id] ?? 0) : 0,
    ];
}

function AC_GetLoggingStatus(int $archiveID, int $variableID): bool
{
    $logging = $GLOBALS['ownTracksRuntimeFake']['logging'] ?? [];

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
    $GLOBALS['ownTracksRuntimeFake']['archiveCalls'][] = [
        'variableID' => $variableID,
        'startTime' => $startTime,
        'endTime' => $endTime,
        'limit' => $limit,
    ];
    $recordsByVariable = $GLOBALS['ownTracksRuntimeFake']['records'] ?? [];
    $records = is_array($recordsByVariable)
        ? ($recordsByVariable[$variableID] ?? [])
        : [];
    if (!is_array($records)) {
        return false;
    }
    $filtered = array_values(array_filter(
        $records,
        static fn (array $record): bool =>
            is_int($record['TimeStamp'] ?? null)
            && $record['TimeStamp'] >= $startTime
            && $record['TimeStamp'] <= $endTime
    ));
    usort(
        $filtered,
        static fn (array $left, array $right): int =>
            $right['TimeStamp'] <=> $left['TimeStamp']
    );

    return array_slice($filtered, 0, $limit);
}

require_once __DIR__ . '/bootstrap.php';
$runtimeModuleFile = defined('OWNTRACKS_RUNTIME_MODULE_FILE')
    ? constant('OWNTRACKS_RUNTIME_MODULE_FILE')
    : __DIR__ . '/../candidate/runtime/OwnTracksPositionMap/module.php';
if (!is_string($runtimeModuleFile) || !is_file($runtimeModuleFile)) {
    throw new RuntimeException('OwnTracks runtime module file is missing.');
}
require_once $runtimeModuleFile;
unset($runtimeModuleFile);
require_once __DIR__ . '/harness/TestOwnTracksPositionMapCandidate.php';

/** @param bool $condition */
function runtimeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function runtimePngChunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data
        . hash('crc32b', $type . $data, true);
}

function runtimeRgbaPng(bool $uniform): string
{
    $width = 256;
    $height = 256;
    $pixel = "\xf2\xef\xe9\xff";
    $raw = '';
    for ($row = 0; $row < $height; $row++) {
        $pixels = str_repeat($pixel, $width);
        if (!$uniform && $row === 128) {
            $pixels = substr_replace($pixels, "\x00\x00\x00\xff", 128 * 4, 4);
        }
        $raw .= "\x00" . $pixels;
    }

    return "\x89PNG\r\n\x1a\n"
        . runtimePngChunk(
            'IHDR',
            pack('NNC5', $width, $height, 8, 6, 0, 0, 0)
        )
        . runtimePngChunk('IDAT', zlib_encode($raw, ZLIB_ENCODING_DEFLATE, 9))
        . runtimePngChunk('IEND', '');
}

$placeholderDetector = new ReflectionMethod(
    OwnTracksPositionMap::class,
    'isUniformRasterPlaceholder'
);
runtimeCheck(
    $placeholderDetector->invoke(null, runtimeRgbaPng(true)) === true,
    'Uniform static raster placeholder was not detected.'
);
runtimeCheck(
    $placeholderDetector->invoke(null, runtimeRgbaPng(false)) === false,
    'Non-uniform static raster tile was rejected as a placeholder.'
);

/** @return list<array<string, mixed>> */
function runtimeSources(): array
{
    return [
        [
            'sourceKey' => 'synthetic-a',
            'label' => 'Synthetic A',
            'selectorValue' => 1,
            'sourceRootId' => 3101,
            'positionIdent' => 'position',
            'accuracyIdent' => 'acc',
            'activityIdent' => 'motionactivities',
        ],
        [
            'sourceKey' => 'synthetic-b',
            'label' => 'Synthetic B',
            'selectorValue' => 2,
            'sourceRootId' => 3102,
            'positionIdent' => 'position',
            'accuracyIdent' => 'acc',
            'activityIdent' => 'motionactivities',
        ],
        [
            'sourceKey' => 'synthetic-c',
            'label' => 'Synthetic C',
            'selectorValue' => 3,
            'sourceRootId' => 3103,
            'positionIdent' => 'position',
            'accuracyIdent' => 'acc',
            'activityIdent' => 'motionactivities',
        ],
    ];
}

$fixture = syntheticFixture();
$latestObservedAt = 1704075000;
$positionID = 4101;
$accuracyID = 4102;
$activityID = 4103;
$positionTwoID = 4201;
$accuracyTwoID = 4202;
$positionThreeID = 4301;
$accuracyThreeID = 4302;
$archiveID = 2101;
$anchorPositionID = 5102;
$northLocationID = 6101;
$southLocationID = 6102;
$GLOBALS['ownTracksRuntimeFake'] = [
    'instances' => [
        $archiveID,
        3101,
        3102,
        3103,
        5101,
        $northLocationID,
        $southLocationID,
    ],
    'objects' => [5101],
    'moduleInstances' => [
        '{43192F0B-135B-4CE7-A0A7-1475603F3060}' => [$archiveID],
    ],
    'instanceModuleIds' => [
        $northLocationID => '{3B6B9CB0-8D95-4358-874A-13FF1A8BECD1}',
        $southLocationID => '{3B6B9CB0-8D95-4358-874A-13FF1A8BECD1}',
    ],
    'locationDescriptors' => [
        $northLocationID => [
            'success' => true,
            'location' => [
                'key' => 'synthetic_north',
                'latitude' => 10.030,
                'longitude' => 20.030,
                'timezone' => 'Europe/Berlin',
                'elevation' => null,
            ],
        ],
        $southLocationID => [
            'success' => true,
            'location' => [
                'key' => 'synthetic_south',
                'latitude' => 9.0,
                'longitude' => 19.0,
                'timezone' => 'Europe/Berlin',
                'elevation' => null,
            ],
        ],
    ],
    'variablesByIdent' => [
        '3101:position' => $positionID,
        '3101:acc' => $accuracyID,
        '3101:motionactivities' => $activityID,
        '3102:position' => $positionTwoID,
        '3102:acc' => $accuracyTwoID,
        '3103:position' => $positionThreeID,
        '3103:acc' => $accuracyThreeID,
        '5101:position' => $anchorPositionID,
    ],
    'variableTypes' => [
        $positionID => 3,
        $accuracyID => 2,
        $activityID => 1,
        $positionTwoID => 3,
        $accuracyTwoID => 2,
        $positionThreeID => 3,
        $accuracyThreeID => 2,
        $anchorPositionID => 3,
    ],
    'variableUpdated' => [
        $anchorPositionID => $latestObservedAt,
        $positionID => $latestObservedAt,
        $accuracyID => $latestObservedAt,
        $positionTwoID => $latestObservedAt - 30,
        $accuracyTwoID => $latestObservedAt - 30,
        $positionThreeID => $latestObservedAt - 60,
        $accuracyThreeID => $latestObservedAt - 60,
    ],
    'values' => [
        $anchorPositionID => '{"lat":10.002,"lon":20.002}',
        $positionID => json_encode([
            'tst' => $latestObservedAt,
            'lat' => 10.01,
            'lon' => 20.01,
        ], JSON_THROW_ON_ERROR),
        $accuracyID => 8.0,
        $positionTwoID => json_encode([
            'tst' => $latestObservedAt - 30,
            'lat' => 10.02,
            'lon' => 20.02,
        ], JSON_THROW_ON_ERROR),
        $accuracyTwoID => 9.0,
        $positionThreeID => json_encode([
            'tst' => $latestObservedAt - 60,
            'lat' => 10.03,
            'lon' => 20.03,
        ], JSON_THROW_ON_ERROR),
        $accuracyThreeID => 10.0,
    ],
    'logging' => [
        $positionID => true,
        $accuracyID => true,
        $activityID => true,
    ],
    'records' => [
        $positionID => $fixture['positionRecordsNewestFirst'],
        $accuracyID => $fixture['accuracyRecordsNewestFirst'],
        $activityID => [
            ['TimeStamp' => 1704073500, 'Value' => 5],
            ['TimeStamp' => 1704067100, 'Value' => 1],
        ],
    ],
    'archiveCalls' => [],
];

$module = new TestOwnTracksPositionMapCandidate();
$module->testNow = $latestObservedAt + 60;
$module->Create();
runtimeCheck(
    $module->testProperty('MaximumGapSeconds') === 60 * 60,
    'Default line-gap threshold differs.'
);
runtimeCheck(
    $module->testHookCalls() === [
        ['action' => 'register', 'address' => 'owntracks-position-map'],
    ],
    'Strict hook was not registered exactly once during Create().'
);
$module->testSetProperty(
    'Sources',
    json_encode(runtimeSources(), JSON_THROW_ON_ERROR)
);
$module->testSetProperty('ExternalAnchorID', 5101);
$module->testSetProperty('SelectedTimeZone', 'Europe/Berlin');
$module->testSetProperty('HistoryDays', 730);
$module->testSetProperty(
    'EtaTargetLocations',
    json_encode(
        [
            ['locationInstanceId' => $northLocationID],
            ['locationInstanceId' => $southLocationID],
        ],
        JSON_THROW_ON_ERROR
    )
);
$module->ApplyChanges();

runtimeCheck($module->testApplyCalls() === 1, 'Parent ApplyChanges was not called.');
runtimeCheck($module->testStatus() === IS_ACTIVE, 'Valid runtime is not active.');
runtimeCheck(
    $module->testVisualizationType() === INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN,
    'Fullscreen HTML visualization type is not selected.'
);
runtimeCheck(
    $module->testReferences()
        === [3101, 3102, 3103, 5101, 5102, 6101, 6102],
    'Runtime references differ.'
);
$bootstrap = $module->testLastUpdate();
runtimeCheck(($bootstrap['action'] ?? null) === 'bootstrap', 'Bootstrap is missing.');
runtimeCheck(count($bootstrap['sources'] ?? []) === 3, 'Bootstrap source count differs.');
runtimeCheck(
    array_column($bootstrap['sources'], 'sourceKey')
        === [
            'synthetic-a',
            'synthetic-b',
            'synthetic-c',
        ]
        && ($bootstrap['selectedSourceKey'] ?? null) === 'synthetic-a',
    'Configured source order or first-source default was not preserved.'
);

$overviewRequest = [
    'requestGeneration' => 1,
    'clientSessionKey' => 'client-overview-0001',
    'sourceKey' => 'current-overview',
    'selectedDate' => '2024-01-01',
    'viewMode' => 'current-overview',
];
$archiveCallsBeforeOverview = count(
    $GLOBALS['ownTracksRuntimeFake']['archiveCalls']
);
$module->RequestAction(
    'SelectTrack',
    json_encode($overviewRequest, JSON_THROW_ON_ERROR)
);
$overviewResult = $module->testLastUpdate();
runtimeCheck(
    ($overviewResult['action'] ?? null) === 'trackResult'
        && ($overviewResult['viewMode'] ?? null) === 'current-overview'
        && ($overviewResult['result']['statistics']['renderedPoints'] ?? 0) === 3,
    'Current three-source overview differs.'
);
runtimeCheck(
    array_column(
        $overviewResult['result']['render']['points'] ?? [],
        'sourceLabel'
    ) === ['Synthetic A', 'Synthetic B', 'Synthetic C'],
    'Current overview source labels differ.'
);
runtimeCheck(
    count($GLOBALS['ownTracksRuntimeFake']['archiveCalls'])
        === $archiveCallsBeforeOverview
        && ($overviewResult['etaEntries'] ?? null) === [],
    'Unselected current overview read ETA archives.'
);
$overviewPoints = $overviewResult['result']['render']['points'] ?? [];
runtimeCheck(
    count(array_filter(
        $overviewPoints,
        static fn (array $point): bool =>
            ($point['observedDate'] ?? null) === '2024-01-01'
    )) === 3,
    'Current overview local observation dates differ.'
);
$selectedOverviewRequest = $overviewRequest;
$selectedOverviewRequest['requestGeneration'] = 2;
$selectedOverviewRequest['etaSourceKey'] = 'synthetic-a';
$module->RequestAction(
    'SelectTrack',
    json_encode($selectedOverviewRequest, JSON_THROW_ON_ERROR)
);
$overviewResult = $module->testLastUpdate();
$overviewArchiveCalls = array_slice(
    $GLOBALS['ownTracksRuntimeFake']['archiveCalls'],
    $archiveCallsBeforeOverview
);
runtimeCheck(
    $overviewArchiveCalls !== []
        && count(array_filter(
            $overviewArchiveCalls,
            static fn (array $call): bool =>
                (in_array(
                    $call['variableID'],
                    [$positionID, $activityID],
                    true
                ) && $call['limit'] > 1 && $call['startTime']
                    < $latestObservedAt + 60 - 30 * 60)
                || $call['endTime'] > $latestObservedAt + 60
                || $call['limit'] > 1001
        )) === 0,
    'Current overview ETA evidence exceeded its 30-minute or record bound.'
);
runtimeCheck(
    ($overviewResult['etaEntries'][0]['status'] ?? null) === 'available'
        && ($overviewResult['etaEntries'][0]['sourceLabel'] ?? null)
            === 'Synthetic A',
    'Selected current position did not produce its source-labelled ETA.'
);
runtimeCheck(
    ($bootstrap['basemap']['mode'] ?? null) === 'none',
    'Repository runtime must bootstrap without tiles.'
);
runtimeCheck(
    ($bootstrap['tileAccess']['mode'] ?? null) === 'none',
    'Repository runtime must bootstrap with disabled tile access.'
);
runtimeCheck(
    !array_key_exists('tileAuthority', $bootstrap),
    'Private tile-authority configuration escaped into the browser bootstrap.'
);
runtimeCheck(
    !array_key_exists('tileFallback', $bootstrap),
    'Private tile-fallback configuration escaped into the browser bootstrap.'
);
runtimeCheck(
    $module->testHookCalls() === [
        ['action' => 'register', 'address' => 'owntracks-position-map'],
    ],
    'ApplyChanges changed the Create-owned hook lifecycle.'
);
runtimeCheck(
    $module->testAttribute('TileCapabilitySecret') === '',
    'Default runtime generated a tile secret.'
);

ob_start();
$module->testProcessHookData();
$disabledHookBody = ob_get_clean();
runtimeCheck($disabledHookBody === 'Not found', 'Disabled hook body differs.');
runtimeCheck(http_response_code() === 404, 'Disabled hook status differs.');
runtimeCheck(
    $module->providerFetchCalls === 0,
    'Default-disabled runtime reached provider DNS/transport boundary.'
);

$module->RequestAction(
    'RequestTileCapability',
    json_encode(
        [
            'requestGeneration' => 1,
            'clientSessionKey' => 'client-synthetic-0001',
        ],
        JSON_THROW_ON_ERROR
    )
);
$capability = $module->testLastUpdate();
runtimeCheck(
    ($capability['action'] ?? null) === 'tileCapabilityError',
    'Disabled tile capability request was not rejected.'
);
runtimeCheck(
    $module->testAttribute('TileCapabilitySecret') === '',
    'Rejected tile capability request generated a secret.'
);

$tile = $module->GetVisualizationTile();
foreach (
    [
        'data-owntracks-openlayers-map',
        'connect-src &apos;none&apos;',
        'handleOwnTracksOpenLayersMessage',
        'OpenLayers · no map tiles',
        'requestAction',
    ] as $required
) {
    runtimeCheck(str_contains($tile, $required), 'Tile marker is missing: ' . $required);
}
runtimeCheck(
    !str_contains($tile, 'tile.openstreetmap'),
    'Runtime tile contains an external tile authority.'
);
$bootstrapCall = 'window.handleOwnTracksOpenLayersMessage(';
$bootstrapStart = strrpos($tile, $bootstrapCall);
runtimeCheck($bootstrapStart !== false, 'Runtime bootstrap call is missing.');
$bootstrapStart += strlen($bootstrapCall);
$bootstrapEnd = strpos($tile, ');</script></body></html>', $bootstrapStart);
runtimeCheck($bootstrapEnd !== false, 'Runtime bootstrap boundary is missing.');
$tileBootstrap = json_decode(
    substr($tile, $bootstrapStart, $bootstrapEnd - $bootstrapStart),
    true,
    64,
    JSON_THROW_ON_ERROR
);
runtimeCheck(
    ($tileBootstrap['action'] ?? null) === 'bootstrap',
    'Runtime tile bootstrap action differs.'
);
runtimeCheck(
    ($tileBootstrap['basemap']['mode'] ?? null) === 'none',
    'Runtime tile bootstrap basemap is not disabled.'
);
runtimeCheck(
    ($tileBootstrap['tileAccess']['mode'] ?? null) === 'none'
        && ($tileBootstrap['tileAccess']['enabled'] ?? null) === false,
    'Runtime tile bootstrap access is not disabled.'
);
runtimeCheck(
    !array_key_exists('tileAuthority', $tileBootstrap),
    'Private tile authority escaped into the runtime tile bootstrap.'
);
runtimeCheck(
    !array_key_exists('tileFallback', $tileBootstrap),
    'Private tile fallback escaped into the runtime tile bootstrap.'
);

$request = [
    'requestGeneration' => 1,
    'clientSessionKey' => 'client-synthetic-0001',
    'sourceKey' => 'synthetic-a',
    'selectedDate' => '2024-01-01',
];
$archiveCallsBeforePath = count(
    $GLOBALS['ownTracksRuntimeFake']['archiveCalls']
);
$module->RequestAction(
    'SelectTrack',
    json_encode($request, JSON_THROW_ON_ERROR)
);
$track = $module->testLastUpdate();
runtimeCheck(($track['action'] ?? null) === 'trackResult', 'Track result is missing.');
runtimeCheck(($track['requestGeneration'] ?? null) === 1, 'Generation differs.');
runtimeCheck(
    ($track['result']['statistics']['validObservations'] ?? 0) > 0,
    'Track result contains no observations.'
);
runtimeCheck(
    ($track['eta']['reason'] ?? null) === 'path-mode'
        && ($track['etaEntries'] ?? null) === []
        && array_key_exists('targetResolution', $track)
        && $track['targetResolution'] === null,
    'Path mode calculated or exposed ETA data.'
);
$pathArchiveCalls = array_slice(
    $GLOBALS['ownTracksRuntimeFake']['archiveCalls'],
    $archiveCallsBeforePath
);
runtimeCheck(
    count(array_filter(
        $pathArchiveCalls,
        static fn (array $call): bool => $call['variableID'] === $activityID
    )) === 0,
    'Path mode read activity evidence for ETA.'
);
runtimeCheck(
    !array_key_exists('anchor', $track),
    'Unselected external position escaped into the OwnTracks result.'
);

$externalRequest = $request;
$externalRequest['requestGeneration'] = 2;
$externalRequest['sourceKey'] = 'saef-external-path';
$archiveCallsBeforeExternal = count(
    $GLOBALS['ownTracksRuntimeFake']['archiveCalls']
);
$module->RequestAction(
    'SelectTrack',
    json_encode($externalRequest, JSON_THROW_ON_ERROR)
);
$externalTrack = $module->testLastUpdate();
runtimeCheck(
    ($externalTrack['action'] ?? null) === 'trackResult'
        && ($externalTrack['result']['statistics']['validObservations'] ?? 0) === 1
        && ($externalTrack['result']['statistics']['renderedPoints'] ?? 0) === 1,
    'Explicit external path selection did not return one timestamped point: '
        . json_encode([$externalTrack, $module->testDebug()])
);
runtimeCheck(
    count($GLOBALS['ownTracksRuntimeFake']['archiveCalls'])
        === $archiveCallsBeforeExternal,
    'External path selection read Archive Control.'
);
runtimeCheck(
    ($externalTrack['eta']['status'] ?? null) === 'unavailable',
    'External path selection unexpectedly produced an ETA.'
);

$callsBeforeStale = count($GLOBALS['ownTracksRuntimeFake']['archiveCalls']);
$module->RequestAction(
    'SelectTrack',
    json_encode($request, JSON_THROW_ON_ERROR)
);
$stale = $module->testLastUpdate();
runtimeCheck(($stale['action'] ?? null) === 'trackError', 'Stale generation was accepted.');
runtimeCheck(
    count($GLOBALS['ownTracksRuntimeFake']['archiveCalls']) === $callsBeforeStale,
    'Stale generation reached Archive Control.'
);

$invalidClient = $request;
$invalidClient['requestGeneration'] = 2;
$invalidClient['clientSessionKey'] = 'invalid';
$module->RequestAction(
    'SelectTrack',
    json_encode($invalidClient, JSON_THROW_ON_ERROR)
);
runtimeCheck(
    ($module->testLastUpdate()['action'] ?? null) === 'trackError',
    'Invalid client key did not fail closed.'
);

$module->ApplyChanges();
runtimeCheck($module->testApplyCalls() === 2, 'Repeated ApplyChanges was not called.');
runtimeCheck(
    $module->testHookCalls() === [
        ['action' => 'register', 'address' => 'owntracks-position-map'],
    ],
    'Repeated ApplyChanges attempted to register or unregister the Strict hook.'
);
runtimeCheck(
    $module->testReferences()
        === [3101, 3102, 3103, 5101, 5102, 6101, 6102],
    'Repeated ApplyChanges duplicated references.'
);

$viewportModule = new TestOwnTracksPositionMapCandidate();
$viewportModule->Create();
$viewportModule->testNow = 1_725_184_000;
$viewportModule->testSetProperty(
    'ProviderConfiguration',
    json_encode([
        'tileFallback' => [
            'mode' => 'osm-standard-raster-on-miss',
            'origin' => 'https://tile.openstreetmap.org',
            'pathTemplate' => '/{z}/{x}/{y}.png',
            'userAgent' => 'SAEFOwnTracksPositionMap/0.1 '
                . '(+https://github.com/doctee)',
            'refererOrigin' => 'https://connect.symcon.de/',
            'maximumConcurrentRequests' => 2,
            'maximumRequestsPerMinute' => 30,
            'minimumZoom' => 0,
            'maximumZoom' => 14,
            'viewportRingTiles' => 1,
            'maximumTilesPerSelection' => 128,
            'maximumRequestsPerSelection' => 8,
            'maximumBytesPerSelection' => 4 * 1024 * 1024,
            'timeoutMilliseconds' => 2000,
            'negativeTtlSeconds' => 60,
        ],
    ], JSON_THROW_ON_ERROR)
);
$viewportModule->testSetAttribute(
    'ActiveRequests',
    json_encode([
        'client-synthetic-0001' => [
            'generation' => 7,
            'seenAt' => $viewportModule->testNow,
            'tileSelection' => [
                'bounds' => [
                    'west' => 7.0,
                    'south' => 47.0,
                    'east' => 12.0,
                    'north' => 50.0,
                    'crossesAntimeridian' => false,
                ],
                'selectionKey' => hash('sha256', 'synthetic-selection'),
            ],
        ],
    ], JSON_THROW_ON_ERROR)
);
$viewportModule->RequestAction('RequestTileViewport', json_encode([
    'requestGeneration' => 7,
    'viewportGeneration' => 1,
    'clientSessionKey' => 'client-synthetic-0001',
    'zoom' => 9,
    'bounds' => [
        'west' => 8.0,
        'south' => 47.5,
        'east' => 10.0,
        'north' => 49.0,
        'crossesAntimeridian' => false,
    ],
], JSON_THROW_ON_ERROR));
runtimeCheck(
    ($viewportModule->testLastUpdate()['action'] ?? null) === 'tileViewport',
    'Bounded tile viewport was not accepted: '
        . json_encode($viewportModule->testDebug())
);
$viewportRequests = json_decode(
    $viewportModule->testAttribute('ActiveRequests'),
    true,
    16,
    JSON_THROW_ON_ERROR
);
runtimeCheck(
    ($viewportRequests['client-synthetic-0001']['tileSelection']['viewport']['tileCount'] ?? 129)
        <= 128,
    'Tile viewport exceeded its bounded allowlist.'
);
$firstViewportSelectionKey =
    $viewportRequests['client-synthetic-0001']['tileSelection']['viewport']['selectionKey']
        ?? null;
runtimeCheck(
    is_string($firstViewportSelectionKey)
        && preg_match('/^[a-f0-9]{64}$/D', $firstViewportSelectionKey) === 1,
    'Tile viewport did not receive a private budget key.'
);
$viewportModule->RequestAction('RequestTileViewport', json_encode([
    'requestGeneration' => 7,
    'viewportGeneration' => 2,
    'clientSessionKey' => 'client-synthetic-0001',
    'zoom' => 9,
    'bounds' => [
        'west' => 8.1,
        'south' => 47.6,
        'east' => 10.1,
        'north' => 49.1,
        'crossesAntimeridian' => false,
    ],
], JSON_THROW_ON_ERROR));
$secondViewportRequests = json_decode(
    $viewportModule->testAttribute('ActiveRequests'),
    true,
    16,
    JSON_THROW_ON_ERROR
);
$secondViewportSelectionKey =
    $secondViewportRequests['client-synthetic-0001']['tileSelection']['viewport']['selectionKey']
        ?? null;
runtimeCheck(
    is_string($secondViewportSelectionKey)
        && $secondViewportSelectionKey !== $firstViewportSelectionKey,
    'A changed viewport reused its miss-budget key.'
);
$retainedViewports =
    $secondViewportRequests['client-synthetic-0001']['tileSelection']['viewports']
        ?? null;
runtimeCheck(
    is_array($retainedViewports)
        && array_keys($retainedViewports) === [2, 1],
    'Current and immediately preceding viewport were not retained in order.'
);
$selectionResolver = new ReflectionMethod(
    OwnTracksPositionMap::class,
    'tileSelectionForClient'
);
$tileBoundaryValidator = new ReflectionMethod(
    OwnTracksPositionMap::class,
    'validateTileBoundary'
);
$tileBoundaryValidator->invoke(
    $viewportModule,
    [
        'basemap' => [
            'enabled' => true,
            'urlTemplate' => '/hook/owntracks-position-map/{z}/{x}/{y}.png',
            'maximumZoom' => 18,
        ],
    ],
    ['enabled' => true],
    ['enabled' => true, 'maximumZoom' => 14],
    ['enabled' => true, 'maximumZoom' => 18]
);
$mismatchedDynamicBoundaryRejected = false;
try {
    $tileBoundaryValidator->invoke(
        $viewportModule,
        [
            'basemap' => [
                'enabled' => true,
                'urlTemplate' => '/hook/owntracks-position-map/{z}/{x}/{y}.png',
                'maximumZoom' => 18,
            ],
        ],
        ['enabled' => true],
        ['enabled' => true, 'maximumZoom' => 14],
        ['enabled' => true, 'maximumZoom' => 17]
    );
} catch (InvalidArgumentException) {
    $mismatchedDynamicBoundaryRejected = true;
}
runtimeCheck(
    $mismatchedDynamicBoundaryRejected,
    'Dynamic fallback drift from the browser zoom boundary was accepted.'
);
runtimeCheck(
    is_array($selectionResolver->invoke(
        $viewportModule,
        'client-synthetic-0001',
        1
    ))
        && $selectionResolver->invoke(
            $viewportModule,
            'client-synthetic-0001',
            99
        ) === null,
    'Viewport resolution was not bound to the requested generation.'
);
$viewportModule->testNow += 61;
$viewportModule->RequestAction('RequestTileViewport', json_encode([
    'requestGeneration' => 7,
    'viewportGeneration' => 3,
    'clientSessionKey' => 'client-synthetic-0001',
    'zoom' => 9,
    'bounds' => [
        'west' => 8.2,
        'south' => 47.7,
        'east' => 10.2,
        'north' => 49.2,
        'crossesAntimeridian' => false,
    ],
], JSON_THROW_ON_ERROR));
$thirdViewportRequests = json_decode(
    $viewportModule->testAttribute('ActiveRequests'),
    true,
    16,
    JSON_THROW_ON_ERROR
);
runtimeCheck(
    array_keys(
        $thirdViewportRequests['client-synthetic-0001']['tileSelection']
            ['viewports'] ?? []
    ) === [3],
    'Expired viewport generations survived the bounded grace interval.'
);
runtimeCheck(
    $selectionResolver->invoke(
        $viewportModule,
        'client-synthetic-0001',
        1
    ) === null,
    'Expired viewport generation remained authorized.'
);
$viewportModule->RequestAction('RequestTileViewport', json_encode([
    'requestGeneration' => 7,
    'viewportGeneration' => 4,
    'clientSessionKey' => 'client-synthetic-0001',
    'zoom' => 9,
    'bounds' => [
        'west' => -80.0,
        'south' => 30.0,
        'east' => -79.0,
        'north' => 31.0,
        'crossesAntimeridian' => false,
    ],
], JSON_THROW_ON_ERROR));
runtimeCheck(
    ($viewportModule->testLastUpdate()['action'] ?? null)
        === 'tileViewportError',
    'Viewport outside the selected data envelope was accepted.'
);

$module->testSetProperty(
    'ProviderConfiguration',
    json_encode(
        [
            'basemap' => [
                'mode' => 'same-origin-xyz',
                'authorityKey' => 'not-authorized-in-this-gate',
                'urlTemplate' => '/map-tiles/{z}/{x}/{y}.png',
                'maximumZoom' => 19,
                'attributionText' => '© OpenStreetMap contributors',
                'attributionUrl' => 'https://www.openstreetmap.org/copyright',
            ],
            'routing' => [
                'mode' => 'none',
                'allowGeodesicFallback' => true,
            ],
        ],
        JSON_THROW_ON_ERROR
    )
);
$module->ApplyChanges();
runtimeCheck(
    $module->testStatus() === 200,
    'Provider activation did not fail closed.'
);
runtimeCheck(
    ($module->testLastUpdate()['action'] ?? null) === 'configurationError',
    'Invalid provider did not produce a generic configuration error.'
);
runtimeCheck($module->testReferences() === [], 'Rejected configuration retained references.');
runtimeCheck(
    $module->testHookCalls() === [
        ['action' => 'register', 'address' => 'owntracks-position-map'],
    ],
    'Rejected configuration changed the fail-closed hook lifecycle.'
);

$module->testSetProperty(
    'ProviderConfiguration',
    json_encode(
        [
            'basemap' => ['mode' => 'none'],
            'routing' => [
                'mode' => 'none',
                'allowGeodesicFallback' => true,
            ],
            'tileAccess' => ['mode' => 'none'],
            'tileAuthority' => ['mode' => 'none'],
            'tileFallback' => [
                'mode' => 'osm-standard-raster-on-miss',
                'origin' => 'https://tile.openstreetmap.org',
                'pathTemplate' => '/{z}/{x}/{y}.png',
                'userAgent' => 'SAEFOwnTracksPositionMap/0.1 '
                    . '(+https://github.com/doctee)',
                'refererOrigin' => 'https://connect.symcon.de/',
                'maximumConcurrentRequests' => 2,
                'maximumRequestsPerMinute' => 30,
                'minimumZoom' => 0,
                'maximumZoom' => 10,
                'viewportRingTiles' => 1,
                'maximumTilesPerSelection' => 128,
                'maximumRequestsPerSelection' => 8,
                'maximumBytesPerSelection' => 1024 * 1024,
                'timeoutMilliseconds' => 1500,
                'negativeTtlSeconds' => 60,
            ],
        ],
        JSON_THROW_ON_ERROR
    )
);
$module->ApplyChanges();
runtimeCheck(
    $module->testStatus() === 200
        && ($module->testLastUpdate()['action'] ?? null)
            === 'configurationError',
    'Provider fallback without static authority did not fail closed.'
);
runtimeCheck(
    $module->providerFetchCalls === 0,
    'Rejected provider fallback reached DNS/transport boundary.'
);

foreach ($module->testDebug() as $debug) {
    runtimeCheck(
        !str_contains($debug['data'], 'Synthetic A'),
        'Debug output contains a private-capable source label.'
    );
}

fwrite(STDOUT, "OwnTracks repository runtime module tests passed.\n");
