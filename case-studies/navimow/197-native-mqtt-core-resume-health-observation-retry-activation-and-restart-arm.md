# 197 Native MQTT Core Resume Health Observation Retry Activation and Restart Arm

**Case study:** Navimow native IP-Symcon module
**Status:** Retry activation and restart arm passed; receive-only transport
active, external restart separately gated
**Date:** 2026-07-29
**Scope:** Execute retry activation Gate D and read-only restart arm Gate E

## 1. Authorization

The user explicitly authorized:

```text
Temporäre Retry-Aktivierung des receive-only MQTT-Transports mit 2400-Sekunden-Tokenhorizont freigegeben.
```

This authorized one activation and its automatic delayed connection. It did
not authorize the external service restart.

## 2. Single Activation

The activation operation rechecked every precondition inside the same bounded
execution:

```text
token remaining:             3354 seconds
required before activation:  2400 seconds
MQTT before activation:      disabled and credential-free
Account:                     Connected
ReauthRequired:              false
```

Executed exactly:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
fallback disable:          0
```

The delayed lifecycle produced one attempt and one success without failure.

## 3. Active Baselines

Two active projections 125 seconds apart were identical:

```text
lifecycle:                     ShadowActive / healthy
MQTT / WebSocket status:       102 / 102
WebSocket Active:              true
connection attempts/successes: 14 / 6
connection failures:           0
Core-resume observations:      0
```

Token horizons:

| Point | Remaining | Required |
|---|---:|---:|
| first active baseline | 3252 s | 2100 s |
| second active baseline | 3127 s | 2100 s |
| restart arm | 3100 s | 1800 s |

All topology and Core configuration hashes remained stable. Complete active
compatibility passed with 14 variables and five logging contracts unchanged.

## 4. Restart Arm

The read-only arm projection froze:

- the old kernel epoch;
- all connection and Receiver counters;
- last connection trigger and timestamps;
- current Core statuses;
- topology and configuration hashes;
- the 3100-second token horizon.

No restart occurred in this step.

## 5. Architecture Decisions

### AD-NAV-699: Check threshold inside the mutation operation

**Decision:** Recheck 2400 seconds before setting the property.

**Reason:** This removes the race between a separate readiness result and
activation.

### AD-NAV-700: Require two full active baselines

**Decision:** Arm restart only after stable projections more than 65 seconds
apart.

**Reason:** The restarted result needs frozen causal counters and hashes.

### AD-NAV-701: Keep the restart external and separate

**Decision:** Stop after the restart-arm projection.

**Reason:** Activation authorization does not authorize a service operation.

## 6. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature enable | 1 |
| Account activation `ApplyChanges()` | 1 |
| automatic connection attempts | 1 |
| automatic connection successes | 1 |
| explicit MQTT Connect | 0 |
| MQTT publish | 0 |
| mower commands | 0 |
| service restarts | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

## 7. Current State

```text
module commit:          45c7bd50
MQTT feature:           enabled
lifecycle:              ShadowActive / healthy
Core transport:         102 / 102
token horizon at arm:   3100 seconds
public state authority: REST
mandatory cleanup:      armed
```

Authorization and MQTT credentials are currently stored in the owned Core
instances under the accepted bounded test window.

## 8. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-retry-activation/
    gate-d-e-evidence-closure.json
```

No credential value, topic, endpoint, payload, device identity, ObjectID,
hostname or private IP address appears in this report.

## 9. Required Next Authorization

```text
Ein einmaliger beaufsichtigter Symcon-Neustart für den Core-Resume-Health-Observation-Retry ist freigegeben.
```

The user performs the external restart and reports completion. No restart is
initiated through Symcon PHP. Cleanup remains mandatory after every result.
