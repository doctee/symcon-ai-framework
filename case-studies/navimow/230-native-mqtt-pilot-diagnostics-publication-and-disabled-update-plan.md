# 230 Native MQTT Pilot Diagnostics Publication and Disabled Update Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Candidate frozen; publication, metadata validation, Symcon update
and all MQTT activation gates remain closed
**Date:** 2026-07-30
**Scope:** Plan deterministic three-file publication and one disabled Symcon
update for steps 228 and 229

## 1. Purpose

Step 229 completed the offline implementation of:

- restart-safe five-hour pilot checkpoints;
- bounded transport episode summaries;
- bounded credential-rotation summaries;
- the read-only `GetMqttPilotDiagnostics()` API;
- the read-only Account form action.

This step freezes the exact candidate and separates all later mutations into
independent authorization gates.

This planning step performs no:

- standalone file copy, commit or push;
- tag or release;
- Module Validator run;
- Symcon access, update or reload;
- MQTT activation or credential retrieval;
- service restart;
- REST live request;
- mower command.

## 2. Fixed Architecture Boundary

Every later gate must preserve:

```text
REST public-state authority:       unchanged
MQTT direction:                    receive-only
MQTT publish path:                 absent
pilot diagnostics:                 internal and read-only
public diagnostic variables:       absent
Archive Control changes:           absent
feature default:                   disabled
inactive staging checkpoint timer: stopped
```

The diagnostic session begins with an actual validated MQTT connection
attempt. Merely enabling and staging the chain must not start the five-hour
timer.

The existing recovery contract remains:

```text
transient retries: exactly three bounded attempts
authentication error: no retry
configuration error: no retry
```

## 3. Offline Validation Baseline

The complete Navimow MQTT gate passed for the frozen candidate:

```text
sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

Result:

```text
fixtures:                    PASS
REST authentication:        PASS
MQTT parser and envelope:    PASS
Receiver and ingestion:      PASS
shadow diagnostics:          PASS
pilot checkpoint tests:      PASS
shadow reconciliation:       PASS
transport lifecycle:         PASS
distribution validation:     PASS
PHPCS:                       PASS
PHPStan:                     PASS
```

Focused test hashes:

| File | SHA-256 |
|---|---|
| `tests/mqtt-pilot-checkpoints.php` | `82fe0f41642e7d974435ead9344b2158acdbfc7305021cd100c3c451453e231d` |
| `tests/mqtt-transport-lifecycle.php` | `6e9db5c51f0604003e9eea787f8fd4cf058e2521d19ef917411ac4ea08e0873b` |

Any productive or test hash drift before publication returns the process to
step 229 review and the complete offline gate.

## 4. Standalone Baseline

Read-only local inspection established:

```text
repository:   private/navimow-publish-20260708
remote:       doctee/symcon-navimow
branch:       main
local HEAD:   3d223a9c24e396d4ba55ca40aede6742592fbe8f
origin/main:  3d223a9c24e396d4ba55ca40aede6742592fbe8f
subject:      feat(mqtt): expose bounded shadow diagnostics
worktree:     clean
```

This is a locally known baseline, not proof of the future remote state. Gate A
must fetch and prune immediately before publication and prove:

```text
branch == main
local HEAD == fetched origin/main
worktree == clean
```

Any remote drift or dirty standalone file stops Gate A.

## 5. Frozen Productive Candidate

Exactly three productive files differ between the clean standalone baseline
and the SAEF distribution:

| Path | Candidate SHA-256 | Candidate Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `c7726fbc1f822a85e714b122c912a9306f5d201cd140df04e8d018f15d36a12e` | `12658d4badd989580ee68431d577115339cbdf05` |
| `NavimowAccount/form.json` | `92cd3b4712821c84213e26761f12ac7b26ea17b7b8b6ed812c9df135f785704a` | `6e38ffd7dd1b267b27f03e406fbcdf4fb7b9de62` |
| `NavimowAccount/locale.json` | `fe12e326c77bcef5fab060aa117f4f85389177b564ec57723818e75a2fadd4a9` | `cb0d7ebf64f79eedf5a54c939036906aa708686e` |

The corresponding standalone baseline hashes are:

| Path | Baseline SHA-256 | Baseline Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `39fbc2183b0d5a119e2a4ba5cfdfcc81373b8a2f0b5be517a8c8cddb0cbbc069` | `2dce3f4651733e480dbeeec3fab713d80d0e3f01` |
| `NavimowAccount/form.json` | `2291ca4b9e07e305daa5dc94b22e7bb8ea9473324f2eec909ea3f96703979e63` | `05f136f1ed3c91f3f1c4683f6af450b67109e261` |
| `NavimowAccount/locale.json` | `a4cc9cd7dd0f71f78b0902e7796e432f7f33d89ab43e5f88f04f3d744564acd2` | `a9386726e9c8e5079a67e0f39e51311be65366e8` |

Exact baseline-to-candidate summary:

```text
3 files changed
703 insertions
0 deletions

