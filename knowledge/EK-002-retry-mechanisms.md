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

### Variable Feedback and Timestamp Resolution

IP-Symcon variable metadata timestamps have second resolution. A feedback value
can change after a wait begins while `VariableUpdated` or `VariableChanged`
still equals the captured start timestamp.

A conditioned wait must therefore not rely exclusively on
`currentTimestamp > startTimestamp`. It should also recognize an observed
transition of the expected-value condition from false to true. The baseline
condition must be captured first so that a value which already matched before
the wait is not mistaken for new feedback.

Polling should keep its API cost explicit:

- timestamp-only waits need no value read;
- waits with an expected value or predicate need at most one metadata read and
  one value read per interval.

This does not make same-value updates observable inside one second when no
higher-resolution signal exists. Callers that only need confirmation of a
target value should avoid issuing the action when that value already matches.

### External Writers and Confirmation Scope

Authoritative feedback proves that the expected target condition was observed
within the bounded confirmation interval. It does not reserve the target after
the wait returns and does not prove that the automation is its only writer.

A semaphore has the same scope limitation: it serializes only executions that
use the same semaphore. Physical controls, device-module behavior, MQTT or
fieldbus peers and unrelated automations may still change the target during or
after the confirmation interval.

When a supervised test observes an unexpected transition:

1. retain the confirmed value and timestamp sequence;
2. compare the automation's command counter with the number of intended
   actions;
3. distinguish the outbound command payload from later device feedback;
4. stop and restore a safe state before continuing; and
5. classify a wait defect only when the expected feedback occurred inside the
   wait contract but was not recognized.

An additional command or a payload from another controller is concurrency
evidence, not a reason to lengthen the wait automatically.

### Availability Is Post-Failure Evidence

Device availability commonly lags a hard power transition. A light can be
powered on physically while its gateway still exposes the preceding offline
value. Using that value as a pre-dispatch gate would lose an interactive
command arriving during reconnection.

For a permitted device command, use this sequence:

1. dispatch the command without waiting for availability to become true;
2. perform the normal bounded authoritative-feedback check;
3. return success immediately when feedback confirms the expected condition;
4. only after missing feedback, inspect the latest optional availability value;
5. classify the failure as `device_offline` when the device is still reported
   unavailable, otherwise retain the general feedback-timeout class.

Availability is therefore diagnostic evidence after a failed confirmation. It
does not replace domain-specific safety interlocks and does not authorize an
otherwise unsafe command. Automatic callers may stop or alter their own retry
sequence for `device_offline`, while interactive callers remain free to try
again after the device reconnects.

Do not use an uncaught exception as a transport mechanism across a Symcon
action-script boundary. `RequestAction()` can cause the action script's
exception to be reported independently by the ScriptEngine, even when the
initiating script catches its own call. The action script should persist the
classified operational failure in bounded diagnostics and return normally.
The initiating automation remains responsible for its own authoritative
feedback check or an explicitly designed shared status contract. Configuration
and programming defects are not expected operational failures and should still
fail visibly.

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
