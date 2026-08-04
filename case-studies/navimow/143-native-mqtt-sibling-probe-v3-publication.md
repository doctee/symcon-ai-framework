# 143 Native MQTT Sibling Probe V3 Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary V3 probe branch published and remotely verified; Symcon
and live gates closed
**Date:** 2026-07-28
**Scope:** Execute only Gate B from step 141

## 1. Purpose

Step 142 closed the productive one-file `QoS` correction as an independent
historical publication result.

This step executes the separately authorized temporary publication gate:

1. starts from the verified corrected `main`;
2. adds the unchanged five-file receive-only sibling probe;
3. validates the probe and private V3 harness;
4. publishes only a temporary experiment branch;
5. proves productive `main` unchanged;
6. stops before every Symcon or broker operation.

Because Gate B was authorized only after step 142 had been closed, this report
is a separate step. The planned Symcon staging and live restoration reports
move to steps 144 and 145 without changing their gate boundaries.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-V3-Branches freigegeben.
```

This authorized one temporary Git branch and its mandatory later deletion.

It did not authorize:

- a Module Control update;
- creation or activation of a Symcon probe instance;
- credential retrieval;
- MQTT enablement or broker connection;
- MQTT publish or mower activity;
- a merge, tag, pull request or productive `main` change.

## 3. Verified Base

Before branch creation, the standalone clone was freshly fetched.

```text
branch:      main
HEAD:        511c7bbe617ee92801a9d336b96254b9b6a6adda
origin/main: 511c7bbe617ee92801a9d336b96254b9b6a6adda
worktree:    clean
```

The local and remote V3 branch names were absent.

The base commit is the corrected productive publication from step 142.

## 4. Temporary Branch

Published branch:

```text
experiment/native-mqtt-sibling-cross-probe-v3-20260728
```

Commit:

```text
b126ec1691b36f93763ea5fce6b35a662d01d00d
test: add temporary MQTT sibling receive probe v3
```

Parent:

```text
511c7bbe617ee92801a9d336b96254b9b6a6adda
```

The branch is not intended for merge or tagging.

## 5. Exact Change Set

Added:

```text
NavimowMqttReceiveProbe/
  MqttReceiveProbeReducer.php
  form.json
  locale.json
  module.json
  module.php
```

Git classification:

```text
added:    5
modified: 0
deleted:  0
```

No productive Account, Configurator, Device, Receiver, library or metadata
file changed.

## 6. Frozen Source Verification

```text
MqttReceiveProbeReducer.php:
06ee2e2408e645ba5d18490c30c9ceb2b303d25c929cb1e8d7ac50f3b91a48c9

form.json:
8280311f8ee8195682f73f62e87409ac2e8c09aea2a71aebeac847efadcdbddc

locale.json:
7ad31ae4213a95e25d515c35006e9271864d6ea2fa99cdd60bed9bd2847b4b61

module.json:
d7d5de3e18f00579db87a7cf8eb5937df17faf6932ea48d9ed2cf723d30de600

module.php:
408f16cf2755b1d80b8527a6c1fb3a4dce4a7882fdcc4743122ad403304f1e1e
```

All copied and remote blob hashes match the frozen sources.

## 7. Validation

Before commit and push:

```text
exact five-file source:    PASS
probe functional tests:   PASS
V3 harness contract:      PASS
PHP syntax:               PASS
JSON syntax:              PASS
PHPCS:                     PASS
PHPStan:                   PASS
git diff --check:          PASS
receive-only path scan:    PASS
```

The published probe contains no:

- MQTT publish path;
- mower command;
- `RequestAction()`;
- Core instance creation or deletion;
- `MC_ReloadModule()`.

## 8. Module Validator Classification

The five probe files are byte-identical to the already validated disposable
and sibling-probe source set used in the earlier native MQTT experiments.

The three JSON files passed fresh parsing. The productive module metadata on
the corrected base commit is unchanged from step 142.

Classification:

```text
INHERITED EXACT PROBE SCHEMA EVIDENCE: PASS
FRESH JSON PARSING:                    PASS
NEW WEB VALIDATOR RESULT:             NOT REQUIRED FOR IDENTICAL INPUT
```

## 9. Remote Verification

After push and a fresh fetch:

```text
local experiment:
b126ec1691b36f93763ea5fce6b35a662d01d00d

remote experiment:
b126ec1691b36f93763ea5fce6b35a662d01d00d

origin/main:
511c7bbe617ee92801a9d336b96254b9b6a6adda
```

All five remote blob hashes are exact. Productive `main` remained unchanged.

## 10. Architecture Closure

### AD-NAV-507: Preserve publication reports as separate gate evidence

**Decision:** Record Gate B in a new step instead of revising the closed
Gate-A report.

**Reason:** Each explicit authorization retains an immutable and auditable
side-effect boundary.

### AD-NAV-508: Keep the probe branch disposable

**Decision:** The V3 probe remains an unmerged temporary branch.

**Reason:** It exists only to discriminate child ingress during one bounded
test and is not productive module functionality.

## 11. Side-Effect Boundary

This step performed:

```text
temporary Git branch: yes
productive main change: no
Symcon mutation:        no
probe instance:         no
credential retrieval:   no
broker connection:      no
MQTT publish:           no
mower command:          no
tag or release:         no
```

REST remains the only authority for public Device variables.

## 12. Mandatory Later Cleanup

After the V3 live-test evidence is closed:

1. restore Module Control to corrected `main`;
2. prove runtime cleanup and compatibility;
3. delete the temporary branch locally;
4. delete the temporary branch remotely;
5. verify its absence after a fresh fetch.

No branch deletion is performed in this publication step because Gate C still
requires the exact remote branch.

## 13. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe-v3/
    publication-closure.json
```

No credential, endpoint, topic, payload, Client ID, Device ID, ObjectID or
installation detail is included.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| Gate A productive correction | PASS |
| Gate B temporary V3 publication | PASS |
| remote source integrity | PASS |
| productive `main` unchanged | PASS |
| Gate C Symcon staging | CLOSED |
| Gate D broker connection | CLOSED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

## 15. Recommended Next Step

After explicit Gate-C authorization, execute the complete read-only baseline,
one supported Module Control update and exactly one inactive sibling staging.

Planned report:

```text
144-native-mqtt-subscription-schema-correction-symcon-staging.md
```

Required authorization:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-V3-Staging freigegeben.
```
