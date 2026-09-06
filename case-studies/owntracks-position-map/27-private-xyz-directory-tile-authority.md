# Private XYZ Directory Tile Authority

**Status:** Repository implementation and synthetic verification complete;
live activation and tile provisioning remain closed

**Date:** 2026-08-31

## Outcome

The selected internal authority is a read-only directory containing a
pre-provisioned XYZ PNG tileset. The runtime performs no network request and
has no provider URL, credential, downloader or directory-enumeration API. This
is the smallest provider-neutral authority that can complete the existing
protected same-origin gateway without introducing a general SAEF map
abstraction.

The repository can represent and validate an enabled configuration, but its
default remains `mode: none`. No live hook, basemap, provider, archive,
OwnTracks object or visualization was changed in this gate.

## Architecture Comparison

| Candidate | Runtime and privacy boundary | Decision |
| --- | --- | --- |
| Direct proxy to the OSM community raster service | Makes a provider request on cache misses, discloses requested tile indices and couples the private runtime to service policy, attribution, cache and availability rules. It is not an offline archive service. | Rejected. |
| MBTiles reader | Keeps reads local but adds SQLite/runtime and packaging complexity before a second storage format has demonstrated a need. | Deferred. |
| Private static XYZ directory | Local read-only lookup, no network or credentials, simple immutable revision boundary and direct composition with the existing gateway/cache. | Selected. |

The directory is a serving boundary, not a provisioning mechanism. A later
tileset import must separately prove the data source, license, attribution,
update process and storage bounds. Tests generate only synthetic PNGs and do
not download or bundle OpenStreetMap tiles.

## Configuration Contract

`OwnTracksTileDirectoryAuthority` accepts exactly one enabled mode:

```text
mode                 private-xyz-directory
rootPath             canonical absolute non-root directory
tileSetRevision      bounded opaque revision, 1..64 characters
minimumZoom          0..22
maximumZoom          minimumZoom..22
tileSizePixels       256 | 512
```

The effective provider configuration keeps four responsibilities distinct:

- `basemap` is the browser presentation and attribution contract;
- `tileAccess` is the Connect-safe WebHook authentication and budget policy;
- `tileAuthority` is the private server-side content owner; and
- `routing` remains disabled.

Basemap, access and authority must be either all disabled or all enabled. In an
enabled repository configuration the URL must be exactly
`/hook/owntracks-position-map/{z}/{x}/{y}.png`, and basemap and authority must
share the same maximum zoom. The authority root is never included in bootstrap
data, HTML, JavaScript, response headers or debug classifications.

## Read And Security Boundary

The reader accepts only integer `z/x/y` coordinates within the configured XYZ
bounds and constructs only the numeric path `root/z/x/y.png`. It rejects:

- relative, root, missing, non-canonical or symbolic-link authority roots;
- linked or non-directory zoom/x components and linked/non-regular tile files;
- paths whose resolved file leaves the canonical root;
- files larger than 512 KiB or changed/truncated during a bounded locked read;
- malformed PNG chunk structure, CRC errors, duplicate/non-leading `IHDR`,
  wrong pixel dimensions, absent `IDAT`, or trailing bytes after `IEND`.

Missing or unsafe content is indistinguishable at the gateway and returns the
existing generic not-found response. Authentication and request budgeting run
before cache and authority access. A valid authority read is then stored in the
existing bounded revision-aware private cache.

## Coordinate Boundaries

XYZ indices are only a raster lookup address. OwnTracks positions remain WGS84
and all distance/ETA calculations remain geodesic in the OwnTracks adapter.
No XYZ pixel coordinate is passed to that logic. The Navimow local coordinate
frame remains uncalibrated and Euclidean and is not accepted by this authority
or renderer contract.

## Verification

The repository matrix covers valid and missing reads, XYZ/zoom bounds, wrong
dimensions, corrupt CRC, oversized content, symbolic-link escape attempts,
unsafe roots and configuration bounds. Runtime tests prove the default still
registers no hook or secret and does not disclose `tileAuthority` in browser
bootstrap data. Gateway and WebHook security regressions remain green.

The browser fixture provisions synthetic tiles into a temporary private XYZ
directory and then serves them through the same strict directory reader. At
1280 x 720, 390 x 844 and 820 x 1180, the protected layer remained ready,
rendered all 48 synthetic points, showed no horizontal overflow and observed at
most four concurrent tile requests. Zoom and `Fit all` retained the ready
layer. The responsive layouts and attribution were visually checked. This
exercises the selected authority without a provider or external network call.

The deterministic 29-file repository package identity is:

```text
839a1881efa8ed1fc8359664856c829f36143b39039c6db3ca9b3311739bcd93
```

## Remaining Gates

Separate authorization is still required for:

- resolving and provisioning the real tileset selected in
  `28-real-tileset-provisioning-plan.md`;
- configuring the private authority path and attribution;
- enabling the basemap, protected hook and capability issuance in the live
  pilot;
- activating any routing authority;
- committing or publishing repository artifacts; or
- changing any existing OwnTracks object or map.

## References

- [OSMF Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)
- [OpenStreetMap attribution guidance](https://www.openstreetmap.org/copyright/attribution-guide/)
