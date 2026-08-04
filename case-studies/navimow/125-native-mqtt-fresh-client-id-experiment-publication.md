# 125 Native MQTT Fresh-Client-ID Experiment Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary experiment branch published and remotely verified;
Symcon and live gates closed
**Date:** 2026-07-28
**Scope:** Execute Gate A from step 124 without changing `main` or Symcon

## 1. Purpose

Step 124 defined a temporary one-file branch for the fresh-client-ID
experiment.

This step:

1. revalidates the exact standalone baseline;
2. creates the dated temporary branch;
3. applies the frozen private patch;
4. repeats all candidate and regression gates;
5. commits and pushes exactly one productive file;
6. verifies the remote commit and blob;
7. proves `origin/main` unchanged;
8. stops before any Symcon update or broker connection.

## 2. Authorization

The user explicitly authorized Gate A:

```text
Veröffentlichung des temporären Fresh-Client-ID-Branches freigegeben.
```

This authorized temporary branch creation, publication, remote verification
and later deletion after complete Symcon restoration.

It did not authorize:

- a Symcon Module Control update;
- invoking either temporary wrapper;
- enabling MQTT;
- a broker connection;
- mower activity or a mower command.

## 3. Revalidated Baseline

The standalone publication clone was fetched and pruned before mutation.

Verified:

```text
branch:
main

local HEAD:
046529c518feefb15a51bd2f1c404401b3a7f474

origin/main:
046529c518feefb15a51bd2f1c404401b3a7f474

subject:
feat: expose bounded MQTT Receiver diagnostics

worktree:
clean
```

The planned experiment branch existed neither locally nor remotely.

The standalone and canonical Account modules were byte-equal:

```text
df820993599dded7962ae2998345db9694d44146ad33793a893e75454322fc3a
```

## 4. Temporary Branch

Created directly from the verified `origin/main`:

```text
experiment/native-mqtt-fresh-client-id-20260728
```

The frozen patch was applied from:

```text
private/navimow-capture/fresh-client-id-experiment/account-module.patch
```

Git reported exactly:

```text
NavimowAccount/module.php
```

Delta:

```text
1 file changed
208 insertions
```

The only other full-tree comparison findings were pre-existing local
`.DS_Store` files in the canonical case-study distribution. They are excluded
from the standalone module and were not published.

## 5. Candidate Verification

Expected and actual Account candidate hash:

```text
04a69a573af052551e6e8202d4dd1057eeac063ef01128b9b52f4f89cc8aba2c
```

The source contained exactly:

- one temporary fresh-client-ID Connect method;
- one temporary Restore method;
- one private restore implementation.

It contained no:

- MQTT publish;
- mower action;
- automatic instance creation or deletion;
- module reload;
- form action;
- persistent identity rotation;
- ownership rewrite during active experiment setup.

## 6. Validation

Executed before commit:

```text
sh private/navimow-capture/fresh-client-id-experiment/validate.sh
case-studies/navimow/tools/check-mqtt-shadow.sh
```

Passed:

- deterministic patch application;
- candidate PHP syntax;
- private test and live-harness syntax;
- synthetic fresh-ID positive path;
- exact temporary ownership mismatch;
- exact stable-ID restoration;
- credential-failure rollback before activation;
- PHPCS;
- PHPStan;
- MQTT fixtures;
- REST authentication;
- native envelope parsing;
- shadow payload parsing;
- Receiver diagnostics;
- Account ingestion;
- REST reconciliation;
- transport lifecycle;
- strict distribution validation;
- staged diff check;
- private-material scan.

Direct PHPCS and PHPStan analysis against the standalone patched Account file
also passed.

## 7. Commit

Created:

```text
commit:
7e1ce7a97cb2294368ac02ce466dc2950e184026

parent:
046529c518feefb15a51bd2f1c404401b3a7f474

subject:
test: add temporary MQTT fresh-client-ID experiment
```

The commit contains exactly:

```text
M NavimowAccount/module.php
```

No tag or pull request was created.

## 8. Remote Publication

Pushed only:

```text
experiment/native-mqtt-fresh-client-id-20260728
```

Independent fetch and read-back proved:

```text
local branch commit:
7e1ce7a97cb2294368ac02ce466dc2950e184026

remote branch commit:
7e1ce7a97cb2294368ac02ce466dc2950e184026
```

Remote Account blob:

```text
04a69a573af052551e6e8202d4dd1057eeac063ef01128b9b52f4f89cc8aba2c
```

The local and remote experiment branches are byte-equal.

## 9. Main Protection

After publication:

```text
origin/main:
046529c518feefb15a51bd2f1c404401b3a7f474
```

Remote `main` Account blob:

```text
df820993599dded7962ae2998345db9694d44146ad33793a893e75454322fc3a
```

Therefore:

- `main` did not advance;
- the diagnostic methods are not on `main`;
- no release tag references the experiment commit;
- no merge occurred.

## 10. Private Evidence

The machine-readable publication closure is stored at:

```text
private/navimow-capture/output/
  native-mqtt-fresh-client-id-experiment/
    publication-closure.json
```

It contains only public commit identifiers, hashes, fixed booleans and
validation outcomes.

No credential, endpoint, topic, device identity, client ID value, ObjectID or
payload is present.

## 11. Architecture Decisions

### AD-NAV-458: Publish only the diagnostic branch

**Decision:** Push the experiment commit only to the dated branch.

**Reason:** The methods must remain absent from productive `main`.

### AD-NAV-459: Verify the remote blob, not only push output

**Decision:** Fetch and hash the remote Account file independently.

**Reason:** A successful push message alone does not prove byte equality.

### AD-NAV-460: Preserve the branch until Symcon rollback passes

**Decision:** Keep the temporary remote branch for the next gated stages.

**Reason:** It remains the deterministic repair and read-back source until
Module Control has returned to verified `main`.

## 12. Gate Result

Temporary branch publication:

```text
PASS
```

Remote blob equality:

```text
PASS
```

`origin/main` protection:

```text
PASS
```

Symcon update:

```text
NOT AUTHORIZED
```

Live MQTT connection:

```text
NOT AUTHORIZED
```

## 13. Recommended Next Step

After explicit Gate-B authorization:

1. capture the repeatable private pre-update baseline;
2. change Module Control to the exact temporary branch;
3. perform one normal module update;
4. verify both temporary wrappers read-only;
5. prove instance, variable and archive compatibility;
6. stop before invoking either wrapper.

Required authorization:

```text
Symcon-Update auf den temporären Fresh-Client-ID-Branch und read-only Prüfung freigegeben.
```
