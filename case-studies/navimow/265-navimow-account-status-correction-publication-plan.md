# 265 Navimow Account Status Correction Publication Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Corrective candidate frozen; local canonicalization, standalone
publication, metadata validation and Symcon recovery remain separately closed

**Date:** 2026-08-05

**Scope:** Plan the deterministic publication and disabled recovery update for
the Account status-finalization correction implemented in step 264

## 1. Decision

Publish the correction only after the current SAEF workstream has been turned
into a clean, immutable candidate commit.

The later standalone publication may copy exactly one file:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
    -> NavimowAccount/module.php
```

Publication, metadata conformance and the corrective Symcon update are three
independent gates. This planning step performs none of them.

## 2. Fixed Architecture Boundary

Every later gate must preserve:

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

The correction must not change OAuth, REST polling, commands, MQTT recovery,
variables, profiles, archive logging, forms or metadata.

`MC_ReloadModule()` is prohibited. A later live gate permits at most one
supported `MC_UpdateModule()` call.

## 3. Verified Planning Baselines

### 3.1 SAEF workstream

```text
worktree:    private/worktrees/navimow-standalone-readiness
branch:      codex/navimow-standalone-readiness
HEAD:        e866844ab8d1dec062c775c5810310e31d590cfb
origin/main: 2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
ahead:       3 commits
state:       deliberate uncommitted steps 261 through 265 and correction
```

The branch is isolated but not yet a reproducible publication source. Direct
copying from this dirty state is forbidden.

### 3.2 Standalone module

```text
repository:  doctee/symcon-navimow
branch:      main
local HEAD:  a8481c9781be603f7c6430b78625a2a4b0188de8
origin/main: a8481c9781be603f7c6430b78625a2a4b0188de8
worktree:    clean
```

A fresh `git ls-remote` on 2026-08-05 confirmed the same remote commit.

### 3.3 Installed Symcon baseline

The last bounded read-only evidence from steps 261 and 262 established:

```text
installed commit:         79686e52
Account status:           101
REST:                     operational
MQTT feature:             disabled
MQTT/WebSocket:           inactive
native credentials:       absent
public variables:         14
Archive Control contracts: 5
```

This is a historical recovery baseline, not a current live claim. Gate C must
re-read every required signal before mutation.

## 4. Frozen Productive Candidate

Exactly one productive file differs between the current standalone tree and
the SAEF distribution:

| Artifact | SHA-256 | Git blob |
|---|---|---|
| standalone Account baseline | `77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4` | `af1d4dd9094ca10a12f0ee264041ee47b7dc19cb` |
| corrective Account candidate | `d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c` | `ad4432c29613062cd277e44ed161a7877b624da5` |

Candidate delta:

```text
modified paths: 1
added paths:    0
deleted paths:  0
insertions:     5
deletions:      0
```

The five additions are one private status constant and four successful
terminal `SetStatus(102)` calls. A complete fileset comparison found no other
content difference.

## 5. Frozen Supporting Evidence

| Artifact | SHA-256 |
|---|---|
| step-263 recovery design | `497f803fa7f0d905c82c54eb602783f7dcf7e5aa5ffa6a6457dabe63fd20daa8` |
| step-264 implementation report | `d6e26330d6e90816900299293b4591c4ace81fadbd54dbaf39f149a9ab9f9821` |
| Symcon runtime harness | `8ce3ad73bc40883ec7d62bdbef1f391906e50ec3cb6fbce01989fa80f8fe9a28` |
| MQTT transport lifecycle test | `75960576f711711134a30f1773c3131f639cc4db9725f7c0879001e5939e4195` |
| focused Navimow check | `e5883d09b376e16e799657609fb173de3ac1cc022b867e7e0e9fc50cba40dcbf` |

The lifecycle suite covers eight successful status-finalization scenarios.
Focused Navimow checks, private pilot accounting, PHPCS, PHPStan and complete
`make check` passed in step 264.

Any productive or supporting hash drift returns the process to implementation
review and complete offline validation.

## 6. Gate A0: Local Candidate Canonicalization

Gate A0 requires separate authorization because it creates a Git commit.

It permits only:

1. verify the branch relationship to freshly fetched `origin/main`;
2. review all deliberate paths from steps 261 through 265;
3. verify that no unrelated worktree path is included;
4. rerun focused checks and complete `make check`;
5. stage the Navimow reports, README, Account correction and two test files;
6. inspect the complete staged diff and run `git diff --cached --check`;
7. create one local Conventional Commit;
8. record the resulting commit and candidate blob;
9. require a clean worktree after the commit.

Suggested commit:

```text
fix(navimow): finalize account instance status
```

Gate A0 permits no push, pull request, standalone copy, publication, Symcon
access or live operation.

### Gate-A0 stop conditions

Stop if:

- `origin/main` is no longer an ancestor of the workstream;
- an unexplained path appears;
- the Account candidate hash differs;
- the staged candidate changes more than the documented five productive lines;
- any focused or repository-wide check fails;
- the post-commit worktree is not clean.

## 7. Gate A: One-File Standalone Publication

Gate A starts only from the clean candidate commit created by Gate A0 and
requires separate explicit publication authorization.

Recommended wording:

```text
Veröffentlichung der Navimow-Account-Statuskorrektur auf symcon-navimow main
freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone remote;
2. prove clean local `main` equals `origin/main` at `a8481c97`;
3. verify the exact source commit and frozen candidate identities;
4. rerun the complete focused and repository-wide offline gates;
5. copy exactly `NavimowAccount/module.php` from the clean SAEF commit;
6. prove exactly one modified path with five insertions and no deletion;
7. prove standalone file equality by SHA-256 and Git blob;
8. run syntax, distribution, PHPCS, PHPStan, privacy and receive-only checks;
9. inspect the complete standalone diff;
10. create one Conventional Commit;
11. push one fast-forward update to standalone `main`;
12. fetch again and verify remote commit, tree and Account blob;
13. record private machine-readable and sanitized public evidence.

