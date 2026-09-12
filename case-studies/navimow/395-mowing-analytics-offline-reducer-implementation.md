# SAEF Step 395: Mowing Analytics Offline Reducer Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Pure reducer implemented and verified offline

**Date:** 2026-09-12

## 1. Purpose

This step implements the contract from step 394 as a transport- and
Symcon-independent reducer.

## 2. Implementation

`MowingAnalyticsReducer` provides four public operations:

```text
initialState()
update(state, scene, observedAt, options)
project(state, geometryKey, now, options)
serializeState(state) / restoreState(encoded)
```

The reducer validates every input, policy and restored state. It groups
accepted Running segments into revision- and zone-bound runs, calculates
distance and duration, rasterizes optional cutting corridors, excludes
obstacles, deduplicates cells and merges the snapshot idempotently into a
bounded retained state.

The distribution and candidate copies are behaviorally identical apart from
their established namespaces.

## 3. Track Metadata Extension

The existing scene and retention pipeline now preserves the minimum metadata
required by analytics:

```text
passSequence
sessionSequence
vehicleStateCode
sourceTimestamp
pathLengthLocal
```

Legacy retained path state lacking these fields migrates conservatively to
neutral values. It remains renderable but cannot gain stronger mowing evidence
than its source supports.

## 4. Failure Behavior

- Invalid current input throws and leaves the previously serialized state
  untouched.
- Invalid stored state is rejected instead of partially recovered.
- Missing cutting width returns explicit `null` coverage values.
- Geometry mismatch returns a no-data projection for the requested revision.
- Sample and serialization limits stop the operation visibly.
- Replaying an unchanged retained scene does not increase distance or area.

## 5. Focused Evidence

Synthetic tests prove:

- metadata survival through retained path projection;
- distance, duration, area and area-performance output;
- zone and subarea daily coverage;
- task interruption and resume counts;
- explicit absence of a rain-reason claim;
- 7- and 14-day recency transitions;
- idempotent repeated reduction;
- width-zero fail-closed behavior;
- stable serialize/restore round trips; and
- rejection of corrupted retained subarea timestamps.

## 6. Architecture Decisions

### AD-NAV-395-01: Keep the reducer implementation-local

No second provider currently shares this exact task, geometry and mower-state
contract. The reducer remains inside Navimow until reuse is demonstrated.

### AD-NAV-395-02: Preserve raw paths outside analytics state

The path store owns bounded coordinates. The analytics store keeps only
aggregates, preventing duplicate private geometry retention.

### AD-NAV-395-03: Prefer deterministic raster estimation

A bounded cell model is inspectable, testable and idempotent. More advanced
geometry libraries remain unnecessary until live accuracy evidence justifies
their additional dependency and complexity.

## 7. Gate Result

| Gate | Result |
|---|---|
| Pure reducer implementation | PASS |
| Retention migration | PASS |
| Negative-state validation | PASS |
| Productive Symcon integration | OPEN in step 397 |
| Publication or live use | CLOSED |
