# Gate 90-N — synthetic live browser postflight

**Status:** Functional map baseline passed, but viewport tile completeness failed;
no live change was made, 2026-09-06.

## Scope

The separately authorized postflight opened the active OwnTracks map once in
the internal browser. The test was allowed to disclose the position-derived XYZ
indices needed for the visible viewport to the configured OSM Standard tile
provider. A first Connect-shell attempt did not expose the map. The actual map
test therefore used the existing password-free private-LAN visualization and
its same-origin gateway.

The browser navigated only through the visualization hierarchy, maximized the
OwnTracks tile and invoked `Fit all` once. It did not select a position, request
an ETA, invoke routing, change a source or day, operate a device, mutate Symcon
configuration or call a deployment operation.

No private origin, host, token, ObjectID, coordinate, tracker identifier, tile
index or movement history is recorded here. The visual evidence remained
transient because a screenshot would contain private position data.

## Result

The active HTML SDK map loaded successfully in current-overview mode. The
aggregate browser diagnostics reported:

- renderer ready and tile authorization ready;
- one same-origin XYZ layer;
- three rendered position points;
- viewport zoom 18;
- 72 protected tile requests started;
- 70 tile requests succeeded;
- two tile requests failed and remained marked missing; and
- one bounded viewport recovery with state `viewport-refreshed`.

The visible map was largely complete, attribution was present, the controls
were usable and buildings and local roads were readable. Two rectangular
basemap gaps nevertheless remained visible after the bounded recovery.

Invoking `Fit all` once did not cause a second request storm and did not alter
the aggregate counts: the same two tiles remained missing. This is a useful
fail-bounded result, but it does not satisfy the acceptance criterion that the
visible fitted viewport be complete.

## Decision boundary

Gate 90-N therefore does not close browser acceptance. It proves that the v8
deployment channel, active module, HTML SDK renderer, capability issuance,
same-origin gateway and most provider-backed tiles work together after
activation. It also provides a narrow reproducible residual failure at the
highest configured overview zoom.

No retry-policy, provider, budget, cache, map, module, visualization or channel
configuration was changed. The activated package and retained rollback package
remain untouched.

A correction requires a new gate. It should first distinguish a durable
negative-cache/provider result from a client recovery-state issue using only
bounded aggregate evidence. Any repository change, Windows qualification,
stage, preflight, activation and follow-up provider contact remain separate
gates. Physical Safari/iPad acceptance and publication also remain pending.
