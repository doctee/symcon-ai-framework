# OwnTracks Position Map Architecture Comparison

**Status:** Architecture recommendation with synthetic offline-core evidence

**Date:** 2026-08-27

## 1. Current Baseline

The installed map is owned by a healthy `OwnTracksMap` instance. It exposes an
HTML iframe through a local hook and renders point features with a modern
OpenLayers API. Configuration enables automatic fitting and contains three
visible `OwnTrackData` sources plus one `ExternalData` path projection/anchor.
The latter is not an independent tracker source.

The bounded response inspection found point/vector and auto-fit behavior, but
no line or polygon geometry API and no explicit accuracy visualization. The
response references remote resources. Their provider, request contents,
license and location-privacy effect require a separate decision before a new
renderer is selected.

The selector-driven path behavior is also documented in the associated
Symcon Community thread: a selected tracker/date supplies archived positions,
the path is represented by timestamp-labelled points, and the external-data
instance supplies the start point for centering. The current snapshot had no
active configured place list and exposed no connecting-line API.

## 2. Options

| Option | Description | Strengths | Risks and gaps | Decision |
| --- | --- | --- | --- | --- |
| A. Extend the installed map module | Add tracks and quality behavior inside the existing `OwnTracksMap` owner. | Preserves one UI and existing source/selector behavior. | Couples design to a provider module, changes the rollback owner, repeats property mutation/`ApplyChanges()` for path switching, and does not validate a renderer boundary. | Reject for the first pilot. |
| B. Build a monolithic case-study map | One private renderer reads the three live instances and archives directly. | Small initial implementation and easy offline prototyping. | Mixes source mapping, archive rules, quality policy and UI; reuse evidence remains unclear. | Accept only as a disposable prototype, not as target architecture. |
| C. Keep an OwnTracks adapter and renderer boundary inside the case study | A provider-owned adapter emits the local candidate contract; a separate case-study renderer consumes only that contract. | Tests the exact boundary anticipated by Navimow 351/352, keeps WGS84 rules explicit and permits parallel rollback. | Two local components and fixtures are needed; no framework reuse can be claimed yet. | Recommended design. |
| D. Create a public SAEF track-map component now | Publish common contract, segmentation and renderer APIs before implementation. | Maximizes immediate apparent reuse. | Violates Reuse Before Extend; Navimow geometry and OwnTracks archive semantics are not both stable. | Explicitly deferred. |

## 3. Recommended Ownership

The first offline implementation remains under this case study:

```text
Existing OwnTrackData instances and Archive Control (read-only)
                         |
Selection variable ------+  selected source + bounded window
                         |
                         v
OwnTracks WGS84 adapter candidate
  - existing Ident mapping
  - bounded archive reads
  - source/receive time handling
  - temporal accuracy attribution
  - WGS84 validation and geodesic strategy
                         |
                         v
Case-study-local track renderer
  - per-source layers
  - markers, segments, freshness and uncertainty
  - complete selected-day fit bounds
  - borderless pan/zoom surface and explicit fit-all control
  - ETA overlay consuming an explicit target/authority projection
  - responsive/touch presentation
  - explicit tile-provider boundary
  - point-only and optional segmented-line modes
```

The adapter does not own Symcon objects, logging or visualization. Its local
candidate is exercised only against synthetic Symcon function fakes; no live
archive is contacted. The renderer uses the same fixtures offline before any
live object is proposed.

## 4. Renderer Comparison Boundary

Renderer selection is intentionally not finalized by the inventory. A future
offline comparison should evaluate at least:

- OpenLayers, because the current map already demonstrates it in the target
  client environment;
- Leaflet, as a smaller point/track-oriented alternative; and
- a no-external-tile diagnostic mode for privacy and offline fallback.

The comparison must use the same synthetic contract fixtures and evaluate:

- three independent marker and track layers;
- uncertainty display;
- long-gap segmentation;
- fit behavior without navigation jumps;
- touch tooltips and fullscreen resize;
- library size and update ownership;
- tile/provider attribution and licensing; and
- behavior when remote resources or tiles are unavailable.

No library is added to SAEF until this comparison and a publication gate are
complete.

### Performance boundary

The first renderer comparison uses the same demand-driven contract:

- exactly one selected historical source per request;
- independent archive-record and rendered-point limits;
- timestamp-point mode as the compatibility baseline with a strict marker
  budget because many interactive point features can dominate client cost;
- optional line geometry generated only for visible, quality-accepted
  segments and without one marker per observation by default;
- stale request cancellation through a generation token; and
- no live map-property rewrite or `ApplyChanges()` on selection changes.

Profiling must compare one-day and multi-day synthetic windows on desktop and
mobile-sized clients. A renderer is rejected if navigation or selection work
scales with the complete retained history rather than the bounded result.

Fit-all is the exception to render-budget sampling: its bounds are reduced
from the complete valid selected-day input before rendering. This keeps the
camera correct without instantiating one interactive feature per archive
record.

ETA calculation stays upstream of presentation. The renderer receives a
bounded status projection and never calls a routing, geocoding or place
service itself. The provider authority class is selected in
`09-provider-decision.md`; activating or operating such a service remains a separate privacy,
network and publication decision.

## 5. Reuse Before Extend Decision

Repository searches found reusable archive-processing rules and WGS84 input
validation in existing SAEF artifacts, but no proven general track renderer or
geodesic track helper. This phase reuses the rules and validation concepts; it
does not add a convenience wrapper or public helper.

The future extraction test is evidence-based:

1. the OwnTracks adapter and local renderer work against synthetic and bounded
   private evidence;
2. the Navimow adapter independently proves its local coordinate contract;
3. both need the same renderer behaviors without importing each other's
   distance or calibration rules; and
4. duplication is concrete enough to justify the smallest common API.

Until then, commonality remains a documented architecture candidate.

## 6. Parallel Pilot and Rollback

No future pilot may replace the installed map in place. It should create a
separate owner and a separate visualization entry after native visualization
placement has been inventoried and authorized.

The rollback unit is the new entry and its exclusively owned objects. The
installed `OwnTracksMap`, its map variable, local hook, two existing links,
three OwnTracks sources, external path projection, selection control,
credentials and all archive contracts remain unchanged throughout the pilot.

This boundary makes rollback independent of archive repair, module downgrade
or source reconfiguration.
