<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/RequestBuilder.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/ResponseParser.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/ForecastProjector.php';

use SAEF\CaseStudy\DwdNowcast\ForecastProjector;
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
