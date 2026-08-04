# 121 Native MQTT Receiver Diagnostics Live Test Report

**Case study:** Navimow native IP-Symcon module
**Status:** Live safety and cleanup passed; Receiver ingress remained zero
**Date:** 2026-07-28
**Scope:** Execute the one-shot Gate-C Receiver-to-Account evidence run

## 1. Authorization

Gate C from step 118 was explicitly authorized with:

```text
Ein einmaliger Receiver-Diagnose-Live-Test mit automatischem Cleanup ist freigegeben.
```

The user then confirmed:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

The mower was started through the official app and remained supervised. No
module mower command was sent.

## 2. Frozen Harness

Before live activation, the six execution sources were:

- syntax checked;
- deterministically hashed;
- recorded in the private harness manifest;
- scanned for prohibited operations.

The complete source set contained:

```text
Connect call sites:              1
MQTT publish call sites:         0
module mower command call sites: 0
module reload call sites:        0
instance create/delete sites:    0
```

The preflight passed while MQTT remained disabled:

```text
Receiver schema and privacy: PASS
Account schema and privacy:  PASS
observable state unchanged:  PASS
MQTT shadow:                 disabled
Receiver receiveCalls:       0
Receiver forwarded:          0
```

## 3. Execution Contract

The live session used:

```text
hard active deadline: 180 seconds
observation cutoff:    165 seconds
cleanup reserve:        15 seconds
poll interval:           2 seconds
```

Observation stopped at approximately 160 seconds to protect the declared
cleanup reserve.

The run permitted exactly:

- one MQTT shadow enable;
- one Connect invocation;
- bounded read-only observations;
- one Disconnect invocation;
- one final disable.

No retry or restart was permitted.

## 4. Enable and Connect

The enable preconditions passed:

- Account connected;
- token usable;
- no reauthentication required;
- retained ownership valid;
- exact Receiver binding retained;
- four exact QoS-0 subscriptions;
- no wildcard;
- WebSocket inactive;
- authorization and MQTT credential slots empty.

One ApplyChanges operation enabled the experimental shadow without connecting.
The lifecycle reached `Ready`.

Exactly one call then invoked:

```text
NAVAC_ConnectMqttShadow
```

The result passed all connection-attempt checks. The Account connection-attempt
counter increased exactly by one.

## 5. Transport Observation

The harness captured 76 observations.

Timing:

```text
first observation:  767 ms
last observation:   158644 ms
```

The first observation saw both native Core transports in settling status
`104`.

The following 75 observations all saw:

```text
native MQTT Client status:      102
native WebSocket Client status: 102
WebSocket active:               true
```

Credentials were present only during the authorized active session. No
credential value entered the evidence.

## 6. Receiver and Account Evidence

All maximum session deltas remained zero:

```text
Receiver receiveCalls:          0
Receiver forwarded:             0
Receiver oversized:             0
Receiver invalidEnvelope:       0
Receiver retainedRejected:      0
Receiver unpaired:              0
Receiver invalidAccount:        0
Receiver handoffFailed:         0
Receiver accountResultInvalid:  0

Account received:               0
Account accepted:               0
Account rejected:               0
```

Final Receiver state:

```text
lastResult:      none
lastReceivedAt:  0
lastForwardedAt: 0
```

Therefore:

```text
NavimowMqttReceiver::ReceiveData() was not observed.
```

No local Receiver rejection occurred because the ingress counter itself never
advanced.

## 7. Physical Context

The mower was visibly performing normal mowing before Connect and was
supervised throughout the active session.

Previous independent active captures observed Navimow location messages at
approximately two-second intervals during mowing.

This makes complete vendor message silence during the 158-second observation
window unlikely, but it does not mathematically prove that this broker session
published a matching message.

The result must therefore be classified as:

```text
strong native delivery/session evidence, not direct broker-publication proof
```

## 8. Cleanup

Cleanup timing:

