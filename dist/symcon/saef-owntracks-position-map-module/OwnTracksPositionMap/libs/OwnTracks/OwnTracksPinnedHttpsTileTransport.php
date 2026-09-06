<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use RuntimeException;

/**
 * Case-study-local HTTPS transport with bounded DNS and a pre-HTTP peer gate.
 */
final class OwnTracksPinnedHttpsTileTransport
{
    private const MAXIMUM_DNS_ADDRESSES = 8;
    private const MAXIMUM_HEADER_VALUE_BYTES = 512;

    /** @var array<string, mixed> */
    private readonly array $configuration;
    private readonly string $tilePathPattern;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->configuration = $this->normalize($configuration);
        $this->tilePathPattern = self::pathPattern(
            $this->configuration['pathTemplate']
        );
    }

    /**
     * @param array<string, mixed> $requestOptions
     * @param array<string, string> $conditionalHeaders
     * @param callable(string, int): mixed $dnsResolver
     * @param callable(array<string, mixed>): array<string, mixed> $executor
     * @return array<string, mixed>
     */
    public function fetch(
        string $url,
        array $requestOptions,
        array $conditionalHeaders,
        callable $dnsResolver,
        callable $executor,
        int $now
    ): array {
        if ($now < 0) {
            throw new InvalidArgumentException('Tile transport time is invalid.');
        }
        $startedAt = hrtime(true);
        $deadline = new OwnTracksTileDeadline($requestOptions['timeoutMilliseconds'] ?? 1);
        $parts = parse_url($url);
        if (!$this->isAuthorizedUrl($parts)) {
            throw new InvalidArgumentException('Tile transport URL is not authorized.');
        }
        $maximumResponseBytes = $requestOptions['maximumResponseBytes'] ?? null;
        $timeoutMilliseconds = $requestOptions['timeoutMilliseconds'] ?? null;
        if (
            ($requestOptions['followRedirects'] ?? null) !== false
            || ($requestOptions['requirePublicPeerAddress'] ?? null) !== true
            || !is_int($maximumResponseBytes)
            || $maximumResponseBytes < 1
            || $maximumResponseBytes > $this->configuration['maximumResponseBytes']
            || !is_int($timeoutMilliseconds)
            || $timeoutMilliseconds < 1
            || $timeoutMilliseconds > $this->configuration['timeoutMilliseconds']
        ) {
            throw new InvalidArgumentException('Tile transport request budget is invalid.');
        }
        $headers = $this->requestHeaders($conditionalHeaders);
        $host = $parts['host'];
        $addresses = $dnsResolver($host, $deadline->remainingMilliseconds());
        $remaining = $deadline->remainingMilliseconds();
        if (!is_array($addresses) || $addresses === [] || count($addresses) > self::MAXIMUM_DNS_ADDRESSES) {
            throw new RuntimeException('Tile transport DNS result is invalid.');
        }
        $addresses = array_values(array_unique($addresses));
        foreach ($addresses as $address) {
            if (!self::isPublicAddress($address)) {
                throw new RuntimeException('Tile transport DNS result is not public.');
            }
        }
        sort($addresses, SORT_STRING);
        $pinnedAddress = $addresses[0];
        $response = $executor([
            'method' => 'GET',
            'url' => $url,
            'host' => $host,
            'port' => 443,
            'pinnedAddress' => $pinnedAddress,
            'headers' => $headers,
            'timeoutMilliseconds' => min($timeoutMilliseconds, $remaining),
            'maximumResponseBytes' => $maximumResponseBytes,
            'followRedirects' => false,
            'verifyTlsPeer' => true,
            'verifyTlsHost' => true,
        ]);
        $measuredMilliseconds = (int) floor((hrtime(true) - $startedAt) / 1_000_000);

        return $this->normalizeResponse(
            $response,
            $url,
            $pinnedAddress,
            $maximumResponseBytes,
            $timeoutMilliseconds,
            $measuredMilliseconds,
            $now
        );
    }

    /**
     * Execute native asynchronous DNS inside cURL's total transfer deadline.
     * Peer validation is after TCP/TLS setup, before the HTTP request is sent.
     *
     * @param array<string, mixed> $requestOptions
     * @param array<string, string> $conditionalHeaders
     * @return array<string, mixed>
     */
    public function fetchWithSystemTransport(
        string $url,
        array $requestOptions,
        array $conditionalHeaders,
        int $now
    ): array {
        // Native async DNS is covered by cURL's total deadline. No PHP DNS call
        // or unbounded resolver worker is allowed on this production path.
        if (!self::systemTransportSupported()) {
            throw new RuntimeException('Bounded tile transport is unavailable.');
        }
        $parts = parse_url($url);
        $timeout = $requestOptions['timeoutMilliseconds'] ?? null;
        $bytes = $requestOptions['maximumResponseBytes'] ?? null;
        if (
            $now < 0 || !$this->isAuthorizedUrl($parts)
            || !is_int($timeout) || $timeout < 1
            || $timeout > $this->configuration['timeoutMilliseconds']
            || !is_int($bytes) || $bytes < 1 || $bytes > $this->configuration['maximumResponseBytes']
            || ($requestOptions['followRedirects'] ?? null) !== false
            || ($requestOptions['requirePublicPeerAddress'] ?? null) !== true
        ) {
            throw new InvalidArgumentException('Tile transport request is invalid.');
        }
        $startedAt = hrtime(true);
        $response = self::executeCurl([
            'url' => $url, 'host' => $parts['host'], 'pinnedAddress' => null,
            'headers' => $this->requestHeaders($conditionalHeaders),
            'timeoutMilliseconds' => $timeout, 'maximumResponseBytes' => $bytes,
        ]);
        $peer = $response['authorizedAddress'] ?? null;
        if (!is_string($peer) || !self::isPublicAddress($peer)) {
            throw new RuntimeException('Tile connection was not authorized.');
        }

        return $this->normalizeResponse(
            $response,
            $url,
            $peer,
            $bytes,
            $timeout,
            (int) floor((hrtime(true) - $startedAt) / 1000000),
            $now
        );
    }

    public static function systemTransportSupported(): bool
    {
        if (
            !function_exists('curl_version')
            || !defined('CURLOPT_PREREQFUNCTION')
            || !defined('CURL_PREREQFUNC_ABORT')
            || !defined('CURL_PREREQFUNC_OK')
        ) {
            return false;
        }
        $version = curl_version();

        return is_array($version) && ($version['features'] & CURL_VERSION_ASYNCHDNS) !== 0;
    }

    /** @return array<string, mixed> */
    private function normalize(array $configuration): array
    {
        $origin = $configuration['origin'] ?? null;
        $pathTemplate = $configuration['pathTemplate'] ?? null;
        $userAgent = $configuration['userAgent'] ?? null;
        $refererOrigin = $configuration['refererOrigin'] ?? null;
        $timeoutMilliseconds = $configuration['timeoutMilliseconds'] ?? null;
        $maximumResponseBytes = $configuration['maximumResponseBytes'] ?? null;
        $fallbackCacheTtlSeconds = $configuration['fallbackCacheTtlSeconds'] ?? null;
        $originParts = is_string($origin) ? parse_url($origin) : false;
        if (
            !is_array($originParts)
            || ($originParts['scheme'] ?? null) !== 'https'
            || !is_string($originParts['host'] ?? null)
            || isset($originParts['user'])
            || isset($originParts['pass'])
            || isset($originParts['query'])
            || isset($originParts['fragment'])
            || isset($originParts['path']) && $originParts['path'] !== ''
            || isset($originParts['port']) && $originParts['port'] !== 443
            || !is_string($pathTemplate)
            || strlen($pathTemplate) > 128
            || substr_count($pathTemplate, '{z}') !== 1
            || substr_count($pathTemplate, '{x}') !== 1
            || substr_count($pathTemplate, '{y}') !== 1
            || !str_starts_with($pathTemplate, '/')
            || str_contains($pathTemplate, '..')
            || str_contains($pathTemplate, '?')
            || str_contains($pathTemplate, '#')
            || !is_string($userAgent)
            || strlen($userAgent) < 10
            || strlen($userAgent) > 200
            || preg_match('/[^\x20-\x7e]/', $userAgent) === 1
            || !is_string($refererOrigin)
            || !self::isHttpsOrigin($refererOrigin)
            || !is_int($timeoutMilliseconds)
            || $timeoutMilliseconds < 250
            || $timeoutMilliseconds > 5000
            || !is_int($maximumResponseBytes)
            || $maximumResponseBytes < 1
            || $maximumResponseBytes > 512 * 1024
            || !is_int($fallbackCacheTtlSeconds)
            || $fallbackCacheTtlSeconds < 3600
            || $fallbackCacheTtlSeconds > 30 * 86400
        ) {
            throw new InvalidArgumentException('Tile transport configuration is invalid.');
        }

        return [
            'origin' => rtrim($origin, '/'),
            'host' => $originParts['host'],
            'pathTemplate' => $pathTemplate,
            'userAgent' => $userAgent,
            'refererOrigin' => rtrim($refererOrigin, '/') . '/',
            'timeoutMilliseconds' => $timeoutMilliseconds,
            'maximumResponseBytes' => $maximumResponseBytes,
            'fallbackCacheTtlSeconds' => $fallbackCacheTtlSeconds,
        ];
    }

    /** @param array<string, mixed>|false $parts */
    private function isAuthorizedUrl(array|false $parts): bool
    {
        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === $this->configuration['host']
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment'])
            && (!isset($parts['port']) || $parts['port'] === 443)
            && is_string($parts['path'] ?? null)
            && preg_match($this->tilePathPattern, $parts['path']) === 1;
    }

    /**
     * @param array<string, string> $conditionalHeaders
     * @return list<string>
     */
    private function requestHeaders(array $conditionalHeaders): array
    {
        $headers = [
            'Accept: image/png',
            'Accept-Encoding: identity',
            'User-Agent: ' . $this->configuration['userAgent'],
            'Referer: ' . $this->configuration['refererOrigin'],
        ];
        foreach ($conditionalHeaders as $name => $value) {
            if (
                !in_array($name, ['If-None-Match', 'If-Modified-Since'], true)
                || $value === ''
                || strlen($value) > self::MAXIMUM_HEADER_VALUE_BYTES
                || preg_match('/[\x00-\x1f\x7f]/', $value) === 1
            ) {
                throw new InvalidArgumentException(
                    'Tile transport conditional header is invalid.'
                );
            }
            $headers[] = $name . ': ' . $value;
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function normalizeResponse(
        array $response,
        string $requestedUrl,
        string $pinnedAddress,
        int $maximumResponseBytes,
        int $timeoutMilliseconds,
        int $measuredMilliseconds,
        int $now
    ): array {
        $status = $response['status'] ?? null;
        $body = $response['body'] ?? null;
        $primaryAddress = $response['primaryAddress'] ?? null;
        $effectiveUrl = $response['effectiveUrl'] ?? null;
        $responseHeaders = $response['headers'] ?? null;
        $executorMilliseconds = $response['elapsedMilliseconds'] ?? null;
        if (
            !is_int($status)
            || $status < 100
            || $status > 599
            || !is_string($body)
            || strlen($body) > $maximumResponseBytes
            || !is_string($primaryAddress)
            || $primaryAddress !== $pinnedAddress
            || !self::isPublicAddress($primaryAddress)
            || $effectiveUrl !== $requestedUrl
            || ($response['redirected'] ?? null) !== false
            || !is_array($responseHeaders)
            || !is_int($executorMilliseconds)
            || $executorMilliseconds < 0
            || $executorMilliseconds > $timeoutMilliseconds
            || $measuredMilliseconds > $timeoutMilliseconds
        ) {
            throw new RuntimeException('Tile transport response is invalid.');
        }
        $headers = self::normalizeResponseHeaders($responseHeaders);
        $contentType = strtolower(trim(explode(';', $headers['content-type'] ?? '')[0]));
        $cacheTtlSeconds = self::cacheTtlSeconds(
            $headers,
            $now,
            $this->configuration['fallbackCacheTtlSeconds']
        );
        $cacheable = $cacheTtlSeconds > 0
            && strtolower(trim($headers['vary'] ?? '')) !== '*';
        if (!$cacheable) {
            $cacheTtlSeconds = 0;
        }

        return [
            'status' => $status,
            'contentType' => $contentType,
            'redirected' => false,
            'elapsedMilliseconds' => max($executorMilliseconds, $measuredMilliseconds),
            'peerAddress' => $primaryAddress,
            'body' => $body,
            'cacheTtlSeconds' => $cacheTtlSeconds,
            'cacheable' => $cacheable,
            'etag' => $headers['etag'] ?? null,
            'lastModified' => $headers['last-modified'] ?? null,
        ];
    }

    /** @param array<string, mixed> $headers @return array<string, string> */
    private static function normalizeResponseHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (
                !is_string($value)
                || preg_match('/^[A-Za-z0-9-]{1,64}$/D', $name) !== 1
                || strlen($value) > 2048
                || preg_match('/[\x00-\x08\x0a-\x1f\x7f]/', $value) === 1
            ) {
                throw new RuntimeException('Tile transport response header is invalid.');
            }
            $normalized[strtolower($name)] = trim($value);
        }

        return $normalized;
    }

    /** @param array<string, string> $headers */
    private static function cacheTtlSeconds(
        array $headers,
        int $now,
        int $fallbackSeconds
    ): int {
        $cacheControl = $headers['cache-control'] ?? '';
        $maximumAge = null;
        $sharedMaximumAge = null;
        foreach (explode(',', strtolower($cacheControl)) as $directive) {
            $directive = trim($directive);
            if (in_array($directive, ['no-store', 'no-cache', 'private'], true)) {
                return 0;
            }
            if (preg_match('/^max-age=(\d+)$/D', $directive, $matches) === 1) {
                $maximumAge = (int) $matches[1];
            }
            if (preg_match('/^s-maxage=(\d+)$/D', $directive, $matches) === 1) {
                $sharedMaximumAge = (int) $matches[1];
            }
        }
        $originMaximumAge = $sharedMaximumAge ?? $maximumAge;
        if ($originMaximumAge !== null) {
            $age = isset($headers['age']) && preg_match('/^\d+$/D', $headers['age']) === 1
                ? (int) $headers['age']
                : 0;

            return min(30 * 86400, max(0, $originMaximumAge - $age));
        }
        if (isset($headers['expires'])) {
            $expiresAt = strtotime($headers['expires']);
            if ($expiresAt !== false) {
                return min(30 * 86400, max(0, $expiresAt - $now));
            }
        }

        return $fallbackSeconds;
    }

    private static function pathPattern(string $template): string
    {
        $quoted = preg_quote($template, '#');

        return '#^' . str_replace(
            ['\\{z\\}', '\\{x\\}', '\\{y\\}'],
            ['(?:0|[1-9][0-9]?)', '(?:0|[1-9][0-9]{0,9})', '(?:0|[1-9][0-9]{0,9})'],
            $quoted
        ) . '$#D';
    }

    private static function requiredCurlIntegerConstant(string $name): int
    {
        if (!defined($name)) {
            throw new RuntimeException('Tile transport cURL peer authorization is unavailable.');
        }
        $value = constant($name);
        if (!is_int($value)) {
            throw new RuntimeException('Tile transport cURL peer authorization is invalid.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private static function executeCurl(array $plan): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Tile transport cURL is unavailable.');
        }
        $prerequisiteOption = self::requiredCurlIntegerConstant('CURLOPT_PREREQFUNCTION');
        $prerequisiteAbort = self::requiredCurlIntegerConstant('CURL_PREREQFUNC_ABORT');
        $prerequisiteOk = self::requiredCurlIntegerConstant('CURL_PREREQFUNC_OK');
        $handle = curl_init($plan['url']);
        if ($handle === false) {
            throw new RuntimeException('Tile transport cURL initialization failed.');
        }
        $body = '';
        $responseHeaders = [];
        $overflow = false;
        $maximumResponseBytes = $plan['maximumResponseBytes'];
        $authorizedAddress = null;
        $headerBytes = 0;
        $options = [
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $plan['headers'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT_MS => min(1500, $plan['timeoutMilliseconds']),
            CURLOPT_TIMEOUT_MS => $plan['timeoutMilliseconds'],
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
            $prerequisiteOption => static function (
                mixed $unusedHandle,
                string $primaryAddress,
                string $localAddress,
                int $primaryPort,
                int $localPort
            ) use (
                &$authorizedAddress,
                $plan,
                $prerequisiteAbort,
                $prerequisiteOk
): int {
                if (
                    $primaryPort !== 443 || !self::isPublicAddress($primaryAddress)
                    || (is_string($plan['pinnedAddress']) && $primaryAddress !== $plan['pinnedAddress'])
                ) {
                    return $prerequisiteAbort;
                }
                $authorizedAddress = $primaryAddress;
                return $prerequisiteOk;
            },
            CURLOPT_WRITEFUNCTION => static function (
                mixed $unusedHandle,
                string $chunk
            ) use (
                &$body,
                &$overflow,
                $maximumResponseBytes
            ): int {
                if (strlen($body) + strlen($chunk) > $maximumResponseBytes) {
                    $overflow = true;
                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function (
                mixed $unusedHandle,
                string $line
            ) use (
                &$responseHeaders,
                &$headerBytes
): int {
                $length = strlen($line);
                $headerBytes += $length;
                if ($headerBytes > 16384) {
                    return 0;
                }
                $trimmed = trim($line);
                if (str_starts_with($trimmed, 'HTTP/')) {
                    $responseHeaders = [];
                    return $length;
                }
                if ($trimmed === '' || !str_contains($line, ':')) {
                    return $length;
                }
                [$name, $value] = explode(':', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (isset($responseHeaders[$name])) {
                    $responseHeaders[$name] .= ', ' . $value;
                } else {
                    $responseHeaders[$name] = $value;
                }

                return $length;
            },
        ];
        if (is_string($plan['pinnedAddress'])) {
            $address = str_contains($plan['pinnedAddress'], ':')
                ? '[' . $plan['pinnedAddress'] . ']' : $plan['pinnedAddress'];
            $options[CURLOPT_RESOLVE] = [$plan['host'] . ':443:' . $address];
        }
        if (defined('CURL_HTTP_VERSION_2TLS')) {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
        }
        if (!curl_setopt_array($handle, $options)) {
            throw new RuntimeException('Tile transport options rejected.');
        }
        $result = curl_exec($handle);
        if ($result === false) {
            $errorCode = curl_errno($handle);
            unset($handle);
            if ($overflow) {
                throw new RuntimeException(
                    'Tile transport response exceeded the byte budget.'
                );
            }
            throw new RuntimeException(
                'Tile transport failed with cURL error ' . $errorCode . '.'
            );
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $primaryAddress = (string) curl_getinfo($handle, CURLINFO_PRIMARY_IP);
        $effectiveUrl = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        $elapsedMilliseconds = (int) round(
            (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000
        );
        unset($handle);

        return [
            'status' => $status,
            'body' => $body,
            'primaryAddress' => $primaryAddress,
            'authorizedAddress' => $authorizedAddress,
            'effectiveUrl' => $effectiveUrl,
            'redirected' => false,
            'elapsedMilliseconds' => $elapsedMilliseconds,
            'headers' => $responseHeaders,
        ];
    }

    private static function isHttpsOrigin(string $origin): bool
    {
        $parts = parse_url($origin);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null)
            && filter_var($parts['host'], FILTER_VALIDATE_IP) === false
            && preg_match(
                '/(?:^|\.)(?:example|invalid|localhost|test)$/iD',
                $parts['host']
            ) !== 1
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment'])
            && (!isset($parts['path']) || $parts['path'] === '' || $parts['path'] === '/')
            && (!isset($parts['port']) || $parts['port'] === 443);
    }

    private static function isPublicAddress(mixed $address): bool
    {
        return is_string($address)
            && filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_GLOBAL_RANGE
            ) !== false
            && (str_contains($address, ':')
                ? !str_starts_with(strtolower($address), 'ff')
                : (int) explode('.', $address)[0] < 224);
    }
}
