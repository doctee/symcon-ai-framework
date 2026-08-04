# 236 Native MQTT Pilot Diagnostics Activation and Active Baselines

**Case study:** Navimow native IP-Symcon module

**Status:** One receive-only activation passed, native pilot diagnostics
started and two stable active baselines accepted

**Date:** 2026-07-30

**Scope:** Execute the separately authorized activation for the diagnostic
72-hour private pilot

## 1. Authorization

The user explicitly authorized:

```text
Aktivierung des receive-only MQTT-Transports für den überwachten
72-Stunden-Private-Pilot freigegeben.
```

Step 235 had already closed commit-bound persistence acceptance and passive
token readiness. This authorization covered one activation and its automatic
connection. It did not authorize a service restart, MQTT publication or mower
command.

## 2. Immediate Preflight

Immediately before mutation, a complete bounded projection proved:

```text
repository:               clean and valid main@793249ec
harness phase:            ready-for-acceptance
token remaining:          2806 seconds
required minimum:         2400 seconds
Account:                   Connected
ReauthRequired:            false
REST:                      operational and authoritative
MQTT feature:              disabled
lifecycle:                 Disabled
MQTT/WebSocket:            104/104
Authorization header:      absent
MQTT username/password:    absent
variables:                 14 retained
Archive loggings:          5 retained
pilot diagnostics:         inactive and empty
```

All MCP channels passed with no transport error, execution error or
truncation.

## 3. Single Activation

The private one-shot runner repeated all time-dependent, ownership and
credential-free preconditions inside the mutation.

Internal token horizon:

```text
2794 seconds
```

Executed exactly:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
cleanup fallback:          0
```

The immediate lifecycle state was the expected automatic path:

```text
ReconnectScheduled
```

## 4. Automatic Connection

The first read after activation found the Core already ready but the Account
still transitional:

```text
MQTT/WebSocket:      102/102
lifecycle:           Connecting
connection attempts: +1
successes:           not yet recorded
```

This read was not ingested as a baseline.

Without an explicit Connect call, the next projection reached:

```text
MQTT/WebSocket:          102/102
WebSocket active:        true
Authorization present:  true
MQTT username/password:  present
lifecycle:               ShadowActive
transition reason:       healthy
connection attempts:     +1
connection successes:    +1
connection failures:     0
```

## 5. Native Pilot Diagnostics

The first validated connection attempt started exactly one internal
observation session:

```text
formatVersion:             1
active:                    true
sessionSequence:           1
checkpointIntervalSeconds: 18000
completed checkpoints:     0
completed episodes:        0
credential rotations:      0
open episode:              none
```

The first absolute five-hour checkpoint is scheduled for approximately:

```text
2026-07-30 13:03:58 CEST
```

Two diagnostic reads did not change the persisted schedule.

## 6. Active Baselines

Two complete baseline projections were accepted 94 seconds apart:

| Contract | First | Second | Result |
|---|---|---|---|
| lifecycle | `ShadowActive` | `ShadowActive` | EQUAL |
| transition reason | `healthy` | `healthy` | EQUAL |
| MQTT/WebSocket | `102/102` | `102/102` | EQUAL |
| connection attempts/successes | `31/23` | `31/23` | EQUAL |
| connection failures | `0` | `0` | EQUAL |
| reconnect state | retained | retained | EQUAL |
| structural contracts | baseline | baseline | EQUAL |
| pilot session | sequence 1 | sequence 1 | EQUAL |
| next native checkpoint | retained | retained | EQUAL |

No MQTT message arrived during the short baseline interval while the mower was
docked. This is not a transport failure: both Core instances and the Account
lifecycle remained healthy. Natural ingress remains required during the
multi-cycle observation.

## 7. Pilot Clock

After ingesting both baselines, the private harness reports:

```text
phase:                active
classification:       RUNNING
started:              2026-07-30 08:07:08 CEST
earliest completion:  2026-08-01 08:07:08 CEST
hard deadline:        2026-08-02 08:07:08 CEST
completed cycles:     0
credential rotations: 0
transport episodes:   0
stop reasons:         none
```

The second active baseline defines the fixed clock. The deadline cannot be
extended.

## 8. Cleanup Obligation

Activation now arms mandatory cleanup for every normal completion, hard stop,
failure or ambiguous result:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
WebSocket Active:          false
Authorization headers:    empty
MQTT username/password:    empty
```

Cleanup must be verified immediately and again after at least 180 seconds.

## 9. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-pilot-diagnostics-activation/
  evidence-closure.json
  pilot-state.json
  snapshots/active-01.json
  snapshots/active-02.json
```

The public report contains no credential value, ObjectID, MQTT topic, payload,
coordinate, hostname or private device identity.

## 10. Architecture Decisions

### AD-NAV-859: Recheck token readiness inside the mutation

The external snapshot is time-dependent. The one-shot runner therefore
revalidates the 2400-second threshold before changing the property.

### AD-NAV-860: Exclude the transitional connection read

Core status `102` alone does not prove that the Account recorded a successful
connection. Only `ShadowActive/healthy` is baseline-ready.

### AD-NAV-861: Start diagnostics on connection, not configuration

The native session begins with the first validated connection attempt. This
preserves timerless inactive staging.

### AD-NAV-862: Start the pilot clock at the second stable baseline

Connection setup time does not count as stable pilot evidence.

### AD-NAV-863: Arm cleanup at activation

Credential-bearing Core configuration now exists intentionally. Complete
immediate and delayed cleanup is mandatory for every terminal outcome.

## 11. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature enable | 1 |
| Account activation `ApplyChanges()` | 1 |
| automatic connection attempts | 1 |
| automatic connection successes | 1 |
| explicit MQTT Connect | 0 |
| cleanup fallback | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| created or deleted objects | 0 |

## 12. Gate Decision

| Gate | Decision |
|---|---|
| persistence acceptance | PASS |
| passive token readiness | PASS |
| separate activation authorization | PASS |
| fresh mutation preconditions | PASS |
| one receive-only activation | PASS |
| automatic connection | PASS |
| native pilot session | ACTIVE |
| two stable active baselines | PASS |
| harness phase | `active` |
| pilot classification | `RUNNING` |
| REST authority | RETAINED |
| cleanup | ARMED |
| service restart | NOT AUTHORIZED |
| MQTT publish | PROHIBITED |
| mower command | NOT PERFORMED |

## 13. Next Step

Proceed with:

```text
237-native-mqtt-pilot-first-native-checkpoint-observation.md
```

After the scheduled checkpoint around 13:03:58 CEST, capture one bounded
read-only projection and ingest it as `checkpoint`. It must prove one native
checkpoint, continued `ShadowActive/healthy`, unchanged contracts, valid
bounded episode/rotation evidence and no stop reason.
