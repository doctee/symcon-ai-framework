# 369 Local Map Runtime SAEF Branch Publication

**Case study:** Navimow native IP-Symcon module

**Status:** Local commit and bounded SAEF branch publication approved; pull
request, standalone publication and live gates remain closed

**Date:** 2026-08-27

## 1. Approved Gate

The user approved Gate P1 after the complete step-368 candidate review. This
gate permits exactly:

1. one Conventional Commit containing the reviewed local-map runtime
   candidate;
2. one push of `codex/navimow-map-source-readiness` to the SAEF origin;
3. read-only verification of the resulting local and remote commit identities.

It does not permit a pull request, merge, standalone module publication,
Module Validator run, Symcon update, map import, MQTT activation or mower
command.

## 2. Verified Baseline

Immediately before commit creation, the isolated worktree had this baseline:

    branch
    codex/navimow-map-source-readiness

    parent commit
    5bfb32d12c9f35adbff207b0634b873eac5052df

    origin/main
    016d78b028a8df261c0788133b86a5fdb9f411d2

    origin/main...HEAD
    behind 0, ahead 14

The branch therefore contains the preceding reviewed Navimow map-source steps
and no unintegrated current SAEF mainline commit. No remote branch with the
approved name existed before publication.

## 3. Frozen Candidate

The commit must preserve the exact step-368 productive candidate:

    fileCount
    42

    filesetSha256
    2d36cb88a552d6b6b7568673d1ba1b32cf4407fe9e894dcdfb5db3d35e06844c

    publicationSha256
    79f3c71e407c84736155b5c9afedaed377cb68293f3463088958215128e794bb

The commit also contains the public SAEF design, implementation, review and
test evidence. It excludes the private geometry, coordinates, visual preview,
authentication state and local capture tooling.

## 4. Verification Contract

Before committing:

- `git diff --check` must pass;
- the generated fileset and generic publication check must remain current;
- the staged paths must remain inside the Navimow case study and its explicit
  Navimow deployment and generated distribution surfaces;
- no ignored private file may be staged.

After committing and pushing:

- the worktree must be clean;
- the local branch tip and remote branch tip must match exactly;
- the committed fileset sidecar must retain the frozen fileset hash;
- no pull request or downstream mutation may be created implicitly.

## 5. Rollback And Retention

The published branch is an immutable review candidate. If verification fails,
publication stops without force-push, reset, cleanup or fallback to another
worktree. The worktree and branch remain retained until pull-request and
post-merge retention decisions are closed separately.

## 6. Next Gate

After successful local and remote identity verification, the next gate is a
SAEF pull request against `main`. Review, merge, standalone publication and
every Symcon action remain separately authorized decisions.
