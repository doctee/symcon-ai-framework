# iOS HTML-SDK Action Bridge Correction

**Status:** Both controlled live corrections and private iPhone acceptance
complete

**Date:** 2026-08-30

## 1. Symptom and Cause

The first private iPhone acceptance showed the initialized no-tile map and
source controls, but the status remained at `Loading synthetic day…`. This
proves that bootstrap and OpenLayers initialization completed while the first
HTML-SDK action did not cross into the module.

The renderer incorrectly required the injected `requestAction` function to be
an own property of `window`. The established MediaCarousel HTML-SDK boundary
uses the injected global binding directly. An iOS/WKWebView environment may
provide that binding without exposing it as `window.requestAction`.

## 2. Repository Correction

The OpenLayers adapter now:

- checks and calls the global `requestAction` binding directly;
- replaces the synthetic fixture wording with `Loading day…`;
- reports a missing or failed action bridge instead of waiting indefinitely;
- applies one 20-second client timeout with no automatic retry;
- handles module configuration and selected-day errors explicitly; and
- clears the timeout only for the current accepted generation.

No provider, tile, WebHook, routing, archive-write or logging behavior was
added.

## 3. Verification

The browser fixture deliberately declares `requestAction` as a global lexical
binding rather than a `window` property. Static regression checks reject a
future return to `window.requestAction`.

In the internal browser at an iPhone-sized viewport, the corrected fixture:

- completed the initial selected-day request;
- rendered the bounded 360-observation fixture as 48 points;
- completed a subsequent source-selection request;
- updated the ETA state for the new source; and
- retained the provider-free five-layer OpenLayers presentation.

The complete OwnTracks test target and deterministic fileset test pass. The
corrected generated package identity is:

```text
fa6bceade0e94e050904bec58cbc8157ef6426a26dfec55de5f6ef484fd52543
```

## 4. Controlled Live Correction

After separate authorization, the active package was replaced atomically with
the corrected package identity. The previous active directory was renamed,
not copied or deleted, and remains as the byte-exact 21-file rollback fileset:

```text
active:   fa6bceade0e94e050904bec58cbc8157ef6426a26dfec55de5f6ef484fd52543
rollback: e2a75773190967dbf663969489449a597743a024a69888c110f43ef366201f25
```

Exactly one reload targeted only the newly owned OwnTracks Position Map
library. The postflight proved:

- the candidate instance remained active with unchanged configuration;
- the installation and link counts remained unchanged;
- both existing and both pilot link structures remained unchanged;
- all three OwnTracks sources, the existing map and hook, the external anchor
  and the pilot configuration retained their pre-reload hashes;
- no candidate WebHook registration appeared;
- provider and routing modes remained `none`; and
- the live HTML-tile read-back contains the direct global action bridge, the
  20-second error state and no `window.requestAction` or synthetic loading
  label.

All MCP calls were accepted only with empty transport and execution errors and
without truncation. An internal-browser attempt reached only the general
Symcon Connect loading splash, not the visualization, and therefore is not
counted as a live UI acceptance. Final confirmation remains a private iPhone
check by the user.

## 5. Symcon Callback Correction

The subsequent private iPhone check reached the action bridge but ended in the
bounded `Selected day timed out` state. Live HTML read-back then exposed a
second integration defect: Symcon's HTML-SDK wrapper forwards visualization
updates only to the conventional `window.handleMessage` callback, while the
candidate had registered only its case-study-specific callback name.

The repository candidate now aliases the case-study handler to the canonical
Symcon callback. Both synthetic fixtures deliver results through
`window.handleMessage`, so they can no longer bypass this boundary. The
iPhone-sized browser test completed the initial request and a subsequent
source change through that callback. All OwnTracks and deterministic fileset
tests pass.

The resulting repository package identity is:

```text
243cbbe08a49e227c968ff6066ef7794782bb5e01adb543dcd2ef60194997f17
```

After separate authorization, the callback correction was activated with one
reload of only the pilot library. The live state is now:

```text
active:          243cbbe08a49e227c968ff6066ef7794782bb5e01adb543dcd2ef60194997f17
bridge rollback: fa6bceade0e94e050904bec58cbc8157ef6426a26dfec55de5f6ef484fd52543
initial rollback:e2a75773190967dbf663969489449a597743a024a69888c110f43ef366201f25
```

All three directories contain their exact 21-file inventories. Postflight
confirmed the active candidate status, the canonical callback alias in the
live HTML tile, unchanged configuration hashes, unchanged structures for both
existing and both pilot links, unchanged instance/link counts, no candidate
WebHook and provider/routing modes still set to `none`. Final acceptance is a
fresh private iPhone open and requires no further mutation.

On 2026-08-31 the user confirmed that the selected-day result renders on the
private iPhone. This closes the action and callback acceptance boundary. The
subsequent presentation-only sizing and maximize correction is recorded in
`15-mobile-presentation-correction.md`.
