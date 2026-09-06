# SharedLocation ETA Live Activation

**Status:** Controlled private activation complete; private visual ETA
acceptance pending

**Date:** 2026-08-31

## Outcome

The separately owned OwnTracks position-map candidate now reuses exactly two
existing `SharedLocation` instances as private ETA target references. A
bounded current-day request completed against one selected OwnTracks source.
The provider-free map, the three source instances, their archives, logging
contracts, existing links and WebHooks remained unchanged.

No ObjectID, location key, coordinate, tracker identifier or movement record
is recorded in this document.

## Controlled Activation

The activated package contains 22 exact files and has identity:

```text
12a47174f29c6115335be2a642d06e5fcdf25e18bd5dc3456b91f77436f8d2c5
```

The immediately preceding 21-file package remains byte-exact as the rollback
unit:

```text
acb949ce5841a96924b7b250586ae602fd727da231de2fc7c52310da02fffd15
```

The previous candidate configuration was also retained byte-exactly in a
private configuration backup. The activation used one targeted Module Control
reload and no kernel restart.

The candidate configuration removed the obsolete static target property and
added only the private references to the two existing locations. Both location
descriptors were revalidated immediately before activation for module type,
active status, bounded response size, stable key, WGS84 range and IANA time
zone. The location instances themselves were not changed.

## Rollback Exercise

The first activation attempt reached an active candidate and valid target
configuration, but an over-specific postflight expected a presentation title
inside the HTML tile that the established tile does not emit. The transaction
therefore retained the new package separately and automatically restored both
the preceding package and its exact configuration.

A read-only inspection proved that the intended invariant is the established
HTML-SDK and OpenLayers boundary, not that absent title literal. The retained
new package was staged again without modification. The second activation used
the corrected invariant and completed successfully. This exercised the exact
rollback path before final activation without changing any source or archive.

## Postflight

The final postflight proved:

- the active 22-file inventory and retained 21-file rollback inventory;
- active candidate status and exactly two registered location references;
- unchanged configurations for all three OwnTracks sources;
- unchanged candidate links and complete WebHook state;
- unchanged Archive Control logging status for position, accuracy and motion
  activity variables of all three sources;
- a valid HTML-SDK/OpenLayers visualization read-back;
- basemap mode `none` and routing mode `none`; and
- one bounded current-day selected-source request without returning private
  movement data through the probe.

The request may resolve no ETA when activity, freshness, direction or closing-
speed evidence is insufficient. That is the designed truthful outcome, not a
fallback to a guessed destination.

## Remaining Acceptance

A private iPhone or iPad check should now confirm that a current moving track
shows `Diagnostic ETA` only when one of the two locations is selected by the
motion-aware evidence. No further live mutation is required for that check.

Commit, publication, provider activation, routing activation and changes to
the existing OwnTracks map remain outside this gate.
