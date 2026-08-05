# 276 Navimow Account Status Correction SAEF Branch Publication Evidence Canonicalization

**Case study:** Navimow native IP-Symcon module

**Status:** Initial branch-publication evidence canonicalized and published by
fast-forward; pull request and merge remain closed

**Date:** 2026-08-05

**Scope:** Canonicalize steps 275 and 276 plus their README entries in one local
documentation commit, publish it once by fast-forward and verify remote branch
equality without creating a pull request

## 1. Result

The evidence canonicalization and publication pass.

```text
branch:                  codex/navimow-standalone-readiness
parent before commit:    a26bbff5fce2c56fb2c9d0acb1d716087ecadd49
origin/main:             2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
canonicalization paths:  3
productive changes:      0
final branch commits:    7
final branch paths:      24
local commits:           1
fast-forward pushes:     1
pull requests:           0
decision:                PASS
```

The remote branch now carries both its initial publication evidence and this
canonicalization record. No prior commit was amended, rebased or squashed.

## 2. Authorization Boundary

The user explicitly authorized SAEF step 276.

This permits:

- fresh remote preflight;
- this report and README entry;
- staging exactly the three documented paths;
- one local documentation commit;
- one normal fast-forward push;
- fresh remote read-back.

It does not permit force push, pull-request creation, merge, tag, release,
branch deletion, worktree removal, standalone or Symcon changes, MQTT
activation, credential retrieval, restart, OAuth action or mower command.

## 3. Fresh Preflight

A fresh fetch proved before editing this report:

```text
local head:       a26bbff5fce2c56fb2c9d0acb1d716087ecadd49
tracking head:    a26bbff5fce2c56fb2c9d0acb1d716087ecadd49
origin/main:      2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
main ancestor:    true
branch commits:   6
branch paths:     22
cross-case paths: 0
```

The only open paths were the expected step-275 report and Navimow README
entry. The index was empty.

The productive Account artifact remained:

```text
Git blob:
ad4432c29613062cd277e44ed161a7877b624da5

SHA-256:
d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c
```

## 4. Exact Commit Scope

The documentation commit contains exactly:

```text
case-studies/navimow/README.md
case-studies/navimow/275-navimow-account-status-correction-saef-branch-publication.md
case-studies/navimow/276-navimow-account-status-correction-saef-branch-publication-evidence-canonicalization.md
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
docs(navimow): canonicalize branch publication evidence
```

The resulting commit hash is resolved directly from Git after creation. It is
not embedded recursively in this file.

## 5. Validation Contract

Before commit:

- the exact three-path worktree allowlist must match;
- the staged index must initially be empty;
- tracked and untracked diff checks must pass;
- ADRs must be unique;
- privacy scan must pass;
- no productive or test path may enter the staged set.

After commit:

- parent and subject must match section 4;
- the exact three-path commit allowlist must match;
- worktree and index must be clean;
- `origin/main` must remain an ancestor;
- the branch must contain seven commits and 24 Navimow-only paths;
- productive Account blob and SHA-256 must remain exact.

Step 274 already validated the unchanged productive and test tree through the
focused Navimow gate, private pilot harness and complete equivalent repository
gate. This documentation-only commit does not repeat code tests. Exact tree
identity and diff scope carry that evidence forward.

## 6. Fast-Forward Publication Contract

The push may proceed only after every post-commit condition in section 5
passes.

Permitted operation:

```text
one normal push of codex/navimow-standalone-readiness
```

Required post-push evidence:

- fresh fetch succeeds;
- local head equals fetched tracking head;
- independent remote lookup equals both;
- remote `main` remains the exact base;
- the remote branch contains seven commits after main;
- the remote branch changes exactly 24 Navimow-only paths;
- the remote productive Account blob remains exact;
- no force push or retry occurred.

An ambiguous result stops for read-only diagnosis and never triggers a blind
second push.

## 7. Pull-Request Boundary

This step intentionally stops after remote branch equality.

The later pull-request step should be authorized to:

1. refresh and verify the final seven-commit remote branch;
2. create one ready-for-review PR against `main`;
3. verify rendered commit and file scope;
4. record the PR identity and initial check state;
5. add a closure report to the same branch through a separately bounded
   documentation commit and fast-forward push;
6. verify that the PR updates to that exact closure head;
7. stop before merge.

Combining PR creation and its closure-report push in one explicitly defined
step prevents another unrecorded publication state while preserving merge as
a separate gate.

## 8. Architecture Decisions

### AD-NAV-1124: Publish initial branch evidence before opening the PR

Reviewers should see how the branch head was created and remotely verified.

### AD-NAV-1125: Use one documentation-only evidence commit

README, publication report and canonicalization report form one exact
three-path unit.

### AD-NAV-1126: Preserve all six prior commits unchanged

The evidence commit appends history and never rewrites productive or reviewed
documentation identities.

### AD-NAV-1127: Carry code validation by exact tree identity

No productive or test blob changes after the complete step-274 gate, so
documentation canonicalization needs structural and privacy checks only.

### AD-NAV-1128: Require a second independent remote read-back

The evidence push receives the same local, tracking and independent remote
identity proof as the initial branch push.

### AD-NAV-1129: Keep PR creation explicitly closed

Publishing branch evidence does not authorize GitHub review state or mainline
integration.

### AD-NAV-1130: Let the PR step close its own publication evidence

The PR step may include a bounded follow-up evidence commit and push only when
that operation is explicitly part of its separate authorization contract.

## 9. Safety Result

This step performs:

```text
remote fetches:         2
local commits:          1
fast-forward pushes:    1
push retries:           0
force pushes:           0
pull requests:          0
merges:                 0
tags or releases:       0
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

## 10. Gate Status

| Gate | Status |
|---|---|
| initial Gate-P1 branch publication | PASS |
| three-file evidence canonicalization | PASS |
| evidence fast-forward push | PASS |
| final remote branch equality | PASS |
| Gate P2 pull request | CLOSED |
| Gate P3 review and checks | CLOSED |
| Gate P4 merge | CLOSED |
| branch/worktree cleanup | CLOSED |
| standalone or Symcon operation | CLOSED |
| MQTT activation | CLOSED |
| mower command | CLOSED |

## 11. Next Step

Proceed with:

```text
277-navimow-account-status-correction-saef-pull-request-publication.md
```

That step may create and remotely verify one ready-for-review pull request,
then append and publish its bounded closure report as defined in section 7
after separate authorization. It must stop before merge and perform no live
operation.
