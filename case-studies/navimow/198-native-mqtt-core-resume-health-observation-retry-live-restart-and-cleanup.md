# 198 Native MQTT Core Resume Health Observation Retry Live Restart and Cleanup

**Case study:** Navimow native IP-Symcon module
**Status:** Retry Gate F passed at the `+90 s` boundary; mandatory cleanup
passed immediately and delayed
**Date:** 2026-07-29
**Scope:** Execute one external restart and mandatory cleanup from step 191

## 1. Purpose

Step 197 activated the receive-only native transport and froze stable active
baselines with a 3100-second restart-arm horizon.

This step:

1. rechecked the live restart threshold;
2. observed exactly one externally initiated Symcon restart;
3. captured the first reachable pre-deadline projection;
4. proved healthy Core adoption at the absolute `+90 s` observation;
5. verified zero Account reconnects;
6. executed mandatory normal Account cleanup;
7. repeated cleanup and compatibility checks after 153 seconds.

## 2. Authorization and Operation

The user explicitly authorized:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart für den Core-Resume-Health-Observation-Retry ist freigegeben.
```

Immediately before the external restart:

```text
token horizon:        2662 seconds
required horizon:     1800 seconds
state:                ShadowActive / healthy
Core status:          102 / 102
connection failures:  0
```

The user performed the external restart and reported:

```text
Symcon neu gestartet
```

No restart was invoked through Symcon PHP and no retry occurred.

## 3. Startup Axis

The new kernel epoch was greater than the frozen old epoch.

```text
kernel start -> Account observation: 81 seconds
kernel start -> first MCP projection: 154 seconds
five-minute patience window:          satisfied
```

MCP unavailability was not treated as failure and caused no fallback mutation.

## 4. Bounded Core Observation

The Account observed the retained native Core at four absolute offsets:

| Ordinal | Offset | MQTT | WebSocket | Canonical health |
|---:|---:|---:|---:|---|
| 1 | `+15 s` | 200 | 200 | pending |
| 2 | `+30 s` | 200 | 200 | pending |
| 3 | `+60 s` | 200 | 200 | pending |
| 4 | `+90 s` | 102 | 102 | healthy |

The deadline was exactly:

```text
kernelStartObservedAt + 90 seconds
```

The first reachable projection occurred after `+60 s` but before `+90 s`.
It preserved all three pending observations while the current Core had already
become `102/102`.

The final `+90 s` lifecycle transition produced:

```text
classification:          healthy
state:                   ShadowActive
reason:                  core-resumed
Core-resume observations: +1
```

## 5. No Account Reconnect

Pre- and post-restart Account counters remained:

```text
connection attempts:   14
connection successes:   6
connection failures:    0
last trigger:            initial
```

Therefore:

```text
Account reconnect delta: 0
automatic recovery:      not entered
```

Three natural receive-only messages were forwarded around the restart window.
No traffic was manufactured.

## 6. Diagnostic Schema Finding

The live custom projection initially requested per-entry fields named
`classification` and `failedPredicates`. Those fields returned empty.

Static source inspection proved that the public diagnostic contract exposes:

```text
healthy: boolean
```

for each bounded observation. `lastKernelCoreClassification` and
`lastKernelCoreFailedPredicates` are top-level lifecycle fields.

Classification:

```text
module serializer defect:  no
custom evidence probe bug: yes
behavioral evidence:       retained through statuses, offsets and top-level result
```

The canonical entry health values are false, false, false and true.

## 7. Deadline Adequacy

The first healthy observation occurred exactly at `+90 s`.

According to the frozen decision table:

```text
technical live test: PASS
current deadline:     valid at boundary
broader pilot:        increase deadline before activation
```

The result proves that the multi-observation implementation fixes the prior
single-shot failure. It also shows that 90 seconds has no observed scheduling
reserve on this installation.

## 8. Mandatory Cleanup

Immediately after the decisive result:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0
emergency Core cleanup:    0
```

Immediate and 153-second delayed checks both proved:

```text
MQTT feature:               disabled
lifecycle:                  Disabled
next attempt:               0
reconnect attempt:          0
WebSocket:                  inactive
Authorization headers:     empty
MQTT username and password: empty
connection counters:        stable
```

## 9. Compatibility Closure

| Contract | Result |
|---|---|
| installed module | clean and valid `main@45c7bd50` |
| retained topology | unchanged |
| canonical subscriptions | 4/4 QoS 0 |
| variables | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Account authentication | connected |
| reauthentication required | false |
| REST state authority | retained |

The mower-variable logging and accumulated history remain intact.

## 10. Architecture Decisions

### AD-NAV-702: Accept the boundary result as technical pass

**Decision:** Pass Gate F because the exact frozen `+90 s` contract completed
with healthy Core adoption and zero reconnects.

**Reason:** Every required state, counter and topology invariant passed.

### AD-NAV-703: Increase deadline before broader pilot

**Decision:** Do not broaden the active MQTT pilot with the current
90-second deadline.

**Reason:** Health appeared at the final observation with no measured reserve.

### AD-NAV-704: Correct the evidence probe, not the module serializer

**Decision:** Treat `healthy` as the canonical per-entry field.

**Reason:** Static source and tests agree; the custom live probe requested
non-contract names.

### AD-NAV-705: Complete cleanup before analysis

**Decision:** Disable and verify the transport before documenting broader
deadline implications.

**Reason:** Credential-free closure is mandatory after every live outcome.

## 11. Side-Effect Accounting

| Operation | Count |
|---|---:|
| external Symcon restarts | 1 |
| restart retries | 0 |
| Account reconnects after restart | 0 |
| automatic recovery connections | 0 |
| cleanup disable operations | 1 |
| cleanup `ApplyChanges()` | 1 |
| explicit MQTT Connect/Disconnect | 0/0 |
| natural forwarded messages | 3 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

## 12. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-health-observation-retry-live-restart/
    gate-f-g-evidence-closure.json
```

The public report contains no credential, token value, absolute expiry
timestamp, topic, endpoint, payload, device identity, ObjectID, hostname, IP
address or garden detail.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| retry Gate F external restart | PASS |
| bounded Core observation | PASS AT `+90 s` |
| Account reconnect delta | 0 |
| retry Gate G mandatory cleanup | PASS |
| delayed cleanup | PASS |
| wider active MQTT pilot | BLOCKED PENDING DEADLINE REVIEW |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 14. Recommended Next Step

Create:

```text
199-native-mqtt-core-resume-health-observation-deadline-and-diagnostics-review.md
```

That step should:

- choose a more conservative deadline before broader active operation;
- update the observation offsets and bounded-entry limit consistently;
- correct reusable private probes to read per-entry `healthy`;
- add offline regressions for the chosen deadline;
- keep MQTT disabled until a separately published correction is installed.
