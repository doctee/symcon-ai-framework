# 194 Native MQTT Core Resume Health Observation Retry Persistence Acceptance

**Case study:** Navimow native IP-Symcon module
**Status:** Retry Gate C accepted by explicit contextual reference; activation
and restart remain separately gated
**Date:** 2026-07-29
**Scope:** Record renewed retry persistence and recovery acceptance

## 1. Purpose

Step 193 proved the retained native transport inactive, credential-free and
compatible after passive token refresh.

Retry Gate C renews the bounded risk acceptance required before one more
credential-bearing activation and restart sequence. This step records that
acceptance and performs no Symcon operation.

## 2. Acceptance

Step 193 section 12 displayed the complete persistence, bounded recovery and
cleanup terms. The user then explicitly confirmed:

```text
Persistenz- und Recovery-Akzeptanz erteilt
```

This is an explicit contextual reference to the immediately preceding complete
terms, not a verbatim repetition.

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

## 3. Accepted Scope

The acceptance covers at most:

```text
temporary activations:       1
active service restarts:     1
transport direction:         receive-only
post-test cleanup:           mandatory
activation token threshold:  2400 seconds
restart-arm token threshold: 1800 seconds
Core-health deadline:        +90 seconds after IPS_KERNELSTARTED
```

It remains revocable until retry activation begins.

It does not authorize:

- MQTT activation by itself;
- a service restart by itself;
- indefinite active operation or credential retention;
- credential disclosure;
- MQTT publish;
- mower commands;
- an activation or restart retry;
- replacement or duplication of Core instances.

## 4. Mandatory Cleanup

After activation, every later pass, failure, stop or ambiguity requires:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
WebSocket Active:          false
Authorization headers:    empty
MQTT username/password:    empty
```

Cleanup and delayed verification are part of the accepted sequence.

## 5. Current State

This acceptance changed no installation state. Step 193 remains the latest
technical state:

```text
installed module:           main@45c7bd50
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
```

The token horizon is time-dependent. Its 3067-second value from step 193 is
historical evidence and must not be reused as a current activation
precondition.

## 6. Architecture Decisions

### AD-NAV-688: Accept the explicit contextual confirmation

**Decision:** Bind retry Gate C to the user's direct reference to the complete
terms in step 193.

**Reason:** The response names both persistence and recovery and immediately
follows the complete bounded acceptance text.

### AD-NAV-689: Bind acceptance to one credential-bearing sequence

**Decision:** Limit acceptance to one activation, one restart and mandatory
cleanup.

**Reason:** Every activation creates the accepted credential-persistence risk
and consumes its authorization.

### AD-NAV-690: Keep token readiness independent from acceptance

**Decision:** Recheck the live token horizon immediately before activation.

**Reason:** Consent does not freeze time. A prior horizon cannot satisfy a
later mutation gate.

### AD-NAV-691: End the acceptance gate without mutation

**Decision:** Do not activate MQTT in this step.

**Reason:** Risk acceptance and creation of the accepted state remain separate
authorization boundaries.

## 7. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Symcon mutations | 0 |
| credential requests | 0 |
| broker connections | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |

## 8. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-retry-persistence-acceptance/
    gate-c-evidence-closure.json
```

The evidence contains no credential or installation identity.

## 9. Gate Decision

| Gate | Decision |
|---|---|
| retry Gate A passive token refresh | PASS |
| retry Gate B inactive staging | PASS |
| retry Gate C renewed acceptance | PASS |
| retry Gate D activation | CLOSED |
| retry Gate E restart arm | CLOSED |
| retry Gate F restart | CLOSED |
| mandatory cleanup | ARMED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 10. Recommended Next Step

Before requesting activation, perform a fresh read-only token-horizon check.
The activation authorization may be used only when that check proves:

```text
token remaining >= 2400 seconds
MQTT disabled and credential-free
```

Because the token horizon continuously decreases, no value from step 193 may
be assumed current.
