# 146 Native MQTT Corrected Ingress Review and Passive Pilot Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Corrected ingress accepted; passive pilot planned but blocked by
recovery hardening
**Date:** 2026-07-28
**Scope:** Review step-145 evidence and define the bounded path to an opt-in
receive-only MQTT pilot

## 1. Purpose

Step 145 proved in one supervised live run:

- the retained native MQTT Client was migrated to exact `Topic` plus `QoS`;
- Core MQTT and WebSocket reached healthy status;
- the productive Receiver and known-good sibling both received one location
  event;
- the productive Receiver accepted and forwarded that event;
- disconnect, credential cleanup and probe deletion completed;
- Symcon returned to corrected productive `main`;
- all 14 variables and five Archive Control logging contracts remained
  unchanged.

This step decides what that evidence permits and what remains unsafe.

It defines:

1. the accepted MQTT support boundary;
2. missing recovery behavior before persistent activation;
3. an offline hardening sequence;
4. a bounded private passive-pilot matrix;
5. explicit publication, Symcon and live-operation gates;
6. stop, disable and cleanup contracts.

This step changes no productive PHP code, publishes nothing and performs no
Symcon or broker operation.

## 2. Evidence Decision

### Accepted

| Capability | Evidence | Decision |
|---|---|---|
| native WSS/MQTT connection | live Core status `102/102` | accepted |
| exact subscriptions | 4 canonical `Topic`/`QoS` entries | accepted |
| productive Receiver ingress | one accepted and forwarded event | accepted |
| exact legacy migration | legacy before Connect, canonical after | accepted |
| receive-only boundary | zero publish and command calls | accepted |
| runtime cleanup | credentials empty and transport inactive | accepted |
| variable and archive continuity | hashes unchanged | accepted |

### Not yet accepted

| Capability | Missing evidence | Decision |
|---|---|---|
| persistent operation | only one 2.4-second live window | blocked |
| Symcon restart recovery | no corrected persistent restart run | blocked |
| broker disconnect recovery | no bounded reconnect evidence | blocked |
| OAuth token rotation | no live MQTT header/credential rotation | blocked |
| prolonged diagnostics | no multi-transition observation | blocked |
| MQTT state authority | semantics remain partial and mixed | prohibited |
| MQTT publish | no requirement or evidence | prohibited |

## 3. Authority and Safety Boundary

The passive pilot must preserve:

- REST as the only authority for public Device variables;
- MQTT only as a fast private hint for targeted REST reconciliation;
- no direct MQTT write to `VehicleState`, `Online`, `BatteryLevel` or
  `LastStatusUpdate`;
- no MQTT publish API or broker uplink;
- no Start, Pause, Resume, Dock or Stop invocation from MQTT lifecycle code;
- explicit Account opt-in, default `false`;
- one owned retained WebSocket/MQTT/Receiver chain;
- no automatic Core creation or reparenting;
- no retry of mower commands;
- bounded diagnostics without topics, payloads or credentials;
- preservation of all existing variable identities and archive settings.

MQTT failure must never stop normal REST polling.

## 4. Current Implementation Review

The current implementation already provides:

- explicit `EnableMqttShadow`;
- symmetric Account/Receiver pairing;
- ownership and configuration hashes;
- single-attempt `ConnectMqttShadow()`;
- `DisconnectMqttShadow()` with credential cleanup;
- rollback after connection failure;
- strict envelope and payload parsing;
- bounded Receiver and Account diagnostics;
- bounded MQTT-to-REST reconciliation;
- no immediate REST retry;
- no MQTT publish path.

The current implementation does not yet provide a complete persistent
lifecycle:

1. `ApplyChanges()` clears MQTT shadow and pending reconciliation state.
2. When MQTT remains enabled, `ApplyChanges()` does not deliberately clean and
   reconstruct the transport.
3. `initializeMqttLifecycle()` classifies state but does not connect.
4. `ProcessMqttLifecycle()` records Core status once and disables its timer.
5. No bounded reconnect is scheduled after an unexpected Core disconnect.
6. A successful OAuth token refresh does not rotate the WebSocket
   authorization header or private MQTT credentials.
7. No stable-active interval resets a reconnect failure episode.

Persistent pilot activation before these gaps are closed would leave restart
and token behavior ambiguous.

