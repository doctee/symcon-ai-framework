<?php

declare(strict_types=1);

require_once __DIR__ . '/../../library/Navimow/PayloadMapper.php';

use Navimow\PayloadMapper;

$fixtureDir = __DIR__ . '/../../case-studies/navimow/fixtures/rest';

function fixture(string $name): array
{
    global $fixtureDir;

    $path = $fixtureDir . '/' . $name;
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Unable to read fixture: ' . $path);
    }

    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Fixture is not a JSON object: ' . $path);
    }

    return $decoded;
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$token = PayloadMapper::mapTokenResponse(fixture('auth-token-success.json'));
assertSameValue(true, $token['hasAccessToken'], 'Token fixture should include an access token placeholder.');
assertSameValue(true, $token['hasRefreshToken'], 'Token fixture should include a refresh token placeholder.');
assertSameValue(3600, $token['expiresIn'], 'Token expiry should map from expires_in.');

$devices = PayloadMapper::mapDiscovery(fixture('auth-list-success.json'));
assertSameValue(1, count($devices), 'Discovery fixture should produce one device.');
assertSameValue('DEVICE_001', $devices[0]['id'], 'Discovery should preserve sanitized device placeholder.');
assertSameValue('Navimow Test Mower', $devices[0]['name'], 'Discovery should map device name.');

$docked = PayloadMapper::mapStatus(fixture('vehicle-status-docked.json'));
assertSameValue(PayloadMapper::VEHICLE_STATE_DOCKED, $docked['vehicleState'], 'Docked status should map to Docked.');
assertSameValue(81, $docked['batteryLevel'], 'Docked battery should map from capacityRemaining percentage.');
assertSameValue(null, $docked['online'], 'Missing online field should not be interpreted as offline.');

$running = PayloadMapper::mapStatus(fixture('vehicle-status-mowing.json'));
assertSameValue(PayloadMapper::VEHICLE_STATE_RUNNING, $running['vehicleState'], 'Running status should map to Running.');
assertSameValue(92, $running['batteryLevel'], 'Running battery should map from capacityRemaining percentage.');

$command = PayloadMapper::mapCommandResult(fixture('command-dock-already-in-state.json'));
assertSameValue(PayloadMapper::COMMAND_RESULT_ALREADY_IN_STATE, $command['result'], 'alreadyInState should map to non-fatal result.');
assertSameValue('ERROR', $command['status'], 'Command-level ERROR status should remain visible.');

$authError = PayloadMapper::mapApiError(fixture('auth-invalid-token.json'));
assertSameValue(true, $authError['reauthRequired'], 'Invalid OAuth info should require reauthentication.');
assertSameValue(4005, $authError['code'], 'Auth error code should be preserved.');

$unknown = PayloadMapper::mapStatus([
    'data' => [
        'payload' => [
            'devices' => [
                [
                    'vehicleState' => 'unexpectedState',
                    'capacityRemaining' => [
                        [
                            'unit' => 'PERCENTAGE',
                            'rawValue' => 50,
                        ],
                    ],
                ],
            ],
        ],
    ],
]);
assertSameValue(PayloadMapper::VEHICLE_STATE_UNKNOWN, $unknown['vehicleState'], 'Unknown vehicle state should map to Unknown.');

echo "Navimow payload mapper fixture checks passed.\n";
