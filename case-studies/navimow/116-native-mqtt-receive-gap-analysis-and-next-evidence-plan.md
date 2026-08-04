# 116 Native MQTT Receive Gap Analysis and Next Evidence Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Offline analysis complete; Receiver-boundary diagnostics required
before another live connection
**Date:** 2026-07-28
**Scope:** Explain the zero-receive result from steps 110 and 115 and define
the smallest evidence-producing successor

## 1. Purpose

Step 115 proved:

- exactly one native MQTT connection attempt;
- healthy MQTT and WebSocket Core statuses;
- valid ownership and configuration;
- 32 bounded read-only observations;
- no Account `received`, `accepted`, `rejected` or error delta;
- deadline-conformant Disconnect and final disable.

This step investigates the receive gap without reconnecting MQTT or mutating
the private installation.

It compares:

1. the successful disposable native spike from step 94;
2. the productive Receiver metadata and source;
3. the strict native envelope parser;
4. the Receiver-to-Account wrapper boundary;
5. the Account ingestion counter placement;
6. the native MQTT configuration shape;
7. the known vendor message-generation cadence.

## 2. Safety Boundary

This analysis performs no:

- MQTT enable or Connect operation;
- credential retrieval;
- MQTT publish;
- mower command;
- module update;
- Core configuration change;
- instance or variable creation;
- archive mutation;
- source publication.

One bounded live Symcon probe read only:

- native MQTT and WebSocket configuration key names and types;
- safe booleans and counts;
- availability and callability of fixed module wrappers.

It returned no endpoint, topic, credential, client ID, ObjectID or device
identity.

## 3. Proven Working Reference

The isolated step-94 topology was:

```text
native WebSocket Client
  -> native MQTT Client
    -> Navimow MQTT Receive Probe
```

It received two allowed `location` messages and proved the native child
envelope:

```text
DataID
PacketType
Payload
QualityOfService
Retain
Topic
```

The productive topology has the same physical order:

```text
native WebSocket Client
  -> native MQTT Client
    -> Navimow MQTT Receiver
```

Both child modules declare exactly:

```text
parentRequirements:
  {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

implemented:
  {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
```

Both implement:

```text
ReceiveData($jsonString)
```

No metadata, interface or method-signature drift was found.

## 4. Productive Data Path

The productive path is:

```text
native MQTT Client
  -> NavimowMqttReceiver::ReceiveData()
    -> outer-size check
    -> MqttEnvelopeParser
    -> retained check
    -> Account selection and module check
    -> NAVAC_IngestMqttEnvelope()
      -> symmetric pairing validation
      -> Account semaphore
      -> envelope parser
      -> topic/device validation
      -> semantic payload parser
      -> private shadow reduction
      -> Account MQTT statistics
```

The installed wrappers were verified read-only:

```text
NAVAC_IngestMqttEnvelope:
  function_exists = true
  is_callable = true

NAVAC_GetMqttDiagnostics:
  function_exists = true
  is_callable = true

NAVAC_ValidateMqttShadowConfiguration:
  function_exists = true
  is_callable = true
```

The wrapper surface is therefore currently available.

## 5. Critical Counter Placement Finding

The public bounded diagnostics expose `MqttStatistics` from the Account.

`received` is incremented only inside:

```text
NavimowAccount::recordMqttResult()
```

That method runs only after the Receiver successfully invokes
`NAVAC_IngestMqttEnvelope()`.

It does not count:

- native Core delivery into `NavimowMqttReceiver::ReceiveData()`;
- Receiver outer-size rejection;
- Receiver envelope rejection;
- retained-message rejection in the Receiver;
- missing or invalid Account pairing in the Receiver;
- unavailable or failed Account wrapper handoff;
- invalid Account result normalization.

Therefore:

```text
Account received = 0
```

does not prove:

```text
Receiver ReceiveData calls = 0
```

This is the primary observability gap.

## 6. Parser and Handoff Evidence

