# Viewport Tile Authorization And Fit Correction

**Status:** Repository implementation and synthetic browser acceptance complete;
live activation remains closed

**Date:** 2026-09-01

## Observed Failure

Physical testing exposed two independent defects. In the current overview the
western point could be placed below the status badge because fit padding and
occlusion diagnostics considered only the selection and navigation controls.
For a long selected day, provider fallback never started because the runtime
tried to enumerate every potential tile across the complete data envelope and
all configured zoom levels. That exceeded the 128-tile spatial budget before
the first missing tile reached the provider boundary.

No source object, archive, logging state, visualization link, provider or live
configuration was changed while diagnosing or correcting these defects.

## Reuse-Before-Extend Decision

The correction retains the case-study-local selection allowlist, capability,
request budget, miss resolver, provider cache and transport. It does not add a
general SAEF map or WebHook API.

The selected data now owns a non-reversible stable selection key and a WGS84
outer envelope. The browser separately reports its current WGS84 viewport
after fit and, with a short debounce, after pan or zoom. The runtime materializes
an allowlist only for that viewport, the current zoom and the one lower preload
zoom. The configured tile-count ceiling therefore remains a real bounded
authorization limit instead of being raised to accommodate an entire route.

## Security And Budget Boundary

The viewport update is accepted only for the active client session and exact
track generation, with a strictly increasing viewport generation, valid
Web-Mercator coordinates, supported zoom and an intersection with the
server-derived data envelope. A provider miss must pass both the current
viewport allowlist and the outer data envelope. Static authority remains first.

Changing the viewport does not change the stable selection key. Request, byte,
negative-cache, global rate and concurrency state therefore cannot be reset by
panning or zooming. Tiles rejected outside the current viewport or data
envelope do not reach DNS or transport. No prefetch, bulk download or offline
archive capability was introduced.

OpenLayers creates or recreates its protected source only after the viewport
acknowledgement. This retries tiles that previously failed before a newly
visible viewport was authorized, while keeping the ephemeral capability header
and same-origin WebHook boundary unchanged.

## Fit Correction

The status badge is now included in both fit padding and overlay-occlusion
diagnostics. The resulting fit reserves space below the complete top-left UI,
so an edge point is not hidden by either the controls or the status count.

## Verification

Repository verification passed:

- the full OwnTracks Composer suite, including package and distribution checks;
- a stable-budget test across two different viewport allowlists;
- runtime acceptance of a bounded viewport and rejection outside the selected
  data envelope;
- deterministic OpenLayers bundle regeneration and check; and
- deterministic regeneration of the 36-file Symcon package.

The internal browser used synthetic data only. At 1280 by 720 and 390 by 844,
Positions and Path reached protected-tile state `ready`, fit diagnostics found
zero point/UI occlusions, and both desktop zoom and touch pan caused a new
bounded tile request cycle. The fixture deliberately returns no actual tile
image; its 404 responses verify retry and viewport sequencing without contacting
a provider.

## Remaining Gate

A separate live gate is required to transfer or activate the regenerated
package. That gate must preserve the current package and complete configuration
as rollback, recheck the active package and provider policy, and perform an
independent postflight. Provider publication, commit, cache purge, static tile
replacement and changes to OwnTracks objects remain closed.
