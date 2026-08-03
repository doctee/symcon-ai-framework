# CL-021 Functional Test and Color Finding

**Gate:** Complete enabled-capability device test
**Result:** PARTIAL — INITIAL STATE RESTORED, COLOR CONTRACT OPEN
**Date:** 2026-07-24
**Live impact:** Seven intended device actions including compensation

## Preflight

The fresh read-only preflight verified the activated wrapper and immutable
runtime identities, equal local and target values, available target feedback,
inactive alarm state, zero historical command failures and unchanged
multi-controller consumers.

The initial contract was STATE false with retained brightness, color
temperature and color values. The planned sequence contained four forward
capability checks followed by exact restoration.

## Confirmed Capabilities

Three capability paths passed through the local ControlLight facade:

1. STATE turned on and was confirmed authoritatively;
2. requested brightness 40 was normalized by the device to 39 and confirmed
   within the existing bounded brightness tolerance; and
3. requested color temperature 3000 K was normalized to 3003 K and confirmed
   within the existing temperature tolerance.

Each phase added exactly one ControlLight command. STATE remained on during the
brightness and temperature checks. The temperature transition also updated the
device's reported color, demonstrating coupling between both color modes.

## Color Finding

The requested RGB test color was dispatched once. The target then reported a
different color together with changed brightness and color temperature. Because
the current `INT_HEX` confirmation contract requires exact color equality, the
three-second authoritative confirmation timed out.

ControlLight correctly:

- recorded one `feedback_timeout` for the color capability;
- avoided optimistic retention of the requested local color;
- synchronized the local facade to the authoritative target feedback; and
- emitted no duplicate command.

The successful `RequestAction()` transport return therefore indicated accepted
action dispatch, not successful authoritative color confirmation. The runtime
diagnostics remain the source of truth for the command outcome.

This is a real contract finding, not an availability failure: the device
remained available and continued to provide current feedback.

## Compensation

The forward sequence stopped immediately after the color finding. A direct
color-restoration attempt was deliberately skipped because it would reuse the
unresolved conversion path.

Instead, three bounded facade commands restored the baseline:

1. restoring the original color temperature also restored the exact original
   reported color;
2. an adjacent brightness request restored the exact retained brightness after
   the device's known one-point normalization; and
3. STATE was restored to false.

The independent postflight confirmed exact initial values locally and at the
target for all four roles. No further compensation was required.

## Final Regression

The final diagnostics contained seven commands, one error and one confirmation
timeout—the single preserved color finding. All other executions succeeded.
All four feedback events, presentation links and known consumers remained
unchanged, and all 29 wrapper source identities matched the activated
nine-v2/20-legacy baseline.

## Gate Decision

CL-021 is fully activated. STATE, brightness and color temperature are
device-tested successfully. Color remains enabled but is not yet accepted as
device-tested.

Before any color fix or retest, a read-only analysis must determine whether the
target action uses a different color representation, applies a device-gamut
conversion, or requires a bounded perceptual/converted comparison. The finding
does not justify globally weakening exact RGB confirmation for other
ControlLight instances.
