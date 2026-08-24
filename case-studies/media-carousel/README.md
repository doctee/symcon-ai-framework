# SAEF Media Carousel

**Status:** Standalone module published and bounded live validation passed;
current repository behavior includes category sources, resilient image
lifecycle, HTML-SDK fullscreen and per-device position retention

## Purpose

This case study implements an IP-Symcon HTML-SDK tile for an ordered sequence
of explicit image media objects or the current image children of a category.
It is intended as a separately testable replacement
candidate for the native content switcher when image loading, touch navigation
and resize behaviour need stronger guarantees.

The module has passed separately authorized pilot, category-source and camera
archive deployments. Native visualization configuration and retained legacy
objects remain installation-owned rollback concerns rather than module logic.

## Behaviour

- The configured source is either an ordered explicit list or a dynamically
  resolved category of IP-Symcon image media objects.
- Category mode selects a bounded number of current children and shows the
  newest object position first by default.
- A rolling deletion refreshes the bounded category sequence; an individual
  missing explicit object is skipped while other valid entries remain usable.
- Every newly created tile response contains a bounded preview of the current
  image together with the HTML shell and sequence metadata.
- A bounded 1280-pixel JPEG display image replaces the preview and remaining
  images are requested progressively in viewing order.
- The current image owns the first request slot and no more than two media
  requests may be active at once.
- The browser retains compressed sources for the sequence but renders only the
  previous, current and next image.
- A transition is committed only after the target image has fired a successful
  browser load event.
- A failed target leaves the last good image visible.
- Horizontal pointer gestures move the image with the finger.
- Large arrows and keyboard navigation remain available.
- Resize, page-show and visibility restoration re-render the current image
  without changing the sequence index.
- IP-Symcon 9.1 and newer use the HTML-SDK fullscreen visualization type;
  IP-Symcon 8.1 through 9.0 retain the compact HTML-SDK visualization type.
- The browser stores only the current sequence index and configuration revision
  in `localStorage` so compact and fullscreen contexts on one device can resume
  the same position. Compressed image sources remain limited to `sessionStorage`.

## Ownership and side effects

The module owns only its instance properties, message registrations and object
references. Category mode references the configured source category and the
currently selected image children. It deliberately has no server-side loop timer and creates no child
objects, links, variables or media objects. User interaction is local to the
visualisation client; two tablets do not change one shared server-side index.

The module reads `IPS_GetMediaContent()` only after validating a positive
ObjectID, media existence, media type and supported image extension. It never
calls a camera action or writes media content.

## Configuration

`SourceMode=list` uses `MediaItems` with the following public schema:

```json
[
  {
    "MediaID": 12345,
    "Title": "Synthetic example",
    "Enabled": true
  }
]
```

ObjectIDs above are synthetic documentation values. Installation-specific IDs
belong only in the IP-Symcon instance configuration or private evidence.

`SourceMode=category` uses `SourceCategoryID`, `CategoryItemLimit` and
`CategoryNewestFirst`. The category is resolved again for every new tile and
media request. Object position defines archive order; the media ObjectID is a
deterministic tie-breaker.

An empty explicit list leaves the module inactive. Duplicate, non-image or
unsupported explicit entries remain configuration errors. Missing explicit
objects are skipped so one expired archive object cannot suppress remaining
valid images; a list with no surviving valid entry still fails visibly. In
category mode, non-image and unsupported children are ignored, while an absent
category or a category without supported images fails with status 200.

Every browser media request carries the sequence revision. If a rolling
category changes between bootstrap and image retrieval, the module returns a
fresh bootstrap with a replacement preview instead of serving a stale index.

## Preview decision

The initial lightweight-bootstrap pilot showed that a newly created expanded
TileVisu client cannot rely on receiving an immediate HTML-SDK response. The
candidate therefore generates one 480-pixel-wide JPEG preview with GD and
embeds it in each initial tile response. A bounded 1280-pixel JPEG display
image is requested after that preview has loaded and replaces it only after
its own successful load event.

The browser keeps only three image elements active and prefetches the normal
sequence over the full viewing interval. The current display image is requested
first and the bounded request scheduler permits at most two in-flight media
requests. Image preparation uses normal load events instead of explicit
parallel `Image.decode()` calls. Preview or display-image transformation failure
falls back to the validated original media.

## Maintenance verification sequence

1. Run the offline module, distribution and deterministic fileset tests.
2. Verify the generated tree against the publication contract before any
   standalone update.
3. Publish and integrate only through the manifest-driven publisher and its
   separate hash-bound authorization gates.
4. Update an installed module only through a separate live preflight and
   postflight gate.
5. Verify initial preview, delayed display-image load, load failure and resize
   behaviour on a non-commanding rolling image category.
6. Verify the approximately ten-image production-equivalent category on a
   separate test page and verify one archive rollover.
7. Measure first-pass transport, memory and image-load timing on the target iPad.
8. On IP-Symcon 9.1 or newer, verify that both compact and expanded tile
   recreation display the preview; on older compatible versions verify the
   compact tile and native detail fallback.
9. Change native visualization placement or retained rollback objects only
   through a separate live gate.

## Repository distribution

The canonical sources are selected by
`deployments/symcon/media-carousel-module.fileset.json` into the standalone
generated tree `dist/symcon/saef-media-carousel-module/`. See
`02-deterministic-distribution.md` for the byte-exact build and integrity
contract. Standalone publication uses
`deployments/symcon/media-carousel-publication.json` through the generic
publisher.

## Sources

- [IP-Symcon HTML-SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/)
- [Official HTML-SDK media example](https://github.com/symcon/SymconTest/tree/master/HTMLVisuTestDuckCounters)
- [IP-Symcon module SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/)

No source code from the third-party Wilkware ImageViewer module is copied into
this implementation.
