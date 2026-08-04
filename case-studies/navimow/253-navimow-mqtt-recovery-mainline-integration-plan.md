# 253 Navimow MQTT Recovery Mainline Integration Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Recovery inventory verified; local mainline integration, push, PR,
standalone publication and all live gates remain separately closed

**Date:** 2026-08-04

**Scope:** Plan the conflict-controlled integration and renewed review of the
recovered receive-only MQTT workstream before any publication

## 1. Purpose

Steps 94 through 252 were recovered from a previously mixed checkout into one
clean, dedicated Navimow workstream. The recovered commit is complete and has
passed its local checks, but it is based on the mainline state immediately
before the latest merged SAEF change.

The publication sequence defined in step 252 therefore cannot proceed directly.
The recovered work must first be integrated with current `origin/main`, reviewed
as one explicit 207-file scope and validated again from its clean worktree.

This step defines that integration. It does not execute a merge, push a branch,
open a pull request, publish the standalone module or access Symcon.

## 2. Verified Starting State

Read-only repository inspection established:

```text
canonical main:    7358fa5878869ff43ad30282f744bf78950c081a
recovery branch:   codex/navimow-mqtt-recovery-clean
recovery commit:   f9eb640004fa8cb5645defa4097d71d6b122285c
merge base:        fd57c68617d09f7fceae03a2274d4a780073644d
recovery status:   clean
recovered paths:   207
```

The changes on current `main` after the merge base touch three non-Navimow
paths. The recovered commit touches only Navimow paths. Their path overlap is:

```text
overlapping paths: 0
```

This makes a conflict-free merge likely, but it is not permission to skip the
post-merge semantic review.

## 3. Recovered Scope Inventory

The 207 paths consist of:

| Review bucket | Paths |
|---|---:|
| numbered SAEF reports | 160 |
| Navimow case-study index | 1 |
| installable distribution | 17 |
| sanitized fixtures | 13 |
| forum documentation | 2 |
| offline tests | 11 |
| case-study tools | 3 |
| **Total** | **207** |

Git change classes are:

```text
added:     193
modified:   14
deleted:     0
```

No path outside `case-studies/navimow/` belongs to the recovered commit.

## 4. Preserved Product Boundary

