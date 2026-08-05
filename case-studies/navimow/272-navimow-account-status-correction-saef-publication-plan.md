# 272 Navimow Account Status Correction SAEF Publication Plan

**Case study:** Navimow native IP-Symcon module

**Status:** SAEF branch publication is planned through separate push, pull
request and merge gates; no publication performed

**Date:** 2026-08-05

**Scope:** Freeze the complete five-commit Navimow branch delta, define its
validation and publication gates, and preserve the current workstream and live
boundaries

## 1. Decision

The complete branch is suitable for SAEF publication after renewed validation
and separate authorization for each remote mutation.

```text
branch:          codex/navimow-standalone-readiness
base:            2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
head:            f583f8c99d9cb54dcda0c41612828b600d3fbb0f
commits:         5
changed paths:   19
insertions:      4117
deletions:       10
current state:   clean
publication:     not authorized by this step
```

The publication candidate is broader than the final Account report alone. It
contains the completed MQTT episode-accounting publication evidence, the
Account status correction and tests, and the complete correction publication
and live-verification evidence.

The five reviewed commits must remain intact. Squash or rebase would invalidate
commit identities already referenced by the evidence chain.

## 2. Authorization Boundary

The user authorized this planning step only.

It permits:

- read-only branch and diff inventory;
- publication sequencing and stop-condition design;
- this report and its README entry.

It does not permit:

- another local commit;
- branch push;
- pull-request creation;
- merge or branch deletion;
- standalone publication;
- Symcon access or mutation;
- MQTT activation or credential retrieval;
- restart, OAuth action or mower command.

## 3. Frozen Commit Chain

The current branch contains exactly these commits after `origin/main`:

| Order | Commit | Subject |
|---:|---|---|
| 1 | `42996e641b19ced13a811947b6deaff2009173d3` | `docs(navimow): review standalone mqtt publication readiness` |
| 2 | `c28c195452f6cd13f36899be022205af3ccbe6d6` | `docs(navimow): record standalone mqtt episode publication` |
| 3 | `e866844ab8d1dec062c775c5810310e31d590cfb` | `docs(navimow): validate mqtt episode metadata` |
| 4 | `d473467dbefb53d94fba0a1e43514f3b54cdcb30` | `fix(navimow): finalize account instance status` |
| 5 | `f583f8c99d9cb54dcda0c41612828b600d3fbb0f` | `docs(navimow): canonicalize account status correction evidence` |

`origin/main` at `2ef7a22` is the exact merge base and an ancestor of the
candidate head.

## 4. Complete Branch Scope

Classification against `origin/main`:

| Class | Paths |
|---|---:|
| Navimow reports | 15 |
| Navimow README | 1 |
| productive Account module | 1 |
| Navimow tests | 2 |
| paths outside `case-studies/navimow/` | 0 |
| **Total** | **19** |

Productive and test delta:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/harness/SymconRuntime.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
```

The productive delta is the five-line explicit successful Account status
finalization already published and verified as standalone commit `eda4945`.
The test delta covers the successful lifecycle terminal paths and preserves
the existing MQTT retry, authority and receive-only contracts.

The 15 reports cover steps 258 through 272, with step 257 receiving a bounded
four-line status update. No shared helper, ControlLight, Open-Meteo, deployment
or generated cross-case-study artifact differs.

## 5. Preserved Architecture

Every publication gate must preserve:

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT default:                  disabled
MQTT publish path:             absent
MQTT mower-command path:       absent
reconnect delays:              60 / 300 / 900 seconds
maximum reconnect attempts:    3
Account variables:             6
Device variables:              8
Archive Control contracts:     5
pilot summary format:          1
pilot summary maximum:         16384 bytes
```

No publication operation may change standalone source, installed Symcon state,
OAuth state, MQTT transport state, variables, profiles or archive logging.

## 6. Toolchain and Workstream Contract

Publication preparation must comply with the merged SAEF workstream rules:

- use only the dedicated clean Navimow worktree;
- freshly fetch `origin/main` before every remote gate;
- do not use dirty ControlLight or Statistics reconstruction worktrees as
  source, build, package or validation input;
- verify that no path outside `case-studies/navimow/` enters the branch delta;
- keep private probes and machine-readable live evidence outside the public
  commit set;
