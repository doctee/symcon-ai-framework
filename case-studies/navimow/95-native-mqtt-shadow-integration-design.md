# 95 Native MQTT Shadow Integration Design

**Case study:** Navimow native IP-Symcon module
**Status:** Productive receive-only shadow architecture designed; implementation pending
**Date:** 2026-07-28
**Scope:** Define the optional native MQTT integration without changing REST authority or existing variable identities

## 1. Purpose

This step converts the successful transport result from
`94-native-mqtt-wss-symcon-live-spike-report.md` into a productive shadow-mode
architecture.

It defines:

- the optional Symcon module topology;
- backward-compatible integration with existing REST-only accounts;
- transport and credential ownership;
- the proven native MQTT envelope contract;
- exact-topic payload processing;
- bounded shadow state and diagnostics;
- MQTT-to-REST reconciliation;
- restart, stale and recovery behavior;
- no-publish and no-command invariants;
- implementation and live-test gates.

This step creates no PHP module code, module GUID, core instance or live
mutation.

## 2. Evidence Basis

The design is based on the following completed evidence:

| Evidence | Result |
| --- | --- |
| private WSS/MQTT capture | receive-only transport passed |
| active MQTT/REST comparison | state, battery and location transitions observed |
| partial-payload parser | fixture-backed and timestamp-aware |
| native Symcon topology inspection | WebSocket Client to MQTT Client compatible |
| isolated Symcon probe | custom-child delivery passed |
| native MQTT child envelope | proven |
| MQTT publish attempts | zero |
| mower-command attempts | zero |
| live rollback | complete |

The proven native outer envelope is:

```text
object
  DataID: string
  PacketType: integer
  Payload: string
  QualityOfService: integer
  Retain: boolean
  Topic: string
```

The `Payload` value requires a second bounded JSON decode.

The current fixture-backed semantic channels are:

- `state`;
- `location`.

The subscribed but semantically unsupported channels remain:

- `event`;
- `attributes`.

## 3. Non-Goals

The first productive shadow phase does not:

- make MQTT authoritative for a public variable;
- write `VehicleState`, `BatteryLevel`, `Online` or `LastStatusUpdate` from
  MQTT;
- change the existing REST command transport;
- implement Start or Stop;
- publish an MQTT message;
- store coordinates or geometry;
- archive MQTT payloads;
- create user-facing MQTT variables;
- alter user archive settings;
- silently adopt unrelated core instances;
- automatically delete a WebSocket or MQTT Client;
- prepare a Symcon Store submission.

## 4. Compatibility Constraint

The existing `NavimowAccount` module has no required parent. Existing Account,
Configurator and Device instances operate through the custom Navimow child
interface and REST.

Adding a static MQTT parent requirement directly to the existing Account would
create these risks:

- REST-only accounts could show a missing or incompatible parent warning;
- an update could appear to require MQTT before the feature is enabled;
- existing Account connection topology would change;
- rollback would couple MQTT removal to the productive Account instance;
- transport experiments would gain unnecessary access to the REST owner.

The existing Account module metadata therefore remains parent-independent in
the shadow phase.

## 5. Selected Topology

The productive shadow topology is:

```text
Navimow cloud
    |
    v
native WebSocket Client
    |
    v
native MQTT Client
    |
    v
Navimow MQTT Receiver
    |
    | one validated local handoff
    v
existing Navimow Account
    |
    +--> existing Navimow Configurator
    |
    +--> existing Navimow Device instance(s)
```

The new `Navimow MQTT Receiver` is an optional receive adapter. It:

- is a child of the native MQTT Client;
- uses the same interface metadata proven by the live probe;
- contains no MQTT protocol implementation;
- contains no REST client;
- has no public action;
- has no public state variable;
- has no `SendDataToParent()` path;
- validates the outer envelope;
- hands one bounded decoded envelope to its paired Account;
- persists no topic or payload.

The existing Account remains the semantic and credential owner.

## 6. Why a Separate Receiver Is Now Justified

Step 91 rejected a new public MQTT module before native custom-child delivery
was proven. Step 94 closes that evidence gap.

The receiver is not a custom MQTT bridge. It does not implement:

- MQTT CONNECT;
- SUBSCRIBE;
- PUBLISH;
- keepalive;
- TLS;
- WebSocket framing.

Those responsibilities remain in the native core instances.

The receiver exists only to preserve optionality and backward compatibility.
It prevents a static MQTT parent requirement from being imposed on every
existing Account instance.

## 7. Module Contracts

### 7.1 Receiver metadata

The receiver uses:

```text
parentRequirements:
  {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

implemented:
  {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
```

A new module GUID must be generated only in the implementation step with the
official Symcon GUID Generator or Module Generator.

### 7.2 Account metadata

The current custom child interface remains unchanged:

```text
{54620029-127D-470D-97C7-44265496FAA0}
```

No MQTT parent requirement is added to `NavimowAccount/module.json`.

### 7.3 Minimal local handoff

One narrow Account method is required because the receiver and Account are not
in the same Symcon parent chain.

Candidate contract:

```text
IngestMqttEnvelope(receiverInstanceId, envelopeJson)
```

The method must:

1. require MQTT shadow mode to be enabled;
2. resolve the configured receiver instance;
3. require the caller ID to equal that receiver;
4. require the receiver module GUID;
5. require symmetric Account/receiver pairing;
6. enforce the outer-envelope byte limit;
7. parse and validate the envelope;
8. perform no action and return no secret.

This is an implementation-required module boundary, not a reusable SAEF helper
API.

## 8. Explicit Pairing

The Account and receiver require symmetric configuration:

| Owner | Setting | Meaning |
| --- | --- | --- |
| Account | `EnableMqttShadow` | explicit opt-in; default `false` |
| Account | `MqttReceiverInstanceId` | selected receiver |
| Receiver | `AccountInstanceId` | selected Account |

The pairing is valid only when:

- both IDs exist;
- both module GUIDs match;
- both settings point to each other;
- the receiver has the native MQTT Client as its direct connection;
- the MQTT Client has the native WebSocket Client as its direct connection;
- no wildcard subscription exists;
- the chain is explicitly declared dedicated to this Account.

An incomplete or asymmetric pairing fails closed. REST continues operating.

No receiver may send data to an Account merely because the Account module GUID
matches.

## 9. Core-Instance Ownership

The first shadow pilot does not automatically create, adopt or delete core
instances.

The user creates or selects one dedicated chain and explicitly pairs it with
one Account. The Account records bounded ownership metadata only after the
chain passes validation.

Required ownership metadata:

```text
formatVersion
receiverInstanceId
mqttInstanceId
webSocketInstanceId
moduleGuids
connectionOrder
accountBinding
subscriptionConfigurationHash
transportConfigurationHash
adoptedAt
```

It must not contain:

- URL;
- header values;
- username;
- password;
- client ID;
- device ID;
- complete topic.

Automatic mutation is permitted only while the current chain still matches the
recorded IDs, module GUIDs, connection order and binding.

Drift disables managed recovery. It is reported, not repaired.

## 10. Future Automatic Creation

Automatic core-instance creation is deferred until the private shadow pilot
proves:

- repeated configuration is idempotent;
- restart does not duplicate the chain;
- branch/module updates preserve connections;
- disabling shadow mode leaves REST untouched;
- removal behavior can distinguish owned and user-owned instances;
- core forms and properties remain stable across supported Symcon versions.

Even after that gate, disabling MQTT must not automatically delete core
instances. Deletion remains an explicit maintenance action with its own
ownership and rollback checks.

## 11. Configuration Contract

The Account adds only the minimum shadow configuration:

| Property | Type | Default | Role |
| --- | --- | --- | --- |
| `EnableMqttShadow` | boolean | `false` | explicit feature gate |
| `MqttReceiverInstanceId` | integer | `0` | explicit paired receiver |

Candidate internal constants:

| Bound | Value |
| --- | ---: |
| outer envelope | 65,536 bytes |
| decoded payload | 32,768 bytes |
| JSON depth | 32 |
| location entries | 64 |
| fields per object | 64 |
| tracked devices | 64 |
| retained error entries | 20 |
| pending REST reconciliations | 64 |

Timing values remain internal during the first pilot. User-facing timing
properties are added only if real observations demonstrate a recurring need.

## 12. Credential Retrieval

`ApiClient` requires a read-only method for:

```text
GET /openapi/mqtt/userInfo/get/v2
```

The response mapper must require:

- API success;
- WSS endpoint data;
- MQTT username;
- MQTT password;
- all required values as non-empty strings;
- `wss://`;
- port 443;
- a bounded path and query;
- no fragment.

The mapper returns a typed internal credential value. It never enters a fixture
with real values and is never sent to Device or Configurator children.

## 13. Credential Ownership and Persistence

Credential ownership remains:

| Secret | Runtime owner |
| --- | --- |
| OAuth client secret | Account property |
| OAuth access and refresh tokens | Account attributes |
| WSS Bearer header | dedicated WebSocket Client property |
| MQTT username and password | dedicated MQTT Client properties |
| client ID | dedicated MQTT Client property |

The Account may configure those core properties only after the ownership gate
passes.

No secret may enter:

- `SendDebug()`;
- public variables;
- shadow diagnostics;
- error history;
- fixtures;
- configuration hashes;
- public reports.

Configuration hashes must use redacted structural values rather than hashing
raw secrets.

## 14. Healthy Token Refresh

Routine OAuth refresh does not reconfigure a healthy MQTT connection.

The Account stores the newest access token for REST. The active WSS connection
continues with the credentials used for its successful upgrade.

The newest OAuth token is applied to the Bearer header only when:

- MQTT shadow is enabled for the first time;
- the owned transport is disconnected and recovery is due;
- an authentication failure requires new transport credentials;
- an explicit supervised reconnect is requested.

This avoids unnecessary disconnects after every OAuth refresh.

## 15. Transport Configuration Sequence

Every managed connection attempt uses this order:

```text
validate ownership and OAuth
    -> set WebSocket Active=false
    -> apply WebSocket deactivation
    -> retrieve fresh MQTT/WSS credentials
    -> configure exact MQTT subscriptions while inactive
    -> configure WSS URL, binary mode, TLS verification and Bearer header
    -> apply inactive child and parent configuration
    -> set WebSocket Active=true exactly once
    -> observe core status and receive evidence
```

There is no immediate retry after an ambiguous result.

The sequence is protected by the existing Account semaphore or a separate
transport semaphore if testing proves that REST operations must remain
independent.

## 16. Authentication Reset

`ResetAuthentication()` must:

1. deactivate an owned WebSocket Client;
2. stop MQTT lifecycle and reconciliation timers;
3. clear pending shadow and recovery state;
4. clear credential-bearing owned core properties where supported;
5. retain the core instances;
6. leave all Device variables and archive settings unchanged;
7. continue the existing OAuth reset behavior.

If ownership is ambiguous, the Account deactivates no unrelated object and
reports the configuration error.

## 17. Transport State Machine

The MQTT lifecycle requires explicit states:

```text
Disabled
WaitingForAuthentication
WaitingForPairing
Ready
Configuring
Connecting
ShadowActive
Disconnected
Backoff
ReauthenticationRequired
ConfigurationError
```

Allowed high-level transitions:

```text
Disabled
    -> WaitingForAuthentication
    -> WaitingForPairing
    -> Ready
    -> Configuring
    -> Connecting
    -> ShadowActive

ShadowActive
    -> Disconnected
    -> Backoff
    -> Configuring

any managed state
    -> Disabled
    -> ReauthenticationRequired
    -> ConfigurationError
```

No state transition may issue a mower command.

## 18. Recovery and Backoff

Candidate recovery delays:

```text
30 seconds
60 seconds
120 seconds
300 seconds
900 seconds
```

After five unsuccessful attempts:

- automatic recovery stops;
- REST polling continues;
- shadow state is marked unavailable;
- the next attempt requires a new qualifying state change or explicit user
  action.

Recovery is safe to retry because it contains no mower action and no MQTT
publish. It still remains bounded because each attempt changes core transport
configuration and may contact the vendor.

## 19. Status and Silence

Core-instance status and channel freshness are separate signals.

Core status determines:

- connected;
- inactive;
- configuration error;
- disconnected.

Channel timestamps determine:

- last state message;
- last location message;
- last accepted message;
- last rejected message.

Message silence alone does not trigger a reconnect. A mower may be stable or a
channel may be event-driven.

