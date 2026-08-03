# CL-003 Kelvin Functional Test

**Date:** 2026-08-03
**Gate:** Explicitly approved isolated real-device color-temperature test
**Result:** PASSED — FINAL STATE 4000 K

## Preconditions

The no-color wrapper and shutdown consumer retained their expected source
hashes. The wrapper was unbroken, the inverse alarm interlock was inactive, the
target instance and availability were active, and STATE, brightness and Kelvin
facade values equalled their authoritative targets. The color facade and color
feedback event remained disabled.

The initial Kelvin value was `0`, which is not a meaningful temperature to
restore. The approved test therefore selected 4000 K as both the test value and
the final stable value.

## Result

One request through the ControlLight Kelvin facade produced exact 4000 K
authoritative feedback. STATE remained true, brightness remained 100 and the
target changed from XY to `COLOR_TEMP` mode. Availability remained true.

The command counter increased by exactly one. Error and confirmation-timeout
counters did not change; the preceding color-contract failure remains retained
in bounded diagnostics.

CL-003 is now directly proven for every enabled capability: STATE, reported
brightness and Kelvin color temperature. It is still not classified as fully
device-tested because the physical hard-off/hard-on reconnection contract
remains a separate supervised gate.
