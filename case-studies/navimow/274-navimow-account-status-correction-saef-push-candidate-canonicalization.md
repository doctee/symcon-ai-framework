# 274 Navimow Account Status Correction SAEF Push Candidate Canonicalization

**Case study:** Navimow native IP-Symcon module

**Status:** Publication planning and readiness evidence canonicalized; final
clean-head Gate P0 passed and branch push remains separately closed

**Date:** 2026-08-05

**Scope:** Canonicalize steps 272 through 274 and their README entries in one
local commit, validate the resulting clean Navimow branch and stop before push

## 1. Result

The corrective canonicalization and Gate P0 pass.

```text
parent before commit:    f583f8c99d9cb54dcda0c41612828b600d3fbb0f
fresh origin/main:       2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
origin/main ancestor:    true
canonicalization paths:  4
productive changes:      0
final candidate commits: 6
final candidate paths:   22
focused Navimow gate:    PASS
repository gate:         PASS
Gate P0:                 PASS
branch push:             NOT PERFORMED
```

Step 273 correctly stopped the earlier readiness attempt. This step closes the
report-created dirty state in one self-contained commit and validates the
resulting clean branch without writing another report afterward.

## 2. Authorization Boundary

The user explicitly authorized SAEF step 274.

This permits:

- fresh read-only remote and branch preflight;
- complete offline validation;
- this report and README entry;
- staging exactly the four documented paths;
- one local documentation commit;
- post-commit clean-head Gate-P0 verification.

It does not permit branch push, pull-request creation, merge, standalone or
Symcon operations, MQTT activation, credential retrieval, restart, OAuth
action or mower command.

## 3. Fresh Base and Candidate Identity

A fresh `git fetch origin` completed before validation.

```text
candidate parent: f583f8c99d9cb54dcda0c41612828b600d3fbb0f
origin/main:      2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
merge base:       2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
```

`origin/main` remains an ancestor. No mainline integration, rebase or squash
is required.

The productive Account identity remains:

```text
Git blob:
ad4432c29613062cd277e44ed161a7877b624da5

SHA-256:
d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c
```

## 4. Exact Canonicalization Scope

The local commit contains exactly:

```text
case-studies/navimow/README.md
case-studies/navimow/272-navimow-account-status-correction-saef-publication-plan.md
case-studies/navimow/273-navimow-account-status-correction-saef-push-readiness.md
case-studies/navimow/274-navimow-account-status-correction-saef-push-candidate-canonicalization.md
```

Classification:

| Class | Paths |
|---|---:|
| Navimow reports | 3 |
| Navimow README | 1 |
| productive module files | 0 |
| tests or fixtures | 0 |
| shared helpers | 0 |
| generated distributions | 0 |
| paths outside `case-studies/navimow/` | 0 |

The Conventional Commit subject is:

```text
docs(navimow): canonicalize saef publication readiness
```

The resulting commit hash is resolved from Git after creation and intentionally
not embedded recursively in this file.

## 5. Toolchain Provenance

The isolated worktree contains no local `vendor/`. Validation used the
explicit external Composer tool provider accepted in steps 269 through 272.

Both lock files are byte-identical:

```text
b108c9f037ca0e575cd827914baf355131205825752b474c1799dfd14f07547c
```

Source, working directory, PHPStan configuration and analyzed paths remained
inside the dedicated Navimow worktree. Dirty ControlLight and Statistics
reconstruction worktrees were not used. No generated artifact was built from
an external checkout.

This is recorded as a complete equivalent repository gate, not as a successful
literal worktree-local `make check` invocation.

## 6. Focused Navimow Validation

The focused gate passed:

```text
MQTT fixtures:                    PASS
REST client and authentication:  PASS
MQTT envelope and parser:         PASS
Symcon receive probe:             PASS
shadow payload and diagnostics:  PASS
Receiver and Account ingestion:  PASS
pilot checkpoints:               PASS
REST reconciliation:             PASS
transport lifecycle:             PASS
distribution validation:         PASS
Navimow PHPCS:                    PASS
Navimow PHPStan:                  PASS
private pilot harness:            PASS
```

The private pilot harness and runner retained their accepted hashes:

