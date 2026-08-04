# Native MQTT Core-Resume Deadline Hardening Inactive Staging

**Case study:** Navimow native IP-Symcon module
**Status:** Gate D passed read-only; retained topology verified inactive,
canonical and credential-free
**Date:** 2026-07-29
**Scope:** Execute only inactive staging Gate D from step 201

## 1. Purpose

Step 205 installed the six-point Core-resume deadline hardening while MQTT
remained disabled.

This step:

1. inspected the retained Account/Receiver/MQTT/WebSocket chain twice;
2. verified exact module types, parent relations and symmetric pairing;
3. validated four exact canonical subscriptions;
4. observed unchanged topology, configurations and counters across 91 seconds;
5. repeated the complete variable, archive, authentication and REST contract;
6. stopped without changing any property or Core object.

## 2. Authorization

The user explicitly authorized:

```text
Inaktives Staging der MQTT-Core-Resume-Deadline-Härtung freigegeben.
```

The authorization was applied read-only because the retained dedicated chain
satisfied every staging precondition.

It did not authorize:

- changing Account or Core properties;
- calling `ApplyChanges()`;
- enabling MQTT or retrieving credentials;
- activating the WebSocket or connecting to the broker;
- restarting Symcon;
- publishing MQTT data;
- sending a mower command.

## 3. Probe Boundary

The private Gate-C compatibility probe was reused:

```text
private/navimow-capture/
  native-mqtt-deadline-hardening-update-readonly.php
```

It emits only bounded Booleans, counters and hashes. It does not emit
ObjectIDs, device identities, topics, endpoints or credentials.

No temporary Symcon object was created.

## 4. Time-Separated Projections

Two bounded executions reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
branch:            main
commit:            8fdab84b
clean:             true
valid:             true
```

Observation window:

```text
first projection:  2026-07-29T11:59:22Z
second projection: 2026-07-29T12:00:53Z
elapsed:           91 seconds
```

The window exceeds the normal 60-second lifecycle interval. The instance,
configuration, variable, archive, command, topology and subscription hashes
were identical.

## 5. Retained Topology

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
| topology and configuration hashes | stable |

No Core object was created, deleted, reparented or reconfigured.

## 6. Subscription Contract

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
- one configured device and exact channel set;
- no wildcard;
- no duplicate or additional subscription;
- stable subscription hash.

## 7. Disabled Lifecycle

Both projections were equal:

```text
feature enabled:                   false
configuration status:              disabled
lifecycle:                         Disabled
next attempt:                      0
reconnect attempt:                 0
Core observation count:            0
Core observation deadline:         0
WebSocket:                         inactive
Authorization headers:            empty
MQTT username and password:        empty
connection attempts:               14, unchanged
connection successes:              6, unchanged
connection failures:               0, unchanged
Core-resume observations:           1, unchanged
last connection trigger:           initial
```

The last connection attempt remained:

```text
2026-07-29T09:44:27Z
```

Across the 91-second Gate-D window:

```text
credential retrieval calls:  0
broker connection attempts:  0
```

The stopped lifecycle and unchanged counters exclude delayed transport work
during this gate.

## 8. Ownership and Compatibility

While `EnableMqttShadow=false`, the public validator deliberately returns the
safe `disabled` classification rather than performing a credential-bearing
adoption check.

Ownership continuity is supported by:

- the exact retained Core chain;
- exact symmetric Account/Receiver pairing;
- stable instance, configuration, topology and subscription hashes;
- prior explicit adoption and owned-cleanup evidence.

Fresh fail-closed ownership validation remains part of a later activation
gate. It was not invoked here because this gate prohibited credential
retrieval.

Both complete projections additionally proved:

| Contract | Result |
|---|---|
| installed module | clean and valid `main@8fdab84b` |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| access token | usable |
| REST continuity | operational |

## 9. Architecture Decisions

### AD-NAV-727: Treat the retained chain as completed staging

**Decision:** Perform no mutation while the disabled chain satisfies every
staging contract.

**Reason:** Reapplying identical properties or `ApplyChanges()` would add risk
without improving evidence.

### AD-NAV-728: Observe more than one lifecycle interval

**Decision:** Compare complete projections across 91 seconds.

**Reason:** Stable hashes and counters beyond the 60-second lifecycle interval
exclude delayed scheduled transport work.

### AD-NAV-729: Keep credential validation behind activation

**Decision:** Preserve inherited ownership continuity and require fresh
fail-closed validation only when a later gate enables the feature.

**Reason:** Inactive staging must not retrieve, persist or expose credentials.

### AD-NAV-730: Keep persistence acceptance separate

**Decision:** Gate D neither consumes prior acceptance nor authorizes
activation or restart.

**Reason:** Core credential persistence and autonomous recovery require
renewed contextual acceptance.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| complete inactive projections | 2 |
| observation interval | 91 seconds |
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| MQTT enable operations | 0 |
| credential retrieval calls | 0 |
| MQTT connection-attempt delta | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| service restarts | 0 |
| created, deleted or reparented objects | 0 |
| Core configuration mutations | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-deadline-hardening-inactive-staging/
    gate-d-evidence-closure.json
```

No private topic, device identity, endpoint, credential, ObjectID, hostname,
IP address or garden detail appears in this public report.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| Gate B metadata conformance | PASS |
| Gate C disabled Symcon update | PASS |
| Gate D inactive staging | PASS |
| Gate D canonical subscriptions | PASS |
| Gate D credential-free state | PASS |
| Gate E renewed persistence acceptance | NOT GIVEN |
| Gate F passive token readiness | CLOSED |
| temporary activation | CLOSED |
| service restart | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

**Gate D is complete.**

## 13. Recommended Next Step

Before activation or restart authorization, Gate E requires renewed explicit
acceptance:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.

Falls der Core bis +180 Sekunden nach IPS_KERNELSTARTED nicht gesund ist,
darf der vorhandene receive-only Lifecycle vor Wiedererreichbarkeit der
Konsole den bereits implementierten begrenzten Recovery-Pfad beginnen.
Es werden keine MQTT-Nachrichten veröffentlicht und keine Mäherbefehle
gesendet.
```

This contextual acceptance alone performs no mutation and authorizes neither
activation nor restart.
