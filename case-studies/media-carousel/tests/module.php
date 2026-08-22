<?php

declare(strict_types=1);

define('IS_ACTIVE', 102);
define('IS_INACTIVE', 104);
define('MEDIATYPE_IMAGE', 1);
define('MM_DELETE', 10902);
define('MM_CHANGEFILE', 10903);
define('MM_AVAILABLE', 10904);
define('MM_UPDATE', 10905);
define('OM_CHANGENAME', 10404);

$pngFixture = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

/** @var array<int, array{MediaType: int, MediaFile: string, name: string, content: string}> $mediaFixtures */
$mediaFixtures = [
    101 => [
        'MediaType' => MEDIATYPE_IMAGE,
        'MediaFile' => 'camera-one.png',
        'name' => 'Camera one',
        'content' => $pngFixture,
    ],
    102 => [
        'MediaType' => MEDIATYPE_IMAGE,
        'MediaFile' => 'camera-two.png',
        'name' => 'Camera two',
        'content' => base64_encode('png-two'),
    ],
    103 => [
        'MediaType' => MEDIATYPE_IMAGE,
        'MediaFile' => 'camera-three.webp',
        'name' => 'Camera three',
        'content' => base64_encode('webp-three'),
    ],
    104 => [
        'MediaType' => MEDIATYPE_IMAGE,
        'MediaFile' => 'camera-four.png',
        'name' => 'Camera four',
        'content' => $pngFixture,
    ],
    201 => [
        'MediaType' => 4,
        'MediaFile' => 'not-an-image.txt',
        'name' => 'Wrong media type',
        'content' => base64_encode('text'),
    ],
    202 => [
        'MediaType' => MEDIATYPE_IMAGE,
        'MediaFile' => 'unsupported.svg',
        'name' => 'Unsupported image',
        'content' => base64_encode('<svg/>'),
    ],
];

/** @var array<int, list<int>> $categoryFixtures */
$categoryFixtures = [
    301 => [101, 102, 103, 201],
];

/** @var array<int, int> $objectPositions */
$objectPositions = [
    101 => 30,
    102 => 10,
    103 => 20,
    104 => 40,
    201 => 50,
];

/** @var list<int> $mediaContentReads */
$mediaContentReads = [];

class IPSModuleStrict
{
    public int $InstanceID = 42;

    /** @var array<string, mixed> */
    private array $properties = [];

    /** @var array<string, string> */
    private array $attributes = [];

    /** @var array<int, true> */
    private array $references = [];

    /** @var array<string, array{senderID: int, message: int}> */
    private array $messages = [];

    /** @var list<string> */
    private array $visualizationUpdates = [];

    /** @var list<array{message: string, data: mixed}> */
    private array $debugMessages = [];

    private int $status = 0;
    private int $visualizationType = 0;
    private int $parentCreateCalls = 0;
    private int $parentApplyCalls = 0;

    public function Create(): void
    {
        $this->parentCreateCalls++;
    }

    public function ApplyChanges(): void
    {
        $this->parentApplyCalls++;
    }

    protected function RegisterPropertyString(string $ident, string $default): void
    {
        $this->properties[$ident] ??= $default;
    }

    protected function RegisterPropertyBoolean(string $ident, bool $default): void
    {
        $this->properties[$ident] ??= $default;
    }

    protected function RegisterPropertyInteger(string $ident, int $default): void
    {
        $this->properties[$ident] ??= $default;
    }

    protected function ReadPropertyString(string $ident): string
    {
        return (string) $this->property($ident);
    }

    protected function ReadPropertyBoolean(string $ident): bool
    {
        return (bool) $this->property($ident);
    }

    protected function ReadPropertyInteger(string $ident): int
    {
        return (int) $this->property($ident);
    }

    protected function RegisterAttributeString(string $ident, string $default): void
    {
        $this->attributes[$ident] ??= $default;
    }

    protected function RegisterAttributeInteger(string $ident, int $default): void
    {
        $this->attributes[$ident] ??= (string) $default;
    }

    protected function ReadAttributeString(string $ident): string
    {
        return $this->attributes[$ident];
    }

