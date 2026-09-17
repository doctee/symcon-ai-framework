<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use SAEF\CaseStudy\OpenMeteo\FieldCatalog;
use SAEF\CaseStudy\OpenMeteo\ForecastPoint;
use SAEF\CaseStudy\OpenMeteo\ForecastSeries;
use SAEF\CaseStudy\OpenMeteo\LocalHorizonModel;
use SAEF\CaseStudy\OpenMeteo\LocalHorizonProfile;

$interpolated = new LocalHorizonProfile([0, 10, 20, 30, 40, 50, 60, 70]);
near(5.0, $interpolated->elevationAt(22.5), 0.000001, 'Horizon interpolation differs.');
near(35.0, $interpolated->elevationAt(-22.5), 0.000001, 'Horizon wrap interpolation differs.');
same(8, count($interpolated->values()), 'Normalized horizon point count differs.');

// NREL SPA reference case, Golden, Colorado, 2003-10-17 12:30:30 MST.
// The runtime intentionally uses a lighter NOAA-style approximation without
// pressure/refraction inputs, so the acceptance tolerance is 0.05 degrees.
$referenceProfile = new LocalHorizonProfile(array_fill(0, 360, 0.0));
$referenceModel = new LocalHorizonModel($referenceProfile, 39.742476, -105.1786);
$solarPositionMethod = new ReflectionMethod($referenceModel, 'solarPosition');
$referencePosition = $solarPositionMethod->invoke($referenceModel, 1066419030.0);
near(
    39.888378,
    $referencePosition['elevationDegrees'],
    0.05,
    'Solar elevation differs from the NREL SPA reference case.'
);
near(
    194.340241,
    $referencePosition['azimuthDegrees'],
    0.05,
    'Solar azimuth differs from the NREL SPA reference case.'
);

throws(
    static fn () => LocalHorizonProfile::fromJson('{}'),
    InvalidArgumentException::class,
    'Non-list horizon JSON must be rejected.'
);
throws(
    static fn () => new LocalHorizonProfile([0, 1, 2, 3, 4, 5, 6]),
    InvalidArgumentException::class,
    'Short horizon profile must be rejected.'
);
throws(
    static fn () => new LocalHorizonProfile([0, 1, 2, 3, 4, 5, 6, 91]),
    InvalidArgumentException::class,
    'Invalid horizon elevation must be rejected.'
);

$sourceTimestamp = 1782043200;
$gti = new ForecastSeries('global_tilted_irradiance', 'W/m²', [
    new ForecastPoint(
        'global_tilted_irradiance',
        'W/m²',
        FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
        $sourceTimestamp,
        $sourceTimestamp - 3600,
        $sourceTimestamp,
        700.0
    ),
]);
$dni = new ForecastSeries('direct_normal_irradiance', 'W/m²', [
    new ForecastPoint(
        'direct_normal_irradiance',
        'W/m²',
        FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
        $sourceTimestamp,
        $sourceTimestamp - 3600,
        $sourceTimestamp,
        600.0
    ),
]);

$openProfile = new LocalHorizonProfile(array_fill(0, 360, 0.0));
$blockedProfile = new LocalHorizonProfile(array_fill(0, 360, 90.0));
$open = (new LocalHorizonModel($openProfile, 48.0, 11.0))->adjustTiltedIrradiance(
    $gti,
    $dni,
    40.0,
    0.0
);
$blocked = (new LocalHorizonModel($blockedProfile, 48.0, 11.0))->adjustTiltedIrradiance(
    $gti,
    $dni,
    40.0,
    0.0
);
near(700.0, (float) $open->points()[0]->value(), 0.000001, 'Open horizon changed GTI.');
check(
    (float) $blocked->points()[0]->value() >= 0.0
        && (float) $blocked->points()[0]->value() < 700.0,
    'Blocked horizon did not remove the direct plane component.'
);
same(
    $gti->points()[0]->validFrom(),
    $blocked->points()[0]->validFrom(),
    'Local horizon changed interval bounds.'
);

$misalignedDni = new ForecastSeries('direct_normal_irradiance', 'W/m²', [
    new ForecastPoint(
        'direct_normal_irradiance',
        'W/m²',
        FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
        $sourceTimestamp,
        $sourceTimestamp - 1800,
        $sourceTimestamp,
        600.0
    ),
]);
throws(
    static fn () => (new LocalHorizonModel($openProfile, 48.0, 11.0))
        ->adjustTiltedIrradiance($gti, $misalignedDni, 40.0, 0.0),
    InvalidArgumentException::class,
    'Misaligned direct irradiance must be rejected.'
);

echo "local-horizon-model: ok\n";