- check MCP transport, execution and truncation separately only if a later
  live gate is separately authorized.

The validation toolchain must be explicit. Either:

1. use dependencies installed inside the clean worktree from its lock file; or
2. use an external Composer tool provider only after proving byte-identical
   `composer.lock` files and a zero install/update/remove delta.

The second method is validation-tool reuse, not a mixed-checkout build. Source,
configuration, analyzed paths and working directory must remain in the clean
Navimow worktree. The report must not summarize this as a successful literal
worktree-local `make check` if the fixed local `vendor/` path was absent.

No generated publication artifact may be produced from an external dirty
checkout.

## 7. Gate P0: Local Publication Readiness

Gate P0 is read-only and must pass immediately before any push:

1. fresh `git fetch origin` succeeds;
2. worktree and index are clean;
3. current branch is `codex/navimow-standalone-readiness`;
4. head is exactly `f583f8c99d9cb54dcda0c41612828b600d3fbb0f` unless a documented mainline
   integration was required;
5. `origin/main` remains an ancestor;
6. the five-commit sequence and 19-path allowlist are exact;
7. the branch has no path outside `case-studies/navimow/`;
8. productive Account SHA-256 and Git blob match the accepted candidate;
9. focused Navimow checks pass;
10. complete repository validation passes through a documented toolchain;
11. privacy and receive-only scans pass;
12. `git diff --check origin/main..HEAD` reports no error.

Gate P0 creates no commit and performs no remote or live mutation.

### Gate-P0 stop conditions

Stop if:

- the branch is dirty or detached;
- an unexplained commit or path appears;
- productive source or tests drift from `d473467`;
- report content drifts from `f583f8c`;
- a validation, privacy or receive-only check fails;
- the toolchain source cannot be proven;
- `origin/main` is no longer an ancestor and the integration policy below has
  not completed.

## 8. Mainline Advance Policy

If `origin/main` advances before push or PR creation:

1. stop the publication gate;
2. inventory the new mainline commits and active workstreams;
3. merge current `origin/main` into the dedicated Navimow branch without
   rebasing or squashing the five frozen commits;
4. resolve only genuine Navimow conflicts;
5. verify that the merge introduces no unexplained Navimow semantic delta;
6. repeat the 19-path classification against the new base;
7. rerun focused and complete validation;
8. document the merge commit and updated branch head before publication.

Rebase and squash are prohibited because steps 267 through 271 bind evidence
to `d473467` and `f583f8c`.

The merge itself would require separate authorization if it changes the local
branch.

## 9. Gate P1: Branch Push

Gate P1 requires separate explicit authorization.

Recommended wording:

```text
Push des Branches codex/navimow-standalone-readiness für die abgeschlossene
Navimow-Episoden- und Account-Status-Arbeit freigegeben.
```

Gate P1 permits only:

1. repeat Gate P0;
2. prove the remote branch state with fresh read-only Git access;
3. push the current branch head once with upstream tracking;
4. fetch the remote branch again;
5. verify local and remote head equality;
6. verify the remote branch is still based on current `origin/main`;
7. record private machine-readable push evidence.

No force push is permitted. An ambiguous push result is resolved by read-only
remote comparison and never by a blind retry.

Gate P1 creates no pull request and performs no merge or live operation.

## 10. Gate P2: Pull Request

Gate P2 requires separate authorization after Gate P1 passes.

Recommended wording:

```text
Erstellung eines Pull Requests für codex/navimow-standalone-readiness gegen
SAEF-main freigegeben.
```

Recommended PR identity:

```text
title: feat(navimow): close mqtt episode and account status recovery
base:  main
head:  codex/navimow-standalone-readiness
mode:  ready for review
```

The PR description must explain:

- the five-commit structure;
- the 19-path Navimow-only scope;
- cumulative MQTT episode diagnostics remain receive-only;
- REST remains authoritative;
- the Account status correction is already published and live-verified;
- variables and archive identities are preserved;
- MQTT remains disabled after the correction;
- external toolchain provenance where applicable;
- no shared helper or ControlLight source delta;
- all remaining live gates are outside the PR.

Gate P2 may create the PR only. It must not merge, retag, publish standalone
source or access Symcon.

## 11. Gate P3: Review and Checks

After PR creation:

