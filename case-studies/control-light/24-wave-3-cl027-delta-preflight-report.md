# Wave 3 CL-027 Delta Preflight Report

**Gate:** Second sequential member, fresh read-only delta
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Inspected only; unchanged

## Mixed-Baseline Result

The fresh delta preflight verified the installation after the first Wave 3
member had been activated. Transport and PHP execution succeeded, returned
output was complete, and the probe contained no mutation operation.

All 29 wrapper sources matched the expected mixed baseline: the pilot, CL-023
and the first Wave 3 member remained on their exact v2 sources, while the second
member retained its exact legacy source. The corrected process-effective wait
helper also retained its pinned identity.

## First-Member Health

The first Wave 3 member remained synchronized and healthy. Its execution and
success counters had both advanced from two to three, consistent with a passive
OnUpdate feedback run after activation. Commands, errors and confirmation
timeouts all remained zero. STATE and DIMMER continued to match authoritative
target feedback.

This counter drift is expected runtime activity and demonstrates why delta
gates compare diagnostic invariants and relations instead of assuming idle
absolute counters.

## Second-Member Readiness

The STATE-only second member retained:

- exact wrapper, parent, child and target-link identities;
- its existing user name, position, icon, visibility and profile;
- the correct local custom action and actionable target;
- false/false local and authoritative target feedback;
- an active legacy OnChange target event with explicit Run Automation action;
- no script, exact event-trigger or presentation-link consumer of its local
  STATE; and
- no pre-existing v2 diagnostic Ident.

## Gate Decision

The CL-027 delta preflight is **PASS**. It did not authorize or perform an
activation.

The next gate may activate CL-027 alone, execute two non-commanding
configuration passes, reconcile its STATE event to OnUpdate and perform the
same source, ownership, presentation, diagnostics, feedback and complete
wrapper regression checks used for the first member. Any failed assertion must
restore its exact legacy source and stop without a device action.
