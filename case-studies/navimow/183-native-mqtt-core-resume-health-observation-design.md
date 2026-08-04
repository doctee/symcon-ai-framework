# 183 Native MQTT Core Resume Health Observation Design

**Case study:** Navimow native IP-Symcon module
**Status:** Offline design complete; implementation and publication closed
**Date:** 2026-07-29
**Scope:** Replace the one-shot post-ready Core-health decision with a bounded
observation contract

## 1. Purpose

Step 182 established that the durable restart barrier works, while one strict
Core-health snapshot at `IPS_KERNELSTARTED + 15 s` is not sufficient to
distinguish delayed native readiness from a failed retained connection.

This step freezes:

1. the observation schedule and deadline;
2. lifecycle and idempotency semantics;
3. immediate negative gates;
4. privacy-safe pre-cleanup diagnostics;
5. the synthetic regression matrix;
6. implementation, publication and renewed live-test gates.

It changes no productive PHP and performs no live operation.

## 2. Design Boundary

The design applies only when all of these facts are true:

```text
MQTT feature enabled
owned native MQTT topology retained
new kernel epoch detected
IPS_KERNELSTARTED observed
Account configuration valid
Account access token usable
ownership and subscription contract valid
native transport not in the exact inactive credential-free shape
strict Core health not yet established
```

It does not change:

- REST as the authoritative state source;
- receive-only MQTT scope;
- MQTT publish prohibition;
- normal initial connection and reconnect limits;
- OAuth or MQTT credential retrieval;
- device variables, actions or archive contracts;
- cleanup ownership boundaries.

## 3. Core-Health Predicate

Successful native Core resume continues to require:

```text
MQTT instance status == 102
AND WebSocket instance status == 102
AND WebSocket configuration Active == true
```

Ingress counters are diagnostic evidence only. A message must not replace the
strict health predicate because its arrival time may precede the current
projection or represent a transport transition.

## 4. Observation Schedule

The observation window uses absolute offsets from
`kernelStartObservedAt`:

| Ordinal | Absolute offset | Purpose |
|---:|---:|---|
| 1 | +15 s | preserve the existing initial post-ready grace |
| 2 | +30 s | catch ordinary delayed native activation |
| 3 | +60 s | cover slower network and parent/child startup |
| 4 | +90 s | final bounded decision |

The deadline is:

```text
kernelCoreObservationDeadlineAt =
    kernelStartObservedAt + 90
```

These are absolute offsets, not cumulative sleeps. The maximum observation
window remains 90 seconds even when a timer fires late.

## 5. Timer Semantics

At each due timer:

1. acquire the existing MQTT lifecycle lock;
2. re-evaluate all immediate gates;
3. capture exactly one Core-health projection;
4. adopt immediately when healthy;
5. otherwise schedule the next future absolute offset;
6. execute final recovery only when the deadline is reached.

If execution is delayed past one or more offsets, the Account must not replay
missed observations in a burst. It captures one current projection and
schedules the next future offset or performs the final decision when already
at or past the deadline.

Examples:

```text
timer fires at +47 s -> capture once, next due at +60 s
timer fires at +72 s -> capture once, next due at +90 s
timer fires at +96 s -> capture once, perform final decision
```

No blocking wait, loop with `sleep()`, cyclic sub-second timer or unbounded
retry is permitted.

## 6. Lifecycle Model

Add one explicit lifecycle state:

```text
CoreResumeObserving
```

Use the existing scheduled kind:

```text
kernel-reconcile
```

The pending projection is:

```text
state:                CoreResumeObserving
transition reason:    core-readiness-pending
Core classification: pending-with-credentials
nextAttemptAt:        next absolute observation time
```

The state is entered only after the first due projection is unhealthy while
all structural gates remain valid and credential fields are present.

Before the first projection, the existing durable states remain:

```text
kernel-start-awaiting-ready
kernel-start-observed
```

## 7. Positive Adoption

At any observation, strict Core health immediately completes reconciliation:

```text
classification:          healthy
lifecycle:               ShadowActive
transition reason:       core-resumed
kernel reconciled at:    current timestamp
next attempt:            none
Core-resume observations: +1 exactly once
```

The Account must not:

- retrieve MQTT credentials;
- call explicit MQTT Connect;
- apply either native Core configuration;
- increment Account connection attempt, success or failure counters.

Any remaining observation timer is cancelled.

## 8. Pending Observation

When strict health is false before the deadline and the transport is not in
the exact inactive credential-free shape:

```text
classification:       pending-with-credentials
lifecycle:            CoreResumeObserving
transition reason:    core-readiness-pending
unexpectedDisconnects: unchanged
reconnectAttempts:     unchanged
Core configuration:   unchanged
```

Status `104`, status `>=200` or `Active=false` is recorded but does not by
itself trigger destructive recovery before the deadline. The window is short,
finite and applies only to a previously owned credential-bearing restart
chain.

This avoids interpreting a transient native error code without its complete
startup context while still guaranteeing cleanup at 90 seconds.

