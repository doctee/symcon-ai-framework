# SAEF Step 388: Continuous Receive-Only Operating Decision

**Case study:** Navimow native IP-Symcon module

**Status:** Target operating model approved; implementation, publication and
live activation remain separate closed gates

**Date:** 2026-09-04

## 1. Purpose

Step 387 proved in one supervised 300-second run that the installed module can
receive frequent position samples, derive a movement direction, render the
active mower marker and close the credential-bearing transport automatically.

The operator now prefers continuous position availability over individually
scheduled capture windows. This step selects the corresponding operating model
and defines its authority, safety, recovery, observability and migration
boundaries.

It performs no productive PHP change, publication, Symcon access, MQTT
activation, credential retrieval, OAuth action, restart or mower command.

## 2. Decision

The target for the private installation is:

```text
continuously available, monitored, receive-only MQTT and position operation
```

The target is approved as an architectural direction. It is not implemented
by removing or extending the current pilot deadline.

The current `EnableMqttShadow` path remains a bounded pilot with a maximum
duration of 72 hours. Continuous operation requires an explicit operating mode,
its own lifecycle contract and a separate migration path.

```text
target mode:                      GO
reuse current pilot as-is:       GO
remove pilot hard stop:          NO-GO
activate current pilot forever:  NO-GO
productive implementation:      CLOSED
live activation:                 CLOSED
```

## 3. Evidence Supporting The Decision

The decision builds on the following completed evidence:

- native MQTT/WSS ingress was observed over multiple bounded pilots;
- the 72-hour incident-policy pilot received sustained traffic and closed at
  its absolute deadline;
- one natural transport incident recovered through the bounded reconnect
  state machine;
- OAuth refresh and MQTT credential rotation were observed without a manual
  authentication action;
- retained-Core restart reconciliation was tested separately;
- REST remained operational and authoritative during MQTT operation;
- MQTT publishing and MQTT mower-command paths remain absent;
- automatic closure removed Authorization and MQTT credentials;
- the latest 300-second run delivered 118 position samples, 117 coordinate
  changes and no invalid envelope or handoff failure;
- the active Local Map marker and its path-derived direction were verified.

This is sufficient to select a private continuous receive-only target. It is
not sufficient to claim public, Store-ready or vendor-supported MQTT operation.

## 4. Why The Pilot Must Remain Separate

The bounded pilot owns policies that are intentionally unsuitable for normal
continuous operation:

- an immutable maximum duration;
- automatic closure at that deadline;
- pilot-specific evidence checkpoints and summaries;
- closure after policy-defined incident limits;
- mandatory return to disabled after every run.

Silently interpreting a large duration as permanent operation would mix test
authorization, runtime policy and credential lifetime. It would also make an
existing property change alter semantics during an update.

The pilot therefore remains a diagnostic tool. The continuous mode composes
the proven transport, parsing, credential rotation, restart reconciliation,
position retention and cleanup machinery without inheriting the pilot's
completion rules.

## 5. Operating Modes

The implementation design must expose one unambiguous mode selection:

| Mode | Purpose | Credential-bearing duration |
|---|---|---|
| `Disabled` | REST-only normal operation | none |
| `BoundedPilot` | supervised diagnostics and evidence | 300 seconds to 72 hours |
| `ContinuousReceiveOnly` | normal private position and task observation | renewable bounded safety leases |

The existing boolean properties must be retained long enough for an explicit,
fail-closed migration. Conflicting legacy and mode configuration must never
select the more permissive mode.

## 6. Authority And Command Boundary

Continuous operation preserves the existing split:

```text
device state and command authority: REST
MQTT role:                         receive-only event and position evidence
MQTT publishing:                   prohibited
MQTT mower commands:               prohibited
```

MQTT may:

- retain bounded position samples;
- retain bounded task and area observations;
- trigger a coalesced Local Map refresh;
- trigger bounded targeted REST reconciliation;
- update MQTT-specific health and freshness diagnostics.

MQTT must not directly write the public `VehicleState`, `Online`,
`BatteryLevel`, command evidence or REST-success variables. A disagreement is
recorded as diagnostic evidence; it does not transfer authority to MQTT.

