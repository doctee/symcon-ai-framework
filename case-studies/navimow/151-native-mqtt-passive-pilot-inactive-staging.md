# 151 Native MQTT Passive Pilot Inactive Staging

**Case study:** Navimow native IP-Symcon module
**Status:** Gate C passed read-only; retained pilot chain ready and inactive,
activation gate closed
**Date:** 2026-07-28
**Scope:** Verify the retained receive-only transport as an inactive pilot
candidate without configuration mutation or network communication

## 1. Purpose

Step 150 installed and verified the published recovery hardening while MQTT
remained disabled.

This step:

1. verified the retained Account/Receiver/MQTT/WebSocket chain twice;
2. checked symmetric Account and Receiver pairing;
3. validated exact canonical subscriptions;
4. verified inactive, credential-free transport state;
5. captured a final complete compatibility baseline;
6. stopped before feature enablement or broker communication.

## 2. Authorization

The user explicitly authorized:

```text
Inaktives Staging des receive-only MQTT-Piloten freigegeben.
```

The authorization was applied read-only because the required dedicated chain
already existed, was paired and was configured canonically.

It did not authorize:

- changing Account properties;
- calling `ApplyChanges()`;
- enabling MQTT;
- retrieving credentials;
- activating the WebSocket;
- connecting to the broker;
- restarting Symcon;
- publishing MQTT data;
- sending a mower command.

## 3. Execution

Two independent bounded inactive-topology probes and one final full
compatibility projection ran.

Every MCP result reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
```

Installed module:

```text
branch: main
commit: 7c1747cc
clean:  true
valid:  true
```

## 4. Retained Topology

Verified:

```text
native WebSocket Client
  -> native MQTT Client
    -> Navimow MQTT Receiver
      -> Navimow Account
```

The arrows denote each child instance's configured parent.

| Contract | Result |
|---|---|
| Receiver module type | exact |
| MQTT Client module type | exact |
| WebSocket Client module type | exact |
| Receiver parent | retained MQTT Client |
| MQTT parent | retained WebSocket Client |
| Receiver Account binding | exact |
| Account Receiver selection | exact |

No Core instance was created, deleted, reparented or reconfigured.

## 5. Subscription Contract

The retained MQTT Client contains exactly four subscriptions:

```text
attributes
event
location
state
```

Verified without returning topics or device identity:

- exact keys `Topic` and `QoS`;
- integer `QoS = 0`;
- one common device identity;
- exact device-scoped topic shape;
- no wildcard;
- no duplicate or additional channel.

## 6. Inactive Safety State

Final readback:

```text
MQTT feature:               disabled
public validator status:    disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username:              empty
MQTT password:              empty
credential requests:        0
broker connection attempts: 0
```

The retained non-secret Client ID and canonical subscriptions remain in place.

## 7. Ownership Validation Boundary

While `EnableMqttShadow=false`, the public Account validator deliberately
returns the fail-safe `disabled` classification before reading the private
ownership registry.

Therefore:

```text
fresh live semantic ownership validation in Gate C: not performed
raw private ownership attribute readback:             not attempted
```

Ownership evidence is inherited from the explicit adoption in step 109 and
the later successful owned Connect/Disconnect runs. Current topology,
selection and configuration remain consistent with that evidence.

This does not weaken the activation boundary:

1. Gate D enables the feature through the Account configuration;
2. `ApplyChanges()` first attempts owned credential cleanup;
3. ownership and topology are semantically revalidated;
4. invalid ownership produces `ConfigurationError`;
5. no lifecycle connection is scheduled when validation fails.

The first authorized activation therefore fails closed before credential
retrieval or broker connection if the retained ownership no longer matches.

## 8. Compatibility Baseline

The final complete projection remained equal to step 150:

| Contract | Result |
|---|---|
| productive instances | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| authentication | connected |
| reauthentication required | false |
| token | usable |

The user's configured logging remains intact.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| MQTT enable operations | 0 |
| credential requests | 0 |
| MQTT connection attempts | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-passive-pilot-inactive-staging/
    gate-c-evidence-closure.json
```

No private topic, device identity, endpoint, credential, ObjectID or garden
detail appears in this public report.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive topology | PASS |
| Gate C canonical subscriptions | PASS |
| Gate C credential-free state | PASS |
| Gate D passive activation | CLOSED |
| restart observation | CLOSED |
| natural token observation | CLOSED |
| degraded-connectivity observation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 12. Recommended Next Step

The retained chain is ready for one separately authorized passive activation.

Create:

```text
152-native-mqtt-passive-pilot-activation.md
```

Required authorization:

```text
Aktivierung des receive-only MQTT-Piloten mit Disable-Fallback freigegeben.
```

Gate D should:

1. capture the immediate disabled baseline;
2. enable the Account MQTT feature once;
3. require the five-second delayed lifecycle attempt;
4. prove fail-closed ownership validation before connection;
5. observe one healthy receive-only connection;
6. prove accepted Receiver ingress when a natural mower event occurs;
7. retain REST authority and all variable/archive contracts;
8. disable and clean immediately on any stop condition.
