<?php

declare(strict_types=1);

if (!defined('OWNTRACKS_RENDERER_FIXTURE_DATA_ONLY')) {
    define('OWNTRACKS_RENDERER_FIXTURE_DATA_ONLY', true);
}
require_once __DIR__ . '/renderer-fixture.php';

if (($_GET['etaUnavailable'] ?? null) === 'position-stale') {
    foreach ($overview['etaEntries'] as &$entry) {
        $entry['status'] = 'unavailable';
        $entry['strategy'] = 'none';
        $entry['reason'] = 'position-stale';
        $entry['etaSeconds'] = null;
    }
    unset($entry);
}

$rendererDirectory = __DIR__ . '/../candidate/renderer';
$bundleDirectory = __DIR__ . '/../candidate/openlayers';
$markup = file_get_contents(
    $rendererDirectory . '/openlayers-renderer.html'
);
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
    exit('OpenLayers renderer fixture assets unavailable.');
}

$fixtureScript = sprintf(
    'window.rendererFixtureResults=%s;window.rendererFixtureOverview=%s;window.rendererFixtureRequests=[];const requestAction=function(action,payload){if(action!=="SelectTrack"){return;}var request=JSON.parse(payload);window.rendererFixtureRequests.push(request);var fixture=request.viewMode==="current-overview"?window.rendererFixtureOverview:window.rendererFixtureResults[request.sourceKey+"|"+request.selectedDate];var delay=request.requestGeneration===1?%d:10;window.setTimeout(function(){if(!fixture){return;}var etaEntries=request.viewMode==="current-overview"&&request.etaSourceKey?fixture.etaEntries.filter(function(entry){return entry.sourceKey===request.etaSourceKey;}):[];window.handleMessage({action:"trackResult",viewMode:request.viewMode,requestGeneration:request.requestGeneration,result:fixture.result,target:fixture.target,eta:fixture.eta,etaEntries:etaEntries});},delay);};',
    json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    json_encode($overview, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ($_GET['slowSelection'] ?? null) === '1' ? 20_500 : 40
);
$simulateSymconDark = ($_GET['symconTheme'] ?? null) === 'dark';
$simulateHostChrome = ($_GET['hostChrome'] ?? null) === '1';

header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'none'; img-src data:");
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
echo '<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;; style-src &apos;unsafe-inline&apos;; script-src &apos;unsafe-inline&apos;; connect-src &apos;none&apos;; img-src data:">';
echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>OwnTracks OpenLayers offline renderer</title><style>';
echo $style, $openLayersStyle;
if ($simulateSymconDark) {
    echo ':root{--card-color:#161f26;--content-color:#edf4f7;'
        . '--accent-color:#42b9df}';
}
if ($simulateHostChrome) {
    echo '[data-host-chrome-probe]{position:fixed;inset:0 0 auto 0;height:46px;'
        . 'z-index:99;pointer-events:auto;cursor:pointer;background:transparent}';
}
echo '</style></head><body>';
if ($simulateHostChrome) {
    echo '<div data-host-chrome-probe aria-hidden="true"></div>';
}
echo $markup;
echo '<script>', $fixtureScript, '</script><script>', $script, '</script>';
echo '<script>window.handleOwnTracksOpenLayersMessage(';
echo json_encode($bootstrap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
echo ');</script></body></html>';
