# 190 Native MQTT Core Resume Health Observation Temporary Activation Stop

**Case study:** Navimow native IP-Symcon module
**Status:** Gate E stopped on token-horizon contract; healthy activation
cleaned completely without restart
**Date:** 2026-07-29
**Scope:** Execute temporary activation Gate E from step 185

## 1. Purpose

Step 189 recorded renewed persistence and recovery acceptance for one bounded
receive-only activation and restart sequence.

This step:

1. reverified every immediate activation precondition;
2. enabled the retained transport exactly once;
3. observed one healthy delayed lifecycle connection;
4. captured two stable active projections;
5. detected that the second token-horizon baseline no longer met 1200 seconds;
6. stopped before restart and executed mandatory cleanup;
7. reverified the disabled installation and compatibility contracts.

## 2. Authorization

The user explicitly authorized:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Core-Resume-Health-Observation-Test freigegeben.
```

This authorized:

- one `EnableMqttShadow=true` mutation;
- one Account `ApplyChanges()`;
- the existing delayed lifecycle connection;
- bounded read-only diagnostics;
- immediate Disable fallback on any activation stop condition.

It did not authorize:

- an explicit MQTT Connect;
- a Symcon service restart;
- MQTT publish;
- mower commands;
- an activation retry;
- Core instance creation, deletion or reparenting.

## 3. Immediate Preconditions

The immediate precondition and compatibility projections passed:

```text
installed module:                    main@45c7bd50
module clean and valid:              true
MQTT feature:                        disabled
transport credential-free:          true
current kernel epoch known:          true
diagnostic kernel epoch exact:       true
current kernel already reconciled:   true
token remaining:                     1358 seconds
token validity >= 1200 seconds:      true
complete compatibility projection:   PASS
```

All 14 variables, five Archive Control logging contracts, queryable history,
command evidence, Receiver pairing and four canonical subscriptions were
intact.

## 4. Single Activation

Executed exactly:

```text
IPS_SetProperty(Account, "EnableMqttShadow", true): 1
IPS_ApplyChanges(Account):                           1
explicit NAVAC_ConnectMqttShadow calls:              0
```

The scheduled-state contract passed:

```text
feature enabled:           true
configuration status:      ready
lifecycle:                 ReconnectScheduled
transition reason:         restart-scheduled
connection-attempt delta:  0
```

The lifecycle timer then initiated exactly one connection.

## 5. Healthy Transport Evidence

The connection completed without failure:

```text
lifecycle:                     ShadowActive
transition reason:             healthy
last connection trigger:       initial
connection-attempt delta:      +1
connection-success delta:      +1
connection-failure delta:      0
Core-resume observation delta: 0
MQTT status:                   102
WebSocket status:              102
WebSocket Active:              true
```

Two natural receive-only messages arrived:

```text
Receiver call delta:       +2
Receiver forwarded delta:  +2
rejected delta:            0
```

No traffic was manufactured and no mower command was sent.

## 6. Active Baseline Stop Condition

Two active transport projections 95 seconds apart were equal for:

- kernel and reconciliation markers;
- lifecycle state and transition reason;
- connection and Core-resume counters;
- connection trigger and timestamps;
- Receiver counters;
- topology and Core configuration hashes;
- Core statuses and credential-presence Booleans.

Both showed a healthy active transport. The supplemental token projections
showed:

| Projection | Remaining | `>= 1200 s` |
|---|---:|---|
| first | 1222 s | true |
| second | 1132 s | false |

Step 185 requires the active baseline to carry a
`token-valid-for-at-least-1200-seconds` contract. The second projection did not
meet that threshold.

Classification:

```text
module activation:       healthy
transport stability:     PASS
token-horizon contract:  STOP
Gate E:                  NOT PASSED
Gate F restart:          NOT ENTERED
```

This is a test-readiness stop, not evidence of a module or transport defect.

## 7. Mandatory Cleanup

The already authorized fallback executed immediately:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
```

Cleanup verified:

```text
MQTT feature:               disabled
lifecycle:                  Disabled
next attempt:               0
reconnect attempt:          0
WebSocket:                  inactive
Authorization headers:     empty
MQTT username and password: empty
MQTT Client ID:             retained
Receiver selection:         retained
```

The final inactive and compatibility projections both passed.

## 8. Compatibility Closure

| Contract | Result |
|---|---|
| installed module | clean and valid `main@45c7bd50` |
| productive instance identities and parents | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| Receiver pairing | retained |
| subscriptions | 4 canonical QoS-0 entries |

The mower-variable logging and accumulated archive history remain intact.

## 9. Architecture Decisions

### AD-NAV-671: Enforce the frozen token baseline

**Decision:** Do not reinterpret the 1200-second active baseline after
activation.

**Reason:** Relaxing a live-test precondition after it fails would make the
restart evidence incomparable with the frozen plan.

### AD-NAV-672: Distinguish readiness stop from module failure

**Decision:** Classify the run as a controlled Gate-E stop.

**Reason:** Activation, transport health, natural ingress and cleanup all
worked; only the remaining observation horizon was insufficient.

### AD-NAV-673: Consume activation and persistence acceptance

**Decision:** Treat the one activation and its credential-persistence window
as consumed even though no restart occurred.

**Reason:** Credentials were temporarily stored in the owned Core instances,
which is the accepted risk Gate D bounded.

### AD-NAV-674: Require a stronger retry threshold

**Decision:** Before another activation, derive a threshold that covers
connection health observation, two separated baselines, operator handoff, a
multi-minute service startup, post-ready observation and cleanup.

**Reason:** Checking exactly 1200 seconds before activation left no reliable
margin for all required pre-restart evidence and human coordination.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature enable | 1 |
| Account activation `ApplyChanges()` | 1 |
| automatic delayed connection attempts | 1 |
| explicit MQTT Connect | 0 |
| cleanup disable | 1 |
| cleanup `ApplyChanges()` | 1 |
| natural received messages | 2 |
| MQTT publish | 0 |
| mower commands | 0 |
| Symcon service restarts | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 11. Current State

```text
module commit:          45c7bd50
MQTT feature:           disabled
lifecycle:              Disabled
Core credentials:       empty
public state authority: REST
restart performed:      no
cleanup:                PASS
```

## 12. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-temporary-activation/
    gate-e-stop-and-cleanup.json
```

No credential value, topic, endpoint, payload, device identity, ObjectID,
hostname or private IP address appears in this public report.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive staging | PASS |
| Gate D persistence and recovery acceptance | CONSUMED |
| Gate E temporary activation | CONTROLLED STOP |
| Gate F active restart | CLOSED |
| mandatory cleanup | PASS |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 14. Recommended Next Step

Create:

```text
191-native-mqtt-core-resume-health-observation-token-horizon-retry-plan.md
```

The plan must:

- observe passive token refresh without manually invoking authentication;
- derive a conservative activation threshold from the complete test timeline;
- require fresh disabled and credential-free staging;
- require renewed persistence and recovery acceptance;
- require separate activation and restart authorizations;
- permit no activation retry under the consumed Gate-E authorization.
