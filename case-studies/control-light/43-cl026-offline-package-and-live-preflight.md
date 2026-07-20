# CL-026 Offline Package and Live Preflight

**Gate:** Hash-locked package construction and fresh read-only live preflight
**Result:** PASS — READY FOR SEPARATE ACTIVATION APPROVAL
**Date:** 2026-07-20
**Live impact:** None

## Candidate and Rollback

The private package contains one complete v2 wrapper candidate for sanitized
instance `CL-026` and an exact Base64 rollback image of its current legacy
source. The candidate:

- selects the already active hash-addressed ControlLight fileset;
- enables STATE, DIMMER and color-temperature capabilities;
- explicitly configures `reported` brightness semantics and authoritative
  feedback;
- preserves the existing alarm polarity and disables only color;
- delegates all object, event, diagnostics, wait and action behavior to the
  shared ControlLight runtime; and
- contains no device action, direct value write, script mutation or legacy
  script-execution call.

The package verifier proved the candidate and rollback hashes, complete
29-wrapper regression matrix, runtime and wait-helper identities, Auto-Off
variable-role contract and closed activation, mutation and device-action gates.

## Read-only Live Result

The package-bound live probe completed without transport error, PHP execution
error or truncation. It did not execute a wrapper or Auto-Off script, request an
action, change a value, create an object or modify source.

The gate proved:

- ready kernel state;
- 29 of 29 wrapper sources at the expected six-v2/23-legacy baseline;
- all runtime-relevant fileset sources and the process-effective corrected
  wait helper at their pinned identities;
- exact equality between the live `CL-026` source and the rollback image;
- unchanged wrapper parent, target link, local variables and three legacy
  feedback events;
- actionable STATE, DIMMER and color-temperature targets;
- no pre-existing v2 diagnostics below the legacy wrapper;
- exactly one script consumer, the already modernized Auto-Off automation;
- exactly two downstream Auto-Off events, for STATE and DIMMER; and
- exactly three presentation links to the local ControlLight variables.

## Expected Non-commanding Synchronization

STATE is currently false both locally and at the target. The two reported
feedback differences are:

| Variable | Current local | Current target | Expected after v2 initialization |
| --- | ---: | ---: | ---: |
| STATE | false | false | false |
| DIMMER | 0 | 28 | 28 |
| Color temperature | 2600 K | 2604 K | 2604 K |

The first configuration execution is therefore expected to change two local
presentation values without sending a device command. STATE and the physical
light remain off. This is the reviewed `reported` synchronization boundary,
not optimistic device control.

## Activation Gate

No activation is authorized by this report. After separate approval, the
transaction must:

1. re-run the private package verifier;
2. compare the live wrapper and Auto-Off sources with their pinned identities;
3. snapshot all local values, presentation metadata, links and the Auto-Off
   timer immediately before mutation;
4. replace only the `CL-026` wrapper source;
5. perform two non-commanding configuration passes;
6. prove the exact expected local synchronization, zero command delta,
   idempotent topology, diagnostics and presentation-link preservation;
7. pass the complete 29-wrapper and Auto-Off structural regression; and
8. roll back the exact legacy bytes and owned v2 additions on any failed gate.

A real-device capability sequence remains a later, separately approved test.
