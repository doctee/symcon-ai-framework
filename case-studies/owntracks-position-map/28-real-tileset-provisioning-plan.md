# Real Tileset Provisioning Plan

**Status:** Bounded private build and staged browser acceptance complete; live
transfer, WebHook/basemap activation, publication, commit and workstation
cleanup remain closed

**Date:** 2026-08-31

## Outcome

The private pilot will use a locally generated OpenStreetMap-derived raster
tileset. Its source is a bounded verified allowlisted set of four public
Geofabrik regional `.osm.pbf` extracts whose union must fully contain the
privately selected pilot extent. A dense privacy-safe polygon-union check found
no uncovered point in either envelope. Each input is clipped to that extent
before the bounded fragments are merged. OpenMapTiles provides the vector
schema and generation pipeline; a pinned full TileServer GL build renders a
pinned, fully local GL style to 256 x 256 PNG tiles. Only the finished immutable
XYZ directory is visible to the Symcon runtime.

The exact extract identifier, geographic extent and storage path are private
deployment inputs. They must not be committed, emitted in logs, returned to the
browser or copied into this case study. No OwnTracks history is used to derive
the public provisioning boundary.

The source and process decision was executed only in private staging under its
separate build and browser-acceptance gate. It is not authority to transfer the
revision to the Symcon host or activate the map.

## Why This Source

Geofabrik offers public regional OSM extracts that are normally refreshed
daily, omits contributor user identifiers from the public downloads and
publishes stable machine-readable metadata. The raw `.osm.pbf` remains an
input artifact; it is never served to the browser.

OpenMapTiles is selected because its schema and generation tooling can build
an owned vector source from OSM data. TileServer GL is selected only as a
temporary local rasterizer: the full build can render PNG tiles from a local
style, while its light build cannot. Neither service is part of the live
request path.

The style candidate is OSM Bright for OpenMapTiles, pinned by commit and copied
with all fonts, glyphs and sprites into the private build input. Its upstream
remote source, sprite and glyph URLs must be replaced before rendering. The
provisioning process runs with external network access disabled after all
hash-verified inputs have been staged.

## Pilot Coverage And Zoom Decision

The first real tileset is deliberately regional:

- coverage: the union of the two target-centred 100-kilometre WGS84 envelopes,
  expanded by one XYZ tile at every zoom as a rendering safety ring;
- envelope input: the two existing private `SharedLocation` descriptors, not
  archived movement data;
- minimum zoom: `0`;
- maximum zoom: `14`;
- pixel size: `256`;
- format: PNG; and
- overzoom: OpenLayers may visually overzoom above the source resolution, but
  the gateway must never request an authority zoom above `14`.

Zoom 14 provides local road and settlement context without turning the first
pilot into an unbounded national or street-detail archive. If a selected-day
track leaves the provisioned extent, the existing provider-free fallback is
the correct behavior. Expanding coverage or raising the zoom requires a new
revision and a separate capacity decision.

The read-only inventory predicted `28567` XYZ tiles for zooms 0 through 14.
The exact build inventory is `28692`: 125 additional corner neighbours are
required to make the one-tile safety ring complete at every zoom. The exact
result remains below both raster hard gates. This aggregate contains no
coordinates or movement history. Map coverage and ETA eligibility are
separate: the same 100-kilometre value is useful for provisioning, but the map
remains available when ETA is not.

## Hard Capacity Gates

Provisioning must stop before publication when any bound is exceeded:

| Artifact or resource | Hard bound |
| --- | ---: |
| Individual raw regional PBF input | 3 GiB |
| Aggregate staged raw PBF inputs | 5 GiB |
| Merged private-extent PBF | 1 GiB |
| Generated vector MBTiles | 8 GiB |
| Raster XYZ files | 50,000 files |
| Raster XYZ bytes | 4 GiB |
| Individual PNG | 512 KiB |
| Concurrent raster requests during generation | 4 |
| Required free workspace before start | 30 GiB |
| Retained activated raster revisions | 2 |

At a planning average of 64 KiB per PNG, 50,000 tiles occupy about 3.05 GiB;
the separate 4 GiB byte ceiling leaves bounded filesystem overhead. The
provisioner must calculate the exact XYZ inventory from the private envelope
before rendering and then verify actual file count and bytes. Estimates never
override the hard gates.

The original single-extract 1-GiB assumption failed the read-only preflight:
the private extent crosses administrative boundaries, while the examined
single cross-border extract is both incomplete for the full extent and above
that limit. A broader single extract would be about 5.8 GB. The selected
four-extract strategy keeps each allowlisted input below the revised 3-GiB
per-file bound and its current aggregate below 5 GiB; exact identifiers and
sizes remain private.

