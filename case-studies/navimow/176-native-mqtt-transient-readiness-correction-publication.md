# 176 Native MQTT Transient Readiness Correction Publication

**Case study:** Navimow native IP-Symcon module
**Status:** One-file correction published and remotely verified; all Symcon
and live gates closed
**Date:** 2026-07-29
**Scope:** Execute only publication Gate A from step 175

## 1. Purpose

Step 174 implemented and offline-verified the durable transient-readiness
barrier. Step 175 froze the candidate and separated publication from every
Symcon and live operation.

This step publishes only the productive Account implementation and proves
remote source integrity.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung der MQTT-Transient-Readiness-Korrektur auf main freigegeben.
```

This authorized one fast-forward publication to
`doctee/symcon-navimow` `main`.

It did not authorize:

- a Symcon Module Control update;
- MQTT staging or activation;
- credential retrieval or broker connection;
- a Symcon service restart;
- MQTT publish or mower command;
- a tag or release.

## 3. Revalidated Baseline

The standalone publication clone was freshly fetched and clean.

Before mutation:

```text
branch:      main
HEAD:        71a90f697031da017264d2a33555b9b6693d8776
origin/main: 71a90f697031da017264d2a33555b9b6693d8776
worktree:    clean
```

All five frozen candidate and evidence hashes matched step 175. The complete
Navimow MQTT gate and pilot observation harness passed immediately before
publication.

## 4. Exact Change

Modified:

```text
NavimowAccount/module.php
```

No file was added or deleted.

```text
insertions: 58
deletions:   16
```

The published correction:

- detects changed-kernel precedence from durable state only;
- performs no semantic Core read or transport mutation while the pre-ready
  barrier owns startup;
- preserves that barrier across invalid local configuration and unavailable
  authentication returns;
- continues a pending reconciliation or waits timerless for the ready message;
- classifies invalid local configuration after the post-ready grace period;
- retains the healthy native-Core adoption path without an Account reconnect.

REST remains authoritative for public mower variables. MQTT remains
receive-only.

Published Account SHA-256:

```text
731e882aea21c5a6cd2d15b4a30e9857598c2876111d14d79e533a5843b9cdc5
```

The standalone file is byte-equal to the canonical case-study source.

## 5. Validation

Before commit:

```text
frozen source hash:             PASS
supporting evidence hashes:     PASS
exact one-file scope:           PASS
canonical byte equality:        PASS
MQTT functional tests:          PASS
pilot observation harness:      PASS
REST/auth tests:                PASS
distribution validator:         PASS
standalone PHP syntax:          PASS
standalone JSON syntax:         PASS
PHPCS:                          PASS
PHPStan 512 MB:                 PASS
git diff --check:               PASS
privacy scan:                   PASS
```

No MQTT publish path, automatic Core create/delete path or
`MC_ReloadModule()` call was introduced.

## 6. Module Validator

Only one PHP implementation file changed. All library, module, form and locale
metadata remained unchanged.

The official Symcon Module Validator was opened with the unchanged public
`library.json` and invoked. It rendered no validation result. The page console
reported:

```text
ReferenceError: $ is not defined
  at SetSchema
  at SetOutput
```

Classification:

```text
official web validator:       INCONCLUSIVE
local JSON/structure checks:  PASS
inherited metadata schema:    PASS
```

The web-tool runtime failure is not recorded as a validator pass and is not a
module defect. No cookie-popup interaction was involved in the observed
failure signature.

## 7. Commit and Remote Verification

Published commit:

```text
7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
fix(mqtt): defer core validation until kernel readiness
```

Parent:

```text
71a90f697031da017264d2a33555b9b6693d8776
```

After push and a fresh fetch:

```text
local main:  7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
origin/main: 7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
worktree:    clean
```

Local and remote Account blob:

```text
0e549c1b10150088113036bc146815f7e77beba8
```

Local commit, remote commit, standalone source and canonical SAEF source are
exact.

## 8. Architecture Closure

### AD-NAV-614: Publish the durable barrier as one file

**Decision:** Publish only the frozen Account implementation.

**Reason:** The correction changes lifecycle ordering without changing module
metadata, topology, variables or public actions.

### AD-NAV-615: Keep validator runtime failure distinct

**Decision:** Record the official web-validator result as `INCONCLUSIVE` while
retaining successful local validation.

**Reason:** A missing page dependency establishes neither module validity nor
invalidity.

### AD-NAV-616: Stop after remote integrity proof

**Decision:** Gate A ends after fresh-fetch commit and blob verification.

**Reason:** Publication does not imply installation, activation or restart
authorization.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| publication commits | 1 |
| publication pushes | 1 |
| modified productive files | 1 |
| Symcon Module Control updates | 0 |
| `MC_ReloadModule()` | 0 |
| MQTT activations | 0 |
| broker connections | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| tags or releases | 0 |

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-transient-readiness-correction-publication/
    publication-closure.json
```

No credential, topic, endpoint, payload, Device ID, ObjectID or installation
detail appears in this public report.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| remote source integrity | PASS |
| official web validator | INCONCLUSIVE |
| local source and metadata validation | PASS |
| Gate B disabled Symcon update | CLOSED |
| Gate C inactive staging | CLOSED |
| Gate D renewed persistence acceptance | NOT GIVEN |
| Gate E MQTT activation | CLOSED |
| Gate F active restart | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 12. Recommended Next Step

After separate authorization, execute only the disabled Symcon update and
create:

```text
177-native-mqtt-transient-readiness-correction-symcon-update.md
```

Required authorization:

```text
Symcon-Update auf die MQTT-Transient-Readiness-Korrektur mit deaktiviertem MQTT freigegeben.
```

Step 177 must leave MQTT disabled and credential-free. It must not activate
MQTT or restart the service.
