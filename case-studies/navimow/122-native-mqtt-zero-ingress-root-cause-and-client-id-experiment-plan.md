# 122 Native MQTT Zero-Ingress Root-Cause and Client-ID Experiment Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Offline analysis complete; implementation and live gates closed
**Date:** 2026-07-28
**Scope:** Isolate the remaining native MQTT delivery difference without
changing REST authority or sending mower commands

## 1. Purpose

Step 121 proved a healthy native WebSocket and MQTT Core connection during
visible mowing, but the earliest Receiver counter remained zero for the full
bounded session.

This step:

1. compares the successful disposable step-94 transport with the retained
   productive chain;
2. verifies the productive configuration and activation order;
3. evaluates the remaining client-session hypothesis;
4. selects one controlled fresh-client-ID experiment;
5. defines restoration, privacy, safety and evidence gates.

This step performs no live mutation, broker connection, module publication or
mower action.

## 2. Evidence Baseline

### 2.1 Successful disposable transport

Step 94 proved:

- native `WebSocket Client -> MQTT Client -> PHP child` delivery;
- WSS on port 443;
- binary WebSocket transfer;
- certificate verification;
- one Bearer authorization header;
- MQTT username and password;
- a fresh run-specific client ID;
- a bounded keepalive;
- four exact QoS-0 subscriptions without wildcards;
- two accepted `location` messages;
- complete removal of the temporary topology.

### 2.2 Retained productive transport

Steps 109 through 121 proved:

- the same native WebSocket and MQTT Core module types;
- the same parent-interface direction;
- the same WSS transport shape;
- the same four exact QoS-0 subscription shapes;
- a retained Account-owned Receiver, MQTT Client and WebSocket Client;
- one stable Account-scoped client identity;
- healthy Core status during the active session;
- zero calls to `NavimowMqttReceiver::ReceiveData()`;
- automatic credential cleanup and disabled final state.

Step 121 therefore places the observed gap before the Receiver parser and
before Account ingestion.

## 3. Field-by-Field Comparison

No private endpoint, topic, credential, client identity, device identity or
ObjectID is reproduced here.

| Field | Successful step 94 | Productive step 121 | Relevance |
|---|---|---|---|
| WebSocket scheme | `wss` | `wss` | equal |
| WebSocket port | 443 | 443 | equal |
| WebSocket data type | binary | binary | equal |
| certificate verification | enabled | enabled | equal |
| authorization shape | one Bearer header | one Bearer header | equal |
| MQTT authentication | username/password | username/password | equal |
| keepalive | bounded | 60 seconds | materially equal |
| subscription count | four | four | equal |
| subscription topics | exact device topics | exact device topics | equal shape |
| subscription QoS | QoS 0 | QoS 0 | equal |
| wildcards | none | none | equal |
| native child interface | MQTT child interface | same interface GUID | equal |
| WebSocket activation | once | once | equal |
| MQTT Client instance | newly created | retained | different |
| WebSocket instance | newly created | retained | different |
| child instance | disposable probe | productive Receiver | different |
| client ID | fresh/run-specific | stable/reused | different |
| physical context | messages observed | visible mowing | both support traffic |
| Receiver ingress | two probe calls | zero Receiver calls | unresolved outcome |

The retained Core instance identities and the retained client ID are coupled
differences. The current evidence does not prove which one is causal.

## 4. Productive ApplyChanges Sequence

The current `ConnectMqttShadow()` sequence is:

1. validate feature enablement and retained ownership;
2. force WebSocket `Active = false`;
3. apply WebSocket changes;
4. retrieve fresh MQTT/WSS credentials through the authenticated REST client;
5. set MQTT username, password, stable client ID, keepalive and subscriptions;
6. apply MQTT Client changes;
7. set WebSocket URL, authorization header, binary type, certificate
   verification and `Active = false`;
8. apply WebSocket changes;
9. validate the complete inactive transport shape;
10. record ownership;
11. set WebSocket `Active = true`;
12. apply WebSocket changes exactly once.

The subscriptions are therefore applied before WebSocket activation. No
ordering defect is visible in the module source.

## 5. Native MQTT Semantics

The official IP-Symcon documentation states that:

- the MQTT Client receives subscribed topics;
- subscriptions are configured on the MQTT Client;
- MQTT 3.1 and 3.1.1 are supported;
- subscriptions are sent with QoS 0;
- username/password authentication is supported;
- the MQTT Client ID can be customized.

Sources:

- [IP-Symcon MQTT Client](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-client/)
- [IP-Symcon MQTT device list](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/geraeteliste/)
- [IP-Symcon 6.0 migration notes](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v55-v60-q3-2021/)

Neither the official documentation nor the installed configuration surface
used in the live installation exposes:

- a configurable Clean Session/Clean Start value;
- the broker CONNACK session-present flag;
- SUBACK results;
- a subscription-level receive counter.

It is therefore not valid to infer clean-session behavior from Core status
`102` alone.

## 6. Stable Client Identity

The Account registers `MqttClientIdentity` as an internal attribute.

On first adoption it creates:

```text
32 lowercase hexadecimal characters from random bytes
```

The native client ID is then derived as:

```text
symcon_navimow_ + first 24 identity characters
```

That identity is retained across disconnects, reconnects, module updates and
IP-Symcon restarts. It is also represented indirectly in the ownership
registry through:

- a client-identity hash;
- a transport-configuration hash containing the expected client ID.

Manually changing only the native MQTT Client property is therefore not an
approved production repair:

- the next normal Connect would overwrite it;
- Account ownership validation would intentionally fail while it differs;
- the normal Disconnect path would reject the mismatched ownership.

## 7. Updated Hypothesis Matrix

| Rank | Hypothesis | Evidence | Next treatment |
|---:|---|---|---|
| 1 | Reused client identity changes broker session/subscription behavior | only material transport property difference known; native Clean Session state is opaque | isolate with fresh ID |
| 2 | Retained native MQTT or WebSocket instance has stale internal state | both retained instances differ from step 94 | remains if fresh ID fails |
| 3 | Productive child routing differs despite matching interface metadata | step 121 stopped before `ReceiveData()`; step 94 proved another child | sibling probe only after client-ID result |
| 4 | Broker published no matching message in step 121 | possible, but visible mowing and prior active cadence make it less likely | retain physical activity gate |
| 5 | Subscription ApplyChanges ordering is wrong | source applies subscriptions before activation | currently unsupported |
| 6 | Receiver or Account parser rejects input | earliest Receiver counter stayed zero | excluded for step 121 |

The client ID is a testable hypothesis, not a diagnosed cause.

## 8. Selected Single-Variable Experiment

### 8.1 Constant fields

The experiment shall retain:

- the existing productive Receiver, MQTT Client and WebSocket Client;
- all parent and child connections;
- the same authenticated Account;
- the same credential endpoint;
- the same WSS endpoint and header shape;
- the same MQTT username and password source;
- the same keepalive;
- the same four exact QoS-0 subscriptions;
- the same activation order;
- one Connect only;
- the same Receiver diagnostics;
- REST as the only public variable authority.

### 8.2 Changed field

Only the MQTT `ClientID` property shall use a fresh, run-specific,
non-identifying value for the bounded active session.

The persistent `MqttClientIdentity` attribute and ownership registry shall not
be rotated or rewritten for the experiment.

This deliberately limits the success criterion to the earliest Receiver
boundary. Account ingestion may return `pairing-rejected` while the temporary
client ID differs from the owned transport hash. Such a rejection is expected
and must not be classified as a transport failure.

### 8.3 Why no permanent rotation yet

A permanent rotation would combine diagnosis with a production-state
migration. It could also hide whether success came from a fresh broker
identity or another changed ownership path.

Permanent adoption is considered only after positive Receiver-ingress
evidence.

## 9. Required Experimental Harness

The normal `ConnectMqttShadow()` cannot perform this test because it always
sets the retained client ID.

The next implementation step shall prepare a temporary private diagnostic
branch against the exact published module revision. It may add only:

- one bounded experimental connect entry point;
- one matching restoration entry point;
- offline tests for the changed and restored Core shapes;
- a private one-shot live harness.

The diagnostic connect entry point shall:

1. acquire the existing lifecycle semaphore;
2. require enabled, inactive, credential-empty and valid owned baseline state;
3. retrieve credentials through the existing API client;
4. generate a fresh private run-specific client ID;
5. configure the same MQTT fields and subscriptions as production;
6. configure the same inactive WebSocket shape;
7. activate the WebSocket exactly once;
8. return no secret, endpoint, topic, identity or payload.

The restoration entry point shall be able to run even though the temporary
client ID makes the normal ownership hash invalid. It shall:

1. verify the exact retained instance IDs and module GUIDs;
2. deactivate the WebSocket;
3. clear the authorization header, MQTT username and MQTT password;
4. restore the stable client ID derived from `MqttClientIdentity`;
5. preserve the exact subscriptions and keepalive;
6. apply MQTT and WebSocket changes;
7. revalidate the original ownership registry;
8. clear temporary lifecycle state;
9. leave MQTT disabled and inactive.

The live harness shall invoke restoration in `finally`. Emergency cleanup
shall repeat the same bounded restore contract without reconnecting.

## 10. Publication Boundary

The diagnostic entry points are not productive module features.

