# 164 Native MQTT Core Resume Ordering Failure Analysis

**Case study:** Navimow native IP-Symcon module
**Status:** Root-cause class confirmed; exact live branch remains
under-instrumented; correction design approved for offline implementation
**Date:** 2026-07-28
**Scope:** Static analysis of the failed Core-resume contract from step 163

## 1. Purpose

Step 163 proved that the kernel hook and delayed reconciliation ran, but the
Account did not adopt the resumed Core:

```text
expected: ShadowActive / core-resumed / connectionAttempts +0
observed: Connecting / connection-attempt / connectionAttempts +1
```

This step reconstructs the startup ordering, identifies the missing regression
coverage and defines a deterministic correction. It performs no live Symcon,
MQTT, REST or mower operation.

## 2. Evidence Boundary

The analysis uses:

- productive lifecycle code in
  `distribution/NavimowAccount/module.php`;
- the deterministic lifecycle test and Symcon runtime harness;
- the sanitized report from step 163;
- the private machine-readable Gate-G/H evidence;
- the lifecycle architecture decisions from steps 154 through 156.

No credential value, private topic, endpoint, Device ID, ObjectID or payload was
read into this public report.

## 3. Confirmed Live Timeline

The bounded evidence establishes:

| Event | Timestamp | Relative to kernel start |
|---|---:|---:|
| new kernel epoch | `1785268124` | `+0 s` |
| Account observed kernel start | `1785268199` | `+75 s` |
| Account reconciled kernel epoch | `1785268214` | `+90 s` |

The observation-to-reconciliation delay was exactly 15 seconds.

At the bounded post-restart projection:

```text
MQTT/WebSocket status:       102/102
WebSocket Active:            true
connectionAttempts delta:    +1
connectionSuccesses delta:   +0
connectionFailures delta:    0
coreResumeObservations:      +0
last transition reason:      connection-attempt
accepted ingress delta:      +2
```

Therefore:

1. the kernel message registration and timer executed;
2. the healthy-Core adoption branch did not complete;
3. one Account-owned connection path ran;
4. native data continuity survived independently of the failed lifecycle
   contract.

## 4. Productive Startup Paths

### 4.1 `Create()`

`Create()` registers `IPS_KERNELSTARTED` and the lifecycle timer. It does not
mutate Core configuration.

### 4.2 `ApplyChanges()`

The current implementation performs this sequence:

```text
clear ephemeral MQTT state
stop reconciliation and lifecycle timers
if disabled:
  disconnect owned transport
else if ownership exists:
  disconnect owned transport
initialize lifecycle
schedule REST/auth timers
schedule MQTT initial attempt after 5 seconds
```

For an enabled, adopted transport, the `else if` condition is true. The owned
transport is therefore always deactivated and its Authorization header, MQTT
username and MQTT password are cleared.

This behavior is appropriate for an explicit same-kernel configuration apply
that intentionally requests a credential-safe restart. It is incompatible
with Core-resume adoption when `ApplyChanges()` participates in service
startup.

### 4.3 `MessageSink()`

`MessageSink()` records the current kernel epoch and replaces the lifecycle
schedule with `kernel-reconcile` after 15 seconds. It is mutation-free.

The message was observed 75 seconds after the recorded kernel start. A generic
5-second startup attempt is therefore eligible substantially earlier if
`ApplyChanges()` runs during that interval.

### 4.4 Kernel Reconciliation

At reconciliation the code chooses exactly one branch:

| Observation | Branch |
|---|---|
| owned Core healthy and WebSocket active | adopt as `core-resumed` |
| owned Core credential-free | schedule initial attempt after 5 seconds |
| owned Core unhealthy with credentials | clean and schedule bounded reconnect |
| invalid auth/configuration/ownership | terminal or waiting state |

The live counters exclude completed healthy adoption. They do not identify
which non-adoption branch produced the later attempt.

### 4.5 Other Attempt Sources

An Account connection can also follow:

- successful OAuth code exchange;
- successful automatic token refresh and credential rotation;
- bounded reconnect after an unhealthy observation;
- explicit `ConnectMqttShadow()`.

