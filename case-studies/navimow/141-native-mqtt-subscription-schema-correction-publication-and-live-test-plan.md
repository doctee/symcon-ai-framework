# 141 Native MQTT Subscription Schema Correction Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Publication and corrected live test planned; all execution gates
closed
**Date:** 2026-07-28
**Scope:** Publish the canonical native MQTT subscription schema, verify
legacy migration in Symcon and execute one bounded receive-only V3 test

## 1. Purpose

Step 139 identified a direct schema mismatch in the retained native MQTT
Client:

- the native Core form and the successful disposable spike use `Topic` and
  `QoS`;
- the retained Navimow integration was configured with `Topic` and
  `QualityOfService`;
- both compatible child instances consequently observed zero ingress.

Step 140 implemented and fully validated an offline correction:

1. new Core subscription configuration uses only `Topic` and `QoS`;
2. the exact previous shape remains readable only as migration input;
3. the next normal Connect rewrites all four entries canonically;
4. received native MQTT envelopes continue to use
   `QualityOfService`;
5. a compact V3 harness proves the active Core schema without unbounded MCP
   output.

This step plans publication and one controlled live verification. It performs
no Git publication, Symcon mutation, broker connection or mower operation.

## 2. Architecture Decision

### AD-NAV-502: Correct productive `main` before the temporary probe

**Decision:** Publish the one-file schema correction to productive `main`
before creating the temporary probe branch.

**Reason:** A corrected Connect migrates the retained Core property to
`Topic` plus `QoS`. The mandatory post-test return must therefore target a
`main` version that understands the canonical property. Returning to the old
implementation would create an avoidable compatibility regression.

### AD-NAV-503: Separate schema proof from ingress proof

**Decision:** The live gate must report both:

- the exact active Core subscription shape after Connect;
- bounded child ingress counters.

**Reason:** A canonical property proves the correction was applied, but it
does not by itself prove broker delivery.

### AD-NAV-504: Retain REST authority

**Decision:** MQTT remains optional, receive-only and disabled after the test.
REST remains authoritative for public Device variables.

**Reason:** The test closes a transport question only. It does not yet
establish sufficient long-term MQTT completeness or stability.

## 3. Fixed Safety Boundary

All later gates retain these constraints:

- no MQTT publish path or invocation;
- no Start, Pause, Resume, Dock or Stop command;
- one Connect and one Disconnect at most;
- no retry or automatic reconnect;
- no Symcon restart;
- no Core MQTT or WebSocket recreation;
- no reparenting of the retained Core chain or productive Receiver;
- exactly one temporary sibling probe;
- no change to Device variables, profiles, actions, timers or Archive Control;
- no `MC_ReloadModule()`;
- no merge or tag for the temporary probe branch;
- credentials, endpoints, topics, payloads, IDs and installation metadata
  remain private;
- failure, timeout, ambiguity or interruption permits only cleanup and
  restoration.

The live observation cutoff remains 165 seconds with a 180-second hard
deadline. Cleanup is mandatory on every exit path.

## 4. Frozen Sources

### Productive correction

Only this standalone module file may change on `main`:

```text
libs/Navimow/MqttTransportConfiguration.php
```

Expected source hash:

```text
f9d6b6b826849c5c1cb125a01167c3f7931bfff12355447e1d43eb7dfe7a022b
```

The corresponding SAEF regression source remains:

```text
tests/mqtt-transport-lifecycle.php:
0278ce7e0482daf3618a5bf449519034040b4ddb6ebcee5e3a1c1de1f1ec1dd6
```

### Temporary five-file probe

The temporary branch may add only:

```text
NavimowMqttReceiveProbe/
  MqttReceiveProbeReducer.php
  form.json
  locale.json
  module.json
  module.php
```

Expected hashes:

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

### Private V3 harness

```text
live-one-shot-v3.php:
09d0c7cc737e4e3579da558b0f18cebada8e76cf06170d315a518cdbb8095489

compact-output-test.php:
f11133663b1e658676fee9e7047a07c89bf002412110e67c7b2ab63ca284d8a1

validate-v3.sh:
fa06707dbb2e2c5eede45fa63ea00e20818409d1479b3430d1305080e462b2f0
```

