# 182 Native MQTT Post-Ready Core Health Failure Analysis

**Case study:** Navimow native IP-Symcon module
**Status:** Offline analysis complete; root cause narrowed but not proven
**Date:** 2026-07-29
**Scope:** Analyze the failed active Core-resume adoption from step 181

## 1. Purpose

Step 181 proved that the durable kernel barrier and its exact 15-second
post-ready delay work. The same live run then classified the retained native
MQTT chain as `unhealthy-with-credentials` and entered bounded recovery.

This step:

1. corrects the receive-counter interpretation from step 181;
2. reconstructs the exact productive decision path;
3. separates established facts from hypotheses;
4. checks the current tests against the live failure;
5. defines the next offline correction boundary.

It performs no Symcon mutation, MQTT connection, restart, publication or mower
command.

## 2. Evidence Correction

The final pre-restart baseline and first reconciled projection showed:

```text
received: 309 -> 311
accepted: 309 -> 311
rejected:   0 ->   0
```

The final baseline was captured before the user performed the restart. It did
not preserve `lastReceivedAt` or a source-message timestamp. The `+2` delta is
therefore bounded to:

```text
final pre-restart baseline
  -> user-performed restart at an unrecorded instant
  -> first reconciled projection
```

Its timing relative to the restart is unresolved. It cannot prove
post-restart ingress or temporary Core health.

The following artifacts now encode that stricter interpretation:

- `181-native-mqtt-transient-readiness-correction-live-restart-and-cleanup.md`;
- `fixtures/mqtt/core-resume-post-ready-unhealthy-live.json`;
- `fixtures/mqtt/README.md`;
- the private step-181 evidence closure.

## 3. Official Runtime Semantics

The official IP-Symcon SDK defines:

- `IPS_KERNELSTARTED` as a synchronous message sent after `KR_READY`;
- status `102` as active;
- status `104` as inactive.

The WebSocket Client documentation states that applying its configuration
saves the settings and then attempts to establish the connection. It does not
promise that a retained native I/O connection has reached status `102` by
`IPS_KERNELSTARTED` or within a fixed interval afterward.

References:

