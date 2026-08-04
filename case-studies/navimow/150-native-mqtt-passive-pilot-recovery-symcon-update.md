# 150 Native MQTT Passive Pilot Recovery Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed; published recovery installed and verified read-only
with MQTT disabled
**Date:** 2026-07-28
**Scope:** Execute only disabled Symcon update Gate B from step 148

## 1. Purpose

Step 149 published the exact recovery hardening to
`doctee/symcon-navimow/main`.

This step:

1. captured the installed baseline twice;
2. executed exactly one supported Module Control update;
3. repeated the complete compatibility projection twice;
4. verified the new bounded recovery diagnostics;
5. stopped with MQTT disabled and credential-free.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update auf die MQTT-Pilot-Recovery-Härtung mit read-only Prüfung freigegeben.
```

This authorized one Module Control update and bounded read-only probes.

It did not authorize:

- MQTT feature activation;
- credential retrieval or broker connection;
- a Symcon restart;
- token or connectivity experiments;
- Core instance creation or reparenting;
- MQTT publish or mower commands;
- `MC_ReloadModule()`.

## 3. Repeated Pre-Update Baseline

The private bounded read-only projection ran twice.

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
commit: 511c7bbe
clean:  true
valid:  true
```

The runs were identical and proved:

- one Account, Configurator, Device and Receiver;
- unchanged productive and retained Core topology;
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
commit:           7c1747cc
clean:            true
valid:            true
```

Call accounting:

```text
MC_UpdateModule: 1
MC_ReloadModule: 0
```

### Probe comparison deviation

The mutation probe initially projected `pass=false` with
`post-update-contract-failed`.

The update itself had succeeded. The only failed assertion expected the
seven-character value `7c1747c`, while Module Control returned the
eight-character abbreviation `7c1747cc`.

Classification:

```text
update failure:        no
installation failure:  no
probe assertion defect: yes
second update allowed: no
```

No second update was executed. The installed commit was instead verified by
two independent post-update read-only projections.

## 5. Repeated Post-Update Verification

Both post-update executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
branch:            main
commit:            7c1747cc
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

## 6. Recovery Diagnostics

One final read-only diagnostic projection verified:

```text
output size:                919 bytes
feature enabled:            false
configuration status:       disabled
new lifecycle fields:       present
new recovery counters:      present
bounded projection:         true
diagnostic contract:        PASS
```

Verified lifecycle fields include:

```text
lastTransitionReason
healthySince
nextAttemptAt
reconnectAttempt
```

Verified statistics include:

```text
connectionSuccesses
connectionFailures
unexpectedDisconnects
reconnectAttempts
reconnectExhausted
credentialRotations
```

No raw attribute, topic, endpoint, token, credential or ObjectID was returned.

## 7. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| read-only baseline projections | 2 |
| read-only post-update projections | 2 |
| read-only recovery diagnostic projections | 1 |
| MQTT enable operations | 0 |
| MQTT connection attempts | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was checked for transport error, PHP execution error and
truncation separately.

## 8. Final State

```text
installed commit:           7c1747cc
module repository:          clean and valid
compatibility:              PASS
MQTT feature:               disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
live MQTT session:          absent
```

REST remains authoritative.

## 9. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-passive-pilot-recovery-symcon-update/
    gate-b-evidence-closure.json
```

Reusable private read-only source:

```text
private/navimow-capture/
  native-mqtt-passive-pilot-recovery-readonly.php
```

No private installation identifier, credential, topic, endpoint or payload is
present in this public report.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate B compatibility | PASS |
| Gate C inactive staging | CLOSED |
| Gate D passive activation | CLOSED |
| restart observation | CLOSED |
| natural token observation | CLOSED |
| degraded-connectivity observation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 11. Recommended Next Step

The retained chain can now be validated as an inactive pilot candidate without
retrieving credentials or connecting to the broker.

Create:

```text
151-native-mqtt-passive-pilot-inactive-staging.md
```

Required authorization:

```text
Inaktives Staging des receive-only MQTT-Piloten freigegeben.
```
