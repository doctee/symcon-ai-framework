<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksTileAccessPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksTileCapability;
use OwnTracksPositionMap\Prototype\OwnTracksTileRequestBudget;
use OwnTracksPositionMap\Prototype\OwnTracksTileWebhookAdapter;

require_once __DIR__ . '/bootstrap.php';

const WEBHOOK_TEST_SECRET =
    'synthetic-webhook-only-secret-000000000000000000000000';
const WEBHOOK_TEST_AUDIENCE = 'owntracks-position-map:webhook-test';
const WEBHOOK_TEST_NOW = 1_725_184_000;

/** @return array<string, mixed> */
function webhookPolicy(array $overrides = []): array
{
    return array_replace([
        'mode' => 'symcon-webhook',
        'connectReachable' => true,
        'authenticationMode' => 'ephemeral-header-capability',
        'headerName' => 'X-SAEF-Tile-Capability',
        'hookPathPrefix' => '/hook/owntracks-position-map',
        'connectForwardingVerified' => true,
        'headerCanonicalizationVerified' => true,
        'tokenTtlSeconds' => 300,
        'refreshBeforeExpirySeconds' => 60,
        'maximumRequestsPerMinute' => 30,
        'maximumConcurrentRequests' => 4,
    ], $overrides);
}

/** @return array<string, string> */
function webhookServer(array $overrides = []): array
{
    return array_replace([
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/hook/owntracks-position-map/3/4/2.png',
        'CONTENT_LENGTH' => '0',
        'QUERY_STRING' => '',
    ], $overrides);
}

/** @return list<array{name: mixed, value: mixed}> */
function webhookHeaders(string $token): array
{
    return [['name' => 'X-SAEF-Tile-Capability', 'value' => $token]];
}

/** @return array{content: string} */
function webhookPng(): array
{
    $content = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lE'
        . 'QVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($content)) {
        throw new RuntimeException('Synthetic WebHook PNG is invalid.');
    }

    return ['content' => $content];
}

function webhookBudgetRoot(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-webhook-budget-'
        . bin2hex(random_bytes(8));
}

function removeWebhookBudgetRoot(string $root): void
{
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-webhook-budget-';
    if (!str_starts_with($root, $prefix) || !is_dir($root) || is_link($root)) {
        throw new RuntimeException('Refusing unsafe WebHook budget cleanup.');
    }
    $items = scandir($root);
    if (!is_array($items)) {
        throw new RuntimeException('WebHook budget directory cannot be read.');
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $root . DIRECTORY_SEPARATOR . $item;
        if (!is_file($path) || is_link($path) || !unlink($path)) {
            throw new RuntimeException('WebHook budget fixture cannot be removed.');
        }
    }
    if (!rmdir($root)) {
        throw new RuntimeException('WebHook budget directory cannot be removed.');
    }
}

