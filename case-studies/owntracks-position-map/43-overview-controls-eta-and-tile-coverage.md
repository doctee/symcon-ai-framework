# Overview Controls, ETA And Tile Coverage

**Status:** Repository candidate, automated contract acceptance, responsive
internal-browser acceptance and exact-package live activation complete;
physical live acceptance pending

**Date:** 2026-09-01

## Accepted Physical Feedback

The previously activated current-overview package passed physical iPad use.
That acceptance exposed seven bounded follow-ups:

1. close current positions must remain individually visible;
2. the overview must show `All` and today in disabled controls;
3. missing regional tiles must not disable the complete basemap;
4. ETA belongs only to the current overview;
5. the `Positions`/`Path` selector belongs first;
6. control heights and widths need another compact refinement; and
7. path direction arrows need materially stronger visibility.

## Repository Correction

The renderer now places the view selector before Source and Day. In
`Positions`, Source is rebuilt as `All`, Day is set to the bootstrap date for
today, and both controls are disabled. Switching to `Path` restores the last
selected source and day. The disabled state is visually subdued without
changing the approved text sizes.

When current markers fall within 24 screen pixels, they are deterministically
fanned out around their true projected coordinates. Thin leader lines retain
the WGS84 attribution and labels receive separate vertical offsets. The
displacement is presentational only: fit bounds, tooltips and all calculations
continue to use the original geodetic coordinates and the fan collapses once
zoom makes the points distinguishable.

Path arrows are now placed at interval midpoints rather than underneath point
markers. Every line segment with at least two points receives an arrow, with a
maximum of twelve per segment. A larger triangle, contrasting fill, stronger
halo and higher layer order keep the arrow visible on both map and dark skin.

## ETA Ownership And Performance

`Path` no longer resolves a target, reads motion activity for ETA or exposes an
ETA result. Its ETA panel is hidden.

`Positions` evaluates ETA per labelled source. It first rejects stale current
positions and positions outside the strict 100-kilometre target radius without
an archive read. Only an eligible source receives a bounded 30-minute archive
and activity window plus at most one predecessor needed for boundary
attribution, capped at 1,000 records and 250 rendered evidence points.
Available or reached ETAs are shown with their source label; unavailable
sources do not obscure an available result from another source.

This remains case-study-local composition of the existing archive adapter,
motion-aware target resolver, WGS84 distance and ETA projector. No general SAEF
map or ETA API is introduced.

## Missing Tiles Versus Missing Coverage

The active immutable tile revision was intentionally provisioned only for the
two target-centred regional envelopes. A selected-day path outside that extent
therefore requests valid but absent XYZ files. Previously four consecutive
rejections removed the complete tile layer, including already available tiles,
and displayed `map tiles unavailable`.

The repository correction treats a rejected individual tile as unavailable
content for that tile only. It retains the protected layer, attribution,
capability refresh and every successful tile. Capability issuance errors,
expiry, invalid capability messages and invalid provider contracts still
disable the layer fail-closed. The browser records a bounded missing-tile
counter for diagnostics but receives no private authority path or coverage
extent.

This correction preserves partial coverage; it cannot manufacture a map tile
that is absent from the immutable authority.

## Bounded Dynamic Loading Decision

Dynamic loading is feasible through the existing same-origin gateway and file
cache, but it is not merely a cache switch. An upstream tile request reveals
the requested XYZ region to the configured provider and adds outbound-network,
licence, rate-limit, SSRF and availability authority. It therefore remains a
separate provider and privacy gate.

A safe candidate must require all of the following:

- a single configured HTTPS origin and fixed provider-specific path template,
  never a browser-provided URL;
- explicit provider terms, attribution, User-Agent and request-rate policy;
- a capability-scoped XYZ allowlist derived server-side from the selected
  source/day fit bounds plus a small viewport ring;
- maximum zoom, per-selection tile count, byte, concurrency and wall-time
  ceilings;
- the existing byte-validated PNG boundary and bounded LRU file cache;
- negative caching for absent tiles and no retry storm;
- an explicit disclosure that the upstream sees requested tile indices; and
- exact configuration, cache and provider rollback boundaries.

Limiting selectable history to 30 days is a useful additional archive and
privacy bound, but does not by itself bound tile count: one day can cross a
large geographic area and repeated pan can request unrelated tiles. A 30-day
limit must therefore be combined with the per-selection spatial allowlist and
hard tile budgets above. No dynamic upstream, 30-day restriction or provider
configuration is implemented or activated in this gate. The later repository
candidate in `49-slow-load-and-bounded-tile-miss-candidate.md` implements the
synthetic allowlist and budget boundary only; provider and network authority
remain closed.

## Verification And Remaining Gates

Runtime, packaged-runtime, renderer, security, distribution, fileset,
performance, PHPCS and PHPStan checks cover the new contracts. A later retry
restored the internal-browser route and completed responsive acceptance at
1280 x 720, 1024 x 768 and 390 x 844 pixels. The checks confirmed:

- no document overflow at any tested size;
- immediate `Positions`/`Path` and source selection;
- disabled `All` and current-day controls in `Positions`;
- restored source and day controls in `Path`;
- two deterministic current-position displacements for the close-marker
  fixture without changing the geodetic fit;
- ETA visibility only in `Positions`;
- one path line with twelve direction-arrow features and viewport-dependent
  timestamp decluttering;
- source-local `Fit all`; and
- zero map rotation with the Symcon dark-skin fixture.

The offline fixture deliberately had no provider tiles. This acceptance proves
the responsive renderer and individual-feature contracts, but does not grant
or simulate dynamic upstream tile access.

The separately authorized package-only live gate activated the exact 29-file
candidate without applying or changing instance configuration. Its independent
postflight confirmed the three-source browser contract, overview mode,
direction-arrow renderer, same-origin content-security policy, unchanged
provider configuration, source/archive evidence, WebHook inventory and
visualization links. The preceding exact package, complete previous
configuration and transferred archive remain retained for rollback.

Physical live acceptance, dynamic provider access, a new tile revision, a
30-day history restriction, commit, publication and retained-artifact cleanup
each require a separate gate.
