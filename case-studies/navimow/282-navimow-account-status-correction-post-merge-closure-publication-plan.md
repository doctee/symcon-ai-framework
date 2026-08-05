# 282 Navimow Account Status Correction Post-Merge Closure Publication Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Documentation-only closure publication planned through separate
canonicalization, push, pull-request, review, merge and cleanup gates; no
publication or deletion performed

**Date:** 2026-08-05

**Scope:** Freeze the local post-merge retention closure, define its exact
publication and cleanup sequence, and preserve the completed standalone and
live boundaries

## 1. Decision

The post-merge closure is suitable for SAEF publication after its publication
plan is canonicalized and each remote or destructive mutation receives
separate authorization.

```text
branch:          codex/navimow-post-merge-retention-review
base:            6a7094202c5db3a06e5bf2e101eee56dc0163f20
current head:    cc906c29d3ec012afa7b71fea7f0f406e6ad6dcf
current commits: 1
current paths:   3
insertions:      584
deletions:       0
worktree:        clean before this planning report
remote branch:   absent
publication:     not authorized by this step
cleanup:         not authorized by this step
```

This workstream contains documentation only. The Account source correction,
standalone publication, Symcon update and canonical functional merge are
already complete and must not be repeated.

## 2. Authorization Boundary

The user authorized this planning step only.

This permits:

- fresh read-only branch, remote and diff inventory;
- publication and cleanup sequencing;
- this report and README entry.

It does not permit:

- another local commit;
- branch push;
- pull-request creation;
- review approval or merge;
- branch deletion or worktree removal;
- private-evidence deletion;
- standalone publication;
- Symcon access or mutation;
- MQTT activation or credential retrieval;
- restart, OAuth action or mower command.

## 3. Frozen Local Commit

The current branch contains exactly one commit after canonical `origin/main`:

| Order | Commit | Subject |
|---:|---|---|
| 1 | `cc906c29d3ec012afa7b71fea7f0f406e6ad6dcf` | `docs(navimow): canonicalize post-merge retention closure` |

The commit parent is exactly:

```text
6a7094202c5db3a06e5bf2e101eee56dc0163f20
```

It contains only:

```text
case-studies/navimow/README.md
case-studies/navimow/280-navimow-account-status-correction-post-merge-retention-and-next-step-review.md
case-studies/navimow/281-navimow-account-status-correction-post-merge-closure-canonicalization.md
```

No amend, rebase or squash may rewrite this identity because steps 280 and 281
bind their evidence to its parent, subject and exact path set.

## 4. Planned Final Candidate

This plan cannot be pushed while it is uncommitted. Gate C0 therefore adds one
self-contained second documentation commit containing:

```text
case-studies/navimow/README.md
case-studies/navimow/282-navimow-account-status-correction-post-merge-closure-publication-plan.md
case-studies/navimow/283-navimow-account-status-correction-post-merge-closure-plan-canonicalization.md
```

Expected final branch scope against the unchanged base:

```text
commits:           2
changed paths:     5
Navimow reports:   4
README files:      1
productive files: 0
test files:       0
cross-case paths: 0
```

The second commit subject is planned as:

```text
docs(navimow): canonicalize post-merge publication plan
```

This avoids the stopped-state pattern from step 273: the publication plan and
its own canonicalization evidence enter one local commit before any push
readiness decision.

## 5. Preserved Architecture and Operations

Every gate must preserve:

```text
public mower-state authority: REST
MQTT direction:               receive-only
MQTT default:                 disabled
MQTT publish path:            absent
MQTT mower-command path:      absent
Account status correction:    complete
standalone main:              eda494513826fa43ccc1b28634b06354356f49a4
installed correction:         historically verified exact
variable identities:          preserved
archive contracts:            preserved
additional live action:       not required
```

No documentation-publication gate may access or mutate the standalone module,
Symcon, OAuth state, MQTT transport or mower.

## 6. Gate C0: Plan Canonicalization

Gate C0 requires separate authorization.

Recommended wording:

```text
SAEF-Schritt 283
navimow-account-status-correction-post-merge-closure-plan-canonicalization.md
freigegeben.
```

It may:

