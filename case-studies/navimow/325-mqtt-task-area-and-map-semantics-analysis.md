# 325 MQTT Task, Area And Map Semantics Analysis

**Case study:** Navimow native IP-Symcon module

**Status:** Local route visualization feasible; per-area coverage requires
multi-area and map-boundary evidence

**Date:** 2026-08-20

## 1. Purpose

This step analyzes whether retained receive-only MQTT evidence can support:

- a local map with mowing tracks and current mower position;
- visible mowing lanes;
- task-progress statistics;
- actual or estimated mowed coverage per configured area; and
- stable future IP-Symcon variables without replacing existing identities.

It also refreshes the current REST command status. This is analysis only. It
changes no productive PHP, module metadata, Symcon object, Archive setting,
MQTT configuration or mower state and sends no command.

## 2. Evidence Boundary

The analysis uses:

- the 72-hour receive-only pilot result from step 324;
- the private structure-only analysis of two bounded MQTT captures;
- sanitized private location messages;
- the implemented bounded position parser and accumulator;
- the official Navimow SDK and Home Assistant source as of 2026-08-20;
- the current public Stop clarification issue; and
- the experimental community-source classification from step 84.

No coordinate, device identity, private topic, payload, ObjectID, hostname or
absolute garden-area value is reproduced here.

## 3. Proven Position Contract

The captures contain 2636 location records. Of these, 2575 contain a complete
pose:

```text
postureX
postureY
postureTheta
time
vehicleState
```

All complete poses are numerically parseable and coordinate pairs vary across
the captured movement. Nominal source cadence is about two seconds, with a
small bounded number of out-of-order timestamps.

The supported interpretation remains:

```text
coordinate system: Navimow-local map
global WGS84 mapping: not established
orientation unit: likely radians, not yet vendor-confirmed
receive ordering: authoritative for ingestion sequence
source timestamp: diagnostic and occasionally out of order
```

The completed pilot adds 7779 position samples and 7696 coordinate changes,
which proves that the contract is useful over a multi-day observation rather
than only in a short capture.

## 4. Proven Task And Progress Fields

The private captures contain 42 sparse progress records and 17 separate
partition records. Progress records include:

```text
action
currentMowBoundary
currentMowProgress
mapWorkPosition
mowStartType
mowingPercentage
mowingWeekArea
subtotalArea
optional subAction
```

Partition records include `partitionIds`. These fields are sparse and must be
accumulated as partial state instead of required on every pose message.

### 4.1 Progress relationship

Across all 42 records:

- `mowingPercentage` is monotonic;
- `currentMowProgress` is monotonic;
- `floor(currentMowProgress / 100)` equals `mowingPercentage` every time;
- `subtotalArea` is numerically parseable and monotonic;
- `mowingWeekArea` is numerically parseable and monotonic; and
- the difference between weekly and subtotal area remains effectively
  constant within decimal serialization tolerance.

This strongly supports:

```text
currentMowProgress: hundredths-of-percent task progress
mowingPercentage:  integer task-progress projection
subtotalArea:      cumulative current-task area candidate
mowingWeekArea:    cumulative weekly-area candidate
```

The area names and units remain inferences. The values are consistent with an
area unit and a stable implied task total, but neither square metres nor the
task-reset boundary is yet protocol-confirmed.

### 4.2 Area identity limitation

The captures prove only:

- one distinct `currentMowBoundary` value;
- one distinct singleton `partitionIds` list; and
- no observed transition between two configured area identifiers.

The correlation is compatible with one active area, but it does not prove
that `currentMowBoundary` is the same identifier namespace as
`partitionIds`. It cannot establish per-area progress or area ordering.

### 4.3 Opaque work-position value

Every captured `mapWorkPosition` is a fixed-length hexadecimal string and
changes with task progress. It is therefore structured binary state encoded as
text, not a directly usable polygon or coordinate list.

No byte-level semantic contract is currently justified. The field must remain
opaque until independently decoded or documented.

## 5. Route Visualization Decision

**Local route visualization: GO for design.**

A first visualization can show:

- the current local-map position;
- orientation;
- receive-ordered path segments;
- mowing versus returning or paused state coloring;
- gaps and out-of-order source timestamps; and
- current task progress alongside the route.

The first map must be explicitly labeled `local map`. It must not imply GPS
placement or cadastral accuracy.

### 5.1 Mowing-lane rendering

Simple center lines are directly supported. Visible mowing lanes require a
configured or evidence-backed cutting width. Each mowing path segment can then
be buffered by half that width.

The rendered band remains an estimate because:

- local coordinate scale is not yet formally confirmed;
- mower-state `Running` may include non-cutting transit;
- `action` and `subAction` semantics are not decoded;
- position accuracy and mower-body offset are unknown; and
- short gaps and out-of-order samples require explicit segmentation.

## 6. Per-Area Coverage Decision

**Manufacturer task progress: usable as a task-level diagnostic.**

**Per-area percentage: not yet evidence-ready.**

Two complementary future calculations are possible.

### 6.1 Manufacturer telemetry

Once multi-area evidence resolves identifier and reset semantics, retain:

- task percentage;
- current-task area;
- weekly area;
- active area or partition identity; and
- task and area transition timestamps.

This is the preferred source for manufacturer-defined completion progress.

### 6.2 Geometric coverage estimate

For each area polygon:

1. retain only evidence-backed cutting segments;
2. buffer the center line by half the cutting width;
3. union overlapping strips so they count once;
4. intersect the union with the area polygon; and
5. divide covered area by polygon area.

