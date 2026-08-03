# Helper Library

This directory contains reusable PHP helper functions for professional
IP-Symcon development within SAEF.

Helpers are intended to be small, explicit and reviewable building blocks.
They support the standards, engineering knowledge and reference implementations
in this repository.

## Design Principles

SAEF helpers should:

- use explicit parameters,
- avoid hidden installation-specific dependencies,
- validate inputs before changing Symcon state,
- preserve existing user data where practical,
- fail loudly on incompatible existing objects,
- be usable by humans and AI coding agents.

Object Ensure helpers accept an optional presentation-update policy. The
backward-compatible default `true` retains the v0.1 behavior of updating
configured display properties. New reusable artifacts should pass the policy
explicitly and normally use `false`, so names, positions, icons and visibility
are applied only when an object is first created. Idents, parents, types and
functional configuration remain managed regardless of presentation policy.

## Naming

Helper functions use the `SAEF_` prefix to avoid conflicts with existing
private helper libraries or community functions.

## Public API Boundary

The functions and constants listed in this README form the supported helper
API. A global declaration alone does not make a symbol public. Functions tagged
`@internal`, `SAEF_HELPER_*` guard constants and implementation GUID constants
remain compatibility or deployment details.

Generated bundle and fileset `functionExports` inventories list every global
function that can collide at load time. They are deliberately broader than the
public API and must not be used as an API catalogue.

Public API changes follow Semantic Versioning. New parameters should normally
be optional trailing parameters. Tightened validation and corrected behavior
must be recorded in `CHANGELOG.md`, even when the PHP signature is unchanged.

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
| `SAEF_ValidateObjectName()` | Validate a non-empty user-facing object name |
| `SAEF_ValidateModuleGuid()` | Validate the format and availability of a module GUID |
| `SAEF_ValidateScriptType()` | Validate a supported Symcon script type |
| `SAEF_NormalizeConfigurationForHash()` | Normalize configuration arrays for stable hash creation |
| `SAEF_CreateConfigurationHash()` | Create a stable SHA-256 hash for configuration arrays |
| `SAEF_EnsureRegistryVariable()` | Idempotently create or update a string variable for small registry metadata |
| `SAEF_ReadRegistry()` | Read registry metadata from a JSON string variable |
| `SAEF_WriteRegistry()` | Write registry metadata to a JSON string variable |
| `SAEF_UpdateRegistryEntry()` | Update one registry entry and persist the registry |
| `SAEF_EnsureStatisticsVariables()` | Idempotently create or update statistic variables |
| `SAEF_IncrementStatistic()` | Increment an integer or float statistic variable with per-variable serialization |
| `SAEF_SetStatisticTimestamp()` | Set an integer statistic variable to a Unix timestamp |
| `SAEF_EnsureErrorRingBufferVariable()` | Idempotently create or update a string variable for bounded error history |
| `SAEF_ReadErrorRingBuffer()` | Read bounded error history from a JSON string variable |
| `SAEF_AppendErrorRingBufferEntry()` | Append one error entry and trim the buffer to a fixed capacity |
| `SAEF_ClearErrorRingBuffer()` | Clear bounded error history |
| `SAEF_EnsureCategory()` | Idempotently create or update a category |
| `SAEF_EnsureVariable()` | Idempotently create or update a variable |
| `SAEF_EnsureCyclicScriptEvent()` | Idempotently create or update a cyclic script event |
| `SAEF_EnsureTriggeredScriptEvent()` | Idempotently create or update an update/change-triggered script event |
| `SAEF_EnsureScript()` | Idempotently create or update a script |
| `SAEF_EnsureDummy()` | Idempotently create or update a Dummy Module instance |
| `SAEF_EnsureLink()` | Idempotently create or update a link |
| `SAEF_EnsureInstance()` | Idempotently create or update an instance by module GUID |
| `SAEF_EnsureProfile()` | Idempotently create or validate a variable profile |
| `SAEF_WaitForVariable()` | Wait for a variable change/update and detect conditioned same-second value transitions |

## Public Constants

| Constant | Contract |
| --- | --- |
| `SAEF_WAIT_CHANGED` | Select `VariableChanged` semantics for `SAEF_WaitForVariable()` |
| `SAEF_WAIT_UPDATED` | Select `VariableUpdated` semantics for `SAEF_WaitForVariable()` |
| `SAEF_ERROR_RING_BUFFER_MAX_CAPACITY` | Maximum supported error ring buffer entry count (`100`) |

`SAEF_WaitForVariable()` accounts for IP-Symcon's second-resolution variable
metadata. When an expected value or predicate is supplied, it detects a
false-to-true value condition even if the selected metadata timestamp remains
in the same second. A pre-existing matching value is not treated as new
feedback unless the configured lookback permits it.

The polling cost remains bounded: timestamp-only waits perform one metadata
read and no value read per poll; conditioned waits perform one metadata read
and at most one value read per poll. The final polling interval is truncated to
the remaining timeout budget. Lookback values are rounded up once to the whole
seconds exposed by IP-Symcon variable metadata.

## Global Deployment Ownership

Helper guards prevent duplicate declarations; they do not select a version.
When multiple bundles or filesets contain the same global `SAEF_` function, the
artifact loaded first by the installation autoloader owns that function for the
complete PHP context lifetime.

Before updating a shared helper:

1. inventory every exporting and consuming bundle/fileset;
2. identify the earliest global load owner;
3. activate the update through that owner;
4. start a clean PHP context or service when the old function is already
   loaded; and
5. verify the effective source file and hash with Reflection.

Selecting only a later consumer does not select its guarded helper copy.

## Related Artifacts

Registry and error ring buffer helpers are intended for small script-owned
metadata only. Discovery payloads or large data sets must not be stored in
these variables. Error ring buffers additionally enforce a fixed entry
capacity and reject malformed or oversized stored histories. Statistics reject
non-finite arithmetic, integer overflow and fractional increments for integer
variables instead of silently changing numeric meaning.

- `standards/SYMCON_STANDARDS.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `knowledge/EK-006-runtime-diagnostics.md`
- `project/SAEF_SYMCON_BUNDLE_BUILD_DESIGN.md`
- `references/RI-001-idempotent-configuration-script.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