If the regional envelope does not fit after clipping and merging, the process
stops. It must not silently increase storage, reduce validation, contact an
external tile service or add a generic SAEF storage abstraction. An MBTiles
reader or continuously running internal renderer may then be reconsidered as a
new architecture gate.

## Private Manifest Contract

Every attempted revision owns one private manifest containing at least:

```text
formatVersion
revision
extracts[]
  extractId
  extractTimestamp
  sourceUrl
  publishedChecksumAlgorithm
  publishedChecksum
  sourceSha256
  sourceBytes
aggregateSourceBytes
clippedMergedPbfSha256
clippedMergedPbfBytes
privateExtent
minimumZoom
maximumZoom
tileSizePixels
openMapTilesCommit
openMapTilesImageDigests
openMapTilesExecutionArchitecture
tileServerGlVersion
tileServerGlImageDigest
tileServerGlExecutionArchitecture
styleCommit
styleFilesSha256
fontAndSpriteFilesSha256
plannedTileCount
actualTileCount
actualTileBytes
xyzInventorySha256
createdAt
```

Container tags such as `latest` are forbidden. Exact commits and image digests
must be resolved and reviewed in a read-only preflight before any pull or
download. The manifest is private because its extract and extent identify the
installation's operating region.

## Provisioning Sequence

1. Create a private staging directory on the target storage volume and verify
   30 GiB free space. Do not use the live authority root.
2. Resolve every selected Geofabrik entry from its stable JSON index. Verify
   union coverage and record each timestamp, byte size and published checksum
   before download.
3. Record exact OpenMapTiles, TileServer GL and style commits plus immutable
   container digests. Inventory licenses and attribution text.
4. At a separately authorized download gate, retrieve only those allowlisted
   artifacts, verify the published checksum and record a local SHA-256 before
   extraction or execution. A published MD5 is an integrity signal, not a
   cryptographic authenticity claim.
5. Clip each verified input to the private extent, merge the bounded fragments
   deterministically, reject duplicate/conflicting objects, then build regional
   vector MBTiles with the fixed extent and zoom range. Reject remote style,
   sprite, glyph, marker and source URLs.
6. Start the pinned rasterizer on loopback only, with a fixed allowed host and
   no external network. Render the precomputed XYZ allowlist with at most four
   concurrent requests into a new revision directory.
7. Validate every PNG through `OwnTracksTileDirectoryAuthority`, enforce count
   and byte ceilings, and compute a deterministic sorted inventory hash.
8. Make the completed revision immutable to the serving account. Never use a
   symlink as authority root.
9. Run a repository/browser acceptance test against the staged real revision.
   This remains separate from live Symcon activation.

The render step may use local HTTP internally because TileServer GL exposes
rendered PNG tiles at a style-based XYZ endpoint. That temporary loopback
endpoint is not a Symcon WebHook, is never exposed through Connect and is
stopped before activation.

## Attribution Contract

The visible map attribution for this pipeline is:

```text
© OpenMapTiles © OpenStreetMap contributors
```

It remains linked to the OpenStreetMap copyright page through the existing
provider policy. The private build manifest additionally records Geofabrik as
the extract processor and retains all source and style license notices. The
map must not be activated if the exact final attribution and licenses cannot
be verified from the pinned inputs.

## Activation And Rollback Boundary

Activation later requires one exact configuration transaction that enables
the same-origin basemap, protected WebHook access and the new immutable
authority root together. Preflight must verify exclusive hook ownership,
Connect header forwarding, root readability, revision hash, attribution and
the unchanged OwnTracks/archive boundary.

Rollback restores the immediately preceding byte-exact module configuration
and authority path. Therefore the previous configuration, module package,
private manifest and complete raster directory remain retained until the new
revision has passed the agreed observation window. Because authority roots may
not be symlinks, revision switching occurs only through the gated module
configuration, never through a filesystem link swap.

## Remaining Gates

1. Download/build gate: acquire, clip, merge and generate the private staged
   tileset.
2. Staged browser acceptance against real tiles, still outside Symcon.
3. Live activation gate for configuration, hook, basemap and rollback
   retention.

Commit, publication, routing and changes to existing OwnTracks objects remain
separately closed.

## References

- [Geofabrik public OSM extracts](https://download.geofabrik.de/)
- [Geofabrik extract technical details](https://download.geofabrik.de/technical.html)
- [OpenMapTiles generation documentation](https://openmaptiles.org/docs/generate/generate-openmaptiles/)
- [OpenMapTiles licensing and attribution](https://github.com/openmaptiles/openmaptiles)
- [TileServer GL rendered-tile endpoints](https://tileserver.readthedocs.io/en/latest/endpoints.html)
- [TileServer GL configuration and host restrictions](https://github.com/maptiler/tileserver-gl/blob/master/docs/config.rst)