    protected function WriteAttributeString(string $ident, string $value): void
    {
        $this->attributes[$ident] = $value;
    }

    protected function ReadAttributeInteger(string $ident): int
    {
        return (int) $this->attributes[$ident];
    }

    protected function WriteAttributeInteger(string $ident, int $value): void
    {
        $this->attributes[$ident] = (string) $value;
    }

    protected function RegisterReference(int $id): void
    {
        $this->references[$id] = true;
    }

    protected function UnregisterReference(int $id): void
    {
        unset($this->references[$id]);
    }

    protected function RegisterMessage(int $senderID, int $message): void
    {
        $this->messages[$senderID . ':' . $message] = [
            'senderID' => $senderID,
            'message' => $message,
        ];
    }

    protected function UnregisterMessage(int $senderID, int $message): void
    {
        unset($this->messages[$senderID . ':' . $message]);
    }

    protected function SetVisualizationType(int $type): void
    {
        $this->visualizationType = $type;
    }

    protected function UpdateVisualizationValue(string $value): void
    {
        $this->visualizationUpdates[] = $value;
    }

    protected function SetStatus(int $status): void
    {
        $this->status = $status;
    }

    protected function SendDebug(string $message, mixed $data, int $format): void
    {
        $this->debugMessages[] = ['message' => $message, 'data' => $data];
    }

    protected function Translate(string $text): string
    {
        return $text;
    }

    public function testSetProperty(string $ident, mixed $value): void
    {
        $this->property($ident);
        $this->properties[$ident] = $value;
    }

    public function testStatus(): int
    {
        return $this->status;
    }

    public function testVisualizationType(): int
    {
        return $this->visualizationType;
    }

    /** @return list<int> */
    public function testReferences(): array
    {
        $references = array_keys($this->references);
        sort($references, SORT_NUMERIC);

        return $references;
    }

    public function testMessageCount(): int
    {
        return count($this->messages);
    }

    /** @return array<string, mixed> */
    public function testLastVisualizationUpdate(): array
    {
        $last = $this->visualizationUpdates[array_key_last($this->visualizationUpdates)] ?? '{}';

        return json_decode($last, true, 32, JSON_THROW_ON_ERROR);
    }

    public function testParentCreateCalls(): int
    {
        return $this->parentCreateCalls;
    }

    public function testParentApplyCalls(): int
    {
        return $this->parentApplyCalls;
    }

    private function property(string $ident): mixed
    {
        if (!array_key_exists($ident, $this->properties)) {
            throw new RuntimeException('Unknown test property: ' . $ident);
        }

        return $this->properties[$ident];
    }
}

function IPS_MediaExists(int $id): bool
{
    global $mediaFixtures;

    return isset($mediaFixtures[$id]);
}

function IPS_CategoryExists(int $id): bool
{
    global $categoryFixtures;

    return isset($categoryFixtures[$id]);
}

/** @return list<int> */
function IPS_GetChildrenIDs(int $id): array
{
    global $categoryFixtures;

    return $categoryFixtures[$id] ?? [];
}

/** @return array{ObjectPosition: int} */
function IPS_GetObject(int $id): array
{
    global $objectPositions;

    return ['ObjectPosition' => $objectPositions[$id] ?? 0];
}

/** @return array{MediaType: int, MediaFile: string} */
function IPS_GetMedia(int $id): array
{
    global $mediaFixtures;

    if (!isset($mediaFixtures[$id])) {
        throw new RuntimeException('Unknown test media.');
    }

    return [
        'MediaType' => $mediaFixtures[$id]['MediaType'],
        'MediaFile' => $mediaFixtures[$id]['MediaFile'],
    ];
}

function IPS_GetMediaContent(int $id): string
{
    global $mediaFixtures, $mediaContentReads;

    $mediaContentReads[] = $id;

    return $mediaFixtures[$id]['content'] ?? '';
}

function IPS_GetName(int $id): string
{
    global $mediaFixtures;

    return $mediaFixtures[$id]['name'] ?? '';
}

