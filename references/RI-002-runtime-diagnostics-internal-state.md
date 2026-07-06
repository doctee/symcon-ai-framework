# RI-002 - Runtime Diagnostics / Internal State

**Status:** Draft 1.0  
**Type:** Reference Implementation  
**Language:** PHP  
**Platform:** IP-Symcon

## Purpose

This reference implementation demonstrates how the SAEF diagnostics helpers can
be composed to model runtime diagnostics and internal automation state.

It intentionally does not introduce new helper functions, new public APIs or
local object-creation logic. The implementation exists to show how existing
diagnostics helpers work together in one complete script.

## Demonstrated Concepts

This implementation demonstrates:

- stable configuration fingerprints using `SAEF_CreateConfigurationHash()`,
- small script-owned registry metadata using `SAEF_EnsureRegistryVariable()` and `SAEF_UpdateRegistryEntry()`,
- explicit runtime statistics using `SAEF_EnsureStatisticsVariables()`,
  `SAEF_IncrementStatistic()` and `SAEF_SetStatisticTimestamp()`,
- bounded error history using `SAEF_EnsureErrorRingBufferVariable()` and `SAEF_AppendErrorRingBufferEntry()`,
- explicit ownership of internal state variables,
- idempotent diagnostic variable creation through existing helpers,
- separation of configuration, diagnostics state and runtime logic.

## Related Framework Artifacts

- `drafts/SYMCON_STANDARDS.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `helpers/diagnostics/ConfigurationHash.php`
- `helpers/diagnostics/Registry.php`
- `helpers/diagnostics/Statistics.php`
- `helpers/diagnostics/ErrorRingBuffer.php`
- `adr/ADR-0002-use-ident-over-object-id.md`

## Usage

Copy the PHP script into an IP-Symcon script and adjust the configuration section.

The script expects an existing parent object. It creates or updates only the
diagnostic variables defined in the configuration. It is safe to execute
repeatedly.

## Implementation

```php
<?php
declare(strict_types=1);

/**
 * RI-002 - Runtime Diagnostics / Internal State
 *
 * SAEF reference implementation.
 *
 * This script creates a small owned diagnostics structure below a configured
 * parent object:
 *
 * Parent
 * |-- Diagnostics Registry
 * |-- Executions
 * |-- Last Run
 * |-- Errors
 * `-- Error History
 *
 * The script demonstrates the preferred SAEF diagnostics style:
 * - configuration first,
 * - diagnostics state is explicit and script-owned,
 * - object creation through existing diagnostics helpers,
 * - no discovery payloads or large data sets in JSON variables,
 * - bounded error history.
 */

require_once __DIR__ . '/../helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../helpers/diagnostics/Registry.php';
require_once __DIR__ . '/../helpers/diagnostics/Statistics.php';
require_once __DIR__ . '/../helpers/diagnostics/ErrorRingBuffer.php';

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

$config = [
    /*
     * Replace with the parent object below which the diagnostics variables
     * should exist.
     *
     * Public framework artifacts must keep this value explicit.
     * Do not hardcode private installation ObjectIDs inside reusable logic.
     */
    'parentID' => 0,

    'version' => 'RI-002-Draft-1.0',

    'configurationHash' => [
        'ignoreKeys' => [
            'lastRun',
            'runtime',
            'timestamp',
        ],
    ],

    'registry' => [
        'ident' => 'DIAGNOSTICS_REGISTRY',
        'name' => 'Diagnostics Registry',
        'position' => 10,
        'icon' => null,
    ],

    'statistics' => [
        [
            'ident' => 'EXECUTIONS',
            'name' => 'Executions',
            'type' => 1,
            'profile' => '',
            'position' => 20,
            'icon' => null,
        ],
        [
            'ident' => 'LAST_RUN',
            'name' => 'Last Run',
            'type' => 1,
            'profile' => '~UnixTimestamp',
            'position' => 30,
            'icon' => null,
        ],
        [
            'ident' => 'ERRORS',
            'name' => 'Errors',
            'type' => 1,
            'profile' => '',
            'position' => 40,
            'icon' => null,
        ],
    ],

    'errorRingBuffer' => [
        'ident' => 'ERROR_HISTORY',
        'name' => 'Error History',
        'position' => 50,
        'icon' => null,
        'capacity' => 20,
    ],
];

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

$errorRingBufferID = null;
$statisticIDs = [];

