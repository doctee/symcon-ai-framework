# 181 Native MQTT Transient Readiness Correction Live Restart and Cleanup

**Case study:** Navimow native IP-Symcon module
**Status:** Durable barrier passed, active Core-resume adoption failed on
post-ready Core health; mandatory cleanup passed
**Date:** 2026-07-29
**Scope:** Observe one externally performed active restart and execute
mandatory cleanup from step 175

## 1. Purpose

Step 180 established two equal active baselines for the published
transient-readiness correction.

This step:

1. observed exactly one external Symcon service restart;
2. captured the first reconciled projection for the new kernel epoch;
3. proved that the durable 15-second post-ready barrier executed;
4. stopped when the native Core was classified unhealthy;
5. performed mandatory normal cleanup;
6. verified credential-free compatibility over 73 seconds;
7. promoted the sanitized failure signature into a regression fixture.

## 2. Restart Boundary

The user externally performed the restart and reported:

```text
Symcon neu gestartet
```

No restart was initiated from Symcon PHP.

The operation remained bounded to:

- one external service restart;
- no explicit MQTT Connect;
- no restart retry;
- no MQTT publish;
- no mower command;
- mandatory cleanup after the first decisive result.

## 3. Frozen Pre-Restart Baseline

Step 180 established:

```text
installed module:            main@7d141f76
lifecycle:                   ShadowActive / healthy
MQTT and WebSocket:          102 / 102
WebSocket Active:            true
connection attempts:         12
connection successes:        4
connection failures:         0
Core-resume observations:    0
last connection trigger:     initial
received / accepted / reject: 309 / 309 / 0
token validity >= 900 s:     true
```

Topology and Core configuration hashes were stable across two active
projections 87 seconds apart.

## 4. Expected Result

Step 175 required:

```text
changed kernel epoch
  -> durable timerless pre-ready barrier
  -> post-ready observation
  -> 15-second reconciliation delay
  -> healthy Core classification
  -> ShadowActive / core-resumed
```

Required counter deltas:

```text
Core-resume observations: +1
connection attempts:       0
connection successes:      0
connection failures:       0
connection trigger:        unchanged
```

## 5. First Reconciled Projection

The first bounded projection found a new kernel epoch:

```text
kernel epoch changed:              true
kernel observation present:        true
kernel reconciliation present:     true
observation-to-reconciliation gap: 15 seconds
lifecycle state:                   ReconnectScheduled
last transition reason:            core-disconnected
Core classification:               unhealthy-with-credentials
classification timestamp:          reconciliation timestamp
Core-resume observation delta:      0
connection-attempt delta:           0
connection-success delta:           0
connection-failure delta:           0
last connection trigger:            unchanged / initial
MQTT and WebSocket status:          104 / 104
WebSocket Active:                   false
topology hash:                      unchanged
Core configuration hashes:         changed
```

The Account had already executed its existing bounded unhealthy-Core recovery
branch. Credential-presence fields were false in this first captured
projection.

## 6. Barrier Result

The correction from step 174 fixed the specific step-172 defect:

| Contract | Step 172 | Step 181 |
|---|---:|---:|
| observation-to-reconciliation delay | 0 s | 15 s |
| Core classification populated | no | yes |
| premature `ConfigurationError` | yes | no |
| post-ready reconciliation executed | no | yes |

Therefore:

```text
durable pre-ready barrier: PASS
post-ready delay:          PASS
```

The active adoption still failed because the native Core was not healthy at
the decisive classification:

```text
Core health:                  FAIL
Account Core-resume adoption: FAIL
Gate F:                       FAIL
```

## 7. Receive-Counter Timing Correction

Between the final active baseline and the decisive projection, receive-only
counters advanced:

```text
received delta: +2
accepted delta: +2
rejected delta: 0
```

The final active baseline was captured before the user performed the restart.
That baseline did not include `lastReceivedAt` or a source-message timestamp.
The delta therefore proves counter advancement only across the complete
baseline-to-projection interval. It does not prove whether the messages arrived
before the restart, during startup or after `IPS_KERNELSTARTED`.

No traffic was manufactured and no mower command was sent.

## 8. Recovery Counters

The settled disabled diagnostics recorded:

```text
connection attempts:       12
connection successes:      4
connection failures:       0
unexpected disconnects:    2
reconnect attempts:        1
reconnect exhausted:       0
Core-resume observations:  0
```

The connection attempt, success and failure counters did not change across
the restart. No Account reconnect occurred before the stop and cleanup.

