# 234 Native MQTT Pilot Diagnostics Inactive Preflight

**Case study:** Navimow native IP-Symcon module

**Status:** Inactive preflight passed and private harness initialized at
`ready-for-acceptance`; renewed persistence acceptance and MQTT activation
remain closed

**Date:** 2026-07-30

**Scope:** Verify the native pilot diagnostics while MQTT remains disabled and
prepare a commit-bound private observation state

## 1. Purpose

Step 233 installed and verified:

```text
main@793249ece1c0944192ea28dade7ecd2340a5135f
```

This step:

1. extends the private read-only projection with native pilot diagnostics;
2. validates the private harness offline;
3. captures two bounded inactive projections;
4. proves 82 seconds of stable credential-free inactivity;
5. initializes a fresh state for the exact installed commit;
6. stops at the separate persistence-acceptance gate.

No Symcon mutation, MQTT credential retrieval, activation, OAuth action,
service restart or mower command occurred.

## 2. Private Harness Extension

The productive module was not changed. The private harness now validates and
retains the fixed version-1 pilot projection:

```text
checkpoints: maximum 32
episodes:    maximum 32
rotations:   maximum 64
openEpisode: maximum 1
```

Inactive snapshots additionally require:

```text
featureEnabled:   false
active:           false
sessionSequence:  0
startedAt:        0
nextCheckpointAt: 0
evidence arrays:  empty
openEpisode:      null
```

A dedicated hash records equality of the inactive pilot diagnostics without
adding them to the general transport contract. This separation is necessary
because activation is expected to change `active`, session sequence and the
next checkpoint time.

## 3. Harness Validation

Executed:

```text
sh private/navimow-capture/native-mqtt-private-pilot/validate.sh
```

Result:

```text
PHP syntax:             PASS
offline behavior:       PASS
read-only boundary:     PASS
private-material scan:  PASS
```

Current private artifact hashes:

| Artifact | SHA-256 |
|---|---|
| `PilotHarness.php` | `6bf52d24e3fce6c7092424b093f2605ef4a1660220b08692a9281eb9f323daa9` |
| `pilot.php` | `9614644843a447dd98ef7e8153697b0095a9d19950f82500ae3372e2a00fc6c9` |
| `offline-test.php` | `3b08f5f7e8388147eadb75826d6705b100fece83af41fb7a15bafd2794568803` |
| `symcon-readonly-probe.php` | `efb42d1801af546e39628513a6b3ec438686c7e504ea94e647efafa8a711cd8c` |
| `validate.sh` | `60acdca7c31c30270fcaf2eb54266bf63897ba314b28166bbfd21a304c1fdfb5` |

## 4. MCP Execution Contract

Both projections used the bounded structured MCP channel. Each passed:

```text
transportError:  null
executionError:  null
truncated:       false
projection pass: true
```

A successful transport was not treated as successful PHP execution.

## 5. Inactive Baselines

The projections were captured 82 seconds apart:

| Check | First | Second | Result |
|---|---:|---:|---|
| required spacing | 65 s | 82 s observed | PASS |
| repository clean and valid | true | true | EQUAL |
| variables | 14 | 14 | EQUAL |
| Archive loggings | 5 | 5 | EQUAL |
| MQTT feature | disabled | disabled | EQUAL |
| MQTT/WebSocket status | `104/104` | `104/104` | EQUAL |
| MQTT credentials | absent | absent | EQUAL |
| lifecycle | `Disabled` | `Disabled` | EQUAL |
| REST operational | true | true | EQUAL |
| MQTT hint | unavailable | unavailable | EQUAL |
| pilot diagnostics | inactive and empty | inactive and empty | EQUAL |
| transport counters | retained | retained | EQUAL |

The OAuth token horizon decreased passively between reads. No refresh or
authentication action was performed. Its current horizon is not sufficient
evidence for a later activation gate.

## 6. Contract Equality

Both snapshots produced:

```text
baseline signature:
4d92abe9c5abaa470814346fd0671f8d3ecef29087be030f1dca761067af7734

pilot diagnostics signature:
351f99b7d23a8fee221cfcf789c90e1b02d8b4d6a4935073a1c55b428bc62cc6
```

The retained component hashes remained:

| Contract | SHA-256 |
|---|---|
| identity | `79d61d2b6d8feaf1a5f2638419641bf9a81b783c948d34691b1722d8e6bedad4` |
| archive | `9f83bac136fd4c5e444e0555486214848148aa7f16209f365b4167392d9b50a1` |
| command evidence | `f237c68db19ee3358a9d009b1e9acdc2aec6aa402dde487958425c4a7d72b9d9` |
| topology | `e2e2de1ca65b4c98de78a517fd98daba51436da901bda53a450c064e678af1d9` |
| subscriptions | `375dc242b1a0ae91e28a62abcd8da2df6a6496df7c49939839ba1ab8f69074fa` |

This preserves the identities of all 14 variables and the five user-owned
Archive Control logging contracts.

## 7. Harness State

The fresh state is bound to the full 40-character installed commit.

Final status:

```text
phase:               ready-for-acceptance
classification:      PENDING
inactive baselines:  2
active baselines:    0
stop reasons:        none
pilot clock started: no
cleanup required:    no
```

`PENDING` is correct. The 48-to-72-hour clock starts only after renewed
commit-bound persistence acceptance, token readiness, explicit activation and
two stable active baselines.

## 8. Private Evidence

Machine-readable evidence is stored at:

```text
private/navimow-capture/output/
  native-mqtt-pilot-diagnostics-inactive-preflight/
  evidence-closure.json
  pilot-state.json
  snapshots/inactive-01.json
  snapshots/inactive-02.json
```

The public report contains no ObjectID, credential, topic, payload, coordinate,
hostname or private device identity.

## 9. Architecture Decisions

### AD-NAV-851: Extend only the private observation layer

The installed productive module already exposes the required bounded API. The
preflight changes only the private consumer and state harness.

### AD-NAV-852: Validate native diagnostics at the ingestion boundary

Malformed or over-bound checkpoint, episode or rotation projections fail
before they enter persistent private pilot evidence.

### AD-NAV-853: Hash inactive diagnostics separately

Inactive equality is proven without freezing fields that must change during a
later active session.

### AD-NAV-854: Require renewed commit-bound acceptance

Earlier persistence acceptance applied to an older published commit and pilot
run. It does not authorize credential persistence or activation for
`793249ec`.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| private harness validation | PASS |
| first inactive projection | PASS |
| 82-second inactive interval | PASS |
| second inactive projection | PASS |
| structural contract equality | PASS |
| native pilot diagnostic equality | PASS |
| disabled credential-free state | PASS |
| REST authority | PASS |
| harness initialization | PASS |
| harness phase | `ready-for-acceptance` |
| persistence acceptance for current commit | NOT GIVEN |
| token readiness | NOT PASSED |
| MQTT activation | CLOSED |
| service restart | NOT PERFORMED |
| mower command | NOT PERFORMED |

## 11. Next Step

Proceed with:

```text
235-native-mqtt-pilot-diagnostics-persistence-acceptance-and-token-readiness.md
```

That step should:

1. record explicit persistence and recovery acceptance for the exact installed
   commit and this fresh pilot state;
2. perform no Symcon mutation while recording acceptance;
3. observe a passive OAuth refresh if the token horizon remains below the
   activation threshold;
4. re-run a bounded read-only readiness projection;
5. preserve a separate explicit authorization gate for MQTT activation.
