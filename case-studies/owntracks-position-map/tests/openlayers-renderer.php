<?php

declare(strict_types=1);

$caseStudyDirectory = dirname(__DIR__);
$browserDirectory = $caseStudyDirectory . '/browser';
$bundleDirectory = $caseStudyDirectory . '/candidate/openlayers';
$source = file_get_contents(
    $browserDirectory . '/src/openlayers-renderer.js'
);
$markup = file_get_contents(
    $caseStudyDirectory . '/candidate/renderer/openlayers-renderer.html'
);
$style = file_get_contents(
    $caseStudyDirectory . '/candidate/renderer/renderer.css'
);
$manifestJson = file_get_contents(
    $bundleDirectory . '/bundle-manifest.json'
);
$packageJson = file_get_contents($browserDirectory . '/package.json');
$lockJson = file_get_contents($browserDirectory . '/package-lock.json');
$bundle = file_get_contents(
    $bundleDirectory . '/openlayers-renderer.bundle.js'
);
$runtimeModule = file_get_contents(
    $caseStudyDirectory . '/candidate/runtime/OwnTracksPositionMap/module.php'
);
if (
    $source === false
    || $markup === false
    || $style === false
    || $manifestJson === false
    || $packageJson === false
    || $lockJson === false
    || $bundle === false
    || $runtimeModule === false
) {
    throw new RuntimeException('OpenLayers candidate assets cannot be read.');
}
foreach (
    [
        '.ot-map__tooltip[hidden]',
        'pointer-events: none',
        '.ot-map .ol-viewport',
        'touch-action: none',
        'touch-action: auto',
        'overscroll-behavior: contain',
        'isolation: isolate',
        '--ot-host-chrome-height: 46px',
        '--ot-panel-frame-top: 6px',
        '--card-color',
        '--content-color',
        '--accent-color',
        'grid-template-columns: minmax(0, 81px) minmax(0, 77px)',
        'width: min(290px, calc(100% - 108px))',
        'min-inline-size: 0',
        'max-inline-size: 100%',
        'padding-left: 3px',
        'height: 26px',
        '.ot-map__eta[hidden]',
        'padding: 4px 6px',
        'opacity: 0.58',
        'cursor: default',
        '::-webkit-date-and-time-value',
        '@media (max-width: 560px)',
        'padding: 29px 4px 3px',
        'top: var(--ot-host-chrome-height)',
        'background: transparent',
        'text-align: center',
        'grid-template-columns: 30px 30px',
        'font-size: 12px',
    ] as $required
) {
    if (!str_contains($style, $required)) {
        throw new RuntimeException(
            'OpenLayers tooltip style is missing required token: ' . $required
        );
    }
}
if (str_contains($style, 'backdrop-filter')) {
    throw new RuntimeException(
        'OpenLayers controls retain the Safari backdrop-filter hit-test risk.'
    );
}
if (preg_match('/\.ot-map\s*\{[^}]*touch-action:\s*none/s', $style) === 1) {
    throw new RuntimeException(
        'OpenLayers map root still disables native control gestures.'
    );
}
$manifest = json_decode($manifestJson, true, 32, JSON_THROW_ON_ERROR);
$package = json_decode($packageJson, true, 32, JSON_THROW_ON_ERROR);
$lock = json_decode($lockJson, true, 64, JSON_THROW_ON_ERROR);
if (!is_array($manifest) || !is_array($package) || !is_array($lock)) {
    throw new RuntimeException('OpenLayers bundle manifest is invalid.');
}
if (
    ($package['dependencies']['ol'] ?? null) !== '10.10.0'
    || ($package['devDependencies']['esbuild'] ?? null) !== '0.28.2'
) {
    throw new RuntimeException('OpenLayers package pins drifted.');
}
$expectedPins = [
    'node_modules/ol' => [
        '10.10.0',
        'sha512-tLPKn6zl+6uWdPufYlqG/lQzuVUTVmfwahQqVr5+wZNyZecyAtIhMTyOtKpu7ooNDLY2sEjKZNXw9HL+sOjC1A==',
    ],
    'node_modules/esbuild' => [
        '0.28.2',
        'sha512-HKVLS8dvII+xoKW9kmqxbRKrnWEXfJJr/FZhhJmiqIB0e053QNYFqOBouTMO/k5sID4MvCiUCvv8b9M4h32wIA==',
    ],
];
foreach ($expectedPins as $lockKey => [$version, $integrity]) {
    $lockedPackage = $lock['packages'][$lockKey] ?? null;
    if (
        !is_array($lockedPackage)
        || ($lockedPackage['version'] ?? null) !== $version
        || ($lockedPackage['integrity'] ?? null) !== $integrity
    ) {
        throw new RuntimeException('Dependency lock drifted: ' . $lockKey);
    }
}

