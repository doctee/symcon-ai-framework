<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use SAEF\CaseStudy\OpenMeteo\PvConfiguration;
use SAEF\CaseStudy\OpenMeteo\RequestBuilder;

$configuration = [
    'latitude' => 48.1234567,
    'longitude' => 11.7654321,
    'elevation' => 520,
    'timezone' => 'Europe/Berlin',
    'forecastDays' => 5,
    'withSoil' => true,
];

$first = RequestBuilder::weather($configuration);
$second = RequestBuilder::weather($configuration);
same($first, $second, 'Weather request must be deterministic.');
check(str_starts_with($first, RequestBuilder::DWD_ICON_ENDPOINT . '?latitude='), 'Endpoint differs.');
check(str_contains($first, 'timezone=Europe%2FBerlin'), 'Timezone must be encoded.');
check(str_contains($first, 'soil_moisture_27_to_81cm'), 'Soil profile is incomplete.');
check(strpos($first, 'current=') < strpos($first, 'hourly='), 'Query ordering differs.');

$solar = RequestBuilder::solar($configuration, 30.0, -90.0);
check(str_contains($solar, 'tilt=30'), 'Solar tilt is missing.');
check(str_contains($solar, 'azimuth=-90'), 'Solar azimuth is missing.');
check(
    str_contains($solar, 'temperature_2m%2Cglobal_tilted_irradiance'),
    'Solar field profile differs.'
);

throws(
    static fn (): string => RequestBuilder::weather(array_merge($configuration, ['latitude' => 91])),
    InvalidArgumentException::class,
    'Invalid latitude must be rejected.'
);
throws(
    static fn (): string => RequestBuilder::weather(array_merge($configuration, ['forecastDays' => 11])),
    InvalidArgumentException::class,
    'Unsupported weather horizon must be rejected.'
);
throws(
    static fn (): string => RequestBuilder::weather(array_merge($configuration, ['timezone' => 'Private/Site'])),
    InvalidArgumentException::class,
    'Invalid timezone must be rejected.'
);

$pv = new PvConfiguration(
    [
        [
            'Ident' => 'West',
            'PeakPowerKw' => 4,
            'TiltDegrees' => 30,
            'AzimuthDegrees' => 90,
            'TemperatureCoefficientPctPerC' => -0.4,
            'NoctDeltaCAt800Wm2' => 25,
            'DerateFactor' => 0.9,
            'InverterIdent' => 'Main',
        ],
        [
            'Ident' => 'East',
            'PeakPowerKw' => 4,
            'TiltDegrees' => 30.0,
            'AzimuthDegrees' => -90.0,
            'TemperatureCoefficientPctPerC' => -0.4,
            'NoctDeltaCAt800Wm2' => 25,
            'DerateFactor' => 0.9,
            'InverterIdent' => 'Main',
        ],
        [
            'Ident' => 'EastSecond',
            'PeakPowerKw' => 2,
            'TiltDegrees' => 30.0000001,
            'AzimuthDegrees' => -90,
            'TemperatureCoefficientPctPerC' => -0.4,
            'NoctDeltaCAt800Wm2' => 25,
            'DerateFactor' => 0.9,
            'InverterIdent' => 'Main',
        ],
    ],
    [['Ident' => 'Main', 'AcLimitKw' => 8, 'EfficiencyFactor' => 0.96]]
);
same(2, count($pv->uniqueOrientations()), 'Equivalent orientations must share a request.');

echo "request-builder: ok\n";
