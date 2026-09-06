<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksTileAccessPolicy;

require_once __DIR__ . '/bootstrap.php';

/** @param array<string, mixed> $overrides */
function tileAccessConfiguration(array $overrides = []): array
{
    return array_replace([
        'mode' => 'symcon-webhook',
        'connectReachable' => true,
        'authenticationMode' => 'ephemeral-header-capability',
        'headerName' => 'X-SAEF-Tile-Capability',
        'hookPathPrefix' => '/hook/owntracks-position-map',
        'connectForwardingVerified' => true,
        'headerCanonicalizationVerified' => true,
        'tokenTtlSeconds' => 300,
        'refreshBeforeExpirySeconds' => 60,
        'maximumRequestsPerMinute' => 360,
        'maximumConcurrentRequests' => 8,
    ], $overrides);
}

/** @param array<string, mixed> $configuration */
function assertTileAccessRejected(array $configuration, string $message): void
{
    try {
        OwnTracksTileAccessPolicy::normalize($configuration);
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$disabled = OwnTracksTileAccessPolicy::normalize(['mode' => 'none']);
assertTrue(!$disabled['enabled'], 'Disabled tile access must remain disabled.');

$webHook = OwnTracksTileAccessPolicy::normalize(tileAccessConfiguration());
assertSameValue(
    ['GET', 'HEAD'],
    $webHook['allowedMethods'],
    'Tile WebHook method allowlist'
);
assertSameValue('memory-only', $webHook['credentialPersistence'], 'Capability storage');
assertTrue(
    !$webHook['stableCredentialInRenderer'],
    'A stable renderer credential must not be permitted.'
);

assertTileAccessRejected(
    tileAccessConfiguration(['authenticationMode' => 'none']),
    'An unauthenticated Connect-reachable WebHook must fail closed.'
);
assertTileAccessRejected(
    tileAccessConfiguration(['authenticationMode' => 'basic-auth']),
    'A long-lived browser Basic Auth credential must fail closed.'
);
assertTileAccessRejected(
    tileAccessConfiguration(['connectForwardingVerified' => false]),
    'Unverified Connect header forwarding must fail closed.'
);
assertTileAccessRejected(
    tileAccessConfiguration(['headerCanonicalizationVerified' => false]),
    'Unverified effective header semantics must fail closed.'
);
assertTileAccessRejected(
    tileAccessConfiguration(['tokenTtlSeconds' => 3600]),
    'An unbounded tile capability lifetime must fail closed.'
);
assertTileAccessRejected(
    tileAccessConfiguration(['maximumConcurrentRequests' => 32]),
    'Unbounded parallel tile requests must fail closed.'
);

fwrite(STDOUT, "OwnTracks tile access policy tests passed.\n");
