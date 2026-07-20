# Isolated Zigbee2MQTT Command Trace Report

**Gate:** CL-023 isolated command and feedback trace
**Result:** PASS
**Date:** 2026-07-19
**Final live state:** Safe baseline restored

## Purpose

Report 18 recorded an unexpected STATE-off transition and one additional
ControlLight command during the DIMMER-40 interval. This gate separated the
ControlLight request, the installed target-module payload and the returned
device state in one bounded trace.

The preflight again required the corrected effective wait-helper and pinned
wrapper identities, safe STATE false/false, reported DIMMER 100/100 and
unchanged error diagnostics.

## Installed Action Contract

Read-only inspection of the installed Zigbee2MQTT device module showed that a
brightness action:

- normalizes the local percentage to the device's 0–254 range;
- publishes only the `brightness` property; and
- does not add a STATE property in the Symcon module.

The target exposes STATE, brightness and a device-level
`level_config.execute_if_off` property. That property was false and was not
changed by this gate.

## Bounded Trace

Debug-file capture was enabled only for the affected device instance. No debug
file existed before the trace. The approved sequence then ran without a delay
between independently confirmed steps:

1. STATE true produced one STATE-on payload and one ControlLight command.
2. The receive path first processed an older OFF report and then the current ON
   report. The corrected wait helper ignored the non-matching observation and
   authoritatively confirmed ON without timeout.
3. DIMMER 40 produced one brightness-only payload with normalized device value
   101 and exactly one ControlLight command.
4. The device reported brightness 101 and STATE ON. The Symcon module converted
   this to local/target DIMMER 39, within the explicit one-point tolerance.
5. DIMMER 100 produced one brightness-only payload with device value 254;
   brightness returned to 100 and STATE remained ON.
6. STATE false produced one STATE-off payload; local and target STATE both
   became false while reported brightness remained 100.

Every requested phase advanced Commands by exactly one. Errors, confirmation
timeouts and ErrorRingBuffer content did not change. All four actions completed
with authoritative equality.

## Cleanup and Regression

Debug logging was disabled in the transaction's cleanup path. The exclusively
created debug file was read, hash-recorded transiently and removed; no temporary
object, event or configuration remained. Final STATE was false locally and at
the target, and final DIMMER was 100 on both sides.

The wrapper, helper, ownership and diagnostics contracts remained unchanged.
The transaction completed without cleanup, transport, execution or truncation
error.

## Finding

The DIMMER action does not intrinsically switch this target off. The isolated
trace disproves the fixed brightness/STATE-coupling hypothesis from report 18.
The earlier interval contained one extra ControlLight command and is therefore
best explained as a concurrent action between the two readbacks. Its exact
external origin was not identified, but the bounded dependency scan found no
installed script source that commands the four CL-023 variables.

This is a multi-controller boundary, not a wait-helper defect. ControlLight
correctly follows subsequent authoritative target events, but it cannot prevent
another controller from issuing a command after a completed request.

## Gate Decision

The corrected wait helper and the complete CL-023 STATE/DIMMER functional
sequence are **PASS**. CL-023 is cleared as Wave 2 live evidence. Broader rollout
still requires per-instance brightness-semantics and consumer gates; it must not
assume exclusive control of shared targets.
