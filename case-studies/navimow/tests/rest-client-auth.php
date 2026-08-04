<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Navimow/ApiClient.php';
require_once __DIR__ . '/../distribution/libs/Navimow/CommandContract.php';
require_once __DIR__
    . '/../distribution/libs/Navimow/MqttCredentialMapper.php';
require_once __DIR__ . '/../distribution/libs/Navimow/OAuthHelper.php';
require_once __DIR__ . '/../distribution/libs/Navimow/PayloadMapper.php';

use Navimow\ApiClient;
use Navimow\ApiException;
use Navimow\CommandContract;
use Navimow\MqttCredentialMapper;
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

$mqttRequests = [];
$mqttClient = new ApiClient(
    'https://navimow.example.test',
    static function (array $request) use (&$mqttRequests): array {
        $mqttRequests[] = $request;

        return [
            'status' => 200,
            'body' => file_get_contents(
                __DIR__
                    . '/../fixtures/mqtt/mqtt-credential-success.json'
            ),
        ];
    }
);
$mqttPayload = $mqttClient->getMqttUserInfo(
    'SYNTHETIC_MQTT_ACCESS'
);
$mqttCredentials = MqttCredentialMapper::map($mqttPayload);
assertSameValue(
    'GET',
    $mqttRequests[0]['method'],
    'MQTT credential request must use GET.'
);
assertSameValue(
    '/openapi/mqtt/userInfo/get/v2',
    parse_url($mqttRequests[0]['url'], PHP_URL_PATH),
    'MQTT credential request used an unexpected endpoint.'
);
assertSameValue(
    null,
    $mqttRequests[0]['body'],
    'MQTT credential request must not have a body.'
);
$mqttHeaders = implode("\n", $mqttRequests[0]['headers']);
assertContainsValue(
    'Accept: application/json',
    $mqttHeaders,
    'MQTT credential request must accept JSON.'
);
assertContainsValue(
    'Authorization: Bearer SYNTHETIC_MQTT_ACCESS',
    $mqttHeaders,
    'MQTT credential request must carry the access token.'
);
assertMatchesValue(
    '/requestId: [0-9a-f-]{36}/',
    $mqttHeaders,
    'MQTT credential request must contain a request ID.'
);
assertSameValue(
    'wss://mqtt.example.test/mqtt?ticket=SYNTHETIC_WSS_TICKET',
    $mqttCredentials['wssUrl'],
    'Relative MQTT path was not combined with the WSS host.'
);
assertSameValue(
    'SYNTHETIC_MQTT_USER',
    $mqttCredentials['mqttUsername'],
    'MQTT username was not mapped.'
);
assertSameValue(
    'SYNTHETIC_MQTT_PASSWORD',
    $mqttCredentials['mqttPassword'],
    'MQTT password was not mapped.'
);

$absoluteCredentials = MqttCredentialMapper::map([
    'code' => 1,
    'data' => [
        'mqttHost' => 'wss://ignored.example.test',
        'mqttUrl' => 'wss://mqtt.example.test/mqtt?ticket=ABSOLUTE',
        'userName' => 'SYNTHETIC_USER',
        'pwdInfo' => 'SYNTHETIC_PASSWORD',
    ],
]);
assertSameValue(
    'wss://mqtt.example.test/mqtt?ticket=ABSOLUTE',
    $absoluteCredentials['wssUrl'],
    'Absolute WSS endpoint was not preserved.'
);

$completeCredentialData = [
    'mqttHost' => 'wss://mqtt.example.test',
    'mqttUrl' => '/mqtt?ticket=SYNTHETIC',
    'userName' => 'SYNTHETIC_USER',
    'pwdInfo' => 'SYNTHETIC_PASSWORD',
];
foreach (array_keys($completeCredentialData) as $missingField) {
    $missingData = $completeCredentialData;
    unset($missingData[$missingField]);
    assertThrows(
        static fn () => MqttCredentialMapper::map([
            'code' => 1,
            'data' => $missingData,
        ]),
        UnexpectedValueException::class,
        'Every required MQTT credential field must fail when missing.'
    );
}