require_once __DIR__ . '/../distribution/MediaCarousel/module.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function extractInitialMessage(string $tile): array
{
    $marker = '<script>handleMessage(';
    $start = strrpos($tile, $marker);
    if ($start === false) {
        throw new RuntimeException('Initial tile message marker is missing.');
    }
    $start += strlen($marker);
    $end = strpos($tile, ');</script>', $start);
    if ($end === false) {
        throw new RuntimeException('Initial tile message terminator is missing.');
    }

    $messageJSON = json_decode(
        substr($tile, $start, $end - $start),
        true,
        16,
        JSON_THROW_ON_ERROR
    );
    if (!is_string($messageJSON)) {
        throw new RuntimeException('Initial tile message is not JSON text.');
    }

    $message = json_decode($messageJSON, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($message)) {
        throw new RuntimeException('Initial tile message is not an object.');
    }

    return $message;
}

/** @param list<array{MediaID: int, Title: string, Enabled: bool}> $items */
function encodeItems(array $items): string
{
    return json_encode($items, JSON_THROW_ON_ERROR);
}

$fallbackModule = new MediaCarousel();
$fallbackModule->Create();
check(
    $fallbackModule->testVisualizationType() === 1,
    'Pre-9.1 HTML visualization fallback is not active.'
);

define('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN', 2);
$module = new MediaCarousel();
$module->Create();
check($module->testParentCreateCalls() === 1, 'Parent Create was not called exactly once.');
check($module->testVisualizationType() === 2, 'HTML fullscreen visualization type is not active.');

$validItems = [
    ['MediaID' => 101, 'Title' => 'Entrance', 'Enabled' => true],
    ['MediaID' => 102, 'Title' => '', 'Enabled' => true],
    ['MediaID' => 103, 'Title' => 'Garden', 'Enabled' => true],
];
$module->testSetProperty('MediaItems', encodeItems($validItems));
$module->ApplyChanges();
check($module->testParentApplyCalls() === 1, 'Parent ApplyChanges was not called.');
check($module->testStatus() === IS_ACTIVE, 'Valid configuration is not active.');
check($module->testReferences() === [101, 102, 103], 'Media references differ.');
check($module->testMessageCount() === 15, 'Media message registrations differ.');

$applyUpdate = $module->testLastVisualizationUpdate();
check(($applyUpdate['action'] ?? null) === 'bootstrap', 'ApplyChanges did not publish bootstrap.');
check(count($applyUpdate['items'] ?? []) === 3, 'Bootstrap item count differs.');
check(
    ($applyUpdate['items'][1]['title'] ?? null) === 'Camera two',
    'Empty title did not fall back to the media name.'
);
check(!isset($applyUpdate['initialMedia']), 'ApplyChanges unexpectedly transported image content.');
check($mediaContentReads === [], 'ApplyChanges unexpectedly read media content.');

$tile = $module->GetVisualizationTile();
$initialMessage = extractInitialMessage($tile);
check(($initialMessage['action'] ?? null) === 'bootstrap', 'Initial tile bootstrap is missing.');
$configurationRevision = (string) ($initialMessage['configurationRevision'] ?? '');
check(strlen($configurationRevision) === 64, 'Initial configuration revision is invalid.');
check(($initialMessage['initialMedia']['preview'] ?? null) === true, 'Initial preview is missing.');
check(
    str_starts_with((string) ($initialMessage['initialMedia']['source'] ?? ''), 'data:image/jpeg;base64,'),
    'Initial preview MIME type differs.'
);
check($mediaContentReads === [101], 'Initial tile did not read exactly the current media content.');
check(str_contains($tile, 'pointerdown'), 'Swipe handling is missing.');
check(str_contains($tile, 'ResizeObserver'), 'Resize handling is missing.');
check(str_contains($tile, 'MAX_PENDING_REQUESTS = 2'), 'Request concurrency bound is missing.');
check(str_contains($tile, "addEventListener('pageshow'"), 'Page lifecycle rendering is missing.');
check(str_contains($tile, 'renderForLifecycleChange'), 'Resize lifecycle rendering is missing.');
check(str_contains($tile, 'probe.onload'), 'DOM load gate is missing.');
check(!str_contains($tile, '.decode()'), 'Explicit decode remains in the frontend.');
check(!str_contains($tile, 'Promise.all'), 'Parallel image preparation remains in the frontend.');
check(!str_contains($tile, 'SAEF_MEDIA_CAROUSEL_SCRIPT'), 'Frontend script placeholder remains.');

