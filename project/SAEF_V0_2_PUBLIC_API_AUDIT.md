# SAEF v0.2 Public API Audit

**Baseline:** `v0.1.0`
**Target:** `v0.2.0`
**Audit date:** 2026-07-20
**Result:** PASS with documented compatibility notes

## API Boundary

The supported helper API is the function and constant inventory in
`helpers/README.md`. Global PHP visibility is not sufficient to make a symbol
public.

The following are not public API:

- functions tagged `@internal`;
- `SAEF_HELPER_*` declaration guards;
- implementation GUID constants;
- generated bundle/fileset export inventories;
- case-study classes and local implementation functions.

Fileset exports remain broader because the loader must detect every global
symbol collision. That deployment requirement does not create a support
promise for internal symbols.

## Supported Surface

SAEF v0.2 supports 29 helper functions.

### Validation

- `SAEF_ValidateParentObject()`
- `SAEF_ValidateIdent()`
- `SAEF_ValidateVariableType()`
- `SAEF_ValidateObjectName()`
- `SAEF_ValidateModuleGuid()`
- `SAEF_ValidateScriptType()`

### Object Configuration

- `SAEF_EnsureCategory()`
- `SAEF_EnsureVariable()`
- `SAEF_EnsureCyclicScriptEvent()`
- `SAEF_EnsureTriggeredScriptEvent()`
- `SAEF_EnsureScript()`
- `SAEF_EnsureDummy()`
- `SAEF_EnsureLink()`
- `SAEF_EnsureInstance()`
- `SAEF_EnsureProfile()`

### Variable Feedback

- `SAEF_WaitForVariable()`

### Runtime Diagnostics

- `SAEF_NormalizeConfigurationForHash()`
- `SAEF_CreateConfigurationHash()`
- `SAEF_EnsureRegistryVariable()`
- `SAEF_ReadRegistry()`
- `SAEF_WriteRegistry()`
- `SAEF_UpdateRegistryEntry()`
- `SAEF_EnsureStatisticsVariables()`
- `SAEF_IncrementStatistic()`
- `SAEF_SetStatisticTimestamp()`
- `SAEF_EnsureErrorRingBufferVariable()`
- `SAEF_ReadErrorRingBuffer()`
- `SAEF_AppendErrorRingBufferEntry()`
- `SAEF_ClearErrorRingBuffer()`

The supported constants are:

- `SAEF_WAIT_CHANGED`;
- `SAEF_WAIT_UPDATED`;
- `SAEF_ERROR_RING_BUFFER_MAX_CAPACITY`.

## Internal Compatibility Symbols

These global functions are present for file-level composition or compatibility
but are not supported as independent public APIs:

- `SAEF_CreateIgnoredConfigurationKeyMap()`;
- `SAEF_NormalizeConfigurationHashValue()`;
- `SAEF_ValidateRegistryVariable()`;
- `SAEF_GetStatisticVariableType()`;
- `SAEF_ValidateErrorRingBufferVariable()`;
- `SAEF_ValidateErrorRingBufferCapacity()`;
- `SAEF_ValidateWaitForVariableArguments()`;
- `SAEF_GetWaitMetadataKey()`;
- `SAEF_WaitLookbackMatches()`;
- `SAEF_WaitValueMatches()`.

The four `WaitForVariable` implementation functions already existed in v0.1.
The Diagnostics implementation functions are introduced with their public
v0.2 wrappers. Reusable callers shall use the public composition APIs instead.
Removal or incompatible changes still require deliberate compatibility review.

## Changes Since v0.1.0

Fourteen public functions are added:

- thirteen Runtime Diagnostics functions;
- `SAEF_EnsureTriggeredScriptEvent()`.

Three validation functions that already existed in v0.1 are now formally
included in the documented public surface:

- `SAEF_ValidateObjectName()`;
- `SAEF_ValidateModuleGuid()`;
- `SAEF_ValidateScriptType()`.

Seven existing Ensure functions receive a backward-compatible optional trailing
`$updateExistingPresentation` parameter. Existing positional calls remain
valid. New reusable callers should pass the policy explicitly.

Behavior corrections without incompatible signature changes include:

- explicit parent-automation event binding for Symcon 6.0+;
- parent/target ownership validation for cyclic events;
- same-second conditioned feedback detection in `SAEF_WaitForVariable()`;
- bounded polling sleep and Unix-second lookback handling.

The new Diagnostics APIs also fail explicitly on corrupt JSON, incompatible
variable types, unbounded error history and invalid statistic arithmetic.

## SemVer Assessment

The target version `0.2.0` is appropriate for the helper changes:

- existing required parameter lists are unchanged;
- new parameters are optional and trailing;
- new functionality is additive;
- behavior changes correct unsafe or nonfunctional contracts and are called out
  in the changelog.

The v0.1.0 `LICENSE` reserved all rights while its Composer metadata declared
MIT. Version 0.2.0 resolves that inconsistent baseline by establishing PolyForm
Noncommercial 1.0.0 as the canonical public license and aligning Composer
metadata. This is not a PHP API change, but it is a material usage-policy change
and must remain prominent in release notes.

## Verification

Reflection against the canonical helper sources confirmed all 29 public
function signatures. Direct helper tests cover Diagnostics, events,
presentation ownership and variable waiting. The executable public-API test
guards the 29 public and 10 internal function classifications. Bundle and
fileset checks cover the broader global export surface. The final offline
cohort review reran these checks through the aggregate `make check` gate.
