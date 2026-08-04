# 247 Native MQTT Episode Diagnostic Hardening Activation and Active Baselines

**Case study:** Navimow native IP-Symcon module

**Status:** Receive-only MQTT private pilot activated once; two stable active
baselines accepted and fixed 72-hour clock started

**Date:** 2026-08-01

**Scope:** Activate the hardened MQTT diagnostic candidate under the accepted
private-pilot policy without changing REST authority

## 1. Authorization

The user explicitly authorized one receive-only activation on:

```text
79686e52f0bbaad77d37b9cd6e4b367797d96f2e
```

The authorization did not include a service restart, MQTT publication, an
explicit MQTT connect call or a mower command.

## 2. Mutation-Time Preflight

The bounded projection immediately before activation passed with separate
channel checks:

```text
transportError: null
executionError: null
truncated:      false
projection:     PASS
```

It proved:

```text
repository:             clean and valid main@79686e5
harness phase:          ready-for-acceptance
token remaining:        2699 seconds
required minimum:       2400 seconds
Account:                Connected
ReauthRequired:         false
REST:                   operational and authoritative
MQTT feature:           disabled
lifecycle:              Disabled
MQTT/WebSocket:         104/104
WebSocket active:       false
Authorization present: false
MQTT user/password:     absent
variables:              14 retained
Archive loggings:       5 retained
```

All five structural hashes matched the accepted inactive baseline.

## 3. Single Activation

The private one-shot runner repeated commit, structure, archive, REST,
authentication, ownership and credential-free checks inside the mutation.
Its internal token horizon was `2574` seconds.

Executed exactly:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
cleanup fallback:          0
```

The immediate lifecycle was the expected automatic state
`ReconnectScheduled`.

## 4. Automatic Connection

The first read after activation found both Core instances ready but the Account
still in `Connecting`. It was retained as transition evidence and excluded from
the baseline.

The following read reached:

```text
MQTT/WebSocket:          102/102
WebSocket active:        true
Authorization present:  true
MQTT user/password:      present
lifecycle:               ShadowActive
transition reason:       healthy
connection attempts:     +1
connection successes:    +1
connection failures:     0
```

Credential values were neither returned nor recorded.

## 5. Active Baselines

Two complete active projections passed `96` seconds apart:

| Contract | First | Second | Result |
|---|---|---|---|
| lifecycle | `ShadowActive/healthy` | `ShadowActive/healthy` | EQUAL |
| MQTT/WebSocket | `102/102` | `102/102` | EQUAL |
| connection attempts/successes | `49/41` | `49/41` | EQUAL |
| connection failures | `0` | `0` | EQUAL |
| structural hashes | accepted | accepted | EQUAL |
| variables/Archive loggings | `14/5` | `14/5` | EQUAL |
| pilot session | sequence 2 | sequence 2 | EQUAL |
| next native checkpoint | retained | retained | EQUAL |

No new MQTT ingress was required during this short docked baseline interval.
Natural ingress remains observation evidence for later mowing cycles.

## 6. Pilot Clock

The second active baseline starts the immutable observation window:

```text
phase:                active
classification:       RUNNING
started:              2026-08-01 08:45:41 CEST
earliest completion:  2026-08-03 08:45:41 CEST
hard deadline:        2026-08-04 08:45:41 CEST
first native checkpoint: approximately 2026-08-01 13:43:02 CEST
completed cycles:     0
new credential rotations: 0
new transport episodes:   0
stop reasons:         none
```

The deadline cannot be extended by later checkpoints or reconnects.

## 7. Operating Boundary

During the pilot:

- REST remains the sole public-state authority;
- MQTT remains receive-only and supplies diagnostics and hints only;
- existing bounded transient recovery may reconnect automatically;
- authentication or configuration failures are not retried as transient
  network failures;
- no service restart, publish operation or mower command is part of this step;
- every terminal result requires immediate and delayed credential-free cleanup.

## 8. Private Evidence

Machine-readable evidence is retained below:

```text
private/navimow-capture/output/
  native-mqtt-episode-diagnostic-hardening-activation/
  activation-result.json
  evidence-closure.json
  pilot-state.json
  preflight.json
  snapshots/active-01-projection.json
  snapshots/active-02-projection.json
  snapshots/transitional-connecting.json
```

The public report contains no credential, topic, ObjectID, device identity,
coordinate, hostname or private payload.

## 9. Architecture Decisions

### AD-NAV-911: Repeat the complete gate inside the one-shot runner

Time-dependent and mutable preconditions are checked again in the same
execution that changes the Account property.

### AD-NAV-912: Exclude transitional Core readiness

Core status `102` does not by itself prove Account health. Only
`ShadowActive/healthy` is eligible as an active baseline.

### AD-NAV-913: Start the clock at the second stable baseline

Connection setup and the first healthy observation do not count as stable
pilot duration.

### AD-NAV-914: Retain REST authority during active MQTT observation

MQTT data remains diagnostic evidence and a low-latency hint. It does not write
the public device variables in this pilot.

### AD-NAV-915: Arm cleanup at successful activation

The owned Core instances now intentionally retain transport credentials.
Immediate and delayed cleanup is mandatory on every terminal path.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only projections | 4 |
| MQTT feature enable | 1 |
| Account activation `ApplyChanges()` | 1 |
| automatic connection attempts | 1 |
| automatic connection successes | 1 |
| explicit MQTT Connect | 0 |
| cleanup fallback | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| created or deleted Symcon objects | 0 |

## 11. Gate Decision

| Gate | Decision |
|---|---|
| exact installed commit | PASS |
| fresh mutation preconditions | PASS |
| one receive-only activation | PASS |
| automatic connection | PASS |
| two stable active baselines | PASS |
| harness phase | `active` |
| pilot classification | `RUNNING` |
| REST authority | RETAINED |
| cleanup | ARMED |
| service restart | NOT PERFORMED |
| MQTT publish | PROHIBITED |
| mower command | NOT PERFORMED |

## 12. Next Step

Proceed with an early read-only stability checkpoint after the transport has
had time to receive natural traffic. It must ingest the current projection into
the fixed pilot state, classify any new episode or credential rotation and
leave the active deadline unchanged.
