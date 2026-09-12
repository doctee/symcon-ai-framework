<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';
require_once __DIR__ . '/harness/LocalMapFixture.php';

final class MowingAnalyticsDevice extends NavimowDevice
{
    public int $now = 2000000;

    protected function currentTimestamp(): int
    {
        return $this->now;
    }
}

function assertMowingAnalyticsDevice(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$device = new MowingAnalyticsDevice(4601);
$device->Create();
$fixture = navimowLocalMapFixture($device->now);
$device->testSetProperty('DeviceId', 'SYNTHETIC_DEVICE');
$device->testSetProperty('EnableLocalMap', true);
$device->testSetProperty('EnableZoneStatistics', true);
$device->testSetProperty('EnableMowingAnalytics', true);
$device->testSetProperty('StatisticsTimeZone', 'UTC');
$device->testSetProperty('CuttingWidthMeters', 0.5);
$device->testSetProperty('CoverageCellSizeMeters', 0.25);
$device->testSetProperty(
    'AcceptedMapProjection',
    json_encode($fixture['package'], JSON_THROW_ON_ERROR)
);
$device->testSetProperty('AcceptedGeometryKey', $fixture['geometryKey']);
$device->ApplyChanges();

$definitions = $device->testVariableDefinitions();
$expected = [
    'MowingAnalyticsStatus' => [1, 'NAVIMOW.MowingAnalyticsState'],
    'MowingAnalyticsUpdatedAt' => [1, '~UnixTimestamp'],
    'LastRunDistance' => [2, 'NAVIMOW.Distance'],
    'LastRunDuration' => [1, 'NAVIMOW.Duration'],
    'LastRunEstimatedArea' => [2, 'NAVIMOW.Area'],
    'LastRunAreaPerformance' => [2, 'NAVIMOW.AreaPerformance'],
    'Zone101CoverageEstimate' => [2, 'NAVIMOW.Percentage'],
    'Zone101EstimatedAreaToday' => [2, 'NAVIMOW.Area'],
    'Zone101EstimatedAreaWeek' => [2, 'NAVIMOW.Area'],
    'Zone101EstimatedAreaMonth' => [2, 'NAVIMOW.Area'],
    'Zone101LastMowedAt' => [1, '~UnixTimestamp'],
    'Zone101MowingRecency' => [1, 'NAVIMOW.MowingRecencyState'],
    'Zone101LatestRunDistance' => [2, 'NAVIMOW.Distance'],
    'Zone101LatestRunDuration' => [1, 'NAVIMOW.Duration'],
];
foreach ($expected as $ident => [$type, $profile]) {
    assertMowingAnalyticsDevice(
        isset($definitions[$ident])
            && $definitions[$ident]['type'] === $type
            && $definitions[$ident]['profile'] === $profile,
        'Mowing analytics variable contract differs for ' . $ident . '.'
    );
}
assertMowingAnalyticsDevice(
    !isset($definitions['Zone104MowingRecency'])
        && $device->testVisualizationType() === 1,
    'Unbound-zone or HTML visualization contract differs.'
);

$device->testSetVariable('Online', true);
$device->testSetVariable('VehicleState', 1);
$device->testSetVariable('LastStatusUpdate', $device->now);
$device->testSetParentHandler(static fn (): string => json_encode(
    $fixture['evidence'],
    JSON_THROW_ON_ERROR
));
assertMowingAnalyticsDevice(
    $device->RefreshLocalMap() === 'Local map refresh succeeded.'
        && $device->testReadVariable('MowingAnalyticsStatus') === 2
        && $device->testReadVariable('MowingAnalyticsUpdatedAt')
            === $device->now
        && $device->testReadVariable('LastRunDistance') > 0.0
        && $device->testReadVariable('LastRunDuration') > 0
        && $device->testReadVariable('LastRunEstimatedArea') > 0.0
        && $device->testReadVariable('Zone101MowingRecency') === 1,
    'Fresh mowing analytics variables differ.'
);

$messages = $device->testVisualizationMessages();
$latestMessage = json_decode(
    $messages[array_key_last($messages)],
    true,
    64,
    JSON_THROW_ON_ERROR
);
assertMowingAnalyticsDevice(
    $latestMessage['action'] === 'render'
        && $latestMessage['theme'] === 'dark'
        && $latestMessage['analytics']['state'] === 'available'
        && str_contains($latestMessage['svg'], 'data-zone-id="101"')
        && str_contains($latestMessage['svg'], 'rotate(-188)'),
    'HTML SDK visualization message or station rotation differs.'
);

$tile = $device->GetVisualizationTile();
assertMowingAnalyticsDevice(
    str_starts_with($tile, '<!doctype html>')
        && strlen($tile) < 2 * 1024 * 1024
        && str_contains($tile, 'data-navimow-map')
        && str_contains($tile, 'touch-action: none')
        && str_contains($tile, 'window.handleMessage = handleMessage')
        && str_contains($tile, "addEventListener('wheel'")
        && str_contains($tile, "addEventListener('pointermove'")
        && str_contains($tile, "querySelectorAll('.zone[data-zone-id]')")
        && str_contains($tile, '@media (max-width: 680px)')
        && !str_contains($tile, 'SAEF_NAVIMOW_MAP_STYLE')
        && !str_contains($tile, 'SAEF_NAVIMOW_MAP_SCRIPT')
        && !str_contains($tile, 'SAEF_NAVIMOW_MAP_BOOTSTRAP')
        && str_ends_with($tile, "</html>\n")
        && !str_contains($tile, 'https://')
        && !str_contains($tile, 'http://')
        && !str_contains($tile, 'fetch(')
        && !str_contains($tile, 'XMLHttpRequest')
        && !str_contains($tile, 'WebSocket'),
    'HTML SDK tile assembly differs.'
);

$beforeDisable = $device->testVariableDefinitions();
$device->testSetProperty('EnableMowingAnalytics', false);
$device->testSetProperty('StatisticsTimeZone', 'Invalid/Zone');
$device->testSetProperty('StatisticsSubareas', '{invalid');
$device->ApplyChanges();
assertMowingAnalyticsDevice(
    $device->testReadVariable('MowingAnalyticsStatus') === 0
        && $device->testVariableDefinitions() === $beforeDisable
        && $device->RefreshLocalMap() === 'Local map refresh succeeded.',
    'Disabled analytics changed variables or blocked the existing map.'
);

echo "Navimow mowing analytics Device checks passed.\n";
