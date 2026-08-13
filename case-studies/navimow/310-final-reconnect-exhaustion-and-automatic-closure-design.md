# 310 Final Reconnect Exhaustion And Automatic Closure Design

**Case study:** Navimow native IP-Symcon module

**Status:** Root-cause boundary refined; automatic pilot closure is design-ready;
long-interval transport recovery remains blocked pending stronger evidence

**Date:** 2026-08-13

## 1. Purpose

Step 309 closed the corrected position-accounting observation with useful
position evidence, repeated transport episodes, late cleanup and one final
exhausted reconnect sequence.

This step:

1. reconstructs the current bounded recovery state machine;
2. places the final exhaustion on the correct side of the hard deadline;
3. classifies supported and unsupported root-cause hypotheses;
4. designs module-owned automatic stop and cleanup behavior; and
5. defines safety invariants for a possible future outer recovery state without
   selecting or implementing a retry cadence.

It performs no productive PHP change, publication, Symcon access, MQTT
activation, credential retrieval, OAuth action, restart or mower command.

## 2. Current Recovery Contract

The installed lifecycle uses one finite retry episode for transport-class
failures:

```text
unexpected unhealthy Core observation
  -> disconnect owned transport and remove credentials
  -> attempt 1 after 60 seconds
  -> attempt 2 after 300 seconds
  -> attempt 3 after 900 seconds
  -> Disconnected / reconnect-exhausted
```

The fourth attempt is prohibited. Each retry revalidates configuration,
ownership and authentication and retrieves fresh transport credentials only
for that attempt.

Authentication and configuration failures do not enter this sequence:

| Failure class | Terminal lifecycle | Transport retry |
|---|---|---|
| authentication | `ReauthenticationRequired` | prohibited |
| missing usable authentication | `WaitingForAuthentication` | prohibited |
| ownership or configuration | `ConfigurationError` | prohibited |
| unhealthy MQTT/WebSocket Core | bounded reconnect episode | permitted |

After 15 continuously healthy minutes, the episode-local attempt counter resets
to zero. Missing mower messages while the Core remains healthy do not trigger
broker reconnect; REST polling continues independently.

## 3. Correct Timeline

```text
pilot clock start:       2026-08-09 11:09:41 UTC
hard deadline:           2026-08-12 11:09:41 UTC
final episode detected:  2026-08-13 03:16:58 UTC
cleanup:                 2026-08-13 03:29:10 UTC
```

The final exhausted episode began:

```text
58037 seconds after the hard deadline
16 hours, 7 minutes and 17 seconds after the hard deadline
```

It is therefore post-deadline forensic evidence. It is not an exhausted
recovery inside the approved 72-hour window.

The pilot had already failed earlier for a different reason. Episode sequence
advanced from 25 at activation to 41 at the 48-hour checkpoint. Sixteen distinct
episodes were therefore observed before early completion was eligible. Policy
required stop and cleanup on the second episode.

```text
transport stability gate:       failed inside approved window
automatic second-episode stop:  failed
automatic hard-deadline stop:   failed
final reconnect exhaustion:     post-deadline evidence
```

## 4. Final Episode Reconstruction

The retained bounded projection for the final episode reports:

| Evidence | Value |
|---|---:|
| MQTT Core status at detection | `200` |
| WebSocket Core status at detection | `200` |
| reconnect attempts used | 3 |
| MQTT ingress age at detection | 1123 seconds |
| REST success age at detection | 192 seconds |
| nearest preceding credential rotation | 1987 seconds |
| connection-failure counter delta for session | 0 |
| reauthentication required | false |
| open episode after exhaustion | none |

Each reconnect attempt could configure the owned transport without throwing a
classified authentication or configuration exception, but the native Core did
not return to sustained `102/102` health. Exhaustion then stopped the lifecycle
timer, removed credentials and retained REST operation.

The zero `connectionFailures` count does not prove that the remote WSS service
accepted a durable session. It proves only that the module-level connection
candidate did not fail through its synchronous exception classifications.

## 5. Root-Cause Boundary

### Supported exclusions

The evidence does not support these as the final trigger:

- mower command or MQTT publishing;
- payload parsing or Receiver forwarding failure;
- Account reauthentication requirement;
- invalid ownership, topology or subscription configuration;
- loss of REST authentication or REST polling;
- an immediate credential-rotation overlap; or
- a planned Symcon restart.

### Remaining failure domain

