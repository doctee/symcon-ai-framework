# 267 Navimow Account Status Correction Standalone Publication

**Case study:** Navimow native IP-Symcon module

**Status:** Gate A passed and remotely verified; metadata conformance and the
corrective Symcon update remain closed

**Date:** 2026-08-05

**Scope:** Publish exactly the frozen Account status-finalization correction to
`doctee/symcon-navimow` main without accessing Symcon

## 1. Result

The five-line Account status correction was published as one fast-forward
commit:

```text
repository:  doctee/symcon-navimow
branch:      main
baseline:    a8481c9781be603f7c6430b78625a2a4b0188de8
published:   eda494513826fa43ccc1b28634b06354356f49a4
subject:     fix(account): finalize successful instance status
files:       1 modified
insertions:  5
deletions:   0
```

A fresh post-push fetch proved local `main`, `origin/main`, the remote commit
and the Account blob equal. No tag or release was created.

## 2. Authorization Boundary

The user explicitly authorized:

```text
Veröffentlichung der Navimow-Account-Statuskorrektur auf symcon-navimow main
freigegeben.
```

That authorization permitted the one-file publication only. It did not
authorize metadata validation, Symcon access, `MC_UpdateModule()`,
`MC_ReloadModule()`, MQTT activation, credential retrieval, service restart or
a mower command.

## 3. Source Provenance

The publication source was the clean local SAEF candidate:

```text
repository:  doctee/symcon-ai-framework
worktree:    dedicated clean Navimow worktree
branch:      codex/navimow-standalone-readiness
commit:      d473467dbefb53d94fba0a1e43514f3b54cdcb30
subject:     fix(navimow): finalize account instance status
```

Before the copy:

- the worktree was clean;
- `origin/main` was an ancestor;
- the focused Navimow gate passed;
- the private pilot accounting harness passed;
- complete `make check` passed;
- the temporary Composer dependency link was removed.

The copied source was therefore an immutable committed artifact, not an
uncommitted reconstruction.

## 4. Exact Productive Identity

| Artifact | SHA-256 | Git blob |
|---|---|---|
| prior standalone Account | `77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4` | `af1d4dd9094ca10a12f0ee264041ee47b7dc19cb` |
| SAEF corrective Account | `d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c` | `ad4432c29613062cd277e44ed161a7877b624da5` |
| published Account | `d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c` | `ad4432c29613062cd277e44ed161a7877b624da5` |

The standalone commit changes exactly:

```text
NavimowAccount/module.php
```

Its complete delta is:

```text
modified paths: 1
added paths:    0
deleted paths:  0
insertions:     5
deletions:      0
```

## 5. Published Behavior

The published Account now:

1. defines a private implementation constant for Core status `102`;
2. sets status `102` after incomplete-configuration cleanup;
3. sets status `102` while authorization is pending;
4. sets status `102` after successful deferred kernel reconciliation;
5. sets status `102` after normal authenticated startup scheduling.

Status finalization remains after branch-specific work and cannot mask an
exception that occurs before a terminal path completes.

The correction does not claim cloud or transport health. Existing variables
and diagnostics continue to represent authentication, REST and MQTT state.

## 6. Validation Evidence

### 6.1 Source validation

```text
focused Navimow fixture and lifecycle suites: PASS
distribution validation:                     PASS
private pilot accounting harness:             PASS
PHPCS:                                         PASS
PHPStan:                                       PASS
complete make check:                           PASS
```

### 6.2 Standalone validation

```text
fresh baseline fetch:              PASS
clean baseline equals origin/main: PASS
full 30-file tree equality:        PASS
Account PHP syntax:                PASS
Git diff check:                    PASS
one-file scope:                    PASS
candidate SHA-256 equality:        PASS
candidate Git blob equality:       PASS
privacy scan:                      PASS
```

The complete SAEF distribution and standalone repository contained 30 files
and were byte-identical after the copy, excluding only `.git` and local OS
metadata.

## 7. Publication Operations

```text
standalone file copies: 1
standalone commits:     1
standalone pushes:      1
push retries:           0
tags:                   0
releases:               0
```

The pre-push refresh proved:

```text
new commit parent: a8481c9781be603f7c6430b78625a2a4b0188de8
origin/main:       a8481c9781be603f7c6430b78625a2a4b0188de8
local state:       clean, ahead exactly one commit
```

The push was a direct fast-forward from `a8481c97` to `eda4945`.

## 8. Remote Verification

A fresh fetch after the push proved:

```text
local main:          eda494513826fa43ccc1b28634b06354356f49a4
fetched origin/main: eda494513826fa43ccc1b28634b06354356f49a4
worktree:            clean
changed path:        NavimowAccount/module.php
remote Account blob: ad4432c29613062cd277e44ed161a7877b624da5
```

The published Account SHA-256 remained
`d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c`.

The initial sandboxed standalone fetch encountered local DNS resolution
denial. The authorized network retry succeeded before any copy or mutation.
This was a local execution-channel restriction, not an ambiguous Git result;
there was no push at that point.

## 9. Preserved Architecture

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

No metadata, form, locale, OAuth, REST polling, command, MQTT recovery,
variable, profile or archive contract changed.

## 10. Architecture Decisions

### AD-NAV-1063: Publish only from the canonical SAEF commit

The source commit `d473467` and candidate blob were verified before copying.

### AD-NAV-1064: Require complete tree equality after the copy

One expected Git diff is insufficient by itself. All 30 public files must
equal the canonical distribution after publication preparation.

### AD-NAV-1065: Keep the standalone commit minimal

Tests, reports and private evidence remain in SAEF. The standalone commit
contains only the productive Account file.

### AD-NAV-1066: Resolve network restrictions before mutation

The failed sandboxed fetch produced no repository ambiguity. Publication
continued only after a successful fresh remote fetch reproduced the baseline.

### AD-NAV-1067: Verify publication through fresh remote read-back

Push output alone is not acceptance. Fetched remote commit, path and blob must
all equal the frozen candidate.

### AD-NAV-1068: Keep installation separate from source availability

The correction is public on standalone `main` but is not claimed as installed
or active in Symcon.

## 11. Safety Result

This step performed:

```text
SAEF pushes:            0
standalone publication: 1
Symcon reads:           0
Symcon mutations:       0
MC_UpdateModule():      0
MC_ReloadModule():      0
MQTT activations:       0
credential requests:    0
service restarts:       0
mower commands:         0
```

Installed Symcon therefore remains on the previously documented commit and
stale Account status until a separately authorized corrective update.

## 12. Gate Status

| Gate | Status |
|---|---|
| Gate A0 local candidate canonicalization | PASS |
| Gate A standalone publication | PASS |
| remote commit and blob verification | PASS |
| Gate B metadata conformance | CLOSED |
| Gate C corrective Symcon update | CLOSED |
| Gate D evidence closure | CLOSED |
| MQTT staging or activation | CLOSED |

## 13. Next Step

Proceed with:

```text
268-navimow-account-status-correction-metadata-conformance.md
```

That step must validate all 13 exact metadata inputs from published commit
`eda494513826fa43ccc1b28634b06354356f49a4` without accessing or changing
Symcon.
