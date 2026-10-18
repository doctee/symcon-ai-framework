<?php

declare(strict_types=1);

require __DIR__ . '/fan.php';

use SAEF\StorageHeater\FanComparison;
use SAEF\StorageHeater\FanComparisonView;
use SAEF\StorageHeater\Forecast;

// Analytic regression: normal=20 kWh, reduced=4 kWh, plus 1.5 kWh per fan hour.
$weightedModels = $models;
foreach (['reference', 'candidate'] as $name) {
    $weightedModels[$name]['intercept'] = 20.0;
    $weightedModels[$name]['mean'] = array_fill(0, count($weightedModels[$name]['features']), 0.0);
    $weightedModels[$name]['scale'] = array_fill(0, count($weightedModels[$name]['features']), 1.0);
    $weightedModels[$name]['coefficients'][1] = -16.0;
}
$fixture = static function (string $date, string $switch) use ($ff): array {
    $f = $ff;
    $c = Forecast::cycle($date, 'Europe/Berlin');
    $f['start'] = $f['issuedAt'] = $c['start'] - 7200;
    $f['end'] = $c['end'];
    $f['phaseStart'] = strtotime($switch . ' Europe/Berlin');
    $f['chargeNights'] = [$date => reset($ff['chargeNights'])];
    $f['projection']['segments'] = [
        ['from' => $f['start'], 'to' => $f['phaseStart'], 'enabled' => true, 'reduced' => true],
        ['from' => $f['phaseStart'], 'to' => $f['end'] + 3600, 'enabled' => true, 'reduced' => false],
    ];
    $f['weather']['points'] = $f['points'] = [];
    for ($t = $f['start']; $t <= $f['end']; $t += 3600) {
        $f['weather']['points'][] = ['time' => $t, 'temperature' => 5.0];
        $f['points'][] = ['time' => $t, 'fanHours' => ($t - $f['start']) / 7200];
    }
    return $f;
};
foreach (
    [
    ['2026-02-05', '2026-02-05 22:00', 0.0, 8],
    ['2026-02-05', '2026-02-05 22:30', 0.0, 8],
    ['2026-02-05', '2026-02-05 23:00', 0.0, 8],
    ['2026-02-05', '2026-02-05 23:45', .75 / 8, 8],
    ['2026-02-05', '2026-02-06 03:00', .5, 8],
    ['2026-02-05', '2026-02-06 06:59:59', (8 * 3600 - 1) / (8 * 3600), 8],
    ['2026-03-28', '2026-03-29 03:00', 3 / 7, 7],
    ['2026-10-24', '2026-10-25 03:00', 5 / 9, 9],
    ] as [$date, $switch, $weight, $chargeHours]
) {
    $f = $fixture($date, $switch);
    $built = FanComparison::build($f, $weightedModels, $f['issuedAt']);
    $n = $built['nights'][$date];
    check(abs($n['features']['reduced'] - $weight) < 1e-10, 'Charging exposure weights switch ' . $switch);
    check($n['chargePlan']['chargeHours'] == $chargeHours, 'Elapsed physical charging duration respects DST');
    check(abs($n['predictions']['reference']['kwh'] - (20 - 16 * $weight)) < 1e-9, 'Analytic weighted night estimate');
    check(abs($n['predictions']['candidate']['kwh'] - $n['predictions']['reference']['kwh'] - 1.5 * $n['features']['night_fan_hours']) < 1e-9, 'Fan runtime counted once across both reduction states');
}
$f = $fixture('2026-02-05', '2026-02-05 23:45');
$built = FanComparison::build($f, $weightedModels, $f['issuedAt']);
check($built['nights']['2026-02-05']['chargePlan']['reducedHours'] == 1.75, '22-07 measurement schedule differs correctly from 23-07 energy weighting');
check(in_array('mixed_reduction_time_weighted_unvalidated', $built['nights']['2026-02-05']['predictions']['candidate']['warnings'], true), 'Mixed night openly experimental');
foreach (['gap', 'overlap', 'disabled'] as $invalid) {
    $badPlan = $f;
    if ($invalid === 'disabled') {
        $badPlan['projection']['segments'][0]['enabled'] = false;
    } else {
        $badPlan['projection']['segments'][1]['from'] += $invalid === 'gap' ? 60 : -60;
    }
    $threw = false;
    try {
        FanComparison::build($badPlan, $models, $badPlan['issuedAt']);
    } catch (RuntimeException $e) {
        $threw = true;
    }
    check($threw, 'Invalid or unsupported plan fails closed: ' . $invalid);
}

