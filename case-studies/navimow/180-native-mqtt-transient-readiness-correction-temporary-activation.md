# 180 Native MQTT Transient Readiness Correction Temporary Activation

**Case study:** Navimow native IP-Symcon module
**Status:** Gate E passed; receive-only transport healthy and temporarily
active, restart gate closed
**Date:** 2026-07-29
**Scope:** Execute only temporary activation Gate E from step 175

## 1. Purpose

Step 179 recorded renewed credential-persistence acceptance for one bounded
receive-only activation and restart sequence.

This step:

1. reverified every activation precondition;
2. enabled the retained transport exactly once;
3. allowed only the delayed Account lifecycle connection;
4. observed the complete 60-second Core health interval;
5. captured two stable active pre-restart baselines;
6. repeated the complete compatibility projection;
7. stopped before any service restart.

## 2. Authorization

The user explicitly authorized:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Transient-Readiness-Restarttest freigegeben.
```

This authorized:

- one `EnableMqttShadow=true` mutation;
- one Account `ApplyChanges()`;
- the existing delayed lifecycle connection;
- bounded read-only diagnostics;
- immediate Disable fallback on any activation stop condition.

It did not authorize:

- an explicit Connect;
- a Symcon service restart;
- MQTT publish;
- mower commands;
- Core instance creation, deletion or reparenting.

## 3. Immediate Preconditions

The precondition projections passed:

```text
installed module:                    main@7d141f76
module clean and valid:              true
MQTT feature:                        disabled
transport credential-free:          true
current kernel epoch known:          true
diagnostic kernel epoch exact:       true
current kernel already reconciled:   true
token validity >= 900 seconds:       true
Account authentication usable:       true
complete compatibility projection:   PASS
```

The baseline retained:

- 14 expected variables;
- five expected Archive Control logging contracts;
- queryable logged history;
- unchanged command evidence;
- exact Receiver pairing and four canonical QoS-0 subscriptions.

## 4. Single Activation

Executed exactly:

```text
IPS_SetProperty(Account, "EnableMqttShadow", true): 1
IPS_ApplyChanges(Account):                           1
explicit NAVAC_ConnectMqttShadow calls:              0
```

After 750 milliseconds:

```text
feature enabled:           true
configuration status:      ready
lifecycle:                 ReconnectScheduled
transition reason:         restart-scheduled
next attempt scheduled:    true
connection-attempt delta:  0
```

No fallback was required.

## 5. Delayed Connection

The Account lifecycle timer initiated exactly one connection:

```text
intermediate lifecycle:       Connecting
intermediate reason:          connection-attempt
last connection trigger:      initial
connection-attempt delta:     +1
connection-success delta:     +1
connection-failure delta:     0
Core-resume observation delta: 0
explicit Connect calls:        0
```

The intermediate `Connecting` state was retained until the normal 60-second
Core health observation completed.

Final transport state:

```text
lifecycle:            ShadowActive
transition reason:    healthy
MQTT status:          102
WebSocket status:     102
WebSocket Active:     true
```

## 6. Active Pre-Restart Baseline

The existing private read-only projection was reused:

```text
private/navimow-capture/
  native-mqtt-core-resume-ordering-active-baseline-readonly.php
```

It records only bounded counters, timestamps, hashes and credential-presence
Booleans.

Two executions 87 seconds apart both passed and were equal for:

- kernel epoch and reconciliation markers;
- lifecycle state and transition;
- connection and Core-resume counters;
- connection trigger and timestamps;
- Receiver counters;
- topology and complete configuration hashes;
- Core status and credential-presence Booleans.

Both showed:

```text
lifecycle:                     ShadowActive / healthy
last connection trigger:       initial
connection attempts/successes: 12 / 4
connection failures:           0
Core-resume observations:      0
Core:                          102 / 102
WebSocket Active:              true
ownership validation:          valid / ready
token validity >= 900 seconds: true
cleanup:                       armed
```

`lastKernelCoreClassification=none` is expected before restart. The restarted
kernel must populate that field through the corrected post-ready
reconciliation.

## 7. Natural Ingress

Two natural receive-only mower messages arrived during activation:

```text
Receiver call delta:       +2
Receiver forwarded delta:  +2
rejected delta:            0
```

No traffic was manufactured and no mower command was sent.

## 8. Compatibility

The complete active-state compatibility projection passed.

| Contract | Result |
|---|---|
| module | `main@7d141f76`, clean and valid |
| productive instance identities and parents | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| token | usable |
| Receiver pairing | retained |
| subscriptions | 4 canonical QoS-0 entries |

REST remains the only authority for public mower variables.

## 9. Architecture Decisions

### AD-NAV-627: Require a reconciled current epoch before activation

**Decision:** Activate only after installed diagnostics match the current
kernel epoch and show completed reconciliation.

**Reason:** The activation path must remain distinct from the post-restart path
under test.

### AD-NAV-628: Preserve normal delayed connection and health timing

**Decision:** Allow the lifecycle timer to initiate the only connection and
wait for its complete 60-second health observation.

**Reason:** An explicit Connect or shortened health window would invalidate
the counter and trigger contract.

### AD-NAV-629: Freeze two equal active baselines

**Decision:** Require two passing projections separated by more than one
lifecycle period before opening Gate F.

**Reason:** The restart result needs exact causal counters, triggers, topology
and configuration baselines.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature enable | 1 |
| Account activation `ApplyChanges()` | 1 |
| automatic delayed connection attempts | 1 |
| explicit MQTT Connect | 0 |
| fallback disable | 0 |
| natural received messages | 2 |
| MQTT publish | 0 |
| mower commands | 0 |
| Symcon service restarts | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 11. Current State

The bounded receive-only transport is temporarily active:

```text
module commit:          7d141f76
MQTT feature:           enabled
lifecycle:              ShadowActive / healthy
Core transport:         102 / 102
public state authority: REST
cleanup:                armed and mandatory
```

Authorization and MQTT credentials are currently stored in the owned Core
instances under the exact acceptance from step 179.

## 12. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-transient-readiness-correction-temporary-activation/
    gate-e-evidence-closure.json
```

No credential value, topic, endpoint, payload, Device ID, ObjectID, hostname
or private IP address appears in this public report.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive staging | PASS |
| Gate D persistence acceptance | PASS |
| Gate E temporary activation | PASS |
| active pre-restart baseline | PASS |
| Gate F active restart | CLOSED |
| Gate G mandatory cleanup | ARMED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 14. Required Next Authorization

Gate F requires:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur Transient-Readiness-Core-Resume-Prüfung ist freigegeben.
```

The user performs the external restart and then confirms completion. No
restart is initiated through Symcon PHP.

After pass, failure or ambiguity, Gate G cleanup executes before any further
test.