Every integration and review gate must preserve:

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT publish path:             absent
mower commands over MQTT:      absent
feature default:               disabled
reconnect delays:              60 / 300 / 900 seconds
maximum reconnect attempts:    3
device variables:              14
Archive Control contracts:     5
pilot summary maximum:         16384 bytes
```

The integration must not introduce a shared-helper, bundle, deployment-channel
or restart-boundary change. The workstream remains owned entirely by the
Navimow case study.

## 5. Superseded Publication Transition

Step 252 originally named direct standalone publication as step 253. That
transition is superseded by the recovery and workstream-coordination evidence.

The frozen one-file standalone candidate remains useful evidence, but its
hashes are planning-time values until they are reproduced after current
mainline integration.

No standalone publication may use:

- the removed mixed recovery checkout;
- an unintegrated pre-mainline branch;
- a dirty worktree;
- hashes copied from step 252 without post-merge reproduction.

## 6. Gate I: Local Mainline Integration

Gate I requires separate explicit authorization. It permits only local Git
integration and validation in the dedicated Navimow worktree.

Recommended wording:

```text
Lokale Integration von origin/main in den sauberen Navimow-Recovery-Branch freigegeben.
```

### 6.1 Fresh preconditions

Immediately before the merge:

1. fetch and prune `origin` read-only;
2. verify the recovery worktree is clean;
3. verify the checked-out branch is exactly
   `codex/navimow-mqtt-recovery-clean`;
4. verify `HEAD` is the reviewed recovery commit;
5. record the freshly fetched `origin/main` commit;
6. recompute merge base and path overlap;
7. stop if any recovered path is no longer Navimow-owned;
8. stop if a new mainline overlap appears until it is reviewed.

### 6.2 Integration method

Use a normal merge of freshly fetched `origin/main` into the recovery branch.
Do not rebase or rewrite the recovery commit. The merge preserves the recovery
provenance and avoids a force-push requirement.

If Git reports any conflict:

- stop without choosing either side automatically;
- record the exact conflicting paths;
- leave publication and push closed;
- open a separate conflict-resolution decision.

### 6.3 Mutation boundary

Gate I permits:

```text
local merge commits:  at most 1
branch push:          0
pull request:         0
standalone publish:   0
Symcon mutations:     0
MQTT activation:      0
```

## 7. Post-Merge Scope Review

A conflict-free merge is accepted only after the effective branch diff against
the newly integrated `origin/main` is reviewed again.

Required checks:

- exactly 207 Navimow paths remain in scope unless a difference is explained;
- no deletion appears;
- every path remains below `case-studies/navimow/`;
- the seven review buckets reconcile to the complete path list;
- all 160 numbered reports form a continuous and correctly linked sequence;
- the README index contains every report exactly once;
- distribution, tests, fixtures and tools agree on module contracts;
- no generated or local filesystem artifact entered the branch;
- no private installation value entered a public file.

Any unexplained path-count or ownership drift blocks the workstream before
tests or publication preparation are treated as authoritative.

## 8. Productive Distribution Review

The 17 distribution paths require a complete semantic review, not only a hash
comparison. It must verify:

- all module metadata and prefixes remain coherent;
- Account, Configurator, Device and Receiver ownership is unchanged;
- REST remains the sole public-state authority;
- MQTT remains receive-only;
- no MQTT publish API or device-command route exists;
- retry, credential and cleanup boundaries remain bounded;
- `NAVAC_GetMqttPilotSummary()` remains additive and at most 16384 bytes;
- variables, profiles, forms, locale and archive contracts remain stable;
- no private endpoint, topic, identity or credential is embedded.

The existing official metadata-validator gate remains separate from this local
review.

## 9. Test and Privacy Gate

After the local merge, all of the following must pass from the clean recovery
worktree:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
php private Navimow pilot harness offline test
make check
git diff --check against origin/main
```

In addition, run bounded scans for:

- credentials and Authorization values;
- private hosts, IP addresses and MQTT topics;
- personal ObjectIDs and device identities;
- local absolute paths and `.DS_Store`;
- MQTT publish or write-capable transport paths;
- files outside the declared Navimow scope.

Private harness paths and private evidence remain local and must not become part
of the public 207-file branch scope.

## 10. Candidate Refreeze

Only after the post-merge review and complete test gate may the standalone
candidate be frozen again.

Required reproduction:

1. compare the integrated distribution recursively with a freshly fetched,
   clean standalone `symcon-navimow/main`;
2. identify every differing productive path;
3. require the expected one-file Account delta or return to design review;
4. recompute SHA-256 and Git blob identities;
5. recompute insertion and deletion counts;
6. bind all supporting test and private-harness hashes;
7. update the future publication plan with the new integrated branch commit.

Step-252 hashes must not be silently reused even when the byte values happen to
remain equal.

## 11. Gate II: SAEF Branch Push and Pull Request

Gate II starts only after Gate I and candidate refreeze pass. It requires a new
explicit authorization because it publishes the recovered workstream to the
SAEF remote.

Gate II may:

- push only `codex/navimow-mqtt-recovery-clean`;
- open one reviewable pull request against SAEF `main`;
- report the exact branch, commits, 207-path scope and successful checks.

Gate II may not:

- merge the pull request;
- push directly to SAEF `main`;
- modify the standalone Navimow repository;
- access Symcon;
- activate MQTT or command the mower.

PR merge is a later user-owned or separately authorized decision.

## 12. Gate III: Canonical Mainline Verification

After the SAEF pull request is merged, a clean current `main` must prove:

- the intended Navimow commit ancestry is present;
- all 207 paths are represented without unrelated workstream content;
- focused Navimow checks and `make check` still pass;
- the standalone candidate can be reproduced byte-for-byte;
- the case-study sequence and gate statuses are current.

Only this canonical-main result can become the source for the later standalone
publication gate.

## 13. Later Standalone Publication