Historical V1 and V2 sources and evidence remain unchanged.

## 5. Gate A: Productive Correction Publication

Required authorization:

```text
Veröffentlichung der nativen MQTT-Subscription-Schema-Korrektur auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone publication clone;
2. require clean local `main` equal to `origin/main`;
3. revalidate the frozen SAEF source and complete MQTT suite;
4. copy the one corrected library file;
5. prove exactly one modified file and no added or deleted file;
6. validate the standalone module;
7. inspect the complete staged diff;
8. commit and push to `main`;
9. fetch and compare local and remote commits and blob hashes;
10. record private machine-readable and sanitized public evidence.

Suggested commit subject:

```text
fix(mqtt): use native QoS subscription field
```

Gate A permits no Symcon update, temporary branch or broker connection.

### Gate-A stop conditions

Stop before commit if:

- publication `main` is dirty or diverged;
- any file other than the frozen library file differs;
- the source hash is not exact;
- standalone validation, PHPCS, PHPStan or regression tests fail;
- private material is detected.

If push verification fails, do not continue to Gate B.

## 6. Gate B: Temporary V3 Probe Publication

Required authorization after Gate A passes:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-V3-Branches freigegeben.
```

Selected branch:

```text
experiment/native-mqtt-sibling-cross-probe-v3-20260728
```

Gate B permits only:

1. create the temporary branch from the newly verified corrected `main`;
2. add exactly the frozen five-file probe directory;
3. prove five added and zero modified or deleted files;
4. validate syntax, metadata, PHPCS and receive-only restrictions;
5. commit and push only the temporary branch;
6. verify all remote blobs and prove `origin/main` unchanged.

Suggested commit subject:

```text
test: add temporary MQTT sibling receive probe v3
```

The branch must never be merged or tagged and must be deleted locally and
remotely after Gate D evidence is closed.

## 7. Gate C: Symcon Update and Inactive Staging

Required authorization after Gate B passes:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-V3-Staging freigegeben.
```

### Read-only baseline

Before Module Control mutation, run the established bounded projection twice
and require stable evidence for:

- installed branch, commit and module validity;
- Account, Configurator, Device and Receiver identities;
- retained MQTT Client and WebSocket topology;
- absence of a probe instance;
- all 14 Device variable identities and metadata;
- all five Archive Control logging contracts and queryability;
- current command, authentication and REST-read compatibility;
- MQTT disabled and WebSocket inactive;
- empty transient credential properties;
- retained subscription entries in either exact accepted form.

Private ObjectIDs and configuration values must not enter public evidence.

### Controlled update

Gate C permits:

1. one supported Module Control update to the temporary V3 branch;
2. no `MC_ReloadModule()`;
3. repeated read-only compatibility projection;
4. validation that the legacy retained subscription form is accepted;
5. creation and pairing of exactly one inactive sibling probe;
6. proof that MQTT remains disabled, WebSocket inactive and credentials empty.

If Gate D is not executed in the same supervised session, close and delete the
probe and return Module Control to corrected `main`.

### Gate-C stop conditions

Do not connect if:

- instance, variable or Archive Control continuity changes;
- authentication or REST reads regress;
- the retained topology is not owned and valid;
- the probe is active before the live harness;
- more than one probe exists;
- credentials are present while inactive;
- the installed commit is not the verified V3 branch.

## 8. Gate D: One-Shot Corrected Live Test

Required authorization after Gate C passes:

```text
Ein einmaliger korrigierter MQTT-Sibling-Cross-Probe-V3-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

Immediately before execution, require a fresh physical statement:

```text
Mäher mäht sichtbar, bleibt voraussichtlich mindestens drei Minuten aktiv und ist beaufsichtigt.
```

An official-app or scheduled run is acceptable. This confirmation is context,
not proof that MQTT traffic must occur. No mower command is authorized.

### Exact live sequence

The V3 harness may perform exactly:

1. validate the inactive retained topology and sibling probe;
2. enable the optional MQTT feature;
3. invoke normal `NAVAC_ConnectMqttShadow()` once;
4. read the active MQTT Client configuration;
5. require exactly four subscription entries with only `Topic` and integer
   `QoS = 0`;
