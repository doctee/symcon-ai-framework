# 107 Native MQTT Pre-Update Baseline and Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B complete; update compatibility and read-only REST smoke test
passed, MQTT topology remains absent
**Date:** 2026-07-28
**Scope:** Capture the private baseline, update from published `main` and stop
before topology preparation

## 1. Purpose

This step executes Gate B from step 105 after the publication closed in step
106.

It covers:

- a private read-only pre-update baseline;
- an immediate deterministic baseline repeat;
- the user-controlled Module Control update;
- post-update instance, variable and archive comparison;
- Receiver module availability;
- one bounded read-only Discovery and status smoke test;
- private and sanitized evidence closure.

It does not:

- create a Navimow MQTT Receiver instance;
- create or change native MQTT or WebSocket Clients;
- enable MQTT shadow mode;
- adopt a transport chain;
- retrieve MQTT credentials;
- connect to the broker;
- send a mower command;
- use `MC_ReloadModule()`.

## 2. Tested Publication

Standalone repository:

```text
doctee/symcon-navimow
```

Branch:

```text
main
```

Commit:

```text
6cc41d32df6cc2e528bdd4059dda3e006055241a
```

No tag was involved.

## 3. Read-Only Probe Contract

The private probe discovers authorized objects through module GUIDs. It does
not depend on historical ObjectIDs.

It reads:

- Account, Configurator and Device instance identity and status;
- redacted configuration shapes;
- 14 established variable contracts;
- Archive Control logging and aggregation;
- bounded history queryability;
- Receiver instance count and, when present, only its direct parent chain;
- availability of the four new Account lifecycle functions;
- connection, reauthentication and REST error state.

It does not return:

- OAuth secrets or tokens;
- raw instance configuration;
- device identity;
- topic;
- WSS endpoint or header;
- MQTT username, password or client ID;
- REST or MQTT payload.

No temporary Symcon object was created.

## 4. Pre-Update Baseline

The first MCP execution was evaluated through separate channels:

| Channel | Result |
|---|---|
| transport | success |
| `transportError` | `null` |
| `executionError` | `null` |
| `truncated` | `false` |
| aggregate probe | PASS |

Baseline facts:

- exactly one Account, Configurator and Device instance;
- all three instance statuses `102`;
- 14 of 14 variables verified;
- variable metadata complete;
- five logged variables;
- bounded history query successful for every logged variable;
- Account connected;
- no reauthentication required;
- no MQTT Receiver instance;
- no new lifecycle function loaded before the update.

The REST error counter already contained an accumulated nonzero value. Its
exact value remains private. It was stable through the repeat and update and
therefore is not attributed to this step.

## 5. Logging Contract

The following variables were logged before the update:

```text
BatteryLevel
LastCommand
LastCommandResult
Online
VehicleState
```

All retained:

- the same ObjectID;
- the same parent;
- the same Ident and type;
- the same profile and action metadata;
- logging enabled;
- aggregation type unchanged;
- bounded history queryability.

No variable was deleted, recreated or reparented.

## 6. Deterministic Repeat

An immediate second read-only capture proved:

```text
instance IDs equal
variable IDs equal
configuration-shape hashes equal
identity hash equal
archive hash equal
operational state equal
Receiver count equal
```

The repeat passed without transport, execution or truncation error.

## 7. Module Update

After the baseline passed, the user updated Navimow manually through Module
Control from branch `main`.

The update:

- retained the three productive instances;
- ran without `MC_ReloadModule()`;
- did not create a Receiver;
- did not create a native MQTT or WebSocket Client;
- did not enable MQTT;
- did not retrieve a credential;
- did not connect to the broker.

## 8. Post-Update Compatibility

The complete probe was executed again before any MQTT configuration.

Required comparison:

| Invariant | Result |
|---|---|
| Account, Configurator and Device ObjectIDs | equal |
| 14 variable ObjectIDs | equal |
| complete variable metadata | equal |
| identity hash | equal |
| archive hash | equal |
| five logging contracts | equal |
| legacy Account safe configuration | equal |
| Configurator configuration shape | equal |
| Device configuration shape | equal |
| productive instance status | all `102` |
| MQTT default | disabled |
| Receiver selection | empty |
| automatically created Receiver | none |

The Account configuration gained only the expected registered MQTT properties:

```text
EnableMqttShadow = false
MqttReceiverInstanceId = 0
```

Every legacy safe setting remained equal.

## 9. New Runtime Availability

The installed module now exposes:

```text
NAVAC_ValidateMqttAdoptionCandidate
NAVAC_AdoptMqttShadowChain
NAVAC_ConnectMqttShadow
NAVAC_DisconnectMqttShadow
```

The Navimow MQTT Receiver module metadata is available to Symcon.

Availability did not create an instance. Receiver instance count remained
zero.

## 10. Read-Only REST Smoke Test

The bounded smoke test performed:

1. one Discovery GET;
2. one Device status GET;
3. Receiver module availability inspection;
4. before/after comparison of command evidence and REST error state.

The initial measurement expected the wrong success text:

```text
expected by probe:
Status updated.

implemented contract:
Status refresh succeeded.
```

That result was classified as a probe assertion defect, not a runtime failure.
The corrected idempotent GET repeat was explicitly limited to the expected
message correction.

Corrected result:

| Invariant | Result |
|---|---|
| Receiver module available | PASS |
| Receiver instance count | zero |
| Discovery | PASS |
| status refresh | PASS |
| REST timestamp progression | PASS |
| Device status timestamp progression | PASS |
| REST error count unchanged | PASS |
| command evidence unchanged | PASS |

No command endpoint was called.

## 11. Private Evidence

Private files:

```text
private/navimow-capture/
  native-mqtt-lifecycle-baseline-probe.php

private/navimow-capture/output/native-mqtt-lifecycle/
  pre-update-baseline.json
  pre-update-repeat-check.json
  post-update-compatibility.json
  post-update-rest-smoke.json
  gate-b-evidence-closure.json
```

The public report contains no private ObjectID, hash or installation metadata.

## 12. Gate Decision

| Gate | Result |
|---|---|
| private pre-update baseline | PASS |
| deterministic repeat | PASS |
| user-controlled Module Control update | PASS |
| post-update instance compatibility | PASS |
| variable identity and metadata | PASS |
| archive and logging continuity | PASS |
| Receiver module availability | PASS |
| no automatic Receiver/core creation | PASS |
| read-only Discovery and status | PASS |
| command invariance | PASS |
| device action attempts | zero |
| MQTT connection attempts | zero |

**Gate B: CLOSED.**

**Gate C: BLOCKED pending explicit topology-preparation authorization.**

## 13. Next Step

The installation currently has no productive Receiver chain. The next step is:

```text
108-native-mqtt-inactive-topology-preparation.md
```

After separate Gate C authorization, that step may prepare exactly one
dedicated inactive and credential-empty chain. It must stop before adoption or
broker connection.
