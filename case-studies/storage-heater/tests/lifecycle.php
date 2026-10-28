<?php

declare(strict_types=1);

require __DIR__ . '/profile.php';

use SAEF\StorageHeater\ComparisonLifecycle;
use SAEF\StorageHeater\Forecast;
use SAEF\StorageHeater\Warming;

// Pure, bounded plan replay, independent of thermal/weather predictions.
$base = ['observedAt' => 100, 'hash' => 'base', 'inputs' => [
    'values' => ['arrival' => 1000, 'winter' => true, 'presence' => false],
    'changedAt' => ['arrival' => 90],
    'events' => ['storageEvent' => ['EventActive' => true, 'NextRun' => 200, 'LastRun' => 0],
        'fanEvent' => ['EventActive' => true, 'NextRun' => 300, 'LastRun' => 0]],
]];
$record = ['forecast' => ['plan' => $base, 'beforePhase' => true, 'issuedAt' => 101, 'end' => 1000,
    'projection' => ['storageStart' => 200, 'fanStart' => 300]]];
$snapshot = static function (int $t, array $values = [], array $events = []) use ($base): array {
    $p = $base;
    $p['observedAt'] = $t;
    $p['inputs']['values'] = array_replace($p['inputs']['values'], $values);
    $p['inputs']['events'] = array_replace_recursive($p['inputs']['events'], $events);
    $p['hash'] = SAEF_CreateConfigurationHash($p['inputs']);
    return $p;
};
$assess = static fn(array $plans, int $time = 400): array => ComparisonLifecycle::assess($record, array_merge([$base], $plans), $time);
check($assess([])['eligible'], 'Unchanged plan eligible');
check($assess([], 1001)['state'] === 'completed', 'Elapsed horizon completes normally');
$cancel = $snapshot(150, ['arrival' => 0]);
$reselect = $snapshot(160, ['arrival' => 500]);
check($assess([$cancel])['state'] === 'cancelled' && !$assess([$cancel])['eligible'], 'Cancellation without replacement curve excludes');
check($assess([$reselect])['state'] === 'replaced', 'Direct earlier arrival replacement excludes');
check($assess([$cancel, $reselect])['state'] === 'cancelled', 'Cancellation retained when followed by short rest-heat selection');
check($assess([$snapshot(100, ['arrival' => 500])])['state'] === 'replaced', 'Same-second ordered snapshot replacement recognized');
check($assess([$snapshot(150, [], ['fanEvent' => ['NextRun' => 400]])])['state'] === 'replaced', 'Changed fan schedule replaces plan');
check($assess([$snapshot(150, [], ['fanEvent' => ['EventActive' => false]])])['state'] === 'cancelled', 'Disabled pending stage cancels');
check($assess([$snapshot(150, ['winter' => false])])['state'] === 'cancelled', 'Summer switch cancels');
$ran = $snapshot(205, [], ['storageEvent' => ['NextRun' => 0, 'LastRun' => 200, 'EventActive' => false]]);
check($assess([$ran])['eligible'], 'Consumed one-shot and flag changes are not cancellation');
$rearmed = $snapshot(250, ['storageRequested' => true], ['storageEvent' => ['NextRun' => 280, 'LastRun' => 200]]);
check($assess([$ran, $rearmed])['eligible'], 'Rearmed timer for an already active stage does not replace warming plan');
$arrival = $snapshot(950, ['presence' => true, 'arrival' => 0], [
    'storageEvent' => ['EventActive' => false, 'NextRun' => 0], 'fanEvent' => ['EventActive' => false, 'NextRun' => 0],
]);
check($assess([$ran, $arrival], 1001)['state'] === 'arrived' && $assess([$arrival], 1001)['eligible'], 'Normal arrival cleanup is not cancellation');
check($assess([$snapshot(1000, ['arrival' => 0])], 1001)['eligible'], 'Reset at horizon end does not retroactively cancel');
$excluded = $record;
$excluded['evaluation'] = $assess([$cancel]);
check(ComparisonLifecycle::assess($excluded, [], 2000) === $excluded['evaluation'], 'Terminal exclusion survives restart and history retention');
$unknown = $record;
$unknown['evaluation'] = ComparisonLifecycle::assess($record, [$reselect], 400);
check(!$unknown['evaluation']['eligible'], 'Incomplete history never establishes eligibility');
$unknown['evaluation'] = ComparisonLifecycle::assess($record, [], 400);
check(ComparisonLifecycle::assess($unknown, [$snapshot(450)], 500)['state'] === 'unknown', 'Repeated checks cannot manufacture missing history coverage');
$late = $record;
$late['forecast']['beforePhase'] = false;
check(!ComparisonLifecycle::assess($late, [$base], 400)['eligible'], 'After-start revision never becomes a pre-phase test');
check(str_contains(ComparisonLifecycle::label($excluded), 'Vorwahl abgebrochen') && str_contains(ComparisonLifecycle::label($excluded), 'ausgeschlossen'), 'Explicit cancellation and scoring label');

// Real observer cancellation without any replacement forecast, followed by normal timer reads.
$prepare($ts('2026-11-02 08:00'), $ts('2026-11-03 16:00'), $ts('2026-11-04 12:00'));
$resetProfile();
$m->properties['ChargeProfileJSON'] = Forecast::encode($profile);
$m->RefreshPlan();
$original = $profileRecords()[0];
$unchangedControls = $events;
$now += 30;
$values[40] = 0;
$values[24] = false;
$m->RefreshPlan();
$aborted = $profileRecords()[0];
check(count($profileRecords()) === 1 && $aborted['evaluation']['state'] === 'cancelled', 'Observer marks cancellation without replacement issue');
check(!$aborted['evaluation']['eligible'] && $aborted['profileMetrics']['gallery']['reference']['n'] === 0, 'Observer regular metrics exclude cancelled phase');
check($aborted['forecast'] === $original['forecast'] && $aborted['forecastHash'] === $original['forecastHash'], 'Classification never changes frozen model, plan or curves');
check($events === $unchangedControls && $commands === 0, 'Classification does not mutate external events or command devices');
$m->Create();
$m->ApplyChanges();
$m->RefreshPlan();
check($profileRecords()[0]['evaluation'] === $aborted['evaluation'], 'Restart preserves terminal classification');
echo $checks . " assertions including plan cancellation and replacement passed\n";
