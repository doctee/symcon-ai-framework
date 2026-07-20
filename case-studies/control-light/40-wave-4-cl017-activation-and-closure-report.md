# Wave 4 CL-017 Activation and Closure Report

**Gate:** Second sequential Wave 4 member
**Result:** PASS — WAVE 4 COMPLETE
**Date:** 2026-07-20
**Live impact:** One wrapper source selected; two non-commanding configuration runs

## Activation boundary

Immediately before mutation, the transaction revalidated the private package,
fresh delta evidence, exact CL-017 rollback source and current CL-004 v2 source.
Only the CL-017 wrapper was changed. Direct readback matched the complete
candidate source byte-for-byte.

Both configuration passes used the direct synchronous script-execution channel
established by the corrected CL-004 activation. Each run completed before the
next operation began. No coordinator-side asynchronous script call was used.

## Postflight

The final postflight passed with:

- two executions and two successes;
- zero commands, errors and confirmation timeouts;
- STATE false locally and at the target;
- retained DIMMER 100 locally and at the target;
- color temperature 2702 locally and at the target;
- all ten v2 diagnostics present with valid ownership and types;
- Registry version, configuration hash and `reported` semantics valid;
- local names, positions, icons, visibility, profiles and custom actions
  preserved;
- target link and all three target events preserved with explicit Run
  Automation action binding;
- exactly the baseline wrapper children plus the ten identified diagnostics;
- all 29 wrapper sources matching the new six-v2/23-legacy baseline; and
- CL-004 still healthy with equal execution/success counters and zero commands,
  errors, timeouts and feedback-command timestamp.

## Link acceptance

Each CL-017 local variable retained exactly one presentation link. For all three
links, object ID, parent, Ident, name, position, icon, visibility, object type
and target variable ID matched the fresh private before-snapshot exactly. No
script or event consumer appeared during activation.

## Wave decision

Wave 4 is complete. CL-004 and CL-017 are active on the current v2 fileset with
authoritative feedback and explicit `reported` brightness semantics. Their
configuration activations issued no device command.

Neither member has yet completed its bounded real-device capability sequence.
Functional tests for STATE, DIMMER and color temperature remain separate,
individually approved gates with compensation to each lamp's exact starting
state. No further legacy wrapper is selected automatically.
