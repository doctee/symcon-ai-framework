# 249 Native MQTT Episode Root Cause Reconciliation

**Case study:** Navimow native IP-Symcon module

**Status:** Distinct episode count reconciled, false evidence-gap reason
corrected and failure domain narrowed

**Date:** 2026-08-03

**Scope:** Analyze the closed receive-only MQTT pilot without reactivation or
live mutation

## 1. Objective

Step 248 stopped and cleaned the pilot after the cumulative
`unexpectedDisconnects` counter exceeded the one-episode policy. This step
reconciles:

- the raw disconnect counter;
- native episode records;
- five-hour checkpoint coverage;
- credential rotations;
- Core status transitions;
- REST and MQTT ingress context;
- archived mower and online-state transitions.

MQTT remained disabled and credential-free throughout this analysis.

## 2. Read-Only Evidence

The retained diagnostics exceeded the 64-KiB MCP output limit when returned as
one document. The read was split into bounded sanitized projections:

1. checkpoints and episode summaries;
2. rotations and Core transitions;
3. archived `VehicleState` and `Online` transitions.

Every accepted projection reported:

```text
transportError: null
executionError: null
truncated:      false
projection:     PASS
```

No ObjectID, topic, credential, payload, coordinate, hostname or device identity
was persisted in the public evidence.

## 3. Checkpoint Coverage Correction

The compact checkpoint ingested in step 248 contained only the latest native
checkpoint. The harness therefore provisionally added
`evidence-gap-exceeded`.

The complete native sequence proves otherwise:

```text
session-2 checkpoints: 11
normal interval:       18000 seconds
largest interval:      18001 seconds
allowed maximum:       21600 seconds
delayed checkpoints:   one, delayed by 1 second
coverage:              complete
```

The raw harness reason is retained as historical machine evidence, but the
reconciled stop-reason set is:

```text
multiple-transport-episodes
```

## 4. Counter and Episode Reconciliation

At the accepted baseline:

```text
unexpectedDisconnects: 7
last episode sequence:  2
```

At pilot cleanup:

```text
unexpectedDisconnects: 19
last episode sequence:  10
```

Therefore:

| Measure | Delta |
|---|---:|
| unexpected-disconnect counter | 12 |
| distinct native episodes | 8 |
| extra counter increments | 4 |

The implementation increments `unexpectedDisconnects` before calling the
episode-opening function. That function refuses to open another record while
an episode is already active. Multiple observations of the same fault can
therefore increment the counter without creating another episode.

All four extra increments are localized to the checkpoint interval containing
episodes 7, 8 and 9. That interval recorded seven disconnect increments but
only three distinct episodes. Episode 9, which stayed open for 1921 seconds and
needed three reconnect attempts, is the strongest candidate for repeated
observations. The retained data cannot attribute each extra increment to one
specific episode.

## 5. Episode Timeline

Eight new session-2 episodes were retained:

| Episode | Detected (CEST) | Duration | Attempts | Rotation overlap |
|---|---|---:|---:|---|
| 3 | 2026-08-01 10:57:58 | 121 s | 1 | no |
| 4 | 2026-08-02 18:16:17 | 121 s | 1 | no |
| 5 | 2026-08-02 22:07:19 | 121 s | 1 | no |
| 6 | 2026-08-02 23:17:19 | 121 s | 1 | no |
| 7 | 2026-08-03 03:37:23 | 120 s | 1 | no |
| 8 | 2026-08-03 04:09:25 | 120 s | 1 | no |
| 9 | 2026-08-03 04:19:25 | 1921 s | 3 | yes |
| 10 | 2026-08-03 19:21:41 | 120 s | 1 | no |

Seven episodes recovered through the standard one-attempt path. Episode 9 was
the only prolonged event.

## 6. Shared Failure Signature

Every new episode:

- was detected by `lifecycle-observation`;
- began with native MQTT and WebSocket status `200/200`;
- had a preceding Core error transition;
- retained MQTT ingress before detection;
- retained successful REST communication with connection state 3;
- recovered without a connection-failure increment;
- did not exhaust reconnect recovery;
- remained in the same kernel epoch.

