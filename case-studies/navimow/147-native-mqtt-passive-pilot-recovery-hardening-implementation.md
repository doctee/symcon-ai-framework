# 147 Native MQTT Passive Pilot Recovery Hardening Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Recovery hardening complete offline; publication and live pilot
remain gated
**Date:** 2026-07-28
**Scope:** Implement and verify the bounded restart, token-rotation and
reconnect contract from step 146

## 1. Purpose

Step 146 accepted corrected native MQTT ingress but blocked persistent
operation until restart, disconnect and OAuth-token recovery were
deterministic.

This step implements that recovery model exclusively in the case-study
distribution and validates it without:

- publishing module files;
- updating or mutating Symcon;
- opening a broker connection;
- contacting a Navimow REST endpoint;
- sending a mower command.

REST remains the only authority for public mower variables.

## 2. Implemented Lifecycle

The Account lifecycle now uses one private `MqttLifecycle` timer and the
existing MQTT lifecycle semaphore.

### Startup and restart

When MQTT is enabled, authenticated and explicitly adopted:

1. `ApplyChanges()` deactivates the owned WebSocket;
2. WebSocket headers and MQTT username/password are cleared;
3. private shadow and reconciliation state are cleared;
4. no credential endpoint is called inline;
5. one lifecycle attempt is scheduled after five seconds;
6. repeated `ApplyChanges()` produces the same credential-free staged state.

The delayed attempt retrieves credentials once and starts one connection.

### Health observation

After activation, Core MQTT and WebSocket status are observed every 60
seconds.

A connection is counted successful only when:

```text
MQTT status = 102
WebSocket status = 102
WebSocket Active = true
```

After 15 continuously healthy minutes, the reconnect episode counter resets.

### Unexpected disconnect

An unexpected unhealthy Core observation:

1. records one bounded disconnect event;
2. deactivates the WebSocket immediately;
3. removes headers and MQTT credentials before waiting;
4. clears transient shadow state;
5. schedules the first reconnect after 60 seconds.

All observation and transition work is serialized by the lifecycle semaphore.
If the timer cannot acquire it, one bounded five-second recheck is scheduled.

### Bounded reconnect

The implemented delay sequence is:

```text
attempt 1:  60 seconds
attempt 2: 300 seconds
attempt 3: 900 seconds
```

After the third failed attempt:

- the lifecycle becomes `Disconnected`;
- the lifecycle timer remains stopped;
- no fourth attempt is made;
- recovery requires an explicit operation or validated authentication event.

Authentication and configuration failures are terminal classifications for
the current connection attempt and never enter the transport retry sequence.

### Network boundaries

The retry model distinguishes two independent paths:

```text
mower -> Navimow cloud
Symcon -> Navimow MQTT/WSS broker
```

Poor mower connectivity normally leaves the Symcon broker connection healthy.
MQTT messages then become absent or delayed, but no reconnect episode starts.
Normal REST polling continues independently and remains authoritative.

The three-attempt limit applies only when the owned Symcon MQTT/WebSocket
transport is observed unhealthy. After exhaustion, REST still operates but
MQTT acceleration remains unavailable until an explicit lifecycle event.

This conservative stop is suitable for the first pilot. Before unattended
production use, pilot evidence must decide whether to add a circuit-breaker
half-open state: after the three bounded attempts and a long quiet period,
exactly one low-frequency probe connection could be permitted. Such a probe
is not part of this implementation.

## 3. OAuth Credential Rotation

After a successful OAuth refresh:

1. the active owned transport is disconnected;
2. old transient credentials are removed;
3. one rotation is recorded;
4. a new connection is scheduled after five seconds;
5. fresh MQTT credentials and the refreshed Authorization header are applied
   only during that connection attempt.

After a failed OAuth refresh, MQTT is cleaned and suspended. Existing REST
authentication classification and retry behavior remain authoritative.

## 4. Diagnostics

The existing private bounded diagnostics now expose:

```text
connectionSuccesses
connectionFailures
unexpectedDisconnects
reconnectAttempts
reconnectExhausted
credentialRotations
lastTransitionReason
healthySince
nextAttemptAt
reconnectAttempt
```

Counters saturate at a fixed integer limit. Allowed lifecycle and reason
values are projected through fixed allowlists. Topics, payloads, device IDs,
URLs, tokens and MQTT credentials remain excluded.

No new public Device variable or variable profile was introduced.

## 5. Deterministic Regression Evidence

The lifecycle test uses only:

