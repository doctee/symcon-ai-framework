# 120 Native MQTT Receiver Diagnostics Symcon Update Report

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed; update compatibility and both diagnostic wrappers
verified read-only
**Date:** 2026-07-28
**Scope:** Update the private pilot to step 119 without enabling MQTT

## 1. Authorization

Gate B from steps 118 and 119 was explicitly authorized with:

```text
Symcon-Update und read-only Receiver-Diagnoseprüfung freigegeben.
```

The authorized scope contained:

- a deterministic private pre-update baseline;
- one Module Control update;
- post-update compatibility readback;
- one Receiver diagnostic call;
- one Account diagnostic call;
- final disabled-state verification.

It did not authorize MQTT connection, mower activity, publication or a module
reload.

## 2. Tested Publication

Standalone repository:

```text
doctee/symcon-navimow
```

Published commit:

```text
046529c518feefb15a51bd2f1c404401b3a7f474
feat: expose bounded MQTT Receiver diagnostics
```

The installed increment is identified by the successful update from `main` and
the new Receiver wrapper. The supported Module Control query does not expose a
reliable installed Git commit value.

## 3. Pre-Update Baseline

The bounded compatibility probe was executed twice for evidence before the
update. One preceding read-only contract inspection used the same probe while
preparing the evidence run and performed no mutation.

Both evidence executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
aggregate result:  PASS
```

The repeat proved:

- one Account, Configurator, Device and Receiver;
- unchanged instance identities and parent relationships;
- 14 expected variable identities and metadata contracts;
- five unchanged Archive Control logging contracts;
- history queryability for every logged variable;
- unchanged Receiver, MQTT Client and WebSocket Client topology;
- Account connected without reauthentication requirement;
- MQTT shadow disabled;
- WebSocket inactive;
- authorization headers empty;
- MQTT username and password empty;
- four exact QoS-0 subscriptions without wildcard;
- Receiver-to-Account binding retained.

Before update:

```text
NAVMQTTRX_GetReceiveDiagnostics available: false
```

## 4. Module Update

Exactly one update was executed through the supported Module Control API:

```text
MC_UpdateModule(ModuleControl, "symcon-navimow")
```

Result:

```text
true
```

The call completed with:

```text
transportError: null
executionError: null
truncated:      false
```

`MC_ReloadModule()` was not called.

### Execution Clarification

Step 118 described the update as a manual user action in Module Control.

For this execution, the explicitly authorized Symcon update was performed
through the same controlled `MC_UpdateModule()` path already proven in step
114. This avoids an unnecessary UI handoff while preserving Module Control as
the update owner.

This is recorded as a procedural deviation from the manual-click wording, not
as an expansion of the mutation scope:

- exactly one update;
- same module and branch;
- no reload;
- no Core or object lifecycle mutation.

## 5. Post-Update Compatibility

The complete post-update probe passed.

| Invariant | Result |
|---|---|
| productive instance identities | unchanged |
| parent relationships | unchanged |
| 14 variable identities | unchanged |
| variable types and profiles | unchanged |
| action contracts | unchanged |
| five logging contracts | unchanged |
| archive aggregation | unchanged |
| logged history queryability | PASS |
| native transport topology | unchanged |
| Receiver-to-Account binding | retained |
| Account authentication | retained |
| MQTT shadow | disabled |
| WebSocket | inactive |
| authorization headers | empty |
| MQTT username and password | empty |

The user's configured logging for mower variables remains intact.

After update:

```text
NAVMQTTRX_GetReceiveDiagnostics available: true
```

## 6. Receiver Diagnostic Verification

The new wrapper was called exactly once:

```text
NAVMQTTRX_GetReceiveDiagnostics
```

Result:

```text
output size:       239 bytes
formatVersion:     1
schema:            PASS
privacy:           PASS
receiveCalls:      0
forwarded:         0
lastResult:        none
```

The zero counters are expected because `ReceiveDiagnostics` is a new
Receiver-owned attribute and no live MQTT session was permitted by Gate B.

The result contained only the fixed projection and allowlisted result codes.
It contained no topic, payload, device identity, ObjectID, endpoint, credential
or raw persistent state.

## 7. Account Diagnostic Verification

The existing Account wrapper was called exactly once:

```text
NAVAC_GetMqttDiagnostics
```

Result:

```text
output size:          665 bytes
formatVersion:        1
featureEnabled:       false
configurationStatus: disabled
schema:               PASS
privacy:              PASS
```

Retained historical counters reported:

```text
connectionAttempts: 2
received:           0
accepted:           0
rejected:           0
```

The two attempts correspond to the previous productive native connection
sessions. Their zero receive counters remain a historical finding and are not
reclassified by this update.

## 8. Read-Only Proof

Before and after both wrapper calls, a bounded hash covered:

- productive and Core instance statuses and relationships;
- configuration hashes;
- all 14 variable identities, metadata and values;
- all five logging and aggregation contracts.

Result:

```text
observable state hashes equal: PASS
```

The final full compatibility repeat also matched:

- instance identities;
- variable identity hash;
- archive hash;
- topology identity;
- safe operational projection.

Diagnostic reads therefore caused no supported observable state change.

## 9. Final State

Final readback proved:

```text
compatibility:              PASS
MQTT shadow:                disabled
WebSocket:                  inactive
authorization headers:      empty
MQTT username and password: empty
Receiver wrapper:           available
live MQTT session:          absent
```

No mower action was sent.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| `MC_ReloadModule()` calls | 0 |
| Receiver diagnostic calls | 1 |
| Account diagnostic calls | 1 |
| MQTT enable operations | 0 |
| MQTT Connect operations | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP transport result was checked separately from PHP execution. No
result was truncated.

## 11. Private Evidence

Private machine-readable evidence is stored below:

```text
private/navimow-capture/output/native-mqtt-receiver-diagnostics/
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