Silence may:

- mark shadow evidence stale;
- request a bounded REST reconciliation;
- leave the native connection unchanged.

## 20. Restart Contract

After a Symcon restart:

- no cached MQTT value is treated as current;
- no MQTT value is written to a public variable;
- the Account revalidates symmetric pairing and ownership;
- the exact subscriptions are normalized once;
- duplicate subscriptions are removed before any apply;
- transport status is inspected before recovery;
- fresh messages reconstruct shadow state;
- REST polling continues independently;
- no command or MQTT publish occurs.

The semantic shadow cache is cleared in `ApplyChanges()`. Persistent ownership,
statistics and recovery metadata remain bounded and versioned.

## 21. Envelope Parser

A new fixture-backed component is required:

```text
libs/Navimow/MqttEnvelopeParser.php
```

It validates:

- maximum envelope size before decode;
- top-level JSON object;
- exact MQTT receive `DataID`;
- required key names;
- field types;
- bounded topic and payload strings;
- QoS;
- retained flag;
- packet type as a bounded integer.

It returns:

```text
topic
payload
qualityOfService
retained
packetType
```

It returns no raw envelope and retains no data.

## 22. Retained Messages

The live spike proved the field type but did not preserve the retained value.

During shadow mode:

- retained messages are counted;
- their shape may be validated;
- they do not mutate shadow semantic state;
- they may queue one bounded REST reconciliation;
- they never update a public variable.

A later fixture and live gate is required before retained state can be used.

## 23. Exact Topic Contract

Subscriptions are generated deterministically from the current discovery
cache:

```text
/downlink/vehicle/{DEVICE_ID}/realtimeDate/state
/downlink/vehicle/{DEVICE_ID}/realtimeDate/event
/downlink/vehicle/{DEVICE_ID}/realtimeDate/attributes
/downlink/vehicle/{DEVICE_ID}/realtimeDate/location
```

Rules:

- four exact topics per discovered device;
- QoS 0;
- no wildcard;
- unique sorted rows;
- maximum 64 devices;
- no topic for a device outside current discovery;
- unknown topic rejection before payload decode;
- device identity checked again inside fixture-backed state payloads.

Complete topics remain only in the native MQTT Client configuration and
transient parser input.

## 24. Payload Parser Promotion

The existing candidate components are:

```text
candidate/MqttPayloadException.php
candidate/MqttPayloadParser.php
candidate/MqttPartialStateAccumulator.php
```

Before promotion into `distribution/libs/Navimow/`, they require:

- production envelope integration;
- reduction of the payload limit from one MiB to 32 KiB unless new evidence
  requires more;
- explicit retained-message behavior;
- restart-safe serialization tests for bounded accumulator state;
- state-string mapping tests;
- unknown-channel counters;
- source timestamp and local receipt timestamp separation.

`event` and `attributes` continue to fail closed after topic recognition.

## 25. State Channel Shadow

The fixture-backed state payload provides:

```text
device_id
state
battery
timestamp
```

The shadow model stores only:

```text
normalized state candidate
battery candidate
source timestamp
local receipt timestamp
last comparison result
```

The known direct state strings may be mapped to the same internal state
constants used by REST. Unknown state strings:

- are counted;
- are not mapped;
- queue one bounded REST reconciliation;
- never reach a public variable.

Source timestamps order state-channel updates. Local receipt timestamps own
freshness because vendor clock assumptions are not established.

## 26. Location Channel Shadow

The location parser applies partial patches in timestamp order.

It may retain only:

- numeric `vehicleState`;
- `mowingPercentage` if fixture-backed and valid;
- message type;
- source timestamp;
- local receipt timestamp;
- changed-field metadata.

It must discard immediately:

- `postureX`;
- `postureY`;
- `postureTheta`;
- coordinates;
- partition or map geometry;
- unknown field values.

Timestamp-less messages may be classified but do not mutate accumulated state.

## 27. Numeric Location State

Current same-session correlation supports:

| Numeric value | Candidate meaning |
| ---: | --- |
| 4 | Running |
| 5 | Docking |
| 2 | Docked |

Value `1` remains unresolved.

Numeric state is therefore not public authority. In shadow mode:

- a change to 4, 5 or 2 may queue REST reconciliation;
- an unknown numeric value also may queue one bounded reconciliation;
- the numeric value itself is retained only as bounded diagnostic evidence;
- no numeric value updates `VehicleState`.

## 28. Coalesced REST Wake

MQTT processing must not perform a synchronous REST call inside
`ReceiveData()`.

The Account maintains a bounded pending-reconciliation registry and a short
timer:

```text
MQTT message
    -> validate and update shadow
    -> add device to pending set
    -> arm one reconciliation timer
    -> return from ReceiveData()
```

The timer:

- handles a bounded number of devices per execution;
- sends a targeted `PollStatus` message to Device children;
- coalesces repeated location traffic;
- enforces a minimum per-device wake interval;
- retains the normal REST polling timer as fallback.

Candidate first-pilot minimum:

```text
30 seconds per device
```

## 29. Targeted Device Poll

The existing Account-to-Device message remains backward compatible.

Current global form:

```text
Function: PollStatus
```

Candidate targeted form:

```text
Function: PollStatus
DeviceId: configured device ID
Reason: mqtt-shadow-reconciliation
```

Each Device:

- accepts the existing global form;
- ignores a targeted form for another device;
- performs the unchanged REST `GetStatus` call for its own ID;
- updates existing variables only from that REST result.

This preserves REST authority while allowing fast MQTT wake hints.

## 30. REST/MQTT Comparison

`performStatus()` compares the fresh REST result against the most recent
eligible MQTT shadow entry for the same device.

Comparison eligibility requires:

- both observations within a bounded local receipt window;
- MQTT source update not superseded by a newer shadow value;
- known direct state mapping;
- valid battery values.

Candidate rules:

| Field | Comparison |
| --- | --- |
| vehicle state | exact normalized state |
| battery | difference of at most one percentage point |
| online | REST only |
| timestamps | recorded separately, not compared as equal |

An ineligible pair is `not-comparable`, not a mismatch.

## 31. Authority Boundary

The only productive write path remains:

```text
REST response
    -> Account mapping
    -> Device applyStatusResult()
    -> existing public variables
```

The MQTT path is:

```text
MQTT receive
    -> bounded parse
    -> private shadow
    -> optional coalesced REST wake
    -> diagnostic comparison
```

No MQTT method calls `SetValue()` for mower-domain public variables.

## 32. Variable and Archive Compatibility

The shadow implementation must preserve:

- Account, Configurator and Device instance IDs;
- all existing variable IDs;
- Idents;
- types;
- profiles;
- custom profiles;
- actions;
- visibility and positions;
- archive logging;
- archive aggregation;
- historical archive data.

The receiver creates no public variables.

Existing user-enabled logging for battery, state and command diagnostics
remains installation-owned and must not be changed by `ApplyChanges()`.

## 33. Internal State

Required internal state is divided by purpose:

| State | Persistence | Content |
| --- | --- | --- |
| ownership registry | persistent | IDs, GUIDs and redacted hashes |
| lifecycle registry | persistent | state, retry and next-attempt metadata |
| statistics | persistent | bounded counters and local timestamps |
| error history | persistent | sanitized bounded entries |
| semantic shadow | cleared on restart | state/battery and ordering metadata |
| pending reconciliation | cleared on restart | bounded device set |

No registry stores geometry, raw payload, topic, endpoint or secret.

## 34. SAEF Diagnostics Reuse Decision

The existing SAEF diagnostics building blocks were evaluated:

- `ConfigurationHash` is directly suitable for redacted ownership and
  subscription fingerprints;
- `Registry` models the required ownership and lifecycle shape;
- `Statistics` models counters and timestamps;
- `ErrorRingBuffer` models bounded sanitized failures.

The current helper implementations create script-owned variables. Using them
unchanged inside the module would create additional child variables and violate
the no-new-public-variable shadow gate.

The implementation plan must therefore choose one of two reviewed paths:

1. adapt the existing diagnostics contracts for native module attributes
   without adding a new public SAEF API; or
2. create hidden module-owned diagnostic variables only after a separate
   variable-contract decision.

The first shadow implementation should prefer versioned bounded module
attributes and reuse `ConfigurationHash` directly. This is consistent with the
existing Account attribute pattern and keeps the public object tree stable.

