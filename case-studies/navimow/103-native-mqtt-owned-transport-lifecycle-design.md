# 103 Native MQTT Owned Transport Lifecycle Design

**Case study:** Navimow native IP-Symcon module
**Status:** Owned transport lifecycle designed; implementation, publication
and live mutation remain blocked
**Date:** 2026-07-28
**Scope:** Design `WP-8` before any productive core-instance mutation

## 1. Purpose

This step defines how `NavimowAccount` may later manage one dedicated native
IP-Symcon transport chain:

```text
native WebSocket Client
  -> native MQTT Client
    -> Navimow MQTT Receiver
```

The design covers:

- explicit adoption;
- ownership and drift detection;
- local client identity;
- secret retrieval and application;
- exact subscriptions;
- connection ordering;
- lifecycle state;
- disconnect, disable and authentication reset;
- bounded recovery;
- rollback;
- offline and supervised Symcon gates.

This step adds no PHP runtime code and performs no Symcon mutation.

## 2. Fixed Boundaries

The following decisions remain unchanged:

- REST is the only public Device-state authority;
- MQTT is receive-only;
- MQTT cannot send a mower command;
- MQTT shadow mode defaults to disabled;
- the Account keeps its current parent-independent metadata;
- no existing variable is recreated;
- Archive Control remains installation-owned;
- no credential enters a public variable, attribute, fixture, report or debug
  message;
- no unrelated core instance may be adopted or changed;
- disabling MQTT never deletes a core instance;
- `MC_ReloadModule()` is not used.

## 3. Create Versus Adopt

### 3.1 First private lifecycle pilot

The first implementation supports **adoption only**.

Before adoption, the operator creates one dedicated inactive chain through the
normal Symcon UI or a separately authorized bounded MCP procedure:

1. WebSocket Client with `Active = false`;
2. MQTT Client connected to that WebSocket Client;
3. Navimow MQTT Receiver connected to that MQTT Client;
4. Receiver paired back to the Account;
5. four exact QoS-0 topics per discovered mower;
6. no wildcard;
7. no WSS or MQTT credential entered.

The Account does not create these objects in `ApplyChanges()`.

### 3.2 Automatic creation

Automatic creation remains deferred until the adoption pilot proves:

- repeated adoption is idempotent;
- module updates preserve the chain;
- restart creates no duplicate;
- disable and reset touch only the owned chain;
- ownership drift always fails closed;
- core property names remain stable on supported Symcon versions;
- the inactive and active rollback paths are reproducible.

There is no `Create MQTT Chain` action in the first implementation.

### 3.3 Deletion

Automatic deletion is prohibited.

Any future delete workflow requires:

- a separate SAEF decision;
- exact ownership revalidation;
- explicit confirmation;
- child-first deletion ordering;
- private baseline and cleanup evidence.

## 4. Two-Level Validation

The current single validation concept must be split internally.

### 4.1 Adoption candidate validation

Used before ownership exists.

It verifies:

- MQTT shadow explicitly enabled;
- Receiver selected and module GUID correct;
- Receiver points back to the Account;
- Receiver parent is the native MQTT Client;
- MQTT parent is the native WebSocket Client;
- exact connection order;
- both core instances inactive;
- credential-bearing properties empty;
- subscriptions exactly match current discovery;
- four topics per device;
- QoS 0;
- no wildcard;
- maximum 64 devices;
- no unrelated child or owner declaration.

Candidate validation reads but never changes the chain.

### 4.2 Owned-chain validation

Used before every mutation.

It additionally verifies:

- all recorded instance IDs;
- all module GUIDs;
- connection order;
- Account binding;
- Receiver symmetric pairing;
- current exact subscriptions against discovery;
- redacted subscription shape hash;
- redacted transport shape hash;
- locally generated client identity hash;
- adoption timestamp and format version.

Any mismatch returns `ownership-drift` and permits no core mutation.

## 5. Explicit Adoption

The form action is:

```text
Adopt Dedicated MQTT Shadow Chain
```

Preconditions:

