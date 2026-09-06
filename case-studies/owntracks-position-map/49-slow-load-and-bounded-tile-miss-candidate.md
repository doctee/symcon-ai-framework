# Slow Load And Bounded Tile-Miss Candidate

**Status:** Repository-only candidate and synthetic security verification;
no upstream transport, provider configuration or live activation

**Date:** 2026-09-01

## Accepted Scope

The physical Safari follow-up established two independent issues:

1. Safari's native date control could still paint its right-hand chrome beyond
   the assigned grid cell; and
2. a selected-day request could exceed the renderer's 20-second advisory
   timer, display `timed out`, and then complete successfully a few seconds
   later.

The active immutable tile authority also remains intentionally incomplete
outside its two provisioned regional envelopes. This gate was authorized for a
repository-only correction and a synthetic, case-study-local missing-tile
fallback. It did not authorize a tile provider, credentials, outbound network
access, live configuration, package activation, commit or publication.

## Safari And Slow-Load Correction

The date input now reserves 14 pixels inside its existing grid cell for native
Safari chrome. The label, column and outer panel retain their approved position
and text size.

The 20-second timer is now an advisory slow-load notice rather than a false
failure boundary. It changes the status to `Still loading selected day…` or
`Still loading positions…` and continues accepting the matching generation.
Only an explicit `trackError` reports that the selection is unavailable.

Diagnostics expose only aggregate timing data:

- duration of the last completed selection request in milliseconds; and
- count of requests that crossed the slow-load threshold.

They contain no source label, selected day, ObjectID, tracker identifier,
coordinate or movement data.

## Reuse Before Extend

The existing protected gateway remains unchanged. It continues to own the
ephemeral Connect capability, strict GET/HEAD route, client rate and concurrency
limits, PNG validation, response-size ceiling, private read-through cache and
uniform failure response.

The fallback composes behind that gateway as a `tileReader`:

1. read the immutable static directory authority first;
2. on a miss, check a server-derived selection allowlist;
3. consult a short negative cache and hard per-selection budgets;
4. construct a URL from one fixed HTTPS origin and fixed XYZ path; and
5. pass a successful PNG back through the existing gateway validation/cache.

No general SAEF map abstraction or new reusable framework helper is introduced.

## Spatial And Transport Boundary

`OwnTracksTileSelectionAllowlist` derives XYZ ranges from one selected result's
private WGS84 fit bounds. The derivation supports the antimeridian, clamps to
Web Mercator, permits at most a two-tile viewport ring and rejects an aggregate
allowlist above its configured hard ceiling. It exposes only `allows()`, an
aggregate tile count and a one-way fingerprint; it does not expose the input
bounds or calculated ranges.

`OwnTracksTileMissResolver` accepts only:

- one HTTPS origin without user info, path, query, fragment or non-443 port;
- one fixed relative XYZ PNG template;
- maximum zoom, request, byte, response-time and negative-cache limits;
- no redirects; and
- a transport response proving a public, non-reserved peer address.

An out-of-allowlist request never invokes the upstream callback. Missing,
invalid, redirected, late or private-peer responses are negatively cached and
return the same missing result as the static authority. A new selection
fingerprint resets all request, byte and negative-cache state rather than
sharing authority between selections.

The candidate deliberately contains no HTTP client. A real adapter must prove
public DNS resolution before connection, repeat that check for the connected
peer to resist rebinding, disable redirects, enforce the byte cap while
streaming, and supply the fixed provider attribution and terms. Merely trusting
the response's peer-address field is sufficient for the injected synthetic
transport test, not authority for production networking.

## Synthetic Acceptance

The repository tests cover:

- static-authority precedence without an upstream call;
- an allowlisted fixed-origin tile success;
- denial of an unrelated world tile before transport;
- negative caching without a retry storm;
- rejection of a private peer address;
- cumulative byte-budget enforcement;
- rejection of HTTP, IP-literal, credential-bearing, path-bearing, query and
  protocol-relative configurations; and
- fail-closed rejection of an oversized geographic selection.

The OpenLayers contract test also rejects the former false timeout labels and
requires the anonymous duration and slow-count diagnostics.

The regenerated 29-file module candidate has fileset identity
`63b84dc9669406eb206c890f1e30a3336f298fd349e0756ad4357a3b9ddff91e`.
It contains only the Safari and slow-load renderer correction. The synthetic
fallback classes remain case-study-local and are intentionally not packaged
until the provider/transport gate fixes their runtime ownership.

## Remaining Gate

Missing external tiles are not yet dynamically loaded in Symcon. The next gate
must select and review exactly one provider and transport policy, including
terms, attribution, User-Agent, rate limits, public DNS/peer enforcement,
privacy disclosure, cache retention and rollback. Only after that decision may
the resolver be integrated into the runtime package and tested against a real
network endpoint. Existing live package, provider configuration, OwnTracks
objects, logging, archive and visualization remain unchanged.

That repository decision is now recorded in
`50-osm-on-miss-provider-and-transport.md`. It selects OSM Standard only for
interactive misses and keeps runtime/network activation closed until the
provider-aware cache and private identification fields are implemented.