1. inspect the rendered PR file list and commit list;
2. verify exactly the expected Navimow scope;
3. wait for all GitHub checks to reach a terminal state;
4. inspect failures by log and classify candidate versus infrastructure errors;
5. address review findings only through a separately reviewed local change;
6. re-run affected focused and repository gates after any change;
7. refresh `origin/main` before merge readiness is declared.

An absent CI workflow is not a pass. Local validation and exact GitHub diff
review remain required evidence.

## 12. Gate P4: Merge

Merge requires separate explicit authorization after Gate P3 passes.

Recommended wording:

```text
Merge des geprüften Navimow-Pull-Requests in SAEF-main freigegeben.
```

Before merge:

- PR head must equal the verified remote branch head;
- base must be current `main`;
- all required checks must pass;
- no unresolved review thread may remain;
- the file and commit scopes must remain unchanged;
- branch protection and mergeability must be green.

After merge:

1. fetch `origin/main`;
2. resolve the merge commit from the remote;
3. verify the five frozen commits are ancestors of remote main;
4. verify the complete expected Navimow tree and productive blob;
5. create a sanitized SAEF merge-verification report;
6. leave branch deletion and worktree cleanup as separate destructive gates.

Merge authorization does not authorize Symcon, MQTT, standalone or device
operations.

## 13. Rollback and Retention

No automatic Git rollback is planned.

Until merge verification closes:

- retain the clean Navimow worktree;
- retain private publication and live evidence;
- retain the standalone source identities `a8481c9` and `eda4945`;
- retain branch commits `d473467` and `f583f8c`;
- do not remove historical worktrees as part of this workstream.

A faulty remote branch can be corrected only through a reviewed follow-up
commit or separately authorized branch replacement. Force push, branch
deletion and worktree removal are not rollback shortcuts.

## 14. Architecture Decisions

### AD-NAV-1095: Publish the complete five-commit chain

The chain preserves independently reviewed MQTT publication, metadata,
productive correction and final evidence boundaries.

### AD-NAV-1096: Name the full PR scope

The branch closes both MQTT episode-accounting evidence and Account status
recovery. Describing it as only an Account report would understate the review
surface.

### AD-NAV-1097: Prohibit squash and rebase

Existing evidence binds productive and documentation identities to
`d473467` and `f583f8c`.

### AD-NAV-1098: Separate push, PR and merge authorization

Each operation changes different remote state and receives its own preflight,
evidence and user gate.

### AD-NAV-1099: Preserve clean-worktree validation provenance

External tools are acceptable only with lock identity and zero dependency
delta; dirty checkout source is never acceptable.

### AD-NAV-1100: Merge advancing main without rewriting evidence

A mainline advance is integrated by a newly audited merge commit so frozen
commit identities remain valid.

### AD-NAV-1101: Treat missing CI as missing evidence

No GitHub check is inferred from local success. The PR gate records the actual
remote check surface.

### AD-NAV-1102: Keep cleanup destructive and separate

Branch deletion, worktree removal and private-evidence retention decisions
occur only after remote merge verification.

## 15. Safety Result

This planning step performs:

```text
local commits:           0
repository pushes:      0
pull requests:          0
merges:                 0
branch deletions:       0
worktree removals:      0
standalone changes:     0
Symcon reads:           0
Symcon mutations:       0
MQTT activations:       0
credential requests:    0
OAuth actions:          0
service restarts:       0
mower commands:         0
```

## 16. Gate Status

| Gate | Status |
|---|---|
| local five-commit candidate | PASS |
| Navimow-only branch scope | PASS |
| workstream isolation | PASS |
| Gate P0 final local readiness | CLOSED |
| Gate P1 branch push | CLOSED |
| Gate P2 pull request | CLOSED |
| Gate P3 review and checks | CLOSED |
| Gate P4 merge | CLOSED |
| branch/worktree cleanup | CLOSED |
| standalone publication | NO CHANGE PLANNED |
| Symcon or MQTT operation | CLOSED |
| mower command | CLOSED |

## 17. Next Step

Proceed with:

```text
273-navimow-account-status-correction-saef-push-readiness.md
```

That step should execute Gate P0 only: refresh remote references, prove the
exact branch identity and scope, run the documented validation gates and
return a conditional push recommendation. It must not push or create a pull
request without another explicit authorization.
