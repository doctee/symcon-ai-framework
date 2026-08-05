# 281 Navimow Account Status Correction Post-Merge Closure Canonicalization

**Case study:** Navimow native IP-Symcon module

**Status:** Post-merge retention decision canonicalized in one local
documentation commit; publication, cleanup and live gates remain closed

**Date:** 2026-08-05

**Scope:** Canonicalize the post-merge retention review, this closure report
and their README entries as one exact local documentation commit based on
current canonical SAEF `main`, without publishing or deleting anything

## 1. Result

The local closure canonicalization passes.

```text
branch:                 codex/navimow-post-merge-retention-review
parent before commit:   6a7094202c5db3a06e5bf2e101eee56dc0163f20
origin/main:            6a7094202c5db3a06e5bf2e101eee56dc0163f20
documentation reports: 2
README files:           1
productive files:       0
paths outside Navimow:  0
local commits:          1
pushes:                 0
pull requests:          0
cleanup operations:     0
```

The containing commit identity is resolved from Git after creation and is not
embedded recursively in this report.

## 2. Authorization Boundary

The user explicitly authorized SAEF step 281.

This permits:

- fresh read-only `origin/main` verification;
- consistency and privacy hardening of step 280;
- this report and README entry;
- staging exactly the three documented paths;
- one local documentation-only commit;
- post-commit local scope and identity verification.

It does not permit push, pull request, merge, branch deletion, worktree
removal, private-evidence deletion, tag, release, standalone publication,
Symcon access, MQTT activation, credential retrieval, OAuth action, restart or
mower command.

## 3. Fresh Base Verification

A fresh fetch proved:

```text
branch HEAD:  6a7094202c5db3a06e5bf2e101eee56dc0163f20
origin/main:  6a7094202c5db3a06e5bf2e101eee56dc0163f20
worktree base equals canonical main: true
```

The worktree was created specifically from the verified merge commit for this
post-merge closure. The older merged topic branch, its worktree and unrelated
worktrees were not modified or used as source.

## 4. Exact Commit Scope

The local commit contains exactly:

```text
case-studies/navimow/README.md
case-studies/navimow/280-navimow-account-status-correction-post-merge-retention-and-next-step-review.md
case-studies/navimow/281-navimow-account-status-correction-post-merge-closure-canonicalization.md
```

Classification:

| Class | Paths |
|---|---:|
| Navimow reports | 2 |
| Navimow README | 1 |
| productive module files | 0 |
| tests or fixtures | 0 |
| shared helpers | 0 |
| generated distributions | 0 |
| paths outside `case-studies/navimow/` | 0 |

Commit subject:

```text
docs(navimow): canonicalize post-merge retention closure
```

The parent must remain exact and no amend, rebase, squash or merge is permitted
in this step.

## 5. Canonicalized Decision

The committed unit records one closed operational conclusion:

```text
Account correction implementation: complete
standalone publication:             complete
installed correction verification: complete
canonical SAEF merge:               complete
additional standalone action:       not required
additional Symcon action:           not required
MQTT activation consequence:        none
```

It also records distinct retention classes:

- permanent public SAEF and GitHub history;
- retained private machine-readable update evidence;
- technically cleanup-eligible but still protected merged source branch and
  worktree;
- active post-merge closure branch and worktree.

Cleanup eligibility remains separate from deletion authorization.

## 6. Documentation Hardening

Step 280 initially described a two-file canonicalization unit. That would have
left this step-281 report uncommitted immediately after the commit. The wording
is corrected in the same canonical unit to require three paths: both reports
and README.

This follows the established Navimow pattern used by step 271: a
canonicalization report is part of the commit whose exact scope it defines.
The correction changes no architectural or operational decision.

## 7. Validation Contract

Before commit:

- the index must initially be empty;
- the worktree allowlist must contain exactly the three paths in section 4;
- tracked and untracked diff checks must pass;
- all new ADR identities must be unique;
- privacy scanning must find no absolute local path, ObjectID, credential,
  token, private topic, hostname, payload, device identity or garden data;
- no productive, test, fixture, helper or distribution path may enter the
  index.

After commit:

- parent and subject must equal section 4;
- the commit path allowlist must remain exact;
- the branch must be one commit ahead of `origin/main` and zero commits behind;
- the worktree and index must be clean;
- productive Account blob
  `ad4432c29613062cd277e44ed161a7877b624da5` must remain exact;
- no remote reference may change.

The productive and test tree is byte-identical to the already reviewed and
merged PR 23 state. Documentation-only canonicalization therefore needs no
repeat Symcon or module test.

## 8. Publication Boundary

This local commit is reviewable but unpublished.

A later publication plan must independently define:

1. fresh `origin/main` and ancestry handling;
2. exact commit and three-path verification;
3. branch push as a separate gate;
4. pull-request creation and review as separate gates;
5. merge and canonical verification as a separate gate;
6. source-branch and worktree cleanup only after closure publication;
7. no standalone or live operation.

No remote branch is created or updated by this step.

## 9. Architecture Decisions

### AD-NAV-1166: Canonicalize post-merge evidence on a new main-based branch

The merged topic branch remains immutable evidence while new work starts from
current canonical SAEF main.

### AD-NAV-1167: Use one self-contained three-file commit

Both reports and README form one reviewable closure unit without leaving an
immediate uncommitted report.

### AD-NAV-1168: Preserve the exact merge commit as parent

The post-merge closure must contain no unrelated mainline or topic history.

### AD-NAV-1169: Keep operational completion unchanged

Canonicalization records the decision and does not reopen standalone, Symcon
or MQTT work.

### AD-NAV-1170: Carry code validation by tree identity

No productive or test blob changes after the fully checked and merged PR head.

### AD-NAV-1171: Keep private evidence outside the commit

Only its retention class is documented publicly; installation data remains in
the private overlay.

### AD-NAV-1172: Separate publication from local canonicalization

A local commit is not authorization to change any remote review state.

### AD-NAV-1173: Preserve destructive cleanup as a later gate

Neither branch nor worktree reachability permits deletion during this step.

## 10. Safety Result

```text
local documentation commits: 1
staged public paths:          3
repository pushes:           0
pull requests:               0
merges:                      0
branch deletions:            0
worktree removals:           0
private evidence deletions:  0
standalone changes:          0
Symcon reads:                0
Symcon mutations:            0
MQTT activations:            0
credential requests:         0
OAuth actions:               0
service restarts:            0
mower commands:              0
```

## 11. Gate Status

| Gate | Status |
|---|---|
| post-merge retention review | PASS |
| three-file local canonicalization | PASS |
| operational correction follow-up | NOT REQUIRED |
| branch publication | CLOSED |
| pull request | CLOSED |
| merge | CLOSED |
| merged source branch deletion | CLOSED |
| merged source worktree removal | CLOSED |
| private evidence deletion | CLOSED |
| standalone or Symcon operation | NOT REQUIRED |
| MQTT activation | CLOSED |
| mower command | CLOSED |

## 12. Next Step

Proceed with:

```text
282-navimow-account-status-correction-post-merge-closure-publication-plan.md
```

That step should verify the resulting local commit and define the remaining
branch, pull-request, merge and cleanup gates. It must perform no publication,
cleanup or live action without further explicit authorization.
