# Hallway Pilot Full Functional Test

**Gate:** Complete all enabled pilot capabilities
**Result:** PASS
**Date:** 2026-07-20
**Live state:** Exact initial STATE, brightness and temperature restored

## Corrected Preflight Baseline

The pilot source, local actions, actionable targets, authoritative feedback and
STATE/DIMMER/temperature event contracts passed immediate read-only checks.
The lamp started on with reported brightness 29 and color temperature 2604 K.

An initial diagnostic assertion stopped safely before any device command
because it incorrectly expected absolute equality between execution and success
counters. Readback showed one retained historical brightness confirmation
timeout from the preceding day. The valid baseline was:

- 162 executions;
- 161 successes;
- one error and one confirmation timeout; and
- one matching bounded ErrorRingBuffer entry.

The relation `executions = successes + errors` was exact. The functional test
therefore used immutable error, timeout and ErrorRingBuffer baselines rather
than erasing or misclassifying historical diagnostics.

## Functional Sequence

Because the lamp was already on, the sequence preserved its real initial state:

1. STATE off;
2. STATE on;
3. DIMMER 40;
4. DIMMER 100;
5. color temperature 3000 K;
6. restore color temperature 2604 K;
7. restore brightness; and
8. perform one normalization-aware exact brightness restoration.

Every phase added exactly one ControlLight command. Authoritative local and
target feedback remained equal after every action. Z2M normalized brightness
40 to 39 and temperature 3000 K to 3003 K, both within the configured bounded
tolerances. Temperature restored exactly to 2604 K.

Requesting the original brightness 29 initially produced reported 28. This was
valid within tolerance but not an exact presentation restoration. A final
request of 30 mapped through the device's discrete level grid to reported 29,
restoring the exact initial value.

## Diagnostic and Regression Result

Eight actions advanced Commands from 3 to 11. Execution and success counters
advanced in parallel while maintaining the original one-error difference.
Errors, confirmation timeouts and ErrorRingBuffer content did not change.
Compensation was not required.

The final authoritative state exactly matched the initial state:

- STATE true;
- DIMMER 29; and
- color temperature 2604 K.

All 29 wrapper identities passed the final read-only regression. The pilot is
therefore functionally complete for every enabled capability. Together with
the three completed STATE/DIMMER sequences, all four active v2 ControlLight
instances now have bounded real-device evidence.
