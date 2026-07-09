<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Navimow/ApiClient.php';
require_once __DIR__ . '/../distribution/libs/Navimow/OAuthHelper.php';
require_once __DIR__ . '/../distribution/libs/Navimow/PayloadMapper.php';

use Navimow\ApiClient;
use Navimow\ApiException;
use Navimow\OAuthHelper;
use Navimow\PayloadMapper;

$requests = [];
$transport = static function (array $request) use (&$requests): array {
    $requests[] = $request;

    return [
        'status' => 200,
        'body' => json_encode([
            'access_token' => 'ACCESS_TEST_VALUE',
            'refresh_token' => 'REFRESH_TEST_VALUE',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR),
    ];
};

$client = new ApiClient('https://navimow.example.test/', $transport);
$tokenPayload = $client->exchangeAuthorizationCode(
    'CODE_TEST_VALUE',
    'homeassistant',
    'SECRET_TEST_VALUE',
    'http://localhost:1/callback'
);
$tokens = PayloadMapper::parseTokenResponse($tokenPayload);

assertSameValue(
    'https://navimow.example.test',
    $client->getBaseUrl(),
    'Base URL should be normalized.'
);
assertSameValue(
    'ACCESS_TEST_VALUE',
    $tokens['accessToken'],
    'Access token should be parsed internally.'
);
assertSameValue(
    3600,
    $tokens['expiresIn'],
    'Token expiry should be parsed.'
);
assertContainsValue(
    'grant_type=authorization_code',
    $requests[0]['body'],
    'OAuth request should be form encoded.'
);
assertContainsValue(
    'client_secret=SECRET_TEST_VALUE',
    $requests[0]['body'],
    'OAuth request should contain the configured client secret.'
);

$statusRequests = [];
$statusTransport = static function (array $request) use (&$statusRequests): array {
    $statusRequests[] = $request;

    return [
        'status' => 200,
        'body' => json_encode([
            'code' => 1,
            'data' => [
                'payload' => [
                    'devices' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ];
};

$statusClient = new ApiClient(
    'https://navimow.example.test',
    $statusTransport
);
$statusClient->getVehicleStatus('ACCESS_PRIVATE_VALUE', 'DEVICE_001');

assertContainsValue(
    'Authorization: Bearer ACCESS_PRIVATE_VALUE',
    implode("\n", $statusRequests[0]['headers']),
    'Authenticated request should contain the bearer header.'
);
assertMatchesValue(
    '/requestId: [0-9a-f-]{36}/',
    implode("\n", $statusRequests[0]['headers']),
    'Authenticated request should contain a request ID.'
);
assertSameValue(
    '{"devices":[{"id":"DEVICE_001"}]}',
    $statusRequests[0]['body'],
    'Status request body should match the fixture-backed contract.'
);

$state = OAuthHelper::createState();
$authorizationUrl = OAuthHelper::buildAuthorizationUrl(
    'https://login.example.test/smartHome/login',
    'homeassistant',
    'http://localhost:1/callback',
    $state
);
assertContainsValue(
    'state=' . $state,
    $authorizationUrl,
    'Authorization URL should carry OAuth state.'
);
assertSameValue(
    'CODE_001',
    OAuthHelper::parseAuthorizationInput(
        'http://localhost:1/callback?code=CODE_001&state=' . $state,
        $state
    ),
    'Redirect URL should yield the authorization code.'
);
assertSameValue(
    'CODE_ONLY',
    OAuthHelper::parseAuthorizationInput('CODE_ONLY'),
    'Supervised code-only handoff should remain supported.'
);

assertThrows(
    static fn () => OAuthHelper::parseAuthorizationInput(
        'http://localhost:1/callback?code=CODE_001&state=WRONG',
        $state
    ),
    InvalidArgumentException::class,
    'OAuth state mismatch should fail.'
);
assertThrows(
    static fn () => PayloadMapper::parseTokenResponse([
        'access_token' => 'ACCESS_TEST_VALUE',
        'expires_in' => 0,
    ]),
    UnexpectedValueException::class,
    'Non-positive token expiry should fail.'
);
assertThrows(
    static fn () => new ApiClient('http://navimow.example.test'),
    ApiException::class,
    'Plain HTTP base URLs should fail.'
);

$invalidJsonClient = new ApiClient(
    'https://navimow.example.test',
    static fn (array $request): array => [
        'status' => 200,
        'body' => 'not-json',
    ]
);
assertThrows(
    static fn () => $invalidJsonClient->getAuthorizedDevices('TOKEN'),
    ApiException::class,
    'Invalid JSON should fail.'
);

$httpFailureClient = new ApiClient(
    'https://navimow.example.test',
    static fn (array $request): array => [
        'status' => 401,
        'body' => '{"access_token":"MUST_NOT_APPEAR"}',
    ]
);
$httpException = captureException(
    static fn () => $httpFailureClient->getAuthorizedDevices(
        'ACCESS_MUST_NOT_APPEAR'
    )
);
assertSameValue(
    'http',
    $httpException instanceof ApiException
        ? $httpException->getKind()
        : null,
    'HTTP failure should be classified.'
);
assertNotContainsValue(
    'MUST_NOT_APPEAR',
    $httpException->getMessage(),
    'HTTP exception must not contain token or response body.'
);

$authError = PayloadMapper::mapApiError([
    'code' => 4005,
    'desc' => 'CODE_OAUTH_INFO_ILLEGAL',
    'data' => null,
]);
assertSameValue(
    true,
    $authError['reauthRequired'],
    'API code 4005 should require reauthentication.'
);

fwrite(
    STDOUT,
    "Navimow REST client and authentication checks passed.\n"
);

function assertSameValue(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . sprintf(
                ' Expected %s, got %s.',
                var_export($expected, true),
                var_export($actual, true)
            )
        );
    }
}

function assertContainsValue(
    string $needle,
    string $haystack,
    string $message
): void {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertNotContainsValue(
    string $needle,
    string $haystack,
    string $message
): void {
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertMatchesValue(
    string $pattern,
    string $value,
    string $message
): void {
    if (preg_match($pattern, $value) !== 1) {
        throw new RuntimeException($message);
    }
}

function assertThrows(
    callable $callback,
    string $expectedClass,
    string $message
): void {
    $exception = captureException($callback);
    if (!$exception instanceof $expectedClass) {
        throw new RuntimeException(
            $message . sprintf(
                ' Expected %s, got %s.',
                $expectedClass,
                get_class($exception)
            )
        );
    }
}

function captureException(callable $callback): Throwable
{
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected callback to throw.');
}
