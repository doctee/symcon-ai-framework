# 138 Native MQTT Sibling Cross-Probe V2 Live Test and Restoration

**Case study:** Navimow native IP-Symcon module
**Status:** V2 one-shot completed; neither child received; runtime and main
fully restored
**Date:** 2026-07-28
**Scope:** Execute Gate C once, classify both child paths, clean all temporary
runtime state and delete the experiment branch

## 1. Purpose

Step 137 installed the temporary V2 branch and staged exactly one inactive
known-good sibling probe beside the productive Receiver.

This step:

1. revalidates the corrected private V2 source;
2. executes exactly one supervised receive-only connection attempt;
3. observes both compatible children for the fixed interval;
4. performs no publish, retry or mower command;
5. verifies automatic runtime cleanup separately;
6. returns Module Control to verified `main`;
7. verifies productive compatibility twice;
8. deletes the temporary local and remote branches.

## 2. Authorization

The user explicitly authorized:

```text
Ein einmaliger MQTT-Sibling-Cross-Probe-V2-Live-Test mit automatischem
Cleanup und Rückkehr zu main ist freigegeben.
```

The user separately confirmed:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

This permitted one broker connection attempt. It did not permit a retry, MQTT
publication or mower command.

After evidence closure, the user clarified that the mower mowed only briefly
and then returned to the station. The physical supervision statement remains
valid, but this later observation withdraws the stronger assumption of
continuous mowing throughout the complete MQTT window.

## 3. Frozen V2

Immediately before execution:

```text
live-one-shot-v2.php SHA-256:
7c2d01c1cee8d5faf3bf33fd5956283308659c0a1193062b19220270b77ccc3e

Connect contract regression:
PASS
```

The harness accepted only the productive asynchronous result:

```text
MQTT connection attempt started.
```

## 4. Preconditions

All preconditions passed:

- productive Receiver and known-good probe shared the retained MQTT parent;
- Account authentication and token were usable;
- no reauthentication was required;
- MQTT shadow was disabled;
- WebSocket was inactive;
- headers and MQTT credentials were empty;
- stable Client ID was present;
- exactly four QoS-0 subscriptions without wildcard were configured;
- both child baselines were zero.

## 5. One-Shot Execution

The corrected V2 entered its observation loop.

Calls:

```text
Connect:      1
Disconnect:   1
probe Arm:    1
probe Close:  1
probe Delete: 1
retries:      0
```

No MQTT publish or mower command occurred.

Core progression:

```text
first sample at 337 ms:
  MQTT status:      104
  WebSocket status: 104

from 2352 ms through final sample:
  MQTT status:      102
  WebSocket status: 102
  WebSocket Active: true
```

Both Core transports reached and retained their healthy status throughout the
remaining observation window.

## 6. Child Observation

The harness sampled both children 82 times through:

```text
last sample: 163154 ms
```

Every sample reported:

```text
productive Receiver delta: 0
known-good probe delta:     0
probe accepted messages:    0
classification:             neither-received
```

This is a valid sampled delivery result. Unlike step 133, the observation loop
was entered and completed.

The result proves zero child ingress during the sampled retained transport
window. Because the mower returned to the station during that window, it is not
independent proof that continuous active-mowing traffic was available for all
163 seconds.

## 7. MCP Output Truncation

