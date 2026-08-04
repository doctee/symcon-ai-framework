# 177 Native MQTT Transient Readiness Correction Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed; durable-barrier correction installed and verified
read-only with MQTT disabled
**Date:** 2026-07-29
**Scope:** Execute only disabled Symcon update Gate B from step 175

## 1. Purpose

Step 176 published the transient-readiness correction to
`doctee/symcon-navimow/main`.

This step:

1. captured the installed disabled baseline twice;
2. executed exactly one supported Module Control update;
3. verified the installed target commit twice;
4. proved instance, variable, archive, command and topology continuity;
5. verified the stopped MQTT lifecycle and stable connection counters;
6. stopped with MQTT disabled and credential-free.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update auf die MQTT-Transient-Readiness-Korrektur mit deaktiviertem MQTT freigegeben.
```

This authorized one Module Control update and bounded read-only probes.

It did not authorize:

- MQTT activation or credential retrieval;
- a broker connection or MQTT publication;
- a Symcon service restart;
- Core instance mutation;
- mower commands;
- `MC_ReloadModule()`.

## 3. Repeated Pre-Update Baseline

The established private read-only compatibility projection ran twice.

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
commit: 71a90f69
clean:  true
valid:  true
```

The two runs agreed on:

- one Account, Configurator, Device and Receiver;
- retained MQTT and WebSocket parent chain;
- 14 expected variables with stable identities and metadata;
- five expected Archive Control logging contracts;
- queryable logged history;
- stable command evidence;
- four exact QoS-0 subscriptions without wildcard;
- connected Account without reauthentication requirement;
- usable access token;
- disabled MQTT feature and inactive WebSocket;
- empty Authorization headers, MQTT username and MQTT password.

## 4. Supported Module Control Update

Exactly one supported operation ran:

```text
MC_UpdateModule(ModuleControl, "symcon-navimow"): 1
MC_ReloadModule():                                0
```

The operation returned `true`.

Its bounded result established:

```text
before: main@71a90f69, clean and valid
after:  main@7d141f76, clean and valid
```

The installed abbreviation is an exact prefix of:

```text
7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
```

No update retry was performed.

## 5. Repeated Post-Update Verification

Both independent post-update executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
branch:            main
commit:            7d141f76
clean:             true
valid:             true
```

Every compatibility hash remained equal before and after the update.

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
| access token | usable |
| MQTT feature | disabled |
| WebSocket | inactive |
| Authorization headers | empty |
| MQTT username and password | empty |

The user's mower-variable logging and accumulated history remain intact.

## 6. Disabled Lifecycle Verification

The bounded diagnostic projection passed twice with equal results:

```text
feature enabled:          false
configuration status:     disabled
lifecycle:                Disabled
next attempt:             0
reconnect attempt:        0
connection attempts:      11
connection successes:     3
connection failures:      0
Core-resume observations: 0
last connection trigger:  initial
```

The connection counters and trigger timestamps remained unchanged across both
post-update observations. Therefore the update caused no MQTT connection
attempt.

The first diagnostic probe tried to use `IPS_GetTimerInterval()`. That
function is unavailable in the Symcon script environment, so the probe's own
guard returned `probe-failed`.

Classification:

```text
MCP transport error:       none
PHP execution error:       none
truncation:                false
installation mutation:    none
update retry:              none
corrected read-only probe: PASS twice
```

The stopped lifecycle is established through the public diagnostics
`nextAttemptAt=0`, `reconnectAttempt=0`, stable counters and the disabled
transport state.

## 7. REST and Command Compatibility

The post-update projections prove:

- connected authentication state;
- no reauthentication requirement;
- usable access token;
- stable REST-related Account variable identities;
- unchanged command evidence;
- unchanged Device variable identities and metadata;
- no mower command during the update.

No manual REST request was manufactured. The existing regular read-only
polling contract remains available and authoritative for public state.

## 8. Architecture Decisions

### AD-NAV-617: Use one supported update only

**Decision:** Execute exactly one `MC_UpdateModule()` and independently verify
the installed commit.

**Reason:** A successful target projection is stronger evidence than repeating
a live mutation.

### AD-NAV-618: Preserve logging identity as a release invariant

**Decision:** Require variable, Archive Control and command-evidence hashes to
remain equal across the update.

**Reason:** Existing archive history depends on stable variable identities and
logging configuration.

### AD-NAV-619: Distinguish probe limitation from module failure

**Decision:** Record unavailable timer readback separately and use public
lifecycle diagnostics for the stopped-state proof.

**Reason:** A missing global script API neither changes the installation nor
invalidates the module's own bounded diagnostics.

### AD-NAV-620: Keep installation separate from transport staging

**Decision:** End Gate B with MQTT disabled, inactive and credential-free.

**Reason:** Source compatibility must be proven before retained topology and
active restart behavior are evaluated in later gates.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| read-only baseline projections | 2 |
| read-only post-update projections | 2 |
| successful lifecycle diagnostic projections | 2 |
| failed read-only timer projection | 1 |
| MQTT enable operations | 0 |
| MQTT connection-attempt delta | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| service restarts | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-transient-readiness-correction-symcon-update/
    gate-b-evidence-closure.json
```

Reusable private read-only source:

```text
private/navimow-capture/
  native-mqtt-passive-pilot-recovery-readonly.php
```

No credential, endpoint, topic, payload, personal ObjectID or installation
detail is present in this public report.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate B compatibility | PASS |
| Gate C inactive staging | CLOSED |
| Gate D renewed persistence acceptance | NOT GIVEN |
| Gate E temporary activation | CLOSED |
| Gate F active restart | CLOSED |
| Gate G mandatory cleanup | NOT ENTERED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 12. Recommended Next Step

After separate authorization, execute only Gate C from step 175:

```text
Inaktives Staging der MQTT-Transient-Readiness-Korrektur freigegeben.
```

Gate C must remain credential-free and must not activate MQTT, connect to the
broker or restart Symcon.
