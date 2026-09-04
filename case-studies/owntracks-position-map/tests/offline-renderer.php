<?php

declare(strict_types=1);

$rendererDirectory = __DIR__ . '/../candidate/renderer';
$html = file_get_contents($rendererDirectory . '/renderer.html');
$css = file_get_contents($rendererDirectory . '/renderer.css');
$javascript = file_get_contents($rendererDirectory . '/renderer.js');
if ($html === false || $css === false || $javascript === false) {
    throw new RuntimeException('Renderer assets cannot be read.');
}

/** @param list<string> $needles */
function requireRendererTokens(
    string $content,
    array $needles,
    string $asset
): void {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            throw new RuntimeException(
                $asset . ' is missing required token: ' . $needle
            );
        }
    }
}

requireRendererTokens(
    $html,
    [
        'data-map-canvas',
        'data-source-select',
        'data-day-select',
        'data-mode-select',
        'data-fit-all',
        'data-eta',
        'no map tiles',
    ],
    'Renderer markup'
);
requireRendererTokens(
    $css,
    [
        'margin: 0',
        'touch-action: none',
        'width: 100%',
        'height: 100%',
        'min-width: 34px',
        'min-height: 34px',
        '@media (max-width: 560px)',
    ],
    'Renderer style'
);
requireRendererTokens(
    $javascript,
    [
        'fitBounds',
        'pointerdown',
        'pointermove',
        'pointerup',
        "addEventListener('wheel'",
        'ResizeObserver',
        'requestGeneration',
        'stale-result-discarded',
        'routeAware',
        '__ownTracksRendererDiagnostics',
    ],
    'Renderer script'
);

foreach (['fetch(', 'XMLHttpRequest', 'WebSocket', 'http://', 'https://'] as $forbidden) {
    if (str_contains($javascript . $css . $html, $forbidden)) {
        throw new RuntimeException(
            'Offline renderer contains external transport: ' . $forbidden
        );
    }
}

ob_start();
require __DIR__ . '/renderer-fixture.php';
$fixture = ob_get_clean();
if (!is_string($fixture)) {
    throw new RuntimeException('Renderer fixture was not generated.');
}
requireRendererTokens(
    $fixture,
    [
        '<!doctype html>',
        'Synthetic A',
        'Synthetic B',
        'Synthetic C',
        '2024-03-31',
        '2024-04-01',
        'external-route',
        'geodesic-observed-speed',
        'target-missing',
        '"fitObservationCount":360',
        '"renderedPoints":48',
    ],
    'Renderer fixture'
);
if (strlen($fixture) > 1024 * 1024) {
    throw new RuntimeException('Renderer fixture exceeds the 1 MiB offline bound.');
}
foreach (
    [
        'ObjectID',
        'trackerId',
        'privateTopic',
        'realCoordinate',
        'password',
    ] as $forbidden
) {
    if (stripos($fixture, $forbidden) !== false) {
        throw new RuntimeException(
            'Renderer fixture contains private-data marker: ' . $forbidden
        );
    }
}

fwrite(STDOUT, "OwnTracks offline renderer contract tests passed.\n");
