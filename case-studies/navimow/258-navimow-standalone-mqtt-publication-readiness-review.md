# 258 Navimow Standalone MQTT Publication Readiness Review

**Case study:** Navimow native IP-Symcon module

**Status:** One-file standalone publication is ready for a separate explicit
Gate-A authorization; no publication or live access performed

**Date:** 2026-08-04

**Scope:** Compare canonical SAEF distribution with the current standalone
Navimow module remote, revalidate the frozen episode-accounting candidate and
decide publication readiness without changing either remote or accessing
Symcon

## 1. Result

No blocking publication-readiness finding was found.

The canonical distribution and the freshly verified standalone `main` contain
the same 30 module files. Exactly one file differs in content:

```text
NavimowAccount/module.php
```

The difference is the additive cumulative episode accounting and bounded MQTT
pilot summary frozen in step 252. The candidate remains suitable for a
separately authorized one-file publication.

```text
Gate-A publication recommendation: GO, conditional
current publication authorization: NO
files permitted for publication:    1
standalone mutations in this step:  0
Symcon reads or mutations:           0
```

## 2. Verified Repository Baselines

### Canonical SAEF source

```text
repository:  doctee/symcon-ai-framework
branch:      main
commit:      2ef7a22a5db1404ad0d66d165639a5dfe789c0ac
source:      case-studies/navimow/distribution/
files:       30
worktree:    dedicated and clean
```

### Standalone module

```text
repository:  doctee/symcon-navimow
branch:      main
commit:      79686e52f0bbaad77d37b9cd6e4b367797d96f2e
subject:     feat(mqtt): harden episode diagnostics
files:       30
local HEAD:  equals fetched origin/main
remote main: independently verified
worktree:    clean
```

The standalone remote did not advance after step 252. No rebase, reset, copy,
commit, push, tag or release operation was performed.

## 3. Exact Fileset Comparison

A recursive checksum comparison excluded only `.git` and `.DS_Store`.

```text
canonical files:        30
standalone files:       30
content differences:    1
added paths:             0
deleted paths:           0
modified paths:          1
candidate insertions:  152
candidate deletions:     0
```

All 29 unaffected files are content-identical. Timestamp-only filesystem
differences are not publication changes and do not enter the candidate.

## 4. Frozen Candidate Identity

| Artifact | SHA-256 | Git blob |
|---|---|---|
| canonical `NavimowAccount/module.php` | `77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4` | `af1d4dd9094ca10a12f0ee264041ee47b7dc19cb` |
| standalone baseline `NavimowAccount/module.php` | `74d24fbce5efd85a89eaa4253d6ec958969cd372d3e6bd43f9247211f8e16e37` | `cfa3028861e7b6343bde41a36bc65c4fd7e19f82` |

Both identities exactly match the step-252 freeze. Mainline integration and PR
merge changed repository ancestry, not the candidate blob.

## 5. Supporting Evidence Identity

Every frozen supporting hash from step 252 remains unchanged:

| Artifact | SHA-256 | Result |
|---|---|---|
| step-251 implementation report | `84629e2ae285824e4776819d125bf16475020f991cd37711fad0e56a6b2a3f61` | PASS |
| MQTT pilot checkpoint test | `b51897b672e8f1fe1131325a8a66a458edc1b7feb69410d149301fec69ac37d4` | PASS |
| reconciled episode fixture | `b803799f8cf27dd4838ec105027fda235cf9ecb6aeacffc64b090f21ce9232c2` | PASS |
| private `PilotHarness.php` | `c2c74a84d470ad13d76f96bc58844c78269bb9b3d1e452298b2b77a647ab722d` | PASS |
| private `offline-test.php` | `0ec4658b9c71ef6e06a059a9904baca8cdee7a686da326b53659530b249b75ff` | PASS |
| private read-only probe | `cf710da3cdb83c05ee8c916c0059d016699100e2cb7aee7928d4c0fb76ccbf36` | PASS |

No supporting file is part of the standalone publication fileset.

## 6. Candidate Behavior Review

The 152-line additive delta:

- adds `GetMqttPilotSummary()` as a read-only diagnostic projection;
- adds cumulative checkpoint, episode, rotation and Core-transition sequences
  to the detailed diagnostic projection;
- keeps `unexpectedDisconnects` as an independent observation counter;
- projects the latest bounded operational context without forensic arrays;
- removes per-episode Core-transition arrays from the compact summary;
- enforces summary format version 1;
- rejects encoded summaries larger than 16384 bytes;
- changes no reconnect timing, retry decision or MQTT transport action;
- adds no variable, archive logging, command or MQTT publish route.

The implementation reads existing bounded attributes and statistics. Calling
the summary does not activate MQTT, fetch credentials, issue REST requests or
write mower state.

## 7. Preserved Architecture Contract

```text
public device-state authority:      REST
MQTT direction:                     receive-only
MQTT publish path:                  absent
MQTT mower-command route:           absent
feature default:                    disabled
reconnect delays:                   60 / 300 / 900 seconds
maximum reconnect attempts:         3
distinct episode source:            episodeSequence
disconnect observation source:      unexpectedDisconnects
summary format:                     version 1
summary maximum:                    16384 bytes
Account variables:                  6
Device variables:                   8
Archive Control contracts:          5
```

