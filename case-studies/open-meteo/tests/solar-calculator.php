<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use SAEF\CaseStudy\OpenMeteo\PvConfiguration;
use SAEF\CaseStudy\OpenMeteo\ResponseParser;
use SAEF\CaseStudy\OpenMeteo\SolarForecastCalculator;

$south = ResponseParser::parse(
    fixture('solar-south-success.json'),
    [],
    ['temperature_2m', 'global_tilted_irradiance'],
    []
);
$configuration = new PvConfiguration(
    [[
        'Ident' => 'South',
        'PeakPowerKw' => 10,
        'TiltDegrees' => 30,
        'AzimuthDegrees' => 0,
        'TemperatureCoefficientPctPerC' => -0.4,
        'NoctDeltaCAt800Wm2' => 25,
        'DerateFactor' => 0.9,
        'InverterIdent' => 'Main',
    ]],
    [['Ident' => 'Main', 'AcLimitKw' => 3, 'EfficiencyFactor' => 0.96]]
);
$power = SolarForecastCalculator::calculate(
    $configuration,
    ['30:0' => $south->hourly('global_tilted_irradiance')],
    $south->hourly('temperature_2m')
);
same(3, $power->count(), 'South power length differs.');
near(0.0, (float) $power->points()[0]->value(), 0.000001, 'Night output must be zero.');
near(3.0, (float) $power->points()[1]->value(), 0.000001, 'Inverter clipping differs.');
near(3.0, (float) $power->points()[2]->value(), 0.000001, 'Inverter clipping differs.');
$energy = SolarForecastCalculator::dailyEnergy($power, 'Europe/Berlin');
near(6.0, (float) $energy->points()[0]->value(), 0.000001, 'Duration-based energy differs.');

$east = ResponseParser::parse(
    fixture('solar-east-success.json'),
    [],
    ['temperature_2m', 'global_tilted_irradiance'],
    []
);
$west = ResponseParser::parse(
    fixture('solar-west-success.json'),
    [],
    ['temperature_2m', 'global_tilted_irradiance'],
    []
);
$arrays = [
    [
        'Ident' => 'East',
        'PeakPowerKw' => 6,
        'TiltDegrees' => 30,
        'AzimuthDegrees' => -90,
        'TemperatureCoefficientPctPerC' => -0.4,
        'NoctDeltaCAt800Wm2' => 25,
        'DerateFactor' => 0.9,
        'InverterIdent' => 'Shared',
    ],
    [
        'Ident' => 'West',
        'PeakPowerKw' => 6,
        'TiltDegrees' => 30,
        'AzimuthDegrees' => 90,
        'TemperatureCoefficientPctPerC' => -0.4,
        'NoctDeltaCAt800Wm2' => 25,
        'DerateFactor' => 0.9,
        'InverterIdent' => 'Shared',
    ],
];
$eastWest = new PvConfiguration(
    $arrays,
    [['Ident' => 'Shared', 'AcLimitKw' => 4, 'EfficiencyFactor' => 0.97]]
);
$orientationSeries = [
    '30:-90' => $east->hourly('global_tilted_irradiance'),
    '30:90' => $west->hourly('global_tilted_irradiance'),
];
$combined = SolarForecastCalculator::calculate(
    $eastWest,
    $orientationSeries,
    $east->hourly('temperature_2m')
);
check((float) $combined->points()[1]->value() <= 4.0, 'Shared inverter limit was exceeded.');

$reversed = new PvConfiguration(
    array_reverse($arrays),
    [['Ident' => 'Shared', 'AcLimitKw' => 4, 'EfficiencyFactor' => 0.97]]
);
$combinedReversed = SolarForecastCalculator::calculate(
    $reversed,
    array_reverse($orientationSeries, true),
    $east->hourly('temperature_2m')
);
foreach ($combined->points() as $index => $point) {
    near(
        (float) $point->value(),
        (float) $combinedReversed->points()[$index]->value(),
        0.000001,
        'Array order changed the result.'
    );
}

throws(
    static fn () => SolarForecastCalculator::calculate(
        $eastWest,
        ['30:-90' => $east->hourly('global_tilted_irradiance')],
        $east->hourly('temperature_2m')
    ),
    InvalidArgumentException::class,
    'Partial orientation set must be rejected.'
);
throws(
    static fn () => new PvConfiguration($arrays, []),
    InvalidArgumentException::class,
    'Missing inverter configuration must be rejected.'
);

echo "solar-calculator: ok\n";
