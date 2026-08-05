# 266 Navimow Account Status Correction Candidate Canonicalization

**Case study:** Navimow native IP-Symcon module

**Status:** Gate A0 passed; corrective candidate is committed locally and the
standalone publication gate remains closed

**Date:** 2026-08-05

**Scope:** Canonicalize steps 261 through 266 and the Account status correction
as one clean local SAEF candidate without publishing or accessing Symcon

## 1. Result

The Navimow Account status correction is now bound to one locally committed
SAEF workstream state.

```text
productive files changed: 1
productive insertions:    5
productive deletions:     0
test files changed:       2
metadata changes:         0
standalone files changed: 0
repository pushes:        0
Symcon reads:             0
Symcon mutations:         0
```

The canonical commit is the commit containing this report with subject:

```text
fix(navimow): finalize account instance status
```

Its exact commit ID is resolved from Git after commit creation and becomes a
fresh precondition in the separately authorized publication step. The
productive identity does not depend on the commit ID and remains fixed by its
SHA-256 and Git blob.

## 2. Workstream Provenance

```text
worktree:       private/worktrees/navimow-standalone-readiness
branch:         codex/navimow-standalone-readiness
base:           origin/main@2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
pre-Gate HEAD:  e866844ab8d1dec062c775c5810310e31d590cfb
base relation:  origin/main is an ancestor
prior commits:  3
```

The three prior branch commits are the reviewed steps 258 through 260. The
canonicalization commit adds only the deliberate continuation from the blocked
update through the correction publication plan and this closure report.

No merge, rebase, reset or unrelated worktree normalization occurred.

## 3. Canonicalized Scope

Productive and test changes:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/harness/SymconRuntime.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
```

Documentation changes:

```text
case-studies/navimow/README.md
case-studies/navimow/261-native-mqtt-episode-accounting-disabled-symcon-update.md
case-studies/navimow/262-navimow-account-status-101-readonly-analysis.md
case-studies/navimow/263-navimow-account-status-recovery-and-update-gate-design.md
case-studies/navimow/264-navimow-account-status-finalization-implementation.md
case-studies/navimow/265-navimow-account-status-correction-publication-plan.md
case-studies/navimow/266-navimow-account-status-correction-candidate-canonicalization.md
```

No file outside `case-studies/navimow/` belongs to the commit.

## 4. Productive Candidate Identity

| Artifact | SHA-256 | Git blob |
|---|---|---|
| standalone Account baseline | `77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4` | `af1d4dd9094ca10a12f0ee264041ee47b7dc19cb` |
| canonical corrective Account | `d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c` | `ad4432c29613062cd277e44ed161a7877b624da5` |

Standalone-to-candidate delta:

```text
modified paths: 1
added paths:    0
deleted paths:  0
insertions:     5
deletions:      0
```

The correction remains one private constant and four successful terminal
`SetStatus(102)` calls. No lifecycle operation was reordered.

## 5. Test Contract

The shared Symcon harness now models:

```text
parent ApplyChanges status: 101
successful module final:    102
```

The lifecycle suite proves eight successful terminal scenarios, including:

- incomplete configuration;
- authorization pending;
- normal authenticated startup;
- three kernel-reconciliation deferrals;
- active-to-disabled cleanup;
- repeated disabled application.

Existing transport, timer, credential, Registry, variable and idempotency
assertions remain in force.

## 6. Validation

The canonicalized state passed:

```text
candidate SHA-256 and blob:          PASS
origin/main ancestry:                PASS
scope review:                        PASS
focused Navimow MQTT suite:          PASS
private pilot accounting harness:    PASS
PHP syntax:                          PASS
PHPCS:                               PASS
PHPStan with 512 MiB:                PASS
complete make check:                 PASS
staged diff check:                   PASS
post-commit worktree cleanliness:    PASS
```

The temporary Composer `vendor` reference used only for unchanged repository
tooling was removed before staging and is not part of the commit.

## 7. Preserved Architecture

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

No OAuth, REST polling, command, MQTT recovery, profile, variable, archive,
form or metadata contract changed.

## 8. Standalone Boundary

The standalone checkout remains clean at:

```text
main@a8481c9781be603f7c6430b78625a2a4b0188de8
```

No file was copied into it. The next publication gate must freshly fetch the
remote and reject any baseline drift before copying the one frozen Account
file.

## 9. Architecture Decisions

### AD-NAV-1059: Canonicalize the complete recovery narrative

The blocked update, diagnosis, design, implementation and publication plan are
committed with the correction so its rationale is reviewable from one branch.

### AD-NAV-1060: Identify productive code independently of commit recursion

The report containing its own commit cannot embed that commit's hash. The
productive SHA-256 and Git blob are immutable identities; the next step
resolves and records the containing commit directly from Git.

### AD-NAV-1061: Keep standalone publication outside local canonicalization

A clean candidate is necessary but does not authorize copying, committing or
pushing the standalone repository.

### AD-NAV-1062: Preserve all live gates

Local source canonicalization grants no Symcon read, update, reload, MQTT
activation, credential access, restart or mower command.

## 10. Safety Result

This step performed:

```text
local SAEF commits:     1
SAEF pushes:            0
standalone changes:     0
standalone pushes:      0
Symcon reads:           0
Symcon mutations:       0
MQTT activations:       0
credential requests:    0
service restarts:       0
mower commands:         0
```

## 11. Gate Status

| Gate | Status |
|---|---|
| Gate A0 local candidate canonicalization | PASS |
| Gate A standalone publication | CLOSED |
| Gate B metadata conformance | CLOSED |
| Gate C corrective Symcon update | CLOSED |
| Gate D evidence closure | CLOSED |
| MQTT staging or activation | CLOSED |

## 12. Next Step

Proceed with:

```text
267-navimow-account-status-correction-standalone-publication.md
```

That step may publish exactly the frozen Account file only after separate
authorization. It must leave metadata validation, Symcon and MQTT gates closed.