// Full observer: first night start at 23:45, then measure the same planned transition.
$now = $ff['start'];
foreach ($ff['plan']['inputs']['bindings'] as $key => $id) {
    if (str_ends_with($key, 'Event')) {
        $events[$id] = $ff['plan']['inputs']['events'][$key];
    } else {
        $values[$id] = $ff['plan']['inputs']['values'][$key];
    }
}
$values[56] = 0;
$values[57] = $values[58] = $values[46] = false;
$events[50]['NextRun'] = $switchAt = strtotime('2026-02-05 23:45 Europe/Berlin');
$events[51]['NextRun'] = strtotime('2026-02-06 04:00 Europe/Berlin');
foreach ([18 => false, 19 => true, 20 => true, 21 => false] as $id => $v) {
    $rawFixtures[$id] = [['TimeStamp' => $now - 9 * 86400, 'Value' => $v]];
}
$rawFixtures[14] = [['TimeStamp' => $now - 7200, 'Value' => .001], ['TimeStamp' => $now - 60, 'Value' => .001]];
$m->properties['FanComparisonModelJSON'] = Forecast::encode($weightedModels);
$m->attributes['FanComparisonJournal'] = '{"schema":1,"discarded":0,"records":[]}';
$oldControlPlan = $events;
$oldJournal = $m->attributes['Journal'];
$m->RefreshPlan();
$mj = FanComparison::journal($m->GetFanComparisonJournalJson());
check(count($mj['records']) === 1, 'First mixed warming night issued: ' . GetValue($m->ids['FanComparisonStatus']));
$mf = $mj['records'][0]['forecast'];
$mn = $mf['fanComparison']['nights']['2026-02-05'];
check($mn['chargePlan']['mixed'] && $mf['issuedAt'] < $switchAt, 'Frozen before first stage, including first mixed night');
$values[40] = 0;
$now = $mn['cycle']['end'] + 3600;
$rawFixtures[20][] = ['TimeStamp' => $switchAt, 'Value' => false];
$m->RefreshPlan();
$mj = FanComparison::journal($m->GetFanComparisonJournalJson());
$ma = $mj['records'][0]['nightActual']['2026-02-05'];
check($ma['reductionPlan']['mismatchSeconds'] == 0, 'Scheduled reduction transition measured without false deviation');
check(!in_array('actual_charge_plan_deviated', $ma['warnings'], true), 'Planned first-night switch is not an error');
check($mj['records'][0]['forecast'] === $mf, 'Settlement preserves immutable mixed forecast');
check($oldControlPlan === $events && $oldJournal === $m->attributes['Journal'] && $commands === 0, 'Controller schedule, original forecasts and device commands unchanged');
$html = FanComparisonView::render($mj, 'Bereit');
check(str_contains($html, '23:45') && str_contains($html, 'zeitgewichtete Näherung'), 'View shows transition and approximation');
file_put_contents($argv[1] . '/mixed-preview.html', $html);

// Equal total duration at a different time must still reveal a plan deviation.
$mj['records'][0]['nightActual'] = [];
$mj['records'][0]['settledAt'] = null;
$m->attributes['FanComparisonJournal'] = Forecast::encode($mj);
$rawFixtures[20] = [
    ['TimeStamp' => $mn['cycle']['start'] - 86400, 'Value' => false],
    ['TimeStamp' => $switchAt, 'Value' => true],
    ['TimeStamp' => $switchAt + 6300, 'Value' => false],
];
$m->RefreshPlan();
$shifted = FanComparison::journal($m->GetFanComparisonJournalJson())['records'][0]['nightActual']['2026-02-05'];
check($shifted['states']['reduced']['hours'] == 1.75 && $shifted['reductionPlan']['mismatchSeconds'] == 12600, 'Same total at wrong times is detected');
check(in_array('actual_charge_plan_deviated', $shifted['warnings'], true), 'Shifted switching flagged separately from forecast error');
// A previously stored 0.8.0 constant-night record is read and settled without migration.
$legacy = $fj['records'][0];
foreach ($legacy['forecast']['fanComparison']['nights'] as &$ln) {
    unset($ln['chargePlan']);
}
unset($ln);
$legacy['forecast']['fanComparison']['method'] = 'one_pass_no_feedback';
$legacy['forecastHash'] = $legacy['key'] = SAEF_CreateConfigurationHash($legacy['forecast']);
$legacy['nightActual'] = [];
$legacy['settledAt'] = null;
$legacyFrozen = $legacy['forecast'];
$m->attributes['FanComparisonJournal'] = Forecast::encode(['schema' => 1, 'discarded' => 0, 'records' => [$legacy]]);
$rawFixtures[20] = [['TimeStamp' => $now - 86400, 'Value' => false]];
$m->RefreshPlan();
$legacyAfter = FanComparison::journal($m->GetFanComparisonJournalJson())['records'][0];
check($legacyAfter['forecast'] === $legacyFrozen, 'Legacy 0.8.0 forecast remains byte-equivalent in decoded content');
check($legacyAfter['nightActual']['2026-02-05']['reductionPlan']['mismatchSeconds'] == 0, 'Legacy constant reduction expectation still scores correctly');
echo $checks . " assertions including mixed reduction nights passed\n";