foreach (
    [
        "from 'ol/Map.js'",
        "from 'ol/View.js'",
        "from 'ol/tilegrid.js'",
        "from 'ol/interaction/defaults.js'",
        "from 'ol/layer/Graticule.js'",
        "from 'ol/layer/Tile.js'",
        "from 'ol/source/ImageTile.js'",
        'fitBounds',
        'crossesAntimeridian',
        'ResizeObserver',
        'map.updateSize()',
        'stale-result-discarded',
        'crypto.getRandomValues',
        'enableRotation: false',
        'altShiftDragRotate: false',
        'pinchRotate: false',
        'rotation: view.getRotation()',
        "openlayersRotationEnabled = 'false'",
        'openlayersRotation = String(',
        'clientSessionKey: clientSessionKey',
        'const labelSource = new VectorSource',
        'const leaderSource = new VectorSource',
        'const arrowSource = new VectorSource',
        "text: '➤'",
        'fill: new Fill({color: colors.track})',
        'rotation: -feature.get(\'direction\')',
        'const midpoint = [',
        'directionArrowCount: arrowSource.getFeatures().length',
        "mode === 'path'",
        "modeSelect.value === 'current-overview'",
        'const minimumOverviewSpanMeters = 100',
        'openlayersMinimumOverviewFitMeters',
        'viewMode: modeSelect.value',
        'sourceSelect.disabled = overview',
        'daySelect.disabled = overview',
        "option.textContent = 'All'",
        "sourceKey: overview ? 'current-overview' : sourceSelect.value",
        'selectedDate: overview ? state.todayDate : daySelect.value',
        'rebuildOverviewDisplacements',
        'openlayersDisplacedPointCount',
        'openlayersFitOverlayOcclusionCount',
        'fitPadding()',
        'etaSourceKey: overview',
        "etaOutput.textContent = 'Tap a position for ETA'",
        'selectedOverviewSourceKey',
        'restoreSelectedOverviewTooltip',
        "'position-stale': 'position too old'",
        'observationLabel(point)',
        "feature.get('labelTextAlign') || 'left'",
        'estimatedWidth',
        'preload: 0',
        'useInterimTilesOnError: true',
        'declutter: true',
        'timestampLabelCandidateCount: labelSource.getFeatures().length',
        'sameOriginXyzTemplate',
        'map.getLayers().insertAt(0, baseLayer)',
        'loader: loadProtectedTile',
        "requestAction('RequestTileCapability'",
        "requestAction('RequestTileViewport'",
        "payload.action === 'tileViewport'",
        "'ready-pending-viewport'",
        'statusOutput.getBoundingClientRect()',
        'X-SAEF-Tile-Capability',
        'X-SAEF-Tile-Viewport',
        'const viewportGeneration = state.tileViewportAcceptedGeneration',
        'state.tileGrid.getZForResolution(view.getResolution())',
        'tileGrid: state.tileGrid',
        'openlayersTileViewportZoom',
        'openlayersTileRequestMinimumZoom',
        'openlayersTileRequestMaximumZoom',
        "feature.get('lineConfidence') === 'unverified'",
        'lineDash: unverified ? [6, 5] : undefined',
        'credentials: \'same-origin\'',
        "cache: 'no-cache'",
        "redirect: 'error'",
        'URL.createObjectURL(blob)',
        'URL.revokeObjectURL(objectUrl)',
        'openlayersTileMissingCount',
        'configuration.maximumZoom > 22',
        'link.textContent = configuration.attributionText',
        "link.rel = 'noreferrer noopener'",
        'openlayersProviderMode',
        'tileLayerCount: baseLayer ? 1 : 0',
        'tileCapabilityRefreshCount',
        'tileRetryCount',
        'tileRetryDrainWaitCount',
        'tileViewportFailureCount',
        'tileFailureCounts',
        'tileManualRearmCount',
        'configureBasemapWhenIdle',
        'waiting-for-basemap-drain',
        'tileBasemapDrainWaitCount',
        'openlayersTileRetryState',
        "'waiting-for-queue'",
        'const tileRetryDelaysMilliseconds = [3000, 60000]',
        'const tileRetryDrainPollMilliseconds = 250',
        'const tileManualRearmCooldownMilliseconds = 3000',
        'requestTileViewport({',
        'force: true',
        'preserveRetryCount: true',
        'rearmMissingTiles',
        "state.lastAction = 'fit-all-tile-rearm'",
        "'requesting-viewport'",
        "'viewport-refreshed'",
        "'ready-pending-fit'",
        'tileObjectUrlBalance',
        'openlayersLabelCount',
        'openlayersProjectionMilliseconds',
        "map.on('pointermove'",
        "addEventListener('pointerleave'",
        'setTimeout(hidePointTooltip, 4000)',
        'openlayersTooltipState',
        'releaseCommittedPickerFocus',
        "control.matches(':open')",
        "'(hover: none) and (pointer: coarse)'",
        'control.blur()',
        'handleSelectionChange',
        '__ownTracksOpenLayersDiagnostics',
    ] as $required
) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException(
            'OpenLayers source is missing required token: ' . $required
        );
    }
}
if (str_contains($source, 'Math.round(view.getZoom()')) {
    throw new RuntimeException(
        'Tile viewport authorization still rounds the view zoom '
            . 'instead of using the source tile grid.'
    );
}
$retryDelayMatch = [];
$viewportGraceMatch = [];
if (
    preg_match(
        '/const tileRetryDelaysMilliseconds = \[([0-9]+), ([0-9]+)\];/',
        $source,
        $retryDelayMatch
    ) !== 1
    || preg_match(
        '/TILE_VIEWPORT_GRACE_SECONDS = ([0-9]+);/',
        $runtimeModule,
        $viewportGraceMatch
    ) !== 1
    || (int) $retryDelayMatch[1] <= 0
    || (int) $retryDelayMatch[1] >= (int) $viewportGraceMatch[1] * 1000
    || (int) $retryDelayMatch[2]
        < (int) $viewportGraceMatch[1] * 1000
) {
    throw new RuntimeException(
        'Tile retry schedule does not separate transient and minute budgets.'
    );
}
$retryStart = strpos($source, 'function scheduleTileRetry(');
$retryEnd = $retryStart === false
    ? false
    : strpos($source, 'function cancelTileRequests()', $retryStart);
