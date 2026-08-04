# 178 Native MQTT Transient Readiness Correction Inactive Staging

**Case study:** Navimow native IP-Symcon module
**Status:** Gate C passed read-only; retained topology verified inactive,
canonical and credential-free
**Date:** 2026-07-29
**Scope:** Execute only inactive staging Gate C from step 175

## 1. Purpose

Step 177 installed and verified the MQTT transient-readiness correction while
MQTT remained disabled.

This step:

1. inspected the retained Account/Receiver/MQTT/WebSocket chain three times;
2. verified exact module types, parents and symmetric pairing;
3. validated four exact canonical subscriptions;
4. observed unchanged topology and counters across 83 seconds;
5. repeated the complete compatibility projection;
6. stopped without changing any property or Core object.

## 2. Authorization

The user explicitly authorized:

```text
Inaktives Staging der MQTT-Transient-Readiness-Korrektur freigegeben.
```

The authorization was applied read-only because the required dedicated chain
already existed and satisfied every staging precondition.

It did not authorize:

- changing Account or Core properties;
- calling `ApplyChanges()`;
- enabling MQTT or retrieving credentials;
- activating the WebSocket or connecting to the broker;
- restarting Symcon;
- publishing MQTT data;
- sending a mower command.

## 3. Probe Boundary

The established private inactive projection was reused:

```text
private/navimow-capture/
  native-mqtt-kernel-start-inactive-readonly.php
```

It reads the disabled chain and emits only Booleans, counters and hashes. It
does not emit ObjectIDs, Device IDs, topics, endpoints or credentials.

No temporary Symcon object was created.

## 4. Repeated Topology Verification

Three bounded executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
branch:            main
commit:            7d141f76
clean:             true
valid:             true
```

The first and final projections were 83 seconds apart. Their topology and
subscription hashes were identical.

Verified chain:

```text
native WebSocket Client
  -> native MQTT Client
    -> Navimow MQTT Receiver
      -> Navimow Account
```

| Contract | Result |
|---|---|
| Receiver module type | exact |
| MQTT Client module type | exact |
| WebSocket Client module type | exact |
| Receiver parent | retained MQTT Client |
| MQTT parent | retained WebSocket Client |
| Receiver Account binding | exact |
| Account Receiver selection | exact |

No Core object was created, deleted, reparented or reconfigured.

## 5. Subscription Contract

The retained MQTT Client contains exactly four device-scoped subscriptions:

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
- exact channel set;
- no wildcard;
- no duplicate or additional subscription.

## 6. Disabled Lifecycle

All three time-separated projections remained equal:

```text
feature enabled:          false
configuration status:     disabled
WebSocket:                inactive
Authorization headers:   empty
MQTT username/password:   empty
connection attempts:      11, unchanged
Core-resume observations: 0, unchanged
```

The separately completed Gate-B diagnostics additionally established:

```text
lifecycle:           Disabled
next attempt at:     0
reconnect attempt:   0
```

Across the 83-second Gate-C window:

```text
credential requests:         0
broker connection attempts:  0
```

## 7. Ownership Boundary

While `EnableMqttShadow=false`, the public configuration validator deliberately
stops at the safe `disabled` classification.

A fresh semantic credential-bearing adoption check is therefore not available
in Gate C without crossing the activation boundary. Ownership continuity is
supported by:

- exact retained Core chain;
- exact symmetric Account/Receiver pairing;
- stable topology and subscription hashes;
- prior explicit adoption and successful owned cleanup evidence.

Activation remains fail-closed: ownership and topology are revalidated before
credential retrieval or connection.

## 8. Compatibility Closure

The final complete read-only projection passed:

| Contract | Result |
|---|---|
| installed module | clean and valid `main@7d141f76` |
| productive instance identities and connections | unchanged |
| variables | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| access token | usable |
| REST state authority | retained |

The user's mower-variable logging remains intact.

## 9. Architecture Decisions

### AD-NAV-621: Treat exact retained topology as completed staging

**Decision:** Perform no mutation when the existing disabled chain satisfies
every staging contract.

**Reason:** Reapplying unchanged properties or `ApplyChanges()` would add risk
without improving evidence.

### AD-NAV-622: Require more than one lifecycle period

**Decision:** Compare the same inactive projection across 83 seconds.

**Reason:** Stable hashes and counters across a complete lifecycle period
distinguish retained inactive state from delayed scheduled work.

### AD-NAV-623: Keep semantic credential adoption behind activation

**Decision:** Preserve inherited ownership continuity at Gate C and require
fresh fail-closed validation only when a later gate enables the feature.

**Reason:** The disabled validator intentionally does not inspect or retrieve
credentials.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| inactive topology projections | 3 |
| complete compatibility projections | 1 |
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| MQTT enable operations | 0 |
| credential requests | 0 |
| MQTT connection-attempt delta | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| service restarts | 0 |
| created or deleted objects | 0 |
| reparented objects | 0 |
| Core configuration mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-transient-readiness-correction-inactive-staging/
    gate-c-evidence-closure.json
```

No private topic, device identity, endpoint, credential, ObjectID or garden
detail appears in this public report.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive staging | PASS |
| Gate C canonical subscriptions | PASS |
| Gate C credential-free state | PASS |
| Gate D renewed persistence acceptance | NOT GIVEN |
| Gate E temporary activation | CLOSED |
| Gate F corrected active restart | CLOSED |
| Gate G mandatory cleanup | NOT ENTERED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 13. Recommended Next Step

Before any activation or restart authorization, Gate D requires this renewed
explicit acceptance:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.
```

This acceptance does not itself authorize MQTT activation or a restart.
