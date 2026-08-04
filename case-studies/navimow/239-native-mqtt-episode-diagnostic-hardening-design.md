# 239 Native MQTT Episode Diagnostic Hardening Design

**Case study:** Navimow native IP-Symcon module

**Status:** Design complete; implementation and all live gates remain closed

**Date:** 2026-07-31

**Scope:** Add bounded timing and context evidence to native receive-only MQTT
transport episodes without changing recovery or device behavior

## 1. Purpose

Step 238 narrowed two recovered pilot failures to the native WebSocket or its
upstream transport path. The exact external trigger remained unresolved
because the existing episode record begins only at the next 60-second Account
lifecycle observation.

This design adds enough bounded context to distinguish:

- the first observed native Core status transition;
- later lifecycle detection;
- scheduled and actual reconnect start;
- first observed Core readiness;
- final healthy lifecycle confirmation;
- MQTT ingress and REST health at detection.

The design is diagnostic only. It must not make the transport more aggressive,
relax pilot policy or turn MQTT into a public-state authority.

## 2. Preserved Authority and Safety Boundary

The established authority order remains:

```text
public device state and automation: REST
receive-only early hint:            MQTT
transport evidence:                 internal Account diagnostics
historical mower transitions:       REST-owned Archive Control
```

The change must not:

- activate MQTT or adopt a native transport;
- connect, disconnect or reconnect from `MessageSink()`;
- change the reconnect delays `[60, 300, 900]`;
- change the three-attempt limit or exhaustion behavior;
- change the one-episode pilot acceptance threshold;
- publish an MQTT message;
- issue a mower command;
- update a public Device variable from MQTT;
- add, remove or rename a variable;
- change Archive Control logging or aggregation;
- persist an ObjectID, topic, endpoint, payload or credential.

Publication, Symcon update, MQTT activation and another private pilot remain
separate explicit gates.

## 3. IP-Symcon Status Observation

The Account registers `IM_CHANGESTATUS` for exactly the owned MQTT Client and
WebSocket Client instances while:

- `EnableMqttShadow` is true;
- the owned topology is valid;
- an MQTT pilot observation session is active.

Registration is reconciled idempotently when topology or feature state
changes. Stale registrations are removed. Disabling MQTT removes both
registrations and leaves retained completed evidence intact.

`MessageSink()` handles:

- the existing kernel-start message from sender `0`;
- `IM_CHANGESTATUS` only from the two currently owned Core instances.

For a Core status message, the Account reads both current instance statuses
with `IPS_GetInstance()`. It does not trust or persist the message `Data`
payload because IP-Symcon does not document its shape. The sender is reduced
to the role `mqtt` or `websocket`; its ObjectID is never stored.

The callback is observational. It must not call any connection, cleanup,
credential, REST or device operation. If the existing MQTT semaphore cannot be
acquired within its bounded timeout, one diagnostic drop counter advances and
normal lifecycle polling remains the fallback.

Official SDK references:

- <https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/module/messagesink/>
- <https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/messages/>

## 4. Bounded Core-Transition Evidence

`MqttPilotObservationRegistry` is upgraded from format version 1 to version 2.
It receives one additional bounded ring:

| Evidence | Maximum |
| --- | ---: |
| Core status transitions | 32 |
| Core transitions copied into one episode | 8 |

Each session-level transition contains only:

```text
sequence
sessionSequence
observedAt
senderRole
mqttStatus
webSocketStatus
classification
openEpisodeSequence
```

Allowed classifications are:

```text
healthy
inactive
error
transitioning
unknown
```

The classification is derived only from the two numeric Core statuses and the
owned WebSocket `Active` property. It is not a remote close reason.

Normal credential rotations can also produce native status changes. They
remain in the bounded transition ring with no automatic failure
classification. Episode causality continues to depend on the existing
unexpected-disconnect path.

## 5. Expanded Episode Contract

The existing fields remain. A version-2 episode adds:

```text
coreFaultObservedAt
coreFaultLeadSeconds
reconnectScheduledAt
reconnectDueAt
reconnectStartedAt
coreReadyAt
coreReadySource
recoveryConfirmationLagSeconds
lastMqttReceivedAt
mqttIngressAgeSeconds
mqttIngressSeen
lastRestSuccessAt
restSuccessAgeSeconds
restSuccessSeen
restConnectionState
nearestPriorRotationAt
rotationSeparationSeconds
coreTransitions
diagnosticCompleteness
```