## 35. Diagnostic Counters

Minimum bounded counters:

```text
receiveCalls
acceptedState
acceptedLocation
rejectedEnvelope
rejectedTopic
rejectedPayload
retainedIgnored
unsupportedChannel
outOfOrder
missingTimestamp
restWakeQueued
restWakeSuppressed
restComparisons
stateMatches
stateMismatches
batteryMatches
batteryMismatches
transportConnectAttempts
transportDisconnects
credentialRefreshAttempts
publishAttempts
commandAttempts
```

`publishAttempts` and `commandAttempts` remain fixed at zero by structural
absence of those paths.

## 36. Error Classification

Errors use bounded vocabulary:

```text
pairing
ownership
configuration
authentication
transport
envelope
topic
payload
ordering
reconciliation
internal
```

Stored context may contain:

- channel;
- reason code;
- message length;
- local timestamp;
- retry attempt;
- core status code.

It may not contain source values, topics, payloads, IDs, URLs or secrets.

## 37. Debug Boundary

The existing `DebugPayloads` property must not enable raw MQTT logging.

MQTT debug output is limited to:

- result code;
- channel;
- byte count;
- accepted/rejected;
- local receipt time;
- bounded counter values.

Coordinates and raw payloads are never sent to `SendDebug()`.

## 38. Static No-Publish Contract

The productive receiver and MQTT Account integration must contain no:

```text
SendDataToParent
MQTT_Publish
Publish
/uplink/
command topic
REST sendCommands call from MQTT code
RequestAction from MQTT code
```

The existing REST command implementation remains separately allowed and must
be excluded precisely from static scans rather than weakening the MQTT rule.

## 39. Offline Test Matrix

Required executable tests:

### Envelope

- proven envelope accepted;
- wrong `DataID` rejected;
- missing key rejected;
- wrong field type rejected;
- oversized envelope rejected before decode;
- oversized payload rejected before second decode;
- malformed outer and inner JSON rejected;
- retained message ignored semantically;
- QoS outside the contract rejected.

### Topic and payload

- four exact topics generated per device;
- deterministic ordering;
- duplicate removal;
- wildcard rejection;
- unknown device rejection;
- state identity mismatch rejection;
- unsupported channel fails closed;
- all promoted MQTT fixtures pass;
- coordinates never survive parser output.

### Pairing and ownership

- disabled Account does nothing;
- asymmetric pairing fails closed;
- wrong module GUID fails closed;
- wrong connection order fails closed;
- ownership drift causes no write;
- unrelated core instances remain unchanged;
- repeated `ApplyChanges()` creates no object or subscription duplicate.

### Lifecycle

- healthy OAuth refresh does not reconnect MQTT;
- initial enable configures once;
- disconnect schedules bounded recovery;
- recovery delays follow the fixed sequence;
- fifth failure stops automatic recovery;
- reset deactivates only an owned chain;
- restart clears semantic shadow and pending reconciliation;
- restart preserves ownership and bounded counters;
- no startup command or publish.

### Reconciliation

- high-frequency location messages coalesce;
- targeted Device poll filters by device ID;
- REST remains the only public write source;
- exact state comparison;
- battery tolerance;
- stale pairs are not mismatches;
- unknown numeric state queues at most one bounded REST wake.

### Compatibility

- existing Account variables remain identical;
- existing Device variables remain identical;
- archive configuration remains untouched;
- REST-only operation passes with no MQTT instances;
- Dock, Pause and Resume regression tests remain green.

## 40. Static and Metadata Gates

Before publication:

```text
PHP syntax
PHPCS
PHPStan
focused envelope/parser tests
ownership fake-runtime tests
lifecycle fake-time tests
REST and command regression tests
module JSON schemas
official Symcon Module Validator
distribution validation
static no-publish scan
static no-command-from-MQTT scan
complete repository gate
```

The new receiver metadata must be validated independently before it enters the
standalone module repository.

## 41. Staged Symcon Gates

### Gate A: Disabled update

- publish implementation without enabling MQTT;
- update Symcon;
- verify every existing instance and variable identity;
- verify archive equality;
- verify no core instance is created;
- verify REST polling and commands remain unchanged.

