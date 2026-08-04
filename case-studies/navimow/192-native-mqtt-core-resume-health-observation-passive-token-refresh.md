# 192 Native MQTT Core Resume Health Observation Passive Token Refresh

**Case study:** Navimow native IP-Symcon module
**Status:** Retry Gate A passed; passive scheduled token refresh observed with
MQTT disabled
**Date:** 2026-07-29
**Scope:** Execute only passive token-refresh Gate A from step 191

## 1. Purpose

Step 190 stopped the first activation before restart because its second active
baseline retained less than the frozen 1200-second token horizon. Step 191
requires a normal scheduled refresh before any retry.

This step proves:

- token expiry moved forward without a manual authentication action;
- the refreshed token provides more than 3000 seconds of horizon;
- Account authentication and REST continuity remain healthy;
- MQTT remains disabled, inactive and credential-free;
- no installation mutation occurred.

## 2. Authorization

The user explicitly authorized:

```text
Passive Token-Refresh-Beobachtung für den Core-Resume-Health-Observation-Retry freigegeben.
```

This authorized bounded read-only projections only.

It did not authorize:

- `RefreshAuthentication()`;
- OAuth authorization;
- Account reset or `ApplyChanges()`;
- manual status refresh;
- MQTT activation or credential retrieval;
- a service restart;
- MQTT publish or mower commands.

## 3. Observation Method

Four bounded projections read:

- the public `TokenExpiresAt` value and variable change timestamp;
- `ConnectionState` and `ReauthRequired`;
- `LastRestSuccess` and `RestErrorCount`;
- only credential-presence Booleans from the retained Core chain.

Polling occurred no more frequently than once per 60 seconds. No temporary
Symcon object was created.

No access token, refresh token or credential value was read or returned.

## 4. Passive Refresh Evidence

The first projection showed:

```text
remaining token horizon: 606 seconds
Account:                 Connected
reauthentication:        false
MQTT:                    disabled
Core credentials:        empty
```

Immediately before the expected refresh margin:

```text
remaining token horizon: 302 seconds
```

The next projection showed:

```text
remaining token horizon: 3513 seconds
required threshold:      3000 seconds
TokenExpiresAt:           moved forward
variable change time:     moved forward
```

The expiry moved forward by 3300 seconds. Absolute expiry timestamps remain
private evidence.

## 5. Operator Confirmation

After the technical observation, the user explicitly confirmed:

> Während der Beobachtung habe ich weder „Token aktualisieren“, eine
> OAuth-Anmeldung noch eine andere manuelle Authentifizierungsaktion in Symcon
> ausgeführt.

This excludes an operator-triggered authentication action as the cause of the
observed expiry movement.

## 6. Authentication and REST Continuity

Across the observation:

```text
ConnectionState:       Connected
ReauthRequired:        false
LastRestSuccess:       moved forward
RestErrorCount start:  9
RestErrorCount end:    9
error-count delta:     0
```

The existing error count of 9 predates this observation. Gate A treats it as a
baseline rather than incorrectly reporting zero errors. No new REST error was
recorded.

## 7. MQTT Safety State

Every projection proved:

```text
EnableMqttShadow:             false
WebSocket Active:             false
Authorization headers:       empty
MQTT username and password:  empty
```

No MQTT credential request, broker connection, publication or mower command
occurred.

## 8. Architecture Decisions

### AD-NAV-680: Attribute refresh through state plus confirmation

**Decision:** Accept the expiry movement as passive scheduled refresh only
with continuous Account health and explicit operator confirmation.

**Reason:** Variable movement alone cannot exclude a manual action outside the
read-only probe.

### AD-NAV-681: Preserve the pre-existing REST error baseline

**Decision:** Require an error-count delta of zero rather than an absolute
counter value of zero.

**Reason:** Historical errors do not invalidate the current observation; new
errors would.

### AD-NAV-682: Require a 3000-second refreshed horizon

**Decision:** Pass only when the new remaining horizon is at least 3000
seconds.

**Reason:** The retry plan needs 2400 seconds before activation plus a
practical staging and authorization window.

### AD-NAV-683: Keep refresh observation independent from MQTT

**Decision:** Observe authentication with the native transport disabled and
credential-free.

**Reason:** Token lifecycle evidence must not create the credential-persistence
risk reserved for later gates.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| read-only projections | 4 |
| `RefreshAuthentication()` | 0 |
| OAuth actions | 0 |
| `ApplyChanges()` | 0 |
| MQTT enable operations | 0 |
| credential requests | 0 |
| broker connections | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| service restarts | 0 |
| created or deleted objects | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 10. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-passive-token-refresh/
    gate-a-evidence-closure.json
```

The public report contains no token value, absolute expiry timestamp,
credential, topic, endpoint, payload, device identity, ObjectID, hostname, IP
address or garden detail.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| retry Gate A passive token refresh | PASS |
| refreshed horizon `>= 3000 s` | PASS |
| retry Gate B inactive staging | CLOSED |
| retry Gate C renewed acceptance | NOT GIVEN |
| retry Gate D activation | CLOSED |
| retry Gate E restart arm | CLOSED |
| retry Gate F restart | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 12. Recommended Next Step

After separate authorization, execute only retry Gate B:

```text
Erneutes inaktives Staging für den Core-Resume-Health-Observation-Retry freigegeben.
```

Gate B is read-only and leaves MQTT disabled and credential-free.
