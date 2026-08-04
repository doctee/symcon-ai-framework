# 221 Native MQTT Private Pilot Persistence Acceptance and Activation Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Persistence and recovery acceptance explicitly given; token
readiness and activation remain separately gated
**Date:** 2026-07-29
**Scope:** Bind the 72-hour private-pilot risk terms to the initialized harness
without accessing or changing Symcon

## 1. Purpose

Step 220 passed the inactive preflight and initialized the private observation
harness:

```text
installed commit: 3d223a9c24e396d4ba55ca40aede6742592fbe8f
harness policy:   NAV-MQTT-PRIVATE-PILOT-72H
harness phase:    ready-for-acceptance
inactive samples: 2
baseline spacing: 82 seconds
stop reasons:     none
```

Before credentials may be retrieved and stored in the owned IP-Symcon Core
instances, this step freezes:

1. the exact contextual persistence acceptance;
2. the accepted automatic recovery boundary;
3. the mandatory cleanup contract;
4. the fresh token-readiness requirement;
5. the separate activation authorization.

This step performs no Symcon access or mutation.

## 2. Exact Acceptance Boundary

The required contextual declaration is:

```text
Ich akzeptiere für einen einmaligen überwachten receive-only MQTT-Private-
Pilot von höchstens 72 Stunden auf dem installierten Navimow-Modul
main@3d223a9c, dass Authorization- und MQTT-Zugangsdaten während des
aktivierten Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert
und bei regulären OAuth-Aktualisierungen kontrolliert ersetzt werden.

Ich akzeptiere die bereits implementierten begrenzten automatischen
Verbindungsversuche bei vorübergehenden Transportfehlern. MQTT bleibt
receive-only, REST bleibt maßgeblich, und nach spätestens 72 Stunden oder
bei einem Stop-Kriterium wird MQTT deaktiviert und vollständig bereinigt.
```

The abbreviated commit in this declaration is unambiguously bound to:

```text
3d223a9c24e396d4ba55ca40aede6742592fbe8f
```

The user explicitly confirmed:

```text
Persistenz- und Recovery-Akzeptanz gemäß SAEF-Schritt 221 erteilt.
```

This is an unambiguous contextual acceptance of the complete declaration
immediately presented in this section.

## 3. Accepted Scope After Confirmation

Explicit confirmation will cover at most:

```text
private-pilot runs:          1
maximum duration:            72 hours
earliest evidence closure:   48 hours
minimum natural cycles:      2
target natural cycles:       3
transport direction:         receive-only
credential rotations:        normal OAuth-driven replacements
automatic recovery:          existing bounded transient-error path
post-pilot cleanup:           mandatory
```

The acceptance remains revocable until activation begins.

It does not authorize:

- MQTT activation by itself;
- an IP-Symcon service restart;
- open-ended operation beyond 72 hours;
- manual mower operation solely to manufacture pilot evidence;
- credential disclosure;
- MQTT publishing;
- MQTT mower commands;
- direct MQTT writes to public Device variables;
- replacement, duplication or reparenting of Core instances;
- a second activation after failed baseline establishment.

## 4. Authority and Safety Contract

Throughout any later active pilot:

- REST remains the sole authority for public mower state;
- all supported mower commands remain REST-only and user initiated;
- MQTT acts only as a receive-only hint and reconciliation trigger;
- the existing REST polling path remains active;
- the shadow diagnostic remains identity-free and non-archived;
- MQTT failure cannot suppress REST polling;
- no induced service restart or network outage belongs to this pilot;
- natural manufacturer-controlled mowing schedules remain unchanged.

The observation harness is read-only. It cannot activate MQTT, request a
connection, write a variable, restart Symcon or command the mower.

## 5. Mandatory Cleanup

