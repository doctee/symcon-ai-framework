<?php

declare(strict_types=1);

require __DIR__ . '/plans.php';

use SAEF\StorageHeater\Forecast;
use SAEF\StorageHeater\Warming;
use SAEF\StorageHeater\WarmingView;
use SAEF\StorageHeater\WarmingWeather;

$now = strtotime('2026-02-05 16:00 Europe/Berlin');
$stale = $gap = $badWeather = false;
$sensorUpdates = $changedAt = $rawFixtures = [];
$controller = ['Type' => 0, 'Mode' => 0, 'SourceID' => 16, 'Hysteresis' => .2, 'SetBack' => 5.0,
    'TargetVariables' => '[{"TargetID":18,"Invert":false}]'];
$p = ['tau_hours' => 20.0, 'fan_decay_per_hour' => .08, 'k_per_hour' => .02,
    'mass_coupling_per_hour' => .3, 'mass_time_hours' => 24.0, 'coefficients' => [.01, .03, .02, .01]];
$wm = ['schema' => 1, 'timezone' => 'Europe/Berlin', 'trainedThrough' => 1, 'rooms' => ['gallery' => $p, 'sofa' => $p]];
foreach (
    [16 => 12.0, 17 => 11.0, 15 => 5.0, 18 => false, 19 => true, 20 => true, 21 => false,
    40 => $now + 30 * 3600, 41 => false, 42 => false, 43 => false, 44 => true, 45 => 20.5, 46 => false, 47 => true] as $id => $v
) {
    $values[$id] = $v;
    $types[$id] = is_bool($v) ? 0 : (is_int($v) ? 1 : 2);
}
$events = [50 => array_replace($event, ['NextRun' => $now + 3600]),
    51 => array_replace($event, ['NextRun' => $now + 16 * 3600]),
    52 => array_replace($event, ['NextRun' => $now + 40 * 3600])];
foreach ([18 => false, 19 => true, 20 => true, 21 => false] as $id => $v) {
    $rawFixtures[$id] = [['TimeStamp' => $now - 9 * 86400, 'Value' => $v]];
}
$rawFixtures[14] = [['TimeStamp' => $now - 7200, 'Value' => .001], ['TimeStamp' => $now - 60, 'Value' => .001]];
$m = new TestModule();
$m->Create();
$m->properties['Enabled'] = true;
$m->properties['BindingsJSON'] = Forecast::encode($b);
$m->properties['ModelJSON'] = Forecast::encode($model);
$m->properties['CoolingModelJSON'] = Forecast::encode($coolModel);
$m->properties['HistoryStart'] = '2025-01-01';
$m->properties['PlanBindingsJSON'] = Forecast::encode($pb);
$m->properties['WarmingModelJSON'] = Forecast::encode($wm);
$m->properties['WarmingOptionsJSON'] = Forecast::encode(['regulator' => 55, 'latitude' => 50.0, 'longitude' => 8.0, 'maxPowerKw' => 7.0, 'standbyKw' => .001]);
$m->ApplyChanges();
$m->RefreshPlan();
$j = Warming::journal($m->GetWarmingJournalJson());
check(count($j['records']) === 1, 'Prospective record saved: ' . GetValue($m->ids['WarmingStatus']));
$r = $j['records'][0];
$frozen = $r['forecast'];
check($frozen['beforePhase'] && $frozen['issuedAt'] === $now, 'Issue time precedes true planned phase start');
check($frozen['end'] === $values[40] && count($frozen['points']) === 31, 'Arrival limits hourly horizon');
check(end($frozen['points'])['fanHours'] > 0, 'Future thermostat permission simulates nonzero fan use');
check($frozen['points'][8]['fanHours'] == 0.0, 'First charging night keeps fan off until planned event');
check($frozen['history']['until'] === $now && $frozen['weatherFetchedAt'] < $now, 'Only known inputs used at issue');
$m->RefreshPlan();
check(count(Warming::journal($m->GetWarmingJournalJson())['records']) === 1, 'Repeated timer does not reissue unchanged forecast');
$values[40] += 3600;
$m->RefreshPlan();
$j = Warming::journal($m->GetWarmingJournalJson());
check(count($j['records']) === 2 && $j['records'][0]['forecast'] === $frozen, 'Plan change appends and preserves original');
check(isset($j['records'][0]['supersededAt']), 'Superseded revision labeled separately');
$before = $m->attributes['WarmingJournal'];
$m->Create();
$m->ApplyChanges();
check($before === $m->attributes['WarmingJournal'], 'Restart retains immutable forecasts');
$originalEnergy = $m->attributes['Journal'];
$start = $now;
$now += 3 * 3600;
foreach ([16 => 12.0, 17 => 11.0] as $id => $v) {
    $rawFixtures[$id] = [['TimeStamp' => $start - 60, 'Value' => $v], ['TimeStamp' => $now, 'Value' => $v]];
}
$m->RefreshPlan();
$j = Warming::journal($m->GetWarmingJournalJson());
check($j['records'][0]['forecast'] === $frozen, 'Later actual temperatures cannot overwrite forecast');
check($j['records'][0]['metrics']['gallery']['n'] === 3, 'Initial point excluded, three future hours scored');
check($j['records'][0]['metrics']['gallery']['mae'] > 0, 'Real forecast error retained');
check($m->attributes['Journal'] === $originalEnergy && $commands === 0, 'No device action or energy journal mutation');
$values[40] = 0;
$m->RefreshPlan();
check(count(Warming::journal($m->GetWarmingJournalJson())['records']) === 2, 'Cleared plan keeps measuring and never backfills');
$values[40] = $now + 72 * 3600;
$values[41] = true;
$changedAt[41] = $start + 3600;
$events[50]['NextRun'] = 0;
$rawFixtures[14][] = ['TimeStamp' => $now - 60, 'Value' => .001];
$badWeather = true;
$m->RefreshPlan();
check(count(Warming::journal($m->GetWarmingJournalJson())['records']) === 2 && str_contains(GetValue($m->ids['WarmingStatus']), 'Weather'), 'Missing forecast weather does not create curve: ' . GetValue($m->ids['WarmingStatus']));
$badWeather = false;
$stale = true;
$m->RefreshPlan();
check(count(Warming::journal($m->GetWarmingJournalJson())['records']) === 2, 'Stale sensors reject new issue');
$stale = false;
$rawFixtures[14][] = ['TimeStamp' => $now - 60, 'Value' => .001];
$m->RefreshPlan();
$j = Warming::journal($m->GetWarmingJournalJson());
check(count($j['records']) === 3 && end($j['records'])['forecast']['end'] - $now === 48 * 3600, 'Long preheat capped at 48 hours');
check(!end($j['records'])['forecast']['beforePhase'], 'Expired phase start is not relabeled pre-phase');
$corrupt = $m->attributes['WarmingJournal'];
$m->attributes['WarmingJournal'] = '{"schema":1,"records":"bad"}';
$m->RefreshPlan();
check($m->attributes['WarmingJournal'] === '{"schema":1,"records":"bad"}', 'Corrupt journal preserved, not reset');
$m->attributes['WarmingJournal'] = $corrupt;
$reads = $archiveReads;
$m->properties['Enabled'] = false;
$m->ApplyChanges();
$m->Tick();
$m->RefreshPlan();
check($archiveReads === $reads, 'Disabled observer does not collect or simulate');

