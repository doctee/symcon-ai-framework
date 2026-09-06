<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

/**
 * Synthetic-ready, case-study-local on-miss resolver.
 *
 * The caller supplies the immutable static authority and a trusted transport.
 * No network implementation, provider choice or credential store is owned here.
 */
final class OwnTracksTileMissResolver
{
    private const MAXIMUM_TILE_BYTES = 512 * 1024;
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";

    /** @var array<string, mixed> */
    private readonly array $configuration;

    /** @param array<string, mixed> $configuration */
    public function __construct(
        private readonly OwnTracksTileSelectionAllowlist $allowlist,
        array $configuration,
        private readonly ?string $selectionKey = null
    ) {
        if (
            $selectionKey !== null
            && preg_match('/^[a-f0-9]{64}$/D', $selectionKey) !== 1
        ) {
            throw new InvalidArgumentException('Tile selection key is invalid.');
        }
        $this->configuration = self::normalize($configuration);
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function normalize(array $configuration): array
    {
        if (($configuration['mode'] ?? null) !== 'fixed-https-xyz') {
            throw new InvalidArgumentException('Tile fallback mode is invalid.');
        }
        $origin = $configuration['origin'] ?? null;
        if (!is_string($origin) || !self::isSafeOrigin($origin)) {
            throw new InvalidArgumentException('Tile fallback origin is invalid.');
        }
        $pathTemplate = $configuration['pathTemplate'] ?? null;
        if (!is_string($pathTemplate) || !self::isSafePathTemplate($pathTemplate)) {
            throw new InvalidArgumentException('Tile fallback path template is invalid.');
        }
        $maximumZoom = $configuration['maximumZoom'] ?? null;
        $maximumRequests = $configuration['maximumRequestsPerSelection'] ?? null;
        $maximumBytes = $configuration['maximumBytesPerSelection'] ?? null;
        $timeoutMilliseconds = $configuration['timeoutMilliseconds'] ?? null;
        $negativeTtlSeconds = $configuration['negativeTtlSeconds'] ?? null;
        if (!is_int($maximumZoom) || $maximumZoom < 1 || $maximumZoom > 22) {
            throw new InvalidArgumentException('Tile fallback zoom budget is invalid.');
        }
        if (!is_int($maximumRequests) || $maximumRequests < 1 || $maximumRequests > 256) {
            throw new InvalidArgumentException('Tile fallback request budget is invalid.');
        }
        if (
            !is_int($maximumBytes)
            || $maximumBytes < self::MAXIMUM_TILE_BYTES
            || $maximumBytes > 16 * 1024 * 1024
        ) {
            throw new InvalidArgumentException('Tile fallback byte budget is invalid.');
        }
        if (
            !is_int($timeoutMilliseconds)
            || $timeoutMilliseconds < 250
            || $timeoutMilliseconds > 5000
        ) {
            throw new InvalidArgumentException('Tile fallback timeout is invalid.');
        }
        if (!is_int($negativeTtlSeconds) || $negativeTtlSeconds < 10 || $negativeTtlSeconds > 600) {
            throw new InvalidArgumentException('Tile fallback negative TTL is invalid.');
        }

        return [
            'mode' => 'fixed-https-xyz',
            'origin' => rtrim($origin, '/'),
            'pathTemplate' => $pathTemplate,
            'maximumZoom' => $maximumZoom,
            'maximumRequestsPerSelection' => $maximumRequests,
            'maximumBytesPerSelection' => $maximumBytes,
            'timeoutMilliseconds' => $timeoutMilliseconds,
            'negativeTtlSeconds' => $negativeTtlSeconds,
            'redirectsAllowed' => false,
            'publicPeerRequired' => true,
        ];
    }

    /**
     * @param callable(int, int, int): (?array{content: string}) $staticReader
     * @param callable(string, array<string, mixed>): array<string, mixed> $upstreamReader
     * @param array<string, mixed> $state
     * @return array{content: string}|null
     */
    public function read(
        int $zoom,
        int $x,
        int $y,
        callable $staticReader,
        callable $upstreamReader,
        array &$state,
        int $now
    ): ?array {
        if (
            $now < 0
            || $now > PHP_INT_MAX
                - $this->configuration['negativeTtlSeconds']
        ) {
            throw new InvalidArgumentException('Tile fallback time is invalid.');
        }
        $static = $staticReader($zoom, $x, $y);
        if ($static !== null) {
            return $static;
        }
        $reservation = $this->reserve($zoom, $x, $y, $state, $now);
        if ($reservation === null) {
            return null;
        }
        try {
            $response = $upstreamReader($reservation['url'], $reservation['options']);
        } catch (UnexpectedValueException) {
            return $this->complete($reservation, null, $state, $now, false);
        } catch (Throwable) {
            $response = null;
        }

        return $this->complete($reservation, $response, $state, $now);
    }

    /**
     * Reserve request and worst-case bytes durably BEFORE releasing the state lock.
     * @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    public function reserve(int $zoom, int $x, int $y, array &$state, int $now): ?array
    {
        if ($now < 0 || $now > PHP_INT_MAX - 600) {
            throw new InvalidArgumentException('Tile reservation time is invalid.');
        }
        $this->initializeState($state);
        $this->pruneNegativeCache($state, $now);
        foreach ($state['pendingReservations'] as $key => $pending) {
            if ($pending['expiresAt'] <= $now) {
                // Interrupted transfers keep their request and worst-case byte charge.
                unset($state['pendingReservations'][$key]);
            }
        }
        $tileKey = hash('sha256', $this->stateSelectionKey() . "\0" . $zoom . '/' . $x . '/' . $y);
        if ($zoom > $this->configuration['maximumZoom'] || !$this->allowlist->allows($zoom, $x, $y)) {
            $state['rejectedOutsideAllowlist']++;
            return null;
        }
        if (($state['negativeCache'][$tileKey] ?? 0) > $now) {
            $state['negativeCacheHits']++;
            return null;
        }
        $remaining = $this->configuration['maximumBytesPerSelection'] - $state['upstreamBytes'];
        if (
            $state['upstreamRequests'] >= $this->configuration['maximumRequestsPerSelection']
            || $remaining < 1 || count($state['pendingReservations']) >= 256
        ) {
            $state['budgetRejections']++;
            return null;
        }
        $id = hash('sha256', random_bytes(32));
        $bytes = min(self::MAXIMUM_TILE_BYTES, $remaining);
        $state['upstreamRequests']++;
        $state['upstreamBytes'] += $bytes;
        $state['pendingReservations'][$id] = [
            'tileKey' => $tileKey, 'bytes' => $bytes, 'expiresAt' => $now + 15,
        ];

        return [
            'id' => $id,
            'url' => $this->configuration['origin'] . str_replace(
                ['{z}', '{x}', '{y}'],
                [(string) $zoom, (string) $x, (string) $y],
                $this->configuration['pathTemplate']
            ),
            'options' => [
                'timeoutMilliseconds' => $this->configuration['timeoutMilliseconds'],
                'maximumResponseBytes' => $bytes,
                'followRedirects' => false,
                'requirePublicPeerAddress' => true,
            ],
        ];
    }

    /**
     * Complete against fresh locked state, never overwrite another request's counters.
     * @param array<string, mixed> $reservation
     * @param array<string, mixed>|null $response
     * @param array<string, mixed> $state
     * @return array{content: string}|null
     */
    public function complete(
        array $reservation,
        ?array $response,
        array &$state,
        int $now,
        bool $networkAdmitted = true
    ): ?array {
        $id = $reservation['id'] ?? '';
        $pending = $state['pendingReservations'][$id] ?? null;
        if (!is_array($pending)) {
            return null;
        }
        unset($state['pendingReservations'][$id]);
        if (!$networkAdmitted) {
            $state['upstreamRequests']--;
            $state['upstreamBytes'] -= $pending['bytes'];
            return null;
        }
        if ($pending['expiresAt'] <= $now || $response === null || !$this->isAcceptedResponse($response)) {
            $this->rememberNegative($state, $pending['tileKey'], $now);
            return null;
        }
        $content = $response['body'];
        $bytes = $response['accountedBytes'] ?? strlen($content);
        if (!is_int($bytes) || $bytes < 0 || $bytes > strlen($content) || $bytes > $pending['bytes']) {
            $state['budgetRejections']++;
            return null;
        }
        $state['upstreamBytes'] -= $pending['bytes'] - $bytes;
        $state['upstreamSuccesses']++;

        return ['content' => $content];
    }

    /** @param array<string, mixed> $state */
    private function initializeState(array &$state): void
    {
        if (($state['selectionFingerprint'] ?? null) === $this->stateSelectionKey()) {
            return;
        }
        $state = [
            'selectionFingerprint' => $this->stateSelectionKey(),
            'upstreamRequests' => 0,
            'upstreamSuccesses' => 0,
            'upstreamBytes' => 0,
            'negativeCacheHits' => 0,
            'rejectedOutsideAllowlist' => 0,
            'budgetRejections' => 0,
            'negativeCache' => [],
            'pendingReservations' => [],
        ];
    }

    private function stateSelectionKey(): string
    {
        return $this->selectionKey ?? $this->allowlist->fingerprint();
    }

    /** @param array<string, mixed> $state */
    private function pruneNegativeCache(array &$state, int $now): void
    {
        foreach ($state['negativeCache'] as $tileKey => $expiresAt) {
            if (!is_int($expiresAt) || $expiresAt <= $now) {
                unset($state['negativeCache'][$tileKey]);
            }
        }
    }

    /** @param array<string, mixed> $state */
    private function rememberNegative(array &$state, string $tileKey, int $now): void
    {
        $state['negativeCache'][$tileKey] = $now
            + $this->configuration['negativeTtlSeconds'];
    }

    /** @param array<string, mixed> $response */
    private function isAcceptedResponse(array $response): bool
    {
        $peerAddress = $response['peerAddress'] ?? null;
        $content = $response['body'] ?? null;

        return ($response['status'] ?? null) === 200
            && ($response['contentType'] ?? null) === 'image/png'
            && ($response['redirected'] ?? null) === false
            && is_int($response['elapsedMilliseconds'] ?? null)
            && $response['elapsedMilliseconds'] >= 0
            && $response['elapsedMilliseconds']
                <= $this->configuration['timeoutMilliseconds']
            && is_string($peerAddress)
            && filter_var(
                $peerAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_GLOBAL_RANGE
            ) !== false
            && (str_contains($peerAddress, ':')
                ? !str_starts_with(strtolower($peerAddress), 'ff')
                : (int) explode('.', $peerAddress)[0] < 224)
            && is_string($content)
            && strlen($content) > 0
            && strlen($content) <= self::MAXIMUM_TILE_BYTES
            && str_starts_with($content, self::PNG_SIGNATURE);
    }

    private static function isSafeOrigin(string $origin): bool
    {
        $parts = parse_url($origin);
        if (!is_array($parts) || array_keys($parts) === []) {
            return false;
        }
        $host = $parts['host'] ?? null;
        if (
            ($parts['scheme'] ?? null) !== 'https'
            || !is_string($host)
            || preg_match('/^[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?$/iD', $host) !== 1
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || strtolower($host) === 'localhost'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['path']) && $parts['path'] !== ''
            || isset($parts['port']) && $parts['port'] !== 443
        ) {
            return false;
        }

        return true;
    }

    private static function isSafePathTemplate(string $pathTemplate): bool
    {
        return str_starts_with($pathTemplate, '/')
            && !str_starts_with($pathTemplate, '//')
            && strlen($pathTemplate) <= 256
            && !str_contains($pathTemplate, '..')
            && !str_contains($pathTemplate, '\\')
            && !str_contains($pathTemplate, '?')
            && !str_contains($pathTemplate, '#')
            && preg_match('/[\x00-\x1f\x7f]/', $pathTemplate) !== 1
            && substr_count($pathTemplate, '{z}') === 1
            && substr_count($pathTemplate, '{x}') === 1
            && substr_count($pathTemplate, '{y}') === 1
            && preg_replace('/\{[zxy]\}/', '0', $pathTemplate) !== null
            && preg_match(
                '#^/[A-Za-z0-9._~!$&\'()*+,;=:@%/{\}-]+\.png$#D',
                $pathTemplate
            ) === 1;
    }
}
