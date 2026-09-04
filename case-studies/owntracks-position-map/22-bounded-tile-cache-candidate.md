# Bounded Tile Cache Candidate

**Status:** Repository implementation and synthetic verification complete;
live persistence and tile authority remain closed

**Date:** 2026-08-31

## Outcome

The protected gateway now owns a bounded, provider-neutral read-through cache.
It reduces repeated reads from a future internal tile authority without moving
authentication, provider selection or persistence into a shared SAEF helper.

No live WebHook, Symcon object, provider endpoint or existing OwnTracks object
was read or changed for this step.

## Security And Lookup Order

The request pipeline remains fail-closed:

```text
request and XYZ validation
  -> capability verification
  -> rate and concurrency limits
  -> cache lookup by tileset revision plus z/x/y
  -> injected internal tile reader on a miss
  -> PNG validation before cache insertion
  -> private response
```

An unauthenticated caller cannot probe a cached tile. The capability and its
identifier are not part of the cache key or cached value. Valid capabilities
can therefore reuse the same tile while rate and concurrency accounting
remains capability-specific.

## Bounds And Invalidation

The case-study-local cache has deliberately fixed bounds:

- 300-second entry lifetime;
- at most 256 entries;
- at most 16 MiB total tile content;
- at most 512 KiB per PNG, inherited from the gateway; and
- least-recently-used eviction when an entry or byte boundary is exceeded.

The caller must supply a 1-to-64-character `tileSetRevision`. It partitions
cache entries independently of a provider name. Changing tile content,
styling, licensing boundary or the owned internal tileset requires a new
revision and cannot accidentally reuse the previous tiles.

The initial cache state boundary was subsequently replaced by the bounded
file-backed runtime adapter in `23-tile-cache-runtime-adapter.md`. Gateway
rate/concurrency state remains separate from tile payload persistence.

## HTTP And Client Cache

Successful responses remain `private, max-age=300` and vary on the capability
header. Browser and OpenLayers session caching are therefore independent of
the new server-side read-through cache. Authentication failures, unavailable
tiles and rate limits remain `no-store`; failures are never negative-cached.

## Verification

The automated gateway matrix proves:

- one source read for the same tile through two different valid capabilities;
- cache partitioning after a tileset revision change;
- a fresh source read after the 300-second lifetime;
- capability rejection before cache or source access;
- eviction at 257 entries; and
- eviction before total content exceeds 16 MiB.

The protected loopback browser fixture is then repeated at desktop, phone and
tablet sizes to ensure the cache change does not alter rendering, touch pan,
zoom, fit-all or the protected failure fallback. The fixture contains only
synthetic tiles and synthetic positions.

The checked 1280 x 720, 390 x 844 and 820 x 1180 viewports rendered all 48
points over protected synthetic tiles with zero tile failures and an observed
maximum concurrency of four. Phone and tablet views had no overflow or
control collision and retained `touch-action: none`. The wrong-capability run
stopped after four failures, removed the tile layer and retained all 48 track
points. The temporary loopback server and viewport override were removed
afterward.

The deterministic 25-file repository package identity is:

```text
4b41004e610a4be0b756b20f61f5a008cd5d629fd88533772dce9ef908d38296
```

## Remaining Gate

The cache does not make a geographic map available. Live adapter integration,
an exact temporary Connect forwarding test, internal tile-authority selection
and pilot activation remain separately gated. No provider publication, commit
or live mutation is authorized by this result.
