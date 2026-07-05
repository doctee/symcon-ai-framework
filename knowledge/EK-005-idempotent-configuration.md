# Engineering Knowledge EK-005

# Idempotent Configuration in IP-Symcon

**Status:** Draft 1.0

## Purpose

This Engineering Knowledge article explains how configuration scripts should be designed so they can be executed repeatedly without creating inconsistent IP-Symcon installations.

Idempotent configuration is one of the fundamental engineering concepts within SAEF because reusable automation, templates and reference implementations all depend on predictable configuration behaviour.

---

## Problem

Configuration scripts are rarely executed only once.

Typical situations include:

- initial installation,
- adding new features,
- updating an existing solution,
- repairing damaged objects,
- migrating to a new server,
- synchronising configuration after manual changes.

A script that creates duplicate objects or depends on execution order becomes increasingly difficult to maintain.

---

## Engineering Context

An idempotent configuration script always moves the installation towards the desired target state.

Running the same script multiple times should produce the same resulting configuration.

This principle enables reliable deployment, safe updates and reproducible installations.

---

## Recommended Pattern

```text
Read Configuration
        ↓
Validate Inputs
        ↓
Locate Existing Objects
        ↓
Create Missing Objects
        ↓
Update Existing Objects
        ↓
Validate Result
```

The objective is not "always create", but "ensure the desired configuration exists".

---

## Design Decisions

### Treat Configuration as Desired State

Describe what should exist rather than which individual operations should be executed.

### Search Before Creating

Before creating categories, variables, events or profiles:

- search by Ident,
- verify ownership,
- reuse existing objects where appropriate.

### Separate Configuration from Runtime

Configuration creates structure.

Runtime logic uses that structure.

Avoid mixing both responsibilities.

### Make Changes Explicit

If a script changes an existing object, the intended behaviour should be documented.

Silent behavioural changes should be avoided.

### Design for Evolution

Configuration scripts should support gradual extension.

Adding new variables or events should not require recreating existing objects.

---

## Trade-offs

Benefits:

- repeatable deployments,
- easier maintenance,
- safer upgrades,
- reduced duplicate objects,
- simpler migration.

Costs:

- additional lookup logic,
- more validation,
- slightly more complex setup scripts.

The increased complexity is usually offset by significantly lower maintenance effort.

---

## Common Anti-Patterns

### Blind Object Creation

Creating new objects every time the script runs.

Result:

- duplicate variables,
- duplicate events,
- inconsistent configuration.

### ObjectID-Centric Configuration

Hardcoding ObjectIDs inside reusable configuration logic.

Prefer Idents and documented configuration values.

### Hidden Side Effects

A configuration script should not unexpectedly modify unrelated objects.

### Runtime Initialisation as Configuration

Configuration should create persistent structure.

Temporary runtime state belongs in the automation logic.

### Delete and Recreate

Deleting existing objects before recreating them often destroys history, links and user configuration.

Prefer incremental updates whenever practical.

---

## Practical Checklist

Before publishing a configuration script, verify:

- Can the script be executed repeatedly?
- Are existing objects reused?
- Are new objects created only when necessary?
- Are Idents used consistently?
- Are ownership boundaries respected?
- Are configuration changes documented?
- Can the script be interrupted and safely executed again?
- Does the script preserve user data where appropriate?

---

## Relationship to RS-001

RS-001 defines idempotent configuration as an engineering requirement.

This article explains how configuration scripts should be structured to fulfil that requirement.

---

## Related Standards

- RS-001 Symcon Engineering Standards
- PHP Standards
- Documentation Standards

---

## Related ADRs

- ADR-0002 — Use Ident over ObjectID where practical
- ADR-0003 — Private Overlay

---

## Related Knowledge

- EK-001 — State Machines
- EK-002 — Retry Mechanisms
- EK-003 — Archive Processing
- EK-004 — Internal State Management
