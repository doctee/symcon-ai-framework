# Protected Tile Gateway Candidate

**Status:** Repository implementation and synthetic browser gate complete;
live WebHook, Connect acceptance and tile authority remain closed

**Date:** 2026-08-31

## Outcome

The case study now contains the bounded execution layer that was missing from
the existing provider and tile-access policies:

- short-lived HMAC capabilities bound to one audience and client session;
- an exact-path, provider-neutral XYZ PNG gateway core;
- an authenticated OpenLayers `ImageTile` loader; and
- a deterministic loopback-only tile fixture for desktop and touch-sized
  browser verification.

The implementation contacted no map provider, registered no Symcon WebHook,
changed no live module and contains no installation credential. Its synthetic
tiles are generated locally and are deliberately not geographic map data.

## Reuse Before Extend

`OwnTracksProviderPolicy` remains the only basemap configuration contract.
`OwnTracksTileAccessPolicy` remains the Connect, header, lifetime, rate and
parallelism contract. The new classes execute those case-study-local
contracts; they do not introduce a general SAEF WebHook, capability or map
helper.

## Capability Contract

`OwnTracksTileCapability` issues an opaque two-part HMAC-SHA256 token. Its
bounded claims contain only version, audience, random client-session key,
issue/expiry times and a random capability identifier. The token:

- requires a 32-to-256-byte private signing secret;
- lives for 60 to 900 seconds;
- is checked with `hash_equals()`;
- rejects unknown claims, malformed base64url, wrong audience, future issue
  time, expiration and signature drift; and
- is never placed in a URL, DOM node, diagnostic snapshot, local storage or
  repository production configuration.

The client receives it only through the established HTML-SDK action/message
boundary and retains it in JavaScript memory.

## Gateway Pipeline

`OwnTracksTileGateway` processes a request in this order:

```text
exact request shape and path
  -> GET/HEAD allowlist and empty body
  -> bounded XYZ coordinates
  -> one exact capability header
  -> capability signature, audience and expiry
  -> per-capability minute and concurrency limits
  -> bounded cache lookup by tileset revision and XYZ
  -> injected internal tile reader on a miss
  -> bounded PNG validation before insertion
  -> private response
```

Only `/hook/owntracks-position-map/{z}/{x}/{y}.png` is accepted. Prefix
extensions, query strings, out-of-grid coordinates and bodies fail closed.
Authentication and path failures return the same generic non-cacheable 404.
Successful responses are `image/png`, `private`, capability-varying and
`nosniff`; HEAD returns the same bounded metadata without a body. Tile content
is limited to 512 KiB.

The gateway accepts an injected tile reader rather than a provider URL. Its
bounded cache is shared only after authentication and never keys on a
capability. It is therefore not a hidden pass-through to public community
tiles and does not grant authority to fetch or mirror any provider. The exact
cache contract and evidence are recorded in
`22-bounded-tile-cache-candidate.md`.

## Browser Loader

The pinned OpenLayers bundle now uses its current `ImageTile` promise loader.
It:

- asks for a short-lived capability through `RequestTileCapability`;
- sends it only in `X-SAEF-Tile-Capability` with same-origin credentials;
- follows no redirect and sends no referrer;
- limits parallel work to the configured maximum;
- consumes OpenLayers abort signals for superseded tiles;
- accepts only successful PNG responses up to 512 KiB;
- revokes every temporary blob URL after a bounded render interval;
- refreshes the capability once before expiry without an automatic retry
  loop; and
- removes the tile layer after four consecutive failures while preserving the
  track, controls and no-tile fallback.

The graticule is visible only in fallback mode. Once a basemap is active, it is
hidden so that the map source owns geographic presentation.

## Automated Evidence

The gateway matrix covers valid GET and HEAD, missing/tampered/expired/wrong-
audience capabilities, exact-path refusal, query and body refusal, XYZ bounds,
invalid PNG, private cache headers, response token absence, rate limiting and
parallelism limiting.

All OwnTracks tests, deterministic 25-file packaging, PHPStan and PHPCS pass.
The repository package identity is:

```text
4b41004e610a4be0b756b20f61f5a008cd5d629fd88533772dce9ef908d38296
```

This hash identifies repository output only. It has not been activated or
published.

## Browser Evidence

The internal browser loaded only a loopback fixture and verified:

| Viewport | Result |
| --- | --- |
| 1280 x 720 desktop | Protected tiles visible, 48 points visible, zoom and Fit all retained, no horizontal overflow |
| 390 x 844 phone | Protected tiles visible, controls and navigation do not overlap, `touch-action: none`, page remains unscrolled during map drag |
| 820 x 1180 tablet | Protected tiles visible, controls and navigation do not overlap, no horizontal or vertical overflow |

Across the checked viewports the observed tile concurrency never exceeded
four and no authorized tile failed. The desktop run returned its temporary
blob-URL balance to zero after the five-second cleanup interval. A shortened
synthetic lifetime produced exactly one pre-expiry capability refresh. A
separate wrong-capability run produced four failed requests, removed the tile
layer, retained all 48 track points and emitted no browser warning or error.

## Remaining Gates

This repository result does not yet make the live screenshot a geographic
map. Three independent authorities remain:

1. integrate capability issuance and the gateway adapter into the private
   pilot runtime while keeping its default mode `none` (completed in
   `26-default-disabled-webhook-runtime-integration.md`);
2. register one exact temporary synthetic WebHook and prove header forwarding,
   authorized/refused behavior and verified cleanup locally and through
   Connect (completed in `25-synthetic-connect-webhook-acceptance.md`); and
3. select and operate an authorized internal OSM-derived XYZ tile source,
   then activate it for only the new pilot in a separate live gate.

None of those actions, nor a commit or publication, is authorized by this
gate.
