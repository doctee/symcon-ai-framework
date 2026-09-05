# SAEF Step 389: Continuous Receive-Only Implementation Design

**Case study:** Navimow native IP-Symcon module

**Status:** Detailed implementation contract complete; productive code,
publication and live activation remain closed

**Date:** 2026-09-04

## 1. Purpose

Step 388 selected continuous, monitored and strictly receive-only MQTT and
position operation as the private target. It kept the existing bounded pilot
unchanged and required a separately implemented normal operating mode.

This step freezes:

- the exact property and migration contract;
- runtime ownership and registry schemas;
- lifecycle, safety-lease and circuit-breaker behavior;
- timer and cleanup ordering;
- user-facing operating variables and profiles;
- position-freshness and Local Map behavior;
- configuration-form and public-method behavior;
- the implementation fileset and synthetic test matrix; and
- the publication and live gate sequence.

It performs no productive PHP change, generated-distribution update,
publication, Symcon access, MQTT activation, credential retrieval, OAuth
action, restart or mower command.

## 2. Reuse Assessment

The current implementation already provides the required lower-level
building blocks:

- exact MQTT Receiver, MQTT Client and WebSocket ownership validation;
- an exact per-device subscription allowlist;
- credential retrieval and mapping;
- credential-first disconnect and rollback;
- a persistent MQTT lifecycle registry;
- `60/300/900`-second bounded transport reconnects;
- passive OAuth refresh and credential rotation;
- retained-Core restart reconciliation;
- bounded MQTT statistics and error history;
- position and task reducers;
- revision-bounded path retention;
- module-owned pilot deadline and cleanup state machines; and
- the SAEF `ConfigurationHash` helper already shipped in the Navimow fileset.

These components must be composed before adding behavior. No new SAEF-wide
helper or public framework API is justified.

One Navimow-local pure reducer is justified because lease, circuit, half-open
and stop transitions otherwise add another large implicit state machine to the
already stateful Account module:

```text
libs/Navimow/MqttContinuousOperationReducer.php
```

The class is an implementation-local library. It performs no Symcon calls,
network calls, credential handling or device action.

## 3. Fixed Implementation Scope

