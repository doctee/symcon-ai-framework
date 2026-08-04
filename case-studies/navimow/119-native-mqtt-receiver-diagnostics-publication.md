# 119 Native MQTT Receiver Diagnostics Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Published and remotely verified; Symcon update remains blocked
**Date:** 2026-07-28
**Scope:** Execute Gate A from step 118 for the bounded Receiver diagnostics

## 1. Authorization and Boundary

Gate A was explicitly authorized with:

```text
Veröffentlichung der Receiver-Diagnostik freigegeben.
```

Exactly one productive Receiver file was published to
`doctee/symcon-navimow/main`.

This step performed no:

- Symcon module update;
- live Symcon mutation;
- MQTT enable, Connect, Disconnect or publish operation;
- mower command or physical-state change;
- instance, variable, profile or archive mutation;
- module reload;
- tag creation.

## 2. Baseline

The standalone publication clone was fetched from the remote before copying.

Verified baseline:

```text
repository:   doctee/symcon-navimow
branch:       main
local HEAD:   efb8343e50dbea612db26e49324130ed3d039e90
origin/main:  efb8343e50dbea612db26e49324130ed3d039e90
subject:      feat: expose bounded MQTT diagnostics
worktree:     clean
```

The canonical and standalone manifests both contained 30 productive files.
Only the Receiver module differed.

## 3. Published Delta

Exactly this file was copied from the canonical distribution:

```text
NavimowMqttReceiver/module.php
```

Staged delta:

```text
modified files: 1
insertions:     202
deletions:        1
unexpected:       0
```

The other 29 productive files remained byte-equal.

Published file SHA-256:

```text
eb670775363010fc1b346e3bcfbe1d44b78a481305cf4aafc5190d4f062de726
```

The delta contains:

- one private Receiver diagnostic attribute;
- the bounded `GetReceiveDiagnostics()` projection;
- ingress counting before envelope validation;
- fixed local rejection counters;
- successful Account handoff accounting;
- fixed Receiver and Account result allowlists;
- malformed-state recovery;
- saturating counters and bounded timestamps.

It contains no MQTT publish, mower action, automatic instance lifecycle or
installation-specific value.

## 4. Validation

The complete Navimow MQTT shadow gate passed before publication:

```text
MQTT fixtures:                    PASS
REST authentication:             PASS
native envelope:                 PASS
MQTT payload parser:             PASS
Receiver diagnostics:            PASS
Account ingestion:               PASS
REST reconciliation:             PASS
transport lifecycle:             PASS
distribution structure:          PASS
PHPCS:                            PASS
PHPStan:                          PASS
```

The copied standalone candidate additionally passed:

```text
PHP syntax:                       PASS
git diff --check:                 PASS
exact one-file delta:             PASS
29-file byte equality:            PASS
candidate hash:                   PASS
privacy and prohibited-path scan: PASS
```

No occurrence was found for:

```text
MC_ReloadModule
MQTT_Publish
SendDataToParent
RequestAction
IPS_CreateInstance
IPS_DeleteInstance
```

## 5. Module Validator Classification

This increment changes no JSON metadata.

All 13 validator artifacts are byte-equal to the standalone baseline validated
in step 113:

```text
library.json
4 x module.json
4 x form.json
4 x locale.json
```

All 13 files also passed fresh JSON parsing.

The official validator page was not re-executed because:

- the productive delta contains only PHP;
- every validator input is byte-identical to the exact prior validated set;
- step 113 already recorded the external page failure and equivalent official
  schema fallback for those exact bytes.

Classification:

```text
INHERITED EXACT SCHEMA EVIDENCE: PASS
NEW WEB VALIDATOR RESULT: NOT REQUIRED FOR UNCHANGED INPUT
```

This is an explicit deviation from rerunning the page named in step 118, not a
claim that the web validator returned a new result.

## 6. Commit and Push

Published commit:

```text
046529c518feefb15a51bd2f1c404401b3a7f474
feat: expose bounded MQTT Receiver diagnostics
```

Fast-forward push:

```text
efb8343..046529c  main -> main
```

No tag was created.

## 7. Independent Remote Verification

After a fresh fetch:

```text
local HEAD:   046529c518feefb15a51bd2f1c404401b3a7f474
origin/main:  046529c518feefb15a51bd2f1c404401b3a7f474
worktree:     clean
```

The remote commit changes exactly:

```text
M NavimowMqttReceiver/module.php
```

The Receiver blob fetched from `origin/main`:

- is byte-equal to the canonical candidate;
- has SHA-256 `eb670775363010fc1b346e3bcfbe1d44b78a481305cf4aafc5190d4f062de726`;
- contains no unclassified publication content.

## 8. Compatibility Position

Repository publication alone proves no installed Symcon compatibility.

The published change is structurally designed to retain:

- all Account, Configurator, Device and Receiver instances;
- all public Device variable IDs and Idents;
- profiles and action contracts;
- Archive Control logging and aggregation;
- REST authority over public mower state;
- disabled-by-default MQTT operation.

Those claims require the separate Gate B pre/post-update readback.

## 9. Evidence Closure

Private machine-readable publication evidence was written below:

```text
private/navimow-capture/output/
  native-mqtt-receiver-diagnostics-publication/
    evidence-closure.json
```

It contains no credential, endpoint, topic, payload, device identity or
installation ObjectID.

## 10. Gate Decision

Gate A:

```text
PASS
```

Gate B:

```text
CLOSED
```

Required next authorization:

```text
Symcon-Update und read-only Receiver-Diagnoseprüfung freigegeben.
```

After that authorization:

1. capture the private pre-update compatibility baseline twice;
2. let the user update the module through Module Control;
3. verify unchanged instance, variable and archive contracts;
4. call `NAVMQTTRX_GetReceiveDiagnostics()` read-only;
5. keep MQTT disabled and credential slots empty;
6. document step 120.

The one-shot live MQTT session remains behind the later independent Gate C.
