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

$form = json_decode(
    file_get_contents(__DIR__ . '/../distribution/NavimowDevice/form.json'),
    true,
    64,
    JSON_THROW_ON_ERROR
);
$formElements = [];
foreach ($form['elements'] as $element) {
    if (is_array($element) && is_string($element['name'] ?? null)) {
        $formElements[$element['name']] = $element;
    }
}
foreach (['MetersPerLocalUnit', 'CoverageCellSizeMeters'] as $name) {
    assertMowingAnalyticsDevice(
        ($formElements[$name]['type'] ?? null) === 'NumberSpinner'
            && ($formElements[$name]['minimum'] ?? null) === 0
            && is_int($formElements[$name]['maximum'] ?? null),
        'Decimal analytics spinner metadata differs for ' . $name . '.'
    );
}

$device = new MowingAnalyticsDevice(4601);
$device->Create();
$fixture = navimowLocalMapFixture($device->now);
$device->testSetProperty('DeviceId', 'SYNTHETIC_DEVICE');
$device->testSetProperty('EnableLocalMap', true);
$device->testSetProperty('EnableZoneStatistics', true);
$device->testSetProperty('EnableMowingAnalytics', true);
$device->testSetProperty('StatisticsTimeZone', 'UTC');
$device->testSetProperty('CuttingWidthMeters', 0.43);
$device->testSetProperty('CoverageCellSizeMeters', 0.1);
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
        && count($latestMessage['statistics']) === 3
        && $latestMessage['statistics'][0]['zoneId'] === 101
        && $latestMessage['statistics'][0]['passProgressPercent'] === 100.0
        && $latestMessage['statistics'][0]['observedArea'] === 90.0
        && $latestMessage['statistics'][0]['latestRunEndedAt']
            === $latestMessage['analytics']['zones'][0]['latestRun']['endedAt']
        && str_contains($latestMessage['svg'], 'data-zone-id="101"')
        && str_contains($latestMessage['svg'], 'rotate(-7)'),
    'HTML SDK visualization message or station rotation differs.'
);

