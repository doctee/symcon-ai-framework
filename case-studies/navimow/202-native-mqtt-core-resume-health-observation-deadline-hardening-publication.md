# 202 Native MQTT Core Resume Health Observation Deadline Hardening Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Published and remotely verified; validator and all Symcon gates closed
**Date:** 2026-07-29
**Scope:** Publish exactly the frozen one-file deadline-hardening delta from
step 201

## 1. Purpose

This step executes Gate A from
`201-native-mqtt-core-resume-health-observation-deadline-hardening-publication-and-live-test-plan.md`.

It publishes only the already implemented retained-Core observation extension:

```text
observation offsets:
[15, 30, 60, 90] -> [15, 30, 60, 90, 120, 180]

maximum retained observations:
4 -> 6
```

It performs no Module Validator run, Symcon update, MQTT activation, service
restart, MQTT publish, mower command, tag or release.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung der MQTT-Core-Resume-Deadline-Härtung auf main freigegeben.
```

This authorization covered one reviewed commit and one fast-forward push to
the standalone module `main`. It did not authorize any later gate.

## 3. Fresh Remote Baseline

Immediately before publication, the standalone clone was fetched and pruned.

```text
repository: doctee/symcon-navimow
branch:     main
local HEAD: 45c7bd509f95865030f676184a1aeff4219c0750
remote HEAD: 45c7bd509f95865030f676184a1aeff4219c0750
worktree:   clean
```

The local branch, fetched remote branch and frozen step-201 baseline were
identical.

## 4. Frozen Candidate Revalidation

All four frozen hashes remained equal:

| Artifact | SHA-256 |
|---|---|
| `distribution/NavimowAccount/module.php` | `6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f` |
| `tests/mqtt-transport-lifecycle.php` | `21a5d34d42a5bfdea2ddc95f47c461707e71cedaa7769541d9be70db1677bbcd` |
| `tests/mqtt-fixtures.php` | `2cd749abf48b0811e1012f21d35778cb2f25263a6d6a64c22d0cf081ba03a153` |
| `fixtures/mqtt/core-resume-bounded-health-observation.json` | `e9acb461a00e34e01fd2f0c8a55b5e53c3826b8b3ec57c79f4fad692cea8a71e` |

The complete productive distribution comparison found:

```text
modified files: 1
added files:    0
deleted files:  0
```

The only modified productive file was:

```text
NavimowAccount/module.php
```

Its complete diff contained two insertions and two deletions implementing only
the two frozen constant changes.

## 5. Pre-Commit Validation

The complete Navimow MQTT offline gate passed:

- MQTT fixtures;
- REST client and authentication;
- native MQTT envelope parsing;
- partial payload parsing;
- Symcon MQTT receive probe;
- shadow payload reduction;
- Receiver diagnostics;
- Account ingestion;
- targeted REST reconciliation;
- transport lifecycle;
- distribution structure;
- PHPCS;
- PHPStan.

Standalone validation also passed:

- PHP syntax for every standalone PHP file;
- JSON decoding for every standalone JSON file;
- changed-file PHPCS;
- changed-file PHPStan with a 512 MB limit;
- `git diff --check`;
- canonical byte equality;
- private network, credential and GitHub-token scan;
- `MC_ReloadModule()`, `MQTT_Publish()` and `RequestAction()` absence in the
  changed Account file.

The staged diff contained exactly one file with two insertions and two
deletions.

## 6. Published Commit

```text
commit:
8fdab84bd2a2190a6025cedd11f1ae6248369c0e

message:
fix(mqtt): extend native core resume deadline

branch:
main
```

The push was a fast-forward:

```text
45c7bd509f95865030f676184a1aeff4219c0750
  ->
8fdab84bd2a2190a6025cedd11f1ae6248369c0e
```

No tag or release was created.

## 7. Independent Remote Verification

After the push:

- `origin/main` was fetched again;
- local `HEAD` equaled fetched `origin/main`;
- the standalone worktree was clean;
- GitHub reported exactly one commit ahead of the previous baseline;
- GitHub reported exactly one modified file;
- that file contained two additions and two deletions;
- the remote Account Git blob was
  `c7d1dfeda3d6aa85841ff71859e81d2457398334`;
- the remote Account SHA-256 was
  `6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f`;
- the remote file was byte-equal to the canonical SAEF distribution file.

The remote publication is therefore deterministic and complete.

## 8. Safety Result

This publication:

- changes no REST state authority;
- adds no MQTT publish path;
- adds no mower-command path;
- changes no module or instance GUID;
- changes no variable, profile, action or archive contract;
- changes no retry delay;
- changes no credential behavior;
- performs no Symcon operation.

The live installation remains at the last verified disabled,
credential-free state from step 198 until a separately authorized update.

## 9. Private Evidence

Machine-readable publication evidence is retained below the private overlay:

```text
private/navimow-capture/output/
  core-resume-deadline-hardening-publication/evidence-closure.json
```

It contains commit, scope, validation and remote-integrity facts but no
credential, private network value, ObjectID, topic or device identity.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| exact one-file scope | PASS |
| complete offline validation | PASS |
| remote commit integrity | PASS |
| remote Account byte equality | PASS |
| Gate B official Module Validator | CLOSED |
| Gate C disabled Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

## 11. Next Step

Run Gate B from step 201 as a separate read-only official Module Validator
verification against:

```text
main@8fdab84bd2a2190a6025cedd11f1ae6248369c0e
```

A successful validator result may open planning for the separately authorized
disabled Symcon update. This publication alone does not authorize it.
