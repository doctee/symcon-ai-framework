# Repository-Only HTML-SDK Runtime Candidate

**Status:** Implemented and synthetically verified; not packaged or installed

**Date:** 2026-08-30

## 1. Authorized Boundary

This gate implements the case-study-local HTML-SDK runtime and its synthetic
tests. It creates no live Symcon object, WebHook, link or visualization and
makes no provider, OSRM, Connect or publication request.

The candidate is deliberately not yet a self-contained installable module. It
loads its PHP, renderer and pinned OpenLayers assets from the case-study tree.
Packaging is therefore a separate preflight gate rather than an implicit part
of implementation.

## 2. Reuse Before Extend

The runtime composes the existing case-study-local components:

- `OwnTracksWgs84` for geodetic validation and distance;
- `OwnTracksDayWindow` for local-day UTC bounds;
- `OwnTracksSymconArchiveAdapter` for bounded read-only archive access;
- `OwnTracksEtaProjector` for explicitly marked geodesic ETA fallback;
- `OwnTracksProviderPolicy` for fail-closed provider configuration; and
- the pinned offline OpenLayers renderer and established HTML-SDK fullscreen
  visualization pattern.

No general SAEF map abstraction, public helper, mirror variable or custom
runtime-metadata store was added. Client cancellation state is bounded,
short-lived and private to this candidate.

## 3. Runtime Contract

Configuration requires exactly three opaque source mappings. The frontend
sends only the allowlisted `SelectTrack` action with a bounded request, an
unpredictable client-session key and a strictly increasing generation. A stale
or malformed generation is rejected before Archive Control is queried.

Each accepted request:

1. resolves the configured source and local calendar day;
2. performs the bounded position and accuracy reads through the existing
   adapter;
3. rechecks the active client generation;
4. computes only the authorized geodesic ETA fallback; and
5. emits the sampled track, complete-day bounds, optional target and external
   anchor to the renderer.

The generated tile embeds local assets and enforces `connect-src 'none'`.
Provider and routing modes other than `none` fail closed in this gate. No
WebHook is registered.

## 4. External Path Anchor

A read-only Symcon MCP schema probe found the existing external-data contract
without retaining an ObjectID, name, coordinate or movement value. The
relevant child is a string variable with ident `position`; its value is a JSON
object containing finite numeric `lat` and `lon` fields.

The runtime reads that current value only when its owning instance is
explicitly configured. The renderer shows it as a distinct anchor and expands
`Fit all` to include it. It does not treat the anchor as a fourth OwnTracks
source, connect it to the historical line or invent a timestamp. WGS84
processing remains strictly separate from Navimow local coordinates.

## 5. Synthetic Verification

The repository checks cover:

- idempotent create/apply lifecycle and exact owned references;
- exactly three sources and generic configuration failure;
- fullscreen HTML visualization output with no network authority;
- current external-anchor schema and rendering;
- client-scoped monotonic generations and pre-archive stale rejection;
- bounded Archive Control calls and complete-day track production;
- geodesic, explicitly non-route-aware ETA;
- fail-closed attempted tile-provider activation;
- deterministic OpenLayers bundle regeneration and license inventory; and
- desktop and iPad-sized browser geometry, fit, tooltip and zero-tile
  diagnostics using synthetic data only.

The candidate changes neither logging nor archive configuration and exposes no
private installation data.

## 6. Packaging Result

The deterministic packaging and installability preflight is complete and
recorded in `12-deterministic-module-packaging.md`. It created no live object,
link, WebHook, provider call, commit or publication.
