<?php

declare(strict_types=1);

require __DIR__ . '/display.php';

use SAEF\StorageHeater\ChargeProfile;
use SAEF\StorageHeater\FanComparison;
use SAEF\StorageHeater\Forecast;
use SAEF\StorageHeater\Warming;

$profile = ['schema' => 1, 'method' => 'elapsed_eight_bins_v1', 'trainedThrough' => 1,
    'weights' => [.25, .20, .15, .10, .08, .07, .05, .10]];
$m->properties['ChargeProfileJSON'] = Forecast::encode($profile);
$profileRecords = static fn(): array => ChargeProfile::journal($m->GetProfileComparisonJournalJson())['records'];
$resetProfile = static function (): void {
    global $m;
    $m->attributes['ProfileComparisonJournal'] = '{"schema":1,"discarded":0,"records":[]}';
};
$phase = $ts('2026-10-22 16:15');
$arrival = $ts('2026-10-24 12:00');
$prepare($ts('2026-10-20 18:00'), $phase, $arrival);
$resetProfile();
$controlBefore = $events;
$m->RefreshPlan();
check($profileRecords() === [] && str_contains(GetValue($m->ids['ProfileComparisonStatus']), '21.10.2026 07:00'), 'Profile waits for first complete night');
$now = FanComparison::issueWindow($phase)['earliest'];
$refreshPower();
$badWeather = true;
$m->Tick();
check($profileRecords() === [], 'Profile missing weather retries without false save');
$badWeather = false;
$now += 300;
$m->Tick();
check(count($profileRecords()) === 1, 'Natural timer saves profile without new selection: ' . GetValue($m->ids['ProfileComparisonStatus']));
$r = $profileRecords()[0];
$f = $r['forecast'];
$baseline = Warming::predict($f);
check(Forecast::encode($f['points']) === Forecast::encode($baseline), 'Baseline numerical path unchanged');
check($f['profileComparison']['points'] !== $baseline, 'Candidate changes temperature curve');
check(abs(end($baseline)['chargeKwh'] - end($f['profileComparison']['points'])['chargeKwh']) < 1e-8, 'Paired total energy conserved');
check($f['issuedAt'] < $phase && $f['end'] >= $f['profileComparison']['firstWarmingNight']['end'], 'Pre-phase complete first night frozen');
$beforeEnergy = $m->attributes['Journal'];
$beforeFan = $m->attributes['FanComparisonJournal'];
$m->Create();
$m->ApplyChanges();
$m->RefreshPlan();
check(count($profileRecords()) === 1 && $profileRecords()[0]['forecast'] === $f, 'Restart preserves and deduplicates profile');
check($m->attributes['Journal'] === $beforeEnergy && array_column(Forecast::decode($m->attributes['FanComparisonJournal'])['records'], 'forecast') === array_column(Forecast::decode($beforeFan)['records'], 'forecast'), 'Profile lifecycle preserves other energy journals');
$events[50]['NextRun'] += 900;
$events[51]['NextRun'] += 900;
$m->RefreshPlan();
check(count($profileRecords()) === 2 && $profileRecords()[0]['forecast'] === $f, 'Changed selection appends separate immutable revision');
$events = $controlBefore;
$now = $phase;
$m->RefreshPlan();
check(count($profileRecords()) === 2, 'No phase-start backfill');
$values[40] = 0;
$m->properties['ChargeProfileJSON'] = '{}';
$now = $f['end'] + 21600;
foreach ([16 => 14.0, 17 => 13.0] as $id => $v) {
    $rawFixtures[$id] = [];
    for ($t = $f['start'] - 60; $t <= $now; $t += 1800) {
        $rawFixtures[$id][] = ['TimeStamp' => $t, 'Value' => $v];
    }
}
$m->RefreshPlan();
$measured = $profileRecords()[0];
check($measured['forecast'] === $f && $measured['settledAt'] === $now, 'Disabled selection/configuration still completes existing comparison');
check($measured['diagnosticProfileMetrics']['gallery']['reference']['n'] > 0, 'Replaced profile retains measured diagnostic scores');
check($measured['profileMetrics']['gallery']['reference']['n'] === 0 && !$measured['evaluation']['eligible'], 'Replaced profile excluded from regular scores');
check($measured['profileMetrics']['gallery']['reference']['n'] === $measured['profileMetrics']['gallery']['profile']['n'], 'Same actual points scored for both models');
$bad = $measured;
$bad['forecast']['profileComparison']['points'][1]['gallery'] += .1;
try {
    ChargeProfile::journal(Forecast::encode(['schema' => 1, 'discarded' => 0, 'records' => [$bad]]));
    check(false, 'Tampered candidate must fail');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'hash'), 'Candidate covered by immutable forecast hash');
}
file_put_contents($argv[1] . '/profile-comparison-preview.html', ChargeProfile::render(['records' => [$measured]], 'Test abgeschlossen'));

