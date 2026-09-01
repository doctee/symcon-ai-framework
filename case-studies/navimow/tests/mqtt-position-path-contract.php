<?php

declare(strict_types=1);

require_once __DIR__
    . '/../distribution/libs/Navimow/MqttPayloadException.php';
require_once __DIR__
    . '/../distribution/libs/Navimow/MqttPositionDiagnostic.php';
require_once __DIR__
    . '/../distribution/libs/Navimow/MqttPathSegmenter.php';

use Navimow\MqttPathSegmenter;
use Navimow\MqttPositionDiagnostic;

function assertPositionPathContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function positionPathPose(float $x, int $timestamp): array
{
    return [
        'localX' => $x,
        'localY' => 1.0,
        'orientation' => 0.0,
        'sourceTimestamp' => $timestamp * 1000,
        'vehicleStateCode' => 1,
    ];
}

$state = MqttPositionDiagnostic::initialState();
$state = MqttPositionDiagnostic::reduce(
    $state,
    positionPathPose(1.0, 100),
    100,
    7
);
$state = MqttPositionDiagnostic::reduce(
    $state,
    positionPathPose(2.0, 105),
    105,
    7
);
$state = MqttPositionDiagnostic::reduce(
    $state,
    positionPathPose(3.0, 110),
    110,
    8
);
$projection = MqttPositionDiagnostic::project($state, 111);
$path = MqttPathSegmenter::build($projection['track'], []);

assertPositionPathContract(
    count($path['segments']) === 2
        && $path['segments'][0]['breakReason'] === 'first-point'
        && $path['segments'][1]['breakReason']
            === 'transport-session-change'
        && $path['segments'][0]['sessionSequence'] === 7
        && $path['segments'][1]['sessionSequence'] === 8,
    'Position projection did not preserve transport-session boundaries.'
);

$legacy = $state;
unset($legacy['latest']['sessionSequence']);
foreach ($legacy['track'] as &$point) {
    unset($point['sessionSequence']);
}
unset($point);
$legacy = MqttPositionDiagnostic::restoreState(
    MqttPositionDiagnostic::serializeState($legacy)
);
$legacyPath = MqttPathSegmenter::build(
    MqttPositionDiagnostic::project($legacy, 111)['track'],
    []
);

assertPositionPathContract(
    count($legacyPath['segments']) === 1
        && $legacyPath['segments'][0]['sessionSequence'] === 0,
    'Legacy position samples were not mapped to the bounded fallback session.'
);

echo "Navimow MQTT position-to-path contract checks passed.\n";
