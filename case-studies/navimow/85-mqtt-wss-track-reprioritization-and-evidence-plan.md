# 85 MQTT/WSS Track Reprioritization and Evidence Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Read-only MQTT/WSS research promoted to next active implementation track
**Date:** 2026-07-27
**Scope:** Reorder the roadmap and define evidence gates without connecting or changing runtime code

## 1. Purpose

This step promotes MQTT/WSS reception from a deferred research item to the next
active Navimow engineering track.

It defines:

- the operational reason for the change;
- the current public transport evidence;
- the exact read-only topic boundary;
- credential, reconnect and privacy rules;
- REST/MQTT reconciliation;
- a staged IP-Symcon integration sequence;
- the conditions under which MQTT may update existing variables.

No MQTT credentials are retrieved, no broker connection is made, no topic is
subscribed, no message is published, and no productive PHP code is changed.

## 2. Why the Roadmap Changes

Step 51 deferred MQTT until an evidenced operational need existed. That
condition is now met:

- regular REST polling did not record short Running-to-Docking transitions;
- timely detection of scheduled departures is desired;
- increasing REST frequency while active improves latency but still depends on
  first detecting activity;
- the station-power hint is useful but installation-specific and indirect;
- current public implementations now expose a concrete WSS credential and
  topic contract;
- the user explicitly prioritizes MQTT reception.

This is a controlled supersession of the Track D ordering in step 51. It does
not supersede the OAuth release gate, Store deferral or command safety gates.

## 3. Reviewed Source Revisions

