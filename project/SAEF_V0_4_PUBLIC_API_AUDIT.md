# SAEF v0.4 Public API Audit

**Baseline:** `v0.3.0`
**Target:** `v0.4.0`
**Audit date:** 2026-08-24
**Result:** PASS

## API Boundary

The supported API is the function and constant inventory documented in
`helpers/README.md` and enforced by `tests/helpers/public-api.php`. Global PHP
visibility alone does not make a symbol public.

Functions tagged `@internal`, `SAEF_HELPER_*` declaration guards,
implementation GUID constants, case-study classes, deployment operations and
publication commands remain outside the public helper API. Generated export
inventories are intentionally broader because they must detect global symbol
collisions.

## Contract Delta

The v0.3 contract contained 29 public functions. The v0.4 contract contains 30
public functions, three public constants and ten internal functions.

The only added public signature is:

```text
SAEF_ValidateMutableObject(int $objectID, ?int $expectedObjectType = null): void
```

A zero-context signature diff across `helpers/` confirms that no existing
public function declaration changed. All existing required parameters, optional
parameters, defaults, return types and public constants remain compatible.

## Function Audit

| Public function | v0.4 disposition |
| --- | --- |
| `SAEF_ValidateParentObject()` | Signature and parent-validation contract unchanged. |
| `SAEF_ValidateMutableObject()` | Additive fail-closed guard; rejects ObjectID `0`, missing objects and optional type drift before mutation. |
| `SAEF_ValidateIdent()` | Unchanged. |
| `SAEF_ValidateVariableType()` | Unchanged. |
| `SAEF_ValidateObjectName()` | Unchanged. |
| `SAEF_ValidateModuleGuid()` | Unchanged. |
| `SAEF_ValidateScriptType()` | Unchanged. |
| `SAEF_EnsureCategory()` | Signature unchanged; created and reused targets now pass mutable-object and type validation before writes. |
| `SAEF_EnsureVariable()` | Signature unchanged; created and reused targets now pass mutable-object and type validation before writes. |
| `SAEF_EnsureCyclicScriptEvent()` | Signature unchanged; event targets now pass mutable-object and event-type validation before writes. |
| `SAEF_EnsureTriggeredScriptEvent()` | Signature unchanged; event targets now pass mutable-object and event-type validation before writes. |
| `SAEF_EnsureScript()` | Signature unchanged; created and reused targets now pass mutable-object and type validation before writes. |
| `SAEF_EnsureDummy()` | Unchanged; continues to compose `SAEF_EnsureInstance()` and therefore receives its target validation. |
| `SAEF_EnsureLink()` | Signature unchanged; created and reused targets now pass mutable-object and type validation before writes. |
| `SAEF_EnsureInstance()` | Signature unchanged; created and reused targets now pass mutable-object and type validation before writes. |
| `SAEF_EnsureProfile()` | Unchanged. |
| `SAEF_WaitForVariable()` | Signature and bounded feedback contract unchanged. |
| `SAEF_NormalizeConfigurationForHash()` | Unchanged. |
| `SAEF_CreateConfigurationHash()` | Unchanged. |
| `SAEF_EnsureRegistryVariable()` | Unchanged. |
| `SAEF_ReadRegistry()` | Unchanged. |
| `SAEF_WriteRegistry()` | Unchanged. |
| `SAEF_UpdateRegistryEntry()` | Unchanged. |
| `SAEF_EnsureStatisticsVariables()` | Unchanged. |
| `SAEF_IncrementStatistic()` | Signature unchanged; read-modify-write is now serialized per variable and fails clearly after bounded semaphore contention. |
| `SAEF_SetStatisticTimestamp()` | Unchanged. |
| `SAEF_EnsureErrorRingBufferVariable()` | Unchanged. |
| `SAEF_ReadErrorRingBuffer()` | Unchanged. |
| `SAEF_AppendErrorRingBufferEntry()` | Unchanged. |
| `SAEF_ClearErrorRingBuffer()` | Unchanged. |

The public constants remain unchanged:

- `SAEF_WAIT_CHANGED = 1`;
- `SAEF_WAIT_UPDATED = 2`; and
- `SAEF_ERROR_RING_BUFFER_MAX_CAPACITY = 100`.

## Behavioral Compatibility

The object-mutation changes reject invalid create results and ownership/type
drift before the first mutator. They do not change successful object identity,
presentation policy or idempotent reconciliation. Failing where an unsafe root,
missing object or wrong object type would previously have reached a mutator is
a safety correction.

Statistics increments retain their numeric validation and return contract. A
concurrent update may now fail with a bounded busy exception instead of losing
an increment through an unprotected read-modify-write race. The semaphore is
scoped to the statistic variable, so unrelated counters remain independent.

## Export and Consumer Audit

`SAEF_ValidateMutableObject()` is exported by:

- the EnsureVariable helper bundle;
- the MQTT Discovery Exporter fileset;
- the ControlLight fileset; and
- the Open-Meteo module source closure.

The serialized `SAEF_IncrementStatistic()` implementation is exported by the
MQTT Discovery Exporter and ControlLight filesets. Module-publication tooling,
case-study classes and deployment operations do not create additional public
helper APIs.

The generated artifacts remain derived from canonical helper sources. Their
guards prevent redeclaration but do not select an effective live owner; any
future live activation still requires the separate shared-owner gate defined
by `project/WORKSTREAM_COORDINATION.md`.

## Verification

- `tests/helpers/public-api.php` confirms 30 public functions, three public
  constants and ten internal functions.
- `tests/helpers/object-mutation-safety.php` confines production object creation
  to guarded Ensure helpers.
- Ensure presentation and event tests cover successful reuse and incompatible
  target rejection.
- Diagnostics tests cover serialized Statistics success, contention and
  semaphore release.
- Bundle and fileset checks validate the complete generated export inventories.

The additive function and the two behavioral hardenings are appropriate for the
minor `v0.4.0` release and require no compatibility shim.
