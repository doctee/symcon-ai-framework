# 171 Native MQTT Core Resume Ordering Correction Temporary Activation

**Case study:** Navimow native IP-Symcon module
**Status:** Gate E passed; receive-only transport healthy and temporarily
active, corrected restart gate closed
**Date:** 2026-07-29
**Scope:** Execute only temporary activation Gate E from step 166

## 1. Purpose

Step 170 recorded renewed credential-persistence acceptance for one bounded
receive-only activation and restart sequence.

This step:

1. verified every activation precondition;
2. enabled the retained transport exactly once;
3. allowed only the delayed Account lifecycle connection;
4. observed the complete 60-second Core health interval;
5. captured two stable active pre-restart baselines;
6. stopped before any service restart.

## 2. Authorization

The user explicitly authorized:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den korrigierten Core-Resume-Restarttest freigegeben.
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

The precondition projection passed:

```text
installed module:                    main@71a90f69
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
last connection trigger:      initial
connection-attempt delta:      +1
connection-success delta:      +1
connection-failure delta:      0
Core-resume observation delta: 0
explicit Connect calls:        0
```

The intermediate `Connecting` state was retained until the normal
60-second Core health observation completed.

Final transport state:

```text
lifecycle:            ShadowActive
transition reason:    healthy
MQTT status:          102
WebSocket status:     102
WebSocket Active:     true
```

## 6. Active Pre-Restart Baseline

A new reusable private read-only projection was added:

```text
private/navimow-capture/
  native-mqtt-core-resume-ordering-active-baseline-readonly.php
```

It records:

- kernel epoch and reconciliation markers;
- lifecycle state, transition and Core classification;
- connection and ingress counters;
- last connection trigger and timestamps;
- Receiver counters;
- Core status and credential-presence Booleans;
- topology and configuration hashes;
- token-validity threshold.

It never returns credential values.

Two executions separated by more than one lifecycle period were identical and
both passed:

```text
lifecycle:                     ShadowActive / healthy
last connection trigger:       initial
Core:                          102 / 102
WebSocket Active:              true
ownership validation:          valid / ready
token validity >= 900 seconds: true
configuration hashes:          stable
cleanup:                       armed
```

`lastKernelCoreClassification=none` is expected before the restart. That field
is populated by the corrected post-ready Core-resume reconciliation.

## 7. Ingress Classification

No new natural mower message arrived during this bounded activation window.

```text
Receiver call delta:       0
Receiver forwarded delta:  0
rejected delta:            0
classification:            transport-ready/data-pending
```

Healthy Core transport without a fresh mower message is allowed by step 166.
No traffic was manufactured and no mower command was sent.

## 8. REST Authority and Compatibility

The final active-state compatibility projection passed.

| Contract | Result |
|---|---|
| module | `main@71a90f69`, clean and valid |
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

### AD-NAV-588: Require a reconciled current epoch before activation

**Decision:** Activate only after the installed Account diagnostics match the
current kernel epoch and show completed reconciliation.

**Reason:** This prevents the activation path from being confused with the
post-restart ordering path under test.

### AD-NAV-589: Preserve the 60-second health observation

**Decision:** Accept `Connecting` with healthy Core status as an intermediate
state and wait for the normal bounded health transition.

**Reason:** Bypassing the lifecycle timer or issuing an explicit Connect would
invalidate the connection-attempt and trigger contract.

### AD-NAV-590: Freeze a hash-backed active baseline

**Decision:** Capture two equal active baselines before requesting restart
authorization.

**Reason:** The restart result must be compared against exact pre-restart
counters, triggers, topology and Core configuration without exposing secrets.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature enable | 1 |
| Account activation `ApplyChanges()` | 1 |
| automatic delayed connection attempts | 1 |
| explicit MQTT Connect | 0 |
| fallback disable | 0 |
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
module commit:          71a90f69
MQTT feature:           enabled
lifecycle:              ShadowActive / healthy
Core transport:         102 / 102
public state authority: REST
cleanup:                armed and mandatory
```

Authorization and MQTT credentials are currently stored in the owned Core
instances under the exact acceptance from step 170.

## 12. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-ordering-correction-temporary-activation/
    gate-e-evidence-closure.json
```

No credential, topic, endpoint, payload, Device ID, ObjectID or garden detail
appears in this public report.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| Gate D renewed persistence acceptance | PASS |
| Gate E temporary activation | PASS |
| Gate E healthy Core | PASS |
| Gate E natural ingress | DATA PENDING |
| Gate F corrected active restart | CLOSED |
| Gate G mandatory cleanup | ARMED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 14. Recommended Next Step

Gate F requires separate explicit authorization:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur korrigierten Core-Resume-Prüfung ist freigegeben.
```

The user performs the external service restart. No restart is initiated from
Symcon PHP. After pass, failure or ambiguity, mandatory cleanup executes.
