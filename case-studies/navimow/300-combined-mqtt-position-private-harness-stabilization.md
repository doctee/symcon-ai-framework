# 300 Combined MQTT Position Private Harness Stabilization

**Case study:** Navimow native IP-Symcon module

**Status:** Private harness consumes pilot-wide position accounting; offline
validation passed; post-deadline closure remains fail-closed

**Date:** 2026-08-09

## 1. Purpose

Step 299 adds cumulative coordinate-free accounting to future module snapshots.
The private pilot harness must consume that aggregate without losing support
for snapshots from the currently installed older module.

## 2. Harness Change

When `pilotDiagnostics.positionAccounting` is present and valid, the harness
uses:

```text
receivedSamples
coordinateChanges
```

for position-window deltas. If the additive object is absent, it falls back to
the existing current-segment counters in `positionDiagnostics`.

The harness validates all five aggregate fields as non-negative integers. It
does not read coordinates, topics, payloads, ObjectIDs or identities.

## 3. Regression Proof

The private offline suite now includes this transition:

```text
raw segment received: 10 -> 3
pilot total received: 10 -> 20
segment sequence:      1 -> 2
reset count:           0 -> 1
```

The harness records a valid position-evidence window and does not emit either
raw-counter regression stop reason.

Legacy fixtures without `positionAccounting` continue to pass through the
fallback path.

## 4. Deadline Boundary

The harness still rejects a regular snapshot captured after `deadlineAt`.
Silently accepting current post-deadline counters would contaminate the frozen
pilot window.

The productive candidate now records `episodeSequence` and `rotationSequence`
inside every future checkpoint. A later harness step may add a dedicated
deadline-closure input that:

1. selects only checkpoints with `recordedAt <= deadlineAt`;
2. evaluates episode and position aggregates from that checkpoint;
3. ignores later transport counters;
4. filters REST transitions to the approved window;
5. remains read-only and cleanup-independent.

This mode cannot reconstruct missing fields from old checkpoints and is not
claimed by the current candidate.

## 5. Architecture Decisions

### AD-NAV-1261: Prefer pilot accounting with legacy fallback

The new aggregate fixes segment resets while the fallback keeps the private
harness usable against the currently installed older module.

### AD-NAV-1262: Keep normal post-deadline ingest fail-closed

A new checkpoint schema enables future reconstruction but does not authorize
using a current snapshot outside the observation window.

### AD-NAV-1263: Do not retrofit unavailable historical checkpoint fields

The failed pilot remains closed by its immutable forensic evidence. New
checkpoint semantics apply only to a future module version and pilot session.

## 6. Verification

The private validation command passed syntax checks for all harness files and
the complete offline state-machine suite.

No Symcon access, MQTT activation, OAuth action, mower command, publication or
repository mutation was performed by the private harness validation.

## 7. Next Step

Run the focused Navimow suite and complete repository checks. If they pass,
freeze the local candidate and prepare separate publication, disabled-update
and future pilot gates.
