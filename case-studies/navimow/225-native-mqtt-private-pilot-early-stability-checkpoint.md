# 225 Native MQTT Private Pilot Early Stability Checkpoint

**Case study:** Navimow native IP-Symcon module
**Status:** Delayed early and first-hour checkpoint passed; one natural cycle
and one healthy credential rotation recorded, pilot remains running
**Date:** 2026-07-29
**Scope:** Capture and ingest the first bounded active checkpoint

## 1. Timing

The nominal `+15 min` checkpoint from step 224 was not captured at its exact
time. The next read-only projection occurred at:

```text
pilot start:       2026-07-29 19:19:00 Europe/Berlin
checkpoint:        2026-07-29 20:22:38 Europe/Berlin
elapsed:           3818 seconds
nominal +15 min:    900 seconds
nominal +60 min:   3600 seconds
maximum gap:      21600 seconds
```

The snapshot is 63 minutes and 38 seconds after start. It therefore covers the
missed early-stability observation and the planned first-hour checkpoint with
one bounded read.

The delay is not a stop criterion. It remains far below the executable
six-hour evidence-gap limit.

## 2. MCP Result

The complete private read-only projection returned:

```text
transportError: null
executionError: null
truncated:      false
projection pass: true
```

No Symcon property, timer, variable, Core configuration or mower state was
changed.

## 3. Runtime Health

The checkpoint proved:

```text
repository:             clean and valid main@3d223a9c
lifecycle:              ShadowActive / healthy
MQTT/WebSocket:         102 / 102
WebSocket active:       true
Account:                Connected
ReauthRequired:         false
REST:                   operational and authoritative
MQTT hint:              available
MQTT/REST comparison:   match
structural contracts:   unchanged
variables:              14
Archive loggings:       5
```

No ownership, subscription, archive, topology or command-evidence drift was
detected.

## 4. Passive Credential Rotation

Relative to the second active baseline:

| Counter | Before | Checkpoint | Delta |
|---|---:|---:|---:|
| credential rotations | 0 | 1 | +1 |
| connection attempts | 16 | 17 | +1 |
| connection successes | 8 | 9 | +1 |
| connection failures | 0 | 0 | 0 |

The rotation therefore satisfies the harness contract:

```text
one rotation
one corresponding attempt
one corresponding success
zero failure
final lifecycle ShadowActive/healthy
```

This fulfills the pilot's minimum credential-rotation evidence requirement.

## 5. Natural MQTT Evidence

Current bounded counters:

```text
received:                 1853
accepted:                 1853
rejected:                    0
reconciliation attempts:   131
comparison matches:        118
comparison mismatches:       0
comparison stale:            0
```

The latest identity-free MQTT hint was available and matched the
REST-authoritative projection. No MQTT payload directly wrote a public Device
variable.

## 6. Natural Mowing Cycle

The eight-hour Archive Control projection contained the complete sequence:

```text
Running -> Docking -> Docked
```

The harness deduplicated and accepted it as one natural completed cycle.
No pilot command was sent.

The public report intentionally omits private timestamps, device identity,
topics, payloads, coordinates and garden data.

## 7. Harness Result

After ingesting the checkpoint:

```text
phase:                active
classification:       RUNNING
completed cycles:     1
credential rotations: 1
transport episodes:   0
stop reasons:         none
evidence complete:    false
cleanup armed:        true
```

The credential-rotation requirement is complete. At least one additional
natural complete mowing cycle and the 48-hour minimum duration remain
required for a normal `PASS` closure.

## 8. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/native-mqtt-private-pilot/
  checkpoint-plus-63m-evidence-closure.json
  pilot-state.json
  snapshots/checkpoint-plus-63m.json
```

## 9. Architecture Decisions

### AD-NAV-813: Let one bounded read cover both missed checkpoints

**Decision:** Treat the `+63 min` projection as the delayed early-stability
check and the first-hour checkpoint.

**Reason:** It is later than both nominal points, within the six-hour evidence
limit and avoids duplicating live reads without additional value.

### AD-NAV-814: Preserve the missed-time fact

**Decision:** Document the missed `+15 min` point rather than relabeling the
snapshot as on-time.

**Reason:** Operational evidence must retain actual timing even when the
result remains valid.

### AD-NAV-815: Accept only the complete rotation delta

**Decision:** Count the observed credential rotation because attempt and
success each advanced once, failures stayed zero and health recovered.

**Reason:** An expiry increase alone would not prove successful MQTT
credential replacement.

### AD-NAV-816: Reconstruct cycles from Archive Control

**Decision:** Count only the complete archived `Running -> Docking -> Docked`
sequence.

**Reason:** Point-in-time status alone cannot prove the intermediate docking
transition.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only projections | 1 |
| Symcon mutations | 0 |
| credential requests | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |

## 11. Gate Decision

| Gate | Decision |
|---|---|
| delayed early-stability checkpoint | PASS |
| first-hour checkpoint | PASS |
| maximum evidence gap | PASS |
| active transport health | PASS |
| REST authority | PASS |
| structural contracts | PASS |
| one credential rotation | PASS |
| first natural cycle | PASS |
| second natural cycle | PENDING |
| 48-hour minimum | PENDING |
| pilot classification | `RUNNING` |
| cleanup | ARMED |

## 12. Next Step

Proceed with:

```text
226-native-mqtt-private-pilot-six-hour-checkpoint.md
```

The nominal `+6 h` checkpoint is:

```text
2026-07-30 01:19:00 Europe/Berlin
```

Capture one complete read-only projection near that point and ingest it as
`checkpoint`. To remain within the hard evidence-gap contract, the next valid
snapshot must occur no later than six hours after this checkpoint.
