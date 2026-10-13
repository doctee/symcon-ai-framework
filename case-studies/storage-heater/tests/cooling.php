<?php

declare(strict_types=1);

// Existing suite supplies the command-free runtime; its external-write guard stays active.
require __DIR__ . '/runtime.php';

use SAEF\StorageHeater\Cooling;
use SAEF\StorageHeater\CoolingView;
use SAEF\StorageHeater\Forecast;

$coolModel = ['schema' => 1, 'timezone' => 'Europe/Berlin', 'window' => '23-05', 'trainedThrough' => 1,
    'kPerHour' => ['gallery' => .01, 'sofa' => .02],
    'ranges' => ['gallery' => [10, 30], 'sofa' => [10, 30], 'outside' => [-10, 25]]];
$base = strtotime('2026-01-17 23:00 Europe/Berlin');
$initial = ['gallery' => ['value' => 20.0], 'sofa' => ['value' => 18.0], 'outside' => ['value' => 10.0]];
$weather = ['points' => []];
for ($i = 0; $i <= 6; ++$i) {
    $weather['points'][] = ['time' => $base + $i * 3600, 'temperature' => 10.0];
}
$p = Cooling::predict($coolModel, $initial, $weather, $base, $base + 21600);
check(abs($p[6]['gallery'] - (10 + 10 * exp(-.06))) < 1e-10, 'Closed-form cooling agrees with analytic solution');
check(abs($p[6]['sofa'] - (10 + 8 * exp(-.12))) < 1e-10, 'Independent room coefficient used');
$p = Cooling::predict($coolModel, $initial, $weather, $base + 300, $base + 21600);
check(count($p) === 7 && $p[0]['time'] === $base + 300, 'Initial temperature never backdated to night start');
foreach (['2026-03-28 23:01 Europe/Berlin' => 5, '2026-10-24 23:01 Europe/Berlin' => 6] as $time => $hours) {
    $cycle = Cooling::cycle(strtotime($time));
    check($cycle['end'] - $cycle['start'] === $hours * 3600, 'DST preserves elapsed horizon cap');
}
$raw = [['TimeStamp' => $base, 'Value' => 20.0], ['TimeStamp' => $base + 7200, 'Value' => 18.0]];
$actual = Cooling::measured($raw, [$base + 3600, $base + 10800], $base + 10800);
check($actual[$base + 3600]['value'] === 19.0 && $actual[$base + 10800] === null, 'Interpolation requires two real brackets; no forward filling');
check(Cooling::measured([$raw[0], ['TimeStamp' => $base + 7 * 3600, 'Value' => 18.0]], [$base + 3600], $base + 7 * 3600)[$base + 3600] === null, 'Long sensor dropout stays missing');
$masked = Cooling::maskBattery($actual, [['TimeStamp' => $base - 10, 'Value' => 50], ['TimeStamp' => $base + 10, 'Value' => 0], ['TimeStamp' => $base + 7000, 'Value' => 100]]);
check($masked[$base + 3600] === null, 'Battery exhaustion inside interpolation bracket masked');
$threw = false;
try {
    Cooling::measured([$raw[1]], [$base], $base);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Future measurement rejected');
$bad = $coolModel;
$bad['kPerHour']['sofa'] = 0;
$threw = false;
try {
    Cooling::model($bad);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'Zero coefficient rejected');

// Optional cooling model must be isolated from the existing electrical journal.
$b['energy'] = 13;
$m->properties['BindingsJSON'] = Forecast::encode($b);
$m->properties['CoolingModelJSON'] = Forecast::encode($coolModel);
$m->properties['CoolingBindingsJSON'] = '{"sofaBattery":31}';
$types[31] = VARIABLETYPE_INTEGER;
$values[31] = 80;
$now = $base + 240;
$m->ApplyChanges();
check($m->status === IS_ACTIVE && isset($m->references[31]), 'Optional battery referenced without external writes');
$stale = true;
$m->Tick();
check(Cooling::journal($m->GetCoolingJournalJson())['records'] === [], 'Stale sensors block cooling issue');
$stale = false;
$values[31] = 0;
$m->Tick();
check(Cooling::journal($m->GetCoolingJournalJson())['records'] === [], 'Empty battery blocks cooling issue');
$values[31] = 80;
$badWeather = true;
$m->Tick();
check(Cooling::journal($m->GetCoolingJournalJson())['records'] === [], 'Missing future weather endpoint blocks issue');
$badWeather = false;
$values[19] = false;
$rawFixtures = [16 => [], 17 => [], 31 => [['TimeStamp' => $base - 7 * 3600, 'Value' => 80]]];
$m->Tick();
$j = Cooling::journal($m->GetCoolingJournalJson());
check(count($j['records']) === 1, 'Cooling works with heater disabled');
$issued = $j['records']['2026-01-17']['forecast'];
$energyJournal = $m->attributes['Journal'];
$values[16] = 25.0;
$m->Tick();
check(Cooling::journal($m->GetCoolingJournalJson())['records']['2026-01-17']['forecast'] === $issued, 'Issued temperatures immutable after new sensor reading');
check($m->attributes['Journal'] === $energyJournal, 'Cooling duplicate tick preserves electrical journal');
$now = $base + 2 * 3600 + 600;
foreach ([16, 17] as $id) {
    $rawFixtures[$id] = [['TimeStamp' => $base, 'Value' => 12.0], ['TimeStamp' => $base + 2 * 3600, 'Value' => 11.5]];
}
$m->Tick();
$j = Cooling::journal($m->GetCoolingJournalJson());
$r = $j['records']['2026-01-17'];
check($r['actual']['gallery'][$base + 3600]['value'] === 11.75, 'Real archive comparison filled at matching target time');
check(!isset($r['actual']['gallery'][$base + 3 * 3600]), 'Future target remains missing');
check($r['forecast'] === $issued, 'Measurements do not alter original weather/model/forecast');
$m->Create();
$m->ApplyChanges();
check(Cooling::journal($m->GetCoolingJournalJson())['records']['2026-01-17']['forecast'] === $issued, 'Cooling ledger survives lifecycle');
$m->properties['CoolingModelJSON'] = '{"invalid":true}';
$m->ApplyChanges();
$m->Tick();
check($m->status === IS_ACTIVE && str_contains(GetValue($m->ids['CoolingStatus']), 'Auskühlmodell'), 'Cooling config failure isolated from energy observation');
$m->properties['CoolingModelJSON'] = Forecast::encode($coolModel);
$m->ApplyChanges();
$now = $base + 12 * 3600;
$m->Tick();
$r = Cooling::journal($m->GetCoolingJournalJson())['records']['2026-01-17'];
check($r['settledAt'] === $now && !isset($r['actual']['gallery'][$base + 5 * 3600]), 'Missing measurements settle as gaps');
$now = strtotime('2026-01-18 23:20 Europe/Berlin');
$m->Tick();
check(count(Cooling::journal($m->GetCoolingJournalJson())['records']) === 1, 'No retrospective forecasts after issue window');
$html = CoolingView::render(['2026-01-17' => $r], [], '<script>alert(1)</script>');
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Untrusted display strings escaped');
check(substr_count($html, '<svg ') === 2 && str_contains($html, 'Galerie Messung'), 'Both charts and numerical comparison present');
$beforeDisable = $m->attributes['CoolingJournal'];
$m->properties['Enabled'] = false;
$m->ApplyChanges();
$reads = $archiveReads;
$m->Tick();
check($beforeDisable === $m->attributes['CoolingJournal'] && $archiveReads === $reads && $commands === 0, 'Disable preserves journal and stops observation; no device commands');
if (isset($argv[2])) {
    file_put_contents($argv[2], $html);
}
echo $checks . " total assertions including cooling passed; no external writes or device actions\n";
