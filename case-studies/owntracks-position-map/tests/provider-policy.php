<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksProviderPolicy;

require_once __DIR__ . '/bootstrap.php';

/**
 * @param class-string<Throwable> $expectedClass
 */
function assertProviderThrows(
    string $expectedClass,
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (Throwable $throwable) {
        assertTrue($throwable instanceof $expectedClass, $message);

        return;
    }

    throw new RuntimeException($message . ': no exception was thrown.');
}

$disabled = OwnTracksProviderPolicy::normalize([
    'basemap' => ['mode' => 'none'],
    'routing' => [
        'mode' => 'none',
        'allowGeodesicFallback' => true,
    ],
]);
assertTrue(!$disabled['basemap']['enabled'], 'No-tile fallback must be disabled.');
assertTrue(!$disabled['routing']['enabled'], 'No-route fallback must be disabled.');
assertTrue(
    $disabled['routing']['allowGeodesicFallback'],
    'Diagnostic ETA fallback must remain explicit.'
);

$internal = OwnTracksProviderPolicy::normalize([
    'basemap' => [
        'mode' => 'same-origin-xyz',
        'authorityKey' => 'internal-osm-tiles',
        'urlTemplate' => '/map-tiles/{z}/{x}/{y}.png',
        'maximumZoom' => 19,
        'attributionText' => '© OpenStreetMap contributors',
        'attributionUrl' => 'https://www.openstreetmap.org/copyright',
    ],
    'routing' => [
        'mode' => 'internal-osrm',
        'authorityKey' => 'internal-osrm',
        'endpointReference' => 'private-osrm-endpoint',
        'profileKey' => 'driving',
        'timeoutMilliseconds' => 1500,
        'maximumRouteAgeSeconds' => 300,
        'allowGeodesicFallback' => true,
    ],
]);
assertSameValue(
    'same-origin-tile-index',
    $internal['basemap']['locationDisclosure'],
    'Basemap disclosure boundary'
);
assertSameValue(
    'server-side',
    $internal['routing']['transport'],
    'Routing transport boundary'
);
assertSameValue(0, $internal['rendererCredentialCount'], 'Renderer credentials');
assertTrue(
    !$internal['externalLocationDisclosure'],
    'Selected provider policy must not disclose locations externally.'
);

foreach (
    [
        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        '//tiles.example.test/{z}/{x}/{y}.png',
        '/map-tiles/../remote/{z}/{x}/{y}.png',
        '/map-tiles/{z}/{x}.png?y={y}',
    ] as $forbiddenTemplate
) {
    assertProviderThrows(
        InvalidArgumentException::class,
        static fn (): array => OwnTracksProviderPolicy::normalize([
            'basemap' => [
                'mode' => 'same-origin-xyz',
                'authorityKey' => 'invalid-test',
                'urlTemplate' => $forbiddenTemplate,
                'maximumZoom' => 19,
                'attributionText' => '© OpenStreetMap contributors',
                'attributionUrl' => 'https://www.openstreetmap.org/copyright',
            ],
            'routing' => [
                'mode' => 'none',
                'allowGeodesicFallback' => true,
            ],
        ]),
        'External or ambiguous tile templates must fail closed.'
    );
}

assertProviderThrows(
    InvalidArgumentException::class,
    static fn (): array => OwnTracksProviderPolicy::normalize([
        'basemap' => ['mode' => 'none'],
        'routing' => [
            'mode' => 'public-osrm-demo',
            'allowGeodesicFallback' => true,
        ],
    ]),
    'Public routing demo must fail closed.'
);
assertProviderThrows(
    InvalidArgumentException::class,
    static fn (): array => OwnTracksProviderPolicy::normalize([
        'basemap' => [
            'mode' => 'same-origin-xyz',
            'authorityKey' => 'internal-osm-tiles',
            'urlTemplate' => '/map-tiles/{z}/{x}/{y}.png',
            'maximumZoom' => 19,
            'attributionText' => '<script>invalid</script>',
            'attributionUrl' => 'https://www.openstreetmap.org/copyright',
        ],
        'routing' => [
            'mode' => 'none',
            'allowGeodesicFallback' => true,
        ],
    ]),
    'Markup attribution must fail closed.'
);

$encoded = json_encode($internal, JSON_THROW_ON_ERROR);
foreach (['apiKey', 'token', 'password', 'https://router.project-osrm.org'] as $secret) {
    assertTrue(
        stripos($encoded, $secret) === false,
        'Provider projection contains a credential or public routing endpoint.'
    );
}

fwrite(STDOUT, "OwnTracks provider policy tests passed.\n");
