# 91 MQTT Symcon Transport Topology Spike

**Case study:** Navimow native IP-Symcon module
**Status:** Native topology selected conditionally; live transport gate pending
**Date:** 2026-07-27
**Scope:** Read-only Symcon capability inspection and transport architecture decision

## 1. Purpose

This step resolves the transport-topology gate opened by
`90-mqtt-active-rest-comparison-capture-report.md`.

It determines how the fixture-backed, receive-only Navimow MQTT/WSS stream can
later enter IP-Symcon without:

- implementing MQTT commands;
- updating productive mower variables;
- replacing REST as the productive authority;
- recreating existing variables or changing archive settings;
- exposing credentials, endpoints, topics or installation identifiers;
- adding productive PHP code during this step.

The alternatives are:

1. native WebSocket Client plus native MQTT Client;
2. a custom Navimow MQTT splitter over the native WebSocket Client;
3. an external receive-only MQTT bridge.

## 2. Evidence Basis

### 2.1 Navimow evidence

Steps 85 through 90 established:

- MQTT is transported over WSS;
- the WebSocket upgrade requires an OAuth Bearer header;
- MQTT CONNECT requires separate username and password values;
- four exact per-device downlink topics are sufficient;
- no MQTT publish is required or permitted;
- state and battery payloads are fixture-backed for shadow use;
- location messages can provide rapid wake evidence;
- REST remains the productive state authority.

### 2.2 Official IP-Symcon documentation

The reviewed official sources are:

- [SDK data flow](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/datenfluss/);
- [MQTT Client](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-client/);
- [WebSocket custom-header introduction](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/);
- [WebSocket binary-transfer introduction](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v64-v70-q4-2023/);
- [`IPS_GetConfiguration`](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/instanzenverwaltung/konfiguration/ips-getconfiguration/);
- [`IPS_SetProperty`](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/instanzenverwaltung/konfiguration/ips-setproperty/).

The documented default MQTT arrangement uses a Client Socket parent. The SDK
data-flow contract, however, determines compatibility through implemented and
required interfaces rather than through that example topology alone.

### 2.3 Authorized read-only Symcon inspection

An authorized bounded read-only probe inspected the installed module metadata,
configuration shapes and forms in IP-Symcon 9.0.

The probe:

- created, changed and deleted no object;
- read no configuration value;
- returned no ObjectID, hostname, endpoint, credential or topic;
- completed with `transportError = null`;
- completed with `executionError = null`;
- returned `truncated = false`.

The installation contained existing MQTT Client instances using Client Socket
parents. This proves ordinary native MQTT operation in the installation, but
not yet MQTT-over-WSS with Navimow.

## 3. Native Interface Compatibility

The read-only metadata produced this interface matrix:

| Module | Module GUID | Parent requirement / implemented interface |
| --- | --- | --- |
| WebSocket Client | `{D68FD31F-0E90-7019-F16C-1949BD3079EF}` | implements `{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}` |
| Client Socket | `{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}` | implements `{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}` |
| MQTT Client | `{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}` | requires `{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}` |

The WebSocket Client and Client Socket implement exactly the parent interface
required by the native MQTT Client.

Therefore this structural connection is compatible in the inspected Symcon
version:

```text
WebSocket Client -> MQTT Client
```

This is stronger evidence than inferring support from a configuration dialog.
It proves module-level compatibility, not successful authentication against
the Navimow broker.

## 4. Native Configuration Capability

Only property names and types were inspected:

| Native instance | Relevant property | Type | Required Navimow use |
| --- | --- | --- | --- |
| WebSocket Client | `URL` | string | private WSS URL |
| WebSocket Client | `Headers` | string | Bearer authorization header |
| WebSocket Client | `Type` | integer | binary transfer |
| WebSocket Client | `VerifyCertificate` | boolean | must remain enabled |
| WebSocket Client | `Active` | boolean | controlled connection lifecycle |
| MQTT Client | `UserName` | string | private MQTT username |
| MQTT Client | `Password` | string | private MQTT password |
| MQTT Client | `ClientID` | string | unique account-scoped client ID |
| MQTT Client | `KeepAliveInterval` | integer | bounded broker keepalive |
| MQTT Client | `Subscriptions` | string | exact-topic subscription list |

The installed WebSocket form identifies `Type = 1` as binary transfer and
supports a structured custom-header list.

The native pair therefore exposes every transport setting required by the
current Navimow evidence. This remains a capability result until a real
connection passes the live gate.

## 5. Candidate Topologies

### 5.1 Option A: Native WebSocket and MQTT clients

```text
Navimow WSS endpoint
        |
        v
IP-Symcon WebSocket Client
  - TLS verification
  - Bearer upgrade header
  - binary frames
        |
        v
IP-Symcon MQTT Client
  - MQTT credentials
  - client ID and keepalive
  - exact subscriptions
        |
        v
Navimow Account
  - account and credential lifecycle
  - topic allowlist
  - receive-only parsing
  - shadow reconciliation
        |
        v
Existing Navimow device/configurator children
```

Benefits:

- uses the native WebSocket and MQTT protocol engines;
- avoids implementing MQTT framing, keepalive and subscription handling;
- keeps certificate verification in the supported I/O layer;
- keeps MQTT reconnect mechanics in a core instance;
- preserves the existing account-to-device ownership boundary.

Unproven points:

- successful Navimow WSS upgrade with the configured Bearer header;
- successful binary MQTT exchange through the native pair;
- exact MQTT child receive envelope delivered to a custom module;
- native behavior after credential expiry, disconnect and Symcon restart.

### 5.2 Option B: Custom MQTT splitter over WebSocket

A Navimow splitter could connect directly to the native WebSocket Client and
implement MQTT CONNECT, CONNACK, SUBSCRIBE, SUBACK, PUBLISH reception, PING and
disconnect handling.

This option is rejected for the current gate because it would:

- duplicate a protocol engine already present in Symcon;
- add binary framing and lifecycle complexity;
- expand security and recovery risk;
- require substantially more tests before any live use;
- provide no evidenced benefit while Option A is structurally compatible.

It may be reconsidered only if the native MQTT Client fails a reproducible
Navimow-specific requirement that cannot be configured safely.

### 5.3 Option C: External receive-only bridge

The private capture harness already proves that an external MQTT library can
receive the Navimow stream. A service could forward sanitized semantic events
to Symcon.

This remains a fallback because it:

- adds another deployed process and restart domain;
- creates another credential-storage boundary;
- complicates installation, diagnostics and upgrades;
- conflicts with the target of a native IP-Symcon module.

It may be selected only after a documented native-stack failure and a separate
security and operations decision.

### 5.4 Rejected long-running PHP socket loop

A blocking or long-running socket loop inside a PHP module is not a candidate.
It would couple transport lifetime to module execution, complicate shutdown
and restart, and bypass the supported I/O topology.

## 6. Decision Matrix

| Criterion | Native WS + MQTT | Custom MQTT splitter | External bridge |
| --- | --- | --- | --- |
| native IP-Symcon deployment | strong | strong | weak |
| current structural compatibility | proven | possible | proven outside Symcon |
| MQTT protocol implementation effort | low | high | medium |
| TLS and header support | native | native WS only | external library |
| credential boundary count | one installation | one installation | two runtimes |
| lifecycle ownership complexity | medium | high | high |
| no-publish enforceability | module contract plus tests | full custom control | bridge contract |
| current recommendation | selected conditionally | reserve | fallback |

## 7. Selected Topology

The preferred topology is:

```text
dedicated WebSocket Client
    -> dedicated MQTT Client
        -> existing Navimow Account
            -> existing Navimow children
```

The core transport chain is dedicated to one Navimow account. It must not
reuse or reconfigure an unrelated MQTT Client or socket from the installation.

The existing `NavimowAccount` remains:

- owner of OAuth and REST authentication;
- owner of MQTT credential retrieval;
- semantic gateway for its children;
- future consumer of receive-only MQTT messages;
- owner of REST/MQTT reconciliation and shadow diagnostics.

No new public `NavimowMqttBridge` module or GUID is justified yet.

The Account module is already a splitter toward its existing children. A later
implementation may additionally become a child of the native MQTT Client
without changing the existing account-to-device interface.

## 8. Required Account Interface Change

The inspected native MQTT Client exposes:

| Direction | Interface |
| --- | --- |
| MQTT Client receives from child | `{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}` |
| MQTT Client sends to child | `{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}` |

A later Account implementation is expected to:

- add the first interface as a parent requirement;
- add the second interface as an implemented receive interface;
- retain its current custom Navimow child interface unchanged.

The exact JSON envelope received from the MQTT Client is not declared proven
by metadata alone. Topic, payload, QoS, retained-message and packet-type fields
must be observed through a bounded live child probe before productive parsing
is connected.

No `module.json` change is made in this step.

## 9. Credential Ownership

Credential ownership is separated deliberately:

| Data | Runtime owner | Public persistence |
| --- | --- | --- |
| OAuth client secret | Navimow Account property | never in reports or fixtures |
| OAuth access/refresh token | Navimow Account internal state | never |
| WSS URL | dedicated WebSocket Client | never |
| Bearer upgrade header | dedicated WebSocket Client | never |
| MQTT username/password | dedicated MQTT Client | never |
| MQTT client ID | dedicated MQTT Client/account contract | value never |
| exact device topics | dedicated MQTT Client | templates only |

The Account obtains MQTT/WSS credentials only after successful OAuth and device
discovery. Neither a Device instance nor the Configurator may own or duplicate
these secrets.

## 10. Parent-Instance Ownership

Before productive implementation, the Account must distinguish:

- a core parent created for and owned by this Navimow Account;
- a manually assigned compatible parent;
- an unrelated pre-existing instance.

Automatic configuration may mutate only an explicitly owned chain.

The ownership contract must be idempotent:

1. find the already owned dedicated instances;
2. verify module GUIDs and connection order;
3. create only missing owned instances;
4. never adopt an arbitrary compatible instance silently;
5. never overwrite unrelated configuration;
6. report drift instead of repairing ambiguous ownership;
7. remove nothing automatically during a failed connection attempt.

The precise naming, identifiers and deletion behavior require a separate
implementation plan after the live spike.

## 11. Connection and Credential Lifecycle

The target lifecycle is:

```text
REST authenticated
    -> retrieve current MQTT/WSS credentials
    -> configure inactive owned MQTT instance
    -> configure inactive owned WebSocket instance
    -> activate WebSocket
    -> await MQTT connection/subscription evidence
    -> receive-only shadow mode
```

Recovery must remain bounded:

```text
healthy
    -> stale or disconnected
    -> bounded backoff
    -> refresh OAuth if required
    -> retrieve fresh MQTT/WSS credentials
    -> apply owned transport configuration
    -> reconnect once per recovery attempt
```

Routine OAuth token rotation must not tear down a healthy MQTT session merely
because a newer token exists. The latest credential set is required when the
transport must reconnect or when authentication fails.

The effect of changing pending core-instance properties without applying them,
and the exact reconnect caused by `IPS_ApplyChanges`, are not assumed. They
must be measured in the controlled live spike before lifecycle code is
designed.

## 12. Subscription Contract

The MQTT Client may contain only the four exact topics for each discovered
device:

```text
/downlink/vehicle/{DEVICE_ID}/realtimeDate/state
/downlink/vehicle/{DEVICE_ID}/realtimeDate/event
/downlink/vehicle/{DEVICE_ID}/realtimeDate/attributes
/downlink/vehicle/{DEVICE_ID}/realtimeDate/location
```

Requirements:

- no `#` subscription;
- no `+` subscription;
- deterministic topic ordering;
- no duplicate entry after ApplyChanges or restart;
- QoS fixed by the evidenced read-only contract;
- unknown topics rejected before payload parsing;
- retained messages classified explicitly;
- exact device identity checked against the topic and payload.

## 13. Structural No-Publish Rule

Although a child of the MQTT Client can technically send data to its parent,
the Navimow Account must contain no MQTT publish path.

The later implementation gate requires:

- no public MQTT send method;
- no action mapped to MQTT;
- no call to `SendDataToParent()` for MQTT;
- no uplink topic constant or template;
- static source scan for publish-related paths;
- offline receive-envelope tests;
- live counters proving zero publishes and zero commands.

REST remains the only command transport under the existing command safety
contracts.

## 14. Shadow and Authority Boundary

The first Symcon MQTT implementation phase is internal shadow mode.

It may:

- parse exact-topic receive envelopes;
- use fixture-backed direct state and battery fields;
- classify location as a rapid wake candidate;
- request a bounded read-only REST refresh;
- retain bounded connection, freshness and mismatch diagnostics.

It must not:

- call the existing productive state-update path from MQTT;
- create new public variables;
- change values of existing public variables directly;
- recreate variables or alter profiles;
- modify archive logging;
- archive location or coordinates;
- trigger any mower command.

Only a later evidence decision may promote selected MQTT fields. Such promotion
must update the existing variable objects and preserve their ObjectIDs and
archive histories.

## 15. Restart and Stale-State Contract

A later implementation must prove:

- exactly one owned transport chain after repeated ApplyChanges;
- exactly one set of exact subscriptions after restart;
- no command on startup or reconnect;
- no stale cached MQTT value promoted as current state;
- a local receipt timestamp independent of vendor timestamps;
- a bounded stale deadline per channel;
- REST polling continues during MQTT loss;
- location traffic cannot keep the direct state channel falsely healthy;
- a Symcon restart reconstructs shadow state only from fresh messages or REST.

Current evidence supports a candidate stale detector but not final timing.
Timing must follow observed state and location cadences and remain separately
configurable or internally reasoned.

## 16. Controlled Live Gate

No live mutation was performed in this step.

The next live gate must be planned before execution and must require explicit
authorization. It should:

1. capture a private baseline of relevant core instances;
2. retrieve fresh MQTT/WSS credentials privately;
3. create a dedicated temporary WebSocket Client;
4. configure binary mode, certificate verification and the Bearer header;
5. create a dedicated temporary MQTT Client as its child;
6. configure a unique client ID, credentials and exact subscriptions;
7. attach a bounded receive-only child probe;
8. observe connection, subscription and at least one allowed message;
9. record the actual child receive envelope without public secrets;
10. prove zero publish and zero mower command attempts;
11. deactivate and delete every temporary object;
12. verify the post-test instance inventory equals the baseline.

The spike must stop on:

- TLS or WebSocket upgrade failure;
- MQTT authentication rejection;
- unexpected topic;
- unknown or oversized receive envelope;
- any indication of publish;
- inability to prove cleanup ownership.

No productive variable or archive inspection is needed beyond confirming that
the Navimow module remains untouched.

## 17. Gate Matrix

| Gate | Result |
| --- | --- |
| native WebSocket custom headers | PASS |
| native WebSocket binary mode | PASS |
| native TLS verification setting | PASS |
| native MQTT credentials and exact subscriptions | PASS |
| WebSocket implements MQTT-required parent interface | PASS |
| Navimow WSS upgrade in Symcon | PENDING LIVE GATE |
| MQTT CONNECT and subscriptions over WSS | PENDING LIVE GATE |
| custom-child receive envelope | PENDING LIVE GATE |
| restart and credential recovery | PENDING LATER GATE |
| receive-only shadow implementation | NO-GO UNTIL LIVE GATE |
| productive MQTT variable authority | NO-GO |
| MQTT commands/publish | PROHIBITED |

## 18. Risks and Open Questions

### R-91-01: Header update may reconnect the WebSocket

Applying a new Bearer header may interrupt a healthy session. The native
instance behavior and safe timing must be measured.

### R-91-02: MQTT credential lifetime is not established

The credential endpoint supplies current values, but their independent expiry
and rotation behavior remain unknown.

### R-91-03: Native MQTT child envelope is not fixture-backed

The parser must not be wired to guessed envelope field names.

### R-91-04: Core instance status may not distinguish every failure

WebSocket, MQTT authentication and subscription errors require separate
diagnostic evidence.

### R-91-05: Automatic parent ownership can damage unrelated installations

The productive implementation must never mutate a compatible instance merely
because it is available.

### R-91-06: Multiple devices expand the topic set

The exact-topic list must remain bounded, deterministic and account-owned.

### Open questions

- Does the native MQTT Client send CONNECT only after the WebSocket reaches
  binary-ready state?
- What exact child envelope represents MQTT PUBLISH reception?
- How are retained messages and QoS represented?
- Which core instance statuses and messages identify upgrade, MQTT auth and
  subscription failures?
- Does a healthy MQTT session survive routine OAuth expiry at the WebSocket
  layer?
- Which configuration application order avoids duplicate reconnects?
- Can temporary live objects be removed with a fully verified baseline restore?

## 19. Architecture Decisions

### AD-NAV-332: Select the native WebSocket-to-MQTT chain

**Decision:** Prefer the native WebSocket Client as parent of the native MQTT
Client.

**Rationale:** Installed module metadata proves interface compatibility and the
native properties cover the evidenced Navimow transport requirements.

**Consequence:** Selection remains conditional until a controlled live
Navimow connection passes.

### AD-NAV-333: Retain Navimow Account as semantic gateway

**Decision:** Do not introduce a new public MQTT bridge module yet.

**Rationale:** The Account already owns authentication and the existing child
data path.

**Consequence:** A later implementation may extend its parent interface while
preserving its current child interface.

### AD-NAV-334: Dedicate one core transport chain per account

**Decision:** Never reuse or reconfigure unrelated WebSocket or MQTT
instances.

**Rationale:** Credentials, exact topics and lifecycle are account-scoped.

**Consequence:** Idempotent ownership and drift detection are mandatory before
automatic creation is implemented.

### AD-NAV-335: Enforce receive-only operation structurally

**Decision:** Add no MQTT publish method, path, topic or action.

**Rationale:** MQTT is admitted only for state reception and wake evidence.

**Consequence:** REST remains the sole command transport.

### AD-NAV-336: Defer reconnect implementation until native behavior is measured

**Decision:** Do not infer property-application or reconnect semantics.

**Rationale:** An unnecessary reconnect during token rotation could reduce
reliability and create duplicate sessions.

**Consequence:** The live spike records connection and configuration behavior
before lifecycle code is designed.

### AD-NAV-337: Keep custom and external transports as gated fallbacks

**Decision:** Reject custom MQTT framing and an external bridge for the current
implementation path.

**Rationale:** Neither is justified while the native topology is compatible.

**Consequence:** A fallback requires a reproducible native failure and a new
architecture decision.

### AD-NAV-338: Preserve REST authority and all existing variable identities

**Decision:** The topology spike and first MQTT implementation remain shadow
only.

**Rationale:** Transport lifecycle and reconciliation are not yet proven in
Symcon.

**Consequence:** Existing variables, ObjectIDs, profiles and archive logging
remain unchanged.

## 20. Decision

**Native WebSocket Client plus native MQTT Client: CONDITIONAL GO.**

**Installed interface compatibility: PROVEN.**

**Required native configuration capability: PROVEN.**

**Navimow WSS/MQTT operation in Symcon: NOT YET PROVEN.**

**Productive MQTT implementation: NO-GO.**

**Productive MQTT variable updates: NO-GO.**

**MQTT publish and mower commands: PROHIBITED.**

**Productive PHP changes in this step: NONE.**

**Existing variables and archive logging: UNCHANGED.**

## 21. Recommended Next Step

Create:

```text
92-native-mqtt-wss-symcon-live-spike-plan.md
```

That plan should define the exact private credential preparation, temporary
instance topology, receive-only child probe, evidence bounds, stop conditions,
rollback and baseline-equality checks before any authorized Symcon mutation is
performed.
