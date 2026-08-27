# 363 Local Map Scene And Zone Statistics Offline Prototype

**Case study:** Navimow native IP-Symcon module

**Status:** Offline scene prototype and private overlay verified; renderer and
runtime integration remain closed

**Date:** 2026-08-27

## 1. Scope And Safety Boundary

This step implements the offline local-map and zone-statistics prototype
approved after step 362. It composes previously separate geometry, path and
task-statistics candidates into one revision-bound scene contract.

The implementation:

- uses only synthetic public fixtures and retained ignored private evidence;
- performs no network, authentication, Symcon, MQTT or mower operation;
- is not loaded by the productive Navimow distribution;
- creates no Symcon variable, archive record or visualization;
- publishes and pushes nothing.

Exact garden geometry, aliases, task keys, areas, timestamps and current local
position remain private.

## 2. Reuse Before Extend

The prototype composes existing SAEF and Navimow building blocks:

- SAEF_CreateConfigurationHash() supplies the canonical revision key;
- MapGeometryReducer supplies bounded local-map geometry;
- MqttPathSegmenter supplies bounded task-correlated path segments;
- ZoneStatisticsReducer supplies pass and zone statistics.

Only LocalMapSceneProjector is new. It remains a Navimow case-study candidate,
not a public SAEF helper. Its purpose is to join the established contracts and
enforce their compatibility gates; it does not duplicate decoding,
segmentation, statistics or configuration hashing.

## 3. Scene Contract

LocalMapSceneProjector::build() accepts:

1. one reduced navimow-local-map geometry projection;
2. one bounded uncalibrated-local path projection;
3. one bounded zone-statistics projection;
4. explicit private zone bindings;
5. current, accepted, path and statistics geometry revision keys;
6. the explicit frame-correlation approval.

It returns a bounded navimow-local-map-candidate scene containing:

- fitted local viewport bounds and padding;
- charging-station pose;
- zone rings, labels and reported net areas;
- obstacle rings and their ownership diagnostics;
- strict zone-boundary crossing diagnostics;
- revision-compatible path segments;
- per-point task or geometry attribution;
- revision-compatible pass statistics and denominator status.

The serialized scene is limited to one MiB. Zone, obstacle, segment, point,
label and coordinate inputs are independently bounded and validated.

## 4. Revision Contract

The current geometry key is recomputed through the existing SAEF
ConfigurationHash helper. A caller-provided key that does not match the
projection is rejected.

A scene has two revision states:

- accepted: current and accepted geometry keys are equal;
- candidate: the geometry changed and requires reconciliation.

Path points are included only when:

- the geometry revision is accepted;
- the frame-correlation gate is approved;
- the path revision equals the current geometry revision.

Statistics are included only when their revision equals the accepted current
geometry. A candidate revision therefore retains its own geometry for review
but emits neither old path segments nor old zone statistics.

This proves replacement without mixing observations from different boundary or
obstacle revisions. Historical storage must retain the geometry revision key
with every future path or statistics segment.

## 5. Attribution And Overlap

Zone attribution follows this order:

1. a known task-zone key is authoritative;
2. geometry checks whether the task attribution is plausible;
3. when task evidence is absent, exactly one containing zone is accepted as a
   geometry fallback;
4. multiple containing zones produce ambiguous;
5. no containing zone produces outside;
6. an unknown task-zone key fails closed and is not replaced by geometry.

An ambiguous point receives no zone key and cannot be counted twice. A task
point inside an overlapping region keeps its task zone while recording the
number of geometric candidates.

Obstacle ownership is evaluated separately. A representative point must match
exactly one zone for single-zone; multiple or zero matches remain explicit
diagnostics.

## 6. Area And Progress Semantics

Each zone carries the manufacturer-reported net area from the geometry
projection. The scene marks a statistics denominator as compatible only when
the configured statistics area equals that reported area within a narrow
numeric tolerance.

The scene retains the existing percentage boundaries:

- task progress remains a manufacturer task-progress candidate;
- observed-area percentage remains pass area divided by reported net zone area;
- geometric stripe coverage remains not-implemented.

No path length, point count or buffered line is presented as mowed percentage.

## 7. Synthetic Evidence

The public synthetic fixture contains:

- three assigned zones and one unassigned zone;
- one deliberately overlapping zone pair;
- uniquely owned obstacles;
- task-attributed, ambiguous and outside path samples;
- pass statistics with and without valid denominators;
- an accepted revision and a changed candidate revision.

The executable test proves:

- viewport fitting includes geometry, station and retained path;
- the overlap pair is detected;
- task evidence resolves a point in the overlap;
- geometry ambiguity never double-counts;
- obstacle ownership is unique;
- reported-area denominator gates work;
- an unassigned zone remains valid without statistics;
- a changed revision drops old path and statistics;
- a failed frame-correlation gate drops the path;
- a false geometry fingerprint is rejected;
- no private installation marker exists in the public scene.

