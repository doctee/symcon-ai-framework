# SAEF Media Carousel

Preview IP-Symcon HTML-SDK module for an ordered sequence of image media
objects with an embedded initial preview, load-before-transition navigation and
client-local touch state.

## Status

Version `0.1.2` is a preview pilot candidate. Every newly created tile embeds a
bounded preview before asynchronously upgrading to the full current image.
Adding the library does not
create an instance, alter a visualisation or replace the native content
switcher. Instance creation and every live-operation step require a separate
approval.

## Installation

Add the following URL in the IP-Symcon Module Control only after the live
installation gate has been approved:

```text
https://github.com/doctee/saef-media-carousel
```

Adding the library alone does not create a `MediaCarousel` instance or change
the productive start page.

## Compatibility

- IP-Symcon 8.1 or newer
- PHP 8.2 or newer
- Tile Visualisation with HTML-SDK support

## Configuration

Create a `MediaCarousel` instance only after the live pilot gate and configure
an ordered list of existing image media objects. The module reads their media
content but does not execute camera actions, create media, retarget links or
run a server-side rotation timer.

## Integrity

`fileset.sources.json` records the canonical source, SHA-256 and byte count of
every generated payload. `fileset.sha256` identifies the complete generated
module fileset. This README and the repository license are publication
metadata and remain outside that payload hash.

## License

[PolyForm Noncommercial License 1.0.0](LICENSE)