No manual authentication or explicit Connect was authorized during Gate G.
Automatic token refresh remains a theoretically possible competing event
because the current diagnostics do not record connection-trigger provenance.

## 5. Confirmed Root-Cause Class

The following code-level contradiction is deterministic:

```text
selected restart contract:
  preserve healthy native Core -> inspect -> adopt

current enabled ApplyChanges contract:
  deactivate Core -> clear credentials -> schedule Account connection
```

Both contracts cannot hold in the same startup sequence.

The live evidence is consistent with this conflict, but it does not contain:

- the exact `ApplyChanges()` invocation timestamp;
- Core status and credential-presence projection at reconciliation entry;
- the scheduled kind consumed by the connection attempt;
- an automatic token-refresh timestamp correlated with the attempt.

The root-cause class is therefore confirmed. Attribution of the single live
attempt to one exact branch is high-confidence but not conclusive.

## 6. Missing Regression Coverage

The healthy native-restart test currently models:

```text
Create
restore persistent Account state
retain active Core state
MessageSink(IPS_KERNELSTARTED)
advance 15 seconds
ProcessMqttLifecycle
```

It deliberately omits `ApplyChanges()`.

A separate test verifies that `ApplyChanges()`:

- deactivates WebSocket;
- clears Authorization and MQTT credentials;
- schedules a 5-second initial attempt.

Both isolated tests pass, while their contracts conflict in the real startup
ordering. No test currently executes:

```text
Create
restore persistent state and active Core
new kernel epoch
ApplyChanges
MessageSink
reconcile
```

The test suite therefore cannot detect the Gate-G failure.

## 7. Correction Options

### Option A: Increase the generic startup delay

Rejected. The observed kernel message arrived after 75 seconds. Any fixed
delay large enough to win this run would be platform-timing dependent and
would slow normal activation.

### Option B: Let `MessageSink()` overwrite the generic timer

Insufficient. The 5-second attempt can execute before the kernel message and
`ApplyChanges()` has already destroyed the resumable Core state.

### Option C: Always preserve active Core in `ApplyChanges()`

Rejected. Explicit configuration changes, disable, authentication reset and
credential rotation still require deterministic cleanup or restart behavior.

### Option D: Treat every restart as a normal Account reconnect

Rejected. This contradicts AD-NAV-524, duplicates work already performed by
Core, obscures ownership semantics and invalidates the accepted Gate-G
counter contract.

### Option E: Add a kernel-epoch precedence barrier

Selected. A new, unreconciled kernel epoch must take precedence over generic
startup and credential rotation scheduling.

## 8. Selected Ordering Contract

The next implementation must use the current kernel start timestamp together
with persisted lifecycle metadata.

For an enabled, valid, adopted transport:

```text
new or already pending unreconciled kernel epoch
  -> preserve owned Core configuration
  -> do not clear credentials
  -> do not schedule generic initial connection
  -> schedule exactly one kernel reconciliation

same reconciled kernel epoch plus explicit ApplyChanges
  -> retain current credential-safe restart behavior

feature disabled
  -> disable and clean immediately
```

`MessageSink()` remains the supported post-ready observer, but
`ApplyChanges()` must also recognize an already changed kernel epoch. This
closes the interval before `IPS_KERNELSTARTED` is delivered.

The scheduling helper must never overwrite a pending `kernel-reconcile` with
`initial`, `rotation` or `reconnect`. Explicit disable remains the only
unconditional winner.

## 9. Epoch Metadata

The existing private fields are sufficient for the basic barrier:

```text
kernelStartTime
kernelStartObservedAt
kernelStartReconciledAt
scheduledKind
```

Required interpretation:

- `kernelStartTime == current kernel` and reconciliation timestamp is zero:
  current epoch is pending;
- persisted kernel differs from current positive kernel timestamp:
  a new epoch exists;
- current epoch has a positive reconciliation timestamp:
  normal same-kernel configuration behavior may proceed.

For first installation or malformed metadata, fail toward the existing
credential-safe delayed connection. Do not infer Core ownership from status
alone.

## 10. Trigger Provenance

The next implementation should add bounded private diagnostics:

```text
lastConnectionTrigger
lastConnectionTriggerAt
lastKernelCoreClassification
lastKernelCoreClassificationAt
```