## 8. Private Overlay Result

The ignored private runner composes:

- the retained real geometry projection;
- the accepted private alias and obstacle contract;
- retained task-pass summaries;
- the one retained current docked position.

The private result confirms:

- PHP and Python derive the same canonical geometry fingerprint;
- the expected obstacle distribution is present;
- every obstacle belongs to exactly one zone;
- the known boundary overlap remains explicit;
- productive retained pass summaries use reported net-zone denominators;
- the docked point is included and lies outside the mowing zones as expected;
- the scene remains a prototype and does not approve runtime integration.

Earlier pilots retained aggregate point counts and path lengths, but not the
complete historical coordinate stream. The private prototype therefore does
not reconstruct mowing stripes. It records this as an evidence limitation
instead of manufacturing coordinates.

## 9. Mutable Map Lifecycle

Changes made in the official app to boundaries or excluded areas change the
canonical geometry key.

The productive design must later provide a bounded refresh policy with this
state transition:

    Accepted revision
      -> changed projection observed
      -> Candidate/Stale
      -> offline reconciliation
      -> atomic acceptance as new revision

Path and area statistics remain attached to their original revision. They may
be compared as historical periods but must not be geometrically overlaid onto a
different revision without an explicit migration method.

The current prototype neither schedules undocumented map calls nor defines
automatic acceptance.

## 10. Architecture Decisions

### AD-NAV-363-01: Compose existing candidates

**Decision:** Keep geometry reduction, path segmentation, pass statistics and
configuration hashing in their existing owners.

**Reason:** The new requirement is contract composition, not another decoding
or statistics implementation.

### AD-NAV-363-02: Fail closed on revision mismatch

**Decision:** Show candidate geometry without path or statistics from another
revision.

**Reason:** A plausible-looking overlay would otherwise silently assign old
observations to changed boundaries.

### AD-NAV-363-03: Preserve task-first attribution

**Decision:** Task identity wins over geometry while geometry supplies
plausibility and fallback diagnostics.

**Reason:** Real zone boundaries can overlap.

### AD-NAV-363-04: Keep geometric coverage unimplemented

**Decision:** Do not infer mowed area from path geometry in this step.

**Reason:** Cutting width, overlap, gaps, obstacle clipping and sampling quality
remain uncalibrated.

### AD-NAV-363-05: Retain provider separation

**Decision:** Keep the scene Navimow-local. A later renderer may consume a
provider-neutral presentation contract, but OwnTracks remains a separate WGS84
adapter.

**Reason:** Shared presentation does not establish a shared coordinate system.

## 11. Verification

Public verification includes:

- PHP syntax checks;
- the focused local-map scene test;
- all existing Navimow MQTT, REST, geometry, path and distribution tests;
- PHPCS for the new candidate;
- PHPStan for the new candidate and existing Navimow production cohort;
- fixture and privacy checks;
- private create-once mode-600 evidence validation.

Public source hashes:

    LocalMapSceneProjector.php
    e055d53498d5739f77e42ad6cb7c112a09d84ee7a9f13030141a544cfdef80b7

    local-map-scene-prototype.php
    53248dbd8c97937873889e44e4a7652cb6e12ff6efafc9dba4a4401b45a610e3

    local-map-scene-synthetic.json
    af624a509bcf60e718c075a018d07bc4f1c68eaba01f1c57587b4087e724e053

The complete Navimow offline check passes with the lock-identical canonical
Composer toolchain selected through COMPOSER_VENDOR_DIR. No dependency is
installed or copied into the isolated worktree.

## 12. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Synthetic scene composition | **PASS** |
| Viewport fitting | **PASS** |
| Obstacle layer and ownership | **PASS** |
| Task-first overlap resolution | **PASS** |
| Reported-area statistics denominator | **PASS** |
| Mutable revision isolation | **PASS** |
| Private real-geometry overlay | **PASS** |
| Historical stripe reconstruction | **NO-GO; coordinates not retained** |
| Geometric coverage percentage | **NO-GO** |
| Productive map refresh | **NO-GO** |
| Symcon variables and archives | **NO-GO** |
| Productive renderer integration | **NO-GO** |

The next recommended SAEF step is an offline renderer and bounded retention
contract. It should render the synthetic scene first, define how future
coordinate-rich segments are retained with their geometry revision, and keep
the Navimow-local and OwnTracks-WGS84 adapters separate.

A new natural receive-only position observation is useful only after that
retention contract exists; otherwise another pilot would again produce
aggregate evidence without the coordinate stream required for mowing paths.
