# 110 Native MQTT Supervised Connect and Receive

**Case study:** Navimow native IP-Symcon module
**Status:** One-shot transport and cleanup passed; receive acceptance
inconclusive and observation deadline exceeded
**Date:** 2026-07-28
**Scope:** Execute Gate E once, observe receive-only native transport,
disconnect, clean credentials and disable the experiment

## 1. Purpose

This step executes the separately authorized Gate E from step 105 against the
adopted chain proven in step 109.

It covers:

- a fresh authentication, ownership, variable and archive preflight;
- exactly one `Connect MQTT Shadow` invocation;
- one native WSS/MQTT activation attempt;
- bounded read-only Core-status observations;
- no-retry handling;
- exactly one explicit disconnect;
- credential cleanup and compatibility readback;
- final disabling of the experimental MQTT shadow;
- private machine-readable evidence closure.

It does not:

- publish MQTT data;
- send a mower command;
- stimulate the mower through the official app;
- write a public Device variable from MQTT;
- create or delete a productive instance or variable;
- change Archive Control;
- use `MC_ReloadModule()`;
- perform a second connection attempt.

REST remains authoritative for public device state.

## 2. Authorization

The user explicitly authorized Gate E after Gate D closed.

The authorization permitted:

```text
one credential request path
one Connect invocation
one WebSocket activation maximum
read-only observation
mandatory Disconnect and credential cleanup
```

Failure, timeout or ambiguity permitted no retry.

## 3. Preflight

The preflight confirmed:

| Invariant | Result |
|---|---|
| Account connected | PASS |
| reauthentication required | no |
| ownership validation | `ready` |
| WebSocket inactive | PASS |
| authorization headers empty | PASS |
| MQTT username and password empty | PASS |
| MQTT client ID empty before first connection | PASS |
| four exact QoS-0 subscriptions | PASS |
| wildcard absent | PASS |
| 14 established variables | unchanged |
| five archive logging contracts | unchanged |

The private baseline probe initially reported the valid Core chain as absent.
The cause was a probe-only PHP array-union defect: `+=` retained the initialized
`candidatePresent = false` value. Changing the private read-only probe to
`array_merge()` corrected the measurement. Direct topology and ownership
validation had remained valid throughout; no live object was changed by this
correction.

## 4. One Connection Attempt

The bounded action invoked:

```text
ConnectMqttShadow
```

exactly once.

Returned result:

```text
MQTT connection attempt started.
```

Immediate configuration-shape readback proved:

- ownership remained `ready`;
- WebSocket `Active = true`;
- WSS scheme and port contract valid;
- one complete Authorization Bearer header present;
- MQTT username present;
- MQTT password present;
- stable MQTT client ID present;
- no secret value returned through MCP;
- no retry path invoked.

The immediate native Core statuses were still inactive while ApplyChanges was
settling.

## 5. Healthy Transport

Every later read-only observation returned:

```text
native MQTT Client status: 102
native WebSocket Client status: 102
WebSocket Active: true
ownership: ready
```

Five retained observations remained healthy and stable.

This proves:

- successful native WSS activation;
- successful native MQTT transport establishment;
- stable ownership during active configuration;
- no configuration or authentication failure during the observation.

It does not by itself prove that the productive Receiver accepted a message.

## 6. Receive-Evidence Gap

The current module records these private attributes internally:

```text
MqttLifecycleRegistry
MqttStatistics
```

They contain the accepted-message counter and `ShadowActive` lifecycle
evidence required by step 105. The module exposes neither through a bounded
public diagnostic method or variable.

The live environment also provides no supported `IPS_GetAttribute*` function.
The procedure deliberately did not:

- read internal Symcon persistence files;
- install a diagnostic backdoor;
- replace the productive Receiver;
- infer acceptance merely from healthy Core status.

One REST success and Device status timestamp advanced during the active
window, while the REST error counter and command evidence remained unchanged.
This advancement is not accepted as MQTT proof because it can also result from
the normal REST polling schedule.

Receive acceptance is therefore:

```text
inconclusive-diagnostics-unavailable
```

## 7. Deadline Deviation

Planned maximum active observation:

```text
180 seconds
```

Measured connection-to-disconnect interval:

```text
206 seconds
```

The 26-second overrun resulted from preparing and yielding the bounded
multi-sample tool orchestration after the connection had already started.

During the overrun:

- the transport remained healthy;
- no second Connect call occurred;
- no MQTT publish occurred;
- no mower action occurred.

Nevertheless, the procedure did not meet its declared deadline. SAEF treats
this as a gate-conformance failure rather than silently rounding the interval.

## 8. Disconnect and Cleanup

The observation procedure invoked:

