# ADR-0004 — Introduce Architectural Patterns

**Status:** Accepted  
**Date:** 2026-07-05

## Context

SAEF currently distinguishes between:

- Principles
- ADRs
- Standards
- Engineering Knowledge
- Reference Implementations
- Templates
- Helpers

During development it became clear that recurring architectural solutions such as state machines, retry workflows, registries and watchdogs are neither pure engineering knowledge nor complete reference implementations.

Without a dedicated artifact type these concepts would either:

- duplicate information across multiple reference implementations, or
- force reference implementations to explain architecture instead of demonstrating implementation.

## Decision

Introduce a new top-level repository section:

```text
patterns/
```

The `patterns/` directory contains reusable architectural patterns that describe proven solution structures independently of a concrete implementation.

Patterns bridge the gap between engineering knowledge and executable reference implementations.

## Rationale

The repository now follows this progression:

```text
Principles
        ↓
Standards
        ↓
Engineering Knowledge
        ↓
Architectural Patterns
        ↓
Reference Implementations
        ↓
Templates
        ↓
Production Code
```

Each artifact has a distinct purpose:

| Artifact | Purpose |
|----------|---------|
| Engineering Knowledge | Explain concepts, trade-offs and design decisions. |
| Pattern | Describe a reusable solution structure. |
| Reference Implementation | Demonstrate a complete implementation. |
| Template | Provide a starting point for new projects. |

This separation improves reuse and keeps individual artifacts focused.

## Initial Pattern Candidates

The first architectural patterns are expected to include:

- PAT-001 — State Machine
- PAT-002 — Retry
- PAT-003 — Registry
- PAT-004 — Idempotent Configuration
- PAT-005 — Archive Processing
- PAT-006 — Watchdog
- PAT-007 — Event Router

## Consequences

### Positive

- Clear separation between architecture and implementation.
- Less duplication across reference implementations.
- Better learning path for humans and AI coding agents.
- Easier evolution of implementation without changing architectural guidance.

### Negative

- One additional repository section to maintain.
- Architectural patterns require their own review process.

## Alternatives Considered

### Encode patterns only in Engineering Knowledge

Rejected because knowledge articles explain concepts but are not optimized for reusable solution structures.

### Encode patterns only in Reference Implementations

Rejected because implementation details obscure the underlying architectural pattern.

## Related Artifacts

- ARCHITECTURE.md
- AGENTS.md
- project/ENGINEERING_MODEL.md
- drafts/SYMCON_STANDARDS.md
- knowledge/
- references/
