# Helper Library

This directory contains reusable PHP helper functions for professional IP-Symcon development within SAEF.

Helpers are intended to be small, explicit and reviewable building blocks. They should support the standards, engineering knowledge and reference implementations in this repository.

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

## Initial Scope

The first helper set focuses on idempotent object creation:

- `SAEF_EnsureVariable()`

Additional helpers will follow for categories, events, scripts, retry handling and variable waiting.

## Related Artifacts

- `drafts/SYMCON_STANDARDS.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `references/RI-001-idempotent-configuration-script.md`