if (
    $retryStart === false
    || $retryEnd === false
    || str_contains(
        substr($source, $retryStart, $retryEnd - $retryStart),
        'source.refresh()'
    )
) {
    throw new RuntimeException(
        'Tile retry still reuses the expired OpenLayers source.'
    );
}
$retrySection = substr($source, $retryStart, $retryEnd - $retryStart);
$retryLimit = strpos(
    $retrySection,
    'state.tileRetryCount >= tileRetryDelaysMilliseconds.length'
);
$drainGuard = strpos($retrySection, 'state.tileQueue.length > 0');
$activeGuard = strpos($retrySection, 'state.activeTileRequests.size > 0');
$retryIncrement = strpos($retrySection, 'state.tileRetryCount += 1');
$viewportRequest = strpos($retrySection, 'requestTileViewport({');
if (
    $retryLimit === false
    || $drainGuard === false
    || $activeGuard === false
    || $retryIncrement === false
    || $viewportRequest === false
    || $drainGuard > $retryIncrement
    || $activeGuard > $retryIncrement
    || $retryIncrement > $viewportRequest
) {
    throw new RuntimeException(
        'Tile retry must wait for queued and active requests before refreshing '
            . 'the viewport source.'
    );
}
$fitAllStart = strpos($source, 'function fitAll(options = {})');
$fitAllEnd = $fitAllStart === false
    ? false
    : strpos($source, 'function clearTileViewportTimer()', $fitAllStart);
if (
    $fitAllStart === false
    || $fitAllEnd === false
    || !str_contains(
        substr($source, $fitAllStart, $fitAllEnd - $fitAllStart),
        'rearmMissingTiles: options.rearmMissingTiles === true'
    )
) {
    throw new RuntimeException(
        'Fit all must preserve user-requested missing-tile rearming.'
    );
}
$viewportStart = strpos($source, 'function requestTileViewport(');
$viewportEnd = $viewportStart === false
    ? false
    : strpos($source, 'function scheduleTileViewport(', $viewportStart);
