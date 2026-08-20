# 330 Wet-Delay Pilot Early Cleanup And Closure Defect

**Case study:** Navimow native IP-Symcon module

**Status:** Credential cleanup passed; pilot closure bookkeeping failed

**Date:** 2026-08-20

## 1. Purpose

This step ends pilot session 7 before its automatic deadline so that the
receive-only parser can be extended without modifying an active observation
runtime. It verifies credential removal immediately and after a delay and
separately evaluates the retained pilot lifecycle metadata.

## 2. Authorization Boundary

The user explicitly authorized exactly one early cleanup with mandatory
credential removal plus immediate and delayed read-only verification.

No mower command, restart, OAuth action, module update, publication,
activation retry or second cleanup was authorized or performed.

## 3. Pre-Cleanup State

The read-only preflight observed the exact installed standalone commit
`405fd24b5450c909c35e038a12bd69378d33deb6` with clean repository metadata and
unchanged structure, pilot and command contracts.

```text
pilot session:          7
pilot active:           true
lifecycle:              ShadowActive
closure state:          Active
MQTT / WebSocket:       102 / 102
WebSocket active:       true
Core credentials:       present
REST operational:       true
REST authority:         authoritative
MQTT authority:         receive-only diagnostic
```

All accepted MCP results separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 4. Single Cleanup

The established private cleanup probe disabled MQTT shadow and position
diagnostics and performed exactly one Account `ApplyChanges`.

```text
cleanup attempts:    1
ApplyChanges calls:  1
cleanup retries:     0
```

Its synchronous result confirmed inactive transport and removal of the
Authorization header, MQTT username and MQTT password. The configured
receiver selection was retained.

## 5. Immediate And Delayed Verification

The immediate readback and a second readback 163 seconds later both proved a
stable disabled and credential-free state:

```text
MQTT / WebSocket:       104 / 104
WebSocket active:       false
Authorization present: false
MQTT username present: false
MQTT password present: false
position diagnostics:  disabled
REST operational:      true
pilot active:          false
next checkpoint:       none
```

The security and transport cleanup therefore passed.

## 6. Closure Bookkeeping Defect

Both readbacks also exposed an independent lifecycle inconsistency:

```text
pilot active:         false
stopped timestamp:    present
closure state:        Active
closure reason:       empty
closure completed:    absent
```

The cause is deterministic. `ApplyChanges()` stops observation when MQTT is
disabled, but `reconcileMqttPilotAutomaticClosure()` only completes states
that already passed through `ClosureRequested`, `CredentialsCleared` or
`PropertiesDisabled`. A direct operator disable from `Active` therefore
removes credentials and stops the observation without closing the registry.

The stale `Active` closure state is not a credential exposure and does not
leave transport running. It is nevertheless a product defect because the
next bounded activation requires the previous session to be closed.

## 7. Evidence Retention

One bounded position sample was retained before cleanup, which proves ingress
but is insufficient for path, area or wet-delay interpretation. Coordinates,
private area labels, schedules, topics, device identities, ObjectIDs and raw
payloads remain private.

Reduced evidence is retained under:

```text
private/navimow-capture/output/multi-area-task-semantics/
```

## 8. Architecture Decisions

### AD-NAV-1353: Accept security cleanup independently

**Decision:** Mark credential and transport cleanup as passed.

**Rationale:** Immediate and delayed readback independently prove disabled
Core instances, absent credentials and operational REST.

### AD-NAV-1354: Fail lifecycle closure independently

**Decision:** Do not classify the pilot registry as closed.

**Rationale:** `closureState=Active` contradicts `active=false` and the
present stop timestamp and blocks the next activation contract.

### AD-NAV-1355: Do not repair private registry state externally

**Decision:** Do not write module attributes from a private probe.

**Rationale:** The module owns its lifecycle registry. A code-level,
idempotent reconciliation is auditable and upgrade-safe; external attribute
mutation is not.

### AD-NAV-1356: Extend parsing only after closure correction

**Decision:** Keep the next parser extension offline until the lifecycle fix
has passed tests and a disabled rollout.

**Rationale:** The stopped pilot no longer blocks source work, while the stale
registry must not be hidden by a new live activation.

## 9. Gate State And Next Step

| Gate | Status |
|---|---|
| single authorized cleanup | PASS |
| immediate credential cleanup | PASS |
| delayed credential-free stability | PASS |
| REST availability | PASS |
| lifecycle registry closure | FAIL |
| direct registry repair | NOT PERFORMED |
| module update or publication | NOT AUTHORIZED |

The next SAEF step should implement and offline-test an idempotent
early-disable reconciliation from `Active` to `Closed`, preserving retained
diagnostics and recording an explicit manual-disable reason. The additive
task/area parser extension can be developed in the same isolated workstream,
but publication, disabled Symcon rollout and any later live pilot remain
separate gates.
