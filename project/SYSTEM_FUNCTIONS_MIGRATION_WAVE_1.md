# System Functions Migration Wave 1

**Release-review status:** 2026-07-20; second migration immediate verification
passed, scheduled observation remains pending. No live probe was performed by
this documentation review.

## Outcome

The first migration preparation wave identified direct callers without storing
their script names, object IDs, argument values or source. A later separately
authorized pilot migrated one four-argument call after runtime bundle
deployment. Following its completed operational observation, exactly one
additional four-argument call was migrated. One legacy call remains unchanged
as the control.

The scan used PHP tokens rather than text matching. It excludes the function
library itself, function definitions, comments, strings, object methods, static
methods and longer names such as `SAEF_WaitForVariable`. Autoload availability
therefore has no effect on the counts.

## Direct-call shapes

`Arity` means the number of supplied arguments, not the function's declared
parameter count. The distribution is useful for forming migration cohorts but
does not establish semantic compatibility.

| Function | Direct callers | Calls | Arity distribution | Wave-1 disposition |
|---|---:|---:|---|---|
| `CreateVariableByName` | 12 | 111 | 3: 84; 4: 8; 5: 6; 6: 12; 7: 1 | Prepare migration to `SAEF_EnsureVariable`; start with the 84 minimal-shape calls. |
| `GetEventByName` | 36 | 36 | 2: 36 | Prepare replacement by stable Ident plus type and ownership validation. |
| `UpdateDeviceWarningSummary` | 17 | 17 | 1: 17 | Keep private; analyze as one domain convention, not 17 helper use cases. |
| `CreateProfileInteger` | 2 | 14 | 8: 2; 9: 12 | Map profile semantics to `SAEF_EnsureProfile` before migration. |
| `SetHiddenStates` | 3 | 5 | 1: 5 | Keep private or inline in the owning configuration script. |
| `CreateVariableByIdent` | 2 | 4 | 4: 3; 7: 1 | Use the three four-argument calls as the lowest-risk helper migration pilot. |
| `RegisterArchive` | 1 | 3 | 2: 3 | Document ownership and aggregation requirements; no helper yet. |
| `WaitForBoolValue` | 3 | 3 | 3: 2; 4: 1 | Compare timeout, interval and update/change semantics with `SAEF_WaitForVariable`. |
| `CreateCategoryByName` | 1 | 2 | 2: 2 | Replace with `SAEF_EnsureCategory` after assigning an explicit stable Ident. |
| `CreateEventByName` | 1 | 1 | 3: 1 | Hold for the event ownership knowledge contract. |
| `CreateEventByNameFromTo` | 1 | 1 | 6: 1 | Hold for schedule-boundary and overnight semantics. |
| `CreateTimerByName` | 1 | 1 | 4: 1 | Compare with `SAEF_EnsureCyclicScriptEvent`; do not create a parallel helper. |
| `CreateProfile` | 1 | 1 | 2: 1 | Replace with `SAEF_EnsureProfile`. |
| `ExtractGuid` | 1 | 1 | 1: 1 | Keep local unless a second independent use case appears. |
| `WaitForVariable` | 0 | 0 | none | Audit indirect invocation, then discard the duplicate if none is found. |

All other inspected functions have no direct caller in the connected runtime.

## Correction to the initial text inventory

The earlier text-based scan was intentionally conservative but produced false
positives. The token scan corrected these notable cases:

- the local `WaitForVariable` was matched inside the longer
  `SAEF_WaitForVariable` name and has no direct caller;
- definitions, comments or non-call text inflated several creation counts;
- event lookup and warning-summary counts were confirmed unchanged.

The token-based figures are now authoritative for static direct calls. Dynamic
callbacks, string-based invocation and calls outside the connected runtime
remain an explicit blind spot.

## Migration gates

### Pilot: Ident-based variable creation

The safest first code migration is the cohort of three four-argument
`CreateVariableByIdent` calls. Before changing any caller, the private review
must confirm:

- the supplied Ident is stable and unique beneath the intended parent;
- existing objects have the expected variable type;
- profile ownership and compatibility are explicit;
- controllable variables have an explicit action owner;
- a second configuration run produces no structural change.

This pilot reuses `SAEF_EnsureVariable` and introduces no new API.

#### Static pilot-gate result

The three four-argument calls were checked inside Symcon. Only aggregate gate
results were returned:

| Gate | Result | Consequence |
|---|---|---|
| Parent argument | REVIEW — all three variables originate from parent-navigation calls | The topology is explicit, but existence and SAEF ownership are not yet proven. |
| Stable Ident | PASS — all three constant references resolve locally to unique string literals accepted by the SAEF Ident syntax | Preserve the explicit constants during migration; do not reintroduce caption normalization. |
| Object name | PASS — three non-empty string literals | No migration blocker found in the static shape. |
| Variable type | PASS — three valid integer literals | All are within the supported Symcon variable types. |
| Profile and action | PASS for scope — neither is supplied by these four-argument calls | No optional profile/action behavior needs mapping for this cohort. |
| Existing object compatibility | PASS — all three targets exist as variables with the expected type | No recreation or type conversion is required. |
| Second-run idempotency | PASS for first migrated call | Target identity, value, metadata and object structure were unchanged after both runs. |

