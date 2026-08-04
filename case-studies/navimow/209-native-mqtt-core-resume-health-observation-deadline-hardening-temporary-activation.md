# Native MQTT Core-Resume Deadline Hardening Temporary Activation

**Case study:** Navimow native IP-Symcon module
**Status:** Gate G and restart arm passed; receive-only transport active,
external restart separately gated
**Date:** 2026-07-29
**Scope:** Execute temporary activation Gate G and read-only restart arm

## 1. Authorization

The user explicitly authorized:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den 180-Sekunden-Core-Resume-Test freigegeben.
```

This authorized one activation and its automatic connection. It did not
authorize the external service restart.

## 2. Immediate Preflight

Immediately before mutation, a bounded read-only preflight proved:

```text
token remaining:             2835 seconds
required before activation:  2400 seconds
MQTT:                        disabled and credential-free
Account:                     Connected
ReauthRequired:              false
REST:                        operational
Account/Receiver pairing:    exact
```

The activation operation repeated the time-dependent authentication checks
inside the same execution and retained 2795 seconds.

## 3. Single Activation

Executed exactly:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
fallback disable:          0
```

The lifecycle first entered `ReconnectScheduled`, then made one automatic
connection attempt. The native Core reached `102/102` before the Account
confirmed the next normal lifecycle cycle as healthy.

## 4. Active Baselines

Two qualifying projections 99 seconds apart were equal:

```text
lifecycle:                     ShadowActive / healthy
MQTT / WebSocket status:       102 / 102
WebSocket Active:              true
connection attempts/successes: 15 / 7
connection failures:           0
Core-resume observations:      1
open Core observation count:   0
open Core deadline:            0
pending reconnect:             0
Receiver calls/forwarded:      316 / 316
```

The activation produced deltas of exactly one connection attempt and one
success, with no failure. Natural receive-only ingress was not manufactured
and the Receiver counters remained stable.

Token horizons:

| Point | Remaining | Required |
|---|---:|---:|
| mutation precondition | 2795 s | 2400 s |
| first active baseline | 2684 s | — |
| second active baseline | 2585 s | — |
| restart arm | 2559 s | 1800 s |

Both complete compatibility projections proved:

- installed clean and valid `main@8fdab84b`;
- 14 unchanged variable contracts;
- five unchanged Archive Control logging contracts;
- queryable history;
- stable command evidence;
- identical topology, Core configuration, identity and archive hashes.

## 5. Restart Arm

The read-only arm projection at `2026-07-29T12:42:39Z` froze:

- the old kernel epoch;
- all connection and Receiver counters;
- last connection trigger and timestamps;
- current Core statuses;
- topology and configuration hashes;
- the 2559-second token horizon.

No restart occurred in this step.

## 6. Architecture Decisions

### AD-NAV-739: Recheck the activation threshold inside the mutation

**Decision:** Repeat the 2400-second and authentication checks before changing
the feature property.

**Reason:** This removes reliance on an aging read-only preflight.

### AD-NAV-740: Accept the normal intermediate Connecting state

**Decision:** Wait one normal lifecycle cycle after the Core reached
`102/102`.

**Reason:** Core health precedes the Account's scheduled lifecycle
confirmation; no explicit Connect or retry was necessary.

### AD-NAV-741: Freeze two complete active baselines

**Decision:** Require equal lifecycle and compatibility projections more than
65 seconds apart.

**Reason:** The restart result needs stable causal counters, Core state and
public-state contracts.

### AD-NAV-742: Stop before the external restart

**Decision:** End after the read-only restart arm.

**Reason:** Gate-G authorization does not authorize an external service
operation.

## 7. Side-Effect Accounting

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

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 8. Current State

```text
module commit:          8fdab84b
MQTT feature:           enabled
lifecycle:              ShadowActive / healthy
Core transport:         102 / 102
token horizon at arm:   2559 seconds
public state authority: REST
mandatory cleanup:      armed
```

Authorization and MQTT credentials are currently stored in the owned Core
instances under the accepted bounded test window.

## 9. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-deadline-hardening-activation/
    gate-g-restart-arm-evidence-closure.json
```

No credential value, topic, endpoint, payload, device identity, ObjectID,
hostname or private IP address appears in this report.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| Gate G temporary activation | PASS |
| active baseline stability | PASS |
| restart-arm threshold | PASS |
| Gate H external restart | CLOSED |
| Gate I mandatory cleanup | ARMED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

## 11. Required Next Authorization

```text
Ein einmaliger beaufsichtigter Symcon-Neustart für den 180-Sekunden-Core-Resume-Test ist freigegeben.
```

The restart must be initiated externally, never through Symcon PHP. Before it
is performed, the arm threshold is rechecked. If the current horizon is below
1800 seconds, no restart occurs and mandatory cleanup runs instead.
