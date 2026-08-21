# Media Carousel HTML-SDK Architecture Decision

**Status:** Accepted for offline candidate

## Context

The productive start page currently uses the native IP-Symcon content switcher.
It provides native full-tile media presentation, but the scheduled transition
can outpace image loading and its small arrow controls do not provide a direct
touch gesture.

An earlier experimental module changed the target of one managed link. That
preserved native media rendering but could not preload the next target, wait for
browser decode, render a gesture between two images or keep navigation state
local to one client.

The typical sequence contains approximately ten images and is usually viewed
as a complete sequence. A reduced start-page preview is acceptable only if it
is needed to meet measured transport or memory budgets.

## Decision

Implement a new and independent `MediaCarousel` module using the official
IP-Symcon HTML-SDK.

- The PHP module validates configuration and supplies media content.
- The initial tile contains no media bytes; the browser requests the current
  image after the HTML shell has initialized.
- The browser owns sequence timing, gesture state and the current index.
- The browser prefetches all compressed sequence sources progressively.
- Only the previous, current and next images are attached as render slots.
- A target becomes current only after successful browser decoding.
- Media updates invalidate one client cache entry through `MM_UPDATE`.
- The native content switcher remains unchanged until a live pilot is approved.

The module does not create or retarget a link and does not use a server-side
timer. The module transports supported media as authenticated HTML-SDK messages
using Data URLs, following the official Symcon example.

## Rationale

The loading problem is a presentation-state problem. Only the browser knows
whether an image has actually loaded and decoded. A server timer or link target
change cannot provide that guarantee.

Client-local sequence state also prevents independent visualisation sessions
from moving one shared server-side index.

Progressive prefetch matches the normal full-sequence viewing pattern without
blocking tile creation on a synchronous media read. Separating compressed
source cache from the three render slots bounds intentional decoded-image
ownership.

## Consequences

- IP-Symcon 8.1 or newer is the candidate baseline.
- Large media messages require an explicit per-image size limit.
- HTML-SDK update messages may be observed by multiple connected clients; the
  content is installation-local media data and each client may reuse it.
- Browser engines may retain decoded resources beyond the three visible image
  elements. Live memory measurement is therefore still required.
- Preview generation is deferred until measurement demonstrates need and the
  image-processing runtime has been verified.
- Expanded-tile lifecycle behaviour must be verified on the target TileVisu.

## Rejected alternatives

### Managed link target

Rejected because a native link target cannot expose a preload/decode commit
boundary or render a swipe transition between two targets.

### Undocumented native media URL

Rejected because an internal URL contract could change and may bypass the
HTML-SDK authentication model.

### Unauthenticated WebHook

Rejected because it would introduce a new externally reachable image-serving
surface and an avoidable security boundary.

### Immediate thumbnail pipeline

Deferred because it would add image decoding and resampling assumptions before
the original compressed sequence has been measured on the target client.
