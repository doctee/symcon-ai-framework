<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCore;

require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightCore.php';

function assertControlLightSame(mixed $expected, mixed $actual, string $message): void
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

/** @param class-string<Throwable> $expected */
function assertControlLightThrows(string $expected, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $expected) {
            return;
        }
        throw new RuntimeException($message . ' Wrong exception: ' . $exception::class);
    }
    throw new RuntimeException($message . ' Expected exception was not thrown.');
}

/** @return array<string, mixed> */
function controlLightFixtureConfiguration(array $fixture): array
{
    $configuration = [
        'preset' => $fixture['preset'],
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ];
    $mapping = [
        'state' => 'identState',
        'brightness' => 'identDim',
        'colorTemperature' => 'identTemp',
        'color' => 'identColor',
    ];
    foreach ($mapping as $capability => $key) {
        if (!in_array($capability, $fixture['capabilities'], true)) {
            $configuration[$key] = '';
        }
    }
    if (isset($fixture['dimmerTargetMax'])) {
        $configuration['dimmerTargetMax'] = $fixture['dimmerTargetMax'];
    }
    if (isset($fixture['stateCommandMode'])) {
        $configuration['stateCommandMode'] = $fixture['stateCommandMode'];
    }
    foreach (['identState', 'identDim', 'identTemp', 'identColor'] as $identKey) {
        if (isset($fixture[$identKey])) {
            $configuration[$identKey] = $fixture[$identKey];
        }
    }

    return $configuration;
}

$tests = [];

$tests['normalizes asymmetric state command modes'] = static function (): void {
    $default = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(
        ControlLightCore::STATE_COMMAND_BIDIRECTIONAL,
        $default['stateCommandMode'],
        'Default state command mode differs.'
    );

    $offOnly = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        'stateCommandMode' => ControlLightCore::STATE_COMMAND_OFF_ONLY,
    ]);
    assertControlLightSame(
        ControlLightCore::STATE_COMMAND_OFF_ONLY,
        $offOnly['stateCommandMode'],
        'Off-only state command mode differs.'
    );

    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'stateCommandMode' => 'manual',
        ]),
        'Unknown state command mode was accepted.'
    );
};