$viewportSection = $viewportStart === false || $viewportEnd === false
    ? ''
    : substr($source, $viewportStart, $viewportEnd - $viewportStart);
foreach (
    [
        'state.tileViewportFailureCount > 0',
        'state.tileManualRearmViewportGeneration',
        'tileManualRearmCooldownMilliseconds',
        'state.tileManualRearmCount += 1',
        'options.preserveRetryCount === true || manualRearm',
    ] as $manualRearmToken
) {
    if (!str_contains($viewportSection, $manualRearmToken)) {
        throw new RuntimeException(
            'Manual tile rearm is missing its bounded guard: '
                . $manualRearmToken
        );
    }
}
$pickerStart = strpos($source, 'function releaseCommittedPickerFocus(');
$pickerEnd = $pickerStart === false
    ? false
    : strpos($source, 'function renderEta()', $pickerStart);
$pickerSection = $pickerStart === false || $pickerEnd === false
    ? ''
    : substr($source, $pickerStart, $pickerEnd - $pickerStart);
if (
    !str_contains($pickerSection, 'window.requestAnimationFrame(function ()')
    || !str_contains($pickerSection, 'document.activeElement !== control')
    || !str_contains($pickerSection, "control.matches(':open')")
    || !str_contains($pickerSection, 'control.blur()')
) {
    throw new RuntimeException(
        'Committed picker focus release is not deferred and scoped.'
    );
}
foreach (
    [
        "sourceSelect.addEventListener('change', handleSelectionChange)",
        "daySelect.addEventListener('change', handleSelectionChange)",
        'releaseCommittedPickerFocus(event.currentTarget)',
    ] as $pickerBinding
) {
    if (!str_contains($source, $pickerBinding)) {
        throw new RuntimeException(
            'Native selection control lacks committed focus release: '
                . $pickerBinding
        );
    }
}
if (str_contains($source, 'new RegularShape')) {
    throw new RuntimeException(
        'Path arrows are still detached geometric marker symbols.'
    );
}
foreach (
    [
        'anchorSource',
        'state.anchor',
        'anchorFeatureCount',
    ] as $forbiddenAnchorToken
) {
    if (str_contains($source, $forbiddenAnchorToken)) {
        throw new RuntimeException(
            'Unselected external position escaped into the OwnTracks renderer: '
            . $forbiddenAnchorToken
        );
    }
}
foreach (
    [
        'data-map-surface',
        'data-source-select',
        'data-day-select',
        'data-mode-select',
        'data-fit-all',
        'data-point-tooltip',
        'data-attribution',
        'role="tooltip"',
        'OpenLayers · no map tiles',
        'value="current-overview" selected>Positions',
        'value="path">Path',
    ] as $required
) {
    if (!str_contains($markup, $required)) {
        throw new RuntimeException(
            'OpenLayers markup is missing required token: ' . $required
        );
    }
}

