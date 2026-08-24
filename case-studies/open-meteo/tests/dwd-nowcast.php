<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/RequestBuilder.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/ResponseParser.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/ForecastProjector.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/NowcastHtmlRenderer.php';
require_once __DIR__ . '/../distribution/libs/DwdNowcast/TransportDiagnostics.php';

use SAEF\CaseStudy\DwdNowcast\ForecastProjector;
use SAEF\CaseStudy\DwdNowcast\NowcastHtmlRenderer;
use SAEF\CaseStudy\DwdNowcast\RequestBuilder;
use SAEF\CaseStudy\DwdNowcast\ResponseParser;
use SAEF\CaseStudy\DwdNowcast\TransportDiagnostics;

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
    str_contains($chart, 'style="box-sizing:border-box;width:100%;padding:0 6px'),
    'DWD chart root is not styled inline.'
);
same(
    true,
    str_contains($chart, '-webkit-text-size-adjust:100%'),
    'DWD chart does not stabilize mobile text sizing.'
);
same(true, str_contains($chart, 'font:11px/1.35'), 'DWD chart base font is not compact.');
same(true, str_contains($chart, 'height:14px'), 'DWD chart bars are not compact.');
same(true, str_contains($chart, 'font-size:9px'), 'DWD chart axis is not compact.');
same(true, str_contains($chart, 'margin:0 0 1px'), 'DWD chart headline spacing is too tall.');
same(true, str_contains($chart, 'margin-top:1px'), 'DWD chart axis spacing is too tall.');
preg_match_all('/background:(#[0-9a-f]{6})/', $chart, $chartColors);
same(60, count($chartColors[1]), 'DWD chart color count differs.');
same(
    array_fill(0, 15, '#4b5563'),
    array_slice($chartColors[1], 0, 15),
    'DWD chart color starts before the authoritative rain interval.'
);
same('#ffd600', $chartColors[1][15], 'DWD chart color does not start with the rain interval.');
same(
    '#4b5563',
    NowcastHtmlRenderer::colorForIntensity(0.099, 0.1),
    'DWD below-threshold color differs.'
);
same(
    '#1677ff',
    NowcastHtmlRenderer::colorForIntensity(0.1, 0.1),
    'DWD threshold color differs.'
);
same(
    '#00c853',
    NowcastHtmlRenderer::colorForIntensity(0.5, 0.1),
    'DWD green color band differs.'
);
same(
    '#e00000',
    NowcastHtmlRenderer::colorForIntensity(8.0, 0.1),
    'DWD heavy-rain color differs.'
);

same(
    TransportDiagnostics::CLASS_TLS_RECORD,
    TransportDiagnostics::classifyWarning(
        'Failure when receiving data from the peer: OpenSSL SSL_read: error:0A0001BB:SSL routines::bad record type'
    ),
    'DWD TLS record warning classification differs.'
);
same(
    TransportDiagnostics::CLASS_DNS_TIMEOUT,
    TransportDiagnostics::classifyWarning('Timeout was reached: Resolving timed out after 10010 milliseconds'),
    'DWD DNS warning classification differs.'
);
same(
    TransportDiagnostics::CLASS_TLS_HANDSHAKE,
    TransportDiagnostics::classifyWarning(
        'SSL connect error: TLS connect error: error:0A0000D9:SSL routines::unsolicited extension'
    ),
    'DWD TLS handshake warning classification differs.'
);
same(
    TransportDiagnostics::CLASS_HTTP_SERVER_ERROR,
    TransportDiagnostics::classifyWarning('Error 503, upstream service temporarily unavailable'),
    'DWD HTTP server warning classification differs.'
);
same(
    null,
    TransportDiagnostics::classifyWarning('Error 404, provider contract not found'),
    'DWD non-retryable HTTP warning was classified as a server outage.'
);
same(
    TransportDiagnostics::CLASS_TIMEOUT,
    TransportDiagnostics::classifyWarning('Timeout was reached: Operation timed out with 0 bytes received'),
    'DWD timeout warning classification differs.'
);
same(null, TransportDiagnostics::classifyWarning('Unrelated PHP warning'), 'Unknown warning was classified.');
$transportDiagnostics = TransportDiagnostics::fromJson('');
$transportDiagnostics = TransportDiagnostics::failure(
    $transportDiagnostics,
    $productTime,
    TransportDiagnostics::CLASS_TLS_RECORD
);
same(1, $transportDiagnostics['failureCount'], 'DWD transport failure count differs.');
same(1, $transportDiagnostics['consecutiveFailures'], 'DWD consecutive transport count differs.');
$transportDiagnostics = TransportDiagnostics::success($transportDiagnostics, $productTime + 60);
same(0, $transportDiagnostics['consecutiveFailures'], 'DWD transport recovery did not reset failures.');
same(1, $transportDiagnostics['lastRecoveryAttempts'], 'DWD transport recovery count differs.');
same(
    $transportDiagnostics,
    TransportDiagnostics::fromJson(TransportDiagnostics::toJson($transportDiagnostics)),
    'DWD transport diagnostics round-trip differs.'
);

$dry = $projected60;
foreach ($dry['windowPoints'] as &$point) {
    $point['intensityMmPerHour'] = 0.0;
}
unset($point);
$dry['summary']['rainExpected'] = false;
$dry['summary']['rainStartsInMinutes'] = -1;
$dryChart = NowcastHtmlRenderer::render($dry, 'Europe/Berlin', $chartLabels);
same(true, str_contains($dryChart, 'No rain in 60 min'), 'DWD dry chart headline differs.');
$trace = $dry;
$trace['windowPoints'][0]['intensityMmPerHour'] = 0.019;
$trace['summary']['precipitationSumMm'] = 0.002;
$trace['summary']['maximumIntensityMmPerHour'] = 0.019;
$trace['summary']['nextIntervalIntensityMmPerHour'] = 0.019;
$traceChart = NowcastHtmlRenderer::render($trace, 'Europe/Berlin', $chartLabels);
same(true, str_contains($traceChart, 'No rain in 60 min'), 'DWD trace headline differs.');
same(60, substr_count($traceChart, 'background:#4b5563'), 'DWD trace was colored as rain.');
same(
    true,
    str_contains($traceChart, 'data-tip="+0 min: 0.02 mm/h"'),
    'DWD trace value is missing from the tooltip.'
);
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