Standalone publication remains separately closed. It will receive a new SAEF
step and must retain the step-252 one-file publication controls:

- fresh standalone fetch and clean-baseline proof;
- exact candidate and baseline hashes;
- one-file copy only;
- complete validation and privacy scans;
- one Conventional Commit and one fast-forward push;
- no tag, release, Symcon access or MQTT activation.

Metadata validation, disabled Symcon update and any later MQTT pilot remain
separate gates after publication.

## 14. Stop Conditions

Stop the integration workflow if:

- either worktree or branch identity is unexpected;
- current `origin/main` differs from the reviewed precondition without renewed
  reconciliation;
- a merge conflict occurs;
- any file leaves the Navimow scope;
- the effective path count changes without explanation;
- a deletion appears;
- tests, PHPStan, distribution validation or privacy checks fail;
- REST authority or receive-only MQTT boundaries drift;
- candidate reproduction differs from the reviewed one-file model;
- push, PR, standalone publication or live work would exceed the current gate.

Do not repair an unexpected condition by resetting, rebasing, force-pushing or
discarding the recovered commit.

## 15. Evidence Closure

Each executed gate must record:

- exact pre- and post-integration commits;
- merge base and path-overlap result;
- clean-worktree status;
- effective path inventory and review buckets;
- focused and complete test results;
- privacy and receive-only scans;
- refrozen standalone candidate hashes;
- exact mutation and publication counts;
- all still-closed downstream gates.

Public evidence contains repository identities and aggregate counts only.
Local paths, installation metadata and private transport evidence remain under
the private overlay.

## 16. Architecture Decisions

### AD-NAV-955: Integrate current main before publication

The recovered workstream predates the latest canonical mainline change. A
publication source must be reproducible from a current clean base.

### AD-NAV-956: Preserve the recovery commit with a normal merge

A merge retains recovery provenance and avoids history rewriting or a future
force-push requirement.

### AD-NAV-957: Treat zero path overlap as evidence, not acceptance

No textual overlap reduces conflict risk but does not replace semantic,
contract or repository-wide validation.

### AD-NAV-958: Review all 207 paths by explicit bucket

The large recovery scope is accepted only when documentation, distribution,
fixtures, tests and tools reconcile independently to the complete inventory.

### AD-NAV-959: Keep the workstream Navimow-owned

No shared helper, bundle, deployment or unrelated case-study change belongs to
this recovery branch.

### AD-NAV-960: Refreeze hashes after integration

Planning-time hashes cannot authorize publication until they are reproduced
from the integrated clean worktree.

### AD-NAV-961: Merge locally before publishing the branch

Local integration and validation are reversible preparation. Branch push and
pull-request creation remain a separate remote-publication gate.

### AD-NAV-962: Require canonical SAEF main before standalone publication

The standalone module must be derived from reviewed canonical SAEF source, not
from an unmerged recovery branch.

### AD-NAV-963: Preserve all downstream live gates

Mainline integration does not authorize metadata validation, Symcon update,
credential retrieval, MQTT activation, restart or mower commands.

### AD-NAV-964: Stop rather than auto-resolve conflicts

Any conflict may represent a cross-workstream contract change and requires an
explicit engineering decision.

## 17. Gate Status

| Gate | Status |
|---|---|
| recovery inventory | PASS |
| current-main path-overlap review | PASS, zero overlap |
| Gate I local mainline integration | CLOSED |
| post-merge 207-path review | CLOSED |
| complete post-merge tests | CLOSED |
| candidate refreeze | CLOSED |
| Gate II branch push and PR | CLOSED |
| Gate III canonical-main verification | CLOSED |
| standalone publication | CLOSED |
| metadata validation | CLOSED |
| Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |
| mower command | NOT PLANNED |

## 18. Next Step

After explicit Gate-I authorization, proceed with:

```text
254-navimow-mqtt-recovery-mainline-integration-and-refreeze.md
```

That step may perform only the local merge, complete scope review, tests and
candidate refreeze. It must not push, open a pull request, publish the
standalone module or access Symcon.
