# 344 Path Map And Zone Area Statistics Design

**Case study:** Navimow native IP-Symcon module

**Status:** Architecture candidate; implementation gated by calibration evidence

**Date:** 2026-08-24

## 1. Product Goal

Provide two separate capabilities:

1. a map showing mowing tracks or stripes and the current local position;
2. per-zone statistics for actually mowed area and progress with an explicit
   denominator.

Neither capability changes REST authority or introduces MQTT commands.

## 2. Available Evidence

The receive-only location channel can supply local X/Y position, orientation,
source time and vehicle-state code. The task channel can supply progress,
subtotal and weekly area candidates, phase codes, delay state and privacy-safe
area correlations.

These channels have different cadence. They must be joined by bounded time
windows with explicit gaps, never by array index or assumed one-to-one rows.

## 3. Missing Evidence

A reliable map still requires:

- coordinate unit, origin, orientation and scale calibration;
- zone polygon or boundary geometry;
- evidence that coordinate frames remain stable across sessions and maps;
- at least two confirmed zone-to-correlation mappings;
- a rule for rain, charging and other interrupted path segments.

An exact percentage of actually mowed zone area additionally requires a known
zone-area denominator. A task percentage alone is a pass-progress candidate,
not proof of geometric coverage or a daily/weekly completion ratio.

## 4. Proposed Architecture

### Path Store

Use a dedicated bounded path-segment store rather than the task ledger or
public variables. Each segment contains timestamped local points, state and a
private area-correlation reference. Start a new segment on large time gaps,
out-of-order timestamps, coordinate discontinuity, zone change or transport
cleanup.

Apply distance and time downsampling, hard point and byte limits, and retain
the raw local coordinate data only in installation-private storage. The first
visualization can render an unrotated local-coordinate diagnostic map while
calibration remains unknown.

### Zone Statistics Reducer

Aggregate from retained pass summaries:

- observed area delta per correlated pass;
- maximum and final progress candidate;
- pass completion evidence;
- interruption and resume counts;
- first and last observation times;
- confidence and evidence source.

Avoid double counting subtotal resets. Weekly area is a plausibility check, not
a zone allocator. A zone percentage may be published only as one of:

- `passProgressPercent`, directly labelled as pass-local;
- `observedArea / configuredZoneArea`, after zone-area calibration;
- geometric coverage estimate from path buffering, with declared mower width,
  overlap model and uncertainty.

### Presentation

Keep the existing mower variables untouched so their archive identities remain
stable. A later map/statistics instance may expose a bounded HTML view and new
explicitly owned statistics variables. User-facing zone names belong to local
configuration mapped to private correlation handles; they are never embedded
in public fixtures or documentation.

## 5. Implementation Gates

1. Complete the Zone 2 and Zone 3 correlation observations from step 343.
2. Capture one coordinate-rich natural run per confirmed zone.
3. Determine coordinate scale and stability from repeated dock and path
   landmarks without publishing garden geometry.
4. Decide the percentage denominator and confidence model.
5. Implement offline path segmentation and zone aggregation with synthetic
   fixtures.
6. Publish and roll out disabled before any new live observation.

The next executable step is step 343 on the next natural run of one of the two
remaining zones. The map and area reducers can then be implemented against
evidence rather than guessed geometry.