- a fake clock;
- synthetic REST/MQTT credential responses;
- in-memory Core instance configuration;
- recorded property and `ApplyChanges()` operations.

It proves:

| Contract | Result |
|---|---|
| five-second delayed startup | PASS |
| no credential call inside `ApplyChanges()` | PASS |
| repeated restart staging is stable | PASS |
| successful token rotation cleans then reconnects once | PASS |
| healthy Core confirmation | PASS |
| immediate credential cleanup after disconnect evidence | PASS |
| reconnect delays `60/300/900` | PASS |
| exactly three reconnect attempts | PASS |
| no fourth attempt after exhaustion | PASS |
| authentication failure is not retried | PASS |
| configuration failure is not retried | PASS |
| reconnect counter retained before 15 minutes | PASS |
| reconnect counter reset after 15 healthy minutes | PASS |
| feature disable cleans transport and stops timers | PASS |
| repeated disabled `ApplyChanges()` is stable | PASS |
| public Account variable values remain unchanged | PASS |
| no automatic Core creation, deletion or module reload | PASS |

The diagnostics fixture was extended to freeze the new privacy-safe schema.

## 6. Validation

Complete offline Navimow MQTT gate:

```text
MQTT fixtures:                 PASS
REST client and auth:          PASS
native MQTT envelope:          PASS
MQTT shadow payload:           PASS
Receiver diagnostics:          PASS
Account ingestion:             PASS
targeted REST reconciliation:  PASS
transport lifecycle:           PASS
distribution structure:        PASS
PHPCS:                         PASS
PHPStan:                       PASS
git diff --check:              PASS
```

Source hashes:

```text
NavimowAccount/module.php:
4127b75e2dd451141a771f5244f185e43a7b4d3a158e6ddc2f59b630e562e48b

mqtt-transport-lifecycle.php:
0092b1626a8b94c728458b36100e131e07c6ce38e9afba13def6b75746d4adef

bounded-diagnostics-shadow-active.json:
be59a4c7cd31c5a4addbe0ba23bb0c2585bfe3836cec34fef31fe293635d558d
```

## 7. Architecture Decisions

### AD-147-1: REST authority is unchanged

MQTT remains a private low-latency hint. Only targeted REST reconciliation may
update public mower variables.

### AD-147-2: Restart work is deferred

`ApplyChanges()` performs deterministic cleanup and scheduling, not network
I/O. This keeps module startup bounded.

### AD-147-3: Recovery is finite

The fixed three-attempt sequence prevents an unavailable broker or transport
from creating an unbounded retry loop.

### AD-147-4: Token changes rebuild the connection

Authorization and broker credentials are never patched into an active
WebSocket. Rotation is disconnect, cleanup and one fresh connection.

### AD-147-5: No new public runtime surface

Recovery metadata stays in existing private lifecycle, statistics and error
attributes. Existing Device variables and Archive Control logging identities
are untouched.

### AD-147-6: Separate missing mower data from broker failure

Missing MQTT events while Core remains healthy do not trigger reconnect.
REST polling absorbs mower-side or cloud-side connectivity gaps. Broker
recovery is driven only by the owned Core transport state.

### AD-147-7: Defer long-interval half-open probes

The pilot stops automatic reconnect after three attempts. A slow autonomous
probe after exhaustion requires separate evidence and design before
production use, because the present implementation intentionally prefers a
stable REST-only fallback over indefinite MQTT retry.

## 8. Remaining Gates

This step does not authorize publication or persistent MQTT activation.

Before a passive pilot:

1. publish the exact recovery increment to the standalone module repository;
2. verify remote byte equality;
3. update Symcon while MQTT remains disabled;
4. verify Account, Configurator, Device, variables and archive settings;
5. stage the adopted transport inactive and credential-free;
6. explicitly authorize the bounded passive pilot;
7. observe restart, token rotation and disconnect behavior without MQTT
   commands or publish traffic;
8. distinguish healthy transport without mower messages from an actual Core
   disconnect;
9. decide from pilot evidence whether a long-interval circuit-breaker probe is
   required for unattended operation;
10. disable and clean the transport if any stop condition occurs.

## 9. Decision

Offline recovery hardening is accepted.

The next SAEF step should be:

```text
148-native-mqtt-passive-pilot-publication-and-symcon-test-plan.md
```

That step should freeze the exact publication files and hashes, define the
disabled pre-update baseline and separate publication, Symcon update, inactive
staging and passive live-pilot gates. It must not itself publish, mutate
Symcon or connect to MQTT.
