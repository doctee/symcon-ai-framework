# OwnTracks Position Map Offline Core

**Status:** Synthetic candidate implemented; no live or renderer mutation

**Date:** 2026-08-29

## 1. Gate and Scope

This implementation covers only the approved SAEF steps 1 and 2:

1. an isolated worktree based on the then-current `origin/main`; and
2. a case-study-local offline core with synthetic fixtures and tests.

It does not connect to Symcon, change the installed map, select a tile
provider, add a visualization entry or publish an artifact. The candidate is
not a general SAEF map abstraction.

## 2. Candidate Components

| Component | Responsibility |
| --- | --- |
| `OwnTracksDayWindow` | Converts a selected local calendar day into a DST-safe, half-open UTC archive window. |
| `OwnTracksWgs84` | Validates EPSG:4326 coordinates, computes Haversine distance and minimal antimeridian-aware bounds. |
| `OwnTracksTrackCore` | Decodes bounded archive-shaped records, joins changing accuracy values, flags quality states, segments paths and applies a separate render budget. |
| `OwnTracksEtaProjector` | Prefers a fresh external route estimate and otherwise permits only an explicitly enabled diagnostic geodesic/observed-speed estimate. |

The input is deliberately Archive-Control-shaped but contains no ObjectID.
The future live adapter must resolve Idents and perform bounded reads; the
offline core neither knows nor owns Symcon objects.

## 3. Complete-Day Fit and Rendering Budget

The core processes one selected source and one half-open day window. It
computes `fitBounds` from every valid observation whose OwnTracks payload time
falls inside that window. Bounds are reduced before line filtering or marker
sampling and therefore remain complete when the renderer retains only a small
feature set.

The candidate enforces independent limits:

- at most 10,000 position archive records per call;
- at most 5,000 rendered points, with a lower caller-selected budget;
- at most 512 line segments;
- at most 64 recent observations as ETA evidence; and
- at most 2 MiB for the serialized result.

The result reports archive-limit and render-budget exhaustion separately. A
limit-reached archive read stays a partial-result state even if fit bounds can
be calculated for every returned point.

The renderer modes remain:

- `timestamp-points` for the current point/timestamp behavior;
- `segmented-line` for quality-accepted path segments; and
- `line-with-sampled-timestamps` for lines plus a bounded point overlay.

Segment boundaries are retained during sampling when they fit in the point
budget. The result explicitly reports when that is impossible.

## 4. Time and Quality Semantics

The selected date is interpreted in an explicit IANA time zone. Tests cover
the 23-hour and 25-hour DST days in `Europe/Berlin`; a calendar day is never
assumed to contain exactly 86,400 seconds.

Position payload time (`tst`) remains `observedAt`; Archive Control time
remains `receivedAt`. Accuracy is joined as the most recent change at or
before reception time and becomes unknown after a configured age. Delayed
reception, source time ahead, out-of-order payloads, poor/stale/unknown
accuracy, duplicates, gaps and implausible jumps stay explicit flags.

All distance, jump and ETA calculations are WGS84/geodesic. No Navimow local
coordinate, zone or Euclidean threshold is imported.

## 5. ETA Boundary

The target must be supplied by a separately owned resolver using an opaque
target key and WGS84 coordinate. The renderer does not infer a destination
from movement direction, a path endpoint or a dynamic OwnTracks place.

A fresh external routing result is the preferred ETA authority. Provider,
network, privacy, credentials and route-request behavior remain outside this
core and require a later decision. If explicitly enabled, the fallback uses
geodesic remaining distance and the median of recent quality-accepted observed
speeds. It sets `routeAware=false` and is only a diagnostic estimate; stale or
insufficient evidence returns `unavailable` instead of false precision.

## 6. Synthetic Evidence

`fixtures/track-day-synthetic.json` uses invented coordinates and opaque
labels. It exercises newest-first archive input, invalid and outside-window
records, delayed and out-of-order reception, accuracy changes, a gap and a
render budget smaller than the complete fit input.

The executable checks cover:

- all three renderer modes;
- complete fit bounds despite marker sampling;
- antimeridian-aware bounds;
- stale request-generation rejection;
- DST day-window calculation;
- external route ETA, diagnostic fallback and missing-target behavior;
- synthetic privacy markers; and
- 250, 1,000, 5,000 and 10,000 observation performance cases.

On the implementation workstation, the latest synthetic run completed the
10,000-observation and 10,000-accuracy-change projection in about 45 ms. This
is engineering evidence, not a mobile-rendering performance claim. Browser
feature construction and responsive interaction are covered by the later
OpenLayers pilot. The repository-only Symcon adapter candidate is documented
in `08-symcon-archive-adapter-candidate.md`; live archive performance remains
unmeasured.

## 7. Renderer Handoff

The subsequent offline renderer comparison uses the same synthetic contract:

- borderless responsive tile surface;
- mouse, touch and trackpad pan/zoom;
- explicit fit-all using `fitBounds`;
- timestamp-point compatibility and optional segmented lines;
- a visibly qualified ETA overlay;
- OpenLayers, Leaflet and no-external-tile diagnostic comparison; and
- desktop/mobile-sized performance measurements.

The outcome and remaining gates are recorded in
`06-offline-renderer-comparison.md`. It contacts no real tile, routing or
geocoding provider. Any dependency bundle, Symcon adapter, visualization object
or parallel live tile remains a later gate.
