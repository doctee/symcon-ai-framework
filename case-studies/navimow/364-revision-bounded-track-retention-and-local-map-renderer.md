# 364 Revision-Bounded Track Retention And Local Map Renderer

**Case study:** Navimow native IP-Symcon module

**Status:** Offline candidate and private visual overlay verified; productive
runtime integration remains closed

**Date:** 2026-08-27

## 1. Scope And Safety Boundary

This step extends the accepted offline local-map scene from step 363 with:

- bounded path retention tied to the exact geometry revision;
- a deterministic, active-content-free SVG renderer;
- explicit presentation options for zone-label visibility;
- explicit station-state presentation without deriving authority in the
  renderer;
- focused retention, renderer, privacy and full Navimow offline checks;
- one ignored private visual projection of the retained short path sample.

It performs no network, authentication, Symcon, MQTT or mower operation. The
candidate is not loaded by the productive distribution. No public artifact
contains private coordinates, garden geometry, aliases, identifiers or live
installation metadata.

## 2. Reuse Before Extend

RevisionBoundedTrackStore consumes the established
LocalMapSceneProjector scene contract. It does not decode MQTT, correlate tasks,
classify geometry or calculate zone statistics again.

LocalMapSvgRenderer consumes the same scene contract. It does not own map
storage, path retention, vehicle state or zone attribution. Presentation
options remain local to the case-study candidate; no new SAEF helper or public
framework API is introduced.

## 3. Retention Contract

The track store accepts only scenes where:

- the geometry revision is accepted;
- frame and path compatibility are already proven;
- the path status is included;
- every point has normalized local coordinates, reception time and
  attribution.

Retained points carry their geometry key. The store is bounded to:

- four geometry revisions;
- 64 segments;
- 2048 points;
- 512 KiB serialized state.

Duplicate identity is derived from geometry revision, normalized local
coordinates and reception time. The oldest complete geometry revision is
evicted when the revision limit is exceeded. Point and segment evictions are
counted explicitly.

The focused test discovered and corrected a format-boundary defect: source
scene points contain nested attribution, while normalized retained points
contain a flat zone key and attribution source. Input normalization and
retained-state validation are now separate operations.

## 4. Renderer Contract

The renderer returns one bounded inline SVG containing:

- zone polygons and optional labels;
- excluded-area outlines;
- retained path polylines;
- diagnostic point markers;
- charging-station pose;
- latest retained mower position.

The SVG contains no script, external href, foreign object or remote asset. Its
maximum serialized size is one MiB.

Excluded areas use a low-opacity fill and dashed outline. They remain visible
without dominating the zone geometry. Strictly outside path points are orange;
ambiguous points are red and unknown task-zone points are violet.

## 5. Presentation Options

The renderer validates two explicit options:

- hiddenZoneSequences: a unique bounded list of zone sequences whose labels
  are hidden while their geometry remains visible;
- stationState: docked, docking, undocked or unknown.

The station colors are:

- green for docked;
- amber for docking or returning;
- slate for undocked;
- petrol for unknown.

The station pose uses the map-provided direction after conversion from the
local Cartesian frame to the SVG frame.

The renderer does not infer station state from MQTT location codes. A future
runtime adapter must map fresh REST-authoritative vehicle state to the
presentation option. MQTT remains receive-only supporting evidence.

## 6. Private Visual Verification

The ignored private runner projected one retained short coordinate window onto
the accepted map revision. The coordinate-free result confirms:

- 25 retained points over 144 seconds;
- 22 strict expected-zone attributions;
- three strict outside points that separate private analysis classifies as
  boundary-adjacent;
- zero points inside an excluded area;
- one hidden non-productive zone label;
- an explicit undocked station presentation for the historical active path;
- no productive runtime approval.

The three outside points remain outside in the data model. The renderer marks
them but does not snap them across the boundary. Point counts and path length
remain unsuitable as mowed-area percentages.

The final SVG and PNG are private mode-0600 evidence. Visual inspection proves
readable labels, non-overlapping map layers, a direction-aware station, subtle
excluded areas, visible diagnostics and a visible latest-position marker.

## 7. Mutable Map Lifecycle

Every retained segment stays attached to the geometry revision used for its
classification. A boundary or excluded-area change in the official app creates
a new candidate geometry revision. Old path data may remain historical but is
not overlaid on the changed map until an explicit migration or reconciliation
decision exists.

Label visibility is a presentation setting and does not change the geometry
revision. Boundary, obstacle or station-pose changes do change the revision.

## 8. Architecture Decisions

### AD-NAV-364-01: Keep retention downstream of scene validation

**Decision:** Retain only accepted, compatible scene paths.

**Reason:** Storage must not make rejected geometry or frame evidence appear
valid later.

### AD-NAV-364-02: Bind every retained path to a geometry revision

**Decision:** Store the geometry key on every segment and evict complete old
revisions when the revision limit is exceeded.

**Reason:** App-driven map changes must not silently reclassify historical
coordinates.

### AD-NAV-364-03: Keep state authority outside the renderer

**Decision:** Require an explicit station-state option and reserve productive
mapping for fresh REST state.

**Reason:** A view component must not elevate diagnostic MQTT codes into public
state authority.

### AD-NAV-364-04: Hide labels without hiding geometry

**Decision:** Label visibility is keyed by validated zone sequence and does not
remove the polygon.

**Reason:** Presentation preferences must not change attribution or revision
semantics.

### AD-NAV-364-05: Keep coverage inference closed

**Decision:** Render the path but do not convert point count, path length or a
buffered line into mowed-area percentage.

**Reason:** Cutting width, overlap, skipped strips, obstacle clipping and sample
density remain uncalibrated.

## 9. Verification

The complete Navimow offline check passes with the lock-identical canonical
Composer toolset selected through COMPOSER_VENDOR_DIR. It includes all existing
REST, MQTT, parser, diagnostics, geometry, scene, distribution and lifecycle
tests plus the new renderer and retention tests, PHPCS and PHPStan.

Focused source hashes:

    LocalMapSvgRenderer.php
    a654f8142d827ac4c0a466c38054b5ee8cf512bc411c943e0e9f696a60019bfb

    RevisionBoundedTrackStore.php
    b4dcf8585087a7a8e6f66b413b5793a53f5575a05042f85e7d824415801bd9a0

    local-map-svg-renderer.php
    73d481ff22fb3ca85fe449cb74489f792f515c0afdcc1ab6a7af11a9b1f9ac35

    revision-bounded-track-store.php
    d302b4289380fe8cf00478659be0e49ab3e7008817f7f9f5c070d627b0f85e2b

The hashes above precede only this documentation addition; no source file is
changed by documenting the result.

## 10. Gate Decision And Next Step

The offline retention and renderer gate passes.

The following gates remain closed:

- productive module integration;
- persistent Symcon-owned path storage;
- automatic private-map refresh;
- automatic geometry-revision acceptance;
- Symcon visualization publication;
- inferred geometric coverage percentage;
- standalone-module publication and live rollout.

The recommended next SAEF step is a runtime-integration readiness design. It
should define ownership, storage location, configuration, refresh cadence,
REST-to-station-state mapping, private-map revision workflow, visualization
surface and rollback without yet activating any productive runtime behavior.
