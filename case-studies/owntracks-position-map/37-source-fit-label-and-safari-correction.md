# Source, Fit, Label and Safari Correction

**Status:** Repository candidate; live gate closed

**Date:** 2026-08-31

## 1. Accepted observations

A physical Safari/Mac check found delayed first-click behavior in the upper
selection area, uneven control sizing, sparse line continuity, an unrelated
external point inside one OwnTracks view and overlapping timestamp labels.
The screenshot and read-only aggregate diagnosis remain private evidence; this
document contains no source identity, coordinate or movement history.

## 2. Root causes and bounded corrections

- The map root disables native touch behavior for OpenLayers. Selection and
  navigation panels now restore `touch-action: manipulation`, text selection
  and an explicit stacking context while the viewport alone owns map gestures.
- The controls use stable grid columns and compact typography instead of
  wrapping flex items with content-dependent widths.
- The external projection was rendered unconditionally and expanded fit-all.
  Normal OwnTracks results no longer carry or render it. A case-study-local
  generic source option now reads the configured current position only when
  explicitly selected and supplies its variable update time as the point time.
- Timestamp labels shared the marker layer and were only index-sampled. Markers
  and labels now use separate vector layers; the bounded label layer uses
  OpenLayers decluttering so hiding a collision never removes the point or its
  hover/tap tooltip.
- A privacy-safe read-only day aggregate showed that the 15-minute segment
  threshold caused several avoidable breaks. The candidate raises only that
  threshold to 60 minutes. Accuracy, line eligibility and maximum-step checks
  remain unchanged, so long gaps and implausible jumps still break the line.

## 3. Source ordering

The runtime intentionally preserves configured OwnTracks source order, appends
the generic external option and selects the first OwnTracks entry by default.
The requested private order is therefore a later configuration-only live
change; public code contains no tracker labels.

## 4. Gate boundary

This correction changes repository artifacts and synthetic tests only. It does
not change the live module package, source configuration, external object,
archive, logging, provider, hook or visualization. Activation requires a
separate package/fileset gate plus a configuration gate for source order and
the 60-minute threshold, followed by physical Safari acceptance.
