# CL-026 Activation and Regression Report

**Gate:** Two-run v2 activation with reported synchronization and Auto-Off
regression
**Result:** PASS — NO DEVICE COMMAND, ROLLBACK NOT REQUIRED
**Date:** 2026-07-20
**Live state:** Seven v2 wrappers active; 22 legacy wrappers remain

## Immediate Delta Gate

The private package verifier and a fresh read-only delta preflight passed
immediately before mutation. The live wrapper still matched the exact rollback
image, Auto-Off retained its tested source, all 29 wrapper identities matched,
the alarm was inactive and the Auto-Off timer was inactive.

The local/target baseline remained:

- STATE false / false;
- DIMMER 0 / 28; and
- color temperature 2600 K / 2604 K.

All local variable metadata, target actions, archive settings, three
presentation links and three legacy feedback events were captured before the
source transition.

## Activation

Only the `CL-026` wrapper source was replaced. Direct read-back matched the
private candidate byte-for-byte and the script was not broken before its first
execution.

The first configuration run:

- reused all three local variables and all three existing feedback-event IDs;
- created exactly the ten expected diagnostics variables;
- synchronized local DIMMER to 28 and color temperature to 2604 K;
- kept local and target STATE false;
- issued zero ControlLight device commands; and
- completed with one execution, one success, zero errors and zero confirmation
  timeouts.

The DIMMER synchronization triggered the existing Auto-Off DIMMER event. The
modernized consumer observed STATE false, did not arm its timer and left its
runtime suppression/follow-up state empty. This is direct integration evidence
for the version-independent downstream contract during the real migration
path.

The second configuration run reused the identical topology and added only the
expected execution and success counter increments. Commands, errors and
confirmation timeouts remained zero.

## Event-Verifier Correction

The first postflight assertion stopped on an apparent event-contract mismatch.
Read-only detail inspection proved that no runtime defect existed: ControlLight
v2 intentionally changes the STATE feedback event from OnChange to OnUpdate so
same-value authoritative feedback can be processed. DIMMER and color
temperature correctly remain OnChange.

The verifier had incorrectly required the legacy OnChange type for all three
events. After correcting only that expectation, the complete postflight passed.
No rollback or live correction was necessary.

## Final Regression

The corrected postflight proved:

- exact v2 candidate source and `ScriptIsBroken = false`;
- two executions, two successes, zero commands, zero errors and zero timeouts;
- empty ErrorRingBuffer and a Registry recording `reported` semantics;
- authoritative equality for STATE, DIMMER and color temperature;
- preserved local names, positions, profiles, actions and archive settings;
- preserved three presentation links and three existing event IDs;
- explicit Run Automation action binding on every feedback event;
- unchanged Auto-Off source, inactive timer and empty bounded runtime state;
- exactly one script consumer for the local variables; and
- all 29 wrapper sources matching the new seven-v2/22-legacy baseline.

## Gate Decision

The `CL-026` v2 activation is **PASS**. The exact rollback remains available but
was not used. A complete STATE/DIMMER/color-temperature real-device sequence is
the next separate gate and requires explicit approval because it will visibly
operate the light.