try {
    validateRuntimeDiagnosticsConfiguration($config);

    $configurationHash = SAEF_CreateConfigurationHash(
        $config,
        $config['configurationHash']['ignoreKeys']
    );

    $registryID = SAEF_EnsureRegistryVariable(
        $config['parentID'],
        $config['registry']['ident'],
        $config['registry']['name'],
        $config['registry']['position'],
        $config['registry']['icon']
    );

    $statisticIDs = SAEF_EnsureStatisticsVariables(
        $config['parentID'],
        $config['statistics']
    );

    $errorRingBufferID = SAEF_EnsureErrorRingBufferVariable(
        $config['parentID'],
        $config['errorRingBuffer']['ident'],
        $config['errorRingBuffer']['name'],
        $config['errorRingBuffer']['position'],
        $config['errorRingBuffer']['icon']
    );

    $registry = SAEF_ReadRegistry($registryID);
    $previousConfigurationHash = $registry['configurationHash'] ?? null;

    SAEF_UpdateRegistryEntry($registryID, 'version', $config['version']);
    SAEF_UpdateRegistryEntry($registryID, 'configurationHash', $configurationHash);
    SAEF_UpdateRegistryEntry($registryID, 'previousConfigurationHash', $previousConfigurationHash);

    SAEF_IncrementStatistic($statisticIDs['EXECUTIONS']);
    SAEF_SetStatisticTimestamp($statisticIDs['LAST_RUN']);

    IPS_LogMessage('SAEF RI-002', 'Runtime diagnostics updated successfully');
} catch (Throwable $exception) {
    if ($errorRingBufferID !== null) {
        SAEF_AppendErrorRingBufferEntry(
            $errorRingBufferID,
            $exception->getMessage(),
            $config['errorRingBuffer']['capacity'],
            [
                'type' => get_class($exception),
                'script' => 'RI-002',
            ]
        );
    }

    if (isset($statisticIDs['ERRORS'])) {
        SAEF_IncrementStatistic($statisticIDs['ERRORS']);
    }

    IPS_LogMessage('SAEF RI-002', 'Runtime diagnostics failed: ' . $exception->getMessage());
    throw $exception;
}

// -----------------------------------------------------------------------------
// Local validation
// -----------------------------------------------------------------------------

/**
 * Performs scenario-specific validation before side effects.
 *
 * Generic object creation and type compatibility checks are delegated to SAEF
 * diagnostics helpers.
 */
function validateRuntimeDiagnosticsConfiguration(array $config): void
{
    if (!isset($config['parentID']) || !is_int($config['parentID'])) {
        throw new InvalidArgumentException('Configuration value parentID must be an integer.');
    }

    if ($config['parentID'] <= 0 || !IPS_ObjectExists($config['parentID'])) {
        throw new InvalidArgumentException('Configured parentID does not exist: ' . (string)$config['parentID']);
    }

    foreach (['configurationHash', 'registry', 'statistics', 'errorRingBuffer'] as $section) {
        if (!array_key_exists($section, $config)) {
            throw new InvalidArgumentException('Configuration section is missing: ' . $section);
        }
    }

    if (!is_array($config['statistics']) || count($config['statistics']) === 0) {
        throw new InvalidArgumentException('Configuration section statistics must contain at least one definition.');
    }

    if (
        !isset($config['errorRingBuffer']['capacity'])
        || !is_int($config['errorRingBuffer']['capacity'])
        || $config['errorRingBuffer']['capacity'] <= 0
    ) {
        throw new InvalidArgumentException('Error ring buffer capacity must be a positive integer.');
    }
}
```

## Design Notes

### Composition Only

RI-002 intentionally composes the diagnostics helpers instead of introducing a
local abstraction. Each helper keeps its own responsibility:

- `ConfigurationHash` creates stable fingerprints.
- `Registry` stores small metadata.
- `Statistics` owns counters and timestamps.
- `ErrorRingBuffer` stores bounded error history.

### Object Creation

The reference implementation does not call `IPS_Create*()` functions directly.

All diagnostic variables are created through existing diagnostics helpers,
which in turn delegate variable creation to `SAEF_EnsureVariable()`.

### Registry Scope

The registry is used only for small metadata such as version and configuration hashes.

It must not store discovery payloads, large snapshots or unbounded runtime data.

### Error History

The error history is a fixed-capacity ring buffer.

This makes diagnostic history useful without creating an unbounded JSON dump.

### Internal State Ownership

All variables belong to the automation that owns the configured parent object.

The reference uses stable Idents and does not rely on private ObjectIDs outside
the explicit `parentID` configuration placeholder.

## Known Constraints

This reference implementation is intentionally focused on diagnostics composition.

It does not include:

- a separate diagnostics category,
- event creation,
- cleanup of obsolete diagnostics variables,
- migration of renamed diagnostics variables,
- archive registration,
- external notification or alerting.

## Review Checklist

Before adapting this implementation:

- Replace `parentID` with a valid parent object ID in a private/local copy.
- Review all Idents.
- Review whether statistics should be integer or float variables.
- Review error ring buffer capacity.
- Execute the script twice and verify that no duplicate variables are created.
- Verify that the registry contains only small metadata.
- Verify that error history remains bounded.
