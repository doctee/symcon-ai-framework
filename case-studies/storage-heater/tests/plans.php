<?php

declare(strict_types=1);

require __DIR__ . '/cooling.php';

use SAEF\StorageHeater\Forecast;
use SAEF\StorageHeater\HeatingPlan;
use SAEF\StorageHeater\PlanView;

$now = strtotime('2026-02-01 20:00 Europe/Berlin');
$stale = $gap = $badWeather = false;
$sensorUpdates = [];
$rawFixtures = [];
$b = ['archive' => 11, 'weather' => 12, 'energy' => 13, 'power' => 14, 'outside' => 15,
    'gallery' => 16, 'sofa' => 17, 'fan' => 18, 'enabled' => 19, 'reduced' => 20, 'presence' => 21];
$pb = ['arrival' => 40, 'storageRequested' => 41, 'fanRequested' => 42, 'storageEvent' => 50,
    'fanEvent' => 51, 'resetEvent' => 52, 'regulatorEnabled' => 43, 'regulatorActive' => 44,
    'setpoint' => 45, 'setback' => 46, 'winter' => 47];
foreach (
    [18 => false, 19 => true, 20 => true, 21 => false, 40 => $now + 86400, 41 => false,
    42 => false, 43 => false, 44 => true, 45 => 20.5, 46 => false, 47 => true] as $id => $value
) {
    $types[$id] = is_bool($value) ? 0 : (is_int($value) ? 1 : 2);
    $values[$id] = $value;
}
$event = ['EventType' => EVENTTYPE_CYCLIC, 'CyclicDateType' => EVENTCYCLICDATETYPE_ONCE,
    'CyclicTimeType' => EVENTCYCLICTIMETYPE_ONCE, 'EventActive' => true, 'NextRun' => $now + 3600,
    'LastRun' => 0, 'CyclicDateFrom' => ['Year' => 2026, 'Month' => 2, 'Day' => 1],
    'CyclicTimeFrom' => ['Hour' => 21, 'Minute' => 0, 'Second' => 0]];
$events = [50 => $event, 51 => array_replace($event, ['NextRun' => $now + 4 * 3600]),
    52 => array_replace($event, ['NextRun' => $now + 36 * 3600])];
