# Gate 90-O — Safari picker and intermittent tile diagnosis

**Status:** Physical Safari baseline accepted and both residuals classified;
read-only diagnosis complete, no correction or live change made, 2026-09-06.

## Scope

The physical Safari check covered the previously problematic OwnTracks dates
and paths. Their map, fit and path presentation are now accepted. Two narrower
residuals remain:

- a native selection picker, especially the date picker, can stay presented
  after the selected value has already been committed; and
- an otherwise complete map can occasionally retain an isolated missing tile.

The follow-up inspected the active source and bounded aggregate runtime state.
It did not change the renderer, module, map configuration, deployment channel,
provider policy, cache, budget or live state. It issued no provider request and
retained no private origin, ObjectID, coordinate, tracker identifier, tile index
or movement history.

## Safari picker result

The controls are native `select` and date-input elements. Their `change`
handlers submit the selection but do not release focus after a committed
choice. This is distinct from the earlier host-overlay hit-target problem: the
selection itself succeeds, while the browser-native picker remains presented.

[WebKit has documented an iPad case](https://bugs.webkit.org/show_bug.cgi?id=235911)
in which a select element is not blurred when its dropdown is dismissed. A
programmatic `blur()` is a known workaround, but applying it indiscriminately
can interfere with keyboard and assistive-technology workflows. The narrow
correction candidate is therefore a deferred, picker-scoped focus release after
an actual committed change. It must preserve desktop keyboard navigation and
must not reintroduce user-agent-specific layout logic.

## Intermittent tile result

The client already permits two bounded recovery generations: one after three
seconds and one after sixty seconds. A changed pan or zoom establishes a new
viewport fingerprint and rearms recovery. `Fit all` on an already identical
fitted viewport is deduplicated, however, so it neither starts a new tile
viewport nor rearms failed tiles. This explains why moving or zooming may fill
an isolated gap while an unchanged `Fit all` does not.

The server also remembers a provider miss for sixty seconds. That mechanism is
not supported as the cause of the current observation: the read-only live
snapshot contained 81 recorded upstream requests and 81 successes, with no
negative-cache hits, spatial rejections, budget rejections, active negative
entries, active reservations or current provider-window pressure. The module
was healthy. The snapshot was not taken during the physical request, so it
cannot classify the exact failed response retrospectively; it does rule out a
currently persistent provider, authorization or budget failure.

The remaining evidence is consistent with a transient browser-to-Symcon or
same-origin response failure under the reported slow connection, or another
failure before a durable provider-miss result is recorded. The current browser
diagnostics combine these cases into a single failed/missing count and cannot
distinguish them without revealing more data.

## Correction boundary

A repository-only correction gate should remain fail-bounded and add:

1. a deferred, accessibility-conscious focus release after a committed native
   picker change;
2. an explicit user-gesture rearm for still-missing tiles when `Fit all` keeps
   the same viewport, with a cooldown and without increasing provider or
   selection budgets; and
3. aggregate response-class diagnostics for network errors and same-origin
   HTTP classes, without coordinates, URLs or XYZ indices.

Synthetic Safari-compatible control tests and slow-response, isolated-failure
tile tests should pass before any Windows qualification, stage, live preflight
or activation. Those later gates, a provider-reaching browser retest and final
physical Safari acceptance remain separately authorized.
