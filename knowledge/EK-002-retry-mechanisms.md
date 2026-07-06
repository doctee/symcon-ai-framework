# Engineering Knowledge EK-002

# Retry Mechanisms in IP-Symcon

**Status:** Draft 1.0

## Purpose

This Engineering Knowledge article explains how retry mechanisms should be designed in professional IP-Symcon solutions.

Retries are useful when automation depends on devices, networks, gateways,
MQTT brokers, cloud APIs or other external systems that may temporarily fail.
A retry mechanism should increase robustness without hiding permanent failures
or creating unsafe repeated actions.

---

## Problem

External systems are not always available when a Symcon script runs.

Typical examples:

- a device is temporarily offline,
- a cloud API does not respond,
- a gateway is restarting,
- a variable has not yet updated,
- a command was accepted but the expected state change is delayed.

A single failed operation does not always mean that the automation logic is
wrong. At the same time, blindly repeating an operation can be unsafe or
misleading.

---

## Engineering Context

Retry mechanisms are most useful when the failed operation is expected to become available again within a short time.

They are especially relevant for:

- cloud-connected devices,
- MQTT-based integrations,
- Home Assistant bridges,
- Zigbee or WLAN devices,
- device state verification,
- archive maintenance scripts,
- actuator commands with delayed feedback.

Retries should be treated as part of the automation design, not as an afterthought.

---

## Recommended Pattern

A robust retry mechanism usually follows this pattern:

```text
Operation
    ↓
Success?
    ├─ yes → continue
    └─ no
        ↓
Classify Failure
        ↓
Retry Allowed?
        ├─ yes → wait → retry
        └─ no
            ↓
        Report Failure
            ↓
        Recovery / Safe State
```

The important engineering decision is not only *how often* an operation is
retried, but also *whether the operation is safe to retry*.

---

## Design Decisions

### Retry Scope

Decide whether the retry happens:

- inside one script execution,
- across multiple executions,
- or as part of a state machine.

Short retries can remain local to one execution. Longer retries should usually persist state in owned variables.

### Retry Limit

Every retry mechanism should define a clear limit:

- maximum number of attempts,
- maximum elapsed time,
- or a defined stop condition.

Unbounded retries should be avoided.

### Retry Delay

Retry delay should match the expected recovery time.

Examples:

- a variable update may justify a delay of milliseconds or seconds,
- a device restart may require minutes,
- a cloud outage may require scheduled retries over a longer period.

### Retry Safety

Only retry operations that are safe to repeat.

Read operations are usually safe. Write operations and actuator commands require more care.

---

## Trade-offs

Retries improve robustness, but they also add complexity.

Benefits:

- fewer false alarms,
- better handling of temporary failures,
- improved resilience against network or cloud instability.

Costs:

- more state to manage,
- more complex error handling,
- risk of hiding permanent failures,
- possible repeated device commands.

A retry mechanism is justified when temporary failure is expected and the retry behaviour is clearly bounded.

---

## Common Anti-Patterns

### Infinite Retry

Repeating forever without timeout or failure reporting.

This hides real problems and may block automation.

### Retry without Classification

Retrying every failure in the same way.

Configuration errors, invalid object IDs and programming errors should usually
fail immediately instead of being retried.

### Retry without Logging

Retrying silently makes later diagnosis difficult.

At minimum, final failure should be visible.

### Retrying Unsafe Actions

Repeated actuator commands can be unsafe.

Examples include repeatedly unlocking, opening, switching or resetting devices without verifying the current state.

### Retry as a Replacement for State Management

If retry behaviour spans several executions, it should not rely on hidden
runtime state. Use explicit state variables or a state machine.

---

## Practical Checklist

Before implementing a retry mechanism, ask:

- Is the failure expected to be temporary?
- Is the operation safe to repeat?
- What is the maximum retry count or timeout?
- What delay is appropriate?
- What happens after final failure?
- Is the retry state visible or persisted if needed?
- Is the final error logged or exposed?
- Does the automation return to a safe state?

---

## Related Standards

- RS-001 Symcon Engineering Standards
- PHP Standards
- Documentation Standards

---

## Related ADRs

- ADR-0001 — Use RequestAction for controllable variables
- ADR-0002 — Use Ident over ObjectID where practical

---

## Related Knowledge

- EK-001 — State Machines in IP-Symcon
- EK-004 — Internal State Management
- EK-006 — Runtime Diagnostics

---

## Related Reference Implementations

- RI-002 — Runtime Diagnostics / Internal State, for explicit retry-adjacent
  diagnostics such as execution counters, error counters and bounded recent
  error history.