The productive candidate may modify only:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/distribution/NavimowAccount/form.json
case-studies/navimow/distribution/NavimowAccount/locale.json
case-studies/navimow/distribution/NavimowDevice/module.php
case-studies/navimow/distribution/libs/Navimow/Profiles.php
case-studies/navimow/distribution/libs/Navimow/LocalMapSvgRenderer.php
case-studies/navimow/candidate/LocalMapSvgRenderer.php
case-studies/navimow/distribution/libs/Navimow/MqttContinuousOperationReducer.php
case-studies/navimow/tools/check-mqtt-shadow.sh
case-studies/navimow/tests/mqtt-continuous-operation.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
case-studies/navimow/tests/mqtt-shadow-diagnostics.php
case-studies/navimow/tests/local-map-evidence-contract.php
case-studies/navimow/tests/local-map-device-lifecycle.php
case-studies/navimow/tests/local-map-svg-renderer.php
case-studies/navimow/tests/local-map-variable-stability.php
case-studies/navimow/tests/local-map-distribution-fileset.php
deployments/symcon/navimow-module.fileset.json
deployments/symcon/navimow-publication.json
```

The generated distribution may change only through the existing
manifest-driven build after the canonical sources pass.

No API client, REST command, MQTT parser, topic, module GUID, interface GUID,
existing variable Ident or Archive configuration belongs to this change.

## 4. Configuration Contract

### 4.1 Existing master switch

Retain the existing property unchanged:

```text
EnableMqttShadow: boolean, default false
```

It remains the only master enable/disable switch. `false` always means
credential-free REST-only operation regardless of every other property.

### 4.2 New operating policy

Add exactly one property:

```text
MqttOperatingMode: integer, default 1
```

Allowed values:

| Value | Symbolic mode | Meaning |
|---:|---|---|
| `1` | `BoundedPilot` | existing 300-second to 72-hour pilot contract |
| `2` | `ContinuousReceiveOnly` | renewable-lease normal private operation |

There is deliberately no enabled value `0`. Disabled state is represented only
by `EnableMqttShadow=false`.

Effective mode resolution is exact:

```text
EnableMqttShadow=false                         -> Disabled
EnableMqttShadow=true, MqttOperatingMode=1    -> BoundedPilot
EnableMqttShadow=true, MqttOperatingMode=2    -> ContinuousReceiveOnly
EnableMqttShadow=true, any other mode value   -> ConfigurationError
```

An invalid value must stop timers, reject connection startup and clear owned
credentials. It must never fall back to continuous mode.

### 4.3 Existing subordinate properties

Retain these properties and their defaults:

```text
MqttPilotMaximumDurationSeconds: integer, 300..259200
EnableMqttPositionDiagnostics:   boolean, default false
MqttReceiverInstanceId:          integer, default 0
```

`MqttPilotMaximumDurationSeconds` applies only to `BoundedPilot`. Continuous
mode ignores its value and uses fixed safety constants.

Position diagnostics remain optional at transport level. The intended private
installation will enable them because the Local Map and zone statistics need
position evidence, but the transport lifecycle must also remain valid with
position diagnostics disabled.

## 5. Migration Contract

The default `MqttOperatingMode=1` is the migration mechanism.

| Installed configuration before update | Effective behavior after update |
|---|---|
| MQTT disabled | remains disabled and credential-free |
| bounded pilot enabled | remains bounded by the existing pilot deadline |
| pilot closure pending | closure resumes before any mode evaluation |
| malformed mode value | configuration error and credential cleanup |

No update may infer continuous mode from:

- `EnableMqttShadow=true`;
- enabled position diagnostics;
- an active or historical pilot registry;
- existing MQTT credentials in Core instances;
- Local Map configuration; or
- previous private acceptance text.

Switching directly from an active bounded pilot to continuous mode first
completes the existing pilot cleanup and disables the master switch. The user
must then explicitly enable the continuous mode in a second saved
configuration.

Switching from continuous mode to bounded pilot similarly completes continuous
cleanup before a new pilot can start. Mode controllers never share an active
credential-bearing session.

## 6. Responsibility Split

### Account module

The Account owns:

- effective mode resolution;
- lifecycle and Core topology;
- credentials and their cleanup;
- lease and half-open timers;
- the continuous-operation registry;
- user-facing operating variables;
- position-freshness classification; and
- all mode transitions.

### Pure reducer

`MqttContinuousOperationReducer` owns only validated state transitions:

- initialize and restore registry state;
- start a lease;
- evaluate renewal timing;
- renew an eligible lease;
- open the circuit after inner exhaustion;
- schedule and account half-open probes;
- enter recovery confirmation;
- reset recovery after sustained health;
- suspend after probe exhaustion;
- request stop; and
- mark credential-cleared and stopped phases.

It returns a new state plus one symbolic requested effect. The Account validates
and performs that effect.

### Existing pilot controller

The current pilot registry, timers, checkpoint accounting, incident reducer and
automatic closure remain pilot-only. No continuous state is written into
`MqttPilotObservationRegistry`.

### Device and renderer

The Device remains the owner of retained path, zone-statistics state and the
Local Map HTMLBox. It consumes only the bounded Account evidence projection.

## 7. Continuous Registry Schema

Add one Account attribute:

```text
MqttContinuousOperationRegistry: string, default "{}"
```

The canonical format is version 1:

```json
{
  "formatVersion": 1,
  "state": "Inactive",
  "sessionSequence": 0,
  "startedAt": 0,
  "configurationHash": "",
  "leaseStartedAt": 0,
  "leaseExpiresAt": 0,
  "renewalEligibleAt": 0,
  "lastLeaseEvaluationAt": 0,
  "lastLeaseRenewedAt": 0,
  "renewalCount": 0,
  "circuitOpenedAt": 0,
  "circuitReason": "",
  "halfOpenProbeCount": 0,
  "nextProbeAt": 0,
  "probeStartedAt": 0,
  "probeDeadlineAt": 0,
  "recoveryHealthySince": 0,
  "stopReason": "",
  "stopRequestedAt": 0,
  "credentialsClearedAt": 0,
  "stoppedAt": 0
}
```

Allowed internal states are:

```text
Inactive
Starting
Active
Degraded
CircuitOpen
HalfOpen
RecoveryConfirming
Suspended
Stopping
CredentialsCleared
Stopped
```

The registry contains no arrays or event history. Historical counters remain in
`MqttStatistics`; recent bounded reasons remain in `MqttErrorHistory`.

Malformed, oversized or unsupported registry content is a configuration-class
failure. It never produces a connection attempt.

## 8. Configuration Fingerprint

The Account must compose the already packaged
`SAEF_CreateConfigurationHash()` helper.

Hash only a normalized structure containing:

```text
format version
effective operating mode
position-diagnostics flag
configured Receiver binding
OAuth base URL
OAuth client ID
OAuth redirect URI
client-secret-present boolean
owned module GUID set
connection order
Account binding hash
subscription configuration hash
client identity hash
```

Do not hash or persist:

- the client secret value;
- access or refresh tokens;
- Authorization headers;
- MQTT username or password;
- MQTT/WSS endpoints;
- topics or device identities;
- timestamps or counters.

The registry stores only the resulting lowercase SHA-256 value.

## 9. Fixed Operating Constants

Continuous mode uses non-configurable constants for its first implementation:

```text
lease duration:                         259200 seconds
renewal lead time:                       86400 seconds
failed renewal recheck:                    300 seconds
minimum start token horizon:              1200 seconds
maximum REST-success age:                   900 seconds
position Fresh maximum age:                 120 seconds
position Delayed maximum age:               600 seconds
half-open cooldowns:             [1800, 7200, 21600, 86400]
maximum half-open probes per lease:             4
half-open observation deadline:                180 seconds
recovery healthy confirmation:                 900 seconds
```

The existing inner reconnect constants stay exactly:

```text
[60, 300, 900] seconds
```

These constants are not form settings. Exposing them before operational reuse
is proven would create unsupported combinations and weaken reviewability.

## 10. Reducer API

The pure final class exposes only named deterministic operations:

```text
initialState(): array
restore(string): array
serialize(array): string
start(array, now, configurationHash): array
leaseDecision(array, now, renewalEligible): array
openCircuit(array, now, reason): array
halfOpenDecision(array, now, prerequisitesReady): array
halfOpenConnected(array, now): array
halfOpenFailed(array, now, reason): array
observeRecoveryHealth(array, now, healthy): array
requestStop(array, now, reason): array
credentialsCleared(array, now): array
stopped(array, now): array
project(array, now): array
```

Each method validates its input and either returns a canonical state or throws
`InvalidArgumentException`. No method mutates its input.

Allowed symbolic effects returned by decisions are:

```text
none
schedule-initial-connect
renew-lease
schedule-half-open
start-half-open
clear-credentials
finalize-stop
```

The reducer cannot request `ApplyChanges()`, OAuth, MQTT publish, a mower
command or a restart.

## 11. User-Facing Variable Contract

Register these Account variables unconditionally and idempotently:

| Position | Ident | Type | Profile | Initial value |
|---:|---|---|---|---:|
| 70 | `MqttOperatingState` | integer | `NAVIMOW.MqttOperatingState` | `0` |
| 80 | `MqttLastMessageAt` | integer | `~UnixTimestamp` | `0` |
| 90 | `MqttLastPositionAt` | integer | `~UnixTimestamp` | `0` |
| 100 | `MqttPositionFreshness` | integer | `NAVIMOW.MqttPositionFreshness` | `0` |
| 110 | `MqttLeaseExpiresAt` | integer | `~UnixTimestamp` | `0` |

Profile `NAVIMOW.MqttOperatingState`:

| Value | Label |
|---:|---|
| 0 | Disabled |
| 1 | Starting |
| 2 | Active |
| 3 | Degraded |
| 4 | Circuit Open |
| 5 | Suspended |
| 6 | Waiting for Authentication |
| 7 | Reauthentication Required |
| 8 | Configuration Error |
| 9 | Stopping |

Profile `NAVIMOW.MqttPositionFreshness`:

| Value | Label |
|---:|---|
| 0 | Unavailable |
| 1 | Fresh |
| 2 | Delayed |
| 3 | Stale |

The Account is the only writer. None of these variables has an action.

Existing variables retain their Ident, type, profile, position and value
semantics. The module does not enable or alter Archive logging for old or new
variables. Existing user-selected logging therefore remains untouched.

On cleanup:

- `MqttOperatingState` becomes Disabled or Suspended as appropriate;
- `MqttPositionFreshness` becomes Unavailable;
- `MqttLeaseExpiresAt` becomes `0` after terminal stop;
- the two last-observation timestamps retain their last valid values.

Retaining timestamps preserves useful operator and optional Archive context
without retaining coordinates.

## 12. User-Facing State Projection

Project the richer internal state without duplicating control:

| Internal condition | Variable value |
|---|---:|
| master switch false | 0 Disabled |
| `Starting` or `HalfOpen` | 1 Starting |
| `Active` | 2 Active |
| `Degraded` or `RecoveryConfirming` | 3 Degraded |
| `CircuitOpen` | 4 Circuit Open |
| `Suspended` | 5 Suspended |
| lifecycle waiting for authentication | 6 Waiting for Authentication |
| lifecycle reauthentication required | 7 Reauthentication Required |
| invalid mode, ownership or configuration | 8 Configuration Error |
| any cleanup phase | 9 Stopping |

The Account instance itself remains status `102` when the module is running.
Transport health is not encoded as an IP-Symcon instance failure because REST
can remain fully operational.

## 13. Timer Contract

Add exactly three Account timers:

```text
MqttContinuousLease
MqttContinuousRecovery
MqttContinuousClosure
```

Generated actions:

```text
NAVAC_ProcessMqttContinuousLease($_IPS["TARGET"]);
NAVAC_ProcessMqttContinuousRecovery($_IPS["TARGET"]);
NAVAC_ProcessMqttContinuousClosure($_IPS["TARGET"]);
```

### Lease timer

Schedule the next exact boundary:

1. `renewalEligibleAt` while the lease is healthy;
2. five-minute recheck while inside the renewal window but ineligible; or
3. `leaseExpiresAt`, whichever occurs first.

No recheck may be scheduled beyond expiry.

### Recovery timer

Schedule only:

- the next half-open eligibility time;
- a five-minute prerequisite recheck after that time; or
- the active probe deadline.

It is always bounded by the current lease expiry.

### Closure timer

Schedule only while `Stopping` or `CredentialsCleared`. It follows the same
credential-first, deferred-property pattern as pilot cleanup and never starts
a connection.

Existing pilot timers remain zero in continuous mode. Continuous timers remain
zero in bounded-pilot mode.

## 14. ApplyChanges Ordering

`ApplyChanges()` must use this order:

1. register profiles and all stable variables;
2. resolve the effective mode without side effects;
3. clear ephemeral shadow and pending-reconciliation state as today;
4. detect and resume a pending pilot cleanup;
5. detect and resume a pending continuous cleanup;
6. stop if either cleanup still owns the lifecycle;
7. validate base authentication configuration;
8. validate mode, pairing, ownership and registry format;
9. give kernel-start reconciliation precedence when eligible;
10. reconcile the controller selected by the effective mode;
11. update operating variables; and
12. set Account instance status `102`.

Cleanup always precedes connect, rotation, recovery, lease renewal and Local Map
evidence production.

## 15. Continuous Start Sequence

A continuous session may start only when:

```text
effective mode is ContinuousReceiveOnly
no pilot session or pilot cleanup is active
continuous registry is Inactive, Stopped or explicitly resumable Suspended
configuration and ownership are exact
transport is inactive and credential-free
REST authentication is connected
ReauthRequired is false
access-token horizon is at least 1200 seconds
no kernel reconciliation is pending
```

The start sequence is:

1. acquire the existing MQTT lifecycle semaphore;
2. recompute all prerequisites;
3. create a new continuous `sessionSequence`;
4. persist configuration hash and a lease ending at `now + 259200`;
5. persist `Starting` before requesting connection;
6. schedule the existing initial connection after five seconds;
7. release the semaphore; and
8. project the new user-facing state.

One start request creates at most one scheduled initial connection. Timeout or
an ambiguous caller response never causes a second start.

## 16. Lease Evaluation

At `renewalEligibleAt`, renewal is allowed only if:

- effective mode is still continuous;
- the stored configuration hash matches;
- no cleanup, circuit or probe is active;
- lifecycle is `ShadowActive` and healthy for at least 15 minutes;
- both owned Core instances report `102`;
- WebSocket `Active=true`;
- expected Authorization and MQTT credential presence is true;
- ownership and exact subscriptions validate;
- `ReauthRequired=false` and the access token is usable;
- `LastRestSuccess` is no older than 900 seconds; and
- no credential rotation is pending.

Renewal performs exactly one registry write:

```text
leaseStartedAt = now
leaseExpiresAt = now + 259200
renewalEligibleAt = now + 172800
lastLeaseRenewedAt = now
renewalCount = bounded increment
```

It performs no credential access, Core mutation, reconnect, OAuth operation or
`ApplyChanges()`.

An ineligible renewal leaves the old lease unchanged and records only the last
evaluation time. Expiry requests continuous cleanup with reason
`lease-expired`.

## 17. Transport Recovery

### Inner episode

The existing active-transport recovery remains:

```text
unexpected Core failure
  -> clear owned transport credentials
  -> retry after 60 seconds
  -> retry after 300 seconds
  -> retry after 900 seconds
  -> exhaustion