One auxiliary read-only probe initially expected
`unexpectedDisconnects=1`. The observed value was `2`, so its internal guard
returned `counter-contract-failed`.

This was:

```text
MCP transport error:    none
PHP execution error:    none
truncation:             false
installation mutation: none
```

The observed counters were retained as evidence rather than forcing the
incorrect absolute expectation.

## 9. Mandatory Cleanup

Normal cleanup executed exactly:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0
```

Immediate and 73-second delayed verification proved:

```text
MQTT feature:                disabled
lifecycle:                   Disabled
next attempt:                0
WebSocket:                   inactive
Authorization headers:      empty
MQTT username and password: empty
```

No emergency Core mutation was required.

The final compatibility projection passed:

- clean and valid `main@7d141f76`;
- retained Account/Receiver/Core topology;
- 14/14 variable identities and metadata;
- 5/5 Archive Control logging contracts;
- queryable archive history;
- unchanged command evidence;
- connected OAuth state and usable token;
- REST state authority retained.

The user's mower-variable logging and history remain intact.

## 10. Fixture Closure

Added:

```text
fixtures/mqtt/core-resume-post-ready-unhealthy-live.json
```

The fixture contains:

- no payload;
- no device identity;
- no topic or endpoint;
- no credential value;
- only relative timing, state, classification and counter deltas.

The existing lifecycle harness already covers the
`unhealthy-with-credentials -> core-disconnected` recovery path. The new
fixture freezes the exact sanitized live signature for the next analysis.

Validation:

```text
MQTT fixture checks:       PASS
complete Navimow gate:     PASS
fixture SHA-256:
c4474c42d8280bdd62182eee781134a189785bdfaa8a21dd5f2845fe367ad022
```

## 11. Architecture Decisions

### AD-NAV-630: Evaluate the first reconciled projection

**Decision:** Stop Gate F as soon as the new epoch has a decisive
classification.

**Reason:** A later reconnect could obscure whether native Core adoption
succeeded without an Account connection attempt.

### AD-NAV-631: Separate barrier correctness from Core health

**Decision:** Record the exact 15-second reconciliation as a barrier pass and
the unhealthy classification as a separate active-adoption failure.

**Reason:** The correction fixed the demonstrated ordering defect even though
the complete restart contract still failed.

### AD-NAV-632: Treat the receive delta as interval evidence

**Decision:** Preserve the `+2` receive/accept delta while classifying its
timing relative to the restart as unresolved.

**Reason:** The final baseline omitted `lastReceivedAt`, and the interval began
before the user initiated the restart.

### AD-NAV-633: Prefer normal cleanup

**Decision:** Disable through one Account property mutation and one
`ApplyChanges()` before considering direct Core cleanup.

**Reason:** The Account retained ownership and restored the credential-free
state successfully.

### AD-NAV-634: Promote the live signature

**Decision:** Add a sanitized fixture even though the generic negative
lifecycle path already has executable coverage.

**Reason:** The combination of exact barrier delay, bounded counter advancement
with unresolved timing and later Core unhealthiness is new live evidence needed
for root-cause analysis.

## 12. Side-Effect Accounting

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
| messages counted after the final baseline | 2 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| created, deleted or reparented objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was evaluated separately for transport error, PHP execution
error and output truncation.

## 13. Current Safe State

```text
installed commit:           7d141f76
module repository:          clean and valid
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
live MQTT session:          absent
REST state authority:       retained
```

## 14. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-transient-readiness-correction-live-restart-and-cleanup/
    gate-f-g-evidence-closure.json
```

The evidence contains no credential, private topic, endpoint, payload, Device
ID, ObjectID, hostname, private IP address or garden detail.

## 15. Gate Decision

| Gate | Decision |
|---|---|
| Gate E active baseline | PASS |
| Gate F durable pre-ready barrier | PASS |
| Gate F post-ready delay | PASS |
| Gate F native Core health | FAIL |
| Gate F Account Core-resume adoption | FAIL |
| Gate F complete active restart | FAIL |
| Gate G mandatory cleanup | PASS |
| disabled compatibility | PASS |
| further active testing | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 16. Recommended Next Step

Proceed offline with:

```text
182-native-mqtt-post-ready-core-health-failure-analysis.md
```

That step should correlate:

- unresolved receive-counter timing;
- native Core status transitions;
- the 197-second kernel-start-to-observation interval;
- the exact 15-second classification window;
- the `unhealthy-with-credentials` recovery branch;
- persisted Core startup behavior and native reconnect timing.

No publication, MQTT activation or another restart is authorized by this
result.
