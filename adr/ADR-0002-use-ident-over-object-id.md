# ADR-0002: Prefer Ident, path, or configuration over hardcoded object IDs

Status: Accepted  
Date: 2026-07-05

## Context

IP-Symcon object IDs are installation-specific. They are stable within one installation but not portable across installations, backups, recreated objects, test systems, or published examples.

Many scripts become difficult to reuse or migrate when object IDs are scattered throughout the code.

## Decision

Use stable object lookup mechanisms where possible:

- Ident below a known parent object,
- object path,
- explicit configuration section,
- helper functions for object creation and discovery.

Hardcoded object IDs are allowed only when they are installation-specific configuration and clearly separated from reusable logic.

## Rationale

Idents are technical names intended for stable object addressing below a parent. They are more suitable for generated or managed objects than display names.

Separating object IDs into configuration makes scripts easier to read, review, migrate, and publish without leaking private installation details.

## Consequences

Positive:

- better portability,
- cleaner public/private separation,
- easier automated object creation,
- fewer hidden dependencies.

Negative:

- object lookup code is slightly more verbose,
- parent objects or configuration roots must be known,
- legacy scripts may still require IDs during migration.

## Alternatives considered

### Hardcode all IDs

Rejected for reusable framework code because it prevents portability and increases private-data leakage risk.

### Use object names only

Rejected because names are user-facing and may change.

## Related

- `project/AI_PROJECT.md`
- `glossary/README.md`