Suggested standalone commit:

```text
fix(account): finalize successful instance status
```

Gate A permits no tag, release, metadata UI action, Symcon access, module
update, MQTT activation, credential request, restart, REST live request or
mower command.

### Gate-A stop conditions

Stop before commit or push if:

- fetched standalone `origin/main` differs from `a8481c97`;
- either worktree is dirty or has unexpected ancestry;
- any second standalone path differs;
- source or destination hash differs from the freeze;
- metadata, variable, archive, retry, command or authority contracts drift;
- a validation or privacy check fails;
- a public MQTT writer or mower-command path appears.

An ambiguous push result is resolved by fresh remote read-back. It never
causes a blind second push.

## 8. Gate B: Published Metadata Conformance

Gate B starts only after Gate A passes and requires separate authorization.

The exact published commit must be checked against all 13 metadata inputs:

- `library.json`;
- four `module.json` files;
- four `form.json` files;
- four `locale.json` files.

The official Symcon Module Validator is attempted first. If its known `$`
runtime defect recurs, the established fallback must freshly download the
official schemas and the validator-referenced AJV version, bind all inputs to
the exact published commit and execute all 13 validations.

Gate B performs no Symcon access or mutation.

## 9. Gate C: Corrective Disabled Symcon Update

Gate C starts only after Gates A and B pass and requires separate explicit
authorization.

Recommended wording:

```text
Symcon-Update auf die Navimow-Account-Statuskorrektur mit deaktiviertem MQTT
freigegeben.
```

### 9.1 Fresh read-only preflight

Use only the bounded structured Symcon MCP channel. Check `transportError`,
`executionError` and `truncated` independently.

The preflight must prove:

- exact installed commit `79686e52` unless a documented later update exists;
- clean and valid module repository on `main`;
- all four Navimow module instances present;
- Account status remains the explained stale `101` recovery state;
- Configurator, Device and Receiver remain compatible;
- kernel is fully ready;
- REST is operational and reauthentication is not required;
- MQTT feature is disabled;
- MQTT and WebSocket are inactive;
- Authorization, MQTT username and MQTT password are absent;
- no reconnect, Core-resume or pilot observation is pending;
- all 14 public variable contracts are unchanged;
- all five Archive Control contracts are present and queryable.

Status `101` is accepted here only as the exact correction target established
by steps 261 through 264. Any different unexplained status or surrounding
contract drift stops the operation.

### 9.2 Single mutation

After a fresh mutation-time precondition hash matches the preflight, execute:

```text
MC_UpdateModule(): 1
MC_ReloadModule(): 0
IPS_ApplyChanges(): 0 explicit calls
```