[$air, $mass] = Warming::roomStep(10, 10, 10, 0, 0, 0, 0, .25, $p);
check(abs($air - 10) < 1e-10 && abs($mass - 10) < 1e-10, 'Exact equilibrium preserved');
check(abs(Warming::storeStep(0, 2, 0, 1, $p) - 40 * (1 - exp(-.05))) < 1e-12, 'Charge-memory exact analytical integration');
$f = $frozen;
$f['projection']['segments'] = [['from' => $f['start'], 'to' => $f['end'], 'enabled' => false, 'reduced' => false, 'fanPermitted' => false]];
$off = Warming::predict($f);
check(end($off)['fanHours'] == 0.0 && end($off)['chargeKwh'] == 0.0, 'Permission and charging disable affect simulation only');
foreach (['2026-03-28 23:00 Europe/Berlin' => 7, '2026-10-24 23:00 Europe/Berlin' => 9] as $date => $hours) {
    $c = Warming::chargeCycle(strtotime($date));
    check($c['end'] - $c['start'] === $hours * 3600, 'DST charging duration uses elapsed seconds');
}
$pending = ['schema' => 1, 'discarded' => 0, 'records' => array_fill(0, 49, $r)];
$threw = false;
try {
    Warming::retain($pending);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Retention never silently removes pending prospective evidence');
$pending['records'][0]['settledAt'] = $now;
check(Warming::retain($pending)['discarded'] === 1, 'Settled evidence retention is counted');
$html = WarmingView::render($j, '<script>bad</script>');
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Warming view escapes text');
check(str_contains($html, 'keine unabhängigen Testphasen') && str_contains($html, 'Experimentelles'), 'Accuracy limits visible');
file_put_contents($argv[1] . '/warming-preview.html', WarmingView::render(['records' => [$j['records'][0]], 'discarded' => 0], 'Testdaten – keine Live-Prognose'));
$masked = Warming::maskBattery(
    [100 => ['value' => 10.0, 'from' => 100, 'to' => 110],
    23000 => ['value' => 10.0, 'from' => 23000, 'to' => 23001]],
    [['TimeStamp' => 1, 'Value' => 50], ['TimeStamp' => 10, 'Value' => 0], ['TimeStamp' => 50, 'Value' => 100]]
);
check($masked[100] === null && $masked[23000] !== null, 'Six-hour battery recovery quarantine applied');
$day = strtotime('2026-03-20 12:00 UTC');
$data = [];
foreach (['direct_normal_irradiance' => 500.0, 'diffuse_radiation' => 100.0] as $field => $value) {
    $data[$field] = [['validFrom' => $day, 'validTo' => $day + 3600, 'value' => $value, 'unit' => 'W/m²', 'semantics' => 'preceding_interval']];
}
$sol = WarmingWeather::solar($data, $day, $day + 3600, 0.0, 0.0);
check($sol[$day] > 580 && $sol[$day] < 601, 'Equatorial noon projects DNI nearly horizontally');
$data['diffuse_radiation'][0]['semantics'] = 'instant';
$threw = false;
try {
    WarmingWeather::solar($data, $day, $day + 3600, 0, 0);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Wrong solar interval semantics rejected');
$broken = $j;
$broken['records'][0]['forecast']['points'][1]['gallery'] += 1;
$threw = false;
try {
    Warming::journal(Forecast::encode($broken));
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Tampered frozen curve rejected before display or scoring');
echo $checks . " total assertions including prospective warming passed; no external writes or device actions\n";
