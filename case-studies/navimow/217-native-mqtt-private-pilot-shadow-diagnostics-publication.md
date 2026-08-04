# 217 Native MQTT Private Pilot Shadow Diagnostics Publication

**Case study:** Navimow native IP-Symcon module
**Status:** One-file publication passed and remotely verified; metadata
conformance, Symcon update and all pilot gates remain closed
**Date:** 2026-07-29
**Scope:** Execute Gate A from step 216

## 1. Purpose

Step 216 froze a one-file productive candidate and required separate
authorization for publication.

The user granted:

```text
Veröffentlichung der MQTT-Shadow-Diagnostik v2 auf main freigegeben.
```

This step:

1. revalidated the exact SAEF candidate;
2. fetched and verified the standalone remote baseline;
3. copied exactly one productive file;
4. validated the standalone candidate;
5. committed and pushed it to `main`;
6. independently fetched and verified the remote result;
7. closed private and sanitized publication evidence.

It performed no Module Validator run, Symcon access, MQTT activation, service
restart, REST live probe or mower action.

## 2. Revalidated SAEF Candidate

The complete Navimow offline gate passed immediately before publication:

```text
sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

Result:

```text
PASS
fixtures
REST client and authentication
MQTT envelope and parser
Receiver and Account ingestion
shadow diagnostics and reconciliation
transport lifecycle
distribution validation
PHPCS
PHPStan
```

The private pilot harness also passed:

```text
sh private/navimow-capture/native-mqtt-private-pilot/validate.sh
```

Result:

```text
PASS
syntax
synthetic-clock behavior
mutation scan
privacy scan
```

The frozen candidate hash remained:

```text
SHA-256: 39fbc2183b0d5a119e2a4ba5cfdfcc81373b8a2f0b5be517a8c8cddb0cbbc069
```

## 3. Fresh Standalone Baseline

The standalone checkout fetched and pruned `origin` before mutation.

Verified baseline:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
local HEAD: 8fdab84bd2a2190a6025cedd11f1ae6248369c0e
origin/main:8fdab84bd2a2190a6025cedd11f1ae6248369c0e
worktree:   clean
```

No remote drift was present.

## 4. Exact Publication Delta

Only this file was copied:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
  -> NavimowAccount/module.php
```

Standalone status after the copy:

```text
M NavimowAccount/module.php
```

Diff:

```text
1 file changed
151 insertions
1 deletion
```

Candidate binding:

```text
SHA-256:        39fbc2183b0d5a119e2a4ba5cfdfcc81373b8a2f0b5be517a8c8cddb0cbbc069
Git blob SHA-1: 2dce3f4651733e480dbeeec3fab713d80d0e3f01
```

No directory synchronization was used.

## 5. Artifact Exclusion

The publication included no:

- `.DS_Store`;
- private harness file;
- pilot state or snapshot;
- capture evidence;
- test or fixture;
- SAEF document;
- local path;
- device identity;
- MQTT topic or credential;
- coordinate or geometry.

The remote tree was enumerated after publication and contained only the
expected standalone module files.

## 6. Standalone Validation

Before commit:

| Check | Result |
|---|---|
| PHP syntax | PASS |
| PHPCS | PASS |
| PHPStan | PASS |
| distribution structure through SAEF gate | PASS |
| `git diff --check` | PASS |
| exact source/standalone SHA-256 equality | PASS |
| exact source/standalone Git blob equality | PASS |
| changed file count | `1` |
| privacy review | PASS |

The exact diff exposed only:

- `GetMqttDiagnostics()` format version 2;
- bounded `shadow.observation`;
- fixed allowlisted semantic fields;
- fail-closed state and range validation;
- no raw shadow, identity or geometry.

## 7. Published Commit

Publication:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
commit:     3d223a9c24e396d4ba55ca40aede6742592fbe8f
short:      3d223a9
subject:    feat(mqtt): expose bounded shadow diagnostics
```

Push result:

```text
8fdab84..3d223a9  main -> main
```

No tag or release was created.

## 8. Independent Remote Verification

After push, `origin` was fetched again.

Verified:

```text
HEAD:        3d223a9c24e396d4ba55ca40aede6742592fbe8f
origin/main: 3d223a9c24e396d4ba55ca40aede6742592fbe8f
HEAD blob:   2dce3f4651733e480dbeeec3fab713d80d0e3f01
remote blob: 2dce3f4651733e480dbeeec3fab713d80d0e3f01
SHA-256:     39fbc2183b0d5a119e2a4ba5cfdfcc81373b8a2f0b5be517a8c8cddb0cbbc069
worktree:    clean
```

The baseline-to-remote path comparison returned exactly:

```text
M NavimowAccount/module.php
```

The publication is therefore deterministic and complete.

## 9. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-private-pilot-shadow-diagnostics-publication/
  evidence-closure.json
```

It records:

- baseline and published commits;
- candidate SHA-256 and Git blob;
- validation results;
- remote verification;
- artifact exclusion;
- closed live gates.

The evidence contains no credential, device identity, topic, payload,
coordinate or installation ObjectID.

## 10. Safety Result

This publication:

- did not access Symcon;
- did not run or claim the Module Validator;
- did not update or reload a module;
- did not retrieve OAuth or MQTT credentials;
- did not activate MQTT;
- did not publish MQTT data;
- did not restart a service;
- did not send a mower command;
- did not create a tag or release.

The last accepted live state remains:

```text
installed module: main@8fdab84b
MQTT:             disabled
credentials:      absent from MQTT Core instances
REST authority:   retained
```

The published commit is not yet claimed as installed.

## 11. Architecture Decisions

### AD-NAV-786: Preserve the one-file publication boundary

**Decision:** Publish only the frozen Account implementation.

**Reason:** It was the sole content difference and prevented local or private
artifacts from entering the standalone repository.

### AD-NAV-787: Bind remote evidence to both commit and blob

**Decision:** Verify the fetched remote commit and exact file blob after push.

**Reason:** A successful push message alone does not prove the intended remote
content.

### AD-NAV-788: Stop before metadata and live gates

**Decision:** End this step after remote publication verification.

**Reason:** Gate-A authorization did not authorize validator execution, Symcon
mutation or MQTT use.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| frozen candidate revalidation | PASS |
| fresh remote baseline | PASS |
| one-file publication | PASS |
| remote commit verification | PASS |
| remote blob verification | PASS |
| private/local artifact exclusion | PASS |
| Gate B metadata conformance | CLOSED |
| Gate C disabled Symcon update | CLOSED |
| inactive pilot preflight | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| mower command | PROHIBITED |

## 13. Next Step

Proceed with:

```text
218-native-mqtt-private-pilot-shadow-diagnostics-metadata-conformance.md
```

That step should run the official Module Validator where functional, otherwise
the established exact-official-schema fallback from step 204, against the exact
published commit `3d223a9c24e396d4ba55ca40aede6742592fbe8f`.

It must perform no Symcon update or MQTT activation.