The retained generic Core status cannot distinguish:

- local network or Internet interruption;
- native IP-Symcon WebSocket Client behavior;
- TLS or WebSocket handshake failure not surfaced to the Account;
- upstream broker or WSS service availability;
- server-side session or idle policy; or
- a timing interaction not represented by the bounded diagnostics.

The exact external trigger remains unresolved. Changing the three inner delays,
retry count, OAuth timing or credential policy is not justified by this evidence.

## 6. Closure Ownership Defect

The private harness rejects evidence after its deadline, but rejection alone
does not disable the productive transport. The native module records checkpoints
and episodes, but currently has no owner that enforces the private pilot's stop
criteria.

This split created two unsafe gaps:

1. the second unexpected episode was recorded but did not trigger cleanup;
2. the 72-hour deadline elapsed without disabling the credential-bearing
   transport.

An interactive chat, scheduled read-only probe or external report cannot be the
sole owner of a mandatory cleanup deadline.

## 7. Automatic Closure Design

### 7.1 Ownership

The Account module remains the sole owner of MQTT lifecycle and credential
cleanup. Automatic pilot closure belongs to the existing native pilot registry
and module timers, not to Device variables, MQTT payload handlers or a separate
public helper.

No new public Device variable is required. The existing Registry and Statistics
diagnostics remain the storage building blocks.

The current form exposes `EnableMqttShadow` as experimental, and permanent MQTT
operation remains explicitly unauthorized. Until a separately reviewed
operating mode exists, enabling this experimental path means bounded pilot mode
and carries the hard 72-hour safety cap. A future permanent mode must be an
explicit new contract; it must not silently remove the cap from the existing
experimental switch.

### 7.2 Safety clock

When a native pilot session starts, persist:

```text
sessionStartedAt
hardStopAt = sessionStartedAt + 72 hours
sessionEpisodeBaseline
closureState
closureReason
closureRequestedAt
closureCompletedAt
```

Starting the safety clock at native session start is deliberately slightly more
conservative than the external evidence clock, which starts after the second
stable baseline. Credential-bearing operation can therefore never exceed 72
hours merely because baseline capture was delayed.

### 7.3 Dedicated deadline timer

Add one owned timer whose due time is the absolute `hardStopAt`. It must not
reuse the five-hour checkpoint cadence.

On `ApplyChanges()`, kernel start and timer execution:

1. read the persisted absolute deadline;
2. if it is in the future, schedule exactly the remaining duration;
3. if it is due or overdue, enter cleanup immediately;
4. never shift the deadline because of restart, token refresh, reconnect or
   delayed execution; and
5. never replay missed checkpoints before cleanup.

### 7.4 Episode stop

Every newly opened distinct episode increments a session-local episode count.

```text
episode 1: observe bounded recovery
episode 2: request pilot closure immediately
reconnect exhaustion: request pilot closure immediately
```

Repeated Core observations inside one already open episode do not increment the
distinct count and do not issue duplicate cleanup requests.

### 7.5 Two-phase idempotent cleanup

Automatic cleanup is a state machine rather than an unguarded recursive
`ApplyChanges()` call:

```text
Active
  -> ClosureRequested
  -> CredentialsCleared
  -> PropertiesDisabled
  -> Closed
```

Required sequence:

1. acquire the existing Account lifecycle semaphore;
2. persist `ClosureRequested` and the immutable reason;
3. stop connection, observation, rotation and deadline timers;
4. disconnect the owned transport and clear Core credentials;
5. release the lifecycle semaphore;
6. set both Account pilot properties to `false`;
7. execute one deferred Account `ApplyChanges()` outside the lifecycle lock;
8. verify disabled position diagnostics, inactive WebSocket and absent
   credentials; and
9. persist `Closed` plus completion timestamp.

If execution stops between phases, the next `ApplyChanges()` or kernel-start
reconciliation resumes cleanup. A persisted closure request always takes
precedence over activation, retained-Core adoption and credential rotation.

Failure to clear credentials enters `ConfigurationError`, keeps all connection
timers stopped and remains a visible cleanup failure. It must never reactivate
MQTT to retry cleanup.

## 8. Closure Reasons

Use a fixed internal enum:

```text
deadline-reached
second-transport-episode
reconnect-exhausted
authentication-failed
configuration-failed
ownership-drift
evidence-continuity-lost
operator-disabled
```

