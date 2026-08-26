# 351 Provider Neutral Track Map And Zone Calibration Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Architecture decision; geometry and runtime gates remain open

**Date:** 2026-08-26

## 1. Product Boundary

The planned map must support two different concerns without coupling them:

1. provider-neutral presentation of current positions and timestamped tracks;
2. Navimow-specific correlation of local mower positions with task passes and
   mowing zones.

A future replacement for the OwnTracks module map with three tracker sources
is the second expected consumer of the presentation layer. That use case makes
a Navimow-only renderer the wrong ownership boundary.

## 2. Reuse Decision

The existing Navimow prototype remains implementation-specific while its
correlation semantics mature. A shared public SAEF map API is not introduced
yet.

When the OwnTracks workstream starts, both consumers should validate a small
common track contract:

- privacy-safe source key;
- observation timestamp;
- coordinate reference identifier;
- position and optional accuracy;
- movement or device state;
- segment and layer metadata;
- bounded retention and explicit freshness.

Adapters remain provider-owned:

- Navimow maps local X/Y, vehicle state, transport session and anonymous task
  correlation into the common contract;
- OwnTracks maps WGS84 latitude/longitude, tracker identity, accuracy and
  optional activity metadata into the same contract.

The segmenter must receive a coordinate-system-specific distance strategy:
Euclidean local distance for Navimow and geodesic distance for OwnTracks. It
must not apply local-coordinate thresholds to WGS84 degrees.

After both adapters and the renderer prove the same stable need, the common
contract and renderer may move into a dedicated SAEF track-map component.

## 3. Navimow Zone Geometry Sources

Zone boundaries are accepted in this priority order.

### 3.1 Manufacturer Geometry

Preferred evidence is an authenticated read-only map payload containing zone
or partition polygons in the same coordinate frame as location telemetry.
Before use, the implementation must prove:

- stable polygon identity without publishing raw identifiers;
- coordinate-frame equality with the location channel;
- closed, non-self-intersecting bounded rings;
- repeatability across sessions and module restarts;
- explicit handling of holes and excluded islands.

No such payload has been proven yet.

### 3.2 Locally Digitized Geometry

If manufacturer polygons remain unavailable, the user may define private zone
polygons on a diagnostic map. The polygons are installation configuration and
map anonymous task-correlation keys to local geometry and local display names.

Before digitizing against a geographic base map, the Navimow local frame must
be calibrated. At least three non-collinear control-point pairs should map
repeatable local mower positions to known map positions. A fitted transform
must report residual error and be rejected when the frame changes or error
exceeds the configured tolerance.

Useful control points include the charging station and two independently
observable landmarks. Track points alone do not establish scale, rotation and
translation reliably.

### 3.3 Inferred Envelopes

Repeated mowing tracks may form a diagnostic occupancy envelope. Such an
envelope is never an authoritative zone boundary because mowing lanes omit
edges, obstacles, no-go islands and weather- or battery-interrupted areas.
It may highlight calibration errors or help the user digitize a polygon, but
must not silently become the area denominator.

## 4. Area And Percentage Contract

Zone-area percentages require one explicit denominator:

- manufacturer-reported configured zone area; or
- area of a validated calibrated local polygon in known metric units.

Until then the module may show only pass-local progress and observed area
deltas. Path length, convex hull and buffered stripes are diagnostic estimates,
not actual mowed-area proof. A future geometric coverage calculation must
declare mower width, overlap model, clipping polygon and uncertainty.

## 5. Presentation Layers

The renderer should support independent layers rather than one merged payload:

- current positions with freshness and source styling;
- segmented historical tracks;
- optional Navimow zone polygons and excluded areas;
- optional progress or coverage overlays;
- per-source visibility and time-window controls.

Three OwnTracks instances can therefore share one map while retaining separate
markers, tracks, freshness and visibility. Navimow-specific pass or zone data
never enters the OwnTracks adapter.

## 6. Privacy And Storage

- Real garden geometry, home coordinates, tracker identities and movement
  histories remain private installation data.
- Public fixtures use synthetic geometry only.
- Raw high-frequency tracks require bounded private storage and retention.
- Public variables should expose summaries or owned presentation output, not
  unbounded coordinate arrays.
- Map tiles and external geocoding require a separate privacy and provider
  decision.

## 7. Next Evidence Steps

1. Statically and privately search captured or newly observed read-only map
   payloads for bounded polygon candidates.
2. Verify whether the local coordinate frame is stable across at least two
   sessions using the dock and repeated landmarks.
3. Record three private noncollinear control-point pairs and fit a transform
   offline.
4. Obtain configured zone areas from manufacturer evidence or private user
   configuration.
5. Start a separate OwnTracks case study that inventories the three instance
   contracts and archive cadence without changing the live map.
6. Compare both consumers and then decide the smallest shared track-map API.

Runtime map integration remains blocked until the Navimow calibration and
denominator gates pass. This is a data precondition, not a missing publication
authorization.
