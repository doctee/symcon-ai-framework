<?php

declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\HueWallSwitchRuntime;

require_once __DIR__ . '/HueWallSwitchRuntime.php';

/*
 * Installation-specific IDs belong in a private deployment overlay.
 * The target IDs must address the local ControlLight STATE facades, not the
 * native Zigbee or Hue device variables.
 */
$configuration = [
    'version' => 'HueWallSwitch-v2-candidate',
    'debounceMilliseconds' => 300,
    'confirmation' => [
        'timeoutMilliseconds' => 500,
        'pollIntervalMilliseconds' => 50,
    ],
    'semaphore' => [
        'timeoutMilliseconds' => 4000,
    ],
    'targets' => [
        'globe' => [
            'name' => 'Globe light',
            'stateVariableID' => 10001,
        ],
        'ceiling' => [
            'name' => 'Kitchen ceiling light',
            'stateVariableID' => 10002,
        ],
    ],
    'sources' => [
        [
            'key' => 'north',
            'name' => 'Hue wall module north',
            'sourceVariableID' => 10003,
            'swapLeftRight' => true,
            'invertTopBottom' => false,
            'leftTargetKey' => 'globe',
            'rightTargetKey' => 'ceiling',
        ],
        [
            'key' => 'south',
            'name' => 'Hue wall module south',
            'sourceVariableID' => 10004,
            'swapLeftRight' => true,
            'invertTopBottom' => true,
            'leftTargetKey' => 'globe',
            'rightTargetKey' => 'ceiling',
        ],
    ],
];

HueWallSwitchRuntime::run((int) $_IPS['SELF'], $_IPS, $configuration);
