# Positive Dim Command Live Closure

**Date:** 2026-09-21

**Scope:** All 24 dimmable callers in the inventoried ControlLight v2 cohort

**Result:** PASSED; representative spoken Alexa acceptance passed on CL-021

## Command Contract

An explicit positive brightness command means power-on plus the requested
brightness, even when retained brightness already equals the request while off.
The runtime holds one per-caller semaphore and uses one shared confirmation
deadline. Already confirmed actions are skipped. Group commands are sent once
to the endpoint and require confirmation from every configured member, not
merely the passive any-member-on projection.

Passive reported brightness remains independent of STATE and never powers on
a device. Zero brightness retains its off-command contract. Alarm guards and
manual-on/off-only protection remain effective. Existing SAEF wait helpers are
reused; no shared helper or protocol conversion was changed.

## Deployment and Verification

A separately authorized immutable fileset was staged and preflighted before
the 24 dimmable callers selected it. Exact source readback and command-free
reconciliation passed. The two state-only callers, CL-014 and CL-030, were not
changed. The global bootstrap, service and target modules were not updated or
restarted. The managed inert source mirror was refreshed with presentation
preserved. Previous immutable filesets and exact rollback sources remain retained.

Every selected caller passed:

- off to 100 percent with authoritative power and brightness confirmation;
- repeated 100 percent without additional device commands;
- off to 40 percent using existing protocol scaling and feedback tolerance; and
- restoration of its actual pre-test state and exact reported brightness,
  with temperature and color unchanged.

This includes three member-confirmed groups and two Matter targets. No new
runtime error or confirmation timeout was recorded. Existing topology was
preserved. One target module lazily created its own configuration variable;
that addition was classified and retained, not attributed to ControlLight.

An initial restoration check exposed existing Z2M bidirectional truncation:
requesting the original level returned one percentage point less. Testing
paused for source diagnosis. A bounded inverse correction restored the exact
original level; subsequent private restoration probes accounted for this
known rounding. Original failure evidence is retained. This did not change
production conversion or relax acceptance of final restoration.

The sanitized installed-contract fixture records the exact 24-caller coverage
and exclusions. Executable assertions prevent confusing this selected cohort
with all installation lights. Runtime regressions cover retained-level equality,
idempotency, missing and drifting power feedback, shared deadlines, groups,
Matter scaling, passive feedback and off-only protection.

## Spoken Consumer Acceptance

For CL-021, the user first switched off through Alexa. Independent readback
confirmed off with retained brightness. A subsequent spoken 100-percent command,
without a separate on command, produced on at 100 percent. The user confirmed
the physical result; device and facade agreed, with two device commands and no
new runtime error or timeout. The exact initial state, brightness and temperature
were restored.

This is representative real speech acceptance, not a spoken test of every caller.
The inspected consumer power/brightness bindings remained directed at their
ControlLight facades.

## Separate Remaining Work

CL-018, CL-019 and CL-029 remain legacy contracts outside this rollout. Color
migration, a stale disabled-color consumer binding on CL-021, and Z2M module
maintenance retain separate scopes. No cleanup or retention deletion is part
of this closure. Historical full-capability and migration reports remain valid
within their original boundaries.
