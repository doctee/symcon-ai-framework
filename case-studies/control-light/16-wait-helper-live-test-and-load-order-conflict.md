# Wait Helper Live Test and Load-Order Conflict

**Gate:** Corrected same-second feedback device test
**Result:** BLOCKED — corrected helper was not loaded
**Date:** 2026-07-19
**Final live state:** Safe baseline restored

## Approved Sequence

The approved test sequence was STATE-on, DIMMER 40, DIMMER 100 and STATE-off,
with an immediate stop and compensation at the first discrepancy. A fresh
preflight confirmed the corrected CL-023 wrapper and fileset identities, safe
STATE and DIMMER baselines, actionable targets and unchanged command, error and
timeout counters.

STATE-on issued one regular `RequestAction()`. The target and local STATE later
both reached true, but ControlLight again reported an authoritative feedback
timeout. The sequence stopped before either DIMMER command.

STATE-off compensation then completed successfully through the same regular
action path. Final STATE is false locally and at the target; DIMMER remains 100
locally and at the target.

## Authoritative Runtime Identity

A bounded read-only Reflection probe resolved the actual function source in a
fresh Symcon script context. `SAEF_WaitForVariable()` was already defined before
the corrected ControlLight runtime was required. Its source was the active MQTT
Discovery Exporter fileset, and its live SHA-256 was
`0e39bf12da3a88f1a79b99cbeb54ed87d5a71e573146cce4e9ae7ed9f4c55bbb`.

The helper's guard constant then caused the corrected copy inside the new
ControlLight fileset to skip its definition. The prior non-commanding
activation proved only that the new runtime files could be selected; it did not
prove which guarded global function implementation was effective.

This explains why the same timeout reproduced without invalidating the offline
same-second regression test: the tested function and the live function were
different implementations.

## Diagnostics and Rollback

The failed STATE-on and successful compensation produced the expected bounded
deltas:

- two device commands in total;
- one new error and one new confirmation timeout from STATE-on;
- no DIMMER command;
- successful STATE-off compensation; and
- one additional retained ErrorRingBuffer entry.

CL-023 was then restored byte-exactly to its preceding wrapper source and run
once for non-commanding synchronization. The rollback Registry version,
wrapper hash, ownership, final values and diagnostic relationships all passed.

## Gate Decision

The ControlLight-only rollout is **BLOCKED** by shared-helper load order. A
longer timeout or another ControlLight wrapper selection cannot correct it.

The next engineering gate must inventory how the MQTT fileset is loaded into
the global Symcon PHP context and migrate that owning load path to the already
regenerated MQTT fileset containing helper identity
`4b79fb7a7339573f61a84d64e8634d6dc7faa3d161f645277a5e62228b8a7222`.
Because an existing global function cannot be redefined in-process, the gate
must also establish whether a PHP-context or service restart is required and
verify the effective function source by Reflection before any new device test.

That read-only inventory is completed in the MQTT Discovery Exporter case
study's report 31. It confirms that `System.Locals` owns the selection and that
a supervised clean-process service restart is required.

## Follow-up

The owner-fileset activation and clean-process restart subsequently passed.
Reflection now proves that the corrected shared helper is effective, and all
29 installed ControlLight wrapper sources passed a read-only identity
regression. Report 17 records this dependency unblock. The historical failed
device test above remains valid evidence; a repeated functional sequence is a
new, separately approved gate.