$m = new TestModule();
$m->Create();
$m->properties['Enabled'] = true;
$m->properties['BindingsJSON'] = Forecast::encode($b);
$m->properties['ModelJSON'] = Forecast::encode($model);
$m->properties['HistoryStart'] = '2025-01-01';
$m->properties['PlanBindingsJSON'] = Forecast::encode($pb);
$m->ApplyChanges();
check($m->status === IS_ACTIVE && count($m->messages) === 14, 'Plan inputs and events subscribed');
$m->RefreshPlan();
$j = HeatingPlan::journal($m->GetPlanJournalJson());
check(count($j['plans']) === 1 && count($j['revisions']) === 1, 'First settled snapshot and revision saved');
$r = end($j['revisions']);
check($r['plan']['observedAt'] === $now && $r['issuedAt'] === $now, 'Observation is not backdated to variable change');
check($r['projection']['storageStart'] === $events[50]['NextRun'], 'Uses actual configured timer, no duplicated controller algorithm');
check($r['projection']['fanRuntimeHours']['max'] == 7.0, 'Fan permission starts at midnight, seven elapsed hours');
check($r['estimate']['status'] === 'waiting_for_issue_window', 'No early fabricated energy forecast');
$frozenPlan = $j['plans'][0];
$frozenRevision = $j['revisions'][0];
$m->RefreshPlan();
check($m->GetPlanJournalJson() === Forecast::encode($j), 'Unchanged repeated observation is idempotent');
$m->MessageSink(123, 40, VM_UPDATE, []);
check($m->timers['PlanRefresh'] === 5000, 'Input message schedules debounced refresh');
$m->RefreshPlan();
check($m->timers['PlanRefresh'] === 0, 'Debounce timer is stopped after execution');
$m->MessageSink(124, 999, VM_UPDATE, []);
check($m->timers['PlanRefresh'] === 0, 'Unknown sender ignored');
$events[51]['NextRun'] = $now + 3 * 3600;
$m->MessageSink(125, 51, EM_CHANGECYCLICTIMEFROM, []);
$m->RefreshPlan();
$j = HeatingPlan::journal($m->GetPlanJournalJson());
check(count($j['plans']) === 2 && end($j['revisions'])['projection']['fanRuntimeHours']['max'] == 8.0, 'Timer edit creates a new projection');
check($j['plans'][0] === $frozenPlan && $j['revisions'][0] === $frozenRevision, 'Earlier snapshots remain immutable');
$now = strtotime('2026-02-01 21:46 Europe/Berlin');
$values[41] = true;
$values[20] = false;
$events[50]['NextRun'] = 0;
$m->Tick();
$j = HeatingPlan::journal($m->GetPlanJournalJson());
$r = end($j['revisions']);
check($r['estimate']['status'] === 'fan_effect_not_calibrated' && $r['estimate']['kwh'] === null, 'Future fan use invalidates a falsely precise kWh total');
check($r['baseline']['issuedAt'] < $r['cycle']['start'], 'Baseline was actually saved before charge');
$original = $m->attributes['Journal'];
$firstReady = $r;
$events[51]['EventActive'] = false;
$m->RefreshPlan();
$j = HeatingPlan::journal($m->GetPlanJournalJson());
$r = end($j['revisions']);
check($r['projection']['fanRuntimeHours']['max'] == 0.0, 'Disabling fan timer removes planned permission');
check($r['estimate']['status'] === 'planned_state_baseline' && $r['estimate']['kwh'] == 20.0, 'Existing regression supports unchanged no-fan state scenario');
check($m->attributes['Journal'] === $original, 'Updating plan never overwrites original energy journal');
$events[51]['EventActive'] = true;
$events[51]['NextRun'] = 0;
$m->RefreshPlan();
$r = end(HeatingPlan::journal($m->GetPlanJournalJson())['revisions']);
check($r['projection']['fanStart'] === null, 'Expired active timer is not a future start');
$values[40] = 0;
$values[41] = $values[42] = false;
$values[21] = true;
$values[43] = true;
$m->RefreshPlan();
$r = end(HeatingPlan::journal($m->GetPlanJournalJson())['revisions']);
check($r['projection']['fanRuntimeHours']['max'] == 9.0 && $r['projection']['resetAt'] === null, 'Arrival clears selection but actual regulator permission continues');
check($r['estimate']['status'] === 'fan_effect_not_calibrated', 'Arrival preserves explicit fan limitation');
$values[45] = 19.0;
$m->RefreshPlan();
$r = end(HeatingPlan::journal($m->GetPlanJournalJson())['revisions']);
check($r['projection']['setpoint'] == 19.0, 'Thermostat target edits create revisions');
$now = strtotime('2026-02-02 00:15 Europe/Berlin');
foreach ([18 => true, 19 => true, 20 => false] as $id => $state) {
    $rawFixtures[$id] = [['TimeStamp' => $firstReady['cycle']['start'] - 60, 'Value' => $state]];
}
$m->RefreshPlan();
$r = end(HeatingPlan::journal($m->GetPlanJournalJson())['revisions']);
check($r['phase'] === 'during_charge' && $r['cycle']['date'] === '2026-02-01', 'After midnight still revises the same night');
check($r['baseline']['issuedAt'] < $r['cycle']['start'] && $r['issuedAt'] === $now, 'Late revision preserves original information time');
check($r['past']['kwh'] == 2.0 && $r['past']['until'] === strtotime('2026-02-01 23:00 Europe/Berlin'), 'Only settled measured hours are labeled actual energy');
check($r['estimate']['remainingKwh'] === null, 'No unsupported residual-energy forecast fabricated');
// Planned reduction changes the quantitative baseline without relabeling fan history.
$baseline = $firstReady['baseline'];
$baseline['model']['coefficients'][1] = -5.0;
$projection = $firstReady['projection'];
$projection['fanRuntimeHours']['max'] = 0.0;
$projection['segments'] = [['enabled' => true, 'reduced' => true]];
$reducedEstimate = HeatingPlan::estimate($baseline, $projection, [], $now);
$projection['segments'][0]['reduced'] = false;
$normalEstimate = HeatingPlan::estimate($baseline, $projection, [], $now);
check($normalEstimate['kwh'] - $reducedEstimate['kwh'] === 5.0, 'Planned reduction changes numerical regression input');
check($normalEstimate['features']['fan_hours'] === $baseline['features']['fan_hours'], 'Past fan feature is never replaced with future planned hours');
$projection['segments'][] = ['enabled' => true, 'reduced' => true];
check(HeatingPlan::estimate($baseline, $projection, [], $now)['status'] === 'mixed_night_not_calibrated', 'Mixed reduction window is explicitly unsupported');
$projection['warnings'][] = 'reset_conditional_on_no_arrival';
check(HeatingPlan::estimate($baseline, $projection, [], $now)['status'] === 'conditional_arrival', 'Conditional reset is not silently treated as certain');
$summer = $firstReady['plan'];
$summer['inputs']['values']['winter'] = false;
$summer['inputs']['values']['regulatorEnabled'] = false;
$summerProjection = HeatingPlan::project($summer, $firstReady['cycle'], $firstReady['issuedAt']);
check($summerProjection['fanStart'] === null && $summerProjection['storageStart'] === null, 'Winter guard blocks pending preheat starts');
$conditional = $firstReady['plan'];
$conditional['inputs']['values']['arrival'] = $firstReady['cycle']['start'] - 3600;
$conditional['inputs']['values']['presence'] = false;
$conditional['inputs']['values']['regulatorEnabled'] = true;
$conditional['inputs']['events']['resetEvent']['NextRun'] = $firstReady['cycle']['start'] + 3600;
$p = HeatingPlan::project($conditional, $firstReady['cycle'], $firstReady['issuedAt']);
check($p['fanRuntimeHours']['max'] == 9.0 && in_array('arrival_not_observed', $p['warnings'], true), 'Fan upper bound includes arrival branch beyond an absence-only reset');
$beforeRestart = $m->attributes['PlanJournal'];
$messages = $m->messages;
$m->Create();
$m->ApplyChanges();
check($m->attributes['PlanJournal'] === $beforeRestart && $m->messages === $messages, 'Lifecycle preserves journal and idempotent subscriptions');
$m->RefreshPlan();
check($m->attributes['PlanJournal'] === $beforeRestart, 'Restart does not invent a revision');
$values[45] = 18.0;
$gap = true;
$m->RefreshPlan();
$r = end(HeatingPlan::journal($m->GetPlanJournalJson())['revisions']);
check($r['estimate']['status'] === 'data_error' && $r['plan']['inputs']['values']['setpoint'] == 18.0, 'Plan persists despite archive failure');
$gap = false;
$now += 301;
$m->RefreshPlan();
$r = end(HeatingPlan::journal($m->GetPlanJournalJson())['revisions']);
check($r['estimate']['status'] === 'fan_effect_not_calibrated', 'Data failure retries without editing failed revision');
$beforeCorruption = $m->attributes['PlanJournal'];
$m->attributes['PlanJournal'] = '{"schema":1,"plans":"broken"}';
$m->RefreshPlan();
check($m->attributes['PlanJournal'] === '{"schema":1,"plans":"broken"}', 'Corrupt ledger is never silently reset');
$m->attributes['PlanJournal'] = $beforeCorruption;
$oldRefs = $m->messages;
$m->properties['PlanBindingsJSON'] = '{}';
$m->ApplyChanges();
check($m->messages === [] && $m->attributes['PlanJournal'] === $beforeCorruption, 'Removing bindings unsubscribes and retains evidence');
$m->properties['PlanBindingsJSON'] = Forecast::encode(array_replace($pb, ['arrival' => 0]));
$m->ApplyChanges();
check(str_contains(GetValue($m->ids['PlanStatus']), 'Invalid external') && $m->messages === [], 'Protected root rejected before subscribing');
$m->properties['PlanBindingsJSON'] = Forecast::encode($pb);
$m->ApplyChanges();
$m->properties['Enabled'] = false;
$m->ApplyChanges();
$reads = $archiveReads;
$m->RefreshPlan();
check($m->messages === [] && $archiveReads === $reads && $m->timers['PlanRefresh'] === 0, 'Disable removes subscriptions and prevents calculation');

