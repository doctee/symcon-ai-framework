# Private Real Tileset Build And Browser Acceptance

**Date:** 2026-08-31

## Outcome

The separately authorized bounded download/build and staged browser-acceptance
gates are complete. A private OpenStreetMap-derived revision was built without
using OwnTracks history to choose coverage. It remains outside the live Symcon
authority and has not been transferred, activated, published or committed.

The private manifest retains exact source identities, checksums, geographic
extent and storage paths. None of those installation details are copied into
this artifact.

## Sanitized Build Evidence

All four allowlisted source files passed their published checksums and local
SHA-256 checks. The bounded clips were merged deterministically; the merged PBF
was ordered, contained no multiple object versions or missing way nodes and
remained below 1 GiB.

The pinned Linux/amd64 OpenMapTiles pipeline generated a valid zoom-0-through-14
vector MBTiles database:

| Measure | Result |
| --- | ---: |
| Vector tiles | 28,356 |
| MBTiles bytes | 555,614,208 |
| SQLite quick check | `ok` |
| MBTiles ceiling | 8 GiB |

The raster inventory deliberately includes all diagonal corner neighbours in
the one-tile safety ring. That exact interpretation yields 28,692 rather than
the 28,567 preflight estimate. The conservative correction remains inside every
approved hard bound:

| Measure | Result |
| --- | ---: |
| PNG files | 28,692 |
| PNG bytes | 681,116,404 |
| Largest PNG | 82,281 bytes |
| File ceiling | 50,000 |
| Byte ceiling | 4 GiB |
| Per-file ceiling | 512 KiB |
| Render concurrency | 4 |

Every file was read through `OwnTracksTileDirectoryAuthority`. Exact inventory,
PNG signature, chunk structure, CRC, 256-by-256 dimensions, containment,
regular-file status and byte limits passed. The completed revision is
write-protected and contains no symlink.

## Renderer And Gateway Security

After the verified inputs were staged, the temporary rasterizer ran with:

- no container network and no host-published port;
- loopback-only binding and an exact loopback Host allowlist;
- a read-only root filesystem, no Linux capabilities and no privilege gain;
- read-only data, style and job mounts; and
- only the new revision directory writable.

The renderer was stopped and removed after generation. The local browser
acceptance server then bound only to loopback and read the write-protected
revision through the same authority. Valid capability requests returned PNG;
missing or wrong capabilities, query strings, unsupported methods and wrong
Host headers all returned the same 404 boundary.

This temporary listener was not a Symcon WebHook, was not exposed through
Connect and was stopped after acceptance.

## Real-Basemap Browser Acceptance

The first real run exposed one candidate defect: the tile layer was inserted
before the first selected-day result and therefore requested default-world
tiles outside the regional authority. The repository candidate now retains an
accepted capability but defers layer insertion until after the first complete
day fit. This keeps initial requests inside the intended regional view.

After rebuilding the pinned OpenLayers bundle, the relevant renderer,
tile-gateway and tile-directory tests passed. The real revision then passed at
1280 x 720, 1024 x 768 and 390 x 844:

- provider mode ready with zero tile failures;
- no horizontal or vertical document overflow;
- maximum observed tile concurrency exactly four;
- fit-all, zoom and pan operational;
- rotation disabled; and
- touch-tap tooltip pinned with timestamp and accuracy.

The browser console contained no warnings or errors. The fixture used only
synthetic points; no private movement sample was placed in repository evidence.

## Remaining Gates And Windows Runtime

The finished runtime artifact is the static XYZ PNG directory plus the reviewed
module package. A Windows 11 Symcon host does not need Docker, Colima,
OpenMapTiles, PostgreSQL, TileServer GL, Node.js or the macOS build tools. It
needs only a private local directory readable by the Symcon service account and
the later gated module/WebHook configuration.

The next gate is a private, byte-verified transfer and read-only preflight on
the Windows host. Live WebHook/basemap activation remains a later exact
configuration transaction with prior configuration backup and retained old
authority revision.

The macOS build sources, VM, images and package installations remain retained
until transfer and live acceptance are complete. Cleanup requires its own exact
gate; it must name the worktree, private staging artifacts, Colima profile,
images and Homebrew formulae separately and must not use broad cleanup or
autoremove commands.
