# 311 Automatic Pilot Closure Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Implemented and offline-verified; publication and live gates remain
closed

**Date:** 2026-08-13

## 1. Purpose

Step 310 identified two executable safety gaps in the experimental receive-only
MQTT pilot: the Account recorded repeated transport episodes but did not stop at
the second episode, and no module-owned timer enforced the 72-hour boundary.

This step implements the bounded closure contract in the canonical case-study
distribution. It does not alter REST authority, MQTT subscription or payload
semantics, the three inner reconnect delays, OAuth handling, public Device
variables or mower commands.

No publication, Symcon access, module update, MQTT activation, credential
retrieval, OAuth action, restart or mower action was performed.

## 2. Changed Files

| File | Change |
|---|---|
| `distribution/NavimowAccount/module.php` | Adds the absolute pilot deadline and idempotent automatic closure controller. |
| `tests/mqtt-pilot-checkpoints.php` | Adds synthetic-clock deadline, restart, episode and closure-resume coverage. |
| `tests/mqtt-transport-lifecycle.php` | Integrates reconnect exhaustion with automatic closure and preserves authentication/configuration classification tests. |
| `README.md` | Records steps 309 through 311 and the current disabled pilot state. |

## 3. Implemented Contract

### 3.1 Absolute safety deadline

Starting a native pilot session now persists:

```text
hardStopAt = startedAt + 259200 seconds
sessionEpisodeBaseline = current cumulative episode sequence
closureState = Active
```

`MqttPilotDeadline` is a dedicated timer. It is scheduled from the persisted
absolute timestamp and therefore does not move after `ApplyChanges()`, restart,
token refresh, checkpoint delay, credential rotation or reconnect.

At or after the deadline, the Account requests `deadline-reached` closure before
any further lifecycle processing. A delayed timer executes the same overdue
path; missed five-hour checkpoints are not replayed first.

### 3.2 Episode boundary

The cumulative episode sequence remains unchanged. A per-session baseline makes
the stop rule independent of previous sessions:

```text
session episode 1 -> bounded recovery remains permitted
session episode 2 -> immediate closure request
```

Repeated Core observations while one episode is already open do not increment
the sequence and cannot create duplicate closure requests.

### 3.3 Reconnect exhaustion

The existing `60 / 300 / 900` second reconnect sequence remains unchanged.
After exactly three failed transport attempts, the existing
`reconnect-exhausted` terminal transition additionally enters automatic pilot
closure. No fourth attempt and no outer half-open probe were introduced.

Authentication and configuration failures retain their existing terminal
classification and do not enter transport retry.

## 4. Closure State Machine

The persisted pilot registry now carries:

```text
closureState
closureReason
closureRequestedAt
credentialsClearedAt
propertiesDisabledAt
closureCompletedAt
```

The implemented transitions are:

```text
Active
  -> ClosureRequested
  -> CredentialsCleared
  -> PropertiesDisabled
  -> Closed
```

The first supported reason wins:

```text
deadline-reached
second-transport-episode
reconnect-exhausted
```

Later deadline, episode or exhaustion signals do not replace it.

## 5. Mutation Ordering

Closure request handling runs under the existing Account MQTT lifecycle
semaphore and immediately stops:

- reconciliation scheduling;
- lifecycle observation and reconnect scheduling;
- five-hour pilot checkpoints; and
- the absolute deadline timer.

`MqttPilotClosure` then performs the destructive sequence:

1. validate and disconnect the Account-owned Core chain;
2. verify that WebSocket authorization and MQTT username/password are absent;
3. persist `CredentialsCleared`;
4. leave the lifecycle semaphore;
5. set `EnableMqttShadow` and `EnableMqttPositionDiagnostics` to `false`;
6. issue one deferred Account `ApplyChanges()`; and
7. persist `Closed` and the completion timestamp.

The Account property mutation is deliberately outside the lifecycle semaphore.
This avoids recursive `ApplyChanges()` while a lifecycle operation owns the
lock.

