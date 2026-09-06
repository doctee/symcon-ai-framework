<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksDayWindow;
use OwnTracksPositionMap\Prototype\OwnTracksEtaProjector;
use OwnTracksPositionMap\Prototype\OwnTracksProviderPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksTrackCore;
use OwnTracksPositionMap\Prototype\OwnTracksWgs84;

require_once __DIR__ . '/bootstrap.php';

/** @return array<string, mixed> */
function buildRendererResult(
    string $sourceKey,
    int $sourceIndex,
    string $selectedDate
): array {
    $window = OwnTracksDayWindow::fromLocalDate(
        $selectedDate,
        'Europe/Berlin'
    );
    $baseLatitude = 10.0 + $sourceIndex * 0.12;
    $baseLongitude = 20.0 + $sourceIndex * 0.12;
    $positions = [];
    $accuracy = [];
    $count = 360;
    for ($index = 0; $index < $count; $index++) {
        $gap = $index >= 180 ? 45 * 60 : 0;
        $observedAt = $window['from'] + 60 + $index * 120 + $gap;
        $phase = $index / 22.0;
        $latitude = $baseLatitude
            + $index * 0.0006
            + sin($phase) * 0.0018;
        $longitude = $baseLongitude
            + $index * 0.0007
            + cos($phase) * 0.0015;
        $positions[] = [
            'TimeStamp' => $observedAt + 4,
            'Value' => json_encode(
                [
                    'tst' => $observedAt,
                    'lat' => round($latitude, 7),
                    'lon' => round($longitude, 7),
                    'alt' => 100 + ($index % 12),
                ],
                JSON_THROW_ON_ERROR
            ),
        ];
        if ($index % 15 === 0) {
            $accuracy[] = [
                'TimeStamp' => $observedAt,
                'Value' => $sourceIndex === 2 && $index >= 240
                    ? 140.0
                    : 8.0 + ($index % 5),
            ];
        }
    }
    $positions = array_reverse($positions);
    $accuracy = array_reverse($accuracy);
    $result = OwnTracksTrackCore::project(
        $positions,
        $accuracy,
        [
            'requestGeneration' => 1,
            'sourceKey' => $sourceKey,
            'from' => $window['from'],
            'to' => $window['to'],
            'renderMode' => 'line-with-sampled-timestamps',
            'maxArchiveRecords' => 1000,
            'maxRenderedPoints' => 48,
            'archiveLimitReached' => false,
            'maximumGapSeconds' => 60 * 60,
            'maximumAccuracyAgeSeconds' => 40 * 60,
            'maximumAccuracyMeters' => 100.0,
        ]
    );
    $latest = $result['etaEvidence'][count($result['etaEvidence']) - 1];
    $target = [
        'targetKey' => 'synthetic-destination-' . ($sourceIndex + 1),
        'latitude' => $latest['latitudeDegrees'] + 0.009,
        'longitude' => $latest['longitudeDegrees'] + 0.011,
    ];
    if ($sourceIndex === 0) {
        $target['routeEstimate'] = [
            'authorityKey' => 'synthetic-router',
            'estimatedAt' => $latest['observedAt'],
            'etaSeconds' => 18 * 60,
            'remainingDistanceMeters' => 6400.0,
        ];
    }
    $eta = OwnTracksEtaProjector::project(
        $result['etaEvidence'],
        $sourceIndex === 2 ? null : $target,
        [
            'evaluatedAt' => $latest['observedAt'] + 60,
            'allowGeodesicFallback' => true,
            'lookbackSeconds' => 60 * 60,
        ]
    );

    return [
        'result' => $result,
        'target' => $sourceIndex === 2 ? null : $target,
        'eta' => $eta,
    ];
}

