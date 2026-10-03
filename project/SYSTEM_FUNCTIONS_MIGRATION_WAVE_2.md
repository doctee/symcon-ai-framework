# System Functions Migration Wave 2

**Operational status:** 2026-10-02; the bounded live migration and independent
postflight passed.

## Scope

Wave 2 removed seven direct dependencies on the autoloaded
`System.Functions` library from five owner scripts:

- three boolean wait calls;
- three event-creation calls covering an interval timer, a daily event and a
  bounded cyclic schedule;
- one module-GUID extraction call.

The change did not execute an owner script, publish MQTT data, invoke a device
action, restart a service, alter archive retention or remove the function
library. Exact source backups and installation-specific evidence remain under
the ignored private evidence tree.

## Scanner correction

The caller scanner now excludes a call when the same script defines that
function locally. This is necessary because PHP resolves such a call to the
local definition rather than to the autoloaded library.

The correction changed the profile assessment:

- `CreateProfile` has no external caller; its one apparent call is locally
  shadowed.
- `CreateProfileInteger` has one external caller; thirteen other apparent
  calls are locally shadowed.

The remaining external profile call was intentionally not migrated. The
active runtime fileset did not export `SAEF_EnsureProfile`, so changing that
caller would have crossed the verified runtime deployment boundary.

## Migration design

### Boolean waits

`SAEF_WaitForVariable` waits for a qualifying update or change. The legacy
boolean helper also succeeded immediately when the variable already held the
expected strict boolean value. Each migrated call therefore uses:

1. an immediate strict comparison;
2. `SAEF_WaitForVariable` only when the value does not already match;
3. the original timeout and polling interval.

This preserves behavior without adding another public wait helper.

### Event creation

The simple interval event is now reconciled by
`SAEF_EnsureCyclicScriptEvent` with an explicit stable Ident and action
binding. The owner with daily and bounded schedule requirements keeps one
owner-local reconciliation function because the existing public helper does
not express that complete contract. The local function validates positive
object identities, parent and event type before mutation and uses documented
Symcon event constants.

No public helper was extended from a single owner-specific use case.

### Module identity

The only `ExtractGuid` call selected one known platform module. It was replaced
with an owner-local named constant bound to the observed module identity. The
generic parser was not copied or republished.

## Verification

The migration passed all of these gates:

- exact pre-change source backup and source-hash binding;
- local PHP syntax checks for all five candidates;
- exact direct source readback after each update;
- stable Ident assignment for three existing event objects after positive ID,
  type, parent and collision checks;
- independent source hashes and absence of all seven legacy call tokens;
- unchanged event schedules, active state and action binding;
- no active target script during postflight;
- no transport error, execution error or output truncation in bounded MCP
  probes.

Symcon normalizes line endings and terminal newlines when storing script
source. Postflight therefore binds to the independently read live bytes, while
the private backup retains the exact predecessor bytes.

## Current direct-call inventory

After Wave 2, 174 external direct calls remain:

| Function | Scripts | Calls | Disposition |
|---|---:|---:|---|
| `CreateVariableByName` | 12 | 111 | Split into explicit migration cohorts below. |
| `GetEventByName` | 35 | 35 | Coordinate stable Ident creation and lookup migration by owner. |
| `UpdateDeviceWarningSummary` | 17 | 17 | Keep private; it is one domain convention. |
| `SetHiddenStates` | 3 | 5 | Keep private or inline under explicit ownership. |
| `RegisterArchive` | 1 | 3 | Require archive ownership and retention contract first. |
| `CreateCategoryByName` | 1 | 2 | Replace after assigning a stable Ident. |
| `CreateProfileInteger` | 1 | 1 | Wait for a verified runtime export of `SAEF_EnsureProfile`. |

All other inspected functions have no external direct caller in the connected
runtime. Static scanning cannot prove absence of dynamic string-based calls or
code outside that runtime.

## Variable migration cohorts

The 111 `CreateVariableByName` calls are concentrated rather than evenly
distributed:

- three scripts contain 92 calls;
- nine scripts contain the remaining 19 calls;
- 84 calls use only parent, caption and type;
- 27 calls supply one or more optional arguments;
- 83 calls use the owner script as parent and 28 use a variable parent;
- 106 captions are literals and five are constructed expressions;
- 98 calls omit a profile; twelve use a literal profile and one uses a
  variable profile.

This evidence supports three separate future lots:

1. **Small static owners:** nine scripts and 19 calls. Assign explicit stable
   Idents per owner and prove second-run idempotency first.
2. **Large minimal-shape owner:** one script contains 57 three-argument calls.
   Treat this as one owner migration with a generated private mapping, not 57
   independent edits.
3. **Optional-contract owners:** the two remaining large scripts contain 35
   calls, including nearly all profile, icon and position behavior. Migrate only
   after profile/action ownership and existing presentation compatibility are
   proven.

The call count alone must not determine replacement order. Stable identity,
existing type compatibility, profile ownership, action binding and idempotent
second-run behavior remain mandatory gates.

## Next action

Prepare the small-static-owner lot as a private read-only mapping first. Do not
mutate a caller until each proposed Ident, existing target type, presentation,
action owner and rollback source are bound. In parallel, add
`SAEF_EnsureProfile` to a future reviewed runtime fileset before attempting the
single remaining external profile migration.
