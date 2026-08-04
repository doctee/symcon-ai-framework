# 153 Native MQTT Passive Pilot Restart Observation

**Case study:** Navimow native IP-Symcon module
**Status:** Restart gate failed safely; native Core resumed persisted active
transport without Account reconstruction, pilot disabled and cleaned
**Date:** 2026-07-28
**Scope:** Execute one supervised service restart during the receive-only MQTT
pilot and apply the authorized Disable fallback

## 1. Purpose

Step 152 activated the receive-only pilot and proved healthy ingress plus REST
reconciliation.

This step tests whether a real IP-Symcon service restart causes:

1. transient credential cleanup;
2. no inline network work;
3. one Account-owned delayed reconstruction;
4. retained topology and REST operation;
5. unchanged variables and Archive Control contracts.

## 2. Authorization

The user explicitly authorized:

```text
Ein beaufsichtigter Symcon-Restart während des receive-only MQTT-Piloten ist freigegeben.
```

The authorization included Disable and credential cleanup on any stop
condition.

It did not authorize:

- a second restart;
- an explicit MQTT Connect;
- MQTT publish;
- a mower command;
- token manipulation;
- Core instance replacement.

## 3. Restart Execution Boundary

The configured SAEF deployment channel intentionally exposes only
package-bound activation restarts. It does not provide a free service-restart
operation.

Restarting the Windows service from its own Symcon PHP process would bypass the
external restart coordinator and provide ambiguous completion evidence.

The user therefore restarted the IP-Symcon service exactly once on the
Windows host and reported completion. No mower or MQTT action was requested.

## 4. Pre-Restart Baseline

Immediately before restart:

```text
lifecycle:            ShadowActive / healthy
MQTT status:          102
WebSocket status:     102
connection attempts:  8
successes:            1
failures:             0
reconnect attempts:   0
received/accepted:    212 / 212
Receiver forwarded:  212 / 212
```

The kernel start timestamp was captured privately for exact comparison.

## 5. Post-Restart Finding

The first available post-restart projection proved:

```text
kernel start changed:        true
kernel runlevel:             ready
topology retained:           true
lifecycle:                   ShadowActive / healthy
MQTT status:                 102
WebSocket status:            102
WebSocket active:            true
credentials present:         true
connection attempts:         8
connection successes:        1
connection failures:         0
reconnect attempts:          0
received/accepted:           287 / 287
Receiver forwarded:          287 / 287
```

The last recorded Account connection attempt occurred before the new kernel
start. Its counter did not increase.

## 6. Classification

The native WebSocket and MQTT Core instances resumed operation from their
persisted active configuration. They continued delivering data, but the
Account recovery lifecycle did not perform the required cleanup and delayed
reconstruction.

Observed:

```text
Core-native reconnect:             yes
Account delayed reconstruction:    no
new Account connection attempt:    no
credential cleanup before resume:  not observed
duplicate topology:                no
data ingress continuity:           yes
REST continuity:                   yes
```

This disproves the assumption that the Account `ApplyChanges()` recovery path
necessarily runs during an ordinary service restart.

### Gate result

```text
Gate E restart recovery: FAIL
transport continuity:    PASS
safety contract:         FAIL
```

Transport continuity alone cannot satisfy the declared credential and
ownership lifecycle.

## 7. Disable Fallback

The finding matched a predefined stop condition. The authorized fallback ran
immediately:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Connect:          0
```

Verified cleanup:

```text
feature:                    disabled
lifecycle:                  Disabled
MQTT status:                104
WebSocket status:           104
WebSocket active:           false
Authorization headers:      empty
MQTT username and password: empty
```

Cleanup passed completely.

## 8. Final Compatibility

The final full read-only projection passed:

| Contract | Result |
|---|---|
| module | `main` / `7c1747cc` / clean / valid |
| productive instances | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| subscriptions | four canonical QoS-0 entries |
| MQTT credentials | empty |

The user's logging configuration remains intact.

## 9. Root-Cause Boundary

The evidence supports:

- Core transport configuration, including active state and credentials, is
  persisted across service restart;
- native Core instances can reconnect independently;
- the Account lifecycle timer and diagnostics can resume without a new
  Account-owned Connect;
- `ApplyChanges()` alone is not a sufficient service-start recovery hook.

The evidence does not yet select a fix.

Candidate directions requiring analysis:

1. accept Core-native restart persistence and revise the credential lifecycle;
2. register and handle a supported kernel-start message, then rotate the
   transport after startup;
3. require controlled disable before planned restart;
4. determine whether a brief Core-native reconnect before a kernel-start
   handler can be prevented at all with retained native clients.

## 10. Architecture Decision

### AD-NAV-521: Fail the restart gate despite data continuity

**Decision:** Treat the restart as failed and disable MQTT.

**Reason:** The proven behavior differs from the declared Account-owned
cleanup and delayed reconstruction contract. Continuing would silently accept
persistent credentials and a second lifecycle owner.

### AD-NAV-522: Preserve the evidence

**Decision:** Do not reinterpret the native Core reconnect as the implemented
recovery path.

**Reason:** Connection counters and timestamps clearly distinguish the two
mechanisms.

## 11. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Symcon service restarts | 1 |
| fallback disable | 1 |
| explicit MQTT Connect | 0 |
| MQTT publish | 0 |
| mower commands | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

All MCP results had separate successful transport, null PHP execution error
and no truncation.

## 12. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-passive-pilot-restart-observation/
    pre-restart-baseline.json
    gate-e-evidence-closure.json
```

No credential, topic, endpoint, device identity, ObjectID, payload, location
or garden detail appears in this public report.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| Gate D activation | PASS |
| Gate E service restart occurred | PASS |
| Core transport continuity | PASS |
| Account-owned delayed reconstruction | FAIL |
| credential lifecycle | FAIL |
| Disable fallback | PASS |
| final cleanup | PASS |
| MQTT pilot active | NO |
| natural token observation | BLOCKED |
| degraded-connectivity observation | BLOCKED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 14. Recommended Next Step

Create an analysis-only redesign step:

```text
154-native-mqtt-service-restart-recovery-redesign.md
```

It should:

1. confirm supported IP-Symcon kernel-start lifecycle hooks;
2. analyze native Core auto-reconnect ordering;
3. decide whether persisted active credentials are acceptable;
4. compare Core-owned continuity with Account-owned forced rotation;
5. define planned- and unplanned-restart behavior separately;
6. keep MQTT disabled until a new offline implementation and live gate pass.

No implementation, publication or reactivation is authorized by this report.