module.php: 697 insertions
form.json:    5 insertions
locale.json:  1 insertion
```

No `library.json`, `module.json`, Device, Configurator, Receiver or library file
differs.

## 6. Artifact Exclusion

The SAEF distribution contains local `.DS_Store` files. They are not
productive artifacts and must never enter the standalone repository.

Gate A may copy only:

```text
distribution/NavimowAccount/module.php
  -> standalone/NavimowAccount/module.php

distribution/NavimowAccount/form.json
  -> standalone/NavimowAccount/form.json

distribution/NavimowAccount/locale.json
  -> standalone/NavimowAccount/locale.json
```

Unrestricted directory synchronization, recursive copy and archive replacement
are prohibited.

The publication excludes:

- `private/`;
- OAuth and MQTT evidence;
- pilot snapshots and state;
- tests, fixtures and tools;
- SAEF reports;
- local filesystem metadata;
- installation paths and ObjectIDs;
- device identities, topics, payloads and geometry.

## 7. Gate A: Standalone Publication

Gate A requires separate explicit authorization.

Recommended wording:

```text
Veröffentlichung der nativen MQTT-Pilotdiagnostik auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone remote;
2. prove clean local `main` equals fetched `origin/main`;
3. rerun the complete Navimow MQTT offline gate;
4. verify every frozen candidate and test hash;
5. copy exactly the three frozen Account files;
6. prove exactly three modified paths and no addition or deletion;
7. verify source and standalone SHA-256 and Git blob equality;
8. run PHP syntax, JSON, distribution, PHPCS and PHPStan checks;
9. run privacy and local-artifact scans;
10. inspect the complete three-file diff;
11. create one Conventional Commit;
12. push one fast-forward `main`;
13. fetch independently and verify the remote commit and all three blobs;
14. write private machine-readable and sanitized public evidence.

Suggested commit:

```text
feat(mqtt): add native pilot checkpoints
```

Gate A permits no:

- tag or release;
- Symcon update or module reload;
- Module Validator success claim before execution;
- MQTT activation, adoption or credential retrieval;
- service restart;
- live REST probe;
- mower command.

### Gate-A stop conditions

Stop before commit or push if:

- fetched `origin/main` differs from the verified baseline;
- the standalone worktree is dirty;
- a fourth path changes;
- any frozen hash differs;
- `.DS_Store` or private evidence appears;
- any test, syntax, JSON, PHPCS, PHPStan or distribution check fails;
- a public MQTT-state writer, publish path or changed recovery policy appears;
- private data is detected;
- the complete diff cannot be reviewed.

An ambiguous push result must be resolved by fetch and hash comparison. It
must not trigger a blind second push.

## 8. Gate A Remote Verification

Publication is incomplete until a fresh fetch proves:

- remote `main` equals the recorded publication commit;
- the commit changes exactly the three Account paths;
- all remote SHA-256 and Git blobs equal the frozen candidate;
- the remote tree contains no `.DS_Store` or private artifact;
- the standalone worktree is clean;
- no tag or release was created.

The sanitized publication report must record:

```text
baseline commit
published commit and subject
three changed paths
source, standalone and remote hashes
validation results
privacy result
side-effect count
```

## 9. Gate B: Metadata Conformance

Gate B begins only after Gate A passes and requires its own explicit
authorization.

The preferred path is the official Symcon Module Validator against the exact
published commit.

The established exact-official-schema fallback from step 204 remains
available only if the browser validator reproduces its known tool failure. A
cookie banner or mini-browser state may be removed before retrying, but its
presence is not by itself evidence of validator success or failure.

Required evidence:

- exact repository URL, branch and published commit;
- result for `library.json` and all four `module.json` files;
- form and locale JSON validity;
- exact metadata and official-schema hashes;
- validator engine/version;
- complete browser error text if the official UI fails;
- explicit distinction between browser-tool failure and schema conformance.

Gate B performs no Symcon mutation and authorizes no later gate automatically.

## 10. Gate C: Disabled Symcon Update

Gate C starts only after Gates A and B pass and requires separate explicit
authorization.

Recommended wording:

```text
Symcon-Update auf die native MQTT-Pilotdiagnostik mit deaktiviertem MQTT
freigegeben.
```

Permitted mutation:

```text
MC_UpdateModule(): 1
MC_ReloadModule(): 0
service restart:    0
ApplyChanges():     no separate MQTT activation
```

