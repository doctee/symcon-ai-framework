<?php

declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\HueWallSwitchCore;

require_once __DIR__ . '/../../case-studies/control-light/candidate/HueWallSwitchCore.php';

function assertHueWallSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s.',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

/** @return array<string, mixed> */
function hueWallCoreConfiguration(): array
{
    return [
        'targets' => [
            'globe' => ['name' => 'Globe', 'stateVariableID' => 101],
            'ceiling' => ['name' => 'Ceiling', 'stateVariableID' => 102],
        ],
        'sources' => [
            [
                'key' => 'north',
                'sourceVariableID' => 201,
                'swapLeftRight' => true,
                'invertTopBottom' => false,
                'leftTargetKey' => 'globe',
                'rightTargetKey' => 'ceiling',
            ],
            [
                'key' => 'south',
                'sourceVariableID' => 202,
                'swapLeftRight' => true,
                'invertTopBottom' => true,
                'leftTargetKey' => 'globe',
                'rightTargetKey' => 'ceiling',
            ],
        ],
    ];
}

$configuration = HueWallSwitchCore::normalizeConfiguration(hueWallCoreConfiguration());

assertHueWallSame(
    4000,
    $configuration['semaphore']['timeoutMilliseconds'],
    'Bounded serialization default differs.'
);
assertHueWallSame(
    'ceiling',
    HueWallSwitchCore::targetKeyForAction($configuration['sources']['north'], 'left_press_release'),
    'Physical-to-logical side swap differs.'
);
assertHueWallSame(
    'globe',
    HueWallSwitchCore::targetKeyForAction(
        $configuration['sources']['south'],
        'Rechts drücken und loslassen'
    ),
    'Localized action mapping differs.'
);
assertHueWallSame(
    null,
    HueWallSwitchCore::targetKeyForAction($configuration['sources']['north'], 'left_hold'),
    'Irrelevant action was not ignored.'
);
assertHueWallSame(true, HueWallSwitchCore::desiredState(false), 'Off state was not toggled on.');
assertHueWallSame(false, HueWallSwitchCore::desiredState(true), 'On state was not toggled off.');

$northDesired = HueWallSwitchCore::desiredState(false);
$southDesired = HueWallSwitchCore::desiredState(false);
assertHueWallSame(
    $northDesired,
    $southDesired,
    'Legacy top/bottom inversion metadata unexpectedly changed toggle semantics.'
);

try {
    $duplicate = hueWallCoreConfiguration();
    $duplicate['targets']['ceiling']['stateVariableID'] = 101;
    HueWallSwitchCore::normalizeConfiguration($duplicate);
    throw new RuntimeException('Duplicate target variable ID was accepted.');
} catch (InvalidArgumentException) {
}

try {
    $unknown = hueWallCoreConfiguration();
    $unknown['sources'][0]['leftTargetKey'] = 'missing';
    HueWallSwitchCore::normalizeConfiguration($unknown);
    throw new RuntimeException('Unknown target key was accepted.');
} catch (InvalidArgumentException) {
}

fwrite(STDOUT, 'PASS: Hue Wall pure mapping and configuration contract.' . PHP_EOL);
