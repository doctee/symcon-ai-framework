# SAEF Step 398: HTML SDK Local Map Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Productive HTML SDK candidate implemented and verified offline;
browser and Symcon live visual gates remain closed

**Date:** 2026-09-12

## 1. Purpose

This step implements the design from step 396 as three bounded frontend assets
owned by `NavimowDevice`.

## 2. Frontend Assets

```text
NavimowDevice/local-map.html
NavimowDevice/local-map.css
NavimowDevice/local-map.js
```

The HTML has a deny-by-default Content Security Policy. CSS fills the complete
tile, follows Symcon light/dark content variables, keeps the map beneath
compact controls and reserves no decorative outer frame. JavaScript implements
only local view navigation and projection rendering.

## 3. Symcon Integration

`Create()` selects `INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN` when
available and retains a conservative numeric fallback for older runtimes.
`GetVisualizationTile()` assembles the three local assets and injects one
escaped bootstrap message inside the document. Later map refreshes use
`UpdateVisualizationValue()`.

The last bounded visualization message is retained in a Device attribute so a
new browser or app tile can bootstrap without a network request.

## 4. Visual Semantics

The renderer now:

- rotates the station presentation by an additional `-8` SVG degrees;
- includes stable `data-zone-id` selectors;
- includes the geometry fingerprint for client reset behavior;
- preserves marker-aware safety extents at map edges;
- applies Due and Overdue only as yellow/red zone outlines; and
- retains the existing station occupancy, directional mower, state colors,
  position freshness and legend contracts.

## 5. Statistics Strip

For every projected zone the strip shows:

- label;
- last-mowed age;
- latest-run estimated coverage percentage; and
- current-week estimated area.

Unknown values display a neutral dash. Due and Overdue labels reuse the map's
yellow/red warning semantics without replacing the zone's identity color.

## 6. Security And Bounds

- No external URL or fetch API is present.
- The server-generated SVG remains capped at 1 MiB.
- The complete visualization message is capped at the module boundary.
- Dynamic labels use DOM `textContent`.
- The bootstrap JSON uses all HTML-safe JSON escaping flags.
- Configuration errors replace content with a bounded status message.

## 7. Compatibility

The existing `LocalMap` HTMLBox variable is retained. Disabling Local Map or
analytics leaves variable identities intact. A changed geometry revision
resets client view to Fit and prevents stale zone focus.

## 8. Architecture Decisions

### AD-NAV-398-01: Ship source assets, not an external bundle

The frontend is small and dependency-free. Local assets are easier to audit
and remove network and supply-chain behavior from the tile.

### AD-NAV-398-02: Bootstrap inside the HTML document

The initial escaped message is inserted before `</body>`, matching the proven
HTML SDK lifecycle and avoiding browser-dependent handling after `</html>`.

### AD-NAV-398-03: Treat live visual evidence as a separate gate

Synthetic rendering proves markup and behavior contracts. Browser, iOS app and
actual private-map layout still require the exact published build in Symcon.