## 5. Required Recovery Model

### Lifecycle states

The existing private lifecycle should be extended or normalized to cover:

```text
Disabled
WaitingForAuthentication
WaitingForPairing
Ready
Connecting
ShadowActive
ReconnectScheduled
Disconnected
ReauthenticationRequired
ConfigurationError
```

No new public Device state or profile is required.

### Lifecycle timer

When MQTT is enabled and configuration is valid:

- healthy transport observation interval: 60 seconds;
- reconnect timer uses the same owned lifecycle timer;
- only one timer wake may start one connection attempt;
- every timer run resets its own interval before work;
- semaphore protection remains mandatory;
- normal REST polling remains independent.

When MQTT is disabled, unauthenticated or invalid:

- lifecycle and reconciliation timers are inactive;
- WebSocket is inactive;
- headers, MQTT username and MQTT password are empty;
- pending MQTT shadow state is cleared;
- stable non-secret ownership metadata may remain.

## 6. Restart Contract

On `ApplyChanges()` or Symcon service restart with MQTT opt-in enabled:

1. inspect ownership and topology without creating Core instances;
2. force the owned WebSocket inactive;
3. clear transient WebSocket headers and MQTT credentials;
4. clear private MQTT shadow and pending reconciliation state;
5. retain the stable Client ID and canonical subscriptions;
6. require usable OAuth authentication;
7. schedule one delayed lifecycle attempt;
8. do not connect inline inside `ApplyChanges()`;
9. do not send a mower command or targeted REST wake during reconstruction.

Selected initial delay:

```text
5 seconds
```

This keeps configuration application bounded and avoids competing with module
startup.

## 7. Reconnect Contract

Reconnect is safe only because the transport is receive-only.

Per disconnect episode:

```text
attempt 1: after 60 seconds
attempt 2: after 300 seconds
attempt 3: after 900 seconds
```

Rules:

- no immediate retry;
- maximum three attempts per episode;
- one credential request and one broker attempt per retry;
- cleanup before every attempt;
- no overlap between attempts;
- configuration, ownership and authentication revalidated every time;
- a configuration or ownership error is never retried;
- reauthentication-required is never retried;
- after the third failure, remain `Disconnected` until an explicit operator
  action or a new validated authentication event;
- reset the failure episode only after 15 minutes of continuously healthy Core
  status.

The exact delays are pilot constants, not public configuration.

## 8. OAuth and Credential Rotation

MQTT uses private credentials plus a WebSocket Authorization header derived
from the current access token.

After a successful OAuth token refresh while MQTT is enabled:

1. do not patch credentials into an active connection;
2. schedule one controlled rotation;
3. disconnect and clear the owned transport;
4. request fresh MQTT credentials with the refreshed access token;
5. write the current Authorization header;
6. connect once;
7. record the rotation result without secret values.

If token refresh fails:

- do not reconnect with known-stale authentication;
- retain REST authentication classification;
- disable and clean MQTT transport;
- wait for the existing bounded OAuth retry or explicit reauthorization;
- never expose token or credential material in diagnostics.

## 9. Diagnostics Contract

Reuse the existing bounded private attributes:

```text
MqttLifecycleRegistry
MqttStatistics
MqttErrorHistory
```

Required bounded fields:

```text
lifecycle state
last Core status
last observation time
connection attempts
connection successes
connection failures
unexpected disconnects
reconnect attempts
reconnect exhausted episodes
credential rotations
last transition reason code
healthy-since timestamp
next-attempt timestamp
```

Rules:

- counters saturate;
- error history remains fixed-capacity;
- reason codes come from a closed allowlist;
- no endpoint, topic, payload, token, password, header, Client ID, Device ID or
  ObjectID;
- timestamps and counts are private diagnostics, not new public variables.

## 10. Deterministic Offline Work Packages

### `PP-1`: Restart reconstruction

Prove:

- enabled restart cleans transient credentials;
- no inline Connect occurs in `ApplyChanges()`;
- exactly one delayed attempt is scheduled;
- repeated `ApplyChanges()` remains idempotent;
- disabled restart stays fully inactive.

### `PP-2`: Healthy lifecycle observation

Prove:

- Core status `102` records `ShadowActive`;
- the next observation remains scheduled;
- no credential request occurs while healthy;
- 15 stable minutes reset the reconnect episode.

