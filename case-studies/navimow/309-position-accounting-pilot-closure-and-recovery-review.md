# 309 Position Accounting Pilot Closure And Recovery Review

**Case study:** Navimow native IP-Symcon module

**Status:** Position-accounting stabilization proved; receive-only pilot failed
on repeated transport episodes and late closure; one post-deadline recovery
sequence exhausted; mandatory cleanup passed

**Date:** 2026-08-13

## 1. Purpose

Steps 305 through 308 stopped safely before establishing a corrected pilot.
The subsequent separately authorized restart-free path reduced only the
mutation-time token horizon from 2400 to 1200 seconds, performed exactly one
activation attempt and established two stable active baselines.

This step closes that observation. It:

1. evaluates the bounded receive-only evidence;
2. separates session-local position accounting from cumulative runtime
   diagnostics;
3. records the final recovery exhaustion and the missed 72-hour closure;
4. verifies immediate and delayed credential-free cleanup; and
5. decides what may proceed next without treating MQTT as production-ready.

It introduces no productive PHP change, publication, Symcon update, restart or
mower command.

## 2. Activation Boundary

The final restart-free readiness path used this bounded exception:

```text
minimum token horizon: 1200 seconds
service restart:       prohibited
activation attempts:   exactly one
automatic retry:       prohibited
cleanup:               mandatory
```

The lower horizon was not a general relaxation of the 2400-second restart
budget. The omitted restart, service-start and Core-resume phases removed the
time reserve for which the larger threshold had been defined.

The single activation advanced the native observation to session 4. Two
read-only active baselines were captured 86 seconds apart and agreed on:

- exact clean and valid standalone commit;
- unchanged identity, Archive, command, topology and subscription contracts;
- MQTT and WebSocket Core status `102/102`;
- lifecycle `ShadowActive`;
- REST operational and authoritative;
- receive-only MQTT and diagnostic-only local position handling; and
- no open transport episode.

The observation clock started from the second stable active baseline.

## 3. Observation Duration

```text
native session start: 2026-08-09 11:06:52 UTC
pilot clock start:    2026-08-09 11:09:41 UTC
cleanup:              2026-08-13 03:29:10 UTC
observed duration:    317969 seconds
observed duration:    88.325 hours
approved maximum:     72 hours
```

The 48-hour checkpoint passed while the transport was `ShadowActive`. The
mandatory 72-hour boundary was not closed on time. The excess observation does
not extend the approved pilot and must not be counted as evidence that the
72-hour policy passed.

## 4. Position Accounting Result

The productive stabilization from steps 298 through 304 passed its primary
live objective. Pilot-wide coordinate-free counters increased monotonically
across ephemeral coordinate cleanup and transport segments:

| Counter | 48-hour checkpoint | Final pre-cleanup |
|---|---:|---:|
| received position samples | 9482 | 18583 |
| coordinate changes | 9424 | 18474 |
| out-of-order timestamps | 32 | 71 |
| segment sequence | 57 | 104 |
| counter reset count | 56 | 103 |

The result proves:

- real local-position payload reception over multiple transport segments;
- continued aggregate growth after ephemeral coordinate cleanup;
- no return to the prior segment-local counter regression; and
- preservation of the coordinate privacy boundary.

Coordinates, orientation, private topics and retained tracks are not part of
the public evidence. The final aggregate does not independently reconstruct the
number of complete natural mowing cycles, so the older cycle-count gate is not
claimed as passed by this step.

```text
position reception:                 PASS
pilot-wide position accounting:     PASS
coordinate privacy boundary:        PASS
natural-cycle count in this report: NOT PROVED
```

## 5. Transport Result

The native diagnostics are cumulative across sessions. Session-4 deltas are
therefore calculated from the first stable active baseline instead of treating
the final lifetime counters as one-pilot values:

| Diagnostic | Session-4 delta |
|---|---:|
| connection attempts | 127 |
| connection successes | 101 |
| connection failures | 0 |
| unexpected disconnect observations | 33 |
| reconnect attempts | 31 |
| exhausted reconnect sequences | 1 |
| credential rotations | 96 |
| MQTT messages received | 20405 |
| MQTT messages accepted | 20404 |
| MQTT messages rejected | 1 |

The active baseline started at episode sequence 25. The 48-hour checkpoint
reported sequence 41, proving 16 new distinct episodes before early completion
was even eligible. The operating policy allowed at most one recovered episode
and required immediate closure on the second. The pilot had therefore already
failed its transport-stability and automatic-stop gates within the approved
window.

At the final pre-cleanup projection:

```text
lifecycle:              Disconnected
transition reason:      reconnect-exhausted
reconnect attempt:      3
MQTT/WebSocket status:  104/104
WebSocket active:       false
transport credentials:  absent
REST:                   operational
reauthentication:       not required
```

The exhausted episode began 58037 seconds after the approved 72-hour deadline.
It is useful forensic evidence for long-outage behavior, but it must not be
backdated into the approved observation window. REST continued independently
and no productive Device variable became MQTT authoritative.

