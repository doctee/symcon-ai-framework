# OwnTracks Position Map Requirements

**Status:** Requirements candidate; implementation gate closed

**Date:** 2026-08-27

## 1. Goal

Provide one position map for three configured OwnTracks tracker sources while
keeping source identity, freshness, accuracy and history independently
observable. The design should be provider-neutral at the presentation boundary
without making the OwnTracks adapter itself provider-neutral.

The first implementation, if separately authorized, remains inside this case
study until reuse has been demonstrated.

## 2. Source Boundary

The initial input set is exactly three healthy `OwnTrackData` instances. Each
instance remains the owner of its received tracker state. The adapter reads
the existing variables and raw archive without changing their configuration.

The legacy map also includes one `ExternalData` instance. It is not a fourth
tracker source. The replacement preserves its configuration only for rollback
compatibility and does not offer it as a browser source or mix it into an
OwnTracks selection or fit-all extent.

The associated
[Symcon Community discussion](https://community.symcon.de/t/modul-owntracks-anwesenheitserkennung-und-live-tracking/126972?page=10)
documents the established pattern: one tracker and date are selected, the
archived `Position` values become timestamp-labelled map points, and an
`ExternalData` instance provides the start point used for centering. The new
map may replace the implementation mechanism, but not that behavior.

Tracker display names, topics, instance names and configured place identities
are installation-private configuration. The map uses a case-study-owned opaque
source key and a separately configured private label.

## 3. Functional Requirements

### 3.1 Current positions

The map must:

- show at most one current marker per configured source;
- preserve per-source color, icon, visibility and label configuration;
- show source freshness without treating an old value as a current position;
- expose observation time separately from archive reception time;
- communicate horizontal accuracy and whether it is direct or carried
  forward from a separate variable; and
- reject malformed or out-of-range WGS84 coordinates.

### 3.2 Historical tracks

The map must:

- allow a controllable selection variable to choose one of the three tracker
  sources for historical path presentation;
- accept an explicit date or bounded start/end window for that selection;
- read only a bounded configured time window and record limit;
- keep each source in an independent layer;
- use the current three-source overview as the default point-and-time mode;
- use one connected line with sampled timestamps as the only historical
  `Path` presentation, with the same quality and gap rules;
- display bounded direction markers as part of each path segment;
- order candidate points by OwnTracks observation time, with deterministic
  handling of equal and out-of-order timestamps;
- segment tracks across configured time gaps, delayed-upload boundaries,
  implausible jumps or unacceptable accuracy;
- retain the distinction between missing data, stationary duplicate
  coordinates and an intentionally filtered point; and
- never serialize an unbounded history into a public Symcon variable.

### 3.3 Interaction

The renderer should support:

- per-source visibility;
- a bounded time-window selector and the existing source-selection behavior;
- direct mouse, touch and trackpad pan and zoom;
- an explicit fit-all control that uses every valid observation of the
  selected source and local calendar day, including observations omitted from
  the rendered marker budget;
- initial fit-all without mandatory refitting after every data refresh;
- marker and track inspection suitable for desktop and touch clients; and
- an explicit no-data, stale-data and partial-history state.

Every rendered point remains inspectable even when its persistent timestamp
label is omitted by the responsive label budget. The interaction follows the
existing SAEF heatmap/forecast tooltip pattern:

- pointer hover shows a transient tooltip on desktop;
- tap pins the same tooltip on touch clients;
- tapping another point replaces it, while tapping the free map closes it;
- a touch-pinned tooltip closes automatically after four seconds;
- the tooltip is positioned against and clamped to the complete map container,
  not an edge marker, so first and last points cannot clip it; and
- tooltip content is limited to observation time and quality metadata. It does
  not expose raw coordinates, tracker identities or private target labels.

The selection variable is a controllable input. A future Symcon integration
must use its action contract through `RequestAction()` and must not bypass it
with a renderer-owned direct value write.

The implementation must define responsive and fullscreen behavior before a
visualization gate is opened.

The map tile is borderless: the map surface fills the complete visualization
content box without an internal title, margin, padding or decorative frame.
Controls and ETA are compact overlays inside safe touch insets. Container and
fullscreen size changes must update the map viewport without requiring a page
reload.

### 3.4 Performance

Historical path work must be demand-driven:

- load only the selected source and requested bounded window;
- never pre-load all retained histories for all three sources;
- enforce both a record limit and a rendered-point budget;
- report partial results when the archive limit is reached;
- keep archive retrieval, normalization and rendering separate so a changed
  selection can cancel or supersede stale work;
- use deterministic simplification or sampling only when its method, retained
  endpoints and removed-point count are visible in diagnostics;
- use a bounded cache keyed by source, window, quality policy and archive
  watermark where profiling proves it useful; and
- avoid rewriting the installed map configuration or calling its
  `ApplyChanges()` merely to switch a path.

Timestamp-point mode is the compatibility baseline, but can be the most
expensive mode because every visible observation becomes an interactive
feature. It therefore has its own strict marker budget. A segmented-line mode
may reduce feature count without creating a marker for every observation.
Lines, sampled timestamp markers and accuracy overlays are independently
switchable so mobile clients do not pay for features they do not display.
Tooltip inspection uses one reusable overlay and existing vector hit detection;
it must not create one DOM node per point or trigger another archive read.

### 3.5 ETA to the next target

The map displays ETA only when the next target has an explicit, current and
privacy-safe authority. The map must not infer a destination from movement
direction, a historical path endpoint or a private place name.

The ETA contract distinguishes:

- a fresh external route estimate, including its opaque authority key and
  calculation time; and
- an optional geodesic observed-speed fallback that is visibly marked as
  diagnostic and not route-aware.

ETA eligibility is local to the two configured target locations. A target is
eligible only while the latest quality-approved current position is at a
geodesic distance of strictly less than `100000` metres from that target. If
neither target is eligible, ETA is `unavailable`; the resolver must not select
or retain a farther target. The exact `100000`-metre boundary is outside the
eligible range. This limit affects ETA only: it must not hide the selected-day
path or change fit-all.

Every ETA reports its basis time, remaining distance, strategy, freshness and
availability state. Missing target, stale route evidence, insufficient speed,
poor current accuracy or a historical selection must yield an explicit
unavailable/stale state rather than a fabricated arrival time.

The target label and coordinates remain installation-private. Public fixtures
use synthetic target keys and geometry only.

## 4. Coordinate and Distance Requirements

OwnTracks input is WGS84 (`EPSG:4326`): latitude and longitude are angular
geodetic coordinates. Distance and jump checks must use a declared geodesic or
great-circle strategy appropriate for WGS84. They must not use Euclidean
distance directly on degrees.

Navimow local `postureX`/`postureY` values are not accepted by this adapter.
They have a separate local coordinate reference, distance strategy,
calibration state and privacy boundary. No implicit local-to-WGS84 transform
is permitted.

## 5. Time and Freshness Requirements

The design uses two clocks:

- `observedAt`: the OwnTracks payload timestamp;
- `receivedAt`: the Archive Control record timestamp.

Delayed mobile uploads make these clocks materially different. Segmentation
uses `observedAt`; operational freshness reports both ages and must not hide a
large receive delay. Future thresholds are configuration, not hard-coded
conclusions from one installation snapshot.

## 6. Accuracy Requirements

Horizontal accuracy is required quality metadata, not decoration. A point may
be rendered as uncertain, excluded from a line, or start a new segment based
on an explicit configured policy.

The current `position` payload does not contain accuracy. Accuracy is archived
separately and only when its value changes. Any temporal carry-forward must:

- declare its evidence and age;
- stop after a bounded maximum age;
- never be represented as a same-sample measurement without proof; and
- preserve an `unknown` state when no valid accuracy applies.

Vertical accuracy is not historically archived and must not be reconstructed
as if it were.

## 7. Privacy and Provider Requirements

- No public artifact contains ObjectIDs, tracker identities, topics,
  coordinates, place names, map screenshots or movement histories.
- Real coordinates and rendered tracks remain installation-private runtime
  data.
- External tile, geocoding or routing services require a separate provider,
  licensing, network and location-privacy decision.
- Provider-neutral means that presentation does not depend on the OwnTracks
  module contract. It does not mean that tile acquisition is automatically
  provider-free.
- The first provider decision is an internal same-origin XYZ basemap, optional
  server-side internal OSRM and a mandatory no-provider fallback. External
  providers remain outside the approved disclosure boundary.
- Synthetic fixtures must use non-identifying coordinates and labels.

## 8. Reliability and Rollback Requirements

A future pilot must run in parallel with the existing map. It must not
overwrite the current map variable or reuse the current map instance as its
owner.

Rollback must consist only of removing or hiding the new visualization entry
and restoring the existing links. The existing data instances, hook, map
instance, external path projection, credentials, logging and archive data remain
untouched.

Archive reads follow RS-001.18: explicit time range, explicit count limit and
deterministic truncation reporting. No archive write or reaggregation belongs
to the map runtime.

## 9. Non-Goals

This phase does not:

- implement PHP, JavaScript, HTML or a Symcon module;
- change the existing OwnTracks map;
- support Navimow coordinates or zone geometry;
- infer home, place or trip semantics from tracks;
- introduce reverse geocoding, routing or geofencing;
- alter logging or retention; or
- create a reusable SAEF map helper or public API.

## 10. Acceptance Criteria for the Next Design Gate

Before an implementation gate can be proposed:

1. the three-source mapping must be expressed without installation IDs;
2. the accuracy temporal-join policy must be fixture-tested;
3. timestamp ordering, gaps, stale data and delayed uploads need synthetic
   regression fixtures;
4. the selection variable, timestamp-labelled point mode and explicitly
   selected external projection must be covered by synthetic fixtures;
5. the renderer and tile-provider privacy decision must be documented;
6. the parallel visualization placement and exact rollback path must be
   inventoried through the authorized native visualization channel; and
7. record and rendered-point budgets must be profiled on representative
   synthetic 1-day and multi-day windows; and
8. fit-all must be proven against the complete valid day bounds even when
   marker sampling is active;
9. route-aware and diagnostic ETA states must remain distinguishable; and
10. no general SAEF abstraction may be extracted at this gate.