```text
cleanup start:  160649 ms
cleanup finish: 160843 ms
```

Results:

| Check | Result |
|---|---|
| cleanup before 165-second cutoff | PASS |
| cleanup before 180-second hard deadline | PASS |
| Disconnect invocation count | 1 |
| Disconnect result | PASS |
| final disable result | PASS |
| emergency cleanup used | no |
| WebSocket inactive | PASS |
| authorization headers empty | PASS |
| MQTT username and password empty | PASS |
| experimental MQTT disabled | PASS |

No active MQTT session remains.

## 9. Compatibility Closure

The final full compatibility readback passed:

- all productive instance identities retained;
- all 14 variable identities and metadata retained;
- all five Archive Control logging contracts retained;
- archive history remains queryable;
- Receiver, MQTT and WebSocket topology identities retained;
- Receiver-to-Account binding retained;
- MQTT disabled;
- WebSocket inactive;
- credentials empty.

The command evidence hash remained unchanged across all observations. No mower
command was sent by the module.

## 10. Hypothesis Update

The new Receiver boundary changes the evidence ranking.

| Hypothesis | Result after this run |
|---|---|
| Receiver rejects malformed envelope | excluded for this run |
| retained-message rejection | excluded for this run |
| missing or invalid Account pairing | excluded for this run |
| Account wrapper or parser rejects input | excluded for this run |
| native MQTT child delivery absent | strongly supported |
| broker published no matching message | still possible, now less likely |
| subscription/session behavior prevents delivery | strongly supported |

The productive Receiver and the successful disposable step-94 probe use the
same parent and implemented interface GUIDs. Their relevant remaining
transport difference is the retained stable MQTT client ID versus the fresh
run-specific client identity used by the successful probe and private Python
captures.

The native MQTT Client exposes no Clean Session property and no SUBACK
diagnostic.

## 11. Gate Result

Safety and cleanup:

```text
PASS
```

Productive Receiver ingress:

```text
NOT OBSERVED
```

Productive Receiver-to-Account path:

```text
NOT PROVEN
```

Overall classification:

```text
safety-pass-receive-ingress-not-observed
```

No retry is authorized by this result.

## 12. Architecture Decisions

### AD-NAV-440: Place the finding before the Receiver parser

**Decision:** Classify the observed gap before
`NavimowMqttReceiver::ReceiveData()`.

**Reason:** The earliest Receiver-owned counter remained zero for the complete
active session.

### AD-NAV-441: Use physical activity as supporting context

**Decision:** Treat visible mowing and prior two-second active traffic as
strong context, not direct proof of publication in this broker session.

**Reason:** The current native client exposes neither broker publication nor
SUBACK evidence.

### AD-NAV-442: Preserve the one-shot boundary

**Decision:** Do not retry with changed configuration in this session.

**Reason:** Changing client identity after a negative result would combine two
experiments and violate the authorized one-Connect contract.

## 13. Private Evidence

Private evidence is stored below:

```text
private/navimow-capture/output/native-mqtt-receiver-diagnostics/
  live-harness-manifest.json
  live-preflight.json
  live-session.json
  post-live-compatibility.json
  post-live-comparison.json
  gate-c-evidence-closure.json
```

The evidence contains no public credential, endpoint, topic, payload, device
identity or garden geometry.

No public MQTT fixture is added because no payload reached the Receiver.

## 14. Recommended Next Step

Create:

```text
122-native-mqtt-zero-ingress-root-cause-and-client-id-experiment-plan.md
```

That offline step should:

1. compare the successful step-94 transport and current productive chain
   field by field;
2. confirm the exact native subscription ApplyChanges sequence;
3. evaluate stable client-session behavior without assuming Clean Session;
4. define a single-variable fresh-client-ID experiment;
5. require restoration or explicit adoption of the resulting client identity;
6. retain one Connect, no retry, active physical supervision and automatic
   cleanup;
7. prohibit implementation changes until that transport hypothesis is tested.
