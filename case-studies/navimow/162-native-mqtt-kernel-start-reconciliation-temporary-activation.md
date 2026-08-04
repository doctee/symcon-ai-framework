# 162 Native MQTT Kernel Start Reconciliation Temporary Activation

**Case study:** Navimow native IP-Symcon module
**Status:** Gate F passed; receive-only transport healthy and temporarily
active, active restart gate closed
**Date:** 2026-07-28
**Scope:** Execute only temporary receive-only activation Gate F from step 156

## 1. Purpose

Step 161 recorded the bounded credential-persistence acceptance. This step
activates the retained native transport exactly once and establishes the
active pre-restart baseline required by Gate G.

## 2. Authorization

The user explicitly authorized:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Restarttest freigegeben.
```

This authorized:

- one `EnableMqttShadow=true` mutation;
- one Account `ApplyChanges()`;
- the existing delayed lifecycle connection;
- bounded read-only diagnostics;
- immediate Disable fallback on any stop condition.

It did not authorize:

- an explicit second Connect;
- a Symcon service restart;
- MQTT publish;
- mower commands;
- Core instance creation, deletion or reparenting.

## 3. Immediate Baseline

The activation harness first captured:

```text
MQTT feature:                 disabled
configuration status:         disabled
lifecycle:                    Disabled
MQTT and WebSocket:           inactive
Authorization headers:        empty
MQTT username and password:   empty
connection attempts:          captured privately
connection successes:         captured privately
Core-resume observations:     captured privately
Receiver ingress:             captured privately
```

All prerequisites passed.

## 4. Single Activation

Executed exactly:

```text
IPS_SetProperty(Account, "EnableMqttShadow", true): 1
IPS_ApplyChanges(Account):                           1
explicit NAVAC_ConnectMqttShadow calls:              0
```

After 750 milliseconds:

```text
feature enabled:        true
configuration status:   ready
lifecycle state:        ReconnectScheduled
transition reason:      restart-scheduled
next attempt scheduled: true
connection-attempt delta: 0
```

This proves:

- no network operation ran inline in `ApplyChanges()`;
- retained ownership and topology were accepted;
- connection work remained delegated to the five-second lifecycle timer;
- no fallback was required.

## 5. Healthy Receive-Only Transport

The delayed lifecycle produced:

```text
lifecycle:                    ShadowActive
transition reason:            healthy
MQTT status:                  102
WebSocket status:             102
WebSocket Active:             true
connection-attempt delta:     1
connection-success delta:     1
connection-failure delta:     0
Core-resume observation delta: 0
```

Credential values were not returned. The active projection exposed only
presence booleans and confirmed the accepted temporary Core persistence.

## 6. Ingress Classification

No new natural mower message arrived during the bounded Gate-F window.

```text
Receiver call delta:       0
Receiver forwarded delta:  0
rejected delta:            0
classification:            transport-ready/data-pending
```

Step 156 explicitly defines a healthy Core without a fresh mower message as
`transport-ready/data-pending`, not failure.

Historical accepted ingress and REST comparison evidence remain present.
Natural ingress can be observed during the later active restart window if the
device emits traffic.

## 7. REST Authority and Compatibility

The active-state compatibility projection passed.

| Contract | Result |
|---|---|
| module | `main@aed0b434`, clean and valid |
| productive instance identities and parents | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| token | usable |
| Receiver pairing | retained |
| subscriptions | 4 canonical QoS-0 entries |

Existing diagnostics contain successful MQTT-to-REST comparisons with no
mismatch. Public mower variables remain updated only through REST.

## 8. Architecture Closure

### AD-NAV-552: Preserve delayed activation

**Decision:** Gate F accepts activation only when `ApplyChanges()` schedules
the existing lifecycle timer without inline connection.

**Reason:** The timer remains the serialization and retry boundary for
credential retrieval and native Core configuration.

### AD-NAV-553: Count one Account-owned activation attempt

**Decision:** A healthy Gate F requires exactly one additional Account
connection attempt and one success.

**Reason:** This distinguishes deliberate activation from duplicate Connects
or a Core-resume observation.

### AD-NAV-554: Allow data-pending healthy transport

**Decision:** Absence of a fresh natural mower message does not fail a healthy
receive-only Core.

**Reason:** Gate F must not manufacture traffic or send a mower command merely
to prove ingress.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature enable | 1 |
| Account activation `ApplyChanges()` | 1 |
| automatic delayed connection attempts | 1 |
| explicit MQTT Connect | 0 |
| fallback disable | 0 |
| MQTT publish | 0 |
| mower commands | 0 |
| Symcon service restarts | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was checked separately for transport error, PHP execution
error and truncation.

## 10. Current State

The bounded receive-only transport is temporarily active:

```text
module commit:          aed0b434
MQTT feature:           enabled
lifecycle:              ShadowActive / healthy
Core transport:         102 / 102
public state authority: REST
cleanup:                armed and mandatory
```

Authorization and MQTT credentials are currently stored in the owned Core
instances under the exact acceptance from step 161.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-kernel-start-reconciliation-temporary-activation/
    gate-f-evidence-closure.json
```

Reusable private mutation source:

```text
private/navimow-capture/
  native-mqtt-kernel-start-activate-once.php
```

No credential, topic, endpoint, payload, Device ID, ObjectID or garden detail
appears in this public report.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate E credential-persistence acceptance | PASS |
| Gate F temporary activation | PASS |
| Gate F healthy Core | PASS |
| Gate F natural ingress | DATA PENDING |
| Gate G active Core-resume restart | CLOSED |
| Gate H mandatory cleanup | ARMED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

The next independently authorized action is Gate G from step 156:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur Core-Resume-Prüfung ist freigegeben.
```

If Gate G is not authorized, the active test must instead be ended through the
already authorized mandatory cleanup.
