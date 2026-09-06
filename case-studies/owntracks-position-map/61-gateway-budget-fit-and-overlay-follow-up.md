# Gateway Budget, Fit And Overlay Follow-up

**Status:** Repository correction complete; synthetic browser acceptance passed;
live activation pending

**Date:** 2026-09-02

## Physical Evidence And Read-only Diagnosis

The physical iPad evidence showed that an initial `Fit all` could retain blank
tiles while a later zoom often completed the map. A fresh read-only runtime
probe found successful provider misses without negative-cache, allowlist or
per-viewport miss-budget rejection.

The first activation preflight then corrected an important assumption before
any transfer or mutation: the protected same-origin gateway already admits 240
requests per minute with four concurrent requests. The 30-request boundary
belongs exclusively to outbound provider traffic. Reducing the gateway to 96
would not fix the evidence and was therefore rejected before activation.

The remaining intermittent behavior is consistent with several selections,
zooms or pans sharing the strict outbound 30-request minute window. A rejected
provider admission was incorrectly counted as a used per-viewport miss and
entered the negative cache even though no external request had been sent. The
browser also retained the resulting failed OpenLayers tile until another user
interaction caused a new request.

## Smallest Corrected Boundary

The corrected candidate preserves every live limit:

- protected same-origin gateway: unchanged at 240 requests per minute;
- gateway concurrency: unchanged at four;
- outbound provider: unchanged at 30 requests per minute;
- provider concurrency: unchanged at two;
- provider misses per accepted viewport: unchanged at 24;
- capability, viewport allowlist, byte budget, cache and Connect protection:
  unchanged.

An admission rejected before transport no longer consumes the viewport miss
budget and no longer creates a negative-cache entry. When a protected tile
still fails, the browser schedules exactly one refresh after 65 seconds for
the unchanged data and viewport generation. A selection, zoom or pan cancels
that pending retry. This permits the existing provider minute window and
60-second negative-cache boundary to clear without increasing outbound volume
or creating an unbounded retry loop.

## Positions Fit

`Positions` now expands its Web-Mercator fit extent to at least 100 by 100
metres before applying overlay-aware padding. This prevents almost coincident
current points from producing an arbitrarily tight fit. `Path` continues to use
the complete selected-day WGS84 extent without this minimum.

## Overlay Placement

The selection panel now starts six pixels below the map edge. On narrow touch
layouts the navigation block remains below the host maximize area. ETA uses the
same compact visual scale as the selection panel. Attribution is smaller and
aligned to the lower map edge instead of floating above ETA.

## Verification

The pinned renderer bundle was regenerated. Resolver and provider-runtime
regressions prove that a denied provider admission consumes neither viewport
budget nor negative cache and can succeed in the next minute. The browser
contract proves the single 65-second generation-bound refresh. Synthetic
browser checks at square and narrow touch sizes verified:

- the 100-metre minimum is active only for `Positions`;
- no control/navigation, control/status or ETA/attribution overlap;
- six-pixel top and bottom edge placement;
- 11-pixel ETA and 9-pixel attribution text;
- complete synthetic tile loading with zero failed or missing tiles; and
- unchanged hidden ETA and unrestricted fit contract in `Path`.

The deterministic corrected 36-file package identity is
`7a9cc8562606eb08357553841590cb3665557476e8420b53c11df64b575c4b71`.

The first live preflight stopped before transfer because the assumed gateway
baseline was wrong. It changed no package, property, cache or live object. The
corrected package identity is recorded only after the complete fileset rebuild.

No live property, package, cache, OwnTracks object, archive, logging state,
visualization link or WebHook was changed by this repository step. Commit and
publication remain closed.