```text
DisconnectMqttShadow
```

exactly once.

Returned result:

```text
MQTT transport disconnected.
```

Cleanup readback proved:

| Invariant | Result |
|---|---|
| WebSocket inactive | PASS |
| authorization headers empty | PASS |
| MQTT username empty | PASS |
| MQTT password empty | PASS |
| stable client ID retained | PASS |
| four subscriptions retained | PASS |
| ownership retained and valid | PASS |
| Receiver and Core instances retained | PASS |
| native Core statuses inactive | PASS |

The WSS endpoint and stable client ID remain as non-secret owned transport
configuration. Authorization material and broker username/password were
removed.

## 9. Compatibility Readback

After cleanup:

- Account, Configurator and Device identities were unchanged;
- all 14 variable identities and metadata were unchanged;
- all five archive logging and aggregation contracts were unchanged;
- command evidence was unchanged;
- REST error count was unchanged;
- the Receiver selection and adopted chain were retained.

No variable was deleted, recreated or reparented.

## 10. Final Disable

Following the Gate-E plan, the experimental MQTT shadow was disabled through
one Account ApplyChanges operation.

Final state:

```text
EnableMqttShadow = false
Receiver selection retained
WebSocket inactive
Authorization headers empty
MQTT username and password empty
client ID and subscriptions retained
chain instances retained
```

The disabled validation result is valid with status `disabled`.

## 11. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Connect invocations | 1 |
| Connect retries | 0 |
| WebSocket activation maximum | 1 |
| Disconnect invocations | 1 |
| final Account ApplyChanges | 1 |
| MQTT publish attempts | 0 |
| mower actions | 0 |
| created or deleted instances | 0 |

## 12. Architecture Decisions

### AD-NAV-428: Do not bypass module attributes

**Decision:** Do not read private Symcon persistence to obtain MQTT lifecycle
attributes.

**Reason:** A live test must use supported module and platform contracts.

**Consequence:** Missing bounded diagnostics is classified as an implementation
gap, not worked around.

### AD-NAV-429: Do not infer accepted receive

**Decision:** Healthy Core status and a coincident REST refresh are insufficient
proof of an accepted MQTT message.

**Reason:** Both have plausible causes independent of productive Receiver
acceptance.

**Consequence:** The receive result remains inconclusive.

### AD-NAV-430: Treat the deadline literally

**Decision:** Record the measured 206-second interval as nonconformant with the
180-second plan.

**Reason:** Bounded live procedures lose value when timing deviations are
silently normalized.

**Consequence:** A future harness must establish the deadline before activation
and schedule cleanup from that absolute deadline.

### AD-NAV-431: Disable after the test

**Decision:** Retain the adopted topology and ownership but disable the
experimental MQTT shadow after cleanup.

**Reason:** No active lifecycle should remain enabled while receive acceptance
cannot be observed.

**Consequence:** A later retest requires explicit re-enablement and a new
one-shot connection authorization.

## 13. Private Evidence

Private files:

```text
private/navimow-capture/
  native-mqtt-lifecycle-baseline-probe.php
  native-mqtt-one-shot-connect.php
  native-mqtt-connection-observation.php
  native-mqtt-explicit-disconnect.php
  native-mqtt-disable-after-test.php

private/navimow-capture/output/native-mqtt-lifecycle/
  pre-connect-baseline.json
  connect-attempt.json
  connection-observation.json
  disconnect-cleanup.json
  post-disconnect-compatibility.json
  post-test-disable.json
  gate-e-evidence-closure.json
```

The public report contains no ObjectID, topic, payload, endpoint, credential,
header value, token, hash or private device state.

## 14. Gate Decision

| Gate component | Result |
|---|---|
| authentication and ownership preflight | PASS |
| exactly one connection invocation | PASS |
| healthy native transport | PASS |
| accepted-message evidence | INCONCLUSIVE |
| 180-second deadline | FAIL |
| no retry | PASS |
| explicit disconnect | PASS |
| credential cleanup | PASS |
| variable and archive compatibility | PASS |
| final experimental disable | PASS |

**Gate E execution: CLOSED without retry.**

**Gate E acceptance: NOT PASSED.**

**Gate F restart testing: BLOCKED.**

## 15. Next Step

The next SAEF step is:

```text
111-native-mqtt-bounded-diagnostics-design-and-implementation.md
```

It should add a privacy-safe, read-only Account diagnostic contract containing
only bounded lifecycle state, counters and timestamps. It must not expose
topics, payloads, device identities, endpoints, credentials or raw ownership.

After offline tests, publication, Symcon update and compatibility validation,
a new separately authorized one-shot connection session may retest accepted
receive with an absolute cleanup deadline.
