# Viewport Tile Live Activation

**Status:** Controlled package activation and independent structural postflight
complete; physical Safari and iPad acceptance pending

**Date:** 2026-09-01

## Approved Scope

This gate transferred and activated only the regenerated 36-file OwnTracks
position-map package from step 55. It did not change the complete module
configuration, provider policy, WebHook configuration, source mappings,
archives, logging, visualization links or static tile authority. It did not
publish a provider, commit repository work, purge a cache or replace static
tiles.

## Guarded Activation

The preflight independently re-established the single healthy live owner, the
active package identity, the complete configuration and provider hashes, the
WebHook and visualization-link fingerprints, all three source contracts and
their archive logging, the canonical private authority and the absence of a
candidate collision.

The exact package was transferred in bounded chunks. The receiver verified the
archive hash and size, all 36 allowlisted regular-file entries, every file size
and digest, and the package fileset identity before extracting it to an
inactive staging directory. Immediately before activation the transaction
rechecked those facts and created an exclusive byte-exact private
configuration backup.

Activation used an atomic active-to-rollback and staging-to-active rename,
followed by one targeted module reload. The complete configuration remained
byte-identical. The previous package, transferred archive and configuration
backup remain available as immutable recovery evidence.

## Independent Postflight

A separate read-only Symcon MCP probe, not the activation result itself,
verified:

- one healthy module instance with status `102` and the intended package
  identity active;
- the immediately preceding package at the exact rollback boundary;
- unchanged complete configuration, provider, WebHook and two visualization
  link fingerprints;
- three intact source mappings with variable type triples `[3, 1, 1]` and all
  nine variables still logged;
- the Connect-reachable WebHook policy still using an ephemeral header
  capability with previously verified Connect forwarding and header
  canonicalization;
- a canonical, non-linked private XYZ authority with maximum zoom 14;
- the unchanged bounded OSM-on-miss policy: at most two concurrent requests,
  30 requests per minute, eight provider requests and 4 MiB per selection,
  128 allowlisted tiles and a one-tile viewport ring;
- no staging or failed residue for the activated candidate; and
- intact retained upload, configuration backup and rollback artifacts.

The provider cache was absent and therefore empty at postflight time. No live
tile request was generated merely to populate it. The first physical map test
will consequently exercise the newly activated viewport authorization,
provider miss and cache-store path rather than succeeding from prior provider
cache content.

## Privacy And Security Boundary

The evidence contains no ObjectIDs, source keys, tracker identifiers,
coordinates, private movement history, filesystem paths or private Connect
origin. The live provider contract remains limited to requested raster XYZ tile
indices; OwnTracks WGS84 observations and ETA inputs are not sent to the tile
provider. Navimow's local coordinate system remains outside this runtime.

## Remaining Acceptance

Physical Safari and iPad testing must now verify both user-visible corrections:

1. in `Positions`, `Fit all` keeps every current point clear of the complete
   top-left controls and status badge; and
2. in `Path`, a previously uncovered day or viewport dynamically obtains
   eligible missing tiles after initial fit and after pan or zoom.

Provider publication, repository commit, rollback/upload/backup cleanup, cache
purge, static-tile replacement and any OwnTracks or visualization-object
mutation remain separate gates.
