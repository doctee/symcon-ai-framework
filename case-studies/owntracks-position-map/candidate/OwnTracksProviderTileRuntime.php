<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;
use Throwable;

/**
 * Selection-bound composition of provider cache, transport and static fallback.
 */
final class OwnTracksProviderTileRuntime
{
    private readonly OwnTracksTileMissResolver $resolver;

    /** @param array<string, mixed> $configuration */
    public function __construct(
        private readonly OwnTracksTileSelectionAllowlist $allowlist,
        array $configuration,
        private readonly OwnTracksProviderTileCache $cache,
        private readonly OwnTracksTileMissStateStore $stateStore,
        private readonly OwnTracksTileRequestBudget $providerBudget,
        private readonly ?string $selectionKey = null,
        private readonly ?string $requestBudgetKey = null,
        private readonly ?OwnTracksTileDeadline $deadline = null
    ) {
        if (
            ($selectionKey !== null
                && preg_match('/^[a-f0-9]{64}$/D', $selectionKey) !== 1)
            || ($requestBudgetKey !== null
                && preg_match('/^[a-f0-9]{64}$/D', $requestBudgetKey) !== 1)
        ) {
            throw new InvalidArgumentException('Provider runtime key is invalid.');
        }
        $this->resolver = new OwnTracksTileMissResolver(
            $allowlist,
            $configuration,
            $selectionKey
        );
    }

    /**
     * @param callable(int, int, int): (?array{content: string}) $staticReader
     * @param callable(string, array<string, mixed>, array<string, string>, int): array<string, mixed> $providerFetcher
     * @return array{content: string}|null
     */
    public function read(
        int $zoom,
        int $x,
        int $y,
        callable $staticReader,
        callable $providerFetcher,
        int $now,
        int $maximumRequestsPerMinute,
        int $maximumConcurrentRequests
    ): ?array {
        if (
            $now < 0
            || $maximumRequestsPerMinute < 1
            || $maximumRequestsPerMinute > 60
            || $maximumConcurrentRequests < 1
            || $maximumConcurrentRequests > 2
        ) {
            throw new InvalidArgumentException(
                'Provider tile runtime budget is invalid.'
            );
        }
        $selectionFingerprint = $this->selectionKey
            ?? $this->allowlist->fingerprint();

        $deadline = $this->deadline ?? new OwnTracksTileDeadline();
        $startedAt = hrtime(true);
        $currentTime = static fn (): int => $now
            + (int) floor((hrtime(true) - $startedAt) / 1000000000);
        $deadline->remainingMilliseconds();
        $fallbackStatic = $staticReader($zoom, $x, $y);
        if (!$this->allowlist->allows($zoom, $x, $y)) {
            return $fallbackStatic;
        }
        $lookup = $this->cache->lookup($zoom, $x, $y, $currentTime());
        if ($lookup['state'] === 'fresh' && is_string($lookup['content'])) {
            return ['content' => $lookup['content']];
        }
        $reservation = $this->stateStore->withSelection(
            $selectionFingerprint,
            $currentTime(),
            fn (array &$state): ?array => $this->resolver->reserve($zoom, $x, $y, $state, $currentTime())
        );
        if ($reservation === null) {
            return $fallbackStatic;
        }
        $response = null;
        $networkAdmitted = false;
        $admission = null;
        try {
            $deadline->remainingMilliseconds();
            $admission = $this->providerBudget->begin(
                'provider:' . ($this->requestBudgetKey ?? $selectionFingerprint),
                $currentTime(),
                $maximumRequestsPerMinute,
                $maximumConcurrentRequests
            );
            if ($admission['accepted'] && is_array($admission['reservation'])) {
                $options = $reservation['options'];
                $options['timeoutMilliseconds'] = min(
                    $options['timeoutMilliseconds'],
                    $deadline->remainingMilliseconds()
                );
                $networkAdmitted = true;
                $response = $providerFetcher(
                    $reservation['url'],
                    $options,
                    $lookup['conditionalHeaders'],
                    $currentTime()
                );
                $deadline->remainingMilliseconds();
            }
        } catch (Throwable) {
            $response = null;
        } finally {
            if (is_array($admission) && is_array($admission['reservation'])) {
                $this->providerBudget->finish($admission['reservation'], $currentTime());
            }
        }
        $pendingStoreResponse = null;
        if (is_array($response) && ($response['status'] ?? null) === 304) {
            if ($this->cache->refresh304($zoom, $x, $y, $response, $currentTime())) {
                $fresh = $this->cache->lookup($zoom, $x, $y, $currentTime());
                if ($fresh['state'] === 'fresh' && is_string($fresh['content'])) {
                    $response = [
                        'status' => 200, 'contentType' => 'image/png', 'redirected' => false,
                        'elapsedMilliseconds' => $response['elapsedMilliseconds'] ?? 0,
                        'peerAddress' => $response['peerAddress'] ?? '',
                        'body' => $fresh['content'], 'accountedBytes' => 0,
                    ];
                }
            }
        } elseif (is_array($response) && ($response['status'] ?? null) === 200) {
            $pendingStoreResponse = $response;
        }
        $deadline->remainingMilliseconds();
        $result = $this->stateStore->withSelection(
            $selectionFingerprint,
            $currentTime(),
            fn (array &$state): ?array => $this->resolver->complete(
                $reservation,
                $response,
                $state,
                $currentTime(),
                $networkAdmitted
            )
        );
        if ($result !== null && $pendingStoreResponse !== null) {
            $this->cache->store200($zoom, $x, $y, $pendingStoreResponse, $currentTime());
        }

        return $result ?? $fallbackStatic;
    }
}
