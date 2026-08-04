# 167 Native MQTT Core Resume Ordering Correction Publication

**Case study:** Navimow native IP-Symcon module
**Status:** One-file correction published and remotely verified; all Symcon
and live gates closed
**Date:** 2026-07-29
**Scope:** Execute only publication Gate A from step 166

## 1. Purpose

Step 165 implemented and validated the Core-resume startup-order correction.
Step 166 froze the candidate and separated publication from every Symcon and
live operation.

This step publishes only the productive Account implementation and proves
remote source integrity.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung der MQTT-Core-Resume-Ordering-Korrektur auf main freigegeben.
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
HEAD:        aed0b4348c6e104f6c2f455e71b861d8620a3c95
origin/main: aed0b4348c6e104f6c2f455e71b861d8620a3c95
worktree:    clean
```

All frozen candidate and supporting evidence hashes matched step 166. The
complete Navimow offline gate passed immediately before publication.

## 4. Exact Change

Modified:

```text
NavimowAccount/module.php
```

No file was added or deleted.

```text
insertions: 338
deletions:   16
```

The published correction provides:

- kernel-epoch precedence before destructive `ApplyChanges()` cleanup;
- timerless apply-first waiting until post-`KR_READY`
  `IPS_KERNELSTARTED`;
- message-first timer preservation;
- deferred token rotation during kernel reconciliation;
- bounded connection-trigger diagnostics;
- bounded Core-classification diagnostics;
- ownership-drift classification;
- unchanged same-kernel explicit restart behavior.

REST remains authoritative for public mower variables. MQTT remains
receive-only.

Published Account hash:

```text
3ec8c72bdbe68be434b3990e094d8dd3270b2d1ef694ecda04d3102051e9a63b
```

The standalone file is byte-equal to the canonical case-study source.

## 5. Validation

Before commit:

```text
frozen source hash:          PASS
supporting evidence hashes:  PASS
exact one-file scope:        PASS
canonical byte equality:     PASS
MQTT functional tests:       PASS
REST/auth tests:             PASS
distribution validator:      PASS
standalone PHP syntax:       PASS
standalone JSON syntax:      PASS
PHPCS:                       PASS
PHPStan 512 MB:              PASS
git diff --check:            PASS
prohibited-path scan:        PASS
privacy scan:                PASS
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
```

Classification:

```text
official web validator:       INCONCLUSIVE
local JSON/structure checks:  PASS
inherited metadata schema:    PASS
```

The web-tool failure is not recorded as a validator pass and is not a module
defect.

## 7. Commit and Remote Verification

Published commit:

```text
71a90f697031da017264d2a33555b9b6693d8776
fix(mqtt): preserve core resume across startup ordering
```

Parent:

```text
aed0b4348c6e104f6c2f455e71b861d8620a3c95
```

After push and a fresh fetch:

```text
local main:  71a90f697031da017264d2a33555b9b6693d8776
origin/main: 71a90f697031da017264d2a33555b9b6693d8776
worktree:    clean
```

Remote Account hash:

```text
3ec8c72bdbe68be434b3990e094d8dd3270b2d1ef694ecda04d3102051e9a63b
```

Local commit, remote commit, standalone source and canonical SAEF source are
exact.

## 8. Architecture Closure

### AD-NAV-576: Publish the post-ready barrier as one file

**Decision:** Publish only the frozen Account implementation.

**Reason:** The correction changes lifecycle ordering without changing module
metadata, topology, variables or public actions.

### AD-NAV-577: Treat validator runtime failure as inconclusive

**Decision:** Preserve the web-validator result separately from local source
and metadata validation.

**Reason:** A missing page dependency does not establish either module validity
or invalidity.

### AD-NAV-578: Stop after remote integrity proof

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
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| tags or releases | 0 |

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-ordering-correction-publication/
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
168-native-mqtt-core-resume-ordering-correction-symcon-update.md
```

Required authorization:

```text
Symcon-Update auf die MQTT-Core-Resume-Ordering-Korrektur mit deaktiviertem MQTT freigegeben.
```

Step 168 must leave MQTT disabled and credential-free. It must not activate
MQTT or restart the service.
