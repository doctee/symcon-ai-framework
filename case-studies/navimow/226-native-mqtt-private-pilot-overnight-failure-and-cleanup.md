# 226 Native MQTT Private Pilot Overnight Failure and Cleanup

**Case study:** Navimow native IP-Symcon module
**Status:** Pilot closed as `FAIL` after missed evidence window and multiple
transport episodes; immediate and delayed credential-free cleanup passed
**Date:** 2026-07-30
**Scope:** Reconstruct the missed overnight checkpoint, evaluate the harness
and execute mandatory cleanup

## 1. Expected Automation

Step 225 required the nominal six-hour checkpoint near:

```text
2026-07-30 01:19:00 Europe/Berlin
```

A one-time Codex heartbeat was proposed in the application. Inspection on the
morning of 2026-07-30 found:

```text
matching active automation:       absent
step-226 report:                  absent
overnight private snapshot:       absent
harness last snapshot:            +63 min checkpoint
```

The proposal had not become a persisted executable automation. No overnight
checkpoint ran.

## 2. Evidence Gap

The next manual read-only projection occurred at:

```text
previous snapshot: 2026-07-29 20:22:38 Europe/Berlin
next snapshot:     2026-07-30 06:31:56 Europe/Berlin
gap:               36558 seconds
maximum:           21600 seconds
```

The 10-hour, 9-minute, 18-second gap exceeds the executable six-hour limit by
14958 seconds.

The harness therefore records:

```text
evidence-gap-exceeded
```

This is an observation-process failure. It does not by itself prove a module
or transport failure.

## 3. Morning Technical State

The bounded read-only projection itself passed:

```text
transportError: null
executionError: null
truncated:      false
projection pass: true
```

At that instant:

```text
repository:           clean and valid main@3d223a9c
lifecycle:            ShadowActive / healthy
MQTT/WebSocket:       102 / 102
Account:              Connected
ReauthRequired:       false
REST:                 operational and authoritative
contracts:            unchanged
MQTT hint:            available
```

The transport had recovered and was healthy when observed.

## 4. Overnight Counter Evidence

Relative to step 225:

| Counter | Delta |
|---|---:|
| connection attempts | +13 |
| connection successes | +13 |
| connection failures | 0 |
| credential rotations | +11 |
| unexpected disconnects | +3 |
| reconnect attempts | +2 |
| reconnect exhausted | 0 |
| received | +243 |
| accepted | +243 |
| rejected | 0 |

All eleven credential rotations and two reconnect paths had matching successful
connection capacity and no connection failure. Natural ingress continued.

However, the harness policy allows at most one recovered transport episode.
Three additional unexpected disconnects produce:

```text
multiple-transport-episodes
```

The broad evidence gap prevents a more precise temporal reconstruction of the
three disconnects from these point-in-time counters.

## 5. Harness Stop

After ingesting the morning snapshot:

```text
phase:                stop-required
classification:       FAIL
completed cycles:     1
credential rotations: 12
transport episodes:   3
stop reasons:
  - evidence-gap-exceeded
  - multiple-transport-episodes
```

The pilot could not continue under the accepted operating policy even though
the live transport was healthy at read time.

## 6. Mandatory Cleanup

The persistence acceptance from step 221 required cleanup at every stop
criterion. The cleanup runner executed:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
```

Immediate result:

```text
MQTT feature:           disabled
MQTT/WebSocket:         104 / 104
WebSocket active:       false
Authorization:          absent
MQTT username/password: absent
REST:                   operational
contracts:              unchanged
```

A second independent read-only projection 193 seconds later proved the same
credential-free state.

## 7. Final State

The final harness result is:

```text
phase:             closed
classification:    FAIL
cleanup complete:  true
evidence complete: false
```

The installation is now:

```text
MQTT:                  disabled
WebSocket:             inactive
MQTT credentials:      absent
REST:                  operational and authoritative
variables:             14 retained
Archive loggings:      5 retained
```

No mower command or service restart occurred.

## 8. Interpretation

The pilot did not pass its operating contract. Two distinct findings must not
be conflated:

1. **Observation failure:** the proposed automation was not active, producing
   an evidence gap above six hours.
2. **Transport-policy failure:** three recovered disconnect episodes exceeded
   the deliberately conservative single-episode limit.

Positive technical evidence remains:

- 11 further credential rotations completed;
- every added connection attempt had a matching success;
- connection failures and reconnect exhaustion remained zero;
- MQTT ingress continued;
- REST remained healthy and authoritative;
- complete cleanup succeeded.

These positives do not override the frozen `FAIL` decision.

## 9. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/native-mqtt-private-pilot/
  overnight-failure-and-cleanup-evidence-closure.json
  pilot-state.json
  snapshots/checkpoint-overnight-late.json
  snapshots/cleanup-immediate.json
  snapshots/cleanup-delayed.json
```

The private cleanup runner hash is:

```text
c94e023070bf07285043e99e9af2ea06c79370e45c8df6b82442626d4342cf40
```

## 10. Architecture Decisions

### AD-NAV-817: Treat an automation proposal as inactive until persisted

**Decision:** Require an inspectable active automation record before relying
on an unattended checkpoint.

**Reason:** Rendering a proposal card does not prove that the scheduled
heartbeat exists or will execute.

### AD-NAV-818: Separate observation failure from transport health

**Decision:** Classify the missing checkpoint as a pilot evidence failure
without claiming that MQTT was unavailable.

**Reason:** The morning projection proved the transport healthy, but cannot
reconstruct the missing interval completely.

### AD-NAV-819: Enforce the multiple-episode hard stop

**Decision:** Preserve the frozen stop after three additional unexpected
disconnects even though recovery succeeded.

**Reason:** Changing the acceptance threshold after observing the data would
invalidate the pilot contract.

### AD-NAV-820: Execute cleanup immediately after classification

**Decision:** Disable the feature once and prove credential removal
immediately and after 193 seconds.

**Reason:** The accepted policy makes cleanup mandatory at any hard stop.

## 11. Side-Effect Accounting

| Operation | Count |
|---|---:|
| morning read-only active projection | 1 |
| MQTT feature disable | 1 |
| Account cleanup `ApplyChanges()` | 1 |
| immediate cleanup projection | 1 |
| delayed cleanup projection | 1 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |

## 12. Gate Decision

| Gate | Decision |
|---|---|
| overnight automation execution | FAIL |
| maximum evidence gap | FAIL |
| morning point-in-time transport health | PASS |
| credential rotations | TECHNICALLY PASS |
| multiple transport episodes | FAIL |
| REST authority | PASS |
| structural contracts | PASS |
| immediate cleanup | PASS |
| delayed cleanup | PASS |
| final credential-free state | PASS |
| pilot classification | `FAIL` |
| pilot continuation | CLOSED |

## 13. Next Step

Proceed with:

```text
227-native-mqtt-private-pilot-failure-analysis-and-retest-decision.md
```

That analysis should:

1. determine why the suggested heartbeat was not persisted;
2. assess whether the one-episode policy is appropriate for normal household
   network variability;
3. distinguish OAuth-driven reconnects from genuine unexpected disconnects;
4. decide whether a shorter supervised retest or a revised automated pilot is
   justified;
5. require new persistence and activation authorization before any reactivation.
