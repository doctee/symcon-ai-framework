# 199 Native MQTT Core Resume Health Observation Deadline and Diagnostics Review

**Case study:** Navimow native IP-Symcon module
**Status:** Design approved for implementation; no productive code changed
**Date:** 2026-07-29
**Scope:** Review the restart observation deadline and bounded diagnostics after
the successful step 198 live retry

## 1. Purpose

Step 198 proved that IP-Symcon can retain and resume the native MQTT and
WebSocket Core instances across a restart without an Account reconnect.

The retained Core became canonically healthy at the exact final observation
point of the existing 90-second deadline. This step therefore:

1. evaluates whether the current deadline has sufficient operational reserve;
2. defines a longer bounded observation schedule;
3. preserves immediate adoption of an already healthy Core;
4. specifies the resulting recovery and credential-lifetime behavior;
5. closes the diagnostic schema finding from the live evidence probe;
6. freezes the implementation and regression scope for the next SAEF step.

This is an architecture and readiness decision only. It changes neither the
module distribution nor the live Symcon installation.

## 2. Evidence Basis

The decisive step 198 observations were:

| Absolute offset after Account kernel observation | MQTT | WebSocket | Entry health |
|---:|---:|---:|---|
| `+15 s` | 200 | 200 | unhealthy |
| `+30 s` | 200 | 200 | unhealthy |
| `+60 s` | 200 | 200 | unhealthy |
| `+90 s` | 102 | 102 | healthy |

The module then adopted the retained Core as `ShadowActive` with reason
`core-resumed`. Account connection attempts, successes and failures did not
change. Mandatory cleanup passed immediately and after 153 seconds.

The result proves the Core-resume path but gives the current 90-second deadline
no observed scheduling reserve on this installation.

## 3. Root Conclusion

The implementation defect addressed by the multi-observation mechanism is
resolved. The remaining weakness is the selected observation horizon:

```text
current healthy point:  +90 seconds
current deadline:       +90 seconds
observed reserve:         0 seconds
```

A slow IP-Symcon service start, delayed Core child initialization or delayed
timer processing could therefore cross the current deadline even when the Core
would become healthy shortly afterwards.

The appropriate correction is to extend the bounded deadline. It is not to
reconnect immediately, poll continuously or weaken the canonical Core-health
predicate.

## 4. Deadline Decision

The next implementation shall use these absolute offsets from
`kernelStartObservedAt`:

```text
+15, +30, +60, +90, +120, +180 seconds
```

The resulting deadline is:

```text
kernelStartObservedAt + 180 seconds
```

For the observed installation, the expected behavior remains adoption at
`+90 s`. The additional `+120 s` and `+180 s` observations are reserve points;
they do not impose an extra wait after a healthy result.

| First healthy observation | Interpretation |
|---:|---|
| `+15 s` or `+30 s` | comfortable reserve |
| `+60 s` or `+90 s` | adequate for the private pilot |
| `+120 s` | pass, but monitor reduced reserve |
| `+180 s` | technical boundary pass; reassess before broader pilot |
| none by `+180 s` | deadline failure and bounded recovery path |

## 5. State-Machine Contract

The extended schedule must preserve the existing state-machine semantics:

1. Observation times are absolute offsets, not chained relative delays.
2. A late timer may evaluate all due offsets exactly once.
3. A healthy Core is adopted at the first due observation that satisfies every
   canonical predicate.
4. Later observation points are cancelled after healthy adoption.
5. An unhealthy intermediate result causes no reconnect and no cleanup.
6. Recovery may start only after the final `+180 s` observation remains
   unhealthy.
7. Recovery remains bounded by the existing retry policy.
8. Authentication and configuration failures remain non-retryable.
9. REST remains authoritative and native MQTT remains receive-only.
10. MQTT publishing remains prohibited.

No deadline extension may cause observation replay after `ApplyChanges()`,
timer delay or repeated kernel-start handling.

## 6. Diagnostic Contract

The bounded diagnostic history shall grow from four to six entries so that one
entry can be retained for every scheduled observation:

```text
maximum entries: 6
entry health key: healthy
```

Each entry continues to expose:

- ordinal and absolute offset;
- observation timestamp;
- MQTT and WebSocket status;
- WebSocket active state;
- presence booleans for Authorization, MQTT username and MQTT password;
- last receive timestamp;
- canonical `healthy` boolean.

The top-level lifecycle projection continues to own:

- `lastKernelCoreClassification`;
- `lastKernelCoreClassificationAt`;
- `lastKernelCoreFailedPredicates`;
- `kernelCoreObservationCount`;
- `kernelCoreObservationDeadlineAt`;
- `kernelCoreObservations`.

The step 198 custom evidence probe incorrectly requested per-entry fields named
`classification` and `failedPredicates`. The module serializer is consistent
with its tested contract. Reusable private probes must read the per-entry
`healthy` field and the top-level classification fields; the productive schema
must not be expanded solely to accommodate that probe error.