The original static-shape review left the Ident and target compatibility gates
open. The subsequent provenance and read-only runtime checks below close those
gates: no Ident is derived from an object caption or legacy normalization, and
all three existing targets are compatible. At that gate, a live change still
required an explicit deployment path, ownership statement and authorization.

#### In-runtime validation result

An isolated, read-only Symcon probe attempted to resolve the three constant
expressions and parent relationships without executing the caller scripts.

| Check | Result | Interpretation |
|---|---|---|
| Supported constant expression shape | PASS — 3 of 3 | All expressions can be treated as constant references rather than arbitrary executable expressions. |
| Constant defined in isolated probe context | OPEN — 0 of 3 | The constants are caller-local or become available through a caller-specific load path. Their values were not guessed or evaluated indirectly. |
| Parent object resolvable | PASS — 3 of 3 | The restricted parent-navigation expressions resolve to existing objects. |
| Parent in caller ancestor path | PASS — 3 of 3 | The resolved parent is structurally associated with the caller. Explicit configuration ownership still needs to be stated. |
| Ident syntax and target uniqueness | NOT TESTED | Requires the caller-local constant definitions. |
| Existing target compatibility | NOT TESTED | No lookup was attempted without a validated Ident value. |

This result is deliberately fail-closed. A constant that is unavailable in the
isolated probe context is not replaced with a caption, normalized guess or
private value copied into the probe.

#### Static caller-local provenance result

A separately authorized temporary test script traced constant definitions
without executing the caller scripts. The scan included the three four-argument
pilot calls and, usefully, the one seven-argument call:

| Check | Result |
|---|---|
| Constant references inspected | 4 |
| Caller-local definitions found | 4 |
| Unique string-literal definitions | 4 |
| Values accepted by the SAEF Ident syntax | 4 |
| Conflicting definitions | 0 |

The Ident provenance gate is therefore closed for all four
`CreateVariableByIdent` calls. The seven-argument call remains outside the
minimal pilot because its optional position, icon or profile semantics still
need mapping.

#### Read-only target compatibility result

The three four-argument pilot calls were resolved using only their validated
caller-local constants and the restricted parent-navigation evaluator:

| Check | Result |
|---|---|
| Pilot calls resolved | 3 of 3 |
| Distinct parent-and-Ident targets | 3 |
| Existing compatible variables | 3 |
| Missing targets | 0 |
| Existing variables with conflicting type | 0 |
| Existing non-variable objects occupying the Ident | 0 |

At this gate, the pilot was statically migration-ready. That result did not
authorize a live change: the replacement still needed an explicit deployment
path for `SAEF_EnsureVariable`, a stated owner for each configuration script and
a post-change two-run idempotency check.

### Main cohort: name-based variable creation

The 84 three-argument calls form the largest homogeneous cohort, but they are
not automatically safe to migrate. Each needs a stable Ident decision. The
remaining 27 calls supply optional behavior and require a parameter-by-parameter
contract comparison before grouping.

### Event lookup and scheduling

The 36 two-argument `GetEventByName` calls demonstrate migration debt around
caption-based identity. Migration must be coordinated with event creation so
that lookup does not switch to an Ident before the owning configuration creates
and reconciles that Ident.

No scheduling helper is added in this wave. The three event-creation shapes
first feed the proposed event scheduling and ownership knowledge article.

### Local wait functions

The unused local `WaitForVariable` is a retirement candidate, subject to a
dynamic-call audit. `WaitForBoolValue` remains a small migration cohort. A
replacement is valid only if boolean equality, timeout, polling interval and
change-versus-update behavior remain equivalent for each caller.

### Warning summary

Seventeen one-argument calls show repeated adoption of one private domain
convention. They do not demonstrate a general-purpose helper contract. The
function stays private; a later case study may describe the domain and design
trade-offs without implementation code or installation data.

## Pilot completion and next action

The deployment-readiness review, generated bundle, offline verification,
runtime activation and first live caller migration are complete. The first
pilot call passed:

- exact single-call source replacement;
- unchanged two-call legacy control group;
- target identity and value preservation;
- unchanged metadata, archive/link state and object structure;
- two successful caller executions after real-device safety preflight;
- complete cleanup of temporary verification objects.

Expressions, values, script names and object IDs must not enter public SAEF
artifacts.

The review is recorded in
[`SYSTEM_FUNCTIONS_PILOT_DEPLOYMENT_PLAN.md`](SYSTEM_FUNCTIONS_PILOT_DEPLOYMENT_PLAN.md).
The operational observation exceeded 48 hours and passed with stable event,
source, target, object-tree, archive/link and domain-state invariants. Direct
MCP source read-back and bounded structured probes completed the final check
without temporary live objects.

The second migration passed its immediate direct read-back and structural
verification. The live caller now contains two SAEF calls and one unchanged
legacy control call. Target identity, value, metadata, archive/link state,
object structure, event schedule and domain state were preserved without a
manual caller execution, temporary live object or device action.

The next action is read-only observation of the next regular scheduled
execution. The final legacy call must remain unchanged until that gate passes.
No helper or API change is needed.
