# 136 Native MQTT Sibling Cross-Probe V2 Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary V2 probe branch published and remotely verified; Symcon
gate closed
**Date:** 2026-07-28
**Scope:** Execute Gate A from step 135 without updating Symcon or connecting
to the broker

## 1. Purpose

Step 135 defined separate gates for temporary source publication, inactive
Symcon staging and one supervised V2 live retest.

This step executes only Gate A:

1. revalidates standalone `main` and all frozen sources;
2. creates a new temporary branch;
3. adds exactly the unchanged five-file receive probe;
4. validates the complete staged change;
5. commits and pushes only that branch;
6. verifies the remote commit and every remote blob;
7. proves productive `main` unchanged;
8. stops before any Symcon operation.

## 2. Authorization

The user explicitly authorized:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-V2-Branches freigegeben.
```

This authorized one temporary Git branch and its mandatory later deletion.

It did not authorize:

- a Module Control update;
- creation of a Symcon probe instance;
- credential retrieval;
- MQTT enablement or broker connection;
- mower activity;
- a merge, tag or pull request.

## 3. Pre-Publication Baseline

The publication clone was fetched and pruned before branch creation.

Verified:

```text
branch:      main
HEAD:        046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
worktree:    clean
target branch locally:  absent
target branch remotely: absent
```

The complete V2 harness gate passed, including the productive Connect contract
regression introduced in step 134.

## 4. Temporary Branch

Created:

```text
experiment/native-mqtt-sibling-cross-probe-v2-20260728
```

Commit:

```text
a32146a6932578ee4dc554ffe9ebfbfba6ef00b1
test: republish temporary MQTT sibling receive probe
```

The commit is a direct child of the verified `main` baseline.

## 5. Exact Change Set

Only the following directory was added:

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

No productive Account, Configurator, Device, Receiver, library or metadata file
changed.

## 6. Validation

Before commit and push:

| Check | Result |
|---|---|
| corrected V2 contract regression | PASS |
| historical V1 hash guard | PASS |
| probe PHP syntax | PASS |
| PHPCS | PASS |
| JSON metadata decoding | PASS |
| exact source comparison | PASS |
| complete staged diff review | PASS |
| private-material scan | PASS |

The probe remains receive-only and contains no publish or mower-command path.

## 7. Remote Verification

After push and a fresh remote fetch:

```text
local branch:
a32146a6932578ee4dc554ffe9ebfbfba6ef00b1

remote branch:
a32146a6932578ee4dc554ffe9ebfbfba6ef00b1

origin/main:
046529c518feefb15a51bd2f1c404401b3a7f474
```

Local and remote experiment commits are equal. Productive `main` is unchanged.

Remote blob hashes:

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

All five hashes equal the frozen step-135 sources.

## 8. Side-Effect Boundary

This step performed:

```text
Symcon mutations:   0
broker connections: 0
MQTT publishes:     0
mower commands:     0
tags:               0
pull requests:      0
```

The publication clone remains on the temporary branch only to support the
later mandatory cleanup. The branch has not been merged.

## 9. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe-v2/
    publication-closure.json
```

No credential, token, private endpoint, topic, payload, Client ID, Device ID,
ObjectID or garden detail appears in this public report.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate A temporary publication | PASS |
| remote source integrity | PASS |
| productive `main` unchanged | PASS |
| Gate B Symcon staging | CLOSED |
| Gate C broker attempt | CLOSED |
| REST state authority | RETAINED |

## 11. Recommended Next Step

After explicit Gate-B authorization, create:

```text
137-native-mqtt-sibling-cross-probe-v2-symcon-staging-report.md
```

Required authorization:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-V2-Staging freigegeben.
```

That step must capture the complete read-only baseline twice, update Module
Control once, prove productive compatibility, stage exactly one inactive probe
and stop before credential retrieval or broker connection.
