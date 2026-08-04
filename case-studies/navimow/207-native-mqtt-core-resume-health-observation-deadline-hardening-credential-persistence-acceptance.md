# Native MQTT Core-Resume Deadline Hardening Credential Persistence Acceptance

**Case study:** Navimow native IP-Symcon module
**Status:** Gate E passed by renewed verbatim acceptance; passive readiness,
activation and restart remain separately gated
**Date:** 2026-07-29
**Scope:** Record renewed persistence, recovery and cleanup acceptance

## 1. Purpose

Step 206 proved the retained native transport inactive, credential-free and
compatible after installation of the six-point Core-resume deadline
hardening.

Gate E renews the bounded risk acceptance required before one further
credential-bearing activation and restart sequence. This step records that
acceptance and performs no Symcon operation.

## 2. Verbatim Acceptance

The user explicitly accepted:

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

This matches the complete Gate-E terms from step 201. The omitted blank line
between the cleanup and recovery paragraphs does not alter their meaning.

## 3. Accepted Scope

The acceptance covers at most:

```text
temporary activations:       1
active service restarts:     1
transport direction:         receive-only
post-test cleanup:           mandatory
activation token threshold:  2400 seconds
restart-arm token threshold: 1800 seconds
Core-health deadline:        +180 seconds after IPS_KERNELSTARTED
```

It remains revocable until temporary activation begins.

It does not authorize:

- MQTT activation by itself;
- a service restart by itself;
- indefinite active operation or credential retention;
- credential disclosure;
- MQTT publish;
- mower commands;
- an activation or restart retry;
- replacement or duplication of Core instances.

## 4. Mandatory Cleanup Contract

If the separately gated activation occurs, every later pass, failure, stop or
ambiguity requires:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
WebSocket Active:          false
Authorization headers:    empty
MQTT username/password:    empty
```

Immediate and delayed cleanup verification remain part of the accepted
sequence. The operational cleanup gate becomes armed only after activation.

## 5. Current State

This acceptance changed no installation state. Step 206 remains the latest
technical state:

```text
installed module:           main@8fdab84b
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
```

Token readiness is time-dependent. Step 206 proved only that the access token
was usable; it did not freeze or establish the minimum 2400-second activation
horizon.

## 6. Architecture Decisions

### AD-NAV-731: Bind Gate E to the verbatim acceptance

**Decision:** Record the user's complete persistence, recovery and cleanup
terms as the Gate-E decision.

**Reason:** The response reproduces every material condition from step 201,
including the extended `+180 s` deadline and both side-effect prohibitions.

### AD-NAV-732: Limit acceptance to one credential-bearing sequence

**Decision:** Limit the accepted risk to one activation, one externally
authorized restart and mandatory cleanup.

**Reason:** Every activation creates credential persistence in the retained
Core chain and consumes this bounded authorization when it begins.

### AD-NAV-733: Keep passive readiness independent

**Decision:** Require a fresh read-only token-horizon observation before
requesting activation.

**Reason:** Consent cannot establish a time-dependent authentication
precondition.

### AD-NAV-734: End Gate E without mutation

**Decision:** Do not activate MQTT or restart Symcon in this step.

**Reason:** Acceptance, activation and the external restart are separate
authorization boundaries in the frozen plan.

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
  native-mqtt-core-resume-deadline-hardening-persistence-acceptance/
    gate-e-evidence-closure.json
```

The evidence contains no credential or installation identity.

## 9. Gate Decision

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| Gate B metadata conformance | PASS |
| Gate C disabled Symcon update | PASS |
| Gate D inactive staging | PASS |
| Gate E renewed acceptance | PASS |
| Gate F passive token readiness | CLOSED |
| Gate G temporary activation | CLOSED |
| active baselines/restart arm | CLOSED |
| Gate H external restart | CLOSED |
| Gate I mandatory cleanup | ARMED ONLY AFTER ACTIVATION |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

**Gate E is complete without live-system access.**

## 10. Recommended Next Step

Gate F is a read-only passive token-readiness check. It may not refresh a
token manually, apply configuration, activate MQTT or contact the broker.

Required pass state:

```text
token remaining >= 2400 seconds
ConnectionState = Connected
ReauthRequired = false
normal REST continuity
MQTT disabled and credential-free
```

If the horizon is below 2400 seconds, the frozen plan permits only the
separately observed passive refresh path, polled no more than once per 60
seconds, followed by confirmation that no manual authentication action
occurred.
