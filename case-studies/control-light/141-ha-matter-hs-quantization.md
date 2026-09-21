# Explicit HA Matter HS Feedback Quantization

**Date:** 2026-09-21
**Status:** Single-pilot RGB color acceptance completed

## Finding

A hue request of 120 degrees can correctly return 119.055 degrees through the
HA Matter HS path. HA scales hue to 0..254, truncates before the Matter command,
then reverses the scale for feedback. The same path scales and truncates
saturation. Comparing that report with the ideal hue using an arbitrary broad
tolerance either rejects a valid command or hides another transport step.

Sources inspected on the report date (upstream, not a pinned installation):

- [HA Matter conversion](https://github.com/home-assistant/core/blob/dev/homeassistant/components/matter/util.py)
- [HA Matter command and readback](https://github.com/home-assistant/core/blob/dev/homeassistant/components/matter/light.py)

## Contract

`colorFeedbackQuantization` defaults to `none`, including the MATTER preset.
The opt-in value `ha-matter-hs-254-truncate` requires an enabled HS_ARRAY_STRING
color capability. Select it only after verifying that endpoint's transport;
neither the generic preset nor a writable color variable proves suitability.

For this profile, Core projects expected HS through HA's integer transport
calculation, then reuses validated circular hue comparison. Only three-decimal
serialization error (0.0005 plus floating epsilon) is admitted. Actual feedback
must not be truncated again. Hue is irrelevant for quantized zero saturation.
Malformed feedback remains invalid, and an ideal optimistic echo that is not
the predicted transport point is insufficient.

The rule applies to idempotency and confirmation, from both on and off. It
supersedes broader off-state hue/saturation tolerances for this explicit profile.
The default comparison, other color representations, brightness, alarm policy,
lock ownership and total confirmation budget remain unchanged. No additional
device command, retry, polling interval or public helper is introduced.

## Dependency and Verification

The deployment candidate explicitly includes the independently reviewed missing
structured-color-feedback repair from the preceding workstream. Its source and
test changes are preserved in the original workstream. Both corrections are
published together after the user's separate publication approval.

Core tests cover all 64770 reachable HS pairs, correct and adjacent wrong bins,
strict validation, achromatic hue, wraparound and unchanged default behavior.
Runtime regressions cover RGB from on/off, repeated identical commands, preserved
brightness, wrong-bin timeout with semaphore release and delayed missing-color
recovery. Facade color remains derived from reported HS, not the requested RGB.

Technical group feedback is not visual acceptance or proof of every member.
Live acceptance must inspect all members, test distinct colors and repetitions,
obtain user sight confirmation, and restore the initial power/brightness/color.
These conditions were met for the single authorized RGB pilot below; no broader
installation-wide color acceptance is implied.

The immutable candidate was staged, preflighted and independently hash-verified,
then selected for one authorized pilot. Two command-free reconciliations retained
object structure, actions, events, links, archive settings and initial device
values. Other callers and the global bootstrap remained unchanged.

## Completed Pilot Color Acceptance

The user independently confirmed red, green and blue visually. Every RGB primary
was also technically confirmed from both ON and OFF across the tested full and
reduced brightness settings. All three native member states, HS colors and
brightness values were checked after settling, not only the aggregate endpoint.
Green reported 119.055 degrees and blue 239.528 degrees without false timeouts.
Explicit color from OFF powered on while preserving retained brightness.

Repeating each requested RGB sent no additional device command. Red, green and
blue did not change independent brightness; the reduced-level test retained 40
percent. No error or timeout counter increased. Twelve real device commands
covered the color sequence, dimming, off-state checks and restoration.

The original reported color, full brightness and OFF state were restored on all
members and the facade. Final object/action/event/link/archive checks passed;
other callers, the global bootstrap and shared mirror were unchanged. No restart
or global rollout occurred. Missing-feedback and negative quantization cases
remain deterministic offline tests, not artificially induced live failures.
Publication and mixed-version managed-mirror reconciliation received separate
explicit approval after acceptance.

## Mixed-Version Mirror Reconciliation

The existing inert mirror keeps the previous runtime payload and the reference
index for its remaining callers. A separately owned mirror contains the new
pilot runtime and its own reference index. Both payloads match the authoritative
files byte-for-byte. The pilot's exclusive references were removed from the old
index; shared dependencies remain there.

The existing ControlLight-local generator and EnsureScript helper were reused.
Inspection identified an ownership-check defect: Symcon supplies ParentID,
not ObjectParentID. The generator and its regression fixture now use the actual
metadata field. User presentation is preserved, and a repeated reconciliation
performs no content write. A separate inert receipt records completion.

Independent source readback confirms both payload and reference-index hashes.
All inventoried caller sources, immutable runtime files and bootstrap remained
unchanged. No action binding, device command or restart was introduced. Console
UI search behavior was not separately re-tested in this gate.
