# 157 Native MQTT Kernel Start Reconciliation Publication

**Case study:** Navimow native IP-Symcon module
**Status:** One-file kernel-start reconciliation published and remotely
verified; all Symcon and live gates closed
**Date:** 2026-07-28
**Scope:** Execute only publication Gate A from step 156

## 1. Purpose

Step 155 implemented post-ready observation and adoption of a native MQTT
transport resumed by IP-Symcon Core. Step 156 froze the exact candidate and
separated publication from installation, activation and restart testing.

This step publishes only the productive Account implementation and proves
remote source integrity.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung der Kernelstart-Reconciliation auf main freigegeben.
```

This permitted one exact fast-forward publication to
`doctee/symcon-navimow` `main`.

It did not authorize:

- a Module Control update;
- MQTT staging or activation;
- credential retrieval or a broker connection;
- a Symcon service restart;
- MQTT publish or a mower command;
- a tag or release.

## 3. Revalidated Baseline

The standalone publication clone was clean and freshly fetched.

Before mutation:

```text
branch:      main
HEAD:        7c1747ccd23a8aff9ddc8170d04f5030be615064
origin/main: 7c1747ccd23a8aff9ddc8170d04f5030be615064
worktree:    clean
```

The frozen candidate and all supporting evidence hashes matched step 156.
The complete Navimow MQTT offline gate passed before publication.

## 4. Exact Change

Modified:

```text
NavimowAccount/module.php
```

No file was added or deleted.

Delta:

```text
insertions: 256
deletions:  2
```

The published implementation adds:

- registration for `IPS_KERNELSTARTED`;
- mutation-free scheduling from `MessageSink()`;
- delayed reconciliation after kernel readiness;
- adoption of a healthy Core-resumed MQTT transport without reconnect;
- credential-free reconstruction when no transport resumed;
- bounded cleanup and recovery for an unhealthy owned transport;
- terminal handling of authentication, configuration and ownership failures;
- separate privacy-safe Core-resume diagnostics.

REST remains authoritative for all public mower variables. MQTT remains a
receive-only acceleration hint.

Published source hash:

```text
544a594569c63aaf942e455fed6fdecc163d404710cb338876e91362ed06e440
```

The standalone file is byte-equal to the canonical case-study distribution
source.

## 5. Validation

Before commit:

```text
frozen source hash:          PASS
supporting evidence hashes:  PASS
exact one-file scope:        PASS
canonical byte equality:     PASS
standalone PHP syntax:       PASS
standalone JSON syntax:      PASS
PHPCS:                       PASS
project PHPStan scope:       PASS
complete MQTT regression:   PASS
distribution validator:     PASS
git diff --check:            PASS
prohibited-path scan:        PASS
privacy review:              PASS
```

A direct ad-hoc PHPStan invocation outside the defined project scope was
discarded because it omitted the repository's intended symbol and harness
configuration. The authoritative project PHPStan gate was then run with
`--memory-limit=512M` and completed with no errors.

## 6. Module Validator Classification

Only one PHP implementation file changed.

Unchanged:

```text
library.json
4 x module.json
4 x form.json
4 x locale.json
```

The official Symcon Module Validator page was opened, populated with unchanged
public module metadata and invoked. The embedded browser rendered no validation
result. No validation error was reported.

Classification:

```text
OFFICIAL WEB VALIDATOR:        INCONCLUSIVE
INHERITED EXACT SCHEMA EVIDENCE: PASS
LOCAL JSON AND STRUCTURE GATES:  PASS
```

`INCONCLUSIVE` is not recorded as a validator pass and is not treated as a
module defect. The unchanged metadata remains covered by fresh JSON parsing
and the SAEF distribution validator.

## 7. Commit and Remote Verification

Published commit:

```text
aed0b4348c6e104f6c2f455e71b861d8620a3c95
fix(mqtt): reconcile native transport after kernel start
```

Parent:

```text
7c1747ccd23a8aff9ddc8170d04f5030be615064
```

After push and a fresh fetch:

```text
local main:  aed0b4348c6e104f6c2f455e71b861d8620a3c95
origin/main: aed0b4348c6e104f6c2f455e71b861d8620a3c95
worktree:    clean
```

Remote file hash:

```text
544a594569c63aaf942e455fed6fdecc163d404710cb338876e91362ed06e440
```

Local commit, remote commit, canonical source and remote blob are exact.

## 8. Architecture Closure

### AD-NAV-541: Publish reconciliation before installation

**Decision:** The kernel-start reconciliation is remotely immutable before any
Symcon update or restart observation.

**Reason:** Every later runtime result can be attributed to one exact source
commit.

### AD-NAV-542: Keep validator uncertainty separate from source validity

**Decision:** A web tool that renders no result remains `INCONCLUSIVE`; local
exact-schema, JSON and structure evidence is reported separately.

**Reason:** This preserves the distinction between tool availability and an
actual module validation result.

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
  native-mqtt-kernel-start-reconciliation-publication/
    publication-closure.json
```

The evidence contains no credential, endpoint, topic, payload, Client ID,
Device ID, ObjectID or installation detail.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A productive publication | PASS |
| exact remote source integrity | PASS |
| official web validator | INCONCLUSIVE |
| inherited exact metadata evidence | PASS |
| Gate B disabled Symcon update | CLOSED |
| Gate C disabled kernel-hook verification | CLOSED |
| Gate D bounded active restart verification | CLOSED |
| MQTT state authority | PROHIBITED |

The next independently authorized action is Gate B from step 156:

```text
Symcon-Update auf die Kernelstart-Reconciliation mit deaktiviertem MQTT freigegeben.
```
