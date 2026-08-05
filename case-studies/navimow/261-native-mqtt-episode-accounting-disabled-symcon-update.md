# 261 Native MQTT Episode Accounting Disabled Symcon Update

**Case study:** Navimow native IP-Symcon module

**Status:** Stopped before mutation because the Account instance did not meet
the established healthy-status precondition

**Date:** 2026-08-04

**Scope:** Attempt Gate C for published commit
`a8481c9781be603f7c6430b78625a2a4b0188de8` while MQTT remains disabled

## 1. Result

The separately authorized disabled module-update gate did not reach its
mutation. Three bounded read-only preflight observations found the installed
Account instance at status `101`, while the accepted precondition and prior
Navimow update evidence require status `102`.

```text
installed commit:        main@79686e52
target commit:           main@a8481c97
repository:              clean and valid
Account status:          101 / 101 / 101
expected Account status: 102
kernel runlevel:         10103
MQTT feature:            disabled
MQTT/WebSocket:          104 / 104
native credentials:      absent
REST:                    connected and operational
MC_UpdateModule():       0
MC_ReloadModule():       0
decision:                STOPPED BEFORE MUTATION
```

The published target remains valid and available. It is not claimed as
installed.

## 2. Authorization Boundary

The user explicitly authorized:

```text
Symcon-Update auf die MQTT-Episodenzählung und Pilotzusammenfassung mit
deaktiviertem MQTT freigegeben.
```

The authorization permitted one supported update only after all Gate-C
preconditions passed. It did not authorize `ApplyChanges()`, a module reload,
a service restart, MQTT activation, credential retrieval or a mower command.

## 3. Structured MCP Contract

All three probes used the bounded structured Symcon MCP channel. Each result
was independently checked for:

```text
transportError: null
executionError: null
truncated:      false
```

The failed precondition is therefore a valid live observation, not an MCP or
PHP execution ambiguity.

## 4. Stable Safety Signals

The initial complete transitional preflight proved:

- installed repository `main@79686e52`, clean and valid;
- Configurator, Device and Receiver status `102`;
- MQTT and WebSocket status `104`;
- feature disabled, lifecycle `Disabled` and no pending reconnect;
- WebSocket Authorization absent;
- MQTT username and password absent;
- exact four-topic, QoS-0, wildcard-free subscription contract;
- all 14 public variable contracts present;
- all five user-enabled archive contracts present and queryable;
- REST connected and operational;
- reauthentication not required and token usable.

These signals remained compatible in the confirmation probes. No MQTT or REST
safety stop appeared.

## 5. Blocking Signal

The Account status was observed at `101`:

| Observation | Delay | Account | Result |
|---|---:|---:|---|
| initial transitional preflight | 0 s | `101` | STOP |
| independent confirmation | 15 s | `101` | STOP |
| delayed confirmation | 75 s | `101` | STOP |

The final observation also proved kernel runlevel `10103`. Thus the deviation
cannot be accepted merely as an unfinished kernel startup. Historical step-244
evidence recorded the same installed Account at status `102` before and after
its update.

The source does not intentionally call `SetStatus(101)`. No causal claim is
made without a separately authorized recovery analysis.

## 6. Stop-Condition Enforcement

Step 252 requires all four productive Navimow instances to be present and
compatible before the single update. Status `101` violates this prerequisite.

Accordingly, this step did not call:

```text
MC_UpdateModule()
MC_ReloadModule()
IPS_ApplyChanges()
```

It also did not improvise a rollback or use the module update as a repair
mechanism. The current installed commit remains unchanged.

## 7. Preserved Contracts

```text
REST authority:             preserved
MQTT direction:            receive-only contract preserved
MQTT activation:           none
native MQTT credentials:   absent
public variables:          14, preserved
archive logging contracts: 5, preserved and queryable
mower commands:            none
```

## 8. Private Evidence

Machine-readable evidence is retained privately below:

```text
private/navimow-capture/output/
  native-mqtt-episode-accounting-disabled-symcon-update/
```

The public report contains no ObjectID, credential, topic, payload, hostname
or device identity.

## 9. Architecture Decisions

### AD-NAV-1031: Treat status 101 as a failed update precondition

The accepted update baseline is status `102`. A healthy REST projection does
not override the module-instance compatibility gate.

### AD-NAV-1032: Distinguish observation stability from health

Three equal observations prove that `101` is stable over the bounded interval;
they do not redefine it as healthy.

### AD-NAV-1033: Do not use an update as implicit recovery

`MC_UpdateModule()` could trigger lifecycle work, but that is not a controlled
diagnosis of the unexpected status. The mutation remains blocked.

### AD-NAV-1034: Keep recovery separate from publication and activation

The published target and metadata gates remain passed. Account-status recovery
requires its own read-only analysis and, if needed, a separately authorized
minimal mutation.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| publication | PASS |
| metadata conformance | PASS |
| structured MCP transport | PASS |
| MQTT disabled and credential-free | PASS |
| REST operational | PASS |
| variable and archive contracts | PASS |
| Account status precondition | FAIL (`101`) |
| supported module update | NOT PERFORMED |
| disabled Symcon update | STOPPED BEFORE MUTATION |
| MQTT staging or activation | CLOSED |
| service restart | NOT PERFORMED |
| mower command | NOT PERFORMED |

## 11. Next Step

Proceed with:

```text
262-navimow-account-status-101-readonly-analysis.md
```

That step should inspect the current Account lifecycle, timers, messages and
diagnostic state through bounded read-only MCP probes and determine whether
status `101` has a non-mutating explanation. It must not call
`IPS_ApplyChanges()`, update or reload the module, restart Symcon, activate
MQTT, retrieve credentials or send a mower command.
