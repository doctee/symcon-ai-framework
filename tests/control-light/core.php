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

    return $configuration;
}

$tests = [];

$tests['normalizes every installed instance contract'] = static function (): void {
    $fixturePath = __DIR__ . '/fixtures/installed-contracts.json';
    $fixture = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
    assertControlLightSame(29, $fixture['capturedInstanceCount'], 'Captured instance count differs.');
    assertControlLightSame(29, count($fixture['instances']), 'Fixture row count differs.');

    $presetCounts = ['Z2M' => 0, 'MATTER' => 0, 'HOMEMATIC' => 0];
    $variants = [];
    $externalTriggerCount = 0;
    $pendingSemantics = 0;
    $reportedSemantics = 0;
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
        $presetCounts[$instance['preset']]++;
        $variants[$instance['variant']] = true;
        $externalTriggerCount += $instance['externalTriggers'];
        if ($instance['brightnessSemantics'] === 'pending') {
            $pendingSemantics++;
        }
        if ($instance['brightnessSemantics'] === 'reported') {
            $reportedSemantics++;
        }
    }

    assertControlLightSame(['Z2M' => 25, 'MATTER' => 3, 'HOMEMATIC' => 1], $presetCounts, 'Preset counts differ.');
    assertControlLightSame(16, count($variants), 'Configuration variant count differs.');
    assertControlLightSame(4, $externalTriggerCount, 'External trigger count differs.');
    assertControlLightSame(22, $pendingSemantics, 'Per-instance brightness decisions must remain explicit.');
    assertControlLightSame(7, $reportedSemantics, 'Activated reported-semantics count differs.');
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
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    fwrite(STDOUT, 'PASS: ' . $name . PHP_EOL);
}
fwrite(STDOUT, sprintf('PASS: %d ControlLight core tests.%s', $passed, PHP_EOL));
