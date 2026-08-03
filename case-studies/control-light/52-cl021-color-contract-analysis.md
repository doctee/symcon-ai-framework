# CL-021 Color Contract Analysis

**Gate:** Read-only target action and feedback analysis
**Result:** ROOT CAUSE IDENTIFIED — NO LIVE CHANGE
**Date:** 2026-07-24
**Live impact:** None

## Scope

This analysis followed the compensated CL-021 color test. It inspected only
the existing target instance metadata, exposed capabilities, installed module
source, current upstream source and restored runtime values.

No script was executed, no action was requested, no configuration was changed
and no diagnostic history was cleared. Exact installation metadata remains in
the private evidence.

## Target Contract

The target exposes both CIE-xy and hue/saturation color models through one
integer Symcon `color` variable with the `~HexColor` profile. There is no
separate actionable RGB, xy or hue/saturation variable available to
ControlLight.

The installed target module selects its color-send mode from reported
`color_mode`. A color-temperature state falls through to the CIE path.

For an integer color action, that path:

1. splits the integer into RGB channels;
2. converts RGB to CIE-xy;
3. derives a new brightness from calculated luminance;
4. sends both xy and brightness; and
5. later reconstructs the integer color from reported xy and brightness.

The feedback integer is consequently a projection of multiple authoritative
properties, not a stable write/read scalar.

## Reproduced Conversion

The exact installed conversion code was reproduced offline for the test color.
Even without a physical device, the module's own RGB-to-xy-to-RGB path does not
return the requested integer. Rounding and the different forward/reverse
matrices already make the conversion lossy.

The real target additionally returned a different projected color, brightness
and color temperature. Device gamut mapping or further Zigbee2MQTT
normalization therefore compounds the module-level loss.

This explains all observations from the functional test:

- the color command changed DIMMER;
- the target continued to provide current authoritative feedback;
- the local facade synchronized to that feedback;
- exact RGB confirmation timed out; and
- restoring color temperature also restored the previous projected color.

## Upstream Status

The installation uses the development line of the Symcon Zigbee2MQTT module.
Its installed version and the current development source retain the same
conversion path. The module contains no regression test that establishes
request-to-feedback RGB identity.

Zigbee2MQTT itself supports native xy, hue/saturation and RGB/hex color command
representations. The Symcon module currently chooses a local RGB-to-xy
conversion and adds derived brightness before publishing the action.

## Rejected SAEF Workarounds

The finding does not justify:

- globally widening exact RGB equality;
- accepting any fresh color transition as successful confirmation;
- hiding the timeout while retaining an unrelated authoritative result; or
- bypassing the target action owner with direct MQTT publication.

Those approaches would weaken RequestAction ownership or could accept an
incorrect color command as successful for other devices.

## Preferred Boundary

The clean fix belongs at the target action/feedback boundary:

1. the target module should expose or preserve a stable semantic color
   contract;
2. its action should avoid changing brightness unless that is part of the
   declared color value;
3. its feedback should expose the representation required for deterministic
   confirmation; and
4. the repaired target contract should be tested independently before
   ControlLight color is retested.

If that upstream repair is not undertaken immediately, the safe interim option
is an explicitly approved CL-021 wrapper change that disables only its color
capability. STATE, brightness and color temperature can remain active and
authoritatively confirmed.

## Gate Decision

The ControlLight wait helper and availability classification are not defective.
The current `INT_HEX` exact comparison correctly rejects a target that does not
provide exact integer color semantics.

No ControlLight core change is approved from this analysis. The next decision
gate is between repairing the target module's semantic color boundary and
temporarily disabling CL-021 color before broader rollout continues.