$invalidCredentialPayloads = [
    [
        'code' => 1,
        'data' => [
            'mqttHost' => 'wss://mqtt.example.test',
            'mqttUrl' => '/mqtt',
            'userName' => '',
            'pwdInfo' => 'SECRET_PASSWORD',
        ],
    ],
    [
        'code' => 1,
        'data' => [
            'mqttHost' => 'wss://mqtt.example.test',
            'mqttUrl' => 'ws://mqtt.example.test/mqtt',
            'userName' => 'SECRET_USER',
            'pwdInfo' => 'SECRET_PASSWORD',
        ],
    ],
    [
        'code' => 1,
        'data' => [
            'mqttHost' => 'wss://mqtt.example.test:8443',
            'mqttUrl' => '/mqtt',
            'userName' => 'SECRET_USER',
            'pwdInfo' => 'SECRET_PASSWORD',
        ],
    ],
    [
        'code' => 1,
        'data' => [
            'mqttHost' => 'wss://mqtt.example.test',
            'mqttUrl' => '/mqtt#SECRET_FRAGMENT',
            'userName' => 'SECRET_USER',
            'pwdInfo' => 'SECRET_PASSWORD',
        ],
    ],
    [
        'code' => 1,
        'data' => [
            'mqttHost' => 'wss://mqtt.example.test',
            'mqttUrl' => "/mqtt\nSECRET_CONTROL",
            'userName' => 'SECRET_USER',
            'pwdInfo' => 'SECRET_PASSWORD',
        ],
    ],
];
foreach ($invalidCredentialPayloads as $invalidCredentialPayload) {
    $credentialException = captureException(
        static fn () => MqttCredentialMapper::map(
            $invalidCredentialPayload
        )
    );
    assertSameValue(
        UnexpectedValueException::class,
        get_class($credentialException),
        'Invalid MQTT credentials must fail closed.'
    );
    foreach (
        [
            'SECRET_PASSWORD',
            'SECRET_USER',
            'SECRET_FRAGMENT',
            'SECRET_CONTROL',
        ] as $secret
    ) {
        assertNotContainsValue(
            $secret,
            $credentialException->getMessage(),
            'MQTT mapper exception exposed a credential value.'
        );
    }
}

