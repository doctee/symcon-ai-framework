# 158 Native MQTT Kernel Start Reconciliation Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed; published kernel-start reconciliation installed and
verified read-only with MQTT disabled
**Date:** 2026-07-28
**Scope:** Execute only disabled Symcon update Gate B from step 156

## 1. Purpose

Step 157 published the exact kernel-start reconciliation to
`doctee/symcon-navimow/main`.

This step:

1. captured the installed baseline twice;
2. executed exactly one supported Module Control update;
3. repeated the complete compatibility projection twice;
4. verified the new bounded kernel-start diagnostics;
5. stopped with MQTT disabled and credential-free.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update auf die Kernelstart-Reconciliation mit deaktiviertem MQTT freigegeben.
```

This authorized one Module Control update and bounded read-only probes.

It did not authorize:

- MQTT feature activation;
- credential retrieval or a broker connection;
- a Symcon service restart;
- Core instance creation, deletion or reparenting;
- MQTT publish or mower commands;
- `MC_ReloadModule()`.

## 3. Repeated Pre-Update Baseline

The established private bounded read-only projection ran twice.

Both MCP executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
```

Installed source:

```text
branch: main
commit: 7c1747cc
clean:  true
valid:  true
```

The runs were identical and proved:

- one Account, Configurator, Device and Receiver;
- retained WebSocket, MQTT and Receiver topology;
- 14 expected variables;
- five expected Archive Control logging contracts;
- logged history queryable;
- unchanged command evidence;
- Account connected without reauthentication requirement;
- access token usable;
- Receiver paired to Account;
- MQTT feature disabled;
- WebSocket inactive;
- headers, MQTT username and password empty;
- four exact QoS-0 subscriptions without wildcard.

## 4. Supported Module Control Update

Exactly one supported operation ran:

```text
MC_UpdateModule(ModuleControl, "symcon-navimow")
```

Result:

```text
operation result: true
branch:           main
commit:           aed0b434
clean:            true
valid:            true
```

The installed abbreviation is an exact prefix of:

```text
aed0b4348c6e104f6c2f455e71b861d8620a3c95
```

Call accounting:

```text
MC_UpdateModule: 1
MC_ReloadModule: 0
```

No second update was required or executed.

## 5. Repeated Post-Update Verification

Both post-update executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
branch:            main
commit:            aed0b434
clean:             true
valid:             true
```

The repeated post-update hashes were identical to each other and to the
pre-update baseline.

| Contract | Result |
|---|---|
| productive instance identities and connections | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Receiver pairing | retained |
| canonical subscriptions | 4/4 QoS 0 |
| Account authentication | connected |
| reauthentication required | false |
| MQTT feature | disabled |
| WebSocket | inactive |
| Authorization headers | empty |
| MQTT username and password | empty |

The user's configured mower-variable logging remains intact.

## 6. Kernel-Start Diagnostics

One final bounded read-only projection verified the new public diagnostic
shape without returning private values.

Lifecycle fields present:

```text
kernelStartObservedAt
kernelStartReconciledAt
kernelStartTime
lastTransitionReason
nextAttemptAt
reconnectAttempt
```

Statistics fields present:

```text
coreResumeObservations
connectionAttempts
connectionSuccesses
connectionFailures
```

Result:

```text
feature enabled:          false
configuration status:     disabled
kernel fields:            present
Core-resume counter:      present
kernel values sanitized:  true
diagnostic contract:      PASS
```

This step verifies availability and the disabled state only. Actual
`IPS_KERNELSTARTED` observation requires the separately authorized restart
gate.

## 7. Architecture Closure

### AD-NAV-543: Verify installation before kernel observation

**Decision:** Install and verify the new wrapper and diagnostic contract while
MQTT remains disabled and credential-free.

**Reason:** This separates source compatibility from service-restart behavior
and keeps a failed installation from entering a restart test.

### AD-NAV-544: Preserve variable and archive identity across MQTT changes

**Decision:** Instance, variable, command-evidence and Archive Control hashes
must remain identical across the module update.

**Reason:** Existing archive history depends on stable variable identities and
logging configuration.

## 8. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| read-only baseline projections | 2 |
| read-only post-update projections | 2 |
| read-only diagnostic projections | 1 |
| MQTT enable operations | 0 |
| MQTT connection attempts | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| service restarts | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was checked separately for transport error, PHP execution
error and truncation.

## 9. Final State

```text
installed commit:           aed0b434
module repository:          clean and valid
compatibility:              PASS
MQTT feature:               disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
live MQTT session:          absent
```

REST remains authoritative.

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-kernel-start-reconciliation-symcon-update/
    gate-b-evidence-closure.json
```

Reusable private read-only source:

```text
private/navimow-capture/
  native-mqtt-passive-pilot-recovery-readonly.php
```

No private installation identifier, credential, topic, endpoint or payload is
present in this public report.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate B compatibility | PASS |
| Gate C inactive topology staging | CLOSED |
| Gate D disabled kernel-hook restart | CLOSED |
| Gate E credential-persistence acceptance | CLOSED |
| Gate F receive-only activation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

The next independently authorized action is Gate C from step 156:

```text
Inaktives Staging der Kernelstart-Reconciliation freigegeben.
```
