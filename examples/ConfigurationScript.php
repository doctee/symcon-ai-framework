<?php
declare(strict_types=1);

/**
 * SAEF Example: IP-Symcon Configuration Script
 *
 * This script creates an owned, idempotent object structure below a configured
 * parent object:
 *
 * Parent
 * `-- SAEF Example
 *     |-- State
 *     |-- Last Run
 *     |-- Last Error
 *     `-- Periodic Update
 *
 * Ownership:
 * - The script owns the category identified by SAEF_EXAMPLE.
 * - The script owns the variables and event below that category.
 * - Existing compatible objects are reused and updated through SAEF helpers.
 *
 * Public framework artifacts must not contain private installation data.
 * Replace parentID with a local ObjectID only in a private/local copy.
 */

require_once __DIR__ . '/../helpers/object/EnsureCategory.php';
require_once __DIR__ . '/../helpers/object/EnsureVariable.php';
require_once __DIR__ . '/../helpers/object/EnsureEvent.php';
require_once __DIR__ . '/../helpers/object/EnsureProfile.php';

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

$config = [
    'parentID' => 0,

    'category' => [
        'ident' => 'SAEF_EXAMPLE',
        'name' => 'SAEF Example',
        'position' => null,
        'icon' => null,
    ],

    'profiles' => [
        [
            'name' => 'SAEF.Example.State',
            'type' => 1,
            'icon' => '',
            'prefix' => '',
            'suffix' => '',
            'minValue' => 0,
            'maxValue' => 2,
            'stepSize' => 1,
            'digits' => null,
            'associations' => [
                [0, 'Unknown', '', -1],
                [1, 'Ready', '', 0x00A000],
                [2, 'Error', '', 0xD00000],
            ],
        ],
    ],

    'variables' => [
        [
            'ident' => 'STATE',
            'name' => 'State',
            'type' => 1,
            'profile' => 'SAEF.Example.State',
            'position' => 10,
            'icon' => null,
        ],
        [
            'ident' => 'LAST_RUN',
            'name' => 'Last Run',
            'type' => 1,
            'profile' => '~UnixTimestamp',
            'position' => 20,
            'icon' => null,
        ],
        [
            'ident' => 'LAST_ERROR',
            'name' => 'Last Error',
            'type' => 3,
            'profile' => '',
            'position' => 30,
            'icon' => null,
        ],
    ],

    'event' => [
        'ident' => 'PERIODIC_UPDATE',
        'name' => 'Periodic Update',
        'targetScriptID' => $_IPS['SELF'],
        'intervalSeconds' => 300,
        'active' => false,
        'position' => 100,
        'hidden' => true,
    ],
];

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

try {
    validateConfigurationScriptConfig($config);

    foreach ($config['profiles'] as $profile) {
        SAEF_EnsureProfile(
            $profile['name'],
            $profile['type'],
            $profile['icon'],
            $profile['prefix'],
            $profile['suffix'],
            $profile['minValue'],
            $profile['maxValue'],
            $profile['stepSize'],
            $profile['digits'],
            $profile['associations']
        );
    }

    $categoryID = SAEF_EnsureCategory(
        $config['parentID'],
        $config['category']['ident'],
        $config['category']['name'],
        $config['category']['position'],
        $config['category']['icon']
    );

    $variableIDs = [];

    foreach ($config['variables'] as $variable) {
        $variableIDs[$variable['ident']] = SAEF_EnsureVariable(
            $categoryID,
            $variable['ident'],
            $variable['name'],
            $variable['type'],
            $variable['profile'],
            null,
            $variable['position'],
            $variable['icon']
        );
    }

    SAEF_EnsureCyclicScriptEvent(
        $categoryID,
        $config['event']['ident'],
        $config['event']['name'],
        $config['event']['targetScriptID'],
        $config['event']['intervalSeconds'],
        $config['event']['active'],
        $config['event']['position'],
        $config['event']['hidden']
    );

    SetValue($variableIDs['STATE'], 1);
    SetValue($variableIDs['LAST_RUN'], time());
    SetValue($variableIDs['LAST_ERROR'], '');

    IPS_LogMessage('SAEF Example Configuration', 'Configuration completed successfully.');
} catch (Throwable $exception) {
    IPS_LogMessage('SAEF Example Configuration', 'Configuration failed: ' . $exception->getMessage());
    throw $exception;
}

// -----------------------------------------------------------------------------
// Local validation
// -----------------------------------------------------------------------------

/**
 * Validates this script's configuration before side effects are performed.
 *
 * Generic object and type validation is delegated to SAEF helpers.
 */
function validateConfigurationScriptConfig(array $config): void
{
    if (!isset($config['parentID']) || !is_int($config['parentID'])) {
        throw new InvalidArgumentException('Configuration value parentID must be an integer.');
    }

    if ($config['parentID'] <= 0 || !IPS_ObjectExists($config['parentID'])) {
        throw new InvalidArgumentException('Configured parentID does not exist: ' . (string)$config['parentID']);
    }

    foreach (['category', 'profiles', 'variables', 'event'] as $section) {
        if (!array_key_exists($section, $config)) {
            throw new InvalidArgumentException('Configuration section is missing: ' . $section);
        }
    }

    if (!is_array($config['profiles'])) {
        throw new InvalidArgumentException('Configuration section profiles must be an array.');
    }

    if (!is_array($config['variables']) || count($config['variables']) === 0) {
        throw new InvalidArgumentException('Configuration section variables must contain at least one variable.');
    }
}
