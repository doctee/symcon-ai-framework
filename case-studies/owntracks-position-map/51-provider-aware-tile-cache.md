# Provider-Aware Tile Cache

**Status:** Repository-only cache candidate and synthetic acceptance; runtime
integration, real network access and live activation closed

**Date:** 2026-09-01

## Decision

The interactive OSM-on-miss candidate receives a separate, provider-aware
filesystem cache. It composes the existing private PNG byte store instead of
duplicating its locking, atomic replacement, PNG validation, corruption
recovery and least-recently-used eviction behavior.

This cache does not alter the immutable static tile revision. Static tiles
remain the primary authority. Provider bytes and metadata use a separate
directory and a provider-revision hash, so a provider or policy revision cannot
silently reuse entries from an older contract.

No general SAEF cache or map abstraction is introduced. The implementation
remains inside this case study until a recurring second consumer establishes a
stable reuse requirement.

## Freshness And Revalidation Contract

For a cacheable `200` response, `OwnTracksProviderTileCache` stores the PNG and:

- the origin-derived expiry, capped at 30 days;
- `ETag` and `Last-Modified` validators when supplied;
- a seven-day post-expiry retention boundary; and
- bounded least-recently-used access metadata.

A fresh lookup may return PNG content to the protected gateway. An expired
lookup returns only the `stale` state and safe conditional request headers; it
does not expose stale bytes to the caller. A successful cacheable `304`
response refreshes the origin expiry and byte-retention anchor. A timeout,
transport error or invalid response cannot refresh the entry or make stale
content visible. A non-cacheable `200` or `304` also discards the prior entry.

`private`, `no-store`, `no-cache` and `Vary: *` are already classified as
non-cacheable by the pinned transport. Passing such a `200` result to the cache
discards any prior provider entry. This prevents a formerly cacheable tile from
remaining available after the origin tightens its policy.

## Hard Bounds

The provider candidate fixes these upper limits:

- 512 cached PNG entries;
- 64 MiB total PNG content;
- 512 KiB metadata manifest;
- 512 KiB per PNG, inherited from the byte store;
- at most 30 days of origin freshness;
- at most seven days of stale retention; and
- 37 days maximum byte retention between a `200` or successful `304` and
  deletion.

Metadata eviction and byte-store eviction are independently bounded. A partial
write can therefore leave only a bounded, unreadable orphan; it cannot grant
tile authority. Corrupt metadata resets to a miss without deleting unrelated
files. Symlinked roots, locks, manifests, temporary files or entries fail
closed.

## Privacy And Diagnostics

The metadata manifest contains only one-way tile/provider keys, timestamps,
validators and aggregate counters. It does not persist the provider revision,
XYZ path, OwnTracks coordinate, tracker identity, source label, selected date,
movement history, contact URL or Referer origin.

Diagnostics expose only entry/byte totals and aggregate hit, miss, write,
revalidation, discard and eviction counts. Provider transport errors continue
to use bounded classifications and do not reveal the requested tile.

## Reuse And Package Boundary

The existing `OwnTracksTileFileCache` now accepts optional retention, entry and
byte limits and provides an exact single-entry delete operation. Its existing
constructor and `forSymconInstance()` retain the previous five-minute,
256-entry and 16-MiB defaults, so the active protected static gateway behavior
does not change.

The deterministic 29-file module candidate was regenerated because that
backwards-compatible byte-store primitive is already packaged. Its fileset
identity is
`67f149020b8a2ce1c8e3f6862d8b3760053ddbe5eb822c45c0ce066e02e97fe9`.
The provider-aware cache, OSM policy, pinned transport, spatial allowlist and
miss resolver remain repository-only and are intentionally absent from this
package until runtime ownership is separately authorized.

## Synthetic Acceptance

The tests cover:

- unchanged default gateway cache behavior;
- configured byte retention beyond the former five-minute limit;
- miss, cacheable `200`, fresh hit, expiry and conditional headers;
- cacheable `304` revalidation anchored at its response time;
- transport-error behavior without stale-byte exposure;
- discard of non-cacheable responses;
- absence of conditional headers while content is still fresh;
- rejection of a `304` without matching content;
- validator header-injection rejection;
- metadata privacy, corruption recovery and unrelated-file preservation; and
- the 512-entry least-recently-used bound and explicit clear boundary.

All tests are synthetic. They perform no DNS lookup and invoke no cURL request.
No OSM request, private identification value, Symcon object, WebHook,
visualization, OwnTracks object, static tile revision or live package changed.

## Rollback And Next Gate

Before runtime integration, rollback is deletion of this repository candidate
only. Once separately integrated, disabling the on-miss provider must stop all
cache reads, revalidations and upstream requests while leaving the immutable
static authority untouched. Deleting a populated provider cache remains a
separate destructive gate; expiry and least-recently-used eviction handle
normal bounded retention automatically.

That packaging and disabled-default integration gate is now recorded in
`52-disabled-provider-runtime-integration.md`. It proves that a static miss can
reach only the synthetic provider boundary for the active server-owned
selection, a stale entry cannot be served after an error, and disabled mode
cannot reach DNS or transport.

Only a later, separately approved preflight may supply the private public
contact URL and Referer origin and issue exactly one allowlisted real request.
Live activation, publication and static-map replacement remain separate gates.
