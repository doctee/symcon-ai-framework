# CL-027 DIMMER Functional Test

**Gate:** Bounded real-device sequence after capability correction
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Final STATE off; retained brightness 100

## Preflight

The corrected wrapper matched its exact candidate source. Local and target
feedback started at STATE false and brightness 50. Diagnostics showed equal
execution/success counters with zero commands, errors and confirmation
timeouts, and the error history was empty. The configured alarm condition
allowed control.

## Functional Sequence

The lamp passed:

1. STATE on while retaining brightness 50;
2. DIMMER 40;
3. DIMMER 100; and
4. STATE off.

Each phase added exactly one ControlLight command. The Z2M device normalized
the requested 40 percent to 39 percent; local and target feedback both reported
39 and remained within the configured tolerance. The 100 percent phase was
exact. STATE off retained local and target brightness 100, proving `reported`
semantics for the newly enabled capability.

Execution and success counters remained equal throughout. Errors, confirmation
timeouts and error history stayed zero/empty. No compensation was required,
and the final STATE was safely false.

## Regression Decision

The final read-only regression passed all 29 wrapper identities. The DIMMER
capability correction and its real-device behavior are therefore **PASS**.
CL-027 now has the same proven STATE/DIMMER functional sequence as the earlier
two-capability reference instances.
