# 238 Native MQTT Pilot Episode Root Cause Analysis

**Case study:** Navimow native IP-Symcon module

**Status:** Failure domain narrowed; exact external trigger unresolved

**Date:** 2026-07-31

**Scope:** Analyze the two recovered transport episodes from the closed native
receive-only MQTT pilot without changing the module or the live installation

## 1. Objective

Step 237 closed the pilot after the second unexpected transport episode and
proved complete cleanup. This step correlates the retained episode, credential
rotation, REST and vehicle-state evidence with the implemented MQTT lifecycle.

The analysis must distinguish:

- observed facts from timing-based inference;
- native Core status from Navimow payload processing;
- measured lifecycle duration from the unknown external outage duration;
- a correlated mower transition from a proven transport cause.

No live mutation, MQTT activation, publication, mower command or productive
code change belongs to this step.

## 2. Episode Timeline

The retained pilot diagnostics contain two closed episodes:

| Episode | Detected (CEST) | Recovered (CEST) | Recorded duration | Core status | Reconnects |
| --- | --- | --- | ---: | --- | ---: |
| 1 | 2026-07-30 15:33:36 | 15:35:37 | 121 seconds | MQTT `200`, WebSocket `200` | 1 |
| 2 | 2026-07-30 17:14:37 | 17:16:37 | 120 seconds | MQTT `200`, WebSocket `200` | 1 |

Both episodes:

- were detected by the 60-second Account lifecycle observation;
- recovered automatically on the first scheduled reconnect;
- did not exhaust the retry sequence;
- did not overlap a recorded credential rotation;
- did not cross a kernel epoch;
- left no connection-failure increment;
- occurred below an otherwise operational REST path.

IP-Symcon defines status `102` as active, `104` as inactive and values from
`200` as error states. The generic value `200` proves an erroneous Core
instance state, but does not identify the native error subtype or remote close
reason.

Reference:
<https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/getstatus/>

## 3. Duration Interpretation

The recorded 120/121 seconds are lifecycle-state durations, not measurements
of the underlying network outage.

The implementation observes transport health every 60 seconds. After detecting
an unexpected disconnect it:

1. records the episode and both Core statuses;
2. disconnects the owned native transport;
3. schedules the first reconnect after 60 seconds;
4. closes the episode only when a later healthy observation confirms recovery.

Consequently, the external fault could have been shorter than the recorded
episode. The available evidence cannot determine its exact start, end or
duration.

## 4. Credential Rotation Correlation

Credential rotations were regular at approximately 3300-second intervals.
The nearest preceding rotations were:

| Episode | Prior rotation (CEST) | Separation |
| --- | --- | ---: |
| 1 | 15:10:30 | 1386 seconds |
| 2 | 17:00:31 | 846 seconds |

Neither episode was open during a rotation. This matters because the productive
rotation path deliberately disconnects the owned transport, records a rotation
and schedules the separate `rotation` lifecycle path. The observed episodes
instead entered through `lifecycle-observation` as unexpected disconnects.

Direct rotation causality is therefore not supported by the retained evidence.
This does not prove that all token, broker-session or server-side expiry effects
are impossible; it only excludes the module's recorded rotation operation as
the immediate trigger.

## 5. Mower-State Correlation

The retained REST-authoritative transition history records `Running` at
17:16:07:

- 90 seconds after episode 2 was detected;
- 30 seconds before episode 2 was marked recovered.

The mower start is temporally close to episode 2, but episode 1 has no matching
start transition. MQTT is receive-only, and REST remains the sole authority for
the public device state. The evidence therefore supports correlation only, not
a causal relation between mowing start and transport failure.

## 6. Failure-Domain Assessment

The strongest supported statement is:

> Both pilot episodes originated in the native WebSocket or its upstream
> transport path before Navimow MQTT payload parsing, and both recovered through
> the existing bounded reconnect path.

MQTT is layered on the native WebSocket instance. Simultaneous status `200` on
both instances therefore does not prove two independent failures. The WebSocket
failure can propagate to the MQTT client.

Plausible unresolved trigger classes are:

- a transient native WebSocket Core failure;
- a brief local network or Internet interruption;
- a broker- or server-side WSS session close;
- another native Core condition represented only by generic status `200`.

The following causes are not supported by the evidence:

- the module's direct credential-rotation operation;
- an IP-Symcon service or kernel restart;
- persistent authentication, configuration, ownership or subscription failure;
- a Navimow payload-parser failure;
- loss of REST authority or archive contracts.

## 7. Diagnostic Gap

The pilot diagnostics correctly detected and bounded each episode, but did not
retain enough context to identify the external trigger. Missing evidence is:

- the native Core status transition time before lifecycle detection;
- a sanitized native Core message or error classification, if available;
- last MQTT ingress time and age at detection;
- last REST success, REST state and their age at the same instant;
- distinct reconnect-start, Core-ready and confirmation timestamps;
- explicit nearest-rotation and nearest-vehicle-transition correlations.

The current error ring exposes the bounded reason
`unexpected-disconnect`, but no native WebSocket close code or error subtype.

## 8. Correction Boundary

Do not weaken the one-episode pilot threshold on the current evidence. The
threshold did its job: it stopped a reliability pilot after a repeated
unexplained fault while cleanup was still deterministic.

Before another long pilot, extend diagnostics only:

- keep the transport receive-only;
- preserve REST as sole public-state authority;
- do not alter reconnect delays, retry counts or stop policy;
- keep evidence bounded, identity-free, non-archived and sanitized;
- record correlations as evidence, never as inferred cause;
- preserve cleanup and credential-removal contracts.

## 9. Current Safety State

The post-pilot live read-only state remains safe:

- module repository clean and valid at
  `main@793249ece1c0944192ea28dade7ecd2340a5135f`;
- MQTT disabled;
- MQTT and WebSocket inactive;
- Authorization header and MQTT credentials absent;
- REST connected and operational;
- reauthentication not required;
- all 14 variable contracts and all 5 archive contracts stable;
- retained pilot history closed with complete cleanup.

## 10. Architecture Decisions

### AD-NAV-864: Interpret episode duration as lifecycle duration

The 120/121-second values describe detection-to-confirmed-recovery time. They
must not be reported as exact network-outage duration.

### AD-NAV-865: Exclude direct rotation causality

The module's recorded credential rotations neither overlapped the episodes nor
used their lifecycle path. Direct rotation causality is excluded for these two
events.

### AD-NAV-866: Treat mower start as correlation only

The `Running` transition near episode 2 is retained as timing evidence. It
cannot establish causality and does not explain episode 1.

### AD-NAV-867: Place the confirmed fault below Account payload parsing

The simultaneous native Core error statuses and absence of parser or REST
failure narrow the confirmed domain to WebSocket or upstream transport.

### AD-NAV-868: Improve evidence before changing recovery policy

The next implementation may add bounded diagnostic context only. Retry,
recovery, authority and pilot-stop semantics remain unchanged until stronger
evidence exists.

## 11. Gate Decision

The root-cause analysis is complete at the level supported by current evidence:

```text
confirmed failure domain: native WebSocket or upstream transport
exact external trigger:   unresolved
direct rotation trigger:  excluded
kernel restart trigger:   excluded
mower-start causality:    not established
recovery path:            successful on first reconnect
policy change:            blocked
diagnostic hardening:     recommended
```

The next SAEF step is
`239-native-mqtt-episode-diagnostic-hardening-design.md`. It should specify the
minimal bounded evidence extension and its offline tests. Publication, Symcon
update and any new pilot activation remain separate explicit gates.
