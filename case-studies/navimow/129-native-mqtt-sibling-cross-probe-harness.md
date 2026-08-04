# 129 Native MQTT Sibling Cross-Probe Harness

**Case study:** Navimow native IP-Symcon module
**Status:** Private harness implemented and validated offline; publication,
Symcon staging and live gates closed
**Date:** 2026-07-28
**Scope:** Implement the sibling cross-probe selected in step 128 without
changing productive source or the private installation

## 1. Purpose

Step 128 selected a known-good Receive Probe as a temporary sibling of the
retained native MQTT Client.

This step implements:

1. an inactive sibling-staging script;
2. an independent inactive cleanup script;
3. a bounded one-shot live harness;
4. a four-outcome classification regression;
5. deterministic source and probe hashes;
6. static safety and privacy gates.

No source was published and no Symcon operation, credential retrieval, broker
connection or mower action was performed.

## 2. Private Artifact Set

The implementation resides only below:

```text
private/navimow-capture/mqtt-sibling-cross-probe/
```

| File | Purpose |
|---|---|
| `stage-inactive-sibling.php` | Create and connect exactly one inactive probe child |
| `cleanup-inactive-sibling.php` | Remove a staged probe when no live run follows |
| `live-one-shot.php` | Execute one bounded normal Connect and guaranteed cleanup |
| `offline-test.php` | Verify the result matrix and Receiver metadata equality |
| `validate.sh` | Run regressions, style, hashes, call counts and privacy checks |
| `manifest.json` | Freeze source hashes, limits and closed authorization gates |

The directory is ignored by Git and contains no credential, endpoint, topic,
payload, device identity, client ID or ObjectID.

## 3. Frozen Publication Baseline

Standalone module clone:

```text
branch:      main
HEAD:        046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
worktree:    clean
```

No standalone file was changed.

## 4. Frozen Known-Good Probe

The step-94 Receive Probe remains the exact publication candidate.

Hashes:

```text
module.php:
408f16cf2755b1d80b8527a6c1fb3a4dce4a7882fdcc4743122ad403304f1e1e

module.json:
d7d5de3e18f00579db87a7cf8eb5937df17faf6932ea48d9ed2cf723d30de600

MqttReceiveProbeReducer.php:
06ee2e2408e645ba5d18490c30c9ceb2b303d25c929cb1e8d7ac50f3b91a48c9

form.json:
8280311f8ee8195682f73f62e87409ac2e8c09aea2a71aebeac847efadcdbddc

locale.json:
7ad31ae4213a95e25d515c35006e9271864d6ea2fa99cdd60bed9bd2847b4b61
```

The offline gate proves the probe and productive Receiver still share:

- module type `3`;
- the same MQTT parent requirement;
- the same implemented receive DataID;
- a public `ReceiveData($jsonString)` entry point.

## 5. Inactive Staging

`stage-inactive-sibling.php` requires:

- exactly one Account, Device and productive Receiver;
- no existing probe instance;
- retained Receiver, MQTT and WebSocket topology;
- MQTT disabled;
- WebSocket inactive;
- authorization header empty;
- MQTT username and password empty;
- a valid private Device ID available only in Symcon memory.

It then:

1. creates exactly one known-good probe instance;
2. configures the expected Device ID without outputting it;
3. connects the probe to the retained MQTT Client;
4. applies the probe configuration;
5. verifies the productive Receiver and probe share the same parent;
6. verifies the transport remained disabled and credential-empty.

It performs no broker connection or Account mutation.

If validation fails after creation, it deletes only the newly created probe.

## 6. Independent Staging Cleanup

Gate-B authorization must not require a later live authorization to restore
the installation.

`cleanup-inactive-sibling.php` therefore:

- accepts zero or one probe instance;
- requires MQTT disabled and the productive Receiver retained;
- verifies the probe parent before deletion;
- closes probe evidence defensively;
- deletes exactly the probe instance;
- verifies the productive Receiver and parent remain unchanged.

This script is the mandatory rollback path if:

- Gate C is not opened;
- the physical confirmation is not received;
- read-only staging verification fails;
- the live harness cannot start.

## 7. One-Shot Live Sequence

`live-one-shot.php` implements:

1. exact instance and wrapper cardinality checks;
2. retained topology and sibling-parent equality;
3. disabled and credential-empty preconditions;
4. usable Account authentication;
5. four exact QoS-0 subscriptions without wildcard;
6. productive Receiver baseline capture;
7. one probe Arm call;
8. one MQTT feature enable;
9. ownership validation in state `ready`;
10. one normal `NAVAC_ConnectMqttShadow()` call;
11. two-second observations of both child counters and Core statuses;
12. early stop after the first child ingress;
13. otherwise stop before the 165-second cutoff;
14. one normal Disconnect in `finally`;
15. MQTT feature disablement;
16. probe closure and final bounded report capture;
17. direct emergency transport cleanup only after normal cleanup failure;
18. probe deletion;
19. final credential-empty and topology verification.

