# Gate 90-P — Safari picker and tile rearm correction

**Status:** Repository correction and provider-free synthetic acceptance
complete; Windows, stage and live gates remain closed, 2026-09-06.

## Scope

This gate implements the two bounded corrections classified in
[Gate 90-O](104-safari-picker-and-intermittent-tile-diagnosis.md):

- dismiss a still-active native picker after a committed selection on a
  coarse-pointer browser; and
- let an explicit `Fit all` recover an otherwise unchanged viewport that has
  a recorded current-generation tile failure.

It also separates aggregate tile failures into network, HTTP class,
content-type, payload and image-decode counters. The diagnostics contain no
URL, coordinate, XYZ index, tracker identifier or response body.

No Symcon installation, configured provider, live map, cache, budget,
allowlist, deployment channel or runtime state was contacted or changed.

## Picker correction

Source, day and view controls now share a committed-change handler. It submits
the existing selection exactly once and schedules focus evaluation for the
next animation frame. The control is blurred only while it is still active and
either exposes the standards-based `:open` picker state or runs with a
coarse-pointer, no-hover primary input.

This keeps the existing touch layout and selection contract unchanged. A
fine-pointer desktop control is not blurred merely because its value changed,
preserving keyboard focus. Physical Safari remains the authoritative test for
the browser-native picker presentation.

## Tile correction

The renderer now counts failures for the currently accepted viewport
generation separately from the existing cumulative diagnostics. An explicit
`Fit all` may request a fresh authorization for the same viewport only when:

1. the viewport is ready;
2. that generation recorded at least one tile failure;
3. the generation has not already received a manual rearm; and
4. the three-second manual cooldown has elapsed.

The rearm preserves the already consumed automatic-retry count and does not
change provider concurrency, selection budgets, minute budgets, capability
lifetimes, spatial authorization or cache policy. A new accepted viewport
starts with zero current-generation failures. Repeating `Fit all` after a
successful recovery is therefore a no-op at the tile boundary.

## Verification

The OpenLayers bundle was rebuilt and checked with an existing lock-identical
dependency tree. The deterministic module fileset still contains 37 files.
The complete OwnTracks suite, focused PHP style checks, JavaScript syntax,
bundle reproducibility and repository whitespace checks passed.

A provider-free loopback browser fixture rejected exactly one tile in the
first 20-tile viewport. Before the automatic three-second retry, the explicit
`Fit all` produced exactly one manual rearm. The final aggregate state was:

- 40 requests started across two viewport generations;
- 39 successes and one classified HTTP-client failure;
- zero failures in the accepted replacement viewport;
- one manual rearm and zero consumed automatic retries; and
- no additional viewport request after a second `Fit all`.

The fixture used generated synthetic tiles only. Its temporary files and
browser tabs were removed after the test.

## Remaining gates

Windows qualification, deterministic inactive packaging, channel stage,
target-bound adapter preflight, activation, independent live health,
provider-reaching browser verification and physical Safari acceptance each
require their own gate. The deployed module and retained rollback package are
unchanged by Gate 90-P.