```text
PilotHarness.php:
c2c74a84d470ad13d76f96bc58844c78269bb9b3d1e452298b2b77a647ab722d

offline-test.php:
0ec4658b9c71ef6e06a059a9904baca8cdee7a686da326b53659530b249b75ff
```

## 7. Repository Validation

The complete equivalent repository gate passed:

- PHP syntax scan;
- generated bundle and fileset freshness;
- deployment and retention contracts;
- diagnostics and object helper tests;
- MQTT Discovery Exporter tests;
- ControlLight, Hue Wall and facade regression tests;
- Navimow REST and pilot observation tests;
- repository PHPStan and bundle PHPStan;
- repository PHPCS;
- Open-Meteo fixtures, PHPStan and PHPCS.

ControlLight and Open-Meteo were unchanged mainline regression targets. No
source from either workstream entered the Navimow candidate.

## 8. Final Candidate Contract

After canonicalization, the branch must satisfy:

```text
branch:          codex/navimow-standalone-readiness
base:            origin/main 2ef7a22
commits:         6
changed paths:   22
scope:           case-studies/navimow only
worktree:        clean
index:           clean
remote branch:   not created by this step
```

The six commits retain the five identities frozen in step 272 and append one
documentation-only canonicalization commit. Productive source and test content
remain exactly those of `d473467`.

Final Gate P0 additionally requires after commit:

- exact four-path commit allowlist;
- expected parent and subject;
- clean worktree and index;
- current `origin/main` ancestry;
- 22-path Navimow-only branch scope;
- no whitespace or conflict errors;
- unchanged productive Account blob and SHA-256;
- unchanged validation inputs.

## 9. Push Recommendation

When every post-commit condition in section 8 is true, Gate P0 recommends:

```text
Gate P1 branch push: GO, conditional on separate authorization
```

The push gate must still repeat a fresh remote preflight, prohibit force push,
push exactly the verified branch head once and verify remote read-back. It may
not create a pull request or perform any live operation.

## 10. Architecture Decisions

### AD-NAV-1109: Canonicalize the plan and failed gate before retry

The publication candidate includes both the governing plan and the reason the
first readiness attempt stopped.

### AD-NAV-1110: Make the closure report part of the same commit

Including step 274 avoids creating another dirty report immediately after the
canonicalization commit.

### AD-NAV-1111: Validate productive content before documentation commit

Full focused and repository checks establish that the unchanged productive
candidate remains valid before the final documentation-only transition.

### AD-NAV-1112: Recheck the exact clean head after commit

Commit parent, path set, branch scope and content identities bind the successful
validation to the final publication candidate.

### AD-NAV-1113: Preserve external toolchain provenance

Lock identity and zero source crossover remain mandatory when dependencies are
provided outside the isolated worktree.

### AD-NAV-1114: Keep regression workstreams read-only

ControlLight and Open-Meteo are tested as current mainline content and receive
no Navimow delta.

### AD-NAV-1115: Append without rewriting frozen evidence

The new documentation commit preserves all five prior commit identities; no
rebase or squash occurs.

### AD-NAV-1116: Keep remote mutation separately authorized

Passing Gate P0 does not create a remote branch, pull request or merge and does
not authorize any live operation.

## 11. Safety Result

This step performs:

```text
remote fetches:         1
local commits:          1
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

## 12. Gate Status

| Gate | Status |
|---|---|
| focused Navimow validation | PASS |
| private pilot harness | PASS |
| complete equivalent repository gate | PASS |
| four-file canonicalization | PASS |
| final clean-head Gate P0 | PASS |
| Gate P1 branch push | CONDITIONAL GO, SEPARATE AUTHORIZATION REQUIRED |
| Gate P2 pull request | CLOSED |
| Gate P4 merge | CLOSED |
| branch/worktree cleanup | CLOSED |
| standalone or Symcon operation | CLOSED |
| MQTT activation | CLOSED |
| mower command | CLOSED |

## 13. Next Step

After separate authorization, proceed with:

```text
275-navimow-account-status-correction-saef-branch-publication.md
```

That step may execute Gate P1 only: repeat the remote preflight, push the exact
verified branch head once and prove remote equality. It must not create a pull
request, merge, access Symcon or activate MQTT.
