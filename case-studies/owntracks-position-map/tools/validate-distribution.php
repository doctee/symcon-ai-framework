<?php

declare(strict_types=1);

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 3));
$distribution = $argv[1] ?? (
    $projectRoot . '/dist/symcon/saef-owntracks-position-map-module'
);
$distribution = str_replace('\\', '/', $distribution);
$errors = [];

$expectedFiles = [
    'OwnTracksPositionMap/assets/licenses/esbuild-build-tool.txt',
    'OwnTracksPositionMap/assets/licenses/ol.txt',
    'OwnTracksPositionMap/assets/licenses/quickselect.txt',
    'OwnTracksPositionMap/assets/licenses/rbush.txt',
    'OwnTracksPositionMap/assets/openlayers-renderer.bundle.css',
    'OwnTracksPositionMap/assets/openlayers-renderer.bundle.js',
    'OwnTracksPositionMap/assets/openlayers-renderer.html',
    'OwnTracksPositionMap/assets/renderer.css',
    'OwnTracksPositionMap/form.json',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksDayWindow.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksEtaProjector.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksMotionAwareTargetResolver.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksOsmTileProviderPolicy.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksPinnedHttpsTileTransport.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksProviderPolicy.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksProviderTileCache.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksProviderTileRuntime.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksSymconArchiveAdapter.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileAccessPolicy.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileCapability.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileDeadline.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileDirectoryAuthority.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileFileCache.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileGateway.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileMissResolver.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileMissStateStore.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileRequestBudget.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileSelectionAllowlist.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTileWebhookAdapter.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksTrackCore.php',
    'OwnTracksPositionMap/libs/OwnTracks/OwnTracksWgs84.php',
    'OwnTracksPositionMap/locale.json',
    'OwnTracksPositionMap/module.json',
    'OwnTracksPositionMap/module.php',
    'fileset.sha256',
    'fileset.sources.json',
    'library.json',
];
$actualFiles = distributionFiles($distribution, $errors);
if ($actualFiles !== $expectedFiles) {
    $errors[] = 'Generated distribution inventory differs.';
}

$library = readJsonObject($distribution . '/library.json', $errors);
$module = readJsonObject(
    $distribution . '/OwnTracksPositionMap/module.json',
    $errors
);
$form = readJsonObject(
    $distribution . '/OwnTracksPositionMap/form.json',
    $errors
);
readJsonObject(
    $distribution . '/OwnTracksPositionMap/locale.json',
    $errors
);
$sourceMap = readJsonObject(
    $distribution . '/fileset.sources.json',
    $errors
);

exactKeys(
    $library,
    ['id', 'author', 'name', 'url', 'version', 'build', 'date', 'compatibility'],
    'library.json',
    $errors
);
exactKeys(
    $module,
    [
        'id', 'name', 'type', 'vendor', 'aliases', 'url',
        'parentRequirements', 'childRequirements', 'implemented', 'prefix',
    ],
    'module.json',
    $errors
);
validateGuid($library['id'] ?? null, 'library GUID', $errors);
validateGuid($module['id'] ?? null, 'module GUID', $errors);
if (($library['id'] ?? null) === ($module['id'] ?? null)) {
    $errors[] = 'Library and module GUID must differ.';
}
if (
    ($library['version'] ?? null) !== '0.1.0'
    || ($library['compatibility']['version'] ?? null) !== '8.1'
) {
    $errors[] = 'Library preview version or compatibility differs.';
}
if (
    ($module['name'] ?? null) !== 'OwnTracksPositionMap'
    || ($module['type'] ?? null) !== 3
    || ($module['prefix'] ?? null) !== 'SAEFOTPM'
) {
    $errors[] = 'Module identity, type or prefix differs.';
}
if (($module['parentRequirements'] ?? null) !== []) {
    $errors[] = 'Module must not require a parent instance.';
}
if (($sourceMap['name'] ?? null) !== 'saef-owntracks-position-map-module') {
    $errors[] = 'Fileset identity differs.';
}
if (count($sourceMap['files'] ?? []) !== 35) {
    $errors[] = 'Fileset payload count differs.';
}
foreach ($sourceMap['files'] ?? [] as $mapping) {
    if (!is_array($mapping)) {
        $errors[] = 'Fileset source-map entry is invalid.';
        continue;
    }
    $target = $mapping['target'] ?? null;
    $expectedHash = $mapping['sha256'] ?? null;
    if (!is_string($target) || !is_string($expectedHash)) {
        $errors[] = 'Fileset source-map fields are invalid.';
        continue;
    }
    $targetFile = $distribution . '/' . $target;
    if (!is_file($targetFile) || hash_file('sha256', $targetFile) !== $expectedHash) {
        $errors[] = 'Generated target hash differs: ' . $target . '.';
    }
}

