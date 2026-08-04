# 114 Native MQTT Diagnostics Symcon Retest Report

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed; update compatibility and bounded read-only
diagnostics verified, MQTT remains disabled
**Date:** 2026-07-28
**Scope:** Update the private pilot to the step-113 publication and verify the
new diagnostic contract without activating MQTT

## 1. Purpose

This step executes Gate B from steps 112 and 113 after explicit authorization:

```text
Symcon-Update und read-only Diagnoseprüfung freigegeben.
```

It covers:

- a private pre-update baseline and deterministic repeat;
- one controlled Module Control update;
- complete post-update compatibility verification;
- one bounded call to `NAVAC_GetMqttDiagnostics()`;
- historical diagnostic classification;
- final disabled-state and compatibility closure.

It does not enable or connect MQTT, retrieve MQTT credentials, publish an MQTT
message, call a mower command, change Archive Control, create an object or call
`MC_ReloadModule()`.

## 2. Tested Publication

Dedicated module repository:

```text
doctee/symcon-navimow
```

Published branch and commit:

```text
main
efb8343e50dbea612db26e49324130ed3d039e90
feat: expose bounded MQTT diagnostics
```

Module Control does not expose the installed Git commit through the supported
module query used in this installation. The installed increment is identified
by the successful update from `main` and the newly available diagnostic
wrapper.

## 3. Pre-Update Baseline

The bounded private probe was executed twice before the update.

Both executions had:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
aggregate result:  PASS
```

The repeat proved:

- identical Account, Configurator, Device and Receiver identities;
- 14 identical variable identities and metadata contracts;
- five identical Archive Control logging contracts;
- bounded history queryability for every logged variable;
- identical Receiver, MQTT Client and WebSocket Client topology;
- identical productive status and safe configuration projections;
- connected OAuth with no reauthentication requirement;
- MQTT shadow disabled;
- WebSocket inactive;
- authorization headers empty;
- MQTT username and password empty;
- stable client ID and four exact subscriptions retained;
- no wildcard subscription.

`NAVAC_GetMqttDiagnostics()` was not available before the update.

## 4. Module Update

Exactly one update was performed:

```text
MC_UpdateModule(ModuleControl, "symcon-navimow")
```

Result:

```text
true
```

`MC_ReloadModule()` was not called.

The update completed without transport, PHP execution or output-truncation
error.

## 5. Post-Update Compatibility

The full compatibility probe passed after ApplyChanges completed.

| Invariant | Result |
|---|---|
| productive instance identities | unchanged |
| 14 variable identities | unchanged |
| variable metadata and profiles | unchanged |
| five logging and aggregation contracts | unchanged |
| archive history queryability | PASS |
| Receiver and Core topology identities | unchanged |
| productive module statuses | expected |
| OAuth connection | retained |
| reauthentication requirement | false |
| REST error state | unchanged |
| MQTT shadow | disabled |
| Receiver selection | retained |
| WebSocket | inactive |
| Authorization headers | empty |
| MQTT username and password | empty |

No instance or variable was deleted, recreated or reparented. The user's
existing archive logging remains intact.

## 6. Read-Only Diagnostic Verification

The new wrapper was available:

```text
NAVAC_GetMqttDiagnostics
```

It was called exactly once while MQTT remained disabled.

Result contract:

```text
output size:         665 bytes
formatVersion:       1
featureEnabled:      false
configurationStatus: disabled
lifecycle state:     Disabled
schema validation:   PASS
privacy validation:  PASS
```

The result contained the exact fixed top-level, lifecycle, statistics, error
and shadow keys. Counters and timestamps were nonnegative integers, and fixed
result fields used only allowlisted values.

The returned JSON contained no:

- device or account identity;
- ObjectID;
- endpoint, hostname or topic;
- authorization header or token;
- MQTT username, password or client ID;
- ownership registry or raw persistent JSON.

Hashes covering exposed productive configurations, Core connections, instance
statuses, variable identities, metadata and values were identical immediately
before and after the wrapper call.

## 7. Historical Checkpoint

The retained diagnostic state reported:

```text
connection attempts: one
received:            zero
accepted:            zero
rejected:            zero
errors:              zero
```

This confirms that step 110 initiated one connection attempt but provides no
retrospective accepted-message evidence.

The historical conclusion therefore remains:

```text
prior receive acceptance is inconclusive
```

Step 110 is not reclassified.

## 8. Final State

The final compatibility repeat passed:

- 14 of 14 variables retained;
- all five archive contracts retained and queryable;
- all productive and Core instance identities retained;
- OAuth connected and no reauthentication required;
- REST error count unchanged;
- MQTT shadow disabled;
- WebSocket inactive;
- authorization and MQTT credential slots empty;
- Receiver selection, client ID and subscriptions retained.

No live MQTT session remains.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| diagnostic wrapper invocations | 1 |
| `MC_ReloadModule()` calls | 0 |
| MQTT enable operations | 0 |
| MQTT connection attempts | 0 |
| MQTT publish attempts | 0 |
| mower actions | 0 |
| created or deleted objects | 0 |
| archive mutations | 0 |

## 10. Architecture Decisions

### AD-NAV-432: Use Module Control update without reload

**Decision:** Use one authorized `MC_UpdateModule()` operation and prohibit
`MC_ReloadModule()`.

**Reason:** The published repository update is the intended installation path;
reload would bypass the controlled update contract.

**Consequence:** The runtime test represents the same update mechanism used by
the private pilot.

### AD-NAV-433: Keep diagnostic observation side-effect free

**Decision:** Hash exposed module, Core and variable state immediately before
and after the single wrapper invocation.

**Reason:** A read-only API needs live evidence that it does not alter the
supported observable state.

**Consequence:** Gate B proves runtime read-only behavior in addition to the
offline code and fixture evidence.

### AD-NAV-434: Preserve the historical negative finding

**Decision:** Treat zero received and accepted counters as no retrospective
receive proof.

**Reason:** A successful transport connection is not equivalent to productive
Receiver acceptance.

**Consequence:** A new bounded one-shot session is still required for accepted
receive evidence.

## 11. Private Evidence

Private evidence is stored below:

```text
private/navimow-capture/output/native-mqtt-diagnostics/
  pre-update-baseline.json
  pre-update-repeat.json
  pre-update-repeat-check.json
  module-update.json
  post-update-compatibility.json
  post-update-comparison.json
  read-only-diagnostics.json
  post-diagnostics-compatibility.json
  post-diagnostics-comparison.json
  gate-b-evidence-closure.json
```

The public report contains no private ObjectID, hash, endpoint, topic,
credential, device identity or installation metadata.

## 12. Gate Decision

| Gate | Result |
|---|---|
| deterministic pre-update baseline | PASS |
| one Module Control update | PASS |
| instance and variable compatibility | PASS |
| archive continuity | PASS |
| Core topology continuity | PASS |
| bounded diagnostic schema | PASS |
| diagnostic privacy | PASS |
| wrapper read-only hashes | PASS |
| final disabled-state cleanup | PASS |

**Gate B: COMPLETE.**

Gate C remains closed. Its required authorization is:

```text
Ein einmaliger MQTT-Diagnose-Retest mit automatischem Cleanup ist freigegeben.
```

That later gate permits one enable, one Connect attempt, bounded observation,
one Disconnect, credential cleanup and final disable. It permits no retry or
restart.
