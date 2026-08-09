# 298 Combined MQTT Position Pilot Failure Analysis And Stabilization Design

**Case study:** Navimow native IP-Symcon module

**Status:** Root cause of position regressions proved; transport trigger still
external and unresolved; bounded stabilization designed

**Date:** 2026-08-09

## 1. Evidence Separation

The 72-hour pilot produced three different observations:

1. local position payload reception worked during real mowing cycles;
2. position counters decreased between native checkpoints;
3. fourteen distinct unexpected transport episodes recovered automatically.

These observations do not have one common root cause and must not be corrected
as one generic reconnect problem.

## 2. Position Counter Root Cause

The position track is intentionally part of `MqttEphemeralState`.
`clearMqttEphemeralState()` removes coordinates during:

- Account `ApplyChanges()`;
- owned-transport disconnect;
- OAuth-driven MQTT credential rotation;
- unexpected-disconnect recovery;
- final feature cleanup.

The pilot observed 79 credential rotations. Tokens are refreshed with a
five-minute margin, and every successful OAuth refresh rotates the private MQTT
credentials through the owned disconnect path. The rotation volume is therefore
consistent with short-lived credentials and is not itself a defect.

The raw position counters belong to one ephemeral transport segment. Treating
them as pilot-wide counters caused the seven apparent regressions.

## 3. Preserved Privacy Boundary

Coordinates, orientation, source timestamps and retained tracks must still be
deleted on every owned disconnect. Persisting the complete position diagnostic
across reconnects would weaken the established privacy and credential-cleanup
boundary.

Only these coordinate-free aggregates may survive inside the existing bounded
pilot registry:

```text
receivedSamples
coordinateChanges
outOfOrderTimestamps
segmentSequence
counterResetCount
```

They reset when a new pilot session starts and remain bounded by the existing
diagnostic counter limit.

## 4. Accumulation Contract

Before clearing a non-empty position segment, the Account rolls the segment's
counter deltas into the current pilot session. At each native checkpoint it
also incorporates the current segment delta.

For every counter:

```text
pilotTotal += max(0, segmentCurrent - segmentBaseline)
```

If any segment counter is below its prior baseline, a new segment is opened and
the complete current value becomes the delta. Repeated cleanup of an already
empty segment adds nothing.

The retained-track count remains a current-segment gauge. It is not accumulated
because the bounded track may downsample and evict entries.

## 5. Deadline Reconstruction Contract

Each native checkpoint additionally freezes:

```text
episodeSequence
rotationSequence
```

This lets a future harness evaluate transport policy at the latest checkpoint
whose `recordedAt` is not later than the pilot deadline. A snapshot captured
after the deadline must never use later live counters as if they belonged to
the approved observation window.

## 6. Transport Episode Assessment

The fourteen distinct episodes remain real pilot failures. Existing evidence
continues to narrow them to the native WebSocket or upstream WSS path:

- no parser failure;
- no REST-authority loss;
- no reconnect exhaustion;
- no direct overlap with recorded credential rotation;
- automatic recovery remained functional.

Generic Core status `200` does not identify a WebSocket close code, local
network interruption or server-side session policy. No reconnect delay, retry
count, OAuth policy or pilot threshold may be changed without stronger evidence.

## 7. Architecture Decisions

### AD-NAV-1253: Keep coordinates ephemeral

Pilot stability must not be obtained by retaining coordinate tracks across
credential or transport cleanup.

### AD-NAV-1254: Accumulate only coordinate-free session counters

The existing pilot registry is the correct owner for aggregate evidence. A new
public variable or independent storage abstraction is unnecessary.

### AD-NAV-1255: Treat credential rotations as planned lifecycle events

The observed rotation count matches the OAuth refresh model. It remains
separate from distinct unexpected transport episodes.

### AD-NAV-1256: Freeze episode sequence in every checkpoint

Deadline reconstruction must use evidence timestamped inside the approved
window, not counters sampled after it.

### AD-NAV-1257: Do not tune recovery from generic status 200

The exact external trigger is unresolved. Recovery policy remains unchanged
until more specific native or upstream evidence exists.

## 8. Implementation Boundary

The candidate may change only:

- Account pilot-registry accounting and projections;
- native checkpoint fields;
- focused tests and sanitized fixtures;
- the private deadline harness after the productive contract is proven.

It must not change REST authority, MQTT direction, public device variables,
Archive logging, commands, retry delays, OAuth timing or transport activation.
