# 08 Runtime Diagnostics Initialization Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G4 implementation sequence step 3 complete
**Date:** 2026-07-15
**Deployment status:** Repository candidate only; no live installation changes

## 1. Outcome

`candidate/MqttDiscoveryExporterRuntime.php` now initializes the exporter-owned
diagnostics structure by composing existing SAEF helpers. No helper body was
copied and no new public framework helper was introduced.

The initialized object structure is:

```text
Exporter script
`-- MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS
    |-- MANAGED_STATE_REGISTRY
    |-- ERROR_HISTORY
    |-- EXECUTIONS
    |-- SUCCESSES
    |-- FAILURES
    |-- COMMANDS
    |-- PUBLISHES
    |-- PUBLISH_SKIPS
    |-- LAST_RUN
    |-- LAST_SUCCESS
    `-- LAST_FAILURE
```

All objects use stable Idents below the exporter script. Existing compatible
objects are reused; incompatible object or variable types fail visibly.

## 2. Helper Composition

The runtime composes:

- `SAEF_EnsureCategory()` for the owned diagnostics category;
- `SAEF_EnsureRegistryVariable()`, `SAEF_ReadRegistry()` and
  `SAEF_WriteRegistry()` for managed metadata;
- `SAEF_EnsureStatisticsVariables()` for typed counters and timestamps;
- `SAEF_EnsureErrorRingBufferVariable()` and
  `SAEF_AppendErrorRingBufferEntry()` for bounded failure history;
- `SAEF_CreateConfigurationHash()` for the desired-configuration fingerprint.

The ErrorRingBuffer capacity is fixed at 20 entries.

## 3. Registry Initialization Contract

The initial registry root contains:

- schema version;
- exporter version;
- current and previous configuration hashes;
- managed entity metadata;
- command-variable index;
- state-variable index;
- last successful reconciliation timestamp.

Existing managed metadata and unknown forward-compatible fields are preserved.
When the desired configuration changes, the former current hash becomes the
previous hash exactly once. Repeated initialization with unchanged
configuration performs no Registry value write.

An unsupported Registry schema, malformed hash, invalid index or invalid
reconciliation timestamp is rejected. Initialization does not silently erase
or reconstruct corrupted managed state.

## 4. Failure Boundary

Owner-script and early Ensure failures are logged and rethrown. They cannot be
stored before diagnostics exist.

After ErrorRingBuffer and Statistics variables exist, initialization failures
are additionally recorded with:

- bounded error context;
- the phase `diagnostics_initialization`;
- exception class;
- incremented `FAILURES` counter;
- updated `LAST_FAILURE` timestamp.

Secondary diagnostic-write failures are logged without replacing the original
exception.

Initialization does not increment normal execution or success statistics.
Those updates belong to a completed runtime run and will be added with the run
result contract.

## 5. Offline Verification

`tests/mqtt-discovery-exporter/runtime-diagnostics.php` verifies five scenario
groups:

1. complete owned diagnostics creation;
2. repeated initialization without duplicates or unchanged Registry writes;
3. managed-state preservation and one-step configuration-hash history;
4. bounded failure recording after diagnostics exist;
5. rejection and logging of a missing owner before object creation.

The test uses a local stateful Symcon fake. It performs no network access,
MQTT publication, live object creation or device action.

The complete `composer check` passes with eight core scenarios, five runtime
diagnostics scenarios, existing helper and bundle tests, PHPStan and PHPCS.

## 6. Gate Decision

G4 remains open. Sequence step 3 is complete. The next step is reconcile mode
without destructive cleanup:

- validate the complete desired configuration and live Symcon variable
  contracts before external effects;
- ensure exact owned MQTT adapter objects and events;
- create a publish plan;
- leave removal and retained-topic cleanup disabled.

No MQTT transport choice or live deployment is authorized by this report.