The one-file candidate changes none of the 14 public variables or 5 Archive
Control contracts. Existing logged variables therefore retain their Idents and
profiles.

## 8. Validation Result

The current canonical candidate passed:

- all MQTT and REST fixtures;
- envelope and partial-payload parsing;
- Receiver and Account ingestion checks;
- shadow diagnostics and REST reconciliation;
- pilot checkpoint, cumulative episode and size-bound checks;
- transport lifecycle and recovery checks;
- distribution structure validation;
- PHPCS and PHPStan;
- the private pilot policy and accounting harness;
- receive-only source scan;
- exact fileset and frozen-hash checks.

The repository-wide `make check` remains mandatory immediately before any
publication commit. A report-only documentation change does not alter the
frozen productive blob.

## 9. Residual Risks

1. Navimow WSS behavior remains vendor-controlled and has produced recovered
   transport episodes during private pilots.
2. The summary improves operational evidence but does not itself improve
   transport stability.
3. The standalone repository has no evidence in this step of an independent
   public CI gate; SAEF validation and exact remote read-back therefore remain
   mandatory during publication.
4. Metadata is unchanged, but official metadata conformance remains a separate
   post-publication gate.
5. A published file is not installed until a separately authorized supported
   Symcon Module Control update occurs.

These risks do not block the one-file publication because MQTT remains
disabled by default and publication alone neither installs nor activates it.

## 10. Gate-A Contract

Publication requires separate explicit authorization using:

```text
Veröffentlichung der MQTT-Episodenzählung und Pilotzusammenfassung auf
symcon-navimow main freigegeben.
```

That authorization permits only:

1. a fresh standalone fetch and exact baseline check;
2. complete focused and repository-wide offline validation;
3. copying canonical `NavimowAccount/module.php` to the standalone checkout;
4. proving one modified path, 152 insertions and no deletion;
5. syntax, PHPCS, PHPStan, privacy and receive-only checks;
6. one Conventional Commit:
   `feat(mqtt): add bounded pilot episode summary`;
7. one fast-forward push to standalone `main`;
8. fresh remote commit and blob verification;
9. private machine-readable and sanitized public publication evidence.

Gate A permits no tag, release, metadata UI, Symcon access, module update,
credential retrieval, MQTT activation, restart, REST live request or mower
command. `MC_ReloadModule()` remains prohibited.

## 11. Stop Conditions

Stop before publication if:

- standalone remote `main` differs from
  `79686e52f0bbaad77d37b9cd6e4b367797d96f2e`;
- canonical candidate SHA-256 or Git blob differs from this report;
- the standalone worktree is not clean and equal to its remote;
- any path other than `NavimowAccount/module.php` differs;
- any deletion or non-additive candidate change appears;
- a validation, privacy or receive-only check fails;
- variable, archive, retry or authority contracts drift.

An ambiguous network result never authorizes a second blind push. Fetch and
remote hash comparison must resolve it.

## 12. Architecture Decisions

### AD-NAV-1004: Compare complete filesets by content

Readiness depends on all 30 module files, not only the expected Account file.
Filesystem timestamps are excluded from semantic scope.

### AD-NAV-1005: Accept the unchanged standalone baseline

Fresh fetch and direct remote-ref verification reproduce the step-252 baseline
exactly, so no rebase or candidate redesign is required.

### AD-NAV-1006: Preserve the one-file publication boundary

Only the Account module carries the frozen additive behavior. Recursive copy or
directory replacement remains prohibited.

### AD-NAV-1007: Treat the canonical blob as publication source

The source of truth is the distribution on canonical SAEF `main`, identified
by SHA-256 and Git blob rather than by the old recovery branch.

### AD-NAV-1008: Keep episode and disconnect counters distinct

`episodeSequence` counts distinct transport episodes while
`unexpectedDisconnects` remains an observation counter.

### AD-NAV-1009: Keep the operational summary bounded

Format version 1 and the 16384-byte encoded limit are publication invariants.

### AD-NAV-1010: Do not infer transport maturity

Diagnostic correctness does not remove the residual upstream WSS stability
risk or authorize permanent MQTT operation.

### AD-NAV-1011: Preserve variable and archive identity

The additive Account method creates no public variable or logging change, so
existing user archive histories remain attached to the same Idents.

### AD-NAV-1012: Separate publication from installation

A standalone main push changes source availability only. Metadata and disabled
Symcon update gates remain independent.

### AD-NAV-1013: Require renewed authorization for Gate A

The prior PR merge authorization did not include the standalone repository.

## 13. Gate Status

| Gate | Status |
|---|---|
| canonical SAEF mainline | PASS, commit `2ef7a22a` |
| standalone baseline freshness | PASS, commit `79686e52` |
| complete fileset comparison | PASS, one modified path |
| candidate and supporting hashes | PASS |
| focused MQTT offline validation | PASS |
| private pilot harness | PASS |
| repository-wide final check | PASS |
| Gate-A publication readiness | CONDITIONAL GO |
| Gate-A publication authorization | PASS IN STEP 259 |
| metadata validation | CLOSED |
| Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |
| mower command | NOT PLANNED |

## 14. Next Step

After explicit Gate-A authorization, proceed with:

```text
259-native-mqtt-episode-accounting-standalone-publication.md
```

That step may publish exactly one reviewed Account file and verify the remote
result. It must stop before metadata validation, Symcon access or MQTT
activation.
