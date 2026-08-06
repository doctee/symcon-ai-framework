<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/RequestBuilder.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/ResponseParser.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/ForecastProjector.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/NowcastHtmlRenderer.php';

use SAEF\CaseStudy\DwdNowcast\ForecastProjector;
use SAEF\CaseStudy\DwdNowcast\NowcastHtmlRenderer;
use SAEF\CaseStudy\DwdNowcast\RequestBuilder;
use SAEF\CaseStudy\DwdNowcast\ResponseParser;

$productTime = 1735718400;
$url = RequestBuilder::build(48.0, 11.0, $productTime + 8 * 60);
$parts = parse_url($url);
if (!is_array($parts) || !isset($parts['query'])) {
    throw new RuntimeException('DWD request URL is invalid.');
}
$query = [];
parse_str((string) $parts['query'], $query);
same('GetFeatureInfo', $query['REQUEST'] ?? null, 'DWD WMS operation differs.');
same('dwd:Niederschlagsradar', $query['LAYERS'] ?? null, 'DWD WMS layer differs.');
same('current', $query['REFERENCE_TIME'] ?? null, 'DWD reference-time selection differs.');
same(
    '2025-01-01T07:55:00Z/2025-01-01T10:15:00Z/PT5M',
    $query['TIME'] ?? null,
    'DWD bounded time interval differs.'
);

$features = [];
for ($lead = 5; $lead <= 120; $lead += 5) {
    $intensity = $lead >= 20 && $lead <= 30 ? 1.2 : -0.001;
    $features[] = dwdFeature($productTime, $productTime + ($lead * 60), $intensity);
}
$features[] = dwdFeature($productTime - 300, $productTime, 8.0);
$body = json_encode(
    ['type' => 'FeatureCollection', 'features' => $features],
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
);
$parsed = ResponseParser::parse($body);
same($productTime, $parsed['productTime'], 'DWD product time differs.');
same(24, count($parsed['points']), 'DWD native forecast point count differs.');
same(5, $parsed['resolutionMinutes'], 'DWD native resolution differs.');
same(0.0, $parsed['points'][0]['intensityMmPerHour'], 'DWD negative-zero mapping differs.');
near(0.1, $parsed['points'][3]['accumulationMm'], 0.000001, 'DWD interval amount differs.');

$projected = ForecastProjector::project($parsed, 30, 0.1, $productTime + 8 * 60);
same(6, count($projected['windowPoints']), 'DWD evaluation window differs.');
same(true, $projected['summary']['rainExpected'], 'DWD rain expectation differs.');
same(15, $projected['summary']['rainStartsInMinutes'], 'DWD rain start differs.');
same(30, $projected['summary']['rainEndsInMinutes'], 'DWD rain end differs.');
near(0.3, $projected['summary']['precipitationSumMm'], 0.000001, 'DWD window sum differs.');
near(1.2, $projected['summary']['maximumIntensityMmPerHour'], 0.000001, 'DWD maximum differs.');

$projected60 = ForecastProjector::project($parsed, 60, 0.1, $productTime + 8 * 60);
$chartLabels = [
    'rainIn' => 'Rain in %d min',
    'noRain' => 'No rain in %d min',
    'now' => 'now',
    'minuteTooltip' => '+%d min: %.2f mm/h',
    'noData' => 'No nowcast data',
];
$chart = NowcastHtmlRenderer::render($projected60, 'Europe/Berlin', $chartLabels);
same(60, substr_count($chart, ' data-tip="'), 'DWD chart minute count differs.');
same(true, str_contains($chart, '09:00'), 'DWD chart local product time differs.');
same(true, str_contains($chart, 'Rain in 15 min'), 'DWD chart headline differs.');
same(true, str_contains($chart, '+30 min'), 'DWD chart midpoint differs.');
same(true, str_contains($chart, '+60 min'), 'DWD chart endpoint differs.');
same(true, str_contains($chart, 'data-tip="+0 min: 0.00 mm/h"'), 'DWD chart tooltip differs.');
same(true, str_contains($chart, ':hover::after'), 'DWD chart tooltip CSS is missing.');
same(true, str_contains($chart, 'pointer-events:none'), 'DWD chart tooltip captures the pointer.');
same(
    true,
    str_contains($chart, 'style="box-sizing:border-box;width:100%;padding:6px'),
    'DWD chart root is not styled inline.'
);
same(true, str_contains($chart, 'font:11px/1.35'), 'DWD chart base font is not compact.');
same(true, str_contains($chart, 'height:14px'), 'DWD chart bars are not compact.');
same(true, str_contains($chart, 'font-size:9px'), 'DWD chart axis is not compact.');
same(true, str_contains($chart, '#00c853'), 'DWD chart absolute green band is missing.');
same('#4b5563', NowcastHtmlRenderer::colorForIntensity(0.0), 'DWD dry color differs.');
same('#e00000', NowcastHtmlRenderer::colorForIntensity(8.0), 'DWD heavy-rain color differs.');

$dry = $projected60;
foreach ($dry['windowPoints'] as &$point) {
    $point['intensityMmPerHour'] = 0.0;
}
unset($point);
$dry['summary']['rainExpected'] = false;
$dry['summary']['rainStartsInMinutes'] = -1;
$dryChart = NowcastHtmlRenderer::render($dry, 'Europe/Berlin', $chartLabels);
same(true, str_contains($dryChart, 'No rain in 60 min'), 'DWD dry chart headline differs.');
same(
    true,
    str_contains(NowcastHtmlRenderer::renderEmpty($chartLabels), 'No nowcast data'),
    'DWD empty chart differs.'
);

$incomplete = $features;
array_pop($incomplete);
array_pop($incomplete);
throws(
    static fn (): array => ResponseParser::parse(json_encode(
        ['type' => 'FeatureCollection', 'features' => $incomplete],
        JSON_THROW_ON_ERROR
    )),
    InvalidArgumentException::class,
    'Incomplete DWD horizon was accepted.'
);
throws(
    static fn (): array => ForecastProjector::project($parsed, 61, 0.1, $productTime),
    InvalidArgumentException::class,
    'Non-native DWD evaluation window was accepted.'
);

echo "dwd-nowcast: ok\n";

/** @return array<string, mixed> */
function dwdFeature(int $referenceTime, int $validTime, float $intensity): array
{
    return [
        'type' => 'Feature',
        'geometry' => null,
        'properties' => [
            'RV_ANALYSIS' => $intensity,
            'TIME' => gmdate('Y-m-d\TH:i:s\Z', $validTime),
            'REFERENCE_TIME' => gmdate('Y-m-d\TH:i:s\Z', $referenceTime),
        ],
    ];
}
