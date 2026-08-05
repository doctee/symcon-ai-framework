# 271 Navimow Account Status Correction Case-Study Canonicalization

**Case study:** Navimow native IP-Symcon module

**Status:** Navimow-only correction evidence canonicalized in one local
documentation commit; publication gates remain closed

**Date:** 2026-08-05

**Scope:** Canonicalize steps 267 through 270 and their README entries as one
local documentation commit without changing productive source or publishing
the SAEF branch

## 1. Result

The case-study canonicalization passed.

```text
branch:                 codex/navimow-standalone-readiness
fresh origin/main:      2ef7a22
parent before commit:   d473467
origin/main ancestor:   true
documentation reports: 5
README files:           1
productive files:       0
paths outside Navimow:  0
local commits:          1
pushes:                 0
```

The correction evidence is now one reviewable local documentation unit. The
containing commit identity is read from Git after commit creation and cannot
be embedded in this report without recursive self-reference.

## 2. Authorization Boundary

The user explicitly authorized:

```text
SAEF-Schritt 271
navimow-account-status-correction-case-study-canonicalization.md freigegeben.
```

This permits the final local review and exactly one local documentation
commit. It does not authorize push, pull request, merge, standalone
publication, Symcon access, MQTT activation, restart, OAuth action or mower
command.

## 3. Fresh Base Verification

A fresh `git fetch origin` completed before canonicalization.

```text
origin/main: 2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
branch HEAD: d473467dbefb53d94fba0a1e43514f3b54cdcb30
merge base:  2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
```

`origin/main` is an ancestor of the Navimow branch. The branch therefore
retains the merged ControlLight closure and current SAEF workstream governance
established on main.

The older local `main` checkout was not used as source, validation baseline or
commit parent.

## 4. Exact Commit Scope

The local commit contains exactly:

```text
case-studies/navimow/README.md
case-studies/navimow/267-navimow-account-status-correction-standalone-publication.md
case-studies/navimow/268-navimow-account-status-correction-metadata-conformance.md
case-studies/navimow/269-navimow-account-status-correction-disabled-symcon-update.md
case-studies/navimow/270-navimow-account-status-correction-integration-review.md
case-studies/navimow/271-navimow-account-status-correction-case-study-canonicalization.md
```

Classification:

| Class | Count |
|---|---:|
| Navimow case-study reports | 5 |
| Navimow README | 1 |
| productive module files | 0 |
| tests or fixtures | 0 |
| shared helpers | 0 |
| generated distributions | 0 |
| paths outside `case-studies/navimow/` | 0 |

The productive Account correction was already canonicalized in commit
`d473467` and is not duplicated in this documentation commit.

## 5. Evidence Consolidation

The committed reports close one continuous evidence chain:

| Step | Evidence |
|---|---|
| 267 | exact five-line standalone publication and remote read-back |
| 268 | exact published metadata conformance |
| 269 | one supported disabled Symcon update and stable status `102` |
| 270 | workstream, private-evidence and toolchain isolation review |
| 271 | local Navimow-only documentation canonicalization |

The reports preserve the governing boundaries:

```text
REST authority:          preserved
MQTT direction:          receive-only
MQTT default:            disabled
MQTT credentials:        absent after update
variable identities:     preserved
Archive contracts:       preserved
device commands:         none in steps 267 through 271
```

## 6. Documentation Hardening Included

The canonical unit includes the two clarifications accepted in step 270:

1. The public publication report identifies a dedicated clean Navimow
   worktree without exposing its private repository-relative path.
2. The live-update report distinguishes the incomplete literal worktree-local
   `make check` invocation from the successful complete equivalent gate using
   a byte-identical Composer lock and explicitly external tool provider.

No public report contains an absolute installation path, ObjectID, credential,
token, private MQTT topic, hostname, payload, device identity or garden data.

## 7. Validation

The pre-commit documentation gate requires:

```text
fresh origin/main ancestry:       PASS
exact six-file staged set:        PASS
Navimow-only path scope:          PASS
productive-source exclusion:      PASS
privacy scan:                     PASS
ADR uniqueness:                   PASS
tracked diff check:               PASS
untracked report diff checks:     PASS
merge-conflict scan:              PASS
```

The code, module distribution and runtime behavior were not changed in this
step. The complete equivalent repository gate recorded in steps 269 and 270
therefore remains the applicable code-validation evidence; no live or device
test was repeated for documentation-only canonicalization.

## 8. Commit Identity Contract

The local commit is constrained by:

- parent `d473467dbefb53d94fba0a1e43514f3b54cdcb30`;
- exactly the six paths listed in section 4;
- no staged path outside `case-studies/navimow/`;
- no productive module, test, fixture, helper or distribution delta;
- Conventional Commit subject
  `docs(navimow): canonicalize account status correction evidence`.

The final commit hash is intentionally not written into this file. The next
step must resolve it directly from Git and verify the parent, subject and
changed-path set before considering publication.

## 9. Architecture Decisions

### AD-NAV-1089: Canonicalize completed evidence separately from productive code

The Account source correction remains in `d473467`; later publication and live
evidence form a documentation-only commit.

### AD-NAV-1090: Refresh origin before local canonicalization

A previously observed remote reference is insufficient when the commit may
later become a publication candidate.

### AD-NAV-1091: Bind the commit to an exact path allowlist

Directory-level staging is accepted only after the staged set equals the six
documented files exactly.

### AD-NAV-1092: Keep historical worktrees outside the commit path

Dirty ControlLight and Statistics reconstruction worktrees remain private
recovery input and are neither read nor normalized by this step.

### AD-NAV-1093: Avoid recursive commit identities in committed reports

The report fixes parent, files and subject. Git provides the resulting commit
identity after creation for the next gate.

### AD-NAV-1094: Preserve separate publication and live gates

A local documentation commit authorizes no push, pull request, merge, Symcon
operation, MQTT lifecycle transition, restart or mower command.

## 10. Safety Result

This step performs:

```text
local documentation commits: 1
repository pushes:           0
pull requests:               0
merges:                      0
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
| Account correction implementation | PASS IN `d473467` |
| standalone publication | PASS |
| metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| corrective disabled Symcon update | PASS |
| integration review | PASS |
| local case-study canonicalization | PASS |
| SAEF branch push | CLOSED |
| pull request or merge | CLOSED |
| MQTT staging or activation | CLOSED |
| service restart | CLOSED |
| mower command | CLOSED |

## 12. Next Step

Proceed with:

```text
272-navimow-account-status-correction-saef-publication-plan.md
```

That step should resolve and verify the local canonicalization commit, review
the complete branch delta against current `origin/main` and define separate
push, pull-request and merge gates. It must perform no publication or live
operation without new explicit authorization.
