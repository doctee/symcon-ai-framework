<?php

declare(strict_types=1);

require __DIR__ . '/TestClock.php';
require __DIR__ . '/mixed.php';

use SAEF\StorageHeater\FanComparison;
use SAEF\StorageHeater\Forecast;

$ts = static fn(string $s): int => strtotime($s . ' Europe/Berlin');
$prepare = static function (int $at, int $phase, int $arrival) use ($ff, $m, $models): void {
    global $now, $values, $events, $sensorUpdates, $changedAt, $rawFixtures, $stale, $gap, $badWeather;
    $now = $at;
    $sensorUpdates = $changedAt = $rawFixtures = [];
    $stale = $gap = $badWeather = false;
    foreach ($ff['plan']['inputs']['bindings'] as $key => $id) {
        if (str_ends_with($key, 'Event')) {
            $events[$id] = $ff['plan']['inputs']['events'][$key];
        } else {
            $values[$id] = $ff['plan']['inputs']['values'][$key];
        }
    }
    $values[40] = $arrival;
    $values[56] = 0;
    $values[57] = $values[58] = $values[46] = false;
    $events[50]['NextRun'] = $phase;
    $events[51]['NextRun'] = $phase + 3600;
    $events[52]['NextRun'] = $arrival + 12 * 3600;
    foreach ([18 => false, 19 => true, 20 => true, 21 => false] as $id => $v) {
        $rawFixtures[$id] = [['TimeStamp' => $at - 9 * 86400, 'Value' => $v]];
    }
    $rawFixtures[14] = [['TimeStamp' => $at - 7200, 'Value' => .001], ['TimeStamp' => $at - 60, 'Value' => .001]];
    $m->properties['FanComparisonModelJSON'] = Forecast::encode($models);
    foreach (['FanComparisonJournal', 'WarmingJournal'] as $key) {
        $m->attributes[$key] = '{"schema":1,"discarded":0,"records":[]}';
    }
};
$records = static fn(): array => FanComparison::journal($m->GetFanComparisonJournalJson())['records'];
$refreshPower = static function () use (&$rawFixtures, &$now): void {
    $rawFixtures[14] = [['TimeStamp' => $now - 7200, 'Value' => .001], ['TimeStamp' => $now - 60, 'Value' => .001]];
};

// Regression: Tuesday issue used to cover only ordinary nights and suppress Thursday's first night.
$phase = $ts('2026-10-22 16:15');
$arrival = $ts('2026-10-24 12:00');
$prepare($ts('2026-10-20 18:00'), $phase, $arrival);
$controlBefore = $events;
$controllerBefore = $controller;
$m->RefreshPlan();
check($records() === [], 'Early selection does not freeze unrelated reduced nights');
check(str_contains(GetValue($m->ids['FanComparisonStatus']), '21.10.2026 07:00'), 'Waiting status names automatic eligibility time');
$m->Create();
$m->ApplyChanges();
$now = $ts('2026-10-21 06:59:59');
$m->RefreshPlan();
check($records() === [], 'Restart and repeated refresh keep waiting before complete horizon');
$now++;
$refreshPower();
$badWeather = true;
$m->Tick();
check($records() === [], 'Missing weather at eligibility remains retryable, never invents a forecast');
$badWeather = false;
$now += 300;
$m->Tick();
$saved = $records();
check(count($saved) === 1, 'Ordinary timer issues without any new user edit: ' . GetValue($m->ids['FanComparisonStatus']));
$firstForecast = $saved[0]['forecast'];
check(array_keys($firstForecast['fanComparison']['nights']) === ['2026-10-22'], 'Only first affected full warming night is saved');
check($firstForecast['issuedAt'] === $now && $now < $phase, 'Actual successful issue time is frozen before phase');
$m->Create();
$m->ApplyChanges();
$m->RefreshPlan();
check(count($records()) === 1 && $records()[0]['forecast'] === $firstForecast, 'Restart neither duplicates nor overwrites valid snapshot');
$now = $ts('2026-10-22 16:00');
$m->RefreshPlan();
check(count($records()) === 1 && $records()[0]['forecast'] === $firstForecast, 'First night remains covered immediately before warming starts');
check($events === $controlBefore && $controller === $controllerBefore && $commands === 0, 'Waiting and timer refresh preserve controls and all scheduled times');

