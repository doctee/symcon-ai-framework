<?php

declare(strict_types=1);

require __DIR__ . '/decision-model.php';

use function CounterContinuityStudy\decide;

$count = 0;
$state = ['integrity' => true, 'pending' => false, 'total' => 20.0,
    'energy' => ['time' => 100, 'value' => 500.0],
    'power' => ['time' => 100, 'value' => 10.0]];
$sample = ['identity' => 'expected', 'readable' => true, 'stable' => true,
    'energy' => ['time' => 110, 'value' => 502.0],
    'power' => ['time' => 110, 'value' => 12.0],
    'health' => ['time' => 110, 'value' => true]];
$check = static function (bool $ok, string $name) use (&$count): void {
    if (!$ok) {
        throw new RuntimeException($name);
    }
    $count++;
};
$reject = static function (array $s, array $v, string $expected, int $now = 110) use ($check): void {
    $r = decide($s, $v, $now, 60);
    $check($r['status'] === $expected && $r['state'] === $s, $expected . ' preserves checkpoint');
};
$r = decide($state, $sample, 110, 60);
$check($r['status'] === 'ACCEPT' && abs($r['state']['total'] - 20.002) < 1e-12, 'positive delta');
$check($r['state']['power']['value'] === 12.0, 'unscaled power');
$again = decide($r['state'], $sample, 110, 60);
$check($again['state'] === $r['state'], 'duplicate does not count twice');
foreach (['energy', 'power', 'health'] as $field) {
    $v = $sample;
    $v[$field]['time'] = 49;
    $reject($state, $v, 'DEFER');
    $v[$field]['time'] = 111;
    $reject($state, $v, 'HOLD');
}
foreach (['readable', 'stable'] as $field) {
    $v = $sample;
    $v[$field] = false;
    $reject($state, $v, 'DEFER');
}
$reject($state, $sample, 'DEFER', 171);
$check(decide($state, $sample, 170, 60)['status'] === 'ACCEPT', 'inclusive age boundary');
$fresh = $sample;
foreach (['energy', 'power', 'health'] as $field) {
    $fresh[$field]['time'] = 172;
}
$check(decide($state, $fresh, 172, 60)['state']['total'] === $r['state']['total'], 'resume from retained checkpoint');
$v = $sample;
$v['energy']['value'] = 2.0;
$reject($state, $v, 'RESET_REVIEW');
foreach (['energy', 'power'] as $field) {
    $v = $sample;
    $v[$field]['time'] = 100;
    $reject($state, $v, 'DEFER');
    $v[$field]['value'] = NAN;
    $reject($state, $v, 'HOLD');
}
$v = $sample;
$v['identity'] = 'other';
$v['readable'] = false;
$reject($state, $v, 'HOLD');
$s = $state;
$s['pending'] = true;
$reject($s, $sample, 'RECONCILE');
$s = $state;
$s['integrity'] = false;
$reject($s, $sample, 'HOLD');
$v = $sample;
$v['health']['value'] = false;
$reject($state, $v, 'HOLD');
echo "PASS: $count synthetic decision checks; no live or persistence coverage.\n";