$tests['normalizes every installed instance contract'] = static function (): void {
    $fixturePath = __DIR__ . '/fixtures/installed-contracts.json';
    $fixture = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
    assertControlLightSame(29, $fixture['capturedInstanceCount'], 'Captured instance count differs.');
    assertControlLightSame(29, count($fixture['instances']), 'Fixture row count differs.');
    assertControlLightSame(
        ['CL-001', 'CL-007', 'CL-008', 'CL-009', 'CL-013', 'CL-016', 'CL-020', 'CL-024', 'CL-025', 'CL-028'],
        $fixture['consumerContracts']['voiceAssistant']['reportedFacadePowerAndBrightness'] ?? null,
        'Reported voice-assistant facade contracts differ.'
    );
    assertControlLightSame(
        ['CL-008', 'CL-009', 'CL-013', 'CL-020', 'CL-025', 'CL-028'],
        $fixture['consumerContracts']['voiceAssistant']['echoRemoteTextCommandTested'] ?? null,
        'Echo Remote text-command test contracts differ.'
    );
    assertControlLightSame(
        ['CL-024'],
        $fixture['consumerContracts']['voiceAssistant']['echoRemoteAlarmBlockTested'] ?? null,
        'Echo Remote alarm-block contracts differ.'
    );
    assertControlLightSame(
        ['CL-030'],
        $fixture['consumerContracts']['voiceAssistant']['asymmetricStateOnlyFacade'] ?? null,
        'Asymmetric voice-assistant facade contracts differ.'
    );
    assertControlLightSame(
        ['CL-030'],
        $fixture['consumerContracts']['voiceAssistant']['alexaImmediateOffRaceTested'] ?? null,
        'Alexa immediate-off race contracts differ.'
    );
    assertControlLightSame(
        [],
        $fixture['consumerContracts']['voiceAssistant']['legacyBrightnessOnlyPendingMigration'] ?? null,
        'Pending legacy voice-assistant contracts differ.'
    );

    $presetCounts = ['Z2M' => 0, 'MATTER' => 0, 'HOMEMATIC' => 0];
    $variants = [];
    $externalTriggerCount = 0;
    $pendingSemantics = 0;
    $reportedSemantics = 0;
    $cl001 = null;
    $cl002 = null;
    $cl003 = null;
    $cl007 = null;
    $cl008 = null;
    $cl009 = null;
    $cl011 = null;
    $cl015 = null;
    $cl016 = null;
    $cl020 = null;
    $cl021 = null;
    $cl024 = null;
    $cl030 = null;
    foreach ($fixture['instances'] as $instance) {
        $normalized = ControlLightCore::normalizeConfiguration(
            controlLightFixtureConfiguration($instance)
        );
        $enabled = [];
        foreach ($normalized['capabilities'] as $capability => $definition) {
            if ($definition['enabled'] === true) {
                $enabled[] = $capability;
            }
        }
        assertControlLightSame($instance['capabilities'], $enabled, $instance['key'] . ' capability contract differs.');
        if (isset($instance['identState'])) {
            assertControlLightSame(
                $instance['identState'],
                $normalized['capabilities']['state']['targetIdent'],
                $instance['key'] . ' state target Ident differs.'
            );
        }
        assertControlLightSame(
            $instance['preset'] === 'Z2M'
                ? ControlLightCore::TEMPERATURE_QUANTIZATION_MIRED
                : ControlLightCore::TEMPERATURE_QUANTIZATION_NONE,
            $normalized['colorTemperatureFeedbackQuantization'],
            $instance['key'] . ' temperature feedback quantization differs.'
        );
        $presetCounts[$instance['preset']]++;
        $variants[$instance['variant']] = true;
        $externalTriggerCount += $instance['externalTriggers'];
        if ($instance['brightnessSemantics'] === 'pending') {
            $pendingSemantics++;
        }
        if ($instance['brightnessSemantics'] === 'reported') {
            $reportedSemantics++;
        }
        if ($instance['key'] === 'CL-021') {
            $cl021 = $instance;
        }
        if ($instance['key'] === 'CL-001') {
            $cl001 = $instance;
        }
        if ($instance['key'] === 'CL-002') {
            $cl002 = $instance;
        }
        if ($instance['key'] === 'CL-003') {
            $cl003 = $instance;
        }
        if ($instance['key'] === 'CL-008') {
            $cl008 = $instance;
        }
        if ($instance['key'] === 'CL-009') {
            $cl009 = $instance;
        }
        if ($instance['key'] === 'CL-011') {
            $cl011 = $instance;
        }
        if ($instance['key'] === 'CL-020') {
            $cl020 = $instance;
        }
        if ($instance['key'] === 'CL-015') {
            $cl015 = $instance;
        }
        if ($instance['key'] === 'CL-016') {
            $cl016 = $instance;
        }
        if ($instance['key'] === 'CL-007') {
            $cl007 = $instance;
        }
        if ($instance['key'] === 'CL-024') {
            $cl024 = $instance;
        }
        if ($instance['key'] === 'CL-030') {
            $cl030 = $instance;
        }
    }

    assertControlLightSame(['Z2M' => 26, 'MATTER' => 2, 'HOMEMATIC' => 1], $presetCounts, 'Preset counts differ.');
    assertControlLightSame(16, count($variants), 'Configuration variant count differs.');
    assertControlLightSame(4, $externalTriggerCount, 'External trigger count differs.');
    assertControlLightSame(3, $pendingSemantics, 'Per-instance brightness decisions must remain explicit.');
    assertControlLightSame(26, $reportedSemantics, 'Decided reported-semantics count differs.');
    assertControlLightSame(
        'active-away-safe-alarm-preserved-alexa-expert-pending-presence-test',
        $cl001['dependencies'] ?? null,
        'CL-001 activation gate differs.'
    );
    assertControlLightSame(
        'active-away-safe-external-on-off-triggers-preserved-pending-presence-test',
        $cl002['dependencies'] ?? null,
        'CL-002 activation gate differs.'
    );
    assertControlLightSame(
        'active-all-enabled-capabilities-direct-tested-hard-cycle-and-immediate-recovery-passed-color-disabled-native-color-retained-shutdown-consumer-facade-aligned-links-native-observer-preserved-mired-matcher-live-3900-to-3906-kelvin-authoritative-feedback-passed',
        $cl003['dependencies'] ?? null,
        'CL-003 activation and remaining gates differ.'
    );
    assertControlLightSame(
        'active-fully-device-tested-autooff-expiry-passed-and-links',
        $cl007['dependencies'] ?? null,
        'CL-007 dependency gate differs.'
    );
    assertControlLightSame(
        [
            'feedbackAuthority' => 'member-confirmed',
            'memberCount' => 2,
            'passiveState' => 'any-member-on',
            'commandState' => 'all-configured-members-match',
            'brightness' => 'reported-group-level-not-member-average',
            'confirmation' => 'shared-deadline-parallel',
            'partialFailure' => 'fail-closed',
        ],
        $cl008['groupContract'] ?? null,
        'CL-008 group contract differs.'
    );
    assertControlLightSame(
        'reported',
        $cl008['brightnessSemantics'] ?? null,
        'CL-008 active brightness contract differs.'
    );
    assertControlLightSame(
        'active-fully-device-tested-autooff-expiry-passed',
        $cl008['dependencies'] ?? null,
        'CL-008 dependency gate differs.'
    );
    assertControlLightSame(255, $cl009['dimmerTargetMax'] ?? null, 'CL-009 target brightness scale differs.');
    assertControlLightSame(
        'active-fully-device-tested-state-brightness-temperature-color-disabled-alexa-color-binding-removed-native-color-and-diagnostics-retained',
        $cl016['dependencies'] ?? null,
        'CL-016 migration and remaining functional gate differ.'
    );
    assertControlLightSame(
        'reported',
        $cl016['brightnessSemantics'] ?? null,
        'CL-016 reported brightness contract differs.'
    );
    assertControlLightSame(
        'active-fully-device-tested-autooff-facade-alexa-state-brightness-and-spoken-temperature-passed-textcommand-temperature-rejected-scene-and-warning-observer-preserved',
        $cl009['dependencies'] ?? null,
        'CL-009 dependency gate differs.'
    );
    assertControlLightSame(
        [
            'feedbackAuthority' => 'member-confirmed',
            'memberCount' => 3,
            'passiveState' => 'any-member-on',
            'commandState' => 'all-configured-members-match',
            'brightness' => 'reported-group-level-not-member-average',
            'colorTemperature' => 'all-configured-members-match',
            'confirmation' => 'shared-deadline-parallel',
            'partialFailure' => 'fail-closed',
        ],
        $cl011['groupContract'] ?? null,
        'CL-011 group contract differs.'
    );
    assertControlLightSame(
        'active-command-free-three-member-group-random-lighting-removed-color-disabled-shutdown-consumers-facade-aligned-pending-presence-test',
        $cl011['dependencies'] ?? null,
        'CL-011 activation gate differs.'
    );
    assertControlLightSame(
        'active-fully-device-tested-off-state-target-turns-on-alexa-passed-scene-structural',
        $cl020['dependencies'] ?? null,
        'CL-020 activation gate differs.'
    );
    assertControlLightSame(
        [
            'feedbackAuthority' => 'member-confirmed',
            'memberCount' => 3,
            'passiveState' => 'any-member-on',
            'commandState' => 'all-configured-members-match',
            'brightness' => 'reported-group-level-not-member-average',
            'colorTemperature' => 'all-configured-members-match',
            'confirmation' => 'shared-deadline-parallel',
            'partialFailure' => 'fail-closed',
        ],
        $cl015['groupContract'] ?? null,
        'CL-015 group contract differs.'
    );
    assertControlLightSame(
        'direct-target-brightness-writer-state-observer-and-color-disabled-until-target-module-repair',
        $cl021['dependencies'] ?? null,
        'CL-021 disabled color contract differs.'
    );
    assertControlLightSame(
        'active-fully-device-tested-alexa-alarm-block-passed-and-links',
        $cl024['dependencies'] ?? null,
        'CL-024 activation gate differs.'
    );
    assertControlLightSame(
        'active-fully-device-tested-consumers-aligned-ha-apple-identity-preserved-alexa-row-preserved-munich-alarm-contract-powerdelta-five-watt-three-second-race-window',
        $cl030['dependencies'] ?? null,
        'CL-030 activation gate differs.'
    );
    assertControlLightSame(
        ControlLightCore::STATE_COMMAND_OFF_ONLY,
        $cl030['stateCommandMode'] ?? null,
        'CL-030 asymmetric command mode differs.'
    );
};

