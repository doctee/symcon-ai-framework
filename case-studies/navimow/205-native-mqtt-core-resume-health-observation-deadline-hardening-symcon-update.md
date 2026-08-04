# Native MQTT Core-Resume Deadline Hardening Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Gate C passed; deadline hardening installed and verified with MQTT
disabled
**Date:** 2026-07-29
**Scope:** Execute only the disabled Symcon update Gate C from steps 201 and
204

## 1. Purpose

Step 202 published the six-point Core-resume observation horizon through
`+180 s`. Step 204 closed the metadata gate through the established exact
official-schema fallback.

This step:

1. captured two equal read-only installation baselines;
2. executed exactly one supported Module Control update;
3. verified the installed target commit twice;
4. proved instance, configuration, variable, archive, command and topology
   continuity;
5. verified a stopped, credential-free MQTT lifecycle twice;
6. stopped without inactive staging, activation or restart.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update auf die MQTT-Core-Resume-Deadline-Härtung mit deaktiviertem MQTT freigegeben.
```

This authorized one `MC_UpdateModule()` call and bounded read-only probes.

It did not authorize:

- `MC_ReloadModule()`;
- MQTT activation or credential retrieval;
- broker connection or MQTT publication;
- inactive staging or Core mutation;
- a Symcon service restart;
- mower commands.

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
commit: 45c7bd50
clean:  true
valid:  true
```

The two runs were equal except for their capture timestamps. They agreed on:

- one retained Account, Configurator, Device and Receiver;
- the retained MQTT and WebSocket parent chain;
- equal instance and configuration projections;
- 14 expected variables with stable identities and metadata;
- five expected Archive Control logging contracts;
- queryable logged history;
- stable command evidence;
- four exact QoS-0 subscriptions without wildcard;
- connected Account authentication without reauthentication;
- recent successful REST and Device polling evidence;
- disabled MQTT and inactive WebSocket;
- empty Authorization headers, MQTT username and MQTT password;
- lifecycle `Disabled`, `nextAttemptAt=0` and stopped Core observation state.

## 4. Supported Module Control Update

Exactly one supported operation ran:

```text
MC_UpdateModule(ModuleControl, "symcon-navimow"): 1
MC_ReloadModule():                                0
```

The operation returned `true` and reported:

```text
before: main@45c7bd50, clean and valid
after:  main@8fdab84b, clean and valid
```

The installed abbreviation is an exact prefix of the published commit:

```text
8fdab84bd2a2190a6025cedd11f1ae6248369c0e
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
commit:            8fdab84b
clean:             true
valid:             true
```

Every compatibility projection remained equal before and after the update.

| Contract | Result |
|---|---|
| productive instance identities and connections | unchanged |
| complete retained configurations | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Receiver pairing | retained |
| canonical subscriptions | 4/4 QoS 0 |
| Account authentication | connected |
| reauthentication required | false |
| REST continuity | operational |
| MQTT feature | disabled |
| WebSocket | inactive |
| Authorization headers | empty |
| MQTT username and password | empty |

The user's variable identities, logging configuration and accumulated archive
history remain intact.

## 6. Disabled Lifecycle Verification

The diagnostics were equal in both pre-update and both post-update
projections:

```text
feature enabled:                   false
configuration status:              disabled
lifecycle:                         Disabled
next attempt:                      0
reconnect attempt:                 0
Core observation count:            0
Core observation deadline:         0
connection attempts:               14
connection successes:              6
connection failures:               0
Core-resume observations:           1
last connection trigger:           initial
```

The last MQTT connection attempt occurred at:

```text
2026-07-29T09:44:27Z
```

The supported update ran at:

```text
2026-07-29T11:53:44Z
```

The last attempt predates the update by more than two hours, and all counters
and timestamps remained unchanged. The update therefore caused no MQTT
connection attempt.

The latest successful regular REST and Device status evidence before the
update was:

```text
2026-07-29T11:50:18Z
```

No manual REST request was manufactured for this gate.

## 7. Architecture Decisions

### AD-NAV-723: Accept only the one supported update

**Decision:** Execute one `MC_UpdateModule()` and establish the target through
the mutation result plus two independent post-update projections.

**Reason:** The successful exact commit transition is unambiguous and does not
justify a reload or retry.

### AD-NAV-724: Treat all retained contracts as update invariants

**Decision:** Require instance, complete configuration, variable, archive and
command projections to remain equal.

**Reason:** The productive delta changes only the bounded observation horizon;
it must not alter retained user or transport state.

### AD-NAV-725: Prove inactivity through configuration and diagnostics

**Decision:** Require disabled configuration, empty credential fields,
inactive WebSocket, stopped lifecycle and unchanged connection counters.

**Reason:** These independent signals distinguish source installation from
transport activation.

### AD-NAV-726: Keep inactive staging separate

**Decision:** End Gate C immediately after the disabled installation proof.

**Reason:** Gate D requires a separately authorized observation interval and
must not be inferred from a successful update.

## 8. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| read-only pre-update projections | 2 |
| read-only post-update projections | 2 |
| MQTT enable operations | 0 |
| MQTT connection attempts after update | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| service restarts | 0 |
| created, deleted or reparented objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 9. Evidence Closure Validation

After the live gate:

```text
focused Navimow MQTT gate:  PASS
repository composer check:  PASS
private evidence JSON:      PASS
private probe PHP syntax:   PASS
public diff whitespace:     PASS
public privacy scan:        PASS
```

The complete repository check included lint, generated-artifact checks,
executable regressions, PHPStan and PHPCS.

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-deadline-hardening-symcon-update/
    gate-c-evidence-closure.json
```

Reusable private sources:

```text
private/navimow-capture/
  native-mqtt-deadline-hardening-update-readonly.php
  native-mqtt-deadline-hardening-update-once.php
```

No credential, endpoint, topic, payload, personal ObjectID or installation
detail is present in this public report.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| Gate B metadata conformance | PASS |
| Gate C disabled Symcon update | PASS |
| Gate C compatibility | PASS |
| Gate D inactive staging | CLOSED |
| renewed persistence acceptance | NOT GIVEN |
| temporary activation | CLOSED |
| service restart | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

**Gate C is complete.**

## 12. Recommended Next Step

After separate authorization, execute only Gate D from step 201:

```text
Inaktives Staging der MQTT-Core-Resume-Deadline-Härtung freigegeben.
```

Gate D is read-only when the retained topology remains valid. It must not
retrieve credentials, activate MQTT, connect to the broker or restart Symcon.
