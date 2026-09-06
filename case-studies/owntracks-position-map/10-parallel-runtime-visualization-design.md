# OwnTracks Parallel Runtime and Visualization Design

**Status:** Architecture and repository runtime gates complete; no live object
or endpoint activated

**Date:** 2026-08-30

## 1. Decision

The first live candidate remains additive and separately owned:

```text
three configured OwnTracks source references ----+
selection/date/path actions ---------------------+--> OwnTracks Position Map
bounded Archive Control adapter -----------------+    HTML-SDK candidate
external path anchor ----------------------------+          |
optional internal OSRM estimate -----------------+          +--> two new links
optional authenticated tile endpoint ------------+               beside old links
```

The existing OwnTracks map, its two links, local hook, selector behavior,
logging and archives are not reused as the new runtime owner. Existing SAEF
WGS84, day-window, track, ETA, archive-adapter and provider-policy candidates
are composed inside the case study. No general map helper is introduced.

The activation sequence is deliberately split. The first item now exists only
as a repository candidate and has not been installed:

1. a parallel HTML-SDK candidate can first run with the no-tile fallback;
2. a Connect authentication spike must then prove short-lived header forwarding
   with synthetic tiles only; and
3. only that evidence may open a separately authorized tile-endpoint activation.

No stage is active on the live system.

## 2. Read-Only Live Boundary

A bounded Symcon MCP probe returned only sanitized aggregates and performed no
mutation:

- one healthy Connect Control;
- one healthy WebHook Control with existing registered hooks;
- two healthy Tile Visualizations;
- both visualizations are password protected, neither permits passwordless WAN
  access, and both omit the password on their configured LAN boundary;
- one healthy existing OwnTracks map; and
- two visible existing map links under two distinct parents.

The result was accepted only with empty transport and execution errors and
`truncated=false`. No object ID, hook path, Connect address, password, source
name, coordinate or movement history was retained.

The two distinct link parents establish the additive placement contract: one
new candidate link beside each existing link. Their concrete positions remain
a native-editor decision at activation time rather than a repository constant.

## 3. Why a Symcon WebHook Is Not Automatically Trusted

The official WebHook Control documentation states that hooks are callable via
Connect Control and recommends authentication against unauthorized external
access. It also uses longest-prefix matching rather than requiring an exact
registered path. The WebServer documentation says every hook owns its own
authentication boundary.

The Tile Visualization security documentation further warns that client-origin
decisions are not reliable behind Connect or a reverse proxy. Consequently,
none of these signals grants access:

- a private or loopback `REMOTE_ADDR`;
- `Origin` or `Referer` alone;
- possession of an unguessable-looking hook path;
- the fact that the visualization itself required a password; or
- successful access to the Connect host.

A hook handler must validate the exact path and authenticate every tile
request itself. Authentication failure returns a generic response without tile
existence, coordinate, provider or credential detail.

Official references:

