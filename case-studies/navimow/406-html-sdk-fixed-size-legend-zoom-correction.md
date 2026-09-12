# SAEF Step 406: HTML SDK Fixed-Size Legend Zoom Correction

- **Date:** 2026-09-12
- **Status:** Correction implemented and verified offline; publication and
  physical validation remain closed
- **Scope:** Navimow HTML SDK legend presentation only

## 1. Purpose

Physical iPad validation confirmed the corrected overlay positions from step
404, but exposed a remaining zoom defect: changing the SVG `viewBox` scaled the
legend together with zones, paths and markers. At higher zoom levels the legend
therefore occupied most of the visible map.

The legend must retain its Fit-view screen size while the map geometry remains
freely zoomable and pannable.

## 2. Root Cause

The HTML SDK previously corrected only the legend's horizontal position. The
legend remained a child of the root SVG and inherited the complete scale caused
by every `viewBox` change. Reapplying its original translation avoided position
accumulation but did not counter the inherited zoom scale.

## 3. Correction

After each render, zoom, pan, zone focus and browser resize, the existing
bounded alignment routine now:

1. restores the immutable renderer anchor;
2. measures the current SVG screen transform and the original Fit scale;
3. applies the inverse zoom ratio to the legend;
4. converts the browser's lower-right inset through the inverse SVG matrix; and
5. positions the counter-scaled legend at that screen-space target.

The lower inset also includes the visible statistics strip, preventing the
fixed legend from covering it. The generated SVG retains its complete original
legend and deterministic fallback when used without the HTML SDK.

## 4. Boundaries

The correction changes only `local-map.js`. It does not alter:

- accepted geometry, zone bindings or private coordinates;
- SVG renderer output or map revision identity;
- retained paths, coverage calculations or statistics;
- REST authority, MQTT operation, OAuth or mower commands;
- variables, profiles, configuration or Archive logging.

## 5. Architecture Decisions

### AD-NAV-406-01: Counter-scale the existing SVG legend

**Decision:** Keep the legend in the self-contained SVG and apply an HTML SDK
counter-transform instead of duplicating it as an HTML overlay.

**Reason:** The standalone SVG remains complete, while the interactive view can
provide screen-fixed controls without introducing a second legend renderer or
diverging symbol semantics.

### AD-NAV-406-02: Derive size from the Fit scale

**Decision:** Calculate the target scale from the immutable Fit `viewBox` and
the current SVG screen matrix.

**Reason:** The resulting legend size is deterministic for the current viewport
and independent of wheel, button, pinch or zone-focus zoom history.

### AD-NAV-406-03: Pin both browser edges

**Decision:** Resolve the lower-right target through `matrix.inverse()` and
reserve the current statistics-strip height.

**Reason:** Counter-scaling alone would keep the legend small but allow its
map-space anchor to drift or leave the viewport during pan and focus changes.

## 6. Verification

Focused Device and distribution checks prove:

- unchanged legend width and semantic SVG contracts;
- presence of Fit/current scale derivation and inverse-matrix placement;
- unchanged navigation interaction and zone focus; and
- complete generated-fileset and offline distribution contracts.

A controlled Chromium run used only synthetic geometry and exercised five
zoom-in steps, zone focus and three zoom-out steps. Width and height are CSS
pixels and remained equal within the `0.75px` test tolerance:

| Viewport | Fit size | Zoom-in size | Zone-focus size | Zoom-out size | Result |
|---|---:|---:|---:|---:|---|
| 1280 x 720 | 382.411 x 387.877 | 382.411 x 387.877 | 382.411 x 387.876 | 382.411 x 387.877 | PASS |
| 1180 x 820 | 435.524 x 441.748 | 435.524 x 441.748 | 435.524 x 441.748 | 435.524 x 441.748 | PASS |
| 600 x 800 | 312.000 x 316.459 | 312.000 x 316.459 | 312.000 x 316.459 | 312.000 x 316.459 | PASS |

Every state retained a `6px` right inset and a lower inset equal to `6px` plus
the visible statistics-strip height. Visual inspection of the iPad-landscape
result showed no navigation or statistics overlap. The complete repository
check, including PHPCS, PHPStan, privacy, Navimow tests and fileset validation,
passed.

Publication, Symcon update and physical iPad confirmation remain separate
explicit gates.

## 7. Gate Status

| Gate | Status |
|---|---|
| Local correction | PASS |
| Focused static tests | PASS |
| Controlled browser checks | PASS, 3/3 viewports |
| Generated fileset and repository check | PASS |
| Publication and live validation | CLOSED |
