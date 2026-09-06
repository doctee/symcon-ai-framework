# High-Zoom And Local Detail Revision

**Status:** Repository implementation, local raster build and synthetic browser
verification complete

**Date:** 2026-09-03

## Problem

The complete-provider correction removed the mixed-style gaps at fitted path
zoom levels. Two narrower limitations remained:

- the browser and provider boundary ended at zoom 14 even when an interactive
  closer view was useful; and
- the immutable regional raster was necessarily soft when a current-position
  fit reached building-level scale.

Extending the full regional static raster through zoom 18 would create a large
and unnecessary private asset. Prefetching provider tiles is also outside the
interactive OSM Standard boundary.

## Boundary

The case-study runtime now separates two ceilings:

1. the configured basemap and provider fallback may serve visible requests
   through zoom 18; and
2. the immutable static authority may cover only a subset of those requests.

The static authority remains the first complete offline boundary through its
configured coverage. Outside that local coverage, only tiles in the currently
authorized viewport may use the existing provider-aware cache and bounded
provider fallback.

The visible request budget is 48 per selection and 60 per minute. Provider
concurrency remains two; browser gateway concurrency remains four. Cache
freshness and revalidation continue to follow the provider response headers.
No background prefetch, bulk download or route provider was added.

## Bounded Retry

The renderer retains the fast three-second recovery and adds one final
60-second retry. Each retry first obtains a fresh viewport authorization and
waits until queued and active tile requests drain before rebuilding the
protected source. A selection change, viewport change or successful
replacement cancels stale work. No third retry is possible.

The rejecting synthetic fixture reached exactly two retries and then remained
stable without additional requests.

## Local Static Detail

Zoom levels 15 through 18 were rendered locally from the already retained,
pinned vector MBTiles. The allowlist is restricted to a two-kilometre radius
plus one tile ring around each of the two existing SharedLocation ETA targets.
It contains:

| Zoom | Tiles |
| ---: | ---: |
| 15 | 128 |
| 16 | 338 |
| 17 | 1,035 |
| 18 | 3,528 |

The extension adds 5,029 PNG files and 86,978,947 bytes. The completed
immutable revision contains 33,721 files and 768,095,351 bytes. Its complete
numeric z/x/y content inventory is
`55e7e2384e62b92a4789b5be4783f26fabcf15b43016913fdd1ad642ad38c616`.

No target coordinate, movement point, tile index or private path is present in
the repository. No OSM Standard tile was downloaded while producing the
static detail revision.

## Verification

The complete OwnTracks suite, PHPCS, PHPStan, deterministic 36-file module
fileset and repository whitespace checks passed. The synthetic browser loaded
the visible viewport through zoom 18 without a missing tile and with maximum
concurrency four. A rejecting fixture proved the exact bounded retry schedule.

This remains an OwnTracks WGS84 implementation detail. It introduces no
general SAEF map abstraction and does not reuse the Navimow local coordinate
frame.