This can estimate actual spatial coverage and reveal missed strips or overlap.
It must be presented separately from manufacturer progress because the two
measure different concepts.

## 7. Missing Map Contract

The current Smart Home REST and MQTT evidence does not expose usable area
polygons, exclusion zones or a map-coordinate transform. `mapWorkPosition` is
not sufficient evidence for any of them.

The experimental `navimow_pro` source identifies private mobile-app protocol
paths for maps and ordered zone mowing. That protocol uses different
authentication and command boundaries and remains excluded from the current
OAuth/OpenAPI module.

The supported options are therefore:

1. wait for an official SDK map/zone API;
2. perform a separately approved read-only private-protocol research track; or
3. allow a user-provided local polygon calibration without claiming vendor map
   equivalence.

Option 3 is the safest independent route to an initial local visualization,
but it requires its own configuration and privacy design.

## 8. Storage And Symcon Contract

The existing public mower variables and Archive histories must remain
unchanged. No current variable is renamed, recreated or repurposed.

A later additive design may expose stable per-area summaries such as:

```text
AreaKey
ManufacturerProgress
EstimatedCoverage
EstimatedMowedArea
MowingDuration
LastCompletedAt
```

Area identity must come from a stable technical key, not a mutable display
name or installation-specific ObjectID. Percentage and area histories may use
Archive variables after update cadence and reset semantics are proven.

Raw route geometry is not a good fit for one Archive variable per sample. A
separate bounded task-track store and a reduced current-position projection
should be designed before implementation.

## 9. Current REST Command Status

### 9.1 Productive and live verified

| Command | Cloud mapping | Module status | Evidence |
|---|---|---|---|
| Dock | `Dock` | enabled | live verified, including Running to Docked |
| Pause | `PauseUnpause`, `on=false` | enabled | live Running to Paused verified |
| Resume | `PauseUnpause`, `on=true` | enabled | live Paused to Running verified |

All three preserve one-write, no-command-retry and later REST verification.

### 9.2 Known but not implemented

| Command | Current upstream status | Module decision |
|---|---|---|
| Start | official low- and high-level support; generic all-zone payload only | disabled pending one-shot transition evidence |
| Stop | official low-level enum and mapping remain present | disabled because terminal and task semantics remain unresolved |

The official SDK still maps Start and Stop to `StartStop` with boolean `on`.
The official Home Assistant integration exposes Start, Pause and Dock and has
no Stop user action. Generic Start still carries no zone, area, map or ordering
parameter.

The official Stop clarification issue remains open. It contains only the
original inquiry and the project follow-up; no maintainer response exists as
of 2026-08-20. Stop therefore remains fail-closed.

## 10. Readiness Matrix

| Capability | Decision | Missing evidence |
|---|---|---|
| current local position | GO for design | permanent-operation decision |
| receive-ordered path | GO for design | bounded task storage contract |
| center-line visualization | GO for design | UI and privacy design |
| mowing-lane estimate | CONDITIONAL GO | coordinate scale, cutting width, cutting-state classification |
| task percentage | GO as diagnostic | reset and task identity evidence |
| current-task area | CONDITIONAL | unit and reset semantics |
| weekly area | CONDITIONAL | unit and week-boundary semantics |
| active area identity | BLOCKED | multi-area transitions |
| per-area manufacturer percentage | BLOCKED | area mapping and progress attribution |
| geometric per-area coverage | BLOCKED | polygons, scale and cutting-state evidence |
| official map rendering | BLOCKED | map/polygon API |

## 11. Architecture Decisions

### AD-NAV-1334: Separate route evidence from map ownership

**Decision:** Treat local poses as route evidence without claiming an official
Navimow map.

**Rationale:** Coordinates are proven; polygons and transforms are not.

**Consequence:** A relative track can proceed before map retrieval is solved.

### AD-NAV-1335: Keep manufacturer and geometric progress distinct

**Decision:** Do not merge `mowingPercentage` with calculated strip coverage.

**Rationale:** Task completion and spatial union coverage represent different
measurements.

**Consequence:** Future UI and statistics label both sources explicitly.

### AD-NAV-1336: Require multi-area evidence before area variables

**Decision:** Create no per-area public variables from one observed partition.

**Rationale:** Identifier namespaces, transitions and reset semantics remain
unproven.

**Consequence:** Existing variables and user Archive logging remain stable.

### AD-NAV-1337: Keep private map protocols outside the current module

**Decision:** Do not import map endpoints or zone commands from the
experimental mobile-app protocol into the OAuth/OpenAPI module.

**Rationale:** Authentication, support, legal and session-impact boundaries are
different.

**Consequence:** Any such investigation requires a separate read-only gate.

### AD-NAV-1338: Preserve the command allowlist

**Decision:** Keep Dock, Pause and Resume enabled and Start plus Stop disabled.

**Rationale:** Current upstream mapping does not replace causal transition and
terminal-state evidence.

**Consequence:** This analysis sends no command and changes no productive
contract.

## 12. Decision And Next Step

**Local route and current-position design: GO.**

**Task-level progress diagnostics: GO for a bounded design.**

**Per-area statistics and official-map rendering: NO-GO pending evidence.**

The next SAEF step should be a multi-area task-semantics capture plan. It
should define a receive-only observation across a normal scheduled job that
uses at least two configured areas, correlate app-visible area transitions
with coordinate-free MQTT projections and perform no mower command.

In parallel, a separate map-source decision should compare official API
waiting, user-provided local polygons and a separately gated read-only private
protocol investigation.