$expectedPackages = [
    'ol' => ['10.10.0', 'BSD-2-Clause'],
    'quickselect' => ['3.0.0', 'ISC'],
    'rbush' => ['4.0.1', 'MIT'],
];
$actualPackages = [];
foreach ($manifest['runtimePackages'] as $package) {
    $actualPackages[$package['name']] = [
        $package['version'],
        $package['license'],
    ];
    $lockKey = 'node_modules/' . $package['name'];
    $lockedRuntimePackage = $lock['packages'][$lockKey] ?? null;
    if (
        !is_array($lockedRuntimePackage)
        || ($lockedRuntimePackage['version'] ?? null) !== $package['version']
        || !str_starts_with(
            (string) ($lockedRuntimePackage['integrity'] ?? ''),
            'sha512-'
        )
    ) {
        throw new RuntimeException(
            'Runtime dependency lock drifted: ' . $package['name']
        );
    }
    $licensePath = $bundleDirectory . '/' . $package['licenseFile'];
    if (!is_file($licensePath) || filesize($licensePath) === 0) {
        throw new RuntimeException(
            'Runtime license file is missing: ' . $package['name']
        );
    }
}
if ($actualPackages !== $expectedPackages) {
    throw new RuntimeException('OpenLayers runtime package inventory drifted.');
}
if (
    ($manifest['buildTool']['name'] ?? null) !== 'esbuild'
    || ($manifest['buildTool']['version'] ?? null) !== '0.28.2'
    || ($manifest['buildTool']['license'] ?? null) !== 'MIT'
) {
    throw new RuntimeException('OpenLayers build-tool inventory drifted.');
}
foreach ($manifest['artifacts'] as $artifact) {
    $path = $bundleDirectory . '/' . $artifact['file'];
    if (!is_file($path)) {
        throw new RuntimeException('Bundle artifact is missing: ' . $artifact['file']);
    }
    if (filesize($path) !== $artifact['bytes']) {
        throw new RuntimeException('Bundle artifact size drifted: ' . $artifact['file']);
    }
    if (hash_file('sha256', $path) !== $artifact['sha256']) {
        throw new RuntimeException('Bundle artifact hash drifted: ' . $artifact['file']);
    }
}
if (strlen($bundle) > 400000) {
    throw new RuntimeException('OpenLayers JavaScript bundle exceeds 400 kB.');
}
foreach (
    [
        'http://',
        'https://',
        'XMLHttpRequest',
        'WebSocket',
        'tile.openstreetmap',
        'requestAction("Route',
        'localStorage',
        'sessionStorage',
    ] as $forbidden
) {
    if (stripos($source . $markup . $style, $forbidden) !== false) {
        throw new RuntimeException(
            'OpenLayers adapter contains forbidden provider reference: '
            . $forbidden
        );
    }
}
if (stripos($source, 'innerHTML') !== false) {
    throw new RuntimeException(
        'OpenLayers adapter must not render provider attribution as HTML.'
    );
}
foreach (
    [
        "typeof requestAction !== 'function'",
        "requestAction('SelectTrack'",
        'window.handleMessage = window.handleOwnTracksOpenLayersMessage',
        'Still loading selected day…',
        'selection-still-loading',
        'lastRequestDurationMilliseconds',
        'slowRequestCount',
        'action-bridge-unavailable',
    ] as $requiredBridgeToken
) {
    if (!str_contains($source, $requiredBridgeToken)) {
        throw new RuntimeException(
            'OpenLayers adapter is missing its HTML-SDK bridge guard: '
            . $requiredBridgeToken
        );
    }
}
foreach (['Selected day timed out', 'Positions timed out', 'selection-timeout'] as $falseTimeoutToken) {
    if (str_contains($source, $falseTimeoutToken)) {
        throw new RuntimeException(
            'OpenLayers adapter retains a false timeout state: '
            . $falseTimeoutToken
        );
    }
}
if (str_contains($source, 'window.requestAction')) {
    throw new RuntimeException(
        'OpenLayers adapter incorrectly requires requestAction on window.'
    );
}

ob_start();
require __DIR__ . '/tile-gateway-browser-fixture.php';
$protectedFixture = ob_get_clean();
if (!is_string($protectedFixture)) {
    throw new RuntimeException('Protected tile fixture was not generated.');
}
foreach (
    [
        'connect-src &apos;self&apos;',
        'img-src data: blob:',
        '/hook/owntracks-position-map/{z}/{x}/{y}.png',
        'RequestTileCapability',
        'RequestTileViewport',
        'ephemeral-header-capability',
        'Synthetic internal tiles',
    ] as $required
) {
    if (!str_contains($protectedFixture, $required)) {
        throw new RuntimeException(
            'Protected tile fixture is missing required token: ' . $required
        );
    }
}
if (strlen($protectedFixture) > 1024 * 1024) {
    throw new RuntimeException('Protected tile fixture exceeds 1 MiB.');
}

ob_start();
require __DIR__ . '/openlayers-renderer-fixture.php';
$fixture = ob_get_clean();
if (!is_string($fixture)) {
    throw new RuntimeException('OpenLayers fixture was not generated.');
}
foreach (
    [
        '<!doctype html>',
        'Synthetic A',
        'Synthetic B',
        'Synthetic C',
        'window.rendererFixtureOverview=',
        '"sourceKey":"current-overview"',
        '"renderedPoints":3',
        'OpenLayers · no map tiles',
        '"fitObservationCount":360',
        '"renderedPoints":48',
        'connect-src &apos;none&apos;',
        '"basemap":{"mode":"none"',
        'const requestAction=function',
    ] as $required
) {
    if (!str_contains($fixture, $required)) {
        throw new RuntimeException(
            'OpenLayers fixture is missing required token: ' . $required
        );
    }
}
if (strlen($fixture) > 1024 * 1024) {
    throw new RuntimeException('OpenLayers fixture exceeds 1 MiB.');
}

fwrite(STDOUT, "OwnTracks OpenLayers offline renderer tests passed.\n");
