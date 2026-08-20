<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\DwdNowcast;

use InvalidArgumentException;
use JsonException;

final class TransportDiagnostics
{
    public const CLASS_TLS_RECORD = 'tls_record';
    public const CLASS_TLS_HANDSHAKE = 'tls_handshake';
    public const CLASS_DNS_TIMEOUT = 'dns_timeout';
    public const CLASS_TIMEOUT = 'timeout';
    public const CLASS_CONNECTION = 'connection';
    public const CLASS_HTTP_SERVER_ERROR = 'http_server_error';
    public const CLASS_NO_RESPONSE = 'no_response';
    public const CLASS_RESPONSE_TOO_LARGE = 'response_too_large';
    public const CLASS_EXCEPTION = 'exception';

    private const SCHEMA_VERSION = 1;

    /** @return array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: ?int, lastFailureClass: ?string, lastRecoveryAt: ?int, lastRecoveryAttempts: int} */
    public static function fromJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::initial();
        }

        if (!is_array($decoded) || ($decoded['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            return self::initial();
        }

        $failureCount = $decoded['failureCount'] ?? null;
        $consecutiveFailures = $decoded['consecutiveFailures'] ?? null;
        $lastFailureAt = $decoded['lastFailureAt'] ?? null;
        $lastFailureClass = $decoded['lastFailureClass'] ?? null;
        $lastRecoveryAt = $decoded['lastRecoveryAt'] ?? null;
        $lastRecoveryAttempts = $decoded['lastRecoveryAttempts'] ?? null;
        if (
            !is_int($failureCount)
            || $failureCount < 0
            || !is_int($consecutiveFailures)
            || $consecutiveFailures < 0
            || ($lastFailureAt !== null && (!is_int($lastFailureAt) || $lastFailureAt <= 0))
            || ($lastFailureClass !== null && !self::isFailureClass($lastFailureClass))
            || ($lastRecoveryAt !== null && (!is_int($lastRecoveryAt) || $lastRecoveryAt <= 0))
            || !is_int($lastRecoveryAttempts)
            || $lastRecoveryAttempts < 0
        ) {
            return self::initial();
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'failureCount' => $failureCount,
            'consecutiveFailures' => $consecutiveFailures,
            'lastFailureAt' => $lastFailureAt,
            'lastFailureClass' => $lastFailureClass,
            'lastRecoveryAt' => $lastRecoveryAt,
            'lastRecoveryAttempts' => $lastRecoveryAttempts,
        ];
    }

    /**
     * @param array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: ?int, lastFailureClass: ?string, lastRecoveryAt: ?int, lastRecoveryAttempts: int} $state
     *
     * @return array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: int, lastFailureClass: string, lastRecoveryAt: ?int, lastRecoveryAttempts: int}
     */
    public static function failure(array $state, int $attemptedAt, string $failureClass): array
    {
        if ($attemptedAt <= 0 || !self::isFailureClass($failureClass)) {
            throw new InvalidArgumentException('Transport diagnostic failure is invalid.');
        }

        $state['failureCount']++;
        $state['consecutiveFailures']++;
        $state['lastFailureAt'] = $attemptedAt;
        $state['lastFailureClass'] = $failureClass;

        return $state;
    }

    /**
     * @param array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: ?int, lastFailureClass: ?string, lastRecoveryAt: ?int, lastRecoveryAttempts: int} $state
     *
     * @return array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: ?int, lastFailureClass: ?string, lastRecoveryAt: ?int, lastRecoveryAttempts: int}
     */
    public static function success(array $state, int $succeededAt): array
    {
        if ($succeededAt <= 0) {
            throw new InvalidArgumentException('Transport diagnostic success time is invalid.');
        }
        if ($state['consecutiveFailures'] > 0) {
            $state['lastRecoveryAt'] = $succeededAt;
            $state['lastRecoveryAttempts'] = $state['consecutiveFailures'];
            $state['consecutiveFailures'] = 0;
        }

        return $state;
    }

    public static function classifyWarning(string $message): ?string
    {
        $normalized = strtolower($message);
        if (
            str_contains($normalized, 'bad record type')
            || str_contains($normalized, 'record layer failure')
            || str_contains($normalized, 'openssl ssl_read')
        ) {
            return self::CLASS_TLS_RECORD;
        }
        if (
            str_contains($normalized, 'unsolicited extension')
            || (
                str_contains($normalized, 'ssl connect error')
                && str_contains($normalized, 'tls connect error')
            )
        ) {
            return self::CLASS_TLS_HANDSHAKE;
        }
        if (preg_match('/\berror\s+5[0-9]{2}\b/', $normalized) === 1) {
            return self::CLASS_HTTP_SERVER_ERROR;
        }
        if (
            str_contains($normalized, 'resolving timed out')
            || str_contains($normalized, 'could not resolve host')
        ) {
            return self::CLASS_DNS_TIMEOUT;
        }
        if (str_contains($normalized, 'timed out') || str_contains($normalized, 'timeout was reached')) {
            return self::CLASS_TIMEOUT;
        }
        if (
            str_contains($normalized, 'failure when receiving data from the peer')
            || str_contains($normalized, 'failed to connect')
            || str_contains($normalized, 'connection refused')
            || str_contains($normalized, 'connection reset')
        ) {
            return self::CLASS_CONNECTION;
        }

        return null;
    }

    /** @param array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: ?int, lastFailureClass: ?string, lastRecoveryAt: ?int, lastRecoveryAttempts: int} $state */
    public static function toJson(array $state): string
    {
        return json_encode(
            $state,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /** @return array{schemaVersion: int, failureCount: int, consecutiveFailures: int, lastFailureAt: null, lastFailureClass: null, lastRecoveryAt: null, lastRecoveryAttempts: int} */
    private static function initial(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'failureCount' => 0,
            'consecutiveFailures' => 0,
            'lastFailureAt' => null,
            'lastFailureClass' => null,
            'lastRecoveryAt' => null,
            'lastRecoveryAttempts' => 0,
        ];
    }

    private static function isFailureClass(mixed $failureClass): bool
    {
        return is_string($failureClass) && in_array(
            $failureClass,
            [
                self::CLASS_TLS_RECORD,
                self::CLASS_TLS_HANDSHAKE,
                self::CLASS_DNS_TIMEOUT,
                self::CLASS_TIMEOUT,
                self::CLASS_CONNECTION,
                self::CLASS_HTTP_SERVER_ERROR,
                self::CLASS_NO_RESPONSE,
                self::CLASS_RESPONSE_TOO_LARGE,
                self::CLASS_EXCEPTION,
            ],
            true
        );
    }
}