### Pre-update read-only gate

Current live state must be re-established. Historical step-226 evidence is not
a substitute.

The bounded pre-update projection must prove:

- installed repository, branch and commit;
- clean and valid module state;
- Account, Configurator, Device and Receiver compatibility;
- MQTT feature disabled;
- lifecycle `Disabled`;
- MQTT and WebSocket inactive;
- WebSocket `Active = false`;
- Authorization header absent;
- MQTT username and password absent;
- REST authentication operational;
- 14 retained variables with equal metadata;
- five retained Archive Control logging/aggregation contracts;
- archive history remains queryable;
- stable command, topology and subscription contracts.

Any credential-bearing, active or ambiguous state stops the update.

### Update and immediate verification

After exactly one supported update:

- installed commit equals the Gate-A commit;
- module repository remains clean and valid;
- all module instances remain compatible;
- MQTT remains disabled and credential-free;
- `GetMqttDiagnostics()` remains format version 2;
- `GetMqttPilotDiagnostics()` returns format version 1;
- `featureEnabled = false`;
- `active = false`;
- `nextCheckpointAt = 0`;
- checkpoint, episode and rotation arrays are bounded;
- `MqttPilotCheckpoint` remains inactive;
- the six Account and eight Device variable contracts remain unchanged;
- all five archive contracts remain unchanged;
- REST remains operational and authoritative;
- the Account form exposes `Show MQTT Pilot Diagnostics`.

Two equal read-only pilot-diagnostic calls must not mutate persistent state.

### Delayed disabled verification

After more than one MQTT lifecycle interval, repeat the bounded read-only
projection and prove:

- MQTT remains disabled and credential-free;
- no checkpoint session started;
- no connection, reconnect or credential-rotation counter advanced because of
  the new diagnostic timer;
- variables and archive contracts remain equal;
- REST remains operational.

Gate C permits no activation, adoption, Connect call, OAuth action, service
restart or mower command.

## 11. Rollback and Forward Correction

### Before push

Restore only the three standalone Account files from clean standalone `HEAD`.
Do not alter the SAEF candidate.

### After push but before Symcon update

Do not rewrite remote history. Correct the issue in SAEF and publish a reviewed
forward commit through a new gated step.

### After disabled Symcon update

If compatibility or diagnostics fail:

1. retain bounded read-only evidence;
2. keep MQTT disabled and credential-free;
3. do not call `MC_ReloadModule()`;
4. do not activate or restart;
5. prepare a reviewed forward correction;
6. use a Git revert only through a separately authorized publication and
   supported update.

No rollback path may enable MQTT or issue a mower command.

## 12. Evidence Closure

Every executed mutation gate requires:

- private machine-readable evidence;
- sanitized public SAEF report;
- exact commit and hash binding;
- explicit operation counts;
- explicit MQTT-disabled and credential-free result;
- explicit variable and archive compatibility result;
- explicit statement that no mower command occurred.

Live timestamps, Core configuration and installation metadata remain private.

## 13. Architecture Decisions

### AD-NAV-832: Publish exactly three Account files

The productive API, form action and translation are one reviewed unit. No
other standalone path differs or may be copied.

### AD-NAV-833: Bind pilot start to connection, not staging

Inactive staging must remain timerless. The first validated connection attempt
starts the persisted five-hour schedule.

### AD-NAV-834: Preserve the supported update path

Gate C permits one `MC_UpdateModule()` and explicitly prohibits
`MC_ReloadModule()` and service restart.

### AD-NAV-835: Require disabled credential-free installation

The new diagnostics must first prove compatibility while MQTT is disabled.
Publication does not authorize activation.

### AD-NAV-836: Validate metadata independently of publication

Remote blob equality and JSON syntax do not replace official metadata
conformance. Gate B remains separate.

### AD-NAV-837: Preserve variables and archive identities

The update must retain all 14 variables and five existing logging contracts.
No diagnostic variable or archive entry is introduced.

### AD-NAV-838: Use forward correction after publication

Published history is not rewritten. Any defect follows a new reviewed,
validated and separately authorized correction path.

## 14. Gate Decision

| Gate | State |
|---|---|
| offline implementation | PASS |
| candidate hashes | FROZEN |
| standalone baseline | LOCALLY VERIFIED; FETCH REQUIRED |
| Gate A publication | CLOSED |
| Gate B metadata validation | CLOSED |
| Gate C disabled Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| mower command | PROHIBITED |

## 15. Next Step

After explicit Gate-A authorization, execute:

```text
231-native-mqtt-pilot-diagnostics-publication.md
```

That step may publish and remotely verify exactly the three frozen Account
files. It may not access Symcon or activate MQTT.
