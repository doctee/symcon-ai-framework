# 168 Native MQTT Core Resume Ordering Correction Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed; corrected ordering installed and verified read-only
with MQTT disabled
**Date:** 2026-07-29
**Scope:** Execute only disabled Symcon update Gate B from step 166

## 1. Purpose

Step 167 published the MQTT Core-resume ordering correction to
`doctee/symcon-navimow/main`.

This step:

1. captured the installed disabled baseline twice;
2. executed exactly one supported Module Control update;
3. verified the installed target commit twice;
4. proved variable, archive, command and topology continuity;
5. verified the four new bounded diagnostic fields;
6. stopped with MQTT disabled and credential-free.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update auf die MQTT-Core-Resume-Ordering-Korrektur mit deaktiviertem MQTT freigegeben.
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

The established private read-only projection ran twice.

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
commit: aed0b434
clean:  true
valid:  true
```

The two runs agreed on:

- one Account, Configurator, Device and Receiver;
- 14 expected variables;
- five expected Archive Control logging contracts;
- queryable logged history;
- unchanged command evidence;
- retained Receiver, MQTT and WebSocket topology;
- four exact QoS-0 subscriptions without wildcard;
- connected Account without reauthentication requirement;
- usable access token;
- disabled MQTT feature and inactive WebSocket;
- empty Authorization headers, MQTT username and MQTT password.

## 4. Supported Module Control Update

Exactly one supported operation ran:

```text
MC_UpdateModule(ModuleControl, "symcon-navimow")
```

The operation returned `true`.

Call accounting:

```text
MC_UpdateModule: 1
MC_ReloadModule: 0
```

The compact mutation projection used incorrect repository-info field names and
therefore could not itself report the before and after commit. No retry was
performed. The installed target was instead established by the repeated
independent post-update projection.

## 5. Repeated Post-Update Verification

Both post-update executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
branch:            main
commit:            71a90f69
clean:             true
valid:             true
```

The installed abbreviation is an exact prefix of:

```text
71a90f697031da017264d2a33555b9b6693d8776
```

All compatibility hashes remained equal before and after the update.

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

The user's existing mower-variable logging and history remain intact.

## 6. Ordering Diagnostics

The bounded diagnostic projection ran twice and passed twice.

New lifecycle fields:

```text
lastKernelCoreClassification
lastKernelCoreClassificationAt
```

New statistics fields:

```text
lastConnectionTrigger
lastConnectionTriggerAt
```

Disabled-state result:

```text
feature enabled:                    false
configuration status:               disabled
last kernel classification:         none
last kernel classification time:    0
last connection trigger:            none
last connection trigger time:       0
```

This verifies field availability and neutral disabled initialization only.
Inactive staging and active Core-resume behavior remain separate gates.

## 7. Architecture Decisions

### AD-NAV-579: Verify the target independently from the update response

**Decision:** Treat the established read-only repository projection as the
authority for the installed branch and commit.

**Reason:** The one-shot update returned success, while its compact metadata
projection used incompatible field names. Repeating the mutation would have
violated the one-update gate.

### AD-NAV-580: Preserve logging identity as a release invariant

**Decision:** Require variable, Archive Control and command-evidence hashes to
remain equal across the module update.

**Reason:** Existing logged history depends on stable variable identities and
logging configuration.

### AD-NAV-581: Keep installation separate from transport staging

**Decision:** End Gate B with MQTT disabled, inactive and credential-free.

**Reason:** Source compatibility must be proven before the retained transport
topology is accepted for a later Core-resume test.

## 8. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| read-only baseline projections | 2 |
| read-only post-update projections | 2 |
| read-only diagnostic projections | 2 |
| MQTT enable operations | 0 |
| MQTT connection attempts | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| service restarts | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 9. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-ordering-correction-symcon-update/
    gate-b-evidence-closure.json
```

Reusable private read-only source:

```text
private/navimow-capture/
  native-mqtt-passive-pilot-recovery-readonly.php
```

No credential, endpoint, topic, payload, personal ObjectID or installation
detail is present in this public report.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate B compatibility | PASS |
| Gate C inactive staging | CLOSED |
| Gate D renewed persistence acceptance | NOT GIVEN |
| Gate E temporary activation | CLOSED |
| Gate F corrected active restart | CLOSED |
| Gate G mandatory cleanup | NOT ENTERED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 11. Recommended Next Step

After separate authorization, execute only Gate C from step 166:

```text
Inaktives Staging der MQTT-Core-Resume-Ordering-Korrektur freigegeben.
```

Gate C must remain credential-free and must not activate MQTT, connect to the
broker or restart Symcon.