| Source | Revision | Role |
| --- | --- | --- |
| official [`segwaynavimow/navimow-sdk`](https://github.com/segwaynavimow/navimow-sdk) | `6596aa0a65dcf05ed248da87c36975f2ea236ab8` | credential endpoint, WSS setup and official topic set |
| community [`TA2k/ioBroker.navimow`](https://github.com/TA2k/ioBroker.navimow) | `8f8f00d7cdac258ea70437c1bb0ed4f6e69e4a42` | independent WSS client, location topic and REST fallback |
| community [`ilguala/navimow_pro`](https://github.com/ilguala/navimow_pro) | `ff63db98ee5154062aa3c00c811d0a90a3a38c61` | confirms its private protocol has no push and is not an MQTT source |
| [IP-Symcon MQTT Client documentation](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-client/) | reviewed 2026-07-27 | native MQTT capabilities and documented Client Socket topology |
| [IP-Symcon module data-flow documentation](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/datenfluss/) | reviewed 2026-07-27 | WebSocket I/O data interface and module topology |

## 4. Credential and WSS Contract

The Smart Home REST API exposes:

```text
GET /openapi/mqtt/userInfo/get/v2
Authorization: Bearer <OAuth access token>
```

Current source implementations consume:

| Field | Purpose |
| --- | --- |
| `mqttHost` | broker host |
| `mqttUrl` | WSS URL or WebSocket path |
| `userName` | MQTT username |
| `pwdInfo` | MQTT password |

The observed WSS setup uses:

```text
transport: MQTT over WebSocket Secure
port: 443
TLS certificate verification: required
WebSocket upgrade header: Authorization: Bearer <OAuth access token>
MQTT CONNECT credentials: userName / pwdInfo
client id: unique web-style client identifier
keepalive: 2400 seconds in both reviewed implementations
```

All four credential values and the complete WSS URL are private runtime data.
They must never appear in public fixtures, debug output or reports.

## 5. Read-Only Topic Contract

The first capture may subscribe only to exact topics for a discovered device:

```text
/downlink/vehicle/{DEVICE_ID}/realtimeDate/state
/downlink/vehicle/{DEVICE_ID}/realtimeDate/event
/downlink/vehicle/{DEVICE_ID}/realtimeDate/attributes
/downlink/vehicle/{DEVICE_ID}/realtimeDate/location
```

The official SDK subscribes to `state`, `event` and `attributes`. The current
ioBroker adapter additionally receives `location`, including position and
mowing progress.

The first implementation must not use:

```text
/downlink/vehicle/{DEVICE_ID}/#
/downlink/vehicle/+/...
#
```

It must never publish to any topic. MQTT command publishing is outside this
track even if an upstream library exposes it.

## 6. Source Confidence Findings

The available evidence is strong enough for a read-only capture, but not yet
for productive state authority:

- the official SDK defines exact device topics and WSS credentials;
- ioBroker independently uses the same topic family;
- ioBroker keeps REST polling active because MQTT state pushes can be
  unreliable;
- ioBroker adds a three-minute active-location stale detector and rate-limited
  reconnect;
- the official SDK's cloud parser expects a different three-segment
  `navimow/...` topic shape than the five-segment downlink topics delivered by
  its MQTT client;
- upstream reports describe connection and message-reception instability.

The parser mismatch and fallback behavior require real account-specific
evidence before payload mapping is trusted.

## 7. Initial Payload Scope

The first capture records bounded examples of:

| Channel | Initial purpose |
| --- | --- |
| `state` | discover state, battery and timestamp fields |
| `event` | discover event envelope without acting on it |
| `attributes` | discover slowly changing attribute envelope |
| `location` | confirm activity, vehicle state and progress shape |

For the first productive phase, only these candidate facts are relevant:

- mower state;
- battery level;
- message freshness;
- online/realtime channel health.

Coordinates, map reconstruction, path history, coverage and garden geometry are
explicitly excluded.

## 8. Privacy and Fixture Rules

Raw evidence belongs below:

```text
private/navimow-capture/output/mqtt/raw/
```

Sanitized candidates belong below:

```text
private/navimow-capture/output/mqtt/sanitized/
```

Public fixtures may later be promoted to:

```text
case-studies/navimow/fixtures/mqtt/
```

Sanitization must:

- replace device and account identifiers with deterministic placeholders;
- remove MQTT username, password, OAuth token, WSS host and path;
- preserve topic channel and JSON types;
- remove or normalize absolute timestamps;
- remove real position coordinates and path arrays;
- exclude map, boundary, station and garden geometry;
- retain only the smallest payload needed for parser tests.

High-frequency streams must be reduced to representative single-message
fixtures. Raw captures must never be committed.

## 9. Connection and Recovery Contract

The passive client must:

1. obtain credentials only through the authenticated REST endpoint;
2. create one MQTT session per account;
3. use a unique, persisted client ID unless live evidence requires rotation;
4. verify TLS certificates;
5. subscribe only after a successful connection;
6. record subscription acknowledgement separately per topic;
7. use a bounded exponential reconnect delay;
8. stop rapid reconnect after repeated failures;
9. refresh OAuth and MQTT credentials before reconnect when authentication is
   rejected or credentials are stale;
10. avoid reconnecting a healthy session solely because an OAuth token rotated;
11. prevent duplicate clients and duplicate subscriptions after ApplyChanges or
    restart;
12. expose connection, last-message and error diagnostics without secrets.

No automatic command may be triggered by connect, disconnect, message,
credential refresh or recovery.

## 10. REST/MQTT Reconciliation

REST remains authoritative during the evidence and shadow phases.

Each MQTT message must be classified by:

- recognized device;
- recognized exact topic;
- valid UTF-8 and JSON;
- supported object or array shape;
- payload timestamp if present;
- local receipt timestamp;
- duplicate or out-of-order status;
- field-level mapping confidence.

The initial precedence is:

| Situation | Decision |
| --- | --- |
| validated fresh MQTT state | store as shadow observation only |
| periodic REST success | update existing productive variables |
| MQTT/REST agreement | increase mapping confidence |
| MQTT/REST disagreement | retain REST value and record bounded diagnostic |
| MQTT stale or disconnected | continue REST fallback |
| REST stale but MQTT healthy | do not silently promote authority during shadow phase |
| unknown MQTT field/state | retain bounded diagnostic; do not mutate public contract |

Only a later decision may allow fresh, fixture-backed MQTT fields to update the
existing variables.

## 11. Existing Variable and Archive Contract

MQTT does not justify new public variables in the first phase.

The existing device variables remain:

```text
VehicleState
Online
BatteryLevel
LastStatusUpdate
LastCommand
LastCommandAt
LastCommandResult
LastCommandError
```

When MQTT authority is eventually approved:

- the same variables and Idents must be updated;
- variable objects must not be deleted or recreated;
- variable types and profiles must remain stable;
- user-enabled Archive Control logging must remain unchanged;
- `LastStatusUpdate` needs a documented source/freshness meaning before it can
  represent both transports.

Connection diagnostics should first use internal attributes and bounded debug
output. Public diagnostics require a separate variable-contract decision.

## 12. IP-Symcon Architecture Spike

IP-Symcon documents:

- a native MQTT Client with username/password and topic subscriptions;
- a Client Socket as its normal parent;
- a WebSocket Client I/O with custom headers available in current Symcon
  versions;
- standard module data flow through parent and child interfaces.

The documentation does not establish that the native MQTT Client can be
directly stacked on the WebSocket Client while preserving MQTT framing and the
required Bearer upgrade header.

The architecture spike must compare:

| Option | Gate |
| --- | --- |
| native MQTT Client plus supported WSS parent | preferred if proven compatible |
| dedicated Navimow MQTT splitter over WebSocket I/O | acceptable if MQTT framing and lifecycle are bounded |
| long-running PHP/userland socket loop | reject unless Symcon lifecycle safety is demonstrated |
| external bridge/service | fallback only; weakens native-module objective |

No new module GUID or productive transport class is approved until this spike
is complete.

## 13. Staged Work Packages

### WP-MQTT-01: Private capture procedure

Create `86-mqtt-wss-private-capture-procedure.md` and a private executable tool
that:

- reuses the existing OAuth session without exposing it;
- retrieves MQTT credentials;
- connects read-only;
- subscribes to the four exact device topics;
- captures for a bounded interval;
- publishes nothing;
- produces raw and sanitized candidates separately.

### WP-MQTT-02: Docked passive capture

Connect while the mower is docked and supervised. Capture connection,
subscription and any naturally arriving messages without issuing a command.

### WP-MQTT-03: Natural activity capture

During a later normal scheduled or official-app-initiated mowing run, observe:

- first Running message;
- location cadence;
- natural Docking;
- final Docked;
- REST/MQTT timing and agreement.

No Symcon or capture-tool command may start, pause, resume or dock the mower.

### WP-MQTT-04: Fixture and parser contract

Promote minimal sanitized fixtures and implement an offline parser with:

- exact topic allowlisting;
- payload bounds;
- field/type validation;
- duplicate and out-of-order handling;
- coordinate exclusion;
- unknown-field tolerance.

### WP-MQTT-05: Symcon transport spike

Prove the WSS/MQTT lifecycle without updating productive mower variables.

### WP-MQTT-06: Shadow-mode pilot

Publish a passive subscriber that records only bounded internal diagnostics and
compares MQTT with REST.

### WP-MQTT-07: Authority decision

After stable observation, decide which validated MQTT fields may update the
existing variables. REST polling remains as reconciliation and fallback.

## 14. Readiness Gates

| Gate | Current decision |
| --- | --- |
| credential endpoint identified | PASS |
| WSS/TLS/auth roles identified | PASS for capture |
| exact state/event/attributes topics identified | PASS |
| exact location topic independently identified | PASS for capture |
| wildcard subscription | NO-GO |
| MQTT publish/commands | NO-GO |
| real credential response fixture | BLOCKED |
| successful private WSS connection | BLOCKED |
| sanitized channel fixtures | BLOCKED |
| offline parser | BLOCKED |
| Symcon-native transport topology | BLOCKED |
| productive variable updates from MQTT | NO-GO |
| removal of REST polling | NO-GO |

## 15. Architecture Decisions

### AD-NAV-296: Promote MQTT/WSS from deferred to next active track

**Decision:** Begin read-only MQTT evidence work before further active Start or
Stop implementation.

**Rationale:** Operational latency need and public transport evidence now
satisfy the step 51 trigger.

**Consequence:** MQTT capture planning becomes step 86.

### AD-NAV-297: Separate reception from authority

**Decision:** Prove connection and parse behavior before MQTT can update public
variables.

**Rationale:** Receiving a message does not prove freshness, completeness or
field semantics.

**Consequence:** The first Symcon integration runs in shadow mode.

### AD-NAV-298: Keep REST as reconciliation and fallback

**Decision:** Do not replace periodic REST polling.

**Rationale:** Current upstream implementations report missing or stale MQTT
streams and retain HTTP polling.

**Consequence:** MQTT improves latency without becoming a single point of
failure.

### AD-NAV-299: Subscribe only to exact per-device topics

**Decision:** Prohibit broad wildcard subscriptions.

**Rationale:** Exact topics minimize privacy exposure and unintended account
scope.

**Consequence:** Discovery must complete before subscription.

### AD-NAV-300: Prohibit all MQTT command publishing

**Decision:** Use MQTT solely as a downlink receive path.

**Rationale:** Existing commands have REST safety, response and verification
contracts; MQTT publishing has none.

**Consequence:** Command integration and MQTT reception remain independent.

### AD-NAV-301: Exclude location geometry from the first contract

**Decision:** Use location only to study activity and freshness, not to expose
coordinates or maps.

**Rationale:** Coordinates reveal private property geometry and are unnecessary
for timely state detection.

**Consequence:** Public fixtures omit real coordinates.

### AD-NAV-302: Preserve variable identity and archive configuration

**Decision:** Reuse existing variables if MQTT authority is later approved.

**Rationale:** Their ObjectIDs and logging history are installation contracts.

**Consequence:** No delete-and-recreate migration is permitted.

### AD-NAV-303: Require a Symcon transport topology spike

**Decision:** Do not assume the native MQTT Client can consume MQTT over the
required authenticated WSS connection.

**Rationale:** Current documentation establishes the components separately but
not the required stack.

**Consequence:** Productive transport implementation waits for an isolated
compatibility proof.

## 16. Decision

**MQTT/WSS reception priority: ADVANCED.**

**Current mode: READ-ONLY RESEARCH AND PRIVATE EVIDENCE.**

**Productive MQTT variable updates: NO-GO.**

**MQTT commands: PERMANENTLY OUT OF SCOPE FOR THIS TRACK.**

The current module, published pilot tag, commands, variable objects and archive
settings remain unchanged.

## 17. Recommended Next Step

Create `86-mqtt-wss-private-capture-procedure.md` and its private, bounded,
receive-only terminal harness. The first live run should connect while docked
and issue no mower command.
