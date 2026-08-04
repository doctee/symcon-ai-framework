# 172 Native MQTT Core Resume Ordering Correction Live Restart and Cleanup

**Case study:** Navimow native IP-Symcon module
**Status:** Gate F failed deterministically; Gate G cleanup passed and restored
the disabled credential-free state
**Date:** 2026-07-29
**Scope:** Execute one corrected active Core-resume restart and mandatory
cleanup from step 166

## 1. Purpose

Step 171 established a stable active pre-restart baseline for the published
MQTT Core-resume ordering correction.

This step:

1. reconfirmed the active baseline immediately before restart;
2. observed exactly one external Symcon service restart;
3. captured the first reconciled projection for the new kernel epoch;
4. stopped immediately when the exact Core-resume contract failed;
5. performed mandatory normal cleanup;
6. verified disabled compatibility and archive continuity.

## 2. Authorization

The user explicitly authorized:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur korrigierten Core-Resume-Prüfung ist freigegeben.
```

The user then performed the external restart and confirmed:

```text
Symcon neu gestartet
```

No restart was initiated from Symcon PHP.

The authorization did not permit:

- a second restart;
- an explicit MQTT Connect;
- a retry experiment;
- MQTT publish;
- mower commands;
- Core instance mutation.

Mandatory cleanup was already included in the Gate-E/G contract.

## 3. Immediate Pre-Restart Baseline

The final baseline immediately before restart passed:

```text
lifecycle:                     ShadowActive / healthy
MQTT and WebSocket:            102 / 102
WebSocket Active:              true
last connection trigger:       initial
connection attempts:           stable
connection successes:          stable
connection failures:           zero
Core-resume observations:      zero
token validity >= 900 seconds: true
topology/configuration hashes: frozen
```

Natural receive-only ingress had advanced before restart, confirming that the
active transport was carrying mower data without requiring a command.

## 4. Expected Corrected Result

Step 166 required the first reconciled projection to prove:

```text
new kernel epoch
  -> post-ready observation
  -> 15-second reconciliation delay
  -> healthy Core classification
  -> ShadowActive / core-resumed
```

Required deltas:

```text
Core-resume observations: +1
connection attempts:       0
connection successes:      0
connection failures:       0
connection trigger:        unchanged
Core configuration hashes: unchanged
```

## 5. First Reconciled Projection

The first bounded post-restart projection found a new kernel epoch and no MCP
transport or PHP execution error.

Observed:

```text
kernel epoch changed:              true
diagnostic epoch matches:          true
kernel observation recorded:       true
kernel reconciliation recorded:    true
observation-to-reconciliation gap: 0 seconds
lifecycle state:                   ConfigurationError
last transition reason:            healthy
Core classification:               none
classification timestamp:          absent
Core-resume observation delta:      0
connection-attempt delta:           0
connection-success delta:           0
connection-failure delta:           0
last connection trigger:            unchanged / initial
MQTT and WebSocket status:          102 / 102
WebSocket Active:                   true
topology/configuration hashes:      unchanged
```

The Core itself resumed healthy and the persisted configurations were
unchanged. The Account did not execute the expected delayed healthy
classification and adoption.

## 6. Failure Decision

Gate F failed for three independent reasons:

1. `ConfigurationError` replaced `ShadowActive`;
2. `core-resumed` and classification `healthy` were absent;
3. reconciliation was marked in the same second as observation instead of
   after the required 15-second delay.

The unchanged connection counters prove that no duplicate Account connection
attempt occurred. This is a startup lifecycle-ordering failure, not a broker
reconnect failure.

The sanitized error projection additionally reported:

```text
latest error:              credential-cleanup-skipped
error occurred after boot: true
```

No second poll sequence, restart, Connect or retry experiment was attempted
after the stop condition.

## 7. Mandatory Cleanup

The normal Account-owned cleanup executed exactly:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0
```

Immediate result:

```text
MQTT feature:                disabled
lifecycle:                   Disabled
next attempt:                none
WebSocket Active:            false
Authorization headers:       empty
MQTT username and password:  empty
non-secret Client ID:        retained
Receiver selection:          retained
```

No emergency Core cleanup was required.

## 8. Time-Separated Cleanup Verification

After more than one lifecycle period, the inactive projection passed:

- exact Receiver/MQTT/WebSocket module types;
- exact retained parent chain;
- symmetric Account/Receiver pairing;
- four canonical QoS-0 subscriptions;
- no wildcard or duplicate;
- stable topology and subscription hashes;
- unchanged connection attempts and Core-resume observations;
- inactive WebSocket and empty credential fields.

The complete compatibility projection also passed:

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
| REST state authority | retained |

The user's mower-variable logging remains intact.

## 9. Architecture Decisions

### AD-NAV-591: Fail on the first reconciled mismatch

**Decision:** End Gate F immediately when the first new-epoch projection lacks
the exact classification, delay and counter contract.

**Reason:** Later health observations could overwrite transition diagnostics
and cannot repair an already invalid startup sequence.

### AD-NAV-592: Distinguish healthy Core from successful Account adoption

**Decision:** Record Core `102/102` and stable hashes separately from the
failed Account lifecycle result.

**Reason:** A healthy native Core does not prove that post-ready ownership
classification and adoption executed.

### AD-NAV-593: Prefer normal cleanup after lifecycle failure

**Decision:** Disable through one Account property mutation and one
`ApplyChanges()` before considering direct Core cleanup.

**Reason:** The Account still retained valid ownership and completed its
normal credential removal successfully.

### AD-NAV-594: Reopen only offline failure analysis

**Decision:** Permit no further active test until the same-second
observation/reconciliation and cleanup error path are explained offline.

**Reason:** Repeating the restart without a new discriminating correction
would reproduce risk rather than add evidence.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| externally performed service restarts | 1 |
| Account connection attempts after restart | 0 |
| explicit MQTT Connect | 0 |
| restart retries | 0 |
| cleanup disable mutations | 1 |
| cleanup `ApplyChanges()` | 1 |
| explicit Disconnect | 0 |
| emergency Core mutations | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| created, deleted or reparented objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 11. Current Safe State

```text
installed commit:           71a90f69
module repository:          clean and valid
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
live MQTT session:          absent
REST state authority:       retained
```

## 12. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-ordering-correction-live-restart-and-cleanup/
    gate-f-g-evidence-closure.json
```

The evidence contains no credential, private topic, endpoint, payload, Device
ID, ObjectID, hostname, private IP address or garden detail.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| Gate E active baseline | PASS |
| Gate F corrected active restart | FAIL |
| Gate F Core-native resume | HEALTHY |
| Gate F Account Core-resume adoption | FAIL |
| Gate G mandatory cleanup | PASS |
| disabled compatibility | PASS |
| further active testing | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 14. Recommended Next Step

Proceed offline with:

```text
173-native-mqtt-core-resume-post-ready-barrier-failure-analysis.md
```

The analysis must explain:

- why observation and reconciliation were marked in the same second;
- why `ConfigurationError` retained transition reason `healthy`;
- why no Core classification was recorded;
- which startup path appended `credential-cleanup-skipped`;
- how to add a fixture-backed regression before another publication or live
  authorization.
