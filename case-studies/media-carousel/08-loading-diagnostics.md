# Bounded view-local loading diagnostics

Version 0.2.5 adds measurement, not a loading algorithm change. It preserves
instance properties, layout, media order, two pending requests and existing
timeout/retry behavior. No new Symcon objects, persisted counters or requests
are created. Registry, Statistics and ErrorRingBuffer were considered: these
helpers own server variables and cannot measure the browser-side wait. Creating
server storage would add unrelated state to this diagnostic deployment. Instead,
the existing per-view client state carries a fixed, bounded aggregate snapshot.

Read `#carousel`'s `data-load-diagnostics` DOM attribute in an authorized browser
session. It contains only counts, elapsed milliseconds, cache/pending sizes and
the current sequence index. It contains no object IDs, titles, image data,
request IDs, source revisions or event history. It disappears with the view.
Counter values saturate; samples cap at one hour. There is no visible badge,
new setting, public module method or extra network telemetry.

The server includes `preparationMilliseconds` in successful media responses.
This measures the existing request handler through image preparation; it
excludes host queueing, final JSON serialization and transport. Client round-trip
time starts before the existing request dispatch and ends at its matching
response. Image-ready time measures a browser image load probe, not a precise
GPU or standalone decode benchmark. Maxima are independent aggregates, not
necessarily samples from the same request.

Compare five ordinary tiles with the same individual maximized tile over a
bounded observation period. Distinguish server preparation, delivery/queueing,
timeouts, rejected late/foreign responses, revision resets and superseded
images. Large round-trip time with short preparation suggests delay outside
preparation; it does not by itself identify the network or Symcon queue as the
cause. Rejection counters may include another legitimate view of the same
instance. Do not infer overload merely from the number of tiles.

Node tests exercise the production client closure, timing categories, privacy
and unchanged request counts. PHP tests check bounded response metadata.
Physical app performance remains an independent acceptance gate.
