<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksOsmTileProviderPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksPinnedHttpsTileTransport;
use OwnTracksPositionMap\Prototype\OwnTracksTileSelectionAllowlist;

require_once __DIR__ . '/../candidate/OwnTracksOsmTileProviderPolicy.php';
require_once __DIR__ . '/../candidate/OwnTracksPinnedHttpsTileTransport.php';
require_once __DIR__ . '/../candidate/OwnTracksTileSelectionAllowlist.php';

/**
 * One-request real-network preflight for the case-study-local OSM transport.
 *
 * This executable deliberately accepts identity values only through the
 * process environment. It requests the synthetic world tile 0/0/0, keeps the
 * response body in memory and emits sanitized transport evidence only.
 */

$contactUrl = getenv('SAEF_OWNTRACKS_OSM_CONTACT_URL');
$refererOrigin = getenv('SAEF_OWNTRACKS_OSM_REFERER_ORIGIN');
if (!is_string($contactUrl) || !is_string($refererOrigin)) {
    fwrite(STDERR, "Required private preflight environment is missing.\n");
    exit(2);
}

try {
    $policy = OwnTracksOsmTileProviderPolicy::normalize([
        'mode' => 'osm-standard-raster-on-miss',
        'origin' => OwnTracksOsmTileProviderPolicy::ORIGIN,
        'pathTemplate' => OwnTracksOsmTileProviderPolicy::PATH_TEMPLATE,
        'userAgent' => 'SAEFOwnTracksPositionMap/0.1 (+' . $contactUrl . ')',
        'refererOrigin' => $refererOrigin,
        'maximumConcurrentRequests' => 1,
        'maximumRequestsPerMinute' => 1,
    ]);
    $allowlist = OwnTracksTileSelectionAllowlist::fromFitBounds(
        [
            'west' => -180.0,
            'east' => 180.0,
            'south' => -85.0,
            'north' => 85.0,
        ],
        0,
        0,
        0,
        1
    );
    if (!$allowlist->allows(0, 0, 0) || $allowlist->tileCount() !== 1) {
        throw new RuntimeException('Synthetic preflight allowlist failed closed.');
    }

    $transport = new OwnTracksPinnedHttpsTileTransport([
        'origin' => $policy['origin'],
        'pathTemplate' => $policy['pathTemplate'],
        'userAgent' => $policy['userAgent'],
        'refererOrigin' => $policy['refererOrigin'],
        'timeoutMilliseconds' => 5000,
        'maximumResponseBytes' => 512 * 1024,
        'fallbackCacheTtlSeconds' => $policy['fallbackCacheTtlSeconds'],
    ]);
    $response = $transport->fetchWithSystemTransport(
        $policy['origin'] . '/0/0/0.png',
        [
            'followRedirects' => false,
            'requirePublicPeerAddress' => true,
            'timeoutMilliseconds' => 5000,
            'maximumResponseBytes' => 512 * 1024,
        ],
        [],
        time()
    );

    $body = $response['body'];
    if (
        $response['status'] !== 200
        || $response['contentType'] !== 'image/png'
        || !is_string($body)
        || !str_starts_with($body, "\x89PNG\r\n\x1a\n")
    ) {
        throw new RuntimeException('OSM preflight response contract failed.');
    }

    echo json_encode(
        [
            'ok' => true,
            'requestCount' => 1,
            'syntheticTile' => '0/0/0',
            'status' => $response['status'],
            'contentType' => $response['contentType'],
            'bytes' => strlen($body),
            'elapsedMilliseconds' => $response['elapsedMilliseconds'],
            'dnsPinned' => true,
            'tlsPeerVerified' => true,
            'publicPeerVerified' => true,
            'redirected' => $response['redirected'],
            'cacheable' => $response['cacheable'],
            'cacheTtlSeconds' => $response['cacheTtlSeconds'],
            'etagPresent' => is_string($response['etag']),
            'lastModifiedPresent' => is_string($response['lastModified']),
            'bodyPersisted' => false,
        ],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
    ) . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(
        STDERR,
        json_encode(
            [
                'ok' => false,
                'requestCountMaximum' => 1,
                'errorClass' => get_class($throwable),
                'message' => $throwable->getMessage(),
            ],
            JSON_THROW_ON_ERROR
        ) . PHP_EOL
    );
    exit(1);
}