$sources = [
    ['sourceKey' => 'synthetic-a', 'label' => 'Synthetic A'],
    ['sourceKey' => 'synthetic-b', 'label' => 'Synthetic B'],
    ['sourceKey' => 'synthetic-c', 'label' => 'Synthetic C'],
];
$dates = ['2024-03-31', '2024-04-01'];
$results = [];
foreach ($sources as $sourceIndex => $source) {
    foreach ($dates as $selectedDate) {
        $results[$source['sourceKey'] . '|' . $selectedDate] =
            buildRendererResult(
                $source['sourceKey'],
                $sourceIndex,
                $selectedDate
            );
    }
}
$overviewPoints = [];
foreach ($sources as $source) {
    $track = $results[$source['sourceKey'] . '|' . $dates[0]]['result'];
    $point = $track['render']['points'][count($track['render']['points']) - 1];
    $point['segmentIndex'] = null;
    $point['lineEligible'] = false;
    $point['sourceKey'] = $source['sourceKey'];
    $point['sourceLabel'] = $source['label'];
    $point['observedDate'] = $dates[0];
    $overviewPoints[] = $point;
}
if (count($overviewPoints) >= 2) {
    $overviewPoints[1]['latitudeDegrees'] =
        $overviewPoints[0]['latitudeDegrees'] + 0.00001;
    $overviewPoints[1]['longitudeDegrees'] =
        $overviewPoints[0]['longitudeDegrees'] + 0.00001;
}
$overviewBounds = OwnTracksWgs84::bounds(array_map(
    static fn (array $point): array => [
        'latitude' => $point['latitudeDegrees'],
        'longitude' => $point['longitudeDegrees'],
    ],
    $overviewPoints
));
$overview = [
    'result' => [
        'requestGeneration' => 1,
        'sourceKey' => 'current-overview',
        'coordinateReference' => 'EPSG:4326',
        'query' => ['renderMode' => 'current-overview'],
        'fitBounds' => $overviewBounds,
        'render' => [
            'mode' => 'current-overview',
            'points' => $overviewPoints,
            'segments' => [],
            'segmentCount' => 0,
        ],
        'statistics' => [
            'validObservations' => 3,
            'renderedPoints' => 3,
            'fitObservationCount' => 3,
        ],
    ],
    'target' => null,
    'eta' => ['status' => 'unavailable', 'routeAware' => false],
    'etaEntries' => [[
        'status' => 'available',
        'sourceKey' => $sources[0]['sourceKey'],
        'sourceLabel' => $sources[0]['label'],
        'etaSeconds' => 18 * 60,
        'routeAware' => false,
    ], [
        'status' => 'unavailable',
        'sourceKey' => $sources[1]['sourceKey'],
        'sourceLabel' => $sources[1]['label'],
        'routeAware' => false,
    ], [
        'status' => 'reached',
        'sourceKey' => $sources[2]['sourceKey'],
        'sourceLabel' => $sources[2]['label'],
        'routeAware' => false,
    ]],
];

$rendererDirectory = __DIR__ . '/../candidate/renderer';
$markup = file_get_contents($rendererDirectory . '/renderer.html');
$style = file_get_contents($rendererDirectory . '/renderer.css');
$script = file_get_contents($rendererDirectory . '/renderer.js');
if ($markup === false || $style === false || $script === false) {
    http_response_code(500);
    exit('Renderer fixture assets unavailable.');
}

$bootstrap = [
    'action' => 'bootstrap',
    'sources' => $sources,
    'selectedSourceKey' => 'synthetic-a',
    'minimumDate' => $dates[0],
    'maximumDate' => $dates[count($dates) - 1],
    'selectedDate' => $dates[0],
    'basemap' => OwnTracksProviderPolicy::normalize([
        'basemap' => ['mode' => 'none'],
        'routing' => [
            'mode' => 'none',
            'allowGeodesicFallback' => true,
        ],
    ])['basemap'],
];
if (defined('OWNTRACKS_RENDERER_FIXTURE_DATA_ONLY')) {
    return;
}
$fixtureScript = sprintf(
    'window.rendererFixtureResults=%s;window.rendererFixtureOverview=%s;window.rendererFixtureRequests=[];window.requestAction=function(action,payload){if(action!=="SelectTrack"){return;}var request=JSON.parse(payload);window.rendererFixtureRequests.push(request);var fixture=request.viewMode==="current-overview"?window.rendererFixtureOverview:window.rendererFixtureResults[request.sourceKey+"|"+request.selectedDate];var delay=request.requestGeneration===1?40:10;window.setTimeout(function(){if(!fixture){return;}window.handleMessage({action:"trackResult",viewMode:request.viewMode,requestGeneration:request.requestGeneration,result:fixture.result,target:fixture.target,eta:fixture.eta});},delay);};',
    json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    json_encode($overview, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
);

header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'");
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>OwnTracks offline renderer</title><style>', $style, '</style>';
echo '</head><body>', $markup, '<script>', $fixtureScript, '</script>';
echo '<script>', $script, '</script><script>';
echo 'window.handleOwnTracksMapMessage(',
    json_encode($bootstrap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ');';
echo '</script></body></html>';
