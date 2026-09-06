<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use Throwable;

/**
 * Case-study-local, transport-neutral request boundary for protected XYZ PNGs.
 */
final class OwnTracksTileGateway
{
    private const HEADER_NAME = 'X-SAEF-Tile-Capability';
    private const PATH_PATTERN =
        '#^/hook/owntracks-position-map/([0-9]{1,2})/'
        . '([0-9]{1,10})/([0-9]{1,10})\.png$#D';
    private const MAXIMUM_TILE_BYTES = 512 * 1024;
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";
    private const MAXIMUM_CLIENT_STATES = 64;

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $policy
     * @param callable(int, int, int): mixed $tileReader
     * @param array<string, mixed> $state
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string,
     *   classification: string
     * }
     */
    public static function handle(
        array $request,
        array $policy,
        string $secret,
        string $audience,
        int $maximumZoom,
        string $tileSetRevision,
        ?OwnTracksTileFileCache $tileCache,
        int $now,
        callable $tileReader,
        array &$state,
        ?OwnTracksTileRequestBudget $requestBudget = null
    ): array {
        $policy = OwnTracksTileAccessPolicy::normalize($policy);
        if (
            ($policy['mode'] ?? null) !== 'symcon-webhook'
            || ($policy['enabled'] ?? null) !== true
            || ($policy['headerName'] ?? null) !== self::HEADER_NAME
            || ($policy['hookPathPrefix'] ?? null)
                !== '/hook/owntracks-position-map'
            || !is_int($policy['maximumRequestsPerMinute'] ?? null)
            || !is_int($policy['maximumConcurrentRequests'] ?? null)
            || $maximumZoom < 1
            || $maximumZoom > 22
            || preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D',
                $tileSetRevision
            ) !== 1
            || $now < 0
        ) {
            throw new InvalidArgumentException(
                'Tile gateway configuration is invalid.'
            );
        }
        $method = $request['method'] ?? null;
        $path = $request['path'] ?? null;
        $headers = $request['headers'] ?? null;
        $bodyBytes = $request['bodyBytes'] ?? null;
        if (
            !is_string($method)
            || !is_string($path)
            || strlen($path) > 160
            || !is_array($headers)
            || !is_int($bodyBytes)
            || $bodyBytes !== 0
            || !in_array($method, ['GET', 'HEAD'], true)
            || str_contains($path, '?')
            || preg_match(self::PATH_PATTERN, $path, $pathMatch) !== 1
        ) {
            return self::rejected('request-rejected');
        }
        $zoom = (int) $pathMatch[1];
        $x = (int) $pathMatch[2];
        $y = (int) $pathMatch[3];
        $side = 2 ** $zoom;
        if (
            $zoom > $maximumZoom
            || $x < 0
            || $y < 0
            || $x >= $side
            || $y >= $side
        ) {
            return self::rejected('request-rejected');
        }
        $capability = self::header($headers, self::HEADER_NAME);
        if ($capability === null || strlen($capability) > 1024) {
            return self::rejected('authentication-rejected');
        }
        try {
            $claims = OwnTracksTileCapability::verify(
                $capability,
                $secret,
                $audience,
                $now
            );
        } catch (InvalidArgumentException) {
            return self::rejected('authentication-rejected');
        }
        $clientKey = hash('sha256', $claims['capabilityId']);
        $reservation = null;
        if ($requestBudget !== null) {
            $admission = $requestBudget->begin(
                $claims['capabilityId'],
                $now,
                $policy['maximumRequestsPerMinute'],
                $policy['maximumConcurrentRequests']
            );
            if (!$admission['accepted']) {
                return self::rateLimited($admission['classification']);
            }
            $reservation = $admission['reservation'];
        } else {
            $limit = self::admitFromTransientState(
                $state,
                $clientKey,
                $policy,
                $now
            );
            if ($limit !== null) {
                return self::rateLimited($limit);
            }
        }

        try {
            $content = $tileCache?->read(
                $tileSetRevision,
                $zoom,
                $x,
                $y,
                $now
            );
            if ($content === null) {
                $tile = $tileReader($zoom, $x, $y);
                if (!is_array($tile) || !is_string($tile['content'] ?? null)) {
                    return self::notFound();
                }
                $content = $tile['content'];
                if (!self::isValidPng($content)) {
                    return self::notFound();
                }
                $tileCache?->write(
                    $tileSetRevision,
                    $zoom,
                    $x,
                    $y,
                    $content,
                    $now
                );
            }
            $headersOut = [
                'Cache-Control' => 'private, max-age=300',
                'Content-Length' => (string) strlen($content),
                'Content-Type' => 'image/png',
                'Vary' => self::HEADER_NAME,
                'X-Content-Type-Options' => 'nosniff',
            ];

            return [
                'status' => 200,
                'headers' => $headersOut,
                'body' => $method === 'HEAD' ? '' : $content,
                'classification' => 'accepted',
            ];
        } catch (Throwable) {
            return self::notFound();
        } finally {
            if ($requestBudget !== null) {
                if (!is_array($reservation)) {
                    throw new InvalidArgumentException(
                        'Tile request-budget reservation is invalid.'
                    );
                }
                $requestBudget->finish($reservation, $now);
            } else {
                $state['clients'][$clientKey]['inFlight'] = max(
                    0,
                    (int) $state['clients'][$clientKey]['inFlight'] - 1
                );
                $state['clients'][$clientKey]['updatedAt'] = $now;
            }
        }
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $policy
     */
    private static function admitFromTransientState(
        array &$state,
        string $clientKey,
        array $policy,
        int $now
    ): ?string {
        self::pruneState($state);
        $windowStartedAt = intdiv($now, 60) * 60;
        $client = $state['clients'][$clientKey] ?? null;
        if (
            !is_array($client)
            || ($client['windowStartedAt'] ?? null) !== $windowStartedAt
        ) {
            $client = [
                'windowStartedAt' => $windowStartedAt,
                'requests' => 0,
                'inFlight' => 0,
                'updatedAt' => $now,
            ];
        }
        if (
            ($client['requests'] ?? 0)
                >= $policy['maximumRequestsPerMinute']
        ) {
            return 'rate-limited';
        }
        if (
            ($client['inFlight'] ?? 0)
                >= $policy['maximumConcurrentRequests']
        ) {
            return 'concurrency-limited';
        }
        $client['requests']++;
        $client['inFlight']++;
        $client['updatedAt'] = $now;
        $state['clients'][$clientKey] = $client;

        return null;
    }

    private static function isValidPng(string $content): bool
    {
        return $content !== ''
            && strlen($content) <= self::MAXIMUM_TILE_BYTES
            && str_starts_with($content, self::PNG_SIGNATURE);
    }

    /** @param array<string, mixed> $headers */
    private static function header(array $headers, string $wanted): ?string
    {
        $matches = [];
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, $wanted) !== 0) {
                continue;
            }
            if (!is_string($value) || trim($value) !== $value) {
                return null;
            }
            $matches[] = $value;
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /** @param array<string, mixed> $state */
    private static function pruneState(array &$state): void
    {
        if (!isset($state['clients']) || !is_array($state['clients'])) {
            $state['clients'] = [];
            return;
        }
        if (count($state['clients']) < self::MAXIMUM_CLIENT_STATES) {
            return;
        }
        uasort(
            $state['clients'],
            static fn (mixed $left, mixed $right): int =>
                (int) (is_array($left) ? ($left['updatedAt'] ?? 0) : 0)
                <=> (int) (is_array($right) ? ($right['updatedAt'] ?? 0) : 0)
        );
        while (count($state['clients']) >= self::MAXIMUM_CLIENT_STATES) {
            array_shift($state['clients']);
        }
    }

    /** @return array{status: int, headers: array<string, string>, body: string, classification: string} */
    private static function rejected(string $classification): array
    {
        return [
            'status' => 404,
            'headers' => [
                'Cache-Control' => 'no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => 'Not found',
            'classification' => $classification,
        ];
    }

    /** @return array{status: int, headers: array<string, string>, body: string, classification: string} */
    private static function rateLimited(string $classification): array
    {
        return [
            'status' => 429,
            'headers' => [
                'Cache-Control' => 'no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
                'Retry-After' => '60',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => 'Request unavailable',
            'classification' => $classification,
        ];
    }

    /** @return array{status: int, headers: array<string, string>, body: string, classification: string} */
    private static function notFound(): array
    {
        return self::rejected('tile-unavailable');
    }
}
