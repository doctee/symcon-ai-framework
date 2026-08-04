# 163 Native MQTT Kernel Start Reconciliation Core Resume Restart

**Case study:** Navimow native IP-Symcon module
**Status:** Gate G failed safely; active transport delivered data but Account
started a new connection attempt, mandatory cleanup passed
**Date:** 2026-07-28
**Scope:** Execute active Core-resume restart Gate G and mandatory cleanup
Gate H from step 156

## 1. Purpose

Step 162 established one healthy receive-only transport and an exact active
pre-restart baseline.

This step tests whether a real service restart produces the selected
observe-and-adopt behavior:

```text
Core resumes -> kernel hook -> core-resumed -> no Account Connect
```

Mandatory cleanup follows pass, failure or ambiguity.

## 2. Authorization

The user explicitly authorized:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur Core-Resume-Prüfung ist freigegeben.
```

The prior Gate-E acceptance covered temporary Core credential persistence.
Gate H cleanup was already included.

The authorization did not permit:

- a second restart;
- an explicit MQTT Connect;
- MQTT publish;
- a mower command;
- retry experiments;
- Core replacement or duplication.

## 3. Active Pre-Restart Baseline

Immediately before restart:

```text
lifecycle:                    ShadowActive / healthy
MQTT status:                  102
WebSocket status:             102
WebSocket Active:             true
connection attempts:          captured privately
connection successes:         captured privately
connection failures:          0
Core-resume observations:     captured privately
Receiver ingress:             captured privately
credential presence:          true
```

Credential values were not read or returned.

## 4. Restart Execution

The user restarted the IP-Symcon service exactly once on the Windows host.

No pre-restart cleanup occurred because Gate G intentionally models
unplanned-restart behavior. No restart was initiated from Symcon PHP.

## 5. Kernel Reconciliation

The first bounded post-restart projection proved:

```text
kernel start changed:             yes
kernel runlevel:                  ready
diagnostic kernel start matches:  yes
kernel-start observation:         present
kernel-start reconciliation:      present
observation-to-reconciliation:    15 seconds
MQTT/WebSocket status:            102/102
WebSocket Active:                 true
```

The kernel-start registration and delayed timer therefore worked.

The resulting lifecycle did not match the Gate-G contract:

```text
expected state:              ShadowActive
observed state:              Connecting
expected transition reason: core-resumed
observed transition reason: connection-attempt
```

## 6. Stop Condition

Compared with the immediate pre-restart baseline:

```text
connection-attempt delta:       +1
connection-success delta:       0
connection-failure delta:       0
Core-resume observation delta:  0
```

Gate G required:

```text
connection-attempt delta:       0
Core-resume observation delta: +1
```

The exact stop condition was therefore met:

```text
Account connection attempt increased and core-resumed was absent.
```

No retry, explicit Connect or second restart was performed.

## 7. Transport Continuity

Despite the lifecycle contract failure, natural receive-only ingress continued
across the restart:

```text
received delta:            +2
accepted delta:            +2
rejected delta:             0
Receiver forwarded delta:  +2
```

This proves transport and data continuity, but continuity alone does not prove
the selected Account/Core ownership transition.

REST remained the only authority for public mower variables.

## 8. Classification

```text
kernel hook and delay:       PASS
native transport continuity: PASS
Account core-resumed path:   FAIL
single-owner counter contract: FAIL
Gate G:                      FAIL
```

The evidence is consistent with a startup-order race in which the Account
reaches its connection path instead of adopting the already healthy Core.
This is an inference, not yet a proven root cause.

The evidence does not justify another live test. Static lifecycle and startup
ordering analysis is required first.

## 9. Mandatory Cleanup

The predefined stop condition triggered Gate H immediately.

Executed exactly:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
```

No direct emergency mutation of Core properties was required.

Verified:

```text
MQTT feature:               disabled
configuration status:       disabled
lifecycle:                  Disabled
next attempt:               none
MQTT status:                104
WebSocket status:           104
WebSocket Active:           false
Authorization headers:      empty
MQTT username and password: empty
```

Gate H passed completely.

## 10. Final Compatibility

The complete post-cleanup projection passed.

| Contract | Result |
|---|---|
| module | `main@aed0b434`, clean and valid |
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

The user's logging configuration remains intact.

## 11. Architecture Closure

### AD-NAV-555: Fail on Account connection-attempt delta

**Decision:** Transport continuity cannot override the explicit
`connectionAttempts +0` Gate-G contract.

**Reason:** A new Account attempt means the intended Core-resume adoption path
was not selected.

### AD-NAV-556: Preserve ingress as separate evidence

**Decision:** Record accepted post-restart ingress as transport continuity, not
as lifecycle acceptance.

**Reason:** Data delivery and lifecycle ownership are independent contracts.

### AD-NAV-557: Stop live testing after deterministic cleanup

**Decision:** Perform mandatory cleanup immediately and require static analysis
before any further active restart.

**Reason:** A second restart or Connect would exceed the authorized budget and
could conceal the ordering defect.

## 12. Side-Effect Accounting

| Operation | Count |
|---|---:|
| active Symcon service restarts | 1 |
| explicit MQTT Connect | 0 |
| second restart | 0 |
| cleanup feature disable | 1 |
| cleanup Account `ApplyChanges()` | 1 |
| direct emergency Core mutations | 0 |
| MQTT publish | 0 |
| mower commands | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was checked separately for transport error, PHP execution
error and truncation.

## 13. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-kernel-start-reconciliation-core-resume-restart/
    gate-g-h-evidence-closure.json
```

No private credential, topic, endpoint, payload, Device ID, ObjectID or garden
detail appears in this public report.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| Gate F temporary activation | PASS |
| Gate G service restart occurred | PASS |
| Gate G kernel reconciliation | PASS |
| Gate G transport continuity | PASS |
| Gate G Core-resume adoption | FAIL |
| Gate G counter contract | FAIL |
| Gate H mandatory cleanup | PASS |
| final credential-free state | PASS |
| MQTT active | NO |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 15. Recommended Next Step

Create an analysis-only step:

```text
164-native-mqtt-core-resume-ordering-failure-analysis.md
```

It should:

1. reconstruct the startup timeline from lifecycle code and timestamps;
2. determine which path incremented `connectionAttempts`;
3. analyze `ApplyChanges()`, kernel message and timer ordering;
4. inspect whether native Core status was non-healthy at reconciliation time;
5. design a deterministic precedence rule between startup scheduling and
   kernel reconciliation;
6. add offline regression coverage before another publication or live test.

No further MQTT activation or restart is authorized.
