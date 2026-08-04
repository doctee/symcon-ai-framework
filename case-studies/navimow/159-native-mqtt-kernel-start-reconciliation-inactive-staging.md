# 159 Native MQTT Kernel Start Reconciliation Inactive Staging

**Case study:** Navimow native IP-Symcon module
**Status:** Gate C passed read-only; retained topology verified inactive and
credential-free
**Date:** 2026-07-28
**Scope:** Execute only inactive topology staging Gate C from step 156

## 1. Purpose

Step 158 installed and verified the published kernel-start reconciliation
while MQTT remained disabled.

This step:

1. verified the retained Account/Receiver/MQTT/WebSocket chain twice;
2. checked symmetric Account and Receiver pairing;
3. validated four exact canonical subscriptions;
4. verified inactive, credential-free transport state;
5. stopped before a service restart, feature enablement or broker
   communication.

## 2. Authorization

The user explicitly authorized:

```text
Inaktives Staging der Kernelstart-Reconciliation freigegeben.
```

The authorization was applied read-only because the required dedicated chain
already existed, was paired and was configured canonically.

It did not authorize:

- changing Account or Core properties;
- calling `ApplyChanges()`;
- enabling MQTT;
- retrieving credentials;
- activating the WebSocket;
- connecting to the broker;
- restarting Symcon;
- publishing MQTT data;
- sending a mower command.

## 3. Read-Only Probe

The older general inactive-topology probe inspected the detailed chain only
when MQTT was enabled. That precondition did not fit Gate C.

A dedicated private read-only projection was therefore added:

```text
private/navimow-capture/
  native-mqtt-kernel-start-inactive-readonly.php
```

It:

- reads the retained chain while MQTT is disabled;
- emits only Booleans, counters and hashes;
- never emits ObjectIDs, Device IDs, topics, endpoints or credentials;
- contains no mutation, `ApplyChanges()`, credential request or network call.

The probe passed PHP syntax validation before use.

## 4. Repeated Execution

Two independent bounded executions reported:

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
commit: aed0b434
clean:  true
valid:  true
```

Topology and subscription hashes were identical across both runs.
Connection-attempt and Core-resume counters did not change between runs.

## 5. Retained Topology

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

## 6. Subscription Contract

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

## 7. Inactive Safety State

Repeated readback:

```text
MQTT feature:               disabled
public validator status:    disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username:              empty
MQTT password:              empty
connection-attempt delta:   0
Core-resume observation delta: 0
credential requests:        0
broker connection attempts: 0
```

The retained non-secret Client ID and canonical subscriptions remain in
place.

## 8. Ownership Validation Boundary

While `EnableMqttShadow=false`, the public Account validator deliberately
returns the fail-safe `disabled` classification before reading the private
ownership registry.

Therefore:

```text
fresh semantic ownership validation in Gate C: not performed
raw private ownership attribute readback:         not attempted
```

Ownership evidence remains inherited from the explicit adoption and later
successful owned Connect/Disconnect runs. Current topology, selection and
configuration remain consistent with that evidence.

This does not weaken a later activation boundary. With the feature enabled,
the Account revalidates ownership and topology before credential retrieval or
connection; invalid ownership fails closed.

## 9. Architecture Closure

### AD-NAV-545: Use a disabled-state topology projection

**Decision:** Gate C uses a dedicated read-only projection that validates the
retained chain without requiring feature enablement.

**Reason:** An inactive staging gate must not depend on the very activation it
is intended to precede.

### AD-NAV-546: Compare private structures by hash

**Decision:** Repeated topology and subscription equality is recorded by
hashes while public evidence contains only structural results.

**Reason:** This proves stability without disclosing installation identity or
private topics.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| MQTT enable operations | 0 |
| credential requests | 0 |
| MQTT connection attempts | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| service restarts | 0 |
| created or deleted objects | 0 |
| reparented objects | 0 |
| Core configuration mutations | 0 |

Every MCP result was checked separately for transport error, PHP execution
error and truncation.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-kernel-start-reconciliation-inactive-staging/
    gate-c-evidence-closure.json
```

No private topic, device identity, endpoint, credential, ObjectID or garden
detail appears in this public report.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive topology staging | PASS |
| Gate C canonical subscriptions | PASS |
| Gate C credential-free state | PASS |
| Gate D disabled kernel-hook restart | CLOSED |
| Gate E credential-persistence acceptance | CLOSED |
| Gate F receive-only activation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

The next independently authorized action is Gate D from step 156:

```text
Ein beaufsichtigter Symcon-Neustart mit deaktiviertem MQTT zur Kernel-Hook-Prüfung ist freigegeben.
```
