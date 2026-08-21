# SAEF Workstream Coordination

**Status:** Stable Draft

## Purpose

SAEF work frequently spans shared helpers, generated filesets, live Symcon
consumers and independent case studies. This document defines how concurrent
work remains reproducible and how one workstream is prevented from changing or
invalidating another accidentally.

## One Workstream, One Clean Base

Every independently reviewable change uses:

- one dedicated Git worktree;
- one dedicated branch;
- a current `origin/main` base; and
- one explicitly stated functional scope.

A mixed or unexplained dirty checkout is recovery input, not a build,
publication or deployment source. Existing changes in such a checkout are
user-owned and must not be reset, deleted or reformatted while another
workstream is extracted.

Generated artifacts and deployment hashes are authoritative only when they are
reproducible from the clean workstream source.

## Worktree Placement

Persistent development worktrees use
`<primary-checkout>/private/worktrees/<workstream>` by default. Before creating
one, verify that the target does not exist and that the primary checkout
excludes `private/` from version control. This keeps the worktree durable,
private and inside the authorized repository workspace without mixing it into
the tracked source tree.

A system temporary directory is reserved for explicitly disposable tests or
historical reconstructions. Before placing a worktree there, document the
constraint that requires the exception, its retention risk and any resulting
workspace-authorization impact. Do not relocate an existing user-owned or
dirty worktree merely to normalize its path; moving it is a separate scoped
operation that preserves its branch and changes.

## Workstream Record

The private overlay should maintain a machine-readable record for each active
workstream with at least:

- workstream name and status;
- branch, worktree and clean base commit;
- public scope and private live scope;
- generated artifacts and live owner, if any;
- open authorization gates;
- rollback artifacts and retention deadline; and
- merge, evidence and cleanup status.

Installation paths, ObjectIDs, hostnames and private topics stay in that private
record and never enter the public repository.

## Shared-Impact Gate

Before changing a shared helper, generated bundle, deployment channel,
autoload boundary or restart behavior, perform a read-only impact inventory:

1. Identify every source and generated artifact that exports the changed API.
2. Identify the earliest effective live owner and its deterministic source
   identity.
3. Identify all known script, module and fileset consumers.
4. Identify active pilot, calibration or observation windows that constrain a
   restart or runtime change.
5. Separate the helper change from unrelated application-runtime changes.
6. Define command-free regression checks for every affected consumer.

Guard constants prevent redeclaration; they do not select a helper version.
Updating a later consumer does not update an earlier global owner.

## Live-Channel Gate

Authorized Symcon work uses the Symcon MCP channel by default and follows
`project/SYMCON_MCP_SCRIPT_READBACK.md`.

If the MCP binding is unavailable, stop and report it. Computer Use, browser,
SSH, PowerShell and temporary Symcon objects are separate fallbacks and require
their own explicit authorization.

Transport success, PHP execution success and non-truncated output are separate
acceptance conditions.

## Merge and Closure Gate

A workstream is not complete merely because its code is merged. Before it is
removed or used as the basis for destructive cleanup, close:

- focused and repository-wide tests appropriate to the risk;
- private machine-readable live evidence;
- sanitized public reports and current regression fixtures;
- rollback identity and minimum retention period;
- live consumer and effective-owner verification; and
- the decision to retain or remove staged immutable artifacts.

Temporary reconstruction worktrees may remain dirty when they deliberately
reproduce a historical live fileset. They must be clearly marked private, must
not be committed as canonical source and may be removed only after evidence and
rollback closure.

## Immutable Deployment Retention

Retention cleanup is a separate destructive gate. It requires a fresh live
reference scan, verified backup and exact candidate/protection lists.

The cleanup contract must preserve the manifest relationship between every
deployment state and its target fileset:

- a referenced fileset protects both the fileset and its deployment state;
- a retained deployment state protects its declared target fileset;
- postflight verifies the complete deployment-to-fileset mapping, not merely
  equal directory counts; and
- active and explicitly retained rollback artifacts remain protected.

Failed preflight or superseded activation state is not sufficient reason to
delete a deployment record when its target fileset is still referenced.

## Recommended Sequence

1. Refresh `origin/main` read-only.
2. Verify the ignored persistent worktree location, then create the dedicated
   worktree and branch.
3. Capture the clean baseline and workstream record.
4. Implement and test only the stated scope.
5. Perform the shared-impact gate where applicable.
6. Review, merge and verify the intended commit.
7. Complete any separately authorized live gate.
8. Close evidence, rollback and retention decisions.
9. Remove obsolete worktrees and immutable artifacts only through their
   dedicated cleanup gates.
