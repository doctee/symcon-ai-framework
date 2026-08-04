# 128 Native MQTT Zero-Ingress Cross-Probe Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Post-experiment analysis complete; sibling cross-probe selected;
all publication and live gates closed
**Date:** 2026-07-28
**Scope:** Close the Fresh-Client-ID hypothesis and select the smallest
discriminating test for the remaining native MQTT zero-ingress boundary

## 1. Purpose

Step 127 proved that a fresh, run-specific MQTT Client ID did not produce
Receiver ingress while:

- the native MQTT and WebSocket clients remained healthy;
- the mower was visibly mowing;
- credentials and exact subscriptions were accepted;
- the runtime was restored automatically;
- Module Control and all productive contracts returned to verified `main`.

This step:

1. consolidates the remaining transport hypotheses;
2. compares the proven test Receiver with the productive Receiver;
3. incorporates the IP-Symcon data-flow contract;
4. evaluates current upstream implementations;
5. selects one temporary sibling cross-probe;
6. defines evidence, safety, cleanup and authorization gates.

This step performs no publication, Symcon mutation, MQTT connection, credential
retrieval or mower action.

## 2. Fixed Safety Boundary

The next investigation remains:

- receive-only;
- REST-authoritative;
- bounded to one broker connection;
- free of MQTT publish;
- free of mower commands;
- free of productive instance reparenting;
- free of variable, action, profile and Archive Control changes;
- free of retries and automatic reconnect experiments;
- fully reversible.

The Fresh-Client-ID experiment shall not be repeated or varied.

## 3. Closed Hypothesis

Changed field in step 127:

```text
retained stable Client ID -> fresh run-specific Client ID
```

Constants included:

- retained productive Receiver;
- retained native MQTT Client;
- retained native WebSocket Client;
- same Account and credential endpoint;
- same WSS configuration shape;
- same four exact QoS-0 subscriptions;
- same activation order;
- visible mowing.

Result:

```text
native Core clients healthy
Receiver receive delta 0
```

Decision:

```text
reused Client ID as sole cause: NOT SUPPORTED
```

Client-session behavior cannot be excluded in every possible combination, but
another Client-ID-only test is not justified.

## 4. Remaining Coupled Differences

The successful step-94 topology used:

```text
fresh WebSocket Client
  -> fresh MQTT Client
    -> temporary known-good Receive Probe
```

The zero-ingress productive topology uses:

```text
retained WebSocket Client
  -> retained MQTT Client
    -> productive Navimow MQTT Receiver
```

After step 127, the meaningful coupled differences are:

1. fresh versus retained Core instance identities;
2. known-good test Receiver versus productive Receiver implementation;
3. isolated topology versus long-lived owned topology.

Changing all three again would not identify a cause.

## 5. Receiver Metadata Comparison

The known-good step-94 probe and the productive Receiver both declare:

```text
type: 3

parentRequirements:
  {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

implemented:
  {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
```

Both expose:

```text
ReceiveData($jsonString)
```

Both were connected through the same native MQTT parent interface.

The productive Receiver increments its earliest persistent diagnostic counter
before parsing, pairing or Account handoff. The zero counter therefore cannot
be explained by:

- envelope rejection;
- retained-message rejection;
- missing Account pairing;
- Account wrapper availability;
- semantic payload parsing.

No static metadata mismatch explains the live gap.

## 6. IP-Symcon Data-Flow Contract

The official IP-Symcon data-flow documentation describes the parent-to-child
direction as:

```text
parent SendDataToChildren()
  -> connected children ReceiveData()
```

The `SendDataToChildren()` reference states that data is sent to all connected
child instances.

The module metadata must expose compatible requirement and implementation
GUIDs, and the child must have the parent connection established.

Sources:

- [IP-Symcon data flow](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/datenfluss/)
- [IP-Symcon SendDataToChildren](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/module/senddatatochildren/)
- [IP-Symcon module metadata](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/)

The current productive topology and both Receiver metadata sets satisfy these
static requirements.

This makes a sibling test meaningful: two compatible children connected to
the same native MQTT Client should both reach `ReceiveData()` for the same
parent delivery.

