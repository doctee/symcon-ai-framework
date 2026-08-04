# Native MQTT Core-Resume Deadline Hardening Live Restart and Cleanup

**Case study:** Navimow native IP-Symcon module
**Status:** Gate H passed at `+90 s`; mandatory immediate and delayed cleanup
passed
**Date:** 2026-07-29
**Scope:** Execute one external restart, bounded Core observation and mandatory
cleanup from step 201

## 1. Purpose

Step 209 activated the receive-only transport once and froze two stable active
baselines plus a valid restart arm.

This step:

1. rechecked the live restart threshold;
2. observed exactly one externally initiated Symcon restart;
3. captured the new kernel and persisted Core-health timeline;
4. proved healthy retained-Core adoption at the absolute `+90 s` point;
5. proved zero Account reconnects;
6. performed mandatory normal Account cleanup;
7. repeated full cleanup verification after 208 seconds.

## 2. Authorization and Restart

The user explicitly authorized:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart für den 180-Sekunden-Core-Resume-Test ist freigegeben.
```

Immediately before the restart:

```text
token horizon:        2329 seconds
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
kernel start -> Account observation: 75 seconds
Account observation -> deadline:     180 seconds
```

The first reachable MCP projection arrived after the Account had already
persisted the `+15` and `+30` observations. Console timing was not treated as
a lifecycle failure.

## 4. Core-Health Timeline

The complete decisive timeline was:

| Ordinal | Absolute offset | MQTT | WebSocket | Credentials | Healthy |
|---:|---:|---:|---:|---|---|
| 1 | `+15 s` | 200 | 200 | present | no |
| 2 | `+30 s` | 200 | 200 | present | no |
| 3 | `+60 s` | 200 | 200 | present | no |
| 4 | `+90 s` | 102 | 102 | present | yes |

At `+60 s`, the persisted point was correctly still unhealthy even though a
slightly later live read already saw the Core at `102/102`. The scheduled
`+90 s` point then adopted it immediately:

```text
classification:          healthy
failed predicates:       none
state:                   ShadowActive
reason:                  core-resumed
Core-resume observations: +1
```

The `+120` and `+180` points were not executed because the first healthy point
is decisive and the active observation must stop immediately.

## 5. No Account Reconnect

Pre- and post-restart Account counters remained:

```text
connection attempts:   15
connection successes:   7
connection failures:    0
last trigger:            initial
```

Therefore:

```text
Account reconnect delta: 0
automatic recovery:      not entered
```

No receive-only message arrived after the restart before cleanup. No traffic
was manufactured.

## 6. Deadline Decision

The first healthy point at `+90 s` is a **private-pilot pass** under the frozen
decision table.

The new `+120/+180 s` hardening was not consumed by this run, but it remained
correctly scheduled until the earlier healthy adoption canceled further
observation. This proves the extended deadline does not delay adoption at an
earlier healthy point.

## 7. Cleanup Harness Finding

The first historical cleanup probe stopped fail-closed before mutation because
it incorrectly required the Core to be already inactive and credential-free
while the Account feature was still enabled.

```text
property mutation: 0
ApplyChanges:      0
```

The normal cleanup contract was then executed with the correct preconditions:
feature enabled, retained exact pairing and ownership `ready`. The corrected
private probe is:

```text
private/navimow-capture/
  native-mqtt-deadline-hardening-normal-cleanup.php
```

No emergency Core mutation was required.

## 8. Mandatory Cleanup

Immediately after the decisive result:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0
emergency Core cleanup:    0
```

Immediate and 208-second delayed full projections both proved:

```text
MQTT feature:               disabled
lifecycle:                  Disabled
next attempt:               0
reconnect attempt:          0
Core observation count:     0
Core observation deadline:  0
WebSocket:                  inactive
Authorization headers:     empty
MQTT username and password: empty
connection counters:        stable at 15 / 7 / 0
```

Both projections additionally proved clean and valid `main@8fdab84b`, exact
retained topology and subscriptions, 14 unchanged variable contracts, five
unchanged archive logging contracts, queryable history, stable command
evidence and continued REST authority.

## 9. Architecture Decisions

### AD-NAV-743: Preserve absolute observations despite delayed console access

**Decision:** Use the persisted Account timeline as the authoritative Axis-B
evidence.

**Reason:** MCP reachability does not define or reset the Core-health
deadline.

### AD-NAV-744: Accept healthy adoption at the scheduled `+90 s` point

**Decision:** Classify Gate H as a private-pilot pass.

**Reason:** The Core became healthy after the persisted `+60 s` point and was
adopted at the next exact scheduled point without an Account reconnect.

### AD-NAV-745: Stop after the first healthy point

**Decision:** Do not wait for synthetic `+120/+180 s` observations.

**Reason:** The state machine correctly cancels later checks after decisive
healthy adoption.

### AD-NAV-746: Correct the cleanup harness precondition

**Decision:** Require active Account ownership before normal cleanup, not an
already cleaned Core.

**Reason:** The old condition made the mandatory normal path unreachable but
failed safely without mutation.

### AD-NAV-747: Verify beyond the complete new horizon

**Decision:** Repeat full cleanup checks after 208 seconds.

**Reason:** This exceeds the 180-second deadline and more than one normal
lifecycle interval.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| external Symcon restarts | 1 |
| restart retries | 0 |
| Account reconnects after restart | 0 |
| automatic recovery connections | 0 |
| cleanup disable operations | 1 |
| cleanup `ApplyChanges()` | 1 |
| explicit MQTT Connect/Disconnect | 0/0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP response was evaluated separately for transport error, PHP execution
error and truncation.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-deadline-hardening-live-restart/
    gate-h-i-evidence-closure.json
```

The public report contains no credential, token value, absolute expiry
timestamp, topic, endpoint, payload, device identity, ObjectID, hostname, IP
address or garden detail.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate H external restart | PASS |
| first healthy observation | PASS AT `+90 s` |
| Account reconnect delta | 0 |
| Gate I mandatory cleanup | PASS |
| delayed cleanup | PASS |
| persistence acceptance | CONSUMED |
| active MQTT transport | DISABLED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

**The complete deadline-hardening live sequence is closed credential-free.**

## 13. Recommended Next Step

No further live mutation is required for this hardening increment. Preserve
the `+90 s` result as the current private-pilot regression expectation and
review the cleanup-harness correction before any future credential-bearing
test.
