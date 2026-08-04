# 161 Native MQTT Kernel Start Reconciliation Credential Persistence Acceptance

**Case study:** Navimow native IP-Symcon module
**Status:** Gate E accepted exactly; activation and active restart remain
separately gated
**Date:** 2026-07-28
**Scope:** Record the bounded credential-persistence acceptance from step 156

## 1. Purpose

Step 160 proved the kernel-start hook with MQTT disabled and credential-free.
Gate E is the explicit risk acceptance required before any active native
transport restart test.

This step records that acceptance and its limits. It performs no Symcon
operation.

## 2. Exact Acceptance

The user supplied the exact required text:

```text
Ich akzeptiere für den einmaligen beaufsichtigten Neustarttest, dass
Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT deaktiviert und bereinigt.
```

The acceptance matches Gate E from step 156 without qualification or
extension.

## 3. Accepted Risk

During the later bounded active test:

- the owned WebSocket Core stores an Authorization header;
- the owned MQTT Core stores username and password;
- the native Core can load and reuse this active configuration during service
  startup;
- this reuse can happen before the Account handles `IPS_KERNELSTARTED`;
- the Account then observes and adopts a healthy owned resumed transport.

No credential value may be returned in diagnostics, reports or public
evidence.

## 4. Scope Limits

The acceptance permits at most:

```text
temporary activations:        1
active service restarts:      1
transport direction:          receive-only
post-test cleanup:            mandatory
```

It remains revocable until Gate F activation begins.

It does not permit:

- activation by this acceptance alone;
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

Cleanup and its verification are part of the accepted test, not an optional
follow-up.

## 6. Current State

This acceptance changed no installation state:

```text
MQTT feature:               disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
broker connection:          absent
```

## 7. Architecture Closure

### AD-NAV-550: Separate risk acceptance from activation

**Decision:** Record credential-persistence acceptance before, but independently
from, the activation mutation.

**Reason:** Consent to a bounded risk does not itself authorize changing the
installation.

### AD-NAV-551: Bind acceptance to mandatory cleanup

**Decision:** Gate E is valid only with cleanup after pass, failure or
ambiguity.

**Reason:** The accepted persistence window is temporary and must not silently
become an indefinite pilot state.

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
  native-mqtt-kernel-start-reconciliation-credential-persistence-acceptance/
    gate-e-evidence-closure.json
```

The evidence contains no credential or installation identity.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate D disabled kernel-hook restart | PASS |
| Gate E exact acceptance | PASS |
| Gate F temporary activation | CLOSED |
| Gate G active Core-resume restart | CLOSED |
| Gate H mandatory cleanup | ARMED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

The next independently authorized action is Gate F from step 156:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Restarttest freigegeben.
```
