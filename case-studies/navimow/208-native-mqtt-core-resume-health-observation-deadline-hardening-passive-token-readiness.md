# Native MQTT Core-Resume Deadline Hardening Passive Token Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Gate F passed; passive scheduled refresh and activation horizon
proved while MQTT remained disabled
**Date:** 2026-07-29
**Scope:** Execute only passive read-only token-readiness Gate F from step 201

## 1. Purpose

Step 207 recorded renewed persistence, bounded recovery and cleanup acceptance
without accessing Symcon.

Gate F verifies the time-dependent authentication precondition before any
temporary activation. The first projection was below the 2400-second
activation threshold, so the frozen plan required a bounded passive refresh
observation rather than a manual refresh.

## 2. Authorization

The user explicitly authorized:

```text
Passiver read-only Token-Readiness-Prüflauf für Gate F freigegeben.
```

This authorized bounded read-only projections only. It did not authorize:

- `RefreshAuthentication()` or an OAuth action;
- a manual status refresh;
- `ApplyChanges()` or any property mutation;
- MQTT credential retrieval, activation or broker communication;
- a service restart;
- MQTT publish or a mower command.

## 3. Probe Boundary

The private bounded probe was:

```text
private/navimow-capture/
  native-mqtt-deadline-hardening-token-readiness-readonly.php
```

Source SHA-256:

```text
db7667138170aeda55f6bd16bc15007c581950b8a95fd890141fe8527b7bdb6d
```

The probe returns only remaining seconds, status values, counters and
credential-presence Booleans. It returns no token, credential, ObjectID,
topic, endpoint or installation identity.

## 4. Initial Readiness Decision

The first bounded execution at `2026-07-29T12:13:22Z` reported:

```text
remaining token horizon: 1015 seconds
activation threshold:    2400 seconds
ConnectionState:         Connected
ReauthRequired:          false
REST:                    operational
MQTT:                    disabled and credential-free
```

The activation threshold initially failed. No activation was attempted and
Gate-E acceptance was not consumed.

## 5. Passive Refresh Observation

Twelve projections were captured over 742 seconds, never more frequently than
once per 60 seconds.

The horizon decreased normally:

```text
1015 -> 948 -> 871 -> 804 -> 735 -> 667
 602 -> 536 -> 471 -> 404 -> 338 seconds
```

The final projection at `2026-07-29T12:25:44Z` showed the passive forward
movement:

```text
refreshed remaining horizon: 3574 seconds
refresh threshold:           3000 seconds
activation threshold:        2400 seconds
refresh threshold pass:      true
activation threshold pass:   true
```

The absolute expiry timestamps remain private evidence.

## 6. Continuous Safety Contracts

Every projection proved:

```text
ConnectionState:                 Connected
ReauthRequired:                  false
REST:                            operational
RestErrorCount:                  9, unchanged
EnableMqttShadow:                false
lifecycle:                       Disabled
next attempt:                    0
WebSocket Active:                false
Authorization headers:          empty
MQTT username and password:      empty
Account/Receiver pairing:        exact
```

`LastRestSuccess` and `LastStatusUpdate` both advanced during the observation.
The historical REST error count did not increase.

## 7. Operator Confirmation

After the technical observation, the user explicitly confirmed:

> Während der Beobachtung habe ich weder „Token aktualisieren“, eine
> OAuth-Anmeldung noch eine andere manuelle Authentifizierungsaktion in Symcon
> ausgeführt.

Together with continuous authentication and REST health, this attributes the
expiry movement to the normal passive Account lifecycle.

## 8. Architecture Decisions

### AD-NAV-735: Follow the passive path after the initial threshold miss

**Decision:** Observe the scheduled refresh instead of invoking any refresh
method.

**Reason:** Gate F prohibits changing authentication state merely to satisfy a
time-dependent readiness condition.

### AD-NAV-736: Stop at the first qualifying forward movement

**Decision:** End polling when the new horizon reached 3574 seconds.

**Reason:** This exceeds the 3000-second passive-refresh requirement and the
2400-second activation threshold; further polling would add no evidence.

### AD-NAV-737: Require state evidence plus operator confirmation

**Decision:** Close Gate F only after continuous technical evidence and the
explicit no-manual-action confirmation.

**Reason:** Expiry movement alone cannot distinguish scheduled behavior from
an operator action outside the probe.

### AD-NAV-738: Treat the final horizon as time-dependent

**Decision:** Require a fresh threshold check immediately before any later
activation mutation.

**Reason:** The 3574-second observation is historical evidence and decreases
continuously.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| read-only projections | 12 |
| `RefreshAuthentication()` | 0 |
| OAuth actions by the probe | 0 |
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| credential requests | 0 |
| broker connections | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |

All MCP results had successful transport, no PHP execution error and no
truncation.

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-deadline-hardening-passive-token-readiness/
    gate-f-evidence-closure.json
```

The public report contains no absolute token expiry, token value, credential,
topic, endpoint, payload, device identity, ObjectID, hostname, IP address or
garden detail.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| Gate B metadata conformance | PASS |
| Gate C disabled Symcon update | PASS |
| Gate D inactive staging | PASS |
| Gate E renewed acceptance | PASS |
| Gate F passive token readiness | PASS |
| Gate G temporary activation | CLOSED |
| active baselines/restart arm | CLOSED |
| Gate H external restart | CLOSED |
| Gate I mandatory cleanup | ARMED ONLY AFTER ACTIVATION |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

**Gate F is complete.**

## 12. Recommended Next Step

Gate G requires separate authorization:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den 180-Sekunden-Core-Resume-Test freigegeben.
```

Before the one activation mutation, its immediate preflight must repeat the
current token threshold and every inactive safety condition. The Gate-F
horizon must not be assumed current.
