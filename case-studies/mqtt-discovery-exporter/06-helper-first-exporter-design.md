# 06 Helper-First Exporter Design

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G4 implementation design
**Date:** 2026-07-15
**Implementation authorization:** Repository candidate only; no live deployment

## 1. Purpose

Freeze the structure and responsibility boundaries for the G4 exporter
candidate before implementation begins.

The candidate is reconstructed from reviewed contracts. It is not a mechanical
rewrite of the private RC2 file and must not copy its local helper
implementations or legacy migration behavior.

## 2. Candidate Layout

```text
case-studies/mqtt-discovery-exporter/candidate/
|-- MqttDiscoveryExporterConfig.php
|-- MqttDiscoveryExporterCore.php
|-- MqttDiscoveryExporterRuntime.php
`-- MqttDiscoveryExporter.php
```

### `MqttDiscoveryExporterConfig.php`

Provides one sanitized configuration function. It contains neutral ObjectID
placeholders and no private topics, hosts, device names or migration history.

### `MqttDiscoveryExporterCore.php`

Contains deterministic, side-effect-free scenario logic:

- raw entity normalization;
- complete capability-contract validation;
- topic and stable identity derivation;
- strict MQTT command payload parsing;
- Home Assistant discovery payload construction;
- runtime payload construction from supplied values;
- desired/managed-state reconciliation planning.

It must not call IP-Symcon functions, MQTT or SAEF helpers.

### `MqttDiscoveryExporterRuntime.php`

Owns IP-Symcon and MQTT effects:

- validates configured Symcon objects and variable actions;
- composes SAEF Ensure and diagnostics helpers;
- creates transport-matched MQTT Device instances and triggered events;
- reads state variables;
- invokes `RequestAction()` with the exact variable type;
- waits for bounded state confirmation through
  `SAEF_WaitForVariable()`;
- publishes through an owned MQTT Device value variable;
- applies exact registry-derived cleanup.

Scenario-specific adapter functions remain local to this candidate. They do
not become new canonical helpers.

### `MqttDiscoveryExporter.php`

Is the small entry and dispatcher. It loads configuration and the two candidate
components, initializes diagnostics and selects one run mode.

## 3. Canonical Helper Composition

The runtime uses the existing canonical functions below:

| Responsibility | Canonical SAEF function |
| --- | --- |
| Internal category | `SAEF_EnsureCategory()` |
| Registry variable | `SAEF_EnsureRegistryVariable()` |
| Statistics | `SAEF_EnsureStatisticsVariables()` and statistic updates |
| Error history | `SAEF_EnsureErrorRingBufferVariable()` and append/clear operations |
| Configuration fingerprint | `SAEF_CreateConfigurationHash()` |
| MQTT device instance | `SAEF_EnsureInstance()` |
| Command/state events | `SAEF_EnsureTriggeredScriptEvent()` |
| State confirmation | `SAEF_WaitForVariable()` |

The candidate must not wrap these functions in a second public API.

## 4. Normalized Entity Contract

Raw aliases such as `powerID` are accepted only at the configuration boundary.
Every enabled entity is normalized into:

```text
deviceId
entityId
class
topicId
capabilities
  power
    stateVariableID
    actionVariableID
    invert
  brightness?
    stateVariableID
    actionVariableID
  rgb?
    stateVariableID
    actionVariableID
  colorTemperature?
    stateVariableID
    actionVariableID
    minimumKelvin
    maximumKelvin
confirmation
  timeoutMilliseconds
  pollIntervalMilliseconds