### `PP-3`: Bounded reconnect

Prove:

- unexpected unhealthy Core status records one disconnect episode;
- attempts occur only at 60, 300 and 900 seconds;
- each attempt cleans first and connects once;
- the fourth attempt does not exist;
- configuration and authentication failures are not retried.

### `PP-4`: Token rotation

Prove:

- successful OAuth refresh schedules one rotation;
- rotation uses the refreshed access token;
- old headers and credentials are cleared first;
- failure leaves the transport inactive and secret-free;
- refresh retry does not multiply MQTT attempts.

### `PP-5`: Disable and reset

Prove:

- disabling MQTT stops both MQTT timers;
- credentials and headers are empty;
- pending shadow and reconciliation entries are cleared;
- Receiver, Core topology and stable subscriptions remain;
- REST polling remains active;
- repeated disable is idempotent.

### `PP-6`: Compatibility

Prove:

- all 14 variables retain identity and metadata;
- five Archive Control logging contracts remain untouched;
- actions and command verification remain unchanged;
- no MQTT public variable is introduced;
- complete REST and MQTT regression gates pass.

## 11. Implementation Boundary

The hardening increment should remain scoped to:

```text
distribution/NavimowAccount/module.php
tests/mqtt-transport-lifecycle.php
```

Additional focused test files are allowed only when the existing lifecycle
harness would become materially harder to review.

Avoid changes to:

- Device variables or action contract;
- Configurator behavior;
- Receiver envelope contract;
- MQTT parsers and fixtures already closed by step 145;
- module GUIDs and metadata;
- public profiles;
- command implementation.

No productive PHP is changed in this planning step.

## 12. Publication and Live Gates

Approval of this plan authorizes none of the following gates.

### Gate A: Offline recovery implementation

Required authorization:

```text
Offline-Implementierung der MQTT-Pilot-Recovery-Härtung freigegeben.
```

Permits only source, tests, fixtures and SAEF documentation. No publication or
Symcon mutation.

### Gate B: Productive publication

After Gate A passes:

```text
Veröffentlichung der MQTT-Pilot-Recovery-Härtung auf main freigegeben.
```

Permits the exact validated productive increment, remote verification and no
tag.

### Gate C: Symcon compatibility update

After Gate B passes:

```text
Symcon-Update auf die MQTT-Pilot-Recovery-Härtung mit read-only Prüfung freigegeben.
```

Permits one Module Control update and repeated compatibility projections while
MQTT remains disabled.

### Gate D: Passive pilot activation

After Gate C passes:

```text
Einmalige Aktivierung des receive-only MQTT-Piloten mit kontrolliertem Connect und Disable-Fallback freigegeben.
```

Permits:

- one explicit opt-in;
- one initial Connect;
- no MQTT publish;
- no mower command;
- immediate disable and cleanup on failure.

### Gate E: Restart observation

After stable ingress is observed:

```text
Ein beaufsichtigter Symcon-Restart während des receive-only MQTT-Piloten ist freigegeben.
```

Permits one service restart and read-only observation of cleanup, delayed
reconnection and continued REST operation.

### Gate F: Natural token-rotation observation

After restart passes:

```text
Passive Beobachtung der natürlichen OAuth- und MQTT-Credential-Rotation freigegeben.
```

Permits no induced expiry and no manual token manipulation.

### Gate G: Pilot closure

At completion or stop:

```text
Abschluss des MQTT-Piloten mit Disable, Credential-Cleanup und Statusbericht freigegeben.
```

Persistent MQTT activation is not a release default even after a passing
private pilot.

## 13. Passive Pilot Matrix

The pilot is event-based with a maximum duration of seven days.

| ID | Observation | Required evidence |
|---|---|---|
| `MQP-01` | initial activation | one Connect, healthy Core, accepted Receiver ingress |
| `MQP-02` | scheduled departure | MQTT hint precedes or accompanies targeted REST update |
| `MQP-03` | normal return | Running/Docking/Docked REST sequence remains authoritative |
| `MQP-04` | repeated scheduled cycle | no duplicate REST burst or state leakage |
| `MQP-05` | one Symcon restart | cleanup then one delayed reconnect, no duplicate topology |
| `MQP-06` | natural OAuth refresh | one controlled MQTT credential rotation |
| `MQP-07` | manual disable | complete credential cleanup, REST continuity |