The available aggregate evidence does not identify whether the final episode
originated in the local network, native WebSocket Core, upstream WSS service or
credential timing. Retry counts, delays and authentication behavior remain
unchanged until a separate root-cause analysis supports a specific correction.

## 6. Cleanup Result

The authorized cleanup executed exactly one Account `ApplyChanges()` after
setting both pilot properties to `false`.

Immediate postconditions passed:

- MQTT disabled;
- position diagnostics disabled and observation removed;
- WebSocket inactive;
- Authorization header absent;
- MQTT username and password absent;
- Receiver selection retained; and
- REST operational.

A second read-only projection after more than 180 seconds proved stable:

```text
lifecycle:             Disabled
next attempt:          0
pilot active:          false
next checkpoint:       0
open episode:          none
transport credentials: absent
REST:                  operational
```

The retained `reconnectAttempt=3` is historical diagnostic state. In
combination with `Disabled`, `nextAttemptAt=0`, inactive Core transport and
absent credentials it does not schedule work. This matches the established
decision in step 169.

Every bounded MCP result used for closure independently satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 7. Contract Preservation

The final delayed projection retained:

- 14 public variables;
- all five user-enabled Archive logging contracts;
- queryable archive history;
- unchanged command-evidence hash;
- unchanged identity, topology and subscription hashes;
- connected Account authentication without reauthentication requirement; and
- REST as the only authority for public mower state.

No mower command, OAuth action, service restart, Core-object creation,
deletion or reparenting occurred during closure.

## 8. Classification

One aggregate label would hide materially different results. The accepted
classification is therefore decomposed:

| Gate | Decision |
|---|---|
| position reception | PASS |
| monotonic pilot-wide position accounting | PASS |
| coordinate privacy and cleanup | PASS |
| REST authority and compatibility | PASS |
| credential rotation observed | PASS |
| transport stability | FAIL, SECOND EPISODE DID NOT STOP PILOT |
| recovery exhaustion inside approved window | NOT PROVED |
| post-deadline recovery behavior | ONE EXHAUSTED SEQUENCE |
| 72-hour automatic closure | FAIL, CLOSED AT 88.325 HOURS |
| natural-cycle count from final aggregate | NOT PROVED |
| mandatory immediate cleanup | PASS |
| mandatory delayed cleanup | PASS |
| overall receive-only pilot | FAIL WITH USEFUL POSITION EVIDENCE |

MQTT and position diagnostics remain disabled by default. This result does not
authorize permanent operation, Store exposure, direct MQTT variable authority
or another live pilot.

## 9. Architecture Decisions

### AD-NAV-1289: Bind the 1200-second horizon to restart-free activation

The smaller threshold is valid only for the bounded activation and baseline
path that explicitly excludes a service restart. It does not replace the
larger restart-test budget.

### AD-NAV-1290: Evaluate session deltas instead of cumulative counters

Lifetime MQTT diagnostics preserve valuable history. Pilot evaluation must
subtract the accepted active baseline so older sessions are not attributed to
the current window.

### AD-NAV-1291: Accept coordinate-free accounting independently

The monotonic accumulator solved the position-counter defect even though the
transport pilot later failed. Useful subsystem evidence is retained without
weakening the overall gate.

### AD-NAV-1292: Separate in-window failure from post-deadline evidence

Repeated episodes failed the approved pilot inside its window. The later
exhaustion remains forensic evidence and does not alter the deadline boundary.

### AD-NAV-1293: Keep the 72-hour deadline hard

Late observation is forensic evidence, not an extension. Future pilots need a
native or external automatic closure mechanism independent of an interactive
chat checkpoint.

### AD-NAV-1294: Retain historical retry state while disabled

`reconnectAttempt` need not be zero when `Disabled`, `nextAttemptAt=0`, Core
transport inactive and credentials absent prove that no retry is scheduled.

### AD-NAV-1295: Keep REST authoritative and MQTT opt-in

The pilot does not change the public state contract. MQTT remains receive-only,
diagnostic and disabled by default.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| activation attempts | 1 |
| activation Account `ApplyChanges()` | 1 |
| cleanup Account `ApplyChanges()` | 1 |
| automatic activation retries | 0 |
| explicit MQTT publish operations | 0 |
| mower commands | 0 |
| service restarts | 0 |
| OAuth actions by the pilot harness | 0 |
| Core objects created or deleted | 0 |

## 11. Private Evidence

Machine-readable evidence remains below the ignored private overlay. Public
documentation contains only aggregate counts, timestamps, statuses and hashes.
It contains no credential, endpoint, topic, device identity, ObjectID,
coordinate or garden geometry.

## 12. Next Step

Create step 310 as an analysis and design gate with two independent work
packages:

1. correlate the final exhausted episode with bounded native Core status,
   credential rotation, REST and ingress timing without another activation;
2. design automatic closure for the second transport episode and hard deadline;
3. define, but do not yet implement, invariants for a possible conservative
   outer recovery policy without retrying authentication or configuration
   failures.

No retry-policy implementation or additional live activation should occur
until that evidence review is complete and separately approved.