Allowed `coreReadySource` values are:

```text
status-message
lifecycle-observation
unknown
```

Allowed `diagnosticCompleteness` values are:

```text
complete
polling-fallback
legacy
partial
```

All ages and intervals are clamped to non-negative integers. Missing evidence
uses explicit booleans and zero timestamps; absence is never presented as a
real zero-age observation.

## 6. Evidence-Capture Sequence

### 6.1 Core status message

On an accepted `IM_CHANGESTATUS` callback:

1. identify the sender role without persisting its ID;
2. read both current Core statuses and WebSocket active state;
3. append one sanitized transition;
4. if an episode is open, copy the transition into its eight-entry ring;
5. when both Core instances are active, set `coreReadyAt` once with source
   `status-message`.

A Core error transition does not open an episode by itself. Existing lifecycle
logic remains the sole owner of transport recovery decisions.

### 6.2 Episode detection

At the existing unexpected-disconnect point:

1. open exactly one episode as today;
2. find the most recent error-class Core transition from the current session
   within 120 seconds;
3. set `coreFaultObservedAt` and derive `coreFaultLeadSeconds`;
4. copy at most the eight most recent relevant transitions;
5. record reconnect schedule time and due time;
6. snapshot `MqttStatistics.lastReceivedAt`;
7. snapshot the Account-owned `LastRestSuccess` and `ConnectionState`;
8. attach the nearest prior recorded credential rotation.

If no status callback was retained, `coreFaultObservedAt` stays zero and the
episode is classified `polling-fallback`. Recovery proceeds unchanged.

### 6.3 Reconnect start

`beginMqttReconnectAttempt()` sets `reconnectStartedAt` on the open episode
before the existing connection attempt. It does not change the attempt counter
or delay calculation.

### 6.4 Core readiness

The first observation of both Core statuses at `102` sets `coreReadyAt`.
`MessageSink()` is preferred for timing. The existing healthy lifecycle
observation supplies a `lifecycle-observation` fallback if no accepted status
message was available.

### 6.5 Episode completion

The existing healthy observation still closes the episode. Completion derives:

```text
durationSeconds =
    recoveredAt - detectedAt

recoveryConfirmationLagSeconds =
    recoveredAt - coreReadyAt
```

The implementation must continue describing `durationSeconds` as lifecycle
duration. Even with status messages, the exact external network outage remains
unknown.

## 7. REST and MQTT Context

The Account already owns suitable context:

- `MqttStatistics.lastReceivedAt`;
- `LastRestSuccess`;
- `ConnectionState`.

At episode detection these values are copied, not queried from the network.
No REST request is added to an outage path.

The episode records whether MQTT ingress and REST success have ever been seen.
This permits later distinctions such as:

- stale MQTT with current REST;
- stale MQTT and stale REST;
- active ingress immediately before a native Core error;
- absent evidence after a fresh start.

It does not prove local-network, Internet or server causality.

## 8. Vehicle-State Correlation Boundary

The Account can own multiple mowers and must not create a second per-device
state history. Vehicle-state transitions therefore remain outside the pilot
registry.

During private evidence closure, episode timestamps may be correlated with the
existing REST-authoritative `VehicleState` Archive Control series. The public
report may state timing proximity, but only private evidence may retain
installation-specific archive references. No correlation is automatically
classified as causal.

## 9. Schema Migration and Compatibility

The writer and read-only projection move to format version 2.

Migration rules:

- preserve all valid version-1 checkpoints, episodes and rotations;
- normalize version-1 episodes with explicit legacy defaults;
- mark migrated episodes `diagnosticCompleteness: legacy`;
- never fabricate Core transition, ingress, REST or reconnect timestamps;
- retain the existing 32/32/64 bounds;
- apply the new 32-entry Core-transition and eight-entry episode bounds;
- remove unknown top-level and nested fields on productive writes;
- keep `GetMqttDiagnostics()` at its existing version and shape.

Only `GetMqttPilotDiagnostics()` changes to version 2. The private pilot harness
and focused fixtures must be updated in the same implementation step. Existing
closed pilot evidence remains historically valid.

## 10. Concurrency and Failure Handling

Core status callbacks, lifecycle timers and credential rotation may occur near
each other. Every read-modify-write operation on the pilot registry must use
the existing bounded MQTT semaphore.

Required behavior:

