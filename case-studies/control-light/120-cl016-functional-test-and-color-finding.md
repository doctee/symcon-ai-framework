# CL-016 Functional Test and Color Finding

**Date:** 2026-08-03
**Gate:** Explicitly approved direct facade capability test
**Result:** PARTIAL PASS — COLOR CONTRACT FAILED, INITIAL STATE RESTORED

## Scope and Fresh Baseline

Only the direct CL-016 facade was exercised. Alexa, SceneControl and the two
broad consumer automations remained untouched. The fresh authoritative
baseline was STATE=false, retained brightness 78, 2202 K, the reported warm
color and `COLOR_TEMP` mode. Target availability was true and the inverse alarm
interlock was inactive.

## Brightness Harness Correction

The first harness incorrectly required exact settled brightness. The target
normalizes requested 40 to reported 39 and requested 78 to reported 77. SAEF
already defines and tests a one-percentage-point tolerance for single-target
brightness, so this was a harness defect rather than a ControlLight failure.

A bounded inverse request of 79 settled at the original reported 78. No runtime
error or confirmation timeout accompanied this normalization.

## Corrected Direct Matrix

The corrected test used the productive SAEF matchers and passed:

- STATE on with authoritative feedback;
- brightness with the existing one-point tolerance;
- color temperature with the existing five-Kelvin tolerance; and
- the forward red color request with the productive color matcher.

Restoring the previous warm color then exposed a real target contract defect.
The Z2M color path coupled brightness into the color projection, failed to
reproduce the requested color feedback and generated a bounded color
`feedback_timeout`. The compensation path repeated the color request once and
recorded a second bounded color timeout. This is consistent with the already
known Z2M color-plus-brightness boundary, not with the brightness tolerance.

## Diagnostics and Restoration

Across the corrected matrix and compensation, diagnostics changed from
36/10/36/0/0 to 83/20/81/2/2 for
executions/commands/successes/errors/confirmation-timeouts. Both bounded error
history entries classify `color` and `feedback_timeout`.

The failed compensation left the lamp safely off with correct temperature,
color and color mode but retained brightness 75. One final bounded and already
proven 79-to-78 restoration returned the complete fresh domain baseline:

- STATE=false;
- reported brightness 78;
- 2202 K and 454 mired;
- original reported color and `COLOR_TEMP` mode;
- equal facade and target values; and
- availability true.

The final diagnostic totals are 93 executions, 23 commands, 91 successes, two
errors and two confirmation timeouts. The two color failures remain visible;
they were not cleared or hidden.

## Decision Gate

STATE, brightness and color temperature are directly proven. Color remains
live-configured but is not a reliable authoritative capability. The recommended
next gate mirrors the established CL-021 response:

1. prepare an exact wrapper candidate with `identColor` disabled;
2. remove the CL-016 color binding from its Alexa row while preserving power,
   brightness and color temperature;
3. reconcile twice without device commands, hiding and disarming only the
   script-owned color facade/event; and
4. retain the native target and diagnostic history for the later Zigbee2MQTT
   V6 review.

No further color, Alexa, scene or consumer action is justified before that
separate decision.