The harness returned a per-sample observation array. Although the harness
completed with `pass: true`, the 32 KiB MCP output bound truncated the tail of
the JSON result:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         true
harness pass:      true
```

The truncation affected the serialized tail, not local harness execution. The
visible result included all observation samples and the successful normal
Disconnect and disable results.

Architecture decision:

- perform no retry;
- close cleanup evidence through a separate bounded read-only process;
- harden any future harness to return a compact aggregate instead of every
  sample.

## 8. Independent Runtime Cleanup

A new PHP process verified:

| Invariant | Result |
|---|---|
| temporary probe count | 0 |
| productive Receiver retained | PASS |
| MQTT shadow disabled | PASS |
| WebSocket inactive | PASS |
| authorization headers empty | PASS |
| MQTT username/password empty | PASS |
| stable Client ID retained | PASS |
| emergency cleanup used | no |

The harness itself completed before its hard deadline and reported all required
cleanup actions successful.

## 9. Main Restoration

Exactly one supported Module Control operation restored:

```text
branch: main
commit: 046529c5
clean:  true
valid:  true
```

No `MC_UpdateModule()` or `MC_ReloadModule()` was used.

The complete post-return projection ran twice. Both runs were equal to each
other and to the pre-update baseline:

- productive topology unchanged;
- all 14 variable identities and metadata unchanged;
- all 5 Archive Control logging contracts unchanged;
- logged history queryable;
- command evidence unchanged;
- authentication retained;
- MQTT disabled and credential-empty;
- temporary wrappers absent;
- productive wrappers present;
- no probe instance.

The user's mower-variable logging remains intact.

## 10. Git Cleanup

After runtime and Module Control restoration:

- remote experiment branch deleted;
- remote deletion verified after prune;
- local experiment branch deleted;
- publication clone returned to clean `main`;
- local `main` and `origin/main` verified equal.

Final state:

```text
local main:
046529c518feefb15a51bd2f1c404401b3a7f474

origin/main:
046529c518feefb15a51bd2f1c404401b3a7f474
```

## 11. Technical Interpretation

The experiment distinguishes the child implementation from the retained parent
path:

| Productive Receiver | Known-good probe | Result |
|---:|---:|---|
| `0` | `0` | `neither-received` |

The known-good probe previously received native MQTT traffic when attached to
the disposable step-94 transport. On the retained productive MQTT parent,
neither it nor the productive Receiver received data while both Core instances
reported healthy status.

Therefore:

- a defect only in the productive Receiver is rejected as the root cause;
- compatible-child selection alone is not the gap;
- the unresolved difference lies before child distribution;
- likely investigation areas are retained MQTT subscription application,
  broker/session behavior and retained Core transport configuration;
- healthy Core status does not prove subscribed application-message ingress.

This does not yet identify one exact root cause.

The later physical-context correction reduces the strength of traffic-volume
assumptions but does not alter the measured child counters or cleanup result.

## 12. Evidence

Private machine-readable evidence:

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe-v2/
    live-one-shot-result.json
    main-restoration.json
    gate-c-evidence-closure.json
```

No credential, token, private endpoint, topic, payload, Client ID, Device ID,
ObjectID or garden detail appears in this public report.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| one-shot safety | PASS |
| observation interval | PASS |
| Core connection health | PASS |
| productive Receiver ingress | NOT OBSERVED |
| known-good sibling ingress | NOT OBSERVED |
| runtime cleanup | PASS |
| return to main | PASS |
| productive compatibility | PASS |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

## 14. Architecture Decisions

### AD-NAV-488: Reject a Receiver-only root cause

**Decision:** Stop treating the productive Receiver implementation as the sole
remaining explanation.

**Reason:** A known-good compatible child also received nothing from the same
retained parent.

### AD-NAV-489: Separate Core health from message ingress

**Decision:** Status `102` proves healthy Core instance state, not successful
application-topic delivery.

**Reason:** Both clients stayed healthy while both child ingress counters
remained zero.

### AD-NAV-490: Preserve REST authority

**Decision:** Keep MQTT disabled by default and retain REST as the only public
state authority.

**Reason:** No retained native MQTT application-message ingress has been
demonstrated.

### AD-NAV-491: Bound future live output

**Decision:** Future live harnesses shall emit compact aggregates and selected
milestones instead of full per-poll arrays.

**Reason:** Local execution was successful, but oversized result serialization
made the MCP evidence channel incomplete.

## 15. Recommended Next Step

Create step 139:

```text
native-mqtt-retained-core-subscription-gap-analysis.md
```

It should remain offline and read-only initially:

1. compare the retained Core MQTT/WebSocket configuration contract with the
   successful disposable step-94 transport;
2. distinguish configured subscriptions from confirmed broker subscription;
3. inspect whether native Core diagnostics can expose SUBACK, subscription or
   receive counters without private payloads;
4. identify the smallest next discriminating probe;
5. replace per-sample live output with a bounded aggregate before any new live
   authorization is considered.
