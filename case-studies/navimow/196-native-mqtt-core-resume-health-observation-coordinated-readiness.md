# 196 Native MQTT Core Resume Health Observation Coordinated Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Coordinated read-only refresh and restaging passed; activation
remained separately gated
**Date:** 2026-07-29
**Scope:** Execute the combined read-only window from step 195

## 1. Purpose

Step 195 showed that operator delay had reduced the token horizon below the
2400-second activation threshold. This step combines passive refresh
observation and immediate inactive restaging without collapsing the later
mutation boundary.

## 2. Authorization and Confirmation

The user authorized:

```text
Koordinierte passive Tokenhorizont-Beobachtung mit anschließendem read-only Restaging freigegeben.
```

After the observation, the user confirmed:

> Während der koordinierten Beobachtung habe ich keine manuelle OAuth- oder
> Token-Aktualisierungsaktion ausgeführt.

## 3. Passive Refresh

The observation started with 1017 seconds remaining. Without any manual
authentication action, the public expiry moved forward and produced:

```text
refreshed token horizon: 3548 seconds
required refresh horizon: 3000 seconds
Account:                  Connected
ReauthRequired:           false
RestErrorCount delta:     0
MQTT:                     disabled and credential-free
```

## 4. Immediate Restaging

Two inactive projections 99 seconds apart both passed:

- installed `main@45c7bd50`, clean and valid;
- exact retained Receiver/MQTT/WebSocket chain;
- four canonical QoS-0 subscriptions;
- stable topology and subscription hashes;
- stable connection and Core-resume counters;
- stopped `Disabled` lifecycle;
- empty Core credential fields;
- 14 unchanged variables;
- five unchanged logging contracts and queryable history.

The final readiness projection showed:

```text
remaining token horizon: 3437 seconds
activation threshold:    2400 seconds
activation ready:        true
```

## 5. Architecture Decisions

### AD-NAV-696: Coordinate only read-only gates

**Decision:** Combine passive refresh and staging, then stop before activation.

**Reason:** This preserves the mutation boundary while avoiding unnecessary
loss of token horizon.

### AD-NAV-697: Retain prior acceptance

**Decision:** Reuse the unconsumed acceptance from step 194.

**Reason:** No credential-bearing activation occurred between acceptance and
this readiness window.

### AD-NAV-698: Require complete restaging after refresh

**Decision:** Revalidate topology, lifecycle, variables and archives
immediately after expiry movement.

**Reason:** Refresh success alone does not prove transport compatibility.

## 6. Side-Effect Accounting

| Operation | Count |
|---|---:|
| read-only projections | 5 |
| manual refresh calls | 0 |
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| credential requests | 0 |
| broker connections | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| service restarts | 0 |

## 7. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-coordinated-readiness/
    gate-a-b-evidence-closure.json
```

The public report contains no token value, absolute expiry timestamp,
credential, topic, endpoint, payload, device identity, ObjectID, hostname, IP
address or garden detail.

## 8. Gate Decision

| Gate | Decision |
|---|---|
| coordinated passive refresh | PASS |
| coordinated inactive restaging | PASS |
| renewed acceptance | PASS, unconsumed |
| activation in this step | NOT PERFORMED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |
