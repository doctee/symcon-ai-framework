# 145 Native MQTT Subscription Schema Correction Live Test and Restoration

**Case study:** Navimow native IP-Symcon module
**Status:** Canonical schema rewrite and ingress to both children proven;
runtime, Module Control and Git fully restored
**Date:** 2026-07-28
**Scope:** Execute Gate D from step 141 exactly once

## 1. Purpose

Steps 139 and 140 identified and corrected the retained MQTT Client
subscription-field mismatch. Steps 142 through 144 published the correction,
published a temporary known-good sibling probe and staged it inactive.

This step:

1. executes one corrected receive-only V3 connection;
2. proves the active Core property uses exact native `QoS`;
3. compares productive Receiver and sibling ingress;
4. performs automatic runtime cleanup;
5. restores Symcon to corrected productive `main`;
6. proves variables, logging and authentication continuity;
7. deletes the temporary branch locally and remotely;
8. promotes a payload-free regression fixture.

## 2. Authorization and Physical Gate

The user explicitly authorized:

```text
Ein einmaliger korrigierter MQTT-Sibling-Cross-Probe-V3-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

Immediately before execution, the user confirmed:

```text
Mäher mäht sichtbar, bleibt voraussichtlich mindestens drei Minuten aktiv und ist beaufsichtigt.
```

No module mower command was authorized or issued.

## 3. Final Preflight

Immediately before the live call:

```text
V3 source hash:        exact
offline V3 gate:       PASS
installed branch:      experiment/native-mqtt-sibling-cross-probe-v3-20260728
installed commit:      b126ec16
repository clean:      true
repository valid:      true
probe count:           1
probe armed:           false
probe receive calls:   0
MQTT shadow:           disabled
WebSocket:             inactive
credentials:           empty
```

## 4. Exact Live Execution

The V3 harness ran once.

Call counts:

```text
Connect:       1
Disconnect:    1
probe Arm:     1
probe Close:   1
probe Delete:  1
retry:         0
MQTT publish:  0
mower command: 0
```

MCP result:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
```

No emergency cleanup was needed.

## 5. Canonical Subscription Proof

Immediately after normal Connect, the retained MQTT Client stored:

```text
subscription count:            4
canonical Topic/QoS entries:   4
legacy entries:                0
invalid entries:               0
all canonical:                 true
```

This closes the migration contract:

- the exact old `QualityOfService` representation was readable before
  connection;
- the corrected lifecycle rewrote it to native integer `QoS = 0`;
- no Core recreation or reparenting was required.

Received native envelopes continue to expose `QualityOfService`; that separate
receive contract remains unchanged.

## 6. Delivery Result

The transport became healthy after 2,355 milliseconds.

```text
samples:                    2
initial MQTT status:        104
initial WebSocket status:   104
final MQTT status:          102
final WebSocket status:     102
WebSocket active throughout: true

productive Receiver delta: 1
sibling probe delta:        1
sibling accepted messages: 1
accepted channel:           location
classification:             both-received
```

Both compatible child instances received the same observed transport event.
The productive Receiver accepted and forwarded it.

The harness stopped as soon as the discriminating result was complete, after
2,394 milliseconds. The planned 165-second cutoff remained only the upper
bound.

## 7. Root-Cause Decision

The retained subscription schema mismatch is now confirmed as the cause of
the tested zero-ingress condition with high confidence:

- the retained topology and both child implementations were preserved;
- the same known-good sibling source was used;
- the normal corrected Connect changed the Core property to native `QoS`;
- the next observed location event reached both children.

This proves corrected ingress, not long-term service stability or complete
MQTT state semantics.

## 8. Automatic Runtime Cleanup

The V3 harness completed before its hard deadline:

```text
Disconnect result:          true
MQTT disable result:        true
probe closed:               true
probe deleted:              true
cleanup before deadline:    true
emergency cleanup:          false
```

Final runtime:

```text
MQTT shadow:                disabled
WebSocket:                  inactive
authorization headers:     empty
MQTT username:              empty
MQTT password:              empty
probe absent:               true
productive Receiver retained: true
```