```

A capability exists only when both state and action IDs exist after alias
normalization. A partial pair is a configuration error, not an absent
capability.

## 5. Validation Boundary

Validation occurs in two stages before MQTT publication, commands or cleanup:

1. Core validation checks configuration shape, stable IDs, uniqueness, topic
   safety, capability pairs, ranges and supported classes.
2. Runtime validation checks Symcon object existence, variable types and
   `HasAction()` for action variables.

The setup of diagnostic storage is the only permitted pre-validation side
effect. Failures before diagnostics exist remain visible through the Symcon log
and exceptions, consistent with EK-006.

## 6. Run Modes

### Reconcile

Used for manual execution and explicit full refresh:

1. validate complete desired configuration;
2. ensure all desired owned objects and events;
3. publish changed discovery payloads;
4. publish changed or forced runtime state;
5. compare previous managed entries with desired entries;
6. remove only exact previously registered resources that are no longer
   desired;
7. persist registry and successful statistics.

### Command Dispatch

Used when the trigger variable belongs to a registered MQTT command adapter:

1. resolve exactly one entity and command from the registry index;
2. strictly parse the command payload;
3. invoke one `RequestAction()`;
4. wait for bounded state confirmation where an observable target exists;
5. publish the observed entity state;
6. persist command outcome and statistics.

The dispatcher does not reconcile or reconfigure every other MQTT adapter.

### State Dispatch

Used when the trigger variable belongs to a registered state event:

1. resolve exactly one entity from the registry index;
2. read that entity's complete state;
3. publish only changed runtime payloads for that entity;
4. persist runtime hash and statistics.

### Unknown Variable Trigger

An unregistered variable trigger is rejected and logged. It must not silently
fall back to full reconciliation.

## 7. Command Result Contract

Command processing returns an explicit result with one of:

- `confirmed`;
- `accepted_unconfirmed` only where the configuration explicitly permits a
  state without observable confirmation;
- `invalid_payload`;
- `action_failed`;
- `confirmation_timeout`;
- `publish_failed`.

Only `confirmed` and explicitly allowed `accepted_unconfirmed` outcomes count
as successful commands. Failures propagate to the run result and cannot be
followed by a successful execution status.

No automatic command retry is introduced in G4.

## 8. MQTT Transport Boundary

The runtime accepts either an IP-Symcon MQTT Client gateway or an IP-Symcon
MQTT Server gateway. The documented publish path is `RequestAction()` on the
Value variable of the matching MQTT Device whose topic and retain behavior are
configured on that instance.

The candidate keeps this transport behind one runtime responsibility. It uses
`SAEF_EnsureInstance()` for instance identity and compatibility, then applies
the MQTT-specific configuration locally.

G4 must measure and document whether one reconfigured publisher instance or
deterministic per-topic publisher instances provide the safer bounded runtime.
The private RC2 approach of applying one publisher instance for every message
is not adopted without this verification.

## 9. Managed-State and Cleanup Contract

Each managed entity registry entry contains only the small metadata needed to
reconcile ownership:

- stable entity key and UUID;
- discovery and runtime topic names required for later retained cleanup;
- discovery and runtime hashes;
- exact command instance Idents;
- exact command event Idents;
- exact state event Idents;
- trigger-variable indexes;
- schema version and last successful timestamps.

Cleanup operates only on previous registry entries missing from the validated
desired state. It does not search by broad prefix, display name or historical
installation fragments.

Deletion of Symcon objects is not generalized into a new public helper during
G4. The candidate uses an ownership-exact local operation and refuses deletion
on parent, Ident or object-type mismatch.

## 10. Diagnostics Contract

The candidate uses:

- Registry for the managed-state map and small run metadata;
- typed Statistics variables for executions, successes, commands, publish
  counts, skips, failures and timestamps;
- ErrorRingBuffer for bounded classified failure context;
- ConfigurationHash for the desired configuration fingerprint.

There is no second general monitor ring in G4. Optional debug logging is
ephemeral and disabled by default.

## 11. Deployment Boundary

The candidate loads canonical helpers from repository-relative paths for
offline development and verification.

It is not directly deployable as a copied IP-Symcon script. ADR-0005 currently
has a proven generated bundle only for the minimal EnsureVariable pilot. Live
use requires a separately reviewed deployment adapter:

- either an expanded deterministic helper bundle manifest and its complete
  dependency closure;
- or a native module/library packaging decision.

G4 must not embed copied helper bodies or introduce a private helper script ID
to bypass this gate.

## 12. G4 Implementation Sequence

1. Correct RS-001.16 to distinguish parent-automation action binding from
   inline event PHP code.
2. Implement and test the pure core contract.
3. Implement diagnostics initialization through canonical helpers.
4. Implement reconcile mode without destructive cleanup enabled.
5. Implement command and state dispatch with exact registry indexes.
6. Implement ownership-exact cleanup behind offline tests.
7. Run complete static checks and open G5 deterministic verification.

## 13. G4 Exit Criteria

- candidate files are complete and contain no private data;
- no existing helper body is duplicated;
- no installation-specific legacy cleanup remains;
- invalid payloads cannot become zero-value commands through scalar casts;
- action variables are validated with `HasAction()`;
- command failures cannot produce a successful run result;
- all events use the canonical triggered-event helper;
- state confirmation is bounded;
- every created resource has one exact registry identity;
- repository checks pass;
- deployment remains explicitly blocked pending its separate adapter.