$businessPayload = json_decode(
    (string) file_get_contents(
        __DIR__
            . '/../fixtures/mqtt/mqtt-credential-business-error.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$businessException = captureException(
    static fn () => MqttCredentialMapper::map($businessPayload)
);
assertNotContainsValue(
    'SYNTHETIC_SECRET_MUST_NOT_APPEAR',
    $businessException->getMessage(),
    'MQTT business error exposed the server description.'
);

$mqttInvalidJsonClient = new ApiClient(
    'https://navimow.example.test',
    static fn (array $request): array => [
        'status' => 200,
        'body' => '{"pwdInfo":"TRANSPORT_SECRET"',
    ]
);
$mqttInvalidJsonException = captureException(
    static fn () => $mqttInvalidJsonClient->getMqttUserInfo(
        'BEARER_SECRET'
    )
);
assertNotContainsValue(
    'TRANSPORT_SECRET',
    $mqttInvalidJsonException->getMessage(),
    'Malformed MQTT response exposed its body.'
);
assertNotContainsValue(
    'BEARER_SECRET',
    $mqttInvalidJsonException->getMessage(),
    'Malformed MQTT response exposed its bearer token.'
);

$mqttHttpFailureClient = new ApiClient(
    'https://navimow.example.test',
    static fn (array $request): array => [
        'status' => 503,
        'body' => '{"pwdInfo":"HTTP_SECRET"}',
    ]
);
$mqttHttpException = captureException(
    static fn () => $mqttHttpFailureClient->getMqttUserInfo(
        'HTTP_BEARER_SECRET'
    )
);
assertNotContainsValue(
    'HTTP_SECRET',
    $mqttHttpException->getMessage(),
    'MQTT HTTP error exposed its body.'
);
assertNotContainsValue(
    'HTTP_BEARER_SECRET',
    $mqttHttpException->getMessage(),
    'MQTT HTTP error exposed its bearer token.'
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

$multiDeviceStatus = PayloadMapper::mapStatus([
    'data' => [
        'payload' => [
            'devices' => [
                [
                    'id' => 'DEVICE_OTHER',
                    'vehicleState' => 'isRunning',
                    'capacityRemaining' => [
                        ['unit' => 'PERCENTAGE', 'rawValue' => 92],
                    ],
                ],
                [
                    'id' => 'DEVICE_TARGET',
                    'vehicleState' => 'isDocked',
                    'capacityRemaining' => [
                        ['unit' => 'PERCENTAGE', 'rawValue' => 81],
                    ],
                ],
            ],
        ],
    ],
], 'DEVICE_TARGET');
assertSameValue(
    PayloadMapper::VEHICLE_STATE_DOCKED,
    $multiDeviceStatus['vehicleState'],
    'Status mapper should select the requested device.'
);
assertSameValue(
    81,
    $multiDeviceStatus['batteryLevel'],
    'Status mapper should use battery data from the requested device.'
);

$dockPayload = CommandContract::createPayload(
    CommandContract::DOCK,
    'DEVICE_001'
);
assertSameValue(
    '{"commands":[{"devices":[{"id":"DEVICE_001"}],"execution":{"command":"action.devices.commands.Dock","params":{}}}]}',
    json_encode($dockPayload, JSON_THROW_ON_ERROR),
    'Dock payload should match the captured command contract.'
);
$pausePayload = CommandContract::createPayload(
    CommandContract::PAUSE,
    'DEVICE_001'
);
assertSameValue(
    '{"commands":[{"devices":[{"id":"DEVICE_001"}],"execution":{"command":"action.devices.commands.PauseUnpause","params":{"on":false}}}]}',
    json_encode($pausePayload, JSON_THROW_ON_ERROR),
    'Pause payload should use the captured boolean false contract.'
);
$resumePayload = CommandContract::createPayload(
    CommandContract::RESUME,
    'DEVICE_001'
);
assertSameValue(
    '{"commands":[{"devices":[{"id":"DEVICE_001"}],"execution":{"command":"action.devices.commands.PauseUnpause","params":{"on":true}}}]}',
    json_encode($resumePayload, JSON_THROW_ON_ERROR),
    'Resume payload should use the captured boolean true contract.'
);
assertThrows(
    static fn () => CommandContract::createPayload(
        'Start',
        'DEVICE_001'
    ),
    InvalidArgumentException::class,
    'Start must remain disabled.'
);
assertThrows(
    static fn () => CommandContract::createPayload(
        'Stop',
        'DEVICE_001'
    ),
    InvalidArgumentException::class,
    'Stop must remain disabled.'
);
assertThrows(
    static fn () => CommandContract::createPayload(
        CommandContract::DOCK,
        ''
    ),
    InvalidArgumentException::class,
    'Dock requires a configured device ID.'
);

$commandRequests = [];
$commandClient = new ApiClient(
    'https://navimow.example.test',
    static function (array $request) use (&$commandRequests): array {
        $commandRequests[] = $request;

        return [
            'status' => 200,
            'body' => file_get_contents(
                __DIR__ . '/../fixtures/rest/command-dock-already-in-state.json'
            ),
        ];
    }
);
$commandResponse = $commandClient->sendCommands(
    'ACCESS_PRIVATE_VALUE',
    $dockPayload
);
assertSameValue(
    '/openapi/smarthome/sendCommands',
    parse_url($commandRequests[0]['url'], PHP_URL_PATH),
    'Dock should use the command endpoint.'
);
assertSameValue(
    '{"commands":[{"devices":[{"id":"DEVICE_001"}],"execution":{"command":"action.devices.commands.Dock","params":{}}}]}',
    $commandRequests[0]['body'],
    'Dock request body should match the allowlisted envelope.'
);
$alreadyInState = PayloadMapper::mapCommandResult(
    $commandResponse,
    'DEVICE_001'
);
assertSameValue(
    PayloadMapper::COMMAND_RESULT_ALREADY_IN_STATE,
    $alreadyInState['result'],
    'Captured alreadyInState response should be non-fatal.'
);
$acceptedFixture = json_decode(
    file_get_contents(
        __DIR__ . '/../fixtures/rest/command-dock-success.json'
    ),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$accepted = PayloadMapper::mapCommandResult(
    $acceptedFixture,
    'DEVICE_001'
);
assertSameValue(
    PayloadMapper::COMMAND_RESULT_ACCEPTED,
    $accepted['result'],
    'Captured SUCCESS should enter pending status verification.'
);
$dockingFixture = json_decode(
    file_get_contents(
        __DIR__ . '/../fixtures/rest/vehicle-status-docking.json'
    ),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$docking = PayloadMapper::mapStatus(
    $dockingFixture,
    'DEVICE_001'
);
assertSameValue(
    PayloadMapper::VEHICLE_STATE_DOCKING,
    $docking['vehicleState'],
    'Captured isDocking status should map to Docking.'
);
$pauseAcceptedFixture = json_decode(
    file_get_contents(
        __DIR__ . '/../fixtures/rest/command-pause-success.json'
    ),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$pauseAccepted = PayloadMapper::mapCommandResult(
    $pauseAcceptedFixture,
    'DEVICE_001'
);
assertSameValue(
    PayloadMapper::COMMAND_RESULT_ACCEPTED,
    $pauseAccepted['result'],
    'Captured Pause SUCCESS should enter pending status verification.'
);
$resumeAcceptedFixture = json_decode(
    file_get_contents(
        __DIR__ . '/../fixtures/rest/command-resume-success.json'
    ),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$resumeAccepted = PayloadMapper::mapCommandResult(
    $resumeAcceptedFixture,
    'DEVICE_001'
);
assertSameValue(
    PayloadMapper::COMMAND_RESULT_ACCEPTED,
    $resumeAccepted['result'],
    'Captured Resume SUCCESS should enter pending status verification.'
);
$pausedFixture = json_decode(
    file_get_contents(
        __DIR__ . '/../fixtures/rest/vehicle-status-paused.json'
    ),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$paused = PayloadMapper::mapStatus(
    $pausedFixture,
    'DEVICE_001'
);
assertSameValue(
    PayloadMapper::VEHICLE_STATE_PAUSED,
    $paused['vehicleState'],
    'Captured isPaused status should map to Paused.'
);
assertSameValue(
    95,
    $paused['batteryLevel'],
    'Captured Paused battery should preserve its percentage value.'
);
assertThrows(
    static fn () => PayloadMapper::mapCommandResult(
        ['code' => 1, 'data' => ['payload' => []]],
        'DEVICE_001'
    ),
    UnexpectedValueException::class,
    'Missing command results must fail closed.'
);
assertThrows(
    static fn () => PayloadMapper::mapCommandResult(
        [
            'code' => 1,
            'data' => [
                'payload' => [
                    'commands' => [
                        [
                            'devices' => [['id' => 'DEVICE_001']],
                            'status' => 'UNKNOWN',
                        ],
                    ],
                ],
            ],
        ],
        'DEVICE_001'
    ),
    UnexpectedValueException::class,
    'Unknown command results must fail closed.'
);
assertThrows(
    static fn () => PayloadMapper::mapCommandResult(
        $commandResponse,
        'DEVICE_OTHER'
    ),
    UnexpectedValueException::class,
    'Command response must match the requested device.'
);

PayloadMapper::assertApiSuccess(['code' => 1]);
assertThrows(
    static fn () => PayloadMapper::assertApiSuccess([
        'code' => 5000,
        'desc' => 'TEST_API_ERROR',
    ]),
    UnexpectedValueException::class,
    'Non-success API codes should fail.'
);

$deviceModuleSource = file_get_contents(
    __DIR__ . '/../distribution/NavimowDevice/module.php'
);
if ($deviceModuleSource === false) {
    throw new RuntimeException('Device module source should be readable.');
}

assertContainsValue(
    'private const COMMAND_VERIFICATION_TIMEOUT_SECONDS = 900;',
    $deviceModuleSource,
    'Dock verification should use the documented 15 minute timeout.'
);
assertContainsValue(
    'private const COMMAND_VERIFICATION_POLL_MILLISECONDS = 60000;',
    $deviceModuleSource,
    'Dock verification should use bounded read-only polling.'
);
assertSameValue(
    1,
    substr_count($deviceModuleSource, "'Function' => 'SendCommand'"),
    'Device module must not contain a command retry path.'
);
assertContainsValue(
    '$vehicleState === self::VEHICLE_STATE_DOCKING',
    $deviceModuleSource,
    'Docking should be treated as progress, not as a failed command.'
);
assertContainsValue(
    'self::COMMAND_STATE_RETURNING',
    $deviceModuleSource,
    'Dock verification should persist the returning state.'
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
