<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use Throwable;

/**
 * Default-disabled, transport-facing adapter for the protected tile gateway.
 *
 * It does not register a WebHook, choose a provider or emit HTTP output.
 */
final class OwnTracksTileWebhookAdapter
{
    private const HEADER_NAME = 'X-SAEF-Tile-Capability';
    private const MAXIMUM_HEADERS = 64;
    private const MAXIMUM_HEADER_NAME_BYTES = 64;
    private const MAXIMUM_HEADER_VALUE_BYTES = 2048;
    private const MAXIMUM_TOTAL_HEADER_BYTES = 16 * 1024;
    private const MAXIMUM_REQUEST_URI_BYTES = 256;
    private const CAPABILITY_PATTERN =
        '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D';
    private const CLIENT_PATTERN = '/^[a-z0-9-]{12,80}$/D';
    private const MAXIMUM_CAPABILITY_ISSUES_PER_MINUTE = 4;
    private const MAXIMUM_CONCURRENT_CAPABILITY_ISSUES = 2;

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $policy
     * @return array{
     *   action: string,
     *   requestGeneration: int,
     *   token: string,
     *   expiresAt: int
     * }
     */
    public static function issueCapability(
        array $request,
        array $policy,
        string $secret,
        string $audience,
        int $now,
        OwnTracksTileRequestBudget $requestBudget
    ): array {
        $policy = OwnTracksTileAccessPolicy::normalize($policy);
        if (
            ($policy['enabled'] ?? null) !== true
            || ($policy['mode'] ?? null) !== 'symcon-webhook'
            || $now < 0
        ) {
            throw new InvalidArgumentException(
                'Tile capability issuance is disabled.'
            );
        }
        $keys = array_keys($request);
        sort($keys, SORT_STRING);
        if ($keys !== ['clientSessionKey', 'requestGeneration']) {
            throw new InvalidArgumentException(
                'Tile capability request is invalid.'
            );
        }
        $requestGeneration = $request['requestGeneration'] ?? null;
        $clientSessionKey = $request['clientSessionKey'] ?? null;
        if (
            !is_int($requestGeneration)
            || $requestGeneration <= 0
            || $requestGeneration > 2_147_483_647
            || !is_string($clientSessionKey)
            || preg_match(self::CLIENT_PATTERN, $clientSessionKey) !== 1
        ) {
            throw new InvalidArgumentException(
                'Tile capability request is invalid.'
            );
        }
        $admission = $requestBudget->begin(
            'capability-issuance:' . $audience,
            $now,
            self::MAXIMUM_CAPABILITY_ISSUES_PER_MINUTE,
            self::MAXIMUM_CONCURRENT_CAPABILITY_ISSUES
        );
        if (!$admission['accepted'] || !is_array($admission['reservation'])) {
            throw new InvalidArgumentException(
                'Tile capability issuance is unavailable.'
            );
        }
        try {
            $issued = OwnTracksTileCapability::issue(
                $secret,
                $audience,
                $clientSessionKey,
                $now,
                $policy['tokenTtlSeconds']
            );
        } finally {
            $requestBudget->finish($admission['reservation'], $now);
        }

        return [
            'action' => 'tileCapability',
            'requestGeneration' => $requestGeneration,
            'token' => $issued['token'],
            'expiresAt' => $issued['expiresAt'],
        ];
    }

    /**
     * @param array<string, mixed> $server
     * @param array<int|string, array{name: mixed, value: mixed}> $headerLines
     * @param array<string, mixed> $policy
     * @param callable(int, int, int): mixed $tileReader
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string,
     *   classification: string
     * }
     */
    public static function handle(
        array $server,
        array $headerLines,
        bool $bodyPresent,
        array $policy,
        string $secret,
        string $audience,
        int $maximumZoom,
        string $tileSetRevision,
        ?OwnTracksTileFileCache $tileCache,
        OwnTracksTileRequestBudget $requestBudget,
        int $now,
        callable $tileReader
    ): array {
        try {
            $request = self::normalizeRequest(
                $server,
                $headerLines,
                $bodyPresent
            );
        } catch (InvalidArgumentException) {
            return self::rejected();
        }
        try {
            $transientState = [];
            return OwnTracksTileGateway::handle(
                $request,
                $policy,
                $secret,
                $audience,
                $maximumZoom,
                $tileSetRevision,
                $tileCache,
                $now,
                $tileReader,
                $transientState,
                $requestBudget
            );
        } catch (Throwable) {
            return self::unavailable();
        }
    }

