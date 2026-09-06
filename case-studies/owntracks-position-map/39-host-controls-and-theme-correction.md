# Host Controls And Theme Correction

**Status:** Repository candidate, synthetic browser acceptance, controlled live
package activation and physical Safari/Mac and iPad acceptance complete

**Date:** 2026-08-31

## Physical Safari Evidence

The first physical Safari/Mac acceptance after the source correction found
that the map, lines, labels and pan behavior worked, but the upper selection
controls did not react. `LT` was visible as the default, while `CT` fit-all
and external-path isolation could not be tested. The pointer appeared as a
hand over the selection area rather than as the native control pointer.

That pointer observation locates the primary interception at the transparent
Symcon/Ninja host chrome above the HTML-SDK document. An internal CSS stacking
level cannot overtake that parent layer. The repository also still placed
`touch-action: none` on the complete map root. Because gesture policy is
intersected across ancestors, a descendant control cannot restore native
behavior from that root policy.

## Bounded Correction

The candidate now:

- reserves a full host-chrome band before placing any selection control;
- keeps the native maximize control available in that band;
- applies `touch-action: none` and selection suppression only to the
  OpenLayers viewport;
- restores native pointer, touch and selection behavior on Source, Day and
  Path;
- removes the translucent `backdrop-filter` layer from the controls;
- gives all three fields equal width and exact matching heights;
- normalizes the Safari date value alignment;
- keeps the compact mobile row while moving navigation below it; and
- consumes Symcon's `--card-color`, `--content-color` and `--accent-color`
  variables, retaining standalone light/dark fallbacks.

The correction is presentation-only. It does not change source selection,
fit-all, path projection, ETA, archive processing, tile access or provider
authority.

## Synthetic Acceptance

A local test fixture models both a 46-pixel pointer-active host layer and a
dark Symcon palette. Browser checks proved:

- every field center resolves to its native `select` or `input`, not the host
  layer;
- Source, Day and Path have equal widths and heights;
- the field cursor is `default` while the simulated host layer remains
  pointer-active;
- the map root uses native touch behavior while the OpenLayers viewport alone
  owns pan and zoom gestures;
- a source change reaches the renderer fixture;
- Symcon card, content and accent colors become the effective map colors;
- the host layer, selection panel and navigation do not overlap at desktop,
  iPad-sized or iPhone-sized viewports; and
- neither tablet nor phone geometry introduces horizontal overflow.

The complete OwnTracks test target, deterministic fileset check and diff check
pass. The resulting 29-file candidate identity is:

```text
c6b93f60167a18ec11f1eba39d11fb36c7fdda116555ff8d594e3485b7386864
```

## Gate Boundary

The separately authorized exact-package gate and completed physical Safari/Mac
and iPad acceptance are recorded in
`40-host-controls-and-theme-live-activation.md`. Commit, publication and
retained-artifact cleanup remain separate gates.