Target live sample:

- at least three normal scheduled mower transitions;
- at least one Docked idle period;
- one planned Symcon restart;
- one naturally occurring OAuth refresh;
- one final manual disable.

Do not create artificial mower runs solely to reach the sample count.

## 14. Pilot Evidence Contract

Record only:

```text
module commit
observation ID
rounded start and end timestamps
Core status sequence
bounded lifecycle reason codes
connection and reconnect deltas
Receiver ingress and forwarding deltas
MQTT-to-REST reconciliation counters
REST state sequence
authentication classification
cleanup booleans
variable and archive contract hashes
```

Never record:

- access or refresh tokens;
- MQTT username or password;
- Authorization headers;
- endpoint or private topic;
- raw envelope or payload;
- Client ID or Device ID;
- ObjectIDs;
- map, coordinate or garden data.

Machine-readable installation evidence remains below `private/`. Public
reports contain sanitized aggregates only.

## 15. Stop Conditions

Disable and clean MQTT immediately when:

- WebSocket or MQTT credentials remain after a failed attempt;
- Core topology or ownership validation changes;
- more than one concurrent Connect occurs;
- reconnect exceeds the fixed attempt budget;
- REST polling stops or public variables become MQTT-authoritative;
- duplicate targeted REST bursts violate the 30-second coalescing contract;
- authentication becomes reauthorization-required;
- any publish or mower-command path is observed;
- variable identities or Archive Control settings change;
- diagnostics expose private material;
- physical mower behavior becomes unexpected during an observed transition.

Failure of MQTT alone is not permission to alter mower behavior.

## 16. Pilot Pass Criteria

The private pilot passes only when:

- every required observation in `MQP-01` through `MQP-07` passes;
- restart causes one delayed reconstruction without duplicate Core instances;
- natural token refresh causes one controlled credential rotation;
- reconnect behavior remains within its attempt and delay budget;
- at least three scheduled transitions produce bounded ingress;
- targeted REST reconciliation remains coalesced;
- REST values remain authoritative;
- no MQTT publish or mower command occurs;
- final disable leaves credentials empty and timers inactive;
- all 14 variables and five logging contracts remain unchanged.

A shorter duration may pass if all event criteria occur naturally. Seven days
without all events is incomplete, not automatically successful.

## 17. Architecture Decisions

### AD-NAV-514: Insert recovery hardening before persistent activation

**Decision:** Do not activate the current implementation as a persistent
pilot.

**Reason:** Correct ingress does not cover restart, disconnect or token
rotation.

### AD-NAV-515: Retry receive-only transport, never device actions

**Decision:** Permit a fixed three-attempt reconnect episode only for the
receive-only transport.

**Reason:** Reconnecting a read path is idempotent when cleanup and ownership
are enforced; mower commands are not part of this state machine.

### AD-NAV-516: Reconnect after token rotation

**Decision:** Replace active MQTT credentials through cleanup and one fresh
connection rather than in-place mutation.

**Reason:** It gives one auditable credential boundary and prevents mixed old
and new authentication.

### AD-NAV-517: Make the pilot event-based

**Decision:** Require transitions, restart, natural token refresh and disable,
with seven days only as a cap.

**Reason:** Elapsed time alone does not prove lifecycle behavior.

### AD-NAV-518: Preserve REST authority through the pilot

**Decision:** MQTT remains a reconciliation hint only.

**Reason:** Partial MQTT payload semantics and long-term completeness are not
yet proven.

## 18. Current Gate Decision

| Gate | Decision |
|---|---|
| corrected schema and ingress | PASS |
| runtime and Git restoration | PASS |
| passive-pilot concept | APPROVED |
| recovery hardening implementation | CLOSED |
| productive publication | CLOSED |
| Symcon update | CLOSED |
| persistent receive-only activation | BLOCKED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 19. Recommended Next Step

Create:

```text
147-native-mqtt-passive-pilot-recovery-hardening-implementation.md
```

Execute `PP-1` through `PP-6` offline with deterministic clocks, fake Core
configuration and exact attempt-count assertions.

Required authorization:

```text
Offline-Implementierung der MQTT-Pilot-Recovery-Härtung freigegeben.
```
