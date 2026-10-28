<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ManualOnPulseOffCore;

require_once __DIR__ . '/../../case-studies/control-light/candidate/ManualOnPulseOffCore.php';

function assertPulseCoreSame(mixed $expected, mixed $actual, string $message): void
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

$configuration = ManualOnPulseOffCore::normalizeConfiguration([
    'powerVariableID' => 10,
    'relayVariableID' => 11,
]);
assertPulseCoreSame(1.0, $configuration['powerOnThreshold'], 'Default threshold differs.');
assertPulseCoreSame(1_200, $configuration['observationMilliseconds'], 'Observation window differs.');
assertPulseCoreSame(false, ManualOnPulseOffCore::isLampOn(1.0, 1.0), 'Threshold is not strict.');
assertPulseCoreSame(true, ManualOnPulseOffCore::isLampOn(1.01, 1.0), 'On feedback was missed.');
assertPulseCoreSame(
    'manual_activation_required',
    ManualOnPulseOffCore::plan(true, false),
    'Manual-on plan differs.'
);
assertPulseCoreSame('already_confirmed', ManualOnPulseOffCore::plan(true, true), 'On idempotency differs.');
assertPulseCoreSame('pulse_off', ManualOnPulseOffCore::plan(false, true), 'Off plan differs.');
assertPulseCoreSame(
    'observe_before_idempotent_off',
    ManualOnPulseOffCore::plan(false, false),
    'Stale-off observation plan differs.'
);

assertPulseCoreSame(false, ManualOnPulseOffCore::isLampOn(5.99, 6.0, true), 'Below inclusive threshold.');
assertPulseCoreSame(true, ManualOnPulseOffCore::isLampOn(6.0, 6.0, true), 'Exact inclusive threshold.');
assertPulseCoreSame(true, ManualOnPulseOffCore::isLampOn(6.01, 6.0, true), 'Above inclusive threshold.');
assertPulseCoreSame(false, array_key_exists('powerOnThresholdInclusive', $configuration), 'Legacy shape drift.');
try {
    ManualOnPulseOffCore::normalizeConfiguration([
        'powerVariableID' => 10, 'relayVariableID' => 11, 'powerOnThresholdInclusive' => 'true',
    ]);
    throw new LogicException('Expected invalid inclusive option rejection.');
} catch (InvalidArgumentException $expected) {
}
echo "PASS: Manual-on pulse-off core contract.\n";
