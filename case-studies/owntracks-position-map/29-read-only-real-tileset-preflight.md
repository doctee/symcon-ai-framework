# Read-only Real Tileset Preflight

**Date:** 2026-08-31

## Outcome

The approved read-only preflight verified the two existing provider-neutral
target descriptors, fixed ETA eligibility to a strict 100-kilometre radius and
calculated a bounded real-tile inventory. It did not download map data, pull or
run a container, create a tileset, mutate Symcon or activate a provider.

The previous assumption that one administrative extract below 1 GiB would
cover the pilot is rejected. The private coverage crosses administrative
boundaries. Four adjacent public regional extracts form the bounded allowlist
and are clipped before merge. A dense polygon-union check found no uncovered
point. Extract identifiers, target descriptors, coordinates and checksums
remain private.

## Read-only Live Evidence

Symcon MCP confirmed one active pilot with exactly two valid referenced
`SharedLocation` descriptors. Both references had the expected module type and
valid WGS84 configuration. Transport and PHP execution completed without error
or truncation. Object IDs, location keys, names and coordinates were discarded
from this artifact.

The rule is exact: a target is ETA-eligible only at a geodesic distance
strictly below `100000` metres from the latest quality-approved current
position. At or above that boundary, or when neither target is eligible, ETA is
`unavailable` with reason `outside-target-radius`. The selected-day path and
fit-all remain available.

## Sanitized Coverage And Capacity

The private planning calculation used the union of the two target-centred
100-kilometre WGS84 envelopes and added one XYZ tile of safety padding per zoom.
It did not inspect or derive a boundary from movement history.

| Measure | Result |
| --- | ---: |
| Zoom range | 0-14 |
| Planned XYZ tiles including safety ring | 28,567 |
| Planning bytes at 64 KiB per PNG | about 1.74 GiB |
| Raster file ceiling | 50,000 |
| Raster byte ceiling | 4 GiB |

The planned inventory is inside both raster ceilings. A day path outside this
private authority receives the existing provider-free fallback rather than an
external tile request.

## Source Preflight

Public source metadata showed:

- the four allowlisted inputs each remain below the revised 3-GiB per-file
  source ceiling;
- their current aggregate is below the new 5-GiB staging ceiling;
- the examined single cross-border extract is about 2.2 GB and does not contain
  the full private 100-kilometre union; and
- the next broader single extract is about 5.8 GB and is rejected as needlessly
  broad.

The approved process therefore stages only the private allowlist, verifies each
published checksum, clips every input to the private extent, merges the bounded
fragments deterministically and requires the merged PBF to stay below 1 GiB.

The read-only coverage check evaluated `405680` points across both complete
100-kilometre envelopes: an approximately 500-metre interior grid and an
approximately 100-metre boundary grid. No point was uncovered. Earlier smaller
three- and four-extract candidates produced explicit gaps and were rejected.
The source identities and gap positions were not retained in this artifact.
The build must repeat coverage validation against the downloaded revisions;
any gap, duplicate/conflicting object or capacity failure stops the build.

## Pinned Upstream Provenance

The following immutable repository revisions were resolved without cloning:

| Component | Version | Commit |
| --- | --- | --- |
| OpenMapTiles | `v3.16` | `c33503af14926d68ea47f1ab7ca4d783ab544f37` |
| TileServer GL | `v5.6.0` | `000c365f3d6948733355be167f09d5585697c4c6` |
| OSM Bright GL style | `v1.11` | `65c699326edce05da9bbda2c53116314259f1503` |

OpenMapTiles code is BSD-licensed and its schema/cartography is CC-BY with
visible attribution. TileServer GL is BSD-2-Clause. OSM Bright code is BSD
3-Clause and its visual design is CC-BY 4.0. The OSM-derived data is ODbL and
retains OpenStreetMap attribution. The style remains a staged input: its vector
source, sprite and glyph URLs must be replaced with local files and the pinned
license notice retained before execution.

The pinned compose graph fixes OpenMapTiles tool version `7.2`. Public registry
metadata resolved the complete Linux/amd64 execution set without a pull:

| Image | Immutable Linux/amd64 manifest |
| --- | --- |
| `openmaptiles/postgis:7.2` | `sha256:6954f0b767b00ca2c3dbd65eb6dccddc7578f6f02dfe963eb1e9ca61c656fa27` |
| `openmaptiles/import-data:7.2` | `sha256:ba3f3004e31a34f42d3cfe1560c5d044c5acc44c2098e85626134ad42ac4f5da` |
| `openmaptiles/openmaptiles-tools:7.2` | `sha256:d7ea1281420eebf2ed0cfc704917026a838f73bdae42a4f877323a00785e1046` |
| `openmaptiles/generate-vectortiles:7.2` | `sha256:21504b36934472ca0e1189991dc0b4768d06404b5d5520929eca085653ceffc9` |
| `maptiler/tileserver-gl:v5.6.0` | `sha256:f5f954587478ca6be606f834fba1880b5ddd9c958132ded79a573bc1790a8bf0` |

The current workstation is arm64, while all four generation images above are
published only for Linux/amd64. TileServer GL has an arm64 image, but mixing
architectures would weaken reproducibility. The build gate must therefore use
an explicitly selected Linux/amd64 runner, or separately prove emulation and
performance before execution. It must reference the full manifests above,
never mutable tags. Repository provenance and source coverage are therefore
closed for the later download/build gate.

## Security And Mutation Boundary

- No source archive, container layer, font, sprite or tile was downloaded.
- No local renderer or HTTP listener was started.
- No Symcon object, configuration, hook, archive or visualization was changed.
- No provider endpoint was exposed and no Connect request was made.
- No private coordinate, tracker identity, ObjectID or movement sample is
  present in this artifact.

Download/build, staged browser acceptance, live WebHook/basemap activation,
commit and publication each remain separate gates.

## References

- [Geofabrik extract technical details](https://download.geofabrik.de/technical.html)
- [OpenMapTiles v3.16](https://github.com/openmaptiles/openmaptiles/releases/tag/v3.16)
- [TileServer GL v5.6.0](https://github.com/maptiler/tileserver-gl/releases/tag/v5.6.0)
- [OSM Bright GL style v1.11](https://github.com/openmaptiles/osm-bright-gl-style/releases/tag/v1.11)
- [OSM Bright license](https://github.com/openmaptiles/osm-bright-gl-style/blob/master/LICENSE.md)
- [TileServer GL image tags](https://hub.docker.com/r/maptiler/tileserver-gl/tags)
