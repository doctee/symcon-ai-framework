# 231 Native MQTT Pilot Diagnostics Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Three-file publication passed and remotely verified; metadata
conformance, Symcon update and MQTT activation remain closed
**Date:** 2026-07-30
**Scope:** Execute Gate A from step 230

## 1. Purpose

Step 230 froze the Account implementation, form and translation for native
five-hour pilot checkpoints and bounded transport episodes.

The user granted:

```text
Veröffentlichung der nativen MQTT-Pilotdiagnostik auf main freigegeben.
```

This step:

1. revalidated the exact SAEF candidate;
2. fetched and verified the standalone remote baseline;
3. copied exactly three productive Account files;
4. validated the standalone candidate;
5. committed and pushed one fast-forward change to `main`;
6. fetched again and verified remote commit, paths and blobs;
7. closed private and sanitized publication evidence.

It performed no Module Validator run, Symcon access, MQTT activation, service
restart, REST live request or mower command.

## 2. Revalidated Candidate

The complete gate passed immediately before publication:

```text
sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

Result:

```text
fixtures
REST client and authentication
MQTT envelope and parser
Receiver and Account ingestion
shadow and pilot diagnostics
shadow reconciliation
transport lifecycle
distribution validation
PHPCS
PHPStan

all: PASS
```

Frozen candidate hashes remained exactly:

| Path | SHA-256 | Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `c7726fbc1f822a85e714b122c912a9306f5d201cd140df04e8d018f15d36a12e` | `12658d4badd989580ee68431d577115339cbdf05` |
| `NavimowAccount/form.json` | `92cd3b4712821c84213e26761f12ac7b26ea17b7b8b6ed812c9df135f785704a` | `6e38ffd7dd1b267b27f03e406fbcdf4fb7b9de62` |
| `NavimowAccount/locale.json` | `fe12e326c77bcef5fab060aa117f4f85389177b564ec57723818e75a2fadd4a9` | `cb0d7ebf64f79eedf5a54c939036906aa708686e` |

## 3. Fresh Standalone Baseline

Before mutation, the standalone checkout fetched and pruned `origin`.

Verified:

```text
repository:   https://github.com/doctee/symcon-navimow
branch:       main
local HEAD:   3d223a9c24e396d4ba55ca40aede6742592fbe8f
origin/main:  3d223a9c24e396d4ba55ca40aede6742592fbe8f
subject:      feat(mqtt): expose bounded shadow diagnostics
worktree:     clean
```

No remote drift was present.

## 4. Exact Publication Delta

Only these files were copied:

```text
NavimowAccount/module.php
NavimowAccount/form.json
NavimowAccount/locale.json
```

Source and standalone files were byte-equal before commit.

Exact diff:

```text
3 files changed
703 insertions
0 deletions

NavimowAccount/module.php: 697 insertions
NavimowAccount/form.json:    5 insertions
NavimowAccount/locale.json:  1 insertion
```

No recursive copy or directory synchronization was used.

## 5. Standalone Validation

| Check | Result |
|---|---|
| complete SAEF Navimow MQTT gate | PASS |
| PHP syntax | PASS |
| standalone JSON syntax | PASS |
| PHPCS | PASS |
| PHPStan | PASS |
| distribution structure | PASS |
| staged diff check | PASS |
| exact source/standalone hashes | PASS |
| changed path count | `3` |
| privacy and local-artifact scan | PASS |
| public-state or actuator-path addition | ABSENT |

The form and locale delta adds only the read-only
`Show MQTT Pilot Diagnostics` action and its German caption.

## 6. Published Commit

Publication:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
commit:     793249ece1c0944192ea28dade7ecd2340a5135f
short:      793249e
parent:     3d223a9c24e396d4ba55ca40aede6742592fbe8f
subject:    feat(mqtt): add native pilot checkpoints
```

Push:

```text
3d223a9..793249e  main -> main
```

No tag or release was created.

## 7. Independent Remote Verification

After the push, `origin` was fetched again.

Verified:

```text
local HEAD == origin/main == 793249ece1c0944192ea28dade7ecd2340a5135f
changed paths: 3
insertions:    703
deletions:     0
worktree:      clean
```

All three remote Git blobs and reconstructed SHA-256 hashes exactly match the
frozen candidate.

The remote tree contains no:

- `.DS_Store`;
- `private/` path;
- SAEF report, test or fixture;
- local installation artifact.

## 8. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-pilot-diagnostics-publication/
  evidence-closure.json
```

It records:

- baseline and published commits;
- three candidate hashes and diff counts;
- validation results;
- independent remote verification;
- artifact exclusion;
- closed later gates.

It contains no credential, device identity, MQTT topic, payload, coordinate or
installation ObjectID.

## 9. Safety Result

This publication:

- did not access or mutate Symcon;
- did not run or claim the Module Validator;
- did not update or reload a module;
- did not retrieve OAuth or MQTT credentials;
- did not activate or publish MQTT;
- did not restart a service;
- did not send a mower command;
- did not create a tag or release.

The published commit is not claimed as installed.

The latest historical cleanup evidence remains:

```text
MQTT:             disabled
WebSocket:        inactive
MQTT credentials: absent
REST:             operational and authoritative
```

That historical state must be re-established live before any future update.

## 10. Architecture Decisions

### AD-NAV-839: Preserve the three-file publication boundary

Only the frozen Account implementation, form and translation were published.
No other productive or evidence file entered the standalone repository.

### AD-NAV-840: Bind remote evidence to commit and every blob

A successful push message was followed by a fresh fetch, exact path comparison
and independent blob and SHA-256 verification.

### AD-NAV-841: Stop after Gate A

Publication authorization did not imply metadata validation, Symcon update or
MQTT activation. All later gates remain closed.

## 11. Side-Effect Accounting

| Operation | Count |
|---|---:|
| standalone file copies | 3 |
| Git commits | 1 |
| Git pushes | 1 |
| post-push fetches | 1 |
| tags/releases | 0 |
| Module Validator runs | 0 |
| Symcon updates/reloads | 0 |
| MQTT activations/publishes | 0 |
| service restarts | 0 |
| mower commands | 0 |

## 12. Gate Decision

| Gate | Decision |
|---|---|
| frozen candidate revalidation | PASS |
| fresh standalone baseline | PASS |
| three-file publication | PASS |
| remote commit verification | PASS |
| remote blob and SHA-256 verification | PASS |
| private/local artifact exclusion | PASS |
| Gate B metadata conformance | CLOSED |
| Gate C disabled Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| mower command | PROHIBITED |

## 13. Next Step

Proceed with:

```text
232-native-mqtt-pilot-diagnostics-metadata-conformance.md
```

That step should validate the exact published commit through the official
Symcon Module Validator or the established exact-official-schema fallback. It
must perform no Symcon update or MQTT activation.