No retry is permitted, including after timeout or ambiguous transport output.
Resolve ambiguity through read-only commit and status inspection.

### 9.3 Immediate and delayed verification

Read-only observations immediately, at approximately `+15 s` and at
approximately `+75 s` must prove:

- exact newly published commit installed;
- Account status `102`;
- Configurator, Device and Receiver compatible;
- REST remains operational;
- MQTT remains disabled and credential-free;
- MQTT and WebSocket remain inactive;
- no reconnect or Core-resume work is armed;
- 14 public variable contracts unchanged;
- five Archive Control contracts unchanged and queryable;
- no OAuth action, restart or mower command occurred.

Success requires stable `102` after the update. A transient or persistent
`101`, unexpected status, credential residue, REST regression or contract drift
is a stop condition and does not authorize reload, repeat update or rollback.
Those actions require a new diagnosis and gate.

## 10. Gate D: Evidence Closure

After Gate C, create a sanitized public report and retain private structured
evidence for:

- exact source, standalone and installed commits;
- precondition and postcondition hashes;
- MCP transport, PHP execution and truncation results;
- status transition and delayed stability;
- disabled credential-free MQTT state;
- REST, variable and archive compatibility;
- operation counts and stop-condition outcome.

Public evidence must exclude credentials, Authorization values, MQTT topics,
endpoints, ObjectIDs, hostnames, payloads, device identities and local paths.

## 11. Rollback Policy

No automatic rollback is authorized.

Standalone commit `a8481c97` remains the source rollback identity. Installed
commit `79686e52` remains the live rollback identity until Gate C succeeds.
If the corrective update fails, preserve evidence and stop. A rollback would
be a separate publication and live-mutation decision because the old source
recreates the missing explicit status-finalization contract.

## 12. Gate Matrix

| Gate | Current status |
|---|---|
| correction design | PASS |
| implementation and offline validation | PASS |
| candidate freeze | PASS |
| Gate A0 local candidate canonicalization | CLOSED |
| Gate A standalone publication | CLOSED |
| Gate B metadata conformance | CLOSED |
| Gate C corrective Symcon update | CLOSED |
| Gate D evidence closure | CLOSED |
| MQTT staging or activation | CLOSED |
| service restart | CLOSED |
| mower command | CLOSED |

## 13. Architecture Decisions

### AD-NAV-1050: Require a clean candidate commit before copying

A hash-frozen but dirty worktree is reviewable evidence, not a reproducible
publication source.

### AD-NAV-1051: Preserve the one-file publication boundary

Only the Account implementation carries the correction. Tests and SAEF
reports remain in the framework repository.

### AD-NAV-1052: Bind publication to both source and destination identities

The clean SAEF commit, standalone baseline, candidate SHA-256 and Git blob are
all preconditions. A path-only comparison is insufficient.

### AD-NAV-1053: Revalidate unchanged metadata after publication

PHP-only publication does not waive the independent exact-commit metadata
gate.

### AD-NAV-1054: Treat status 101 as a bounded recovery target

The prior healthy-status precondition is intentionally replaced only for this
correction. The exact stale state and all surrounding safety contracts must be
reconfirmed before mutation.

### AD-NAV-1055: Use one supported update without reload

The corrective source must finalize its own lifecycle. A reload or explicit
ApplyChanges call would obscure whether that contract works.

### AD-NAV-1056: Require delayed status stability

Immediate `102` alone is insufficient. Two delayed observations must exclude a
repeat of the stale post-lifecycle state.

### AD-NAV-1057: Keep rollback manual and separately gated

The old module source lacks the correction and is not a safe automatic repair.

### AD-NAV-1058: Keep MQTT outside the recovery operation

This change repairs Core instance status only. It does not reopen any MQTT
pilot or activation decision.

## 14. Safety Result

This planning step performed:

```text
local commits:           0
repository pushes:      0
standalone publication: 0
Symcon reads:           0
Symcon mutations:       0
MQTT activations:       0
credential requests:    0
service restarts:       0
mower commands:         0
```

## 15. Next Step

Proceed with:

```text
266-navimow-account-status-correction-candidate-canonicalization.md
```

That step should execute only Gate A0 after separate authorization. It must
not push either repository, publish the standalone module, access Symcon or
activate MQTT.
