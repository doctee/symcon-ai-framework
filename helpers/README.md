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
| `diagnostics/` | Runtime diagnostics, configuration hashes and internal state helpers |
| `object/` | Idempotent object-tree helpers |
| `variable/` | Variable wait and action helpers |

## Current Helpers

| Helper | Purpose |
| --- | --- |
| `SAEF_ValidateParentObject()` | Validate that an object exists and can be used as parent |
| `SAEF_ValidateIdent()` | Validate a stable IP-Symcon Ident |
| `SAEF_ValidateVariableType()` | Validate a Symcon variable type |
| `SAEF_NormalizeConfigurationForHash()` | Normalize configuration arrays for stable hash creation |
| `SAEF_CreateConfigurationHash()` | Create a stable SHA-256 hash for configuration arrays |
| `SAEF_EnsureRegistryVariable()` | Idempotently create or update a string variable for small registry metadata |
| `SAEF_ReadRegistry()` | Read registry metadata from a JSON string variable |
| `SAEF_WriteRegistry()` | Write registry metadata to a JSON string variable |
| `SAEF_UpdateRegistryEntry()` | Update one registry entry and persist the registry |
| `SAEF_EnsureStatisticsVariables()` | Idempotently create or update statistic variables |
| `SAEF_IncrementStatistic()` | Increment an integer or float statistic variable |
| `SAEF_SetStatisticTimestamp()` | Set an integer statistic variable to a Unix timestamp |
| `SAEF_EnsureCategory()` | Idempotently create or update a category |
| `SAEF_EnsureVariable()` | Idempotently create or update a variable |
| `SAEF_EnsureCyclicScriptEvent()` | Idempotently create or update a cyclic script event |
| `SAEF_EnsureScript()` | Idempotently create or update a script |
| `SAEF_EnsureDummy()` | Idempotently create or update a Dummy Module instance |
| `SAEF_EnsureLink()` | Idempotently create or update a link |
| `SAEF_EnsureInstance()` | Idempotently create or update an instance by module GUID |
| `SAEF_EnsureProfile()` | Idempotently create or validate a variable profile |
| `SAEF_WaitForVariable()` | Wait for a variable change or update with optional value check |

## Related Artifacts

Registry helpers are intended for small script-owned metadata only. Discovery payloads or large data sets must not be stored in registry variables.

- `drafts/SYMCON_STANDARDS.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `references/RI-001-idempotent-configuration-script.md`