## 9. Module Control Restoration

Exactly one supported return operation ran:

```text
MC_UpdateModuleRepositoryBranch(
  ModuleControl,
  "symcon-navimow",
  "main"
)
```

Result:

```text
branch: main
commit: 511c7bbe
clean:  true
valid:  true
```

`MC_ReloadModule()` was not used.

The canonical four-entry `QoS` property remains installed and is understood
by corrected productive `main`.

## 10. Productive Compatibility

The complete post-restoration projection passed twice.

All hashes equal the pre-update baseline:

```text
instance topology:  unchanged
variable identity:  unchanged
archive contract:   unchanged
command evidence:   unchanged
```

Verified:

- 14 of 14 variables retained;
- all five user-configured logging contracts retained;
- logged history remains queryable;
- authentication remains connected and usable;
- no reauthentication is required;
- Receiver remains paired;
- Receiver diagnostics now show one accepted and forwarded message.

### Read-only evidence deviation

Two preliminary post-restoration projections failed before execution with:

```text
ParseError: unexpected fully qualified name "\\n"
```

Cause: the transient compatibility reader contained escaped newline text
instead of actual line breaks.

Classification:

- MCP transport succeeded;
- PHP execution failed before producing output;
- no production mutation occurred;
- the transient text was corrected;
- two subsequent independent projections passed completely.

This deviation does not affect the live result or cleanup evidence.

## 11. Git Cleanup

After verified Symcon return to `main`:

```text
temporary remote branch: deleted
temporary local branch:  deleted
remote absence fetched:  verified
publication main:         511c7bbe617ee92801a9d336b96254b9b6a6adda
publication worktree:     clean
```

The temporary probe commit was never merged or tagged.

## 12. Regression Fixture

Promoted:

```text
fixtures/mqtt/transport-subscription-schema-live-v3.json
```

The fixture contains only:

- canonical subscription counts;
- bounded delivery deltas;
- cleanup booleans;
- final classification.

It contains no payload, topic, credential, endpoint, Client ID, Device ID,
ObjectID, coordinate or installation detail.

The fixture test and complete Navimow MQTT suite pass.

## 13. Architecture Decisions

### AD-NAV-511: Close the subscription mismatch as confirmed

**Decision:** Treat canonical native `QoS` as proven productive
configuration.

**Reason:** Core rewrite and delivery to both compatible children were
observed in the same bounded run.

### AD-NAV-512: Keep MQTT disabled after successful ingress

**Decision:** Do not enable MQTT persistently yet.

**Reason:** One live ingress result does not prove restart behavior, token and
credential renewal, prolonged broker stability or complete semantic
reconciliation.

### AD-NAV-513: Retain REST state authority

**Decision:** Public Device state remains REST-authoritative.

**Reason:** MQTT currently acts only as a fast hint for targeted REST
reconciliation.

## 14. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe-v3/
    live-one-shot-result.json
    main-restoration.json
    post-restoration-compatibility.json
    final-runtime.json
    branch-cleanup.json
    gate-d-evidence-closure.json
```

No private topic, payload value, credential, endpoint, Client ID, Device ID,
ObjectID or garden detail appears in this public report.

## 15. Gate Decision

| Gate | Decision |
|---|---|
| canonical Core schema rewrite | PASS |
| productive Receiver ingress | PASS |
| sibling probe ingress | PASS |
| automatic runtime cleanup | PASS |
| corrected `main` restoration | PASS |
| productive and archive continuity | PASS |
| temporary Git cleanup | PASS |
| persistent MQTT enablement | BLOCKED |
| REST state authority | RETAINED |

## 16. Recommended Next Step

Create:

```text
146-native-mqtt-corrected-ingress-review-and-passive-pilot-plan.md
```

The next plan should define a bounded opt-in passive pilot for:

1. persistent but explicitly enabled receive-only MQTT;
2. restart and reconnect behavior;
3. credential refresh and cleanup;
4. bounded diagnostics over several scheduled mower transitions;
5. MQTT-triggered targeted REST reads;
6. zero MQTT state authority and zero publish capability;
7. an immediate disable and cleanup path.