The Core fault preceded lifecycle detection by 1 to 59 seconds. This confirms
that the native Core or upstream WSS path failed before the Account polling loop
reacted.

## 7. Credential-Rotation Correlation

Credential rotations continued at the expected interval. Separation from the
nearest preceding rotation varied from 366 to 3006 seconds.

Episodes 5 and 7 occurred 366 seconds after a rotation, but other episodes had
substantially different separations. Episode 9 overlapped a rotation because
the episode was already open for more than half an hour; the rotation did not
open that episode.

The evidence does not support the module's credential-rotation operation as a
general immediate trigger. A server-side session policy or broker behavior
cannot be excluded with the available native status detail.

## 8. Mower and REST Correlation

The archive contains two mowing cycles during the retained window:

```text
2026-08-03 10:00 to 12:17 CEST
2026-08-03 17:15 to 18:57 CEST
```

No transport episode occurred while the mower was running. Episode 10 began
approximately 24 minutes after the second return to the dock. No `Online`
transition was archived during the complete pilot window.

Mowing, docking and REST-visible device availability are therefore not
supported as triggers.

## 9. Root-Cause Boundary

Confirmed:

```text
failure domain: native WebSocket or upstream WSS transport
distinct failures: 8
counter observations: 12
payload parsing: not implicated
REST authority: retained
kernel restart: excluded
mower activity: not correlated
automatic recovery: functional but one episode prolonged
```

Not distinguishable from retained evidence:

- local network or Internet interruption;
- native WebSocket Core defect;
- broker- or server-side session close;
- remote idle or session policy;
- the specific meaning of generic Core status `200`.

## 10. Safety State

The final immediate and delayed reads remain authoritative:

- MQTT and WebSocket inactive at `104/104`;
- WebSocket authorization absent;
- MQTT username and password absent;
- lifecycle `Disabled`;
- REST operational;
- 14 variables retained;
- 5 Archive loggings retained;
- no open native episode.

## 11. Architecture Decisions

### AD-NAV-921: Use episode sequence for distinct-failure policy

`unexpectedDisconnects` is an observation counter and may increase repeatedly
inside one open episode. Future pilot policy must distinguish that counter from
the number of distinct episode records.

### AD-NAV-922: Preserve full checkpoint coverage during compaction

Checkpoint timestamps are small and policy-relevant. Output reduction must not
discard entries required to prove continuity.

### AD-NAV-923: Treat episode 9 as one prolonged failure

Its additional disconnect observations and three reconnect attempts do not
represent five separate causal episodes.

### AD-NAV-924: Keep the external trigger unresolved

Generic native status `200` narrows the layer but does not identify a close
code, network cause or broker decision.

### AD-NAV-925: Block reactivation pending diagnostic contract correction

Another pilot would reproduce ambiguous counting and oversized evidence. The
diagnostic and harness contracts must be corrected and offline-tested first.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| MQTT reactivation | NOT PERFORMED |
| native checkpoint continuity | PASS |
| evidence-gap stop reason | INVALID, CORRECTED |
| distinct episode count | 8 |
| disconnect observation delta | 12 |
| multiple-episode failure | CONFIRMED |
| exact external trigger | UNRESOLVED |
| automatic recovery | FUNCTIONAL |
| cleanup | COMPLETE |
| another pilot | BLOCKED |

## 13. Next Step

Proceed with:

```text
250-native-mqtt-episode-accounting-and-bounded-projection-design.md
```

That design should:

1. expose the cumulative episode sequence in diagnostics;
2. keep observation and distinct-episode counters separate;
3. make the harness use distinct episodes for its policy;
4. provide a bounded summary projection below the MCP limit;
5. preserve all checkpoint timestamps required for coverage;
6. add offline traces for repeated observations inside one open episode.

No publication, Symcon update or MQTT activation belongs to that design step.