- [WebHook Control](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/webhook-control/)
- [WebServer](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/webserver/)
- [Tile Visualization security](https://www.symcon.de/de/service/dokumentation/komponenten/kachel-visualisierung/instanzkonfiguration/sicherheit/)
- [IP-Symcon security overview](https://www.symcon.de/de/service/dokumentation/sicherheit/)

## 4. Tile Delivery Alternatives

| Alternative | Connect/iPad behavior | Security and performance | Decision |
| --- | --- | --- | --- |
| Unauthenticated Symcon WebHook | Reachable through Connect | Exposes a high-rate map surface; visualization password is insufficient | Reject. |
| Hook protected by stable Basic Auth in renderer | Potentially reachable | Officially recognizable auth pattern, but leaves a long-lived browser credential and uncertain high-frequency UX | Reject for this candidate. |
| Authenticated Symcon WebHook with short-lived header capability | Potentially same-origin through Connect | No token in URL; requires verified Connect header forwarding, bounded capability lifetime, request limits and private caching | Conditional candidate. |
| Separate LAN tile server | Works locally | Strong service isolation and efficient static delivery, but a Connect-hosted iPad cannot reach it through the same-origin path automatically | Optional local-only authority. |
| No-tile graticule | Works locally and through Connect | No new endpoint or provider dependency | Mandatory fallback and first activation mode. |

The conditional WebHook candidate is not activation-ready merely because the
policy class accepts a synthetically verified configuration. Real Connect
forwarding, expiration, refresh and rejection behavior must be observed before
the provider mode can change from `none`.

## 5. Authenticated WebHook Contract

`OwnTracksTileAccessPolicy` keeps this design case-study-local and rejects a
Connect-reachable hook unless all of these are explicit:

```text
mode                       symcon-webhook
connectReachable           true
authenticationMode         ephemeral-header-capability
headerName                 X-SAEF-Tile-Capability
hookPathPrefix             /hook/owntracks-position-map
connectForwardingVerified  true
headerCanonicalizationVerified true
tokenTtlSeconds             60..900
refreshBeforeExpirySeconds  15..<token TTL
maximumRequestsPerMinute    30..1200
maximumConcurrentRequests   1..16
allowedMethods              GET, HEAD
```

The server owns a long-lived signing secret in private instance configuration.
The renderer receives only a short-lived audience-bound capability, keeps it
in memory and sends it as a request header. It must never appear in the tile
URL, DOM, local storage, logs, diagnostics or repository fixtures. A future
implementation must compare signatures in constant time, validate expiry and
audience before reading a tile, and refresh before expiry without retry loops.

Because the current OpenLayers adapter intentionally uses native XYZ image
loading and projects no credential, it cannot enable this WebHook mode. A
future synthetic spike must add a bounded authenticated tile loader and prove
blob cleanup, cancellation, cache behavior and failure fallback before the
provider contract is extended.

The live Symcon 9.1 transport spike additionally established that
`REQUEST_URI` omits the query component while `QUERY_STRING` retains it, and
that duplicate custom-header names collapse consistently to the last effective
value before PHP execution. Activation therefore requires both explicit query
rejection through `QUERY_STRING` and a verified single authorization authority:
only the hook handler may interpret the capability header. No proxy or other
upstream component may make a different authorization decision from the raw
header sequence.

## 6. Runtime Object Contract

The proposed runtime owns only new objects:

| Owned object | Purpose | Persistent private data |
| --- | --- | --- |
| one HTML-SDK module instance | Owns rendering, selection actions, bounded archive reads and diagnostics | three opaque source mappings, external-anchor reference, optional endpoint references |
| two links to the new instance | Place the candidate beside the two existing map links | presentation metadata only |
| optional owned WebHook registration | Serve authenticated XYZ requests after its separate proof and gate | exact owned prefix and target instance |

The module uses the native HTML visualization type and selects fullscreen HTML
when the runtime exposes that capability, following the existing MediaCarousel
reference. It does not create mirror archive variables, logging events, map
media or a second selector variable merely for presentation.

User actions cross the HTML-SDK boundary as an explicit action allowlist:

- select one configured opaque source;
- select one local calendar day;
- choose point-only, segmented-line or line-with-sampled-timestamps;
- fit all complete valid day bounds; and
- pan or zoom locally in OpenLayers.

Every source/day request increments `requestGeneration`; superseded reads stop
before further archive or ETA work. The external source remains a path
projection controlled by the established selection flow, not a fourth
OwnTracks source. Its read-only adapter accepts only the existing string
variable with ident `position` and a JSON object containing finite numeric
`lat` and `lon`. It may render only when explicitly selected, carries no
invented observation time and never expands an OwnTracks fit-all extent.

## 7. Performance Contract

Archive and renderer limits remain independent. The first activation proposal
must retain the already tested maximums of 2,500 archive records and 500
rendered points per request unless a new synthetic profile justifies a change.
Timestamp labels remain separately budgeted.

For an authenticated tile endpoint:

- only visible XYZ tiles are requested; no prefetch crawl or offline bulk
  download is permitted;
- browser and endpoint concurrency remain bounded;
- cancellation stops superseded viewport requests;
- successful immutable tiles use private caching and authentication-varying
  responses so a shared Connect cache cannot serve one session's response to
  another;
- authentication and missing-tile responses are not cached;
- endpoint diagnostics retain aggregate counts and latency buckets only, never
  capability values or raw tile sequences; and
- repeated endpoint failure switches the renderer to the no-tile fallback
  without retry amplification.

Internal OSRM remains server-side and receives only current and next-target
WGS84 coordinates. It is not called per tile or per pan event.

## 8. Coordinate Boundary

The runtime accepts OwnTracks WGS84 only. All bounds, antimeridian handling,
distance and ETA fallback remain geodetic. Navimow local `x/y`, local scale,
zone geometry and Euclidean thresholds are neither accepted nor transformed by
this runtime. This preserves the Navimow 351/352 decision boundary.

## 9. Rollback and Retention

The immutable pre-activation rollback baseline consists of the existing map,
its hook, two links, selector behavior, three sources, external anchor, logging
and archives. None is edited by the candidate.

Rollback order for a future authorized activation is:

1. hide or remove only the two new links;
2. disable and unregister only the exact hook owned by the new module, if it
   was separately activated;
3. disable or remove only the new module instance after evidence retention is
   closed; and
4. verify both existing links and the existing map remain visible and healthy.

Hook removal is destructive global routing work. It requires an exact owner,
fresh registration read-back and a separate cleanup gate; prefix matching means
that a guessed or partially matching hook must never be removed.

## 10. Remaining Gates

1. **Parallel no-tile activation gate:** after packaging verification, create
   the one module instance and two links through the native
   object/visualization workflow, without a WebHook, provider or internal OSRM
   call.
2. **Synthetic Connect authentication spike:** register a temporary, exact
   owned test hook only after separate authorization; prove authorized and
   unauthorized behavior locally and through Connect, then clean it up and
   verify removal.
3. **Authenticated tile activation gate:** only after the spike, activate the
   bounded loader and internal tile authority with independent rollback.

No gate here authorizes a commit, publication, installation, hook registration,
Connect change, provider request or visualization mutation.
