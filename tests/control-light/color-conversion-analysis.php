<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCore;

require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightCore.php';

function assertColorAnalysisSame(mixed $expected, mixed $actual, string $message): void
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
function assertColorAnalysisThrows(string $expected, callable $operation, string $message): void
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

function circularHueDistance(float $expected, float $actual): float
{
    $linear = abs($expected - $actual);
    return min($linear, 360.0 - $linear);
}

/** @return array{hue: float, saturation: float, value: float} */
function rgbIntegerToHsv(int $color): array
{
    $red = (($color >> 16) & 0xFF) / 255;
    $green = (($color >> 8) & 0xFF) / 255;
    $blue = ($color & 0xFF) / 255;
    $maximum = max($red, $green, $blue);
    $minimum = min($red, $green, $blue);
    $delta = $maximum - $minimum;
    $hue = 0.0;

    if ($delta !== 0.0) {
        if ($maximum === $red) {
            $hue = 60.0 * fmod((($green - $blue) / $delta), 6.0);
        } elseif ($maximum === $green) {
            $hue = 60.0 * ((($blue - $red) / $delta) + 2.0);
        } else {
            $hue = 60.0 * ((($red - $green) / $delta) + 4.0);
        }
    }
    if ($hue < 0.0) {
        $hue += 360.0;
    }

    return [
        'hue' => $hue,
        'saturation' => $maximum === 0.0 ? 0.0 : ($delta / $maximum) * 100.0,
        'value' => $maximum * 100.0,
    ];
}

$matter = ControlLightCore::normalizeConfiguration([
    'preset' => 'MATTER',
    'dimmerTargetMax' => 255,
    'brightnessSemantics' => ControlLightCore::BRIGHTNESS_REPORTED,
]);
$cl020RequestedRgb = 0x3366CC;
$cl020ExpectedHs = ControlLightCore::localToTarget('color', $cl020RequestedRgb, $matter);
assertColorAnalysisSame('[220,75]', $cl020ExpectedHs, 'CL-020 expected HS projection differs.');
assertColorAnalysisSame(
    true,
    ControlLightCore::targetValueMatches('color', $cl020ExpectedHs, '[219.685,74.803]', $matter),
    'The bounded native-HS matcher rejected the observed CL-020 normalization.'
);
assertColorAnalysisSame(
    false,
    ControlLightCore::targetValueMatches('color', $cl020ExpectedHs, '[219.4,74.803]', $matter),
    'The native-HS matcher accepted an out-of-bound hue deviation.'
);
assertColorAnalysisSame(
    false,
    ControlLightCore::targetValueMatches('color', $cl020ExpectedHs, '[219.685,74.4]', $matter),
    'The native-HS matcher accepted an out-of-bound saturation deviation.'
);
assertColorAnalysisSame(
    true,
    ControlLightCore::targetValueMatches('color', '[359.8,75]', '[0.2,75]', $matter),
    'Circular hue comparison failed at the 360-degree boundary.'
);
assertColorAnalysisSame(
    true,
    ControlLightCore::targetValueMatches('color', '[0,0]', '[271,0.4]', $matter),
    'Achromatic hue should be ignored inside the saturation boundary.'
);
assertColorAnalysisSame(
    false,
    ControlLightCore::targetValueMatches('color', '[0,0.6]', '[180,0.6]', $matter),
    'Hue must remain relevant outside the achromatic saturation boundary.'
);
assertColorAnalysisSame(
    true,
    ControlLightCore::targetValueMatches('color', '[120,50]', '[120.5,49.5]', $matter),
    'An exact-boundary HS normalization was rejected.'
);
assertColorAnalysisSame(
    false,
    ControlLightCore::targetValueMatches('color', '[120,50]', '[120.501,50]', $matter),
    'An over-boundary hue normalization was accepted.'
);

$representativeHues = [
    0xFF0000 => '[0,100]',
    0xFFFF00 => '[60,100]',
    0x00FF00 => '[120,100]',
    0x00FFFF => '[180,100]',
    0x0000FF => '[240,100]',
    0xFF00FF => '[300,100]',
];
foreach ($representativeHues as $rgb => $expectedHs) {
    $projected = ControlLightCore::localToTarget('color', $rgb, $matter);
    assertColorAnalysisSame($expectedHs, $projected, 'Representative hue projection differs.');
    $decodedExpected = json_decode($expectedHs, true, 512, JSON_THROW_ON_ERROR);
    $hue = (float)$decodedExpected[0];
    $normalized = json_encode(
        [fmod($hue + 0.4, 360.0), 99.6],
        JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
    assertColorAnalysisSame(
        true,
        ControlLightCore::targetValueMatches('color', $expectedHs, $normalized, $matter),
        'Representative bounded hue normalization was rejected.'
    );
}

assertColorAnalysisThrows(
    InvalidArgumentException::class,
    static fn(): bool => ControlLightCore::targetValueMatches('color', '[220,75]', 'not-json', $matter),
    'Malformed HS JSON was accepted.'
);
assertColorAnalysisThrows(
    InvalidArgumentException::class,
    static fn(): bool => ControlLightCore::targetValueMatches('color', '[220,75]', '[220]', $matter),
    'Incomplete HS JSON was accepted.'
);
assertColorAnalysisThrows(
    InvalidArgumentException::class,
    static fn(): bool => ControlLightCore::targetValueMatches('color', '[220,75]', '[220,101]', $matter),
    'Out-of-domain saturation was accepted.'
);

$cl020CanonicalRgb = ControlLightCore::targetToLocal('color', $cl020ExpectedHs, $matter);
assertColorAnalysisSame(0x4080FF, $cl020CanonicalRgb, 'CL-020 canonical chromaticity RGB differs.');
$cl020RequestedHsv = rgbIntegerToHsv($cl020RequestedRgb);
$cl020CanonicalHsv = rgbIntegerToHsv($cl020CanonicalRgb);
assertColorAnalysisSame(80.0, round($cl020RequestedHsv['value'], 3), 'Requested RGB value differs.');
assertColorAnalysisSame(100.0, round($cl020CanonicalHsv['value'], 3), 'Canonical RGB value differs.');

$cl021RequestedHsv = rgbIntegerToHsv(0x3366FF);
$cl021FeedbackHsv = rgbIntegerToHsv(0x00E7FF);
assertColorAnalysisSame(
    false,
    circularHueDistance($cl021RequestedHsv['hue'], $cl021FeedbackHsv['hue']) <= 0.5
        && abs($cl021RequestedHsv['saturation'] - $cl021FeedbackHsv['saturation']) <= 0.5,
    'The native-HS candidate must not accept the observed CL-021 color shift.'
);
assertColorAnalysisSame(
    false,
    0x3366FF === 0x0060FF,
    'The reproduced Z2M module round-trip unexpectedly retained exact RGB.'
);

echo "PASS: ControlLight color conversion analysis\n";