The reusable private read-only source is:

```text
private/navimow-capture/
  native-mqtt-receiver-diagnostics-readonly.php
```

No private installation identifier, endpoint, topic, credential or payload is
copied into this public report.

## 12. Architecture Decisions

### AD-NAV-437: Retain Module Control ownership

**Decision:** Use one authorized `MC_UpdateModule()` operation and continue to
prohibit `MC_ReloadModule()`.

**Reason:** This uses the installation's supported repository update path and
matches the established step-114 procedure.

### AD-NAV-438: Compare Receiver and Account boundaries separately

**Decision:** Read each wrapper once and retain both projections.

**Reason:** The next live test must distinguish native Receiver ingress from
Account acceptance.

### AD-NAV-439: Preserve user logging as a compatibility gate

**Decision:** Treat the five enabled logging contracts as mandatory pre/post
invariants.

**Reason:** Module evolution must not recreate variables or silently remove the
user's archive configuration.

## 13. Gate Decision

| Gate | Result |
|---|---|
| deterministic pre-update baseline | PASS |
| one Module Control update | PASS |
| instance and variable compatibility | PASS |
| archive continuity | PASS |
| Receiver wrapper availability | PASS |
| Receiver schema and privacy | PASS |
| Account schema and privacy | PASS |
| wrapper read-only hashes | PASS |
| final disabled and credential-empty state | PASS |

**Gate B: COMPLETE.**

Gate C remains closed.

Required authorization:

```text
Ein einmaliger Receiver-Diagnose-Live-Test mit automatischem Cleanup ist freigegeben.
```

After authorization, the live run may use:

- an already active scheduled mowing run; or
- one supervised user-controlled start through the official app before
  Connect.

It permits one MQTT enable, one Connect, bounded observation, one Disconnect,
credential cleanup and final disable. It permits no retry, MQTT publish, module
mower command or restart.
