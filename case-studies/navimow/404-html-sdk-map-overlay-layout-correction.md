# SAEF Step 404: HTML SDK Map Overlay Layout Correction

- **Date:** 2026-09-12
- **Status:** Correction implemented and verified offline; publication and live
  validation remain closed
- **Scope:** Navimow HTML SDK presentation only

## 1. Purpose

This step corrects three layout findings from the physical Dark-Skin map
review:

- move the navigation panel away from the upper-right map content;
- align the legend with the actual right browser edge despite SVG aspect-ratio
  letterboxing; and
- remove excessive right-side space inside the legend.

The correction does not change map geometry, retained tracks, mowing
analytics, variables, Archive logging, REST authority, MQTT operation, OAuth
state or mower commands.

## 2. Root Cause

The navigation panel was anchored to the upper-right edge and therefore
covered map content in the accepted private geometry.

The legend itself was correctly anchored to the right edge of the SVG
`viewBox`. The accepted geometry is substantially narrower than a landscape
browser viewport, however. With `preserveAspectRatio="xMidYMid meet"`, the
browser adds horizontal letterboxing and the SVG edge is visibly inset from
the actual tile edge. Increasing the legend width in step 402 then left more
interior space than the physical review required.

## 3. Implemented Correction

### Navigation

The complete navigation panel retains the accepted `52px` upper interaction
offset and moves from `right: 6px` to `left: 6px`. The control order, touch
targets, Dark-Skin styling and responsive two-row selector layout remain
unchanged.

### Legend browser-edge alignment

The renderer emits numeric `data-anchor-x` and `data-anchor-y` values matching
the legend's original SVG translation. After each render, view change and
browser resize, the HTML SDK:

1. restores the immutable SVG anchor;
2. measures the rendered map and legend rectangles;
3. converts the required screen-pixel shift through the current SVG scale; and
4. applies only a horizontal translation that leaves exactly `6px` at the
   actual browser edge.

This keeps map geometry and `preserveAspectRatio` unchanged. The alignment is
presentation-only and remains valid after Fit, zoom, zone focus and viewport
resize.

### Legend width

The bounded two-column width changes from `19.0` to `16.5` legend-font units.
The live-shaped 1280 x 720 test reduced the legend from approximately `301px`
to `251px`. The longest right-column label retains approximately the same
visual right padding as the final row retains at the bottom.

## 4. Verification

Focused renderer and Device tests verify:

- identical width and anchor contracts in candidate and distribution
  renderers;
- left navigation anchoring;
- presence of the browser-edge alignment routine;
- unchanged Dark-Skin and touch-control contracts; and
- unchanged HTML SDK assembly limits and network isolation.

Controlled browser measurements used the accepted private geometry without
publishing geometry, coordinates or ObjectIDs:

| Viewport | Navigation left | Legend right | Overlay collision | Result |
|---|---:|---:|---|---|
| 1280 x 720 | 6px | 6px | no | PASS |
| 1024 x 768 | 6px | 6px | no | PASS |
| 600 x 800 | 6px | 6px | no | PASS |

Zoom and zone focus were exercised in the browser. The map view changed and
the legend retained its six-pixel browser-edge alignment. The compact viewport
kept the existing wrapped selector and all legend text visible.

Physical Safari and IP-Symcon iPad confirmation remains a separate live gate
after publication and a controlled module update.

## 5. Architecture Decisions

### AD-NAV-404-01: Keep map coordinates independent of browser overlays

**Decision:** Correct the legend's browser placement in the HTML SDK rather
than changing the map `viewBox`, projection or aspect-ratio policy.

**Reason:** Geometry changes would move every zone, path and marker merely to
repair a presentation overlay. A measured client-side offset addresses the
actual letterboxing boundary without changing spatial semantics.

### AD-NAV-404-02: Preserve a deterministic renderer fallback

**Decision:** Keep the original in-viewBox transform and expose it as numeric
data attributes.

**Reason:** The generated SVG remains complete and readable without the HTML
SDK. The browser may repeatedly restore and recalculate the anchor without
accumulating floating-point translations.

### AD-NAV-404-03: Move the complete navigation panel

**Decision:** Move the panel as one stable control group instead of reordering
only the zone selector.

**Reason:** The observed overlap concerns the complete upper-right overlay.
Keeping its established order and dimensions avoids a new interaction pattern
and leaves the responsive control contract intact.

## 6. Gate Result

| Gate | Status |
|---|---|
| Local correction | PASS |
| Focused renderer and Device tests | PASS |
| Controlled desktop, iPad-size and compact browser checks | PASS |
| Generated fileset and complete repository check | PASS |
| Local candidate commit | PASS |
| Push and SAEF pull request | CLOSED |
| Standalone publication | CLOSED |
| Symcon module update | CLOSED |
| Physical Safari and iPad validation | CLOSED |