The normal productive Connect path is deliberately used. No diagnostic
Client-ID method exists on published `main`.

## 8. Result Classification

The harness emits one fixed classification:

| Productive Receiver delta | Probe delta | Classification |
|---:|---:|---|
| `> 0` | `> 0` | `both-received` |
| `0` | `> 0` | `probe-only` |
| `> 0` | `0` | `receiver-only` |
| `0` | `0` | `neither-received` |

`offline-test.php` verifies all four paths and positive-delta normalization.

Ingress is not required for the harness safety result. A zero-ingress session
can pass cleanup while producing `neither-received`.

## 9. Cleanup Ordering

Cleanup is ordered:

```text
Disconnect
  -> disable MQTT feature
    -> close and read probe evidence
      -> emergency transport cleanup only if needed
        -> delete temporary probe
          -> validate final state
```

The probe is retained until after Disconnect so both Receiver results remain
available throughout the active window.

The probe is deleted before Module Control may later return to `main`, avoiding
an invalid instance whose module source is no longer installed.

## 10. Partial-Failure Hardening

The harness marks the MQTT feature as cleanup-owned before calling
`IPS_ApplyChanges()`.

It marks probe evidence as cleanup-owned immediately after invoking Arm.

Therefore:

- a partially failed enable still triggers disablement;
- an unexpected Arm result still triggers Close and deletion;
- an ambiguous Connect result still triggers exactly one Disconnect;
- no failure permits a second Connect.

## 11. Fixed Limits

```text
normal Connect call sites:      1
normal Disconnect call sites:   1
probe Arm call sites:           1
probe Close call sites:         1
probe Delete call sites:        1
MQTT publish call sites:        0
mower-command call sites:       0
module reload call sites:       0
Core create call sites:         0
poll interval:                  2 seconds
observation cutoff:             165 seconds
hard deadline:                  180 seconds
```

The staging source contains exactly one probe Create and one parent Connect
call.

## 12. Evidence Projection

The future live output is bounded to:

- relative milliseconds;
- native Core status codes;
- WebSocket active boolean;
- productive Receiver deltas and fixed result codes;
- probe receive and accepted deltas;
- probe bounded shape report;
- fixed cross-probe classification;
- call counts;
- cleanup and deletion booleans;
- fixed error code and pass boolean.

It returns no:

- credential or token;
- endpoint or host;
- topic;
- payload value;
- device identity;
- client ID;
- installation ObjectID;
- garden geometry.

## 13. Offline Validation

Executed:

```text
PHP syntax:
  stage, cleanup, live and offline sources PASS

PHPCS:
  all private PHP sources PASS

offline result matrix:
  PASS

known-good probe regression:
  PASS

productive Receiver regression:
  PASS

probe source hashes:
  PASS

static call counts:
  PASS

private-material scan:
  PASS
```

Overall:

```text
Navimow MQTT sibling cross-probe validation passed.
```

## 14. Authorization State

```text
temporary publication: NOT AUTHORIZED
Symcon update:          NOT AUTHORIZED
probe staging:          NOT AUTHORIZED
broker connection:      NOT AUTHORIZED
mower activity:         NOT AUTHORIZED
```

The artifacts were not executed through MCP.

## 15. Architecture Decisions

### AD-NAV-473: Separate inactive staging from live execution

**Decision:** Create the sibling probe in an independently authorized inactive
step.

**Reason:** Parent topology can be verified before any credential retrieval or
broker connection.

### AD-NAV-474: Give Gate B its own rollback

**Decision:** Provide a standalone inactive cleanup script.

**Reason:** A staged temporary module instance must not depend on Gate-C
authorization for removal.

### AD-NAV-475: Use the productive lifecycle unchanged

**Decision:** Invoke normal Connect and Disconnect exactly once.

**Reason:** The test changes the child set only; changing Client ID or
activation order would combine hypotheses.

### AD-NAV-476: Delete the probe inside live cleanup

**Decision:** Remove the temporary child before source rollback.

**Reason:** Returning Module Control to `main` while the probe exists would
leave an instance without installed module source.

### AD-NAV-477: Keep hypothesis and safety outcomes independent

**Decision:** Do not require child ingress for harness `pass`.

**Reason:** Cleanup correctness and routing evidence answer different
questions.

## 16. Recommended Next Step

Create:

```text
130-native-mqtt-sibling-cross-probe-publication-and-live-test-plan.md
```

That step should:

1. define the exact temporary branch and five-file probe publication;
2. define repeated pre-update and post-update compatibility projections;
3. define inactive sibling staging and rollback gates;
4. define the one-shot live execution and physical confirmation;
5. define automatic runtime and source restoration;
6. define remote and local branch deletion after complete closure.
