<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use JsonException;

/**
 * Case-study-local issuer and verifier for browser-memory tile capabilities.
 */
final class OwnTracksTileCapability
{
    private const VERSION = 1;
    private const MAXIMUM_TOKEN_BYTES = 1024;
    private const MINIMUM_SECRET_BYTES = 32;
    private const MAXIMUM_SECRET_BYTES = 256;
    private const CLIENT_PATTERN = '/^[a-z0-9-]{12,80}$/D';
    private const AUDIENCE_PATTERN = '/^[a-z0-9][a-z0-9:._-]{0,127}$/D';

    /** @return array{token: string, issuedAt: int, expiresAt: int} */
    public static function issue(
        string $secret,
        string $audience,
        string $clientSessionKey,
        int $issuedAt,
        int $ttlSeconds
    ): array {
        self::validateInputs(
            $secret,
            $audience,
            $clientSessionKey,
            $issuedAt,
            $ttlSeconds
        );
        $payload = [
            'v' => self::VERSION,
            'aud' => $audience,
            'sid' => $clientSessionKey,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttlSeconds,
            'jti' => self::base64UrlEncode(random_bytes(16)),
        ];
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $encodedPayload = self::base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $encodedPayload, $secret, true);
        $token = $encodedPayload . '.' . self::base64UrlEncode($signature);
        if (strlen($token) > self::MAXIMUM_TOKEN_BYTES) {
            throw new InvalidArgumentException('Tile capability is too large.');
        }

        return [
            'token' => $token,
            'issuedAt' => $issuedAt,
            'expiresAt' => $issuedAt + $ttlSeconds,
        ];
    }

    /**
     * @return array{
     *   audience: string,
     *   clientSessionKey: string,
     *   issuedAt: int,
     *   expiresAt: int,
     *   capabilityId: string
     * }
     */
    public static function verify(
        string $token,
        string $secret,
        string $expectedAudience,
        int $now
    ): array {
        self::validateSecret($secret);
        self::validateAudience($expectedAudience);
        if (
            $now < 0
            || $token === ''
            || strlen($token) > self::MAXIMUM_TOKEN_BYTES
            || substr_count($token, '.') !== 1
        ) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }
        [$encodedPayload, $encodedSignature] = explode('.', $token, 2);
        $signature = self::base64UrlDecode($encodedSignature);
        $expectedSignature = hash_hmac(
            'sha256',
            $encodedPayload,
            $secret,
            true
        );
        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }
        try {
            $payload = json_decode(
                self::base64UrlDecode($encodedPayload),
                true,
                8,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }
        if (!is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }
        $expectedKeys = ['aud', 'exp', 'iat', 'jti', 'sid', 'v'];
        $actualKeys = array_keys($payload);
        sort($actualKeys);
        if ($actualKeys !== $expectedKeys) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }
        $audience = $payload['aud'] ?? null;
        $clientSessionKey = $payload['sid'] ?? null;
        $issuedAt = $payload['iat'] ?? null;
        $expiresAt = $payload['exp'] ?? null;
        $capabilityId = $payload['jti'] ?? null;
        if (
            ($payload['v'] ?? null) !== self::VERSION
            || !is_string($audience)
            || !hash_equals($expectedAudience, $audience)
            || !is_string($clientSessionKey)
            || preg_match(self::CLIENT_PATTERN, $clientSessionKey) !== 1
            || !is_int($issuedAt)
            || !is_int($expiresAt)
            || $issuedAt < 0
            || $issuedAt > $now + 5
            || $expiresAt <= $now
            || $expiresAt - $issuedAt < 60
            || $expiresAt - $issuedAt > 900
            || !is_string($capabilityId)
            || strlen(self::base64UrlDecode($capabilityId)) !== 16
        ) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }

        return [
            'audience' => $audience,
            'clientSessionKey' => $clientSessionKey,
            'issuedAt' => $issuedAt,
            'expiresAt' => $expiresAt,
            'capabilityId' => $capabilityId,
        ];
    }

    private static function validateInputs(
        string $secret,
        string $audience,
        string $clientSessionKey,
        int $issuedAt,
        int $ttlSeconds
    ): void {
        self::validateSecret($secret);
        self::validateAudience($audience);
        if (
            preg_match(self::CLIENT_PATTERN, $clientSessionKey) !== 1
            || $issuedAt < 0
            || $ttlSeconds < 60
            || $ttlSeconds > 900
        ) {
            throw new InvalidArgumentException(
                'Tile capability input is invalid.'
            );
        }
    }

    private static function validateSecret(string $secret): void
    {
        $length = strlen($secret);
        if (
            $length < self::MINIMUM_SECRET_BYTES
            || $length > self::MAXIMUM_SECRET_BYTES
        ) {
            throw new InvalidArgumentException(
                'Tile capability secret is invalid.'
            );
        }
    }

    private static function validateAudience(string $audience): void
    {
        if (preg_match(self::AUDIENCE_PATTERN, $audience) !== 1) {
            throw new InvalidArgumentException(
                'Tile capability audience is invalid.'
            );
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }
        $padding = strlen($value) % 4;
        $decoded = base64_decode(
            strtr($value, '-_', '+/')
                . ($padding === 0 ? '' : str_repeat('=', 4 - $padding)),
            true
        );
        if ($decoded === false || self::base64UrlEncode($decoded) !== $value) {
            throw new InvalidArgumentException('Tile capability is invalid.');
        }

        return $decoded;
    }
}