## 7. Renewable Safety Lease

Continuous operation is open-ended from the user's perspective but always
bounded internally by a persisted operating lease.

Initial lease:

```text
lease duration:       72 hours
renewal window:       final 24 hours
renewal side effect:  registry update only
```

The module may renew the lease to `now + 72 hours` only when all of these
conditions are true:

1. `ContinuousReceiveOnly` remains explicitly selected;
2. ownership, pairing, subscriptions and configuration are valid;
3. no cleanup or migration is pending;
4. REST authentication is usable and reauthentication is not required;
5. recent REST polling is healthy;
6. MQTT Client and WebSocket Client are both healthy and the WebSocket is
   active;
7. owned Core credentials are present only in the expected instances;
8. no recovery or half-open probe is active;
9. the stored configuration fingerprint still matches.

Fresh mower messages are deliberately not a lease-renewal requirement. A
docked mower or temporary mower-side network gap may legitimately emit no
position traffic while the owned transport remains healthy.

Lease renewal must not reconnect, fetch credentials, call `ApplyChanges()` or
restart a Core instance. If renewal conditions remain false, the original
deadline is retained. At expiry, module-owned credential cleanup takes
priority over reconnect, rotation and retained-Core adoption.

This creates a maximum forward credential-exposure horizon of 72 hours at any
successful checkpoint without forcing periodic connection churn.

## 8. Lifecycle Model

The existing MQTT lifecycle should be extended, not duplicated. The operating
contract needs these externally meaningful states:

```text
Disabled
Starting
Active
Degraded
CircuitOpen
WaitingForAuthentication
ReauthenticationRequired
ConfigurationError
Stopping
```

Internal Core-resume, credential-rotation and reconnect states may remain more
detailed. The user-facing state must be concise and deterministic.

Required transitions include:

```text
Disabled -> Starting -> Active
Active -> Degraded -> Active
Degraded -> CircuitOpen
CircuitOpen -> Starting -> Active
any active state -> WaitingForAuthentication
any active state -> ReauthenticationRequired
any active state -> ConfigurationError
any enabled state -> Stopping -> Disabled
lease expiry -> Stopping -> Disabled
```

Every persistent state has one Account owner and survives a Symcon restart.

## 9. Connectivity And Recovery

### 9.1 Missing mower messages

No mower messages while the MQTT and WebSocket Core instances remain healthy
is a data-freshness event, not a broker failure.

The module must:

- continue REST polling;
- retain the last bounded path without presenting it as current;
- update position freshness;
- avoid a broker reconnect solely because the mower is quiet.

### 9.2 Active transport incident

The proven inner reconnect sequence remains unchanged:

```text
attempt 1: after 60 seconds
attempt 2: after 300 seconds
attempt 3: after 900 seconds
attempt 4: prohibited
```

After exhaustion, the owned transport and its credentials are cleared before
entering `CircuitOpen`. REST continues normally.

### 9.3 Half-open recovery

Continuous mode may perform bounded half-open recovery after an exhausted
transport episode. The implementation plan must use these upper bounds:

```text
earliest probe:                  after 30 minutes
later cooldowns:                2 hours, 6 hours, 24 hours
maximum half-open probes/lease: 4
one credential retrieval/probe: exactly one
probe observation deadline:     180 seconds
healthy reset interval:         15 minutes
```

Every half-open probe revalidates configuration, ownership, REST authentication
and the remaining lease first. A failed or ambiguous probe is never repeated
immediately. Its credentials are cleared before the next cooldown.

After four unsuccessful probes, or at lease expiry, the mode becomes
credential-free and requires an explicit operator resume after diagnosis.

This outer recovery policy is finite. It does not turn a permanent cloud or
configuration fault into an infinite retry loop.

### 9.4 Non-retryable failures

Authentication, configuration, ownership, pairing and subscription failures
do not enter either reconnect layer. They stop the transport, clear credentials
and expose the corresponding state.

## 10. Authentication And Credentials

The existing OAuth access and refresh-token flow remains the authentication
source. MQTT credentials are retrieved only for:

- initial continuous-mode connection;
- a proven OAuth credential rotation;
- one authorized half-open recovery probe.

While continuous mode is healthy, Authorization and MQTT credentials remain
in the module-owned IP-Symcon Core instances because the Core clients require
them. They must not be copied into variables, public diagnostics, logs,
evidence or case-study files.

Credential cleanup is mandatory when:

- the user selects `Disabled`;
- the lease expires;
- authentication becomes unusable;
- reauthentication is required;
- configuration or ownership becomes invalid;
- recovery is exhausted;
- module migration or update requires quiescence.

A preference for continuous operation is not by itself acceptance of this
longer credential-retention context. A concise, mode-specific acceptance is
required once, immediately before the first live activation.

## 11. Position Freshness And Map Behaviour

The map must distinguish a current position from retained history.

Recommended initial freshness classes:

| Age of latest valid point | Meaning | Presentation |
|---|---|---|
| up to 120 seconds | `Fresh` | normal state color and direction |
| 121 to 600 seconds | `Delayed` | strong warning color and age indication |
| more than 600 seconds | `Stale` | no claim of current position; retained path remains |
| no accepted point | `Unavailable` | no external mower marker |

REST state still determines station occupancy and the mower's semantic state.
When REST reports Docked, the station shows the occupancy symbol. When REST
reports Running but position evidence is stale, the map must show a clear
position warning rather than silently presenting the last point as current.

The existing 72-hour position retention remains the initial rolling path
window. Zone-pass and mowing-recency statistics remain separate retained
aggregates and are not erased merely because old path points expire.

Geometry revisions continue to bound path interpretation. A changed map or
zone boundary must not retroactively reinterpret earlier coordinates as if
they were captured under the new geometry.

## 12. Operational Visibility

Continuous background operation needs concise status without turning the
object tree into a debug console.

The next design step must specify an additive variable contract for at least:

- MQTT operating state;
- last accepted MQTT message time;
- last accepted position time;
- position freshness;
- current lease expiry or suspended state.

Detailed counters, transition reasons, reconnect episodes, credential
rotations and bounded recent errors remain in the existing structured
diagnostics. Existing Registry, Statistics and bounded error-history patterns
must be composed before any new reusable helper is considered.

No diagnostic value may contain coordinates, topics, device identities,
credentials, endpoints, hostnames or ObjectIDs.

## 13. Restart, Update And Manual Stop

### Restart

On kernel start, persisted lease and cleanup state take precedence over
retained-Core adoption:

- an unexpired healthy lease may resume through the proven Core-reconciliation
  path;
- an expired lease must clean credentials before any adoption;
- incomplete cleanup resumes before connect or rotation;
- service availability may take several minutes and is not judged by a
  90-second external timeout.

### Module update

The first rollout of continuous mode must occur while MQTT is disabled and
credential-free. A later update policy must either preserve a healthy,
compatible lease explicitly or quiesce and clean the transport before update.
No update may infer activation from a stale legacy property.

### Manual stop

Selecting `Disabled` is the kill switch. One Account `ApplyChanges()` must
start idempotent cleanup immediately. Cleanup has priority over all scheduled
connect, reconnect, rotation, checkpoint and map work.

`MC_ReloadModule()` is not part of the operating procedure.

## 14. Privacy And Release Boundary

Continuous MQTT remains a private-installation feature using an undocumented
vendor path. This decision does not change:

- the unresolved public OAuth distribution gate;
- the lack of a vendor-supported MQTT contract;
- the Store-preparation deferral;
- the private nature of coordinates, route history and zone geometry;
- the prohibition on MQTT commands and publishing.

Public documentation may describe contracts, symbolic states and bounded
counts. Live evidence and all installation-specific data remain below the
private overlay.

## 15. Failure And Safe-State Contract

The continuous mode is healthy only when both planes are classified:

| REST | MQTT | Result |
|---|---|---|
| healthy | healthy | `Active` |
| healthy | mower data quiet | `Active` with delayed/unavailable position |
| healthy | transport recovering | `Degraded` |
| healthy | recovery exhausted | credential-free `CircuitOpen` or suspended |
| auth unavailable | any | MQTT stopped; REST authentication state exposed |
| configuration invalid | any | credential-free `ConfigurationError` |

