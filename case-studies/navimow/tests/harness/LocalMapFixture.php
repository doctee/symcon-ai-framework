<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../../../helpers/diagnostics/ConfigurationHash.php';

/** @return array<string, mixed> */
function navimowLocalMapFixture(int $now): array
{
    $fixture = json_decode(
        (string) file_get_contents(
            __DIR__ . '/../../fixtures/map/local-map-scene-synthetic.json'
        ),
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($fixture)) {
        throw new RuntimeException('Synthetic local-map fixture is invalid.');
    }
    $points = $fixture['points'];
    foreach ($points as $index => &$point) {
        $point['receivedAt'] = $now - 60 + $index * 10;
        $point['sourceTimestamp'] = $point['receivedAt'] * 1000;
    }
    unset($point);
    $passes = $fixture['ledgerProjection']['passes'];
    foreach ($passes as $index => &$pass) {
        $pass['startedAt'] = $now - 65 + $index * 20;
        $pass['lastObservedAt'] = $pass['startedAt'] + 15;
        if ($pass['completionObservedAt'] !== null) {
            $pass['completionObservedAt'] = $pass['lastObservedAt'];
        }
    }
    unset($pass);
    $task = [
        'formatVersion' => 1,
        'authority' => 'mqtt-inference',
        'semanticUnit' => 'correlated-zone-pass',
        'status' => 'available',
        'retainedPassCount' => count($passes),
        'retainedTransitionCount' => 0,
        'passes' => $passes,
        'transitions' => [],
    ];
    $package = [
        'formatVersion' => 1,
        'geometry' => $fixture['geometry'],
        'bindings' => $fixture['bindings'],
        'frameCorrelationApproved' => true,
    ];

    return [
        'package' => $package,
        'geometryKey' => SAEF_CreateConfigurationHash(
            $fixture['geometry']
        ),
        'evidence' => [
            'formatVersion' => 1,
            'status' => 'ok',
            'authority' => [
                'state' => 'rest-authoritative',
                'path' => 'mqtt-inference',
                'task' => 'mqtt-inference',
            ],
            'observedAt' => $now,
            'position' => [
                'availability' => 'available',
                'latest' => $points[array_key_last($points)] + [
                    'ageSeconds' => 10,
                ],
                'track' => $points,
                'trackSummary' => [],
                'counters' => [],
            ],
            'task' => $task,
        ],
    ];
}
