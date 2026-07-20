# Auto-Off Modernization and CL-026 Contract Verification

**Gate:** SAEF modernization of all installed Auto-Off scripts and complete
functional verification of the ControlLight-dependent contract
**Result:** PASS — INITIAL STATE RESTORED
**Date:** 2026-07-20
**Live state:** Four modernized Auto-Off scripts active; `CL-026` remains legacy

## Scope Correction

Direct live reconciliation established that the Auto-Off-dependent hallway
light is sanitized instance `CL-026`, not the separate active v2 hallway pilot.
The current `CL-026` wrapper intentionally remains on the shared legacy
ControlLight implementation. It therefore has neither the v2
`brightnessSemantics` configuration nor authoritative-feedback and structured
runtime diagnostics supplied by ControlLight v2.

This is a known migration boundary, not drift in an active v2 wrapper. All six
active v2 wrappers retained their expected sources and contracts. The wording
in report 07 and this case-study index was corrected to remove the ambiguous
pilot attribution.

## Auto-Off Modernization

All four installed Auto-Off scripts were modernized under
`OPTIMIZE_CONTROL_SCRIPT.md`. The verified common behavior includes:

- device actions through `RequestAction()`;
- explicit configuration and type validation;
- safe refusal to switch off when motion inputs are missing, invalid or active;
- bounded shutdown confirmation, command retries and follow-up cycles;
- per-script serialization for participating executions;
- idempotent managed-event reconciliation with explicit Run Automation action
  binding;
- preservation of existing object identities, links, archive configuration,
  actions and configured timeout durations; and
- migration and reuse of the existing script-owned runtime state.

Every installed source was read back directly after deployment and was not
broken. The repository-wide SAEF check passed. Deployment and structural
verification issued no device command.

## Version-Independent CL-026 Dependency

The modernized consumer does not depend on ControlLight v2 internals. Its
upstream contract is deliberately limited to observable variable roles:

| Role | Contract |
| --- | --- |
| STATE / control | Authoritative on/off truth, timer trigger when true, only shutdown action target and shutdown confirmation source |
| DIMMER / activity | Extends the timer only when STATE is true and the reported value is positive |
| Motion | Arms the timer on activity; active or unavailable motion prevents shutdown |
| Upstream diagnostics | Not required by Auto-Off |

Consequently, a retained non-zero device brightness while STATE is false is
valid and must not be interpreted as an active light.

## Live Functional Verification

The `CL-026` Auto-Off consumer passed two identical structural reconciliation
runs and the following bounded device sequence:

1. STATE was switched on and armed the unchanged ten-minute timer.
2. DIMMER was changed while STATE was on and re-armed the timer.
3. STATE was switched off while the device retained a non-zero brightness; the
   timer remained inactive.
4. STATE was switched on again and a real short TimerEvent switched only STATE
   off.
5. Shutdown confirmation observed both local and target STATE false, the timer
   stopped, and retained target brightness remained unchanged by Auto-Off.

The device applied its known brightness grid normalization during the test.
Restoration therefore used one bounded adjacent command value to reproduce the
exact initial retained target brightness. The original local legacy DIMMER
display, STATE values and pre-existing timer deadline were then restored. The
postflight confirmed the original values, event structure, variable metadata,
archive settings and links.

## Migration Boundary

The successful Auto-Off test removes the downstream-consumer blocker but does
not migrate `CL-026`. Its later ControlLight v2 transition requires a separate
approved transaction with:

1. a fresh read-only source, topology and consumer preflight;
2. an explicit `reported` brightness decision;
3. acknowledgement that initial synchronization may change the off-state local
   DIMMER display from legacy zero to retained device brightness;
4. a hash-locked backup and bounded rollback; and
5. complete Auto-Off plus all-wrapper regression after activation.

No ControlLight source or object was changed as part of this documentation
reconciliation.
