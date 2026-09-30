<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Forecast.php';
use SAEF\StorageHeater\Forecast;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    ++$checks;
}
function rejects(callable $call, string $message): void
{
    try {
        $call();
    } catch (Throwable) {
        check(true, $message);
        return;
    }
    check(false, $message);
}
foreach (['2026-01-14' => 9, '2025-10-25' => 10, '2026-03-28' => 8] as $date => $hours) {
    $cycle = Forecast::cycle($date, 'Europe/Berlin');
    check(($cycle['end'] - $cycle['start']) / 3600 === $hours, 'DST night length');
    check($cycle['cutoff'] < $cycle['issueFrom'] && $cycle['issueFrom'] < $cycle['start'], 'No future history');
}
$rows = [['TimeStamp' => 3600, 'Duration' => 3600, 'Avg' => 3.0], ['TimeStamp' => 7200, 'Duration' => 3600, 'Avg' => 4.0]];
check(array_sum(Forecast::hourly(array_reverse($rows), 3600, 10800)) === 7.0, 'Newest first archive');
rejects(fn() => Forecast::hourly([$rows[0]], 3600, 10800), 'Missing hour');
rejects(fn() => Forecast::hourly([$rows[0], $rows[0]], 3600, 10800), 'Duplicate hour');
rejects(fn() => Forecast::hourly([['TimeStamp' => 3600, 'Duration' => 1200, 'Avg' => 3]], 3600, 7200), 'Partial hour');
check(Forecast::duty([['TimeStamp' => 1, 'Value' => false], ['TimeStamp' => 5400, 'Value' => true]], 3600, 7200)['hours'] === 0.5, 'Carry-in and partial duty');
rejects(fn() => Forecast::duty([['TimeStamp' => 5400, 'Value' => true]], 3600, 7200), 'No default false');
$points = [];
for ($t = 3600; $t <= 10800; $t += 3600) {
    $points[] = ['validFrom' => $t, 'validTo' => $t, 'value' => $t / 3600, 'unit' => '°C', 'semantics' => 'instant'];
}
check(Forecast::weather($points, 3600, 10800)['mean'] === 2.0, 'Weather trapezoids');
rejects(fn() => Forecast::weather(array_slice($points, 0, 2), 3600, 10800), 'Missing final weather point');
$model = ['schema' => 1, 'features' => Forecast::FEATURES, 'cutoffHour' => 21, 'window' => '22-07', 'timezone' => 'Europe/Berlin', 'trainedThrough' => 1,
    'mean' => array_fill(0, 8, 0.0), 'scale' => array_fill(0, 8, 1.0), 'coefficients' => array_fill(0, 8, 1.0), 'intercept' => -1.0, 'ranges' => array_fill(0, 8, [0.0, 10.0])];
$features = array_fill_keys(Forecast::FEATURES, 2.0);
check(Forecast::predict($model, $features, 10)['kwh'] === 15.0, 'Known linear result');
rejects(fn() => Forecast::predict($model, $features, 1), 'No training from the future');
$features['gallery'] = INF;
rejects(fn() => Forecast::predict($model, $features, 10), 'Nonfinite input');
$model['scale'][0] = 0;
rejects(fn() => Forecast::validateModel($model), 'Invalid scale');
rejects(fn() => Forecast::decode('null'), 'Corrupt JSON state');
echo $checks . " core checks passed\n";
