# CL-003 Kürbis STATE and Brightness Functional Test

**Date:** 2026-08-03
**Gate:** Explicitly approved real-device test
**Result:** PASSED — INITIAL STATE RESTORED

## Preflight Correction

The first test preflight stopped before any device command because the target's
`last_seen` value was older than an arbitrary two-minute threshold while
availability still reported true. Read-only timestamp inspection showed quiet,
internally consistent values rather than contradictory feedback.

Using `last_seen` as a pre-dispatch gate would contradict the documented
hard-power contract and SAEF's availability behavior. A permitted interactive
command must be sent without first waiting for availability or telemetry to
become fresh. Missing bounded feedback is the point at which current
availability becomes diagnostic evidence.

## Test Sequence

The bounded direct facade sequence passed:

1. STATE off with authoritative false feedback;
2. STATE on with authoritative true feedback;
3. brightness 40, reported as 39 within the existing one-point target
   normalization tolerance; and
4. brightness restored to the exact initial 100.

The target refreshed `last_seen` after the first command. Four intended device
commands produced no errors or confirmation timeouts. Final diagnostics were
17 executions, four commands and 17 successes.

## Restoration and Remaining Gates

The complete tested-domain baseline was restored to STATE=true and
brightness=100. Color and Kelvin values remained byte-for-value unchanged and
were not commanded. Target availability remained true and all facade values
equalled their authoritative targets.

CL-003 is now proven for STATE and brightness. It is not yet fully
device-tested: Kelvin has never produced feedback, color retains the known Z2M
feedback risk, and the physical hard-off/hard-on observation remains a
separate coordinated gate.