$recreatedTile = $module->GetVisualizationTile();
$recreatedMessage = extractInitialMessage($recreatedTile);
check(
    ($recreatedMessage['initialMedia']['preview'] ?? null) === true,
    'Recreated tile has no self-contained initial preview.'
);
check(
    $mediaContentReads === [101, 101],
    'Recreated tile did not independently read the current media content.'
);

$module->RequestAction(
    'LoadMedia',
    json_encode(
        [
            'index' => 1,
            'requestID' => 'test_1',
            'configurationRevision' => $configurationRevision,
        ],
        JSON_THROW_ON_ERROR
    )
);
$mediaUpdate = $module->testLastVisualizationUpdate();
check(
    $mediaContentReads === [101, 101, 102],
    'LoadMedia did not read exactly the requested media content.'
);
check(($mediaUpdate['action'] ?? null) === 'media', 'LoadMedia did not publish media.');
check(($mediaUpdate['index'] ?? null) === 1, 'Loaded media index differs.');
check(
    str_starts_with((string) ($mediaUpdate['source'] ?? ''), 'data:image/png;base64,'),
    'Loaded media MIME type differs.'
);
check(($mediaUpdate['preview'] ?? null) === false, 'Loaded original is marked as preview.');

$module->RequestAction(
    'LoadMedia',
    json_encode(
        [
            'index' => 0,
            'requestID' => 'test_rendered',
            'configurationRevision' => $configurationRevision,
        ],
        JSON_THROW_ON_ERROR
    )
);
$renderedUpdate = $module->testLastVisualizationUpdate();
check(
    $mediaContentReads === [101, 101, 102, 101],
    'Rendered display request did not read exactly the requested media content.'
);
check(
    str_starts_with((string) ($renderedUpdate['source'] ?? ''), 'data:image/jpeg;base64,'),
    'Rendered display request did not transport a bounded JPEG.'
);
check(
    ($renderedUpdate['preview'] ?? null) === false,
    'Rendered display image is marked as preview.'
);

$module->RequestAction(
    'LoadMedia',
    json_encode(
        [
            'index' => 99,
            'requestID' => 'test_invalid',
            'configurationRevision' => $configurationRevision,
        ],
        JSON_THROW_ON_ERROR
    )
);
$mediaError = $module->testLastVisualizationUpdate();
check(($mediaError['action'] ?? null) === 'mediaError', 'Invalid media index did not fail closed.');
check(
    ($mediaError['requestID'] ?? null) === 'test_invalid',
    'Media error did not preserve request correlation.'
);

$module->MessageSink(time(), 102, MM_UPDATE, []);
$invalidation = $module->testLastVisualizationUpdate();
check(($invalidation['action'] ?? null) === 'invalidate', 'Media update did not invalidate cache.');
check(($invalidation['index'] ?? null) === 1, 'Invalidated media index differs.');

$module->ApplyChanges();
check($module->testReferences() === [101, 102, 103], 'Repeated ApplyChanges changed references.');
check($module->testMessageCount() === 15, 'Repeated ApplyChanges duplicated messages.');

$module->testSetProperty('MediaItems', encodeItems([
    ['MediaID' => 101, 'Title' => '', 'Enabled' => true],
    ['MediaID' => 102, 'Title' => '', 'Enabled' => false],
]));
$module->ApplyChanges();
check($module->testStatus() === IS_ACTIVE, 'Enabled subset is not active.');
check($module->testReferences() === [101], 'Disabled media remains referenced.');
check($module->testMessageCount() === 5, 'Disabled media remains registered.');