### Gate B: Inactive pairing

- create one dedicated inactive transport chain;
- create and pair one receiver;
- verify ownership and configuration shape;
- keep WebSocket inactive;
- verify no public value changes.

### Gate C: Docked receive-only shadow

- enable shadow;
- connect once;
- receive bounded state/location evidence;
- verify internal counters;
- verify no public write originates from MQTT;
- verify no publish or command.

### Gate D: Active scheduled operation

- allow a normal mower schedule to start;
- observe rapid location wake;
- verify one coalesced targeted REST refresh;
- compare MQTT and REST state/battery;
- make no mower-control decision from the test.

### Gate E: Restart

- restart Symcon during a supervised normal operating phase;
- verify no stale shadow promotion;
- verify one chain and one exact subscription set;
- verify automatic receive recovery or bounded fallback;
- verify REST remains operational.

### Gate F: Credential recovery

- execute only after restart behavior passes;
- use a bounded non-actuating credential-recovery procedure;
- do not force vendor account failure without a separate plan;
- verify no reconnect loop and no secret leakage.

Each live gate requires private machine-readable evidence, a sanitized report
and verified cleanup or settled productive state.

## 42. Promotion Gates for MQTT Authority

MQTT may become authoritative for a field only after:

- at least one extended private shadow observation;
- restart and disconnect recovery pass;
- mismatch rates are bounded and explained;
- source timestamp ordering is stable;
- retained-message semantics are proven;
- fallback behavior is deterministic;
- existing variable IDs and archive histories remain preserved;
- a separate SAEF authority decision approves the exact field.

Promotion is per field. Battery and vehicle state do not have to move together.

Location geometry is excluded from authority in the current roadmap.

## 43. Risks

### R-95-01: Core properties persist credentials

Native core instances must retain connection properties. This is an
installation-local credential boundary and must be documented in the module.

### R-95-02: Core status may not identify authentication cause

A non-healthy status may not distinguish WSS, MQTT or credential failure.
Recovery must remain bounded and diagnostics must not overclaim a cause.

### R-95-03: Applying headers may reconnect

Changing and applying the WSS header can tear down a healthy session. Healthy
OAuth refresh therefore does not update the active transport.

### R-95-04: Multiple devices expand subscriptions and REST wakes

Exact topics grow by four per mower. Device count, pending wakes and per-cycle
work are bounded.

### R-95-05: Direct receiver-to-Account API is local coupling

The optional adapter requires one explicit cross-instance method. Symmetric
pairing and module-GUID checks keep the coupling narrow and testable.

### R-95-06: Source clocks may differ

Vendor timestamps order messages but do not determine local freshness. Local
receipt time remains authoritative for TTL and comparison windows.

### R-95-07: State topic is not an activity heartbeat

State messages can be delayed while stable. Location is the rapid wake hint;
REST remains the fallback.

### R-95-08: Numeric location state is incomplete

Observed values do not define the full domain. Unknown values fail closed and
may trigger only bounded REST reconciliation.

## 44. Open Questions

- Does the native MQTT Client preserve exact subscription order after every
  Symcon update?
- Which core status transitions distinguish WSS upgrade and MQTT login failure?
- How long are MQTT credentials valid independently of OAuth tokens?
- Does a healthy broker session survive every routine access-token expiry?
- Are retained messages ever emitted by the Navimow broker?
- Which additional state strings occur in mapping, lifted, error and update
  phases?
- Is 30 seconds the correct REST-wake cooldown for more than one mower?
- Should hidden diagnostic variables be preferred over attributes after the
  first pilot?
- When is automatic creation of the dedicated core chain sufficiently proven?

## 45. Architecture Decisions

### AD-NAV-356: Preserve the Account's parent-independent metadata

**Decision:** Do not add a mandatory MQTT parent requirement to the existing
Account.

**Rationale:** REST-only instances must remain healthy and unchanged after an
update.

**Consequence:** MQTT enters through an optional receiver adapter.

### AD-NAV-357: Add a native-child receiver, not a protocol bridge

**Decision:** Add one minimal module below the native MQTT Client.

**Rationale:** Native transport is proven; only optional delivery into the
existing Account boundary is missing.

**Consequence:** No custom MQTT, TLS or WebSocket engine is introduced.