// Independent integration checks: normal and DST nights, mixed reduction, fan-only transitions and saturation.
foreach (['2026-02-05', '2026-03-28', '2026-10-24'] as $date) {
    $cycle = Forecast::cycle($date, 'Europe/Berlin');
    $cycle['start'] += 3600;
    $length = $cycle['end'] - $cycle['start'];
    foreach ([0.0, 8.0, 30.0, 90.0] as $total) {
        foreach ([false, true] as $mixed) {
            $mid = $cycle['start'] + (int) ($length * .43);
            $s = ['from' => $cycle['start'], 'to' => $cycle['end'], 'enabled' => true, 'reduced' => false];
            $segments = $mixed ? [array_replace($s, ['to' => $mid, 'reduced' => true]), array_replace($s, ['from' => $mid])] : [$s];
            $fixture = ['start' => $cycle['start'], 'end' => $cycle['end'], 'maxPowerKw' => 7.2,
                'chargeNights' => [$date => ['normal' => ['kwh' => $total], 'reduced' => ['kwh' => $total / 2]]],
                'projection' => ['segments' => $segments]];
            $schedule = ChargeProfile::schedule($fixture, $profile);
            foreach ($segments as $segment) {
                $expected = min(7.2, ($segment['reduced'] ? $total / 2 : $total) / ($length / 3600)) * ($segment['to'] - $segment['from']) / 3600;
                check(abs(ChargeProfile::energy($schedule, $segment['from'], $segment['to']) - $expected) < 1e-8, 'Segment energy conserved across DST and reduction');
            }
            foreach ($schedule as $bin) {
                check($bin['kwh'] >= 0 && $bin['kwh'] / (($bin['to'] - $bin['from']) / 3600) <= 7.2 + 1e-9, 'Power bounded and nonnegative');
            }
            $integral = 0.0;
            for ($t = $cycle['start']; $t < $cycle['end']; $t += 900) {
                $integral += ChargeProfile::energy($schedule, $t, min($t + 900, $cycle['end']));
            }
            check(abs($integral - array_sum(array_column($schedule, 'kwh'))) < 1e-8, 'Quarter-hour integration preserves frozen schedule');
        }
    }
}
$m->properties['ChargeProfileJSON'] = Forecast::encode($profile);
foreach (['2026-02-06 06:59:59', '2026-02-06 07:00', '2026-03-29 03:00', '2026-10-25 03:00'] as $start) {
    $phase = $ts($start);
    $w = FanComparison::issueWindow($phase);
    $prepare($w['earliest'], $phase, $w['cycle']['end'] + 3600);
    $resetProfile();
    $m->RefreshPlan();
    check(count($profileRecords()) === 1, 'Profile first-night boundary: ' . $start . GetValue($m->ids['ProfileComparisonStatus']));
}
$prepare($ts('2026-10-22 22:00'), $ts('2026-10-22 23:45'), $arrival);
$resetProfile();
$m->RefreshPlan();
check($profileRecords() === [], 'At 22 no backfill of profile forecast');
check($commands === 0 && $controller === $controllerBefore, 'All profile cases keep heating control unchanged');
echo $checks . " assertions including charge profile passed\n";