The strict envelope parser requires the exact live-proven key set and DataID.
Its fixture regressions pass for state and location envelopes.

The Receiver offline tests pass for:

- native metadata;
- valid envelope parsing;
- retained rejection;
- malformed DataID rejection;
- Account-module validation;
- callable Account handoff;
- accepted Account result.

The Account ingestion tests pass for:

- symmetric pairing;
- exact subscriptions;
- ownership validation;
- state-envelope acceptance;
- `ShadowActive`;
- private shadow reduction;
- unchanged public variables.

The complete current gate passed:

```text
MQTT fixtures
REST authentication
native envelope
payload parser
Receiver scaffold
Account ingestion
REST reconciliation
transport lifecycle
distribution validation
PHPCS
PHPStan
```

This makes a deterministic offline parser or Account-ingestion defect less
likely, but cannot exclude a live Receiver rejection before Account handoff.

## 7. Native Configuration Finding

The disabled post-test MQTT Client exposes only:

```text
ClientID
KeepAliveInterval
Password
Subscriptions
UserName
```

The WebSocket Client exposes:

```text
Active
Headers
Type
URL
VerifyCertificate
```

There is no configurable Clean Session property in the supported native
configuration surface.

The retained local client ID is the meaningful transport difference from the
step-94 disposable probe, which used a random run-specific client ID.

The private Python captures also used a random client ID and explicitly
requested a clean MQTT session.

This difference is relevant but not yet causal:

- both productive sessions reached healthy Core status;
- no subscription acknowledgement is exposed by the native Client;
- rotating identity before proving a Receiver ingress gap would combine two
  hypotheses in one live mutation.

Client-ID rotation is therefore not approved as the next experiment.

## 8. Message-Generation Finding

Vendor traffic is event-dependent.

Known evidence:

- a docked private capture received two location messages in approximately
  180 seconds;
- the disposable native spike received two location messages quickly;
- active mowing produced location messages approximately every two seconds;
- state messages were sparse and change-driven;
- event and attributes channels produced no messages in the long active
  captures.

The 160-second step-115 window did not intentionally stimulate the mower and
recorded no physical activity context.

No broker publication during that particular window is therefore plausible.
However, two productive native sessions now lack Account receive evidence, so
event sparsity alone is insufficient as a final explanation.

## 9. Hypothesis Matrix

| Rank | Hypothesis | Current evidence | Status |
|---:|---|---|---|
| 1 | No vendor message was published during the bounded idle window | Event-dependent traffic; no physical activity marker | plausible |
| 2 | Receiver was called but rejected before Account handoff | Account counter starts after handoff; no Receiver counter exists | plausible and unobservable |
| 3 | Native Client connected but did not apply or deliver subscriptions | Core `102` does not prove child delivery or SUBACK | plausible |
| 4 | Stable client identity changes broker session behavior | Differs from successful random-client tests; no native Clean Session property | possible, not causal |
| 5 | Account wrapper unavailable | Runtime `function_exists` and `is_callable` pass | currently excluded |
| 6 | Account semantic parser rejected live input | Such a call would increment received/rejected | inconsistent with zero Account calls |
| 7 | MQTT message reached Account and diagnostics lost it | Counter writes and synthetic tests pass | low probability |

The current evidence cannot distinguish hypotheses 1 through 3.

## 10. Required Receiver Diagnostics

Before another live connection, add a bounded Receiver-local diagnostic
contract.

Recommended method:

```text
GetReceiveDiagnostics(): string
```

Recommended fixed result:

```text
formatVersion
receiveCalls
forwarded
oversized
invalidEnvelope
retainedRejected
unpaired
invalidAccount
handoffFailed
accountResultInvalid
lastResult
lastReceivedAt
lastForwardedAt
```

Contract rules:

- counters are nonnegative and bounded;
- result strings use a fixed allowlist;
- timestamps are nonnegative integers;
- no topic, payload, DataID, device identity or ObjectID is returned;
- no endpoint, credential or Account result body is returned;
- the first counter increments before envelope parsing;
- `forwarded` increments only after invoking the Account wrapper;
- rejected paths remain distinguishable by fixed local codes;
- no public variable is added;
- no Archive Control setting changes;
- no MQTT publish or mower path is introduced.