// A changed plan gets a fresh issue; moving it farther out returns to waiting, preserving history.
$now = $ts('2026-10-21 08:00');
$refreshPower();
$events[50]['NextRun'] += 900;
$events[51]['NextRun'] += 900;
$m->RefreshPlan();
check(count($records()) === 2 && $records()[0]['forecast'] === $firstForecast, 'Rescheduled first stage appends immutable revision');
$events[50]['NextRun'] += 7 * 86400;
$events[51]['NextRun'] += 7 * 86400;
$events[52]['NextRun'] += 7 * 86400;
$values[40] += 7 * 86400;
$m->RefreshPlan();
check(count($records()) === 2 && str_contains(GetValue($m->ids['FanComparisonStatus']), 'wartet bis'), 'Later plan waits and keeps prior evidence');
$values[40] = 0;
$m->RefreshPlan();
check(count($records()) === 2, 'Cancellation keeps saved comparisons');

// Fourteen-day selection is accepted by observer; timing depends on actual event, not input age.
$prepare($arrival - 14 * 86400, $phase, $arrival);
$m->RefreshPlan();
check($records() === [], 'Very early selection is normal waiting, not a spurious forecast');
$m->Create();
$m->ApplyChanges();
$now = FanComparison::issueWindow($phase)['earliest'];
$refreshPower();
$m->Tick();
check(count($records()) === 1, 'Fourteen-day selection eventually issues via unchanged timer');

// A legacy snapshot without the first night must not suppress a corrected record, even with matching hashes.
$saved = $records()[0];
$legacy = $saved;
$legacy['forecast']['fanComparison']['nights'] = ['2026-10-21' => reset($saved['forecast']['fanComparison']['nights'])];
$legacy['forecast']['fanComparison']['nights']['2026-10-21']['cycle'] = Forecast::cycle('2026-10-21', 'Europe/Berlin');
unset($legacy['forecast']['fanComparison']['firstWarmingNight']);
$legacy['forecastHash'] = $legacy['key'] = SAEF_CreateConfigurationHash($legacy['forecast']);
$m->attributes['FanComparisonJournal'] = Forecast::encode(['schema' => 1, 'discarded' => 0, 'records' => [$legacy]]);
$m->RefreshPlan();
check(count($records()) === 2 && $records()[0]['forecast'] === $legacy['forecast'], 'Coverage check supersedes unrelated legacy night without rewriting it');

// Local-calendar first-night boundaries and elapsed 48-hour windows include both DST changes.
foreach (
    [
    ['2026-02-06 06:59:59', '2026-02-05'],
    ['2026-02-06 07:00', '2026-02-06'],
    ['2026-02-05 23:45', '2026-02-05'],
    ['2026-03-29 03:00', '2026-03-28'],
    ['2026-10-25 03:00', '2026-10-24'],
    ] as [$start, $date]
) {
    $p = $ts($start);
    $w = FanComparison::issueWindow($p);
    check($w['cycle']['date'] === $date && $w['cycle']['end'] - $w['earliest'] === 48 * 3600, 'Correct first night and horizon: ' . $start);
    $prepare($w['earliest'], $p, $w['cycle']['end'] + 3600);
    $m->RefreshPlan();
    check(isset($records()[0]['forecast']['fanComparison']['nights'][$date]), 'Observer includes first night across boundary: ' . $start . ': ' . GetValue($m->ids['FanComparisonStatus']));
}

$prepare($ts('2026-10-22 22:00'), $ts('2026-10-22 23:45'), $arrival);
$m->RefreshPlan();
check($records() === [] && str_contains(GetValue($m->ids['FanComparisonStatus']), 'Vorabfrist'), 'At 22:00 no late first-night issue even before storage start');
$prepare($phase, $phase, $arrival);
$m->RefreshPlan();
check($records() === [], 'At phase start no backfill');
$prepare($ts('2026-10-21 12:00'), $phase, $ts('2026-10-23 06:59'));
$m->RefreshPlan();
check($records() === [] && str_contains(GetValue($m->ids['FanComparisonStatus']), 'Ankunft'), 'Arrival truncating first night is explicitly excluded');
check($commands === 0, 'Entire early-plan regression remains non-actuating');
echo $checks . " assertions including early preselection passed\n";
