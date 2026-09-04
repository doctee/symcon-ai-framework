<?php

declare(strict_types=1);

if (!defined('OWNTRACKS_RENDERER_FIXTURE_DATA_ONLY')) {
    define('OWNTRACKS_RENDERER_FIXTURE_DATA_ONLY', true);
}
require_once __DIR__ . '/renderer-fixture.php';

if (($_GET['legacy'] ?? null) === '1') {
    foreach ($results as &$fixtureResult) {
        foreach ($fixtureResult['result']['render']['points'] as &$point) {
            $point['horizontalAccuracyMeters'] = null;
            $point['accuracyAttribution'] = 'unknown';
            $point['lineConfidence'] = 'unverified';
            if (!in_array('accuracy-unknown', $point['qualityFlags'], true)) {
                $point['qualityFlags'][] = 'accuracy-unknown';
            }
        }
        unset($point);
        $fixtureResult['result']['statistics']['renderedUnverifiedPoints'] =
            $fixtureResult['result']['statistics']['renderedPoints'];
    }
    unset($fixtureResult);
}

$rendererDirectory = __DIR__ . '/../candidate/renderer';
$bundleDirectory = __DIR__ . '/../candidate/openlayers';
$markup = file_get_contents($rendererDirectory . '/openlayers-renderer.html');
$style = file_get_contents($rendererDirectory . '/renderer.css');
$openLayersStyle = file_get_contents(
    $bundleDirectory . '/openlayers-renderer.bundle.css'
);
$script = file_get_contents(
    $bundleDirectory . '/openlayers-renderer.bundle.js'
);
if (
    $markup === false
    || $style === false
    || $openLayersStyle === false
    || $script === false
) {
    http_response_code(500);
    exit('Protected tile fixture assets unavailable.');
}

$originalBootstrap = $bootstrap;
$bootstrap['basemap'] = [
    'mode' => 'same-origin-xyz',
    'enabled' => true,
    'authorityKey' => 'synthetic-internal-tiles',
    'tileLayerKind' => 'xyz',
    'urlTemplate' => '/hook/owntracks-position-map/{z}/{x}/{y}.png',
    'maximumZoom' => 18,
    'attributionText' => 'Synthetic internal tiles',
    'attributionUrl' => 'https://www.openstreetmap.org/copyright',
    'locationDisclosure' => 'same-origin-tile-index',
    'credentialMode' => 'none',
];
$fixtureFastRefresh = isset($_GET['refresh']);
$fixtureTokenTtl = $fixtureFastRefresh ? 60 : 300;
$fixtureRefreshBeforeExpiry = $fixtureFastRefresh ? 55 : 60;
$bootstrap['tileAccess'] = [
    'mode' => 'symcon-webhook',
    'enabled' => true,
    'authenticationMode' => 'ephemeral-header-capability',
    'headerName' => 'X-SAEF-Tile-Capability',
    'hookPathPrefix' => '/hook/owntracks-position-map',
    'tokenTtlSeconds' => $fixtureTokenTtl,
    'refreshBeforeExpirySeconds' => $fixtureRefreshBeforeExpiry,
    'maximumConcurrentRequests' => 4,
];
$fixtureCapability = isset($_GET['reject'])
    ? 'eyJzeW50aGV0aWMiOnRydWV9.d3Jvbmc'
    : 'eyJzeW50aGV0aWMiOnRydWV9.c3ludGhldGlj';

$fixtureScript = sprintf(
    'window.rendererFixtureResults=%s;window.rendererFixtureOverview=%s;'
    . 'window.rendererFixtureRequests=[];'
    . 'document.documentElement.dataset.fixtureViewportRequests="0";'
    . 'const requestAction=function(action,payload){var request=JSON.parse(payload);'
    . 'window.rendererFixtureRequests.push({action:action,request:request});'
    . 'if(action==="RequestTileCapability"){window.setTimeout(function(){'
    . 'window.handleMessage({action:"tileCapability",requestGeneration:'
    . 'request.requestGeneration,token:%s,'
    . 'expiresAt:Math.floor(Date.now()/1000)+%d});},5);return;}'
    . 'if(action==="RequestTileViewport"){window.setTimeout(function(){'
    . 'document.documentElement.dataset.fixtureViewportRequests=String('
    . 'Number(document.documentElement.dataset.fixtureViewportRequests)+1);'
    . 'document.documentElement.dataset.fixtureViewportGeneration=String('
    . 'request.viewportGeneration);'
    . 'document.documentElement.dataset.fixtureViewportZoom=String('
    . 'request.zoom);'
    . 'window.handleMessage({action:"tileViewport",requestGeneration:'
    . 'request.requestGeneration,viewportGeneration:'
    . 'request.viewportGeneration});},5);return;}'
    . 'if(action!=="SelectTrack"){return;}var fixture=request.viewMode'
    . '==="current-overview"?window.rendererFixtureOverview:'
    . 'window.rendererFixtureResults[request.sourceKey+"|"+request.selectedDate];'
    . 'window.setTimeout(function(){if(!fixture){return;}'
    . 'window.handleMessage({action:"trackResult",requestGeneration:'
    . 'request.requestGeneration,viewMode:request.viewMode,result:'
    . 'fixture.result,anchor:null,target:'
    . 'fixture.target,eta:fixture.eta,etaEntries:fixture.etaEntries||[]});},20);};',
    json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    json_encode($overview, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    json_encode($fixtureCapability, JSON_THROW_ON_ERROR),
    $fixtureTokenTtl
);

header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'self'; img-src data: blob:");
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
echo '<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;; style-src &apos;unsafe-inline&apos;; script-src &apos;unsafe-inline&apos;; connect-src &apos;self&apos;; img-src data: blob:">';
echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>OwnTracks protected tile gateway fixture</title><style>';
echo $style, $openLayersStyle, '</style></head><body>', $markup;
echo '<script>', $fixtureScript, '</script><script>', $script, '</script>';
echo '<script>window.handleOwnTracksOpenLayersMessage(';
echo json_encode($bootstrap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
echo ');</script></body></html>';
$bootstrap = $originalBootstrap;
unset($originalBootstrap);
unset($fixtureCapability);
unset($fixtureFastRefresh, $fixtureTokenTtl, $fixtureRefreshBeforeExpiry);
