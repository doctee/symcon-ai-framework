# Wave 2 Timeout Experiment and Wait-Race Analysis

**Gate:** Five-second confirmation experiment and repeated functional test
**Result:** BLOCKED — helper defect identified
**Date:** 2026-07-19
**Live state:** Five-second candidate rolled back; safe v2 baseline restored

## Controlled Configuration Experiment

The approved candidate changed only:

- runtime version metadata; and
- confirmation timeout from three to five seconds.

Polling remained 100 ms. Authoritative feedback, `reported` brightness,
capabilities and all other configuration stayed identical. The active v2 source
was preserved as the exact rollback artifact.

A fresh preflight checked all 29 wrappers, the shared core, active fileset,
topology, consumers, feedback and the existing diagnostic history. Two
non-commanding synchronizations activated the five-second candidate without
changing commands, errors, timeouts or error history.

## Repeated Failure

The repeated test again stopped on STATE-on. The correct target value appeared,
but the runtime recorded another confirmation timeout. Compensation restored
STATE false and DIMMER 100. The compensating STATE-off action also reached the
correct value but recorded a timeout.

No DIMMER test action was executed. Historical diagnostics were preserved.

## Root Cause

`SAEF_WaitForVariable()` captures the second-resolution
`VariableUpdated` timestamp and, after each poll, evaluates the expected value
only when:

```text
current VariableUpdated > start VariableUpdated
```

If asynchronous feedback arrives after the initial check but within the same
IP-Symcon second, the value changes while both timestamps remain equal. The
helper then never evaluates the now-correct value and eventually returns false.

This explains both observations:

- the target reached the requested value;
- increasing only the timeout did not help.

Archive logging was disabled, but the retained variable and diagnostic
timestamps plus the successful compensation make the same-second race
observable. The defect is in confirmation detection, not DIMMER semantics,
RequestAction routing or target action availability.

## Rollback

Because the five-second candidate did not resolve the defect, only that
configuration change was rolled back. The previous active v2 wrapper identity
and Registry version were restored through a non-commanding synchronization.

Final state:

- STATE false locally and at the target;
- DIMMER 100 locally and at the target;
- no rollback device command;
- all diagnostic evidence retained;
- shared core, events and wrapper topology unchanged.

## Required Next Gate

Further device testing and additional instance migration remain blocked.

The next engineering task should correct the existing helper without adding a
one-off ControlLight polling loop. A regression test must model a predicate
transition from false to true while `VariableUpdated` remains in the same
second. The fix must preserve update/change semantics for callers without a
value predicate and pass the complete helper, ControlLight and fileset suites.

Repository implementation, fileset publication and live selection are separate
gates. No helper or fileset change is authorized by this report.
