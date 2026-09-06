# OpenLayers Offline Pilot

**Status:** Bundle, synthetic adapter and responsive in-app browser QA complete

**Date:** 2026-08-29

## 1. Gate Scope

This step implements the authorized dependency gate only:

- pin and locally bundle OpenLayers;
- retain a complete runtime/build license inventory;
- consume the existing synthetic OwnTracks contract;
- render without tiles, routing, geocoding or another network source; and
- prepare desktop/mobile geometry and interaction diagnostics.

It does not access Symcon, add a basemap provider, create a visualization,
change the installed OwnTracks map or publish the bundle.

## 2. Exact Dependency Contract

The case-study-local `browser/package.json` and lockfile pin:

| Role | Package | Version | License |
| --- | --- | --- | --- |
| Runtime | `ol` | 10.10.0 | BSD-2-Clause |
| Build only | `esbuild` | 0.28.2 | MIT |

The lockfile retains npm SHA-512 integrity values. The build metafile proves
that only these runtime packages contribute code to the selected entry:

| Runtime package | Version | License |
| --- | --- | --- |
| `ol` | 10.10.0 | BSD-2-Clause |
| `rbush` | 4.0.1 | MIT |
| `quickselect` | 3.0.0 | ISC |

The generated `candidate/openlayers/licenses/` directory contains the exact
license text for every runtime package and the build tool. No dependency is
loaded from a CDN at runtime.

## 3. Deterministic Bundle

`browser/build-openlayers.mjs` builds the single adapter entry with a fixed
target and no source map or timestamp. `npm run check` rebuilds into a temporary
directory and requires byte-identical output.

The generated manifest records per-artifact size and SHA-256. At this gate the
main artifacts are:

- JavaScript: 335,857 bytes;
- OpenLayers CSS: 5,349 bytes; and
- a JSON manifest plus four exact license files.

The committed artifact is a case-study candidate, not a public SAEF helper or
distribution module.

## 4. Provider-Bounded OpenLayers Adapter

The disabled-provider bootstrap creates exactly five layers:

1. an OpenLayers graticule with no external source;
2. a vector layer for segmented lines;
3. a vector layer for timestamp points; and
4. a vector layer for the optional external path anchor; and
5. a vector layer for the explicit ETA target.

The provider contract documented in `09-provider-decision.md` may add one
optional same-origin XYZ `TileLayer` at index 0. This produces six layers only
when that explicit configuration is enabled. Absolute and scheme-relative tile
URLs fail closed, and neither route requests nor renderer credentials are
introduced. The synthetic fixture keeps the provider disabled and adds
`connect-src 'none'` to its Content Security Policy, so its instantiated layer
graph owns no network source. The bundle may still contain generic OpenLayers
network code and CRS identifier URLs.

The adapter preserves the case-study behavior:

- three opaque synthetic source selections and two local calendar days;
- monotonic generation with stale-result rejection;
- point-only, segmented-line and line-plus-timestamp modes;
- complete-day fit from `fitBounds`, with different overlay padding below and
  above 560 CSS pixels;
- a separate timestamp-label budget of at most 16 desktop and 8 compact labels,
  while every sampled point remains rendered and selectable;
- OpenLayers drag, wheel, pinch and keyboard interactions;
- explicit fit and zoom controls;
- antimeridian-aware longitude unwrapping before EPSG:3857 projection;
- resize through `map.updateSize()` followed by fit; and
- visibly distinct route-aware, diagnostic and unavailable ETA states.

The synthetic fixture retains 360 valid observations while rendering only 48
features. No camera extent is derived from that sampled feature set. Timestamp
text is thinned independently from point features to prevent compact layouts
from turning the timestamp option into an overlapping label field.

## 5. Reuse Before Extend

This step composes the existing case-study-local WGS84 core, day window,
fit-bounds result, ETA projection, control markup, responsive styling and
synthetic fixture. It introduces no general SAEF map API. The OpenLayers entry
is still owned by this case study; a second stable consumer would be required
before extracting an adapter abstraction.

## 6. Verification

Executable checks cover:

- exact direct dependency pins and registry integrity;
- exact runtime package/version/license inventory from the build;
- byte-identical bundle regeneration;
- artifact SHA-256 and size readback;
- JavaScript and PHP syntax;
- fixture CSP and absence of provider references in adapter source;
- 1 MiB fixture and 400 kB JavaScript bundle ceilings;
- complete versus rendered observation counts;
- PHPStan, PHPCS and the full SAEF `make check` gate.

After restarting Codex, the in-app browser became available. The pinned fixture
was verified at 1,280 x 720 and 390 x 844 CSS pixels. The browser checks proved:

- no horizontal document overflow at either viewport;
- 48 point features with 13 desktop and 8 compact timestamp labels;
- measured feature projection of 1.3 ms desktop and 0.5 ms compact in this
  synthetic run;
- route-aware, geodesic-diagnostic and unavailable ETA states;
- stale selection-result rejection across rapid source changes;
- both local calendar days and all three render modes;
- zoom controls, keyboard zoom, pointer drag and point selection;
- byte-identical screenshots before the navigation sequence and after
  `Fit all`, proving restoration of the complete-day view;
- non-overlapping compact ETA, attribution and tooltip overlays; and
- no browser console warning or error.

The available browser automation did not expose a physical pinch gesture, and
its synthetic wheel action produced no observable map event. Physical
trackpad/touch pinch and wheel behavior are therefore not claimed as verified;
the explicit zoom controls, keyboard zoom and pointer drag provide tested
interaction paths.

## 7. Tooltip Implementation Evidence

The responsive review added the established SAEF heatmap/forecast interaction
contract for points whose persistent label is thinned: desktop hover and touch
tap use one container-clamped tooltip, a new point replaces the current one,
free-map tap closes it, and touch state expires after four seconds. The tooltip
reuses the loaded feature's timestamp and accuracy and creates neither per-point
DOM nodes nor another archive query.

Gate 4B implemented this contract in the synthetic candidate and verified it
at 1,280 x 720 desktop and 1,024 x 1,366 iPad geometry:

- an unlabeled point opens the tooltip on desktop hover;
- leaving the point closes the transient tooltip;
- click/tap pins it and a different point replaces its content;
- tapping free map space and starting a pan close it immediately;
- the pinned state closes automatically after four seconds;
- the first and last rendered points keep the complete tooltip inside the map;
- timestamp and horizontal accuracy are the only feature details; and
- the browser produced no warning or error.

## 8. Remaining Gate

**Parallel runtime and visualization gate:** create an additive, separately
owned runtime and tile with rollback confined to new objects.

The bounded read-only live adapter verification is complete and recorded in
`08-symcon-archive-adapter-candidate.md`. It created no persistent binding and
does not open the remaining gate. The provider decision and optional
same-origin XYZ boundary are recorded in `09-provider-decision.md`; the
synthetic fixture still activates no tile layer.