$tests['keeps reported and effective brightness semantics distinct'] = static function (): void {
    $reported = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    $effective = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_EFFECTIVE,
    ]);

    assertControlLightSame(42, ControlLightCore::targetToLocal('brightness', 42, $reported, false), 'Reported brightness differs.');
    assertControlLightSame(0, ControlLightCore::targetToLocal('brightness', 42, $effective, false), 'Effective off brightness differs.');
    assertControlLightSame(42, ControlLightCore::targetToLocal('brightness', 42, $effective, true), 'Effective on brightness differs.');
};

$tests['scales Matter brightness in both directions'] = static function (): void {
    $configuration = ControlLightCore::normalizeConfiguration([
        'preset' => 'MATTER',
        'dimmerTargetMax' => 255,
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(128, ControlLightCore::localToTarget('brightness', 50, $configuration), 'Matter target brightness differs.');
    assertControlLightSame(50, ControlLightCore::targetToLocal('brightness', 128, $configuration, true), 'Matter local brightness differs.');
};

$tests['round-trips configured color formats'] = static function (): void {
    foreach (['INT_HEX', 'RGB_ARRAY_STRING', 'RGB_OBJECT_STRING', 'HS_ARRAY_STRING'] as $format) {
        $configuration = ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'colorTargetFormat' => $format,
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        ]);
        // HS_ARRAY_STRING carries hue and saturation only; use a full-value color.
        $target = ControlLightCore::localToTarget('color', 0x3366FF, $configuration);
        $roundTrip = ControlLightCore::targetToLocal('color', $target, $configuration);
        assertControlLightSame(0x3366FF, $roundTrip, 'Color round-trip differs for ' . $format . '.');
    }
};

