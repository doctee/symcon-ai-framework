# Tile-Grid Zoom Alignment Correction

**Status:** Repository correction verified and live package activated

**Date:** 2026-09-02

## Symptom

For a long historical path, `Fit all` rendered only isolated map strips or
rectangles. A manual zoom changed which area was covered, but did not make the
initial viewport reliable.

## Read-Only Live Evidence

One explicitly authorized live browser call reproduced the issue. The client
reported 65 protected tile requests: 35 succeeded and 30 failed after the one
bounded recovery generation. Sanitized server state showed two successive
viewport selections with 15 rejected requests each, no provider-budget
rejection and no successful provider request for either selection.

The matching counts prove that the blank areas were not caused by a static
coverage extent. Static tiles are still exact XYZ entries. The missing tiles
were rejected before provider resolution because their zoom level was outside
the viewport allowlist.

## Root Cause

The renderer authorized a viewport with `Math.round(view.getZoom())`.
OpenLayers does not choose an XYZ level by rounding the view zoom. Its tile
renderer asks the source tile grid for the nearest resolution. With a zoom
factor of two, the resolution boundary is arithmetic and can select the next
tile level before the fractional view zoom reaches `.5`.

Thus a valid fitted view could authorize level `z` while the protected source
requested level `z + 1`. Pre-provisioned static hits still rendered, but every
uncached tile at the actual source level failed closed as outside the
selection-bound allowlist.

## Correction

The case-study renderer now creates one OpenLayers XYZ tile grid for the
configured maximum zoom and uses that same object for both:

- the protected `ImageTile` source; and
- the viewport authorization zoom derived with
  `getZForResolution(view.getResolution())`.

The server allowlist, provider limits, capability headers, viewport ring,
selection ownership and privacy boundary are unchanged. In particular, the
fix does not broaden authorization to an adjacent zoom level.

## Verification Boundary

Repository checks must prove that:

- viewport authorization no longer rounds `view.getZoom()`;
- the source and authorization share the same XYZ tile grid;
- the derived zoom is exposed only as a non-location diagnostic aggregate;
- the protected-loader, retry and security tests remain green; and
- the generated module fileset remains deterministic.

No ObjectID, tracker identifier, coordinate, tile index, movement history,
private origin or host metadata is recorded here.

The exact activation and retained rollback evidence are recorded separately in
`72-tile-grid-zoom-alignment-live-activation.md`.
