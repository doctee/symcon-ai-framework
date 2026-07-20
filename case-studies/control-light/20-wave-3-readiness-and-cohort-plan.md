# Wave 3 Readiness and Cohort Plan

**Gate:** Remaining dependency-free cohort inventory
**Result:** PASS WITH EXCLUSIONS
**Date:** 2026-07-19
**Live state:** Read-only; unchanged

## Scope

After the pilot and CL-023 passed, the six dependency-free contracts from the
original risk inventory were re-read against the live installation. The probe
verified wrapper identities, local and target values, action availability,
event action binding, object counts and presentation fingerprints. It also
searched installed script sources, exact event trigger variables and link
targets for references to their local ControlLight variables.

No wrapper, object, event, variable or device was changed.

## Cohort Result

All six wrappers retained their expected legacy source identities. Every
configured target was actionable and every owned target event remained active
with explicit Run Automation action binding. Five contracts had exact local
and target feedback equality.

The candidates separate into these treatments:

| Contract group | Treatment |
| --- | --- |
| Two-capability, dependency-free, feedback aligned | Select one for Wave 3 |
| STATE-only, dependency-free, feedback aligned | Select as second sequential member |
| Color-temperature or color expansion, feedback aligned | Defer until the two-member Wave 3 proves sequential rollout |
| Two-capability feedback mismatch | Exclude pending non-commanding sync analysis |

One two-capability contract currently reports STATE false with local DIMMER
zero but retained target DIMMER non-zero. It is excluded because selecting
`reported` would visibly synchronize that local value even without a device
command. That presentation/state change needs its own explicit gate.

An initial broad serialized-event string search produced apparent references
for two candidates. Exact semantic readback showed that none of those events
triggered on the candidate local variables; the numeric strings occurred in
unrelated event metadata. No installed script source, exact event trigger or
link target references a selected Wave 3 local variable.

## Selected Sequential Members

Wave 3 consists of two dependency-free Z2M contracts, activated sequentially
with a stop gate between them:

1. **CL-025** — STATE and DIMMER, both false/zero locally and at the target;
2. **CL-027** — STATE-only, false locally and at the target.

Both use the agreed default `reported` semantics. For CL-027 this setting is a
required explicit runtime contract but has no active brightness capability.
Neither candidate has script, event-trigger or presentation-link consumers of
its local variables.

CL-025 repeats the proven two-capability topology under a clean dependency
boundary. CL-027 then extends evidence to a capability subset without combining
that change with color scaling or external triggers.

## Package and Activation Gate

The next non-live step may build a private, hash-locked package containing:

- byte-exact wrapper backups for both candidates;
- separate versioned v2 wrappers using `reported` semantics;
- the already active ControlLight fileset and corrected shared helper as pinned
  dependencies;
- per-candidate object, event, action and presentation allowlists;
- a sequential activation coordinator that never advances after a failed
  member; and
- rollback and regression contracts for the pilot, CL-023 and all remaining
  legacy wrappers.

Each member must run two non-commanding synchronizations and prove zero command,
error and timeout deltas, exact object reuse and stable user presentation. The
second member may start only after the first member's complete postflight
passes. Any real functional device sequence remains a later, separately
approved gate.

## Gate Decision

Wave 3 readiness is **PASS WITH EXCLUSIONS** for CL-025 followed by CL-027.
Package construction is non-live. Staging, wrapper replacement and script
execution are not authorized by this report.