$module->testSetProperty('MediaItems', encodeItems([
    ['MediaID' => 101, 'Title' => '', 'Enabled' => true],
    ['MediaID' => 101, 'Title' => '', 'Enabled' => true],
]));
$module->ApplyChanges();
check($module->testStatus() === 200, 'Duplicate media did not fail configuration.');
check($module->testReferences() === [], 'Invalid configuration retained references.');
check($module->testMessageCount() === 0, 'Invalid configuration retained messages.');

$module->testSetProperty('MediaItems', encodeItems([
    ['MediaID' => 201, 'Title' => '', 'Enabled' => true],
]));
$module->ApplyChanges();
check($module->testStatus() === 200, 'Non-image media did not fail configuration.');

$module->testSetProperty('MediaItems', encodeItems([
    ['MediaID' => 202, 'Title' => '', 'Enabled' => true],
]));
$module->ApplyChanges();
check($module->testStatus() === 200, 'Unsupported image extension did not fail configuration.');

$module->testSetProperty('MediaItems', '[]');
$module->ApplyChanges();
check($module->testStatus() === IS_INACTIVE, 'Empty configuration is not inactive.');

$module->testSetProperty('MediaItems', '{broken');
$module->ApplyChanges();
check($module->testStatus() === 200, 'Malformed JSON did not fail configuration.');

$module->testSetProperty('MediaItems', encodeItems([
    ['MediaID' => 999, 'Title' => 'Expired', 'Enabled' => true],
    ['MediaID' => 101, 'Title' => 'Available', 'Enabled' => true],
]));
$module->ApplyChanges();
check($module->testStatus() === IS_ACTIVE, 'One expired explicit media disabled the sequence.');
check($module->testReferences() === [101], 'Expired explicit media remains referenced.');

$module->testSetProperty('MediaItems', encodeItems([
    ['MediaID' => 999, 'Title' => 'Expired', 'Enabled' => true],
]));
$module->ApplyChanges();
check($module->testStatus() === 200, 'All-expired explicit media did not fail visibly.');

$categoryModule = new MediaCarousel();
$categoryModule->Create();
$categoryModule->testSetProperty('SourceMode', 'category');
$categoryModule->testSetProperty('SourceCategoryID', 301);
$categoryModule->testSetProperty('CategoryItemLimit', 2);
$categoryModule->ApplyChanges();
check($categoryModule->testStatus() === IS_ACTIVE, 'Valid category source is not active.');
check(
    $categoryModule->testReferences() === [101, 103, 301],
    'Category source references do not contain the category and selected media.'
);
check($categoryModule->testMessageCount() === 10, 'Category media registrations differ.');
$categoryBootstrap = $categoryModule->testLastVisualizationUpdate();
check(
    array_column($categoryBootstrap['items'] ?? [], 'title') === ['Camera one', 'Camera three'],
    'Category source is not ordered newest first and limited.'
);

$oldCategoryRevision = (string) ($categoryBootstrap['configurationRevision'] ?? '');
$categoryModule->testSetProperty('CategoryNewestFirst', false);
$categoryModule->ApplyChanges();
$oldestFirstBootstrap = $categoryModule->testLastVisualizationUpdate();
check(
    array_column($oldestFirstBootstrap['items'] ?? [], 'title') === ['Camera two', 'Camera three'],
    'Category source is not ordered oldest first.'
);

$categoryModule->testSetProperty('CategoryNewestFirst', true);
$categoryModule->ApplyChanges();
unset($mediaFixtures[101]);
$categoryFixtures[301] = [102, 103, 104, 201];
$categoryModule->MessageSink(time(), 101, MM_DELETE, []);
check($categoryModule->testStatus() === IS_ACTIVE, 'Rolling category deletion disabled the module.');
check(
    $categoryModule->testReferences() === [103, 104, 301],
    'Rolling category references were not reconciled.'
);
$rolledBootstrap = $categoryModule->testLastVisualizationUpdate();
check(($rolledBootstrap['action'] ?? null) === 'bootstrap', 'Category roll did not publish bootstrap.');
check(isset($rolledBootstrap['initialMedia']), 'Category roll did not include a replacement preview.');
check(
    array_column($rolledBootstrap['items'] ?? [], 'title') === ['Camera four', 'Camera three'],
    'Category roll did not expose the newest bounded sequence.'
);

