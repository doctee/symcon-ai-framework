# Mobile Presentation Correction

**Status:** Controlled live correction complete; private visual acceptance
pending

**Date:** 2026-08-31

## 1. Accepted Functional Baseline

The private iPhone check confirmed that the selected OwnTracks day loads and
renders. The remaining issue was presentation-only: the three selection fields
and the right map-navigation group consumed too much of the narrow tile, while
the native Symcon maximize control had previously been hidden to avoid an
overlap.

No archive, source, path, tooltip, ETA or provider defect was involved.

## 2. Repository Correction

At viewports up to 560 CSS pixels the candidate now:

- reserves the upper-right host corner for the native Symcon control;
- reduces selection labels to 9 px and field text to 12 px;
- reduces selection-field height to 32 px;
- reduces map-navigation buttons to 36 px; and
- places the right navigation below the reserved host corner.

At wider viewports only the right navigation moves below the native maximize
control. The selection fields retain their established desktop/tablet size.
The map surface still fills the complete visualization content box.

The internal browser verified the reserved corner and compact dimensions at
390 x 844, then verified at 1024 x 768 that the navigation begins below the
host-control boundary. The full OwnTracks and deterministic-fileset test target
passes.

## 3. Controlled Live Correction

The exact active 21-file candidate package is:

```text
4c5a3358923ad46194f180d128291cbf87a41bb035c220b609ec00a60e73f32f
```

The immediately preceding package remains byte-exact as the presentation
rollback:

```text
243cbbe08a49e227c968ff6066ef7794782bb5e01adb543dcd2ef60194997f17
```

The package was staged, hash-verified, atomically exchanged and reloaded once.
Postflight proved the active and rollback inventories, active instance status,
unchanged candidate configuration, delivered responsive CSS, no candidate
WebHook and provider/routing mode `none`.

The two pilot links retain hidden titles. Their native maximize controls were
restored with `IPS_SetHiddenMaximize(..., false)` after positive link and target
validation. Existing OwnTracks map links were not presentation targets.

## 4. Rollback

Presentation rollback is bounded to:

1. set maximize hidden on the two exact pilot links; and
2. atomically restore the retained preceding package followed by one pilot
   library reload.

No source instance, archive, logging configuration, external path anchor,
existing map, provider or WebHook belongs to this rollback.
