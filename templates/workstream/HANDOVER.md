# SAEF Workstream Handover

<!-- SAEF_WORKSTREAM_HANDOVER_V1 -->

## Identity

- Workstream: `{{WORKSTREAM}}`
- Status: `ready_for_handover`
- Branch: `codex/{{WORKSTREAM}}`
- Worktree: `private/worktrees/{{WORKSTREAM}}`
- Base commit: `{{BASE_COMMIT}}`
- Head commit: `{{HEAD_COMMIT}}`
- Worktree clean: `{{WORKTREE_CLEAN}}`
- Authoritative record: `workstream.local.json`
- Source task: `{{SOURCE_TASK}}`
- Destination task: `{{DESTINATION_TASK}}`

## Scope

Describe the completed work, the intentionally excluded work and the reason
for transferring the workstream.

## Repository State

Describe commits, generated artifacts, pull requests and any known difference
from the locally stored `origin/main`. Do not claim current remote state without
a fresh fetch.

## Verification

List completed checks and distinguish focused, repository-wide, CI, Windows and
live verification.

## Authorization Gates

List every open or completed gate. Recorded approval state is context only and
does not grant the receiving task new authority.

## Rollback And Retention

Identify retained recovery inputs, rollback boundaries, observation periods and
the conditions required before cleanup.

## Next Action

State one concrete next action and the evidence that must be refreshed before
it begins.