foreach (['2026-03-28 21:46 Europe/Berlin' => 8.0, '2026-10-24 21:46 Europe/Berlin' => 10.0] as $time => $hours) {
    $t = strtotime($time);
    $p = $r['plan'];
    $p['inputs']['values']['regulatorEnabled'] = true;
    $p['inputs']['values']['presence'] = true;
    $projection = HeatingPlan::project($p, HeatingPlan::cycle($t), $t);
    check($projection['fanRuntimeHours']['max'] == $hours, 'DST uses real elapsed fan permission hours');
}
$j = HeatingPlan::journal($beforeCorruption);
$j['plans'] = array_fill(0, 260, $frozenPlan);
$j['revisions'] = array_fill(0, 260, $firstReady);
$j = HeatingPlan::retain($j);
check(count($j['plans']) === 256 && count($j['revisions']) === 256 && $j['discarded'] === 8, 'Bounded retention counts removed evidence');
$html = PlanView::render($j, '<script>bad</script>');
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Plan view escapes untrusted text');
check(str_contains($html, 'keine Dauerlaufzeit') && str_contains($html, 'Kalibrierung'), 'View explains runtime and model limits');
check($commands === 0 && $m->attributes['Journal'] === $original, 'All revisions are observational; original forecasts unchanged');
echo $checks . " total assertions including preselection passed; no external writes or device actions\n";
