# Hue Wall Concurrency Fix and Physical Regression

**Gate:** Shared Hue Wall concurrency correction and physical regression
**Result:** PASS — OFFLINE/RECOVERY TEST STILL PENDING
**Date:** 2026-07-26
**Live impact:** One handler source replacement and bounded light commands

## Scope

After explicit approval, the Hue Wall adapter was corrected so both rockers of
one physical module remain independently usable and commands from different
modules addressing the same lamp use bounded target serialization. The
physical assignment of switch S was also changed to left for Glaskugel and
right for Küchendecke.

The transaction did not change either ControlLight facade, globally select a
new System.Locals fileset, delete legacy objects, restart Symcon or simulate an
offline lamp. Installation-specific IDs, rollback material and exact timestamps
remain in the private evidence overlay.

## Corrected Contract

The adapter now owns one debounce timestamp per source and target, rather than
one timestamp for an entire wall module. A valid action on one rocker therefore
cannot suppress an almost simultaneous valid action on the other rocker.

Serialization remains target-owned. Different lamps can proceed independently;
commands from either module for the same lamp wait up to four seconds for the
short target critical section. State is read only after entering that section,
so the second command derives its toggle from confirmed current state.

The immutable candidate fileset was staged and hash-verified without changing
the global runtime selection. A fresh live preflight found zero source drift
across all 29 ControlLight wrappers and preserved all six handler events. The
handler then selected the new fileset privately and was read back byte-exactly.
Two reconciliations created the four new source/target debounce variables
without a device command. The two former per-source timestamps remain inert.

## Physical and Integration Regression

The four normal module/side paths were each toggled twice. All eight operations
produced exactly eight adapter commands and eight authoritative confirmations.
An external ControlLight state change followed by a physical toggle also
passed, proving that the adapter does not depend on a stale assumed rocker
position.

After the correction:

- switch S left controlled Glaskugel and switch S right controlled
  Küchendecke;
- simultaneous operation of both S rockers switched both targets on, and a
  second simultaneous operation switched both off;
- those two different-target scenarios produced four commands and four
  confirmations, with source updates at most 34 ms apart and no debounce loss;
  and
- rapid commands from N-left and S-right addressed Küchendecke in succession,
  producing the observed on-then-off result with both commands confirmed.

The two same-target source updates were approximately 1.003 seconds apart.
That live observation proves that neither command was dropped and that the
second used the first result, but it does not by itself measure time spent
waiting on the semaphore. The executable runtime regression separately proves
the bounded same-target wait/failure contract.

## Final Postflight

The final adapter totals were 17 command attempts and 17 confirmations, with
zero command failures, zero confirmation timeouts and zero debounced valid
actions. The adapter error history and both ControlLight error histories were
empty.

Glaskugel and Küchendecke ended off, with each local ControlLight STATE exactly
equal to native feedback. Their wrapper command totals reflected all adapter
and external-control operations, while both retained zero errors and zero
confirmation timeouts. Both active update-triggered action events, both active
change-triggered facade-feedback events and both inactive unnamed legacy
events retained their identities.

## Evidence Closure and Remaining Gate

The current sanitized fixture now marks CL-005 and CL-012 as active with passed
normal Hue Wall and concurrency regression. They are not promoted to
fully-device-tested because the complete enabled-capability matrices and the
planned offline/recovery scenario remain incomplete. The overall count
therefore remains eleven active v2 instances, eight fully device-tested and 18
retained legacy wrappers.

The next separately authorized gate is the offline/recovery matrix. It must
verify a physically unavailable lamp, structured failure without an uncaught
ScriptEngine fatal, restoration of connectivity, authoritative resynchronization
and the first post-recovery physical/voice interaction. Only after that gate
and an observation interval should the two inactive unnamed events and other
retained legacy state be considered for ownership-checked cleanup.
