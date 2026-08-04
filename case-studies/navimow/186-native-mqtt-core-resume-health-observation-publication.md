# 186 Native MQTT Core Resume Health Observation Publication

**Case study:** Navimow native IP-Symcon module
**Status:** One-file correction published and remotely verified; all Symcon
and live gates closed
**Date:** 2026-07-29
**Scope:** Execute only publication Gate A from step 185

## 1. Purpose

Step 184 implemented bounded native Core-health observation. Step 185 froze
the exact candidate and separated publication from installation and live
testing.

This step publishes only:

```text
NavimowAccount/module.php
```

and proves remote source integrity.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung der MQTT-Core-Resume-Health-Observation auf main freigegeben.
```

This authorized one fast-forward publication to
`doctee/symcon-navimow` `main`.

It did not authorize:

- a Symcon Module Control update;
- inactive staging or MQTT activation;
- credential retrieval or broker connection;
- a service restart;
- MQTT publish or mower command;
- a tag or release.

## 3. Revalidated Baseline

The standalone repository was freshly fetched before mutation.

```text
branch:      main
HEAD:        7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
origin/main: 7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
worktree:    clean
```

Published Account SHA-256:

```text
731e882aea21c5a6cd2d15b4a30e9857598c2876111d14d79e533a5843b9cdc5
```

All five frozen candidate and regression hashes from step 185 matched.

## 4. Exact Change

Modified:

```text
NavimowAccount/module.php
```

No file was added or deleted.

```text
insertions: 315
deletions:    9
```

The correction:

- adds absolute `+15/+30/+60/+90 s` Core observations;
- introduces `CoreResumeObserving`;
- preserves a valid retained Core before the deadline;
- adopts healthy Core resume without an Account reconnect;
- executes one existing bounded recovery at the deadline;
- retains at most four privacy-safe pre-cleanup projections;
- preserves immediate disable, authentication, configuration and ownership
  gates;
- keeps REST authoritative and MQTT receive-only.

Published Account SHA-256:

```text
1bbc18327564bca52a9257f11485b4b8c9340e2f5f51e5066caa4fec253d79d7
```

The standalone file is byte-equal to the canonical SAEF source.

## 5. Validation

Before commit:

```text
frozen source hash:             PASS
supporting regression hashes:  PASS
exact one-file scope:           PASS
canonical byte equality:        PASS
complete Navimow MQTT gate:     PASS
pilot observation harness:      PASS
standalone PHP syntax:          PASS
standalone JSON syntax:         PASS
PHPCS:                          PASS
PHPStan 512 MB:                 PASS
git diff --check:               PASS
privacy scan:                   PASS
forbidden operation scan:       PASS
```

No `MC_ReloadModule()`, automatic Core create/delete operation, MQTT publish
path or mower-command path was introduced.

## 6. Symcon Module Validator

The official public validator was opened with the unchanged public
`library.json` and invoked.

It rendered no validation result. The page console again reported:

```text
ReferenceError: $ is not defined at SetSchema
ReferenceError: $ is not defined at SetOutput
```

Classification:

```text
official web validator:       INCONCLUSIVE
local JSON/structure checks:  PASS
inherited metadata schema:    PASS
```

The public webtool runtime failure is neither a validator pass nor a module
defect.

## 7. Commit and Remote Verification

Published commit:

```text
45c7bd509f95865030f676184a1aeff4219c0750
fix(mqtt): observe native core readiness after restart
```

Parent:

```text
7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
```

After push and fresh fetch:

```text
local main:  45c7bd509f95865030f676184a1aeff4219c0750
origin/main: 45c7bd509f95865030f676184a1aeff4219c0750
worktree:    clean
```

Local and remote tree:

```text
c37f43a201d7f19c153478fd2e3155d87111ed47
```

Local and remote Account blob:

```text
46cde25e2b94f0dfd0d8638de0dd069d6f8083bc
```

Remote source SHA-256:

```text
1bbc18327564bca52a9257f11485b4b8c9340e2f5f51e5066caa4fec253d79d7
```

Local commit, remote commit, blob and canonical SAEF source are exact.

## 8. Architecture Closure

### AD-NAV-655: Publish only the Account implementation

**Decision:** Publish exactly one productive file.

**Reason:** The correction changes only Account-owned restart lifecycle and
diagnostics.

### AD-NAV-656: Keep the published hash inventory unchanged in SAEF

**Decision:** Do not mechanically rewrite the existing standalone snapshot
manifest in this publication step.

**Reason:** That manifest contains a broader pre-existing distribution
snapshot and is not the one-file publication source of truth. Commit, blob,
remote SHA-256 and this report provide the current publication evidence.

### AD-NAV-657: Keep validator runtime failure distinct

**Decision:** Record the official webvalidator as `INCONCLUSIVE`.

**Reason:** Its missing page dependency establishes neither module validity nor
invalidity.

### AD-NAV-658: Stop after remote integrity proof

**Decision:** Gate A ends after a fresh fetch and exact remote verification.

**Reason:** Publication does not imply installation or live-test
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
  native-mqtt-core-resume-health-observation-publication/
    publication-closure.json
```

The public report contains no credential, private topic, endpoint, payload,
device identity, ObjectID, hostname, IP address or garden detail.

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

After separate authorization, execute only the disabled Symcon update:

```text
187-native-mqtt-core-resume-health-observation-symcon-update.md
```

Required authorization:

```text
Symcon-Update auf die MQTT-Core-Resume-Health-Observation mit deaktiviertem MQTT freigegeben.
```

Step 187 must leave MQTT disabled and credential-free. It must not activate
MQTT or restart the service.
