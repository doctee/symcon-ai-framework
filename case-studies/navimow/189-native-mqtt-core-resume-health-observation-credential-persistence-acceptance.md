# 189 Native MQTT Core Resume Health Observation Credential Persistence Acceptance

**Case study:** Navimow native IP-Symcon module
**Status:** Gate D accepted by explicit contextual reference; activation and
restart remain separately gated
**Date:** 2026-07-29
**Scope:** Record renewed persistence and recovery acceptance from step 185

## 1. Purpose

Step 188 proved the retained native topology while MQTT was disabled, inactive
and credential-free.

Gate D renews the risk acceptance required before one more active native
transport restart test. This step records that acceptance and its limits. It
performs no Symcon operation.

## 2. Acceptance

Step 188 displayed the complete persistence and recovery terms immediately
before the user's response. The user then explicitly confirmed:

```text
Persistenz- und Recovery-Akzeptanz erteilt, weiter
```

This is an explicit contextual reference to both displayed paragraphs, not a
verbatim repetition.

The accepted terms are:

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

## 3. Accepted Risk

During the later bounded active test:

- the owned WebSocket Core may store an Authorization header;
- the owned MQTT Core may store username and password;
- the native Core may load and reuse this active configuration during service
  startup;
- reuse may occur before Account reconciliation;
- after kernel readiness, the Account observes Core health at bounded
  `+15/+30/+60/+90 s` offsets;
- if no healthy Core is observed by the deadline, the existing bounded
  receive-only recovery path may start before the console is reachable.

No credential value may be returned in diagnostics, reports or public
evidence.

## 4. Scope Limits

The acceptance covers at most:

```text
temporary activations:       1
active service restarts:     1
transport direction:         receive-only
post-test cleanup:           mandatory
Core-health deadline:        +90 s after IPS_KERNELSTARTED
```

It remains revocable until Gate E activation begins.

It does not authorize:

- activation by this acceptance alone;
- a service restart by this acceptance alone;
- indefinite active operation or credential retention;
- credential disclosure;
- MQTT publish;
- mower commands;
- a second restart;
- retry experiments after ambiguity;
- replacement or duplication of Core instances.

## 5. Mandatory Cleanup

After the later active restart test, regardless of pass, failure or ambiguity:

```text
EnableMqttShadow:             false
Account ApplyChanges:         exactly once for cleanup
WebSocket Active:             false
Authorization headers:       empty
MQTT username and password:  empty
```

Cleanup and its verification are part of the accepted test.

## 6. Current State

This acceptance changed no installation state. Gate-C evidence remains
authoritative:

```text
installed module:           main@45c7bd50
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
broker connection:          absent
```

## 7. Architecture Decisions

### AD-NAV-667: Accept an unambiguous contextual confirmation

**Decision:** Bind Gate D to the user's explicit reference to the persistence
and recovery terms displayed in the immediately preceding message.

**Reason:** The response names both acceptance dimensions and occurs directly
after the complete bounded terms. The evidence still distinguishes contextual
confirmation from verbatim repetition.

### AD-NAV-668: Bind acceptance to one sequence

**Decision:** Limit this acceptance to one activation, one supervised restart
and mandatory cleanup.

**Reason:** Earlier acceptance was consumed by the completed step-180/181
sequence and cannot authorize this new persistence window.

### AD-NAV-669: Include autonomous bounded recovery

**Decision:** Treat the published `+90 s` fallback behavior as part of the
accepted risk.

**Reason:** During a slow service startup, the transport lifecycle may act
before external control becomes available.

### AD-NAV-670: Keep acceptance independent from mutation

**Decision:** End Gate D without enabling MQTT or changing Symcon.

**Reason:** Acceptance of bounded credential persistence is not authorization
to create that persistence state.

## 8. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Symcon mutations | 0 |
| credential requests | 0 |
| broker connections | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |

## 9. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-credential-persistence-acceptance/
    gate-d-evidence-closure.json
```

The evidence contains no credential or installation identity.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive staging | PASS |
| Gate D renewed persistence and recovery acceptance | PASS |
| Gate E temporary activation | CLOSED |
| Gate F active restart | CLOSED |
| mandatory cleanup | ARMED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 11. Recommended Next Step

Gate E requires separate explicit authorization:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Core-Resume-Health-Observation-Test freigegeben.
```

Gate E permits one activation and a stable active pre-restart baseline. It does
not authorize the subsequent Symcon restart.