They shall:

- not be added to the canonical SAEF distribution;
- not be merged into standalone `main`;
- not be tagged;
- not appear in the user form;
- exist only on a temporary experiment branch;
- be removed by switching the Module Control installation back to the exact
  previously published `main` revision after the test.

The private patch, source hashes and rollback material belong below
`private/`.

## 11. Live Safety Contract

The live gate remains closed until separately authorized.

When authorized, the run requires:

- mower visibly mowing and supervised;
- official app and physical stop control available;
- no module mower command;
- receive-only MQTT;
- no MQTT publish;
- one WebSocket activation;
- one broker connection attempt;
- no retry after timeout, ambiguity or zero ingress;
- a 180-second hard deadline;
- observation cutoff at 165 seconds;
- at least 15 seconds reserved for cleanup;
- cleanup in `finally`;
- post-cleanup read-back.

## 12. Evidence Contract

Private machine-readable evidence shall record only:

- source and harness hashes;
- relative timing;
- Core status codes;
- WebSocket active state;
- changed-field classification `fresh-client-id`;
- Receiver counter deltas;
- Account counter deltas and bounded result codes;
- cleanup calls and outcomes;
- configuration-shape hashes;
- compatibility hashes and counts.

It shall not record:

- credentials or tokens;
- WebSocket URL or host;
- topic strings;
- client ID values;
- device IDs;
- payloads;
- private ObjectIDs;
- garden geometry.

## 13. Outcome Matrix

| Observation | Interpretation | Decision |
|---|---|---|
| Receiver `receiveCalls > 0` | fresh ID changes delivery outcome; client-session hypothesis strongly supported | stop, clean up, design permanent identity migration separately |
| Receiver ingress and Account rejection both increase | transport success plus expected temporary ownership rejection | same as above |
| Receiver ingress remains zero with healthy Core status | client ID alone did not resolve the gap | clean up; next isolate retained Core instance or sibling-child delivery |
| Core never reaches healthy status | fresh identity experiment inconclusive | clean up; do not retry |
| cleanup or restoration fails | safety failure | emergency cleanup, no further MQTT test |
| variable/archive identity changes | compatibility failure | restore published `main`, stop |

A positive result does not authorize automatic reconnects or make MQTT
authoritative.

## 14. Regression and Compatibility Gates

Before and after the live experiment verify:

- all productive module types;
- all productive instance IDs, parents and connections;
- all 14 existing variable identities and metadata;
- all five Archive Control logging contracts;
- archive history queryability;
- unchanged command evidence;
- no MQTT publish or mower command;
- unchanged REST polling and public variable authority;
- exact Receiver pairing after restoration;
- original stable client ID shape restored;
- ownership valid;
- WebSocket inactive;
- authorization, username and password empty;
- experimental MQTT disabled.

After returning to standalone `main`, repeat the read-only module and
compatibility checks.

## 15. Architecture Decisions

### AD-NAV-443: Test the fresh client ID before replacing Core instances

**Decision:** Change the client ID first while retaining the complete productive
topology.

**Reason:** It is the narrowest remaining configurable transport difference
from the successful disposable probe.

### AD-NAV-444: Measure success at Receiver ingress

**Decision:** Use `ReceiveData()` entry as the primary success boundary and
permit expected Account ownership rejection during the temporary mismatch.

**Reason:** Rewriting persistent ownership would add a second variable and is
not required to prove native child delivery.

### AD-NAV-445: Do not rotate persistent identity during diagnosis

**Decision:** Preserve `MqttClientIdentity` and its ownership registry.

**Reason:** Diagnosis must remain reversible and distinct from a production
migration.

### AD-NAV-446: Keep diagnostics off productive main

**Decision:** Use a temporary private experiment branch and restore the exact
published main revision after the run.

**Reason:** A one-use diagnostic mutation is not a reusable module capability.

### AD-NAV-447: Preserve one-shot semantics

**Decision:** Permit one connection attempt and no retry.

**Reason:** A second attempt would obscure the single-variable result and
increase live transport risk.

## 16. Gate Decision

Offline root-cause analysis:

```text
COMPLETE
```

Temporary diagnostic implementation:

```text
NOT STARTED
```

Publication or Symcon update:

```text
NOT AUTHORIZED
```

Live fresh-client-ID experiment:

```text
NOT AUTHORIZED
```

REST authority, public variables and mower commands:

```text
UNCHANGED
```

## 17. Recommended Next Step

Create:

```text
123-native-mqtt-fresh-client-id-experiment-harness.md
```

That step should implement and offline-validate the private temporary branch,
the one-shot harness, deterministic rollback and privacy-safe evidence schema.
It must stop before publication, Module Control update or broker connection.
