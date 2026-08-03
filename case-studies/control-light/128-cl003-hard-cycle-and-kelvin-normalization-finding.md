# CL-003 Hard Cycle and Kelvin Normalization Finding

**Date:** 2026-08-03
**Gate:** Explicitly approved supervised physical hard-off/hard-on test
**Result:** HARD CYCLE PASSED — KELVIN MATCHER FOLLOW-UP REQUIRED

## Physical Off Contract

The lamp was physically disconnected for approximately one minute. During the
confirmed power interruption, Z2M continued to expose stale availability=true,
STATE=true, brightness=100 and 4000 K. `last_seen` did not advance. This confirms
that neither availability nor retained device values can be treated as a
pre-dispatch proof of reachability for this device.

No command, error or timeout occurred while the lamp was without power.

## Immediate Hard-On Command

Immediately after the user confirmed physical power restoration, ControlLight
sent brightness 60 without checking availability or `last_seen` first. The
target returned 59 within the existing one-point brightness tolerance and
`last_seen` advanced. There was no new error or confirmation timeout.

Brightness then returned exactly to 100. The hard-power reconnection and
immediate interactive-command contract therefore passed.

## Post-Boot Color-Mode Finding

After the physical restart, the target retained a 4000 K variable value but
reported XY mode. The Kelvin number alone therefore did not prove that the
effective post-boot light mode was still color temperature.

A bounded restoration request of 3900 K changed the target to `COLOR_TEMP`, but
the device's mired resolution reported 3906 K. The six-Kelvin delta exceeded
the current fixed five-Kelvin matcher by one Kelvin and produced one bounded
`colorTemperature` feedback timeout. No retry was issued. The previously exact
4000 K request then restored 4000 K and `COLOR_TEMP` mode successfully.

## Final State and Decision

The exact intended final state is restored: STATE=true, brightness=100,
4000 K, `COLOR_TEMP`, target available and facade equal to target. The disabled
color capability remains disabled.

The physical hard-cycle gate is complete. CL-003 is not yet promoted to the
fully closed count because the fixed Kelvin tolerance rejects a legitimate
mired-quantized result. The recommended next step is an offline, shared-runtime
analysis of a mired-aware Kelvin matcher with cross-instance regression tests;
any runtime activation remains a separate gate.