- `EnableMqttShadow = true`;
- candidate validation passes;
- chain is inactive;
- credential properties are empty;
- Account has a non-empty discovery cache;
- no ownership registry exists for another chain.

The confirmation displays only the current local instance names and the
connection order. Names are transient UI context and are not stored in public
reports.

On confirmation, adoption:

1. revalidates all preconditions;
2. generates or restores one local client identity;
3. computes redacted structural hashes;
4. writes the bounded ownership registry;
5. moves lifecycle state to `Ready`;
6. leaves both core clients inactive;
7. retrieves no credential;
8. applies no core configuration.

Repeated adoption of the identical chain is idempotent. Adoption of a different
chain while ownership exists fails until an explicit future release workflow
is designed.

## 6. Ownership Registry

Persistent attribute:

```text
MqttOwnershipRegistry
```

Schema:

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
clientIdentityHash
adoptedAt
```

The registry must not contain:

- instance names;
- endpoint;
- header;
- username;
- password;
- access token;
- client ID;
- device ID;
- complete topic;
- raw core configuration.

## 7. Redacted Hashes

The current pre-lifecycle prototype hashes transient raw configuration. WP-8
must replace that with canonical redacted structures before any live use.

### 7.1 Subscription shape

The ownership fingerprint contains only:

```text
formatVersion
deviceCount
channels:
  attributes
  event
  location
  state
qos:
  0
topicCount
```

Actual topics are still compared transiently against topics generated from the
current discovery cache. They are never written to the registry.

### 7.2 Transport shape

The redacted structure contains:

```text
module GUIDs
connection order
WebSocket Type = 1
VerifyCertificate = true
effective port = 443
WSS scheme present
path present boolean
query present boolean
Authorization header name present
Bearer value present boolean
MQTT username present boolean
MQTT password present boolean
client ID format valid boolean
keepalive
subscription shape hash
```

It excludes every raw secret and endpoint component.

Hashing a raw secret is also prohibited.

## 8. Local MQTT Client Identity

The cloud response does not own the client ID.

The Account creates one random local identity token at explicit adoption and
stores it in a private Account attribute:

```text
MqttClientIdentity
```

Rules:

- generated with cryptographically secure random bytes;
- lowercase hexadecimal representation;
- generated once per adopted Account;
- bounded fixed length;
- contains no ObjectID, hostname, username or device ID;
- never returned by diagnostics;
- only its hash enters the ownership registry.

The MQTT Client property is derived deterministically:

```text
symcon_navimow_<local identity prefix>
```

This is stable across restart and module update while remaining unique across
installations.

## 9. Exact Subscription Generation

Subscriptions are generated from the current `DiscoveryCache`.

For each valid device:

```text
/downlink/vehicle/{DEVICE_ID}/realtimeDate/attributes
/downlink/vehicle/{DEVICE_ID}/realtimeDate/event
/downlink/vehicle/{DEVICE_ID}/realtimeDate/location
/downlink/vehicle/{DEVICE_ID}/realtimeDate/state
```

Generation rules:

- validate each device ID with the existing bounded identity contract;
- maximum 64 devices;
- four channels exactly;
- QoS 0;
- sort complete topics bytewise;
- remove duplicates;
- reject `#` and `+`;
- reject an empty discovery result;
- compare current configured topics before writing;
- write only when the canonical set differs.

Complete topics remain only in transient PHP values and the dedicated native
MQTT Client configuration.

## 10. Explicit Connect Action

The first pilot exposes:

```text
Connect MQTT Shadow
```

It requires explicit confirmation and performs one attempt.

Preconditions:

- shadow enabled;
- ownership valid;
- lifecycle state `Ready`, `Disconnected` or `Backoff`;
- both core clients inactive;
- usable OAuth token;
- discovery and subscription generation valid;
- no other Account or lifecycle semaphore active.

There is no connection from `ApplyChanges()`.

## 11. Connection Sequence

Every authorized attempt uses this exact order:

1. enter a dedicated MQTT lifecycle semaphore;
2. revalidate ownership and OAuth;
3. set WebSocket `Active = false`;
4. apply WebSocket deactivation;
5. confirm inactive core state;
6. retrieve fresh credentials with `getMqttUserInfo()`;
7. map credentials with `MqttCredentialMapper`;
8. generate deterministic exact subscriptions;
9. configure MQTT username, password, client ID, keepalive and subscriptions;
10. apply the inactive MQTT Client once;
11. configure WebSocket URL, binary type, certificate verification and one
    Authorization Bearer header;
12. keep WebSocket `Active = false`;
13. apply the inactive WebSocket Client once;
14. read back shape-only configuration and revalidate it;
15. set WebSocket `Active = true`;
16. apply the WebSocket Client exactly once;
17. transition to `Connecting`;
18. release the semaphore;
19. observe core status and accepted receive evidence asynchronously.

No ambiguous result causes an immediate second activation.

## 12. Secret Application

Credential flow:

```text
Account access token
  -> ApiClient request header
  -> MqttCredentialMapper local result
  -> native core properties
```

Property ownership:

| Secret | Native destination |
| --- | --- |
| WSS URL and query | WebSocket `URL` |
| OAuth access token | WebSocket `Headers`, exactly one Authorization row |
| MQTT username | MQTT `UserName` |
| MQTT password | MQTT `Password` |

The Authorization value is:

```text
Bearer <complete access token>
```

The complete value, including `Bearer`, is written to the WebSocket property.

The temporary credential array is released after property application. It is
never written to an Account attribute, lifecycle registry, exception, debug
entry or evidence file.

Core properties are the installation-local secret boundary.

## 13. Healthy OAuth Refresh

Routine OAuth refresh:

- updates existing REST token attributes;
- increments only an internal token generation counter;
- does not rewrite WebSocket headers;
- does not apply either core client;
- does not disconnect a healthy MQTT session;
- does not activate WebSocket again.

The newest OAuth token and fresh MQTT credentials are used only for:

- explicit initial connection;
- explicit reconnect;
- a later authorized recovery attempt;
- confirmed MQTT authentication recovery.

## 14. Lifecycle Registry

Persistent attribute:

```text
MqttLifecycleRegistry
```

Schema:

```text
formatVersion
state
attempt
nextAttemptAt
lastTransitionAt
lastCoreStatus
lastReceiveAt
tokenGeneration
terminalReason
lastRestWakeByDevice
```

`lastCoreStatus` is an integer status code only. No core configuration or
instance name is stored.

States:

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

## 15. State Transitions

Allowed transitions:

```text
Disabled
  -> WaitingForAuthentication
  -> WaitingForPairing
  -> Ready

Ready
  -> Configuring
  -> Connecting
  -> ShadowActive

Connecting
  -> ShadowActive
  -> Disconnected
  -> ReauthenticationRequired
  -> ConfigurationError

ShadowActive
  -> Disconnected
  -> ReauthenticationRequired
  -> Disabled

Disconnected
  -> Backoff
  -> Ready
  -> Disabled

Backoff
  -> Configuring
  -> ConfigurationError
  -> Disabled
```

An accepted Receiver message records `lastReceiveAt`. It may confirm
`ShadowActive` when core status is also healthy.

Message silence alone never triggers reconnect.

## 16. Recovery Phases

### 16.1 First lifecycle implementation

The first implementation supports explicit connect and explicit disconnect.

`MqttLifecycle` may observe and classify status but must not reconnect
automatically.

### 16.2 Recovery implementation gate

Automatic recovery is added only after the supervised restart gate passes.

Fixed schedule:

```text
30
60
120
300
900 seconds
```

Rules:

- one attempt per timer execution;
- fresh credentials per actual attempt;
- no nested retry in `ApiClient`;
- no retry in the parser or Receiver;
- no reconnect from silence alone;
- fifth failed attempt becomes terminal `ConfigurationError`;
- attempt count resets only after healthy core status and an accepted message;
- REST polling and commands continue independently.

## 17. Disconnect

Explicit action:

```text
Disconnect MQTT Shadow
```

It:

1. enters the lifecycle semaphore;
2. revalidates ownership;
3. sets WebSocket `Active = false`;
4. applies deactivation once;
5. confirms inactive state;
6. clears WebSocket `Headers`;
7. clears MQTT `UserName` and `Password`;
8. applies both clients while inactive;
9. stops lifecycle and reconciliation timers;
10. clears semantic shadow and pending reconciliation;
11. preserves subscriptions, local client identity and ownership metadata;
12. leaves every core instance in place;
13. transitions to `Ready` or `Disabled`.

If ownership is ambiguous, it changes no core property.

## 18. Disable Behavior

When `EnableMqttShadow` changes to `false`:

- lifecycle and reconciliation timers stop;
- semantic shadow and pending reconciliation clear;
- an owned chain is deactivated and credential properties are cleared;
- ownership and local client identity remain;
- core instances remain;
- REST behavior remains unchanged.

An existing REST-only Account with no ownership registry performs no native
instance lookup or mutation while disabled.

## 19. Authentication Reset

`ResetAuthentication()` extends its existing behavior only when ownership is
valid.

Before clearing OAuth data, it:

- deactivates the owned WebSocket;
- clears owned WSS and MQTT credential properties;
- stops MQTT timers;
- clears shadow and pending reconciliation;
- moves lifecycle state to `ReauthenticationRequired`.

It does not:

- delete the chain;
- clear the local client identity;
- clear ownership;
- change Device variables;
- alter archive settings.

Ownership drift prevents core mutation but does not prevent the existing OAuth
reset.

## 20. ApplyChanges

`ApplyChanges()` remains idempotent.

Default disabled path:

- registers properties, attributes and timers;
- leaves MQTT timers at zero;
- clears transient shadow data;
- creates no object;
- retrieves no credential;
- performs no native lookup without ownership;
- changes no public variable.

Enabled path:

- clears transient shadow and pending work after restart/update;
- validates pairing and ownership;
- reconstructs lifecycle state from bounded metadata and core status;
- never connects automatically in the first pilot;
- never rewrites credentials for a healthy session;
- never repairs drift.

Repeated `ApplyChanges()` cannot duplicate an instance, connection,
subscription or activation.

## 21. Transaction and Rollback

Adoption requires credential-bearing properties to be empty. Therefore the
first connect does not need to retain unknown previous secrets for rollback.

On failure before activation:

1. keep WebSocket inactive;
2. clear newly written header, username and password;
3. preserve owned instances and non-secret subscription configuration;
4. record one sanitized reason code;
5. transition to `Ready`, `Backoff` or `ConfigurationError`;
6. perform no retry in the same call.

On failure after activation:

1. deactivate once;
2. clear newly applied credentials;
3. retain ownership and instances;
4. clear semantic shadow and pending reconciliation;
5. record bounded status and reason;
6. leave REST operational.

Rollback never restores a captured raw secret.

## 22. Concurrency

Use a dedicated semaphore:

```text
NAVIMOW.MQTT.LIFECYCLE.<AccountInstanceID>
```

It serializes:

- adoption;
- connect;
- disconnect;
- disable cleanup;
- authentication cleanup;
- lifecycle timer processing.

MQTT ingestion retains its separate shadow semaphore. Existing REST account
locking remains unchanged.

No lifecycle call waits while holding the Receiver ingestion lock.

## 23. Diagnostics

WP-8 uses the existing private attributes only.

Allowed lifecycle diagnostics:

- state;
- attempt;
- next attempt timestamp;
- transition timestamp;
- integer core status;
- credential retrieval attempt count;
- activation count;
- disconnect count;
- fixed reason code.

Prohibited:

- endpoint;
- topic;
- device ID;
- ObjectID in public output;
- username;
- password;
- token;
- header;
- raw configuration;
- exception trace.

The expanded public diagnostic method remains `WP-9`, not this step.

## 24. Form Contract

The Account form may expose:

```text
Validate MQTT Shadow Configuration
Adopt Dedicated MQTT Shadow Chain
Connect MQTT Shadow
Disconnect MQTT Shadow
```

Rules:

- adoption, connect and disconnect require confirmation;
- no credential textbox is added;
- no URL or topic is displayed;
- actions return bounded sanitized status text;
- actions are unavailable or fail closed when prerequisites do not pass.

