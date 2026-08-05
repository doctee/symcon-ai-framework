<?php

declare(strict_types=1);

require_once __DIR__
    . '/../distribution/libs/Navimow/MqttPayloadException.php';
require_once __DIR__
    . '/../distribution/libs/Navimow/MqttPositionDiagnostic.php';

use Navimow\MqttPayloadException;
use Navimow\MqttPositionDiagnostic;

function assertPositionDiagnostic(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertPositionDiagnosticThrows(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (MqttPayloadException) {
        return;
    }

    throw new RuntimeException($message);
}

function positionPose(
    float $x,
    float $y,
    float $orientation,
    int $sourceTimestamp,
    int $stateCode
): array {
    return [
        'localX' => $x,
        'localY' => $y,
        'orientation' => $orientation,
        'sourceTimestamp' => $sourceTimestamp,
        'vehicleStateCode' => $stateCode,
    ];
}

$state = MqttPositionDiagnostic::initialState();
$state = MqttPositionDiagnostic::reduce(
    $state,
    positionPose(12.5, -8.25, 0.5, 1700000000000, 4),
    1700000000
);
assertPositionDiagnostic(
    $state['sampleSequence'] === 1
        && count($state['track']) === 1
        && $state['latest']['sampleSequence'] === 1,
    'First complete position sample was not retained.'
);

$state = MqttPositionDiagnostic::reduce(
    $state,
    positionPose(13.0, -8.0, 0.6, 1700000002000, 4),
    1700000002
);
assertPositionDiagnostic(
    $state['sampleSequence'] === 2
        && count($state['track']) === 1
        && $state['downsampledCount'] === 1
        && $state['latest']['localX'] === 13.0,
    'Five-second position downsampling is not deterministic.'
);

$state = MqttPositionDiagnostic::reduce(
    $state,
    positionPose(13.5, -7.5, 0.7, 1699999999000, 5),
    1700000003
);
assertPositionDiagnostic(
    $state['sampleSequence'] === 3
        && count($state['track']) === 2
        && $state['outOfOrderTimestampCount'] === 1
        && $state['track'][1]['vehicleStateCode'] === 5,
    'State-change retention or source-time regression accounting failed.'
);

$state = MqttPositionDiagnostic::reduce(
    $state,
    positionPose(14.0, -7.0, 0.8, 1699999999000, 5),
    1700000008
);
assertPositionDiagnostic(
    count($state['track']) === 3
        && $state['outOfOrderTimestampCount'] === 1,
    'Equal source timestamps were incorrectly classified as regressions.'
);

$projection = MqttPositionDiagnostic::project($state, 1700000010);
assertPositionDiagnostic(
    $projection['availability'] === 'available'
        && $projection['latest']['ageSeconds'] === 2
        && $projection['counters']['receivedSampleCount'] === 4
        && $projection['counters']['retainedSampleCount'] === 3
        && $projection['counters']['droppedSampleCount'] === 1
        && $projection['trackSummary']['firstReceivedAt'] === 1700000000
        && $projection['trackSummary']['lastReceivedAt'] === 1700000008
        && $projection['trackSummary']['coordinateChangeCount'] === 3
        && $projection['trackSummary']['pathLengthLocal'] > 1.9
        && $projection['trackSummary']['pathLengthLocal'] < 2.0
        && $projection['trackSummary']['maximumStepDistanceLocal'] > 0.7
        && $projection['trackSummary']['bounds'] === [
            'minimumX' => 12.5,
            'maximumX' => 14.0,
            'minimumY' => -8.25,
            'maximumY' => -7.0,
        ]
        && $projection['trackSummary']
            ['maximumPositiveSourceGapMilliseconds'] === 2000,
    'Position projection is incomplete or has incorrect age semantics.'
);

$bounded = MqttPositionDiagnostic::initialState();
for ($index = 0; $index < 515; $index++) {
    $bounded = MqttPositionDiagnostic::reduce(
        $bounded,
        positionPose(
            (float) $index,
            (float) -$index,
            0.0,
            1700000000000 + ($index * 5000),
            4
        ),
        1700000000 + ($index * 5)
    );
}
$serialized = MqttPositionDiagnostic::serializeState($bounded);
$restored = MqttPositionDiagnostic::restoreState($serialized);
assertPositionDiagnostic(
    count($bounded['track']) === 512
        && $bounded['evictedCount'] === 3
        && strlen($serialized) <= 131072
        && $restored === $bounded
        && MqttPositionDiagnostic::serializeState($restored) === $serialized,
    'Position track retention, serialization or restoration is not bounded.'
);

assertPositionDiagnosticThrows(
    static fn (): array => MqttPositionDiagnostic::reduce(
        MqttPositionDiagnostic::initialState(),
        positionPose(INF, 0.0, 0.0, 1700000000000, 4),
        1700000000
    ),
    'Non-finite local position was accepted.'
);
assertPositionDiagnosticThrows(
    static fn (): array => MqttPositionDiagnostic::reduce(
        MqttPositionDiagnostic::initialState(),
        positionPose(10000001.0, 0.0, 0.0, 1700000000000, 4),
        1700000000
    ),
    'Out-of-range local position was accepted.'
);
assertPositionDiagnosticThrows(
    static fn (): array => MqttPositionDiagnostic::reduce(
        MqttPositionDiagnostic::initialState(),
        positionPose(0.0, 0.0, 3.2, 1700000000000, 4),
        1700000000
    ),
    'Out-of-range orientation was accepted.'
);
assertPositionDiagnosticThrows(
    static fn (): array => MqttPositionDiagnostic::restoreState('{'),
    'Malformed persisted position state was accepted.'
);

$privacyJson = json_encode($projection, JSON_THROW_ON_ERROR);
assertPositionDiagnostic(
    !str_contains($privacyJson, 'DEVICE_')
        && !str_contains($privacyJson, '/downlink/')
        && !str_contains($privacyJson, 'Payload'),
    'Position projection contains an identity, topic or raw-payload marker.'
);

echo "Navimow MQTT position diagnostic checks passed.\n";