If credential removal fails, connection timers remain stopped, the lifecycle
enters `ConfigurationError`, and only the cleanup phase is retried. MQTT is not
reactivated to perform cleanup.

## 6. Restart And Race Behavior

`ApplyChanges()` now gives a persisted closure request precedence over Core
resume, startup connection and credential rotation.

- Restart before deadline restores the original `hardStopAt` and remaining
  timer interval.
- Restart after deadline requests closure without a connection attempt.
- Restart in `ClosureRequested`, `CredentialsCleared` or
  `PropertiesDisabled` resumes the next idempotent phase.
- Concurrent stop signals retain the first reason and share one closure timer.
- Lifecycle, reconnect and rotation scheduling return without work once closure
  is pending.

## 7. Diagnostic Contract

The existing bounded format remains additive. `GetMqttPilotDiagnostics()` now
projects the deadline, episode baseline and all closure timestamps. The bounded
summary projects the deadline, closure state, first reason and completion time.

No credentials, topics, device identifiers or coordinates are added. Position
diagnostics remain ephemeral and are cleared by the final `ApplyChanges()`;
coordinate-free pilot accounting and closure evidence remain available.

No public Device variable was added or removed. Existing variables and their
Archive logging identity remain unchanged. REST remains authoritative for all
public mower state.

## 8. Offline Evidence

Synthetic-clock tests prove:

- exact 72-hour deadline firing;
- overdue closure during restart;
- unchanged absolute deadline across a pre-deadline restart;
- one episode remaining recoverable;
- repeated observations inside one episode remaining one episode;
- immediate closure request on episode two;
- first-reason retention for concurrent signals;
- reconnect exhaustion after exactly three attempts;
- no fourth reconnect attempt;
- deferred property finalization outside the lifecycle lock;
- one Account `ApplyChanges()` for a normal closure;
- persisted `ClosureRequested` recovery after restart;
- disabled position diagnostics after closure;
- unchanged public variable state;
- inert lifecycle scheduling after closure request; and
- unchanged authentication and configuration no-retry behavior.

The focused MQTT suite, distribution validator and complete repository gate
pass with the canonical dependency toolset from the main workspace.

## 9. Architecture Decisions

### AD-NAV-1303: Treat the experimental switch as bounded pilot mode

`EnableMqttShadow` continues to mean a maximum 72-hour receive-only pilot. A
future permanent mode requires a new explicit contract rather than silently
removing this cap.

### AD-NAV-1304: Persist an absolute deadline

Relative timers are scheduling mechanisms only. `hardStopAt` is the safety
authority and survives restart or delayed execution unchanged.

### AD-NAV-1305: Count episodes relative to the session baseline

Cumulative diagnostics remain monotonic while each new pilot independently
enforces its one-recoverable-episode limit.

### AD-NAV-1306: Remove credentials before disabling properties

Core cleanup precedes Account property finalization. A property-only disabled
state is not accepted as proof that credential-bearing child instances are
clean.

### AD-NAV-1307: Defer Account ApplyChanges outside the lifecycle lock

The closure timer separates lock-owned transport cleanup from Account
reconfiguration and prevents recursive lifecycle ownership.

### AD-NAV-1308: Keep permanent transport recovery out of this change

No half-open circuit state, outer retry cadence or permanent mode is inferred
from generic Core status evidence.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| automatic 72-hour deadline | IMPLEMENTED |
| automatic second-episode stop | IMPLEMENTED |
| reconnect-exhaustion closure | IMPLEMENTED |
| restart-resumable cleanup | IMPLEMENTED |
| credential-first ordering | IMPLEMENTED |
| REST and public-variable compatibility | PASS, OFFLINE |
| focused MQTT suite | PASS |
| complete repository gate | PASS |
| publication | CLOSED |
| Symcon update | CLOSED |
| MQTT activation or live pilot | CLOSED |

## 11. Next Step

After the final local repository gate passes, proceed with a separate
publication-readiness step. That step should freeze the exact productive and
test files, review standalone metadata impact and define distinct publication,
disabled Symcon rollout and live-validation gates. It must not combine
publication with MQTT activation.
