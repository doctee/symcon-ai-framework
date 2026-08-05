# 279 Navimow Account Status Correction SAEF PR Merge and Canonical Verification

**Case study:** Navimow native IP-Symcon module

**Status:** Explicitly authorized PR merge executed and canonical SAEF mainline
independently verified; cleanup, standalone and live gates remain closed

**Date:** 2026-08-05

**Scope:** Add the bounded merge-execution contract to PR 23, verify its final
report-only head and terminal checks, merge through GitHub using the repository
merge-commit convention and verify canonical remote `main` without deleting
the source branch or accessing any standalone or live system

## 1. Result

Gate P4 passes.

```text
repository:        doctee/symcon-ai-framework
pull request:      #23
title:             feat(navimow): close mqtt episode and account status recovery
base before merge: 2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
reviewed head:     be6d5c25d04ac6116f478b8041e412e85f83f288
final PR commits:  10
final PR paths:    27
merge method:      GitHub merge commit
decision:          PASS
```

The exact final PR head and resulting merge commit are resolved directly from
Git and GitHub after execution. The merge commit is intentionally not embedded
recursively in this pre-merge report.

## 2. Authorization Boundary

The user explicitly authorized SAEF step 279 after step 278 completed without
blocking findings.

This permits:

- fresh read-only Git and GitHub preflight;
- this report and README entry;
- one local documentation-only final PR commit;
- one normal fast-forward push to the existing PR branch;
- waiting for terminal checks on that exact final head;
- one GitHub merge of PR 23 using a merge commit;
- fresh read-only verification of PR state and canonical remote `main`.

It does not permit force push, rebase, squash, auto-merge, direct push to
`main`, source-branch deletion, worktree removal, tag, release, standalone
publication, Symcon access, MQTT activation, credential retrieval, OAuth
action, restart or mower command.

## 3. Authorized Pre-Merge Baseline

Immediately before creating this report:

```text
local head:        be6d5c25d04ac6116f478b8041e412e85f83f288
tracking head:     be6d5c25d04ac6116f478b8041e412e85f83f288
remote head:       be6d5c25d04ac6116f478b8041e412e85f83f288
PR head:           be6d5c25d04ac6116f478b8041e412e85f83f288
origin/main:       2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
PR state:          OPEN
draft:             false
mergeability:      MERGEABLE
merge state:       CLEAN
PR commits:        9
PR paths:          26
cross-case paths:  0
CI checks:         2 of 2 successful
reviews:           0
inline comments:   0
PR comments:       0
blocking findings: 0
```

The Account distribution artifact remained the reviewed Git blob:

```text
ad4432c29613062cd277e44ed161a7877b624da5
```

## 4. Final Pull Request Scope

This report and its README entry form one final documentation-only PR commit.
Because README is already part of the effective diff, the final scope becomes:

```text
commits after base: 10
changed paths:      27
cross-case paths:   0
productive changes: 0 in final commit
```

The final commit contains exactly:

```text
case-studies/navimow/README.md
case-studies/navimow/279-navimow-account-status-correction-saef-pr-merge-and-canonical-verification.md
```

Commit subject:

```text
docs(navimow): record canonical merge contract
```

No productive PHP, test, fixture, metadata, tool, shared helper or unrelated
case-study file changes in this closure commit.

## 5. Final-Head Gate

The merge may execute only when all conditions pass simultaneously:

1. local, fetched tracking, independent remote and PR head equal the final
   report commit;
2. `origin/main` still equals the authorized base and remains an ancestor;
3. GitHub renders exactly ten commits and 27 Navimow-only paths;
4. no deletion or unrelated path appears;
5. the productive Account blob remains exact;
6. GitHub reports `OPEN`, ready for review, `MERGEABLE` and `CLEAN`;
7. every check for the final head is terminal and successful;
8. no review, inline comment or PR comment introduces a finding;
9. the local worktree and index are clean.

An ambiguous push or check state stops for read-only diagnosis. It never
authorizes a retry, force push or direct-main correction.

## 6. Merge Procedure

The bounded mutation sequence is:

1. commit and push only the two documentation paths in section 4;
2. fetch and independently verify final remote and PR-head equality;
3. wait for all final-head checks to finish successfully;
4. repeat mergeability, scope, comment and base verification;
5. merge PR 23 once using GitHub's merge-commit method;
6. fetch remote refs without changing any local main checkout;
7. read back PR state, merge commit and remote-main head independently;
8. prove the final PR head is an ancestor of remote `main`;
9. verify the complete 27-path tree and Account blob from remote `main`;
10. verify report continuity and README uniqueness from canonical remote main;
11. stop without deleting branch or worktree.

The source worktree may remain on the merged topic branch. No checkout or
normalization of another worktree is required.

## 7. Canonical Verification Contract

The merge passes only when:

```text
PR state:                       MERGED
PR draft state:                 false
reported merge commit:          remote main head
final PR head ancestor of main: true
effective merged paths:         27 Navimow-only
step 279 report in remote main: exactly once
step 279 README entry:          exactly once
productive Account blob:        ad4432c29613062cd277e44ed161a7877b624da5
REST authority:                 preserved
MQTT direction:                 receive-only
MQTT default:                   disabled
standalone mutations:           0
Symcon reads or mutations:      0
```

Transport-level success from the merge call is insufficient without all
read-back conditions.

## 8. Preserved Runtime Boundary

Canonical SAEF ownership does not change runtime authority:

- REST remains authoritative for public mower state;
- MQTT remains optional, receive-only and disabled by default;
- no MQTT publish or mower-command route exists;
- variable and archive identities remain unchanged;
- the Account status correction only finalizes normal Core lifecycle status;
- prior recovered MQTT transport episodes remain a private-pilot risk;
- public OAuth, Store and complete command readiness remain unresolved;
- no general-availability claim follows from this merge.

## 9. Architecture Decisions

### AD-NAV-1146: Include the merge contract in the merged history

The canonical case-study sequence must contain the authorization boundary and
verification contract used to change mainline ownership.

### AD-NAV-1147: Revalidate the final report-only head

Documentation changes commit identity and therefore require fresh scope,
mergeability and terminal CI evidence.

### AD-NAV-1148: Use the repository merge-commit convention

The merge commit preserves the reviewed topic history and matches the prior
SAEF Navimow integration method.

### AD-NAV-1149: Verify remote main independently

Neither the merge command nor PR UI state alone proves canonical integration.

### AD-NAV-1150: Preserve exact productive identity through merge

The Account blob must remain byte-identical from reviewed branch to remote
main.

### AD-NAV-1151: Keep source retention separate

Branch deletion and worktree removal are destructive cleanup operations with
their own evidence and authorization requirements.

### AD-NAV-1152: Avoid another worktree mutation

Remote refs provide canonical proof without switching or normalizing the
user-owned main checkout.

### AD-NAV-1153: Keep standalone publication independent

SAEF mainline ownership does not authorize changing the dedicated module
repository.

### AD-NAV-1154: Keep all live gates independent

Repository integration grants no Symcon, authentication, MQTT, restart or
device permission.

### AD-NAV-1155: Stop after canonical verification

Successful merge closes this workstream's mainline gate only; next-step and
retention decisions remain explicit.

## 10. Safety Result

```text
final report commits:       1
final branch pushes:        1
push retries:               0
force pushes:               0
pull-request merges:        1
merge retries:              0
direct main pushes:         0
tags or releases:           0
branch deletions:           0
worktree removals:          0
standalone changes:         0
Symcon reads:               0
Symcon mutations:           0
MQTT activations:           0
credential requests:        0
OAuth actions:              0
service restarts:           0
mower commands:             0
```

## 11. Gate Status

| Gate | Status |
|---|---|
| explicit Gate P4 authorization | PASS |
| final report-only head publication | PASS |
| final head scope and CI | PASS |
| PR #23 merge | PASS |
| canonical remote-main verification | PASS |
| branch deletion | CLOSED |
| worktree cleanup | CLOSED |
| standalone publication | CLOSED |
| Symcon operation | CLOSED |
| MQTT activation | CLOSED |
| mower command | CLOSED |

## 12. Next Step

Proceed with an analysis-only closure decision:

```text
280-navimow-account-status-correction-post-merge-retention-and-next-step-review.md
```

That step should confirm what evidence, branch and worktree must be retained,
assess whether any further standalone or live action is actually required and
keep every destructive or operational action separately gated.