- one callback produces at most one transition entry;
- duplicate equal-status callbacks at the same second collapse to one entry;
- lock failure records one bounded dropped-event counter;
- lock failure never blocks lifecycle recovery;
- malformed registry data falls back to a fixed bounded schema;
- registration failure leaves polling diagnostics operational;
- diagnostic failure never changes transport credentials or topology.

No new reusable SAEF helper is justified. The behavior belongs to the
Navimow Account's implementation-specific transport registry.

## 11. Offline Regression Matrix

The implementation step must prove:

| Scenario | Expected result |
| --- | --- |
| MQTT disabled | no Core status registrations |
| valid inactive staging | no pilot status registrations |
| active pilot starts | exactly two owned status registrations |
| ApplyChanges repeated | registrations remain idempotent |
| topology changes | stale registrations removed, current pair registered |
| disable | both registrations removed, history retained |
| unrelated status sender | ignored |
| raw message data | never persisted |
| error status callback | sanitized transition only, no recovery action |
| duplicate callback | one transition |
| 33 transitions | newest 32 retained |
| episode detection after error callback | first-fault lead time retained |
| episode without callback | polling fallback retained |
| MQTT ingress absent | explicit `mqttIngressSeen: false` |
| REST success absent | explicit `restSuccessSeen: false` |
| first reconnect | `reconnectStartedAt` retained, counters unchanged |
| Core ready by message | message timestamp retained |
| Core ready by observer | fallback source retained |
| healthy confirmation | confirmation lag derived |
| more than 8 episode transitions | newest 8 retained |
| credential rotation nearby | timing correlation retained, no causality |
| version-1 registry | lossless historical migration with legacy defaults |
| read-only projection | no state, timer or message-registration mutation |
| public variables | Account 6 and Device 8 remain unchanged |
| existing MQTT suite | recovery and receive-only behavior unchanged |
| complete Navimow gate | syntax, fixtures, PHPCS and PHPStan pass |

The runtime fake must model `RegisterMessage()`, `UnregisterMessage()` and
`GetMessageList()` sufficiently to verify registration ownership and cleanup.

## 12. Acceptance Criteria

The local implementation is ready for a publication plan only when:

- every new field is fixed-schema and bounded;
- no ObjectID or raw message data appears in a projection;
- status callbacks produce no transport or REST side effect;
- old version-1 evidence migrates without fabricated timestamps;
- all focused and complete offline checks pass;
- reconnect delays, retry count and pilot policy are byte-for-byte unchanged;
- public variables and archive contracts remain unchanged;
- MQTT remains default-disabled and receive-only.

## 13. Architecture Decisions

### AD-NAV-869: Observe owned Core status changes through `MessageSink`

`IM_CHANGESTATUS` provides earlier evidence than the 60-second lifecycle timer.
The callback records context only; polling remains the recovery owner and
fallback.

### AD-NAV-870: Ignore undocumented message payload data

The implementation reads current Core status from `IPS_GetInstance()` and
never persists raw `MessageSink()` data. This avoids coupling evidence to an
undocumented payload shape.

### AD-NAV-871: Keep transition history bounded and identity-free

The pilot registry retains at most 32 sanitized Core transitions and eight
transition entries per episode. Sender roles replace installation ObjectIDs.

### AD-NAV-872: Snapshot existing REST and MQTT health without network access

Episode detection copies existing timestamps and connection state. It does not
introduce REST traffic into the transport-failure path.

### AD-NAV-873: Separate Core readiness from lifecycle recovery confirmation

`coreReadyAt` records the first healthy Core observation;
`recoveredAt` remains the later lifecycle confirmation. This makes observation
latency visible without changing recovery semantics.

### AD-NAV-874: Keep mower transitions in REST-owned Archive Control

The Account does not duplicate per-device history. Vehicle-state correlations
are produced only during private evidence analysis.

### AD-NAV-875: Version the pilot projection and preserve legacy evidence

`GetMqttPilotDiagnostics()` advances to version 2 with explicit migration.
The established general MQTT diagnostic API remains unchanged.

## 14. Gate Decision

The diagnostic-hardening design is complete. It authorizes no productive or
live mutation.

The next SAEF step is
`240-native-mqtt-episode-diagnostic-hardening-implementation.md`:

1. implement the version-2 registry and status observation locally;
2. extend the runtime fake and focused fixtures;
3. run the complete Navimow MQTT offline gate;
4. document exact productive deltas and compatibility results.

Publication, Symcon update, persistence acceptance and pilot activation remain
closed until their own explicit SAEF gates.
