# SAEF Step 382: Zone Mowing Recency Design and Pilot Attempt

## Status

Historical design and first pilot attempt from 2026-08-29, recovered and
canonicalized after the later workstream had already advanced. The first
bounded receive-only activation attempt was cleaned up automatically after an
overly strict synchronous postcondition.

The operating statements below are point-in-time evidence, not the current
live status. Steps 383 to 393 later corrected the position-session contract,
completed the receive-only validation and introduced the reviewed continuous
operating model. Steps 394 to 399 subsequently developed the bounded mowing
analytics and map projection.

## Objective

Detect when a complete accepted mowing zone has not been fully mowed for a
configurable period such as seven or fourteen days. Keep the design extensible
to smaller spatial areas without creating an unbounded number of IP-Symcon
variables.

## Existing Evidence Boundary

The existing variables describe the retained MQTT task projection:

- pass progress;
- observed area;
- last task observation;
- evidence quality.

`LastObservedAt` is not a mowing-recency timestamp. Task telemetry may also be
observed during a delay, pause or return. It must not be used as proof that a
zone was mowed.

The current task ledger does contain a stronger event:
`completionObservedAt`. It is recorded only when task progress reaches the
bounded completion threshold. This is the initial authority for complete-zone
recency, still explicitly classified as MQTT inference.

Historical cloud data is not available. The first trustworthy completion time
can therefore only be learned prospectively after receive-only observation has
started.

## Recommended Variable Contract

Variables are the primary operational contract because they support Archive
logging, alerts, rules and dashboards independently of the map renderer.

For each accepted and bound manufacturer zone:

| Ident suffix | Type | Meaning |
| --- | --- | --- |
| `LastCompletedAt` | Integer timestamp | Last observed complete task pass |
| `CompletionAgeDays` | Float | Current age of that completion evidence |
| `MowingRecencyState` | Integer profile | Unknown, Fresh, Watch or Overdue |

The existing stable zone prefix remains based on the manufacturer zone ID.
No variables are created for an unbound zone. Variables are retained when the
feature is disabled, and the module does not change Archive logging settings.

Suggested defaults:

- `WatchAfterDays = 7`;
- `OverdueAfterDays = 14`;
- `OverdueAfterDays` must be greater than `WatchAfterDays`.

An unknown state is distinct from overdue. Missing historical evidence must
never be presented as proof that a zone was neglected.

## Persistence Contract

The bounded task ledger retains at most 32 passes and is not a sufficient
multi-week authority on its own. A compact revision-bound recency store should
persist only the latest completion timestamp per accepted zone.

The store must:

- be keyed by the accepted geometry revision and manufacturer zone binding;
- support at most the accepted bounded zone count;
- update timestamps monotonically;
- reject future, malformed or mismatched evidence;
- reset spatial truth to unknown after an incompatible geometry revision;
- remain independent of path-retention and display-retention limits.

## Map Projection

The map is secondary to the variables. It should project the recency state
without replacing existing zone colors:

- Fresh: no additional warning decoration;
- Watch: thin yellow zone border and compact age label;
- Overdue: thin red zone border and compact age label;
- Unknown: teal or neutral dashed border, without an alarm claim.

This keeps the current zone identity readable and avoids confusing mowing age
with mower state or station state.

## Smaller Areas

Smaller-area recency should later use a bounded spatial raster inside each
accepted zone. Each cell stores one last-mowed timestamp and is rendered as a
heatmap overlay. Cells must remain internal structured state rather than
individual IP-Symcon variables.

A cell may be marked mowed only from position points that satisfy all of these
conditions:

- current accepted geometry revision;
- unambiguous zone attribution;
- mowing vehicle-state code rather than Docking, Paused or Offline;
- bounded movement plausibility;
- calibrated or explicitly conservative cutter-width coverage.

The local coordinate scale and effective cutter-width buffer are not yet
calibrated. Cell-level coverage therefore remains a later evidence gate and is
not part of the first zone-level implementation.

## Pilot Attempt

A private read-only preflight passed the revision, lifecycle, REST-readiness,
token-horizon, disabled-transport, credential-absence and variable-contract
checks defined for this attempt. Exact installation metadata and identifiers
remain in private evidence.

The previous five-minute pilot limit was intentionally changed to the bounded
72-hour maximum during the single activation attempt. The activation gate then
required active registry state and credentials synchronously. The module is
allowed to enter `ReconnectScheduled` before those values converge, so the
postcondition failed closed.

The private procedure performed its mandatory cleanup immediately. A separate
read-only postflight passed the disabled-transport, inactive-core,
credential-absence, closed-pilot, REST-readiness and unchanged-variable
contracts. Exact live values remain in private evidence.

No second activation, OAuth action, restart or mower command occurred.

## Architecture Decisions

### AD-NAV-382-01: Variables are authoritative; the map is a projection

**Decision:** Expose bounded per-zone recency variables and derive map warning
decoration from them.

**Reason:** Variables are stable, archivable and actionable. SVG presentation
must not become the only source for automation decisions.

### AD-NAV-382-02: Complete pass and recent activity are separate semantics

**Decision:** Base whole-zone recency on observed completion, not merely on a
position point or task observation.

**Reason:** A zone can be entered or partly mowed without being completed,
especially after rain, battery return or schedule-window expiry.

### AD-NAV-382-03: Spatial detail uses a bounded internal raster

**Decision:** Represent future sub-zone recency as structured raster state and
render it as a map heatmap, not as one Symcon variable per cell.

**Reason:** Cell variables would create unbounded object growth and make map
revision changes unsafe.

## Historical Next Gates

1. Obtain fresh authorization for exactly one receive-only retry with the
   corrected, syntax-checked asynchronous activation gate.
2. Collect prospective completion evidence during the bounded pilot.
3. Implement and fixture-test the compact zone-recency store and stable
   variables before any publication or live update.

These gates describe the state at the time of the first attempt. Their later
execution and resulting architecture are documented in steps 383 to 399.