## 9. Final Unhealthy Decision

If strict health is still false at or after the deadline:

```text
classification:       unhealthy-with-credentials
transition reason:    core-disconnected
unexpectedDisconnects: +1 exactly once
```

Before changing the Core, persist the final privacy-safe projection. Then:

1. clear pending credential-rotation state;
2. execute the existing owned transport cleanup;
3. mark the kernel epoch reconciled;
4. schedule the existing bounded reconnect path;
5. preserve its first 60-second reconnect delay.

Repeated timer execution after this transition must not repeat cleanup or
increment counters.

## 10. Immediate Gates

The observation window tolerates only native transport readiness. It does not
defer safety or structural failures.

### 10.1 Feature disabled

At any point:

```text
EnableMqttShadow == false
  -> cancel observation
  -> normal owned cleanup
  -> Disabled
  -> no reconnect
```

Disable remains the highest-priority gate.

### 10.2 Invalid Account configuration

```text
configuration invalid
  -> cancel observation
  -> safe owned cleanup when ownership is still verifiable
  -> ConfigurationError
  -> no reconnect attempt
```

### 10.3 Unusable Account authentication

```text
access token unusable
  -> cancel observation
  -> owned credential cleanup
  -> WaitingForAuthentication or ReauthenticationRequired
  -> no MQTT credential request
```

### 10.4 Ownership or topology invalid

```text
ownership invalid
  -> cancel observation
  -> do not mutate an unverified Core
  -> ConfigurationError
  -> record credential-cleanup-skipped when applicable
```

### 10.5 Credential-free Core

When the WebSocket is inactive and all owned credential fields are empty:

```text
classification: credential-free
  -> end Core-resume observation
  -> mark epoch reconciled
  -> schedule existing kernel-fallback initial connection
```

This is not an unhealthy retained-Core decision and must not increment
`unexpectedDisconnects`.

## 11. Bounded Diagnostics

Persist a maximum of four observation entries inside the existing MQTT
lifecycle metadata. Do not introduce a new public helper or storage system.

Each entry contains only:

```text
ordinal
observedAt
offsetSeconds
mqttStatus
webSocketStatus
webSocketActive
authorizationPresent
mqttUsernamePresent
mqttPasswordPresent
lastReceivedAt
healthy
```

Additional lifecycle fields:

```text
kernelCoreObservationCount
kernelCoreObservationDeadlineAt
lastKernelCoreFailedPredicates
```

Allowed failed-predicate labels:

```text
mqtt-status
websocket-status
websocket-inactive
```

The list is deterministic and contains no native error text.

The history is reset only when:

- a different kernel epoch is accepted;
- MQTT is explicitly disabled and cleanup completes;
- a new normal initial connection episode starts.

It remains available after adoption or final recovery so the decisive
startup evidence is not destroyed by cleanup.

## 12. Privacy Contract

Diagnostics and fixtures must not contain:

- Authorization values;
- MQTT username or password;
- endpoint or hostname;
- private topic;
- payload;
- device identity;
- installation ObjectID;
- local IP address;
- garden or location data.

Credential presence is represented only as Boolean values. `lastReceivedAt`
is an Account-owned timestamp, not a payload or source timestamp.

## 13. Idempotency and Concurrency

The existing lifecycle semaphore remains mandatory.

Required invariants:

- duplicate `IPS_KERNELSTARTED` for the same epoch does not reset the deadline;
- duplicate `ApplyChanges()` does not clear pending observations;
- duplicate timer invocation at one timestamp captures at most one entry;
- a new kernel epoch replaces the old pending observation window;
- stale timers cannot reconcile an older epoch;
- adoption occurs at most once per epoch;
- final cleanup occurs at most once per epoch;
- `unexpectedDisconnects` increments at most once per failed epoch;
- explicit disable wins over every pending observation;
- no Account credential operation overlaps the observation window.

## 14. Synthetic Fixture Plan

Add:

```text
fixtures/mqtt/core-resume-bounded-health-observation.json
```

The fixture must be synthetic and contain cases for:

| Case | Core timeline | Expected result |
|---|---|---|
| healthy-first | healthy at +15 | adopt at +15 |
| delayed-30 | inactive at +15, healthy at +30 | adopt at +30 |
| delayed-60 | inactive at +15/+30, healthy at +60 | adopt at +60 |
| delayed-90 | inactive through +60, healthy at +90 | adopt at +90 |
| never-ready | inactive through +90 | one cleanup and reconnect |
| late-timer | first execution at +47 | one observation, next +60 |
| past-deadline | first execution after +90 | one final observation and recovery |
| message-duplicate | duplicate ready message | unchanged deadline and history |
| apply-duplicate | repeated `ApplyChanges()` | unchanged deadline and history |
| timer-duplicate | repeated timer at same time | one observation |
| disable-wins | disable during pending window | immediate cleanup, no reconnect |
| auth-loss | token unusable during window | immediate auth suspension |
| credential-free | fields empty during window | kernel fallback, no disconnect count |
| ownership-drift | ownership invalid during window | no unverified Core mutation |
| ingress-only | counter advances while Core unhealthy | remain pending |
| new-epoch | epoch changes during pending window | reset to the new bounded window |

