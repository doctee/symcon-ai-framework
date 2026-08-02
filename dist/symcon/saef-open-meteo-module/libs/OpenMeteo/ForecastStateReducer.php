<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class ForecastStateReducer
{
    public const STATE_UNCONFIGURED = 'unconfigured';
    public const STATE_CURRENT = 'current';
    public const STATE_STALE = 'stale';
    public const STATE_WARNING = 'warning';
    public const STATE_ERROR = 'error';

    /**
     * @return array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * }
     */
    public static function initial(string $configurationHash, int $maxRetries): array
    {
        self::assertHash($configurationHash);
        if ($maxRetries < 0 || $maxRetries > 10) {
            throw new InvalidArgumentException('Forecast maximum retries are invalid.');
        }

        return [
            'state' => self::STATE_UNCONFIGURED,
            'configurationHash' => $configurationHash,
            'hasData' => false,
            'lastAttempt' => null,
            'lastSuccess' => null,
            'validFrom' => null,
            'validTo' => null,
            'retryCount' => 0,
            'maxRetries' => $maxRetries,
            'errorCode' => null,
        ];
    }

    /**
     * @param array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * } $state
     *
     * @return array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * }
     */
    public static function success(
        array $state,
        int $attemptedAt,
        int $validFrom,
        int $validTo
    ): array {
        self::assertState($state);
        if ($attemptedAt < 0 || $validFrom < 0 || $validTo <= $validFrom) {
            throw new InvalidArgumentException('Forecast success timing is invalid.');
        }

        $state['state'] = self::STATE_CURRENT;
        $state['hasData'] = true;
        $state['lastAttempt'] = $attemptedAt;
        $state['lastSuccess'] = $attemptedAt;
        $state['validFrom'] = $validFrom;
        $state['validTo'] = $validTo;
        $state['retryCount'] = 0;
        $state['errorCode'] = null;

        return $state;
    }

    /**
     * @param array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * } $state
     *
     * @return array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * }
     */
    public static function failure(
        array $state,
        int $attemptedAt,
        string $errorCode,
        bool $retryable
    ): array {
        self::assertState($state);
        if ($attemptedAt < 0 || preg_match('/^[a-z][a-z0-9_]*$/', $errorCode) !== 1) {
            throw new InvalidArgumentException('Forecast failure metadata is invalid.');
        }

        $state['lastAttempt'] = $attemptedAt;
        $state['errorCode'] = $errorCode;
        if ($retryable) {
            $state['retryCount'] = min($state['retryCount'] + 1, $state['maxRetries']);
            $state['state'] = $state['hasData'] ? self::STATE_WARNING : self::STATE_ERROR;
        } else {
            $state['retryCount'] = $state['maxRetries'];
            $state['state'] = $state['hasData'] ? self::STATE_WARNING : self::STATE_ERROR;
        }

        return $state;
    }

    /**
     * @param array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * } $state
     *
     * @return array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * }
     */
    public static function evaluateFreshness(
        array $state,
        int $now,
        int $staleAfterSeconds
    ): array {
        self::assertState($state);
        if ($now < 0 || $staleAfterSeconds < 1) {
            throw new InvalidArgumentException('Forecast freshness policy is invalid.');
        }
        if (!$state['hasData'] || $state['lastSuccess'] === null) {
            return $state;
        }
        if ($now - $state['lastSuccess'] > $staleAfterSeconds) {
            $state['state'] = self::STATE_STALE;
        } elseif ($state['errorCode'] === null) {
            $state['state'] = self::STATE_CURRENT;
        }

        return $state;
    }

    /**
     * @param array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * } $state
     *
     * @return array{
     *     state: string,
     *     configurationHash: string,
     *     hasData: bool,
     *     lastAttempt: ?int,
     *     lastSuccess: ?int,
     *     validFrom: ?int,
     *     validTo: ?int,
     *     retryCount: int,
     *     maxRetries: int,
     *     errorCode: ?string
     * }
     */
    public static function configurationChanged(array $state, string $configurationHash): array
    {
        self::assertState($state);
        self::assertHash($configurationHash);
        if ($state['configurationHash'] === $configurationHash) {
            return $state;
        }

        return self::initial($configurationHash, $state['maxRetries']);
    }

    /** @param array<string, mixed> $state */
    private static function assertState(array $state): void
    {
        $required = [
            'state',
            'configurationHash',
            'hasData',
            'lastAttempt',
            'lastSuccess',
            'validFrom',
            'validTo',
            'retryCount',
            'maxRetries',
            'errorCode',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $state)) {
                throw new InvalidArgumentException('Forecast state is incomplete.');
            }
        }
        self::assertHash(is_string($state['configurationHash']) ? $state['configurationHash'] : '');
    }

    private static function assertHash(string $configurationHash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $configurationHash) !== 1) {
            throw new InvalidArgumentException('Forecast configuration hash is invalid.');
        }
    }
}
