# ADR-0012: Use risk-tiered Symcon change workflows

Status: Accepted
Date: 2026-10-15

## Context

SAEF needs short feedback cycles for home-automation improvements without
losing source ownership, recoverability or control over physical effects.
Native MCP tools can read and maintain own module files directly. Requiring
a transport package, custom wrapper and transaction adapter for every small
change adds failure modes without necessarily reducing the target risk.

Conversely, a successful file write, syntax check or Git revert does not prove
correct automation behavior or restore persistent data and device state.
Repository work, publication, live execution and destructive operations remain
different authorization boundaries.

## Decision

Select the least complex workflow that covers the actual impact:

1. **Direct bounded change:** a small, independently reversible change with
   known ownership and limited effects uses private backup, offline tests,
   authorized MCP mutation, separate validation and independent readback.
2. **Isolated development:** new or larger behavior is developed in Git and
   tested offline and, where needed, on an isolated Symcon system before a
   separately approved production transition.
3. **Planned high-impact change:** migrations, deletion, archive correction,
   security changes and coupled changes requiring consistency use an exact
   plan, state/data backup, recovery strategy and bounded execution approval.

These are engineering routes, not automatic grants of authority. Classify by
consequences, dependencies and reversibility, not line count or file type.
Unknown impact blocks mutation until clarified. Complex development can still
need the high-impact route for its production transition.

Use native specialized MCP operations where their verified contract suffices.
Do not create a wrapper, ZIP exchange or new adapter merely to change a file.
Existing installed deployment contracts, ownership pins, locks and rollback
requirements remain binding. Switching their owner or delivery route requires
an explicit migration; direct MCP writes must not bypass them.

ADR-0006 through ADR-0011 remain valid in their respective managed-runtime,
publication and deployment scopes. This decision narrows selection of those
mechanisms; it does not change their implementations or installed policies.

Keep one authoritative source per artifact. Git publication is independent
of private development and live testing. Do not auto-deploy an agent-writable
development branch or silently merge bidirectional live/repository changes.

The operational detail is in
[Symcon change workflow](../project/SYMCON_CHANGE_WORKFLOW.md).

## Rationale

Safety properties are required; a particular transport implementation is not.
Simple changes should have simple recovery. Persistent state migrations need
more than source recovery. Test isolation and observation-only comparisons
reduce production experimentation more effectively than extra wrappers.

## Consequences

- Routine work needs one compact change record, not nested qualification
  packages. Native result contracts still require independent verification.
- A bounded test-environment authorization can cover repeated development
  iterations; it cannot authorize production access or deployment.
- Readback proves deployed bytes, not control quality. Domain acceptance and
  physical side effects remain explicit.
- Existing deployment infrastructure is retained, not cleaned up, disabled
  or migrated by this decision.
- No new MCP client, runtime helper, token configuration or live action is
  introduced by adopting this workflow.

## Alternatives considered

- **Custom transaction tooling for every change:** rejected as the default;
  retain it where coupled state, ownership or crash recovery requires it.
- **Unrestricted live development with backups only:** rejected; a backup
  cannot undo physical effects or prove an isolated test.
- **Read-only MCP plus automatic Git pull:** useful separation of access, but
  not a production review boundary when unreviewed commits are auto-activated.
- **Mandatory publication before every iteration:** rejected for private
  development; publication retains its own review and authorization.
