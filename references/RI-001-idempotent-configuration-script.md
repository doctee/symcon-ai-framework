# RI-001 — Idempotent Configuration Script

**Status:** Draft 1.0  
**Type:** Reference Implementation  
**Language:** PHP  
**Platform:** IP-Symcon

## Purpose

This reference implementation demonstrates an idempotent IP-Symcon configuration script.

It shows how a setup script can safely create or update categories, variables and events without creating duplicates when executed repeatedly.

## Demonstrated Concepts

This implementation demonstrates:

- stable object identification using Idents,
- idempotent object creation,
- explicit ownership of created objects,
- separation of configuration and logic,
- script-owned internal state variables,
- deterministic event creation,
- Symcon 6.0+ event action binding for script-executing events.

## Related Framework Artifacts

- RS-001 — Symcon Engineering Standards
- EK-004 — Internal State Management
- EK-005 — Idempotent Configuration
- ADR-0002 — Use Ident over ObjectID where practical

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
 * Reference implementation for SAEF.
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
 * The script is intentionally small, but demonstrates the core engineering
 * pattern used by larger Symcon configuration scripts.
 */

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

$config = [
    // Replace with the parent object under which the demo structure should exist.
    // For reusable/public artifacts this value must remain explicit configuration.
    'parentID' => 0,

    'categoryIdent' => 'SAEF_DEMO',
    'categoryName'  => 'SAEF Demo',

    'variables' => [
        [
            'ident'   => 'STATE',
            'name'    => 'State',
            'type'    => 1, // Integer
            'profile' => '',
        ],
        [
            'ident'   => 'LAST_RUN',
            'name'    => 'Last Run',
            'type'    => 1, // Integer timestamp
            'profile' => '~UnixTimestamp',
        ],
        [
            'ident'   => 'ERROR',
            'name'    => 'Error',
            'type'    => 0, // Boolean
            'profile' => '~Switch',
        ],
    ],

    'event' => [
        'ident'      => 'PERIODIC_UPDATE',
        'name'       => 'Periodic Update',
        'interval'   => 300, // seconds
        'targetScriptID' => $_IPS['SELF'],
        'active'     => false,
    ],
];

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

validateConfiguration($config);

$categoryID = ensureCategory(
    $config['parentID'],
    $config['categoryIdent'],
    $config['categoryName']
);

foreach ($config['variables'] as $variableConfig) {
    ensureVariable(
        $categoryID,
        $variableConfig['ident'],
        $variableConfig['name'],
        $variableConfig['type'],
        $variableConfig['profile']
    );
}

ensureCyclicScriptEvent(
    $categoryID,
    $config['event']['ident'],
    $config['event']['name'],
    $config['event']['targetScriptID'],
    $config['event']['interval'],
    $config['event']['active']
);

SetValue(IPS_GetObjectIDByIdent('LAST_RUN', $categoryID), time());

IPS_LogMessage('SAEF RI-001', 'Idempotent configuration completed');

// -----------------------------------------------------------------------------
// Functions
// -----------------------------------------------------------------------------

function validateConfiguration(array $config): void
{
    if (!isset($config['parentID']) || !is_int($config['parentID']) || $config['parentID'] <= 0) {
        throw new InvalidArgumentException('Configuration value parentID must be a valid IP-Symcon object ID.');
    }

    if (!IPS_ObjectExists($config['parentID'])) {
        throw new InvalidArgumentException('Configured parentID does not exist: ' . $config['parentID']);
    }

    if (($config['categoryIdent'] ?? '') === '') {
        throw new InvalidArgumentException('categoryIdent must not be empty.');
    }

    if (($config['categoryName'] ?? '') === '') {
        throw new InvalidArgumentException('categoryName must not be empty.');
    }
}

function ensureCategory(int $parentID, string $ident, string $name): int
{
    $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

    if ($existingID !== false) {
        IPS_SetName($existingID, $name);
        return $existingID;
    }

    $categoryID = IPS_CreateCategory();
    IPS_SetParent($categoryID, $parentID);
    IPS_SetIdent($categoryID, $ident);
    IPS_SetName($categoryID, $name);

    return $categoryID;
}

function ensureVariable(int $parentID, string $ident, string $name, int $type, string $profile = ''): int
{
    $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

    if ($existingID !== false) {
        $variable = IPS_GetVariable($existingID);

        if ($variable['VariableType'] !== $type) {
            throw new RuntimeException(sprintf(
                'Existing variable %s has type %d, expected %d.',
                $ident,
                $variable['VariableType'],
                $type
            ));
        }

        IPS_SetName($existingID, $name);
        applyProfile($existingID, $profile);

        return $existingID;
    }

    $variableID = IPS_CreateVariable($type);
    IPS_SetParent($variableID, $parentID);
    IPS_SetIdent($variableID, $ident);
    IPS_SetName($variableID, $name);
    applyProfile($variableID, $profile);

    return $variableID;
}

function applyProfile(int $variableID, string $profile): void
{
    if ($profile === '') {
        return;
    }

    if (!IPS_VariableProfileExists($profile)) {
        throw new RuntimeException('Variable profile does not exist: ' . $profile);
    }

    IPS_SetVariableCustomProfile($variableID, $profile);
}

function ensureCyclicScriptEvent(
    int $parentID,
    string $ident,
    string $name,
    int $targetScriptID,
    int $intervalSeconds,
    bool $active
): int {
    if (!IPS_ScriptExists($targetScriptID)) {
        throw new InvalidArgumentException('Target script does not exist: ' . $targetScriptID);
    }

    if ($intervalSeconds <= 0) {
        throw new InvalidArgumentException('intervalSeconds must be greater than zero.');
    }

    $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

    if ($existingID !== false) {
        $eventID = $existingID;
    } else {
        $eventID = IPS_CreateEvent(1); // Cyclic event
        IPS_SetParent($eventID, $parentID);
        IPS_SetIdent($eventID, $ident);
    }

    IPS_SetName($eventID, $name);

    // Cyclic event: every N seconds.
    IPS_SetEventCyclic($eventID, 0, 0, 0, 0, 1, $intervalSeconds);

    // Execute the target script.
    IPS_SetEventScript($eventID, $targetScriptID);

    // Required for script-executing events in IP-Symcon 6.0+.
    IPS_SetEventAction($eventID, '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}', []);

    IPS_SetEventActive($eventID, $active);

    return $eventID;
}
```

## Design Notes

### Parent Object

The parent object is explicit configuration. This keeps the reference implementation portable and avoids hidden installation-specific assumptions.

### Idents

All created objects receive stable Idents. This allows the script to find and update its own objects during later executions.

### Existing Variable Type Mismatch

If an existing variable with the expected Ident has a different type, the script stops with an error instead of silently replacing it. Replacing the variable would destroy history, links and user configuration.

### Event Creation

The cyclic event is created or updated deterministically. The event action binding is set explicitly so that script execution is fully defined.

### Event Active State

The created event is inactive by default. This prevents accidental scheduled execution before the user has reviewed and adapted the configuration.

## Known Constraints

This reference implementation is intentionally small.

It does not yet include:

- custom profile creation,
- cleanup of obsolete objects,
- migration of renamed objects,
- dry-run mode,
- structured diagnostics.

These topics should be addressed in more advanced reference implementations.

## Review Checklist

Before adapting this implementation:

- Replace `parentID` with a valid parent object ID.
- Review all Idents.
- Review variable profiles.
- Review whether the cyclic event should be active.
- Execute the script twice and verify that no duplicate objects are created.
- Verify that existing variable values are preserved.