$tests['uses bounded target comparison tolerances'] = static function (): void {
    $configuration = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(true, ControlLightCore::targetValueMatches('brightness', 50, 51, $configuration), 'Brightness tolerance differs.');
    assertControlLightSame(false, ControlLightCore::targetValueMatches('brightness', 50, 52, $configuration), 'Brightness mismatch was accepted.');
    assertControlLightSame(true, ControlLightCore::targetValueMatches('colorTemperature', 2600, 2604, $configuration), 'Temperature tolerance differs.');
    assertControlLightSame(false, ControlLightCore::targetValueMatches('colorTemperature', 2600, 2606, $configuration), 'Temperature mismatch was accepted.');

    $normalizedDeviceConfiguration = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        'colorTemperatureTolerance' => 10,
    ]);
    assertControlLightSame(
        true,
        ControlLightCore::targetValueMatches(
            'colorTemperature',
            3500,
            3508,
            $normalizedDeviceConfiguration
        ),
        'Configured temperature normalization tolerance was ignored.'
    );
    assertControlLightSame(
        false,
        ControlLightCore::targetValueMatches(
            'colorTemperature',
            3500,
            3511,
            $normalizedDeviceConfiguration
        ),
        'Configured temperature normalization tolerance accepted an out-of-contract value.'
    );

    $matter = ControlLightCore::normalizeConfiguration([
        'preset' => 'MATTER',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(
        0.5,
        $matter['colorHueToleranceDegrees'],
        'Default hue tolerance differs.'
    );
    assertControlLightSame(
        0.5,
        $matter['colorSaturationTolerancePercentagePoints'],
        'Default saturation tolerance differs.'
    );
    assertControlLightSame(
        [
            'mode' => 'unchanged',
            'hueToleranceDegrees' => 0.5,
            'saturationTolerancePercentagePoints' => 0.5,
        ],
        $matter['colorOffStateTransition'],
        'Default off-state color transition differs.'
    );
    assertControlLightSame(
        true,
        ControlLightCore::targetValueMatches('color', '[220,75]', '[219.685,74.803]', $matter),
        'Bounded native-HS target normalization was rejected.'
    );
    assertControlLightSame(
        false,
        ControlLightCore::targetValueMatches('color', '[220,75]', '[219.4,74.803]', $matter),
        'Out-of-bound native-HS target normalization was accepted.'
    );
};

$tests['matches mired-quantized Kelvin feedback only for configured targets'] = static function (): void {
    $z2m = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(
        ControlLightCore::TEMPERATURE_QUANTIZATION_MIRED,
        $z2m['colorTemperatureFeedbackQuantization'],
        'Z2M temperature quantization differs.'
    );
    assertControlLightSame(
        true,
        ControlLightCore::targetValueMatches('colorTemperature', 3900, 3906, $z2m),
        'Equivalent 256-mired Kelvin feedback was rejected.'
    );
    assertControlLightSame(
        false,
        ControlLightCore::targetValueMatches('colorTemperature', 3900, 3922, $z2m),
        'Different-mired Kelvin feedback was accepted.'
    );

    for ($requestedKelvin = 2000; $requestedKelvin <= 6500; $requestedKelvin++) {
        $deviceMired = (int)round(1000000 / $requestedKelvin);
        $reportedKelvin = (int)round(1000000 / $deviceMired);
        assertControlLightSame(
            true,
            ControlLightCore::targetValueMatches(
                'colorTemperature',
                $requestedKelvin,
                $reportedKelvin,
                $z2m
            ),
            'Mired round-trip feedback differs at ' . (string)$requestedKelvin . ' K.'
        );
    }

    $matter = ControlLightCore::normalizeConfiguration([
        'preset' => 'MATTER',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(
        ControlLightCore::TEMPERATURE_QUANTIZATION_NONE,
        $matter['colorTemperatureFeedbackQuantization'],
        'Matter unexpectedly selected mired feedback quantization.'
    );
    assertControlLightSame(
        false,
        ControlLightCore::targetValueMatches('colorTemperature', 3900, 3906, $matter),
        'Matter accepted Z2M-specific mired quantization.'
    );

    $z2mWithoutQuantization = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        'colorTemperatureFeedbackQuantization' => ControlLightCore::TEMPERATURE_QUANTIZATION_NONE,
    ]);
    assertControlLightSame(
        false,
        ControlLightCore::targetValueMatches(
            'colorTemperature',
            3900,
            3906,
            $z2mWithoutQuantization
        ),
        'Explicitly disabled mired quantization was ignored.'
    );
};

$tests['normalizes optional availability without making it a capability'] = static function (): void {
    $z2m = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(true, $z2m['availability']['enabled'], 'Z2M availability is disabled.');
    assertControlLightSame('device_status', $z2m['availability']['targetIdent'], 'Z2M availability Ident differs.');
    assertControlLightSame(true, ControlLightCore::availabilityValueMatches(true, $z2m), 'Available Z2M value differs.');
    assertControlLightSame(false, ControlLightCore::availabilityValueMatches(false, $z2m), 'Offline Z2M value differs.');

    $matter = ControlLightCore::normalizeConfiguration([
        'preset' => 'MATTER',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
    ]);
    assertControlLightSame(false, $matter['availability']['enabled'], 'Unconfigured Matter availability is enabled.');
};

$tests['normalizes explicit member-confirmed group feedback'] = static function (): void {
    $configuration = ControlLightCore::normalizeConfiguration([
        'preset' => 'Z2M',
        'identTemp' => '',
        'identColor' => '',
        'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
        'groupFeedback' => [
            'mode' => ControlLightCore::FEEDBACK_MEMBER_CONFIRMED,
            'freshnessSeconds' => 900,
            'brightnessTolerance' => 2,
            'members' => [
                [
                    'key' => 'member-1',
                    'stateVariableID' => 101,
                    'brightnessVariableID' => 102,
                    'availabilityVariableID' => 103,
                    'lastSeenVariableID' => 104,
                ],
                [
                    'key' => 'member-2',
                    'stateVariableID' => 201,
                    'brightnessVariableID' => 202,
                    'availabilityVariableID' => 203,
                    'lastSeenVariableID' => 204,
                ],
            ],
        ],
    ]);

    assertControlLightSame(true, $configuration['groupFeedback']['enabled'], 'Group feedback is disabled.');
    assertControlLightSame(2, count($configuration['groupFeedback']['members']), 'Member count differs.');
    assertControlLightSame(2, $configuration['groupFeedback']['brightnessTolerance'], 'Tolerance differs.');
};

$tests['rejects ambiguous or unbounded configuration'] = static function (): void {
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration(['preset' => 'Z2M']),
        'Missing brightness semantics were accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => 'pending',
        ]),
        'Pending brightness semantics reached normalized runtime configuration.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'colorTemperatureTolerance' => 101,
        ]),
        'Unbounded color-temperature tolerance was accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'colorTemperatureFeedbackQuantization' => 'dynamic',
        ]),
        'Unknown color-temperature feedback quantization was accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'tempTargetIsMired' => true,
        ]),
        'Kelvin feedback quantization was accepted for a mired-valued target.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'MATTER',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'colorHueToleranceDegrees' => 5.001,
        ]),
        'Unbounded hue tolerance was accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'MATTER',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'colorSaturationTolerancePercentagePoints' => '0.5',
        ]),
        'Non-numeric saturation tolerance was accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'colorOffStateTransition' => [
                'mode' => 'target-turns-on',
                'hueToleranceDegrees' => 2.0,
                'saturationTolerancePercentagePoints' => 0.5,
            ],
        ]),
        'HS-only off-state transition was accepted for an RGB target.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'MATTER',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'colorOffStateTransition' => [
                'mode' => 'target-turns-on',
                'hueToleranceDegrees' => 5.001,
                'saturationTolerancePercentagePoints' => 0.5,
            ],
        ]),
        'Unbounded off-state hue tolerance was accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'confirmation' => ['timeoutMilliseconds' => 100, 'pollIntervalMilliseconds' => 200],
        ]),
        'Invalid confirmation bounds were accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'authoritativeFeedback' => false,
        ]),
        'Non-authoritative v2 mode was accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'availability' => [
                'targetIdent' => 'device_status',
                'targetType' => 0,
                'availableValue' => 'online',
            ],
        ]),
        'Availability value with the wrong variable type was accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'groupFeedback' => [
                'mode' => ControlLightCore::FEEDBACK_MEMBER_CONFIRMED,
                'members' => [
                    [
                        'key' => 'duplicate',
                        'stateVariableID' => 101,
                        'brightnessVariableID' => 102,
                        'availabilityVariableID' => 103,
                        'lastSeenVariableID' => 104,
                    ],
                    [
                        'key' => 'duplicate',
                        'stateVariableID' => 201,
                        'brightnessVariableID' => 202,
                        'availabilityVariableID' => 203,
                        'lastSeenVariableID' => 204,
                    ],
                ],
            ],
        ]),
        'Duplicate group member keys were accepted.'
    );
    assertControlLightThrows(
        InvalidArgumentException::class,
        static fn(): array => ControlLightCore::normalizeConfiguration([
            'preset' => 'Z2M',
            'identDim' => '',
            'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
            'groupFeedback' => [
                'mode' => ControlLightCore::FEEDBACK_MEMBER_CONFIRMED,
                'members' => [
                    [
                        'key' => 'member-1',
                        'stateVariableID' => 101,
                        'brightnessVariableID' => 102,
                        'availabilityVariableID' => 103,
                        'lastSeenVariableID' => 104,
                    ],
                    [
                        'key' => 'member-2',
                        'stateVariableID' => 201,
                        'brightnessVariableID' => 202,
                        'availabilityVariableID' => 203,
                        'lastSeenVariableID' => 204,
                    ],
                ],
            ],
        ]),
        'Member-confirmed mode without brightness was accepted.'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    fwrite(STDOUT, 'PASS: ' . $name . PHP_EOL);
}
fwrite(STDOUT, sprintf('PASS: %d ControlLight core tests.%s', $passed, PHP_EOL));