$budgetRoots = [webhookBudgetRoot(), webhookBudgetRoot(), webhookBudgetRoot()];
try {
    $issuanceBudget = new OwnTracksTileRequestBudget($budgetRoots[0]);
    try {
        OwnTracksTileWebhookAdapter::issueCapability(
            ['requestGeneration' => 1, 'clientSessionKey' => 'synthetic-client-01'],
            ['mode' => 'none'],
            WEBHOOK_TEST_SECRET,
            WEBHOOK_TEST_AUDIENCE,
            WEBHOOK_TEST_NOW,
            $issuanceBudget
        );
        throw new RuntimeException('Disabled capability issuance was accepted.');
    } catch (InvalidArgumentException) {
    }

    $message = OwnTracksTileWebhookAdapter::issueCapability(
        ['requestGeneration' => 7, 'clientSessionKey' => 'synthetic-client-01'],
        webhookPolicy(),
        WEBHOOK_TEST_SECRET,
        WEBHOOK_TEST_AUDIENCE,
        WEBHOOK_TEST_NOW,
        $issuanceBudget
    );
    assertSameValue('tileCapability', $message['action'], 'Capability action');
    assertSameValue(7, $message['requestGeneration'], 'Capability generation');
    assertTrue(
        !str_contains(serialize($message), WEBHOOK_TEST_SECRET),
        'Signing secret leaked into capability response.'
    );

    foreach (
        [
        ['requestGeneration' => 1, 'clientSessionKey' => 'short'],
        [
            'requestGeneration' => 1,
            'clientSessionKey' => 'synthetic-client-01',
            'audience' => 'attacker',
        ],
        ] as $invalidCapabilityRequest
    ) {
        try {
            OwnTracksTileWebhookAdapter::issueCapability(
                $invalidCapabilityRequest,
                webhookPolicy(),
                WEBHOOK_TEST_SECRET,
                WEBHOOK_TEST_AUDIENCE,
                WEBHOOK_TEST_NOW,
                $issuanceBudget
            );
            throw new RuntimeException('Invalid capability request was accepted.');
        } catch (InvalidArgumentException) {
        }
    }

    for ($generation = 8; $generation <= 10; $generation++) {
        OwnTracksTileWebhookAdapter::issueCapability(
            [
            'requestGeneration' => $generation,
            'clientSessionKey' => 'synthetic-client-01',
            ],
            webhookPolicy(),
            WEBHOOK_TEST_SECRET,
            WEBHOOK_TEST_AUDIENCE,
            WEBHOOK_TEST_NOW,
            new OwnTracksTileRequestBudget($budgetRoots[0])
        );
    }
    try {
        OwnTracksTileWebhookAdapter::issueCapability(
            ['requestGeneration' => 11, 'clientSessionKey' => 'synthetic-client-01'],
            webhookPolicy(),
            WEBHOOK_TEST_SECRET,
            WEBHOOK_TEST_AUDIENCE,
            WEBHOOK_TEST_NOW,
            new OwnTracksTileRequestBudget($budgetRoots[0])
        );
        throw new RuntimeException('Capability issuance rate limit was bypassed.');
    } catch (InvalidArgumentException) {
    }

    $budget = new OwnTracksTileRequestBudget($budgetRoots[0]);
    $accepted = OwnTracksTileWebhookAdapter::handle(
        webhookServer(),
        webhookHeaders($message['token']),
        false,
        webhookPolicy(),
        WEBHOOK_TEST_SECRET,
        WEBHOOK_TEST_AUDIENCE,
        18,
        'synthetic-v1',
        null,
        $budget,
        WEBHOOK_TEST_NOW + 1,
        static fn (int $zoom, int $x, int $y): array => webhookPng()
    );
    assertSameValue(200, $accepted['status'], 'Adapter accepted status');
    assertSameValue('accepted', $accepted['classification'], 'Adapter result');
    assertTrue(
        !isset($accepted['headers']['Access-Control-Allow-Origin']),
        'Adapter emitted a CORS authority.'
    );
    assertTrue(
        !str_contains(serialize($accepted), $message['token']),
        'Capability leaked into adapter response.'
    );

    $wrongAudience = OwnTracksTileCapability::issue(
        WEBHOOK_TEST_SECRET,
        'owntracks-position-map:wrong-audience',
        'synthetic-client-02',
        WEBHOOK_TEST_NOW,
        300
    );

    $negativeRequests = [
        [webhookServer(), [], false],
        [webhookServer(), webhookHeaders($message['token'] . 'x'), false],
        [webhookServer(), webhookHeaders($wrongAudience['token']), false],
        [
            webhookServer(),
            array_merge(
                webhookHeaders($message['token']),
                [['name' => 'x-saef-tile-capability', 'value' => $message['token']]]
            ),
            false,
        ],
        [
            webhookServer(),
            webhookHeaders($message['token'] . ',' . $message['token']),
            false,
        ],
        [webhookServer(['REQUEST_METHOD' => 'POST']), webhookHeaders($message['token']), false],
        [webhookServer(['REQUEST_URI' => '/hook/owntracks-position-map/3/4/2.png?x=1']), webhookHeaders($message['token']), false],
        [webhookServer(['QUERY_STRING' => 'x=1']), webhookHeaders($message['token']), false],
        [webhookServer(['CONTENT_LENGTH' => '1']), webhookHeaders($message['token']), true],
        [webhookServer(['CONTENT_LENGTH' => null]), webhookHeaders($message['token']), true],
        [webhookServer(), [['name' => "Bad\nHeader", 'value' => 'x']], false],
        [
            webhookServer(),
            array_fill(0, 65, ['name' => 'X-Test', 'value' => 'x']),
            false,
        ],
        [
            webhookServer(),
            [['name' => 'X-Test', 'value' => str_repeat('x', 2049)]],
            false,
        ],
    ];
    foreach ($negativeRequests as [$server, $headers, $bodyPresent]) {
        $rejected = OwnTracksTileWebhookAdapter::handle(
            $server,
            $headers,
            $bodyPresent,
            webhookPolicy(),
            WEBHOOK_TEST_SECRET,
            WEBHOOK_TEST_AUDIENCE,
            18,
            'synthetic-v1',
            null,
            $budget,
            WEBHOOK_TEST_NOW + 1,
            static function (int $zoom, int $x, int $y): never {
                throw new RuntimeException('Rejected request reached tile reader.');
            }
        );
        assertSameValue(404, $rejected['status'], 'Adapter rejection status');
        assertSameValue('Not found', $rejected['body'], 'Adapter rejection body');
    }

    $persistentBudget = new OwnTracksTileRequestBudget($budgetRoots[1]);
    for ($index = 0; $index < 30; $index++) {
        $response = OwnTracksTileWebhookAdapter::handle(
            webhookServer(),
            webhookHeaders($message['token']),
            false,
            webhookPolicy(),
            WEBHOOK_TEST_SECRET,
            WEBHOOK_TEST_AUDIENCE,
            18,
            'synthetic-v1',
            null,
            new OwnTracksTileRequestBudget($budgetRoots[1]),
            WEBHOOK_TEST_NOW + 1,
            static fn (int $zoom, int $x, int $y): array => webhookPng()
        );
        assertSameValue(200, $response['status'], 'Persistent in-budget request');
    }
    $limited = OwnTracksTileWebhookAdapter::handle(
        webhookServer(),
        webhookHeaders($message['token']),
        false,
        webhookPolicy(),
        WEBHOOK_TEST_SECRET,
        WEBHOOK_TEST_AUDIENCE,
        18,
        'synthetic-v1',
        null,
        $persistentBudget,
        WEBHOOK_TEST_NOW + 1,
        static fn (int $zoom, int $x, int $y): array => webhookPng()
    );
    assertSameValue(429, $limited['status'], 'Persistent rate limit');

    $claims = OwnTracksTileCapability::verify(
        $message['token'],
        WEBHOOK_TEST_SECRET,
        WEBHOOK_TEST_AUDIENCE,
        WEBHOOK_TEST_NOW + 1
    );
    $concurrencyBudget = new OwnTracksTileRequestBudget($budgetRoots[2]);
    $reservations = [];
    for ($index = 0; $index < 4; $index++) {
        $admission = $concurrencyBudget->begin(
            $claims['capabilityId'],
            WEBHOOK_TEST_NOW + 1,
            30,
            4
        );
        assertTrue($admission['accepted'], 'Concurrency fixture admission failed.');
        $reservations[] = $admission['reservation'];
    }
    $concurrencyLimited = OwnTracksTileWebhookAdapter::handle(
        webhookServer(),
        webhookHeaders($message['token']),
        false,
        webhookPolicy(),
        WEBHOOK_TEST_SECRET,
        WEBHOOK_TEST_AUDIENCE,
        18,
        'synthetic-v1',
        null,
        $concurrencyBudget,
        WEBHOOK_TEST_NOW + 1,
        static fn (int $zoom, int $x, int $y): array => webhookPng()
    );
    assertSameValue(429, $concurrencyLimited['status'], 'Persistent concurrency limit');
    foreach ($reservations as $reservation) {
        if (is_array($reservation)) {
            $concurrencyBudget->finish($reservation, WEBHOOK_TEST_NOW + 1);
        }
    }

    $stale = $concurrencyBudget->begin(
        'stale-capability',
        WEBHOOK_TEST_NOW + 1,
        30,
        1
    );
    assertTrue($stale['accepted'], 'Stale lease fixture failed.');
    $staleLimited = $concurrencyBudget->begin(
        'stale-capability',
        WEBHOOK_TEST_NOW + 1,
        30,
        1
    );
    assertSameValue(
        'concurrency-limited',
        $staleLimited['classification'],
        'Active lease was not enforced.'
    );
    $recovered = $concurrencyBudget->begin(
        'stale-capability',
        WEBHOOK_TEST_NOW + 32,
        30,
        1
    );
    assertTrue($recovered['accepted'], 'Expired lease was not recovered.');

    $minuteLease = $concurrencyBudget->begin(
        'minute-boundary-capability',
        WEBHOOK_TEST_NOW + 59,
        30,
        1
    );
    assertTrue($minuteLease['accepted'], 'Minute-boundary lease fixture failed.');
    $minuteLimited = $concurrencyBudget->begin(
        'minute-boundary-capability',
        WEBHOOK_TEST_NOW + 60,
        30,
        1
    );
    assertSameValue(
        'concurrency-limited',
        $minuteLimited['classification'],
        'Minute rollover discarded an active lease.'
    );

    $state = file_get_contents($budgetRoots[1] . '/budget.json');
    assertTrue(is_string($state), 'Persistent budget state is missing.');
    foreach (
        [$message['token'], 'synthetic-client-01', WEBHOOK_TEST_AUDIENCE] as $sensitiveValue
    ) {
        assertTrue(
            !str_contains($state, $sensitiveValue),
            'Persistent budget state contains sensitive material.'
        );
    }
    assertSameValue(
        1,
        file_put_contents($budgetRoots[1] . '/budget.json', '{'),
        'Corrupt budget fixture write'
    );
    $failClosed = OwnTracksTileWebhookAdapter::handle(
        webhookServer(),
        webhookHeaders($message['token']),
        false,
        webhookPolicy(),
        WEBHOOK_TEST_SECRET,
        WEBHOOK_TEST_AUDIENCE,
        18,
        'synthetic-v1',
        null,
        new OwnTracksTileRequestBudget($budgetRoots[1]),
        WEBHOOK_TEST_NOW + 1,
        static fn (int $zoom, int $x, int $y): array => webhookPng()
    );
    assertSameValue(503, $failClosed['status'], 'Corrupt budget must fail closed');
    assertTrue(
        !str_contains(serialize($failClosed), $budgetRoots[1]),
        'Failure response leaked a private path.'
    );
} finally {
    foreach ($budgetRoots as $budgetRoot) {
        if (is_dir($budgetRoot)) {
            removeWebhookBudgetRoot($budgetRoot);
        }
    }
}

fwrite(STDOUT, "OwnTracks tile WebHook adapter security tests passed.\n");
