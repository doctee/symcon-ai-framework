# 131 Native MQTT Sibling Cross-Probe Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary five-file probe branch published and remotely verified;
Symcon and live gates closed
**Date:** 2026-07-28
**Scope:** Execute Gate A from step 130 and stop before any private
installation mutation

## 1. Purpose

Step 130 froze the complete temporary publication contract.

This step:

1. revalidates the exact standalone `main`;
2. repeats all private harness and productive MQTT regressions;
3. refreshes the standalone-main publication manifest;
4. creates the exact dated experiment branch;
5. stages only the five known-good probe files;
6. validates and commits the complete difference;
7. pushes and independently verifies the remote branch;
8. proves `origin/main` unchanged;
9. stops before any Symcon operation.

## 2. Authorization

The user explicitly authorized Gate A:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-Branches freigegeben.
```

This authorized only temporary Git publication and its later deletion after
complete runtime closure.

It did not authorize:

- a Symcon Module Control update;
- probe-instance creation;
- credential retrieval;
- MQTT enablement or broker connection;
- mower activity or commands.

## 3. Revalidated Baseline

The standalone publication clone was fetched and fast-forward checked.

Verified:

```text
branch:      main
HEAD:        046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
subject:     feat: expose bounded MQTT Receiver diagnostics
worktree:    clean
```

The selected experiment branch was absent locally and remotely.

## 4. Repeated Validation

Executed:

```text
private/navimow-capture/mqtt-sibling-cross-probe/validate.sh
case-studies/navimow/tools/check-mqtt-shadow.sh
```

Results:

- private staging, cleanup, live and offline PHP syntax passed;
- PHPCS passed;
- four-outcome classifier passed;
- known-good probe regression passed;
- productive Receiver regression passed;
- all REST and MQTT shadow regressions passed;
- strict distribution validation passed;
- PHPStan passed for the productive MQTT surface;
- frozen source hashes passed;
- static call counts passed;
- private-material scan passed.

## 5. Publication Manifest

The existing manifest helper was reused:

```text
case-studies/navimow/tools/
  prepare-mqtt-spike-publication.php
```

It:

1. verified the frozen probe manifest;
2. captured every standalone `main` file hash;
3. excluded only `.git` and the not-yet-existing probe directory;
4. compared the current SAEF distribution with standalone `main`;
5. staged the reviewed probe package;
6. proved all pre-existing standalone files byte-identical afterward.

Current SAEF-to-standalone distribution drift:

```text
0 files
```

The refreshed standalone manifest is:

```text
case-studies/navimow/tools/symcon-mqtt-spike-library/
  standalone-main-files.sha256
```

SHA-256:

```text
348ea25bfaec7ee9de2c868aa4c2ef334f67f50339c5ba3829d95cc3e358ab98
```

## 6. Published Branch

Branch:

```text
experiment/native-mqtt-sibling-cross-probe-20260728
```

Commit:

```text
5d9941062d5fa1e58a16f46e04305742fef3515a
```

Commit subject:

```text
test: add temporary MQTT sibling receive probe
```

The branch was created directly from:

```text
046529c518feefb15a51bd2f1c404401b3a7f474
```

## 7. Exact Remote Difference

Remote comparison against `origin/main`:

```text
added:    5
modified: 0
deleted:  0
```

Added files:

```text
NavimowMqttReceiveProbe/
  MqttReceiveProbeReducer.php
  form.json
  locale.json
  module.json
  module.php
```

No productive file changed.

## 8. Remote Blob Verification

Every remote blob matched the frozen local source:

| File | SHA-256 verification |
|---|---|
| `MqttReceiveProbeReducer.php` | PASS |
| `form.json` | PASS |
| `locale.json` | PASS |
| `module.json` | PASS |
| `module.php` | PASS |

Local experiment commit equals remote experiment commit.

After the remote fetch:

```text
origin/main:
046529c518feefb15a51bd2f1c404401b3a7f474
```

No force push, tag or pull request was created.

## 9. Published Module Contract

The temporary module:

- is a type-3 child module;
- requires the native MQTT parent interface;
- implements the proven native MQTT receive DataID;
- exposes bounded Arm, Close and report methods;
- creates no variable or action;
- contains no MQTT publish or REST command path;
- stores only bounded aggregate shape evidence;
- remains isolated from productive source.

## 10. Private Evidence

Machine-readable closure:

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe/
    publication-closure.json
```

It contains only public branch identifiers, hashes, counts and authorization
booleans.

## 11. Architecture Decisions

### AD-NAV-483: Refresh the full standalone manifest

**Decision:** Recapture all current standalone `main` hashes before staging.

**Reason:** The earlier probe publication used an older main revision; current
publication must prove the present baseline independently.

### AD-NAV-484: Publish the complete module directory

**Decision:** Include all five frozen files, including passive form and locale
metadata.

**Reason:** The temporary branch must reproduce the already validated module
artifact exactly.

### AD-NAV-485: Preserve a zero-drift productive baseline

**Decision:** Reject any change outside the probe directory.

**Reason:** The cross-probe tests child delivery only and must not carry a
productive source delta.

### AD-NAV-486: Stop after remote verification

**Decision:** Perform no Symcon operation under Gate A.

**Reason:** Installation and instance staging require the separate Gate-B
authorization from step 130.

## 12. Gate Result

Offline validation:

```text
PASS
```

Temporary publication:

```text
PASS
```

Remote commit and five blobs:

```text
PASS
```

`origin/main` unchanged:

```text
PASS
```

Symcon update:

```text
NOT AUTHORIZED
```

Broker connection:

```text
NOT ATTEMPTED
```

## 13. Recommended Next Step

Gate B may now be opened.

Required authorization:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-Staging freigegeben.
```

The next execution will:

1. capture the complete read-only `main` baseline twice;
2. update Module Control to commit `5d99410`;
3. verify all productive contracts;
4. create and connect one inactive sibling probe;
5. prove MQTT remains disabled and credential-empty;
6. stop before any broker connection.