### AD-NAV-358: Require symmetric explicit pairing

**Decision:** Account and receiver must point to each other and pass module and
connection checks.

**Rationale:** A compatible module type alone is insufficient ownership proof.

**Consequence:** Arbitrary local receiver calls and silent adoption fail closed.

### AD-NAV-359: Defer automatic core-instance creation

**Decision:** The first shadow pilot uses an explicitly dedicated chain.

**Rationale:** Creation, update, restart and deletion semantics require their
own evidence.

**Consequence:** Initial setup is more manual but has a smaller blast radius.

### AD-NAV-360: Keep transport management in the Account

**Decision:** The Account owns credential retrieval, owned-core configuration
and recovery state.

**Rationale:** OAuth, discovery and device identity already belong to the
Account.

**Consequence:** Receiver remains stateless and contains no secret lifecycle.

### AD-NAV-361: Parse in two bounded stages

**Decision:** Parse the native envelope before exact-topic payload parsing.

**Rationale:** The live contract proves a JSON string nested inside the outer
JSON envelope.

**Consequence:** Both byte limits are enforced before their corresponding
decodes.

### AD-NAV-362: Use MQTT only to shadow and wake REST

**Decision:** MQTT may update internal diagnostics and queue a coalesced REST
read, but may not update public mower state.

**Rationale:** This captures MQTT latency benefits without changing authority.

**Consequence:** Existing Device update and archive behavior remains unchanged.

### AD-NAV-363: Discard geometry before persistence

**Decision:** Location geometry never enters the shadow registry, diagnostics
or debug output.

**Rationale:** Geometry is unnecessary for the current state and wake use case.

**Consequence:** The productive privacy boundary is narrower than the vendor
payload.

### AD-NAV-364: Separate source ordering from local freshness

**Decision:** Vendor timestamps order channel updates; local receipt timestamps
determine freshness and comparison windows.

**Rationale:** Vendor clock assumptions are not established.

**Consequence:** Clock skew cannot keep stale shadow evidence fresh.

### AD-NAV-365: Model transport recovery explicitly

**Decision:** Use a persisted bounded state machine and fixed backoff sequence.

**Rationale:** Restart and multi-execution recovery cannot rely on hidden local
state.

**Consequence:** Every recovery attempt and terminal stop is deterministic.

### AD-NAV-366: Preserve the public variable and archive contract

**Decision:** The receiver creates no public variable and MQTT writes no
existing mower-domain variable during shadow mode.

**Rationale:** The user's ObjectIDs and archive histories are installation-owned
compatibility state.

**Consequence:** The first MQTT update can be tested without archive migration.

### AD-NAV-367: Reuse SAEF diagnostic contracts without exposing new variables

**Decision:** Reuse the ConfigurationHash behavior directly and model Registry,
Statistics and ErrorRingBuffer contracts in bounded module attributes for the
first pilot.

**Rationale:** The existing helpers were checked, but their variable-backed
storage conflicts with the no-new-public-variable gate.

**Consequence:** No new general SAEF helper API is introduced before recurring
module reuse is demonstrated.

## 46. Decision

**Native MQTT shadow architecture: APPROVED FOR IMPLEMENTATION PLANNING.**

**Existing Account metadata compatibility: PRESERVED.**

**Optional receiver adapter: SELECTED.**

**REST authority: RETAINED.**

**MQTT publish: PROHIBITED.**

**MQTT-triggered mower command: PROHIBITED.**

**Existing variable IDs and archive histories: HARD INVARIANT.**

**Automatic core-instance creation: DEFERRED.**

**Productive MQTT field authority: NOT APPROVED.**

## 47. Recommended Next Step

Create:

```text
96-native-mqtt-shadow-implementation-plan.md
```

That plan should divide implementation into independently reviewable packages:

1. envelope fixtures and parser;
2. receiver module scaffold and metadata;
3. Account pairing and shadow ingestion;
4. payload-parser promotion and privacy reduction;
5. coalesced targeted REST reconciliation;
6. credential endpoint and owned transport lifecycle;
7. bounded diagnostics and recovery state;
8. compatibility, no-publish and restart tests;
9. disabled-by-default publication;
10. staged Symcon shadow gates.
