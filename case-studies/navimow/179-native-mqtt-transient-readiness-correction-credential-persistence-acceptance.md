# 179 Native MQTT Transient Readiness Correction Credential Persistence Acceptance

**Case study:** Navimow native IP-Symcon module
**Status:** Gate D accepted through explicit verbatim confirmation; activation
and restart remain separately gated
**Date:** 2026-07-29
**Scope:** Record the renewed bounded persistence acceptance from step 175

## 1. Purpose

Step 178 proved the durable-barrier candidate's retained native topology while
MQTT was disabled, inactive and credential-free.

Gate D renews the explicit risk acceptance required before one more active
native transport restart test. This step records that acceptance and its
limits. It performs no Symcon operation.

## 2. Acceptance

The user explicitly repeated and accepted the complete required text:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.
```

This is a verbatim confirmation rather than an acceptance inferred from a
short contextual response.

## 3. Accepted Risk

During the later bounded active test:

- the owned WebSocket Core may store an Authorization header;
- the owned MQTT Core may store username and password;
- the native Core may load and reuse this active configuration during service
  startup;
- reuse may occur before Account reconciliation;
- the Account must classify the resumed Core only after the kernel-ready
  barrier.

No credential value may be returned in diagnostics, reports or public
evidence.

## 4. Scope Limits

The acceptance covers at most:

```text
temporary activations:       1
active service restarts:     1
transport direction:         receive-only
post-test cleanup:           mandatory
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

This acceptance changed no installation state. The immediately preceding
Gate-C evidence remains authoritative:

```text
installed module:           main@7d141f76
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
broker connection:          absent
```

## 7. Architecture Decisions

### AD-NAV-624: Record verbatim acceptance

**Decision:** Bind Gate D to the user's complete repeated risk text.

**Reason:** The accepted behavior, duration and cleanup obligation are
explicit without contextual inference.

### AD-NAV-625: Renew acceptance for one corrected test only

**Decision:** Bind this acceptance to one activation/restart sequence and its
mandatory cleanup.

**Reason:** The prior acceptance was consumed by the completed step-172 test
and cannot authorize another persistence window.

### AD-NAV-626: Keep acceptance independent from mutation

**Decision:** End Gate D without enabling MQTT or changing Symcon.

**Reason:** Consent to bounded credential persistence is not authorization to
create that persistence state.

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
  native-mqtt-transient-readiness-correction-credential-persistence-acceptance/
    gate-d-evidence-closure.json
```

The evidence contains no credential or installation identity.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive staging | PASS |
| Gate D renewed persistence acceptance | PASS |
| Gate E temporary activation | CLOSED |
| Gate F corrected active restart | CLOSED |
| Gate G mandatory cleanup | ARMED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 11. Recommended Next Step

Gate E now requires separate explicit authorization:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Transient-Readiness-Restarttest freigegeben.
```

Gate E permits one activation and an active pre-restart baseline. It does not
authorize the subsequent Symcon restart.
