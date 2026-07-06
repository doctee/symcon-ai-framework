# Engineering Knowledge EK-004

# Internal State Management in IP-Symcon

**Status:** Draft 1.0

## Purpose

This Engineering Knowledge article explains how internal state should be modelled in professional IP-Symcon automations.

Internal state is the information an automation owns itself. It is not the state of a physical device, but the knowledge required for the automation to operate reliably across script executions, restarts and failures.

This article explains the engineering concepts behind RS-001 rules for explicit state, diagnostics and ownership. It does not define additional mandatory rules.

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

## Runtime Diagnostics in SAEF v0.2.0

Runtime diagnostics are a specialised form of internal state.

Their purpose is to make an automation easier to understand during operation without turning logs, status variables or JSON fields into unbounded data stores. RI-002 demonstrates the current SAEF composition model for runtime diagnostics.

### Configuration Hash

A configuration hash is a compact fingerprint of the desired configuration.

It helps answer:

- Which configuration version did this automation last apply?
- Did the desired configuration change since the previous run?
- Can a support or review step compare two runs without inspecting every configuration field?

Volatile fields such as timestamps, runtime values or last-run metadata should be ignored before hashing. The hash is diagnostic metadata, not a security control.

### Registry Pattern

A registry is a small script-owned metadata map.

It is useful for values such as:

- component version,
- configuration hash,
- previous configuration hash,
- migration marker,
- last known phase.

A registry should not become a generic JSON dump. Discovery payloads, device snapshots, large API responses and historical data belong in purpose-built structures or archive processing, not in a registry variable.

### Statistics

Statistics are explicit counters and timestamps.

Typical examples:

- execution count,
- error count,
- retry count,
- last run timestamp,
- last successful run timestamp.

Statistics should usually be separate variables rather than fields inside a large JSON blob. Separate variables make ownership, profile selection and review easier.

### Error Ring Buffer

An error ring buffer stores a bounded history of recent diagnostic failures.

It is useful when the latest error alone is not enough to understand intermittent problems. The buffer must remain fixed-size so diagnostics do not grow without limit.

Error ring buffers should contain concise context only. They must not contain credentials, tokens, private network details, full discovery payloads or large external responses.

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

RS-001 defines the rules for variable lifecycle, ownership, explicit state and diagnostics.

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
- EK-006 — Runtime Diagnostics

---

## Related Reference Implementations

- RI-001 — Idempotent Configuration Script
- RI-002 — Runtime Diagnostics / Internal State
