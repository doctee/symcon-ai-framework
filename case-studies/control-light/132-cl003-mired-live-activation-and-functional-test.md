# CL-003 Mired Live Activation And Functional Test

**Date:** 2026-08-04
**Scope:** CL-003 Z2M color-temperature feedback
**Result:** PASSED

## Minimal Activation Boundary

The current consolidated ControlLight candidate contains several later runtime
features in addition to the Mired-aware Kelvin matcher. It was therefore not
selected directly for the live test.

Instead, the immutable candidate was reconstructed from the byte-exact
previously active CL-003 fileset. Only `ControlLightCore.php` changed
functionally:

- the existing fixed Kelvin tolerance remains the first comparison;
- Z2M Kelvin feedback may additionally match when requested and reported values
  map to the same rounded integer Mired value; and
- non-Z2M presets retain the preceding fixed target-unit behavior.

`ControlLightRuntime.php`, Hue Wall sources and every shared helper in that
consumer fileset remained byte-identical. Offline checks proved the observed
3900/3906 K match, rejection of a different-Mired response and unchanged
Matter behavior.

The candidate was staged and passed preflight without selecting it as the
global bootstrap. Only the CL-003 wrapper was changed to reference the new
immutable fileset. Its active trigger events were quiesced temporarily and
restored exactly. Two command-free reconciliation runs passed with no device
or MQTT action, no new error and no restart.

## Physical-Power Precondition

CL-003 is normally hard-switched. The functional gate therefore waited until
the user had powered the lamp and fresh Z2M availability and target feedback
were visible. No command was sent while the device reported unavailable.

## Functional Result

Exactly one `RequestAction()` was issued through the ControlLight
color-temperature facade:

| Observation | Value |
| --- | ---: |
| Requested color temperature | 3900 K |
| Authoritative Z2M feedback | 3906 K |
| Fixed difference | 6 K |
| Configured fixed tolerance | 5 K |
| Requested representation | 256 Mired |
| Feedback representation | 256 Mired |

The old fixed comparison would have timed out because the difference is one
Kelvin beyond its bound. The explicit Mired-aware matcher accepted the
authoritative feedback because both values represent the same device-side
quantization step.

The facade synchronized to the authoritative 3906 K value. The command counter
increased by exactly one. Error and confirmation-timeout counters did not
change, and the bounded error history remained byte-identical.

## Scope Preservation

No other wrapper was migrated. Four remaining wrappers continue to reference
the preceding shared fileset, and all other immutable ControlLight cohorts are
unchanged. This functional result therefore proves the matcher for CL-003
without broadening the activation to unrelated lamps or protocol presets.

The private evidence retains exact wrapper, package, fileset, source, event,
counter and backup identities. The public result contains no installation
ObjectIDs, paths or MQTT topics.
