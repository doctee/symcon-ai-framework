# 152 Native MQTT Passive Pilot Activation

**Case study:** Navimow native IP-Symcon module
**Status:** Gate D passed; receive-only MQTT pilot active and healthy, restart
gate closed
**Date:** 2026-07-28
**Scope:** Enable the retained pilot once, verify delayed recovery startup,
healthy ingress and complete productive compatibility

## 1. Purpose

Step 151 verified the retained transport as an inactive, credential-free pilot
candidate.

This step:

1. enabled the Account MQTT feature once;
2. called `ApplyChanges()` once;
3. required ownership-aware delayed startup;
4. observed one healthy receive-only transport;
5. proved productive Receiver ingress;
6. verified targeted REST reconciliation;
7. repeated the complete variable and archive compatibility contract;
8. stopped before restart or token experiments.

## 2. Authorization

The user explicitly authorized:

```text
Aktivierung des receive-only MQTT-Piloten mit Disable-Fallback freigegeben.
```

This authorized one opt-in and its automatic delayed connection. Failure would
have permitted immediate disable and credential cleanup only.

It did not authorize:

- an explicit second Connect;
- MQTT publish;
- a mower command;
- a Symcon restart;
- token manipulation or induced expiry;
- deliberate network interruption;
- Core instance creation, deletion or reparenting.

## 3. Activation

Immediate preconditions:

```text
MQTT feature:               disabled
configuration status:       disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
```

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
```

This proves that:

- `ApplyChanges()` did not connect inline;
- retained ownership and topology were accepted;
- connection work was deferred to the five-second lifecycle timer;
- the fail-closed fallback was not required.

## 4. Healthy Transport

After the delayed lifecycle attempt:

```text
lifecycle:       ShadowActive
MQTT status:     102
WebSocket status: 102
WebSocket active: true
connection failures: 0
reconnect attempts:  0
```

After the first 60-second health observation:

```text
lifecycle state:        ShadowActive
transition reason:      healthy
healthy-since present:  true
next observation:       scheduled
connection successes:   1
unexpected disconnects: 0
```

Transient credentials are present only in their owned active Core
configuration. They were returned through the probe only as presence
booleans.

## 5. Receiver Ingress

At the first active observation:

```text
Receiver calls:     15
Receiver forwarded: 15
```

At the final compatibility observation:

```text
Receiver calls:     90
Receiver forwarded: 90
last result:         accepted
```

The inactive baseline contained one historical accepted message. The pilot
therefore observed substantial new natural ingress without a mower command or
artificial activity.

No topic, payload, device identity or location was returned.

## 6. REST Reconciliation

At the first completed health gate:

```text
Account received:          61
Account accepted:          61
Account rejected:          0
reconciliation attempts:   3
comparison matches:        1
comparison mismatches:     0
comparison stale:          0
tracked devices:           1
pending reconciliations:   1
```

The comparison result proves at least one MQTT hint was checked against REST
and matched. Public variables remain updated only through the REST mapping
path.

MQTT remains a private acceleration hint, not state authority.

## 7. Single-Attempt Evidence

The live sequence proves:

- one delayed lifecycle schedule;
- no explicit Connect call;
- one successful Core health confirmation;
- zero connection failures;
- zero reconnect attempts;
- no overlapping connection evidence.

The Navimow MQTT credential endpoint does not expose a live call counter.
Therefore the report does not claim an independently measured endpoint-call
count. The single-attempt classification is derived from the bounded lifecycle
sequence and absence of failure or reconnect evidence.

## 8. Compatibility

The complete active-state projection passed:

| Contract | Result |
|---|---|
| installed module | `main` / `7c1747cc` / clean / valid |
| productive instances and parents | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| token | usable |
| Receiver pairing | retained |
| subscriptions | 4 canonical QoS-0 entries |

The user's variable logging remains intact.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature enable | 1 |
| Account `ApplyChanges()` | 1 |
| explicit MQTT Connect | 0 |
| automatic delayed lifecycle connection | 1 |
| MQTT publish | 0 |
| mower commands | 0 |
| Symcon restart | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |
| fallback disable | 0 |

Every MCP result had:

```text
transportError: null
executionError: null
truncated:      false
```

## 10. Final State

The receive-only passive pilot intentionally remains active:

```text
module commit:        7c1747cc
MQTT feature:         enabled
lifecycle:            ShadowActive / healthy
Core transport:       102 / 102
REST polling:         retained
public state authority: REST
```

No restart or token manipulation has been performed.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-passive-pilot-activation/
    gate-d-evidence-closure.json
```

Reusable private read-only source:

```text
private/navimow-capture/
  native-mqtt-passive-pilot-active-readonly.php
```

No private credential, topic, endpoint, device identity, ObjectID, payload,
location or garden detail appears in this public report.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive staging | PASS |
| Gate D passive activation | PASS |
| Receiver ingress | PASS |
| REST reconciliation | PASS |
| variable and archive continuity | PASS |
| Gate E restart observation | CLOSED |
| natural token observation | CLOSED |
| degraded-connectivity observation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 13. Recommended Next Step

The active pilot may continue passively. The next controlled mutation is one
supervised Symcon restart.

Create:

```text
153-native-mqtt-passive-pilot-restart-observation.md
```

Required authorization:

```text
Ein beaufsichtigter Symcon-Restart während des receive-only MQTT-Piloten ist freigegeben.
```

Before restart, capture one immediate active baseline. After restart prove:

1. transient credential cleanup;
2. no inline connection during `ApplyChanges()`;
3. one delayed reconstruction;
4. no duplicate topology;
5. continued Receiver ingress and REST reconciliation;
6. unchanged variables and Archive Control contracts;
7. Disable and cleanup on any stop condition.
