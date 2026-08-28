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
        && substr_count($svg, 'class="mower"') === 1
        && str_contains($svg, 'rotate(-180)')
        && str_contains($svg, 'data-theme="dark"')
        && str_contains($svg, '.background{fill:#171b1f}')
        && str_contains($svg, 'station-docked rect{fill:#22a06b')
        && str_contains($svg, 'station-docking rect{fill:#d98b22')
        && str_contains($svg, 'station-undocked rect{fill:#75818a')
        && str_contains($svg, 'fill-opacity:.08')
        && str_contains($svg, 'stroke-dasharray:1.1 .8'),
    'Expected map layers are missing.'
);
assertLocalMapSvg(
    str_contains($lightSvg, 'data-theme="light"')
        && str_contains($lightSvg, '.background{fill:#f8fafc}')
        && str_contains($lightSvg, '.path{fill:none;stroke:#111827'),
    'Explicit light theme differs.'
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
        ),
    'Configured zone-label visibility or station state differs.'
);
assertLocalMapSvg(
    str_contains(
        LocalMapSvgRenderer::render(
            $scene,
            ['stationState' => 'docked']
        ),
        'class="station station-docked"'
    )
        && str_contains(
            LocalMapSvgRenderer::render(
                $scene,
                ['stationState' => 'undocked']
            ),
            'class="station station-undocked"'
        ),
    'Docked or undocked station presentation differs.'
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
