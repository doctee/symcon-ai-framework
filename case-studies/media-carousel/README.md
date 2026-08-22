# SAEF Media Carousel

**Status:** Repository-complete offline candidate - no publication, live
installation or start-page replacement

## Purpose

This case study implements an IP-Symcon HTML-SDK tile for an ordered sequence
of image media objects. It is intended as a separately testable replacement
candidate for the native content switcher when image loading, touch navigation
and resize behaviour need stronger guarantees.

The productive native content switcher remains the rollback and control path
until a separately authorised live pilot has passed.

## Behaviour

- The configured source is an ordered list of IP-Symcon image media objects.
- Every newly created tile response contains a bounded preview of the current
  image together with the HTML shell and sequence metadata.
- The full current image and remaining images are requested asynchronously and
  progressively in viewing order.
- The browser retains compressed sources for the sequence but renders only the
  previous, current and next image.
- A transition is committed only after the target image has fired a successful
  browser load event.
- A failed target leaves the last good image visible.
- Horizontal pointer gestures move the image with the finger.
- Large arrows and keyboard navigation remain available.
- Resize keeps the current image and only recalculates tile geometry.
- The browser stores the current sequence index per instance and configuration
  revision in `sessionStorage` for a possible tile recreation.

## Ownership and side effects

The module owns only its instance properties, message registrations and object
references. It deliberately has no server-side loop timer and creates no child
objects, links, variables or media objects. User interaction is local to the
visualisation client; two tablets do not change one shared server-side index.

The module reads `IPS_GetMediaContent()` only after validating a positive
ObjectID, media existence, media type and supported image extension. It never
calls a camera action or writes media content.

## Configuration

`MediaItems` is a list with the following public schema:

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

An empty list leaves the module inactive. A non-empty but invalid list fails
closed with status 200. Duplicate, missing, non-image or unsupported media
entries are rejected instead of being silently reinterpreted.

## Preview decision

The initial lightweight-bootstrap pilot showed that a newly created expanded
TileVisu client cannot rely on receiving an immediate HTML-SDK response. The
candidate therefore generates one 640-pixel-wide JPEG preview with GD and
embeds it in each initial tile response. The full current image is requested
after that preview has loaded and replaces it only after its own successful
load event.

The browser keeps only three image elements active and prefetches the normal
sequence over the full viewing interval. Image preparation is serial and uses
normal load events instead of explicit parallel `Image.decode()` calls. Preview
failure falls back to the original current media for that initial response.

## Verification sequence

1. Run the offline module, distribution and deterministic fileset tests.
2. Publish the exact generated fileset only after a separate approval.
3. Install the candidate as a separate module library only after approval.
4. Add a test instance with two non-commanding image media objects.
5. Verify initial preview, delayed full-image load, load failure and resize behaviour.
6. Configure the approximately ten-image production-equivalent sequence on a
   separate test page.
7. Measure first-pass transport, memory and image-load timing on the target iPad.
8. Verify that both compact and expanded tile recreation display the preview.
9. Replace the native content switcher only through a separate live gate.

## Repository distribution

The canonical sources are selected by
`deployments/symcon/media-carousel-module.fileset.json` into the standalone
generated tree `dist/symcon/saef-media-carousel-module/`. See
`02-deterministic-distribution.md` for the byte-exact build, integrity and
future publication contract.

## Sources

- [IP-Symcon HTML-SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/)
- [Official HTML-SDK media example](https://github.com/symcon/SymconTest/tree/master/HTMLVisuTestDuckCounters)
- [IP-Symcon module SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/)

No source code from the third-party Wilkware ImageViewer module is copied into
this implementation.
