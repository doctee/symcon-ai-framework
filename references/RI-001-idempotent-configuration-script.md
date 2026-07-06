# RI-001 — Idempotent Configuration Script

**Status:** Draft 2.0  
**Type:** Reference Implementation  
**Language:** PHP  
**Platform:** IP-Symcon

## Purpose

This reference implementation demonstrates an idempotent IP-Symcon configuration script using SAEF helpers.

It shows how a setup script can safely create or update categories, variables,
profiles and cyclic script events without creating duplicates when executed
repeatedly.

## Demonstrated Concepts

This implementation demonstrates:

- stable object identification using Idents,
- idempotent object creation using SAEF helpers,
- explicit ownership of created objects,
- separation of configuration and logic,
- script-owned internal state variables,
- deterministic cyclic script event creation,
- Symcon 6.0+ event action binding for script-executing events,
- validation before side effects.

## Related Framework Artifacts

- `drafts/SYMCON_STANDARDS.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `helpers/object/EnsureCategory.php`
- `helpers/object/EnsureVariable.php`
- `helpers/object/EnsureEvent.php`
- `helpers/object/EnsureProfile.php`
- `templates/ConfigurationScript.php`
- `adr/ADR-0002-use-ident-over-object-id.md`

## Usage

Copy the PHP script into an IP-Symcon script and adjust the configuration section.

The script is safe to execute repeatedly. Existing owned objects are reused and updated where appropriate.

## Implementation

```php
<?php
declare(strict_types=1);

/**
 * RI-001 — Idempotent Configuration Script
 *
 * SAEF reference implementation.
 *
 * This script creates a small owned object structure below a configured parent:
 *
 * Parent
 * └── SAEF Demo
 *     ├── State
 *     ├── Last Run
 *     ├── Error
 *     └── Periodic Update
 *
 * The script demonstrates the preferred SAEF style:
 * - configuration first,
 * - validation before side effects,
 * - idempotent SAEF helpers,
 * - explicit ownership,
 * - safe default event activation.
 */

require_once __DIR__ . '/../helpers/object/EnsureCategory.php';
require_once __DIR__ . '/../helpers/object/EnsureVariable.php';
require_once __DIR__ . '/../helpers/object/EnsureEvent.php';
require_once __DIR__ . '/../helpers/object/EnsureProfile.php';

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

$config = [
    /*
     * Replace with the parent object below which the demo structure should exist.
     *
     * Public framework artifacts must keep this value explicit.
     * Do not hardcode private installation ObjectIDs inside reusable logic.
     */
    'parentID' => 0,

    'category' => [
        'ident' => 'SAEF_DEMO',
        'name'  => 'SAEF Demo',
        'position' => null,
        'icon' => null,
    ],

    'profiles' => [
        [
            'name' => 'SAEF.Demo.State',
            'type' => 1,
            'icon' => '',
            'prefix' => '',
            'suffix' => '',
            'minValue' => 0,
            'maxValue' => 3,
            'stepSize' => 1,
            'digits' => null,
            'associations' => [
                [0, 'Unknown', '', -1],
                [1, 'Ready', '', 0x00FF00],
                [2, 'Running', '', 0xFFFF00],
                [3, 'Error', '', 0xFF0000],
            ],
        ],
    ],

    'variables' => [
        [
            'ident'   => 'STATE',
            'name'    => 'State',
            'type'    => 1,
            'profile' => 'SAEF.Demo.State',
            'position' => 10,
            'icon' => null,
        ],
        [
            'ident'   => 'LAST_RUN',
            'name'    => 'Last Run',
            'type'    => 1,
            'profile' => '~UnixTimestamp',
            'position' => 20,
            'icon' => null,
        ],
        [
            'ident'   => 'ERROR',
            'name'    => 'Error',
            'type'    => 0,
            'profile' => '~Switch',
            'position' => 30,
            'icon' => null,
        ],
    ],

    'event' => [
        'ident' => 'PERIODIC_UPDATE',
        'name' => 'Periodic Update',
        'targetScriptID' => $_IPS['SELF'],
        'intervalSeconds' => 300,
        /*
         * Keep generated events inactive by default.
         * Users should review configuration before enabling scheduled execution.
         */
        'active' => false,
        'position' => 100,
        'hidden' => true,
    ],
];

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

try {
    validateDemoConfiguration($config);

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

    foreach ($config['variables'] as $variable) {
        SAEF_EnsureVariable(
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

    SetValue(IPS_GetObjectIDByIdent('STATE', $categoryID), 1);
    SetValue(IPS_GetObjectIDByIdent('LAST_RUN', $categoryID), time());
    SetValue(IPS_GetObjectIDByIdent('ERROR', $categoryID), false);

    IPS_LogMessage('SAEF RI-001', 'Idempotent configuration completed successfully');
} catch (Throwable $exception) {
    IPS_LogMessage('SAEF RI-001', 'Configuration failed: ' . $exception->getMessage());
    throw $exception;
}

// -----------------------------------------------------------------------------
// Local validation
// -----------------------------------------------------------------------------

/**
 * Performs scenario-specific validation before side effects.
 *
 * Generic validation is handled by SAEF helpers.
 * This function validates the configuration structure of this reference scenario.
 */
function validateDemoConfiguration(array $config): void
{
    if (!isset($config['parentID']) || !is_int($config['parentID'])) {
        throw new InvalidArgumentException('Configuration value parentID must be an integer.');
    }

    if ($config['parentID'] <= 0 || !IPS_ObjectExists($config['parentID'])) {
        throw new InvalidArgumentException('Configured parentID does not exist: ' . (string)$config['parentID']);
    }

    if (!isset($config['category'], $config['variables'], $config['event'])) {
        throw new InvalidArgumentException('Configuration must contain category, variables and event sections.');
    }

    if (!is_array($config['variables']) || count($config['variables']) === 0) {
        throw new InvalidArgumentException('Configuration must contain at least one variable.');
    }
}
```

## Design Notes

### Helper-First Implementation

The script uses SAEF helpers instead of implementing its own object-creation logic.

This is intentional. Reference implementations should demonstrate how
framework users and AI coding agents are expected to compose SAEF helpers.

### Parent Object

The parent object is explicit configuration. This keeps the reference
implementation portable and avoids hidden installation-specific assumptions.

### Idents

All created objects receive stable Idents. This allows repeated execution without duplicates.

### Existing Type Mismatch

SAEF helpers stop with an error if an existing object with the expected Ident has an incompatible type.

This preserves existing history, links and user configuration instead of deleting and recreating objects.

### Profiles

The script demonstrates explicit profile creation through `SAEF_EnsureProfile()`.

Profiles are created or validated before variables use them.

### Event Creation

The cyclic event is created or updated deterministically through `SAEF_EnsureCyclicScriptEvent()`.

The event action binding is handled by the helper.

### Event Active State

The created event is inactive by default. This prevents accidental scheduled
execution before the user has reviewed and adapted the configuration.

## Known Constraints

This reference implementation is intentionally small.

It does not yet include:

- cleanup of obsolete objects,
- migration of renamed objects,
- dry-run mode,
- structured diagnostics,
- archive registration,
- links or media objects.

These topics should be addressed in more advanced reference implementations.

## Review Checklist

Before adapting this implementation:

- Replace `parentID` with a valid parent object ID.
- Review all Idents.
- Review variable profiles.
- Review whether the cyclic event should be active.
- Execute the script twice and verify that no duplicate objects are created.
- Verify that existing variable values are preserved.
- Verify that the generated event is inactive until intentionally enabled.
