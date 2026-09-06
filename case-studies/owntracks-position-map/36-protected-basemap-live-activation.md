# Protected Basemap Live Activation

**Status:** Active; routing remains disabled

**Date:** 2026-08-31

## Outcome

The private regional basemap is active on the corrected SymconTest lifecycle
package. The activation changed only the pilot's provider configuration and
performed one `ApplyChanges()`. The pilot remained healthy, and every
non-provider property, all three source configurations, required logging
states, both additive links and persistent WebHook Control configuration
remained unchanged.

The active package retains fileset identity
`a60d43f3432997797f48eb0d6ddc5426ec4265340aaf89aa3c403151991008eb`.
Both older package generations remain available for rollback.

## Active Provider Boundary

The active contract is limited to:

- one same-origin XYZ basemap;
- one pre-provisioned, read-only private tile directory;
- zoom levels zero through fourteen;
- a native Strict hook at the module-owned path;
- five-minute, memory-only header capabilities refreshed one minute before
  expiry;
- at most 240 requests per minute;
- at most four concurrent requests; and
- private browser-side blob URLs that are revoked after image loading.

Routing remains `none`. The browser receives neither the private filesystem
authority nor a persistent credential. The visualization CSP permits only
same-origin tile connections and contains no external tile host.

Persistent WebHook Control remains byte-identical and has no OwnTracks entry.
That is expected because the corrected module uses the native volatile Strict
hook lifecycle.

## Security Postflight

Before and after activation, requests without a capability, with an invalid
capability and with an unsupported method produced indistinguishable short
`404` responses with `no-store` and `nosniff`. The positive browser path issued
only a short-lived in-memory capability through the HTML-SDK action bridge.

The embedded bootstrap was decoded structurally and proved:

- `basemap.mode = same-origin-xyz`;
- `tileAccess.mode = symcon-webhook`;
- ephemeral header-capability authentication;
- a concurrency ceiling of four; and
- no browser-visible tile authority.

An independent read-only Symcon postflight repeated the package,
configuration, ownership and CSP checks after browser acceptance.

## Browser Acceptance

The internal browser reached the private visualization through Connect and
rendered real map tiles for a bounded current-day source without exposing its
identity or coordinates in repository evidence.

Desktop acceptance proved:

- real basemap imagery and attribution;
- zoom, drag and fit-all interaction;
- 30 completed tile requests with no failures;
- no leaked blob URLs;
- maximum observed concurrency of four; and
- rotation fixed at zero.

At a 1024 by 768 iPad-sized viewport, the real map remained visible, the page
did not scroll during a map drag, tile authorization stayed ready and the
controls remained usable.

At a 390 by 844 iPhone-sized viewport, the effective map viewport was 366 by
820 pixels. Selection and navigation controls stayed within the viewport and
did not overlap. Dragging did not scroll the page; zoom and fit-all remained
operational. After settling, 63 tile requests had completed successfully,
none had failed, all temporary blob URLs were revoked, concurrency never
exceeded four and rotation remained zero.

These are controlled internal-browser responsive and pointer tests, not a
claim about a physical iOS Safari session. Physical iPhone/iPad acceptance can
be recorded separately without changing the active configuration.

## Rollback And Remaining Gates

Configuration rollback restores the exact provider-free property while
leaving the stable disabled hook inert. Package rollback remains independently
available through the two retained generations.

Route-aware ETA, OSRM or another routing service, publication, commit and
cleanup of retained package/build artifacts remain separate gates.
