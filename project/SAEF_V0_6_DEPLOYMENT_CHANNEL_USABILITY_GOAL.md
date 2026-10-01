# SAEF v0.6 Deployment Channel Usability Goal

**Status:** Product goal accepted; implementation deferred
**Decision date:** 2026-09-17
**Baseline:** SAEF v0.5.0 and Channel v8

## Purpose

SAEF v0.6 should make routine deployment-channel work substantially simpler
for the operator without weakening the security, evidence or rollback
contracts established by Channel v8.

The v0.5 MQTT runtime-fileset rollout proved the required internal safeguards,
but its integration and qualification history exposed too many implementation
details in the human approval flow. Names such as qualification generations,
consumer revisions and chains of intermediate status hashes are useful machine
evidence. They are not the intended routine operator interface.

## Product Goal

A routine supported deployment should require two understandable operator
steps:

1. **Prepare plan** builds or selects the immutable package, stages it
   inactive, performs the required read-only checks, creates the protected
   backup and presents one human-readable review plan.
2. **Apply now** grants one short-lived approval for that exact plan. The
   channel then performs the one-use claim, fresh baseline validation,
   activation, required reload or restart, health checks, postflight and any
   automatic rollback inside the existing bounded transaction.

An explicit read-only inspection remains available after a lost or ambiguous
response. It is a recovery path, not a normal third step. An uncertain
mutation is never repeated automatically.

## Operator Contract

The review presented to a human should emphasize:

- the target and candidate version or package identity;
- the current state that will be replaced;
- whether activation includes a module reload or service restart;
- the health checks and rollback boundary;
- the approval expiry; and
- actions that remain explicitly excluded.

Exact hashes, profile generations and component identities remain recorded and
validated by the system. Routine approvals should not require the operator to
manually assemble or restate those internal bindings when the tooling can
verify them directly from protected evidence.

## Preserved Safety Contract

The usability goal does not collapse or remove the internal phases. A v0.6
implementation must retain:

- deterministic packages and exact source identities;
- inactive staging before activation;
- protected byte-exact backup and rollback evidence;
- a server-generated immutable review plan;
- a short-lived, one-use claim bound to the target, host and operator;
- fresh drift detection immediately before the first mutation;
- bounded child processes and serialized channel execution;
- independent postflight and automatic rollback where the profile permits it;
- fail-closed recovery and read-only inspection after uncertain responses; and
- separate authorization for channel administration, publication, retention
  deletion, device actions and unrelated live mutations.

Qualification remains mandatory when a runner, adapter, policy schema or
other trusted profile generation changes. A normal deployment using an
already installed and qualified profile should reuse that qualification
instead of exposing it as a new human approval chain.

## Typical Channel Work

The simplified flow should cover supported, target-bound operations such as:

- shared Symcon runtime-fileset updates;
- MQTT exporter or ControlLight runtime updates;
- standalone module package updates through an installed target adapter;
- automatic runtime-health and runtime-mirror checks around activation;
- bounded read-only status or inspection after uncertain feedback; and
- automatic rollback inside the approved deployment transaction.

Channel installation, target allowlist changes, profile installation and
retention cleanup remain rare administrative workflows with their own gates.
Normal Symcon object or event maintenance, MQTT publication, device commands,
repository publication and release publication do not become deployment-
channel operations.

## Acceptance Criteria

The v0.6 implementation may be considered successful when:

1. a supported routine deployment has one preparation command or action and
   one explicit apply command or action;
2. the human review is understandable without knowledge of internal runner or
   consumer revision names;
3. installed qualification evidence is reused until trusted implementation
   bytes or contracts change;
4. the approval window permits deliberate review while fresh revalidation
   still occurs immediately before mutation;
5. status and evidence retain the full machine-verifiable hash chain;
6. ambiguous outcomes lead to read-only inspection, never a second apply; and
7. the existing five-verb forced-command boundary and all excluded authority
   remain intact unless a later ADR explicitly changes them.

## Deferred Work

This document records the product direction only. It does not select an
implementation, freeze the complete v0.6 scope, modify Channel v8, install a
profile, qualify Windows artifacts, create a live plan, activate a target,
restart a service or authorize cleanup.

Implementation should begin later in a dedicated workstream from a current,
clean `origin/main`, with separate review of the reusable profile, operator
summary, plan lifetime, qualification reuse and recovery behavior.

## Related

- `project/SAEF_V0_6_INVENTORY.md`
- `adr/ADR-0007-use-restricted-windows-deployment-channel.md`
- `adr/ADR-0009-use-target-bound-standalone-module-deployment.md`
- `adr/ADR-0010-use-scope-bound-one-click-deployment-approval.md`
- `adr/ADR-0011-use-bounded-powershell-child-processes.md`
- `project/SCOPE_BOUND_DEPLOYMENT_APPROVAL.md`
