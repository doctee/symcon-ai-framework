<?php

declare(strict_types=1);

final class TestOwnTracksPositionMapCandidate extends OwnTracksPositionMap
{
    public int $testNow = 1;
    public int $providerFetchCalls = 0;
    /** @var list<array<string, mixed>> */
    public array $providerResponses = [];

    public function testProcessHookData(): void
    {
        $this->ProcessHookData();
    }

    protected function currentTimestamp(): int
    {
        return $this->testNow;
    }

    protected function fetchProviderTile(
        \OwnTracksPositionMap\Prototype\OwnTracksPinnedHttpsTileTransport $transport,
        string $url,
        array $options,
        array $conditionalHeaders,
        int $now
    ): array {
        $this->providerFetchCalls++;
        $response = array_shift($this->providerResponses);
        if (!is_array($response)) {
            throw new RuntimeException(
                'Synthetic provider transport was not authorized by the test.'
            );
        }

        return $response;
    }
}