## 7. Upstream Comparison

### 7.1 `TA2k/ioBroker.navimow`

The current adapter uses `mqtt.js` and:

- creates a fresh random client identity;
- subscribes after the MQTT `connect` event;
- uses the four known exact device topics;
- additionally subscribes to one device-level wildcard;
- processes messages through one client callback;
- reconnects automatically.

The additional wildcard is not selected for the next Symcon test:

- the private Python capture received messages with the exact allowlist;
- the step-94 native Symcon probe received messages with the same exact
  allowlist;
- adding a wildcard would change the subscription contract while testing the
  child path.

The upstream implementation remains useful evidence that subscriptions are
normally established after connection, but it does not expose broker SUBACK
state to the Symcon module.

Source:

- [TA2k/ioBroker.navimow `main.js`](https://raw.githubusercontent.com/TA2k/ioBroker.navimow/main/main.js)

### 7.2 `ilguala/navimow_pro`

The current Home Assistant integration documents adaptive REST polling:

```text
normal: approximately 30 seconds
active or returning: approximately 12 seconds
```

Its documented high-level architecture does not provide an MQTT receive path
that can explain the native Symcon zero-ingress result.

It does reinforce the current fallback decision:

- REST can remain the stable public state authority;
- faster active polling is a viable user-facing behavior;
- MQTT remains an optional latency optimization until proven.

Source:

- [ilguala/navimow_pro](https://github.com/ilguala/navimow_pro)

## 8. Updated Hypothesis Matrix

| Rank | Hypothesis | Evidence after step 127 | Next treatment |
|---:|---|---|---|
| 1 | Retained native MQTT Client does not deliver to any custom child | healthy Core status, zero productive Receiver calls | attach known-good sibling probe |
| 2 | Productive Receiver is not selected for parent delivery despite matching metadata | known-good probe worked; productive Receiver never called | compare sibling counters on same parent |
| 3 | Broker session has no effective subscription despite configured topics | status `102` does not expose SUBACK | remains when both siblings stay zero |
| 4 | Broker published no matching traffic during the window | possible, but visible mowing and prior cadence weaken it | retain physical activity gate |
| 5 | Retained WebSocket or MQTT instance has stale internal state | retained Core identities are the major remaining transport difference | isolate only after sibling result |
| 6 | Exact topics are insufficient | contradicted by private and step-94 exact-topic captures | do not add wildcard now |
| 7 | Stable Client ID alone prevents delivery | fresh-ID test also produced zero ingress | closed as sole cause |

## 9. Selected Cross-Probe

### 9.1 Topology

Temporarily add the already proven Receive Probe as a second child of the
retained native MQTT Client:

```text
retained WebSocket Client
  -> retained MQTT Client
    -> productive Navimow MQTT Receiver
    -> temporary known-good Receive Probe
```

The productive Receiver remains connected and unchanged.

No Core instance is created, replaced, deleted or reparented.

### 9.2 Constant fields

Retain:

- published productive `main`;
- stable Account-owned Client ID;
- retained WebSocket and MQTT instances;
- normal `ConnectMqttShadow()` lifecycle;
- existing four exact QoS-0 subscriptions;
- existing Receiver-to-Account pairing;
- existing activation order;
- one credential retrieval;
- visible supervised mowing;
- REST authority.

### 9.3 Changed field

Add only:

```text
one temporary known-good probe child
```

The probe shall be armed before the single Connect call.

## 10. Result Matrix

Use deltas from immediate pre-Connect baselines.

| Productive Receiver | Sibling Probe | Interpretation |
|---:|---:|---|
| `> 0` | `> 0` | retained parent delivery works; earlier zero result was session or traffic specific |
| `0` | `> 0` | parent delivery works but productive Receiver is not selected or invoked |
| `> 0` | `0` | productive path works; probe arming or sibling connection is invalid |
| `0` | `0` | gap remains in retained Core, effective subscription or broker traffic before child routing |

Any nonzero productive Receiver counter must also be classified through its
existing bounded result code.

No outcome authorizes MQTT to update public Device variables.

## 11. Publication Design

The known-good Receive Probe is test infrastructure, not productive
functionality.

The next implementation step shall prepare a temporary dated branch from the
exact standalone `main` commit and add only the previously validated probe
module files.

It shall not change:

- `NavimowAccount`;
- `NavimowMqttReceiver`;
- any productive library;
- forms, variables or profiles;
- release metadata or tags.

The temporary branch shall not be merged into `main`.

## 12. Live Harness Contract

The future bounded harness shall:

1. verify exact branch and source hashes;
2. capture the complete productive compatibility baseline twice;
3. create exactly one temporary probe instance;
4. connect it as sibling to the retained MQTT Client;
5. verify both children have the same expected parent;
6. arm the probe;
7. require MQTT disabled and credential-empty;
8. require Account authentication usable;
9. require visible supervised mowing;
10. enable MQTT once;
11. invoke normal `NAVAC_ConnectMqttShadow()` exactly once;
12. observe both Receiver counters and Core statuses;
13. stop after discriminating ingress or the fixed cutoff;
14. invoke normal Disconnect exactly once;
15. disable MQTT;
16. close and delete the temporary probe;
17. verify runtime and productive contracts;
18. return Module Control to `main`;
19. delete the temporary publication branch after closure.

## 13. Fixed Limits

```text
broker connection attempts: 1
Connect calls:              1
Disconnect calls:           1
retries:                    0
observation cutoff:         165 seconds
hard deadline:              180 seconds
cleanup reserve:            at least 15 seconds
MQTT publishes:             0
mower commands:             0
```

Failure, timeout, ambiguity or transport loss permits cleanup only.

## 14. Cleanup Invariants

Before result interpretation, require:

- probe evidence closed;
- WebSocket inactive;
- authorization header empty;
- MQTT username and password empty;
- MQTT feature disabled;
- stable Client ID retained;
- productive Receiver still paired;
- exact subscriptions retained;
- temporary probe instance deleted;
- no other instance deleted or reparented;
- all 14 variable contracts unchanged;
- all five Archive Control contracts unchanged;
- archive history queryable;
- authentication usable;
- Module Control restored to exact `main`.

## 15. Authorization Gates

This planning step authorizes no later operation.

### Gate A: Temporary Probe Publication

Required authorization:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-Branches freigegeben.
```

### Gate B: Symcon Update and Inactive Sibling Staging

Required authorization after Gate A passes:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-Staging freigegeben.
```

This permits no MQTT connection.

### Gate C: One-Shot Live Cross-Probe

Required authorization after Gate B and read-only verification pass:

```text
Ein einmaliger MQTT-Sibling-Cross-Probe-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

Immediately before execution, additionally require:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

## 16. Architecture Decisions

### AD-NAV-468: Close Client ID as the next variable

**Decision:** Do not repeat or extend the Fresh-Client-ID experiment.

**Reason:** One isolated live change produced healthy Core clients and the same
zero-ingress result.

### AD-NAV-469: Test sibling routing before replacing Core instances

**Decision:** Attach the known-good probe to the retained MQTT Client first.

**Reason:** This changes one child boundary without replacing the productive
transport or reparenting the productive Receiver.

### AD-NAV-470: Keep exact subscriptions constant

**Decision:** Do not add the upstream device wildcard during the cross-probe.

**Reason:** Exact topics already received live messages in two independent
clients, and a wildcard would combine routing and subscription hypotheses.

### AD-NAV-471: Reuse proven test infrastructure

**Decision:** Republish the exact known-good Receive Probe rather than create a
new diagnostic child.

**Reason:** Its metadata, envelope handling, bounds and cleanup behavior were
already proven in step 94.

### AD-NAV-472: Keep REST authoritative

**Decision:** MQTT remains receive-only and cannot write public variables.

**Reason:** The remaining question is transport delivery, not state ownership.

## 17. Recommended Next Step

Create:

```text
129-native-mqtt-sibling-cross-probe-harness.md
```

That step should:

1. freeze the exact known-good probe source against current standalone `main`;
2. implement offline topology and cleanup validation;
3. implement the private bounded sibling harness;
4. prove one Connect, one Disconnect and zero prohibited calls statically;
5. stop before publication or any live Symcon mutation.
