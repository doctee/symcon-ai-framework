# Engineering Knowledge EK-001

# State Machines in IP-Symcon

**Status:** Draft 1.0

## Purpose

This Engineering Knowledge article explains when and how state machines should
be used in professional IP-Symcon solutions.

Unlike RS-001, this document does not define mandatory rules. It explains the
engineering concepts, trade-offs and design patterns behind state-machine based
automation.

---

# What is a State Machine?

A state machine models an automation as a finite set of states and well-defined transitions.

Instead of describing behaviour as nested `if` statements, the automation explicitly answers two questions:

- What is the current state?
- Which events allow a transition to another state?

The automation behaviour is therefore driven by state transitions instead of procedural control flow.

---

# When to Use a State Machine

A state machine is recommended when an automation:

- has more than two operating states,
- contains retries or recovery logic,
- waits for external events,
- reacts differently depending on previous behaviour,
- contains manual acknowledgement,
- must recover after restarts.

Typical IP-Symcon examples include:

- cloud-connected devices,
- irrigation controllers,
- charging logic,
- alarm systems,
- washing machine monitoring,
- leak detection,
- complex lighting control.

---

# When NOT to Use a State Machine

Do not introduce a state machine simply because it sounds more structured.

Simple automations usually remain easier to understand as straightforward procedural code.

Examples:

- mirror one variable to another,
- switch a light after motion,
- send a notification,
- calculate a derived value.

The simplest solution that remains maintainable should be preferred.

---

# Typical Structure

A state machine usually consists of:

1. State
2. Events
3. Transition Rules
4. Actions
5. Recovery Logic

Each transition should be explicit and documented.

---

# State Persistence

One important design decision is whether the state survives script execution.

Transient state:

- exists only during one execution,
- suitable for short synchronous tasks.

Persistent state:

- stored in owned Symcon variables,
- survives restarts,
- enables retries,
- supports recovery.

Persistent state is generally preferred for long-running automations.

---

# Design Principles

Good state machines:

- have clearly named states,
- define valid transitions,
- avoid hidden transitions,
- separate transition logic from actions,
- make recovery explicit.

Avoid encoding multiple meanings into one state.

---

# Common Anti-Patterns

## Deeply Nested Conditions

Many nested `if` blocks often indicate an implicit state machine.

Replace them with explicit states.

---

## Hidden State

Avoid relying on undocumented runtime variables or execution order.

The current state should always be recoverable.

---

## Infinite Retry Loops

Retries without limits often hide permanent failures.

Combine retries with timeout handling and recovery logic.

---

## Mixed Responsibilities

Do not mix state management with device-specific implementation details.

Keep state transitions independent from hardware interfaces whenever practical.

---

# Relationship to RS-001

RS-001 defines **when** a state machine should be considered.

This article explains **how** to design one.

---

# Practical Checklist

Before introducing a state machine, ask:

- Are there more than two meaningful states?
- Does behaviour depend on previous events?
- Is retry or timeout logic required?
- Must execution continue after a restart?
- Will explicit states improve readability?

If most answers are "yes", a state machine is usually appropriate.

---

# Related Standards

- RS-001 Symcon Engineering Standards
- PHP Standards
- Engineering Principles

---

# Related Reference Implementations

- RI-002 — Runtime Diagnostics / Internal State, for explicit state ownership,
  statistics and bounded error context that can support state-machine based
  automations.