$categoryModule->RequestAction(
    'LoadMedia',
    json_encode(
        [
            'index' => 0,
            'requestID' => 'stale_revision',
            'configurationRevision' => $oldCategoryRevision,
        ],
        JSON_THROW_ON_ERROR
    )
);
$revisionRefresh = $categoryModule->testLastVisualizationUpdate();
check(
    ($revisionRefresh['action'] ?? null) === 'bootstrap',
    'Stale category request did not receive a refreshed bootstrap.'
);
check(
    ($revisionRefresh['configurationRevision'] ?? null)
        === ($rolledBootstrap['configurationRevision'] ?? null),
    'Stale category request received a different current revision.'
);

$form = json_decode(
    (string) file_get_contents(__DIR__ . '/../distribution/MediaCarousel/form.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$list = [];
foreach ($form['elements'] ?? [] as $element) {
    if (($element['name'] ?? null) === 'MediaItems') {
        $list = $element;
        break;
    }
}
check(($list['type'] ?? null) === 'List', 'MediaItems is not configured as a List.');
check(($list['rowCount'] ?? null) === 10, 'MediaItems default row count differs.');
foreach ($list['columns'] ?? [] as $column) {
    check(array_key_exists('add', $column), 'A MediaItems column has no add default.');
}

$moduleSource = (string) file_get_contents(__DIR__ . '/../distribution/MediaCarousel/module.php');
check(!str_contains($moduleSource, 'IPS_SetLinkTargetID'), 'Module still changes a link target.');
check(!str_contains($moduleSource, 'RegisterTimer'), 'Module still owns a server timer.');
check(!str_contains($moduleSource, 'IPS_Create'), 'Module unexpectedly creates Symcon objects.');
check(str_contains($moduleSource, 'resolveCategoryMediaItems'), 'Dynamic category source is missing.');

$frontendSource = (string) file_get_contents(
    __DIR__ . '/../distribution/MediaCarousel/carousel.js'
);
check(str_contains($frontendSource, 'buildPrefetchOrder'), 'Progressive sequence prefetch is missing.');
check(str_contains($frontendSource, 'readyRevisions'), 'Loaded source cache is missing.');
check(str_contains($frontendSource, 'sessionStorage'), 'Session image-source persistence is missing.');
check(str_contains($frontendSource, 'localStorage'), 'Cross-view position persistence is missing.');
check(
    str_contains($frontendSource, 'positionStorageKey'),
    'Position storage is not isolated from image-source storage.'
);
$positionStoreStart = strpos(
    $frontendSource,
    'localStorage.setItem(positionStorageKey(), JSON.stringify({'
);
$positionStoreEnd = $positionStoreStart === false
    ? false
    : strpos($frontendSource, '} catch (error)', $positionStoreStart);
$positionStore = $positionStoreStart === false || $positionStoreEnd === false
    ? ''
    : substr($frontendSource, $positionStoreStart, $positionStoreEnd - $positionStoreStart);
check(
    $positionStore !== '' && !str_contains($positionStore, 'source'),
    'Persistent position storage unexpectedly contains an image source.'
);
check(
    str_contains($frontendSource, 'configurationRevision: state.configurationRevision'),
    'Media requests do not carry the sequence revision.'
);
$moveStart = strpos($frontendSource, 'async function move(');
$loadStart = strpos($frontendSource, 'await waitForLoadedImage(', $moveStart ?: 0);
$busyStart = strpos($frontendSource, 'state.busy = true;', $moveStart ?: 0);
check(
    $moveStart !== false
        && $loadStart !== false
        && $busyStart !== false
        && $busyStart < $loadStart,
    'Navigation is not guarded before asynchronous loading.'
);
check(
    substr_count(
        (string) file_get_contents(__DIR__ . '/../distribution/MediaCarousel/module.html'),
        '<img '
    ) === 3,
    'Frontend does not have exactly three render image slots.'
);

echo "media-carousel-module: ok\n";
