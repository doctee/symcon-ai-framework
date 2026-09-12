# SAEF Step 402: HTML SDK Safari Controls And Legend Correction

- **Date:** 2026-09-12
- **Status:** Corrections implemented and verified offline; publication and live
  validation remain closed
- **Scope:** Navimow HTML SDK presentation only

## 1. Purpose

This step records three corrections derived from the first visible HTML SDK map
review:

- balance the legend's right and bottom interior spacing;
- make the map navigation operable below the upper Safari and iPad interaction
  exclusion area; and
- give the zone selector deterministic Dark-Skin presentation.

The correction does not change map geometry, mowing analytics, retained data,
REST authority, MQTT operation, OAuth state, commands, variables or Archive
configuration.

## 2. Observed Problems

The live presentation exposed these bounded frontend defects:

1. The longest labels in the legend left less optical space at the right edge
   than the final row left at the bottom edge.
2. Controls placed at six pixels from the upper tile edge could be visible but
   not interactive in Safari and the iPad app. The affected area is consistent
   with the known approximately 46-pixel visualization interaction boundary.
3. The native `select` surface could use a light browser default even when the
   map was configured for Dark Skin.

## 3. Implemented Correction

### Legend

The bounded legend width factor increases from `18.0` to `19.0` font units.
Height, row spacing and lower padding remain unchanged. The extra width restores
the right-side optical padding without moving the legend away from the map
edge or changing its content.

### Safari and iPad controls

The navigation panel now starts at `52px` from the upper tile edge. This keeps
all interactive controls below the 46-pixel boundary with a six-pixel gap.

Control behavior is further made explicit:

- navigation receives pointer events;
- buttons and the zone selector use `touch-action: manipulation`;
- coarse-pointer controls are 44 pixels high; and
- the WebKit tap highlight is suppressed without disabling focus or events.

The non-interactive status output remains at the upper edge because it does not
need to receive pointer input.

### Dark mode

The map defaults to explicit dark tokens. A configured light theme overrides
them through `data-theme="light"`. The selector and its options use a solid
panel background, the map text color and the inherited native color scheme.
This avoids depending on partial `light-dark()` support in embedded Safari
contexts while preserving the existing explicit map theme contract.

## 4. Verification

The focused PHP tests verify:

- the revised legend-width contract in both renderer mirrors;
- presence of the 52-pixel control offset;
- 44-pixel coarse-pointer targets;
- explicit dark and light theme tokens; and
- native selector and option styling.

Automated browser interaction checks passed for:

| Viewport | Pointer model | Result |
|---|---|---|
| Desktop, 1280 x 800 | Fine pointer | Zoom and zone selection PASS |
| iPad, 1024 x 768 | Touch/coarse pointer | Zoom and zone selection PASS |
| Compact tile, 600 x 800 | Touch/coarse pointer | Wrapped selector PASS |

In every case the first interactive control started below 46 pixels, the
browser hit test resolved to the expected control and the selector rendered
with the dark surface and text colors. The complete repository `make check`,
including PHPStan, also passed with the lock-identical canonical Composer
toolset.

Physical Safari and IP-Symcon iPad validation remains required after a separate
publication and disabled rollout. Chromium touch emulation is useful regression
evidence but does not replace that live gate.

## 5. Architecture Decisions

### AD-NAV-402-01: Reserve the upper interaction boundary explicitly

The map keeps interactive controls below the known host-UI boundary instead of
trying to work around it with event forwarding. This remains understandable,
testable and independent of undocumented host behavior.

### AD-NAV-402-02: Use explicit theme tokens for native controls

Embedded browser support for newer color functions is not assumed for native
form controls. Explicit dark defaults and a light-theme override provide a
deterministic result while retaining the module's user-selected theme.

### AD-NAV-402-03: Keep the correction presentation-only

No runtime state, analytics or transport contract is changed to repair a map
layout and interaction defect. Publication and live validation therefore remain
separate from the completed offline correction.

## 6. Gate Result

| Gate | Status |
|---|---|
| Local correction | PASS |
| Focused renderer and Device tests | PASS |
| Automated desktop and touch interaction checks | PASS |
| Complete repository check | PASS |
| Commit, push and SAEF pull request | CLOSED pending approval |
| Standalone publication | CLOSED |
| Symcon module update | CLOSED |
| Physical Safari and iPad validation | CLOSED |
