# 193 Native MQTT Core Resume Health Observation Retry Inactive Staging

**Case study:** Navimow native IP-Symcon module
**Status:** Retry Gate B passed read-only; retained transport remains inactive,
canonical and credential-free
**Date:** 2026-07-29
**Scope:** Execute only retry inactive-staging Gate B from step 191

## 1. Purpose

Step 192 observed a passive scheduled token refresh and established more than
3000 seconds of token horizon.

This step:

1. inspected the retained native chain twice;
2. observed it across more than one 60-second lifecycle interval;
3. proved exact topology and canonical subscriptions;
4. verified stopped lifecycle and stable counters;
5. repeated the complete variable and Archive Control compatibility contract;
6. stopped without any installation mutation.

## 2. Authorization

The user explicitly authorized:

```text
Erneutes inaktives Staging für den Core-Resume-Health-Observation-Retry freigegeben.
```

This authorized bounded read-only projections only.

It did not authorize:

- changing Account or Core properties;
- `ApplyChanges()`;
- MQTT activation or credential retrieval;
- a broker connection;
- a service restart;
- MQTT publish or mower commands.

## 3. Repeated Inactive Projections

Two independent executions 99 seconds apart reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
branch:            main
commit:            45c7bd50
clean:             true
valid:             true
```

The topology hash, subscription hash, connection-attempt counter and
Core-resume observation counter remained equal.

## 4. Retained Topology

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

No object was created, deleted, reparented or configured.

## 5. Subscription Contract

The retained MQTT Client still contains exactly four device-scoped
subscriptions:

```text
attributes
event
location
state
```

Verified without returning topics or device identity:

- exact `Topic` and `QoS` keys;
- integer `QoS = 0`;
- exact channel set for one device;
- no wildcard;
- no duplicate or additional subscription.

## 6. Inactive Lifecycle

The final lifecycle projection proved:

```text
feature enabled:                  false
configuration status:             disabled
lifecycle:                        Disabled
next attempt:                     0
reconnect attempt:                0
Core observation count:           0
Core observation deadline:        0
WebSocket:                        inactive
Authorization headers:           empty
MQTT username and password:       empty
connection attempts:              13, unchanged
Core-resume observations:         0, unchanged
```

Across the 99-second staging window:

```text
credential requests:         0
broker connection attempts:  0
```

## 7. Compatibility and Token Horizon

The complete compatibility projection passed:

| Contract | Result |
|---|---|
| productive instance identities and parents | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| Receiver pairing | retained |
| subscriptions | 4 canonical QoS-0 entries |

At closure:

```text
remaining token horizon: 3067 seconds
retry activation threshold: 2400 seconds
threshold currently met: true
```

This is a staging observation, not an activation reservation. Gate D must
recheck the threshold immediately before mutation.

## 8. Architecture Decisions

### AD-NAV-684: Re-stage after passive refresh

**Decision:** Repeat the complete inactive contract after token refresh.

**Reason:** Authentication movement must not be assumed to preserve unrelated
transport, variable or archive state without evidence.

### AD-NAV-685: Use more than one lifecycle interval

**Decision:** Compare inactive projections across 99 seconds.

**Reason:** Stable hashes and counters beyond 60 seconds exclude delayed
lifecycle work in this gate.

### AD-NAV-686: Treat token horizon as transient

**Decision:** Record the current horizon but require a fresh check immediately
before activation.

**Reason:** Time continues to elapse while acceptance and activation
authorizations are obtained.

### AD-NAV-687: Preserve staging as read-only

**Decision:** Perform no mutation when retained topology already satisfies
every contract.

**Reason:** Reapplying identical Core properties would add risk without adding
evidence.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| inactive projections | 2 |
| compatibility projections | 1 |
| lifecycle projections | 1 |
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| MQTT enable operations | 0 |
| credential requests | 0 |
| broker connections | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| service restarts | 0 |
| created, deleted or reparented objects | 0 |
| Archive Control mutations | 0 |

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-retry-inactive-staging/
    gate-b-evidence-closure.json
```

The public report contains no credential, token value, absolute expiry
timestamp, topic, endpoint, payload, device identity, ObjectID, hostname, IP
address or garden detail.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| retry Gate A passive token refresh | PASS |
| retry Gate B inactive staging | PASS |
| retry Gate C renewed acceptance | NOT GIVEN |
| retry Gate D activation | CLOSED |
| retry Gate E restart arm | CLOSED |
| retry Gate F restart | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 12. Recommended Next Step

Retry Gate C requires renewed explicit acceptance:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.

Falls der Core bis +90 Sekunden nach IPS_KERNELSTARTED nicht gesund ist,
darf der vorhandene receive-only Lifecycle vor Wiedererreichbarkeit der
Konsole den bereits implementierten begrenzten Recovery-Pfad beginnen.
Es werden keine MQTT-Nachrichten veröffentlicht und keine Mäherbefehle
gesendet.
```

This acceptance alone performs no mutation.