1. refresh `origin/main`;
2. verify commit `cc906c29` and its exact three paths;
3. verify the only open paths are this report and README;
4. create step 283;
5. stage exactly the three planned paths in section 4;
6. create one local documentation commit;
7. prove the final two-commit, five-path Navimow-only scope;
8. stop without publication or cleanup.

### Gate-C0 stop conditions

Stop if:

- the worktree contains an unexplained path;
- `cc906c29` or its parent differs;
- `origin/main` has advanced without applying section 7;
- a privacy, ADR, continuity or whitespace check fails;
- any productive, test, fixture, helper or cross-case path appears.

## 7. Mainline Advance Policy

If `origin/main` advances before Gate C0, branch push or PR creation:

1. stop the active gate;
2. inventory the new mainline commits and affected workstreams;
3. merge current `origin/main` into this branch only after separate
   authorization;
4. do not rebase or squash `cc906c29`;
5. resolve only genuine documentation conflicts;
6. verify that the merge introduces no Navimow semantic change;
7. recompute commit and path scope against the new base;
8. repeat privacy, continuity and repository checks;
9. document the integration before publication.

Mainline advancement is not permission for an implicit local merge.

## 8. Gate C1: Final Local Publication Readiness

After Gate C0, a separate read-only readiness step must prove:

1. current clean branch and index;
2. exact two-commit sequence;
3. exact five-path Navimow-only allowlist;
4. zero productive and test delta;
5. report continuity through the current step;
6. exactly one README entry per new report;
7. privacy and ADR uniqueness;
8. exact productive Account blob
   `ad4432c29613062cd277e44ed161a7877b624da5`;
9. `git diff --check` success;
10. current `origin/main` ancestry.

Because the candidate is documentation-only and carries the already reviewed
productive tree byte-for-byte, no Symcon or module-functional retest is
required. GitHub CI remains mandatory after publication.

Gate C1 creates no commit or remote mutation. Its report may be included only
through another explicitly bounded canonicalization step; it must not silently
expand the frozen five-path candidate.

## 9. Gate C2: Branch Publication

Branch publication requires separate explicit authorization after the final
local candidate is frozen.

Recommended wording:

```text
Push des Branches codex/navimow-post-merge-retention-review für die
dokumentarische Navimow-Post-Merge-Closure freigegeben.
```

The push gate may:

1. repeat the final local readiness checks;
2. prove the remote branch is absent or equals an explicitly expected head;
3. push the exact candidate once with upstream tracking;
4. fetch and independently verify remote equality;
5. stop without creating a pull request.

Force push and blind retry are prohibited. Ambiguous transport is resolved by
read-only remote comparison.

## 10. Gate C3: Pull Request

Pull-request creation requires separate authorization after branch equality.

Recommended identity:

```text
title: docs(navimow): close account status correction retention
base:  main
head:  codex/navimow-post-merge-retention-review
mode:  ready for review
```

The description must state:

- documentation-only scope;
- Account correction is operationally complete;
- no standalone or Symcon action is required;
- private evidence is retained but not published;
- merged source cleanup remains separate;
- MQTT remains receive-only and disabled by default;
- all runtime and device gates remain closed.

PR creation does not authorize review approval or merge.

## 11. Gate C4: Review and Checks

The review gate must:

1. inspect every rendered commit and file;
2. prove documentation-only Navimow scope;
3. confirm no private installation detail entered the PR;
4. wait for all GitHub checks to reach terminal success;
5. inspect reviews, threads and comments;
6. classify any failure before changing the candidate;
7. issue only a conditional merge recommendation.

Any productive change invalidates this plan and requires a new workstream.

## 12. Gate C5: Merge and Canonical Verification

Merge requires separate explicit authorization after Gate C4 passes.

The merge gate must:

1. add its bounded merge contract to the PR only if that scope was planned and
   separately checked;
2. require exact PR head, base, path scope and terminal checks;
3. use the established GitHub merge-commit method;
4. fetch `origin/main` after merge;
5. verify the complete final head is an ancestor of remote main;
6. verify all new reports and README entries from remote main;
7. preserve the Account blob exactly;
8. stop before branch or worktree cleanup.

No direct push to main is permitted.