```

The attempt counter still resets only after 900 seconds of continuous Core
health.

In bounded-pilot mode, exhaustion retains current automatic pilot closure.

In continuous mode, exhaustion:

1. verifies credential-free Core state;
2. persists `CircuitOpen`;
3. records the bounded reason `inner-reconnect-exhausted`;
4. schedules the first half-open eligibility after 1800 seconds; and
5. leaves REST polling untouched.

### Outer half-open sequence

The four probe delays are indexed exactly:

| Probe | Delay after preceding opening/failure |
|---:|---:|
| 1 | 1800 seconds |
| 2 | 7200 seconds |
| 3 | 21600 seconds |
| 4 | 86400 seconds |

Due time alone is insufficient. Before consuming a probe, require:

- current lease not expired;
- mode and configuration hash unchanged;
- credential-free transport;
- valid ownership and subscriptions;
- REST success within 900 seconds;
- usable token with at least 1200 seconds remaining; and
- no reauthentication, refresh retry or rotation pending.

If prerequisites are temporarily false, do not consume a probe. Recheck after
300 seconds, bounded by lease expiry.

One consumed probe performs exactly one credential retrieval and one Core
connection attempt. It gets 180 seconds to reach `102/102` with active
WebSocket. A synchronous error, timeout or ambiguous health result fails that
probe and invokes credential cleanup before the next cooldown.

After Core health returns, remain `RecoveryConfirming` for 900 seconds. Any
unhealthy observation in that interval fails the probe. Sustained health resets
the inner reconnect counter and outer probe count, returns to `Active`, and
permits later lease renewal.

After probe 4 fails, enter credential-free `Suspended`. No automatic connect is
scheduled until the operator explicitly resumes the continuous operation.

## 18. Mower Silence And Position Freshness

Position freshness is independent from Core transport health.

Use the latest accepted position sample's local receipt timestamp:

```text
no accepted point or transport disabled  -> Unavailable
age <= 120 seconds                       -> Fresh
age 121..600 seconds                     -> Delayed
age > 600 seconds                        -> Stale
```

Recompute freshness:

- after every accepted position payload;
- on every 60-second lifecycle observation;
- after cleanup; and
- during `ApplyChanges()` and kernel reconciliation.

No MQTT reconnect is scheduled because freshness is Delayed, Stale or
Unavailable while the Core transport remains healthy.

`MqttLastMessageAt` follows the last accepted MQTT envelope, not merely a
received invalid envelope. `MqttLastPositionAt` follows only a validated
position sample.

## 19. Local Map Contract

Extend the Account evidence status to:

```text
ok          Fresh position
delayed     Delayed position
stale       Stale position
unavailable no accepted position
```

The Device accepts `ok`, `delayed` and `stale` as valid evidence projections.
It continues to retain and prune path and statistics data for all three.

Renderer behavior:

| Freshness | External mower marker | Path |
|---|---|---|
| Fresh | visible with REST-derived state color and direction | visible |
| Delayed | visible at last point with amber dashed freshness halo | visible |
| Stale | hidden | retained path visible |
| Unavailable | hidden | retained path visible when available |
| REST Docked | hidden; station occupancy shown | retained path visible |

The delayed halo is a second visual channel. It must not replace the mower's
REST-derived fill color. Its title includes that the position is delayed.

Add one compact legend sample for the amber dashed halo labeled
`Position verspätet`. The legend remains within the existing lower-right
layout and must pass dark- and light-theme renderer tests.

The current station and directional marker geometry remain unchanged.

## 20. Authentication And Rotation

Passive OAuth behavior remains unchanged.

After a successful token refresh:

- bounded pilot mode uses the existing credential rotation;
- active continuous mode rotates once through the existing cleanup-first path;
- CircuitOpen or Suspended continuous mode stores no MQTT credentials and does
  not reconnect merely because a token rotated;
- the new token may satisfy a later scheduled half-open prerequisite;
- any authentication or configuration failure bypasses transport retries.

A transient token-refresh transport failure requests credential cleanup first,
preserves the current lease and projects `WaitingForAuthentication` while the
existing bounded token-refresh retry sequence runs. A later successful refresh
may schedule one rotation-class reconnect when the lease is still valid.

An exhausted refresh sequence remains credential-free and waiting for an
explicit authentication recovery action; it does not consume a transport
half-open probe. A rejected refresh token, absent refresh token or explicit
`ReauthenticationRequired` condition suspends the continuous operation.

## 21. Cleanup State Machine

Allowed continuous stop reasons are fixed:

```text
operator-disabled
operator-suspended
lease-expired
authentication-unavailable
reauthentication-required
configuration-invalid
ownership-invalid
mode-changed
registry-invalid
half-open-exhausted
update-incompatible
```

The first reason wins.

Cleanup phases:

```text
any continuous state
  -> Stopping
  -> CredentialsCleared
  -> Stopped or Suspended
