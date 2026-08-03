# CL-020 HS-native Functional Test and Rollback

**Date:** 2026-07-27
**Live scope:** One explicitly approved CL-020 device/consumer test followed by
fail-closed rollback
**Current result:** CL-020 is again on its exact legacy wrapper

## Result

The HS-native candidate passed its direct on-state capability matrix:

- STATE feedback was authoritative;
- facade brightness 70 mapped to target brightness 179;
- requested color temperature 3500 settled at the accepted normalized value
  3508;
- an RGB color request settled within 0.315 degrees hue and 0.197 percentage
  points saturation of its native-HS expectation; and
- the color request left facade and target brightness unchanged.

The installed Echo Remote text-command path also passed STATE, brightness and
blue color commands while the lamp was on. The color command again preserved
brightness. The scene configuration still references the CL-020 facade
brightness and color variables. It was inspected but not executed because the
same scene contains additional device targets outside this test's authorization.

## Blocking Finding

Restoring color while the lamp was off exposed a different target contract. The
color action implicitly powered the Matter/Home Assistant light on. Its later
native-HS feedback differed from the facade projection by approximately 1.376
degrees hue, outside the configured 0.5-degree tolerance. The runtime therefore
raised the intended authoritative confirmation timeout.

This is not evidence for widening the global matcher tolerance. The equivalent
on-state request was already accepted with substantially lower error. The
remaining problem is specifically an off-state color transition and its
asynchronous target normalization.

## Compensation and Rollback

A direct native-HS compensation restored the retained color to within 0.026
degrees hue and 0.301 percentage points saturation of the refreshed initial
target value. STATE, brightness and color temperature were restored exactly,
and the lamp was left off. Color is therefore semantically restored within the
declared native tolerance, but not byte-exact after target normalization.

The wrapper source was then restored byte-exactly to its prepared legacy hash.
Only the ten diagnostics owned by the temporary v2 activation were removed.
The four pre-existing feedback events remain active under the wrapper with
explicit event-action bindings. Independent readback found 21 v2 wrappers,
eight legacy wrappers and no unknown source.

Alexa, scene and target configuration hashes remained unchanged. No device
action was issued during the source rollback.

## Decision

CL-020 remains legacy. A later candidate must define and test an explicit
off-state color contract, including whether color preparation may power the
target, how stabilization is observed, and whether confirmation is one- or
two-phase. That design must preserve the already proven brightness independence
and must not weaken confirmation tolerances for unrelated on-state commands.
