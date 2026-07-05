# Engineering Knowledge EK-004

# Internal State Management in IP-Symcon

**Status:** Draft 1.0

## Purpose

This Engineering Knowledge article explains how internal state should be modelled in professional IP-Symcon automations.

Internal state is the information an automation owns itself. It is not the state of a physical device, but the knowledge required for the automation to operate reliably across script executions, restarts and failures.

---

## Problem

Many automation problems are caused by hidden state.

Typical examples are:

- timestamps stored only in local variables,
- retry counters recreated every execution,
- undocumented cache values,
- implicit execution order,
- status encoded in several unrelated variables.

Such implementations are difficult to debug, migrate and review.

---

## Engineering Context

Internal state becomes important whenever an automation:

- spans multiple executions,
- performs retries,
- detects stale data,
- resumes long-running work,
- keeps statistics,
- stores configuration fingerprints,
- records diagnostics,
- implements a state machine.

State should be treated as part of the automation architecture.

---

## Recommended Pattern

```text
Configuration
        │
        ▼
Internal State
        │
        ├── Runtime Status
        ├── Persistent Metadata
        ├── Cache
        ├── Statistics
        ├── Error Information
        └── Recovery Information
```

Keep every category explicit and owned by exactly one automation.

---

## Design Decisions

### Own Your State

Every persistent value should have one owner.

Examples:

- automation status,
- last successful execution,
- retry counter,
- configuration hash,
- last processed timestamp.

### Separate Device State from Internal State

Do not store device information as internal state if it already exists in the owning Symcon instance.

Only persist information that belongs to the automation itself.

### Make State Discoverable

Create owned variables below a dedicated parent object.

Use stable Idents.

Document every persistent variable.

### Keep State Minimal

Persist only what is required to resume, diagnose or optimise the automation.

Derived values should usually be recalculated.

---

## Typical Internal State

### Runtime Status

Current operating mode.

Examples:

- Idle
- Running
- Waiting
- Retry
- Error

### Metadata

Persistent information.

Examples:

- last execution,
- last successful execution,
- version,
- configuration hash.

### Cache

Temporary optimisation data.

Cache should always be rebuildable.

### Statistics

Operational counters.

Examples:

- executions,
- retries,
- errors,
- processing duration.

### Diagnostics

Structured engineering information.

Examples:

- latest error,
- error history,
- warning history.

---

## Trade-offs

Benefits:

- deterministic behaviour,
- restart safety,
- easier diagnostics,
- simpler recovery,
- AI-readable architecture.

Costs:

- additional variables,
- migration effort,
- documentation overhead.

Well-designed internal state usually reduces overall system complexity despite introducing more explicit objects.

---

## Common Anti-Patterns

### Hidden Runtime State

Relying on static variables or execution order.

### Duplicate State

Storing the same information in multiple places.

### Permanent Cache

Treating cache as authoritative data.

### Mixing Configuration and Runtime State

Configuration should change rarely.

Runtime state changes during operation.

Keep them separate.

### Generic JSON Dump

One large JSON variable containing unrelated information.

Prefer structured variables unless a documented registry is the better engineering choice.

---

## Practical Checklist

- Does every persistent value have a clear owner?
- Is configuration separated from runtime state?
- Can cache be rebuilt?
- Can the automation recover after restart?
- Are timestamps explicit?
- Is retry state persisted if required?
- Are diagnostic variables documented?
- Can obsolete state be cleaned up?

---

## Relationship to RS-001

RS-001 defines the rules for variable lifecycle, ownership and explicit state.

This article explains how internal automation state should be designed.

---

## Related Standards

- RS-001 Symcon Engineering Standards
- PHP Standards
- Documentation Standards

---

## Related ADRs

- ADR-0002 — Use Ident over ObjectID
- ADR-0003 — Private Overlay

---

## Related Knowledge

- EK-001 — State Machines
- EK-002 — Retry Mechanisms
- EK-003 — Archive Processing
- EK-005 — Idempotent Configuration
