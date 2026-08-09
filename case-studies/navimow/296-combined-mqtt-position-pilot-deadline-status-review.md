# 296 Combined MQTT Position Pilot Deadline Status Review

**Case study:** Navimow native IP-Symcon module

**Status:** Seventy-two-hour deadline reached; position reception and four
natural cycles proved, but transport episode and position monotonicity gates
failed; mandatory cleanup awaiting separate authorization

**Date:** 2026-08-09

## 1. Result

The pilot reached its mandatory deadline. A bounded read-only projection still
found the receive-only chain active and operational:

```text
REST authority:       operational
lifecycle:            ShadowActive
MQTT Core:            102
WebSocket Core:       102
position status:      available
```

Operational recovery remained functional, but the frozen stability contract
did not pass. The overall pilot result is `FAIL` and cleanup is mandatory.

## 2. Transport Result

Relative to the second active baseline:

| Evidence | Delta |
|---|---:|
| native checkpoints | 14 |
| distinct transport episodes | 14 |
| unexpected-disconnect observations | 18 |
| duplicate observations | 4 |
| reconnect attempts | 18 |
| exhausted reconnects | 0 |
| credential rotations | 79 |
| MQTT messages received | 15316 |
| MQTT messages accepted | 15315 |
| MQTT messages rejected | 1 |

The pilot permits at most one distinct transport episode. Fourteen episodes
are therefore a hard stop even though every observed episode recovered and no
retry exhaustion occurred.

## 3. Natural Mowing Cycles

The standard probe retains only eight hours of REST archive history. A second
bounded read-only query covered the exact pilot window and returned twelve
state transitions forming four complete natural cycles:

```text
Running -> Docking -> Docked: 4
```

The natural-cycle requirement passes. No mower command was used.

## 4. Position Result

Position reception is technically proved:

- 14 current-session native checkpoints;
- 13 checkpoints with available position evidence;
- two high-activity windows with more than 800 and 1200 samples;
- more than two natural cycles overlapping position growth;
- current live position projection available;
- no dropped or downsampled samples in the final projection.

The position counter restarted across transport reconnections. Seven decreases
were observed between consecutive native checkpoints, and two high-activity
windows reported out-of-order timestamp evidence. The required monotonic
counter contract therefore fails.

This separates two conclusions:

```text
position data reception:       PROVED
position pilot contract:       FAIL
```

## 5. Harness Deadline Behavior

The local harness correctly rejected ingesting a snapshot captured after the
mandatory deadline with `snapshot-after-deadline`; its state file remained
unchanged.

Its unchanged status projection is consequently
`READY_TO_CLOSE_INCONCLUSIVE`. The fuller immutable forensic evidence proves
hard-stop conditions and therefore determines the final `FAIL` classification.
The gap also shows that deadline reconstruction must not depend on a probe
executed only after the deadline.

## 6. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only Symcon projections | 2 |
| Symcon mutations | 0 |
| MQTT activations | 0 |
| credential requests | 0 |
| OAuth actions | 0 |
| service restarts | 0 |
| mower commands | 0 |

Every MCP call reported `transportError=null`, `executionError=null` and
`truncated=false`.

## 7. Architecture Decisions

### AD-NAV-1246: Separate availability from stability

Continuous recovery and active Core status do not compensate for exceeding the
distinct-episode limit.

### AD-NAV-1247: Preserve useful position evidence on pilot failure

The transport pilot fails, but the observed position stream remains valid
evidence for a separately redesigned position accumulator.

### AD-NAV-1248: Reconstruct the full REST window read-only

Deadline review must query the bounded pilot interval when the regular probe's
short archive horizon no longer contains earlier natural cycles.

### AD-NAV-1249: Do not mutate an expired harness state

Post-deadline forensic evidence is retained separately instead of falsifying a
snapshot timestamp to force state-machine ingestion.

## 8. Gate Status

| Gate | Status |
|---|---|
| 72-hour window | COMPLETE |
| natural REST cycles | PASS, FOUR |
| position reception | PASS |
| position monotonicity | FAIL |
| transport episode bound | FAIL |
| overall pilot | FAIL |
| mandatory cleanup | OPEN, AUTHORIZATION REQUIRED |

## 9. Next Step

Execute the already validated combined cleanup once: disable MQTT transport
and position diagnostics together, apply the Account configuration once, then
perform immediate and delayed read-only verification. After cleanup, analyze
why Core reconnects reset the position accumulator and revise the unattended
deadline harness before another pilot.
