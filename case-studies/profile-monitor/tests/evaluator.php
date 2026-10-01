<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/Evaluator.php';

use SAEF\ProfileMonitor\Evaluator;

$count = 0;
function expect($actual, $expected, string $label): void
{
    global $count;
    ++$count;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . json_encode([$actual, $expected]));
    }
}
function rejected(callable $operation, string $label): void
{
    try {
        $operation();
    } catch (InvalidArgumentException $error) {
        expect(true, true, $label);
        return;
    }
    throw new RuntimeException('Not rejected: ' . $label);
}
function row(int $id, $value, string $profile = '', array $extra = []): array
{
    return array_replace(['id' => $id, 'type' => is_bool($value) ? 0 : (is_int($value) ? 1 : 2),
        'value' => $value, 'updated' => 100, 'profile' => $profile], $extra);
}
$profiles = [['ProfileName' => '~Battery', 'ProfileValue' => true],
    ['ProfileName' => '~Battery.Reversed', 'ProfileValue' => false],
    ['ProfileName' => '~Battery.100', 'ProfileValue' => '10']];
$engine = new Evaluator($profiles, [['ID2Ignore' => 5]], []);
$r = $engine->evaluate([row(1, true, '~Battery'), row(2, false, '~Battery.Reversed'),
    row(3, 10, '~Battery.100'), row(4, 11, '~Battery.100'), row(5, 0, '~Battery.100'),
    row(6, 0, '~Battery.100', ['updated' => 0]), row(7, 0, 'unmonitored')]);
