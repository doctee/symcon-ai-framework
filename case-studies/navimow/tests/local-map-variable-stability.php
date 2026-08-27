<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';

function assertLocalMapVariables(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$device = new NavimowDevice(4301);
$device->Create();
$device->ApplyChanges();
$first = $device->testVariableDefinitions();
$device->ApplyChanges();
$second = $device->testVariableDefinitions();
$expected = [
    'VehicleState' => [1, 'NAVIMOW.VehicleState', 10],
    'Online' => [0, '', 20],
    'BatteryLevel' => [1, '~Intensity.100', 30],
    'LastStatusUpdate' => [1, '~UnixTimestamp', 40],
    'LastCommand' => [1, 'NAVIMOW.Command', 50],
    'LastCommandAt' => [1, '~UnixTimestamp', 60],
    'LastCommandResult' => [1, 'NAVIMOW.CommandResult', 70],
    'LastCommandError' => [3, '', 80],
];
foreach ($expected as $ident => [$type, $profile, $position]) {
    assertLocalMapVariables(
        isset($first[$ident], $second[$ident])
            && $first[$ident] === $second[$ident]
            && $first[$ident]['type'] === $type
            && $first[$ident]['profile'] === $profile
            && $first[$ident]['position'] === $position,
        'Existing variable contract changed for ' . $ident . '.'
    );
}
assertLocalMapVariables(
    count($second) === count($first)
        && isset($second['LocalMap'])
        && $second['LocalMap']['type'] === 3
        && $second['LocalMap']['profile'] === '~HTMLBox'
        && $second['LocalMap']['position'] === 100,
    'Additive LocalMap variable is not stable.'
);

echo "Navimow local-map variable stability checks passed.\n";
