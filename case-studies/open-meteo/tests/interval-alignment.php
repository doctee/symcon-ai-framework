<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use SAEF\CaseStudy\OpenMeteo\FieldCatalog;
use SAEF\CaseStudy\OpenMeteo\ForecastPoint;
use SAEF\CaseStudy\OpenMeteo\ForecastSeries;
use SAEF\CaseStudy\OpenMeteo\IntervalAligner;

$spring = IntervalAligner::localDayBounds('2025-03-30', 'Europe/Berlin');
same(23 * 3600, $spring['to'] - $spring['from'], 'Spring DST day must have 23 hours.');
$autumn = IntervalAligner::localDayBounds('2025-10-26', 'Europe/Berlin');
same(25 * 3600, $autumn['to'] - $autumn['from'], 'Autumn DST day must have 25 hours.');

$leftPoint = new ForecastPoint(
    'precipitation',
    'mm',
    FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
    7200,
    3600,
    7200,
    1.0
);
$rightExact = new ForecastPoint(
    'ac_power',
    'kW',
    FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
    7200,
    3600,
    7200,
    2.0
);
$rightShifted = new ForecastPoint(
    'ac_power',
    'kW',
    FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
    10800,
    7200,
    10800,
    3.0
);
$left = new ForecastSeries('precipitation', 'mm', [$leftPoint]);
$right = new ForecastSeries('ac_power', 'kW', [$rightExact, $rightShifted]);
same(1, count(IntervalAligner::exactIntervals($left, $right)), 'Only exact intervals may align.');
same($leftPoint, IntervalAligner::containing($left, 4000), 'Containing interval differs.');
same(null, IntervalAligner::containing($left, 7200), 'Interval end must be exclusive.');

throws(
    static fn () => IntervalAligner::localDayBounds('2025-02-30', 'Europe/Berlin'),
    InvalidArgumentException::class,
    'Invalid local date must be rejected.'
);

echo "interval-alignment: ok\n";
