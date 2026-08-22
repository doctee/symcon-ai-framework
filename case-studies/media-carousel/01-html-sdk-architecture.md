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

- The PHP module validates configuration and supplies media content from an
  explicit list or a dynamically resolved source category.
- The module selects the HTML fullscreen visualization type when the Symcon 9.1
  runtime exposes it and otherwise retains the HTML tile visualization type.
- Category mode bounds and orders current image children by object position,
  newest first by default, without persisting rolling child ObjectIDs.
- The initial tile contains a bounded preview of the current media so every new
  compact or expanded client has a self-contained first image.
- The browser requests a bounded display image after the preview has loaded.
- The current display image is requested before neighbours and at most two
  media requests are active concurrently.
- The browser owns sequence timing, gesture state and the current index.
- The browser prefetches bounded compressed sequence sources progressively.
- Only the previous, current and next images are attached as render slots.
- A target becomes current only after a successful browser load event.
- Media updates invalidate one client cache entry through `MM_UPDATE`.
- Every media request carries the active sequence revision. Sequence drift
  returns a new bootstrap and preview instead of applying a stale index.
- The native content switcher remains unchanged until a live pilot is approved.

The module does not create or retarget a link and does not use a server-side
timer. The module transforms supported media into bounded JPEG display payloads
and transports them as authenticated HTML-SDK messages using Data URLs,
following the official Symcon example. If transformation is unavailable for an
otherwise valid image, the validated original remains the compatibility fallback.

## Rationale

The loading problem is a presentation-state problem. Only the browser knows
whether an image has actually loaded and decoded. A server timer or link target
change cannot provide that guarantee.

Client-local sequence state also prevents independent visualisation sessions
from moving one shared server-side index.

Progressive prefetch matches the normal full-sequence viewing pattern. A
bounded initial preview avoids depending on a runtime message that can race a
new TileVisu client, while a 1280-pixel display payload retains useful expanded
quality without transporting every 1920-pixel camera original. Current-first
request ordering and an effective two-request ceiling prevent neighbour
prefetch from starving the visible image. Load-event preparation avoids
explicit parallel decoding on the target iPad. Separating compressed source
cache from the three render slots bounds intentional decoded-image ownership.

The production-equivalent archive replaces media children over time. Treating
those children as fixed configuration would make normal retention look like a
configuration failure. Category identity is therefore stable configuration;
its current media children are runtime input. Missing explicit-list entries are
also tolerated when at least one valid image remains.

## Consequences

- IP-Symcon 8.1 or newer is the candidate baseline.
- Maximized HTML uses the native Symcon 9.1 fullscreen visualization type;
  compatible older runtimes continue to expose only the compact HTML tile.
- GD image support is used for the bounded JPEG preview and display payload; a
  failed transformation falls back to the original current image.
- Large media messages require an explicit per-image size limit.
- HTML-SDK update messages may be observed by multiple connected clients; the
  content is installation-local media data and each client may reuse it.
- Browser engines may retain decoded resources beyond the three visible image
  elements. Live memory measurement is therefore still required.
- Preview generation adds one bounded media read and resize to each new tile;
  each requested display payload adds one bounded resize.
- A category rollover can replace the browser sequence and generate one new
  bounded preview; it does not create a server-side polling timer.
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

### Full original in every initial tile

Rejected because the original-image pilot still produced an unreliable blank
expanded client and unnecessarily enlarged every recreated tile response.
