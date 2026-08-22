<?php

declare(strict_types=1);

$moduleDirectory = dirname(__DIR__) . '/distribution/MediaCarousel';
$html = file_get_contents($moduleDirectory . '/module.html');
$javascript = file_get_contents($moduleDirectory . '/carousel.js');

if ($html === false || $javascript === false) {
    http_response_code(500);
    exit('Fixture assets unavailable');
}

$html = str_replace('/* SAEF_MEDIA_CAROUSEL_SCRIPT */', $javascript, $html);
$sources = [];
$items = [];
$colors = ['#005b96', '#2f6b3c', '#8a4f1d', '#6d3d78', '#006d77', '#9b2226', '#264653', '#7f5539', '#3a0ca3', '#495057'];

for ($index = 0; $index < 10; $index++) {
    $number = $index + 1;
    $svg = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><rect width="100%%" height="100%%" fill="%s"/><text x="50%%" y="46%%" text-anchor="middle" dominant-baseline="middle" fill="white" font-family="sans-serif" font-size="120">Kamera %d</text><text x="50%%" y="62%%" text-anchor="middle" dominant-baseline="middle" fill="white" opacity=".72" font-family="sans-serif" font-size="42">synthetisches Browser-Testbild</text></svg>',
        $colors[$index],
        $number
    );
    $sources[$index] = 'data:image/svg+xml;base64,' . base64_encode($svg);
    $items[] = [
        'index' => $index,
        'mediaID' => 1000 + $number,
        'title' => 'Kamera ' . $number,
    ];
}

$configuration = [
    'action' => 'bootstrap',
    'instanceID' => 4243,
    'configurationRevision' => 'browser-fixture-v2',
    'items' => $items,
    'settings' => [
        'autoLoop' => false,
        'loopSeconds' => 8,
        'loadTimeoutSeconds' => 5,
        'retryCount' => 1,
        'pauseAfterInteractionSeconds' => 10,
        'transitionMilliseconds' => 280,
        'fitMode' => 'contain',
        'showTitles' => true,
        'showDots' => true,
        'showArrows' => true,
    ],
    'initialMedia' => [
        'index' => 0,
        'source' => $sources[0],
        'contentRevision' => 'fixture-preview-0',
        'configurationRevision' => 'browser-fixture-v2',
        'requestID' => 'initial-preview',
        'preview' => true,
    ],
];

$fixtureScript = sprintf(
    '<script>window.fixtureRequests=[];window.requestAction=function(action,payload){if(action!=="LoadMedia"){return;}const request=JSON.parse(payload);window.fixtureRequests.push(request.index);window.setTimeout(function(){window.handleMessage(JSON.stringify({action:"media",configurationRevision:"browser-fixture-v2",requestID:request.requestID,index:request.index,source:%s[request.index],contentRevision:"fixture-"+request.index,preview:false}));},request.index===0?900:(request.index===4?650:60));};window.handleMessage(%s);</script>',
    json_encode($sources, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    json_encode(json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), JSON_THROW_ON_ERROR)
);

header('Content-Type: text/html; charset=UTF-8');
echo str_replace('</body>', $fixtureScript . '</body>', $html);
