# CL-014 Homematic State-Only Activation and Functional Test

**Gate:** Post-release state-only protocol migration
**Result:** PASS — INITIAL STATE RESTORED
**Date:** 2026-07-23
**Live impact:** Two authorized state commands; no compensation required

## Scope

The migration selected the retained state-only Homematic contract `CL-014`.
It reused the existing ControlLight container, wrapper, local `STATE` variable
and target link. The legacy Home Assistant exporter remained outside the
transaction and unchanged.

Installation-specific names, ObjectIDs, source backups and exact live evidence
remain in the private overlay.

## Corrected Blocker Classification

Earlier readiness reports classified the contract as an incompatible target
action. Fresh read-only inspection showed that the Homematic `STATE` variable
was Boolean, operational and actionable.

The actual incompatibility was structural:

- the legacy target link pointed directly at the `STATE` variable;
- ControlLight v2 expects the link to identify the target root and resolves a
  configured variable Ident below that root; and
- the Homematic device exposes the canonical uppercase Ident `STATE`, while the
  generic preset default is lowercase.

The migration therefore did not require a new helper or public runtime API. The
private wrapper explicitly selected `identState = STATE`, and the existing link
was retargeted from the direct variable to its owning Homematic instance.

## Preparation and Preflight

The private package contained:

- a hash-locked v2 wrapper candidate;
- a byte-exact legacy source backup;
- the existing and desired target-link contracts;
- the original local variable action, visibility, presentation and value
  baseline;
- a fixed allowlist for v2 diagnostics and the target feedback event; and
- leaf-first rollback instructions.

The fresh read-only preflight verified:

- ready kernel state;
- exact legacy wrapper and active immutable runtime hashes;
- unchanged three-child container topology and empty legacy wrapper topology;
- an operational target instance with actionable Boolean `STATE`;
- the inert local legacy `STATE` baseline;
- the alarm contract; and
- closed device-action and mutation gates before separate approval.

No object or device state changed during preparation or preflight.

## Activation

After explicit approval, one bounded transaction:

1. replaced only the existing wrapper source;
2. retargeted only the existing target link to the device root;
3. performed one non-commanding synchronization;
4. verified the eleven-object v2 child allowlist;
5. verified the update-triggered feedback event and explicit IP-Symcon
   `Run Automation` action binding; and
6. repeated reconciliation to prove stable object identity and topology.

Both reconciliations succeeded with zero commands, errors and confirmation
timeouts. Local and target state were equal. The wrapper's custom action was
set correctly, all other 28 wrapper sources were unchanged, and both legacy
Home Assistant exporter scripts retained their exact source identities.
Rollback was not required.

## Functional Test

The separately authorized real-device sequence was:

1. confirm the initial off state locally and at the target;
2. request on through the local ControlLight variable;
3. confirm authoritative on feedback locally and at the target;
4. request off through the same ControlLight variable; and
5. confirm exact restoration of the initial off state.

Exactly two ControlLight commands were recorded. The immediate test result
observed four successful wrapper executions from action and feedback handling.
Two additional successful update-triggered feedback executions settled before
the independent postflight. The command counter remained exactly two, proving
that the additional executions were feedback processing rather than duplicate
device commands.

Final diagnostics contained zero errors and zero confirmation timeouts. No
compensation command was needed.

## Evidence Closure

The private machine-readable evidence records:

- explicit authorization scope;
- package, activation and wrapper hashes;
- separate MCP transport, PHP execution and truncation channels;
- intended device-action count;
- immediate and settled diagnostic counters;
- initial-state restoration and compensation status; and
- the complete activation regression count.

This sanitized report records the engineering decision without exposing live
installation metadata. The current 29-contract fixture now classifies eight
wrappers as active v2 with explicit `reported` semantics and 21 retained legacy
contracts. Historical v0.2 and v0.3 reports retain their original seven/22
snapshot.

## Gate Decision

`CL-014` is activated and fully device-tested for its only enabled capability.
The previous Homematic blocker is resolved through an installation-specific
target-root and Ident correction composed from the existing ControlLight v2
runtime. No ControlLight core change is required.
