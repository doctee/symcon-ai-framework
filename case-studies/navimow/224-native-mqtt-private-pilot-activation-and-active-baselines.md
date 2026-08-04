# 224 Native MQTT Private Pilot Activation and Active Baselines

**Case study:** Navimow native IP-Symcon module
**Status:** One receive-only activation passed, two stable active baselines
accepted and the fixed 72-hour private-pilot clock is running
**Date:** 2026-07-29
**Scope:** Execute the separately authorized activation and initialize the
active observation phase

## 1. Authorization

The user explicitly authorized:

```text
Aktivierung des receive-only MQTT-Transports für den überwachten
72-Stunden-Private-Pilot freigegeben.
```

Step 221 had already recorded the separate persistence and recovery
acceptance. Step 223 had proved a passive OAuth refresh without manual
authentication.

This authorization covered one activation and its automatic connection. It
did not authorize a service restart, MQTT publication or mower command.

## 2. Immediate Preflight

Immediately before mutation, the frozen private read-only projection proved:

```text
repository:               clean and valid main@3d223a9c
token remaining:          2711 seconds
required minimum:         2400 seconds
Account:                   Connected
ReauthRequired:            false
REST:                      operational and authoritative
MQTT feature:              disabled
lifecycle:                 Disabled
MQTT/WebSocket:            inactive
Authorization header:      absent
MQTT username/password:    absent
structural contracts:      equal to inactive baseline
```

Every MCP channel passed:

```text
transportError: null
executionError: null
truncated:      false
projection pass: true
```

## 3. Single Activation

The private activation runner repeated the time-dependent and credential-free
preconditions inside the mutation. It retained:

```text
token remaining: 2643 seconds
```

Executed exactly:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
cleanup fallback:          0
```

Immediate result:

```text
feature enabled:       true
configuration:         ready
validation:            ready
lifecycle:             ReconnectScheduled
transition reason:     restart-scheduled
```

The intermediate state is the expected automatic connection path.

## 4. Automatic Connection

Without an explicit Connect call, the owned Core chain reached:

```text
MQTT status:             102
WebSocket status:        102
WebSocket active:        true
Authorization present:  true
MQTT username present:  true
MQTT password present:  true
lifecycle:               ShadowActive
transition reason:       healthy
```

Activation counter delta:

| Counter | Delta |
|---|---:|
| connection attempts | +1 |
| connection successes | +1 |
| connection failures | 0 |

No reconnect retry or cleanup fallback was needed.

## 5. Active Baselines

Two complete active projections were accepted 77 seconds apart:

| Contract | First | Second | Result |
|---|---:|---:|---|
| lifecycle | `ShadowActive` | `ShadowActive` | EQUAL |
| transition reason | `healthy` | `healthy` | EQUAL |
| attempts/successes/failures | `16/8/0` | `16/8/0` | EQUAL |
| unexpected disconnects | 2 | 2 | EQUAL |
| reconnect attempts/exhausted | `1/0` | `1/0` | EQUAL |
| credential rotations | 0 | 0 | EQUAL |
| MQTT/WebSocket | `102/102` | `102/102` | EQUAL |
| structural contracts | baseline | baseline | EQUAL |

The token horizons were 2556 and 2480 seconds. Authentication remained
connected and reauthentication was not required.

## 6. Natural Receive-Only Evidence

Between the last inactive baseline and the second active baseline:

```text
received:                 +84
accepted:                 +84
rejected:                   0
reconciliation attempts:   +4
comparison matches:        +1
comparison mismatches:      0
```

The second baseline contained an available identity-free MQTT hint and a
matching REST comparison. MQTT did not directly write public Device variables;
REST remained authoritative.

No private payload, topic, device identity, coordinate or mower state is
published in this report.

## 7. Harness Start

Both active baselines were ingested successfully.

Harness status:

```text
phase:                active
classification:       RUNNING
started:              2026-07-29 19:19:00 Europe/Berlin
earliest completion:  2026-07-31 19:19:00 Europe/Berlin
hard deadline:        2026-08-01 19:19:00 Europe/Berlin
completed cycles:     0
credential rotations: 0
transport episodes:   0
stop reasons:         none
```

The second active baseline defines the pilot clock. The deadline cannot be
extended.

## 8. Cleanup Obligation

Activation arms mandatory cleanup for every later pass, failure, stop or
ambiguous result:

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
private/navimow-capture/output/native-mqtt-private-pilot/
  activation-evidence-closure.json
  pilot-state.json
  snapshots/active-01.json
  snapshots/active-02.json
```

The activation runner is private:

```text
private/navimow-capture/native-mqtt-private-pilot/activate-once.php
SHA-256:
27436c9f5cfa0a6458dd74a7ae23431fec34b37151cd8b43cb8a6507fe7bbeba
```

## 10. Architecture Decisions

### AD-NAV-809: Recheck the threshold inside the mutation

**Decision:** Repeat authentication, 2400-second, inactive and credential-free
checks in the one-shot activation runner.

**Reason:** The external preflight is time-dependent and cannot protect a
later mutation by itself.

### AD-NAV-810: Exclude the early Core-up snapshot

**Decision:** Do not ingest the first `102/102` snapshot before the Account
records the automatic connection success.

**Reason:** Its attempt/success signature was still transitional and could not
form a stable baseline pair.

### AD-NAV-811: Start the clock at the second stable baseline

**Decision:** Set the fixed pilot timestamps only after two equal active
projections at least 65 seconds apart.

**Reason:** Connection setup time is not evidence of stable pilot operation.

### AD-NAV-812: Arm cleanup at activation

**Decision:** Treat complete credential cleanup as mandatory from the moment
the feature is enabled.

**Reason:** Authorization and MQTT credentials are now intentionally retained
in the owned IP-Symcon Core instances.

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
| internal activation preconditions | PASS |
| one receive-only activation | PASS |
| automatic connection | PASS |
| two stable active baselines | PASS |
| harness phase | `active` |
| pilot classification | `RUNNING` |
| REST authority | RETAINED |
| cleanup | ARMED |
| service restart | NOT AUTHORIZED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

## 13. Next Step

Proceed with:

```text
225-native-mqtt-private-pilot-early-stability-checkpoint.md
```

At approximately `+15 minutes`, capture one bounded read-only checkpoint and
ingest it as `checkpoint`. It must prove continued `ShadowActive/healthy`,
REST authority, unchanged contracts, valid counters and no stop reason.
