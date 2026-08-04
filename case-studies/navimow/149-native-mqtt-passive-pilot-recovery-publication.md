# 149 Native MQTT Passive Pilot Recovery Publication

**Case study:** Navimow native IP-Symcon module
**Status:** One-file recovery hardening published and remotely verified; all
Symcon and live gates closed
**Date:** 2026-07-28
**Scope:** Execute only publication Gate A from step 148

## 1. Purpose

Step 147 implemented bounded restart, OAuth rotation and reconnect recovery.
Step 148 froze the exact publication candidate and separated publication from
all Symcon and live pilot operations.

This step publishes only the productive Account implementation and proves
remote source integrity.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung der MQTT-Pilot-Recovery-Härtung auf main freigegeben.
```

This permitted one exact fast-forward publication to
`doctee/symcon-navimow` `main`.

It did not authorize:

- a Module Control update;
- inactive pilot staging;
- MQTT feature activation;
- credential retrieval or broker connection;
- a Symcon restart;
- token or connectivity experiments;
- MQTT publish or a mower command;
- a tag or release.

## 3. Revalidated Baseline

The standalone publication clone was clean and freshly fetched.

Before mutation:

```text
branch:      main
HEAD:        511c7bbe617ee92801a9d336b96254b9b6a6adda
origin/main: 511c7bbe617ee92801a9d336b96254b9b6a6adda
worktree:    clean
```

The complete Navimow MQTT offline gate passed before copying.

## 4. Exact Change

Modified:

```text
NavimowAccount/module.php
```

No file was added or deleted.

Delta:

```text
insertions: 588
deletions:  132
```

The published implementation adds:

- delayed credential-free reconstruction after `ApplyChanges()`;
- serialized lifecycle observation;
- controlled MQTT rotation after OAuth refresh;
- immediate cleanup after unexpected Core disconnect;
- reconnect delays of 60, 300 and 900 seconds;
- termination after three failed reconnect attempts;
- no retry for authentication or configuration failures;
- stable-health reset after 15 minutes;
- bounded lifecycle and recovery diagnostics;
- complete disable and cleanup behavior.

REST remains authoritative for all public mower variables.

Published source hash:

```text
4127b75e2dd451141a771f5244f185e43a7b4d3a158e6ddc2f59b630e562e48b
```

The standalone file is byte-equal to the canonical case-study distribution
source.

## 5. Validation

Before commit:

```text
frozen source hash:        PASS
exact one-file scope:      PASS
canonical byte equality:   PASS
standalone PHP syntax:     PASS
standalone JSON syntax:    PASS
PHPCS:                     PASS
PHPStan:                   PASS
complete MQTT regression: PASS
distribution validator:   PASS
git diff --check:          PASS
prohibited-path scan:      PASS
privacy review:            PASS
```

The complete regression covered REST authentication, native envelopes,
payload reduction, Receiver diagnostics, Account ingestion, targeted REST
reconciliation, lifecycle timing, token rotation, finite reconnect and
distribution structure.

## 6. Module Validator Classification

Only one PHP implementation file changed.

Unchanged:

```text
library.json
4 x module.json
4 x form.json
4 x locale.json
```

The metadata remains byte-identical to the previously validated standalone
module. Fresh JSON parsing and the SAEF distribution validator passed.

Classification:

```text
INHERITED EXACT SCHEMA EVIDENCE: PASS
NEW WEB VALIDATOR RESULT: NOT REQUIRED FOR UNCHANGED INPUT
```

## 7. Commit and Remote Verification

Published commit:

```text
7c1747ccd23a8aff9ddc8170d04f5030be615064
feat(mqtt): harden passive pilot recovery
```

Parent:

```text
511c7bbe617ee92801a9d336b96254b9b6a6adda
```

After push and a fresh fetch:

```text
local main:  7c1747ccd23a8aff9ddc8170d04f5030be615064
origin/main: 7c1747ccd23a8aff9ddc8170d04f5030be615064
worktree:    clean
```

Remote file hash:

```text
4127b75e2dd451141a771f5244f185e43a7b4d3a158e6ddc2f59b630e562e48b
```

Local commit, remote commit, canonical source and remote blob are exact.

## 8. Architecture Closure

### AD-NAV-519: Publish recovery before live activation

**Decision:** Productive recovery code is remotely immutable before any
installation or broker operation.

**Reason:** Symcon evidence can now identify one exact source commit and does
not depend on an unpublished workspace state.

### AD-NAV-520: Preserve separate mutation gates

**Decision:** Publication does not imply installation or pilot activation.

**Reason:** A source-integrity result cannot substitute for compatibility,
credential-cleanup or live transport evidence.

## 9. Side-Effect Boundary

This step performed:

```text
Git main publication: yes
Symcon mutation:      no
broker connection:    no
MQTT publish:         no
mower command:        no
tag or release:       no
MC_ReloadModule():    no
```

MQTT remains default-disabled.

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-passive-pilot-recovery-publication/
    publication-closure.json
```

The evidence contains no credential, endpoint, topic, payload, Client ID,
Device ID, ObjectID or installation detail.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A productive publication | PASS |
| exact remote source integrity | PASS |
| Gate B disabled Symcon update | CLOSED |
| Gate C inactive staging | CLOSED |
| Gate D passive activation | CLOSED |
| restart observation | CLOSED |
| natural token observation | CLOSED |
| degraded-connectivity observation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 12. Recommended Next Step

After separate Gate-B authorization, update Module Control once while MQTT
remains disabled and execute the bounded read-only compatibility projection.

Create:

```text
150-native-mqtt-passive-pilot-recovery-symcon-update.md
```

Required authorization:

```text
Symcon-Update auf die MQTT-Pilot-Recovery-Härtung mit read-only Prüfung freigegeben.
```
