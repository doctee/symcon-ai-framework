# 142 Native MQTT Subscription Schema Correction Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Productive one-file correction published and remotely verified;
all later gates closed
**Date:** 2026-07-28
**Scope:** Execute only Gate A from step 141

## 1. Purpose

Step 141 separated:

1. productive schema correction publication;
2. temporary V3 probe publication;
3. inactive Symcon staging;
4. one corrected receive-only live test.

This step executes only the first gate. It publishes the native MQTT Client
subscription-field correction without updating Symcon or contacting the
broker.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung der nativen MQTT-Subscription-Schema-Korrektur auf main freigegeben.
```

This authorized the exact one-file publication to
`doctee/symcon-navimow` `main`.

It did not authorize:

- the temporary V3 probe branch;
- a Module Control update;
- creation of a Symcon probe instance;
- credential retrieval or broker connection;
- MQTT publish or mower activity;
- a tag or release.

## 3. Revalidated Baseline

The standalone publication clone was clean and freshly fetched.

Before mutation:

```text
branch:      main
HEAD:        046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
worktree:    clean
```

The frozen SAEF source and V3 contract gate passed before copying.

## 4. Exact Change

Modified:

```text
libs/Navimow/MqttTransportConfiguration.php
```

No file was added or deleted.

The change:

- emits native MQTT Client subscriptions with `Topic` and `QoS`;
- accepts the exact old `Topic` and `QualityOfService` shape only for
  migration;
- normalizes accepted input to canonical `QoS`;
- rejects mixed, incomplete, extended or nonzero-QoS entries;
- leaves received MQTT envelope semantics unchanged.

Published source hash:

```text
f9d6b6b826849c5c1cb125a01167c3f7931bfff12355447e1d43eb7dfe7a022b
```

The standalone file is byte-equal to the canonical case-study distribution
source.

## 5. Validation

Before commit:

```text
frozen source hash:        PASS
exact one-file scope:      PASS
standalone PHP syntax:     PASS
standalone JSON syntax:    PASS
PHPCS:                     PASS
PHPStan:                   PASS
complete MQTT regression: PASS
distribution validator:   PASS
git diff --check:          PASS
prohibited-path scan:      PASS
```

The complete MQTT regression includes:

- fixtures;
- REST authentication;
- native envelope parsing;
- shadow payload mapping;
- Receiver diagnostics;
- Account ingestion;
- targeted REST reconciliation;
- transport lifecycle and legacy migration;
- distribution structure.

## 6. Module Validator Classification

This increment changes only one PHP library file.

These validator inputs are unchanged:

```text
library.json
4 x module.json
4 x form.json
4 x locale.json
```

They remain byte-identical to the exact schema set validated through the
official-schema fallback in step 113 and inherited in step 119.

Classification:

```text
INHERITED EXACT SCHEMA EVIDENCE: PASS
NEW WEB VALIDATOR RESULT: NOT REQUIRED FOR UNCHANGED INPUT
```

The current publication also passed fresh JSON parsing and the SAEF
distribution validator.

## 7. Commit and Remote Verification

Published commit:

```text
511c7bbe617ee92801a9d336b96254b9b6a6adda
fix(mqtt): use native QoS subscription field
```

Parent:

```text
046529c518feefb15a51bd2f1c404401b3a7f474
```

After push and a fresh fetch:

```text
local main:  511c7bbe617ee92801a9d336b96254b9b6a6adda
origin/main: 511c7bbe617ee92801a9d336b96254b9b6a6adda
worktree:    clean
```

The remote file hash is exactly:

```text
f9d6b6b826849c5c1cb125a01167c3f7931bfff12355447e1d43eb7dfe7a022b
```

Local commit, remote commit, canonical source and remote blob are consistent.

## 8. Architecture Closure

### AD-NAV-505: Publish a compatibility correction independently

**Decision:** The native `QoS` correction remains on productive `main`
independently of the later receive result.

**Reason:** It corrects the declared native Core configuration contract and
retains narrow migration support. A future zero-ingress result would show
that the correction is insufficient, not that `QualityOfService` was valid
Core configuration.

### AD-NAV-506: Do not update Symcon in the publication gate

**Decision:** Publication and live installation remain separate approvals.

**Reason:** Remote source integrity must be closed before any authorized live
mutation.

## 9. Side-Effect Boundary

This step performed:

```text
Git main publication: yes
Symcon mutation:      no
temporary branch:     no
broker connection:    no
MQTT publish:         no
mower command:        no
tag or release:       no
```

REST remains the only authority for public Device variables. MQTT remains
disabled by default.

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-subscription-schema-correction/
    publication-closure.json
```

The evidence contains no credential, endpoint, topic, payload, Client ID,
Device ID, ObjectID or installation detail.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A productive publication | PASS |
| exact remote source integrity | PASS |
| Gate B temporary probe publication | CLOSED |
| Gate C Symcon staging | CLOSED |
| Gate D broker connection | CLOSED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

## 12. Recommended Next Step

After separate Gate-B authorization, publish the unchanged five-file probe on
a temporary branch based on corrected commit
`511c7bbe617ee92801a9d336b96254b9b6a6adda`.

Required authorization:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-V3-Branches freigegeben.
```
