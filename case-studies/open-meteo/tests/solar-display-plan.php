<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../candidate/SolarDisplayPlan.php';

use SAEF\CaseStudy\OpenMeteo\IntervalAligner;

/** @return array<string, mixed> */
function displaySource(string $key, int $id, string $date, float $energy): array
{
    $bounds = IntervalAligner::localDayBounds($date, 'Europe/Berlin');
    $hours = ($bounds['to'] - $bounds['from']) / 3600;
    $points = [];
    for ($time = $bounds['from']; $time < $bounds['to']; $time += 3600) {
        $points[] = [
            'sourceTimestamp' => $time + 3600, 'validFrom' => $time, 'validTo' => $time + 3600,
            'value' => $energy / $hours, 'unit' => 'kW', 'semantics' => 'preceding_interval',
        ];
    }
    return [
        'key' => $key, 'instanceId' => $id, 'status' => 102, 'dataState' => 2,
        'lastSuccess' => $bounds['from'] + 600, 'readbackLastSuccess' => $bounds['from'] + 600,
        'configurationHash' => str_repeat('a', 64), 'readbackConfigurationHash' => str_repeat('a', 64),
        'expectedConfigurationHash' => str_repeat('a', 64), 'todayValue' => $energy,
        'power' => ['success' => true, 'breakdown' => 'system', 'data' => ['system' => $points]],
        'daily' => ['success' => true, 'breakdown' => 'system', 'data' => ['system' => [[
            'sourceTimestamp' => $bounds['from'], 'validFrom' => $bounds['from'], 'validTo' => $bounds['to'],
            'value' => $energy, 'unit' => 'kWh', 'semantics' => 'local_day',
        ]]]],
    ];
}

/** @param list<array<string, mixed>> $sources */
function displayRejected(array $sources, int $now, string $message): void
{
    $result = SolarDisplayPlan::evaluate($sources, $now, 'Europe/Berlin', 10800);
    same(false, $result['success'], $message);
    check(!array_key_exists('sumKwh', $result) && !array_key_exists('chartProjection', $result), 'Invalid result must contain no publishable data.');
}

foreach (['2026-03-29' => 23, '2026-10-25' => 25, '2026-12-31' => 24, '2027-01-01' => 24] as $date => $hours) {
    $a = displaySource('plant_a', 11, $date, 3.2);
    $b = displaySource('plant_b', 12, $date, 1.8);
    $now = $a['lastSuccess'] + 100;
    $result = SolarDisplayPlan::evaluate([$a, $b], $now, 'Europe/Berlin', 10800);
    same(true, $result['success'], 'Complete local day accepted.');
    near(5.0, $result['sumKwh'], 0.000001, 'Sum differs.');
    same($hours * 3600, $result['validTo'] - $result['validFrom'], 'DST day length differs.');
    same(false, $result['chartProjection']['applyAllowed'], 'Plan must never authorize writes.');
}

$a = displaySource('plant_a', 11, '2026-10-03', 3.0);
$b = displaySource('plant_b', 12, '2026-10-03', 2.0);
$now = $a['lastSuccess'] + 100;
foreach (
    [
    'status' => 200, 'dataState' => 3, 'lastSuccess' => $now + 1,
    'readbackLastSuccess' => $now, 'todayValue' => 0.0,
    'configurationHash' => 'invalid', 'expectedConfigurationHash' => str_repeat('b', 64),
    'readbackConfigurationHash' => str_repeat('b', 64),
    'instanceId' => 0, 'key' => 'plant_b',
    ] as $field => $value
) {
    $bad = $a;
    $bad[$field] = $value;
    displayRejected([$bad, $b], $now, 'Reject ' . $field);
}
displayRejected([$a, $a], $now, 'Do not double count a source.');
$duplicate = $b;
$duplicate['instanceId'] = $a['instanceId'];
displayRejected([$a, $duplicate], $now, 'Reject duplicate instance under a different key.');
displayRejected([$a, $b], $now + 10801, 'Reject stale data.');
displayRejected([$a, $b], IntervalAligner::localDayBounds('2026-10-04', 'Europe/Berlin')['from'], 'Reject yesterday.');
same(true, SolarDisplayPlan::evaluate([$a, $b], $a['lastSuccess'] + 10800, 'Europe/Berlin', 10800)['success'], 'Freshness boundary.');

