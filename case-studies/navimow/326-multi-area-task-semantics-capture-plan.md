# 326 Multi-Area Task Semantics Capture Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Receive-only procedure ready; observation activation remains gated

**Date:** 2026-08-20

## 1. Purpose

This step turns the field relationships from step 325 into a bounded
multi-area evidence plan. It must determine whether task, boundary and
partition telemetry can support stable per-area statistics without changing
the mower, its schedule or existing Symcon variables.

The plan sends no mower command and does not activate MQTT by itself.

## 2. Installation Context Boundary

The private installation has multiple configured zones. A subset participates
in the regular schedule, and participating zones are processed as separate
scheduled jobs. At least one configured zone is outside that schedule.

Public evidence uses only these neutral labels:

```text
scheduled-area-<local-alias>
unscheduled-area-<local-alias>
```

Real labels, weekday/time assignments, identifiers, coordinates and garden
geometry remain in the Git-ignored private overlay.

## 3. Questions

The observation must answer:

1. Does one scheduled area produce one stable `currentMowBoundary` value?
2. Does `partitionIds` identify the same area, a task list or another object?
3. When do task progress and subtotal area reset?
4. Does progress restart at each area or span more than one area?
5. Does weekly area continue monotonically across separate jobs?
6. Which state and progress transitions occur after a rain interruption?
7. Can route samples be assigned to one area without using coordinates in
   public evidence?

## 4. Capture Scope

Minimum useful evidence comprises:

- one complete scheduled job for each participating scheduled area;
- one repeated job for at least one area on a different day;
- pre-task Docked evidence;
- Running samples throughout the task;
- Docking and final Docked evidence where naturally observed; and
- a naturally occurring rain interruption only when it happens without test
  intervention.

Preferred duration is one complete schedule week. A second week strengthens
identifier stability but is not required for the first semantic decision.

## 5. Receive-Only Contract

The observation may use the existing bounded native MQTT diagnostics and REST
status reads. During the window it must:

- keep REST authoritative for canonical mower state;
- keep MQTT strictly receive-only;
- send no Start, Stop, Pause, Resume or Dock command;
- make no schedule or map change;
- never manufacture a rain event;
- retain raw payloads only in the private output tree;
- publish only normalized, coordinate-free relationships; and
- perform the established credential-first cleanup after an activated pilot.

Activation, credential persistence and cleanup remain separate live gates.

## 6. Observation Labels

Each expected schedule slot receives a private local label before capture:

```text
expectedAreaKey
expectedWindowStart
expectedWindowEnd
expectedOccurrence
```

The label is operator context, not protocol truth. A captured area mapping is
accepted only when MQTT identifiers, REST state timing and the app-visible job
agree. Ambiguous or rain-interrupted runs remain unassigned.

## 7. Rain Classification

Rain may interrupt the current mowing program. Such a run must be classified
separately as one of:

```text
rain-paused
rain-returning
rain-aborted
rain-resumed
unknown-weather-interruption
```

Classification requires observed state and app context. Rain must not be
inferred merely from a progress gap. Interrupted evidence cannot establish a
normal task reset or completion boundary.

## 8. Private Projection

For each observed task, the private reducer should retain:

```text
observationKey
expectedAreaKey
startedAt
endedAt
REST state sequence
hashed boundary key
hashed partition-list key
partition count
first and last task progress
first and last subtotal area
first and last weekly area
pose count
coordinate-change count
out-of-order timestamp count
interruption classification
mapping confidence
```

Hashes are local correlation keys, not public identifiers. The projection must
not contain credentials or raw MQTT topics.

## 9. Acceptance Gates

### Area identity

An area key is provisionally mapped only when:

- two separate normal jobs for the same scheduled area repeat the same
  boundary or partition relationship; and
- at least one different area produces a distinguishable relationship.

### Task progress

Per-area manufacturer progress becomes design-ready only when reset and final
value behavior are consistent across two normal jobs.

### Route attribution

Route attribution becomes design-ready when the active area mapping remains
stable for the complete Running interval. Polygon-based coverage remains
blocked independently.

### Rain behavior

Rain behavior is descriptive until one interrupted and one later continued or
restarted task establish the lifecycle. It does not block normal-area mapping
when interrupted runs are excluded.

## 10. Fail-Closed Rules

The observation is inconclusive when:

- app and schedule context disagree;
- identifiers change inside a task without explainable transition evidence;
- the capture misses task start or terminal state;
- token or transport gaps cover an area transition;
- a task is interrupted but the cause is not observed; or
- fewer than two distinguishable scheduled areas are captured.

No public per-area variable may be created from inconclusive evidence.

## 11. Architecture Decisions

### AD-NAV-1339: Use schedule context only as a private hypothesis

**Decision:** Correlate scheduled areas privately and require protocol
repetition before accepting a mapping.

**Rationale:** A schedule describes intent, not necessarily the executed task.

### AD-NAV-1340: Isolate rain-interrupted tasks

**Decision:** Do not combine rain-interrupted and normally completed tasks in
reset or completion analysis.

**Rationale:** Weather handling can change lifecycle semantics.

### AD-NAV-1341: Preserve command-free observation

**Decision:** Learn area semantics from normal scheduled jobs only.

**Rationale:** The evidence objective does not require mower actuation.

## 12. Decision And Next Gate

**Multi-area capture design: PASS.**

**MQTT activation: not performed.**

The next live gate is one bounded receive-only observation activation using
the private schedule map. It must begin before a normal scheduled job and
retain automatic closure plus mandatory cleanup.