- [IP-Symcon messages and kernel states](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/nachrichten/)
- [IP-Symcon module status codes](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/getstatus/)
- [IP-Symcon WebSocket Client](https://www.symcon.de/de/service/dokumentation/modulreferenz/io/websocketclient/)
- [IP-Symcon MQTT Client](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-client/)

The measured `197` seconds from kernel epoch to Account observation therefore
describe the observed kernel-start-to-ready interval. They do not establish
when the WebSocket or MQTT client attempted, achieved or lost connectivity.

## 4. Exact Productive Decision Path

The current Account implementation performs:

```text
IPS_KERNELSTARTED
  -> record current kernel epoch and observation timestamp
  -> schedule kernel reconciliation for +15 seconds
  -> validate feature, Account configuration, token and ownership
  -> inspect exactly one Core-health projection
```

Core health is a strict conjunction:

```text
MQTT instance status == 102
AND WebSocket instance status == 102
AND WebSocket configuration Active == true
```

If that projection is false while credential fields are still present, the
Account immediately:

1. records `unhealthy-with-credentials`;
2. increments `unexpectedDisconnects`;
3. records `unexpected-disconnect`;
4. clears the owned Core credentials and deactivates the WebSocket;
5. marks the kernel epoch reconciled;
6. schedules normal reconnect recovery.

The first live projection was captured after this branch had completed. Its
`104/104`, `Active=false`, empty credential fields and changed Core
configuration hashes are therefore post-recovery observations. They do not
identify which health predicate first failed at the classification instant.

## 5. Established Facts

| Observation | Result |
|---|---|
| installed correction | `main@7d141f76` |
| new kernel epoch | yes |
| kernel observation recorded | yes |
| observation-to-reconciliation delay | exactly 15 s |
| configuration validation before classification | passed |
| usable Account token | yes |
| topology hash | unchanged |
| Core classification | `unhealthy-with-credentials` |
| Account Core-resume adoption | no |
| Account connection attempt delta | 0 |
| Account success/failure delta | 0/0 |
| cleanup | passed |
| final MQTT state | disabled and credential-free |
| REST authority | retained |

The live result proves the failure location is after the corrected durable
barrier and before successful Core-resume adoption.

## 6. Causes Excluded by Evidence

The following causes are inconsistent with the captured result:

- **Premature pre-ready reconciliation:** the exact 15-second post-message
  delay executed.
- **Changed or missing owned topology:** validation passed and the topology hash
  remained stable.
- **Invalid Account configuration:** the configuration gate passed.
- **Unavailable Account authentication:** the token remained usable.
- **An Account credential request or reconnect before classification:**
  attempt, success and failure counters did not change.
- **Cleanup failure:** immediate and delayed disabled-state checks passed.
- **MQTT payload/parser failure:** no rejected-message increase occurred.

These exclusions do not prove broker availability or retained native Core
credential validity.

## 7. Remaining Hypotheses

### H1: Native Core readiness exceeded the fixed grace period

At `IPS_KERNELSTARTED + 15 s`, one or both Core instances may still have been
inactive or connecting.

**Plausibility:** high. The official runtime contracts do not guarantee status
`102` within this period, and the implementation observes only one instant.

### H2: Retained Core configuration was reapplied during startup

Parent/child `ApplyChanges()` ordering may have temporarily changed
`Active`, status or connection state after the Account received
`IPS_KERNELSTARTED`.

**Plausibility:** medium. The current evidence lacks a startup status timeline.

### H3: Broker or network connectivity failed during startup

The WebSocket may have attempted connection but encountered a transient broker,
DNS, TLS or network failure.

**Plausibility:** medium. The first captured projection followed destructive
cleanup and contains no pre-cleanup status history.

### H4: Retained MQTT credentials were no longer accepted

The Core could have restored the fields but failed authentication before the
Account classified health.

**Plausibility:** possible but unproven. Account OAuth remained valid, but that
does not independently prove the private MQTT credential lifetime.

### H5: The Core resumed briefly and disconnected

The native chain may have reached active state and then become inactive before
the 15-second projection.

**Plausibility:** unresolved. The `+2` counters cannot be placed relative to
the restart, so they do not support this hypothesis.

## 8. Diagnostic Blind Spots

The live procedure and runtime diagnostics did not preserve:

- the exact external restart instant;
- `lastReceivedAt` in the final baseline;
- pre-cleanup MQTT and WebSocket statuses at classification;
- the pre-cleanup WebSocket `Active` boolean;
- a per-predicate Core-health result;
- native status transitions between `IPS_KERNELSTARTED` and classification;
- native Core error details before credential cleanup.

The recovery branch changes the same configuration needed to explain the
failure. A later read-only projection can verify cleanup but cannot reconstruct
the failed predicate.

## 9. Test-Gap Analysis

The lifecycle harness currently has two neighboring contracts:

1. the transient-readiness fixture restores a healthy Core before the
   `IPS_KERNELSTARTED` message and expects adoption after 15 seconds;
2. the unhealthy-Core test holds both native instances at `104` and expects
   immediate cleanup plus a 60-second reconnect schedule after the same delay.

Neither contract models:

```text
valid retained credentials
  -> Core still inactive at +15 s
  -> Core becomes healthy later without Account reconnection
```

The current regression suite therefore validates the implementation that
failed live. It does not test bounded delayed native readiness.

## 10. Required Correction Boundary

The next correction should replace the single destructive snapshot with a
bounded post-ready Core-health observation window.

Proposed behavioral contract:

```text
IPS_KERNELSTARTED
  -> durable epoch barrier
  -> initial 15-second grace
  -> observe Core health without mutation
  -> adopt immediately when 102 / 102 / Active=true
  -> otherwise repeat bounded read-only observations
  -> clean and enter normal reconnect only at the deadline
```

Candidate observation schedule:

```text
+15 s, +30 s, +60 s, +90 s after IPS_KERNELSTARTED
```

The exact deadline remains a design decision for the next step. It must be
long enough for native startup variance but bounded so stale credentials and a
dead transport are not retained indefinitely.

Immediate fail-closed behavior remains required for:

- disabled MQTT feature;
- invalid Account configuration;
- unusable Account authentication;
- invalid ownership or topology;
- explicit credential-free Core state.

Feature disable must continue to win immediately and perform normal cleanup.

## 11. Required Diagnostics

Before any destructive recovery, the Account should persist only
privacy-safe facts:

```text
observation ordinal
observation timestamp
MQTT status code
WebSocket status code
WebSocket Active boolean
authorization-present boolean
MQTT-username-present boolean
MQTT-password-present boolean
lastReceivedAt, when already available as a timestamp
final failed health predicate set
```

No credential value, endpoint, private topic, payload, device identity or
installation ObjectID may enter public diagnostics or fixtures.

## 12. Architecture Decisions

### AD-NAV-635: Reject causal claims from an interval-only counter delta

**Decision:** Treat the step-181 `+2` receive/accept delta as interval evidence
with unresolved timing.

**Reason:** The interval began before the unrecorded restart instant and lacked
a final `lastReceivedAt` baseline.

### AD-NAV-636: Do not infer the failed health predicate after cleanup

**Decision:** Record only that strict Core health failed in step 181.

**Reason:** The recovery branch deactivated and sanitized the Core before the
first projection, changing all three observed health inputs.

### AD-NAV-637: Replace one-shot health classification with bounded observation

**Decision:** Design a finite post-ready health window before destructive
recovery.

**Reason:** `KR_READY` establishes kernel readiness, not a documented deadline
for every native network client to reach status `102`.

### AD-NAV-638: Preserve fail-closed ownership and authentication gates

**Decision:** Extend grace only for a structurally valid,
credential-bearing owned Core chain.

**Reason:** Readiness tolerance must not weaken configuration, ownership,
authentication or disable safety.

### AD-NAV-639: Capture the decisive pre-cleanup projection

**Decision:** Add privacy-safe per-predicate health diagnostics before cleanup.

**Reason:** A post-cleanup projection cannot distinguish delayed readiness,
configuration loss, authentication rejection or transient network failure.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| step-181 evidence interpretation | CORRECTED |
| durable kernel barrier | RETAIN |
| fixed one-shot health decision | REJECT |
| exact root cause | NOT PROVEN |
| bounded observation redesign | REQUIRED |
| productive PHP change in this step | NONE |
| publication | CLOSED |
| further active testing | CLOSED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

## 14. Current Safe State

The last verified live state remains:

```text
installed commit:           7d141f76
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
REST state authority:       retained
```

No live state was read or changed in this offline step.

## 15. Recommended Next Step

Proceed offline with:

```text
183-native-mqtt-core-resume-health-observation-design.md
```

That step should freeze:

- the bounded observation schedule and deadline;
- lifecycle states and idempotency rules;
- immediate negative gates;
- privacy-safe pre-cleanup diagnostics;
- synthetic delayed-readiness, never-ready, disable-wins and ownership-drift
  test cases;
- the publication and renewed live-test gates.

No additional restart should be authorized until that design is implemented
and the complete offline MQTT gate passes.
