# 187 Native MQTT Core Resume Health Observation Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed; observation correction installed and verified with
MQTT disabled
**Date:** 2026-07-29
**Scope:** Execute only disabled Symcon update Gate B from step 185

## 1. Purpose

Step 186 published the bounded Core-resume health observation to
`doctee/symcon-navimow/main`.

This step:

1. captured two equal read-only installation baselines;
2. executed exactly one supported Module Control update;
3. verified the installed target commit twice;
4. proved instance, variable, archive, command and topology continuity;
5. verified a stopped, credential-free MQTT lifecycle twice;
6. stopped without staging, activation or restart.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update auf die MQTT-Core-Resume-Health-Observation mit deaktiviertem MQTT freigegeben.
```

This authorized one `MC_UpdateModule()` and bounded read-only probes.

It did not authorize:

- MQTT activation or credential retrieval;
- a broker connection or MQTT publication;
- inactive staging or Core mutation;
- a Symcon service restart;
- mower commands;
- `MC_ReloadModule()`.

## 3. Repeated Pre-Update Baseline

Both independent MCP executions reported:

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
commit: 7d141f76
clean:  true
valid:  true
```

The two runs agreed on:

- one retained Account, Configurator, Device and Receiver;
- the retained MQTT and WebSocket parent chain;
- 14 expected variables with stable identities and metadata;
- five expected Archive Control logging contracts;
- queryable logged history;
- stable command evidence;
- four exact QoS-0 subscriptions without wildcard;
- connected Account authentication without reauthentication;
- disabled MQTT and inactive WebSocket;
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
before: main@7d141f76, clean and valid
after:  main@45c7bd50, clean and valid
```

The installed abbreviation is an exact prefix of the published commit:

```text
45c7bd509f95865030f676184a1aeff4219c0750
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
commit:            45c7bd50
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
| MQTT feature | disabled |
| WebSocket | inactive |
| Authorization headers | empty |
| MQTT username and password | empty |

The user's mower-variable logging and accumulated history remain intact.

## 6. Disabled Lifecycle Verification

Two bounded diagnostic projections passed and were equal:

```text
feature enabled:                  false
configuration status:             disabled
lifecycle:                        Disabled
next attempt:                     0
reconnect attempt:                0
Core observation count:           0
Core observation deadline:        0
connection attempts:              12
connection successes:             4
connection failures:              0
Core-resume observations:         0
last connection trigger:          initial
```

The last connection attempt occurred at:

```text
2026-07-29T06:07:35Z
```

The supported update ran at:

```text
2026-07-29T08:13:34Z
```

The counters and timestamps remained unchanged across both post-update
observations. The last attempt predates the update by more than two hours.
Therefore the Module Control update caused no MQTT connection attempt.

## 7. REST and Command Compatibility

The post-update projections prove:

- connected authentication state;
- no reauthentication requirement;
- a currently usable access token;
- stable REST-related Account variables;
- unchanged Device state and command evidence;
- no mower command during the update.

No manual REST request was manufactured. Existing regular read-only polling
remains available and authoritative for public device state.

## 8. Architecture Decisions

### AD-NAV-659: Use one supported update without reload

**Decision:** Execute exactly one `MC_UpdateModule()` and verify the installed
commit independently.

**Reason:** A clean target projection proves installation without broadening
the mutation through `MC_ReloadModule()` or an update retry.

### AD-NAV-660: Preserve variable and archive identity

**Decision:** Treat all variable, metadata, logging and archive hashes as
release invariants.

**Reason:** Existing user logging and accumulated archive history depend on
stable variable identities.

### AD-NAV-661: Establish inactivity through public diagnostics

**Decision:** Prove stopped MQTT through configuration, empty credentials,
Core state and stable diagnostic counters.

**Reason:** These projections distinguish an installed implementation from an
activated transport without accessing private credentials.

### AD-NAV-662: Keep staging and activation separate

**Decision:** End Gate B immediately after the disabled installation proof.

**Reason:** Retained-topology staging and credential persistence introduce
different evidence and authorization requirements.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| read-only baseline projections | 2 |
| read-only post-update projections | 2 |
| read-only lifecycle projections | 2 |
| MQTT enable operations | 0 |
| MQTT connection attempts after update | 0 |
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
  native-mqtt-core-resume-health-observation-symcon-update/
    gate-b-evidence-closure.json
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
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 12. Recommended Next Step

After separate authorization, execute only Gate C from step 185:

```text
Inaktives Staging der MQTT-Core-Resume-Health-Observation freigegeben.
```

Gate C is read-only when the retained topology is valid. It must not retrieve
credentials, activate MQTT, connect to the broker or restart Symcon.