6. observe Core, productive Receiver and sibling counters read-only;
7. classify the bounded result;
8. invoke normal `NAVAC_DisconnectMqttShadow()` once;
9. disable MQTT;
10. close and delete the sibling probe;
11. verify inactive, credential-empty cleanup.

There is no retry after timeout, disconnect or ambiguous output.

### Required compact evidence

The private result must include only bounded, sanitized fields:

- test start and elapsed duration;
- Connect and Disconnect call counts;
- active subscription count, canonical count and `allCanonical`;
- sample count and first-healthy timestamp;
- initial and final Core statuses;
- WebSocket-active continuity;
- maximum productive Receiver delta;
- maximum sibling probe delta;
- maximum accepted sibling message count;
- final classification;
- cleanup and pass flags.

It must not include:

- credentials or authorization headers;
- broker host or private topic;
- payload contents;
- Client ID, Device ID or ObjectIDs;
- full configuration dumps;
- unbounded observations.

## 9. Result Classification

| Result | Meaning | Decision |
|---|---|---|
| canonical schema and both children receive | configuration mismatch confirmed as root cause | retain correction; plan passive MQTT pilot |
| canonical schema and only sibling receives | productive Receiver path remains defective | retain schema correction; inspect Receiver filter/handoff |
| canonical schema and only Receiver receives | productive transport works; sibling evidence path differs | retain correction; inspect probe independently |
| canonical schema and neither receives while Core healthy | schema correction applied but not sufficient | retain correctness fix; continue transport/broker investigation |
| canonical schema cannot be proven | migration or Core contract failed | stop, clean up and prepare correction rollback |
| Core unhealthy or output ambiguous | transport result inconclusive | stop after cleanup; no retry |

Physical mower activity is supporting context only. It must not override
measured schema, Core status or child counters.

## 10. Restoration Contract

After every Gate-D outcome:

1. prove zero active probe instances;
2. prove MQTT disabled;
3. prove WebSocket inactive;
4. prove transient headers and credentials empty;
5. update Module Control to the verified corrected `main`;
6. repeat the complete read-only compatibility projection;
7. prove all 14 variables and five logging contracts unchanged;
8. preserve the canonical retained subscription property;
9. close private and sanitized public evidence;
10. delete the temporary branch locally and remotely.

Emergency direct cleanup is allowed only if normal module cleanup fails and
must be documented separately.

## 11. Rollback Decision

The schema correction is a direct native Core contract fix and is not rolled
back merely because no messages arrive.

Prepare a normal revert commit on productive `main` only if:

- legacy retained configuration cannot be read;
- normal Connect cannot write a valid native configuration;
- disconnect or cleanup regresses;
- existing REST, variable or archive contracts regress.

Do not use force push, branch reset, Core deletion or topology recreation as a
rollback mechanism.

## 12. Planned Reports

On separate authorization, create:

```text
142-native-mqtt-subscription-schema-correction-publication.md
143-native-mqtt-subscription-schema-correction-symcon-staging.md
144-native-mqtt-subscription-schema-correction-live-test-and-restoration.md
```

Step 142 covers Gates A and B only if each is authorized separately. Step 143
covers Gate C. Step 144 covers Gate D and complete restoration.

## 13. Validation of This Plan

Planning-time offline evidence:

```text
V3 syntax:                 PASS
V3 compact-output test:    PASS
V3 safety/contract gate:   PASS
transport lifecycle:       PASS
complete MQTT suite:       PASS
distribution validation:  PASS
PHPCS:                     PASS
PHPStan:                   PASS
```

Planning-time standalone baseline:

```text
branch:      main
HEAD:        046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
worktree:    clean
```

This baseline must be fetched and revalidated at Gate A. It is not an
authorization to publish.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| offline correction | PASS |
| compact V3 evidence | PASS |
| Gate A productive publication | CLOSED |
| Gate B temporary probe publication | CLOSED |
| Gate C Symcon staging | CLOSED |
| Gate D broker connection | CLOSED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

## 15. Recommended Next Step

After explicit Gate-A authorization, execute only the productive one-file
publication and create:

```text
142-native-mqtt-subscription-schema-correction-publication.md
```

Required authorization:

```text
Veröffentlichung der nativen MQTT-Subscription-Schema-Korrektur auf main freigegeben.
```