$bad = $a;
array_pop($bad['power']['data']['system']);
displayRejected([$bad, $b], $now, 'Reject incomplete last hour.');
$bad = $a;
array_shift($bad['power']['data']['system']);
displayRejected([$bad, $b], $now, 'Reject incomplete first hour.');
$bad = $a;
array_splice($bad['power']['data']['system'], 5, 1);
displayRejected([$bad, $b], $now, 'Reject internal gap.');
$bad = $a;
$bad['power']['data']['system'][1] = $bad['power']['data']['system'][0];
displayRejected([$bad, $b], $now, 'Reject duplicate/overlapping power interval.');
$bad = $a;
$bad['power']['data']['system'] = array_reverse($bad['power']['data']['system']);
displayRejected([$bad, $b], $now, 'Reject reverse order.');
foreach (['value' => -1, 'unit' => 'W', 'semantics' => 'instant', 'sourceTimestamp' => 1] as $field => $value) {
    $bad = $a;
    $bad['power']['data']['system'][0][$field] = $value;
    displayRejected([$bad, $b], $now, 'Reject power ' . $field);
}
foreach ([NAN, INF, '3', null] as $value) {
    $bad = $a;
    $bad['todayValue'] = $value;
    displayRejected([$bad, $b], $now, 'Reject invalid numeric value.');
}
$bad = $a;
$bad['daily']['data']['system'][] = $bad['daily']['data']['system'][0];
displayRejected([$bad, $b], $now, 'Reject duplicate day.');
$bad = $a;
$bad['daily']['data']['system'][0]['validTo']--;
displayRejected([$bad, $b], $now, 'Reject daily boundary drift.');
$bad = $a;
$bad['daily']['data']['system'][0]['value']++;
displayRejected([$bad, $b], $now, 'Reject daily/power disagreement.');
$bad = $a;
$bad['power']['success'] = false;
displayRejected([$bad, $b], $now, 'Reject API failure.');
$bad = $a;
$bad['power']['breakdown'] = 'baseline';
displayRejected([$bad, $b], $now, 'No baseline substitution.');

$zeroA = displaySource('plant_a', 11, '2026-10-03', 0.0);
$zeroB = displaySource('plant_b', 12, '2026-10-03', 0.0);
same(0.0, SolarDisplayPlan::evaluate([$zeroA, $zeroB], $now, 'Europe/Berlin', 10800)['sumKwh'], 'Genuine zero is valid.');

// Each revision REPLACES the desired day projection. Never append these pairs.
foreach ([3.0, 2.0, 2.5, 0.0, 4.0] as $forecast) {
    $source = displaySource('plant_a', 11, '2026-10-03', $forecast);
    $result = SolarDisplayPlan::evaluate([$source, $b], $now, 'Europe/Berlin', 10800);
    $points = $result['chartProjection']['records']['plant_a'];
    same(2, count($points), 'Projection must not accumulate revisions.');
    near($forecast, $points[1]['Value'] - $points[0]['Value'], 0.000001, 'Day delta must equal latest forecast.');
    same($result['validFrom'], $points[0]['TimeStamp'], 'Reset belongs to local day.');
    same($result['validFrom'] + 1, $points[1]['TimeStamp'], 'Distinct reset and forecast timestamps.');
    same($result, SolarDisplayPlan::evaluate([$source, $b], $now, 'Europe/Berlin', 10800), 'Deterministic retry.');
}
throws(fn () => SolarDisplayPlan::evaluate([$a], $now, 'Europe/Berlin', 10800), InvalidArgumentException::class, 'Require both plants.');
throws(fn () => SolarDisplayPlan::evaluate([$a, $b], $now, 'Europe/Berlin', 0), InvalidArgumentException::class, 'Require freshness limit.');
echo "solar display plan: ok\n";
