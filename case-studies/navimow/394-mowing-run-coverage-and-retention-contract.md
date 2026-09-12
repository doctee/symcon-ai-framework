# SAEF Step 394: Mowing Run, Coverage And Retention Contract

**Case study:** Navimow native IP-Symcon module

**Status:** Design contract accepted for offline implementation

**Date:** 2026-09-12

## 1. Purpose

This step defines how retained paths become bounded mowing-run and area
statistics without turning an undocumented MQTT inference into authoritative
device state.

## 2. Input Authority

The reducer may consume only an accepted Local Map scene whose geometry,
retained path and task statistics share the same accepted geometry revision.
It must reject or omit data when:

- the geometry fingerprint is invalid or mismatched;
- frame correlation is not approved;
- a path point has no unambiguous zone attribution;
- timestamps are non-monotonic or separated by more than 300 seconds;
- one local step exceeds 50 units; or
- configured calibration is invalid.

Only retained path segments with MQTT vehicle-state candidate code `Running`
contribute mowing distance, duration or area. This is evidence of likely
cutting, not proof that the blade was active.

Retained aggregates are selected by an analytics-contract fingerprint that
binds the accepted geometry revision, local-to-metre scale, cutting width,
raster resolution, statistics time zone, zone bindings and subarea polygons.
A change to any bound input starts a separate retained analytics revision and
cannot reinterpret or merge earlier aggregates.

## 3. Run Identity

A run is analytics-revision- and zone-bound. Its identity uses the retained
task-pass sequence when present, otherwise the transport-session sequence plus
first point timestamp. A run never crosses:

- a geometry revision;
- a physical calibration or statistics-time-zone revision;
- a zone-binding or subarea-geometry revision;
- a zone boundary;
- a task-pass boundary; or
- an unrelated transport session.

The reducer merges repeated projections by stable identity and maximum
aggregate values. Reprocessing the same retained scene therefore cannot
double-count a run.

## 4. Distance And Duration

Distance is the sum of accepted point-to-point local distances multiplied by
the configured `MetersPerLocalUnit` scale. Active duration is the sum of
accepted point-pair time differences.

Distance and duration are diagnostic because sparse positions can omit turns
or movement between messages.

## 5. Geometric Coverage Estimate

Coverage requires an explicit positive cutting width. With width zero, the
contract fails closed as `disabled-missing-cutting-width` and every area or
coverage result is `null`.

With calibration present, each accepted path pair is sampled into a bounded
raster corridor:

- sample cells are clipped to the attributed zone polygon;
- obstacle polygons are excluded;
- repeated cells inside the same run or day count once;
- a run coverage percentage uses the manufacturer-reported net zone area as
  denominator; and
- the result is always labelled an estimate, never a measurement.

`CoverageCellSizeMeters` controls resolution. The implementation stops after
250,000 samples and exposes truncation instead of silently under-reporting.

## 6. Period Statistics

The reducer retains daily unique-cell estimates for 400 days. The projections
are:

- area estimated today;
- area estimated in the current local-time week;
- area estimated in the current local-time month; and
- the latest run's distance, active duration, estimated area and estimated
  square metres per hour.

Week and month values are sums of daily unique-cell estimates. A cell mowed on
different days counts once per day. They are workload estimates, not the union
of all covered land in the period.

## 7. Mowing Recency

For every configured zone, the latest accepted Running point defines
`LastMowedAt`. The default policy is:

| State | Rule |
|---|---|
| Unknown | no accepted mowing evidence |
| Current | less than 7 days |
| Due | at least 7 and less than 14 days |
| Overdue | at least 14 days |

The thresholds are configurable but the critical threshold must be greater
than the warning threshold.

## 8. Optional Subareas

An optional subarea is a named polygon bound to exactly one public map zone and
the current accepted geometry revision. It receives daily, weekly and monthly
area estimates plus last-mowed and recency projections.

Subareas prepare later statistics for smaller regions. They do not alter the
manufacturer zone, mower schedule or task attribution. A map edit creates a
new geometry revision; old and new subarea evidence must never be mixed.

## 9. Interruption Semantics

Task-ledger interruption and resume counts may be projected. A reliable rain
interruption counter is not available because no stable cause field has been
observed. The public result remains `null` until separate evidence proves the
reason contract.

## 10. Retention And Privacy

The state is bounded to:

- 4 geometry revisions;
- 32 zones;
- 32 subareas;
- 400 daily rows;
- 256 runs;
- 512 KiB serialized state; and
- 2,048 scene points per reduction.

The retained analytics state contains fingerprints, aggregates and configured
labels, but no raw geometry, raw coordinates, credentials, topics, device IDs,
ObjectIDs, hostnames or installation paths.

## 11. Architecture Decisions

### AD-NAV-394-01: Keep progress and coverage separate

Manufacturer task progress and geometric track coverage answer different
questions and remain separately visible.

### AD-NAV-394-02: Require explicit physical calibration

The module must not invent blade width or coordinate scale. Missing cutting
width disables geometric area output.

### AD-NAV-394-03: Retain daily cells as aggregates, not coordinates

This permits period statistics and idempotency while bounding private location
retention in the analytics state.
