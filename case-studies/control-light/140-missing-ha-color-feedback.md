# Missing HA Color Feedback

**Date:** 2026-09-21
**Scope:** Single authorized pilot activated; color acceptance remains partial

## Finding

An HA light endpoint can report an empty color string while off and update
STATE before publishing its color attributes after power-on. ControlLight
confirmed the requested STATE but then failed in the unrelated color
synchronization with an invalid-JSON exception. A color request could fail even
before dispatch when comparing its missing baseline.

## Contract

The runtime treats only null, empty/whitespace strings and the JSON null token
as unavailable feedback for structured color formats. It preserves the last
known facade color without claiming that it is fresh. Integer black remains a
valid color; malformed nonempty values still reach strict core validation.

Missing color does not invalidate an independently confirmed state or brightness
command. A passive missing color event returns feedback_unavailable, not
feedback_synchronized. Later valid feedback updates the facade without any
device command.

An explicit color request is never confirmed by absence or by the retained
facade value. It dispatches normally and reuses the existing bounded wait for
valid target feedback. Permanent absence produces the existing classified
timeout, with no retry or extra timeout budget. Alarm checks, lock ownership,
off-state transition policy and color tolerances are unchanged.

This is an internal runtime correction, not a new public helper or converter
API. Normal synchronization reads the target once. Independent state actions
do not wait for optional color attributes.

## Verification and Rollout Boundary

The runtime suite has 49 passing cases, including 16 added cases for missing
markers, state/dim/off independence, passive recovery, delayed color feedback,
bounded permanent absence, malformed input, integer black, structured color
formats, idempotency and semaphore release.

All focused ControlLight test entrypoints and the full `make check` gate pass.
The generated fileset is rebuilt and validated against its source manifest.

The dedicated immutable fileset must be separately staged and activated for
the selected pilot caller only, retaining its exact previous source and
fileset. The existing shared deployment must not be edited in place. No global
bootstrap replacement or service restart is needed for that selection.

Uniform power-on semantics for all genuinely color-capable lights remain a
separate approved implementation scope, not part of this repair. A selectable
XY control alone is not proof of physical RGB capability.

## Narrow Pilot Result

The immutable candidate was selected for one caller only after stage, preflight
and independent file-hash verification. Two command-free reconciliations kept
topology unchanged. State on/off, positive dim from off, repeated dim idempotency,
blue from off and repeated blue idempotency passed with all three physical-group
member variables checked, not aggregate state alone. Initial device values were
restored. This is technical feedback evidence, not a user visual acceptance.

A subsequent green request reported hue 119.055 degrees on every member versus
the requested 120 degrees. The unchanged 0.5-degree on-state matcher correctly
classified this as a color feedback timeout. No JSON exception occurred. Further
color tests stopped; red remains untested. The Core source is byte-identical to
the predecessor, and no tolerance was widened. A quantization-aware comparison
requires separate analysis and acceptance before claiming full color closure.

The missing-feedback branch is covered deterministically offline; live feedback
was not artificially erased to exercise it. Shared mirror projection, publication
and broader uniform-color rollout are separate follow-ups.


## Subsequent closure

The partial result above is historical. Report 141 records the separately
approved HA Matter quantization correction and complete single-pilot RGB
acceptance. Both changes are included in the same publication; broad rollout
remains a distinct gate.
