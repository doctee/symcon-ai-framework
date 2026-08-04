# 115 Native MQTT Diagnostics One-Shot Retest Report

**Case study:** Navimow native IP-Symcon module
**Status:** Receive acceptance inconclusive; transport, deadline, cleanup and
compatibility passed
**Date:** 2026-07-28
**Scope:** Execute the separately authorized Gate-C one-shot receive retest
with bounded diagnostics and automatic cleanup

## 1. Purpose

This step executes Gate C from steps 112 and 114 after explicit authorization:

```text
Ein einmaliger MQTT-Diagnose-Retest mit automatischem Cleanup ist freigegeben.
```

The run permits exactly one:

- experimental MQTT enable operation;
- Connect invocation;
- bounded receive-only observation session;
- Disconnect invocation;
- final disable operation.

It permits no retry, MQTT publish, mower command, instance creation or deletion,
archive mutation, restart or `MC_ReloadModule()` call.

## 2. Harness Correction

The previous Gate-E Connect probe expected an empty MQTT client-ID slot before
connection. Step 110 correctly retained the stable local client ID during
cleanup.

Reusing that old assertion would have rejected the valid current baseline.
Before activation, the private harness was therefore replaced with a
Gate-C-specific contract that requires:

```text
client ID retained
WebSocket inactive
authorization headers empty
MQTT username empty
MQTT password empty
four exact QoS-0 subscriptions
wildcards absent
```

All enable, Connect, observation, Disconnect, disable and emergency-cleanup
sources were complete, syntax-checked and deterministically hashed before
activation. They were not edited during the live session.

The reviewed sources contained exactly one Connect call and no publish, mower,
instance create/delete or module-reload path.

## 3. Enable Preflight

The immediate preflight confirmed:

- Account connected and token usable;
- no reauthentication requirement;
- MQTT shadow disabled;
- diagnostics status `disabled`;
- retained Receiver selection and ownership;
- exact Receiver-to-MQTT-to-WebSocket topology;
- WebSocket inactive;
- authorization and MQTT credential slots empty;
- stable client ID retained;
- four exact QoS-0 subscriptions retained;
- no wildcard;
- 14 variable identities and metadata captured;
- five archive contracts captured;
- command evidence captured.

One Account ApplyChanges enabled the experimental shadow.

Post-enable validation required and observed:

```text
featureEnabled:       true
configurationStatus:  ready
lifecycle state:      Ready
ownership:            ready
WebSocket:            inactive
credential slots:     empty
```

No broker connection occurred during enable.

## 4. Absolute Deadline

All timing boundaries were established before Connect:

```text
hard active deadline: 180 seconds
observation cutoff:    165 seconds
cleanup reserve:        15 seconds
maximum poll interval:   5 seconds
```

The harness stopped observations five seconds before the formal cutoff to
protect the cleanup reserve.

Measured timing:

```text
last observation: 159815 ms
cleanup start:     160005 ms
cleanup finish:    160531 ms
```

Therefore:

```text
cleanup started before cutoff:       PASS
cleanup finished before hard limit:  PASS
deadline conformance:                PASS
```

## 5. One-Shot Connect

Exactly one invocation called:

```text
NAVAC_ConnectMqttShadow
```

The call returned:

```text
MQTT connection attempt started.
```

The diagnostic connection-attempt counter increased exactly by one.

Immediate readback proved:

- ownership remained `ready`;
- WebSocket activation requested once;
- WSS and port contract valid;
- one complete Authorization header present;
- MQTT username and password present;
- stable client ID retained;
- no retry path invoked.

## 6. Transport Observation

The harness captured 32 read-only observations.

The first observation occurred at 532 ms while both Core instances were still
settling. Subsequent observations reached and retained:

```text
native MQTT Client status:      102
native WebSocket Client status: 102
WebSocket active:               true
ownership:                      ready
```

Both Core transports remained healthy through the final observation.

No transport or diagnostic error occurred.

## 7. Receive Result

The complete observation window retained:

```text
received delta:  0
accepted delta:  0
rejected delta:  0
error delta:     0
last result:     none
lifecycle state: Connecting
```

The command-evidence hash remained unchanged in every observation.

Gate-C acceptance required:

```text
received delta >= 1
accepted delta >= 1
lastReceivedAt inside the session
lifecycle state = ShadowActive
lastResult = accepted
both Core statuses = 102
```

No observation satisfied that contract. Healthy Core status alone is not
accepted as receive evidence.

Classification:

```text
receive acceptance: inconclusive-at-cutoff
```

The overall receive gate did not pass. No reconnect was attempted.

## 8. Automatic Cleanup

The regular cleanup path ran immediately after observation.

Exactly one Disconnect call returned:

```text
MQTT transport disconnected.
```

Readback proved:

- WebSocket inactive;
- authorization headers empty;
- MQTT username and password empty;
- stable client ID retained;
- four subscriptions retained;
- Receiver and both Core instances retained;
- ownership remained valid.

One final Account ApplyChanges disabled the experimental shadow.

Final diagnostics:

```text
featureEnabled:       false
configurationStatus:  disabled
lifecycle state:      Disabled
```

The emergency cleanup path was not used.

## 9. Compatibility Closure

The complete post-retest comparison passed:

| Invariant | Result |
|---|---|
| Account, Configurator and Device identities | unchanged |
| Receiver and Core topology identities | unchanged |
| 14 variable identities and metadata | unchanged |
| five logging and aggregation contracts | unchanged |
| archive history queryability | PASS |
| command evidence during active session | unchanged |
| REST error count | unchanged |
| OAuth connection | retained |
| reauthentication requirement | false |
| MQTT shadow | disabled |
| WebSocket | inactive |
| authorization and MQTT credentials | empty |

The user's existing variable logging remains intact.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| enable ApplyChanges | 1 |
| Connect invocations | 1 |
| Connect retries | 0 |
| read-only observations | 32 |
| Disconnect invocations | 1 |
| disable ApplyChanges | 1 |
| emergency cleanup invocations | 0 |
| MQTT publish attempts | 0 |
| mower actions | 0 |
| created or deleted objects | 0 |
| archive mutations | 0 |

## 11. Architecture Decisions

### AD-NAV-435: Retain stable client identity

**Decision:** Treat a retained non-secret client ID as the valid pre-connect
state.

**Reason:** Cleanup intentionally removes authorization material and broker
credentials while preserving the adopted local transport identity.

**Consequence:** Future preflights must distinguish stable identity from private
session credentials.

### AD-NAV-436: Stop before the formal cutoff

**Decision:** End polling at approximately 160 seconds although the formal
observation cutoff is 165 seconds.

**Reason:** Tool-call latency must not consume the mandatory cleanup reserve.

**Consequence:** Cleanup completed with more than 19 seconds remaining before
the hard deadline.

### AD-NAV-437: Do not repeat an evidence-empty session

**Decision:** Do not request another equivalent one-shot connection immediately.

**Reason:** Two productive sessions now prove healthy native transport but no
accepted Receiver message. Repetition without a changed evidence hypothesis
would add actuation and credential exposure without engineering value.

**Consequence:** The next step is an offline receive-gap analysis covering
subscription application, native envelope delivery, Receiver forwarding and
event-generation assumptions.

## 12. Private Evidence

Private evidence is stored below:

```text
private/navimow-capture/output/native-mqtt-diagnostics/
  retest-enable.json
  retest-connect.json
  retest-session.json
  disconnect-cleanup.json
  post-test-disable.json
  post-retest-compatibility.json
  post-retest-diagnostics.json
  gate-c-evidence-closure.json
```

Private harness source remains below `private/navimow-capture/`.

The public report contains no ObjectID, hash, endpoint, topic, credential,
client ID, device identity or installation metadata.

## 13. Gate Decision

| Gate component | Result |
|---|---|
| enable preflight | PASS |
| one-shot Connect | PASS |
| native transport health | PASS |
| accepted receive evidence | INCONCLUSIVE |
| command invariance | PASS |
| absolute deadline | PASS |
| regular cleanup | PASS |
| final disable | PASS |
| compatibility and archive continuity | PASS |

**Gate C receive acceptance: NOT PASSED.**

**Gate C safety, timing and cleanup: PASSED.**

No live MQTT session remains.

The recommended next SAEF step is:

```text
116-native-mqtt-receive-gap-analysis-and-next-evidence-plan.md
```