expect($r['checked'], [1, 2, 3, 4, 5, 6], 'checked includes exclusions and no-data');
expect($r['warnings'], [1, 2, 3], 'legacy equality and threshold');
expect($r['ignored'], [5], 'central exclusion');
expect($r['unknown'], [6], 'never updated');
expect($engine->evaluate([])['checked'], [], 'empty inventory');
expect((new Evaluator($profiles, [], [], false))->evaluate([row(1, 0, '~Battery.100', ['updated' => 0])])['warnings'], [1], 'explicit legacy no-data switch');
expect((new Evaluator([['ProfileName' => 'volt', 'ProfileValue' => '2,2']], [], []))->evaluate([row(1, 2.1, 'volt')])['warnings'], 2.1 <= '2,2' ? [1] : [], 'comma text comparison preserved');
$blink = Evaluator::presets(false, true, 10, 2);
$extra = ['moduleId' => Evaluator::BLINK_MODULE, 'ident' => 'battery', 'presentation' => ['PRESENTATION' => Evaluator::VALUE_PRESENTATION]];
$rows = [];
foreach ([0, 1, 2, 3, 4] as $value) {
    $rows[] = row($value + 1, $value, '', $extra);
}
$r = (new Evaluator([], [], $blink))->evaluate($rows);
expect($r['warnings'], [2, 3], 'Blink critical and low, no percentage interpretation');
expect($r['unknown'], [1, 5], 'Blink unknown and unexpected value');
expect((new Evaluator([], [], Evaluator::presets(false, true, 10, 1)))->evaluate($rows)['warnings'], [2], 'Blink critical-only option');
expect((new Evaluator([], [], $blink))->evaluate([row(1, 1, '', array_replace($extra, ['moduleId' => Evaluator::ZIGBEE_MODULE]))])['checked'], [], 'no name-only matching');
expect((new Evaluator([], [], $blink))->evaluate([row(1, 1, '', array_replace($extra, ['ident' => 'batteryVoltage']))])['checked'], [], 'exact ident');
expect((new Evaluator([], [], $blink))->evaluate([row(1, 1, '', array_replace($extra, ['action' => true]))])['warnings'], [], 'no actuator monitoring');
expect((new Evaluator([], [], $blink))->evaluate([row(1, 1, '', array_replace($extra, ['presentation' => []]))])['unknown'], [1], 'presentation drift visible');
$zigbee = Evaluator::presets(true, false, 10, 2);
$z = ['moduleId' => Evaluator::ZIGBEE_MODULE, 'ident' => 'battery', 'presentation' => ['PRESENTATION' => Evaluator::VALUE_PRESENTATION, 'MIN' => 0, 'MAX' => 200, 'PERCENTAGE' => true]];
$engine = new Evaluator([], [], $zigbee);
expect($engine->evaluate([row(1, 20, '', $z), row(2, 21, '', $z), row(3, 0, '', $z)])['warnings'], [1, 3], 'raw scale normalized and true zero warns');
foreach ([['MIN' => 100, 'MAX' => 100], ['MIN' => 200, 'MAX' => 100], ['PERCENTAGE' => false], ['MIN' => '0'], ['MIN' => -PHP_FLOAT_MAX, 'MAX' => PHP_FLOAT_MAX]] as $invalid) {
    $bad = $z;
    $bad['presentation'] = array_replace($z['presentation'], $invalid);
    expect($engine->evaluate([row(1, 5, '', $bad)])['unknown'], [1], 'invalid scale');
}
expect($engine->evaluate([row(1, 201, '', $z)])['unknown'], [1], 'out of range not clamped');
$floatRule = ['name' => 'voltage', 'variableId' => 1, 'type' => 2, 'operator' => 'le', 'threshold' => 2.2, 'unit' => 'raw'];
expect((new Evaluator([], [], [$floatRule]))->evaluate([row(1, 2.1)])['warnings'], [1], 'explicit Float voltage');
expect((new Evaluator([], [], [$floatRule]))->evaluate([row(1, INF)])['unknown'], [1], 'nonfinite measurement');
$boolean = ['name' => 'inverted', 'variableId' => 1, 'type' => 0, 'operator' => 'eq', 'threshold' => false, 'unit' => 'raw'];
expect((new Evaluator([], [], [$boolean]))->evaluate([row(1, false)])['warnings'], [1], 'explicit inverted Boolean');
expect((new Evaluator([], [], [$boolean]))->evaluate([row(1, 0)])['unknown'], [1], 'Boolean type mismatch');
expect((new Evaluator($profiles, [], [$boolean]))->evaluate([row(1, false, '~Battery')])['warnings'], [], 'legacy wins');
$boolean['overrideLegacy'] = true;
expect((new Evaluator($profiles, [], [$boolean]))->evaluate([row(1, false, '~Battery')])['warnings'], [1], 'explicit override');
$second = array_replace($boolean, ['name' => 'other']);
rejected(static function () use ($boolean, $second): void {
    (new Evaluator([], [], [$boolean, $second]))->evaluate([row(1, false)]);
}, 'conflicting rules');
foreach ([['variableId' => 0], ['type' => 3], ['unknownKey' => true], ['threshold' => 'false'], ['operator' => 'le']] as $invalid) {
    rejected(static function () use ($boolean, $invalid): void {
        new Evaluator([], [], [array_replace($boolean, $invalid)]);
    }, 'invalid explicit rule');
}
rejected(static function (): void {
    new Evaluator([], [0], []);
}, 'protected root');
rejected(static function (): void {
    (new Evaluator([], [], []))->evaluate([row(1, 0), row(1, 0)]);
}, 'duplicate input');
rejected(static function () use ($blink): void {
    new Evaluator([], [], array_merge($blink, $blink));
}, 'duplicate rule names');
rejected(static function () use ($zigbee): void {
    new Evaluator([], [], [array_replace($zigbee[0], ['threshold' => '2,2'])]);
}, 'numeric text not silently converted');
// Rust/Ninja: integer bounds must not silently accept float or numeric text.
foreach ([['MIN' => 0.0], ['MAX' => 200.0], ['MIN' => false], ['MAX' => '200']] as $invalid) {
    $bad = $z;
    $bad['presentation'] = array_replace($z['presentation'], $invalid);
    $r = $engine->evaluate([row(1, 5, '', $bad)]);
    expect($r['checked'], [1], 'invalid scale remains discoverable');
    expect($r['warnings'], [], 'invalid scale never fabricates battery alarm');
    expect($r['details'][0]['status'], 'invalid_scale_type', 'integer scale type');
}
foreach ([null, 'invalid', 42, false] as $presentation) {
    $bad = array_replace($extra, ['presentation' => $presentation]);
    expect((new Evaluator([], [], $blink))->evaluate([row(1, 1, '', $bad)])['unknown'], [1], 'malformed presentation');
}
foreach ([0, 1, 2, 3] as $value) {
    $r = (new Evaluator([], [], $blink))->evaluate([row(1, $value, '', array_replace($extra, ['updated' => 0]))]);
    expect($r['unknown'], [1], 'Blink never updated');
    expect($r['warnings'], [], 'Blink no-data is not an alarm');
}
$floatPercent = array_replace($floatRule, ['unit' => 'percent', 'threshold' => 10]);
$floatRow = row(1, 0.1, '', ['presentation' => ['MIN' => 0.0, 'MAX' => 1.0, 'PERCENTAGE' => true]]);
expect((new Evaluator([], [], [$floatPercent]))->evaluate([$floatRow])['warnings'], [1], 'float percentage at threshold');
echo "Profile Monitor evaluator: $count assertions passed\n";
