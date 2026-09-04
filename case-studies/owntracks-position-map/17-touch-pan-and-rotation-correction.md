# Touch Pan and Rotation Correction

**Status:** Repository, internal-browser and controlled live gates complete;
private iPhone/iPad acceptance pending

**Date:** 2026-08-31

## 1. Symptom and Cause

The private iPhone check found that zoom and rotation worked, while one- or
two-finger pan usually scrolled the surrounding Symcon visualization instead
of moving the map.

The renderer root declared `touch-action: none`, but the subsequently loaded
OpenLayers stylesheet applied `touch-action: pan-x pan-y` to the actual
`.ol-viewport` interaction surface. The computed browser style confirmed that
this inner rule won. OpenLayers also enabled pinch rotation by default.

## 2. Smallest Correction

The case-study renderer now:

- applies `touch-action: none` directly to `.ot-map .ol-viewport` with greater
  specificity than the bundled OpenLayers rule;
- applies `overscroll-behavior: contain` to the map root and viewport;
- retains the native OpenLayers `DragPan` and `PinchZoom` interactions;
- disables `PinchRotate` and alternate drag rotation; and
- disables rotation at the View constraint as a second invariant.

No custom `touchstart` or `touchmove` handler was introduced. Reusing the
OpenLayers interaction stack avoids parallel gesture state and preserves its
pointer lifecycle.

## 3. Verification

The complete OwnTracks and deterministic-fileset tests pass. The generated
21-file candidate has the exact identity:

```text
acb949ce5841a96924b7b250586ae602fd727da231de2fc7c52310da02fffd15
```

In the internal browser at 390 x 844 and 1024 x 1366, both the root and actual
OpenLayers viewport reported:

```text
touch-action: none
overscroll-behavior: contain
rotation enabled: false
rotation: 0
```

The zoom control remained functional and rotation stayed zero afterwards.
Desktop browser evidence cannot fully substitute for a real iOS WKWebView
finger gesture, so final iPhone/iPad acceptance remains part of a separate
live gate.

## 4. Controlled Live Correction

After separate authorization, the exact 21-file candidate was staged,
hash-verified, atomically activated and reloaded once. The active identity is:

```text
acb949ce5841a96924b7b250586ae602fd727da231de2fc7c52310da02fffd15
```

The immediately preceding presentation package remains byte-exact as the
touch-correction rollback:

```text
4c5a3358923ad46194f180d128291cbf87a41bb035c220b609ec00a60e73f32f
```

Postflight proved the complete active and rollback inventories, active
candidate status, unchanged configuration, both intended presentation links,
delivered viewport touch rules, delivered rotation policy, no candidate
WebHook and provider/routing mode `none`.

Final evidence requires a fresh real iPhone and, if available, iPad gesture
check: one-finger pan must move the map, pinch must zoom without rotation and a
gesture beginning on the map must not scroll the surrounding visualization.

## 5. Closed Boundaries

This gate changed only the separately owned live candidate package and reloaded
that pilot library once. It changed no Symcon object, visualization flag,
archive, logging setting, WebHook, provider, routing authority, commit or
publication.