## 13. Gate C6: Source Cleanup

Cleanup is destructive and remains a separate gate after canonical closure
verification.

The cleanup inventory may consider:

- merged remote branch `codex/navimow-standalone-readiness`;
- its clean local branch and worktree;
- the later merged remote branch
  `codex/navimow-post-merge-retention-review`;
- its clean local branch and worktree.

Before any deletion:

1. refresh remote main and branch refs;
2. prove every candidate head is reachable from canonical main;
3. prove each worktree is clean and has no untracked evidence;
4. prove no active process, observation or rollback contract references it;
5. retain private machine-readable evidence;
6. list exact deletion targets and exclusions;
7. obtain explicit user authorization.

Unrelated Navimow recovery, ControlLight, Statistics, Open-Meteo or other
worktrees are excluded unless their own workstream authorizes cleanup.

## 14. Retention and Rollback

Until Gate C6 passes:

- retain both Navimow source branches and worktrees named in section 13;
- retain PR 23 and all canonical SAEF history permanently;
- retain private account-status update evidence;
- retain standalone identity `eda494513826fa43ccc1b28634b06354356f49a4`;
- do not use force push, branch deletion or worktree removal as rollback.

A publication defect is corrected only through a reviewed follow-up commit or
separately authorized branch replacement.

## 15. Architecture Decisions

### AD-NAV-1174: Publish only the post-merge closure

The candidate contains retention and provenance documentation, not another
functional correction.

### AD-NAV-1175: Canonicalize the plan before readiness

This avoids evaluating an inherently dirty candidate whose own publication
contract is not yet committed.

### AD-NAV-1176: Preserve `cc906c29` unchanged

Its parent, path set and evidence identity are already canonicalized locally.

### AD-NAV-1177: Use a two-commit, five-path final candidate

The structure is minimal, complete and independently reviewable.

### AD-NAV-1178: Separate push, PR, review and merge gates

Each changes a different remote state and requires its own fresh preconditions.

### AD-NAV-1179: Keep CI mandatory for documentation publication

Documentation-only scope lowers runtime risk but does not bypass repository
integrity checks.

### AD-NAV-1180: Keep operational completion immutable

Publishing retention evidence does not reopen standalone or Symcon work.

### AD-NAV-1181: Preserve private evidence outside Git

The public plan documents retention policy only, never installation content.

### AD-NAV-1182: Merge advancing main without rewriting evidence

An audited merge commit preserves existing local identities.

### AD-NAV-1183: Treat cleanup as a post-publication destructive gate

Merged reachability is necessary but not sufficient authorization for deletion.

### AD-NAV-1184: Limit cleanup to explicitly named Navimow artifacts

Other workstreams remain independent even when their worktrees appear stale.

### AD-NAV-1185: Retain live and device boundaries through closure

No documentation gate grants authentication, MQTT, restart or mower access.

## 16. Safety Result

This planning step performs:

```text
local commits:           0
repository pushes:      0
pull requests:          0
merges:                 0
branch deletions:       0
worktree removals:      0
private evidence reads: 0
standalone changes:     0
Symcon reads:           0
Symcon mutations:       0
MQTT activations:       0
credential requests:    0
OAuth actions:          0
service restarts:       0
mower commands:         0
```

## 17. Gate Status

| Gate | Status |
|---|---|
| local post-merge closure commit | PASS, `cc906c29` |
| Gate C0 plan canonicalization | CLOSED |
| Gate C1 final local readiness | CLOSED |
| Gate C2 branch publication | CLOSED |
| Gate C3 pull request | CLOSED |
| Gate C4 review and checks | CLOSED |
| Gate C5 merge and canonical verification | CLOSED |
| Gate C6 source cleanup | CLOSED |
| private evidence deletion | CLOSED |
| standalone or Symcon operation | NOT REQUIRED |
| MQTT activation | CLOSED |
| mower command | CLOSED |

## 18. Next Step

Proceed with:

```text
283-navimow-account-status-correction-post-merge-closure-plan-canonicalization.md
```

That step may create the exact local second documentation commit described in
section 4. It must not push, create a pull request, merge, clean up or access a
standalone or live system.
