# Disabled Provider Runtime Integration

**Status:** Packaged repository candidate with provider fallback disabled by
default; real network access, live activation and provider transmission closed

**Date:** 2026-09-01

## Scope And Default

The case-study-local OSM policy, pinned HTTPS transport, spatial allowlist,
miss resolver, provider cache and locked miss-state store are now packaged and
connected behind `tileFallback.mode = none`.

The default configuration contains no User-Agent contact URL, Referer origin
or provider authority. `ApplyChanges()` and the WebHook return before creating
a provider cache, resolving DNS or opening a connection when the fallback is
disabled. Provider settings remain server-private and are never included in
the HTML bootstrap.

Enabling the fallback in a later gate requires all existing same-origin
basemap, Connect-protected tile-access and immutable static-directory authority
contracts to be enabled and mutually consistent. A fallback cannot operate as
a standalone public tile proxy.

## Server-Owned Spatial Authority

The browser cannot submit or widen a tile allowlist. After a valid OwnTracks
selection has been evaluated, the module validates its `fitBounds` and stores
only the latest bounded selection in private per-client runtime state. The
existing signed capability supplies the authenticated client-session key to
the WebHook.

The provider path is reachable only in this order:

1. the Connect-reachable WebHook accepts the ephemeral header capability;
2. the client has a current server-produced selection;
3. the immutable static directory reports a miss;
4. the requested XYZ index lies inside that selection plus at most the
   configured two-tile ring;
5. the per-selection request and byte budgets admit the request;
6. the provider-wide rate and concurrency budget admits the request; and
7. the pinned public-DNS/TLS transport accepts the upstream response.

Without a current selection, the same capability can read static tiles only.
An oversized geographic selection remains usable for OwnTracks data but gets
no provider fallback.

The WGS84 fit remains entirely inside the OwnTracks adapter. No Navimow local
coordinate, calibration, Euclidean distance or zone assumption enters this
path.

## Cache-Isolation Correction

The original five-minute gateway cache is deliberately bypassed whenever the
provider fallback is enabled. Otherwise a dynamically fetched tile could be
stored under the static revision and later served to another authenticated
browser session before that session's spatial allowlist was evaluated.

Static files remain the first authority and are read directly. Dynamic content
is stored only in `OwnTracksProviderTileCache`; every read from that cache is
preceded by the current selection allowlist. Sharing the same provider tile is
therefore possible only when both selections independently authorize it.

A cacheable `200` is written only after the resolver accepts the cumulative
selection byte budget. A response rejected by that budget cannot become a
later cache hit. A successful `304` accounts for zero transferred image bytes,
refreshes the existing entry and then exposes it as fresh. Provider failure or
invalid/non-cacheable revalidation never exposes stale bytes.

## Locked Runtime State

`OwnTracksTileMissStateStore` provides only the missing concurrency-safe state
that the existing request-budget helper does not own: per-selection cumulative
request/byte counters and short negative caching. It remains local to this case
study and is not promoted to a general SAEF helper.

The store is bounded to:

- 16 selection fingerprints;
- one hour retention per selection;
- 256 KiB total state;
- 256 negative entries per selection; and
- atomic, mode-0600 files below a mode-0700 instance directory.

Selection and negative-cache keys are SHA-256 fingerprints. The state file
contains no raw XYZ path, WGS84 bound, source label, selected date, tracker
identifier, contact URL or Referer origin. Corruption resets only the owned
state file; links fail closed.

## Package And Synthetic Acceptance

The deterministic package now contains 34 payload files and 36 files including
its two generated identity files. Its fileset identity is
`99150a2519e9259d4d323ec3dc183798d251a0c5fb1690b705db207ab1f1fe6f`.

Synthetic tests prove:

- static authority precedence without a provider call;
- an allowlisted static miss reaching the injected provider boundary;
- fresh provider-cache reuse without another call;
- stale provider failure returning no tile;
- conditional `304` revalidation and zero transferred-byte accounting;
- rejection outside the active selection before provider access;
- rejection and non-caching of a response exceeding the cumulative byte
  budget;
- hashed spatial miss state;
- rejection of fallback configuration without the static authority; and
- zero provider-boundary calls under the module's default configuration.

Repository and packaged runtime tests, distribution/fileset verification,
PHPCS and PHPStan operate without a DNS lookup or cURL request. No private
contact/Referer value, Symcon object, WebHook configuration, visualization,
OwnTracks object, static tile revision or live package changed.

## Rollback And Next Gate

This repository candidate is not active. Its rollback is removal of the seven
new packaged provider components and restoration of the previous 29-file
candidate identity. No live rollback action is currently necessary.

The next gate should be exactly one allowlisted real-network transport
preflight outside the live module. It requires separate approval of the exact
private User-Agent contact URL and Referer origin that OSMF will receive. The
probe must verify DNS pinning, TLS, peer address, response size, cache headers
and returned content type, then stop after one request.

Installing this package, configuring Symcon, enabling fallback for browser
traffic, publishing, committing or clearing retained cache/state directories
remain later independent gates.
