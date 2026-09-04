<?php

declare(strict_types=1);

if (!defined('STDOUT')) {
    define('STDOUT', fopen('php://output', 'wb'));
}

ob_start();
require __DIR__ . '/runtime-module.php';
ob_end_clean();

$module->testSetProperty(
    'ProviderConfiguration',
    '{"basemap":{"mode":"none"},"routing":{"mode":"none",'
    . '"allowGeodesicFallback":true}}'
);
$module->ApplyChanges();
$module->RequestAction(
    'SelectTrack',
    json_encode(
        [
            'requestGeneration' => 1,
            'clientSessionKey' => 'browser-fixture-client-0001',
            'sourceKey' => 'synthetic-a',
            'selectedDate' => '2024-01-01',
        ],
        JSON_THROW_ON_ERROR
    )
);
$trackResult = $module->testLastUpdate();
$trackResultJson = json_encode(
    $trackResult,
    JSON_THROW_ON_ERROR
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
$shim = '<script>window.requestAction=function(ident,requestJson){'
    . 'const request=JSON.parse(requestJson);const response=' . $trackResultJson . ';'
    . 'response.requestGeneration=request.requestGeneration;'
    . 'window.handleOwnTracksOpenLayersMessage(response);};</script>';
$tile = $module->GetVisualizationTile();
$fixture = preg_replace('/<script>/', $shim . '<script>', $tile, 1);
if (!is_string($fixture)) {
    throw new RuntimeException('Runtime browser fixture could not be created.');
}

header('Content-Type: text/html; charset=utf-8');
echo $fixture;