Automatic create, delete and reconnect controls are absent.

## 25. Offline Harness

Before publication, add:

```text
tests/mqtt-shadow-lifecycle.php
```

The fake runtime models:

- native module GUIDs;
- parent connections;
- instance active state;
- core properties;
- status codes;
- `IPS_ApplyChanges()` counts and order;
- credential endpoint responses;
- Account attributes and timers;
- Receiver message evidence.

Required cases:

- disabled update performs zero native calls;
- candidate adoption succeeds only while inactive and credential-empty;
- repeated adoption is idempotent;
- ownership drift blocks every mutation;
- first connect follows the exact write/apply order;
- one explicit connect causes one activation;
- failure before activation clears newly applied secrets;
- failure after activation deactivates and clears secrets;
- healthy OAuth refresh causes zero core apply calls;
- exact subscriptions are stable and duplicate-free;
- disconnect mutates only the owned chain;
- reset with drift mutates no core object;
- restart clears semantic shadow and pending work;
- restart preserves ownership and client identity;
- no publish or mower command path exists.

Recovery schedule tests belong to the later recovery implementation increment.

## 26. Staged Symcon Gates

### Gate A: Disabled update

- publish feature branch only;
- update module with MQTT disabled;
- verify no new instance;
- verify all existing variable IDs and archive settings;
- verify REST polling and commands;
- verify no credential endpoint call;
- use no `MC_ReloadModule()`.

### Gate B: Inactive adoption

- separately authorize one dedicated inactive chain;
- pair one Receiver;
- keep credential properties empty;
- adopt explicitly;
- verify redacted ownership metadata;
- repeat `ApplyChanges()`;
- verify zero duplicate and zero activation.

### Gate C: Docked explicit connection

- mower docked and supervised;
- retrieve fresh credentials;
- configure and activate once;
- receive bounded exact-topic evidence;
- verify zero MQTT publish and zero mower command;
- verify only REST updates public variables.

### Gate D: Scheduled active operation

- use a normal mower schedule;
- observe MQTT hint and one coalesced REST refresh;
- verify private state/battery comparison;
- make no mower-control decision from MQTT.

### Gate E: Restart

- restart Symcon during supervised normal operation;
- verify one chain and one subscription set;
- verify no stale shadow promotion;
- verify REST remains operational;
- classify reconnect behavior without enabling unbounded recovery.

### Gate F: Credential recovery

- execute only after Gate E;
- use a separate non-actuating plan;
- verify fixed retry bounds;
- verify fresh credentials per actual attempt;
- verify no secret leakage or reconnect loop.

Every live gate requires:

- explicit authorization;
- private machine-readable evidence;
- sanitized public report;
- current regression fixtures where applicable;
- rollback or verified settled productive state.

## 27. Stop Conditions

Stop mutation and begin rollback on:

- certificate verification disabled;
- non-WSS endpoint;
- non-443 port;
- wildcard subscription;
- changed parent relation;
- ownership hash mismatch;
- active candidate during adoption;
- pre-existing credential in an unowned candidate;
- unexpected core `ApplyChanges()` count;
- second activation in one attempt;
- unrelated instance mutation;
- variable or archive drift;
- MQTT publish evidence;
- mower-command evidence;
- secret in diagnostic output.

## 28. Open Questions

- Which native core status transitions reliably distinguish WSS upgrade and
  MQTT authentication failure?
- Does changing inactive MQTT subscriptions preserve their canonical order?
- Does clearing `Headers`, `UserName` and `Password` have identical behavior on
  all supported Symcon versions?
- Does a healthy connection survive routine OAuth expiry for the complete
  private observation period?
- Which status and time evidence is sufficient before setting
  `ShadowActive`?
- Should transport recovery remain explicit for the entire private pilot?
- When does evidence justify automatic core-instance creation?

These questions do not block the first offline lifecycle implementation. They
remain live promotion gates.

## 29. Architecture Decisions

### AD-NAV-414: Start with explicit adoption