The method may expose Receiver-owned attributes only through a fixed projection,
never as raw JSON.

## 11. Next Live Evidence Matrix

After implementation, publication, update and read-only validation, one later
live gate should compare Receiver and Account deltas.

| Receiver delta | Account delta | Interpretation |
|---:|---:|---|
| `receiveCalls = 0` | `received = 0` | no Core delivery or no broker publication |
| `receiveCalls > 0`, `forwarded = 0` | `received = 0` | Receiver rejection identified by local reason |
| `forwarded > 0` | `received = 0` | wrapper invocation or Account boundary defect |
| `forwarded > 0` | `received > 0`, `accepted = 0` | Account parser/pairing rejection |
| `forwarded > 0` | `accepted > 0` | productive receive path proven |

This closes the current ambiguity without exposing payloads.

## 12. Operating Window for a Later Retest

Do not repeat another unconditioned idle session.

The next live session should align with an already scheduled mower run:

- no mower start solely for testing;
- begin after the official schedule has naturally started;
- confirm Running through the existing REST-authoritative variable;
- expect location traffic within a short bounded window;
- retain the same one-Connect and no-retry rule;
- use the proven absolute cleanup deadline;
- stop immediately after a decisive Receiver/Account observation.

Active evidence shows an approximately two-second location cadence. A healthy
active session should therefore distinguish delivery from event sparsity much
faster than another 160-second idle wait.

## 13. Escalation Sequence

Only if an active scheduled window still reports:

```text
Receiver receiveCalls = 0
native MQTT status = 102
native WebSocket status = 102
```

may the next investigation compare:

1. a temporary sibling receive probe under the same native MQTT Client; or
2. a one-time run-specific client identity with explicit cleanup.

These are separate experiments.

Do not rotate the client ID and add a sibling probe in the same session.

## 14. Architecture Decisions

### AD-NAV-438: Account received is not transport ingress

**Decision:** Stop using the Account `received` counter as proof that the
Receiver was not called.

**Reason:** The counter begins after Receiver validation and wrapper handoff.

**Consequence:** Steps 110 and 115 remain correctly inconclusive rather than
being reclassified as broker-delivery failures.

### AD-NAV-439: Instrument the earliest owned boundary

**Decision:** Add bounded diagnostics at the beginning of Receiver
`ReceiveData()`.

**Reason:** This is the earliest productive boundary controlled by the module.

**Consequence:** A later live session can separate Core delivery, Receiver
rejection and Account ingestion without raw payload capture.

### AD-NAV-440: Use natural active traffic

**Decision:** Align a later retest with an existing scheduled mow.

**Reason:** Active location traffic has a proven approximately two-second
cadence, while idle traffic is sparse and event-dependent.

**Consequence:** No mower action is needed solely to generate evidence.

### AD-NAV-441: Defer client-ID rotation

**Decision:** Preserve the stable client identity until Receiver ingress is
observable.

**Reason:** Rotation would alter the broker-session hypothesis before the
current delivery boundary can be measured.

**Consequence:** Client identity becomes a later isolated experiment only if
active traffic still produces zero Receiver calls.

## 15. Gate Decision

| Item | Decision |
|---|---|
| native interfaces and topology | match successful spike |
| productive Receiver source | offline PASS |
| Account wrapper availability | runtime PASS |
| Account ingestion source | offline PASS |
| Core transport health | live PASS |
| Receiver ingress observability | missing |
| cause of zero Account receives | unresolved |
| immediate repeat connection | rejected |
| client-ID rotation now | rejected |
| Receiver bounded diagnostics | approved next design increment |

No productive code is changed in this analysis step.

The recommended next SAEF step is:

```text
117-native-mqtt-receiver-bounded-diagnostics-design-and-implementation.md
```
