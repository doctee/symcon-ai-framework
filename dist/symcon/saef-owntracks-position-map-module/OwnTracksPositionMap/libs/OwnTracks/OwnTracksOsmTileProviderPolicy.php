<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

/**
 * Case-study-local policy for low-volume interactive OSM Standard misses.
 *
 * This policy does not authorize prefetching, offline archives or static-map
 * generation from the community tile service.
 */
final class OwnTracksOsmTileProviderPolicy
{
    public const ORIGIN = 'https://tile.openstreetmap.org';
    public const PATH_TEMPLATE = '/{z}/{x}/{y}.png';
    public const ATTRIBUTION_TEXT = '© OpenStreetMap contributors';
    public const ATTRIBUTION_URL = 'https://www.openstreetmap.org/copyright';
    public const FALLBACK_CACHE_TTL_SECONDS = 604_800;

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function normalize(array $configuration): array
    {
        if (($configuration['mode'] ?? null) !== 'osm-standard-raster-on-miss') {
            throw new InvalidArgumentException('OSM tile-provider mode is invalid.');
        }
        if (($configuration['origin'] ?? null) !== self::ORIGIN) {
            throw new InvalidArgumentException('OSM tile-provider origin is invalid.');
        }
        if (($configuration['pathTemplate'] ?? null) !== self::PATH_TEMPLATE) {
            throw new InvalidArgumentException('OSM tile-provider path is invalid.');
        }
        $userAgent = $configuration['userAgent'] ?? null;
        if (!is_string($userAgent) || !self::isIdentifiableUserAgent($userAgent)) {
            throw new InvalidArgumentException('OSM tile-provider User-Agent is invalid.');
        }
        $refererOrigin = $configuration['refererOrigin'] ?? null;
        if (!is_string($refererOrigin) || !self::isSafeHttpsOrigin($refererOrigin)) {
            throw new InvalidArgumentException('OSM tile-provider Referer origin is invalid.');
        }
        $maximumConcurrentRequests = $configuration['maximumConcurrentRequests'] ?? null;
        if (
            !is_int($maximumConcurrentRequests)
            || $maximumConcurrentRequests < 1
            || $maximumConcurrentRequests > 2
        ) {
            throw new InvalidArgumentException(
                'OSM tile-provider concurrency limit is invalid.'
            );
        }
        $maximumRequestsPerMinute = $configuration['maximumRequestsPerMinute'] ?? null;
        if (
            !is_int($maximumRequestsPerMinute)
            || $maximumRequestsPerMinute < 1
            || $maximumRequestsPerMinute > 60
        ) {
            throw new InvalidArgumentException(
                'OSM tile-provider request rate is invalid.'
            );
        }

        return [
            'mode' => 'osm-standard-raster-on-miss',
            'origin' => self::ORIGIN,
            'pathTemplate' => self::PATH_TEMPLATE,
            'maximumZoom' => 19,
            'userAgent' => $userAgent,
            'refererOrigin' => rtrim($refererOrigin, '/') . '/',
            'attributionText' => self::ATTRIBUTION_TEXT,
            'attributionUrl' => self::ATTRIBUTION_URL,
            'maximumConcurrentRequests' => $maximumConcurrentRequests,
            'maximumRequestsPerMinute' => $maximumRequestsPerMinute,
            'fallbackCacheTtlSeconds' => self::FALLBACK_CACHE_TTL_SECONDS,
            'honorOriginCacheHeaders' => true,
            'conditionalRequestsRequired' => true,
            'prefetchAllowed' => false,
            'offlineArchiveAllowed' => false,
            'bestEffortNoSla' => true,
        ];
    }

    private static function isIdentifiableUserAgent(string $userAgent): bool
    {
        if (
            strlen($userAgent) < 24
            || strlen($userAgent) > 200
            || preg_match('/[^\x20-\x7e]/', $userAgent) === 1
            || !str_starts_with($userAgent, 'SAEFOwnTracksPositionMap/')
        ) {
            return false;
        }
        if (
            preg_match('/\(\+(https:\/\/[^\s;()]+)(?:;[^()]*)?\)$/D', $userAgent, $matches)
                !== 1
        ) {
            return false;
        }

        return self::isSafeHttpsContactUrl($matches[1]);
    }

    private static function isSafeHttpsContactUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && self::isPublicDnsName($parts['host'] ?? null)
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment'])
            && (!isset($parts['port']) || $parts['port'] === 443);
    }

    private static function isSafeHttpsOrigin(string $origin): bool
    {
        $parts = parse_url($origin);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && self::isPublicDnsName($parts['host'] ?? null)
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment'])
            && (!isset($parts['path']) || $parts['path'] === '' || $parts['path'] === '/')
            && (!isset($parts['port']) || $parts['port'] === 443);
    }

    private static function isPublicDnsName(mixed $host): bool
    {
        return is_string($host)
            && strlen($host) <= 253
            && preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}'
                . '[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/iD',
                $host
            ) === 1
            && filter_var($host, FILTER_VALIDATE_IP) === false
            && strtolower($host) !== 'localhost'
            && !preg_match(
                '/(?:^|\.)(?:example|invalid|localhost|test)$/iD',
                $host
            );
    }
}
