# OwnTracks Offline Renderer Comparison

**Status:** Diagnostic renderer implemented; OpenLayers offline pilot selected

**Date:** 2026-08-29

## 1. Scope and Evidence Boundary

This step implements the approved offline renderer comparison against
synthetic data. It does not install a JavaScript dependency, contact a tile or
routing provider, use live Symcon data or create a visualization object.

The executable candidate is intentionally a no-tile diagnostic map. It proves
the provider-neutral interaction and data boundaries without pretending that a
coordinate grid is a street basemap. OpenLayers and Leaflet are compared as
future adapter targets from official API evidence and existing installation
evidence; neither library is copied or loaded by this step.

## 2. Executable Diagnostic Candidate

The candidate under `candidate/renderer/` provides:

- a borderless surface that fills its content box;
- one selection control for three synthetic source keys;
- a DST-bound local day selection;
- `timestamp-points`, `segmented-line` and
  `line-with-sampled-timestamps` modes;
- pointer drag, touch drag, two-pointer pinch, mouse-wheel, button and keyboard
  pan/zoom;
- explicit fit-all based on complete `fitBounds`, not sampled markers;
- timestamp labels and point details with accuracy attribution;
- a target marker and visibly distinguished route-aware, diagnostic or
  unavailable ETA;
- resize handling; and
- a monotonic request generation that discards stale selection results.

The background is an EPSG:4326/Web-Mercator coordinate grid and states that it
uses no map tiles. It is suitable for contract and interaction diagnosis only.

The synthetic browser fixture supplies three opaque sources and two calendar
days. Each source/day contains 360 valid observations but only 48 rendered
points. This proves that the camera extent and feature budget remain separate.

## 3. Renderer Options

| Criterion | Diagnostic Canvas | OpenLayers adapter | Leaflet adapter |
| --- | --- | --- | --- |
| Executable in this gate | Yes, with no external resource | No local dependency present | No local dependency present |
| Basemap | None; coordinate grid only | Optional tile/vector layer behind explicit provider configuration | Optional tile/grid layer behind explicit provider configuration |
| Pan/zoom | Candidate-owned pointer, wheel, keyboard and pinch handling | Default interaction collection includes drag, pinch, wheel and keyboard navigation | Map interaction options include dragging, pinch/wheel and keyboard zoom |
| Complete-day fit | Candidate consumes `fitBounds` directly with overlay padding | `View.fit()` accepts an extent, map size and four-sided pixel padding | `fitBounds()` accepts geographic bounds and asymmetric overlay padding |
| Resize | Candidate `ResizeObserver` refits current complete bounds | Adapter must update map size before/refit after container change | Adapter must call/verify `invalidateSize()` before/refit after container change |
| Vector budget | Direct Canvas draw; 48-feature fixture | Vector/VectorImage/WebGL choices need a fixture benchmark | SVG or Canvas; `preferCanvas`/`L.canvas()` needs a fixture benchmark |
| Antimeridian | Core emits an explicit crossing flag; candidate unwraps the minimal interval | Adapter must transform the unwrapped WGS84 extent deliberately | Adapter must prove crossing bounds; naive west/east construction is insufficient |
| Current-installation evidence | New case-study-only code | Existing map already demonstrates a modern OpenLayers client | No current-installation evidence |
| Dependency/publication effect | No new dependency | Requires a pinned, locally bundled dependency and license inventory | Requires a pinned, locally bundled dependency and license inventory |

Official OpenLayers documentation confirms that `Map` can operate with no
layers, its default interaction collection contains drag, pinch, wheel and
keyboard navigation, and `View.fit()` accepts explicit size and padding:
[OpenLayers Map](https://openlayers.org/en/latest/apidoc/module-ol_Map-Map.html),
[OpenLayers interactions](https://openlayers.org/en/latest/apidoc/module-ol_interaction_defaults.html),
[OpenLayers View](https://openlayers.org/en/latest/apidoc/module-ol_View-View.html).

Official Leaflet documentation confirms draggable/zoom interaction options,
`fitBounds()` with asymmetric padding, `invalidateSize()` and optional Canvas
path rendering: [Leaflet reference](https://leafletjs.com/reference).

## 4. Decision

OpenLayers is the recommended first geographic pilot adapter, subject to a
separate dependency gate. The decision is based on the existing OwnTracks-map
client evidence, explicit projection/view ownership, four-sided fit padding and
layer choices. It is not a decision to reuse the installed map instance or its
hook.

Leaflet remains the bounded fallback if an offline bundle/fixture benchmark
shows materially lower integration cost while preserving antimeridian,
touch/resize and vector-budget requirements. No unmeasured library-size or
performance advantage is claimed.

The diagnostic Canvas renderer remains useful as a provider-outage and privacy
fallback. It is not promoted to a general SAEF renderer because only this case
study consumes it.

## 5. Verification

Automated checks verify:

- no `fetch`, XHR, WebSocket or external resource URL in renderer assets;
- complete fit metadata versus a 48-point render budget;
- three synthetic sources, two days and all path modes;
- route-aware, diagnostic and unavailable ETA fixture states;
- required pointer, wheel, resize, generation and accessibility hooks;
- a fixture size below 1 MiB; and
- JavaScript syntax, PHP syntax, PHPStan, PHPCS and the aggregate SAEF gate.

The local in-app browser became available after an app restart. Responsive
desktop/mobile evidence, navigation restoration, selection races, ETA states
and the resulting label-budget correction are recorded in
`07-openlayers-offline-pilot.md`. Physical trackpad/touch pinch and wheel input
remain outside the observable automation surface and are not inferred as
passed.

## 6. Next Gates

The rollout remains additive and separates authority:

1. **Dependency and responsive browser gate:** completed by the pinned no-tile
   candidate and sanitized evidence documented in
   `07-openlayers-offline-pilot.md`.
2. **Provider gate:** completed by the same-origin internal XYZ, optional
   internal OSRM and mandatory no-provider fallback decision in
   `09-provider-decision.md`.
3. **Symcon adapter gate:** implement bounded read-only archive access by
   configured Idents and generation-aware selection actions.
4. **Parallel visualization gate:** create a new separately owned tile beside
   the existing map, with a byte-identifiable rollback unit.

None of these gates authorizes changes to logging, archives, OwnTracks
instances, the installed map or its existing visualization links.
