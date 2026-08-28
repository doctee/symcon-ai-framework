# 374 Local Map Runtime SAEF Merge And Canonical Verification

**Case study:** Navimow native IP-Symcon module

**Status:** PR merge executed and canonical SAEF main verified; standalone,
live and cleanup gates remain closed

**Date:** 2026-08-28

## 1. Result

PR #82 was merged into SAEF `main` using a GitHub merge commit. Fresh Git and
GitHub readbacks prove that the canonical merge contains exactly the reviewed
pull-request tree.

    repository: doctee/symcon-ai-framework
    pull request: #82
    base before merge: 016d78b028a8df261c0788133b86a5fdb9f411d2
    final reviewed head: 4d3e2fb025bfef4e266790e53e82179f3ddddfd5
    merge commit: ce1615f40b0f046c7b6ff61c6b6c889e0f96d758
    merge method: GitHub merge commit
    merged at: 2026-08-28T05:53:56Z
    final PR commits: 19
    final PR paths: 67
    decision: PASS

No standalone module, Symcon installation, OAuth session, MQTT transport or
mower was accessed or changed.

## 2. Authorization Boundary

The user fully authorized the next step after the positive merge decision in
step 373. That authorization covered:

- fresh Git and GitHub merge preflight;
- one merge of PR #82 using the repository merge-commit convention;
- independent canonical remote-main verification;
- this documentation-only post-merge evidence branch and publication.

It did not authorize standalone publication, tag or release creation, Symcon
update, official Module Validator execution, private geometry import, local-map
activation, MQTT activation, credential retrieval, OAuth action, restart,
mower command, branch deletion or worktree removal.

## 3. Final Pre-Merge Gate

Immediately before the merge, all required conditions passed:

    local head: 4d3e2fb025bfef4e266790e53e82179f3ddddfd5
    tracking head: 4d3e2fb025bfef4e266790e53e82179f3ddddfd5
    PR head: 4d3e2fb025bfef4e266790e53e82179f3ddddfd5
    origin/main: 016d78b028a8df261c0788133b86a5fdb9f411d2
    PR state: OPEN
    draft: false
    mergeability: MERGEABLE
    merge state: CLEAN
    changed paths: 67
    CI checks: 2 of 2 successful
    reviews: 0
    comments: 0
    blocking findings: 0
    worktree: clean

The final documentation head retained the correction commit
`198b3b452a4757183da635e49df6a1eb325eb8ef` and the positive re-review from
step 373.

## 4. Canonical Merge Proof

Freshly fetched `origin/main` resolves to:

    ce1615f40b0f046c7b6ff61c6b6c889e0f96d758

The merge commit has exactly these parents, in order:

    first parent
    016d78b028a8df261c0788133b86a5fdb9f411d2

    second parent
    4d3e2fb025bfef4e266790e53e82179f3ddddfd5

The complete Git tree of both the reviewed PR head and canonical merge commit
is:

    ec9a7b8bd4635e8c861dd63a4f1357b29e4c1024

An exact tree diff is empty. Both the authorized base and the final reviewed
head are ancestors of canonical `origin/main`. GitHub independently reports
PR #82 as `MERGED` with the same merge commit.

This proves that no conflict resolution, unreviewed file change or direct-main
mutation altered the reviewed candidate during integration.

## 5. Canonical Runtime Verification

A clean persistent worktree was created directly from the verified merge
commit. The complete Navimow offline suite passed again from that canonical
source:

    MQTT, REST and authentication fixtures: PASS
    receive-only MQTT parser and lifecycle checks: PASS
    path, task, geometry and zone reducers: PASS
    local-map scene and SVG renderer checks: PASS
    local-map lifecycle and restart checks: PASS
    variable-stability checks: PASS
    distribution and fileset checks: PASS
    PHPCS: PASS
    PHPStan: PASS, no errors

The general SAEF Composer resolver supplied only the primary checkout's
lock-identical vendor toolchain. The canonical worktree remained the sole
source and Git owner for this verification.

Frozen productive candidate remains:

    fileCount
    42

    filesetSha256
    a89dd1cce971342093cf70520f9d9e626106acbc0a2180dbeea192c78684c826

    publicationSha256
    c3e61fc36468728845db08ea462e2ec4ddd264b52d5534c9f29f48a0d3ef1633

## 6. Preserved Boundaries

- REST remains authoritative for public mower and station state.
- MQTT remains optional, receive-only and disabled by default.
- No MQTT publish or mower-command route was introduced.
- Existing Device variable and archive identities remain stable.
- `EnableLocalMap` remains `false` by default.
- The merge contains no private geometry, coordinates, credentials, hosts,
  personal ObjectIDs or capture output.
- Merging SAEF did not publish or update the standalone Symcon module.

## 7. Architecture Decisions

### AD-NAV-374-01: Verify the immutable merge tree

**Decision:** Use parent, ancestry and complete-tree equality as the canonical
merge proof.

**Reason:** PR state or a successful merge command alone cannot prove that the
reviewed candidate reached `main` unchanged.

### AD-NAV-374-02: Record post-merge evidence from canonical main

**Decision:** Create this report in a clean worktree based directly on the
verified merge commit.

**Reason:** A report created only on the merged topic branch would not prove
its own canonical source or separate post-merge documentation from the
productive candidate.

### AD-NAV-374-03: Preserve release and live gates

**Decision:** End this step after SAEF consolidation and evidence publication.

**Reason:** Standalone publication, Module Validator, disabled Symcon rollout
and private local-map activation have different mutation and privacy risks.

## 8. Retention

The merged source worktree and branch, the new documentation worktree and
branch, and all private evidence remain retained. No cleanup or deletion is
implicit in a successful merge.

The original source worktree remains useful until this post-merge report is
canonical and a separate retention review has established an exact cleanup
allowlist.

## 9. Gate Status

| Gate | Status |
| --- | --- |
| PR #82 final preflight | passed |
| PR #82 merge commit | passed |
| canonical parent and ancestry proof | passed |
| reviewed-head and merge-tree equality | passed |
| canonical full Navimow verification | passed |
| post-merge evidence branch publication | approved in this step |
| post-merge evidence PR and merge | separate |
| source branch or worktree cleanup | closed |
| standalone publication | closed |
| official Module Validator | closed |
| Symcon update or local-map activation | closed |

## 10. Next Step

Publish this two-file documentation closure through a small pull request and
verify its terminal checks. Its later merge may canonicalize the post-merge
evidence but must not include productive code. Only after that should a
retention and standalone-release readiness review decide the next independent
gate.
