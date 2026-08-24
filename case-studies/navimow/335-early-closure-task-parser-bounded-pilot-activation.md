# 335 Early Closure Task Parser Bounded Pilot Activation

**Case study:** Navimow native IP-Symcon module

**Status:** Active observation; operational phases passed

**Date:** 2026-08-20

## 1. Authorization

The operator confirmed that no manual OAuth, login or token-refresh action had
occurred since the 12:47:13 CEST readiness check. Temporary Authorization and
MQTT credential persistence in the owned IP-Symcon Core instances was accepted
for exactly one bounded receive-only MQTT and position pilot with mandatory
cleanup.

This authorization does not include a restart, OAuth action or mower command.

## 2. Fresh Preflight

Immediately before mutation, the bounded probe rechecked every activation
precondition. The installed standalone commit was `6f8a6a9e`, REST was
operational, the previous session was consistently closed, both features and
Core instances were disabled and no Core credential was present.

```text
token remaining:     3281 seconds
minimum horizon:     1200 seconds
session before:      7
preconditions:       PASS
```

## 3. Single Activation

Exactly one restart-free activation set MQTT shadow and position diagnostics to
enabled and invoked Account `IPS_ApplyChanges()` once. There was no activation
retry and no cleanup fallback was required.

The immediate result was the accepted asynchronous state
`ReconnectScheduled`. This was observed, not retried.

## 4. Phase 1

The first delayed read-only probe established:

```text
lifecycle:           ShadowActive
session:             8, active
incident count:      0
automatic closure:   armed
MQTT / WebSocket:    102 / 102
position diagnostic: available
REST:                operational
```

Authorization and MQTT credentials are present only in the owned Core
instances for the active pilot. The reduced evidence contains no credential
value, ObjectID, topic, device identifier, coordinate or raw payload.

## 5. Current Gate State

| Gate | Status |
|---|---|
| fresh L2 readiness | PASS |
| exactly one activation | PASS |
| asynchronous Phase 1 | PASS |
| delayed Phase 2 operational stability | PASS |
| natural task-transition evidence | PENDING |
| mandatory closure and cleanup | PENDING |

The pilot may end before 72 hours once sufficient natural task and area
transitions are captured. Seventy-two hours remains the hard maximum.

## 6. Phase 2 Evidence Separation

After more than two minutes, `ShadowActive`, session 8, zero session incidents,
Core status 102/102, position availability and REST continuity were reproduced.
No new position sample or complete task transition had arrived yet.

The first combined assertion incorrectly required at least one new position
sample to classify the entire Phase 2 as passing. It made no mutation. A
corrected read-only probe separated the two independent outcomes:

```text
operational stability:       PASS
natural task evidence ready: false
```

The pilot therefore continues normally. Missing natural evidence at this early
checkpoint is not a transport failure and must not trigger another activation.