```

Final state mapping is exact:

| Stop reason | Final registry state | Master switch |
|---|---|---|
| `operator-disabled` | `Stopped` | false |
| `mode-changed` | `Stopped` | forced false |
| `operator-suspended` | `Suspended` | unchanged true |
| `lease-expired` | `Suspended` | unchanged true |
| `half-open-exhausted` | `Suspended` | unchanged true |
| authentication or reauthentication terminal | `Suspended` | unchanged true |
| configuration, ownership, registry or update failure | `Stopped` | forced false |

Sequence:

1. acquire lifecycle semaphore;
2. persist `Stopping` and immutable reason;
3. stop lifecycle, lease and recovery timers;
4. clear pending reconnect and rotation schedules;
5. deactivate owned WebSocket;
6. remove Authorization header, MQTT username and MQTT password;
7. apply owned Core changes;
8. verify inactive and credential-free topology;
9. persist `CredentialsCleared`;
10. release semaphore;
11. finalize operating state and variables; and
12. stop the closure timer.

For `operator-disabled` or `mode-changed`, configuration is already disabled or
must be forced to a safe disabled master state before another mode may start.

For half-open exhaustion, `operator-suspended` or lease expiry, preserve the
user's continuous-mode selection but persist runtime `Suspended`. Only
`ResumeMqttContinuousOperation()` may create a new lease.

If credential verification fails, remain `Stopping`, keep all connection timers
off, expose Configuration Error and retry cleanup after 60 seconds. Cleanup
never activates the transport.

## 22. Restart Contract

At kernel start:

1. register the kernel observation as today;
2. restore and validate the continuous registry;
3. resume pending cleanup before retained-Core adoption;
4. compare current time with the persisted lease expiry;
5. clean immediately when the lease is expired;
6. keep CircuitOpen or Suspended credential-free;
7. resume a due recovery timer without consuming a probe;
8. permit retained-Core adoption only for an unexpired Starting, Active,
   Degraded or RecoveryConfirming session; and
9. retain the original lease deadline.

The existing bounded `+15/+30/+60/+90/+120/+180` Core observation axis remains
unchanged. External verification allows up to five minutes for the Symcon
service and console to become reachable before that internal timeline is
judged.

## 23. Configuration Change And Update Contract

### User configuration change

If the continuous configuration fingerprint changes while active:

- request credential-first stop with `configuration-invalid` or
  `mode-changed` as appropriate;
- never reuse old credentials against a new topology;
- validate the new inactive topology;
- start a new lease only when the saved configuration still explicitly selects
  enabled continuous mode and all prerequisites pass.

One `ApplyChanges()` may complete cleanup asynchronously. It must not perform a
second connection attempt merely because the caller retries after timeout.

### Module update

The first module publication and update are permitted only with MQTT disabled
and Core credentials absent.

For later compatible updates, an unchanged format and configuration hash may
preserve the lease but still use the existing retained-Core reconciliation.
An unsupported registry version or changed lifecycle contract stops
credential-free with `update-incompatible`; it never migrates an active session
optimistically.

`MC_UpdateModule()` remains the supported update mechanism.
`MC_ReloadModule()` remains prohibited.

## 24. Public Method And Form Contract

### Form elements

Keep the master checkbox and add a `Select` control for
`MqttOperatingMode` with:

```text
Bounded pilot
Continuous receive-only
```

Update the explanatory label to state that MQTT is receive-only, REST remains
authoritative, bounded pilot has a deadline and continuous mode uses renewable
safety leases.

The pilot-duration spinner remains visible. Its caption explicitly says that it
applies only to bounded pilot mode.

### Existing actions

- `ConnectMqttShadow()` remains manual bounded-pilot tooling and rejects
  continuous mode with no mutation.
- `DisconnectMqttShadow()` remains pilot disconnect in bounded mode and becomes
  credential-first manual suspension in continuous mode.
- adoption validation remains available only for inactive, credential-free
  topology.
- existing diagnostics remain read-only.

### New action

Add:

```text
ResumeMqttContinuousOperation(): string
```

The button caption is `Resume Continuous MQTT`. It is guarded by a confirmation
that one new receive-only lease and one initial connection attempt may start.

The method accepts only:

- enabled continuous mode;
- `Suspended` state;
- credential-free owned topology;
- valid configuration and ownership;
- connected REST authentication;
- token horizon of at least 1200 seconds; and
- no cleanup, kernel reconciliation or pilot session.

It starts one new lease and schedules one initial connection. Every other state
returns a concise message without mutation.

## 25. Diagnostics Contract

Increment the existing `GetMqttDiagnostics()` format version from 2 to 3 and
add an `operation` section containing only:

```text
effectiveMode
state
sessionSequence
startedAt
leaseExpiresAt
renewalEligibleAt
lastLeaseRenewedAt
renewalCount
circuitReason
halfOpenProbeCount
nextProbeAt
probeDeadlineAt
recoveryHealthySince
stopReason
stopRequestedAt
credentialsClearedAt
stoppedAt
configurationMatches
positionFreshness
lastAcceptedMessageAt
lastAcceptedPositionAt
```

Extend `MqttStatistics` with bounded counters:

```text
continuousStarts
continuousLeaseRenewals
continuousLeaseExpirations
continuousCircuitOpenings
continuousHalfOpenProbes
continuousHalfOpenRecoveries
continuousHalfOpenFailures
continuousSuspensions
continuousStops
```

Extend allowed bounded error reasons only with symbolic values needed by this
state machine. No reason contains exception text or external values.

Diagnostics expose no credential values, coordinates, device identifiers,
topics, endpoints, ObjectIDs, hostnames or private configuration.

## 26. Implementation Changes By File

### `NavimowAccount/module.php`

- require the reducer and ConfigurationHash helper;
- register the new property, attribute, timers and five variables;
- resolve effective mode;
- route pilot and continuous controllers explicitly;
- add lease, recovery and closure handlers;
- make reconnect exhaustion and authentication handling mode-aware;
- add continuous diagnostics and variable projection;
- add freshness evaluation;
- guard existing manual connect/disconnect methods; and
- add explicit continuous resume.

### `MqttContinuousOperationReducer.php`

- implement validated pure state, lease and circuit transitions;
- enforce fixed cooldown and count bounds;
- provide bounded serialization and projection.

### `Profiles.php`

- add the two exact integer profiles without altering existing associations.

### Account form and locale

- add operating-mode selection and continuous resume action;
- clarify bounded duration and receive-only authority;
- add German captions without changing technical property names.

### `NavimowDevice/module.php`

- accept delayed evidence;
- pass exact freshness to the renderer;
- retain path/statistics while suppressing stale current-position claims.

### Both Local Map renderers

- accept `positionFreshness`;
- add the delayed halo, title and legend sample;
- keep production and prototype implementations behaviorally equal apart from
  namespace.

### Fileset and publication manifests

- add the reducer to both deterministic inventories;
- preserve all existing targets and ordering rules.

### Test runner

- execute the new reducer test;
- include the new class and modified renderers in PHPCS and PHPStan scope.

## 27. Synthetic Test Matrix

### Mode and migration

1. disabled legacy configuration remains disabled;
2. enabled legacy configuration defaults to bounded pilot;
3. no legacy state selects continuous mode;
4. invalid mode is credential-free Configuration Error;
5. active pilot remains deadline-bound after update;
6. direct active pilot-to-continuous switch closes before new start;
7. direct continuous-to-pilot switch closes before new start.

### Registry and reducer

8. initial, serialize and restore round-trip;
9. malformed JSON, wrong version, unknown state and oversized state reject;
10. every named transition accepts only valid predecessor states;
11. first stop reason wins;
12. counters saturate and timestamps never move backwards;
13. reducer emits only the allowed symbolic effects.

### Lease

14. start creates exact 72-hour lease and 48-hour renewal eligibility;
15. healthy renewal extends from current time without transport effect;
16. early renewal is rejected;
17. failed eligibility leaves deadline unchanged;
18. five-minute rechecks never cross expiry;
19. exact and delayed expiry request one stop;
20. restart before expiry preserves deadline;
21. restart after expiry cleans before Core adoption.

### Recovery

22. inner delays remain exactly 60, 300 and 900 seconds;
23. pilot exhaustion still closes the pilot;
24. continuous exhaustion enters credential-free CircuitOpen;
25. half-open delays are exactly 1800, 7200, 21600 and 86400 seconds;
26. false prerequisites do not consume a probe;
27. one probe performs one credential retrieval and one connection attempt;
28. ambiguous or timed-out probe cleans credentials before rescheduling;
29. 180-second probe deadline is enforced;
30. 900 seconds of health returns to Active and resets recovery;
31. health loss during confirmation fails the probe;
32. fourth failed probe enters Suspended with no timer;
33. explicit resume creates one new lease and one start only.

### Authentication and configuration

34. low token horizon waits without connection;
35. passive refresh makes later start eligible;
36. rotation while Active reconnects exactly once;
37. rotation while CircuitOpen does not connect;
38. authentication and configuration errors never enter retry;
39. ownership or subscription drift cleans and blocks.

### Cleanup and restart

40. operator disable stops all continuous timers;
41. cleanup clears WebSocket header and MQTT username/password first;
42. crash/resume works from Stopping and CredentialsCleared;
43. failed credential verification cannot finalize stop;
44. cleanup never calls connect, OAuth, publish or mower command;
45. retained-Core adoption is allowed only for unexpired eligible states;
46. CircuitOpen and Suspended restart credential-free.

### Variables and diagnostics

47. all old Account and Device variables retain exact contracts;
48. five new variables register idempotently at fixed positions;
49. no Archive logging or aggregation is changed;
50. operating-state projection covers every internal state;
51. last timestamps survive terminal cleanup;
52. lease timestamp clears after terminal stop;
53. diagnostic format 3 is bounded and privacy-safe;
54. all new counters saturate safely.

### Freshness and map

55. exact 120- and 600-second boundaries classify correctly;
56. mower silence never schedules reconnect while Core is healthy;
57. Fresh marker retains state color and direction;
58. Delayed marker retains state color plus amber dashed halo;
59. Stale and Unavailable hide the current marker;
60. Docked always uses station occupancy instead of external marker;
61. retained path and zone statistics survive stale position;
62. dark and light legend layouts contain no overlap;
63. geometry revision remains the path/statistics boundary.

### Authority and fileset

64. MQTT cannot write REST-owned public state;
65. no MQTT publish or command path exists;
66. targeted REST reconciliation remains bounded;
67. reducer appears in source, generated and publication inventories;
68. deterministic fileset build and publication check pass.

## 28. Required Validation Sequence

Implementation is complete only after:

```text
php case-studies/navimow/tests/mqtt-continuous-operation.php
php case-studies/navimow/tests/mqtt-transport-lifecycle.php
php case-studies/navimow/tests/mqtt-shadow-diagnostics.php
php case-studies/navimow/tests/local-map-evidence-contract.php
php case-studies/navimow/tests/local-map-device-lifecycle.php
php case-studies/navimow/tests/local-map-svg-renderer.php
php case-studies/navimow/tests/local-map-variable-stability.php
php case-studies/navimow/tests/local-map-distribution-fileset.php
case-studies/navimow/tools/check-mqtt-shadow.sh
composer navimow:fileset-check
composer navimow:publication-check
composer check
```

Use the repository's lock-identical resolved Composer vendor directory. Missing
`vendor/` in an isolated worktree is a toolchain-resolution condition, not a
reason to copy, download or substitute dependencies.

## 29. Compatibility Gates

The candidate must prove:

- existing 29-variable cross-module contract unchanged;
- five new Account variables additive only;
- existing Archive logging and aggregation hashes unchanged;
- all module GUIDs and prefixes unchanged;
- REST fixtures and command tests unchanged;
- pilot duration, deadline and cleanup tests unchanged;
- disabled update remains credential-free;
- default mode remains bounded pilot, never continuous;
- Local Map disabled and stale behavior remains deterministic; and
- module validator and metadata checks pass.

## 30. Publication And Live Gates

| Gate | Scope | Status |
|---|---|---|
| D1 detailed design | this document | PASS |
| I1 productive implementation | local clean worktree | CLOSED |
| I2 full offline validation | no live access | CLOSED |
| R1 focused code review | exact candidate | CLOSED |
| P1 SAEF branch, PR and merge | Git publication | CLOSED |
| P2 standalone PR publication | external publication | CLOSED |
| M1 metadata conformance | read-only published tree | CLOSED |
| S1 disabled Symcon update | exact one module update | CLOSED |
| S2 inactive migration/postflight | read-only live | CLOSED |
| L1 credential-retention acceptance | operator statement | NOT REQUESTED |
| L2 24-hour continuous acceptance activation | one start | CLOSED |
| L3 24-hour evidence and cleanup | bounded observation | CLOSED |
| O1 ongoing private operation | second explicit activation | CLOSED |

No gate inherits authority from the preceding gate.

## 31. Architecture Decisions

### AD-NAV-389-01: Add a mode policy behind the existing master switch

Defaulting the new policy to bounded pilot preserves every existing enabled and
disabled configuration without an ambiguous migration marker.

### AD-NAV-389-02: Keep pilot and continuous registries separate

Pilot evidence and completion semantics must not become normal service state.
Both controllers may compose the same owned transport and cleanup primitives.

### AD-NAV-389-03: Introduce one Navimow-local pure reducer

Lease and circuit transitions need deterministic synthetic-clock tests. A local
class removes complexity from the Account without creating a framework API.

### AD-NAV-389-04: Reuse the SAEF ConfigurationHash helper

The helper is already packaged for Navimow and provides the stable normalized
fingerprint needed for restart and update decisions.

### AD-NAV-389-05: Register operating variables additively

Operators need normal status without parsing diagnostics. Stable additive
variables preserve all existing logging and identity contracts.

### AD-NAV-389-06: Preserve state color and overlay freshness

REST-derived mower state and MQTT-derived position freshness are independent.
An amber halo communicates delay without overwriting the authoritative state
color.

### AD-NAV-389-07: Bound outer recovery to four probes per lease

Normal outages recover autonomously, while persistent external or Core failure
cannot create an endless credential-fetch loop.

### AD-NAV-389-08: Require credential-free publication rollout

Mode migration and active credential handling are separate risks. The first
module update must prove the additive disabled path before any continuous
activation is considered.

## 32. Gate Result

**PASS** for detailed implementation design.

The target behavior, data ownership, migration, constants, reducers, variables,
timers, fileset and offline acceptance tests are frozen sufficiently for a
productive implementation step.

No PHP, JSON form, distribution, generated fileset or live installation was
changed in this step.

## 33. Next Step

Proceed with:

```text
390-continuous-receive-only-implementation.md
```

That step may implement only the frozen candidate in a current clean dedicated
worktree and run the complete offline validation sequence. It must not publish,
update Symcon, activate MQTT, retrieve live credentials, run OAuth, restart
Symcon or send a mower command.
