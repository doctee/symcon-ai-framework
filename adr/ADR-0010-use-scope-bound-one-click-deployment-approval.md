# ADR-0010: Use scope-bound one-click deployment approval

Status: Accepted for repository integration; live use remains gated
Date: 2026-09-07

## Context

Channel version 8 deliberately separates qualification, inactive staging,
read-only preflight, activation, independent postflight, rollback, active
identity reseal and retention. That separation makes failures attributable and
keeps each mutation reviewable, but it also causes a user to approve several
mechanical continuations of one already reviewed plan.

Combining those steps into a shell command or adding a general remote command
would weaken the forced-command boundary. A plain confirmation string would
also be replayable and would not bind the reviewed baseline, target or adapter.

## Decision

SAEF uses a short-lived HMAC approval proof for one immutable deployment plan.
The user-facing action may be presented as **Jetzt anwenden**, but the internal
state machine retains all existing phases and safety checks.

The proof binds:

- the canonical plan SHA-256;
- target and adapter profile;
- the exact ordered operation set;
- expected starting identities;
- an opaque channel-host binding;
- opaque approver and execution-host bindings;
- issue and expiry times; and
- a cryptographic nonce.

The private HMAC secret and raw identities are never part of repository files,
the plan or retained evidence. The coordinator atomically claims a nonce under
the plan identity, HMAC-protects every bounded state record, persists phase
transitions before invoking a runner and rejects a terminal or differently
claimed plan. One fixed coordinator lock prevents cross-plan execution races.
The runner receives a fixed phase name and structured immutable context, never
a command or executable path from the plan.

The fixed sequence is qualification, stage, preflight, activation and
independent postflight. A target may additionally bind active-identity reseal
and a second independent postflight. Rollback is mandatory in both forms and
is invoked automatically after any potentially mutating failure. An unproven
rollback ends in `manual_recovery_required`.

The remote channel keeps exactly `probe`, `stage`, `preflight`, `activate` and
`status`. The Windows integration maps coordinator phases only to installed,
hash-pinned profiles and adds no arbitrary remote execution.

Allowlist changes, service restarts, provider contacts, publication and
retention deletion are rejected by this one-click plan. They remain separate
risk gates. Cross-root standalone-module retention is specified separately in
`project/STANDALONE_MODULE_CROSS_ROOT_RETENTION.md`.

Cross-root retention implementation is deliberately deferred to its own
workstream. It spans adapter state, channel deployment state and managed
filesets, introduces deletion authority and needs a distinct review plan,
Windows qualification, backup, rollback and live gate. Combining it with
deployment confirmation would violate the existing retention boundary.

## Rationale

The proof captures what the user reviewed instead of only recording that a
button was pressed. Persist-before-call transitions make lost responses and
process crashes explicit. A resumed uncertain mutation is inspected before any
further step; it is never blindly repeated. Existing channel and adapter locks
remain authoritative for cross-process and server-side serialization.

This composes the target-bound adapter, reseal and rollback contracts instead
of moving their responsibilities into a generic coordinator.

## Consequences

### Positive

- One deliberate user action can authorize one exact, short-lived plan.
- Replay, double-click and concurrent execution fail closed.
- Baseline drift is checked immediately before activation.
- Lost feedback has a deterministic inspect-or-manual-recovery boundary.
- OwnTracks can be the first profile without making it the generic design.

### Negative

- A trusted controller must protect the HMAC secret and private state root.
- Each Windows runner profile still needs PowerShell 5.1, ACL and rollback
  qualification.
- Existing adapters need explicit post-success rollback and inspection entry
  points before production one-click activation can be enabled.
- Each exact Windows runner/profile generation requires its own Windows
  PowerShell 5.1 qualification before installation.

## Alternatives considered

### Concatenate existing client commands

Rejected because the sequence has no one-use claim, crash reconciliation or
automatic postflight rollback and cannot safely integrate reseal.

### Add a general gateway operation

Rejected because it would widen the restricted SSH grammar and create a route
to arbitrary server-side code.

### Put target health and reseal into the generic gateway

Rejected because those remain adapter-owned, target-specific responsibilities.

## Related

- `adr/ADR-0007-use-restricted-windows-deployment-channel.md`
- `adr/ADR-0009-use-target-bound-standalone-module-deployment.md`
- `project/SCOPE_BOUND_DEPLOYMENT_APPROVAL.md`
- `project/CHANNEL_V8_ONE_CLICK_THREAT_MODEL.md`
- `project/STANDALONE_MODULE_CROSS_ROOT_RETENTION.md`
