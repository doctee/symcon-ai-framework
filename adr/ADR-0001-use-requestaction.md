# ADR-0001: Prefer RequestAction over SetValue for controllable Symcon variables

Status: Accepted  
Date: 2026-07-05

## Context

IP-Symcon variables can often be changed directly with `SetValue()`. However, many device, module, and gateway integrations expose controllable variables where the correct operation is performed through the variable action.

Directly setting such a variable may only change the Symcon state and may not trigger the device, module, gateway, validation logic, side effects, or error handling.

## Decision

For controllable variables, use `RequestAction()` instead of `SetValue()`.

`SetValue()` is acceptable for internal state variables, calculated values, cache variables, helper variables, or script-owned variables without external action semantics.

## Rationale

`RequestAction()` uses the action path intended by the module or device integration. This preserves validation, side effects, transport logic, and device communication.

This makes automation behavior closer to user interaction in WebFront, apps, voice control, or other Symcon frontends.

## Consequences

Positive:

- safer interaction with devices,
- fewer state/device mismatches,
- module logic is respected,
- better compatibility with future module changes.

Negative:

- may fail if no action is registered,
- sometimes requires handling errors or unsupported actions,
- not suitable for all internal variables.

## Alternatives considered

### Direct `SetValue()`

Rejected for device-facing variables because it may bypass device logic.

### Mixed usage without rule

Rejected because it makes scripts inconsistent and harder to review.

## Related

- `project/AI_PROJECT.md`
- `standards/symcon-standards.md`