$modulePhp = readText(
    $distribution . '/OwnTracksPositionMap/module.php',
    $errors
);
foreach (
    [
        'class OwnTracksPositionMap extends IPSModuleStrict',
        "__DIR__ . '/libs/OwnTracks'",
        "__DIR__ . '/assets'",
        "'RegisteredReferences'",
        "'ActiveRequests'",
        "['tileAccess']",
        "'TileCapabilitySecret'",
        'ProcessHookData',
        'OwnTracksTileWebhookAdapter::handle',
        'OwnTracksTileWebhookAdapter::issueCapability',
        'OwnTracksTileDirectoryAuthority',
        'OwnTracksProviderTileRuntime',
        'OwnTracksProviderTileCache',
        'OwnTracksTileMissStateStore',
        'fetchWithSystemTransport',
        'RegisterHook(self::TILE_HOOK_ADDRESS)',
        'disabledTileResponse',
        "connect-src &apos;none&apos;",
    ] as $marker
) {
    if (!str_contains($modulePhp, $marker)) {
        $errors[] = 'Packaged module marker is missing: ' . $marker . '.';
    }
}
foreach (['SetValue(', 'AC_SetLoggingStatus', 'AC_DeleteVariableData'] as $forbidden) {
    if (str_contains($modulePhp, $forbidden)) {
        $errors[] = 'Packaged module contains forbidden authority: ' . $forbidden . '.';
    }
}
$formNames = [];
foreach ($form['elements'] ?? [] as $element) {
    if (is_array($element) && is_string($element['name'] ?? null)) {
        $formNames[] = $element['name'];
    }
}
sort($formNames, SORT_STRING);
$expectedFormNames = [
    'AllowGeodesicEta',
    'EtaTargetLocations',
    'ExternalAnchorID',
    'ExternalAnchorPositionIdent',
    'HistoryDays',
    'MaxArchiveRecords',
    'MaxRenderedPoints',
    'MaximumAccuracyAgeSeconds',
    'MaximumAccuracyMeters',
    'MaximumActivityAgeSeconds',
    'MaximumGapSeconds',
    'MaximumReceptionDelaySeconds',
    'MaximumStepDistanceMeters',
    'SelectedTimeZone',
    'Sources',
];
sort($expectedFormNames, SORT_STRING);
if ($formNames !== $expectedFormNames) {
    $errors[] = 'Configuration form property inventory differs.';
}
$formJson = json_encode($form, JSON_THROW_ON_ERROR);
if (str_contains($formJson, 'ProviderConfiguration')) {
    $errors[] = 'Provider configuration must not be exposed in this package.';
}

foreach ($actualFiles as $relativeFile) {
    $contents = readText($distribution . '/' . $relativeFile, $errors);
    foreach (['/Users/', '\\Users\\', '192.168.'] as $privateMarker) {
        if (str_contains($contents, $privateMarker)) {
            $errors[] = 'Distribution contains a private path or address marker.';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "OwnTracks distribution validation failed:\n");
    foreach (array_values(array_unique($errors)) as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "OwnTracks distribution structure is valid.\n");

/** @param list<string> $errors @return list<string> */
function distributionFiles(string $root, array &$errors): array
{
    if (!is_dir($root) || is_link($root)) {
        $errors[] = 'Generated distribution root is missing or unsafe.';
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
            continue;
        }
        if ($entry->isLink()) {
            $errors[] = 'Generated distribution contains a symbolic link.';
            continue;
        }
        $files[] = str_replace(
            '\\',
            '/',
            substr($entry->getPathname(), strlen($root) + 1)
        );
    }
    sort($files, SORT_STRING);

    return $files;
}

/** @param list<string> $errors @return array<string, mixed> */
function readJsonObject(string $path, array &$errors): array
{
    try {
        $decoded = json_decode(
            readText($path, $errors),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded) || array_is_list($decoded)) {
            $errors[] = basename($path) . ' must contain a JSON object.';
            return [];
        }

        return $decoded;
    } catch (Throwable $exception) {
        $errors[] = basename($path) . ' is invalid JSON.';
        return [];
    }
}

/** @param list<string> $errors */
function readText(string $path, array &$errors): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        $errors[] = basename($path) . ' is unreadable.';
        return '';
    }

    return $contents;
}

/** @param array<string, mixed> $value @param list<string> $expected @param list<string> $errors */
function exactKeys(
    array $value,
    array $expected,
    string $label,
    array &$errors
): void {
    $actual = array_keys($value);
    sort($actual, SORT_STRING);
    sort($expected, SORT_STRING);
    if ($actual !== $expected) {
        $errors[] = $label . ' fields differ.';
    }
}

/** @param list<string> $errors */
function validateGuid(mixed $value, string $label, array &$errors): void
{
    if (
        !is_string($value)
        || preg_match(
            '/^\{[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}\}$/D',
            $value
        ) !== 1
    ) {
        $errors[] = $label . ' is invalid.';
    }
}
