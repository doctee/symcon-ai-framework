# Complete Viewport Tile Loading

**Status:** Repository implementation and synthetic desktop/iPad acceptance
complete; exact-package live activation recorded separately

**Date:** 2026-09-01

## Physical Observation

Historical path views showed rectangular gaps between statically available and
dynamically loaded tiles. The result varied between runs: sometimes only one
part of the fitted view was filled, sometimes dynamic coverage was absent, and
sometimes a blank strip remained between two covered regions.

The screenshots were used only as private diagnostic input. No coordinate,
tile index, source identity or movement history is reproduced here.

## Read-Only Diagnosis

The renderer already serialized requests to the configured concurrency bound.
The incomplete result instead came from two independent authorization budgets:

1. the server accepted the current viewport and built a bounded tile allowlist,
   but then intersected every tile with the narrower observation bounds; and
2. the miss budget belonged to the complete selected track rather than to one
   authorized viewport, while OpenLayers also preloaded another zoom level.

A sanitized live read confirmed real static misses and successful provider
cache writes. The rectangular gaps were therefore missing tile responses, not
a layer-order or iPad rendering artifact.

## Case-Study-Local Correction

The existing building blocks are composed without adding a general SAEF map
abstraction:

- the second spatial check now uses the already accepted viewport bounds;
- every accepted viewport receives a private miss-state key;
- the provider request budget uses a separate stable instance key, so a new
  viewport cannot reset the 30-request-per-minute boundary;
- OpenLayers preloading is disabled, prioritizing only currently visible
  tiles; and
- the live per-viewport miss allowance is raised from eight to 24 while the
  existing 30 requests per minute, two concurrent requests, 4-MiB byte limit,
  128-tile allowlist and one-tile ring remain unchanged.

Static authority, provider cache, ephemeral capability, request queue and
server-side viewport allowlist remain authoritative. OwnTracks coordinates are
not sent to the tile provider; the provider receives only eligible XYZ tile
indices. Navimow local coordinates remain outside this WGS84 runtime.

## Verification

The complete OwnTracks suite, deterministic 36-file package checks, PHPStan,
PHPCS and the OpenLayers bundle check passed. The package identity is:

```text
20f0aba9c8d7c033365ccf168f314a1b81bf1a7b6fcc60c327e81da768719231
```

The internal browser used only local synthetic positions and generated tiles:

- a 1280 by 720 viewport loaded 20 of 20 requested tiles with no failure or
  missing-tile result;
- a 1024 by 1366 iPad viewport remained visually complete with zero failed or
  missing tiles and no fit/UI occlusion; and
- Path, zoom and the following `Fit all` produced fresh accepted viewport
  generations without a missing tile.

The temporary browser viewport, tab, local test server and synthetic tile
directory were removed after verification.

Commit, publication, cache purge and static-tile replacement remain closed.