MQTT failure must never stop REST polling. REST failure must never cause MQTT
data to become authoritative.

## 16. Implementation And Live Gate Sequence

| Gate | Mutation | Decision |
|---|---|---|
| operating decision | documentation only | PASS |
| continuous-mode detailed design | documentation only | NEXT |
| offline implementation and migration tests | repository files | CLOSED |
| SAEF review and merge | Git publication | CLOSED |
| standalone publication | external publication | CLOSED |
| disabled credential-free Symcon update | live module mutation | CLOSED |
| inactive migration/readiness check | read-only live access | CLOSED |
| continuous credential-retention acceptance | none | NOT YET REQUESTED |
| first continuous activation | one mode change and `ApplyChanges()` | CLOSED |
| 24-hour monitored acceptance window | receive-only observation | CLOSED |
| permanent private operation | ongoing | CLOSED UNTIL ACCEPTANCE WINDOW PASSES |

Publication, update, activation and ongoing operation remain separate gates.

## 17. Acceptance Window

The first implementation must not jump directly from offline tests to an
unobserved permanent state.

After disabled rollout and a passing preflight, use one 24-hour monitored
continuous-mode acceptance window. The mode itself is continuous, but the
evidence review occurs after 24 hours.

The window must prove:

- at least one natural mowing interval when the schedule permits;
- fresh position and direction evidence while moving;
- correct Fresh, Delayed, Stale and Unavailable behavior where observable;
- no direct MQTT write to REST-owned variables;
- bounded recovery diagnostics;
- lease persistence and valid renewal eligibility;
- manual disable followed by immediate and delayed credential-free cleanup.

After that cleanup and review, a second explicit gate may enable ongoing
private operation. The acceptance window does not require waiting for a full
72-hour pilot because transport capability and automatic 72-hour closure are
already proven separately.

## 18. Architecture Decisions

### AD-NAV-388-01: Select continuous receive-only as the private target

The Local Map, path retention and zone statistics need position evidence
during natural mowing without arranging individual capture windows.

### AD-NAV-388-02: Keep bounded pilot and continuous operation separate

Test completion rules and normal service recovery have different semantics.
An explicit mode prevents a duration value from silently changing authority.

### AD-NAV-388-03: Use renewable 72-hour safety leases

The user receives continuous service while the module always retains a finite
forward credential-exposure horizon and an executable cleanup deadline.

### AD-NAV-388-04: Retain REST authority and prohibit MQTT commands

The MQTT evidence is valuable for timeliness and geometry, but the protocol is
undocumented and does not replace the established REST state or command path.

### AD-NAV-388-05: Separate mower silence from transport failure

A quiet docked or poorly connected mower must affect data freshness, not cause
broker churn while the Core transport is healthy.

### AD-NAV-388-06: Add a finite outer circuit breaker

Three fast reconnects handle ordinary interruptions. Credential-free,
cooldown-bound half-open probes handle longer transport outages without an
infinite retry loop.

### AD-NAV-388-07: Make operating health visible and details bounded

Concise variables support normal operation; existing structured diagnostics
retain engineering context without exposing private payloads.

### AD-NAV-388-08: Require a monitored acceptance window before ongoing use

Existing pilots prove the components, while one 24-hour continuous-mode window
must prove their new lifecycle composition and cleanup boundary.

## 19. Gate Result

**GO** for designing and implementing a private continuous receive-only mode.

**NO-GO** for converting the current bounded pilot into an indefinite run or
activating MQTT before the new mode, migration, tests, publication, disabled
rollout and acceptance gate exist.

## 20. Next Step

Proceed with:

```text
389-continuous-receive-only-implementation-design.md
```

That step must freeze the exact property migration, lifecycle schema, lease and
circuit-breaker reducers, timers, variable profiles, form behavior, cleanup
ordering, compatibility checks and synthetic test matrix. It must still make
no productive PHP change and perform no live Symcon action.
