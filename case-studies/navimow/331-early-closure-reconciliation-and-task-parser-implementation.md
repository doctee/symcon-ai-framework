# 331 Early Closure Reconciliation And Task Parser Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Local candidate implemented and fully verified

**Date:** 2026-08-20

## 1. Purpose

This step fixes the stale pilot registry identified in step 330 and extends
the receive-only MQTT parser with the bounded task, area-candidate and delay
fields established by the private structure analysis.

The implementation remains diagnostic-only. It creates no public variable,
changes no Archive identity and does not alter REST state authority or any
mower command.

## 2. Early-Disable Closure

Disabling MQTT while a pilot registry is `Active` now requests the existing
credential-first closure state machine with the explicit reason
`operator-disabled`.

```text
Active
  -> ClosureRequested
  -> CredentialsCleared
  -> PropertiesDisabled
  -> Closed
```

The same transition also reconciles the live defect signature from step 330:

```text
feature disabled
pilot active false
stopped timestamp present
closure state Active
```

No private probe writes the registry. The module remains the sole owner of
the state transition and retains its bounded retry behavior if credential
cleanup cannot be proven.

## 3. Parser Extension

The location parser now recognizes only these additional evidence-backed
fields:

```text
action
subAction
mowStartType
currentMowProgress
mowingPercentage
subtotalArea
mowingWeekArea
currentMowBoundary
partitionIds
taskDelay
mapWorkPosition
```

Every value is type-checked and bounded. Partition lists are non-empty and
limited to 64 entries. Progress is bounded to hundredths of 100 percent and
area candidates remain unit-neutral bounded numbers.

`mapWorkPosition` must match the observed fixed hexadecimal shape but is not
persisted because no semantic decoder exists.

## 4. Timestamp Contract

Normal task and partition records continue to use their manufacturer source
timestamp and the existing out-of-order rejection.

The observed `taskDelay` message has no source timestamp. It is therefore
accepted only as a boolean receipt-timestamped task diagnostic:

- local receipt time is retained as `taskTelemetryReceivedAt`;
- the previous manufacturer `lastSourceTimestamp` is not advanced; and
- unrelated timestamp-less location payloads remain rejected.

This preserves timestamp provenance instead of manufacturing a source time.

## 5. Identity And Privacy

Raw boundary and partition identifiers exist only in the transient parsed
patch. Before accumulation, the Account module replaces them with
device-bound SHA-256 correlation keys and a bounded partition count.

The persistent shadow and public diagnostic projection contain neither raw
identifier values nor the opaque work-position string. Absolute coordinates
remain confined to the separately controlled position diagnostic.

## 6. Diagnostic Projection

`GetMqttDiagnostics()` adds nullable receive-only hints for:

```text
action and subAction codes
mow start type
task progress
task and weekly area candidates
task delay
task telemetry receipt time and age
boundary correlation key
partition-list correlation key and count
```

The keys support private cross-run comparison without claiming that boundary
and partition identifiers share a namespace. Fields remain nullable because
MQTT messages are sparse partial updates.

## 7. Verification

Focused regression coverage proves:

- active operator disable requests and completes exactly one owned closure;
- the step-330 inactive stale-`Active` registry migrates to `Closed`;
- task progress and area candidates are accumulated without public-variable
  churn;
- timestamp-less `taskDelay` is receipt-timestamped without changing source
  time;
- boundary and partition values are replaced with stable correlation hashes;
- the opaque work-position value is validated and discarded;
- malformed task booleans and empty partition lists fail closed; and
- REST authentication, commands and the existing pilot harness remain green.

The complete repository check passed using the canonical dependency toolset:

```text
syntax:       PASS
SAEF tests:   PASS
Navimow:      PASS
PHPStan:      PASS
PHPCS:        PASS
```

The isolated worktree intentionally has no local `vendor/`. The initial
`make check` therefore stopped only when the Open-Meteo wrapper referenced
that absent path. Its remaining PHPStan and PHPCS gates and the complete
`composer check` were then executed against the same worktree with the
canonical root toolset and passed.

## 8. Preserved Contracts

```text
REST state authority:            unchanged
MQTT direction:                  receive-only
MQTT publish and command paths:  absent
public variables:                unchanged
Archive logging identities:      unchanged
position storage boundary:       unchanged
automatic closure policy:        retained
```

## 9. Architecture Decisions

### AD-NAV-1357: Reuse the existing closure state machine

**Decision:** Route operator disable and stale-state migration through the
existing credential-first closure sequence.

**Rationale:** One owner and one cleanup path are safer than a second direct
registry repair mechanism.

### AD-NAV-1358: Receipt-timestamp only the proven delay shape

**Decision:** Permit missing source time only for a validated boolean
`taskDelay` patch.

**Rationale:** The observed payload is useful, but assigning local receipt
time as manufacturer time would corrupt ordering semantics.

### AD-NAV-1359: Persist correlation keys, not area identifiers

**Decision:** Hash boundary and partition identities with the device key
before they enter the accumulated shadow.

**Rationale:** Cross-run equality is needed for area mapping; raw private
identifiers are not needed by the diagnostic contract.

### AD-NAV-1360: Discard opaque work-position data

**Decision:** Validate but do not retain `mapWorkPosition`.

**Rationale:** Its 128-character hexadecimal shape is proven, but its meaning
is not. Retention would increase private state without an interpretable use.

## 10. Gate State And Next Step

| Gate | Status |
|---|---|
| clean current-main worktree | PASS |
| local implementation | PASS |
| focused regression tests | PASS |
| complete SAEF check | PASS |
| public-variable and Archive stability | PASS OFFLINE |
| publication | PENDING |
| disabled Symcon rollout | PENDING |
| live stale-state reconciliation | PENDING |
| bounded receive-only evidence pilot | PENDING |

The candidate is ready for a publication-readiness freeze. Publication to
SAEF and the standalone module, disabled Symcon rollout, live reconciliation
and later receive-only activation remain separately evidenced operations.