The first reason wins. Later observations may increment bounded diagnostics but
must not overwrite the causal closure reason.

## 9. Restart And Race Contract

### Restart before deadline

- preserve the original absolute deadline;
- perform existing retained-Core reconciliation;
- continue only when all current restart-health gates pass; and
- never grant another 72-hour interval.

### Restart at or after deadline

- do not adopt retained credentials as an active pilot;
- clear owned Core credentials first;
- disable both pilot properties; and
- close without a connection attempt.

### Concurrent episode and deadline

- both paths call the same idempotent closure request;
- the first persisted reason remains authoritative;
- property mutation and `ApplyChanges()` occur once; and
- no reconnect is scheduled after `ClosureRequested`.

## 10. Future Outer Recovery Invariants

The final episode demonstrates why permanent unattended operation may someday
need a circuit-breaker half-open state. It does not provide enough evidence to
select its quiet period or cadence.

Any future operational mode must satisfy all of these invariants:

- remain credential-free while the circuit is open;
- keep REST polling authoritative and independent;
- permit at most one half-open connection probe at a time;
- revalidate ownership, configuration, REST authentication and token horizon;
- prohibit half-open probes for authentication or configuration failures;
- return to open state immediately when the probe does not reach sustained
  `102/102` health;
- require 15 healthy minutes before resetting the inner recovery episode;
- expose bounded diagnostics without coordinates or credentials; and
- allow the user to disable MQTT at any time with immediate cleanup priority.

The quiet interval, backoff progression and maximum probe frequency remain
`OPEN`. Choosing them from generic Core status `200` would violate the existing
evidence boundary. This outer recovery belongs to a future permanent-operation
mode, not to the bounded private-pilot controller.

## 11. Required Offline Tests

Implementation must add synthetic-clock coverage for:

1. exact deadline firing;
2. delayed timer execution after the deadline;
3. restart before and after the absolute deadline;
4. second distinct episode closure;
5. repeated observations inside one episode;
6. reconnect exhaustion closure;
7. simultaneous deadline and episode signals;
8. one deferred `ApplyChanges()` only;
9. crash/resume at every cleanup phase;
10. credential absence before and after property finalization;
11. no activation, reconnect or rotation after closure request;
12. unchanged REST authority, variables and Archive logging;
13. position observation removed while coordinate-free closure counters remain;
14. authentication and configuration failures never entering transport retry;
15. disabled restart remaining credential-free and inert.

No live test should be planned until these tests and the complete repository
gate pass.

## 12. Architecture Decisions

### AD-NAV-1296: Classify the final exhaustion as post-deadline evidence

The episode began after the hard boundary and cannot be used as an in-window
recovery result.

### AD-NAV-1297: Make stop criteria executable in the lifecycle owner

Recording a policy violation without disabling the transport is insufficient.
The Account must own automatic closure.

### AD-NAV-1298: Start the safety deadline at native session start

The credential-exposure cap must not depend on an external baseline capture or
interactive observer.

### AD-NAV-1299: Use one idempotent closure state machine

Deadline, repeated episodes, exhaustion and operator disable converge on one
cleanup path with first-reason retention and restart recovery.

### AD-NAV-1300: Keep pilot closure separate from permanent recovery

A bounded pilot stops on its policy boundary. It must not silently transition
into an indefinite half-open retry service.

### AD-NAV-1301: Defer half-open timing

The evidence defines safety invariants but does not justify a quiet interval or
probe frequency.

### AD-NAV-1302: Do not overload the experimental pilot switch

The current switch remains bounded by the pilot contract. Permanent operation
requires an explicit mode, migration behavior and separate release decision.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| final episode temporal classification | PASS, POST-DEADLINE |
| exact external root cause | UNRESOLVED |
| current three-attempt inner recovery | RETAIN |
| automatic second-episode closure design | READY |
| automatic hard-deadline closure design | READY |
| idempotent cleanup/resume design | READY |
| permanent half-open recovery timing | NOT READY |
| productive implementation | NOT PERFORMED |
| publication | CLOSED |
| Symcon update or MQTT activation | CLOSED |

## 14. Next Step

Proceed with step 311:

```text
311-automatic-pilot-closure-implementation.md
```

Implement only the module-owned safety deadline, second-episode stop and
idempotent cleanup/resume state machine with focused synthetic-clock tests.
Do not change the three inner reconnect delays, introduce half-open probes,
publish, update Symcon or reactivate MQTT in that step.