All timelines use a fake clock. No real delay or network access is permitted.

## 15. Executable Test Requirements

Extend `tests/mqtt-transport-lifecycle.php` to prove:

1. exact absolute scheduling;
2. successful adoption at every allowed offset;
3. zero Core mutation before the final deadline;
4. zero Account connection operations during observation;
5. exactly one final cleanup and reconnect schedule;
6. immediate negative-gate behavior;
7. bounded diagnostic history;
8. idempotency and stale-timer rejection;
9. unchanged REST, Receiver and subscription contracts.

The old unhealthy-Core assertion must change from:

```text
inactive at +15 -> immediate cleanup
```

to:

```text
inactive at +15 -> CoreResumeObserving
inactive at +90 -> exactly one cleanup
```

The step-181 live fixture remains historical evidence and must not be rewritten
to claim the future behavior occurred live.

## 16. Implementation Boundary

The implementation step may change only:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
case-studies/navimow/tests/mqtt-fixtures.php
case-studies/navimow/fixtures/mqtt/README.md
case-studies/navimow/fixtures/mqtt/
case-studies/navimow/README.md
the implementation report
```

Additional files require a documented necessity. No form, locale, Device,
Receiver, REST client, payload parser or variable-contract change is expected.

## 17. Offline Validation Gate

Before publication:

```text
php syntax checks
MQTT fixture checks
MQTT transport lifecycle checks
MQTT Account ingestion checks
MQTT Receiver checks
MQTT parser and reconciliation checks
REST client and authentication checks
distribution structure validation
Symcon Module Validator
complete Navimow MQTT shadow gate
```

The implementation must first demonstrate a red delayed-readiness regression
against the current behavior, then pass after the correction.

## 18. Publication and Live-Test Gates

Offline success does not authorize publication.

Required later sequence:

1. implementation report and exact file/hash inventory;
2. separate publication plan;
3. standalone repository publication;
4. remote hash verification;
5. Symcon update while MQTT remains disabled;
6. inactive topology and credential-free compatibility check;
7. renewed verbatim credential-persistence acceptance;
8. temporary receive-only activation;
9. two equal active baselines including `lastReceivedAt`;
10. one externally performed supervised restart;
11. bounded observation timeline and decisive result;
12. mandatory disable and delayed credential-free cleanup verification.

The live procedure must record the restart boundary closely enough to classify
any receive-counter delta relative to the restart. It must never infer timing
from aggregate counters alone.

## 19. Architecture Decisions

### AD-NAV-640: Use a 90-second absolute observation window

**Decision:** Observe at `+15/+30/+60/+90 s` from
`kernelStartObservedAt`.

**Reason:** This retains the existing grace, covers progressively slower
native readiness and remains strictly bounded.

### AD-NAV-641: Preserve the Core before the deadline

**Decision:** A structurally valid credential-bearing Core is read only while
`CoreResumeObserving`.

**Reason:** Destructive cleanup at the first transient negative projection
caused the step-181 diagnostic and recovery ambiguity.

### AD-NAV-642: Keep strict health and immediate structural gates

**Decision:** Delayed readiness does not weaken the `102/102/Active` success
predicate or configuration, authentication, ownership and disable gates.

**Reason:** Readiness tolerance must not become implicit connection success or
unsafe ownership recovery.

### AD-NAV-643: Persist a four-entry privacy-safe timeline

**Decision:** Store bounded per-predicate projections before cleanup.

**Reason:** The timeline distinguishes delayed readiness from a never-ready
Core without exposing credentials or payloads.

### AD-NAV-644: Use absolute offsets and skip missed observations

**Decision:** Never accumulate timer delay or replay missed intervals.

**Reason:** Absolute scheduling preserves the 90-second bound and avoids
operation bursts after a blocked ScriptEngine.

### AD-NAV-645: Keep historical live evidence immutable in meaning

**Decision:** The future synthetic fixture supplements rather than replaces the
step-181 live signature.

**Reason:** A corrected implementation does not retroactively change what the
published version did during the live restart.

## 20. Gate Decision

| Gate | Decision |
|---|---|
| bounded observation schedule | FROZEN |
| strict Core-health predicate | RETAIN |
| immediate structural gates | RETAIN |
| diagnostic privacy contract | FROZEN |
| productive PHP implementation | CLOSED |
| publication | CLOSED |
| Symcon update | CLOSED |
| further restart | CLOSED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 21. Recommended Next Step

Proceed offline with:

```text
184-native-mqtt-core-resume-health-observation-implementation.md
```

That step should add the synthetic fixture and red regression first, implement
the bounded observation state machine, pass the complete Navimow gate and stop
before publication or live activity.
