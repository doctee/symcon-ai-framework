<?php

declare(strict_types=1);

require_once __DIR__ . '/local-map-scene-prototype.php';
require_once __DIR__ . '/../candidate/LocalMapSvgRenderer.php';

use Navimow\Prototype\LocalMapSvgRenderer;

function assertLocalMapSvg(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param callable(): mixed $operation */
function assertLocalMapSvgRejected(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$svg = LocalMapSvgRenderer::render($scene);
$configuredSvg = LocalMapSvgRenderer::render(
    $scene,
    [
        'hiddenZoneSequences' => [4],
        'stationState' => 'docking',
    ]
);
$lightSvg = LocalMapSvgRenderer::render($scene, ['theme' => 'light']);
$recencyScene = $scene;
$recencyScene['analytics'] = [
    'zones' => [
        [
            'zoneKey' => str_repeat('a', 64),
            'recencyState' => 2,
            'recencyDays' => 8,
        ],
        [
            'zoneKey' => str_repeat('b', 64),
            'recencyState' => 3,
            'recencyDays' => 15,
        ],
    ],
];
$recencySvg = LocalMapSvgRenderer::render($recencyScene);

assertLocalMapSvg(
    str_starts_with($svg, '<svg xmlns="http://www.w3.org/2000/svg"')
        && str_ends_with($svg, '</svg>')
        && strlen($svg) < 1024 * 1024,
    'SVG envelope or byte limit differs.'
);
assertLocalMapSvg(
    substr_count($svg, '<polygon class="zone"') === 4
        && substr_count($svg, '<polygon class="obstacle') === 3
        && substr_count($svg, '<polyline class="path"') > 0
        && substr_count($svg, 'class="station station-unknown"') === 1
        && substr_count(
            $svg,
            'class="mower mower-unknown mower-position-fresh"'
        ) === 1
        && str_contains($svg, 'class="station-base"')
        && str_contains($svg, 'class="station-occupancy"')
        && str_contains($svg, 'class="mower-body"')
        && str_contains($svg, 'class="mower-direction"')
        && str_contains($svg, 'data-heading-degrees="')
        && str_contains($svg, 'rotate(-188)')
        && str_contains($svg, 'data-zone-id="101"')
        && str_contains($svg, 'data-theme="dark"')
        && str_contains($svg, 'width="100%" height="100%"')
        && str_contains($svg, 'html,body{margin:0;padding:0')
        && str_contains($svg, '.background{fill:#171b1f}')
        && str_contains(
            $svg,
            'station-docked .station-occupancy'
        )
        && str_contains($svg, 'display:inline;fill:#39d98a')
        && str_contains($svg, 'station-docking .station-base')
        && str_contains($svg, 'fill:#7a3f00;stroke:#ff9f1c')
        && str_contains($svg, 'station-undocked .station-base')
        && str_contains($svg, 'fill:#303a42;stroke:#a7b1b8')
        && str_contains($svg, 'station-unknown .station-base')
        && str_contains($svg, 'fill:#701a75;stroke:#f0abfc')
        && str_contains($svg, 'mower-unknown .mower-body')
        && str_contains($svg, 'fill:#d946ef;stroke:#701a75')
        && str_contains($svg, 'fill-opacity:.06')
        && str_contains($svg, 'stroke-dasharray:1.1 .8'),
    'Expected map layers are missing.'
);
assertLocalMapSvg(
    substr_count($svg, '<g class="legend"') === 1
        && str_contains($svg, '<title>Symbollegende</title>')
        && str_contains($svg, '>Station</text>')
        && str_contains($svg, '>Mäher</text>')
        && str_contains($svg, '>Angedockt</text>')
        && str_contains($svg, '>Unterwegs</text>')
        && str_contains($svg, '>Pause/Bereit</text>')
        && str_contains($svg, '>Störung</text>')
        && str_contains($svg, '>Offline</text>')
        && str_contains($svg, '>Fahrspur</text>')
        && str_contains($svg, '>Sperrbereich</text>')
        && str_contains($svg, '>Zuordnung prüfen</text>')
        && str_contains($svg, '>Position verspätet</text>')
        && str_contains(
            $svg,
            'class="legend-station legend-station-unknown"'
        )
        && str_contains(
            $svg,
            'class="legend-mower legend-mower-unknown"'
        )
        && substr_count($svg, 'class="station station-') === 1
        && substr_count(
            $svg,
            'class="mower mower-unknown mower-position-fresh"'
        ) === 1
        && substr_count($svg, '<polyline class="path"') === 5
        && substr_count($svg, '<circle class="path-point') === 2
        && substr_count($svg, '<polygon class="obstacle') === 3,
    'Legend content or semantic layer isolation differs.'
);
$legendWidthPattern = '/<g class="legend"[^>]*><title>Symbollegende<\/title><rect class="legend-background" width="([0-9.]+)"/';
assertLocalMapSvg(
    preg_match($legendWidthPattern, $svg, $legendWidthMatch) === 1
        && abs(
            (float) $legendWidthMatch[1]
            - min(
                $scene['viewport']['width'] * 0.52,
                max(
                    26.0,
                    max(
                        1.35,
                        min(
                            1.9,
                            max(
                                $scene['viewport']['width'],
                                $scene['viewport']['height']
                            ) / 58.0
                        )
                    ) * 16.5
                )
            )
        ) < 0.001,
    'Legend width does not retain the compact two-column contract.'
);
assertLocalMapSvg(
    preg_match(
        '/<g class="legend" data-anchor-x="[0-9.-]+" data-anchor-y="[0-9.-]+" transform="translate\([0-9.-]+ [0-9.-]+\)">/',
        $svg
    ) === 1,
    'Legend viewport-alignment anchors are missing.'
);
assertLocalMapSvg(
    str_contains($lightSvg, 'data-theme="light"')
        && str_contains($lightSvg, '.background{fill:#f8fafc}')
        && str_contains($lightSvg, '.path{fill:none;stroke:#111827')
        && str_contains($lightSvg, '.legend-background{fill:#ffffff')
        && str_contains($lightSvg, 'fill:#1f2937'),
    'Explicit light theme differs.'
);
assertLocalMapSvg(
    str_contains(
        $recencySvg,
        'class="zone zone-recency-warning" data-zone-id="101"'
    )
        && str_contains(
            $recencySvg,
            'class="zone zone-recency-critical" data-zone-id="102"'
        )
        && str_contains($recencySvg, 'fill="#243a52" stroke="#78aee8"')
        && str_contains($recencySvg, 'fill="#24483a" stroke="#67c994"')
        && str_contains($recencySvg, 'last mowed 8 days ago')
        && str_contains($recencySvg, 'last mowed 15 days ago')
        && str_contains(
            $recencySvg,
            '.zone-recency-warning{stroke:#ffd166!important'
        )
        && str_contains(
            $recencySvg,
            '.zone-recency-critical{stroke:#ff5964!important'
        ),
    'Mowing recency replaced base zone color or lost its warning outline.'
);
assertLocalMapSvg(
    substr_count(
        $svg,
        'class="path-point path-point-outside"'
    ) === 1
        && substr_count(
            $svg,
            'class="path-point path-point-ambiguous"'
        ) === 1
        && substr_count(
            $svg,
            'class="path-point path-point-unknown-task-zone"'
        ) === 0,
    'Diagnostic point selectors or markers differ.'
);
assertLocalMapSvg(
    substr_count($svg, '<circle class="path-point') === 2
        && str_contains($svg, '<title>Outside mapped zone</title>')
        && str_contains($svg, '<title>Ambiguous zone attribution</title>'),
    'Diagnostic point rendering differs.'
);
assertLocalMapSvg(
    !str_contains(strtolower($svg), '<script')
        && !str_contains(strtolower($svg), 'javascript:')
        && !str_contains(strtolower($svg), '<foreignobject')
        && !str_contains(strtolower($svg), ' href='),
    'SVG contains an active or external content surface.'
);
assertLocalMapSvg(
    substr_count($configuredSvg, '<text class="zone-label"') === 3
        && !str_contains($configuredSvg, '>Unassigned Area</text>')
        && str_contains(
            $configuredSvg,
            'class="station station-docking"'
        )
        && str_contains(
            $configuredSvg,
            '<title>Mower returning to station</title>'
        )
        && str_contains(
            $configuredSvg,
            'class="legend-station legend-station-docking"'
        ),
    'Configured zone-label visibility or station state differs.'
);
assertLocalMapSvg(
    str_contains(
        $dockedSvg = LocalMapSvgRenderer::render(
            $scene,
            ['stationState' => 'docked']
        ),
        'class="station station-docked"'
    )
        && str_contains(
            $dockedSvg,
            'station-docked .station-occupancy'
        )
        && str_contains(
            $undockedSvg = LocalMapSvgRenderer::render(
                $scene,
                ['stationState' => 'undocked']
            ),
            'class="station station-undocked"'
        )
        && str_contains(
            $undockedSvg,
            '.station .station-occupancy,.legend-station .station-occupancy{display:none}'
        ),
    'Docked or undocked station presentation differs.'
);
foreach (
    [
        'active',
        'paused',
        'returning',
        'attention',
        'offline',
        'docked',
        'unknown',
    ] as $mowerState
) {
    $stateSvg = LocalMapSvgRenderer::render(
        $scene,
        ['mowerState' => $mowerState]
    );
    assertLocalMapSvg(
        str_contains(
            $stateSvg,
            'class="mower mower-' . $mowerState
                . ' mower-position-fresh"'
        )
            && str_contains(
                $stateSvg,
                'class="legend-mower legend-mower-' . $mowerState . '"'
            ),
        'Mower-state presentation differs for ' . $mowerState . '.'
    );
}
$delayedSvg = LocalMapSvgRenderer::render($scene, [
    'mowerState' => 'active',
    'positionFreshness' => 'delayed',
]);
assertLocalMapSvg(
    str_contains(
        $delayedSvg,
        'class="mower mower-active mower-position-delayed"'
    )
        && str_contains(
            $delayedSvg,
            '<title>Mower active; position delayed</title>'
        )
        && str_contains($delayedSvg, 'class="mower-freshness-halo"')
        && str_contains(
            $delayedSvg,
            '.mower-position-delayed .mower-freshness-halo{display:inline}'
        ),
    'Delayed position did not retain state color with a freshness halo.'
);
assertLocalMapSvgRejected(
    static fn (): string => LocalMapSvgRenderer::render(
        $scene,
        ['positionFreshness' => 'recentish']
    ),
    'An unknown position freshness was accepted.'
);
$headingMatched = preg_match(
    '/data-heading-degrees="(-?[0-9]+(?:\.[0-9]+)?)"/',
    $svg,
    $heading
) === 1;
assertLocalMapSvg(
    $headingMatched
        && is_finite((float) $heading[1])
        && abs((float) $heading[1]) > 0.01,
    'The mower marker has no path-derived orientation.'
);
assertLocalMapSvgRejected(
    static fn (): string => LocalMapSvgRenderer::render(
        $scene,
        ['mowerState' => 'cutting']
    ),
    'An unknown mower state was accepted.'
);
assertLocalMapSvg(
    !str_contains(
        LocalMapSvgRenderer::render($scene, ['showMower' => false]),
        'class="mower mower-'
    ),
    'A hidden current mower marker was rendered.'
);
assertLocalMapSvgRejected(
    static fn (): string => LocalMapSvgRenderer::render(
        $scene,
        ['showMower' => 1]
    ),
    'A non-boolean mower visibility flag was accepted.'
);
assertLocalMapSvgRejected(
    static fn (): string => LocalMapSvgRenderer::render(
        $scene,
        ['stationState' => 'moving']
    ),
    'An unknown station state was accepted.'
);
assertLocalMapSvgRejected(
    static fn (): string => LocalMapSvgRenderer::render(
        $scene,
        ['theme' => 'system']
    ),
    'An unknown map theme was accepted.'
);
assertLocalMapSvgRejected(
    static fn (): string => LocalMapSvgRenderer::render(
        $scene,
        ['hiddenZoneSequences' => [4, 4]]
    ),
    'Duplicate hidden-zone sequences were accepted.'
);

echo "local map SVG renderer tests passed\n";