**Decision:** The first lifecycle implementation does not create core
instances.

**Rationale:** Adoption allows ownership and rollback behavior to be proven
without hidden topology mutation.

**Consequence:** Gate B requires one manually created dedicated inactive chain.

### AD-NAV-415: Require empty credential slots at adoption

**Decision:** Reject a candidate containing WSS or MQTT credentials.

**Rationale:** The Account must not take ownership of unknown secrets or an
unrelated active transport.

**Consequence:** First-connect rollback can clear only values it introduced.

### AD-NAV-416: Replace raw configuration hashes

**Decision:** Use canonical redacted shape hashes and transient exact
comparison.

**Rationale:** Hashing raw secrets still makes secret material an input to
persistent metadata.

**Consequence:** WP-8 implementation must migrate the current prototype hash
contract before live use.

### AD-NAV-417: Persist a random local client identity

**Decision:** Generate one private local identity at adoption and derive a
stable MQTT client ID from it.

**Rationale:** ObjectIDs are not installation-unique broker identities.

**Consequence:** Restart and module update preserve the same client ID without
cloud ownership.

### AD-NAV-418: Activate WebSocket exactly once

**Decision:** Complete and verify all inactive configuration before one
activation.

**Rationale:** The native spike proved this ordering and did not prove
ambiguous retry safety.

**Consequence:** A failed attempt ends before any second activation.

### AD-NAV-419: Keep healthy OAuth refresh transport-neutral

**Decision:** Do not rewrite WSS headers during routine token refresh.

**Rationale:** Applying the header may reconnect a healthy transport.

**Consequence:** Fresh credentials are applied only during an authorized
connection attempt.

### AD-NAV-420: Separate initial lifecycle from recovery

**Decision:** First implement explicit connect/disconnect without automatic
recovery.

**Rationale:** Restart and native status behavior need supervised evidence.

**Consequence:** Fixed backoff implementation follows Gate E rather than
preceding it.

### AD-NAV-421: Never delete on disable or reset

**Decision:** Deactivate and clear owned credentials while retaining the
chain.

**Rationale:** Deletion has a larger ownership and rollback risk.

**Consequence:** Re-enablement may reuse the explicitly adopted chain.

### AD-NAV-422: Preserve REST and archive independence

**Decision:** Lifecycle failure cannot stop REST polling or recreate Device
variables.

**Rationale:** MQTT is an optional acceleration path.

**Consequence:** Productive state and historical logging survive every MQTT
failure mode.

## 30. Verification

Passed:

```text
git diff --check
make check
```

The complete SAEF repository gate, including repository-wide PHPStan, passed.
No runtime, fixture or private evidence file was changed by this design step.

## 31. Decision

**`WP-8` lifecycle design: COMPLETE.**

**First pilot topology strategy: EXPLICIT ADOPTION.**

**Automatic core creation: DEFERRED.**

**Automatic deletion: PROHIBITED.**

**Automatic recovery in first implementation: PROHIBITED.**

**Redacted ownership contract: DEFINED.**

**Local client identity contract: DEFINED.**

**Exact subscription contract: DEFINED.**

**Secret application boundary: DEFINED.**

**Single-activation sequence: DEFINED.**

**Disable and reset behavior: DEFINED.**

**Rollback contract: DEFINED.**

**Offline harness gate: DEFINED.**

**Staged Symcon gates: DEFINED.**

**Productive PHP mutation: NONE.**

**Standalone publication: NOT AUTHORIZED.**

**Live Symcon mutation: NONE.**

**Full repository gate: PASS.**

## 32. Recommended Next Step

Create:

```text
104-native-mqtt-explicit-adoption-and-lifecycle-implementation.md
```

That step should implement only the first lifecycle increment:

1. candidate versus owned validation;
2. redacted ownership hashes;
3. persistent local client identity;
4. explicit idempotent adoption;
5. explicit one-attempt connect;
6. explicit owned disconnect;
7. disabled and authentication-reset cleanup;
8. lifecycle states without automatic reconnect;
9. deterministic fake-runtime tests;
10. no publication or live Symcon mutation.
