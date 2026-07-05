# Helper Library

This directory contains reusable PHP helper functions for professional IP-Symcon development within SAEF.

Helpers are intended to be small, explicit and reviewable building blocks. They support the standards, engineering knowledge and reference implementations in this repository.

## Design Principles

SAEF helpers should:

- use explicit parameters,
- avoid hidden installation-specific dependencies,
- validate inputs before changing Symcon state,
- preserve existing user data where practical,
- fail loudly on incompatible existing objects,
- be usable by humans and AI coding agents.

## Naming

Helper functions use the `SAEF_` prefix to avoid conflicts with existing private helper libraries or community functions.

## Structure

| Path | Purpose |
| --- | --- |
| `common/` | Shared validation and utility helpers |
| `object/` | Idempotent object-tree helpers |

## Current Helpers

| Helper | Purpose |
| --- | --- |
| `SAEF_ValidateParentObject()` | Validate that an object exists and can be used as parent |
| `SAEF_ValidateIdent()` | Validate a stable IP-Symcon Ident |
| `SAEF_ValidateVariableType()` | Validate a Symcon variable type |
| `SAEF_EnsureCategory()` | Idempotently create or update a category |
| `SAEF_EnsureVariable()` | Idempotently create or update a variable |
| `SAEF_EnsureCyclicScriptEvent()` | Idempotently create or update a cyclic script event |

## Related Artifacts

- `drafts/SYMCON_STANDARDS.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `references/RI-001-idempotent-configuration-script.md`
