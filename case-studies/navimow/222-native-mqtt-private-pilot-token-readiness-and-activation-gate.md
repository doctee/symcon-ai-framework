# 222 Native MQTT Private Pilot Token Readiness and Activation Gate

**Case study:** Navimow native IP-Symcon module
**Status:** Read-only compatibility checks passed, but current token horizon is
below 2400 seconds; activation blocked pending passive refresh evidence
**Date:** 2026-07-29
**Scope:** Execute the fresh time-dependent readiness check after persistence
acceptance without activating MQTT

## 1. Purpose

Step 221 recorded explicit persistence and recovery acceptance for one
receive-only private pilot of at most 72 hours.

This step:

1. revalidates the frozen private probe;
2. confirms the initialized harness remains ready;
3. captures a fresh bounded read-only Symcon projection;
4. evaluates the 2400-second activation threshold;
5. repeats the read no sooner than 60 seconds after a marginal result;
6. blocks activation when the threshold is no longer satisfied;
7. preserves the passive OAuth refresh path.

It performs no Symcon mutation, credential request, broker connection, service
restart or mower command.

## 2. Acceptance Binding

The user explicitly stated:

```text
Persistenz- und Recovery-Akzeptanz gemäß SAEF-Schritt 221 erteilt.
```

This binds one future credential-bearing activation to:

```text
module commit: 3d223a9c24e396d4ba55ca40aede6742592fbe8f
policy:        NAV-MQTT-PRIVATE-PILOT-72H
maximum:       72 hours
transport:     receive-only
REST:          authoritative
cleanup:       mandatory after activation
```

The statement does not authorize activation.

## 3. Probe and MCP Contract

The frozen private harness validation passed immediately before live access.
The read-only probe hash remained:

```text
cf07b6ba44e5327eb646923eff220418a430d0843e608b52b28087921fecd3a9
```

Both MCP executions were evaluated across:

```text
transportError
executionError
truncated
captured output
```

Result for each execution:

```text
transportError: null
executionError: null
truncated:      false
projection pass: true
```

## 4. Stable Inactive Contract

Both projections proved:

```text
repository:             clean and valid main@3d223a9c
harness phase:          ready-for-acceptance
MQTT feature:           disabled
lifecycle:              Disabled
next/reconnect attempt: 0/0
MQTT/WebSocket status:  104/104
WebSocket active:       false
Authorization present: false
MQTT username present:  false
MQTT password present:  false
Account connected:      true
ReauthRequired:         false
REST operational:       true
REST authority:         retained
MQTT hint:              unavailable
variables:              14
Archive loggings:       5
```

Identity, archive, command evidence, topology and subscription hashes remained
equal to the inactive baseline from step 220.

## 5. Token-Horizon Result

The first projection observed:

```text
token remaining: 2413 seconds
threshold:       2400 seconds
result:          marginal PASS at capture time
```

Because only 13 seconds of margin remained, this value could not survive the
separate authorization boundary. No activation was attempted.

A second read 70 seconds later observed:

```text
token remaining: 2343 seconds
threshold:       2400 seconds
result:          BLOCKED
```

The threshold behaved monotonically as expected. The earlier result is
historical evidence and cannot be reused for activation.

## 6. Passive Refresh Path

The established non-mutating path is:

1. keep MQTT disabled and credential-free;
2. do not press `Token aktualisieren`;
3. do not perform OAuth login or another manual authentication action;
4. observe no more than once per 60 seconds;
5. wait for the normal scheduled OAuth refresh;
6. require confirmation that no manual authentication action occurred;
7. repeat the complete read-only projection;
8. require at least 2400 seconds before requesting activation authorization.

The passive observation does not retrieve MQTT credentials and does not start
the pilot.

## 7. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-private-pilot-token-readiness/
    evidence-closure.json
```

It contains only commit binding, bounded status, token-horizon integers,
contract hashes, Booleans and operation counts. It contains no credential,
ObjectID, topic, payload, coordinate, hostname or device identity.

## 8. Architecture Decisions

### AD-NAV-802: Reject a marginal threshold across authorization delay

**Decision:** Do not request or perform activation based on a value only 13
seconds above the threshold.

**Reason:** Separate user authorization cannot reliably complete before that
time-dependent precondition expires.

### AD-NAV-803: Prove expiry with bounded cadence

**Decision:** Repeat the read after 70 seconds and classify the current gate
from the newer value.

**Reason:** The operating policy permits at most one read per 60 seconds below
the threshold and forbids reuse of historical readiness.

### AD-NAV-804: Permit only passive refresh

**Decision:** Keep MQTT disabled while waiting for the module's normal OAuth
refresh and require confirmation that no manual authentication action
occurred.

**Reason:** Manual refresh would invalidate the intended passive-rotation
evidence and weaken the later multi-day pilot objective.

## 9. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only projections | 2 |
| Symcon mutations | 0 |
| credential requests | 0 |
| broker connections | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |

## 10. Gate Decision

| Gate | Decision |
|---|---|
| persistence and recovery acceptance | PASS |
| frozen harness validation | PASS |
| exact installed commit | PASS |
| inactive credential-free contract | PASS |
| REST authority | PASS |
| first token observation | MARGINAL PASS |
| current token observation | BLOCKED, 2343 seconds |
| passive refresh evidence | REQUIRED |
| separate activation authorization | NOT REQUESTED |
| MQTT activation | CLOSED |
| pilot clock | NOT STARTED |
| mandatory cleanup | NOT YET ARMED |

## 11. Next Step

Proceed with:

```text
223-native-mqtt-private-pilot-passive-token-readiness.md
```

That step should observe the normal OAuth refresh without manual
authentication, repeat the complete inactive projection and request the
separate activation authorization only if the fresh horizon is at least 2400
seconds.
