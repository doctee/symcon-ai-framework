# 275 Navimow Account Status Correction SAEF Branch Publication

**Case study:** Navimow native IP-Symcon module

**Status:** Exact six-commit Navimow branch published and remotely verified;
pull request and merge remain closed

**Date:** 2026-08-05

**Scope:** Execute Gate P1 by pushing the exact Gate-P0-approved branch head
once, verifying remote equality and stopping before pull-request creation

## 1. Result

Gate P1 passed.

```text
branch:          codex/navimow-standalone-readiness
local head:      a26bbff5fce2c56fb2c9d0acb1d716087ecadd49
remote head:     a26bbff5fce2c56fb2c9d0acb1d716087ecadd49
remote main:     2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
commits:         6
changed paths:   22
paths outside Navimow: 0
branch pushes:   1
push retries:    0
force pushes:    0
decision:        PASS
```

The remote branch is an exact publication of the clean candidate accepted in
step 274. No pull request was created.

## 2. Authorization Boundary

The user explicitly authorized SAEF step 275.

This permitted:

- fresh remote preflight;
- one normal upstream push of the exact verified branch head;
- fresh fetch and remote hash read-back;
- this sanitized report and README entry.

It did not permit a force push, second push, pull request, merge, tag, release,
branch deletion, worktree removal, standalone change, Symcon access, MQTT
activation, credential retrieval, restart, OAuth action or mower command.

## 3. Pre-Push Gate

Immediately before push:

```text
HEAD:                 a26bbff5fce2c56fb2c9d0acb1d716087ecadd49
origin/main:          2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
origin/main ancestor: true
worktree:             clean
index:                clean
candidate commits:    6
candidate paths:      22
cross-case paths:     0
remote branch:        absent
```

The productive Account artifact matched:

```text
Git blob:
ad4432c29613062cd277e44ed161a7877b624da5

SHA-256:
d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c
```

No mainline advance or candidate drift occurred after step 274.

## 4. Push Operation

Exactly one standard upstream push created:

```text
refs/heads/codex/navimow-standalone-readiness
```

Operation counts:

```text
normal pushes:       1
push retries:        0
force pushes:        0
tags:                0
releases:            0
pull requests:       0
merges:              0
branch deletions:    0
```

The local branch now tracks
`origin/codex/navimow-standalone-readiness`.

## 5. Remote Read-Back

After push, a fresh fetch and independent branch lookup returned:

```text
refs/heads/codex/navimow-standalone-readiness
a26bbff5fce2c56fb2c9d0acb1d716087ecadd49

refs/heads/main
2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
```

Local and remote verification proved:

- local head equals fetched tracking head;
- fetched tracking head equals the independently read remote head;
- remote `main` remains the exact candidate base;
- remote `main` is an ancestor of the branch;
- the branch contains six commits after main;
- the branch changes exactly 22 paths;
- every changed path is below `case-studies/navimow/`;
- the remote productive Account blob remains exact;
- no whitespace or merge-conflict error appears.

The push result is unambiguous and required no retry.

## 6. Published Commit Chain

| Order | Commit | Subject |
|---:|---|---|
| 1 | `42996e6` | `docs(navimow): review standalone mqtt publication readiness` |
| 2 | `c28c195` | `docs(navimow): record standalone mqtt episode publication` |
| 3 | `e866844` | `docs(navimow): validate mqtt episode metadata` |
| 4 | `d473467` | `fix(navimow): finalize account instance status` |
| 5 | `f583f8c` | `docs(navimow): canonicalize account status correction evidence` |
| 6 | `a26bbff` | `docs(navimow): canonicalize saef publication readiness` |

No commit was rebased, squashed or rewritten.

## 7. Validation Binding

Step 274 executed focused and complete validation twice around the final
documentation commit and accepted exact clean head `a26bbff`.

The final successful gate included:

- all Navimow MQTT and REST fixtures;
- transport lifecycle and pilot checkpoint tests;
- distribution validation;
- private pilot harness;
- Navimow PHPCS and PHPStan;
- complete Composer check;
- repository PHPStan and PHPCS;
- ControlLight, Hue Wall and helper regressions;
- Open-Meteo fixtures, PHPStan and PHPCS.

One initial final Composer attempt encountered its global 300-second process
timeout in an unchanged MQTT Discovery Exporter fileset test. The isolated test
passed immediately. The unchanged complete gate then passed with a documented
900-second process budget. This was classified as process orchestration, not a
candidate defect.

Push and fetch do not change Git content, so no code test was repeated after
remote equality reproduced the exact accepted head.

## 8. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT default:                  disabled
MQTT publish path:             absent
MQTT mower-command path:       absent
Account variables:             6
Device variables:              8
Archive Control contracts:     5
pilot summary format:          1
pilot summary maximum:         16384 bytes
```

Branch publication changes source availability only. It does not change the
already installed Symcon commit or transport state.

## 9. Evidence Canonicalization Boundary

This report and its README entry were created only after remote read-back.
They are therefore not part of remote head `a26bbff`.

Before pull-request creation, a separate step should:

1. add its own closure report;
2. canonicalize README plus steps 275 and 276 in one documentation-only local
   commit;
3. verify the resulting clean seven-commit branch;
4. repeat the branch push preflight;
5. publish the evidence commit through one fast-forward push;
6. verify remote equality again;
7. stop before pull-request creation.

This additional push requires separate authorization. It must not amend,
rebase or squash the six published commits.

## 10. Architecture Decisions

### AD-NAV-1117: Push only the exact Gate-P0 head

The remote branch begins at the fully validated clean candidate, not at a
post-validation reconstruction.

### AD-NAV-1118: Require independent remote hash read-back

Push output alone does not prove the resulting remote identity.

### AD-NAV-1119: Prohibit force and ambiguous retry

A new branch requires only a normal push. Any ambiguous result must be resolved
through read-only remote inspection.

### AD-NAV-1120: Preserve the six-commit evidence chain

No history rewrite may invalidate commit identities already referenced by the
case study.

### AD-NAV-1121: Bind validation to content identity

Remote equality with `a26bbff` carries the exact successful step-274 validation
without rerunning tests after a content-neutral fetch.

### AD-NAV-1122: Canonicalize publication evidence before PR creation

The branch PR should contain the report proving how the branch itself was
published and verified.

### AD-NAV-1123: Keep PR and merge separately authorized

Creating a remote branch does not authorize a pull request, review decision or
mainline mutation.

## 11. Safety Result

This step performs:

```text
remote fetches:         2
branch pushes:          1
push retries:           0
force pushes:           0
local commits:          0
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

## 12. Gate Status

| Gate | Status |
|---|---|
| Gate P0 final clean-head readiness | PASS |
| Gate P1 initial branch push | PASS |
| remote head equality | PASS |
| branch ancestry and scope | PASS |
| branch-publication evidence canonicalization | CLOSED |
| evidence fast-forward push | CLOSED |
| Gate P2 pull request | CLOSED |
| Gate P3 review and checks | CLOSED |
| Gate P4 merge | CLOSED |
| branch/worktree cleanup | CLOSED |
| standalone or Symcon operation | CLOSED |
| MQTT activation | CLOSED |
| mower command | CLOSED |

## 13. Next Step

Proceed with:

```text
276-navimow-account-status-correction-saef-branch-publication-evidence-canonicalization.md
```

That step may create one local documentation-only evidence commit and publish
it through one fast-forward push after separate authorization. It must stop
before pull-request creation and perform no live operation.
