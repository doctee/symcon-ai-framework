<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

/**
 * Case-study-local activation contract for a Connect-reachable tile endpoint.
 *
 * This class validates configuration only. It neither registers a WebHook nor
 * issues or verifies capabilities.
 */
final class OwnTracksTileAccessPolicy
{
    private const HEADER_NAME = 'X-SAEF-Tile-Capability';
    private const HOOK_PREFIX = '/hook/owntracks-position-map';

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function normalize(array $configuration): array
    {
        $mode = $configuration['mode'] ?? null;
        if ($mode === 'none') {
            return [
                'mode' => 'none',
                'enabled' => false,
                'connectReachable' => false,
                'authenticationMode' => 'none',
                'activationReady' => true,
            ];
        }
        if ($mode !== 'symcon-webhook') {
            throw new InvalidArgumentException(
                'Unsupported tile access boundary.'
            );
        }
        if (($configuration['connectReachable'] ?? null) !== true) {
            throw new InvalidArgumentException(
                'A Symcon WebHook must be treated as Connect-reachable.'
            );
        }
        if (
            ($configuration['authenticationMode'] ?? null)
            !== 'ephemeral-header-capability'
        ) {
            throw new InvalidArgumentException(
                'Connect-reachable tiles require an ephemeral capability.'
            );
        }
        if (($configuration['headerName'] ?? null) !== self::HEADER_NAME) {
            throw new InvalidArgumentException(
                'Tile capability header is invalid.'
            );
        }
        if (($configuration['hookPathPrefix'] ?? null) !== self::HOOK_PREFIX) {
            throw new InvalidArgumentException('Tile hook prefix is invalid.');
        }
        if (($configuration['connectForwardingVerified'] ?? null) !== true) {
            throw new InvalidArgumentException(
                'Connect header forwarding is not verified.'
            );
        }
        if (($configuration['headerCanonicalizationVerified'] ?? null) !== true) {
            throw new InvalidArgumentException(
                'Effective authentication header semantics are not verified.'
            );
        }

        $tokenTtlSeconds = $configuration['tokenTtlSeconds'] ?? null;
        $refreshBeforeExpirySeconds =
            $configuration['refreshBeforeExpirySeconds'] ?? null;
        $maximumRequestsPerMinute =
            $configuration['maximumRequestsPerMinute'] ?? null;
        $maximumConcurrentRequests =
            $configuration['maximumConcurrentRequests'] ?? null;
        if (
            !is_int($tokenTtlSeconds)
            || $tokenTtlSeconds < 60
            || $tokenTtlSeconds > 900
            || !is_int($refreshBeforeExpirySeconds)
            || $refreshBeforeExpirySeconds < 15
            || $refreshBeforeExpirySeconds >= $tokenTtlSeconds
            || !is_int($maximumRequestsPerMinute)
            || $maximumRequestsPerMinute < 30
            || $maximumRequestsPerMinute > 1200
            || !is_int($maximumConcurrentRequests)
            || $maximumConcurrentRequests < 1
            || $maximumConcurrentRequests > 16
        ) {
            throw new InvalidArgumentException(
                'Tile access time or request bounds are invalid.'
            );
        }

        return [
            'mode' => 'symcon-webhook',
            'enabled' => true,
            'connectReachable' => true,
            'authenticationMode' => 'ephemeral-header-capability',
            'credentialPersistence' => 'memory-only',
            'stableCredentialInRenderer' => false,
            'headerName' => self::HEADER_NAME,
            'hookPathPrefix' => self::HOOK_PREFIX,
            'allowedMethods' => ['GET', 'HEAD'],
            'tokenTtlSeconds' => $tokenTtlSeconds,
            'refreshBeforeExpirySeconds' => $refreshBeforeExpirySeconds,
            'maximumRequestsPerMinute' => $maximumRequestsPerMinute,
            'maximumConcurrentRequests' => $maximumConcurrentRequests,
            'cacheVisibility' => 'private',
            'connectForwardingVerified' => true,
            'headerCanonicalizationVerified' => true,
            'authenticationAuthority' => 'hook-handler-only',
            'activationReady' => true,
        ];
    }
}