$tile = $device->GetVisualizationTile();
assertMowingAnalyticsDevice(
    str_starts_with($tile, '<!doctype html>')
        && strlen($tile) < 2 * 1024 * 1024
        && str_contains($tile, 'data-navimow-map')
        && str_contains($tile, 'touch-action: none')
        && str_contains($tile, '--nav-statistics-height: 0px')
        && str_contains($tile, 'inset: 0 0 var(--nav-statistics-height) 0')
        && str_contains($tile, 'top: 6px')
        && str_contains($tile, 'touch-action: manipulation')
        && str_contains($tile, 'height: 30px')
        && str_contains($tile, 'left: 16px')
        && str_contains($tile, 'right: auto')
        && str_contains($tile, 'alignLegendRight')
        && str_contains($tile, 'viewportEdgeInset = 6')
        && str_contains($tile, "querySelector('.legend[data-anchor-x][data-anchor-y]')")
        && str_contains($tile, 'const counterScale = fitScale / currentScale')
        && str_contains($tile, 'legend.getBBox()')
        && str_contains($tile, 'matrix.inverse()')
        && str_contains($tile, "') scale(' + counterScale + ')'")
        && str_contains($tile, 'scheduleStatisticsLayout')
        && str_contains($tile, 'updateMowerTimeLabel')
        && str_contains($tile, 'ageSeconds <= 60')
        && str_contains($tile, "root.style.setProperty('--nav-statistics-height'")
        && str_contains($tile, "Array.isArray(payload.statistics)")
        && str_contains($tile, "'aktuell'")
        && str_contains($tile, "'Woche '")
        && str_contains($tile, 'data-statistics-details')
        && str_contains($tile, 'details.showModal()')
        && str_contains($tile, 'zone.passCoveragePercent')
        && str_contains($tile, "'Stand '")
        && str_contains($tile, 'function zonePresentation')
        && str_contains($tile, 'box.x + box.width / 2')
        && str_contains($tile, "element.getAttribute('stroke')")
        && str_contains($tile, "row.style.setProperty('--nav-zone-color'")
        && str_contains($tile, 'row.dataset.zoneId')
        && str_contains($tile, 'left.centerX - right.centerX')
        && str_contains($tile, '--nav-panel-solid: #20262b')
        && str_contains($tile, '.nav-map[data-theme="light"]')
        && !str_contains($tile, '<select data-zone')
        && !str_contains($tile, 'zoneSelect')
        && !str_contains($tile, '.nav-map__navigation select')
        && str_contains($tile, 'window.handleMessage = handleMessage')
        && str_contains($tile, "addEventListener('wheel'")
        && str_contains($tile, "addEventListener('pointermove'")
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
// Historical analytics must never hide an independently observed task pass.
$statisticsMethod = new ReflectionMethod(NavimowDevice::class, 'localMapVisualizationStatistics');
$passScene = ['zones' => [[
    'zoneId' => 101,
    'zoneKey' => 'synthetic-zone',
    'label' => 'Synthetic Zone',
    'statistics' => ['latestPass' => [
        'lastObservedAt' => $device->now - 10,
        'completionObserved' => false,
        'passProgressPercent' => 21.0,
        'observedAreaDelta' => 294.89,
    ]],
]]];
$oldAnalytics = ['zones' => [[
    'zoneKey' => 'synthetic-zone',
    'latestRunCoveragePercent' => null,
    'latestRun' => ['endedAt' => $device->now - 86400],
    'weekEstimatedArea' => null,
]]];
$passRows = $statisticsMethod->invoke($device, $passScene, $oldAnalytics);
assertMowingAnalyticsDevice(
    $passRows[0]['passCoveragePercent'] === null,
    'Unmatched coverage must not appear beside pass progress.'
);
$matchingScene = $passScene;
$matchingScene['zones'][0]['statistics']['latestPass']['passSequence'] = 7;
$matchingScene['zones'][0]['statistics']['latestPass']['startedAt'] = $device->now - 120;
$matchingAnalytics = $oldAnalytics;
$matchingAnalytics['zones'][0]['latestRunCoveragePercent'] = 26.8;
$matchingAnalytics['zones'][0]['latestRun'] = [
    'passSequence' => 7, 'startedAt' => $device->now - 100,
    'endedAt' => $device->now - 10,
];
$matchedRows = $statisticsMethod->invoke($device, $matchingScene, $matchingAnalytics);
assertMowingAnalyticsDevice($matchedRows[0]['passCoveragePercent'] === 26.8, 'Same pass coverage lost.');
$matchingAnalytics['zones'][0]['latestRun']['passSequence'] = 6;
$matchedRows = $statisticsMethod->invoke($device, $matchingScene, $matchingAnalytics);
assertMowingAnalyticsDevice($matchedRows[0]['passCoveragePercent'] === null, 'Previous pass coverage leaked.');
$matchingAnalytics['zones'][0]['latestRun']['passSequence'] = 7;
$matchingAnalytics['zones'][0]['latestRun']['startedAt'] = $device->now - 86400;
$matchedRows = $statisticsMethod->invoke($device, $matchingScene, $matchingAnalytics);
assertMowingAnalyticsDevice($matchedRows[0]['passCoveragePercent'] === null, 'Reused sequence coverage leaked.');
assertMowingAnalyticsDevice(
    $passRows[0]['passIsCurrent'] === true
        && $passRows[0]['passProgressPercent'] === 21.0
        && $passRows[0]['observedArea'] === 294.89
        && $passRows[0]['latestRunCoveragePercent'] === null
        && $passRows[0]['latestRunEndedAt'] === $device->now - 86400,
    'Current pass was lost beside historical unknown coverage.'
);
foreach ([-301, 1] as $offset) {
    $datedScene = $passScene;
    $datedScene['zones'][0]['statistics']['latestPass']['lastObservedAt'] = $device->now + $offset;
    $rows = $statisticsMethod->invoke($device, $datedScene, $oldAnalytics);
    assertMowingAnalyticsDevice(!$rows[0]['passIsCurrent'], 'Stale/future pass marked current.');
}
$completedScene = $passScene;
$completedScene['zones'][0]['statistics']['latestPass']['completionObserved'] = true;
$rows = $statisticsMethod->invoke($device, $completedScene, $oldAnalytics);
assertMowingAnalyticsDevice(!$rows[0]['passIsCurrent'], 'Completed pass marked current.');
$device->testSetVariable('LastStatusUpdate', $device->now - 301);
$rows = $statisticsMethod->invoke($device, $passScene, $oldAnalytics);
assertMowingAnalyticsDevice(!$rows[0]['passIsCurrent'], 'Stale REST marked pass current.');
$device->testSetVariable('LastStatusUpdate', $device->now);
$device->testSetVariable('VehicleState', 2);
$rows = $statisticsMethod->invoke($device, $passScene, $oldAnalytics);
assertMowingAnalyticsDevice(!$rows[0]['passIsCurrent'], 'Docked mower marked pass current.');
$device->testSetVariable('VehicleState', 1);
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
$messages = $device->testVisualizationMessages();
$fallbackMessage = json_decode(
    $messages[array_key_last($messages)],
    true,
    64,
    JSON_THROW_ON_ERROR
);
assertMowingAnalyticsDevice(
    $fallbackMessage['analytics']['state'] === 'no-data'
        && count($fallbackMessage['statistics']) === 3
        && $fallbackMessage['statistics'][0]['passProgressPercent'] === 100.0
        && $fallbackMessage['statistics'][0]['latestRunCoveragePercent']
            === null,
    'Disabled analytics did not preserve useful zone-statistics presentation.'
);

echo "Navimow mowing analytics Device checks passed.\n";
