# OSM On-Miss Provider And Transport

**Status:** Repository-only provider decision and synthetic transport security
acceptance; cache integration, real network access and live activation closed

**Date:** 2026-09-01

## Decision

OpenStreetMap Standard raster tiles are selected as the only candidate for
low-volume, interactive misses outside the immutable private tile authority:

```text
https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

This is a fallback availability decision, not a change to the provider-neutral
browser contract. The browser continues to request only the protected
same-origin Symcon WebHook. Static tiles remain the primary authority and the
configured upstream remains server-private.

The official [OSMF Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)
was reviewed on 2026-09-01. OSM Standard is best effort, can block clients
without notice and permits normal interactive viewport loading only. It
requires visible attribution, an identifiable User-Agent, correct Referer
handling and origin-cache compliance. Bulk downloading, prefetching and offline
archives from this service are prohibited.

Consequently this provider must never update, seed or replace the private
static tile revision. Static revisions continue to be built from verified OSM
data extracts under the existing private provisioning workflow.

## Fixed Provider Policy

`OwnTracksOsmTileProviderPolicy` accepts exactly:

- mode `osm-standard-raster-on-miss`;
- the exact HTTPS origin and XYZ PNG path above;
- visible `© OpenStreetMap contributors` attribution linked to
  `https://www.openstreetmap.org/copyright`;
- a stable `SAEFOwnTracksPositionMap/<version>` User-Agent with a public HTTPS
  contact URL;
- one explicit HTTPS Referer origin;
- at most two concurrent upstream requests and 60 requests per minute; and
- origin cache headers, conditional requests and a seven-day fallback cache
  TTL when the origin supplies no usable expiry.

Generic library agents, missing contact information, IP-literal or reserved
placeholder origins, HTTP, alternative tile hosts and alternate style paths
fail closed. The synthetic tests use non-private public identifiers only; no
live Connect origin or personal contact value is stored in the repository.

## Transport Security

`OwnTracksPinnedHttpsTileTransport` composes the existing spatial allowlist and
miss resolver without becoming a framework helper. Before a request it:

1. accepts only the configured origin and a numeric XYZ path without query or
   fragment;
2. validates timeout, byte, redirect and public-peer requirements;
3. resolves at most eight A/AAAA addresses and rejects the complete result if
   any address is private, reserved or malformed;
4. pins one reviewed public address into cURL with `CURLOPT_RESOLVE`;
5. keeps the original hostname for TLS verification and the HTTP Host value;
6. permits HTTPS only, disables redirects and caps connect/total time;
7. aborts the body stream before it can exceed 512 KiB; and
8. verifies that the effective URL and connected peer still match the pinned
   request.

This closes DNS rebinding and redirect-based SSRF paths at both resolution and
connection boundaries. Errors expose only a bounded classification, never the
private selection, URL path, DNS response or body.

The transport reads `Cache-Control`, `Age`, `Expires`, `ETag` and
`Last-Modified`. `private`, `no-store`, `no-cache` and `Vary: *` produce a non-cacheable
result. `max-age`/`s-maxage` is reduced by `Age`; absent usable expiry falls
back to seven days. ETag and Last-Modified are returned for the later
conditional cache adapter.

## Privacy Disclosure

A real miss request discloses the requested XYZ tile index, the outbound public
IP address, the stable application User-Agent and the configured Referer origin
to OSMF. Tile indices reveal an approximate viewed region even though no raw
OwnTracks coordinate, tracker identifier, selected date or movement record is
sent.

The Referer origin can identify the Connect installation. Its exact value and
the public contact URL therefore remain private configuration and require
explicit approval before the first network request. They must not be copied to
public SAEF artifacts or diagnostics.

## Synthetic Acceptance

The repository tests prove:

- exact provider origin/path, attribution and identification requirements;
- denial of placeholder, HTTP, IP-literal and excessive-rate configurations;
- public-only DNS and mixed public/private DNS rejection before transport;
- pinned peer, effective URL, TLS, redirect, timeout and byte boundaries;
- conditional ETag/Last-Modified request headers without header injection;
- origin max-age, seven-day fallback and no-store behavior; and
- no invocation of the system cURL transport during the test gate.

PHPCS and PHPStan pass. No real DNS lookup, cURL request, provider access,
credential, module package, Symcon configuration, WebHook, static revision or
live object changed.

## Cache And Next Gates

The existing five-minute, 256-entry/16-MiB gateway cache is deliberately not
used for this provider: it cannot retain per-entry origin expiry, ETag or
Last-Modified and therefore does not satisfy the reviewed policy. Runtime
integration remains blocked until a provider-aware cache:

- persists origin expiry and validators;
- uses conditional requests after expiry;
- never stores `no-store`, `no-cache` or `Vary: *` responses;
- retains a tile for seven days only when the origin provides no usable cache
  lifetime;
- remains bounded by explicit disk, entry and revision budgets; and
- has an exact purge and rollback boundary independent of the immutable static
  directory.

That repository gate is now implemented and verified in
`51-provider-aware-tile-cache.md`. The provider-aware cache is still a
case-study-local candidate and has not been connected to the runtime.

After the runtime integration gate, a separate real-network preflight may make one
allowlisted tile request using the approved private User-Agent/contact and
Referer origin. Runtime packaging, live activation, provider transmission and
cache cleanup remain later separate gates.