Allowed trigger values:

```text
manual
initial
kernel-fallback
reconnect
rotation
```

Allowed kernel classifications:

```text
healthy
credential-free
unhealthy-with-credentials
disabled
authentication-unavailable
configuration-invalid
ownership-invalid
```

These fields contain no credential or device data. They make future live
evidence causally attributable without reading secrets or increasing network
activity.

## 11. Required Regression Matrix

Before publication, offline tests must prove:

1. real ordering `Create -> restore -> ApplyChanges -> MessageSink` preserves
   a healthy active Core;
2. the inverse ordering `MessageSink -> ApplyChanges` preserves the pending
   kernel reconciliation;
3. the new epoch produces `core-resumed`, attempt delta zero and one
   Core-resume observation;
4. duplicate `ApplyChanges()`, message delivery and timer execution remain
   idempotent for the epoch;
5. same-epoch explicit `ApplyChanges()` still performs credential-safe delayed
   restart;
6. disabled `ApplyChanges()` wins and cleans immediately;
7. credential-free Core at reconciliation schedules exactly one
   `kernel-fallback` attempt;
8. unhealthy credential-bearing Core enters bounded recovery;
9. token refresh cannot overwrite a pending kernel reconciliation;
10. authentication/configuration failures do not retry;
11. trigger and classification diagnostics normalize and redact safely;
12. all public variables, profiles, actions and Archive Control identities
    remain unchanged.

The harness must model automatic startup ordering instead of assuming that
`ApplyChanges()` is absent.

## 12. Architecture Decisions

### AD-NAV-558: Kernel epoch takes precedence over generic startup

**Decision:** A changed or pending unreconciled kernel epoch blocks generic
MQTT startup scheduling.

**Reason:** Core-resume state must survive until the Account can classify it.

### AD-NAV-559: Split startup apply from explicit same-epoch apply

**Decision:** `ApplyChanges()` uses kernel epoch metadata to distinguish a
restart candidate from an explicit configuration apply.

**Reason:** The two situations require opposite transport behavior even
though IP-Symcon may enter the same callback.

### AD-NAV-560: Preserve one lifecycle executor

**Decision:** Both early epoch detection and `MessageSink()` only schedule the
existing locked lifecycle reconciliation.

**Reason:** Core inspection and mutation remain bounded, serialized and
idempotent.

### AD-NAV-561: Record private connection provenance

**Decision:** Record the bounded trigger and kernel classification for each
future attempt.

**Reason:** Counters prove that work occurred but cannot identify which
ordering branch caused it.

### AD-NAV-562: Keep live MQTT disabled

**Decision:** Do not reactivate MQTT or repeat the restart until the corrected
ordering matrix passes offline and is separately published.

**Reason:** Step 163 already reached the stop condition and completed
credential cleanup.

## 13. Impact on Existing Contracts

The correction must not change:

- REST as the authority for public mower state;
- receive-only MQTT behavior;
- MQTT publish prohibition;
- mower command behavior;
- public variable Idents, types, profiles or positions;
- existing Archive Control logging;
- ownership validation;
- bounded `60/300/900` recovery;
- authentication and configuration terminal-error policy.

No productive PHP change is made in this analysis step.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| startup ordering reconstructed | PASS |
| deterministic contract conflict found | PASS |
| missing regression scenario found | PASS |
| exact live attempt branch proven | NO |
| correction strategy selected | PASS |
| productive implementation | NOT STARTED |
| MQTT active | NO |
| another restart authorized | NO |
| REST state authority | RETAINED |

## 15. Recommended Next Step

Create:

```text
165-native-mqtt-core-resume-ordering-correction-implementation.md
```

That step should:

1. implement the kernel-epoch precedence barrier;
2. preserve current same-kernel explicit `ApplyChanges()` cleanup semantics;
3. prevent startup and token-rotation schedules from replacing a pending
   kernel reconciliation;
4. add private trigger and Core-classification diagnostics;
5. add both real-order and inverse-order regression scenarios;
6. run the complete offline validation suite.

Publication, Symcon update, MQTT activation and another restart remain separate
future gates requiring explicit authorization.
