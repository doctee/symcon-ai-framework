# 228 Native MQTT Pilot Checkpoint and Episode Diagnostics Design

**Case study:** Navimow native IP-Symcon module
**Status:** Design complete; productive implementation and offline validation
are defined, publication and live use remain closed
**Date:** 2026-07-30
**Scope:** Replace external checkpoint dependency with bounded native Account
diagnostics

## 1. Purpose

Step 227 identified two independent evidence gaps:

1. the proposed external automation was never proven persistent;
2. aggregate MQTT counters could not reconstruct individual recovery episodes.

The next private pilot must therefore retain its transport evidence inside the
long-running IP-Symcon Account instance. This design defines that evidence
without enabling MQTT, changing recovery behavior or adding public variables.

## 2. Authority and Scope

The existing authority order remains:

```text
public device state and automation: REST
receive-only early hint:            MQTT
pilot continuity evidence:          internal Account diagnostics
```

The native diagnostic scope is limited to:

- five-hour checkpoint continuity;
- transport episode start and completion;
- credential-rotation overlap;
- restart-safe scheduling;
- bounded, identity-free inspection.

Mowing cycles are not reconstructed in the Account. Existing REST-owned
`VehicleState` Archive Control data remains the source for later cycle
evaluation. This avoids a second state history and preserves all existing
variable identities and logging settings.

## 3. Chosen Runtime Model

The Account owns:

```text
attribute: MqttPilotObservationRegistry
timer:     MqttPilotCheckpoint
API:       GetMqttPilotDiagnostics()
callback:  ProcessMqttPilotCheckpoint()
```

The timer interval is five hours:

```text
18,000 seconds
```

This leaves one hour of margin below the six-hour evidence-gap hard stop from
step 227.

No new property is introduced. `EnableMqttShadow` only permits observation;
the session follows the actual connection lifecycle:

- disabled: timer stopped, retained session closed;
- enabled but only staged: timer remains stopped;
- first actual connection attempt: session begins and the next absolute
  checkpoint is scheduled;
- restart: persisted absolute schedule reconciled;
- overdue: exactly one delayed checkpoint, no replay storm;
- manual disconnect or disabled feature: session closes, timer stops and
  diagnostic history remains retained.

## 4. Bounded Registry

The registry uses a fixed schema and bounded rings:

| Evidence | Maximum |
|---|---:|
| checkpoints | 32 |
| completed transport episodes | 32 |
| credential rotations | 64 |
| open transport episode | 1 |

Thirty-two checkpoints cover more than the 72-hour pilot horizon. Unknown
top-level fields are removed on every productive write.

Checkpoint entries contain only:

- sequence and session sequence;
- scheduled and actual timestamps;
- delay;
- lifecycle and last observed Core statuses;
- configuration and REST connection state;
- remaining token horizon;
- selected monotonic MQTT counters.

They contain no device identity, topic, payload, endpoint, credential, header,
hostname, ObjectID or geometry.

## 5. Episode Contract

An episode begins only at the two existing
`unexpectedDisconnects` increment points:

```text
lifecycle-observation
kernel-reconciliation
```

The open record retains:

```text
sequence
detectedAt
detectionSource
mqttStatus
webSocketStatus
reconnectAttemptsUsed
overlappedRotation
kernelStartTime (internal only)
```

Completion adds:

```text
recoveredAt
durationSeconds
outcome
kernelEpochChanged
```

Allowed terminal outcomes are:

```text
recovered
reconnect-exhausted
disabled
```

Repeated unhealthy observations while one episode is open do not create
duplicates. Rotation marks overlap and preserves the attempt count before the
existing lifecycle reset.

## 6. Scheduling Contract

The registry persists `nextCheckpointAt` as an absolute timestamp.

At `ApplyChanges()`:

1. disabled observation is closed and its timer stopped;
2. a newly enabled observation starts one session;
3. a future due time is scheduled exactly;
4. an overdue due time schedules one immediate callback.

After a delayed callback, the next due time advances by whole five-hour
periods until it lies in the future. Missed checkpoints are not fabricated.
The retained `delaySeconds` makes the evidence gap explicit.

Timer-lock contention retries after 60 seconds. It does not change connection
or recovery state.

## 7. Inspection Contract

`GetMqttPilotDiagnostics()` is a dedicated version-1 read-only projection.
The existing version-2 `GetMqttDiagnostics()` remains unchanged so current
fixtures and private consumers do not receive an incompatible shape.

The dedicated projection:

- emits fixed keys only;
- revalidates codes, integers and booleans;
- applies ring bounds at read time;
- writes no state;
- sends no network request;
- changes no timer;
- exposes no internal `kernelStartTime`.

The Account form may expose the API through a read-only button. No variable or
Archive Control entry is created.

## 8. Safety and Compatibility

The implementation must not:

- activate or adopt MQTT;
- reconnect or disconnect the transport;
- alter the three-attempt recovery policy;
- issue a mower command;
- write public Device state from MQTT;
- create or rename an Account or Device variable;
- alter Archive Control logging or aggregation;
- require an ObjectID.

Publication, Symcon update, staging, pilot activation and restart remain
separate explicit gates.

## 9. Regression Matrix

Offline validation must prove:

| Scenario | Expected result |
|---|---|
| default disabled | no timer, inactive empty projection |
| inactive enable/staging | no session and no timer |
| first connection attempt | one session, five-hour absolute due time |
| diagnostic read | persistent state unchanged |
| restart before due | remaining interval restored |
| restart after due | one immediate delayed checkpoint |
| delayed callback | no replay; next absolute future slot |
| disconnect and recovery | one bounded completed episode |
| rotation overlap | overlap and attempts retained |
| kernel epoch change | boolean classification retained |
| malformed stored data | bounded fixed projection, no pass-through |
| disable | timer stopped, session closed |
| public contract | exactly six Account variables remain |
| existing MQTT suite | unchanged behavior |

## 10. Architecture Decisions

### AD-NAV-826: Automate checkpoint evidence inside the Account

The checkpoint owner is the same persistent module that owns the transport
lifecycle. External automation is no longer required for five-hour evidence
continuity.

The observation clock begins with the actual connection attempt, not inactive
staging.

### AD-NAV-827: Preserve REST Archive Control as mowing-cycle evidence

Native pilot diagnostics record transport continuity only. Existing logged
REST state remains the authoritative cycle history.

### AD-NAV-828: Use an absolute restart-safe five-hour schedule

Absolute persistence prevents timer drift and leaves a one-hour margin below
the accepted evidence-gap stop.

### AD-NAV-829: Retain bounded episode summaries beside aggregate counters

Aggregate counters remain useful, but policy decisions require duration,
attempt, rotation-overlap and kernel-epoch evidence per episode.

### AD-NAV-830: Keep the existing MQTT diagnostic API compatible

A dedicated versioned API is added instead of changing the established
version-2 result.

### AD-NAV-831: Preserve variables, logging and recovery semantics

The feature is internal and observational. It creates no variable, archive
change, transport attempt or mower action.

## 11. Next Gate

Implement this design locally, add focused regression coverage and run the
complete Navimow MQTT offline gate. Publication and any Symcon use require a
new explicit step and authorization.