## 7. Recovery and Security Effect

Extending the deadline delays automatic cleanup or recovery by at most
90 seconds compared with the current implementation.

During an activated restart test, Authorization and MQTT credentials can remain
inside the module-owned IP-Symcon Core instances throughout this observation
window. This is an explicit availability and safety tradeoff:

- it avoids destroying a Core that is still starting normally;
- it avoids an unnecessary Account reconnect;
- it preserves the bounded final deadline;
- it does not authorize indefinite credential retention;
- mandatory disable and cleanup remain required after each supervised test.

The existing transient-network retry contract remains unchanged:

- exactly three bounded connection attempts;
- no retry for authentication or configuration errors;
- no command execution through MQTT;
- REST fallback and authority remain intact.

## 8. Token-Horizon Impact

Replacing the 90-second post-ready allowance with 180 seconds raises the
conservative full operation budget from 1560 to 1650 seconds.

The established gates remain sufficient:

| Gate | Threshold | Reserve against 1650-second full budget |
|---|---:|---:|
| activation | 2400 seconds | 750 seconds |
| restart arm | 1800 seconds | evaluated later in the flow and still sufficient |

The restart-arm threshold covers only the remaining restart sequence, not the
already completed activation and baseline phases. It therefore does not need
to increase solely because of the 90-second deadline extension.

Token horizon must still be checked from read-only diagnostics immediately
before activation and again before the externally initiated restart.

## 9. Implementation Scope

The productive change is intentionally narrow:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

Required constant changes:

```text
MQTT_KERNEL_CORE_OBSERVATION_OFFSETS_SECONDS
  [15, 30, 60, 90]
  -> [15, 30, 60, 90, 120, 180]

MQTT_KERNEL_CORE_OBSERVATION_MAX_ENTRIES
  4 -> 6
```

No public property, form field, variable, profile, action, module GUID,
instance topology or user configuration changes.

The mirrored publication repository is outside this review step and must only
be updated after implementation validation and explicit publication approval.

## 10. Required Regression Matrix

The implementation step must prove offline:

1. the exact six absolute offsets and `+180 s` deadline;
2. immediate healthy adoption independently at every offset;
3. no later observation after adoption;
4. unhealthy results retained through `+120 s` without reconnect;
5. exactly one recovery transition after an unhealthy `+180 s` result;
6. late timer execution processes due offsets once and in order;
7. repeated timer calls do not duplicate observations;
8. history is bounded to six entries;
9. per-entry `healthy` and top-level classification fields serialize correctly;
10. existing retry, cleanup and disabled-state behavior remains unchanged;
11. REST authority, command behavior and all public contracts remain unchanged;
12. the full Navimow validation suite, PHP syntax checks, coding standard,
    static analysis, distribution validation and Symcon Module Validator pass.

Private live probes used in a later supervised test must also be corrected to
consume the canonical diagnostic fields.

## 11. Architecture Decisions

### AD-NAV-706: Extend the Core observation deadline to 180 seconds

**Decision:** Observe at `+15`, `+30`, `+60`, `+90`, `+120` and `+180 s`.

**Reason:** The retained Core first became healthy at the former boundary.
Two bounded reserve points account for legitimate slower startup behavior.

### AD-NAV-707: Preserve immediate healthy adoption

**Decision:** Adopt the Core at the first healthy scheduled observation.

**Reason:** The longer deadline is a fallback horizon, not a mandatory delay.

### AD-NAV-708: Bound observation diagnostics at six entries

**Decision:** Retain at most one entry per scheduled offset.

**Reason:** The complete decision path remains inspectable without unbounded
Registry growth.

### AD-NAV-709: Keep `healthy` as the canonical entry field

**Decision:** Correct private evidence probes instead of adding duplicate
per-entry classification fields.

**Reason:** The productive serializer and regression contract already agree.

### AD-NAV-710: Defer recovery until the extended final deadline

**Decision:** Do not reconnect or clean up on unhealthy intermediate results.

**Reason:** Premature recovery would defeat retained-Core reconciliation and
can create avoidable connection churn.

### AD-NAV-711: Preserve all public module contracts

**Decision:** Limit the productive change to internal timing and bounded
diagnostic capacity.

**Reason:** Existing variables, logging history, actions and user
configuration must remain stable.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| step 198 technical Core resume | PASS |
| current 90-second broader-pilot reserve | INSUFFICIENT |
| 180-second design | APPROVED FOR IMPLEMENTATION |
| productive code in this step | NONE |
| live Symcon mutation in this step | NONE |
| MQTT state after this step | disabled and credential-free |
| next live restart | blocked until implementation, validation, publication and renewed acceptance |

## 13. Next Step

Proceed with:

```text
200-native-mqtt-core-resume-health-observation-deadline-hardening-implementation.md
```

That step should implement the two constant changes, update the complete
offline regression matrix, correct reusable private diagnostic probes and
validate the installable distribution. Publication and another live restart
remain separate, explicitly authorized steps.
