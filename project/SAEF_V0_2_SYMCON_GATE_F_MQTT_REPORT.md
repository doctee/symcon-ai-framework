# SAEF v0.2 Symcon Gate F MQTT Report

**Gate:** Repeated MQTT preparation and no-op reconcile verification
**Result:** PASS
**Date:** 2026-07-20
**Live impact:** Diagnostic run statistics only; no new MQTT message or device action

## Scope

This Gate F sub-gate exercised the active v0.2.0 MQTT Discovery Exporter owner
through its existing public script boundary. It verified repeated configuration
preparation and the explicitly confirmed reconcile-without-cleanup operation.

The sub-gate did not execute cleanup, dispatch a command, select a ControlLight
consumer or invoke a physical device action. Object IDs, private configuration,
topics and runtime payloads remain outside this repository.

## Preflight

The fresh preflight confirmed:

- the kernel was ready;
- the exporter hierarchy matched the Gate E topology identity;
- Registry and ErrorRingBuffer JSON were valid;
- diagnostics counters and bounded history matched the post-activation state;
- the one managed pilot entity was already published;
- its current and published configuration fingerprints matched; and
- the required synchronous parameterized script boundary was available.

## Repeated Preparation

The owner script was first executed twice without an operation parameter. This
selects only `prepareReconcile()` and cannot enter publication or command
dispatch.

After each execution:

- the complete object, variable, instance, event, link, action and archive
  topology identity was unchanged;
- the Registry identity was unchanged;
- the ErrorRingBuffer identity was unchanged; and
- all execution, success, failure, command, publication and skip counters were
  unchanged.

This proves that the active final fileset reuses the existing compatible
configuration structure without duplicate objects, events or metadata writes.

## Repeated Reconcile

The existing owner script requires both a named operation and its private pilot
confirmation before it can call `executeReconcileWithoutCleanup()`. That guarded
path was executed twice.

Both runs reported:

- exactly one managed entity;
- the established publisher set;
- matching current and published configuration fingerprints;
- discovery and runtime state already published;
- zero published messages; and
- two skipped unchanged channels.

Only the expected execution, success, skip and timestamp diagnostics advanced.
Failure, command and publication counters did not advance, and the bounded
error history remained byte-identical.

## Independent Postflight

The final bounded read-only probe confirmed:

- the kernel remained ready;
- the complete owner topology identity remained equal to the preflight;
- Registry and ErrorRingBuffer remained structurally valid;
- the configuration fingerprints still matched;
- all six exporter events remained active with explicit Run Automation action
  binding; and
- the gate emitted no MQTT message and attempted no device action.

Every MCP operation completed without transport error, PHP execution error or
output truncation.

## Gate Decision

The MQTT idempotency part of Gate F is **PASS**. The final exporter fileset has
now been verified both structurally and through repeated live preparation and
reconcile behavior.

This result does not authorize command dispatch or physical-device testing. It
also does not select the staged final ControlLight fileset. Those remain
separate supervised Gate F actions with fresh preflight and bounded
authoritative observation.

## Related Artifacts

- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
- `project/SAEF_V0_2_SYMCON_GATE_D_E_REPORT.md`
- `case-studies/mqtt-discovery-exporter/14-supervised-integration-and-rollback-plan.md`
- `references/RI-001-idempotent-configuration-script.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
