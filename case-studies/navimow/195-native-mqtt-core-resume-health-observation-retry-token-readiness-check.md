# 195 Native MQTT Core Resume Health Observation Retry Token Readiness Check

**Case study:** Navimow native IP-Symcon module
**Status:** Read-only precheck passed; retry activation correctly blocked by
insufficient token horizon
**Date:** 2026-07-29
**Scope:** Recheck the time-dependent Gate-D activation threshold

## 1. Purpose

Step 194 recorded renewed persistence and recovery acceptance but explicitly
prohibited reuse of the historical token horizon from step 193.

This step performs one fresh read-only readiness projection immediately before
any activation authorization.

## 2. Authorization Boundary

The user's contextual continuation authorized the fresh read-only check.

It did not authorize:

- MQTT activation;
- `ApplyChanges()`;
- credential retrieval;
- a broker connection;
- a service restart;
- MQTT publish or mower commands.

## 3. Result

The bounded MCP projection reported:

```text
transport success:      true
transportError:         null
executionError:         null
truncated:              false
read-only contract:     PASS
remaining token:        1220 seconds
activation threshold:   2400 seconds
activation ready:       false
```

Authentication and transport safety state:

```text
Account:                    Connected
ReauthRequired:             false
RestErrorCount:             9, unchanged
MQTT feature:               disabled
configuration status:       disabled
lifecycle:                  Disabled
next attempt:               0
WebSocket:                  inactive
Authorization headers:     empty
MQTT username and password: empty
```

## 4. Decision

```text
retry activation:      BLOCKED
activation attempted:  no
acceptance consumed:   no
restart attempted:     no
```

This is the expected operation of the threshold introduced by step 191. It is
not an authentication or module failure.

At capture time, normal scheduled refresh was expected after the remaining
horizon reached approximately 300 seconds, roughly 15 minutes later. This is
an estimate, not authorization to wait, refresh or activate.

## 5. Coordinated Readiness Window

Repeating refresh observation, staging, acceptance and activation as isolated
human-paced steps consumes too much of a one-hour token lifetime.

The next read-only window should therefore combine:

1. passive observation until `TokenExpiresAt` moves forward;
2. confirmation of a new horizon of at least 3000 seconds;
3. immediate two-projection inactive staging over at least 65 seconds;
4. complete compatibility verification;
5. immediate activation-readiness projection;
6. stop before mutation and request the separate activation authorization.

The acceptance from step 194 remains valid because no credential-bearing
activation occurred.

The combined window remains read-only and performs no authentication action.

## 6. Architecture Decisions

### AD-NAV-692: Enforce current rather than historical time

**Decision:** Block activation at 1220 seconds despite prior passing token
evidence.

**Reason:** Token readiness is a live time-dependent precondition.

### AD-NAV-693: Preserve unconsumed acceptance

**Decision:** Keep step-194 acceptance available for the retry.

**Reason:** No credential was written and no accepted persistence state was
created.

### AD-NAV-694: Coordinate future read-only gates

**Decision:** Combine passive refresh observation and inactive restaging into
one bounded read-only window.

**Reason:** Human delay between individually safe gates can consume the token
horizon before activation.

### AD-NAV-695: Keep activation separately authorized

**Decision:** End the coordinated window before setting
`EnableMqttShadow=true`.

**Reason:** Operational efficiency must not collapse the mutation boundary.

## 7. Side-Effect Accounting

| Operation | Count |
|---|---:|
| read-only projections | 1 |
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| credential requests | 0 |
| broker connections | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| service restarts | 0 |

## 8. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-retry-token-readiness/
    gate-d-precheck.json
```

The public report contains no token value, absolute expiry timestamp,
credential, topic, endpoint, payload, device identity, ObjectID, hostname, IP
address or garden detail.

## 9. Gate Decision

| Gate | Decision |
|---|---|
| retry Gate A passive token refresh | PASS, historical |
| retry Gate B inactive staging | PASS, historical |
| retry Gate C renewed acceptance | PASS, unconsumed |
| retry Gate D current token precheck | BLOCKED |
| retry Gate D activation | CLOSED |
| retry Gate F restart | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 10. Recommended Next Step

Authorize one combined read-only observation window:

```text
Koordinierte passive Tokenhorizont-Beobachtung mit anschließendem read-only Restaging freigegeben.
```

The window performs no manual refresh and stops before MQTT activation.
