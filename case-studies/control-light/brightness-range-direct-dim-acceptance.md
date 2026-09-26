# Calibrated brightness range and direct dim acceptance

**Date:** 2026-09-26
**Scope:** Explicit single-target range mapping; no fleet-wide opt-in.

## Contract

`brightnessRange` optionally maps a user-facing positive interval onto a
qualified native dimmer interval. `localMinimum` is an integer from 1 to 99;
`targetMinimum` is positive and below `dimmerTargetMax`. Positive values below
the local minimum clamp to that minimum. Zero retains the existing STATE-off
contract. Omitting the option preserves existing conversion and configuration
fingerprints. Member-confirmed groups and missing STATE/brightness capabilities
are rejected.

Command and feedback use inverse linear mappings with integer rounding.
Native brightness feedback is not a substitute for authoritative STATE.
The separate `zeroFeedbackIsMinimum` boolean is disabled by default. Enable
it only after proving that the target reports zero for its lowest positive
command; it maps that quantized feedback to the local minimum without ever
turning a positive command into a native zero command. Effective brightness
still becomes zero while STATE is false; reported brightness may retain the
minimum while off.

Electrical device calibration and the exposed command range are different
contracts. Do not use a device's electrical minimum as the native command
minimum without measurement.

## Accepted composition

The calibrated pilot combines this range with the independently qualified
`brightnessOffStateTransition: ['mode' => 'target-turns-on']`.
The existing dispatcher sends one mapped positive brightness command from
off, confirms STATE and brightness, and does not send a preliminary on command.
No helper, retry loop or additional wait path was introduced.

Both native and production-facade high/off/low sequences passed. The observer
confirmed no flash. Facade feedback settled at the configured minimum, including
the device's quantized native-zero report. The three facade actions produced
exactly three command increments and no error or confirmation-timeout increment.
Two command-free reconciliations preserved bindings and diagnostics. Other
callers, the global bootstrap, Alexa configuration and electrical calibration
were unchanged.

## Verification and rollout boundary

Regression tests cover invalid ranges, monotonic mapping, quantized round trips,
reported/effective semantics, passive feedback, zero-as-off, repeated-command
idempotence and direct-start composition. The full repository gate passed.
Exact source, transaction, topology and test evidence remains private.

The audited cohort still uses two immutable packages: the common package and
the range-enabled pilot package. Their Runtime file is identical, but their
Core differs by this opt-in mapping. This acceptance does not claim a completed
fleet-wide package consolidation or universal anti-flash qualification.

Two current mirror indexes and retained historical mirrors remain. An approved
parent-only move placed the pilot mirror at its domain category without changing
its content, identity or presentation. A single combined mirror and retirement
of historical artifacts remain separate ownership/retention decisions.
