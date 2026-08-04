# 259 Native MQTT Episode Accounting Standalone Publication

**Case study:** Navimow native IP-Symcon module

**Status:** Exact one-file candidate published and remotely verified; metadata,
Symcon and MQTT activation gates remain closed

**Date:** 2026-08-04

**Scope:** Execute the explicitly authorized Gate-A publication of cumulative
MQTT episode accounting and the bounded pilot summary to
`doctee/symcon-navimow` without tag, release or live-system access

## 1. Result

The frozen canonical Account candidate was copied byte-exactly to the clean
standalone checkout, committed once and pushed fast-forward to standalone
`main`.

```text
repository:     doctee/symcon-navimow
branch:         main
baseline:       79686e52f0bbaad77d37b9cd6e4b367797d96f2e
published:      a8481c9781be603f7c6430b78625a2a4b0188de8
subject:        feat(mqtt): add bounded pilot episode summary
modified paths: 1
insertions:     152
deletions:      0
```

Fresh fetch, direct remote-ref read-back, blob comparison and SHA-256
comparison all passed. No second push was required.

## 2. Authorization Boundary

The user explicitly authorized:

```text
Veröffentlichung der MQTT-Episodenzählung und Pilotzusammenfassung auf
symcon-navimow main freigegeben.
```

The authorization was interpreted only as Gate A from step 258. It did not
authorize metadata validation, Symcon update, credential access, MQTT
activation, restart, REST live request or mower command.

## 3. Fresh Preflight

Immediately before productive copy:

```text
standalone local HEAD:    79686e52f0bbaad77d37b9cd6e4b367797d96f2e
standalone origin/main:   79686e52f0bbaad77d37b9cd6e4b367797d96f2e
direct remote main:       79686e52f0bbaad77d37b9cd6e4b367797d96f2e
standalone worktree:      clean
canonical candidate SHA:  77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4
canonical candidate blob: af1d4dd9094ca10a12f0ee264041ee47b7dc19cb
```

All public and private supporting hashes frozen in step 252 were reproduced
before publication.

## 4. Pre-Publication Validation

The following gates passed immediately before the copy:

- complete focused Navimow MQTT suite;
- REST authentication fixtures;
- envelope, parser, Receiver and Account ingestion checks;
- pilot checkpoints, summary limits and episode accounting;
- transport lifecycle and recovery checks;
- distribution structure validation;
- PHPCS and PHPStan;
- private pilot accounting harness;
- complete repository `make check`.

No validation changed the standalone repository.

## 5. Exact Productive Mutation

Only this mapping was applied:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
    -> NavimowAccount/module.php
```

Post-copy verification proved:

```text
standalone files:                 30
canonical files:                  30
byte-identical complete fileset: yes
Git modified paths:               1
Git added paths:                  0
Git deleted paths:                0
insertions/deletions:             152 / 0
```

PHP syntax, PHPCS and PHPStan were rerun against the copied standalone file.
Privacy and receive-only source scans found no new credential, private
installation value, MQTT writer, uplink topic, variable write or command path.

## 6. Published Identity

| Artifact | Value |
|---|---|
| commit | `a8481c9781be603f7c6430b78625a2a4b0188de8` |
| parent | `79686e52f0bbaad77d37b9cd6e4b367797d96f2e` |
| file | `NavimowAccount/module.php` |
| SHA-256 | `77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4` |
| Git blob | `af1d4dd9094ca10a12f0ee264041ee47b7dc19cb` |

The published file is byte-identical to the canonical SAEF distribution on
mainline commit `2ef7a22a5db1404ad0d66d165639a5dfe789c0ac`.

## 7. Remote Verification

After the push, a fresh fetch and direct remote query proved:

```text
local standalone HEAD:  a8481c9781be603f7c6430b78625a2a4b0188de8
fetched origin/main:    a8481c9781be603f7c6430b78625a2a4b0188de8
direct remote main:     a8481c9781be603f7c6430b78625a2a4b0188de8
remote Account blob:    af1d4dd9094ca10a12f0ee264041ee47b7dc19cb
standalone worktree:    clean
```

The standalone repository exposes no GitHub Actions run for this commit. This
does not weaken the gate because complete SAEF validation, exact fileset
comparison and remote blob read-back passed before and after publication.

## 8. Preserved Runtime Contract

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
public variables:                   14, unchanged
Archive Control contracts:          5, unchanged
```

Publication changed source availability only. It did not install, load or
execute the new code in Symcon.

## 9. Mutation Counts

```text
standalone files copied:       1
standalone commits:            1
standalone pushes:             1
blind retry pushes:            0
tags created:                  0
releases created:              0
SAEF remote pushes:            0
Symcon reads:                  0
Symcon mutations:              0
MQTT credential requests:      0
MQTT activations:              0
mower commands:                0
```

Existing immutable tags `pilot-0.1.0.1` through `pilot-0.1.0.4` were not
changed. No GitHub release exists or was created.

## 10. Architecture Decisions

### AD-NAV-1014: Publish only the frozen Account blob

The authorized change is the exact canonical blob. No recursive synchronization
or metadata copy is permitted.

### AD-NAV-1015: Revalidate immediately before mutation

Earlier readiness evidence is necessary but not sufficient. Baseline, hashes
and all offline gates were repeated before copy and commit.

### AD-NAV-1016: Require full fileset equality after copy

The one-file Git delta must result in a complete standalone fileset that is
byte-identical to canonical distribution.

### AD-NAV-1017: Keep the publication additive

The exact `152/0` delta is a safety invariant. Any deletion would have stopped
the operation.

### AD-NAV-1018: Verify commit and blob remotely

A successful push transport is not publication evidence. Fresh fetch, direct
remote ref and remote-tree blob must agree.

### AD-NAV-1019: Accept absent standalone CI with compensating evidence

Focused checks, complete SAEF checks and byte-exact remote verification provide
the publication gate. This decision does not waive future CI improvements.

### AD-NAV-1020: Keep tags and releases unchanged

This increment is a private-pilot source update, not a new immutable pilot
release checkpoint.

### AD-NAV-1021: Preserve REST authority and receive-only MQTT

The summary exposes bounded diagnostics only and introduces no transport or
command authority.

### AD-NAV-1022: Keep metadata and installation separate

Published PHP does not authorize validator use or a supported Symcon Module
Control update.

### AD-NAV-1023: Prohibit `MC_ReloadModule()`

Any later installation test must use the supported Module Control update path
and its own explicit gate.

## 11. Gate Status

| Gate | Status |
|---|---|
| Gate-A authorization | PASS |
| one-file standalone publication | PASS |
| remote commit verification | PASS |
| remote blob verification | PASS |
| tag or release creation | NOT PERFORMED |
| metadata conformance | CLOSED |
| Symcon disabled update | CLOSED |
| MQTT staging or activation | CLOSED |
| mower command | NOT PLANNED |

## 12. Next Step

The next separately gated artifact is:

```text
260-native-mqtt-episode-accounting-metadata-conformance.md
```

It should validate the exact published commit and unchanged metadata using the
official Symcon Module Validator where available, with the established current
official-schema fallback if the validator UI fails. It must not access Symcon
or activate MQTT.
