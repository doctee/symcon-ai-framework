<?php

declare(strict_types=1);

require __DIR__ . '/warming.php';

use SAEF\StorageHeater\FanComparison;
use SAEF\StorageHeater\FanComparisonView;
use SAEF\StorageHeater\Forecast;

$now = $frozen['start'];
$sensorUpdates = $changedAt = [];
$stale = $gap = $badWeather = false;
foreach ($frozen['plan']['inputs']['bindings'] as $key => $id) {
    if (str_ends_with($key, 'Event')) {
        $events[$id] = $frozen['plan']['inputs']['events'][$key];
    } else {
        $values[$id] = $frozen['plan']['inputs']['values'][$key];
    }
}
$values[56] = 0;
$values[57] = $values[58] = $values[46] = false;
$events[51]['NextRun'] = $now + 5 * 3600;
$rawFixtures[14] = [['TimeStamp' => $now - 7200, 'Value' => .001], ['TimeStamp' => $now - 60, 'Value' => .001]];
$values[16] = 12.0;
$values[17] = 11.0;
$reference = $model;
$reference['intercept'] = 10.0;
$reference['coefficients'] = array_fill(0, 8, 0.0);
$candidate = $reference;
$candidate['features'][] = 'night_fan_hours';
$candidate['mean'][] = 0.0;
$candidate['scale'][] = 1.0;
$candidate['coefficients'][] = 1.5;
$candidate['ranges'][] = [0, 10];
$models = ['schema' => 1, 'reference' => $reference, 'candidate' => $candidate];
$m->properties['FanComparisonModelJSON'] = Forecast::encode($models);
$m->ApplyChanges();
$oldEnergy = $m->attributes['Journal'];
$m->RefreshPlan();
$fj = FanComparison::journal($m->GetFanComparisonJournalJson());
check(count($fj['records']) === 1, 'Separate pre-phase comparison saved: ' . GetValue($m->ids['FanComparisonStatus']));
$ff = $fj['records'][0]['forecast'];
$night = reset($ff['fanComparison']['nights']);
check($ff['beforePhase'] && $ff['issuedAt'] < $ff['phaseStart'], 'Candidate was actually frozen before warming');
check($night['features']['night_fan_hours'] > 0 && $night['features']['night_fan_hours'] <= 9, 'Runtime comes from prospective thermostat, bounded by night');
check(abs($night['predictions']['candidate']['kwh'] - 10 - 1.5 * $night['features']['night_fan_hours']) < 1e-9, 'Joint model evaluates future runtime, not a fixed permission surcharge');
check($night['predictions']['reference']['kwh'] == 10.0 && !$ff['fanComparison']['windowHeatLossEnabled'], 'Matched reference and no window heat-loss coefficient');
$m->RefreshPlan();
check(count(FanComparison::journal($m->GetFanComparisonJournalJson())['records']) === 1, 'Identical plan not duplicated');
$values[40] += 3600;
$m->RefreshPlan();
$fj = FanComparison::journal($m->GetFanComparisonJournalJson());
check(count($fj['records']) === 2 && $fj['records'][0]['forecast'] === $ff, 'Plan revision appends without overwriting frozen evidence');
$beforeRestart = $m->attributes['FanComparisonJournal'];
$m->attributes['FanComparisonJournal'] = '{"broken":true}';
$m->ApplyChanges();
check($m->status === IS_ACTIVE && $m->timers['Observe'] > 0, 'Corrupt comparison cannot disable existing forecast timer');
check($m->attributes['FanComparisonJournal'] === '{"broken":true}', 'Corrupt comparison evidence is not reset');
$m->attributes['FanComparisonJournal'] = $beforeRestart;
$m->Create();
$m->ApplyChanges();
check($m->attributes['FanComparisonJournal'] === $beforeRestart, 'Restart preserves comparison ledger');
$rawFixtures[18] = [['TimeStamp' => $now - 86400, 'Value' => false]];
$rawFixtures[19] = [['TimeStamp' => $now - 86400, 'Value' => true]];
$rawFixtures[20] = [['TimeStamp' => $now - 86400, 'Value' => false]];
$values[40] = 0;
$now = $night['cycle']['end'] + 3600;
$gap = true;
$m->RefreshPlan();
$missing = FanComparison::journal($m->GetFanComparisonJournalJson());
check(!isset($missing['records'][0]['nightActual'][$night['cycle']['date']]), 'Incomplete night stays missing, never measured as zero');
check(isset($missing['records'][0]['measurementErrors'][$night['cycle']['date']]), 'Incomplete measurement is visible and retryable');
$gap = false;
$m->RefreshPlan();
$fj = FanComparison::journal($m->GetFanComparisonJournalJson());
$actual = $fj['records'][0]['nightActual'][$night['cycle']['date']] ?? [];
check(($actual['status'] ?? '') === 'complete', 'Complete post-night energy measured: ' . json_encode($fj['records'][0]['measurementErrors']));
check($actual['from'] === $night['cycle']['start'] && $actual['until'] === $night['cycle']['end'], 'Exactly matching 22–07 measurement window');
check($actual['states']['fan']['hours'] == 0 && $actual['fanHoursError'] > 0, 'Actual duty scores forecast duty but never replaces it');
check($fj['records'][0]['forecast'] === $ff, 'Measurements and plan cancellation cannot modify the forecast');
check(isset($actual['diagnosticErrors']['candidate']['absoluteKwh']), 'Replaced forecast retains diagnostic errors');
check($actual['errors'] === [] && !$actual['evaluationEligible'], 'Replaced forecast excluded from regular errors');
check($m->attributes['Journal'] === $oldEnergy && $commands === 0, 'No existing energy journal writes or actuator calls');
$errors = FanComparison::errors($night, .01);
check($errors['candidate']['percent'] === null, 'Standby-only night does not produce misleading percent error');
$bad = $models;
$bad['candidate']['features'][8] = 'window_degree_hours';
$threw = false;
try {
    FanComparison::models($bad);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Window surcharge cannot be activated via candidate feature');
$threw = false;
try {
    FanComparison::build($ff, $models, $ff['phaseStart']);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'At or after phase start is rejected, not backdated');
$mixed = $ff;
$mixed['projection']['segments'][] = ['from' => $night['cycle']['start'], 'to' => $night['cycle']['end'], 'enabled' => false, 'reduced' => true, 'fanPermitted' => false];
$threw = false;
try {
    FanComparison::build($mixed, $models, $mixed['start']);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Mixed unsupported loading plan is excluded');
$future = $models;
$future['reference']['trainedThrough'] = $future['candidate']['trainedThrough'] = $ff['start'];
$threw = false;
try {
    FanComparison::build($ff, $future, $ff['start']);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Future-trained candidate cannot create historical predictions');
$partial = $ff;
$partial['end'] = $night['cycle']['end'] - 1;
$threw = false;
try {
    FanComparison::build($partial, $models, $partial['start']);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Partial night never scored against a full-night counter');
$bad = $fj;
$bad['records'][0]['forecast']['fanComparison']['nights'][$night['cycle']['date']]['predictions']['candidate']['kwh'] += 1;
$threw = false;
try {
    FanComparison::journal(Forecast::encode($bad));
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Tampered candidate hash rejected');
$html = FanComparisonView::render($fj, '<script>bad</script>');
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Comparison view escapes data');
check(str_contains($html, 'keine unabhängigen Testphasen'), 'No claim of independent evidence from revisions');
file_put_contents($argv[1] . '/fan-preview.html', $html);
$m->properties['FanComparisonModelJSON'] = '{}';
$m->RefreshPlan();
check(count(FanComparison::journal($m->GetFanComparisonJournalJson())['records']) === 2, 'Disable candidate keeps evidence');
echo $checks . " assertions including prospective fan comparison passed\n";
