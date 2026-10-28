<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCore;
use SAEF\CaseStudy\ControlLight\CwwwLightAdapter;

require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightCore.php';
require_once __DIR__ . '/../../case-studies/control-light/candidate/CwwwLightAdapter.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$reject = static function (callable $operation) use ($assert): void {
    try {
        $operation();
    } catch (InvalidArgumentException) {
        $assert(true, 'Rejected');
        return;
    }
    $assert(false, 'Invalid input accepted');
};
$adapter = new CwwwLightAdapter(2650, 4800);
$configuration = ControlLightCore::normalizeConfiguration([
    'brightnessSemantics' => 'reported', 'identState' => 'state',
    'identDim' => 'brightness', 'identTemp' => 'color_temp_kelvin',
    'dimmerTargetMax' => 255,
    'brightnessOffStateTransition' => ['mode' => 'target-turns-on'],
]);
for ($percent = 1; $percent <= 100; $percent++) {
    $native = ControlLightCore::localToTarget('brightness', $percent, $configuration);
    $payload = $adapter->command('brightness', $native);
    $assert($payload === ['state' => 'ON', 'brightness' => $native, 'transition' => 0], 'Combined direct dim');
    $feedback = $adapter->feedback($payload);
    $assert(ControlLightCore::targetToLocal('brightness', $feedback['brightness'], $configuration, false) === $percent, 'Retained percent mapping');
}
for ($kelvin = 2650; $kelvin <= 4800; $kelvin++) {
    $payload = $adapter->command('colorTemperature', $kelvin);
    $assert(!array_key_exists('state', $payload) && !array_key_exists('brightness', $payload), 'Kelvin must not replace power/brightness');
    $observed = $adapter->feedback($payload)['colorTemperature'];
    $assert(abs($observed - $kelvin) <= 5, 'Byte quantization within existing confirmation tolerance');
}
$assert($adapter->command('state', true) === ['state' => 'ON'], 'ON retains white mix');
$assert($adapter->command('brightness', 0) === ['state' => 'OFF'], 'OFF retains device brightness');
$assert($adapter->feedback(['state' => 'OFF', 'brightness' => 128]) === ['state' => false, 'brightness' => 128], 'Reported not effective');
$assert($adapter->feedback(['state' => 'OFF']) === ['state' => false], 'Missing brightness not fabricated');
$assert($adapter->feedback(['color_mode' => 'cwww', 'color' => ['c' => 0, 'w' => 0]]) === [], 'Zero mix has unknown Kelvin');
$assert($adapter->feedback(['color_mode' => 'cwww', 'color' => ['c' => 23, 'w' => 233]])['colorTemperature'] === 2843, 'Observed legacy mix');
foreach ([-1, 256, '128', 1.5, true, null] as $bad) {
    $reject(static fn() => $adapter->command('brightness', $bad));
    $reject(static fn() => $adapter->feedback(['brightness' => $bad]));
}
foreach ([2649, 4801, '3000', null] as $bad) {
    $reject(static fn() => $adapter->command('colorTemperature', $bad));
}
foreach (
    [['state' => 'TOGGLE'], ['state' => false], ['color' => ['c' => 1, 'w' => 2]],
        ['color_mode' => 'cwww', 'color' => ['c' => 1]], ['brightness' => 10, 'state' => null]] as $bad
) {
    $reject(static fn() => $adapter->feedback($bad));
}
$reject(static fn() => $adapter->command('color', 1));
$reject(static fn() => new CwwwLightAdapter(4800, 2650));
$assert($adapter->command('brightness', 26) === $adapter->command('brightness', 26), 'Deterministic command');
$complete = '{"state":"OFF","brightness":128,"color_mode":"cwww","color":{"c":23,"w":233}}';
$initial = $adapter->initialFeedback($complete);
$assert($initial === ['state' => false, 'brightness' => 128, 'colorTemperature' => 2843], 'One coherent initial packet');
$assert(ControlLightCore::targetToLocal('brightness', $initial['brightness'], $configuration, false) === 50, 'Adopt native to percent while OFF');
$assert($adapter->initialFeedback($complete) === $initial, 'Repeated adoption calculation is idempotent');
$assert($adapter->feedbackJson('{"state":"OFF"}') === ['state' => false], 'No attributes retained inside parser');
$assert($adapter->feedbackJson('{}') === [], 'Empty object invents nothing');
$assert($adapter->feedbackJson('{"effect":"None","brightness":0}') === ['brightness' => 0], 'Unknown fields ignored; true zero retained');
foreach ([true, false] as $on) {
    foreach ([0, 26, 128, 255] as $brightness) {
        $packet = json_encode(['state' => $on ? 'ON' : 'OFF', 'brightness' => $brightness,
            'color_mode' => 'cwww', 'color' => ['c' => 255, 'w' => 0]], JSON_THROW_ON_ERROR);
        $observed = $adapter->initialFeedback($packet);
        $assert($observed['state'] === $on && $observed['brightness'] === $brightness, 'Initial snapshot keeps power independent');
        $assert($observed['colorTemperature'] === 4800, 'Same-packet endpoint');
    }
}
foreach (
    [
    '', 'null', '[]', 'true', '42', '"ON"', '{broken', str_repeat(' ', 8193),
    str_repeat('{"nested":', 10) . '{}' . str_repeat('}', 10),
    '{"state":"ON","color_mode":"cwww","color":[]}',
    '{"state":"ON","brightness":256}',
    '{"state":"OFF","brightness":"128"}',
    '{"color_mode":"cwww","color":{"c":255}}',
    '{"color_mode":"cwww","color":{"w":255}}',
    ] as $badPacket
) {
    $reject(static fn() => $adapter->feedbackJson($badPacket));
}
// A rejected half-packet cannot contribute a channel to the next packet.
$assert($adapter->feedbackJson('{"color_mode":"cwww","color":{"c":0,"w":255}}') === ['colorTemperature' => 2650], 'No cross-packet C/W merge');
foreach (
    [
    '{}', '{"state":"ON"}', '{"state":"OFF","brightness":128}',
    '{"state":"OFF","brightness":128,"color_mode":"cwww","color":{"c":0,"w":0}}',
    ] as $partial
) {
    $reject(static fn() => $adapter->initialFeedback($partial));
}
echo "CWWW adapter: $checks assertions passed; no live calls.\n";