    /**
     * @param array<string, mixed> $server
     * @param array<int|string, array{name: mixed, value: mixed}> $headerLines
     * @return array{
     *   method: string,
     *   path: string,
     *   headers: array<string, string>,
     *   bodyBytes: int
     * }
     */
    private static function normalizeRequest(
        array $server,
        array $headerLines,
        bool $bodyPresent
    ): array {
        $method = $server['REQUEST_METHOD'] ?? null;
        $requestUri = $server['REQUEST_URI'] ?? null;
        $contentLength = $server['CONTENT_LENGTH'] ?? null;
        $queryString = $server['QUERY_STRING'] ?? null;
        if (
            !is_string($method)
            || !is_string($requestUri)
            || strlen($requestUri) > self::MAXIMUM_REQUEST_URI_BYTES
            || str_contains($requestUri, "\r")
            || str_contains($requestUri, "\n")
            || ($contentLength !== null
                && (!is_string($contentLength)
                    || preg_match('/^(0|[1-9][0-9]{0,9})$/D', $contentLength)
                        !== 1))
            || ($contentLength !== null && $contentLength !== '0')
            || ($queryString !== null && !is_string($queryString))
            || (is_string($queryString) && $queryString !== '')
            || !array_is_list($headerLines)
            || count($headerLines) > self::MAXIMUM_HEADERS
        ) {
            throw new InvalidArgumentException('Tile request is invalid.');
        }
        $totalHeaderBytes = 0;
        $capabilities = [];
        foreach ($headerLines as $header) {
            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;
            if (
                !is_string($name)
                || !is_string($value)
                || $name === ''
                || strlen($name) > self::MAXIMUM_HEADER_NAME_BYTES
                || strlen($value) > self::MAXIMUM_HEADER_VALUE_BYTES
                || preg_match(
                    '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D',
                    $name
                ) !== 1
                || preg_match('/[\x00-\x1F\x7F]/D', $value) === 1
            ) {
                throw new InvalidArgumentException('Tile request is invalid.');
            }
            $totalHeaderBytes += strlen($name) + strlen($value);
            if ($totalHeaderBytes > self::MAXIMUM_TOTAL_HEADER_BYTES) {
                throw new InvalidArgumentException('Tile request is invalid.');
            }
            if (strcasecmp($name, self::HEADER_NAME) === 0) {
                $capabilities[] = $value;
            }
        }
        $headers = [];
        if (
            count($capabilities) === 1
            && preg_match(self::CAPABILITY_PATTERN, $capabilities[0]) === 1
            && strlen($capabilities[0]) <= 1024
        ) {
            $headers[self::HEADER_NAME] = $capabilities[0];
        }

        return [
            'method' => $method,
            'path' => $requestUri,
            'headers' => $headers,
            'bodyBytes' => $bodyPresent ? 1 : 0,
        ];
    }

    /**
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string,
     *   classification: string
     * }
     */
    private static function unavailable(): array
    {
        return [
            'status' => 503,
            'headers' => [
                'Cache-Control' => 'no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
                'Retry-After' => '60',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => 'Request unavailable',
            'classification' => 'adapter-unavailable',
        ];
    }

    /**
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string,
     *   classification: string
     * }
     */
    private static function rejected(): array
    {
        return [
            'status' => 404,
            'headers' => [
                'Cache-Control' => 'no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => 'Not found',
            'classification' => 'request-rejected',
        ];
    }
}
