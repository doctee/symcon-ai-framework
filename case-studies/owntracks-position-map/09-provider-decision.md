# OwnTracks Position Map Provider Decision

**Status:** Gate complete; provider activation and live objects remain closed

**Date:** 2026-08-30

## 1. Decision

The first private OwnTracks pilot selects these authority classes:

- **Basemap:** internally served, same-origin XYZ tiles derived from
  OpenStreetMap data;
- **Routing:** optional internally operated OSRM reached only by the server-side
  runtime through an opaque endpoint reference;
- **Fallback:** no-tile OpenLayers graticule and an explicitly non-route-aware
  geodesic ETA; and
- **Geocoding:** disabled.

The renderer does not contain an external tile URL, route URL, API key or
credential. Direct OpenStreetMap community tiles, public OSRM demo servers and
commercial cloud endpoints are not selected authorities for private movement
data.

This decision chooses the contract and trust boundary. It does not claim that
an internal tile or OSRM service already exists, and it does not authorize
installing one.

## 2. Existing Map Boundary

A bounded read-only Symcon MCP classification found one existing OwnTracks map
instance. Its configuration and direct child string values contained no remote
URL, so the effective tile authority could not be proven without reading a
different runtime boundary. The existing map is therefore not treated as
implicit provider approval.

No source URL, host, object ID, token or rendered location was returned or
stored by that classification.

## 3. Alternatives

| Option | Privacy and ownership | Availability and credentials | Decision |
| --- | --- | --- | --- |
| Direct OSM standard raster tiles | Tile indices and client metadata leave the installation. OSMF says not to submit personal or confidential data to its services. | No key, but best effort with no SLA; visible attribution, cache behavior and usage limits are mandatory. | Reject for private production tracks. |
| Hosted commercial tiles/routing | Viewport tiles and exact route endpoints leave the installation. | Requires provider terms, credential ownership, quotas and usually billing. | Deferred to a separate explicit disclosure gate. |
| Same-origin internal XYZ plus internal OSRM | Browser tile requests remain same-origin; exact route coordinates stay within the server-side internal authority. | Internal service operation, updates, monitoring and storage are locally owned. No browser credential. | Selected. |
| No tiles and no route service | No location disclosure and no provider dependency. | Always available with the local bundle, but geographically sparse and ETA is diagnostic only. | Mandatory fallback. |

OpenStreetMap distinguishes free map data from its capacity-limited community
tile service and recommends switching providers or self-hosting when the tile
policy cannot be met. Its standard tile service forbids bulk/offline downloads
and provides no SLA. Self-hosted tile generation has material hardware and
update cost, so infrastructure activation remains separate from this renderer
gate.

OSRM exposes route requests as coordinate-bearing HTTP paths. Its public demo
is rate-limited, best effort and without uptime, latency or update guarantees.
The demo is therefore not used for ETA. A selected internal OSRM endpoint must
be resolved privately by the server-side runtime; the renderer receives only
the bounded `EtaProjection`.

## 4. Basemap Contract

`OwnTracksProviderPolicy` accepts only:

```text
mode                 none | same-origin-xyz
authorityKey         opaque private key
urlTemplate          same-origin /.../{z}/{x}/{y} template
maximumZoom          bounded 1..22
attributionText      plain text
attributionUrl       OpenStreetMap copyright URL
```

Absolute URLs, scheme-relative URLs, traversal, query strings and fragments
fail closed. A selected basemap emits no renderer credential and declares
`locationDisclosure=same-origin-tile-index`.

OpenLayers receives the attribution both on the XYZ source and in the existing
always-visible map overlay. The selected OSM-derived presentation uses
`© OpenStreetMap contributors` linked to the OSM copyright page. The no-tile
mode removes the layer and restores `OpenLayers · no map tiles`.

The same-origin endpoint must serve tiles it is authorized to serve. It must
not be a hidden pass-through that circumvents another provider's usage policy.

## 5. Routing Contract

`OwnTracksProviderPolicy` accepts only:

```text
mode                       none | internal-osrm
authorityKey               opaque private key
endpointReference          opaque server-side resolver key
profileKey                 configured internal profile
timeoutMilliseconds        bounded 100..5000
maximumRouteAgeSeconds     bounded 30..3600
allowGeodesicFallback      explicit boolean
```

No route URL or credential is projected into HTML or JavaScript. A future
transport resolves the private endpoint reference, sends only current and next
target coordinates, validates a bounded OSRM route response and passes the
existing `routeEstimate` contract to `OwnTracksEtaProjector`.

Failure, timeout, stale route data or disabled routing falls back only when
`allowGeodesicFallback=true`. The UI continues to distinguish `Route ETA` from
`Diagnostic ETA ≈ ... · no routing`.

## 6. Implementation Evidence

The gate adds:

- `candidate/OwnTracksProviderPolicy.php` for the provider/privacy contract;
- `tests/provider-policy.php` for selected, fallback and refusal paths;
- optional `TileLayer`/`XYZ` support in the pinned OpenLayers adapter;
- dynamic, plain-text attribution rendering without `innerHTML`; and
- renderer diagnostics for provider mode and actual tile-layer count.

The synthetic browser fixture still supplies `mode=none` under
`connect-src 'none'`; bundle and browser tests therefore contact no tile or
routing provider. The generated JavaScript remains below the existing 400 kB
ceiling.

A local in-app-browser regression check confirmed the disabled-provider
fallback at 1280 x 720 and an iPad-sized 820 x 1180 viewport: provider mode
`none`, zero tile layers, 48 synthetic rendered points, visible no-tile
attribution, no horizontal overflow and no browser warnings or errors. This is
fallback evidence only; it does not claim that an internal tile or OSRM service
was contacted or accepted.

## 7. Sources and Review Boundary

Primary documentation reviewed for this decision:

- [OSMF Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)
- [OpenStreetMap attribution and ODbL guidance](https://www.openstreetmap.org/copyright/attribution-guide/)
- [Switch2OSM self-hosted tile guidance](https://switch2osm.org/serving-tiles/)
- [OpenLayers 10.10 XYZ source options](https://openlayers.org/en/latest/apidoc/module-ol_source_XYZ.html)
- [OSRM backend and self-hosting documentation](https://github.com/Project-OSRM/osrm-backend)
- [OSRM demo-server limitations](https://github.com/Project-OSRM/osrm-backend/wiki/Demo-server)
- [OSRM HTTP API](https://github.com/Project-OSRM/osrm-backend/blob/master/docs/http.md)

Provider policies can change. They must be reviewed again before switching to
an external authority or deploying an internal service based on updated data.

## 8. Remaining Gate

The parallel runtime, visualization and Connect-safe endpoint boundary is now
defined in `10-parallel-runtime-visualization-design.md`. A same-origin endpoint
must not be called a WebHook unless it also satisfies that document's separate
request-authentication contract. Live activation remains closed.
