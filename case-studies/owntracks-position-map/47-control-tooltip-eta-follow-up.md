# Control, Tooltip And ETA Follow-up

**Status:** Repository implementation, responsive internal-browser acceptance
and exact-package live activation complete; physical acceptance pending

**Date:** 2026-09-01

## Physical Findings

The active package showed three bounded issues:

1. Safari allowed the native date field border to extend beyond the selection
   panel;
2. the `View` label sat too close to the panel's top and left edge; and
3. selecting an overview point immediately rebuilt and fitted the map after the
   ETA response, which removed the just-pinned tooltip. A rejected ETA exposed
   only the generic `ETA unavailable` text.

## Read-only ETA Diagnosis

A bounded Symcon MCP probe inspected only anonymized freshness and radius
outcomes. It emitted no ObjectIDs, labels, coordinates or movement samples. All
three current positions were inside the strict 100-kilometre boundary, but each
was older than one hour. The existing fail-closed ETA contract accepts a current
position for at most 15 minutes, so `position-stale` was the correct rejection.

The 15-minute freshness and strict under-100-kilometre limits remain unchanged.

## Repository Correction

The selection grid now uses zero-minimum bounded columns whose complete width,
gaps, padding and border fit inside the panel. Native controls also receive
explicit logical minimum and maximum widths. The panel is slightly narrower,
and label text receives a small top and left inset without increasing field
font size.

An ETA-only overview response still rebuilds current features but no longer
performs an unnecessary fit. The selected source is resolved to its replacement
feature and the same tooltip is pinned again for the established four-second
touch interval. Normal initial loads, mode changes and explicit `Fit all`
continue to fit all observations.

Unavailable ETA entries now map bounded internal reasons to short user-facing
details. In particular, `position-stale` and `current-position-stale` render as
`position too old`. The raw internal reason and private target identity remain
outside the browser presentation.

## Verification

The complete OwnTracks suite, performance checks, OpenLayers bundle check,
module-fileset check, PHPCS, PHPStan and diff check pass. Internal-browser checks
at 1024 x 768 and 390 x 844 confirmed:

- zero field overhang and zero horizontal document overflow;
- visible top and left label insets;
- a pinned tooltip immediately after the ETA response and one second later;
- source-labelled available ETA; and
- source-labelled stale rejection as
  `ETA unavailable · position too old`.

The resulting exact 29-file package identity is
`29c274c8433826848bb1c20f91fffb4e1b4852262b83e3c1bfb292c7d1aeb697`.

No live object or package was changed by this repository step.

## Remaining Gates

The exact package is active with the independently verified rollback boundary
recorded in `48-control-tooltip-eta-live-activation.md`. Physical acceptance,
commit, publication, provider or tile-set changes and retained-artifact cleanup
remain closed.
