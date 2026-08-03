# Color Chromaticity Conversion Analysis

**Gate:** Read-only live contract inspection and offline conversion analysis
**Result:** SHARED SEMANTICS IDENTIFIED — TWO DISTINCT TARGET BOUNDARIES
**Date:** 2026-07-27
**Live impact:** None

## Scope

This analysis compares the remaining color blockers for the Matter/Home
Assistant target of CL-020 and the Zigbee2MQTT target of CL-021.

The live work was read-only. It inspected existing target variables and the
already installed module conversion paths. No script was executed, no
`RequestAction()` call was made, no object or source was changed and no device
command was sent. The exact installation metadata remains in private evidence.

An executable offline matrix in
`tests/control-light/color-conversion-analysis.php` reproduces the relevant
projections and checks the proposed semantic boundary. It does not modify the
production candidate.

## Common ControlLight Semantics

For targets that expose color separately from brightness, ControlLight COLOR
shall represent chromaticity while DIMMER remains the sole intensity contract.
An RGB integer used by the facade is then a presentation and interchange
projection, not proof that its maximum channel value is authoritative
brightness.

Converting such an RGB value to hue/saturation deliberately discards HSV value.
Converting authoritative hue/saturation feedback back to the `~HexColor`
facade therefore uses a canonical full-value RGB projection. For example,
`#3366CC` and canonical `#4080FF` describe the same 220-degree hue and
75-percent saturation but have different HSV values.

Consequently, feedback confirmation must compare the native chromaticity
coordinates for a chromaticity-only target. Exact equality of two independently
rounded RGB projections is the wrong invariant for that target format.

## CL-020: Native HS Boundary

The Home Assistant Entity target writes and reports a two-component
hue/saturation value. Brightness is a separate target property and is not
included in its color action.

For the failed CL-020 test:

- requested facade color `#3366CC` projected to `[220,75]`;
- authoritative target feedback was `[219.685,74.803]`;
- hue differed by 0.315 degrees;
- saturation differed by 0.197 percentage points; and
- the full-value RGB projections differed in one channel.

This is a small target normalization, not the large semantic color change
suggested by comparing the original non-full-value RGB integer with the
canonical feedback integer.

The offline candidate therefore compares HS natively with circular hue
distance. A provisional bound of 0.5 degrees and 0.5 saturation percentage
points accepts the observed normalization while rejecting deviations beyond
either boundary. Hue is ignored only when both values remain inside the
achromatic saturation boundary.

These bounds are analysis candidates, not activated configuration. Before a
production change they require a multi-color offline matrix and a separately
approved real-device retest across the hue wrap-around and low-saturation
edges.

## CL-021: Z2M XY/Brightness Boundary

The live Zigbee2MQTT device exposes only one actionable integer `color`
variable to Symcon. Although the device description advertises both xy and HS,
there is no separate actionable `color_hs` variable on the current instance.

The installed module chooses its send representation from the currently
reported color mode. With `COLOR_TEMP` reported, it selects its CIE path. That
path converts the requested RGB integer to xy, derives brightness from RGB
luminance and sends both values. Feedback then reconstructs an RGB integer from
reported xy and brightness.

The prior test changed requested `#3366FF` to authoritative `#00E7FF`, moved
brightness and retained a temperature interaction. The hue shift is about
39 degrees and the saturation shift about 20 percentage points. Even the
module-only forward/reverse calculation produced `#0060FF`.

The CL-020 HS tolerance therefore cannot repair CL-021. Applying it globally
would correctly continue to reject CL-021, but it would not remove the
underlying brightness coupling or provide a stable target contract.

## Adapter Decision

The shared layer should define one chromaticity/intensity separation, with
representation-specific confirmation:

- exact integer equality remains valid for targets that declare stable RGB
  integer semantics;
- HS strings use circular hue and bounded saturation comparison;
- XY requires its own native, brightness-independent feedback endpoint and
  bounded xy comparison; and
- no adapter may accept freshness alone or a large coordinate transition as
  successful authoritative feedback.

CL-020 is eligible for a bounded HS adapter implementation after the remaining
offline edge matrix is complete. CL-021 remains color-disabled until the Z2M
module exposes a stable native color contract that does not implicitly change
brightness, preferably as part of the separately planned v6/module work.

## Next Gates

1. Extend the offline matrix across representative hues, wrap-around, low
   saturation, malformed values and exact-boundary cases.
2. Implement the HS-native matcher as a format-specific ControlLight change,
   regenerate the fileset and run the complete repository gate.
3. Prepare a separate rollback-backed CL-020 activation and color/brightness
   independence test; do not combine it with CL-021 work.
4. Reassess the Z2M v6 beta contract and, if still required, design the module
   fix and upstream regression/PR before re-enabling CL-021 color.
