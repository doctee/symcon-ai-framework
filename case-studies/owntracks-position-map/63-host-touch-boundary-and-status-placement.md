# Host Touch Boundary And Status Placement

**Status:** Repository correction complete; responsive host-layer acceptance
passed; exact package activated separately in step 64

**Date:** 2026-09-02

## Physical Regression

After the compact-overlay follow-up, the upper selection fields no longer
reacted on iPhone. The HTML SDK still owned and rendered the complete tile
surface, but Symcon/Ninja placed a pointer-active host-control layer above the
HTML document's upper 46 pixels. No child `z-index` inside the HTML document
can overtake that parent layer.

The preceding layout had moved the selection frame to six pixels from the top
and unintentionally placed the native fields inside that host layer. A
synthetic iPhone hit test reproduced the regression: all three field centres
resolved to the host probe rather than to their native `select` or `input`.

## Corrected Boundary

The correction separates visual and interactive placement:

- the selection frame may still begin six pixels below the tile edge;
- the non-interactive position/load count moves into the frame's upper band;
- field labels remain in that upper visual band;
- every native selection field starts after the 46-pixel host boundary;
- zoom and fit controls also start after the same boundary; and
- the status output cannot capture pointer events.

This preserves the compact top placement without pretending that HTML stacking
can override the host chrome. Map projection, source selection, archive reads,
ETA, tile authorization, provider budgets and cache behavior are unchanged.

## Verification

The host fixture retains a pointer-active 46-pixel overlay. Internal-browser
checks at 390 by 844, 1024 by 768 and 1280 by 720 verified:

- frame top at six pixels;
- status top at 11 pixels and entirely before the field labels;
- first interactive pixel at approximately 46.9 pixels;
- every top, centre and bottom hit sample resolves to its native field or
  button and none to the host layer; and
- no horizontal document overflow.

The complete OwnTracks suite, deterministic fileset check, PHPStan, PHPCS and
diff check pass. The resulting 36-file package identity is
`6cfc1cba63add51505a460f139ea5c547dd0ccd88c7ac1a2e18c6a1cac800bb0`.

No live object, configuration or package was changed by this repository step.
The separately authorized live activation is recorded in step 64.