After a separately authorized activation, every normal completion, hard stop,
failure or ambiguous result requires:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
WebSocket Active:          false
Authorization headers:    empty
MQTT username/password:    empty
```

Cleanup must be verified immediately and again after at least 180 seconds.
The cleanup obligation becomes armed when activation begins and remains armed
until both checks pass.

Failure to establish two equal active baselines at least 65 seconds apart
triggers immediate cleanup. It does not start the pilot clock and does not
permit an automatic activation retry.

## 6. Fresh Readiness Requirement

The token horizon observed in step 220 is historical and continuously
decreases. It cannot authorize a later activation.

After explicit persistence acceptance and immediately before requesting
activation, one fresh bounded read-only projection must prove:

```text
repository:                clean and valid main@3d223a9c
harness phase:             ready-for-acceptance
MQTT feature:              disabled
lifecycle:                 Disabled
next attempt:              0
reconnect attempt:         0
WebSocket:                 inactive
Authorization headers:    empty
MQTT username/password:    empty
Account:                   Connected
ReauthRequired:            false
token remaining:           at least 2400 seconds
REST:                      operational
contracts:                 equal to inactive baseline
MQTT hint:                 unavailable
```

If the token horizon is below 2400 seconds:

1. do not activate MQTT;
2. do not press `Token aktualisieren`;
3. do not initiate OAuth;
4. observe the normal passive refresh path no more than once per 60 seconds;
5. require user confirmation that no manual authentication action occurred;
6. repeat the complete read-only readiness projection.

## 7. Separate Activation Authorization

Passing persistence acceptance and token readiness still does not activate
MQTT.

The required separate authorization is:

```text
Aktivierung des receive-only MQTT-Transports für den überwachten
72-Stunden-Private-Pilot freigegeben.
```

Only after that authorization may the activation step execute:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
```

The harness accepts activation only after two stable active projections at
least 65 seconds apart. The second active baseline defines the fixed 48-hour
earliest-completion point and 72-hour deadline.

## 8. Current Technical State

No live access was required for this planning and acceptance gate. The latest
proven state remains step 220:

```text
module:                 main@3d223a9c
repository:             clean and valid
MQTT:                   disabled
WebSocket:              inactive
MQTT credentials:       absent
REST:                   operational and authoritative
variables:              14 retained
Archive loggings:       5 retained
harness:                ready-for-acceptance
pilot clock:            not started
```

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Symcon read operations | 0 |
| Symcon mutations | 0 |
| credential requests | 0 |
| broker connections | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |

## 10. Architecture Decisions

### AD-NAV-798: Require explicit contextual persistence acceptance

**Decision:** Do not treat a general instruction to continue as acceptance of
credential persistence.

**Reason:** Continuing the SAEF workflow and accepting a bounded
credential-bearing runtime state are materially different decisions.

### AD-NAV-799: Bind acceptance to commit, policy and one run

**Decision:** Bind the declaration to `main@3d223a9c`, policy
`NAV-MQTT-PRIVATE-PILOT-72H` and one activation sequence.

**Reason:** A later module revision or a second run could have different
runtime and recovery characteristics.

### AD-NAV-800: Recheck token readiness after acceptance

**Decision:** Treat every earlier token horizon as historical evidence.

**Reason:** The horizon is time-dependent and can fall below the safe
activation threshold while documentation is being completed.

### AD-NAV-801: Arm cleanup only on activation

**Decision:** Keep cleanup unarmed while the installation remains disabled and
credential-free, then make it mandatory when activation begins.

**Reason:** No credential-bearing transport exists before activation, while
every later outcome must close that state deterministically.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| operating policy | PASS |
| observation harness | PASS |
| inactive preflight | PASS |
| harness phase | `ready-for-acceptance` |
| contextual terms frozen | PASS |
| contextual persistence acceptance | PASS |
| fresh token readiness | CLOSED |
| separate activation authorization | NOT GIVEN |
| MQTT activation | CLOSED |
| pilot clock | NOT STARTED |
| mandatory cleanup | NOT YET ARMED |
| REST authority | RETAINED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

## 12. Next Step

The acceptance gate is complete. Proceed with:

```text
222-native-mqtt-private-pilot-token-readiness-and-activation-gate.md
```

That step must perform a fresh read-only token and inactive-state check, stop
before activation and request the separate authorization from section 7 only
when the time-dependent threshold remains satisfied.
